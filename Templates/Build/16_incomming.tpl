<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : RALLY POINT INCOMMING TROOPS                              ##
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

include_once 'GameEngine/Data/unitdata.php';

$units = $database->getMovement(34, $village->wid, 1);
$artifactsSum = $database->getArtifactsSumByKind($session->uid, $village->wid, 3);
// Rally-point level and Scout presence reconnaissance network
$rpLevel = (int) $database->getFieldLevelInVillage($village->wid, 16);

// Hitung total unit pengintai (Scout) pembela yang bersiaga di desa (pasukan sendiri + bala bantuan)
$defScouts = 0;
$scoutUnitIDs = [4, 14, 23, 44, 52, 64, 74, 82];

$defOwnUnits = isset($village->unitarray) && is_array($village->unitarray)
    ? $village->unitarray
    : $database->getUnit($village->wid, false);

if (is_array($defOwnUnits)) {
    foreach ($scoutUnitIDs as $sid) {
        if (!empty($defOwnUnits['u' . $sid])) {
            $defScouts += (int) $defOwnUnits['u' . $sid];
        }
    }
}

$defEnfUnits = isset($village->enforcetome) && is_array($village->enforcetome)
    ? $village->enforcetome
    : $database->getEnforceVillage($village->wid, 0);

if (is_array($defEnfUnits)) {
    foreach ($defEnfUnits as $enf) {
        foreach ($scoutUnitIDs as $sid) {
            if (!empty($enf['u' . $sid])) {
                $defScouts += (int) $enf['u' . $sid];
            }
        }
    }
}
?>

<style>
.atk_marker{position:relative;float:right;width:14px;height:14px;border-radius:50%;border:1px solid #666;margin:0 0 0 6px;cursor:pointer;box-shadow:inset 0 0 0 2px rgba(255,255,255,.55)}
.atk_marker.marker0{background:#dddddd}
.atk_marker.marker1{background:#3cb043}
.atk_marker.marker2{background:#f2c200}
.atk_marker.marker3{background:#e23b3b}
.atk_marker:hover{box-shadow:inset 0 0 0 2px rgba(255,255,255,.55),0 0 0 1px #333}

/* Military Intelligence Reconnaissance Card */
.tz-intel-box {
    margin: 10px 0 6px 0;
    padding: 8px 12px;
    background: #fbfbf9;
    border: 1px solid #d5cfc2;
    border-left: 4px solid #3f6eb0;
    border-radius: 4px;
    font-size: 11px;
    color: #333;
}
.tz-intel-box.siege-alert {
    border-left-color: #c23a2b;
    background: #fff9f8;
}
.tz-intel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e8e3d8;
    padding-bottom: 4px;
    margin-bottom: 6px;
}
.tz-intel-title {
    font-weight: bold;
    color: #4a453e;
    font-size: 11.5px;
}
.tz-intel-status {
    font-size: 11px;
}
.tz-intel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 4px 12px;
}
.tz-intel-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.tz-intel-label {
    color: #777;
    font-weight: 500;
}
.tz-intel-val {
    font-weight: bold;
}
.tz-jammed {
    color: #c23a2b;
    font-style: italic;
    font-weight: normal;
}
.tz-active {
    color: #2f852f;
    font-style: italic;
    font-weight: normal;
}
.tz-intel-footer {
    margin-top: 6px;
    padding-top: 4px;
    border-top: 1px dashed #e8e3d8;
    font-size: 10.5px;
    color: #555;
}
.tz-intel-footer.tz-locked {
    color: #888;
}
</style>
<script type="text/javascript">
function cycleMarker(moveid, el){
    var cur = parseInt(el.getAttribute('data-marker') || '0', 10);
    var next = (cur + 1) % 4;
    var body = 'moveid=' + encodeURIComponent(moveid) + '&marker=' + encodeURIComponent(next);
    fetch('ajax.php?f=marker', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    }).then(function(r){ return r.json(); }).then(function(d){
        if (d && d.ok) {
            el.setAttribute('data-marker', next);
            el.className = 'atk_marker marker' + next;
        }
    });
}
</script>

<?php foreach ($units as $u):
    $session->timer++;
    $sort = (int)$u['sort_type'];
    $atk = (int)$u['attack_type'];

    if ($sort === 3 && $atk!= 1):
        $action = ($atk == 2? REINFORCEMENTFOR : ($atk == 3? ATTACK_ON : RAID_ON));
        $from = (int)$u['from'];
        $isElders = ($from === 0);
        $owner = $isElders? 0 : $database->getVillageField($from, 'owner');
        $isMine = ($owner == $session->uid);
        $isEnemyAttack = ($atk == 3 || $atk == 4) && !$isElders && !$isMine;

        $colspan = ($u['t11'] > 0)? 11 : 10;
        $tribe = $isElders? 4 : $database->getUserField($owner, 'tribe', 0);
        $start = ($tribe - 1) * 10 + 1;
        $end = $tribe * 10;
        $dt = $generator->procMtime($u['endtime']);

        $mtimes = [];
        if (!$isElders) {
            $fromInfo = $database->getMInfo($from);
            $toInfo = $database->getMInfo($u['to']);
            $dist = $database->getDistance($fromInfo['x'], $fromInfo['y'], $toInfo['x'], $toInfo['y']);
            for ($i = $start; $i <= $end; $i++) {
                $spd = isset($GLOBALS['u'.$i]['speed']) ? $GLOBALS['u'.$i]['speed'] : 0;
                $mtimes[$i] = $spd > 0 ? $generator->getTimeFormat((int) round(($dist / $spd) * 3600 / INCREASE_SPEED)) : '-';
            }
        }

        // Kalkulasi Intelijen Militer
        $atkTotalTroops = 0;
        $atkScoutCount  = 0;
        $slowestSpeed   = 999;
        $hasSiege       = false;
        $hasCavalry     = false;
        $hasInfantry    = false;

        if ($isEnemyAttack) {
            for ($i = 1; $i <= 10; $i++) {
                $tval = isset($u['t' . $i]) ? (int) $u['t' . $i] : 0;
                if ($tval > 0) {
                    $atkTotalTroops += $tval;
                    $actualUnitID = $start + $i - 1;
                    if (in_array($actualUnitID, $scoutUnitIDs)) {
                        $atkScoutCount += $tval;
                    }
                    $spd = isset($GLOBALS['u' . $actualUnitID]['speed']) ? (int) $GLOBALS['u' . $actualUnitID]['speed'] : 0;
                    if ($spd > 0 && $spd < $slowestSpeed) {
                        $slowestSpeed = $spd;
                    }
                    if (isset($unitsbytype['siege']) && in_array($actualUnitID, $unitsbytype['siege'])) {
                        $hasSiege = true;
                    } elseif (isset($unitsbytype['cavalry']) && in_array($actualUnitID, $unitsbytype['cavalry'])) {
                        $hasCavalry = true;
                    } elseif (isset($unitsbytype['infantry']) && in_array($actualUnitID, $unitsbytype['infantry'])) {
                        $hasInfantry = true;
                    }
                }
            }
            if (!empty($u['t11'])) {
                $atkTotalTroops += (int) $u['t11'];
            }
        }

        // Duel Intelijen: jika scout musuh >= scout defender, efek patroli defender tersaring
        $scoutJammed = ($atkScoutCount > 0 && $defScouts > 0 && $atkScoutCount >= $defScouts);
        $effectiveScouts = ($defScouts > 0 && !$scoutJammed) ? $defScouts : 0;

        $hasEyesight = ($artifactsSum['totals'] > 0);

        // Lapisan Fitur Intelijen yang terbuka:
        $canSeeSpeed  = ($rpLevel >= 10 || $effectiveScouts >= 20 || $hasEyesight);
        $canSeeScale  = ($rpLevel >= 15 || $effectiveScouts >= 50 || $hasEyesight);
        $canSeeRoster = ($rpLevel >= 20 || $effectiveScouts >= 100 || $hasEyesight);

        // Label Kecepatan:
        $isSiegeAlert = false;
        if ($hasSiege || $slowestSpeed <= 4) {
            $speedLabel = '⚠️ Pengepungan (~' . ($slowestSpeed < 999 ? $slowestSpeed : 3) . ' field/jam: Ketapel/Ram!)';
            $isSiegeAlert = true;
        } elseif ($slowestSpeed >= 13 && !$hasInfantry) {
            $speedLabel = '⚡ Laju Cepat (~' . $slowestSpeed . ' field/jam: Kavaleri murni)';
        } else {
            $speedLabel = '🚶 Laju Standar (~' . ($slowestSpeed < 999 ? $slowestSpeed : 7) . ' field/jam: Infanteri)';
        }

        // Label Skala Pasukan:
        if ($atkTotalTroops < 100) {
            $scaleLabel = '🟢 Skirmish / Raid Kecil (1 - 99 prajurit)';
        } elseif ($atkTotalTroops < 500) {
            $scaleLabel = '🟡 Regiment (100 - 499 prajurit)';
        } elseif ($atkTotalTroops < 2000) {
            $scaleLabel = '🟠 Battalion (500 - 1.999 prajurit)';
        } elseif ($atkTotalTroops < 10000) {
            $scaleLabel = '🔴 Legion / Big Army (2.000 - 9.999 prajurit)';
        } else {
            $scaleLabel = '🟣 Imperial Hammer (10.000+ prajurit)';
        }
?>

<?php if ($isEnemyAttack): ?>
<div class="tz-intel-box <?= $isSiegeAlert ? 'siege-alert' : '' ?>">
    <div class="tz-intel-header">
        <span class="tz-intel-title">🛡️ INTELIJEN MILITER & PENGAWASAN</span>
        <span class="tz-intel-status">
            <?php if ($canSeeRoster): ?>
                <b style="color:#2f852f;">● MAKSIMAL (Unit Terbuka)</b>
            <?php elseif ($canSeeScale): ?>
                <b style="color:#c87f0a;">● LANJUT (Skala Terdeteksi)</b>
            <?php elseif ($canSeeSpeed): ?>
                <b style="color:#2f6fb0;">● TAKTIS (Laju Terdeteksi)</b>
            <?php else: ?>
                <b style="color:#777;">○ DASAR</b>
            <?php endif; ?>
        </span>
    </div>
    <div class="tz-intel-grid">
        <div class="tz-intel-item">
            <span class="tz-intel-label">Titik Temu:</span>
            <span class="tz-intel-val">Level <?= $rpLevel ?></span>
        </div>
        <div class="tz-intel-item">
            <span class="tz-intel-label">Patroli Pengintai:</span>
            <span class="tz-intel-val">
                <?= number_format($defScouts) ?> Scout
                <?php if ($defScouts == 0): ?>
                    <i class="tz-jammed">(Kosong)</i>
                <?php elseif ($scoutJammed): ?>
                    <i class="tz-jammed">(Tersaring pengintai musuh)</i>
                <?php else: ?>
                    <i class="tz-active">(Bersiaga)</i>
                <?php endif; ?>
            </span>
        </div>
        <div class="tz-intel-item">
            <span class="tz-intel-label">Ritme Gerak:</span>
            <span class="tz-intel-val">
                <?= $canSeeSpeed ? $speedLabel : '<i>(Perlu Lvl 10 / 20 Scout)</i>' ?>
            </span>
        </div>
        <div class="tz-intel-item">
            <span class="tz-intel-label">Estimasi Skala:</span>
            <span class="tz-intel-val">
                <?= $canSeeScale ? $scaleLabel : '<i>(Perlu Lvl 15 / 50 Scout)</i>' ?>
            </span>
        </div>
    </div>
    <div class="tz-intel-footer <?= $canSeeRoster ? '' : 'tz-locked' ?>">
        <?php if ($canSeeRoster): ?>
            ✓ <b>Komposisi Unit Terdeteksi:</b> Seluruh icon jenis pasukan yang dibawa musuh ditandai pada tabel di bawah (angka pasti tetap dirahasiakan).
        <?php else: ?>
            ℹ️ <i>Tingkatkan Titik Temu ke Level 20 atau siagakan 100 Scout di desa untuk membuka identifikasi jenis unit.</i>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<table class="troop_details" cellpadding="1" cellspacing="1">
    <thead><tr>
        <td class="role">
            <?php if (!$isElders):?>
                <a href="karte.php?d=<?= $from?>&c=<?= $generator->getMapCheck($from)?>"><?= $database->getVillageField($from,'name')?></a>
            <?php else:?><a><?php echo VILLAGE_OF_THE_ELDERS; ?></a><?php endif;?>
        </td>
        <td colspan="<?= $colspan?>">
            <?php if (($atk == 3 || $atk == 4) && !$isElders): $marker = (int)($u['marker'] ?? 0); $mid = (int)$u['moveid'];?>
            <span class="atk_marker marker<?= $marker?>" data-marker="<?= $marker?>" title="<?= MARK_ATTACK?>" onclick="cycleMarker(<?= $mid?>,this)"></span>
            <?php endif;?>
            <?php if (!$isElders):?>
                <a href="karte.php?d=<?= $u['to']?>&c=<?= $generator->getMapCheck($u['to'])?>"><?= $action?> <?= $database->getVillageField($u['to'],'name')?></a>
            <?php else:?><a><?= VILLAGE_OF_THE_ELDERS_TROOPS?></a><?php endif;?>
        </td>
    </tr></thead>
    <tbody class="units">
        <tr><th>&nbsp;</th>
            <?php for ($i=$start;$i<=$end;$i++):?><td><img src="img/x.gif" class="unit u<?= $i?>" title="<?= $technology->getUnitName($i)?><?= isset($mtimes[$i])? ': '.$mtimes[$i] : ''?>"></td><?php endfor;?>
            <?php if ($u['t11']):?><td><img src="img/x.gif" class="unit uhero" title="<?php echo U0; ?>"></td><?php endif;?>
        </tr>
        <tr><th><?= TROOPS?></th>
            <?php for ($i=1;$i<=$colspan;$i++):
                $val = isset($u['t'.$i])? $u['t'.$i] : 0;
                if ($isElders) { echo '<td class="none">?</td>'; continue; }
                if ($atk == 2) {
                    if (!$isMine) echo '<td class="none">?</td>';
                    else echo '<td class="'.($val==0?'none':'').'">'.($val==0?'0':$val).'</td>';
                } else {
                    if ($val == 0) {
                        if ($canSeeRoster) {
                            echo '<td class="none">0</td>';
                        } else {
                            echo '<td class="none">' . ($hasEyesight ? '0' : '?') . '</td>';
                        }
                    } else {
                        if ($hasEyesight) {
                            echo '<td><b>' . $val . '</b></td>';
                        } elseif ($canSeeRoster) {
                            echo '<td><b style="color:#c23a2b;font-size:12px;" title="Unit terdeteksi ikut menyerang!">?</b></td>';
                        } elseif ($rpLevel > 0 && $val < $rpLevel) {
                            echo '<td><b style="color:#000;">?</b></td>';
                        } else {
                            echo '<td class="none">?</td>';
                        }
                    }
                }
            endfor;?>
        </tr>
    </tbody>
    <tbody class="infos"><tr>
        <th><?= ARRIVAL?></th>
        <td colspan="<?= $colspan?>">
            <div class="in small"><span id="timer<?= $session->timer?>"><?= $generator->getTimeFormat($u['endtime']-time())?></span> h</div>
            <div class="at small"><?= $dt[0]!='today'? ON.' '.$dt[0].' ' : ''?><?= AT?> <?= $dt[1]?> <?= HRS?></div>
        </td>
    </tr></tbody>
</table>

<?php elseif ($sort === 4):
    $fromInfo = $database->isVillageOases($u['from'])? $database->getOMInfo($u['from']) : $database->getMInfo($u['from']);
    $colspan = $u['t11']? 11 : 10;
    $tribe = $session->tribe; $start=($tribe-1)*10+1;
    $totalRes = $u['wood']+$u['clay']+$u['iron']+$u['crop'];
    $carry = 0; for($i=0;$i<10;$i++) { $t = isset($u['t'.($i+1)])? $u['t'.($i+1)] : 0; $carry += $t * ${'u'.($start+$i)}['cap']; }
    $dt = $generator->procMtime($u['endtime']);
?>
<table class="troop_details" cellpadding="1" cellspacing="1">
    <thead><tr>
        <td class="role"><a href="karte.php?d=<?= $village->wid?>&c=<?= $generator->getMapCheck($village->wid)?>"><?= $village->vname?></a></td>
        <td colspan="<?= $colspan?>"><a href="karte.php?d=<?= $fromInfo['wref']?>&c=<?= $generator->getMapCheck($fromInfo['wref'])?>"><?= RETURNFROM?> <?= $fromInfo['name']?></a></td>
    </tr></thead>
    <tbody class="units">
        <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++):?><td><img src="img/x.gif" class="unit u<?= $i?>"></td><?php endfor;?><?php if($u['t11']):?><td><img src="img/x.gif" class="unit uhero"></td><?php endif;?></tr>
        <tr><th><?= TROOPS?></th><?php for($i=1;$i<($u['t11']?12:11);$i++): $v = isset($u['t'.$i])? $u['t'.$i] : 0;?><td class="<?= $v==0?'none':''?>"><?= $v?></td><?php endfor;?></tr>
    </tbody>
    <?php if ($totalRes>0 && $atk!=1 && $atk!=2):?>
    <tbody class="goods"><tr><th><?= BOUNTY?></th><td colspan="<?= $colspan?>">
        <div class="res"><img class="r1" src="img/x.gif"> <?= $u['wood']?> | <img class="r2" src="img/x.gif"> <?= $u['clay']?> | <img class="r3" src="img/x.gif"> <?= $u['iron']?> | <img class="r4" src="img/x.gif"> <?= $u['crop']?></div>
        <div class="carry"><img class="car" src="img/x.gif"> <?= $totalRes?>/<?= $carry?></div>
    </td></tr></tbody>
    <?php endif;?>
    <tbody class="infos"><tr><th><?= ARRIVAL?></th><td colspan="<?= $colspan?>">
        <div class="in small"><span id="timer<?= $session->timer?>"><?= $generator->getTimeFormat($u['endtime']-time())?></span> h</div>
        <div class="at"><?= $dt[0]!='today'? ON.' '.$dt[0].' ' : ''?><?= AT?> <?= $dt[1]?></div>
    </td></tr></tbody>
</table>
<?php endif; endforeach;?>

<?php foreach ($database->getOasis($village->wid) as $o):
    foreach ($database->getMovement(6,$o['wref'],0) as $m):
        $session->timer++; $owner=$database->getVillageField($m['from'],'owner'); $isMine=($owner==$session->uid);
        $colspan=($m['t11'])?11:10; $tribe=$database->getUserField($owner,'tribe',0); $start=($tribe-1)*10+1; // #267: show hero column for incoming attacks too
        $action=($m['attack_type']==2?REINFORCEMENTFOR:($m['attack_type']==3?ATTACK_ON:RAID_ON)); $dt=$generator->procMtime($m['endtime']);
?>
<table class="troop_details" cellpadding="1" cellspacing="1">
    <thead><tr>
        <td class="role"><a href="karte.php?d=<?= $m['from']?>&c=<?= $generator->getMapCheck($m['from'])?>"><?= $database->getVillageField($m['from'],'name')?></a></td>
        <td colspan="<?= $colspan?>"><a href="karte.php?d=<?= $m['to']?>&c=<?= $generator->getMapCheck($m['to'])?>"><?= $action?> <?= $database->getOMInfo($m['to'])['name']?></a></td>
    </tr></thead>
    <tbody class="units">
        <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++):?><td><img src="img/x.gif" class="unit u<?= $i?>"></td><?php endfor;?><?php if($m['t11']):?><td><img src="img/x.gif" class="unit uhero"></td><?php endif;?></tr>
        <tr><th><?= TROOPS?></th><?php for($i=1;$i<=$colspan;$i++): $v = isset($m['t'.$i])? $m['t'.$i] : 0;
            if($m['attack_type']==2){ echo $isMine? '<td class="'.($v==0?'none':'').'">'.($v?$v:'0').'</td>' : '<td class="none">?</td>'; }
            else { echo $artifactsSum['totals']==0? '<td class="none">?</td>' : '<td class="'.($v==0?'none':'').'">'.($v==0?'0':'?').'</td>'; }
        endfor;?></tr>
    </tbody>
    <tbody class="infos"><tr><th><?= ARRIVAL?></th><td colspan="<?= $colspan?>">
        <div class="in small"><span id="timer<?= $session->timer?>"><?= $generator->getTimeFormat($m['endtime']-time())?></span> h</div>
        <div class="at"><?= $dt[0]!='today'? ON.' '.$dt[0].' ' : ''?><?= AT?> <?= $dt[1]?> <?= HRS?></div>
    </td></tr></tbody>
</table>
<?php endforeach; endforeach;?>

<?php foreach ($database->getMovement(7,$village->wid,1) as $s):
    $session->timer++; $tribe=$session->tribe; $start=($tribe-1)*10+1; $dt=$generator->procMtime($s['endtime']);
?>
<table class="troop_details" cellpadding="1" cellspacing="1">
    <thead><tr>
        <td class="role"><a href="karte.php?d=<?= $village->wid?>&c=<?= $generator->getMapCheck($village->wid)?>"><?= $village->vname?></a></td>
        <td colspan="10"><a href="karte.php?d=<?= $s['to']?>&c=<?= $generator->getMapCheck($s['to'])?>"><?= $database->getMInfo($s['to'])['name']?></a></td>
    </tr></thead>
    <tbody class="units">
        <tr><th>&nbsp;</th><?php for($i=$start;$i<$start+10;$i++):?><td><img src="img/x.gif" class="unit u<?= $i?>"></td><?php endfor;?></tr>
        <tr><th><?= TROOPS?></th><?php for($i=1;$i<=10;$i++): $v=($i==10?3:0);?><td class="<?= $v==0?'none':''?>"><?= $v?></td><?php endfor;?></tr>
    </tbody>
    <tbody class="infos"><tr><th><?= ARRIVAL?></th><td colspan="10">
        <div class="in small"><span id="timer<?= $session->timer?>"><?= $generator->getTimeFormat($s['endtime']-time())?></span> h</div>
        <div class="at"><?= $dt[0]!='today'? ON.' '.$dt[0].' ' : ''?><?= AT?> <?= $dt[1]?></div>
    </td></tr></tbody>
</table>
<?php endforeach;?>