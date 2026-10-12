<?php

#################################################################################
##  Filename       : OrmasTest.php                                             ##
##  Type           : Unit Test for Ormas (Village Thug / Nuisance) Module      ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Ormas.php';

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

echo "=== Running Ormas & Thug Nuisance Unit Tests ===\n";

// 1. Table schema & metadata tests
echo "1. Testing Metadata for all 4 Ormas Types...\n";
$infoPP = Ormas::getOrmasInfo(Ormas::TYPE_PP);
assertEqual('Pemuda Pancasila', $infoPP['name'], "Type 1 is Pemuda Pancasila");
assertTrue(!empty($infoPP['bribe']), "PP has bribe demands");
assertEqual(15, $infoPP['min_troops_fight'], "PP requires 15 troops to fight");

$infoBanser = Ormas::getOrmasInfo(Ormas::TYPE_BANSER);
assertEqual('Banser Pencari Rawon di Kondangan', $infoBanser['name'], "Type 2 is Banser");
assertEqual(20, $infoBanser['min_troops_fight'], "Banser requires 20 troops to fight");

$infoHerkules = Ormas::getOrmasInfo(Ormas::TYPE_HERKULES);
assertEqual('Komplotan Herkules', $infoHerkules['name'], "Type 3 is Komplotan Herkules");
assertEqual(25, $infoHerkules['min_troops_fight'], "Herkules requires 25 troops to fight");

$infoSetempat = Ormas::getOrmasInfo(Ormas::TYPE_SETEMPAT);
assertEqual('Ormas Setempat', $infoSetempat['name'], "Type 4 is Ormas Setempat");
assertEqual(10, $infoSetempat['min_troops_fight'], "Ormas Setempat requires 10 troops to fight");

// 2. Dispatch, retrieval, and dismiss tests
echo "2. Testing Dispatch, Expiry, and Dismiss...\n";
$testVid = 999991;

// Clean prior test state
Ormas::dismissOrmas($testVid);
assertTrue(!Ormas::isVillageAfflicted($testVid), "Village 999991 starts unaffected");
assertEqual(null, Ormas::getVillageOrmas($testVid), "getVillageOrmas returns null when unaffected");

// Dispatch PP with 3600 seconds duration (1 hour)
$ok = Ormas::dispatchOrmas($testVid, Ormas::TYPE_PP, 3600);
assertTrue($ok, "Dispatching PP succeeds");
assertTrue(Ormas::isVillageAfflicted($testVid), "Village is now afflicted");

$active = Ormas::getVillageOrmas($testVid);
assertTrue(!empty($active), "Active row returned");
assertEqual(Ormas::TYPE_PP, (int)$active['ormas_type'], "Afflicted with Type 1 PP");
assertTrue((int)$active['end_time'] > time(), "end_time is in future");

// 3. Multiplier & debuff verification for PP (Type 1)
echo "3. Testing Debuff Multipliers for Pemuda Pancasila...\n";
assertEqual(0.75, Ormas::getProductionMultiplier($testVid, 'wood'), "PP applies -25% wood production (0.75x)");
assertEqual(0.75, Ormas::getProductionMultiplier($testVid, 'clay'), "PP applies -25% clay production (0.75x)");
assertEqual(0.85, Ormas::getProductionMultiplier($testVid, 'crop'), "PP applies -15% crop production (0.85x)");
assertEqual(1.00, Ormas::getProductionMultiplier($testVid, 'iron'), "PP does not penalize iron (1.00x)");
assertEqual(1.20, Ormas::getBuildTimeMultiplier($testVid), "PP slows construction time by +20% (1.20x)");

// 4. Multiplier & debuff verification for Banser (Type 2)
echo "4. Testing Debuff Multipliers for Banser...\n";
Ormas::dispatchOrmas($testVid, Ormas::TYPE_BANSER, 7200);
assertEqual(40, Ormas::getExtraCropUpkeep($testVid), "Banser consumes 40 extra crop upkeep/hour");
assertEqual(0.70, Ormas::getCulturePointMultiplier($testVid), "Banser reduces CP production by -30% (0.70x)");
assertEqual(1.50, Ormas::getCelebrationCostMultiplier($testVid), "Banser increases celebration cost by +50% (1.50x)");

// 5. Multiplier & debuff verification for Komplotan Herkules (Type 3)
echo "5. Testing Debuff Multipliers for Komplotan Herkules...\n";
Ormas::dispatchOrmas($testVid, Ormas::TYPE_HERKULES, 7200);
assertEqual(0.65, Ormas::getMerchantSpeedMultiplier($testVid), "Herkules slows merchant speed by -35% (0.65x)");
assertEqual(0.75, Ormas::getProductionMultiplier($testVid, 'iron'), "Herkules cuts iron production by -25% (0.75x)");
assertEqual(0.75, Ormas::getProductionMultiplier($testVid, 'wood'), "Herkules cuts wood production by -25% (0.75x)");
assertEqual(0.85, Ormas::getCarryCapacityMultiplier($testVid), "Herkules cuts carry capacity by -15% (0.85x)");

// 6. Multiplier & debuff verification for Ormas Setempat (Type 4)
echo "6. Testing Debuff Multipliers for Ormas Setempat...\n";
Ormas::dispatchOrmas($testVid, Ormas::TYPE_SETEMPAT, 7200);
assertEqual(0.85, Ormas::getProductionMultiplier($testVid, 'wood'), "Setempat cuts all production by -15% (wood: 0.85x)");
assertEqual(0.85, Ormas::getProductionMultiplier($testVid, 'clay'), "Setempat cuts all production by -15% (clay: 0.85x)");
assertEqual(0.85, Ormas::getProductionMultiplier($testVid, 'iron'), "Setempat cuts all production by -15% (iron: 0.85x)");
assertEqual(0.85, Ormas::getProductionMultiplier($testVid, 'crop'), "Setempat cuts all production by -15% (crop: 0.85x)");
assertEqual(0.90, Ormas::getAttackBonusMultiplier($testVid), "Setempat reduces troop attack power by -10% (0.90x)");

// 7. Dismissal test
echo "7. Testing Dismissal...\n";
$dismissOk = Ormas::dismissOrmas($testVid);
assertTrue($dismissOk, "Dismissing Ormas succeeds");
assertTrue(!Ormas::isVillageAfflicted($testVid), "Village is no longer afflicted after dismissal");
assertEqual(1.0, Ormas::getProductionMultiplier($testVid, 'wood'), "Production multiplier reverts to 1.0x");
assertEqual(0, Ormas::getExtraCropUpkeep($testVid), "Extra crop upkeep reverts to 0");

// 8. Test bribe and fight mechanics
echo "8. Testing Bribe and Fight Mechanics...\n";
Ormas::dispatchOrmas($testVid, Ormas::TYPE_SETEMPAT, 7200);
$resBribeNonExistent = Ormas::bribeOrmas(99999999, 1);
assertEqual(false, $resBribeNonExistent['success'], "Bribe on non-existent village fails gracefully");

$resFightNonExistent = Ormas::fightOrmas(99999999, 1);
assertEqual(false, $resFightNonExistent['success'], "Fight on non-existent village fails gracefully");

// 9. Test Admin listing, bulk dismiss, and forced spawn
echo "9. Testing Admin Listing & Bulk Dismiss...\n";
$activeList = Ormas::getAllActiveOrmas();
assertTrue(is_array($activeList), "getAllActiveOrmas returns array");

$cleared = Ormas::dismissAll();
assertTrue($cleared >= 1, "dismissAll cleared at least testVid");
assertTrue(!Ormas::isVillageAfflicted($testVid), "testVid is cleared after dismissAll");

$spawnRes = Ormas::processSpawns(true);
assertTrue(is_int($spawnRes), "processSpawns(true) returns integer count");

// Clean up
Ormas::dismissOrmas($testVid);

echo "\nAll Ormas Unit Tests Passed Successfully!\n";


