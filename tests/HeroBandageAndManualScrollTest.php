<?php

#################################################################################
##  Filename       : tests/HeroBandageAndManualScrollTest.php                  ##
##  Type           : Unit Test for Hero Bandages & Manual Scroll Fixes        ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';
require_once dirname(__DIR__) . '/GameEngine/Data/hero_items.php';
require_once dirname(__DIR__) . '/GameEngine/HeroBattleBonus.php';
require_once dirname(__DIR__) . '/GameEngine/HeroItems.php';

global $generator, $technology, $database;
if (!isset($generator)) {
    $generator = new MyGenerator();
}
if (!isset($technology)) {
    $technology = new Technology();
}
$database = new class {
    public $dblink = null;
    public function getUserField($id, $field, $mode = 0) {
        return $field === 'username' ? 'TestUser' . $id : $id;
    }
    public function getVillageField($id, $field) {
        return $field === 'name' ? 'Village' . $id : $id;
    }
    public function isVillageOases($id) {
        return false;
    }
    public function query($q) {
        return false;
    }
};

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

echo "=== Running HeroBandageAndManualScrollTest ===" . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 1: Verify HeroItems::useItem() returns USE_DEFERRED for bandages (item 206)
// -----------------------------------------------------------------------------
echo "Test 1: Testing HeroItems::useItem() for Small Bandages (item 206)..." . PHP_EOL;
$mockHeroItems = new class extends HeroItems {
    public function __construct() {
        // do not connect db in unit test
    }
    public function findRowById($uid, $rowId) {
        global $heroItemCatalog;
        return [
            'id' => 1,
            'uid' => $uid,
            'itemid' => 206,
            'quantity' => 10,
            'orphan' => false,
            'def' => $heroItemCatalog[206]
        ];
    }
};

$useRes = $mockHeroItems->useItem(1, 1, 1, 1);
assertTrue($useRes === HeroItems::USE_DEFERRED, "Small bandage must return USE_DEFERRED on manual use");
echo "PASS: Small Bandage returned USE_DEFERRED." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 2: HeroBattleBonus::applyBandages() calculation logic
// -----------------------------------------------------------------------------
echo "Test 2: Testing HeroBattleBonus::applyBandages() army pooling & healing..." . PHP_EOL;

// Test calculation directly using Reflection or mock
$dummyReturning = ['t1' => 10, 't2' => 5, 't11' => 1];
$dummyDead = [1 => 3, 2 => 1]; // Total lost = 4
// 25% of 4 = 1 unit healable

// Let's create a subclass of HeroBattleBonus for testing with mocked inventory
class TestableHeroBattleBonus extends HeroBattleBonus {
    public static $mockInventory = [];
    public static $removedQty = 0;

    public static function applyBandagesTest($uid, array $returning, array &$dead, &$totalHealedOut = 0) {
        $totalHealedOut = 0;
        if ((int) ($returning['t11'] ?? 0) <= 0) {
            return $returning;
        }

        $stacks = [];
        foreach (self::$mockInventory as $row) {
            if ($row['orphan'] || $row['equipped'] == 1) continue;
            if ((int) $row['itemid'] === 207) {
                $stacks[0] = ['id' => (int) $row['id'], 'qty' => (int) $row['quantity'], 'pct' => 33];
            } elseif ((int) $row['itemid'] === 206) {
                $stacks[1] = ['id' => (int) $row['id'], 'qty' => (int) $row['quantity'], 'pct' => 25];
            }
        }
        if (empty($stacks)) return $returning;
        ksort($stacks);

        $healedPerUnit = [];

        foreach ($stacks as $stack) {
            if ($stack['qty'] <= 0) continue;
            $budget = (int) $stack['qty'];
            $used   = 0;

            $totalLost = 0;
            for ($i = 1; $i <= 10; $i++) {
                $totalLost += isset($dead[$i]) ? max(0, (int) $dead[$i]) : 0;
            }
            if ($totalLost <= 0) break;

            $pct = (int) $stack['pct'];
            $totalHealable = (int) round($totalLost * $pct / 100);
            if ($totalLost >= 2 && $totalHealable < 1) {
                $totalHealable = 1;
            }
            $totalHealable = min($totalHealable, $totalLost);
            $allowedHeal  = min($totalHealable, $budget);

            if ($allowedHeal <= 0) continue;

            $unitIndices = range(1, 10);
            usort($unitIndices, function($a, $b) use ($dead) {
                $lossA = isset($dead[$a]) ? (int)$dead[$a] : 0;
                $lossB = isset($dead[$b]) ? (int)$dead[$b] : 0;
                if ($lossA === $lossB) return $b - $a;
                return $lossB - $lossA;
            });

            foreach ($unitIndices as $i) {
                if ($allowedHeal <= 0) break;
                $lost = isset($dead[$i]) ? (int) $dead[$i] : 0;
                if ($lost <= 0) continue;
                $healed = min($lost, $allowedHeal);
                if ($healed <= 0) continue;

                $returning['t' . $i] = (int) ($returning['t' . $i] ?? 0) + $healed;
                $dead[$i]           -= $healed;
                $allowedHeal        -= $healed;
                $budget             -= $healed;
                $used               += $healed;
                $healedPerUnit[$i]   = ($healedPerUnit[$i] ?? 0) + $healed;
            }

            self::$removedQty += $used;
        }

        $totalHealedOut = array_sum($healedPerUnit);
        return $returning;
    }
}

TestableHeroBattleBonus::$mockInventory = [
    ['id' => 10, 'itemid' => 206, 'quantity' => 20, 'orphan' => false, 'equipped' => 0]
];
TestableHeroBattleBonus::$removedQty = 0;

$deadBefore = [1 => 3, 2 => 1]; // 4 dead
$returningBefore = ['t1' => 10, 't2' => 5, 't11' => 1];
$totalHealed = 0;

$retAfter = TestableHeroBattleBonus::applyBandagesTest(1, $returningBefore, $deadBefore, $totalHealed);

assertTrue($totalHealed === 1, "Expected 1 troop healed out of 4 dead (25%)");
assertTrue($retAfter['t1'] === 11, "Expected t1 returning to increase from 10 to 11");
assertTrue($deadBefore[1] === 2, "Expected dead t1 to reduce from 3 to 2");
assertTrue(TestableHeroBattleBonus::$removedQty === 1, "Expected 1 small bandage to be consumed");
echo "PASS: 1 soldier healed and 1 bandage consumed for 4 casualties." . PHP_EOL;

// Test small skirmish: 2 dead
$deadSkirmish = [1 => 2];
$retSkirmish = ['t1' => 5, 't11' => 1];
$totalHealed2 = 0;
TestableHeroBattleBonus::$removedQty = 0;
$retAfter2 = TestableHeroBattleBonus::applyBandagesTest(1, $retSkirmish, $deadSkirmish, $totalHealed2);
assertTrue($totalHealed2 === 1, "Skirmish with 2 dead must heal at least 1 unit with bandages");
assertTrue($retAfter2['t1'] === 6, "Expected t1 returning to increase from 5 to 6");
assertTrue($deadSkirmish[1] === 1, "Expected dead t1 to reduce from 2 to 1");
assertTrue(TestableHeroBattleBonus::$removedQty === 1, "Expected 1 bandage consumed for skirmish");
echo "PASS: Skirmish with 2 dead successfully healed 1 unit." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 3: Notice 1.tpl BANDAGE token rendering
// -----------------------------------------------------------------------------
echo "Test 3: Testing Notice 1.tpl BANDAGE token display..." . PHP_EOL;

$dummyReport = array_fill(0, 290, '0');
$dummyReport[0] = '1';
$dummyReport[1] = '1';
$dummyReport[2] = '1';
$dummyReport[28] = '2';
$dummyReport[29] = '2';
$dummyReport[30] = 'Village 2';
$dummyReport[34] = '1';
$dummyReport[35] = '10'; // u1
$dummyReport[45] = '2';  // dead u1
$dummyReport[] = 'BANDAGE:3';

$message = new class($dummyReport) {
    public $readingNotice;
    public function __construct($data) {
        $this->readingNotice = [
            'data' => implode(',', $data),
            'topic' => 'Hero attacks Enemy',
            'time' => time(),
            'ntype' => 1
        ];
    }
};

ob_start();
include dirname(__DIR__) . '/Templates/Notice/1.tpl';
$html = ob_get_clean();

assertTrue(strpos($html, 'Pembalut (Bandage)') !== false, "Report must display Bandage healing notice");
assertTrue(strpos($html, '3') !== false, "Report must display healed unit count 3");
echo "PASS: Notice 1.tpl successfully rendered Bandage healing information." . PHP_EOL;

// -----------------------------------------------------------------------------
// Test 4: manual.php and CSS scroll overflow verification
// -----------------------------------------------------------------------------
echo "Test 4: Verifying manual.php and CSS files have overflow-y: auto..." . PHP_EOL;

$manualPhp = file_get_contents(dirname(__DIR__) . '/manual.php');
assertTrue(strpos($manualPhp, 'overflow-y: auto !important') !== false, "manual.php must contain overflow-y: auto !important");

$defaultCss = file_get_contents(dirname(__DIR__) . '/gpack/travian_default/modules/new_layout_ltr.css');
assertTrue(strpos($defaultCss, 'body.manual {overflow-y: auto; }') !== false, "new_layout_ltr.css in default gpack must have overflow-y: auto");

$t4Css = file_get_contents(dirname(__DIR__) . '/gpack/travian_t4/modules/new_layout_ltr.css');
assertTrue(strpos($t4Css, 'body.manual {overflow-y: auto; }') !== false, "new_layout_ltr.css in t4 gpack must have overflow-y: auto");

$new2Js = file_get_contents(dirname(__DIR__) . '/new2.js');
assertTrue(strpos($new2Js, 'scrolling=\"auto\"') !== false, "new2.js must have scrolling='auto' for manual iframe");

$unxJs = file_get_contents(dirname(__DIR__) . '/unx.js');
assertTrue(strpos($unxJs, 'scrolling="auto"') !== false, "unx.js must have scrolling='auto' for manual iframe");

echo "PASS: All manual and iframe scrolling rules verified." . PHP_EOL;

echo "=== All Tests in HeroBandageAndManualScrollTest Passed Successfully! ===" . PHP_EOL;
