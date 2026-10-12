<?php

#################################################################################
##  Filename       : StarterHeroTest.php                                       ##
##  Type           : Unit Test for T4-style Starter Hero feature               ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Units.php';

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

// 1. Verify HERO_FROM_START is enabled
assertTrue(defined('HERO_FROM_START') && HERO_FROM_START, "HERO_FROM_START must be enabled in config");

// 2. Verify Units::createStarterHero exists as a callable
assertTrue(is_callable(['Units', 'createStarterHero']), "Units::createStarterHero must be callable");

// 3. Verify that createStarterHero does not create a duplicate hero when current hero is dead
$testUid = 99998;
$testWid = 88898;
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testUid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testWid");
$database->query("INSERT INTO " . TB_PREFIX . "units (`vref`, `hero`) VALUES ($testWid, 0)");

// Create starter hero first
$res1 = Units::createStarterHero($testUid, $testWid, 1, 'DeadHeroTest');
assertTrue($res1 === true, "createStarterHero initial creation failed");

// Mark the hero as dead (dead = 1, health = 0)
$database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0 WHERE uid = $testUid");

// Calling createStarterHero again must return false and NOT insert a new row
$res2 = Units::createStarterHero($testUid, $testWid, 1, 'DeadHeroTest');
assertTrue($res2 === false, "createStarterHero must return false when hero is dead");

// Verify total hero rows for this user is still exactly 1
$cntRes = mysqli_fetch_assoc($database->query("SELECT COUNT(*) as cnt FROM " . TB_PREFIX . "hero WHERE uid = $testUid"));
assertEqual(1, (int)$cntRes['cnt'], "Must not duplicate hero when hero is dead");

// Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $testUid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testWid");

echo "PASS StarterHero unit tests passed." . PHP_EOL;

