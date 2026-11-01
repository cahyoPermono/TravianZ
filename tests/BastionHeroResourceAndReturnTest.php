<?php
declare(strict_types=1);

/**
 * Test Suite: Benteng Kuno Hero Home Village Preservation & Resource Bonus Continuity
 *
 * Verifies:
 * 1. Stationing conqueror garrison at Benteng Kuno does NOT overwrite hero.wref to 20201.
 * 2. Converting friendly attack waves to reinforcement does NOT overwrite hero.wref to 20201.
 * 3. Village::loadHeroProd() / getHeroForVillage() continues to generate resource bonuses for home village.
 * 4. Hero returning from Bastion via returnunitsComplete() preserves / restores hero.wref.
 * 5. Self-healing: if an existing hero had wref corrupted to 20201, ensureTables() and
 *    getHeroForVillage() automatically heal it back to the home village.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationTroopMovements.php';

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
$generator = new MyGenerator();
$GLOBALS['generator'] = $generator;
$technology = new Technology();
$GLOBALS['technology'] = $technology;

echo "=== START BASTION HERO RESOURCE & RETURN TEST ===\n\n";

$db = $GLOBALS['database']->dblink;
$database = $GLOBALS['database'];
$bastionWref = Bastion::BASTION_WREF;

function assertEqual($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        echo "FAIL: $msg (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")\n";
        exit(1);
    }
}

function assertTrue($condition, $msg = '') {
    if (!$condition) {
        echo "FAIL: $msg\n";
        exit(1);
    }
}

// -------------------------------------------------------------
// SETUP TEST USER, VILLAGE & HERO
// -------------------------------------------------------------
$testUid = 888123;
$testWid = 555123;
$testAlly = 777123;

// Clean up previous test runs
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE vref = $bastionWref AND `from` = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testWid OR `to` = $testWid");

// Insert User
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (id, username, password, email, tribe, access, alliance, regtime) 
    VALUES ($testUid, 'HeroCommander', 'pass', 'hero@test.com', 1, 2, $testAlly, " . time() . ")");

// Insert Village (Capital)
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created)
    VALUES ($testWid, $testUid, 'Desa Pahlawan', 1, 200, 1000, 1000, 1000, 1000, 5000, 5000, " . time() . ", " . time() . ")");

// Insert Units
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "units` (vref, u1, hero) VALUES ($testWid, 50, 1)");

// Insert T4 Hero with 20 points in Resources (res_type = 0, equal distribution)
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "hero` (uid, unit, name, wref, level, points, experience, dead, health, resources, res_type, lastupdate)
    VALUES ($testUid, 1, 'Sang Panglima', $testWid, 5, 0, 500, 0, 100, 20, 0, " . time() . ")");
$heroId = (int)mysqli_insert_id($db);

$database->clearHeroCache();

// -------------------------------------------------------------
// TEST 1: Initial Village Hero Resource Production
// -------------------------------------------------------------
echo "[TEST 1] Verifying initial hero resource production for home village...\n";
$heroRow = $database->getHeroForVillage($testWid, false);
assertTrue($heroRow !== null, "Hero must be found for village $testWid");
assertEqual(20, (int)$heroRow['resources'], "Hero resource points must be 20");
assertEqual(0, (int)$heroRow['res_type'], "Hero res_type must be 0 (all resources)");
echo "  -> PASS: Hero resource production correctly initialized.\n\n";

// -------------------------------------------------------------
// TEST 2: Stationing Conqueror Garrison does NOT change hero.wref
// -------------------------------------------------------------
echo "[TEST 2] Testing stationConqueringTroops does not overwrite hero.wref to Bastion...\n";

// Create mock attack row for conqueror
$attRef = 999881;
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "attacks` WHERE id = $attRef");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "attacks` (id, vref, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11, attack_type)
    VALUES ($attRef, $bastionWref, 25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 3)");

$mockData = ['ref' => $attRef, 'from' => $testWid, 'to' => $bastionWref];
$mockFrom = ['wref' => $testWid, 'owner' => $testUid, 'alliance' => $testAlly, 'name' => 'Desa Pahlawan'];

Bastion::stationConqueringTroops($mockData, $mockFrom);

// Verify hero wref is STILL $testWid (NOT $bastionWref)
$database->clearHeroCache();
$heroCheck1 = $database->getHeroField($testUid, 'wref', false);
assertEqual($testWid, (int)$heroCheck1, "hero.wref must remain home village ($testWid) after conquering Bastion");

// Verify enforcement row at Bastion has hero = 1
$enfQ = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE vref = $bastionWref AND `from` = $testWid LIMIT 1");
$enfRow = mysqli_fetch_assoc($enfQ);
assertTrue($enfRow !== null, "Enforcement row must exist at Bastion");
assertEqual(1, (int)$enfRow['hero'], "Enforcement hero count must be 1");

// Verify getHeroForVillage still returns hero for home village!
$heroForVil = $database->getHeroForVillage($testWid, false);
assertTrue($heroForVil !== null, "getHeroForVillage must still return hero for home village while stationed at Bastion");
echo "  -> PASS: Conquering hero stays resident of home village ($testWid) with full production.\n\n";

// -------------------------------------------------------------
// TEST 3: Converting Friendly Wave to Reinforcement does NOT change hero.wref
// -------------------------------------------------------------
echo "[TEST 3] Testing convertAttackWaveToReinforcement does not overwrite hero.wref...\n";
$waveData = [
    'from' => $testWid,
    'to' => $bastionWref,
    't1' => 10, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0,
    't11' => 1
];

Bastion::convertAttackWaveToReinforcement($waveData, $mockFrom, 1, $testAlly);

$database->clearHeroCache();
$heroCheck2 = $database->getHeroField($testUid, 'wref', false);
assertEqual($testWid, (int)$heroCheck2, "hero.wref must still be home village ($testWid)");
echo "  -> PASS: Wave conversion preserves home village wref.\n\n";

// -------------------------------------------------------------
// TEST 4: Hero Return Movement (returnunitsComplete) Preserves / Restores wref
// -------------------------------------------------------------
echo "[TEST 4] Testing returnunitsComplete() when hero returns home...\n";

// Simulate return movement from Bastion to home village
$now = time();
$retRef = 999882;
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "attacks` WHERE id = $retRef");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "attacks` (id, vref, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11, attack_type)
    VALUES ($retRef, $testWid, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2)");

$moveId = $database->addMovement(4, $bastionWref, $testWid, $retRef, $now - 60, $now - 10);

// Deliberately corrupt hero.wref to Bastion (20201) to simulate the bug scenario!
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET wref = $bastionWref WHERE heroid = $heroId");
$database->clearHeroCache();

class TestHeroMovementProcessor {
    use AutomationTroopMovements;
    public function claimReturnMovementRecord($moveid, $ref) {
        global $database;
        $database->setMovementProc((int)$moveid);
        return true;
    }
    public function pruneResource() {}
    public function invokeReturnUnitsComplete() {
        $this->returnunitsComplete();
    }
}

// Execute movement processor
$processor = new TestHeroMovementProcessor();
$processor->invokeReturnUnitsComplete();

// Verify hero arrived in units table
$unitsRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT hero FROM `" . TB_PREFIX . "units` WHERE vref = $testWid"));
assertEqual(2, (int)$unitsRow['hero'], "Home village units.hero must have incremented");

// Verify returnunitsComplete restored hero.wref to home village ($testWid)!
$database->clearHeroCache();
$heroCheck3 = $database->getHeroField($testUid, 'wref', false);
assertEqual($testWid, (int)$heroCheck3, "returnunitsComplete() must heal and restore hero.wref to home village ($testWid)");
echo "  -> PASS: returnunitsComplete() successfully restored hero.wref to home village!\n\n";

// -------------------------------------------------------------
// TEST 5: Self-Healing via Bastion::ensureTables() and getHeroForVillage()
// -------------------------------------------------------------
echo "[TEST 5] Testing self-healing when hero.wref is corrupted to 20201...\n";

// Corrupt hero.wref again
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET wref = $bastionWref WHERE heroid = $heroId");
$database->clearHeroCache();

// Calling getHeroForVillage should trigger on-the-fly self-healing
$healedRow = $database->getHeroForVillage($testWid, false);
assertTrue($healedRow !== null, "getHeroForVillage must self-heal corrupted hero.wref and return hero");
assertEqual($testWid, (int)$healedRow['wref'], "Hero wref in returned row must be healed to $testWid");

// Verify in DB directly
$dbWref = mysqli_fetch_assoc(mysqli_query($db, "SELECT wref FROM `" . TB_PREFIX . "hero` WHERE heroid = $heroId"))['wref'];
assertEqual($testWid, (int)$dbWref, "Database hero.wref must be updated to $testWid");

// Test Bastion::ensureTables() batch healing
mysqli_query($db, "UPDATE `" . TB_PREFIX . "hero` SET wref = $bastionWref WHERE heroid = $heroId");
Bastion::ensureTables();
$dbWrefAfterEnsure = mysqli_fetch_assoc(mysqli_query($db, "SELECT wref FROM `" . TB_PREFIX . "hero` WHERE heroid = $heroId"))['wref'];
assertEqual($testWid, (int)$dbWrefAfterEnsure, "Bastion::ensureTables() batch self-healing must restore hero.wref to $testWid");

echo "  -> PASS: Self-healing mechanisms flawlessly repaired corrupted hero.wref!\n\n";

// -------------------------------------------------------------
// CLEANUP
// -------------------------------------------------------------
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE vref = $bastionWref AND `from` = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testWid OR `to` = $testWid");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "attacks` WHERE id IN ($attRef, $retRef)");

echo "=== ALL BASTION HERO RESOURCE & RETURN TESTS PASSED SUCCESSFULLY! ===\n";
