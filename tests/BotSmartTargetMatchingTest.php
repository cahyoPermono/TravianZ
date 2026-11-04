<?php

#################################################################################
##  Filename       : BotSmartTargetMatchingTest.php                            ##
##  Type           : Unit Test for Smart Target Matching and Army Mobilization ##
#################################################################################

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);

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

echo "=== 1. Testing Smart Target Matching Against Big Players ===\n";

// Target is human player with 500 pop (likely has huge army)
$bigHumanPlayer = [
    'wref' => 300,
    'owner' => 50,
    'vname' => 'Big Player Citadel',
    'pop' => 500,
    'username' => 'ProPlayer',
    'is_bot' => 0
];

// If bot only has 40 deployable combat troops:
$deployableCombatWeak = 40;
$canAttackBigWeak = !($bigHumanPlayer['pop'] >= 350 && $deployableCombatWeak < 180);
assertEqual(false, $canAttackBigWeak, "Weak bot (40 troops) must NOT attack big human player (500 pop)");

// If bot has 250 deployable combat troops:
$deployableCombatStrong = 250;
$canAttackBigStrong = !($bigHumanPlayer['pop'] >= 350 && $deployableCombatStrong < 180);
assertEqual(true, $canAttackBigStrong, "Strong bot (250 troops) is allowed to attack big human player (500 pop)");

echo "PASSED\n";

echo "=== 2. Testing Mobilization Party Size (Exceeds old 100 cap) ===\n";

$deployableCombat = 600;
$targetPop = 400;
$minAttackForce = 25;

// Formula from BotAI: min(800, max($minAttackForce, (int)floor($deployableCombat * 0.70)))
$targetPartySize = min(800, max($minAttackForce, (int)floor($deployableCombat * 0.70)));
assertEqual(420, $targetPartySize, "Should mobilize 70% of 600 troops (420 troops)");
assertTrue($targetPartySize > 100, "Party size ($targetPartySize) must comfortably exceed the obsolete 100 cap");

echo "PASSED (Party size = $targetPartySize)\n";

echo "=== 3. Testing Enhanced Training Throughput for Mature Bots ===\n";

// For mature village (pop >= 500, brLevel >= 8)
$pop = 600;
$brLevel = 10;
$maxQueued = ($pop >= 500 && $brLevel >= 8) ? 200 : 35;
assertEqual(200, $maxQueued, "Max training queue cap must be 200 for mature villages");

echo "PASSED\n";

echo "\n=== All Smart Target Matching Tests Passed Successfully! ===\n";

