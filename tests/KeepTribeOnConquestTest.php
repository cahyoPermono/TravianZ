<?php
/**
 * Test Suite: Keep Tribe on Conquest (Special Server Mechanics)
 * 
 * Verifies:
 * 1. vdata.tribe column persistence and auto-migration.
 * 2. Player-vs-player conquest preserves the conquered village's original tribe.
 * 3. Tribe-specific buildings (Brewery 35, Trapper 36, Horse Drinking Trough 41, Command Center 44) are preserved (level - 1) upon conquest.
 * 4. Wall type in fdata (f40t) is set according to the conquered village tribe.
 * 5. Natar conquest adopts the tribe of the chief's origin village.
 * 6. Founding new village by settlers inherits the settler origin village tribe.
 * 7. Avatar tribe and Hero tribe remain unaffected on conquest.
 * 8. Village class and Building requirements use village tribe (effective tribe).
 */

$_SERVER['REQUEST_URI'] = '/login.php';
$_SERVER['SCRIPT_NAME'] = 'login.php';
$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['HTTP_HOST'] = 'localhost';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Automation.php';
require_once __DIR__ . '/../GameEngine/Village.php';
require_once __DIR__ . '/../GameEngine/Building.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

function helperInsertFData($conn, $vid, $custom = []) {
    $fields = ['vref' => $vid];
    for ($i = 1; $i <= 40; $i++) {
        $fields['f' . $i] = 0;
        $fields['f' . $i . 't'] = 0;
    }
    $fields['f99'] = 0;
    $fields['f99t'] = 0;
    foreach ($custom as $k => $v) {
        $fields[$k] = $v;
    }
    $cols = implode(',', array_keys($fields));
    $vals = implode(',', array_values($fields));
    $q = "REPLACE INTO " . TB_PREFIX . "fdata ($cols) VALUES ($vals)";
    $res = mysqli_query($conn, $q);
    if (!$res) {
        echo "Error helperInsertFData: " . mysqli_error($conn) . "\n";
    }
}

function helperInsertVData($conn, $vid, $owner, $tribe, $name, $capital = 0, $pop = 100, $natar = 0) {
    $now = time();
    mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "vdata 
        (wref, owner, tribe, name, capital, pop, natar, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, loyalty, exp1, exp2, exp3) 
        VALUES 
        ($vid, $owner, $tribe, '$name', $capital, $pop, $natar, 500, 500, 500, 500, 800, 800, $now, 100, 0, 0, 0)");
}

echo "=== Running Keep Tribe on Conquest Unit Tests ===\n\n";

$conn = $database->dblink;
$GLOBALS['db'] = $database;

// 1. Verify ensureVillageTribeColumn ensures tribe column exists in vdata
$database->ensureVillageTribeColumn();
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM " . TB_PREFIX . "vdata LIKE 'tribe'");
assert_true(mysqli_num_rows($colRes) > 0, "vdata table contains 'tribe' column");

// Setup Users:
// User A: Roman (tribe = 1, cp = 999999)
// User B: Teuton (tribe = 2, cp = 999999)
// User C: Gaul (tribe = 3, cp = 999999)
$uidA = 999991;
$uidB = 999992;
$uidC = 999993;

mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($uidA, $uidB, $uidC)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access, cp) VALUES ($uidA, 'PlayerRoman', 1, 2, 999999)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access, cp) VALUES ($uidB, 'PlayerTeuton', 2, 2, 999999)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access, cp) VALUES ($uidC, 'PlayerGaul', 3, 2, 999999)");

// Setup Villages:
// Vil A1: Roman origin village (tribe = 1, owner = uidA, capital = 1, pop = 200, Palace lvl 10)
// Vil B1: Teuton village with Brewery (gid 35 lvl 5), Main Building lvl 10, Earth Wall lvl 10 (f40t = 32, f40 = 10, pop = 150)
// Vil B2: Teuton village 2 (so defender has > 1 village)
// Vil Natar: Natar village (owner = 3, tribe = 5)
// Vil Natar2: Natar dummy village so Natar has > 1 village
$vidA1 = 999994;
$vidB1 = 999995;
$vidB2 = 999999;
$vidNatar = 999996;
$vidNatar2 = 999990;

mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2)");

mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidA1, 3, 0, 10, 10, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidB1, 3, 0, 11, 10, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidB2, 3, 0, 11, 11, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidNatar, 3, 0, 12, 10, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidNatar2, 3, 0, 12, 11, 1, 'f3')");

helperInsertVData($conn, $vidA1, $uidA, 1, 'Roma Capital', 1, 200);
helperInsertVData($conn, $vidB1, $uidB, 2, 'Teuton Village', 0, 150);
helperInsertVData($conn, $vidB2, $uidB, 2, 'Teuton Second', 0, 100);
helperInsertVData($conn, $vidNatar, 3, 5, 'Natar Outpost', 0, 100, 0);
helperInsertVData($conn, $vidNatar2, 3, 5, 'Natar Capital', 1, 200, 0);

// Setup fdata:
// Vil A1: Palace lvl 10 (f20 = 10, f20t = 26) so canconquer = 1
helperInsertFData($conn, $vidA1, ['f20' => 10, 'f20t' => 26, 'f40' => 0, 'f40t' => 31]);
// Vil B1: Main Building lvl 10 (f20 = 10, f20t = 15), Brewery lvl 5 (f21 = 5, f21t = 35), Earth Wall lvl 10 (f40 = 10, f40t = 32)
helperInsertFData($conn, $vidB1, ['f20' => 10, 'f20t' => 15, 'f21' => 5, 'f21t' => 35, 'f40' => 10, 'f40t' => 32]);
helperInsertFData($conn, $vidB2, ['f20' => 5, 'f20t' => 15]);
// Vil Natar: Main building lvl 1, wall lvl 5 (f40 = 5, f40t = 31)
helperInsertFData($conn, $vidNatar, ['f20' => 1, 'f20t' => 15, 'f40' => 5, 'f40t' => 31]);
helperInsertFData($conn, $vidNatar2, ['f20' => 10, 'f20t' => 26]);

MYSQLi_DB::clearVillageCache();

// 2. Test PvP Conquest (Roman player chiefing Teuton village)
$resClass = new ReflectionClass('Automation');
$conqMethod = $resClass->getMethod('handleConquest');
$conqMethod->setAccessible(true);
$auto = $resClass->newInstanceWithoutConstructor();

$attackData = ['from' => $vidA1, 'to' => $vidB1, 't9' => 1];
$type = 3;
$dead9 = 0;
$traped9 = 0;
$targettribe = 2;
$fromData = ['wref' => $vidA1, 'owner' => $uidA, 'celebration' => 0, 'type' => 0];
$toData = ['owner' => $uidB, 'capital' => 0, 'name' => 'Teuton Village', 'celebration' => 0, 'type' => 0];
$toFData = ['loyalty' => 10]; // Low loyalty, 1 chief will drop it <= 0!
$owntribe = 1;
$varray = [$vidB1, $vidB2]; // Defender has 2 villages
$varray1 = [$vidA1];
$battlepart = ['moralBonus' => 1];

$conqRes = $conqMethod->invokeArgs($auto, [
    $attackData, $type, $dead9, $traped9, $targettribe,
    $fromData, $toData, $toFData, $owntribe,
    $varray, $varray1, $battlepart, 0, 0, 9
]);

assert_true(!empty($conqRes['chiefing_village']) && (int)$conqRes['chiefing_village'] === 1, "Conquest executed successfully");

// Check Conquered Village B1 in vdata
$vRes = mysqli_query($conn, "SELECT owner, tribe FROM " . TB_PREFIX . "vdata WHERE wref = $vidB1");
$vRow = mysqli_fetch_assoc($vRes);
assert_true((int)$vRow['owner'] === $uidA, "Village ownership transferred to Roman attacker");
assert_true((int)$vRow['tribe'] === 2, "Conquered village KEPT its Teuton tribe (tribe = 2)");

// Check User A avatar tribe
$uRes = mysqli_query($conn, "SELECT tribe FROM " . TB_PREFIX . "users WHERE id = $uidA");
$uRow = mysqli_fetch_assoc($uRes);
assert_true((int)$uRow['tribe'] === 1, "Conqueror avatar tribe remains Roman (tribe = 1)");

// Check Brewery and Wall in fdata
$fRes = mysqli_query($conn, "SELECT f20, f20t, f21, f21t, f40, f40t FROM " . TB_PREFIX . "fdata WHERE vref = $vidB1");
$fRow = mysqli_fetch_assoc($fRes);
assert_true((int)$fRow['f20'] === 9, "Main building leveled down by 1 (10 -> 9)");
assert_true((int)$fRow['f21t'] === 35, "Tribe-exclusive Brewery (gid 35) PRESERVED");
assert_true((int)$fRow['f21'] === 4, "Brewery leveled down by 1 (5 -> 4), not demolished");
assert_true((int)$fRow['f40'] === 0, "Wall level reset to 0 upon conquest");
assert_true((int)$fRow['f40t'] === 32, "Wall type set to Teuton Earth Wall (32)");

// 3. Test Natar Conquest (Gaul chief conquers Natar village)
// Create Gaul origin village
$vidC1 = 999997;
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $vidC1");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $vidC1");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id = $vidC1");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidC1, 3, 0, 15, 15, 1, 'f3')");
helperInsertVData($conn, $vidC1, $uidC, 3, 'Gaul Capital', 1, 300);
helperInsertFData($conn, $vidC1, ['f20' => 10, 'f20t' => 26]);
MYSQLi_DB::clearVillageCache();

$natarAttackData = ['from' => $vidC1, 'to' => $vidNatar, 't9' => 1];
$natarFromData = ['wref' => $vidC1, 'owner' => $uidC, 'celebration' => 0, 'type' => 0];
$natarToData = ['owner' => 3, 'capital' => 0, 'name' => 'Natar Outpost', 'celebration' => 0, 'type' => 0];
$natarToFData = ['loyalty' => 10];
$natarVArray = [$vidNatar, $vidNatar2];
$natarVArray1 = [$vidC1];

$natarConqRes = $conqMethod->invokeArgs($auto, [
    $natarAttackData, 3, 0, 0, 5,
    $natarFromData, $natarToData, $natarToFData, 3,
    $natarVArray, $natarVArray1, $battlepart, 0, 0, 9
]);

assert_true(!empty($natarConqRes['chiefing_village']) && (int)$natarConqRes['chiefing_village'] === 1, "Natar conquest executed successfully");

$nRes = mysqli_query($conn, "SELECT owner, tribe FROM " . TB_PREFIX . "vdata WHERE wref = $vidNatar");
$nRow = mysqli_fetch_assoc($nRes);
assert_true((int)$nRow['owner'] === $uidC, "Natar village ownership transferred to Gaul attacker");
assert_true((int)$nRow['tribe'] === 3, "Natar conquered village ADOPTED chief origin village tribe (Gaul = 3)");

// Check wall in Natar village was updated to Gaul Palisade (33)
$nFRes = mysqli_query($conn, "SELECT f40, f40t FROM " . TB_PREFIX . "fdata WHERE vref = $vidNatar");
$nFRow = mysqli_fetch_assoc($nFRes);
assert_true((int)$nFRow['f40'] === 0, "Wall level in conquered Natar village reset to 0");
assert_true((int)$nFRow['f40t'] === 33, "Wall type in conquered Natar village changed to Gaul Palisade (33)");

// 4. Test Settler founding new village
$vidNew = 999998;
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $vidNew");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $vidNew");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id = $vidNew");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidNew, 3, 0, 20, 20, 0, 'f3')");

// Settlers trained from Teuton conquered village (vidB1, tribe = 2)
$settlerOriginTribe = (int)$database->getVillageField($vidB1, 'tribe');
$database->addVillage($vidNew, $uidA, 'PlayerRoman', 0, 2, 'Teuton Colony', 0, $settlerOriginTribe);

$newRes = mysqli_query($conn, "SELECT owner, tribe FROM " . TB_PREFIX . "vdata WHERE wref = $vidNew");
$newRow = mysqli_fetch_assoc($newRes);
assert_true((int)$newRow['owner'] === $uidA, "New settlement belongs to Roman player");
assert_true((int)$newRow['tribe'] === 2, "New settlement founded by Teuton settlers INHERITED Teuton tribe (2)");

// 5. Test Village Object and Building requirements logic
$vRefClass = new ReflectionClass('Village');
$vilObj = $vRefClass->newInstanceWithoutConstructor();
$wProp = $vRefClass->getProperty('wid');
$wProp->setAccessible(true);
$wProp->setValue($vilObj, $vidB1);

$dProp = $vRefClass->getProperty('db');
$dProp->setAccessible(true);
$dProp->setValue($vilObj, $database);

$sProp = $vRefClass->getProperty('sess');
$sProp->setAccessible(true);
$mockSession = (object)['uid' => $uidA, 'isAdmin' => false, 'tribe' => 1];
$sProp->setValue($vilObj, $mockSession);

$tProp = $vRefClass->getProperty('tech');
$tProp->setAccessible(true);
global $technology;
if (!isset($technology) || !is_object($technology)) {
    require_once __DIR__ . '/../GameEngine/Technology.php';
    $technology = new Technology();
}
$tProp->setValue($vilObj, $technology);

$loadTownMethod = $vRefClass->getMethod('LoadTown');
$loadTownMethod->setAccessible(true);
$loadTownMethod->invoke($vilObj);

assert_true((int)$vilObj->tribe === 2, "Village class correctly loaded village tribe (tribe = 2)");

// Also verify Building evaluation for Teuton-exclusive Brewery (gid 35) on Roman player with Teuton village
$bRefClass = new ReflectionClass('Building');
$bldg = $bRefClass->newInstanceWithoutConstructor();
$bVilProp = $bRefClass->getProperty('vil');
$bVilProp->setAccessible(true);
$bVilProp->setValue($bldg, $vilObj);
$bSessProp = $bRefClass->getProperty('sess');
$bSessProp->setAccessible(true);
$bSessProp->setValue($bldg, $mockSession);

$evalMethod = $bRefClass->getMethod('evaluateRequirements');
$evalMethod->setAccessible(true);
$breweryAllowedInTeutonVillage = $evalMethod->invokeArgs($bldg, [35, ['tribe' => [2], 'unique' => false]]);
assert_true($breweryAllowedInTeutonVillage === true, "Roman account can build Brewery in conquered Teuton village because effective tribe is 2");

$romanBldgBlockedInTeutonVillage = $evalMethod->invokeArgs($bldg, [41, ['tribe' => [1], 'unique' => false]]);
assert_true($romanBldgBlockedInTeutonVillage === false, "Roman-exclusive Horse Drinking Trough (41) blocked in Teuton village");

// Cleanup
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($uidA, $uidB, $uidC)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2, $vidC1, $vidNew)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2, $vidC1, $vidNew)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($vidA1, $vidB1, $vidB2, $vidNatar, $vidNatar2, $vidC1, $vidNew)");

echo "\n>>> ALL KEEP TRIBE ON CONQUEST TESTS PASSED SUCCESSFULLY! <<<\n";
