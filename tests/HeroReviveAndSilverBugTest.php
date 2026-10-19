<?php

#################################################################################
##  Filename       : HeroReviveAndSilverBugTest.php                            ##
##  Type           : Unit Test for Hero Multi-Row & Silver Desync Bug Fix      ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Units.php';
require_once dirname(__DIR__) . '/GameEngine/HeroItems.php';
require_once dirname(__DIR__) . '/GameEngine/BlackMarket.php';

global $database;

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

$testUid = 88881;
$testWid = 77771;

// Clean up any test artifacts first
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testUid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testWid");
$database->query("INSERT INTO " . TB_PREFIX . "units (`vref`, `hero`) VALUES ($testWid, 0)");

// ==============================================================================
// Scenario: Player has dead Phalanx Level 3 with 2500 Silver
// Then trains a Theutates Thunder (unit 24), creating duplicate hero row with 0 Silver
// ==============================================================================

$now = time();

// Row 1: Dead Phalanx Level 3 with 2500 Silver
$database->query("INSERT INTO " . TB_PREFIX . "hero 
    (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `lastupdate`, `trainingtime`, `inrevive`, `intraining`)
    VALUES ($testUid, 21, 'TestHero', $testWid, 3, 15, 350, 2500, 1, 0, $now, 0, 0, 0)");

// Row 2: Newly trained Theutates Thunder unit 24 with 0 Silver, intraining = 1
$trainingEndTime = $now + 3600;
$database->query("INSERT INTO " . TB_PREFIX . "hero 
    (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `lastupdate`, `trainingtime`, `inrevive`, `intraining`)
    VALUES ($testUid, 24, 'TestHero', $testWid, 0, 5, 0, 0, 0, 100, $now + 10, $trainingEndTime, 0, 1)");

// 1. Verify that HeroItems::getSilver returns the real silver (2500), not 0
$heroItems = new HeroItems();
$silverFromItems = $heroItems->getSilver($testUid);
assertEqual(2500, $silverFromItems, "HeroItems::getSilver must return 2500 despite duplicate row with 0 silver");

// 2. Verify that BlackMarket::getSilver returns the real silver (2500), not 0
$silverFromBM = BlackMarket::getSilver($testUid);
assertEqual(2500, $silverFromBM, "BlackMarket::getSilver must return 2500 despite duplicate row with 0 silver");

// 3. Run self-healing cleanupDuplicateHeroes
Units::cleanupDuplicateHeroes($testUid);

// 4. Verify that duplicate rows are consolidated to exactly 1 row
$cntRes = mysqli_fetch_assoc($database->query("SELECT COUNT(*) as cnt FROM " . TB_PREFIX . "hero WHERE uid = $testUid"));
assertEqual(1, (int)$cntRes['cnt'], "cleanupDuplicateHeroes must consolidate to exactly 1 hero row");

// 5. Verify the consolidated hero properties
$heroRes = mysqli_fetch_assoc($database->query("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $testUid"));
assertEqual(3, (int)$heroRes['level'], "Consolidated hero must retain level 3");
assertEqual(350, (int)$heroRes['experience'], "Consolidated hero must retain 350 experience");
assertEqual(2500, (int)$heroRes['silver'], "Consolidated hero must retain 2500 silver");
assertEqual(24, (int)$heroRes['unit'], "Consolidated hero must adopt unit 24 (Theutates Thunder) from training");
assertEqual(1, (int)$heroRes['intraining'], "Consolidated hero must be in training status");
assertEqual($trainingEndTime, (int)$heroRes['trainingtime'], "Consolidated hero must retain training countdown");

// 6. Test when training time has passed: self-healing should make hero alive & ready
$database->query("UPDATE " . TB_PREFIX . "hero SET trainingtime = " . ($now - 60) . " WHERE uid = $testUid");
// Add an accidental duplicate row again to test auto-completion
$database->query("INSERT INTO " . TB_PREFIX . "hero 
    (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `lastupdate`, `trainingtime`, `inrevive`, `intraining`)
    VALUES ($testUid, 24, 'TestHero', $testWid, 0, 5, 0, 0, 0, 100, $now + 20, " . ($now - 60) . ", 0, 1)");

Units::cleanupDuplicateHeroes($testUid);

$heroRes2 = mysqli_fetch_assoc($database->query("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $testUid"));
assertEqual(0, (int)$heroRes2['dead'], "Hero must be alive after training time passed");
assertEqual(0, (int)$heroRes2['intraining'], "Hero must no longer be in training after time passed");
assertEqual(100.0, (float)$heroRes2['health'], "Hero must have 100 health after training completed");

// Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testUid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testWid");

echo "PASS HeroReviveAndSilverBugTest passed successfully." . PHP_EOL;
