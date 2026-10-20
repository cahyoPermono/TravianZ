<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       dorf2.tpl                                                   ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor 			                       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##                                                                             ##
##  Refactor notes:                                                            ##
##  - păstrată logica originală 100%                                           ##
##  - compatibil PHP 5.6+ / 7+                                                 ##
##  - redus cod duplicat                                                       ##
##  - optimizat accesul repetitiv în resarray                                  ##
##  - comentarii adăugate                                                      ##
##  - output HTML mai sigur                                                    ##
##                                                                             ##
#################################################################################

/**
 * Escape HTML compatibil PHP vechi
 */
if (!function_exists('safeHTML')) {
    function safeHTML($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Returnează datele unui field
 */
if (!function_exists('getFieldData')) {
    function getFieldData($resarray, $fieldId)
    {
        return array(
            'level' => isset($resarray['f'.$fieldId]) ? (int)$resarray['f'.$fieldId] : 0,
            'type'  => isset($resarray['f'.$fieldId.'t']) ? (int)$resarray['f'.$fieldId.'t'] : 0
        );
    }
}
?>

<?php
/**
 * Detect all active building processes (Construction, Training, Research, Smithy, Armoury, Celebration, Demolition)
 */
$activeBuildings = array();
$gidToFields = array();

// Map field slots (19-38) to building types (gid)
for ($f = 19; $f <= 38; $f++) {
    $t = isset($village->resarray['f'.$f.'t']) ? (int)$village->resarray['f'.$f.'t'] : 0;
    if ($t > 0) {
        if (!isset($gidToFields[$t])) {
            $gidToFields[$t] = array();
        }
        $gidToFields[$t][] = $f;
    }
}
$gidToFields[16] = array(39); // Rally Point
if (isset($village->resarray['f40t']) && (int)$village->resarray['f40t'] > 0) {
    $gidToFields[(int)$village->resarray['f40t']] = array(40);
}
if (isset($village->resarray['f99t']) && (int)$village->resarray['f99t'] == 40) {
    $gidToFields[40] = array(99); // World Wonder
}

// 1. Town Hall Celebrations (GID 24)
$celTime = isset($village->currentcel) ? (int)$village->currentcel : 0;
if ($celTime <= time() && isset($village->wid) && isset($database)) {
    $celTime = (int)$database->getVillageField($village->wid, 'celebration');
}
if ($celTime > time() && !empty($gidToFields[24])) {
    foreach ($gidToFields[24] as $f) {
        $activeBuildings[$f] = array(
            'class' => 'anim-celebrating',
            'label' => defined('CELEBRATIONS') ? CELEBRATIONS . '...' : 'Holding celebration...',
            'icon'  => '🎉'
        );
    }
}

// 2. Academy Research & Smithy / Armoury Upgrades
if (!empty($village->researching) && is_array($village->researching)) {
    foreach ($village->researching as $res) {
        $prefix = substr($res['tech'], 0, 1);
        if ($prefix === 't' && !empty($gidToFields[22])) { // Academy
            foreach ($gidToFields[22] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-researching',
                    'label' => defined('RESEARCH') ? RESEARCH . '...' : 'Researching...',
                    'icon'  => '📚'
                );
            }
        } elseif ($prefix === 'b' && !empty($gidToFields[12])) { // Blacksmith
            foreach ($gidToFields[12] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-smithing',
                    'label' => defined('UPGRADING') ? UPGRADING . ' (Weapon)...' : 'Forging weapons...',
                    'icon'  => '🔥'
                );
            }
        } elseif ($prefix === 'a' && !empty($gidToFields[13])) { // Armoury
            foreach ($gidToFields[13] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-armoring',
                    'label' => defined('UPGRADING') ? UPGRADING . ' (Armor)...' : 'Forging armor...',
                    'icon'  => '🛡️'
                );
            }
        }
    }
}

// 3. Troop Training
$trainQueue = (!empty($village->wid) && isset($database)) ? $database->getTraining($village->wid) : array();
if (!empty($trainQueue) && is_array($trainQueue)) {
    global $unitsbytype;
    if (!isset($unitsbytype)) {
        include_once("GameEngine/Data/unitdata.php");
    }
    $ownerTribe = (int)($session->tribe ?? 0);
    foreach ($trainQueue as $train) {
        $unit = (int)$train['unit'];
        $isGreat = $unit > 1000;
        $baseUnit = $isGreat ? $unit - 1000 : $unit;

        $targetGid = 0;
        $actLabel = defined('TRAINING') ? TRAINING . '...' : 'Training troops...';
        $actIcon = '⚔️';

        if ($baseUnit == 99 && $ownerTribe == 3) {
            $targetGid = 36; // Trapper
            $actIcon = '🪤';
            $actLabel = 'Setting traps...';
        } elseif (isset($unitsbytype['expansion']) && in_array($baseUnit, $unitsbytype['expansion'])) {
            if (!empty($gidToFields[25])) $targetGid = 25;
            elseif (!empty($gidToFields[26])) $targetGid = 26;
            elseif (!empty($gidToFields[44])) $targetGid = 44;
            $actIcon = '👑';
            $actLabel = 'Training settlers/leaders...';
        } elseif (isset($unitsbytype['siege']) && in_array($baseUnit, $unitsbytype['siege'])) {
            $targetGid = $isGreat ? 49 : 21;
            $actIcon = '⚙️';
            $actLabel = 'Building siege engines...';
        } elseif (isset($unitsbytype['cavalry']) && in_array($baseUnit, $unitsbytype['cavalry'])) {
            $targetGid = $isGreat ? 30 : 20;
            $actIcon = '🐎';
            $actLabel = 'Training cavalry...';
        } else {
            $targetGid = $isGreat ? 29 : 19;
            $actIcon = '⚔️';
            $actLabel = 'Training infantry...';
        }

        if ($targetGid > 0 && !empty($gidToFields[$targetGid])) {
            foreach ($gidToFields[$targetGid] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-training',
                    'label' => $actLabel,
                    'icon'  => $actIcon
                );
            }
        }
    }
}

// 4. Hospital Healing
if (isset($database) && method_exists($database, 'getHealing') && !empty($village->wid)) {
    $healingQueue = $database->getHealing($village->wid);
    if (!empty($healingQueue)) {
        foreach (array(46, 48) as $hGid) {
            if (!empty($gidToFields[$hGid])) {
                foreach ($gidToFields[$hGid] as $f) {
                    $activeBuildings[$f] = array(
                        'class' => 'anim-training',
                        'label' => 'Healing wounded troops...',
                        'icon'  => '🩹'
                    );
                }
            }
        }
    }
}

// 5. Hero Reviving / Training
if (!empty($gidToFields[37]) && isset($session->uid, $database) && method_exists($database, 'getHero')) {
    $heroData = $database->getHero($session->uid, 0, true);
    if (!empty($heroData)) {
        $h = isset($heroData['uid']) ? $heroData : (isset($heroData[0]) ? $heroData[0] : null);
        if ($h && (int)$h['wref'] === (int)$village->wid && ((int)$h['inrevive'] === 1 || (int)($h['intraining'] ?? 0) === 1) && (int)$h['trainingtime'] > time()) {
            foreach ($gidToFields[37] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-training',
                    'label' => 'Reviving hero...',
                    'icon'  => '👑'
                );
            }
        }
    }
}

// 6. Demolition
if (isset($database) && method_exists($database, 'getDemolition') && !empty($village->wid)) {
    $demolition = $database->getDemolition($village->wid);
    if (!empty($demolition) && isset($demolition[0]['buildnumber']) && (int)$demolition[0]['buildnumber'] > 0) {
        $demolishedField = (int)$demolition[0]['buildnumber'];
        $activeBuildings[$demolishedField] = array(
            'class' => 'anim-demolishing',
            'label' => defined('DEMOLITION') ? DEMOLITION . '...' : 'Demolishing...',
            'icon'  => '💥'
        );
        if (!empty($gidToFields[15])) {
            foreach ($gidToFields[15] as $f) {
                $activeBuildings[$f] = array(
                    'class' => 'anim-constructing',
                    'label' => defined('DEMOLITION') ? DEMOLITION . '...' : 'Overseeing demolition...',
                    'icon'  => '🔨'
                );
            }
        }
    }
}

// 7. Construction / Upgrade in Progress (HIGHEST PRIORITY)
if (!empty($building->buildArray) && is_array($building->buildArray)) {
    foreach ($building->buildArray as $job) {
        $fieldId = (int)$job['field'];
        $activeBuildings[$fieldId] = array(
            'class' => 'anim-constructing',
            'label' => defined('BUILDING_UPGRADING') ? trim(BUILDING_UPGRADING, ':') . '...' : 'Upgrading...',
            'icon'  => '🔨'
        );
    }
}

/**
 * Determină titlul zidului
 */
if ($building->walling()) {

    $wtitle = Building::procResType($building->walling()) .
              " Level " .
              (int)$village->resarray['f40'];

} else {

    if ((int)$village->resarray['f40'] === 0) {

        $wtitle = "Outer building site";

    } else {

        $wtitle = Building::procResType($village->resarray['f40t'], 0) .
                  " Level " .
                  (int)$village->resarray['f40'];
    }
}

if (isset($activeBuildings[40])) {
    $wtitle .= " (" . $activeBuildings[40]['label'] . ")";
}
?>

<!-- ===================== MAP 1 ===================== -->

<map name="map1" id="map1">

    <area href="build.php?id=40"
          title="<?php echo safeHTML($wtitle); ?>"
          coords="325,225,180"
          shape="circle"
          alt="" />

    <area href="build.php?id=40"
          title="<?php echo safeHTML($wtitle); ?>"
          coords="220,230,185"
          shape="circle"
          alt="" />

</map>

<!-- ===================== MAP 2 ===================== -->

<map name="map2" id="map2">

<?php

/**
 * Coordonate clădiri
 * Cheile sunt generate automat 19-39
 */
$coords = array(
    19 => "53,91,91,71,127,91,91,112",
    20 => "136,66,174,46,210,66,174,87",
    21 => "196,56,234,36,270,56,234,77",
    22 => "270,69,308,49,344,69,308,90",
    23 => "327,117,365,97,401,117,365,138",
    24 => "14,129,52,109,88,129,52,150",
    25 => "97,137,135,117,171,137,135,158",
    26 => "182,119,182,65,257,65,257,119,220,140",
    27 => "337,156,375,136,411,156,375,177",
    28 => "2,199,40,179,76,199,40,220",
    29 => "129,164,167,144,203,164,167,185",
    30 => "92,189,130,169,166,189,130,210",
    31 => "342,216,380,196,416,216,380,237",
    32 => "22,238,60,218,96,238,60,259",
    33 => "167,232,205,212,241,232,205,253",
    34 => "290,251,328,231,364,251,328,272",
    35 => "95,273,133,253,169,273,133,294",
    36 => "222,284,260,264,296,284,260,305",
    37 => "80,306,118,286,154,306,118,327",
    38 => "199,316,237,296,273,316,237,337",
    39 => "270,158,303,135,316,155,318,178,304,211,288,227,263,238,250,215"
);

/**
 * Sloturi speciale Natar
 */
$natarBlocked = array(25, 26, 29, 30, 33);

/**
 * Generare areas pentru clădiri
 */
for ($t = 19; $t <= 39; $t++) {

    // Skip sloturi speciale Natar
    if ($village->natar == 1 && in_array($t, $natarBlocked)) {

        // World Wonder
        if ($t == 33) {

            if ((int)$village->resarray['f99'] > 0) {

                $title = Building::procResType(40) .
                         " Level " .
                         (int)$village->resarray['f99'];

            } else {

                $title = Building::procResType(40);
            }

            if (isset($activeBuildings[99])) {
                $title .= " (" . $activeBuildings[99]['label'] . ")";
            }

            echo '<area href="build.php?id=99"
                        title="' . safeHTML($title) . '"
                        coords="190,170,80"
                        shape="circle" />';
        }

        continue;
    }

    $field = getFieldData($village->resarray, $t);

    // Titlu clădire
    if ($field['type'] > 0) {

        $title = Building::procResType($field['type']) .
                 " Level " .
                 $field['level'];

    } else {

        $title = "Building site";

        if ($t == 39 && $field['level'] == 0) {
            $title = "Rally Point building site";
        }
    }

    if (isset($activeBuildings[$t])) {
        $title .= " (" . $activeBuildings[$t]['label'] . ")";
    }

    echo '<area href="build.php?id=' . $t . '"
                title="' . safeHTML($title) . '"
                coords="' . safeHTML($coords[$t]) . '"
                shape="poly" />';
}
?>

    <!-- Zid exterior -->

    <area href="build.php?id=40"
          title="<?php echo safeHTML($wtitle); ?>"
          coords="312,338,347,338,377,320,406,288,421,262,421,222,396,275,360,311"
          shape="poly"
          alt="" />

    <area href="build.php?id=40"
          title="<?php echo safeHTML($wtitle); ?>"
          coords="49,338,0,274,0,240,33,286,88,338"
          shape="poly"
          alt="" />

    <area href="build.php?id=40"
          title="<?php echo safeHTML($wtitle); ?>"
          coords="0,144,34,88,93,39,181,15,252,15,305,31,358,63,402,106,421,151,421,93,378,47,280,0,175,0,78,28,0,92"
          shape="poly"
          alt="" />

</map>

<?php
/**
 * Sufixul graficii satului per trib (galii nu au postfix).
 * Triburile 6-9 folosesc grafica existenta cea mai apropiata pana la imagini dedicate.
 * Nota: versiunea veche muta $session->tribe pentru gali - eliminat (efect secundar global).
 */
$vmapSuffix = [1 => '1', 2 => '2', 3 => '', 4 => '1', 5 => '1', 6 => '6', 7 => '7', 8 => '8', 9 => '9'];
$suffix = isset($vmapSuffix[$session->tribe]) ? $vmapSuffix[$session->tribe] : '1';

/**
 * Determină clasa hărții
 */
if ($building->walling()) {

    $vmapc = "d2_1" . $suffix;

} else {

    $vmapc = ((int)$village->resarray['f40'] === 0)
        ? "d2_0"
        : "d2_1" . $suffix;
}
?>

<!-- ===================== VILLAGE MAP ===================== -->

<?php
$animDisabledClass = (isset($_COOKIE['tz_no_anim']) && $_COOKIE['tz_no_anim'] === '1') ? ' anim-disabled' : '';
?>
<div id="village_map" class="<?php echo safeHTML($vmapc . $animDisabledClass); ?>">

<?php

/**
 * Sloturi speciale Natar
 */
$natarVillageSkip = array(25, 26, 29, 30, 33);

/**
 * Clădiri sat
 */
for ($i = 1; $i <= 20; $i++) {

    $fieldId = $i + 18;

    // Skip sloturi Natar
    if ($village->natar == 1 && in_array($fieldId, $natarVillageSkip)) {
        continue;
    }

    $field = getFieldData($village->resarray, $fieldId);

    $text = "Building site";
    $img  = "iso";

    // Clădire existentă
    if ($field['type'] != 0) {

        $text = Building::procResType($field['type']) .
                " Level " .
                $field['level'];

        $img = "g" . $field['type'];
    }

    /**
     * Verifică dacă există upgrade în curs
     */
    if (!empty($building->buildArray) && is_array($building->buildArray)) {

        foreach ($building->buildArray as $job) {

            if ((int)$job['field'] == $fieldId) {

                $img = 'g' . (int)$job['type'] . 'b';

                $text = Building::procResType($job['type']) .
                        " Level " .
                        $field['level'];

                break;
            }
        }
    }

    $animClass = '';
    if (isset($activeBuildings[$fieldId])) {
        $animClass = ' ' . $activeBuildings[$fieldId]['class'];
        $text .= ' (' . $activeBuildings[$fieldId]['label'] . ')';
    }

    echo '<img src="img/x.gif"
               class="building d' . $i . ' ' . safeHTML($img) . $animClass . '"
               alt="' . safeHTML($text) . '" />';

    /**
     * Quest rockets
     */
    if (
        (isset($_SESSION['qst']) && $_SESSION['qst'] == 38 && QTYPE == 37) ||
        (isset($_SESSION['qst']) && $_SESSION['qst'] == 31 && QTYPE == 25)
    ) {

        if ($i < 8) {

            $dte = array("tur", "purp", "yell", "oran", "green", "red", "dark");

            $im = $dte[$i - 1];

            echo '<img src="img/x.gif"
                       class="building e' . $i . ' rocket ' . $im . '"
                       alt="' . safeHTML($text) . '" />';
        }
    }
}

/**
 * Update quest
 */
if (
    (isset($_SESSION['qst']) && $_SESSION['qst'] == 38 && QTYPE == 37) ||
    (isset($_SESSION['qst']) && $_SESSION['qst'] == 31 && QTYPE == 25)
) {

    $database->updateUserField(
        $_SESSION['username'],
        'quest',
        '40',
        0
    );

    $_SESSION['qst'] = 40;
}

/**
 * Rally Point
 */
$rpAnimClass = isset($activeBuildings[39]) ? ' ' . $activeBuildings[39]['class'] : '';
$rpText = 'Rally Point Level ' . (int)$village->resarray['f39'];
if (isset($activeBuildings[39])) {
    $rpText .= ' (' . $activeBuildings[39]['label'] . ')';
}

if ((int)$village->resarray['f39'] == 0) {

    if ($building->rallying()) {

        echo '<img src="img/x.gif"
                   class="dx1 g16b' . $rpAnimClass . '"
                   alt="' . safeHTML($rpText) . '" />';

    } else {

        echo '<img src="img/x.gif"
                   class="dx1 g16e"
                   alt="Rally Point building site" />';
    }

} else {

    echo '<img src="img/x.gif"
               class="dx1 g16' . $rpAnimClass . '"
               alt="' . safeHTML($rpText) . '" />';
}
?>

<?php
/**
 * World Wonder render
 */
if (
    isset($village->resarray['f99t']) &&
    (int)$village->resarray['f99t'] == 40
) {

    $wwLevel = (int)$village->resarray['f99'];

    /**
     * Stil de Minune propriu fiecarui trib.
     *
     * Adaugam o clasa suplimentara (.wwt1 ... .wwt9) peste cea de nivel. In CSS
     * combinatia are prioritate mai mare, deci suprascrie imaginea, inaltimea si
     * pozitia.
     *
     * FALLBACK: clasa se pune DOAR daca tribul chiar are imaginile pe disc.
     * Pentru triburile fara arta proprie nu se adauga nimic, iar minunea ramane
     * cea originala. Cand adaugi imagini pentru un trib nou, e de ajuns sa pui
     * fisierele in gpack/<pachet>/img/g/ww/t<trib>/ si regulile in CSS.
     */
    include_once('GameEngine/Data/ww_tribe.php');
    $wwClass = tz_ww_tribe_class($village->owner_tribe ?? $session->tribe ?? 0);
    $wwAnimClass = isset($activeBuildings[99]) ? ' ' . $activeBuildings[99]['class'] : '';
    $wwText = 'Worldwonder';
    if (isset($activeBuildings[99])) {
        $wwText .= ' (' . $activeBuildings[99]['label'] . ')';
    }

    if ($wwLevel >= 0 && $wwLevel <= 19) {
        echo '<img class="ww g40' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }

    if ($wwLevel >= 20 && $wwLevel <= 39) {
        echo '<img class="ww g40_1' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }

    if ($wwLevel >= 40 && $wwLevel <= 59) {
        echo '<img class="ww g40_2' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }

    if ($wwLevel >= 60 && $wwLevel <= 79) {
        echo '<img class="ww g40_3' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }

    if ($wwLevel >= 80 && $wwLevel <= 99) {
        echo '<img class="ww g40_4' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }

    if ($wwLevel == 100) {
        echo '<img class="ww g40_5' . $wwClass . $wwAnimClass . '" src="img/x.gif" alt="' . safeHTML($wwText) . '">';
    }
}
?>

    <!-- ===================== BUILDING ACTIVITY BADGES ===================== -->
    <div id="building_activities">
<?php
for ($i = 1; $i <= 20; $i++) {
    $fieldId = $i + 18;
    if ($village->natar == 1 && in_array($fieldId, $natarVillageSkip)) {
        continue;
    }
    if (isset($activeBuildings[$fieldId])) {
        $act = $activeBuildings[$fieldId];
        echo '<div class="bld-activity d' . $i . ' ' . safeHTML($act['class']) . '" title="' . safeHTML($act['label']) . '">'
            . '<span class="bld-badge"><span class="badge-icon">' . $act['icon'] . '</span></span>'
            . '</div>';
    }
}
if (isset($activeBuildings[39])) {
    $act = $activeBuildings[39];
    echo '<div class="bld-activity dx1 ' . safeHTML($act['class']) . '" title="' . safeHTML($act['label']) . '">'
        . '<span class="bld-badge"><span class="badge-icon">' . $act['icon'] . '</span></span>'
        . '</div>';
}
if (isset($activeBuildings[99])) {
    $act = $activeBuildings[99];
    echo '<div class="bld-activity d40 ' . safeHTML($act['class']) . '" title="' . safeHTML($act['label']) . '">'
        . '<span class="bld-badge"><span class="badge-icon">' . $act['icon'] . '</span></span>'
        . '</div>';
}
?>
    </div>

    <!-- ===================== LEVELS ===================== -->

    <div id="levels"
        <?php
        if (isset($_COOKIE['t3l'])) {
            echo 'class="on"';
        }
        ?>>

<?php
/**
 * Level numbers
 */
for ($i = 1; $i <= 20; $i++) {

    $fieldId = $i + 18;

    if ((int)$village->resarray['f'.$fieldId.'t'] != 0) {

        echo '<div class="d' . $i . '">'
            . (int)$village->resarray['f'.$fieldId]
            . '</div>';
    }
}

/**
 * Rally point level
 */
if ((int)$village->resarray['f39t'] != 0) {

    echo '<div class="l39">'
        . (int)$village->resarray['f39']
        . '</div>';
}

/**
 * Wall level
 */
if ((int)$village->resarray['f40t'] != 0) {

    echo '<div class="l40">'
        . (int)$village->resarray['f40']
        . '</div>';
}

/**
 * WW level
 */
if ((int)$village->resarray['f99t'] != 0) {

    echo '<div class="d40">'
        . (int)$village->resarray['f99']
        . '</div>';
}
?>

    </div>

    <!-- Overlay maps with exact dimensions to prevent Chromium coordinate scaling bugs -->
    <img class="map1" usemap="#map1" src="img/dorf2_map1_540x448.png" width="540" height="448" alt="" />
    <img class="map2" usemap="#map2" src="img/dorf2_map2_422x339.png" width="422" height="339" alt="" />

</div>

<!-- Toggle levels -->
<img src="img/x.gif"
     id="lswitch"
     <?php
     if (isset($_COOKIE['t3l'])) {
         echo 'class="on"';
     }
     ?>
     onclick="vil_levels_toggle()" />

<!-- Toggle building animations -->
<div id="anim_switch"
     class="<?php echo (isset($_COOKIE['tz_no_anim']) && $_COOKIE['tz_no_anim'] === '1') ? 'off' : 'on'; ?>"
     title="Toggle Building Animations"
     onclick="vil_anim_toggle(this)">
    <span class="anim-icon">&#9889;</span>
</div>
<script type="text/javascript">
function vil_anim_toggle(btn) {
    var vmap = document.getElementById('village_map');
    if (!vmap) return;
    var isDisabled = vmap.classList.toggle('anim-disabled');
    if (btn) {
        if (isDisabled) {
            btn.classList.remove('on');
            btn.classList.add('off');
        } else {
            btn.classList.remove('off');
            btn.classList.add('on');
        }
    }
    document.cookie = 'tz_no_anim=' + (isDisabled ? '1' : '0') + '; path=/; max-age=31536000';
}
</script>