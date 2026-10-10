<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : 17_6.tpl                                                  ##
##  Type           : BUILDING TEMPLATE - MERCENARY ENCLAVE                     ##
##  Purpose        : Neutral Mercenary hiring hall & garrison management       ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

global $database, $session, $village, $id;

require_once __DIR__ . '/../../GameEngine/Mercenary.php';
require_once __DIR__ . '/../../GameEngine/BlackMarket.php';

$level = (int)$village->resarray['f'.$id];
$silver = BlackMarket::getSilver((int)$session->uid);
$unitsInfo = Mercenary::getUnitsInfo();
$garrison = Mercenary::getGarrison((int)$village->wid);

$flash = null;
if (!empty($_SESSION['merc_flash'])) {
    $flash = $_SESSION['merc_flash'];
    unset($_SESSION['merc_flash']);
}
?>

<div id="build" class="gid17">
    <a href="#" onClick="return Popup(17,4);" class="build_logo">
        <img class="building g17" src="img/x.gif" alt="<?php echo MARKETPLACE;?>" title="<?php echo MARKETPLACE;?>" />
    </a>
    <h1><?php echo MARKETPLACE;?> <span class="level"><?php echo LEVEL;?> <?php echo $level;?></span></h1>
    <p class="build_desc"><?php echo MARKETPLACE_DESC;?></p>

    <?php include("17_menu.tpl");?>

    <!-- BANNER ENCLAVE TENTARA BAYARAN -->
    <div style="background: linear-gradient(135deg, #1c2b1e 0%, #293d2b 100%); border: 2px solid #476a4a; border-radius: 6px; padding: 12px 16px; margin: 15px 0 12px 0; color: #f4eee2; box-shadow: 0 3px 6px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0; color: #a4de02; font-size: 16px; text-shadow: 1px 1px 2px #000;">
                    ⚔️ Markas Tentara Bayaran Netral <span style="font-size: 11px; color: #bbb; font-weight: normal;">(Mercenary Enclave)</span>
                </h2>
                <div style="font-size: 11px; color: #d0decb; margin-top: 3px;">
                    Sewa serdadu independen tanpa pandang suku menggunakan koin Silver. Bertempur mati-matian mempertahankan desa!
                </div>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 4px;">
                <div style="background: #111a12; border: 1px solid #76a079; border-radius: 4px; padding: 5px 10px; text-align: right;">
                    <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Saldo Koin Silver</span><br />
                    <span style="font-size: 15px; font-weight: bold; color: #fff;">💰 <?php echo number_format($silver); ?></span>
                </div>
                <div style="background: #111a12; border: 1px solid #76a079; border-radius: 4px; padding: 5px 10px; text-align: right;">
                    <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Garnisun Aktif</span><br />
                    <span style="font-size: 15px; font-weight: bold; color: #a4de02;">🛡️ <?php echo number_format($garrison['total_troops']); ?></span>
                    <span style="font-size: 10px; color: #ccc;">(<?php echo $garrison['upkeep']; ?> crop/jam)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RUMOR PERKUMPULAN ASSASSIN -->
    <?php
    require_once __DIR__ . '/../../GameEngine/Assassin.php';
    $sanctuary = Assassin::getSanctuary();
    $sanctuaryHint = Assassin::getRumorDirection((int)$village->wid);
    $sWref = (int)($sanctuary['wref'] ?? 0);
    $sCheck = $sWref ? $generator->getMapCheck($sWref) : '';
    ?>
    <div style="background: linear-gradient(135deg, #181216 0%, #291a1d 100%); border: 1px solid #632b2b; border-radius: 6px; padding: 10px 14px; margin: 10px 0 16px 0; color: #eed8d8; font-size: 11px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div>
                <b style="color: #e74c3c;">📜 Bisikan Gelap: Perkumpulan Assassin (Kuil Bayangan)</b><br />
                Para pengelana membisikkan bahwa markas rahasia pembunuh bayaran tersembunyi di <b><?php echo $sanctuaryHint; ?></b>. Mereka menerima kontrak penyerangan, racun hero &amp; sabotase tanpa meninggalkan jejak identitas pemesan.
            </div>
            <div>
                <a href="karte.php?d=<?php echo $sWref; ?>&c=<?php echo $sCheck; ?>" style="background: #7a1d1d; color: #fff; text-decoration: none; padding: 5px 12px; border-radius: 4px; font-weight: bold; border: 1px solid #a93226; display: inline-block;">
                    🗺️ Kunjungi Kuil (<?php echo $sanctuary['x'] . '|' . $sanctuary['y']; ?>) &raquo;
                </a>
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

    <!-- FORM REKRUTMEN TENTARA BAYARAN -->
    <form method="POST" action="build.php" id="merc_form" style="margin: 0;">
        <input type="hidden" name="ft" value="merc_hire">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
            <?php foreach ($unitsInfo as $uId => $u): ?>
                <div style="background: #fff; border: 1px solid #d4c8b6; border-radius: 5px; padding: 10px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                        <div style="font-size: 32px; width: 44px; text-align: center; filter: drop-shadow(0 2px 3px rgba(0,0,0,0.2));">
                            <?php echo $u['icon']; ?>
                        </div>
                        <div>
                            <div style="font-weight: bold; font-size: 13px; color: #3b2816;">
                                <?php echo $u['name']; ?>
                                <span style="font-size: 11px; font-weight: normal; color: #777;">(<?php echo $u['en_name']; ?>)</span>
                                <span style="font-size: 10px; background: #e9ecef; color: #495057; padding: 2px 5px; border-radius: 3px; margin-left: 4px;">
                                    <?php echo $u['type']; ?>
                                </span>
                            </div>
                            <div style="font-size: 11px; color: #555; margin: 2px 0;">
                                <?php echo $u['desc']; ?>
                            </div>
                            <div style="font-size: 11px; color: #333; margin-top: 3px;">
                                ⚔️ <b>Serang:</b> <?php echo $u['atk']; ?> &nbsp;|&nbsp;
                                🛡️ <b>Def Inf:</b> <span style="color:#2b662a; font-weight:bold;"><?php echo $u['di']; ?></span> &nbsp;|&nbsp;
                                🐎 <b>Def Kav:</b> <span style="color:#1b508f; font-weight:bold;"><?php echo $u['dc']; ?></span> &nbsp;|&nbsp;
                                🌾 <b>Upkeep:</b> <?php echo $u['upkeep']; ?> crop/jam
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right; min-width: 140px; padding-left: 10px; border-left: 1px dashed #ddd;">
                        <div style="font-size: 12px; font-weight: bold; color: #996600; margin-bottom: 4px;">
                            💰 <?php echo $u['cost_silver']; ?> Silver / unit
                        </div>
                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                            <span style="font-size: 11px; color: #666;">Jumlah:</span>
                            <input type="number" name="<?php echo $u['key']; ?>" id="input_<?php echo $u['key']; ?>" value="0" min="0" max="500" style="width: 55px; padding: 3px 5px; text-align: center; border: 1px solid #ccc; border-radius: 3px;" oninput="recalcMercCost()">
                        </div>
                        <div style="font-size: 10px; color: #888; margin-top: 3px;">
                            Di desa: <b><?php echo number_format($garrison[$u['key']]); ?></b> unit
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SUMMARY RECRUITMENT BOX -->
        <div style="background: #f7f3ea; border: 1px solid #cbb89d; border-radius: 5px; padding: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <span style="font-size: 12px; color: #444;">Total Biaya Kontrak:</span>
                <span id="merc_total_silver" style="font-size: 16px; font-weight: bold; color: #996600; margin-left: 4px;">0</span>
                <span style="font-size: 12px; color: #666;">Koin Silver</span>
                <span id="merc_silver_warning" style="display: none; color: #dc3545; font-size: 11px; margin-left: 10px; font-weight: bold;">⚠️ Saldo Silver tidak cukup!</span>
            </div>
            <div>
                <button type="submit" id="merc_btn_submit" style="background: #2e6b38; color: #fff; border: 1px solid #1c4824; padding: 7px 18px; font-weight: bold; font-size: 12px; border-radius: 3px; cursor: pointer;">
                    📜 Tanda Tangani Kontrak Rekrutmen
                </button>
            </div>
        </div>
    </form>

    <!-- MANAJEMEN GARNISUN SAAT INI (DISMISS / PULANGKAN) -->
    <?php if ($garrison['total_troops'] > 0): ?>
        <div style="background: #faf6ee; border: 1px solid #cbb89d; border-radius: 6px; padding: 14px; margin-bottom: 16px;">
            <h3 style="margin: 0 0 6px 0; color: #693206; font-size: 14px; border-bottom: 1px dashed #cbb89d; padding-bottom: 5px;">
                📋 Garnisun Aktif & Pemulangan Kontrak
            </h3>
            <p style="font-size: 11px; color: #555; margin: 4px 0 10px 0;">
                Pasukan bayaran di bawah ini saat ini sedang bertugas sebagai garnisun penjaga di desa Anda. Jika desa mengalami krisis lumbung gandum, Anda dapat memulangkan sebagian unit untuk memotong biaya makan per jam.
            </p>

            <form method="POST" action="build.php" style="margin: 0;">
                <input type="hidden" name="ft" value="merc_dismiss">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

                <table cellpadding="6" cellspacing="2" style="width: 100%; font-size: 12px; background: #fff; border: 1px solid #e0d5c1; border-radius: 4px;">
                    <tr style="background: #f3ece0;">
                        <th style="text-align: left;">Prajurit Bayaran</th>
                        <th style="text-align: center;">Jumlah Aktif</th>
                        <th style="text-align: center;">Beban Upkeep</th>
                        <th style="text-align: right;">Jumlah Dipulangkan</th>
                    </tr>
                    <?php foreach ($unitsInfo as $uId => $u): ?>
                        <tr>
                            <td><?php echo $u['icon'] . ' ' . $u['name']; ?></td>
                            <td style="text-align: center; font-weight: bold;"><?php echo number_format($garrison[$u['key']]); ?></td>
                            <td style="text-align: center; color: #666;"><?php echo ($garrison[$u['key']] * $u['upkeep']); ?> crop/jam</td>
                            <td style="text-align: right;">
                                <input type="number" name="<?php echo $u['key']; ?>" value="0" min="0" max="<?php echo $garrison[$u['key']]; ?>" style="width: 55px; padding: 2px 4px; text-align: center; border: 1px solid #ccc; border-radius: 3px;" <?php if($garrison[$u['key']] <= 0) echo 'disabled'; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                    <span style="font-size: 11px; color: #777;">
                        ⚠️ <i>Catatan Disersi: Jika stok gandum habis mencapai 0, tentara bayaran akan melarikan diri secara mandiri terlebih dahulu untuk mencegah kematian pasukan reguler Anda.</i>
                    </span>
                    <button type="submit" style="background: #a94442; color: #fff; border: 1px solid #843534; padding: 5px 12px; font-weight: bold; font-size: 11px; border-radius: 3px; cursor: pointer;">
                        🚪 Pulangkan Pasukan Terpilih
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
var playerSilver = <?php echo (int)$silver; ?>;
var costs = {
    m1: 4,
    m2: 3,
    m3: 7,
    m4: 8
};

function recalcMercCost() {
    var c1 = parseInt(document.getElementById('input_m1').value) || 0;
    var c2 = parseInt(document.getElementById('input_m2').value) || 0;
    var c3 = parseInt(document.getElementById('input_m3').value) || 0;
    var c4 = parseInt(document.getElementById('input_m4').value) || 0;

    var totalCost = (c1 * costs.m1) + (c2 * costs.m2) + (c3 * costs.m3) + (c4 * costs.m4);
    var label = document.getElementById('merc_total_silver');
    var warn = document.getElementById('merc_silver_warning');
    var btn = document.getElementById('merc_btn_submit');

    label.innerText = totalCost.toLocaleString();

    if (totalCost > playerSilver) {
        warn.style.display = 'inline';
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
    } else {
        warn.style.display = 'none';
        btn.disabled = false;
        btn.style.opacity = '1.0';
        btn.style.cursor = 'pointer';
    }
}
</script>
