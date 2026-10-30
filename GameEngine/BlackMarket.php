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
    const TRIBE_CHANGE_GOLD_COST = 100; // 100 Gold to change tribe via Kitab Mutasi Leluhur

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

        $stmt = @mysqli_prepare($db, "SELECT MAX(silver) FROM `" . TB_PREFIX . "hero` WHERE uid = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $silver);
            $found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return ($found && !is_null($silver)) ? (int)$silver : 0;
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

        $stmt = @mysqli_prepare($db, "UPDATE `" . TB_PREFIX . "hero` SET silver = silver - ? WHERE uid = ? AND silver >= ? ORDER BY silver DESC LIMIT 1");
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

        $stmt = @mysqli_prepare($db, "UPDATE `" . TB_PREFIX . "hero` SET silver = silver + ? WHERE uid = ? ORDER BY silver DESC LIMIT 1");
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
            1 => ['name' => 'Paket Pasokan Kroco', 'silver' => 35, 'amount' => 500],
            2 => ['name' => 'Kargo Selundupan Pedagang', 'silver' => 80, 'amount' => 1500],
            3 => ['name' => 'Peti Harta Saudagar Gelap', 'silver' => 200, 'amount' => 4000],
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
                'silver' => 65,
                'desc' => 'Menyembuhkan wabah di desa seketika.'
            ],
            'grain_reserve' => [
                'name' => 'Lumbung Darurat Ransum',
                'silver' => 50,
                'desc' => 'Menyuplai +2.000 gandum darurat ke lumbung desa.'
            ],
            'ancient_relic' => [
                'name' => 'Gulungan Pusaka Kuno Selundupan',
                'silver' => 55,
                'desc' => 'Menambahkan +250 Culture Points (CP) untuk mempercepat ekspansi desa baru.'
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

            $bonusCrop = 2000;
            $database->modifyResource($vref, 0, 0, 0, $bonusCrop, 1, true);
            self::logAction($uid, $vref, 'grain_reserve', "Injected $bonusCrop emergency grain", $cost);

            return [
                'success' => true,
                'message' => "🌾 Pasokan lumbung gandum darurat tiba! +" . number_format($bonusCrop) . " Gandum telah ditambahkan ke lumbung desa Anda."
            ];
        }

        if ($itemKey === 'ancient_relic') {
            if (!self::spendSilver($uid, $cost, 'Black Market Ancient Relic')) {
                return ['success' => false, 'message' => 'Gagal memproses transaksi Silver.'];
            }

            // Reward Culture Points to both village and account
            $cpBonus = 250;
            $database->addCP($vref, $cpBonus);
            $database->setCelCp($uid, $cpBonus);
            self::logAction($uid, $vref, 'ancient_relic', "Acquired $cpBonus Culture Points via Smuggler Ancient Relic", $cost);

            return [
                'success' => true,
                'message' => "📜 Gulungan pusaka kuno selundupan berhasil dipelajari! +" . number_format($cpBonus) . " Culture Points (CP) telah ditambahkan ke peradaban desa dan akun Anda!"
            ];
        }

        return ['success' => false, 'message' => 'Item tidak dikenali.'];
    }

    /**
     * Hero Consumables Catalog for Black Market.
     * Price is intentionally set with a black-market markup/premium (higher than auction market price)
     * because items are delivered instantly into inventory without auction bidding or waiting.
     */
    public static function getHeroConsumablesCatalog(): array
    {
        return [
            202 => [
                'name'         => 'Ember Kehidupan (Bucket of Water)',
                'desc'         => 'Menghidupkan kembali Hero yang gugur seketika dengan 100% darah (HP) tanpa biaya sumber daya dan tanpa waktu tunggu.',
                'icon_class'   => 'item202',
                'market_price' => '~400 - 600 Silver',
                'silver_price' => 500,
                'min_qty'      => 1,
                'max_qty'      => 10,
                'badge'        => '🔥 Sangat Langka',
            ],
            204 => [
                'name'         => 'Kitab Kebijaksanaan (Book of Wisdom)',
                'desc'         => 'Mereset seluruh alokasi poin atribut Hero seketika agar dapat didistribusikan ulang (Kekuatan Tempur, Bonus Serang/Bertahan, Sumber Daya).',
                'icon_class'   => 'item204',
                'market_price' => '~300 - 500 Silver',
                'silver_price' => 800,
                'min_qty'      => 1,
                'max_qty'      => 10,
                'badge'        => '✨ Reset Poin Atribut',
            ],
            205 => [
                'name'         => 'Karya Seni Peradaban (Artwork)',
                'desc'         => 'Memberikan bonus Culture Points (CP) seketika setara hasil produksi CP harian seluruh desa akun Anda (maksimal 2.500/5.000 CP).',
                'icon_class'   => 'item205',
                'market_price' => '~400 - 700 Silver',
                'silver_price' => 1200,
                'min_qty'      => 1,
                'max_qty'      => 20,
                'badge'        => '🏛️ Ekspansi Cepat',
            ],
            203 => [
                'name'         => 'Tablet Hukum (Law Tablet)',
                'desc'         => 'Memulihkan loyalitas desa sebesar +1% per tablet (bisa dinaikkan hingga 125% untuk benteng proteksi ekstra dari perebutan desa oleh musuh).',
                'icon_class'   => 'item203',
                'market_price' => '~15 - 25 Silver / pcs',
                'silver_price' => 45,
                'min_qty'      => 1,
                'max_qty'      => 100,
                'badge'        => '🛡️ Anti Penaklukan',
            ],
            208 => [
                'name'         => 'Kandang Hewan Liar (Cage)',
                'desc'         => 'Menjebak & menangkap 1 hewan buas di oasis per kandang saat hero menyerang, langsung menjadi penjaga desa gratis tanpa makan gandum.',
                'icon_class'   => 'item208',
                'market_price' => '~8 - 12 Silver / pcs',
                'silver_price' => 20,
                'min_qty'      => 1,
                'max_qty'      => 200,
                'badge'        => '🐯 Pertahanan Bebas Gandum',
            ],
            201 => [
                'name'         => 'Gulungan Pengalaman (Scroll)',
                'desc'         => 'Memberikan +10 EXP pengalaman hero per lembar secara instan untuk mempercepat kenaikan level hero.',
                'icon_class'   => 'item201',
                'market_price' => '~10 - 20 Silver / pcs',
                'silver_price' => 35,
                'min_qty'      => 1,
                'max_qty'      => 200,
                'badge'        => '⚡ Cepat Naik Level',
            ],
            200 => [
                'name'         => 'Salep Penyembuh Luka (Ointment)',
                'desc'         => 'Memulihkan +1% darah (HP) hero per salep secara instan (maksimal hingga 99% HP saat hero masih hidup).',
                'icon_class'   => 'item200',
                'market_price' => '~3 - 6 Silver / pcs',
                'silver_price' => 12,
                'min_qty'      => 1,
                'max_qty'      => 300,
                'badge'        => '❤️ Pemulihan Darah',
            ],
            207 => [
                'name'         => 'Perban Tempur Besar (Bandage)',
                'desc'         => 'Menyelamatkan dan mengobati 33% pasukan pendamping hero yang gugur dalam pertempuran setelah penyerangan.',
                'icon_class'   => 'item207',
                'market_price' => '~15 - 25 Silver / pcs',
                'silver_price' => 40,
                'min_qty'      => 1,
                'max_qty'      => 100,
                'badge'        => '🩹 Selamatkan 33% Pasukan',
            ],
            206 => [
                'name'         => 'Perban Tempur Kecil (Small Bandage)',
                'desc'         => 'Menyelamatkan dan mengobati 25% pasukan pendamping hero yang gugur dalam pertempuran setelah penyerangan.',
                'icon_class'   => 'item206',
                'market_price' => '~10 - 15 Silver / pcs',
                'silver_price' => 25,
                'min_qty'      => 1,
                'max_qty'      => 100,
                'badge'        => '🩹 Selamatkan 25% Pasukan',
            ],
        ];
    }

    /**
     * Buy Hero Consumable items with Silver from the Black Market.
     */
    public static function buyHeroConsumable(int $uid, int $vref, int $itemid, int $quantity): array
    {
        $catalog = self::getHeroConsumablesCatalog();
        if (!isset($catalog[$itemid])) {
            return ['success' => false, 'message' => 'Pilihan perbekalan hero tidak valid.'];
        }

        $item = $catalog[$itemid];
        $quantity = max(1, (int)$quantity);
        $maxQty = (int)($item['max_qty'] ?? 100);
        if ($quantity > $maxQty) {
            return ['success' => false, 'message' => 'Jumlah pembelian melebihi batas maksimal transaksi (' . $maxQty . ' unit).'];
        }

        $unitPrice = (int)$item['silver_price'];
        $totalCost = $unitPrice * $quantity;

        $currentSilver = self::getSilver($uid);
        if ($currentSilver < $totalCost) {
            return [
                'success' => false,
                'message' => 'Koin Silver tidak mencukupi. Butuh ' . number_format($totalCost) . ' Silver (Saldo Anda: ' . number_format($currentSilver) . ' Silver).'
            ];
        }

        if (!self::spendSilver($uid, $totalCost, "Black Market Hero Consumable: " . $item['name'] . " x" . $quantity)) {
            return ['success' => false, 'message' => 'Gagal memproses transaksi pengurangan Silver.'];
        }

        require_once __DIR__ . '/HeroItems.php';
        $heroItems = new HeroItems();
        $addedId = $heroItems->addItem($uid, $itemid, $quantity);

        if (!$addedId) {
            // Refund silver if adding item fails
            self::addSilver($uid, $totalCost);
            return ['success' => false, 'message' => 'Terjadi kendala saat memasukkan barang ke tas inventori Hero. Koin Silver telah dikembalikan.'];
        }

        $details = "Bought " . $quantity . "x " . $item['name'] . " (item #" . $itemid . ") for " . $totalCost . " Silver";
        self::logAction($uid, $vref, 'hero_consumable', $details, $totalCost);

        return [
            'success' => true,
            'message' => "🤝 Transaksi penyelundup berhasil! +" . number_format($quantity) . "x " . $item['name'] . 
                         " berhasil dibeli seharga " . number_format($totalCost) . " Silver dan telah masuk ke tas inventori Hero Anda!"
        ];
    }

    /**
     * 5. Hero Item Appraisal: Calculate item appraisal and humorous syndicate deductions.
     * Total deduction: 30% (tutup mulut 15%, jatah preman 10%, uang rokok/kopi 5%).
     * Net payout: 70% of gross value, distributed equally among 4 resources (Wood, Clay, Iron, Crop).
     */
    public static function getItemAppraisal(int $itemid, int $quantity = 1): array
    {
        global $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            include_once __DIR__ . '/Data/hero_items.php';
        }

        $quantity = max(1, (int)$quantity);
        $item = $heroItemCatalog[$itemid] ?? null;

        $itemName = function_exists('heroItemName') ? heroItemName($itemid) : ($item['name'] ?? ('Item #' . $itemid));
        $tier = (int)($item['tier'] ?? 1);
        $slot = (int)($item['slot'] ?? 0);

        // Base gross value calculation in total resources (sum of 4 resources)
        $unitGross = 1000;
        if ($item !== null) {
            if ($slot === (defined('HSLOT_BAG') ? HSLOT_BAG : 7)) {
                // Consumables
                switch ($itemid) {
                    case 200: // Salep / Ointment
                    case 206: // Small Bandages
                        $unitGross = 200;
                        break;
                    case 208: // Cage / Kandang
                        $unitGross = 320;
                        break;
                    case 207: // Large Bandages
                        $unitGross = 400;
                        break;
                    case 201: // Scroll / Gulungan XP
                        $unitGross = 600;
                        break;
                    case 203: // Tablets of Law
                        $unitGross = 1200;
                        break;
                    case 204: // Book of Wisdom
                        $unitGross = 5000;
                        break;
                    case 205: // Artwork
                        $unitGross = 10000;
                        break;
                    case 202: // Bucket of Life / Ember Kehidupan
                        $unitGross = 12000;
                        break;
                    default:
                        $unitGross = 400;
                        break;
                }
            } else {
                // Equipment items based on tier
                if ($tier === 1) {
                    $unitGross = 4000; // 1,000 each res
                } elseif ($tier === 2) {
                    $unitGross = 10000; // 2,500 each res
                } elseif ($tier === 3) {
                    $unitGross = 24000; // 6,000 each res
                } else {
                    $unitGross = 4000;
                }
            }
        }

        $grossTotal = $unitGross * $quantity;
        // Divide gross equally among 4 resources
        $grossEach = (int)floor($grossTotal / 4);
        $grossTotal = $grossEach * 4;

        // Net payout: exactly 70% of gross, distributed equally across 4 resources
        $netEach = (int)floor(($grossTotal * 0.70) / 4);
        $netTotal = $netEach * 4;
        $totalFee = $grossTotal - $netTotal;

        // Humorous Syndicate Deductions Breakdown
        // 1. Biaya Tutup Mulut Penyelundup (~15%)
        $feeTutupMulut = (int)round($grossTotal * 0.15);
        // 2. Jatah Preman Keamanan Lapak (~10%)
        $feePreman = (int)round($grossTotal * 0.10);
        // 3. Uang Rokok & Kopi Calo Gelap (balance to match exact totalFee)
        $feeUangKopi = max(0, $totalFee - $feeTutupMulut - $feePreman);

        return [
            'itemid'          => $itemid,
            'name'            => $itemName,
            'tier'            => $tier,
            'slot'            => $slot,
            'quantity'        => $quantity,
            'unit_gross'      => $unitGross,
            'gross_total'     => $grossTotal,
            'gross_each'      => $grossEach,
            'fee_tutup_mulut' => $feeTutupMulut,
            'fee_preman'      => $feePreman,
            'fee_uang_kopi'   => $feeUangKopi,
            'fee_total'       => $totalFee,
            'fee_percent'     => 30,
            'net_each'        => $netEach,
            'net_total'       => $netTotal,
        ];
    }

    /**
     * Sell an unequipped hero item to the Black Market for 4 equal resources.
     */
    public static function sellHeroItem(int $uid, int $vref, int $rowId, int $quantity = 1): array
    {
        global $database;
        $db = self::db();
        $uid = (int)$uid;
        $vref = (int)$vref;
        $rowId = (int)$rowId;
        $quantity = max(1, (int)$quantity);

        if (!$db || $uid <= 0 || $vref <= 0 || $rowId <= 0) {
            return ['success' => false, 'message' => 'Parameter penjualan barang tidak valid.'];
        }

        // Verify ownership and equipped status
        $stmt = @mysqli_prepare($db, "SELECT id, uid, itemid, quantity, equipped FROM `" . TB_PREFIX . "hero_items` WHERE uid = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Gagal membaca data inventori hero.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $rowId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return ['success' => false, 'message' => 'Barang tidak ditemukan di dalam tas inventori Anda.'];
        }

        if ((int)$row['equipped'] === 1) {
            return ['success' => false, 'message' => 'Barang sedang dipakai oleh Hero! Lepaskan terlebih dahulu sebelum menggadaikannya ke Pasar Gelap.'];
        }

        $availQty = (int)$row['quantity'];
        if ($availQty < $quantity) {
            return ['success' => false, 'message' => 'Jumlah barang yang ingin dijual (' . $quantity . ') melebihi stok di tas (' . $availQty . ').'];
        }

        $itemid = (int)$row['itemid'];
        $appraisal = self::getItemAppraisal($itemid, $quantity);
        $netEach = (int)$appraisal['net_each'];
        $netTotal = (int)$appraisal['net_total'];

        // Remove item from inventory
        if (!class_exists('HeroItems')) {
            require_once __DIR__ . '/HeroItems.php';
        }
        $heroItems = new HeroItems();
        if (!$heroItems->removeItem($uid, $rowId, $quantity)) {
            return ['success' => false, 'message' => 'Gagal memotong barang dari inventori hero.'];
        }

        // Add 4 equal resources to the village
        $database->modifyResource($vref, $netEach, $netEach, $netEach, $netEach, 1, true);

        // Log transaction
        $itemName = $appraisal['name'];
        $details = "Gadai " . $quantity . "x " . $itemName . " -> Total " . number_format($netTotal) . " res (" . number_format($netEach) . " each). Potongan preman 30%: Tutup Mulut " . number_format($appraisal['fee_tutup_mulut']) . ", Preman " . number_format($appraisal['fee_preman']) . ", Rokok " . number_format($appraisal['fee_uang_kopi']);
        self::logAction($uid, $vref, 'pawn_item', $details, 0);

        return [
            'success' => true,
            'message' => "🤝 Transaksi gelap tuntas! " . $quantity . "x " . $itemName . " berhasil digadaikan ke tengkulak pasar gelap. " .
                         "Gudang desa menerima +" . number_format($netEach) . " Kayu, Tanah Liat, Besi, dan Gandum (Total: " . number_format($netTotal) . " sumber daya). " .
                         "Dipotong komisi tutup mulut penyelundup & jatah preman 30% (" . number_format($appraisal['fee_total']) . " res)."
        ];
    }

    /**
     * Appraise an item for Silver payout.
     */
    public static function getItemSilverAppraisal(int $itemid, int $quantity = 1): array
    {
        global $heroItemCatalog;
        if (!isset($heroItemCatalog)) {
            require_once __DIR__ . '/Data/hero_items.php';
        }

        $item = $heroItemCatalog[$itemid] ?? null;
        $itemName = function_exists('heroItemName') ? heroItemName($itemid) : ($item['name'] ?? ('Item #' . $itemid));
        $tier = (int)($item['tier'] ?? 1);
        $slot = (int)($item['slot'] ?? 0);
        $quantity = max(1, (int)$quantity);

        $unitSilver = 5;
        if ($item !== null) {
            if ($slot === (defined('HSLOT_BAG') ? HSLOT_BAG : 7)) {
                // Consumables
                switch ($itemid) {
                    case 200: // Salep / Ointment
                    case 206: // Small Bandages
                        $unitSilver = 2;
                        break;
                    case 207: // Large Bandages
                        $unitSilver = 4;
                        break;
                    case 208: // Cage / Kandang
                        $unitSilver = 5;
                        break;
                    case 201: // Scroll / Gulungan XP
                        $unitSilver = 10;
                        break;
                    case 203: // Tablets of Law
                        $unitSilver = 20;
                        break;
                    case 204: // Book of Wisdom
                        $unitSilver = 60;
                        break;
                    case 205: // Artwork
                        $unitSilver = 100;
                        break;
                    case 202: // Bucket of Life / Ember Kehidupan
                        $unitSilver = 120;
                        break;
                    default:
                        $unitSilver = 5;
                        break;
                }
            } else {
                // Equipment items based on tier
                if ($tier === 1) {
                    $unitSilver = 50;
                } elseif ($tier === 2) {
                    $unitSilver = 120;
                } elseif ($tier === 3) {
                    $unitSilver = 250;
                } else {
                    $unitSilver = 50;
                }
            }
        }

        $totalSilver = $unitSilver * $quantity;

        return [
            'itemid'       => $itemid,
            'name'         => $itemName,
            'tier'         => $tier,
            'slot'         => $slot,
            'quantity'     => $quantity,
            'unit_silver'  => $unitSilver,
            'total_silver' => $totalSilver,
        ];
    }

    /**
     * Sell an unequipped hero item to the Black Market for Silver coins.
     */
    public static function sellHeroItemForSilver(int $uid, int $vref, int $rowId, int $quantity = 1): array
    {
        $db = self::db();
        $uid = (int)$uid;
        $vref = (int)$vref;
        $rowId = (int)$rowId;
        $quantity = max(1, (int)$quantity);

        if (!$db || $uid <= 0 || $rowId <= 0) {
            return ['success' => false, 'message' => 'Parameter penjualan barang tidak valid.'];
        }

        // Verify ownership and equipped status
        $stmt = @mysqli_prepare($db, "SELECT id, uid, itemid, quantity, equipped FROM `" . TB_PREFIX . "hero_items` WHERE uid = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Gagal membaca data inventori hero.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $rowId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return ['success' => false, 'message' => 'Barang tidak ditemukan di dalam tas inventori Anda.'];
        }

        if ((int)$row['equipped'] === 1) {
            return ['success' => false, 'message' => 'Barang sedang dipakai oleh Hero! Lepaskan terlebih dahulu sebelum menggadaikannya ke Pasar Gelap.'];
        }

        $availQty = (int)$row['quantity'];
        if ($availQty < $quantity) {
            return ['success' => false, 'message' => 'Jumlah barang yang ingin dijual (' . $quantity . ') melebihi stok di tas (' . $availQty . ').'];
        }

        $itemid = (int)$row['itemid'];
        $appraisal = self::getItemSilverAppraisal($itemid, $quantity);
        $totalSilver = (int)$appraisal['total_silver'];

        // Remove item from inventory
        if (!class_exists('HeroItems')) {
            require_once __DIR__ . '/HeroItems.php';
        }
        $heroItems = new HeroItems();
        if (!$heroItems->removeItem($uid, $rowId, $quantity)) {
            return ['success' => false, 'message' => 'Gagal memotong barang dari inventori hero.'];
        }

        // Add Silver to hero
        self::addSilver($uid, $totalSilver);

        // Log transaction
        $itemName = $appraisal['name'];
        $details = "Gadai " . $quantity . "x " . $itemName . " -> Total " . number_format($totalSilver) . " Silver.";
        self::logAction($uid, $vref, 'pawn_item_silver', $details, 0);

        return [
            'success' => true,
            'message' => "🤝 Transaksi tuntas! " . $quantity . "x " . $itemName . " berhasil digadaikan. Anda menerima +" . number_format($totalSilver) . " Koin Silver!"
        ];
    }

    /**
     * Get tribe name.
     */
    public static function getTribeName(int $tribe): string
    {
        $names = [
            1 => defined('TRIBE1') ? TRIBE1 : 'Romans',
            2 => defined('TRIBE2') ? TRIBE2 : 'Teutons',
            3 => defined('TRIBE3') ? TRIBE3 : 'Gauls',
            6 => defined('TRIBE6') ? TRIBE6 : 'Huns',
            7 => defined('TRIBE7') ? TRIBE7 : 'Egyptians',
            8 => defined('TRIBE8') ? TRIBE8 : 'Spartans',
            9 => defined('TRIBE9') ? TRIBE9 : 'Vikings',
            10 => defined('TRIBE10') ? TRIBE10 : 'Nusantara',
        ];
        return $names[$tribe] ?? ('Suku #' . $tribe);
    }

    /**
     * Get available tribes based on server configuration.
     */
    public static function getAvailableTribes(int $excludeTribe = 0): array
    {
        $tribes = [
            1 => [
                'name' => self::getTribeName(1),
                'flag' => true,
                'desc' => 'Pembangunan fleksibel ganda (sumber daya & gedung serentak), infanteri & kavaleri tangguh seimbang.',
                'badge' => 'Pembangunan Ganda'
            ],
            2 => [
                'name' => self::getTribeName(2),
                'flag' => true,
                'desc' => 'Spesialis serbuan agresif awal, biaya pasukan murah, daya tampung jarahan gua musuh berkurang.',
                'badge' => 'Penjarah Agresif'
            ],
            3 => [
                'name' => self::getTribeName(3),
                'flag' => true,
                'desc' => 'Pertahanan solid, kapasitas gua 2x lipat, perangkap tersembunyi, kavaleri tercepat di Travian.',
                'badge' => 'Pertahanan & Kecepatan'
            ],
            6 => [
                'name' => self::getTribeName(6),
                'flag' => (defined('NEW_FUNCTION_TRIBE_HUNS') && NEW_FUNCTION_TRIBE_HUNS),
                'desc' => 'Kavaleri penyerbu berkekuatan dahsyat, Pusat Komando untuk ekspansi tanpa istana/kediaman.',
                'badge' => 'Kavaleri Dahsyat'
            ],
            7 => [
                'name' => self::getTribeName(7),
                'flag' => (defined('NEW_FUNCTION_TRIBE_EGIPTEANS') && NEW_FUNCTION_TRIBE_EGIPTEANS),
                'desc' => 'Kapasitas sumber daya melimpah, Tembok Batu kokoh, Waterworks pelipat ganda bonus oasis.',
                'badge' => 'Ekonomi & Ketahanan'
            ],
            8 => [
                'name' => self::getTribeName(8),
                'flag' => (defined('NEW_FUNCTION_TRIBE_SPARTANS') && NEW_FUNCTION_TRIBE_SPARTANS),
                'desc' => 'Konsumsi gandum efisien, unit infanteri tempur legendaris, Rumah Sakit Besar pemulih luka.',
                'badge' => 'Infanteri Tempur Elit'
            ],
            9 => [
                'name' => self::getTribeName(9),
                'flag' => (defined('NEW_FUNCTION_TRIBE_VIKINGS') && NEW_FUNCTION_TRIBE_VIKINGS),
                'desc' => 'Prajurit maritim penjarah tanpa gentar, daya serbu tinggi, Barikade pertahanan kayu.',
                'badge' => 'Serbuan Maritim'
            ],
            10 => [
                'name' => self::getTribeName(10),
                'flag' => (defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA),
                'desc' => 'Prajurit panah mematikan, Meriam Cetbang, Benteng Kedaton, ekonomi maritim & pertapaan mistis.',
                'badge' => 'Kemaritiman & Mistis'
            ],
        ];

        $available = [];
        foreach ($tribes as $tid => $data) {
            if ($data['flag'] && $tid !== $excludeTribe) {
                $available[$tid] = $data;
            }
        }
        return $available;
    }

    /**
     * Get wall building type for a tribe.
     */
    public static function getTribeWallType(int $tribe): int
    {
        $map = [
            1 => 31, // Roman City Wall
            2 => 32, // Teuton Earth Wall
            3 => 33, // Gaul Palisade
            6 => 42, // Hun Makeshift Wall
            7 => 43, // Egyptian Stone Wall
            8 => 47, // Spartan Wall
            9 => 50, // Viking Barricade
            10 => 32, // Nusantara Benteng Kedaton
        ];
        return $map[$tribe] ?? 31;
    }

    /**
     * 6. Kitab Mutasi Leluhur: Change tribe for 100 Gold with complete asset conversion.
     */
    public static function changeTribe(int $uid, int $vref, int $newTribe): array
    {
        global $database, $session;

        $db = self::db();
        if (!$db || $uid <= 0 || $vref <= 0 || $newTribe <= 0) {
            return ['success' => false, 'message' => 'Parameter perubahan suku tidak valid.'];
        }

        $avail = self::getAvailableTribes();
        if (!isset($avail[$newTribe])) {
            return ['success' => false, 'message' => 'Pilihan suku tujuan tidak valid atau sedang dinonaktifkan di server.'];
        }

        // Query user data
        $uRes = mysqli_query($db, "SELECT id, username, tribe, gold FROM `" . TB_PREFIX . "users` WHERE id = $uid LIMIT 1");
        $userRow = $uRes ? mysqli_fetch_assoc($uRes) : null;
        if (!$userRow) {
            return ['success' => false, 'message' => 'Data pengguna tidak ditemukan.'];
        }

        $oldTribe = (int)$userRow['tribe'];
        $userGold = (int)$userRow['gold'];
        $username = $userRow['username'];

        if ($oldTribe === $newTribe) {
            return ['success' => false, 'message' => 'Anda sudah menjadi bagian dari suku ' . self::getTribeName($newTribe) . '.'];
        }

        if ($userGold < self::TRIBE_CHANGE_GOLD_COST) {
            return ['success' => false, 'message' => 'Koin Gold Anda tidak mencukupi. Butuh ' . self::TRIBE_CHANGE_GOLD_COST . ' Gold (Saldo Anda saat ini: ' . number_format($userGold) . ' Gold).'];
        }

        $oldTribeName = self::getTribeName($oldTribe);
        $newTribeName = self::getTribeName($newTribe);

        $baseUnits = [
            1 => 1,
            2 => 11,
            3 => 21,
            6 => 51,
            7 => 61,
            8 => 71,
            9 => 81,
            10 => 91,
        ];
        $oldBase = $baseUnits[$oldTribe] ?? 1;
        $newBase = $baseUnits[$newTribe] ?? 1;

        // Get all village IDs belonging to this user
        $vids = [];
        $vRes = mysqli_query($db, "SELECT wref FROM `" . TB_PREFIX . "vdata` WHERE owner = $uid");
        if ($vRes) {
            while ($vRow = mysqli_fetch_assoc($vRes)) {
                $vids[] = (int)$vRow['wref'];
            }
        }
        $vList = !empty($vids) ? implode(',', $vids) : '';

        // Begin transaction
        mysqli_begin_transaction($db);

        try {
            // 1. Deduct 100 Gold atomically
            $cost = self::TRIBE_CHANGE_GOLD_COST;
            $deductQ = "UPDATE `" . TB_PREFIX . "users` SET gold = gold - $cost WHERE id = $uid AND gold >= $cost";
            mysqli_query($db, $deductQ);
            if (mysqli_affected_rows($db) <= 0) {
                mysqli_rollback($db);
                return ['success' => false, 'message' => 'Gagal memotong ' . $cost . ' Gold. Saldo Gold mungkin telah berubah.'];
            }

            // 2. Update user tribe in users table & vdata villages
            mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET tribe = $newTribe WHERE id = $uid");
            mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET tribe = $newTribe WHERE owner = $uid");

            // 3. Update Hero unit type
            $heroRes = mysqli_query($db, "SELECT heroid, unit FROM `" . TB_PREFIX . "hero` WHERE uid = $uid LIMIT 1");
            if ($heroRes && ($hRow = mysqli_fetch_assoc($heroRes))) {
                $curHeroUnit = (int)$hRow['unit'];
                $offset = ($curHeroUnit - $oldBase);
                if ($offset < 0 || $offset > 9) {
                    $offset = 5; // default to heavy cavalry (unit 6)
                }
                $newHeroUnit = $newBase + $offset;
                mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET unit = $newHeroUnit WHERE uid = $uid");
            }

            if (!empty($vList)) {
                $newWallType = self::getTribeWallType($newTribe);

                // 4. Update village walls in fdata and building queue
                mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f40t = $newWallType WHERE vref IN ($vList) AND (f40 > 0 OR f40t > 0)");
                mysqli_query($db, "UPDATE `" . TB_PREFIX . "bdata` SET type = $newWallType WHERE wid IN ($vList) AND field = 40");

                // 4b. Smart conversion of tribe-exclusive infrastructure buildings:
                // A. Command Center (44) <-> Palace (26) / Residence (25) for Huns (Tribe 6)
                if ($newTribe === 6 && $oldTribe !== 6) {
                    for ($f = 19; $f <= 38; $f++) {
                        mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f{$f}t = 44 WHERE vref IN ($vList) AND f{$f}t IN (25, 26)");
                    }
                    mysqli_query($db, "UPDATE `" . TB_PREFIX . "bdata` SET type = 44 WHERE wid IN ($vList) AND type IN (25, 26)");
                } elseif ($oldTribe === 6 && $newTribe !== 6) {
                    foreach ($vids as $vid) {
                        $cRes = mysqli_query($db, "SELECT capital FROM `" . TB_PREFIX . "vdata` WHERE wref = $vid LIMIT 1");
                        $isCap = ($cRes && ($cRow = mysqli_fetch_assoc($cRes))) ? (int)$cRow['capital'] : 0;
                        $targetGov = ($isCap === 1) ? 26 : 25;
                        for ($f = 19; $f <= 38; $f++) {
                            mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f{$f}t = $targetGov WHERE vref = $vid AND f{$f}t = 44");
                        }
                        mysqli_query($db, "UPDATE `" . TB_PREFIX . "bdata` SET type = $targetGov WHERE wid = $vid AND type = 44");
                    }
                }

                // B. Hospital (46) <-> Big Hospital (48) for Spartans (8) and Vikings (9)
                $isOldBigHosp = in_array($oldTribe, [8, 9]);
                $isNewBigHosp = in_array($newTribe, [8, 9]);
                if ($isNewBigHosp && !$isOldBigHosp) {
                    for ($f = 19; $f <= 38; $f++) {
                        mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f{$f}t = 48 WHERE vref IN ($vList) AND f{$f}t = 46");
                    }
                    mysqli_query($db, "UPDATE `" . TB_PREFIX . "bdata` SET type = 48 WHERE wid IN ($vList) AND type = 46");
                } elseif (!$isNewBigHosp && $isOldBigHosp) {
                    for ($f = 19; $f <= 38; $f++) {
                        mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f{$f}t = 46 WHERE vref IN ($vList) AND f{$f}t = 48");
                    }
                    mysqli_query($db, "UPDATE `" . TB_PREFIX . "bdata` SET type = 46 WHERE wid IN ($vList) AND type = 48");
                }

                // 5. Convert troops in units table for all villages
                $uRes = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "units` WHERE vref IN ($vList)");
                if ($uRes) {
                    while ($uRow = mysqli_fetch_assoc($uRes)) {
                        $vrefId = (int)$uRow['vref'];
                        $setParts = [];
                        for ($i = 0; $i <= 9; $i++) {
                            $oldCol = 'u' . ($oldBase + $i);
                            $newCol = 'u' . ($newBase + $i);
                            $qty = (int)($uRow[$oldCol] ?? 0);
                            if ($qty > 0) {
                                $newExisting = (int)($uRow[$newCol] ?? 0);
                                $totalNew = $newExisting + $qty;
                                $setParts[] = "`$newCol` = $totalNew";
                                $setParts[] = "`$oldCol` = 0";
                            }
                        }
                        if (!empty($setParts)) {
                            mysqli_query($db, "UPDATE `" . TB_PREFIX . "units` SET " . implode(', ', $setParts) . " WHERE vref = $vrefId");
                        }
                    }
                }

                // 6. Convert academy research in tdata for all villages
                $tRes = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "tdata` WHERE vref IN ($vList)");
                if ($tRes) {
                    while ($tRow = mysqli_fetch_assoc($tRes)) {
                        $vrefId = (int)$tRow['vref'];
                        $setParts = [];
                        for ($i = 1; $i <= 8; $i++) {
                            $oldCol = 't' . ($oldBase + $i);
                            $newCol = 't' . ($newBase + $i);
                            if (!empty($tRow[$oldCol])) {
                                $setParts[] = "`$newCol` = 1";
                                $setParts[] = "`$oldCol` = 0";
                            }
                        }
                        if (!empty($setParts)) {
                            mysqli_query($db, "UPDATE `" . TB_PREFIX . "tdata` SET " . implode(', ', $setParts) . " WHERE vref = $vrefId");
                        }
                    }
                }

                // 7. Convert active troop training queues in training table
                for ($i = 0; $i <= 9; $i++) {
                    $oldUnit = $oldBase + $i;
                    $newUnit = $newBase + $i;
                    mysqli_query($db, "UPDATE `" . TB_PREFIX . "training` SET unit = $newUnit WHERE vref IN ($vList) AND unit = $oldUnit");
                }

                // 8. Convert active academy research in research table
                for ($i = 1; $i <= 8; $i++) {
                    $oldTech = 't' . ($oldBase + $i);
                    $newTech = 't' . ($newBase + $i);
                    mysqli_query($db, "UPDATE `" . TB_PREFIX . "research` SET tech = '$newTech' WHERE vref IN ($vList) AND tech = '$oldTech'");
                }

                // 9. Convert stationed reinforcements abroad (sent FROM player's villages)
                $enfRes = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE `from` IN ($vList)");
                if ($enfRes) {
                    while ($enfRow = mysqli_fetch_assoc($enfRes)) {
                        $enfId = (int)$enfRow['id'];
                        $setParts = [];
                        for ($i = 0; $i <= 9; $i++) {
                            $oldCol = 'u' . ($oldBase + $i);
                            $newCol = 'u' . ($newBase + $i);
                            $qty = (int)($enfRow[$oldCol] ?? 0);
                            if ($qty > 0) {
                                $newExisting = (int)($enfRow[$newCol] ?? 0);
                                $totalNew = $newExisting + $qty;
                                $setParts[] = "`$newCol` = $totalNew";
                                $setParts[] = "`$oldCol` = 0";
                            }
                        }
                        if (!empty($setParts)) {
                            mysqli_query($db, "UPDATE `" . TB_PREFIX . "enforcement` SET " . implode(', ', $setParts) . " WHERE id = $enfId");
                        }
                    }
                }
            }

            // Commit all changes
            mysqli_commit($db);
        } catch (\Throwable $e) {
            mysqli_rollback($db);
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem saat mutasi suku: ' . $e->getMessage()];
        }

        // Cache invalidation
        if (isset($_SESSION['username'])) {
            unset($_SESSION['cache_user_' . $_SESSION['username']]);
        }
        if (class_exists('DatabaseUserQueries')) {
            DatabaseUserQueries::$fieldsCache = [];
        }
        if (class_exists('DatabaseHeroQueries')) {
            DatabaseHeroQueries::$heroCache = [];
            DatabaseHeroQueries::$heroFieldCache = [];
            DatabaseHeroQueries::$heroVillageCache = [];
        }
        if (class_exists('DatabaseTroopQueries')) {
            DatabaseTroopQueries::$isResearchedCache = [];
        }

        // Invalidate active session variables
        if (isset($session)) {
            $session->tribe = $newTribe;
            if (isset($session->userarray)) {
                $session->userarray['tribe'] = $newTribe;
                $session->userarray['gold'] = max(0, (int)($session->userarray['gold'] ?? 0) - self::TRIBE_CHANGE_GOLD_COST);
            }
            if (isset($session->userinfo)) {
                $session->userinfo['tribe'] = $newTribe;
                $session->userinfo['gold'] = max(0, (int)($session->userinfo['gold'] ?? 0) - self::TRIBE_CHANGE_GOLD_COST);
            }
            if (isset($session->gold)) {
                $session->gold = max(0, (int)$session->gold - self::TRIBE_CHANGE_GOLD_COST);
                $_SESSION['gold'] = $session->gold;
            }
        }

        // Audit logging
        $time = time();
        $detailsEsc = mysqli_real_escape_string($db, "Changed tribe from $oldTribeName to $newTribeName");
        $logEsc = mysqli_real_escape_string($db, "Kitab Mutasi Leluhur");
        $actionEsc = mysqli_real_escape_string($db, "Black Market: Change Tribe");
        mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "gold_fin_log` (`uid`, `wid`, `action`, `gold`, `time`, `log`, `details`) VALUES ($uid, $vref, '$actionEsc', -" . self::TRIBE_CHANGE_GOLD_COST . ", $time, '$logEsc', '$detailsEsc')");

        self::logAction($uid, $vref, 'change_tribe', "Mutasi suku dari $oldTribeName ke $newTribeName (Biaya " . self::TRIBE_CHANGE_GOLD_COST . " Gold)", 0);

        return [
            'success' => true,
            'message' => "📜 Ritual Kitab Mutasi Leluhur berhasil dilaksanakan! Akun Anda kini resmi beralih ke suku $newTribeName. " .
                         "Seluruh pasukan di semua desa, benteng pertahanan, riset teknologi di Akademi, antrean pelatihan, dan Hero Anda telah dimigrasikan secara utuh ke peradaban baru."
        ];
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
        } elseif ($post['ft'] === 'bm_buy_consumable') {
            $itemid = (int)($post['itemid'] ?? 0);
            $qty    = max(1, (int)($post['qty'] ?? 1));
            $result = self::buyHeroConsumable($uid, $vref, $itemid, $qty);
        } elseif ($post['ft'] === 'bm_pawn_item') {
            $rowId = (int)($post['row_id'] ?? 0);
            $qty   = max(1, (int)($post['qty'] ?? 1));
            $result = self::sellHeroItem($uid, $vref, $rowId, $qty);
        } elseif ($post['ft'] === 'bm_pawn_item_silver') {
            $rowId = (int)($post['row_id'] ?? 0);
            $qty   = max(1, (int)($post['qty'] ?? 1));
            $result = self::sellHeroItemForSilver($uid, $vref, $rowId, $qty);
        } elseif ($post['ft'] === 'bm_change_tribe') {
            $newTribe = (int)($post['new_tribe'] ?? 0);
            $result   = self::changeTribe($uid, $vref, $newTribe);
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
