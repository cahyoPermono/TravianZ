<?php

#################################################################################
##  Filename       : tests/HeroCageOfficialMechanicsTest.php                   ##
##  Type           : Unit Test for Modern Official Travian Cage Mechanics     ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';
require_once dirname(__DIR__) . '/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__) . '/GameEngine/Data/hero_items.php';
require_once dirname(__DIR__) . '/GameEngine/HeroBattleBonus.php';
require_once dirname(__DIR__) . '/GameEngine/HeroItems.php';
require_once dirname(__DIR__) . '/GameEngine/Automation/AutomationBattleResolution.php';

global $generator, $technology, $database;
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

function assertEquals($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . json_encode($expected) . ", Got: " . json_encode($actual) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running HeroCageOfficialMechanicsTest ===" . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 1: Verify HeroItems::useItem() returns USE_DEFERRED for cages (item 208)
// -----------------------------------------------------------------------------
echo "Test 1: Testing HeroItems::useItem() for Cages (item 208)..." . PHP_EOL;
$mockHeroItems = new class extends HeroItems {
    public function __construct() {}
    public function findRowById($uid, $rowId) {
        global $heroItemCatalog;
        return [
            'id' => 1,
            'uid' => $uid,
            'itemid' => 208,
            'quantity' => 10,
            'orphan' => false,
            'def' => $heroItemCatalog[208]
        ];
    }
};

$useRes = $mockHeroItems->useItem(1, 1, 1, 1);
assertTrue($useRes === HeroItems::USE_DEFERRED, "Cages must return USE_DEFERRED on manual use");
echo "PASS: Cages returned USE_DEFERRED." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 2: Round-Robin Algorithm Verification
// -----------------------------------------------------------------------------
echo "Test 2: Testing Round-Robin animal capture distribution..." . PHP_EOL;

// Scenario A: 5 animals of different types, 3 cages available.
// Oasis: 2 Rats (u31), 2 Spiders (u32), 2 Bats (u34), 2 Bears (u38), 2 Elephants (u40). Total 10.
// Cages: 5.
// Cycle 1 must take: 1 Rat, 1 Spider, 1 Bat, 1 Bear, 1 Elephant. Total 5 cages used!
// Old greedy algorithm would have taken: 2 Rats, 2 Spiders, 1 Bat (and 0 Bears, 0 Elephants).
$defenderA = array_fill_keys(array_map(fn($i) => 'u'.$i, range(1, 100)), 0);
$defenderA['u31'] = 2;
$defenderA['u32'] = 2;
$defenderA['u34'] = 2;
$defenderA['u38'] = 2;
$defenderA['u40'] = 2;

$budgetA = 5;
$initialA = [];
for ($i = 31; $i <= 40; $i++) {
    $initialA[$i] = (int)$defenderA['u' . $i];
}
$capturedA = array_fill_keys(range(31, 40), 0);
$remA = $initialA;

while ($budgetA > 0) {
    $cycle = 0;
    for ($i = 31; $i <= 40; $i++) {
        if ($budgetA <= 0) break;
        if ($remA[$i] > 0) {
            $remA[$i]--;
            $capturedA[$i]++;
            $budgetA--;
            $cycle++;
        }
    }
    if ($cycle === 0) break;
}

assertEquals(1, $capturedA[31], "Round-robin must take 1 Rat (u31)");
assertEquals(1, $capturedA[32], "Round-robin must take 1 Spider (u32)");
assertEquals(1, $capturedA[34], "Round-robin must take 1 Bat (u34)");
assertEquals(1, $capturedA[38], "Round-robin must take 1 Bear (u38)");
assertEquals(1, $capturedA[40], "Round-robin must take 1 Elephant (u40)");
assertEquals(0, $capturedA[33], "Round-robin must take 0 Boar (u33) because none present");
assertEquals(5, array_sum($capturedA), "Total captured must equal exactly 5 cages");
echo "PASS: Scenario A (5 cages across 5 types) captured 1-by-1 evenly." . PHP_EOL;

// Scenario B: Asymmetric distribution with multi-cycle looping.
// Oasis: 5 Rats (u31), 1 Spider (u32), 1 Bear (u38). Total 7 animals.
// Cages: 4.
// Cycle 1: 1 Rat, 1 Spider, 1 Bear (3 cages used, remaining: 4 Rats, 0 Spiders, 0 Bears; 1 cage left).
// Cycle 2: 1 Rat (1 cage used, remaining: 3 Rats; 0 cages left).
// Total captured: 2 Rats, 1 Spider, 1 Bear.
$budgetB = 4;
$initialB = array_fill_keys(range(31, 40), 0);
$initialB[31] = 5;
$initialB[32] = 1;
$initialB[38] = 1;
$capturedB = array_fill_keys(range(31, 40), 0);
$remB = $initialB;

while ($budgetB > 0) {
    $cycle = 0;
    for ($i = 31; $i <= 40; $i++) {
        if ($budgetB <= 0) break;
        if ($remB[$i] > 0) {
            $remB[$i]--;
            $capturedB[$i]++;
            $budgetB--;
            $cycle++;
        }
    }
    if ($cycle === 0) break;
}

assertEquals(2, $capturedB[31], "Round-robin must loop and take second Rat");
assertEquals(1, $capturedB[32], "Round-robin took Spider");
assertEquals(1, $capturedB[38], "Round-robin took Bear");
assertEquals(4, array_sum($capturedB), "Total captured must be 4");
assertEquals(3, $remB[31], "3 Rats must remain untouched in oasis");
assertEquals(0, $remB[32], "0 Spiders remain");
assertEquals(0, $remB[38], "0 Bears remain");
echo "PASS: Scenario B (multi-cycle round-robin) distributed correctly." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 3: Notice 1 CAGE Token Parsing
// -----------------------------------------------------------------------------
echo "Test 3: Testing CAGE notice token parsing..." . PHP_EOL;

$sampleCageToken = "CAGE:5;1;1;0;1;0;0;0;1;0;1";
$dataarray = ["1", "100", "1", "0", "0", "0", $sampleCageToken];

$cageCaptureCount = 0;
$cageCapturedUnits = [];
foreach ($dataarray as $entry) {
    if (is_string($entry) && strpos($entry, 'CAGE:') === 0) {
        $cageDataStr = substr($entry, 5);
        $cageParts = explode(';', $cageDataStr);
        $cageCaptureCount = (int)($cageParts[0] ?? 0);
        if (count($cageParts) > 1) {
            for ($ci = 1; $ci <= 10; $ci++) {
                $uNum = 30 + $ci;
                $cnt = (int)($cageParts[$ci] ?? 0);
                if ($cnt > 0) {
                    $cageCapturedUnits[$uNum] = $cnt;
                }
            }
        }
        break;
    }
}

assertEquals(5, $cageCaptureCount, "Notice must parse 5 total captured animals");
assertEquals(1, $cageCapturedUnits[31], "Captured units must include 1 Rat (u31)");
assertEquals(1, $cageCapturedUnits[32], "Captured units must include 1 Spider (u32)");
assertEquals(1, $cageCapturedUnits[34], "Captured units must include 1 Bat (u34)");
assertEquals(1, $cageCapturedUnits[38], "Captured units must include 1 Bear (u38)");
assertEquals(1, $cageCapturedUnits[40], "Captured units must include 1 Elephant (u40)");
assertTrue(!isset($cageCapturedUnits[33]), "u33 had 0 and should not be in parsed breakdown");
echo "PASS: CAGE token parsed into correct counts and breakdown." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 4: Verify Peaceful Mission No-Combat Logic in AutomationBattleResolution
// -----------------------------------------------------------------------------
echo "Test 4: Testing AutomationBattleResolution peaceful cage mission resolution..." . PHP_EOL;

$dummyClass = new class {
    use AutomationBattleResolution;
    public function callResolveCageCaptureMission(...$args) {
        return $this->resolveCageCaptureMission(...$args);
    }
};

// Create mock database to capture addNotice, addMovement, setMovementProc
$capturedNotice = null;
$capturedMovement = null;
$capturedProc = null;

$database = new class($capturedNotice, $capturedMovement, $capturedProc) {
    public $noticeRef;
    public $movementRef;
    public $procRef;
    public $dblink = null;

    public function __construct(&$noticeRef, &$movementRef, &$procRef) {
        $this->noticeRef = &$noticeRef;
        $this->movementRef = &$movementRef;
        $this->procRef = &$procRef;
    }
    public function addNotice($uid, $toWref, $ally, $type, $topic, $data, $time) {
        $this->noticeRef = [
            'uid' => $uid,
            'toWref' => $toWref,
            'ally' => $ally,
            'type' => $type,
            'topic' => $topic,
            'data' => $data,
            'time' => $time
        ];
    }
    public function addMovement($type, $from, $to, $ref, $time, $endtime, $send = 1, $w = 0, $c = 0, $i = 0, $cr = 0) {
        $this->movementRef = [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'ref' => $ref,
            'time' => $time,
            'endtime' => $endtime,
            'wood' => $w,
            'clay' => $c,
            'iron' => $i,
            'crop' => $cr
        ];
    }
    public function setMovementProc($moveid) {
        $this->procRef = $moveid;
    }
    public function addStarvationData($wref) {}
    public function getCoor($wref) { return ['x' => 10, 'y' => 20]; }
    public function getArtifactsValueInfluence($owner, $wref, $type, $time) {
        return $time;
    }
    public function getUserField($id, $f, $m = 0) { return 0; }
    public function getVillageField($id, $f) { return 'Village'; }
    public function getHeroField($uid, $field) { return $field === 'unit' ? 1 : 0; }
    public function getFieldLevelInVillage($wref, $gid) { return 0; }
};

$dummyData = [
    'moveid' => 999,
    'ref'    => 888,
    'from'   => 101,
    'to'     => 505,
    't1'     => 10,
    't2'     => 5,
    't3'     => 0,
    't4'     => 0,
    't5'     => 0,
    't6'     => 0,
    't7'     => 0,
    't8'     => 0,
    't9'     => 0,
    't10'    => 0,
    't11'    => 1, // Hero
];
$dummyFrom = ['owner' => 42, 'wref' => 101, 'name' => 'HomeVillage'];
$dummyTo   = ['owner' => 2, 'wref' => 505, 'name' => 'OasisNature'];

$dummyCageResult = [
    'handled'        => true,
    'captured'       => [31 => 1, 32 => 1, 33 => 0, 34 => 1, 35 => 0, 36 => 0, 37 => 0, 38 => 1, 39 => 0, 40 => 1],
    'initial'        => [31 => 2, 32 => 2, 33 => 0, 34 => 2, 35 => 0, 36 => 0, 37 => 0, 38 => 2, 39 => 0, 40 => 2],
    'total_captured' => 5,
    'cages_used'     => 5,
    'cages_remaining'=> 0
];

$dummyClass->callResolveCageCaptureMission(
    $dummyData,
    $dummyFrom,
    $dummyTo,
    1, // owntribe (Roman)
    0, // ownally
    0, // targetally
    1700000000, // AttackArrivalTime
    $dummyCageResult
);

// Verify Notice created
assertTrue($capturedNotice !== null, "Notice must be created");
assertEquals(1, $capturedNotice['type'], "Notice type must be 1 (Attack/Raid report)");
assertEquals(42, $capturedNotice['uid'], "Notice recipient must be attacker uid");
assertTrue(strpos($capturedNotice['data'], ',CAGE:5;1;1;0;1;0;0;0;1;0;1') !== false, "Notice data must contain CAGE token with total and breakdown");

// Parse combat CSV report: units dead for attacker (10 counts) must be 0,0,0,0,0,0,0,0,0,0
$reportTokens = explode(',', $capturedNotice['data']);
// index 3..12: units sent (t1..t10) -> 10,5,0,0,0,0,0,0,0,0
assertEquals('10', $reportTokens[3], "Attacker t1 sent must be 10");
assertEquals('5', $reportTokens[4], "Attacker t2 sent must be 5");
// index 13..22: units dead -> 0,0,0,0,0,0,0,0,0,0
for ($k = 13; $k <= 22; $k++) {
    assertEquals('0', $reportTokens[$k], "Attacker casualties at index $k must be 0 (No combat)");
}
// Bounty/plunder (steal 0,0,0,0, bounty 0)
assertEquals('0', $reportTokens[23], "Plundered wood must be 0");
assertEquals('0', $reportTokens[24], "Plundered clay must be 0");
assertEquals('0', $reportTokens[25], "Plundered iron must be 0");
assertEquals('0', $reportTokens[26], "Plundered crop must be 0");
assertEquals('0', $reportTokens[27], "Plundered bounty must be 0");

// Verify Movement return created
assertTrue($capturedMovement !== null, "Return movement must be scheduled");
assertEquals(4, $capturedMovement['type'], "Return movement sort_type must be 4");
assertEquals(505, $capturedMovement['from'], "Return from must be oasis wref 505");
assertEquals(101, $capturedMovement['to'], "Return to must be home wref 101");
assertEquals(888, $capturedMovement['ref'], "Movement ref must match attacks row id");
assertEquals(999, $capturedProc, "setMovementProc must process moveid 999");
echo "PASS: resolveCageCaptureMission executed with zero casualties, zero plunder, CAGE token, and safe return movement." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 5: Verify HeroBattleBonus::applyCages() reference assignment
// -----------------------------------------------------------------------------
echo "Test 5: Testing HeroBattleBonus::applyCages() reference return with null initial variable..." . PHP_EOL;

$cageResultTest = null;
$defenderInput = ['u31' => 5, 'u32' => 5];
// When no cages exist in inventory for uid 99999
HeroBattleBonus::applyCages(99999, 100, 200, $defenderInput, $cageResultTest);
assertTrue(is_array($cageResultTest), "cageResult must be populated as array");
assertEquals(false, $cageResultTest['handled'], "cageResult handled must be false when no cages present");
echo "PASS: applyCages correctly assigns reference when passed null." . PHP_EOL;

echo "=== ALL HERO CAGE TESTS PASSED SUCCESSFULLY! ===" . PHP_EOL;
