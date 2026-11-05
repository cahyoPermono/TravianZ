<?php

#################################################################################
##  Filename       : tests/BotAdminOrderConquestTest.php                       ##
##  Type           : Unit Test for Admin Order Bot Chief Conquest Feature      ##
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

echo "=== Running BotAdminOrderConquestTest ===" . PHP_EOL;
global $database;

// ------------------------------------------------------------------------------
// TEST 1: Validation - Empty target input returns error
// ------------------------------------------------------------------------------
echo "Test 1: Validation - Empty target input returns error... ";
$res1 = BotAI::orderConquest('Alaric', '');
assertEqual('error', $res1['status'], "Empty target must return error");
assertTrue(strpos($res1['message'], 'tidak boleh kosong') !== false, "Error message must mention empty target");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Validation - Non-existent target returns error
// ------------------------------------------------------------------------------
echo "Test 2: Validation - Non-existent target returns error... ";
$res2 = BotAI::orderConquest('Alaric', 'NonExistentVillageXYZ_9999');
assertEqual('error', $res2['status'], "Non-existent target must return error");
assertTrue(strpos($res2['message'], 'tidak ditemukan') !== false, "Error message must mention target not found");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// SETUP FIXTURES: Test Bot & Test Human with multiple villages
// ------------------------------------------------------------------------------
echo "Setting up test fixtures... ";
$testSuffix = substr(md5((string)microtime(true)), 0, 6);
$botName = 'BotConqTest_' . $testSuffix;
$botEmail = 'bot_' . $testSuffix . '@bot.travianz';
$humanName = 'HumanConqTest_' . $testSuffix;
$humanEmail = 'human_' . $testSuffix . '@example.com';

// 1. Create Bot User (Teuton tribe 2)
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, desc1, regtime, cp)
    VALUES ('$botName', MD5('password'), '$botEmail', 2, 2, 1, '[#BOT] Admin Conquest Test', " . time() . ", 5000)
");
$botUid = (int)mysqli_insert_id($database->dblink);

// Create Bot Village
$botCoords = [80, 80];
$botWRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$botCoords[0]} AND y = {$botCoords[1]} LIMIT 1");
if (empty($botWRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$botCoords[0]}, {$botCoords[1]}, 1, 'f3')");
    $botWid = (int)mysqli_insert_id($database->dblink);
} else {
    $botWid = (int)$botWRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $botWid");
}
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp)
    VALUES ($botWid, $botUid, 'Bot Bastion', 1, 350, 5000)
    ON DUPLICATE KEY UPDATE owner = $botUid, name = 'Bot Bastion', capital = 1
");
$database->query("
    INSERT INTO " . TB_PREFIX . "fdata (vref, f1t, f28, f28t)
    VALUES ($botWid, 1, 10, 25)
    ON DUPLICATE KEY UPDATE f28 = 10, f28t = 25
");
$database->query("
    INSERT INTO " . TB_PREFIX . "units (vref, u11, u19, u20, hero)
    VALUES ($botWid, 100, 0, 0, 0)
    ON DUPLICATE KEY UPDATE u11 = 100, u19 = 0, u20 = 0, hero = 0
");
$database->query("
    INSERT INTO " . TB_PREFIX . "tdata (vref, t19)
    VALUES ($botWid, 0)
    ON DUPLICATE KEY UPDATE t19 = 0
");

// 2. Create Human User
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, regtime, cp)
    VALUES ('$humanName', MD5('pass'), '$humanEmail', 1, 2, 0, " . time() . ", 5000)
");
$humanUid = (int)mysqli_insert_id($database->dblink);

// Create Human Capital Village
$capCoords = [85, 80];
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
    VALUES ($capWid, $humanUid, 'Human Capital', 1, 500, 5000)
    ON DUPLICATE KEY UPDATE owner = $humanUid, name = 'Human Capital', capital = 1
");

// Create Human Second Village (Non-Capital, Conquering Target!)
$secCoords = [88, 80];
$secWRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$secCoords[0]} AND y = {$secCoords[1]} LIMIT 1");
if (empty($secWRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$secCoords[0]}, {$secCoords[1]}, 1, 'f3')");
    $secWid = (int)mysqli_insert_id($database->dblink);
} else {
    $secWid = (int)$secWRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $secWid");
}
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp)
    VALUES ($secWid, $humanUid, 'Human Colony', 0, 300, 2000)
    ON DUPLICATE KEY UPDATE owner = $humanUid, name = 'Human Colony', capital = 0
");
$database->query("
    INSERT INTO " . TB_PREFIX . "units (vref, u1)
    VALUES ($secWid, 50)
    ON DUPLICATE KEY UPDATE u1 = 50
");

echo "FIXTURES READY" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Capital Protection Check & Capital Override Check
// ------------------------------------------------------------------------------
echo "Test 3: Capital Protection Check... ";
// Attempt conquest against Capital without override
$resCapBlocked = BotAI::orderConquest($botName, 'Human Capital', [
    'allow_capital_conquest' => false,
]);
assertEqual('error', $resCapBlocked['status'], "Conquest against Capital without override must be blocked");
assertTrue(strpos($resCapBlocked['message'], 'Ibu Kota') !== false, "Error message must explain Capital protection");

// Attempt conquest against Capital WITH override
$resCapAllowed = BotAI::orderConquest($botName, 'Human Capital', [
    'allow_capital_conquest' => true,
    'auto_prepare' => true,
]);
assertEqual('success', $resCapAllowed['status'], "Conquest against Capital with override must succeed");
// Verify capital flag was stripped
$capCheck = (int)($database->query_return("SELECT capital FROM " . TB_PREFIX . "vdata WHERE wref = $capWid")[0]['capital'] ?? 1);
assertEqual(0, $capCheck, "Target capital flag must be 0 after override");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: Bot Resolution by Username, UID, and Auto-Closest
// ------------------------------------------------------------------------------
echo "Test 4: Bot Resolution (Username, UID, Auto)... ";
// A. By Username
$resByBotName = BotAI::orderConquest($botName, 'Human Colony', ['auto_prepare' => true]);
assertEqual('success', $resByBotName['status'], "Conquest by bot username must succeed");
assertEqual($botName, $resByBotName['details']['bot_username'], "Resolved bot username must match");

// B. By Bot UID
$resByBotUid = BotAI::orderConquest($botUid, 'Human Colony', ['auto_prepare' => true]);
assertEqual('success', $resByBotUid['status'], "Conquest by bot UID must succeed");
assertEqual($botUid, $resByBotUid['details']['bot_uid'], "Resolved bot UID must match");

// C. By Auto ('0' or 'auto')
$resByAuto = BotAI::orderConquest('auto', 'Human Colony', ['auto_prepare' => true]);
assertEqual('success', $resByAuto['status'], "Conquest by auto-closest bot must succeed");
assertTrue(!empty($resByAuto['details']['bot_uid']), "Auto bot must find a valid bot");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Target Resolution by Name, Coordinates X|Y, and Village ID (Wref)
// ------------------------------------------------------------------------------
echo "Test 5: Target Resolution (Name, Coordinates, Wref)... ";
// A. By Coordinate "88|80"
$resByCoord = BotAI::orderConquest($botName, "{$secCoords[0]}|{$secCoords[1]}", ['auto_prepare' => true]);
assertEqual('success', $resByCoord['status'], "Target by coordinate must succeed");
assertEqual($secWid, $resByCoord['details']['target_wid'], "Coordinate target must resolve to Human Colony");

// B. By Village Name "Human Colony"
$resByName = BotAI::orderConquest($botName, "Human Colony", ['auto_prepare' => true]);
assertEqual('success', $resByName['status'], "Target by name must succeed");
assertEqual($secWid, $resByName['details']['target_wid'], "Name target must resolve to Human Colony");

// C. By Village ID (wref)
$resByWref = BotAI::orderConquest($botName, (string)$secWid, ['auto_prepare' => true]);
assertEqual('success', $resByWref['status'], "Target by wref must succeed");
assertEqual($secWid, $resByWref['details']['target_wid'], "Wref target must resolve to Human Colony");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 6: Auto-Prepare Verification (Chief, Residence, CP, Catapults)
// ------------------------------------------------------------------------------
echo "Test 6: Auto-Prepare and Attack Mechanics (Attack Type 3 & Catapult Targets)... ";
// Clear bot's chief and catapults to verify auto-prepare
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 0, u18 = 0 WHERE vref = $botWid");

$resExec = BotAI::orderConquest($botName, 'Human Colony', [
    'escort_count' => 100,
    'catapult_count' => 25,
    'send_hero' => true,
    'auto_prepare' => true,
]);
assertEqual('success', $resExec['status'], "Conquest execution with auto-prepare must succeed");

// Verify Chief was researched
$tRes = (int)($database->query_return("SELECT t19 FROM " . TB_PREFIX . "tdata WHERE vref = $botWid")[0]['t19'] ?? 0);
assertEqual(1, $tRes, "Chief (u19) must be researched in tdata");

// Verify attacks table has attack_type = 3 (Normal Attack)
$lastAttack = $database->query_return("
    SELECT * FROM " . TB_PREFIX . "attacks
    WHERE vref = $botWid
    ORDER BY id DESC
    LIMIT 1
");
assertFalse(empty($lastAttack), "Attack record must exist");
$att = $lastAttack[0];
assertEqual(3, (int)$att['attack_type'], "Chief Conquest MUST be attack_type = 3 (Normal Attack)");
assertEqual(1, (int)$att['t9'], "Teuton Chief (slot t9) count must be 1");
assertEqual(25, (int)$att['ctar1'], "Catapult primary target must be 25 (Residence)");
assertEqual(26, (int)$att['ctar2'], "Catapult secondary target must be 26 (Palace)");

// Verify movement table has sort_type = 3
$lastMov = $database->query_return("
    SELECT * FROM " . TB_PREFIX . "movement
    WHERE `from` = $botWid AND `to` = $secWid
    ORDER BY moveid DESC
    LIMIT 1
");
assertFalse(empty($lastMov), "Movement record must exist");
$mov = $lastMov[0];
assertEqual(3, (int)$mov['sort_type'], "Movement sort_type must be 3 (Attack)");
assertTrue((int)$mov['endtime'] > (int)$mov['starttime'], "Endtime must be after starttime");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 7: Persistent Conquest Target Lock & Repeated Waves Until Conquest
// ------------------------------------------------------------------------------
echo "Test 7: Persistent Conquest Target Lock (Auto Repeat Waves)... ";
$activeOrd = BotAI::getConquestOrder($botWid);
assertFalse(empty($activeOrd), "Active conquest order must be registered");
assertEqual($secWid, (int)$activeOrd['target_wid'], "Active order target must match target_wid");

// Simulate Chief returning home: clear in-flight attacks & movements, place Chief back at home
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid");
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 1, u11 = 200 WHERE vref = $botWid");

// Disable night mode temporarily for test
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => false]);

$loopResult = BotAI::processConquest([
    'uid' => $botUid,
    'wref' => $botWid,
    'tribe' => 2,
]);
assertFalse(empty($loopResult), "processConquest must return action log");
assertTrue(strpos($loopResult, 'Dispatched next conquest wave') !== false, "Bot must automatically launch next wave for locked target");

// Simulate village officially conquered by setting owner to bot UID
$database->query("UPDATE " . TB_PREFIX . "vdata SET owner = $botUid WHERE wref = $secWid");
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid");

$conqDone = BotAI::processConquest([
    'uid' => $botUid,
    'wref' => $botWid,
    'tribe' => 2,
]);
$finalOrd = BotAI::getConquestOrder($botWid);
assertEqual(null, $finalOrd, "Conquest order must be cleared once village is conquered");

// Re-enable night sleep default
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => true]);
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// CLEANUP
// ------------------------------------------------------------------------------
echo "Cleaning up test records... ";
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid OR `to` = $secWid OR `to` = $capWid");
$database->query("DELETE FROM " . TB_PREFIX . "attacks WHERE vref = $botWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($botWid, $capWid, $secWid)");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($botWid, $capWid, $secWid)");
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref IN ($botWid, $capWid, $secWid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($botWid, $capWid, $secWid)");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($botUid, $humanUid)");
$database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id IN ($botWid, $capWid, $secWid)");
echo "CLEANUP COMPLETE" . PHP_EOL;

echo "=== All BotAdminOrderConquestTest tests PASSED successfully! ===" . PHP_EOL;
