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

$level = (int)$village->resarray['f'.$id];
$silver = BlackMarket::getSilver((int)$session->uid);

$awood = (int)$database->getWoodAvailable($village->wid);
$aclay = (int)$database->getClayAvailable($village->wid);
$airon = (int)$database->getIronAvailable($village->wid);
$acrop = (int)$database->getCropAvailable($village->wid);

$isPlagued = Plague::isVillageInfected($village->wid);
$plagueInfo = $isPlagued ? Plague::getVillagePlague($village->wid) : null;

$flash = null;
if (!empty($_SESSION['bm_flash'])) {
    $flash = $_SESSION['bm_flash'];
    unset($_SESSION['bm_flash']);
}
?>

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
                    Pusat penukaran komoditas terlarang, pencucian sumber daya tanpa Gold, dan pengadaan kontraband rahasia.
                </div>
            </div>
            <div style="background: #11100e; border: 1px solid #d4af37; border-radius: 4px; padding: 6px 12px; text-align: right; margin-top: 4px;">
                <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Saldo Koin Silver</span><br />
                <span style="font-size: 16px; font-weight: bold; color: #f5f5f5;">💰 <?php echo number_format($silver); ?></span>
                <span style="font-size: 11px; color: #d4af37;">Silver</span>
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
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+2.000 Tiap Sumber Daya</div>
                <div style="background: #f4eee2; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>8.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="1">
                    <button type="submit" <?php if($silver < 10) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 10 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 10 ? 'pointer' : 'not-allowed'; ?>;">
                        10 Silver
                    </button>
                </form>
            </div>

            <!-- TIER 2 -->
            <div style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #d4af37; border-radius: 5px; padding: 10px; text-align: center; box-shadow: 0 0 4px rgba(212,175,55,0.2);">
                <div style="font-weight: bold; color: #8a6d3b; font-size: 12px;">Kargo Pedagang ⭐</div>
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+6.000 Tiap Sumber Daya</div>
                <div style="background: #fdfaf2; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>24.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="2">
                    <button type="submit" <?php if($silver < 25) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 25 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 25 ? 'pointer' : 'not-allowed'; ?>;">
                        25 Silver
                    </button>
                </form>
            </div>

            <!-- TIER 3 -->
            <div style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #993300; border-radius: 5px; padding: 10px; text-align: center;">
                <div style="font-weight: bold; color: #993300; font-size: 12px;">Peti Saudagar Gelap 👑</div>
                <div style="color: #666; font-size: 10px; margin: 2px 0 6px 0;">+16.000 Tiap Sumber Daya</div>
                <div style="background: #fdf4ee; padding: 4px; border-radius: 3px; font-size: 11px; margin-bottom: 8px;">
                    Total: <b>64.000</b> res
                </div>
                <form method="POST" action="build.php" style="margin: 0;">
                    <input type="hidden" name="ft" value="bm_shipment">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="tier" value="3">
                    <button type="submit" <?php if($silver < 60) echo 'disabled'; ?> style="width: 100%; padding: 5px; font-weight: bold; background: <?php echo $silver >= 60 ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo $silver >= 60 ? 'pointer' : 'not-allowed'; ?>;">
                        60 Silver
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
                    15 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="antidote">
                        <button type="submit" <?php if(!$isPlagued || $silver < 15) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($isPlagued && $silver >= 15) ? '#b02a37' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($isPlagued && $silver >= 15) ? 'pointer' : 'not-allowed'; ?>;">
                            Sembuhkan
                        </button>
                    </form>
                </td>
            </tr>

            <!-- ITEM 2: GRAIN RESERVE -->
            <tr style="background: #faf7f2;">
                <td>
                    <b>🌾 Lumbung Darurat Ransum</b><br />
                    <span style="font-size: 10px; color: #666;">Menyuplai +4.000 gandum instan untuk menyelamatkan desa dari krisis kelaparan pasukan.</span>
                </td>
                <td style="text-align: center; font-size: 11px; color: #444;">
                    Lumbung: <b><?php echo number_format($acrop); ?></b> Gandum
                </td>
                <td style="text-align: center; font-weight: bold; color: #996600;">
                    12 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="grain_reserve">
                        <button type="submit" <?php if($silver < 12) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($silver >= 12) ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($silver >= 12) ? 'pointer' : 'not-allowed'; ?>;">
                            Beli Ransum
                        </button>
                    </form>
                </td>
            </tr>

            <!-- ITEM 3: FORGED MERCHANT PASS -->
            <tr>
                <td>
                    <b>📜 Surat Jalan Pedagang Palsu</b><br />
                    <span style="font-size: 10px; color: #666;">Izin jalan bebas pajak yang memberi pasokan logistik selundupan ekstra +1.500 tiap res.</span>
                </td>
                <td style="text-align: center; font-size: 11px; color: #444;">
                    Siap Pakai
                </td>
                <td style="text-align: center; font-weight: bold; color: #996600;">
                    8 Silver
                </td>
                <td style="text-align: right;">
                    <form method="POST" action="build.php" style="margin: 0;">
                        <input type="hidden" name="ft" value="bm_contraband">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="item_key" value="merchant_pass">
                        <button type="submit" <?php if($silver < 8) echo 'disabled'; ?> style="padding: 4px 8px; font-size: 11px; font-weight: bold; background: <?php echo ($silver >= 8) ? '#386b2e' : '#ccc'; ?>; color: #fff; border: none; border-radius: 3px; cursor: <?php echo ($silver >= 8) ? 'pointer' : 'not-allowed'; ?>;">
                            Beli Surat
                        </button>
                    </form>
                </td>
            </tr>
        </table>
    </div>
</div>

<script type="text/javascript">
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
</script>
