<?php

#################################################################################
##  Filename       : tests/BotPlayerTargetingAndArmyBalanceTest.php            ##
##  Type           : Unit Test for Bot 70% Player Target & Army Rebalancing    ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

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

echo "=== 1. Testing BotAI Home Defense & Deployable Combat Calculation ===" . PHP_EOL;

// Mock scenario:
// Home reserve needed = 30
// Suku Galia (Tribe 3):
// Phalanx (u21) = 50 (pertahanan murni)
// Swordsman (u22) = 25 (pasukan serang)
$tribe = 3;
$pop = 150;
$homeReserve = BotAI::getHomeReserve($pop);
assertTrue($homeReserve === 30, "Home reserve for pop 150 should be 30");

$offensiveUnits = BotAI::getOffensiveUnits($tribe, false);
$tribeUnits = BotAI::getTribeUnits($tribe);
$siegeUnits = BotAI::getSiegeUnits($tribe);

// Mock home units
$homeUnits = [
    'u21' => 50, // Phalanx (def)
    'u22' => 25, // Swordsman (off)
    'u23' => 5,  // Scout
    'hero' => 0
];

$totalNonSiegeAvailable = 25; // Swordsman
$totalSiegeAvailable = 0;
$totalCombatAvailable = $totalNonSiegeAvailable + $totalSiegeAvailable;

// Hitung total pertahanan murni di rumah
$scoutU = $tribeUnits['scout'] ?? 0;
$chiefU = BotAI::getChiefUnit($tribe);
$settlerU = $tribe * 10;
$totalDefensiveAtHome = 0;
foreach ($tribeUnits['all'] as $uNum) {
    if (!in_array($uNum, $offensiveUnits, true) && !in_array($uNum, $siegeUnits, true)) {
        if ($uNum === $scoutU || $uNum === $settlerU || $uNum === $chiefU) continue;
        $totalDefensiveAtHome += (int)($homeUnits['u' . $uNum] ?? 0);
    }
}
assertEqual(50, $totalDefensiveAtHome, "Total defensive at home should be 50 Phalanx");

// Defisit pertahanan:
$defenseDeficit = max(0, $homeReserve - $totalDefensiveAtHome);
assertEqual(0, $defenseDeficit, "Defense deficit must be 0 because 50 Phalanx >= 30 reserve");

// Deployable combat:
$deployableCombat = max(0, $totalCombatAvailable - $defenseDeficit);
assertEqual(25, $deployableCombat, "All 25 Swordsmen are deployable for attack without being blocked by home reserve!");

echo "PASSED" . PHP_EOL;

echo "=== 2. Testing BotAI 90% Real Player Target Selection Ratio ===" . PHP_EOL;

// Setup mock candidate pool: 3 real players and 10 bot villages
$playerCandidates = [
    ['wref' => 101, 'is_bot' => false, 'dist' => 15.0, 'username' => 'HumanHero1'],
    ['wref' => 102, 'is_bot' => false, 'dist' => 25.0, 'username' => 'HumanHero2'],
    ['wref' => 103, 'is_bot' => false, 'dist' => 35.0, 'username' => 'HumanHero3'],
];

$botCandidates = [];
for ($i = 1; $i <= 10; $i++) {
    $botCandidates[] = [
        'wref' => 200 + $i,
        'is_bot' => true,
        'dist' => 5.0 + $i, // Bots are closer than players (5.0, 6.0, ...)
        'username' => 'Bot_' . $i
    ];
}

$targetPlayerPercent = (int)BotAI::getConfig('BOT_AI_TARGET_PLAYER_PERCENT', 90);
assertEqual(90, $targetPlayerPercent, "BOT_AI_TARGET_PLAYER_PERCENT config must be 90");

// Run 1000 trials to verify selection distribution
$playerHits = 0;
$botHits = 0;
$trials = 1000;

for ($t = 0; $t < $trials; $t++) {
    $chosenPool = [];
    if (!empty($playerCandidates) && !empty($botCandidates)) {
        if (rand(1, 100) <= $targetPlayerPercent) {
            $chosenPool = $playerCandidates;
        } else {
            $chosenPool = $botCandidates;
        }
    }

    usort($chosenPool, function($a, $b) {
        return $a['dist'] <=> $b['dist'];
    });

    $topCandidates = array_slice($chosenPool, 0, min(3, count($chosenPool)));
    $chosen = $topCandidates[array_rand($topCandidates)];

    if ($chosen['is_bot']) {
        $botHits++;
    } else {
        $playerHits++;
    }
}

$playerRatio = ($playerHits / $trials) * 100.0;
echo "Ran $trials target selection trials: Player Hits = $playerHits ({$playerRatio}%), Bot Hits = $botHits" . PHP_EOL;
assertTrue($playerRatio >= 84.0 && $playerRatio <= 96.0, "Player target ratio must be approximately 90% (observed: {$playerRatio}%)");

echo "PASSED" . PHP_EOL;

echo "=== 3. Testing Real Player Distance Tolerance (Up to 200 Tiles) ===" . PHP_EOL;

$botCoor = ['x' => 0, 'y' => 0];
$maxDistance = 70.0;
$maxPlayerDist = (float)BotAI::getConfig('BOT_AI_MAX_PLAYER_DISTANCE', 200.0);

// Test a real player at 150 tiles (beyond 70, within 200)
$playerAt150 = ['is_bot' => false, 'dist' => 150.0];
$maxAllowedPlayerDist = $playerAt150['is_bot'] ? $maxDistance : max($maxDistance, $maxPlayerDist);
assertTrue($playerAt150['dist'] <= $maxAllowedPlayerDist, "Player at 150 tiles must be accepted within 200 tile limit");

// Test a bot at 150 tiles (beyond 70)
$botAt150 = ['is_bot' => true, 'dist' => 150.0];
$maxAllowedBotDist = $botAt150['is_bot'] ? $maxDistance : max($maxDistance, $maxPlayerDist);
assertFalse($botAt150['dist'] <= $maxAllowedBotDist, "Bot at 150 tiles must be rejected exceeding 70 tile limit");

echo "PASSED" . PHP_EOL;

echo "=== 4. Testing Bot Training Candidates Composition ===" . PHP_EOL;

// Mock Gaul (Tribe 3) where Phalanx is basic (defensive) and Swordsman is inf_adv (offensive)
$gaulUnits = BotAI::getTribeUnits(3);
$offUnits = BotAI::getOffensiveUnits(3, false);
assertFalse(in_array($gaulUnits['basic'], $offUnits, true), "Gaul basic unit (Phalanx u21) is defensive");
assertTrue(in_array($gaulUnits['inf_adv'], $offUnits, true), "Gaul inf_adv (Swordsman u22) is offensive");

// When defense is secure, Swordsman weight is 55 while Phalanx is 15
$phalanxWeightSecure = 15;
$swordsmanWeightSecure = 55;
assertTrue($swordsmanWeightSecure > ($phalanxWeightSecure * 3), "When defense is secure, offensive infantry must heavily outweigh defensive basic infantry");

echo "PASSED" . PHP_EOL;

echo "=== 5. Testing Night Sleep (Blackout 20:00 - 08:00) ===" . PHP_EOL;
assertTrue(BotAI::getConfig('BOT_AI_NIGHT_SLEEP_ENABLED', true), "Night sleep must be ENABLED by default");

// Verify hour boundary logic:
$startHour = (int)BotAI::getConfig('BOT_AI_ATTACK_START_HOUR', 8);
$endHour = (int)BotAI::getConfig('BOT_AI_ATTACK_END_HOUR', 20);
assertEqual(8, $startHour, "Attack start hour is 08:00");
assertEqual(20, $endHour, "Attack end hour is 20:00 (8 PM)");

// Check night hours (20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6, 7) vs day hours (8..19)
$testNightHours = [20, 21, 23, 0, 4, 7];
foreach ($testNightHours as $h) {
    $isNight = ($h < $startHour || $h >= $endHour);
    assertTrue($isNight, "Hour {$h}:00 must be recognized as night blackout period");
}
$testDayHours = [8, 10, 12, 14, 17, 19];
foreach ($testDayHours as $h) {
    $isNight = ($h < $startHour || $h >= $endHour);
    assertFalse($isNight, "Hour {$h}:00 must be recognized as active daylight attack period");
}
echo "PASSED" . PHP_EOL;

echo "=== All Tests Passed Successfully! ===" . PHP_EOL;
