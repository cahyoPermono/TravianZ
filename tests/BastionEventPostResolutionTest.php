<?php
/**
 * Test Suite: Benteng Kuno Post-Event Resolution & Polish
 * Verifies:
 * 1. Friendly wave attack conversion into reinforcement (no friendly fire).
 * 2. Conquering army survivors stay stationed at Benteng Kuno (no retreat home).
 * 3. No guardian respawn on ensureBastionVillage() page visits.
 * 4. Fixed event end time triggers victory for current occupant at deadline.
 * 5. Village ownership neutrality (vdata.owner stays 91016, troops return on victory).
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
require_once __DIR__ . '/../GameEngine/Units.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationBattleResolution.php';

$generator = new MyGenerator();
$GLOBALS['generator'] = $generator;

echo "=== START BENTENG KUNO POST-RESOLUTION TEST ===\n\n";

$db = $GLOBALS['database']->dblink;
$wref = Bastion::BASTION_WREF;

// Setup test alliance & players
$faction = Bastion::ensureBotFaction();
$guardianUid = $faction['uid'];

// Create Test Alliance 101 and Player 1001 & Player 1002
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "alidata` WHERE `id` IN (101, 102)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "alidata` (`id`, `name`, `tag`, `leader`, `coor`, `desc`) 
    VALUES (101, 'Aliansi Garuda', 'GRD', 1001, 0, 'Test Alliance 1')");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "alidata` (`id`, `name`, `tag`, `leader`, `coor`, `desc`) 
    VALUES (102, 'Aliansi Singa', 'SNG', 2001, 0, 'Test Alliance 2')");

@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE `id` IN (1001, 1002, 2001)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `password`, `email`, `tribe`, `access`, `alliance`) 
    VALUES (1001, 'CommanderGaruda1', 'pwd', 'g1@test.com', 1, 2, 101)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `password`, `email`, `tribe`, `access`, `alliance`) 
    VALUES (1002, 'CommanderGaruda2', 'pwd', 'g2@test.com', 1, 2, 101)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `password`, `email`, `tribe`, `access`, `alliance`) 
    VALUES (2001, 'RivalSinga1', 'pwd', 's1@test.com', 1, 2, 102)");

// Ensure villages for test players
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE `wref` IN (30001, 30002, 30003)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`, `wood`, `clay`, `iron`, `crop`, `maxstore`, `maxcrop`)
    VALUES (30001, 1001, 'Desa Garuda 1', 1, 100, 5000, 5000, 5000, 5000, 10000, 10000)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`, `wood`, `clay`, `iron`, `crop`, `maxstore`, `maxcrop`)
    VALUES (30002, 1002, 'Desa Garuda 2', 1, 100, 5000, 5000, 5000, 5000, 10000, 10000)");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`, `wood`, `clay`, `iron`, `crop`, `maxstore`, `maxcrop`)
    VALUES (30003, 2001, 'Desa Singa 1', 1, 100, 5000, 5000, 5000, 5000, 10000, 10000)");

// Ensure wdata rows exist
foreach ([30001, 30002, 30003] as $wid) {
    @mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE `id` = $wid");
    @mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (`id`, `fieldtype`, `oasistype`, `x`, `y`, `occupied`, `image`)
        VALUES ($wid, 3, 0, 15, 15, 1, 't3')");
}

// -------------------------------------------------------------
// TEST 1: No Guardian Respawn on ensureBastionVillage() Page Visit
// -------------------------------------------------------------
echo "[TEST 1] Testing Guardian Respawn Prevention on page visit...\n";
Bastion::startEvent(150, 43200); // Start event with 150 guardians

// Simulate defeat of guardians (set units u41..u47 to 0)
@mysqli_query($db, "UPDATE `" . TB_PREFIX . "units` SET `u41`=0, `u42`=0, `u43`=0, `u46`=0, `u47`=0 WHERE `vref` = $wref");
// Simulate player reinforcement present
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref");
@mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "enforcement` (`vref`, `from`, `u1`, `u2`, `hero`) VALUES ($wref, 30001, 50, 25, 1)");

// Player visits bastion.php -> calls Bastion::ensureBastionVillage()
Bastion::ensureBastionVillage();

// Check if units remained 0 and enforcement still exists!
$unitsRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT u41, u42, u43 FROM `" . TB_PREFIX . "units` WHERE `vref` = $wref"));
$enfRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT u1, u2, hero FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref"));

if ((int)$unitsRow['u41'] === 0 && (int)$unitsRow['u42'] === 0 && (int)$enfRow['u1'] === 50) {
    echo "  -> PASS: Guardians did NOT respawn and reinforcements were preserved!\n";
} else {
    echo "  -> FAIL: Guardians respawned or reinforcements were wiped! u41=" . $unitsRow['u41'] . ", u1=" . ($enfRow['u1'] ?? 'null') . "\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 2: Conqueror Surviving Troops Stationing (No Return Movement)
// -------------------------------------------------------------
echo "\n[TEST 2] Testing Conqueror Troops Garrison Retention...\n";

// Set event active, with no holder yet
@mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `status` = 1, `holder_alliance` = 0, `holder_uid` = 0 WHERE `id` = 1");

// Create an attack record with surviving units
$now = time();
$atkRef = $database->addAttack(30001, 40, 20, 10, 0, 0, 0, 0, 0, 0, 0, 1, 3, 0, 0, 0, 0);
// Add movement type 4 (return trip) as Automation normally does
$database->addMovement(4, $wref, 30001, $atkRef, $now, $now + 1800);

// Call handleBattleResult simulating that CommanderGaruda1 wiped out the defenders
$dataMock = [
    'ref' => $atkRef,
    'from' => 30001,
    'to' => $wref
];
$fromMock = [
    'wref' => 30001,
    'owner' => 1001,
    'name' => 'Desa Garuda 1',
    'alliance' => 101
];
$toMock = [
    'wref' => $wref,
    'name' => 'Benteng Kuno',
    'owner' => $guardianUid
];

Bastion::handleBattleResult($dataMock, $fromMock, $toMock, 101, 0, 10, 70, 150, 150);

// Check:
// 1. Movement type 4 for $atkRef should be DELETED!
$qMov = mysqli_query($db, "SELECT moveid FROM `" . TB_PREFIX . "movement` WHERE `sort_type` = 4 AND `ref` = $atkRef AND `from` = $wref");
$hasReturn = ($qMov && mysqli_num_rows($qMov) > 0);

// 2. Enforcement at $wref from 30001 should have the surviving troops!
$qEnf = mysqli_query($db, "SELECT u1, u2, u3, hero FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref AND `from` = 30001");
$enfData = $qEnf ? mysqli_fetch_assoc($qEnf) : null;

// 3. Holder should now be Alliance 101
$evAfter = Bastion::getEvent();

if (!$hasReturn && !empty($enfData) && (int)$enfData['u1'] === 40 && (int)$enfData['hero'] === 1 && (int)$evAfter['holder_alliance'] === 101) {
    echo "  -> PASS: Conqueror troops safely stationed as fortress garrison (return movement deleted)!\n";
} else {
    echo "  -> FAIL: Conqueror retention failed! hasReturn=" . ($hasReturn ? 'yes' : 'no') . ", u1=" . ($enfData['u1'] ?? 'null') . "\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 3: Friendly Wave Conversion to Reinforcement
// -------------------------------------------------------------
echo "\n[TEST 3] Testing Friendly Attack Wave Conversion to Reinforcement...\n";

// Now Alliance 101 holds the fortress.
// Player 1002 (same alliance) had an attack wave on the way!
$waveRef = $database->addAttack(30002, 30, 15, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3, 0, 0, 0, 0);
$moveId = $database->addMovement(3, 30002, $wref, $waveRef, $now - 100, $now);

// Check friendliness
$isFriendly = Bastion::isFriendlyWithHolder(1002, 101);
if (!$isFriendly) {
    echo "  -> FAIL: isFriendlyWithHolder returned false for same alliance!\n";
    exit(1);
}

// Check rival is NOT friendly
$isRivalFriendly = Bastion::isFriendlyWithHolder(2001, 102);
if ($isRivalFriendly) {
    echo "  -> FAIL: isRivalFriendly returned true for rival alliance 102!\n";
    exit(1);
}

// Execute conversion
$dataWave = [
    'ref' => $waveRef,
    'moveid' => $moveId,
    'from' => 30002,
    'to' => $wref,
    't1' => 30,
    't2' => 15,
    't3' => 0,
    't4' => 0,
    't5' => 0,
    't6' => 0,
    't7' => 0,
    't8' => 0,
    't9' => 0,
    't10' => 0,
    't11' => 0
];
$fromWave = [
    'wref' => 30002,
    'owner' => 1002,
    'name' => 'Desa Garuda 2'
];

Bastion::convertAttackWaveToReinforcement($dataWave, $fromWave, 1, 101);

// Check if troops from 30002 are now in enforcement
$qWaveEnf = mysqli_query($db, "SELECT u1, u2 FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref AND `from` = 30002");
$waveEnfRow = $qWaveEnf ? mysqli_fetch_assoc($qWaveEnf) : null;

if (!empty($waveEnfRow) && (int)$waveEnfRow['u1'] === 30 && (int)$waveEnfRow['u2'] === 15) {
    echo "  -> PASS: Friendly wave automatically converted to garrison reinforcement without friendly fire!\n";
} else {
    echo "  -> FAIL: Friendly wave conversion failed! u1=" . ($waveEnfRow['u1'] ?? 'null') . "\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 4: Fixed Event End Time & Final Whistle Victory
// -------------------------------------------------------------
echo "\n[TEST 4] Testing Fixed Event End Time & Final Whistle Victory...\n";

// Set event_end_time to 10 seconds ago to simulate clock striking 20:00 WIB
$pastEndTime = time() - 10;
@mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET `event_end_time` = $pastEndTime, `status` = 1 WHERE `id` = 1");

// Run processTick
Bastion::processTick();

$evVict = Bastion::getEvent();
if ((int)$evVict['status'] === Bastion::STATUS_VICTORY && (int)$evVict['winner_alliance'] === 101 && (int)$evVict['winner_uid'] === 1001) {
    echo "  -> PASS: Match end time triggered instant victory for occupant (Alliance 101)!\n";
} else {
    echo "  -> FAIL: Victory not declared at match end time! status=" . $evVict['status'] . ", winner=" . $evVict['winner_alliance'] . "\n";
    exit(1);
}

// -------------------------------------------------------------
// TEST 5: Village Ownership Neutrality & Safe Troop Return
// -------------------------------------------------------------
echo "\n[TEST 5] Testing Village Ownership Neutrality & Safe Troop Return...\n";

// 1. Benteng Kuno vdata.owner must be 91016 (guardianUid), NOT 1001!
$qVdata = mysqli_query($db, "SELECT owner, name FROM `" . TB_PREFIX . "vdata` WHERE `wref` = $wref");
$vRow = mysqli_fetch_assoc($qVdata);

if ((int)$vRow['owner'] === (int)$guardianUid) {
    echo "  -> PASS: Benteng Kuno owner remains neutral (UID $guardianUid), NOT player village!\n";
} else {
    echo "  -> FAIL: Benteng Kuno owner changed to player UID " . $vRow['owner'] . "!\n";
    exit(1);
}

// 2. Check that reinforcements at 20201 were cleared from enforcement table
$qRemEnf = mysqli_query($db, "SELECT COUNT(*) as cnt FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref");
$remRow = mysqli_fetch_assoc($qRemEnf);

// 3. And return movements (sort_type 4) were created heading back to 30001 and 30002!
$qRet1 = mysqli_query($db, "SELECT moveid FROM `" . TB_PREFIX . "movement` WHERE `sort_type` = 4 AND `from` = $wref AND `to` = 30001");
$qRet2 = mysqli_query($db, "SELECT moveid FROM `" . TB_PREFIX . "movement` WHERE `sort_type` = 4 AND `from` = $wref AND `to` = 30002");

if ((int)$remRow['cnt'] === 0 && mysqli_num_rows($qRet1) > 0 && mysqli_num_rows($qRet2) > 0) {
    echo "  -> PASS: Stationed alliance troops were safely scheduled to return home to their respective villages!\n";
} else {
    echo "  -> FAIL: Troop return failed! remEnf=" . $remRow['cnt'] . ", ret1=" . mysqli_num_rows($qRet1) . ", ret2=" . mysqli_num_rows($qRet2) . "\n";
    exit(1);
}

// =========================================================================
// TEST 6: Self-Healing Ownership Restoration & Profile Village Filtering
// =========================================================================
echo "\n[TEST 6] Testing Self-Healing Ownership & Player Profile Filtering...\n";

// 1. Artificially corrupt Benteng Kuno to be owned by a player (e.g. UID 1001)
mysqli_query($db, "UPDATE `" . TB_PREFIX . "vdata` SET `owner` = 1001, `name` = 'Benteng Kuno (WoW)' WHERE `wref` = $wref");

// 2. Verify getProfileVillages(1001) filters out wref 20201
$profVils = $database->getProfileVillages(1001, 0, false);
$hasBentengInProfile = false;
foreach ($profVils as $pv) {
    if ((int)$pv['wref'] === $wref) {
        $hasBentengInProfile = true;
        break;
    }
}
if (!$hasBentengInProfile) {
    echo "  -> PASS: getProfileVillages() successfully filters out Benteng Kuno from player's profile!\n";
} else {
    echo "  -> FAIL: Benteng Kuno still appeared in player's profile villages!\n";
    exit(1);
}

// 3. Trigger ensureTables() or processTick() -> should auto-heal owner back to 91016
Bastion::ensureTables();
$qHealed = mysqli_query($db, "SELECT `owner`, `name` FROM `" . TB_PREFIX . "vdata` WHERE `wref` = $wref LIMIT 1");
$hRow = mysqli_fetch_assoc($qHealed);
if ((int)$hRow['owner'] === 91016) {
    echo "  -> PASS: Bastion::ensureTables() successfully self-healed owner back to 91016!\n";
} else {
    echo "  -> FAIL: Self-healing failed! Owner is still " . $hRow['owner'] . "\n";
    exit(1);
}

// Clean up test data
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $wref OR `to` = $wref");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE `vref` = $wref");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE `wref` IN (30001, 30002, 30003)");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE `id` IN (30001, 30002, 30003)");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE `id` IN (1001, 1002, 2001)");
@mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "alidata` WHERE `id` IN (101, 102)");

echo "\n=== ALL 6 POST-RESOLUTION TESTS PASSED SUCCESSFULLY! ===\n";
