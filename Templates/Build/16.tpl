<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : RALLYPOINT                                                ##
##  Type           : BUILDING TEMPLATE                                         ##
## --------------------------------------------------------------------------- ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  Test Server    : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if (isset($_GET['refresh'])) {
    $village->unitarray = $database->getUnit($village->wid, false);
}
$hasRally = $village->resarray['f39'] > 0;
?>
<div id="build" class="gid16">
    <a href="#" onclick="return Popup(16,4);" class="build_logo">
        <img class="g16" src="img/x.gif" alt="<?php echo RALLYPOINT; ?>" title="<?= RALLYPOINT ?>">
    </a>
    <h1><?= RALLYPOINT ?> <span class="level"><?= LEVEL ?> <?= $village->resarray['f'.$id] ?></span></h1>
    <p class="build_desc"><?= RALLYPOINT_DESC ?></p>

<?php if ($hasRally): ?>
    <?php include_once '16_menu.tpl'; ?>

    <?php
    $curWeather = class_exists('Weather') ? Weather::getCurrentWeather() : null;
    $isKarhutla = ($curWeather && (int)($curWeather['weather_type'] ?? 0) === Weather::TYPE_KARHUTLA);
    if ($isKarhutla):
    ?>
    <div style="background:#fff3e0; border-left:4px solid #d35400; padding:8px 12px; margin:8px 0 12px 0; border-radius:4px; font-size:11px; color:#a04000; line-height:1.45; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <b>🔥 Peringatan Cuaca: Badai Asap Karhutla Aktif!</b><br>
        Jarak pandang berkurang <b>90%</b> akibat kabut asap kebakaran hutan &amp; lahan. Pasukan musuh yang bergerak menyerang diselimuti kabut asap pekat dan baru terdeteksi di Titik Temu saat sisa waktu tiba <b>&le; 10 menit</b>!
    </div>
    <?php endif; ?>

    <?php
    // --- INCOMING ---
    $units_type = $database->getMovement(34, $village->wid, 1);
    $settlers = $database->getMovement(7, $village->wid, 1);
    $oasis_incoming = 0;
    foreach ($database->getOasis($village->wid) as $o) {
        foreach ($database->getMovement(6, $o['wref'], 0) as $om) {
            if ($om['attack_type'] != 2 && $om['attack_type'] != 1 && class_exists('Weather') && !Weather::isAttackVisibleUnderWeather((int)$om['endtime'], (int)$om['attack_type'])) {
                continue; // Masked by Karhutla
            }
            $oasis_incoming++;
        }
    }
    $units_incoming = 0;
    foreach ($units_type as $u) {
        if ($u['attack_type'] == 1 && $u['sort_type'] == 3) continue;
        if (($u['attack_type'] == 3 || $u['attack_type'] == 4) && class_exists('Weather') && !Weather::isAttackVisibleUnderWeather((int)$u['endtime'], (int)$u['attack_type'])) {
            continue; // Masked by Karhutla
        }
        $units_incoming++;
    }
    $totalIncoming = $units_incoming + count($settlers) + $oasis_incoming;
    ?>

    <?php if ($totalIncoming > 0): ?>
        <h4><?= INCOMING_TROOPS ?> (<?= $totalIncoming ?>)</h4>
        <?php include '16_incomming.tpl'; ?>
    <?php endif; ?>

    <!-- OWN TROOPS -->
    <h4><?= TROOPS_IN_THE_VILLAGE ?></h4>
    <table class="troop_details" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <td class="role"><a href="karte.php?d=<?= $village->wid ?>&c=<?= $generator->getMapCheck($village->wid) ?>"><?= $village->vname ?></a></td>
                <td colspan="<?= $village->unitarray['hero'] ? 11 : 10 ?>"><a href="spieler.php?uid=<?= $session->uid ?>"><?= OWN_TROOPS ?></a></td>
            </tr>
        </thead>
        <tbody class="units"><?php include '16_troops.tpl'; ?></tbody>
    </table>

    <!-- REINFORCEMENTS TO ME -->
    <?php foreach ($village->enforcetome as $e):
        $isTaskmaster = $e['from'] == 0;
        $colspan = 10 + $e['hero'];
        $tribe = $isTaskmaster ? 4 : $database->getUserField($database->getVillageField($e['from'],'owner'),'tribe',0);
        $start = ($tribe-1)*10+1;
    ?>
    <table class="troop_details" cellpadding="1" cellspacing="1">
        <thead><tr>
            <td class="role">
                <?php if (!$isTaskmaster): ?>
                    <a href="karte.php?d=<?= $e['from'] ?>&c=<?= $generator->getMapCheck($e['from']) ?>"><?= $database->getVillageField($e['from'],'name') ?></a>
                <?php else: ?><a><?= TASKMASTER ?></a><?php endif; ?>
            </td>
            <td colspan="<?= $colspan ?>">
                <?php if (!$isTaskmaster):
                    $owner = $database->getVillageField($e['from'],'owner');
                    $uname = $database->getUserField($owner,'username',0);
                ?>
                    <a href="spieler.php?uid=<?= $owner ?>"><?= LANG=='es' ? TROOPS.' '.$uname : $uname.' '.TROOPS ?></a>
                <?php else: ?><a><?= VILLAGE_OF_THE_ELDERS_TROOPS ?></a><?php endif; ?>
            </td>
        </tr></thead>
        <tbody class="units">
            <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++): ?><td><img src="img/x.gif" class="unit u<?= $i ?>" title="<?= $technology->getUnitName($i) ?>"></td><?php endfor; ?><?php if($e['hero']): ?><td><img src="img/x.gif" class="unit uhero" title="<?php echo U0; ?>"></td><?php endif; ?></tr>
            <tr><th><?= TROOPS ?></th><?php for($i=$start;$i<$start+10;$i++): ?><td class="<?= $e['u'.$i]==0?'none':'' ?>"><?= $e['u'.$i] ?></td><?php endfor; ?><?php if($e['hero']): ?><td><?= $e['hero'] ?></td><?php endif; ?></tr>
        </tbody>
        <tbody class="infos"><tr><th><?= UPKEEP ?></th><td colspan="<?= $colspan ?>">
            <div class="sup"><?= $technology->getUpkeep($e,$tribe) ?><img class="r4" src="img/x.gif"> <?= PER_HR ?></div>
            <div class="sback"><?php if(!$isTaskmaster): ?><a href="a2b.php?w=<?= $e['id'] ?>"><?= SEND_BACK ?></a><?php if ($owner == $session->uid && !empty($e['hero'])): ?> | <a href="build.php?id=<?= $id ?>&amp;setherohome=<?= $e['id'] ?>" style="color:#0284c7; font-weight:bold;" onclick="return confirm('Pindahkan desa asal Hero ke desa ini?');">📍 Jadikan Desa Asal Hero</a><?php endif; ?><?php elseif (defined('NEW_FUNCTIONS_HERO_T4') && NEW_FUNCTIONS_HERO_T4): ?><a href="a2b.php?releaseanimals=<?= $e['id'] ?>" onclick="return confirm('<?= defined('HERO_RELEASE_CONFIRM') ? HERO_RELEASE_CONFIRM : 'Release these animals? They will be gone for good.' ?>');"><?= defined('HERO_RELEASE_ANIMALS') ? HERO_RELEASE_ANIMALS : 'Release' ?></a><?php else: ?><span class="none"><b><?= SEND_BACK ?></b></span><?php endif; ?></div>
        </td></tr></tbody>
    </table>
    <?php endforeach; ?>

    <!-- MERCENARY GARRISON -->
    <?php
    require_once __DIR__ . '/../../GameEngine/Mercenary.php';
    $mercGarrison = Mercenary::getGarrison($village->wid);
    if ($mercGarrison['total_troops'] > 0):
    ?>
    <table class="troop_details" cellpadding="1" cellspacing="1">
        <thead><tr>
            <td class="role"><a href="build.php?gid=17&amp;t=6" style="color: #2e6b38; font-weight: bold;">⚔️ Enclave Bayaran</a></td>
            <td colspan="4"><a href="build.php?gid=17&amp;t=6">Garnisun Tentara Bayaran Netral</a></td>
        </tr></thead>
        <tbody class="units">
            <tr>
                <th>&nbsp;</th>
                <td title="Garda Zirah Besi" style="font-size:11px;">🛡️ Garda Zirah</td>
                <td title="Pemanah Tembok Aegis" style="font-size:11px;">🏹 Pemanah Aegis</td>
                <td title="Ksatria Perisai Templar" style="font-size:11px;">🐎 Ksatria Templar</td>
                <td title="Penjaga Gerbang Praetorian" style="font-size:11px;">🏛️ Praetorian Elit</td>
            </tr>
            <tr>
                <th><?= TROOPS ?></th>
                <td class="<?= $mercGarrison['m1']==0?'none':'' ?>"><?= number_format($mercGarrison['m1']) ?></td>
                <td class="<?= $mercGarrison['m2']==0?'none':'' ?>"><?= number_format($mercGarrison['m2']) ?></td>
                <td class="<?= $mercGarrison['m3']==0?'none':'' ?>"><?= number_format($mercGarrison['m3']) ?></td>
                <td class="<?= $mercGarrison['m4']==0?'none':'' ?>"><?= number_format($mercGarrison['m4']) ?></td>
            </tr>
        </tbody>
        <tbody class="infos"><tr><th><?= UPKEEP ?></th><td colspan="4">
            <div class="sup"><?= $mercGarrison['upkeep'] ?><img class="r4" src="img/x.gif"> <?= PER_HR ?></div>
            <div class="sback"><a href="build.php?gid=17&amp;t=6" style="color:#2e6b38; font-weight:bold;">Kelola Kontrak</a></div>
        </td></tr></tbody>
    </table>
    <?php endif; ?>

    <!-- SANGGAR PARANORMAL ASTRAL SPIRITS -->
    <?php
    require_once __DIR__ . '/../../GameEngine/Paranormal.php';
    $spirits = Paranormal::getVillageSpirits($village->wid);
    $hasSanggar = (isset($building) && method_exists($building, 'getTypeLevel') && $building->getTypeLevel(51) > 0);
    $pocongCount = (int)($spirits['pocong'] ?? 0);
    $kuntiCount = (int)($spirits['kuntilanak'] ?? 0);
    $genderuwoCount = (int)($spirits['genderuwo'] ?? 0);
    $tuyulActive = !empty($spirits['tuyul_until']) && (int)$spirits['tuyul_until'] > time();
    $hasSpirits = ($pocongCount > 0 || $kuntiCount > 0 || $genderuwoCount > 0 || $tuyulActive);

    if ($hasSpirits || $hasSanggar):
        $totDef = Paranormal::getVillageDefense($village->wid);
    ?>
    <table class="troop_details" cellpadding="1" cellspacing="1">
        <thead><tr>
            <td class="role"><a href="build.php?gid=51" style="color: #6366f1; font-weight: bold;">🔮 Sanggar Gaib</a></td>
            <td colspan="4"><a href="build.php?gid=51">Pasukan Gaib Nusantara (Sanggar Paranormal)</a></td>
        </tr></thead>
        <tbody class="units">
            <tr>
                <th>&nbsp;</th>
                <td title="Pocong: 40 Def Inf / 20 Def Kav (1 slot)" style="font-size:11px; text-align:center;">👻 Pocong</td>
                <td title="Kuntilanak: 20 Def Inf / 60 Def Kav (1 slot)" style="font-size:11px; text-align:center;">👰‍♀️ Kuntilanak</td>
                <td title="Genderuwo: 75 Def Inf / 75 Def Kav (2 slot)" style="font-size:11px; text-align:center;">👹 Genderuwo</td>
                <td title="Tuyul: +15% Jarahan Raid" style="font-size:11px; text-align:center;">🕯️ Khodam Tuyul</td>
            </tr>
            <tr>
                <th><?= defined('TROOPS') ? TROOPS : 'Pasukan' ?></th>
                <td class="<?= $pocongCount == 0 ? 'none' : '' ?>" style="text-align:center; font-weight:bold;"><?= number_format($pocongCount) ?></td>
                <td class="<?= $kuntiCount == 0 ? 'none' : '' ?>" style="text-align:center; font-weight:bold;"><?= number_format($kuntiCount) ?></td>
                <td class="<?= $genderuwoCount == 0 ? 'none' : '' ?>" style="text-align:center; font-weight:bold;"><?= number_format($genderuwoCount) ?></td>
                <td class="<?= !$tuyulActive ? 'none' : '' ?>" style="text-align:center; font-weight:bold; color: <?= $tuyulActive ? '#16a34a' : 'inherit' ?>;"><?= $tuyulActive ? 'Aktif (+15%)' : 'Tidak Aktif' ?></td>
            </tr>
        </tbody>
        <tbody class="infos"><tr><th><?= defined('UPKEEP') ? UPKEEP : 'Konsumsi' ?></th><td colspan="4">
            <div class="sup" style="color:#6366f1; font-weight:bold;">
                0 <img class="r4" src="img/x.gif" alt="Gandum"> <?= defined('PER_HR') ? PER_HR : '/jam' ?>
                <span style="font-weight:normal; font-size:10px; color:#64748b; margin-left:6px;">(Pertahanan: 🛡️ <?= number_format($totDef['dp']) ?> Inf / 🐎 <?= number_format($totDef['cdp']) ?> Kav)</span>
            </div>
            <div class="sback"><a href="build.php?gid=51" style="color:#6366f1; font-weight:bold;">Kelola di Sanggar</a></div>
        </td></tr></tbody>
    </table>
    <?php endif; ?>

    <?php
    // split my reinforcements
    $enforcevill = []; $enforceoasis = [];
    foreach ($village->enforcetoyou as $e) {
        $conq = (int)$database->getOasisField($e['vref'],'conqured');
        if ($conq>0) { $e['conqured']=$conq; $enforceoasis[]=$e; } else $enforcevill[]=$e;
    }
    foreach ($village->enforceoasis as $e) $enforceoasis[]=$e;
    ?>

    <!-- TROOPS IN OTHER VILLAGES -->
    <?php if ($enforcevill): ?>
        <h4><?= TROOPS_IN_OTHER_VILLAGE ?></h4>
        <?php foreach ($enforcevill as $e):
            $colspan=10+$e['hero']; $tribe=$database->getUserField($database->getVillageField($e['from'],'owner'),'tribe',0); $start=($tribe-1)*10+1;
        ?>
        <table class="troop_details" cellpadding="1" cellspacing="1">
            <thead><tr>
                <td class="role"><a href="karte.php?d=<?= $e['from'] ?>&c=<?= $generator->getMapCheck($e['from']) ?>"><?= $database->getVillageField($e['from'],'name') ?></a></td>
                <td colspan="<?= $colspan ?>"><a href="karte.php?d=<?= $e['vref'] ?>&c=<?= $generator->getMapCheck($e['vref']) ?>"><?= REINFORCEMENTFOR ?> <?= $database->getVillageField($e['vref'],'name') ?></a></td>
            </tr></thead>
            <tbody class="units">
                <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++): ?><td><img src="img/x.gif" class="unit u<?= $i ?>" title="<?= $technology->getUnitName($i) ?>"></td><?php endfor; ?><?php if($e['hero']): ?><td><img src="img/x.gif" class="unit uhero"></td><?php endif; ?></tr>
                <tr><th><?= TROOPS ?></th><?php for($i=$start;$i<$start+10;$i++): ?><td class="<?= $e['u'.$i]==0?'none':'' ?>"><?= $e['u'.$i] ?></td><?php endfor; ?><?php if($e['hero']): ?><td><?= $e['hero'] ?></td><?php endif; ?></tr>
            </tbody>
            <tbody class="infos"><tr><th><?= UPKEEP ?></th><td colspan="<?= $colspan ?>">
                <div class="sup"><?= $technology->getUpkeep($e,$tribe) ?><img class="r4" src="img/x.gif"> <?= PER_HR ?></div>
                <div class="sback"><a href="a2b.php?r=<?= $e['id'] ?>"><?= SEND_BACK ?></a></div>
            </td></tr></tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- TROOPS IN OASIS -->
    <?php if ($enforceoasis): ?>
        <h4><?= TROOPS_IN_OASIS ?></h4>
        <?php foreach ($enforceoasis as $e):
            $colspan=10+$e['hero']; $owner=$database->getVillageField($e['from'],'owner'); $tribe=$database->getUserField($owner,'tribe',0); $start=($tribe-1)*10+1;
        ?>
        <table class="troop_details" cellpadding="1" cellspacing="1">
            <thead><tr>
                <td class="role"><a href="karte.php?d=<?= $e['vref'] ?>&c=<?= $generator->getMapCheck($e['vref']) ?>"><?= $database->getVillageField($e['conqured'],'name') ?></a></td>
                <td colspan="<?= $colspan ?>">
                    <a href="spieler.php?uid=<?= $owner ?>"><?= $database->getUserField($owner,'username',0) ?> <?= TROOPS ?></a> <?= FROM ?> <a href="karte.php?d=<?= $e['from'] ?>&c=<?= $generator->getMapCheck($e['from']) ?>"><?= $database->getVillageField($e['from'],'name') ?></a>
                </td>
            </tr></thead>
            <tbody class="units">
                <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++): ?><td><img src="img/x.gif" class="unit u<?= $i ?>"></td><?php endfor; ?><?php if($e['hero']): ?><td><img src="img/x.gif" class="unit uhero"></td><?php endif; ?></tr>
                <tr><th><?= TROOPS ?></th><?php for($i=$start;$i<$start+10;$i++): ?><td class="<?= $e['u'.$i]==0?'none':'' ?>"><?= $e['u'.$i] ?></td><?php endfor; ?><?php if($e['hero']): ?><td><?= $e['hero'] ?></td><?php endif; ?></tr>
            </tbody>
            <tbody class="infos"><tr><th><?= UPKEEP ?></th><td colspan="<?= $colspan ?>">
                <div class="sup"><?= $technology->getUpkeep($e,$tribe) ?><img class="r4" src="img/x.gif"> <?= PER_HR ?></div>
                <div class="sback"><a href="a2b.php?r=<?= $e['id'] ?>"><?= SEND_BACK ?></a></div>
            </td></tr></tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- PRISONERS (my troops captured in another village) -->
    <?php $p3 = $database->getPrisoners3($village->wid); if($p3): ?>
        <h4><?= PRISONERS ?></h4>
        <?php foreach($p3 as $p):
            $colspan=10+(!empty($p['t11'])?1:0);
            $owner = $database->getVillageField($p['from'],'owner');
            $tribe = max(1, (int)$database->getUserField($owner,'tribe',0));
            $start = ($tribe-1)*10+1;
        ?>
        <table class="troop_details" cellpadding="1" cellspacing="1">
            <thead><tr>
                <td class="role"><a href="karte.php?d=<?= $p['wref'] ?>&c=<?= $generator->getMapCheck($p['wref']) ?>"><?= $database->getVillageField($p['wref'],'name') ?></a></td>
                <td colspan="<?= $colspan ?>"><a href="karte.php?d=<?= $p['wref'] ?>"><?= PRISONERSIN ?> <?= $database->getVillageField($p['wref'],'name') ?></a></td>
            </tr></thead>
            <tbody class="units">
                <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++): ?><td><img src="img/x.gif" class="unit u<?= $i ?>" title="<?= $technology->getUnitName($i) ?>"></td><?php endfor; ?><?php if(!empty($p['t11'])): ?><td><img src="img/x.gif" class="unit uhero" title="<?= $technology->getUnitName('hero') ?>"></td><?php endif; ?></tr>
                <tr><th><?= TROOPS ?></th><?php for($i=1;$i<=10;$i++): ?><td class="<?= empty($p['t'.$i])?'none':'' ?>"><?= (int)($p['t'.$i] ?? 0) ?></td><?php endfor; ?><?php if(!empty($p['t11'])): ?><td><?= (int)$p['t11'] ?></td><?php endif; ?></tr>
            </tbody>
            <tbody class="infos"><tr><th><?= UPKEEP ?></th><td colspan="<?= $colspan ?>">
                <div class="sup"><?= $technology->getUpkeep($p,$tribe,0,1) ?><img class="r4" src="img/x.gif"> <?= PER_HR ?></div>
                <div class="sback"><a href="a2b.php?delprisoners=<?= (int)$p['id'] ?>"><?= KILL ?></a></div>
            </td></tr></tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- PRISONERS (enemy troops trapped in my village) -->
    <?php $p = $database->getPrisoners($village->wid); if($p): ?>
        <?php if(!$p3): ?><h4><?= PRISONERS ?></h4><?php endif; ?>
        <?php foreach($p as $pr):
            $colspan=10+(!empty($pr['t11'])?1:0);
            $owner = $database->getVillageField($pr['from'],'owner');
            $tribe = max(1, (int)$database->getUserField($owner,'tribe',0));
            $start = ($tribe-1)*10+1;
        ?>
        <table class="troop_details" cellpadding="1" cellspacing="1">
            <thead><tr>
                <td class="role"><a href="karte.php?d=<?= $pr['from'] ?>&c=<?= $generator->getMapCheck($pr['from']) ?>"><?= $database->getVillageField($pr['from'],'name') ?></a></td>
                <td colspan="<?= $colspan ?>"><a href="karte.php?d=<?= $pr['from'] ?>"><?= PRISONERSFROM ?> <?= $database->getVillageField($pr['from'],'name') ?></a></td>
            </tr></thead>
            <tbody class="units">
                <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++): ?><td><img src="img/x.gif" class="unit u<?= $i ?>" title="<?= $technology->getUnitName($i) ?>"></td><?php endfor; ?><?php if(!empty($pr['t11'])): ?><td><img src="img/x.gif" class="unit uhero" title="<?= $technology->getUnitName('hero') ?>"></td><?php endif; ?></tr>
                <tr><th><?= TROOPS ?></th><?php for($i=1;$i<=10;$i++): ?><td class="<?= empty($pr['t'.$i])?'none':'' ?>"><?= (int)($pr['t'.$i] ?? 0) ?></td><?php endfor; ?><?php if(!empty($pr['t11'])): ?><td><?= (int)$pr['t11'] ?></td><?php endif; ?></tr>
            </tbody>
            <tbody class="infos"><tr><td colspan="<?= $colspan+1 ?>">
                <div class="sup"><img class="r6" src="img/x.gif"></div>
                <div class="sback"><a href="a2b.php?delprisoners=<?= (int)$pr['id'] ?>"><?= SEND_BACK ?></a></div>
            </td></tr></tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $out = $database->getMovement(3,$village->wid,0);
    $set = $database->getMovement(5,$village->wid,0);
    $cnt = count($set);
    foreach($out as $u) if($u['vref']==$village->wid) $cnt++;
    // T4 hero port: adventure legs count as troops on their way too.
    if (defined('NEW_FUNCTIONS_HERO_T4') && NEW_FUNCTIONS_HERO_T4) {
        $cnt += count($database->getMovement(20,$village->wid,0));
        $cnt += count($database->getMovement(21,$village->wid,1));
    }
    ?>
    <?php if($cnt>=1): ?>
        <h4><?= TROOPS_ON_THEIR_WAY ?></h4>
        <?php include '16_walking.tpl'; ?>
    <?php endif; ?>

<?php else: ?>
    <b><?= RALLYPOINT_COMMENCE ?></b><br>
<?php endif; ?>

<?php include 'upgrade.tpl'; ?>
</div>