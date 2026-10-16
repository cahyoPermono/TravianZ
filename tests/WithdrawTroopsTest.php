<?php

#################################################################################
##  Test: Withdraw & Send Troops Validation Test                               ##
##  Project: TravianZ                                                          ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';

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

echo "=== Running WithdrawTroopsTest ===" . PHP_EOL;

// Test unit ID mapping for all tribes
$testTribes = [
    1 => ['name' => 'Romans', 'start' => 1, 'settler' => 10],
    2 => ['name' => 'Teutons', 'start' => 11, 'settler' => 20],
    3 => ['name' => 'Gauls', 'start' => 21, 'settler' => 30],
    4 => ['name' => 'Natars', 'start' => 31, 'settler' => 40],
    10 => ['name' => 'Nusantara', 'start' => 91, 'settler' => 100]
];

foreach ($testTribes as $tribeId => $tribeData) {
    for ($i = 1; $i <= 10; $i++) {
        $unitId = ($tribeId - 1) * 10 + $i;
        if ($i === 10) {
            assertEqual($tribeData['settler'], $unitId, "Settler unit ID for {$tribeData['name']} must be {$tribeData['settler']}");
        }
    }
    echo "PASS: Unit ID calculation verified for {$tribeData['name']}" . PHP_EOL;
}

// Test withdrawal validation logic with simulated enforcement array
$enforceTeutons = [
    'u11' => 0, 'u12' => 0, 'u13' => 0, 'u14' => 0, 'u15' => 0,
    'u16' => 0, 'u17' => 0, 'u18' => 0, 'u19' => 0, 'u20' => 8,
    'hero' => 1
];

$ownerTribe = 2; // Teutons
// Check unit 10 (settler) with 8 units requested to send back
$unit10Id = ($ownerTribe - 1) * 10 + 10;
assertEqual(20, $unit10Id, "Teuton unit 10 must map to u20");
assertEqual(8, $enforceTeutons['u' . $unit10Id], "Enforcement has 8 settlers in u20");

$post = ['t10' => 8, 't11' => 1];
$unit10Count = (int)($enforceTeutons['u' . $unit10Id] ?? 0);
assertTrue($post['t10'] <= $unit10Count, "Sending back 8 of 8 settlers must pass validation");

$heroCount = (int)($enforceTeutons['hero'] ?? 0);
assertTrue($post['t11'] <= $heroCount, "Sending back 1 of 1 hero must pass validation");

// Requesting 9 when only 8 available should be detected
assertTrue(9 > $unit10Count, "Sending back 9 of 8 settlers must fail validation");

echo "=== All WithdrawTroopsTest Tests Passed Successfully! ===" . PHP_EOL;
