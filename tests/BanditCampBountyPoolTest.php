<?php
/**
 * Test Suite: Bandit World Boss Bounty Pool Sharing
 * Tests BanditCamp damage contribution logging and proportional Silver reward splitting.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running BanditCampBountyPoolTest ===\n";

$db = $database->dblink;
$testUids = [99951, 99952, 99953];
$campId = 9991;
$campWref = 9992;

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
}
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId OR wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $campId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $campWref");

// Create 3 players with 100 silver each
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES (99951, 'BossFighter1', 'p', 'b1@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99951, 99951, 'H1', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES (99952, 'BossFighter2', 'p', 'b2@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99952, 99952, 'H2', 1, 1, 100)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access) VALUES (99953, 'BossFighter3', 'p', 'b3@test.com', 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver) VALUES (99953, 99953, 'H3', 1, 1, 100)");

// Create dummy world map tile and camp
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($campWref, 3, 0, 100, 100, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop) VALUES ($campWref, 3, 'World Boss Camp', 0, 100, 0, 1000, 1000, 1000, 1000)");

$camp = [
    'id' => $campId,
    'wref' => $campWref,
    'tier' => BanditCamp::TIER_WORLDBOSS, // 4
    'name' => 'Benteng Raja Begal (World Boss)',
    'reward_exp' => 28000,
    'reward_cp' => 2800,
    'reward_silver' => 4000,
    'status' => 1
];

BanditCamp::ensureTable();

// 1. Log attack damage from 3 players:
// Player 1 deals 5,000 damage (50%)
// Player 2 deals 3,000 damage (30%)
// Player 3 deals 2,000 damage (20%) -> and lands the final blow
BanditCamp::logAttackContribution($campId, 99951, 5000);
BanditCamp::logAttackContribution($campId, 99952, 3000);
BanditCamp::logAttackContribution($campId, 99953, 2000);

// 2. Clear camp with Player 3 as conqueror
$clearResult = BanditCamp::rewardAndClearCamp($camp, 99953, 'BossFighter3', 0);
assertTest(!empty($clearResult), "Camp was successfully rewarded and cleared");

// 3. Verify Silver distribution:
// Total pool is 4,000 Silver.
// Player 1 (50%): 2,000 Silver -> 100 + 2000 = 2100
// Player 2 (30%): 1,200 Silver -> 100 + 1200 = 1300
// Player 3 (20%): 800 Silver -> 100 + 800 = 900
$s1 = BlackMarket::getSilver(99951);
$s2 = BlackMarket::getSilver(99952);
$s3 = BlackMarket::getSilver(99953);

assertTest($s1 === 2100, "Player 1 (50% damage) received 2,000 Silver (now $s1)");
assertTest($s2 === 1300, "Player 2 (30% damage) received 1,200 Silver (now $s2)");
assertTest($s3 === 900, "Player 3 (20% damage) received 800 Silver (now $s3)");

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
}
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId OR wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $campId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $campWref");

echo "=== All BanditCampBountyPoolTest tests passed! ===\n";
