<?php
/**
 * Test Suite: Nusantara Chiefing Hun Village - Building Availability & Validation Test
 *
 * Verifies that:
 * 1. An account with Nusantara main tribe (tribe = 10) that owns a conquered Hun village (tribe = 6):
 *    - Sees Hun buildings (Command Center GID 44, Makeshift Wall GID 43) in avaliable.tpl.
 *    - Does NOT see Residence (GID 25) or Palace (GID 26) in avaliable.tpl.
 *    - Does NOT see Sanggar Paranormal (GID 51) in avaliable.tpl.
 *    - Wall at slot 40 is Makeshift Wall (GID 43), NOT Earth Wall (GID 32) or City Wall (GID 31).
 *    - Building backend (meetRequirement & evaluateRequirements) permits Command Center (44).
 *    - Building backend blocks Residence (25), Palace (26), and Sanggar Paranormal (51).
 * 2. An account with Nusantara main tribe on a native Nusantara village (tribe = 10):
 *    - Sees Sanggar Paranormal (51) and Residence (25).
 *    - Does NOT see Command Center (44).
 */

$_SERVER['REQUEST_URI'] = '/build.php';
$_SERVER['SCRIPT_NAME'] = 'build.php';
$_SERVER['PHP_SELF'] = 'build.php';
$_SERVER['HTTP_HOST'] = 'localhost';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Village.php';
require_once __DIR__ . '/../GameEngine/Building.php';

function assert_test($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Nusantara Chiefing Hun Village Building Availability Tests ===\n\n";

// 1. Setup mock objects for Nusantara player (tribe = 10) with Hun village (tribe = 6)
$mockSession = (object)[
    'uid' => 77701,
    'username' => 'SultanNusantara',
    'tribe' => 10,
    'isAdmin' => false,
    'plus' => false,
    'goldclub' => false,
];

function createMockResarray($mbLvl = 10, $crannyLvl = 5) {
    $res = [];
    for ($i = 1; $i <= 40; $i++) {
        $res['f' . $i] = 0;
        $res['f' . $i . 't'] = 0;
    }
    // Main Building in slot 19
    $res['f19'] = $mbLvl;
    $res['f19t'] = 15;
    // Cranny in slot 20
    $res['f20'] = $crannyLvl;
    $res['f20t'] = 23;
    return $res;
}

// Mock Village: Hun village
$mockHunVillage = new stdClass();
$mockHunVillage->wid = 88801;
$mockHunVillage->vname = 'Hun Conquered Camp';
$mockHunVillage->tribe = 6;
$mockHunVillage->capital = 0;
$mockHunVillage->natar = 0;
$mockHunVillage->coor = ['x' => 10, 'y' => 20];
$mockHunVillage->resarray = createMockResarray(10, 5);
$mockHunVillage->infoarray = ['tribe' => 6];

// Setup Building instance using reflection to inject mocks
$bRefClass = new ReflectionClass('Building');
$bldg = $bRefClass->newInstanceWithoutConstructor();

$dbProp = $bRefClass->getProperty('db');
$dbProp->setAccessible(true);
$dbProp->setValue($bldg, $database);

$sProp = $bRefClass->getProperty('sess');
$sProp->setAccessible(true);
$sProp->setValue($bldg, $mockSession);

$vProp = $bRefClass->getProperty('vil');
$vProp->setAccessible(true);
$vProp->setValue($bldg, $mockHunVillage);

// Test effective tribe detection
assert_test($bldg->getEffectiveTribe() === 6, "Hun village effective tribe correctly detected as 6 (Hun)");

// Test Building backend requirements for Hun village
$meetReqMethod = $bRefClass->getMethod('meetRequirement');
$meetReqMethod->setAccessible(true);

$evalReqMethod = $bRefClass->getMethod('evaluateRequirements');
$evalReqMethod->setAccessible(true);

// Mock getTypeLevel and getTypeField behavior for requirements check
// Building class calls $this->getTypeLevel($gid) and $this->getTypeField($gid)
// In a Reflection test with mocked village, verify meetRequirement directly:
$canResidenceInHun = $meetReqMethod->invoke($bldg, 25);
assert_test($canResidenceInHun === false, "Residence (GID 25) must be BLOCKED in Hun village (Huns use Command Center)");

$canPalaceInHun = $meetReqMethod->invoke($bldg, 26);
assert_test($canPalaceInHun === false, "Palace (GID 26) must be BLOCKED in Hun village");

$canParanormalInHun = $evalReqMethod->invoke($bldg, 51, Building::BUILD_REQUIREMENTS[51]);
assert_test($canParanormalInHun === false, "Sanggar Paranormal (GID 51) must be BLOCKED in Hun village (exclusive to Nusantara tribe 10)");

$canCommandCenterInHun = $evalReqMethod->invoke($bldg, 44, Building::BUILD_REQUIREMENTS[44]);
assert_test($canCommandCenterInHun === true, "Command Center (GID 44) must be ALLOWED in Hun village (matches tribe 6)");

$canMakeshiftWallInHun = $evalReqMethod->invoke($bldg, 43, Building::BUILD_REQUIREMENTS[43]);
assert_test($canMakeshiftWallInHun === true, "Makeshift Wall (GID 43) must be ALLOWED in Hun village");

$canEarthWallInHun = $evalReqMethod->invoke($bldg, 32, Building::BUILD_REQUIREMENTS[32]);
assert_test($canEarthWallInHun === false, "Earth Wall (GID 32) must be BLOCKED in Hun village");

// 2. Setup mock objects for Nusantara player on Native Nusantara Village (tribe = 10)
$mockNusaVillage = new stdClass();
$mockNusaVillage->wid = 88802;
$mockNusaVillage->vname = 'Majapahit Capital';
$mockNusaVillage->tribe = 10;
$mockNusaVillage->capital = 1;
$mockNusaVillage->natar = 0;
$mockNusaVillage->coor = ['x' => 0, 'y' => 0];
$mockNusaVillage->resarray = createMockResarray(10, 5);
$mockNusaVillage->infoarray = ['tribe' => 10];

$vProp->setValue($bldg, $mockNusaVillage);
assert_test($bldg->getEffectiveTribe() === 10, "Nusantara village effective tribe correctly detected as 10 (Nusantara)");

$canParanormalInNusa = $evalReqMethod->invoke($bldg, 51, Building::BUILD_REQUIREMENTS[51]);
assert_test($canParanormalInNusa === true, "Sanggar Paranormal (GID 51) must be ALLOWED in Nusantara village");

$canCommandCenterInNusa = $evalReqMethod->invoke($bldg, 44, Building::BUILD_REQUIREMENTS[44]);
assert_test($canCommandCenterInNusa === false, "Command Center (GID 44) must be BLOCKED in Nusantara village");

$canEarthWallInNusa = $evalReqMethod->invoke($bldg, 32, Building::BUILD_REQUIREMENTS[32]);
assert_test($canEarthWallInNusa === true, "Earth Wall (GID 32) must be ALLOWED in Nusantara village");

// 3. Template Render Verification (Templates/Build/avaliable.tpl)
// Render avaliable.tpl with Hun village:
$GLOBALS['session'] = $mockSession;
$GLOBALS['village'] = $mockHunVillage;
$GLOBALS['building'] = $bldg;
$id = 25; // Building slot 25

ob_start();
include __DIR__ . '/../Templates/Build/avaliable.tpl';
$hunOutput = ob_get_clean();

// Check Hun village rendering:
assert_test(strpos($hunOutput, 'building g44') !== false, "avaliable.tpl for Hun village MUST include Command Center (g44)");
assert_test(strpos($hunOutput, 'building g25') === false, "avaliable.tpl for Hun village MUST NOT include Residence (g25)");
assert_test(strpos($hunOutput, 'building g26') === false, "avaliable.tpl for Hun village MUST NOT include Palace (g26)");
assert_test(strpos($hunOutput, 'building g51') === false, "avaliable.tpl for Hun village MUST NOT include Sanggar Paranormal (g51)");

// Render avaliable.tpl with Nusantara village:
$GLOBALS['village'] = $mockNusaVillage;
$vProp->setValue($bldg, $mockNusaVillage);

ob_start();
include __DIR__ . '/../Templates/Build/avaliable.tpl';
$nusaOutput = ob_get_clean();

assert_test(strpos($nusaOutput, 'building g51') !== false, "avaliable.tpl for Nusantara village MUST include Sanggar Paranormal (g51)");
assert_test(strpos($nusaOutput, 'building g25') !== false, "avaliable.tpl for Nusantara village MUST include Residence (g25)");
assert_test(strpos($nusaOutput, 'building g44') === false, "avaliable.tpl for Nusantara village MUST NOT include Command Center (g44)");

echo "\n>>> ALL NUSANTARA CHIEF HUN VILLAGE TESTS PASSED SUCCESSFULLY! <<<\n";
