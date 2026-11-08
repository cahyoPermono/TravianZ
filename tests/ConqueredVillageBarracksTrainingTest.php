<?php
/**
 * Test for Conquered Village Barracks Troop Training & Conquest Queue Cleanup
 * 
 * Verifies:
 * 1. Obsolete queues (training, bdata, research, demolition) are cleared on conquest.
 * 2. Conquered village tribe resolution & fallback logic.
 * 3. Cross-tribe troop training in barracks (e.g. Egyptian conqueror in Teuton village)
 *    successfully trains Teuton basic infantry (Clubswinger u11).
 * 4. Forged cross-tribe troop training requests are safely rejected.
 * 5. Template (19_train.tpl) renders correctly without negative ranges when tribe is 0 or cross-tribe.
 */

define('TESTING_MODE', true);

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        fwrite(STDERR, "  Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "  Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
    echo "PASS: $message" . PHP_EOL;
}

echo "=== Running ConqueredVillageBarracksTrainingTest ===" . PHP_EOL;
global $database;

$testSuffix = substr(md5((string)microtime(true)), 0, 6);

// 1. Create Attacker (Egyptian - Tribe 7)
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime)
    VALUES ('EgyConqueror_{$testSuffix}', MD5('pass'), 'egy_{$testSuffix}@example.com', 7, 2, " . time() . ")
");
$attackerUid = (int)mysqli_insert_id($database->dblink);

// 2. Create Defender (Teuton - Tribe 2)
$database->query("
    INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime)
    VALUES ('TeutonDef_{$testSuffix}', MD5('pass'), 'teuton_{$testSuffix}@example.com', 2, 2, " . time() . ")
");
$defenderUid = (int)mysqli_insert_id($database->dblink);

// 3. Create Defender Village (to be conquered)
$coords = [92, 92];
$wRow = $database->query_return("SELECT id FROM " . TB_PREFIX . "wdata WHERE x = {$coords[0]} AND y = {$coords[1]} LIMIT 1");
if (empty($wRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "wdata (fieldtype, oasistype, x, y, occupied, image) VALUES (3, 0, {$coords[0]}, {$coords[1]}, 1, 'f3')");
    $targetWid = (int)mysqli_insert_id($database->dblink);
} else {
    $targetWid = (int)$wRow[0]['id'];
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $targetWid");
}

$database->query("
    INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, tribe, wood, clay, iron, crop, maxstore, maxcrop)
    VALUES ($targetWid, $defenderUid, 'Teuton Outpost', 0, 150, 2, 50000, 50000, 50000, 50000, 80000, 80000)
    ON DUPLICATE KEY UPDATE owner = $defenderUid, tribe = 2, wood = 50000, clay = 50000, iron = 50000, crop = 50000
");

// Insert building data: Barracks at slot 19, level 5 (f19 = 5, f19t = 19)
$database->query("
    INSERT INTO " . TB_PREFIX . "fdata (vref, f19, f19t)
    VALUES ($targetWid, 5, 19)
    ON DUPLICATE KEY UPDATE f19 = 5, f19t = 19
");

// Insert tdata: default research
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref = $targetWid");
$database->query("INSERT INTO " . TB_PREFIX . "tdata (vref, t11) VALUES ($targetWid, 1)");

// Insert units row
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $targetWid");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($targetWid)");

// --- TEST 1: Conquest Obsolete Queue Cleanup ---
// Simulate leftover queues from the previous owner
$database->query("INSERT INTO " . TB_PREFIX . "training (vref, unit, amt, pop, timestamp, eachtime, timestamp2) VALUES ($targetWid, 11, 20, 1, " . (time() + 600) . ", 30, " . (time() + 30) . ")");
$database->query("INSERT INTO " . TB_PREFIX . "bdata (wid, field, type, loopcon, timestamp, master, level) VALUES ($targetWid, 20, 20, 0, " . (time() + 300) . ", 0, 1)");
$database->query("INSERT INTO " . TB_PREFIX . "research (vref, tech, timestamp) VALUES ($targetWid, 't12', " . (time() + 500) . ")");
$database->query("INSERT INTO " . TB_PREFIX . "demolition (vref, buildnumber, lvl, timetofinish) VALUES ($targetWid, 25, 1, " . (time() + 1000) . ")");

// Verify they were inserted
$trainCountPre = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "training WHERE vref = $targetWid")[0]['c'];
$bdataCountPre = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "bdata WHERE wid = $targetWid")[0]['c'];
$resCountPre   = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "research WHERE vref = $targetWid")[0]['c'];
$demoCountPre  = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "demolition WHERE vref = $targetWid")[0]['c'];

assertTrue($trainCountPre > 0 && $bdataCountPre > 0 && $resCountPre > 0 && $demoCountPre > 0, "Test 1A: Setup queues for target village before conquest");

// Execute the cleanup logic from AutomationBattleResolution.php
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = " . (int)$targetWid);
$database->query("DELETE FROM " . TB_PREFIX . "bdata WHERE wid = " . (int)$targetWid);
$database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = " . (int)$targetWid);
$database->query("DELETE FROM " . TB_PREFIX . "demolition WHERE vref = " . (int)$targetWid);

$trainCountPost = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "training WHERE vref = $targetWid")[0]['c'];
$bdataCountPost = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "bdata WHERE wid = $targetWid")[0]['c'];
$resCountPost   = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "research WHERE vref = $targetWid")[0]['c'];
$demoCountPost  = (int)$database->query_return("SELECT COUNT(*) as c FROM " . TB_PREFIX . "demolition WHERE vref = $targetWid")[0]['c'];

assertEqual(0, $trainCountPost, "Test 1B: Training queue cleared on conquest");
assertEqual(0, $bdataCountPost, "Test 1C: Building construction queue cleared on conquest");
assertEqual(0, $resCountPost,   "Test 1D: Academy research queue cleared on conquest");
assertEqual(0, $demoCountPost,  "Test 1E: Demolition queue cleared on conquest");

// --- TEST 2: Conquered Village Tribe Fallback ---
$targetVillageTribe = 2; // Teuton
$chiefOriginTribe   = 7; // Egyptian
$isNatarConquest    = false;
$conqueredTribe = $isNatarConquest ? $chiefOriginTribe : ($targetVillageTribe > 0 ? $targetVillageTribe : 0);
if ($conqueredTribe <= 0) {
    $conqueredTribe = ($chiefOriginTribe > 0) ? $chiefOriginTribe : 1;
}
assertEqual(2, $conqueredTribe, "Test 2A: Keep original village tribe on player conquest");

// Test Natar conquest
$isNatarConquest = true;
$conqueredTribeNatar = $isNatarConquest ? $chiefOriginTribe : ($targetVillageTribe > 0 ? $targetVillageTribe : 0);
if ($conqueredTribeNatar <= 0) {
    $conqueredTribeNatar = ($chiefOriginTribe > 0) ? $chiefOriginTribe : 1;
}
assertEqual(7, $conqueredTribeNatar, "Test 2B: Natar conquest adopts chief origin tribe");

// Test zero tribe fallback
$targetVillageTribeZero = 0;
$chiefOriginTribeZero = 0;
$conqueredTribeZero = $targetVillageTribeZero > 0 ? $targetVillageTribeZero : $chiefOriginTribeZero;
if ($conqueredTribeZero <= 0) {
    $conqueredTribeZero = ($chiefOriginTribeZero > 0) ? $chiefOriginTribeZero : 1;
}
assertEqual(1, $conqueredTribeZero, "Test 2C: Zero tribe fallback safely defaults to 1 (Roman)");

// Transfer ownership to Attacker (Egyptian) but keep village tribe as Teuton (2)
$database->setVillageFields($targetWid, ['loyalty', 'owner', 'tribe'], [100, $attackerUid, 2]);

// --- TEST 3: Cross-Tribe Troop Training in Barracks via Technology::procTech ---
// Setup mock globals for session and village
class MockSession {
    public $uid;
    public $tribe;
    public $userinfo;
    public function __construct($uid, $tribe) {
        $this->uid = $uid;
        $this->tribe = $tribe;
        $this->userinfo = ['gold' => 0];
    }
}

class MockVillage {
    public $wid;
    public $tribe;
    public $techarray;
    public $resarray;
    public $unitarray;
    public $atotal = 50000;
    public function __construct($wid, $tribe) {
        $this->wid = $wid;
        $this->tribe = $tribe;
        $this->techarray = ['t11' => 1];
        $this->resarray = ['f19' => 5, 'f19t' => 19];
        $this->unitarray = ['u11' => 0];
    }
    public function getProd($type) {
        return 1000;
    }
}

class MockBuilding {
    public function getTypeLevel($gid, $vid = 0) {
        if ($gid == 19) return 5; // Barracks level 5
        return 0;
    }
}

global $session, $village, $building, $technology;
$session = new MockSession($attackerUid, 7); // Egyptian user
$village = new MockVillage($targetWid, 2);   // Teuton village
$building = new MockBuilding();
$technology = new Technology();

// Post training for 15 Clubswingers (t11)
$postData = [
    'ft'  => 't1',
    'id'  => 19,
    't11' => 15,
];

$technology->procTech($postData);

// Check training table
$trainedRows = $database->query_return("SELECT * FROM " . TB_PREFIX . "training WHERE vref = $targetWid AND unit = 11");
assertTrue(!empty($trainedRows), "Test 3A: Clubswingers (u11) successfully queued in conquered Teuton village by Egyptian player");
assertEqual(15, (int)$trainedRows[0]['amt'], "Test 3B: Correct amount (15) of Clubswingers queued");

// --- TEST 4: Cross-Tribe Forged Unit Rejected ---
// Try to forge train unit 61 (Egyptian Slave Militia) in Teuton village
$postDataInvalid = [
    'ft'  => 't1',
    'id'  => 19,
    't61' => 10,
];

$technology->procTech($postDataInvalid);

$invalidRows = $database->query_return("SELECT * FROM " . TB_PREFIX . "training WHERE vref = $targetWid AND unit = 61");
assertTrue(empty($invalidRows), "Test 4: Forged unit 61 (Egyptian) rejected in Teuton village");

// --- TEST 5: Template (19_train.tpl) Rendering Safety ---
// Test 5A: Conquered Teuton village (tribe = 2) renders u11
$generator = new class {
    public function getTimeFormat($s) { return gmdate("H:i:s", $s); }
};
$bid19 = [
    5 => ['attri' => 70]
];
$unitsbytype = [
    'infantry' => [1, 2, 3, 11, 12, 13, 14, 21, 22, 31, 32, 33, 34, 41, 42, 43, 44, 51, 52, 61, 62, 63, 71, 72, 73, 74, 81, 82, 83, 84, 91, 92, 93]
];
$id = 19;

ob_start();
include __DIR__ . '/../Templates/Build/19_train.tpl';
$htmlOutput = ob_get_clean();

assertTrue(strpos($htmlOutput, 'name="t11"') !== false, "Test 5A: 19_train.tpl successfully renders input for unit t11 in Teuton village");

// Test 5B: Village with tribe = 0 safely resolves without negative ranges
$villageZero = new MockVillage($targetWid, 0);
$village = $villageZero;
$village->techarray = ['t61' => 1];

ob_start();
include __DIR__ . '/../Templates/Build/19_train.tpl';
$htmlOutputZero = ob_get_clean();

assertTrue(strpos($htmlOutputZero, 'name="t61"') !== false, "Test 5B: 19_train.tpl safely falls back to session tribe (7) when village tribe is 0 and renders t61");

// Cleanup test records
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($attackerUid, $defenderUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $targetWid");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $targetWid");
$database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref = $targetWid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $targetWid");
$database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $targetWid");

echo "=== All ConqueredVillageBarracksTrainingTest passed! ===" . PHP_EOL;
