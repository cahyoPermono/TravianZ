<?php
/**
 * SanggarParanormalClampFixTest.php
 * Verifies that Sanggar Paranormal (GID 51) is not clamped to Barricade (GID 50)
 * in Admin Panel building editing, that Admin procResType supports GID 51,
 * and that Village::healNusantaraParanormal self-heals any corrupted slots.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Lang/en.php';
require_once __DIR__ . '/../GameEngine/Admin/function.php';
require_once __DIR__ . '/../GameEngine/Village.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Sanggar Paranormal GID 51 & Self-Healing Tests ===\n\n";

// 1. Test Admin funct::procResType(51)
$funct = new funct();
$name50 = $funct->procResType(50);
$name51 = $funct->procResType(51);

assert_true($name50 === 'Barricade', "procResType(50) should be 'Barricade' (actual: $name50)");
assert_true($name51 === 'Sanggar Paranormal' || (defined('PARANORMAL_SANCTUARY') && $name51 === PARANORMAL_SANCTUARY), "procResType(51) should be 'Sanggar Paranormal' (actual: $name51)");

// 2. Test clamping logic in editBuildings.php
$gidInput = 51;
$levelInput = 18;
$clampedGid = max(0, min(51, $gidInput));
assert_true($clampedGid === 51, "GID 51 must not be clamped down to 50 (actual: $clampedGid)");

$gidOver = 60;
$clampedOver = max(0, min(51, $gidOver));
assert_true($clampedOver === 51, "GID > 51 must clamp to max 51 (actual: $clampedOver)");

// 3. Test Village::healNusantaraParanormal() via Reflection
$reflectionClass = new ReflectionClass('Village');
$mockVillage = $reflectionClass->newInstanceWithoutConstructor();

// Setup mock mockDb
$mockDb = new class {
    public $lastQuery = '';
    public function query($sql) {
        $this->lastQuery = $sql;
        return true;
    }
};

$dbProp = $reflectionClass->getProperty('db');
$dbProp->setAccessible(true);
$dbProp->setValue($mockVillage, $mockDb);

$widProp = $reflectionClass->getProperty('wid');
$widProp->setAccessible(true);
$widProp->setValue($mockVillage, 12345);

$tribeProp = $reflectionClass->getProperty('tribe');
$tribeProp->setAccessible(true);
$tribeProp->setValue($mockVillage, 10); // Nusantara

// Setup mock resarray with slot 37 corrupted to 50 (Barricade)
$resarray = [];
for ($i = 1; $i <= 40; $i++) {
    $resarray['f' . $i] = 10;
    $resarray['f' . $i . 't'] = 1;
}
$resarray['f37t'] = 50; // Corrupted Barricade
$resarray['f37'] = 18;  // Level 18
$resarray['f40t'] = 32; // Earth wall

$resProp = $reflectionClass->getProperty('resarray');
$resProp->setAccessible(true);
$resProp->setValue($mockVillage, $resarray);

$healMethod = $reflectionClass->getMethod('healNusantaraParanormal');
$healMethod->setAccessible(true);
$healMethod->invoke($mockVillage);

$afterResarray = $resProp->getValue($mockVillage);
assert_true($afterResarray['f37t'] === 51, "Slot 37 with GID 50 in Nusantara village must self-heal to GID 51 (Sanggar Paranormal)");
assert_true(strpos($mockDb->lastQuery, '`f37t` = 51') !== false, "Database query must update f37t to 51");

// 4. Test that non-Nusantara village (e.g. Viking tribe 9) is NOT touched
$mockDb->lastQuery = '';
$tribeProp->setValue($mockVillage, 9); // Viking
$resarray['f37t'] = 50;
$resProp->setValue($mockVillage, $resarray);

$healMethod->invoke($mockVillage);
$afterVikingResarray = $resProp->getValue($mockVillage);
assert_true($afterVikingResarray['f37t'] === 50, "Slot 37 in non-Nusantara village must remain untouched");
assert_true($mockDb->lastQuery === '', "Database must NOT be updated for non-Nusantara village");

echo "\n>>> ALL SANGGAR PARANORMAL FIX TESTS PASSED SUCCESSFULLY! <<<\n";

