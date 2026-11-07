<?php

#################################################################################
##  Filename       : tests/BotConquestNaturalChiefTrainingTest.php             ##
##  Type           : Unit Test for Natural Bot Chief Retraining & Resource    ##
##                   Prioritization on Casualty                                ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertFalse($condition, $message) {
    if ($condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running BotConquestNaturalChiefTrainingTest ===" . PHP_EOL;
global $database;

// ------------------------------------------------------------------------------
// SETUP FIXTURES
// ------------------------------------------------------------------------------
$testSuffix = substr(md5((string)microtime(true)), 0, 6);
$botName = 'BotNatChief_' . $testSuffix;
$botEmail = 'bot_' . $testSuffix . '@bot.travianz';
$humanName = 'HumanNatChief_' . $testSuffix;
$humanEmail = 'human_' . $testSuffix . '@example.com';

// 1. Create Bot User (Teuton tribe 2)
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, desc1, regtime, cp)
    VALUES ('$botName', MD5('password'), '$botEmail', 2, 2, 1, '[#BOT] Natural Chief Test', " . (time() - 86400 * 15) . ", 15000)
");
$botUid = (int)mysqli_insert_id($database->dblink);

// Bot Village
$botCoords = [84, 84];
$botWRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$botCoords[0]} AND y = {$botCoords[1]} LIMIT 1");
if (empty($botWRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$botCoords[0]}, {$botCoords[1]}, 1, 'f3')");
    $botWid = (int)mysqli_insert_id($database->dblink);
} else {
    $botWid = (int)$botWRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $botWid");
}

$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop, created)
    VALUES ($botWid, $botUid, 'Bot Training Camp', 1, 350, 15000, 60000, 60000, 60000, 60000, 80000, 80000, " . (time() - 86400 * 15) . ")
    ON DUPLICATE KEY UPDATE owner = $botUid, name = 'Bot Training Camp', capital = 1, wood = 60000, clay = 60000, iron = 60000, crop = 60000, maxstore = 80000, maxcrop = 80000, created = " . (time() - 86400 * 15) . "
");

// Setup Residence Level 10 (f28t = 25, f28 = 10) and Barracks (f21t = 19, f21 = 5)
$database->query("
    INSERT INTO " . TB_PREFIX . "fdata (vref, f1t, f28, f28t, f21, f21t, f22, f22t, f39, f39t)
    VALUES ($botWid, 1, 10, 25, 5, 19, 20, 22, 10, 16)
    ON DUPLICATE KEY UPDATE f28 = 10, f28t = 25, f21 = 5, f21t = 19, f22 = 20, f22t = 22, f39 = 10, f39t = 16
");

// Units: Initially 0 Chiefs (died in battle!), 200 Clubswingers (u11)
$database->query("
    INSERT INTO " . TB_PREFIX . "units (vref, u11, u12, u13, u14, u15, u16, u17, u18, u19, u20, hero)
    VALUES ($botWid, 200, 0, 0, 0, 0, 0, 0, 25, 0, 0, 0)
    ON DUPLICATE KEY UPDATE u11 = 200, u18 = 25, u19 = 0
");

// Technology: Chief (u19) researched
$database->query("
    INSERT INTO " . TB_PREFIX . "tdata (vref, t19)
    VALUES ($botWid, 1)
    ON DUPLICATE KEY UPDATE t19 = 1
");

// Clear existing training queue for bot village
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $botWid");

// 2. Create Target Human Village
$humanCoords = [86, 84];
$humanWRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$humanCoords[0]} AND y = {$humanCoords[1]} LIMIT 1");
if (empty($humanWRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$humanCoords[0]}, {$humanCoords[1]}, 1, 'f3')");
    $targetWid = (int)mysqli_insert_id($database->dblink);
} else {
    $targetWid = (int)$humanWRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $targetWid");
}

$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, regtime)
    VALUES ('$humanName', MD5('password'), '$humanEmail', 1, 2, 0, " . time() . ")
");
$humanUid = (int)mysqli_insert_id($database->dblink);

// Human Capital Village (so target is a 2nd conquerable village)
$capCoords = [88, 84];
$capWRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$capCoords[0]} AND y = {$capCoords[1]} LIMIT 1");
if (empty($capWRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$capCoords[0]}, {$capCoords[1]}, 1, 'f3')");
    $capWid = (int)mysqli_insert_id($database->dblink);
} else {
    $capWid = (int)$capWRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $capWid");
}
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp)
    VALUES ($capWid, $humanUid, 'Human Capital', 1, 300, 5000)
    ON DUPLICATE KEY UPDATE owner = $humanUid, name = 'Human Capital', capital = 1
");

// Target Village (non-capital, loyalty 70)
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($targetWid, $humanUid, 'Human Moon Village', 0, 200, 70)
    ON DUPLICATE KEY UPDATE owner = $humanUid, name = 'Human Moon Village', capital = 0, loyalty = 70
");

// Register active target lock for this bot village
BotAI::setConquestOrder($botWid, [
    'bot_wid' => $botWid,
    'bot_uid' => $botUid,
    'bot_username' => $botName,
    'target_wid' => $targetWid,
    'target_village' => 'Human Moon Village',
    'target_owner' => $humanUid,
    'target_username' => $humanName,
    'escort_count' => 150,
    'catapult_count' => 25,
    'send_hero' => false,
    'auto_prepare' => true,
    'allow_capital_conquest' => false,
    'started_at' => time() - 3600,
]);

// Temporarily disable night sleep for deterministic testing
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => false]);

$botData = [
    'uid' => $botUid,
    'wref' => $botWid,
    'tribe' => 2,
    'pop' => 350,
    'regtime' => time() - 86400 * 15,
    'created' => time() - 86400 * 15,
    'wood' => 60000,
    'clay' => 60000,
    'iron' => 60000,
    'crop' => 60000,
    'maxstore' => 80000,
    'maxcrop' => 80000,
];

// ------------------------------------------------------------------------------
// TEST 1: Chief Casualty - NO Instant Spawn, Natural Training Queued
// ------------------------------------------------------------------------------
echo "Test 1: Chief death does NOT instant spawn; trains naturally in Residence... ";

$resProcess = BotAI::processConquest($botData);
assertFalse(empty($resProcess), "processConquest must return an action report");

// Verify that units.u19 is STILL 0 at home (NO instant spawn!)
$homeChiefs = (int)($database->query_return("SELECT u19 FROM " . TB_PREFIX . "units WHERE vref = $botWid")[0]['u19'] ?? 0);
assertEqual(0, $homeChiefs, "Chief MUST NOT be cheat-spawned into units table instantly!");

// Verify that Chief (u19) was enqueued naturally into training table
$trainRow = $database->query_return("
    SELECT * FROM " . TB_PREFIX . "training
    WHERE vref = $botWid AND unit = 19
    LIMIT 1
");
assertFalse(empty($trainRow), "Chief (u19) MUST be present in training queue");
assertEqual(1, (int)$trainRow[0]['amt'], "Enqueued training amount must be 1 Chief");
assertTrue((int)$trainRow[0]['eachtime'] > 0, "Chief training duration (eachtime) must be positive");
assertTrue((int)$trainRow[0]['timestamp'] >= time() + (int)$trainRow[0]['eachtime'], "Chief finish timestamp must reflect duration");

// Verify that resources were deducted
$vRes = $database->query_return("SELECT wood, clay, iron, crop FROM " . TB_PREFIX . "vdata WHERE wref = $botWid")[0];
assertTrue((float)$vRes['wood'] < 60000, "Wood must be deducted for training Chief");
assertTrue((float)$vRes['clay'] < 60000, "Clay must be deducted for training Chief");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: While Chief is in Training, Wave Does NOT Dispatch
// ------------------------------------------------------------------------------
echo "Test 2: While Chief is in training queue, wave is withheld... ";
$waitReport = BotAI::processConquest($botData);
assertTrue(strpos($waitReport, 'sedang dilatih') !== false || strpos($waitReport, 'currently training') !== false, "Bot must report Chief is currently training");

// Verify no outgoing attack movement was created yet
$movCount = (int)($database->query_return("SELECT COUNT(*) as cnt FROM " . TB_PREFIX . "movement WHERE `from` = $botWid AND `to` = $targetWid AND proc = 0")[0]['cnt'] ?? 0);
assertEqual(0, $movCount, "No attack movement must be created while Chief is training in queue");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Resource Prioritization in processBuild
// ------------------------------------------------------------------------------
echo "Test 3: Resource prioritization in processBuild defers non-conquest buildings... ";
// Clear bdata and training so Chief is not in queue and not at home
$database->query("DELETE FROM " . TB_PREFIX . "bdata WHERE wid = $botWid");
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $botWid");
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 0, u11 = 20 WHERE vref = $botWid");

// Case 3A: Chief is needed -> generic building upgrades are postponed
$buildPostponedChief = BotAI::processBuild($botData);
assertFalse(empty($buildPostponedChief), "processBuild must postpone construction while Chief is needed");
assertTrue(strpos($buildPostponedChief, 'Pembangunan ditunda') !== false, "Build postponement message must cite Chief priority");

// Case 3B: Chief is queued, but escort army is below target and resources are low
BotAI::trainChiefNaturally($botData, 19, ['auto_prepare' => true]);
$database->query("UPDATE " . TB_PREFIX . "vdata SET wood = 2000, clay = 2000, iron = 2000, crop = 1000 WHERE wref = $botWid");
$botDataLow = array_merge($botData, ['wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 1000]);
$buildPostponedArmy = BotAI::processBuild($botDataLow);
assertFalse(empty($buildPostponedArmy), "processBuild must postpone construction while escort army is needed with low resources");
assertTrue(strpos($buildPostponedArmy, 'Pembangunan ditunda') !== false, "Build postponement message must cite army prioritization");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: Resource Prioritization & Cap Expansion in processTrain
// ------------------------------------------------------------------------------
echo "Test 4: Resource prioritization & escort training in processTrain... ";
// Restore ample resources
$database->query("UPDATE " . TB_PREFIX . "vdata SET wood = 50000, clay = 50000, iron = 50000, crop = 50000 WHERE wref = $botWid");
$botDataHigh = array_merge($botData, ['wood' => 50000, 'clay' => 50000, 'iron' => 50000, 'crop' => 50000]);
$trainResult = BotAI::processTrain($botDataHigh);
assertFalse(empty($trainResult), "processTrain must train military units for active conquest");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Natural Training Completion & Next Wave Dispatch
// ------------------------------------------------------------------------------
echo "Test 5: Natural training completion & next wave dispatch... ";
// Simulate training completion: remove from training table and place 1 Chief at home
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $botWid");
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 1, u11 = 200, u18 = 25 WHERE vref = $botWid");

// Call processConquest now that Chief and escort are at home
$dispatchReport = BotAI::processConquest($botDataHigh);
assertFalse(empty($dispatchReport), "processConquest must report wave dispatch");
assertTrue(strpos($dispatchReport, 'Dispatched next conquest wave') !== false || strpos($dispatchReport, 'Meluncurkan gelombang penaklukan') !== false, "Bot must dispatch wave when Chief is home");

// Verify movement was created with sort_type = 3
$mRow = $database->query_return("
    SELECT m.*, a.attack_type, a.t9
    FROM " . TB_PREFIX . "movement m
    JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
    WHERE m.from = $botWid AND m.to = $targetWid AND m.proc = 0
    ORDER BY m.moveid DESC LIMIT 1
");
assertFalse(empty($mRow), "Attack movement must be in flight");
assertEqual(3, (int)$mRow[0]['attack_type'], "Conquest wave must be attack_type = 3 (Normal Attack)");
assertEqual(1, (int)$mRow[0]['t9'], "Teuton Chief (slot t9) must be 1");

// Verify active order has auto_prepare = false so subsequent waves never cheat
$activeOrderAfter = BotAI::getConquestOrder($botWid);
assertFalse(empty($activeOrderAfter), "Active order must still exist");
assertFalse(!empty($activeOrderAfter['auto_prepare']), "Active order auto_prepare MUST be false for subsequent waves");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// CLEANUP FIXTURES
// ------------------------------------------------------------------------------
echo "Cleaning up test records... ";
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid OR `to` IN ($targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "attacks WHERE vref IN ($botWid, $targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $botWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($botWid, $targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($botWid, $targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref IN ($botWid, $targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($botWid, $targetWid, $capWid)");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($botUid, $humanUid)");
BotAI::clearConquestOrder($botWid);
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => true]);
echo "CLEANUP COMPLETE" . PHP_EOL;

echo "=== All BotConquestNaturalChiefTrainingTest tests PASSED successfully! ===" . PHP_EOL;
