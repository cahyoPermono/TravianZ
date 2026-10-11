<?php
/**
 * TravianZ Emergency Nusantara Research & Schema Resolver
 * -------------------------------------------------------
 * 1. Adds missing columns t91..t100 to tdata if not present.
 * 2. Immediately processes and finishes any stuck research in the research table.
 */

require_once __DIR__ . '/GameEngine/config.php';
require_once __DIR__ . '/GameEngine/Database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TravianZ Nusantara Research Resolver ===\n\n";

// 1. Check & Add missing columns to tdata table
$res = mysqli_query($database->dblink, "SHOW COLUMNS FROM " . TB_PREFIX . "tdata");
$existingColumns = [];
while ($row = mysqli_fetch_assoc($res)) {
    $existingColumns[$row['Field']] = true;
}

$columnsToAdd = [];
for ($i = 91; $i <= 100; $i++) {
    $col = 't' . $i;
    if (!isset($existingColumns[$col])) {
        $prevCol = ($i === 91) ? 't90' : ('t' . ($i - 1));
        $columnsToAdd[] = "ADD COLUMN `$col` tinyint(1) NULL DEFAULT '0' AFTER `$prevCol`";
    }
}

if (!empty($columnsToAdd)) {
    echo "Adding missing columns (" . count($columnsToAdd) . ") to table " . TB_PREFIX . "tdata...\n";
    $alterSql = "ALTER TABLE " . TB_PREFIX . "tdata " . implode(', ', $columnsToAdd);
    if (mysqli_query($database->dblink, $alterSql)) {
        echo "[OK] Successfully added columns: " . implode(', ', array_map(function($i) { return 't' . $i; }, range(91, 100))) . "\n\n";
    } else {
        echo "[ERROR] Failed to alter table: " . mysqli_error($database->dblink) . "\n\n";
    }
} else {
    echo "[INFO] Columns t91..t100 already exist in table " . TB_PREFIX . "tdata.\n\n";
}

// 2. Resolve any stuck research
$now = time();
$q = "SELECT id, vref, tech, timestamp FROM " . TB_PREFIX . "research WHERE timestamp <= $now";
$stuck = $database->query_return($q);

if (!empty($stuck)) {
    echo "Found " . count($stuck) . " completed research item(s) pending resolution:\n";
    $deleteIds = [];
    $tdataUpdates = [];
    $abdataUpdates = [];

    foreach ($stuck as $r) {
        $id = (int)$r['id'];
        $vid = (int)$r['vref'];
        $tech = $r['tech'];
        $prefix = substr($tech, 0, 1);

        echo " - Resolving Research #$id: Village=$vid, Tech=$tech (Expired at " . date('Y-m-d H:i:s', $r['timestamp']) . ")\n";
        $deleteIds[] = $id;

        if ($prefix === 't') {
            if (!isset($tdataUpdates[$vid])) $tdataUpdates[$vid] = [];
            $tdataUpdates[$vid][] = "$tech = 1";
        } elseif ($prefix === 'a' || $prefix === 'b') {
            if (!isset($abdataUpdates[$vid])) $abdataUpdates[$vid] = [];
            $abdataUpdates[$vid][] = "$tech = $tech + 1";
        }
    }

    // Apply tdata updates
    foreach ($tdataUpdates as $vid => $sets) {
        $sql = "UPDATE " . TB_PREFIX . "tdata SET " . implode(', ', $sets) . " WHERE vref = $vid";
        if (mysqli_query($database->dblink, $sql)) {
            echo "   [OK] Updated tdata for village $vid (" . implode(', ', $sets) . ")\n";
        } else {
            echo "   [ERROR] Failed to update tdata for village $vid: " . mysqli_error($database->dblink) . "\n";
        }
    }

    // Apply abdata updates
    foreach ($abdataUpdates as $vid => $sets) {
        $sql = "UPDATE " . TB_PREFIX . "abdata SET " . implode(', ', $sets) . " WHERE vref = $vid";
        if (mysqli_query($database->dblink, $sql)) {
            echo "   [OK] Updated abdata for village $vid (" . implode(', ', $sets) . ")\n";
        } else {
            echo "   [ERROR] Failed to update abdata for village $vid: " . mysqli_error($database->dblink) . "\n";
        }
    }

    // Delete resolved research
    if (!empty($deleteIds)) {
        $delSql = "DELETE FROM " . TB_PREFIX . "research WHERE id IN (" . implode(', ', $deleteIds) . ")";
        if (mysqli_query($database->dblink, $delSql)) {
            echo "   [OK] Deleted " . count($deleteIds) . " resolved research row(s).\n";
        } else {
            echo "   [ERROR] Failed to delete research rows: " . mysqli_error($database->dblink) . "\n";
        }
    }
} else {
    echo "[INFO] No stuck research items found.\n";
}

echo "\nDone! Nusantara research is fully operational.\n";
