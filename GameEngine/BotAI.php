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

    private static ?array $runtimeConfig = null;

    /**
     * Retrieve Bot AI dynamic configuration setting.
     * If $key is null, returns the entire configuration array.
     * Priority for specific key:
     * 1. Prevention/bot_config.json (customized via Admin GUI)
     * 2. PHP Constant defined in config.php (e.g. BOT_AI_MAX_POP)
     * 3. Default fallback
     */
    public static function getConfig(?string $key = null, $default = null) {
        if (self::$runtimeConfig === null) {
            $configFile = __DIR__ . '/Prevention/bot_config.json';
            if (file_exists($configFile)) {
                $content = @file_get_contents($configFile);
                $decoded = json_decode($content, true);
                self::$runtimeConfig = is_array($decoded) ? $decoded : [];
            } else {
                self::$runtimeConfig = [];
            }
        }

        if ($key === null) {
            return self::$runtimeConfig;
        }

        if (array_key_exists($key, self::$runtimeConfig)) {
            return self::$runtimeConfig[$key];
        }

        if (defined($key)) {
            return constant($key);
        }

        return $default;
    }

    /**
     * Persist Bot AI dynamic configuration to Prevention/bot_config.json.
     * Completely safe: never touches or damages config.php.
     */
    public static function saveConfig(array $settings): bool {
        $configFile = __DIR__ . '/Prevention/bot_config.json';
        $current = [];
        if (file_exists($configFile)) {
            $content = @file_get_contents($configFile);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $current = $decoded;
            }
        }

        foreach ($settings as $k => $v) {
            $current[$k] = $v;
        }

        self::$runtimeConfig = $current;
        $dir = dirname($configFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return (bool)@file_put_contents($configFile, json_encode($current, JSON_PRETTY_PRINT));
    }

    /**
     * Main execution cycle for all active bots.
     * Can be invoked from Automation.php or standalone via CLI.
     *
     * @param bool $force If true, bypasses the interval throttle.
     * @return array Execution report.
     */
    public static function run($force = false) {
        global $database;

        if (self::getConfig('BOT_AI_ENABLED', true) === false) {
            return ['status' => 'disabled', 'message' => 'Bot AI is disabled in config'];
        }

        $interval = (int)self::getConfig('BOT_AI_INTERVAL', 120);
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
        $sql = "SELECT u.id as uid, u.username, u.tribe, u.access, u.alliance, u.regtime, u.timestamp,
                       v.wref, v.name as vname, v.pop, v.wood, v.clay, v.iron, v.crop, v.maxstore, v.maxcrop, v.lastupdate, v.created
                FROM " . TB_PREFIX . "users u
                JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
                WHERE u.access = 2
                  AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
                ORDER BY u.id ASC, v.wref ASC";

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

            // 4. Automated Nusantara Paranormal & Astral Spirits Management (Suku Nusantara)
            $paranormalResult = self::processParanormal($bot);
            if ($paranormalResult) {
                $botLogs[] = "Paranormal: " . $paranormalResult;
            }

            // 5. Automated Natural Village Expansion (Desa ke-2: Padepokan lvl 10, Pelatihan Settler & Pemukiman Alami)
            $expansionResult = self::processExpansion($bot);
            if ($expansionResult) {
                $botLogs[] = "Expansion: " . $expansionResult;
            }

            // 5. Automated Hero Management (Auto Revive & Auto Adventure)
            $heroResult = self::processHero($bot);
            if ($heroResult) {
                $botLogs[] = "Hero: " . $heroResult;
            }

            // 6. Automated Alliance Mutual Defense (Bantuan Bertahan Aliansi)
            $defenseResult = self::processAllianceDefense($bot);
            if ($defenseResult) {
                $botLogs[] = "Defense: " . $defenseResult;
            }

            // 7. Automated Raiding / Attacks (Hero included when available)
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
     * Replenish resources for bot villages naturally based on genuine resource field levels
     * and elapsed time, exactly mirroring standard Travian player production.
     * Raided bot villages stay depleted and accumulate resources realistically over time.
     */
    public static function replenishResources(array $bot) {
        global $database, $bid1, $bid2, $bid3, $bid4, $bid5, $bid6, $bid7, $bid8, $bid9, $bid10, $bid11;

        if (!isset($bid1) || !isset($bid4)) {
            include_once __DIR__ . '/Data/buidata.php';
        }

        $wid = (int)$bot['wref'];
        $fdata = $database->getResourceLevel($wid, false);
        if (!$fdata) {
            return;
        }

        $storageBase = defined('STORAGE_BASE') ? (int)STORAGE_BASE : 800;
        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;

        // 1. Calculate realistic warehouse & granary limits
        $whLevel = (int)($fdata['f25'] ?? 0);
        $grLevel = (int)($fdata['f28'] ?? 0);
        $maxStore = max($storageBase, (int)($bot['maxstore'] ?? $storageBase));
        $maxCrop  = max($storageBase, (int)($bot['maxcrop'] ?? $storageBase));

        if ($whLevel > 0) {
            $maxStore = max($maxStore, self::getStorageCapacity($whLevel));
        }
        if ($grLevel > 0) {
            $maxCrop = max($maxCrop, self::getStorageCapacity($grLevel));
        }

        // 2. Elapsed time since lastupdate
        $now = time();
        $lastupdate = (int)($bot['lastupdate'] ?? 0);
        if ($lastupdate <= 0 && method_exists($database, 'getVillageField')) {
            $lastupdate = (int)$database->getVillageField($wid, 'lastupdate');
        }
        if ($lastupdate <= 0 || $lastupdate > $now) {
            $lastupdate = $now - 60;
        }

        $timepast = $now - $lastupdate;
        if ($timepast <= 0) {
            return;
        }
        // Cap max elapsed time to 24 hours to avoid extreme jumps after server maintenance
        $timepast = min(86400, $timepast);

        // 3. Compute true gross production per hour from resource fields (f1..f18)
        $woodProd = 0;
        $clayProd = 0;
        $ironProd = 0;
        $cropProd = 0;

        for ($i = 1; $i <= 18; $i++) {
            $fType = (int)($fdata['f' . $i . 't'] ?? 0);
            $fLvl  = (int)($fdata['f' . $i] ?? 0);
            if ($fLvl < 0) $fLvl = 0;

            switch ($fType) {
                case 1:
                    $woodProd += (int)($bid1[$fLvl]['prod'] ?? 2);
                    break;
                case 2:
                    $clayProd += (int)($bid2[$fLvl]['prod'] ?? 2);
                    break;
                case 3:
                    $ironProd += (int)($bid3[$fLvl]['prod'] ?? 2);
                    break;
                case 4:
                    $cropProd += (int)($bid4[$fLvl]['prod'] ?? 2);
                    break;
            }
        }

        // Fallback baseline if fields not configured yet
        if ($woodProd <= 0) $woodProd = 8;
        if ($clayProd <= 0) $clayProd = 8;
        if ($ironProd <= 0) $ironProd = 8;
        if ($cropProd <= 0) $cropProd = 12;

        // 4. Bonus buildings (Sawmill=5, Brickyard=6, Iron Foundry=7, Grain Mill=8, Bakery=9)
        $woodBonus = 0;
        $clayBonus = 0;
        $ironBonus = 0;
        $cropBonus = 0;
        for ($i = 19; $i <= 38; $i++) {
            $bType = (int)($fdata['f' . $i . 't'] ?? 0);
            $bLvl  = (int)($fdata['f' . $i] ?? 0);
            if ($bLvl <= 0) continue;

            if ($bType == 5 && isset($bid5[$bLvl]['attri'])) $woodBonus += (int)$bid5[$bLvl]['attri'];
            if ($bType == 6 && isset($bid6[$bLvl]['attri'])) $clayBonus += (int)$bid6[$bLvl]['attri'];
            if ($bType == 7 && isset($bid7[$bLvl]['attri'])) $ironBonus += (int)$bid7[$bLvl]['attri'];
            if ($bType == 8 && isset($bid8[$bLvl]['attri'])) $cropBonus += (int)$bid8[$bLvl]['attri'];
            if ($bType == 9 && isset($bid9[$bLvl]['attri'])) $cropBonus += (int)$bid9[$bLvl]['attri'];
        }

        if ($woodBonus > 0) $woodProd += round($woodProd * ($woodBonus / 100));
        if ($clayBonus > 0) $clayProd += round($clayProd * ($clayBonus / 100));
        if ($ironBonus > 0) $ironProd += round($ironProd * ($ironBonus / 100));
        if ($cropBonus > 0) $cropProd += round($cropProd * ($cropBonus / 100));

        // Scale hourly production by server SPEED
        $woodProdPerHour = round($woodProd * $speed);
        $clayProdPerHour = round($clayProd * $speed);
        $ironProdPerHour = round($ironProd * $speed);
        $cropProdPerHour = round($cropProd * $speed);

        // 5. Account for crop upkeep (population consumption)
        $pop = max(2, (int)($bot['pop'] ?? 2));
        $netCropPerHour = max(5 * $speed, $cropProdPerHour - $pop);

        // 6. Natural delta produced during elapsed time
        $deltaWood = ($woodProdPerHour / 3600.0) * $timepast;
        $deltaClay = ($clayProdPerHour / 3600.0) * $timepast;
        $deltaIron = ($ironProdPerHour / 3600.0) * $timepast;
        $deltaCrop = ($netCropPerHour / 3600.0) * $timepast;

        // 7. Atomic update in database using modifyResource with clamp to maxstore / maxcrop
        if (method_exists($database, 'modifyResource')) {
            $database->query("
                UPDATE " . TB_PREFIX . "vdata
                SET maxstore = $maxStore, maxcrop = $maxCrop
                WHERE wref = $wid AND (maxstore != $maxStore OR maxcrop != $maxCrop)
            ");
            $database->modifyResource($wid, $deltaWood, $deltaClay, $deltaIron, $deltaCrop, 1, true);
        } else {
            $database->query("
                UPDATE " . TB_PREFIX . "vdata
                SET wood = LEAST($maxStore, wood + $deltaWood),
                    clay = LEAST($maxStore, clay + $deltaClay),
                    iron = LEAST($maxStore, iron + $deltaIron),
                    crop = LEAST($maxCrop, crop + $deltaCrop),
                    maxstore = $maxStore,
                    maxcrop = $maxCrop,
                    lastupdate = $now
                WHERE wref = $wid
            ");
        }
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
        $pop = max(10, (int)($bot['pop'] ?? 0));
        $fdata = $database->getResourceLevel($wid, false);

        if (!$fdata) {
            return null;
        }

        $wallType = self::getTribeWallType($tribe);
        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') ? (bool)BOT_AI_DYNAMIC_SCALING : true;
        $maxFieldLvl = $dynamicScaling ? 10 : (defined('BOT_MAX_FIELD_LEVEL') ? (int)BOT_MAX_FIELD_LEVEL : 6);
        $maxBuildingLvl = (int)self::getConfig('BOT_AI_MAX_BUILDING_LEVEL', 15);
        $adminMaxPop = (int)self::getConfig('BOT_AI_MAX_POP', 800);
        $enableCavalry = defined('BOT_AI_ENABLE_CAVALRY') ? (bool)BOT_AI_ENABLE_CAVALRY : true;

        // Pacing pertumbuhan alami berdasarkan umur desa bot & server speed
        $vCreated = (int)($bot['created'] ?? 0);
        $villageAgeBase = ($vCreated > 0) ? $vCreated : (int)($bot['regtime'] ?? $bot['timestamp'] ?? time() - 86400);
        $ageSeconds = max(60, time() - $villageAgeBase);
        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;
        $effectiveAgeDays = ($ageSeconds / 86400.0) * sqrt($speed);

        // 1. Batas maksimal populasi berdasarkan umur & konfigurasi admin
        $basePopLimit = 25;
        $maxGrowthRange = max(100, $adminMaxPop - $basePopLimit);
        $maxPopByAge = (int)round($basePopLimit + min($maxGrowthRange, pow($effectiveAgeDays + 0.3, 1.15) * 28));

        // Auto-normalize overgrown bot village to natural building levels
        if ($pop > max($adminMaxPop, ($maxPopByAge * 1.5))) {
            self::rebalanceVillageBuildings($wid, $tribe, $effectiveAgeDays);
            return "Auto-normalized overgrown bot village buildings & population down to natural cap";
        }

        if ($pop >= $maxPopByAge || $pop >= $adminMaxPop) {
            return null; // Batas populasi wajar sesuai umur akun / admin cap tercapai
        }

        // 2. Batas level ladang berdasarkan umur (Hari 1: max lvl 2, Hari 2: max lvl 3-4, dst)
        $ageFieldCap = max(2, min(10, (int)floor(1.2 + ($effectiveAgeDays * 1.0))));
        $maxFieldLvl = min($maxFieldLvl, $ageFieldCap);

        // 3. Batas level gedung kota berdasarkan umur (Hari 1: max lvl 3, Hari 2: max lvl 4-5)
        $ageBuildingCap = max(2, min(20, (int)floor(2 + ($effectiveAgeDays * 1.2))));
        $maxBuildingLvl = min($maxBuildingLvl, $ageBuildingCap);

        // 3. Pacing konstruksi: simulasi antrean tukang bangunan (~6-10 menit per upgrade)
        if (rand(1, 100) > 35) {
            return null; // Tukang bangunan sedang bekerja
        }

        // 4. Resource check: Jika desa bot baru saja dirampok dan sumber daya tiris (< 30), tunda konstruksi
        $vRes = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'wood, clay, iron, crop', false) : null;
        $curWood = (float)($vRes['wood'] ?? $bot['wood'] ?? 0);
        $curClay = (float)($vRes['clay'] ?? $bot['clay'] ?? 0);
        $curIron = (float)($vRes['iron'] ?? $bot['iron'] ?? 0);
        $curCrop = (float)($vRes['crop'] ?? $bot['crop'] ?? 0);
        if ($curWood < 30 || $curClay < 30 || $curIron < 30 || $curCrop < 20) {
            return null; // Sumber daya habis dijarah pemain, tunggu produksi pulih
        }

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
        $wsLevel = (int)($fdata['f23'] ?? 0); // slot 23: Workshop (type 21)
        $thLevel = (int)($fdata['f24'] ?? 0); // slot 24: Town Hall (type 24)
        $gmLevel = (int)($fdata['f29'] ?? 0); // slot 29: Grain Mill (type 8)
        $bkLevel = (int)($fdata['f32'] ?? 0); // slot 32: Bakery (type 9)
        $smLevel = (int)($fdata['f33'] ?? 0); // slot 33: Sawmill (type 5)
        $byLevel = (int)($fdata['f34'] ?? 0); // slot 34: Brickyard (type 6)
        $ifLevel = (int)($fdata['f35'] ?? 0); // slot 35: Iron Foundry (type 7)
        $bsLevel = (int)($fdata['f36'] ?? 0); // slot 36: Blacksmith (type 12)
        $trLevel = (int)($fdata['f37'] ?? 0); // slot 37: Treasury (type 27)
        $spLevel = ((int)($fdata['f38t'] ?? 0) === 51) ? (int)($fdata['f38'] ?? 0) : 0; // slot 38: Sanggar Paranormal (type 51, Nusantara)

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
        // Phase 2: Construct advanced buildings when prerequisites and age requirements are satisfied
        elseif ($acLevel < 1 && $mbLevel >= 3 && $brLevel >= 2 && $effectiveAgeDays >= 1.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f22 = 1, f22t = 22 WHERE vref = $wid");
            $upgraded = "Constructed Academy (slot 22) lvl 1";
        } elseif ($mpLevel < 1 && $mbLevel >= 3 && $whLevel >= 1 && $grLevel >= 1 && $effectiveAgeDays >= 1.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f27 = 1, f27t = 17 WHERE vref = $wid");
            $upgraded = "Constructed Marketplace (slot 27) lvl 1";
        } elseif ($enableCavalry && $stLevel < 1 && $mbLevel >= 3 && ($acLevel >= 1 || $pop >= 80) && $effectiveAgeDays >= 2.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f20 = 1, f20t = 20 WHERE vref = $wid");
            $upgraded = "Constructed Stable (slot 20) lvl 1";
        } elseif ($wsLevel < 1 && $mbLevel >= 5 && ($acLevel >= 2 || $pop >= 150) && $effectiveAgeDays >= 4.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f23 = 1, f23t = 21 WHERE vref = $wid");
            $upgraded = "Constructed Workshop (slot 23) lvl 1";
        } elseif ($rsLevel < 1 && $mbLevel >= 5 && $effectiveAgeDays >= 2.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f30 = 1, f30t = 25 WHERE vref = $wid");
            $upgraded = "Constructed Residence (slot 30) lvl 1";
        } elseif ($dynamicScaling && $gmLevel < 1 && $minFieldLevel >= 5 && $effectiveAgeDays >= 2.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f29 = 1, f29t = 8 WHERE vref = $wid");
            $upgraded = "Constructed Grain Mill (slot 29) lvl 1";
        } elseif ($dynamicScaling && $bsLevel < 1 && $mbLevel >= 3 && $acLevel >= 1 && $effectiveAgeDays >= 2.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f36 = 1, f36t = 12 WHERE vref = $wid");
            $upgraded = "Constructed Blacksmith (slot 36) lvl 1";
        } elseif ($dynamicScaling && $thLevel < 1 && $mbLevel >= 10 && $acLevel >= 10 && $effectiveAgeDays >= 4.5) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f24 = 1, f24t = 24 WHERE vref = $wid");
            $upgraded = "Constructed Town Hall (slot 24) lvl 1";
        } elseif ($dynamicScaling && $smLevel < 1 && $minFieldLevel >= 10 && $mbLevel >= 5 && $effectiveAgeDays >= 5.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f33 = 1, f33t = 5 WHERE vref = $wid");
            $upgraded = "Constructed Sawmill (slot 33) lvl 1";
        } elseif ($dynamicScaling && $byLevel < 1 && $minFieldLevel >= 10 && $mbLevel >= 5 && $effectiveAgeDays >= 5.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f34 = 1, f34t = 6 WHERE vref = $wid");
            $upgraded = "Constructed Brickyard (slot 34) lvl 1";
        } elseif ($dynamicScaling && $ifLevel < 1 && $minFieldLevel >= 10 && $mbLevel >= 5 && $effectiveAgeDays >= 5.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f35 = 1, f35t = 7 WHERE vref = $wid");
            $upgraded = "Constructed Iron Foundry (slot 35) lvl 1";
        } elseif ($dynamicScaling && $bkLevel < 1 && $minFieldLevel >= 10 && $gmLevel >= 5 && $mbLevel >= 5 && $effectiveAgeDays >= 6.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f32 = 1, f32t = 9 WHERE vref = $wid");
            $upgraded = "Constructed Bakery (slot 32) lvl 1";
        } elseif ($dynamicScaling && $trLevel < 1 && $mbLevel >= 10 && $effectiveAgeDays >= 6.0) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f37 = 1, f37t = 27 WHERE vref = $wid");
            $upgraded = "Constructed Treasury (slot 37) lvl 1";
        }
        // Nusantara: Cranny upgrade to lvl 3 as prerequisite for Sanggar Paranormal
        elseif ($tribe === 10 && $crLevel < 3 && $mbLevel >= 3) {
            $targetLevel = $crLevel + 1;
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f31 = $targetLevel, f31t = 23 WHERE vref = $wid");
            $upgraded = "Upgraded Cranny (slot 31) to lvl $targetLevel (Prerequisite for Sanggar Paranormal)";
        }
        // Nusantara: Construct Sanggar Paranormal (GID 51) on slot 38 naturally
        elseif ($tribe === 10 && self::getConfig('BOT_AI_PARANORMAL_ENABLED', true) && $spLevel < 1 && (int)($fdata['f38t'] ?? 0) === 0 && $mbLevel >= 3 && $crLevel >= 3 && $effectiveAgeDays >= 1.5 && $curWood >= 150 && $curClay >= 120 && $curIron >= 160 && $curCrop >= 80) {
            $database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 1, f38t = 51 WHERE vref = $wid");
            if (method_exists($database, 'modifyResource')) {
                $database->modifyResource($wid, 150, 120, 160, 80, 0);
            }
            self::recountPop($wid);
            return "Constructed Sanggar Paranormal (slot 38) lvl 1";
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
                        if ($wsLevel > 0 && $wsLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 23, 'type' => 21, 'lvl' => $wsLevel + 1, 'name' => 'Workshop'];
                        }
                        if ($mpLevel > 0 && $mpLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 27, 'type' => 17, 'lvl' => $mpLevel + 1, 'name' => 'Marketplace'];
                        }
                        if ($rsLevel > 0 && $rsLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 30, 'type' => 25, 'lvl' => $rsLevel + 1, 'name' => 'Residence'];
                            // Prioritize Residence up to level 10 if expansion enabled and age >= 2.5
                            if ($rsLevel < 10 && self::getConfig('BOT_AI_EXPANSION_ENABLED', true) && $effectiveAgeDays >= 2.5) {
                                $candidates[] = ['slot' => 30, 'type' => 25, 'lvl' => $rsLevel + 1, 'name' => 'Residence'];
                                $candidates[] = ['slot' => 30, 'type' => 25, 'lvl' => $rsLevel + 1, 'name' => 'Residence'];
                            }
                        }
                        if ($thLevel > 0 && $thLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 24, 'type' => 24, 'lvl' => $thLevel + 1, 'name' => 'Town Hall'];
                        }
                        if ($gmLevel > 0 && $gmLevel < min(5, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 29, 'type' => 8, 'lvl' => $gmLevel + 1, 'name' => 'Grain Mill'];
                        }
                        if ($bkLevel > 0 && $bkLevel < min(5, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 32, 'type' => 9, 'lvl' => $bkLevel + 1, 'name' => 'Bakery'];
                        }
                        if ($smLevel > 0 && $smLevel < min(5, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 33, 'type' => 5, 'lvl' => $smLevel + 1, 'name' => 'Sawmill'];
                        }
                        if ($byLevel > 0 && $byLevel < min(5, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 34, 'type' => 6, 'lvl' => $byLevel + 1, 'name' => 'Brickyard'];
                        }
                        if ($ifLevel > 0 && $ifLevel < min(5, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 35, 'type' => 7, 'lvl' => $ifLevel + 1, 'name' => 'Iron Foundry'];
                        }
                        if ($bsLevel > 0 && $bsLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 36, 'type' => 12, 'lvl' => $bsLevel + 1, 'name' => 'Blacksmith'];
                        }
                        if ($trLevel > 0 && $trLevel < min(20, $maxBuildingLvl)) {
                            $candidates[] = ['slot' => 37, 'type' => 27, 'lvl' => $trLevel + 1, 'name' => 'Treasury'];
                        }
                        if ($tribe === 10 && self::getConfig('BOT_AI_PARANORMAL_ENABLED', true) && $spLevel > 0 && $spLevel < min(20, $maxBuildingLvl) && (int)($fdata['f38t'] ?? 0) === 51) {
                            $candidates[] = ['slot' => 38, 'type' => 51, 'lvl' => $spLevel + 1, 'name' => 'Sanggar Paranormal'];
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
            // Deduct realistic construction cost from village storage
            $dWood = min($curWood, 40);
            $dClay = min($curClay, 40);
            $dIron = min($curIron, 35);
            $dCrop = min($curCrop, 25);
            if (method_exists($database, 'modifyResource')) {
                $database->modifyResource($wid, $dWood, $dClay, $dIron, $dCrop, 0);
            }
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
    /**
     * Helper to safely retrieve building level (GID) in village.
     */
    public static function getBuildingLevel(int $wid, int $gid, array $fdata = []): int {
        global $database;
        if (method_exists($database, 'getFieldLevelInVillage')) {
            $lvl = (int)$database->getFieldLevelInVillage($wid, $gid);
            if ($lvl > 0) {
                return $lvl;
            }
        }
        if (empty($fdata) && method_exists($database, 'getResourceLevel')) {
            $fdata = $database->getResourceLevel($wid, false);
        }
        $maxLvl = 0;
        for ($i = 19; $i <= 40; $i++) {
            if ((int)($fdata['f' . $i . 't'] ?? 0) === $gid) {
                $maxLvl = max($maxLvl, (int)($fdata['f' . $i] ?? 0));
            }
        }
        return $maxLvl;
    }

    /**
     * Automated troop training in barracks, stable, or workshop.
     * 100% Natural mechanics:
     * - Requires actual Barracks (GID 19), Stable (GID 20), or Workshop (GID 21).
     * - Queues troops into standard training table with realistic build times.
     * - Consumes genuine village resources based on unit cost from Data/unitdata.php.
     * - Incremental graduation via standard AutomationTraining::trainingComplete().
     */
    public static function processTrain(array $bot) {
        global $database;

        $wid = (int)$bot['wref'];
        $tribe = (int)$bot['tribe'];
        $pop = max(10, (int)$bot['pop']);

        // Check building levels using getBuildingLevel
        $brLevel = self::getBuildingLevel($wid, 19);
        if ($brLevel < 1) {
            return null; // Barracks not yet built
        }

        // Pacing pasukan alami berdasarkan umur akun bot & populasi
        $regTime = (int)($bot['regtime'] ?? $bot['timestamp'] ?? 0);
        if ($regTime <= 0) {
            $regTime = time() - 86400;
        }
        $ageSeconds = max(60, time() - $regTime);
        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;
        $effectiveAgeDays = ($ageSeconds / 86400.0) * sqrt($speed);

        // Hitung batas pertahanan garnisun rumah (Home Garrison Reserve)
        $homeReserve = self::getHomeReserve($pop);

        // Rasio pasukan realistis terhadap populasi (25 - 35% populasi)
        $popFactor = defined('BOT_AI_TROOP_POP_FACTOR') ? (float)BOT_AI_TROOP_POP_FACTOR : 0.30;
        $popArmyCap = max(8, (int)round($pop * $popFactor));

        // Batas maksimal pasukan berdasarkan umur bot
        $ageArmyCap = (int)round(12 + ($effectiveAgeDays * 18));
        $maxTroops = min($ageArmyCap, $popArmyCap);
        // Pastikan batas maksimal pasukan selalu mencukupi home reserve + surplus tempur
        $maxTroops = max($homeReserve + 10, min(300, $maxTroops));

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

        // Units marching in outgoing attacks
        $resMarching = $database->query_return("
            SELECT SUM(a.t1 + a.t2 + a.t3 + a.t4 + a.t5 + a.t6 + a.t7 + a.t8 + a.t9 + a.t10) as marching
            FROM " . TB_PREFIX . "movement m
            JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            WHERE m.from = $wid AND m.sort_type = 3 AND m.proc = 0
        ");
        $marchingTotal = (int)($resMarching[0]['marching'] ?? 0);

        // Units currently queued in training
        $resQueued = $database->query_return("
            SELECT SUM(amt) as queued
            FROM " . TB_PREFIX . "training
            WHERE vref = $wid
        ");
        $queuedTotal = (int)($resQueued[0]['queued'] ?? 0);

        $totalArmy = $atHomeTotal + $marchingTotal + $queuedTotal;

        // Auto-trimming: Jika bot memiliki pasukan berlebih tidak wajar, normalkan ke batas natural
        if ($totalArmy > ($maxTroops * 1.5)) {
            self::trimVillageTroops($wid, $tribe, $maxTroops);
            return "Rebalanced troops: trimmed excess army from $totalArmy down to natural cap of $maxTroops";
        }

        if ($totalArmy >= $maxTroops) {
            return null; // Batas pasukan wajar tercapai
        }

        // Mode Pemulihan Darurat: Jika pasukan bot berada di bawah batas garnisun rumah (Home Reserve)
        $isEmergencyRecovery = ($totalArmy < $homeReserve);

        // Jangan menumpuk antrean berlebih: dalam mode darurat izinkan antrean lebih tinggi agar barak aktif memulihkan pasukan
        $maxQueued = $isEmergencyRecovery ? min(20, $homeReserve) : 6;
        if ($queuedTotal >= $maxQueued) {
            return null; // Antrean pelatihan masih penuh
        }

        // Pacing pelatihan: 40% peluang per tick dalam masa damai; 100% aktif (bypass roll) saat pemulihan darurat
        if (!$isEmergencyRecovery && rand(1, 100) > 40) {
            return null; // Pelatihan di barak sedang berlangsung
        }

        // Determine trainable unit types based on buildings, population, and bot age
        $acLevel = self::getBuildingLevel($wid, 22);
        $stLevel = self::getBuildingLevel($wid, 20);
        $wsLevel = self::getBuildingLevel($wid, 21);
        $enableCavalry = defined('BOT_AI_ENABLE_CAVALRY') ? (bool)BOT_AI_ENABLE_CAVALRY : true;

        $trainCandidates = [];
        if ($isEmergencyRecovery) {
            // Mode Darurat: Fokus prioritaskan infanteri dasar & infanteri pertahanan agar segera pulih
            $trainCandidates[$tribeUnits['basic']] = 75;
            if (!empty($tribeUnits['inf_def']) && ($brLevel >= 2 || $pop >= 50)) {
                $trainCandidates[$tribeUnits['inf_def']] = 25;
            }
        } else {
            // Basic infantry is always eligible (Barracks >= 1)
            $trainCandidates[$tribeUnits['basic']] = 50;

            // Defensive infantry: Membutuhkan barak lvl 2 atau pop >= 50 dan umur >= 0.8 hari
            if (!empty($tribeUnits['inf_def']) && ($brLevel >= 2 || $pop >= 50) && $effectiveAgeDays >= 0.8) {
                $trainCandidates[$tribeUnits['inf_def']] = 30;
            }

            // Advanced / Elite offensive infantry: Membutuhkan akademi atau barak lvl 3 dan pop >= 80, umur >= 1.5 hari
            if (!empty($tribeUnits['inf_adv']) && ($acLevel >= 1 || $brLevel >= 3) && $pop >= 80 && $effectiveAgeDays >= 1.5) {
                $trainCandidates[$tribeUnits['inf_adv']] = 20;
            }

            // Kavaleri: Hanya terbuka setelah bot berumur >= 2.5 hari, pop >= 100, dan memiliki Stable (lvl >= 1)
            if ($enableCavalry && $effectiveAgeDays >= 2.5 && $pop >= 100 && $stLevel >= 1) {
                if (!empty($tribeUnits['cav_light'])) $trainCandidates[$tribeUnits['cav_light']] = 15;
                if (!empty($tribeUnits['cav_def']) && $stLevel >= 2) $trainCandidates[$tribeUnits['cav_def']] = 10;
                if (!empty($tribeUnits['cav_heavy']) && $stLevel >= 3 && $pop >= 150) $trainCandidates[$tribeUnits['cav_heavy']] = 10;
            }

            // Senjata Pengepungan: Hanya terbuka setelah bot berumur >= 4.5 hari, pop >= 180, dan memiliki Workshop (lvl >= 1)
            if ($effectiveAgeDays >= 4.5 && $pop >= 180 && $wsLevel >= 1) {
                if (!empty($tribeUnits['ram'])) $trainCandidates[$tribeUnits['ram']] = 5;
                if (!empty($tribeUnits['catapult']) && $wsLevel >= 2) $trainCandidates[$tribeUnits['catapult']] = 4;
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

        // Fetch unit cost and training time from Data/unitdata.php and Data/buidata.php
        global ${'u' . $chosenUnit}, $bid19, $bid20, $bid21;
        if (!isset(${'u' . $chosenUnit})) {
            include_once __DIR__ . '/Data/unitdata.php';
        }
        if (!isset($bid19) || !isset($bid20) || !isset($bid21)) {
            include_once __DIR__ . '/Data/buidata.php';
        }
        $uData = ${'u' . $chosenUnit} ?? null;
        if (!$uData) {
            return null;
        }

        $wCost = (int)($uData['wood'] ?? 100);
        $cCost = (int)($uData['clay'] ?? 100);
        $iCost = (int)($uData['iron'] ?? 100);
        $crCost = (int)($uData['crop'] ?? 100);
        $unitPop = (int)($uData['pop'] ?? 1);
        $baseTime = (int)($uData['time'] ?? 1200);

        // Update resource production to current timestamp
        if (method_exists($database, 'updateResField')) {
            $database->updateResField($wid);
        }

        // Cek ketersediaan sumber daya desa bot
        $vRes = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'wood, clay, iron, crop', false) : null;
        $curWood = (float)($vRes['wood'] ?? $bot['wood'] ?? 0);
        $curClay = (float)($vRes['clay'] ?? $bot['clay'] ?? 0);
        $curIron = (float)($vRes['iron'] ?? $bot['iron'] ?? 0);
        $curCrop = (float)($vRes['crop'] ?? $bot['crop'] ?? 0);

        $canAffordWood = ($wCost > 0) ? (int)floor($curWood / $wCost) : 999;
        $canAffordClay = ($cCost > 0) ? (int)floor($curClay / $cCost) : 999;
        $canAffordIron = ($iCost > 0) ? (int)floor($curIron / $iCost) : 999;
        $canAffordCrop = ($crCost > 0) ? (int)floor($curCrop / $crCost) : 999;
        $maxAffordable = min($canAffordWood, $canAffordClay, $canAffordIron, $canAffordCrop);

        if ($maxAffordable < 1) {
            return null; // Sumber daya belum cukup, bot harus menunggu produksi lapangan (seperti pemain asli)
        }

        // Batch size: 3 - 5 unit per tick dalam pemulihan darurat, 1 - 2 unit dalam masa damai
        $batchSize = $isEmergencyRecovery ? rand(3, 5) : rand(1, 2);
        $trainCount = min($batchSize, $maxAffordable, $maxTroops - $totalArmy);
        if ($trainCount <= 0) {
            return null;
        }

        // Deduct genuine village resources
        $totalW = $wCost * $trainCount;
        $totalC = $cCost * $trainCount;
        $totalI = $iCost * $trainCount;
        $totalCr = $crCost * $trainCount;
        if (method_exists($database, 'modifyResource')) {
            $database->modifyResource($wid, $totalW, $totalC, $totalI, $totalCr, 0);
        }

        // Calculate natural training duration per unit ($eachTime) based on building level
        $bLevel = 1;
        $attri = 100;

        if (in_array($chosenUnit, [4, 5, 6, 15, 16, 23, 24, 25, 26, 35, 36, 45, 46, 53, 54, 55, 56, 64, 65, 66, 75, 76, 85, 86, 94, 95, 96])) {
            $bLevel = max(1, $stLevel);
            $attri = (float)($bid20[$bLevel]['attri'] ?? 100);
        } elseif (in_array($chosenUnit, [7, 8, 17, 18, 27, 28, 37, 38, 47, 48, 57, 58, 67, 68, 77, 78, 87, 88, 97, 98])) {
            $bLevel = max(1, $wsLevel);
            $attri = (float)($bid21[$bLevel]['attri'] ?? 100);
        } else {
            $bLevel = max(1, $brLevel);
            $attri = (float)($bid19[$bLevel]['attri'] ?? 100);
        }

        $eachTime = max(3, (int)round(($attri / 100) * $baseTime / $speed));

        // Enqueue into standard training table
        global $technology, $village;
        if (empty($technology)) {
            require_once __DIR__ . '/Technology.php';
            $technology = new Technology();
        }
        $savedWid = $village->wid ?? null;
        if (empty($village)) {
            $village = (object)['wid' => $wid];
        } else {
            $village->wid = $wid;
        }

        $database->trainUnit($wid, $chosenUnit, $trainCount, $unitPop, $eachTime, 0);

        if ($savedWid !== null) {
            $village->wid = $savedWid;
        }

        $uName = self::getUnitName($chosenUnit);
        return "Queued +$trainCount $uName into training queue (duration: {$eachTime}s each, Cost: {$totalW}w, {$totalC}c, {$totalI}i, {$totalCr}cr, Total army: " . ($totalArmy + $trainCount) . "/$maxTroops)";
    }

    /**
     * Trim excess troops in a bot village down to a target natural garrison size.
     */
    /**
     * Trim excess troops in a bot village down to a target natural garrison size.
     * Guarantees that only basic infantry is kept, zeroing out premature cavalry, siege, and high-tier units.
     */
    public static function trimVillageTroops(int $wid, int $tribe, int $targetTotal = 25): int {
        global $database;
        $tribeUnits = self::getTribeUnits($tribe);
        $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
        $colsSql = implode(', ', $unitCols);
        $res = $database->query_return("SELECT {$colsSql} FROM " . TB_PREFIX . "units WHERE vref = $wid");
        if (empty($res[0])) return 0;

        $row = $res[0];
        $currentTotal = 0;
        foreach ($tribeUnits['all'] as $u) {
            $currentTotal += (int)($row['u' . $u] ?? 0);
        }

        // Keep target garrison as basic infantry, reset high tier/cavalry/siege
        $basicUnit = $tribeUnits['basic'];
        $updates = [];
        foreach ($tribeUnits['all'] as $u) {
            if ($u === $basicUnit) {
                $updates[] = "u{$u} = $targetTotal";
            } else {
                $updates[] = "u{$u} = 0";
            }
        }
        $database->query("UPDATE " . TB_PREFIX . "units SET " . implode(', ', $updates) . " WHERE vref = $wid");
        if (method_exists($database, 'addStarvationData')) {
            $database->addStarvationData($wid);
        }
        return max(0, $currentTotal - $targetTotal);
    }

    /**
     * Helper to rebalance a bot village's resource fields, city buildings, and queues to natural age limits.
     * Also clears pending building, training, and research queues so the village does not jump back up on the next tick.
     */
    public static function rebalanceVillageBuildings(int $wid, int $tribe, float $effectiveAgeDays = 1.0): int {
        global $database;

        // Natural resource field level (Day 1 = max lvl 2, Day 2 = max lvl 3, Day 3 = max lvl 4)
        if ($effectiveAgeDays <= 1.0) {
            $fieldLvl = 2;
        } elseif ($effectiveAgeDays <= 2.0) {
            $fieldLvl = 3;
        } elseif ($effectiveAgeDays <= 3.0) {
            $fieldLvl = 4;
        } else {
            $fieldLvl = max(2, min(10, (int)floor(1.0 + ($effectiveAgeDays * 1.0))));
        }

        // Natural city infrastructure levels
        $mbLvl = max(1, min(20, (int)floor(2 + ($effectiveAgeDays * 1.2)))); // Day 1 = 3, Day 2 = 4
        $whLvl = max(1, min(20, (int)floor(1 + ($effectiveAgeDays * 0.8)))); // Day 1 = 1, Day 2 = 2
        $grLvl = max(1, min(20, (int)floor(1 + ($effectiveAgeDays * 0.8)))); // Day 1 = 1, Day 2 = 2
        $brLvl = max(1, min(20, (int)floor(1 + ($effectiveAgeDays * 0.9)))); // Day 1 = 1, Day 2 = 2
        $wlLvl = max(1, min(20, (int)floor(1 + ($effectiveAgeDays * 0.8)))); // Day 1 = 1, Day 2 = 2
        $crLvl = max(1, min(10, (int)floor(1 + ($effectiveAgeDays * 0.7)))); // Day 1 = 1, Day 2 = 2
        $wallType = self::getTribeWallType($tribe);

        // Advanced buildings (unlocked only after maturity)
        $acLvl = ($effectiveAgeDays >= 1.5) ? max(1, min(20, (int)floor(($effectiveAgeDays - 1.0) * 1.5))) : 0;
        $mpLvl = ($effectiveAgeDays >= 1.2) ? max(1, min(20, (int)floor($effectiveAgeDays * 0.8))) : 0;
        $stLvl = ($effectiveAgeDays >= 2.5) ? max(1, min(20, (int)floor(($effectiveAgeDays - 2.0) * 1.2))) : 0;
        $wsLvl = ($effectiveAgeDays >= 4.5) ? max(1, min(20, (int)floor(($effectiveAgeDays - 4.0) * 1.0))) : 0;
        $rsLvl = ($effectiveAgeDays >= 3.0) ? max(1, min(10, (int)floor(($effectiveAgeDays - 2.5) * 1.0))) : 0;
        $spLvl = ($tribe === 10 && $effectiveAgeDays >= 1.5) ? max(1, min(20, (int)floor(($effectiveAgeDays - 1.0) * 1.0))) : 0;

        // Build full SQL update for fdata
        $setParts = [];
        for ($fi = 1; $fi <= 18; $fi++) {
            $setParts[] = "f{$fi} = $fieldLvl";
        }

        // Core city slots
        $setParts[] = "f26 = $mbLvl, f26t = 15"; // Main Building
        $setParts[] = "f25 = $whLvl, f25t = 10"; // Warehouse
        $setParts[] = "f28 = $grLvl, f28t = 11"; // Granary
        $setParts[] = "f39 = 1, f39t = 16";      // Rally Point
        $setParts[] = "f21 = $brLvl, f21t = 19"; // Barracks
        $setParts[] = "f40 = $wlLvl, f40t = $wallType"; // Wall
        $setParts[] = "f31 = $crLvl, f31t = 23"; // Cranny

        // Advanced buildings (reset to 0 if age not reached)
        $setParts[] = $acLvl > 0 ? "f22 = $acLvl, f22t = 22" : "f22 = 0, f22t = 0";
        $setParts[] = $mpLvl > 0 ? "f27 = $mpLvl, f27t = 17" : "f27 = 0, f27t = 0";
        $setParts[] = $stLvl > 0 ? "f20 = $stLvl, f20t = 20" : "f20 = 0, f20t = 0";
        $setParts[] = $wsLvl > 0 ? "f23 = $wsLvl, f23t = 21" : "f23 = 0, f23t = 0";
        $setParts[] = $rsLvl > 0 ? "f30 = $rsLvl, f30t = 25" : "f30 = 0, f30t = 0";

        // Sanggar Paranormal (slot 38, Nusantara tribe only)
        if ($tribe === 10 && $spLvl > 0) {
            $setParts[] = "f38 = $spLvl, f38t = 51";
        }

        // Clear unused / unbuilt slots: f19, f24, f29, f32..f38
        $unusedSlots = [19, 24, 29, 32, 33, 34, 35, 36, 37];
        if (!($tribe === 10 && $spLvl > 0)) {
            $unusedSlots[] = 38;
        }
        foreach ($unusedSlots as $us) {
            $setParts[] = "f{$us} = 0, f{$us}t = 0";
        }

        // Normalize astral spirits to building capacity
        if ($tribe === 10 && class_exists('Paranormal')) {
            Paranormal::ensureTableExists();
            if ($spLvl <= 0) {
                $database->query("UPDATE " . TB_PREFIX . "paranormal SET pocong = 0, kuntilanak = 0, genderuwo = 0 WHERE vref = $wid");
            } else {
                $maxCap = Paranormal::getMaxCapacity($spLvl);
                $spirits = Paranormal::getVillageSpirits($wid);
                $used = Paranormal::getUsedSlots($spirits);
                if ($used > $maxCap) {
                    $ratio = $maxCap / max(1, $used);
                    $newP = (int)floor((int)($spirits['pocong'] ?? 0) * $ratio);
                    $newK = (int)floor((int)($spirits['kuntilanak'] ?? 0) * $ratio);
                    $newG = (int)floor((int)($spirits['genderuwo'] ?? 0) * $ratio);
                    $database->query("UPDATE " . TB_PREFIX . "paranormal SET pocong = $newP, kuntilanak = $newK, genderuwo = $newG WHERE vref = $wid");
                }
            }
        }

        $database->query("UPDATE " . TB_PREFIX . "fdata SET " . implode(', ', $setParts) . " WHERE vref = $wid");

        // Clear pending building, training, research, and demolition queues
        $database->query("DELETE FROM " . TB_PREFIX . "bdata WHERE wid = $wid");
        $database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $wid");
        $database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = $wid");
        $database->query("DELETE FROM " . TB_PREFIX . "demolition WHERE vref = $wid");

        // Update storage capacities in vdata and normalize resource stock to realistic partial fill (~35% cap)
        $whCap = self::getStorageCapacity($whLvl);
        $grCap = self::getStorageCapacity($grLvl);
        $initRes = min(400, (int)round($whCap * 0.35));
        $initCrop = min(400, (int)round($grCap * 0.35));
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = LEAST(wood, $initRes),
                clay = LEAST(clay, $initRes),
                iron = LEAST(iron, $initRes),
                crop = LEAST(crop, $initCrop),
                maxstore = $whCap,
                maxcrop = $grCap,
                lastupdate = " . time() . "
            WHERE wref = $wid
        ");

        // Invalidate database resource level cache
        if (class_exists('DatabaseVillageQueries') && method_exists('DatabaseVillageQueries', 'clearResourseLevelsCache')) {
            DatabaseVillageQueries::clearResourseLevelsCache();
        }

        // Recount population
        return self::recountPop($wid);
    }

    /**
     * Rebalance all bot villages on the server to natural population and garrison levels.
     * Can be invoked from Admin Bot Manager.
     */
    public static function rebalanceBotTroops($targetGarrison = 0, $rebalancePop = false, $targetDay = 1): array {
        global $database;

        $sql = "SELECT u.id as uid, u.username, u.tribe, u.regtime, u.timestamp, v.wref, v.pop
                FROM " . TB_PREFIX . "users u
                JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
                WHERE u.access = 2
                  AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
                ORDER BY u.id ASC";
        $bots = $database->query_return($sql);

        if (empty($bots)) {
            return [
                'status' => 'idle',
                'message' => 'No active bot accounts found',
                'villages_processed' => 0,
                'troops_trimmed' => 0,
                'total_troops_after' => 0,
            ];
        }

        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;
        $trimmedBots = 0;
        $totalTrimmedTroops = 0;
        $popAdjustedBots = 0;

        foreach ($bots as $bot) {
            $wid = (int)$bot['wref'];
            $tribe = (int)$bot['tribe'];
            $bUid = (int)$bot['uid'];

            // Determine effective age days
            if ($targetDay > 0) {
                $effectiveAgeDays = (float)$targetDay;
                // Synchronize bot regtime to chosen target day
                $syncedRegTime = time() - (int)($targetDay * 86400);
                $database->query("UPDATE " . TB_PREFIX . "users SET regtime = $syncedRegTime WHERE id = $bUid");
            } else {
                $regTime = (int)($bot['regtime'] ?? $bot['timestamp'] ?? 0);
                if ($regTime <= 0) {
                    $regTime = time() - 86400;
                }
                $ageSeconds = max(60, time() - $regTime);
                $effectiveAgeDays = ($ageSeconds / 86400.0) * sqrt($speed);
            }

            // 1. Rebalance buildings & population to natural age curve if requested
            if ($rebalancePop) {
                self::rebalanceVillageBuildings($wid, $tribe, $effectiveAgeDays);
                $popAdjustedBots++;
            }

            // 2. Rebalance garrison troops
            if ($targetGarrison > 0) {
                $allowedTroops = $targetGarrison;
            } else {
                $pop = (int)($database->getVillageField($wid, 'pop') ?: $bot['pop']);
                $homeReserve = self::getHomeReserve($pop);
                $ageArmyCap = (int)round(12 + ($effectiveAgeDays * 13));
                $popArmyCap = max(8, (int)round($pop * 0.35));
                $allowedTroops = min($ageArmyCap, $popArmyCap);
                $allowedTroops = max($homeReserve, min(250, $allowedTroops));
            }

            // Clear foreign reinforcements stationed in or sent from the bot village
            $database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE vref = $wid OR `from` = $wid");

            // Trim standing army to natural garrison size
            $trimmed = self::trimVillageTroops($wid, $tribe, $allowedTroops);
            if ($trimmed > 0) {
                $totalTrimmedTroops += $trimmed;
                $trimmedBots++;
            }
        }

        if (method_exists($database, 'clearUnitsCache')) {
            $database->clearUnitsCache();
        }

        // Calculate remaining troops across all bot villages
        $totalTroopsAfter = 0;
        $vrefs = array_map(function($b) { return (int)$b['wref']; }, $bots);
        if (!empty($vrefs)) {
            $vrefList = implode(',', $vrefs);
            $allUnits = $database->query_return("SELECT * FROM " . TB_PREFIX . "units WHERE vref IN ($vrefList)");
            if (!empty($allUnits)) {
                foreach ($allUnits as $uRow) {
                    for ($ui = 1; $ui <= 100; $ui++) {
                        $totalTroopsAfter += (int)($uRow['u' . $ui] ?? 0);
                    }
                }
            }
        }

        return [
            'status' => 'success',
            'villages_processed' => count($bots),
            'bots_total' => count($bots),
            'bots_trimmed' => $trimmedBots,
            'troops_trimmed' => $totalTrimmedTroops,
            'troops_removed' => $totalTrimmedTroops,
            'pop_adjusted_bots' => $popAdjustedBots,
            'total_troops_after' => $totalTroopsAfter,
            'target_day' => $targetDay,
        ];
    }

    /**
     * Automated Natural Village Expansion:
     * 100% genuine Travian mechanics for acquiring Village #2:
     * - Requires Residence (Padepokan) Level 10 (exp1 == 0).
     * - Requires genuine village storage capacity to fund settler costs.
     * - Trains 3 Settlers sequentially in Residence, consuming real resources and taking genuine training time.
     * - Requires sufficient Culture Points (CP) accumulated naturally by the bot's buildings.
     * - Requires 750 wood, 750 clay, 750 iron, 750 crop starting supplies.
     * - Dispatches genuine travel movement across the map (movement sort_type = 5).
     * - When movement arrives, standard AutomationTroopMovements::sendSettlersComplete() founds the village.
     * - Village #2 grows from Day 0 naturally based on its own creation timestamp.
     */
    public static function processExpansion(array $bot): ?string {
        global $database;

        if (!self::getConfig('BOT_AI_EXPANSION_ENABLED', true)) {
            return null;
        }

        $maxVillages = (int)self::getConfig('BOT_AI_MAX_VILLAGES', 2);
        if ($maxVillages <= 1) {
            return null;
        }

        $uid = (int)($bot['uid'] ?? $bot['id'] ?? 0);
        $wid = (int)$bot['wref'];
        $tribe = (int)$bot['tribe'];

        // 1. Check total villages owned by this bot
        $userVillages = $database->getProfileVillages($uid);
        $villageCount = is_array($userVillages) ? count($userVillages) : 1;
        if ($villageCount >= $maxVillages) {
            return null; // Already reached max villages
        }

        // 2. Check if this village has free expansion slot (exp1 == 0)
        $vFields = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'exp1, exp2, exp3', false) : null;
        if (!empty($vFields['exp1']) && (int)$vFields['exp1'] > 0) {
            return null; // Expansion slot 1 already used for this village
        }

        // 3. Check if there is already a settlement movement in progress from this village
        $inProgress = $database->query_return("
            SELECT moveid, endtime FROM " . TB_PREFIX . "movement
            WHERE `from` = $wid AND sort_type = 5 AND proc = 0
            LIMIT 1
        ");
        if (!empty($inProgress)) {
            $remaining = max(0, (int)$inProgress[0]['endtime'] - time());
            return "Settler expedition in transit to found new village (arrives in " . round($remaining / 60) . " mins)";
        }

        // 4. Check Residence level in this village (Prerequisite: Residence level 10)
        $rsLevel = self::getBuildingLevel($wid, 25, $bot['fdata'] ?? []);
        if ($rsLevel < 10) {
            return null; // Residence not yet level 10, bot continues natural progression
        }

        // 5. Settler Unit & Cost
        $settlerUnit = $tribe * 10;
        $uVar = 'u' . $settlerUnit;
        $uData = $GLOBALS[$uVar] ?? null;
        if (!is_array($uData) && file_exists(__DIR__ . '/Data/unitdata.php')) {
            require_once __DIR__ . '/Data/unitdata.php';
            $uData = $GLOBALS[$uVar] ?? null;
        }
        if (!is_array($uData)) {
            return null;
        }

        // 6. Check existing settlers (at home + in training)
        $unitRow = $database->query_return("SELECT u{$settlerUnit} as s FROM " . TB_PREFIX . "units WHERE vref = $wid");
        $homeSettlers = (int)($unitRow[0]['s'] ?? 0);

        $trainRow = $database->query_return("
            SELECT SUM(amt) as queued FROM " . TB_PREFIX . "training
            WHERE vref = $wid AND unit = $settlerUnit
        ");
        $queuedSettlers = (int)($trainRow[0]['queued'] ?? 0);
        $totalSettlers = $homeSettlers + $queuedSettlers;

        // 7. If total settlers < 3, train the next settler naturally if resources are available
        if ($totalSettlers < 3) {
            $vRes = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'wood, clay, iron, crop', false) : null;
            $curWood = (float)($vRes['wood'] ?? $bot['wood'] ?? $bot['vdata']['wood'] ?? 0);
            $curClay = (float)($vRes['clay'] ?? $bot['clay'] ?? $bot['vdata']['clay'] ?? 0);
            $curIron = (float)($vRes['iron'] ?? $bot['iron'] ?? $bot['vdata']['iron'] ?? 0);
            $curCrop = (float)($vRes['crop'] ?? $bot['crop'] ?? $bot['vdata']['crop'] ?? 0);

            $wCost = (int)$uData['wood'];
            $cCost = (int)$uData['clay'];
            $iCost = (int)$uData['iron'];
            $crCost = (int)$uData['crop'];

            // Pacing: train 1 settler when resources allow
            if ($curWood >= $wCost && $curClay >= $cCost && $curIron >= $iCost && $curCrop >= $crCost) {
                // Deduct resources
                if (method_exists($database, 'modifyResource')) {
                    $database->modifyResource($wid, $wCost, $cCost, $iCost, $crCost, 0);
                }

                // Calculate training duration
                $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;
                $baseTime = (int)($uData['time'] ?? 24000);
                $resBonus = pow(0.95, max(0, $rsLevel - 1));
                $duration = max(45, (int)round(($baseTime * $resBonus) / $speed));

                // Queue training in Residence
                $now = time();
                $lastQueue = $database->query_return("
                    SELECT timestamp FROM " . TB_PREFIX . "training
                    WHERE vref = $wid AND unit = $settlerUnit
                    ORDER BY timestamp DESC LIMIT 1
                ");
                $startTime = !empty($lastQueue) ? (int)$lastQueue[0]['timestamp'] : $now;
                $endTime = $startTime + $duration;

                $database->query("
                    INSERT INTO " . TB_PREFIX . "training (vref, unit, amt, pop, timestamp, eachtime, timestamp2)
                    VALUES ($wid, $settlerUnit, 1, 1, $endTime, $duration, $startTime + $duration)
                ");

                return "Trained Settler (" . ($totalSettlers + 1) . "/3 queued in Residence, duration: " . round($duration / 60) . " mins, Cost: {$wCost}w, {$cCost}c, {$iCost}i, {$crCost}cr)";
            }

            return null; // Saving resources to train settler
        }

        // If we reach here, we have 3 settlers either completed or in training
        if ($homeSettlers < 3) {
            return "Waiting for 3 Settlers to complete training ($homeSettlers/3 ready, $queuedSettlers in training)";
        }

        // 8. 3 Settlers are ready at home! Check Culture Points requirement
        $mode = defined('CP') ? (int)CP : 1;
        global ${'cp' . $mode};
        if (!isset(${'cp' . $mode})) {
            require_once __DIR__ . '/Data/cp.php';
        }
        $cpTable = ${'cp' . $mode} ?? $GLOBALS['cp1'] ?? [];
        $targetVillageIndex = $villageCount + 1;
        $neededCP = (int)($cpTable[$targetVillageIndex] ?? 2000);
        $userCP = (int)$database->getUserField($uid, 'cp', 0);

        if ($userCP < $neededCP) {
            return "3 Settlers ready, awaiting Culture Points: {$userCP}/{$neededCP} CP needed for village #{$targetVillageIndex}";
        }

        // 9. Check 750 starting resources for founding the new village
        $vRes = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'wood, clay, iron, crop', false) : null;
        $curWood = (float)($vRes['wood'] ?? $bot['wood'] ?? $bot['vdata']['wood'] ?? 0);
        $curClay = (float)($vRes['clay'] ?? $bot['clay'] ?? $bot['vdata']['clay'] ?? 0);
        $curIron = (float)($vRes['iron'] ?? $bot['iron'] ?? $bot['vdata']['iron'] ?? 0);
        $curCrop = (float)($vRes['crop'] ?? $bot['crop'] ?? $bot['vdata']['crop'] ?? 0);
        if ($curWood < 750 || $curClay < 750 || $curIron < 750 || $curCrop < 750) {
            return "3 Settlers ready, awaiting 750 of each resource for new village foundation";
        }

        // 10. Check Rally Point (must be at least lvl 1 to dispatch movement)
        $rpLvl = self::getBuildingLevel($wid, 16, $bot['fdata'] ?? []);
        if ($rpLvl < 1) {
            return null; // Rally point needed
        }

        // 11. Find a valid uninhabited wilderness tile near this village
        $fromCoor = $database->getCoor($wid);
        if (!$fromCoor) return null;
        $fromX = (int)$fromCoor['x'];
        $fromY = (int)$fromCoor['y'];

        $targetTile = self::findSettlingTile($wid, $fromX, $fromY);
        if (!$targetTile) {
            return "3 Settlers ready, searching for free uninhabited wilderness tile near ($fromX|$fromY)";
        }

        $targetWid = (int)$targetTile['id'];
        $toX = (int)$targetTile['x'];
        $toY = (int)$targetTile['y'];

        // 12. Deduct 750 of each resource and 3 settlers
        $database->modifyResource($wid, 750, 750, 750, 750, 0);
        $database->modifyUnit($wid, [$settlerUnit], [3], [0]);

        // 13. Calculate travel time
        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;
        $dist = sqrt(pow($toX - $fromX, 2) + pow($toY - $fromY, 2));
        $travelSec = max(60, (int)round(($dist / (5.0 * $speed)) * 3600));

        // 14. Dispatch settlement movement (sort_type = 5)
        $database->addMovement(5, $wid, $targetWid, 0, time(), time() + $travelSec);

        return "Dispatched 3 Settlers to found Village #{$targetVillageIndex} at ($toX|$toY)! Distance: " . round($dist, 1) . " tiles (" . round($travelSec / 60) . " mins travel)";
    }

    /**
     * Find an unoccupied, non-oasis wilderness tile suitable for a new village.
     */
    public static function findSettlingTile(int $fromWid, int $fromX, int $fromY): ?array {
        global $database;

        for ($rad = 10; $rad <= 30; $rad += 10) {
            $minX = $fromX - $rad;
            $maxX = $fromX + $rad;
            $minY = $fromY - $rad;
            $maxY = $fromY + $rad;

            $sql = "SELECT id, x, y, fieldtype
                    FROM " . TB_PREFIX . "wdata
                    WHERE occupied = 0
                      AND oasistype = 0
                      AND fieldtype > 0
                      AND x BETWEEN $minX AND $maxX
                      AND y BETWEEN $minY AND $maxY
                    ORDER BY ABS(x - $fromX) + ABS(y - $fromY) ASC
                    LIMIT 30";

            $candidates = $database->query_return($sql);
            if (empty($candidates)) {
                continue;
            }

            foreach ($candidates as $tile) {
                $tid = (int)$tile['id'];
                $dist = sqrt(pow((int)$tile['x'] - $fromX, 2) + pow((int)$tile['y'] - $fromY, 2));
                if ($dist < 2.5) {
                    continue; // Leave room immediately adjacent to capital
                }

                // Verify no other settlement movement is already heading to this tile
                $moveCheck = $database->query_return("
                    SELECT moveid FROM " . TB_PREFIX . "movement
                    WHERE `to` = $tid AND sort_type = 5 AND proc = 0
                    LIMIT 1
                ");
                if (!empty($moveCheck)) {
                    continue;
                }

                return $tile;
            }
        }

        return null;
    }

    /**
     * Retrieve detailed expansion progress for a bot village (for Admin display).
     */
    public static function getExpansionStatus(int $uid, int $wid, int $tribe): array {
        global $database;

        $userVillages = $database->getProfileVillages($uid);
        $villageCount = is_array($userVillages) ? count($userVillages) : 1;
        $maxVillages = (int)self::getConfig('BOT_AI_MAX_VILLAGES', 2);
        $rsLevel = self::getBuildingLevel($wid, 25);
        $settlerUnit = $tribe * 10;

        $unitRow = $database->query_return("SELECT u{$settlerUnit} as s FROM " . TB_PREFIX . "units WHERE vref = $wid");
        $homeSettlers = (int)($unitRow[0]['s'] ?? 0);

        $trainRow = $database->query_return("
            SELECT SUM(amt) as queued FROM " . TB_PREFIX . "training
            WHERE vref = $wid AND unit = $settlerUnit
        ");
        $queuedSettlers = (int)($trainRow[0]['queued'] ?? 0);

        $inProgress = $database->query_return("
            SELECT m.moveid, m.endtime, w.x, w.y
            FROM " . TB_PREFIX . "movement m
            LEFT JOIN " . TB_PREFIX . "wdata w ON m.to = w.id
            WHERE m.`from` = $wid AND m.sort_type = 5 AND m.proc = 0
            LIMIT 1
        ");

        $mode = defined('CP') ? (int)CP : 1;
        global ${'cp' . $mode};
        if (!isset(${'cp' . $mode})) {
            @include_once __DIR__ . '/Data/cp.php';
        }
        $cpTable = ${'cp' . $mode} ?? $GLOBALS['cp1'] ?? [];
        $neededCP = (int)($cpTable[$villageCount + 1] ?? 2000);
        $userCP = (int)$database->getUserField($uid, 'cp', 0);

        $statusText = '';
        if ($villageCount >= $maxVillages) {
            $statusText = "✅ Memiliki $villageCount Desa (Maksimal)";
        } elseif (!empty($inProgress)) {
            $rem = max(0, (int)$inProgress[0]['endtime'] - time());
            $tx = $inProgress[0]['x'] ?? '?';
            $ty = $inProgress[0]['y'] ?? '?';
            $statusText = "🚶 Ekspedisi ke ($tx|$ty) - " . round($rem / 60) . "m";
        } elseif ($homeSettlers >= 3) {
            if ($userCP < $neededCP) {
                $statusText = "⏳ 3 Pionir Siap (Menunggu CP: $userCP/$neededCP)";
            } else {
                $statusText = "🏕️ 3 Pionir Siap (Menyiapkan ekspedisi)";
            }
        } elseif ($queuedSettlers > 0) {
            $statusText = "🔨 Melatih Pionir (" . ($homeSettlers + $queuedSettlers) . "/3)";
        } elseif ($rsLevel >= 10) {
            $statusText = "🏛️ Padepokan Lvl 10 (Menabung bahan pionir)";
        } else {
            $statusText = "🏗️ Padepokan Lvl $rsLevel/10";
        }

        return [
            'villages' => $villageCount,
            'max_villages' => $maxVillages,
            'rs_level' => $rsLevel,
            'cp' => $userCP,
            'cp_needed' => $neededCP,
            'settlers_home' => $homeSettlers,
            'settlers_queued' => $queuedSettlers,
            'in_progress' => !empty($inProgress),
            'status_text' => $statusText,
        ];
    }

    /**
     * Automated Nusantara Paranormal & Astral Spirits Management (Suku Nusantara - Tribe 10).
     * 100% Natural mechanics:
     * - Only applies to Nusantara tribe (tribe 10) with an existing Sanggar Paranormal (GID 51, lvl >= 1).
     * - Strictly respects maximum astral spirit capacity based on building level (Paranormal::getMaxCapacity).
     * - Consumes genuine village resources; leaves a safe economic reserve so village never starves.
     * - Incremental, natural summoning batches (1 to 2 spirits per cycle, organic cadence).
     * - Naturally activates Khodam Tuyul ritual (24h raid bounty) only when village has surplus resources and active raiding.
     */
    public static function processParanormal(array $bot): ?string {
        global $database;

        $tribe = (int)($bot['tribe'] ?? 0);
        if ($tribe !== 10) {
            return null;
        }

        if (!self::getConfig('BOT_AI_PARANORMAL_ENABLED', true)) {
            return null;
        }

        if (!class_exists('Paranormal')) {
            require_once __DIR__ . '/Paranormal.php';
        }

        $wid = (int)($bot['wref'] ?? $bot['wid'] ?? 0);
        if ($wid <= 0) {
            return null;
        }

        $spLevel = self::getBuildingLevel($wid, 51);
        if ($spLevel < 1) {
            return null;
        }

        Paranormal::ensureTableExists();
        $spirits = Paranormal::getVillageSpirits($wid);
        $maxCap = Paranormal::getMaxCapacity($spLevel);
        $usedSlots = Paranormal::getUsedSlots($spirits);
        $freeSlots = max(0, $maxCap - $usedSlots);

        // Fetch current village resources
        $vRes = method_exists($database, 'getVillageFields') ? $database->getVillageFields($wid, 'wood, clay, iron, crop', false) : null;
        $curWood = (float)($vRes['wood'] ?? $bot['wood'] ?? 0);
        $curClay = (float)($vRes['clay'] ?? $bot['clay'] ?? 0);
        $curIron = (float)($vRes['iron'] ?? $bot['iron'] ?? 0);
        $curCrop = (float)($vRes['crop'] ?? $bot['crop'] ?? 0);

        // Safe reserve: keep at least 100 of each resource so normal village production/building isn't halted
        $reserve = 100;
        $availWood = max(0, $curWood - $reserve);
        $availClay = max(0, $curClay - $reserve);
        $availIron = max(0, $curIron - $reserve);
        $availCrop = max(0, $curCrop - $reserve);

        // 1. Natural Khodam Tuyul Ritual Activation (+15% raid bounty for 24h)
        // Conditions: Sanggar Paranormal lvl >= 2, raid enabled, tuyul not active, plenty of surplus resources
        $attacksEnabled = (bool)self::getConfig('BOT_AI_ENABLE_ATTACKS', true);
        $tuyulUntil = (int)($spirits['tuyul_until'] ?? 0);
        $tuyulCost = Paranormal::$tuyulCost; // 500 wood, 500 clay, 500 iron, 1000 crop

        if ($attacksEnabled && $spLevel >= 2 && $tuyulUntil <= time()) {
            if ($availWood >= $tuyulCost['wood'] && $availClay >= $tuyulCost['clay'] && $availIron >= $tuyulCost['iron'] && $availCrop >= $tuyulCost['crop']) {
                // Organic activation chance
                if (rand(1, 100) <= 40) {
                    $database->modifyResource($wid, $tuyulCost['wood'], $tuyulCost['clay'], $tuyulCost['iron'], $tuyulCost['crop'], 0);
                    $newUntil = time() + Paranormal::$tuyulDuration;
                    $table = TB_PREFIX . "paranormal";
                    $database->query("UPDATE `$table` SET `tuyul_until` = $newUntil WHERE `vref` = $wid");
                    return "Activated Khodam Tuyul ritual (+15% raid bounty for 24 hours)";
                }
            }
        }

        // 2. Astral Spirits Summoning (Pocong, Kuntilanak, Genderuwo)
        if ($freeSlots <= 0) {
            return null; // Slot kapasitas penuh
        }

        // Natural cadence: don't summon on every single tick
        if (rand(1, 100) > 50) {
            return null;
        }

        // Build list of eligible spirits based on resources and slots
        $candidates = [];

        // Pocong (Type 1, 1 slot, def_i: 40, def_c: 20)
        $pCost = Paranormal::$spiritStats[1]['cost'];
        if ($freeSlots >= 1 && $availWood >= $pCost['wood'] && $availClay >= $pCost['clay'] && $availIron >= $pCost['iron'] && $availCrop >= $pCost['crop']) {
            $curP = (int)($spirits['pocong'] ?? 0);
            $curK = (int)($spirits['kuntilanak'] ?? 0);
            $weight = ($curP <= $curK) ? 2 : 1;
            for ($w = 0; $w < $weight; $w++) {
                $candidates[] = 1;
            }
        }

        // Kuntilanak (Type 2, 1 slot, def_i: 20, def_c: 60)
        $kCost = Paranormal::$spiritStats[2]['cost'];
        if ($freeSlots >= 1 && $availWood >= $kCost['wood'] && $availClay >= $kCost['clay'] && $availIron >= $kCost['iron'] && $availCrop >= $kCost['crop']) {
            $curP = (int)($spirits['pocong'] ?? 0);
            $curK = (int)($spirits['kuntilanak'] ?? 0);
            $weight = ($curK <= $curP) ? 2 : 1;
            for ($w = 0; $w < $weight; $w++) {
                $candidates[] = 2;
            }
        }

        // Genderuwo (Type 3, 2 slots, def_i: 75, def_c: 75, heavy defender)
        if ($spLevel >= 3 && $freeSlots >= 2) {
            $gCost = Paranormal::$spiritStats[3]['cost'];
            if ($availWood >= $gCost['wood'] && $availClay >= $gCost['clay'] && $availIron >= $gCost['iron'] && $availCrop >= $gCost['crop']) {
                $candidates[] = 3;
            }
        }

        if (empty($candidates)) {
            return null; // Tidak ada cukup sumber daya surplus
        }

        $chosenType = $candidates[array_rand($candidates)];
        $stat = Paranormal::$spiritStats[$chosenType];
        $slotNeeded = $stat['slots'];

        // Natural batch size: 1 unit per cycle (or 2 if single-slot, slots >= 3, and abundant resources)
        $batch = 1;
        if ($slotNeeded == 1 && $freeSlots >= 3 && rand(1, 100) <= 25) {
            $reqW2 = $stat['cost']['wood'] * 2;
            $reqC2 = $stat['cost']['clay'] * 2;
            $reqI2 = $stat['cost']['iron'] * 2;
            $reqCr2 = $stat['cost']['crop'] * 2;
            if ($availWood >= $reqW2 && $availClay >= $reqC2 && $availIron >= $reqI2 && $availCrop >= $reqCr2) {
                $batch = 2;
            }
        }

        $totalWood = $stat['cost']['wood'] * $batch;
        $totalClay = $stat['cost']['clay'] * $batch;
        $totalIron = $stat['cost']['iron'] * $batch;
        $totalCrop = $stat['cost']['crop'] * $batch;

        // Deduct genuine resources
        $database->modifyResource($wid, $totalWood, $totalClay, $totalIron, $totalCrop, 0);

        // Update paranormal table
        $field = ($chosenType === 1) ? 'pocong' : (($chosenType === 2) ? 'kuntilanak' : 'genderuwo');
        $table = TB_PREFIX . "paranormal";
        $database->query("UPDATE `$table` SET `$field` = `$field` + $batch WHERE `vref` = $wid");

        $newUsed = $usedSlots + ($slotNeeded * $batch);
        return "Summoned natural astral spirit: {$batch}x {$stat['name']} (Slots: {$newUsed}/{$maxCap})";
    }

    /**
     * Get paranormal status of a village (level, capacities, spirits, tuyul).
     */
    public static function getParanormalStatus(int $wid): array {
        if (!class_exists('Paranormal')) {
            require_once __DIR__ . '/Paranormal.php';
        }
        $spLevel = self::getBuildingLevel($wid, 51);
        $spirits = Paranormal::getVillageSpirits($wid);
        $maxCap = Paranormal::getMaxCapacity($spLevel);
        $usedSlots = Paranormal::getUsedSlots($spirits);

        return [
            'level' => $spLevel,
            'max_cap' => $maxCap,
            'used_slots' => $usedSlots,
            'free_slots' => max(0, $maxCap - $usedSlots),
            'pocong' => (int)($spirits['pocong'] ?? 0),
            'kuntilanak' => (int)($spirits['kuntilanak'] ?? 0),
            'genderuwo' => (int)($spirits['genderuwo'] ?? 0),
            'tuyul_active' => (!empty($spirits['tuyul_until']) && (int)$spirits['tuyul_until'] > time()),
            'tuyul_until' => (int)($spirits['tuyul_until'] ?? 0),
        ];
    }

    /**
     * Automated Hero AI:
     * 1. Auto-create starter hero if missing.
     * 2. Auto-revive hero if dead.
     * 3. Auto-dispatch hero on adventure if alive, healthy (>= 30% HP), and idle at home.
     */
    public static function processHero(array $bot): ?string {
        global $database;

        $uid = (int)($bot['uid'] ?? $bot['id'] ?? 0);
        $wid = (int)($bot['wref'] ?? $bot['wid'] ?? 0);
        $tribe = (int)($bot['tribe'] ?? 0);

        if ($uid <= 0 || $wid <= 0) {
            return null;
        }

        // 1. Check hero record
        $heroRow = $database->query_return("SELECT heroid, dead, health, inrevive, trainingtime, wref FROM " . TB_PREFIX . "hero WHERE uid = $uid LIMIT 1");

        if (empty($heroRow[0])) {
            // Auto-create starter hero
            if (class_exists('Units')) {
                Units::createStarterHero($uid, $wid, $tribe, $bot['username']);
                return "Starter hero auto-created";
            }
            return null;
        }

        $hero = $heroRow[0];
        $isDead = (int)$hero['dead'];
        $health = (float)$hero['health'];
        $inRevive = (int)$hero['inrevive'];
        $trainingTime = (int)$hero['trainingtime'];

        // 2. Auto-revive if dead
        if ($isDead === 1) {
            if ($inRevive === 1) {
                if ($trainingTime <= time()) {
                    // Ready to finish revival
                    $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 0, inrevive = 0, health = 100, lastupdate = " . time() . " WHERE uid = $uid");
                    $database->query("UPDATE " . TB_PREFIX . "units SET hero = 1 WHERE vref = $wid");
                    return "Hero revive completed (100% HP, back in garrison)";
                }
                return "Hero in revival (" . max(0, $trainingTime - time()) . "s remaining)";
            } else {
                // Queue auto-revive (default 180s = 3 minutes)
                $reviveDuration = defined('BOT_AI_REVIVE_DURATION') ? (int)BOT_AI_REVIVE_DURATION : 180;
                $readyTime = time() + $reviveDuration;
                $database->query("UPDATE " . TB_PREFIX . "hero SET inrevive = 1, trainingtime = $readyTime, wref = $wid WHERE uid = $uid");
                return "Hero auto-revival started (ready in {$reviveDuration}s)";
            }
        }

        // Clean up damaged/desynced hero if health is 0
        if ($health <= 0) {
            $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, inrevive = 1, trainingtime = " . (time() + 180) . ", wref = $wid WHERE uid = $uid");
            $database->query("UPDATE " . TB_PREFIX . "units SET hero = 0 WHERE vref = $wid");
            return "Cleared wounded hero and queued revival";
        }

        // 3. Auto Adventure (if hero is at home, healthy >= 30% HP, and T4 adventure enabled)
        if (defined('NEW_FUNCTIONS_HERO_T4') && NEW_FUNCTIONS_HERO_T4 && class_exists('HeroAdventure')) {
            $unitsRow = $database->query_return("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $wid LIMIT 1");
            $heroAtHome = !empty($unitsRow[0]) && (int)$unitsRow[0]['hero'] > 0;

            if ($heroAtHome && $health >= 30) {
                // Ensure hero is not currently marching in an outgoing attack or adventure
                $activeMove = $database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "movement WHERE `from` = $wid AND sort_type IN (3, 20) AND proc = 0");
                if (empty($activeMove[0]) || (int)$activeMove[0]['c'] === 0) {
                    $advObj = new HeroAdventure();
                    $running = $advObj->getRunning($uid);
                    if (!$running) {
                        $offers = $advObj->getOffers($uid);
                        if (empty($offers)) {
                            $advObj->generateOffers($uid);
                            $offers = $advObj->getOffers($uid);
                        }
                        if (!empty($offers)) {
                            $targetAdv = $offers[0];
                            $resCode = $advObj->startAdventure($uid, (int)$targetAdv['id']);
                            if ($resCode === HeroAdventure::START_OK) {
                                return "Hero embarked on adventure #{$targetAdv['id']} (diff: {$targetAdv['difficulty']})";
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Automated Alliance Mutual Defense (Bantuan Bertahan Aliansi):
     * When any bot or member in the same alliance is attacked, other bots in that
     * alliance dispatch defensive reinforcements (attack_type = 2) to garrison
     * and defend the ally's village.
     *
     * Detection priorities:
     * 1. Active Incoming Attacks (Priority 1):
     *    Checks movement table for attacks/raids currently marching towards an ally village.
     *    Dispatches ~40% of available defense troops (up to 150) + Hero if available.
     * 2. Recent Attack Casualties (Priority 2):
     *    Checks ndata for reports (ntype 4,5,6,7) within the last 2 hours.
     *    Dispatches ~25% of available defense troops (up to 80) to replenish garrison.
     *
     * Safeguards:
     * - Preserves 60-75% garrison at home for self defense.
     * - Prevents duplicate reinforcements (if this bot is already marching or stationed there).
     * - Caps helper bots per village (max 3 bots reinforcing the same village simultaneously).
     * - Excludes internal alliance attacks.
     *
     * @param array $bot Bot record row
     * @return string|null Log message if reinforcement dispatched, or null
     */
    public static function processAllianceDefense(array $bot): ?string {
        global $database;
        if (!isset($database) || $database === null) {
            return null;
        }

        // Feature toggle (default: true)
        if (defined('BOT_AI_ALLIANCE_DEFENSE_ENABLED') && !BOT_AI_ALLIANCE_DEFENSE_ENABLED) {
            return null;
        }

        $botAlliance = (int)($bot['alliance'] ?? 0);
        if ($botAlliance <= 0) {
            return null; // Bot is not part of any alliance
        }

        $wid = (int)$bot['wref'];
        $uid = (int)$bot['uid'];
        $tribe = (int)$bot['tribe'];

        // Get bot village coordinates
        $botCoor = $database->getCoor($wid);
        if (!$botCoor) {
            return null;
        }

        // Check standing troops at home
        $tribeUnits = self::getTribeUnits($tribe);
        $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
        $colsSql = implode(', ', $unitCols);
        $res = $database->query_return("SELECT {$colsSql}, hero FROM " . TB_PREFIX . "units WHERE vref = $wid");
        if (empty($res[0])) {
            return null;
        }
        $homeUnits = $res[0];

        // Group available troops by role (prioritize defensive units)
        // For Gauls (tribe 3), basic unit 21 (Phalanx) is their primary defender (40 inf / 50 cav def)
        $defensiveRoles = ($tribe === 3)
            ? ['basic', 'inf_def', 'cav_def']
            : ['inf_def', 'cav_def', 'basic'];
        $secondaryRoles = ['inf_adv', 'cav_heavy', 'cav_light'];

        $availableDef = [];
        $totalDefAvailable = 0;
        foreach (array_merge($defensiveRoles, $secondaryRoles) as $role) {
            if (isset($tribeUnits[$role])) {
                $uNum = $tribeUnits[$role];
                $cnt = (int)($homeUnits['u' . $uNum] ?? 0);
                if ($cnt > 0 && !isset($availableDef[$uNum])) {
                    $availableDef[$uNum] = $cnt;
                    $totalDefAvailable += $cnt;
                }
            }
        }

        $pop = max(10, (int)($bot['pop'] ?? 10));
        $homeReserve = self::getHomeReserve($pop);
        $deployableDef = max(0, $totalDefAvailable - $homeReserve);

        $minTroops = defined('BOT_AI_MIN_DEFENSE_TROOPS') ? (int)BOT_AI_MIN_DEFENSE_TROOPS : 10;
        if ($deployableDef < $minTroops) {
            return null; // Garnisun pertahanan rumah wajib dipertahankan; tidak ada sisa pasukan untuk dikirim
        }

        // Limit concurrent outgoing reinforcement movements per bot (default 2)
        $maxOutgoingDefense = defined('BOT_AI_MAX_OUTGOING_DEFENSE') ? (int)BOT_AI_MAX_OUTGOING_DEFENSE : 2;
        $activeReinf = $database->query_return("
            SELECT COUNT(*) as active_reinf
            FROM " . TB_PREFIX . "movement m
            JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            WHERE m.`from` = $wid AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2
        ");
        if ((int)($activeReinf[0]['active_reinf'] ?? 0) >= $maxOutgoingDefense) {
            return null;
        }

        $maxDefenseDist = defined('BOT_AI_MAX_DEFENSE_DISTANCE') ? (float)BOT_AI_MAX_DEFENSE_DISTANCE : 120.0;
        $maxHelpersPerVillage = defined('BOT_AI_MAX_HELPERS_PER_VILLAGE') ? (int)BOT_AI_MAX_HELPERS_PER_VILLAGE : 3;

        // =========================================================================
        // STEP 1: SEARCH FOR THREATENED ALLY VILLAGES IN SAME ALLIANCE
        // =========================================================================
        $threatTarget = null;
        $threatReason = '';
        $threatLevel = 0; // 1 = Active incoming attack, 2 = Recent attack recovery

        // 1A. PRIORITY 1: Active Incoming Attacks / Raids on an alliance village
        $activeIncoming = $database->query_return("
            SELECT m.moveid, m.to as target_wid, m.from as attacker_wid, m.endtime,
                   v.name as vname, v.pop, w.x, w.y, u.username as ally_username, u.id as ally_uid,
                   a.attack_type
            FROM " . TB_PREFIX . "movement m
            JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            JOIN " . TB_PREFIX . "vdata v ON m.to = v.wref
            JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
            JOIN " . TB_PREFIX . "users u ON v.owner = u.id
            LEFT JOIN " . TB_PREFIX . "vdata v_atk ON m.`from` = v_atk.wref
            LEFT JOIN " . TB_PREFIX . "users u_atk ON v_atk.owner = u_atk.id
            WHERE u.alliance = $botAlliance
              AND m.to != $wid
              AND m.proc = 0
              AND m.sort_type = 3
              AND a.attack_type IN (3, 4)
              AND m.endtime > " . time() . "
              AND (u_atk.alliance IS NULL OR u_atk.alliance != $botAlliance)
            ORDER BY m.endtime ASC
            LIMIT 10
        ");

        if (!empty($activeIncoming)) {
            foreach ($activeIncoming as $inc) {
                $tWid = (int)$inc['target_wid'];
                $dist = self::getDistance($botCoor['x'], $botCoor['y'], $inc['x'], $inc['y']);
                if ($dist > $maxDefenseDist || $dist < 0.5) {
                    continue;
                }

                // Check if this bot is already marching troops or already has stationed troops there
                $alreadyMarching = $database->query_return("
                    SELECT m.moveid
                    FROM " . TB_PREFIX . "movement m
                    JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
                    WHERE m.`from` = $wid AND m.`to` = $tWid AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2
                    LIMIT 1
                ");
                if (!empty($alreadyMarching)) {
                    continue;
                }

                $alreadyStationed = $database->query_return("
                    SELECT id FROM " . TB_PREFIX . "enforcement WHERE vref = $tWid AND `from` = $wid LIMIT 1
                ");
                if (!empty($alreadyStationed)) {
                    continue;
                }

                // Check how many helpers are already aiding this village
                $currentHelpers = $database->query_return("
                    SELECT COUNT(*) as total_marching
                    FROM " . TB_PREFIX . "movement m
                    JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
                    WHERE m.`to` = $tWid AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2
                ");
                $marchingCount = (int)($currentHelpers[0]['total_marching'] ?? 0);
                if ($marchingCount >= $maxHelpersPerVillage) {
                    continue;
                }

                $timeLeft = max(1, (int)$inc['endtime'] - time());
                $minsLeft = round($timeLeft / 60, 1);
                $atkTypeLabel = ((int)$inc['attack_type'] === 3) ? 'Serangan Penuh' : 'Raid';
                $threatTarget = [
                    'wref' => $tWid,
                    'vname' => $inc['vname'],
                    'x' => $inc['x'],
                    'y' => $inc['y'],
                    'dist' => $dist,
                    'ally_username' => $inc['ally_username'],
                    'ally_uid' => (int)$inc['ally_uid'],
                ];
                $threatReason = "Diserang ({$atkTypeLabel}) - Tiba dalam {$minsLeft}m";
                $threatLevel = 1;
                break;
            }
        }

        // 1B. PRIORITY 2: Recent Attack Casualties (Alliance member attacked in last 2 hours)
        if ($threatTarget === null) {
            $recentAttacks = $database->query_return("
                SELECT n.toWref as target_wid, n.time, n.data,
                       v.name as vname, v.pop, w.x, w.y, u.username as ally_username, u.id as ally_uid
                FROM " . TB_PREFIX . "ndata n
                JOIN " . TB_PREFIX . "users u ON n.uid = u.id
                JOIN " . TB_PREFIX . "vdata v ON n.toWref = v.wref
                JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
                WHERE u.alliance = $botAlliance
                  AND n.toWref != $wid
                  AND n.ntype IN (4, 5, 6, 7)
                  AND n.time >= " . (time() - 7200) . "
                ORDER BY n.id DESC
                LIMIT 15
            ");

            if (!empty($recentAttacks)) {
                $checkedWrefs = [];
                foreach ($recentAttacks as $atk) {
                    $tWid = (int)$atk['target_wid'];
                    if ($tWid <= 0 || isset($checkedWrefs[$tWid])) {
                        continue;
                    }
                    $checkedWrefs[$tWid] = true;

                    $dist = self::getDistance($botCoor['x'], $botCoor['y'], $atk['x'], $atk['y']);
                    if ($dist > $maxDefenseDist || $dist < 0.5) {
                        continue;
                    }

                    // Check if this bot is already marching troops or already has stationed troops there
                    $alreadyMarching = $database->query_return("
                        SELECT m.moveid
                        FROM " . TB_PREFIX . "movement m
                        JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
                        WHERE m.`from` = $wid AND m.`to` = $tWid AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2
                        LIMIT 1
                    ");
                    if (!empty($alreadyMarching)) {
                        continue;
                    }

                    $alreadyStationed = $database->query_return("
                        SELECT id FROM " . TB_PREFIX . "enforcement WHERE vref = $tWid AND `from` = $wid LIMIT 1
                    ");
                    if (!empty($alreadyStationed)) {
                        continue;
                    }

                    $currentHelpers = $database->query_return("
                        SELECT COUNT(*) as total_marching
                        FROM " . TB_PREFIX . "movement m
                        JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
                        WHERE m.`to` = $tWid AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2
                    ");
                    $marchingCount = (int)($currentHelpers[0]['total_marching'] ?? 0);
                    if ($marchingCount >= $maxHelpersPerVillage) {
                        continue;
                    }

                    $agoMins = max(1, round((time() - (int)$atk['time']) / 60));
                    $threatTarget = [
                        'wref' => $tWid,
                        'vname' => $atk['vname'],
                        'x' => $atk['x'],
                        'y' => $atk['y'],
                        'dist' => $dist,
                        'ally_username' => $atk['ally_username'],
                        'ally_uid' => (int)$atk['ally_uid'],
                    ];
                    $threatReason = "Baru diserang {$agoMins}m lalu (Pemulihan Garnisun)";
                    $threatLevel = 2;
                    break;
                }
            }
        }

        if ($threatTarget === null) {
            return null; // No ally currently requires defensive aid
        }

        // =========================================================================
        // STEP 2: CALCULATE TROOP REINFORCEMENTS TO DISPATCH (FROM DEPLOYABLE SURPLUS)
        // =========================================================================
        // Active attack (Priority 1): send ~40% of deployable defense troops (up to 100)
        // Recent recovery (Priority 2): send ~25% of deployable defense troops (up to 50)
        $ratio = ($threatLevel === 1) ? 0.40 : 0.25;
        $maxCap = ($threatLevel === 1) ? 100 : 50;
        $targetPartySize = min($maxCap, max($minTroops, (int)floor($deployableDef * $ratio)));
        $targetPartySize = min($targetPartySize, $deployableDef);

        $dispatched = [];
        $dispatchedSpeeds = [];
        $totalDispatched = 0;

        foreach ($availableDef as $uNum => $avail) {
            $portion = (int)round(($avail / $totalDefAvailable) * $targetPartySize);
            $remainingCap = $deployableDef - $totalDispatched;
            $send = min($avail, max(0, $portion), $remainingCap);
            if ($send > 0) {
                $dispatched[$uNum] = $send;
                $dispatchedSpeeds[] = self::getUnitSpeed($uNum);
                $totalDispatched += $send;
            }
        }

        // Guarantee minimum troops while strictly respecting deployableDef
        if ($totalDispatched < $minTroops && $totalDispatched < $deployableDef) {
            $primaryUnit = key($availableDef);
            $add = min($availableDef[$primaryUnit] - ($dispatched[$primaryUnit] ?? 0), $deployableDef - $totalDispatched, $minTroops - $totalDispatched);
            if ($add > 0) {
                $dispatched[$primaryUnit] = ($dispatched[$primaryUnit] ?? 0) + $add;
                $totalDispatched += $add;
                if (!in_array(self::getUnitSpeed($primaryUnit), $dispatchedSpeeds, true)) {
                    $dispatchedSpeeds[] = self::getUnitSpeed($primaryUnit);
                }
            }
        }

        if ($totalDispatched <= 0) {
            return null;
        }

        // Hero participation: If hero is alive, healthy (health >= 50), stationed at home,
        // and threat is an active incoming attack (Priority 1)
        $sendHero = 0;
        if ($threatLevel === 1 && !empty($homeUnits['hero']) && (int)$homeUnits['hero'] > 0) {
            $hCheck = $database->query_return("SELECT heroid, health, dead FROM " . TB_PREFIX . "hero WHERE uid = $uid AND dead = 0 LIMIT 1");
            if (!empty($hCheck[0]) && (int)$hCheck[0]['dead'] === 0 && (float)$hCheck[0]['health'] >= 50) {
                $sendHero = 1;
                $database->query("UPDATE " . TB_PREFIX . "units SET hero = hero - 1 WHERE vref = $wid AND hero > 0");
            }
        }

        // 1. Deduct troops from bot village (mode 0 = subtract)
        $unitNums = array_keys($dispatched);
        $amounts = array_values($dispatched);
        $modes = array_fill(0, count($amounts), 0);
        $database->modifyUnit($wid, $unitNums, $amounts, $modes);

        // 2. Travel duration based on slowest marching unit
        $marchSpeed = !empty($dispatchedSpeeds) ? min($dispatchedSpeeds) : 6;
        $incSpeed = defined('INCREASE_SPEED') ? max(1, (int)INCREASE_SPEED) : 1;
        $duration = max(30, (int)round(($threatTarget['dist'] / $marchSpeed) * 3600 / $incSpeed));

        // 3. Map dispatched units to tribe slots t1..t10
        $t = array_fill(1, 10, 0);
        $baseUnit = ($tribe - 1) * 10;
        foreach ($dispatched as $uNum => $cnt) {
            $slot = $uNum - $baseUnit;
            if ($slot >= 1 && $slot <= 10) {
                $t[$slot] = $cnt;
            }
        }

        // 4. Create attack entry with attack_type = 2 (Reinforcement / Bantuan Bertahan)
        $attackId = $database->addAttack(
            $wid,
            $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], $sendHero,
            2, // Reinforcement
            0, 0, 0
        );

        if (!$attackId) {
            // Rollback on failure
            $database->modifyUnit($wid, $unitNums, $amounts, array_fill(0, count($amounts), 1));
            if ($sendHero) {
                $database->query("UPDATE " . TB_PREFIX . "units SET hero = hero + 1 WHERE vref = $wid");
            }
            return null;
        }

        // 5. Create troop movement entry
        $database->addMovement(
            3, // Outward movement
            $wid,
            $threatTarget['wref'],
            $attackId,
            time(),
            time() + $duration
        );

        // Build human-friendly log
        $unitParts = [];
        foreach ($dispatched as $uNum => $cnt) {
            $unitParts[] = self::getUnitName($uNum) . ": " . $cnt;
        }
        if ($sendHero) {
            $unitParts[] = "Hero: 1";
        }
        $unitsDesc = implode(', ', $unitParts);
        $distRounded = round($threatTarget['dist'], 1);
        $durMins = round($duration / 60, 1);

        return "Bantuan bertahan ({$totalDispatched} pasukan [{$unitsDesc}]) dikirim ke sekutu '{$threatTarget['ally_username']}' ({$threatTarget['vname']} [{$threatTarget['x']}|{$threatTarget['y']}]) | Jarak: {$distRounded} | Tiba: {$durMins}m | Status: {$threatReason}";
    }

    /**
     * Check if current time is within peaceful nighttime window (20:00 - 08:00).
     * Bots are strictly prohibited from attacking anyone (players, other bots, oases, revenge)
     * during this blackout window so they can safely train/regenerate troops.
     * Attacks are only permitted from 08:00:00 to 19:59:59 server time.
     */
    public static function isNightTime(): bool {
        $startHour = defined('BOT_AI_ATTACK_START_HOUR') ? (int)BOT_AI_ATTACK_START_HOUR : 8;
        $endHour   = defined('BOT_AI_ATTACK_END_HOUR') ? (int)BOT_AI_ATTACK_END_HOUR : 20;
        $hour      = (int)date('G');

        if ($startHour < $endHour) {
            return ($hour < $startHour || $hour >= $endHour);
        }
        return ($hour >= $endHour && $hour < $startHour);
    }

    /**
     * Process automated raids to nearby villages.
     * Supports dynamic scaling:
     * - Raid party size scales with standing army (up to 120 troops under dynamic scaling)
     * - Dispatches combined arms (infantry + cavalry + Hero)
     * - Calculates duration using the slowest marching unit
     */
    public static function processAttack(array $bot) {
        // Strict Blackout Window: Bots are forbidden from attacking ANYONE (including bots, players, oases, revenge)
        // between 20:00 (8:00 PM) and 08:00 (8:00 AM) server time so troops can safely regenerate.
        if (self::isNightTime()) {
            return null;
        }

        global $database;

        $wid = (int)$bot['wref'];
        $uid = (int)$bot['uid'];
        $tribe = (int)$bot['tribe'];
        $pop = max(10, (int)$bot['pop']);

        // Mandatory Home Garrison Reserve:
        $homeReserve = self::getHomeReserve($pop);
        $minDeployable = max(15, min(30, (int)round($pop * 0.10)));
        $minTroopsConfig = defined('BOT_AI_MIN_RAID_TROOPS') ? (int)BOT_AI_MIN_RAID_TROOPS : 15;
        $minAttackForce = max($minDeployable, $minTroopsConfig);

        $attackChance = defined('BOT_AI_ATTACK_CHANCE') ? (int)BOT_AI_ATTACK_CHANCE : 35;
        $maxDistance = defined('BOT_AI_MAX_DISTANCE') ? (float)BOT_AI_MAX_DISTANCE : 70.0;
        $maxConcurrent = defined('BOT_AI_MAX_CONCURRENT_ATTACKS') ? (int)BOT_AI_MAX_CONCURRENT_ATTACKS : 1;
        $dynamicScaling = defined('BOT_AI_DYNAMIC_SCALING') ? (bool)BOT_AI_DYNAMIC_SCALING : true;

        // Allow up to 2 concurrent raids for developed villages
        if ($dynamicScaling && $pop >= 300) {
            $maxConcurrent = max(2, $maxConcurrent);
        }

        $tribeUnits = self::getTribeUnits($tribe);

        // Fetch available units at home (including hero)
        $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
        $colsSql = implode(', ', $unitCols);
        $res = $database->query_return("SELECT {$colsSql}, hero FROM " . TB_PREFIX . "units WHERE vref = $wid");

        if (empty($res[0])) {
            return null;
        }

        $homeUnits = $res[0];

        $ramNum = $tribeUnits['ram'] ?? null;
        $catNum = $tribeUnits['catapult'] ?? null;
        $siegeUnits = array_values(array_filter([$ramNum, $catNum]));

        // Distinguish non-siege combat units (infantry & cavalry) vs siege units (rams & catapults)
        $nonSiegeRoles = ['basic', 'inf_def', 'inf_adv', 'cav_light', 'cav_def', 'cav_heavy'];
        $availableNonSiege = [];
        $totalNonSiegeAvailable = 0;

        foreach ($nonSiegeRoles as $role) {
            if (isset($tribeUnits[$role])) {
                $uNum = $tribeUnits[$role];
                $cnt = (int)($homeUnits['u' . $uNum] ?? 0);
                if ($cnt > 0) {
                    $availableNonSiege[$uNum] = $cnt;
                    $totalNonSiegeAvailable += $cnt;
                }
            }
        }

        $availableSiege = [];
        $totalSiegeAvailable = 0;
        foreach (['ram', 'catapult'] as $role) {
            if (isset($tribeUnits[$role])) {
                $uNum = $tribeUnits[$role];
                $cnt = (int)($homeUnits['u' . $uNum] ?? 0);
                if ($cnt > 0) {
                    $availableSiege[$uNum] = $cnt;
                    $totalSiegeAvailable += $cnt;
                }
            }
        }

        $totalCombatAvailable = $totalNonSiegeAvailable + $totalSiegeAvailable;
        $deployableCombat = max(0, $totalCombatAvailable - $homeReserve);
        if ($deployableCombat < $minAttackForce) {
            return null; // Garnisun pertahanan rumah wajib dipertahankan; sisa pasukan tempur belum cukup untuk menyerang
        }

        // Check active outgoing attacks (only count real attacks/raids; exclude defensive reinforcements)
        $resActive = $database->query_return("
            SELECT COUNT(m.moveid) as active_attacks
            FROM " . TB_PREFIX . "movement m
            LEFT JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
            WHERE m.`from` = $wid AND m.sort_type = 3 AND m.proc = 0
              AND (a.attack_type IS NULL OR a.attack_type IN (3, 4))
        ");
        $activeAttacks = (int)($resActive[0]['active_attacks'] ?? 0);
        if ($activeAttacks >= 3) {
            return null;
        }

        // Get bot village coordinates
        $botCoor = $database->getCoor($wid);
        if (!$botCoor) {
            return null;
        }

        $protectedList = defined('PROTECTED_PLAYERS') && strlen(PROTECTED_PLAYERS) > 0
            ? array_map('trim', explode(',', PROTECTED_PLAYERS))
            : [];
        $botAlliance = (int)($bot['alliance'] ?? 0);
        $revengeMaxDist = defined('BOT_AI_REVENGE_MAX_DISTANCE') ? (float)BOT_AI_REVENGE_MAX_DISTANCE : 250.0;

        // =========================================================================
        // PRIORITY 1: GLOBAL & ALLIANCE REVENGE (Balas Dendam Aktif & Laporan Tempur)
        // =========================================================================
        $revengeTarget = null;
        $isRevenge = false;

        if (!defined('BOT_AI_REVENGE_ENABLED') || BOT_AI_REVENGE_ENABLED) {
            $attackerWrefs = []; // [attacker_wref => last_incident_time]

            // Channel A: Serangan Musuh yang Sedang Aktif Berjalan Menuju Bot atau Sekutu
            // Pasukan musuh sedang di jalan, desa asalnya rentan diserang balik!
            $allyWrefs = [$wid];
            if ($botAlliance > 0) {
                $allyVids = $database->query_return("
                    SELECT v.wref
                    FROM " . TB_PREFIX . "vdata v
                    JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                    WHERE u.alliance = $botAlliance
                ");
                if (!empty($allyVids)) {
                    $allyWrefs = array_map('intval', array_column($allyVids, 'wref'));
                }
            }
            $allyWrefFilter = implode(',', array_unique($allyWrefs));

            $activeIncoming = $database->query_return("
                SELECT m.`from` as attacker_wref, m.starttime
                FROM " . TB_PREFIX . "movement m
                JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
                WHERE m.`to` IN ($allyWrefFilter)
                  AND m.sort_type = 3
                  AND m.proc = 0
                  AND a.attack_type IN (3, 4)
                  AND m.`from` != $wid
                ORDER BY m.starttime DESC
                LIMIT 15
            ");

            if (!empty($activeIncoming)) {
                foreach ($activeIncoming as $inc) {
                    $aw = (int)$inc['attacker_wref'];
                    if ($aw > 0 && !isset($attackerWrefs[$aw])) {
                        $attackerWrefs[$aw] = (int)$inc['starttime'];
                    }
                }
            }

            // Channel B: Laporan Tempur Terakhir (ndata) 24 jam terakhir
            $recentAttacks = [];
            if ($botAlliance > 0) {
                $recentAttacks = $database->query_return("
                    SELECT n.data, n.time
                    FROM " . TB_PREFIX . "ndata n
                    JOIN " . TB_PREFIX . "users u ON n.uid = u.id
                    WHERE (n.uid = $uid OR u.alliance = $botAlliance)
                      AND n.ntype IN (4, 5, 6, 7)
                      AND n.time >= " . (time() - 86400) . "
                    ORDER BY n.id DESC LIMIT 50
                ");
            } else {
                $recentAttacks = $database->query_return("
                    SELECT data, time
                    FROM " . TB_PREFIX . "ndata
                    WHERE uid = $uid
                      AND ntype IN (4, 5, 6, 7)
                      AND time >= " . (time() - 86400) . "
                    ORDER BY id DESC LIMIT 20
                ");
            }

            if (!empty($recentAttacks)) {
                foreach ($recentAttacks as $atkNotice) {
                    $parts = explode(',', $atkNotice['data']);
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $w = (int)$parts[1];
                        if ($w > 0) {
                            $t = (int)$atkNotice['time'];
                            if (!isset($attackerWrefs[$w]) || $t > $attackerWrefs[$w]) {
                                $attackerWrefs[$w] = $t;
                            }
                        }
                    }
                }
            }

            if (!empty($attackerWrefs)) {
                $wrefList = implode(',', array_keys($attackerWrefs));
                $targetVillages = $database->query_return("
                    SELECT v.wref, v.owner, v.name as vname, v.pop, w.x, w.y, u.username, u.access, u.protect, u.alliance
                    FROM " . TB_PREFIX . "vdata v
                    JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
                    JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                    WHERE v.wref IN ($wrefList)
                      AND v.wref != $wid
                      AND v.owner != $uid
                      AND u.access < 8
                      AND u.protect < " . time()
                );

                if (!empty($targetVillages)) {
                    $validRevengeTargets = [];
                    foreach ($targetVillages as $tgt) {
                        if (in_array($tgt['username'], $protectedList, true)) {
                            continue;
                        }
                        if ($botAlliance > 0 && !empty($tgt['alliance']) && (int)$tgt['alliance'] === $botAlliance) {
                            continue;
                        }

                        $w = (int)$tgt['wref'];
                        $lastAtkTime = (int)($attackerWrefs[$w] ?? 0);

                        // Cek apakah bot ini sudah meluncurkan serangan balasan setelah insiden ini terjadi
                        $alreadyRetaliated = $database->query_return("
                            SELECT moveid
                            FROM " . TB_PREFIX . "movement
                            WHERE `from` = $wid AND `to` = $w AND sort_type = 3 AND starttime >= $lastAtkTime
                            LIMIT 1
                        ");
                        if (!empty($alreadyRetaliated)) {
                            continue; // Sudah melancarkan balas dendam untuk insiden ini
                        }

                        // 1. Cek apakah ada gerakan serangan aktif yang sedang berjalan ke desa target dari bot ini
                        $ongoingToTarget = $database->query_return("
                            SELECT moveid
                            FROM " . TB_PREFIX . "movement
                            WHERE `from` = $wid AND `to` = $w AND sort_type = 3 AND proc = 0
                            LIMIT 1
                        ");
                        if (!empty($ongoingToTarget)) {
                            continue;
                        }

                        // 2. War Fatigue & Anti-Ping-Pong Cooldown:
                        // Cegah balas-membalas non-stop antara 2 desa dalam 2 jam (7200 detik)
                        $revengeCooldown = defined('BOT_AI_REVENGE_COOLDOWN') ? (int)BOT_AI_REVENGE_COOLDOWN : 7200;
                        $recentRetaliation = $database->query_return("
                            SELECT moveid
                            FROM " . TB_PREFIX . "movement
                            WHERE `from` = $wid AND `to` = $w AND sort_type = 3 AND starttime >= " . (time() - $revengeCooldown) . "
                            LIMIT 1
                        ");
                        if (!empty($recentRetaliation)) {
                            continue; // Masih dalam masa jeda / pendinginan perang
                        }

                        // 3. Batasi kroyokan bot sealiansi: maksimal 2 serangan bot sealiansi bersamaan ke 1 target
                        if ($botAlliance > 0) {
                            $allianceSwarm = $database->query_return("
                                SELECT COUNT(m.moveid) as ally_atks
                                FROM " . TB_PREFIX . "movement m
                                JOIN " . TB_PREFIX . "vdata v ON m.`from` = v.wref
                                JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                                WHERE u.alliance = $botAlliance AND m.`to` = $w AND m.sort_type = 3 AND m.proc = 0
                            ");
                            if ((int)($allianceSwarm[0]['ally_atks'] ?? 0) >= 2) {
                                continue;
                            }
                        }

                        $dist = self::getDistance($botCoor['x'], $botCoor['y'], $tgt['x'], $tgt['y']);
                        if ($dist < 0.5) {
                            continue;
                        }

                        // Jangkauan Balas Dendam (default 250 tile atau 0 untuk tanpa batas)
                        if ($revengeMaxDist > 0 && $dist > $revengeMaxDist) {
                            continue;
                        }

                        $tgt['dist'] = $dist;
                        $tgt['last_incident_time'] = $lastAtkTime;
                        $validRevengeTargets[] = $tgt;
                    }

                    if (!empty($validRevengeTargets)) {
                        // Urutkan: Serangan musuh paling baru diprioritaskan, jika sama prioritaskan terdekat
                        usort($validRevengeTargets, function($a, $b) {
                            if ($a['last_incident_time'] !== $b['last_incident_time']) {
                                return $b['last_incident_time'] <=> $a['last_incident_time'];
                            }
                            return $a['dist'] <=> $b['dist'];
                        });

                        $revengeTarget = $validRevengeTargets[0];
                        $isRevenge = true;
                    }
                }
            }
        }

        // =========================================================================
        // TARGET SELECTION & PEACEFUL HOUR CHECKS
        // =========================================================================
        $target = null;

        if ($revengeTarget !== null) {
            // REVENGE IS ACTIVE (100% pasti membalas dalam jam aktif 08:00 - 20:00):
            $target = $revengeTarget;
        } else {
            // REGULAR RANDOM RAID (FARMING):
            // Check active attacks limit for routine farming raids
            if ($activeAttacks >= $maxConcurrent) {
                return null;
            }

            // Check attack window (default 08:00 - 20:00 server time)
            if (self::isNightTime()) {
                return null; // Outside attack hours
            }

            // Probability roll for regular raids (default 35%)
            if (rand(1, 100) > $attackChance) {
                return null;
            }

            // Query candidate target villages within local area ($maxDistance)
            $targets = $database->query_return("
                SELECT v.wref, v.owner, v.name as vname, v.pop, w.x, w.y, u.username, u.access, u.protect, u.alliance
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
                if ($botAlliance > 0 && !empty($tgt['alliance']) && (int)$tgt['alliance'] === $botAlliance) {
                    continue;
                }

                $dist = self::getDistance($botCoor['x'], $botCoor['y'], $tgt['x'], $tgt['y']);
                if ($dist > $maxDistance || $dist < 0.5) {
                    continue;
                }

                $tw = (int)$tgt['wref'];

                // 1. Cek apakah bot ini sudah memiliki gerakan serangan aktif yang sedang berjalan ke desa target
                $ongoingToTarget = $database->query_return("
                    SELECT moveid
                    FROM " . TB_PREFIX . "movement
                    WHERE `from` = $wid AND `to` = $tw AND sort_type = 3 AND proc = 0
                    LIMIT 1
                ");
                if (!empty($ongoingToTarget)) {
                    continue;
                }

                // 2. Cooldown antar serangan dari bot ini ke desa yang sama (minimal 1 jam / 3600 detik)
                $recentAttack = $database->query_return("
                    SELECT moveid
                    FROM " . TB_PREFIX . "movement
                    WHERE `from` = $wid AND `to` = $tw AND sort_type = 3 AND starttime >= " . (time() - 3600) . "
                    LIMIT 1
                ");
                if (!empty($recentAttack)) {
                    continue;
                }

                if (defined('BOT_AI_GANG_ATTACK_ENABLED') && !BOT_AI_GANG_ATTACK_ENABLED) {
                    $incoming = $database->query_return("
                        SELECT COUNT(*) as total
                        FROM " . TB_PREFIX . "movement
                        WHERE `to` = " . $tw . " AND sort_type = 3 AND proc = 0
                    ");
                    if ((int)($incoming[0]['total'] ?? 0) >= 2) {
                        continue;
                    }
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
        }

        // =========================================================================
        // ATTACK TYPE & TROOP DISPATCH CALCULATION
        // =========================================================================
        // Calculate tentative target party size based on deployable surplus:
        if ($isRevenge) {
            // Retaliation strike: Send ~50% of deployable surplus (up to 150 troops)
            $targetPartySize = min(150, max($minAttackForce, (int)floor($deployableCombat * 0.50)));
        } elseif ($dynamicScaling) {
            // Dynamic scaling attack: ~35% of deployable surplus, up to 100 troops
            $targetPartySize = min(100, max($minAttackForce, (int)floor($deployableCombat * 0.35)));
        } else {
            // Legacy cap
            $targetPartySize = min(15, max($minAttackForce, (int)floor($deployableCombat * 0.40)));
        }
        $targetPartySize = min($targetPartySize, $deployableCombat);

        // Rule: Rams and Catapults are ONLY used in Normal Attacks (Type 3). In Raids (Type 4), siege weapons
        // are NEVER brought because they do zero structural damage in raids and severely slow down the march speed.
        // A bot only mounts a Normal Attack assault if it has siege weapons AND an overwhelming strike force (>= 80 troops).
        $hasSiege = ($totalSiegeAvailable > 0);
        $canSiegeAssault = ($hasSiege && $targetPartySize >= 80);

        if ($canSiegeAssault) {
            $attackType = 3; // Full assault with siege weapons (Normal Attack)
            $availableCombat = $availableNonSiege + $availableSiege;
            $totalCombatAvailable = $totalNonSiegeAvailable + $totalSiegeAvailable;
        } else {
            $attackType = 4; // Raid (Type 4) - strictly NO rams or catapults!
            $availableCombat = $availableNonSiege;
            $totalCombatAvailable = $totalNonSiegeAvailable;

            // Recalculate deployable non-siege troops
            $deployableNonSiege = max(0, $totalNonSiegeAvailable - $homeReserve);
            if ($deployableNonSiege < $minAttackForce) {
                return null; // Pasukan non-siege tidak cukup untuk melakukan raid tanpa melanggar kuota garnisun
            }
            $deployableCombat = $deployableNonSiege;
            $targetPartySize = min($targetPartySize, $deployableCombat);
        }
        $attackTypeName = ($attackType === 3) ? 'Normal Attack' : 'Raid';

        // Allocate quotas across available combat units strictly within deployableCombat
        $dispatched = [];
        $dispatchedSpeeds = [];
        $totalDispatched = 0;

        foreach ($availableCombat as $uNum => $avail) {
            $portion = (int)round(($avail / $totalCombatAvailable) * $targetPartySize);
            $remainingCap = $deployableCombat - $totalDispatched;
            $send = min($avail, max(0, $portion), $remainingCap);
            if ($send > 0) {
                $dispatched[$uNum] = $send;
                $dispatchedSpeeds[] = self::getUnitSpeed($uNum);
                $totalDispatched += $send;
            }
        }

        // Guarantee minimum troops while strictly respecting deployableCombat
        if ($totalDispatched < $minAttackForce && $totalDispatched < $deployableCombat) {
            $primaryUnit = key($availableCombat);
            if ($primaryUnit !== null) {
                $maxCanAdd = min(
                    $availableCombat[$primaryUnit] - ($dispatched[$primaryUnit] ?? 0),
                    $deployableCombat - $totalDispatched,
                    $minAttackForce - $totalDispatched
                );
                if ($maxCanAdd > 0) {
                    $dispatched[$primaryUnit] = ($dispatched[$primaryUnit] ?? 0) + $maxCanAdd;
                    $totalDispatched += $maxCanAdd;
                    if (!in_array(self::getUnitSpeed($primaryUnit), $dispatchedSpeeds, true)) {
                        $dispatchedSpeeds[] = self::getUnitSpeed($primaryUnit);
                    }
                }
            }
        }

        // Safety guarantee: If raid, purge any siege units that could have slipped in
        if ($attackType === 4 && !empty($dispatched)) {
            foreach ($siegeUnits as $su) {
                if (isset($dispatched[$su])) {
                    $totalDispatched -= $dispatched[$su];
                    unset($dispatched[$su]);
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

        // Hero participation: if bot hero is alive and stationed at home
        $sendHero = 0;
        if (!empty($homeUnits['hero']) && (int)$homeUnits['hero'] > 0) {
            $hCheck = $database->query_return("SELECT heroid, health, dead FROM " . TB_PREFIX . "hero WHERE uid = $uid AND dead = 0 LIMIT 1");
            if (!empty($hCheck[0]) && (int)$hCheck[0]['dead'] === 0 && (float)$hCheck[0]['health'] >= 25) {
                $sendHero = 1;
                $database->query("UPDATE " . TB_PREFIX . "units SET hero = hero - 1 WHERE vref = $wid AND hero > 0");
            }
        }

        $attackId = $database->addAttack(
            $wid,
            $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], $sendHero,
            $attackType,
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
        if ($sendHero) {
            $summaryParts[] = "Hero: 1";
        }
        $summaryStr = implode(', ', $summaryParts);

        $revengePrefix = $isRevenge ? "[BALAS DENDAM ALIANSI / REVENGE] " : "";
        return "{$revengePrefix}{$attackTypeName} dispatched! " . ($totalDispatched + $sendHero) . " troops [{$summaryStr}] marched towards {$target['vname']} ({$target['username']}) - distance: " . round($target['dist'], 1) . " tiles, arrival in {$duration}s";
    }

    /**
     * Dispatch an admin-commanded AI attack/raid against a specific player, village, or entire alliance.
     *
     * @param int|string $botUid 0 for closest bot, 'all' for all available bots, 'alliance:<id>' for all bots in alliance, or specific bot UID.
     * @param string|int $targetInput Target username, "x|y" coordinate, village wref, or 'alliance:<id>' / alliance tag/name.
     * @param int $attackType 4 = Raid, 3 = Normal Attack, 2 = Reinforcement (Bantuan Bertahan).
     * @param int $troopCount Minimum / custom troop count to dispatch (0 = auto 35-50 garrison).
     * @param string $composition 'balanced', 'cavalry', 'infantry', 'siege', or 'defense'.
     * @return array Result array with status, message, and details.
     */
    public static function orderAttack($botUid, $targetInput, $attackType = 4, $troopCount = 0, $composition = 'balanced') {
        global $database;
        if (!isset($database) || $database === null) {
            require_once __DIR__ . '/Database.php';
        }

        $targetInput = trim((string)$targetInput);
        if (empty($targetInput)) {
            return ['status' => 'error', 'message' => 'Target tidak boleh kosong (masukkan username, koordinat X|Y, atau pilih aliansi sasaran).'];
        }

        $attackType = in_array((int)$attackType, [2, 3, 4], true) ? (int)$attackType : 4;
        $attackTypeName = ($attackType === 2) ? 'Reinforcement' : (($attackType === 3) ? 'Normal Attack' : 'Raid');
        $validCompositions = ['balanced', 'cavalry', 'infantry', 'siege', 'defense'];
        $composition = in_array($composition, $validCompositions, true) ? $composition : 'balanced';

        // 1. Resolve Target (Single Village vs Alliance)
        $isAllianceTarget = false;
        $targetAlliance = null;
        $targetVillages = [];

        // Check if targetInput is 'alliance:<id>' or matches an existing alliance tag/name
        $targetAliId = 0;
        if (strpos($targetInput, 'alliance:') === 0) {
            $targetAliId = (int)substr($targetInput, 9);
        } else {
            $escTarget = $database->escape($targetInput);
            $aliCheck = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata WHERE tag = '$escTarget' OR name = '$escTarget' LIMIT 1");
            if (!empty($aliCheck[0]['id'])) {
                $targetAliId = (int)$aliCheck[0]['id'];
            }
        }

        if ($targetAliId > 0) {
            $aliData = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata WHERE id = $targetAliId LIMIT 1");
            if (!empty($aliData[0])) {
                $targetAlliance = $aliData[0];
                $isAllianceTarget = true;
                $tVils = $database->query_return("
                    SELECT v.wref, v.owner, v.name as vname, v.pop, w.x, w.y, u.username, u.access, u.protect, a.tag as ali_tag, a.name as ali_name
                    FROM " . TB_PREFIX . "vdata v
                    JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
                    JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                    LEFT JOIN " . TB_PREFIX . "alidata a ON u.alliance = a.id
                    WHERE u.alliance = $targetAliId
                    ORDER BY v.pop DESC
                ");
                if (!empty($tVils)) {
                    $targetVillages = $tVils;
                } else {
                    return ['status' => 'error', 'message' => "Aliansi target [{$targetAlliance['tag']}] {$targetAlliance['name']} tidak memiliki desa yang aktif."];
                }
            }
        }

        // If not alliance target, resolve single village
        if (!$isAllianceTarget) {
            $singleTarget = null;
            if (preg_match('/^(-?\d+)\s*\|\s*(-?\d+)$/', $targetInput, $matches)) {
                $tx = (int)$matches[1];
                $ty = (int)$matches[2];
                $wRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = $tx AND y = $ty LIMIT 1");
                $tWref = !empty($wRow[0]['id']) ? (int)$wRow[0]['id'] : 0;
                if ($tWref > 0) {
                    $singleTarget = $database->getMInfo($tWref);
                }
            } elseif (is_numeric($targetInput)) {
                $singleTarget = $database->getMInfo((int)$targetInput);
            } else {
                // Treat as username
                $tUser = $database->getUserField($database->escape($targetInput), 'id', 1);
                if ($tUser) {
                    $tVillages = $database->getProfileVillages($tUser);
                    if (!empty($tVillages)) {
                        $singleTarget = $database->getMInfo((int)$tVillages[0]['wref']);
                    }
                }
            }

            if (empty($singleTarget) || empty($singleTarget['wref']) || empty($singleTarget['owner'])) {
                return ['status' => 'error', 'message' => "Target '{$targetInput}' tidak ditemukan atau bukan desa yang dihuni."];
            }

            $targetOwner = (int)$singleTarget['owner'];
            $targetUser = $database->getUserField($targetOwner, 'username', 0);
            $targetVillages = [[
                'wref'     => (int)$singleTarget['wref'],
                'owner'    => $targetOwner,
                'vname'    => $singleTarget['name'] ?? 'Desa',
                'username' => $targetUser,
                'x'        => (int)$singleTarget['x'],
                'y'        => (int)$singleTarget['y'],
                'ali_tag'  => '',
                'ali_name' => '',
            ]];
        }

        // 2. Fetch Eligible Attacker Bots
        $sql = "SELECT u.id as uid, u.username, u.tribe, u.alliance, v.wref, v.name as vname, w.x, w.y,
                       a.tag as ali_tag, a.name as ali_name
                FROM " . TB_PREFIX . "users u
                JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
                JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
                LEFT JOIN " . TB_PREFIX . "alidata a ON u.alliance = a.id
                WHERE u.access = 2
                  AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz%' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')";

        $attackerAllianceInfo = null;
        if (strpos((string)$botUid, 'alliance:') === 0) {
            $aliId = (int)substr($botUid, 9);
            $sql .= " AND u.alliance = $aliId";
            $aRow = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata WHERE id = $aliId LIMIT 1");
            if (!empty($aRow[0])) {
                $attackerAllianceInfo = $aRow[0];
            }
        } elseif ($botUid !== 'all' && is_numeric($botUid) && (int)$botUid > 0) {
            $sql .= " AND u.id = " . (int)$botUid;
        }

        // Exclude bots that own any target village
        $targetWrefs = array_column($targetVillages, 'wref');
        $targetOwners = array_unique(array_column($targetVillages, 'owner'));

        $bots = $database->query_return($sql);
        if (empty($bots)) {
            $msg = ($attackerAllianceInfo !== null)
                ? "Tidak ada akun bot aktif yang tergabung dalam aliansi [{$attackerAllianceInfo['tag']}] {$attackerAllianceInfo['name']}."
                : "Tidak ada bot aktif yang tersedia untuk melakukan serangan.";
            return ['status' => 'error', 'message' => $msg];
        }

        // Filter out bots targeting their own villages
        $bots = array_values(array_filter($bots, function($b) use ($targetWrefs, $targetOwners) {
            return !in_array((int)$b['wref'], $targetWrefs, true) && !in_array((int)$b['uid'], $targetOwners, true);
        }));

        if (empty($bots)) {
            return ['status' => 'error', 'message' => 'Bot tidak dapat menyerang desanya sendiri atau target tidak valid.'];
        }

        // Prepare fair distribution tracking across all members and villages of the target alliance
        $playerAttackCounts = [];
        $villageAttackCounts = [];
        $targetVillagesByPlayer = [];
        if ($isAllianceTarget) {
            foreach ($targetVillages as $tv) {
                $pUid = (int)$tv['owner'];
                $vWref = (int)$tv['wref'];
                if (!isset($playerAttackCounts[$pUid])) {
                    $playerAttackCounts[$pUid] = 0;
                    $targetVillagesByPlayer[$pUid] = [];
                }
                $targetVillagesByPlayer[$pUid][] = $tv;
                $villageAttackCounts[$vWref] = 0;
            }
        }

        // Select bots to dispatch
        $selectedBots = [];
        if ($botUid === 'all' || strpos((string)$botUid, 'alliance:') === 0 || $isAllianceTarget) {
            $selectedBots = $bots;
        } elseif (is_numeric($botUid) && (int)$botUid > 0) {
            $selectedBots = $bots;
        } else {
            // Closest single bot to target village
            $firstTv = $targetVillages[0];
            foreach ($bots as &$b) {
                $b['dist'] = self::getDistance($b['x'], $b['y'], $firstTv['x'], $firstTv['y']);
            }
            unset($b);
            usort($bots, function($a, $b) { return $a['dist'] <=> $b['dist']; });
            $selectedBots = [ $bots[0] ];
        }

        $incSpeed = defined('INCREASE_SPEED') ? max(1, (int)INCREASE_SPEED) : 1;
        $dispatchedReports = [];

        foreach ($selectedBots as $botIndex => $bot) {
            $bWid = (int)$bot['wref'];
            $bUid = (int)$bot['uid'];
            $bTribe = (int)$bot['tribe'];
            $tribeUnits = self::getTribeUnits($bTribe);

            // Determine target village for this bot
            if ($isAllianceTarget && !empty($playerAttackCounts)) {
                // Fair round-robin / load-balanced distribution across all members (orang-orang) of the alliance
                // 1. Find the minimum attack count among all players in the target alliance
                $minPlayerAttacks = min($playerAttackCounts);

                // 2. Filter players who currently have received the least attacks
                $eligiblePlayerUids = array_keys(array_filter($playerAttackCounts, function($cnt) use ($minPlayerAttacks) {
                    return $cnt === $minPlayerAttacks;
                }));

                // 3. Among eligible players, pick the best target village for this bot
                // Prioritize rotating villages of that player, then closest distance
                $assignedTarget = null;
                $bestScore = 999999999;
                $chosenPlayerUid = null;

                foreach ($eligiblePlayerUids as $pUid) {
                    foreach ($targetVillagesByPlayer[$pUid] as $tv) {
                        $vWref = (int)$tv['wref'];
                        $vAttacks = $villageAttackCounts[$vWref] ?? 0;
                        $dist = self::getDistance($bot['x'], $bot['y'], $tv['x'], $tv['y']);

                        // Score: rotate among villages, then closest
                        $score = ($vAttacks * 10000) + $dist;
                        if ($score < $bestScore) {
                            $bestScore = $score;
                            $assignedTarget = $tv;
                            $assignedTarget['dist'] = $dist;
                            $chosenPlayerUid = $pUid;
                        }
                    }
                }

                // Fallback in case of any edge case
                if ($assignedTarget === null) {
                    $assignedTarget = $targetVillages[0];
                    $assignedTarget['dist'] = self::getDistance($bot['x'], $bot['y'], $assignedTarget['x'], $assignedTarget['y']);
                    $chosenPlayerUid = (int)$assignedTarget['owner'];
                }

                // Increment attack counts for both the chosen player and chosen village
                $playerAttackCounts[$chosenPlayerUid]++;
                $villageAttackCounts[(int)$assignedTarget['wref']] = ($villageAttackCounts[(int)$assignedTarget['wref']] ?? 0) + 1;
                $currentTarget = $assignedTarget;
            } else {
                $currentTarget = $targetVillages[0];
                $currentTarget['dist'] = self::getDistance($bot['x'], $bot['y'], $currentTarget['x'], $currentTarget['y']);
            }

            $targetWref = (int)$currentTarget['wref'];
            $targetUser = $currentTarget['username'];
            $targetVname = $currentTarget['vname'];
            $targetX = (int)$currentTarget['x'];
            $targetY = (int)$currentTarget['y'];
            $dist = (float)$currentTarget['dist'];

            // Fetch available units at home
            $unitCols = array_map(function($u) { return 'u' . $u; }, $tribeUnits['all']);
            $colsSql = implode(', ', $unitCols);
            $res = $database->query_return("SELECT {$colsSql}, hero FROM " . TB_PREFIX . "units WHERE vref = $bWid LIMIT 1");
            $homeUnits = !empty($res[0]) ? $res[0] : [];

            $baseUnit = ($bTribe - 1) * 10;
            $settlerU = $baseUnit + 10;
            $chiefU = $baseUnit + 9;
            $ramU = $tribeUnits['ram'] ?? null;
            $catU = $tribeUnits['catapult'] ?? null;
            $siegeUnits = array_values(array_filter([$ramU, $catU]));

            // Fetch available regular combat units at home (strictly only troops the bot actually owns, NO auto-provisioning)
            // Rules:
            // 1. Settlers (slot 10) never participate in attacks or raids.
            // 2. Chiefs (slot 9) only participate in Normal Attacks (Type 3) to conquer villages.
            // 3. Rams and Catapults ONLY participate in Normal Attacks (Type 3).
            //    In Raids (Type 4) or Reinforcements (Type 2), siege units are strictly excluded
            //    because they have no raid function and severely slow down movement speed.
            $availableUnits = [];
            $totalAvailable = 0;
            foreach ($tribeUnits['all'] as $uNum) {
                if ($uNum === $settlerU) {
                    continue;
                }
                if ($uNum === $chiefU && $attackType !== 3) {
                    continue;
                }
                if (in_array($uNum, $siegeUnits, true) && $attackType !== 3) {
                    continue;
                }

                $cur = (int)($homeUnits['u' . $uNum] ?? 0);
                if ($cur > 0) {
                    $availableUnits[$uNum] = $cur;
                    $totalAvailable += $cur;
                }
            }

            // Check hero availability
            $sendHero = 0;
            $hCheck = $database->query_return("SELECT u.hero, h.dead, h.health FROM " . TB_PREFIX . "units u LEFT JOIN " . TB_PREFIX . "hero h ON h.uid = $bUid WHERE u.vref = $bWid LIMIT 1");
            $heroAvailable = (!empty($hCheck[0]) && (int)($hCheck[0]['hero'] ?? 0) > 0 && (int)($hCheck[0]['dead'] ?? 0) === 0 && (float)($hCheck[0]['health'] ?? 0) >= 25);

            // If the bot has no troops at all and no ready hero, skip this bot
            if ($totalAvailable <= 0 && !$heroAvailable) {
                continue;
            }

            // Target party size capped strictly by total available units at home
            if ($troopCount > 0) {
                $targetSend = min((int)$troopCount, $totalAvailable);
            } else {
                // Default: send all troops if small garrison (<= 50), or up to 50
                $targetSend = min(50, $totalAvailable);
            }

            // If nothing to send and no hero, skip
            if ($targetSend <= 0 && !$heroAvailable) {
                continue;
            }

            $dispatched = [];
            if ($targetSend >= $totalAvailable) {
                $dispatched = $availableUnits;
            } elseif ($targetSend > 0) {
                // Select $targetSend units from $availableUnits based on requested composition
                $basicU   = $tribeUnits['basic'];
                $defU     = $tribeUnits['inf_def'] ?? $basicU;
                $cavDefU  = $tribeUnits['cav_def'] ?? null;
                $advU     = $tribeUnits['inf_adv'] ?? $basicU;
                $lightCav = $tribeUnits['cav_light'] ?? null;
                $heavyCav = $tribeUnits['cav_heavy'] ?? $lightCav;
                $ramU     = $tribeUnits['ram'] ?? null;
                $catU     = $tribeUnits['catapult'] ?? null;
                $scoutU   = $tribeUnits['scout'] ?? null;

                $weights = [];
                foreach ($availableUnits as $uNum => $avail) {
                    $w = 1.0;
                    $isScout = ($scoutU && $uNum === $scoutU);
                    $isCav   = in_array($uNum, array_filter([$lightCav, $heavyCav, $cavDefU]), true);
                    $isSiege = in_array($uNum, array_filter([$ramU, $catU]), true);
                    $isInf   = in_array($uNum, array_filter([$basicU, $defU, $advU]), true);
                    $isDef   = in_array($uNum, array_filter([$defU, $cavDefU, $basicU]), true);

                    if ($composition === 'siege' && $attackType !== 3) {
                        $w = $isCav ? 1.5 : ($isInf ? 1.0 : 0.8);
                    } elseif ($isScout) {
                        $w = 0.05; // Do not send scouts unless only scouts are available
                    } elseif ($composition === 'cavalry') {
                        $w = $isCav ? 5.0 : ($isInf ? 1.0 : 0.5);
                    } elseif ($composition === 'infantry') {
                        $w = $isInf ? 5.0 : ($isCav ? 1.0 : 0.5);
                    } elseif ($composition === 'siege') {
                        $w = $isSiege ? 5.0 : ($isInf ? 1.0 : 0.5);
                    } elseif ($composition === 'defense') {
                        $w = $isDef ? 5.0 : 1.0;
                    } else { // balanced
                        $w = $isCav ? 1.5 : ($isInf ? 1.0 : 0.8);
                    }

                    $weights[$uNum] = $w * $avail;
                }

                $sumWeights = array_sum($weights);
                $remaining = $targetSend;

                if ($sumWeights > 0) {
                    foreach ($availableUnits as $uNum => $avail) {
                        $portion = (int)floor(($weights[$uNum] / $sumWeights) * $targetSend);
                        $send = min($avail, $portion);
                        if ($send > 0) {
                            $dispatched[$uNum] = $send;
                            $remaining -= $send;
                        }
                    }
                }

                // If any remainder left due to floor rounding, fill from available units with remaining headroom
                if ($remaining > 0) {
                    $headroom = [];
                    foreach ($availableUnits as $uNum => $avail) {
                        $hr = $avail - ($dispatched[$uNum] ?? 0);
                        if ($hr > 0) {
                            $headroom[$uNum] = $hr;
                        }
                    }
                    arsort($headroom);
                    foreach ($headroom as $uNum => $hr) {
                        $take = min($hr, $remaining);
                        $dispatched[$uNum] = ($dispatched[$uNum] ?? 0) + $take;
                        $remaining -= $take;
                        if ($remaining <= 0) {
                            break;
                        }
                    }
                }
            }

            // Filter out any 0 amounts
            $dispatched = array_filter($dispatched, function($cnt) { return $cnt > 0; });

            // Strict enforcement: Rams and Catapults can ONLY march on Normal Attacks ($attackType === 3).
            // In Raids ($attackType === 4), purge any siege units that might have been included.
            if ($attackType !== 3 && !empty($dispatched)) {
                foreach ($siegeUnits as $su) {
                    if (isset($dispatched[$su])) {
                        unset($dispatched[$su]);
                    }
                }
            }

            if (empty($dispatched) && !$heroAvailable) {
                continue;
            }

            // Deduct dispatched units from bot village (mode 0 = subtract)
            if (!empty($dispatched)) {
                $database->modifyUnit($bWid, array_keys($dispatched), array_values($dispatched), array_fill(0, count($dispatched), 0));
            }

            // Hero participation
            if ($heroAvailable) {
                $sendHero = 1;
                $database->query("UPDATE " . TB_PREFIX . "units SET hero = hero - 1 WHERE vref = $bWid AND hero > 0");
            }

            // March speed
            $dispatchedSpeeds = [];
            foreach (array_keys($dispatched) as $un) {
                $dispatchedSpeeds[] = self::getUnitSpeed((int)$un);
            }
            $marchSpeed = !empty($dispatchedSpeeds) ? min($dispatchedSpeeds) : 6;
            $duration = max(30, (int)round(($dist / $marchSpeed) * 3600 / $incSpeed));

            // Map dispatched units to tribe slots t1..t10
            $t = array_fill(1, 10, 0);
            $baseUnit = ($bTribe - 1) * 10;
            $sumDispatched = 0;
            foreach ($dispatched as $uNum => $cnt) {
                $slot = $uNum - $baseUnit;
                if ($slot >= 1 && $slot <= 10) {
                    $t[$slot] = $cnt;
                    $sumDispatched += $cnt;
                }
            }

            $attackId = $database->addAttack(
                $bWid,
                $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10], $sendHero,
                $attackType,
                0, 0, 0
            );

            if (!$attackId) {
                // Rollback on failure
                if (!empty($dispatched)) {
                    $database->modifyUnit($bWid, array_keys($dispatched), array_values($dispatched), array_fill(0, count($dispatched), 1));
                }
                if ($sendHero) {
                    $database->query("UPDATE " . TB_PREFIX . "units SET hero = hero + 1 WHERE vref = $bWid");
                }
                continue;
            }

            $database->addMovement(
                3, // Attack/Raid
                $bWid,
                $targetWref,
                $attackId,
                time(),
                time() + $duration
            );

            $troopBreakdown = [];
            foreach ($dispatched as $uNum => $c) {
                $troopBreakdown[] = self::getUnitName($uNum) . ": " . number_format($c);
            }
            if ($sendHero) {
                $troopBreakdown[] = "Hero: 1";
            }

            $attackerAliPrefix = !empty($bot['ali_tag']) ? "[{$bot['ali_tag']}] " : "";
            $targetAliPrefix = !empty($currentTarget['ali_tag']) ? "[{$currentTarget['ali_tag']}] " : "";

            $attackVerb = ($attackType === 2) ? 'Bantuan Bertahan' : $attackTypeName;
            $dispatchedReports[] = "<b>{$attackerAliPrefix}{$bot['username']}</b> [{$bot['vname']}] mengerahkan " . ($sumDispatched + $sendHero) . " pasukan (" . implode(', ', $troopBreakdown) . ") via {$attackVerb} ke <b>{$targetAliPrefix}{$targetUser}</b> [{$targetVname} ({$targetX}|{$targetY})], tiba dalam " . round($duration / 60, 1) . " menit (Jarak: " . round($dist, 1) . " petak).";
        }

        if (empty($dispatchedReports)) {
            return [
                'status'  => 'error',
                'message' => 'Tidak ada pasukan yang dapat dikerahkan. Bot yang dipilih tidak memiliki pasukan atau hero yang siap dikirim di desanya.'
            ];
        }

        $summaryMsg = "";
        if ($attackType === 2) {
            $summaryMsg = "🛡️ <b>BANTUAN BERTAHAN BERHASIL DIKERAHKAN!</b> Sebanyak " . count($dispatchedReports) . " bot meluncurkan bala bantuan pasukan ke {$targetUser}!";
        } elseif ($isAllianceTarget && $attackerAllianceInfo !== null) {
            $summaryMsg = "⚔️ <b>PERANG ALIANSI PECAH!</b> Aliansi [{$attackerAllianceInfo['tag']}] {$attackerAllianceInfo['name']} mengerahkan " . count($dispatchedReports) . " bot untuk menyerang seluruh anggota Aliansi [{$targetAlliance['tag']}] {$targetAlliance['name']} secara merata!";
        } elseif ($isAllianceTarget) {
            $summaryMsg = "🎯 <b>SERANGAN MASSAL KE ALIANSI!</b> Sebanyak " . count($dispatchedReports) . " bot dikerahkan untuk menggempur seluruh anggota Aliansi [{$targetAlliance['tag']}] {$targetAlliance['name']} secara merata!";
        } else {
            $summaryMsg = "🎯 Berhasil memerintahkan " . count($dispatchedReports) . " bot untuk melancarkan serangan ke {$targetUser}!";
        }

        return [
            'status'   => 'success',
            'count'    => count($dispatchedReports),
            'reports'  => $dispatchedReports,
            'message'  => $summaryMsg
        ];
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
     * Calculate mandatory home garrison reserve that must never march out on attacks or distant reinforcements.
     * Keeps villages defended and prevents army extinction.
     *
     * @param int $pop Village population
     * @return int Minimum garrison troops to retain at home
     */
    public static function getHomeReserve(int $pop): int {
        $pop = max(10, $pop);
        // Scales naturally with population: minimum 15, maximum 60 (approx 20% of pop)
        return max(15, min(60, (int)round($pop * 0.20)));
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
     * @return array
     */
    public static function getTribeUnits(int $tribe): array {
        $base = ($tribe - 1) * 10;
        $all = range($base + 1, $base + 10);

        $map = [
            1  => [ // Roman
                'basic' => 1, 'inf_def' => 2, 'inf_adv' => 3, 'scout' => 4, 'cav_light' => 5, 'cav_heavy' => 6, 'ram' => 7, 'catapult' => 8
            ],
            2  => [ // Teuton
                'basic' => 11, 'inf_def' => 12, 'inf_adv' => 13, 'scout' => 14, 'cav_light' => 15, 'cav_heavy' => 16, 'ram' => 17, 'catapult' => 18
            ],
            3  => [ // Gaul
                'basic' => 21, 'inf_adv' => 22, 'scout' => 23, 'cav_light' => 24, 'cav_def' => 25, 'cav_heavy' => 26, 'ram' => 27, 'catapult' => 28
            ],
            6  => [ // Hun
                'basic' => 51, 'inf_adv' => 52, 'scout' => 53, 'cav_light' => 54, 'cav_heavy' => 56, 'ram' => 57, 'catapult' => 58
            ],
            7  => [ // Egyptian
                'basic' => 61, 'inf_def' => 62, 'inf_adv' => 63, 'scout' => 64, 'cav_light' => 65, 'cav_heavy' => 66, 'ram' => 67, 'catapult' => 68
            ],
            8  => [ // Spartan
                'basic' => 71, 'inf_def' => 72, 'inf_adv' => 73, 'scout' => 74, 'cav_light' => 75, 'cav_heavy' => 76, 'ram' => 77, 'catapult' => 78
            ],
            9  => [ // Viking
                'basic' => 81, 'scout' => 82, 'inf_def' => 83, 'inf_adv' => 84, 'cav_light' => 85, 'cav_heavy' => 86, 'ram' => 87, 'catapult' => 88
            ],
            10 => [ // Nusantara
                'basic' => 91, 'inf_def' => 92, 'inf_adv' => 93, 'scout' => 94, 'cav_light' => 95, 'cav_heavy' => 96, 'ram' => 97, 'catapult' => 98
            ],
        ];

        $info = $map[$tribe] ?? [
            'basic'     => $base + 1,
            'inf_def'   => $base + 2,
            'inf_adv'   => $base + 3,
            'scout'     => $base + 4,
            'cav_light' => $base + 5,
            'cav_heavy' => $base + 6,
            'ram'       => $base + 7,
            'catapult'  => $base + 8,
        ];

        $info['all'] = $all;
        return $info;
    }

    /**
     * Get human-readable unit name.
     */
    public static function getUnitName(int $unitNum): string {
        $cName = 'U' . $unitNum;
        if (defined($cName)) {
            return (string)constant($cName);
        }
        return "Unit " . $unitNum;
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

        if (class_exists('Building') && method_exists('Building', 'recountCP') && !empty($database->dblink) && $database->dblink instanceof \mysqli) {
            Building::recountCP($database, $vid);
        }
        $database->query("UPDATE " . TB_PREFIX . "vdata SET pop = $popTot WHERE wref = $vid");

        return $popTot;
    }

    /**
     * Compute population contribution of a building.
     */
    public static function buildingPOP(int $type, int $lvl): int {
        $varName = 'bid' . $type;
        $data = $GLOBALS[$varName] ?? null;
        if (!is_array($data)) {
            global $$varName;
            $data = $$varName ?? null;
        }
        if (!is_array($data) && file_exists(__DIR__ . '/Data/buidata.php')) {
            require __DIR__ . '/Data/buidata.php';
            $data = $GLOBALS[$varName] ?? ($$varName ?? null);
        }

        $popTot = 0;
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
