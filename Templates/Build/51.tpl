<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : 51.tpl                                                    ##
##  Type           : BUILDING TEMPLATE - SANGGAR PARANORMAL (NUSANTARA)        ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Antigravity & Shadow                                      ##
##  Project        : TravianZ                                                  ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

global $village, $building, $database, $session, $bid51, $id, $loopsame, $doublebuild, $master, $generator;

require_once dirname(__DIR__, 2) . '/GameEngine/Paranormal.php';

include("next.tpl");

$level = (int)$village->resarray['f'.$id];
$currentCap = Paranormal::getMaxCapacity($level);
$spirits = Paranormal::getVillageSpirits($village->wid);
$usedSlots = Paranormal::getUsedSlots($spirits);
$freeSlots = max(0, $currentCap - $usedSlots);

$tuyulActive = !empty($spirits['tuyul_until']) && (int)$spirits['tuyul_until'] > time();
$tuyulTimeLeft = $tuyulActive ? ((int)$spirits['tuyul_until'] - time()) : 0;
?>

<div id="build" class="gid51">
    <h1><?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?> <span class="level"><?php echo LEVEL;?> <?php echo $level;?></span></h1>
    <p class="build_desc">
        <a href="#" onClick="return Popup(51,4,'gid');" class="build_logo">
            <img class="building g51" src="img/x.gif" alt="<?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?>" title="<?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?>" />
        </a>
        <?php echo defined('PARANORMAL_SANCTUARY_DESC') ? PARANORMAL_SANCTUARY_DESC : 'Tempat pertapaan mistis para sesepuh Nusantara untuk mengikat dan memanggil makhluk astral sebagai pelindung desa dan pembawa berkah kelimpahan.'; ?>
    </p>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'summon_ok'): ?>
            <p style="color:#2e7d32;font-weight:bold;padding:5px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:3px;">
                ✓ Ritual pemanggilan gaib berhasil diselesaikan! Pasukan astral kini menjaga desa.
            </p>
        <?php elseif ($_GET['msg'] == 'tuyul_ok'): ?>
            <p style="color:#2e7d32;font-weight:bold;padding:5px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:3px;">
                ✓ Perjanjian berkah Tuyul terjalin! Pasukan penyerang Anda mendapatkan bonus jarahan +15% selama 24 jam.
            </p>
        <?php elseif ($_GET['msg'] == 'dismiss_ok'): ?>
            <p style="color:#e65100;font-weight:bold;padding:5px;background:#fff3e0;border:1px solid #ffcc80;border-radius:3px;">
                ✓ Makhluk gaib telah dikembalikan ke alam baka. Kapasitas slot mistis dibebaskan.
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <table cellpadding="1" cellspacing="1" id="build_value">
        <tr>
            <th>Kapasitas Wadah Gaib:</th>
            <td><b><?php echo $currentCap;?></b> slot gaib (Terpakai: <b><?php echo $usedSlots;?></b> | Sisa: <b><?php echo $freeSlots;?></b>)</td>
        </tr>
        <?php if (!$building->isMax($village->resarray['f'.$id.'t'], $id)):
            $next = $level + 1 + $loopsame + $doublebuild + $master;
            $next = $next > 20 ? 20 : $next;
        ?>
        <tr>
            <th>Kapasitas Level <?php echo $next;?>:</th>
            <td><b><?php echo Paranormal::getMaxCapacity($next);?></b> slot gaib</td>
        </tr>
        <?php endif;?>
    </table>

    <!-- SECTION PESUGIHAN TUYUL -->
    <div style="margin:15px 0;padding:10px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
        <h4 style="margin:0 0 6px 0;color:#5d4037;">🕯️ Ritual Khodam Tuyul (+15% Hasil Rampasan / Raid Bounty)</h4>
        <p style="font-size:11px;margin:0 0 8px 0;color:#555;">
            Memelihara tuyul gaib untuk memandu perampok Anda mencuri perak dan sumber daya ekstra dari desa target.
        </p>
        <?php if ($tuyulActive): ?>
            <p style="color:#1b5e20;font-weight:bold;margin:4px 0;">
                ✨ Tuyul Aktif! Sisa Durasi: <span id="timer1"><?php echo $generator->getTimeFormat($tuyulTimeLeft); ?></span> (Bonus +15% Jarahan)
            </p>
        <?php else:
            $tCost = Paranormal::$tuyulCost;
            $canAffordTuyul = ($village->awood >= $tCost['wood'] && $village->aclay >= $tCost['clay'] && $village->airon >= $tCost['iron'] && $village->acrop >= $tCost['crop']);
        ?>
            <form method="POST" action="build.php" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>" />
                <input type="hidden" name="cmd" value="paranormal" />
                <input type="hidden" name="action" value="tuyul" />
                <div style="font-size:11px;margin-bottom:6px;">
                    Biaya Ritual (24 Jam):
                    <img class="r1" src="img/x.gif" title="<?php echo LUMBER;?>"/> <?php echo $tCost['wood']; ?> |
                    <img class="r2" src="img/x.gif" title="<?php echo CLAY;?>"/> <?php echo $tCost['clay']; ?> |
                    <img class="r3" src="img/x.gif" title="<?php echo IRON;?>"/> <?php echo $tCost['iron']; ?> |
                    <img class="r4" src="img/x.gif" title="<?php echo CROP;?>"/> <?php echo $tCost['crop']; ?>
                </div>
                <?php if ($canAffordTuyul): ?>
                    <button type="submit" class="build" style="cursor:pointer;padding:3px 10px;font-weight:bold;">Mulai Ritual Tuyul (24 Jam)</button>
                <?php else: ?>
                    <span style="color:#c62828;font-size:11px;">Sumber daya tidak mencukupi untuk ritual ini.</span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- SECTION PEMANGGILAN MAKHLUK GAIB -->
    <h4 style="margin:15px 0 6px 0;">👻 Pemanggilan Makhluk Penjaga Gaib</h4>
    <p style="font-size:11px;color:#666;margin:0 0 10px 0;">
        Makhluk gaib menjaga desa secara langsung saat diserang (tidak memakan gandum harian, namun slot terbatas dan mereka sirna saat gugur dalam pertempuran).
    </p>

    <table cellpadding="1" cellspacing="1" class="build_details">
        <thead>
            <tr>
                <td>Makhluk Gaib</td>
                <td>Pertahanan</td>
                <td>Slot</td>
                <td>Di Desa</td>
                <td>Jumlah Panggil</td>
            </tr>
        </thead>
        <tbody>
            <?php foreach (Paranormal::$spiritStats as $sType => $sData):
                $curCount = (int)($spirits[$sType == 1 ? 'pocong' : ($sType == 2 ? 'kuntilanak' : 'genderuwo')] ?? 0);
                $cost = $sData['cost'];
                $slotsPerUnit = $sData['slots'];

                // Hitung max berdasarkan res dan free slots
                $maxByRes = min(
                    $cost['wood'] > 0 ? floor($village->awood / $cost['wood']) : 9999,
                    $cost['clay'] > 0 ? floor($village->aclay / $cost['clay']) : 9999,
                    $cost['iron'] > 0 ? floor($village->airon / $cost['iron']) : 9999,
                    $cost['crop'] > 0 ? floor($village->acrop / $cost['crop']) : 9999
                );
                $maxBySlot = floor($freeSlots / $slotsPerUnit);
                $maxSummon = max(0, min($maxByRes, $maxBySlot));
            ?>
            <tr>
                <td class="desc">
                    <div class="tit" style="font-weight:bold;">
                        <?php echo htmlspecialchars($sData['name']); ?>
                    </div>
                    <div style="font-size:10px;color:#666;margin:2px 0;">
                        <?php echo htmlspecialchars($sData['desc']); ?>
                    </div>
                    <div class="details" style="font-size:11px;">
                        <img class="r1" src="img/x.gif" title="<?php echo LUMBER;?>"/><?php echo $cost['wood'];?> |
                        <img class="r2" src="img/x.gif" title="<?php echo CLAY;?>"/><?php echo $cost['clay'];?> |
                        <img class="r3" src="img/x.gif" title="<?php echo IRON;?>"/><?php echo $cost['iron'];?> |
                        <img class="r4" src="img/x.gif" title="<?php echo CROP;?>"/><?php echo $cost['crop'];?>
                    </div>
                </td>
                <td style="text-align:center;font-size:11px;">
                    🛡️ <?php echo $sData['def_i']; ?> Inf<br />
                    🐎 <?php echo $sData['def_c']; ?> Kav
                </td>
                <td style="text-align:center;font-weight:bold;">
                    <?php echo $slotsPerUnit; ?>
                </td>
                <td style="text-align:center;font-weight:bold;color:#1565c0;">
                    <?php echo $curCount; ?>
                </td>
                <td style="text-align:center;">
                    <form method="POST" action="build.php" style="display:inline-block;margin:0;">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>" />
                        <input type="hidden" name="cmd" value="paranormal" />
                        <input type="hidden" name="action" value="summon" />
                        <input type="hidden" name="spirit_type" value="<?php echo $sType; ?>" />
                        <input type="number" class="text" name="amount" id="sum_amt_<?php echo $sType; ?>" value="0" min="0" max="<?php echo $maxSummon; ?>" style="width:45px;text-align:center;" />
                        <a href="#" onClick="document.getElementById('sum_amt_<?php echo $sType; ?>').value=<?php echo $maxSummon; ?>; return false;" style="font-size:11px;">(<?php echo $maxSummon; ?>)</a>
                        <br />
                        <button type="submit" style="margin-top:4px;cursor:pointer;padding:2px 6px;font-size:11px;" <?php if ($maxSummon <= 0) echo 'disabled'; ?>>Panggil</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- SECTION KEMBALIKAN KE ALAM BAKA (DISMISS) -->
    <?php if ($usedSlots > 0): ?>
    <div style="margin:15px 0;padding:8px;background:#fafafa;border:1px dashed #ccc;font-size:11px;">
        <span style="font-weight:bold;color:#c62828;">Kembalikan ke Alam Baka (Lepas Makhluk Gaib):</span>
        <form method="POST" action="build.php" style="display:inline;margin-left:10px;">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>" />
            <input type="hidden" name="cmd" value="paranormal" />
            <input type="hidden" name="action" value="dismiss" />
            <select name="spirit_type" style="font-size:11px;">
                <option value="1">Pocong (Ada: <?php echo (int)$spirits['pocong']; ?>)</option>
                <option value="2">Kuntilanak (Ada: <?php echo (int)$spirits['kuntilanak']; ?>)</option>
                <option value="3">Genderuwo (Ada: <?php echo (int)$spirits['genderuwo']; ?>)</option>
            </select>
            Jumlah: <input type="number" name="amount" value="1" min="1" style="width:40px;text-align:center;" />
            <button type="submit" style="cursor:pointer;font-size:11px;">Lepas</button>
        </form>
    </div>
    <?php endif; ?>

    <?php include("upgrade.tpl");?>
</div>
