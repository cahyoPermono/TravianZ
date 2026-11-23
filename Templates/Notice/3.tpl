<?php

#################################################################################
##                -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-               ##
## --------------------------------------------------------------------------- ##
##  Filename       : 3.tpl                                                     ##
##  Type           : Battle Report - No Troops Returned                        ##
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

// ======================== CONFIG & HERO DETECTION ========================
// In $data_fail (AutomationBattleResolution), attacker hero sent is at index 266, dead at 267 (10-tribe layout).
// In legacy / alternate layouts, hero may be at index 239/240 or 284/285.
$heroSentAtt = 0;
$heroDeadAtt = 0;
$heroSentIdx = 266;
$heroDeadIdx = 267;

if (isset($dataarray[266]) && is_numeric($dataarray[266]) && (int)$dataarray[266] > 0) {
    $heroSentAtt = (int)$dataarray[266];
    $heroDeadAtt = (isset($dataarray[267]) && is_numeric($dataarray[267])) ? (int)$dataarray[267] : 0;
    $heroSentIdx = 266;
    $heroDeadIdx = 267;
} elseif (isset($dataarray[239]) && is_numeric($dataarray[239]) && (int)$dataarray[239] > 0) {
    $heroSentAtt = (int)$dataarray[239];
    $heroDeadAtt = (isset($dataarray[240]) && is_numeric($dataarray[240])) ? (int)$dataarray[240] : 0;
    $heroSentIdx = 239;
    $heroDeadIdx = 240;
} elseif (isset($dataarray[284]) && is_numeric($dataarray[284]) && (int)$dataarray[284] > 0) {
    $heroSentAtt = (int)$dataarray[284];
    $heroDeadAtt = (isset($dataarray[285]) && is_numeric($dataarray[285])) ? (int)$dataarray[285] : 0;
    $heroSentIdx = 284;
    $heroDeadIdx = 285;
}

// An army can only contain at most 1 hero (safeguard against legacy desync/offset reading)
$heroSentAtt = min(1, max(0, $heroSentAtt));
$heroDeadAtt = min(1, max(0, $heroDeadAtt));

$hasHero = ($heroSentAtt > 0);
$colspan  = $hasHero ? 11 : 10;
$colspan2 = 10;

// ======================== URL SETUP ========================

// detectare admin REAL (nu variabilă nesigură)
$isAdmin = (isset($isAdmin) && $isAdmin) || (isset($session) && isset($session->access) && $session->access >= 8) || (isset($_SESSION['access']) && $_SESSION['access'] >= 8);

if ($isAdmin) {
    $mapUrl = "Admin/admin.php?p=village&did=";
    $playerUrl = "Admin/admin.php?p=player&uid=";
} else {
    $mapUrl = "karte.php?d=";
    $playerUrl = "spieler.php?uid=";
}

// ======================== ATTACKER (CACHED DB CALLS) ========================
$attackerId = $dataarray[0];
$attackerName = $database->getUserField($attackerId, 'username', 0);
$attackerUid  = $database->getUserField($attackerId, 'id', 0);

if ($attackerName != "[?]") {
    $user_url = "<a href=\"".$playerUrl.$attackerUid."\">".$attackerName."</a>";
} else {
    $user_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// FROM VILLAGE
$fromVillage = $database->getVillageField($dataarray[1], 'name');

if ($fromVillage != "[?]") {
    $from_url = "<a href=\"".$mapUrl.$dataarray[1]."&c=".$generator->getMapCheck($dataarray[1])."\">".$fromVillage."</a>";
} else {
    $from_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// ======================== DEFENDER (CACHED) ========================
$defId = $dataarray[28];
$defName = $database->getUserField($defId, 'username', 0);
$defUid  = $database->getUserField($defId, 'id', 0);

if ($defName != "[?]") {
    $defuser_url = "<a href=\"".$playerUrl.$defUid."\">".$defName."</a>";
} else {
    $defuser_url = "<font color=\"grey\"><b>[?]</b></font>";
}

// DEF VILLAGE / OASIS HANDLING
$defVillageName = $database->getVillageField($dataarray[29], 'name');

if ($database->isVillageOases($dataarray[29])) {
    $deffrom_url = "<a href=\"".$mapUrl.$dataarray[29]."&c=".$generator->getMapCheck($dataarray[29])."\">".$dataarray[30]."</a>";
} elseif ($defVillageName != "[?]") {
    $deffrom_url = "<a href=\"".$mapUrl.$dataarray[29]."&c=".$generator->getMapCheck($dataarray[29])."\">".$defVillageName."</a>";
} elseif (!empty($dataarray[30]) && $dataarray[30] != "[?]") {
    $deffrom_url = "<a href=\"".$mapUrl.$dataarray[29]."&c=".$generator->getMapCheck($dataarray[29])."\">".htmlspecialchars($dataarray[30])."</a>";
} else {
    $deffrom_url = "<font color=\"grey\"><b>[?]</b></font>";
}

?>
<style>
img.u91, img.u92, img.u93, img.u94, img.u95, img.u96, img.u97, img.u98, img.u99, img.u100,
.unit.u91, .unit.u92, .unit.u93, .unit.u94, .unit.u95, .unit.u96, .unit.u97, .unit.u98, .unit.u99, .unit.u100 {
    background-image: url('img/un/u/v10_nusantara2.gif?v=20261023c') !important;
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

<!-- ======================== ATTACKER ======================== -->
<table cellpadding="1" cellspacing="1" id="attacker">
<thead>
<tr>
<td class="role"><?php echo ATTACKER; ?></td>
<td colspan="<?php echo $colspan; ?>">
    <?php echo $user_url; ?> <?php echo FROM_THE_VILL; ?> <?php echo $from_url; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
// UNIT DISPLAY (attacker)
$tribe = (!empty($dataarray[2]) && is_numeric($dataarray[2])) ? (int)$dataarray[2] : 1;
$start = ($tribe - 1) * 10 + 1;

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

// PRISONERS
if ($heroSentIdx === 266) {
    $trappedStart   = 268;
    $trappedHeroIdx = 278;
} elseif ($heroSentIdx === 239) {
    $trappedStart   = 241;
    $trappedHeroIdx = 251;
} else {
    $trappedStart   = 286;
    $trappedHeroIdx = 296;
}

if (array_sum(array_map('intval', array_slice($dataarray, $trappedStart, 11))) > 0) {

    echo "</tr><tr><th>".PRISONERS."</th>";

    for ($i = $trappedStart; $i <= ($trappedStart + 9); $i++) {
        $val = (int)($dataarray[$i] ?? 0);
        echo ($val == 0)
            ? "<td class=\"none\">0</td>"
            : "<td>".$val."</td>";
    }

    if ($hasHero) {
        $hVal = (int)($dataarray[$trappedHeroIdx] ?? 0);
        $tdclass = ($hVal == 0) ? 'class="none"' : '';
        echo "<td $tdclass>".$hVal."</td>";
    }
}
?>

</tr>
</tbody>

<?php
// ======================== SPECIAL ACTIONS ========================
$catpUnits = [8, 18, 28, 38, 48, 58, 68, 78, 88, 98];
$ramUnits  = [7, 17, 27, 37, 47, 57, 67, 77, 87, 97];
$chiefUnits = [9, 19, 29, 39, 49, 59, 69, 79, 89, 99];

if ($heroSentIdx === 266) {
    $ramUnitIdx   = 279;
    $ramInfoIdx   = 280;
    $catpUnitIdx  = 281;
    $catpInfoIdx  = 282;
    $chiefUnitIdx = 283;
    $chiefInfoIdx = 284;
    $troopInfoIdx = 285;
    $heroInfoIdx  = 286;
} elseif ($heroSentIdx === 272 || count($dataarray) >= 270) {
    $ramUnitIdx   = 264;
    $ramInfoIdx   = 265;
    $catpUnitIdx  = 266;
    $catpInfoIdx  = 267;
    $chiefUnitIdx = 268;
    $chiefInfoIdx = 269;
    $troopInfoIdx = 288;
    $heroInfoIdx  = 289;
} elseif ($heroSentIdx === 239) {
    $ramUnitIdx   = 253;
    $ramInfoIdx   = 254;
    $catpUnitIdx  = 255;
    $catpInfoIdx  = 256;
    $chiefUnitIdx = 257;
    $chiefInfoIdx = 258;
    $troopInfoIdx = 259;
    $heroInfoIdx  = 260;
} else {
    $ramUnitIdx   = 298;
    $ramInfoIdx   = 299;
    $catpUnitIdx  = 300;
    $catpInfoIdx  = 301;
    $chiefUnitIdx = 302;
    $chiefInfoIdx = 303;
    $troopInfoIdx = 304;
    $heroInfoIdx  = 306;
}

// 1. Ram Info Resolution
$ramData = (!empty($dataarray[$ramInfoIdx]) && $dataarray[$ramInfoIdx] !== '?' && $dataarray[$ramInfoIdx] !== '0') ? $dataarray[$ramInfoIdx] : null;
$ramUnit = (!empty($dataarray[$ramUnitIdx]) && is_numeric($dataarray[$ramUnitIdx])) ? (int)$dataarray[$ramUnitIdx] : null;
if ($ramData === null) {
    foreach ($dataarray as $k => $v) {
        if (!is_string($v) || $v === '' || $v === '?' || $v === '0' || is_numeric($v)) continue;
        $prev = isset($dataarray[$k - 1]) && is_numeric($dataarray[$k - 1]) ? (int)$dataarray[$k - 1] : 0;
        if (in_array($prev, $ramUnits, true) ||
            (strpos($v, 'RC_WALL_') !== false || strpos($v, 'RC_NO_WALL') !== false || strpos($v, 'The ram does not work') !== false || stripos($v, 'berbek') !== false || stripos($v, 'ram does not work') !== false) ||
            ((stripos($v, 'wall') !== false || stripos($v, 'tembok') !== false || stripos($v, 'zidul') !== false || stripos($v, 'muralla') !== false || stripos($v, 'mauer') !== false) &&
             (stripos($v, 'damaged') !== false || stripos($v, 'rusak') !== false || stripos($v, 'destroyed') !== false || stripos($v, 'hancur') !== false || stripos($v, 'not damaged') !== false || stripos($v, 'tidak') !== false || stripos($v, 'avariat') !== false))
        ) {
            $ramData = $v;
            if ($prev > 0) $ramUnit = $prev;
            break;
        }
    }
}
if ($ramUnit === null || $ramUnit <= 0) {
    $ramUnit = ($tribe - 1) * 10 + 7;
}

if (!empty($ramData)) {
    $ramName = is_numeric($ramUnit) ? $technology->getUnitName($ramUnit) : (defined('U17') ? U17 : 'Ram');
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $ramUnit; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($ramName); ?>" title="<?php echo htmlspecialchars($ramName); ?>" />
<?php echo $ramData; ?>
</td>
</tr>
</tbody>
<?php }

// 2. Catapult Info Resolution
$catpData = (!empty($dataarray[$catpInfoIdx]) && $dataarray[$catpInfoIdx] !== '?' && $dataarray[$catpInfoIdx] !== '0') ? $dataarray[$catpInfoIdx] : null;
$catpUnit = (!empty($dataarray[$catpUnitIdx]) && is_numeric($dataarray[$catpUnitIdx])) ? (int)$dataarray[$catpUnitIdx] : null;
if ($catpData === null) {
    foreach ($dataarray as $k => $v) {
        if (!is_string($v) || $v === '' || $v === '?' || $v === '0' || is_numeric($v)) continue;
        $prev = isset($dataarray[$k - 1]) && is_numeric($dataarray[$k - 1]) ? (int)$dataarray[$k - 1] : 0;
        if (in_array($prev, $catpUnits, true) ||
            (strpos($v, 'RC_DESTROYED') !== false || strpos($v, 'RC_NOT_DAMAGED') !== false || strpos($v, 'RC_DAMAGED_FROM_TO') !== false || strpos($v, 'RC_NO_BUILDINGS') !== false || strpos($v, 'RC_VILLAGE_') !== false) ||
            ((strpos($v, '<b>') !== false || stripos($v, 'level') !== false || stripos($v, 'tingkat') !== false) &&
             (stripos($v, 'destroyed') !== false || stripos($v, 'hancur') !== false || stripos($v, 'damaged') !== false || stripos($v, 'rusak') !== false || stripos($v, 'zerstört') !== false || stripos($v, 'distrus') !== false || stripos($v, 'détruit') !== false || stripos($v, 'destruid') !== false))
        ) {
            if (in_array($prev, $ramUnits, true) || in_array($prev, $chiefUnits, true) || stripos($v, 'wall') !== false || stripos($v, 'tembok') !== false || stripos($v, 'zidul') !== false || stripos($v, 'muralla') !== false || stripos($v, 'loyalty') !== false || stripos($v, 'loyalitas') !== false) {
                continue;
            }
            $catpData = $v;
            if ($prev > 0) $catpUnit = $prev;
            break;
        }
    }
}
if ($catpUnit === null || $catpUnit <= 0) {
    $catpUnit = ($tribe - 1) * 10 + 8;
}

if (!empty($catpData)) {
    $catpName = is_numeric($catpUnit) ? $technology->getUnitName($catpUnit) : (defined('U18') ? U18 : 'Catapult');
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $catpUnit; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($catpName); ?>" title="<?php echo htmlspecialchars($catpName); ?>" />
<?php echo $catpData; ?>
</td>
</tr>
</tbody>
<?php }

// 3. Chief / Senator / Loyalty Info Resolution
$chiefData = (!empty($dataarray[$chiefInfoIdx]) && $dataarray[$chiefInfoIdx] !== '?' && $dataarray[$chiefInfoIdx] !== '0') ? $dataarray[$chiefInfoIdx] : null;
$chiefUnit = (!empty($dataarray[$chiefUnitIdx]) && is_numeric($dataarray[$chiefUnitIdx])) ? (int)$dataarray[$chiefUnitIdx] : null;
if ($chiefData === null) {
    foreach ($dataarray as $k => $v) {
        if (!is_string($v) || $v === '' || $v === '?' || $v === '0' || is_numeric($v)) continue;
        $prev = isset($dataarray[$k - 1]) && is_numeric($dataarray[$k - 1]) ? (int)$dataarray[$k - 1] : 0;
        if (in_array($prev, $chiefUnits, true) ||
            (strpos($v, 'RC_LOYALTY_') !== false || strpos($v, 'RC_NOT_ENOUGH_CP') !== false || strpos($v, 'RC_CANT_TAKEOVER') !== false || strpos($v, 'RC_RESIDENCE_') !== false || strpos($v, 'RC_NO_REDUCE_') !== false || strpos($v, 'RC_INHABITANTS_') !== false) ||
            (stripos($v, 'loyalty') !== false || stripos($v, 'loyalitas') !== false || stripos($v, 'loialitatea') !== false || stripos($v, 'lealtad') !== false)
        ) {
            $chiefData = $v;
            if ($prev > 0) $chiefUnit = $prev;
            break;
        }
    }
}
if ($chiefUnit === null || $chiefUnit <= 0) {
    $chiefUnit = ($tribe - 1) * 10 + 9;
}

if (!empty($chiefData)) {
    $chiefName = is_numeric($chiefUnit) ? $technology->getUnitName($chiefUnit) : (defined('U19') ? U19 : 'Chief');
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit u<?php echo $chiefUnit; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($chiefName); ?>" title="<?php echo htmlspecialchars($chiefName); ?>" />
<?php echo $chiefData; ?>
</td>
</tr>
</tbody>
<?php }

// 4. Troop Return Info Resolution
$troopReturnMsg = (!empty($dataarray[$troopInfoIdx]) && $dataarray[$troopInfoIdx] !== '?' && $dataarray[$troopInfoIdx] !== '0') ? $dataarray[$troopInfoIdx] : null;
if ($troopReturnMsg === null) {
    foreach ($dataarray as $v) {
        if (is_string($v) && (strpos($v, 'RC_NONE_RETURNED') !== false || stripos($v, 'soldiers returned') !== false)) {
            $troopReturnMsg = $v;
            break;
        }
    }
}
if (!empty($troopReturnMsg)) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<?php echo $troopReturnMsg; ?>
</td>
</tr>
</tbody>
<?php }

if ($hasHero && $heroDeadAtt > 0) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<img class="unit uhero" src="img/x.gif" alt="<?php echo U0; ?>" title="<?php echo U0; ?>" />
<span style="color:#b91c1c;font-weight:bold;"><?php echo defined('TZ_HERO_DIED_BATTLE') ? TZ_HERO_DIED_BATTLE : 'Pahlawan Anda telah gugur dalam pertempuran!'; ?></span>
</td>
</tr>
</tbody>
<?php }

// Check if target was a Bandit Camp to show helpful tactical advice
if (!class_exists('BanditCamp') && file_exists(dirname(__DIR__, 2) . '/GameEngine/BanditCamp.php')) {
    require_once dirname(__DIR__, 2) . '/GameEngine/BanditCamp.php';
}
$toWref = (int)($message->readingNotice['toWref'] ?? $dataarray[29] ?? 0);
if ($toWref > 0 && class_exists('BanditCamp') && BanditCamp::isBanditCamp($toWref)) {
?>
<tbody class="goods">
<tr>
<th><?php echo INFORMATION; ?></th>
<td colspan="<?php echo $colspan; ?>">
<span style="color:#b91c1c;font-weight:bold;">⚔️ Catatan Pertempuran Sarang Bandit:</span> Pasukan dan pahlawan Anda gugur karena melancarkan <b>Serangan Normal (fight to the death)</b> melawan ratusan penjaga bandit. Pada serangan normal, jika kekuatan serang di bawah pertahanan total bandit, pasukan akan wipeout 100%.<br />
💡 <b>Tips:</b> Gunakan mode <b>Serbu / Jarah (Raid)</b> di masa mendatang agar kerugian pasukan terbagi secara proporsional dan hero tidak langsung gugur.
</td>
</tr>
</tbody>
<?php }
?>

</table>

<!-- ======================== DEFENDER ======================== -->
<?php
$target = $dataarray[34] - 1;
$start = ($target * 10) + 1;
$troopsStart = ($target * 21) + 35;
?>

<table cellpadding="1" cellspacing="1" class="defender">
<thead>
<tr>
<td class="role"><?php echo DEFENDER; ?></td>
<td colspan="<?php echo $colspan2; ?>">
    <?php echo $defuser_url." ".FROM_THE_VILL." ".$deffrom_url; ?>
</td>
</tr>
</thead>

<tbody class="units">
<tr>
<td>&nbsp;</td>

<?php
$unmaskedDef = null;
if ($isAdmin) {
    $toWref = (int)($message->readingNotice['toWref'] ?? $dataarray[29] ?? 0);
    $repTime = (int)($message->readingNotice['time'] ?? 0);
    $repId = (int)($message->readingNotice['id'] ?? 0);
    if ($toWref > 0 && $repTime > 0) {
        $uRes = $database->query("
            SELECT `data` FROM " . TB_PREFIX . "ndata 
            WHERE toWref = $toWref 
              AND `time` BETWEEN " . ($repTime - 10) . " AND " . ($repTime + 10) . " 
              AND id != $repId 
              AND `data` NOT LIKE '%?,?%' 
            ORDER BY id ASC LIMIT 1
        ");
        if ($uRes instanceof \mysqli_result && ($uRow = mysqli_fetch_assoc($uRes))) {
            $unmaskedDef = array_map('tz_expand_report', explode(",", $uRow['data']));
        }
    }
}

for ($i = $start; $i <= ($start + 9); $i++) {
    $unitName = $technology->getUnitName($i);
    echo "<td><img src=\"img/x.gif\" class=\"unit u$i\" title=\"$unitName\" alt=\"$unitName\" /></td>";
}

echo "</tr><tr><th>".TROOPS."</th>";

for ($i = 0; $i < 10; $i++) {
    if ($unmaskedDef !== null && isset($unmaskedDef[35 + $i])) {
        $val = $unmaskedDef[35 + $i];
        echo ($val === '0' || $val === 0)
            ? "<td class=\"none\">0</td>"
            : "<td style=\"font-weight:bold;color:#1e40af;\">".$val."</td>";
    } else {
        echo "<td class=\"none\">?</td>";
    }
}

echo "<tr><th>".CASUALTIES."</th>";

for ($i = 0; $i < 10; $i++) {
    if ($unmaskedDef !== null && isset($unmaskedDef[45 + $i])) {
        $val = $unmaskedDef[45 + $i];
        echo ($val === '0' || $val === 0)
            ? "<td class=\"none\">0</td>"
            : "<td style=\"font-weight:bold;color:#b91c1c;\">".$val."</td>";
    } else {
        echo "<td class=\"none\">?</td>";
    }
}
?>

</tr>
</tbody>
</table>

<?php
// ======================== ECONOMIC BATTLE ANALYSIS ========================
if (file_exists(dirname(__DIR__, 2) . '/GameEngine/BattleReportEconomy.php')) {
    require_once dirname(__DIR__, 2) . '/GameEngine/BattleReportEconomy.php';
    $viewerUid = (int)($session->uid ?? 0);
    $econAnalysis = BattleReportEconomy::calculate($dataarray, $viewerUid, true);
    echo BattleReportEconomy::renderHtml($econAnalysis);
}
?>

</td></tr></tbody></table>