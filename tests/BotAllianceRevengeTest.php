<?php

#################################################################################
##  Filename       : BotAllianceRevengeTest.php                                ##
##  Type           : Unit Test for BotAI Alliance Revenge Feature              ##
#################################################################################

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);
if (!defined('BOT_AI_ATTACK_START_HOUR')) define('BOT_AI_ATTACK_START_HOUR', 0);
if (!defined('BOT_AI_ATTACK_END_HOUR')) define('BOT_AI_ATTACK_END_HOUR', 24);

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

class MockDatabaseRevenge {
    public $calls = [];
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];

    public function escape($str) {
        return addslashes($str);
    }

    public function getCoor($wid) {
        if ($wid === 101) return ['x' => 10, 'y' => 20];
        if ($wid === 777) return ['x' => 30, 'y' => 40];
        return ['x' => 0, 'y' => 0];
    }

    public function query_return($sql) {
        $this->calls[] = $sql;

        // 1. Fetch available units at home (vref = 101)
        if (strpos($sql, "FROM " . TB_PREFIX . "units WHERE vref = 101") !== false) {
            return [[
                'u1' => 60,  // Legionnaire
                'u2' => 40,  // Praetorian
                'u3' => 80,  // Imperian
                'u4' => 0,
                'u5' => 20,  // Equites Imperatoris
                'u6' => 10,  // Equites Caesaris
                'u7' => 0,
                'u8' => 0,
                'u9' => 0,
                'u10' => 0,
                'hero' => 0
            ]];
        }

        // 2. Active outgoing attacks check
        if (strpos($sql, "COUNT(m.moveid) as active_attacks") !== false) {
            return [['active_attacks' => 0]];
        }

        // 3. Ally villages list (alliance = 5)
        if (strpos($sql, "WHERE u.alliance = 5") !== false && strpos($sql, "SELECT v.wref") !== false) {
            return [
                ['wref' => 101],
                ['wref' => 202]
            ];
        }

        // 4. Channel A: Active incoming attack on ally village 202 from enemy 777
        if (strpos($sql, "WHERE m.`to` IN") !== false && strpos($sql, "a.attack_type IN (3, 4)") !== false) {
            return [[
                'attacker_wref' => 777,
                'starttime' => time() - 300
            ]];
        }

        // 5. Channel B: ndata reports
        if (strpos($sql, "FROM " . TB_PREFIX . "ndata") !== false) {
            return [];
        }

        // 6. Target village info for 777
        if (strpos($sql, "v.wref IN (777)") !== false) {
            return [[
                'wref' => 777,
                'owner' => 99,
                'vname' => 'Markas Musuh',
                'pop' => 500,
                'x' => 30,
                'y' => 40,
                'username' => 'Musuh_Jahat',
                'access' => 2,
                'protect' => 0,
                'alliance' => 0
            ]];
        }

        // 7. Retaliation check
        if (strpos($sql, "WHERE `from` = 101 AND `to` = 777") !== false) {
            return []; // Not yet retaliated
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

global $database;
$database = new MockDatabaseRevenge();

// Bot setup
$bot = [
    'uid' => 12,
    'username' => 'Bot_Avenger',
    'tribe' => 1,
    'wref' => 101,
    'pop' => 300,
    'alliance' => 5
];

// Test 1: Channel A - Active incoming attack counter-strike
$res = BotAI::processAttack($bot);
assertTrue($res !== null, "processAttack must return non-null result for revenge");
assertTrue(strpos($res, "[BALAS DENDAM ALIANSI / REVENGE]") !== false, "Must contain [BALAS DENDAM ALIANSI / REVENGE] prefix");
assertTrue(strpos($res, "Musuh_Jahat") !== false, "Must target enemy username Musuh_Jahat");
assertTrue(strpos($res, "Markas Musuh") !== false, "Must target enemy village Markas Musuh");

assertEqual(1, count($database->addedAttacks), "Must create 1 attack record");
assertEqual(4, $database->addedAttacks[0]['type'], "Revenge attack type defaults to Raid (4) to prevent army extinction");

assertEqual(1, count($database->addedMovements), "Must create 1 movement record");
assertEqual(101, $database->addedMovements[0]['from'], "From must be 101");
assertEqual(777, $database->addedMovements[0]['to'], "To must be enemy 777");

// Test 2: Distance limit configuration check
define('BOT_AI_REVENGE_MAX_DISTANCE', 15.0); // Target at distance ~28.2 tiles will exceed 15.0
$resFar = BotAI::processAttack($bot);
// Distance between (10,20) and (30,40) is sqrt(20^2 + 20^2) = 28.28 tiles > 15.0 max distance
// Since revenge candidate is skipped due to distance and it's outside daytime or random roll, regular raid returns null
assertEqual(null, $resFar, "When target distance exceeds BOT_AI_REVENGE_MAX_DISTANCE, revenge candidate is skipped");

echo "PASS BotAllianceRevengeTest passed successfully!\n";
