<?php

#################################################################################
##  Filename       : StarterHeroTest.php                                       ##
##  Type           : Unit Test for T4-style Starter Hero feature               ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
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

// 1. Verify HERO_FROM_START is enabled
assertTrue(defined('HERO_FROM_START') && HERO_FROM_START, "HERO_FROM_START must be enabled in config");

// 2. Verify Units::createStarterHero exists as a callable
assertTrue(is_callable(['Units', 'createStarterHero']), "Units::createStarterHero must be callable");

echo "PASS StarterHero unit tests passed." . PHP_EOL;
