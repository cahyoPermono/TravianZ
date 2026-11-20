<?php

#################################################################################
##  Filename       : ExpeditionEngineTest.php                                  ##
##  Type           : Unit Test for Alliance Expedition Engine (Co-op PvE)      ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BanditCamp.php';
require_once dirname(__DIR__) . '/GameEngine/Expedition/ExpeditionEngine.php';

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

echo "=== Running Alliance Expedition (Co-op PvE) Unit Tests ===\n";

// 1. Ensure tables
echo "Test 1: Ensure database tables exist...\n";
ExpeditionEngine::ensureTables();
global $database;
$tables = $database->query_return("SHOW TABLES LIKE '" . TB_PREFIX . "expeditions'");
assertTrue(!empty($tables), "s1_expeditions table must exist");
$tablesMem = $database->query_return("SHOW TABLES LIKE '" . TB_PREFIX . "expedition_members'");
assertTrue(!empty($tablesMem), "s1_expedition_members table must exist");
$tablesDaily = $database->query_return("SHOW TABLES LIKE '" . TB_PREFIX . "expedition_daily_usage'");
assertTrue(!empty($tablesDaily), "s1_expedition_daily_usage table must exist");

// 2. Test Daily Limit & Tracking
echo "Test 2: Daily limit tracking...\n";
$testUid = 999901;
// Reset today's usage for test
$today = date('Y-m-d');
$database->query("DELETE FROM " . TB_PREFIX . "expedition_daily_usage WHERE uid = $testUid AND entry_date = '$today'");

assertEqual(0, ExpeditionEngine::getDailyUsage($testUid), "Initial daily usage must be 0");
assertTrue(ExpeditionEngine::canJoinExpedition($testUid), "User with 0 runs should be able to join");

ExpeditionEngine::recordDailyUsage($testUid);
assertEqual(1, ExpeditionEngine::getDailyUsage($testUid), "Daily usage should be 1 after recording 1 run");
ExpeditionEngine::recordDailyUsage($testUid);
ExpeditionEngine::recordDailyUsage($testUid);
assertEqual(3, ExpeditionEngine::getDailyUsage($testUid), "Daily usage should be 3");
assertTrue(!ExpeditionEngine::canJoinExpedition($testUid), "User with 3 runs should NOT be able to join (daily limit reached)");

// Revert usage
ExpeditionEngine::refundDailyUsage($testUid);
assertEqual(2, ExpeditionEngine::getDailyUsage($testUid), "Daily usage should be 2 after refund");
assertTrue(ExpeditionEngine::canJoinExpedition($testUid), "User with 2 runs should be able to join again");

// 3. Test Minimum Power Threshold Check (Anti-Tuyul)
echo "Test 3: Minimum Power Threshold validation...\n";
$mockCampHp = 50000;
// 1 Clubswinger (u11 has 40 atk)
$tinyTroops = ['u11' => 1];
$tinyPower = ExpeditionEngine::calculateTroopsAttackPower(2, $tinyTroops, false, 0);
assertEqual(40, $tinyPower, "1 Clubswinger should have 40 attack power");
$checkTiny = ExpeditionEngine::validateMinimumPower($tinyPower, $mockCampHp);
assertTrue(!$checkTiny['valid'], "1 Clubswinger must fail the minimum power check for 50,000 HP boss");

// Strong army: 500 Clubswingers (500 * 40 = 20,000 atk)
$strongTroops = ['u11' => 500];
$strongPower = ExpeditionEngine::calculateTroopsAttackPower(2, $strongTroops, false, 0);
assertEqual(20000, $strongPower, "500 Clubswingers should have 20,000 attack power");
$checkStrong = ExpeditionEngine::validateMinimumPower($strongPower, $mockCampHp);
assertTrue($checkStrong['valid'], "500 Clubswingers must pass the minimum power check");

// 4. Test Tribe Synergy Evaluation (All 8 Tribes + Coalition)
echo "Test 4: Verify Tribe Synergy calculations for all 8 tribes...\n";
// Case A: Romans only (Tribe 1)
$synergiesRomans = ExpeditionEngine::computeSynergiesFromTribes([1]);
assertTrue(isset($synergiesRomans['synergies'][1]), "Romans synergy must be active");
assertEqual('Testudo Shield Wall', $synergiesRomans['synergies'][1]['name'], "Romans synergy name matches");
assertEqual(0.08, $synergiesRomans['casualty_reduction'], "Romans casualty reduction must be 8%");

// Case B: Teutons only (Tribe 2)
$synergiesTeutons = ExpeditionEngine::computeSynergiesFromTribes([2]);
assertEqual('Berserker Rage & Sunder', $synergiesTeutons['synergies'][2]['name'], "Teutons synergy name matches");
assertEqual(0.10, $synergiesTeutons['offense_buff'], "Teutons offense buff must be 10%");
assertEqual(0.15, $synergiesTeutons['enemy_def_reduction'], "Teutons enemy defense reduction must be 15%");

// Case C: Gauls only (Tribe 3)
$synergiesGauls = ExpeditionEngine::computeSynergiesFromTribes([3]);
assertEqual('Field Medicine & Rapid Egress', $synergiesGauls['synergies'][3]['name'], "Gauls synergy name matches");
assertEqual(0.10, $synergiesGauls['casualty_recovery'], "Gauls casualty recovery must be 10%");
assertEqual(0.20, $synergiesGauls['speed_buff'], "Gauls return speed buff must be 20%");

// Case D: Huns only (Tribe 6)
$synergiesHuns = ExpeditionEngine::computeSynergiesFromTribes([6]);
assertEqual('Steppe Flanking & Blitz', $synergiesHuns['synergies'][6]['name'], "Huns synergy name matches");
assertEqual(0.15, $synergiesHuns['crit_strike'], "Huns critical strike must be 15%");

// Case E: Egyptians only (Tribe 7)
$synergiesEgypt = ExpeditionEngine::computeSynergiesFromTribes([7]);
assertEqual('Nile Logistics & Provisioning', $synergiesEgypt['synergies'][7]['name'], "Egyptians synergy name matches");
assertEqual(0.25, $synergiesEgypt['carry_buff'], "Egyptians carry buff must be 25%");
assertEqual(0.10, $synergiesEgypt['loot_multiplier'], "Egyptians loot multiplier must be 10%");

// Case F: Spartans only (Tribe 8)
$synergiesSpartans = ExpeditionEngine::computeSynergiesFromTribes([8]);
assertEqual('Phalanx Iron Will', $synergiesSpartans['synergies'][8]['name'], "Spartans synergy name matches");
assertEqual(0.45, $synergiesSpartans['max_loss_cap'], "Spartans max loss cap must be 45%");
assertEqual(0.15, $synergiesSpartans['hero_exp_buff'], "Spartans hero exp buff must be 15%");

// Case G: Vikings only (Tribe 9)
$synergiesVikings = ExpeditionEngine::computeSynergiesFromTribes([9]);
assertEqual('War Cry & Silver Hoard', $synergiesVikings['synergies'][9]['name'], "Vikings synergy name matches");
assertEqual(0.08, $synergiesVikings['enemy_atk_reduction'], "Vikings enemy attack reduction must be 8%");
assertEqual(0.20, $synergiesVikings['silver_multiplier'], "Vikings silver multiplier must be 20%");

// Case H: Nusantara only (Tribe 10)
$synergiesNusantara = ExpeditionEngine::computeSynergiesFromTribes([10]);
assertEqual('Bhayangkara War Vanguard', $synergiesNusantara['synergies'][10]['name'], "Nusantara synergy name matches");
assertEqual(0.12, $synergiesNusantara['offense_buff'], "Nusantara offense buff must be 12%");
assertEqual(0.20, $synergiesNusantara['rare_drop_buff'], "Nusantara rare drop buff must be 20%");

// Case I: Tricolor Alliance (3 different tribes, e.g. Romans, Teutons, Gauls)
$synergiesTri = ExpeditionEngine::computeSynergiesFromTribes([1, 2, 3]);
assertTrue(!empty($synergiesTri['coalition_bonus']), "Coalition bonus must be present for 3 tribes");
assertEqual('Tricolor Alliance', $synergiesTri['coalition_bonus']['name'], "3 tribes triggers Tricolor Alliance");
assertEqual(0.05, $synergiesTri['coalition_bonus']['offense'], "Tricolor Alliance gives +5% offense");

// Case J: Grand Coalition (5 different tribes, e.g. Romans, Teutons, Gauls, Vikings, Nusantara)
$synergiesGrand = ExpeditionEngine::computeSynergiesFromTribes([1, 2, 3, 9, 10]);
assertEqual('Grand Coalition', $synergiesGrand['coalition_bonus']['name'], "5 tribes triggers Grand Coalition");
assertEqual(0.10, $synergiesGrand['coalition_bonus']['offense'], "Grand Coalition gives +10% offense");
assertEqual(0.10, $synergiesGrand['coalition_bonus']['defense'], "Grand Coalition gives +10% defense");
assertEqual(0.15, $synergiesGrand['coalition_bonus']['hero_exp'], "Grand Coalition gives +15% hero exp");

// 5. Test Shared Casualty Proportion
echo "Test 5: Shared Casualty Proportion & Gaul recovery...\n";
$members = [
    [
        'uid' => 101,
        'tribe' => 1, // Romans
        'troops' => ['u1' => 1000], // 1,000 Legionaries
    ],
    [
        'uid' => 102,
        'tribe' => 3, // Gauls
        'troops' => ['u21' => 500], // 500 Phalanx
    ]
];

$rawLossRatio = 0.20; // 20% team loss
$appliedLoss = ExpeditionEngine::calculateSharedCasualties($members, $rawLossRatio, [1, 3]);

// Because Romans are present: casualty reduction = -8% -> net loss = 20% * (1 - 0.08) = 18.4%
assertEqual(round(0.20 * (1 - 0.08), 4), round($appliedLoss['effective_loss_ratio'], 4), "Romans should reduce loss ratio by 8%");

// Member 1 (1000 troops) should lose ~184 troops, and Gaul field medicine recovers 10% (+18 troops)
assertEqual(184, $appliedLoss['members'][101]['troops_lost']['u1'], "Member 1 should lose 184 troops");
assertEqual(18, $appliedLoss['members'][101]['troops_recovered']['u1'], "Member 1 should recover 18 troops from Gaul medic");
assertEqual(834, $appliedLoss['members'][101]['troops_survived']['u1'], "Member 1 should have 834 surviving troops (1000 - 184 + 18)");

// Member 2 (500 troops) should lose ~92 troops, and Gauls recover 10% (+9 troops)
// 92 lost, recovered 10% = 9 troops recovered -> surviving = 500 - 92 + 9 = 417
assertEqual(92, $appliedLoss['members'][102]['troops_lost']['u21'], "Member 2 should lose 92 troops");
assertEqual(9, $appliedLoss['members'][102]['troops_recovered']['u21'], "Member 2 Gauls should recover 9 troops");
assertEqual(417, $appliedLoss['members'][102]['troops_survived']['u21'], "Member 2 should have 417 total troops returning");

// 6. Test Spartan Iron Will Wipeout Cap
echo "Test 6: Spartan Iron Will wipeout protection...\n";
$spartanMembers = [
    [
        'uid' => 103,
        'tribe' => 8, // Spartans
        'troops' => ['u71' => 1000],
    ]
];
$catastrophicLoss = 0.95; // 95% wipeout
$spartanApplied = ExpeditionEngine::calculateSharedCasualties($spartanMembers, $catastrophicLoss, [8]);
assertEqual(0.45, $spartanApplied['effective_loss_ratio'], "Spartan Iron Will must cap catastrophic loss ratio at 45%");
assertEqual(450, $spartanApplied['members'][103]['troops_lost']['u71'], "Spartan loss capped at 450 (45%)");
assertEqual(550, $spartanApplied['members'][103]['troops_survived']['u71'], "Spartan survived 550 (55%)");

// 7. Test Reward Distribution (50% Flat + 50% Damage Proportion)
echo "Test 7: Reward Distribution (50% Flat + 50% Damage Proportional)...\n";
$membersDamage = [
    101 => 6000, // 60% of damage
    102 => 4000  // 40% of damage
];
$poolTotal = [
    'wood' => 10000,
    'clay' => 10000,
    'iron' => 10000,
    'crop' => 10000,
    'silver' => 1000,
    'cp' => 400,
    'exp' => 2000,
    'essence' => 4
];
// Test with Egyptians (tribe 7) present (+10% resource bounty) and Vikings (tribe 9) (+20% silver)
$distributed = ExpeditionEngine::calculateRewardDistribution($membersDamage, $poolTotal, [7, 9]);

// Total wood with Egyptian +10% bonus = 11,000
// Flat pool (50%) = 5,500 -> 2,750 per person
// Damage pool (50%) = 5,500:
// Member 101 (60%): 2,750 + (0.60 * 5,500 = 3,300) = 6,050
// Member 102 (40%): 2,750 + (0.40 * 5,500 = 2,200) = 4,950
assertEqual(6050, $distributed[101]['wood'], "Member 101 wood matches 50-50 formula + Egyptian buff");
assertEqual(4950, $distributed[102]['wood'], "Member 102 wood matches 50-50 formula + Egyptian buff");

// Total silver with Viking +20% bonus = 1,200
// Flat pool = 600 -> 300 per person
// Member 101 (60%): 300 + (0.60 * 600 = 360) = 660
// Member 102 (40%): 300 + (0.40 * 600 = 240) = 540
assertEqual(660, $distributed[101]['silver'], "Member 101 silver matches 50-50 formula + Viking buff");
assertEqual(540, $distributed[102]['silver'], "Member 102 silver matches 50-50 formula + Viking buff");

// 8. Test Database Expedition Lifecycle (Create, Join, Cancel/Refund)
echo "Test 8: Full Expedition Database Lifecycle...\n";
// Setup mock alliance & members
$mockAllyId = 999;
$leaderUid = 9991;
$memberUid = 9992;
$leaderVillage = 88801;
$memberVillage = 88802;

// Clean up any test records
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($leaderUid, $memberUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "expedition_daily_usage WHERE uid IN ($leaderUid, $memberUid)");

// Insert test users (Romans = 1, Gauls = 3)
$time = time();
$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, alliance, tribe, access, regtime, timestamp) VALUES ($leaderUid, 'ExpLeader', $mockAllyId, 1, 2, $time, $time)");
$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, alliance, tribe, access, regtime, timestamp) VALUES ($memberUid, 'ExpMember', $mockAllyId, 3, 2, $time, $time)");

// Insert villages
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital) VALUES ($leaderVillage, $leaderUid, 'Leader Vil', 1)");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital) VALUES ($memberVillage, $memberUid, 'Member Vil', 1)");

// Insert troops (Leader: 1000 Legionaries u1, Member: 500 Phalanx u21)
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u1, u21) VALUES ($leaderVillage, 1000, 0)");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u1, u21) VALUES ($memberVillage, 0, 500)");

// Use an active camp
$activeCamps = BanditCamp::getActiveCamps();
$targetCamp = null;
foreach ($activeCamps as $ac) {
    if ((int)$ac['tier'] >= 3) {
        $targetCamp = $ac;
        break;
    }
}
if (!$targetCamp) {
    $targetCamp = BanditCamp::spawnCamp(BanditCamp::TIER_FORTRESS);
}
$targetCampId = (int)$targetCamp['id'];

// Test Create Expedition
$createRes = ExpeditionEngine::createExpedition($leaderUid, $targetCampId, 15, $leaderVillage, ['u1' => 600]);
assertTrue($createRes['status'] === 'success', "Leader should successfully create expedition: " . ($createRes['message'] ?? ''));
$testExpId = (int)$createRes['expedition_id'];
assertTrue($testExpId > 0, "Expedition ID must be greater than 0");

// Verify troops deducted from leader village (1000 - 600 = 400 remaining)
$vUnitsLeader = $database->getUnit($leaderVillage, false);
assertEqual(400, (int)$vUnitsLeader['u1'], "Leader village should have 400 u1 remaining");

// Test Member Join
$joinRes = ExpeditionEngine::joinExpedition($testExpId, $memberUid, $memberVillage, ['u1' => 300]); // 300 u21 for Gauls
assertTrue($joinRes['status'] === 'success', "Member should successfully join expedition: " . ($joinRes['message'] ?? ''));

// Verify troops deducted from member village (500 - 300 = 200 remaining)
$vUnitsMember = $database->getUnit($memberVillage, false);
assertEqual(200, (int)$vUnitsMember['u21'], "Member village should have 200 u21 remaining");

// Verify Expedition Details
$details = ExpeditionEngine::getExpeditionDetails($testExpId);
assertTrue(!empty($details), "Expedition details should be retrievable");
assertEqual(2, $details['member_count'], "Expedition should have 2 members");
assertTrue(isset($details['synergies']['synergies'][1]), "Romans synergy should be active");
assertTrue(isset($details['synergies']['synergies'][3]), "Gauls synergy should be active");

// Test Cancel & Refund
echo "Test 9: Voluntary Cancel & Troop Refund Verification...\n";
$cancelRes = ExpeditionEngine::cancelExpedition($testExpId, $leaderUid, "Latihan selesai");
assertTrue($cancelRes['status'] === 'success', "Cancel expedition should succeed");

// Verify troops restored to leader (400 + 600 = 1000)
$vUnitsLeaderAfter = $database->getUnit($leaderVillage, false);
assertEqual(1000, (int)$vUnitsLeaderAfter['u1'], "Leader village should have full 1000 u1 restored");

// Verify troops restored to member (200 + 300 = 500)
$vUnitsMemberAfter = $database->getUnit($memberVillage, false);
assertEqual(500, (int)$vUnitsMemberAfter['u21'], "Member village should have full 500 u21 restored");

// Verify daily limits refunded
assertEqual(0, ExpeditionEngine::getDailyUsage($leaderUid), "Leader daily usage should be restored to 0");
assertEqual(0, ExpeditionEngine::getDailyUsage($memberUid), "Member daily usage should be restored to 0");

// 10. Test Combat Assault Resolution & Victory Lifecycle
echo "Test 10: Combat Assault Resolution (Unified Battlegroup Victory)...\n";
// Spawn a temporary Fortress for combat test
$testFortress = BanditCamp::spawnCamp(BanditCamp::TIER_FORTRESS);
$fortressCampId = (int)$testFortress['id'];
$fortressWref = (int)$testFortress['wref'];

// Clean up and re-insert test users & villages
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($leaderUid, $memberUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "expedition_daily_usage WHERE uid IN ($leaderUid, $memberUid)");

$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, alliance, tribe, access, regtime, timestamp) VALUES ($leaderUid, 'ExpLeader', $mockAllyId, 1, 2, $time, $time)");
$database->query("INSERT INTO " . TB_PREFIX . "users (id, username, alliance, tribe, access, regtime, timestamp) VALUES ($memberUid, 'ExpMember', $mockAllyId, 3, 2, $time, $time)");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, wood, clay, iron, crop) VALUES ($leaderVillage, $leaderUid, 'Leader Vil', 1, 1000, 1000, 1000, 1000)");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, wood, clay, iron, crop) VALUES ($memberVillage, $memberUid, 'Member Vil', 1, 1000, 1000, 1000, 1000)");

// Give overwhelming force to ensure victory (Leader: 5,000 Imperians u3; Member: 3,000 Swordsmen u22)
// u3 has 70 atk each -> 350,000 atk; u22 has 65 atk each -> 195,000 atk
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u3, u22) VALUES ($leaderVillage, 5000, 0)");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, u3, u22) VALUES ($memberVillage, 0, 3000)");

// Create & Join
$createCombat = ExpeditionEngine::createExpedition($leaderUid, $fortressCampId, 15, $leaderVillage, ['u3' => 5000]);
assertTrue($createCombat['status'] === 'success', "Expedition should be created for combat: " . ($createCombat['message'] ?? ''));
$combatExpId = (int)$createCombat['expedition_id'];

$joinCombat = ExpeditionEngine::joinExpedition($combatExpId, $memberUid, $memberVillage, ['u2' => 3000]); // u2 of Gauls = u22 Swordsmen
assertTrue($joinCombat['status'] === 'success', "Member should join combat expedition");

// Resolve assault
$assaultRes = ExpeditionEngine::resolveAssault($combatExpId);
assertEqual('victory', $assaultRes['status'], "Assault against fortress should end in victory");

// Check camp is cleared
$clearedCamp = $database->query_return("SELECT status, cur_hp FROM " . TB_PREFIX . "bandit_camps WHERE id = $fortressCampId LIMIT 1");
assertEqual(0, (int)$clearedCamp[0]['status'], "Camp status should be 0 (cleared)");
assertEqual(0, (int)$clearedCamp[0]['cur_hp'], "Camp cur_hp should be 0");

// Check expedition is resolved
$resolvedExp = $database->query_return("SELECT status FROM " . TB_PREFIX . "expeditions WHERE id = $combatExpId LIMIT 1");
assertEqual(ExpeditionEngine::STATUS_RESOLVED, (int)$resolvedExp[0]['status'], "Expedition status should be STATUS_RESOLVED");

// Check surviving troops returned to village
$vUnitsLeaderCombat = $database->getUnit($leaderVillage, false);
assertTrue((int)$vUnitsLeaderCombat['u3'] > 4000, "Leader should have majority of u3 surviving and back in village");

$vUnitsMemberCombat = $database->getUnit($memberVillage, false);
assertTrue((int)$vUnitsMemberCombat['u22'] > 2500, "Member should have majority of u22 surviving and back in village");

// Check resources gained
$vDataLeaderAfter = $database->query_return("SELECT wood FROM " . TB_PREFIX . "vdata WHERE wref = $leaderVillage LIMIT 1");
assertTrue((int)$vDataLeaderAfter[0]['wood'] > 1000, "Leader village should have gained looted wood");

// Clean up test data
$database->query("DELETE FROM " . TB_PREFIX . "expeditions WHERE id = $combatExpId");
$database->query("DELETE FROM " . TB_PREFIX . "expedition_members WHERE expedition_id = $combatExpId");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id IN ($leaderUid, $memberUid)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($leaderVillage, $memberVillage)");
$database->query("DELETE FROM " . TB_PREFIX . "expedition_daily_usage WHERE uid IN ($leaderUid, $memberUid)");

echo "All Alliance Expedition Unit & Lifecycle Tests Passed Successfully!\n";
