<?php

#################################################################################
##  Filename       : BotArmyBalancingTest.php                                  ##
##  Type           : Unit Test for Bot AI Army Balancing & Garrison Protection ##
#################################################################################

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);
if (!defined('BOT_AI_MIN_RAID_TROOPS')) define('BOT_AI_MIN_RAID_TROOPS', 15);
if (!defined('BOT_AI_MIN_DEFENSE_TROOPS')) define('BOT_AI_MIN_DEFENSE_TROOPS', 10);
if (!defined('BOT_AI_REVENGE_COOLDOWN')) define('BOT_AI_REVENGE_COOLDOWN', 7200);
if (!defined('BOT_AI_ATTACK_START_HOUR')) define('BOT_AI_ATTACK_START_HOUR', 0);
if (!defined('BOT_AI_ATTACK_END_HOUR')) define('BOT_AI_ATTACK_END_HOUR', 24);
if (!defined('BOT_AI_ATTACK_CHANCE')) define('BOT_AI_ATTACK_CHANCE', 100);

require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n");
        exit(1);
    }
}

class MockBalancingDatabase {
    public $calls = [];
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];
    public $standingUnits = [];
    public $recentMovements = [];
    public $outgoingMovements = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function getCoor($wid) {
        if ($wid === 101) return ['x' => 10, 'y' => 20];
        if ($wid === 777) return ['x' => 15, 'y' => 25];
        return ['x' => 0, 'y' => 0];
    }

    public function query_return($sql) {
        $this->calls[] = $sql;

        // Fetch standing units for vref
        if (strpos($sql, "FROM " . TB_PREFIX . "units WHERE vref = 101") !== false) {
            return [$this->standingUnits];
        }

        // Active outgoing attacks
        if (strpos($sql, "SELECT COUNT(m.moveid) as active_attacks") !== false) {
            return [['active_attacks' => count($this->outgoingMovements)]];
        }

        // Active outgoing defense
        if (strpos($sql, "SELECT COUNT(*) as active_reinf") !== false) {
            return [['active_reinf' => 0]];
        }

        // Active incoming attacks to allies / bot
        if (strpos($sql, "m.to IN (101)") !== false || strpos($sql, "m.`to` IN (101)") !== false) {
            return [];
        }

        // Recent combat notifications
        if (strpos($sql, "FROM " . TB_PREFIX . "ndata") !== false) {
            return [];
        }

        // Candidate targets
        if (strpos($sql, "SELECT v.wref, v.owner, v.name as vname, v.pop") !== false) {
            return [[
                'wref' => 777,
                'owner' => 999,
                'vname' => 'Enemy Village',
                'pop' => 120,
                'x' => 15,
                'y' => 25,
                'username' => 'EnemyPlayer',
                'access' => 2,
                'protect' => 0,
                'alliance' => 0,
            ]];
        }

        // Ongoing movements to target 777
        if (strpos($sql, "WHERE `from` = 101 AND `to` = 777 AND sort_type = 3 AND proc = 0") !== false) {
            return $this->outgoingMovements;
        }

        // Recent attack to target 777 within cooldown
        if (strpos($sql, "WHERE `from` = 101 AND `to` = 777 AND sort_type = 3 AND starttime >=") !== false) {
            return $this->recentMovements;
        }

        // Incoming to target check
        if (strpos($sql, "WHERE `to` = 777 AND sort_type = 3 AND proc = 0") !== false) {
            return [];
        }

        // Hero check
        if (strpos($sql, "FROM " . TB_PREFIX . "hero WHERE uid = 50") !== false) {
            return [['heroid' => 1, 'health' => 100, 'dead' => 0]];
        }

        return [];
    }

    public function query($sql) {
        $this->calls[] = $sql;
        return true;
    }

    public function modifyUnit($wid, $unitNums, $amounts, $modes) {
        $this->modifiedUnits[] = [
            'wid' => $wid,
            'unitNums' => $unitNums,
            'amounts' => $amounts,
            'modes' => $modes
        ];
        foreach ($unitNums as $idx => $uNum) {
            $key = 'u' . $uNum;
            if (isset($this->standingUnits[$key])) {
                $this->standingUnits[$key] = max(0, $this->standingUnits[$key] - $amounts[$idx]);
            }
        }
        return true;
    }

    public function addAttack($wid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $hero, $type, $c1, $c2, $spy) {
        $attackId = count($this->addedAttacks) + 1;
        $this->addedAttacks[] = [
            'id' => $attackId,
            'wid' => $wid,
            'units' => [$t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10],
            'hero' => $hero,
            'type' => $type
        ];
        return $attackId;
    }

    public function addMovement($type, $from, $to, $ref, $starttime, $endtime) {
        $this->addedMovements[] = [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'ref' => $ref,
            'starttime' => $starttime,
            'endtime' => $endtime
        ];
        return count($this->addedMovements);
    }
}

echo "Running Bot AI Army Balancing Test...\n";

// TEST 1: getHomeReserve() formulas
echo "Test 1: Testing getHomeReserve() across population sizes...\n";
assertEqual(15, BotAI::getHomeReserve(10), "Pop 10 should yield minimum 15 home reserve");
assertEqual(15, BotAI::getHomeReserve(50), "Pop 50 (20% = 10) should yield minimum 15 home reserve");
assertEqual(20, BotAI::getHomeReserve(100), "Pop 100 (20% = 20) should yield 20 home reserve");
assertEqual(40, BotAI::getHomeReserve(200), "Pop 200 (20% = 40) should yield 40 home reserve");
assertEqual(60, BotAI::getHomeReserve(300), "Pop 300 (20% = 60) should yield 60 home reserve");
assertEqual(60, BotAI::getHomeReserve(800), "Pop 800 should be capped at 60 home reserve");
echo "-> Passed Test 1!\n";

// TEST 2: processAttack anti-suicide threshold and garrison preservation
echo "Test 2: Testing processAttack() refuses to attack when army <= home reserve...\n";
global $database;
$database = new MockBalancingDatabase();

// Roman bot with pop 100 (homeReserve = 20) having only 18 troops (<= homeReserve)
$bot = [
    'wref' => 101,
    'uid' => 50,
    'tribe' => 1,
    'pop' => 100,
    'alliance' => 0,
];
$database->standingUnits = [
    'u1' => 18, 'u2' => 0, 'u3' => 0, 'u4' => 0, 'u5' => 0,
    'u6' => 0, 'u7' => 0, 'u8' => 0, 'u9' => 0, 'u10' => 0,
    'hero' => 0
];

$result = BotAI::processAttack($bot);
assertEqual(null, $result, "Bot with troops <= homeReserve must NOT launch attack");
assertEqual(0, count($database->addedAttacks), "No attack record should be created");
assertEqual(18, $database->standingUnits['u1'], "Home garrison must remain 100% intact");
echo "-> Passed Test 2!\n";

// TEST 3: processAttack with small surplus (< minAttackForce of 15)
echo "Test 3: Testing processAttack() refuses to attack when surplus < minAttackForce (15)...\n";
// Roman bot pop 100 (homeReserve = 20), standing troops = 28. Deployable surplus = 8 (< 15)
$database->standingUnits['u1'] = 28;
$result = BotAI::processAttack($bot);
assertEqual(null, $result, "Bot with surplus < 15 must NOT attack (anti-suicide)");
assertEqual(0, count($database->addedAttacks), "No attack record created for insufficient surplus");
echo "-> Passed Test 3!\n";

// TEST 4: processAttack with sufficient surplus dispatches Raid (Type 4) and preserves homeReserve
echo "Test 4: Testing processAttack() dispatches Raid (Type 4) and preserves homeReserve...\n";
// Roman bot pop 100 (homeReserve = 20), standing troops = 60 Legionnaires (u1). Deployable surplus = 40.
$database->standingUnits['u1'] = 60;
$result = BotAI::processAttack($bot);
assertTrue(!empty($result), "Bot with sufficient surplus should launch raid");
assertEqual(1, count($database->addedAttacks), "Attack record should be created");
$attack = $database->addedAttacks[0];

// Attack type MUST be 4 (Raid)
assertEqual(4, $attack['type'], "Attack type must be 4 (Raid) to prevent army extinction");

// Dispatched troops must not exceed deployable surplus (40), and home village must keep at least 20
$dispatchedCount = array_sum($attack['units']);
assertTrue($dispatchedCount >= 15, "Dispatched troops ($dispatchedCount) must meet minimum force of 15");
assertTrue($database->standingUnits['u1'] >= 20, "Village standing garrison ({$database->standingUnits['u1']}) must be >= homeReserve (20)");
echo "-> Dispatched $dispatchedCount troops, remaining at home: {$database->standingUnits['u1']}\n";
echo "-> Passed Test 4!\n";

// TEST 5: Siege weapons and overwhelming force (>= 80 deployable troops) upgrades to Normal Attack (Type 3)
echo "Test 5: Testing siege weapons + army >= 80 triggers Normal Attack (Type 3)...\n";
$database = new MockBalancingDatabase();
// Pop 200 (homeReserve = 40). Standing troops: 260 Imperians (u3) + 30 Rams (u7). Deployable = 250 (35% = 87 troops).
$bot['pop'] = 200;
$database->standingUnits = [
    'u1' => 0, 'u2' => 0, 'u3' => 260, 'u4' => 0, 'u5' => 0,
    'u6' => 0, 'u7' => 30, 'u8' => 0, 'u9' => 0, 'u10' => 0,
    'hero' => 0
];
$result = BotAI::processAttack($bot);
assertTrue(!empty($result), "Siege attack should succeed");
assertEqual(1, count($database->addedAttacks), "Attack record should be created");
$siegeAttack = $database->addedAttacks[0];
assertEqual(3, $siegeAttack['type'], "Overwhelming siege assault should use Normal Attack (Type 3)");
assertTrue($database->standingUnits['u3'] + $database->standingUnits['u7'] >= 40, "Home garrison reserve must still be preserved");
echo "-> Passed Test 5!\n";

// TEST 6: Revenge cooldown and war fatigue
echo "Test 6: Testing cooldown / war fatigue prevents attack loop...\n";
$database = new MockBalancingDatabase();
$database->standingUnits['u1'] = 60;
// Simulate an attack launched 1800s ago (within 7200s cooldown)
$database->recentMovements = [['moveid' => 999]];
$result = BotAI::processAttack($bot);
assertEqual(null, $result, "Bot must respect attack cooldown to the same target");
assertEqual(0, count($database->addedAttacks), "No attack launched during cooldown");
echo "-> Passed Test 6!\n";

// TEST 7: processAllianceDefense preserves home garrison reserve
echo "Test 7: Testing processAllianceDefense() preserves home garrison reserve...\n";
$database = new MockBalancingDatabase();
// Pop 100 (homeReserve = 20), Gaul bot (tribe 3).
$gaulBot = [
    'wref' => 101,
    'uid' => 50,
    'tribe' => 3,
    'pop' => 100,
    'alliance' => 1,
];
// Standing troops: only 15 Phalanx (u21). Deployable = max(0, 15 - 20) = 0.
$database->standingUnits = [
    'u21' => 15, 'u22' => 0, 'u23' => 0, 'u24' => 0, 'u25' => 0,
    'u26' => 0, 'u27' => 0, 'u28' => 0, 'u29' => 0, 'u30' => 0,
    'hero' => 0
];
$defResult = BotAI::processAllianceDefense($gaulBot);
assertEqual(null, $defResult, "Alliance defense must NOT dispatch troops when below homeReserve");
assertEqual(0, count($database->addedAttacks), "No reinforcement dispatched");
echo "-> Passed Test 7!\n";

echo "\nALL BOT ARMY BALANCING TESTS PASSED SUCCESSFULLY!\n";
