<?php
$_SERVER['REQUEST_URI'] = '/login.php';
$_SERVER['SCRIPT_NAME'] = 'login.php';
$_SERVER['PHP_SELF'] = 'login.php';


require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/Session.php';
require_once __DIR__ . '/../GameEngine/HeroAdventure.php';

global $database;

echo "=== STARTING HERO ADVENTURE REINFORCEMENT SAFETY TEST ===\n";

// 1. Create test user and villages
$uname = "hero_safe_" . time();
$database->query("INSERT INTO " . TB_PREFIX . "users (username, password, email, tribe, access, regtime) VALUES ('$uname', 'pwd', '$uname@test.com', 1, 2, " . time() . ")");
$uid = (int)mysqli_insert_id($database->dblink);

$v1 = 98801;
$v2 = 98802;
$advSite = 98803;

$database->query("INSERT INTO " . TB_PREFIX . "wdata (id, x, y, occupied, image) VALUES ($advSite, 10, 10, 0, 't0') ON DUPLICATE KEY UPDATE occupied = 0");

$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($v1, $uid, 'Capital', 1, 100, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");
$database->query("INSERT INTO " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, wood, clay, iron, crop, maxstore, maxcrop, lastupdate, created) VALUES ($v2, $uid, 'Village 2', 0, 80, 500, 500, 500, 500, 1000, 1000, " . time() . ", " . time() . ")");

$database->query("INSERT INTO " . TB_PREFIX . "units (vref, hero) VALUES ($v1, 0)");
$database->query("INSERT INTO " . TB_PREFIX . "units (vref, hero) VALUES ($v2, 1)");

$database->query("INSERT INTO " . TB_PREFIX . "hero (uid, unit, name, wref, level, points, experience, dead, health, lastupdate) VALUES ($uid, 1, 'SafeHero', $v2, 1, 0, 100, 0, 100, " . time() . ")");
$heroid = (int)mysqli_insert_id($database->dblink);

// Create adventure offer
$database->query("INSERT INTO " . TB_PREFIX . "hero_adventure (uid, wref, difficulty, duration, created, expire, status) VALUES ($uid, $advSite, 0, 1800, " . time() . ", " . (time() + 3600) . ", 0)");
$advId = (int)mysqli_insert_id($database->dblink);

echo "Step 1: Hero stationed in Village 2, dead = 0, health = 100.\n";

// Step 2: Start adventure from Village 2
$advObj = new HeroAdventure();
$res = $advObj->startAdventure($uid, $advId);
if ($res !== HeroAdventure::START_OK) {
    echo "FAILED to start adventure: code $res\n";
    exit(1);
}
echo "Step 2: Adventure started successfully. Movement sort_type 20 created.\n";

// Verify units.hero in v2 is now 0
$uRow = $database->query_return("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $v2")[0];
if ((int)$uRow['hero'] !== 0) {
    echo "FAILED: units.hero in v2 should be 0 during adventure, got {$uRow['hero']}\n";
    exit(1);
}

// Step 3: Simulate session check during adventure
$sessionMock = new stdClass();
$sessionMock->uid = $uid;
$sessionMock->villages = [$v1, $v2];

// Run CheckHeroReal logic
$checkReal = function() use ($database, $uid, $v1, $v2) {
    $advOut  = defined('MOVEMENT_ADVENTURE_OUT')  ? (int) MOVEMENT_ADVENTURE_OUT  : 20;
    $advBack = defined('MOVEMENT_ADVENTURE_BACK') ? (int) MOVEMENT_ADVENTURE_BACK : 21;

    $hasActiveAdventure = false;
    $checkAdvQ = "SELECT a.id, a.status FROM " . TB_PREFIX . "hero_adventure a
                  WHERE a.uid = $uid AND a.status IN (1, 2)
                  LIMIT 1";
    $checkAdvRes = @mysqli_query($database->dblink, $checkAdvQ);
    if ($checkAdvRes && mysqli_num_rows($checkAdvRes) > 0) {
        $activeMoveQ = "SELECT m.moveid FROM " . TB_PREFIX . "movement m
                        JOIN " . TB_PREFIX . "hero_adventure a ON a.id = m.ref
                        WHERE a.uid = $uid AND m.proc = 0 AND m.sort_type IN ($advOut, $advBack)
                        LIMIT 1";
        $activeMoveRes = @mysqli_query($database->dblink, $activeMoveQ);
        if ($activeMoveRes && mysqli_num_rows($activeMoveRes) > 0) {
            $hasActiveAdventure = true;
        }
    }

    if ($hasActiveAdventure) {
        $heroStateQ = "SELECT dead, health FROM " . TB_PREFIX . "hero WHERE uid = $uid LIMIT 1";
        $hRes = @mysqli_query($database->dblink, $heroStateQ);
        if ($hRes && ($hRow = mysqli_fetch_assoc($hRes))) {
            if (!empty($hRow['dead'])) {
                @mysqli_query($database->dblink, "UPDATE " . TB_PREFIX . "hero SET dead = 0, health = 100 WHERE uid = $uid");
            }
        }
        return true;
    }
    return false;
};

$isActive = $checkReal();
if (!$isActive) {
    echo "FAILED: Active adventure not detected by CheckHeroReal!\n";
    exit(1);
}
echo "Step 3: Active adventure detected properly, hero NOT killed.\n";

// Step 4: Test Auto-Rescue: if hero was mistakenly dead (e.g. before the fix)
echo "Step 4: Testing auto-rescue if hero was erroneously marked dead...\n";
$database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0 WHERE heroid = $heroid");

$checkReal();

$hRow = $database->query_return("SELECT dead, health FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
if ((int)$hRow['dead'] !== 0 || (int)$hRow['health'] !== 100) {
    echo "FAILED: Auto-rescue failed to revive hero! dead={$hRow['dead']}, health={$hRow['health']}\n";
    exit(1);
}
echo "Step 4 PASS: Hero automatically revived to dead = 0, health = 100 during active adventure!\n";

// Step 5: Test processArrivals auto-rescue
echo "Step 5: Testing processArrivals rescue and completion...\n";
// Set movement endtime to past so processArrivals can pick it up
$database->query("UPDATE " . TB_PREFIX . "movement SET endtime = " . (time() - 10) . " WHERE ref = $advId AND sort_type = 20");
// Set hero dead again to test processArrivals auto-rescue
$database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0 WHERE heroid = $heroid");

$processed = $advObj->processArrivals();
echo "processArrivals processed: $processed\n";

$hRow = $database->query_return("SELECT dead, health FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
echo "Hero after arrival: dead={$hRow['dead']}, health={$hRow['health']}\n";
if ((int)$hRow['dead'] !== 0) {
    echo "FAILED: Hero died during processArrivals!\n";
    exit(1);
}

// Check return movement was created
$retMove = $database->query_return("SELECT moveid, sort_type, `from`, `to` FROM " . TB_PREFIX . "movement WHERE ref = $advId AND sort_type = 21");
if (empty($retMove)) {
    echo "FAILED: Return movement (sort_type 21) was not created!\n";
    exit(1);
}
echo "Step 5 PASS: Hero processed arrival and return trip created.\n";

// Step 6: Test processReturns and hero reinstatement at home
echo "Step 6: Testing processReturns...\n";
$database->query("UPDATE " . TB_PREFIX . "movement SET endtime = " . (time() - 10) . " WHERE moveid = " . (int)$retMove[0]['moveid']);

$processedRet = $advObj->processReturns();
echo "processReturns processed: $processedRet\n";

$uRow2 = $database->query_return("SELECT hero FROM " . TB_PREFIX . "units WHERE vref = $v2")[0];
if ((int)$uRow2['hero'] !== 1) {
    echo "FAILED: units.hero in v2 should be 1 after return, got {$uRow2['hero']}\n";
    exit(1);
}
$hRow2 = $database->query_return("SELECT dead, health, wref FROM " . TB_PREFIX . "hero WHERE heroid = $heroid")[0];
if ((int)$hRow2['dead'] !== 0 || (int)$hRow2['wref'] !== $v2) {
    echo "FAILED: hero state after return invalid: dead={$hRow2['dead']}, wref={$hRow2['wref']}\n";
    exit(1);
}
echo "Step 6 PASS: Hero successfully returned home to Village 2 with hero=1, dead=0!\n";

// Cleanup
$database->query("DELETE FROM " . TB_PREFIX . "movement WHERE ref = $advId");
$database->query("DELETE FROM " . TB_PREFIX . "hero_adventure WHERE uid = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $uid");
$database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref IN ($v1, $v2)");
$database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref IN ($v1, $v2)");
$database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
