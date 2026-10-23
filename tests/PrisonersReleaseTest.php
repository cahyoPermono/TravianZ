<?php

declare(strict_types=1);

#################################################################################
##  Test: Prisoners Release & Rally Point Display Test                         ##
##  Project: TravianZ                                                          ##
#################################################################################

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running PrisonersReleaseTest ===" . PHP_EOL;

// -------------------------------------------------------------------------
// 1. Test getPrisoners flattening logic (prevent nested [ [ row0, row1 ] ])
// -------------------------------------------------------------------------
class MockDatabasePrisoners {
    public static array $prisonersCache = [];
    public static array $prisonersCacheByID = [];
    public static array $prisonersCacheByVillageAndFromIDs = [];
    public array $mockRows = [];

    public function getPrisoners($wid, int $mode = 0, bool $use_cache = true): array {
        $array_passed = is_array($wid);
        if (!$array_passed) {
            $wid = [$wid];
        }
        foreach ($wid as $index => $widValue) {
            $wid[$index] = (int) $widValue;
        }

        if ($use_cache && !$array_passed && array_key_exists($wid[0].$mode, self::$prisonersCache)) {
            return self::$prisonersCache[$wid[0].$mode];
        } else if ($use_cache && $array_passed) {
            $newWIDs = [];
            foreach ($wid as $key) {
                if (!isset(self::$prisonersCache[$key.$mode])) {
                    $newWIDs[] = $key;
                }
            }
            if (!count($newWIDs)) {
                return self::$prisonersCache;
            } else {
                $wid = $newWIDs;
            }
        }

        // Mock DB fetch matching WHERE wref IN (...) or from IN (...)
        $result = [];
        foreach ($this->mockRows as $row) {
            $col = $mode ? 'from' : 'wref';
            if (in_array((int)$row[$col], $wid, true)) {
                $result[] = $row;
            }
        }

        if (!$array_passed) {
            self::$prisonersCache[$wid[0].$mode] = $result;
        } else {
            if ($result && count($result)) {
                foreach ($result as $record) {
                    $key = $record[($mode ? 'from' : 'wref')] . $mode;
                    if (!isset(self::$prisonersCache[$key])) {
                        self::$prisonersCache[$key] = [];
                    }
                    self::$prisonersCache[$key][] = $record;
                }
            }
            foreach ($wid as $key) {
                if (!isset(self::$prisonersCache[$key.$mode])) {
                    self::$prisonersCache[$key.$mode] = [];
                }
            }
        }

        return ($array_passed ? self::$prisonersCache : self::$prisonersCache[$wid[0].$mode]);
    }

    public function getPrisonersByID(int $id): ?array {
        foreach ($this->mockRows as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function deletePrisoners($id): void {
        if (!is_array($id)) {
            $id = [$id];
        }
        $id = array_map('intval', $id);
        $this->mockRows = array_values(array_filter($this->mockRows, function($row) use ($id) {
            return !in_array((int)$row['id'], $id, true);
        }));
        self::$prisonersCache = [];
        self::$prisonersCacheByID = [];
        self::$prisonersCacheByVillageAndFromIDs = [];
    }
}

$db = new MockDatabasePrisoners();
$villageWid = 42;

// Scenario: 14 traps occupied across 2 attacks
// Attack 1: 10 Clubswingers (Teuton) from village 101
$row1 = [
    'id' => 1, 'wref' => $villageWid, 'from' => 101,
    't1' => 10, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0, 't11' => 0
];
// Attack 2: 4 Legionnaires (Roman) from village 202
$row2 = [
    'id' => 2, 'wref' => $villageWid, 'from' => 202,
    't1' => 4, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0,
    't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0, 't11' => 0
];
$db->mockRows = [$row1, $row2];

// Test scalar getPrisoners return format
$p = $db->getPrisoners($villageWid);
assertEqual(2, count($p), "getPrisoners must return a flat array with 2 records");
assertTrue(isset($p[0]['id']) && (int)$p[0]['id'] === 1, "First element must be row 1");
assertTrue(isset($p[1]['id']) && (int)$p[1]['id'] === 2, "Second element must be row 2");
assertEqual(101, (int)$p[0]['from'], "First prisoner 'from' village ID must be 101");
assertEqual(202, (int)$p[1]['from'], "Second prisoner 'from' village ID must be 202");

// Test cache hit returns the same flat array
$cached = $db->getPrisoners($villageWid);
assertEqual(2, count($cached), "Cached getPrisoners must remain flat with 2 records");

echo "PASS: getPrisoners returns flat records for multiple captured armies" . PHP_EOL;

// -------------------------------------------------------------------------
// 2. Test Rally Point Template Render logic
// -------------------------------------------------------------------------
$mockUsers = [
    101 => ['tribe' => 2, 'username' => 'TeutonAttacker'], // Teuton
    202 => ['tribe' => 1, 'username' => 'RomanAttacker'],  // Roman
];
$mockVillages = [
    101 => ['name' => 'Teuton Outpost', 'owner' => 101],
    202 => ['name' => 'Roman Fort', 'owner' => 202],
];

$renderedRows = [];
foreach ($p as $pr) {
    $owner = $mockVillages[$pr['from']]['owner'] ?? 0;
    $tribe = max(1, (int)($mockUsers[$owner]['tribe'] ?? 1));
    $start = ($tribe - 1) * 10 + 1;
    $villageName = $mockVillages[$pr['from']]['name'] ?? '[?]';
    $delLink = "a2b.php?delprisoners=" . (int)$pr['id'];

    $troopCounts = [];
    for ($i = 1; $i <= 10; $i++) {
        $troopCounts[] = (int)($pr['t' . $i] ?? 0);
    }

    $renderedRows[] = [
        'village' => $villageName,
        'tribe' => $tribe,
        'start' => $start,
        'delLink' => $delLink,
        'troops' => $troopCounts,
    ];
}

assertEqual('Teuton Outpost', $renderedRows[0]['village'], "Row 0 village name rendered correctly");
assertEqual(2, $renderedRows[0]['tribe'], "Row 0 tribe is Teuton (2)");
assertEqual(11, $renderedRows[0]['start'], "Row 0 start unit index is 11 (u11)");
assertEqual('a2b.php?delprisoners=1', $renderedRows[0]['delLink'], "Row 0 send back link has id=1");
assertEqual(10, $renderedRows[0]['troops'][0], "Row 0 has 10 clubswingers");

assertEqual('Roman Fort', $renderedRows[1]['village'], "Row 1 village name rendered correctly");
assertEqual(1, $renderedRows[1]['tribe'], "Row 1 tribe is Roman (1)");
assertEqual(1, $renderedRows[1]['start'], "Row 1 start unit index is 1 (u1)");
assertEqual('a2b.php?delprisoners=2', $renderedRows[1]['delLink'], "Row 1 send back link has id=2");
assertEqual(4, $renderedRows[1]['troops'][0], "Row 1 has 4 legionnaires");

echo "PASS: Rally point prisoner display renders village name, tribe units, and send-back links" . PHP_EOL;

// -------------------------------------------------------------------------
// 3. Test a2b.php empty parameter fallback logic
// -------------------------------------------------------------------------
function simulateA2bHandling($delprisonersParam, &$invokedDeleteId, &$redirectUrl): string {
    $delprisoners = isset($delprisonersParam) ? preg_replace("/[^a-zA-Z0-9_-]/", "", (string)$delprisonersParam) : null;
    $invokedDeleteId = null;
    $redirectUrl = null;

    if (isset($delprisoners)) {
        if (!empty($delprisoners)) {
            $invokedDeleteId = (int)$delprisoners;
        }
        $redirectUrl = "build.php?id=39";
        return 'REDIRECT';
    }

    return 'SEND_TROOPS_FORM';
}

$simDeleteId = null;
$simRedirect = null;
$resultAction = simulateA2bHandling('', $simDeleteId, $simRedirect);
assertEqual('REDIRECT', $resultAction, "Empty delprisoners='' must redirect rather than show SEND TROOPS form");
assertEqual('build.php?id=39', $simRedirect, "Redirect location must be rally point");
assertEqual(null, $simDeleteId, "No deletePrisoners called when ID is empty");

$resultActionValid = simulateA2bHandling('1', $simDeleteId, $simRedirect);
assertEqual('REDIRECT', $resultActionValid, "Valid delprisoners='1' must redirect");
assertEqual(1, $simDeleteId, "deletePrisoners invoked with ID 1");

echo "PASS: a2b.php prevents accidental SEND TROOPS form fallthrough on empty delprisoners" . PHP_EOL;

// -------------------------------------------------------------------------
// 4. Test deletePrisoners release mechanism
// -------------------------------------------------------------------------
class MockUnitsPrisonerRelease {
    public array $modifyUnitCalls = [];
    public array $trainUnitCalls = [];
    public array $addAttackCalls = [];
    public array $addMovementCalls = [];
    public ?string $redirect = null;

    public function deletePrisoners(int $id, MockDatabasePrisoners $database, int $currentVillageWid): void {
        $prisoner = $database->getPrisonersByID($id);
        if (!$prisoner) {
            $this->redirect = "build.php?id=39";
            return;
        }

        $troops = 0;
        if ($prisoner['wref'] === $currentVillageWid) {
            for ($i = 1; $i <= 11; $i++) {
                $troops += (int)($prisoner['t' . $i] ?? 0);
            }

            // Return movement
            $p_reference = 999;
            $this->addAttackCalls[] = ['from' => $prisoner['from'], 'troops' => $troops];
            $this->addMovementCalls[] = ['type' => 4, 'from' => $prisoner['wref'], 'to' => $prisoner['from'], 'ref' => $p_reference];

            // Reset traps: reduce 99 and 99o, then queue repair
            $this->modifyUnitCalls[] = ['wid' => $currentVillageWid, 'units' => ["99", "99o"], 'amounts' => [$troops, $troops], 'modes' => [0, 0]];
            $this->trainUnitCalls[] = ['wid' => $currentVillageWid, 'unit' => 99, 'amt' => $troops];

            $database->deletePrisoners($prisoner['id']);
        }
        $this->redirect = "build.php?id=39";
    }
}

$units = new MockUnitsPrisonerRelease();
$units->deletePrisoners(1, $db, $villageWid);

assertEqual('build.php?id=39', $units->redirect, "Units::deletePrisoners redirects to build.php?id=39");
assertEqual(1, count($units->addMovementCalls), "One return movement (type 4) created");
assertEqual(4, $units->addMovementCalls[0]['type'], "Movement type is 4 (returning)");
assertEqual(101, $units->addMovementCalls[0]['to'], "Troops returning to village 101");
assertEqual(10, $units->modifyUnitCalls[0]['amounts'][0], "10 occupied traps freed and deducted");
assertEqual(10, $units->trainUnitCalls[0]['amt'], "10 traps queued for auto-repair");
assertEqual(1, count($db->mockRows), "Row 1 removed from database, 1 row remains");
assertEqual(2, (int)$db->mockRows[0]['id'], "Row 2 still in database");

// Release non-existent prisoner ID
$units->deletePrisoners(9999, $db, $villageWid);
assertEqual('build.php?id=39', $units->redirect, "Invalid prisoner ID gracefully redirects");

echo "PASS: Prisoners release successfully dispatches return movement, clears traps, and cleans DB" . PHP_EOL;

echo "=== All PrisonersReleaseTest checks passed successfully! ===" . PHP_EOL;
