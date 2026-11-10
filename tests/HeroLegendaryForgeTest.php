<?php
/**
 * Test Suite: Hero Legendary Equipment (Tier 4) & Crafting Forge
 * Tests:
 * 1. Catalog integrity of Tier 4 universal legendary items and World Essence.
 * 2. World Essence drops from Bandit Camps and World Boss contribution sharing.
 * 3. HeroForge crafting logic, ingredient validation, and atomic resource/item consumption.
 * 4. Multi-tribe universal equipping without tribe restrictions.
 * 5. HeroBattleBonus integration for fighting strength and all-units bonus.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/hero_items.php';
require_once __DIR__ . '/../GameEngine/HeroItems.php';
require_once __DIR__ . '/../GameEngine/HeroForge.php';
require_once __DIR__ . '/../GameEngine/HeroBattleBonus.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running HeroLegendaryForgeTest ===\n";

global $database, $heroItemCatalog;
$db = $database->dblink;

$testUid = 8891;
$testVref = 88911;

// -------------------------------------------------------------
// Test 1: Catalog Integrity & Text Generation
// -------------------------------------------------------------
echo "\n--- Test 1: Catalog & Text Generation ---\n";
assertTest(isset($heroItemCatalog[210]), "Item 210 (World Essence) exists in catalog");
assertTest((int)$heroItemCatalog[210]['tier'] === 4, "World Essence tier is 4");
assertTest((int)$heroItemCatalog[210]['slot'] === HSLOT_BAG, "World Essence slot is HSLOT_BAG");

$recipes = HeroForge::getRecipes();
assertTest(count($recipes) >= 14, "HeroForge has at least 14 Tier 4 craftable recipes (got " . count($recipes) . ")");

// Verify weapons are universal (no 'unit' key)
assertTest(isset($heroItemCatalog[406]), "Item 406 (Sovereign's Warblade) exists");
assertTest(!isset($heroItemCatalog[406]['unit']), "Item 406 has no tribe-specific unit binding");
assertTest(function_exists('heroItemTribe') && heroItemTribe(406) === 0, "heroItemTribe(406) returns 0 (Universal)");

$txt406 = heroItemBonusText(406);
assertTest(strpos($txt406, '3000') !== false, "Item 406 bonus text contains 3000 fighting strength");
assertTest(strpos($txt406, '4') !== false, "Item 406 bonus text contains +4 unit bonus");

// -------------------------------------------------------------
// Test 2: Bandit World Essence Rewards
// -------------------------------------------------------------
echo "\n--- Test 2: World Essence Drop & Boss Pool Sharing ---\n";

$bossCampId = 88991;
$bossWref   = 88992;
$attacker1  = 8892;
$attacker2  = 8893;
$vil1       = 88921;
$vil2       = 88931;

// Cleanup test records
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref IN ($testVref, $vil1, $vil2, $bossWref)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id IN ($testVref, $vil1, $vil2, $bossWref)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $bossCampId OR wref = $bossWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $bossCampId");

// Setup 2 attackers for World Boss
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($attacker1, 'Attacker1', 'p', 'a1@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level) VALUES ($attacker1, $attacker1, 'Hero1', 1, 1)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vil1, 3, 0, 20, 20, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($vil1, $attacker1, 'V1', 1, 100, 0, 1000, 1000, 1000, 1000, 100000, 100000)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($attacker2, 'Attacker2', 'p', 'a2@test.com', 2, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level) VALUES ($attacker2, $attacker2, 'Hero2', 11, 1)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vil2, 3, 0, 21, 21, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($vil2, $attacker2, 'V2', 1, 100, 0, 1000, 1000, 1000, 1000, 100000, 100000)");

// Setup World Boss camp
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($bossWref, 3, 0, 25, 25, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($bossWref, 3, 'World Boss Camp', 0, 100, 0, 50000, 50000, 50000, 50000, 100000, 100000)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bandit_camps` (id, wref, tier, name, max_hp, cur_hp, bounty_wood, bounty_clay, bounty_iron, bounty_crop, reward_exp, reward_cp, reward_silver, status, created) VALUES ($bossCampId, $bossWref, 4, 'Naga Siluman World Boss', 10000, 10000, 50000, 50000, 50000, 50000, 10000, 1000, 2000, 1, " . time() . ")");

// Record attacks: Attacker1 did 7000 dmg (70%), Attacker2 did 3000 dmg (30%)
BanditCamp::ensureTable();
BanditCamp::logAttackContribution($bossCampId, $attacker1, 7000);
BanditCamp::logAttackContribution($bossCampId, $attacker2, 3000);

// Clear camp with Attacker2 landing last-hit
$bossData = mysqli_fetch_assoc(mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $bossCampId LIMIT 1"));
$clearRes = BanditCamp::rewardAndClearCamp($bossData, $attacker2, 'Attacker2', $vil2);
assertTest(!empty($clearRes), "World Boss camp cleared successfully");

// Check World Essence distribution:
// Total pool = 80 Essence.
// Attacker1 (70%): round(0.70 * 80) = 56 Essence.
// Attacker2 (30% + last hit bonus 3): round(0.30 * 80) + 3 = 24 + 3 = 27 Essence.
$ess1 = HeroForge::getEssenceCount($attacker1);
$ess2 = HeroForge::getEssenceCount($attacker2);

assertTest($ess1 === 56, "Attacker1 (70% damage) received 56 World Essence (got $ess1)");
assertTest($ess2 === 27, "Attacker2 (30% damage + 3 last hit bonus) received 27 World Essence (got $ess2)");

// -------------------------------------------------------------
// Test 3: HeroForge Crafting Requirements & Execution
// -------------------------------------------------------------
echo "\n--- Test 3: HeroForge Crafting Requirements & Execution ---\n";

// Create user and village for crafting tests
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($testUid, 'MasterSmith', 'p', 'ms@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level) VALUES ($testUid, $testUid, 'SmithHero', 1, 1)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($testVref, 3, 0, 30, 30, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($testVref, $testUid, 'SmithVil', 1, 100, 0, 150000, 150000, 150000, 120000, 200000, 200000)");

$heroItems = new HeroItems();

// Give player 35 World Essence
$heroItems->addItem($testUid, 210, 35);
$initialEssence = HeroForge::getEssenceCount($testUid);
assertTest($initialEssence === 35, "Player has 35 World Essence in bag");

// Give player 1x Tier 3 sword (103: Imperian Long Sword) and 2x Tier 2 swords (102: Legionnaire Sword, 105: Praetorian Sword)
$t3RowId = $heroItems->addItem($testUid, 103, 1);
$t2RowId1 = $heroItems->addItem($testUid, 102, 1);
$t2RowId2 = $heroItems->addItem($testUid, 105, 1);

// Also give an irrelevant Tier 3 helmet (3: Helmet of Wisdom) to test slot mismatch
$t3HelmetRowId = $heroItems->addItem($testUid, 3, 1);

// Test checking requirements for 406 (Sovereign's Warblade - Slot 3)
$chk = HeroForge::checkRequirements($testUid, $testVref, 406);
assertTest($chk['can_craft'] === true, "checkRequirements returns true when all ingredients are present");
assertTest($chk['has_t3'] === true && $chk['has_t2'] === true, "checkRequirements detects eligible T3 and T2 weapons");
assertTest($chk['has_essence'] === true && $chk['has_resources'] === true, "checkRequirements detects enough essence and resources");

// Test failed craft: Wrong slot item (Helmet used for Weapon forge)
$failSlot = HeroForge::craft($testUid, $testVref, 406, $t3HelmetRowId, [$t2RowId1, $t2RowId2]);
assertTest($failSlot['success'] === false, "Crafting fails when using wrong slot item");

// Test failed craft: Duplicate Tier 2 item IDs passed
$failDup = HeroForge::craft($testUid, $testVref, 406, $t3RowId, [$t2RowId1, $t2RowId1]);
assertTest($failDup['success'] === false, "Crafting fails when duplicate T2 items are provided");

// Test successful craft of Sovereign's Warblade (406)
$craftSuccess = HeroForge::craft($testUid, $testVref, 406, $t3RowId, [$t2RowId1, $t2RowId2]);
assertTest($craftSuccess['success'] === true, "Crafting Sovereign's Warblade succeeded: " . ($craftSuccess['message'] ?? ''));

// Verify consumed items:
$checkT3 = mysqli_fetch_assoc(mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "hero_items` WHERE id = $t3RowId"));
$checkT2_1 = mysqli_fetch_assoc(mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "hero_items` WHERE id = $t2RowId1"));
$checkT2_2 = mysqli_fetch_assoc(mysqli_query($db, "SELECT id FROM `" . TB_PREFIX . "hero_items` WHERE id = $t2RowId2"));
assertTest(empty($checkT3), "Sacrificed Tier 3 item was consumed from DB");
assertTest(empty($checkT2_1) && empty($checkT2_2), "Sacrificed Tier 2 items were consumed from DB");

// Verify World Essence deducted by exactly 30: 35 - 30 = 5 remaining
$remEssence = HeroForge::getEssenceCount($testUid);
assertTest($remEssence === 5, "World Essence was reduced from 35 to 5 (got $remEssence)");

// Verify resources deducted: 150k - 100k = 50k wood/clay/iron, 120k - 80k = 40k crop
$vResAfter = mysqli_fetch_assoc(mysqli_query($db, "SELECT wood, clay, iron, crop FROM `" . TB_PREFIX . "vdata` WHERE wref = $testVref"));
assertTest((int)$vResAfter['wood'] === 50000, "Village wood deducted by 100k (now " . $vResAfter['wood'] . ")");
assertTest((int)$vResAfter['crop'] === 40000, "Village crop deducted by 80k (now " . $vResAfter['crop'] . ")");

// Verify newly crafted Tier 4 item exists in player's inventory
$newItemId = (int)$craftSuccess['rowid'];
$craftedRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT itemid, slot FROM `" . TB_PREFIX . "hero_items` WHERE id = $newItemId AND uid = $testUid"));
assertTest(!empty($craftedRow) && (int)$craftedRow['itemid'] === 406, "Crafted Tier 4 weapon (406) exists in inventory");

// -------------------------------------------------------------
// Test 4: Universal Equipping & Multi-Tribe Usability
// -------------------------------------------------------------
echo "\n--- Test 4: Universal Equipping Across Tribes ---\n";

// Roman hero equips Sovereign's Warblade
$equipOkRoman = $heroItems->equipItem($testUid, $newItemId);
assertTest($equipOkRoman === true, "Roman hero can equip Sovereign's Warblade");

// Change tribe of player to Teuton (tribe 2) and Gaul (tribe 3) and verify equip still succeeds
mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET tribe = 2 WHERE id = $testUid");
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET unit = 11 WHERE uid = $testUid");
$heroItems->unequipItem($testUid, $newItemId);
$equipOkTeuton = $heroItems->equipItem($testUid, $newItemId);
assertTest($equipOkTeuton === true, "Teuton hero can equip Sovereign's Warblade (Universal)");

mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET tribe = 3 WHERE id = $testUid");
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET unit = 21 WHERE uid = $testUid");
$heroItems->unequipItem($testUid, $newItemId);
$equipOkGaul = $heroItems->equipItem($testUid, $newItemId);
assertTest($equipOkGaul === true, "Gaul hero can equip Sovereign's Warblade (Universal)");

// -------------------------------------------------------------
// Test 5: HeroBattleBonus Combat Integration
// -------------------------------------------------------------
echo "\n--- Test 5: HeroBattleBonus Combat Integration ---\n";

$heroItems->equipItem($testUid, $newItemId);
$bonuses = $heroItems->getBonuses($testUid);

assertTest($bonuses[HB_FIGHT] === 3000, "HB_FIGHT bonus is +3000");
assertTest($bonuses[HB_ALL_UNITS] === 4, "HB_ALL_UNITS bonus is +4");

$cavalryLookup = [4 => true, 5 => true, 6 => true, 15 => true, 16 => true, 23 => true, 24 => true, 25 => true, 26 => true];
$army = ['u21' => 100, 'u24' => 50]; // 100 Phalanx (infantry), 50 Theutates Thunder (cavalry)

list($addedAp, $addedCap) = HeroBattleBonus::unitOffense($testUid, $army, $cavalryLookup);
// 100 Phalanx * 4 = 400 AP; 50 Theutates * 4 = 200 CAP
assertTest($addedAp === 400, "HeroBattleBonus added +400 infantry AP for 100 phalanx (got $addedAp)");
assertTest($addedCap === 200, "HeroBattleBonus added +200 cavalry CAP for 50 TT (got $addedCap)");

$addedDef = HeroBattleBonus::unitDefense($testUid, $army);
// (100 + 50) * 4 = 600 defense
assertTest($addedDef === 600, "HeroBattleBonus added +600 total defense for all 150 units (got $addedDef)");

// Cleanup test database
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid IN ($testUid, $attacker1, $attacker2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref IN ($testVref, $vil1, $vil2, $bossWref)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id IN ($testVref, $vil1, $vil2, $bossWref)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $bossCampId OR wref = $bossWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $bossCampId");

echo "\n=== All HeroLegendaryForgeTest tests passed successfully! ===\n";
