<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : DailyQuests.php                                           ##
##  Purpose        : Daily Milestone Quests & 50 Silver Reward Chest           ##
##  Author         : Antigravity AI & TravianZ                                 ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class DailyQuests
{
    const REWARD_SILVER = 50;

    const TARGETS = [
        'adventure' => 1,  // 1x petualangan hero
        'train'     => 20, // 20x latih pasukan
        'build'     => 2,  // 2x upgrade bangunan/ladang
        'attack'    => 1,  // 1x kirim serangan / raid
    ];

    private static function db()
    {
        global $database;
        return $database->dblink ?? null;
    }

    /**
     * Self-healing database schema for daily quests tracking.
     */
    public static function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = self::db();
        if (!$db) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "daily_quests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uid` int(11) NOT NULL,
            `quest_date` date NOT NULL,
            `p_adventure` tinyint(1) NOT NULL DEFAULT 0,
            `count_adventure` int(11) NOT NULL DEFAULT 0,
            `p_train` tinyint(1) NOT NULL DEFAULT 0,
            `count_train` int(11) NOT NULL DEFAULT 0,
            `p_build` tinyint(1) NOT NULL DEFAULT 0,
            `count_build` int(11) NOT NULL DEFAULT 0,
            `p_attack` tinyint(1) NOT NULL DEFAULT 0,
            `count_attack` int(11) NOT NULL DEFAULT 0,
            `claimed` tinyint(1) NOT NULL DEFAULT 0,
            `claimed_time` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uid_date` (`uid`, `quest_date`),
            KEY `quest_date` (`quest_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        @mysqli_query($db, $sql);
        $ensured = true;
    }

    /**
     * Fetch daily progress for a user.
     */
    public static function getDailyProgress(int $uid, ?string $date = null): array
    {
        self::ensureTable();
        $db = self::db();
        $uid = (int)$uid;
        $targetDate = $date ?? date('Y-m-d');

        $default = [
            'uid'             => $uid,
            'quest_date'      => $targetDate,
            'p_adventure'     => 0,
            'count_adventure' => 0,
            'p_train'         => 0,
            'count_train'     => 0,
            'p_build'         => 0,
            'count_build'     => 0,
            'p_attack'        => 0,
            'count_attack'    => 0,
            'claimed'         => false,
            'claimed_time'    => 0,
            'points'          => 0,
            'can_claim'       => false,
        ];

        if (!$db || $uid <= 0) {
            return $default;
        }

        $stmt = @mysqli_prepare($db, "SELECT p_adventure, count_adventure, p_train, count_train, p_build, count_build, p_attack, count_attack, claimed, claimed_time 
            FROM `" . TB_PREFIX . "daily_quests` WHERE uid = ? AND quest_date = ? LIMIT 1");
        if (!$stmt) {
            return $default;
        }

        mysqli_stmt_bind_param($stmt, 'is', $uid, $targetDate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return $default;
        }

        $pAdv = (int)$row['p_adventure'];
        $pTrn = (int)$row['p_train'];
        $pBld = (int)$row['p_build'];
        $pAtk = (int)$row['p_attack'];
        $claimed = ((int)$row['claimed'] === 1);

        $points = ($pAdv * 25) + ($pTrn * 25) + ($pBld * 25) + ($pAtk * 25);
        $canClaim = ($points >= 100 && !$claimed);

        return [
            'uid'             => $uid,
            'quest_date'      => $targetDate,
            'p_adventure'     => $pAdv,
            'count_adventure' => (int)$row['count_adventure'],
            'p_train'         => $pTrn,
            'count_train'     => (int)$row['count_train'],
            'p_build'         => $pBld,
            'count_build'     => (int)$row['count_build'],
            'p_attack'        => $pAtk,
            'count_attack'    => (int)$row['count_attack'],
            'claimed'         => $claimed,
            'claimed_time'    => (int)$row['claimed_time'],
            'points'          => $points,
            'can_claim'       => $canClaim,
        ];
    }

    /**
     * Record player progress towards daily quests.
     */
    public static function recordProgress(int $uid, string $type, int $amount = 1): bool
    {
        self::ensureTable();
        $db = self::db();
        $uid = (int)$uid;
        $amount = max(1, (int)$amount);
        $today = date('Y-m-d');

        if (!$db || $uid <= 0 || !isset(self::TARGETS[$type])) {
            return false;
        }

        $req = self::TARGETS[$type];
        $countCol = 'count_' . $type;
        $pCol = 'p_' . $type;

        // Ensure row exists for today
        @mysqli_query($db, "INSERT IGNORE INTO `" . TB_PREFIX . "daily_quests` (`uid`, `quest_date`) VALUES ($uid, '$today')");

        // Increment count and evaluate milestone
        $q = "UPDATE `" . TB_PREFIX . "daily_quests` 
              SET `$countCol` = `$countCol` + $amount,
                  `$pCol` = IF(`$countCol` >= $req, 1, `$pCol`)
              WHERE `uid` = $uid AND `quest_date` = '$today'";

        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Claim daily milestone chest reward (+50 Silver).
     */
    public static function claimReward(int $uid): array
    {
        self::ensureTable();
        $db = self::db();
        $uid = (int)$uid;
        $today = date('Y-m-d');
        $time = time();

        if (!$db || $uid <= 0) {
            return ['success' => false, 'message' => 'User tidak valid.'];
        }

        $prog = self::getDailyProgress($uid, $today);
        if ($prog['points'] < 100) {
            return ['success' => false, 'message' => 'Misi harian belum lengkap (Poin saat ini: ' . $prog['points'] . '/100).'];
        }

        if ($prog['claimed']) {
            return ['success' => false, 'message' => 'Peti Harian sudah diklaim untuk hari ini! Kembali lagi besok.'];
        }

        // Atomically set claimed
        $stmt = @mysqli_prepare($db, "UPDATE `" . TB_PREFIX . "daily_quests` SET claimed = 1, claimed_time = ? WHERE uid = ? AND quest_date = ? AND claimed = 0");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Gagal memproses klaim hadiah.'];
        }
        mysqli_stmt_bind_param($stmt, 'iis', $time, $uid, $today);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected <= 0) {
            return ['success' => false, 'message' => 'Hadiah sudah diklaim sebelumnya.'];
        }

        // Add 50 Silver to Hero
        if (!class_exists('BlackMarket')) {
            require_once __DIR__ . '/BlackMarket.php';
        }
        BlackMarket::addSilver($uid, self::REWARD_SILVER);

        // Also add 50 CP bonus as a daily reward
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET cp = cp + 50 WHERE id = $uid");

        // Log action
        BlackMarket::logAction($uid, 0, 'daily_quest_chest', "Klaim Peti Harian -> +" . self::REWARD_SILVER . " Silver, +50 CP", 0);

        return [
            'success' => true,
            'message' => "🎉 Selamat! Peti Perak Harian berhasil dibuka. Anda menerima +" . self::REWARD_SILVER . " Koin Silver dan +50 Culture Points!"
        ];
    }
}
