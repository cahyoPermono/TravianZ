<?php

#################################################################################
##  Filename       : tests/BattleReportScoutLeakTest.php                       ##
##  Type           : Unit Test for Scout Report Isolation and Leak Prevention  ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';

global $generator, $database, $technology;
if (!isset($generator)) {
    $generator = new MyGenerator();
}
if (!isset($technology)) {
    $technology = new Technology();
}

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertFalse($condition, $message) {
    if ($condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running BattleReportScoutLeakTest ===" . PHP_EOL;

// =============================================================================
// TEST 1: Attack Report with Leaked Scout Data in 1.tpl must NOT show Scout Info
// =============================================================================
echo "Test 1: Attack report containing leaked scout data must suppress scout info in 1.tpl..." . PHP_EOL;

$spyHtml = '<div class="res"><img class="r1" src="img/x.gif" alt="Lumber" title="Lumber" />17812 | <img class="r2" src="img/x.gif" alt="Clay" title="Clay" />899 | <img class="r3" src="img/x.gif" alt="Iron" title="Iron" />34955 | <img class="r4" src="img/x.gif" alt="Crop" title="Crop" />45700</div><div class="carry">Total Resources: 99365</div>';

// Construct simulated combat report data string (Attack: 49 Swordsmen + 1 TT)
$dummyCombatReport = array_fill(0, 290, '0');
$dummyCombatReport[0] = '1'; // uid
$dummyCombatReport[1] = '1'; // vid
$dummyCombatReport[2] = '3'; // Gaul tribe
$dummyCombatReport[4] = '49'; // 49 Swordsmen (u22)
$dummyCombatReport[6] = '1';  // 1 Theutates Thunder (u24)
// Leaked scout data into indices 270 and 271 from previous scout iteration:
$dummyCombatReport[270] = '23'; // spy_pic (Pathfinder)
$dummyCombatReport[271] = $spyHtml; // spy_data
$dummyCombatReport[272] = '0'; // t11 (Hero Sent = 0)
$dummyCombatReport[273] = '0'; // dead11 (Hero Dead = 0)

$message = new stdClass();
$message->readingNotice = array(
    'id' => 12345,
    'uid' => 1,
    'to' => 1,
    'topic' => 'Gergovia attacks Infinite Loop',
    'ntype' => 1, // Standard Won Attack
    'data' => implode(',', $dummyCombatReport),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
);

$session = new stdClass();
$session->access = 0;
$session->uid = 1;

ob_start();
include dirname(__DIR__) . '/Templates/Notice/1.tpl';
$renderedHtml = ob_get_clean();

// The leaked scout resources MUST NOT appear in the rendered combat report
assertFalse(strpos($renderedHtml, 'Total Resources: 99365'), "Combat report must NOT show leaked scout Total Resources");
assertFalse(strpos($renderedHtml, '17812'), "Combat report must NOT show leaked scout lumber 17812");
assertFalse(strpos($renderedHtml, '34955'), "Combat report must NOT show leaked scout iron 34955");
assertFalse(strpos($renderedHtml, '45700'), "Combat report must NOT show leaked scout crop 45700");
assertFalse(strpos($renderedHtml, '<img class="unit u23" src="img/x.gif" alt="" />'), "Combat report must NOT show leaked scout info icon u23 in Information row");

echo "PASS: Leaked scout data is completely ignored and hidden in combat/raid reports." . PHP_EOL;

// =============================================================================
// TEST 2: Legitimate Scout Report must still show Scout Info correctly
// =============================================================================
echo "Test 2: Legitimate scout report must display scout info in 1.tpl..." . PHP_EOL;

$message->readingNotice = array(
    'id' => 12346,
    'uid' => 1,
    'to' => 1,
    'topic' => 'Gergovia scouts Infinite Loop',
    'ntype' => 18, // Legitimate Scout Report
    'data' => implode(',', $dummyCombatReport),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
);

ob_start();
include dirname(__DIR__) . '/Templates/Notice/1.tpl';
$scoutHtml = ob_get_clean();

assertTrue(strpos($scoutHtml, 'Total Resources: 99365') !== false, "Legitimate scout report must display Total Resources: 99365");
assertTrue(strpos($scoutHtml, '17812') !== false, "Legitimate scout report must display lumber 17812");
assertTrue(strpos($scoutHtml, 'unit u23') !== false, "Legitimate scout report must display scout unit icon u23");

echo "PASS: Legitimate scout reports correctly display scout information." . PHP_EOL;

// =============================================================================
// TEST 3: AutomationBattleResolution buildCombatReport Scout Isolation
// =============================================================================
echo "Test 3: buildCombatReport must exclude info_spy when scout is false/empty..." . PHP_EOL;

require_once dirname(__DIR__) . '/GameEngine/Automation.php';
$automation = new Automation();

$reflector = new ReflectionClass($automation);
$method = $reflector->getMethod('buildCombatReport');
$method->setAccessible(true);

$from = ['owner' => 1, 'wref' => 100, 'name' => 'Village A'];
$to   = ['owner' => 2, 'wref' => 200, 'name' => 'Village B'];
$owntribe = 3; // Gaul
$targettribe = 1; // Roman
$unitssend_att = '0,49,0,1,0,0,0,0,0,0';
$unitsdead_att = '0,41,0,1,0,0,0,0,0,0';
$steal = [120, 0, 120, 120];
$battlepart = ['bounty' => 360];
$unitssend_def = array_fill(0, 11, '0,0,0,0,0,0,0,0,0,0');
$unitsdead_def = array_fill(0, 11, '0,0,0,0,0,0,0,0,0,0');
$unitssend_deff = array_fill(0, 11, '0,0,0,0,0,0,0,0,0,0');
$unitsdead_deff = array_fill(0, 11, '0,0,0,0,0,0,0,0,0,0');
$DefenderHeroesTot = 0;
$DefenderHeroesDead = 0;
$info_ram = ',';
$info_cat = ',';
$info_chief = ',';
$info_spy = '23,' . $spyHtml; // simulated leaked spy info
$info_trap = '';
$info_hero = '';
$info_troop = '';
$data = ['t11' => 0];
$dead11 = 0;
$herosend_def = 0;
$deadhero = 0;
$unitstraped_att = '0,0,0,0,0,0,0,0,0,0';
$village_destroyed = 0;
$can_destroy = 0;
$catp_pic = 28;

// Call with $scout = 0 (attack/raid) even when $info_spy is passed
$resultCombat = $method->invoke(
    $automation,
    0, // $scout = 0
    $from, $to, $owntribe, $targettribe,
    $unitssend_att, $unitsdead_att, $steal, $battlepart,
    $unitssend_def, $unitsdead_def, $unitssend_deff, $unitsdead_deff,
    0, 0, 0, 0, 0, 0, 0, 0, 0,
    $DefenderHeroesTot, $DefenderHeroesDead,
    $info_ram, $info_cat, $info_chief, $info_spy,
    $info_trap, $info_hero, $info_troop,
    $data, $dead11, $herosend_def, $deadhero, $unitstraped_att,
    $village_destroyed, $can_destroy, $catp_pic
);

assertFalse(strpos($resultCombat['data2'], 'Total Resources: 99365'), "buildCombatReport data2 must NOT contain info_spy when scout=0");
assertFalse(strpos($resultCombat['data2'], '<div class="res">'), "buildCombatReport data2 must NOT contain res div when scout=0");

// Call with $scout = 1 (scouting)
$resultScout = $method->invoke(
    $automation,
    1, // $scout = 1
    $from, $to, $owntribe, $targettribe,
    $unitssend_att, $unitsdead_att, $steal, $battlepart,
    $unitssend_def, $unitsdead_def, $unitssend_deff, $unitsdead_deff,
    0, 0, 0, 0, 0, 0, 0, 0, 0,
    $DefenderHeroesTot, $DefenderHeroesDead,
    $info_ram, $info_cat, $info_chief, $info_spy,
    $info_trap, $info_hero, $info_troop,
    $data, $dead11, $herosend_def, $deadhero, $unitstraped_att,
    $village_destroyed, $can_destroy, $catp_pic
);

assertTrue(strpos($resultScout['data2'], 'Total Resources: 99365') !== false, "buildCombatReport data2 must contain info_spy when scout=1");

echo "PASS: buildCombatReport strictly isolates info_spy to scout missions only." . PHP_EOL;

echo "=== All BattleReportScoutLeakTest tests passed! ===" . PHP_EOL;
