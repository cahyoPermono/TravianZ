<?php
/**
 * Test Combat Simulator for Tribe Nusantara (Tribe 10) & Hero HP Simulation
 */

function assertTrue($condition, $message) {
    if (!$condition) {
        echo "FAIL: " . $message . "\n";
        exit(1);
    }
}

echo "Testing Warsim Nusantara & Hero HP Simulation...\n";

require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Data/unitdata.php';
require_once __DIR__ . '/../GameEngine/Battle.php';

global $battle, $form;
$battle = new Battle();
$form = new stdClass();

// Test 1: Templates exist
$tplFiles = [
    __DIR__ . '/../Templates/Simulator/att_10.tpl',
    __DIR__ . '/../Templates/Simulator/def_10.tpl',
    __DIR__ . '/../Templates/Simulator/res_a10.tpl',
    __DIR__ . '/../Templates/Simulator/res_d10.tpl',
    __DIR__ . '/../Templates/Simulator/def_end.tpl',
];
foreach ($tplFiles as $tpl) {
    assertTrue(file_exists($tpl), "Template file must exist: " . basename($tpl));
}
echo "PASS: All Nusantara simulator template files exist.\n";

// Test 2: Hero Solo Simulation (Nusantara Attacker vs Roman Defender)
$_POST = [
    'a1_v' => 10,
    'a2_v1' => 1,
    'h_off' => 800,
    'h_off_bonus' => 10,
    'h_hp' => 100,
    'a2_1' => 5, // 5 Legionnaires (5 * 35 = 175 DI)
];
$battle->procSim($_POST);

assertTrue(!empty($_POST['result']), "Result must not be empty for Hero solo simulation");
assertTrue(isset($_POST['result']['Attack_points']), "Attack_points must exist");
assertTrue($_POST['result']['Attack_points'] >= 800, "Attack_points must include hero power");
assertTrue(!empty($_POST['result']['hero']), "Hero result must be populated");
assertTrue($_POST['result']['hero']['sent'] === true, "Hero sent flag must be true");
assertTrue($_POST['result']['hero']['dead'] === 0, "Hero should survive with superior power");
assertTrue($_POST['result']['hero']['remain_hp'] > 0, "Hero should have remaining HP");
echo "PASS: Hero solo simulation works successfully (Attack points: {$_POST['result']['Attack_points']}, Hero damage: -{$_POST['result']['hero']['damage']}%, Remain HP: {$_POST['result']['hero']['remain_hp']}%).\n";

// Test 3: Hero Death on Catastrophic Defense
$_POST = [
    'a1_v' => 10,
    'a2_v1' => 1,
    'h_off' => 100, // Very weak hero
    'h_hp' => 100,
    'a2_2' => 50, // 50 Praetorians = 3250 DI (Overwhelming defense)
];
$battle->procSim($_POST);

assertTrue(!empty($_POST['result']['hero']), "Hero result must be populated");
assertTrue($_POST['result']['hero']['dead'] === 1, "Hero must die against overwhelming army");
assertTrue($_POST['result']['hero']['remain_hp'] === 0, "Remaining HP must be 0 when hero dies");
assertTrue($_POST['result']['hero']['damage'] === 100, "Damage must be 100% when hero dies");
echo "PASS: Hero catastrophic death correctly simulated.\n";

// Test 4: Combined Army + Hero Simulation (Nusantara Attacker)
$_POST = [
    'a1_v' => 10,
    'a2_v1' => 1,
    'a1_1' => 100, // 100 Pendekar Keris
    'a1_5' => 20,  // 20 Kavaleri Berkuda
    'h_off' => 1000,
    'h_off_bonus' => 5,
    'h_hp' => 90,
    'a2_2' => 40,  // 40 Praetorians
];
$battle->procSim($_POST);

assertTrue(!empty($_POST['result']['casualties_attacker']), "Attacker casualties must be computed");
assertTrue($_POST['result']['Attack_points'] > 4000, "Army + Hero Attack_points must be combined");
assertTrue(!empty($_POST['result']['hero']), "Hero outcome must be computed in combined battle");
assertTrue($_POST['result']['hero']['start_hp'] === 90, "Hero start_hp must match input");
echo "PASS: Nusantara troops + Hero combined simulation works successfully.\n";

// Test 5: Nusantara as Defender (Tribe 10 Defender)
$_POST = [
    'a1_v' => 1,   // Romans attacking
    'a2_v10' => 1, // Nusantara defending
    'a1_3' => 50,  // 50 Imperians (50 * 70 = 3500 AP)
    'a2_91' => 30, // 30 Pendekar Keris
    'a2_92' => 30, // 30 Prajurit Tombak
];
$battle->procSim($_POST);

assertTrue(!empty($_POST['result']), "Result must be computed when Nusantara is defender");
assertTrue($_POST['result']['Defend_points'] > 0, "Defend points must be positive for Nusantara defenders");
echo "PASS: Nusantara as Defender simulation works successfully (Defend points: {$_POST['result']['Defend_points']}).\n";

echo "ALL COMBAT SIMULATOR TESTS PASSED!\n";
