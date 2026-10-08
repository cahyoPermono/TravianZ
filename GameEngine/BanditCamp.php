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
            bounty_wood int(11) NOT NULL DEFAULT 2500,
            bounty_clay int(11) NOT NULL DEFAULT 2500,
            bounty_iron int(11) NOT NULL DEFAULT 2500,
            bounty_crop int(11) NOT NULL DEFAULT 2500,
            reward_exp int(11) NOT NULL DEFAULT 200,
            reward_cp int(11) NOT NULL DEFAULT 50,
            reward_silver int(11) NOT NULL DEFAULT 5,
            status tinyint(1) NOT NULL DEFAULT 1,
            cleared_by int(11) DEFAULT 0,
            cleared_time int(11) DEFAULT 0,
            created int(11) NOT NULL,
            PRIMARY KEY (id),
            KEY (wref),
            KEY (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $q);
    }

    /**
     * Get all currently active bandit camps.
     */
    public static function getActiveCamps($fromX = null, $fromY = null): array {
        global $database;

        self::ensureTable();

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

        // Camp Tier Profiles (Balanced for Travian 1x Server)
        $profiles = [
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
                'bounty' => rand(300, 600),
                'exp' => 150,
                'cp' => 30,
                'silver' => 5,
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
                'bounty' => rand(1200, 2000),
                'exp' => 450,
                'cp' => 80,
                'silver' => 12,
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
                    34 => rand(100, 180), // Srigala
                    35 => rand(120, 200), // Beruang
                    36 => rand(60, 100),  // Buaya
                    37 => rand(80, 140),  // Harimau
                    38 => rand(30, 60),   // Gajah Liar
                    42 => rand(80, 150),  // Desertir Natar
                ],
                'bounty' => rand(3500, 6000),
                'exp' => 1200,
                'cp' => 250,
                'silver' => 30,
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
                    35 => rand(400, 700),   // Beruang
                    36 => rand(300, 500),   // Buaya
                    37 => rand(500, 900),   // Harimau
                    38 => rand(300, 600),   // Gajah Purba
                    42 => rand(400, 700),   // Natar Swordsman
                    43 => rand(300, 500),   // Natar Guard
                    46 => rand(150, 300),   // Natar Heavy Cav
                ],
                'bounty' => rand(12000, 20000),
                'exp' => 3500,
                'cp' => 600,
                'silver' => 75,
            ],
        ];

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
            $campId = (int)$camp['id'];

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
                $attackerSql = "
                    SELECT m.from as from_wref, v.owner as conqueror_uid, u.username as conqueror_name
                    FROM " . TB_PREFIX . "movement m
                    JOIN " . TB_PREFIX . "vdata v ON m.from = v.wref
                    JOIN " . TB_PREFIX . "users u ON v.owner = u.id
                    WHERE m.to = $wref AND m.sort_type IN (3, 4) AND m.proc = 1
                    ORDER BY m.endtime DESC
                    LIMIT 1
                ";
                $lastAttacker = $database->query_return($attackerSql);

                $conquerorUid = !empty($lastAttacker[0]['conqueror_uid']) ? (int)$lastAttacker[0]['conqueror_uid'] : 0;
                $conquerorName = !empty($lastAttacker[0]['conqueror_name']) ? $lastAttacker[0]['conqueror_name'] : 'Prajurit Sekutu';

                $expBonus = (int)$camp['reward_exp'];
                $cpBonus = (int)$camp['reward_cp'];
                $silverBonus = (int)$camp['reward_silver'];
                $time = time();

                // 1. Award rewards to conqueror
                if ($conquerorUid > 0) {
                    // Hero EXP & Silver
                    $database->query("
                        UPDATE " . TB_PREFIX . "hero
                        SET experience = experience + $expBonus, silver = silver + $silverBonus
                        WHERE uid = $conquerorUid
                    ");

                    // CP on users table
                    $database->query("
                        UPDATE " . TB_PREFIX . "users
                        SET cp = cp + $cpBonus
                        WHERE id = $conquerorUid
                    ");

                    // Notice / In-game report
                    $tierLabel = self::getTierLabel((int)$camp['tier']);
                    $topic = "🏆 [KEMENANGAN] {$camp['name']} Berhasil Dihancurkan!";
                    $noticeData = "Selamat! Pasukanmu berhasil menumpas seluruh penjaga di {$camp['name']} ({$tierLabel}). " .
                                  "Hadiah Kemenangan: +{$expBonus} Hero EXP, +{$cpBonus} CP, +{$silverBonus} Silver.";

                    $database->addNotice($conquerorUid, $wref, 0, 1, $topic, $noticeData, $time);
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

                $cleared[] = [
                    'id' => $campId,
                    'name' => $camp['name'],
                    'conqueror' => $conquerorName,
                    'exp' => $expBonus,
                    'cp' => $cpBonus,
                    'silver' => $silverBonus
                ];
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
