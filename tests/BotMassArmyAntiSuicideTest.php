<?php

#################################################################################
##  Filename       : tests/BotMassArmyAntiSuicideTest.php                      ##
##  Type           : Unit Test for Bot Focus Mass Army / Anti-Suicide Feature  ##
#################################################################################

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);
if (!defined('BOT_AI_MIN_RAID_TROOPS')) define('BOT_AI_MIN_RAID_TROOPS', 25);
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

function assertFalse($condition, $message) {
    if ($condition) {
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

class MockMassArmyDatabase {
    public $calls = [];
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];
    public $standingUnits = [];
    public $outgoingMovements = [];
    public $configData = [];

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
                'vname' => 'Enemy Metropolis',
                'pop' => 450,
                'x' => 15,
                'y' => 25,
                'username' => 'HumanDefender',
                'access' => 2,
                'protect' => 0,
                'alliance' => 0,
                'is_bot' => 0
            ]];
        }

        if (strpos($sql, "SELECT `k`, `v` FROM") !== false) {
            $rows = [];
            foreach ($this->configData as $k => $v) {
                $rows[] = ['k' => $k, 'v' => json_encode($v)];
            }
            return $rows;
        }

        if (strpos($sql, "SELECT COUNT(m.moveid) as cnt") !== false || strpos($sql, "SELECT m.`to` as target_wref") !== false) {
            return [];
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
                $this->standingUnits[$key] -= $amounts[$idx];
            }
        }
        return true;
    }

    public function addAttack($wid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $hero, $type, $ct1=0, $ct2=0, $spy=0) {
        $id = count($this->addedAttacks) + 1;
        $this->addedAttacks[] = [
            'id' => $id,
            'from' => $wid,
            'units' => [$t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10],
            'hero' => $hero,
            'type' => $type
        ];
        return $id;
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
        return true;
    }
}

echo "=== Running Bot Mass Army & Anti-Suicide Mobilization Tests ===\n";

global $database;
$database = new MockMassArmyDatabase();

// TEST 1: Config persistence for BOT_AI_MASS_ARMY_FIRST & BOT_AI_MASS_ARMY_MIN_TROOPS
echo "Test 1: Testing BotAI config persistence for Mass Army & Anti-Suicide mode...\n";
BotAI::saveConfig([
    'BOT_AI_MASS_ARMY_FIRST' => true,
    'BOT_AI_MASS_ARMY_MIN_TROOPS' => 1500,
    'BOT_AI_NIGHT_SLEEP_ENABLED' => false
]);
BotAI::resetRuntimeConfig();

assertTrue(BotAI::getConfig('BOT_AI_MASS_ARMY_FIRST'), "BOT_AI_MASS_ARMY_FIRST must be true");
assertEqual(1500, (int)BotAI::getConfig('BOT_AI_MASS_ARMY_MIN_TROOPS'), "BOT_AI_MASS_ARMY_MIN_TROOPS must be 1500");
echo "-> Passed Test 1!\n";

// TEST 2: Anti-Suicide Prevention: Bot with 120 troops refuses to attack when target is 1500
echo "Test 2: Testing processAttack() suppresses attacks when troops < 1500 (Anti-Suicide)...\n";
$bot = [
    'wref' => 101,
    'uid' => 10,
    'tribe' => 1, // Romans
    'pop' => 200,
    'alliance' => 0,
    'username' => 'Bot_Roman_Legion'
];

// Roman bot pop 200 (homeReserve = 40). Standing troops = 120 Imperians (u3). Deployable = 80 (< 1500)
$database->standingUnits = [
    'u1' => 0, 'u2' => 0, 'u3' => 120, 'u4' => 0, 'u5' => 0,
    'u6' => 0, 'u7' => 0, 'u8' => 0, 'u9' => 0, 'u10' => 0,
    'hero' => 0
];
$result = BotAI::processAttack($bot);
assertTrue($result !== null && strpos($result, 'Mode Himpun Pasukan') !== false, "Bot must hold attack and report amassing troops: got '$result'");
assertEqual(0, count($database->addedAttacks), "Must NOT create any attack when troops are below mass army threshold");
echo "-> Passed Test 2!\n";

// TEST 3: Mass Mobilization Strike: Bot with 2000 troops attacks with massive strike force
echo "Test 3: Testing processAttack() launches massive strike force when army reaches >= 1500...\n";
// Standing troops: 2000 Imperians (u3). Deployable = 1960 (>= 1500)
$database->standingUnits['u3'] = 2000;
$result = BotAI::processAttack($bot);
assertTrue(!empty($result), "Bot with >= 1500 troops should launch attack");
assertEqual(1, count($database->addedAttacks), "Exactly 1 attack must be created");

$attack = $database->addedAttacks[0];
$dispatchedTotal = array_sum($attack['units']);
assertTrue($dispatchedTotal >= 1000, "Dispatched troops ($dispatchedTotal) must be a formidable strike force (>= 1000 units), not a tiny suicide trickle");
echo "-> Dispatched massive strike force of $dispatchedTotal troops!\n";
echo "-> Passed Test 3!\n";

// TEST 4: Legacy behavior: When Mass Army First is unchecked (false), small attacks are permitted
echo "Test 4: Testing processAttack() permits small raids when BOT_AI_MASS_ARMY_FIRST is false...\n";
BotAI::saveConfig([
    'BOT_AI_MASS_ARMY_FIRST' => false
]);
BotAI::resetRuntimeConfig();

$database->addedAttacks = [];
$database->standingUnits['u3'] = 80; // Deployable = 40
$resultLegacy = BotAI::processAttack($bot);
assertTrue(!empty($resultLegacy), "When Mass Army is disabled, bot should dispatch small raid");
assertEqual(1, count($database->addedAttacks), "Attack record should be created for legacy mode");
echo "-> Dispatched small raid of " . array_sum($database->addedAttacks[0]['units']) . " troops in legacy mode.\n";
echo "-> Passed Test 4!\n";

// Restore config to default
BotAI::saveConfig([
    'BOT_AI_MASS_ARMY_FIRST' => false,
    'BOT_AI_MASS_ARMY_MIN_TROOPS' => 1000
]);
BotAI::resetRuntimeConfig();

echo "\nALL BOT MASS ARMY & ANTI-SUICIDE MOBILIZATION TESTS PASSED SUCCESSFULLY!\n";
