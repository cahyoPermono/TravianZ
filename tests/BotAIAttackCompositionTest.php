<?php

#################################################################################
##  Filename       : BotAIAttackCompositionTest.php                            ##
##  Type           : Unit & Integration Test for BotAI Attack Composition      ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BotAI.php';

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

echo "=== 1. Testing BotAI Unit Categorization Helper Methods ===" . PHP_EOL;

// 1.1 Gauls (Tribe 3)
$gaulOffensiveAll = BotAI::getOffensiveUnits(3, true);
$gaulOffensiveNoSiege = BotAI::getOffensiveUnits(3, false);
$gaulDefensive = BotAI::getDefensiveUnits(3);
$gaulCavOffensive = BotAI::getCavalryUnits(3, true);
$gaulCavAll = BotAI::getCavalryUnits(3, false);
$gaulInfOffensive = BotAI::getInfantryUnits(3, true);

assertTrue(in_array(22, $gaulOffensiveAll, true), "Gaul offensive includes Swordsman (u22)");
assertTrue(in_array(24, $gaulOffensiveAll, true), "Gaul offensive includes Theutates Thunder (u24)");
assertTrue(in_array(26, $gaulOffensiveAll, true), "Gaul offensive includes Haeduan (u26)");
assertTrue(!in_array(21, $gaulOffensiveAll, true), "Gaul offensive MUST NEVER include Phalanx (u21)");
assertTrue(!in_array(25, $gaulOffensiveAll, true), "Gaul offensive MUST NEVER include Druidrider (u25)");

assertTrue(!in_array(27, $gaulOffensiveNoSiege, true), "Gaul offensive without siege excludes Ram (u27)");
assertTrue(!in_array(28, $gaulOffensiveNoSiege, true), "Gaul offensive without siege excludes Trebuchet (u28)");

assertTrue(in_array(21, $gaulDefensive, true), "Gaul defensive includes Phalanx (u21)");
assertTrue(in_array(25, $gaulDefensive, true), "Gaul defensive includes Druidrider (u25)");

assertEqual([24, 26], $gaulCavOffensive, "Gaul offensive cavalry is TT (u24) and Haeduan (u26)");
assertTrue(!in_array(25, $gaulCavOffensive, true), "Gaul offensive cavalry MUST NOT include Druidrider (u25)");
assertTrue(in_array(25, $gaulCavAll, true), "Gaul all cavalry includes Druidrider (u25)");

assertEqual([22], $gaulInfOffensive, "Gaul offensive infantry is ONLY Swordsman (u22)");
assertTrue(!in_array(21, $gaulInfOffensive, true), "Gaul offensive infantry MUST NOT include Phalanx (u21)");

// 1.2 Romans (Tribe 1)
$romanOffensive = BotAI::getOffensiveUnits(1, true);
assertTrue(in_array(3, $romanOffensive, true), "Roman offensive includes Imperian (u3)");
assertTrue(!in_array(2, $romanOffensive, true), "Roman offensive MUST NEVER include Praetorian (u2)");
assertEqual([5, 6], BotAI::getCavalryUnits(1, true), "Roman offensive cavalry is EI (u5) and EC (u6)");

// 1.3 Teutons (Tribe 2)
$teutonOffensive = BotAI::getOffensiveUnits(2, true);
assertTrue(in_array(11, $teutonOffensive, true), "Teuton offensive includes Clubswinger (u11)");
assertTrue(in_array(13, $teutonOffensive, true), "Teuton offensive includes Axeman (u13)");
assertTrue(!in_array(12, $teutonOffensive, true), "Teuton offensive MUST NEVER include Spearman (u12)");
assertTrue(!in_array(15, $teutonOffensive, true), "Teuton offensive MUST NEVER include Paladin (u15)");
assertEqual([16], BotAI::getCavalryUnits(2, true), "Teuton offensive cavalry is ONLY Teutonic Knight (u16)");

// 1.4 Nusantara (Tribe 10)
$nusantaraOffensive = BotAI::getOffensiveUnits(10, true);
assertTrue(in_array(91, $nusantaraOffensive, true), "Nusantara offensive includes Pendekar Keris (u91)");
assertTrue(in_array(93, $nusantaraOffensive, true), "Nusantara offensive includes Pemanah Busur Gendewa (u93)");
assertTrue(!in_array(92, $nusantaraOffensive, true), "Nusantara offensive MUST NEVER include Prajurit Tombak (u92)");
assertEqual([95, 96], BotAI::getCavalryUnits(10, true), "Nusantara offensive cavalry is Prajurit Berkuda (u95) and Kavaleri Berat (u96)");

echo "PASS: Unit categorization helper tests successful!" . PHP_EOL;

echo PHP_EOL . "=== 2. Testing BotAI::orderAttack() with Gaul Bot Troops ===" . PHP_EOL;

global $database;
$dblink = $database->dblink;

// Setup test users & villages
$testBotUser = 'BotTestGaul_' . time();
$testVictimUser = 'VictimTest_' . time();

// Create Gaul Bot User (access = 2, is_bot = 1, tribe = 3)
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, regtime) VALUES ('$testBotUser', 'dummy', 'bot@test.com', 3, 2, 1, " . time() . ")");
$botUid = (int)$dblink->insert_id;

// Create Victim User (tribe = 1)
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, is_bot, regtime) VALUES ('$testVictimUser', 'dummy', 'vic@test.com', 1, 2, 0, " . time() . ")");
$vicUid = (int)$dblink->insert_id;

// Find 2 unoccupied field coordinates or use test wrefs
$wrefs = $database->query_return("SELECT id, x, y FROM " . TB_PREFIX . "wdata WHERE occupied = 0 AND fieldtype > 0 LIMIT 2");
if (count($wrefs) < 2) {
    echo "SKIP database tests: Not enough free map tiles." . PHP_EOL;
    exit(0);
}

$botWref = (int)$wrefs[0]['id'];
$vicWref = (int)$wrefs[1]['id'];

// Create village records
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop) VALUES ($botWref, $botUid, 'BotGaulVil', 1, 100)");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop) VALUES ($vicWref, $vicUid, 'VictimVil', 1, 100)");
$database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id IN ($botWref, $vicWref)");

// Create units record for bot village with:
// 100 Phalanxes (u21), 50 Swordsmen (u22), 40 Theutates Thunders (u24), 20 Druidriders (u25)
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u21, u22, u24, u25, hero) VALUES ($botWref, 100, 50, 40, 20, 0)");

try {
    // -------------------------------------------------------------
    // Test Case 2.1: Composition = 'cavalry' (Kuda Full) on Raid (attackType = 4)
    // -------------------------------------------------------------
    echo "Running Test Case 2.1: Kuda Full (cavalry composition)..." . PHP_EOL;
    $resCav = BotAI::orderAttack($botUid, $testVictimUser, 4, 25, 'cavalry');
    assertEqual('success', $resCav['status'], "orderAttack cavalry should succeed");
    assertEqual(1, $resCav['count'], "Should dispatch 1 bot attack");

    // Check dispatched attack
    $atkRow = $database->query_return("SELECT * FROM " . TB_PREFIX . "attacks WHERE vref = $botWref ORDER BY id DESC LIMIT 1");
    assertTrue(!empty($atkRow), "Attack record should be created");
    $atk = $atkRow[0];

    // Slot 1 (u21 Phalanx): MUST BE 0!
    assertEqual('0', (string)$atk['t1'], "Slot 1 (Phalanx) must be 0 in cavalry attack!");
    // Slot 2 (u22 Swordsman): MUST BE 0!
    assertEqual('0', (string)$atk['t2'], "Slot 2 (Swordsman) must be 0 in cavalry attack!");
    // Slot 3 (u23 Pathfinder): 0
    assertEqual('0', (string)$atk['t3'], "Slot 3 (Pathfinder) must be 0");
    // Slot 4 (u24 Theutates Thunder): MUST BE 25!
    assertEqual('25', (string)$atk['t4'], "Slot 4 (Theutates Thunder) must receive full 25 quota!");
    // Slot 5 (u25 Druidrider): MUST BE 0!
    assertEqual('0', (string)$atk['t5'], "Slot 5 (Druidrider) must be 0 in offensive cavalry raid!");

    // Check march speed from movement row
    $movRow = $database->query_return("SELECT * FROM " . TB_PREFIX . "movement WHERE ref = {$atk['id']} AND sort_type = 3 LIMIT 1");
    assertTrue(!empty($movRow), "Movement row must exist");
    $mov = $movRow[0];
    $dist = BotAI::getDistance($wrefs[0]['x'], $wrefs[0]['y'], $wrefs[1]['x'], $wrefs[1]['y']);
    $incSpeed = defined('INCREASE_SPEED') ? max(1, (int)INCREASE_SPEED) : 1;
    // Expected speed is 19 (Theutates Thunder), NOT 6 (Swordsman) or 7 (Phalanx)
    $expectedDuration = max(30, (int)round(($dist / 19) * 3600 / $incSpeed));
    $actualDuration = (int)($mov['endtime'] - $mov['starttime']);
    assertEqual($expectedDuration, $actualDuration, "Movement duration must match pure cavalry speed (19 tiles/hr)!");

    echo "PASS: Test Case 2.1 successful (100% cavalry, ZERO infantry, speed = 19)!" . PHP_EOL;

    // -------------------------------------------------------------
    // Test Case 2.2: Composition = 'balanced' (Seimbang) on Raid (attackType = 4)
    // -------------------------------------------------------------
    echo "Running Test Case 2.2: Balanced attack (offensive only, NO Phalanx)..." . PHP_EOL;
    $resBal = BotAI::orderAttack($botUid, $testVictimUser, 4, 30, 'balanced');
    assertEqual('success', $resBal['status'], "orderAttack balanced should succeed");

    $atkRow2 = $database->query_return("SELECT * FROM " . TB_PREFIX . "attacks WHERE vref = $botWref ORDER BY id DESC LIMIT 1");
    $atk2 = $atkRow2[0];

    // Phalanx (t1) MUST BE 0!
    assertEqual('0', (string)$atk2['t1'], "Slot 1 (Phalanx) must be EXACTLY 0 in balanced offensive attack!");
    // Druidrider (t5) MUST BE 0!
    assertEqual('0', (string)$atk2['t5'], "Slot 5 (Druidrider) must be EXACTLY 0 in balanced offensive attack!");
    // Swordsman (t2) + TT (t4) must sum to 30
    $dispatchedCombat = (int)$atk2['t2'] + (int)$atk2['t4'];
    assertEqual(30, $dispatchedCombat, "Dispatched combat troops must equal targetSend (30)");
    assertTrue((int)$atk2['t2'] > 0, "Swordsman (u22) must be present in balanced offensive attack");
    assertTrue((int)$atk2['t4'] > 0, "Theutates Thunder (u24) must be present in balanced offensive attack");

    echo "PASS: Test Case 2.2 successful (Balanced attack used Swordsmen + TT, ZERO Phalanx)!" . PHP_EOL;

    // -------------------------------------------------------------
    // Test Case 2.3: Composition = 'infantry' on Raid (attackType = 4)
    // -------------------------------------------------------------
    echo "Running Test Case 2.3: Infantry attack (Swordsman ONLY, NO Phalanx, NO Cavalry)..." . PHP_EOL;
    $resInf = BotAI::orderAttack($botUid, $testVictimUser, 4, 15, 'infantry');
    assertEqual('success', $resInf['status'], "orderAttack infantry should succeed");

    $atkRow3 = $database->query_return("SELECT * FROM " . TB_PREFIX . "attacks WHERE vref = $botWref ORDER BY id DESC LIMIT 1");
    $atk3 = $atkRow3[0];

    assertEqual('0', (string)$atk3['t1'], "Slot 1 (Phalanx) must be 0 in offensive infantry attack!");
    assertEqual('15', (string)$atk3['t2'], "Slot 2 (Swordsman) must be 15 in offensive infantry attack!");
    assertEqual('0', (string)$atk3['t4'], "Slot 4 (TT) must be 0 in infantry attack!");

    echo "PASS: Test Case 2.3 successful (100% Swordsman, ZERO Phalanx, ZERO Cavalry)!" . PHP_EOL;

    // -------------------------------------------------------------
    // Test Case 2.4: Bot with ONLY Phalanxes (Zero offensive troops)
    // -------------------------------------------------------------
    echo "Running Test Case 2.4: Gaul bot with ONLY Phalanxes (must NOT attack)..." . PHP_EOL;
    // Wipe swordsmen and TT, leave only Phalanxes
    $database->query("UPDATE " . TB_PREFIX . "units SET u22 = 0, u24 = 0, u25 = 0, u21 = 150 WHERE vref = $botWref");

    // Trying 'cavalry' composition
    $resNoCav = BotAI::orderAttack($botUid, $testVictimUser, 4, 20, 'cavalry');
    assertEqual('error', $resNoCav['status'], "orderAttack with cavalry when no cavalry available must fail cleanly");
    assertTrue(strpos($resNoCav['message'], 'tidak memiliki') !== false || !empty($resNoCav['reports']), "Must explain why bot was skipped");

    // Trying 'balanced' composition
    $resNoOff = BotAI::orderAttack($botUid, $testVictimUser, 4, 20, 'balanced');
    assertEqual('error', $resNoOff['status'], "orderAttack on raid when only Phalanxes exist must fail cleanly (no offensive troops)");

    // Verify ZERO attacks were sent from this bot while only Phalanxes existed
    $recentAtks = $database->query_return("SELECT COUNT(*) as cnt FROM " . TB_PREFIX . "attacks WHERE vref = $botWref AND id > {$atk3['id']}");
    assertEqual('0', (string)$recentAtks[0]['cnt'], "Zero attacks must be dispatched when bot has only defensive Phalanxes!");

    echo "PASS: Test Case 2.4 successful (Phalanxes are strictly preserved at home, never dispatched on attack)!" . PHP_EOL;

    // -------------------------------------------------------------
    // Test Case 2.5: Reinforcement (attackType = 2) CAN send Phalanxes
    // -------------------------------------------------------------
    echo "Running Test Case 2.5: Reinforcement to ally CAN dispatch Phalanxes..." . PHP_EOL;
    $resReinf = BotAI::orderAttack($botUid, $testVictimUser, 2, 25, 'defense');
    assertEqual('success', $resReinf['status'], "orderAttack reinforcement should succeed with defensive troops");

    $atkRowReinf = $database->query_return("SELECT * FROM " . TB_PREFIX . "attacks WHERE vref = $botWref ORDER BY id DESC LIMIT 1");
    $atkReinf = $atkRowReinf[0];
    assertEqual('25', (string)$atkReinf['t1'], "Phalanxes (t1) are allowed and encouraged for defensive reinforcement!");

    echo "PASS: Test Case 2.5 successful (Reinforcement properly dispatches defensive Phalanxes)!" . PHP_EOL;

} finally {
    // Cleanup test data
    $database->query("DELETE FROM " . TB_PREFIX . "attacks WHERE vref = $botWref");
    $database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $botWref OR `to` = $botWref");
    $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $botWref");
    $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($botWref, $vicWref)");
    $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id IN ($botWref, $vicWref)");
    $database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($botUid, $vicUid)");
}

echo PHP_EOL . "=== ALL TESTS PASSED SUCCESSFULLY! ===" . PHP_EOL;
