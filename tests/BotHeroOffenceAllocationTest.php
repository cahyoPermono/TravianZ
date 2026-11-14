<?php
/**
 * Test Suite for Bot Hero Offence Stat Allocation
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BotAI.php';
require_once __DIR__ . '/../GameEngine/Units.php';

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        echo "FAIL: $message | Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
}

echo "=== Testing Bot Hero Offence Stat Allocation ===\n";

global $database;
$testBotUid = 999985;
$testWid = 999985;

// Clean up any test user and hero
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $testBotUid");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testBotUid");

// 1. Create a dummy bot user
$database->query("INSERT INTO " . TB_PREFIX . "users (`id`, `username`, `email`, `is_bot`) VALUES ($testBotUid, 'Bot_OffenceTester', 'bot_test@bot.travianz', 1)");

// 2. Test auto-creation + starter point allocation
$botData = [
    'uid' => $testBotUid,
    'wref' => $testWid,
    'tribe' => 1,
    'username' => 'Bot_OffenceTester'
];

$res1 = BotAI::processHero($botData);
echo "ProcessHero 1 (Create Starter): " . ($res1 ?? 'none') . "\n";
assertTrue($res1 !== null && strpos($res1, 'Starter hero auto-created') !== false, "Starter hero must be created");

$hero1 = $database->query_return("SELECT points, attack, defence, attackbonus FROM " . TB_PREFIX . "hero WHERE uid = $testBotUid LIMIT 1")[0];
assertEqual(5, (int)$hero1['attack'], "5 starter points must be allocated to attack (Fighting Strength)");
assertEqual(5, (int)$hero1['defence'], "Defence must be synced to 5");
assertEqual(0, (int)$hero1['points'], "Remaining points must be 0");

echo "PASS: Starter hero points immediately allocated to Fighting Strength.\n";

// 3. Test level-up point allocation (say bot gains 250 points, like a high-level bot)
$database->query("UPDATE " . TB_PREFIX . "hero SET points = 250 WHERE uid = $testBotUid");

$res2 = BotAI::processHero($botData);
echo "ProcessHero 2 (Allocate 250 points): " . ($res2 ?? 'none') . "\n";
assertTrue(strpos($res2, 'Fighting Strength') !== false, "Must mention Fighting Strength allocation");
assertTrue(strpos($res2, 'Off-Bonus') !== false, "Must mention Off-Bonus allocation");

$hero2 = $database->query_return("SELECT points, attack, defence, attackbonus FROM " . TB_PREFIX . "hero WHERE uid = $testBotUid LIMIT 1")[0];
assertEqual(100, (int)$hero2['attack'], "Attack must be capped at 100 max");
assertEqual(100, (int)$hero2['defence'], "Defence must be synced to 100");
assertEqual(100, (int)$hero2['attackbonus'], "Off-Bonus must be capped at 100 max (+20% full offensive troop bonus)");
// 5 initial + 250 = 255. 100 into attack + 100 into attackbonus = 200 used, 55 remaining.
assertEqual(55, (int)$hero2['points'], "Remaining points must be 55");

echo "PASS: High-level bot points maxes Fighting Strength (100) and Off-Bonus (100).\n";

// 4. Test upgradeAllBotHeroes batch method
$numUpdated = BotAI::upgradeAllBotHeroes();
echo "upgradeAllBotHeroes updated $numUpdated heroes in DB.\n";
assertTrue($numUpdated >= 0, "Batch update must complete without errors");

// Clean up
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $testBotUid");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testBotUid");

echo "=== ALL BOT HERO STAT ALLOCATION TESTS PASSED! ===\n";

