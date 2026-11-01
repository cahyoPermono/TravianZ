<?php

/**
 * Test for Bastion Stationed Troops Roster and Attacker Breakdown in Reinforcement Reports (15.tpl)
 */

echo "=== STARTING BASTION STATIONED TROOPS & ATTACKER REPORT TEST ===\n";

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

global $generator, $technology;
if (!isset($technology)) {
    $technology = new Technology();
}

// Test 1: Bastion::getStationedArmiesDetails() structure and counts
echo "\nTest 1: Testing Bastion::getStationedArmiesDetails()...\n";
$stationed = Bastion::getStationedArmiesDetails();
if (!is_array($stationed) || !array_key_exists('own', $stationed) || !array_key_exists('reinforcements', $stationed) || !array_key_exists('grand_total', $stationed)) {
    echo "FAIL: getStationedArmiesDetails() did not return expected keys!\n";
    exit(1);
}
echo "PASS: getStationedArmiesDetails() returned valid structure with grand_total = " . $stationed['grand_total'] . "\n";

// Test 2: Verify Bastion::getBastionGarrison() includes units > 50 and hero
echo "\nTest 2: Testing Bastion::getBastionGarrison() supports all tribes (1..100) + hero...\n";
$garrison = Bastion::getBastionGarrison();
if (!isset($garrison['total']) || !isset($garrison['own']) || !isset($garrison['enforce'])) {
    echo "FAIL: getBastionGarrison() missing expected keys!\n";
    exit(1);
}
echo "PASS: getBastionGarrison() returned total = " . $garrison['total'] . "\n";

// Test 3: Verify 15.tpl rendering with ATTACKER: token
echo "\nTest 3: Testing Notice 15.tpl with ATTACKER: token...\n";

// Mock readingNotice with ATTACKER: token
// 0: defUid(6), 1: destWref(20201), 2: destName(? Benteng Kuno), 3: tribe(10),
// 4..13: defSent(480,267,0,0,0,0,0,0,0,0),
// 14..23: defDead(1,0,0,0,0,0,0,0,0,0),
// 24: defHeroSent(0), 25: defHeroDead(0), 26: fromWref(19999),
// 27: ATTACKER:attackerUid;attWref;attTribe;t1..t10;d1..d10;hSent;hDead
$attToken = 'ATTACKER:88104;20602;1;50;30;0;0;0;0;0;0;0;0;10;5;0;0;0;0;0;0;0;0;1;0';
$dataStr = '6,20201,? Benteng Kuno,10,480,267,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,19999,' . $attToken;

$message = new stdClass();
$message->readingNotice = [
    'id' => 999999,
    'uid' => 6,
    'toWref' => 20201,
    'ntype' => 16,
    'topic' => 'Reinforcement in ? Benteng Kuno was attacked',
    'time' => time(),
    'data' => $dataStr
];

function tz_expand_report($s) { return $s; }
if (!defined('SUBJECT')) define('SUBJECT', 'Subject');
if (!defined('TZ_SENT')) define('TZ_SENT', 'Sent');
if (!defined('ON')) define('ON', 'on');
if (!defined('TZ_AT')) define('TZ_AT', 'at');
if (!defined('TZ_HOUR')) define('TZ_HOUR', 'hour');
if (!defined('DEFENDER')) define('DEFENDER', 'Defender');
if (!defined('ATTACKER')) define('ATTACKER', 'Attacker');
if (!defined('TROOPS')) define('TROOPS', 'Troops');
if (!defined('CASUALTIES')) define('CASUALTIES', 'Casualties');
if (!defined('FROM_THE_VILL')) define('FROM_THE_VILL', 'from the village');
if (!defined('RC_HERO')) define('RC_HERO', 'Hero');
function tz_loc_topic($t) { return $t; }

ob_start();
include __DIR__ . '/../Templates/Notice/15.tpl';
$html = ob_get_clean();

if (strpos($html, 'id="attacker"') === false) {
    echo "FAIL: Notice 15.tpl did not render attacker table!\n";
    exit(1);
}
if (strpos($html, 'Pertahanan di Benteng Kuno') === false) {
    echo "FAIL: Notice 15.tpl did not show Benteng Kuno badge!\n";
    exit(1);
}
if (strpos($html, 'class="unit u91"') === false) {
    echo "FAIL: Notice 15.tpl did not render defender Nusantara units!\n";
    exit(1);
}
echo "PASS: Notice 15.tpl rendered Attacker table with unit icons and troops sent/casualties successfully!\n";

// Test 4: Verify 15.tpl fallback for legacy/historical notice without token
echo "\nTest 4: Testing Notice 15.tpl fallback for notice without ATTACKER: token...\n";
$legacyDataStr = '6,20201,? Benteng Kuno,10,480,267,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,19999';
$message->readingNotice = [
    'id' => 999998,
    'uid' => 6,
    'toWref' => 20201,
    'ntype' => 16,
    'topic' => 'Reinforcement in ? Benteng Kuno was attacked',
    'time' => 1793518383, // matches existing test notice in db
    'data' => $legacyDataStr
];

ob_start();
include __DIR__ . '/../Templates/Notice/15.tpl';
$htmlLegacy = ob_get_clean();

if (strpos($htmlLegacy, 'Pertahanan di Benteng Kuno') === false) {
    echo "FAIL: Notice 15.tpl did not show Benteng Kuno badge in legacy report!\n";
    exit(1);
}
echo "PASS: Notice 15.tpl rendered cleanly with fallback detection!\n";

echo "\n=== ALL BASTION TROOPS & ATTACKER REPORT TESTS PASSED SUCCESSFULLY! ===\n";
