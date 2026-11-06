<?php
/**
 * Test Suite: Bot Anti-Dogpiling Strict Enforcement
 */

if (!defined('SQL_SERVER')) {
    // If running on host directly, set localhost:3308 fallback if needed
    @include_once __DIR__ . '/../GameEngine/config.php';
}
@include_once __DIR__ . '/../GameEngine/Database.php';
@include_once __DIR__ . '/../GameEngine/BotAI.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        echo "[FAILED] $message: Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assertTrue($condition, $message) {
    if (!$condition) {
        echo "[FAILED] $message\n";
        exit(1);
    }
}

echo "=== Testing BotAI getActiveIncomingBotAttacks Helper ===" . PHP_EOL;

assertTrue(method_exists('BotAI', 'getActiveIncomingBotAttacks'), "BotAI::getActiveIncomingBotAttacks method must exist");

$gangAttackMax = (int)BotAI::getConfig('BOT_AI_GANG_ATTACK_MAX', 1);
assertEqual(1, $gangAttackMax, "Default/configured gang attack max must be 1");

// Test negative or zero wref handling
$zeroCheck = BotAI::getActiveIncomingBotAttacks(0);
assertEqual(0, $zeroCheck, "Zero wref must return 0 incoming attacks");

echo "PASSED" . PHP_EOL;

echo "=== All Anti-Dogpiling Tests Passed Successfully! ===" . PHP_EOL;
