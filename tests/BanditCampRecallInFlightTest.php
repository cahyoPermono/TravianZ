<?php
/**
 * Test for Bandit Camp In-Flight Armies Auto-Recall & Scout Intel Consolation
 *
 * Verifies:
 * 1. When a bandit camp is cleared by Player 1, any in-flight attacks from Player 2
 *    are automatically turned around immediately (sort_type = 4).
 * 2. Return duration is shortened based on elapsed travel time so far.
 * 3. Player 2's hero receives +25 Silver and +25 Hero EXP consolation.
 * 4. Player 2 receives an in-game Intel Notice (ntype = 28) with details.
 * 5. Fallback handler handleArrivedAtClearedCamp handles simultaneous arrival at razed tile.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';

$dblink = $database->dblink;

echo "=== STARTING BANDIT CAMP RECALL IN-FLIGHT TEST ===" . PHP_EOL;

// 1. Find two test players who own villages
$users = $database->query_return("
    SELECT u.id, u.username, v.wref as vid
    FROM " . TB_PREFIX . "users u
    JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
    WHERE u.id > 4
    GROUP BY u.id
    ORDER BY u.id ASC
    LIMIT 2
");

if (count($users) < 2) {
    echo "Need at least 2 non-system users with villages to test." . PHP_EOL;
    exit(1);
}

$p1Uid = (int)$users[0]['id'];
$p1Name = $users[0]['username'];
$p1Vid = (int)$users[0]['vid'];

$p2Uid = (int)$users[1]['id'];
$p2Name = $users[1]['username'];
$p2Vid = (int)$users[1]['vid'];

echo "Player 1 (Conqueror): $p1Name (UID: $p1Uid, VID: $p1Vid)" . PHP_EOL;
echo "Player 2 (Recalled):   $p2Name (UID: $p2Uid, VID: $p2Vid)" . PHP_EOL;

// Ensure hero rows exist
$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, wref, experience, silver) VALUES ($p1Uid, $p1Vid, 100, 100) ON DUPLICATE KEY UPDATE uid = uid");
$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, wref, experience, silver) VALUES ($p2Uid, $p2Vid, 100, 100) ON DUPLICATE KEY UPDATE uid = uid");

$p2HeroBefore = $database->query_return("SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $p2Uid LIMIT 1")[0];
$p2ExpBefore = (int)$p2HeroBefore['experience'];
$p2SilverBefore = (int)$p2HeroBefore['silver'];

echo "Player 2 Hero before: EXP = $p2ExpBefore, Silver = $p2SilverBefore" . PHP_EOL;

// 2. Setup a dummy Bandit Camp
$now = time();
$dummyWref = 99998;
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "bandit_camps WHERE wref = $dummyWref");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $dummyWref");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "units WHERE vref = $dummyWref");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "wdata WHERE id = $dummyWref");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "ndata WHERE toWref = $dummyWref OR (uid = $p2Uid AND ntype = 28 AND time >= " . ($now - 60) . ")");

mysqli_query($dblink, "INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($dummyWref, 1, 0, 123, -123, 1, 'bc_3')");
mysqli_query($dblink, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp, wood, clay, iron, crop) VALUES ($dummyWref, 3, 'Sarang Penyamun Uji Coba', 0, 100, 100, 1000, 1000, 1000, 1000)");
mysqli_query($dblink, "INSERT INTO " . TB_PREFIX . "units (vref, u31, u32) VALUES ($dummyWref, 10, 5)");

mysqli_query($dblink, "INSERT INTO " . TB_PREFIX . "bandit_camps (wref, tier, name, max_hp, cur_hp, reward_exp, reward_cp, reward_silver, status, created) VALUES ($dummyWref, 2, 'Sarang Penyamun Uji Coba', 100, 100, 500, 100, 200, 1, $now)");
$campId = mysqli_insert_id($dblink);

// 3. Create Player 2's in-flight attack targeting this dummy camp
// Simulate: attack started 600s ago, scheduled arrival is in 1800s (total trip 2400s)
$startTime = $now - 600;
$endTime = $now + 1800;

mysqli_query($dblink, "INSERT INTO " . TB_PREFIX . "attacks (vref, t1, t2, t11, attack_type) VALUES ($p2Vid, 20, 10, 1, 3)");
$refId = mysqli_insert_id($dblink);

$database->addMovement(3, $p2Vid, $dummyWref, $refId, $startTime, $endTime);
$moveRes = $database->query_return("SELECT moveid FROM " . TB_PREFIX . "movement WHERE ref = $refId AND `from` = $p2Vid AND `to` = $dummyWref LIMIT 1");
$moveId = (int)$moveRes[0]['moveid'];

echo "Created in-flight attack: moveid = $moveId, ref = $refId, total travel duration = 2400s, elapsed = 600s" . PHP_EOL;

// 4. Verify camp is ready
$camp = $database->query_return("SELECT * FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId LIMIT 1")[0];

// 5. Player 1 clears the camp!
echo "Clearing camp by Player 1 ($p1Name)..." . PHP_EOL;
$clearRes = BanditCamp::rewardAndClearCamp($camp, $p1Uid, $p1Name, $p1Vid);

// 6. Assertions on Camp cleanup
$campAfter = $database->query_return("SELECT status, cleared_by FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId LIMIT 1")[0];
assert((int)$campAfter['status'] === 0, "Camp status must be 0 (cleared)");
assert((int)$campAfter['cleared_by'] === $p1Uid, "Camp cleared_by must be Player 1 UID");

$vdataCheck = $database->query_return("SELECT wref FROM " . TB_PREFIX . "vdata WHERE wref = $dummyWref");
assert(empty($vdataCheck), "Camp vdata must be deleted");

// 7. Assertions on Player 2's in-flight attack
$origMove = $database->query_return("SELECT proc FROM " . TB_PREFIX . "movement WHERE moveid = $moveId LIMIT 1")[0];
assert((int)$origMove['proc'] === 1, "Original in-flight movement must be marked proc = 1");

$returnMove = $database->query_return("SELECT * FROM " . TB_PREFIX . "movement WHERE `from` = $dummyWref AND `to` = $p2Vid AND sort_type = 4 AND ref = $refId AND proc = 0 LIMIT 1");
assert(!empty($returnMove), "New return movement (sort_type = 4) must exist for Player 2");

$retDuration = (int)$returnMove[0]['endtime'] - $now;
echo "Return movement created: duration = {$retDuration}s (original remaining was 1800s, cut to ~600s)" . PHP_EOL;
assert($retDuration >= 595 && $retDuration <= 605, "Return duration should equal elapsed time (~600s), but got {$retDuration}s");

// 8. Assertions on Player 2's Hero Consolation Reward (+25 Silver & +25 EXP)
$p2HeroAfter = $database->query_return("SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $p2Uid LIMIT 1")[0];
$p2ExpAfter = (int)$p2HeroAfter['experience'];
$p2SilverAfter = (int)$p2HeroAfter['silver'];

echo "Player 2 Hero after: EXP = $p2ExpAfter (diff: " . ($p2ExpAfter - $p2ExpBefore) . "), Silver = $p2SilverAfter (diff: " . ($p2SilverAfter - $p2SilverBefore) . ")" . PHP_EOL;
assert($p2ExpAfter === $p2ExpBefore + 25, "Player 2 should receive +25 Hero EXP");
assert($p2SilverAfter === $p2SilverBefore + 25, "Player 2 should receive +25 Silver");

// 9. Assertions on Notice / Report (ntype = 28)
$notice = $database->query_return("SELECT * FROM " . TB_PREFIX . "ndata WHERE uid = $p2Uid AND ntype = 28 ORDER BY time DESC LIMIT 1");
assert(!empty($notice), "Player 2 should receive notice of ntype 28");
assert(strpos($notice[0]['topic'], '[INTEL PENYAMUN]') !== false, "Topic should contain [INTEL PENYAMUN]");

parse_str($notice[0]['data'], $parsedData);
assert(!empty($parsedData['camp_name']), "Notice data should contain camp_name");
assert($parsedData['conqueror'] === $p1Name, "Notice data conqueror should match Player 1");
assert((int)$parsedData['silver'] === 25, "Notice data silver should be 25");
assert((int)$parsedData['exp'] === 25, "Notice data exp should be 25");

echo "Notice payload verified: " . $notice[0]['topic'] . PHP_EOL;

// 10. Test fallback handler handleArrivedAtClearedCamp
echo "Testing handleArrivedAtClearedCamp fallback..." . PHP_EOL;
$dummyBounceEnd = $now + 1200;
BanditCamp::handleArrivedAtClearedCamp($dummyWref, $p2Uid, $dummyBounceEnd);
// Since p2 already received notice within 5 minutes, it should avoid duplicate bonus
$p2HeroAfterDup = $database->query_return("SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $p2Uid LIMIT 1")[0];
assert((int)$p2HeroAfterDup['silver'] === $p2SilverAfter, "Duplicate call within 5 minutes should not give double silver");

// Clean up test data
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "attacks WHERE id = $refId");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "movement WHERE moveid = $moveId OR (ref = $refId AND `from` = $dummyWref)");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "wdata WHERE id = $dummyWref");
mysqli_query($dblink, "DELETE FROM " . TB_PREFIX . "ndata WHERE id = " . (int)$notice[0]['id']);

// Restore hero to before
mysqli_query($dblink, "UPDATE " . TB_PREFIX . "hero SET experience = $p2ExpBefore, silver = $p2SilverBefore WHERE uid = $p2Uid");

echo "=== ALL TESTS PASSED SUCCESSFULLY! ===" . PHP_EOL;
