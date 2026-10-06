<?php

#################################################################################
##  Filename       : BotAITest.php                                             ##
##  Type           : Unit Test for BotAI module                                ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

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

// 1. Test tribe unit mappings
assertEqual(1,  BotAI::getTribeBasicUnit(1), "Roman basic unit is u1");
assertEqual(11, BotAI::getTribeBasicUnit(2), "Teuton basic unit is u11");
assertEqual(21, BotAI::getTribeBasicUnit(3), "Gaul basic unit is u21");
assertEqual(51, BotAI::getTribeBasicUnit(6), "Hun basic unit is u51");
assertEqual(61, BotAI::getTribeBasicUnit(7), "Egyptian basic unit is u61");
assertEqual(71, BotAI::getTribeBasicUnit(8), "Spartan basic unit is u71");
assertEqual(81, BotAI::getTribeBasicUnit(9), "Viking basic unit is u81");

// 2. Test wall types
assertEqual(31, BotAI::getTribeWallType(1), "Roman wall is 31");
assertEqual(32, BotAI::getTribeWallType(2), "Teuton wall is 32");
assertEqual(33, BotAI::getTribeWallType(3), "Gaul wall is 33");
assertEqual(42, BotAI::getTribeWallType(6), "Hun wall is 42");
assertEqual(43, BotAI::getTribeWallType(7), "Egyptian wall is 43");
assertEqual(47, BotAI::getTribeWallType(8), "Spartan wall is 47");
assertEqual(50, BotAI::getTribeWallType(9), "Viking wall is 50");

// 3. Test distance calculation (including world boundary wrap-around)
$d1 = BotAI::getDistance(0, 0, 3, 4);
assertEqual(5.0, round($d1, 2), "Distance between (0,0) and (3,4) is 5.0");

// Wrap around world edges
$d2 = BotAI::getDistance(WORLD_MAX, 0, -WORLD_MAX, 0);
assertEqual(1.0, round($d2, 2), "Wrap-around distance across world edges is 1.0");

// 4. Test storage capacity scaling
assertEqual(1200, BotAI::getStorageCapacity(1), "Warehouse lvl 1 gives 1200 capacity");
assertEqual(1700, BotAI::getStorageCapacity(2), "Warehouse lvl 2 gives 1700 capacity");
assertEqual(80000, BotAI::getStorageCapacity(20), "Warehouse lvl 20 gives 80000 capacity");

// 5. Test population computation
assertTrue(BotAI::buildingPOP(15, 1) > 0, "Main building lvl 1 has positive pop");
assertTrue(BotAI::buildingPOP(1, 1) > 0, "Woodcutter lvl 1 has positive pop");

echo "PASS BotAI unit tests passed.\n";
