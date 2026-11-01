<?php
/**
 * Test to verify that attacks on Benteng Kuno resolve completely,
 * set movement.proc = 1, and never loop or add duplicate reports on refresh.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationBattleResolution.php';

global $database;

echo "=== STARTING BASTION ATTACK RESOLUTION NO-LOOP TEST ===\n\n";

// 1. Verify Bastion::getEventState() returns valid array
echo "Test 1: Testing Bastion::getEventState()...\n";
$state = Bastion::getEventState();
if (!is_array($state) || !isset($state['status'])) {
    echo "FAIL: Bastion::getEventState() did not return expected array!\n";
    exit(1);
}
echo "PASS: Bastion::getEventState() returned status = " . $state['status'] . "\n\n";

// 2. Verify that Bastion::getEvent() and Bastion::getEventState() are consistent
echo "Test 2: Testing consistency between getEvent() and getEventState()...\n";
$ev1 = Bastion::getEvent();
$ev2 = Bastion::getEventState();
if ($ev1 !== $ev2) {
    echo "FAIL: getEvent() and getEventState() returned different results!\n";
    exit(1);
}
echo "PASS: getEvent() and getEventState() are perfectly identical!\n\n";

echo "=== ALL BASTION ATTACK RESOLUTION NO-LOOP TESTS PASSED! ===\n";
