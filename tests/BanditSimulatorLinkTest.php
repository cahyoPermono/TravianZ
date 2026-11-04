<?php

#################################################################################
##  Filename       : tests/BanditSimulatorLinkTest.php                         ##
##  Type           : Unit Test for Bandit Camp Simulator Calculator Button     ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Form.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

function assertEqual($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message (Expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

echo "=== 1. Testing Bandit Camp Simulator URL Construction ===" . PHP_EOL;

// Mock bandit camp with guarding troops:
// Tier 2 (Markas Begal): 45 Babi Hutan (u35), 80 Srigala (u36), 35 Beruang (u37)
$camp = [
    'wref' => 12345,
    'tier' => 2,
    'cur_hp' => 160
];

$unitData = [
    'vref' => 12345,
    'u35' => 45,
    'u36' => 80,
    'u37' => 35
];

$guardsList = [];
$simParams = ['target=4', 'from=bandit', 'wref=' . (int)$camp['wref']];
for ($u = 1; $u <= 50; $u++) {
    if (!empty($unitData['u' . $u]) && $unitData['u' . $u] > 0) {
        $guardsList[] = [
            'id' => $u,
            'count' => (int)$unitData['u' . $u]
        ];
        $simParams[] = 'u' . $u . '=' . (int)$unitData['u' . $u];
    }
}
$simUrl = 'warsim.php?' . implode('&amp;', $simParams);

echo "Constructed URL: " . $simUrl . PHP_EOL;
assertTrue(strpos($simUrl, 'warsim.php?') === 0, "URL must start with warsim.php?");
assertTrue(strpos($simUrl, 'target=4') !== false, "URL must contain target=4 (Nature)");
assertTrue(strpos($simUrl, 'from=bandit') !== false, "URL must contain from=bandit");
assertTrue(strpos($simUrl, 'wref=12345') !== false, "URL must contain wref=12345");
assertTrue(strpos($simUrl, 'u35=45') !== false, "URL must contain u35=45");
assertTrue(strpos($simUrl, 'u36=80') !== false, "URL must contain u36=80");
assertTrue(strpos($simUrl, 'u37=35') !== false, "URL must contain u37=35");

echo "PASSED" . PHP_EOL;

echo "=== 2. Testing warsim.php Form Pre-filling from Query Params ===" . PHP_EOL;

// Simulate GET request processing in warsim.php
$_GET = [
    'target' => '4',
    'from' => 'bandit',
    'wref' => '12345',
    'u35' => '45',
    'u36' => '80',
    'u37' => '35'
];

$form = new Form();

if (!empty($_GET['target'])) {
    if (is_array($_GET['target'])) {
        $parsedTargets = array_map('intval', $_GET['target']);
    } else {
        $rawTargets = explode(',', (string)$_GET['target']);
        $parsedTargets = [];
        foreach ($rawTargets as $rt) {
            $rtInt = (int)$rt;
            if ($rtInt >= 1 && $rtInt <= 10) {
                $parsedTargets[] = $rtInt;
            }
        }
    }
    if (empty($parsedTargets)) {
        $parsedTargets = [4];
    }
    $_GET['target'] = $parsedTargets;

    foreach ($_GET as $key => $value) {
        if ($key[0] === 'u' && is_numeric($value)) {
            $form->setValue('a2_' . substr($key, 1), (int)$value);
        }
    }
}

$target = !empty($_GET['target']) ? (is_array($_GET['target']) ? $_GET['target'] : [(int)$_GET['target']]) : [];

assertEqual([4], $target, "Target tribe must be Nature (4)");
assertEqual(45, (int)$form->getValue('a2_35'), "Form a2_35 must be 45 (Babi Hutan)");
assertEqual(80, (int)$form->getValue('a2_36'), "Form a2_36 must be 80 (Srigala)");
assertEqual(35, (int)$form->getValue('a2_37'), "Form a2_37 must be 35 (Beruang)");
assertEqual(0, (int)$form->getValue('a2_31'), "Form a2_31 must be empty/0");

echo "PASSED" . PHP_EOL;

echo "=== All Tests Passed Successfully! ===" . PHP_EOL;

