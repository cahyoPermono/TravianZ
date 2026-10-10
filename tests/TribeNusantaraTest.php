<?php

#################################################################################
##  Filename       : TribeNusantaraTest.php                                    ##
##  Type           : Unit & Integration Test for Suku Nusantara (Tribe 10)     ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/loader.php';
tz_load_language('en');
require_once dirname(__DIR__) . '/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__) . '/GameEngine/Data/resdata.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';
require_once dirname(__DIR__) . '/GameEngine/Units.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';

function assertTrue($condition, $message) {
    if (!$condition) {
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

echo "Testing Suku Nusantara (Tribe 10) Integration..." . PHP_EOL;

// 1. Feature Flag
assertTrue(defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA === true, "NEW_FUNCTION_TRIBE_NUSANTARA must be enabled");

// 2. Language Constants
assertTrue(defined('TRIBE10') && TRIBE10 === 'Nusantara', "TRIBE10 must be defined as 'Nusantara'");
assertTrue(defined('U99_TRAP') && U99_TRAP === 'Trap', "U99_TRAP must be defined as 'Trap'");

$expectedUnitNames = [
    91 => 'Pendekar Keris',
    92 => 'Prajurit Tombak',
    93 => 'Pemanah Busur Gendewa',
    94 => 'Telik Sandi',
    95 => 'Kavaleri Berkuda',
    96 => 'Gajah Perang Bhayangkara',
    97 => 'Cetbang Pemecah Benteng',
    98 => 'Meriam Kalantaka',
    99 => 'Senapati Palapa',
    100 => 'Pemukim Bahari',
];

foreach ($expectedUnitNames as $idx => $name) {
    $const = 'U' . $idx;
    assertTrue(defined($const), "Constant $const must be defined");
    assertEqual($name, constant($const), "Constant $const value mismatch");
}

// 3. Unit Data 91-100
for ($u = 91; $u <= 100; $u++) {
    $var = 'u' . $u;
    if ($u == 99) {
        global $u99_nusantara, $u99_trap;
        assertTrue(isset($u99_nusantara) && is_array($u99_nusantara), "u99_nusantara must exist as array");
        assertTrue(isset($u99_trap) && is_array($u99_trap), "u99_trap must exist as array");
        assertEqual(45, $u99_nusantara['atk'], "u99_nusantara atk mismatch");
        assertEqual(0, $u99_trap['atk'], "u99_trap atk mismatch");
    } else {
        global ${$var};
        assertTrue(isset(${$var}) && is_array(${$var}), "$var must exist as array");
        assertTrue(isset(${$var}['atk']), "$var must have 'atk'");
        assertTrue(isset(${$var}['pop']), "$var must have 'pop'");
        assertTrue(isset(${$var}['time']), "$var must have 'time'");
    }
}

// 4. Units by Type categorization
global $unitsbytype;
assertTrue(in_array(91, $unitsbytype['infantry']), "Unit 91 must be infantry");
assertTrue(in_array(92, $unitsbytype['infantry']), "Unit 92 must be infantry");
assertTrue(in_array(93, $unitsbytype['infantry']), "Unit 93 must be infantry");
assertTrue(in_array(94, $unitsbytype['cavalry']), "Unit 94 must be cavalry");
assertTrue(in_array(95, $unitsbytype['cavalry']), "Unit 95 must be cavalry");
assertTrue(in_array(96, $unitsbytype['cavalry']), "Unit 96 must be cavalry");
assertTrue(in_array(97, $unitsbytype['siege']), "Unit 97 must be siege");
assertTrue(in_array(98, $unitsbytype['siege']), "Unit 98 must be siege");
assertTrue(in_array(99, $unitsbytype['expansion']), "Unit 99 must be expansion");
assertTrue(in_array(100, $unitsbytype['expansion']), "Unit 100 must be expansion");
assertTrue(in_array(94, $unitsbytype['scout']), "Unit 94 must be scout");
assertTrue(in_array(99, $unitsbytype['chief']), "Unit 99 must be chief");

// 5. Research & Upgrade Data
for ($u = 92; $u <= 99; $u++) {
    $var = 'r' . $u;
    global ${$var};
    assertTrue(isset(${$var}) && is_array(${$var}), "$var research cost must exist");
}
for ($u = 91; $u <= 98; $u++) {
    $var = 'ab' . $u;
    global ${$var};
    assertTrue(isset(${$var}) && is_array(${$var}), "$var armoury cost must exist");
    assertEqual(20, count(${$var}), "$var must have 20 upgrade levels");
}

// 6. Hero Stats
global $h91, $h92, $h93, $h95, $h96;
assertTrue(isset($h91) && is_array($h91), "h91 must exist");
assertTrue(isset($h92) && is_array($h92), "h92 must exist");
assertTrue(isset($h93) && is_array($h93), "h93 must exist");
assertTrue(isset($h95) && is_array($h95), "h95 must exist");
assertTrue(isset($h96) && is_array($h96), "h96 must exist");

// 7. BotAI lookups
assertEqual(32, BotAI::getTribeWallType(10), "BotAI wall type for Tribe 10 must be 32 (Benteng Kedaton)");
assertEqual(91, BotAI::getTribeBasicUnit(10), "BotAI basic unit for Tribe 10 must be 91 (Pendekar Keris)");
assertEqual(7, BotAI::getUnitSpeed(91), "BotAI unit speed for unit 91 must be 7");

// 8. Technology unit naming & trap collision resolution
global $technology;
$tech = new Technology();
assertEqual('Pendekar Keris', $tech->getUnitName(91), "Tech getUnitName(91) must be Pendekar Keris");
assertEqual('Pemukim Bahari', $tech->getUnitName(100), "Tech getUnitName(100) must be Pemukim Bahari");

// When session tribe is Gaul (3), unit 99 must be 'Trap'
global $session;
$session = new stdClass();
$session->tribe = 3;
assertEqual('Trap', $tech->getUnitName(99), "Gaul unit 99 name must be Trap");

// When session tribe is Nusantara (10), unit 99 must be 'Senapati Palapa'
$session->tribe = 10;
assertEqual('Senapati Palapa', $tech->getUnitName(99), "Nusantara unit 99 name must be Senapati Palapa");

// 9. Hero full revival data
require_once dirname(__DIR__) . '/GameEngine/Data/hero_full.php';
global $h91_full, $h92_full, $h93_full, $h95_full, $h96_full;
assertTrue(isset($h91_full) && count($h91_full) >= 60, "h91_full must have >= 60 revival levels");
assertTrue(isset($h92_full) && count($h92_full) >= 60, "h92_full must have >= 60 revival levels");
assertTrue(isset($h93_full) && count($h93_full) >= 60, "h93_full must have >= 60 revival levels");
assertTrue(isset($h95_full) && count($h95_full) >= 60, "h95_full must have >= 60 revival levels");
assertTrue(isset($h96_full) && count($h96_full) >= 60, "h96_full must have >= 60 revival levels");

// 10. Templates check
assertTrue(file_exists(dirname(__DIR__) . '/Templates/Build/22_10.tpl'), "22_10.tpl must exist");
assertTrue(file_exists(dirname(__DIR__) . '/Templates/a2b/units_10.tpl'), "units_10.tpl must exist");

// 11. Technology getTrainingList check
global $database, $village;
$village = new stdClass();
$village->wid = 12345;

$fakeDb = new class {
    public $sampleTraining = [];
    public function getTraining($wid) {
        return $this->sampleTraining;
    }
    public function getVillageField($wid, $field) {
        return 1;
    }
    public function getUserField($uid, $field, $mode) {
        return 10;
    }
};
$database = $fakeDb;

$session->tribe = 10;
$fakeDb->sampleTraining = [
    ['id' => 1, 'vref' => 12345, 'unit' => 91, 'amt' => 10, 'pop' => 1, 'timestamp' => time() + 300, 'eachtime' => 30, 'timestamp2' => time() + 30],
    ['id' => 2, 'vref' => 12345, 'unit' => 92, 'amt' => 5, 'pop' => 1, 'timestamp' => time() + 500, 'eachtime' => 40, 'timestamp2' => time() + 340],
];
$barracksList = $tech->getTrainingList(1);
assertEqual(2, count($barracksList), "Barracks training list must contain Nusantara units 91 and 92");
assertEqual('Pendekar Keris', $barracksList[0]['name'], "Unit 91 name in training list must be Pendekar Keris");

$fakeDb->sampleTraining = [
    ['id' => 3, 'vref' => 12345, 'unit' => 95, 'amt' => 2, 'pop' => 2, 'timestamp' => time() + 600, 'eachtime' => 300, 'timestamp2' => time() + 300],
];
$stablesList = $tech->getTrainingList(2);
assertEqual(1, count($stablesList), "Stables training list must contain Nusantara unit 95");

$fakeDb->sampleTraining = [
    ['id' => 4, 'vref' => 12345, 'unit' => 99, 'amt' => 1, 'pop' => 4, 'timestamp' => time() + 1000, 'eachtime' => 1000, 'timestamp2' => time() + 1000],
    ['id' => 5, 'vref' => 12345, 'unit' => 100, 'amt' => 3, 'pop' => 1, 'timestamp' => time() + 2000, 'eachtime' => 600, 'timestamp2' => time() + 600],
];
$residenceList = $tech->getTrainingList(4);
assertEqual(2, count($residenceList), "Residence training list for Tribe 10 must contain unit 99 and 100");
assertEqual('Senapati Palapa', $residenceList[0]['name'], "Unit 99 in residence must be Senapati Palapa");

echo "PASS: All Suku Nusantara (Tribe 10) integration tests passed successfully!" . PHP_EOL;
