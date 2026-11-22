<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : ExpeditionEngine.php                                      ##
##  Type           : Alliance Expedition Engine (Co-op PvE System)            ##
##  Purpose        : Multi-player Forward Camp & Unified Battlegroup assault  ##
##                   against Bandit Fortress (Tier 3) and World Boss (Tier 4) ##
##                   featuring 8 Tribe Synergies & Shared Casualty Mitigation. ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class ExpeditionEngine {

    const STATUS_LOBBY     = 0; // Forward Camp persiapan (buka slot lobi 2-5 pemain)
    const STATUS_RESOLVED  = 1; // Ekspedisi sukses benturan dan menang (Boss tewas)
    const STATUS_FAILED    = 2; // Ekspedisi terpukul mundur (Boss selamat / partial damage)
    const STATUS_CANCELLED = 3; // Ekspedisi dibatalkan (auto-refund karena kuota kurang / dibatalkan leader)

    const DAILY_LIMIT = 3;       // Maksimal 3x ekspedisi per hari per pemain (Anti-Abuse)
    const MIN_MEMBERS = 2;       // Minimal 2 pemain untuk meluncurkan serangan bersama
    const MAX_MEMBERS = 5;       // Maksimal 5 pemain dalam 1 Forward Camp party
    const MIN_POWER_RATIO = 0.05;// Minimal 5% kekuatan relatif terhadap total HP/penjaga Boss (Anti-Tuyul)

    /**
     * Pastikan tabel-tabel database untuk Alliance Expedition tersedia.
     */
    public static function ensureTables(): void {
        global $database;
        if (!isset($database->dblink) || !$database->dblink) {
            return;
        }

        // 1. Tabel Sesi Ekspedisi (Forward Camp Session)
        $q1 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "expeditions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `camp_id` int(11) NOT NULL,
            `target_wref` int(11) NOT NULL,
            `leader_uid` int(11) NOT NULL,
            `alliance_id` int(11) NOT NULL DEFAULT 0,
            `status` tinyint(2) NOT NULL DEFAULT 0,
            `impact_time` int(11) NOT NULL,
            `prep_duration` int(11) NOT NULL DEFAULT 1200,
            `min_participants` tinyint(2) NOT NULL DEFAULT 2,
            `max_participants` tinyint(2) NOT NULL DEFAULT 5,
            `created_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY (`camp_id`),
            KEY (`leader_uid`),
            KEY (`alliance_id`),
            KEY (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $q1);

        // 2. Tabel Anggota & Pasukan di Forward Camp
        $q2 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "expedition_members` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `expedition_id` int(11) NOT NULL,
            `uid` int(11) NOT NULL,
            `from_wref` int(11) NOT NULL,
            `tribe` tinyint(2) NOT NULL,
            `u1` int(11) NOT NULL DEFAULT 0,
            `u2` int(11) NOT NULL DEFAULT 0,
            `u3` int(11) NOT NULL DEFAULT 0,
            `u4` int(11) NOT NULL DEFAULT 0,
            `u5` int(11) NOT NULL DEFAULT 0,
            `u6` int(11) NOT NULL DEFAULT 0,
            `u7` int(11) NOT NULL DEFAULT 0,
            `u8` int(11) NOT NULL DEFAULT 0,
            `u9` int(11) NOT NULL DEFAULT 0,
            `u10` int(11) NOT NULL DEFAULT 0,
            `has_hero` tinyint(1) NOT NULL DEFAULT 0,
            `attack_power` bigint(20) NOT NULL DEFAULT 0,
            `damage_dealt` bigint(20) NOT NULL DEFAULT 0,
            `losses_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
            `reward_wood` int(11) NOT NULL DEFAULT 0,
            `reward_clay` int(11) NOT NULL DEFAULT 0,
            `reward_iron` int(11) NOT NULL DEFAULT 0,
            `reward_crop` int(11) NOT NULL DEFAULT 0,
            `reward_exp` int(11) NOT NULL DEFAULT 0,
            `reward_cp` int(11) NOT NULL DEFAULT 0,
            `reward_silver` int(11) NOT NULL DEFAULT 0,
            `reward_essence` int(11) NOT NULL DEFAULT 0,
            `joined_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `exp_uid` (`expedition_id`, `uid`),
            KEY (`expedition_id`),
            KEY (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $q2);

        // 3. Tabel Batas Harian Partisipasi (Daily Usage)
        $q3 = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "expedition_daily_usage` (
            `uid` int(11) NOT NULL,
            `entry_date` date NOT NULL,
            `runs_count` tinyint(2) NOT NULL DEFAULT 0,
            PRIMARY KEY (`uid`, `entry_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        mysqli_query($database->dblink, $q3);
    }

    /**
     * Ambil jumlah pemakaian ekspedisi hari ini oleh seorang user.
     */
    public static function getDailyUsage(int $uid): int {
        global $database;
        self::ensureTables();
        $uid = (int)$uid;
        if ($uid <= 0) return 0;
        $today = date('Y-m-d');
        $res = $database->query_return("SELECT runs_count FROM `" . TB_PREFIX . "expedition_daily_usage` WHERE uid = $uid AND entry_date = '$today' LIMIT 1");
        return !empty($res[0]['runs_count']) ? (int)$res[0]['runs_count'] : 0;
    }

    /**
     * Periksa apakah pemain masih memiliki kuota harian untuk bergabung ke ekspedisi.
     */
    public static function canJoinExpedition(int $uid): bool {
        return self::getDailyUsage($uid) < self::DAILY_LIMIT;
    }

    /**
     * Tambahkan 1 pemakaian ekspedisi harian.
     */
    public static function recordDailyUsage(int $uid): void {
        global $database;
        self::ensureTables();
        $uid = (int)$uid;
        if ($uid <= 0) return;
        $today = date('Y-m-d');
        $q = "INSERT INTO `" . TB_PREFIX . "expedition_daily_usage` (`uid`, `entry_date`, `runs_count`)
              VALUES ($uid, '$today', 1)
              ON DUPLICATE KEY UPDATE `runs_count` = `runs_count` + 1";
        mysqli_query($database->dblink, $q);
    }

    /**
     * Kembalikan 1 kuota ekspedisi harian (saat auto-refund atau batal).
     */
    public static function refundDailyUsage(int $uid): void {
        global $database;
        self::ensureTables();
        $uid = (int)$uid;
        if ($uid <= 0) return;
        $today = date('Y-m-d');
        $q = "UPDATE `" . TB_PREFIX . "expedition_daily_usage`
              SET `runs_count` = GREATEST(0, `runs_count` - 1)
              WHERE `uid` = $uid AND `entry_date` = '$today'";
        mysqli_query($database->dblink, $q);
    }

    /**
     * Hitung total Attack Power dari daftar pasukan yang disetor.
     */
    public static function calculateTroopsAttackPower(int $tribe, array $troops, bool $hasHero = false, int $heroUid = 0): int {
        global $database;
        $tribe = max(1, (int)$tribe);
        $totalAtk = 0;

        // Muat unit data
        static $unitDataCache = null;
        if ($unitDataCache === null) {
            $unitDataFile = dirname(__DIR__) . '/Data/unitdata.php';
            if (file_exists($unitDataFile)) {
                require $unitDataFile;
                $unitDataCache = [];
                for ($i = 1; $i <= 100; $i++) {
                    $varName = 'u' . $i;
                    if (isset($$varName) && is_array($$varName)) {
                        $unitDataCache[$i] = $$varName;
                    }
                }
            }
        }

        // Hitung kekuatan dari prajurit u1..u10 suku tersebut
        for ($i = 1; $i <= 10; $i++) {
            $count = 0;
            if (isset($troops['u' . $i])) {
                $count = (int)$troops['u' . $i];
            } elseif (isset($troops[$i])) {
                $count = (int)$troops[$i];
            }
            $globalUnitId = ($tribe - 1) * 10 + $i;
            if (isset($troops['u' . $globalUnitId])) {
                $count = (int)$troops['u' . $globalUnitId];
            }

            if ($count > 0 && isset($unitDataCache[$globalUnitId])) {
                $unitAtk = (int)($unitDataCache[$globalUnitId]['atk'] ?? 0);
                $totalAtk += ($count * $unitAtk);
            }
        }

        // Tambah kekuatan Hero jika disertakan
        if ($hasHero && $heroUid > 0 && isset($database)) {
            $hero = $database->getHero($heroUid);
            if (!empty($hero[0]) && empty($hero[0]['dead'])) {
                $heroAtkPoints = (int)($hero[0]['attack'] ?? 0);
                $heroPower = 100 + ($heroAtkPoints * 80);
                $totalAtk += $heroPower;
            }
        }

        return max(0, $totalAtk);
    }

    /**
     * Validasi apakah kontribusi pasukan memenuhi syarat batas minimal (Anti-Tuyul).
     */
    public static function validateMinimumPower(int $attackPower, int $campHp): array {
        $requiredMin = max(50, (int)round($campHp * self::MIN_POWER_RATIO));
        if ($attackPower < $requiredMin) {
            return [
                'valid' => false,
                'current' => $attackPower,
                'required' => $requiredMin,
                'message' => "Pasukan tidak memenuhi syarat kekuatan minimum ekspedisi! Minimal butuh " . number_format($requiredMin) . " Attack Power (Pasukan Anda: " . number_format($attackPower) . ")."
            ];
        }
        return [
            'valid' => true,
            'current' => $attackPower,
            'required' => $requiredMin,
            'message' => "Syarat kekuatan terpenuhi."
        ];
    }

    /**
     * Hitung Tribe Synergies & Coalition Buffs berdasarkan daftar suku yang hadir.
     */
    public static function computeSynergiesFromTribes(array $tribes): array {
        $uniqueTribes = array_unique(array_filter(array_map('intval', $tribes)));

        $result = [
            'synergies' => [],
            'casualty_reduction' => 0.0,
            'offense_buff' => 0.0,
            'enemy_def_reduction' => 0.0,
            'casualty_recovery' => 0.0,
            'speed_buff' => 0.0,
            'crit_strike' => 0.0,
            'carry_buff' => 0.0,
            'loot_multiplier' => 0.0,
            'max_loss_cap' => 1.0,
            'hero_exp_buff' => 0.0,
            'enemy_atk_reduction' => 0.0,
            'silver_multiplier' => 0.0,
            'rare_drop_buff' => 0.0,
            'coalition_bonus' => null
        ];

        foreach ($uniqueTribes as $t) {
            switch ($t) {
                case 1: // Romans
                    $result['synergies'][1] = [
                        'name' => 'Testudo Shield Wall',
                        'tribe_name' => 'Romans',
                        'desc' => 'Mengurangi total korban gugur ekspedisi sebesar 8% berkat formasi perisai infanteri berat.',
                        'badge' => '🛡️ -8% Casualty'
                    ];
                    $result['casualty_reduction'] += 0.08;
                    break;
                case 2: // Teutons
                    $result['synergies'][2] = [
                        'name' => 'Berserker Rage & Sunder',
                        'tribe_name' => 'Teutons',
                        'desc' => 'Meningkatkan total ofensif sebesar +10% dan melemahkan pertahanan musuh sebesar 15%.',
                        'badge' => '⚔️ +10% Offense & -15% Enemy Def'
                    ];
                    $result['offense_buff'] += 0.10;
                    $result['enemy_def_reduction'] += 0.15;
                    break;
                case 3: // Gauls
                    $result['synergies'][3] = [
                        'name' => 'Field Medicine & Rapid Egress',
                        'tribe_name' => 'Gauls',
                        'desc' => 'Menyelamatkan 10% prajurit gugur setelah perang, dan mempercepat perjalanan pulang +20%.',
                        'badge' => '🩹 +10% Heal & +20% Speed'
                    ];
                    $result['casualty_recovery'] += 0.10;
                    $result['speed_buff'] += 0.20;
                    break;
                case 6: // Huns
                    $result['synergies'][6] = [
                        'name' => 'Steppe Flanking & Blitz',
                        'tribe_name' => 'Huns',
                        'desc' => 'Serangan kilat kavaleri memberikan peluang Critical Strike +15% burst damage.',
                        'badge' => '🏹 +15% Critical Strike'
                    ];
                    $result['crit_strike'] += 0.15;
                    break;
                case 7: // Egyptians
                    $result['synergies'][7] = [
                        'name' => 'Nile Logistics & Provisioning',
                        'tribe_name' => 'Egyptians',
                        'desc' => 'Meningkatkan kapasitas angkut +25% dan bonus rampasan sumber daya gudang +10%.',
                        'badge' => '📦 +25% Carry & +10% Res Loot'
                    ];
                    $result['carry_buff'] += 0.25;
                    $result['loot_multiplier'] += 0.10;
                    break;
                case 8: // Spartans
                    $result['synergies'][8] = [
                        'name' => 'Phalanx Iron Will',
                        'tribe_name' => 'Spartans',
                        'desc' => 'Mencegah pembantaian total (kerugian maksimal dikunci di 45%) dan +15% Hero EXP.',
                        'badge' => '🏛️ Anti-Wipeout (Max 45% Loss) & +15% Hero EXP'
                    ];
                    $result['max_loss_cap'] = min($result['max_loss_cap'], 0.45);
                    $result['hero_exp_buff'] += 0.15;
                    break;
                case 9: // Vikings
                    $result['synergies'][9] = [
                        'name' => 'War Cry & Silver Hoard',
                        'tribe_name' => 'Vikings',
                        'desc' => 'Teriakan perang melemahkan attack musuh -8% dan memberikan bonus Silver +20%.',
                        'badge' => '🪙 +20% Silver & -8% Enemy Atk'
                    ];
                    $result['enemy_atk_reduction'] += 0.08;
                    $result['silver_multiplier'] += 0.20;
                    break;
                case 10: // Nusantara
                    $result['synergies'][10] = [
                        'name' => 'Bhayangkara War Vanguard',
                        'tribe_name' => 'Nusantara',
                        'desc' => 'Serbuan Gajah Perang Bhayangkara meningkatkan total daya serang ekspedisi sebesar +12% dan meningkatkan perolehan World Boss Essence sebesar +20%.',
                        'badge' => '🐘 +12% Offense & +20% World Essence'
                    ];
                    $result['offense_buff'] += 0.12;
                    $result['rare_drop_buff'] += 0.20;
                    break;
            }
        }

        // Bonus Koalisi Multikultural (Grand Coalition Bonus)
        $tribeCount = count($uniqueTribes);
        if ($tribeCount >= 5) {
            $result['coalition_bonus'] = [
                'name' => 'Grand Coalition',
                'tribes_count' => $tribeCount,
                'offense' => 0.10,
                'defense' => 0.10,
                'hero_exp' => 0.15,
                'badge' => '👑 Grand Coalition (+10% Atk/Def, +15% EXP)'
            ];
            $result['offense_buff'] += 0.10;
            $result['hero_exp_buff'] += 0.15;
        } elseif ($tribeCount >= 3) {
            $result['coalition_bonus'] = [
                'name' => 'Tricolor Alliance',
                'tribes_count' => $tribeCount,
                'offense' => 0.05,
                'defense' => 0.05,
                'hero_exp' => 0.0,
                'badge' => '🌟 Tricolor Alliance (+5% Atk/Def)'
            ];
            $result['offense_buff'] += 0.05;
        }

        return $result;
    }

    /**
     * Hitung pembagian kerugian bersama (Shared Casualty Mitigation).
     * Semua anggota menanggung persentase gugur yang adil dan proporsional.
     */
    public static function calculateSharedCasualties(array $members, float $rawLossRatio, array $tribes): array {
        $synergies = self::computeSynergiesFromTribes($tribes);

        $lossRatio = max(0.0, min(1.0, $rawLossRatio));

        // Terapkan batas Spartan Iron Will
        if ($lossRatio > $synergies['max_loss_cap']) {
            $lossRatio = $synergies['max_loss_cap'];
        }

        // Terapkan mitigasi perisai Romawi
        if ($synergies['casualty_reduction'] > 0) {
            $lossRatio = $lossRatio * (1.0 - $synergies['casualty_reduction']);
        }

        $lossRatio = max(0.0, min(1.0, $lossRatio));

        $result = [
            'effective_loss_ratio' => $lossRatio,
            'members' => []
        ];

        foreach ($members as $m) {
            $uid = (int)($m['uid'] ?? 0);
            $tribe = (int)($m['tribe'] ?? 1);
            $troops = $m['troops'] ?? [];

            $mResult = [
                'uid' => $uid,
                'troops_sent' => $troops,
                'troops_lost' => [],
                'troops_recovered' => [],
                'troops_survived' => [],
                'total_sent' => 0,
                'total_lost' => 0,
                'total_recovered' => 0,
                'total_survived' => 0,
            ];

            foreach ($troops as $uKey => $count) {
                $count = (int)$count;
                if ($count <= 0) continue;

                $mResult['total_sent'] += $count;
                $rawLost = (int)round($count * $lossRatio);
                $rawLost = min($count, max(0, $rawLost));

                // Jika aliansi punya suku Galia, sembuhkan 10% prajurit gugur!
                $recovered = 0;
                if ($synergies['casualty_recovery'] > 0 && $rawLost > 0) {
                    $recovered = (int)round($rawLost * $synergies['casualty_recovery']);
                    $recovered = min($rawLost, max(0, $recovered));
                }

                $survived = ($count - $rawLost) + $recovered;

                $mResult['troops_lost'][$uKey] = $rawLost;
                $mResult['troops_recovered'][$uKey] = $recovered;
                $mResult['troops_survived'][$uKey] = $survived;

                $mResult['total_lost'] += $rawLost;
                $mResult['total_recovered'] += $recovered;
                $mResult['total_survived'] += $survived;
            }

            $result['members'][$uid] = $mResult;
        }

        return $result;
    }

    /**
     * Hitung pembagian reward: 50% Flat Share + 50% Damage Proportional Share.
     */
    public static function calculateRewardDistribution(array $membersDamage, array $poolTotal, array $tribes): array {
        $synergies = self::computeSynergiesFromTribes($tribes);

        $memberCount = max(1, count($membersDamage));
        $totalDamage = array_sum($membersDamage);

        // Modifikasi pool dengan buff suku (Egyptians: +10% resource, Vikings: +20% silver, Spartans/Coalition: Hero EXP)
        $woodPool = (int)round(($poolTotal['wood'] ?? 0) * (1.0 + $synergies['loot_multiplier']));
        $clayPool = (int)round(($poolTotal['clay'] ?? 0) * (1.0 + $synergies['loot_multiplier']));
        $ironPool = (int)round(($poolTotal['iron'] ?? 0) * (1.0 + $synergies['loot_multiplier']));
        $cropPool = (int)round(($poolTotal['crop'] ?? 0) * (1.0 + $synergies['loot_multiplier']));
        $silverPool = (int)round(($poolTotal['silver'] ?? 0) * (1.0 + $synergies['silver_multiplier']));
        $cpPool = (int)($poolTotal['cp'] ?? 0);
        $expPool = (int)round(($poolTotal['exp'] ?? 0) * (1.0 + $synergies['hero_exp_buff']));
        $essencePool = (int)($poolTotal['essence'] ?? 0);

        // Tambah bonus drop essence jika Nusantara hadir
        if ($synergies['rare_drop_buff'] > 0 && $essencePool > 0) {
            $essencePool = (int)round($essencePool * (1.0 + $synergies['rare_drop_buff']));
        }

        $distribution = [];

        foreach ($membersDamage as $uid => $dmg) {
            $dmgRatio = ($totalDamage > 0) ? ($dmg / $totalDamage) : (1.0 / $memberCount);

            // 50% Flat Pool
            $flatWood = floor(($woodPool * 0.50) / $memberCount);
            $flatClay = floor(($clayPool * 0.50) / $memberCount);
            $flatIron = floor(($ironPool * 0.50) / $memberCount);
            $flatCrop = floor(($cropPool * 0.50) / $memberCount);
            $flatSilver = floor(($silverPool * 0.50) / $memberCount);
            $flatCp = floor(($cpPool * 0.50) / $memberCount);
            $flatExp = floor(($expPool * 0.50) / $memberCount);

            // 50% Damage Pool
            $dmgWood = floor(($woodPool * 0.50) * $dmgRatio);
            $dmgClay = floor(($clayPool * 0.50) * $dmgRatio);
            $dmgIron = floor(($ironPool * 0.50) * $dmgRatio);
            $dmgCrop = floor(($cropPool * 0.50) * $dmgRatio);
            $dmgSilver = floor(($silverPool * 0.50) * $dmgRatio);
            $dmgCp = floor(($cpPool * 0.50) * $dmgRatio);
            $dmgExp = floor(($expPool * 0.50) * $dmgRatio);

            // World Essence: minimal 1 per anggota jika pool cukup
            $shareEssence = ($essencePool > 0) ? max(1, (int)round($dmgRatio * $essencePool)) : 0;

            $distribution[$uid] = [
                'uid' => $uid,
                'damage' => $dmg,
                'pct' => ($totalDamage > 0) ? round($dmgRatio * 100, 2) : round(100 / $memberCount, 2),
                'wood' => (int)($flatWood + $dmgWood),
                'clay' => (int)($flatClay + $dmgClay),
                'iron' => (int)($flatIron + $dmgIron),
                'crop' => (int)($flatCrop + $dmgCrop),
                'silver' => (int)($flatSilver + $dmgSilver),
                'cp' => (int)($flatCp + $dmgCp),
                'exp' => (int)($flatExp + $dmgExp),
                'essence' => $shareEssence,
            ];
        }

        return $distribution;
    }

    /**
     * Buka Sesi Ekspedisi Baru (Inisiator membuat Forward Camp Lobby).
     */
    public static function createExpedition(
        int $leaderUid,
        int $campId,
        int $prepMinutes = 20,
        int $fromWref = 0,
        array $troops = [],
        bool $hasHero = false
    ): array {
        global $database;
        self::ensureTables();

        $leaderUid = (int)$leaderUid;
        $campId = (int)$campId;
        $prepMinutes = max(10, min(120, (int)$prepMinutes));

        // 1. Validasi Kuota Harian Inisiator
        if (!self::canJoinExpedition($leaderUid)) {
            return ['status' => 'error', 'message' => "Batas harian tercapai! Anda telah mengikuti " . self::DAILY_LIMIT . " ekspedisi hari ini."];
        }

        // 2. Validasi Aliansi Inisiator
        $leaderRows = $database->query_return("SELECT id, username, alliance, tribe FROM `" . TB_PREFIX . "users` WHERE id = $leaderUid LIMIT 1");
        $leaderData = $leaderRows[0] ?? [];
        $allianceId = (int)($leaderData['alliance'] ?? 0);
        if ($allianceId <= 0) {
            return ['status' => 'error', 'message' => "Anda harus bergabung dalam aliansi untuk membuka Ekspedisi Forward Camp!"];
        }

        // 3. Validasi Target Bandit Camp (Hanya Tier 3 Benteng & Tier 4 World Boss)
        $camps = $database->query_return("SELECT * FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId AND status = 1 LIMIT 1");
        if (empty($camps[0])) {
            return ['status' => 'error', 'message' => "Target Sarang Bandit tidak ditemukan atau sudah ditaklukkan!"];
        }
        $camp = $camps[0];
        $tier = (int)$camp['tier'];
        if ($tier < 3) {
            return ['status' => 'error', 'message' => "Ekspedisi Aliansi hanya diperuntukkan untuk Benteng Bandit (Tier 3) dan World Boss (Tier 4)!"];
        }

        // 4. Periksa apakah sudah ada ekspedisi aktif untuk camp ini oleh aliansi yang sama
        $activeExp = $database->query_return("SELECT id FROM `" . TB_PREFIX . "expeditions` WHERE camp_id = $campId AND alliance_id = $allianceId AND status = 0 LIMIT 1");
        if (!empty($activeExp[0])) {
            return ['status' => 'error', 'message' => "Aliansi Anda sudah memiliki Forward Camp aktif untuk target ini! Silakan bergabung ke lobi yang ada."];
        }

        // 5. Validasi Pasukan Inisiator
        if ($fromWref <= 0) {
            $fromWref = (int)$database->getVillageID($leaderUid);
        }

        // Deteksi suku desa pemberangkatan (village tribe)
        $fromVilRows = $database->query_return("SELECT tribe FROM `" . TB_PREFIX . "vdata` WHERE wref = $fromWref LIMIT 1");
        $vTribe = !empty($fromVilRows[0]['tribe']) ? (int)$fromVilRows[0]['tribe'] : 0;
        if ($vTribe <= 0) {
            $vUnitsCheck = $database->getUnit($fromWref, false);
            $candidateTribes = [
                1 => [1, 10], 2 => [11, 20], 3 => [21, 30], 6 => [51, 60],
                7 => [61, 70], 8 => [71, 80], 9 => [81, 90], 10 => [91, 100]
            ];
            foreach ($candidateTribes as $cT => $r) {
                for ($u = $r[0]; $u <= $r[1]; $u++) {
                    if (!empty($vUnitsCheck['u' . $u]) && (int)$vUnitsCheck['u' . $u] > 0) {
                        $vTribe = $cT;
                        break 2;
                    }
                }
            }
        }
        $leaderTribe = ($vTribe > 0) ? $vTribe : (int)($leaderData['tribe'] ?? 1);

        // Verifikasi keberadaan Hero di desa pemberangkatan jika dicentang
        if ($hasHero) {
            $heroCheck = $database->query_return("SELECT heroid FROM `" . TB_PREFIX . "hero` WHERE uid = $leaderUid AND dead = 0 AND wref = $fromWref LIMIT 1");
            if (empty($heroCheck)) {
                $hasHero = false;
            }
        }

        $atkPower = self::calculateTroopsAttackPower($leaderTribe, $troops, $hasHero, $leaderUid);
        $minCheck = self::validateMinimumPower($atkPower, (int)$camp['cur_hp']);
        if (!$minCheck['valid']) {
            return ['status' => 'error', 'message' => $minCheck['message']];
        }

        // 6. Verifikasi kepemilikan desa & kecukupan pasukan
        if ($fromWref <= 0) {
            $fromWref = (int)$database->getVillageID($leaderUid);
        }
        $vUnits = $database->getUnit($fromWref, false);
        for ($i = 1; $i <= 10; $i++) {
            $unitCol = 'u' . (($leaderTribe - 1) * 10 + $i);
            $reqCount = (int)($troops['u' . $i] ?? ($troops[$i] ?? 0));
            $avail = (int)($vUnits[$unitCol] ?? 0);
            if ($reqCount > $avail) {
                return ['status' => 'error', 'message' => "Jumlah pasukan tidak mencukupi di desa pemberangkatan."];
            }
        }

        $now = time();
        $impactTime = $now + ($prepMinutes * 60);

        // Buat record ekspedisi
        $qExp = "INSERT INTO `" . TB_PREFIX . "expeditions` 
                 (`camp_id`, `target_wref`, `leader_uid`, `alliance_id`, `status`, `impact_time`, `prep_duration`, `min_participants`, `max_participants`, `created_at`)
                 VALUES ($campId, " . (int)$camp['wref'] . ", $leaderUid, $allianceId, 0, $impactTime, " . ($prepMinutes * 60) . ", " . self::MIN_MEMBERS . ", " . self::MAX_MEMBERS . ", $now)";
        mysqli_query($database->dblink, $qExp);
        $expeditionId = mysqli_insert_id($database->dblink);

        // Deduct pasukan dari desa dan catat di s1_expedition_members
        self::deductAndRegisterMember($expeditionId, $leaderUid, $fromWref, $leaderTribe, $troops, $hasHero, $atkPower);
        self::recordDailyUsage($leaderUid);

        return [
            'status' => 'success',
            'expedition_id' => $expeditionId,
            'impact_time' => $impactTime,
            'message' => "Forward Camp berhasil dibentuk! Lobi ekspedisi terbuka untuk anggota aliansi Anda."
        ];
    }

    /**
     * Anggota Aliansi Bergabung ke Sesi Ekspedisi (Deploy Pasukan ke Forward Camp).
     */
    public static function joinExpedition(
        int $expeditionId,
        int $uid,
        int $fromWref,
        array $troops,
        bool $hasHero = false
    ): array {
        global $database;
        self::ensureTables();

        $expeditionId = (int)$expeditionId;
        $uid = (int)$uid;

        // 1. Ambil data ekspedisi
        $expRows = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expeditions` WHERE id = $expeditionId LIMIT 1");
        if (empty($expRows[0])) {
            return ['status' => 'error', 'message' => "Sesi ekspedisi tidak ditemukan!"];
        }
        $exp = $expRows[0];
        if ((int)$exp['status'] !== self::STATUS_LOBBY) {
            return ['status' => 'error', 'message' => "Lobi ekspedisi sudah ditutup atau telah diberangkatkan!"];
        }

        // 2. Validasi Kuota Harian
        if (!self::canJoinExpedition($uid)) {
            return ['status' => 'error', 'message' => "Batas harian tercapai! Anda telah mengikuti " . self::DAILY_LIMIT . " ekspedisi hari ini."];
        }

        // 3. Validasi Kesamaan Aliansi
        $userRows = $database->query_return("SELECT id, username, alliance, tribe FROM `" . TB_PREFIX . "users` WHERE id = $uid LIMIT 1");
        $userData = $userRows[0] ?? [];
        $allianceId = (int)($userData['alliance'] ?? 0);
        if ($allianceId <= 0 || $allianceId !== (int)$exp['alliance_id']) {
            return ['status' => 'error', 'message' => "Anda bukan anggota dari aliansi penyelenggara ekspedisi ini!"];
        }

        // 4. Periksa apakah sudah bergabung
        $existing = $database->query_return("SELECT id FROM `" . TB_PREFIX . "expedition_members` WHERE expedition_id = $expeditionId AND uid = $uid LIMIT 1");
        if (!empty($existing[0])) {
            return ['status' => 'error', 'message' => "Anda sudah menyetor pasukan ke Forward Camp ekspedisi ini!"];
        }

        // 5. Cek batas maksimal peserta
        $currentMembers = $database->query_return("SELECT COUNT(*) as total FROM `" . TB_PREFIX . "expedition_members` WHERE expedition_id = $expeditionId");
        if (!empty($currentMembers[0]['total']) && (int)$currentMembers[0]['total'] >= self::MAX_MEMBERS) {
            return ['status' => 'error', 'message' => "Lobi Forward Camp sudah penuh (Maksimal " . self::MAX_MEMBERS . " pemain)!"];
        }

        // 6. Validasi Pasukan & Kekuatan Minimal
        if ($fromWref <= 0) {
            $fromWref = (int)$database->getVillageID($uid);
        }

        // Deteksi suku desa pemberangkatan (village tribe)
        $fromVilRows = $database->query_return("SELECT tribe FROM `" . TB_PREFIX . "vdata` WHERE wref = $fromWref LIMIT 1");
        $vTribe = !empty($fromVilRows[0]['tribe']) ? (int)$fromVilRows[0]['tribe'] : 0;
        if ($vTribe <= 0) {
            $vUnitsCheck = $database->getUnit($fromWref, false);
            $candidateTribes = [
                1 => [1, 10], 2 => [11, 20], 3 => [21, 30], 6 => [51, 60],
                7 => [61, 70], 8 => [71, 80], 9 => [81, 90], 10 => [91, 100]
            ];
            foreach ($candidateTribes as $cT => $r) {
                for ($u = $r[0]; $u <= $r[1]; $u++) {
                    if (!empty($vUnitsCheck['u' . $u]) && (int)$vUnitsCheck['u' . $u] > 0) {
                        $vTribe = $cT;
                        break 2;
                    }
                }
            }
        }
        $userTribe = ($vTribe > 0) ? $vTribe : (int)($userData['tribe'] ?? 1);

        // Verifikasi hero jika dicentang
        if ($hasHero) {
            $heroCheck = $database->query_return("SELECT heroid FROM `" . TB_PREFIX . "hero` WHERE uid = $uid AND dead = 0 AND wref = $fromWref LIMIT 1");
            if (empty($heroCheck)) {
                $hasHero = false;
            }
        }

        $camp = $database->query_return("SELECT cur_hp FROM `" . TB_PREFIX . "bandit_camps` WHERE id = " . (int)$exp['camp_id'] . " LIMIT 1");
        $campHp = !empty($camp[0]['cur_hp']) ? (int)$camp[0]['cur_hp'] : 1000;

        $atkPower = self::calculateTroopsAttackPower($userTribe, $troops, $hasHero, $uid);
        $minCheck = self::validateMinimumPower($atkPower, $campHp);
        if (!$minCheck['valid']) {
            return ['status' => 'error', 'message' => $minCheck['message']];
        }

        // 7. Cek ketersediaan pasukan di desa
        if ($fromWref <= 0) {
            $fromWref = (int)$database->getVillageID($uid);
        }
        $vUnits = $database->getUnit($fromWref, false);
        for ($i = 1; $i <= 10; $i++) {
            $unitCol = 'u' . (($userTribe - 1) * 10 + $i);
            $reqCount = (int)($troops['u' . $i] ?? ($troops[$i] ?? 0));
            $avail = (int)($vUnits[$unitCol] ?? 0);
            if ($reqCount > $avail) {
                return ['status' => 'error', 'message' => "Jumlah pasukan tidak mencukupi di desa pemberangkatan."];
            }
        }

        // Deduct & register
        self::deductAndRegisterMember($expeditionId, $uid, $fromWref, $userTribe, $troops, $hasHero, $atkPower);
        self::recordDailyUsage($uid);

        return [
            'status' => 'success',
            'expedition_id' => $expeditionId,
            'message' => "Berhasil bergabung ke Forward Camp! Pasukan Anda telah dikerahkan ke titik kumpul ekspedisi."
        ];
    }

    /**
     * Potong pasukan dari desa asal dan masukkan ke tabel s1_expedition_members.
     */
    private static function deductAndRegisterMember(
        int $expeditionId,
        int $uid,
        int $fromWref,
        int $tribe,
        array $troops,
        bool $hasHero,
        int $atkPower
    ): void {
        global $database;
        $now = time();

        $uCols = [];
        $uDeductUpdates = [];
        for ($i = 1; $i <= 10; $i++) {
            $cnt = (int)($troops['u' . $i] ?? ($troops[$i] ?? 0));
            $uCols['u' . $i] = $cnt;
            if ($cnt > 0) {
                $globalCol = 'u' . (($tribe - 1) * 10 + $i);
                $uDeductUpdates[] = "`$globalCol` = GREATEST(0, `$globalCol` - $cnt)";
            }
        }

        // Potong dari units di desa asal
        if (!empty($uDeductUpdates)) {
            $sqlDeduct = "UPDATE `" . TB_PREFIX . "units` SET " . implode(', ', $uDeductUpdates) . " WHERE vref = $fromWref";
            mysqli_query($database->dblink, $sqlDeduct);

            // Sinkronkan starvupdate di desa saat pasukan berkurang
            $database->setVillageFields($fromWref, ['starvupdate'], [$now]);
            $database->addStarvationData($fromWref);

            if (method_exists($database, 'clearUnitsCache')) {
                $database->clearUnitsCache();
            }
        }

        // Insert ke s1_expedition_members
        $hasHeroVal = $hasHero ? 1 : 0;
        $q = "INSERT INTO `" . TB_PREFIX . "expedition_members` 
              (`expedition_id`, `uid`, `from_wref`, `tribe`, `u1`, `u2`, `u3`, `u4`, `u5`, `u6`, `u7`, `u8`, `u9`, `u10`, `has_hero`, `attack_power`, `joined_at`)
              VALUES (
                  $expeditionId, $uid, $fromWref, $tribe,
                  {$uCols['u1']}, {$uCols['u2']}, {$uCols['u3']}, {$uCols['u4']}, {$uCols['u5']},
                  {$uCols['u6']}, {$uCols['u7']}, {$uCols['u8']}, {$uCols['u9']}, {$uCols['u10']},
                  $hasHeroVal, $atkPower, $now
              )";
        mysqli_query($database->dblink, $q);
    }

    /**
     * Batalkan ekspedisi dan kembalikan seluruh pasukan ke desa masing-masing.
     */
    public static function cancelExpedition(int $expeditionId, int $uid = 0, string $reason = ''): array {
        global $database;
        self::ensureTables();

        $expeditionId = (int)$expeditionId;
        $expRows = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expeditions` WHERE id = $expeditionId LIMIT 1");
        if (empty($expRows[0])) {
            return ['status' => 'error', 'message' => "Sesi ekspedisi tidak ditemukan."];
        }
        $exp = $expRows[0];
        if ((int)$exp['status'] !== self::STATUS_LOBBY) {
            return ['status' => 'error', 'message' => "Hanya ekspedisi dalam status persiapan yang dapat dibatalkan."];
        }

        // Jika UID dispesifikasikan, cek apakah inisiator/leader
        if ($uid > 0 && $uid !== (int)$exp['leader_uid']) {
            return ['status' => 'error', 'message' => "Hanya pemimpin ekspedisi (Rally Leader) yang dapat membatalkan ekspedisi."];
        }

        self::refundMembers($expeditionId, !empty($reason) ? $reason : "Dibatalkan oleh pemimpin ekspedisi.");
        $database->query("UPDATE `" . TB_PREFIX . "expeditions` SET `status` = " . self::STATUS_CANCELLED . " WHERE id = $expeditionId");

        return ['status' => 'success', 'message' => "Ekspedisi berhasil dibatalkan dan seluruh pasukan telah dipulangkan ke desa masing-masing."];
    }

    /**
     * Kembalikan semua pasukan anggota dan pulihkan batas harian.
     */
    public static function refundMembers(int $expeditionId, string $reason): void {
        global $database;
        $members = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expedition_members` WHERE expedition_id = $expeditionId");
        if (empty($members)) {
            return;
        }

        $now = time();
        foreach ($members as $m) {
            $mUid = (int)$m['uid'];
            $mWref = (int)$m['from_wref'];
            $mTribe = (int)$m['tribe'];

            // Kembalikan prajurit ke units desa asal
            $restoreUpdates = [];
            for ($i = 1; $i <= 10; $i++) {
                $cnt = (int)($m['u' . $i] ?? 0);
                if ($cnt > 0) {
                    $globalCol = 'u' . (($mTribe - 1) * 10 + $i);
                    $restoreUpdates[] = "`$globalCol` = `$globalCol` + $cnt";
                }
            }

            if (!empty($restoreUpdates) && $mWref > 0) {
                // Pastikan baris di tabel units ada
                $qEnsure = "INSERT INTO `" . TB_PREFIX . "units` (`vref`) VALUES ($mWref) ON DUPLICATE KEY UPDATE `vref` = `vref`";
                mysqli_query($database->dblink, $qEnsure);

                $sqlRestore = "UPDATE `" . TB_PREFIX . "units` SET " . implode(', ', $restoreUpdates) . " WHERE vref = $mWref";
                mysqli_query($database->dblink, $sqlRestore);

                // Sinkronkan starvupdate ke waktu sekarang agar tidak terkena akumulasi timedif kelaparan retroaktif
                $database->setVillageFields($mWref, ['starvupdate'], [$now]);
                $database->addStarvationData($mWref);
            }

            // Kembalikan kuota harian
            self::refundDailyUsage($mUid);

            // Kirim notifikasi Notice Type 28
            $noticeTopic = "🛡️ [FORWARD CAMP] Ekspedisi Aliansi Dibatalkan";
            $noticeMsg = "type=expedition_report&action=cancelled&expedition_id=" . $expeditionId . "&reason=" . urlencode($reason) . "&message=" . urlencode("Sesi Forward Camp ekspedisi telah dibatalkan ($reason). Seluruh armada pasukan Anda telah dipulangkan kembali ke desa dengan selamat tanpa kerugian apapun.");
            $database->addNotice($mUid, $mWref, 0, 28, $noticeTopic, $noticeMsg, $now);
        }

        if (method_exists($database, 'clearUnitsCache')) {
            $database->clearUnitsCache();
        }
    }

    /**
     * Eksekusi benturan pertempuran gabungan (Unified Battlegroup Assault).
     */
    public static function resolveAssault(int $expeditionId): array {
        global $database;
        self::ensureTables();
        $now = time();

        $expRows = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expeditions` WHERE id = $expeditionId LIMIT 1");
        if (empty($expRows[0])) {
            return ['status' => 'error', 'message' => "Ekspedisi tidak ditemukan."];
        }
        $exp = $expRows[0];
        $campId = (int)$exp['camp_id'];
        $targetWref = (int)$exp['target_wref'];

        // Ambil data anggota ekspedisi
        $members = $database->query_return("SELECT * FROM `" . TB_PREFIX . "expedition_members` WHERE expedition_id = $expeditionId");
        if (count($members) < self::MIN_MEMBERS) {
            // Kuota tidak terpenuhi saat peluncuran -> auto-refund!
            self::refundMembers($expeditionId, "Kuota minimum " . self::MIN_MEMBERS . " anggota tidak terpenuhi.");
            $database->query("UPDATE `" . TB_PREFIX . "expeditions` SET `status` = " . self::STATUS_CANCELLED . " WHERE id = $expeditionId");
            return ['status' => 'cancelled', 'message' => "Kuota minimum tidak terpenuhi. Ekspedisi dibatalkan dan pasukan dipulangkan."];
        }

        // Cek apakah camp masih ada di peta
        $camps = $database->query_return("SELECT * FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId AND status = 1 LIMIT 1");
        if (empty($camps[0])) {
            // Camp sudah lenyap -> auto-recall pasukan!
            self::refundMembers($expeditionId, "Target Sarang Bandit telah dihancurkan oleh pihak lain.");
            $database->query("UPDATE `" . TB_PREFIX . "expeditions` SET `status` = " . self::STATUS_CANCELLED . " WHERE id = $expeditionId");
            return ['status' => 'cancelled', 'message' => "Target telah dihancurkan sebelumnya. Pasukan berbalik arah pulang."];
        }
        $camp = $camps[0];

        // Kumpulkan suku anggota dan hitung sinergi
        $tribes = [];
        $totalAttack = 0;
        $memberTroopsMap = [];
        $memberDamageMap = [];

        foreach ($members as $m) {
            $mUid = (int)$m['uid'];
            $mTribe = (int)$m['tribe'];
            $tribes[] = $mTribe;

            $troops = [];
            for ($i = 1; $i <= 10; $i++) {
                $troops['u' . $i] = (int)$m['u' . $i];
            }
            $memberTroopsMap[] = [
                'uid' => $mUid,
                'tribe' => $mTribe,
                'troops' => $troops
            ];

            $mAtk = self::calculateTroopsAttackPower($mTribe, $troops, (bool)$m['has_hero'], $mUid);
            $totalAttack += $mAtk;
            $memberDamageMap[$mUid] = $mAtk;
        }

        $synergies = self::computeSynergiesFromTribes($tribes);

        // Terapkan Teuton Offense (+10%) & Coalition Offense
        $totalAttack = (int)round($totalAttack * (1.0 + $synergies['offense_buff']));

        // Terapkan Critical Strike Huns jika aktif (+15%)
        if ($synergies['crit_strike'] > 0) {
            $totalAttack = (int)round($totalAttack * (1.0 + $synergies['crit_strike']));
        }

        // Ambil data penjaga camp di units target
        $campUnits = $database->getUnit($targetWref, false);
        $totalDefPower = 0;
        $totalDefTroops = 0;

        // Muat unitdata untuk hitung defense penjaga
        require_once dirname(__DIR__) . '/Data/unitdata.php';
        for ($i = 1; $i <= 50; $i++) {
            $uCnt = (int)($campUnits['u' . $i] ?? 0);
            if ($uCnt > 0) {
                $totalDefTroops += $uCnt;
                // Estimasi power penjaga alam/bandit
                $uVar = 'u' . $i;
                $uStat = isset($$uVar) ? $$uVar : ['di' => 40, 'dc' => 40];
                $avgDef = (($uStat['di'] ?? 40) + ($uStat['dc'] ?? 40)) / 2;
                $totalDefPower += ($uCnt * $avgDef);
            }
        }

        // Terapkan Pelemahan Armor Teuton (-15% defense musuh)
        if ($synergies['enemy_def_reduction'] > 0) {
            $totalDefPower = (int)round($totalDefPower * (1.0 - $synergies['enemy_def_reduction']));
        }

        // Terapkan Viking Demoralize (-8% enemy attack/def)
        if ($synergies['enemy_atk_reduction'] > 0) {
            $totalDefPower = (int)round($totalDefPower * (1.0 - $synergies['enemy_atk_reduction']));
        }

        $totalDefPower = max(10, $totalDefPower);

        // FORMULA PERTEMPURAN TRAVIAN:
        // Jika Attacker > Defender -> Menang!
        $isVictory = ($totalAttack >= $totalDefPower);
        $rawLossRatio = 0.0;
        if ($isVictory) {
            // Rumus exponent kerugian pemenang
            $rawLossRatio = pow(($totalDefPower / $totalAttack), 1.5);
            $rawLossRatio = min(0.90, max(0.05, $rawLossRatio));
        } else {
            // Kekalahan ekspedisi
            $rawLossRatio = 1.0;
        }

        // Hitung Shared Casualty Proportion
        $casualtyData = self::calculateSharedCasualties($memberTroopsMap, $rawLossRatio, $tribes);
        $effectiveLossRatio = $casualtyData['effective_loss_ratio'];

        // Pulangkan sisa pasukan yang selamat ke desa asal masing-masing
        foreach ($casualtyData['members'] as $mUid => $cMember) {
            $mWref = 0;
            $mTribe = 1;
            foreach ($members as $mRow) {
                if ((int)$mRow['uid'] === $mUid) {
                    $mWref = (int)$mRow['from_wref'];
                    $mTribe = (int)$mRow['tribe'];
                    break;
                }
            }

            $survivedUpdates = [];
            foreach ($cMember['troops_survived'] as $uKey => $sCount) {
                $sCount = (int)$sCount;
                if ($sCount > 0) {
                    $uIdx = (int)substr($uKey, 1);
                    $globalCol = 'u' . (($mTribe - 1) * 10 + $uIdx);
                    $survivedUpdates[] = "`$globalCol` = `$globalCol` + $sCount";
                }
            }

            if (!empty($survivedUpdates) && $mWref > 0) {
                // Pastikan baris di tabel units ada
                $qEnsure = "INSERT INTO `" . TB_PREFIX . "units` (`vref`) VALUES ($mWref) ON DUPLICATE KEY UPDATE `vref` = `vref`";
                mysqli_query($database->dblink, $qEnsure);

                $sqlSurv = "UPDATE `" . TB_PREFIX . "units` SET " . implode(', ', $survivedUpdates) . " WHERE vref = $mWref";
                mysqli_query($database->dblink, $sqlSurv);

                // Sinkronkan starvupdate ke waktu sekarang agar tidak terkena akumulasi timedif kelaparan retroaktif
                $database->setVillageFields($mWref, ['starvupdate'], [$now]);
                $database->addStarvationData($mWref);
            }

            // Update catatan persentase kerugian di s1_expedition_members
            $database->query("
                UPDATE `" . TB_PREFIX . "expedition_members`
                SET `losses_pct` = " . round($effectiveLossRatio * 100, 2) . ",
                    `damage_dealt` = " . (int)($memberDamageMap[$mUid] ?? 0) . "
                WHERE `expedition_id` = $expeditionId AND `uid` = $mUid
            ");
        }

        if (method_exists($database, 'clearUnitsCache')) {
            $database->clearUnitsCache();
        }

        $now = time();

        if ($isVictory) {
            // Habisi semua penjaga di desa bandit
            $database->query("UPDATE `" . TB_PREFIX . "units` SET u31=0,u32=0,u33=0,u34=0,u35=0,u36=0,u37=0,u38=0,u39=0,u40=0 WHERE vref = $targetWref");

            // Ambil sisa sumber daya
            $resRow = $database->query_return("SELECT wood, clay, iron, crop FROM `" . TB_PREFIX . "vdata` WHERE wref = $targetWref LIMIT 1");
            $bWood = !empty($resRow[0]['wood']) ? (int)round($resRow[0]['wood']) : (int)$camp['bounty_wood'];
            $bClay = !empty($resRow[0]['clay']) ? (int)round($resRow[0]['clay']) : (int)$camp['bounty_clay'];
            $bIron = !empty($resRow[0]['iron']) ? (int)round($resRow[0]['iron']) : (int)$camp['bounty_iron'];
            $bCrop = !empty($resRow[0]['crop']) ? (int)round($resRow[0]['crop']) : (int)$camp['bounty_crop'];

            $bossEssencePool = defined('BANDIT_BOSS_ESSENCE_POOL') ? (int)BANDIT_BOSS_ESSENCE_POOL : 8;
            $poolTotal = [
                'wood' => $bWood,
                'clay' => $bClay,
                'iron' => $bIron,
                'crop' => $bCrop,
                'silver' => (int)$camp['reward_silver'],
                'cp' => (int)$camp['reward_cp'],
                'exp' => (int)$camp['reward_exp'],
                'essence' => ($camp['tier'] == 4) ? $bossEssencePool : (($camp['tier'] == 3) ? 2 : 0)
            ];

            // Hitung pembagian reward (50% Flat + 50% Damage)
            $rewards = self::calculateRewardDistribution($memberDamageMap, $poolTotal, $tribes);

            // Berikan hadiah ke masing-masing anggota
            if (!class_exists('BlackMarket')) {
                require_once dirname(__DIR__) . '/BlackMarket.php';
            }
            if (!class_exists('HeroItems')) {
                require_once dirname(__DIR__) . '/HeroItems.php';
            }
            $heroItems = new HeroItems();

            foreach ($rewards as $rUid => $r) {
                $mWref = 0;
                foreach ($members as $mRow) {
                    if ((int)$mRow['uid'] === $rUid) {
                        $mWref = (int)$mRow['from_wref'];
                        break;
                    }
                }
                if ($mWref <= 0) {
                    $mWref = (int)$database->getVillageID($rUid);
                }

                // Tambahkan Silver
                if (!empty($r['silver'])) {
                    BlackMarket::addSilver($rUid, (int)$r['silver']);
                }

                // Tambahkan Hero EXP
                if (!empty($r['exp'])) {
                    $database->query("UPDATE `" . TB_PREFIX . "hero` SET `experience` = IFNULL(experience, 0) + " . (int)$r['exp'] . " WHERE `uid` = $rUid");
                }

                // Tambahkan CP
                if (!empty($r['cp'])) {
                    $database->query("UPDATE `" . TB_PREFIX . "users` SET `cp` = IFNULL(cp, 0) + " . (int)$r['cp'] . " WHERE `id` = $rUid");
                }

                // Kirim Sumber Daya ke desa
                if ($mWref > 0 && ($r['wood'] > 0 || $r['clay'] > 0 || $r['iron'] > 0 || $r['crop'] > 0)) {
                    $database->query("
                        UPDATE `" . TB_PREFIX . "vdata`
                        SET `wood` = `wood` + " . (int)$r['wood'] . ",
                            `clay` = `clay` + " . (int)$r['clay'] . ",
                            `iron` = `iron` + " . (int)$r['iron'] . ",
                            `crop` = `crop` + " . (int)$r['crop'] . "
                        WHERE `wref` = $mWref
                    ");
                }

                // Berikan World Essence jika ada
                if (!empty($r['essence'])) {
                    $heroItems->addItem($rUid, 210, (int)$r['essence']);
                }

                // Update data reward di s1_expedition_members
                $database->query("
                    UPDATE `" . TB_PREFIX . "expedition_members`
                    SET `reward_wood` = " . (int)$r['wood'] . ",
                        `reward_clay` = " . (int)$r['clay'] . ",
                        `reward_iron` = " . (int)$r['iron'] . ",
                        `reward_crop` = " . (int)$r['crop'] . ",
                        `reward_exp` = " . (int)$r['exp'] . ",
                        `reward_cp` = " . (int)$r['cp'] . ",
                        `reward_silver` = " . (int)$r['silver'] . ",
                        `reward_essence` = " . (int)$r['essence'] . "
                    WHERE `expedition_id` = $expeditionId AND `uid` = $rUid
                ");

                // Kirim laporan kemenangan Notice 28
                $synergyBadges = [];
                foreach ($synergies['synergies'] as $syn) {
                    $synergyBadges[] = "• " . $syn['name'] . " (" . $syn['tribe_name'] . "): " . $syn['desc'];
                }
                if (!empty($synergies['coalition_bonus'])) {
                    $synergyBadges[] = "• " . $synergies['coalition_bonus']['badge'];
                }

                $noticeTopic = "👑 [EKSPEDISI ALIANSI: MENANG] {$camp['name']} Berhasil Ditaklukkan!";
                $noticeText = "type=expedition_report&action=victory&camp_name=" . urlencode($camp['name']) . "&losses_pct=" . round($effectiveLossRatio * 100, 2) .
                              "&wood=" . $r['wood'] . "&clay=" . $r['clay'] . "&iron=" . $r['iron'] . "&crop=" . $r['crop'] .
                              "&silver=" . $r['silver'] . "&cp=" . $r['cp'] . "&exp=" . $r['exp'] . "&essence=" . $r['essence'] .
                              "&expedition_id=" . $expeditionId .
                              "&message=" . urlencode(
                                  "Serangan gabungan Forward Camp berhasil menumbangkan {$camp['name']}!\n\n" .
                                  "🛡️ Sinergi Suku Aktif:\n" . implode("\n", $synergyBadges) . "\n\n" .
                                  "📊 Statistik Pasukan Bersama:\n" .
                                  "• Rata-rata Kerugian Gugur (Shared Casualty): " . round($effectiveLossRatio * 100, 2) . "%\n" .
                                  "• Kontribusi Kerusakan Anda: " . number_format($r['damage']) . " (" . $r['pct'] . "%)\n\n" .
                                  "🎁 Hadiah Ekspedisi Anda (50% Flat + 50% Kontribusi):\n" .
                                  "🌲 Kayu: +" . number_format($r['wood']) . " | 🧱 Liat: +" . number_format($r['clay']) . " | ⛏️ Besi: +" . number_format($r['iron']) . " | 🌾 Gandum: +" . number_format($r['crop']) . "\n" .
                                  "🪙 Koin Silver: +" . number_format($r['silver']) . " | 🏛️ CP: +" . number_format($r['cp']) . " | ⭐ Hero EXP: +" . number_format($r['exp']) .
                                  ($r['essence'] > 0 ? ("\n💎 World Essence: +" . number_format($r['essence']) . " pcs") : "")
                              );

                $database->addNotice($rUid, $targetWref, 0, 28, $noticeTopic, $noticeText, $now);
            }

            // Hapus atau kosongkan camp
            $database->query("
                UPDATE `" . TB_PREFIX . "bandit_camps`
                SET `cur_hp` = 0, `status` = 0, `cleared_by` = " . (int)$exp['leader_uid'] . ", `cleared_time` = $now
                WHERE `id` = $campId
            ");

            // Raze target village from map via BanditCamp
            if (method_exists('BanditCamp', 'razeBanditVillage')) {
                BanditCamp::razeBanditVillage($targetWref);
            }

            $database->query("UPDATE `" . TB_PREFIX . "expeditions` SET `status` = " . self::STATUS_RESOLVED . " WHERE id = $expeditionId");

            return [
                'status' => 'victory',
                'camp_id' => $campId,
                'camp_name' => $camp['name'],
                'effective_loss_ratio' => $effectiveLossRatio,
                'rewards' => $rewards
            ];

        } else {
            // Ekspedisi gagal / terpukul mundur
            $database->query("UPDATE `" . TB_PREFIX . "expeditions` SET `status` = " . self::STATUS_FAILED . " WHERE id = $expeditionId");

            foreach ($members as $mRow) {
                $mUid = (int)$mRow['uid'];
                $noticeTopic = "💀 [EKSPEDISI ALIANSI: TERPUKUL MUNDUR] Pertahanan {$camp['name']} Terlalu Kuat!";
                $noticeText = "type=expedition_report&action=defeat&camp_name=" . urlencode($camp['name']) . "&losses_pct=" . round($effectiveLossRatio * 100, 2) .
                              "&expedition_id=" . $expeditionId .
                              "&message=" . urlencode(
                                  "Serangan gabungan Forward Camp ke {$camp['name']} tidak berhasil menembus pertahanan lawan.\n" .
                                  "Pasukan ekspedisi menanggung kerugian bersama sebesar " . round($effectiveLossRatio * 100, 2) . "%."
                              );
                $database->addNotice($mUid, $targetWref, 0, 28, $noticeTopic, $noticeText, $now);
            }

            return [
                'status' => 'failed',
                'camp_id' => $campId,
                'camp_name' => $camp['name'],
                'effective_loss_ratio' => $effectiveLossRatio
            ];
        }
    }

    /**
     * Memproses ekspedisi aktif secara otomatis (Dipanggil dari Automation.php atau cron).
     */
    public static function processExpeditions(bool $force = false): array {
        global $database;
        self::ensureTables();

        $now = time();
        $processed = ['resolved' => 0, 'cancelled' => 0];

        // Cari ekspedisi dalam status lobby yang sudah mencapai impact_time
        $due = $database->query_return("SELECT id FROM `" . TB_PREFIX . "expeditions` WHERE `status` = " . self::STATUS_LOBBY . " AND `impact_time` <= $now");
        if (!empty($due)) {
            foreach ($due as $exp) {
                $eId = (int)$exp['id'];
                $res = self::resolveAssault($eId);
                if (isset($res['status'])) {
                    if ($res['status'] === 'victory' || $res['status'] === 'failed') {
                        $processed['resolved']++;
                    } elseif ($res['status'] === 'cancelled') {
                        $processed['cancelled']++;
                    }
                }
            }
        }

        return $processed;
    }

    /**
     * Ambil data lengkap suatu ekspedisi beserta anggotanya.
     */
    public static function getExpeditionDetails(int $expeditionId): ?array {
        global $database;
        self::ensureTables();
        $expeditionId = (int)$expeditionId;

        $expRows = $database->query_return("
            SELECT e.*, b.name as camp_name, b.tier as camp_tier, b.cur_hp, b.max_hp, b.bounty_wood, b.bounty_clay, b.bounty_iron, b.bounty_crop, b.reward_silver, b.reward_cp, b.reward_exp, w.x, w.y
            FROM `" . TB_PREFIX . "expeditions` e
            JOIN `" . TB_PREFIX . "bandit_camps` b ON e.camp_id = b.id
            JOIN `" . TB_PREFIX . "wdata` w ON e.target_wref = w.id
            WHERE e.id = $expeditionId
            LIMIT 1
        ");
        if (empty($expRows[0])) {
            return null;
        }

        $exp = $expRows[0];
        $members = $database->query_return("
            SELECT m.*, u.username
            FROM `" . TB_PREFIX . "expedition_members` m
            LEFT JOIN `" . TB_PREFIX . "users` u ON m.uid = u.id
            WHERE m.expedition_id = $expeditionId
            ORDER BY m.joined_at ASC
        ");

        $tribes = [];
        $totalAttack = 0;
        foreach ($members as $m) {
            $tribes[] = (int)$m['tribe'];
            $totalAttack += (int)$m['attack_power'];
        }

        $synergies = self::computeSynergiesFromTribes($tribes);

        return [
            'expedition' => $exp,
            'members' => $members,
            'member_count' => count($members),
            'total_attack' => $totalAttack,
            'synergies' => $synergies
        ];
    }

    /**
     * Ambil ekspedisi lobi yang sedang aktif untuk sebuah sarang bandit tertentu.
     */
    public static function getActiveCampExpedition(int $campId): ?array {
        global $database;
        self::ensureTables();
        $campId = (int)$campId;

        $exp = $database->query_return("SELECT id FROM `" . TB_PREFIX . "expeditions` WHERE camp_id = $campId AND status = " . self::STATUS_LOBBY . " LIMIT 1");
        if (!empty($exp[0]['id'])) {
            return self::getExpeditionDetails((int)$exp[0]['id']);
        }
        return null;
    }

    /**
     * Ambil daftar ekspedisi aktif untuk aliansi tertentu.
     */
    public static function getAllianceActiveExpeditions(int $allianceId): array {
        global $database;
        self::ensureTables();
        $allianceId = (int)$allianceId;
        if ($allianceId <= 0) return [];

        $rows = $database->query_return("
            SELECT e.id
            FROM `" . TB_PREFIX . "expeditions` e
            WHERE e.alliance_id = $allianceId AND e.status = " . self::STATUS_LOBBY . "
            ORDER BY e.impact_time ASC
        ");

        $result = [];
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $det = self::getExpeditionDetails((int)$r['id']);
                if ($det) {
                    $result[] = $det;
                }
            }
        }
        return $result;
    }
}
