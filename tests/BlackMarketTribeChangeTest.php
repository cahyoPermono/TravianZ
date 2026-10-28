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

// Setup wall f40 = 10, f40t = 31 (Roman City Wall), f19 = 5 (Residence 25), f20 = 3 (Hospital 46)
$qFdata = "INSERT INTO `" . TB_PREFIX . "fdata` (`vref`, `f19`, `f19t`, `f20`, `f20t`, `f40`, `f40t`) VALUES ($testWid, 5, 25, 3, 46, 10, 31)";
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
mysqli_query($db, "UPDATE `" . TB_PREFIX . "users` SET gold = 500 WHERE id = $testUid");
$resSameTribe = BlackMarket::changeTribe($testUid, $testWid, 1);
assertCondition(!$resSameTribe['success'], "changeTribe fails when choosing current tribe");

// 5. Test: Successful Tribe Change from Romans (1) to Nusantara (10)
$resSuccess = BlackMarket::changeTribe($testUid, $testWid, 10);
assertCondition($resSuccess['success'], "changeTribe succeeded: " . ($resSuccess['message'] ?? ''));

// Verify Gold deduction: 500 - 100 = 400
$uCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT gold, tribe FROM `" . TB_PREFIX . "users` WHERE id = $testUid"));
assertCondition((int)$uCheck['gold'] === 400, "User gold was deducted exactly 100 Gold (now 400)");
assertCondition((int)$uCheck['tribe'] === 10, "User tribe is now 10 (Nusantara)");

// Verify Wall conversion in fdata: f40 level is still 10, f40t is now 32 (Benteng Kedaton)
$fCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT f40, f40t, f19t, f20t FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid"));
assertCondition((int)$fCheck['f40'] === 10, "Wall level preserved at 10");
assertCondition((int)$fCheck['f40t'] === 32, "Wall type converted to 32 (Benteng Kedaton)");
assertCondition((int)$fCheck['f19t'] === 25, "Residence preserved for Nusantara");
assertCondition((int)$fCheck['f20t'] === 46, "Regular Hospital preserved for Nusantara");

// 6. Test: Change to Huns (Tribe 6) -> Residence (25) converts to Command Center (44)
$resHun = BlackMarket::changeTribe($testUid, $testWid, 6);
assertCondition($resHun['success'], "changeTribe to Huns (6) succeeded");
$fHunCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT f19, f19t, f40t FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid"));
assertCondition((int)$fHunCheck['f19t'] === 44, "Residence (25) converted to Command Center (44) for Huns");
assertCondition((int)$fHunCheck['f40t'] === 42, "Wall converted to 42 (Makeshift Wall) for Huns");

// 7. Test: Change from Huns (6) to Sparta (8) -> Command Center converts to Palace (capital=1) & Hospital converts to Big Hospital (48)
$resSparta = BlackMarket::changeTribe($testUid, $testWid, 8);
assertCondition($resSparta['success'], "changeTribe to Sparta (8) succeeded");
$fSpartaCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT f19t, f20t, f40t FROM `" . TB_PREFIX . "fdata` WHERE vref = $testWid"));
assertCondition((int)$fSpartaCheck['f19t'] === 26, "Command Center (44) converted to Palace (26) in capital for Sparta");
assertCondition((int)$fSpartaCheck['f20t'] === 48, "Hospital (46) converted to Big Hospital (48) for Sparta");
assertCondition((int)$fSpartaCheck['f40t'] === 47, "Wall converted to 47 (Spartan Wall) for Sparta");

// Verify Hero conversion: unit 6 -> 96 -> 56 -> 76 (Elpida Rider)
$hCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT unit FROM `" . TB_PREFIX . "hero` WHERE uid = $testUid"));
assertCondition((int)$hCheck['unit'] === 76, "Hero unit properly migrated across tribes to 76 (Spartan Elpida Rider)");

// Verify Units conversion: after migrating Romans -> Nusantara -> Huns -> Sparta, troops are in u71 (50), u73 (20), u76 (5)
$unCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT u1, u91, u51, u71, u73, u76 FROM `" . TB_PREFIX . "units` WHERE vref = $testWid"));
assertCondition((int)$unCheck['u1'] === 0 && (int)$unCheck['u91'] === 0 && (int)$unCheck['u51'] === 0 && (int)$unCheck['u71'] === 50, "Troops migrated cleanly to Sparta u71 (50)");
assertCondition((int)$unCheck['u73'] === 20, "Troop u73 is 20 (Spartan Shieldsworn)");
assertCondition((int)$unCheck['u76'] === 5, "Troop u76 is 5 (Spartan Elpida Rider)");

// Verify Research conversion in tdata: migrated to t72, t73, t76
$tCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT t2, t92, t52, t72, t73, t76 FROM `" . TB_PREFIX . "tdata` WHERE vref = $testWid"));
assertCondition((int)$tCheck['t2'] === 0 && (int)$tCheck['t92'] === 0 && (int)$tCheck['t72'] === 1, "Research cleanly migrated to Sparta t72");
assertCondition((int)$tCheck['t73'] === 1, "Research t73 is 1");
assertCondition((int)$tCheck['t76'] === 1, "Research t76 is 1");

// Verify Training queue conversion: unit 1 -> 91 -> 51 -> 71
$trCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT unit FROM `" . TB_PREFIX . "training` WHERE vref = $testWid"));
assertCondition((int)$trCheck['unit'] === 71, "Training queue unit migrated to 71 (Spartan Hoplite)");

// Verify Research queue conversion: tech 't4' -> 't94' -> 't54' -> 't74'
$rCheck = mysqli_fetch_assoc(mysqli_query($db, "SELECT tech FROM `" . TB_PREFIX . "research` WHERE vref = $testWid"));
assertCondition($rCheck['tech'] === 't74', "Research queue tech migrated to 't74'");

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
