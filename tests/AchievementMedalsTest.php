<?php
/**
 * Test Achievement Medals System for TravianZ
 */

require_once __DIR__ . '/../GameEngine/Database.php';

echo "=== Running Achievement Medals Tests ===\n";

global $database;

// 1. Verify medal images exist in both gpack directories
$expectedImages = [
    'bandit_hunter.svg',
    'bandit_slayer.svg',
    'bandit_nemesis.svg',
    'boss_slayer.svg',
    'boss_raider.svg',
    'oasis_master.svg',
    'warlord.svg',
    'scout_master.svg',
    'imperium.svg',
    'metropolis.svg',
    'tycoon.svg',
    'hero50.svg',
    'forge_master.svg',
    'philanthropist.svg',
];

$packs = [
    __DIR__ . '/../gpack/travian_default/img/gloriamedals/',
    __DIR__ . '/../gpack/travian_t4/img/gloriamedals/',
];

foreach ($packs as $packDir) {
    foreach ($expectedImages as $img) {
        if (!file_exists($packDir . $img)) {
            die("FAIL: Image missing: {$packDir}{$img}\n");
        }
    }
}
echo "PASS: All 14 SVG medal images exist in both gpack directories.\n";

// 2. Test medal.php parsing logic with mock user data
// Test Case A: User with 0 bandit kills should NOT get [#BANDITHUNTER]
$displayarray = [
    'id'       => 999999, // Non-existent user with 0 stats
    'username' => 'TestNoob',
    'tribe'    => 1,
    'protect'  => 0,
    'regtime'  => time() - 86400,
    'access'   => 2,
    'gpack'    => 'gpack/travian_default/',
];
$varmedal = [];
$profiel = "Hello world! Check out my badge: [#BANDITHUNTER] and [#BOSSSLAYER]!";

// Run medal.php
ob_start();
include __DIR__ . '/../Templates/Profile/medal.php';
ob_end_clean();

if (strpos($profiel, 'bandit_hunter.svg') !== false || strpos($profiel, 'boss_slayer.svg') !== false) {
    die("FAIL: Fake medal tags were not stripped for non-qualifying user!\n");
}
if (strpos($profiel, '[#BANDITHUNTER]') !== false || strpos($profiel, '[#BOSSSLAYER]') !== false) {
    die("FAIL: Unmet medal BBCode tags were left in text!\n");
}
echo "PASS: Fake/unearned medal tags are properly stripped from profile.\n";

// Test Case B: Create temporary qualified records for testing
$testUid = 888888;
$database->query("DELETE FROM " . TB_PREFIX . "bandit_camps WHERE cleared_by = $testUid");
$time = time();

// Insert 12 dummy cleared bandit camps (10 normal + 2 tier 4 boss)
for ($i = 1; $i <= 10; $i++) {
    $database->query("INSERT INTO " . TB_PREFIX . "bandit_camps (wref, tier, name, max_hp, cur_hp, status, cleared_by, cleared_time, created)
                      VALUES (99999 + $i, 1, 'Camp $i', 100, 0, 0, $testUid, $time, $time)");
}
$database->query("INSERT INTO " . TB_PREFIX . "bandit_camps (wref, tier, name, max_hp, cur_hp, status, cleared_by, cleared_time, created)
                  VALUES (999990, 4, 'Boss Camp 1', 500, 0, 0, $testUid, $time, $time)");
$database->query("INSERT INTO " . TB_PREFIX . "bandit_camps (wref, tier, name, max_hp, cur_hp, status, cleared_by, cleared_time, created)
                  VALUES (999991, 4, 'Boss Camp 2', 500, 0, 0, $testUid, $time, $time)");

$displayarray = [
    'id'       => $testUid,
    'username' => 'TestBanditHero',
    'tribe'    => 2,
    'protect'  => 0,
    'regtime'  => time() - 86400 * 30,
    'access'   => 2,
    'gpack'    => 'gpack/travian_default/',
];
$varmedal = [];
$profiel = "My badges: [#BANDITHUNTER] [#BOSSSLAYER] [#BANDITNEMESIS]";

ob_start();
include __DIR__ . '/../Templates/Profile/medal.php';
ob_end_clean();

// Cleanup test records
$database->query("DELETE FROM " . TB_PREFIX . "bandit_camps WHERE cleared_by = $testUid");

// Assertions:
// 1. Should have bandit_hunter.svg
if (strpos($profiel, 'bandit_hunter.svg') === false) {
    die("FAIL: [#BANDITHUNTER] was not replaced with bandit_hunter.svg!\nProfiel: $profiel\n");
}
// 2. Should have boss_slayer.svg
if (strpos($profiel, 'boss_slayer.svg') === false) {
    die("FAIL: [#BOSSSLAYER] was not replaced with boss_slayer.svg!\nProfiel: $profiel\n");
}
// 3. Should NOT have bandit_nemesis.svg (only 12 camps, needs 75)
if (strpos($profiel, 'bandit_nemesis.svg') !== false) {
    die("FAIL: [#BANDITNEMESIS] should NOT be granted for 12 camps!\nProfiel: $profiel\n");
}
// 4. Tooltip should contain camps cleared and boss defeated count
if (strpos($profiel, 'Camps Cleared:</td><td>12') === false) {
    die("FAIL: Tooltip does not contain correct Camps Cleared count!\nProfiel: $profiel\n");
}
if (strpos($profiel, 'Bosses Defeated:</td><td>2') === false) {
    die("FAIL: Tooltip does not contain correct Bosses Defeated count!\nProfiel: $profiel\n");
}

echo "PASS: Qualifying user successfully gets [#BANDITHUNTER] & [#BOSSSLAYER] with interactive tooltips.\n";

echo "=== ALL ACHIEVEMENT MEDALS TESTS PASSED! ===\n";
