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
                  AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
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
     */
    private static function replenishResources(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $maxStore = max(1200, (int)$bot['maxstore']);
        $maxCrop = max(1200, (int)$bot['maxcrop']);

        // Grant organic production boost per tick
        $boost = 250 * SPEED;
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
        $maxFieldLvl = defined('BOT_MAX_FIELD_LEVEL') ? (int)BOT_MAX_FIELD_LEVEL : 6;

        // Infrastructure levels
        $mbLevel = (int)($fdata['f26'] ?? 0); // Main Building
        $whLevel = (int)($fdata['f25'] ?? 0); // Warehouse
        $grLevel = (int)($fdata['f28'] ?? 0); // Granary
        $rpLevel = (int)($fdata['f39'] ?? 0); // Rally Point
        $brLevel = (int)($fdata['f21'] ?? 0); // Barracks
        $wlLevel = (int)($fdata['f40'] ?? 0); // Wall
        $crLevel = (int)($fdata['f31'] ?? 0); // Cranny

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

        // Decision hierarchy: Early-game sensible progression
        if ($minFieldLevel < 1) {
            // First get all resource fields to level 1
            $slot = $lowestFields[array_rand($lowestFields)];
            $targetLevel = 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
            $upgraded = "Resource field slot $slot to lvl $targetLevel";
        } elseif ($mbLevel < 3) {
            // Main building to lvl 3 to unlock Rally Point & Barracks
            $targetLevel = $mbLevel + 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f26 = $targetLevel, f26t = 15 WHERE vref = $wid");
            $upgraded = "Main Building (slot 26) to lvl $targetLevel";
        } elseif ($whLevel < 1) {
            // Warehouse lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f25 = 1, f25t = 10 WHERE vref = $wid");
            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxstore = 1200 WHERE wref = $wid");
            $upgraded = "Constructed Warehouse (slot 25) lvl 1";
        } elseif ($grLevel < 1) {
            // Granary lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f28 = 1, f28t = 11 WHERE vref = $wid");
            $database->query("UPDATE " . TB_PREFIX . "vdata SET maxcrop = 1200 WHERE wref = $wid");
            $upgraded = "Constructed Granary (slot 28) lvl 1";
        } elseif ($rpLevel < 1) {
            // Rally Point lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f39 = 1, f39t = 16 WHERE vref = $wid");
            $upgraded = "Constructed Rally Point (slot 39) lvl 1";
        } elseif ($brLevel < 1) {
            // Barracks lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f21 = 1, f21t = 19 WHERE vref = $wid");
            $upgraded = "Constructed Barracks (slot 21) lvl 1";
        } elseif ($wlLevel < 1) {
            // Wall lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f40 = 1, f40t = $wallType WHERE vref = $wid");
            $upgraded = "Constructed Wall (slot 40) lvl 1";
        } elseif ($crLevel < 1) {
            // Cranny lvl 1
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f31 = 1, f31t = 23 WHERE vref = $wid");
            $upgraded = "Constructed Cranny (slot 31) lvl 1";
        } elseif ($minFieldLevel < $maxFieldLvl && rand(1, 100) <= 65) {
            // Upgrade resource fields
            $slot = $lowestFields[array_rand($lowestFields)];
            $targetLevel = $minFieldLevel + 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
            $upgraded = "Resource field slot $slot to lvl $targetLevel";
        } else {
            // Upgrade city buildings
            $candidates = [];
            if ($mbLevel < 8) $candidates[] = ['slot' => 26, 'type' => 15, 'lvl' => $mbLevel + 1, 'name' => 'Main Building'];
            if ($brLevel < 5) $candidates[] = ['slot' => 21, 'type' => 19, 'lvl' => $brLevel + 1, 'name' => 'Barracks'];
            if ($whLevel < 5) $candidates[] = ['slot' => 25, 'type' => 10, 'lvl' => $whLevel + 1, 'name' => 'Warehouse'];
            if ($grLevel < 5) $candidates[] = ['slot' => 28, 'type' => 11, 'lvl' => $grLevel + 1, 'name' => 'Granary'];
            if ($wlLevel < 5) $candidates[] = ['slot' => 40, 'type' => $wallType, 'lvl' => $wlLevel + 1, 'name' => 'Wall'];
            if ($crLevel < 3) $candidates[] = ['slot' => 31, 'type' => 23, 'lvl' => $crLevel + 1, 'name' => 'Cranny'];

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
            } elseif ($minFieldLevel < 10) {
                $slot = $lowestFields[array_rand($lowestFields)];
                $targetLevel = $minFieldLevel + 1;
                $database->query("UPDATE " . TB_PREFIX . "fdata SET f{$slot} = $targetLevel WHERE vref = $wid");
                $upgraded = "Resource field slot $slot to lvl $targetLevel";
            }
        }

        if ($upgraded) {
            self::recountPop($wid);
        }

        return $upgraded;
    }

    /**
     * Process training of basic infantry units in Barracks.
     */
    public static function processTrain(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $tribe = (int)$bot['tribe'];
        $fdata = $database->getResourceLevel($wid, false);

        // Barracks must be built
        $brLevel = (int)($fdata['f21'] ?? 0);
        if ($brLevel < 1) {
            return null;
        }

        $unitNum = self::getTribeBasicUnit($tribe);
        $col = 'u' . $unitNum;
        $maxTroops = defined('BOT_AI_MAX_TROOPS') ? (int)BOT_AI_MAX_TROOPS : 45;

        // Current units at home
        $res = $database->query_return("SELECT {$col} FROM " . TB_PREFIX . "units WHERE vref = $wid");
        $atHome = (int)($res[0][$col] ?? 0);

        // Units marching in outgoing attacks
        $resMarching = $database->query_return("
            SELECT SUM(a.t1) as marching
            FROM " . TB_PREFIX . "movement m
            JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            WHERE m.from = $wid AND m.sort_type = 3 AND m.proc = 0
        ");
        $marching = (int)($resMarching[0]['marching'] ?? 0);
        $totalArmy = $atHome + $marching;

        if ($totalArmy >= $maxTroops) {
            return null; // Cap reached
        }

        $trainCount = min(rand(2, 4), $maxTroops - $totalArmy);
        if ($trainCount <= 0) {
            return null;
        }

        $database->query("UPDATE " . TB_PREFIX . "units SET {$col} = {$col} + $trainCount WHERE vref = $wid");

        return "Trained +$trainCount unit(s) [u{$unitNum}] (Total army: " . ($totalArmy + $trainCount) . "/$maxTroops)";
    }

    /**
     * Process automated raids to nearby villages.
     */
    public static function processAttack(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $uid = (int)$bot['uid'];
        $tribe = (int)$bot['tribe'];
        $unitNum = self::getTribeBasicUnit($tribe);
        $col = 'u' . $unitNum;

        $minTroops = defined('BOT_AI_MIN_RAID_TROOPS') ? (int)BOT_AI_MIN_RAID_TROOPS : 6;
        $attackChance = defined('BOT_AI_ATTACK_CHANCE') ? (int)BOT_AI_ATTACK_CHANCE : 35;
        $maxDistance = defined('BOT_AI_MAX_DISTANCE') ? (float)BOT_AI_MAX_DISTANCE : 35.0;
        $maxConcurrent = defined('BOT_AI_MAX_CONCURRENT_ATTACKS') ? (int)BOT_AI_MAX_CONCURRENT_ATTACKS : 1;

        // Troops available at home
        $res = $database->query_return("SELECT {$col} FROM " . TB_PREFIX . "units WHERE vref = $wid");
        $available = (int)($res[0][$col] ?? 0);

        if ($available < $minTroops) {
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
            // Skip protected players from config
            if (in_array($tgt['username'], $protectedList, true)) {
                continue;
            }

            // Distance calculation with map wrapping
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

        // Sort by distance ascending and pick randomly from the closest 3
        usort($candidates, function($a, $b) {
            return $a['dist'] <=> $b['dist'];
        });

        $topCandidates = array_slice($candidates, 0, 3);
        $target = $topCandidates[array_rand($topCandidates)];

        // Calculate raiding troops to send (30% - 50% of available, capped between 5 and 15)
        $sendTroops = min(15, max(5, (int)floor($available * 0.4)));

        // 1. Deduct troops from bot village
        $database->modifyUnit($wid, [$unitNum, 'hero'], [$sendTroops, 0], [0, 0]);

        // 2. Travel time calculation
        $speed = self::getUnitSpeed($unitNum);
        $duration = max(30, round(($target['dist'] / $speed) * 3600 / INCREASE_SPEED));

        // 3. Create attack entry (type 4 = Raid)
        $attackId = $database->addAttack(
            $wid,
            $sendTroops, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
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

        return "Raid dispatched! {$sendTroops} troops marched towards {$target['vname']} ({$target['username']}) - distance: " . round($target['dist'], 1) . " tiles, arrival in {$duration}s";
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
        ];
        return $map[$tribe] ?? 31;
    }

    /**
     * Unit walking speed lookup.
     */
    public static function getUnitSpeed(int $unitNum): int {
        $speeds = [
            1 => 6,  // Legionnaire
            11 => 7, // Clubswinger
            21 => 7, // Phalanx
            51 => 6, // Mercenary
            61 => 7, // Slave Militia
            71 => 6, // Hoplite
            81 => 7, // Thrall
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
