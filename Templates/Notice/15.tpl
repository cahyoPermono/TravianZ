<?php

#################################################################################
##                -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-               ##
## --------------------------------------------------------------------------- ##
##  Filename       : 15.tpl                                                    ##
##  Type           : Battle Report - Reinforcements Attacked                   ##
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

// ======================== CONFIG ========================
$hasHero = (!empty($dataarray[24]) && $dataarray[24] > 0);
$colspan = $hasHero ? 11 : 10;

// ======================== DEFENDER (cached DB calls) ========================
$defId = $dataarray[0];

$defName = $database->getUserField($defId, 'username', 0);
$defUid  = $database->getUserField($defId, 'id', 0);

if ($defName != "[?]") {
    $user_url = "<a href=\"".$playerUrl.$defUid."\">".$defName."</a>";
} else {
    $user_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// ======================== FROM VILLAGE ========================
$fromId = $dataarray[26];
$fromName = $database->getVillageField($fromId, 'name');

if ($fromName != "[?]") {
    $from_url = "<a href=\"".$mapUrl.$fromId."&c=".$generator->getMapCheck($fromId)."\">".$fromName."</a>";
} else {
    $from_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// ======================== ATTACKER DETECTION & FALLBACK ========================
$attackerInfo = null;

// 1. Check for ATTACKER: token in $dataarray
foreach ($dataarray as $chunk) {
    if (is_string($chunk) && strpos($chunk, 'ATTACKER:') === 0) {
        $p = explode(';', substr($chunk, 9));
        if (count($p) >= 23) {
            $attackerInfo = [
                'uid' => (int)$p[0],
                'wref' => (int)$p[1],
                'tribe' => (int)$p[2],
                'units' => array_slice($p, 3, 10),
                'dead' => array_slice($p, 13, 10),
                'hero_sent' => (int)($p[23] ?? 0),
                'hero_dead' => (int)($p[24] ?? 0),
            ];
        }
        break;
    }
}

// 2. Fallback for existing/historical notices (e.g. Benteng Kuno battles in database)
$isAttackerEmpty = empty($attackerInfo) || (array_sum(array_map('intval', $attackerInfo['units'] ?? [])) == 0 && (int)($attackerInfo['hero_sent'] ?? 0) == 0);

if ($isAttackerEmpty && isset($database) && isset($database->dblink)) {
    $targetWref = (int)($dataarray[1] ?? 0);
    $nTime = (int)($message->readingNotice['time'] ?? 0);
    $destNameRaw = !empty($dataarray[2]) ? stripslashes($dataarray[2]) : '';
    $cleanDestName = ltrim($destNameRaw, '? ');
    $isBastion = ($targetWref === 20201 || (defined('Bastion::BASTION_WREF') && $targetWref === (int)constant('Bastion::BASTION_WREF')) || strpos($message->readingNotice['topic'] ?? '', 'Benteng Kuno') !== false || strpos($cleanDestName, 'Benteng Kuno') !== false);

    if ($nTime > 0) {
        $timeMin = $nTime - 60;
        $timeMax = $nTime + 60;
        $wrefCond = $targetWref > 0 ? "toWref = $targetWref" : "(toWref = 20201 OR topic LIKE '%Benteng Kuno%')";
        if (!empty($cleanDestName)) {
            $wrefCond .= " OR topic LIKE '%" . mysqli_real_escape_string($database->dblink, $cleanDestName) . "%'";
        }
        $q = @mysqli_query($database->dblink, "SELECT id, uid, toWref, ntype, data, topic, time FROM " . TB_PREFIX . "ndata WHERE ($wrefCond) AND ntype IN (1,2,3,4,5,6,7,22,23) AND time BETWEEN $timeMin AND $timeMax ORDER BY id DESC LIMIT 1");
        if ($q && $row = mysqli_fetch_assoc($q)) {
            $rowNtype = (int)$row['ntype'];
            $rowArr = explode(',', $row['data']);
            if (in_array($rowNtype, [1, 2, 3, 4, 5, 6, 7])) {
                $attUid = (int)($rowArr[0] ?? 0);
                $attWref = (int)($rowArr[1] ?? 0);
                $attTribe = (int)($rowArr[2] ?? 1);
                $attSent = array_slice($rowArr, 3, 10);
                $attDead = array_slice($rowArr, 13, 10);
                $hSent = (int)($rowArr[272] ?? $rowArr[270] ?? 0);
                $hDead = (int)($rowArr[273] ?? $rowArr[271] ?? 0);
                $attackerInfo = [
                    'uid' => $attUid,
                    'wref' => $attWref,
                    'tribe' => $attTribe > 0 ? $attTribe : 1,
                    'units' => $attSent,
                    'dead' => $attDead,
                    'hero_sent' => $hSent,
                    'hero_dead' => $hDead,
                ];
                $isAttackerEmpty = false;
            } elseif (in_array($rowNtype, [22, 23])) {
                $attUid = (int)($rowArr[0] ?? 0);
                $attWref = (int)($rowArr[1] ?? 0);
                $attTribe = (int)$database->getUserField($attUid, 'tribe', 0);
                $attSent = array_slice($rowArr, 4, 10);
                $attDead = array_fill(0, 10, 0);
                $hSent = (int)($rowArr[14] ?? 0);
                $attackerInfo = [
                    'uid' => $attUid,
                    'wref' => $attWref,
                    'tribe' => $attTribe > 0 ? $attTribe : 1,
                    'units' => $attSent,
                    'dead' => $attDead,
                    'hero_sent' => $hSent,
                    'hero_dead' => 0,
                ];
                $isAttackerEmpty = false;
            }
        }
    }

    // 3. Additional fallback: check bastion_logs and extract troops from attacker's notice
    if ($isAttackerEmpty && $isBastion && $nTime > 0) {
        $qLog = @mysqli_query($database->dblink, "SELECT * FROM " . TB_PREFIX . "bastion_logs WHERE ABS(event_time - $nTime) <= 120 AND uid > 0 ORDER BY id DESC LIMIT 1");
        if ($qLog && $l = mysqli_fetch_assoc($qLog)) {
            $attUid = (int)$l['uid'];
            $attTribe = (int)$database->getUserField($attUid, 'tribe', 0);
            $qVill = @mysqli_query($database->dblink, "SELECT wref FROM " . TB_PREFIX . "vdata WHERE owner = $attUid LIMIT 1");
            $attWref = ($qVill && $v = mysqli_fetch_assoc($qVill)) ? (int)$v['wref'] : 0;

            // Search ndata for any attack notice belonging to this attacker
            $qAttNotice = @mysqli_query($database->dblink, "SELECT data, ntype FROM " . TB_PREFIX . "ndata WHERE uid = $attUid AND ntype IN (1,2,3,4,5,6,7,22,23) AND ABS(time - $nTime) <= 120 ORDER BY id DESC LIMIT 1");
            if ($qAttNotice && $attRow = mysqli_fetch_assoc($qAttNotice)) {
                $rArr = explode(',', $attRow['data']);
                if (in_array((int)$attRow['ntype'], [1, 2, 3, 4, 5, 6, 7])) {
                    $attackerInfo = [
                        'uid' => $attUid,
                        'wref' => $attWref,
                        'tribe' => $attTribe > 0 ? $attTribe : 1,
                        'units' => array_slice($rArr, 3, 10),
                        'dead' => array_slice($rArr, 13, 10),
                        'hero_sent' => (int)($rArr[272] ?? $rArr[270] ?? 0),
                        'hero_dead' => (int)($rArr[273] ?? $rArr[271] ?? 0),
                    ];
                } elseif (in_array((int)$attRow['ntype'], [22, 23])) {
                    $attackerInfo = [
                        'uid' => $attUid,
                        'wref' => $attWref,
                        'tribe' => $attTribe > 0 ? $attTribe : 1,
                        'units' => array_slice($rArr, 4, 10),
                        'dead' => array_fill(0, 10, 0),
                        'hero_sent' => (int)($rArr[14] ?? 0),
                        'hero_dead' => 0,
                    ];
                }
            } else {
                // Check recent attacks table for this village
                $qAtk = @mysqli_query($database->dblink, "SELECT a.* FROM " . TB_PREFIX . "attacks a WHERE a.vref = $attWref ORDER BY a.id DESC LIMIT 1");
                if ($qAtk && $aRow = mysqli_fetch_assoc($qAtk)) {
                    $attackerInfo = [
                        'uid' => $attUid,
                        'wref' => $attWref,
                        'tribe' => $attTribe > 0 ? $attTribe : 1,
                        'units' => [
                            $aRow['t1'] ?? 0, $aRow['t2'] ?? 0, $aRow['t3'] ?? 0, $aRow['t4'] ?? 0, $aRow['t5'] ?? 0,
                            $aRow['t6'] ?? 0, $aRow['t7'] ?? 0, $aRow['t8'] ?? 0, $aRow['t9'] ?? 0, $aRow['t10'] ?? 0,
                        ],
                        'dead' => array_fill(0, 10, 0),
                        'hero_sent' => (int)($aRow['t11'] ?? 0),
                        'hero_dead' => 0,
                    ];
                }
            }
        }
    }
}

// Build attacker URLs and data if present
$hasAttacker = !empty($attackerInfo);
$attColspan = 10;
$attUserUrl = '';
$attFromUrl = '';
$attAllyTag = '';
$attStart = 1;
$attHasHero = false;
$attHeroSent = 0;
$attHeroDead = 0;

if ($hasAttacker) {
    $attUid = (int)$attackerInfo['uid'];
    $attName = $database->getUserField($attUid, 'username', 0);
    if ($attName != '[?]') {
        $attUserUrl = "<a href=\"" . $playerUrl . $attUid . "\"><b>" . htmlspecialchars($attName) . "</b></a>";
    } else {
        $attUserUrl = "<font color=\"grey\"><b>[?]</b></font>";
    }

    $attAllyId = (int)$database->getUserField($attUid, 'alliance', 0);
    if ($attAllyId > 0 && isset($database->dblink)) {
        $qA = @mysqli_query($database->dblink, "SELECT tag FROM " . TB_PREFIX . "alidata WHERE id = $attAllyId LIMIT 1");
        if ($qA && $a = mysqli_fetch_assoc($qA)) {
            $attAllyTag = $a['tag'];
        }
    }

    $attWref = (int)$attackerInfo['wref'];
    $attVillageName = $database->getVillageField($attWref, 'name');
    if ($attVillageName != '[?]') {
        $attFromUrl = "<a href=\"" . $mapUrl . $attWref . "&c=" . $generator->getMapCheck($attWref) . "\">" . htmlspecialchars($attVillageName) . "</a>";
    } else {
        $attFromUrl = "<font color=\"grey\"><b>[?]</b></font>";
    }

    $attTribe = (int)$attackerInfo['tribe'];
    if ($attTribe <= 0) $attTribe = 1;
    $attStart = ($attTribe - 1) * 10 + 1;
    $attHeroSent = (int)($attackerInfo['hero_sent'] ?? 0);
    $attHeroDead = (int)($attackerInfo['hero_dead'] ?? 0);
    $attHasHero = ($attHeroSent > 0 || $attHeroDead > 0);
    $attColspan = $attHasHero ? 11 : 10;
}

// Destination village info (where the reinforcement was stationed)
$destWref = (int)($dataarray[1] ?? 0);
$destName = !empty($dataarray[2]) ? stripslashes($dataarray[2]) : ($destWref > 0 ? $database->getVillageField($destWref, 'name') : '');
$cleanDestName = ltrim($destName, '? ');
$isBastionDest = ($destWref === 20201 || (defined('Bastion::BASTION_WREF') && $destWref === (int)constant('Bastion::BASTION_WREF')) || strpos($destName, 'Benteng Kuno') !== false || strpos($message->readingNotice['topic'] ?? '', 'Benteng Kuno') !== false);
?>

<style>
img.u91, img.u92, img.u93, img.u94, img.u95, img.u96, img.u97, img.u98, img.u99, img.u100,
.unit.u91, .unit.u92, .unit.u93, .unit.u94, .unit.u95, .unit.u96, .unit.u97, .unit.u98, .unit.u99, .unit.u100 {
    background-image: url('img/un/u/v10_nusantara2.gif?v=20261023c') !important;
}
.report-bastion-badge {
    background: #fef2f2;
    border: 1px solid #f87171;
    color: #991b1b;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
    margin-bottom: 6px;
}
.report-ally {
    color: #b91c1c;
    font-weight: bold;
}
</style>

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

<?php if ($isBastionDest): ?>
    <div style="text-align:center; margin-bottom: 8px;">
        <span class="report-bastion-badge">🏰 Pertahanan di Benteng Kuno (0|0)</span>
    </div>
<?php endif; ?>

<!-- ======================== ATTACKER REPORT ======================== -->
<?php if ($hasAttacker): ?>
<table cellpadding="1" cellspacing="1" id="attacker" class="attacker" style="margin-bottom: 12px;">
<thead>
<tr>
    <td class="role"><?php echo defined('ATTACKER') ? ATTACKER : 'Attacker'; ?></td>
    <td colspan="<?php echo $attColspan; ?>">
        <?php echo $attUserUrl; ?> <?php echo !empty($attAllyTag) ? '<span class="report-ally">[' . htmlspecialchars($attAllyTag) . ']</span>' : ''; ?> <?php echo defined('FROM_THE_VILL') ? FROM_THE_VILL : 'from the village'; ?> <?php echo $attFromUrl; ?>
    </td>
</tr>
</thead>

<tbody class="units">
<?php
$hasAttUnits = (array_sum(array_map('intval', $attackerInfo['units'] ?? [])) > 0 || $attHasHero);
if ($hasAttUnits):
?>
<tr>
<td>&nbsp;</td>

<?php
// Attacker unit icons
for ($i = $attStart; $i <= ($attStart + 9); $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

// Hero icon
if ($attHasHero) {
    echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"" . (defined('RC_HERO') ? RC_HERO : 'Hero') . "\" alt=\"Hero\" /></td>";
}

echo "</tr><tr><th>" . (defined('TROOPS') ? TROOPS : 'Troops') . "</th>";

// Attacker troops sent
for ($i = 0; $i < 10; $i++) {
    $cnt = (int)($attackerInfo['units'][$i] ?? 0);
    echo ($cnt == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>" . $cnt . "</td>";
}

if ($attHasHero) {
    echo "<td>" . $attHeroSent . "</td>";
}

echo "</tr><tr><th>" . (defined('CASUALTIES') ? CASUALTIES : 'Casualties') . "</th>";

// Attacker casualties
for ($i = 0; $i < 10; $i++) {
    $cnt = (int)($attackerInfo['dead'][$i] ?? 0);
    echo ($cnt == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>" . $cnt . "</td>";
}

if ($attHasHero) {
    $tdclass = ($attHeroDead == 0) ? 'class="none"' : '';
    echo "<td $tdclass>" . $attHeroDead . "</td>";
}
?>

</tr>
<?php else: ?>
<tr>
    <td colspan="<?php echo $attColspan; ?>" style="text-align: center; color: #6b7280; font-style: italic; padding: 8px;">
        Data rincian pasukan penyerang tidak tersedia.
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
<?php endif; ?>

<!-- ======================== DEFENDER REPORT ======================== -->
<table cellpadding="1" cellspacing="1" class="defender">

<thead>
<tr>
    <td class="role"><?php echo DEFENDER; ?></td>
    <td colspan="<?php echo $colspan; ?>">
        <?php echo $user_url; ?> <?php echo FROM_THE_VILL; ?> <?php echo $from_url; ?>
        <?php if (!empty($destName)): ?>
            <span style="font-size: 11px; color: #555;">(<?php echo defined('REINFORCEMENTFOR') ? REINFORCEMENTFOR : 'Reinforcement in'; ?> <b><?php echo htmlspecialchars($destName); ?></b>)</span>
        <?php endif; ?>
    </td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
// ======================== UNITS ========================
$tribe = $dataarray[3];
$start = ($tribe - 1) * 10 + 1;

// unit icons
for ($i = $start; $i <= ($start + 9); $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

// hero column
if ($hasHero) {
    echo "<td><img src=\"img/x.gif\" class=\"unit uhero\" title=\"".RC_HERO."\" alt=\"".RC_HERO."\" /></td>";
}

echo "</tr><tr><th>".TROOPS."</th>";

// ======================== TROOPS ========================
for ($i = 4; $i <= 13; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

if ($hasHero) {
    echo "<td>".$dataarray[24]."</td>";
}

// ======================== CASUALTIES ========================
echo "<tr><th>".CASUALTIES."</th>";

for ($i = 14; $i <= 23; $i++) {
    echo ($dataarray[$i] == 0)
        ? "<td class=\"none\">0</td>"
        : "<td>".$dataarray[$i]."</td>";
}

if ($hasHero) {

    // SAFE FIX: avoid undefined variable warning
    $tdclass = (isset($dataarray[25]) && $dataarray[25] == 0)
        ? 'class="none"'
        : '';

    echo "<td $tdclass>".$dataarray[25]."</td>";
}
?>

</tr>
</tbody>

</table>

</td></tr></tbody></table>