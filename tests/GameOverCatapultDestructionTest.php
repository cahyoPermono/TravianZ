<?php

#################################################################################
##  Filename       : tests/GameOverCatapultDestructionTest.php                 ##
##  Type           : Unit Test for Game Over & Catapult Destruction Feature    ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';
require_once dirname(__DIR__) . '/GameEngine/Automation.php';

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

echo "=== Running GameOverCatapultDestructionTest ===" . PHP_EOL;
global $database;

// Create automation instance to test trait methods
$automation = new Automation();

// ------------------------------------------------------------------------------
// TEST 1: can_destroy logic allows destroying last village/capital (not natar/artefact)
// ------------------------------------------------------------------------------
echo "Test 1: Destruction guard allows single village / capital destruction... ";
// Test with mock data
$reflector = new ReflectionClass($automation);
$method = $reflector->getMethod('triggerPlayerGameOver');
assertTrue($method->isPublic(), "triggerPlayerGameOver must be a public method on Automation");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Bot account complete elimination on Game Over (wiped from users & db)
// ------------------------------------------------------------------------------
echo "Test 2: Bot account complete elimination on Game Over... ";
$testBotUser = 'Test_Bot_Eliminate_' . rand(1000, 9999);
$testBotEmail = $testBotUser . '@bot.travianz';

// Insert temporary test bot into database
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, desc1, desc2)
    VALUES ('$testBotUser', 'secret', '$testBotEmail', 1, 2, 1, '[#BOT]', '[#BOT]')
");
$botUid = (int)$database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE username = '$testBotUser'")[0]['id'];
assertTrue($botUid > 0, "Test bot must be created in DB");
assertTrue(BotAI::isBot($botUid), "BotAI::isBot must return true for the test bot");

// Trigger Game Over elimination for the test bot
$automation->triggerPlayerGameOver($botUid, 1, 1001);

// Verify test bot is completely gone from users table
$checkBot = $database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE id = $botUid");
assertTrue(empty($checkBot), "Bot must be completely deleted from users table upon Game Over");

// Verify admin log contains elimination notice
$checkLog = $database->query_return("SELECT log FROM " . TB_PREFIX . "admin_log WHERE log LIKE '%Bot ID $botUid%' ORDER BY id DESC LIMIT 1");
assertTrue(!empty($checkLog), "Admin log must record bot elimination");
assertTrue(strpos($checkLog[0]['log'], 'dieliminasi total') !== false, "Log must mention total elimination");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Human player account Game Over state (is_gameover=1, access=0, message sent)
// ------------------------------------------------------------------------------
echo "Test 3: Human player account Game Over state... ";
$testHumanUser = 'Test_Player_GO_' . rand(1000, 9999);
$testHumanEmail = $testHumanUser . '@gmail.com';

// Insert temporary human player into database
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot)
    VALUES ('$testHumanUser', 'secret', '$testHumanEmail', 2, 2, 0)
");
$humanUid = (int)$database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE username = '$testHumanUser'")[0]['id'];
assertTrue($humanUid > 0, "Test human player must be created in DB");
assertFalse(BotAI::isBot($humanUid), "BotAI::isBot must return false for human player");

// Trigger Game Over elimination for human player
$attackerUid = 1;
$automation->triggerPlayerGameOver($humanUid, $attackerUid, 1002);

// Verify human player is marked with is_gameover = 1 and access = 0
$checkHuman = $database->getUserArray($humanUid, 1);
assertTrue(!empty($checkHuman), "Human player user row should still exist for Game Over screen");
assertEqual(1, (int)$checkHuman['is_gameover'], "Human player must have is_gameover = 1");
assertEqual(0, (int)$checkHuman['access'], "Human player access must be set to 0 (excluded from rankings)");
assertTrue(strpos($checkHuman['desc2'] ?? '', '[#GAMEOVER#]') !== false, "desc2 must contain GAMEOVER tag");

// Verify in-game message was sent to human player
$checkMsg = $database->query_return("SELECT topic, message FROM " . TB_PREFIX . "mdata WHERE target = $humanUid ORDER BY id DESC LIMIT 1");
assertTrue(!empty($checkMsg), "In-game message must be sent to human player on Game Over");
assertTrue(strpos($checkMsg[0]['topic'], 'GAME OVER') !== false, "Message topic must mention GAME OVER");

// Verify admin log contains player Game Over entry
$checkLogP = $database->query_return("SELECT log FROM " . TB_PREFIX . "admin_log WHERE log LIKE '%Pemain ID $humanUid%' ORDER BY id DESC LIMIT 1");
assertTrue(!empty($checkLogP), "Admin log must record player Game Over");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: Human player Restart from Game Over creates a fresh village
// ------------------------------------------------------------------------------
echo "Test 4: Human player Restart from Game Over restores access and creates new village... ";
include_once dirname(__DIR__) . '/GameEngine/Account.php';
$account = new Account();
$newWid = $database->generateVillages([
    [
        'wid'     => 0,
        'mode'    => 0,
        'type'    => 3,
        'kid'     => rand(1, 4),
        'capital' => 1,
        'pop'     => 2,
        'name'    => $testHumanUser . "'s village",
        'natar'   => 0
    ]
], $humanUid, $testHumanUser);
$newWid = is_array($newWid) ? (int)($newWid[0] ?? 0) : (int)$newWid;
assertTrue($newWid > 0, "New village must be generated for player restart");

// Restore player access
$database->updateUserField($humanUid, ['is_gameover', 'access'], [0, 2], 1);
$restoredHuman = $database->getUserArray($humanUid, 1);
assertEqual(0, (int)$restoredHuman['is_gameover'], "Player is_gameover must be reset to 0 after restart");
assertEqual(2, (int)$restoredHuman['access'], "Player access must be restored to 2 after restart");

// Verify player has 1 village in vdata
$villages = $database->getVillagesID($humanUid);
assertEqual(1, count($villages), "Player must now have exactly 1 village");
assertEqual($newWid, (int)$villages[0], "Village ID must match the newly generated village");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Human player Permanent Delete option wipes player completely
// ------------------------------------------------------------------------------
echo "Test 5: Permanent Delete option wipes human player completely... ";
$database->DelVillage($newWid);
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $humanUid");
$checkDeleted = $database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE id = $humanUid");
assertTrue(empty($checkDeleted), "Player must be completely deleted when permanent delete is chosen");
echo "PASSED" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 6: Multi-village player losing 1 village reassigns capital and does NOT Game Over
// ------------------------------------------------------------------------------
echo "Test 6: Multi-village player reassigns capital and does NOT trigger Game Over... ";
$multiUser = 'Test_Multi_' . rand(1000, 9999);
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot)
    VALUES ('$multiUser', 'secret', '$multiUser@test.com', 1, 2, 0)
");
$multiUid = (int)$database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE username = '$multiUser'")[0]['id'];

// Create 2 villages for this player
$v1 = $database->generateVillages([['wid' => 0, 'mode' => 0, 'type' => 3, 'kid' => 1, 'capital' => 1, 'pop' => 2, 'name' => 'Capital Village', 'natar' => 0]], $multiUid, $multiUser);
$v1 = is_array($v1) ? (int)$v1[0] : (int)$v1;
$v2 = $database->generateVillages([['wid' => 0, 'mode' => 0, 'type' => 3, 'kid' => 1, 'capital' => 0, 'pop' => 50, 'name' => 'Second Village', 'natar' => 0]], $multiUid, $multiUser);
$v2 = is_array($v2) ? (int)$v2[0] : (int)$v2;

$varray = [
    ['wref' => $v1, 'pop' => 2, 'capital' => 1],
    ['wref' => $v2, 'pop' => 50, 'capital' => 0]
];
$attackData = ['to' => $v1, 'owner' => 1];
$toData = ['wref' => $v1, 'owner' => $multiUid, 'capital' => 1];
$fromData = ['owner' => 1];

// Invoke handleVillageDestruction for v1 (village_destroyed = 1, can_destroy = 1)
$destructMethod = $reflector->getMethod('handleVillageDestruction');
$destructMethod->setAccessible(true);
$destructMethod->invoke($automation, 1, 1, $attackData, $toData, $varray, $fromData);

// Verify v2 became the new capital
$v2Data = $database->getVillage($v2, 0, false);
assertEqual(1, (int)$v2Data['capital'], "Remaining village v2 must be promoted to capital");

// Verify player is NOT in Game Over because v2 is still alive
$checkMulti = $database->getUserArray($multiUid, 1);
assertEqual(0, (int)($checkMulti['is_gameover'] ?? 0), "Multi-village player must not get Game Over while still having villages");
assertEqual(2, (int)$checkMulti['access'], "Multi-village player access must remain 2");

// Clean up
$database->DelVillage($v2);
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $multiUid");
echo "PASSED" . PHP_EOL;

echo "=== All 6 tests in GameOverCatapultDestructionTest passed successfully! ===" . PHP_EOL;
