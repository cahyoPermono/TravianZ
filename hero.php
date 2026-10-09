<?php
include_once("GameEngine/Generator.php");
$start_timer = $generator->pageLoadTimeStart();

#################################################################################
##  Filename       : hero.php                                                  ##
##  Type           : Dedicated Hero Management Page (T4 Style)                 ##
##  Purpose        : Hero attributes, inventory, adventures, and auction       ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
include_once("GameEngine/Technology.php");
include_once("GameEngine/Units.php");
AccessLogger::logRequest();

global $units, $technology, $database, $session, $village, $generator;

if (!isset($session) || !is_object($session) || $session->uid <= 0) {
    header("Location: login.php");
    exit;
}

// Auto-provision starter hero if enabled and player does not have one yet
if (defined('HERO_FROM_START') && HERO_FROM_START && class_exists('Units')) {
    Units::createStarterHero($session->uid, (int)$village->wid, (int)$session->tribe, $session->username);
}

if (!isset($units) || !is_object($units)) {
    $units = isset($GLOBALS['units']) && is_object($GLOBALS['units']) ? $GLOBALS['units'] : new Units();
}
if (!isset($technology) || !is_object($technology)) {
    $technology = isset($GLOBALS['technology']) && is_object($GLOBALS['technology']) ? $GLOBALS['technology'] : new Technology();
}

$hero_info = $units->Hero($session->uid);
$heroes = $units->Hero($session->uid, 1);

// Handle village switch
if (isset($_GET['newdid'])) {
    $_SESSION['wid'] = (int) $_GET['newdid'];
    $tabParam = isset($_GET['t4tab']) ? '&t4tab=' . preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET['t4tab']) : '';
    header("Location: hero.php" . ($tabParam ? '?' . ltrim($tabParam, '&') : ''));
    exit;
}

// Handle oasis abandonment if submitted from hero.php
if (isset($_GET['t4tab']) && $_GET['t4tab'] === 'land' && isset($_GET['del'])) {
    $oasisWref = (int) $_GET['del'];
    $oasisOwner = (int) $database->getOasisField($oasisWref, 'owner');
    $oasisConquered = (int) $database->getOasisField($oasisWref, 'conqured');
    if ($oasisWref > 0 && $oasisOwner === (int) $session->uid && $oasisConquered === (int) $village->wid) {
        $units->returnOasisTroops($oasisWref);
        $database->removeOases($oasisWref);
    }
    header("Location: hero.php?t4tab=land");
    exit;
}

// Active tab determination
$allowedTabs = ['hero', 'items', 'adventures', 'auction', 'land'];
$t4tab = isset($_GET['t4tab']) && in_array($_GET['t4tab'], $allowedTabs, true) ? $_GET['t4tab'] : 'hero';
$id = 0; // Standalone hero page

$heroUnitNames = [
    1  => U1, 2  => U2, 3  => U3, 5  => U5, 6  => U6,
    11 => U11, 12 => U12, 13 => U13, 15 => U15, 16 => U16,
    21 => U21, 22 => U22, 24 => U24, 25 => U25, 26 => U26,
    51 => U51, 53 => U53, 54 => U54, 55 => U55, 56 => U56,
    61 => U61, 62 => U62, 63 => U63, 65 => U65, 66 => U66,
    71 => U71, 72 => U72, 73 => U73, 75 => U75, 76 => U76,
    81 => U81, 83 => U83, 84 => U84, 85 => U85, 86 => U86,
];

if ($hero_info) {
    $name  = $heroUnitNames[$hero_info['unit']] ?? null;
    $name1 = $hero_info['name'];
} else {
    $name = 'Mr. Nobody';
    $name1 = 'unknown';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title><?php echo SERVER_NAME . ' - ' . (defined('HERO') ? HERO : 'Hero') . ' &raquo; ' . ($hero_info ? htmlspecialchars($hero_info['name']) : 'Overview'); ?></title>
    <link rel="shortcut icon" href="favicon.ico"/>
    <meta http-equiv="cache-control" content="max-age=0" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="expires" content="0" />
    <meta http-equiv="imagetoolbar" content="no" />
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="mt-full.js?0faab" type="text/javascript"></script>
    <script src="unx.js?f4b7i" type="text/javascript"></script>
    <script src="new.js?0faab" type="text/javascript"></script>
    <link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?f4b7i" rel="stylesheet" type="text/css" />
    <link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?e21d2" rel="stylesheet" type="text/css" />
    <link href="css/mobile_engine.css?v=20261009_m1" rel="stylesheet" type="text/css" />
    <link href="<?php echo GP_LOCATE; ?>travian.css?e21d2" rel="stylesheet" type="text/css" />
    <script type="text/javascript">
    window.addEvent('domready', start);
    </script>
</head>

<body class="v35 ie ie8">
<div class="wrapper">
<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" />
<div id="dynamic_header"></div>
<?php include("Templates/header.tpl"); ?>
<div id="mid">
<?php include("Templates/menu.tpl"); ?>

<div id="content" class="hero">
    <h1><?php echo defined('HERO') ? HERO : 'Hero'; ?></h1>

    <?php
    // Navigation tabs (Hero, Items, Adventures, Auction)
    include_once("Templates/Build/37_t4nav.tpl");

    // Revive check if hero is dead
    $isDead = false;
    if ($hero_info === false && !empty($heroes)) {
        foreach ($heroes as $hdata) {
            if ($hdata['dead'] == 1) {
                $isDead = true;
                break;
            }
        }
    }

    if ($t4tab === 'adventures') {
        include_once("Templates/Build/37_adventures.tpl");
    } elseif ($t4tab === 'items') {
        include_once("Templates/Build/37_items.tpl");
    } elseif ($t4tab === 'auction') {
        include_once("Templates/Build/37_auction.tpl");
    } elseif ($t4tab === 'land') {
        include_once("Templates/Build/37_land.tpl");
    } else {
        // Tab 'hero'
        if ($isDead) {
            include_once("Templates/Build/37_revive.tpl");
        } elseif ($hero_info !== false) {
            include_once("Templates/Build/37_hero.tpl");
        } else {
            echo "<p>" . (defined('HERO_NOT_YET') ? HERO_NOT_YET : 'No hero active.') . "</p>";
        }
    }
    ?>
</div>

<div id="side_info">
<?php
include("Templates/multivillage.tpl");
include("Templates/quest.tpl");
include("Templates/news.tpl");
if(!NEW_FUNCTIONS_DISPLAY_LINKS) {
    echo "<br><br><br><br>";
    include("Templates/links.tpl");
}
?>
</div>
<div class="clear"></div>
</div>
<div class="footer-stopper"></div>
<div class="clear"></div>

<?php
include("Templates/footer.tpl");
include("Templates/res.tpl");
?>
<div id="stime">
<div id="ltime">
<div id="ltimeWrap">
<?php echo CALCULATED_IN;?> <b><?php echo round(($generator->pageLoadTimeEnd() - $start_timer) * 1000); ?></b> ms
<br /><?php echo SERVER_TIME;?> <span id="tp1" class="b"><?php echo date('H:i:s'); ?></span>
</div>
</div>
</div>
<div id="ce"></div>
</body>
</html>
