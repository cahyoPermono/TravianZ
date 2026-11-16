<?php
/**
 * Test Suite: Bot AI Karhutla Weather Fog-of-War & Speed Integration Test
 *
 * Verifies:
 * 1. Under normal weather (CLEAR), incoming attacks > 10m away are visible and bot dispatches reinforcement.
 * 2. Under Karhutla (Wildfire Smoke Haze, type 6):
 *    - Attacks > 10m (e.g. 15m / 900s) away are hidden by smoke haze: bot cannot see them and skips reinforcement.
 *    - Attacks <= 10m (e.g. 8m / 480s) away become visible through the haze: bot detects and dispatches reinforcement.
 * 3. Bot walking speed is modified by weather speed multipliers (0.85x for normal tribes, 0.95x for Nusantara).
 */

require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Weather.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

if (!defined('TB_PREFIX')) define('TB_PREFIX', 's1_');
if (!defined('INCREASE_SPEED')) define('INCREASE_SPEED', 1);
if (!defined('WORLD_MAX')) define('WORLD_MAX', 400);

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

class MockDatabaseKarhutla {
    public $calls = [];
    public $incomingAttackTime = 900; // default 15 minutes away

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
                'u1' => 100, // Legionnaire
                'u2' => 100, // Praetorian
                'u3' => 0,
                'u4' => 0,
                'u5' => 0,
                'u6' => 0,
                'u7' => 0,
                'u8' => 0,
                'u9' => 0,
                'u10' => 0,
                'hero' => 0
            ]];
        }

        // 2. Active outgoing reinforcements
        if (strpos($sql, "active_reinf") !== false) {
            return [['active_reinf' => 0]];
        }

        // 3. Search for active incoming attacks on ally village
        if (strpos($sql, "u.alliance = 5") !== false && strpos($sql, "m.sort_type = 3") !== false && strpos($sql, "a.attack_type IN (3, 4)") !== false) {
            return [[
                'moveid' => 999,
                'target_wid' => 202,
                'attacker_wid' => 888,
                'endtime' => time() + $this->incomingAttackTime,
                'vname' => 'Desa Sekutu',
                'pop' => 300,
                'x' => 12,
                'y' => 22,
                'ally_username' => 'Teman_Bot',
                'ally_uid' => 55,
                'attack_type' => 3
            ]];
        }

        // 4. Duplicate / Stationed checks
        if (strpos($sql, "m.`from` = 101 AND m.`to` = 202") !== false) return [];
        if (strpos($sql, "FROM " . TB_PREFIX . "enforcement WHERE vref = 202 AND `from` = 101") !== false) return [];
        if (strpos($sql, "total_marching") !== false) return [['total_marching' => 0]];

        return [];
    }

    public function modifyUnit($wid, $unitNums, $amounts, $modes) { return true; }
    public function addAttack($from, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $hero, $type, $ctar1, $ctar2, $spymode) { return 12345; }
    public function addMovement($type, $from, $to, $ref, $starttime, $endtime) { return 67890; }
    public function sendPendingMessages() { return true; }
}

echo "=== Running Bot AI Karhutla Weather Integration Tests ===\n\n";

global $database;
$database = new MockDatabaseKarhutla();

$botRoman = [
    'uid' => 12,
    'wref' => 101,
    'tribe' => 1,
    'alliance' => 5,
    'pop' => 300,
    'username' => 'Bot_Defender'
];

// -------------------------------------------------------------------------
// TEST 1: Normal Weather (CLEAR) -> Attack 15 mins away (900s) IS visible
// -------------------------------------------------------------------------
echo "[Test 1] Testing normal weather (CLEAR): attack 15m away is visible and reinforced... ";
Weather::generateNextWeather('global', Weather::TYPE_CLEAR);
$database->incomingAttackTime = 900; // 15 mins away
$res1 = BotAI::processAllianceDefense($botRoman);
assertTrue($res1 !== null, "Bot must detect and reinforce ally under clear weather");
echo "PASSED!\n";

// -------------------------------------------------------------------------
// TEST 2: Karhutla (Wildfire Haze) -> Attack 15 mins away (900s > 600s) is MASKED
// -------------------------------------------------------------------------
echo "[Test 2] Testing Karhutla weather: attack 15m away (>10m) is masked by smoke haze... ";
Weather::generateNextWeather('global', Weather::TYPE_KARHUTLA);
$database->incomingAttackTime = 900; // 15 mins away
$res2 = BotAI::processAllianceDefense($botRoman);
assertTrue($res2 === null, "Bot must NOT detect attack masked by Karhutla haze (arrival > 10m away)");
echo "PASSED (Bot blind to attack until it enters <= 10m radar)!\n";

// -------------------------------------------------------------------------
// TEST 3: Karhutla (Wildfire Haze) -> Attack 8 mins away (480s <= 600s) is VISIBLE
// -------------------------------------------------------------------------
echo "[Test 3] Testing Karhutla weather: attack 8m away (<=10m) becomes visible and reinforced... ";
$database->incomingAttackTime = 480; // 8 mins away
$res3 = BotAI::processAllianceDefense($botRoman);
assertTrue($res3 !== null, "Bot must detect and reinforce ally once attack enters <= 10m detection window under Karhutla");
echo "PASSED!\n";

// -------------------------------------------------------------------------
// TEST 4: Weather Speed Multipliers in BotAI
// -------------------------------------------------------------------------
echo "[Test 4] Testing BotAI weather speed multiplier integration... ";
Weather::generateNextWeather('global', Weather::TYPE_KARHUTLA);
assertEqual(0.85, BotAI::getWeatherSpeedMultiplier(1), "Roman troops slowed to 85% speed under Karhutla");
assertEqual(0.95, BotAI::getWeatherSpeedMultiplier(10), "Nusantara troops resist Karhutla haze with 95% speed");

Weather::generateNextWeather('global', Weather::TYPE_CLEAR);
assertEqual(1.05, BotAI::getWeatherSpeedMultiplier(1), "Roman troops get 1.05x clear weather bonus");
echo "PASSED!\n";

// Restore normal weather
Weather::generateNextWeather('global', Weather::TYPE_CLEAR);

echo "\n✅ ALL BOT AI KARHUTLA WEATHER INTEGRATION TESTS PASSED!\n";
