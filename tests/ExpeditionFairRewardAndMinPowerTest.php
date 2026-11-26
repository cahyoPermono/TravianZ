<?php
/**
 * Standalone Unit Test: Fair Co-op Expedition Reward & 10% Camp Defense Minimum Power
 *
 * Verifies:
 * 1. calculateCampDefensePower() & getRequiredMinimumPower() compute 10% of actual camp defense power
 *    (not 5% of unit count cur_hp), including troops + hero power.
 * 2. calculateRewardDistribution() uses 15% Conditional Synergy (>=10% damage) + 85% Damage Proportional.
 * 3. Contributors < 10% receive 0 from the 15% synergy pool and 0 leftover World Essence.
 * 4. World Essence uses Largest Remainder without guaranteed 1 pcs for low contributors.
 */

require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Expedition/ExpeditionEngine.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "[FAILED] $message\n");
        exit(1);
    }
    echo "[PASSED] $message\n";
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "[FAILED] $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n");
        exit(1);
    }
    echo "[PASSED] $message\n";
}

// Minimal DB mock for World Boss Lv.1 units & Hero power
$database = new class {
    public $dblink = null;

    public function getUnit($wref, $mode = false) {
        if ($wref == 5001) {
            // World Boss Lv.1 profile: 550 Boar, 400 Wolf, 700 Bear, 450 Croc, 650 Tiger, 400 Elephant
            return [
                'u35' => 550,
                'u36' => 400,
                'u37' => 700,
                'u38' => 450,
                'u39' => 650,
                'u40' => 400,
            ];
        }
        return [];
    }

    public function getHero($uid) {
        if ($uid == 1) {
            // Hero with 50 attack points -> 100 + 50*80 = 4,100 power
            return [['uid' => 1, 'attack' => 50, 'dead' => 0]];
        }
        return [];
    }
};

echo "=== Running Fair Co-op Reward & Minimum Power Unit Tests ===\n";

// -------------------------------------------------------------------------
// Test 1: World Boss Defense Power & 10% Minimum Entry Requirement
// -------------------------------------------------------------------------
$wbDefPower = ExpeditionEngine::calculateCampDefensePower(5001, 3150);
// 550*51.5 + 400*75 + 700*170 + 450*310 + 650*210 + 400*480 = 645,325
assertEqual(645325, $wbDefPower, "World Boss Lv.1 total defense power must equal 645,325");

$reqPower = ExpeditionEngine::getRequiredMinimumPower(5001, 3150);
assertEqual(64533, $reqPower, "Minimum entry power for World Boss Lv.1 must be 10% (64,533)");

// Small leecher (4 Clubswingers = 160 power) MUST fail
$smallPower = ExpeditionEngine::calculateTroopsAttackPower(2, ['u11' => 4], false, 0);
$smallCheck = ExpeditionEngine::validateMinimumPower($smallPower, $wbDefPower);
assertTrue(!$smallCheck['valid'], "4 Clubswingers (160 power) must be rejected against World Boss");

// Troops + Hero combined meeting >= 64,533 MUST pass (e.g. 1520 Clubswingers = 60,800 + Hero 4,100 = 64,900)
$combinedPower = ExpeditionEngine::calculateTroopsAttackPower(2, ['u11' => 1520], true, 1);
assertEqual(64900, $combinedPower, "1520 Clubswingers + Hero(50 atk) must sum to 64,900 power");
$combinedCheck = ExpeditionEngine::validateMinimumPower($combinedPower, $wbDefPower);
assertTrue($combinedCheck['valid'], "Troops + Hero meeting >= 10% boss defense power must pass");

// -------------------------------------------------------------------------
// Test 2: Reward Split 15% Conditional Synergy + 85% Damage (With Leecher < 10%)
// -------------------------------------------------------------------------
$membersDamage = [
    1 => 80000, // 80% (eligible >= 10%)
    2 => 15000, // 15% (eligible >= 10%)
    3 => 5000,  //  5% (ineligible < 10%)
];
$poolTotal = [
    'wood' => 100000,
    'clay' => 100000,
    'iron' => 100000,
    'crop' => 100000,
    'silver' => 4000,
    'cp' => 1000,
    'exp' => 10000,
    'essence' => 8,
];

$dist = ExpeditionEngine::calculateRewardDistribution($membersDamage, $poolTotal, [1, 2, 3]);

// 15% Synergy = 15,000 split among 2 eligible (1 & 2) -> 7,500 each; member 3 gets 0
// 85% Damage  = 85,000 -> A: 68,000, B: 12,750, C: 4,250
assertEqual(75500, $dist[1]['wood'], "80% contributor gets 68,000 + 7,500 = 75,500 wood");
assertEqual(20250, $dist[2]['wood'], "15% contributor gets 12,750 + 7,500 = 20,250 wood");
assertEqual(4250,  $dist[3]['wood'], "5% contributor gets 0 synergy + 4,250 = 4,250 wood");
assertTrue($dist[1]['synergy_eligible'] && $dist[2]['synergy_eligible'], "15% and 80% contributors are synergy eligible");
assertTrue(!$dist[3]['synergy_eligible'], "5% contributor is NOT synergy eligible");

assertEqual(3020, $dist[1]['silver'], "80% contributor silver = 3,020");
assertEqual(810,  $dist[2]['silver'], "15% contributor silver = 810");
assertEqual(170,  $dist[3]['silver'], "5% contributor silver = 170");

// Essence (8 total): A=6.4, B=1.2, C=0.4 -> base 6+1+0=7, leftover 1 goes to eligible A (frac 0.4) -> 7, 1, 0
assertEqual(7, $dist[1]['essence'], "80% contributor gets 7 Essence");
assertEqual(1, $dist[2]['essence'], "15% contributor gets 1 Essence");
assertEqual(0, $dist[3]['essence'], "5% contributor gets 0 Essence (no guaranteed 1 pcs)");
assertEqual(8, $dist[1]['essence'] + $dist[2]['essence'] + $dist[3]['essence'], "Total allocated essence equals pool (8)");

// -------------------------------------------------------------------------
// Test 3: Largest Remainder when <10% contributor has largest fractional part
// -------------------------------------------------------------------------
$membersDamage2 = [
    1 => 75000, // 75% -> 6.00 essence
    2 => 18000, // 18% -> 1.44 essence (eligible)
    3 => 7000,  //  7% -> 0.56 essence (ineligible < 10%)
];
$dist2 = ExpeditionEngine::calculateRewardDistribution($membersDamage2, $poolTotal, [1, 2, 3]);
assertEqual(6, $dist2[1]['essence'], "75% contributor gets 6 Essence");
assertEqual(2, $dist2[2]['essence'], "18% eligible contributor receives leftover Essence instead of <10% contributor");
assertEqual(0, $dist2[3]['essence'], "7% contributor must NOT steal rounded-up Essence");

echo "\nAll Fair Co-op Reward & Minimum Power Tests Passed!\n";
