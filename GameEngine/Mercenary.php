<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Mercenary.php                                             ##
##  Type           : Mercenary Enclave (Tentara Bayaran) Backend Engine        ##
##  Purpose        : Independent neutral mercenary hiring, garrison defense,  ##
##                   upkeep calculation, and battle casualties                 ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

require_once __DIR__ . '/BlackMarket.php';

class Mercenary
{
    /**
     * Database connection helper.
     */
    protected static function db()
    {
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
     * Ensure database table exists.
     */
    public static function ensureTable(): void
    {
        $db = self::db();
        if (!$db) return;

        $q = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "mercenaries` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `vref` int(11) NOT NULL,
            `uid` int(11) NOT NULL,
            `m1` int(11) NOT NULL DEFAULT 0,
            `m2` int(11) NOT NULL DEFAULT 0,
            `m3` int(11) NOT NULL DEFAULT 0,
            `m4` int(11) NOT NULL DEFAULT 0,
            `updated` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vref` (`vref`),
            KEY `uid` (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        @mysqli_query($db, $q);
    }

    /**
     * 4 Neutral Mercenary Unit Definitions.
     */
    public static function getUnitsInfo(): array
    {
        return [
            1 => [
                'key'         => 'm1',
                'name'        => 'Garda Zirah Besi',
                'en_name'     => 'Ironclad Vanguard',
                'icon'        => '🛡️',
                'type'        => 'Infanteri Bertahan Seimbang',
                'atk'         => 25,
                'di'          => 110,
                'dc'          => 105,
                'pop'         => 1,
                'upkeep'      => 1,
                'cost_silver' => 12,
                'desc'        => 'Zirah pelat baja berat anti-kuda dan anti-infanteri yang kokoh membentengi desa dari serbuan musuh.'
            ],
            2 => [
                'key'         => 'm2',
                'name'        => 'Pemanah Tembok Aegis',
                'en_name'     => 'Aegis Wall Archer',
                'icon'        => '🏹',
                'type'        => 'Infanteri Anti-Infanteri',
                'atk'         => 20,
                'di'          => 140,
                'dc'          => 65,
                'pop'         => 1,
                'upkeep'      => 1,
                'cost_silver' => 14,
                'desc'        => 'Pemanah benteng dengan pertahanan jarak jauh yang sangat mematikan terhadap infanteri penyerbu lawan.'
            ],
            3 => [
                'key'         => 'm3',
                'name'        => 'Ksatria Perisai Templar',
                'en_name'     => 'Templar Shield Knight',
                'icon'        => '🐎',
                'type'        => 'Kavaleri Anti-Kavaleri',
                'atk'         => 35,
                'di'          => 120,
                'dc'          => 180,
                'pop'         => 2,
                'upkeep'      => 2,
                'cost_silver' => 25,
                'desc'        => 'Kavaleri pelindung berlapis baja tebal dengan tombak panjang khusus untuk mematahkan dan meremukkan kavaleri musuh.'
            ],
            4 => [
                'key'         => 'm4',
                'name'        => 'Penjaga Gerbang Praetorian',
                'en_name'     => 'Praetorian Citadel Guard',
                'icon'        => '🏛️',
                'type'        => 'Serdadu Elit Benteng',
                'atk'         => 45,
                'di'          => 175,
                'dc'          => 165,
                'pop'         => 2,
                'upkeep'      => 2,
                'cost_silver' => 30,
                'desc'        => 'Pengawal gerbang benteng elit legendaris berkekuatan luar biasa tinggi yang tangguh menahan gempuran segala jenis pasukan musuh.'
            ],
        ];
    }

    /**
     * Get active mercenary garrison for a village.
     */
    public static function getGarrison(int $vref): array
    {
        self::ensureTable();
        $db = self::db();
        $res = [
            'm1' => 0, 'm2' => 0, 'm3' => 0, 'm4' => 0,
            'total_troops' => 0,
            'upkeep' => 0
        ];

        if (!$db || $vref <= 0) return $res;

        $q = "SELECT m1, m2, m3, m4 FROM `" . TB_PREFIX . "mercenaries` WHERE vref = " . (int)$vref . " LIMIT 1";
        $query = @mysqli_query($db, $q);
        if ($query && ($row = mysqli_fetch_assoc($query))) {
            $res['m1'] = max(0, (int)$row['m1']);
            $res['m2'] = max(0, (int)$row['m2']);
            $res['m3'] = max(0, (int)$row['m3']);
            $res['m4'] = max(0, (int)$row['m4']);
            $res['total_troops'] = $res['m1'] + $res['m2'] + $res['m3'] + $res['m4'];
            $res['upkeep'] = ($res['m1'] * 1) + ($res['m2'] * 1) + ($res['m3'] * 2) + ($res['m4'] * 2);
        }
        return $res;
    }

    /**
     * Calculate mercenary upkeep for village crop balance.
     */
    public static function getVillageUpkeep(int $vref): int
    {
        $garrison = self::getGarrison($vref);
        return (int)$garrison['upkeep'];
    }

    /**
     * Calculate defense points provided by mercenaries.
     */
    public static function getGarrisonDefense(int $vref): array
    {
        $g = self::getGarrison($vref);
        if ($g['total_troops'] <= 0) {
            return ['dp' => 0, 'cdp' => 0, 'involve' => 0];
        }

        $dp = ($g['m1'] * 110) + ($g['m2'] * 140) + ($g['m3'] * 120) + ($g['m4'] * 175);
        $cdp = ($g['m1'] * 105) + ($g['m2'] * 65) + ($g['m3'] * 180) + ($g['m4'] * 165);

        return [
            'dp'      => $dp,
            'cdp'     => $cdp,
            'involve' => $g['total_troops']
        ];
    }

    /**
     * Apply battle casualty loss to mercenary garrison and return casualty details.
     * @return array ['troops' => [...], 'dead' => [...], 'total_troops' => int, 'total_dead' => int]
     */
    public static function applyCasualties(int $vref, float $casualtyRatio): array
    {
        $res = [
            'troops' => ['m1' => 0, 'm2' => 0, 'm3' => 0, 'm4' => 0],
            'dead'   => ['m1' => 0, 'm2' => 0, 'm3' => 0, 'm4' => 0],
            'total_troops' => 0,
            'total_dead'   => 0
        ];

        if ($vref <= 0) return $res;
        $casualtyRatio = min(1.0, max(0.0, (float)$casualtyRatio));

        $db = self::db();
        if (!$db) return $res;

        $g = self::getGarrison($vref);
        if ($g['total_troops'] <= 0) return $res;

        $res['troops'] = [
            'm1' => $g['m1'],
            'm2' => $g['m2'],
            'm3' => $g['m3'],
            'm4' => $g['m4']
        ];
        $res['total_troops'] = $g['total_troops'];

        if ($casualtyRatio > 0) {
            $dead1 = (int)round($g['m1'] * $casualtyRatio);
            $dead2 = (int)round($g['m2'] * $casualtyRatio);
            $dead3 = (int)round($g['m3'] * $casualtyRatio);
            $dead4 = (int)round($g['m4'] * $casualtyRatio);

            $res['dead'] = ['m1' => $dead1, 'm2' => $dead2, 'm3' => $dead3, 'm4' => $dead4];
            $res['total_dead'] = $dead1 + $dead2 + $dead3 + $dead4;

            $new1 = max(0, $g['m1'] - $dead1);
            $new2 = max(0, $g['m2'] - $dead2);
            $new3 = max(0, $g['m3'] - $dead3);
            $new4 = max(0, $g['m4'] - $dead4);

            $now = time();
            $q = "UPDATE `" . TB_PREFIX . "mercenaries`
                  SET m1 = $new1, m2 = $new2, m3 = $new3, m4 = $new4, updated = $now
                  WHERE vref = " . (int)$vref;
            @mysqli_query($db, $q);
        }

        return $res;
    }

    /**
     * Check and apply desertion when village crop is empty and negative.
     */
    public static function checkDesertion(int $vref): int
    {
        global $database;
        $db = self::db();
        if (!$db || $vref <= 0) return 0;

        $cropAvail = (int)$database->getCropAvailable($vref);
        if ($cropAvail > 0) return 0;

        $g = self::getGarrison($vref);
        if ($g['total_troops'] <= 0) return 0;

        // Deserters run away to stop village starvation: 15% of mercenaries leave per tick
        $desertRate = 0.15;
        $d1 = max(1, (int)ceil($g['m1'] * $desertRate));
        $d2 = max(1, (int)ceil($g['m2'] * $desertRate));
        $d3 = max(1, (int)ceil($g['m3'] * $desertRate));
        $d4 = max(1, (int)ceil($g['m4'] * $desertRate));

        $new1 = max(0, $g['m1'] - ($g['m1'] > 0 ? $d1 : 0));
        $new2 = max(0, $g['m2'] - ($g['m2'] > 0 ? $d2 : 0));
        $new3 = max(0, $g['m3'] - ($g['m3'] > 0 ? $d3 : 0));
        $new4 = max(0, $g['m4'] - ($g['m4'] > 0 ? $d4 : 0));

        $totalDeserted = ($g['m1'] - $new1) + ($g['m2'] - $new2) + ($g['m3'] - $new3) + ($g['m4'] - $new4);
        if ($totalDeserted > 0) {
            $now = time();
            $q = "UPDATE `" . TB_PREFIX . "mercenaries`
                  SET m1 = $new1, m2 = $new2, m3 = $new3, m4 = $new4, updated = $now
                  WHERE vref = " . (int)$vref;
            @mysqli_query($db, $q);
        }
        return $totalDeserted;
    }

    /**
     * Hire Mercenaries using Silver.
     */
    public static function hireMercenaries(int $uid, int $vref, array $counts): array
    {
        self::ensureTable();
        $db = self::db();

        $c1 = max(0, (int)($counts['m1'] ?? 0));
        $c2 = max(0, (int)($counts['m2'] ?? 0));
        $c3 = max(0, (int)($counts['m3'] ?? 0));
        $c4 = max(0, (int)($counts['m4'] ?? 0));

        $totalUnits = $c1 + $c2 + $c3 + $c4;
        if ($totalUnits <= 0) {
            return ['success' => false, 'message' => 'Masukkan jumlah tentara bayaran yang ingin disewa minimal 1 unit.'];
        }
        if ($totalUnits > 500) {
            return ['success' => false, 'message' => 'Maksimum rekrutmen dalam satu kontrak adalah 500 unit.'];
        }

        // Calculate Silver Cost
        $cost = ($c1 * 12) + ($c2 * 14) + ($c3 * 25) + ($c4 * 30);

        $silver = BlackMarket::getSilver($uid);
        if ($silver < $cost) {
            return [
                'success' => false,
                'message' => 'Koin Silver tidak mencukupi untuk kontrak ini. Dibutuhkan ' . number_format($cost) .
                             ' Silver (Saldo Anda: ' . number_format($silver) . ').'
            ];
        }

        // Spend Silver
        if (!BlackMarket::spendSilver($uid, $cost, 'Hire Mercenaries')) {
            return ['success' => false, 'message' => 'Gagal memproses pembayaran koin Silver.'];
        }

        // Upsert into mercenaries table
        $now = time();
        $q = "INSERT INTO `" . TB_PREFIX . "mercenaries` (`vref`, `uid`, `m1`, `m2`, `m3`, `m4`, `updated`)
              VALUES ($vref, $uid, $c1, $c2, $c3, $c4, $now)
              ON DUPLICATE KEY UPDATE
                m1 = m1 + $c1,
                m2 = m2 + $c2,
                m3 = m3 + $c3,
                m4 = m4 + $c4,
                updated = $now";
        @mysqli_query($db, $q);

        $desc = "Hired $c1 Garda Zirah, $c2 Pemanah Aegis, $c3 Ksatria Templar, $c4 Praetorian for $cost Silver";
        BlackMarket::logAction($uid, $vref, 'hire_mercenary', $desc, $cost);

        return [
            'success' => true,
            'message' => "⚔️ Kontrak bayaran disepakati! $totalUnits prajurit bayaran telah bergabung menjadi garnisun desa Anda (Biaya: " .
                         number_format($cost) . " Silver). Mereka siap bertarung membela desa!"
        ];
    }

    /**
     * Dismiss Mercenaries to reduce crop upkeep.
     */
    public static function dismissMercenaries(int $uid, int $vref, array $counts): array
    {
        self::ensureTable();
        $db = self::db();

        $d1 = max(0, (int)($counts['m1'] ?? 0));
        $d2 = max(0, (int)($counts['m2'] ?? 0));
        $d3 = max(0, (int)($counts['m3'] ?? 0));
        $d4 = max(0, (int)($counts['m4'] ?? 0));

        $totalDismiss = $d1 + $d2 + $d3 + $d4;
        if ($totalDismiss <= 0) {
            return ['success' => false, 'message' => 'Pilih jumlah unit yang ingin dipulangkan/dilepas.'];
        }

        $g = self::getGarrison($vref);
        if ($d1 > $g['m1'] || $d2 > $g['m2'] || $d3 > $g['m3'] || $d4 > $g['m4']) {
            return ['success' => false, 'message' => 'Jumlah unit yang dilepas melebihi pasukan yang ada di desa.'];
        }

        $now = time();
        $q = "UPDATE `" . TB_PREFIX . "mercenaries`
              SET m1 = m1 - $d1, m2 = m2 - $d2, m3 = m3 - $d3, m4 = m4 - $d4, updated = $now
              WHERE vref = $vref AND uid = $uid";
        @mysqli_query($db, $q);

        return [
            'success' => true,
            'message' => "Kontrak $totalDismiss serdadu bayaran telah diakhiri. Beban konsumsi gandum desa berkurang."
        ];
    }

    /**
     * Process POST requests from Mercenary UI.
     */
    public static function procPost(array $post): void
    {
        global $session, $village;

        if (empty($post['ft'])) return;

        $uid  = (int)($session->uid ?? 0);
        $vref = (int)($village->wid ?? 0);
        $id   = (int)($post['id'] ?? 17);

        if ($uid <= 0 || $vref <= 0) return;

        $result = null;

        if ($post['ft'] === 'merc_hire') {
            $counts = [
                'm1' => (int)($post['m1'] ?? 0),
                'm2' => (int)($post['m2'] ?? 0),
                'm3' => (int)($post['m3'] ?? 0),
                'm4' => (int)($post['m4'] ?? 0),
            ];
            $result = self::hireMercenaries($uid, $vref, $counts);
        } elseif ($post['ft'] === 'merc_dismiss') {
            $counts = [
                'm1' => (int)($post['m1'] ?? 0),
                'm2' => (int)($post['m2'] ?? 0),
                'm3' => (int)($post['m3'] ?? 0),
                'm4' => (int)($post['m4'] ?? 0),
            ];
            $result = self::dismissMercenaries($uid, $vref, $counts);
        }

        if ($result !== null) {
            $_SESSION['merc_flash'] = [
                'type'    => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
            header("Location: build.php?id=$id&t=6");
            exit;
        }
    }
}
