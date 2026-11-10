<?php
/**
 * Test: Bandit Camp Attack Resolution, Reinforcement Blocking & Wipeout Report Verification
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';
require_once __DIR__ . '/../GameEngine/Battle.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Units.php';

echo "=== STARTING BANDIT CAMP BATTLE & REPORT TESTS ===\n\n";

// TEST 1: BanditCamp::isBanditCamp identification
echo "Test 1: Verify BanditCamp::isBanditCamp...\n";
$activeCamps = $database->query_return("SELECT wref, tier FROM " . TB_PREFIX . "bandit_camps WHERE status = 1 LIMIT 5");
if (empty($activeCamps)) {
    // If no active camp, spawn or simulate one
    $testTier = 2;
    $spawned = BanditCamp::spawnCamp($testTier);
    $campWref = (int)$spawned['wref'];
} else {
    $campWref = (int)$activeCamps[0]['wref'];
}
$isBandit = BanditCamp::isBanditCamp($campWref);
if (!$isBandit) {
    echo "FAILED: Expected wref $campWref to be detected as bandit camp.\n";
    exit(1);
}
echo "PASSED: Camp wref $campWref successfully identified as Bandit Camp.\n\n";

// TEST 2: Reinforcement (c=2) blocking to Bandit Camp via Units::checkErrors
echo "Test 2: Verify Units::checkErrors blocks reinforcement to Bandit Camp...\n";
$coor = $database->getCoor($campWref);
$units = new Units();
$postReinforce = [
    'x' => $coor['x'],
    'y' => $coor['y'],
    'c' => 2, // Reinforcement
    't1' => 10
];
$err = $units->checkErrors($postReinforce);
if (empty($err) || strpos($err, "bala bantuan") === false && strpos($err, "reinforce") === false) {
    echo "FAILED: Expected reinforcement to be rejected. Got error: '$err'\n";
    exit(1);
}
echo "PASSED: Reinforcement correctly blocked with error: '$err'\n\n";

// TEST 3: Raid (c=4) is accepted by checkErrors
echo "Test 3: Verify Units::checkErrors allows Raid (c=4) to Bandit Camp...\n";
global $village, $session;
$village = new stdClass();
$village->wid = 100;
$village->tribe = 1;
$village->unitarray = ['u1' => 100];
$session = new stdClass();
$session->sit = 0;
$session->uid = 1;

$postRaid = [
    'x' => $coor['x'],
    'y' => $coor['y'],
    'c' => 4, // Raid
    't1' => 10
];
$errRaid = $units->checkErrors($postRaid);
if (!empty($errRaid)) {
    echo "FAILED: Expected raid to be accepted without error. Got error: '$errRaid'\n";
    exit(1);
}
echo "PASSED: Raid (c=4) to Bandit Camp passed validation successfully.\n\n";

// TEST 4: Combat comparison: Raid (type 4) vs Normal Attack (type 3) against Tier 2 Bandit defenders
echo "Test 4: Combat mechanics comparison (Raid vs Normal Attack)...\n";
$battle = new Battle();
$p = BanditCamp::getTierProfiles()[BanditCamp::TIER_HIDEOUT];
$defUnits = [];
for ($i = 1; $i <= 100; $i++) {
    $defUnits['u' . $i] = 0;
}
foreach ($p['troops'] as $u => $cnt) {
    $defUnits['u' . $u] = $cnt;
}
$defUnits['hero'] = 0;

// Attacker: 200 Legionnaires (Romans: u1..u10)
$attUnits = [];
for ($i = 1; $i <= 10; $i++) {
    $attUnits['u' . $i] = 0;
}
$attUnits['u1'] = 200; // 200 Legionnaires
$attUnits['uhero'] = 1; // 1 Hero

// 4a. Raid (type 4)
$raidResult = $battle->calculateBattle(
    $attUnits, $defUnits, 0, 1, 4, 0, 100, 100, 4,
    [], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    1, 10, 100, $campWref, 0, []
);
$raidCasualties = $raidResult['casualties_attacker'][1] ?? 0;
$raidSurviving = $attUnits['u1'] - $raidCasualties;

// 4b. Normal Attack (type 3)
$normalResult = $battle->calculateBattle(
    $attUnits, $defUnits, 0, 1, 4, 0, 100, 100, 3,
    [], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    1, 10, 100, $campWref, 0, []
);
$normalCasualties = $normalResult['casualties_attacker'][1] ?? 0;
$normalSurviving = $attUnits['u1'] - $normalCasualties;

echo "Raid casualties: $raidCasualties / 200 (Surviving: $raidSurviving troops)\n";
echo "Normal attack casualties: $normalCasualties / 200 (Surviving: $normalSurviving troops)\n";

if ((int)$normalSurviving !== 0 || (int)$raidSurviving <= 0) {
    echo "FAILED: Expected normal attack to wipe out (0 surviving) and raid to retain survivors.\n";
    exit(1);
}
echo "PASSED: Confirmed Raid allows troops to survive, whereas Normal Attack suffers 100% wipeout!\n\n";

// TEST 5: Templates/Notice/3.tpl hero display & bandit camp tip verification
echo "Test 5: Verify Notice 3.tpl renders hero and bandit camp tip...\n";
global $generator;
$technology = new Technology();
$tokens = array_fill(0, 261, 0);
$tokens[0] = 1; $tokens[1] = 100; $tokens[2] = 1;
$tokens[3] = 200; $tokens[13] = 200;
$tokens[28] = 10; $tokens[29] = $campWref; $tokens[30] = "Sarang Penyamun Rimba";
$tokens[34] = 4;
$tokens[239] = 1; // Hero sent
$tokens[240] = 1; // Hero dead

$message = new stdClass();
$message->readingNotice = [
    'id' => 88888,
    'data' => implode(",", $tokens),
    'topic' => "Player attacks Sarang Penyamun Rimba",
    'time' => time(),
    'toWref' => $campWref
];

ob_start();
include __DIR__ . '/../Templates/Notice/3.tpl';
$renderedHtml = ob_get_clean();

$hasHeroImg = (strpos($renderedHtml, 'class="unit uhero"') !== false);
$hasHeroDeadText = (strpos($renderedHtml, 'Pahlawan Anda telah gugur') !== false);
$hasBanditTip = (strpos($renderedHtml, 'Catatan Pertempuran Sarang Bandit') !== false);

echo "Hero icon in attacker troops: " . ($hasHeroImg ? "OK" : "MISSING") . "\n";
echo "Hero death notice: " . ($hasHeroDeadText ? "OK" : "MISSING") . "\n";
echo "Bandit Camp tactical tip: " . ($hasBanditTip ? "OK" : "MISSING") . "\n";

if (!$hasHeroImg || !$hasHeroDeadText || !$hasBanditTip) {
    echo "FAILED: Notice 3.tpl did not render expected hero elements or bandit tip.\n";
    exit(1);
}
echo "PASSED: Notice 3.tpl successfully renders hero casualties and bandit camp guidance!\n\n";

echo "=== ALL BANDIT CAMP TESTS COMPLETED SUCCESSFULLY! ===\n";
