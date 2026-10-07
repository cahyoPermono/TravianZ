<?php

#################################################################################
##  Filename       : BanditCampTest.php                                        ##
##  Type           : Unit Test for BanditCamp module                           ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BanditCamp.php';

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

echo "=== Running BanditCamp Unit Tests ===\n";

// 1. Test tier labels and stars
assertEqual('Sarang Kroco (Tier 1)', BanditCamp::getTierLabel(1), "Tier 1 label matches");
assertEqual('Markas Begal (Tier 2)', BanditCamp::getTierLabel(2), "Tier 2 label matches");
assertEqual('Benteng Gembong (Tier 3)', BanditCamp::getTierLabel(3), "Tier 3 label matches");
assertEqual('WORLD BOSS (Tier 4)', BanditCamp::getTierLabel(4), "Tier 4 label matches");

assertTrue(strpos(BanditCamp::getTierStars(1), '★') !== false, "Tier 1 has stars");
assertTrue(strpos(BanditCamp::getTierStars(4), 'BOSS') !== false, "Tier 4 has BOSS badge in stars");

// 2. Test distance calculation and wrapping
$d1 = BanditCamp::getDistance(0, 0, 3, 4);
assertEqual(5.0, round($d1, 2), "Euclidean distance (0,0) to (3,4) is 5.0");

$dWrap = BanditCamp::getDistance(200, 0, -200, 0);
assertTrue($dWrap <= 401, "Distance wrapping handles world boundary");

// 3. Test system account retrieval
$user = BanditCamp::getBanditUser();
assertTrue(!empty($user['id']), "Bandit system user exists with valid ID");
assertEqual('Gembong Bandit', $user['username'], "Bandit system user name is 'Gembong Bandit'");
assertEqual(4, (int)$user['tribe'], "Bandit tribe is 4 (Nature)");

// 4. Test active camps retrieval
$camps = BanditCamp::getActiveCamps();
assertTrue(is_array($camps), "Active camps returns an array");
assertTrue(count($camps) > 0, "At least one bandit camp is active on map");

$hasBoss = false;
foreach ($camps as $c) {
    if ((int)$c['tier'] === 4) {
        $hasBoss = true;
        break;
    }
}
assertTrue($hasBoss, "World Boss is among active camps");

// 5. Test camp spawn and clearing lifecycle
echo "Testing dynamic spawn and clearance lifecycle...\n";
$testCamp = BanditCamp::spawnCamp(BanditCamp::TIER_OUTPOST);
assertTrue(!empty($testCamp), "Successfully spawned test camp");
$wref = (int)$testCamp['wref'];
$campId = (int)$testCamp['id'];

// Check village and units exist
$vdataRow = $database->getVillage($wref);
assertTrue(!empty($vdataRow), "Village row created for camp in vdata");
$unitRow = $database->getUnit($wref);
assertTrue(!empty($unitRow), "Units row created for camp in units");

// Zero out guards to simulate victory
$database->query("UPDATE " . TB_PREFIX . "units SET u31 = 0, u32 = 0, u34 = 0 WHERE vref = $wref");

// Run checkCleared
$cleared = BanditCamp::checkCleared();
$wasCleared = false;
foreach ($cleared as $cl) {
    if ((int)$cl['id'] === $campId) {
        $wasCleared = true;
        break;
    }
}
assertTrue($wasCleared, "Camp was recognized as cleared when troops reached 0");

// Verify tile was freed
$wdataCheck = $database->getMInfo($wref);
assertEqual(0, (int)$wdataCheck['occupied'], "Map tile occupied flag reset to 0 after clear");

// Verify status marked 0 in bandit_camps
$statusCheck = $database->query_return("SELECT status FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId");
assertEqual('0', (string)$statusCheck[0]['status'], "Camp status updated to 0");

echo "All BanditCamp Unit Tests Passed Successfully!\n";
