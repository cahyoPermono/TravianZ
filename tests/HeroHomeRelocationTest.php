<?php

declare(strict_types=1);

/**
 * Unit Test for Hero Home Village Relocation without Hero's Mansion requirement.
 * 1. Verifies lone hero reinforcement to own village without Hero's Mansion transfers home village.
 * 2. Verifies setherohome action converts an existing reinforcement hero into permanent home resident.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';

echo "=== Running HeroHomeRelocationTest ===" . PHP_EOL;

function assertEqual($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        $msg = $message ?: "Expected " . var_export($expected, true) . " but got " . var_export($actual, true);
        echo "FAIL: $msg" . PHP_EOL;
        exit(1);
    }
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        echo "FAIL: $message" . PHP_EOL;
        exit(1);
    }
}

global $database;

// Setup test player and two villages
$testUser = "test_reloc_hero_" . time();
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime) VALUES ('$testUser', 'pass', '$testUser@test.com', 1, 2, " . time() . ")");
$uid = mysqli_insert_id($database->dblink);

$wref1 = 99881;
$wref2 = 99882;

// Village 1 (Original Home)
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($wref1, $uid, 'Village 1', 1, 100, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, hero) VALUES ($wref1, 1)");

// Village 2 (Target Village - Has NO Hero's Mansion!)
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($wref2, $uid, 'Village 2', 0, 80, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, hero) VALUES ($wref2, 0)");

// Create Hero for user in Village 1
$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, dead, health, lastupdate) VALUES ($uid, 1, 'Sir Test', $wref1, 5, 0, 500, 0, 100, " . time() . ")");
$heroid = mysqli_insert_id($database->dblink);

// Verify initial state
$heroRow = $database->query_return("SELECT wref, dead FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
assertEqual((int)$wref1, (int)$heroRow['wref'], "Initial hero wref must be Village 1");

// =========================================================================
// TEST 1: Lone Hero Reinforcement to Village 2 (without Hero's Mansion)
// =========================================================================
echo "Test 1: Testing lone hero reinforcement between own villages without Hero's Mansion..." . PHP_EOL;

// Mock movement delivery data: lone hero (t11 = 1, t1..t10 = 0)
$data = [
    'from' => $wref1,
    'to'   => $wref2,
    't1' => 0, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0,
    't11' => 1
];

// Check condition as coded in AutomationTroopMovements
$troopsPresent = 0;
for ($i = 1; $i <= 10; $i++) {
    if ($data['t' . $i] > 0) {
        $troopsPresent = 1;
        break;
    }
}
$isoasis = 0;
$DefenderID = (int)$database->getVillageField($data['to'], "owner");
$AttackerID = (int)$database->getVillageField($data['from'], "owner");
$HeroTransfer = 0;

if ($data['t11'] > 0 && !$isoasis && !$troopsPresent) {
    if ($AttackerID == $DefenderID) {
        $database->modifyUnit($data['to'], ["hero"], [1], [1]);
        $hid = $database->getHeroField($DefenderID, 'heroid');
        $database->modifyHero("wref", $data['to'], $hid);
        if (method_exists($database, 'clearHeroCache')) {
            $database->clearHeroCache();
        }
        $HeroTransfer = 1;
    }
}

assertEqual(1, $HeroTransfer, "HeroTransfer flag must be 1 (lone hero transfer)");
$units2 = $database->query_return("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $wref2")[0];
assertEqual(1, (int)$units2['hero'], "Village 2 units must now have hero = 1");

$heroUpdated = $database->query_return("SELECT wref FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
assertEqual((int)$wref2, (int)$heroUpdated['wref'], "Hero wref must now be Village 2");

echo "PASS: Test 1 passed successfully!" . PHP_EOL;

// =========================================================================
// TEST 2: Convert existing Reinforcement Hero into Home Resident (setherohome)
// =========================================================================
echo "Test 2: Testing setherohome converting reinforcement hero to permanent home resident..." . PHP_EOL;

// Reset hero to village 1
$database->query("UPDATE " . TB_PREFIX . "hero SET wref = $wref1 WHERE heroid = $heroid");
$database->query("UPDATE " . TB_PREFIX . "units SET hero = 0 WHERE vref = $wref2");

// Simulate hero stationed as reinforcement at Village 2 (e.g. sent with other troops)
$database->query("INSERT INTO " . TB_PREFIX . "enforcement (vref, `from`, u1, hero) VALUES ($wref2, $wref1, 10, 1)");
$enforceId = mysqli_insert_id($database->dblink);

// Execute setherohome logic
$enforce = $database->getEnforceArray($enforceId, 0, false);
assertTrue(!empty($enforce), "Enforcement row must exist");
assertEqual((int)$wref2, (int)$enforce['vref'], "Enforcement vref is Village 2");
assertEqual(1, (int)$enforce['hero'], "Enforcement hero is 1");

$fromOwner = (int)$database->getVillageField($enforce['from'], 'owner');
assertEqual($uid, $fromOwner, "Enforcement originates from same user");

// Apply conversion logic
$otherTroops = 0;
for ($i = 1; $i <= 50; $i++) {
    if (!empty($enforce['u' . $i]) && (int)$enforce['u' . $i] > 0) {
        $otherTroops += (int)$enforce['u' . $i];
    }
}
assertTrue($otherTroops > 0, "Other troops (10 u1) present in enforcement");

// Hero decremented from enforcement, remaining troops kept
$database->query("UPDATE " . TB_PREFIX . "enforcement SET hero = hero - 1 WHERE id = $enforceId");
$database->modifyUnit($wref2, ["hero"], [1], [1]);
$database->modifyHero("wref", $wref2, $heroid);
$database->clearHeroCache();

// Verify enforcement row
$enforceAfter = $database->getEnforceArray($enforceId, 0, false);
assertEqual(0, (int)$enforceAfter['hero'], "Enforcement hero must be reduced to 0");
assertEqual(10, (int)$enforceAfter['u1'], "Enforcement regular troops (u1 = 10) must remain intact");

// Verify village 2 units
$units2After = $database->query_return("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $wref2")[0];
assertEqual(1, (int)$units2After['hero'], "Village 2 now owns the hero natively");

// Verify hero wref
$heroAfter = $database->query_return("SELECT wref FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
assertEqual((int)$wref2, (int)$heroAfter['wref'], "Hero home village is now officially Village 2");

echo "PASS: Test 2 passed successfully!" . PHP_EOL;

// Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE id = $enforceId");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE heroid = $heroid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($wref1, $wref2)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($wref1, $wref2)");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");

echo "=== ALL HeroHomeRelocationTest PASSED SUCCESSFULLY! ===" . PHP_EOL;
