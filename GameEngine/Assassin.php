<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Assassin.php                                              ##
##  Type           : Assassin Syndicate / Brotherhood Engine for TravianZ      ##
##  Purpose        : Secret order located at hidden world map sanctuary tile.  ##
##                   Offers 3 clandestine high-cost contracts:                 ##
##                   1. Shadow Raid (100% anonymous stealth plunder)           ##
##                   2. Hero Assassination (lethal poisoning of target hero)   ##
##                   3. Night Sabotage (covert demolition of building level)   ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Assassin {

    const CONTRACT_SHADOW_RAID      = 'shadow_raid';
    const CONTRACT_HERO_ASSASSINATE = 'hero_assassinate';
    const CONTRACT_NIGHT_SABOTAGE   = 'night_sabotage';

    const STATUS_IN_PROGRESS = 0;
    const STATUS_COMPLETED   = 1;
    const STATUS_FAILED      = 2;

    const SPEED = 40; // Base speed: 40 fields per hour

    const PRICES = [
        self::CONTRACT_SHADOW_RAID => [
            'silver' => 300,
            'gold'   => 15,
            'title'  => 'Serbuan Bayangan (Shadow Raid)',
            'desc'   => 'Serbu & jarah desa target dengan 250 Assassin tanpa membuka identitas pengirim. Hasil jarahan dikirim ke desa Anda.',
            'icon'   => '⚔️'
        ],
        self::CONTRACT_HERO_ASSASSINATE => [
            'silver' => 600,
            'gold'   => 30,
            'title'  => 'Eksekusi Hero (Hero Assassination)',
            'desc'   => 'Racun dan bunuh Hero musuh seketika saat terlelap. Identitas penyewa dirahasiakan sepenuhnya.',
            'icon'   => '☠️'
        ],
        self::CONTRACT_NIGHT_SABOTAGE => [
            'silver' => 500,
            'gold'   => 25,
            'title'  => 'Sabotase Malam (Night Sabotage)',
            'desc'   => 'Susup dan turunkan 1 level gedung di desa musuh tanpa meninggalkan jejak.',
            'icon'   => '🔥'
        ],
    ];

    /**
     * Database connection helper.
     */
    protected static function db() {
        global $database;
        if (isset($database->dblink) && $database->dblink) {
            return $database->dblink;
        }
        static $db = null;
        if ($db === null) {
            $db = @mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASS, SQL_DB, SQL_PORT);
        }
        return $db;
    }

    /**
     * Ensure database tables exist.
     */
    public static function ensureTables(): void {
        $db = self::db();
        if (!$db) return;

        $q1 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "assassin_sanctuary` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `wref` int(11) NOT NULL,
            `x` int(11) NOT NULL,
            `y` int(11) NOT NULL,
            `name` varchar(64) NOT NULL DEFAULT 'Kuil Bayangan [Sanctuary]',
            `status` tinyint(2) NOT NULL DEFAULT 1,
            `created_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `wref` (`wref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        @mysqli_query($db, $q1);

        $q2 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "assassin_contracts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `client_uid` int(11) NOT NULL,
            `client_wid` int(11) NOT NULL,
            `target_wid` int(11) NOT NULL,
            `target_uid` int(11) NOT NULL,
            `target_name` varchar(64) NOT NULL DEFAULT '',
            `target_x` int(11) NOT NULL DEFAULT 0,
            `target_y` int(11) NOT NULL DEFAULT 0,
            `contract_type` varchar(32) NOT NULL,
            `cost_currency` varchar(16) NOT NULL,
            `cost_amount` int(11) NOT NULL,
            `duration` int(11) NOT NULL,
            `start_time` int(11) NOT NULL,
            `end_time` int(11) NOT NULL,
            `status` tinyint(2) NOT NULL DEFAULT 0,
            `result_summary` text NULL,
            `created_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `client_uid` (`client_uid`),
            KEY `target_wid` (`target_wid`),
            KEY `status` (`status`),
            KEY `end_time` (`end_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        @mysqli_query($db, $q2);
    }

    /**
     * Get or create dedicated Assassin system user.
     */
    public static function getAssassinUser(): array {
        global $database;
        $db = self::db();

        $sql = "SELECT id, username, tribe, access FROM " . TB_PREFIX . "users WHERE username = 'Klan Assassin' LIMIT 1";
        $res = $database->query_return($sql);

        if (!empty($res[0])) {
            return $res[0];
        }

        $time = time();
        $q = "INSERT INTO " . TB_PREFIX . "users 
              (username, password, email, tribe, access, gold, act, timestamp, regtime, protect) 
              VALUES ('Klan Assassin', '" . md5(uniqid('assassin_', true)) . "', 'assassin@syndicate.travianz', 4, 2, 0, '', $time, $time, 0)";
        mysqli_query($db, $q);
        $uid = mysqli_insert_id($db);

        return [
            'id' => $uid,
            'username' => 'Klan Assassin',
            'tribe' => 4,
            'access' => 2
        ];
    }

    /**
     * Get active sanctuary data from database.
     */
    public static function getSanctuary(): ?array {
        global $database;
        self::ensureTables();

        $sql = "SELECT s.*, w.fieldtype, w.image
                FROM " . TB_PREFIX . "assassin_sanctuary s
                JOIN " . TB_PREFIX . "wdata w ON s.wref = w.id
                WHERE s.status = 1
                LIMIT 1";
        $res = $database->query_return($sql);

        if (!empty($res[0])) {
            return $res[0];
        }

        return self::ensureSanctuary();
    }

    /**
     * Ensure the secret sanctuary exists on the map.
     */
    public static function ensureSanctuary(): array {
        global $database;
        self::ensureTables();

        $existing = $database->query_return("
            SELECT s.*, w.fieldtype, w.image
            FROM " . TB_PREFIX . "assassin_sanctuary s
            JOIN " . TB_PREFIX . "wdata w ON s.wref = w.id
            WHERE s.status = 1
            LIMIT 1
        ");
        if (!empty($existing[0])) {
            return $existing[0];
        }

        $user = self::getAssassinUser();
        $uid = (int)$user['id'];

        // Find a secluded wilderness tile (e.g. distance 20-50 from origin)
        $tileSql = "SELECT id, x, y, fieldtype
                    FROM " . TB_PREFIX . "wdata
                    WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0
                      AND SQRT(POW(x, 2) + POW(y, 2)) BETWEEN 15 AND 45
                    LIMIT 30";
        $freeTiles = $database->query_return($tileSql);

        if (empty($freeTiles)) {
            // Fallback to any free wilderness tile
            $fallbackSql = "SELECT id, x, y, fieldtype
                            FROM " . TB_PREFIX . "wdata
                            WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0
                            LIMIT 30";
            $freeTiles = $database->query_return($fallbackSql);
        }

        if (empty($freeTiles)) {
            throw new \RuntimeException("No free wilderness tiles found on map to place Assassin Sanctuary.");
        }

        $tile = $freeTiles[array_rand($freeTiles)];
        $wref = (int)$tile['id'];
        $x = (int)$tile['x'];
        $y = (int)$tile['y'];
        $fieldtype = (int)$tile['fieldtype'];
        $time = time();
        $sanctuaryName = 'Kuil Bayangan [Sanctuary]';

        // 1. Create village entry in vdata
        $database->addVillage($wref, $uid, 'Klan Assassin', 0, 120, $sanctuaryName);

        // 2. Resource fields & units table setup
        $database->addResourceFields($wref, $fieldtype);
        $database->addUnits($wref);

        // 3. Mark tile as occupied on map
        $database->setFieldTaken($wref);

        // 4. Station 500 elite shadow guards in sanctuary
        $database->query("UPDATE " . TB_PREFIX . "units SET u41 = 500, u46 = 200 WHERE vref = $wref");

        // 5. Fill resource vault
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = 50000, clay = 50000, iron = 50000, crop = 50000,
                maxstore = 80000, maxcrop = 80000, lastupdate = $time
            WHERE wref = $wref
        ");

        // 6. Record in assassin_sanctuary table
        $db = self::db();
        $nameEsc = mysqli_real_escape_string($db, $sanctuaryName);
        $database->query("
            INSERT INTO " . TB_PREFIX . "assassin_sanctuary
            (wref, x, y, name, status, created_at)
            VALUES
            ($wref, $x, $y, '$nameEsc', 1, $time)
        ");

        $id = mysqli_insert_id($db);

        return [
            'id' => $id,
            'wref' => $wref,
            'x' => $x,
            'y' => $y,
            'name' => $sanctuaryName,
            'status' => 1,
            'created_at' => $time,
            'fieldtype' => $fieldtype
        ];
    }

    /**
     * Check if a given map wref is the Assassin Sanctuary.
     */
    public static function isSanctuary(int $wref): bool {
        static $sanctuaryWref = null;
        if ($sanctuaryWref === null) {
            $s = self::getSanctuary();
            $sanctuaryWref = !empty($s['wref']) ? (int)$s['wref'] : 0;
        }
        return ($wref > 0 && $wref === $sanctuaryWref);
    }

    /**
     * Calculate toroidal map distance.
     */
    public static function getDistance(float $x1, float $y1, float $x2, float $y2): float {
        $worldMax = defined('WORLD_MAX') ? (int)WORLD_MAX : 400;
        $mapWidth = $worldMax * 2 + 1;

        $dx = abs($x1 - $x2);
        if ($dx > $worldMax) {
            $dx = $mapWidth - $dx;
        }

        $dy = abs($y1 - $y2);
        if ($dy > $worldMax) {
            $dy = $mapWidth - $dy;
        }

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Compute approximate direction / rumor hint towards the sanctuary from a village.
     */
    public static function getRumorDirection(int $viewerWid): string {
        global $database;
        $s = self::getSanctuary();
        if (!$s) return "di suatu wilayah berkabut";

        $viewerCoors = $database->getCoor($viewerWid);
        if (!$viewerCoors) return "di koordinat ({$s['x']}|{$s['y']})";

        $vx = (int)$viewerCoors['x'];
        $vy = (int)$viewerCoors['y'];
        $sx = (int)$s['x'];
        $sy = (int)$s['y'];

        $dist = round(self::getDistance($vx, $vy, $sx, $sy));

        $dirY = ($sy >= $vy) ? 'Utara' : 'Selatan';
        $dirX = ($sx >= $vx) ? 'Timur' : 'Barat';
        $quadrant = ($dirY === 'Utara' && $dirX === 'Timur') ? 'Timur Laut'
                  : (($dirY === 'Utara' && $dirX === 'Barat') ? 'Barat Laut'
                  : (($dirY === 'Selatan' && $dirX === 'Timur') ? 'Tenggara' : 'Barat Daya'));

        return "arah {$quadrant} (sekitar ~{$dist} petak dari desa Anda)";
    }

    /**
     * Calculate contract duration in seconds based on distance.
     */
    public static function calculateDuration(int $sanctuaryWref, int $targetWref): int {
        global $database;
        $sCoors = $database->getCoor($sanctuaryWref);
        $tCoors = $database->getCoor($targetWref);

        if (!$sCoors || !$tCoors) {
            return 300;
        }

        $dist = self::getDistance((float)$sCoors['x'], (float)$sCoors['y'], (float)$tCoors['x'], (float)$tCoors['y']);
        $speedMult = (defined('SPEED') && SPEED > 0) ? (float)SPEED : 1;
        
        // Base travel speed = 40 fields/hr
        $durationSeconds = round(($dist / (self::SPEED * $speedMult)) * 3600);
        return max(180, (int)$durationSeconds); // Minimum 3 minutes for suspense
    }

    /**
     * Get active ongoing contracts for a user.
     */
    public static function getActiveContracts(int $uid): array {
        global $database;
        self::ensureTables();
        $uid = (int)$uid;
        $sql = "SELECT c.*, w.x as tw_x, w.y as tw_y
                FROM " . TB_PREFIX . "assassin_contracts c
                LEFT JOIN " . TB_PREFIX . "wdata w ON c.target_wid = w.id
                WHERE c.client_uid = $uid AND c.status = " . self::STATUS_IN_PROGRESS . "
                ORDER BY c.end_time ASC";
        return $database->query_return($sql) ?: [];
    }

    /**
     * Get contract history for a user.
     */
    public static function getContractHistory(int $uid, int $limit = 6): array {
        global $database;
        self::ensureTables();
        $uid = (int)$uid;
        $limit = max(1, min(20, (int)$limit));
        $sql = "SELECT c.*, w.x as tw_x, w.y as tw_y
                FROM " . TB_PREFIX . "assassin_contracts c
                LEFT JOIN " . TB_PREFIX . "wdata w ON c.target_wid = w.id
                WHERE c.client_uid = $uid AND c.status IN (" . self::STATUS_COMPLETED . ", " . self::STATUS_FAILED . ")
                ORDER BY c.end_time DESC
                LIMIT $limit";
        return $database->query_return($sql) ?: [];
    }

    /**
     * Hire an assassin contract.
     */
    public static function hireContract(int $uid, int $clientWid, $targetInput, string $contractType, string $currency): array {
        global $database, $generator;
        self::ensureTables();
        $db = self::db();

        $uid = (int)$uid;
        $clientWid = (int)$clientWid;
        $contractType = trim($contractType);
        $currency = strtolower(trim($currency));

        // 1. Validate contract type
        if (!isset(self::PRICES[$contractType])) {
            return ['status' => 'error', 'message' => 'Pilihan jenis kontrak assassin tidak valid.'];
        }

        // 2. Validate currency
        if ($currency !== 'silver' && $currency !== 'gold') {
            return ['status' => 'error', 'message' => 'Mata uang pembayaran harus berupa Silver atau Gold.'];
        }

        $cost = (int)self::PRICES[$contractType][$currency];

        // 3. Resolve Target Village
        $targetWid = 0;
        $targetX = 0;
        $targetY = 0;
        $targetName = '';
        $targetUid = 0;

        $targetInputStr = trim((string)$targetInput);

        if (preg_match('/(-?\d+)\s*[\|,]\s*(-?\d+)/', $targetInputStr, $m)) {
            $tx = (int)$m[1];
            $ty = (int)$m[2];
            $targetWid = $generator->getBaseID($tx, $ty);
        } elseif (is_numeric($targetInputStr)) {
            $targetWid = (int)$targetInputStr;
        } else {
            $nameEsc = mysqli_real_escape_string($db, $targetInputStr);
            $vRes = $database->query_return("SELECT wref, owner, name FROM " . TB_PREFIX . "vdata WHERE name = '$nameEsc' LIMIT 1");
            if (!empty($vRes[0])) {
                $targetWid = (int)$vRes[0]['wref'];
            }
        }

        if ($targetWid <= 0) {
            return ['status' => 'error', 'message' => 'Target desa atau koordinat tidak ditemukan di peta dunia.'];
        }

        $targetVil = $database->query_return("
            SELECT v.wref, v.owner, v.name, w.x, w.y, w.fieldtype, w.occupied, u.username, u.protect, u.access
            FROM " . TB_PREFIX . "vdata v
            JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
            JOIN " . TB_PREFIX . "users u ON v.owner = u.id
            WHERE v.wref = $targetWid
            LIMIT 1
        ");

        if (empty($targetVil[0])) {
            return ['status' => 'error', 'message' => 'Target bukan desa pemain yang valid.'];
        }

        $tData = $targetVil[0];
        $targetUid = (int)$tData['owner'];
        $targetName = $tData['name'];
        $targetX = (int)$tData['x'];
        $targetY = (int)$tData['y'];

        // 4. Validation rules
        if ($targetUid === $uid) {
            return ['status' => 'error', 'message' => 'Anda tidak bisa menyewa assassin untuk menyerang desa Anda sendiri!'];
        }

        $sanctuary = self::getSanctuary();
        $sanctuaryWref = !empty($sanctuary['wref']) ? (int)$sanctuary['wref'] : 0;
        if ($targetWid === $sanctuaryWref) {
            return ['status' => 'error', 'message' => 'Assassin menolak menyerang Kuil Suci mereka sendiri!'];
        }

        if ((int)$tData['access'] >= 8) {
            return ['status' => 'error', 'message' => 'Assassin menolak menyentuh staf pengawas server (Admin/Multihunter).'];
        }

        $protectTime = (int)($tData['protect'] ?? 0);
        if ($protectTime > time()) {
            return ['status' => 'error', 'message' => 'Target masih dalam masa Perlindungan Pemula (Beginner Protection)!'];
        }

        // Active contracts limit (max 3 concurrent contracts per player)
        $activeCount = count(self::getActiveContracts($uid));
        if ($activeCount >= 3) {
            return ['status' => 'error', 'message' => 'Anda sudah memiliki 3 kontrak aktif. Tunggu misi sebelumnya selesai.'];
        }

        // 5. Check and deduct payment
        if ($currency === 'silver') {
            if (!class_exists('BlackMarket')) {
                require_once __DIR__ . '/BlackMarket.php';
            }
            $currentSilver = BlackMarket::getSilver($uid);
            if ($currentSilver < $cost) {
                return ['status' => 'error', 'message' => "Saldo Silver Anda tidak cukup (Diperlukan: {$cost} Silver, Anda memiliki: {$currentSilver})."];
            }
            $paid = BlackMarket::spendSilver($uid, $cost, 'assassin_' . $contractType);
            if (!$paid) {
                return ['status' => 'error', 'message' => 'Gagal memotong saldo Silver. Coba beberapa saat lagi.'];
            }
        } else {
            // Gold currency
            $currentGold = (int)$database->getUserField($uid, 'gold', 0, false);
            if ($currentGold < $cost) {
                return ['status' => 'error', 'message' => "Saldo Gold Anda tidak cukup (Diperlukan: {$cost} Gold, Anda memiliki: {$currentGold})."];
            }
            $q = "UPDATE " . TB_PREFIX . "users SET gold = gold - $cost WHERE id = $uid AND gold >= $cost LIMIT 1";
            mysqli_query($db, $q);
            if (mysqli_affected_rows($db) <= 0) {
                return ['status' => 'error', 'message' => 'Gagal memotong saldo Gold.'];
            }
        }

        // 6. Calculate Duration and Timestamps
        $duration = self::calculateDuration($sanctuaryWref, $targetWid);
        $startTime = time();
        $endTime = $startTime + $duration;

        // 7. Insert contract
        $tNameEsc = mysqli_real_escape_string($db, $targetName);
        $insertSql = "INSERT INTO " . TB_PREFIX . "assassin_contracts
            (client_uid, client_wid, target_wid, target_uid, target_name, target_x, target_y,
             contract_type, cost_currency, cost_amount, duration, start_time, end_time, status, created_at)
            VALUES
            ($uid, $clientWid, $targetWid, $targetUid, '$tNameEsc', $targetX, $targetY,
             '$contractType', '$currency', $cost, $duration, $startTime, $endTime, " . self::STATUS_IN_PROGRESS . ", $startTime)";
        mysqli_query($db, $insertSql);
        $contractId = mysqli_insert_id($db);

        return [
            'status' => 'success',
            'contract_id' => $contractId,
            'duration' => $duration,
            'end_time' => $endTime,
            'target_name' => $targetName,
            'target_coor' => "({$targetX}|{$targetY})",
            'message' => 'Kontrak assassin berhasil disewa! Bayangan bergerak menuju target.'
        ];
    }

    /**
     * Main tick / automation cycle to execute completed contracts.
     */
    public static function tick(): array {
        self::ensureTables();
        self::getSanctuary();
        return self::processContracts();
    }

    /**
     * Relocate or set Sanctuary to specific or random coordinates.
     */
    public static function relocateSanctuary(?int $targetX = null, ?int $targetY = null): array {
        global $database, $generator;
        self::ensureTables();

        $user = self::getAssassinUser();
        $uid = (int)$user['id'];

        // Remove old sanctuary village if exists
        $old = $database->query_return("SELECT * FROM " . TB_PREFIX . "assassin_sanctuary WHERE status = 1");
        if (!empty($old)) {
            foreach ($old as $o) {
                $oldWref = (int)$o['wref'];
                $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $oldWref");
                $database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $oldWref");
                $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $oldWref");
                $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id = $oldWref");
            }
            $database->query("DELETE FROM " . TB_PREFIX . "assassin_sanctuary");
        }

        // If targetX and targetY are specified, validate tile
        $tile = null;
        if ($targetX !== null && $targetY !== null) {
            $wref = $generator->getBaseID($targetX, $targetY);
            $tCheck = $database->query_return("SELECT id, x, y, fieldtype, occupied, oasistype FROM " . TB_PREFIX . "wdata WHERE id = $wref LIMIT 1");
            if (!empty($tCheck[0])) {
                if ($tCheck[0]['occupied'] != 0 || $tCheck[0]['oasistype'] != 0 || $tCheck[0]['fieldtype'] <= 0) {
                    throw new \InvalidArgumentException("Koordinat ($targetX|$targetY) bukan petak lembah kosong yang dapat ditempati!");
                }
                $tile = $tCheck[0];
            } else {
                throw new \InvalidArgumentException("Koordinat ($targetX|$targetY) di luar batas peta!");
            }
        }

        if (!$tile) {
            // Find secluded random tile
            $tileSql = "SELECT id, x, y, fieldtype
                        FROM " . TB_PREFIX . "wdata
                        WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0
                          AND SQRT(POW(x, 2) + POW(y, 2)) BETWEEN 15 AND 45
                        LIMIT 30";
            $freeTiles = $database->query_return($tileSql);
            if (empty($freeTiles)) {
                $freeTiles = $database->query_return("SELECT id, x, y, fieldtype FROM " . TB_PREFIX . "wdata WHERE occupied = 0 AND fieldtype > 0 AND oasistype = 0 LIMIT 30");
            }
            $tile = $freeTiles[array_rand($freeTiles)];
        }

        $wref = (int)$tile['id'];
        $x = (int)$tile['x'];
        $y = (int)$tile['y'];
        $fieldtype = (int)$tile['fieldtype'];
        $time = time();
        $sanctuaryName = 'Kuil Bayangan [Sanctuary]';

        $database->addVillage($wref, $uid, 'Klan Assassin', 0, 120, $sanctuaryName);
        $database->addResourceFields($wref, $fieldtype);
        $database->addUnits($wref);
        $database->setFieldTaken($wref);
        $database->query("UPDATE " . TB_PREFIX . "units SET u41 = 500, u46 = 200 WHERE vref = $wref");
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = 50000, clay = 50000, iron = 50000, crop = 50000,
                maxstore = 80000, maxcrop = 80000, lastupdate = $time
            WHERE wref = $wref
        ");

        $db = self::db();
        $nameEsc = mysqli_real_escape_string($db, $sanctuaryName);
        $database->query("
            INSERT INTO " . TB_PREFIX . "assassin_sanctuary
            (wref, x, y, name, status, created_at)
            VALUES
            ($wref, $x, $y, '$nameEsc', 1, $time)
        ");
        $id = mysqli_insert_id($db);

        return [
            'id' => $id,
            'wref' => $wref,
            'x' => $x,
            'y' => $y,
            'name' => $sanctuaryName,
            'status' => 1,
            'created_at' => $time,
            'fieldtype' => $fieldtype
        ];
    }

    /**
     * Process due contracts in real time.
     */
    public static function processContracts(): array {
        global $database;
        self::ensureTables();
        $db = self::db();
        $now = time();

        $dueSql = "SELECT * FROM " . TB_PREFIX . "assassin_contracts
                   WHERE status = " . self::STATUS_IN_PROGRESS . " AND end_time <= $now
                   ORDER BY end_time ASC";
        $dueContracts = $database->query_return($dueSql);

        if (empty($dueContracts)) {
            return ['processed' => 0];
        }

        $processed = 0;
        foreach ($dueContracts as $contract) {
            $cid = (int)$contract['id'];
            $type = $contract['contract_type'];

            $result = null;
            switch ($type) {
                case self::CONTRACT_SHADOW_RAID:
                    $result = self::resolveShadowRaid($contract);
                    break;
                case self::CONTRACT_HERO_ASSASSINATE:
                    $result = self::resolveHeroAssassinate($contract);
                    break;
                case self::CONTRACT_NIGHT_SABOTAGE:
                    $result = self::resolveNightSabotage($contract);
                    break;
                default:
                    $result = ['status' => self::STATUS_FAILED, 'summary' => 'Unknown contract type'];
                    break;
            }

            $newStatus = (int)($result['status'] ?? self::STATUS_COMPLETED);
            $summaryEsc = mysqli_real_escape_string($db, $result['summary'] ?? '');

            $database->query("
                UPDATE " . TB_PREFIX . "assassin_contracts
                SET status = $newStatus, result_summary = '$summaryEsc'
                WHERE id = $cid
            ");

            $processed++;
        }

        return ['processed' => $processed];
    }

    /**
     * 1. Resolve Shadow Raid Contract.
     */
    protected static function resolveShadowRaid(array $contract): array {
        global $database;
        $db = self::db();
        $now = time();

        $targetWid = (int)$contract['target_wid'];
        $targetUid = (int)$contract['target_uid'];
        $clientUid = (int)$contract['client_uid'];
        $clientWid = (int)$contract['client_wid'];
        $targetName = $contract['target_name'];

        // Get target defending garrison
        $unitRow = $database->getUnit($targetWid, false);
        $totalDefendersKilled = 0;
        $killedDetails = [];

        if (!empty($unitRow)) {
            $updates = [];
            for ($u = 1; $u <= 90; $u++) {
                $count = (int)($unitRow['u' . $u] ?? 0);
                if ($count > 0) {
                    // Shadow blades eliminate 35% - 60% of defending troops
                    $killed = min($count, (int)ceil($count * (rand(35, 60) / 100)));
                    if ($killed > 0) {
                        $updates[] = "u{$u} = u{$u} - {$killed}";
                        $totalDefendersKilled += $killed;
                        $killedDetails[] = "{$killed} prajurit";
                    }
                }
            }
            if (!empty($updates)) {
                $database->query("UPDATE " . TB_PREFIX . "units SET " . implode(', ', $updates) . " WHERE vref = $targetWid");
            }
        }

        // Steal resources from target (capped at 2500 per resource, total 10000)
        $tVdata = $database->getVillage($targetWid, 0, false);
        $wood = max(0, (int)($tVdata['wood'] ?? 0));
        $clay = max(0, (int)($tVdata['clay'] ?? 0));
        $iron = max(0, (int)($tVdata['iron'] ?? 0));
        $crop = max(0, (int)($tVdata['crop'] ?? 0));

        $lootWood = min($wood, rand(1200, 2500));
        $lootClay = min($clay, rand(1200, 2500));
        $lootIron = min($iron, rand(1200, 2500));
        $lootCrop = min($crop, rand(1200, 2500));
        $totalLoot = $lootWood + $lootClay + $lootIron + $lootCrop;

        // Deduct loot from target
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = GREATEST(0, wood - $lootWood),
                clay = GREATEST(0, clay - $lootClay),
                iron = GREATEST(0, iron - $lootIron),
                crop = GREATEST(0, crop - $lootCrop)
            WHERE wref = $targetWid
        ");

        // Transfer loot tribute directly to client's village
        $database->query("
            UPDATE " . TB_PREFIX . "vdata
            SET wood = wood + $lootWood,
                clay = clay + $lootClay,
                iron = iron + $lootIron,
                crop = crop + $lootCrop
            WHERE wref = $clientWid
        ");

        $summary = "Serbuan Bayangan berhasil! Membasmi {$totalDefendersKilled} penjaga dan merampas {$totalLoot} sumber daya untuk lumbung Anda.";

        // --- 100% Anonymous Victim Message ---
        $victimTopic = "[SERANGAN MALAM] Desa {$targetName} Diserang Pasukan Bayangan!";
        $victimMsg = "Di tengah kesunyian malam, sekelompok pembunuh bertopeng dari Klan Assassin menerobos tembok desa {$targetName}.\n\n"
                   . "⚔️ Korban Jiwa Pertahanan: {$totalDefendersKilled} prajurit gugur.\n"
                   . "📦 Sumber Daya Yang Dijarah: {$lootWood} Kayu, {$lootClay} Tanah Liat, {$lootIron} Besi, {$lootCrop} Gandum (Total: {$totalLoot}).\n\n"
                   . "Para penyerang lenyap ke dalam kegelapan tanpa meninggalkan petunjuk sedikitpun mengenai siapa yang menyewa mereka.";
        self::sendDirectMessage($targetUid, 'Klan Assassin (Topeng Hitam)', $victimTopic, $victimMsg);

        // --- Client Report ---
        $clientTopic = "[KONTRAK TUNTAS] Serbuan Bayangan di {$targetName}";
        $clientMsg = "Misi serbuan bayangan telah tuntas dilaksanakan dengan kerahasiaan absolut!\n\n"
                   . "🎯 Target: {$targetName} ({$contract['target_x']}|{$contract['target_y']})\n"
                   . "⚔️ Musuh Dibasmi: {$totalDefendersKilled} prajurit\n"
                   . "💰 Upeti Jarahan: {$lootWood} Kayu, {$lootClay} Tanah Liat, {$lootIron} Besi, {$lootCrop} Gandum\n\n"
                   . "Seluruh hasil jarahan telah diantarkan secara rahasia ke lumbung desa Anda.";
        self::sendDirectMessage($clientUid, 'Kuil Bayangan (Assassin Guild)', $clientTopic, $clientMsg);

        return ['status' => self::STATUS_COMPLETED, 'summary' => $summary];
    }

    /**
     * 2. Resolve Hero Assassination Contract.
     */
    protected static function resolveHeroAssassinate(array $contract): array {
        global $database;
        $now = time();

        $targetUid = (int)$contract['target_uid'];
        $targetWid = (int)$contract['target_wid'];
        $clientUid = (int)$contract['client_uid'];
        $targetName = $contract['target_name'];

        // Check if target hero is currently alive
        $heroRow = $database->query_return("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $targetUid LIMIT 1");

        if (!empty($heroRow[0]) && (int)$heroRow[0]['dead'] === 0 && (int)$heroRow[0]['health'] > 0) {
            // Lethal assassination: kill target hero
            $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0, lastupdate = $now WHERE uid = $targetUid");
            $database->query("UPDATE " . TB_PREFIX . "units SET hero = 0 WHERE vref = $targetWid");

            $summary = "Hero musuh berhasil diracun dan dieksekusi mati di kamarnya.";

            // --- 100% Anonymous Victim Message ---
            $victimTopic = "[KABAR DUKA] Hero Anda Ditemukan Tewas Diracun!";
            $victimMsg = "Tragedi berdarah terjadi di desa Anda! Hero Anda ditemukan terbujur kaku di kamarnya dengan luka tusuk belati beracun bertanda segel Klan Assassin.\n\n"
                       . "☠️ Status Hero: Tewas seketika (Health 0%).\n\n"
                       . "Sang pembunuh menyusup seperti hantu dan tidak meninggalkan jejak ataupun identitas penyewa. Anda harus membangkitkannya kembali.";
            self::sendDirectMessage($targetUid, 'Klan Assassin (Topeng Hitam)', $victimTopic, $victimMsg);

            // --- Client Report ---
            $clientTopic = "[KONTRAK TUNTAS] Eksekusi Hero di {$targetName}";
            $clientMsg = "Target telah dinetralkan. Belati beracun klan kami telah menembus jantung Hero musuh di desa {$targetName}.\n\n"
                       . "Misi berhasil tanpa meninggalkan jejak atau kecurigaan sedikitpun.";
            self::sendDirectMessage($clientUid, 'Kuil Bayangan (Assassin Guild)', $clientTopic, $clientMsg);

            return ['status' => self::STATUS_COMPLETED, 'summary' => $summary];
        } else {
            // Target hero was already dead or nonexistent: 50% refund under Creed's honor code
            $currency = $contract['cost_currency'];
            $refund = (int)floor($contract['cost_amount'] / 2);

            if ($currency === 'silver') {
                if (!class_exists('BlackMarket')) {
                    require_once __DIR__ . '/BlackMarket.php';
                }
                BlackMarket::addSilver($clientUid, $refund);
            } else {
                $db = self::db();
                mysqli_query($db, "UPDATE " . TB_PREFIX . "users SET gold = gold + $refund WHERE id = $clientUid");
            }

            $summary = "Hero target sudah dalam keadaan tewas saat assassin tiba. 50% biaya ({$refund} {$currency}) dikembalikan.";

            $clientTopic = "[LAPORAN MISI] Hero Target Sudah Tidak Bernyawa";
            $clientMsg = "Assassin kami telah menyusup ke kediaman musuh di desa {$targetName}, namun Hero sasaran ternyata sudah dalam kondisi tewas/tidak bertugas.\n\n"
                       . "Sesuai kode etik persaudaraan bayangan, kami mengembalikan 50% bayaran Anda sebesar {$refund} " . ucfirst($currency) . ".";
            self::sendDirectMessage($clientUid, 'Kuil Bayangan (Assassin Guild)', $clientTopic, $clientMsg);

            return ['status' => self::STATUS_COMPLETED, 'summary' => $summary];
        }
    }

    /**
     * 3. Resolve Night Sabotage Contract.
     */
    protected static function resolveNightSabotage(array $contract): array {
        global $database;
        $now = time();

        $targetWid = (int)$contract['target_wid'];
        $targetUid = (int)$contract['target_uid'];
        $clientUid = (int)$contract['client_uid'];
        $targetName = $contract['target_name'];

        $fdata = $database->getResourceLevel($targetWid, false);

        // Find candidate constructed buildings (fields 19-40, exclude WW f99)
        $candidates = [];
        for ($i = 19; $i <= 40; $i++) {
            $lvl = (int)($fdata['f' . $i] ?? 0);
            $type = (int)($fdata['f' . $i . 't'] ?? 0);
            if ($lvl > 0 && $type > 0) {
                $candidates[] = ['slot' => $i, 'type' => $type, 'lvl' => $lvl];
            }
        }

        // If no inner buildings, check resource fields (1-18)
        if (empty($candidates)) {
            for ($i = 1; $i <= 18; $i++) {
                $lvl = (int)($fdata['f' . $i] ?? 0);
                if ($lvl > 0) {
                    $candidates[] = ['slot' => $i, 'type' => 0, 'lvl' => $lvl];
                }
            }
        }

        if (!empty($candidates)) {
            $targetBuilding = $candidates[array_rand($candidates)];
            $slot = (int)$targetBuilding['slot'];
            $oldLvl = (int)$targetBuilding['lvl'];
            $bType = (int)$targetBuilding['type'];
            $newLvl = max(0, $oldLvl - 1);

            $bName = 'Gedung';
            if (!defined('WOODCUTTER') && file_exists(__DIR__ . '/Lang/en.php')) {
                @require_once __DIR__ . '/Lang/en.php';
            }
            if ($bType > 0 && class_exists('Building')) {
                $bName = Building::procResType($bType);
            } elseif ($slot <= 18) {
                $bName = 'Ladang Sumber Daya';
            }

            // Demote building level
            $fields = ["f" . $slot];
            $values = [$newLvl];
            if ($newLvl === 0 && $slot >= 19) {
                $fields[] = "f" . $slot . "t";
                $values[] = 0;
            }
            $database->setVillageLevel($targetWid, $fields, $values);

            // Recalculate population
            if (class_exists('BotAI')) {
                BotAI::recountPop($targetWid);
            }

            $summary = "Sabotase sukses! Berhasil merusak dan menurunkan level {$bName} dari level {$oldLvl} menjadi level {$newLvl}.";

            // --- 100% Anonymous Victim Message ---
            $victimTopic = "[SABOTASE MALAM] {$bName} di Desa Anda Dirusak!";
            $victimMsg = "Sabotase misterius terjadi di desa {$targetName}!\n\n"
                       . "🔥 Bangunan Yang Dirusak: {$bName}\n"
                       . "📉 Perubahan Level: Level {$oldLvl} turun menjadi Level {$newLvl}.\n\n"
                       . "Pelaku melarikan diri melintasi bayang-bayang tanpa meninggalkan jejak atau bukti identitas pemesan.";
            self::sendDirectMessage($targetUid, 'Klan Assassin (Topeng Hitam)', $victimTopic, $victimMsg);

            // --- Client Report ---
            $clientTopic = "[KONTRAK TUNTAS] Sabotase Berhasil di {$targetName}";
            $clientMsg = "Operasi infiltrasi dan sabotase berjalan mulus sesuai rencana.\n\n"
                       . "🎯 Sasaran: {$bName} di desa {$targetName}\n"
                       . "💥 Hasil: Berhasil diturunkan dari Level {$oldLvl} menjadi Level {$newLvl}.\n\n"
                       . "Keamanan desa musuh telah diperdaya sepenuhnya.";
            self::sendDirectMessage($clientUid, 'Kuil Bayangan (Assassin Guild)', $clientTopic, $clientMsg);

            return ['status' => self::STATUS_COMPLETED, 'summary' => $summary];
        } else {
            $summary = "Tidak ada bangunan yang dapat disabotase di desa target.";
            return ['status' => self::STATUS_COMPLETED, 'summary' => $summary];
        }
    }

    /**
     * Send direct in-game message to player inbox (clean, immediate).
     */
    public static function sendDirectMessage(int $toUid, string $senderName, string $topic, string $message): bool {
        $db = self::db();
        if (!$db || $toUid <= 0) return false;

        $user = self::getAssassinUser();
        $assassinUid = (int)$user['id'];
        $time = time();

        $stmt = @mysqli_prepare($db, "
            INSERT INTO `" . TB_PREFIX . "mdata`
            (`target`, `owner`, `topic`, `message`, `viewed`, `archived`, `send`, `time`, `deltarget`, `delowner`, `alliance`, `player`, `coor`, `report`)
            VALUES (?, ?, ?, ?, 0, 0, 0, ?, 0, 0, 0, 0, 0, 0)
        ");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iissi', $toUid, $assassinUid, $topic, $message, $time);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $ok;
        }

        return false;
    }
}
