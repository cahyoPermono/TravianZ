<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Bastion.php                                               ##
##  Type           : Ancient Bastion (King of the Hill) Event Engine           ##
##  Purpose        : Epic Central Fortress (0|0) contest event where           ##
##                   player alliances and bot factions compete to capture      ##
##                   and hold the bastion for 12 hours for massive buffs       ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Bastion {

    const BASTION_WREF            = 20201; // Sumbu Pusat Peta (0|0)
    const STATUS_INACTIVE         = 0;     // Damai / Tidak Ada Event
    const STATUS_ACTIVE           = 1;     // Event Berjalan (Perebutan Aktif)
    const STATUS_VICTORY          = 2;     // Kemenangan Tercapai (Masa Berkah / Cooldown)
    const STATUS_SCHEDULED        = 3;     // Terjadwal (Menunggu Waktu Mulai yang Ditentukan Admin)

    const SCHEDULE_MANUAL         = 0;     // Manual oleh Admin (Default)
    const SCHEDULE_ONEOFF         = 1;     // Tanggal & Jam Tertentu
    const SCHEDULE_WEEKLY         = 2;     // Mingguan Rutin (misal tiap Sabtu 19:00 WIB)
    const SCHEDULE_INTERVAL       = 3;     // Interval Rutin Tiap N Hari

    const DEFAULT_HOLD_DURATION   = 43200; // 12 Jam (dalam detik)
    const DEFAULT_GUARDIAN_TROOPS = 300;   // Default 300 unit penjaga awal
    const DEFAULT_BOT_INTERVAL    = 7200;  // 2 Jam sekali bot menguji benteng
    const DEFAULT_BUFF_DURATION   = 259200;// 3 Hari Berkah Kemenangan (3 * 86400)

    /**
     * Resolve active mysqli database connection.
     */
    private static function db() {
        if (isset($GLOBALS['database']) && isset($GLOBALS['database']->dblink) && $GLOBALS['database']->dblink) {
            return $GLOBALS['database']->dblink;
        }
        if (isset($GLOBALS['link']) && $GLOBALS['link']) {
            return $GLOBALS['link'];
        }
        return null;
    }

    /**
     * Self-healing migration to create or update bastion tables.
     */
    public static function ensureTables(): void {
        $db = self::db();
        if (!$db) return;

        // 1. Table status event benteng
        $q1 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "bastion_event` (
            `id` int(11) NOT NULL DEFAULT 1,
            `wref` int(11) NOT NULL DEFAULT " . self::BASTION_WREF . ",
            `status` tinyint(2) NOT NULL DEFAULT 0,
            `holder_alliance` int(11) NOT NULL DEFAULT 0,
            `holder_uid` int(11) NOT NULL DEFAULT 0,
            `hold_start_time` int(11) NOT NULL DEFAULT 0,
            `hold_duration` int(11) NOT NULL DEFAULT " . self::DEFAULT_HOLD_DURATION . ",
            `event_start_time` int(11) NOT NULL DEFAULT 0,
            `event_end_time` int(11) NOT NULL DEFAULT 0,
            `guardian_initial_troops` int(11) NOT NULL DEFAULT " . self::DEFAULT_GUARDIAN_TROOPS . ",
            `bot_attack_interval` int(11) NOT NULL DEFAULT " . self::DEFAULT_BOT_INTERVAL . ",
            `last_bot_attack` int(11) NOT NULL DEFAULT 0,
            `last_bot_reinforce` int(11) NOT NULL DEFAULT 0,
            `buff_until` int(11) NOT NULL DEFAULT 0,
            `winner_alliance` int(11) NOT NULL DEFAULT 0,
            `winner_uid` int(11) NOT NULL DEFAULT 0,
            `winner_time` int(11) NOT NULL DEFAULT 0,
            `schedule_mode` tinyint(2) NOT NULL DEFAULT 0,
            `scheduled_start_time` int(11) NOT NULL DEFAULT 0,
            `recurring_day` tinyint(2) NOT NULL DEFAULT 6,
            `recurring_hour` tinyint(2) NOT NULL DEFAULT 19,
            `recurring_minute` tinyint(2) NOT NULL DEFAULT 0,
            `recurring_interval_days` int(11) NOT NULL DEFAULT 7,
            `last_scheduled_start` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `holder_alliance` (`holder_alliance`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q1);

        // Self-healing check for added scheduler columns on existing table
        $schedCols = [
            'schedule_mode' => 'TINYINT(2) NOT NULL DEFAULT 0',
            'scheduled_start_time' => 'INT(11) NOT NULL DEFAULT 0',
            'recurring_day' => 'TINYINT(2) NOT NULL DEFAULT 6',
            'recurring_hour' => 'TINYINT(2) NOT NULL DEFAULT 19',
            'recurring_minute' => 'TINYINT(2) NOT NULL DEFAULT 0',
            'recurring_interval_days' => 'INT(11) NOT NULL DEFAULT 7',
            'last_scheduled_start' => 'INT(11) NOT NULL DEFAULT 0'
        ];
        foreach ($schedCols as $col => $type) {
            $checkCol = @mysqli_query($db, "SHOW COLUMNS FROM `" . TB_PREFIX . "bastion_event` LIKE '$col'");
            if ($checkCol && mysqli_num_rows($checkCol) === 0) {
                @mysqli_query($db, "ALTER TABLE `" . TB_PREFIX . "bastion_event` ADD `$col` $type");
            }
        }

        // Pastikan row 1 selalu ada
        $check = @mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "bastion_event` WHERE id = 1 LIMIT 1");
        if ($check && mysqli_num_rows($check) === 0) {
            @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bastion_event` (`id`, `wref`, `status`, `guardian_initial_troops`, `hold_duration`, `bot_attack_interval`) 
                VALUES (1, " . self::BASTION_WREF . ", 0, " . self::DEFAULT_GUARDIAN_TROOPS . ", " . self::DEFAULT_HOLD_DURATION . ", " . self::DEFAULT_BOT_INTERVAL . ")");
        }

        // 2. Table riwayat pertarungan dan log event benteng
        $q2 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "bastion_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_time` int(11) NOT NULL DEFAULT 0,
            `log_type` varchar(32) NOT NULL DEFAULT 'info',
            `alliance_id` int(11) NOT NULL DEFAULT 0,
            `uid` int(11) NOT NULL DEFAULT 0,
            `title` varchar(255) NOT NULL DEFAULT '',
            `description` text,
            PRIMARY KEY (`id`),
            KEY `event_time` (`event_time`),
            KEY `alliance_id` (`alliance_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q2);

        // 3. Table Hall of Fame (Daftar Pemenang & Juara Bertahan dari Tiap Musim Event)
        $q3 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "bastion_hall_of_fame` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `season` int(11) NOT NULL DEFAULT 1,
            `alliance_id` int(11) NOT NULL DEFAULT 0,
            `alliance_name` varchar(100) NOT NULL DEFAULT '',
            `alliance_tag` varchar(20) NOT NULL DEFAULT '',
            `leader_uid` int(11) NOT NULL DEFAULT 0,
            `leader_username` varchar(100) NOT NULL DEFAULT '',
            `win_time` int(11) NOT NULL DEFAULT 0,
            `hold_duration` int(11) NOT NULL DEFAULT " . self::DEFAULT_HOLD_DURATION . ",
            `buff_until` int(11) NOT NULL DEFAULT 0,
            `note` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `season` (`season`),
            KEY `win_time` (`win_time`),
            KEY `alliance_id` (`alliance_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q3);

        // Migrasi juara sebelumnya jika tabel Hall of Fame masih kosong namun sudah ada pemenang di bastion_event
        $checkHof = @mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "bastion_hall_of_fame` LIMIT 1");
        if ($checkHof && mysqli_num_rows($checkHof) === 0) {
            $evRes = @mysqli_query($db, "SELECT winner_alliance, winner_uid, winner_time, buff_until, hold_duration FROM `" . TB_PREFIX . "bastion_event` WHERE id = 1 LIMIT 1");
            if ($evRes && ($evRow = mysqli_fetch_assoc($evRes))) {
                $wAlly = (int)($evRow['winner_alliance'] ?? 0);
                $wUid = (int)($evRow['winner_uid'] ?? 0);
                $wTime = (int)($evRow['winner_time'] ?? 0);
                if ($wAlly > 0 && $wTime > 0) {
                    $aName = "Aliansi #$wAlly";
                    $aTag = "KUNO";
                    $qA = @mysqli_query($db, "SELECT name, tag FROM `" . TB_PREFIX . "alidata` WHERE id = $wAlly LIMIT 1");
                    if ($qA && $a = mysqli_fetch_assoc($qA)) {
                        $aName = $a['name'];
                        $aTag = $a['tag'];
                    }
                    $uName = "Panglima";
                    $qU = @mysqli_query($db, "SELECT username FROM `" . TB_PREFIX . "users` WHERE id = $wUid LIMIT 1");
                    if ($qU && $u = mysqli_fetch_assoc($qU)) {
                        $uName = $u['username'];
                    }
                    $wDur = (int)($evRow['hold_duration'] ?: self::DEFAULT_HOLD_DURATION);
                    $wBuff = (int)($evRow['buff_until'] ?: ($wTime + self::DEFAULT_BUFF_DURATION));
                    $aNameEsc = mysqli_real_escape_string($db, $aName);
                    $aTagEsc = mysqli_real_escape_string($db, $aTag);
                    $uNameEsc = mysqli_real_escape_string($db, $uName);
                    @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bastion_hall_of_fame` 
                        (`season`, `alliance_id`, `alliance_name`, `alliance_tag`, `leader_uid`, `leader_username`, `win_time`, `hold_duration`, `buff_until`, `note`)
                        VALUES (1, $wAlly, '$aNameEsc', '$aTagEsc', $wUid, '$uNameEsc', $wTime, $wDur, $wBuff, 'Juara Perdana Benteng Kuno')");
                }
            }
        }
    }

    /**
     * Check if a village reference is the Ancient Bastion.
     */
    public static function isBastionVillage($wref): bool {
        return (int)$wref === self::BASTION_WREF;
    }

    /**
     * Check if the Ancient Bastion contest event is currently active.
     */
    public static function isEventActive(): bool {
        $event = self::getEvent();
        return (int)($event['status'] ?? 0) === self::STATUS_ACTIVE;
    }

    /**
     * Get Bastion coordinates & WREF.
     */
    public static function getBastionWref(): int {
        return self::BASTION_WREF;
    }

    /**
     * Retrieve current Bastion event state.
     */
    public static function getEvent(): array {
        self::ensureTables();
        $db = self::db();
        if (!$db) return [];

        $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_event` WHERE id = 1 LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row;
        }
        return [
            'id' => 1,
            'wref' => self::BASTION_WREF,
            'status' => self::STATUS_INACTIVE,
            'holder_alliance' => 0,
            'holder_uid' => 0,
            'hold_start_time' => 0,
            'hold_duration' => self::DEFAULT_HOLD_DURATION,
            'guardian_initial_troops' => self::DEFAULT_GUARDIAN_TROOPS,
            'bot_attack_interval' => self::DEFAULT_BOT_INTERVAL,
            'buff_until' => 0,
            'winner_alliance' => 0,
            'winner_uid' => 0,
            'winner_time' => 0,
            'schedule_mode' => self::SCHEDULE_MANUAL,
            'scheduled_start_time' => 0,
            'recurring_day' => 6,
            'recurring_hour' => 19,
            'recurring_minute' => 0,
            'recurring_interval_days' => 7,
            'last_scheduled_start' => 0
        ];
    }

    /**
     * Ensure the Guardian User and Bot Alliance exist.
     * Returns array ['uid' => int, 'ally' => int]
     */
    public static function ensureBotFaction(): array {
        $db = self::db();
        if (!$db) return ['uid' => 2, 'ally' => 0];

        // 1. Cek user Penjaga Kuno (Tribe 5 Natars)
        $qUser = @mysqli_query($db, "SELECT id, alliance FROM `" . TB_PREFIX . "users` WHERE username = 'Penjaga Kuno' LIMIT 1");
        if ($qUser && mysqli_num_rows($qUser) > 0) {
            $u = mysqli_fetch_assoc($qUser);
            $guardianUid = (int)$u['id'];
            $guardianAlly = (int)$u['alliance'];
        } else {
            // Buat user Penjaga Kuno
            $regTime = time();
            @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` 
                (`username`, `password`, `email`, `tribe`, `access`, `gold`, `gender`, `birthday`, `regtime`, `act`, `protect`)
                VALUES ('Penjaga Kuno', '" . md5('penjaga_kuno_secret_' . $regTime) . "', 'guardian@bastion.internal', 5, 2, 0, 0, '1990-01-01', $regTime, '', 0)");
            $guardianUid = (int)mysqli_insert_id($db);
            $guardianAlly = 0;
        }

        // 2. Cek aliansi Penjaga Kuno
        if ($guardianAlly <= 0) {
            $qAlly = @mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "alidata` WHERE tag = 'KUNO' LIMIT 1");
            if ($qAlly && mysqli_num_rows($qAlly) > 0) {
                $a = mysqli_fetch_assoc($qAlly);
                $guardianAlly = (int)$a['id'];
            } else {
                @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "alidata` (`name`, `tag`, `leader`, `coor`, `desc`)
                    VALUES ('Aliansi Penjaga Kuno', 'KUNO', $guardianUid, " . self::BASTION_WREF . ", 'Aliansi Kuno penjaga pusaka dan Benteng Pusat Peta.')");
                $guardianAlly = (int)mysqli_insert_id($db);
            }
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET `alliance` = $guardianAlly WHERE `id` = $guardianUid");
        }

        return ['uid' => $guardianUid, 'ally' => $guardianAlly];
    }

    /**
     * Ensure the village at (0|0) is populated as the Ancient Bastion.
     */
    public static function ensureBastionVillage($guardianTotal = self::DEFAULT_GUARDIAN_TROOPS): void {
        global $database;
        $db = self::db();
        if (!$db) return;

        $faction = self::ensureBotFaction();
        $guardianUid = $faction['uid'];
        $wref = self::BASTION_WREF;

        // Cek apakah village sudah ada di vdata
        $qV = @mysqli_query($db, "SELECT wref, owner FROM `" . TB_PREFIX . "vdata` WHERE wref = $wref LIMIT 1");
        if ($qV && mysqli_num_rows($qV) > 0) {
            // Pastikan nama desa dan pemilik awal tetap Penjaga Kuno jika tidak ada holder
            $event = self::getEvent();
            if ($event['status'] == self::STATUS_INACTIVE || $event['holder_alliance'] == 0) {
                @mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET `name` = '🏰 Benteng Kuno', `owner` = $guardianUid, `pop` = 250, `natar` = 1 WHERE `wref` = $wref");
            }
        } else {
            // Buat village baru di (0|0)
            if ($database) {
                $database->addVillage($wref, $guardianUid, 'Penjaga Kuno', 0, 250, '🏰 Benteng Kuno', 1);
                $database->addResourceFields($wref, 3); // 4-4-4-6 layout
                $database->addUnits($wref);
                $database->setFieldTaken($wref);
            }
        }

        // Pastikan Tembok Kuno (Level 12) & Titik Temu (Level 1) terpasang di fdata
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET `f39` = 1, `f39t` = 16, `f40` = 12, `f40t` = 35 WHERE `vref` = $wref");

        // Pastikan lumbung dan gudang penuh agar tidak pernah kelaparan
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET `wood` = 80000, `clay` = 80000, `iron` = 80000, `crop` = 80000, `maxstore` = 80000, `maxcrop` = 80000 WHERE `wref` = $wref");

        // Atur pasukan penjaga awal
        self::refillGuardians($guardianTotal);
    }

    /**
     * Refill or set guardian troops at the Bastion.
     * Uses balanced Tribe 5 (Natars) units summing up to $total units.
     */
    public static function refillGuardians(int $total = self::DEFAULT_GUARDIAN_TROOPS): void {
        $db = self::db();
        if (!$db) return;

        $wref = self::BASTION_WREF;
        if ($total <= 0) $total = self::DEFAULT_GUARDIAN_TROOPS;

        // Proporsi penjaga:
        // 40% Pikeman (u41 - anti kavaleri)
        // 30% Throned Warrior (u42 - anti infanteri)
        // 20% Guardsman (u43 - pertahanan tebal)
        // 7% Natarian Knight (u46 - kavaleri)
        // 3% War Elephant (u47 - raksasa penjaga)
        $u41 = (int)round($total * 0.40);
        $u42 = (int)round($total * 0.30);
        $u43 = (int)round($total * 0.20);
        $u46 = (int)round($total * 0.07);
        $u47 = max(1, $total - ($u41 + $u42 + $u43 + $u46));

        // Bersihkan bala bantuan lama jika sedang reset
        @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref");

        // Update units table di vdata benteng
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "units` SET 
            `u1`=0, `u2`=0, `u3`=0, `u4`=0, `u5`=0, `u6`=0, `u7`=0, `u8`=0, `u9`=0, `u10`=0,
            `u11`=0, `u12`=0, `u13`=0, `u14`=0, `u15`=0, `u16`=0, `u17`=0, `u18`=0, `u19`=0, `u20`=0,
            `u21`=0, `u22`=0, `u23`=0, `u24`=0, `u25`=0, `u26`=0, `u27`=0, `u28`=0, `u29`=0, `u30`=0,
            `u31`=0, `u32`=0, `u33`=0, `u34`=0, `u35`=0, `u36`=0, `u37`=0, `u38`=0, `u39`=0, `u40`=0,
            `u41`=$u41, `u42`=$u42, `u43`=$u43, `u44`=0, `u45`=0, `u46`=$u46, `u47`=$u47, `u48`=0, `u49`=0, `u50`=0
            WHERE `vref` = $wref");
    }

    /**
     * Start the Bastion event.
     */
    public static function startEvent(int $guardianTroops = self::DEFAULT_GUARDIAN_TROOPS, int $holdDuration = self::DEFAULT_HOLD_DURATION, int $botInterval = self::DEFAULT_BOT_INTERVAL): bool {
        self::ensureTables();
        self::ensureBastionVillage($guardianTroops);
        $db = self::db();
        if (!$db) return false;

        $faction = self::ensureBotFaction();
        $now = time();

        $q = "UPDATE `" . TB_PREFIX . "bastion_event` SET
            `status` = " . self::STATUS_ACTIVE . ",
            `holder_alliance` = 0,
            `holder_uid` = 0,
            `hold_start_time` = 0,
            `hold_duration` = $holdDuration,
            `event_start_time` = $now,
            `event_end_time` = 0,
            `guardian_initial_troops` = $guardianTroops,
            `bot_attack_interval` = $botInterval,
            `last_bot_attack` = $now,
            `last_bot_reinforce` = $now,
            `buff_until` = 0
            WHERE `id` = 1";
        $ok = @mysqli_query($db, $q);

        self::addLog('event_start', 0, 0, 'Gerbang Benteng Kuno Telah Terbuka!', 
            "Pintu Benteng Kuno di pusat peta (0|0) telah resmi terbuka! Dijaga oleh $guardianTroops Penjaga Kuno. Aliansi mana pun yang berhasil menguasai dan mempertahankan benteng selama " . round($holdDuration / 3600) . " jam akan memenangkan Berkah Kedaulatan!");

        // Siarkan pengumuman pembukaan benteng ke seluruh pemain (IGM & Chat Global)
        self::broadcastEventStart($guardianTroops, $holdDuration);

        return (bool)$ok;
    }

    /**
     * Stop / Peace freeze the event.
     */
    public static function stopEvent(): bool {
        self::ensureTables();
        $db = self::db();
        if (!$db) return false;

        $ok = @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET 
            `status` = " . self::STATUS_INACTIVE . ",
            `holder_alliance` = 0,
            `holder_uid` = 0,
            `hold_start_time` = 0
            WHERE `id` = 1");
        self::ensureBastionVillage();
        self::addLog('event_stop', 0, 0, 'Event Benteng Kuno Ditutup', 'Gerbang benteng telah ditutup kembali. Seluruh pertempuran dibekukan.');
        return (bool)$ok;
    }

    /**
     * Configure the automatic event schedule.
     */
    public static function setSchedule(int $mode, int $scheduledTime = 0, int $day = 6, int $hour = 19, int $minute = 0, int $intervalDays = 7): bool {
        self::ensureTables();
        $db = self::db();
        if (!$db) return false;

        $event = self::getEvent();
        $now = time();

        $nextStart = self::calculateNextStartTime($mode, $scheduledTime, $day, $hour, $minute, $intervalDays, (int)($event['winner_time'] ?? 0));

        if ($mode > self::SCHEDULE_MANUAL && $nextStart !== null && $nextStart > $now) {
            // Jadwal berada di masa depan: status otomatis menjadi STATUS_SCHEDULED (gerbang terkunci menanti jadwal)
            $newStatus = self::STATUS_SCHEDULED;
            $holderReset = ", `holder_alliance` = 0, `holder_uid` = 0, `hold_start_time` = 0, `buff_until` = 0";
        } elseif ($mode === self::SCHEDULE_MANUAL) {
            // Mode manual: gerbang ditutup (damai)
            $newStatus = self::STATUS_INACTIVE;
            $holderReset = ", `holder_alliance` = 0, `holder_uid` = 0, `hold_start_time` = 0, `buff_until` = 0";
        } else {
            $newStatus = (int)($event['status'] ?? self::STATUS_INACTIVE);
            $holderReset = "";
        }

        $q = "UPDATE `" . TB_PREFIX . "bastion_event` SET
            `schedule_mode` = $mode,
            `scheduled_start_time` = $scheduledTime,
            `recurring_day` = $day,
            `recurring_hour` = $hour,
            `recurring_minute` = $minute,
            `recurring_interval_days` = $intervalDays,
            `status` = $newStatus
            $holderReset
            WHERE `id` = 1";

        $ok = @mysqli_query($db, $q);

        if ($newStatus === self::STATUS_SCHEDULED || $newStatus === self::STATUS_INACTIVE) {
            self::ensureBastionVillage((int)($event['guardian_initial_troops'] ?: self::DEFAULT_GUARDIAN_TROOPS));
        }

        $dummyEvent = [
            'schedule_mode' => $mode,
            'scheduled_start_time' => $scheduledTime,
            'recurring_day' => $day,
            'recurring_hour' => $hour,
            'recurring_minute' => $minute,
            'recurring_interval_days' => $intervalDays
        ];
        $desc = self::formatScheduleDescription($dummyEvent);
        self::addLog('schedule_update', 0, 0, 'Jadwal Event Benteng Kuno Diperbarui', "Admin telah mengatur jadwal: $desc.");

        return (bool)$ok;
    }

    /**
     * Calculate next scheduled start timestamp (Unix timestamp) from raw parameters.
     */
    public static function calculateNextStartTime(int $mode, int $scheduledTime = 0, int $day = 6, int $hour = 19, int $minute = 0, int $intervalDays = 7, int $winnerTime = 0): ?int {
        $now = time();

        if ($mode === self::SCHEDULE_ONEOFF) {
            return ($scheduledTime > $now) ? $scheduledTime : null;
        }

        if ($mode === self::SCHEDULE_WEEKLY) {
            $targetDay = $day; // 0 (Minggu) .. 6 (Sabtu)
            $targetHour = $hour;
            $targetMin = $minute;

            $currentDay = (int)date('w', $now);
            $daysDiff = ($targetDay - $currentDay + 7) % 7;
            
            // Waktu target di hari ini
            $targetToday = strtotime(date('Y-m-d', $now) . " $targetHour:$targetMin:00");
            if ($daysDiff === 0 && $now >= $targetToday) {
                // Jika hari ini tapi jamnya sudah lewat, jadwalkan minggu depan
                $daysDiff = 7;
            }

            return strtotime("+$daysDiff days", $targetToday);
        }

        if ($mode === self::SCHEDULE_INTERVAL) {
            $intervalDays = max(1, $intervalDays);
            if ($winnerTime > 0) {
                $target = $winnerTime + ($intervalDays * 86400);
                return ($target > $now) ? $target : $now;
            }
        }

        return null;
    }

    /**
     * Calculate next scheduled start timestamp (Unix timestamp).
     * Returns null if no schedule active or event is already running (unless $ignoreStatus is true).
     */
    public static function getNextScheduledStartTime(array $event, bool $ignoreStatus = false): ?int {
        $status = (int)($event['status'] ?? 0);
        if (!$ignoreStatus && $status === self::STATUS_ACTIVE) {
            return null; // Sedang berlangsung
        }

        $mode = (int)($event['schedule_mode'] ?? self::SCHEDULE_MANUAL);
        $scheduledTime = (int)($event['scheduled_start_time'] ?? 0);
        $day = (int)($event['recurring_day'] ?? 6);
        $hour = (int)($event['recurring_hour'] ?? 19);
        $minute = (int)($event['recurring_minute'] ?? 0);
        $intervalDays = (int)($event['recurring_interval_days'] ?? 7);
        $winnerTime = (int)($event['winner_time'] ?? 0);

        return self::calculateNextStartTime($mode, $scheduledTime, $day, $hour, $minute, $intervalDays, $winnerTime);
    }

    /**
     * Get human-readable description of current schedule in Indonesian.
     */
    public static function formatScheduleDescription(array $event): string {
        $mode = (int)($event['schedule_mode'] ?? self::SCHEDULE_MANUAL);
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        switch ($mode) {
            case self::SCHEDULE_ONEOFF:
                $ts = (int)($event['scheduled_start_time'] ?? 0);
                if ($ts <= 0) return "Tanggal tertentu belum ditentukan";
                return "Tanggal Khusus: " . date('d M Y', $ts) . " pukul " . date('H:i', $ts) . " WIB";

            case self::SCHEDULE_WEEKLY:
                $day = (int)($event['recurring_day'] ?? 6);
                $hour = (int)($event['recurring_hour'] ?? 19);
                $min = (int)($event['recurring_minute'] ?? 0);
                $dayName = $dayNames[$day] ?? 'Sabtu';
                return "Mingguan Rutin: Setiap hari $dayName pukul " . sprintf('%02d:%02d', $hour, $min) . " WIB";

            case self::SCHEDULE_INTERVAL:
                $days = (int)($event['recurring_interval_days'] ?? 7);
                return "Interval Rutin: Setiap $days hari sekali setelah kemenangan sebelumnya";

            case self::SCHEDULE_MANUAL:
            default:
                return "Manual (Hanya dimulai saat Admin menekan tombol)";
        }
    }

    /**
     * Log an event message.
     */
    public static function addLog(string $type, int $allyId, int $uid, string $title, string $desc): void {
        $db = self::db();
        if (!$db) return;

        $now = time();
        $titleEsc = mysqli_real_escape_string($db, $title);
        $descEsc = mysqli_real_escape_string($db, $desc);
        $typeEsc = mysqli_real_escape_string($db, $type);

        @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bastion_logs` (`event_time`, `log_type`, `alliance_id`, `uid`, `title`, `description`)
            VALUES ($now, '$typeEsc', $allyId, $uid, '$titleEsc', '$descEsc')");

        // Batasi maksimal 100 log terbaru
        @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bastion_logs` WHERE id NOT IN (
            SELECT id FROM (SELECT id FROM `" . TB_PREFIX . "bastion_logs` ORDER BY id DESC LIMIT 100) AS tmp
        )");
    }

    /**
     * Retrieve recent logs for bastion.php view.
     */
    public static function getRecentLogs(int $limit = 15): array {
        self::ensureTables();
        $db = self::db();
        if (!$db) return [];

        $logs = [];
        $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_logs` ORDER BY id DESC LIMIT " . (int)$limit);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $logs[] = $row;
            }
        }
        return $logs;
    }

    /**
     * Process combat hook when an attack lands on (0|0).
     * Called from AutomationBattleResolution.php when $data['to'] == BASTION_WREF.
     */
    public static function handleBattleResult($data, $from, $to, $ownally, $targetally, $totaldead_att, $totalsend_att, $totaldead_def, $totalsend_alldef): void {
        $db = self::db();
        if (!$db) return;

        $event = self::getEvent();
        if ($event['status'] != self::STATUS_ACTIVE) {
            return;
        }

        $attackerUid = (int)($from['owner'] ?? 0);
        $attackerAlly = (int)($ownally ?? 0);
        $attackerName = addslashes($from['name'] ?? 'Penyerang');
        
        // Cek nama penyerang dari user jika ada
        $qU = @mysqli_query($db, "SELECT username, alliance FROM `" . TB_PREFIX . "users` WHERE id = $attackerUid LIMIT 1");
        if ($qU && $u = mysqli_fetch_assoc($qU)) {
            $attackerUsername = $u['username'];
            if (!$attackerAlly && (int)$u['alliance'] > 0) {
                $attackerAlly = (int)$u['alliance'];
            }
        } else {
            $attackerUsername = "Komandan #$attackerUid";
        }

        // Cek nama aliansi penyerang
        $attackerAllyName = "Tanpa Aliansi";
        if ($attackerAlly > 0) {
            $qA = @mysqli_query($db, "SELECT name, tag FROM `" . TB_PREFIX . "alidata` WHERE id = $attackerAlly LIMIT 1");
            if ($qA && $a = mysqli_fetch_assoc($qA)) {
                $attackerAllyName = $a['name'] . " [" . $a['tag'] . "]";
            }
        }

        // Cek apakah penyerang berhasil membersihkan seluruh pemain bertahan (Defender wiped out)
        $defenderWipedOut = ($totaldead_def >= $totalsend_alldef);
        $attackerHasSurvivors = ($totaldead_att < $totalsend_att);

        if ($defenderWipedOut && $attackerHasSurvivors) {
            // ATTACKER MENANG! KENDALI BENTENG BERPINDAH
            $oldAlly = (int)$event['holder_alliance'];
            $now = time();

            // Update status penguasa baru & reset timer 12 jam
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET
                `holder_alliance` = $attackerAlly,
                `holder_uid` = $attackerUid,
                `hold_start_time` = $now
                WHERE `id` = 1");

            // Update desa benteng di vdata agar kepemilikannya mencerminkan penguasa baru
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET `owner` = $attackerUid WHERE `wref` = " . self::BASTION_WREF);

            // Bersihkan sisa unit penjaga lama di tabel units
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "units` SET 
                `u1`=0, `u2`=0, `u3`=0, `u4`=0, `u5`=0, `u6`=0, `u7`=0, `u8`=0, `u9`=0, `u10`=0,
                `u11`=0, `u12`=0, `u13`=0, `u14`=0, `u15`=0, `u16`=0, `u17`=0, `u18`=0, `u19`=0, `u20`=0,
                `u21`=0, `u22`=0, `u23`=0, `u24`=0, `u25`=0, `u26`=0, `u27`=0, `u28`=0, `u29`=0, `u30`=0,
                `u31`=0, `u32`=0, `u33`=0, `u34`=0, `u35`=0, `u36`=0, `u37`=0, `u38`=0, `u39`=0, `u40`=0,
                `u41`=0, `u42`=0, `u43`=0, `u44`=0, `u45`=0, `u46`=0, `u47`=0, `u48`=0, `u49`=0, `u50`=0
                WHERE `vref` = " . self::BASTION_WREF);

            $title = "🏰 Benteng Kuno Telah Direbut!";
            $desc = "Pasukan dari $attackerAllyName yang dipimpin oleh $attackerUsername telah meratakan pertahanan sebelumnya dan resmi menduduki Benteng Kuno! Timer 12 jam dimulai kembali.";
            self::addLog('capture', $attackerAlly, $attackerUid, $title, $desc);
        } else {
            // DEFENDER BERHASIL MENANGKIS SERANGAN
            $currentHolderAlly = (int)$event['holder_alliance'];
            $title = "🛡️ Pertahanan Benteng Menangkis Serangan";
            $desc = "Serangan dari $attackerUsername ($attackerAllyName) berhasil dipukul mundur oleh garnisun penjaga Benteng Kuno!";
            self::addLog('repelled', $currentHolderAlly, $attackerUid, $title, $desc);
        }
    }

    /**
     * Recurring automation cycle: checks 12h victory condition and bot attack intervals.
     * Called during Automation / Village upkeep.
     */
    public static function processTick(): void {
        $db = self::db();
        if (!$db) return;

        $event = self::getEvent();
        if (empty($event)) return;

        $now = time();

        // 1. Cek apakah ada victory buff yang sudah kadaluarsa
        if ($event['status'] == self::STATUS_VICTORY && $event['buff_until'] > 0 && $now > $event['buff_until']) {
            $nextStatus = ((int)($event['schedule_mode'] ?? self::SCHEDULE_MANUAL) > self::SCHEDULE_MANUAL) ? self::STATUS_SCHEDULED : self::STATUS_INACTIVE;
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `status` = $nextStatus, `buff_until` = 0 WHERE `id` = 1");
            self::addLog('buff_expired', (int)$event['winner_alliance'], 0, 'Masa Berkah Kedaulatan Berakhir', 'Masa berkah kemenangan aliansi penguasa benteng telah tuntas.');
            return;
        }

        // Self-healing safeguard: jika status ACTIVE tetapi ada jadwal di masa depan yang belum tiba,
        // kembalikan status ke STATUS_SCHEDULED agar gerbang tetap tertutup sampai jadwal tiba.
        if ($event['status'] == self::STATUS_ACTIVE) {
            $schedMode = (int)($event['schedule_mode'] ?? self::SCHEDULE_MANUAL);
            if ($schedMode > self::SCHEDULE_MANUAL) {
                $futureStart = self::calculateNextStartTime(
                    $schedMode,
                    (int)($event['scheduled_start_time'] ?? 0),
                    (int)($event['recurring_day'] ?? 6),
                    (int)($event['recurring_hour'] ?? 19),
                    (int)($event['recurring_minute'] ?? 0),
                    (int)($event['recurring_interval_days'] ?? 7),
                    (int)($event['winner_time'] ?? 0)
                );
                if ($futureStart !== null && $futureStart > $now) {
                    @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET 
                        `status` = " . self::STATUS_SCHEDULED . ", 
                        `holder_alliance` = 0, 
                        `holder_uid` = 0, 
                        `hold_start_time` = 0 
                        WHERE `id` = 1");
                    self::ensureBastionVillage((int)($event['guardian_initial_troops'] ?: self::DEFAULT_GUARDIAN_TROOPS));
                    return;
                }
            }
        }

        // Cek Pembukaan Jadwal Otomatis jika status bukan ACTIVE
        if ($event['status'] != self::STATUS_ACTIVE) {
            $mode = (int)($event['schedule_mode'] ?? self::SCHEDULE_MANUAL);
            if ($mode > self::SCHEDULE_MANUAL) {
                $lastStart = (int)($event['last_scheduled_start'] ?? 0);
                $shouldStart = false;
                $reason = '';

                if ($mode === self::SCHEDULE_ONEOFF) {
                    $ts = (int)($event['scheduled_start_time'] ?? 0);
                    if ($ts > 0 && $now >= $ts && ($now - $lastStart > 3600)) {
                        $shouldStart = true;
                        $reason = "Jadwal khusus (" . date('d M Y H:i', $ts) . " WIB)";
                    }
                } elseif ($mode === self::SCHEDULE_WEEKLY) {
                    $targetDay = (int)($event['recurring_day'] ?? 6);
                    $targetHour = (int)($event['recurring_hour'] ?? 19);
                    $targetMin = (int)($event['recurring_minute'] ?? 0);

                    $curDay = (int)date('w', $now);
                    $curHour = (int)date('G', $now);
                    $curMin = (int)date('i', $now);

                    if ($curDay === $targetDay && $curHour === $targetHour && $curMin >= $targetMin) {
                        if (date('Y-m-d', $lastStart) !== date('Y-m-d', $now)) {
                            $shouldStart = true;
                            $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                            $reason = "Jadwal mingguan otomatis (Setiap " . ($dayNames[$targetDay] ?? 'Hari') . " pukul " . sprintf('%02d:%02d', $targetHour, $targetMin) . " WIB)";
                        }
                    }
                } elseif ($mode === self::SCHEDULE_INTERVAL) {
                    $intervalSec = max(1, (int)($event['recurring_interval_days'] ?? 7)) * 86400;
                    $winnerTime = (int)($event['winner_time'] ?? 0);
                    if ($winnerTime > 0 && ($now - $winnerTime) >= $intervalSec && ($now - $lastStart > 3600)) {
                        $shouldStart = true;
                        $reason = "Jadwal interval (" . round($intervalSec / 86400) . " hari setelah kemenangan sebelumnya)";
                    }
                }

                if ($shouldStart) {
                    $extraUpdate = ($mode === self::SCHEDULE_ONEOFF) ? ", `schedule_mode` = " . self::SCHEDULE_MANUAL . ", `scheduled_start_time` = 0" : "";
                    @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `last_scheduled_start` = $now $extraUpdate WHERE `id` = 1");
                    $guardians = (int)($event['guardian_initial_troops'] ?: self::DEFAULT_GUARDIAN_TROOPS);
                    $holdDuration = (int)($event['hold_duration'] ?: self::DEFAULT_HOLD_DURATION);
                    $botInterval = (int)($event['bot_attack_interval'] ?: self::DEFAULT_BOT_INTERVAL);

                    self::startEvent($guardians, $holdDuration, $botInterval);
                    self::addLog('schedule_start', 0, 0, 'Gerbang Benteng Kuno Dibuka Sesuai Jadwal!', 
                        "Event Benteng Kuno resmi dimulai secara otomatis! ($reason).");
                    return;
                }
            }

            return;
        }

        // Selalu pastikan lumbung gandum benteng penuh agar pasukan bertahan tidak pernah kelaparan
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET `crop` = 80000, `maxcrop` = 80000 WHERE `wref` = " . self::BASTION_WREF);

        // 2. CEK KEMENANGAN (Apakah penguasa saat ini sudah bertahan 12 jam?)
        $holderAlly = (int)$event['holder_alliance'];
        $holdStart = (int)$event['hold_start_time'];
        $duration = (int)($event['hold_duration'] ?: self::DEFAULT_HOLD_DURATION);

        if ($holderAlly > 0 && $holdStart > 0 && ($now - $holdStart) >= $duration) {
            self::declareVictory($holderAlly, (int)$event['holder_uid']);
            return;
        }

        // 3. MEKANIK SERANGAN / BANTUAN BOT (Natural & Santai)
        $botInterval = (int)($event['bot_attack_interval'] ?: self::DEFAULT_BOT_INTERVAL);
        $lastBotAttack = (int)$event['last_bot_attack'];

        if (($now - $lastBotAttack) >= $botInterval) {
            self::triggerBotAction($event);
        }
    }

    /**
     * Declare Victory for an alliance that reached the 12-hour threshold.
     */
    public static function declareVictory(int $allianceId, int $holderUid): void {
        $db = self::db();
        if (!$db) return;

        $now = time();
        $buffUntil = $now + self::DEFAULT_BUFF_DURATION;
        $event = self::getEvent();
        $holdDur = (int)($event['hold_duration'] ?: self::DEFAULT_HOLD_DURATION);

        // Ambil nama aliansi pemenang
        $qA = @mysqli_query($db, "SELECT name, tag FROM `" . TB_PREFIX . "alidata` WHERE id = $allianceId LIMIT 1");
        $allyName = "Aliansi #$allianceId";
        if ($qA && $a = mysqli_fetch_assoc($qA)) {
            $allyName = $a['name'] . " [" . $a['tag'] . "]";
        }

        // Ambil nama pemain champion
        $qU = @mysqli_query($db, "SELECT username FROM `" . TB_PREFIX . "users` WHERE id = $holderUid LIMIT 1");
        $uName = $qU && ($u = mysqli_fetch_assoc($qU)) ? $u['username'] : "Panglima Perang";

        // Update event ke status VICTORY
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET
            `status` = " . self::STATUS_VICTORY . ",
            `winner_alliance` = $allianceId,
            `winner_uid` = $holderUid,
            `winner_time` = $now,
            `buff_until` = $buffUntil,
            `event_end_time` = $now
            WHERE `id` = 1");

        // Catat kemenangan secara permanen ke dalam Hall of Fame
        self::recordHallOfFame($allianceId, $holderUid, $now, $holdDur, $buffUntil);

        // Bagikan reward resource ke seluruh anggota aliansi yang berpartisipasi
        self::distributeVictoryRewards($allianceId);

        $title = "🏆 KEMENANGAN MUTLAK! $allyName Menjadi Penguasa Benteng Kuno!";
        $desc = "Setelah bertahan selama 12 jam penuh di bawah kepemimpinan $uName, $allyName dinobatkan sebagai Penguasa Benteng Kuno! Seluruh anggota aliansi menerima Berkah Kedaulatan (+10% Produksi Sumber Daya, +15% Kecepatan Pasukan, +5% Pertahanan) selama 3 hari!";
        self::addLog('victory', $allianceId, $holderUid, $title, $desc);

        // Siarkan pengumuman kemenangan ke Global Chat jika tabel tersedia
        $chatCheck = @mysqli_query($db, "SHOW TABLES LIKE '" . TB_PREFIX . "chat_global'");
        if ($chatCheck && mysqli_num_rows($chatCheck) > 0) {
            $winMsg = "🏆 [KEMENANGAN BENTENG] Aliansi $allyName yang dipimpin oleh $uName telah memenangkan tahta Benteng Kuno (0|0) dan berhak atas Berkah Kedaulatan selama 3 hari!";
            $winMsgEsc = mysqli_real_escape_string($db, $winMsg);
            @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "chat_global` (`id_user`, `date`, `msg`, `type`, `poll_id`, `report_id`, `deleted`, `edited`, `updated_at`)
                VALUES (1, $now, '$winMsgEsc', 'text', 0, 0, 0, 0, $now)");
        }
    }

    /**
     * Record a winner into the Hall of Fame table.
     */
    public static function recordHallOfFame(int $allianceId, int $leaderUid, int $winTime, int $holdDuration, int $buffUntil, string $note = ''): bool {
        self::ensureTables();
        $db = self::db();
        if (!$db || $allianceId <= 0) return false;

        $allyName = "Aliansi #$allianceId";
        $allyTag = "";
        $qA = @mysqli_query($db, "SELECT name, tag FROM `" . TB_PREFIX . "alidata` WHERE id = $allianceId LIMIT 1");
        if ($qA && $a = mysqli_fetch_assoc($qA)) {
            $allyName = $a['name'];
            $allyTag = $a['tag'];
        }

        $uName = "Panglima";
        if ($leaderUid > 0) {
            $qU = @mysqli_query($db, "SELECT username FROM `" . TB_PREFIX . "users` WHERE id = $leaderUid LIMIT 1");
            if ($qU && $u = mysqli_fetch_assoc($qU)) {
                $uName = $u['username'];
            }
        }

        $maxSeason = 0;
        $qS = @mysqli_query($db, "SELECT MAX(season) AS max_s FROM `" . TB_PREFIX . "bastion_hall_of_fame`");
        if ($qS && $s = mysqli_fetch_assoc($qS)) {
            $maxSeason = (int)($s['max_s'] ?? 0);
        }
        $season = $maxSeason + 1;

        if (empty($note)) {
            $note = "Penguasa Benteng Musim $season";
        }

        $allyNameEsc = mysqli_real_escape_string($db, $allyName);
        $allyTagEsc = mysqli_real_escape_string($db, $allyTag);
        $uNameEsc = mysqli_real_escape_string($db, $uName);
        $noteEsc = mysqli_real_escape_string($db, $note);

        $q = "INSERT INTO `" . TB_PREFIX . "bastion_hall_of_fame`
            (`season`, `alliance_id`, `alliance_name`, `alliance_tag`, `leader_uid`, `leader_username`, `win_time`, `hold_duration`, `buff_until`, `note`)
            VALUES
            ($season, $allianceId, '$allyNameEsc', '$allyTagEsc', $leaderUid, '$uNameEsc', $winTime, $holdDuration, $buffUntil, '$noteEsc')";
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Get Hall of Fame list ordered by season descending.
     */
    public static function getHallOfFame(int $limit = 20): array {
        self::ensureTables();
        $db = self::db();
        if (!$db) return [];

        $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_hall_of_fame` ORDER BY `season` DESC, `win_time` DESC LIMIT $limit");
        $list = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $list[] = $row;
            }
        }
        return $list;
    }

    /**
     * Get the latest winner details (Reigning Champion).
     */
    public static function getLatestWinner(): ?array {
        self::ensureTables();
        $db = self::db();
        if (!$db) return null;

        $q = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_hall_of_fame` ORDER BY `season` DESC, `win_time` DESC LIMIT 1");
        if ($q && $row = mysqli_fetch_assoc($q)) {
            return $row;
        }

        $event = self::getEvent();
        if (!empty($event['winner_alliance']) && !empty($event['winner_time'])) {
            $wAlly = (int)$event['winner_alliance'];
            $wUid = (int)$event['winner_uid'];
            $aName = "Aliansi #$wAlly";
            $aTag = "";
            $qA = @mysqli_query($db, "SELECT name, tag FROM `" . TB_PREFIX . "alidata` WHERE id = $wAlly LIMIT 1");
            if ($qA && $a = mysqli_fetch_assoc($qA)) {
                $aName = $a['name'];
                $aTag = $a['tag'];
            }
            $uName = "Panglima";
            $qU = @mysqli_query($db, "SELECT username FROM `" . TB_PREFIX . "users` WHERE id = $wUid LIMIT 1");
            if ($qU && $u = mysqli_fetch_assoc($qU)) {
                $uName = $u['username'];
            }
            return [
                'id' => 0,
                'season' => 1,
                'alliance_id' => $wAlly,
                'alliance_name' => $aName,
                'alliance_tag' => $aTag,
                'leader_uid' => $wUid,
                'leader_username' => $uName,
                'win_time' => (int)$event['winner_time'],
                'hold_duration' => (int)($event['hold_duration'] ?: self::DEFAULT_HOLD_DURATION),
                'buff_until' => (int)($event['buff_until'] ?? 0),
                'note' => 'Juara Bertahan Benteng Kuno'
            ];
        }

        return null;
    }

    /**
     * Distribute victory spoils to members of the winning alliance.
     */
    private static function distributeVictoryRewards(int $allianceId): void {
        $db = self::db();
        if (!$db || $allianceId <= 0) return;

        // Ambil seluruh desa milik anggota aliansi pemenang
        $qUsers = @mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "users` WHERE alliance = $allianceId");
        if (!$qUsers) return;

        $uids = [];
        while ($u = mysqli_fetch_assoc($qUsers)) {
            $uids[] = (int)$u['id'];
        }

        if (!empty($uids)) {
            $uidList = implode(',', $uids);
            // Tambahkan 35.000 untuk masing-masing resource ke ibu kota/desa anggota
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET 
                `wood` = `wood` + 35000,
                `clay` = `clay` + 35000,
                `iron` = `iron` + 35000,
                `crop` = `crop` + 35000
                WHERE `owner` IN ($uidList)");
        }
    }

    /**
     * Trigger a natural periodic Bot action toward the Bastion.
     */
    public static function triggerBotAction(array $event): void {
        global $database;
        $db = self::db();
        if (!$db) return;

        $now = time();
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `last_bot_attack` = $now WHERE `id` = 1");

        $botFaction = self::ensureBotFaction();
        $botUid = $botFaction['uid'];
        $botAlly = $botFaction['ally'];
        $currentHolderAlly = (int)($event['holder_alliance'] ?? 0);

        // Jika saat ini aliansi BOT yang sedang memegang benteng: kirim bantuan pasukan (Reinforce)
        if ($currentHolderAlly > 0 && $currentHolderAlly === $botAlly) {
            // Tambahkan 20 prajurit bantuan penjaga langsung ke benteng
            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "units` SET 
                `u41` = `u41` + 10,
                `u42` = `u42` + 8,
                `u46` = `u46` + 2
                WHERE `vref` = " . self::BASTION_WREF);
            self::addLog('bot_reinforce', $botAlly, $botUid, '🛡️ Bala Bantuan Penjaga Kuno Tiba', 
                'Aliansi Penjaga Kuno memperkuat pertahanan benteng mereka dengan 20 prajurit tambahan.');
            return;
        }

        // Jika aliansi pemain yang sedang memegang benteng: kirim regu serang penguji (Trial Wave)
        // Cari desa bot terdekat (misal milik UID 10, UID 2, atau buat pergerakan pasukan natural)
        $botVillages = @mysqli_query($db, "SELECT wref, name FROM `" . TB_PREFIX . "vdata` WHERE owner IN ($botUid, 10, 2) AND wref != " . self::BASTION_WREF . " LIMIT 1");
        $fromWref = 0;
        $fromName = "Markas Pasukan Kuno";
        if ($botVillages && $bv = mysqli_fetch_assoc($botVillages)) {
            $fromWref = (int)$bv['wref'];
            $fromName = $bv['name'];
        }

        if ($fromWref <= 0) {
            // Fallback titik awal di koordinat dekat benteng misal (10|10)
            $fallbackCoor = @mysqli_query($db, "SELECT id, name FROM `" . TB_PREFIX . "wdata` WHERE x BETWEEN 5 AND 15 AND y BETWEEN 5 AND 15 LIMIT 1");
            if ($fallbackCoor && $fc = mysqli_fetch_assoc($fallbackCoor)) {
                $fromWref = (int)$fc['id'];
            } else {
                $fromWref = 20202; // Tetangga sebelah (1|0)
            }
        }

        // Buat serangan bot natural ke (0|0)
        // Regu penguji kecil (total 25-35 unit: 15 Pikeman, 10 Axerider, 5 Kavaleri)
        if ($database) {
            $t1 = 15; $t2 = 10; $t3 = 5; $t4 = 0; $t5 = 0; $t6 = 0; $t7 = 0; $t8 = 0; $t9 = 0; $t10 = 0; $t11 = 0;
            $duration = 1800; // 30 menit perjalanan
            $endtime = $now + $duration;

            $ref = $database->addAttack($fromWref, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
            if ($ref) {
                $database->addMovement(3, $fromWref, self::BASTION_WREF, $ref, $now, $endtime);
                self::addLog('bot_attack', $botAlly, $botUid, '⚔️ Regu Pasukan Kuno Bergerak ke Benteng', 
                    "Pasukan Kuno telah memberangkatkan 30 prajurit dari $fromName menuju Benteng Kuno untuk menguji kesiapan garnisun pertahanan!");
            }
        }
    }

    /**
     * Check if a village or user has the Bastion Winner Production Buff (+10%).
     */
    public static function getProductionMultiplier(int $wid, string $resType = ''): float {
        $db = self::db();
        if (!$db) return 1.0;

        $event = self::getEvent();
        if ($event['status'] != self::STATUS_VICTORY || empty($event['winner_alliance']) || time() > $event['buff_until']) {
            return 1.0;
        }

        // Ambil pemilik desa dan aliansinya
        $qV = @mysqli_query($db, "SELECT u.alliance FROM `" . TB_PREFIX . "vdata` v JOIN `" . TB_PREFIX . "users` u ON u.id = v.owner WHERE v.wref = $wid LIMIT 1");
        if ($qV && $row = mysqli_fetch_assoc($qV)) {
            if ((int)$row['alliance'] > 0 && (int)$row['alliance'] === (int)$event['winner_alliance']) {
                return 1.10; // +10% Resource Production
            }
        }
        return 1.0;
    }

    /**
     * Check if a user has the Bastion Winner Speed Buff (+15%).
     */
    public static function hasSpeedBuff(int $uid): bool {
        $db = self::db();
        if (!$db || $uid <= 0) return false;

        $event = self::getEvent();
        if ($event['status'] != self::STATUS_VICTORY || empty($event['winner_alliance']) || time() > $event['buff_until']) {
            return false;
        }

        $qU = @mysqli_query($db, "SELECT alliance FROM `" . TB_PREFIX . "users` WHERE id = $uid LIMIT 1");
        if ($qU && $u = mysqli_fetch_assoc($qU)) {
            return ((int)$u['alliance'] > 0 && (int)$u['alliance'] === (int)$event['winner_alliance']);
        }
        return false;
    }

    /**
     * Get garrison troop count currently at the Bastion.
     */
    public static function getBastionGarrison(): array {
        global $database;
        $db = self::db();
        if (!$db) return ['total' => 0, 'own' => 0, 'enforce' => 0, 'units' => []];

        $wref = self::BASTION_WREF;
        $ownUnits = $database ? $database->getUnit($wref, false) : [];
        $totalOwn = 0;
        if (is_array($ownUnits)) {
            for ($i = 1; $i <= 50; $i++) {
                $totalOwn += (int)($ownUnits['u' . $i] ?? 0);
            }
        }

        // Hitung bala bantuan di tabel enforcement
        $totalEnforce = 0;
        $qEnf = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE vref = $wref");
        if ($qEnf) {
            while ($row = mysqli_fetch_assoc($qEnf)) {
                for ($i = 1; $i <= 50; $i++) {
                    $totalEnforce += (int)($row['u' . $i] ?? 0);
                }
            }
        }

        return [
            'total' => ($totalOwn + $totalEnforce),
            'own' => $totalOwn,
            'enforce' => $totalEnforce,
            'units' => $ownUnits
        ];
    }

    /**
     * Get aggregated breakdown of all stationed troops at Bastion (own units + reinforcements).
     * Returns ['total' => int, 'breakdown' => [unit_id => count], 'hero' => int]
     */
    public static function getAllStationedTroops(): array {
        global $database;
        $db = self::db();
        $breakdown = [];
        $total = 0;
        $heroTotal = 0;
        if (!$db) return ['total' => 0, 'breakdown' => [], 'hero' => 0];

        $wref = self::BASTION_WREF;
        $ownUnits = $database ? $database->getUnit($wref, false) : [];
        if (is_array($ownUnits)) {
            for ($i = 1; $i <= 60; $i++) {
                $c = (int)($ownUnits['u' . $i] ?? 0);
                if ($c > 0) {
                    $breakdown[$i] = ($breakdown[$i] ?? 0) + $c;
                    $total += $c;
                }
            }
            if (!empty($ownUnits['hero'])) {
                $heroTotal += (int)$ownUnits['hero'];
            }
        }

        // Bala bantuan dari aliansi pemain
        $qEnf = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE vref = $wref");
        if ($qEnf) {
            while ($row = mysqli_fetch_assoc($qEnf)) {
                for ($i = 1; $i <= 60; $i++) {
                    $c = (int)($row['u' . $i] ?? 0);
                    if ($c > 0) {
                        $breakdown[$i] = ($breakdown[$i] ?? 0) + $c;
                        $total += $c;
                    }
                }
                if (!empty($row['hero'])) {
                    $heroTotal += (int)$row['hero'];
                }
            }
        }

        return [
            'total' => $total,
            'breakdown' => $breakdown,
            'hero' => $heroTotal
        ];
    }

    /**
     * Broadcast notification to all players via In-Game Message (IGM) and Global Chat
     * when the Ancient Bastion gates are opened.
     */
    public static function broadcastEventStart(int $guardianTroops, int $holdDuration): void {
        $db = self::db();
        if (!$db) return;

        $now = time();
        $holdHours = round($holdDuration / 3600);
        if ($holdHours <= 0) $holdHours = 12;

        $topic = "🏰 [EVENT] Gerbang Benteng Kuno (0|0) Resmi Terbuka!";
        $topicEsc = mysqli_real_escape_string($db, '<span style="color:#b91c1c; font-weight:bold;">' . $topic . '</span>');

        $message = "[message]Panglima dan Penguasa Kerajaan yang Terhormat,\n\n"
            . "Kabar gempar mengguncang seantero kerajaan! Gerbang [b]Benteng Kuno (The Ancient Bastion)[/b] di pusat peta koordinat [b](0|0)[/b] telah resmi terbuka untuk diperebutkan!\n\n"
            . "Benteng kuno saat ini dijaga ketat oleh garnisun berkekuatan [b]" . number_format($guardianTroops) . " Prajurit Penjaga Kuno[/b]. Aliansi mana pun yang berhasil menembus pertahanan benteng dan mempertahankannya selama [b]" . $holdHours . " jam berturut-turut[/b] akan dinobatkan sebagai Penguasa Benteng Kuno!\n\n"
            . "[b]Hadiah Kemenangan Aliansi (Berkah Kedaulatan selama 3 Hari):[/b]\n"
            . "• [b]+10%[/b] Produksi Seluruh Sumber Daya (Kayu, Liat, Besi, Gandum)\n"
            . "• [b]+15%[/b] Kecepatan Pergerakan Seluruh Pasukan Aliansi\n"
            . "• [b]+5%[/b] Pertahanan Seluruh Desa Anggota Aliansi\n\n"
            . "Buka Balai Benteng Kuno di menu navigasi atau periksa koordinat [b](0|0)[/b] pada peta untuk memantau pertempuran dan mengerahkan pasukan aliansi Anda!\n\n"
            . "Semoga keberanian dan strategi membawa aliansi Anda menuju puncak kejayaan![/message]";

        $messageEsc = mysqli_real_escape_string($db, $message);

        // 1. Kirim In-Game Message (IGM) ke semua pemain riil
        $qUsers = @mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "users` 
            WHERE id > 5 AND access > 0 
            AND username NOT IN ('Penjaga Kuno', 'Gembong Bandit', 'Klan Assassin')
            ORDER BY id ASC");

        if ($qUsers && mysqli_num_rows($qUsers) > 0) {
            $rows = [];
            while ($u = mysqli_fetch_assoc($qUsers)) {
                $uid = (int)$u['id'];
                $rows[] = "($uid, 1, '$topicEsc', '$messageEsc', 0, 0, 0, $now, 0, 0, 0, 0, 0, 0)";
            }

            if (!empty($rows)) {
                $chunks = array_chunk($rows, 50);
                foreach ($chunks as $chunk) {
                    $sql = "INSERT INTO `" . TB_PREFIX . "mdata` 
                        (`target`, `owner`, `topic`, `message`, `viewed`, `archived`, `send`, `time`, `deltarget`, `delowner`, `alliance`, `player`, `coor`, `report`)
                        VALUES " . implode(',', $chunk);
                    @mysqli_query($db, $sql);
                }
            }
        }

        // 2. Siarkan pesan ke Global Chat jika tabel chat_global tersedia
        $chatCheck = @mysqli_query($db, "SHOW TABLES LIKE '" . TB_PREFIX . "chat_global'");
        if ($chatCheck && mysqli_num_rows($chatCheck) > 0) {
            $chatMsg = "🏰 [PENGUMUMAN EVENT] Gerbang Benteng Kuno di (0|0) telah resmi terbuka! Perebutan King of the Hill dimulai! Segera atur pasukan aliansi Anda!";
            $chatMsgEsc = mysqli_real_escape_string($db, $chatMsg);
            @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "chat_global` (`id_user`, `date`, `msg`, `type`, `poll_id`, `report_id`, `deleted`, `edited`, `updated_at`)
                VALUES (1, $now, '$chatMsgEsc', 'text', 0, 0, 0, 0, $now)");
        }

        self::addLog('broadcast', 0, 0, 'Pemberitahuan Event Terkirim ke Seluruh Pemain', 
            'Pesan pembukaan Benteng Kuno telah disiarkan ke seluruh pemain melalui Pesan Masuk (IGM) dan Chat Global.');
    }
}

