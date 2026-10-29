<?php

#################################################################################
##  Filename       : BotAllianceDefenseConfigTest.php                          ##
##  Type           : Unit Test for Dynamic Admin Alliance Defense Configuration ##
#################################################################################

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

// -----------------------------------------------------------------------------
// Test 1: Config Reading and Default Values
// -----------------------------------------------------------------------------
$enabled = BotAI::getConfig('BOT_AI_ALLIANCE_DEFENSE_ENABLED', null);
assertEqual(true, $enabled, "BOT_AI_ALLIANCE_DEFENSE_ENABLED should be true by default in config");

$ratioActive = BotAI::getConfig('BOT_AI_DEFENSE_RATIO_ACTIVE', 0.0);
assertEqual(0.75, (float)$ratioActive, "BOT_AI_DEFENSE_RATIO_ACTIVE should be 0.75");

$ratioRecovery = BotAI::getConfig('BOT_AI_DEFENSE_RATIO_RECOVERY', 0.0);
assertEqual(0.50, (float)$ratioRecovery, "BOT_AI_DEFENSE_RATIO_RECOVERY should be 0.50");

$maxCapActive = BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP', 0);
assertEqual(350, (int)$maxCapActive, "BOT_AI_DEFENSE_MAX_CAP should be 350");

$maxCapRecovery = BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP_RECOVERY', 0);
assertEqual(150, (int)$maxCapRecovery, "BOT_AI_DEFENSE_MAX_CAP_RECOVERY should be 150");

$maxHelpers = BotAI::getConfig('BOT_AI_MAX_HELPERS_PER_VILLAGE', 0);
assertEqual(6, (int)$maxHelpers, "BOT_AI_MAX_HELPERS_PER_VILLAGE should be 6");

$pureDefOnly = BotAI::getConfig('BOT_AI_DEFENSE_PURE_DEF_ONLY', false);
assertEqual(true, (bool)$pureDefOnly, "BOT_AI_DEFENSE_PURE_DEF_ONLY should be true");

$maxDistance = BotAI::getConfig('BOT_AI_MAX_DEFENSE_DISTANCE', 0.0);
assertEqual(120.0, (float)$maxDistance, "BOT_AI_MAX_DEFENSE_DISTANCE should be 120.0");

echo "✓ Test 1: Config defaults verified successfully.\n";

// -----------------------------------------------------------------------------
// Test 2: Save & Reload Config
// -----------------------------------------------------------------------------
$originalCap = (int)BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP', 350);

// Temporarily change max cap to 500
$saveRes = BotAI::saveConfig(['BOT_AI_DEFENSE_MAX_CAP' => 500]);
assertTrue($saveRes, "saveConfig must succeed");
assertEqual(500, (int)BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP', 0), "BOT_AI_DEFENSE_MAX_CAP updated to 500");

// Restore original
BotAI::saveConfig(['BOT_AI_DEFENSE_MAX_CAP' => $originalCap]);
assertEqual($originalCap, (int)BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP', 0), "BOT_AI_DEFENSE_MAX_CAP restored to original");

echo "✓ Test 2: Save & reload config verified successfully.\n";

// -----------------------------------------------------------------------------
// Test 3: Teuton Pure Defense Filtering (No Clubswingers or Axemen)
// -----------------------------------------------------------------------------
class MockDatabaseTeutonDefense {
    public $addedAttacks = [];
    public $addedMovements = [];
    public $modifiedUnits = [];

    public function escape($str) { return addslashes($str); }
    public function getCoor($wid) { return ['x' => 10, 'y' => 20]; }

    public function query_return($sql) {
        // Fetch Teuton troops at home:
        // u11 = Clubswinger (100) - Basic / Fragile
        // u12 = Spearman (300) - Specialist Inf Def
        // u13 = Axeman (80) - Offensive
        // u15 = Paladin (100) - Specialist Cav Light Def
        if (strpos($sql, "FROM " . TB_PREFIX . "units WHERE vref = 102") !== false) {
            return [[
                'u11' => 100,
                'u12' => 300,
                'u13' => 80,
                'u14' => 0,
                'u15' => 100,
                'u16' => 0,
                'u17' => 0,
                'u18' => 0,
                'u19' => 0,
                'u20' => 0,
                'hero' => 0
            ]];
        }

        // Active outgoing reinforcement check
        if (strpos($sql, "m.`from` = 102 AND m.sort_type = 3 AND m.proc = 0 AND a.attack_type = 2") !== false) {
            return [['active_reinf' => 0]];
        }

        // Active incoming attack on ally
        if (strpos($sql, "u.alliance = 5") !== false && strpos($sql, "m.sort_type = 3") !== false) {
            return [[
                'moveid' => 1001,
                'target_wid' => 202,
                'attacker_wid' => 999,
                'endtime' => time() + 600,
                'vname' => 'Desa Julian_R',
                'pop' => 450,
                'x' => 12,
                'y' => 24,
                'ally_username' => 'Julian_R',
                'ally_uid' => 60,
                'attack_type' => 3
            ]];
        }

        // Duplicate check
        if (strpos($sql, "m.`from` = 102 AND m.`to` = 202") !== false) return [];
        if (strpos($sql, "FROM " . TB_PREFIX . "enforcement WHERE vref = 202 AND `from` = 102") !== false) return [];
        if (strpos($sql, "m.`to` = 202 AND m.sort_type = 3") !== false) return [['total_marching' => 0]];
        if (strpos($sql, "FROM " . TB_PREFIX . "hero WHERE uid = 15") !== false) return [];

        return [];
    }

    public function query($sql) { return true; }

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
$database = new MockDatabaseTeutonDefense();

$botTeuton = [
    'uid' => 15,
    'username' => 'Bot_Teuton_Ally',
    'tribe' => 2, // Teuton
    'wref' => 102,
    'pop' => 350,
    'alliance' => 5
];

$report = BotAI::processAllianceDefense($botTeuton);
assertTrue($report !== null, "Teuton bot should dispatch alliance defense");
assertTrue(count($database->addedAttacks) === 1, "Must create 1 attack record");

$attackTroops = $database->addedAttacks[0]['troops'];
// Troops array index 0..9 correspond to u11..u20 for Teutons
$tClubswinger = $attackTroops[0]; // u11
$tSpearman    = $attackTroops[1]; // u12
$tAxeman      = $attackTroops[2]; // u13
$tPaladin     = $attackTroops[4]; // u15

assertEqual(0, $tClubswinger, "Clubswingers (u11) must NOT be sent when pure defense mode is active!");
assertEqual(0, $tAxeman, "Axemen (u13) must NOT be sent when pure defense mode is active!");
assertTrue($tSpearman > 0, "Spearmen (u12) must be sent as specialist infantry defense!");
assertTrue($tPaladin > 0, "Paladins (u15) must be sent as cavalry defense!");

$totalSent = array_sum($attackTroops);
assertTrue($totalSent >= 200, "With 75% active ratio and 350 cap, Teuton should send substantially more than 100 troops (sent: $totalSent)");
assertTrue($totalSent <= 350, "Total sent ($totalSent) must not exceed active cap of 350 for pop <= 400");

echo "✓ Test 3: Teuton pure defense filtering and scaled dispatch passed successfully!\n";

// -----------------------------------------------------------------------------
// Test 4: Mature Bot Dynamic Bonus Cap (> 400 pop)
// -----------------------------------------------------------------------------
class MockDatabaseTeutonMatureDefense extends MockDatabaseTeutonDefense {
    public function query_return($sql) {
        // Mature village has 600 pop and 700 defensive troops (500 Spearmen, 200 Paladins)
        if (strpos($sql, "FROM " . TB_PREFIX . "units WHERE vref = 103") !== false) {
            return [[
                'u11' => 100,
                'u12' => 500, // Spearman (inf_def)
                'u13' => 80,
                'u14' => 0,
                'u15' => 200, // Paladin (cav_light)
                'u16' => 0,
                'u17' => 0,
                'u18' => 0,
                'u19' => 0,
                'u20' => 0,
                'hero' => 0
            ]];
        }
        if (strpos($sql, "m.`from` = 103 AND m.sort_type = 3") !== false) return [['active_reinf' => 0]];
        if (strpos($sql, "m.`from` = 103 AND m.`to` = 202") !== false) return [];
        if (strpos($sql, "FROM " . TB_PREFIX . "enforcement WHERE vref = 202 AND `from` = 103") !== false) return [];
        return parent::query_return($sql);
    }
}

$database = new MockDatabaseTeutonMatureDefense();
$botMature = [
    'uid' => 16,
    'username' => 'Bot_Mature_Ally',
    'tribe' => 2,
    'wref' => 103,
    'pop' => 600, // Pop 600 -> +100 bonus cap: max cap = 350 + 100 = 450
    'alliance' => 5
];

$reportMature = BotAI::processAllianceDefense($botMature);
assertTrue($reportMature !== null, "Mature bot should dispatch defense");
$matureSent = array_sum($database->addedAttacks[0]['troops']);
// Home reserve for 600 pop: 60 + (300 * 0.35) = 165. Total def: 700. Deployable: 535.
// 75% of 535 = 401. Max cap = 350 + 100 = 450.
assertTrue($matureSent > 350, "Mature bot sent $matureSent troops (must exceed standard cap of 350 due to dynamic bonus)");
assertTrue($matureSent <= 450, "Mature bot cap with 600 pop should allow up to 450 troops (sent: $matureSent)");

echo "✓ Test 4: Mature bot dynamic bonus cap passed successfully!\n";

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
