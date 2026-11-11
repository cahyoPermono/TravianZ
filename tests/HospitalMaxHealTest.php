<?php
/**
 * Test Suite: Hospital Max Heal Recommendation & Auto-fill
 *
 * Verifies:
 * 1. Technology::maxHeal correctly calculates maximum healable troops based on current resources.
 * 2. Capping behavior: min(resource_limit, wounded_limit).
 * 3. Bottleneck testing (wood, clay, iron, crop individual shortages).
 * 4. Odd unit cost handling with ceil/floor integer arithmetic.
 * 5. Template rendering for 46.tpl (Hospital) and 48.tpl (Big Hospital), ensuring:
 *    - thead (NAME, AVAILABLE, QUANTITY, MAX)
 *    - input id="hamt_XX"
 *    - clickable max link with document.getElementById('hamt_XX').value=<max>
 *    - cost & duration details
 * 6. Healing execution via procHeal using maxHeal recommendation.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
@include_once __DIR__ . '/../GameEngine/Data/unitdata.php';
@include_once __DIR__ . '/../GameEngine/Data/buidata.php';

class HospitalMaxHealTest {
    private $db;
    private $tech;

    public function __construct($database) {
        $this->db = $database;
        $this->tech = new Technology();
    }

    public function run() {
        echo "=== Running Hospital Max Heal Recommendation Tests ===\n\n";

        $testVid = 888901;
        $this->cleanupVillage($testVid);

        try {
            $this->testMaxHealCalculations($testVid);
            $this->testMaxHealResourceBottlenecks($testVid);
            $this->testMaxHealOddUnitCosts($testVid);
            $this->testHospitalTemplateRendering($testVid);
            $this->testBigHospitalTemplateRendering($testVid);
            $this->testHealingExecutionWithMax($testVid);

            echo "\n✅ ALL HOSPITAL MAX HEAL TESTS PASSED!\n";
        } finally {
            $this->cleanupVillage($testVid);
        }
    }

    private function cleanupVillage($vid) {
        $this->db->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $vid");
        $this->db->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $vid");
        $this->db->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $vid");
        $this->db->clearHospital($vid);
        $this->db->query("DELETE FROM " . TB_PREFIX . "healing WHERE vref = $vid");
    }

    private function testMaxHealCalculations($vid) {
        global $village;
        echo "[1] Testing maxHeal calculation & capping behavior... ";

        // Setup village with 10,000 of each resource
        $this->db->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($vid, 7, 'Hospital Test', 1, 200, 10000, 10000, 10000, 10000, 80000, 80000, 10)");
        $this->db->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f20, f20t) VALUES ($vid, 5, 46)");

        $village = new stdClass();
        $village->wid = $vid;
        $village->awood = 10000;
        $village->aclay = 10000;
        $village->airon = 10000;
        $village->acrop = 10000;

        // u91 (Pendekar Keris): wood 110, clay 90, iron 130, crop 30
        // Heal cost per unit: 50% => wood 55, clay 45, iron 65, crop 15
        // With 10,000 resources:
        // wood: floor(20000 / 110) = 181
        // clay: floor(20000 / 90) = 222
        // iron: floor(20000 / 130) = 153
        // crop: floor(20000 / 30) = 666
        // maxAfford = min(181, 222, 153, 666) = 153

        // Uncapped test
        $uncapped = $this->tech->maxHeal(91, false);
        if ($uncapped !== 153) {
            throw new Exception("Expected uncapped maxHeal for u91 to be 153, got: $uncapped");
        }

        // Capped with 40 wounded troops in hospital
        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [91], [40]);
        $cappedAt40 = $this->tech->maxHeal(91, true);
        if ($cappedAt40 !== 40) {
            throw new Exception("Expected capped maxHeal to be 40 (limited by wounded), got: $cappedAt40");
        }

        // Capped with 200 wounded troops (limited by resources = 153)
        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [91], [200]);
        $cappedAt153 = $this->tech->maxHeal(91, true);
        if ($cappedAt153 !== 153) {
            throw new Exception("Expected capped maxHeal to be 153 (limited by resources), got: $cappedAt153");
        }

        // Pre-passed wounded parameter
        $paramTest = $this->tech->maxHeal(91, true, 25);
        if ($paramTest !== 25) {
            throw new Exception("Expected pre-passed wounded test to be 25, got: $paramTest");
        }

        echo "PASSED (uncapped: 153, capped by wounded: 40, capped by resources: 153)\n";
    }

    private function testMaxHealResourceBottlenecks($vid) {
        global $village;
        echo "[2] Testing resource bottlenecks (iron, wood, clay, crop)... ";

        $village = new stdClass();
        $village->wid = $vid;

        // Bottleneck on Iron (only 65 iron => can afford exactly 1 u91)
        $village->awood = 10000;
        $village->aclay = 10000;
        $village->airon = 65;
        $village->acrop = 10000;
        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [91], [50]);

        $max = $this->tech->maxHeal(91, true);
        if ($max !== 1) {
            throw new Exception("Expected 1 u91 with 65 iron, got: $max");
        }

        // 64 iron => cannot afford even 1 u91 (needs ceil(130*1/2) = 65)
        $village->airon = 64;
        $max0 = $this->tech->maxHeal(91, true);
        if ($max0 !== 0) {
            throw new Exception("Expected 0 u91 with 64 iron, got: $max0");
        }

        // Zero crop => should return 0
        $village->airon = 1000;
        $village->acrop = 0;
        $maxCrop0 = $this->tech->maxHeal(91, true);
        if ($maxCrop0 !== 0) {
            throw new Exception("Expected 0 u91 with 0 crop, got: $maxCrop0");
        }

        echo "PASSED (exact bottleneck limit detection)\n";
    }

    private function testMaxHealOddUnitCosts($vid) {
        global $village;
        echo "[3] Testing odd unit training costs (u11 Clubswinger)... ";

        // u11 (Clubswinger): wood 95, clay 75, iron 40, crop 40
        // Heal cost for 1 unit: ceil(95/2) = 48 wood, ceil(75/2) = 38 clay, 20 iron, 20 crop
        // Heal cost for 2 units: ceil(95*2/2) = 95 wood, ceil(75*2/2) = 75 clay, 40 iron, 40 crop
        $village = new stdClass();
        $village->wid = $vid;
        $village->aclay = 500;
        $village->airon = 500;
        $village->acrop = 500;
        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [11], [10]);

        // With 47 wood: cannot heal 1 (needs 48)
        $village->awood = 47;
        $res = $this->tech->maxHeal(11, true);
        if ($res !== 0) {
            throw new Exception("Expected 0 u11 with 47 wood, got: $res");
        }

        // With 48 wood: can heal 1
        $village->awood = 48;
        $res1 = $this->tech->maxHeal(11, true);
        if ($res1 !== 1) {
            throw new Exception("Expected 1 u11 with 48 wood, got: $res1");
        }

        // With 94 wood: can heal 1 (needs 95 for 2)
        $village->awood = 94;
        $res1b = $this->tech->maxHeal(11, true);
        if ($res1b !== 1) {
            throw new Exception("Expected 1 u11 with 94 wood, got: $res1b");
        }

        // With 95 wood: can heal 2
        $village->awood = 95;
        $res2 = $this->tech->maxHeal(11, true);
        if ($res2 !== 2) {
            throw new Exception("Expected 2 u11 with 95 wood, got: $res2");
        }

        echo "PASSED (odd cost integer ceiling handled with 100% precision)\n";
    }

    private function testHospitalTemplateRendering($vid) {
        global $database, $village, $building, $technology, $generator, $session, $bid46, $id, $loopsame, $doublebuild, $master;
        echo "[4] Testing Templates/Build/46.tpl rendering with MAX recommendation... ";

        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [91, 92], [30, 15]);

        $village = new stdClass();
        $village->wid = $vid;
        $village->resarray = ['f20' => 5, 'f20t' => 46];
        $village->awood = 1000;
        $village->aclay = 1000;
        $village->airon = 1000;
        $village->acrop = 1000;
        $id = 20;
        $loopsame = 0; $doublebuild = 0; $master = 0;

        $session = new stdClass();
        $session->userinfo = ['gold' => 0];
        $session->gold = 0;

        $technology = $this->tech;
        $building = new class {
            public function isMax($t, $i) { return false; }
            public function isCurrent($id) { return 0; }
            public function isLoop($id) { return 0; }
            public function canBuild($t, $i) { return false; }
            public function resourceRequired($id, $type) { return ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 100, 'pop' => 1, 'time' => 60]; }
        };

        ob_start();
        include __DIR__ . '/../Templates/Build/46.tpl';
        $output = ob_get_clean();

        // Check thead structure
        if (strpos($output, '<thead>') === false || strpos($output, defined('MAX') ? MAX : 'Max') === false) {
            throw new Exception("Template missing thead or MAX column header");
        }

        // Check input field IDs
        if (strpos($output, 'id="hamt_91"') === false || strpos($output, 'id="hamt_92"') === false) {
            throw new Exception("Template missing id='hamt_91' or id='hamt_92'");
        }

        // Check clickable max links
        // For u91: 1000 iron => floor(2000/130) = 15. Wounded = 30. Max = 15.
        // For u92: 1000 iron => floor(2000/150) = 13. Wounded = 15. Max = 13.
        if (strpos($output, "document.getElementById('hamt_91').value=15") === false) {
            throw new Exception("Template missing onclick autofill for u91 (15)");
        }
        if (strpos($output, ">(15)</a>") === false) {
            throw new Exception("Template missing (15) max link for u91");
        }

        if (strpos($output, "document.getElementById('hamt_92').value=13") === false) {
            throw new Exception("Template missing onclick autofill for u92 (13)");
        }
        if (strpos($output, ">(13)</a>") === false) {
            throw new Exception("Template missing (13) max link for u92");
        }

        // Check unit cost details rendered (Pendekar Keris: 55|45|65|15)
        if (strpos($output, '55|') === false || strpos($output, '45|') === false) {
            throw new Exception("Template missing healing cost details for unit 91");
        }

        echo "PASSED (thead, id='hamt_XX', clickable (15) and (13) links rendered)\n";
    }

    private function testBigHospitalTemplateRendering($vid) {
        global $database, $village, $building, $technology, $generator, $session, $bid48, $id, $loopsame, $doublebuild, $master;
        echo "[5] Testing Templates/Build/48.tpl (Big Hospital) rendering... ";

        $this->db->clearHospital($vid);
        $this->db->addWounded($vid, [91], [25]);

        $village = new stdClass();
        $village->wid = $vid;
        $village->resarray = ['f20' => 5, 'f20t' => 48];
        $village->awood = 50000;
        $village->aclay = 50000;
        $village->airon = 50000;
        $village->acrop = 50000;
        $id = 20;
        $loopsame = 0; $doublebuild = 0; $master = 0;

        $session = new stdClass();
        $session->userinfo = ['gold' => 0];
        $session->gold = 0;

        $technology = $this->tech;
        $building = new class {
            public function isMax($t, $i) { return false; }
            public function isCurrent($id) { return 0; }
            public function isLoop($id) { return 0; }
            public function canBuild($t, $i) { return false; }
            public function resourceRequired($id, $type) { return ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 100, 'pop' => 1, 'time' => 60]; }
        };

        ob_start();
        include __DIR__ . '/../Templates/Build/48.tpl';
        $output = ob_get_clean();

        // High resources, 25 wounded => maxHeal should be 25
        if (strpos($output, "document.getElementById('hamt_91').value=25") === false) {
            throw new Exception("Template 48 missing onclick autofill for u91 (25)");
        }
        if (strpos($output, ">(25)</a>") === false) {
            throw new Exception("Template 48 missing (25) max link for u91");
        }

        echo "PASSED (Big Hospital renders MAX recommendation (25) correctly)\n";
    }

    private function testHealingExecutionWithMax($vid) {
        global $village, $database, $building;
        echo "[6] Testing procHeal execution using recommended max amount... ";

        $this->db->clearHospital($vid);
        $this->db->query("DELETE FROM " . TB_PREFIX . "healing WHERE vref = $vid");
        $this->db->addWounded($vid, [91], [30]);

        $village = new stdClass();
        $village->wid = $vid;
        $village->awood = 1000;
        $village->aclay = 1000;
        $village->airon = 1000;
        $village->acrop = 1000;

        $GLOBALS['building'] = new class {
            public function getTypeLevel($gid) { return $gid == 46 ? 5 : 0; }
        };

        // Recommended max for u91 with 1000 iron is 15
        $recommendedMax = $this->tech->maxHeal(91, true);
        if ($recommendedMax !== 15) {
            throw new Exception("Expected recommendedMax to be 15, got: $recommendedMax");
        }

        // Submit form with recommended max
        $post = [
            'ft' => 'heal',
            'hunit' => 91,
            'hamt' => [91 => $recommendedMax]
        ];

        $refMethod = new ReflectionMethod('Technology', 'procHeal');
        $refMethod->setAccessible(true);
        $refMethod->invoke($this->tech, $post);

        // Check wounded deducted by 15 => 30 - 15 = 15 remaining
        $w = $this->db->getWounded($vid);
        if ((int)($w['u91'] ?? 0) !== 15) {
            throw new Exception("Expected 15 wounded u91 remaining, got: " . var_export($w['u91'] ?? null, true));
        }

        // Check healing queue has 15 queued
        $h = $this->db->getHealing($vid);
        if (empty($h) || (int)$h[0]['amt'] !== 15) {
            throw new Exception("Expected 15 queued in healing, got: " . var_export($h, true));
        }

        echo "PASSED (15 wounded troops successfully submitted & queued)\n";
    }
}

$runner = new HospitalMaxHealTest($database);
$runner->run();
