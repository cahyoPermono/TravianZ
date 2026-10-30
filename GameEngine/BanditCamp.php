<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BanditCamp.php                                            ##
##  Type           : PvE Bandit Camps & World Boss Engine for TravianZ        ##
##  Purpose        : Automated spawning, guarding, looting, and boss events   ##
##                   for PvE combat with rich hero EXP, Silver & CP rewards    ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class BanditCamp {

    const TIER_OUTPOST   = 1; // Sarang Penyamun Kroco (Easy)
    const TIER_HIDEOUT   = 2; // Markas Begal Hutan (Medium)
    const TIER_FORTRESS  = 3; // Benteng Gembong Begal (Hard)
    const TIER_WORLDBOSS = 4; // World Boss (Epic Raid Boss)

    /**
     * Main execution cycle for Bandit Camps.
     * Can be invoked from Automation.php or standalone CLI.
     */
    public static function run($force = false) {
        if (defined('BANDIT_CAMPS_ENABLED') && !BANDIT_CAMPS_ENABLED) {
            return ['status' => 'disabled', 'message' => 'Bandit Camps disabled in config.php'];
        }

        $interval = defined('BANDIT_CAMPS_INTERVAL') ? (int)BANDIT_CAMPS_INTERVAL : 180;
        $lockFile = __DIR__ . '/Prevention/bandit_camps.txt';

        if (!$force && file_exists($lockFile)) {
            $lastRun = filemtime($lockFile);
            if ((time() - $lastRun) < $interval) {
                return [
                    'status' => 'throttled',
                    'message' => 'Next check in ' . ($interval - (time() - $lastRun)) . 's'
                ];
            }
        }

        @touch($lockFile);

        self::ensureTable();
        self::upgradeExistingActiveCamps();
        $banditUser = self::getBanditUser();

        // 1. Check if any camps have been defeated/cleared
        $cleared = self::checkCleared();

        // 2. Maintain target camp counts on map
        $spawned = self::maintainCamps();

        return [
            'status' => 'success',
            'cleared' => $cleared,
            'spawned' => $spawned,
            'active_count' => count(self::getActiveCamps())
        ];
    }

    /**
     * Get or create the dedicated Bandit system user account.
     */
    public static function getBanditUser(): array {
        global $database;

        $sql = "SELECT id, username, tribe, access FROM " . TB_PREFIX . "users WHERE username = 'Gembong Bandit' LIMIT 1";
        $res = $database->query_return($sql);

        if (!empty($res[0])) {
            return $res[0];
        }

        $time = time();
        $q = "INSERT INTO " . TB_PREFIX . "users 
              (username, password, email, tribe, access, gold, act, timestamp, regtime, protect) 
              VALUES ('Gembong Bandit', '" . md5(uniqid()) . "', 'bandit@system.travianz', 4, 2, 0, '', $time, $time, 0)";
        mysqli_query($database->dblink, $q);
        $uid = mysqli_insert_id($database->dblink);

        return [
            'id' => $uid,
            'username' => 'Gembong Bandit',
            'tribe' => 4,
            'access' => 2
        ];
    }

    /**
     * Ensure database table exists.
     */
    public static function ensureTable() {
        global $database;
        $q = "CREATE TABLE IF NOT EXISTS " . TB_PREFIX . "bandit_camps (
            id int(11) NOT NULL AUTO_INCREMENT,
            wref int(11) NOT NULL,
            tier tinyint(2) NOT NULL DEFAULT 1,
            name varchar(64) NOT NULL,
            max_hp int(11) NOT NULL DEFAULT 100,
            cur_hp int(11) NOT NULL DEFAULT 100,
            bounty_wood int(11) NOT NULL DEFAULT 5000,
            bounty_clay int(11) NOT NULL DEFAULT 5000,
            bounty_iron int(11) NOT NULL DEFAULT 5000,
            bounty_crop int(11) NOT NULL DEFAULT 5000,
            reward_exp int(11) NOT NULL DEFAULT 600,
            reward_cp int(11) NOT NULL DEFAULT 100,
            reward_silver int(11) NOT NULL DEFAULT 60,
            status tinyint(1) NOT NULL DEFAULT 1,
            cleared_by int(11) DEFAULT 0,
            cleared_time int(11) DEFAULT 0,
            created int(11) NOT NULL,
            PRIMARY KEY (id),
            KEY (wref),
            KEY (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $q);

        $qAttacks = "CREATE TABLE IF NOT EXISTS " . TB_PREFIX . "bandit_camp_attacks (
            id int(11) NOT NULL AUTO_INCREMENT,
            camp_id int(11) NOT NULL,
            uid int(11) NOT NULL,
            damage_dealt int(11) NOT NULL DEFAULT 0,
            timestamp int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY camp_uid (camp_id, uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $qAttacks);
    }

    /**
     * Log attack damage contribution against a camp.
     */
    public static function logAttackContribution(int $campId, int $uid, int $damageDealt = 0): void {
        global $database;
        $campId = (int)$campId;
        $uid = (int)$uid;
        $damageDealt = max(1, (int)$damageDealt);
        $time = time();

        if ($campId <= 0 || $uid <= 0) {
            return;
        }

        self::ensureTable();
        $q = "INSERT INTO " . TB_PREFIX . "bandit_camp_attacks (camp_id, uid, damage_dealt, timestamp)
              VALUES ($campId, $uid, $damageDealt, $time)";
        mysqli_query($database->dblink, $q);
    }

    /**
     * Get all contributors, damage stats, percentage, and estimated spoils for a bandit camp.
     * Especially used for World Boss (Tier 4) multi-player raid bounty distribution.
     */
    public static function getCampContributors(int $campId): array {
        global $database;

        self::ensureTable();
        $campId = (int)$campId;
        if ($campId <= 0) {
            return ['camp_id' => 0, 'total_damage' => 0, 'contributors' => []];
        }

        $camps = $database->query_return("SELECT * FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId LIMIT 1");
        if (empty($camps[0])) {
            return ['camp_id' => $campId, 'total_damage' => 0, 'contributors' => []];
        }
        $camp = $camps[0];
        $wref = (int)$camp['wref'];
        $silverBonus = (int)$camp['reward_silver'];
        $cpBonus = (int)$camp['reward_cp'];
        $expBonus = (int)$camp['reward_exp'];

        // Get resource pool: current remaining in village, or bounty columns as baseline
        $remWood = (int)$camp['bounty_wood'];
        $remClay = (int)$camp['bounty_clay'];
        $remIron = (int)$camp['bounty_iron'];
        $remCrop = (int)$camp['bounty_crop'];

        if ($wref > 0) {
            $remRes = $database->query_return("SELECT wood, clay, iron, crop FROM " . TB_PREFIX . "vdata WHERE wref = $wref LIMIT 1");
            if (!empty($remRes[0])) {
                $vWood = (int)round($remRes[0]['wood']);
                $vClay = (int)round($remRes[0]['clay']);
                $vIron = (int)round($remRes[0]['iron']);
                $vCrop = (int)round($remRes[0]['crop']);
                if ($vWood > 0 || $vClay > 0 || $vIron > 0 || $vCrop > 0) {
                    $remWood = $vWood;
                    $remClay = $vClay;
                    $remIron = $vIron;
                    $remCrop = $vCrop;
                }
            }
        }

        $contribSql = "SELECT a.uid, u.username, SUM(a.damage_dealt) as total_dmg 
                       FROM " . TB_PREFIX . "bandit_camp_attacks a
                       LEFT JOIN " . TB_PREFIX . "users u ON a.uid = u.id
                       WHERE a.camp_id = $campId 
                       GROUP BY a.uid, u.username
                       ORDER BY total_dmg DESC";
        $rows = $database->query_return($contribSql);

        $totalDamage = 0;
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $totalDamage += (int)$r['total_dmg'];
            }
        }

        $contributors = [];
        $rank = 1;
        if (!empty($rows) && $totalDamage > 0) {
            foreach ($rows as $r) {
                $dmg = (int)$r['total_dmg'];
                $ratio = $dmg / $totalDamage;
                $pct = round($ratio * 100, 2);

                $estSilver = max(1, (int)floor($ratio * $silverBonus));
                $estCp = max(1, (int)floor($ratio * $cpBonus));
                $estExp = max(1, (int)floor($ratio * $expBonus));
                $estWood = (int)floor($ratio * $remWood);
                $estClay = (int)floor($ratio * $remClay);
                $estIron = (int)floor($ratio * $remIron);
                $estCrop = (int)floor($ratio * $remCrop);
                $estTotalRes = $estWood + $estClay + $estIron + $estCrop;

                $contributors[] = [
                    'rank' => $rank++,
                    'uid' => (int)$r['uid'],
                    'username' => !empty($r['username']) ? $r['username'] : ('Pemain #' . $r['uid']),
                    'damage' => $dmg,
                    'percentage' => $pct,
                    'est_silver' => $estSilver,
                    'est_cp' => $estCp,
                    'est_exp' => $estExp,
                    'est_wood' => $estWood,
                    'est_clay' => $estClay,
                    'est_iron' => $estIron,
                    'est_crop' => $estCrop,
                    'est_total_res' => $estTotalRes
                ];
            }
        }

        return [
            'camp_id' => $campId,
            'camp_name' => $camp['name'],
            'tier' => (int)$camp['tier'],
            'total_damage' => $totalDamage,
            'pool_silver' => $silverBonus,
            'pool_cp' => $cpBonus,
            'pool_exp' => $expBonus,
            'pool_wood' => $remWood,
            'pool_clay' => $remClay,
            'pool_iron' => $remIron,
            'pool_crop' => $remCrop,
            'pool_total_res' => ($remWood + $remClay + $remIron + $remCrop),
            'contributors' => $contributors
        ];
    }

    /**
     * Retrofit existing active camps to the new generous rewards and bounty scale.
     */
    public static function upgradeExistingActiveCamps(): void {
        global $database;
        if (!isset($database->dblink) || !$database->dblink) return;

        // Upgraded baselines per tier
        $minScale = [
            self::TIER_OUTPOST   => ['bounty' => 2500,  'exp' => 600,   'cp' => 100,  'silver' => 60],
            self::TIER_HIDEOUT   => ['bounty' => 10000, 'exp' => 2200,  'cp' => 350,  'silver' => 250],
            self::TIER_FORTRESS  => ['bounty' => 32000, 'exp' => 7500,  'cp' => 900,  'silver' => 950],
            self::TIER_WORLDBOSS => ['bounty' => 100000,'exp' => 28000, 'cp' => 2800, 'silver' => 4000],
        ];

        $active = $database->query_return("SELECT id, wref, tier, bounty_wood, reward_exp, reward_silver FROM " . TB_PREFIX . "bandit_camps WHERE status = 1");
        if (empty($active)) return;

        foreach ($active as $c) {
            $t = (int)$c['tier'];
            $req = $minScale[$t] ?? $minScale[self::TIER_OUTPOST];
            $campId = (int)$c['id'];
            $wref = (int)$c['wref'];

            // Check if this camp has outdated rewards
            if ((int)$c['reward_exp'] < $req['exp'] || (int)$c['reward_silver'] < $req['silver'] || (int)$c['bounty_wood'] < $req['bounty']) {
                $bounty = $req['bounty'];
                $storage = max(40000, (int)round($bounty * 1.5));
                $exp = $req['exp'];
                $cp = $req['cp'];
                $silver = $req['silver'];

                // Update camp table
                $database->query("
                    UPDATE " . TB_PREFIX . "bandit_camps
                    SET bounty_wood = $bounty, bounty_clay = $bounty, bounty_iron = $bounty, bounty_crop = $bounty,
                        reward_exp = $exp, reward_cp = $cp, reward_silver = $silver
                    WHERE id = $campId
                ");

                // Update village storage and resources
                $database->query("
                    UPDATE " . TB_PREFIX . "vdata
                    SET wood = GREATEST(wood, $bounty), clay = GREATEST(clay, $bounty),
                        iron = GREATEST(iron, $bounty), crop = GREATEST(crop, $bounty),
                        maxstore = GREATEST(maxstore, $storage), maxcrop = GREATEST(maxcrop, $storage)
                    WHERE wref = $wref
                ");
            }

            // Retrofit active camps that have alien troops outside Nature (e.g. u41..u100)
            $u = $database->getUnit($wref, false);
            if (!empty($u)) {
                $hasAlien = false;
                $transferredHarimau = 0;
                $transferredGajah = 0;
                for ($al = 41; $al <= 100; $al++) {
                    if (!empty($u['u' . $al]) && (int)$u['u' . $al] > 0) {
                        $hasAlien = true;
                        $amt = (int)$u['u' . $al];
                        if ($al == 42) {
                            $transferredHarimau += $amt;
                        } else {
                            $transferredGajah += $amt;
                        }
                    }
                }
                if ($hasAlien) {
                    $setAlienZero = [];
                    for ($al = 41; $al <= 100; $al++) {
                        $setAlienZero[] = "u{$al} = 0";
                    }
                    $alienSql = implode(', ', $setAlienZero);
                    $database->query("
                        UPDATE " . TB_PREFIX . "units
                        SET u39 = u39 + $transferredHarimau,
                            u40 = u40 + $transferredGajah,
                            {$alienSql}
                        WHERE vref = $wref
                    ");
                }
            }
        }
    }

    /**
     * Get all currently active bandit camps.
     */
    public static function getActiveCamps($fromX = null, $fromY = null): array {
        global $database;

        self::ensureTable();
        self::upgradeExistingActiveCamps();

        $sql = "SELECT b.*, w.x, w.y, v.pop, v.wood, v.clay, v.iron, v.crop
                FROM " . TB_PREFIX . "bandit_camps b
                JOIN " . TB_PREFIX . "wdata w ON b.wref = w.id
                JOIN " . TB_PREFIX . "vdata v ON b.wref = v.wref
                WHERE b.status = 1
                ORDER BY b.tier ASC, b.id ASC";

        $camps = $database->query_return($sql);

        if ($fromX !== null && $fromY !== null) {
            foreach ($camps as &$c) {
                $c['dist'] = self::getDistance($fromX, $fromY, $c['x'], $c['y']);
            }
            unset($c);
            usort($camps, function($a, $b) {
                return $a['dist'] <=> $b['dist'];
            });
        }

        return $camps;
    }

    /**
     * Maintain target active camp count and ensure World Boss is active.
     */
    public static function maintainCamps(): array {
        $targetCount = defined('BANDIT_CAMPS_TARGET_COUNT') ? (int)BANDIT_CAMPS_TARGET_COUNT : 6;
        $enableBoss = defined('BANDIT_CAMPS_WORLD_BOSS_ENABLED') ? (bool)BANDIT_CAMPS_WORLD_BOSS_ENABLED : true;

        $active = self::getActiveCamps();
        $hasBoss = false;
        $tierCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($active as $c) {
            $t = (int)$c['tier'];
            $tierCounts[$t] = ($tierCounts[$t] ?? 0) + 1;
            if ($t === self::TIER_WORLDBOSS) {
                $hasBoss = true;
            }
        }

        $spawned = [];

        // 1. Ensure 1 World Boss is active
        if ($enableBoss && !$hasBoss) {
            $boss = self::spawnCamp(self::TIER_WORLDBOSS);
            if ($boss) {
                $spawned[] = $boss;
            }
        }

        // 2. Maintain regular camps (balanced mix of Tier 1, 2, and 3)
        $currentRegular = count($active) - ($hasBoss ? 1 : 0);
        $needed = $targetCount - $currentRegular;

        if ($needed > 0) {
            for ($i = 0; $i < $needed; $i++) {
                // Tier distribution: 50% Tier 1, 35% Tier 2, 15% Tier 3
                $rand = rand(1, 100);
                if ($rand <= 50) {
                    $tier = self::TIER_OUTPOST;
                } elseif ($rand <= 85) {
                    $tier = self::TIER_HIDEOUT;
                } else {
                    $tier = self::TIER_FORTRESS;
                }

                $res = self::spawnCamp($tier);
                if ($res) {
                    $spawned[] = $res;
                }
            }
        }

        return $spawned;
    }

    /**
     * Get profiles configuration for all Bandit Camp tiers.
     */
    public static function getTierProfiles(): array {
        return [
            self::TIER_OUTPOST => [
                'names' => [
                    'Sarang Penyamun Rimba',
                    'Pos Begal Kali Brantas',
                    'Perkemahan Perampok Alas',
                    'Gua Penyamun Lereng',
                    'Gubuk Pembajak Hutan'
                ],
                'pop' => 25,
                'troops' => [
                    31 => rand(25, 45), // Tikus
                    32 => rand(15, 25), // Babi Hutan
                    34 => rand(20, 35), // Srigala
                ],
                'bounty' => rand(2000, 3500),
                'exp' => 600,
                'cp' => 100,
                'silver' => 60,
            ],
            self::TIER_HIDEOUT => [
                'names' => [
                    'Markas Begal Hutan Roban',
                    'Pondok Perompak Selat',
                    'Gua Penyamun Curug Cikaso',
                    'Benteng Begal Cadas Pangeran',
                    'Kubu Perampok Alas Donoloyo'
                ],
                'pop' => 65,
                'troops' => [
                    32 => rand(30, 50),  // Babi Hutan
                    34 => rand(60, 100), // Srigala
                    35 => rand(30, 60),  // Beruang
                    36 => rand(15, 30),  // Buaya
                    37 => rand(15, 25),  // Harimau
                ],
                'bounty' => rand(8000, 14000),
                'exp' => 2200,
                'cp' => 350,
                'silver' => 250,
            ],
            self::TIER_FORTRESS => [
                'names' => [
                    'Benteng Gembong Alas Purwo',
                    'Sarang Begal Gunung Raung',
                    'Kubu Perompak Karang Bolong',
                    'Markas Panglima Pemberontak',
                    'Kandang Begal Hutan Larangan'
                ],
                'pop' => 125,
                'troops' => [
                    34 => rand(100, 180), // Kelelawar
                    35 => rand(120, 200), // Babi Hutan
                    36 => rand(60, 100),  // Srigala
                    37 => rand(80, 140),  // Beruang
                    38 => rand(30, 60),   // Buaya
                    39 => rand(60, 120),  // Harimau
                    40 => rand(25, 50),   // Gajah Liar
                ],
                'bounty' => rand(28000, 45000),
                'exp' => 7500,
                'cp' => 900,
                'silver' => 950,
            ],
            self::TIER_WORLDBOSS => [
                'names' => [
                    '[WORLD BOSS] Naga Siluman Rawa Pening',
                    '[WORLD BOSS] Raja Gajah Purba Mpu Bharada',
                    '[WORLD BOSS] Panglima Siluman Alas Ketangga',
                    '[WORLD BOSS] Raja Siluman Sanghyang Kenambang'
                ],
                'pop' => 280,
                'troops' => [
                    35 => rand(400, 700),   // Babi Hutan
                    36 => rand(300, 500),   // Srigala
                    37 => rand(500, 900),   // Beruang
                    38 => rand(300, 600),   // Buaya
                    39 => rand(450, 800),   // Harimau Siluman
                    40 => rand(250, 500),   // Gajah Purba Raksasa
                ],
                'bounty' => rand(90000, 150000),
                'exp' => 28000,
                'cp' => 2800,
                'silver' => 4000,
            ],
        ];
    }

    /**
     * Spawn a specific tier Bandit Camp.
     */
    public static function spawnCamp(int $tier): ?array {
        global $database;

        self::ensureTable();
        $banditUser = self::getBanditUser();
        $banditUid = (int)$banditUser['id'];

        // Pick location near an active player village
        $tile = self::findFreeTileNearPlayers();
        if (!$tile) {
            return null;
        }

        $wref = (int)$tile['id'];
        $fieldtype = (int)$tile['fieldtype'];

        $profiles = self::getTierProfiles();
        $p = $profiles[$tier] ?? $profiles[self::TIER_OUTPOST];
        $campName = $p['names'][array_rand($p['names'])];
        $pop = (int)$p['pop'];
        $bounty = (int)$p['bounty'];
        $storage = max(40000, (int)round($bounty * 1.5));

        // 1. Create village entry in vdata
        $database->addVillage($wref, $banditUid, 'Gembong Bandit', 0, $pop, $campName);

        // 2. Resource fields & units table setup
        $database->addResourceFields($wref, $fieldtype);
        $database->addUnits($wref);
        $database->addTech($wref);
        $database->addABTech($wref);

        // 3. Mark tile as occupied on map
        $database->setFieldTaken($wref);

        // 4. Fill guarding troops in units table
        $setUnits = [];
        $totalGuards = 0;
        foreach ($p['troops'] as $uNum => $amt) {
            $setUnits[] = "u{$uNum} = {$amt}";
            $totalGuards += $amt;
        }
        $setSql = implode(', ', $setUnits);
        $database->query("UPDATE " . TB_PREFIX . "units SET {$setSql} WHERE vref = $wref");

        // 5. Fill resource vault in vdata
        $time = time();
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = $bounty, clay = $bounty, iron = $bounty, crop = $bounty,
                maxstore = $storage, maxcrop = $storage, lastupdate = $time
            WHERE wref = $wref
        ");

        // 6. Record in bandit_camps table
        $nameEsc = mysqli_real_escape_string($database->dblink, $campName);
        $exp = (int)$p['exp'];
        $cp = (int)$p['cp'];
        $silver = (int)$p['silver'];

        $database->query("
            INSERT INTO " . TB_PREFIX . "bandit_camps
            (wref, tier, name, max_hp, cur_hp, bounty_wood, bounty_clay, bounty_iron, bounty_crop, reward_exp, reward_cp, reward_silver, status, created)
            VALUES
            ($wref, $tier, '$nameEsc', $totalGuards, $totalGuards, $bounty, $bounty, $bounty, $bounty, $exp, $cp, $silver, 1, $time)
        ");

        $campId = mysqli_insert_id($database->dblink);

        return [
            'id' => $campId,
            'wref' => $wref,
            'name' => $campName,
            'tier' => $tier,
            'coords' => "({$tile['x']}|{$tile['y']})",
            'bounty' => $bounty,
            'guards' => $totalGuards
        ];
    }

    /**
     * Find an unoccupied wilderness tile near active players.
     */
    private static function findFreeTileNearPlayers(): ?array {
        global $database;

        $banditUser = self::getBanditUser();
        $banditUid = (int)$banditUser['id'];

        // Find active player centers
        $sql = "SELECT w.x, w.y, v.wref
                FROM " . TB_PREFIX . "vdata v
                JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
                JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                WHERE u.access < 8 AND u.id != $banditUid
                ORDER BY v.pop DESC
                LIMIT 15";

        $centers = $database->query_return($sql);

        if (!empty($centers)) {
            $origin = $centers[array_rand($centers)];
            $ox = (int)$origin['x'];
            $oy = (int)$origin['y'];
            $radius = rand(4, 25);

            $tileSql = "SELECT id, x, y, fieldtype
                        FROM " . TB_PREFIX . "wdata
                        WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0
                          AND x BETWEEN " . ($ox - $radius) . " AND " . ($ox + $radius) . "
                          AND y BETWEEN " . ($oy - $radius) . " AND " . ($oy + $radius) . "
                        LIMIT 30";
            $freeTiles = $database->query_return($tileSql);

            if (!empty($freeTiles)) {
                return $freeTiles[array_rand($freeTiles)];
            }
        }

        // Global fallback: any unoccupied wilderness tile
        $fallbackSql = "SELECT id, x, y, fieldtype
                        FROM " . TB_PREFIX . "wdata
                        WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0
                        LIMIT 50";
        $fallbackTiles = $database->query_return($fallbackSql);

        if (!empty($fallbackTiles)) {
            return $fallbackTiles[array_rand($fallbackTiles)];
        }

        return null;
    }

    /**
     * Called when a battle against a potential Bandit Camp finishes.
     * Evaluates remaining defenders synchronously and immediately awards
     * rewards and clears the camp tile if defeated.
     */
    public static function onBattleCompleted(int $targetWref, int $attackerUid, int $attackerWref = 0, int $damageDealt = 0) {
        global $database;

        self::ensureTable();

        $camps = $database->query_return("
            SELECT b.*, w.x, w.y
            FROM " . TB_PREFIX . "bandit_camps b
            JOIN " . TB_PREFIX . "wdata w ON b.wref = w.id
            WHERE b.wref = $targetWref AND b.status = 1
            LIMIT 1
        ");

        if (empty($camps[0])) {
            return null;
        }

        $camp = $camps[0];

        // Log damage contribution for this attacker
        if ($damageDealt > 0 && $attackerUid > 0) {
            self::logAttackContribution((int)$camp['id'], $attackerUid, $damageDealt);
        }

        // Check if all defending units in the camp are defeated
        $unitRow = $database->getUnit($targetWref, false);
        $totalRemaining = 0;
        if (!empty($unitRow)) {
            for ($i = 1; $i <= 100; $i++) {
                $totalRemaining += (int)($unitRow['u' . $i] ?? 0);
            }
        }

        if ($totalRemaining === 0) {
            $userRes = $database->query_return("SELECT username FROM " . TB_PREFIX . "users WHERE id = $attackerUid LIMIT 1");
            $conquerorName = !empty($userRes[0]['username']) ? $userRes[0]['username'] : 'Prajurit Sekutu';
            return self::rewardAndClearCamp($camp, $attackerUid, $conquerorName, $attackerWref);
        }

        return null;
    }

    /**
     * Reward the conqueror, notify, and remove the camp from the world map.
     */
    public static function rewardAndClearCamp(array $camp, int $conquerorUid, string $conquerorName = '', int $attackerWref = 0): array {
        global $database;

        $wref = (int)$camp['wref'];
        $campId = (int)$camp['id'];
        $expBonus = (int)$camp['reward_exp'];
        $cpBonus = (int)$camp['reward_cp'];
        $silverBonus = (int)$camp['reward_silver'];
        $time = time();

        // 0. Fetch remaining unlooted resources before camp deletion
        $remRes = $database->query_return("SELECT wood, clay, iron, crop FROM " . TB_PREFIX . "vdata WHERE wref = $wref LIMIT 1");
        $remWood = max(0, (int)round($remRes[0]['wood'] ?? 0));
        $remClay = max(0, (int)round($remRes[0]['clay'] ?? 0));
        $remIron = max(0, (int)round($remRes[0]['iron'] ?? 0));
        $remCrop = max(0, (int)round($remRes[0]['crop'] ?? 0));

        // Check World Boss shared contributors
        $isWorldBoss = ((int)$camp['tier'] === self::TIER_WORLDBOSS);
        $contributorsData = self::getCampContributors($campId);
        $contributors = $contributorsData['contributors'];
        $totalBossDamage = (int)$contributorsData['total_damage'];

        // If World Boss has logged contributors, share ALL spoils proportionally:
        // Silver, Culture Points (CP), Hero EXP, and Resources
        $isSharedBossReward = ($isWorldBoss && count($contributors) > 0 && $totalBossDamage > 0);

        if ($isSharedBossReward) {
            if (!class_exists('BlackMarket')) {
                require_once __DIR__ . '/BlackMarket.php';
            }

            foreach ($contributors as $c) {
                $cUid = (int)$c['uid'];
                $cDamage = (int)$c['damage'];
                $pct = $c['percentage'];
                $shareSilver = (int)$c['est_silver'];
                $shareCp = (int)$c['est_cp'];
                $shareExp = (int)$c['est_exp'];
                $shareWood = (int)$c['est_wood'];
                $shareClay = (int)$c['est_clay'];
                $shareIron = (int)$c['est_iron'];
                $shareCrop = (int)$c['est_crop'];
                $shareTotalRes = (int)$c['est_total_res'];

                // 1. Add Silver to Hero
                BlackMarket::addSilver($cUid, $shareSilver);

                // 2. Add CP to user
                $database->query("
                    UPDATE " . TB_PREFIX . "users
                    SET cp = IFNULL(cp, 0) + $shareCp
                    WHERE id = $cUid
                ");

                // 3. Add Hero EXP
                $database->query("
                    UPDATE " . TB_PREFIX . "hero
                    SET experience = IFNULL(experience, 0) + $shareExp
                    WHERE uid = $cUid
                ");

                // 4. Deliver resources to contributor's village
                $targetVil = ($cUid === $conquerorUid && $attackerWref > 0)
                    ? $attackerWref
                    : (int)$database->getVillageID($cUid);

                if ($targetVil > 0 && ($shareWood > 0 || $shareClay > 0 || $shareIron > 0 || $shareCrop > 0)) {
                    $database->query("
                        UPDATE " . TB_PREFIX . "vdata
                        SET wood = wood + $shareWood,
                            clay = clay + $shareClay,
                            iron = iron + $shareIron,
                            crop = crop + $shareCrop
                        WHERE wref = $targetVil
                    ");
                }

                // 5. Send detailed in-game victory report
                $bossTopic = "👑 [HADIAH WORLD BOSS] {$camp['name']} Telah Tumbang!";
                $bossMsg = "Kabar gembira! Monster penguasa rimba {$camp['name']} berhasil ditaklukkan oleh aliansi pejuang!\n\n" .
                           "📊 Catatan Kontribusi Anda:\n" .
                           "• Total Kerusakan / Damage Anda : " . number_format($cDamage) . " unit\n" .
                           "• Persentase Kontribusi          : {$pct}% (dari total " . number_format($totalBossDamage) . " damage)\n\n" .
                           "🎁 Hadiah Bagi Hasil yang Anda Dapatkan:\n" .
                           "🪙 Koin Silver        : +" . number_format($shareSilver) . "\n" .
                           "🏛️ Culture Points (CP) : +" . number_format($shareCp) . "\n" .
                           "⭐ Hero EXP            : +" . number_format($shareExp) . "\n" .
                           "📦 Sumber Daya (ke Desa): +" . number_format($shareTotalRes) . " Total\n" .
                           "   (🌲 Kayu: +" . number_format($shareWood) . " | 🧱 Liat: +" . number_format($shareClay) . " | ⛏️ Besi: +" . number_format($shareIron) . " | 🌾 Gandum: +" . number_format($shareCrop) . ")";

                $database->addNotice($cUid, $wref, 0, 1, $bossTopic, $bossMsg, $time);
            }
        } else {
            // 1. Award rewards to single conqueror
            if ($conquerorUid > 0) {
                // Hero EXP & Silver
                $database->query("
                    UPDATE " . TB_PREFIX . "hero
                    SET experience = IFNULL(experience, 0) + $expBonus, silver = IFNULL(silver, 0) + $silverBonus
                    WHERE uid = $conquerorUid
                ");

                // CP on users table
                $database->query("
                    UPDATE " . TB_PREFIX . "users
                    SET cp = IFNULL(cp, 0) + $cpBonus
                    WHERE id = $conquerorUid
                ");

                // Transfer all remaining camp spoils into conqueror village
                $targetVil = $attackerWref > 0 ? $attackerWref : (int)$database->getVillageID($conquerorUid);
                if ($targetVil > 0 && ($remWood > 0 || $remClay > 0 || $remIron > 0 || $remCrop > 0)) {
                    $database->query("
                        UPDATE " . TB_PREFIX . "vdata
                        SET wood = wood + $remWood,
                            clay = clay + $remClay,
                            iron = iron + $remIron,
                            crop = crop + $remCrop
                        WHERE wref = $targetVil
                    ");
                }

                // Notice / In-game report
                $tierLabel = self::getTierLabel((int)$camp['tier']);
                $topic = "🏆 [KEMENANGAN] {$camp['name']} Berhasil Dihancurkan!";
                $bonusSpoils = "";
                if ($remWood > 0 || $remClay > 0 || $remIron > 0 || $remCrop > 0) {
                    $bonusSpoils = " Sisa rampasan gudang bandit (+" . number_format($remWood) . " Kayu, +" . number_format($remClay) . " Liat, +" . number_format($remIron) . " Besi, +" . number_format($remCrop) . " Gandum) telah diangkut ke desamu!";
                }
                $noticeData = "Selamat! Pasukanmu berhasil menumpas seluruh penjaga di {$camp['name']} ({$tierLabel}). " .
                              "Hadiah Kemenangan: +{$expBonus} Hero EXP, +{$cpBonus} CP, +{$silverBonus} Silver." . $bonusSpoils;

                $database->addNotice($conquerorUid, $wref, 0, 1, $topic, $noticeData, $time);
            }
        }

        // 2. Cleanup village data and free map tile
        $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $wref");
        $database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $wref");
        $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $wref");
        $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id = $wref");

        // 3. Mark camp as cleared in database
        $database->query("
            UPDATE " . TB_PREFIX . "bandit_camps
            SET status = 0, cur_hp = 0, cleared_by = $conquerorUid, cleared_time = $time
            WHERE id = $campId
        ");

        return [
            'id' => $campId,
            'name' => $camp['name'],
            'conqueror' => $conquerorName,
            'exp' => $expBonus,
            'cp' => $cpBonus,
            'silver' => $silverBonus
        ];
    }

    /**
     * Check active camps for zero remaining troops (cleared).
     * Awards Hero EXP, CP, Silver, removes camp tile, and logs victory.
     */
    public static function checkCleared(): array {
        global $database;

        self::ensureTable();

        $activeCamps = $database->query_return("
            SELECT b.*, w.x, w.y
            FROM " . TB_PREFIX . "bandit_camps b
            JOIN " . TB_PREFIX . "wdata w ON b.wref = w.id
            WHERE b.status = 1
        ");

        if (empty($activeCamps)) {
            return [];
        }

        $cleared = [];

        foreach ($activeCamps as $camp) {
            $wref = (int)$camp['wref'];

            // Sum remaining troops in camp units (bypass cache to get fresh count)
            $unitRow = $database->getUnit($wref, false);
            $totalRemaining = 0;
            if (!empty($unitRow)) {
                for ($i = 1; $i <= 100; $i++) {
                    $totalRemaining += (int)($unitRow['u' . $i] ?? 0);
                }
            }

            if ($totalRemaining === 0) {
                // Find victorious attacker who wiped out the camp
                // Check both returning (sort_type = 4, m.from = wref) and incoming (sort_type = 3, m.to = wref)
                $attackerSql = "
                    SELECT v.owner as conqueror_uid, u.username as conqueror_name
                    FROM " . TB_PREFIX . "movement m
                    JOIN " . TB_PREFIX . "vdata v ON (CASE WHEN m.sort_type = 4 THEN m.to = v.wref ELSE m.from = v.wref END)
                    JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                    WHERE ((m.to = $wref AND m.sort_type = 3) OR (m.from = $wref AND m.sort_type = 4))
                    ORDER BY m.endtime DESC
                    LIMIT 1
                ";
                $lastAttacker = $database->query_return($attackerSql);

                $conquerorUid = !empty($lastAttacker[0]['conqueror_uid']) ? (int)$lastAttacker[0]['conqueror_uid'] : 0;
                $conquerorName = !empty($lastAttacker[0]['conqueror_name']) ? $lastAttacker[0]['conqueror_name'] : '';

                // Secondary fallback: check recent combat reports in ndata if movement records already expired
                if ($conquerorUid === 0) {
                    $noticeSql = "
                        SELECT n.uid as conqueror_uid, u.username as conqueror_name
                        FROM " . TB_PREFIX . "ndata n
                        JOIN " . TB_PREFIX . "users u ON n.uid = u.id
                        WHERE n.toWref = $wref AND n.ntype IN (1, 2)
                        ORDER BY n.time DESC
                        LIMIT 1
                    ";
                    $lastNotice = $database->query_return($noticeSql);
                    if (!empty($lastNotice[0]['conqueror_uid'])) {
                        $conquerorUid = (int)$lastNotice[0]['conqueror_uid'];
                        $conquerorName = $lastNotice[0]['conqueror_name'] ?? '';
                    }
                }

                if (empty($conquerorName)) {
                    $conquerorName = 'Prajurit Sekutu';
                }

                $cleared[] = self::rewardAndClearCamp($camp, $conquerorUid, $conquerorName);
            }
        }

        return $cleared;
    }

    /**
     * Get label for camp tier.
     */
    public static function getTierLabel(int $tier): string {
        switch ($tier) {
            case self::TIER_OUTPOST:   return 'Sarang Kroco (Tier 1)';
            case self::TIER_HIDEOUT:   return 'Markas Begal (Tier 2)';
            case self::TIER_FORTRESS:  return 'Benteng Gembong (Tier 3)';
            case self::TIER_WORLDBOSS: return 'WORLD BOSS (Tier 4)';
            default: return 'Sarang Bandit';
        }
    }

    /**
     * Get star rating string for tier.
     */
    public static function getTierStars(int $tier): string {
        switch ($tier) {
            case self::TIER_OUTPOST:   return '★☆☆☆';
            case self::TIER_HIDEOUT:   return '★★☆☆';
            case self::TIER_FORTRESS:  return '★★★☆';
            case self::TIER_WORLDBOSS: return '★★★★ [BOSS]';
            default: return '★☆☆☆';
        }
    }

    /**
     * Distance calculation with map wrapping.
     */
    public static function getDistance($x1, $y1, $x2, $y2): float {
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
}
