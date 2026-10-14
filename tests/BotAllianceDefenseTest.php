<?php

#################################################################################
##  Filename       : BotAllianceDefenseTest.php                                ##
##  Type           : Unit Test for BotAI Alliance Mutual Defense Feature       ##
#################################################################################

// Define constants if not already defined
if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);

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

class MockDatabaseDefense {
    public $calls = [];
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function getCoor($wid) {
        return ['x' => 10, 'y' => 20];
    }

    public function query_return($sql) {
        $this->calls[] = $sql;

        // 1. Fetch available units at home (vref = 101)
        if (strpos($sql, "FROM " . TB_PREFIX . "units WHERE vref = 101") !== false) {
            return [[
                'u1' => 50,  // Legionnaire (basic)
                'u2' => 80,  // Praetorian (inf_def)
                'u3' => 20,  // Imperian (inf_adv)
                'u4' => 0,
                'u5' => 10,  // Equites Imperatoris (cav_light)
                'u6' => 15,  // Equites Caesaris (cav_heavy)
                'u7' => 0,
                'u8' => 0,
                'u9' => 0,
                'u10' => 0,
                'hero' => 1
            ]];
        }

        // 2. Active outgoing reinforcement check
        if (strpos($sql, "m.`from` = 101 AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2") !== false && strpos($sql, "COUNT(*)") !== false) {
            return [['active_reinf' => 0]];
        }

        // 3. Search for active incoming attacks on ally village
        if (strpos($sql, "u.alliance = 5") !== false && strpos($sql, "m.sort_type = 3") !== false && strpos($sql, "a.attack_type IN (3, 4)") !== false) {
            return [[
                'moveid' => 999,
                'target_wid' => 202,
                'attacker_wid' => 888,
                'endtime' => time() + 900, // 15 mins left
                'vname' => 'Benteng Sekutu',
                'pop' => 350,
                'x' => 15,
                'y' => 25,
                'ally_username' => 'Kawan_Satu_Ormas',
                'ally_uid' => 55,
                'attack_type' => 3
            ]];
        }

        // 4. Duplicate check: is this bot already marching?
        if (strpos($sql, "m.`from` = 101 AND m.`to` = 202") !== false) {
            return [];
        }

        // 5. Already stationed check
        if (strpos($sql, "FROM " . TB_PREFIX . "enforcement WHERE vref = 202 AND `from` = 101") !== false) {
            return [];
        }

        // 6. Helpers count check
        if (strpos($sql, "m.`to` = 202 AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2") !== false) {
            return [['total_marching' => 0]];
        }

        // 7. Hero check
        if (strpos($sql, "FROM " . TB_PREFIX . "hero WHERE uid = 12") !== false) {
            return [[
                'heroid' => 1,
                'health' => 85,
                'dead' => 0
            ]];
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
            'units' => $unitNums,
            'amounts' => $amounts,
            'modes' => $modes
        ];
        return true;
    }

    public function addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $ctar1, $ctar2, $spy) {
        $id = count($this->addedAttacks) + 1;
        $this->addedAttacks[] = [
            'id' => $id,
            'vid' => $vid,
            'troops' => [$t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10],
            'hero' => $t11,
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
        return 1;
    }
}

// Set mock database globally BEFORE any method calls
global $database;
$database = new MockDatabaseDefense();

// -----------------------------------------------------------------------------
// Test 1: Bot without alliance returns null
// -----------------------------------------------------------------------------
$botNoAlliance = [
    'uid' => 10,
    'username' => 'Bot_Solo',
    'tribe' => 1,
    'wref' => 101,
    'pop' => 150,
    'alliance' => 0
];
$res = BotAI::processAllianceDefense($botNoAlliance);
assertEqual(null, $res, "Bot without alliance returns null");

// -----------------------------------------------------------------------------
// Test 2: orderAttack input validation for attackType = 2 (Reinforcement)
// -----------------------------------------------------------------------------
$resEmpty = BotAI::orderAttack(0, "", 2, 50, 'defense');
assertEqual('error', $resEmpty['status'], "orderAttack with empty target returns error status");

// -----------------------------------------------------------------------------
// Test 3: Simulation for Active Incoming Attack on Ally (Priority 1)
// -----------------------------------------------------------------------------
$botAlly = [
    'uid' => 12,
    'username' => 'Bot_Helper',
    'tribe' => 1, // Roman
    'wref' => 101,
    'pop' => 200,
    'alliance' => 5
];

$defReport = BotAI::processAllianceDefense($botAlly);
assertTrue($defReport !== null, "processAllianceDefense must return non-null string report when ally is under attack");
assertTrue(strpos($defReport, "Kawan_Satu_Ormas") !== false, "Report must mention the attacked ally username");
assertTrue(strpos($defReport, "Benteng Sekutu") !== false, "Report must mention the attacked ally village name");
assertTrue(strpos($defReport, "Hero: 1") !== false, "Report must mention Hero reinforcement participation");

assertEqual(1, count($database->addedAttacks), "Must create exactly 1 attack record");
assertEqual(2, $database->addedAttacks[0]['type'], "Attack record must have attack_type = 2 (Reinforcement)");
assertEqual(1, $database->addedAttacks[0]['hero'], "Hero flag must be set to 1 in attack record");

assertEqual(1, count($database->addedMovements), "Must create exactly 1 movement record");
assertEqual(3, $database->addedMovements[0]['type'], "Movement sort_type must be 3");
assertEqual(101, $database->addedMovements[0]['from'], "Movement from must be bot's village 101");
assertEqual(202, $database->addedMovements[0]['to'], "Movement to must be ally's village 202");

assertEqual(1, count($database->modifiedUnits), "Troops must be deducted from bot's village");
assertEqual(101, $database->modifiedUnits[0]['wid'], "Troops must be deducted from wref 101");
foreach ($database->modifiedUnits[0]['modes'] as $m) {
    assertEqual(0, $m, "Deduction mode must be 0 (subtract)");
}

echo "PASS BotAllianceDefenseTest passed successfully!\n";
