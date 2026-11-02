<?php
/**
 * Test Dynamic Training Queue Recalculation on Building Upgrade
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Data/buidata.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

function assertCustom($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running TrainingQueueDynamicAdjustmentTest ===\n";

global $database, $technology;

// 1. Setup a dummy test village in memory/DB or use test village
$testVid = 999999;
$testUid = 999999;

// Cleanup any old test rows
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $testVid");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $testVid");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $testVid");

// Insert dummy village (Tribe 10 Nusantara)
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp, celebration, type, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, loyalty, exp1, exp2, exp3, created, natar, starv, starvupdate, evasion, tribe) VALUES ($testVid, $testUid, 'Test Nusa Vil', 1, 100, 100, 0, 0, 1000, 1000, 1000, 1000, 10000, 10000, " . time() . ", 100, 0, 0, 0, " . time() . ", 0, 0, 0, 0, 10)");

// Insert building data: Barracks (gid 19) at slot 19 with Level 3
$database->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f19, f19t) VALUES ($testVid, 3, 19)");

// Clear caches
if (method_exists($database, 'clearResourseLevelsCache')) {
    $database->clearResourseLevelsCache();
}

// 2. Test getTrainingTime for unit 91 (Pendekar Keris) at Level 3
$timeLvl3 = $technology->getTrainingTime(91, false, $testVid);
// Level 3: bid19[3]['attri'] = 81%. u91['time'] = 1400.
// If SPEED=1: 1400 * 0.81 = 1134. If SPEED=2: 567.
$expectedLvl3 = (int)round(1400 * 0.81 / SPEED);
assertCustom($timeLvl3 === $expectedLvl3, "Training time at level 3 is $expectedLvl3 seconds (got $timeLvl3)");

// 3. Queue 2 batches of troops at Level 3 speed
$now = time();
$amt1 = 208;
$amt2 = 185;
$batch1End = $now + ($timeLvl3 * $amt1);
$batch1Next = $now + $timeLvl3;

$batch2Start = $batch1End;
$batch2End = $batch2Start + ($timeLvl3 * $amt2);
$batch2Next = $batch2Start + $timeLvl3;

$database->query("INSERT INTO " . TB_PREFIX . "training (vref, unit, amt, pop, timestamp, eachtime, timestamp2) VALUES ($testVid, 91, $amt1, 1, $batch1End, $timeLvl3, $batch1Next)");
$id1 = mysqli_insert_id($database->dblink);

$database->query("INSERT INTO " . TB_PREFIX . "training (vref, unit, amt, pop, timestamp, eachtime, timestamp2) VALUES ($testVid, 91, $amt2, 1, $batch2End, $timeLvl3, $batch2Next)");
$id2 = mysqli_insert_id($database->dblink);

// Verify rows inserted
$queued = $database->getTraining($testVid);
assertCustom(count($queued) === 2, "2 batches inserted into training table");
assertCustom((int)$queued[0]['eachtime'] === $timeLvl3, "Batch 1 has eachtime $timeLvl3");
assertCustom((int)$queued[1]['eachtime'] === $timeLvl3, "Batch 2 has eachtime $timeLvl3");

// 4. Upgrade Barracks to Level 13
$database->query("UPDATE " . TB_PREFIX . "fdata SET f19 = 13 WHERE vref = $testVid");
if (method_exists($database, 'clearResourseLevelsCache')) {
    $database->clearResourseLevelsCache();
}

$timeLvl13 = $technology->getTrainingTime(91, false, $testVid);
$expectedLvl13 = (int)round(1400 * 0.2824 / SPEED);
assertCustom($timeLvl13 === $expectedLvl13, "Training time at level 13 is $expectedLvl13 seconds (got $timeLvl13)");
assertCustom($timeLvl13 < $timeLvl3, "Level 13 is significantly faster than Level 3 ($timeLvl13 < $timeLvl3)");

// 5. Run recalculateTrainingQueue
$updated = $technology->recalculateTrainingQueue($testVid, 19);
assertCustom($updated === 2, "Both training batches were updated (count: $updated)");

// 6. Verify updated batches
$queuedUpdated = $database->getTraining($testVid);
$batch1Updated = $queuedUpdated[0];
$batch2Updated = $queuedUpdated[1];

assertCustom((int)$batch1Updated['eachtime'] === $timeLvl13, "Batch 1 eachtime updated to Level 13 speed ($timeLvl13)");
assertCustom((int)$batch2Updated['eachtime'] === $timeLvl13, "Batch 2 eachtime updated to Level 13 speed ($timeLvl13)");

$batch1Duration = (int)$batch1Updated['timestamp'] - $now;
$expectedBatch1Duration = $amt1 * $timeLvl13;
assertCustom(abs($batch1Duration - $expectedBatch1Duration) <= 2, "Batch 1 total duration matches level 13 time ($batch1Duration ~ $expectedBatch1Duration)");

// Batch 2 should start exactly when Batch 1 finishes
$batch2StartUpdated = (int)$batch2Updated['timestamp2'] - $timeLvl13;
assertCustom($batch2StartUpdated === (int)$batch1Updated['timestamp'], "Batch 2 starts exactly when Batch 1 finishes");

$batch2Duration = (int)$batch2Updated['timestamp'] - (int)$batch1Updated['timestamp'];
$expectedBatch2Duration = $amt2 * $timeLvl13;
assertCustom($batch2Duration === $expectedBatch2Duration, "Batch 2 duration matches level 13 time ($batch2Duration === $expectedBatch2Duration)");

// 7. Test Idempotency (calling again should do 0 updates)
$updatedAgain = $technology->recalculateTrainingQueue($testVid, 19);
assertCustom($updatedAgain === 0, "Calling recalculate again when up-to-date returns 0 (idempotent)");

// 8. Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $testVid");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $testVid");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $testVid");

echo "=== All TrainingQueueDynamicAdjustmentTest tests passed successfully! ===\n";

