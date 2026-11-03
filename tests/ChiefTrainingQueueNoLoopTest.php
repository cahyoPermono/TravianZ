<?php

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/loader.php';
tz_load_language('en');
require_once dirname(__DIR__) . '/GameEngine/Data/unitdata.php';
require_once dirname(__DIR__) . '/GameEngine/Data/buidata.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';
require_once dirname(__DIR__) . '/GameEngine/Automation/AutomationTraining.php';

class FakeDatabaseChiefTest {
    public $queries = [];
    public $training = [];
    public $unitMods = [];
    public $deletedTrainingIds = [];
    public $updatedTraining = [];

    public function query($sql) {
        $this->queries[] = $sql;
        return true;
    }

    public function getTraining($vid) {
        return $this->training;
    }

    public function getTrainingList() {
        return $this->training;
    }

    public function getVillageField($vid, $field) {
        if ($field === 'tribe') return 10; // Nusantara
        return 0;
    }

    public function getUserField($uid, $field, $mode = 0) {
        if ($field === 'tribe') return 10;
        return 0;
    }

    public function getFieldLevelInVillage($vid, $gid) {
        if ($gid == 25) return 20; // Residence Level 20
        return 0;
    }

    public function getProfileVillages($vids, $mode) {}
    public function cacheResourceLevels($vids) {}
    public function getUnit($vids) { return []; }
    public function getEnforceVillage($vids, $mode) { return []; }
    public function getMovement($mode, $vids, $type) { return []; }
    public function getOasisEnforce($vids, $mode) { return []; }
    public function getPrisoners($vids, $mode) { return []; }
    public function addStarvationData($vid) {}

    public function modifyUnit($vref, $units, $amounts, $modes) {
        $this->unitMods[] = [
            'vref' => $vref,
            'units' => $units,
            'amounts' => $amounts,
            'modes' => $modes
        ];
        return true;
    }

    public function updateTraining($id, $trained, $duration) {
        $this->updatedTraining[] = [
            'id' => $id,
            'trained' => $trained,
            'duration' => $duration
        ];
        return true;
    }

    public function trainUnit($id, $unit, $amt, $pop, $each, $mode) {
        if ($mode == 1) {
            $this->deletedTrainingIds[] = $id;
        }
        return true;
    }

    public static function clearUnitsCache() {}
}

class AutomationTrainingHarness {
    use AutomationTraining {
        trainingComplete as public runTrainingComplete;
    }
}

function runTests() {
    global $database, $bid25, $u99_nusantara, $technology;

    $db = new FakeDatabaseChiefTest();
    $database = $db;

    echo "Test 1: Check unit 99 base training time for Nusantara\n";
    $tech = new Technology();
    $technology = $tech;
    $time = $tech->getTrainingTime(99, false, 1234);
    $expectedSpeed = defined('SPEED') && SPEED > 0 ? (float)SPEED : 1.0;
    $expectedAttr = $bid25[20]['attri']; // 13.51
    $expectedEach = (int)round(($expectedAttr / 100) * 85000.0 / $expectedSpeed);
    if ($time !== $expectedEach) {
        echo "FAIL: Expected training time $expectedEach, got $time\n";
        exit(1);
    }
    echo "  PASS: Training time for unit 99 is $time (matches Nusantara Senapati Palapa in Residence 20)\n";

    echo "Test 2: recalculateTrainingQueue does NOT modify row if newEach == oldEach\n";
    $now = time();
    $db->training = [
        [
            'id' => 77,
            'vref' => 1234,
            'unit' => 99,
            'amt' => 1,
            'pop' => 4,
            'timestamp' => $now + 50,
            'eachtime' => $expectedEach,
            'timestamp2' => $now + 50,
        ]
    ];
    $db->queries = [];
    $updated = $tech->recalculateTrainingQueue(1234, 25);
    if ($updated !== 0) {
        echo "FAIL: Expected 0 rows updated, got $updated\n";
        exit(1);
    }
    if (count($db->queries) !== 0) {
        echo "FAIL: Expected 0 queries executed, got " . count($db->queries) . "\n";
        exit(1);
    }
    echo "  PASS: Zero rows updated when newEach == oldEach (no loops caused by timestamp re-anchoring)\n";

    echo "Test 3: recalculateTrainingQueue does NOT reset overdue timestamp2 to \$now\n";
    $oldEach = (int)round(($bid25[10]['attri'] / 100) * 85000.0 / $expectedSpeed);
    $overdueTimestamp2 = $now - 5;
    $db->training = [
        [
            'id' => 88,
            'vref' => 1234,
            'unit' => 99,
            'amt' => 1,
            'pop' => 4,
            'timestamp' => $now - 5,
            'eachtime' => $oldEach,
            'timestamp2' => $overdueTimestamp2,
        ]
    ];
    $db->queries = [];
    $updated = $tech->recalculateTrainingQueue(1234, 25);
    if ($updated !== 1) {
        echo "FAIL: Expected 1 row updated on building upgrade, got $updated\n";
        exit(1);
    }
    if (strpos($db->queries[0], "timestamp2 = $overdueTimestamp2") === false) {
        echo "FAIL: Timestamp2 must preserve old overdue timestamp ($overdueTimestamp2) instead of being pushed to $now! Query: " . $db->queries[0] . "\n";
        exit(1);
    }
    echo "  PASS: Overdue timestamp2 preserved so training completion detects readiness immediately\n";

    echo "Test 4: AutomationTraining completion produces Unit 99 and removes queue entry\n";
    $db->training = [
        [
            'id' => 991,
            'vref' => 1234,
            'unit' => 99,
            'amt' => 1,
            'pop' => 4,
            'timestamp' => $now - 2,
            'eachtime' => 53,
            'timestamp2' => $now - 2,
        ]
    ];
    $db->unitMods = [];
    $db->deletedTrainingIds = [];
    $harness = new AutomationTrainingHarness();
    $harness->runTrainingComplete();

    if (empty($db->unitMods)) {
        echo "FAIL: modifyUnit was not called for completed training!\n";
        exit(1);
    }
    if ($db->unitMods[0]['units'][0] !== 99 || $db->unitMods[0]['amounts'][0] !== 1) {
        echo "FAIL: modifyUnit did not add unit 99 (amount 1)! Got: " . json_encode($db->unitMods) . "\n";
        exit(1);
    }
    if (!in_array(991, $db->deletedTrainingIds)) {
        echo "FAIL: Training row 991 was not deleted from training queue! Deleted IDs: " . json_encode($db->deletedTrainingIds) . "\n";
        exit(1);
    }
    echo "  PASS: Unit 99 (Senapati Palapa) added to village troops and row deleted from training table\n";

    echo "ALL TESTS PASSED!\n";
}

runTests();
