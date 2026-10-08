<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : VillageRelocate.php                                       ##
##  Type           : Beginner Protection Village Relocation System             ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Antigravity Studio for TravianZ Extended Edition          ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

class VillageRelocate {

    // Standard field distributions for fieldtypes 1..12
    private static $fieldTypesMap = [
        1 => [4,4,1,4,4,2,3,4,4,3,3,4,4,1,4,2,1,2], // 3-3-3-9 (9 Cropper)
        2 => [3,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 3-4-5-6
        3 => [1,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 4-4-4-6 (Standard)
        4 => [1,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 4-5-3-6
        5 => [1,4,1,3,1,2,3,4,4,3,3,4,4,1,4,2,1,2], // 5-3-4-6
        6 => [4,4,1,3,4,4,4,4,4,4,4,4,4,4,4,2,4,4], // 1-1-1-15 (15 Cropper)
        7 => [1,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 4-4-3-7
        8 => [3,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 3-4-4-7
        9 => [3,4,4,1,1,2,3,4,4,3,3,4,4,1,4,2,1,2], // 4-3-4-7
        10 => [3,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 3-5-4-6
        11 => [3,1,1,3,1,4,4,3,3,2,2,3,1,4,4,2,4,4], // 4-3-5-6
        12 => [1,4,1,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2], // 5-4-3-6
    ];

    /**
     * Ensure database columns and tables exist.
     */
    public static function ensureSchema() {
        global $database;
        $link = $database->return_link();

        // 1. Add village_relocated flag to users table if missing
        $checkCol = mysqli_query($link, "SHOW COLUMNS FROM " . TB_PREFIX . "users LIKE 'village_relocated'");
        if ($checkCol && mysqli_num_rows($checkCol) == 0) {
            mysqli_query($link, "ALTER TABLE " . TB_PREFIX . "users ADD COLUMN `village_relocated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `protect`");
        }

        // 2. Create village_relocation_log table if missing
        $createLog = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "village_relocation_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `uid` INT NOT NULL,
            `old_wref` INT NOT NULL,
            `new_wref` INT NOT NULL,
            `old_x` INT NOT NULL,
            `old_y` INT NOT NULL,
            `new_x` INT NOT NULL,
            `new_y` INT NOT NULL,
            `time` INT NOT NULL,
            KEY `idx_uid` (`uid`),
            KEY `idx_time` (`time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($link, $createLog);
    }

    /**
     * Check if a player is eligible to relocate their village.
     *
     * @param int $uid User ID
     * @param int $wid Village ID to relocate
     * @return array
     */
    public static function canRelocate($uid, $wid) {
        global $database;
        self::ensureSchema();

        $link = $database->return_link();
        $uid = (int)$uid;
        $wid = (int)$wid;

        if ($uid <= 0 || $wid <= 0) {
            return [
                'can' => false,
                'reason' => 'Data akun atau desa tidak valid.',
                'timeLeft' => 0,
                'hasProtection' => false,
                'hasUsed' => false
            ];
        }

        // Check village ownership
        $vres = mysqli_query($link, "SELECT wref, owner, name FROM " . TB_PREFIX . "vdata WHERE wref = $wid AND owner = $uid LIMIT 1");
        if (!$vres || mysqli_num_rows($vres) == 0) {
            return [
                'can' => false,
                'reason' => 'Desa tidak ditemukan atau bukan milik akun Anda.',
                'timeLeft' => 0,
                'hasProtection' => false,
                'hasUsed' => false
            ];
        }

        // Check user protection and relocation status
        $ures = mysqli_query($link, "SELECT id, protect, village_relocated FROM " . TB_PREFIX . "users WHERE id = $uid LIMIT 1");
        if (!$ures || mysqli_num_rows($ures) == 0) {
            return [
                'can' => false,
                'reason' => 'Pengguna tidak ditemukan.',
                'timeLeft' => 0,
                'hasProtection' => false,
                'hasUsed' => false
            ];
        }
        $userData = mysqli_fetch_assoc($ures);

        $now = time();
        $protectEnd = (int)$userData['protect'];
        $hasProtection = ($protectEnd > $now);
        $timeLeft = max(0, $protectEnd - $now);
        $hasUsed = ((int)$userData['village_relocated'] === 1);

        // Condition 1: Must be within beginner protection
        if (!$hasProtection) {
            return [
                'can' => false,
                'reason' => 'Masa Perlindungan Pemula (Beginner Protection) telah berakhir. Pemindahan desa hanya diizinkan saat perlindungan masih aktif.',
                'timeLeft' => 0,
                'hasProtection' => false,
                'hasUsed' => $hasUsed
            ];
        }

        // Condition 2: Only once per account
        if ($hasUsed) {
            return [
                'can' => false,
                'reason' => 'Hak istimewa pemindahan desa sudah pernah digunakan. Pemindahan hanya diizinkan satu kali per akun.',
                'timeLeft' => $timeLeft,
                'hasProtection' => true,
                'hasUsed' => true
            ];
        }

        // Condition 3: No ongoing movements (troops or merchants)
        $mres = mysqli_query($link, "SELECT COUNT(*) as c FROM " . TB_PREFIX . "movement WHERE proc = 0 AND (`from` = $wid OR `to` = $wid)");
        $movCount = $mres ? (int)mysqli_fetch_assoc($mres)['c'] : 0;
        if ($movCount > 0) {
            return [
                'can' => false,
                'reason' => 'Semua pasukan dan pedagang harus berada di desa. Tidak boleh ada pergerakan pasukan atau pengiriman logistik yang sedang berlangsung.',
                'timeLeft' => $timeLeft,
                'hasProtection' => true,
                'hasUsed' => false,
                'hasMovements' => true
            ];
        }

        // Condition 4: No foreign reinforcements stationed or outgoing reinforcements
        $eres = mysqli_query($link, "SELECT COUNT(*) as c FROM " . TB_PREFIX . "enforcement WHERE `from` = $wid OR `vref` = $wid");
        $enfCount = $eres ? (int)mysqli_fetch_assoc($eres)['c'] : 0;
        if ($enfCount > 0) {
            return [
                'can' => false,
                'reason' => 'Ada pasukan bantuan yang masih berstatus garnisun di desa Anda atau di luar. Pulangkan seluruh pasukan bantuan terlebih dahulu.',
                'timeLeft' => $timeLeft,
                'hasProtection' => true,
                'hasUsed' => false,
                'hasEnforcements' => true
            ];
        }

        return [
            'can' => true,
            'reason' => 'Syarat terpenuhi. Anda dapat memindahkan desa.',
            'timeLeft' => $timeLeft,
            'hasProtection' => true,
            'hasUsed' => false
        ];
    }

    /**
     * Inspect destination tile and validate if it's an unoccupied abandoned valley.
     *
     * @param int $x
     * @param int $y
     * @param int $currentWid
     * @return array
     */
    public static function checkTargetCoordinates($x, $y, $currentWid) {
        global $database;
        $link = $database->return_link();

        $x = (int)$x;
        $y = (int)$y;
        $currentWid = (int)$currentWid;

        // Query target tile
        $q = "SELECT id, fieldtype, oasistype, x, y, occupied, image FROM " . TB_PREFIX . "wdata WHERE x = $x AND y = $y LIMIT 1";
        $res = mysqli_query($link, $q);
        if (!$res || mysqli_num_rows($res) == 0) {
            return [
                'valid' => false,
                'error' => "Koordinat ($x|$y) berada di luar batas peta dunia.",
                'tile' => null
            ];
        }

        $tile = mysqli_fetch_assoc($res);
        $targetWid = (int)$tile['id'];

        if ($targetWid == $currentWid) {
            return [
                'valid' => false,
                'error' => "Koordinat tujuan sama dengan lokasi desa Anda saat ini.",
                'tile' => $tile
            ];
        }

        if ((int)$tile['occupied'] != 0) {
            return [
                'valid' => false,
                'error' => "Koordinat ($x|$y) sudah ditempati oleh desa lain atau sarang bandit.",
                'tile' => $tile
            ];
        }

        if ((int)$tile['oasistype'] != 0 || (int)$tile['fieldtype'] == 0) {
            return [
                'valid' => false,
                'error' => "Koordinat ($x|$y) merupakan oasis atau perairan bebas dan tidak dapat didirikan desa.",
                'tile' => $tile
            ];
        }

        // Field type name description
        $fieldNames = [
            1 => '3-3-3-9 (9 Lumbung Gandum)',
            2 => '3-4-5-6 (Kaya Besi)',
            3 => '4-4-4-6 (Standar Berimbang)',
            4 => '4-5-3-6 (Kaya Tanah Liat)',
            5 => '5-3-4-6 (Kaya Kayu)',
            6 => '1-1-1-15 (15 Lumbung Gandum)',
            7 => '4-4-3-7 (7 Gandum)',
            8 => '3-4-4-7 (7 Gandum)',
            9 => '4-3-4-7 (7 Gandum)',
            10 => '3-5-4-6 (Kaya Tanah)',
            11 => '4-3-5-6 (Kaya Besi)',
            12 => '5-4-3-6 (Kaya Kayu)'
        ];

        $ft = (int)$tile['fieldtype'];
        $tile['fieldtypeName'] = $fieldNames[$ft] ?? "Tipe $ft";

        return [
            'valid' => true,
            'error' => '',
            'tile' => $tile
        ];
    }

    /**
     * Inspect destination tile by WID.
     *
     * @param int $targetWid
     * @param int $currentWid
     * @return array
     */
    public static function checkTargetByWid($targetWid, $currentWid) {
        global $database;
        $link = $database->return_link();
        $targetWid = (int)$targetWid;

        $res = mysqli_query($link, "SELECT x, y FROM " . TB_PREFIX . "wdata WHERE id = $targetWid LIMIT 1");
        if (!$res || mysqli_num_rows($res) == 0) {
            return [
                'valid' => false,
                'error' => "Tile ID $targetWid tidak ditemukan.",
                'tile' => null
            ];
        }
        $row = mysqli_fetch_assoc($res);
        return self::checkTargetCoordinates($row['x'], $row['y'], $currentWid);
    }

    /**
     * Execute village relocation.
     *
     * @param int $uid User ID
     * @param int $oldWid Current Village ID
     * @param int $targetX Target Coordinate X
     * @param int $targetY Target Coordinate Y
     * @return array
     */
    public static function relocateVillage($uid, $oldWid, $targetX, $targetY) {
        global $database;
        self::ensureSchema();

        $link = $database->return_link();
        $uid = (int)$uid;
        $oldWid = (int)$oldWid;
        $targetX = (int)$targetX;
        $targetY = (int)$targetY;

        // 1. Check user eligibility
        $eligibility = self::canRelocate($uid, $oldWid);
        if (!$eligibility['can']) {
            return [
                'success' => false,
                'message' => $eligibility['reason']
            ];
        }

        // 2. Check target coordinates
        $targetCheck = self::checkTargetCoordinates($targetX, $targetY, $oldWid);
        if (!$targetCheck['valid']) {
            return [
                'success' => false,
                'message' => $targetCheck['error']
            ];
        }

        $targetTile = $targetCheck['tile'];
        $newWid = (int)$targetTile['id'];
        $newFieldType = (int)$targetTile['fieldtype'];

        // Get old village coordinates and fieldtype
        $oldTileRes = mysqli_query($link, "SELECT id, x, y, fieldtype FROM " . TB_PREFIX . "wdata WHERE id = $oldWid LIMIT 1");
        $oldTile = mysqli_fetch_assoc($oldTileRes);
        $oldX = (int)$oldTile['x'];
        $oldY = (int)$oldTile['y'];
        $oldFieldType = (int)$oldTile['fieldtype'];

        // Get village name
        $vRes = mysqli_query($link, "SELECT name FROM " . TB_PREFIX . "vdata WHERE wref = $oldWid LIMIT 1");
        $vRow = mysqli_fetch_assoc($vRes);
        $villageName = $vRow['name'] ?? 'Desa';

        // 3. Begin Atomic Transaction
        mysqli_begin_transaction($link);

        try {
            // A. Update wdata
            mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $newWid");

            // B. Update vdata (Village record)
            mysqli_query($link, "UPDATE " . TB_PREFIX . "vdata SET wref = $newWid WHERE wref = $oldWid");

            // C. Update fdata (Buildings and Resource Fields)
            mysqli_query($link, "UPDATE " . TB_PREFIX . "fdata SET vref = $newWid WHERE vref = $oldWid");

            // If destination has a different fieldtype, adapt f1t..f18t field types
            if ($oldFieldType != $newFieldType && isset(self::$fieldTypesMap[$newFieldType])) {
                $newTypes = self::$fieldTypesMap[$newFieldType];
                $fUpdates = [];
                for ($i = 1; $i <= 18; $i++) {
                    $fUpdates[] = "f{$i}t = " . (int)$newTypes[$i - 1];
                }
                $updateFdataSql = "UPDATE " . TB_PREFIX . "fdata SET " . implode(", ", $fUpdates) . " WHERE vref = $newWid";
                mysqli_query($link, $updateFdataSql);
            }

            // D. Update military units & training
            mysqli_query($link, "UPDATE " . TB_PREFIX . "units SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "abdata SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "bdata SET wid = $newWid WHERE wid = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "research SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "tdata SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "training SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "demolition SET vref = $newWid WHERE vref = $oldWid");

            // E. Update market & farmlists
            mysqli_query($link, "UPDATE " . TB_PREFIX . "market SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "farmlist SET wref = $newWid WHERE wref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "raidlist SET towref = $newWid WHERE towref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "route SET wid = $newWid WHERE wid = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "route SET `from` = $newWid WHERE `from` = $oldWid");

            // F. Update hero location
            mysqli_query($link, "UPDATE " . TB_PREFIX . "hero SET wref = $newWid WHERE wref = $oldWid AND uid = $uid");

            // G. Update custom systems: mercenaries & plague
            mysqli_query($link, "UPDATE " . TB_PREFIX . "mercenaries SET vref = $newWid WHERE vref = $oldWid");
            mysqli_query($link, "UPDATE " . TB_PREFIX . "village_plague SET vref = $newWid WHERE vref = $oldWid");

            // H. Mark user as relocated (Only once!)
            mysqli_query($link, "UPDATE " . TB_PREFIX . "users SET village_relocated = 1 WHERE id = $uid");

            // I. Write relocation audit log
            $now = time();
            $logSql = "INSERT INTO " . TB_PREFIX . "village_relocation_log (uid, old_wref, new_wref, old_x, old_y, new_x, new_y, time) 
                       VALUES ($uid, $oldWid, $newWid, $oldX, $oldY, $targetX, $targetY, $now)";
            mysqli_query($link, $logSql);

            // Commit all changes
            mysqli_commit($link);

            // Invalidate session caches
            if (isset($_SESSION['username'])) {
                unset($_SESSION['cache_user_' . $_SESSION['username']]);
            }
            if (isset($_SESSION['id_user'])) {
                unset($_SESSION['cache_villages_' . $_SESSION['id_user']]);
            }
            $_SESSION['wid'] = $newWid;

            return [
                'success' => true,
                'message' => "🎉 Selamat! Desa '$villageName' berhasil dipindahkan dari koordinat ($oldX|$oldY) ke koordinat ($targetX|$targetY). Seluruh bangunan, sumber daya, dan pasukan Anda telah tiba di tanah baru!",
                'newWid' => $newWid,
                'newX' => $targetX,
                'newY' => $targetY
            ];

        } catch (Exception $e) {
            mysqli_rollback($link);
            return [
                'success' => false,
                'message' => "Terjadi kesalahan saat memproses pemindahan desa: " . $e->getMessage()
            ];
        }
    }
}
