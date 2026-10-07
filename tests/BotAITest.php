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
assertEqual(91, BotAI::getTribeBasicUnit(10), "Nusantara basic unit is u91");

// 2. Test wall types
assertEqual(31, BotAI::getTribeWallType(1), "Roman wall is 31");
assertEqual(32, BotAI::getTribeWallType(2), "Teuton wall is 32");
assertEqual(33, BotAI::getTribeWallType(3), "Gaul wall is 33");
assertEqual(42, BotAI::getTribeWallType(6), "Hun wall is 42");
assertEqual(43, BotAI::getTribeWallType(7), "Egyptian wall is 43");
assertEqual(47, BotAI::getTribeWallType(8), "Spartan wall is 47");
assertEqual(50, BotAI::getTribeWallType(9), "Viking wall is 50");
assertEqual(32, BotAI::getTribeWallType(10), "Nusantara wall is 32");

// 3. Test structured tribe units (getTribeUnits)
$nusantaraUnits = BotAI::getTribeUnits(10);
assertEqual(91, $nusantaraUnits['basic'], "Nusantara basic is 91 (Pendekar Keris)");
assertEqual(93, $nusantaraUnits['inf_adv'], "Nusantara adv inf is 93 (Jawara Golok)");
assertEqual(94, $nusantaraUnits['scout'], "Nusantara scout is 94 (Telik Sandi)");
assertEqual(95, $nusantaraUnits['cav_light'], "Nusantara light cav is 95 (Berkuda Panah)");
assertEqual(96, $nusantaraUnits['cav_heavy'], "Nusantara heavy cav is 96 (Gajah Perang)");
assertEqual(10, count($nusantaraUnits['all']), "Nusantara has 10 units (u91-u100)");

$romanUnits = BotAI::getTribeUnits(1);
assertEqual(1, $romanUnits['basic'], "Roman basic is 1");
assertEqual(3, $romanUnits['inf_adv'], "Roman adv inf is 3");
assertEqual(5, $romanUnits['cav_light'], "Roman light cav is 5");
assertEqual(6, $romanUnits['cav_heavy'], "Roman heavy cav is 6");

$teutonUnits = BotAI::getTribeUnits(2);
assertEqual(11, $teutonUnits['basic'], "Teuton basic is 11");
assertEqual(13, $teutonUnits['inf_adv'], "Teuton adv inf is 13");
assertEqual(15, $teutonUnits['cav_light'], "Teuton light cav is 15");
assertEqual(16, $teutonUnits['cav_heavy'], "Teuton heavy cav is 16");

$gaulUnits = BotAI::getTribeUnits(3);
assertEqual(21, $gaulUnits['basic'], "Gaul basic is 21");
assertEqual(22, $gaulUnits['inf_adv'], "Gaul adv inf is 22");
assertEqual(24, $gaulUnits['cav_light'], "Gaul light cav is 24");
assertEqual(26, $gaulUnits['cav_heavy'], "Gaul heavy cav is 26");

// 4. Test unit speed lookups
assertEqual(7,  BotAI::getUnitSpeed(91), "Pendekar Keris speed is 7");
assertEqual(13, BotAI::getUnitSpeed(95), "Berkuda Panah speed is 13");
assertEqual(9,  BotAI::getUnitSpeed(96), "Gajah Perang speed is 9");
assertEqual(6,  BotAI::getUnitSpeed(1),  "Legionnaire speed is 6");
assertEqual(14, BotAI::getUnitSpeed(5),  "Equites Imperatoris speed is 14");
assertEqual(19, BotAI::getUnitSpeed(24), "Theutates Thunder speed is 19");

// 5. Test dynamic scaling army calculation
$baseMax = defined('BOT_AI_MAX_TROOPS') ? (int)BOT_AI_MAX_TROOPS : 45;
$popFactor = defined('BOT_AI_TROOP_POP_FACTOR') ? (float)BOT_AI_TROOP_POP_FACTOR : 1.8;
$maxCap = defined('BOT_AI_MAX_TROOPS_CAP') ? (int)BOT_AI_MAX_TROOPS_CAP : 3000;

// Pop 20 (fresh bot): floored at baseMax (45)
$calcPop20 = max($baseMax, min($maxCap, (int)round(20 * $popFactor)));
assertEqual(45, $calcPop20, "Pop 20 village army ceiling is floored at 45");

// Pop 200 (growing bot): 200 * 1.8 = 360
$calcPop200 = max($baseMax, min($maxCap, (int)round(200 * $popFactor)));
assertEqual(360, $calcPop200, "Pop 200 village army ceiling scales to 360");

// Pop 800 (mid/late bot): 800 * 1.8 = 1440
$calcPop800 = max($baseMax, min($maxCap, (int)round(800 * $popFactor)));
assertEqual(1440, $calcPop800, "Pop 800 village army ceiling scales to 1440");

// Pop 2500 (metropolis): capped at maxCap (3000)
$calcPop2500 = max($baseMax, min($maxCap, (int)round(2500 * $popFactor)));
assertEqual(3000, $calcPop2500, "Pop 2500 village army ceiling is capped at 3000");

// 6. Test distance calculation (including world boundary wrap-around)
$d1 = BotAI::getDistance(0, 0, 3, 4);
assertEqual(5.0, round($d1, 2), "Distance between (0,0) and (3,4) is 5.0");

// Wrap around world edges
$d2 = BotAI::getDistance(WORLD_MAX, 0, -WORLD_MAX, 0);
assertEqual(1.0, round($d2, 2), "Wrap-around distance across world edges is 1.0");

// 7. Test storage capacity scaling
assertEqual(1200, BotAI::getStorageCapacity(1), "Warehouse lvl 1 gives 1200 capacity");
assertEqual(1700, BotAI::getStorageCapacity(2), "Warehouse lvl 2 gives 1700 capacity");
assertEqual(80000, BotAI::getStorageCapacity(20), "Warehouse lvl 20 gives 80000 capacity");

// 8. Test population computation
assertTrue(BotAI::buildingPOP(15, 1) > 0, "Main building lvl 1 has positive pop");
assertTrue(BotAI::buildingPOP(1, 1) > 0, "Woodcutter lvl 1 has positive pop");

echo "PASS BotAI unit tests passed.\n";
