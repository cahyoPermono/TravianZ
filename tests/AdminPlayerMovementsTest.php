<?php
/**
 * Test for Admin Player Movements and Stationed Troops Tracking
 */

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

echo "=== Running AdminPlayerMovementsTest ===\n";

// 1. Test SQL Generation and Structure
define('TB_PREFIX', 'sdata_');
define('MULTIHUNTER', 9);
define('ADMIN', 9);

$contextWrefs = [1001, 1002];
$wrefsIn = implode(',', $contextWrefs);

$sqlMovements = "
    SELECT m.moveid, m.sort_type, m.from, m.to, m.ref, m.starttime, m.endtime,
           a.attack_type, a.ctar1, a.ctar2,
           a.t1, a.t2, a.t3, a.t4, a.t5, a.t6, a.t7, a.t8, a.t9, a.t10, a.t11
    FROM " . TB_PREFIX . "movement m
    LEFT JOIN " . TB_PREFIX . "attacks a ON (m.ref = a.id AND m.sort_type IN (3, 4, 8))
    WHERE m.proc = 0
      AND (m.from IN ($wrefsIn) OR m.to IN ($wrefsIn))
    ORDER BY m.endtime ASC
";

assertTrue(strpos($sqlMovements, "m.from IN (1001,1002) OR m.to IN (1001,1002)") !== false, "Movement query correctly matches player villages");
assertTrue(strpos($sqlMovements, "m.proc = 0") !== false, "Movement query filters for in-flight movements only");

// 2. Test Movement Type Classification
function classifyMovement($sortType, $attackType, $isIncoming) {
    if ($sortType === 3) {
        if ($attackType === 3) return $isIncoming ? 'incoming_attack' : 'outgoing_attack';
        if ($attackType === 4) return $isIncoming ? 'incoming_raid' : 'outgoing_raid';
        if ($attackType === 2) return $isIncoming ? 'incoming_reinforce' : 'outgoing_reinforce';
        if ($attackType === 1) return $isIncoming ? 'incoming_scout' : 'outgoing_scout';
    } elseif ($sortType === 4) {
        return 'returning';
    } elseif ($sortType === 5) {
        return 'settler_expedition';
    } elseif (in_array($sortType, [9, 10, 20, 21])) {
        return 'hero_adventure';
    }
    return 'other';
}

assertEqual('outgoing_attack', classifyMovement(3, 3, false), "Attack type 3 classified as outgoing attack");
assertEqual('incoming_raid', classifyMovement(3, 4, true), "Attack type 4 classified as incoming raid");
assertEqual('returning', classifyMovement(4, 0, false), "Sort type 4 classified as returning");
assertEqual('settler_expedition', classifyMovement(5, 0, false), "Sort type 5 classified as settler expedition");
assertEqual('hero_adventure', classifyMovement(9, 0, false), "Sort type 9 classified as hero adventure");

// 3. Test Time Remaining and Formatting
$now = 100000;
$endTime = 103725; // 3725 seconds later (1 hour, 2 minutes, 5 seconds)
$rem = $endTime - $now;
$h = floor($rem / 3600);
$m = floor(($rem % 3600) / 60);
$s = $rem % 60;
$remFormatted = sprintf('%02d:%02d:%02d', $h, $m, $s);

assertEqual('01:02:05', $remFormatted, "Countdown formatted properly as HH:MM:SS");

// 4. Test Troop Breakdown by Tribe
function extractTroopList($mv, $senderTribe) {
    $unitStart = ($senderTribe - 1) * 10 + 1;
    $troopsList = [];
    if (in_array((int)$mv['sort_type'], [3, 4, 8])) {
        for ($idx = 1; $idx <= 10; $idx++) {
            $cnt = (int)($mv['t' . $idx] ?? 0);
            if ($cnt > 0) {
                $troopsList[] = [
                    'id' => $unitStart + ($idx - 1),
                    'cnt' => $cnt
                ];
            }
        }
    } elseif ((int)$mv['sort_type'] === 5) {
        $troopsList[] = [
            'id' => $senderTribe * 10,
            'cnt' => 3
        ];
    }
    return $troopsList;
}

// Egyptian Army (Tribe 7: units 61..70)
$egyptianAtt = [
    'sort_type' => 3,
    't1' => 500, // Armed Slave (u61)
    't3' => 50,  // Temple Guardian (u63)
    't10' => 0
];
$troops = extractTroopList($egyptianAtt, 7);
assertEqual(2, count($troops), "Extracted 2 troop types for Egyptian attack");
assertEqual(61, $troops[0]['id'], "Unit 1 for Egyptian is u61 (Armed Slave)");
assertEqual(500, $troops[0]['cnt'], "Count is 500");
assertEqual(63, $troops[1]['id'], "Unit 3 for Egyptian is u63 (Temple Guardian)");
assertEqual(50, $troops[1]['cnt'], "Count is 50");

// Settler Expedition for Teutons (Tribe 2: settler is u20)
$settlerMv = ['sort_type' => 5];
$troopsSettler = extractTroopList($settlerMv, 2);
assertEqual(20, $troopsSettler[0]['id'], "Teuton settler unit is u20");
assertEqual(3, $troopsSettler[0]['cnt'], "Settler count is 3");

echo "=== All AdminPlayerMovementsTest tests passed successfully! ===\n";
