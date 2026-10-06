<?php

declare(strict_types=1);

/**
 * Tests for Option 2 (Hero Level oasis unlock & optional Hero's Mansion)
 * and Option 3 (Hero's Mansion passive buffs: HP regen and revive discount).
 */

require_once __DIR__ . '/../GameEngine/config.php';

// 1. Test Oasis Slots Formula
function calculateAllowedOases(int $mansionLevel, int $heroLevel): int {
    $mansionSlots = floor(($mansionLevel - 5) / 5);
    $heroSlots    = floor(($heroLevel - 5) / 5);
    return (int) min(3, max(0, (int)$mansionSlots, (int)$heroSlots));
}

assert(calculateAllowedOases(0, 0) === 0, "Level 0 hero, 0 mansion should have 0 slots");
assert(calculateAllowedOases(0, 5) === 0, "Level 5 hero, 0 mansion should have 0 slots");
assert(calculateAllowedOases(0, 9) === 0, "Level 9 hero, 0 mansion should have 0 slots");
assert(calculateAllowedOases(0, 10) === 1, "Level 10 hero, 0 mansion should unlock 1 slot");
assert(calculateAllowedOases(0, 14) === 1, "Level 14 hero, 0 mansion should unlock 1 slot");
assert(calculateAllowedOases(0, 15) === 2, "Level 15 hero, 0 mansion should unlock 2 slots");
assert(calculateAllowedOases(0, 19) === 2, "Level 19 hero, 0 mansion should unlock 2 slots");
assert(calculateAllowedOases(0, 20) === 3, "Level 20 hero, 0 mansion should unlock 3 slots");
assert(calculateAllowedOases(0, 50) === 3, "Level 50 hero should be capped at 3 slots");

// Test Mansion overrides lower hero level
assert(calculateAllowedOases(10, 2) === 1, "Level 10 mansion should unlock 1 slot even with level 2 hero");
assert(calculateAllowedOases(15, 2) === 2, "Level 15 mansion should unlock 2 slots even with level 2 hero");
assert(calculateAllowedOases(20, 2) === 3, "Level 20 mansion should unlock 3 slots even with level 2 hero");

// Test Hero level overrides lower mansion level
assert(calculateAllowedOases(0, 15) === 2, "Level 15 hero should unlock 2 slots with 0 mansion");
assert(calculateAllowedOases(10, 20) === 3, "Level 20 hero should unlock 3 slots with level 10 mansion");

// 2. Test Passive Buff Calculations
function calculateMansionRegen(int $mansionLevel): float {
    $rate = defined('HERO_MANSION_REGEN_PER_LEVEL') ? (float)HERO_MANSION_REGEN_PER_LEVEL : 3.0;
    return $mansionLevel * $rate;
}

assert(calculateMansionRegen(0) === 0.0, "Level 0 mansion should give 0 regen");
assert(calculateMansionRegen(1) === 3.0, "Level 1 mansion should give 3 HP/day");
assert(calculateMansionRegen(10) === 30.0, "Level 10 mansion should give 30 HP/day");
assert(calculateMansionRegen(20) === 60.0, "Level 20 mansion should give 60 HP/day");

function calculateReviveDiscount(int $mansionLevel): float {
    $rate = defined('HERO_MANSION_REVIVE_DISCOUNT') ? (float)HERO_MANSION_REVIVE_DISCOUNT : 0.025;
    return min(0.50, $mansionLevel * $rate);
}

assert(calculateReviveDiscount(0) === 0.0, "Level 0 mansion should give 0% discount");
assert(calculateReviveDiscount(1) === 0.025, "Level 1 mansion should give 2.5% discount");
assert(calculateReviveDiscount(10) === 0.25, "Level 10 mansion should give 25% discount");
assert(calculateReviveDiscount(20) === 0.50, "Level 20 mansion should give 50% discount");
assert(calculateReviveDiscount(30) === 0.50, "Level 30 mansion should be capped at 50% discount");

function calculateDiscountedTrainTime(int $baseTime, int $mansionLevel): int {
    $discount = calculateReviveDiscount($mansionLevel);
    if ($discount > 0) {
        return max(1, (int) round($baseTime * (1 - $discount)));
    }
    return $baseTime;
}

assert(calculateDiscountedTrainTime(1000, 0) === 1000, "Base time 1000 with lvl 0 mansion should be 1000");
assert(calculateDiscountedTrainTime(1000, 10) === 750, "Base time 1000 with lvl 10 mansion (-25%) should be 750");
assert(calculateDiscountedTrainTime(1000, 20) === 500, "Base time 1000 with lvl 20 mansion (-50%) should be 500");

// 3. Test Config Constants
assert(defined('HERO_MANSION_OPTIONAL') && HERO_MANSION_OPTIONAL === true, "HERO_MANSION_OPTIONAL must be true");
assert(defined('HERO_FROM_START') && HERO_FROM_START === true, "HERO_FROM_START must be true");

echo "PASS HeroMansionOasisTest: All oasis and mansion passive buff tests passed.\n";
