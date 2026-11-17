<?php

#################################################################################
##  Unit Test: Alien Troops Self-Healing & Mutation Sweeping                   ##
#################################################################################

function assertCondition($cond, string $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Alien Troops Self-Healing Test ===\n\n";

// Mock implementation of healAlienTroops logic to verify conversion rules
class MockVillageAlienHealer {
    public int $tribe;
    public int $wid = 1234;
    public array $unitarray = [];
    public array $dbQueries = [];

    public function __construct(int $tribe, array $initialUnits) {
        $this->tribe = $tribe;
        $this->unitarray = $initialUnits;
    }

    public function healAlienTroops(): void {
        $vTribe = (int)($this->tribe ?? 1);
        $baseUnits = [
            1 => 1,   // Romawi: 1..10
            2 => 11,  // Teuton: 11..20
            3 => 21,  // Galia: 21..30
            6 => 51,  // Hun: 51..60
            7 => 61,  // Mesir: 61..70
            8 => 71,  // Sparta: 71..80
            9 => 81,  // Viking: 81..90
            10 => 91, // Nusantara: 91..100
        ];

        if (!isset($baseUnits[$vTribe])) {
            return;
        }

        $villageBase = $baseUnits[$vTribe];
        $validMin = $villageBase;
        $validMax = $villageBase + 9;

        $setParts = [];
        $hasChanges = false;

        for ($u = 1; $u <= 100; $u++) {
            if ($u >= $validMin && $u <= $validMax) {
                continue;
            }
            // KECUALIKAN: Hewan alam liar (u31..u40)
            if ($u >= 31 && $u <= 40) {
                continue;
            }
            // KECUALIKAN: Perangkap Trapper Galia (u99)
            if ($u == 99) {
                continue;
            }

            $col = 'u' . $u;
            $qty = (int)($this->unitarray[$col] ?? 0);
            if ($qty > 0) {
                $slotOffset = ($u - 1) % 10;
                $targetUnit = $villageBase + $slotOffset;
                $targetCol = 'u' . $targetUnit;

                // Update data in-memory unitarray
                $newTotal = (int)($this->unitarray[$targetCol] ?? 0) + $qty;
                $this->unitarray[$targetCol] = $newTotal;
                $this->unitarray[$col] = 0;

                $setParts[] = "`$targetCol` = `$targetCol` + $qty";
                $setParts[] = "`$col` = 0";
                $hasChanges = true;
            }
        }

        if ($hasChanges && !empty($setParts)) {
            $this->dbQueries[] = "UPDATE s630d_units SET " . implode(', ', $setParts) . " WHERE vref = " . (int)$this->wid;
        }
    }
}

// Case 1: The exact user scenario from the uploaded screenshot
// Suku = 7 (Egypt)
// Pasukan:
// - u31: 13 Rat, u40: 10 Elephant (Nature animals)
// - u61: 441 Armed Slave, u62: 683 Egyptian Fighter, u65: 91 Chariot
// - u81: 65 Viking Raider, u85: 42 Viking Rider (Residue from Viking)
$initialUnits = [
    'u31' => 13,
    'u40' => 10,
    'u61' => 441,
    'u62' => 683,
    'u65' => 91,
    'u81' => 65,
    'u85' => 42,
    'u99' => 5,
];

$healer = new MockVillageAlienHealer(7, $initialUnits);
$healer->healAlienTroops();

// Verify u81 (Viking Raider, slot 0) converted to u61 (Armed Slave, 441 + 65 = 506)
assertCondition($healer->unitarray['u61'] === 506, "u61 (Armed Slave) correctly incremented from 441 to 506 (+65 from u81 Viking Raider)");
assertCondition($healer->unitarray['u81'] === 0, "u81 (Viking Raider) cleared to 0");

// Verify u85 (Viking Rider, slot 4) converted to u65 (Chariot, 91 + 42 = 133)
assertCondition($healer->unitarray['u65'] === 133, "u65 (Chariot) correctly incremented from 91 to 133 (+42 from u85 Viking Rider)");
assertCondition($healer->unitarray['u85'] === 0, "u85 (Viking Rider) cleared to 0");

// Verify native Egyptian fighter untouched
assertCondition($healer->unitarray['u62'] === 683, "u62 (Egyptian Fighter) untouched at 683");

// Verify caged animals preserved
assertCondition($healer->unitarray['u31'] === 13, "u31 (Rat) preserved at 13");
assertCondition($healer->unitarray['u40'] === 10, "u40 (Elephant) preserved at 10");

// Verify Trapper traps preserved
assertCondition($healer->unitarray['u99'] === 5, "u99 (Trap) preserved at 5");

// Verify SQL update query generated
assertCondition(!empty($healer->dbQueries), "Database update query generated");
assertCondition(strpos($healer->dbQueries[0], '`u61` = `u61` + 65') !== false, "Query increments u61 atomically");
assertCondition(strpos($healer->dbQueries[0], '`u81` = 0') !== false, "Query clears u81");
assertCondition(strpos($healer->dbQueries[0], '`u65` = `u65` + 42') !== false, "Query increments u65 atomically");
assertCondition(strpos($healer->dbQueries[0], '`u85` = 0') !== false, "Query clears u85");

// Case 2: Clean village has no changes and triggers zero queries
$cleanVillage = new MockVillageAlienHealer(7, ['u61' => 100, 'u62' => 50, 'u31' => 5]);
$cleanVillage->healAlienTroops();
assertCondition(empty($cleanVillage->dbQueries), "Clean village triggers zero database queries");

// Case 3: Romans (Tribe 1) with Teuton troops (u11 Clubswinger = 30)
$romanVillage = new MockVillageAlienHealer(1, ['u1' => 50, 'u11' => 30]);
$romanVillage->healAlienTroops();
assertCondition($romanVillage->unitarray['u1'] === 80, "u11 Clubswinger merged into u1 Legionnaire (50 + 30 = 80)");
assertCondition($romanVillage->unitarray['u11'] === 0, "u11 Clubswinger cleared to 0");

echo "\n>>> ALL ALIEN TROOPS SELF-HEALING TESTS PASSED SUCCESSFULLY! <<<\n";
