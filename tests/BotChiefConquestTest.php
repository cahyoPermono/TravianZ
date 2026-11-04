<?php

#################################################################################
##  Filename       : tests/BotChiefConquestTest.php                            ##
##  Type           : Unit Test for Bot Chief Village Conquest AI               ##
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

echo "=== Running BotChiefConquestTest ===" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 1: Chief Unit Resolution Across All Tribes
// ------------------------------------------------------------------------------
echo "Test 1: Chief Unit Resolution across all tribes... ";
assertEqual(9, BotAI::getChiefUnit(1), "Romans chief unit must be 9 (Senator)");
assertEqual(19, BotAI::getChiefUnit(2), "Teutons chief unit must be 19 (Chief)");
assertEqual(29, BotAI::getChiefUnit(3), "Gauls chief unit must be 29 (Chieftain)");
assertEqual(0, BotAI::getChiefUnit(4), "Nature has no chief unit");
assertEqual(0, BotAI::getChiefUnit(5), "Natars has no chief unit for bot conquest");
assertEqual(59, BotAI::getChiefUnit(6), "Huns chief unit must be 59 (Logades / Tribal Chief)");
assertEqual(69, BotAI::getChiefUnit(7), "Egyptians chief unit must be 69 (Nomarch)");
assertEqual(79, BotAI::getChiefUnit(8), "Spartans chief unit must be 79 (Ephor)");
assertEqual(89, BotAI::getChiefUnit(9), "Vikings chief unit must be 89 (Jarl)");
assertEqual(99, BotAI::getChiefUnit(10), "Nusantara chief unit must be 99 (Patih)");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Bot vs Human Player Distinction (isBotUser)
// ------------------------------------------------------------------------------
echo "Test 2: Bot vs Human Player Distinction (isBotUser)... ";
global $database;

// Create temporary human player user
$testPlayerName = 'TestHuman_' . substr(md5((string)microtime(true)), 0, 6);
$humanEmail = 'human_' . uniqid() . '@example.com';
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, regtime)
    VALUES ('{$testPlayerName}', 'secret', '{$humanEmail}', 1, 2, 0, " . time() . ")
");
$humanUid = (int)mysqli_insert_id($database->dblink);

// Create temporary bot user
$testBotName = 'Bot_' . substr(md5((string)microtime(true)), 0, 6);
$botEmail = 'bot_' . uniqid() . '@bot.travianz';
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, desc1, regtime)
    VALUES ('{$testBotName}', 'secret', '{$botEmail}', 2, 2, 1, '[#BOT] Autonomous', " . time() . ")
");
$botUid = (int)mysqli_insert_id($database->dblink);

assertFalse(BotAI::isBotUser($humanUid), "isBotUser must return false for human player");
assertTrue(BotAI::isBotUser($botUid), "isBotUser must return true for bot account");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Conquest Configuration Persistence
// ------------------------------------------------------------------------------
echo "Test 3: Conquest Configuration Persistence... ";
$saveOk = BotAI::saveConfig([
    'BOT_AI_CONQUEST_ENABLED' => true,
    'BOT_AI_CONQUEST_MIN_ESCORT' => 75,
    'BOT_AI_CONQUEST_MAX_DISTANCE' => 200.0,
    'BOT_AI_CONQUEST_PRIORITIZE_PLAYERS' => true,
    'BOT_AI_NIGHT_SLEEP_ENABLED' => false,
]);
assertTrue($saveOk, "saveConfig should succeed");
BotAI::resetRuntimeConfig();

assertTrue(BotAI::getConfig('BOT_AI_CONQUEST_ENABLED'), "BOT_AI_CONQUEST_ENABLED should be true");
assertEqual(75, (int)BotAI::getConfig('BOT_AI_CONQUEST_MIN_ESCORT'), "BOT_AI_CONQUEST_MIN_ESCORT should be 75");
assertEqual(200.0, (float)BotAI::getConfig('BOT_AI_CONQUEST_MAX_DISTANCE'), "BOT_AI_CONQUEST_MAX_DISTANCE should be 200.0");
assertTrue(BotAI::getConfig('BOT_AI_CONQUEST_PRIORITIZE_PLAYERS'), "BOT_AI_CONQUEST_PRIORITIZE_PLAYERS should be true");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: Natural Academy Research Prerequisites & Queuing
// ------------------------------------------------------------------------------
echo "Test 4: Natural Academy Research Prerequisites & Queuing... ";

// Set up bot village for Teutons (Tribe 2, chief unit = 19)
$testBotWid = 444001;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "attacks WHERE vref = $testBotWid");

$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop)
    VALUES ($testBotWid, $botUid, 'Bot Main', 1, 350, 100000, 100000, 100000, 100000, 120000, 120000)
");
$database->query("
    INSERT INTO " . TB_PREFIX . "fdata (vref, f22, f22t, f39, f39t, f30, f30t)
    VALUES ($testBotWid, 15, 22, 3, 16, 10, 25)
"); // Academy 15, Rally Point 3 (both insufficient for Chief)
$database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($testBotWid)");
$database->query("INSERT INTO " . TB_PREFIX . "tdata (vref) VALUES ($testBotWid)");

$botData = [
    'uid' => $botUid,
    'id' => $botUid,
    'wref' => $testBotWid,
    'tribe' => 2,
    'pop' => 350,
    'wood' => 100000,
    'clay' => 100000,
    'iron' => 100000,
    'crop' => 100000,
];

// With Academy 15 and RP 3, Chief research must not start
$resEarly = BotAI::processConquest($botData);
assertEqual(null, $resEarly, "Chief research must not start when prerequisites not met");

// Upgrade Academy to 20 and Rally Point to 5 (Teuton req)
$database->query("UPDATE " . TB_PREFIX . "fdata SET f22 = 20, f39 = 5 WHERE vref = $testBotWid");
$resStart = BotAI::processConquest($botData);
assertTrue($resStart !== null && strpos($resStart, 'Started natural research for Chief') !== false, "Chief research should start naturally: $resStart");

// Verify research entry in database
$researchEntries = $database->query_return("SELECT * FROM " . TB_PREFIX . "research WHERE vref = $testBotWid AND tech = 't19'");
assertTrue(!empty($researchEntries), "Research table must contain t19 queue");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Natural Training Queuing in Residence / Palace
// ------------------------------------------------------------------------------
echo "Test 5: Natural Training Queuing in Residence/Palace... ";

// Complete research in tdata
$database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = $testBotWid");
$database->query("UPDATE " . TB_PREFIX . "tdata SET t19 = 1 WHERE vref = $testBotWid");

// Residence lvl 10 provides 1 slot
$resTrain = BotAI::processConquest($botData);
assertTrue($resTrain !== null && strpos($resTrain, 'Trained Chief (u19) in Residence/Palace') !== false, "Chief should be queued for training: $resTrain");

$trainEntries = $database->query_return("SELECT * FROM " . TB_PREFIX . "training WHERE vref = $testBotWid AND unit = 19");
assertTrue(!empty($trainEntries), "Training table must contain unit 19 queue");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 6: Target Selection (Non-Capital, Owner > 1 Village, Human Prioritized)
// ------------------------------------------------------------------------------
echo "Test 6: Target Selection and Prioritization... ";

// Complete Chief training (1 chief at home)
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $testBotWid");
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 1, u11 = 200, u13 = 100, u18 = 15 WHERE vref = $testBotWid");
$database->query("UPDATE " . TB_PREFIX . "users SET cp = 10000 WHERE id = $botUid"); // Ample CP

// Create targets:
// Target A: Capital of Human (capital = 1) -> CANNOT be conquered!
$widHumanCap = 444002;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $widHumanCap");
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($widHumanCap, $humanUid, 'Human Capital', 1, 500, 100)
");

// Target B: Non-Capital of Human (capital = 0, loyalty = 100) -> Eligible!
$widHumanNonCap = 444003;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $widHumanNonCap");
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($widHumanNonCap, $humanUid, 'Human Outpost', 0, 250, 100)
");

// Target C: Another bot non-capital village
$widOtherBot = 444004;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $widOtherBot");
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($widOtherBot, $botUid + 999, 'Bot Farm', 0, 150, 100)
");
// Give other bot 2 villages so owner_villages > 1
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES (444005, $botUid + 999, 'Bot Cap', 1, 300, 100)
");

// Ensure coordinates exist in wdata
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($testBotWid, 10, 10)");
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($widHumanCap, 12, 10)");
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($widHumanNonCap, 13, 10)");
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($widOtherBot, 11, 10)"); // Closest, but it is a bot!

// Dispatch conquest
$resAttack = BotAI::processConquest($botData);
assertTrue($resAttack !== null && strpos($resAttack, 'Ekspedisi Penaklukan Diberangkatkan') !== false, "Expedition should be dispatched: $resAttack");
assertTrue(strpos($resAttack, 'Pemain Manusia') !== false, "Must target a human player non-capital village! Output: $resAttack");
assertTrue(strpos($resAttack, 'Target: Padepokan/Istana') !== false, "Catapults must target Residence/Palace! Output: $resAttack");

// Verify attack entry in database
$attacks = $database->query_return("
    SELECT a.* FROM " . TB_PREFIX . "attacks a
    JOIN " . TB_PREFIX . "movement m ON m.ref = a.id
    WHERE m.from = $testBotWid
");
assertTrue(!empty($attacks), "Attack record must exist in DB");
assertEqual(1, (int)$attacks[0]['t9'], "t9 must be 1 (Chief)");
assertEqual(3, (int)$attacks[0]['attack_type'], "attack_type must be 3 (Normal Attack)");
assertEqual(25, (int)$attacks[0]['ctar1'], "ctar1 must target Residence (gid 25)");
assertEqual(26, (int)$attacks[0]['ctar2'], "ctar2 must target Palace (gid 26)");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 7: Lock-on Priority when Target Village Has Reduced Loyalty (< 100)
// ------------------------------------------------------------------------------
echo "Test 7: Lock-on Priority for Ongoing Conquest (Loyalty < 100)... ";

// Reset movements and reset chief at home
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $testBotWid");
$database->query("UPDATE " . TB_PREFIX . "units SET u19 = 1, u11 = 200, u13 = 100, u18 = 10 WHERE vref = $testBotWid");

// Lower loyalty of Human Outpost to 75%
$database->query("UPDATE " . TB_PREFIX . "vdata SET loyalty = 75 WHERE wref = $widHumanNonCap");

// Add a new juicy human village closer than Outpost
$widJuicy = 444006;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $widJuicy");
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($widJuicy, $humanUid, 'Human Juicy City', 0, 700, 100)
");
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($widJuicy, 11, 10)"); // Closer than Outpost

$resRelentless = BotAI::processConquest($botData);
assertTrue(strpos($resRelentless, 'Human Outpost') !== false, "Must lock on to Human Outpost because its loyalty is < 100%! Output: $resRelentless");
assertTrue(strpos($resRelentless, 'Loyalitas: 75%') !== false, "Output should show target loyalty 75%: $resRelentless");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 8: 200-Tile Radius & Random Selection Across All Player Non-Capitals
// ------------------------------------------------------------------------------
echo "Test 8: 200-Tile Radius & Non-Proximity Random Player Target Selection... ";

// Set default conquest radius to 200
$conquestMaxDist = (float)BotAI::getConfig('BOT_AI_CONQUEST_MAX_DISTANCE', 200.0);
assertEqual(200.0, $conquestMaxDist, "Conquest max distance must be 200 tiles");

// Reset Outpost loyalty back to 100% so all villages are fresh candidates
$database->query("UPDATE " . TB_PREFIX . "vdata SET loyalty = 100 WHERE wref = $widHumanNonCap");

// Add Far player non-capital village at (110, 10) -> distance 100 tiles
$widFar = 444007;
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $widFar");
$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, loyalty)
    VALUES ($widFar, $humanUid, 'Human Far Outpost', 0, 450, 100)
");
$database->query("REPLACE INTO " . TB_PREFIX . "wdata (id, x, y) VALUES ($widFar, 110, 10)");

// Perform multiple trial selections by resetting movements and checking targets picked
$hitClose = 0;
$hitFar = 0;
$trials = 40;

for ($i = 0; $i < $trials; $i++) {
    $database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $testBotWid");
    $database->query("UPDATE " . TB_PREFIX . "units SET u19 = 1, u11 = 200, u13 = 100, u18 = 10 WHERE vref = $testBotWid");

    $trialRes = BotAI::processConquest($botData);
    if ($trialRes && strpos($trialRes, 'Human Far Outpost') !== false) {
        $hitFar++;
    } elseif ($trialRes && (strpos($trialRes, 'Human Juicy City') !== false || strpos($trialRes, 'Human Outpost') !== false)) {
        $hitClose++;
    }
}

assertTrue($hitFar > 0, "Far player village at distance 100 must be chosen in random selection (Hit far: $hitFar/$trials)");
assertTrue($hitClose > 0, "Close player villages must also be chosen in random selection (Hit close: $hitClose/$trials)");
echo "PASSED (Far Hits: $hitFar, Close Hits: $hitClose out of $trials trials)" . PHP_EOL;

// ------------------------------------------------------------------------------
// CLEANUP
// ------------------------------------------------------------------------------
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($testBotWid, $widHumanCap, $widHumanNonCap, $widOtherBot, 444005, $widJuicy, $widFar)");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $testBotWid");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($humanUid, $botUid)");
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => true, 'BOT_AI_CONQUEST_MAX_DISTANCE' => 200.0]);
BotAI::resetRuntimeConfig();

echo "=== All BotChiefConquestTest Passed Successfully! ===" . PHP_EOL;
