<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BotAI.php                                                 ##
##  Type           : Autonomous Bot AI Engine for TravianZ                    ##
##  Purpose        : Automated building, troop training, and early-game raids  ##
##                   for simulated bot players. Designed for co-op & multiplayer##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

require_once __DIR__ . '/Data/buidata.php';
require_once __DIR__ . '/Data/unitdata.php';

class BotAI {

    /**
     * Main execution cycle for all active bots.
     * Can be invoked from Automation.php or standalone via CLI.
     *
     * @param bool $force If true, bypasses the interval throttle.
     * @return array Execution report.
     */
    public static function run($force = false) {
        global $database;

        if (defined('BOT_AI_ENABLED') && !BOT_AI_ENABLED) {
            return ['status' => 'disabled', 'message' => 'Bot AI is disabled in config.php'];
        }

        $interval = defined('BOT_AI_INTERVAL') ? (int)BOT_AI_INTERVAL : 180;
        $lockFile = __DIR__ . '/Prevention/bot_ai.txt';

        if (!$force && file_exists($lockFile)) {
            $lastRun = filemtime($lockFile);
            if ((time() - $lastRun) < $interval) {
                return [
                    'status' => 'throttled',
                    'message' => 'Next Bot AI run in ' . ($interval - (time() - $lastRun)) . 's'
                ];
            }
        }

        @touch($lockFile);

        // Fetch all active bot accounts and their villages
        $sql = "SELECT u.id as uid, u.username, u.tribe, u.access,
                       v.wref, v.name as vname, v.pop, v.wood, v.clay, v.iron, v.crop, v.maxstore, v.maxcrop
                FROM " . TB_PREFIX . "users u
                JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
                WHERE u.access = 2
                  AND (u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
                ORDER BY u.id ASC";

        $bots = $database->query_return($sql);

        if (empty($bots)) {
            return ['status' => 'idle', 'message' => 'No active bot accounts found', 'bots_processed' => 0];
        }

        $logs = [];

        foreach ($bots as $bot) {
            $botLogs = [];

            // 1. Maintain resources & storage
            self::replenishResources($bot);

            // 2. Automated Village Upkeep / Building progression
            $buildResult = self::processBuild($bot);
            if ($buildResult) {
                $botLogs[] = "Build: " . $buildResult;
            }

            // 3. Automated Troop Training
            $trainResult = self::processTrain($bot);
            if ($trainResult) {
                $botLogs[] = "Train: " . $trainResult;
            }

            // 4. Automated Raiding / Attacks
            $attackResult = self::processAttack($bot);
            if ($attackResult) {
                $botLogs[] = "Attack: " . $attackResult;
            }

            if (!empty($botLogs)) {
                $logs[$bot['username']] = $botLogs;
            }
        }

        return [
            'status' => 'success',
            'bots_total' => count($bots),
            'bots_active' => count($logs),
            'logs' => $logs
        ];
    }

    /**
     * Replenish resources for bot villages so they grow and can be farmed.
     * Dynamically scales production with village population and ensures
     * storage capacity reflects current warehouse/granary building levels.
     */
    private static function replenishResources(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $pop = max(10, (int)$bot['pop']);
        $maxStore = max(1200, (int)$bot['maxstore']);
        $maxCrop = max(1200, (int)$bot['maxcrop']);

        // Check if warehouse/granary has upgraded beyond base capacity
        $fdata = $database->getResourceLevel($wid, false);
        if ($fdata) {
            $whLevel = (int)($fdata['f25'] ?? 0);
            $grLevel = (int)($fdata['f28'] ?? 0);
            if ($whLevel > 0) {
                $maxStore = max($maxStore, self::getStorageCapacity($whLevel));
            }
            if ($grLevel > 0) {
                $maxCrop = max($maxCrop, self::getStorageCapacity($grLevel));
            }
        }

        // Grant organic production boost per tick
        // Scales dynamically with population when BOT_AI_DYNAMIC_SCALING is enabled
        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') && BOT_AI_DYNAMIC_SCALING;
        if ($dynamicScaling) {
            $boost = max(250, (int)round($pop * 3.5)) * SPEED;
        } else {
            $boost = 250 * SPEED;
        }

        $wood = min($maxStore, (int)$bot['wood'] + $boost);
        $clay = min($maxStore, (int)$bot['clay'] + $boost);
        $iron = min($maxStore, (int)$bot['iron'] + $boost);
        $crop = min($maxCrop, max(150, (int)$bot['crop'] + $boost));

        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = $wood, clay = $clay, iron = $iron, crop = $crop,
                maxstore = $maxStore, maxcrop = $maxCrop, lastupdate = " . time() . "
            WHERE wref = $wid
        ");
    }

    /**
     * Process progressive building construction for bot village.
     * Supports early-game baseline and dynamic mid/late-game expansion:
     * - Unlocks Academy (slot 22), Stable (slot 20), Marketplace (slot 27), Residence (slot 30)
     * - Scales resource fields up to level 10
     * - Scales city buildings up to BOT_AI_MAX_BUILDING_LEVEL (lvl 20)
     */
    public static function processBuild(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $tribe = (int)$bot['tribe'];
        $fdata = $database->getResourceLevel($wid, false);

        if (!$fdata) {
            return null;
        }

        $wallType = self::getTribeWallType($tribe);
        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') && BOT_AI_DYNAMIC_SCALING;
        $maxFieldLvl = $dynamicScaling ? 10 : (defined('BOT_MAX_FIELD_LEVEL') ? (int)BOT_MAX_FIELD_LEVEL : 6);
        $maxBuildingLvl = ($dynamicScaling && defined('BOT_AI_MAX_BUILDING_LEVEL')) ? (int)BOT_AI_MAX_BUILDING_LEVEL : 8;
        $enableCavalry = defined('BOT_AI_ENABLE_CAVALRY') && BOT_AI_ENABLE_CAVALRY;

        // Infrastructure levels
        $mbLevel = (int)($fdata['f26'] ?? 0); // slot 26: Main Building (type 15)
        $whLevel = (int)($fdata['f25'] ?? 0); // slot 25: Warehouse (type 10)
        $grLevel = (int)($fdata['f28'] ?? 0); // slot 28: Granary (type 11)
        $rpLevel = (int)($fdata['f39'] ?? 0); // slot 39: Rally Point (type 16)
        $brLevel = (int)($fdata['f21'] ?? 0); // slot 21: Barracks (type 19)
        $wlLevel = (int)($fdata['f40'] ?? 0); // slot 40: Wall (type $wallType)
        $crLevel = (int)($fdata['f31'] ?? 0); // slot 31: Cranny (type 23)

        // Advanced buildings (dynamic scaling)
        $acLevel = (int)($fdata['f22'] ?? 0); // slot 22: Academy (type 22)
        $stLevel = (int)($fdata['f20'] ?? 0); // slot 20: Stable (type 20)
        $mpLevel = (int)($fdata['f27'] ?? 0); // slot 27: Marketplace (type 17)
        $rsLevel = (int)($fdata['f30'] ?? 0); // slot 30: Residence (type 25)

        // Find minimum level among resource fields (f1..f18)
        $minFieldLevel = 999;
        $lowestFields = [];
        for ($i = 1; $i <= 18; $i++) {
            $lvl = (int)($fdata['f' . $i] ?? 0);
            if ($lvl < $minFieldLevel) {
                $minFieldLevel = $lvl;
                $lowestFields = [$i];
            } elseif ($lvl === $minFieldLevel) {
                $lowestFields[] = $i;
            }
        }

        $upgraded = null;

        // Phase 1: Early-game foundational progression (lvl 1 baseline)
        if ($minFieldLevel < 1) {
            $slot = $lowestFields[array_rand($lowestFields)];
            $targetLevel = 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
            $upgraded = "Resource field slot $slot to lvl $targetLevel";
        } elseif ($mbLevel < 3) {
            $targetLevel = $mbLevel + 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f26 = $targetLevel, f26t = 15 WHERE vref = $wid");
            $upgraded = "Main Building (slot 26) to lvl $targetLevel";
        } elseif ($whLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f25 = 1, f25t = 10 WHERE vref = $wid");
            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxstore = 1200 WHERE wref = $wid");
            $upgraded = "Constructed Warehouse (slot 25) lvl 1";
        } elseif ($grLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f28 = 1, f28t = 11 WHERE vref = $wid");
            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxcrop = 1200 WHERE wref = $wid");
            $upgraded = "Constructed Granary (slot 28) lvl 1";
        } elseif ($rpLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f39 = 1, f39t = 16 WHERE vref = $wid");
            $upgraded = "Constructed Rally Point (slot 39) lvl 1";
        } elseif ($brLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f21 = 1, f21t = 19 WHERE vref = $wid");
            $upgraded = "Constructed Barracks (slot 21) lvl 1";
        } elseif ($wlLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f40 = 1, f40t = $wallType WHERE vref = $wid");
            $upgraded = "Constructed Wall (slot 40) lvl 1";
        } elseif ($crLevel < 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f31 = 1, f31t = 23 WHERE vref = $wid");
            $upgraded = "Constructed Cranny (slot 31) lvl 1";
        }
        // Phase 2: Construct advanced buildings when prerequisites are satisfied
        elseif ($dynamicScaling && $acLevel < 1 && $mbLevel >= 3 && $brLevel >= 3) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f22 = 1, f22t = 22 WHERE vref = $wid");
            $upgraded = "Constructed Academy (slot 22) lvl 1";
        } elseif ($dynamicScaling && $mpLevel < 1 && $mbLevel >= 3 && $whLevel >= 1 && $grLevel >= 1) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f27 = 1, f27t = 17 WHERE vref = $wid");
            $upgraded = "Constructed Marketplace (slot 27) lvl 1";
        } elseif ($dynamicScaling && $enableCavalry && $stLevel < 1 && $mbLevel >= 5 && $acLevel >= 5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f20 = 1, f20t = 20 WHERE vref = $wid");
            $upgraded = "Constructed Stable (slot 20) lvl 1";
        } elseif ($dynamicScaling && $rsLevel < 1 && $mbLevel >= 5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f30 = 1, f30t = 25 WHERE vref = $wid");
            $upgraded = "Constructed Residence (slot 30) lvl 1";
        }
        // Phase 3: Progressive expansion (Resource Fields vs City Infrastructure)
        else {
            // Storage check: ensure Warehouse & Granary keep pace with resource fields
            $needsStorageUpgrade = false;
            if ($minFieldLevel >= 3) {
                if ($whLevel < min($maxBuildingLvl, $minFieldLevel - 1)) {
                    $targetWH = $whLevel + 1;
                    $cap = self::getStorageCapacity($targetWH);
                    $database->query("UPDATE " . TB_PREFIX . "fdata SET f25 = $targetWH, f25t = 10 WHERE vref = $wid");
                    $database->query("UPDATE " . TB_PREFIX . "vdata SET maxstore = $cap WHERE wref = $wid");
                    $upgraded = "Upgraded Warehouse (slot 25) to lvl $targetWH (Cap: $cap)";
                    $needsStorageUpgrade = true;
                } elseif ($grLevel < min($maxBuildingLvl, $minFieldLevel - 1)) {
                    $targetGR = $grLevel + 1;
                    $cap = self::getStorageCapacity($targetGR);
                    $database->query("UPDATE " . TB_PREFIX . "fdata SET f28 = $targetGR, f28t = 11 WHERE vref = $wid");
                    $database->query("UPDATE " . TB_PREFIX . "vdata SET maxcrop = $cap WHERE wref = $wid");
                    $upgraded = "Upgraded Granary (slot 28) to lvl $targetGR (Cap: $cap)";
                    $needsStorageUpgrade = true;
                }
            }

            if (!$needsStorageUpgrade) {
                // If fields below maxFieldLvl and roll succeeds, upgrade field
                $upgradeFieldChance = $dynamicScaling ? 55 : 65;
                if ($minFieldLevel < $maxFieldLvl && rand(1, 100) <= $upgradeFieldChance) {
                    $slot = $lowestFields[array_rand($lowestFields)];
                    $targetLevel = $minFieldLevel + 1;
                    $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
                    $upgraded = "Resource field slot $slot to lvl $targetLevel";
                } else {
                    // Upgrade city buildings
                    $candidates = [];
                    if ($mbLevel < $maxBuildingLvl) $candidates[] = ['slot' => 26, 'type' => 15, 'lvl' => $mbLevel + 1, 'name' => 'Main Building'];
                    if ($brLevel < $maxBuildingLvl) $candidates[] = ['slot' => 21, 'type' => 19, 'lvl' => $brLevel + 1, 'name' => 'Barracks'];
                    if ($whLevel < $maxBuildingLvl) $candidates[] = ['slot' => 25, 'type' => 10, 'lvl' => $whLevel + 1, 'name' => 'Warehouse'];
                    if ($grLevel < $maxBuildingLvl) $candidates[] = ['slot' => 28, 'type' => 11, 'lvl' => $grLevel + 1, 'name' => 'Granary'];
                    if ($wlLevel < $maxBuildingLvl) $candidates[] = ['slot' => 40, 'type' => $wallType, 'lvl' => $wlLevel + 1, 'name' => 'Wall'];

                    $crMax = $dynamicScaling ? 10 : 3;
                    if ($crLevel < $crMax) $candidates[] = ['slot' => 31, 'type' => 23, 'lvl' => $crLevel + 1, 'name' => 'Cranny'];

                    if ($dynamicScaling) {
                        if ($acLevel > 0 && $acLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 22, 'type' => 22, 'lvl' => $acLevel + 1, 'name' => 'Academy'];
                        }
                        if ($stLevel > 0 && $stLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 20, 'type' => 20, 'lvl' => $stLevel + 1, 'name' => 'Stable'];
                        }
                        if ($mpLevel > 0 && $mpLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 27, 'type' => 17, 'lvl' => $mpLevel + 1, 'name' => 'Marketplace'];
                        }
                        if ($rsLevel > 0 && $rsLevel < min(10, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 30, 'type' => 25, 'lvl' => $rsLevel + 1, 'name' => 'Residence'];
                        }
                    }

                    if (!empty($candidates)) {
                        $choice = $candidates[array_rand($candidates)];
                        $slot = $choice['slot'];
                        $type = $choice['type'];
                        $lvl = $choice['lvl'];
                        $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $lvl, f{$slot}t = $type WHERE vref = $wid");

                        // Update storage capacities if warehouse or granary
                        if ($type == 10) {
                            $storageCap = self::getStorageCapacity($lvl);
                            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxstore = $storageCap WHERE wref = $wid");
                        } elseif ($type == 11) {
                            $cropCap = self::getStorageCapacity($lvl);
                            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxcrop = $cropCap WHERE wref = $wid");
                        }

                        $upgraded = "Upgraded {$choice['name']} (slot $slot) to lvl $lvl";
                    } elseif ($minFieldLevel < $maxFieldLvl) {
                        $slot = $lowestFields[array_rand($lowestFields)];
                        $targetLevel = $minFieldLevel + 1;
                        $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
                        $upgraded = "Resource field slot $slot to lvl $targetLevel";
                    }
                }
            }
        }

        if ($upgraded) {
            self::recountPop($wid);
        }

        return $upgraded;
    }

    /**
     * Process training of military units.
     * Supports dynamic scaling:
     * - Army cap scales with village population (e.g. pop * 1.8 up to 3000)
     * - Counts full army across all 10 unit columns and outgoing attacks
     * - Produces multi-tier units (advanced infantry & cavalry) when buildings allow
     * - Batch size scales proportionally with village size
     */
    public static function processTrain(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $tribe = (int)$bot['tribe'];
        $pop = max(10, (int)$bot['pop']);
        $fdata = $database->getResourceLevel($wid, false);

        // Barracks must be built
        $brLevel = (int)($fdata['f21'] ?? 0);
        if ($brLevel < 1) {
            return null;
        }

        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') && BOT_AI_DYNAMIC_SCALING;
        $popFactor = defined('BOT_AI_TROOP_POP_FACTOR') ? (float)BOT_AI_TROOP_POP_FACTOR : 1.8;
        $maxTroopsCap = defined('BOT_AI_MAX_TROOPS_CAP') ? (int)BOT_AI_MAX_TROOPS_CAP : 3000;
        $baseMax = defined('BOT_AI_MAX_TROOPS') ? (int)BOT_AI_MAX_TROOPS : 45;

        // Dynamic maximum army capacity
        $maxTroops = $dynamicScaling ? max($baseMax, min($maxTroopsCap, (int)round($pop * $popFactor))) : $baseMax;

        $tribeUnits = self::getTribeUnits($tribe);

        // Sum standing army across all 10 unit columns for this tribe
        $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
        $colsSql = implode(', ', $unitCols);
        $res = $database->query_return("SELECT {$colsSql} FROM " . TB_PREFIX . "units WHERE vref = $wid");

        $atHomeTotal = 0;
        if (!empty($res[0])) {
            foreach ($tribeUnits['all'] as $u) {
                $atHomeTotal += (int)($res[0]['u' . $u] ?? 0);
            }
        }

        // Units marching in outgoing attacks (sum t1 through t10)
        $resMarching = $database->query_return("
            SELECT SUM(a.t1 + a.t2 + a.t3 + a.t4 + a.t5 + a.t6 + a.t7 + a.t8 + a.t9 + a.t10) as marching
            FROM " . TB_PREFIX . "movement m
            JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            WHERE m.from = $wid AND m.sort_type = 3 AND m.proc = 0
        ");
        $marchingTotal = (int)($resMarching[0]['marching'] ?? 0);
        $totalArmy = $atHomeTotal + $marchingTotal;

        if ($totalArmy >= $maxTroops) {
            return null; // Cap reached
        }

        // Determine trainable unit types based on buildings & population
        $acLevel = (int)($fdata['f22'] ?? 0);
        $stLevel = (int)($fdata['f20'] ?? 0);
        $enableCavalry = defined('BOT_AI_ENABLE_CAVALRY') && BOT_AI_ENABLE_CAVALRY;

        $trainCandidates = [];
        // Basic infantry is always eligible
        $trainCandidates[$tribeUnits['basic']] = 40;

        if ($dynamicScaling) {
            // Advanced infantry: Academy >= 3 & Barracks >= 3 & pop >= 90
            if ($acLevel >= 3 && $brLevel >= 3 && $pop >= 90 && !empty($tribeUnits['inf_adv'])) {
                $trainCandidates[$tribeUnits['inf_adv']] = 35;
            }

            // Light Cavalry: Stable >= 1 & enableCavalry & pop >= 150
            if ($enableCavalry && $stLevel >= 1 && $pop >= 150 && !empty($tribeUnits['cav_light'])) {
                $trainCandidates[$tribeUnits['cav_light']] = 25;
            }

            // Heavy Cavalry: Stable >= 5 & enableCavalry & pop >= 250
            if ($enableCavalry && $stLevel >= 5 && $pop >= 250 && !empty($tribeUnits['cav_heavy'])) {
                $trainCandidates[$tribeUnits['cav_heavy']] = 15;
            }
        }

        // Weighted random selection of unit to train
        $randRoll = rand(1, array_sum($trainCandidates));
        $cum = 0;
        $chosenUnit = $tribeUnits['basic'];
        foreach ($trainCandidates as $u => $weight) {
            $cum += $weight;
            if ($randRoll <= $cum) {
                $chosenUnit = $u;
                break;
            }
        }

        // Batch size scales with village population
        if ($dynamicScaling) {
            $batchMin = max(2, (int)floor($pop / 50));
            $batchMax = max(4, (int)ceil($pop / 25));
            $batchSize = rand($batchMin, $batchMax);
        } else {
            $batchSize = rand(2, 4);
        }

        $trainCount = min($batchSize, $maxTroops - $totalArmy);
        if ($trainCount <= 0) {
            return null;
        }

        $col = 'u' . $chosenUnit;
        $database->query("UPDATE " . TB_PREFIX . "units SET {$col} = {$col} + $trainCount WHERE vref = $wid");

        return "Trained +$trainCount unit(s) [u{$chosenUnit}] (Total army: " . ($totalArmy + $trainCount) . "/$maxTroops)";
    }

    /**
     * Process automated raids to nearby villages.
     * Supports dynamic scaling:
     * - Raid party size scales with standing army (up to 120 troops under dynamic scaling)
     * - Dispatches combined arms (infantry + cavalry)
     * - Calculates duration using the slowest marching unit
     */
    public static function processAttack(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $uid = (int)$bot['uid'];
        $tribe = (int)$bot['tribe'];
        $pop = max(10, (int)$bot['pop']);

        $minTroops = defined('BOT_AI_MIN_RAID_TROOPS') ? (int)BOT_AI_MIN_RAID_TROOPS : 6;
        $attackChance = defined('BOT_AI_ATTACK_CHANCE') ? (int)BOT_AI_ATTACK_CHANCE : 35;
        $maxDistance = defined('BOT_AI_MAX_DISTANCE') ? (float)BOT_AI_MAX_DISTANCE : 35.0;
        $maxConcurrent = defined('BOT_AI_MAX_CONCURRENT_ATTACKS') ? (int)BOT_AI_MAX_CONCURRENT_ATTACKS : 1;
        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') && BOT_AI_DYNAMIC_SCALING;

        // Allow up to 2 concurrent raids for developed villages
        if ($dynamicScaling && $pop >= 300) {
            $maxConcurrent = max(2, $maxConcurrent);
        }

        $tribeUnits = self::getTribeUnits($tribe);

        // Fetch available units at home
        $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
        $colsSql = implode(', ', $unitCols);
        $res = $database->query_return("SELECT {$colsSql} FROM " . TB_PREFIX . "units WHERE vref = $wid");

        if (empty($res[0])) {
            return null;
        }

        $homeUnits = $res[0];

        // Identify combat-eligible troop pools
        $combatRoles = ['basic', 'inf_adv', 'cav_light', 'cav_heavy'];
        $availableCombat = [];
        $totalCombatAvailable = 0;

        foreach ($combatRoles as $role) {
            if (isset($tribeUnits[$role])) {
                $uNum = $tribeUnits[$role];
                $cnt = (int)($homeUnits['u' . $uNum] ?? 0);
                if ($cnt > 0) {
                    $availableCombat[$uNum] = $cnt;
                    $totalCombatAvailable += $cnt;
                }
            }
        }

        if ($totalCombatAvailable < $minTroops) {
            return null;
        }

        // Check active outgoing attacks
        $resActive = $database->query_return("
            SELECT COUNT(*) as active_attacks
            FROM " . TB_PREFIX . "movement
            WHERE `from` = $wid AND sort_type = 3 AND proc = 0
        ");
        if ((int)($resActive[0]['active_attacks'] ?? 0) >= $maxConcurrent) {
            return null;
        }

        // Probability roll
        if (rand(1, 100) > $attackChance) {
            return null;
        }

        // Get bot village coordinates
        $botCoor = $database->getCoor($wid);
        if (!$botCoor) {
            return null;
        }

        // Query candidate target villages
        $protectedList = defined('PROTECTED_PLAYERS') && strlen(PROTECTED_PLAYERS) > 0
            ? array_map('trim', explode(',', PROTECTED_PLAYERS))
            : [];

        $targets = $database->query_return("
            SELECT v.wref, v.owner, v.name as vname, v.pop, w.x, w.y, u.username, u.access, u.protect
            FROM " . TB_PREFIX . "vdata v
            JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
            JOIN " . TB_PREFIX . "users u ON v.owner = u.id
            WHERE v.wref != $wid
              AND v.owner != $uid
              AND u.access < 8
              AND u.protect < " . time()
        );

        if (empty($targets)) {
            return null;
        }

        $candidates = [];

        foreach ($targets as $tgt) {
            if (in_array($tgt['username'], $protectedList, true)) {
                continue;
            }

            $dist = self::getDistance($botCoor['x'], $botCoor['y'], $tgt['x'], $tgt['y']);
            if ($dist > $maxDistance || $dist < 0.5) {
                continue;
            }

            // Fair play: Target must not have 2 or more incoming attacks already
            $incoming = $database->query_return("
                SELECT COUNT(*) as total
                FROM " . TB_PREFIX . "movement
                WHERE `to` = " . (int)$tgt['wref'] . " AND sort_type = 3 AND proc = 0
            ");
            if ((int)($incoming[0]['total'] ?? 0) >= 2) {
                continue;
            }

            $tgt['dist'] = $dist;
            $candidates[] = $tgt;
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by distance ascending and pick randomly from closest 3
        usort($candidates, function($a, $b) {
            return $a['dist'] <=> $b['dist'];
        });

        $topCandidates = array_slice($candidates, 0, 3);
        $target = $topCandidates[array_rand($topCandidates)];

        // Calculate raid party size
        if ($dynamicScaling) {
            // Under dynamic scaling: send ~25% to 35% of standing army, up to 120 troops
            $targetPartySize = min(120, max($minTroops, (int)floor($totalCombatAvailable * 0.30)));
        } else {
            // Legacy cap: 5 - 15 troops
            $targetPartySize = min(15, max(5, (int)floor($totalCombatAvailable * 0.40)));
        }

        // Allocate raid quotas across available combat units
        $dispatched = [];
        $dispatchedSpeeds = [];
        $totalDispatched = 0;

        foreach ($availableCombat as $uNum => $avail) {
            $portion = (int)round(($avail / $totalCombatAvailable) * $targetPartySize);
            $send = min($avail, max(0, $portion));
            if ($send > 0) {
                $dispatched[$uNum] = $send;
                $dispatchedSpeeds[] = self::getUnitSpeed($uNum);
                $totalDispatched += $send;
            }
        }

        // Guarantee minimum troops
        if ($totalDispatched < $minTroops) {
            $primaryUnit = key($availableCombat);
            $add = min($availableCombat[$primaryUnit] - ($dispatched[$primaryUnit] ?? 0), $minTroops - $totalDispatched);
            if ($add > 0) {
                $dispatched[$primaryUnit] = ($dispatched[$primaryUnit] ?? 0) + $add;
                $totalDispatched += $add;
                if (!in_array(self::getUnitSpeed($primaryUnit), $dispatchedSpeeds)) {
                    $dispatchedSpeeds[] = self::getUnitSpeed($primaryUnit);
                }
            }
        }

        if ($totalDispatched <= 0) {
            return null;
        }

        // 1. Deduct troops from bot village
        $unitNums = array_keys($dispatched);
        $amounts = array_values($dispatched);
        $modes = array_fill(0, count($amounts), 0);
        $database->modifyUnit($wid, $unitNums, $amounts, $modes);

        // 2. Travel time based on slowest marching unit
        $marchSpeed = !empty($dispatchedSpeeds) ? min($dispatchedSpeeds) : 6;
        $duration = max(30, (int)round(($target['dist'] / $marchSpeed) * 3600 / INCREASE_SPEED));

        // 3. Map dispatched units to tribe slots t1..t10 for addAttack()
        $t = array_fill(1, 10, 0);
        $baseUnit = ($tribe - 1) * 10;
        foreach ($dispatched as $uNum => $cnt) {
            $slot = $uNum - $baseUnit;
            if ($slot >= 1 && $slot <= 10) {
                $t[$slot] = $cnt;
            }
        }

        $attackId = $database->addAttack(
            $wid,
            $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], 0,
            4, // Raid
            0, 0, 0
        );

        if (!$attackId) {
            return null;
        }

        // 4. Create movement entry
        $database->addMovement(
            3, // Attack/Raid
            $wid,
            $target['wref'],
            $attackId,
            time(),
            time() + $duration
        );

        $summaryParts = [];
        foreach ($dispatched as $uNum => $cnt) {
            $summaryParts[] = "u{$uNum}: {$cnt}";
        }
        $summaryStr = implode(', ', $summaryParts);

        return "Raid dispatched! {$totalDispatched} troops [{$summaryStr}] marched towards {$target['vname']} ({$target['username']}) - distance: " . round($target['dist'], 1) . " tiles, arrival in {$duration}s";
    }

    /**
     * Map wrap-around distance calculation.
     */
    public static function getDistance($x1, $y1, $x2, $y2) {
        $worldMax = defined('WORLD_MAX') ? (int)WORLD_MAX : 400;

        $xd = abs($x1 - $x2);
        if ($xd > $worldMax) {
            $xd = (2 * $worldMax + 1) - $xd;
        }

        $yd = abs($y1 - $y2);
        if ($yd > $worldMax) {
            $yd = (2 * $worldMax + 1) - $yd;
        }

        return sqrt($xd * $xd + $yd * $yd);
    }

    /**
     * Get basic infantry unit number for a given tribe.
     */
    public static function getTribeBasicUnit(int $tribe): int {
        return ($tribe - 1) * 10 + 1;
    }

    /**
     * Returns structured unit information for a given tribe.
     *
     * @param int $tribe
     * @return array [
     *   'basic' => int,       // Basic infantry (always trainable in Barracks)
     *   'inf_adv' => int,     // Advanced / offensive infantry (requires Academy)
     *   'scout' => int,       // Scout unit
     *   'cav_light' => int,   // Light cavalry (trainable in Stable)
     *   'cav_heavy' => int,   // Heavy cavalry (trainable in higher Stable)
     *   'all' => int[]        // All 10 unit numbers for this tribe
     * ]
     */
    public static function getTribeUnits(int $tribe): array {
        $base = ($tribe - 1) * 10;
        $all = range($base + 1, $base + 10);

        $map = [
            1  => ['basic' => 1,  'inf_adv' => 3,  'scout' => 4,  'cav_light' => 5,  'cav_heavy' => 6],
            2  => ['basic' => 11, 'inf_adv' => 13, 'scout' => 14, 'cav_light' => 15, 'cav_heavy' => 16],
            3  => ['basic' => 21, 'inf_adv' => 22, 'scout' => 23, 'cav_light' => 24, 'cav_heavy' => 26],
            6  => ['basic' => 51, 'inf_adv' => 51, 'scout' => 52, 'cav_light' => 53, 'cav_heavy' => 55],
            7  => ['basic' => 61, 'inf_adv' => 63, 'scout' => 64, 'cav_light' => 65, 'cav_heavy' => 66],
            8  => ['basic' => 71, 'inf_adv' => 73, 'scout' => 74, 'cav_light' => 75, 'cav_heavy' => 76],
            9  => ['basic' => 81, 'inf_adv' => 83, 'scout' => 82, 'cav_light' => 85, 'cav_heavy' => 86],
            10 => ['basic' => 91, 'inf_adv' => 93, 'scout' => 94, 'cav_light' => 95, 'cav_heavy' => 96],
        ];

        $info = $map[$tribe] ?? [
            'basic'     => $base + 1,
            'inf_adv'   => $base + 3,
            'scout'     => $base + 4,
            'cav_light' => $base + 5,
            'cav_heavy' => $base + 6,
        ];

        $info['all'] = $all;
        return $info;
    }

    /**
     * Get defensive wall building type for a given tribe.
     */
    public static function getTribeWallType(int $tribe): int {
        $map = [
            1 => 31, // Roman City Wall
            2 => 32, // Teuton Earth Wall
            3 => 33, // Gaul Palisade
            6 => 42, // Hun Makeshift Wall
            7 => 43, // Egyptian Stone Wall
            8 => 47, // Spartan Wall
            9 => 50, // Viking Wooden Wall
            10 => 32, // Nusantara Benteng Kedaton
        ];
        return $map[$tribe] ?? 31;
    }

    /**
     * Unit walking speed lookup.
     */
    public static function getUnitSpeed(int $unitNum): int {
        $varName = 'u' . $unitNum;
        global $$varName;
        if (isset($$varName) && is_array($$varName) && isset($$varName['speed'])) {
            return (int)$$varName['speed'];
        }

        $speeds = [
            // Romans (Tribe 1)
            1 => 6, 2 => 5, 3 => 7, 4 => 16, 5 => 14, 6 => 10, 7 => 4, 8 => 3, 9 => 5, 10 => 5,
            // Teutons (Tribe 2)
            11 => 7, 12 => 7, 13 => 6, 14 => 9, 15 => 10, 16 => 9, 17 => 4, 18 => 3, 19 => 5, 20 => 5,
            // Gauls (Tribe 3)
            21 => 7, 22 => 6, 23 => 17, 24 => 19, 25 => 16, 26 => 13, 27 => 4, 28 => 3, 29 => 4, 30 => 5,
            // Huns (Tribe 6)
            51 => 6, 52 => 19, 53 => 16, 54 => 15, 55 => 14, 56 => 13, 57 => 4, 58 => 3, 59 => 5, 60 => 5,
            // Egyptians (Tribe 7)
            61 => 7, 62 => 6, 63 => 6, 64 => 16, 65 => 15, 66 => 10, 67 => 4, 68 => 3, 69 => 4, 70 => 5,
            // Spartans (Tribe 8)
            71 => 6, 72 => 6, 73 => 5, 74 => 16, 75 => 16, 76 => 9, 77 => 4, 78 => 3, 79 => 4, 80 => 5,
            // Vikings (Tribe 9)
            81 => 7, 82 => 10, 83 => 6, 84 => 6, 85 => 12, 86 => 10, 87 => 4, 88 => 3, 89 => 5, 90 => 5,
            // Nusantara (Tribe 10)
            91 => 7, 92 => 6, 93 => 6, 94 => 15, 95 => 13, 96 => 9, 97 => 4, 98 => 3, 99 => 5, 100 => 5,
        ];
        return $speeds[$unitNum] ?? 6;
    }

    /**
     * Storage capacity table for Warehouse / Granary.
     */
    public static function getStorageCapacity(int $level): int {
        $wgarray = [
            1 => 1200, 2 => 1700, 3 => 2300, 4 => 3100, 5 => 4000,
            6 => 5000, 7 => 6300, 8 => 7800, 9 => 9600, 10 => 11800,
            11 => 14400, 12 => 17600, 13 => 21400, 14 => 25900, 15 => 31300,
            16 => 37900, 17 => 45700, 18 => 55100, 19 => 66400, 20 => 80000
        ];
        $storageBase = defined('STORAGE_BASE') ? (int)STORAGE_BASE : 800;
        $base = $wgarray[$level] ?? 1200;
        return (int)($base * ($storageBase / 800));
    }

    /**
     * Recount village population and culture points.
     */
    public static function recountPop(int $vid): int {
        global $database;

        if (!class_exists('Building')) {
            require_once __DIR__ . '/Building.php';
        }

        $vid = (int)$vid;
        $fdata = $database->getResourceLevel($vid, false);
        $popTot = 0;

        for ($i = 1; $i <= 40; $i++) {
            $lvl = (int)($fdata['f' . $i] ?? 0);
            $type = (int)($fdata['f' . $i . 't'] ?? 0);
            if ($type > 0 && $lvl > 0) {
                $popTot += self::buildingPOP($type, $lvl);
            }
        }

        Building::recountCP($database, $vid);
        $database->query("UPDATE " . TB_PREFIX . "vdata SET pop = $popTot WHERE wref = $vid");

        return $popTot;
    }

    /**
     * Compute population contribution of a building.
     */
    public static function buildingPOP(int $type, int $lvl): int {
        $varName = 'bid' . $type;
        global $$varName;

        $popTot = 0;
        $data = $$varName ?? null;

        if (is_array($data)) {
            for ($i = 0; $i <= $lvl; $i++) {
                if (isset($data[$i]['pop'])) {
                    $popTot += (int)$data[$i]['pop'];
                }
            }
        }

        return $popTot;
    }
}
