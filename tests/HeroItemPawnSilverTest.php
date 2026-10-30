<?php
/**
 * Test Suite: Hero Item Pawn Shop for Silver
 * Tests BlackMarket::getItemSilverAppraisal() and BlackMarket::sellHeroItemForSilver().
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/hero_items.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../GameEngine/HeroItems.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running HeroItemPawnSilverTest ===\n";

// 1. Test Appraisal Calculation
$cageAppraisal = BlackMarket::getItemSilverAppraisal(208, 10);
assertTest($cageAppraisal['itemid'] === 208, "Item ID is 208 (Cage)");
assertTest($cageAppraisal['unit_silver'] === 5, "Cage unit silver is 5");
assertTest($cageAppraisal['total_silver'] === 50, "10 Cages total silver is 50");

$tier1Appraisal = BlackMarket::getItemSilverAppraisal(1, 1); // Short sword of legionnaire (Tier 1)
assertTest($tier1Appraisal['unit_silver'] === 50, "Tier 1 weapon unit silver is 50");
assertTest($tier1Appraisal['total_silver'] === 50, "1x Tier 1 weapon total silver is 50");

$tier2Appraisal = BlackMarket::getItemSilverAppraisal(2, 1); // Sword of legionnaire (Tier 2)
assertTest($tier2Appraisal['unit_silver'] === 120, "Tier 2 weapon unit silver is 120");

$tier3Appraisal = BlackMarket::getItemSilverAppraisal(3, 1); // Long sword of legionnaire (Tier 3)
assertTest($tier3Appraisal['unit_silver'] === 250, "Tier 3 weapon unit silver is 250");

// 2. Test sellHeroItemForSilver with a test user in database
$testUid = 99991;
$testVref = 1;
$db = $database->dblink;

// Setup test user in users table and hero in hero table
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid = $testUid");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, gold) VALUES ($testUid, 'SilverTester', 'test', 'test@test.com', 1, 2, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, experience, silver) VALUES ($testUid, $testUid, 'TestHero', 1, 1, 0, 100)");

$heroItems = new HeroItems();
// Add 10 cages (itemid 208)
$rowId = $heroItems->addItem($testUid, 208, 10);
assertTest($rowId > 0, "Successfully added 10 cages to test user inventory");

// Initial silver check
$initSilver = BlackMarket::getSilver($testUid);
assertTest($initSilver === 100, "Initial silver balance is 100");

// Test selling 4 cages for silver
$res = BlackMarket::sellHeroItemForSilver($testUid, $testVref, $rowId, 4);
assertTest($res['success'] === true, "Selling 4 cages for silver succeeded: " . $res['message']);

$afterSilver = BlackMarket::getSilver($testUid);
// 100 + (4 * 5 = 20) = 120
assertTest($afterSilver === 120, "Silver balance correctly increased by 20 (now $afterSilver)");

// Remaining cages should be 6
$inv = $heroItems->getInventory($testUid);
$cagesRemaining = 0;
foreach ($inv as $it) {
    if ((int)$it['id'] === (int)$rowId) {
        $cagesRemaining = (int)$it['quantity'];
    }
}
assertTest($cagesRemaining === 6, "Remaining cages count is exactly 6");

// Test over-quantity selling
$resOver = BlackMarket::sellHeroItemForSilver($testUid, $testVref, $rowId, 10);
assertTest($resOver['success'] === false, "Over-quantity sale is rejected");

// Test equipped item protection
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero_items` SET equipped = 1 WHERE id = $rowId");
$resEquipped = BlackMarket::sellHeroItemForSilver($testUid, $testVref, $rowId, 1);
assertTest($resEquipped['success'] === false, "Equipped item sale is rejected");

// Cleanup
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid = $testUid");

echo "=== All HeroItemPawnSilverTest tests passed! ===\n";
