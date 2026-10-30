<?php
/**
 * Test Suite: Bandit World Boss Bounty Pool Sharing
 * Tests BanditCamp damage contribution logging, getCampContributors calculation,
 * and proportional multi-reward splitting (Silver, CP, Hero EXP, Resources).
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
$testVrefs = [999511, 999521, 999531];
$campId = 9991;
$campWref = 9992;

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "ndata` WHERE uid = $u");
}
foreach ($testVrefs as $v) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $v");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $v");
}
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId OR wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $campId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $campWref");

// Create 3 players with initial data
// Player 1
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, cp) VALUES (99951, 'BossFighter1', 'p', 'b1@test.com', 1, 2, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver, experience) VALUES (99951, 99951, 'H1', 1, 1, 100, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES (999511, 3, 0, 10, 10, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES (999511, 99951, 'Village 1', 1, 100, 0, 1000, 1000, 1000, 1000, 100000, 100000)");

// Player 2
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, cp) VALUES (99952, 'BossFighter2', 'p', 'b2@test.com', 1, 2, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver, experience) VALUES (99952, 99952, 'H2', 1, 1, 100, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES (999521, 3, 0, 11, 11, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES (999521, 99952, 'Village 2', 1, 100, 0, 1000, 1000, 1000, 1000, 100000, 100000)");

// Player 3
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, cp) VALUES (99953, 'BossFighter3', 'p', 'b3@test.com', 1, 2, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (heroid, uid, name, unit, level, silver, experience) VALUES (99953, 99953, 'H3', 1, 1, 100, 0)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES (999531, 3, 0, 12, 12, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES (999531, 99953, 'Village 3', 1, 100, 0, 1000, 1000, 1000, 1000, 100000, 100000)");

// Create dummy world map tile and camp
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($campWref, 3, 0, 100, 100, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($campWref, 3, 'World Boss Camp', 0, 100, 0, 100000, 100000, 100000, 100000, 200000, 200000)");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bandit_camps` 
    (id, wref, tier, name, max_hp, cur_hp, bounty_wood, bounty_clay, bounty_iron, bounty_crop, reward_exp, reward_cp, reward_silver, status, created)
    VALUES ($campId, $campWref, 4, 'Benteng Raja Begal (World Boss)', 10000, 10000, 100000, 100000, 100000, 100000, 28000, 2800, 4000, 1, " . time() . ")");

$camp = [
    'id' => $campId,
    'wref' => $campWref,
    'tier' => BanditCamp::TIER_WORLDBOSS, // 4
    'name' => 'Benteng Raja Begal (World Boss)',
    'reward_exp' => 28000,
    'reward_cp' => 2800,
    'reward_silver' => 4000,
    'bounty_wood' => 100000,
    'bounty_clay' => 100000,
    'bounty_iron' => 100000,
    'bounty_crop' => 100000,
    'status' => 1
];

BanditCamp::ensureTable();

// 1. Log attack damage from 3 players:
// Player 1 deals 5,000 damage (50%)
// Player 2 deals 3,000 damage (30%)
// Player 3 deals 2,000 damage (20%) -> and lands final blow
BanditCamp::logAttackContribution($campId, 99951, 5000);
BanditCamp::logAttackContribution($campId, 99952, 3000);
BanditCamp::logAttackContribution($campId, 99953, 2000);

// 2. Test getCampContributors() before camp is cleared
$contribData = BanditCamp::getCampContributors($campId);
assertTest($contribData['total_damage'] === 10000, "Total damage should be 10000, got: " . $contribData['total_damage']);
assertTest(count($contribData['contributors']) === 3, "There should be 3 contributors, got: " . count($contribData['contributors']));

$c1 = $contribData['contributors'][0];
$c2 = $contribData['contributors'][1];
$c3 = $contribData['contributors'][2];

assertTest($c1['uid'] === 99951 && $c1['percentage'] == 50.0, "Player 1 should have rank 1 and 50% contribution");
assertTest($c1['est_silver'] === 2000, "Player 1 estimated Silver should be 2000");
assertTest($c1['est_cp'] === 1400, "Player 1 estimated CP should be 1400");
assertTest($c1['est_exp'] === 14000, "Player 1 estimated EXP should be 14000");
assertTest($c1['est_wood'] === 50000 && $c1['est_total_res'] === 200000, "Player 1 estimated wood=50k and total res=200k");

assertTest($c2['uid'] === 99952 && $c2['percentage'] == 30.0, "Player 2 should have rank 2 and 30% contribution");
assertTest($c2['est_silver'] === 1200, "Player 2 estimated Silver should be 1200");
assertTest($c2['est_cp'] === 840, "Player 2 estimated CP should be 840");
assertTest($c2['est_exp'] === 8400, "Player 2 estimated EXP should be 8400");
assertTest($c2['est_wood'] === 30000 && $c2['est_total_res'] === 120000, "Player 2 estimated wood=30k and total res=120k");

assertTest($c3['uid'] === 99953 && $c3['percentage'] == 20.0, "Player 3 should have rank 3 and 20% contribution");
assertTest($c3['est_silver'] === 800, "Player 3 estimated Silver should be 800");
assertTest($c3['est_cp'] === 560, "Player 3 estimated CP should be 560");
assertTest($c3['est_exp'] === 5600, "Player 3 estimated EXP should be 5600");
assertTest($c3['est_wood'] === 20000 && $c3['est_total_res'] === 80000, "Player 3 estimated wood=20k and total res=80k");

// 3. Clear camp with Player 3 as conqueror
$clearResult = BanditCamp::rewardAndClearCamp($camp, 99953, 'BossFighter3', 999531);
assertTest(!empty($clearResult), "Camp was successfully rewarded and cleared");

// 4. Verify Silver distribution:
// Total pool is 4,000 Silver. Initial is 100 each.
// Player 1 (50%): 2,000 Silver -> 100 + 2000 = 2100
// Player 2 (30%): 1,200 Silver -> 100 + 1200 = 1300
// Player 3 (20%): 800 Silver -> 100 + 800 = 900
$s1 = BlackMarket::getSilver(99951);
$s2 = BlackMarket::getSilver(99952);
$s3 = BlackMarket::getSilver(99953);

assertTest($s1 === 2100, "Player 1 (50%) received 2,000 Silver (now $s1)");
assertTest($s2 === 1300, "Player 2 (30%) received 1,200 Silver (now $s2)");
assertTest($s3 === 900, "Player 3 (20%) received 800 Silver (now $s3)");

// 5. Verify CP distribution:
// Total pool is 2,800 CP. Initial is 0 each.
// Player 1 (50%): 1,400 CP
// Player 2 (30%): 840 CP
// Player 3 (20%): 560 CP
$cp1 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT cp FROM `" . TB_PREFIX . "users` WHERE id = 99951"))['cp'];
$cp2 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT cp FROM `" . TB_PREFIX . "users` WHERE id = 99952"))['cp'];
$cp3 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT cp FROM `" . TB_PREFIX . "users` WHERE id = 99953"))['cp'];

assertTest($cp1 === 1400, "Player 1 received 1,400 CP (now $cp1)");
assertTest($cp2 === 840, "Player 2 received 840 CP (now $cp2)");
assertTest($cp3 === 560, "Player 3 received 560 CP (now $cp3)");

// 6. Verify Hero EXP distribution:
// Total pool is 28,000 EXP. Initial is 0 each.
// Player 1 (50%): 14,000 EXP
// Player 2 (30%): 8,400 EXP
// Player 3 (20%): 5,600 EXP
$exp1 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT experience FROM `" . TB_PREFIX . "hero` WHERE uid = 99951"))['experience'];
$exp2 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT experience FROM `" . TB_PREFIX . "hero` WHERE uid = 99952"))['experience'];
$exp3 = (int)mysqli_fetch_assoc(mysqli_query($db, "SELECT experience FROM `" . TB_PREFIX . "hero` WHERE uid = 99953"))['experience'];

assertTest($exp1 === 14000, "Player 1 received 14,000 Hero EXP (now $exp1)");
assertTest($exp2 === 8400, "Player 2 received 8,400 Hero EXP (now $exp2)");
assertTest($exp3 === 5600, "Player 3 received 5,600 Hero EXP (now $exp3)");

// 7. Verify Resources distribution into villages:
// Total pool is 100,000 each resource. Initial was 1,000 in village.
// Player 1 (50%): +50,000 each -> 51,000
// Player 2 (30%): +30,000 each -> 31,000
// Player 3 (20%): +20,000 each -> 21,000
$v1Res = mysqli_fetch_assoc(mysqli_query($db, "SELECT wood, clay, iron, crop FROM `" . TB_PREFIX . "vdata` WHERE wref = 999511"));
$v2Res = mysqli_fetch_assoc(mysqli_query($db, "SELECT wood, clay, iron, crop FROM `" . TB_PREFIX . "vdata` WHERE wref = 999521"));
$v3Res = mysqli_fetch_assoc(mysqli_query($db, "SELECT wood, clay, iron, crop FROM `" . TB_PREFIX . "vdata` WHERE wref = 999531"));

assertTest((int)$v1Res['wood'] === 51000 && (int)$v1Res['clay'] === 51000, "Player 1 village received 50k wood & clay (now " . $v1Res['wood'] . ")");
assertTest((int)$v2Res['wood'] === 31000 && (int)$v2Res['iron'] === 31000, "Player 2 village received 30k wood & iron (now " . $v2Res['wood'] . ")");
assertTest((int)$v3Res['wood'] === 21000 && (int)$v3Res['crop'] === 21000, "Player 3 village received 20k wood & crop (now " . $v3Res['wood'] . ")");

// 8. Verify notice generated for all players
$n1 = mysqli_fetch_assoc(mysqli_query($db, "SELECT topic, data FROM `" . TB_PREFIX . "ndata` WHERE uid = 99951 ORDER BY id DESC LIMIT 1"));
assertTest(!empty($n1) && strpos($n1['topic'], 'WORLD BOSS') !== false, "Player 1 received World Boss notice");
assertTest(strpos($n1['data'], '50%') !== false && strpos($n1['data'], 'Koin Silver') !== false, "Player 1 notice contains 50% and Silver breakdown");

// Cleanup
foreach ($testUids as $u) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $u");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "ndata` WHERE uid = $u");
}
foreach ($testVrefs as $v) {
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $v");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $v");
}
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId OR wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $campId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $campWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $campWref");

echo "=== All BanditCampBountyPoolTest tests passed! ===\n";
