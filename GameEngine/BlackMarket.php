<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : BlackMarket.php                                           ##
##  Type           : Black Market (Pasar Gelap) Backend Engine                 ##
##  Purpose        : Smuggler trade, resource laundering, silver bundles,       ##
##                   and tactical contraband items                             ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

class BlackMarket
{
    const LAUNDER_FEE_PERCENT = 15; // 15% cut fee

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
     * Ensure database tables exist.
     */
    public static function ensureTables(): void
    {
        $db = self::db();
        if (!$db) return;

        $q = "CREATE TABLE IF NOT EXISTS `" . TB_PREFIX . "blackmarket_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uid` int(11) NOT NULL,
            `vref` int(11) NOT NULL,
            `action_type` varchar(32) NOT NULL,
            `details` text NOT NULL,
            `silver_cost` int(11) NOT NULL DEFAULT 0,
            `timestamp` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `uid` (`uid`),
            KEY `vref` (`vref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        @mysqli_query($db, $q);
    }

    /**
     * Get player's current Silver balance.
     */
    public static function getSilver(int $uid): int
    {
        $db = self::db();
        if (!$db || $uid <= 0) return 0;

        $stmt = @mysqli_prepare($db, "SELECT silver FROM `" . TB_PREFIX . "hero` WHERE uid = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $silver);
            $found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $found ? (int)$silver : 0;
        }
        return 0;
    }

    /**
     * Atomically spend player's Silver.
     */
    public static function spendSilver(int $uid, int $amount, string $reason = ''): bool
    {
        $db = self::db();
        $amount = (int)$amount;
        if (!$db || $uid <= 0 || $amount <= 0) return false;

        $stmt = @mysqli_prepare($db, "UPDATE `" . TB_PREFIX . "hero` SET silver = silver - ? WHERE uid = ? AND silver >= ? LIMIT 1");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'iii', $amount, $uid, $amount);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $ok;
    }

    /**
     * Add Silver to player.
     */
    public static function addSilver(int $uid, int $amount): bool
    {
        $db = self::db();
        $amount = (int)$amount;
        if (!$db || $uid <= 0 || $amount <= 0) return false;

        $stmt = @mysqli_prepare($db, "UPDATE `" . TB_PREFIX . "hero` SET silver = silver + ? WHERE uid = ? LIMIT 1");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'ii', $amount, $uid);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $ok;
    }

    /**
     * Log a Black Market transaction.
     */
    public static function logAction(int $uid, int $vref, string $type, string $details, int $silver = 0): void
    {
        $db = self::db();
        if (!$db) return;

        self::ensureTables();
        $typeEsc = mysqli_real_escape_string($db, $type);
        $detailsEsc = mysqli_real_escape_string($db, $details);
        $time = time();

        $q = "INSERT INTO `" . TB_PREFIX . "blackmarket_log` (`uid`, `vref`, `action_type`, `details`, `silver_cost`, `timestamp`)
              VALUES ($uid, $vref, '$typeEsc', '$detailsEsc', $silver, $time)";
        @mysqli_query($db, $q);
    }

    /**
     * 1. Resource Laundering: Convert resources with 15% cut fee without Gold.
     * $fromRes and $toRes are 1: Wood, 2: Clay, 3: Iron, 4: Crop.
     */
    public static function launderResources(int $uid, int $vref, int $fromRes, int $toRes, int $amount): array
    {
        global $database, $village;

        $resNames = [1 => 'Kayu', 2 => 'Tanah Liat', 3 => 'Besi', 4 => 'Gandum'];
        if (!isset($resNames[$fromRes], $resNames[$toRes])) {
            return ['success' => false, 'message' => 'Pilihan jenis sumber daya tidak valid.'];
        }
        if ($fromRes === $toRes) {
            return ['success' => false, 'message' => 'Sumber daya asal dan tujuan tidak boleh sama.'];
        }
        if ($amount < 100) {
            return ['success' => false, 'message' => 'Jumlah minimum pencucian adalah 100 sumber daya.'];
        }

        // Check available stock in village
        $avail = [
            1 => (int)$database->getWoodAvailable($vref, false),
            2 => (int)$database->getClayAvailable($vref, false),
            3 => (int)$database->getIronAvailable($vref, false),
            4 => (int)$database->getCropAvailable($vref, false),
        ];

        if ($avail[$fromRes] < $amount) {
            return ['success' => false, 'message' => 'Persediaan ' . $resNames[$fromRes] . ' Anda tidak mencukupi (Tersedia: ' . number_format($avail[$fromRes]) . ').'];
        }

        $yield = (int)floor($amount * (1 - (self::LAUNDER_FEE_PERCENT / 100)));
        if ($yield <= 0) {
            return ['success' => false, 'message' => 'Jumlah hasil pencucian terlalu kecil.'];
        }

        // Deduct source resource, add target resource
        $resDeduct = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $resAdd    = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $resDeduct[$fromRes] = $amount;
        $resAdd[$toRes]       = $yield;

        $okDeduct = $database->modifyResource($vref, $resDeduct[1], $resDeduct[2], $resDeduct[3], $resDeduct[4], 0, true);
        if (!$okDeduct) {
            return ['success' => false, 'message' => 'Gagal memotong sumber daya asal.'];
        }

        $okAdd = $database->modifyResource($vref, $resAdd[1], $resAdd[2], $resAdd[3], $resAdd[4], 1, true);

        $details = "Laundered $amount " . $resNames[$fromRes] . " -> $yield " . $resNames[$toRes] . " (Fee 15%)";
        self::logAction($uid, $vref, 'launder', $details, 0);

        return [
            'success' => true,
            'message' => "Operasi cuci berhasil! " . number_format($amount) . " " . $resNames[$fromRes] .
                         " dicuci menjadi " . number_format($yield) . " " . $resNames[$toRes] .
                         " (Potongan komisi penyelundup 15%: " . number_format($amount - $yield) . ")."
        ];
    }

    /**
     * 2. Smuggler's Resource Shipment: Buy resource bundles with Silver.
     */
    public static function buyResourceShipment(int $uid, int $vref, int $tier): array
    {
        global $database;

        $shipments = [
            1 => ['name' => 'Paket Pasokan Kroco', 'silver' => 10, 'amount' => 2000],
            2 => ['name' => 'Kargo Selundupan Pedagang', 'silver' => 25, 'amount' => 6000],
            3 => ['name' => 'Peti Harta Saudagar Gelap', 'silver' => 60, 'amount' => 16000],
        ];

        if (!isset($shipments[$tier])) {
            return ['success' => false, 'message' => 'Paket pengiriman tidak valid.'];
        }

        $ship = $shipments[$tier];
        $cost = (int)$ship['silver'];
        $amt  = (int)$ship['amount'];

        $currentSilver = self::getSilver($uid);
        if ($currentSilver < $cost) {
            return ['success' => false, 'message' => 'Koin Silver tidak mencukupi. Butuh ' . $cost . ' Silver (Saldo Anda: ' . $currentSilver . ').'];
        }

        if (!self::spendSilver($uid, $cost, 'Black Market Shipment Tier ' . $tier)) {
            return ['success' => false, 'message' => 'Gagal memproses transaksi Silver.'];
        }

        // Add resources directly to village
        $database->modifyResource($vref, $amt, $amt, $amt, $amt, 1, true);

        $details = "Bought " . $ship['name'] . " ($amt each res) for $cost Silver";
        self::logAction($uid, $vref, 'shipment', $details, $cost);

        return [
            'success' => true,
            'message' => "Kargo penyelundup tiba dengan selamat! +" . number_format($amt) .
                         " Kayu, Tanah Liat, Besi, dan Gandum telah masuk ke gudang desa (" . $ship['name'] . ")."
        ];
    }

    /**
     * 3. Contraband Bazaar: Tactical consumable items.
     */
    public static function buyContraband(int $uid, int $vref, string $itemKey): array
    {
        global $database;

        $items = [
            'antidote' => [
                'name' => 'Obat Penawar Wabah Selundupan',
                'silver' => 15,
                'desc' => 'Menyembuhkan wabah di desa seketika.'
            ],
            'grain_reserve' => [
                'name' => 'Lumbung Darurat Ransum',
                'silver' => 12,
                'desc' => 'Menyuplai +4.000 gandum darurat ke lumbung desa.'
            ],
            'merchant_pass' => [
                'name' => 'Surat Jalan Pedagang Palsu',
                'silver' => 8,
                'desc' => 'Penyelundup mempercepat pedagang desa.'
            ],
        ];

        if (!isset($items[$itemKey])) {
            return ['success' => false, 'message' => 'Item kontraband tidak valid.'];
        }

        $item = $items[$itemKey];
        $cost = (int)$item['silver'];

        $currentSilver = self::getSilver($uid);
        if ($currentSilver < $cost) {
            return ['success' => false, 'message' => 'Koin Silver tidak mencukupi. Butuh ' . $cost . ' Silver (Saldo: ' . $currentSilver . ').'];
        }

        // Item-specific validations & applications
        if ($itemKey === 'antidote') {
            require_once __DIR__ . '/Plague.php';
            if (!Plague::isVillageInfected($vref)) {
                return ['success' => false, 'message' => 'Desa ini sedang tidak terjangkit wabah penyakit. Obat penawar tidak diperlukan.'];
            }

            if (!self::spendSilver($uid, $cost, 'Black Market Antidote')) {
                return ['success' => false, 'message' => 'Gagal memproses transaksi Silver.'];
            }

            Plague::cureVillage($vref);
            self::logAction($uid, $vref, 'antidote', 'Cured plague via Smuggler Antidote', $cost);

            return [
                'success' => true,
                'message' => "🧪 Mujarab! Obat penawar selundupan berhasil menyembuhkan seluruh wabah penyakit di desa seketika. Karantina dicabut dan produksi normal kembali!"
            ];
        }

        if ($itemKey === 'grain_reserve') {
            if (!self::spendSilver($uid, $cost, 'Black Market Grain Reserve')) {
                return ['success' => false, 'message' => 'Gagal memproses transaksi Silver.'];
            }

            $bonusCrop = 4000;
            $database->modifyResource($vref, 0, 0, 0, $bonusCrop, 1, true);
            self::logAction($uid, $vref, 'grain_reserve', "Injected $bonusCrop emergency grain", $cost);

            return [
                'success' => true,
                'message' => "🌾 Pasokan lumbung gandum darurat tiba! +" . number_format($bonusCrop) . " Gandum telah ditambahkan ke lumbung desa Anda."
            ];
        }

        if ($itemKey === 'merchant_pass') {
            if (!self::spendSilver($uid, $cost, 'Black Market Merchant Pass')) {
                return ['success' => false, 'message' => 'Gagal memproses transaksi Silver.'];
            }

            // Reward bonus merchant supply bonus
            $database->modifyResource($vref, 1500, 1500, 1500, 1500, 1, true);
            self::logAction($uid, $vref, 'merchant_pass', 'Used forged merchant pass for bonus cargo', $cost);

            return [
                'success' => true,
                'message' => "📜 Surat jalan pedagang palsu berhasil digunakan! Penyelundup memberikan muatan logistik tambahan 1.500 masing-masing sumber daya ke desa."
            ];
        }

        return ['success' => false, 'message' => 'Item tidak dikenali.'];
    }

    /**
     * Process POST requests from Black Market UI.
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

        if ($post['ft'] === 'bm_launder') {
            $fromRes = (int)($post['from_res'] ?? 0);
            $toRes   = (int)($post['to_res'] ?? 0);
            $amount  = (int)($post['amount'] ?? 0);
            $result  = self::launderResources($uid, $vref, $fromRes, $toRes, $amount);
        } elseif ($post['ft'] === 'bm_shipment') {
            $tier   = (int)($post['tier'] ?? 0);
            $result = self::buyResourceShipment($uid, $vref, $tier);
        } elseif ($post['ft'] === 'bm_contraband') {
            $itemKey = trim($post['item_key'] ?? '');
            $result  = self::buyContraband($uid, $vref, $itemKey);
        }

        if ($result !== null) {
            $_SESSION['bm_flash'] = [
                'type'    => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ];
            header("Location: build.php?id=$id&t=5");
            exit;
        }
    }
}
