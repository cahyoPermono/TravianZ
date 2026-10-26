<?php

#################################################################################
##  Filename       : BotScoutAndTroopScalingTest.php                          ##
##  Type           : Unit Test for Bot Scout Usage and High-Pop Troop Scaling   ##
#################################################################################

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('SPEED')) define('SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);
if (!defined('BOT_AI_SCOUT_CHANCE')) define('BOT_AI_SCOUT_CHANCE', 100);
if (!defined('BOT_AI_ATTACK_START_HOUR')) define('BOT_AI_ATTACK_START_HOUR', 0);
if (!defined('BOT_AI_ATTACK_END_HOUR')) define('BOT_AI_ATTACK_END_HOUR', 24);

require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n");
        exit(1);
    }
}

echo "=== Running BotScoutAndTroopScalingTest ===\n";

// TEST 1: Population 800 Troop Scaling & Home Reserve
echo "Test 1: Testing pop 800 troop capacity and home reserve...\n";
$pop800 = 800;
$reserve800 = BotAI::getHomeReserve($pop800);
assertEqual(235, $reserve800, "Pop 800 home reserve must be 235");

$popFactor = (float)BotAI::getConfig('BOT_AI_TROOP_POP_FACTOR', 1.25);
assertEqual(1.25, $popFactor, "BOT_AI_TROOP_POP_FACTOR should default to 1.25");

$popArmyCap800 = (int)round($pop800 * $popFactor);
assertEqual(1000, $popArmyCap800, "Pop 800 should have army cap of 1000");
echo "-> PASS: Pop 800 scales naturally to 235 defenders and 1000 total army capacity!\n";

// TEST 2: Scout unit identification across all tribes
echo "Test 2: Testing scout unit identification for all tribes...\n";
$scouts = [
    1 => 4,   // Roman Equites Legati
    2 => 14,  // Teuton Scout
    3 => 23,  // Gaul Pathfinder
    6 => 52,  // Hun Spotter
    7 => 64,  // Egyptian Sofi
    8 => 74,  // Spartan Kryptes
    9 => 82,  // Viking Thrall Scout
    10 => 94, // Nusantara Telik Sandi
];
foreach ($scouts as $tribe => $expectedScout) {
    $info = BotAI::getTribeUnits($tribe);
    assertEqual($expectedScout, $info['scout'], "Tribe $tribe scout unit should be $expectedScout");
}
echo "-> PASS: All tribe scout unit IDs verified!\n";

// TEST 3: Mounted vs Foot Scout Classification
echo "Test 3: Testing mounted vs foot scout classification...\n";
assertTrue(BotAI::isMountedScout(1), "Roman is mounted scout (Stable)");
assertTrue(!BotAI::isMountedScout(2), "Teuton is foot scout (Barracks)");
assertTrue(BotAI::isMountedScout(3), "Gaul is mounted scout (Stable)");
assertTrue(BotAI::isMountedScout(6), "Hun is mounted scout (Stable)");
assertTrue(BotAI::isMountedScout(7), "Egyptian is mounted scout (Stable)");
assertTrue(!BotAI::isMountedScout(8), "Spartan is foot scout (Barracks)");
assertTrue(!BotAI::isMountedScout(9), "Viking is foot scout (Barracks)");
assertTrue(BotAI::isMountedScout(10), "Nusantara is mounted scout (Stable)");
echo "-> PASS: Mounted vs Foot classification correct for all tribes!\n";

// TEST 4: processScout() Dispatching and Counter-Espionage Preservation
echo "Test 4: Testing processScout() execution, home reserve protection, and dispatch...\n";

class MockScoutDatabase {
    public $units = [];
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function getCoor($wid) {
        if ($wid === 201) return ['x' => 10, 'y' => 10];
        if ($wid === 301) return ['x' => 12, 'y' => 12];
        return ['x' => 0, 'y' => 0];
    }

    public function query_return($sql) {
        // Query standing scouts
        if (strpos($sql, "SELECT u4 FROM") !== false) {
            return [$this->units];
        }

        // Active outgoing scouts
        if (strpos($sql, "a.attack_type = 1") !== false) {
            return [['cnt' => 0]];
        }

        // Nearby targets query
        if (strpos($sql, "FROM " . TB_PREFIX . "vdata v") !== false) {
            return [[
                'wref' => 301,
                'vname' => 'Enemy Village',
                'pop' => 350,
                'uid' => 99,
                'username' => 'TargetPlayer',
                'alliance' => 0,
                'access' => 2,
                'protect' => 0,
                'x' => 12,
                'y' => 12,
            ]];
        }

        // Oases query
        if (strpos($sql, "FROM " . TB_PREFIX . "wdata w") !== false && strpos($sql, "odata") !== false) {
            return [];
        }

        return [];
    }

    public function hasBeginnerProtection($wref) {
        return false;
    }

    public function modifyUnit($wid, $unitNums, $amounts, $modes) {
        $this->modifiedUnits[] = [
            'wid' => $wid,
            'unitNums' => $unitNums,
            'amounts' => $amounts,
            'modes' => $modes,
        ];
    }

    public function addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $ctar1, $ctar2, $spy) {
        $id = count($this->addedAttacks) + 1;
        $this->addedAttacks[] = [
            'id' => $id,
            'vid' => $vid,
            't' => [$t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11],
            'type' => $type,
            'spy' => $spy,
        ];
        return $id;
    }

    public function addMovement($type, $from, $to, $ref, $starttime, $endtime) {
        $this->addedMovements[] = [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'ref' => $ref,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];
    }
}

global $database;
$database = new MockScoutDatabase();

// Roman bot with pop 656. Home scout reserve should be 8.
// If bot only has 6 scouts, deployable = max(0, 6 - 8) = 0.
$database->units = ['u4' => 6];
$botRow = [
    'wref' => 201,
    'uid' => 55,
    'tribe' => 1,
    'pop' => 656,
    'alliance' => 0,
];

// Force day time for test
if (!defined('BOT_AI_ATTACK_START_HOUR')) define('BOT_AI_ATTACK_START_HOUR', 0);
if (!defined('BOT_AI_ATTACK_END_HOUR')) define('BOT_AI_ATTACK_END_HOUR', 24);

$resultNotEnough = BotAI::processScout($botRow);
assertEqual(null, $resultNotEnough, "processScout must NOT dispatch when scouts <= homeScoutReserve");
assertEqual(0, count($database->addedAttacks), "No attack record should be created when scouts insufficient");

// Now give bot 20 scouts. Deployable = 20 - 8 = 12 scouts.
$database->units = ['u4' => 20];
$resultDispatched = BotAI::processScout($botRow);
assertTrue($resultDispatched !== null, "processScout should dispatch when sufficient scouts are available");
assertEqual(1, count($database->addedAttacks), "1 scout attack record created");

$attackRecord = $database->addedAttacks[0];
assertEqual(1, $attackRecord['type'], "Attack type must be 1 (Spy / Scouting)");
assertTrue(in_array($attackRecord['spy'], [1, 2]), "Spy mode must be 1 or 2");
assertEqual(201, $attackRecord['vid'], "Attack origin must be bot village 201");
// u4 (Roman scout) is slot 4
assertTrue($attackRecord['t'][3] >= 2 && $attackRecord['t'][3] <= 5, "Dispatched between 2 and 5 Equites Legati");

assertEqual(1, count($database->addedMovements), "1 movement record created");
assertEqual(3, $database->addedMovements[0]['type'], "Movement sort_type must be 3 (Attack/Scout)");
assertEqual(201, $database->addedMovements[0]['from'], "Movement from bot village");
assertEqual(301, $database->addedMovements[0]['to'], "Movement to target village");

echo "-> PASS: processScout successfully dispatched reconnaissance with attack_type = 1 and preserved home counter-espionage!\n";

echo "\n=== ALL BotScoutAndTroopScalingTest TESTS PASSED SUCCESSFULLY! ===\n";
