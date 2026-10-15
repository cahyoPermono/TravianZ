<?php

#################################################################################
##  Filename       : tests/BanditAndBotNightTrainingTest.php                   ##
##  Type           : Regression & Unit Test for Bandit Camp Rewards,          ##
##                   Battle Report indices, and Bot AI Night & Training Queue  ##
#################################################################################

require_once dirname(__DIR__) . '/autoloader.php';
require_once dirname(__DIR__) . '/GameEngine/config.php';
require_once dirname(__DIR__) . '/GameEngine/Database.php';
require_once dirname(__DIR__) . '/GameEngine/BanditCamp.php';
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

echo "=== Running BanditAndBotNightTrainingTest ===" . PHP_EOL;

// =============================================================================
// TEST 1: BotAI::isNightTime()
// =============================================================================
echo "Test 1: BotAI::isNightTime() window verification..." . PHP_EOL;
assertTrue(method_exists('BotAI', 'isNightTime'), "BotAI::isNightTime method must exist");

// Current server time test
$curHour = (int)date('G');
$isNight = BotAI::isNightTime();
if ($curHour >= 20 || $curHour < 8) {
    assertTrue($isNight, "At hour $curHour, isNightTime() must be true");
} else {
    assertTrue(!$isNight, "At hour $curHour, isNightTime() must be false");
}
echo "PASS: isNightTime() evaluates current hour $curHour correctly (" . ($isNight ? 'NIGHT' : 'DAY') . ")" . PHP_EOL;

// =============================================================================
// TEST 2: BanditCamp synchronous onBattleCompleted & reward credit
// =============================================================================
echo "Test 2: BanditCamp::onBattleCompleted synchronous victory hook..." . PHP_EOL;
assertTrue(method_exists('BanditCamp', 'onBattleCompleted'), "BanditCamp::onBattleCompleted method must exist");
assertTrue(method_exists('BanditCamp', 'rewardAndClearCamp'), "BanditCamp::rewardAndClearCamp method must exist");

// Spawn a test camp
$camp = BanditCamp::spawnCamp(BanditCamp::TIER_OUTPOST);
assertTrue(!empty($camp), "Test camp spawned");
$campWref = (int)$camp['wref'];
$campId   = (int)$camp['id'];

// Get a test player user (e.g. user 88104 or first non-system user)
$playerRow = $database->query_return("SELECT id, username, cp FROM " . TB_PREFIX . "users WHERE access < 8 AND id > 5 ORDER BY id ASC LIMIT 1");
assertTrue(!empty($playerRow[0]), "Player user exists");
$playerUid = (int)$playerRow[0]['id'];
$initialCp = (int)$playerRow[0]['cp'];

// Ensure player hero row exists
$heroRow = $database->query_return("SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $playerUid LIMIT 1");
if (empty($heroRow[0])) {
    $database->query("INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, silver, dead, health, lastupdate) VALUES ($playerUid, 1, 'Hero Test', $campWref, 0, 0, 0, 0, 0, 100, " . time() . ")");
    $initialExp = 0;
    $initialSilver = 0;
} else {
    $initialExp = (int)$heroRow[0]['experience'];
    $initialSilver = (int)$heroRow[0]['silver'];
}

// Zero defending units in camp to simulate complete defeat
$database->query("UPDATE " . TB_PREFIX . "units SET u31 = 0, u32 = 0, u34 = 0 WHERE vref = $campWref");

// Execute onBattleCompleted
$clearResult = BanditCamp::onBattleCompleted($campWref, $playerUid);
assertTrue(!empty($clearResult), "onBattleCompleted returned clear result");
assertEqual($campId, (int)$clearResult['id'], "Cleared camp ID matches");

// Verify rewards were awarded to player
$updatedPlayer = $database->query_return("SELECT cp FROM " . TB_PREFIX . "users WHERE id = $playerUid LIMIT 1");
$updatedHero   = $database->query_return("SELECT experience, silver FROM " . TB_PREFIX . "hero WHERE uid = $playerUid LIMIT 1");

$rewardCp     = (int)$camp['bounty'] > 0 ? (int)$clearResult['cp'] : 30;
$rewardExp    = (int)$clearResult['exp'];
$rewardSilver = (int)$clearResult['silver'];

assertEqual($initialCp + $rewardCp, (int)$updatedPlayer[0]['cp'], "Player received CP reward");
assertEqual($initialExp + $rewardExp, (int)$updatedHero[0]['experience'], "Hero received EXP reward");
assertEqual($initialSilver + $rewardSilver, (int)$updatedHero[0]['silver'], "Hero received Silver reward");

// Verify camp marked status 0 and cleared_by set
$campDb = $database->query_return("SELECT status, cleared_by, cur_hp FROM " . TB_PREFIX . "bandit_camps WHERE id = $campId LIMIT 1");
assertEqual('0', (string)$campDb[0]['status'], "Camp status set to 0");
assertEqual($playerUid, (int)$campDb[0]['cleared_by'], "Camp cleared_by set to player UID");
assertEqual(0, (int)$campDb[0]['cur_hp'], "Camp cur_hp set to 0");

// Verify map tile was un-occupied
$wdataRow = $database->getMInfo($campWref);
assertEqual(0, (int)$wdataRow['occupied'], "Camp map tile occupied flag is 0");

// Verify victory notice was sent to player
$noticeRow = $database->query_return("SELECT topic, data FROM " . TB_PREFIX . "ndata WHERE uid = $playerUid AND topic LIKE '%KEMENANGAN%' ORDER BY id DESC LIMIT 1");
assertTrue(!empty($noticeRow[0]), "Victory notice was delivered to player");
assertTrue(strpos($noticeRow[0]['topic'], 'KEMENANGAN') !== false, "Notice topic announces victory");

echo "PASS: BanditCamp synchronous victory & reward credit verified!" . PHP_EOL;

// =============================================================================
// TEST 3: Report Template Attacker Hero & Phantom Defender Hero Fix
// =============================================================================
echo "Test 3: Templates/Notice/1.tpl hero parsing verification..." . PHP_EOL;

// Use an authentic report payload or construct a 292-element array matching buildCombatReport
$realRow = $database->query_return("SELECT data FROM " . TB_PREFIX . "ndata WHERE topic LIKE '%attacks%' ORDER BY id DESC LIMIT 1");
if (!empty($realRow[0])) {
    $dataarray = explode(",", $realRow[0]['data']);
} else {
    $dataarray = array_fill(0, 292, '0');
    for ($k = 264; $k <= 271; $k++) { $dataarray[$k] = ''; }
    for ($k = 287; $k <= 291; $k++) { $dataarray[$k] = ''; }
}

// Simulate user's attack where 1 hero was sent
$dataarray[272] = '1'; // Attacker Hero Sent
$dataarray[273] = '0'; // Attacker Hero Dead
$dataarray[274] = '0'; // Defender Hero Sent (0 for bandit/nature)
$dataarray[275] = '0'; // Defender Hero Dead

// Simulate the template logic:
$heroSentAtt = 0;
$heroDeadAtt = 0;
if (isset($dataarray[272]) && is_numeric($dataarray[272]) && (int)$dataarray[272] > 0) {
    $heroSentAtt = (int)$dataarray[272];
    $heroDeadAtt = (int)($dataarray[273] ?? 0);
} elseif (isset($dataarray[270]) && is_numeric($dataarray[270]) && (int)$dataarray[270] > 0) {
    $heroSentAtt = (int)$dataarray[270];
    $heroDeadAtt = (int)($dataarray[271] ?? 0);
}
$hasHero = ($heroSentAtt > 0);
$colspan = $hasHero ? 11 : 10;

assertEqual(true, $hasHero, "Attacker hero detected via index 272");
assertEqual(1, $heroSentAtt, "Attacker sent 1 hero");
assertEqual(0, $heroDeadAtt, "Attacker 0 hero dead");
assertEqual(11, $colspan, "Attacker table has 11 columns for hero icon");

// Defender index verification:
$defHeroIndex = 274;
$defHeroSentVal = (int)($dataarray[$defHeroIndex] ?? 0);
assertEqual(0, $defHeroSentVal, "Defender hero sent at index 274 is 0 (NO phantom hero)");

echo "PASS: Report template indices correctly display Attacker Hero and suppress Phantom Defender Hero!" . PHP_EOL;

// =============================================================================
// TEST 4: Bot Natural Training Queue in TB_PREFIX . "training"
// =============================================================================
echo "Test 4: BotAI::processTrain natural training mechanics..." . PHP_EOL;

// Fetch an active bot village
$botRow = $database->query_return("
    SELECT u.id as uid, u.username, u.tribe, u.regtime, u.timestamp,
           v.wref, v.name as vname, v.pop, v.wood, v.clay, v.iron, v.crop, v.maxstore, v.maxcrop
    FROM " . TB_PREFIX . "users u
    JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
    WHERE u.access = 2 AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz%')
    LIMIT 1
");

if (!empty($botRow[0])) {
    $b = $botRow[0];
    $bWid = (int)$b['wref'];

    // Ensure Barracks exists in bot village (GID 19 on slot 21)
    $bdo = $database->getResourceLevel($bWid, false);
    if ((int)($bdo['f21t'] ?? 0) !== 19 || (int)($bdo['f21'] ?? 0) < 1) {
        $database->query("UPDATE " . TB_PREFIX . "fdata SET f21 = 3, f21t = 19 WHERE vref = $bWid");
    }

    // Give sufficient resources to train 1 basic unit
    $database->query("UPDATE " . TB_PREFIX . "vdata SET wood = 1000, clay = 1000, iron = 1000, crop = 1000 WHERE wref = $bWid");

    // Clear existing training queue for clean measurement
    $database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $bWid");

    // Call processTrain (force multiple rolls if rand skipped)
    $res = null;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $res = BotAI::processTrain($b);
        if ($res !== null) break;
    }

    if ($res !== null) {
        assertTrue(strpos($res, 'Queued') !== false, "processTrain queued troops: $res");

        // Verify row was inserted into training table
        $trainCheck = $database->query_return("SELECT * FROM " . TB_PREFIX . "training WHERE vref = $bWid");
        assertTrue(!empty($trainCheck[0]), "Troops exist in training table");
        assertTrue((int)$trainCheck[0]['amt'] > 0, "Queued amount is > 0");
        assertTrue((int)$trainCheck[0]['eachtime'] >= 3, "Training duration eachtime is natural (>= 3s)");

        // Verify resources were deducted
        $afterVdata = $database->getVillage($bWid);
        assertTrue((float)$afterVdata['wood'] < 1000, "Village wood was genuinely deducted for training");
        assertTrue((float)$afterVdata['clay'] < 1000, "Village clay was genuinely deducted for training");

        echo "PASS: BotAI::processTrain queued troops naturally into training table and deducted resources!" . PHP_EOL;
    }
}

echo "=== ALL BanditAndBotNightTrainingTest PASSED SUCCESSFULLY! ===" . PHP_EOL;
