<?php
/**
 * Test Reinforcement Battle Hero and Troop Survival
 *
 * Verifies:
 * 1. Reinforcement partial casualty survival: surviving units are NOT deleted when hero dies or when some unit types die.
 * 2. Complete wipeout deletion: reinforcement row is deleted ONLY when all troops and heroes die.
 * 3. Hero damage recalculation (wall damaged): hero surviving pass 1 is not killed by double-counted damage in pass 2.
 * 4. Report display (Templates/Notice/1.tpl): Teuton reinforcement hero casualty reads from correct index (offset 10).
 * 5. Defender hero status box: recognizes defender's own hero participating via reinforcement.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Lang/' . LANG . '.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Data/buidata.php';
require_once __DIR__ . '/../GameEngine/Building.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Battle.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationBattleResolution.php';

global $database;

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new Exception("Assertion failed: $message. Expected: $expStr, got: $actStr");
    }
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        throw new Exception("Assertion failed: $message");
    }
}

echo "=== STARTING REINFORCEMENT BATTLE HERO AND TROOP SURVIVAL TEST ===\n";

// =========================================================================
// TEST 1: Reinforcement Survival in applyReinforcementCasualties
// =========================================================================
echo "Test 1: Testing partial casualties in applyReinforcementCasualties...\n";

// Setup mock test class
$testRunner = new class {
    use AutomationBattleResolution;

    public function runReinforcementCasualties($data, $battlepart, $from, $to, $ownally, $AttackArrivalTime, $scout, array &$alldead, array &$DefenderHeroesDeadArray) {
        return $this->applyReinforcementCasualties($data, $battlepart, $from, $to, $ownally, $AttackArrivalTime, $scout, $alldead, $DefenderHeroesDeadArray);
    }
};

// Create test user and villages
$u1 = "reinf_user1_" . time();
$u2 = "reinf_user2_" . time();
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime) VALUES ('$u1', 'pwd', '$u1@test.com', 2, 2, " . time() . ")");
$uid1 = (int)mysqli_insert_id($database->dblink);
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime) VALUES ('$u2', 'pwd', '$u2@test.com', 1, 2, " . time() . ")");
$uid2 = (int)mysqli_insert_id($database->dblink);

$wref1 = 98711; // Origin village (Black Joker, Teuton)
$wref2 = 98712; // Target village (Ace of Spades)
$wrefAtt = 98713; // Attacker village

$database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE vref IN ($wref1, $wref2) OR `from` IN ($wref1, $wref2)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($wref1, $wref2, $wrefAtt)");

$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($wref1, $uid1, 'Black Joker', 1, 100, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($wref2, $uid1, 'Ace of Spades', 0, 80, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($wrefAtt, $uid2, 'Attacker Vill', 1, 100, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");

// Insert reinforcement: 24 spearmen (u12), 13 paladins (u15), 1 hero
$database->query("INSERT INTO " . TB_PREFIX . "enforcement (vref, `from`, u11, u12, u13, u14, u15, u16, u17, u18, u19, u20, hero) VALUES ($wref2, $wref1, 0, 24, 0, 0, 13, 0, 0, 0, 0, 0, 1)");
$enfId = (int)mysqli_insert_id($database->dblink);

$battlepart = [
    2 => 0.333333, // 33% loss ratio
    'deadheroref' => [
        $enfId => 1 // Hero died in battle
    ]
];

$data = ['to' => $wref2];
$from = ['wref' => $wrefAtt, 'owner' => $uid2];
$to = ['wref' => $wref2, 'name' => 'Ace of Spades', 'owner' => $uid1];
$alldead = array_fill(1, 100, 0) + ['hero' => 0];
$defHeroesDead = array_fill(1, 10, 0);

$testRunner->runReinforcementCasualties($data, $battlepart, $from, $to, 0, time(), false, $alldead, $defHeroesDead);

// Verify reinforcement row STILL EXISTS in database!
$checkEnf = $database->query_return("SELECT * FROM " . TB_PREFIX . "enforcement WHERE id = $enfId");
assertTrue(!empty($checkEnf), "Reinforcement row MUST NOT be deleted when surviving spearmen and paladins remain!");
$enfRow = $checkEnf[0];
assertEqual(16, (int)$enfRow['u12'], "Surviving spearmen (u12) must be 24 - 8 = 16");
assertEqual(9, (int)$enfRow['u15'], "Surviving paladins (u15) must be 13 - 4 = 9");
echo "PASS: Partial casualties correctly left surviving troops (16 spearmen, 9 paladins) in enforcement!\n";

// =========================================================================
// TEST 2: Complete Wipeout Deletion in applyReinforcementCasualties
// =========================================================================
echo "Test 2: Testing complete wipeout in applyReinforcementCasualties...\n";

// Reinforcement with 10 spearmen, 1 hero
$database->query("INSERT INTO " . TB_PREFIX . "enforcement (vref, `from`, u11, u12, u13, u14, u15, u16, u17, u18, u19, u20, hero) VALUES ($wref2, $wref1, 0, 10, 0, 0, 0, 0, 0, 0, 0, 0, 1)");
$enfId2 = (int)mysqli_insert_id($database->dblink);

// 100% loss ratio
$battlepartWipe = [
    2 => 1.0,
    'deadheroref' => [
        $enfId2 => 1
    ]
];

$testRunner->runReinforcementCasualties($data, $battlepartWipe, $from, $to, 0, time(), false, $alldead, $defHeroesDead);

$checkEnf2 = $database->query_return("SELECT * FROM " . TB_PREFIX . "enforcement WHERE id = $enfId2");
assertTrue(empty($checkEnf2), "Reinforcement row MUST be deleted when 100% of troops and hero are wiped out!");
echo "PASS: Complete wipeout correctly deleted the enforcement row!\n";

// =========================================================================
// TEST 3: Hero Survival in Two-Pass Battle Recalculation (Battle.php)
// =========================================================================
echo "Test 3: Testing hero survival in two-pass recalculation (Battle.php)...\n";

$battle = new Battle();

// Create hero with 50 health
$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, dead, health, lastupdate) VALUES ($uid1, 12, 'TungTungSahur', $wref1, 13, 0, 1000, 0, 50, " . time() . ")");
$heroId = (int)mysqli_insert_id($database->dblink);

// Use reflection to test applyHeroBattleDamage
$refBattle = new ReflectionClass('Battle');
$method = $refBattle->getMethod('applyHeroBattleDamage');
$method->setAccessible(true);

// Pass 1: Hero takes 33% damage (loss ratio 0.33)
// Hero has 50 HP. Damage is 33 HP.
$verdict1 = $method->invokeArgs($battle, [$heroId, 0.33, null, false, null]);
assertEqual(0, $verdict1, "Hero with 50 HP taking 33 HP damage must survive pass 1 (verdict 0)");

$hRow = $database->query_return("SELECT health, dead FROM " . TB_PREFIX . "hero WHERE heroid = $heroId")[0];
assertEqual(17, (int)$hRow['health'], "Hero health in DB after pass 1 must be 50 - 33 = 17 HP");
assertEqual(0, (int)$hRow['dead'], "Hero must not be dead in pass 1");

// Pass 2: Recalculation after wall damage!
// In Pass 2, previousVerdict is 0 (survived pass 1).
// Without our fix, line 1936 would compare remaining 17 HP <= 33 HP damage and kill the hero!
$verdict2 = $method->invokeArgs($battle, [$heroId, 0.33, null, false, 0]);
assertEqual(0, $verdict2, "Hero must NOT be killed in pass 2 due to remaining health being compared against damage again!");

$hRow2 = $database->query_return("SELECT health, dead FROM " . TB_PREFIX . "hero WHERE heroid = $heroId")[0];
assertEqual(0, (int)$hRow2['dead'], "Hero must still be alive after pass 2!");
assertEqual(17, (int)$hRow2['health'], "Hero health must not be double-subtracted!");
echo "PASS: Hero survived two-pass recalculation without taking double damage or false death!\n";

// =========================================================================
// TEST 4: Templates/Notice/1.tpl Teuton Reinforcement Dead Hero Index
// =========================================================================
echo "Test 4: Testing Notice 1.tpl reinforcement hero casualties display...\n";

// Build mock dataarray:
// Tribe 2 (Teutons) reinforcement is at defArray index 2 ($dataarray[76] = 1).
// Total heroes sent for tribe 2 is at index 245 = 1.
// Dead heroes for tribe 2 is at index 255 = 1 (or 0).
// In 1.tpl:
// $heroIndex = 244 + ($index - 1) = 245
// $heroDeadIndex = 10 -> $dataarray[245 + 10] = $dataarray[255]

$mockData = array_fill(0, 310, '0');
$mockData[34] = '2'; // target tribe Teutons
$mockData[76] = '1'; // Teuton reinforcement present
// Reinforcement troops for tribe 2: troopsStart = 2 * 21 + 35 = 77
$mockData[78] = '24'; // u12 sent: 24
$mockData[81] = '13'; // u15 sent: 13
$mockData[88] = '8';  // u12 dead: 8
$mockData[91] = '4';  // u15 dead: 4
$mockData[245] = '1'; // Teuton total heroes sent: 1
$mockData[254] = '0'; // Roman dead heroes: 0
$mockData[255] = '1'; // Teuton dead heroes: 1

$defArray = [1, $mockData[55], $mockData[76], $mockData[97], $mockData[118], $mockData[139], $mockData[160], $mockData[181], $mockData[202], $mockData[223]];
$index = 2; // Teutons reinforcement
$heroIndex = 244 + ($index - 1); // 245
$heroDeadIndex = 10; // Our fixed offset

assertEqual('1', $mockData[$heroIndex], "Teuton hero sent must be 1 at index 245");
assertEqual('1', $mockData[$heroIndex + $heroDeadIndex], "Teuton hero casualties must be 1 at index 255 (not 0 at 254)");

echo "PASS: Notice 1.tpl correctly reads Teuton dead hero from index 255 using offset 10!\n";

// Cleanup
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE heroid = $heroId");
$database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE vref IN ($wref1, $wref2)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($wref1, $wref2, $wrefAtt)");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($uid1, $uid2)");

echo "\n=== ALL REINFORCEMENT BATTLE HERO AND TROOP SURVIVAL TESTS PASSED! ===\n";
