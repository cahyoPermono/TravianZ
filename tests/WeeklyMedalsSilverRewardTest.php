<?php
/**
 * Test Suite: Weekly Medals Silver Reward
 * Verifies that top medal winners receive silver bonuses according to their ranks.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationMedals.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running WeeklyMedalsSilverRewardTest ===\n";

class TestAutomationMedals {
    use AutomationMedals;

    public function testAwardTopMedals($field, $category, $imgPrefix, $week, $userFilter) {
        $this->awardTopMedals($field, $category, $imgPrefix, $week, $userFilter);
    }
}

$db = $database->dblink;
$testUids = [99981, 99982, 99983, 99984, 99985, 99986];

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "medal` WHERE userid = $u");
}

// User 1: 1000 ap (Rank 1) -> +300 silver
// User 2: 800 ap (Rank 2) -> +150 silver
// User 3: 500 ap (Rank 3) -> +150 silver
// User 4: 300 ap (Rank 4) -> +100 silver (Tier 4 & 5 dibedakan dari 6-10)
// User 5: 200 ap (Rank 5) -> +100 silver (Tier 4 & 5 dibedakan dari 6-10)
// User 6: 100 ap (Rank 6) -> +50 silver
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99981, 'Rank1Attacker', 'p', 'r1@test.com', 1, 2, 1000)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99981, 99981, 'Hero1', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99982, 'Rank2Attacker', 'p', 'r2@test.com', 1, 2, 800)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99982, 99982, 'Hero2', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99983, 'Rank3Attacker', 'p', 'r3@test.com', 1, 2, 500)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99983, 99983, 'Hero3', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99984, 'Rank4Attacker', 'p', 'r4@test.com', 1, 2, 300)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99984, 99984, 'Hero4', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99985, 'Rank5Attacker', 'p', 'r5@test.com', 1, 2, 200)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99985, 99985, 'Hero5', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, ap) VALUES (99986, 'Rank6Attacker', 'p', 'r6@test.com', 1, 2, 100)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99986, 99986, 'Hero6', 1, 1, 100)");

$tester = new TestAutomationMedals();
$filter = "id IN (" . implode(',', $testUids) . ")";
$tester->testAwardTopMedals('ap', 1, 't2_', 1, $filter);

$s1 = BlackMarket::getSilver(99981);
$s2 = BlackMarket::getSilver(99982);
$s3 = BlackMarket::getSilver(99983);
$s4 = BlackMarket::getSilver(99984);
$s5 = BlackMarket::getSilver(99985);
$s6 = BlackMarket::getSilver(99986);

assertTest($s1 === 400, "Rank 1 received +300 Silver (100 -> 400)");
assertTest($s2 === 250, "Rank 2 received +150 Silver (100 -> 250)");
assertTest($s3 === 250, "Rank 3 received +150 Silver (100 -> 250)");
assertTest($s4 === 200, "Rank 4 received +100 Silver (100 -> 200)");
assertTest($s5 === 200, "Rank 5 received +100 Silver (100 -> 200)");
assertTest($s6 === 150, "Rank 6 received +50 Silver (100 -> 150)");

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "medal` WHERE userid = $u");
}

echo "=== All WeeklyMedalsSilverRewardTest tests passed! ===\n";
