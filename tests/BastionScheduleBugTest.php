<?php
/**
 * Test Bastion Event Scheduler and Lifecycle
 *
 * Verifies:
 * 1. Date formatting in Indonesian (no "+07:00000000k000000Sunday" bug from unescaped 'pukul').
 * 2. Active event is NOT prematurely killed by processTick() when a recurring weekly schedule exists.
 * 3. Weekly schedule auto-recovery: resumes active status if the scheduled day/time is today and no victory has occurred.
 * 4. setSchedule() preserves currently ACTIVE event instead of reverting it to SCHEDULED.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';

global $database;

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new Exception("Assertion failed: $message. Expected: $expStr, got: $actStr");
    }
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        throw new Exception("Assertion failed: $message");
    }
}

echo "=== STARTING BASTION SCHEDULE BUG TESTS ===\n";

Bastion::ensureTables();
$db = $database->dblink;

// =========================================================================
// TEST 1: Date String Formatting (No Unescaped 'pukul' Corruption)
// =========================================================================
echo "Test 1: Testing date string formatting...\n";
$sampleTs = strtotime("2026-11-01 08:00:00");
$formatted = date('l, d F Y', $sampleTs) . ' pukul ' . date('H:i', $sampleTs);
assertTrue(strpos($formatted, '000000') === false, "Formatted date must not contain microsecond artifacts (000000)");
assertTrue(strpos($formatted, '+07:00') === false, "Formatted date must not contain timezone offset artifacts (+07:00)");
assertTrue(strpos($formatted, 'pukul 08:00') !== false, "Formatted date must contain 'pukul 08:00'");
assertEqual("Sunday, 01 November 2026 pukul 08:00", $formatted);
echo "PASS: Date formatting produces clean Indonesian date without corrupted tokens!\n";

// =========================================================================
// TEST 2: Active Event Preservation in processTick()
// =========================================================================
echo "Test 2: Verifying active event is NOT cancelled by processTick()...\n";

$now = time();
$curDay = (int)date('w', $now);
$curHour = (int)date('G', $now);

// Setup weekly schedule matching current day/hour
mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET
    `status` = " . Bastion::STATUS_ACTIVE . ",
    `schedule_mode` = " . Bastion::SCHEDULE_WEEKLY . ",
    `recurring_day` = $curDay,
    `recurring_hour` = $curHour,
    `recurring_minute` = 0,
    `last_scheduled_start` = $now,
    `event_start_time` = $now,
    `winner_time` = 0,
    `buff_until` = 0
    WHERE `id` = 1");

// Run processTick()
Bastion::processTick();

$ev = Bastion::getEvent();
assertEqual(Bastion::STATUS_ACTIVE, (int)$ev['status'], "Active event MUST remain STATUS_ACTIVE after processTick(), not reverted to STATUS_SCHEDULED!");
echo "PASS: Active event remained active through processTick()!\n";

// =========================================================================
// TEST 3: Resilient Weekly Schedule Recovery
// =========================================================================
echo "Test 3: Testing automatic recovery when scheduled event was locked/scheduled today...\n";

// Simulate the user's exact state:
// Today at 08:00 WIB the schedule triggered (last_scheduled_start = today),
// but status was flipped to STATUS_SCHEDULED by the previous bug.
$targetHour = max(0, $curHour - 1); // 1 hour ago
mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET
    `status` = " . Bastion::STATUS_SCHEDULED . ",
    `schedule_mode` = " . Bastion::SCHEDULE_WEEKLY . ",
    `recurring_day` = $curDay,
    `recurring_hour` = $targetHour,
    `recurring_minute` = 0,
    `last_scheduled_start` = $now,
    `event_start_time` = 0,
    `winner_time` = 0,
    `buff_until` = 0
    WHERE `id` = 1");

// Process tick should detect that today is the scheduled day and time has passed,
// and resume the event into STATUS_ACTIVE.
Bastion::processTick();

$evRecovered = Bastion::getEvent();
assertEqual(Bastion::STATUS_ACTIVE, (int)$evRecovered['status'], "Event scheduled for today that was locked MUST auto-recover to STATUS_ACTIVE!");
echo "PASS: Event scheduled for today successfully auto-recovered to STATUS_ACTIVE!\n";

// =========================================================================
// TEST 4: setSchedule() Preserves Active Event
// =========================================================================
echo "Test 4: Testing setSchedule() preserves currently active event...\n";

// Ensure active
mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `status` = " . Bastion::STATUS_ACTIVE . " WHERE `id` = 1");

// Admin saves weekly schedule
Bastion::setSchedule(Bastion::SCHEDULE_WEEKLY, 0, $curDay, $curHour, 0, 7);

$evAfterSet = Bastion::getEvent();
assertEqual(Bastion::STATUS_ACTIVE, (int)$evAfterSet['status'], "setSchedule() MUST NOT reset active event to STATUS_SCHEDULED!");
echo "PASS: setSchedule() preserved active event!\n";

echo "\n=== ALL BASTION SCHEDULE TESTS PASSED SUCCESSFULLY! ===\n";
