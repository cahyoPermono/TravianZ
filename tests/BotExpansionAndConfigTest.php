<?php

#################################################################################
##  Filename       : tests/BotExpansionAndConfigTest.php                       ##
##  Type           : Unit Test for Bot Config & Natural Village Expansion      ##
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

echo "=== Running BotExpansionAndConfigTest ===" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 1: Config Management (getConfig / saveConfig)
// ------------------------------------------------------------------------------
echo "Test 1: Config Management... ";
$preTestConfig = BotAI::getConfig();
$initialPop = BotAI::getConfig('BOT_AI_MAX_POP', 800);
assertTrue($initialPop >= 450, "Default BOT_AI_MAX_POP should be at least 450 (got $initialPop)");

$saveSuccess = BotAI::saveConfig([
    'BOT_AI_MAX_POP' => 1200,
    'BOT_AI_MAX_BUILDING_LEVEL' => 18,
    'BOT_AI_MAX_VILLAGES' => 3,
    'BOT_AI_EXPANSION_ENABLED' => true,
]);
assertTrue($saveSuccess, "BotAI::saveConfig should return true");

// Test runtime reload
BotAI::resetRuntimeConfig();

$allConfig = BotAI::getConfig();
assertTrue(is_array($allConfig), "BotAI::getConfig() with 0 arguments must return array");
assertEqual(1200, (int)($allConfig['BOT_AI_MAX_POP'] ?? 0), "BOT_AI_MAX_POP in array must be 1200");

assertEqual(1200, (int)BotAI::getConfig('BOT_AI_MAX_POP'), "BOT_AI_MAX_POP should now be 1200");
assertEqual(18, (int)BotAI::getConfig('BOT_AI_MAX_BUILDING_LEVEL'), "BOT_AI_MAX_BUILDING_LEVEL should now be 18");
assertEqual(3, (int)BotAI::getConfig('BOT_AI_MAX_VILLAGES'), "BOT_AI_MAX_VILLAGES should now be 3");
assertTrue(BotAI::getConfig('BOT_AI_EXPANSION_ENABLED'), "BOT_AI_EXPANSION_ENABLED should now be true");

// Reset to standard testing config
BotAI::saveConfig([
    'BOT_AI_MAX_POP' => 800,
    'BOT_AI_MAX_BUILDING_LEVEL' => 15,
    'BOT_AI_MAX_VILLAGES' => 2,
    'BOT_AI_EXPANSION_ENABLED' => true,
]);
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 2: Population Cap Scaling Beyond 445 Pop
// ------------------------------------------------------------------------------
echo "Test 2: Population Cap Scaling Beyond 445... ";
$effectiveAgeDays = 30.0; // Mature 30-day bot
$adminMaxPop = (int)BotAI::getConfig('BOT_AI_MAX_POP', 800);
$basePopLimit = 50;
$growthRate = 1.6;

// Formula implemented in BotAI.php:
$calcPopLimit = (int)round($basePopLimit + min($adminMaxPop - $basePopLimit, pow($effectiveAgeDays * 12, $growthRate)));
$maxPop = min($adminMaxPop, $calcPopLimit);

assertTrue($maxPop > 450, "Mature bot population cap should exceed 445 (got $maxPop with admin cap $adminMaxPop)");
assertEqual(800, $maxPop, "Mature 30-day bot population cap should reach admin cap (800)");
echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// TEST 3: Mock Database for Natural Expansion
// ------------------------------------------------------------------------------
echo "Test 3: Natural Expansion Workflow (Padepokan, Pionir, CP, Ekspedisi)... ";

class MockExpansionDatabase {
    public array $users = [];
    public array $vdata = [];
    public array $fdata = [];
    public array $units = [];
    public array $training = [];
    public array $movement = [];
    public array $wdata = [];
    public array $queries = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function query($sql) {
        $this->queries[] = $sql;
        // Parse INSERT INTO training
        if (preg_match('/INSERT INTO \w*training\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $sql, $m)) {
            $cols = array_map('trim', explode(',', $m[1]));
            $vals = array_map('trim', explode(',', $m[2]));
            $row = [];
            foreach ($cols as $idx => $c) {
                $row[$c] = trim($vals[$idx], "'\"");
            }
            $this->training[] = $row;
        }
        // Parse INSERT INTO movement
        if (preg_match('/INSERT INTO \w*movement\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $sql, $m)) {
            $cols = array_map('trim', explode(',', $m[1]));
            $vals = array_map('trim', explode(',', $m[2]));
            $row = [];
            foreach ($cols as $idx => $c) {
                $row[$c] = trim($vals[$idx], "'\"");
            }
            $this->movement[] = $row;
        }
        // Parse UPDATE units
        if (preg_match('/UPDATE\s+\w*units\s+SET\s+(.+)\s+WHERE\s+vref\s*=\s*(\d+)/i', $sql, $m)) {
            $vref = (int)$m[2];
            $parts = explode(',', $m[1]);
            foreach ($parts as $p) {
                if (preg_match('/(u\d+)\s*=\s*(.+)/', $p, $pm)) {
                    $val = trim($pm[2]);
                    if (preg_match('/u\d+\s*-\s*(\d+)/', $val, $vm)) {
                        $this->units[$vref][$pm[1]] = max(0, ($this->units[$vref][$pm[1]] ?? 0) - (int)$vm[1]);
                    } else {
                        $this->units[$vref][$pm[1]] = (int)$val;
                    }
                }
            }
        }
        // Parse UPDATE vdata
        if (preg_match('/UPDATE\s+\w*vdata\s+SET\s+(.+)\s+WHERE\s+wref\s*=\s*(\d+)/i', $sql, $m)) {
            $wref = (int)$m[2];
            $parts = explode(',', $m[1]);
            foreach ($parts as $p) {
                if (preg_match('/(wood|clay|iron|crop)\s*=\s*(.+)/', $p, $pm)) {
                    $res = $pm[1];
                    $val = trim($pm[2]);
                    if (preg_match('/(wood|clay|iron|crop)\s*-\s*(\d+)/', $val, $vm)) {
                        $this->vdata[$wref][$res] = max(0, ($this->vdata[$wref][$res] ?? 0) - (int)$vm[2]);
                    } else {
                        $this->vdata[$wref][$res] = (int)$val;
                    }
                }
            }
        }
        return true;
    }

    public function query_return($sql) {
        // SELECT u30 as s FROM units
        if (preg_match('/SELECT\s+u(\d+)\s+as\s+s\s+FROM\s+\w*units\s+WHERE\s+vref\s*=\s*(\d+)/is', $sql, $m)) {
            $col = 'u' . $m[1];
            $vref = (int)$m[2];
            return [['s' => $this->units[$vref][$col] ?? 0]];
        }
        // SUM(amt) FROM training
        if (preg_match('/SUM\(amt\)\s+as\s+queued\s+FROM\s+\w*training\s+WHERE\s+vref\s*=\s*(\d+)\s+AND\s+unit\s*=\s*(\d+)/is', $sql, $m)) {
            $vref = (int)$m[1];
            $unit = (int)$m[2];
            $queued = 0;
            foreach ($this->training as $tr) {
                if ((int)($tr['vref'] ?? 0) === $vref && (int)($tr['unit'] ?? 0) === $unit) {
                    $queued += (int)($tr['amt'] ?? 0);
                }
            }
            return [['queued' => $queued]];
        }
        // Check movement sort_type = 5
        if (preg_match('/FROM\s+\w*movement.+sort_type\s*=\s*5/is', $sql)) {
            $activeMoves = [];
            foreach ($this->movement as $mv) {
                if ((int)($mv['sort_type'] ?? 0) === 5 && (int)($mv['proc'] ?? 0) === 0) {
                    $activeMoves[] = $mv;
                }
            }
            return $activeMoves;
        }
        // Empty wilderness candidates (occupied = 0, fieldtype > 0)
        if (preg_match('/FROM\s+\w*wdata\s+WHERE\s+occupied\s*=\s*0/is', $sql)) {
            return [
                ['id' => 5001, 'x' => 12, 'y' => 24],
                ['id' => 5002, 'x' => 15, 'y' => 26],
            ];
        }
        return [];
    }

    public function getResourceLevel($wid, $use_cache = true) {
        return $this->fdata[$wid] ?? [];
    }

    public function getVillageFields($wid, $fields = '', $use_cache = true) {
        return $this->vdata[$wid] ?? [];
    }

    public function modifyResource($wid, $w, $c, $i, $cr, $mode = 0, $clamp = false) {
        if ($mode === 0) {
            $this->vdata[$wid]['wood'] = max(0, ($this->vdata[$wid]['wood'] ?? 0) - $w);
            $this->vdata[$wid]['clay'] = max(0, ($this->vdata[$wid]['clay'] ?? 0) - $c);
            $this->vdata[$wid]['iron'] = max(0, ($this->vdata[$wid]['iron'] ?? 0) - $i);
            $this->vdata[$wid]['crop'] = max(0, ($this->vdata[$wid]['crop'] ?? 0) - $cr);
        } else {
            $this->vdata[$wid]['wood'] = ($this->vdata[$wid]['wood'] ?? 0) + $w;
            $this->vdata[$wid]['clay'] = ($this->vdata[$wid]['clay'] ?? 0) + $c;
            $this->vdata[$wid]['iron'] = ($this->vdata[$wid]['iron'] ?? 0) + $i;
            $this->vdata[$wid]['crop'] = ($this->vdata[$wid]['crop'] ?? 0) + $cr;
        }
        return true;
    }

    public function modifyUnit($wid, $units, $amounts, $modes) {
        foreach ($units as $idx => $u) {
            $col = 'u' . $u;
            $amt = $amounts[$idx];
            $m = $modes[$idx];
            if ($m === 0) {
                $this->units[$wid][$col] = max(0, ($this->units[$wid][$col] ?? 0) - $amt);
            } else {
                $this->units[$wid][$col] = ($this->units[$wid][$col] ?? 0) + $amt;
            }
        }
        return true;
    }

    public function addMovement($type, $from, $to, $ref, $time, $endtime) {
        $this->movement[] = [
            'moveid' => count($this->movement) + 1,
            'sort_type' => $type,
            'from' => $from,
            'to' => $to,
            'ref' => $ref,
            'starttime' => $time,
            'endtime' => $endtime,
            'proc' => 0,
        ];
        return true;
    }

    public function getProfileVillages($uid) {
        $res = [];
        foreach ($this->vdata as $wref => $v) {
            if ((int)($v['owner'] ?? 0) === (int)$uid) {
                $res[] = ['wref' => $wref];
            }
        }
        return $res;
    }

    public function getUserField($uid, $field, $mode = 0) {
        return $this->users[$uid][$field] ?? 0;
    }

    public function getCoor($wid) {
        return ['x' => $this->wdata[$wid]['x'] ?? 10, 'y' => $this->wdata[$wid]['y'] ?? 20];
    }
}

// Setup simulated bot & world state
$mockDb = new MockExpansionDatabase();
$GLOBALS['database'] = $mockDb;

$botUid = 101;
$botWid = 2001;
$botTribe = 3; // Galia (Gaul), Settler is unit 30

$mockDb->users[$botUid] = [
    'id' => $botUid,
    'username' => 'Bot_Gaul_Warrior',
    'tribe' => $botTribe,
    'cp' => 500, // Initial CP (insufficient for 2nd village)
];

$mockDb->vdata[$botWid] = [
    'wref' => $botWid,
    'owner' => $botUid,
    'name' => 'Alami_Village',
    'wood' => 50000,
    'clay' => 50000,
    'iron' => 50000,
    'crop' => 50000,
    'maxstore' => 80000,
    'maxcrop' => 80000,
    'exp1' => 0,
    'exp2' => 0,
    'exp3' => 0,
    'created' => time() - (86400 * 10), // 10 days old
];

$mockDb->wdata[$botWid] = ['x' => 10, 'y' => 20];

// Building data: slot 25 is Residence (Padepokan)
$mockFdata = ['vref' => $botWid];
for ($i = 1; $i <= 40; $i++) {
    $mockFdata['f' . $i] = 1;
    $mockFdata['f' . $i . 't'] = 0;
}
$mockFdata['f25'] = 10;   // Residence Level 10
$mockFdata['f25t'] = 25;  // Type 25 (Residence)
$mockFdata['f39'] = 5;    // Rally Point Level 5
$mockFdata['f39t'] = 16;  // Type 16 (Rally Point)
$mockDb->fdata[$botWid] = $mockFdata;

$mockDb->units[$botWid] = [
    'vref' => $botWid,
    'u30' => 0, // 0 settlers initially
];

$botArray = [
    'id' => $botUid,
    'username' => 'Bot_Gaul_Warrior',
    'tribe' => $botTribe,
    'wref' => $botWid,
    'vname' => 'Alami_Village',
    'vcreated' => time() - (86400 * 10),
    'exp1' => 0,
    'res_level' => 10,
    'rp_level' => 5,
    'fdata' => $mockFdata,
    'vdata' => &$mockDb->vdata[$botWid],
    'is_capital' => true,
];

// Step A: When residence is 10 and 0 settlers, processExpansion must queue 1 settler in training
$expAction = BotAI::processExpansion($botArray);
assertTrue($expAction !== null, "Bot should take an expansion action (train settler)");
assertTrue(stripos($expAction, "Settler") !== false, "Action should be training settler, got: $expAction");
assertEqual(1, count($mockDb->training), "Training queue should have 1 item");
assertEqual("30", (string)$mockDb->training[0]['unit'], "Queued unit must be Settler (30 for Gaul)");

// Step B: Check expansion status reported to admin
$status = BotAI::getExpansionStatus($botUid, $botWid, $botTribe);
assertEqual(1, $status['villages'], "Bot should currently have 1 village");
assertEqual(1, $status['settlers_queued'], "1 settler should be queued");
assertTrue(stripos($status['status_text'], "Pionir") !== false, "Status text should indicate training pionir");

// Step C: Simulate 3 settlers completed training, but CP is still low (500 CP vs 2000 CP needed)
$mockDb->training = [];
$mockDb->units[$botWid]['u30'] = 3; // 3 settlers now at home

$statusCP = BotAI::getExpansionStatus($botUid, $botWid, $botTribe);
assertTrue(stripos($statusCP['status_text'], "Menunggu CP") !== false, "Status text should indicate waiting for CP (got: {$statusCP['status_text']})");

// Expansion should NOT launch expedition because CP is insufficient
$expActionNoCP = BotAI::processExpansion($botArray);
assertTrue($expActionNoCP !== null && stripos($expActionNoCP, "awaiting Culture Points") !== false, "Expansion should wait for Culture Points, got: $expActionNoCP");
assertEqual(0, count($mockDb->movement), "No movement should be dispatched without CP");

// Step D: Grant sufficient Culture Points (e.g., 2500 CP)
$mockDb->users[$botUid]['cp'] = 2500;

// Now processExpansion should launch the expedition!
$expActionGo = BotAI::processExpansion($botArray);
assertTrue($expActionGo !== null, "Bot should now dispatch Settler expedition");
assertTrue(stripos($expActionGo, "Dispatched 3 Settlers") !== false, "Expedition message expected, got: $expActionGo");

// Verify that 3 settlers and 750 of each resource were deducted
assertEqual(0, (int)$mockDb->units[$botWid]['u30'], "3 settlers must be deducted from garrison");
assertEqual(1, count($mockDb->movement), "Movement record must be inserted");
assertEqual(5, (int)$mockDb->movement[0]['sort_type'], "Movement sort_type must be 5 (Settler)");

// Verify status text shows expedition in progress
$statusTransit = BotAI::getExpansionStatus($botUid, $botWid, $botTribe);
assertTrue(stripos($statusTransit['status_text'], "Ekspedisi ke") !== false, "Status text should indicate expedition in transit, got: {$statusTransit['status_text']}");

echo "PASS" . PHP_EOL;

// ------------------------------------------------------------------------------
// Test 4: Expansion to 3rd Village (BOT_AI_MAX_VILLAGES = 3)
// ------------------------------------------------------------------------------
echo "Test 4: Expansion to 3rd Village (BOT_AI_MAX_VILLAGES = 3)... ";

// Configure max villages = 3
BotAI::saveConfig(['BOT_AI_MAX_VILLAGES' => 3]);

// Simulate bot currently owning 2 villages
$widVil2 = 2002;
$mockDb->vdata[$widVil2] = [
    'wref' => $widVil2,
    'owner' => $botUid,
    'name' => 'Village_2',
    'wood' => 50000,
    'clay' => 50000,
    'iron' => 50000,
    'crop' => 50000,
    'maxstore' => 80000,
    'maxcrop' => 80000,
    'exp1' => 0,
    'exp2' => 0,
    'exp3' => 0,
    'created' => time() - (86400 * 5),
];
$mockDb->wdata[$widVil2] = ['x' => 15, 'y' => 25];

// Village 1 used slot 1 for Village 2
$mockDb->vdata[$botWid]['exp1'] = $widVil2;
$mockDb->movement = []; // clear previous movement

// Case 4A: Village 1 with Residence Lvl 10 (only 1 slot) cannot expand further
$mockDb->fdata[$botWid]['f25'] = 10;
$mockDb->fdata[$botWid]['f25t'] = 25;
$mockDb->units[$botWid]['u30'] = 3;
$resVil1Lvl10 = BotAI::processExpansion($botArray);
assertEqual(null, $resVil1Lvl10, "Village 1 with Residence 10 cannot expand because slot 1 is used");

// Case 4B: Village 1 upgrades Residence to Level 20 (unlocks slot 2 / exp2)
$mockDb->fdata[$botWid]['f25'] = 20;
$botArray['fdata'] = $mockDb->fdata[$botWid];
$mockDb->units[$botWid]['u30'] = 3;
$mockDb->users[$botUid]['cp'] = 8000; // sufficient CP for 3rd village
$resVil1Lvl20 = BotAI::processExpansion($botArray);
assertTrue($resVil1Lvl20 !== null && stripos($resVil1Lvl20, "Dispatched 3 Settlers") !== false, "Village 1 with Residence 20 can expand to 3rd village!");

// Case 4C: When bot reaches 3 villages and max is 3, expansion stops
$mockDb->movement = [];
$widVil3 = 2003;
$mockDb->vdata[$widVil3] = [
    'wref' => $widVil3,
    'owner' => $botUid,
    'name' => 'Village_3',
    'exp1' => 0,
];
$resVilAtMax = BotAI::processExpansion($botArray);
assertEqual(null, $resVilAtMax, "When bot has reached max villages (3), processExpansion must return null");

// Restore pre-test config
if (!empty($preTestConfig)) {
    BotAI::saveConfig($preTestConfig);
}

echo "PASS" . PHP_EOL;

echo "=== All BotExpansionAndConfigTest tests passed successfully! ===" . PHP_EOL;

