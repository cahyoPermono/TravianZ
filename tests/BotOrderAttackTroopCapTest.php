<?php

#################################################################################
##  Filename       : tests/BotOrderAttackTroopCapTest.php                      ##
##  Type           : Unit Test for Bot Order Attack Troop Cap & No Provision   ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
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

echo "=== Running BotOrderAttackTroopCapTest ===" . PHP_EOL;

class MockOrderAttackDatabase {
    public $queries = [];
    public $unitsData = [];
    public $attacks = [];
    public $movements = [];

    public function escape($val) {
        return addslashes($val);
    }

    public function query($q) {
        $this->queries[] = $q;
        return true;
    }

    public function query_return($q) {
        $this->queries[] = $q;

        // 1. Check units table
        if (stripos($q, 'FROM ' . TB_PREFIX . 'units') !== false) {
            preg_match('/vref\s*=\s*(\d+)/i', $q, $m);
            $vref = isset($m[1]) ? (int)$m[1] : 0;
            return isset($this->unitsData[$vref]) ? [$this->unitsData[$vref]] : [];
        }

        // 2. Check hero table
        if (stripos($q, 'FROM ' . TB_PREFIX . 'hero') !== false) {
            return [['heroid' => 1, 'uid' => 10, 'dead' => 0, 'health' => 100, 'hero' => 0]];
        }

        // 3. Check alidata
        if (stripos($q, 'FROM ' . TB_PREFIX . 'alidata') !== false) {
            return [];
        }

        // 4. Check wdata for coordinates
        if (stripos($q, 'FROM ' . TB_PREFIX . 'wdata') !== false) {
            return [['id' => 200]];
        }

        // 5. Bot users query
        if (stripos($q, 'FROM ' . TB_PREFIX . 'users u') !== false) {
            return [[
                'uid'      => 10,
                'username' => 'Gilang',
                'tribe'    => 8, // Spartan
                'alliance' => 0,
                'wref'     => 100,
                'vname'    => 'Oakhaven',
                'x'        => -8,
                'y'        => -6,
                'ali_tag'  => '',
                'ali_name' => '',
            ]];
        }

        return [];
    }

    public function getMInfo($wref) {
        return [
            'wref'  => (int)$wref,
            'owner' => 2,
            'name'  => 'Code Emperror',
            'x'     => -4,
            'y'     => -24
        ];
    }

    public function getUserField($idOrName, $field, $mode = 0) {
        if ($field === 'username') return 'cahyo';
        if ($field === 'id') return 2;
        return null;
    }

    public function getProfileVillages($uid) {
        return [['wref' => 200]];
    }

    public function modifyUnit($wid, $unitNums, $amounts, $modes) {
        if (!isset($this->unitsData[$wid])) {
            $this->unitsData[$wid] = [];
        }
        for ($i = 0; $i < count($unitNums); $i++) {
            $col = 'u' . $unitNums[$i];
            $curr = $this->unitsData[$wid][$col] ?? 0;
            if ($modes[$i] == 1) { // ADD
                $this->unitsData[$wid][$col] = $curr + $amounts[$i];
            } else { // SUBTRACT
                $this->unitsData[$wid][$col] = max(0, $curr - $amounts[$i]);
            }
        }
    }

    public function addAttack($vref, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $hero, $type, $ctar1, $ctar2, $spy) {
        $attackId = count($this->attacks) + 1;
        $this->attacks[$attackId] = [
            'vref' => $vref,
            't1' => $t1, 't2' => $t2, 't3' => $t3, 't4' => $t4, 't5' => $t5,
            't6' => $t6, 't7' => $t7, 't8' => $t8, 't9' => $t9, 't10' => $t10,
            'hero' => $hero, 'type' => $type
        ];
        return $attackId;
    }

    public function addMovement($type, $from, $to, $ref, $starttime, $endtime) {
        $this->movements[] = [
            'type' => $type, 'from' => $from, 'to' => $to, 'ref' => $ref
        ];
        return true;
    }
}

global $database;
$mock = new MockOrderAttackDatabase();
$database = $mock;

// TEST 1: The user's exact case:
// Bot has 30 troops (29 Spartan Hoplite u71, 1 Agoge Warrior u73).
// Admin orders attack of 100 troops.
$mock->unitsData[100] = [
    'vref' => 100,
    'u71'  => 29, // Spartan Hoplite (slot 1)
    'u72'  => 1,  // Agoge Warrior (slot 2)
    'u73'  => 0,  // Homoioi (slot 3)
    'u74'  => 0, 'u75' => 0, 'u76' => 0, 'u77' => 0, 'u78' => 0, 'u79' => 0, 'u80' => 0,
    'hero' => 0
];

$res = BotAI::orderAttack(10, "cahyo", 4, 100, 'balanced');

assertEqual('success', $res['status'], "orderAttack must succeed");
assertEqual(1, $res['count'], "Must dispatch 1 bot");

// Check attack table in database
assertEqual(1, count($mock->attacks), "Must create exactly 1 attack");
$attack = $mock->attacks[1];
assertEqual(29, $attack['t1'], "Must send exactly 29 Spartan Hoplite (slot 1)");
assertEqual(1,  $attack['t2'], "Must send exactly 1 Agoge Warrior (slot 2)");
assertEqual(0,  $attack['t3'], "Must send 0 Homoioi (slot 3) as bot had 0");
assertEqual(0,  $attack['t4'], "Must send 0 Spartan Scout (slot 4)");
assertEqual(0,  $attack['t5'], "Must send 0 Spartan Rider (slot 5)");
assertEqual(0,  $attack['t6'], "Must send 0 Hippeis (slot 6)");

// Check total dispatched troops
$totalDispatched = $attack['t1'] + $attack['t2'] + $attack['t3'] + $attack['t4'] + $attack['t5'] + $attack['t6'] + $attack['t7'] + $attack['t8'] + $attack['t9'] + $attack['t10'];
assertEqual(30, $totalDispatched, "Total dispatched must be 30, NOT 100!");

// Check village remaining units: all 30 were sent, so 0 remain
assertEqual(0, $mock->unitsData[100]['u71'], "Spartan Hoplite at home must now be 0");
assertEqual(0, $mock->unitsData[100]['u72'], "Agoge Warrior at home must now be 0");

// Verify report text mentions 30 pasukan (Spartan Hoplite: 29, Agoge Warrior: 1)
$report = $res['reports'][0];
assertTrue(strpos($report, '30 pasukan') !== false, "Report must state 30 pasukan, report was: $report");
assertTrue(strpos($report, 'Spartan Hoplite: 29') !== false, "Report must state Spartan Hoplite: 29");
assertTrue(strpos($report, 'Agoge Warrior: 1') !== false, "Report must state Agoge Warrior: 1");

echo "PASS: Test 1 (Exact user scenario: 30 available troops capped strictly to 30) passed!" . PHP_EOL;

// TEST 2: Bot has 0 troops and 0 hero
$mock->unitsData[100] = [
    'vref' => 100,
    'u71' => 0, 'u72' => 0, 'u73' => 0, 'u74' => 0, 'u75' => 0,
    'u76' => 0, 'u77' => 0, 'u78' => 0, 'u79' => 0, 'u80' => 0,
    'hero' => 0
];
$resEmpty = BotAI::orderAttack(10, "cahyo", 4, 100, 'balanced');
assertEqual('error', $resEmpty['status'], "orderAttack must return error when bot has 0 troops");
echo "PASS: Test 2 (Bot with 0 troops returns error and skips) passed!" . PHP_EOL;

// TEST 3: Bot has 150 troops (100 Hoplite u71, 50 Rider u75). Admin orders 50 troops with composition 'cavalry'.
$mock->unitsData[100] = [
    'vref' => 100,
    'u71' => 100,
    'u72' => 0, 'u73' => 0, 'u74' => 0,
    'u75' => 50, // Cavalry
    'u76' => 0, 'u77' => 0, 'u78' => 0, 'u79' => 0, 'u80' => 0,
    'hero' => 0
];
$resCav = BotAI::orderAttack(10, "cahyo", 4, 50, 'cavalry');
assertEqual('success', $resCav['status'], "Cavalry orderAttack must succeed");
$cavAttack = $mock->attacks[2];
$cavTotal = $cavAttack['t1'] + $cavAttack['t2'] + $cavAttack['t3'] + $cavAttack['t4'] + $cavAttack['t5'] + $cavAttack['t6'];
assertEqual(50, $cavTotal, "Must dispatch exactly 50 troops");
assertTrue($cavAttack['t5'] > 0, "Must include cavalry troops (u75 -> slot t5)");
assertEqual(100, $mock->unitsData[100]['u71'] + $mock->unitsData[100]['u75'], "Village troops properly deducted to 100 remaining without phantom units");
echo "PASS: Test 3 (Partial army dispatch respects composition without phantom units) passed!" . PHP_EOL;
// TEST 4: Bot has Rams (u77) and Catapults (u78). Admin orders RAID (attack_type = 4).
// Verify Rams and Catapults are NEVER dispatched in a Raid!
$mock->unitsData[100] = [
    'vref' => 100,
    'u71' => 50,  // Hoplite (slot 1)
    'u72' => 0, 'u73' => 0, 'u74' => 0,
    'u75' => 20,  // Rider (slot 5)
    'u76' => 0,
    'u77' => 15,  // Spartan Ram (slot 7)
    'u78' => 10,  // Spartan Catapult (slot 8)
    'u79' => 0, 'u80' => 0,
    'hero' => 0
];
$resRaid = BotAI::orderAttack(10, "cahyo", 4, 100, 'balanced'); // attack_type = 4 (Raid)
assertEqual('success', $resRaid['status'], "Raid orderAttack must succeed");
$raidAttack = $mock->attacks[3];
assertEqual(0, (int)$raidAttack['t7'], "Rams (t7) must NEVER be dispatched in a Raid!");
assertEqual(0, (int)$raidAttack['t8'], "Catapults (t8) must NEVER be dispatched in a Raid!");
assertEqual(15, (int)$mock->unitsData[100]['u77'], "Rams must remain safely at home in village");
assertEqual(10, (int)$mock->unitsData[100]['u78'], "Catapults must remain safely at home in village");
echo "PASS: Test 4 (Rams & Catapults strictly excluded from Raid) passed!" . PHP_EOL;

// TEST 5: Bot has Rams (u77) and Catapults (u78). Admin orders NORMAL ATTACK (attack_type = 3) with composition 'siege'.
// Verify Rams and Catapults ARE dispatched in Normal Attack!
$mock->unitsData[100] = [
    'vref' => 100,
    'u71' => 50,  // Hoplite (slot 1)
    'u72' => 0, 'u73' => 0, 'u74' => 0,
    'u75' => 20,  // Rider (slot 5)
    'u76' => 0,
    'u77' => 15,  // Spartan Ram (slot 7)
    'u78' => 10,  // Spartan Catapult (slot 8)
    'u79' => 0, 'u80' => 0,
    'hero' => 0
];
$resNormal = BotAI::orderAttack(10, "cahyo", 3, 50, 'siege'); // attack_type = 3 (Normal Attack)
assertEqual('success', $resNormal['status'], "Normal attack orderAttack must succeed");
$normalAttack = $mock->attacks[4];
assertEqual(3, (int)$normalAttack['type'], "Attack type must be 3 (Normal Attack)");
assertTrue((int)$normalAttack['t7'] > 0 || (int)$normalAttack['t8'] > 0, "Rams and/or Catapults MUST be dispatched in Normal Attack with siege composition");
echo "PASS: Test 5 (Rams & Catapults properly dispatched in Normal Attack) passed!" . PHP_EOL;

// TEST 6: Admin orders RAID (attack_type = 4) but with composition 'siege'.
// Even if composition is 'siege', Raid must strictly disallow rams and catapults!
$mock->unitsData[100] = [
    'vref' => 100,
    'u71' => 50,  // Hoplite (slot 1)
    'u72' => 0, 'u73' => 0, 'u74' => 0,
    'u75' => 20,  // Rider (slot 5)
    'u76' => 0,
    'u77' => 15,  // Spartan Ram (slot 7)
    'u78' => 10,  // Spartan Catapult (slot 8)
    'u79' => 0, 'u80' => 0,
    'hero' => 0
];
$resRaidSiege = BotAI::orderAttack(10, "cahyo", 4, 30, 'siege'); // attack_type = 4 (Raid)
assertEqual('success', $resRaidSiege['status'], "Raid with siege composition request must succeed");
$raidSiegeAttack = $mock->attacks[5];
assertEqual(0, (int)$raidSiegeAttack['t7'], "Rams (t7) must NEVER be dispatched in a Raid even if composition was siege!");
assertEqual(0, (int)$raidSiegeAttack['t8'], "Catapults (t8) must NEVER be dispatched in a Raid even if composition was siege!");
echo "PASS: Test 6 (Raid with requested siege composition safely defaults to non-siege) passed!" . PHP_EOL;

echo "ALL BotOrderAttackTroopCapTest passed successfully!" . PHP_EOL;

