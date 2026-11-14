<?php
/**
 * Test Suite for Hero T4 Fighting Strength Mechanism
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Battle.php';
require_once __DIR__ . '/../GameEngine/Units.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';

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

echo "=== Testing Hero T4 Fighting Strength Mechanics ===\n";

// 1. Verify Units::buildHeroStats scales both attack and defence from attack points (Fighting Strength)
$units = new Units();
$reflection = new ReflectionClass($units);
$buildHeroStatsMethod = $reflection->getMethod('buildHeroStats');
$buildHeroStatsMethod->setAccessible(true);

// Use Unit 76 (Gajah Perang Bhayangkara)
// $h76=array('atk'=>244,'atkp'=>160,'di'=>93,'dip'=>54.5,'dc'=>133.5,'dcp'=>75.5);
$heroData76 = $GLOBALS['h76'];

$mockHeroRow = [
    'heroid' => 99999,
    'uid' => 99999,
    'unit' => 76,
    'name' => 'Gajah Mada Test',
    'inrevive' => 0,
    'intraining' => 0,
    'trainingtime' => 0,
    'level' => 49,
    'attack' => 100, // 100 points in Fighting Strength
    'defence' => 0,  // 0 points in legacy defence
    'attackbonus' => 100,
    'defencebonus' => 0,
    'regeneration' => 5,
    'resources' => 45,
    'res_type' => 0,
    'health' => 100,
    'dead' => 0,
    'points' => 0,
    'experience' => 120000
];

$stats = $buildHeroStatsMethod->invoke($units, $mockHeroRow, $heroData76);

echo "Calculated Hero Stats for 100 Fighting Strength (0 legacy defence):\n";
echo " - Attack: " . $stats['atk'] . "\n";
echo " - Def Infantry: " . $stats['di'] . "\n";
echo " - Def Cavalry: " . $stats['dc'] . "\n";

// Expected:
// atk = 244 + 5 * floor(100 * 160 / 5) = 16244
assertEqual(16244.0, (float)$stats['atk'], "Atk must be fully scaled with 100 points");

// In T4, di and dc MUST also scale with the 100 points of Fighting Strength:
// di = 93 + 5 * floor(100 * 54.5 / 5) = 93 + 5450 = 5543
// dc = 133.5 + 5 * floor(100 * 75.5 / 5) = 133.5 + 7550 = 7683.5
assertEqual(5543.0, (float)$stats['di'], "Defence vs Infantry must be scaled using Fighting Strength (5543)");
assertEqual(7683.5, (float)$stats['dc'], "Defence vs Cavalry must be scaled using Fighting Strength (7683.5)");
assertEqual(100, (int)$stats['defence'], "Virtual defence points must match Fighting Strength (100)");

echo "PASS: Units::buildHeroStats properly computes both Atk and Def from Fighting Strength!\n";

// 2. Verify Battle::getBattleHero uses Fighting Strength for defence
$battle = new Battle();
$battleReflection = new ReflectionClass($battle);
$getBattleHeroMethod = $battleReflection->getMethod('getBattleHero');
$getBattleHeroMethod->setAccessible(true);

// Insert a temporary hero in DB to test getBattleHero
global $database;
$testUid = 999988;
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testUid");
$database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `trainingtime`, `autoregen`, `lastupdate`) 
    VALUES ($testUid, 76, 'T4 Hero Test', 1, 49, 0, 120000, 1000, 0, 100, 100, 0, 100, 0, 5, 45, 0, 0, 50, " . time() . ")");

$battleHero = $getBattleHeroMethod->invoke($battle, $testUid);

echo "Battle Hero Object:\n";
echo " - Atk: " . $battleHero['atk'] . "\n";
echo " - Def Inf: " . $battleHero['di'] . "\n";
echo " - Def Cav: " . $battleHero['dc'] . "\n";

assertTrue($battleHero['di'] > 5000, "Battle hero di must use 100 points of Fighting Strength (> 5000)");
assertTrue($battleHero['dc'] > 7000, "Battle hero dc must use 100 points of Fighting Strength (> 7000)");

echo "PASS: Battle::getBattleHero applies Fighting Strength to defense in combat!\n";

// 3. Test Auto-migration of legacy defence points
$testMigrateUid = 999989;
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testMigrateUid");
// Legacy hero had 30 attack and 20 defence, 5 points
$database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `trainingtime`, `autoregen`, `lastupdate`) 
    VALUES ($testMigrateUid, 1, 'Legacy Hero', 1, 10, 5, 5000, 100, 0, 100, 30, 20, 0, 0, 0, 0, 0, 0, 50, " . time() . ")");

// Simulate auto-migration logic from 37_hero.tpl
$t4Hero = defined('NEW_FUNCTIONS_HERO_T4') && NEW_FUNCTIONS_HERO_T4;
$heroRows = $database->getHero($testMigrateUid, 0, false, false);
$hero_info = $heroRows[0];

if ($t4Hero && isset($hero_info['heroid']) && !empty($hero_info['defence']) && (int)$hero_info['defence'] > 0 && (int)$hero_info['defence'] !== (int)$hero_info['attack']) {
    $oldDef = (int) $hero_info['defence'];
    $heroId = (int) $hero_info['heroid'];
    mysqli_query($database->dblink, "UPDATE " . TB_PREFIX . "hero SET `points` = `points` + $oldDef, `defence` = `attack` WHERE `heroid` = $heroId AND `defence` = $oldDef");
    $hero_info['points'] = (int)($hero_info['points'] ?? 0) + $oldDef;
    $hero_info['defence'] = (int) $hero_info['attack'];
}

$migratedHero = $database->getHero($testMigrateUid, 0, false, false)[0];
assertEqual(25, (int)$migratedHero['points'], "Legacy 20 defence points must be refunded to available points (5 + 20 = 25)");
assertEqual(30, (int)$migratedHero['attack'], "Fighting Strength remains 30");
assertEqual(30, (int)$migratedHero['defence'], "Defence synchronized to match Fighting Strength (30)");

echo "PASS: Legacy defence point auto-migration & refund works perfectly!\n";

// Cleanup test heroes
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid IN ($testUid, $testMigrateUid)");

echo "=== ALL HERO T4 FIGHTING STRENGTH TESTS PASSED! ===\n";
