<?php
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/hero_items.php';
require_once __DIR__ . '/../GameEngine/HeroItems.php';
require_once __DIR__ . '/../GameEngine/HeroBattleBonus.php';
require_once __DIR__ . '/../GameEngine/Technology.php';

echo "=== Running CavalryPedangSumpingTest ===" . PHP_EOL;

// 1. Verify Item 372 definition in heroItemCatalog
global $heroItemCatalog;
$item372 = $heroItemCatalog[372] ?? null;

if (!$item372) {
    die("FAIL: Item 372 not found in catalog\n");
}
if (($item372['bonus'][HB_FIGHT] ?? 0) !== 300) {
    die("FAIL: Expected HB_FIGHT = 300, got: " . ($item372['bonus'][HB_FIGHT] ?? 'none') . "\n");
}
if (($item372['bonus'][HB_TRAIN_CAV_SPEED] ?? 0) !== 200) {
    die("FAIL: Expected HB_TRAIN_CAV_SPEED = 200, got: " . ($item372['bonus'][HB_TRAIN_CAV_SPEED] ?? 'none') . "\n");
}
echo "PASS: Item 372 definition has HB_FIGHT = 300 and HB_TRAIN_CAV_SPEED = 200.\n";

// 2. Verify human-readable bonus text
$bonusText = heroItemBonusText(372);
echo "Bonus text: $bonusText\n";
if (strpos($bonusText, '+300') === false || strpos($bonusText, '+200% cavalry training speed') === false) {
    die("FAIL: Bonus text does not reflect +300 fighting strength and +200% cavalry training speed: $bonusText\n");
}
echo "PASS: heroItemBonusText(372) renders correctly.\n";

// 3. Verify Technology cavalry training speed application
class TestTechnology extends Technology {
    public function testApplyHeroTrainingBonus($each, $unit, array $footies, array $calvary) {
        $reflection = new ReflectionClass(parent::class);
        $method = $reflection->getMethod('applyHeroTrainingBonus');
        $method->setAccessible(true);
        return $method->invoke($this, $each, $unit, $footies, $calvary);
    }
    public function testHeroTrainingPercent($unit, array $footies, array $calvary) {
        $reflection = new ReflectionClass(parent::class);
        $method = $reflection->getMethod('heroTrainingPercent');
        $method->setAccessible(true);
        return $method->invoke($this, $unit, $footies, $calvary);
    }
}

// Mock HeroBattleBonus to simulate hero with item 372 equipped
$testUid = 999999;
$testWid = 888888;
global $village, $database;
$village = new stdClass();
$village->wid = $testWid;

// Mock database getVillageField
$reflectionCache = new ReflectionProperty('HeroBattleBonus', 'cache');
$reflectionCache->setAccessible(true);
$reflectionCache->setValue(null, [
    $testUid => [
        HB_FIGHT => 300,
        HB_TRAIN_CAV => 0,
        HB_TRAIN_CAV_SPEED => 200,
        HB_TRAIN_INF => 0,
        HB_UNIT_BONUS => [95 => 10]
    ]
]);

// Also stub getVillageField in database for testWid -> testUid
mysqli_query($database->dblink, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $testWid");
mysqli_query($database->dblink, "INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name) VALUES ($testWid, $testUid, 'Test Town')");

$tech = new TestTechnology();
$footies = [1, 2, 3, 11, 12, 13, 14, 21, 22, 31, 32, 33, 34, 41, 42, 43, 44, 51, 52, 61, 62, 63, 71, 72, 73, 74, 81, 82, 83, 84, 91, 92, 93];
$calvary = [4, 5, 6, 15, 16, 23, 24, 25, 26, 35, 36, 45, 46, 53, 54, 55, 56, 64, 65, 66, 75, 76, 85, 86, 94, 95, 96];

// Test u95 (Kavaleri Berkuda - Cavalry): base 2500s -> should become round(2500 / 3) = 833s
$resCavalry = $tech->testApplyHeroTrainingBonus(2500, 95, $footies, $calvary);
if ($resCavalry !== 833) {
    die("FAIL: Expected cavalry time 2500 / 3 = 833, got: $resCavalry\n");
}
echo "PASS: Cavalry training time reduced 3x (+200% speed) from 2500s to 833s!\n";

// Test u91 (Pendekar Keris - Infantry): should remain untouched at 1400s
$resInfantry = $tech->testApplyHeroTrainingBonus(1400, 91, $footies, $calvary);
if ($resInfantry !== 1400) {
    die("FAIL: Expected infantry time to remain 1400, got: $resInfantry\n");
}
echo "PASS: Infantry training time untouched at 1400s.\n";

// Test heroTrainingPercent: probe 1,000,000 / 3 = 333,333 -> (1000000 - 333333) / 10000 = 67%
$pct = $tech->testHeroTrainingPercent(95, $footies, $calvary);
if ($pct !== 67) {
    die("FAIL: Expected displayed hero percent = 67%, got: $pct%\n");
}
echo "PASS: Hero displayed reduction percent = 67% (matches 3x speed increase).\n";

// Cleanup test row
mysqli_query($database->dblink, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $testWid");

echo "=== ALL CavalryPedangSumpingTest PASSED SUCCESSFULLY! ===\n";
