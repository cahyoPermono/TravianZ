<?php
/**
 * Test Suite: Daily Quests Build Progress Integration
 * Tests that both queuing buildings (addBuilding) and completing buildings (AutomationBuildQueue)
 * properly increment and complete DailyQuests 'build' milestone.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/DailyQuests.php';
require_once __DIR__ . '/../GameEngine/Automation.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running DailyQuestsBuildIntegrationTest ===\n";

$dblink = $database->dblink;
$testUid = 99962;
$testWid = 99962;

// Cleanup any old test fixtures
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "bdata` WHERE wid = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "daily_quests` WHERE uid = $testUid");

// Setup user, village, and fields
mysqli_query($dblink, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($testUid, 'BuilderTestUser', 'p', 'builder@test.com', 1, 2)");
mysqli_query($dblink, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp) VALUES ($testWid, $testUid, 'BuilderVillage', 1, 100, 100)");
mysqli_query($dblink, "INSERT INTO `" . TB_PREFIX . "fdata` (vref, f1, f1t, f2, f2t) VALUES ($testWid, 1, 1, 1, 2)");

// 1. Initial State Check
$prog0 = DailyQuests::getDailyProgress($testUid);
assertTest($prog0['count_build'] === 0, "Initial build count is 0");
assertTest($prog0['p_build'] === 0, "Initial build milestone is 0");

// 2. Test queuing a normal building upgrade (addBuilding)
$res1 = $database->addBuilding($testWid, 1, 1, 0, time() + 600, 0, 2);
assertTest($res1 !== false, "Building 1 added to bdata queue successfully");

$prog1 = DailyQuests::getDailyProgress($testUid);
assertTest($prog1['count_build'] === 1, "Build count incremented to 1 after addBuilding");
assertTest($prog1['p_build'] === 0, "Build milestone not yet complete (1/2)");

// 3. Queue a second building upgrade
$res2 = $database->addBuilding($testWid, 2, 2, 0, time() + 600, 0, 2);
assertTest($res2 !== false, "Building 2 added to bdata queue successfully");

$prog2 = DailyQuests::getDailyProgress($testUid);
assertTest($prog2['count_build'] === 2, "Build count incremented to 2 after 2nd addBuilding");
assertTest($prog2['p_build'] === 1, "Build milestone completed (2/2)!");

// 4. Test Master Builder does NOT increment upon queueing (master = 1)
$resMaster = $database->addBuilding($testWid, 3, 3, 0, time() + 600, 1, 2);
assertTest($resMaster !== false, "Master build added to bdata queue successfully");
$progMaster = DailyQuests::getDailyProgress($testUid);
assertTest($progMaster['count_build'] === 2, "Master builder queue did NOT increment count (remains 2)");

// Clean up bdata for completion test
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "bdata` WHERE wid = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "daily_quests` WHERE uid = $testUid");

// 5. Test building completion in AutomationBuildQueue
// Insert a building that is ready to complete (timestamp in the past)
$readyTime = time() - 30;
mysqli_query($dblink, "INSERT INTO `" . TB_PREFIX . "bdata` (wid, field, type, loopcon, timestamp, master, level) 
                   VALUES ($testWid, 1, 1, 0, $readyTime, 0, 3)");

// Run automation build complete
$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$buildCompleteMethod = $reflection->getMethod('buildComplete');
$buildCompleteMethod->setAccessible(true);
$buildCompleteMethod->invoke($automation);

// Verify building finished and DailyQuests progress was recorded
$fRes = mysqli_query($dblink, "SELECT f1 FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid");
$fRow = mysqli_fetch_assoc($fRes);
assertTest((int)$fRow['f1'] === 3, "Building level in fdata updated to 3 by buildComplete");

$bCount = mysqli_query($dblink, "SELECT COUNT(*) as cnt FROM `" . TB_PREFIX . "bdata` WHERE wid = $testWid");
$bRow = mysqli_fetch_assoc($bCount);
assertTest((int)$bRow['cnt'] === 0, "Completed building removed from bdata");

$progCompl = DailyQuests::getDailyProgress($testUid);
assertTest($progCompl['count_build'] === 1, "Build count incremented to 1 by buildComplete");

// Cleanup test fixtures
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "bdata` WHERE wid = $testWid");
mysqli_query($dblink, "DELETE FROM `" . TB_PREFIX . "daily_quests` WHERE uid = $testUid");

echo "=== All DailyQuestsBuildIntegrationTest tests passed! ===\n";
