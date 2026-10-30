<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : OasisBounty.php                                           ##
##  Purpose        : Oasis Nature Hunting Silver Bounty System with Daily Cap  ##
##  Author         : Antigravity AI & TravianZ                                 ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class OasisBounty
{
    const DAILY_CAP = 150;

    /**
     * Silver reward per killed nature beast.
     */
    const BOUNTY_RATES = [
        31 => 1, // Rat / Tikus
        32 => 1, // Spider / Laba-laba
        33 => 1, // Snake / Ular
        34 => 1, // Bat / Kelelawar
        35 => 2, // Wild Boar / Babi Hutan
        36 => 2, // Wolf / Serigala
        37 => 4, // Bear / Beruang
        38 => 4, // Crocodile / Buaya
        39 => 4, // Tiger / Harimau
        40 => 8, // Elephant / Gajah
    ];

    private static function db()
    {
        global $database;
        return $database->dblink ?? null;
    }

    /**
     * Self-healing table structure for daily oasis bounty tracking.
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

        $sql = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "oasis_bounty_daily` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uid` int(11) NOT NULL,
            `bounty_date` date NOT NULL,
            `silver_earned` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uid_date` (`uid`, `bounty_date`),
            KEY `bounty_date` (`bounty_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        @mysqli_query($db, $sql);
        $ensured = true;
    }

    /**
     * Calculate potential Silver bounty from killed nature units.
     *
     * @param array $killedNatureUnits Map of unitId (31-40) => count killed
     * @return int
     */
    public static function calculateBounty(array $killedNatureUnits): int
    {
        $bounty = 0;
        foreach ($killedNatureUnits as $unitId => $count) {
            $uId = (int)$unitId;
            $qty = max(0, (int)$count);
            if ($qty > 0 && isset(self::BOUNTY_RATES[$uId])) {
                $bounty += (self::BOUNTY_RATES[$uId] * $qty);
            }
        }
        return $bounty;
    }

    /**
     * Get Silver earned today by player.
     */
    public static function getDailyEarned(int $uid, ?string $date = null): int
    {
        self::ensureTable();
        $db = self::db();
        if (!$db || $uid <= 0) {
            return 0;
        }

        $targetDate = $date ?? date('Y-m-d');
        $stmt = @mysqli_prepare($db, "SELECT silver_earned FROM `" . TB_PREFIX . "oasis_bounty_daily` WHERE uid = ? AND bounty_date = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'is', $uid, $targetDate);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $earned);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return ($found && !is_null($earned)) ? (int)$earned : 0;
    }

    /**
     * Award Silver bounty to player's hero, respecting the daily cap.
     *
     * @param int   $uid               Player UID
     * @param array $killedNatureUnits Map of killed animals [31 => count, ...]
     * @param int   $wref              Oasis target village reference
     * @return array
     */
    public static function awardBounty(int $uid, array $killedNatureUnits, int $wref = 0): array
    {
        self::ensureTable();
        $db = self::db();
        $uid = (int)$uid;
        $today = date('Y-m-d');

        $rawBounty = self::calculateBounty($killedNatureUnits);
        $totalAnimals = 0;
        foreach ($killedNatureUnits as $uId => $cnt) {
            if (isset(self::BOUNTY_RATES[(int)$uId])) {
                $totalAnimals += max(0, (int)$cnt);
            }
        }

        if ($uid <= 0 || $rawBounty <= 0) {
            return [
                'awarded'        => 0,
                'potential'      => $rawBounty,
                'daily_total'    => self::getDailyEarned($uid, $today),
                'daily_cap'      => self::DAILY_CAP,
                'capped'         => false,
                'animals_killed' => $totalAnimals
            ];
        }

        $currentDaily = self::getDailyEarned($uid, $today);
        $remainingQuota = max(0, self::DAILY_CAP - $currentDaily);

        $actualAwarded = min($rawBounty, $remainingQuota);
        $newDailyTotal = $currentDaily + $actualAwarded;
        $isCapped = ($rawBounty > $actualAwarded);

        if ($actualAwarded > 0) {
            // Update daily tracking table
            $stmt = @mysqli_prepare($db, "INSERT INTO `" . TB_PREFIX . "oasis_bounty_daily` (`uid`, `bounty_date`, `silver_earned`) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE `silver_earned` = `silver_earned` + ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isii', $uid, $today, $actualAwarded, $actualAwarded);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            // Award Silver into Hero balance
            if (!class_exists('BlackMarket')) {
                require_once __DIR__ . '/BlackMarket.php';
            }
            BlackMarket::addSilver($uid, $actualAwarded);

            // Log action in blackmarket log
            $desc = "Bounty Berburu Oasis (Wref: $wref) -> Menumpas $totalAnimals hewan buas. +$actualAwarded Silver (Hari ini: $newDailyTotal/" . self::DAILY_CAP . ").";
            BlackMarket::logAction($uid, $wref, 'oasis_bounty', $desc, 0);
        }

        return [
            'awarded'        => $actualAwarded,
            'potential'      => $rawBounty,
            'daily_total'    => $newDailyTotal,
            'daily_cap'      => self::DAILY_CAP,
            'capped'         => $isCapped,
            'animals_killed' => $totalAnimals
        ];
    }
}
