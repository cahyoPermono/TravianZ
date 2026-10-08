<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Plague.php                                                ##
##  Type           : Epidemic & Village Health Engine for TravianZ             ##
##  Purpose        : Dynamic disease, contamination, quarantine, and herbal     ##
##                   medicine system with tactical crisis & cure mechanics     ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Plague {

    const PLAGUE_NONE       = 0;
    const PLAGUE_CHOLERA    = 1; // Wabah Kolera (Krisis Pangan & Kelaparan)
    const PLAGUE_MALARIA    = 2; // Demam Rawa & Gigitan Nyamuk (Tropis Rawa)
    const PLAGUE_PESTILENCE = 3; // Pestilensi Medan Tempur (Sisa Perang Berdarah)

    /**
     * Database connection resolution.
     */
    private static function db() {
        if (isset($GLOBALS['database']) && isset($GLOBALS['database']->dblink)) {
            return $GLOBALS['database']->dblink;
        }
        if (isset($GLOBALS['link']) && $GLOBALS['link']) {
            return $GLOBALS['link'];
        }
        return null;
    }

    /**
     * Create plague table if not existing.
     */
    public static function ensureTable(): void {
        $db = self::db();
        if (!$db) return;

        $q = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "village_plague` (
            `vref` int(11) NOT NULL,
            `plague_type` tinyint(2) NOT NULL DEFAULT 0,
            `severity` tinyint(2) NOT NULL DEFAULT 1,
            `infected_at` int(11) NOT NULL DEFAULT 0,
            `cure_time` int(11) NOT NULL DEFAULT 0,
            `quarantine` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`vref`),
            KEY `cure_time` (`cure_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q);
    }

    /**
     * Get active plague status for a village.
     */
    public static function getVillagePlague(int $vref): ?array {
        if (defined('PLAGUE_SYSTEM_ENABLED') && !PLAGUE_SYSTEM_ENABLED) {
            return null;
        }

        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return null;

        $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "village_plague` WHERE `vref` = " . (int)$vref . " LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            if ((int)$row['plague_type'] > 0) {
                // If cure time passed, automatically heal
                if ((int)$row['cure_time'] > 0 && (int)$row['cure_time'] <= time()) {
                    self::cureVillage($vref);
                    return null;
                }
                return $row;
            }
        }
        return null;
    }

    /**
     * Check if a village is infected.
     */
    public static function isVillageInfected(int $vref): bool {
        $plague = self::getVillagePlague($vref);
        return !empty($plague) && (int)($plague['plague_type'] ?? 0) > 0;
    }

    /**
     * Infect a village with a specific plague type.
     */
    public static function infectVillage(int $vref, int $plagueType, int $severity = 1, int $duration = 14400): bool {
        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return false;

        $now = time();
        $cureTime = $now + $duration;

        $q = "INSERT INTO `" . TB_PREFIX . "village_plague` (`vref`, `plague_type`, `severity`, `infected_at`, `cure_time`, `quarantine`)
              VALUES ($vref, $plagueType, $severity, $now, $cureTime, 0)
              ON DUPLICATE KEY UPDATE `plague_type` = $plagueType, `severity` = $severity, `infected_at` = $now, `cure_time` = $cureTime;";
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Cure a village immediately.
     */
    public static function cureVillage(int $vref): bool {
        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return false;

        $q = "UPDATE `" . TB_PREFIX . "village_plague` SET `plague_type` = 0, `cure_time` = 0 WHERE `vref` = " . (int)$vref;
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Toggle quarantine status for a village.
     */
    public static function toggleQuarantine(int $vref): bool {
        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return false;

        $q = "UPDATE `" . TB_PREFIX . "village_plague` SET `quarantine` = 1 - `quarantine` WHERE `vref` = " . (int)$vref;
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Apply Traditional Herbal Medicine (Jamu Nusantara / Tabib).
     * Costs resources and cures or substantially cuts plague duration.
     */
    public static function applyTraditionalMedicine(int $vref, int $uid): array {
        global $database;
        self::ensureTable();
        $db = self::db();

        $plague = self::getVillagePlague($vref);
        if (!$plague) {
            return ['success' => false, 'message' => 'Desa ini dalam kondisi sehat, tidak terjangkit wabah!'];
        }

        // Get user tribe to check for Nusantara bonus
        $tribe = (int)$database->getUserField($uid, 'tribe', 0);
        $cost = ($tribe === 10) ? 75 : 150; // Nusantara gets 50% discount on herbal concoctions!

        // Check if village has enough resources
        $wood = $database->getWoodAvailable($vref);
        $clay = $database->getClayAvailable($vref);
        $iron = $database->getIronAvailable($vref);
        $crop = $database->getCropAvailable($vref);

        if ($wood < $cost || $clay < $cost || $iron < $cost || $crop < $cost) {
            return [
                'success' => false,
                'message' => 'Sumber daya tidak mencukupi untuk meracik Jamu Tradisional! Butuh masing-masing ' . $cost . ' Kayu, Tanah Liat, Besi, dan Gandum.'
            ];
        }

        // Deduct resources
        $database->modifyResource($vref, $cost, $cost, $cost, $cost, 0);

        // Instantly cure the village
        self::cureVillage($vref);

        return [
            'success' => true,
            'message' => 'Jamu Tradisional & Ramuan Herbal berhasil diracik! Warga desa sembuh seketika dari wabah penyakit.'
        ];
    }

    /**
     * Metadata info for plague types.
     */
    public static function getPlagueInfo(?int $type = null): array {
        $info = [
            self::PLAGUE_CHOLERA => [
                'name' => 'Wabah Kolera & Krisis Pangan',
                'name_en' => 'Famine Cholera Outbreak',
                'icon' => '☠️',
                'color' => '#cf1322',
                'bg' => '#fff1f0',
                'border' => '#ff4d4f',
                'desc' => 'Kelangkaan gandum dan sanitasi air yang buruk memicu wabah kolera. Penduduk lemas dan ladang terbengkalai.',
                'effects' => [
                    '📉 Produksi Semua Sumber Daya -20%',
                    '⚔️ Daya Serang Pasukan Desa -15%',
                    '🛡️ Daya Tahan Pasukan Melemah'
                ]
            ],
            self::PLAGUE_MALARIA => [
                'name' => 'Demam Rawa & Gigitan Nyamuk',
                'name_en' => 'Swamp Malaria Fever',
                'icon' => '🦟',
                'color' => '#d46b08',
                'bg' => '#fff7e6',
                'border' => '#ffa940',
                'desc' => 'Genangan air dan rawa tropis mendatangkan jutaan nyamuk pembawa demam tinggi. Pasukan dan pedagang menggigil tak berdaya.',
                'effects' => [
                    '🏃 Kecepatan Pasukan -25%',
                    '🌾 Produksi Gandum -15%',
                    '🐫 Kecepatan Pedagang -20%'
                ]
            ],
            self::PLAGUE_PESTILENCE => [
                'name' => 'Pestilensi Medan Tempur',
                'name_en' => 'Battlefield Pestilence',
                'icon' => '💀',
                'color' => '#722ed1',
                'bg' => '#f9f0ff',
                'border' => '#b37feb',
                'desc' => 'Bangkai pertempuran sengit di luar benteng membusuk dan menyebarkan hawa penyakit beracun ke seluruh pemukiman.',
                'effects' => [
                    '👥 Risiko Berkurangnya Populasi Desa (-1 per 6 jam)',
                    '⏳ Waktu Latih Pasukan di Barak/Kandang Melambat +25%',
                    '📉 Produksi Kayu & Besi -10%'
                ]
            ]
        ];

        if ($type !== null) {
            return $info[$type] ?? [
                'name' => 'Kondisi Sehat',
                'icon' => '💚',
                'desc' => 'Desa dalam kondisi sehat dan bugar.',
                'effects' => []
            ];
        }

        return $info;
    }

    /**
     * Multiplier for resource production in Village::calculateProduction.
     */
    public static function getProductionMultiplier(int $vref, string $resType): float {
        $plague = self::getVillagePlague($vref);
        if (!$plague) return 1.0;

        $type = (int)$plague['plague_type'];
        switch ($type) {
            case self::PLAGUE_CHOLERA:
                return 0.80; // -20% across all resources

            case self::PLAGUE_MALARIA:
                if ($resType === 'crop') return 0.85;
                return 0.95;

            case self::PLAGUE_PESTILENCE:
                if ($resType === 'wood' || $resType === 'iron') return 0.90;
                return 0.95;

            default:
                return 1.0;
        }
    }

    /**
     * Multiplier for troop speed if village is infected.
     */
    public static function getSpeedMultiplier(int $vref): float {
        $plague = self::getVillagePlague($vref);
        if (!$plague) return 1.0;

        if ((int)$plague['plague_type'] === self::PLAGUE_MALARIA) {
            return 0.75; // -25% speed due to fever
        }
        return 1.0;
    }

    /**
     * Multiplier for attack/defense efficiency if infected.
     */
    public static function getCombatMultiplier(int $vref): float {
        $plague = self::getVillagePlague($vref);
        if (!$plague) return 1.0;

        if ((int)$plague['plague_type'] === self::PLAGUE_CHOLERA) {
            return 0.85; // -15% combat power
        }
        return 1.0;
    }

    /**
     * Automation tick hook.
     * Cleans up expired plagues and evaluates starvation risk.
     */
    public static function tick(): void {
        if (defined('PLAGUE_SYSTEM_ENABLED') && !PLAGUE_SYSTEM_ENABLED) {
            return;
        }

        self::ensureTable();
        $db = self::db();
        if (!$db) return;

        $now = time();

        // 1. Clean up expired infections
        @mysqli_query($db, "UPDATE `" . TB_PREFIX . "village_plague` SET `plague_type` = 0 WHERE `cure_time` > 0 AND `cure_time` <= $now");

        // 2. Small chance (5%) for critically starving villages (crop < 5) to develop cholera
        $starvingQ = "SELECT v.wref, v.crop, v.pop FROM `" . TB_PREFIX . "vdata` v 
                      LEFT JOIN `" . TB_PREFIX . "village_plague` p ON v.wref = p.vref
                      WHERE v.crop < 5 AND v.pop > 30 AND (p.plague_type IS NULL OR p.plague_type = 0)
                      LIMIT 20";
        $starvingRes = @mysqli_query($db, $starvingQ);
        if ($starvingRes) {
            while ($v = mysqli_fetch_assoc($starvingRes)) {
                if (mt_rand(1, 100) <= 5) {
                    self::infectVillage((int)$v['wref'], self::PLAGUE_CHOLERA, 1, 14400); // 4 hours
                }
            }
        }
    }
}
