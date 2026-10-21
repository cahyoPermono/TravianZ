<?php

#################################################################################
##  Filename       : tests/NusantaraReinforcementNoticeTest.php                ##
##  Type           : Unit Test for Nusantara Reinforcement & Notice 1 Parsing  ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Technology.php';

global $generator, $technology, $database;
if (!isset($generator)) {
    $generator = new MyGenerator();
}
if (!isset($technology)) {
    $technology = new Technology();
}
$database = new class {
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

echo "=== Running NusantaraReinforcementNoticeTest ===" . PHP_EOL;

// 1. Construct simulated battle report data array with NUSREINF token
$dummyReport = array_fill(0, 290, '0');
$dummyReport[0] = '1'; // Attacker UID
$dummyReport[1] = '1'; // Attacker WREF
$dummyReport[2] = '1'; // Attacker Tribe (Romans)
$dummyReport[28] = '2'; // Defender UID
$dummyReport[29] = '2'; // Defender WREF
$dummyReport[30] = 'Village 2'; // Defender Name
$dummyReport[34] = '1'; // Defender Tribe (Romans)

// Defender own troops: 10 Romans sent, 2 dead
$dummyReport[35] = '10'; // u1
$dummyReport[45] = '2';  // dead u1

// Simulate old phantom Natar bug: $natar flag was 1 ($dummyReport[139]), but 0 troops sent & 0 dead
$dummyReport[139] = '1'; // Natar flag (the bug)
// Units 140..159 are all '0'

// Append Nusantara Reinforcement token
// 50 u91, 20 u95, 10 u96 sent; 15 u91, 5 u95, 2 u96 dead; 1 hero sent, 0 hero dead
$nusSend = '50;0;0;0;20;10;0;0;0;0';
$nusDead = '15;0;0;0;5;2;0;0;0;0';
$nusToken = 'NUSREINF:' . $nusSend . ';' . $nusDead . ';1;0';
$dummyReport[] = $nusToken;

$message = new stdClass();
$message->readingNotice = [
    'id' => 88888,
    'uid' => 2,
    'to' => 2,
    'topic' => 'Attacker attacks Village 2',
    'ntype' => 1,
    'data' => implode(',', $dummyReport),
    'time' => time(),
    'viewed' => 1,
    'archive' => 0
];

$session = new stdClass();
$session->access = 0;
$session->uid = 2;

ob_start();
include dirname(__DIR__) . '/Templates/Notice/1.tpl';
$renderedHtml = ob_get_clean();

// Verification 1: Phantom Natars (units 41..50) must NOT be rendered
assertTrue(strpos($renderedHtml, 'unit u41') === false, "Phantom Natar unit u41 must NOT be rendered when troops are 0");
assertTrue(strpos($renderedHtml, 'unit u50') === false, "Phantom Natar unit u50 must NOT be rendered");

// Verification 2: Nusantara reinforcement units (u91..u100) MUST be rendered
assertTrue(strpos($renderedHtml, 'unit u91') !== false, "Nusantara unit u91 (Prajurit Tombak) must be rendered");
assertTrue(strpos($renderedHtml, 'unit u96') !== false, "Nusantara unit u96 (Gajah Perang) must be rendered");

// Verification 3: Troops counts and casualties must appear in output
assertTrue(strpos($renderedHtml, '<td>50</td>') !== false, "Nusantara sent troop count 50 must be rendered");
assertTrue(strpos($renderedHtml, '<td>15</td>') !== false, "Nusantara casualty count 15 must be rendered");

// Verification 4: Hero in reinforcement must be rendered
assertTrue(strpos($renderedHtml, 'unit uhero') !== false, "Reinforcement hero icon must be rendered");

echo "PASS: Nusantara reinforcement correctly displayed in battle notice and phantom Natars prevented." . PHP_EOL;
echo "=== All Tests Passed Successfully! ===" . PHP_EOL;
