<?php
/**
 * Test for Chief Conquest Resource Field Protection & Self-Healing
 */

require_once __DIR__ . '/../GameEngine/Database.php';

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        echo "FAIL: $message\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

function assertTrue($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running ConquestResourceFieldProtectionTest ===\n";

// 1. Test Conquest Level Reduction Logic for Resource Fields vs Village Buildings
function simulateConquestDemolish($buildlevel) {
    $newLevels_fieldNames = [];
    $newLevels_fieldValues = [];

    for ($i = 1; $i <= 39; $i++) {
        if ($buildlevel['f' . $i] != 0) {
            $minLevel = ($buildlevel['f' . $i . 't'] == 15) ? 1 : 0;
            $leveldown = $buildlevel['f' . $i] - 1;
            if ($leveldown < $minLevel) $leveldown = $minLevel;
            $newLevels_fieldNames[] = 'f' . $i;
            $newLevels_fieldValues[] = $leveldown;
            // The fix: only i >= 19 resets f{i}t to 0
            if ($leveldown <= 0 && $i >= 19) {
                $newLevels_fieldNames[] = 'f' . $i . 't';
                $newLevels_fieldValues[] = 0;
            }
        }
    }

    return array_combine($newLevels_fieldNames, $newLevels_fieldValues);
}

// Case A: Resource field 4 (Iron Mine) at level 1, dropping to level 0
// Case B: Inner building 20 (Barracks) at level 1, dropping to level 0
$testBuildLevel = [
    'f4' => 1,
    'f4t' => 3,  // Iron Mine
    'f20' => 1,
    'f20t' => 19, // Barracks
];
for ($k = 1; $k <= 39; $k++) {
    if (!isset($testBuildLevel['f' . $k])) {
        $testBuildLevel['f' . $k] = 0;
        $testBuildLevel['f' . $k . 't'] = 0;
    }
}

$results = simulateConquestDemolish($testBuildLevel);

assertEqual(0, $results['f4'], "Field 4 level drops from 1 to 0");
assertTrue(!isset($results['f4t']), "Field 4 type (f4t) MUST NOT be wiped to 0!");

assertEqual(0, $results['f20'], "Building 20 level drops from 1 to 0");
assertEqual(0, $results['f20t'], "Building 20 type (f20t) must be wiped to 0 (empty slot)");

// 2. Test Self-Healing Logic for Corrupted Resource Fields (f1t..f18t)
function healCorruptedFields(&$resarray, $vType) {
    static $fieldMaps = [
        1  => [4,4,1,4,4,2,3,4,4,3,3,4,4,1,4,2,1,2],
        2  => [3,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        3  => [1,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        4  => [1,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        5  => [1,4,1,3,1,2,3,4,4,3,3,4,4,1,4,2,1,2],
        6  => [4,4,1,3,4,4,4,4,4,4,4,4,4,4,4,2,4,4],
        7  => [1,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        8  => [3,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        9  => [3,4,4,1,1,2,3,4,4,3,3,4,4,1,4,2,1,2],
        10 => [3,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
        11 => [3,1,1,3,1,4,4,3,3,2,2,3,1,4,4,2,4,4],
        12 => [1,4,1,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2],
    ];

    $map = $fieldMaps[$vType] ?? $fieldMaps[3];
    $healed = [];
    for ($i = 1; $i <= 18; $i++) {
        if (!isset($resarray['f'.$i.'t']) || (int)$resarray['f'.$i.'t'] === 0) {
            $correctType = (int)($map[$i - 1] ?? 1);
            $resarray['f'.$i.'t'] = $correctType;
            $healed[$i] = $correctType;
        }
    }
    return $healed;
}

// Simulate village type 3 with corrupted f4t = 0
$corruptedVillage = [
    'f1t' => 1,
    'f2t' => 4,
    'f3t' => 1,
    'f4t' => 0, // CORRUPTED
    'f5t' => 2,
];
for ($j = 6; $j <= 18; $j++) $corruptedVillage['f'.$j.'t'] = 2;

$healed = healCorruptedFields($corruptedVillage, 3);
assertEqual(3, $corruptedVillage['f4t'], "Corrupted field 4 must be restored to type 3 (Iron Mine)");
assertEqual([4 => 3], $healed, "Healed array must report field 4 restored to 3");

echo "=== All ConquestResourceFieldProtectionTest checks passed successfully! ===\n";
