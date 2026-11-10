<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/oasisManager.tpl                          ##
##  Type           : Admin Panel Frontend & Map Tile Oasis Management GUI      ##
##  Purpose        : Web-based GUI to inspect any world tile and convert it    ##
##                   into any desired Oasis (1..12) with custom/auto nature    ##
##                   garrison, initial resources, or revert back to valley.    ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (!defined('MULTIHUNTER')) {
    define('MULTIHUNTER', 8);
}
if (!defined('ADMIN')) {
    define('ADMIN', 9);
}
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
    }
}

// Access control: Administrator or Multihunter only
if (!isset($_SESSION['access']) || $_SESSION['access'] < MULTIHUNTER) {
    echo '<p style="color:#f87171;padding:16px;">Access denied: Administrator or Multihunter privileges required.</p>';
    return;
}

global $database, $generator;
$link = $GLOBALS['link'] ?? ($database->dblink ?? null);
if (!$link && class_exists('Database')) {
    global $database;
    if ($database && isset($database->dblink)) {
        $link = $database->dblink;
    }
}

$flashSuccess = '';
$flashError   = '';
$flashInfo    = '';

// ─────────────────────────────────────────────────────────────────────────────
// OASIS & FIELD DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────────
$OASIS_METAS = [
    1  => ['name' => 'Oasis Kayu +25%',              'res' => 'wood',  'crop' => 0,  'bonus_txt' => '+25% Kayu',                     'desc' => 'Meningkatkan produksi kayu sebesar 25%.', 'icon' => '🪵', 'badge' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0'],
    2  => ['name' => 'Oasis Kayu +25%',              'res' => 'wood',  'crop' => 0,  'bonus_txt' => '+25% Kayu',                     'desc' => 'Meningkatkan produksi kayu sebesar 25%.', 'icon' => '🪵', 'badge' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0'],
    3  => ['name' => 'Oasis Kayu +25% & Gandum +25%','res' => 'mixed', 'crop' => 25, 'bonus_txt' => '+25% Kayu & +25% Gandum',    'desc' => 'Meningkatkan produksi kayu 25% dan gandum 25%.', 'icon' => '🪵🌾', 'badge' => '#10b981', 'bg' => '#f0fdf4', 'border' => '#86efac'],
    4  => ['name' => 'Oasis Liat +25%',              'res' => 'clay',  'crop' => 0,  'bonus_txt' => '+25% Tanah Liat',               'desc' => 'Meningkatkan produksi tanah liat sebesar 25%.', 'icon' => '🧱', 'badge' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'],
    5  => ['name' => 'Oasis Liat +25%',              'res' => 'clay',  'crop' => 0,  'bonus_txt' => '+25% Tanah Liat',               'desc' => 'Meningkatkan produksi tanah liat sebesar 25%.', 'icon' => '🧱', 'badge' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'],
    6  => ['name' => 'Oasis Liat +25% & Gandum +25%','res' => 'mixed', 'crop' => 25, 'bonus_txt' => '+25% Tanah Liat & +25% Gandum', 'desc' => 'Meningkatkan produksi tanah liat 25% dan gandum 25%.', 'icon' => '🧱🌾', 'badge' => '#f59e0b', 'bg' => '#fffdf2', 'border' => '#fef08a'],
    7  => ['name' => 'Oasis Besi +25%',              'res' => 'iron',  'crop' => 0,  'bonus_txt' => '+25% Besi',                     'desc' => 'Meningkatkan produksi besi sebesar 25%.', 'icon' => '⛓️', 'badge' => '#475569', 'bg' => '#f8fafc', 'border' => '#cbd5e1'],
    8  => ['name' => 'Oasis Besi +25%',              'res' => 'iron',  'crop' => 0,  'bonus_txt' => '+25% Besi',                     'desc' => 'Meningkatkan produksi besi sebesar 25%.', 'icon' => '⛓️', 'badge' => '#475569', 'bg' => '#f8fafc', 'border' => '#cbd5e1'],
    9  => ['name' => 'Oasis Besi +25% & Gandum +25%','res' => 'mixed', 'crop' => 25, 'bonus_txt' => '+25% Besi & +25% Gandum',    'desc' => 'Meningkatkan produksi besi 25% dan gandum 25%.', 'icon' => '⛓️🌾', 'badge' => '#64748b', 'bg' => '#f1f5f9', 'border' => '#94a3b8'],
    10 => ['name' => 'Oasis Gandum +25%',            'res' => 'crop',  'crop' => 25, 'bonus_txt' => '+25% Gandum',                   'desc' => 'Meningkatkan produksi gandum sebesar 25%.', 'icon' => '🌾', 'badge' => '#ca8a04', 'bg' => '#fefce8', 'border' => '#fef08a'],
    11 => ['name' => 'Oasis Gandum +25%',            'res' => 'crop',  'crop' => 25, 'bonus_txt' => '+25% Gandum',                   'desc' => 'Meningkatkan produksi gandum sebesar 25%.', 'icon' => '🌾', 'badge' => '#ca8a04', 'bg' => '#fefce8', 'border' => '#fef08a'],
    12 => ['name' => 'Oasis Gandum +50% (Double Crop)','res' => 'crop', 'crop' => 50, 'bonus_txt' => '🌾⭐ +50% Gandum (Double Crop)', 'desc' => 'Meningkatkan produksi gandum hingga 50%! Tipe oasis paling dicari pemain.', 'icon' => '🌾⭐', 'badge' => '#b45309', 'bg' => '#fffbeb', 'border' => '#fcd34d'],
];

$FIELD_TYPES = [
    1  => '3-3-3-9 (9-Cropper)',
    2  => '3-4-5-6 (Standar Kayu/Liat)',
    3  => '4-4-4-6 (Standar Seimbang)',
    4  => '4-5-3-6 (Liat Fokus)',
    5  => '5-3-4-6 (Kayu Fokus)',
    6  => '1-1-1-15 (15-Cropper)',
    7  => '4-4-3-7 (Gandum 7)',
    8  => '3-4-4-7 (Gandum 7)',
    9  => '4-3-4-7 (Gandum 7)',
    10 => '3-5-4-6 (Liat 5)',
    11 => '4-3-5-6 (Besi 5)',
    12 => '5-4-3-6 (Kayu 5)',
];

$NATURE_UNITS = [
    31 => ['name' => 'Tikus (Rat)',          'icon' => 'gpack/travian_default/img/u/31.gif'],
    32 => ['name' => 'Laba-laba (Spider)',   'icon' => 'gpack/travian_default/img/u/32.gif'],
    33 => ['name' => 'Ular (Snake)',         'icon' => 'gpack/travian_default/img/u/33.gif'],
    34 => ['name' => 'Kelelawar (Bat)',      'icon' => 'gpack/travian_default/img/u/34.gif'],
    35 => ['name' => 'Babi Hutan (Boar)',    'icon' => 'gpack/travian_default/img/u/35.gif'],
    36 => ['name' => 'Serigala (Wolf)',      'icon' => 'gpack/travian_default/img/u/36.gif'],
    37 => ['name' => 'Beruang (Bear)',       'icon' => 'gpack/travian_default/img/u/37.gif'],
    38 => ['name' => 'Buaya (Crocodile)',    'icon' => 'gpack/travian_default/img/u/38.gif'],
    39 => ['name' => 'Harimau (Tiger)',      'icon' => 'gpack/travian_default/img/u/39.gif'],
    40 => ['name' => 'Gajah (Elephant)',     'icon' => 'gpack/travian_default/img/u/40.gif'],
];

// ─────────────────────────────────────────────────────────────────────────────
// TARGET COORDINATES RESOLUTION
// ─────────────────────────────────────────────────────────────────────────────
$targetX = isset($_REQUEST['x']) ? (int)$_REQUEST['x'] : 0;
$targetY = isset($_REQUEST['y']) ? (int)$_REQUEST['y'] : 0;
$searchWref = isset($_REQUEST['wref']) ? (int)$_REQUEST['wref'] : 0;

// Shortcut random picks
if (isset($_GET['rand_valley'])) {
    $rQ = mysqli_query($link, "SELECT x, y FROM " . TB_PREFIX . "wdata WHERE fieldtype > 0 AND occupied = 0 ORDER BY RAND() LIMIT 1");
    if ($rQ && ($rRow = mysqli_fetch_assoc($rQ))) {
        $targetX = (int)$rRow['x'];
        $targetY = (int)$rRow['y'];
        $searchWref = 0;
    }
} elseif (isset($_GET['rand_oasis'])) {
    $rQ = mysqli_query($link, "SELECT x, y FROM " . TB_PREFIX . "wdata WHERE oasistype > 0 ORDER BY RAND() LIMIT 1");
    if ($rQ && ($rRow = mysqli_fetch_assoc($rQ))) {
        $targetX = (int)$rRow['x'];
        $targetY = (int)$rRow['y'];
        $searchWref = 0;
    }
}

// Clamp coordinate values to server WORLD_MAX
$worldMax = defined('WORLD_MAX') ? WORLD_MAX : 100;
$targetX = max(-$worldMax, min($worldMax, $targetX));
$targetY = max(-$worldMax, min($worldMax, $targetY));

// ─────────────────────────────────────────────────────────────────────────────
// POST ACTION HANDLERS
// ─────────────────────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $wref   = (int)($_POST['wref'] ?? 0);
    $actX   = (int)($_POST['x'] ?? $targetX);
    $actY   = (int)($_POST['y'] ?? $targetY);

    if ($action === 'change_to_oasis') {
        $newOasisType = max(1, min(12, (int)($_POST['oasis_type'] ?? 12)));
        $troopMode    = $_POST['troop_mode'] ?? 'auto'; // 'auto', 'custom', 'empty'
        $initRes      = max(0, min(800000, (int)($_POST['init_res'] ?? 800)));
        $claimVid     = (int)($_POST['claim_village_id'] ?? 0);
        $confirmOver  = !empty($_POST['confirm_override_village']);

        // Inspect target tile before modifying
        $chkQ = mysqli_query($link, "SELECT w.*, v.name AS v_name, v.owner AS v_owner, u.username
            FROM " . TB_PREFIX . "wdata w
            LEFT JOIN " . TB_PREFIX . "vdata v ON w.id = v.wref
            LEFT JOIN " . TB_PREFIX . "users u ON v.owner = u.id
            WHERE w.id = {$wref} LIMIT 1");
        $chkTile = $chkQ ? mysqli_fetch_assoc($chkQ) : null;

        if (!$chkTile) {
            $flashError = "Petak dunia (wref #{$wref}) tidak ditemukan di database!";
        } elseif (!empty($chkTile['v_owner']) && $chkTile['v_owner'] > 2 && !$confirmOver) {
            $flashError = "⚠️ Petak ini adalah DESA AKTIF milik pemain <b>" . htmlspecialchars($chkTile['username']) . "</b> (Desa: <i>" . htmlspecialchars($chkTile['v_name']) . "</i>). Anda harus mencentang kotak persetujuan jika benar-benar ingin menimpa desa tersebut!";
        } else {
            mysqli_begin_transaction($link);
            try {
                // If it was an active village and confirmed to overwrite, clean up village tables
                if (!empty($chkTile['v_owner']) && $chkTile['v_owner'] > 2) {
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "vdata WHERE wref = {$wref}");
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "fdata WHERE vref = {$wref}");
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "bdata WHERE wid = {$wref}");
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "research WHERE vref = {$wref}");
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "training WHERE vref = {$wref}");
                    mysqli_query($link, "DELETE FROM " . TB_PREFIX . "market WHERE vref = {$wref}");
                }

                // Update wdata
                $imgName = 'o' . $newOasisType;
                mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET fieldtype = 0, oasistype = {$newOasisType}, image = '{$imgName}', occupied = 0 WHERE id = {$wref}");

                // Resolve owner & conquest
                $conquredId = 0;
                $ownerId    = 2; // Nature
                $oName      = 'Unoccupied Oasis';
                $loyalty    = 100.0;

                if ($claimVid > 0) {
                    $cvQ = mysqli_query($link, "SELECT wref, owner FROM " . TB_PREFIX . "vdata WHERE wref = {$claimVid} LIMIT 1");
                    if ($cvQ && ($cvRow = mysqli_fetch_assoc($cvQ))) {
                        $conquredId = (int)$cvRow['wref'];
                        $ownerId    = (int)$cvRow['owner'];
                        $oName      = 'Occupied Oasis';
                    }
                }

                // Update or Insert odata
                $timeNow = time();
                $randHigh = rand(0, 2);
                $sqlOdata = "INSERT INTO " . TB_PREFIX . "odata 
                    (`wref`, `type`, `conqured`, `wood`, `iron`, `clay`, `maxstore`, `crop`, `maxcrop`, `loyalty`, `owner`, `name`, `high`, `lastupdated`, `lastupdated2`)
                    VALUES 
                    ({$wref}, {$newOasisType}, {$conquredId}, {$initRes}, {$initRes}, {$initRes}, 800, {$initRes}, 800, {$loyalty}, {$ownerId}, '{$oName}', {$randHigh}, {$timeNow}, {$timeNow})
                    ON DUPLICATE KEY UPDATE
                    `type` = {$newOasisType},
                    `conqured` = {$conquredId},
                    `wood` = {$initRes},
                    `clay` = {$initRes},
                    `iron` = {$initRes},
                    `crop` = {$initRes},
                    `loyalty` = {$loyalty},
                    `owner` = {$ownerId},
                    `name` = '{$oName}',
                    `lastupdated` = {$timeNow},
                    `lastupdated2` = {$timeNow}";
                mysqli_query($link, $sqlOdata);

                // Ensure row in units exists
                mysqli_query($link, "INSERT IGNORE INTO " . TB_PREFIX . "units (`vref`) VALUES ({$wref})");

                // Handle nature garrison
                if ($conquredId > 0 || $troopMode === 'empty') {
                    mysqli_query($link, "UPDATE " . TB_PREFIX . "units SET u31=0, u32=0, u33=0, u34=0, u35=0, u36=0, u37=0, u38=0, u39=0, u40=0 WHERE vref = {$wref}");
                } elseif ($troopMode === 'custom') {
                    $uParts = [];
                    for ($uIdx = 31; $uIdx <= 40; $uIdx++) {
                        $uVal = max(0, min(99999, (int)($_POST['custom_u' . $uIdx] ?? 0)));
                        $uParts[] = "u{$uIdx} = {$uVal}";
                    }
                    mysqli_query($link, "UPDATE " . TB_PREFIX . "units SET " . implode(', ', $uParts) . " WHERE vref = {$wref}");
                }

                // Commit the main transaction
                mysqli_commit($link);

                // If auto troop spawn was requested and oasis is unoccupied, regenerate standard wild animals
                if ($conquredId === 0 && $troopMode === 'auto') {
                    if ($database && method_exists($database, 'regenerateOasisUnits')) {
                        $database->regenerateOasisUnits($wref);
                    }
                }

                // Clean or recalculate croppers table if exists
                $crCheck = @mysqli_query($link, "SHOW TABLES LIKE '" . TB_PREFIX . "croppers'");
                if ($crCheck && mysqli_num_rows($crCheck) > 0) {
                    @mysqli_query($link, "DELETE FROM " . TB_PREFIX . "croppers WHERE wref = {$wref}");
                    $nearQ = @mysqli_query($link, "SELECT wref, x, y FROM " . TB_PREFIX . "croppers WHERE ABS(x - {$actX}) <= 3 AND ABS(y - {$actY}) <= 3");
                    if ($nearQ && $database && method_exists($database, 'getBestOasisCropBonus')) {
                        while ($cRow = mysqli_fetch_assoc($nearQ)) {
                            $cBonus = $database->getBestOasisCropBonus($cRow['x'], $cRow['y']);
                            @mysqli_query($link, "UPDATE " . TB_PREFIX . "croppers SET best_oasis_bonus = {$cBonus} WHERE wref = " . (int)$cRow['wref']);
                        }
                    }
                }

                // Log admin action
                $adminId = (int)($_SESSION['id'] ?? 0);
                $logMsg = "Admin {$adminId} changed map tile ({$actX}|{$actY}) [#{$wref}] to Oasis Type {$newOasisType} ({$OASIS_METAS[$newOasisType]['bonus_txt']})";
                @mysqli_query($link, "INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) VALUES (0, {$adminId}, '" . mysqli_real_escape_string($link, $logMsg) . "', " . time() . ")");

                $flashSuccess = "🎉 Berhasil! Petak <b>({$actX}|{$actY})</b> [ID: {$wref}] telah sukses diubah menjadi <b>{$OASIS_METAS[$newOasisType]['name']}</b> ({$OASIS_METAS[$newOasisType]['bonus_txt']})!";
            } catch (\Throwable $e) {
                mysqli_rollback($link);
                $flashError = "Gagal mengubah field menjadi oasis: " . $e->getMessage();
            }
        }
    } elseif ($action === 'change_to_valley') {
        $newFieldType = max(1, min(12, (int)($_POST['field_type'] ?? 3)));
        mysqli_begin_transaction($link);
        try {
            $randImg = 't' . rand(0, 8);
            mysqli_query($link, "UPDATE " . TB_PREFIX . "wdata SET fieldtype = {$newFieldType}, oasistype = 0, image = '{$randImg}', occupied = 0 WHERE id = {$wref}");
            mysqli_query($link, "DELETE FROM " . TB_PREFIX . "odata WHERE wref = {$wref}");
            mysqli_query($link, "DELETE FROM " . TB_PREFIX . "units WHERE vref = {$wref}");
            mysqli_query($link, "DELETE FROM " . TB_PREFIX . "enforcement WHERE vref = {$wref}");
            mysqli_commit($link);

            // Recompute croppers table if relevant
            $crCheck = @mysqli_query($link, "SHOW TABLES LIKE '" . TB_PREFIX . "croppers'");
            if ($crCheck && mysqli_num_rows($crCheck) > 0 && $database && method_exists($database, 'getBestOasisCropBonus')) {
                if (in_array($newFieldType, [1, 6])) {
                    $bBonus = $database->getBestOasisCropBonus($actX, $actY);
                    @mysqli_query($link, "INSERT INTO " . TB_PREFIX . "croppers (`wref`, `x`, `y`, `fieldtype`, `best_oasis_bonus`) VALUES ({$wref}, {$actX}, {$actY}, {$newFieldType}, {$bBonus}) ON DUPLICATE KEY UPDATE `fieldtype` = {$newFieldType}, `best_oasis_bonus` = {$bBonus}");
                }
                $nearQ = @mysqli_query($link, "SELECT wref, x, y FROM " . TB_PREFIX . "croppers WHERE ABS(x - {$actX}) <= 3 AND ABS(y - {$actY}) <= 3");
                if ($nearQ) {
                    while ($cRow = mysqli_fetch_assoc($nearQ)) {
                        $cBonus = $database->getBestOasisCropBonus($cRow['x'], $cRow['y']);
                        @mysqli_query($link, "UPDATE " . TB_PREFIX . "croppers SET best_oasis_bonus = {$cBonus} WHERE wref = " . (int)$cRow['wref']);
                    }
                }
            }

            $adminId = (int)($_SESSION['id'] ?? 0);
            $logMsg = "Admin {$adminId} changed map tile ({$actX}|{$actY}) [#{$wref}] back to Valley Type {$newFieldType} ({$FIELD_TYPES[$newFieldType]})";
            @mysqli_query($link, "INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) VALUES (0, {$adminId}, '" . mysqli_real_escape_string($link, $logMsg) . "', " . time() . ")");

            $flashSuccess = "✅ Berhasil! Petak <b>({$actX}|{$actY})</b> [ID: {$wref}] telah diubah menjadi Lembah Terbengkalai <b>{$FIELD_TYPES[$newFieldType]}</b>.";
        } catch (\Throwable $e) {
            mysqli_rollback($link);
            $flashError = "Gagal mengubah oasis ke lembah: " . $e->getMessage();
        }
    } elseif ($action === 'respawn_nature') {
        if ($database && method_exists($database, 'regenerateOasisUnits')) {
            $database->regenerateOasisUnits($wref);
            $flashSuccess = "🐾 Pasukan satwa liar pada Oasis ({$actX}|{$actY}) [ID: {$wref}] berhasil di-regenerasi otomatis!";
        } else {
            $flashError = "Fungsi regenerateOasisUnits tidak tersedia pada engine!";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FETCH SELECTED TILE INFORMATION
// ─────────────────────────────────────────────────────────────────────────────
$whereClause = ($searchWref > 0) ? "w.id = " . (int)$searchWref : "w.x = {$targetX} AND w.y = {$targetY}";
$tileQ = mysqli_query($link, "SELECT 
    w.id AS wref, w.fieldtype, w.oasistype, w.occupied, w.image, w.x, w.y,
    o.type AS o_type, o.conqured, o.wood, o.iron, o.clay, o.crop, o.maxstore, o.maxcrop, o.loyalty, o.owner AS o_owner, o.name AS o_name, o.high,
    v.name AS v_name, v.capital, v.pop, v.owner AS v_owner,
    u.id AS user_id, u.username, u.tribe,
    u_oasis.username AS oasis_owner_username
FROM " . TB_PREFIX . "wdata w
LEFT JOIN " . TB_PREFIX . "odata o ON w.id = o.wref
LEFT JOIN " . TB_PREFIX . "vdata v ON w.id = v.wref
LEFT JOIN " . TB_PREFIX . "users u ON v.owner = u.id
LEFT JOIN " . TB_PREFIX . "users u_oasis ON o.owner = u_oasis.id
WHERE {$whereClause}
LIMIT 1");

$tile = $tileQ ? mysqli_fetch_assoc($tileQ) : null;
if ($tile) {
    $targetX = (int)$tile['x'];
    $targetY = (int)$tile['y'];
    $curWref = (int)$tile['wref'];
} else {
    $curWref = 0;
}

// Fetch nature garrison if row in units exists
$troops = [];
if ($curWref > 0) {
    $uQ = mysqli_query($link, "SELECT * FROM " . TB_PREFIX . "units WHERE vref = {$curWref} LIMIT 1");
    if ($uQ && ($uRow = mysqli_fetch_assoc($uQ))) {
        $troops = $uRow;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FETCH NEARBY OASIS RADAR (RADIUS 10)
// ─────────────────────────────────────────────────────────────────────────────
$radarRadius = 10;
$nearbyOases = [];
if ($tile) {
    $radarQ = mysqli_query($link, "SELECT 
        w.id AS wref, w.x, w.y, w.oasistype,
        o.type, o.conqured, o.owner, o.loyalty, o.name AS o_name,
        u.username
    FROM " . TB_PREFIX . "wdata w
    LEFT JOIN " . TB_PREFIX . "odata o ON w.id = o.wref
    LEFT JOIN " . TB_PREFIX . "users u ON o.owner = u.id
    WHERE w.oasistype > 0
      AND ABS(w.x - {$targetX}) <= {$radarRadius}
      AND ABS(w.y - {$targetY}) <= {$radarRadius}
    ORDER BY (POW(w.x - {$targetX}, 2) + POW(w.y - {$targetY}, 2)) ASC
    LIMIT 15");
    if ($radarQ) {
        while ($rRow = mysqli_fetch_assoc($radarQ)) {
            $nearbyOases[] = $rRow;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GLOBAL OASIS STATISTICS
// ─────────────────────────────────────────────────────────────────────────────
$statTotalOases = 0;
$statFreeOases  = 0;
$statOccOases   = 0;
$statDoubleCrop = 0;
$statQ = @mysqli_query($link, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN o.owner = 2 OR o.conqured = 0 THEN 1 ELSE 0 END) as free_count,
    SUM(CASE WHEN o.owner > 2 AND o.conqured > 0 THEN 1 ELSE 0 END) as occ_count,
    SUM(CASE WHEN w.oasistype = 12 THEN 1 ELSE 0 END) as crop50_count
FROM " . TB_PREFIX . "wdata w
LEFT JOIN " . TB_PREFIX . "odata o ON w.id = o.wref
WHERE w.oasistype > 0");
if ($statQ && ($statRow = mysqli_fetch_assoc($statQ))) {
    $statTotalOases = (int)$statRow['total'];
    $statFreeOases  = (int)$statRow['free_count'];
    $statOccOases   = (int)$statRow['occ_count'];
    $statDoubleCrop = (int)$statRow['crop50_count'];
}
?>

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; max-width: 1080px; margin: 0 auto; padding: 12px 16px;">

    <!-- HEADER TITLE -->
    <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 18px 24px; border-radius: 12px; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.25); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="margin: 0; font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    <span>🏝️</span> Oasis & Map Tile Manager
                </h2>
                <p style="margin: 5px 0 0; font-size: 13px; opacity: 0.92;">
                    Pusat kendali peta untuk merubah petak/field tertentu menjadi Oasis dengan jenis bonus, persediaan sumber daya, dan garnisun hewan yang diinginkan.
                </p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    🌴 Total Oasis: <b><?php echo number_format($statTotalOases); ?></b>
                </span>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    🌾 50% Crop: <b><?php echo number_format($statDoubleCrop); ?></b>
                </span>
            </div>
        </div>
    </div>

    <!-- FLASH NOTIFICATIONS -->
    <?php if ($flashSuccess): ?>
        <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; color: #065f46; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; color: #991b1b; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <?php echo $flashError; ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH & COORDINATES BAR -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <form method="get" action="admin.php" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin: 0;">
            <input type="hidden" name="p" value="oasisManager">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span style="font-weight: 700; font-size: 14px; color: #334155; display: flex; align-items: center; gap: 6px;">
                    📍 Koordinat Petak:
                </span>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-weight: 700; color: #64748b;">X:</label>
                    <input type="number" name="x" value="<?php echo $targetX; ?>" min="-<?php echo $worldMax; ?>" max="<?php echo $worldMax; ?>"
                           style="width: 75px; height: 36px; text-align: center; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; font-size: 14px;">
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label style="font-weight: 700; color: #64748b;">Y:</label>
                    <input type="number" name="y" value="<?php echo $targetY; ?>" min="-<?php echo $worldMax; ?>" max="<?php echo $worldMax; ?>"
                           style="width: 75px; height: 36px; text-align: center; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; font-size: 14px;">
                </div>
                <button type="submit" style="background: #059669; color: #fff; border: 0; height: 36px; padding: 0 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    🔍 Periksa Petak
                </button>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Shortcut Cepat:</span>
                <a href="admin.php?p=oasisManager&x=0&y=0" style="background: #f1f5f9; color: #334155; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                    🎯 Pusat (0|0)
                </a>
                <a href="admin.php?p=oasisManager&rand_valley=1" style="background: #ecfdf5; color: #047857; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                    🎲 Acak Lembah Kosong
                </a>
                <a href="admin.php?p=oasisManager&rand_oasis=1" style="background: #fef3c7; color: #92400e; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                    🎲 Acak Oasis
                </a>
            </div>
        </form>
    </div>

    <?php if (!$tile): ?>
        <div style="background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; color: #64748b;">
            <p style="font-size: 16px; margin: 0 0 10px;">Petak koordinat (<?php echo $targetX; ?>|<?php echo $targetY; ?>) tidak ditemukan pada ukuran peta saat ini (-<?php echo $worldMax; ?> s/d <?php echo $worldMax; ?>).</p>
            <p style="font-size: 13px; margin: 0;">Silakan masukkan koordinat yang valid lalu klik tombol Periksa Petak.</p>
        </div>
    <?php else: ?>

        <!-- TILE DETAIL CARD -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 22px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 20px; align-items: center;">
                
                <!-- VISUAL TILE SPRITE -->
                <div style="text-align: center;">
                    <div style="width: 90px; height: 90px; border-radius: 12px; border: 2px solid #cbd5e1; overflow: hidden; margin: 0 auto; background: #f8fafc; display: flex; align-items: center; justify-content: center; position: relative;">
                        <?php if ((int)$tile['oasistype'] > 0): ?>
                            <img src="gpack/travian_default/img/m/o<?php echo (int)$tile['oasistype']; ?>.gif" 
                                 alt="Oasis" style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                 onerror="this.onerror=null; this.src='gpack/travian_default/img/m/d00.gif';">
                        <?php else: ?>
                            <img src="gpack/travian_default/img/m/<?php echo htmlspecialchars($tile['image']); ?>.gif" 
                                 alt="Tile" style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                 onerror="this.onerror=null; this.src='gpack/travian_default/img/m/t1.gif';">
                        <?php endif; ?>
                    </div>
                    <span style="display: block; margin-top: 6px; font-size: 11px; font-weight: 700; color: #64748b;">
                        Tile #<?php echo $curWref; ?>
                    </span>
                </div>

                <!-- TILE SPECS & STATUS -->
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a;">
                            Petak (<?php echo $tile['x']; ?> | <?php echo $tile['y']; ?>)
                        </h3>
                        <div>
                            <?php if ((int)$tile['oasistype'] > 0): ?>
                                <span style="background: <?php echo $OASIS_METAS[(int)$tile['oasistype']]['bg']; ?>; color: <?php echo $OASIS_METAS[(int)$tile['oasistype']]['badge']; ?>; border: 1px solid <?php echo $OASIS_METAS[(int)$tile['oasistype']]['border']; ?>; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                    <?php echo $OASIS_METAS[(int)$tile['oasistype']]['icon']; ?> <?php echo $OASIS_METAS[(int)$tile['oasistype']]['name']; ?>
                                </span>
                            <?php elseif ((int)$tile['occupied'] > 0 && !empty($tile['v_owner'])): ?>
                                <span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                    🏰 Desa Pemain: <?php echo htmlspecialchars($tile['v_name']); ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                    🌿 Lembah Terbengkalai (<?php echo $FIELD_TYPES[(int)$tile['fieldtype']] ?? 'Field'; ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; font-size: 13px; color: #475569;">
                        <div>
                            <b>Tipe Saat Ini:</b>
                            <?php 
                            if ((int)$tile['oasistype'] > 0) {
                                echo "<span style='color:#059669;font-weight:700;'>Oasis (" . $OASIS_METAS[(int)$tile['oasistype']]['bonus_txt'] . ")</span>";
                            } elseif ((int)$tile['occupied'] > 0) {
                                echo "<span style='color:#dc2626;font-weight:700;'>Desa Pemain</span>";
                            } else {
                                echo "<span style='color:#0284c7;font-weight:700;'>Lembah Kosong (" . ($FIELD_TYPES[(int)$tile['fieldtype']] ?? 'Standar') . ")</span>";
                            }
                            ?>
                        </div>
                        <div>
                            <b>Status Kepemilikan:</b>
                            <?php 
                            if ((int)$tile['oasistype'] > 0) {
                                if ((int)$tile['o_owner'] > 2 && !empty($tile['oasis_owner_username'])) {
                                    echo "Dikuasai oleh <a href='admin.php?p=player&uid=" . (int)$tile['o_owner'] . "' style='color:#059669;font-weight:700;text-decoration:none;'>" . htmlspecialchars($tile['oasis_owner_username']) . "</a>";
                                } else {
                                    echo "<span style='color:#16a34a;font-weight:600;'>Oasis Liar / Bebas (Nature)</span>";
                                }
                            } elseif ((int)$tile['occupied'] > 0) {
                                echo "Milik <a href='admin.php?p=player&uid=" . (int)$tile['v_owner'] . "' style='color:#dc2626;font-weight:700;text-decoration:none;'>" . htmlspecialchars($tile['username'] ?? 'User') . "</a> (Populasi: " . (int)$tile['pop'] . ")";
                            } else {
                                echo "<span style='color:#64748b;'>Belum Berpenghuni (Siap Diubah)</span>";
                            }
                            ?>
                        </div>
                        <?php if ((int)$tile['oasistype'] > 0): ?>
                            <div>
                                <b>Loyalitas:</b> <?php echo round((float)$tile['loyalty'], 1); ?>%
                            </div>
                            <div>
                                <b>Stok Sumber Daya:</b> 
                                🪵 <?php echo number_format((int)$tile['wood']); ?> | 
                                🧱 <?php echo number_format((int)$tile['clay']); ?> | 
                                ⛓️ <?php echo number_format((int)$tile['iron']); ?> | 
                                🌾 <?php echo number_format((int)$tile['crop']); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <a href="../karte.php?x=<?php echo $tile['x']; ?>&y=<?php echo $tile['y']; ?>" target="_blank" style="color: #0284c7; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                🗺️ Buka di Peta Game &raquo;
                            </a>
                        </div>
                    </div>

                    <!-- CURRENT NATURE TROOPS PREVIEW IF OASIS -->
                    <?php if ((int)$tile['oasistype'] > 0 && !empty($troops)): ?>
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
                            <span style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">
                                🐾 Garnisun Satwa Liar Saat Ini:
                            </span>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <?php 
                                $hasTroops = false;
                                for ($ui = 31; $ui <= 40; $ui++): 
                                    $cnt = (int)($troops['u' . $ui] ?? 0);
                                    if ($cnt > 0) $hasTroops = true;
                                ?>
                                    <span style="font-size: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 8px; display: inline-flex; align-items: center; gap: 5px;">
                                        <img src="<?php echo $NATURE_UNITS[$ui]['icon']; ?>" alt="<?php echo $NATURE_UNITS[$ui]['name']; ?>" style="height: 16px;">
                                        <b><?php echo number_format($cnt); ?></b>
                                    </span>
                                <?php endfor; ?>
                                <?php if (!$hasTroops): ?>
                                    <span style="font-size: 12px; color: #94a3b8; font-style: italic;">Tidak ada satwa liar (0 Pasukan).</span>
                                <?php endif; ?>
                                
                                <form method="post" action="admin.php?p=oasisManager&x=<?php echo $targetX; ?>&y=<?php echo $targetY; ?>" style="display: inline; margin-left: 8px;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="respawn_nature">
                                    <input type="hidden" name="wref" value="<?php echo $curWref; ?>">
                                    <button type="submit" onclick="return confirm('Re-generate pasukan satwa liar untuk oasis ini?');"
                                            style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 8px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                        🔄 Respawn Hewan Standar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- MAIN ACTION ACCORDION / FORMS -->
        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px;">

            <!-- FORM 1: CONVERT TO DESIRED OASIS -->
            <div style="background: #ffffff; border: 2px solid #059669; border-radius: 12px; padding: 22px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.08);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                    <span style="font-size: 24px;">✨</span>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #065f46;">
                            Ubah Petak Ini Menjadi Oasis Yang Diinginkan
                        </h3>
                        <p style="margin: 2px 0 0; font-size: 13px; color: #64748b;">
                            Pilih jenis oasis target (1..12), persediaan awal, dan konfigurasi pasukan hewan penjaga alam.
                        </p>
                    </div>
                </div>

                <form method="post" action="admin.php?p=oasisManager&x=<?php echo $targetX; ?>&y=<?php echo $targetY; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_to_oasis">
                    <input type="hidden" name="wref" value="<?php echo $curWref; ?>">
                    <input type="hidden" name="x" value="<?php echo $targetX; ?>">
                    <input type="hidden" name="y" value="<?php echo $targetY; ?>">

                    <!-- 12 OASIS SELECTION GRID -->
                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 10px;">
                            1. Pilih Tipe Oasis:
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px;">
                            <?php foreach ($OASIS_METAS as $oId => $oMeta): ?>
                                <?php $isSelected = ((int)$tile['oasistype'] === $oId) || ((int)$tile['oasistype'] === 0 && $oId === 12); ?>
                                <label style="display: flex; align-items: flex-start; gap: 10px; border: 1.5px solid <?php echo $isSelected ? '#059669' : '#e2e8f0'; ?>; background: <?php echo $isSelected ? '#ecfdf5' : '#fff'; ?>; padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: all 0.15s ease;">
                                    <input type="radio" name="oasis_type" value="<?php echo $oId; ?>" <?php echo $isSelected ? 'checked' : ''; ?>
                                           style="margin-top: 3px; accent-color: #059669;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                            <span style="font-weight: 800; font-size: 13px; color: #0f172a;">
                                                [#<?php echo $oId; ?>] <?php echo $oMeta['name']; ?>
                                            </span>
                                            <span style="font-size: 14px;"><?php echo $oMeta['icon']; ?></span>
                                        </div>
                                        <div style="font-size: 12px; font-weight: 700; color: <?php echo $oMeta['badge']; ?>; margin: 2px 0;">
                                            <?php echo $oMeta['bonus_txt']; ?>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b; line-height: 1.3;">
                                            <?php echo $oMeta['desc']; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- NATURE TROOPS CONFIGURATION -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 18px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 8px;">
                            2. Konfigurasi Pasukan Hewan Penjaga (Nature Troops):
                        </label>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="troop_mode" value="auto" checked onchange="toggleCustomTroops(false)" style="accent-color: #059669;">
                                🤖 Otomatis Spawn Standar Travian (Direkomendasikan)
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="troop_mode" value="custom" onchange="toggleCustomTroops(true)" style="accent-color: #059669;">
                                ✍️ Kustom Jumlah Hewan
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="troop_mode" value="empty" onchange="toggleCustomTroops(false)" style="accent-color: #059669;">
                                🧹 Kosongkan Hewan (0 Pasukan / Bebas Farming)
                            </label>
                        </div>

                        <!-- CUSTOM TROOP INPUT FIELDS (HIDDEN BY DEFAULT) -->
                        <div id="customTroopsPanel" style="display: none; background: #fff; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px; margin-top: 10px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px;">
                                <?php for ($ui = 31; $ui <= 40; $ui++): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <img src="<?php echo $NATURE_UNITS[$ui]['icon']; ?>" alt="<?php echo $NATURE_UNITS[$ui]['name']; ?>" style="height: 18px;">
                                            <span style="font-size: 12px; font-weight: 600;"><?php echo explode(' ', $NATURE_UNITS[$ui]['name'])[0]; ?>:</span>
                                        </div>
                                        <input type="number" name="custom_u<?php echo $ui; ?>" value="<?php echo (int)($troops['u' . $ui] ?? 0); ?>" min="0" max="99999"
                                               style="width: 55px; height: 28px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: 700; font-size: 12px;">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RESOURCES & OPTIONAL CLAIMS -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 18px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                3. Stok Sumber Daya Awal per Resiko:
                            </label>
                            <input type="number" name="init_res" value="800" min="0" max="800000"
                                   style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 14px; box-sizing: border-box;">
                            <span style="font-size: 11px; color: #64748b;">Standar: 800 (Kayu, Liat, Besi, Gandum). Maksimal kapasitas gudang oasis: 800.</span>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                4. Klaim Langsung untuk Desa Pemain (Opsional):
                            </label>
                            <input type="number" name="claim_village_id" value="0" min="0"
                                   placeholder="Masukkan ID Desa (WREF) pemilik..."
                                   style="width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 14px; box-sizing: border-box;">
                            <span style="font-size: 11px; color: #64748b;">Biarkan 0 jika oasis dibiarkan liar (Nature).</span>
                        </div>
                    </div>

                    <!-- OVERRIDE CONFIRMATION IF ACTIVE VILLAGE -->
                    <?php if (!empty($tile['v_owner']) && $tile['v_owner'] > 2): ?>
                        <div style="background: #fef2f2; border: 1.5px solid #ef4444; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; color: #991b1b; font-size: 13px;">
                            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="confirm_override_village" value="1" style="margin-top: 3px; accent-color: #ef4444;">
                                <span>
                                    <b>⚠️ KONFIRMASI PENGHANCURAN DESA:</b> Petak ini saat ini adalah desa aktif milik pemain <u><?php echo htmlspecialchars($tile['username']); ?></u>. 
                                    Saya memahami bahwa merubah petak ini menjadi Oasis akan <b>menghapus seluruh bangunan dan populasi desa tersebut</b> dari peta.
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="submit" onclick="return confirm('Apakah Anda yakin ingin mengubah petak ini menjadi Oasis?');"
                                style="background: #059669; color: #fff; border: 0; padding: 12px 28px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(5,150,105,0.3);">
                            <span>✨</span> Simpan & Ubah Menjadi Oasis
                        </button>
                    </div>
                </form>
            </div>

            <!-- FORM 2: CONVERT OASIS BACK TO NORMAL VALLEY -->
            <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <span style="font-size: 22px;">🔄</span>
                    <div>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #334155;">
                            Ubah Kembali Menjadi Lembah Terbengkalai (Abandoned Valley)
                        </h3>
                        <p style="margin: 2px 0 0; font-size: 12px; color: #64748b;">
                            Gunakan opsi ini jika ingin merubah petak oasis menjadi tanah kosong / desa terbengkalai biasa (misal menjadi 15-Cropper atau 9-Cropper).
                        </p>
                    </div>
                </div>

                <form method="post" action="admin.php?p=oasisManager&x=<?php echo $targetX; ?>&y=<?php echo $targetY; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_to_valley">
                    <input type="hidden" name="wref" value="<?php echo $curWref; ?>">
                    <input type="hidden" name="x" value="<?php echo $targetX; ?>">
                    <input type="hidden" name="y" value="<?php echo $targetY; ?>">

                    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                        <label style="font-size: 13px; font-weight: 700; color: #475569;">
                            Pilih Tipe Lembah (Distribusi Sumber Daya):
                        </label>
                        <select name="field_type" style="height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 14px; background: #fff; min-width: 250px;">
                            <?php foreach ($FIELD_TYPES as $fId => $fName): ?>
                                <option value="<?php echo $fId; ?>" <?php echo ((int)$tile['fieldtype'] === $fId) ? 'selected' : ''; ?>>
                                    [#<?php echo $fId; ?>] <?php echo $fName; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" onclick="return confirm('Apakah Anda yakin ingin mengubah petak ini menjadi Lembah Terbengkalai biasa? (Seluruh data oasis akan dihapus)');"
                                style="background: #475569; color: #fff; border: 0; padding: 0 20px; height: 38px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;">
                            🔄 Ubah Menjadi Lembah Biasa
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- NEARBY OASIS RADAR -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span>📡</span> Radar Oasis Sekitar (Radius <?php echo $radarRadius; ?> Petak)
                </h3>
                <span style="font-size: 12px; color: #64748b;">
                    Ditemukan <b><?php echo count($nearbyOases); ?></b> oasis di sekitar (<?php echo $targetX; ?>|<?php echo $targetY; ?>)
                </span>
            </div>

            <?php if (empty($nearbyOases)): ?>
                <p style="font-size: 13px; color: #64748b; margin: 0;">Tidak ada oasis lain dalam radius <?php echo $radarRadius; ?> petak dari koordinat ini.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
                                <th style="padding: 10px 12px;">Koordinat</th>
                                <th style="padding: 10px 12px;">ID</th>
                                <th style="padding: 10px 12px;">Tipe Oasis</th>
                                <th style="padding: 10px 12px;">Bonus</th>
                                <th style="padding: 10px 12px;">Status</th>
                                <th style="padding: 10px 12px; text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nearbyOases as $nOas): ?>
                                <?php 
                                $isThis = ($nOas['wref'] == $curWref);
                                $oType  = (int)$nOas['oasistype'];
                                $meta   = $OASIS_METAS[$oType] ?? null;
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; background: <?php echo $isThis ? '#ecfdf5' : '#fff'; ?>;">
                                    <td style="padding: 8px 12px; font-weight: 700;">
                                        (<?php echo $nOas['x']; ?> | <?php echo $nOas['y']; ?>)
                                        <?php if ($isThis): ?>
                                            <span style="font-size: 10px; background: #059669; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Sedang Dipilih</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px 12px; color: #64748b;">#<?php echo $nOas['wref']; ?></td>
                                    <td style="padding: 8px 12px;">
                                        <?php echo $meta['icon'] ?? '🌴'; ?> <?php echo $meta['name'] ?? "Oasis {$oType}"; ?>
                                    </td>
                                    <td style="padding: 8px 12px; font-weight: 700; color: <?php echo $meta['badge'] ?? '#059669'; ?>;">
                                        <?php echo $meta['bonus_txt'] ?? ''; ?>
                                    </td>
                                    <td style="padding: 8px 12px;">
                                        <?php if ((int)$nOas['owner'] > 2 && !empty($nOas['username'])): ?>
                                            <span style="color: #dc2626; font-weight: 600;">Dikuasai: <?php echo htmlspecialchars($nOas['username']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #059669; font-weight: 600;">Liar / Nature</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px 12px; text-align: right;">
                                        <a href="admin.php?p=oasisManager&x=<?php echo $nOas['x']; ?>&y=<?php echo $nOas['y']; ?>"
                                           style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 700;">
                                            ✏️ Edit Petak
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script>
function toggleCustomTroops(show) {
    var p = document.getElementById('customTroopsPanel');
    if (p) {
        p.style.display = show ? 'block' : 'none';
    }
}
</script>
