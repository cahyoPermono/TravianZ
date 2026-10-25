<?php
require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
require_once __DIR__ . '/../GameEngine/BotAI.php';
require_once __DIR__ . '/../GameEngine/Automation/AutomationTroopMovements.php';
define('BOT_AI_BASTION_ATTACK_CHANCE', 100);

echo "=== STARTING BASTION REINFORCEMENT AND BOT WARFARE TESTS ===\n";

global $database;
$db = $database->dblink;
if (!$db) {
    die("FAIL: Cannot connect to database\n");
}
Bastion::ensureTables();

// Helper to reset bastion_event state
function setBastionEventState($status, $holderAlly, $holderUid, $prevAlly = 0) {
    global $database;
    $db = $database->dblink;
    mysqli_query($db, "UPDATE `" . TB_PREFIX . "bastion_event` SET
        `status` = $status,
        `holder_alliance` = $holderAlly,
        `holder_uid` = $holderUid,
        `previous_holder_alliance` = $prevAlly,
        `hold_start_time` = " . time() . "
        WHERE `id` = 1");
}

// -------------------------------------------------------------
// Test 1: Bastion::canReinforce validation rules
// -------------------------------------------------------------
echo "Test 1: Testing Bastion::canReinforce() permission logic...\n";

// A. When event is inactive
setBastionEventState(Bastion::STATUS_INACTIVE, 10, 5);
if (Bastion::canReinforce(5, 10) !== false) {
    die("FAIL: canReinforce should return false when event is inactive\n");
}

// B. When event is active and held by Alliance 10
setBastionEventState(Bastion::STATUS_ACTIVE, 10, 5);
if (Bastion::canReinforce(5, 10) !== true) {
    die("FAIL: canReinforce should return true for holder alliance member\n");
}
if (Bastion::canReinforce(6, 10) !== true) {
    die("FAIL: canReinforce should return true for another member of holder alliance\n");
}
if (Bastion::canReinforce(7, 20) !== false) {
    die("FAIL: canReinforce should return false for rival alliance 20\n");
}
if (Bastion::canReinforce(8, 0) !== false) {
    die("FAIL: canReinforce should return false for player without alliance\n");
}

// C. When event is active and held by Solo player (UID 99, ally 0)
setBastionEventState(Bastion::STATUS_ACTIVE, 0, 99);
if (Bastion::canReinforce(99, 0) !== true) {
    die("FAIL: canReinforce should return true for solo holder UID 99\n");
}
if (Bastion::canReinforce(100, 0) !== false) {
    die("FAIL: canReinforce should return false for other solo player\n");
}
echo "PASS: Bastion::canReinforce() strictly allows only occupying alliance/player!\n";

// -------------------------------------------------------------
// Test 2: Bastion::handleReinforcementArrival and logging
// -------------------------------------------------------------
echo "Test 2: Testing reinforcement arrival logging to bastion_logs...\n";

setBastionEventState(Bastion::STATUS_ACTIVE, 10, 5);
// Clean test logs
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bastion_logs` WHERE log_type = 'reinforce'");

$testData = [
    't1' => 25, 't2' => 15, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0, 't11' => 1
];
$fromMock = ['owner' => 5, 'name' => 'Desa Pertahanan'];
$toMock = ['wref' => Bastion::BASTION_WREF, 'name' => 'Benteng Kuno'];

Bastion::handleReinforcementArrival($testData, $fromMock, $toMock, 10);

$qLog = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_logs` WHERE log_type = 'reinforce' ORDER BY id DESC LIMIT 1");
if (!$qLog || mysqli_num_rows($qLog) === 0) {
    die("FAIL: Expected reinforce log in bastion_logs\n");
}
$logRow = mysqli_fetch_assoc($qLog);
if (strpos($logRow['title'], 'Bala Bantuan') === false || strpos($logRow['description'], '41 prajurit') === false) {
    die("FAIL: Log content incorrect: " . print_r($logRow, true) . "\n");
}
echo "PASS: Reinforcement arrival correctly logged with total troops & hero breakdown!\n";

// -------------------------------------------------------------
// Test 3: BotAI::processBastionWarfare behavior
// -------------------------------------------------------------
echo "Test 3: Testing BotAI::processBastionWarfare() AI decision matrix...\n";

// Create or mock a bot user and village for testing
$testBotUid = 88888;
$testBotWid = 99999;
$botAlly = 77;
$rivalAlly = 99;

mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testBotUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testBotWid");

mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "users` (`id`, `username`, `alliance`, `tribe`, `access`)
    VALUES ($testBotUid, 'Test_Bot_Alpha', $botAlly, 1, 2)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "wdata` (`id`, `x`, `y`) VALUES ($testBotWid, 10, 10)");
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `pop`) VALUES ($testBotWid, $testBotUid, 'Bot Ville', 150)");

// Give bot plenty of troops: Romans (tribe 1): u1=Legionnaire, u2=Praetorian, u3=Imperian, u4=Equites Legati, u5=Equites Imperatoris
mysqli_query($db, "INSERT INTO `" . TB_PREFIX . "units` (`vref`, `u1`, `u2`, `u3`, `u4`, `u5`, `hero`)
    VALUES ($testBotWid, 50, 50, 50, 10, 20, 0)");

$botArray = [
    'uid' => $testBotUid,
    'wref' => $testBotWid,
    'alliance' => $botAlly,
    'tribe' => 1,
    'pop' => 150
];

// Clean any previous movements for testBotWid
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testBotWid");

// A. SCENARIO: Rival Alliance holds Benteng Kuno -> Bot MUST ATTACK with Normal Attack (attack_type = 3)
setBastionEventState(Bastion::STATUS_ACTIVE, $rivalAlly, 1234, 0);

$action = BotAI::processBastionWarfare($botArray);
if (!$action || strpos($action, 'Penyerangan') === false) {
    die("FAIL: Bot should launch conquest Normal Attack against rival held Bastion, got: $action\n");
}

// Verify attack record was created with attack_type = 3 (Normal attack, NOT raid or reinforce)
$qAtk = mysqli_query($db, "SELECT a.attack_type FROM `" . TB_PREFIX . "movement` m 
    JOIN `" . TB_PREFIX . "attacks` a ON m.ref = a.id 
    WHERE m.`from` = $testBotWid AND m.`to` = " . Bastion::BASTION_WREF . " LIMIT 1");
$atkRow = mysqli_fetch_assoc($qAtk);
if (!$atkRow || (int)$atkRow['attack_type'] !== 3) {
    die("FAIL: Expected attack_type = 3 (Normal Attack), got: " . ($atkRow['attack_type'] ?? 'none') . "\n");
}
echo "PASS: Bot strictly uses Normal Attack (attack_type = 3) when attempting to conquer Benteng Kuno!\n";

// Clean movement
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testBotWid");

// B. SCENARIO: Bot Alliance is holder -> Bot REINFORCES (attack_type = 2)
setBastionEventState(Bastion::STATUS_ACTIVE, $botAlly, $testBotUid, 0);

$actionDef = BotAI::processBastionWarfare($botArray);
if (!$actionDef || strpos($actionDef, 'Bertahan') === false) {
    die("FAIL: Bot should reinforce its own alliance Bastion, got: $actionDef\n");
}

$qDef = mysqli_query($db, "SELECT a.attack_type FROM `" . TB_PREFIX . "movement` m 
    JOIN `" . TB_PREFIX . "attacks` a ON m.ref = a.id 
    WHERE m.`from` = $testBotWid AND m.`to` = " . Bastion::BASTION_WREF . " LIMIT 1");
$defRow = mysqli_fetch_assoc($qDef);
if (!$defRow || (int)$defRow['attack_type'] !== 2) {
    die("FAIL: Expected attack_type = 2 (Reinforcement), got: " . ($defRow['attack_type'] ?? 'none') . "\n");
}
echo "PASS: Bot sends Reinforcements (attack_type = 2) only when its alliance holds Benteng Kuno!\n";

// Clean movement
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testBotWid");

// C. SCENARIO: Bot Alliance was recently dethroned -> Bot launches COUNTER-ATTACK (attack_type = 3)
setBastionEventState(Bastion::STATUS_ACTIVE, $rivalAlly, 5555, $botAlly);

$actionReclaim = BotAI::processBastionWarfare($botArray);
if (!$actionReclaim || strpos($actionReclaim, 'Serangan Balasan') === false) {
    die("FAIL: Bot should launch counter-attack when alliance lost Bastion, got: $actionReclaim\n");
}

$qReclaim = mysqli_query($db, "SELECT a.attack_type FROM `" . TB_PREFIX . "movement` m 
    JOIN `" . TB_PREFIX . "attacks` a ON m.ref = a.id 
    WHERE m.`from` = $testBotWid AND m.`to` = " . Bastion::BASTION_WREF . " LIMIT 1");
$recRow = mysqli_fetch_assoc($qReclaim);
if (!$recRow || (int)$recRow['attack_type'] !== 3) {
    die("FAIL: Expected attack_type = 3 for counter-attack, got: " . ($recRow['attack_type'] ?? 'none') . "\n");
}
echo "PASS: Bot successfully launches prioritized Counter-Attack (Normal Attack) upon losing Bastion!\n";
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testBotWid");
// -------------------------------------------------------------
// Test 4: Testing in-transit reinforcement delivery bounce vs acceptance
// -------------------------------------------------------------
echo "Test 4: Testing in-transit reinforcement bounce and acceptance in AutomationTroopMovements...\n";

// Set Bastion holder to Alliance 10
setBastionEventState(Bastion::STATUS_ACTIVE, 10, 5);

class TestTroopMovementRunner {
    use AutomationTroopMovements;
    public function deliver($data, $to, $isoasis, $DefenderID) {
        $this->completeReinforcementDelivery($data, $to, $isoasis, $DefenderID);
    }
}
$runner = new TestTroopMovementRunner();

// Create a dummy attack record
$atkId = $database->addAttack($testBotWid, 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0);

$reinfData = [
    'ref' => $atkId,
    'from' => $testBotWid,
    'to' => Bastion::BASTION_WREF,
    't1' => 10, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0, 't11' => 0,
    'starttime' => time() - 100,
    'endtime' => time()
];
$toVillage = [
    'wref' => Bastion::BASTION_WREF,
    'owner' => 5,
    'name' => 'Benteng Kuno'
];

// Sub-test 4A: Sender is $testBotUid (Alliance $botAlly = 77, but Bastion is held by Alliance 10)
// Troops MUST BOUNCE!
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = " . Bastion::BASTION_WREF);
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE `from` = $testBotWid");

$runner->deliver($reinfData, $toVillage, 0, 5);

// Check if return movement sort_type 4 was created
$qBounce = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "movement` 
    WHERE `from` = " . Bastion::BASTION_WREF . " AND `to` = $testBotWid AND sort_type = 4");
if (!$qBounce || mysqli_num_rows($qBounce) === 0) {
    die("FAIL: Expected bounce return movement (sort_type 4) for non-holding alliance reinforcement!\n");
}

// Check that no enforcement row was created
$qEnf = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE `from` = $testBotWid AND vref = " . Bastion::BASTION_WREF);
if ($qEnf && mysqli_num_rows($qEnf) > 0) {
    die("FAIL: Non-holding alliance troops should NOT be stationed in enforcement!\n");
}
echo "PASS: Non-holding alliance reinforcement successfully bounced back home!\n";

// Sub-test 4B: Now update Bastion holder to Alliance $botAlly (Alliance 77)
setBastionEventState(Bastion::STATUS_ACTIVE, $botAlly, $testBotUid);
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = " . Bastion::BASTION_WREF);
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "bastion_logs` WHERE log_type = 'reinforce'");

$runner->deliver($reinfData, $toVillage, 0, $testBotUid);

// Check that enforcement was created
$qEnf2 = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "enforcement` WHERE `from` = $testBotWid AND vref = " . Bastion::BASTION_WREF);
if (!$qEnf2 || mysqli_num_rows($qEnf2) === 0) {
    die("FAIL: Occupying alliance troops should be stationed in enforcement!\n");
}

// Check that arrival was logged
$qLog2 = mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "bastion_logs` WHERE log_type = 'reinforce' ORDER BY id DESC LIMIT 1");
if (!$qLog2 || mysqli_num_rows($qLog2) === 0) {
    die("FAIL: Occupying alliance reinforcement arrival should be logged in bastion_logs!\n");
}
echo "PASS: Occupying alliance reinforcement successfully stationed and logged!\n";

// Cleanup test fixtures
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "movement` WHERE `from` = $testBotWid OR `to` = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "attacks` WHERE id = $atkId");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "enforcement` WHERE `from` = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "wdata` WHERE id = $testBotWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testBotUid");

echo "\n=== ALL BASTION REINFORCEMENT AND BOT WARFARE TESTS PASSED SUCCESSFULLY! ===\n";

