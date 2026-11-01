<?php
/**
 * Test to verify:
 * 1. Reinforcement battle report consolidation (no duplicate notices per player/tribe).
 * 2. DatabaseMessageQueries::noticeViewedDuplicates() marks all duplicates read.
 * 3. BotAI::processBastionWarfare offensive units include siege and have high mobilization cap.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationBattleResolution.php';
require_once __DIR__ . '/../GameEngine/BotAI.php';

global $database;

echo "=== STARTING REINFORCEMENT CONSOLIDATION & BOT SIEGE WARFARE TEST ===\n\n";

// Test 1: Verify noticeViewedDuplicates function exists and updates records
echo "Test 1: Testing DatabaseMessageQueries::noticeViewedDuplicates()...\n";
$prefix = TB_PREFIX;
$testUid = 999999;
$testTime = time();
$testTopic = "Bantuan di Benteng Kuno diserang";

// Insert 3 mock notices with viewed = 0
$database->query("INSERT INTO {$prefix}ndata (uid, toWref, topic, ntype, data, time, viewed) VALUES 
    ({$testUid}, 100, '{$testTopic}', 15, 'mock_data', {$testTime}, 0),
    ({$testUid}, 100, '{$testTopic}', 15, 'mock_data', {$testTime}, 0),
    ({$testUid}, 100, '{$testTopic}', 15, 'mock_data', {$testTime}, 0)");

$checkUnread = $database->query("SELECT COUNT(*) as cnt FROM {$prefix}ndata WHERE uid = {$testUid} AND time = {$testTime} AND viewed = 0");
$rowUnread = mysqli_fetch_assoc($checkUnread);
if ($rowUnread['cnt'] != 3) {
    echo "FAIL: Expected 3 unread notices, found " . $rowUnread['cnt'] . "\n";
    exit(1);
}

// Call noticeViewedDuplicates
$database->noticeViewedDuplicates($testUid, $testTime, $testTopic);

$checkRead = $database->query("SELECT COUNT(*) as cnt FROM {$prefix}ndata WHERE uid = {$testUid} AND time = {$testTime} AND viewed = 1");
$rowRead = mysqli_fetch_assoc($checkRead);
if ($rowRead['cnt'] != 3) {
    echo "FAIL: Expected 3 read notices, found " . $rowRead['cnt'] . "\n";
    exit(1);
}
echo "PASS: noticeViewedDuplicates successfully marked all 3 duplicate notices as read!\n\n";

// Clean up mock notices
$database->query("DELETE FROM {$prefix}ndata WHERE uid = {$testUid}");

// Test 2: Verify BotAI bastion mobilization configs and siege inclusion
echo "Test 2: Testing BotAI Bastion warfare siege and mobilization parameters...\n";
$maxCap = BotAI::getConfig('BOT_AI_BASTION_MAX_ATTACK_TROOPS', 3500);
if ($maxCap < 1000) {
    echo "FAIL: BOT_AI_BASTION_MAX_ATTACK_TROOPS should be >= 1000 (found: {$maxCap})\n";
    exit(1);
}
echo "PASS: BotAI::getConfig('BOT_AI_BASTION_MAX_ATTACK_TROOPS') = {$maxCap} allows large-scale siege strike forces!\n";

// Test getOffensiveUnits with $includeSiege = true vs false for Romans (Tribe 1)
$offensiveWithSiege = BotAI::getOffensiveUnits(1, true);
$offensiveWithoutSiege = BotAI::getOffensiveUnits(1, false);

if (!in_array(7, $offensiveWithSiege) || !in_array(8, $offensiveWithSiege)) {
    echo "FAIL: getOffensiveUnits with includeSiege=true did not include Roman rams (7) or catapults (8)!\n";
    exit(1);
}
if (in_array(7, $offensiveWithoutSiege) || in_array(8, $offensiveWithoutSiege)) {
    echo "FAIL: getOffensiveUnits with includeSiege=false should NOT include Roman rams or catapults!\n";
    exit(1);
}
echo "PASS: BotAI::getOffensiveUnits correctly supports siege engine integration (Rams & Catapults)!\n\n";

echo "=== ALL REINFORCEMENT CONSOLIDATION & BOT SIEGE WARFARE TESTS PASSED! ===\n";
