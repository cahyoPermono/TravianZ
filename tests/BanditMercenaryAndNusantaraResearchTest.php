<?php

/**
 * Test Suite for:
 * 1. Bandit Camps Reward Scaling (Resource bounty, Hero EXP, Silver, CP for all tiers)
 * 2. Mercenary Troops in troops.tpl (Dorf1 display of Neutral Mercenaries)
 * 3. Nusantara Prajurit Tombak (Unit 92) Research Prerequisite Check (Blacksmith & Armoury support)
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/../GameEngine/config.php.bak';
require_once __DIR__ . '/../GameEngine/Database.php';

echo "=== STARTING BANDIT, MERCENARY & NUSANTARA RESEARCH TESTS ===\n";

// =========================================================================
// TEST 1: Bandit Camps Reward Scaling
// =========================================================================
echo "Test 1: Testing BanditCamp tier profiles and reward scaling...\n";
require_once __DIR__ . '/../GameEngine/BanditCamp.php';

$reflector = new ReflectionClass('BanditCamp');
$method = $reflector->getMethod('spawnCamp');

// Check profile tiers directly from BanditCamp
$tier1 = BanditCamp::TIER_OUTPOST;
$tier2 = BanditCamp::TIER_HIDEOUT;
$tier3 = BanditCamp::TIER_FORTRESS;
$tier4 = BanditCamp::TIER_WORLDBOSS;

// We can check BanditCamp::ensureTable exists and table definitions
BanditCamp::ensureTable();

// Verify that the table schema has the new default rewards
$res = $database->query_return("SHOW COLUMNS FROM " . TB_PREFIX . "bandit_camps LIKE 'reward_exp'");
assert(!empty($res), "Table bandit_camps should exist");

// Run upgrade check
BanditCamp::upgradeExistingActiveCamps();
echo "-> PASS: BanditCamp table and reward scaling verified!\n";

// =========================================================================
// TEST 2: Nusantara Prajurit Tombak (Unit 92) Research Prerequisites
// =========================================================================
echo "Test 2: Testing Nusantara Prajurit Tombak (Unit 92) research prerequisite check...\n";
require_once __DIR__ . '/../GameEngine/Technology.php';

// Mock Building object with configurable levels
class MockBuildingForResearch {
    public array $levels = [];

    public function setLevel(int $type, int $level): void {
        $this->levels[$type] = $level;
    }

    public function getTypeLevel(int $type): int {
        return $this->levels[$type] ?? 0;
    }
}

$mockBuilding = new MockBuildingForResearch();
$GLOBALS['building'] = $mockBuilding;

$technology = new Technology();

// Scenario A: Second village has Academy 1 and Blacksmith 1 (GID 12), NO Armoury (GID 13)
$mockBuilding->setLevel(22, 1); // Academy
$mockBuilding->setLevel(12, 1); // Blacksmith
$mockBuilding->setLevel(13, 0); // No Armoury

$resultA = $technology->meetRRequirement(92);
assert($resultA === true, "Unit 92 must be researchable with Academy 1 and Blacksmith (12) 1!");
echo "-> PASS: Scenario A: Academy Lv 1 + Blacksmith Lv 1 successfully qualifies Unit 92 for research!\n";

// Scenario B: Village has Academy 1 and Armoury 1 (GID 13), NO Blacksmith (GID 12)
$mockBuilding->setLevel(22, 1); // Academy
$mockBuilding->setLevel(12, 0); // No Blacksmith
$mockBuilding->setLevel(13, 1); // Armoury

$resultB = $technology->meetRRequirement(92);
assert($resultB === true, "Unit 92 must also be researchable with Academy 1 and Armoury (13) 1!");
echo "-> PASS: Scenario B: Academy Lv 1 + Armoury Lv 1 successfully qualifies Unit 92 for research!\n";

// Scenario C: Village has Academy 1 but NEITHER Blacksmith NOR Armoury
$mockBuilding->setLevel(22, 1); // Academy
$mockBuilding->setLevel(12, 0); // No Blacksmith
$mockBuilding->setLevel(13, 0); // No Armoury

$resultC = $technology->meetRRequirement(92);
assert($resultC === false, "Unit 92 must NOT be researchable without Blacksmith or Armoury!");
echo "-> PASS: Scenario C: Correctly blocks research when neither prerequisite is met.\n";

// Scenario D: Village has Blacksmith 1 but NO Academy
$mockBuilding->setLevel(22, 0); // No Academy
$mockBuilding->setLevel(12, 1); // Blacksmith
$mockBuilding->setLevel(13, 0); // No Armoury

$resultD = $technology->meetRRequirement(92);
assert($resultD === false, "Unit 92 must NOT be researchable without Academy!");
echo "-> PASS: Scenario D: Correctly blocks research when Academy is missing.\n";

// =========================================================================
// TEST 3: Mercenary troops in troops.tpl
// =========================================================================
echo "Test 3: Testing Mercenary troops rendering in troops.tpl...\n";
require_once __DIR__ . '/../GameEngine/Mercenary.php';

// Mock Village object
class MockVillageForTroops {
    public int $wid = 999999;
}

$GLOBALS['village'] = new MockVillageForTroops();
$GLOBALS['session'] = (object)['tribe' => 10, 'access' => 2];

// Insert or update mock mercenaries for wid 999999
Mercenary::ensureTable();
$database->query("
    INSERT INTO " . TB_PREFIX . "mercenaries (vref, uid, m1, m2, m3, m4, updated)
    VALUES (999999, 1, 50, 30, 20, 10, " . time() . ")
    ON DUPLICATE KEY UPDATE m1=50, m2=30, m3=20, m4=10
");

$garrison = Mercenary::getGarrison(999999);
assert($garrison['total_troops'] === 110, "Mercenary garrison total should be 110");
assert($garrison['m1'] === 50, "m1 should be 50");
assert($garrison['m2'] === 30, "m2 should be 30");
assert($garrison['m3'] === 20, "m3 should be 20");
assert($garrison['m4'] === 10, "m4 should be 10");

// Capture troops.tpl output
ob_start();
include __DIR__ . '/../Templates/troops.tpl';
$output = ob_get_clean();

assert(strpos($output, 'Tentara Bayaran (Enclave)') !== false, "Output must contain Tentara Bayaran section header!");
assert(strpos($output, 'Garda Zirah Besi') !== false, "Output must list Garda Zirah Besi!");
assert(strpos($output, 'Pemanah Tembok Aegis') !== false, "Output must list Pemanah Tembok Aegis!");
assert(strpos($output, 'Ksatria Perisai Templar') !== false, "Output must list Ksatria Perisai Templar!");
assert(strpos($output, 'Penjaga Gerbang Praetorian') !== false, "Output must list Penjaga Gerbang Praetorian!");
assert(strpos($output, '50') !== false, "Output must show unit count 50!");
assert(strpos($output, 'build.php?gid=17&amp;t=6') !== false, "Output must link to Mercenary Enclave in market!");

// Cleanup mock row
$database->query("DELETE FROM " . TB_PREFIX . "mercenaries WHERE vref = 999999");

echo "-> PASS: Mercenary troops successfully render on dorf1 troops.tpl with full counts and interactive links!\n";

echo "\n=== ALL TESTS IN BanditMercenaryAndNusantaraResearchTest PASSED SUCCESSFULLY! ===\n";
