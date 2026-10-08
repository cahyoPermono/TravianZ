<?php

#################################################################################
##  Unit Tests for Beginner Village Relocation System                          ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/VillageRelocate.php';

global $database;
$link = $database->return_link();

function assertTest($condition, $message) {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

echo "=== Running Beginner Village Relocation Tests ===\n\n";

// 1. Schema verification
echo "1. Schema verification:\n";
VillageRelocate::ensureSchema();

$checkCol = mysqli_query($link, "SHOW COLUMNS FROM " . TB_PREFIX . "users LIKE 'village_relocated'");
assertTest($checkCol && mysqli_num_rows($checkCol) > 0, "Column users.village_relocated exists");

$checkLog = mysqli_query($link, "SHOW TABLES LIKE '" . TB_PREFIX . "village_relocation_log'");
assertTest($checkLog && mysqli_num_rows($checkLog) > 0, "Table village_relocation_log exists");

// Setup a test user and village
echo "\n2. Test Environment Setup:\n";
$testUsername = 'test_reloc_user_' . time();
$pass = md5('testpass');
$email = 'test_reloc@example.com';
$now = time();
$protectTime = $now + 7200; // 2 hours of protection

// Create user
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime, protect, village_relocated) 
                     VALUES ('$testUsername', '$pass', '$email', 1, 2, $now, $protectTime, 0)");
$testUid = mysqli_insert_id($link);
assertTest($testUid > 0, "Created test user (UID: $testUid)");

// Find two adjacent empty valleys
$qTiles = "SELECT id, x, y, fieldtype FROM " . TB_PREFIX . "wdata WHERE occupied = 0 AND oasistype = 0 AND fieldtype > 0 LIMIT 2";
$resTiles = mysqli_query($link, $qTiles);
$tileA = mysqli_fetch_assoc($resTiles);
$tileB = mysqli_fetch_assoc($resTiles);
assertTest(!empty($tileA) && !empty($tileB), "Found two empty valleys (A: {$tileA['id']}, B: {$tileB['id']})");

$oldWid = (int)$tileA['id'];
$oldX = (int)$tileA['x'];
$oldY = (int)$tileA['y'];
$targetWid = (int)$tileB['id'];
$targetX = (int)$tileB['x'];
$targetY = (int)$tileB['y'];

// Occupy tile A as test village
mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $oldWid");
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) 
                     VALUES ($oldWid, $testUid, 'Desa Relokasi', 1, 10, 100, 750, 750, 750, 750, 800, 800, $now, $now)");
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "fdata (vref, f1t, f2t, f3t, f4t, f5t, f6t, f7t, f8t, f9t, f10t, f11t, f12t, f13t, f14t, f15t, f16t, f17t, f18t) 
                     VALUES ($oldWid, 1, 4, 1, 3, 2, 2, 3, 4, 4, 3, 3, 4, 4, 1, 4, 2, 1, 2)");
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "units (vref, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) 
                     VALUES ($oldWid, 15, 0, 0, 0, 0, 0, 0, 0, 0, 0)");

echo "  Setup test village on tile $oldWid at ($oldX|$oldY)\n";

// 3. Eligibility Checks
echo "\n3. Eligibility Checks:\n";

// Case 3a: Valid eligibility
$check1 = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($check1['can'] === true, "User is eligible to relocate (under protection, not used, no movements)");
assertTest($check1['timeLeft'] > 0, "Time left is positive: {$check1['timeLeft']} seconds");

// Case 3b: Protection expired
mysqli_query($link, "UPDATE " . TB_PREFIX . "users SET protect = " . ($now - 100) . " WHERE id = $testUid");
$checkExp = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($checkExp['can'] === false, "Relocation blocked when protection expired");
assertTest(strpos($checkExp['reason'], 'Perlindungan') !== false, "Error message explains protection expired");

// Restore protection
mysqli_query($link, "UPDATE " . TB_PREFIX . "users SET protect = $protectTime WHERE id = $testUid");

// Case 3c: Already relocated
mysqli_query($link, "UPDATE " . TB_PREFIX . "users SET village_relocated = 1 WHERE id = $testUid");
$checkUsed = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($checkUsed['can'] === false, "Relocation blocked when already used");
assertTest(strpos($checkUsed['reason'], 'sudah pernah digunakan') !== false, "Error message explains already used");

// Reset village_relocated flag
mysqli_query($link, "UPDATE " . TB_PREFIX . "users SET village_relocated = 0 WHERE id = $testUid");

// Case 3d: Ongoing troop movement
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "movement (`from`, `to`, `endtime`, `sort_type`, `proc`) 
                     VALUES ($oldWid, 1, " . ($now + 300) . ", 1, 0)");
$movId = mysqli_insert_id($link);
$checkMov = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($checkMov['can'] === false, "Relocation blocked with ongoing troop movements");
assertTest(strpos($checkMov['reason'], 'pergerakan pasukan') !== false, "Error message explains ongoing movement");

// Clean up movement
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "movement WHERE moveid = $movId");

// Case 3e: Stationed reinforcement
mysqli_query($link, "INSERT INTO " . TB_PREFIX . "enforcement (`vref`, `from`, `u1`) VALUES ($oldWid, 1, 10)");
$enfId = mysqli_insert_id($link);
$checkEnf = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($checkEnf['can'] === false, "Relocation blocked with foreign reinforcements stationed");

// Clean up enforcement
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "enforcement WHERE id = $enfId");

$checkRestored = VillageRelocate::canRelocate($testUid, $oldWid);
assertTest($checkRestored['can'] === true, "Eligibility restored after movements and reinforcements cleared");

// 4. Target Validation Checks
echo "\n4. Target Validation Checks:\n";

// Target is same village
$targetSame = VillageRelocate::checkTargetCoordinates($oldX, $oldY, $oldWid);
assertTest($targetSame['valid'] === false, "Cannot target current village location");

// Target out of map
$targetOOB = VillageRelocate::checkTargetCoordinates(9999, 9999, $oldWid);
assertTest($targetOOB['valid'] === false, "Cannot target out-of-bounds coordinates");

// Target occupied
$targetOccupied = VillageRelocate::checkTargetCoordinates($oldX, $oldY, $targetWid);
assertTest($targetOccupied['valid'] === false, "Cannot target an occupied tile");

// Target valid empty valley
$targetValid = VillageRelocate::checkTargetCoordinates($targetX, $targetY, $oldWid);
assertTest($targetValid['valid'] === true, "Target empty valley at ($targetX|$targetY) is valid");
assertTest(!empty($targetValid['tile']['fieldtypeName']), "Target tile field type is identified: {$targetValid['tile']['fieldtypeName']}");

// 5. Relocation Execution
echo "\n5. Execution of Village Relocation:\n";

$result = VillageRelocate::relocateVillage($testUid, $oldWid, $targetX, $targetY);
assertTest($result['success'] === true, "Relocation succeeded: {$result['message']}");
assertTest($result['newWid'] === $targetWid, "Returned new WID matches target tile ID ($targetWid)");

// Verify Database State
$oldTileCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT occupied FROM " . TB_PREFIX . "wdata WHERE id = $oldWid"));
assertTest((int)$oldTileCheck['occupied'] === 0, "Old tile is now unoccupied (occupied = 0)");

$newTileCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT occupied FROM " . TB_PREFIX . "wdata WHERE id = $targetWid"));
assertTest((int)$newTileCheck['occupied'] === 1, "New tile is now occupied (occupied = 1)");

$vdataCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT wref, name FROM " . TB_PREFIX . "vdata WHERE owner = $testUid"));
assertTest((int)$vdataCheck['wref'] === $targetWid, "vdata.wref moved to target tile ($targetWid)");

$fdataCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT vref FROM " . TB_PREFIX . "fdata WHERE vref = $targetWid"));
assertTest(!empty($fdataCheck), "fdata.vref successfully updated to new tile");

$unitsCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT vref, u1 FROM " . TB_PREFIX . "units WHERE vref = $targetWid"));
assertTest((int)$unitsCheck['u1'] === 15, "Troops safely relocated to new tile (u1 = 15)");

$userCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT village_relocated FROM " . TB_PREFIX . "users WHERE id = $testUid"));
assertTest((int)$userCheck['village_relocated'] === 1, "users.village_relocated is set to 1");

$logCheck = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM " . TB_PREFIX . "village_relocation_log WHERE uid = $testUid ORDER BY id DESC LIMIT 1"));
assertTest(!empty($logCheck), "Relocation log recorded");
assertTest((int)$logCheck['old_wref'] === $oldWid && (int)$logCheck['new_wref'] === $targetWid, "Relocation log has correct old and new WIDs");
assertTest((int)$logCheck['old_x'] === $oldX && (int)$logCheck['new_x'] === $targetX, "Relocation log has correct coordinates");

// 6. Block Second Relocation Attempt
echo "\n6. Enforce One-Time Limit:\n";

// Find another empty tile
$qTileC = "SELECT id, x, y FROM " . TB_PREFIX . "wdata WHERE occupied = 0 AND oasistype = 0 AND fieldtype > 0 LIMIT 1";
$tileC = mysqli_fetch_assoc(mysqli_query($link, $qTileC));

$attempt2 = VillageRelocate::relocateVillage($testUid, $targetWid, (int)$tileC['x'], (int)$tileC['y']);
assertTest($attempt2['success'] === false, "Second relocation attempt is rejected");
assertTest(strpos($attempt2['message'], 'sudah pernah digunakan') !== false, "Error states one-time limit reached");

// 7. Cleanup Test Data
echo "\n7. Cleanup Test Data:\n";
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($oldWid, $targetWid)");
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($oldWid, $targetWid)");
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($oldWid, $targetWid)");
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($oldWid, $targetWid)"); // Restore occupied status
mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id IN ($oldWid, $targetWid)");
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "village_relocation_log WHERE uid = $testUid");
mysqli_query($link, "DELETE FROM " . TB_PREFIX . "users WHERE id = $testUid");
echo "  [PASS] Test data cleaned up cleanly\n";

echo "\n🎉 ALL VILLAGE RELOCATION TESTS PASSED! 🎉\n";
