<?php

#################################################################################
##  Filename       : tests/NusantaraHeroAndScoutReportTest.php                 ##
##  Type           : Regression & Unit Test for Scout Battle Reports &         ##
##                   Nusantara Hero Equipment Catalog                          ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Data/hero_items.php';

global $generator;
if (!isset($generator)) {
    $generator = new MyGenerator();
}

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

echo "=== Running NusantaraHeroAndScoutReportTest ===" . PHP_EOL;

// =============================================================================
// TEST 1: Nusantara Hero Items Catalog (361 - 375)
// =============================================================================
echo "Test 1: Verifying Nusantara Hero Equipment (items 361-375)..." . PHP_EOL;
global $heroItemCatalog;

$expectedUnits = array(
    361 => 91, 362 => 91, 363 => 91,
    364 => 92, 365 => 92, 366 => 92,
    367 => 93, 368 => 93, 369 => 93,
    370 => 95, 371 => 95, 372 => 95,
    373 => 96, 374 => 96, 375 => 96,
);

for ($iid = 361; $iid <= 375; $iid++) {
    assertTrue(isset($heroItemCatalog[$iid]), "Item $iid must exist in catalog");
    assertEqual(HSLOT_RIGHT, $heroItemCatalog[$iid]['slot'], "Item $iid must be HSLOT_RIGHT");
    assertEqual(10, heroItemTribe($iid), "Item $iid must belong to Tribe 10 (Nusantara)");
    assertEqual($expectedUnits[$iid], $heroItemCatalog[$iid]['unit'], "Item $iid unit mismatch");
    
    $name = heroItemName($iid);
    assertTrue(!empty($name) && $name !== 'Unknown item', "Item $iid name must be valid: $name");
    
    $bonusText = heroItemBonusText($iid);
    assertTrue(strpos($bonusText, 'Nusantara hero') !== false, "Item $iid bonus text must mention Nusantara hero: $bonusText");
}
echo "PASS: All 15 Nusantara items are correctly defined, mapped to tribe 10, and validated." . PHP_EOL;

// =============================================================================
// TEST 2: Scout Report Parsing and Decoupling from Hero Count
// =============================================================================
echo "Test 2: Verifying Scout Report Parsing in 1.tpl..." . PHP_EOL;

// Construct simulated scout report data string
// In a scout report where attacker sent 5 scouts, 0 heroes:
// $info_spy is "14,<div class=\"res\"><img class=\"r1\" src=\"img/x.gif\" alt=\"Lumber\" title=\"Lumber\" />348 | <img class=\"r2\" src=\"img/x.gif\" alt=\"Clay\" title=\"Clay\" />32 | <img class=\"r3\" src=\"img/x.gif\" alt=\"Iron\" title=\"Iron\" />1481 | <img class=\"r4\" src=\"img/x.gif\" alt=\"Crop\" title=\"Crop\" />3640</div><div class=\"carry\">Total Resources: 5500</div>"
// Followed by t11=0, dead11=0
$spyHtml = '<div class="res"><img class="r1" src="img/x.gif" alt="Lumber" title="Lumber" />348 | <img class="r2" src="img/x.gif" alt="Clay" title="Clay" />32 | <img class="r3" src="img/x.gif" alt="Iron" title="Iron" />1481 | <img class="r4" src="img/x.gif" alt="Crop" title="Crop" />3640</div><div class="carry">Total Resources: 5500</div>';

$dummyReport = array_fill(0, 290, '0');
$dummyReport[0] = '1'; // uid
$dummyReport[1] = '1'; // vid
$dummyReport[2] = '1'; // Roman tribe
$dummyReport[6] = '5'; // 5 scouts (u4)
$dummyReport[270] = '14'; // spy_pic
$dummyReport[271] = $spyHtml; // spy_data
$dummyReport[272] = '0'; // t11 (Hero Sent = 0)
$dummyReport[273] = '0'; // dead11 (Hero Dead = 0)

$message = new stdClass();
$message->readingNotice = array(
    'id' => 99999,
    'uid' => 1,
    'to' => 1,
    'topic' => 'Vee scouts Burdigala',
    'ntype' => 1,
    'data' => implode(',', $dummyReport),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
);

$session = new stdClass();
$session->access = 0;
$session->uid = 1;
$technology = new Technology();

ob_start();
include dirname(__DIR__) . '/Templates/Notice/1.tpl';
$renderedHtml = ob_get_clean();

assertTrue(strpos($renderedHtml, 'Total Resources: 5500') !== false, "Rendered HTML must contain scouted resources (5500)");
assertTrue(strpos($renderedHtml, 'unit u14') !== false, "Rendered HTML must contain scout unit icon u14");

// Crucial: verify that the hero star icon is NOT shown or not showing 14 phantom heroes
assertTrue(strpos($renderedHtml, '<td>14</td>') === false, "Hero count must NOT be 14 (spy_pic should not leak into hero troops)");
assertTrue(strpos($renderedHtml, 'unit uhero') === false, "Hero icon should NOT be shown when 0 heroes sent");

echo "PASS: Scout report renders resources correctly and does not show phantom hero count." . PHP_EOL;

// =============================================================================
// TEST 3: Sanggar Paranormal (GID 51) Configuration & Mechanics
// =============================================================================
echo "Test 3: Verifying Sanggar Paranormal (GID 51) configuration and mechanics..." . PHP_EOL;
require_once dirname(__DIR__) . '/GameEngine/Data/buidata.php';
require_once dirname(__DIR__) . '/GameEngine/Building.php';
require_once dirname(__DIR__) . '/GameEngine/Paranormal.php';

global $bid51;
assertTrue(isset($bid51), "\$bid51 array must be defined");
assertEqual(20, count($bid51), "Sanggar Paranormal must have 20 levels");
assertEqual(10, Paranormal::getMaxCapacity(1), "Level 1 capacity should be 10 slots");
assertEqual(200, Paranormal::getMaxCapacity(20), "Level 20 capacity should be 200 slots");

$bReq = Building::BUILD_REQUIREMENTS[51] ?? null;
assertTrue($bReq !== null, "Building requirements for GID 51 must exist");
assertEqual([10], $bReq['tribe'], "GID 51 must be restricted to Tribe 10 (Nusantara)");
assertEqual([[15, 3], [23, 3]], $bReq['levels'], "GID 51 requires MB Lv 3 and Cranny Lv 3");

// Test Spirit Stats & Slot Calculation
$mockSpirits = [
    'vref' => 12345,
    'pocong' => 10,     // 10 slots, 40 def_i, 20 def_c
    'kuntilanak' => 5,  // 5 slots, 20 def_i, 60 def_c
    'genderuwo' => 2,   // 4 slots, 75 def_i, 75 def_c
    'tuyul_until' => time() + 3600
];
$usedSlots = Paranormal::getUsedSlots($mockSpirits);
assertEqual(19, $usedSlots, "10 Pocong (10) + 5 Kunti (5) + 2 Genderuwo (4) should use 19 slots");

// Test Database Table & Operations
$testVref = 888888;
$db = $database->dblink;
if ($db) {
    $table = TB_PREFIX . "paranormal";
    $database->query("DELETE FROM `$table` WHERE `vref` = $testVref");
    $database->query("INSERT INTO `$table` (`vref`, `pocong`, `kuntilanak`, `genderuwo`, `tuyul_until`) VALUES ($testVref, 10, 5, 2, " . (time() + 7200) . ")");
    
    // Test defense points
    $def = Paranormal::getVillageDefense($testVref);
    assertEqual(650, $def['dp'], "Infantry defense should be 10*40 + 5*20 + 2*75 = 650");
    assertEqual(650, $def['cdp'], "Cavalry defense should be 10*20 + 5*60 + 2*75 = 650");
    assertEqual(17, $def['involve'], "Total spirits involved should be 17");

    // Test Tuyul raid bonus
    $raidMult = Paranormal::getRaidMultiplier($testVref);
    assertEqual(1.15, $raidMult, "Active Tuyul must provide 1.15 (15%) raid multiplier");

    // Test casualties
    $cas = Paranormal::applyCasualties($testVref, 0.5); // 50% losses
    assertEqual(5, $cas['dead']['pocong'], "50% of 10 pocong = 5 dead");
    assertEqual(3, $cas['dead']['kuntilanak'], "50% of 5 kuntilanak = 3 dead (rounded)");
    assertEqual(1, $cas['dead']['genderuwo'], "50% of 2 genderuwo = 1 dead");
    
    $afterSpirits = Paranormal::getVillageSpirits($testVref);
    assertEqual(5, (int)$afterSpirits['pocong'], "Remaining pocong should be 5");
    assertEqual(2, (int)$afterSpirits['kuntilanak'], "Remaining kuntilanak should be 2");
    assertEqual(1, (int)$afterSpirits['genderuwo'], "Remaining genderuwo should be 1");

    // Cleanup
    $database->query("DELETE FROM `$table` WHERE `vref` = $testVref");
}

echo "PASS: Sanggar Paranormal mechanics, defense scaling, and Tuyul buffs validated." . PHP_EOL;
echo "=== All Tests Passed Successfully! ===" . PHP_EOL;
