<?php

#################################################################################
##  Filename       : tests/BotConfigPersistenceTest.php                        ##
##  Type           : Unit Test for Dual-Layer Bot Config Persistence           ##
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

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running BotConfigPersistenceTest ===" . PHP_EOL;

// 1. Save original config
$savedConfig = BotAI::getConfig();

// 2. Save custom settings (e.g. 3 villages, 150% troop factor)
echo "Test 1: Saving custom settings to dual-layer persistence... ";
$saveOk = BotAI::saveConfig([
    'BOT_AI_MAX_VILLAGES' => 3,
    'BOT_AI_TROOP_POP_FACTOR' => 1.5,
]);
assertTrue($saveOk, "saveConfig must succeed");
echo "PASS" . PHP_EOL;

// 3. Verify in database
echo "Test 2: Verifying persistence in Database table... ";
global $database;
$dbPrefix = defined('TB_PREFIX') ? TB_PREFIX : 's1_';
$dbRow = $database->query_return("SELECT `v` FROM `{$dbPrefix}bot_config` WHERE `k` = 'BOT_AI_MAX_VILLAGES'");
assertTrue(!empty($dbRow), "Database table must contain BOT_AI_MAX_VILLAGES");
assertEqual(3, (int)json_decode($dbRow[0]['v']), "Database value for BOT_AI_MAX_VILLAGES must be 3");

$dbRowFactor = $database->query_return("SELECT `v` FROM `{$dbPrefix}bot_config` WHERE `k` = 'BOT_AI_TROOP_POP_FACTOR'");
assertTrue(!empty($dbRowFactor), "Database table must contain BOT_AI_TROOP_POP_FACTOR");
assertEqual(1.5, (float)json_decode($dbRowFactor[0]['v']), "Database value for BOT_AI_TROOP_POP_FACTOR must be 1.5");
echo "PASS" . PHP_EOL;

// 4. Test Zip Overwrite Simulation
echo "Test 3: Simulating zip extraction overwrite on bot_config.json... ";
$configFile = dirname(__DIR__) . '/GameEngine/Prevention/bot_config.json';
// Simulate an accidental zip overwrite with default values (2 villages, 1.25 troop factor)
file_put_contents($configFile, json_encode([
    'BOT_AI_MAX_VILLAGES' => 2,
    'BOT_AI_TROOP_POP_FACTOR' => 1.25,
], JSON_PRETTY_PRINT));

// Clear memory cache as if a new HTTP request arrived
BotAI::resetRuntimeConfig();

// Database priority must protect the user's real saved settings from being reverted!
$loadedVil = BotAI::getConfig('BOT_AI_MAX_VILLAGES');
$loadedFactor = BotAI::getConfig('BOT_AI_TROOP_POP_FACTOR');

assertEqual(3, (int)$loadedVil, "getConfig must return 3 from Database even if bot_config.json was overwritten");
assertEqual(1.5, (float)$loadedFactor, "getConfig must return 1.5 from Database even if bot_config.json was overwritten");
echo "PASS" . PHP_EOL;

// 5. Cleanup / Restore original config
echo "Test 4: Restoring original settings... ";
BotAI::saveConfig($savedConfig);
echo "PASS" . PHP_EOL;

echo "=== ALL BotConfigPersistenceTest TESTS PASSED! ===" . PHP_EOL;
