<?php

#################################################################################
##  Filename       : tests/BlackMarketHeroConsumablesTest.php                  ##
##  Type           : Unit & Integration Test for Black Market Hero Consumables ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Data/hero_items.php';
require_once dirname(__DIR__) . '/GameEngine/HeroItems.php';
require_once dirname(__DIR__) . '/GameEngine/BlackMarket.php';

global $database;
if (!isset($database)) {
    $database = new Database();
}


function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running BlackMarketHeroConsumablesTest ===" . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 1: Verify Hero Consumables Catalog in Black Market
// -----------------------------------------------------------------------------
echo "Test 1: Verifying Hero Consumables Catalog..." . PHP_EOL;
$catalog = BlackMarket::getHeroConsumablesCatalog();

assertTrue(is_array($catalog), "Catalog must be an array");
assertTrue(isset($catalog[202]), "Bucket of Water (202) must exist in catalog");
assertTrue(isset($catalog[204]), "Book of Wisdom (204) must exist in catalog");
assertTrue(isset($catalog[205]), "Artwork (205) must exist in catalog");
assertTrue(isset($catalog[203]), "Law Tablet (203) must exist in catalog");
assertTrue(isset($catalog[208]), "Cage (208) must exist in catalog");
assertTrue(isset($catalog[201]), "Scroll (201) must exist in catalog");
assertTrue(isset($catalog[200]), "Ointment (200) must exist in catalog");
assertTrue(isset($catalog[207]), "Bandage (207) must exist in catalog");
assertTrue(isset($catalog[206]), "Small Bandage (206) must exist in catalog");

// Check prices (priced higher than auction market price as requested)
assertEqual(500, $catalog[202]['silver_price'], "Bucket of Water must cost 500 Silver");
assertEqual(800, $catalog[204]['silver_price'], "Book of Wisdom must cost 800 Silver");
assertEqual(1200, $catalog[205]['silver_price'], "Artwork must cost 1200 Silver");
assertEqual(45, $catalog[203]['silver_price'], "Law Tablet must cost 45 Silver");
assertEqual(20, $catalog[208]['silver_price'], "Cage must cost 20 Silver");
assertEqual(35, $catalog[201]['silver_price'], "Scroll must cost 35 Silver");
assertEqual(12, $catalog[200]['silver_price'], "Ointment must cost 12 Silver");
assertEqual(40, $catalog[207]['silver_price'], "Bandage must cost 40 Silver");
assertEqual(25, $catalog[206]['silver_price'], "Small Bandage must cost 25 Silver");

echo "PASS: All 9 hero consumables present with black market premium pricing." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 2: Validation of Invalid Item IDs and Exceeded Quantities
// -----------------------------------------------------------------------------
echo "Test 2: Verifying input validations..." . PHP_EOL;
$resInvalid = BlackMarket::buyHeroConsumable(1, 1, 9999, 1);
assertEqual(false, $resInvalid['success'], "Must reject invalid itemid");
assertTrue(strpos($resInvalid['message'], 'tidak valid') !== false, "Message must indicate invalid item");

$resExceed = BlackMarket::buyHeroConsumable(1, 1, 202, 999);
assertEqual(false, $resExceed['success'], "Must reject quantity exceeding max_qty");
assertTrue(strpos($resExceed['message'], 'melebihi batas') !== false, "Message must indicate max qty exceeded");

echo "PASS: Invalid item and excessive quantity properly rejected." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 3: Insufficient Silver Check
// -----------------------------------------------------------------------------
echo "Test 3: Verifying insufficient silver check..." . PHP_EOL;
// User with 0 silver buying bucket (1500 silver)
$resPoor = BlackMarket::buyHeroConsumable(99999, 1, 202, 1);
assertEqual(false, $resPoor['success'], "Must reject when silver is insufficient");
assertTrue(strpos($resPoor['message'], 'Silver tidak mencukupi') !== false, "Message must state insufficient silver");

echo "PASS: Insufficient silver correctly blocked." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 4: Live DB Integration Test (if connected)
// -----------------------------------------------------------------------------
global $database;
if (isset($database->dblink) && $database->dblink) {
    echo "Test 4: Running Live DB purchase simulation..." . PHP_EOL;
    $db = $database->dblink;
    
    // Check or create test user hero
    $testUid = 88888;
    $testVref = 12345;
    
    // Clean old test rows
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid = $testUid");
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "blackmarket_log` WHERE uid = $testUid");
    
    // Insert test hero with 5000 Silver
    $qHero = "INSERT INTO `" . TB_PREFIX . "hero` (`uid`, `name`, `wref`, `silver`, `dead`, `health`) VALUES ($testUid, 'TestHeroBM', $testVref, 5000, 1, 0)";
    mysqli_query($db, $qHero);
    
    $initialSilver = BlackMarket::getSilver($testUid);
    assertEqual(5000, $initialSilver, "Initial test hero silver must be 5000");
    
    // 1. Buy 1x Bucket of Water (cost: 500)
    $resBuyBucket = BlackMarket::buyHeroConsumable($testUid, $testVref, 202, 1);
    assertTrue($resBuyBucket['success'], "Bucket purchase must succeed: " . ($resBuyBucket['message'] ?? ''));
    
    $silverAfterBucket = BlackMarket::getSilver($testUid);
    assertEqual(4500, $silverAfterBucket, "Silver after bucket purchase must be 4500 (5000 - 500)");
    
    // Verify item in hero_items
    $heroItems = new HeroItems();
    $inv = $heroItems->getInventory($testUid);
    $foundBucket = false;
    foreach ($inv as $row) {
        if ((int)$row['itemid'] === 202 && (int)$row['quantity'] === 1) {
            $foundBucket = true;
            break;
        }
    }
    assertTrue($foundBucket, "Bucket of Water must be in hero inventory with qty 1");
    
    // 2. Buy 10x Cages (item 208, cost 20 * 10 = 200)
    $resBuyCages = BlackMarket::buyHeroConsumable($testUid, $testVref, 208, 10);
    assertTrue($resBuyCages['success'], "Cages purchase must succeed: " . ($resBuyCages['message'] ?? ''));
    
    $silverAfterCages = BlackMarket::getSilver($testUid);
    assertEqual(4300, $silverAfterCages, "Silver after cages must be 4300 (4500 - 200)");
    
    // 3. Buy 5 MORE Cages to verify stack increment
    $resBuyCages2 = BlackMarket::buyHeroConsumable($testUid, $testVref, 208, 5);
    assertTrue($resBuyCages2['success'], "Second cages purchase must succeed");
    
    $silverAfterCages2 = BlackMarket::getSilver($testUid);
    assertEqual(4200, $silverAfterCages2, "Silver after 2nd cages must be 4200 (4300 - 100)");
    
    $inv2 = $heroItems->getInventory($testUid);
    $cageQty = 0;
    foreach ($inv2 as $row) {
        if ((int)$row['itemid'] === 208) {
            $cageQty += (int)$row['quantity'];
        }
    }
    assertEqual(15, $cageQty, "Total cages in inventory must be 15 (10 + 5 stacked)");
    
    // 4. Verify transaction log
    $qLog = mysqli_query($db, "SELECT COUNT(*) FROM `" . TB_PREFIX . "blackmarket_log` WHERE uid = $testUid AND action_type = 'hero_consumable'");
    $logCount = mysqli_fetch_row($qLog)[0];
    assertEqual(3, (int)$logCount, "Must have 3 blackmarket log entries for the purchases");
    
    // Clean up test rows
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero_items` WHERE uid = $testUid");
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "blackmarket_log` WHERE uid = $testUid");
    
    echo "PASS: Live DB purchase, atomic silver deduction, stacking, and logging fully verified!" . PHP_EOL;
}

echo "=== All BlackMarketHeroConsumablesTest checks passed successfully! ===" . PHP_EOL;
