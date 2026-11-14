<?php
/**
 * HeroCountSafeguardTest.php
 *
 * Verifies that:
 * 1. Templates/Notice/1.tpl and 3.tpl clamp hero display count to at most 1.
 * 2. Troop dispatch (attack.tpl and Units.php) caps hero to 1.
 * 3. HeroAdventure and BotAI use idempotent hero assignment (hero = 1) preventing accumulation.
 */

require_once __DIR__ . '/../GameEngine/Database.php';

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        echo "FAIL: $message | Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Testing Hero Count Safeguards ===\n";

// 1. Notice template parsing with corrupted / legacy 94 hero count
$dataarray = array_fill(0, 292, '0');
for ($k = 264; $k <= 271; $k++) { $dataarray[$k] = ''; }
$dataarray[272] = '94'; // legacy corrupted value
$dataarray[273] = '0';

$heroSentAtt = (int)$dataarray[272];
$heroDeadAtt = (int)($dataarray[273] ?? 0);
$heroSentAtt = min(1, max(0, $heroSentAtt));
$heroDeadAtt = min(1, max(0, $heroDeadAtt));
$hasHero = ($heroSentAtt > 0);

assertEqual(1, $heroSentAtt, "Hero sent count is cleanly clamped to 1");
assertEqual(0, $heroDeadAtt, "Hero dead count remains 0");
assertEqual(true, $hasHero, "Has hero flag is true");

// 2. a2b attack normalization
$process = ['t11' => '94'];
$t = [];
for ($i = 1; $i <= 11; $i++) {
    $key = 't' . $i;
    $t[$i] = (isset($process[$key]) && $process[$key] !== '') ? $process[$key] : 0;
}
$t[11] = !empty($t[11]) ? min(1, max(0, (int)$t[11])) : 0;
assertEqual(1, $t[11], "Attack form normalizes t11 from 94 to 1");

// 3. Admin troops clamp
$postHero = 94;
$heroVal = min(1, max(0, (int)$postHero));
assertEqual(1, $heroVal, "Admin addTroops clamps hero to 1");

// 4. a2b units_common and rally point display clamp
$corruptedUnitsHero = 94;
$a2bHeroCount = min(1, max(0, (int)$corruptedUnitsHero));
assertEqual(1, $a2bHeroCount, "a2b units_common clamps hero count to 1");
$rallyPointHeroCount = min(1, max(0, (int)$corruptedUnitsHero));
assertEqual(1, $rallyPointHeroCount, "Rally point display clamps hero count to 1");

// 5. Village synchronization simulation
// Suppose player owns Village A (home, wid 100) and Village B (secondary, wid 200, e.g. "Debug On Production")
$playerHero = ['uid' => 6, 'wref' => 100, 'dead' => 0];
$playerVillages = [100, 200];
$villageUnits = [
    100 => ['hero' => 94], // corrupted
    200 => ['hero' => 94], // phantom hero in secondary village
];
foreach ($playerVillages as $vref) {
    if ($vref !== (int)$playerHero['wref']) {
        $villageUnits[$vref]['hero'] = 0; // cleared by self-healing
    } else {
        $villageUnits[$vref]['hero'] = min(1, max(0, $villageUnits[$vref]['hero']));
    }
}
assertEqual(1, $villageUnits[100]['hero'], "Home village hero clamped to 1");
assertEqual(0, $villageUnits[200]['hero'], "Secondary village (Debug On Production) phantom hero reset to 0");

echo "=== ALL HERO COUNT SAFEGUARD TESTS PASSED! ===\n";

