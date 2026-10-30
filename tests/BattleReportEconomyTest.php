<?php
/**
 * Test Suite: Battle Report Economic Analysis (Profit & Loss / Casualties)
 * Tests unit cost retrieval, casualty calculations, loot revenue analysis,
 * profit/loss margins, and HTML rendering.
 */

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/BattleReportEconomy.php';

function assertTest($condition, $message) {
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "PASS: $message\n";
}

echo "=== Running BattleReportEconomyTest ===\n";

// 1. Test getUnitCost()
$legioCost = BattleReportEconomy::getUnitCost(1);
assertTest($legioCost['total'] === 400, "u1 (Legionnaire) total cost should be 400, got: " . $legioCost['total']);
assertTest($legioCost['wood'] === 120 && $legioCost['iron'] === 150, "u1 wood=120, iron=150");

$clubCost = BattleReportEconomy::getUnitCost(11);
assertTest($clubCost['total'] === 250, "u11 (Clubswinger) total cost should be 250, got: " . $clubCost['total']);
assertTest($clubCost['wood'] === 95 && $clubCost['crop'] === 40, "u11 wood=95, crop=40");

$phalanxCost = BattleReportEconomy::getUnitCost(21);
assertTest($phalanxCost['total'] === 315, "u21 (Phalanx) total cost should be 315, got: " . $phalanxCost['total']);

$kerisCost = BattleReportEconomy::getUnitCost(91);
assertTest($kerisCost['total'] === 360, "u91 (Prajurit Keris) total cost should be 360, got: " . $kerisCost['total']);

// 2. Test Profit Battle Scenario
// Attacker: Teuton (tribe 2)
// Sends Clubswingers, 2 died (cost 500)
// Loots 2000 of each resource (8000 total)
$dataProfit = array_fill(0, 310, '0');
$dataProfit[0] = '101'; // Attacker UID
$dataProfit[1] = '1001';
$dataProfit[2] = '2';   // Teuton
$dataProfit[3] = '50';  // 50 Clubswingers sent
$dataProfit[13] = '2';  // 2 Clubswingers dead
$dataProfit[23] = '2000'; // Wood
$dataProfit[24] = '2000'; // Clay
$dataProfit[25] = '2000'; // Iron
$dataProfit[26] = '2000'; // Crop
$dataProfit[27] = '10000'; // Capacity
$dataProfit[28] = '202'; // Defender UID
$dataProfit[29] = '2002';
$dataProfit[34] = '3';   // Gaul
$dataProfit[35] = '5';   // 5 Phalanx sent
$dataProfit[45] = '5';   // 5 Phalanx dead

$econProfit = BattleReportEconomy::calculate($dataProfit, 101, false);
assertTest($econProfit['attacker']['casualties_cost']['total'] === 500, "Attacker casualties cost should be 500, got: " . $econProfit['attacker']['casualties_cost']['total']);
assertTest($econProfit['loot']['total'] === 8000, "Total loot should be 8000, got: " . $econProfit['loot']['total']);
assertTest($econProfit['attacker']['net_balance']['total'] === 7500, "Net profit should be +7500, got: " . $econProfit['attacker']['net_balance']['total']);
assertTest($econProfit['attacker']['status'] === 'profit', "Attacker status should be 'profit'");
assertTest($econProfit['viewer_role'] === 'attacker', "Viewer role for UID 101 should be 'attacker'");

// Defender side in profit battle
assertTest($econProfit['defender']['casualties_cost']['total'] === (5 * 315), "Defender casualties cost should be 5*315=1575");
assertTest($econProfit['defender']['total_loss']['total'] === (1575 + 8000), "Defender total loss should be 1575 + 8000 = 9575");

// 3. Test Loss / Deficit Battle Scenario
// Attacker: Roman (tribe 1)
// 20 Legionnaires dead (cost 8000)
// Loots only 500 each (2000 total)
$dataLoss = array_fill(0, 310, '0');
$dataLoss[0] = '101';
$dataLoss[1] = '1001';
$dataLoss[2] = '1';   // Roman
$dataLoss[3] = '30';  // 30 Legios sent
$dataLoss[13] = '20'; // 20 Legios dead
$dataLoss[23] = '500';
$dataLoss[24] = '500';
$dataLoss[25] = '500';
$dataLoss[26] = '500';
$dataLoss[27] = '5000';
$dataLoss[28] = '202';

$econLoss = BattleReportEconomy::calculate($dataLoss, 101, false);
assertTest($econLoss['attacker']['casualties_cost']['total'] === 8000, "Attacker casualties cost should be 8000");
assertTest($econLoss['loot']['total'] === 2000, "Loot should be 2000");
assertTest($econLoss['attacker']['net_balance']['total'] === -6000, "Net loss should be -6000, got: " . $econLoss['attacker']['net_balance']['total']);
assertTest($econLoss['attacker']['status'] === 'loss', "Attacker status should be 'loss'");

// 4. Test Total Loss / No Troops Returned (3.tpl)
// 50 Clubswingers sent, all killed, loot 0
$dataTotalLoss = array_fill(0, 310, '0');
$dataTotalLoss[0] = '101';
$dataTotalLoss[1] = '1001';
$dataTotalLoss[2] = '2';  // Teuton
$dataTotalLoss[3] = '50'; // 50 Clubswingers sent
$dataTotalLoss[28] = '202';

$econTotalLoss = BattleReportEconomy::calculate($dataTotalLoss, 101, true);
assertTest($econTotalLoss['attacker']['total_dead_units'] === 50, "Total dead units in total loss should be 50");
assertTest($econTotalLoss['attacker']['casualties_cost']['total'] === 12500, "Total casualty cost should be 50*250 = 12500");
assertTest($econTotalLoss['loot']['total'] === 0, "Loot in total loss must be 0");
assertTest($econTotalLoss['attacker']['net_balance']['total'] === -12500, "Net balance should be -12500");
assertTest($econTotalLoss['attacker']['status'] === 'loss', "Status should be 'loss'");

// 5. Test HTML Rendering
$htmlProfit = BattleReportEconomy::renderHtml($econProfit);
assertTest(strpos($htmlProfit, 'SURPLUS / UNTUNG') !== false, "HTML for profit must contain SURPLUS / UNTUNG");
assertTest(strpos($htmlProfit, '+7,500') !== false, "HTML must contain +7,500");
assertTest(strpos($htmlProfit, 'Modal Korban Penyerang') !== false, "HTML must contain Modal Korban Penyerang");
assertTest(strpos($htmlProfit, '-500') !== false, "HTML must contain -500 for attacker casualties");

$htmlLoss = BattleReportEconomy::renderHtml($econLoss);
assertTest(strpos($htmlLoss, 'DEFISIT / RUGI') !== false, "HTML for loss must contain DEFISIT / RUGI");
assertTest(strpos($htmlLoss, '-6,000') !== false, "HTML must contain -6,000");

// Viewer as defender
$econDefView = BattleReportEconomy::calculate($dataProfit, 202, false);
assertTest($econDefView['viewer_role'] === 'defender', "Viewer role for UID 202 should be 'defender'");
$htmlDef = BattleReportEconomy::renderHtml($econDefView);
assertTest(strpos($htmlDef, 'RINGKASAN KERUGIAN DESA BERTAHAN') !== false, "Defender HTML must contain defender summary");

echo "=== All BattleReportEconomyTest tests passed! ===\n";
