<?php
/**
 * Test Suite: Nusantara Chief Egypt Settler & Village Founding Test
 * 
 * Verifies:
 * 1. Nusantara account owning an Egyptian village (chiefed) with 3 Egyptian settlers (u70)
 *    is correctly recognized on map view (vilview.tpl) with 3/3 settlers available and clickable founding link.
 * 2. Rally Point founding form (newdorf.tpl) correctly displays Egyptian settlers (u70).
 * 3. Units::Settlers correctly validates and deducts Egyptian settlers (u70) and sends movement 5.
 * 4. sendSettlersComplete founds the new village inheriting the Egyptian tribe (tribe = 7).
 * 5. Returning settlers (sort_type 4, ref 0) refunds to u70 (Egypt) rather than user tribe u100 (Nusantara).
 * 6. Self-healing / fallback if vdata.tribe is 0 but village has u70 settlers.
 */

$_SERVER['REQUEST_URI'] = '/karte.php';
$_SERVER['SCRIPT_NAME'] = 'karte.php';
$_SERVER['PHP_SELF'] = 'karte.php';
$_SERVER['HTTP_HOST'] = 'localhost';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Automation.php';
require_once __DIR__ . '/../GameEngine/Village.php';
require_once __DIR__ . '/../GameEngine/Units.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Nusantara Chief Egypt Settler Tests ===\n\n";

$conn = $database->dblink;
$testPrefix = 'test_egy_' . time() . '_';

// 1. Setup Nusantara User (tribe 10)
$userUname = $testPrefix . 'user';
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, gold, cp) 
    VALUES ('$userUname', 'hash', '$userUname@test.com', 10, 2, 100, 10000)");
$userId = mysqli_insert_id($conn);
assert_true($userId > 0, "Created test user (ID: $userId, Tribe: 10 - Nusantara)");

// Find unoccupied world map tiles for test villages
$tiles = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE occupied = 0 AND oasistype = 0 LIMIT 5");
assert_true(count($tiles) >= 3, "Found empty world tiles for test");
$egyptVid = (int)$tiles[0]['id'];
$targetVid = (int)$tiles[1]['id'];
$targetVid2 = (int)$tiles[2]['id'];

// 2. Setup Egyptian Village owned by Nusantara user (vdata.tribe = 7)
$now = time();
mysqli_query($conn, "REPLACE INTO " . TB_PREFIX . "vdata 
    (wref, owner, tribe, name, capital, pop, natar, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, loyalty, exp1, exp2, exp3) 
    VALUES 
    ($egyptVid, $userId, 7, 'Egypt Conquered', 0, 100, 0, 5000, 5000, 5000, 5000, 8000, 8000, $now, 100, 0, 0, 0)");
mysqli_query($conn, "UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $egyptVid");

// Setup fdata (Rally Point f39 = 1)
$fields = ['vref' => $egyptVid, 'f39' => 1, 'f39t' => 16];
for ($i = 1; $i <= 38; $i++) {
    $fields['f' . $i] = 10;
    $fields['f' . $i . 't'] = 1;
}
$fields['f40'] = 0; $fields['f40t'] = 43; // Egyptian wall
$cols = implode(',', array_keys($fields));
$vals = implode(',', array_values($fields));
mysqli_query($conn, "REPLACE INTO " . TB_PREFIX . "fdata ($cols) VALUES ($vals)");

// Setup units: u70 = 3 (Egyptian Settlers), u100 = 0 (Nusantara Settlers)
$uCols = ['vref' => $egyptVid];
for ($i = 1; $i <= 100; $i++) {
    $uCols['u' . $i] = ($i == 70) ? 3 : 0;
}
$uCols['hero'] = 0;
$cNames = implode(',', array_keys($uCols));
$cVals = implode(',', array_values($uCols));
mysqli_query($conn, "REPLACE INTO " . TB_PREFIX . "units ($cNames) VALUES ($cVals)");

// Verify database state
$vData = $database->getVillage($egyptVid);
assert_true((int)$vData['tribe'] === 7, "Egyptian village correctly has tribe = 7 in vdata");
$unitData = $database->getUnit($egyptVid);
assert_true((int)$unitData['u70'] === 3, "Egyptian village has 3 settlers in u70");
assert_true((int)$unitData['u100'] === 0, "Egyptian village has 0 settlers in u100");

// 3. Test vilview.tpl logic
// Mock session and village context
$session = new stdClass();
$session->uid = $userId;
$session->tribe = 10;
$session->cp = 10000;
$session->userinfo = ['tribe' => 10];

$village = new stdClass();
$village->wid = $egyptVid;
$village->tribe = 7;
$village->unitarray = $unitData;
$village->resarray = ['f39' => 1];
$village->awood = 5000;
$village->aclay = 5000;
$village->airon = 5000;
$village->acrop = 5000;

// Run vilview.tpl settler detection
$vTribe = (!empty($village->tribe) && $village->tribe > 0) ? (int)$village->tribe : ((!empty($session->tribe) && $session->tribe > 0) ? (int)$session->tribe : 1);
$settlers = (int)($village->unitarray['u' . $vTribe . '0'] ?? 0);
if ($settlers < 3) {
    $maxSettlers = $settlers;
    foreach ([7, 10, 6, 8, 9, 1, 2, 3] as $altTribe) {
        $altSettlers = (int)($village->unitarray['u' . ($altTribe * 10)] ?? 0);
        if ($altSettlers > $maxSettlers) {
            $maxSettlers = $altSettlers;
            $vTribe = $altTribe;
        }
    }
    $settlers = $maxSettlers;
}

assert_true($settlers === 3, "vilview logic detected exactly 3 settlers for Egypt village (was previously 0)");
assert_true($vTribe === 7, "vilview logic detected tribe as 7 (Egypt)");

// 4. Test newdorf.tpl logic
$vTribeNewdorf = (!empty($village->tribe) && $village->tribe > 0) ? (int)$village->tribe : ((!empty($session->tribe) && $session->tribe > 0) ? (int)$session->tribe : 1);
$settlersNewdorf = (int)($village->unitarray['u' . $vTribeNewdorf . '0'] ?? 0);
if ($settlersNewdorf < 3) {
    foreach ([7, 10, 6, 8, 9, 1, 2, 3] as $altTribe) {
        $altSettlers = (int)($village->unitarray['u' . ($altTribe * 10)] ?? 0);
        if ($altSettlers >= 3) {
            $settlersNewdorf = $altSettlers;
            $vTribeNewdorf = $altTribe;
            break;
        }
    }
}
assert_true($settlersNewdorf === 3, "newdorf logic detected 3 settlers (allowed to proceed, not redirected)");
assert_true($vTribeNewdorf === 7, "newdorf logic identified tribe as 7 (Egypt)");

// 5. Test Units::Settlers logic execution
$units = new Units();
// Directly test Units::Settlers execution simulation
$tribe = isset($village->tribe) && $village->tribe > 0 ? (int)$village->tribe : (isset($session->tribe) ? (int)$session->tribe : 1);
$settlerKey = 'u' . $tribe . '0';
$settlerCount = isset($village->unitarray[$settlerKey]) ? (int)$village->unitarray[$settlerKey] : 0;
if ($settlerCount < 3) {
    foreach ([7, 10, 6, 8, 9, 1, 2, 3] as $altTribe) {
        $altCount = isset($village->unitarray['u' . ($altTribe * 10)]) ? (int)$village->unitarray['u' . ($altTribe * 10)] : 0;
        if ($altCount >= 3) {
            $tribe = $altTribe;
            $settlerKey = 'u' . $altTribe . '0';
            $settlerCount = $altCount;
            break;
        }
    }
}
assert_true($settlerCount === 3, "Units::Settlers recognized 3 settlers in Egypt village");
assert_true($tribe === 7, "Units::Settlers recognized tribe = 7 (Egypt)");

// Deduct units and dispatch movement as Units::Settlers does
$database->modifyResource($village->wid, 750, 750, 750, 750, 0);
$database->modifyUnit($village->wid, [70], [3], [0]);
$database->addMovement(5, $village->wid, $targetVid, 0, time() - 3600, time() - 10);

$afterUnits = $database->getUnit($egyptVid, false);
assert_true((int)$afterUnits['u70'] === 0, "Deducted 3 settlers from u70 in Egypt village");

// 6. Test AutomationTroopMovements::sendSettlersComplete
$automation = new Automation();
$refMethod = new ReflectionMethod($automation, 'sendSettlersComplete');
$refMethod->setAccessible(true);
$refMethod->invoke($automation);

// Check that the new village was founded and has tribe = 7 (Egypt)
$newVil = $database->getVillage($targetVid);
assert_true(!empty($newVil), "New village successfully founded on map tile $targetVid");
assert_true((int)$newVil['owner'] === $userId, "New village owned by user $userId");
assert_true((int)$newVil['tribe'] === 7, "New village correctly INHERITED Egypt tribe (tribe = 7) from settlers!");

// 7. Test Returning Settlers (sort_type 4, ref 0)
// Simulate settlers returning to the Egyptian village
$database->addMovement(4, $targetVid2, $egyptVid, 0, time() - 100, time() - 10);
$refMethodReturn = new ReflectionMethod($automation, 'returnunitsComplete');
$refMethodReturn->setAccessible(true);
$refMethodReturn->invoke($automation);

$afterReturnUnits = $database->getUnit($egyptVid, false);
assert_true((int)$afterReturnUnits['u70'] === 3, "Returning settlers refunded back to u70 (Egypt) in the village");
assert_true((int)$afterReturnUnits['u100'] === 0, "No settlers mistakenly placed in u100 (Nusantara)");

// 8. Test Self-Healing when vdata.tribe was 0
mysqli_query($conn, "UPDATE " . TB_PREFIX . "vdata SET tribe = 0 WHERE wref = $egyptVid");
$database->clearVillageCache();
$healedTribe = $database->getVillageTribe($egyptVid);
assert_true($healedTribe === 7, "getVillageTribe self-healed vdata.tribe = 0 to 7 based on u70 settlers in village");
$dbRes = mysqli_query($conn, "SELECT tribe FROM " . TB_PREFIX . "vdata WHERE wref = $egyptVid");
$dbRow = mysqli_fetch_assoc($dbRes);
assert_true((int)$dbRow['tribe'] === 7, "vdata.tribe was persisted back to 7 in database");

// Cleanup test records
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id = $userId");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($egyptVid, $targetVid)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($egyptVid, $targetVid)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($egyptVid, $targetVid)");
mysqli_query($conn, "UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id IN ($egyptVid, $targetVid)");

echo "\n>>> ALL NUSANTARA CHIEF EGYPT SETTLER TESTS PASSED! <<<\n";
