<?php
/**
 * Test: Bandit Camp Starvation Immunity, Crop Bounty Preservation, and Hero Report Layout
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Automation.php';

echo "=== STARTING BANDIT CAMP STARVATION & REPORT INTEGRATION TEST ===\n\n";

// TEST 1: Spawn a test camp and verify BanditCamp::isBanditUser and isBanditCamp
echo "Test 1: Verify BanditCamp::isBanditUser and isBanditCamp...\n";
$spawned = BanditCamp::spawnCamp(BanditCamp::TIER_FORTRESS);
assert(!empty($spawned), "Spawned camp should not be empty");
$wref = (int)$spawned['wref'];
$campId = (int)$spawned['id'];

$isCamp = BanditCamp::isBanditCamp($wref);
assert($isCamp === true, "Expected wref $wref to be identified as Bandit Camp");

$banditUser = BanditCamp::getBanditUser();
$isUser = BanditCamp::isBanditUser((int)$banditUser['id']);
assert($isUser === true, "Expected bandit user ID {$banditUser['id']} to be identified as Bandit User");
echo "PASSED: Bandit Camp and Bandit User correctly recognized.\n\n";

// TEST 2: Verify addStarvationData ignores Bandit Camp
echo "Test 2: Verify addStarvationData does not set starvation on Bandit Camp...\n";
$database->query("UPDATE " . TB_PREFIX . "vdata SET starv = 0, starvupdate = 0 WHERE wref = $wref");
$database->addStarvationData($wref);
$vCheck = $database->getVillage($wref);
assert((int)$vCheck['starv'] === 0, "Bandit Camp starv should remain 0 after addStarvationData");
echo "PASSED: addStarvationData successfully ignored Bandit Camp.\n\n";

// TEST 3: Verify updateRes does not drain crop bounty
echo "Test 3: Verify updateRes preserves crop bounty without negative upkeep...\n";
$automation = new Automation();
$refUpdateRes = new ReflectionMethod('Automation', 'updateRes');
$refUpdateRes->setAccessible(true);

// Set initial crop bounty
$initialCrop = 32000;
$database->query("UPDATE " . TB_PREFIX . "vdata SET crop = $initialCrop, lastupdate = " . (time() - 3600) . " WHERE wref = $wref");
$refUpdateRes->invoke($automation, $wref);

$vCropCheck = $database->getVillage($wref);
assert((int)$vCropCheck['crop'] >= $initialCrop, "Bandit Camp crop should not decrease below initial bounty, got: " . $vCropCheck['crop']);
echo "PASSED: updateRes preserved crop bounty ({$vCropCheck['crop']}) without upkeep deduction.\n\n";

// TEST 4: Verify AutomationStarvation does not kill bandit troops
echo "Test 4: Verify starvationProcessVillage skips Bandit Camp...\n";
$uBefore = $database->getUnit($wref, false);
$totalTroopsBefore = 0;
for ($i = 31; $i <= 40; $i++) {
    $totalTroopsBefore += (int)($uBefore['u' . $i] ?? 0);
}
assert($totalTroopsBefore > 0, "Bandit Camp should have guards before starvation check");

$refStarvProcess = new ReflectionMethod('Automation', 'starvationProcessVillage');
$refStarvProcess->setAccessible(true);
$starvMock = [
    'wref' => $wref,
    'owner' => (int)$banditUser['id'],
    'pop' => 125,
    'starv' => 500,
    'starvupdate' => time() - 3600
];
$refStarvProcess->invoke($automation, $starvMock, time());

$uAfter = $database->getUnit($wref, false);
$totalTroopsAfter = 0;
for ($i = 31; $i <= 40; $i++) {
    $totalTroopsAfter += (int)($uAfter['u' . $i] ?? 0);
}
assert($totalTroopsAfter === $totalTroopsBefore, "Bandit Camp troops should NOT starve! Before: $totalTroopsBefore, After: $totalTroopsAfter");
echo "PASSED: Bandit Camp troops completely immune to starvation (Total guards: $totalTroopsAfter).\n\n";

// TEST 5: Verify Notice 3.tpl renders hero at index 266 and suppresses bogus [Ram] ?
echo "Test 5: Verify Notice 3.tpl with index 266 (production 10-tribe layout)...\n";
global $technology, $generator, $session;
$technology = new Technology();
$session = new stdClass();
$session->access = 0; // regular player view

// Build tokens matching real AutomationBattleResolution $data_fail with 10 tribes
$tokens = array_fill(0, 286, "");
for ($i = 0; $i <= 285; $i++) {
    $tokens[$i] = "0";
}
$tokens[0] = "1";
$tokens[1] = "100";
$tokens[2] = "2"; // Teuton
$tokens[28] = (string)$banditUser['id'];
$tokens[29] = (string)$wref;
$tokens[30] = "Markas Panglima Pemberontak";
$tokens[34] = "4"; // Nature
// Masked defenders
for ($i = 35; $i <= 244; $i++) {
    $tokens[$i] = "?";
}
// Attacker hero at index 266 and dead at index 267
$tokens[266] = "1";
$tokens[267] = "1";

$message = new stdClass();
$message->readingNotice = [
    'id' => 99999,
    'data' => implode(",", $tokens),
    'topic' => "Player attacks Markas Panglima Pemberontak",
    'time' => time(),
    'toWref' => $wref
];

ob_start();
include __DIR__ . '/../Templates/Notice/3.tpl';
$htmlNotice3 = ob_get_clean();

$hasHeroColumn = (strpos($htmlNotice3, 'class="unit uhero"') !== false);
$hasHeroDeath = (strpos($htmlNotice3, 'Pahlawan Anda telah gugur') !== false);
$noBogusRam = (strpos($htmlNotice3, 'Information: ?') === false && strpos($htmlNotice3, 'alt="Ram"') === false);
$hasCampTip = (strpos($htmlNotice3, 'Catatan Pertempuran Sarang Bandit') !== false);

assert($hasHeroColumn, "Hero column should be rendered in attacker troops row");
assert($hasHeroDeath, "Hero death alert should be rendered");
assert($noBogusRam, "No bogus ram ? information should be rendered");
assert($hasCampTip, "Tactical guidance for bandit camp should be rendered");
echo "PASSED: Notice 3.tpl successfully detects hero at index 266 and eliminates bogus info lines!\n\n";

// Cleanup test camp
BanditCamp::rewardAndClearCamp($spawned, 1, 'Tester', 100);
echo "=== ALL BANDIT CAMP STARVATION & REPORT TESTS PASSED SUCCESSFULLY! ===\n";
