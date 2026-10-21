<?php

#################################################################################
##                -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-               ##
## --------------------------------------------------------------------------- ##
##  Filename       : 1.tpl                                                     ##
##  Type           : Battle Report - Attack / Raid                             ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  URLs:          : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

$dataarray = array_map('tz_expand_report', explode(",", $message->readingNotice['data']));

// ======================== SPY REPORT DETECTION ========================
$spyData = null;
$spyPic = null;
$spyIndex = null;
foreach ($dataarray as $idx => $val) {
    if (is_string($val) && (
        strpos($val, '<div class="res">') !== false ||
        strpos($val, 'class="r1"') !== false ||
        strpos($val, 'RC_CRANNY_CAPACITY') !== false ||
        strpos($val, 'RC_WALL_LEVEL') !== false ||
        strpos($val, 'RC_RESIDENCE_LEVEL') !== false ||
        strpos($val, 'RC_PALACE_LEVEL') !== false ||
        strpos($val, 'RC_NO_INFO') !== false ||
        (defined('RC_CRANNY_CAPACITY') && strpos($val, constant('RC_CRANNY_CAPACITY')) !== false)
    )) {
        $spyData = $val;
        $spyIndex = $idx;
        if (isset($dataarray[$idx - 1]) && is_numeric($dataarray[$idx - 1])) {
            $spyPic = (int)$dataarray[$idx - 1];
        }
        break;
    }
}

// ======================== BASIC SETTINGS ========================
$heroSentAtt = 0;
$heroDeadAtt = 0;
if ($spyIndex !== null) {
    // In scout reports, spyPic is at ($spyIndex - 1) and spyData is at $spyIndex.
    // The attacker hero (t11) is located immediately after spyData:
    if (isset($dataarray[$spyIndex + 1]) && is_numeric($dataarray[$spyIndex + 1]) && (int)$dataarray[$spyIndex + 1] > 0) {
        $heroSentAtt = (int)$dataarray[$spyIndex + 1];
        $heroDeadAtt = (isset($dataarray[$spyIndex + 2]) && is_numeric($dataarray[$spyIndex + 2])) ? (int)$dataarray[$spyIndex + 2] : 0;
    }
} else {
    if (isset($dataarray[272]) && is_numeric($dataarray[272]) && (int)$dataarray[272] > 0) {
        $heroSentAtt = (int)$dataarray[272];
        $heroDeadAtt = (int)($dataarray[273] ?? 0);
    } elseif (isset($dataarray[270]) && is_numeric($dataarray[270]) && (int)$dataarray[270] > 0) {
        $heroSentAtt = (int)$dataarray[270];
        $heroDeadAtt = (int)($dataarray[271] ?? 0);
    }
}
$hasHero = ($heroSentAtt > 0);
$colspan = $hasHero ? 11 : 10;

// Spy detection
$spy = ($spyData !== null) || (!empty($dataarray[269]) && !empty($dataarray[268]) && empty($dataarray[287]));

// ======================== URL SETUP ========================

// detectare admin REAL (nu variabilă nesigură)
$isAdmin = (isset($session) && isset($session->access) && $session->access >= 8);

if ($isAdmin) {
    $mapUrl = "Admin/admin.php?p=village&did=";
    $playerUrl = "Admin/admin.php?p=player&uid=";
} else {
    $mapUrl = "karte.php?d=";
    $playerUrl = "spieler.php?uid=";
}

// ======================== ATTACKER DATA (CACHED) ========================
$attackerId = $dataarray[0];
$attackerName = $database->getUserField($attackerId, 'username', 0);
$attackerUid  = $database->getUserField($attackerId, 'id', 0);

if ($attackerName != "[?]") {
    $user_url = "<a href=\"".$playerUrl.$attackerUid."\">".$attackerName."</a>";
} else {
    $user_url = "<font color=\"grey\"><b>[?]</b></font>";
}

$fromVillage = $database->getVillageField($dataarray[1], 'name');

if ($fromVillage != "[?]") {
    $from_url = "<a href=\"".$mapUrl.$dataarray[1]."&c=".$generator->getMapCheck($dataarray[1])."\">".$fromVillage."</a>";
} else {
    $from_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// ======================== DEFENDER DATA (CACHED) ========================
$defId = $dataarray[28];
$defName = $database->getUserField($defId, 'username', 0);
$defUid  = $database->getUserField($defId, 'id', 0);

if ($defName != "[?]") {
    $defuser_url = "<a href=\"".$playerUrl.$defUid."\">".$defName."</a>";
} else {
    $defuser_url = "<font color=\"grey\"><b>[?]</b></font>";
}

$defVillageName = $database->getVillageField($dataarray[29], 'name');

if ($database->isVillageOases($dataarray[29])) {
    $deffrom_url = "<a href=\"".$mapUrl.$dataarray[29]."&c=".$generator->getMapCheck($dataarray[29])."\">".$dataarray[30]."</a>";
} elseif ($defVillageName != "[?]") {
    $deffrom_url = "<a href=\"".$mapUrl.$dataarray[29]."&c=".$generator->getMapCheck($dataarray[29])."\">".$defVillageName."</a>";
} else {
    $deffrom_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// ======================== HTML START ========================
?>
<table cellpadding="1" cellspacing="1" id="report_surround">
<thead>
<tr>
    <th><?php echo SUBJECT; ?>:</th>
    <th><?php echo tz_loc_topic($message->readingNotice['topic']); ?></th>
</tr>
<tr>
    <?php $date = $generator->procMtime($message->readingNotice['time']); ?>
    <td class="sent"><?php echo TZ_SENT; ?></td>
    <td><?php echo ON; ?> <span><?php echo $date[0]." ".TZ_AT." ".$date[1]; ?></span> <span><?php echo TZ_HOUR; ?></span></td>
</tr>
</thead>

<tbody>
<tr><td colspan="2" class="empty"></td></tr>
<tr><td colspan="2" class="report_content">

<table cellpadding="1" cellspacing="1" id="attacker">
<thead>
<tr>
    <td class="role"><?php echo ATTACKER; ?></td>
    <td colspan="<?php echo $colspan ?>">
        <?php echo $user_url; ?> <?php echo $from_url ? FROM_THE_VILL.' '.$from_url : ''; ?>
    </td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
$tribe = !empty($dataarray[2]) ? $dataarray[2] : 5;
$start = ($tribe - 1) * 10 + 1;

// UNIT ICONS
for ($i = $start; $i <= ($start + 9); $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

if ($hasHero) {
    echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".RC_HERO."\" alt=\"".RC_HERO."\" /></td>";
}

echo "</tr><tr><th>".TROOPS."</th>";

// TROOPS
for ($i = 3; $i <= 12; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

if ($hasHero) {
    echo "<td>".$heroSentAtt."</td>";
}

// CASUALTIES
echo "<tr><th>".CASUALTIES."</th>";

for ($i = 13; $i <= 22; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

if ($hasHero) {
    $tdclass = ($heroDeadAtt == 0) ? 'class="none"' : '';
    echo "<td $tdclass>".$heroDeadAtt."</td>";
}

// PRISONERS (unchanged logic but safer sum)
// Cast to int: some reports carry non-numeric text in these slots (e.g. the
// "Information" line shifts indices), which made array_sum() warn under PHP 8.
if (!$spy && array_sum(array_map('intval', array_slice($dataarray, 274, 11))) > 0) {
    echo "</tr><tr><th>".PRISONERS."</th>";

    for ($i = 274; $i <= 283; $i++) {
        echo ($dataarray[$i] == 0)
            ? "<td class=\"none\">0</td>"
            : "<td>".$dataarray[$i]."</td>";
    }

    if ($hasHero) {
        $tdclass = ($dataarray[284] == 0) ? 'class="none"' : '';
        echo "<td $tdclass>".$dataarray[284]."</td>";
    }
}
?>
</tr>
</tbody>

<?php
// ======================== SPECIAL ACTIONS ========================
if (!empty($dataarray[262]) && !empty($dataarray[263])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $dataarray[262]; ?>" src="img/x.gif" alt="<?php echo U17; ?>" title="<?php echo U17; ?>" />
<?php echo $dataarray[263]; ?>
</td>
</tr>
</tbody>
<?php }

if (!empty($dataarray[264]) && !empty($dataarray[265])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $dataarray[264]; ?>" src="img/x.gif" alt="<?php echo U18; ?>" title="<?php echo U18; ?>" />
<?php echo $dataarray[265]; ?>
</td>
</tr>
</tbody>
<?php }

if (!empty($dataarray[266]) && !empty($dataarray[267])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $dataarray[266]; ?>" src="img/x.gif" alt="<?php echo U19; ?>" title="<?php echo U19; ?>" />
<?php echo $dataarray[267]; ?>
</td>
</tr>
</tbody>
<?php }

if ($spyData !== null) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<?php if ($spyPic > 0) { ?>
<img class="unit u<?php echo $spyPic; ?>" src="img/x.gif" alt="" />
<?php } ?>
<?php echo $spyData; ?>
</td>
</tr>
</tbody>
<?php } elseif ($spy && !empty($dataarray[269])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<?php echo $dataarray[269]; ?>
</td>
</tr>
</tbody>
<?php }

if (!empty($dataarray[285])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<?php echo $dataarray[285]; ?>
</td>
</tr>
</tbody>
<?php }

if (!empty($dataarray[288]) && !empty($dataarray[289])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $dataarray[288]; ?>" src="img/x.gif" alt="<?php echo U0; ?>" title="<?php echo U0; ?>" />
<?php echo $dataarray[289]; ?>
</td>
</tr>
</tbody>
<?php }

// Bandage healing notice (T4 hero port)
$healedByBandage = 0;
foreach ($dataarray as $entry) {
    if (is_string($entry) && strpos($entry, 'BANDAGE:') === 0) {
        $healedByBandage = (int) substr($entry, 8);
        break;
    }
}
if ($healedByBandage > 0) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<span style="color:#15803d; font-weight:bold;">🩹 Pembalut (Bandage):</span>
Sebanyak <b><?php echo $healedByBandage; ?></b> prajurit berhasil disembuhkan dan pulang ke desa bersama Hero.
</td>
</tr>
</tbody>
<?php }

if (!empty($dataarray[287])) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<?php echo $dataarray[287]; ?>
</td>
</tr>
</tbody>
<?php }
if (empty($dataarray[268]) && empty($dataarray[269])) { ?>
<tbody class="goods">
<tr>
<th><?php echo BOUNTY; ?></th>
<td colspan="<?php echo $colspan; ?>">
<div class="res">
<img class="r1" src="img/x.gif" /><?php echo $dataarray[23]; ?> |
<img class="r2" src="img/x.gif" /><?php echo $dataarray[24]; ?> |
<img class="r3" src="img/x.gif" /><?php echo $dataarray[25]; ?> |
<img class="r4" src="img/x.gif" /><?php echo $dataarray[26]; ?>
</div>
<div class="carry">
<?php echo ($dataarray[23]+$dataarray[24]+$dataarray[25]+$dataarray[26])."/".$dataarray[27]; ?>
</div>
</td>
</tr>
</tbody>
<?php } ?>
</table>

<?php
// ======================== DEFENDER LOOP ========================
$defArray = [1, $dataarray[55], $dataarray[76], $dataarray[97], $dataarray[118], $dataarray[139], $dataarray[160], $dataarray[181], $dataarray[202], $dataarray[223]];
$targetTribe = $dataarray[34];

foreach ($defArray as $index => $value) {

    if ($value == 0) continue;

    $heroIndex = ($index == 0 ? 274 : 244 + ($index - 1));
    $heroDeadIndex = ($index == 0 ? 1 : 9);

    $target = ($index == 0 ? $targetTribe : $index) - 1;
    $start = $target * 10 + 1;
    $troopsStart = $index * 21 + 35;

    // Safety guard: if reinforcement row has 0 troops and 0 heroes, skip to prevent phantom empty reinforcement
    if ($index > 0) {
        $reinfTroopSum = 0;
        for ($k = $troopsStart; $k <= $troopsStart + 9; $k++) {
            $reinfTroopSum += (int)($dataarray[$k] ?? 0);
        }
        $reinfHeroCount = (int)($dataarray[$heroIndex] ?? 0);
        if ($reinfTroopSum === 0 && $reinfHeroCount === 0) {
            continue;
        }
    }
?>
<table cellpadding="1" cellspacing="1" class="defender">
<thead>
<tr>
<td class="role"><?php echo DEFENDER; ?></td>
<td colspan="<?php echo (!empty($dataarray[$heroIndex])) ? 11 : 10; ?>">
<?php echo ($index == 0) ? $defuser_url." ".FROM_THE_VILL." ".$deffrom_url : REINFORCEMENT; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
for ($i = $start; $i <= ($start + 9); $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

if (!empty($dataarray[$heroIndex])) {
    echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" /></td>";
}

echo "</tr><tr><th>".TROOPS."</th>";

for ($i = $troopsStart; $i <= $troopsStart + 9; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

if (!empty($dataarray[$heroIndex])) {
    echo "<td>".$dataarray[$heroIndex]."</td>";
}

echo "<tr><th>".CASUALTIES."</th>";

for ($i = $troopsStart + 10; $i <= $troopsStart + 19; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

// SAFE FIX: avoid undefined variable warning
$tdclass1 = '';

if (!empty($dataarray[$heroIndex])) {
    $tdclass1 = ($dataarray[$heroIndex + $heroDeadIndex] == 0) ? 'class="none"' : '';
    echo "<td $tdclass1>".$dataarray[$heroIndex + $heroDeadIndex]."</td>";
}
?>
</tr>
</tbody>
</table>
<?php } ?>

<?php
// ======================== NUSANTARA REINFORCEMENT ========================
$nusReinfData = null;
foreach ($dataarray as $item) {
    if (is_string($item) && strpos($item, 'NUSREINF:') === 0) {
        $parts = explode(';', substr($item, 9));
        if (count($parts) >= 20) {
            $nusReinfData = [
                'send' => array_slice($parts, 0, 10),
                'dead' => array_slice($parts, 10, 10),
                'hero_send' => (int)($parts[20] ?? 0),
                'hero_dead' => (int)($parts[21] ?? 0)
            ];
        }
        break;
    }
}

if (!empty($nusReinfData)):
?>
<table cellpadding="1" cellspacing="1" class="defender">
<thead>
<tr>
<td class="role"><?php echo DEFENDER; ?></td>
<td colspan="<?php echo (!empty($nusReinfData['hero_send'])) ? 11 : 10; ?>">
<?php echo REINFORCEMENT; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
for ($i = 91; $i <= 100; $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

if (!empty($nusReinfData['hero_send'])) {
    echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" /></td>";
}

echo "</tr><tr><th>".TROOPS."</th>";

for ($i = 0; $i < 10; $i++) {
    $val = $nusReinfData['send'][$i] ?? '0';
    echo ($val === '0' || $val === 0 || $val === '?')
        ? ($val === '?' ? "<td class=\"none\">?</td>" : "<td class=\"none\">0</td>")
        : "<td>".$val."</td>";
}

if (!empty($nusReinfData['hero_send'])) {
    echo "<td>".$nusReinfData['hero_send']."</td>";
}

echo "</tr><tr><th>".CASUALTIES."</th>";

for ($i = 0; $i < 10; $i++) {
    $val = $nusReinfData['dead'][$i] ?? '0';
    echo ($val === '0' || $val === 0 || $val === '?')
        ? ($val === '?' ? "<td class=\"none\">?</td>" : "<td class=\"none\">0</td>")
        : "<td>".$val."</td>";
}

if (!empty($nusReinfData['hero_send'])) {
    $tdclass1 = ($nusReinfData['hero_dead'] == 0) ? 'class="none"' : '';
    echo "<td $tdclass1>".$nusReinfData['hero_dead']."</td>";
}
?>
</tr>
</tbody>
</table>
<?php endif; ?>

<?php
// ======================== DEFENDER HERO CARD ========================
$defHeroData = null;
foreach ($dataarray as $item) {
    if (is_string($item) && strpos($item, 'DEFHERO:') === 0) {
        $parts = explode(';', substr($item, 8));
        if (count($parts) >= 9) {
            $defHeroData = [
                'present'   => (int)$parts[0],
                'name'      => $parts[1],
                'level'     => (int)$parts[2],
                'unit'      => (int)$parts[3],
                'lost_hp'   => (int)$parts[4],
                'rem_hp'    => (int)$parts[5],
                'xp_gained' => (int)$parts[6],
                'is_dead'   => (int)$parts[7],
                'reason'    => $parts[8]
            ];
        }
        break;
    }
}

// Fallback for older reports without DEFHERO: token in CSV payload
if ($defHeroData === null && !empty($defUid) && !empty($database->dblink)) {
    $hQ = mysqli_query($database->dblink, "SELECT heroid, name, level, unit, health, dead, wref FROM " . TB_PREFIX . "hero WHERE uid = " . (int)$defUid . " LIMIT 1");
    if ($hQ && $hRow = mysqli_fetch_assoc($hQ)) {
        $wasDefending = !empty($dataarray[272]);
        $wasDead = !empty($dataarray[273]);
        if ($wasDefending) {
            $defHeroData = [
                'present'   => 1,
                'name'      => $hRow['name'],
                'level'     => (int)$hRow['level'],
                'unit'      => (int)$hRow['unit'],
                'lost_hp'   => $wasDead ? 100 : 0,
                'rem_hp'    => (int)$hRow['health'],
                'xp_gained' => 0,
                'is_dead'   => $wasDead ? 1 : 0,
                'reason'    => 'fought'
            ];
        } else {
            $defHeroData = [
                'present'   => 0,
                'name'      => $hRow['name'],
                'level'     => (int)$hRow['level'],
                'unit'      => (int)$hRow['unit'],
                'lost_hp'   => 0,
                'rem_hp'    => (int)$hRow['health'],
                'xp_gained' => 0,
                'is_dead'   => (int)$hRow['dead'],
                'reason'    => ((int)$hRow['dead'] == 1) ? 'dead' : 'away'
            ];
        }
    }
}

if (!empty($defHeroData) && ($defHeroData['reason'] ?? '') !== 'none' && $targetTribe != 4):
?>
<table cellpadding="1" cellspacing="1" class="defender" style="margin-top: 10px; border: 1.5px solid <?= ($defHeroData['present'] ? '#10b981' : '#cbd5e1') ?>;">
<thead>
<tr>
<td class="role" style="font-weight: bold; background: <?= ($defHeroData['present'] ? '#d1fae5; color: #065f46; border-color: #a7f3d0;' : '#f1f5f9; color: #475569; border-color: #e2e8f0;') ?>">
    👑 Pahlawan
</td>
<td colspan="<?php echo $colspan; ?>" style="background: <?= ($defHeroData['present'] ? 'linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #065f46; border-color: #a7f3d0;' : '#f8fafc; color: #475569; border-color: #e2e8f0;') ?>; font-weight: bold; padding: 5px 8px;">
    <?php if ($defHeroData['present']): ?>
        ⚔️ Pahlawan Bertahan: <b><?= htmlspecialchars($defHeroData['name']) ?></b> (Level <?= $defHeroData['level'] ?>) <?= FROM_THE_VILL . ' ' . $deffrom_url ?>
    <?php else: ?>
        🛡️ Pahlawan Bertahan <?= FROM_THE_VILL . ' ' . $deffrom_url ?>
    <?php endif; ?>
</td>
</tr>
</thead>
<tbody>
<tr>
<td colspan="<?php echo $colspan + 1; ?>" style="padding: 8px 12px; background: #fff;">
    <?php if ($defHeroData['present']): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <img class="unit uhero" src="img/x.gif" title="Hero" alt="Hero" style="vertical-align: middle;" />
                <span style="font-size: 13px; font-weight: bold; color: #1e293b;"><?= htmlspecialchars($defHeroData['name']) ?></span>
                <span style="font-size: 11px; color: #64748b;">(Level <?= $defHeroData['level'] ?>)</span>
            </div>
            <div style="display: flex; gap: 10px; font-size: 11.5px; align-items: center; flex-wrap: wrap;">
                <span style="background: #fee2e2; color: #991b1b; padding: 2px 7px; border-radius: 4px; font-weight: bold;">
                    💔 Hilang HP: -<?= $defHeroData['lost_hp'] ?>%
                </span>
                <span style="background: <?= ($defHeroData['rem_hp'] > 25 ? '#dcfce7; color: #166534;' : '#fef3c7; color: #92400e;') ?>; padding: 2px 7px; border-radius: 4px; font-weight: bold;">
                    ❤️ Sisa HP: <?= $defHeroData['rem_hp'] ?>%
                </span>
                <span style="background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px; font-weight: bold;">
                    ⭐ +<?= $defHeroData['xp_gained'] ?> EXP
                </span>
                <?php if ($defHeroData['is_dead']): ?>
                    <span style="background: #ef4444; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: bold;">
                        ☠️ Gugur
                    </span>
                <?php else: ?>
                    <span style="background: #10b981; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: bold;">
                        🛡️ Selamat
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="font-size: 11.5px; color: #475569;">
            <?php if ($defHeroData['reason'] === 'dead'): ?>
                ⚠️ <b>Pahlawan tidak ikut bertahan</b>: Pahlawan <b><?= htmlspecialchars($defHeroData['name']) ?></b> (Level <?= $defHeroData['level'] ?>) sedang <b>gugur (HP 0%)</b> saat serangan tiba dan belum dibangkitkan kembali di Rumah Pahlawan.
            <?php elseif ($defHeroData['reason'] === 'away'): ?>
                ℹ️ <b>Pahlawan tidak ikut bertahan</b>: Pahlawan <b><?= htmlspecialchars($defHeroData['name']) ?></b> (Level <?= $defHeroData['level'] ?>) sedang berada di luar desa / tidak ditempatkan di desa ini saat serangan terjadi.
            <?php else: ?>
                ℹ️ <b>Pahlawan tidak ikut bertahan</b>: Pemain belum memiliki pahlawan aktif.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</td>
</tr>
</tbody>
</table>
<?php endif; ?>

<?php
// ======================== MERCENARY GARRISON ========================
$mercData = null;
foreach ($dataarray as $item) {
    if (is_string($item) && strpos($item, 'MERC:') === 0) {
        $parts = explode(';', substr($item, 5));
        if (count($parts) >= 8) {
            $mTroops = [
                1 => (int)$parts[0],
                2 => (int)$parts[1],
                3 => (int)$parts[2],
                4 => (int)$parts[3]
            ];
            $mDead = [
                1 => (int)$parts[4],
                2 => (int)$parts[5],
                3 => (int)$parts[6],
                4 => (int)$parts[7]
            ];
            $totT = array_sum($mTroops);
            if ($totT > 0) {
                $mercData = [
                    'troops' => $mTroops,
                    'dead'   => $mDead,
                    'total_troops' => $totT,
                    'total_dead'   => array_sum($mDead)
                ];
            }
        }
        break;
    }
}

// Fallback for older reports without MERC: tag in CSV payload
if ($mercData === null && !empty($dataarray[29])) {
    $defWid = (int)$dataarray[29];
    if (file_exists(dirname(__DIR__, 2) . '/GameEngine/Mercenary.php')) {
        require_once dirname(__DIR__, 2) . '/GameEngine/Mercenary.php';
        $curGarrison = Mercenary::getGarrison($defWid);
        if (!empty($curGarrison['total_troops']) && $curGarrison['total_troops'] > 0) {
            // Estimate casualties based on defender regular losses ratio
            $defSendTot = 0;
            $defDeadTot = 0;
            for ($k = 35; $k <= 44; $k++) {
                $defSendTot += (int)($dataarray[$k] ?? 0);
            }
            for ($k = 45; $k <= 54; $k++) {
                $defDeadTot += (int)($dataarray[$k] ?? 0);
            }
            $ratio = ($defSendTot > 0) ? min(1.0, max(0.0, $defDeadTot / $defSendTot)) : 0.0;

            $d1 = (int)round($curGarrison['m1'] * $ratio);
            $d2 = (int)round($curGarrison['m2'] * $ratio);
            $d3 = (int)round($curGarrison['m3'] * $ratio);
            $d4 = (int)round($curGarrison['m4'] * $ratio);

            $mTroops = [
                1 => $curGarrison['m1'] + $d1,
                2 => $curGarrison['m2'] + $d2,
                3 => $curGarrison['m3'] + $d3,
                4 => $curGarrison['m4'] + $d4
            ];
            $mDead = [
                1 => $d1,
                2 => $d2,
                3 => $d3,
                4 => $d4
            ];
            $mercData = [
                'troops' => $mTroops,
                'dead'   => $mDead,
                'total_troops' => array_sum($mTroops),
                'total_dead'   => array_sum($mDead)
            ];
        }
    }
}

if (!empty($mercData) && $mercData['total_troops'] > 0):
?>
<table cellpadding="1" cellspacing="1" class="defender" style="margin-top: 10px; border: 1.5px solid #38bdf8;">
<thead>
<tr>
<td class="role" style="font-weight: bold; background: #e0f2fe; color: #0369a1; border-color: #bae6fd;">
    🛡️ <?php echo defined('MERCENARY') ? MERCENARY : 'Mercenary'; ?>
</td>
<td colspan="<?php echo $colspan; ?>" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); color: #0369a1; font-weight: bold; padding: 5px 8px; border-color: #bae6fd;">
    ⚔️ Tentara Bayaran (Mercenary Garrison) <?php echo FROM_THE_VILL . ' ' . $deffrom_url; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">🛡️</span><br/><span style="font-size:9.5px; font-weight:bold; color:#1e293b;">Garda Zirah</span></td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">🏹</span><br/><span style="font-size:9.5px; font-weight:bold; color:#1e293b;">Pemanah Aegis</span></td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">🐎</span><br/><span style="font-size:9.5px; font-weight:bold; color:#1e293b;">Ksatria Templar</span></td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">🏛️</span><br/><span style="font-size:9.5px; font-weight:bold; color:#1e293b;">Praetorian Elit</span></td>
<?php for ($e = 5; $e <= ($colspan - 1); $e++): ?>
<td class="none" style="opacity: 0.25;">?</td>
<?php endfor; ?>
</tr>
<tr>
<th><?php echo TROOPS; ?></th>
<?php for ($m = 1; $m <= 4; $m++): ?>
    <?php echo ($mercData['troops'][$m] == 0) ? '<td class="none">0</td>' : '<td><b>'.$mercData['troops'][$m].'</b></td>'; ?>
<?php endfor; ?>
<?php for ($e = 5; $e <= ($colspan - 1); $e++): ?>
<td class="none">0</td>
<?php endfor; ?>
</tr>
<tr>
<th><?php echo CASUALTIES; ?></th>
<?php for ($m = 1; $m <= 4; $m++): ?>
    <?php echo ($mercData['dead'][$m] == 0) ? '<td class="none">0</td>' : '<td style="color:#dc2626; font-weight:bold;">'.$mercData['dead'][$m].'</td>'; ?>
<?php endfor; ?>
<?php for ($e = 5; $e <= ($colspan - 1); $e++): ?>
<td class="none">0</td>
<?php endfor; ?>
</tr>
</tbody>
</table>
<?php endif; ?>

<?php
// Paranormal Guardian display in battle report
$paranormalData = null;
if (!empty($dataarray[29])) {
    $defWid = (int)$dataarray[29];
    if (file_exists(dirname(__DIR__, 2) . '/GameEngine/Paranormal.php')) {
        require_once dirname(__DIR__, 2) . '/GameEngine/Paranormal.php';
        $pSpirits = Paranormal::getVillageSpirits($defWid);
        $pTotal = (int)$pSpirits['pocong'] + (int)$pSpirits['kuntilanak'] + (int)$pSpirits['genderuwo'];
        if ($pTotal > 0) {
            $defSendTot = 0;
            $defDeadTot = 0;
            for ($k = 35; $k <= 44; $k++) {
                $defSendTot += (int)($dataarray[$k] ?? 0);
            }
            for ($k = 45; $k <= 54; $k++) {
                $defDeadTot += (int)($dataarray[$k] ?? 0);
            }
            $pRatio = ($defSendTot > 0) ? min(1.0, max(0.0, $defDeadTot / $defSendTot)) : 0.0;
            $dP = (int)round((int)$pSpirits['pocong'] * $pRatio);
            $dK = (int)round((int)$pSpirits['kuntilanak'] * $pRatio);
            $dG = (int)round((int)$pSpirits['genderuwo'] * $pRatio);

            $paranormalData = [
                'troops' => [
                    1 => (int)$pSpirits['pocong'] + $dP,
                    2 => (int)$pSpirits['kuntilanak'] + $dK,
                    3 => (int)$pSpirits['genderuwo'] + $dG
                ],
                'dead' => [
                    1 => $dP,
                    2 => $dK,
                    3 => $dG
                ],
                'total_troops' => $pTotal + $dP + $dK + $dG
            ];
        }
    }
}

if (!empty($paranormalData) && $paranormalData['total_troops'] > 0):
?>
<table cellpadding="1" cellspacing="1" class="defender" style="margin-top: 10px; border: 1.5px solid #a855f7;">
<thead>
<tr>
<td class="role" style="font-weight: bold; background: #f3e8ff; color: #7e22ce; border-color: #d8b4fe;">
    👻 Makhluk Gaib
</td>
<td colspan="<?php echo $colspan; ?>" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); color: #7e22ce; font-weight: bold; padding: 5px 8px; border-color: #d8b4fe;">
    🔮 Penjaga Mistis (Paranormal Guardians) <?php echo FROM_THE_VILL . ' ' . $deffrom_url; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">👻</span><br/><span style="font-size:9.5px; font-weight:bold; color:#581c87;">Pocong</span></td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">👰</span><br/><span style="font-size:9.5px; font-weight:bold; color:#581c87;">Kuntilanak</span></td>
<td style="text-align:center; padding: 4px 2px;"><span style="font-size:16px;">👹</span><br/><span style="font-size:9.5px; font-weight:bold; color:#581c87;">Genderuwo</span></td>
<?php for ($e = 4; $e <= ($colspan - 1); $e++): ?>
<td class="none" style="opacity: 0.25;">?</td>
<?php endfor; ?>
</tr>
<tr>
<th><?php echo TROOPS; ?></th>
<?php for ($m = 1; $m <= 3; $m++): ?>
    <?php echo ($paranormalData['troops'][$m] == 0) ? '<td class="none">0</td>' : '<td><b>'.$paranormalData['troops'][$m].'</b></td>'; ?>
<?php endfor; ?>
<?php for ($e = 4; $e <= ($colspan - 1); $e++): ?>
<td class="none">0</td>
<?php endfor; ?>
</tr>
<tr>
<th><?php echo CASUALTIES; ?></th>
<?php for ($m = 1; $m <= 3; $m++): ?>
    <?php echo ($paranormalData['dead'][$m] == 0) ? '<td class="none">0</td>' : '<td style="color:#dc2626; font-weight:bold;">'.$paranormalData['dead'][$m].'</td>'; ?>
<?php endfor; ?>
<?php for ($e = 4; $e <= ($colspan - 1); $e++): ?>
<td class="none">0</td>
<?php endfor; ?>
</tr>
</tbody>
</table>
<?php endif; ?>

</td></tr></tbody></table>