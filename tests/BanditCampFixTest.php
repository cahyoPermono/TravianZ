<?php
/**
 * Test Suite: Bandit Camp Fix & Loot/Clearance Verification
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';
require_once __DIR__ . '/../GameEngine/Automation.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Bandit Camp Fix Unit Test ===\n\n";

$db = $database->dblink;

// 1. Verify profile configuration: no Tier 3 or Tier 4 contains units outside Nature (u31..u40)
$tiers = BanditCamp::getTierProfiles();

foreach ($tiers as $tierId => $tierData) {
    foreach ($tierData['troops'] as $unitId => $amt) {
        assert_true($unitId >= 31 && $unitId <= 40, "Tier $tierId troop unit $unitId is valid Nature unit (u31..u40)");
    }
}

// 2. Test upgradeExistingActiveCamps retrofitting
// Insert a dummy bandit camp with corrupted u42 units
$dummyVid = 999981;
$banditUser = BanditCamp::getBanditUser();
$banditUid = (int)$banditUser['id'];

// Ensure row in vdata and units
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $dummyVid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "units WHERE vref = $dummyVid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "bandit_camps WHERE wref = $dummyVid");

mysqli_query($db, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($dummyVid, $banditUid, 'Test Corrupted Camp', 10000, 10000, 10000, 10000, 80000, 80000)");
mysqli_query($db, "INSERT INTO " . TB_PREFIX . "units (vref, u34, u35, u42) VALUES ($dummyVid, 50, 30, 114)");
mysqli_query($db, "INSERT INTO " . TB_PREFIX . "bandit_camps (wref, tier, name, max_hp, cur_hp, bounty_wood, bounty_clay, bounty_iron, bounty_crop, reward_exp, reward_cp, reward_silver, status, created) VALUES ($dummyVid, 3, 'Sarang Begal Test', 100, 100, 28617, 28617, 28617, 0, 7500, 900, 950, 1, " . time() . ")");

// Run upgradeExistingActiveCamps
BanditCamp::upgradeExistingActiveCamps();

// Check units row
$uRes = mysqli_query($db, "SELECT * FROM " . TB_PREFIX . "units WHERE vref = $dummyVid");
$uRow = mysqli_fetch_assoc($uRes);

assert_true((int)$uRow['u42'] === 0, "Corrupted Thorned Warriors (u42) zeroed out");
assert_true((int)$uRow['u39'] >= 114, "Corrupted Thorned Warriors converted into Harimau (u39)");
assert_true((int)$uRow['u34'] === 50, "Nature unit u34 preserved");
assert_true((int)$uRow['u35'] === 30, "Nature unit u35 preserved");

// 3. Test Battle Resolution defense losses wipeout
// When battle part indicates 100% loss, all units in units table must become 0
$resClass = new ReflectionClass('Automation');
$method = $resClass->getMethod('applyOwnDefenceCasualties');
$method->setAccessible(true);

// Set defender units to test
mysqli_query($db, "UPDATE " . TB_PREFIX . "units SET u34 = 50, u35 = 30, u39 = 114, u42 = 10 WHERE vref = $dummyVid");

// Call applyOwnDefenceCasualties with battlepart[2] = 1.0 (100% casualty)
$battlepart = [0 => 0, 1 => 0, 2 => 1.0];
$attackData = ['to' => $dummyVid];
$auto = $resClass->newInstanceWithoutConstructor();
$method->invokeArgs($auto, [$attackData, 4, $battlepart]);

// Verify DB units are all 0
$uCheck = mysqli_query($db, "SELECT u34, u35, u39, u42 FROM " . TB_PREFIX . "units WHERE vref = $dummyVid");
$uAfter = mysqli_fetch_assoc($uCheck);
assert_true((int)$uAfter['u34'] === 0, "Defense unit u34 reduced to 0 on 100% casualties");
assert_true((int)$uAfter['u35'] === 0, "Defense unit u35 reduced to 0 on 100% casualties");
assert_true((int)$uAfter['u39'] === 0, "Defense unit u39 reduced to 0 on 100% casualties");
assert_true((int)$uAfter['u42'] === 0, "Defense unit u42 (alien unit) also reduced to 0 on 100% casualties (no immortal units)");

// 4. Test onBattleCompleted triggers clearance and awards bonus resources
// Create attacker user & village
$attUid = 999982;
$attVid = 999983;
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "users WHERE id = $attUid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $attVid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "hero WHERE uid = $attUid");

mysqli_query($db, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, cp, gold) VALUES ($attUid, 'CampConqueror', 1, 100, 10)");
mysqli_query($db, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($attVid, $attUid, 'Conqueror Capital', 1000, 1000, 1000, 1000, 80000, 80000)");
mysqli_query($db, "INSERT INTO " . TB_PREFIX . "hero (uid, wref, experience, silver, level, points) VALUES ($attUid, $attVid, 0, 50, 0, 0)");

// Give camp remaining resources
mysqli_query($db, "UPDATE " . TB_PREFIX . "vdata SET wood = 25000, clay = 25000, iron = 25000, crop = 5000 WHERE wref = $dummyVid");

// Ensure camp exists in wdata
mysqli_query($db, "INSERT IGNORE INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($dummyVid, 3, 0, -7, 8, 1, 'f3')");

// Trigger onBattleCompleted
$result = BanditCamp::onBattleCompleted($dummyVid, $attUid, $attVid);

assert_true($result !== null, "onBattleCompleted returned result object");
assert_true((int)$result['exp'] === 7500, "7,500 Hero EXP awarded");
assert_true((int)$result['cp'] === 900, "900 Culture Points awarded");
assert_true((int)$result['silver'] === 950, "950 Silver awarded");

// Verify Hero updated
$hRes = mysqli_query($db, "SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $attUid");
$hRow = mysqli_fetch_assoc($hRes);
assert_true((int)$hRow['experience'] >= 7500, "Attacker hero received EXP in database");
assert_true((int)$hRow['silver'] === 1000, "Attacker hero silver increased by 950 (50 + 950 = 1000)");

// Verify User CP updated
$uRes = mysqli_query($db, "SELECT cp FROM " . TB_PREFIX . "users WHERE id = $attUid");
$uRow = mysqli_fetch_assoc($uRes);
assert_true((int)$uRow['cp'] === 1000, "Attacker user CP increased by 900 (100 + 900 = 1000)");

// Verify Attacker village received the remaining unlooted resources
$vRes = mysqli_query($db, "SELECT wood, clay, iron, crop FROM " . TB_PREFIX . "vdata WHERE wref = $attVid");
$vRow = mysqli_fetch_assoc($vRes);
assert_true((float)$vRow['wood'] >= 26000, "Attacker village received unlooted wood bonus (1000 + 25000 = 26000)");
assert_true((float)$vRow['clay'] >= 26000, "Attacker village received unlooted clay bonus (1000 + 25000 = 26000)");
assert_true((float)$vRow['iron'] >= 26000, "Attacker village received unlooted iron bonus (1000 + 25000 = 26000)");

// Verify Bandit camp row cleared (status = 0)
$cRes = mysqli_query($db, "SELECT status FROM " . TB_PREFIX . "bandit_camps WHERE wref = $dummyVid");
$cRow = mysqli_fetch_assoc($cRes);
assert_true((int)$cRow['status'] === 0, "bandit_camps table marked status = 0 (cleared)");

// Cleanup
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "users WHERE id = $attUid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($dummyVid, $attVid)");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "units WHERE vref = $dummyVid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "bandit_camps WHERE wref = $dummyVid");
mysqli_query($db, "DELETE FROM " . TB_PREFIX . "hero WHERE uid = $attUid");

echo "\n>>> ALL BANDIT CAMP FIX TESTS PASSED SUCCESSFULLY! <<<\n";
