<?php

/**
 * Unit Test for Admin Hero Inventory Manager (heroManager.tpl)
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/hero_items.php';
require_once __DIR__ . '/../GameEngine/HeroItems.php';

class AdminHeroManagerTest {
    private int $passes = 0;
    private int $fails = 0;

    private function assert(bool $condition, string $testName): void {
        if ($condition) {
            $this->passes++;
            echo "  [PASS] $testName\n";
        } else {
            $this->fails++;
            echo "  [FAIL] $testName\n";
        }
    }

    public function runAll(): void {
        global $database;
        echo "=== Running AdminHeroManagerTest ===\n";

        // Cari satu user aktif untuk testing (bukan Nature/Natars)
        $users = $database->query_return("SELECT id, username, tribe FROM " . TB_PREFIX . "users WHERE id > 4 AND access = 2 LIMIT 1");
        if (empty($users[0])) {
            $users = $database->query_return("SELECT id, username, tribe FROM " . TB_PREFIX . "users WHERE id > 3 LIMIT 1");
        }
        $testUid = (int)$users[0]['id'];
        $testUsername = $users[0]['username'];
        $testTribe = (int)($users[0]['tribe'] ?? 1);
        echo "Using test user: $testUsername (UID: $testUid)\n";

        // Pastikan hero row ada
        $hCheck = $database->query_return("SELECT heroid FROM " . TB_PREFIX . "hero WHERE uid = $testUid LIMIT 1");
        if (empty($hCheck)) {
            $baseU = max(1, ($testTribe - 1) * 10 + 1);
            $database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `production`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `autoregen`, `lastupdate`) VALUES ($testUid, $baseU, 'Hero Test', 0, 0, 0, 0, 100, 0, 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, " . time() . ")");
        }

        $heroItems = new HeroItems();

        // 1. Test addItem for single equipment (Helmet of Awareness #1)
        $preInv = $heroItems->getInventory($testUid);
        $preCount = count($preInv);

        $itemId = 1; // Helmet of Awareness
        $grantedId = $heroItems->addItem($testUid, $itemId, 1);
        $this->assert($grantedId > 0, "addItem granted equipment #1 successfully (Row ID: $grantedId)");

        $postInv = $heroItems->getInventory($testUid);
        $this->assert(count($postInv) === $preCount + 1, "Inventory count increased by 1 for equipment");

        // 2. Test addItem for stackable consumable (Cage #208)
        $preCageCount = 0;
        foreach ($postInv as $r) {
            if ((int)$r['itemid'] === 208) {
                $preCageCount = (int)$r['quantity'];
                break;
            }
        }

        $grantedCageId = $heroItems->addItem($testUid, 208, 15);
        $this->assert($grantedCageId > 0, "addItem granted 15x Cages successfully");

        $postCageInv = $heroItems->getInventory($testUid);
        $newCageCount = 0;
        foreach ($postCageInv as $r) {
            if ((int)$r['itemid'] === 208) {
                $newCageCount = (int)$r['quantity'];
                break;
            }
        }
        $this->assert($newCageCount === $preCageCount + 15, "Stackable consumable quantity increased by 15 (now: $newCageCount)");

        // 3. Test Silver adjustment
        $preSilver = $heroItems->getSilver($testUid);
        $database->query("UPDATE " . TB_PREFIX . "hero SET silver = silver + 500 WHERE uid = $testUid");
        $postSilver = $heroItems->getSilver($testUid);
        $this->assert($postSilver === $preSilver + 500, "Silver balance correctly incremented by +500");

        // 4. Test Clean Up: Delete granted test equipment row
        $stmt = $database->dblink->prepare("DELETE FROM " . TB_PREFIX . "hero_items WHERE id = ? AND uid = ? LIMIT 1");
        $stmt->bind_param('ii', $grantedId, $testUid);
        $stmt->execute();
        $this->assert($stmt->affected_rows > 0, "Item row #$grantedId deleted cleanly");
        $stmt->close();

        // Cleanup consumable delta
        if ($preCageCount > 0) {
            $database->query("UPDATE " . TB_PREFIX . "hero_items SET quantity = $preCageCount WHERE id = $grantedCageId");
        } else {
            $database->query("DELETE FROM " . TB_PREFIX . "hero_items WHERE id = $grantedCageId");
        }
        // Rollback silver
        $database->query("UPDATE " . TB_PREFIX . "hero SET silver = $preSilver WHERE uid = $testUid");

        echo "\nResults: {$this->passes} passed, {$this->fails} failed.\n";
        if ($this->fails > 0) {
            exit(1);
        }
    }
}

$test = new AdminHeroManagerTest();
$test->runAll();
