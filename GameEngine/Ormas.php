<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Ormas.php                                                 ##
##  Type           : Dynamic Village Thug & Ormas Nuisance Engine for TravianZ ##
##  Purpose        : Random events where local gangs (Pemuda Pancasila,        ##
##                   Banser Pencari Rawon, Komplotan Herkules, Ormas Setempat) ##
##                   visit villages, extort resources/crop, and apply debuffs  ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Ormas {

    const TYPE_NONE       = 0;
    const TYPE_PP         = 1; // Pemuda Pancasila (Pungli Loreng Jeruk)
    const TYPE_BANSER     = 2; // Banser Pencari Rawon di Kondangan
    const TYPE_HERKULES   = 3; // Komplotan Herkules (Penguasa Lapak Pasar)
    const TYPE_SETEMPAT   = 4; // Ormas Setempat (Uang Rokok & Jatah Kamling)

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
     * Create ormas table if not existing.
     */
    public static function ensureTable(): void {
        $db = self::db();
        if (!$db) return;

        $q = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "village_ormas` (
            `vref` int(11) NOT NULL,
            `ormas_type` tinyint(2) NOT NULL DEFAULT 0,
            `severity` tinyint(2) NOT NULL DEFAULT 1,
            `start_time` int(11) NOT NULL DEFAULT 0,
            `end_time` int(11) NOT NULL DEFAULT 0,
            `demands` varchar(255) NOT NULL DEFAULT '',
            `created_at` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`vref`),
            KEY `end_time` (`end_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q);
    }

    /**
     * Get active ormas record for a village.
     */
    public static function getVillageOrmas(int $vref): ?array {
        if (defined('ORMAS_SYSTEM_ENABLED') && !ORMAS_SYSTEM_ENABLED) {
            return null;
        }

        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return null;

        $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "village_ormas` WHERE `vref` = " . (int)$vref . " LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            if ((int)$row['ormas_type'] > 0) {
                // If end_time has passed, auto-dismiss
                if ((int)$row['end_time'] > 0 && (int)$row['end_time'] <= time()) {
                    self::dismissOrmas($vref);
                    return null;
                }
                return $row;
            }
        }
        return null;
    }

    /**
     * Check if a village is currently afflicted by an Ormas.
     */
    public static function isVillageAfflicted(int $vref): bool {
        $data = self::getVillageOrmas($vref);
        return !empty($data) && (int)($data['ormas_type'] ?? 0) > 0;
    }

    /**
     * Dispatch an Ormas to visit a village.
     */
    public static function dispatchOrmas(int $vref, int $ormasType, ?int $duration = null): bool {
        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return false;

        $now = time();
        if ($duration === null || $duration <= 0) {
            // Random duration between 1 hour (3600s) and 3 days (259200s)
            $roll = mt_rand(1, 100);
            if ($roll <= 40) {
                $duration = 3600; // 1 jam
            } elseif ($roll <= 70) {
                $duration = 10800; // 3 jam
            } elseif ($roll <= 90) {
                $duration = 86400; // 24 jam (1 hari)
            } else {
                $duration = 259200; // 3 hari
            }
        }

        $endTime = $now + $duration;
        $q = "INSERT INTO `" . TB_PREFIX . "village_ormas` (`vref`, `ormas_type`, `severity`, `start_time`, `end_time`, `demands`, `created_at`)
              VALUES ($vref, $ormasType, 1, $now, $endTime, '', $now)
              ON DUPLICATE KEY UPDATE `ormas_type` = $ormasType, `severity` = 1, `start_time` = $now, `end_time` = $endTime;";
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Dismiss / expel an Ormas from a village.
     */
    public static function dismissOrmas(int $vref): bool {
        self::ensureTable();
        $db = self::db();
        if (!$db || $vref <= 0) return false;

        $q = "UPDATE `" . TB_PREFIX . "village_ormas` SET `ormas_type` = 0, `end_time` = 0 WHERE `vref` = " . (int)$vref;
        return (bool)@mysqli_query($db, $q);
    }

    /**
     * Get rich metadata, lore, debuffs, and dismissal requirements.
     */
    public static function getOrmasInfo(?int $type = null): array {
        $info = [
            self::TYPE_PP => [
                'id' => self::TYPE_PP,
                'name' => 'Pemuda Pancasila',
                'alias' => 'Pungli Loreng Jeruk',
                'icon' => '🍊',
                'badge_color' => '#d4380d',
                'bg_gradient' => 'linear-gradient(135deg, #fff2e8 0%, #ffbb96 100%)',
                'border_color' => '#fa541c',
                'desc' => 'Mendatangi desa membawa map proposal pembangunan posko darurat serta memungut jatah parkir liar di gerbang desa.',
                'effects' => [
                    '🪵 Produksi Kayu -25%',
                    '🧱 Produksi Tanah Liat -25%',
                    '🌾 Pungli Gandum -15%',
                    '🔨 Pembangunan Gedung Melambat +20%'
                ],
                'bribe' => [
                    'wood' => 300,
                    'clay' => 300,
                    'iron' => 0,
                    'crop' => 200,
                    'label' => 'Uang Proposal Posko'
                ],
                'min_troops_fight' => 15,
                'fight_label' => 'Gerebek & Bubarkan Posko (Min. 15 Prajurit)'
            ],
            self::TYPE_BANSER => [
                'id' => self::TYPE_BANSER,
                'name' => 'Banser Pencari Rawon di Kondangan',
                'alias' => 'Pemburu Prasmanan Kondangan',
                'icon' => '🍲',
                'badge_color' => '#389e0d',
                'bg_gradient' => 'linear-gradient(135deg, #f6ffed 0%, #b7eb8f 100%)',
                'border_color' => '#52c41a',
                'desc' => 'Nongkrong beramai-ramai di balai desa, menghabiskan seluruh persediaan rawon prasmanan dan nasi kebuli milik warga.',
                'effects' => [
                    '🌾 Konsumsi Gandum Extra +40 / jam (Makan Rawon)',
                    '🏛️ Poin Budaya (CP) -30% (Suasana Gaduh)',
                    '🍷 Biaya Pesta Balai Desa +50%'
                ],
                'bribe' => [
                    'wood' => 0,
                    'clay' => 0,
                    'iron' => 0,
                    'crop' => 800,
                    'label' => 'Bungkuskan 10 Panci Rawon'
                ],
                'min_troops_fight' => 20,
                'fight_label' => 'Bubarkan Kongkow Prasmanan (Min. 20 Prajurit)'
            ],
            self::TYPE_HERKULES => [
                'id' => self::TYPE_HERKULES,
                'name' => 'Komplotan Herkules',
                'alias' => 'Penguasa Lapak & Pasar',
                'icon' => '💪',
                'badge_color' => '#722ed1',
                'bg_gradient' => 'linear-gradient(135deg, #f9f0ff 0%, #d3adf7 100%)',
                'border_color' => '#9254de',
                'desc' => 'Preman kekar menduduki pasar desa dan memungut retribusi liar dari setiap pedagang dan karavan yang lewat.',
                'effects' => [
                    '🐎 Kecepatan Pedagang -35% (Jalur Dipalak)',
                    '⛓️ Produksi Tambang Besi -25%',
                    '🪵 Produksi Kayu -25%',
                    '🎒 Kapasitas Jarahan Raid -15%'
                ],
                'bribe' => [
                    'wood' => 400,
                    'clay' => 0,
                    'iron' => 400,
                    'crop' => 300,
                    'label' => 'Uang Setoran Lapak Pasar'
                ],
                'min_troops_fight' => 25,
                'fight_label' => 'Adu Mekanik Bersenjata (Min. 25 Prajurit)'
            ],
            self::TYPE_SETEMPAT => [
                'id' => self::TYPE_SETEMPAT,
                'name' => 'Ormas Setempat',
                'alias' => 'Jatah Keamanan Kamling',
                'icon' => '☕',
                'badge_color' => '#874d00',
                'bg_gradient' => 'linear-gradient(135deg, #fffbe6 0%, #ffe58f 100%)',
                'border_color' => '#faad14',
                'desc' => 'Mondar-mandir berseragam kumal meminta jatah bulanan uang rokok dan kopi harian kepada warga desa.',
                'effects' => [
                    '📉 Seluruh Produksi Sumber Daya -15%',
                    '⚔️ Nilai Serangan Pasukan -10% (Moral Turun)'
                ],
                'bribe' => [
                    'wood' => 150,
                    'clay' => 150,
                    'iron' => 150,
                    'crop' => 150,
                    'label' => 'Uang Kopi & Rokok Kampung'
                ],
                'min_troops_fight' => 10,
                'fight_label' => 'Usir Preman Kampung (Min. 10 Prajurit)'
            ],
        ];

        if ($type !== null) {
            return $info[$type] ?? $info[self::TYPE_SETEMPAT];
        }

        return $info;
    }

    /**
     * Multiplier for resource production in Village::calculateProduction.
     */
    public static function getProductionMultiplier(int $vref, string $resType): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        switch ($type) {
            case self::TYPE_PP:
                if ($resType === 'wood' || $resType === 'clay') return 0.75;
                if ($resType === 'crop') return 0.85;
                return 1.0;

            case self::TYPE_BANSER:
                return 1.0;

            case self::TYPE_HERKULES:
                if ($resType === 'iron' || $resType === 'wood') return 0.75;
                return 1.0;

            case self::TYPE_SETEMPAT:
                return 0.85; // All resources -15%

            default:
                return 1.0;
        }
    }

    /**
     * Extra crop consumed per hour (e.g. Banser makan rawon).
     */
    public static function getExtraCropUpkeep(int $vref): int {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_BANSER) {
            return 40; // 40 gandum/jam ludes dimakan
        }
        return 0;
    }

    /**
     * Multiplier for merchant travel speed.
     */
    public static function getMerchantSpeedMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_HERKULES) {
            return 0.65; // Pedagang diperlambat 35% karena dipalak
        }
        return 1.0;
    }

    /**
     * Multiplier for building construction time.
     */
    public static function getBuildTimeMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_PP) {
            return 1.20; // Pembangunan 20% lebih lama karena tukang diganggu
        }
        return 1.0;
    }

    /**
     * Multiplier for raid loot carry capacity.
     */
    public static function getCarryCapacityMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_HERKULES) {
            return 0.85; // Jarahan dipotong preman 15%
        }
        return 1.0;
    }

    /**
     * Multiplier for troop attack bonus.
     */
    public static function getAttackBonusMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_SETEMPAT) {
            return 0.90; // Moral turun, serangan -10%
        }
        return 1.0;
    }

    /**
     * Multiplier for daily Culture Points production.
     */
    public static function getCulturePointMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_BANSER) {
            return 0.70; // CP -30%
        }
        return 1.0;
    }

    /**
     * Multiplier for village celebration costs.
     */
    public static function getCelebrationCostMultiplier(int $vref): float {
        $ormas = self::getVillageOrmas($vref);
        if (!$ormas) return 1.0;

        $type = (int)($ormas['ormas_type'] ?? 0);
        if ($type === self::TYPE_BANSER) {
            return 1.50; // Biaya pesta +50%
        }
        return 1.0;
    }

    /**
     * Automated periodic spawning hook for Automation engine.
     */
    public static function processSpawns(): void {
        if (defined('ORMAS_SYSTEM_ENABLED') && !ORMAS_SYSTEM_ENABLED) {
            return;
        }

        self::ensureTable();
        $db = self::db();
        if (!$db) return;

        // Roll spawn chance (default 25% chance per automation tick)
        $chance = defined('ORMAS_SPAWN_CHANCE') ? (int)ORMAS_SPAWN_CHANCE : 25;
        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $minPop = defined('ORMAS_MIN_POP') ? (int)ORMAS_MIN_POP : 40;
        $now = time();

        // Pick 1 candidate player village (non-admin, not protected, pop >= minPop, no active ormas)
        $q = "SELECT v.wref FROM " . TB_PREFIX . "vdata v
              JOIN " . TB_PREFIX . "users u ON v.owner = u.id
              LEFT JOIN " . TB_PREFIX . "village_ormas o ON v.wref = o.vref AND o.end_time > $now AND o.ormas_type > 0
              WHERE u.access < 8
                AND u.protect < $now
                AND v.pop >= $minPop
                AND o.vref IS NULL
              ORDER BY RAND() LIMIT 1";

        $res = @mysqli_query($db, $q);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $targetWid = (int)$row['wref'];
            $randomType = mt_rand(1, 4);
            self::dispatchOrmas($targetWid, $randomType);
        }
    }
}
