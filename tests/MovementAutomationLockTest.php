<?php
/**
 * Test: Movement Automation and Lock Mechanism Test
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';

echo "=== Running MovementAutomationLockTest ===\n";

// 1. Test Lock Path and flock in Automation
echo "Test 1: Verifying Automation flock behavior...\n";
$lockName = defined('AUTOMATION_LOCK_FILE_NAME') ? AUTOMATION_LOCK_FILE_NAME : 'automation.lck';
$lockPath = (empty($lockName) || $lockName[0] === '/' || (strlen($lockName) >= 2 && $lockName[1] === ':'))
    ? $lockName
    : dirname(__DIR__) . '/' . ltrim($lockName, '/\\');

$handle = @fopen($lockPath, 'c');
assert($handle !== false, "Failed to open lock file at $lockPath");
$locked = @flock($handle, LOCK_EX | LOCK_NB);
assert($locked, "Failed to acquire exclusive non-blocking lock");
@flock($handle, LOCK_UN);
@fclose($handle);
echo "PASS: Automation lock path and flock functioning correctly.\n";

// 2. Test Village.php fallback mechanism
echo "Test 2: Verifying Village.php Automation execution...\n";
if (!defined('AUTOMATION_MANUAL_RUN')) {
    define('AUTOMATION_MANUAL_RUN', true);
}
require_once __DIR__ . '/../GameEngine/Automation.php';
assert(class_exists('Automation'), "Class Automation should exist");
$auto = new Automation();
echo "PASS: Automation instantiated successfully via class_exists.\n";

// 3. Test endtime <= time condition in database queries
echo "Test 3: Testing movement completion condition (endtime <= time)...\n";
$time = time();
$q = "SELECT moveid FROM " . TB_PREFIX . "movement WHERE proc = 0 AND endtime <= $time";
$res = mysqli_query($database->dblink, $q);
assert($res !== false, "Query for completed movements failed: " . mysqli_error($database->dblink));
echo "PASS: Movement query with endtime <= time executed successfully.\n";

echo "=== All MovementAutomationLockTest Tests Passed! ===\n";
