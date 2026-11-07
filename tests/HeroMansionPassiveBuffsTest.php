<?php
/**
 * Test for Hero's Mansion Passive Buffs:
 * 1. Hero EXP Gain Bonus (+1% per level, max +20% at level 20)
 * 2. Troop Crop Upkeep Discount (-0.5% per level, max -10% at level 20)
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        fwrite(STDERR, "  Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "  Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

echo "=== Running HeroMansionPassiveBuffsTest ===" . PHP_EOL;
global $database;

$testSuffix = substr(md5((string)microtime(true)), 0, 6);
$uName = 'HeroMansionTest_' . $testSuffix;

// 1. Create Test User
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime)
    VALUES ('$uName', MD5('pass'), 'hero_test_{$testSuffix}@example.com', 1, 2, " . time() . ")
");
$uid = (int)mysqli_insert_id($database->dblink);

// 2. Create Test Village
$coords = [89, 89];
$wRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$coords[0]} AND y = {$coords[1]} LIMIT 1");
if (empty($wRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$coords[0]}, {$coords[1]}, 1, 'f3')");
    $wid = (int)mysqli_insert_id($database->dblink);
} else {
    $wid = (int)$wRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $wid");
}

$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop)
    VALUES ($wid, $uid, 'Hero Mansion Castle', 1, 250)
    ON DUPLICATE KEY UPDATE owner = $uid, name = 'Hero Mansion Castle', capital = 1
");

// 3. Create Test Hero stationed in this village
$database->query("
    INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, health, dead)
    VALUES ($uid, 1, 'Sir Testalot', $wid, 0, 0, 0, 100, 0)
");
$heroid = (int)mysqli_insert_id($database->dblink);

// Initialize fdata without Hero's Mansion (Level 0)
$database->query("
    INSERT INTO " . TB_PREFIX . "fdata (vref, f1t, f38, f38t)
    VALUES ($wid, 1, 0, 37)
    ON DUPLICATE KEY UPDATE f38 = 0, f38t = 37
");
$database->clearVillageCache();

// ------------------------------------------------------------------------------
// PART 1: HERO EXP GAIN WITH HERO'S MANSION
// ------------------------------------------------------------------------------
echo "--- Testing Hero EXP Bonus ---" . PHP_EOL;

// Case 1A: Level 0 Mansion -> Base EXP (100 XP -> 100)
$database->modifyHeroXp('experience', 100, $heroid);
$curExp = (int)($database->query_return("SELECT experience FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0]['experience'] ?? 0);
assertEqual(100, $curExp, "Mansion Level 0 must award exact base 100 XP");

// Case 1B: Level 10 Mansion -> +10% EXP (100 XP -> 110)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 10, f38t = 37 WHERE vref = $wid");
$database->clearVillageCache();

$database->modifyHeroXp('experience', 100, $heroid);
$curExp = (int)($database->query_return("SELECT experience FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0]['experience'] ?? 0);
assertEqual(210, $curExp, "Mansion Level 10 must award 100 + 110 (+10% bonus) = 210 XP total");

// Case 1C: Level 20 Mansion -> +20% EXP (100 XP -> 120)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 20, f38t = 37 WHERE vref = $wid");
$database->clearVillageCache();

$database->modifyHeroXp('experience', 100, $heroid);
$curExp = (int)($database->query_return("SELECT experience FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0]['experience'] ?? 0);
assertEqual(330, $curExp, "Mansion Level 20 must award 210 + 120 (+20% bonus) = 330 XP total");

// ------------------------------------------------------------------------------
// PART 2: TROOP CROP UPKEEP DISCOUNT
// ------------------------------------------------------------------------------
echo "--- Testing Troop Crop Upkeep Discount ---" . PHP_EOL;
$tech = new Technology();

// Setup 100 Legionnaires (u1 pop = 1, total base upkeep = 100)
$testTroopArray = ['u1' => 100];

// Case 2A: Level 0 Mansion -> 0% discount (upkeep = 100)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 0, f38t = 37 WHERE vref = $wid");
$database->clearVillageCache();

$upkeepLvl0 = $tech->getUpkeep($testTroopArray, 0, $wid);
assertEqual(100, $upkeepLvl0, "Mansion Level 0 must have standard 100 crop upkeep");

// Case 2B: Level 10 Mansion -> 5% discount (100 * 0.95 = 95)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 10, f38t = 37 WHERE vref = $wid");
$database->clearVillageCache();

$upkeepLvl10 = $tech->getUpkeep($testTroopArray, 0, $wid);
assertEqual(95, $upkeepLvl10, "Mansion Level 10 must apply -5% discount (100 -> 95 crop upkeep)");

// Case 2C: Level 20 Mansion -> 10% discount (100 * 0.90 = 90)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f38 = 20, f38t = 37 WHERE vref = $wid");
$database->clearVillageCache();

$upkeepLvl20 = $tech->getUpkeep($testTroopArray, 0, $wid);
assertEqual(90, $upkeepLvl20, "Mansion Level 20 must apply -10% discount (100 -> 90 crop upkeep)");

// ------------------------------------------------------------------------------
// CLEANUP FIXTURES
// ------------------------------------------------------------------------------
echo "Cleaning up test records... ";
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE heroid = $heroid");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $wid");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $wid");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
$database->clearVillageCache();
echo "CLEANUP COMPLETE" . PHP_EOL;

echo "=== All HeroMansionPassiveBuffsTest tests PASSED successfully! ===" . PHP_EOL;
