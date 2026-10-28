<?php

#################################################################################
##  Unit Test: Black Market Tribe Change (Kitab Mutasi Leluhur - 100 Gold)     ##
#################################################################################

require_once __DIR__ . '/../GameEngine/config.php';
require_once __DIR__ . '/../GameEngine/Database.php';
require_once __DIR__ . '/../GameEngine/BlackMarket.php';

function assertCondition($cond, string $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running BlackMarket Tribe Change Test ===\n\n";

global $database;
$db = $database->dblink;

// 1. Verify Tribe Helpers
assertCondition(BlackMarket::getTribeName(1) === 'Romans', "Tribe 1 name is Romans");
assertCondition(BlackMarket::getTribeName(2) === 'Teutons', "Tribe 2 name is Teutons");
assertCondition(BlackMarket::getTribeName(3) === 'Gauls', "Tribe 3 name is Gauls");
assertCondition(BlackMarket::getTribeName(10) === 'Nusantara', "Tribe 10 name is Nusantara");

$avail = BlackMarket::getAvailableTribes(1);
assertCondition(!isset($avail[1]), "Exclude tribe 1 works properly");
assertCondition(isset($avail[10]), "Tribe 10 (Nusantara) is available");

assertCondition(BlackMarket::getTribeWallType(1) === 31, "Tribe 1 wall type is 31");
assertCondition(BlackMarket::getTribeWallType(2) === 32, "Tribe 2 wall type is 32");
assertCondition(BlackMarket::getTribeWallType(3) === 33, "Tribe 3 wall type is 33");
assertCondition(BlackMarket::getTribeWallType(10) === 32, "Tribe 10 wall type is 32 (Benteng Kedaton)");

// 2. Setup Test User and Test Village
$testUsername = 'test_tribe_' . time();
$pass = md5('secret123');
$email = $testUsername . '@travian.test';
$time = time();

// Insert test user with 50 gold initially (not enough for 100 gold cost)
$qUser = "INSERT INTO `" . TB_PREFIX . "users` (`username`, `password`, `email`, `tribe`, `gold`, `regtime`, `act`)
          VALUES ('$testUsername', '$pass', '$email', 1, 50, $time, '')";
mysqli_query($db, $qUser);
$testUid = mysqli_insert_id($db);
assertCondition($testUid > 0, "Test user created with ID $testUid");

// Insert fake village in vdata
$testWid = 999990 + ($testUid % 1000);
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "tdata` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "training` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "research` WHERE vref = $testWid");

$qVillage = "INSERT INTO `" . TB_PREFIX . "vdata` (`wref`, `owner`, `name`, `capital`, `pop`, `wood`, `clay`, `iron`, `crop`, `maxstore`, `maxcrop`, `lastupdate`)
             VALUES ($testWid, $testUid, 'Test Village', 1, 100, 1000, 1000, 1000, 1000, 5000, 5000, $time)";
mysqli_query($db, $qVillage);

// Setup wall f40 = 10, f40t = 31 (Roman City Wall)
$qFdata = "INSERT INTO `" . TB_PREFIX . "fdata` (`vref`, `f40`, `f40t`) VALUES ($testWid, 10, 31)";
mysqli_query($db, $qFdata);

// Setup units: 50 u1 (Legionnaire), 20 u3 (Imperian), 5 u6 (Equites Caesaris)
$qUnits = "INSERT INTO `" . TB_PREFIX . "units` (`vref`, `u1`, `u3`, `u6`, `hero`) VALUES ($testWid, 50, 20, 5, 1)";
mysqli_query($db, $qUnits);

// Setup research tdata: t2 = 1, t3 = 1, t6 = 1
$qTdata = "INSERT INTO `" . TB_PREFIX . "tdata` (`vref`, `t2`, `t3`, `t6`) VALUES ($testWid, 1, 1, 1)";
mysqli_query($db, $qTdata);

// Setup hero: unit 6 (Equites Caesaris)
$qHero = "INSERT INTO `" . TB_PREFIX . "hero` (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `dead`, `health`, `lastupdate`, `trainingtime`)
          VALUES ($testUid, 6, '$testUsername', $testWid, 5, 10, 500, 50, 0, 100, $time, $time)";
mysqli_query($db, $qHero);

// Setup training queue: 10 unit 1
$qTraining = "INSERT INTO `" . TB_PREFIX . "training` (`vref`, `unit`, `amt`, `pop`, `timestamp`, `eachtime`, `timestamp2`)
              VALUES ($testWid, 1, 10, 1, $time + 60, 6, $time)";
mysqli_query($db, $qTraining);

// Setup research queue: tech 't4'
$qResearch = "INSERT INTO `" . TB_PREFIX . "research` (`vref`, `tech`, `timestamp`)
              VALUES ($testWid, 't4', $time + 120)";
mysqli_query($db, $qResearch);

// 3. Test: Insufficient Gold check
$resFailGold = BlackMarket::changeTribe($testUid, $testWid, 10);
assertCondition(!$resFailGold['success'], "changeTribe fails when player only has 50 Gold (requires 100)");

// 4. Test: Same Tribe check
mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET gold = 200 WHERE id = $testUid");
$resSameTribe = BlackMarket::changeTribe($testUid, $testWid, 1);
assertCondition(!$resSameTribe['success'], "changeTribe fails when choosing current tribe");

// 5. Test: Successful Tribe Change from Romans (1) to Nusantara (10)
$resSuccess = BlackMarket::changeTribe($testUid, $testWid, 10);
assertCondition($resSuccess['success'], "changeTribe succeeded: " . ($resSuccess['message'] ?? ''));

// Verify Gold deduction: 200 - 100 = 100
$uCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT gold, tribe FROM `" . TB_PREFIX . "users` WHERE id = $testUid"));
assertCondition((int)$uCheck['gold'] === 100, "User gold was deducted exactly 100 Gold (now 100)");
assertCondition((int)$uCheck['tribe'] === 10, "User tribe is now 10 (Nusantara)");

// Verify Wall conversion in fdata: f40 level is still 10, f40t is now 32 (Benteng Kedaton)
$fCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT f40, f40t FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid"));
assertCondition((int)$fCheck['f40'] === 10, "Wall level preserved at 10");
assertCondition((int)$fCheck['f40t'] === 32, "Wall type converted to 32 (Benteng Kedaton)");

// Verify Hero conversion: unit 6 -> 96 (Gajah Perang)
$hCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT unit FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid"));
assertCondition((int)$hCheck['unit'] === 96, "Hero unit converted from 6 to 96 (Gajah Perang)");

// Verify Units conversion: u1 -> u91 (50), u3 -> u93 (20), u6 -> u96 (5), old cols 0
$unCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT u1, u3, u6, u91, u93, u96 FROM `" . TB_PREFIX . "units` WHERE vref = $testWid"));
assertCondition((int)$unCheck['u1'] === 0 && (int)$unCheck['u91'] === 50, "Troop u1 (50) migrated to u91 (50)");
assertCondition((int)$unCheck['u3'] === 0 && (int)$unCheck['u93'] === 20, "Troop u3 (20) migrated to u93 (20)");
assertCondition((int)$unCheck['u6'] === 0 && (int)$unCheck['u96'] === 5, "Troop u6 (5) migrated to u96 (5)");

// Verify Research conversion in tdata: t2 -> t92, t3 -> t93, t6 -> t96
$tCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT t2, t3, t6, t92, t93, t96 FROM `" . TB_PREFIX . "tdata` WHERE vref = $testWid"));
assertCondition((int)$tCheck['t2'] === 0 && (int)$tCheck['t92'] === 1, "Research t2 migrated to t92");
assertCondition((int)$tCheck['t3'] === 0 && (int)$tCheck['t93'] === 1, "Research t3 migrated to t93");
assertCondition((int)$tCheck['t6'] === 0 && (int)$tCheck['t96'] === 1, "Research t6 migrated to t96");

// Verify Training queue conversion: unit 1 -> 91
$trCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT unit FROM `" . TB_PREFIX . "training` WHERE vref = $testWid"));
assertCondition((int)$trCheck['unit'] === 91, "Training queue unit 1 converted to 91");

// Verify Research queue conversion: tech 't4' -> 't94'
$rCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT tech FROM `" . TB_PREFIX . "research` WHERE vref = $testWid"));
assertCondition($rCheck['tech'] === 't94', "Research queue tech 't4' converted to 't94'");

// Verify Gold Fin Log audit trail
$gLog = mysqli_fetch_assoc(mysqli_query($db, "SELECT * FROM `" . TB_PREFIX . "gold_fin_log` WHERE uid = $testUid ORDER BY id DESC LIMIT 1"));
assertCondition(!empty($gLog) && (int)$gLog['gold'] === -100, "Gold fin log recorded -100 Gold");

// Cleanup test records
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "users` WHERE id = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "vdata` WHERE wref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "units` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "tdata` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "training` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "research` WHERE vref = $testWid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "gold_fin_log` WHERE uid = $testUid");
mysqli_query($db, "DELETE FROM `" . TB_PREFIX . "blackmarket_log` WHERE uid = $testUid");

echo "\n>>> ALL BLACK MARKET TRIBE CHANGE TESTS PASSED SUCCESSFULLY! <<<\n";
