<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : 17_5.tpl                                                  ##
##  Type           : BUILDING TEMPLATE - BLACK MARKET                          ##
##  Purpose        : Smuggler trade, resource laundering & contraband items    ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

global $database, $session, $village, $id;

require_once __DIR__ . '/../../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../../GameEngine/Plague.php';
require_once __DIR__ . '/../../GameEngine/HeroItems.php';

$level = (int)$village->resarray['f'.$id];
$silver = BlackMarket::getSilver((int)$session->uid);
$userGold = (int)($session->gold ?? ($session->userarray['gold'] ?? 0));
$userTribe = (int)($session->tribe ?? ($session->userarray['tribe'] ?? 1));
$availableTribes = BlackMarket::getAvailableTribes($userTribe);
$currentTribeName = BlackMarket::getTribeName($userTribe);

$awood = (int)$database->getWoodAvailable($village->wid);
$aclay = (int)$database->getClayAvailable($village->wid);
$airon = (int)$database->getIronAvailable($village->wid);
$acrop = (int)$database->getCropAvailable($village->wid);

$isPlagued = Plague::isVillageInfected($village->wid);
$plagueInfo = $isPlagued ? Plague::getVillagePlague($village->wid) : null;

$heroItemsObj = new HeroItems();
$userInventory = $heroItemsObj->getInventory((int)$session->uid);
$pawnableItems = [];
foreach ($userInventory as $it) {
    if (empty($it['equipped']) && empty($it['orphan'])) {
        $pawnableItems[] = $it;
    }
}

$flash = null;
if (!empty($_SESSION['bm_flash'])) {
    $flash = $_SESSION['bm_flash'];
    unset($_SESSION['bm_flash']);
}
?>
<link rel="stylesheet" href="css/hero_items.css" type="text/css">

<div id="build" class="gid17">
    <a href="#" onClick="return Popup(17,4);" class="build_logo">
        <img class="building g17" src="img/x.gif" alt="<?php echo MARKETPLACE;?>" title="<?php echo MARKETPLACE;?>" />
    </a>
    <h1><?php echo MARKETPLACE;?> <span class="level"><?php echo LEVEL;?> <?php echo $level;?></span></h1>
    <p class="build_desc"><?php echo MARKETPLACE_DESC;?></p>

    <?php include("17_menu.tpl");?>

    <!-- BANNER PASAR GELAP -->
    <div style="background: linear-gradient(135deg, #1f1d1a 0%, #3a2e24 100%); border: 2px solid #6b4d32; border-radius: 6px; padding: 12px 16px; margin: 15px 0 12px 0; color: #f4eee2; box-shadow: 0 3px 6px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0; color: #ffcc66; font-size: 16px; text-shadow: 1px 1px 2px #000;">
                    🏴‍☠️ Pasar Gelap Penyelundup <span style="font-size: 11px; color: #bbb; font-weight: normal;">(Smuggler's Den)</span>
                </h2>
                <div style="font-size: 11px; color: #d0c5b4; margin-top: 3px;">
                    Pusat penukaran komoditas terlarang, pencucian sumber daya tanpa Gold, pengadaan kontraband, dan mutasi suku.
                </div>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap;">
                <div style="background: #11100e; border: 1px solid #d4af37; border-radius: 4px; padding: 6px 12px; text-align: right;">
                    <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Saldo Koin Gold</span><br />
                    <span style="font-size: 16px; font-weight: bold; color: #ffcc00;">💰 <?php echo number_format($userGold); ?></span>
                    <span style="font-size: 11px; color: #d4af37;">Gold</span>
                </div>
                <div style="background: #11100e; border: 1px solid #a0a0a0; border-radius: 4px; padding: 6px 12px; text-align: right;">
                    <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Saldo Koin Silver</span><br />
                    <span style="font-size: 16px; font-weight: bold; color: #f5f5f5;">🪙 <?php echo number_format($silver); ?></span>
                    <span style="font-size: 11px; color: #a0a0a0;">Silver</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FLASH NOTIFICATION -->
    <?php if ($flash): ?>
        <div style="margin: 10px 0; padding: 10px 14px; border-radius: 4px; font-size: 12px; <?php echo $flash['type'] === 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <strong><?php echo $flash['type'] === 'success' ? '✅ Berhasil:' : '⚠️ Perhatian:'; ?></strong>
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- PILAR 1: PENCUCIAN SUMBER DAYA (BEBAS GOLD) -->
    <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="margin: 0 0 6px 0; color: #693206; font-size: 14px; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px;">
            ⚖️ 1. Pencucian Sumber Daya <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Resource Laundering — Tanpa Gold)</span>
        </h3>
        <p style="font-size: 11px; color: #555; margin: 4px 0 10px 0;">
            Tukar kelebihan satu jenis sumber daya ke jenis lain dengan bebas tanpa batasan NPC. Penyelundup memotong komisi gelap <b>15%</b> (Anda menerima 85% hasil bersih).
        </p>

        <form method="POST" action="build.php" style="margin: 0;">
            <input type="hidden" name="ft" value="bm_launder">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

            <table cellpadding="4" cellspacing="2" style="width: 100%; font-size: 12px; background: #fff; border: 1px solid #e0d5c1; border-radius: 4px;">
                <tr style="background: #f3ece0;">
                    <th style="width: 25%; text-align: left;">Sumber Daya Asal</th>
                    <th style="width: 25%; text-align: left;">Sumber Daya Tujuan</th>
                    <th style="width: 25%; text-align: left;">Jumlah Yang Dicuci</th>
                    <th style="width: 25%; text-align: left;">Estimasi Diterima</th>
                </tr>
                <tr>
                    <td>
                        <select name="from_res" id="bm_from" style="width: 100%; padding: 4px;" onchange="updateLaunderPreview()">
                            <option value="1">🪵 Kayu (<?php echo number_format($awood); ?>)</option>
                            <option value="2">🧱 Tanah Liat (<?php echo number_format($aclay); ?>)</option>
                            <option value="3">⛏️ Besi (<?php echo number_format($airon); ?>)</option>
                            <option value="4">🌾 Gandum (<?php echo number_format($acrop); ?>)</option>
                        </select>
                    </td>
                    <td>
                        <select name="to_res" id="bm_to" style="width: 100%; padding: 4px;" onchange="updateLaunderPreview()">
                            <option value="4">🌾 Gandum</option>
                            <option value="1">🪵 Kayu</option>
                            <option value="2">🧱 Tanah Liat</option>
                            <option value="3">⛏️ Besi</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="amount" id="bm_amount" value="1000" min="100" step="50" style="width: 90%; padding: 4px;" oninput="updateLaunderPreview()">
                    </td>
                    <td>
                        <span id="bm_preview" style="font-weight: bold; color: #2e6b38; font-size: 13px;">850</span>
                        <span style="font-size: 10px; color: #888;">(-15% fee)</span>
                    </td>
                </tr>
            </table>

            <div style="text-align: right; margin-top: 8px;">
                <button type="submit" class="dynamic_img" style="background: #693206; color: #fff; border: 1px solid #4a2103; padding: 6px 14px; font-weight: bold; border-radius: 3px; cursor: pointer;">
                    🤝 Cuci Sumber Daya Sekarang
                </button>
            </div>
        </form>
    </div>

    <!-- PILAR 2: PAKET PENGIRIMAN KARGO SELUNDUPAN (DENGAN SILVER) -->
    <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="margin: 0 0 6px 0; color: #693206; font-size: 14px; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px;">
            📦 2. Kargo Sumber Daya Selundupan <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Beli dengan Koin Silver Hasil Jarahan)</span>
        </h3>
        <p style="font-size: 11px; color: #555; margin: 4px 0 10px 0;">
            Manfaatkan koin Silver dari Sarang Bandit untuk menyuplai keempat sumber daya (Kayu, Tanah Liat, Besi, Gandum) secara serentak ke desa Anda.
        </p>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- TIER 1 -->
            <div style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #dcd1be; border-radius: 5px; padding: 10px; text-align: center;">
                <div style="font-weight: bold; color: #493322; font-size: 12px;">Paket Kroco</div>
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+500 Tiap Sumber Daya</div>
                <div style="background: #f4eee2; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>2.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="1">
                    <button type="submit" <?php if($silver < 35) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 35 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 35 ? 'pointer' : 'not-allowed'; ?>;">
                        35 Silver
                    </button>
                </form>
            </div>

            <!-- TIER 2 -->
            <div style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #d4af37; border-radius: 5px; padding: 10px; text-align: center; box-shadow: 0 0 4px rgba(212,175,55,0.2);">
                <div style="font-weight: bold; color: #8a6d3b; font-size: 12px;">Kargo Pedagang ⭐</div>
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+1.500 Tiap Sumber Daya</div>
                <div style="background: #fdfaf2; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>6.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="2">
                    <button type="submit" <?php if($silver < 80) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 80 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 80 ? 'pointer' : 'not-allowed'; ?>;">
                        80 Silver
                    </button>
                </form>
            </div>

            <!-- TIER 3 -->
            <div style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #993300; border-radius: 5px; padding: 10px; text-align: center;">
                <div style="font-weight: bold; color: #993300; font-size: 12px;">Peti Saudagar Gelap 👑</div>
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+4.000 Tiap Sumber Daya</div>
                <div style="background: #fdf4ee; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>16.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="3">
                    <button type="submit" <?php if($silver < 200) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 200 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 200 ? 'pointer' : 'not-allowed'; ?>;">
                        200 Silver
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- PILAR 3: BAZAAR KONTRABAND TAKTIS -->
    <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="margin: 0 0 6px 0; color: #693206; font-size: 14px; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px;">
            🧪 3. Bazaar Kontraband Taktis <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Perlengkapan Darurat & Obat Terlarang)</span>
        </h3>
        <p style="font-size: 11px; color: #555; margin: 4px 0 10px 0;">
            Barang selundupan khusus berkhasiat instan untuk menghadapi krisis gandum, wabah penyakit, atau logistik.
        </p>

        <table cellpadding="6" cellspacing="2" style="width: 100%; font-size: 12px; background: #fff; border: 1px solid #e0d5c1; border-radius: 4px;">
            <tr style="background: #f3ece0;">
                <th style="text-align: left; width: 45%;">Nama Item & Khasiat</th>
                <th style="text-align: center; width: 25%;">Status Desa</th>
                <th style="text-align: center; width: 15%;">Harga</th>
                <th style="text-align: right; width: 15%;">Aksi</th>
            </tr>

            <!-- ITEM 1: ANTIDOTE WABAH -->
            <tr>
                <td>
                    <b>🧪 Obat Penawar Wabah Selundupan</b><br />
                    <span style="font-size: 10px; color: #666;">Menyembuhkan wabah penyakit di desa seketika tanpa menunggu karantina 24 jam.</span>
                </td>
                <td style="text-align: center;">
                    <?php if ($isPlagued): ?>
                        <span style="display: inline-block; padding: 2px 6px; background: #f8d7da; color: #721c24; border-radius: 3px; font-weight: bold; font-size: 11px;">
                            ☣️ Terjangkit Wabah!
                        </span>
                    <?php else: ?>
                        <span style="display: inline-block; padding: 2px 6px; background: #d4edda; color: #155724; border-radius: 3px; font-size: 11px;">
                            ✅ Desa Sehat
                        </span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; font-weight: bold; color: #996600;">
                    65 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="antidote">
                        <button type="submit" <?php if(!$isPlagued || $silver < 65) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($isPlagued && $silver >= 65) ? '#b02a37' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($isPlagued && $silver >= 65) ? 'pointer' : 'not-allowed'; ?>;">
                            Sembuhkan
                        </button>
                    </form>
                </td>
            </tr>

            <!-- ITEM 2: GRAIN RESERVE -->
            <tr style="background: #faf7f2;">
                <td>
                    <b>🌾 Lumbung Darurat Ransum</b><br />
                    <span style="font-size: 10px; color: #666;">Menyuplai +2.000 gandum instan untuk menyelamatkan desa dari krisis kelaparan pasukan.</span>
                </td>
                <td style="text-align: center; font-size: 11px; color: #444;">
                    Lumbung: <b><?php echo number_format($acrop); ?></b> Gandum
                </td>
                <td style="text-align: center; font-weight: bold; color: #996600;">
                    50 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="grain_reserve">
                        <button type="submit" <?php if($silver < 50) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($silver >= 50) ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($silver >= 50) ? 'pointer' : 'not-allowed'; ?>;">
                            Beli Ransum
                        </button>
                    </form>
                </td>
            </tr>

            <!-- ITEM 3: ANCIENT RELIC (CP BONUS) -->
            <tr>
                <td>
                    <b>📜 Gulungan Pusaka Kuno Selundupan</b><br />
                    <span style="font-size: 10px; color: #666;">Naskah kuno peninggalan peradaban masa lalu yang memberi <b>+250 Culture Points (CP)</b> seketika untuk mempercepat pendirian desa baru.</span>
                </td>
                <td style="text-align: center; font-size: 11px; color: #444;">
                    +250 CP Akun
                </td>
                <td style="text-align: center; font-weight: bold; color: #996600;">
                    55 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="ancient_relic">
                        <button type="submit" <?php if($silver < 55) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($silver >= 55) ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($silver >= 55) ? 'pointer' : 'not-allowed'; ?>;">
                            Beli Pusaka
                        </button>
                    </form>
                </td>
            </tr>
        </table>
    </div>

    <!-- PILAR 4: BURSA PERBEKALAN HERO SELUNDUPAN (HERO CONSUMABLES BLACK MARKET) -->
    <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 1px dashed #cbb89d; padding-bottom: 6px; margin-bottom: 8px;">
            <h3 style="margin: 0; color: #693206; font-size: 14px;">
                🪣 4. Bursa Perbekalan Hero Selundupan <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Hero Consumables — Bayar Pakai Silver)</span>
            </h3>
            <span style="font-size: 10.5px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 2px 8px; border-radius: 3px; font-weight: bold;">
                ⚡ Instan Langsung Masuk Tas Hero
            </span>
        </div>
        <p style="font-size: 11px; color: #555; margin: 4px 0 12px 0; line-height: 1.5;">
            Butuh perbekalan darurat tanpa harus menunggu lelang berjam-jam atau berebut tawaran dengan pemain lain? Penyelundup pasar gelap menyediakan stok perbekalan hero siap pakai yang langsung diselundupkan ke tas hero Anda saat ini juga.
            <br />
            <span style="color: #8c4a17; font-size: 10px;">
                <i>⚠️ Catatan Tengkulak: Karena perbekalan ini merupakan barang selundupan instan "tanpa antre", harganya memiliki margin pasar gelap yang lebih mahal dari harga pasaran lelang biasa.</i>
            </span>
        </p>

        <?php
        $heroConsumables = BlackMarket::getHeroConsumablesCatalog();
        ?>
        <div style="border: 1px solid #e0d5c1; border-radius: 4px; background: #fff; overflow-x: auto;">
            <table cellpadding="6" cellspacing="0" style="width: 100%; font-size: 11px; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f3ece0; border-bottom: 1px solid #d5c8b2;">
                        <th style="text-align: center; width: 54px; padding: 6px 4px;">Icon</th>
                        <th style="text-align: left; padding: 6px 8px;">Nama Perbekalan & Manfaat</th>
                        <th style="text-align: center; width: 135px; padding: 6px 4px;">Harga Pasar Gelap</th>
                        <th style="text-align: center; width: 95px; padding: 6px 4px;">Jumlah</th>
                        <th style="text-align: right; width: 115px; padding: 6px 8px;">Aksi Beli</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowIdx = 0;
                    foreach ($heroConsumables as $cItemId => $cItem): 
                        $rowIdx++;
                        $rowBg = ($rowIdx % 2 === 0) ? '#faf7f2' : '#ffffff';
                        $uPrice = (int)$cItem['silver_price'];
                        $minQ = (int)$cItem['min_qty'];
                        $maxQ = (int)$cItem['max_qty'];
                        $canAffordMin = ($silver >= ($uPrice * $minQ));
                    ?>
                    <tr style="background: <?php echo $rowBg; ?>; border-bottom: 1px solid #eee;">
                        <td style="text-align: center; vertical-align: middle; padding: 6px 4px;">
                            <div style="width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; background: #eceae5; border: 1px solid #d4cbb8; border-radius: 4px; overflow: hidden;">
                                <span class="heroT4Item <?php echo $cItem['icon_class']; ?>" style="transform: scale(0.85); transform-origin: center center;" title="<?php echo htmlspecialchars($cItem['name']); ?>"></span>
                            </div>
                        </td>
                        <td style="vertical-align: middle; padding: 6px 8px;">
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <b style="color: #4a2808; font-size: 12px;"><?php echo htmlspecialchars($cItem['name']); ?></b>
                                <span style="font-size: 10px; background: #eaf3db; color: #3b6311; border: 1px solid #c7dfa7; border-radius: 3px; padding: 1px 5px; font-weight: bold;">
                                    <?php echo $cItem['badge']; ?>
                                </span>
                            </div>
                            <div style="color: #666; font-size: 10px; margin-top: 3px; line-height: 1.35;">
                                <?php echo htmlspecialchars($cItem['desc']); ?>
                            </div>
                            <div style="color: #999; font-size: 9.5px; margin-top: 2px;">
                                <i>Pasaran Lelang Biasa: <?php echo htmlspecialchars($cItem['market_price']); ?></i>
                            </div>
                        </td>
                        <td style="text-align: center; vertical-align: middle; padding: 6px 4px;">
                            <span style="font-weight: bold; color: #b8860b; font-size: 12px;">
                                💰 <?php echo number_format($uPrice); ?>
                            </span>
                            <span style="font-size: 10px; color: #888;">Silver / pcs</span>
                        </td>
                        <td style="text-align: center; vertical-align: middle; padding: 6px 4px;">
                            <form method="POST" action="build.php" id="form_c_<?php echo $cItemId; ?>" style="margin: 0;" onsubmit="return confirm('Beli ' + document.getElementById('qty_<?php echo $cItemId; ?>').value + 'x <?php echo addslashes($cItem['name']); ?> seharga ' + (parseInt(document.getElementById('qty_<?php echo $cItemId; ?>').value) * <?php echo $uPrice; ?>).toLocaleString() + ' Silver?');">
                                <input type="hidden" name="ft" value="bm_buy_consumable">
                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                <input type="hidden" name="itemid" value="<?php echo $cItemId; ?>">
                                
                                <div style="display: flex; align-items: center; justify-content: center; gap: 2px;">
                                    <input type="number" 
                                           id="qty_<?php echo $cItemId; ?>" 
                                           name="qty" 
                                           value="<?php echo $minQ; ?>" 
                                           min="<?php echo $minQ; ?>" 
                                           max="<?php echo $maxQ; ?>" 
                                           style="width: 52px; padding: 3px; font-size: 11px; text-align: center; border: 1px solid #ccc; border-radius: 3px;"
                                           oninput="updateConsumableTotal(<?php echo $cItemId; ?>, <?php echo $uPrice; ?>)">
                                </div>
                                <div style="font-size: 9px; color: #888; margin-top: 2px;">
                                    Total: <b id="total_lbl_<?php echo $cItemId; ?>" style="color: #693206;"><?php echo number_format($uPrice * $minQ); ?></b> Silver
                                </div>
                        </td>
                        <td style="text-align: right; vertical-align: middle; padding: 6px 8px;">
                                <button type="submit" 
                                        id="btn_c_<?php echo $cItemId; ?>"
                                        <?php if(!$canAffordMin) echo 'disabled'; ?>
                                        style="background: <?php echo $canAffordMin ? '#2e6b38' : '#bbb'; ?>; color: #fff; border: 1px solid <?php echo $canAffordMin ? '#1e4b25' : '#aaa'; ?>; padding: 5px 10px; font-size: 11px; font-weight: bold; border-radius: 3px; cursor: <?php echo $canAffordMin ? 'pointer' : 'not-allowed'; ?>; white-space: nowrap; box-shadow: <?php echo $canAffordMin ? '0 1px 2px rgba(0,0,0,0.15)' : 'none'; ?>;">
                                    <?php echo $canAffordMin ? '🛒 Beli Instan' : '❌ Silver Kurang'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PILAR 5: PEGADAIAN GELAP & JUAL BARANG BUKTI (HERO ITEM PAWN SHOP) -->
    <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <h3 style="margin: 0 0 6px 0; color: #693206; font-size: 14px; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px;">
            🧺 5. Pegadaian Gelap & Jual Beli Barang Bukti <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Hero Item Pawn Shop — Tukar Barang Jadi Sumber Daya Instan)</span>
        </h3>
        <p style="font-size: 11px; color: #555; margin: 4px 0 10px 0;">
            Punya perlengkapan senjata, zirah, atau perbekalan petualangan berlebih yang menganggur di tas? Gadaikan langsung ke tengkulak pasar gelap untuk mendapatkan pasokan <b>4 jenis sumber daya merata</b> secara instan ke gudang desa!
            <br />
            <span style="color: #8c4a17; font-size: 10px;">
                <i>⚠️ Catatan Tengkulak: Karena ini barang panas tanpa surat resmi kekaisaran, tengkulak memotong komisi gelap <b>30%</b> (15% Uang Tutup Mulut Penyelundup + 10% Jatah Preman Keamanan Lapak + 5% Uang Rokok & Kopi Calo). Hasil bersih 70% langsung diselundupkan ke gudang desa.</i>
            </span>
        </p>

        <?php if (empty($pawnableItems)): ?>
            <div style="background: #fff; border: 1px dashed #cbb89d; border-radius: 4px; padding: 16px; text-align: center; color: #777; font-size: 12px;">
                🎒 <b>Tas inventori Anda tidak memiliki barang yang bisa digadaikan.</b><br />
                <span style="font-size: 11px; color: #999;">Semua barang mungkin sedang dipakai oleh Hero atau tas Anda sedang kosong. Kirim hero bertualang untuk mengumpulkan jarahan!</span>
            </div>
        <?php else: ?>
            <div style="max-height: 380px; overflow-y: auto; border: 1px solid #e0d5c1; border-radius: 4px; background: #fff;">
                <table cellpadding="6" cellspacing="0" style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f3ece0; border-bottom: 1px solid #d5c8b2;">
                            <th style="text-align: left; padding: 6px 8px;">Barang Bukti</th>
                            <th style="text-align: center; padding: 6px 4px; width: 65px;">Slot / Tier</th>
                            <th style="text-align: center; padding: 6px 4px; width: 45px;">Stok</th>
                            <th style="text-align: right; padding: 6px 6px; width: 85px;">Taksiran Kotor</th>
                            <th style="text-align: right; padding: 6px 6px; width: 95px;">Potongan (30%)</th>
                            <th style="text-align: right; padding: 6px 6px; width: 110px;">Hasil Bersih</th>
                            <th style="text-align: center; padding: 6px 8px; width: 90px;">Aksi Gadai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $slotNames = [
                            1 => 'Helm',
                            2 => 'Zirah',
                            3 => 'Senjata',
                            4 => 'Tangan Kiri',
                            5 => 'Sepatu',
                            6 => 'Kuda',
                            7 => 'Perbekalan'
                        ];
                        foreach ($pawnableItems as $pItem):
                            $pItemid = (int)$pItem['itemid'];
                            $pQty = (int)$pItem['quantity'];
                            $pSlot = (int)($pItem['def']['slot'] ?? 7);
                            $pTier = (int)($pItem['def']['tier'] ?? 1);
                            $pIsBag = ($pSlot === 7);
                            $appraisal = BlackMarket::getItemAppraisal($pItemid, 1);
                            $bonusTxt = function_exists('heroItemBonusText') ? heroItemBonusText($pItemid) : '';
                        ?>
                        <tr style="border-bottom: 1px solid #f0e9dc;">
                            <td style="padding: 6px 8px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="heroT4Item item<?php echo $pItemid; ?>" style="transform: scale(0.65); margin: -8px -6px; transform-origin: center; display: inline-block;"></span>
                                    <div>
                                        <b style="color: #333; font-size: 11px;"><?php echo htmlspecialchars($pItem['name']); ?></b>
                                        <?php if (!empty($bonusTxt)): ?>
                                            <div style="font-size: 9px; color: #888;"><?php echo htmlspecialchars($bonusTxt); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <span style="font-size: 10px; color: #666;"><?php echo $slotNames[$pSlot] ?? 'Item'; ?></span><br />
                                <?php if (!$pIsBag): ?>
                                    <span style="font-size: 9px; background: #eceae5; padding: 1px 4px; border-radius: 3px; font-weight: bold; color: #555;">T<?php echo $pTier; ?></span>
                                <?php else: ?>
                                    <span style="font-size: 9px; background: #e4f0d0; padding: 1px 4px; border-radius: 3px; font-weight: bold; color: #3f5f22;">Bag</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; vertical-align: middle; font-weight: bold; color: #444;">
                                <?php echo $pQty; ?>x
                            </td>
                            <td style="text-align: right; vertical-align: middle; color: #888; font-size: 10px;">
                                <span style="text-decoration: line-through;"><?php echo number_format($appraisal['gross_total']); ?></span> res
                                <div style="font-size: 9px; color: #aaa;">(<?php echo number_format($appraisal['gross_each']); ?>/res)</div>
                            </td>
                            <td style="text-align: right; vertical-align: middle; color: #b22222; font-size: 10px;" title="Tutup Mulut: -<?php echo number_format($appraisal['fee_tutup_mulut']); ?> | Preman: -<?php echo number_format($appraisal['fee_preman']); ?> | Rokok Calo: -<?php echo number_format($appraisal['fee_uang_kopi']); ?>">
                                -<?php echo number_format($appraisal['fee_total']); ?> res
                                <div style="font-size: 9px; color: #993333;">-30% jatah</div>
                            </td>
                            <td style="text-align: right; vertical-align: middle; color: #1e7e34;">
                                <b style="font-size: 11px;">+<?php echo number_format($appraisal['net_total']); ?></b> res
                                <div style="font-size: 9px; color: #28a745;">(+<?php echo number_format($appraisal['net_each']); ?> tiap res)</div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <form method="POST" action="build.php" style="margin: 0;" onsubmit="return confirm('Gadaikan <?php echo addslashes($pItem['name']); ?> ke tengkulak Pasar Gelap seharga +<?php echo number_format($appraisal['net_total']); ?> total resource?');">
                                    <input type="hidden" name="ft" value="bm_pawn_item">
                                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                    <input type="hidden" name="row_id" value="<?php echo (int)$pItem['id']; ?>">
                                    <?php if ($pIsBag && $pQty > 1): ?>
                                        <div style="display: flex; justify-content: center; align-items: center; gap: 2px; margin-bottom: 3px;">
                                            <input type="number" name="qty" value="1" min="1" max="<?php echo $pQty; ?>" style="width: 38px; font-size: 10px; padding: 1px 2px; text-align: center; border: 1px solid #ccc; border-radius: 2px;">
                                            <span style="font-size: 9px; color: #888;">pcs</span>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="qty" value="1">
                                    <?php endif; ?>
                                    <button type="submit" style="background: #8a3b14; color: #fff; border: 1px solid #6b2a09; padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 3px; cursor: pointer; white-space: nowrap;">
                                        🤝 Gadai
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- PILAR 6: KITAB MUTASI LELUHUR (PERGANTIAN SUKU) -->
    <div style="background: linear-gradient(180deg, #fbf7ee 0%, #f4ebd9 100%); border: 1.5px solid #a67c4a; border-radius: 6px; padding: 14px; margin-bottom: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 1px dashed #cbb89d; padding-bottom: 6px; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #5a2c00; font-size: 14px;">
                📜 6. Kitab Mutasi Leluhur <span style="font-size: 11px; color: #8a6d3b; font-weight: normal;">(Scroll of Ancestral Transmutation — Pergantian Suku)</span>
            </h3>
            <span style="font-size: 10.5px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 2px 8px; border-radius: 3px; font-weight: bold;">
                ⭐ Item Legendaris Langka (100 Gold)
            </span>
        </div>

        <p style="font-size: 11.5px; color: #444; margin: 4px 0 12px 0; line-height: 1.55;">
            Kitab terlarang peninggalan bangsa kuno yang mampu mengubah garis keturunan peradaban akun Anda secara menyeluruh. Melalui ritual mutasi leluhur ini, Anda dapat beralih ke suku bangsa lain kapan saja di tengah permainan tanpa kehilangan progres akun Anda.
        </p>

        <!-- FITUR KONVERSI OTOMATIS -->
        <div style="background: #ffffff; border: 1px solid #e2d3bb; border-radius: 4px; padding: 10px 14px; margin-bottom: 12px; font-size: 11px; color: #555;">
            <b style="color: #693206; font-size: 11.5px;">🔄 Mekanisme Migrasi Otomatis & Terjamin (Full Atomic Transmutation):</b>
            <ul style="margin: 6px 0 0 18px; padding: 0; line-height: 1.6;">
                <li><b>Garis Pasukan:</b> Seluruh pasukan yang sudah jadi di setiap desa otomatis bermutasi ke unit ekuivalen suku baru (contoh: Prajurit Pedang &rarr; Legionnaire/Clubswinger/Phalanx).</li>
                <li><b>Benteng Pertahanan Desa:</b> Tembok desa (Slot 40) berganti ke tipe benteng suku baru tanpa menurunkan level dinding yang sudah dibangun.</li>
                <li><b>Riset Akademi:</b> Seluruh teknologi pasukan yang telah diteliti di Akademi dikonversikan setara ke unit suku baru.</li>
                <li><b>Hero Karakter:</b> Hero menyesuaikan tipe unit dasarnya ke suku baru dengan mempertahankan Level, Atribut, dan EXP secara utuh.</li>
                <li><b>Antrean Pelatihan & Riset:</b> Pasukan di antrean barak/istal dan riset aktif di Akademi langsung dikonversi mengikuti peradaban baru.</li>
            </ul>
        </div>

        <!-- FORM PILIH SUKU -->
        <div style="background: #fff; border: 1px solid #d9cbba; border-radius: 4px; padding: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                <div>
                    <span style="font-size: 11px; color: #666;">Suku Anda Saat Ini:</span> 
                    <span style="font-weight: bold; color: #2e6b38; background: #eaf3db; border: 1px solid #c7dfa7; border-radius: 3px; padding: 2px 7px; font-size: 11.5px;">
                        🏛️ <?php echo htmlspecialchars($currentTribeName); ?> (Suku #<?php echo $userTribe; ?>)
                    </span>
                </div>
                <div>
                    <span style="font-size: 11px; color: #666;">Biaya Kitab:</span> 
                    <span style="font-weight: bold; color: #9c6500; font-size: 12px;">
                        💰 100 Gold
                    </span>
                    <span style="font-size: 10.5px; color: #777;">(Saldo Anda: <b><?php echo number_format($userGold); ?></b> Gold)</span>
                </div>
            </div>

            <form method="POST" action="build.php" style="margin: 0;" onsubmit="return confirmTribeChange();">
                <input type="hidden" name="ft" value="bm_change_tribe">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

                <table cellpadding="6" cellspacing="0" style="width: 100%; font-size: 11px; border-collapse: collapse; margin-bottom: 10px;">
                    <thead>
                        <tr style="background: #f3ece0; border-bottom: 1px solid #d5c8b2;">
                            <th style="width: 35px; text-align: center; padding: 6px 4px;">Pilih</th>
                            <th style="text-align: left; padding: 6px 8px;">Nama Suku Tujuan</th>
                            <th style="text-align: left; padding: 6px 8px;">Karakteristik & Keunggulan</th>
                            <th style="width: 140px; text-align: center; padding: 6px 8px;">Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $tIdx = 0;
                        foreach ($availableTribes as $tId => $tData): 
                            $tIdx++;
                            $rowBg = ($tIdx % 2 === 0) ? '#faf7f2' : '#ffffff';
                        ?>
                        <tr style="background: <?php echo $rowBg; ?>; border-bottom: 1px solid #eee;">
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="radio" name="new_tribe" id="tribe_opt_<?php echo $tId; ?>" value="<?php echo $tId; ?>" <?php if ($tIdx === 1) echo 'checked'; ?> style="cursor: pointer;">
                            </td>
                            <td style="vertical-align: middle; padding: 6px 8px;">
                                <label for="tribe_opt_<?php echo $tId; ?>" style="font-weight: bold; color: #4a2808; cursor: pointer; font-size: 11.5px;">
                                    🏛️ <?php echo htmlspecialchars($tData['name']); ?>
                                </label>
                            </td>
                            <td style="vertical-align: middle; padding: 6px 8px; color: #555; line-height: 1.35;">
                                <label for="tribe_opt_<?php echo $tId; ?>" style="cursor: pointer;">
                                    <?php echo htmlspecialchars($tData['desc']); ?>
                                </label>
                            </td>
                            <td style="text-align: center; vertical-align: middle; padding: 6px 8px;">
                                <span style="font-size: 10px; background: #eef2f7; color: #2c4d6f; border: 1px solid #c9d7e4; border-radius: 3px; padding: 1px 6px; font-weight: bold;">
                                    <?php echo htmlspecialchars($tData['badge']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; border-top: 1px dashed #d9cbba; padding-top: 10px;">
                    <div style="font-size: 10px; color: #888;">
                        <i>⚠️ Perhatian: Mutasi leluhur bersifat permanen untuk sesi tersebut sampai Anda menggunakan Kitab Mutasi Leluhur kembali.</i>
                    </div>
                    <div>
                        <?php if ($userGold >= 100): ?>
                            <button type="submit" style="background: #8a3b14; color: #fff; border: 1px solid #6b2a09; padding: 6px 14px; font-size: 11.5px; font-weight: bold; border-radius: 3px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                📜 Beli Kitab Mutasi Leluhur (100 Gold)
                            </button>
                        <?php else: ?>
                            <button type="button" disabled style="background: #bbb; color: #fff; border: 1px solid #aaa; padding: 6px 14px; font-size: 11.5px; font-weight: bold; border-radius: 3px; cursor: not-allowed;">
                                ❌ Gold Tidak Cukup (Butuh 100 Gold)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
var userSilverBalance = <?php echo (int)$silver; ?>;

function updateLaunderPreview() {
    var amt = parseInt(document.getElementById('bm_amount').value) || 0;
    var preview = document.getElementById('bm_preview');
    if (amt <= 0) {
        preview.innerText = '0';
        return;
    }
    var yieldAmt = Math.floor(amt * 0.85);
    preview.innerText = yieldAmt.toLocaleString();
}

function updateConsumableTotal(itemId, unitPrice) {
    var qtyInput = document.getElementById('qty_' + itemId);
    var totalLbl = document.getElementById('total_lbl_' + itemId);
    var btn = document.getElementById('btn_c_' + itemId);
    
    if (!qtyInput || !totalLbl) return;
    
    var qty = parseInt(qtyInput.value) || 0;
    if (qty < 1) {
        qty = 1;
        qtyInput.value = 1;
    }
    var total = qty * unitPrice;
    
    totalLbl.innerText = total.toLocaleString();
    
    if (btn) {
        if (total > userSilverBalance) {
            btn.disabled = true;
            btn.style.background = '#bbb';
            btn.style.borderColor = '#aaa';
            btn.style.cursor = 'not-allowed';
            btn.style.boxShadow = 'none';
            btn.innerText = '❌ Silver Kurang';
        } else {
            btn.disabled = false;
            btn.style.background = '#2e6b38';
            btn.style.borderColor = '#1e4b25';
            btn.style.cursor = 'pointer';
            btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.15)';
            btn.innerText = '🛒 Beli Instan';
        }
    }
}

function confirmTribeChange() {
    var selected = document.querySelector('input[name="new_tribe"]:checked');
    if (!selected) {
        alert('Silakan pilih suku tujuan terlebih dahulu.');
        return false;
    }
    return confirm('Apakah Anda yakin ingin menggunakan Kitab Mutasi Leluhur seharga 100 Gold untuk berganti suku? Seluruh pasukan, benteng desa, riset akademi, antrean latih, dan Hero Anda akan dimigrasikan ke suku baru secara permanen.');
}
</script>

