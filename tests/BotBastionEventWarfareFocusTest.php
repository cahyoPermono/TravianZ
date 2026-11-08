<?php
/**
 * Test for Bot Behavior during Ancient Bastion (Benteng Kuno) Event:
 * 1. Bots must NEVER attack, raid, scout, or conquer players when Benteng Kuno is active.
 * 2. Bots must mobilize, attack, and attempt to capture (ngerebut) Benteng Kuno when held by enemies/Penjaga Kuno.
 * 3. Bots must send reinforcements to defend (mempertahankan) Benteng Kuno when held by the bot or bot's alliance.
 * 4. Solo bots (without alliance) participate naturally alongside alliance bots.
 */

define('TESTING_MODE', true);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BotAI.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        fwrite(STDERR, "  Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "  Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

echo "=== Running BotBastionEventWarfareFocusTest ===" . PHP_EOL;
global $database;

// Backup original bastion_event record
$origEventRes = $database->query_return("SELECT * FROM " . TB_PREFIX . "bastion_event WHERE id = 1 LIMIT 1");
$origEvent = !empty($origEventRes[0]) ? $origEventRes[0] : null;

$testSuffix = substr(md5((string)microtime(true)), 0, 6);

// 1. Create Test Bot (Solo Bot - Teuton, tribe 2)
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime, is_bot, alliance)
    VALUES ('BotBastion_{$testSuffix}', MD5('pass'), 'bot_{$testSuffix}@bot.travianz', 2, 2, " . time() . ", 1, 0)
");
$botUid = (int)mysqli_insert_id($database->dblink);

// 2. Create Test Bot Village
$coords = [25, 25];
$wRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$coords[0]} AND y = {$coords[1]} LIMIT 1");
if (empty($wRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$coords[0]}, {$coords[1]}, 1, 'f3')");
    $botWid = (int)mysqli_insert_id($database->dblink);
} else {
    $botWid = (int)$wRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $botWid");
}

$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, tribe, wood, clay, iron, crop, maxstore, maxcrop)
    VALUES ($botWid, $botUid, 'Bot Stronghold', 1, 350, 2, 80000, 80000, 80000, 80000, 80000, 80000)
    ON DUPLICATE KEY UPDATE owner = $botUid, pop = 350, tribe = 2, wood = 80000, clay = 80000, iron = 80000, crop = 80000
");

// Insert units for the bot: 500 Clubswingers (u11), 200 Spearmen (u12), 100 Axemen (u13), 50 Teutonic Knights (u15), 10 Rams (u17), 10 Catapults (u18)
$database->query("
    INSERT INTO " . TB_PREFIX . "units (vref, u11, u12, u13, u15, u17, u18, hero)
    VALUES ($botWid, 500, 200, 100, 50, 10, 10, 0)
    ON DUPLICATE KEY UPDATE u11 = 500, u12 = 200, u13 = 100, u15 = 50, u17 = 10, u18 = 10, hero = 0
");

$botData = [
    'uid'      => $botUid,
    'id'       => $botUid,
    'username' => 'BotBastion_' . $testSuffix,
    'wref'     => $botWid,
    'tribe'    => 2,
    'pop'      => 350,
    'alliance' => 0,
];

// --- TEST 1: When Event is INACTIVE ---
$database->query("
    INSERT INTO " . TB_PREFIX . "bastion_event (id, status, holder_alliance, holder_uid)
    VALUES (1, 0, 0, 0)
    ON DUPLICATE KEY UPDATE status = 0, holder_alliance = 0, holder_uid = 0
");

assertEqual(false, Bastion::isEventActive(), "Test 1A: Bastion event is inactive");
assertEqual(false, BotAI::isBastionContestingBot($botData), "Test 1B: Bot is not contesting when event is inactive");

// --- TEST 2: When Event is ACTIVE -> Attacks on Players MUST BE 100% BLOCKED ---
$database->query("
    UPDATE " . TB_PREFIX . "bastion_event
    SET status = 1, event_start_time = " . (time() - 3600) . ", event_end_time = " . (time() + 36000) . ",
        holder_alliance = 0, holder_uid = 91016
    WHERE id = 1
");

assertEqual(true, Bastion::isEventActive(), "Test 2A: Bastion event is active");

// Ensure time is during day so night blackout does not mask the check
$nightSetting = BotAI::getConfig('BOT_AI_NIGHT_SLEEP_ENABLED', true);
BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => false]);

$attackResult = BotAI::processAttack($botData);
assertTrue(is_string($attackResult) && strpos($attackResult, 'Benteng Kuno sedang aktif') !== false,
    "Test 2B: processAttack is suppressed and paused during Benteng Kuno event");

$conquestResult = BotAI::processConquest($botData);
assertTrue(is_string($conquestResult) && strpos($conquestResult, 'Benteng Kuno sedang aktif') !== false,
    "Test 2C: processConquest is suppressed and paused during Benteng Kuno event");

$scoutResult = BotAI::processScout($botData);
assertTrue(is_string($scoutResult) && strpos($scoutResult, 'Benteng Kuno sedang aktif') !== false,
    "Test 2D: processScout is suppressed and paused during Benteng Kuno event");

// --- TEST 3: Capturing (Ngerebut) Benteng Kuno by Solo Bot ---
// Bastion held by Penjaga Kuno (UID 91016). Bot is not friendly with holder.
assertTrue(BotAI::isBastionContestingBot($botData), "Test 3A: Solo bot is identified as a Bastion contender");

// Clean any previous movement from bot to Bastion
$bastionWref = Bastion::BASTION_WREF;
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid AND `to` = $bastionWref");

// Execute Bastion warfare
$warfareResult = BotAI::processBastionWarfare($botData);
assertTrue(is_string($warfareResult) && strpos($warfareResult, 'Benteng Kuno') !== false,
    "Test 3B: processBastionWarfare dispatches attack expedition to (0|0) - Result: " . $warfareResult);

// Verify attack movement row created in database
$movements = $database->query_return("
    SELECT m.*, a.attack_type, (a.t1 + a.t2 + a.t3 + a.t4 + a.t5 + a.t6 + a.t7 + a.t8 + a.t9 + a.t10) as total_troops
    FROM " . TB_PREFIX . "movement m
    JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
    WHERE m.`from` = $botWid AND m.`to` = $bastionWref AND m.sort_type = 3
");

assertTrue(!empty($movements), "Test 3C: Attack movement record successfully inserted towards Bastion (0|0)");
assertEqual(3, (int)$movements[0]['attack_type'], "Test 3D: Attack type is Normal Attack (type 3), required to conquer Benteng Kuno");
assertTrue((int)$movements[0]['total_troops'] > 50, "Test 3E: Dispatched sizable expeditionary strike force (" . $movements[0]['total_troops'] . " troops)");

// --- TEST 4: Defending (Mempertahankan) Benteng Kuno when Held by Bot ---
// Set Benteng Kuno holder to this bot UID
$database->query("
    UPDATE " . TB_PREFIX . "bastion_event
    SET holder_alliance = 0, holder_uid = $botUid, hold_start_time = " . (time() - 600) . "
    WHERE id = 1
");

// Now bot is friendly with holder (it holds the fortress!)
assertEqual(false, BotAI::isBastionContestingBot($botData), "Test 4A: Bot holding Bastion is no longer contesting");
assertTrue(Bastion::isFriendlyWithHolder($botUid, 0), "Test 4B: Bot is friendly with current holder (self)");

// Clear previous movements
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid AND `to` = $bastionWref");

// Provide more defensive troops (Spearmen u12)
$database->query("UPDATE " . TB_PREFIX . "units SET u12 = 300 WHERE vref = $botWid");

$defendResult = BotAI::processBastionWarfare($botData);
assertTrue(is_string($defendResult) && strpos($defendResult, 'Bertahan') !== false,
    "Test 4C: processBastionWarfare dispatches reinforcement to defend (0|0) - Result: " . $defendResult);

$defMovements = $database->query_return("
    SELECT m.*, a.attack_type, (a.t1 + a.t2 + a.t3 + a.t4 + a.t5 + a.t6 + a.t7 + a.t8 + a.t9 + a.t10) as total_troops
    FROM " . TB_PREFIX . "movement m
    JOIN " . TB_PREFIX . "attacks a ON m.ref = a.id
    WHERE m.`from` = $botWid AND m.`to` = $bastionWref AND m.sort_type = 3
");

assertTrue(!empty($defMovements), "Test 4D: Reinforcement movement record successfully inserted towards Bastion");
assertEqual(2, (int)$defMovements[0]['attack_type'], "Test 4E: Reinforcement type is 2 (Bala Bantuan / Reinforcement)");

// --- CLEANUP ---
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWid AND `to` = $bastionWref");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $botUid");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $botWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $botWid");

// Restore original bastion_event state
if ($origEvent) {
    $database->query("
        UPDATE " . TB_PREFIX . "bastion_event
        SET status = " . (int)$origEvent['status'] . ",
            holder_alliance = " . (int)$origEvent['holder_alliance'] . ",
            holder_uid = " . (int)$origEvent['holder_uid'] . ",
            hold_start_time = " . (int)$origEvent['hold_start_time'] . ",
            event_start_time = " . (int)$origEvent['event_start_time'] . ",
            event_end_time = " . (int)$origEvent['event_end_time'] . "
        WHERE id = 1
    ");
}

if ($nightSetting !== null) {
    BotAI::saveConfig(['BOT_AI_NIGHT_SLEEP_ENABLED' => $nightSetting]);
}

echo "=== All BotBastionEventWarfareFocusTest checks PASSED successfully! ===" . PHP_EOL;
