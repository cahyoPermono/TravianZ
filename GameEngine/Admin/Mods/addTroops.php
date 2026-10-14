<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       addTroops.php                                               ##
##  Type           BACKEND                                                     ##
##  Developed by:  Dzoki & Advocatie                                           ##
##  License:       TravianZ Project                                            ##
##  Reworks by:    ronix                                                       ##
##  Copyright:     TravianZ (c) 2010-2025. All rights reserved.                ##
##                                                                             ##
#################################################################################

// #299: load CSRF helpers + admin_deny() before the access check below.
require_once(__DIR__ . '/../csrf.php');
if (!isset($_SESSION)) {
    session_start();
}
if (empty($_SESSION['access']) || $_SESSION['access'] < (defined('MULTIHUNTER') ? MULTIHUNTER : 8)) {
    admin_deny('You must be signed in as an administrator to view this page. Your session may have expired — please return to the admin panel and sign in again.');
}

// Issue #139: this Mod is POSTed to directly, so it must verify the CSRF token
// itself (it does not go through admin.php's central csrf_verify()).
require_once(__DIR__ . '/../csrf.php');
csrf_verify();

include_once __DIR__ . "/../../Database.php";
include_once __DIR__ . "/../../Technology.php";
include_once __DIR__ . "/../../Data/unitdata.php";

/* ---------------------------------------------------------------------------
 * Input & validare
 * --------------------------------------------------------------------------- */
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: ../../../Admin/admin.php");
    exit;
}

$village = $database->getVillage($id);
$user = $database->getUserArray($village['owner'], 1);
$tribe = (int)$user['tribe'];
$u = ($tribe - 1) * 10;

/* ---------------------------------------------------------------------------
 * Construiește SET pentru u1-u10 / u11-u20 etc.
 * - originalul concatena escape($_POST + ",") greșit
 * - aici cast la int + implode
 * --------------------------------------------------------------------------- */
$fields = [];
for ($i = 1; $i <= 10; $i++) {
    $unitId = $u + $i;
    $addVal = (int)($_POST['add_u' . $unitId] ?? 0);
    if ($addVal > 0) {
        $fields[] = "u$unitId = u$unitId + $addVal";
    } elseif (isset($_POST['u' . $unitId])) {
        $val = max(0, (int)$_POST['u' . $unitId]);
        $fields[] = "u$unitId = $val";
    }
}

// Hero adjustment
if (isset($_POST['hero'])) {
    $heroVal = max(0, (int)$_POST['hero']);
    $fields[] = "hero = $heroVal";

    $uOwner = (int)$user['id'];
    if ($heroVal == 1) {
        $heroCheck = $database->query_return("SELECT heroid FROM " . TB_PREFIX . "hero WHERE uid = $uOwner LIMIT 1");
        if (!empty($heroCheck)) {
            $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 0, health = 100, inrevive = 0, intraining = 0, wref = $id WHERE uid = $uOwner");
        } else {
            $basicUnit = $u + 1;
            $heroNameEsc = $database->escape(($user['username'] ?? 'Player') . "'s Hero");
            $database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `production`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `autoregen`, `lastupdate`) VALUES ($uOwner, $basicUnit, '$heroNameEsc', $id, 0, 0, 0, 100, 0, 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, " . time() . ")");
        }
    } elseif ($heroVal == 0) {
        $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0, inrevive = 0, intraining = 0 WHERE uid = $uOwner");
    }
}

if (!empty($fields)) {
    if (method_exists($database, 'addUnits')) {
        $database->addUnits($id);
    }
    $q = "UPDATE " . TB_PREFIX . "units SET " . implode(", ", $fields) . " WHERE vref = $id";
    $database->query($q);

    // Invalidate in-memory caches
    if (class_exists('DatabaseHeroQueries')) {
        \DatabaseHeroQueries::$heroCache = [];
        \DatabaseHeroQueries::$heroFieldCache = [];
        \DatabaseHeroQueries::$heroVillageCache = [];
    }
    if (method_exists($database, 'clearUnitsCache')) {
        $database->clearUnitsCache();
    }
}

/* ---------------------------------------------------------------------------
 * Log admin - adaptat pentru tabelul tău
 * --------------------------------------------------------------------------- */
$adminId = (string)(int)$_SESSION['id'];
$time = time();

$villageName = $village['name'] ?? 'Village';
$villageNameSafe = htmlspecialchars($villageName, ENT_QUOTES, 'UTF-8');

$logText = "Updated / added troop amounts in village <a href='admin.php?p=addTroops&did=$id'>$villageNameSafe</a> (Owner: " . htmlspecialchars($user['username'] ?? '') . ")";

$adminIdEsc = $database->escape($adminId);
$logEsc = $database->escape($logText);

$database->query(
    "INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) " .
    "VALUES (0, '$adminIdEsc', '$logEsc', $time)"
);

$database->addStarvationData($id);

header("Location: ../../../Admin/admin.php?p=addTroops&did=" . $id . "&saved=1");
exit;
?>