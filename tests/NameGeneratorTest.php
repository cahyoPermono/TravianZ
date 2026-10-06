<?php

#################################################################################
##  Filename       : NameGeneratorTest.php                                     ##
##  Type           : Unit Test for NameGenerator module                        ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/NameGenerator.php';

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message" . PHP_EOL);
        exit(1);
    }
}

// 1. Test offline username generation across different tribes
$tribes = [1, 2, 3, 6, 7, 8, 9];
foreach ($tribes as $t) {
    $name = NameGenerator::generateUsername($t, false);
    assertTrue(!empty($name), "Username for tribe $t should not be empty");
    assertTrue(strlen($name) >= 3, "Username '$name' should be at least 3 characters");
    assertTrue(strpos($name, 'Bot_') === false, "Username '$name' should not have 'Bot_' prefix");
}

// 2. Test village name generation
foreach ($tribes as $t) {
    $vName = NameGenerator::generateVillageName('PlayerX', $t);
    assertTrue(!empty($vName), "Village name for tribe $t should not be empty");
    assertTrue(strpos($vName, 'Bot_') === false, "Village name should not contain 'Bot_'");
}

// 3. Test bio generation
$bio = NameGenerator::generateBio();
assertTrue(is_string($bio), "Bio should be a string");
assertTrue(strpos($bio, '[#BOT]') === false, "Bio should not contain '[#BOT]' tag");

echo "PASS NameGenerator unit tests passed." . PHP_EOL;
