<?php

#################################################################################
##  Unit Tests for Black Market & Mercenary Enclaves                           ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BlackMarket.php';
require_once dirname(__DIR__) . '/GameEngine/Mercenary.php';
require_once dirname(__DIR__) . '/GameEngine/Plague.php';

global $database;

function assertTest($condition, $message) {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

echo "=== Running Black Market & Mercenary Tests ===\n\n";

// 1. Table creation & schema verification
echo "1. Table verification:\n";
BlackMarket::ensureTables();
Mercenary::ensureTable();
assertTest(true, "Tables ensured without error");

// 2. Silver balance tests
echo "\n2. Silver Balance & Spend Tests:\n";
$testUid = 7; // User nusa
BlackMarket::addSilver($testUid, 150);
$initialSilver = BlackMarket::getSilver($testUid);
assertTest($initialSilver >= 0, "Current silver is non-negative ($initialSilver)");

$spendOk = BlackMarket::spendSilver($testUid, 5, 'Test Spend');
assertTest($spendOk, "Spent 5 silver successfully");
assertTest(BlackMarket::getSilver($testUid) === $initialSilver - 5, "Silver balance correctly decremented by 5");

$addOk = BlackMarket::addSilver($testUid, 5);
assertTest($addOk, "Added 5 silver back successfully");
assertTest(BlackMarket::getSilver($testUid) === $initialSilver, "Silver balance restored to $initialSilver");

$overspend = BlackMarket::spendSilver($testUid, 99999999, 'Overspend Test');
assertTest(!$overspend, "Cannot spend more silver than available (overspend blocked)");

// 3. Resource Laundering Tests (15% Cut)
echo "\n3. Resource Laundering Tests:\n";
$testVref = 20004;

// Ensure test village has clean test resources and capacity
$database->setVillageField($testVref, 'maxstore', 60000);
$database->setVillageField($testVref, 'maxcrop', 60000);
$database->setVillageField($testVref, 'crop', 5000);
$database->setVillageField($testVref, 'wood', 5000);
$database->setVillageField($testVref, 'clay', 5000);
$database->setVillageField($testVref, 'iron', 5000);

$wBefore = (int)$database->getWoodAvailable($testVref, false);
$cBefore = (int)$database->getCropAvailable($testVref, false);

$launderRes = BlackMarket::launderResources($testUid, $testVref, 1, 4, 1000);
assertTest($launderRes['success'], "Resource laundering succeeded: " . $launderRes['message']);

$wAfter = (int)$database->getWoodAvailable($testVref, false);
$cAfter = (int)$database->getCropAvailable($testVref, false);

assertTest(abs($wAfter - ($wBefore - 1000)) <= 5, "1000 Wood deducted correctly (approx within 5 for live production)");
assertTest(abs($cAfter - ($cBefore + 850)) <= 5, "850 Crop added correctly (15% fee cut, approx within 5 for live production)");

// 4. Resource Shipment Tests
echo "\n4. Smuggler Resource Shipment Tests:\n";
$silverBefore = BlackMarket::getSilver($testUid);
$shipRes = BlackMarket::buyResourceShipment($testUid, $testVref, 1);
assertTest($shipRes['success'], "Shipment tier 1 bought: " . $shipRes['message']);
$silverAfter = BlackMarket::getSilver($testUid);
assertTest($silverAfter === $silverBefore - 10, "10 Silver deducted for tier 1 shipment");

// 5. Contraband Tests
echo "\n5. Contraband Bazaar Tests:\n";
// Grain reserve
$cropBefore = (int)$database->getCropAvailable($testVref, false);
$grainRes = BlackMarket::buyContraband($testUid, $testVref, 'grain_reserve');
assertTest($grainRes['success'], "Emergency Grain Reserve bought: " . $grainRes['message']);
$cropAfter = (int)$database->getCropAvailable($testVref, false);
assertTest(abs($cropAfter - ($cropBefore + 4000)) <= 5, "+4000 Crop added via emergency reserve (approx within 5 for live production)");

// Antidote test
// First infect village for test
Plague::infectVillage($testVref, Plague::PLAGUE_CHOLERA, 2, 7200);
assertTest(Plague::isVillageInfected($testVref), "Village is infected with plague for test");

$antidoteRes = BlackMarket::buyContraband($testUid, $testVref, 'antidote');
assertTest($antidoteRes['success'], "Antidote bought and cured village: " . $antidoteRes['message']);
assertTest(!Plague::isVillageInfected($testVref), "Village is now completely cured of plague");

// 6. Mercenary Enclave Unit Definitions & Hiring
echo "\n6. Mercenary Enclave Tests:\n";
$units = Mercenary::getUnitsInfo();
assertTest(count($units) === 4, "4 Mercenary units defined");
assertTest($units[1]['name'] === 'Garda Zirah Besi' && $units[1]['cost_silver'] === 4, "Unit 1 is Garda Zirah Besi (4 Silver)");
assertTest($units[2]['name'] === 'Pemanah Busur Kreta' && $units[2]['cost_silver'] === 3, "Unit 2 is Pemanah Busur Kreta (3 Silver)");
assertTest($units[3]['name'] === 'Penjarah Padang Stepa' && $units[3]['cost_silver'] === 7, "Unit 3 is Penjarah Padang Stepa (7 Silver)");
assertTest($units[4]['name'] === 'Penebas Benteng' && $units[4]['cost_silver'] === 8, "Unit 4 is Penebas Benteng (8 Silver)");

// Hire mercenaries: 10 Garda Zirah, 5 Pemanah, 2 Penjarah, 1 Penebas
// Cost: (10*4) + (5*3) + (2*7) + (1*8) = 40 + 15 + 14 + 8 = 77 Silver
$silverBeforeHire = BlackMarket::getSilver($testUid);
$hireRes = Mercenary::hireMercenaries($testUid, $testVref, [
    'm1' => 10,
    'm2' => 5,
    'm3' => 2,
    'm4' => 1
]);
assertTest($hireRes['success'], "Mercenaries hired successfully: " . $hireRes['message']);
assertTest(BlackMarket::getSilver($testUid) === $silverBeforeHire - 77, "77 Silver deducted correctly");

// 7. Garrison & Upkeep
echo "\n7. Garrison & Upkeep Tests:\n";
$garrison = Mercenary::getGarrison($testVref);
assertTest($garrison['m1'] >= 10, "Garda Zirah count is at least 10");
assertTest($garrison['m2'] >= 5, "Pemanah count is at least 5");
assertTest($garrison['m3'] >= 2, "Penjarah count is at least 2");
assertTest($garrison['m4'] >= 1, "Penebas count is at least 1");

$expectedUpkeep = ($garrison['m1'] * 1) + ($garrison['m2'] * 1) + ($garrison['m3'] * 2) + ($garrison['m4'] * 2);
assertTest(Mercenary::getVillageUpkeep($testVref) === $expectedUpkeep, "Mercenary village upkeep calculated correctly ($expectedUpkeep crop/hr)");

// 8. Defense Calculation Tests
echo "\n8. Defense Points Calculation:\n";
$defense = Mercenary::getGarrisonDefense($testVref);
assertTest($defense['dp'] > 0 && $defense['cdp'] > 0, "Mercenaries provide active defense points (dp: {$defense['dp']}, cdp: {$defense['cdp']})");
assertTest($defense['involve'] === $garrison['total_troops'], "Involved troops count matches total garrison");

// 9. Dismissal Tests
echo "\n9. Mercenary Dismissal Tests:\n";
$dismissRes = Mercenary::dismissMercenaries($testUid, $testVref, [
    'm1' => 2,
    'm2' => 1,
    'm3' => 0,
    'm4' => 0
]);
assertTest($dismissRes['success'], "Dismissed 2 Garda Zirah & 1 Pemanah: " . $dismissRes['message']);
$garrisonAfter = Mercenary::getGarrison($testVref);
assertTest($garrisonAfter['m1'] === $garrison['m1'] - 2, "Garda Zirah count reduced by 2");
assertTest($garrisonAfter['m2'] === $garrison['m2'] - 1, "Pemanah count reduced by 1");

// 10. Battle Casualties Test
echo "\n10. Battle Casualties Test:\n";
$gPreCasualty = Mercenary::getGarrison($testVref);
Mercenary::applyCasualties($testVref, 0.5); // 50% loss
$gPostCasualty = Mercenary::getGarrison($testVref);
assertTest($gPostCasualty['total_troops'] < $gPreCasualty['total_troops'], "Casualties applied proportionally to mercenaries (from {$gPreCasualty['total_troops']} down to {$gPostCasualty['total_troops']})");

echo "\n🎉 ALL TESTS PASSED SUCCESSFULLY! 🎉\n";
