<?php
/**
 * Test Suite: Hospital Healing in Progress Display Clarification
 *
 * Verifies:
 * 1. Templates/Build/46.tpl (Hospital) and 48.tpl (Big Hospital) render the healing in progress table
 *    with standard class="under_progress".
 * 2. Table header clearly defines columns: HEALING (Healing), DURATION (Duration), FINISHED (Finished).
 * 3. Each active healing queue row contains:
 *    - td.desc with troop icon and amount/name
 *    - td.dur with live timer span (<span id="timer...">) for remaining duration
 *    - td.fin with formatted completion time
 * 4. Next unit completion row (tr.next) is rendered with UNIT_FINISHED label and next unit countdown timer.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Generator.php';
@include_once __DIR__ . '/../GameEngine/Data/unitdata.php';
@include_once __DIR__ . '/../GameEngine/Data/buidata.php';

class HospitalHealingProgressDisplayTest {
    private $db;
    private $tech;

    public function __construct($database) {
        $this->db = $database;
        $this->tech = new Technology();
    }

    public function run() {
        echo "=== Running Hospital Healing Progress Display Tests ===\n\n";

        $testVid = 888902;
        $this->cleanupVillage($testVid);

        try {
            $this->testHospitalHealingProgressRendering($testVid);
            $this->testBigHospitalHealingProgressRendering($testVid);

            echo "\n✅ ALL HOSPITAL HEALING PROGRESS DISPLAY TESTS PASSED!\n";
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

    private function testHospitalHealingProgressRendering($vid) {
        global $village, $database, $building, $technology, $generator, $session, $bid46, $id, $loopsame, $doublebuild, $master;
        echo "[1] Testing Templates/Build/46.tpl healing progress table structure & timers... ";

        $now = time();
        $this->cleanupVillage($vid);

        // Queue 2 healing rows:
        // Row 1: 11 x u96 (Gajah Perang Bhayangkara), eachtime 230s, next in 200s, total finish in 200 + 10*230 = 2500s
        $this->db->query("INSERT INTO " . TB_PREFIX . "healing (vref, unit, amt, timestamp, eachtime, timestamp2) VALUES ($vid, 96, 11, $now, 230, " . ($now + 200) . ")");
        // Row 2: 5 x u96, eachtime 230s, next in 150s, total finish in 150 + 4*230 = 1070s
        $this->db->query("INSERT INTO " . TB_PREFIX . "healing (vref, unit, amt, timestamp, eachtime, timestamp2) VALUES ($vid, 96, 5, $now, 230, " . ($now + 150) . ")");

        $this->db->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($vid, 7, 'Hosp Disp Test', 1, 200, 10000, 10000, 10000, 10000, 80000, 80000, 10)");
        $this->db->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f20, f20t) VALUES ($vid, 20, 46)");

        $village = new stdClass();
        $village->wid = $vid;
        $village->resarray = ['f20' => 20, 'f20t' => 46];
        $village->awood = 10000;
        $village->aclay = 10000;
        $village->airon = 10000;
        $village->acrop = 10000;
        $id = 20;
        $loopsame = 0; $doublebuild = 0; $master = 0;

        $session = new stdClass();
        $session->userinfo = ['gold' => 0];
        $session->gold = 0;
        $session->timer = 0;

        $technology = $this->tech;
        $building = new class {
            public function isMax($t, $i) { return true; }
            public function isCurrent($id) { return 0; }
            public function isLoop($id) { return 0; }
            public function canBuild($t, $i) { return false; }
            public function resourceRequired($id, $type) { return ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 100, 'pop' => 1, 'time' => 60]; }
        };

        if (!isset($generator)) {
            $generator = new MyGenerator();
        }

        ob_start();
        include __DIR__ . '/../Templates/Build/46.tpl';
        $output = ob_get_clean();

        // 1. Table with under_progress
        if (strpos($output, 'id="hospital_healing" class="under_progress"') === false) {
            throw new Exception("Expected id='hospital_healing' class='under_progress' in 46.tpl");
        }

        // 2. Table headers: HEALING, DURATION, FINISHED
        $expectedHealingHeader = defined('HEALING') ? HEALING : 'Healing';
        if (strpos($output, $expectedHealingHeader) === false) {
            throw new Exception("Missing HEALING header in progress table");
        }
        if (strpos($output, DURATION) === false) {
            throw new Exception("Missing DURATION header in progress table");
        }
        if (strpos($output, FINISHED) === false) {
            throw new Exception("Missing FINISHED header in progress table");
        }

        // 3. Check rows rendered with duration timers
        if (strpos($output, '11 x Gajah Perang Bhayangkara') === false || strpos($output, '5 x Gajah Perang Bhayangkara') === false) {
            throw new Exception("Troop entries not rendered in 46.tpl");
        }
        if (strpos($output, 'id="timer1"') === false || strpos($output, 'id="timer2"') === false) {
            throw new Exception("Duration timers (timer1, timer2) not rendered for rows");
        }

        // 4. Check td.dur and td.fin classes
        if (strpos($output, '<td class="dur">') === false || strpos($output, '<td class="fin">') === false) {
            throw new Exception("Missing td.dur or td.fin classes in 46.tpl");
        }

        // 5. Check tr.next row with UNIT_FINISHED
        if (strpos($output, '<tr class="next">') === false) {
            throw new Exception("Missing tr.next row in 46.tpl");
        }
        if (strpos($output, UNIT_FINISHED) === false) {
            throw new Exception("Missing UNIT_FINISHED label in 46.tpl");
        }
        if (strpos($output, 'id="timer3"') === false) {
            throw new Exception("Missing next unit countdown timer (timer3) in 46.tpl");
        }

        echo "PASSED (table.under_progress, thead headers, row timers, and tr.next verified)\n";
    }

    private function testBigHospitalHealingProgressRendering($vid) {
        global $village, $database, $building, $technology, $generator, $session, $bid48, $id, $loopsame, $doublebuild, $master;
        echo "[2] Testing Templates/Build/48.tpl (Big Hospital) healing progress rendering... ";

        $now = time();
        $this->cleanupVillage($vid);

        $this->db->query("INSERT INTO " . TB_PREFIX . "healing (vref, unit, amt, timestamp, eachtime, timestamp2) VALUES ($vid, 91, 7, $now, 100, " . ($now + 80) . ")");
        $this->db->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($vid, 7, 'Big Hosp Disp Test', 1, 200, 10000, 10000, 10000, 10000, 80000, 80000, 10)");
        $this->db->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f20, f20t) VALUES ($vid, 15, 48)");

        $village = new stdClass();
        $village->wid = $vid;
        $village->resarray = ['f20' => 15, 'f20t' => 48];
        $village->awood = 10000;
        $village->aclay = 10000;
        $village->airon = 10000;
        $village->acrop = 10000;
        $id = 20;
        $loopsame = 0; $doublebuild = 0; $master = 0;

        $session = new stdClass();
        $session->userinfo = ['gold' => 0];
        $session->gold = 0;
        $session->timer = 0;

        $technology = $this->tech;
        $building = new class {
            public function isMax($t, $i) { return false; }
            public function isCurrent($id) { return 0; }
            public function isLoop($id) { return 0; }
            public function canBuild($t, $i) { return false; }
            public function resourceRequired($id, $type) { return ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 100, 'pop' => 1, 'time' => 60]; }
        };

        if (!isset($generator)) {
            $generator = new MyGenerator();
        }

        ob_start();
        include __DIR__ . '/../Templates/Build/48.tpl';
        $output = ob_get_clean();

        if (strpos($output, 'id="hospital_healing" class="under_progress"') === false) {
            throw new Exception("Expected id='hospital_healing' class='under_progress' in 48.tpl");
        }
        if (strpos($output, DURATION) === false || strpos($output, FINISHED) === false) {
            throw new Exception("Missing headers in 48.tpl");
        }
        if (strpos($output, '7 x Pendekar Keris') === false) {
            throw new Exception("Expected '7 x Pendekar Keris' in 48.tpl");
        }
        if (strpos($output, '<tr class="next">') === false || strpos($output, UNIT_FINISHED) === false) {
            throw new Exception("Missing tr.next or UNIT_FINISHED in 48.tpl");
        }

        echo "PASSED (Big Hospital progress table properly formatted)\n";
    }
}

$runner = new HospitalHealingProgressDisplayTest($database);
$runner->run();
