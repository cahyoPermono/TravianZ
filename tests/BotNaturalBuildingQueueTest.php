<?php

#################################################################################
##  Filename       : tests/BotNaturalBuildingQueueTest.php                     ##
##  Type           : Unit Test for Natural Bot Building & Builder Queue System ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';
require_once dirname(__DIR__) . '/GameEngine/Data/buidata.php';

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

echo "=== Running BotNaturalBuildingQueueTest ===" . PHP_EOL;

class MockQueueDatabase {
    public array $fdata = [];
    public array $vdata = [];
    public array $bdata = [];
    public array $queries = [];
    public int $nextBdataId = 1;

    public function escape($str) {
        return addslashes((string)$str);
    }

    public function getResourceLevel($wid, $use_cache = true) {
        return $this->fdata[$wid] ?? null;
    }

    public function getVillageFields($wid, $fields, $use_cache = true) {
        return $this->vdata[$wid] ?? null;
    }

    public function query_return($sql) {
        $this->queries[] = $sql;
        // SELECT id, field, type, level, timestamp, loopcon FROM ...bdata WHERE wid = $wid AND master = 0
        if (preg_match('/FROM\s+\w*bdata\s+WHERE\s+wid\s*=\s*(\d+)/i', $sql, $m)) {
            $wid = (int)$m[1];
            $res = [];
            foreach ($this->bdata as $row) {
                if ($row['wid'] === $wid && (int)$row['master'] === 0) {
                    $res[] = $row;
                }
            }
            return $res;
        }
        return [];
    }

    public function addBuilding($wid, $field, $type, $loop, $time, $master, $level) {
        $wid = (int)$wid;
        $field = (int)$field;
        $type = (int)$type;
        $loop = (int)$loop;
        $time = (int)$time;
        $master = (int)$master;
        $level = (int)$level;

        // Set type in fdata
        if (isset($this->fdata[$wid])) {
            $this->fdata[$wid]['f' . $field . 't'] = $type;
        }

        // Add to bdata
        $this->bdata[] = [
            'id' => $this->nextBdataId++,
            'wid' => $wid,
            'field' => $field,
            'type' => $type,
            'loop' => $loop,
            'timestamp' => $time,
            'master' => $master,
            'level' => $level,
            'loopcon' => 0,
        ];
        return true;
    }

    public function getBuildingTime($id, $tid, $plus, $wref, $buildingArray) {
        global ${'bid' . $tid}, $bid15;
        $dataArray = ${'bid' . $tid} ?? null;
        if (!$dataArray) {
            return 300;
        }
        $level = (int)($buildingArray['f' . $id] ?? 0) + (int)$plus;
        if (!isset($dataArray[$level]['time'])) {
            return 0;
        }
        $rawTime = (int)$dataArray[$level]['time'];
        $mbLevel = (int)($buildingArray['f26'] ?? 0);
        $speed = defined('SPEED') ? max(1, (float)SPEED) : 1.0;

        if ($tid == 15) {
            return (int)round($rawTime / $speed);
        }

        if ($mbLevel > 0 && isset($bid15[$mbLevel]['attri'])) {
            return (int)round($rawTime * ($bid15[$mbLevel]['attri'] / 100) / $speed);
        }
        return (int)round($rawTime * 5 / $speed);
    }

    public function modifyResource($vid, $wood, $clay, $iron, $crop, $mode, $setLastupdate = false) {
        if (!isset($this->vdata[$vid])) return false;
        $sign = (!$mode ? -1 : 1);
        $this->vdata[$vid]['wood'] = max(0, $this->vdata[$vid]['wood'] + ($sign * $wood));
        $this->vdata[$vid]['clay'] = max(0, $this->vdata[$vid]['clay'] + ($sign * $clay));
        $this->vdata[$vid]['iron'] = max(0, $this->vdata[$vid]['iron'] + ($sign * $iron));
        $this->vdata[$vid]['crop'] = max(0, $this->vdata[$vid]['crop'] + ($sign * $crop));
        return true;
    }

    public function query($sql) {
        $this->queries[] = $sql;
        return true;
    }
}

$mockDb = new MockQueueDatabase();
$GLOBALS['database'] = $mockDb;

// ------------------------------------------------------------------------------
// TEST 1: Real Resource Cost Deduction (Not Flat 40!)
// ------------------------------------------------------------------------------
echo "Test 1: Real Resource Cost Deduction from buidata.php... ";
$teutonBot = [
    'id' => 301,
    'wref' => 3001,
    'tribe' => 2, // Teuton
    'pop' => 40,
    'wood' => 1000, 'clay' => 1000, 'iron' => 1000, 'crop' => 1000,
    'created' => time() - (86400 * 2),
];
$fdata3001 = ['vref' => 3001];
for ($i = 1; $i <= 18; $i++) {
    $fdata3001['f' . $i] = 1;
    $fdata3001['f' . $i . 't'] = (($i - 1) % 4) + 1; // 1: wood, 2: clay, 3: iron, 4: crop
}
for ($i = 19; $i <= 40; $i++) {
    $fdata3001['f' . $i] = 0;
    $fdata3001['f' . $i . 't'] = 0;
}
$fdata3001['f26'] = 1; $fdata3001['f26t'] = 15; // Main Building lvl 1 (target is lvl 2)
$mockDb->fdata[3001] = $fdata3001;
$mockDb->vdata[3001] = $teutonBot;

// Main Building level 2 cost from $bid15[2]: wood: 90, clay: 50, iron: 75, crop: 25
global $bid15;
$mb2Wood = (int)$bid15[2]['wood']; // 90
$mb2Clay = (int)$bid15[2]['clay']; // 50
$mb2Iron = (int)$bid15[2]['iron']; // 75
$mb2Crop = (int)$bid15[2]['crop']; // 25

$res = BotAI::processBuild($teutonBot);
assertTrue($res !== null, "Teuton bot should upgrade Main Building to lvl 2");
assertTrue(count($mockDb->bdata) === 1, "Exactly 1 construction job must be added to bdata");

// Verify exact cost deduction (90 wood, 50 clay, 75 iron, 25 crop)
assertEqual(1000 - $mb2Wood, (int)$mockDb->vdata[3001]['wood'], "Wood deducted must match real buidata cost ($mb2Wood), not 40");
assertEqual(1000 - $mb2Clay, (int)$mockDb->vdata[3001]['clay'], "Clay deducted must match real buidata cost ($mb2Clay), not 40");
assertEqual(1000 - $mb2Iron, (int)$mockDb->vdata[3001]['iron'], "Iron deducted must match real buidata cost ($mb2Iron), not 35");
assertEqual(1000 - $mb2Crop, (int)$mockDb->vdata[3001]['crop'], "Crop deducted must match real buidata cost ($mb2Crop), not 25");
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Builder Queue Limit for Non-Romans (Max 1 Job Concurrently)
// ------------------------------------------------------------------------------
echo "Test 2: Builder Queue Limit for Non-Romans (Busy Queue Blocks New Upgrades)... ";
// Teuton bot currently has 1 active job in bdata. Another processBuild should be BLOCKED (return null).
$res2 = BotAI::processBuild($teutonBot);
assertEqual(null, $res2, "Teuton bot cannot start another construction while builder queue is busy (1 active job in bdata)");
assertEqual(1, count($mockDb->bdata), "No additional bdata entries should be created");
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Roman Dual Queue (1 Resource Field + 1 Village Building)
// ------------------------------------------------------------------------------
echo "Test 3: Roman Dual Queue (Simultaneous Resource Field + City Building)... ";
$romanBot = [
    'id' => 302,
    'wref' => 3002,
    'tribe' => 1, // Roman
    'pop' => 50,
    'wood' => 2000, 'clay' => 2000, 'iron' => 2000, 'crop' => 2000,
    'created' => time() - (86400 * 2),
];
$fdata3002 = ['vref' => 3002];
for ($i = 1; $i <= 18; $i++) {
    $fdata3002['f' . $i] = 1;
    $fdata3002['f' . $i . 't'] = (($i - 1) % 4) + 1;
}
for ($i = 19; $i <= 40; $i++) {
    $fdata3002['f' . $i] = 0;
    $fdata3002['f' . $i . 't'] = 0;
}
$fdata3002['f26'] = 1; $fdata3002['f26t'] = 15; // MB lvl 1
$mockDb->fdata[3002] = $fdata3002;
$mockDb->vdata[3002] = $romanBot;

// 1st build: Should pick Main Building (city building, slot 26 > 18)
$resRoman1 = BotAI::processBuild($romanBot);
assertTrue($resRoman1 !== null, "Roman bot should queue city building (MB lvl 2)");
$romanJobs = array_filter($mockDb->bdata, fn($j) => $j['wid'] === 3002);
assertEqual(1, count($romanJobs), "Roman should have 1 active city building in bdata");

// 2nd build: City queue is busy, but Resource (dorf1) queue is FREE! Roman CAN queue a resource field!
// Force lowest field to level 1 with maxFieldLvl >= 2
$resRoman2 = BotAI::processBuild($romanBot);
assertTrue($resRoman2 !== null, "Roman bot should queue resource field via its secondary builder queue");
$romanJobs = array_filter($mockDb->bdata, fn($j) => $j['wid'] === 3002);
assertEqual(2, count($romanJobs), "Roman should now have 2 jobs active (1 dorf1 + 1 dorf2)");

// 3rd build: BOTH queues are now busy! Roman bot must return null!
$resRoman3 = BotAI::processBuild($romanBot);
assertEqual(null, $resRoman3, "Roman bot cannot build a 3rd building when both dorf1 and dorf2 queues are occupied");
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 4: Catapult Damage Handicap & Step-by-Step Natural Recovery
// ------------------------------------------------------------------------------
echo "Test 4: Catapult Damage Handicap (No Instant Jump, Must Rebuild Level by Level)... ";
$catapultedBot = [
    'id' => 303,
    'wref' => 3003,
    'tribe' => 3, // Gaul
    'pop' => 30,
    'wood' => 500, 'clay' => 500, 'iron' => 500, 'crop' => 500,
    'created' => time() - (86400 * 3),
];
$fdata3003 = ['vref' => 3003];
for ($i = 1; $i <= 18; $i++) {
    $fdata3003['f' . $i] = 5; // Normal fields at level 5
    $fdata3003['f' . $i . 't'] = (($i - 1) % 4) + 1;
}
for ($i = 19; $i <= 40; $i++) {
    $fdata3003['f' . $i] = 1;
    $fdata3003['f' . $i . 't'] = 15;
}
// Catapult destroyed slot 3 (Iron Mine) completely to level 0!
$fdata3003['f3'] = 0;
$fdata3003['f3t'] = 3;

$mockDb->fdata[3003] = $fdata3003;
$mockDb->vdata[3003] = $catapultedBot;

$resCatapult = BotAI::processBuild($catapultedBot);
assertTrue($resCatapult !== null, "Bot should prioritize rebuilding destroyed slot 3");
$catapultJobs = array_values(array_filter($mockDb->bdata, fn($j) => $j['wid'] === 3003));
assertEqual(1, count($catapultJobs), "Job must be queued in bdata");
assertEqual(3, (int)$catapultJobs[0]['field'], "Queued field must be slot 3");
assertEqual(1, (int)$catapultJobs[0]['level'], "Target level must be LEVEL 1 (step by step, NEVER jumping back to lvl 5 or 9)");
assertTrue((int)$catapultJobs[0]['timestamp'] > time(), "Job finish timestamp must be in the future based on real construction time");

// While slot 3 level 1 is under construction in bdata, bot CANNOT upgrade slot 3 again or start another job
$resCatapultLocked = BotAI::processBuild($catapultedBot);
assertEqual(null, $resCatapultLocked, "Bot must wait through the actual construction duration of level 1 before it can do anything else");
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 5: Insufficient Resources Blocks Construction
// ------------------------------------------------------------------------------
echo "Test 5: Insufficient Resources Blocks Construction (Bot Naturally Saves Up)... ";
$poorBot = [
    'id' => 304,
    'wref' => 3004,
    'tribe' => 2,
    'pop' => 30,
    'wood' => 50, 'clay' => 50, 'iron' => 50, 'crop' => 50, // Less than woodcutter lvl 1 (wood: 40, clay: 100)
    'created' => time() - (86400 * 2),
];
$fdata3004 = ['vref' => 3004];
for ($i = 1; $i <= 18; $i++) {
    $fdata3004['f' . $i] = 0;
    $fdata3004['f' . $i . 't'] = (($i - 1) % 4) + 1;
}
for ($i = 19; $i <= 40; $i++) {
    $fdata3004['f' . $i] = 0;
    $fdata3004['f' . $i . 't'] = 0;
}
$mockDb->fdata[3004] = $fdata3004;
$mockDb->vdata[3004] = $poorBot;

$resPoor = BotAI::processBuild($poorBot);
assertEqual(null, $resPoor, "Bot with 50 clay cannot build Woodcutter lvl 1 which requires 100 clay");
$poorJobs = array_filter($mockDb->bdata, fn($j) => $j['wid'] === 3004);
assertEqual(0, count($poorJobs), "No bdata job should be queued when resources are insufficient");
echo "PASS" . PHP_EOL;

echo "=== All BotNaturalBuildingQueueTest tests passed successfully! ===" . PHP_EOL;
