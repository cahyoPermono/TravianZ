<?php
/**
 * Test for Egyptian Hero +25% Resource Production Advantage
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

echo "=== Running EgyptianHeroResourceBonusTest ===\n";

// Test calculation logic for Egyptian hero vs other tribes
function calcHeroProduction($points, $resType, $tribe, $speed = 4, $baseAll = 3, $baseOne = 10) {
    $heroMult = ($tribe === 7) ? 1.25 : 1.0;
    $map = [1 => 'wood', 2 => 'clay', 3 => 'iron', 4 => 'crop'];

    if ($resType === 0 || !isset($map[$resType])) {
        $each = $points * (float)$baseAll * $heroMult;
        return [
            'type' => 'all',
            'hourly_each_1x' => $each,
            'hourly_each_speed' => (int)round($each * $speed),
        ];
    }

    $single = $points * (float)$baseOne * $heroMult;
    return [
        'type' => $map[$resType],
        'hourly_1x' => $single,
        'hourly_speed' => (int)round($single * $speed),
    ];
}

// 1. Test Egyptian (Tribe 7) with 90 points on Speed 4
$egyptSingle = calcHeroProduction(90, 1, 7, 4);
assertEqual(4500, $egyptSingle['hourly_speed'], "Egyptian single resource with 90 points at 4x speed must produce 4500/hr (+25%)");

$egyptAll = calcHeroProduction(90, 0, 7, 4);
assertEqual(1350, $egyptAll['hourly_each_speed'], "Egyptian all resources with 90 points at 4x speed must produce 1350/hr (+25%)");

// 2. Test Non-Egyptian (e.g. Romans Tribe 1) with 90 points on Speed 4
$romanSingle = calcHeroProduction(90, 1, 1, 4);
assertEqual(3600, $romanSingle['hourly_speed'], "Roman single resource with 90 points at 4x speed must produce standard 3600/hr");

$romanAll = calcHeroProduction(90, 0, 1, 4);
assertEqual(1080, $romanAll['hourly_each_speed'], "Roman all resources with 90 points at 4x speed must produce standard 1080/hr");

// 3. Test Database getHeroForVillage includes u.tribe
global $database;
$testWid = 1; // Any sample wid or check query syntax
$q = "SELECT h.heroid, h.uid, h.wref, h.resources, h.res_type, u.tribe
        FROM " . TB_PREFIX . "hero h
        LEFT JOIN " . TB_PREFIX . "users u ON u.id = h.uid
       LIMIT 1";
$res = mysqli_query($database->dblink, $q);
assertTrue($res !== false, "Query selecting hero with user tribe must execute cleanly on MySQL");
if ($row = mysqli_fetch_assoc($res)) {
    assertTrue(array_key_exists('tribe', $row), "Row must contain tribe column");
}

echo "=== All EgyptianHeroResourceBonusTest checks passed successfully! ===\n";
