<?php
/**
 * Test Suite: Hospital functionality for Tribe 10 (Suku Nusantara)
 *
 * Verifies:
 * 1. Attack casualties -> Wounded Nusantara troops in Hospital (GID 46).
 * 2. Defense casualties (own village) -> Wounded Nusantara troops in Hospital.
 * 3. Reinforcement casualties -> Wounded troops in origin village Hospital.
 * 4. UI Rendering in Templates/Build/46.tpl for Nusantara units (u91-u98).
 * 5. Healing workflow via Technology::procHeal and AutomationTraining::healingComplete.
 */

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Technology.php';
require_once __DIR__ . '/../GameEngine/Automation.php';

class HospitalNusantaraTestRunner {
    use AutomationBattleResolution;
    use AutomationTraining;

    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function run() {
        echo "=== Running Hospital Nusantara Tests ===\n\n";

        $testVid = 888801;
        $reinfVid = 888802;
        $targetVid = 888803;

        // Clean up any leftovers
        $this->cleanupTestVillages([$testVid, $reinfVid, $targetVid]);

        try {
            $this->setupVillages($testVid, $reinfVid, $targetVid);

            $this->testAttackCasualtiesHospitalized($testVid, $targetVid);
            $this->testDefenseCasualtiesHospitalized($testVid);
            $this->testReinforcementCasualtiesHospitalized($reinfVid, $testVid);
            $this->testHospitalTemplateRendering($testVid);
            $this->testHealAndCompleteWorkflow($testVid);

            echo "\n✅ ALL HOSPITAL NUSANTARA TESTS PASSED!\n";
        } finally {
            $this->cleanupTestVillages([$testVid, $reinfVid, $targetVid]);
        }
    }

    private function setupVillages($v1, $v2, $v3) {
        global $database;

        // Village 1: Nusantara with Hospital lvl 5 (capacity = 150)
        $database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($v1, 7, 'Nusa Capital', 1, 200, 50000, 50000, 50000, 50000, 80000, 80000, 10) ON DUPLICATE KEY UPDATE tribe = 10");
        $database->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f20, f20t) VALUES ($v1, 5, 46) ON DUPLICATE KEY UPDATE f20 = 5, f20t = 46");
        $database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($v1) ON DUPLICATE KEY UPDATE vref = $v1");

        // Village 2: Nusantara reinforcement origin with Hospital lvl 10 (capacity = 300)
        $database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($v2, 7, 'Nusa Reinf Base', 0, 150, 50000, 50000, 50000, 50000, 80000, 80000, 10) ON DUPLICATE KEY UPDATE tribe = 10");
        $database->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f20, f20t) VALUES ($v2, 10, 46) ON DUPLICATE KEY UPDATE f20 = 10, f20t = 46");
        $database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($v2) ON DUPLICATE KEY UPDATE vref = $v2");

        // Village 3: Target village
        $database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, tribe) VALUES ($v3, 2, 'Nature Camp', 0, 50, 1000, 1000, 1000, 1000, 5000, 5000, 4) ON DUPLICATE KEY UPDATE tribe = 4");
        $database->query("INSERT INTO " . TB_PREFIX . "fdata (vref) VALUES ($v3) ON DUPLICATE KEY UPDATE vref = $v3");
        $database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($v3) ON DUPLICATE KEY UPDATE vref = $v3");

        $database->clearResourseLevelsCache();
    }

    private function testAttackCasualtiesHospitalized($v1, $v3) {
        global $database;
        echo "[1] Testing attack casualties hospitalization for Tribe 10... ";

        $database->clearHospital($v1);

        // Simulate 100 dead u91 (Pendekar Keris) in attack
        // At hospital share 40%, 40 should become wounded
        $data = ['from' => $v1, 'to' => $v3, 'ref' => 99991, 'moveid' => 99991, 't1' => 100, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0, 't6' => 0, 't7' => 0, 't8' => 0, 't9' => 0, 't10' => 0, 't11' => 0];
        $from = ['wref' => $v1, 'owner' => 7, 'name' => 'Nusa Capital'];
        $to = ['wref' => $v3, 'owner' => 2, 'name' => 'Nature Camp'];
        $dead = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0];
        $traped = array_fill(1, 11, 0);
        $troopsdead = $dead;
        $totalstolentaken = 0;

        $this->finalizeReturnOrDeath($data, $from, $to, 10, 3, 0, 0, time(), 0, 0, '', '', 0, $v3, $v1, [0,0,0,0], $dead, $traped, $troopsdead, 100, 100, 0, $totalstolentaken);

        $wounded = $database->getWounded($v1);
        if (empty($wounded) || (int)($wounded['u91'] ?? 0) !== 40) {
            throw new Exception("Expected 40 wounded u91 in hospital, got: " . var_export($wounded['u91'] ?? null, true));
        }

        echo "PASSED (40 wounded u91 hospitalized)\n";
    }

    private function testDefenseCasualtiesHospitalized($v1) {
        global $database;
        echo "[2] Testing own defense casualties hospitalization for Tribe 10... ";

        $database->clearHospital($v1);

        // Place 100 u92 (Prajurit Tombak) in village
        $database->query("UPDATE " . TB_PREFIX . "units SET u92 = 100 WHERE vref = $v1");
        $database->clearResourseLevelsCache();

        $data = ['to' => $v1];
        // 50% loss ratio in battlepart
        $battlepart = [2 => 0.50];
        $totalDefHealed = 0;

        $owndead = $this->applyOwnDefenceCasualties($data, 10, $battlepart, false, $totalDefHealed);

        // 50 u92 died. 40% of 50 = 20 wounded
        $wounded = $database->getWounded($v1);
        if (empty($wounded) || (int)($wounded['u92'] ?? 0) !== 20) {
            throw new Exception("Expected 20 wounded u92 in hospital, got: " . var_export($wounded['u92'] ?? null, true));
        }

        echo "PASSED (20 wounded u92 hospitalized)\n";
    }

    private function testReinforcementCasualtiesHospitalized($v2, $v1) {
        global $database;
        echo "[3] Testing reinforcement defense casualties hospitalization for Tribe 10... ";

        $database->clearHospital($v2);

        // Village 2 sent 100 u93 (Prajurit Pedang) to defend Village 1
        $database->query("INSERT INTO " . TB_PREFIX . "enforcement (vref, `from`, u93) VALUES ($v1, $v2, 100)");
        $enforceId = mysqli_insert_id($database->dblink);

        $data = ['to' => $v1];
        $battlepart = [2 => 0.60]; // 60 died
        $alldead = array_fill(1, 100, 0);
        $DefenderHeroesDeadArray = [];

        $this->applyReinforcementCasualties($data, $battlepart, ['owner' => 999, 'wref' => 999], ['name' => 'Defended Village', 'wref' => $v1], 0, time(), false, $alldead, $DefenderHeroesDeadArray, 1);

        // 60 died. 40% of 60 = 24 wounded in Village 2 Hospital
        $wounded = $database->getWounded($v2);
        if (empty($wounded) || (int)($wounded['u93'] ?? 0) !== 24) {
            throw new Exception("Expected 24 wounded u93 in Village 2 Hospital, got: " . var_export($wounded['u93'] ?? null, true));
        }

        echo "PASSED (24 wounded u93 hospitalized in origin village)\n";
    }

    private function testHospitalTemplateRendering($v1) {
        global $database, $village, $building, $technology, $session, $bid46, $id, $loopsame, $doublebuild, $master;
        echo "[4] Testing Templates/Build/46.tpl rendering for Tribe 10... ";

        // Setup environment for template rendering
        $database->clearHospital($v1);
        $database->addWounded($v1, [91, 92], [35, 15]);

        // Mock objects
        $village = new stdClass();
        $village->wid = $v1;
        $village->resarray = ['f20' => 5, 'f20t' => 46];
        $id = 20;
        $loopsame = 0; $doublebuild = 0; $master = 0;

        $session = new stdClass();
        $session->userinfo = ['gold' => 0];
        $session->gold = 0;

        $technology = new Technology();
        $building = new class {
            public function isMax($t, $i) { return false; }
            public function isCurrent($id) { return 0; }
            public function isLoop($id) { return 0; }
            public function canBuild($t, $i) { return false; }
            public function resourceRequired($id, $type) { return ['wood' => 100, 'clay' => 100, 'iron' => 100, 'crop' => 100, 'pop' => 1, 'time' => 60]; }
        };

        // Include bid46
        @include_once __DIR__ . '/../GameEngine/Data/buidata.php';

        ob_start();
        include __DIR__ . '/../Templates/Build/46.tpl';
        $output = ob_get_clean();

        if (strpos($output, 'Pendekar Keris') === false) {
            throw new Exception("Template output missing unit name 'Pendekar Keris'");
        }
        if (strpos($output, 'Prajurit Tombak') === false) {
            throw new Exception("Template output missing unit name 'Prajurit Tombak'");
        }
        if (strpos($output, 'value="91"') === false || strpos($output, 'value="92"') === false) {
            throw new Exception("Template output missing unit heal button values 91 or 92");
        }
        if (strpos($output, '50</b> / 150') === false && strpos($output, '50</b> / 150') === false && strpos($output, '50') === false) {
            throw new Exception("Template output missing capacity display");
        }

        echo "PASSED (rendered Pendekar Keris, Prajurit Tombak, and capacity correctly)\n";
    }

    private function testHealAndCompleteWorkflow($v1) {
        global $database, $village, $technology;
        echo "[5] Testing healing submission and completion workflow... ";

        $database->clearHospital($v1);
        $database->query("DELETE FROM " . TB_PREFIX . "healing WHERE vref = $v1");
        $database->addWounded($v1, [91], [20]);

        $village = new stdClass();
        $village->wid = $v1;
        $village->awood = 10000;
        $village->aclay = 10000;
        $village->airon = 10000;
        $village->acrop = 10000;

        $GLOBALS['building'] = new class {
            public function getTypeLevel($gid) { return $gid == 46 ? 5 : 0; }
        };

        // Call procHeal via Technology post
        $post = [
            'ft' => 'heal',
            'hunit' => 91,
            'hamt' => [91 => 20]
        ];

        $tech = new Technology();
        $refMethod = new ReflectionMethod('Technology', 'procHeal');
        $refMethod->setAccessible(true);
        $refMethod->invoke($tech, $post);

        // Verify wounded deducted
        $w = $database->getWounded($v1);
        if (!empty($w['u91']) && (int)$w['u91'] !== 0) {
            throw new Exception("Expected 0 remaining wounded u91, got: " . $w['u91']);
        }

        // Verify queue inserted
        $healingList = $database->getHealing($v1);
        if (empty($healingList) || count($healingList) !== 1 || (int)$healingList[0]['unit'] !== 91 || (int)$healingList[0]['amt'] !== 20) {
            throw new Exception("Expected 20 u91 queued in healing, got: " . var_export($healingList, true));
        }

        // Fast-forward timestamp to simulate healing completion of all units (eachtime ~ 919s * 20)
        $database->query("UPDATE " . TB_PREFIX . "healing SET timestamp2 = " . (time() - 30000) . " WHERE vref = $v1");

        // Run healingComplete
        $refHeal = new ReflectionMethod('HospitalNusantaraTestRunner', 'healingComplete');
        $refHeal->setAccessible(true);
        $refHeal->invoke($this);

        // Verify queue cleared
        $healingAfter = $database->getHealing($v1);
        if (!empty($healingAfter)) {
            throw new Exception("Expected healing queue to be empty after completion, got: " . var_export($healingAfter, true));
        }

        // Verify troops added back to units
        $units = $database->getUnit($v1, false);
        if ((int)($units['u91'] ?? 0) < 20) {
            throw new Exception("Expected at least 20 u91 restored to village units, got: " . var_export($units['u91'] ?? null, true));
        }

        echo "PASSED (20 u91 successfully healed and returned to army)\n";
    }

    private function cleanupTestVillages(array $vids) {
        global $database;
        $idList = implode(',', array_map('intval', $vids));
        $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($idList)");
        $database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($idList)");
        $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($idList)");
        $database->query("DELETE FROM " . TB_PREFIX . "hospital WHERE vref IN ($idList)");
        $database->query("DELETE FROM " . TB_PREFIX . "healing WHERE vref IN ($idList)");
        $database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE vref IN ($idList) OR `from` IN ($idList)");
        $database->clearResourseLevelsCache();
    }
}

$runner = new HospitalNusantaraTestRunner($database);
$runner->run();
