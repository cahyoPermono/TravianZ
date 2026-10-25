<?php
/**
 * Test player attack submission to Benteng Kuno (0|0) via coordinates in a2b.php / Units.php
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
require_once __DIR__ . '/../GameEngine/Bastion.php';
if (!defined('SITTER_PERM_ATTACK')) define('SITTER_PERM_ATTACK', 1);
if (!defined('SITTER_PERM_RAID'))   define('SITTER_PERM_RAID',   2);
if (!defined('SITTER_PERM_REINF'))  define('SITTER_PERM_REINF',  4);
require_once __DIR__ . '/../GameEngine/Units.php';

echo "=== STARTING BASTION PLAYER ATTACK COORDS TESTS ===\n";

global $database, $generator;

// 1. Test getVillageState on Benteng Kuno (wref 20201)
echo "Test 1: Testing getVillageState(20201) and coordinate resolution...\n";
$wref = Bastion::getBastionWref();
$coor = $database->getCoor($wref);
if ($coor['x'] != 0 || $coor['y'] != 0) {
    echo "FAIL: Bastion coordinates expected (0, 0), got ({$coor['x']}, {$coor['y']})\n";
    exit(1);
}

$state = $database->getVillageState($wref);
if (!$state) {
    echo "FAIL: getVillageState(20201) returned FALSE!\n";
    exit(1);
}
echo "PASS: getVillageState(20201) correctly returns TRUE!\n";

// 2. Setup mock session and village
$testUser = $database->query_return("SELECT id, username, alliance FROM " . TB_PREFIX . "users WHERE id > 5 LIMIT 1");
$uid = (int)$testUser[0]['id'];
$testVil = $database->query_return("SELECT wref, name FROM " . TB_PREFIX . "vdata WHERE owner = $uid LIMIT 1");
$wid = (int)$testVil[0]['wref'];

// Grant test village troops so validation passes
$database->query("UPDATE " . TB_PREFIX . "units SET u1 = 100, u2 = 50 WHERE vref = $wid");

class MockVillage {
    public $wid;
    public $resarray = ['f39' => 1];
    public $unitarray = ['u1' => 100, 'u2' => 50, 'hero' => 0];
    public function __construct($wid) {
        $this->wid = $wid;
    }
}

class MockSession {
    public $uid;
    public $username;
    public $alliance;
    public $tribe = 1;
    public $sit = 0;
    public function __construct($uid, $ally) {
        $this->uid = $uid;
        $this->alliance = $ally;
    }
    public function sitterCan($perm) {
        return true;
    }
}

global $village, $session;
$village = new MockVillage($wid);
$session = new MockSession($uid, 0);

// Ensure Bastion event is active
$event = Bastion::getEvent();
if ($event['status'] != Bastion::STATUS_ACTIVE) {
    Bastion::startEvent(Bastion::MODE_MANUAL, 28800);
}

// 3. Test checkErrors when player inputs X=0, Y=0 with Normal Attack (c = 3)
echo "Test 2: Testing checkErrors with Normal attack (c = 3) to coordinates (0, 0)...\n";
$units = new Units();
$post = [
    't1' => 10,
    'c' => 3, // Normal attack
    'x' => '0',
    'y' => '0'
];

$_POST = $post;
$err = $units->checkErrors($post);
if ($err !== "") {
    echo "FAIL: checkErrors returned error: '$err'\n";
    exit(1);
}
echo "PASS: checkErrors allowed Normal Attack to coordinates (0, 0) without 'Coordinates do not exist' error!\n";

// 4. Test checkErrors when non-holder attempts Reinforcement (c = 2) to coordinates (0, 0)
echo "Test 3: Testing checkErrors with Reinforcement (c = 2) by non-holding player...\n";
$postReinf = [
    't1' => 10,
    'c' => 2, // Reinforcement
    'x' => '0',
    'y' => '0'
];

$_POST = $postReinf;
$errReinf = $units->checkErrors($postReinf);
if ($errReinf === "" || strpos($errReinf, "Coordinates do not exist") !== false) {
    echo "FAIL: Expected reinforcement restriction error, got: '$errReinf'\n";
    exit(1);
}
echo "PASS: Reinforcement correctly blocked with descriptive message: '$errReinf'!\n";

echo "\n=== ALL BASTION PLAYER ATTACK COORDS TESTS PASSED SUCCESSFULLY! ===\n";
