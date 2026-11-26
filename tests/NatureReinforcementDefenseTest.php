<?php
/**
 * Test: Nature Reinforcement (from = 0, e.g. Caged Animals) Defense Contribution
 *
 * Verifies that Nature animals stationed as reinforcements with `from = 0`
 * (created by HeroBattleBonus::applyCages) contribute their full defense
 * points in Battle::calculateBattle() instead of being skipped.
 */

require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Battle.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "[FAILED] $message\n");
        exit(1);
    }
    echo "[PASSED] $message\n";
}

// Minimal DB mock covering Battle::calculateBattle() lookups
$database = new class {
    public $dblink = null;
    public $enforcements = [];

    public function getArtifactsValueInfluence($uid, $wref, $type, $effect, $flag) {
        return 1;
    }

    public function getVillageField($wref, $field) {
        if ($field === 'natar') {
            return 0;
        }
        if ($field === 'owner') {
            return ($wref == 200) ? 20 : (($wref == 300) ? 30 : 0);
        }
        return 0;
    }

    public function getUserArray($uid, $mode = 1) {
        if ($uid == 30) {
            return ['id' => 30, 'tribe' => 1]; // Roman ally
        }
        return ['id' => $uid, 'tribe' => 3];
    }

    public function getABTech($wref) {
        return ['a1' => 10, 'a2' => 10, 'a3' => 0, 'a4' => 0, 'a5' => 0, 'a6' => 0, 'a7' => 0, 'a8' => 0];
    }

    public function getVillage($uid, $mode = 0) {
        return false;
    }

    public function getEnforceVillage($wref, $mode = 0) {
        return $this->enforcements;
    }
};

$battle = new Battle();

// Helper to build empty u1..u100 array
$emptyUnits = [];
for ($i = 1; $i <= 100; $i++) {
    $emptyUnits['u' . $i] = 0;
}

echo "=== Running Nature Reinforcement Defense Regression Test ===\n";

// -------------------------------------------------------------------------
// Test 1: Exact scenario from bug report (6 Clubswingers vs 1 TT + 62 animals)
// -------------------------------------------------------------------------
$att = $emptyUnits;
$att['u11'] = 6; // 6 Teuton Clubswingers (atk = 40 * 6 = 240)

$def = $emptyUnits;
$def['u24'] = 1; // 1 Gaul Theutates Thunder (di = 25, dc = 40)
$def['hero'] = 0;

$natureReinf = $emptyUnits;
$natureReinf['id'] = 1;
$natureReinf['vref'] = 200;
$natureReinf['from'] = 0; // Nature reinforcement (caged animals)
$natureReinf['hero'] = 0;
$natureReinf['u31'] = 7;
$natureReinf['u32'] = 7;
$natureReinf['u33'] = 6;
$natureReinf['u34'] = 6;
$natureReinf['u35'] = 6;
$natureReinf['u36'] = 6;
$natureReinf['u37'] = 6;
$natureReinf['u38'] = 6;
$natureReinf['u39'] = 6;
$natureReinf['u40'] = 6;

$res = $battle->calculateBattle(
    $att,
    $def,
    0,          // wall
    2,          // att_tribe (Teuton)
    3,          // def_tribe (Gaul)
    0,          // residence
    100,        // attpop
    100,        // defpop
    4,          // type = 4 (Raid)
    [],         // def_ab
    0, 0, 0, 0, 0, 0, 0, 0, // att_ab1..8
    0, 0, 0,    // tblevel, stonemason, walllevel
    0, 0, 0,    // offhero, hero_strength, deffhero
    10, 20, 100, 200, 0,
    [$natureReinf]
);

assertTrue($res['Defend_points'] > 8700, "Defend_points must include all 62 animals (> 8700), got {$res['Defend_points']}");
assertTrue((int)$res['casualties_attacker'][1] === 6, "All 6 Clubswingers must die against 62 animals, got {$res['casualties_attacker'][1]}");
assertTrue((int)$res['bounty'] === 0, "Bounty must be 0 when all attackers die, got {$res['bounty']}");
assertTrue($res[2] < 0.01, "Defender casualty ratio must be < 1%, got {$res[2]}");
assertTrue((int)round($res[2] * 7) === 0 && (int)round($res[2] * 6) === 0, "Zero animals must die against 6 Clubswingers");

// -------------------------------------------------------------------------
// Test 2: Scout attack (type = 1) with from = 0 Nature reinforcement
// -------------------------------------------------------------------------
$scoutAtt = $emptyUnits;
$scoutAtt['u14'] = 5; // 5 Teuton Scouts

$scoutRes = $battle->calculateBattle(
    $scoutAtt,
    $emptyUnits + ['hero' => 0],
    0, 2, 3, 0, 100, 100, 1,
    [], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    10, 20, 100, 200, 0,
    [$natureReinf]
);
assertTrue(isset($scoutRes[1]) && $scoutRes[1] == 0, "Scout attack against nature animals without scouts must not fail or detect");

// -------------------------------------------------------------------------
// Test 3: Normal player reinforcement (from > 0) still works with armory & shares
// -------------------------------------------------------------------------
$playerReinf = $emptyUnits;
$playerReinf['id'] = 2;
$playerReinf['vref'] = 200;
$playerReinf['from'] = 300; // Roman ally village owned by uid 30
$playerReinf['hero'] = 0;
$playerReinf['u2'] = 100; // 100 Praetorians

$resWithAlly = $battle->calculateBattle(
    $att,
    $emptyUnits + ['hero' => 0],
    0, 2, 3, 0, 100, 100, 3,
    [], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    10, 20, 100, 200, 0,
    [$natureReinf, $playerReinf]
);
assertTrue(!empty($resWithAlly['DefensePointShares'][30]) && $resWithAlly['DefensePointShares'][30] > 6500, "Player reinforcement from>0 must still get armory-upgraded DP share");
assertTrue($resWithAlly['Defend_points'] > 15000, "Combined animals + Praetorians DP must exceed 15000, got {$resWithAlly['Defend_points']}");

echo "\nAll Nature Reinforcement Defense Tests Passed!\n";
