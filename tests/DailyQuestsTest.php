<?php
/**
 * Test Suite: Daily Quests Milestone Chest
 * Tests DailyQuests progress tracking, milestone completion, and 50 Silver reward claiming.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../GameEngine/DailyQuests.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running DailyQuestsTest ===\n";

$db = $database->dblink;
$testUid = 99961;

// Cleanup test user
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "daily_quests` WHERE uid = $testUid");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES ($testUid, 'DailyQuester', 'p', 'daily@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES ($testUid, $testUid, 'DailyHero', 1, 1, 100)");

// 1. Initial State Check
$prog0 = DailyQuests::getDailyProgress($testUid);
assertTest($prog0['points'] === 0, "Initial points is 0");
assertTest($prog0['claimed'] === false, "Not claimed yet");
assertTest($prog0['can_claim'] === false, "Cannot claim yet");

// 2. Complete Task 1: Adventure (25 pts)
DailyQuests::recordProgress($testUid, 'adventure');
$prog1 = DailyQuests::getDailyProgress($testUid);
assertTest($prog1['p_adventure'] === 1, "Adventure task marked complete");
assertTest($prog1['points'] === 25, "Points now 25");
assertTest($prog1['can_claim'] === false, "Cannot claim yet at 25 pts");

// 3. Complete Task 2: Training (25 pts)
// Requires 20 units total. Sending 10 then 10.
DailyQuests::recordProgress($testUid, 'train', 10);
$prog2a = DailyQuests::getDailyProgress($testUid);
assertTest($prog2a['p_train'] === 0, "Train task not complete yet with 10 units");

DailyQuests::recordProgress($testUid, 'train', 10);
$prog2b = DailyQuests::getDailyProgress($testUid);
assertTest($prog2b['p_train'] === 1, "Train task completed after 20 total units");
assertTest($prog2b['points'] === 50, "Points now 50");

// 4. Complete Task 3: Building (25 pts)
// Requires 2 upgrades.
DailyQuests::recordProgress($testUid, 'build', 1);
$prog3a = DailyQuests::getDailyProgress($testUid);
assertTest($prog3a['p_build'] === 0, "Build task not complete with 1 upgrade");

DailyQuests::recordProgress($testUid, 'build', 1);
$prog3b = DailyQuests::getDailyProgress($testUid);
assertTest($prog3b['p_build'] === 1, "Build task completed after 2 upgrades");
assertTest($prog3b['points'] === 75, "Points now 75");

// 5. Complete Task 4: Attack / Raid (25 pts)
DailyQuests::recordProgress($testUid, 'attack', 1);
$prog4 = DailyQuests::getDailyProgress($testUid);
assertTest($prog4['p_attack'] === 1, "Attack task completed");
assertTest($prog4['points'] === 100, "Points reached 100/100");
assertTest($prog4['can_claim'] === true, "Can claim chest now");

// 6. Claim Reward
$claimRes = DailyQuests::claimReward($testUid);
assertTest($claimRes['success'] === true, "Reward claimed successfully: " . $claimRes['message']);
assertTest(BlackMarket::getSilver($testUid) === 150, "Hero Silver balance increased by 50 (100 -> 150)");

// 7. Verify Cannot Double Claim
$claimAgain = DailyQuests::claimReward($testUid);
assertTest($claimAgain['success'] === false, "Double claiming on the same day is rejected");
assertTest(BlackMarket::getSilver($testUid) === 150, "Hero Silver balance remains 150");

// Cleanup
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "daily_quests` WHERE uid = $testUid");

echo "=== All DailyQuestsTest tests passed! ===\n";
