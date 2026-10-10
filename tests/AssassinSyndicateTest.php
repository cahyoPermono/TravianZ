<?php

#################################################################################
##  Filename       : AssassinSyndicateTest.php                                 ##
##  Type           : Comprehensive Unit Test for Assassin Syndicate Engine     ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/Generator.php';
require_once dirname(__DIR__) . '/GameEngine/Units.php';
require_once dirname(__DIR__) . '/GameEngine/BlackMarket.php';
require_once dirname(__DIR__) . '/GameEngine/Lang/en.php';
require_once dirname(__DIR__) . '/GameEngine/Assassin.php';

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

echo "=== Running Assassin Syndicate Unit Tests ===\n";

// -------------------------------------------------------------
// Test 1: Tables and Sanctuary Setup
// -------------------------------------------------------------
echo "1. Testing table initialization and sanctuary creation...\n";
Assassin::ensureTables();

$sanctuary = Assassin::getSanctuary();
assertTrue(!empty($sanctuary), "Sanctuary must exist");
assertTrue((int)$sanctuary['wref'] > 0, "Sanctuary wref must be valid");
assertTrue(isset($sanctuary['x']) && isset($sanctuary['y']), "Sanctuary coordinates must exist");

$isSanct = Assassin::isSanctuary((int)$sanctuary['wref']);
assertTrue($isSanct === true, "isSanctuary must return true for sanctuary wref");

$isNotSanct = Assassin::isSanctuary(999999);
assertTrue($isNotSanct === false, "isSanctuary must return false for bogus wref");

echo "   [OK] Sanctuary verified at ({$sanctuary['x']}|{$sanctuary['y']}) wref={$sanctuary['wref']}\n";

// -------------------------------------------------------------
// Test 2: Distance and Duration Calculations
// -------------------------------------------------------------
echo "2. Testing distance and duration logic...\n";
$dist = Assassin::getDistance(0, 0, 3, 4);
assertEqual(5.0, round($dist, 1), "Distance (0,0) to (3,4) must be 5.0");

$distWrap = Assassin::getDistance(200, 0, -200, 0);
assertTrue($distWrap > 0, "Toroidal distance should calculate correctly");

$duration = Assassin::calculateDuration((int)$sanctuary['wref'], (int)$sanctuary['wref']);
assertTrue($duration >= 180, "Duration must respect minimum duration threshold");
echo "   [OK] Distance calculations verified.\n";

// -------------------------------------------------------------
// Setup Dummy Client and Target Players
// -------------------------------------------------------------
echo "3. Provisioning test accounts & villages...\n";
$clientUid = 88101;
$clientWid = 77101;
$targetUid = 88102;
$targetWid = 77102;

// Clean up any previous test data
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "mdata WHERE target IN ($clientUid, $targetUid) OR owner IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "assassin_contracts WHERE client_uid = $clientUid");

$timeNow = time();

// Create Client User (Romans, tribe 1)
$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, password, email, tribe, access, gold, protect) VALUES ($clientUid, 'Client_Tester', 'pass', 'c@test.com', 1, 2, 100, 0)");
$database->query("INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($clientWid, 3, 0, 10, 10, 1, 'b10')");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate) VALUES ($clientWid, $clientUid, 'Client City', 1, 100, 2000, 2000, 2000, 2000, 10000, 10000, $timeNow)");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u1, u2) VALUES ($clientWid, 10, 0)");
Units::createStarterHero($clientUid, $clientWid, 1, 'Client_Tester');

// Create Target User (Teutons, tribe 2)
$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, password, email, tribe, access, gold, protect) VALUES ($targetUid, 'Target_Victim', 'pass', 't@test.com', 2, 2, 0, 0)");
$database->query("INSERT INTO " . TB_PREFIX . "wdata (id, fieldtype, oasistype, x, y, occupied, image) VALUES ($targetWid, 3, 0, 15, 15, 1, 'b10')");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate) VALUES ($targetWid, $targetUid, 'Victim City', 1, 150, 4000, 4000, 4000, 4000, 10000, 10000, $timeNow)");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u11, u12) VALUES ($targetWid, 50, 25)");
$database->query("INSERT INTO " . TB_PREFIX . "fdata (vref, f1, f1t, f20, f20t) VALUES ($targetWid, 5, 1, 5, 19)"); // Barracks at slot 20, lvl 5
Units::createStarterHero($targetUid, $targetWid, 2, 'Target_Victim');

// Give client 1500 Silver
BlackMarket::addSilver($clientUid, 1500);
assertEqual(1600, BlackMarket::getSilver($clientUid), "Client starting silver must be 1600 (100 starter + 1500)");

echo "   [OK] Test accounts created with initial assets.\n";

// -------------------------------------------------------------
// Test 3: Contract Validation Rules
// -------------------------------------------------------------
echo "4. Testing hiring validation rules...\n";
// Self-target error
$resSelf = Assassin::hireContract($clientUid, $clientWid, $clientWid, Assassin::CONTRACT_SHADOW_RAID, 'silver');
assertEqual('error', $resSelf['status'], "Hiring on self must fail");

// Sanctuary target error
$resSanct = Assassin::hireContract($clientUid, $clientWid, (int)$sanctuary['wref'], Assassin::CONTRACT_SHADOW_RAID, 'silver');
assertEqual('error', $resSanct['status'], "Hiring on sanctuary must fail");

// Invalid currency
$resCur = Assassin::hireContract($clientUid, $clientWid, $targetWid, Assassin::CONTRACT_SHADOW_RAID, 'crypto');
assertEqual('error', $resCur['status'], "Invalid currency must fail");

echo "   [OK] All validation constraints properly enforced.\n";

// -------------------------------------------------------------
// Test 4: Shadow Raid Execution & Anonymous Plunder
// -------------------------------------------------------------
echo "5. Testing Shadow Raid contract lifecycle...\n";
$silverBefore = BlackMarket::getSilver($clientUid);
$raidHire = Assassin::hireContract($clientUid, $clientWid, $targetWid, Assassin::CONTRACT_SHADOW_RAID, 'silver');
assertTrue($raidHire['status'] === 'success', "Shadow raid hiring must succeed");

$silverAfter = BlackMarket::getSilver($clientUid);
assertEqual(300, $silverBefore - $silverAfter, "300 Silver deducted for shadow raid");

$cid = (int)$raidHire['contract_id'];
assertTrue($cid > 0, "Contract ID must be generated");

// Force end_time to past so contract is immediately processable
$database->query("UPDATE " . TB_PREFIX . "assassin_contracts SET end_time = " . (time() - 10) . " WHERE id = $cid");

// Process contract
$procRes = Assassin::processContracts();
assertTrue($procRes['processed'] >= 1, "At least 1 contract must be processed");

// Verify contract completed in DB
$cRow = $database->query_return("SELECT * FROM " . TB_PREFIX . "assassin_contracts WHERE id = $cid");
assertEqual(Assassin::STATUS_COMPLETED, (int)$cRow[0]['status'], "Contract status must be completed (1)");

// Verify target defenders suffered casualties
$tUnits = $database->getUnit($targetWid, false);
assertTrue((int)$tUnits['u11'] < 50, "Target defenders (u11) must have been eliminated");

// Verify target resources were plundered
$tVdataAfter = $database->getVillage($targetWid, 0, false);
assertTrue((int)$tVdataAfter['wood'] < 4000, "Target wood must have been plundered");

// Verify client received stolen resources
$cVdataAfter = $database->getVillage($clientWid, 0, false);
assertTrue((int)$cVdataAfter['wood'] > 2000, "Client must have received plundered tribute");

// Verify victim received anonymous message from 'Klan Assassin'
$victimMsgs = $database->query_return("SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $targetUid ORDER BY id DESC LIMIT 1");
assertTrue(!empty($victimMsgs), "Victim must receive notification message");
assertTrue(strpos($victimMsgs[0]['topic'], 'SERANGAN MALAM') !== false, "Victim topic must be night raid");
assertTrue(strpos($victimMsgs[0]['message'], 'Klan Assassin') !== false, "Message must mention masked Klan Assassin");
assertTrue(strpos($victimMsgs[0]['message'], 'Client_Tester') === false, "Client name MUST NOT appear in victim message");

echo "   [OK] Shadow Raid resolved with 100% anonymity and resource transfer.\n";

// -------------------------------------------------------------
// Test 5: Hero Assassination Execution
// -------------------------------------------------------------
echo "6. Testing Hero Assassination contract...\n";
// Ensure target hero is alive
$targetHero = $database->query_return("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $targetUid LIMIT 1");
assertTrue(!empty($targetHero) && (int)$targetHero[0]['dead'] === 0, "Target hero must initially be alive");

$goldBefore = (int)$database->getUserField($clientUid, 'gold', 0, false);
$heroHire = Assassin::hireContract($clientUid, $clientWid, $targetWid, Assassin::CONTRACT_HERO_ASSASSINATE, 'gold');
assertTrue($heroHire['status'] === 'success', "Hero assassination hiring must succeed");

$goldAfter = (int)$database->getUserField($clientUid, 'gold', 0, false);
assertEqual(30, $goldBefore - $goldAfter, "30 Gold deducted for hero assassination");

$hid = (int)$heroHire['contract_id'];
$database->query("UPDATE " . TB_PREFIX . "assassin_contracts SET end_time = " . (time() - 10) . " WHERE id = $hid");

Assassin::processContracts();

// Verify target hero is now dead!
$targetHeroAfter = $database->query_return("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $targetUid LIMIT 1");
assertEqual(1, (int)$targetHeroAfter[0]['dead'], "Target hero must be dead (dead = 1)");
assertEqual(0, (int)$targetHeroAfter[0]['health'], "Target hero health must be 0");

// Check victim message
$victimHeroMsg = $database->query_return("SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $targetUid ORDER BY id DESC LIMIT 1");
assertTrue(strpos($victimHeroMsg[0]['topic'], 'KABAR DUKA') !== false, "Victim topic must be grief alert");
assertTrue(strpos($victimHeroMsg[0]['message'], 'Client_Tester') === false, "Client name MUST NOT leak to victim");

echo "   [OK] Hero Assassination successfully eliminated target hero with zero trace.\n";

// -------------------------------------------------------------
// Test 6: Night Sabotage Execution
// -------------------------------------------------------------
echo "7. Testing Night Sabotage contract...\n";
$fdataBefore = $database->getResourceLevel($targetWid, false);
$barracksLvlBefore = (int)$fdataBefore['f20']; // Barracks level 5
assertEqual(5, $barracksLvlBefore, "Target barracks must be level 5 before sabotage");

$sabotageHire = Assassin::hireContract($clientUid, $clientWid, $targetWid, Assassin::CONTRACT_NIGHT_SABOTAGE, 'silver');
assertTrue($sabotageHire['status'] === 'success', "Night sabotage hiring must succeed");

$sid = (int)$sabotageHire['contract_id'];
$database->query("UPDATE " . TB_PREFIX . "assassin_contracts SET end_time = " . (time() - 10) . " WHERE id = $sid");

Assassin::processContracts();

$fdataAfter = $database->getResourceLevel($targetWid, false);
// Either barracks or a resource field was demoted by 1 level
$demoted = false;
if ((int)$fdataAfter['f20'] === 4 || (int)$fdataAfter['f1'] === 4) {
    $demoted = true;
}
assertTrue($demoted, "At least one building level must have been downgraded");

// Check victim message
$victimSabotageMsg = $database->query_return("SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $targetUid ORDER BY id DESC LIMIT 1");
assertTrue(strpos($victimSabotageMsg[0]['topic'], 'SABOTASE MALAM') !== false, "Victim topic must be sabotage");
assertTrue(strpos($victimSabotageMsg[0]['message'], 'Client_Tester') === false, "Client identity must remain confidential");

echo "   [OK] Night Sabotage successfully demoted target building covertly.\n";

// -------------------------------------------------------------
// Clean up test data
// -------------------------------------------------------------
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "wdata WHERE id IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref IN ($clientWid, $targetWid)");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "mdata WHERE target IN ($clientUid, $targetUid) OR owner IN ($clientUid, $targetUid)");
$database->query("DELETE FROM " . TB_PREFIX . "assassin_contracts WHERE client_uid = $clientUid");

echo "\n>>> ALL ASSASSIN SYNDICATE UNIT TESTS PASSED SUCCESSFULLY! <<<\n";
