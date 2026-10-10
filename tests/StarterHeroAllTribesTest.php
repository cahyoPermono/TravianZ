<?php

#################################################################################
##  Filename       : StarterHeroAllTribesTest.php                               ##
##  Type           : Unit Test for Starter Hero across all 8 playable tribes    ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Units.php';

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

// 1. Verify HERO_FROM_START is true
assertTrue(defined('HERO_FROM_START') && HERO_FROM_START, "HERO_FROM_START must be defined and true");

// 2. Test unit mapping for all 8 playable tribes
$expectedUnits = [
    1  => 1,   // Romans -> Legionnaire
    2  => 11,  // Teutons -> Clubswinger
    3  => 21,  // Gauls -> Phalanx
    6  => 51,  // Huns -> Mercenary
    7  => 61,  // Egyptians -> Slave Militia
    8  => 71,  // Spartans -> Hoplite
    9  => 81,  // Vikings -> Thrall
    10 => 91,  // Nusantara -> Pendekar Keris
];

echo "Testing starter hero creation for each tribe...\n";

foreach ($expectedUnits as $tribe => $expectedUnit) {
    $dummyUid = 99000 + $tribe;
    $dummyWid = 88000 + $tribe;
    $username = "TestTribe_{$tribe}";

    // Clean up any test artifact
    $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $dummyUid");
    $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $dummyWid");
    $database->query("INSERT INTO " . TB_PREFIX . "units (`vref`, `hero`) VALUES ($dummyWid, 0)");

    // Call createStarterHero
    $res = Units::createStarterHero($dummyUid, $dummyWid, $tribe, $username);
    assertTrue($res === true, "createStarterHero failed for tribe $tribe");

    // Verify row in hero table
    $heroes = $database->getHero($dummyUid, 0, false, false);
    assertTrue(!empty($heroes), "Hero row missing in DB for tribe $tribe");
    $hero = isset($heroes[0]) ? $heroes[0] : $heroes;
    assertEqual($expectedUnit, (int)$hero['unit'], "Hero unit mismatch for tribe $tribe");
    assertEqual($dummyWid, (int)$hero['wref'], "Hero wref mismatch for tribe $tribe");
    assertEqual(100, (int)$hero['health'], "Hero health must be 100 for tribe $tribe");
    assertEqual(0, (int)$hero['dead'], "Hero must not be dead for tribe $tribe");

    // Verify units.hero garrison flag
    $unitRow = $database->getUnit($dummyWid);
    assertEqual(1, (int)($unitRow['hero'] ?? 0), "units.hero garrison must be 1 for tribe $tribe");

    // Clean up
    $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $dummyUid");
    $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $dummyWid");

    echo "  [OK] Tribe $tribe -> Unit $expectedUnit provisioned successfully\n";
}

echo "PASS: All 8 tribes tested and verified successfully!\n";
