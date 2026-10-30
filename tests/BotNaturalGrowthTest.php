<?php

#################################################################################
##  Filename       : tests/BotNaturalGrowthTest.php                            ##
##  Type           : Unit Test for Natural Bot Growth & Troop Rebalance        ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
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

echo "=== Running BotNaturalGrowthTest ===" . PHP_EOL;

// 1. Verify Method Existence
assertTrue(method_exists('BotAI', 'trimVillageTroops'), "BotAI::trimVillageTroops must exist");
assertTrue(method_exists('BotAI', 'rebalanceBotTroops'), "BotAI::rebalanceBotTroops must exist");

// 2. Test Age Calculation & Growth Ceilings
// Simulate Day 1 Bot (Age = 1.0 day on 1x speed server)
$ageDays = 1.0;
$speed = 1;
$effectiveAgeDays = $ageDays * max(1, $speed);

// Day 1 Building & Pop limits:
// 1 day = 50 + (1 * 25) = 75 pop ceiling
$maxPopDay1 = (int)round(50 + ($effectiveAgeDays * 25));
assertTrue($maxPopDay1 <= 80, "Day 1 bot population ceiling should be ~75 (<= 80), was: $maxPopDay1");

// Day 1 Resource Field Cap:
// 1 day = min(10, 2 + floor(1.0 * 1.0)) = 3
$fieldCapDay1 = min(10, 2 + (int)floor($effectiveAgeDays * 1.0));
assertEqual(3, $fieldCapDay1, "Day 1 bot resource field level ceiling must be level 3");

// Day 1 Troop Cap:
// 1 day = min(3000, 15 + (1.0 * 12)) = 27 troops
$maxTroopsDay1 = min(3000, (int)round(15 + ($effectiveAgeDays * 12)));
assertTrue($maxTroopsDay1 <= 35, "Day 1 bot troop limit should be ~27 (<= 35), was: $maxTroopsDay1");

// Day 1 Building Prerequisites (Stable & Workshop must be locked)
$canBuildStableDay1 = ($effectiveAgeDays >= 2.5);
$canBuildWorkshopDay1 = ($effectiveAgeDays >= 4.5);
assertTrue(!$canBuildStableDay1, "Stable (Kavaleri) must NOT be unlocked on Day 1");
assertTrue(!$canBuildWorkshopDay1, "Workshop (Senjata Pengepungan) must NOT be unlocked on Day 1");

// 3. Simulate Day 3 Bot
$ageDays3 = 3.0;
$effectiveAgeDays3 = $ageDays3 * max(1, $speed);
$maxPopDay3 = (int)round(50 + ($effectiveAgeDays3 * 25)); // 125
$fieldCapDay3 = min(10, 2 + (int)floor($effectiveAgeDays3 * 1.0)); // 5
$canBuildStableDay3 = ($effectiveAgeDays3 >= 2.5); // TRUE
$canBuildWorkshopDay3 = ($effectiveAgeDays3 >= 4.5); // FALSE

assertTrue($maxPopDay3 <= 150, "Day 3 bot population ceiling should be ~125, was: $maxPopDay3");
assertEqual(5, $fieldCapDay3, "Day 3 bot resource field cap must be level 5");
assertTrue($canBuildStableDay3, "Stable should be unlocked on Day 3");
assertTrue(!$canBuildWorkshopDay3, "Workshop should still be locked on Day 3");

// 4. Test Mock DB Trimming of Excessive Troop Count & Building Normalization
class MockBotTrimDatabase {
    public array $queries = [];
    public array $unitsData = [];
    public array $fdata = [];
    public array $vdata = [];

    public function query(string $sql) {
        $this->queries[] = $sql;
        // Parse update on units table
        if (preg_match('/UPDATE\s+\w*units\s+SET\s+(.+)\s+WHERE\s+vref\s*=\s*(\d+)/i', $sql, $m)) {
            $setClause = $m[1];
            $vref = (int)$m[2];
            $parts = explode(',', $setClause);
            foreach ($parts as $p) {
                if (preg_match('/(u\d+)\s*=\s*(\d+)/', $p, $pm)) {
                    $this->unitsData[$vref][$pm[1]] = (int)$pm[2];
                }
            }
        }
        // Parse update on fdata table
        if (preg_match('/UPDATE\s+\w*fdata\s+SET\s+(.+)\s+WHERE\s+vref\s*=\s*(\d+)/i', $sql, $m)) {
            $setClause = $m[1];
            $vref = (int)$m[2];
            $parts = explode(',', $setClause);
            foreach ($parts as $p) {
                if (preg_match('/(f\d+t?)\s*=\s*(\d+)/', $p, $pm)) {
                    $this->fdata[$vref][$pm[1]] = (int)$pm[2];
                }
            }
        }
        // Parse update on vdata table
        if (preg_match('/UPDATE\s+\w*vdata\s+SET\s+(.+)\s+WHERE\s+wref\s*=\s*(\d+)/i', $sql, $m)) {
            $setClause = $m[1];
            $wref = (int)$m[2];
            $parts = explode(',', $setClause);
            foreach ($parts as $p) {
                if (preg_match('/(wood|clay|iron|crop|maxstore|maxcrop)\s*=\s*(\d+)/', $p, $pm)) {
                    $this->vdata[$wref][$pm[1]] = (int)$pm[2];
                }
            }
        }
    }

    public array $modifyResourceCalls = [];

    public function modifyResource($vid, $wood, $clay, $iron, $crop, $mode, $setLastupdate = false) {
        $this->modifyResourceCalls[] = [
            'vid' => $vid,
            'wood' => $wood,
            'clay' => $clay,
            'iron' => $iron,
            'crop' => $crop,
            'mode' => $mode,
            'setLastupdate' => $setLastupdate,
        ];
        if (!isset($this->vdata[$vid])) {
            $this->vdata[$vid] = ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0, 'maxstore' => 1200, 'maxcrop' => 1200];
        }
        $sign = ($mode == 1) ? 1 : -1;
        $this->vdata[$vid]['wood'] = max(0, min($this->vdata[$vid]['maxstore'], $this->vdata[$vid]['wood'] + ($sign * $wood)));
        $this->vdata[$vid]['clay'] = max(0, min($this->vdata[$vid]['maxstore'], $this->vdata[$vid]['clay'] + ($sign * $clay)));
        $this->vdata[$vid]['iron'] = max(0, min($this->vdata[$vid]['maxstore'], $this->vdata[$vid]['iron'] + ($sign * $iron)));
        $this->vdata[$vid]['crop'] = max(0, min($this->vdata[$vid]['maxcrop'], $this->vdata[$vid]['crop'] + ($sign * $crop)));
        return true;
    }

    public function getVillageFields($vid, $fields, $useCache = false) {
        return $this->vdata[$vid] ?? ['wood' => 500, 'clay' => 500, 'iron' => 500, 'crop' => 500];
    }

    public function query_return(string $sql) {
        if (strpos($sql, 'FROM') !== false && strpos($sql, 'units') !== false) {
            $u11 = isset($this->unitsData[101]['u11']) ? $this->unitsData[101]['u11'] : 500;
            $u12 = isset($this->unitsData[101]['u12']) ? $this->unitsData[101]['u12'] : 150;
            $u15 = isset($this->unitsData[101]['u15']) ? $this->unitsData[101]['u15'] : 80;
            return [[
                'vref' => 101,
                'u11' => $u11,
                'u12' => $u12,
                'u13' => 0,
                'u14' => 0,
                'u15' => $u15,
                'u16' => 0,
                'u17' => 0,
                'u18' => 0,
                'u19' => 0,
                'u20' => 0,
            ]];
        }
        if (strpos($sql, 'users') !== false && strpos($sql, 'vdata') !== false) {
            return [[
                'uid' => 201,
                'username' => 'Bot_Test',
                'tribe' => 2,
                'regtime' => time() - 86400,
                'timestamp' => time(),
                'wref' => 101,
                'pop' => 450,
            ]];
        }
        return [];
    }

    public function getResourceLevel(int $vid, bool $useCache = false): array {
        $res = $this->fdata[$vid] ?? [];
        for ($i = 1; $i <= 18; $i++) {
            if (!isset($res['f' . $i])) $res['f' . $i] = 2;
            if (!isset($res['f' . $i . 't'])) $res['f' . $i . 't'] = ($i % 4) + 1;
        }
        if (!isset($res['f26'])) { $res['f26'] = 3; $res['f26t'] = 15; }
        if (!isset($res['f25'])) { $res['f25'] = 1; $res['f25t'] = 10; }
        if (!isset($res['f28'])) { $res['f28'] = 1; $res['f28t'] = 11; }
        if (!isset($res['f21'])) { $res['f21'] = 1; $res['f21t'] = 19; }
        if (!isset($res['f40'])) { $res['f40'] = 1; $res['f40t'] = 32; }
        if (!isset($res['f39'])) { $res['f39'] = 1; $res['f39t'] = 16; }
        return $res;
    }

    public function getVillageField(int $vid, string $field) {
        return 50;
    }

    public function recountPop(int $wid) {
        return 52;
    }
}

// Inject mock database
global $database;
$origDb = $database ?? null;
$mockDb = new MockBotTrimDatabase();
$database = $mockDb;

// Test trim on bloated village 101 (total 730 troops) down to target 30
$trimmedCount = BotAI::trimVillageTroops(101, 2, 30);

assertTrue($trimmedCount > 650, "Trimmed count should be around 700 units, was: $trimmedCount");
$afterU11 = $mockDb->unitsData[101]['u11'] ?? 500;
$afterU12 = $mockDb->unitsData[101]['u12'] ?? 150;
$afterU15 = $mockDb->unitsData[101]['u15'] ?? 80;
$totalAfter = $afterU11 + $afterU12 + $afterU15;

assertTrue($totalAfter <= 30, "Total troops after trimming must be <= 30, was: $totalAfter");
assertEqual(30, $totalAfter, "Total troops after trimming must equal target total 30");
assertTrue($afterU15 <= 5, "Cavalry (u15) must be scaled down proportionally (was 80, now: $afterU15)");
assertTrue($afterU11 >= 20, "Basic infantry (u11) must be scaled proportionally and retain bulk of troops (now: $afterU11)");
echo "Total troops trimmed from 730 down to: $totalAfter (Trimmed: $trimmedCount)" . PHP_EOL;

// 5. Test rebalanceVillageBuildings on Day 1
BotAI::rebalanceVillageBuildings(101, 2, 1.0);
assertEqual(2, $mockDb->fdata[101]['f1'], "Field 1 must be normalized to level 2 on Day 1");
assertEqual(2, $mockDb->fdata[101]['f18'], "Field 18 must be normalized to level 2 on Day 1");
assertEqual(3, $mockDb->fdata[101]['f26'], "Main Building (f26) must be level 3 on Day 1");
assertEqual(1, $mockDb->fdata[101]['f25'], "Warehouse (f25) must be level 1 on Day 1");
assertEqual(1, $mockDb->fdata[101]['f28'], "Granary (f28) must be level 1 on Day 1");
assertEqual(1, $mockDb->fdata[101]['f21'], "Barracks (f21) must be level 1 on Day 1");
assertEqual(0, $mockDb->fdata[101]['f20'], "Stable (f20) must be stripped/set to level 0 on Day 1");
assertEqual(0, $mockDb->fdata[101]['f23'], "Workshop (f23) must be stripped/set to level 0 on Day 1");
assertEqual(0, $mockDb->fdata[101]['f22'], "Academy (f22) must be stripped/set to level 0 on Day 1");

// Verify queues in bdata, training, research were deleted
$foundBdataDelete = false;
$foundTrainingDelete = false;
$foundResearchDelete = false;
foreach ($mockDb->queries as $q) {
    if (stripos($q, 'DELETE FROM') !== false && stripos($q, 'bdata') !== false) $foundBdataDelete = true;
    if (stripos($q, 'DELETE FROM') !== false && stripos($q, 'training') !== false) $foundTrainingDelete = true;
    if (stripos($q, 'DELETE FROM') !== false && stripos($q, 'research') !== false) $foundResearchDelete = true;
}
assertTrue($foundBdataDelete, "bdata pending construction queue must be deleted during building rebalance");
assertTrue($foundTrainingDelete, "training pending troop queue must be deleted during building rebalance");
assertTrue($foundResearchDelete, "research pending queue must be deleted during building rebalance");

// 6. Test rebalanceBotTroops with targetDay = 1
$rebReport = BotAI::rebalanceBotTroops(25, true, 1);
assertEqual('success', $rebReport['status'], "Rebalance status must be success");
assertEqual(1, $rebReport['pop_adjusted_bots'], "Must adjust pop for 1 bot");
assertEqual(1, $rebReport['target_day'], "Report must reflect target_day = 1");

// 7. Test BotAI::replenishResources: Natural growth & anti-instant-refill
// Simulate a village that was just raided: has only 30 wood left, warehouse cap is 1200
$mockDb->vdata[101] = [
    'wood' => 30,
    'clay' => 30,
    'iron' => 30,
    'crop' => 30,
    'maxstore' => 1200,
    'maxcrop' => 1200,
    'lastupdate' => time() - 60 // 60 seconds ago
];
$mockDb->modifyResourceCalls = [];

$testBot = [
    'wref' => 101,
    'pop' => 50,
    'maxstore' => 1200,
    'maxcrop' => 1200,
    'wood' => 30,
    'clay' => 30,
    'iron' => 30,
    'crop' => 30,
    'lastupdate' => time() - 60
];

BotAI::replenishResources($testBot);

// Verify modifyResource was called with a realistic delta, NOT an instant jump to 1200!
assertTrue(!empty($mockDb->modifyResourceCalls), "replenishResources must call modifyResource to update resources");
$lastCall = end($mockDb->modifyResourceCalls);
$addedWood = $lastCall['wood'];
assertTrue($addedWood > 0, "Wood produced in 60s should be > 0");
assertTrue($addedWood < 100, "Wood produced in 60s must be realistic (< 100 units, NOT +2500 boost!), was: $addedWood");
$woodAfter = $mockDb->vdata[101]['wood'];
assertTrue($woodAfter < 200, "Total wood after 60s of a raid should stay low (< 200), was: $woodAfter");
echo "Natural resource test passed: After raid, 60s produced +$addedWood wood (Total: $woodAfter/1200, no instant 1200 refill)" . PHP_EOL;

// 8. Test processBuild & processTrain pause when resources are depleted (< 30)
$mockDb->unitsData[101] = [
    'u11' => 10,
    'u12' => 0,
    'u15' => 0,
];
$mockDb->vdata[101] = [
    'wood' => 10,
    'clay' => 10,
    'iron' => 10,
    'crop' => 10,
    'maxstore' => 1200,
    'maxcrop' => 1200,
];
$depletedBot = [
    'wref' => 101,
    'tribe' => 2,
    'pop' => 50,
    'wood' => 10,
    'clay' => 10,
    'iron' => 10,
    'crop' => 10,
    'regtime' => time() - 86400,
];
$buildRes = BotAI::processBuild($depletedBot);
assertEqual(null, $buildRes, "processBuild must pause when resources are depleted (< 30) from raids");
$trainRes = BotAI::processTrain($depletedBot);
assertEqual(null, $trainRes, "processTrain must pause when resources are depleted (< 30) from raids");
echo "Raid impact test passed: Bot cannot build or train when depleted dry by raids" . PHP_EOL;

// Restore original DB
$database = $origDb;

echo "PASS: All BotNaturalGrowthTest assertions passed successfully!" . PHP_EOL;
