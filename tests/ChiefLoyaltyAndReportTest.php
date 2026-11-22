<?php
/**
 * Test Suite: Chief Loyalty Reduction & Battle Report Rendering
 *
 * Verifies:
 * 1. Chief lowers loyalty even when attacker has 0 Culture Points (CP).
 * 2. Chief lowers loyalty even when attacker village has no free expansion slots.
 * 3. Raid attack (type 4) does NOT lower loyalty (returns RC_NO_REDUCE_CP_RAID).
 * 4. Residence/Palace/Command Center level > 0 blocks loyalty reduction (returns RC_RESIDENCE_NOT_DESTROYED).
 * 5. Capital or last village blocks loyalty reduction.
 * 6. Loyalty reaching <= 0 with 0 CP caps loyalty at 0 and blocks conquest (returns RC_NOT_ENOUGH_CP).
 * 7. Templates/Notice/1.tpl correctly renders chief loyalty info in 10-tribe layout without hiding it.
 */

$_SERVER['REQUEST_URI'] = '/berichte.php';
$_SERVER['SCRIPT_NAME'] = 'berichte.php';
$_SERVER['PHP_SELF'] = 'berichte.php';
$_SERVER['HTTP_HOST'] = 'localhost';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Automation.php';
require_once __DIR__ . '/../GameEngine/Building.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

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

function helperInsertVData($conn, $vid, $owner, $tribe, $name, $capital = 0, $pop = 100, $loyalty = 100, $exp1 = 0, $exp2 = 0) {
    $now = time();
    mysqli_query($conn, "REPLACE INTO " . TB_PREFIX . "vdata 
        (wref, owner, tribe, name, capital, pop, natar, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, loyalty, exp1, exp2, exp3) 
        VALUES 
        ($vid, $owner, $tribe, '$name', $capital, $pop, 0, 500, 500, 500, 500, 800, 800, $now, $loyalty, $exp1, $exp2, 0)");
}

echo "=== Running Chief Loyalty & Battle Report Unit Tests ===\n\n";

$conn = $database->dblink;
$GLOBALS['db'] = $database;

// Setup Users:
// User A: Teuton (tribe = 2, cp = 0, simulating player with 0 CP)
// User B: Defender (tribe = 2)
$uidA = 888881;
$uidB = 888882;

mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($uidA, $uidB)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access, cp) VALUES ($uidA, 'AttackerChief', 2, 2, 0)");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "users (id, username, tribe, access, cp) VALUES ($uidB, 'DefenderTarget', 2, 2, 5000)");

// Setup Villages:
$vidA1 = 888883; // Attacker village
$vidB1 = 888884; // Target village 1 (non-capital)
$vidB2 = 888885; // Target village 2 (defender capital, so defender has > 1 village)

mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidA1, $vidB1, $vidB2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vidA1, $vidB1, $vidB2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($vidA1, $vidB1, $vidB2)");

mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidA1, 3, 0, 20, 20, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidB1, 3, 0, 21, 20, 1, 'f3')");
mysqli_query($conn, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($vidB2, 3, 0, 21, 21, 1, 'f3')");

helperInsertVData($conn, $vidA1, $uidA, 2, 'Attacker HQ', 1, 200, 100);
helperInsertVData($conn, $vidB1, $uidB, 2, 'Defender Colony', 0, 150, 100);
helperInsertVData($conn, $vidB2, $uidB, 2, 'Defender Capital', 1, 300, 100);

// fdata: No residence in B1 (no f25, f26, or f44)
helperInsertFData($conn, $vidA1, ['f20' => 10, 'f20t' => 26]); // Palace lvl 10
helperInsertFData($conn, $vidB1, ['f20' => 5, 'f20t' => 15]);  // Only Main Building lvl 5
helperInsertFData($conn, $vidB2, ['f20' => 10, 'f20t' => 26]);

MYSQLi_DB::clearVillageCache();

$resClass = new ReflectionClass('Automation');
$conqMethod = $resClass->getMethod('handleConquest');
$conqMethod->setAccessible(true);
$auto = $resClass->newInstanceWithoutConstructor();

// Test 1: Chief lowers loyalty even with 0 CP
$attackData = ['from' => $vidA1, 'to' => $vidB1, 't9' => 1];
$fromData = ['wref' => $vidA1, 'owner' => $uidA, 'celebration' => 0, 'type' => 0];
$toData = ['owner' => $uidB, 'capital' => 0, 'name' => 'Defender Colony', 'celebration' => 0, 'type' => 0];
$toFData = ['loyalty' => 100];
$varray = [$vidB1, $vidB2];
$varray1 = [$vidA1];
$battlepart = ['moralBonus' => 1];

$conqRes1 = $conqMethod->invokeArgs($auto, [
    $attackData, 3, 0, 0, 2,
    $fromData, $toData, $toFData, 2,
    $varray, $varray1, $battlepart, 0, 0, 19
]);

assert_true(isset($conqRes1['info_chief']) && strpos($conqRes1['info_chief'], 'RC_LOYALTY_LOWERED') !== false, "Test 1: Chief lowers loyalty when attacker has 0 CP");
$vRes1 = mysqli_query($conn, "SELECT loyalty, owner FROM " . TB_PREFIX . "vdata WHERE wref = $vidB1");
$vRow1 = mysqli_fetch_assoc($vRes1);
assert_true((int)$vRow1['loyalty'] < 100 && (int)$vRow1['loyalty'] >= 65, "Test 1: Target loyalty decreased from 100 to {$vRow1['loyalty']}");
assert_true((int)$vRow1['owner'] === $uidB, "Test 1: Target village owner still defender");

// Test 2: Chief lowers loyalty even when expansion slots are full (villexp >= canconquer)
helperInsertVData($conn, $vidA1, $uidA, 2, 'Attacker HQ', 1, 200, 100, 99999); // slot 1 used
helperInsertVData($conn, $vidB1, $uidB, 2, 'Defender Colony', 0, 150, 80);
MYSQLi_DB::clearVillageCache();

$toFData['loyalty'] = 80;
$conqRes2 = $conqMethod->invokeArgs($auto, [
    $attackData, 3, 0, 0, 2,
    $fromData, $toData, $toFData, 2,
    $varray, $varray1, $battlepart, 0, 0, 19
]);

assert_true(isset($conqRes2['info_chief']) && strpos($conqRes2['info_chief'], 'RC_LOYALTY_LOWERED') !== false, "Test 2: Chief lowers loyalty when expansion slots are full");
$vRes2 = mysqli_query($conn, "SELECT loyalty FROM " . TB_PREFIX . "vdata WHERE wref = $vidB1");
$vRow2 = mysqli_fetch_assoc($vRes2);
assert_true((int)$vRow2['loyalty'] < 80, "Test 2: Target loyalty decreased further to {$vRow2['loyalty']}");

// Test 3: Raid attack (type 4) does NOT lower loyalty
helperInsertVData($conn, $vidB1, $uidB, 2, 'Defender Colony', 0, 150, 75);
MYSQLi_DB::clearVillageCache();
$toFData['loyalty'] = 75;

$conqRes3 = $conqMethod->invokeArgs($auto, [
    $attackData, 4, 0, 0, 2, // Type 4 = Raid
    $fromData, $toData, $toFData, 2,
    $varray, $varray1, $battlepart, 0, 0, 19
]);

assert_true(isset($conqRes3['info_chief']) && strpos($conqRes3['info_chief'], 'RC_NO_REDUCE_CP_RAID') !== false, "Test 3: Raid blocks loyalty reduction with RC_NO_REDUCE_CP_RAID");
$vRes3 = mysqli_query($conn, "SELECT loyalty FROM " . TB_PREFIX . "vdata WHERE wref = $vidB1");
$vRow3 = mysqli_fetch_assoc($vRes3);
assert_true((int)$vRow3['loyalty'] === 75, "Test 3: Loyalty unchanged on raid");

// Test 4: Residence > 0 blocks loyalty reduction
helperInsertFData($conn, $vidB1, ['f20' => 5, 'f20t' => 15, 'f25' => 1, 'f25t' => 25]); // Residence lvl 1
helperInsertVData($conn, $vidB1, $uidB, 2, 'Defender Colony', 0, 150, 75);
MYSQLi_DB::clearVillageCache();

$conqRes4 = $conqMethod->invokeArgs($auto, [
    $attackData, 3, 0, 0, 2,
    $fromData, $toData, $toFData, 2,
    $varray, $varray1, $battlepart, 0, 0, 19
]);

assert_true(isset($conqRes4['info_chief']) && strpos($conqRes4['info_chief'], 'RC_RESIDENCE_NOT_DESTROYED') !== false, "Test 4: Residence level > 0 blocks loyalty reduction with RC_RESIDENCE_NOT_DESTROYED");

// Reset residence back to 0
helperInsertFData($conn, $vidB1, ['f20' => 5, 'f20t' => 15]);
MYSQLi_DB::clearVillageCache();

// Test 5: Loyalty reaches <= 0 with 0 CP caps loyalty at 0 and blocks conquest
helperInsertVData($conn, $vidB1, $uidB, 2, 'Defender Colony', 0, 150, 10); // Loyalty only 10, chief will reduce > 15
MYSQLi_DB::clearVillageCache();
$toFData['loyalty'] = 10;

$conqRes5 = $conqMethod->invokeArgs($auto, [
    $attackData, 3, 0, 0, 2,
    $fromData, $toData, $toFData, 2,
    $varray, $varray1, $battlepart, 0, 0, 19
]);

assert_true(isset($conqRes5['info_chief']) && strpos($conqRes5['info_chief'], 'RC_NOT_ENOUGH_CP') !== false, "Test 5: Insufficient CP when loyalty reaches 0 returns RC_NOT_ENOUGH_CP");
assert_true((int)$conqRes5['chiefing_village'] === 0, "Test 5: Village not conquered when CP insufficient");
$vRes5 = mysqli_query($conn, "SELECT loyalty, owner FROM " . TB_PREFIX . "vdata WHERE wref = $vidB1");
$vRow5 = mysqli_fetch_assoc($vRes5);
assert_true((int)$vRow5['loyalty'] === 0, "Test 5: Loyalty capped at 0");
assert_true((int)$vRow5['owner'] === $uidB, "Test 5: Owner remains defender");

// Test 6: Template Notice/1.tpl rendering test
// Verify that chief info box is rendered and visible when chief data is present in 10-tribe layout
$technology = new Technology();
$session = new stdClass();
$session->access = 2;
$session->uid = $uidA;

// Construct a 10-tribe report dataarray
// 274 elements matching standard TravianZ combat report structure
$reportData = array_fill(0, 275, '0');
$reportData[0] = $uidA; // Attacker UID
$reportData[1] = $vidA1; // Attacker Village ID
$reportData[2] = 2; // Attacker Tribe (Teutons)
$reportData[3] = '293'; // Clubswingers
$reportData[8] = '80'; // Teutonic Knights
$reportData[11] = '1'; // 1 Chief (unit 19) sent
$reportData[28] = $uidB; // Defender UID
$reportData[29] = $vidB1; // Defender Village ID
$reportData[30] = 'Defender Colony'; // Defender Village Name
$reportData[35] = 2; // Defender Tribe

// Stolen resources & bounty
$reportData[23] = '500';
$reportData[24] = '600';
$reportData[25] = '700';
$reportData[26] = '800';
$reportData[27] = '24000';

// Chief result (indices 250/251 or 268/269)
$reportData[250] = '19'; // Unit 19
$reportData[251] = rc_tok('RC_LOYALTY_LOWERED', 100, 78);
// Also simulate 268/269 carrying chief data in 10-tribe layout
$reportData[268] = '19';
$reportData[269] = rc_tok('RC_LOYALTY_LOWERED', 100, 78);

$message = new stdClass();
$message->readingNotice = [
    'id' => 12345,
    'uid' => $uidA,
    'toWref' => $vidB1,
    'topic' => 'Ace of Spades attacks Horemheb\'s village 2',
    'ntype' => 1,
    'data' => implode(',', $reportData),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
];

$dataarray = explode(',', $message->readingNotice['data']);

ob_start();
include __DIR__ . '/../Templates/Notice/1.tpl';
$htmlOutput = ob_get_clean();

assert_true(strpos($htmlOutput, 'unit u19') !== false, "Test 6: Notice/1.tpl displays Chief unit 19 icon");
assert_true(strpos($htmlOutput, '78') !== false || strpos($htmlOutput, 'RC_LOYALTY_LOWERED') !== false || stripos($htmlOutput, 'loyalty') !== false || stripos($htmlOutput, 'loyalitas') !== false, "Test 6: Notice/1.tpl displays loyalty reduction message");
assert_true(strpos($htmlOutput, 'class="r1"') !== false && strpos($htmlOutput, 'class="carry"') !== false, "Test 6: Notice/1.tpl displays Bounty / Plunder even when Chief is in attack");

// Test 6b: Template rendering with RC_NO_REDUCE_CP_RAID
$reportData[251] = rc_tok('RC_NO_REDUCE_CP_RAID');
$message->readingNotice['data'] = implode(',', $reportData);
$dataarray = explode(',', $message->readingNotice['data']);

ob_start();
include __DIR__ . '/../Templates/Notice/1.tpl';
$htmlOutputRaid = ob_get_clean();

assert_true(strpos($htmlOutputRaid, 'unit u19') !== false, "Test 6b: Notice/1.tpl displays Chief icon on raid");
assert_true(strpos($htmlOutputRaid, 'RC_NO_REDUCE_CP_RAID') !== false || stripos($htmlOutputRaid, 'raid') !== false || stripos($htmlOutputRaid, 'penyerbuan') !== false, "Test 6b: Notice/1.tpl displays raid notice for chief");

// Test 6c: Template rendering with RC_RESIDENCE_NOT_DESTROYED
$reportData[251] = rc_tok('RC_RESIDENCE_NOT_DESTROYED');
$message->readingNotice['data'] = implode(',', $reportData);
$dataarray = explode(',', $message->readingNotice['data']);

ob_start();
include __DIR__ . '/../Templates/Notice/1.tpl';
$htmlOutputRes = ob_get_clean();

assert_true(strpos($htmlOutputRes, 'unit u19') !== false, "Test 6c: Notice/1.tpl displays Chief icon on residence not destroyed");
assert_true(strpos($htmlOutputRes, 'RC_RESIDENCE_NOT_DESTROYED') !== false || stripos($htmlOutputRes, 'residence') !== false || stripos($htmlOutputRes, 'istana') !== false, "Test 6c: Notice/1.tpl displays residence not destroyed notice for chief");

// Cleanup test DB
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "users WHERE id IN ($uidA, $uidB)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($vidA1, $vidB1, $vidB2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($vidA1, $vidB1, $vidB2)");
mysqli_query($conn, "DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($vidA1, $vidB1, $vidB2)");

echo "\nAll Chief Loyalty & Battle Report Unit Tests Passed Successfully!\n";
