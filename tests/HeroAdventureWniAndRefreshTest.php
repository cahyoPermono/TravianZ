<?php
$_SERVER['REQUEST_URI'] = '/login.php';
$_SERVER['SCRIPT_NAME'] = 'login.php';
$_SERVER['PHP_SELF']    = 'login.php';

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Session.php';
require_once __DIR__ . '/../GameEngine/HeroAdventure.php';
require_once __DIR__ . '/../GameEngine/Data/hero_items.php';

global $database, $heroAdventureConfig;

echo "=== STARTING HERO ADVENTURE WNI & REFRESH TEST ===\n";

function assertTest($cond, $msg) {
    if (!$cond) {
        echo "FAILED: $msg\n";
        exit(1);
    }
    echo "PASS: $msg\n";
}

// -------------------------------------------------------------
// Test 1: Configuration check for WNI level & refresh cost
// -------------------------------------------------------------
assertTest(isset($heroAdventureConfig[2]), "WNI level (difficulty 2) exists in config");
assertTest($heroAdventureConfig[2]['label'] === 'HERO_ADV_WNI', "WNI label is HERO_ADV_WNI");
assertTest($heroAdventureConfig[2]['hp_loss'][1] >= 70, "WNI maximum HP loss is >= 70% (harder than hard)");
assertTest($heroAdventureConfig[2]['exp'][1] > $heroAdventureConfig[1]['exp'][1], "WNI EXP reward exceeds Hard EXP reward");
assertTest($heroAdventureConfig['refresh_gold_cost'] === 3, "Refresh gold cost is 3");
assertTest(HeroAdventure::DIFF_WNI === 2, "HeroAdventure::DIFF_WNI constant equals 2");
assertTest(HeroAdventure::REFRESH_GOLD_COST === 3, "HeroAdventure::REFRESH_GOLD_COST constant equals 3");

// -------------------------------------------------------------
// Test 2: Setup test player, villages, and hero
// -------------------------------------------------------------
$uname = "wni_test_" . time() . "_" . mt_rand(100, 999);
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, gold, regtime) VALUES ('$uname', 'pwd', '$uname@test.com', 1, 2, 2, " . time() . ")");
$uid = (int) mysqli_insert_id($database->dblink);

$v1 = 98901;
$site1 = 98902;
$site2 = 98903;

$database->query("INSERT INTO " . TB_PREFIX . "wdata (id, x, y, occupied, image) VALUES ($site1, 15, 15, 0, 't0') ON DUPLICATE KEY UPDATE occupied = 0");
$database->query("INSERT INTO " . TB_PREFIX . "wdata (id, x, y, occupied, image) VALUES ($site2, 18, 18, 0, 't0') ON DUPLICATE KEY UPDATE occupied = 0");

$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($v1, $uid, 'Capital', 1, 100, 500, 500, 500, 500, 10000, 10000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, hero) VALUES ($v1, 1)");

$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, dead, health, lastupdate) VALUES ($uid, 1, 'WniHero', $v1, 1, 0, 100, 0, 100, " . time() . ")");
$heroid = (int) mysqli_insert_id($database->dblink);

$advObj = new HeroAdventure();

// -------------------------------------------------------------
// Test 3: Refresh adventure with INSUFFICIENT gold (gold = 2, need 3)
// -------------------------------------------------------------
$database->query("INSERT INTO " . TB_PREFIX . "hero_adventure (uid, wref, difficulty, duration, created, expire, status) VALUES ($uid, $site1, 0, 600, " . time() . ", " . (time() + 3600) . ", 0)");
$advId1 = (int) mysqli_insert_id($database->dblink);

$refRes1 = $advObj->refreshAdventure($uid, $advId1);
assertTest($refRes1 === HeroAdventure::REFRESH_NO_GOLD, "refreshAdventure fails with REFRESH_NO_GOLD when user has 2 gold");

$userGold = (int) $database->getUserField($uid, 'gold', 0);
assertTest($userGold === 2, "User gold remains 2 after failed refresh");

$advCheck = mysqli_fetch_assoc($database->query("SELECT difficulty, wref FROM " . TB_PREFIX . "hero_adventure WHERE id = $advId1"));
assertTest((int)$advCheck['difficulty'] === 0 && (int)$advCheck['wref'] === $site1, "Adventure offer was untouched on failed refresh");

// -------------------------------------------------------------
// Test 4: Refresh adventure with SUFFICIENT gold (give 10 gold, deduct 3)
// -------------------------------------------------------------
$database->query("UPDATE " . TB_PREFIX . "users SET gold = 10 WHERE id = $uid");

$refRes2 = $advObj->refreshAdventure($uid, $advId1);
assertTest($refRes2 === HeroAdventure::REFRESH_OK, "refreshAdventure succeeds with REFRESH_OK when user has 10 gold");

$uRow = mysqli_fetch_assoc($database->query("SELECT gold FROM " . TB_PREFIX . "users WHERE id = $uid"));
$userGoldAfter = (int) $uRow['gold'];
assertTest($userGoldAfter === 7, "User gold was deducted exactly by 3 (from 10 to 7)");

$advCheck2 = mysqli_fetch_assoc($database->query("SELECT difficulty, wref, created, expire FROM " . TB_PREFIX . "hero_adventure WHERE id = $advId1"));
assertTest((int)$advCheck2['expire'] > time(), "Adventure expiration renewed after refresh");
echo "Refreshed adventure difficulty rolled: " . $advCheck2['difficulty'] . "\n";

// -------------------------------------------------------------
// Test 5: Start & Process WNI level adventure
// -------------------------------------------------------------
// Create dedicated WNI adventure offer (difficulty = 2)
$database->query("INSERT INTO " . TB_PREFIX . "hero_adventure (uid, wref, difficulty, duration, created, expire, status) VALUES ($uid, $site2, 2, 10, " . time() . ", " . (time() + 3600) . ", 0)");
$advIdWni = (int) mysqli_insert_id($database->dblink);

$startRes = $advObj->startAdventure($uid, $advIdWni);
assertTest($startRes === HeroAdventure::START_OK, "Hero successfully embarked on WNI adventure");

// Check movement created
$moveRow = mysqli_fetch_assoc($database->query("SELECT moveid, sort_type, endtime FROM " . TB_PREFIX . "movement WHERE ref = $advIdWni AND sort_type = " . MOVEMENT_ADVENTURE_OUT));
assertTest(!empty($moveRow), "Movement sort_type 20 created for WNI adventure");

// Fast-forward endtime to past so Automation/processArrivals can process it
$database->query("UPDATE " . TB_PREFIX . "movement SET endtime = " . (time() - 10) . " WHERE moveid = " . (int)$moveRow['moveid']);

$procArrivals = $advObj->processArrivals();
assertTest($procArrivals >= 1, "processArrivals processed WNI adventure arrival");

// Check return movement (sort_type 21) or death
$heroCheck = mysqli_fetch_assoc($database->query("SELECT dead, health, experience FROM " . TB_PREFIX . "hero WHERE heroid = $heroid"));
echo "Hero after WNI adventure: dead={$heroCheck['dead']}, health={$heroCheck['health']}%, XP={$heroCheck['experience']}\n";

if ($heroCheck['dead'] == 0) {
    // Hero survived WNI adventure: fast-forward return trip
    $retMove = mysqli_fetch_assoc($database->query("SELECT moveid FROM " . TB_PREFIX . "movement WHERE ref = $advIdWni AND sort_type = " . MOVEMENT_ADVENTURE_BACK));
    assertTest(!empty($retMove), "Return movement sort_type 21 created with loot");

    $database->query("UPDATE " . TB_PREFIX . "movement SET endtime = " . (time() - 10) . " WHERE moveid = " . (int)$retMove['moveid']);
    $procReturns = $advObj->processReturns();
    assertTest($procReturns >= 1, "processReturns processed hero return home");

    $unitCheck = mysqli_fetch_assoc($database->query("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $v1"));
    assertTest((int)$unitCheck['hero'] === 1, "Hero safely returned home to units.hero = 1");
} else {
    echo "Hero fell on WNI adventure as expected from high damage roll (handled safely)!\n";
}

// -------------------------------------------------------------
// Test 6: Verify Notice / Report shows WNI
// -------------------------------------------------------------
$noticeRow = mysqli_fetch_assoc($database->query("SELECT id, topic, data FROM " . TB_PREFIX . "ndata WHERE uid = $uid ORDER BY id DESC LIMIT 1"));
assertTest(!empty($noticeRow), "Report notice created in ndata");
assertTest(strpos($noticeRow['data'], 'difficulty=2') !== false, "Report notice data specifies difficulty=2 (WNI)");

parse_str($noticeRow['data'], $advData);
$difficulty = (int)($advData['difficulty'] ?? 0);
$diffLabel = $difficulty === 2
    ? (defined('HERO_ADV_WNI') ? HERO_ADV_WNI : 'an extreme (WNI) adventure')
    : ($difficulty === 1 ? 'a hard adventure' : 'a normal adventure');

assertTest(strpos($diffLabel, 'WNI') !== false, "Report diffLabel correctly identifies WNI: '$diffLabel'");

// -------------------------------------------------------------
// Cleanup
// -------------------------------------------------------------
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE ref IN ($advId1, $advIdWni)");
$database->query("DELETE FROM " . TB_PREFIX . "hero_adventure WHERE uid = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $v1");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE owner = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "ndata WHERE uid = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");

echo "\n=== ALL HERO ADVENTURE WNI & REFRESH TESTS PASSED SUCCESSFULLY! ===\n";
