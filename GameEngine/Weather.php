<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Weather.php                                               ##
##  Type           : Dynamic Weather & Seasonal Engine for TravianZ            ##
##  Purpose        : Global & regional dynamic weather system with tactical    ##
##                   production, march speed, wall defense & scouting bonuses  ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class Weather {

    const TYPE_CLEAR   = 1; // Musim Subur / Cuaca Cerah (Sunny / Harvest Bloom)
    const TYPE_MONSOON = 2; // Hujan Badai Muson (Monsoon / Heavy Storm)
    const TYPE_DROUGHT = 3; // Kemarau Panjang / Panas Terik (Severe Drought)
    const TYPE_FOG     = 4; // Kabut Tebal Nusantara / Asap Mistis (Dense Fog)
    const TYPE_AURORA  = 5; // Cahaya Dewa / Purnama Agung (Celestial Blessing)

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
     * Create weather table if not existing.
     */
    public static function ensureTable(): void {
        $db = self::db();
        if (!$db) return;

        $q = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "weather` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `zone` varchar(20) NOT NULL DEFAULT 'global',
            `weather_type` tinyint(2) NOT NULL DEFAULT 1,
            `start_time` int(11) NOT NULL DEFAULT 0,
            `end_time` int(11) NOT NULL DEFAULT 0,
            `created_at` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `zone_end` (`zone`, `end_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($db, $q);
    }

    /**
     * Get or initialize currently active weather for a zone.
     */
    public static function getCurrentWeather(string $zone = 'global'): array {
        self::ensureTable();
        $db = self::db();
        $now = time();

        if ($db) {
            $zoneEsc = mysqli_real_escape_string($db, $zone);
            $res = @mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "weather` WHERE `zone` = '$zoneEsc' AND `end_time` > $now ORDER BY `id` DESC LIMIT 1");
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                return $row;
            }
        }

        // None active or expired -> generate next weather cycle
        return self::generateNextWeather($zone);
    }

    /**
     * Generate and persist a new weather event.
     */
    public static function generateNextWeather(string $zone = 'global', ?int $forcedType = null): array {
        self::ensureTable();
        $db = self::db();
        $now = time();

        // Duration: default 6 hours (21600 seconds) or from config
        $cycleDuration = defined('WEATHER_CYCLE_SECONDS') ? (int)WEATHER_CYCLE_SECONDS : 21600;

        if ($forcedType !== null) {
            $type = $forcedType;
        } else {
            // Weighted random selection:
            // Clear: 35%, Monsoon: 25%, Drought: 20%, Fog: 15%, Aurora: 5%
            $roll = mt_rand(1, 100);
            if ($roll <= 35) {
                $type = self::TYPE_CLEAR;
            } elseif ($roll <= 60) {
                $type = self::TYPE_MONSOON;
            } elseif ($roll <= 80) {
                $type = self::TYPE_DROUGHT;
            } elseif ($roll <= 95) {
                $type = self::TYPE_FOG;
            } else {
                $type = self::TYPE_AURORA;
            }
        }

        $endTime = $now + $cycleDuration;

        if ($db) {
            $zoneEsc = mysqli_real_escape_string($db, $zone);
            $q = "INSERT INTO `" . TB_PREFIX . "weather` (`zone`, `weather_type`, `start_time`, `end_time`, `created_at`) 
                  VALUES ('$zoneEsc', $type, $now, $endTime, $now)";
            @mysqli_query($db, $q);
            $newId = mysqli_insert_id($db);

            return [
                'id' => $newId,
                'zone' => $zone,
                'weather_type' => $type,
                'start_time' => $now,
                'end_time' => $endTime,
                'created_at' => $now
            ];
        }

        return [
            'id' => 0,
            'zone' => $zone,
            'weather_type' => $type,
            'start_time' => $now,
            'end_time' => $endTime,
            'created_at' => $now
        ];
    }

    /**
     * Get human-readable metadata, labels, and descriptions for weather types.
     */
    public static function getWeatherInfo(?int $type = null): array {
        $info = [
            self::TYPE_CLEAR => [
                'name' => 'Musim Subur / Cuaca Cerah',
                'name_en' => 'Harvest Bloom / Sunny',
                'icon' => '☀️',
                'color' => '#d48806',
                'bg_gradient' => 'linear-gradient(135deg, #fffbe6 0%, #ffe58f 100%)',
                'border_color' => '#faad14',
                'desc' => 'Matahari bersinar cerah menyuburkan ladang gandum dan kayu serta melancarkan arus perniagaan para pedagang.',
                'effects' => [
                    '🌾 Produksi Gandum +15%',
                    '🪵 Produksi Kayu +10%',
                    '🐎 Kecepatan Pedagang +20%',
                    '⚔️ Kecepatan Pasukan +5%'
                ]
            ],
            self::TYPE_MONSOON => [
                'name' => 'Hujan Badai Muson',
                'name_en' => 'Monsoon Storm',
                'icon' => '🌧️',
                'color' => '#096dd9',
                'bg_gradient' => 'linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%)',
                'border_color' => '#1890ff',
                'desc' => 'Angin muson dan guyuran hujan lebat membasahi tanah liat. Medan berlumpur menyulitkan pasukan bergerak namun dinding pertahanan semakin kokoh.',
                'effects' => [
                    '🧱 Produksi Tanah Liat +15%',
                    '🌾 Produksi Gandum +10%',
                    '🪵 Produksi Kayu -10%',
                    '🛡️ Bonus Dinding Pertahanan +15%',
                    '🚶 Kecepatan Pasukan -20% (Suku Nusantara hanya -5%)'
                ]
            ],
            self::TYPE_DROUGHT => [
                'name' => 'Kemarau Panjang & Terik',
                'name_en' => 'Severe Drought',
                'icon' => '🏜️',
                'color' => '#d4380d',
                'bg_gradient' => 'linear-gradient(135deg, #fff2e8 0%, #ffd8bf 100%)',
                'border_color' => '#fa541c',
                'desc' => 'Panas menyengat mengeringkan sungai dan ladang. Penambangan besi lebih leluasa, namun cadangan gandum terancam susut cepat.',
                'effects' => [
                    '🌾 Produksi Gandum -20%',
                    '⛓️ Produksi Besi +10%',
                    '🏃 Kecepatan Pasukan +10% (Jalan Kering)',
                    '🐫 Kecepatan Pedagang +10%',
                    '🍞 Konsumsi Gandum Pasukan +10%'
                ]
            ],
            self::TYPE_FOG => [
                'name' => 'Kabut Tebal Nusantara',
                'name_en' => 'Mystic Mist / Dense Fog',
                'icon' => '🌫️',
                'color' => '#531dab',
                'bg_gradient' => 'linear-gradient(135deg, #f9f0ff 0%, #efdbff 100%)',
                'border_color' => '#9254de',
                'desc' => 'Kabut halimun tebal menyelimuti lembah. Pengintaian menjadi sangat efektif dan serangan mendadak memberi keuntungan kejutan taktis.',
                'effects' => [
                    '👁️ Keberhasilan Telik Sandi / Scout +30%',
                    '⚡ Efek Kejut Serangan Ronde 1 +5%',
                    '⚖️ Produksi Sumber Daya Normal'
                ]
            ],
            self::TYPE_AURORA => [
                'name' => 'Purnama Agung / Berkah Dewa',
                'name_en' => 'Celestial Blessing',
                'icon' => '✨',
                'color' => '#389e0d',
                'bg_gradient' => 'linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%)',
                'border_color' => '#52c41a',
                'desc' => 'Langit malam dihiasi cahaya purnama berkah. Semangat spiritual rakyat membara menghasilkan poin budaya dan kekuatan hero berlipat.',
                'effects' => [
                    '🏛️ Poin Budaya (CP) +25%',
                    '👑 EXP Hero Petualangan +20%',
                    '🍷 Biaya Perayaan Desa -20%',
                    '🌾 Semua Produksi +5%'
                ]
            ],
        ];

        if ($type !== null) {
            return $info[$type] ?? $info[self::TYPE_CLEAR];
        }

        return $info;
    }

    /**
     * Multiplier for resource production in Village::calculateProduction.
     */
    public static function getProductionMultiplier(int $wid, string $resType): float {
        if (defined('WEATHER_SYSTEM_ENABLED') && !WEATHER_SYSTEM_ENABLED) {
            return 1.0;
        }

        $weather = self::getCurrentWeather();
        $type = (int)($weather['weather_type'] ?? self::TYPE_CLEAR);

        switch ($type) {
            case self::TYPE_CLEAR:
                if ($resType === 'crop') return 1.15;
                if ($resType === 'wood') return 1.10;
                return 1.05;

            case self::TYPE_MONSOON:
                if ($resType === 'clay') return 1.15;
                if ($resType === 'crop') return 1.10;
                if ($resType === 'wood') return 0.90;
                return 1.00;

            case self::TYPE_DROUGHT:
                if ($resType === 'crop') return 0.80;
                if ($resType === 'clay') return 0.90;
                if ($resType === 'iron') return 1.10;
                return 1.00;

            case self::TYPE_AURORA:
                return 1.05;

            case self::TYPE_FOG:
            default:
                return 1.00;
        }
    }

    /**
     * Multiplier for movement travel speed.
     */
    public static function getSpeedMultiplier(int $tribe = 0, bool $isMerchant = false): float {
        if (defined('WEATHER_SYSTEM_ENABLED') && !WEATHER_SYSTEM_ENABLED) {
            return 1.0;
        }

        $weather = self::getCurrentWeather();
        $type = (int)($weather['weather_type'] ?? self::TYPE_CLEAR);

        if ($isMerchant) {
            switch ($type) {
                case self::TYPE_CLEAR:   return 1.20;
                case self::TYPE_MONSOON: return 0.85;
                case self::TYPE_DROUGHT: return 1.10;
                default:                 return 1.00;
            }
        }

        // Troop movement speed
        switch ($type) {
            case self::TYPE_CLEAR:
                return 1.05;

            case self::TYPE_MONSOON:
                // Tribe 10 (Nusantara) is accustomed to tropical rain & monsoon mud!
                if ($tribe === 10) {
                    return 0.95;
                }
                return 0.80; // Other tribes slowed by 20% in mud

            case self::TYPE_DROUGHT:
                return 1.10; // Hard dry roads

            default:
                return 1.00;
        }
    }

    /**
     * Multiplier for wall defense in battles.
     */
    public static function getWallBonusMultiplier(): float {
        if (defined('WEATHER_SYSTEM_ENABLED') && !WEATHER_SYSTEM_ENABLED) {
            return 1.0;
        }

        $weather = self::getCurrentWeather();
        $type = (int)($weather['weather_type'] ?? self::TYPE_CLEAR);

        if ($type === self::TYPE_MONSOON) {
            return 1.15; // 15% extra wall defense due to heavy mud obstacle
        }
        return 1.0;
    }

    /**
     * Multiplier for scouting success rate.
     */
    public static function getScoutBonusMultiplier(): float {
        if (defined('WEATHER_SYSTEM_ENABLED') && !WEATHER_SYSTEM_ENABLED) {
            return 1.0;
        }

        $weather = self::getCurrentWeather();
        $type = (int)($weather['weather_type'] ?? self::TYPE_CLEAR);

        if ($type === self::TYPE_FOG) {
            return 1.30; // 30% bonus in dense fog
        }
        return 1.0;
    }

    /**
     * Automation tick hook.
     */
    public static function tick(): void {
        self::getCurrentWeather();
    }
}
