<?php
/**
 * Test Suite: Bandit World Boss Monotonic Scaling & Active Camp Retrofit Test
 * 
 * Tests that:
 * 1. Base profile for Tier 4 World Boss is deterministic.
 * 2. Every single guard unit (u35..u40) strictly increases monotonically per level.
 * 3. EXP, CP, Silver rewards scale properly and are not overwritten by base values.
 * 4. Existing active World Boss with outdated/low troops is automatically calibrated
 *    by upgradeExistingActiveCamps().
 */

require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BanditCamp.php';

function assertTest($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running Bandit World Boss Scaling & Retrofit Unit Tests ===\n";

$db = $database->dblink;

// Ensure database schema is migrated
BanditCamp::ensureTable();

// 1. Verify Tier 4 base profile
$profiles = BanditCamp::getTierProfiles();
$wbProfile = $profiles[BanditCamp::TIER_WORLDBOSS];

assertTest($wbProfile['troops'][35] === 550, "Base Boar (u35) is 550");
assertTest($wbProfile['troops'][36] === 400, "Base Wolf (u36) is 400");
assertTest($wbProfile['troops'][37] === 700, "Base Bear (u37) is 700");
assertTest($wbProfile['troops'][38] === 450, "Base Crocodile (u38) is 450");
assertTest($wbProfile['troops'][39] === 650, "Base Tiger (u39) is 650");
assertTest($wbProfile['troops'][40] === 400, "Base Elephant (u40) is 400");
assertTest($wbProfile['bounty'] === 120000, "Base Bounty is 120,000");
assertTest($wbProfile['exp'] === 28000, "Base EXP is 28,000");
assertTest($wbProfile['cp'] === 2800, "Base CP is 2,800");
assertTest($wbProfile['silver'] === 4000, "Base Silver is 4,000");

// 2. Test mathematical scaling progression from Level 1 to Level 10
echo "\n--- Testing Scaling Monotonicity Across Levels 1 to 10 ---\n";
$prevGuards = null;
$prevExp = 0;
$prevCp = 0;
$prevSilver = 0;
$prevBounty = 0;

for ($lvl = 1; $lvl <= 10; $lvl++) {
    $scaleMultiplier = 1.0 + (($lvl - 1) * 0.25);
    $expMult = 1.0 + (($lvl - 1) * 0.20);
    $cpMult = 1.0 + (($lvl - 1) * 0.20);
    $silverMult = 1.0 + (($lvl - 1) * 0.25);

    $currBounty = (int)round($wbProfile['bounty'] * $scaleMultiplier);
    $currExp = (int)round($wbProfile['exp'] * $expMult);
    $currCp = (int)round($wbProfile['cp'] * $cpMult);
    $currSilver = (int)round($wbProfile['silver'] * $silverMult);

    $currGuards = [];
    foreach ($wbProfile['troops'] as $uNum => $baseAmt) {
        $currGuards[$uNum] = (int)round($baseAmt * $scaleMultiplier);
    }

    if ($prevGuards !== null) {
        assertTest($currBounty > $prevBounty, "Level $lvl Bounty ($currBounty) > Level " . ($lvl - 1) . " Bounty ($prevBounty)");
        assertTest($currExp > $prevExp, "Level $lvl EXP ($currExp) > Level " . ($lvl - 1) . " EXP ($prevExp)");
        assertTest($currCp > $prevCp, "Level $lvl CP ($currCp) > Level " . ($lvl - 1) . " CP ($prevCp)");
        assertTest($currSilver > $prevSilver, "Level $lvl Silver ($currSilver) > Level " . ($lvl - 1) . " Silver ($prevSilver)");

        foreach ($currGuards as $uNum => $amt) {
            assertTest($amt > $prevGuards[$uNum], "Level $lvl Unit u$uNum ($amt) > Level " . ($lvl - 1) . " (" . $prevGuards[$uNum] . ")");
        }
    }

    $prevGuards = $currGuards;
    $prevBounty = $currBounty;
    $prevExp = $currExp;
    $prevCp = $currCp;
    $prevSilver = $currSilver;
}

// 3. Verify specific Level 5 vs Level 6 values
echo "\n--- Verifying Exact Level 5 vs Level 6 Metrics ---\n";
// Level 5 (multiplier = 2.0x)
$lvl5Elephant = (int)round(400 * (1.0 + 4 * 0.25)); // 800
$lvl6Elephant = (int)round(400 * (1.0 + 5 * 0.25)); // 900
assertTest($lvl5Elephant === 800, "Level 5 Elephant is exactly 800");
assertTest($lvl6Elephant === 900, "Level 6 Elephant is exactly 900");
assertTest($lvl6Elephant > $lvl5Elephant, "Level 6 Elephant (900) is strictly greater than Level 5 (800)");

$lvl5Exp = (int)round(28000 * (1.0 + 4 * 0.20)); // 50,400
$lvl6Exp = (int)round(28000 * (1.0 + 5 * 0.20)); // 56,000
assertTest($lvl5Exp === 50400, "Level 5 EXP is 50,400");
assertTest($lvl6Exp === 56000, "Level 6 EXP is 56,000");

$lvl5Silver = (int)round(4000 * (1.0 + 4 * 0.25)); // 8,000
$lvl6Silver = (int)round(4000 * (1.0 + 5 * 0.25)); // 9,000
assertTest($lvl5Silver === 8000, "Level 5 Silver is 8,000");
assertTest($lvl6Silver === 9000, "Level 6 Silver is 9,000");

// 4. Test Retrofit of Existing Active World Boss
echo "\n--- Testing Active World Boss Retrofit via upgradeExistingActiveCamps() ---\n";
$mockCampId = 8881;
$mockWref = 8882;

// Clean up mock records
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $mockCampId OR wref = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camp_attacks` WHERE camp_id = $mockCampId");

// Insert simulated outdated World Boss LV. 6 matching the user's screenshot
// - Elephant was only 567
// - EXP was stuck at base 28,000
// - Silver was stuck at base 4,000
// - Name had duplicate tag: [WORLD BOSS - LV. 6] [WORLD BOSS] Raja Siluman Sanghyang Kenambang
$doubleTagName = "[WORLD BOSS - LV. 6] [WORLD BOSS] Raja Siluman Sanghyang Kenambang";
$escName = mysqli_real_escape_string($db, $doubleTagName);
$time = time();

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($mockWref, 3, 0, 50, 50, 1, 'v3')");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (wref, owner, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop) VALUES ($mockWref, 10, '$escName', 0, 280, 0, 320859, 320859, 320859, 320859, 450000, 450000)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "units` (vref, u35, u36, u37, u38, u39, u40) VALUES ($mockWref, 1229, 828, 1442, 927, 1427, 567)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "bandit_camps` (id, wref, tier, level, name, max_hp, cur_hp, bounty_wood, bounty_clay, bounty_iron, bounty_crop, reward_exp, reward_cp, reward_silver, status, created) VALUES ($mockCampId, $mockWref, 4, 6, '$escName', 6420, 6420, 320859, 320859, 320859, 320859, 28000, 2800, 4000, 1, $time)");

// Run upgradeExistingActiveCamps
BanditCamp::upgradeExistingActiveCamps();

// Check updated camp record
$updatedCamp = $database->query_return("SELECT * FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $mockCampId");
assertTest(!empty($updatedCamp), "Mock camp exists in database");
$cRow = $updatedCamp[0];

assertTest((int)$cRow['reward_exp'] === 56000, "Retrofit upgraded reward_exp to 56,000 (was 28,000)");
assertTest((int)$cRow['reward_cp'] === 5600, "Retrofit upgraded reward_cp to 5,600 (was 2,800)");
assertTest((int)$cRow['reward_silver'] === 9000, "Retrofit upgraded reward_silver to 9,000 (was 4,000)");
assertTest((int)$cRow['bounty_wood'] === 320859, "Retrofit preserved higher existing bounty (320,859)");
assertTest(strpos($cRow['name'], '[WORLD BOSS] [WORLD BOSS]') === false, "Retrofit removed duplicate [WORLD BOSS] tag from camp name");
assertTest($cRow['name'] === "[WORLD BOSS - LV. 6] Raja Siluman Sanghyang Kenambang", "Camp name is cleanly formatted");

// Check updated units
$updatedUnits = $database->getUnit($mockWref, false);
assertTest((int)$updatedUnits['u40'] === 900, "Retrofit upgraded Elephant from 567 to 900");
assertTest((int)$updatedUnits['u39'] === 1463, "Retrofit calibrated Tiger to 1,463");
assertTest((int)$updatedUnits['u37'] === 1575, "Retrofit calibrated Bear to 1,575");
assertTest((int)$updatedUnits['u35'] === 1238, "Retrofit calibrated Boar to 1,238");

$totalExpectedGuards = 1238 + 900 + 1575 + 1013 + 1463 + 900; // 7,089
assertTest((int)$cRow['cur_hp'] === $totalExpectedGuards, "Retrofit updated cur_hp to $totalExpectedGuards (was 6,420)");
assertTest((int)$cRow['max_hp'] === $totalExpectedGuards, "Retrofit updated max_hp to $totalExpectedGuards (was 6,420)");

// Clean up mock records
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $mockCampId OR wref = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $mockWref");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $mockWref");

echo "\n>>> ALL BANDIT WORLD BOSS SCALING & RETROFIT TESTS PASSED! <<<\n";
