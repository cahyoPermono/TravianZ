<?php
/**
 * Test Suite: Multi-Village Embassy Demolition Logic
 *
 * Verifies that:
 * 1. An alliance leader with multiple embassies can demolish an embassy in village 2
 *    as long as village 1 still has a sufficient embassy (>= minEmbassyLevel).
 * 2. An alliance leader CANNOT demolish their only sufficient embassy below the required level.
 * 3. Instant demolition (to level 0) is permitted if another village holds a sufficient embassy,
 *    and blocked if no other village does.
 * 4. Regular members (non-leaders) can demolish their embassy in village 2.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';

global $database;
$db = $database->dblink;

echo "=== Running Embassy Demolition Multi-Village Tests ===\n\n";

$leaderUid = 8891;
$memberUid = 8892;
$v1 = 88901;
$v2 = 88902;
$aliId = 8899;

// Cleanup any old test records
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id IN ($leaderUid, $memberUid)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref IN ($v1, $v2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref IN ($v1, $v2)");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "alidata` WHERE id = $aliId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "demolition` WHERE vref IN ($v1, $v2)");

try {
    // 1. Create Leader and Member
    $time = time();
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `password`, `email`, `tribe`, `alliance`) VALUES ($leaderUid, 'TestLeader', 'pass', 'lead@test.com', 1, $aliId)");
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `password`, `email`, `tribe`, `alliance`) VALUES ($memberUid, 'TestMember', 'pass', 'mem@test.com', 1, $aliId)");

    // 2. Create Alliance with leader = $leaderUid
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "alidata` (`id`, `name`, `tag`, `leader`, `coor`, `desc`) VALUES ($aliId, 'Test Ali', 'TA', $leaderUid, 0, 'Test Desc')");

    // 3. Create 2 Villages for Leader
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`) VALUES ($v1, $leaderUid, 'Village 1', 1, 200)");
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`) VALUES ($v2, $leaderUid, 'Village 2', 0, 100)");

    // Desa 1: Embassy Level 10 at slot f20 (f20 = 10, f20t = 18)
    // Desa 2: Embassy Level 1 at slot f20 (f20 = 1, f20t = 18)
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "fdata` (`vref`, `f20`, `f20t`) VALUES ($v1, 10, 18)");
    mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "fdata` (`vref`, `f20`, `f20t`) VALUES ($v2, 1, 18)");

    $database->clearResourseLevelsCache();

    // Setup mock session
    global $session, $village, $building;
    $session = new stdClass();
    $session->uid = $leaderUid;
    $session->alliance = $aliId;

    $village = new stdClass();
    $village->wid = $v2;
    $village->owner = $leaderUid;
    $village->resarray = ['f20' => 1, 'f20t' => 18];

    $building = new class {
        public function resourceRequired($field, $type, $isMaster = 0) {
            return ['wood' => 10, 'clay' => 10, 'iron' => 10, 'crop' => 10, 'pop' => 1, 'time' => 100];
        }
    };

    // --- TEST 1: Demolish Embassy in Desa 2 when Desa 1 has Level 10 ---
    echo "[1] Testing canDemolishEmbassy in Desa 2 (Desa 1 has Embassy lvl 10)... ";
    $canClassic = $database->canDemolishEmbassy($leaderUid, $v2, 20, false);
    $canInstant = $database->canDemolishEmbassy($leaderUid, $v2, 20, true);

    if (!$canClassic) {
        throw new Exception("Expected canDemolishEmbassy (classic) in Desa 2 to be TRUE, got FALSE");
    }
    if (!$canInstant) {
        throw new Exception("Expected canDemolishEmbassy (instant) in Desa 2 to be TRUE, got FALSE");
    }
    echo "PASSED (both classic and instant allowed in Desa 2)\n";

    // --- TEST 2: addDemolition in Desa 2 ---
    echo "[2] Testing addDemolition execution on Desa 2... ";
    $addRes = $database->addDemolition($v2, 20);
    if ($addRes !== true) {
        throw new Exception("Expected addDemolition in Desa 2 to return TRUE, got: " . var_export($addRes, true));
    }
    $demRow = $database->getDemolition($v2);
    if (empty($demRow)) {
        throw new Exception("Expected demolition entry in database for Desa 2, none found");
    }
    echo "PASSED (demolition queued successfully)\n";

    // --- TEST 3: Cannot demolish primary embassy in Desa 1 below required level ---
    echo "[3] Testing primary embassy in Desa 1 protection... ";
    // Set Desa 1 to Level 3 (minimum required for leader)
    mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f20 = 3 WHERE vref = $v1");
    // Set Desa 2 to 0 (no embassy)
    mysqli_query($db, "UPDATE `" . TB_PREFIX . "fdata` SET f20 = 0, f20t = 0 WHERE vref = $v2");
    $database->clearResourseLevelsCache();

    $village->wid = $v1;
    $village->resarray = ['f20' => 3, 'f20t' => 18];

    // Classic from 3 -> 2 (below minLevel 3) should be blocked!
    $canFrom3to2 = $database->canDemolishEmbassy($leaderUid, $v1, 20, false);
    if ($canFrom3to2 !== false) {
        throw new Exception("Expected canDemolishEmbassy in Desa 1 (lvl 3 -> 2) to be FALSE, got TRUE");
    }

    // Instant should also be blocked!
    $canInstantDesa1 = $database->canDemolishEmbassy($leaderUid, $v1, 20, true);
    if ($canInstantDesa1 !== false) {
        throw new Exception("Expected canDemolishEmbassy (instant) in Desa 1 to be FALSE, got TRUE");
    }

    $addFailRes = $database->addDemolition($v1, 20);
    if ($addFailRes !== 18) {
        throw new Exception("Expected addDemolition on Desa 1 to return 18 (blocked), got: " . var_export($addFailRes, true));
    }
    echo "PASSED (primary embassy lvl 3 correctly protected against demolition)\n";

    // --- TEST 4: Non-leader member can demolish embassy in village 2 ---
    echo "[4] Testing regular member embassy demolition in village 2... ";
    $session->uid = $memberUid;
    $session->alliance = $aliId;
    $canMember = $database->canDemolishEmbassy($memberUid, $v2, 20, true);
    if (!$canMember) {
        throw new Exception("Expected regular member to be allowed to demolish embassy, got FALSE");
    }
    echo "PASSED (regular member allowed)\n";

    echo "\n✅ ALL EMBASSY DEMOLITION TESTS PASSED!\n";
} finally {
    // Cleanup
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id IN ($leaderUid, $memberUid)");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref IN ($v1, $v2)");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref IN ($v1, $v2)");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "alidata` WHERE id = $aliId");
    mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "demolition` WHERE vref IN ($v1, $v2)");
}

