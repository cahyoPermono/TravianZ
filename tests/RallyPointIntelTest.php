<?php

declare(strict_types=1);

/**
 * Unit tests for Rally Point & Scout Reconnaissance Intelligence Logic.
 */

function evaluateIntel(int $rpLevel, int $defScouts, array $atkUnits, int $atkScoutCount, bool $hasEyesight = false): array {
    $scoutUnitIDs = [4, 14, 23, 44, 52, 64, 74, 82];

    // Counter-scout duel
    $scoutJammed = ($atkScoutCount > 0 && $defScouts > 0 && $atkScoutCount >= $defScouts);
    $effectiveScouts = ($defScouts > 0 && !$scoutJammed) ? $defScouts : 0;

    $canSeeSpeed  = ($rpLevel >= 10 || $effectiveScouts >= 20 || $hasEyesight);
    $canSeeScale  = ($rpLevel >= 15 || $effectiveScouts >= 50 || $hasEyesight);
    $canSeeRoster = ($rpLevel >= 20 || $effectiveScouts >= 100 || $hasEyesight);

    $atkTotal = array_sum($atkUnits);

    if ($atkTotal < 100) {
        $scale = 'skirmish';
    } elseif ($atkTotal < 500) {
        $scale = 'regiment';
    } elseif ($atkTotal < 2000) {
        $scale = 'battalion';
    } elseif ($atkTotal < 10000) {
        $scale = 'legion';
    } else {
        $scale = 'hammer';
    }

    return [
        'scoutJammed'     => $scoutJammed,
        'effectiveScouts' => $effectiveScouts,
        'canSeeSpeed'     => $canSeeSpeed,
        'canSeeScale'     => $canSeeScale,
        'canSeeRoster'    => $canSeeRoster,
        'scale'           => $scale,
        'atkTotal'        => $atkTotal,
    ];
}

$assertCount = 0;
function assertTrue(bool $val, string $msg) {
    global $assertCount;
    $assertCount++;
    if (!$val) {
        throw new RuntimeException("Assertion failed: $msg");
    }
}

echo "Testing Rally Point & Scout Reconnaissance Intelligence...\n";

// Test 1: Low level RP, no scouts -> all advanced intel locked
$r1 = evaluateIntel(1, 0, [50, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue(!$r1['canSeeSpeed'], 'RP 1, 0 scouts should not see speed');
assertTrue(!$r1['canSeeScale'], 'RP 1, 0 scouts should not see scale');
assertTrue(!$r1['canSeeRoster'], 'RP 1, 0 scouts should not see roster');
assertTrue(!$r1['scoutJammed'], 'No scouts means not jammed');

// Test 2: RP 10 unlocks Speed
$r2 = evaluateIntel(10, 0, [150, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue($r2['canSeeSpeed'], 'RP 10 should see speed');
assertTrue(!$r2['canSeeScale'], 'RP 10 should not see scale yet');
assertTrue(!$r2['canSeeRoster'], 'RP 10 should not see roster yet');

// Test 3: 20 Scouts unlock Speed even at RP 1
$r3 = evaluateIntel(1, 20, [150, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue($r3['canSeeSpeed'], '20 scouts should unlock speed');
assertTrue(!$r3['canSeeScale'], '20 scouts should not unlock scale yet');

// Test 4: RP 15 or 50 Scouts unlock Scale
$r4a = evaluateIntel(15, 0, [600, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue($r4a['canSeeScale'], 'RP 15 unlocks scale');
assertTrue($r4a['scale'] === 'battalion', '600 troops is a battalion');

$r4b = evaluateIntel(1, 50, [600, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue($r4b['canSeeScale'], '50 scouts unlocks scale');

// Test 5: RP 20 or 100 Scouts unlock Unit Roster
$r5a = evaluateIntel(20, 0, [5000, 0, 0, 0, 0, 0, 100, 50, 0, 0], 0);
assertTrue($r5a['canSeeRoster'], 'RP 20 unlocks roster');
assertTrue($r5a['scale'] === 'legion', '5150 troops is a legion');

$r5b = evaluateIntel(1, 100, [15000, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0);
assertTrue($r5b['canSeeRoster'], '100 scouts unlocks roster');
assertTrue($r5b['scale'] === 'hammer', '15000 troops is a hammer');

// Test 6: Counter-scout duel (Attacker scouts >= Defender scouts)
$r6 = evaluateIntel(1, 50, [500, 0, 0, 50, 0, 0, 0, 0, 0, 0], 50);
assertTrue($r6['scoutJammed'], 'Attacker bringing 50 scouts jams defender 50 scouts');
assertTrue($r6['effectiveScouts'] === 0, 'Effective scouts drops to 0 when jammed');
assertTrue(!$r6['canSeeScale'], 'Jammed scouts cannot reveal scale when RP < 15');

// Test 7: Counter-scout duel with RP 20 (RP 20 permanent tower remains active even if scouts jammed)
$r7 = evaluateIntel(20, 50, [500, 0, 0, 60, 0, 0, 0, 0, 0, 0], 60);
assertTrue($r7['scoutJammed'], 'Attacker 60 scouts jams defender 50 scouts');
assertTrue($r7['canSeeRoster'], 'RP 20 still reveals roster despite jammed scouts');

echo "PASS: All $assertCount Rally Point Intel unit tests passed successfully!\n";
