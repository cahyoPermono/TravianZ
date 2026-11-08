<?php
/**
 * Test: Multi-Tribe Troop Movement & Reinforcement (e.g. Teuton user owning Egyptian village with Temple Guardians)
 */

$_SERVER['REQUEST_URI'] = '/login.php';
$_SERVER['SCRIPT_NAME'] = 'login.php';
$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['HTTP_HOST'] = 'localhost';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

$conn = $database->dblink;

echo "=== Running MultiTribe Troop Reinforcement Test ===\n";

$uid = 999810;
$vidEgy = 999811;
$vidTarget = 999812;

// Clean up
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidEgy, $vidTarget)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "enforcement WHERE `from` = $vidEgy OR `vref` = $vidTarget");

// User Teuton (tribe = 2)
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access) VALUES ($uid, 'TeutonGeneral', 2, 2)");

// Village 1: Egyptian village owned by Teuton (tribe = 7)
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, tribe, name, capital, pop) VALUES ($vidEgy, $uid, 7, 'EgyptianConquest', 0, 100)");

// Village 2: Target village / fortress
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, tribe, name, capital, pop) VALUES ($vidTarget, 91016, 5, 'Benteng Kuno', 0, 250)");

// Test 1: getVillageTribe returns 7 for Egyptian village owned by Teuton
$vTribe = $database->getVillageTribe($vidEgy);
assert_true($vTribe === 7, "getVillageTribe correctly identifies Egyptian tribe (7) instead of user tribe (2)");

// Test 2: addEnforce maps t3 to u63 (Temple Guardian) and NOT u13 (Axeman)
$reinfData = [
    'from' => $vidEgy,
    'to' => $vidTarget,
    't1' => 0,
    't2' => 0,
    't3' => 35, // 35 Temple Guardians
    't4' => 0,
    't5' => 0,
    't6' => 0,
    't7' => 0,
    't8' => 0,
    't9' => 0,
    't10' => 0,
    't11' => 0
];
$database->addEnforce($reinfData);

$res = mysqli_query($conn, "SELECT * FROM " . TB_PREFIX . "enforcement WHERE `from` = $vidEgy AND `vref` = $vidTarget LIMIT 1");
assert_true($res && mysqli_num_rows($res) > 0, "Enforcement record created");
$row = mysqli_fetch_assoc($res);

assert_true((int)$row['u63'] === 35, "Temple Guardian stored in u63 (expected 35, got " . (int)$row['u63'] . ")");
assert_true((int)$row['u13'] === 0, "Axeman slot u13 remains 0 (got " . (int)$row['u13'] . ")");

// Test 3: Self-healing test - artificially corrupt an enforcement row by setting u13 = 35 and u63 = 0, then run heal
mysqli_query($conn, "UPDATE " . TB_PREFIX . "enforcement SET u13 = 35, u63 = 0 WHERE id = " . (int)$row['id']);
Bastion::ensureTables();

$healedRes = mysqli_query($conn, "SELECT * FROM " . TB_PREFIX . "enforcement WHERE id = " . (int)$row['id']);
$healedRow = mysqli_fetch_assoc($healedRes);
assert_true((int)$healedRow['u63'] === 35, "Self-healing successfully migrated u13 back to u63 (expected 35, got " . (int)$healedRow['u63'] . ")");
assert_true((int)$healedRow['u13'] === 0, "Self-healing zeroed out the mismatched u13 slot (got " . (int)$healedRow['u13'] . ")");

// Cleanup
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidEgy, $vidTarget)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "enforcement WHERE `from` = $vidEgy OR `vref` = $vidTarget");

echo "\nAll MultiTribe Troop Reinforcement tests passed successfully!\n";

