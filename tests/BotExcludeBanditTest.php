<?php

#################################################################################
##  Filename       : BotExcludeBanditTest.php                                  ##
##  Type           : Unit Test for Bot Exclusion from Bandit Camps & PvE Boss  ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BanditCamp.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

function assertTrue($condition, $message) {
    if (!$condition) {
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

echo "=== Running Bot Exclusion from Bandit Camps Unit Tests ===\n";

global $database;

// 1. Ensure Bandit User and Table exist
$banditUser = BanditCamp::getBanditUser();
assertTrue(!empty($banditUser['id']), "Bandit user must exist");
BanditCamp::ensureTable();

// 2. Fetch or spawn an active Bandit Camp for testing
$activeCamps = BanditCamp::getActiveCamps();
if (empty($activeCamps)) {
    $spawned = BanditCamp::spawnCamp(BanditCamp::TIER_OUTPOST);
    assertTrue(!empty($spawned), "Spawned test bandit camp");
    $activeCamps = BanditCamp::getActiveCamps();
}
assertTrue(!empty($activeCamps), "Active bandit camp exists");
$testCamp = $activeCamps[0];
$testCampWref = (int)$testCamp['wref'];
$testCampId = (int)$testCamp['id'];

echo "Test Camp ID: $testCampId, Wref: $testCampWref\n";

// 3. Test BanditCamp::isBanditCamp helper
echo "Test 1: Verify BanditCamp::isBanditCamp identification...\n";
assertTrue(BanditCamp::isBanditCamp($testCampWref), "testCampWref must be identified as Bandit Camp");
assertTrue(!BanditCamp::isBanditCamp(0), "Wref 0 must not be a bandit camp");
assertTrue(!BanditCamp::isBanditCamp(99999999), "Non-existent wref must not be a bandit camp");

// 4. Test BotAI::isBanditTarget helper
echo "Test 2: Verify BotAI::isBanditTarget identification...\n";
assertTrue(BotAI::isBanditTarget($testCampWref), "testCampWref must be detected by BotAI as bandit target");

// 5. Test bot vs human identification
echo "Test 3: Verify Bot vs Human detection...\n";
$botUsers = $database->query_return("SELECT id, username FROM " . TB_PREFIX . "users WHERE is_bot = 1 OR email LIKE '%@bot.travianz%' LIMIT 1");
assertTrue(!empty($botUsers[0]), "Must have at least one bot user in test DB");
$botUid = (int)$botUsers[0]['id'];
$botName = $botUsers[0]['username'];
assertTrue(BotAI::isBotUser($botUid), "BotAI::isBotUser must return true for bot $botName ($botUid)");

$humanUsers = $database->query_return("SELECT id, username FROM " . TB_PREFIX . "users WHERE id > 5 AND is_bot = 0 AND email NOT LIKE '%@bot.travianz%' AND username != 'Gembong Bandit' LIMIT 1");
if (!empty($humanUsers[0])) {
    $humanUid = (int)$humanUsers[0]['id'];
    assertTrue(!BotAI::isBotUser($humanUid), "BotAI::isBotUser must return false for human user {$humanUsers[0]['username']}");
}

// 6. Test BanditCamp::logAttackContribution rejects bot
echo "Test 4: Verify BanditCamp::logAttackContribution rejects bots...\n";
$initialCount = (int)($database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "bandit_camp_attacks WHERE camp_id = $testCampId AND uid = $botUid")[0]['c'] ?? 0);
BanditCamp::logAttackContribution($testCampId, $botUid, 9999);
$newCount = (int)($database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "bandit_camp_attacks WHERE camp_id = $testCampId AND uid = $botUid")[0]['c'] ?? 0);
assertEqual($initialCount, $newCount, "BanditCamp::logAttackContribution must NOT record attacks from bots");

// 7. Test BanditCamp::onBattleCompleted rejects bot
echo "Test 5: Verify BanditCamp::onBattleCompleted rejects bots...\n";
$campStatusBefore = (int)($database->query_return("SELECT status FROM " . TB_PREFIX . "bandit_camps WHERE id = $testCampId")[0]['status'] ?? 0);
$clearResult = BanditCamp::onBattleCompleted($testCampWref, $botUid, 0, 5000);
assertEqual(null, $clearResult, "BanditCamp::onBattleCompleted must return null when attacker is a bot");
$campStatusAfter = (int)($database->query_return("SELECT status FROM " . TB_PREFIX . "bandit_camps WHERE id = $testCampId")[0]['status'] ?? 0);
assertEqual($campStatusBefore, $campStatusAfter, "Camp status must remain untouched when attacked by bot");

// 8. Test BotAI::orderConquest rejects bandit camp target
echo "Test 6: Verify BotAI::orderConquest blocks ordering conquest on Bandit Camp...\n";
$orderRes = BotAI::orderConquest($botUid, (string)$testCampWref);
assertEqual('error', $orderRes['status'] ?? '', "orderConquest must return error when target is a bandit camp");
assertTrue(strpos($orderRes['message'] ?? '', 'Sarang Bandit') !== false, "Error message must state target is Bandit Camp/World Boss");

// 9. Test BotAI::orderAttack rejects bandit camp target
echo "Test 7: Verify BotAI::orderAttack blocks targeting Bandit Camp...\n";
$gangRes = BotAI::orderAttack($botUid, (string)$testCampWref, 3);
assertEqual('error', $gangRes['status'] ?? '', "orderAttack must return error when target is a bandit camp");
assertTrue(strpos($gangRes['message'] ?? '', 'Sarang Bandit') !== false, "Error message must state target is Bandit Camp/World Boss");

echo "\n>>> ALL 7 BOT EXCLUSION FROM BANDIT CAMPS TESTS PASSED! <<<\n";
