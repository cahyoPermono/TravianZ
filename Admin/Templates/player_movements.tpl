<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : player_movements.tpl                                      ##
##  Type           : Admin Panel Frontend                                      ##
## --------------------------------------------------------------------------- ##
##  Developed by   : TravianZ Team & Antigravity                               ##
##  Purpose        : Real-time Troop Movement & Stationed Units Monitor        ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (!isset($_SESSION['access']) || $_SESSION['access'] < MULTIHUNTER) {
    return;
}

global $database, $generator;

// Handle Abort Movement action (Admin only)
$movMsg = '';
$movMsgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adm_act']) && $_POST['adm_act'] === 'abort_mov' && $_SESSION['access'] == ADMIN) {
    if (function_exists('csrf_verify') && !csrf_verify()) {
        $movMsg = 'Security token invalid (CSRF failure).';
        $movMsgType = 'error';
    } else {
        $abortMid = (int)($_POST['abort_mid'] ?? 0);
        require_once __DIR__ . '/../../GameEngine/BotAI.php';
        $res = BotAI::abortMovement($abortMid, true);
        if ($res['status'] === 'success') {
            $movMsg = '✅ ' . htmlspecialchars($res['message']);
            $movMsgType = 'success';
        } else {
            $movMsg = '❌ ' . htmlspecialchars($res['message']);
            $movMsgType = 'error';
        }
    }
}

// Determine Context: Village view or Player view
$isVillageContext = (isset($_GET['p']) && $_GET['p'] === 'village') && isset($village['wref']) && (int)$village['wref'] > 0;

if ($isVillageContext) {
    $contextWrefs = [(int)$village['wref']];
    $ownerUid = (int)$village['owner'];
    $ownerUser = $database->getUserArray($ownerUid, 1);
    $cardTitle = "🏹 Pergerakan & Status Militer Desa: " . htmlspecialchars($village['name']) . " (" . $coor['x'] . "|" . $coor['y'] . ")";
} else {
    $targetUid = isset($user['id']) ? (int)$user['id'] : (int)($_GET['uid'] ?? 0);
    $ownerUser = isset($user['id']) ? $user : $database->getUserArray($targetUid, 1);
    $pVillages = $database->getProfileVillages($targetUid);
    $contextWrefs = array_map('intval', array_column($pVillages, 'wref'));
    $cardTitle = "🏹 Pergerakan Pasukan & Posisi Militer Pemain: " . htmlspecialchars($ownerUser['username'] ?? 'User');
}

if (empty($contextWrefs)) {
    return;
}

$wrefsIn = implode(',', $contextWrefs);

// 1. Fetch In-Flight Movements involving these villages
$sqlMovements = "
    SELECT m.moveid, m.sort_type, m.from, m.to, m.ref, m.starttime, m.endtime,
           a.attack_type, a.ctar1, a.ctar2,
           a.t1, a.t2, a.t3, a.t4, a.t5, a.t6, a.t7, a.t8, a.t9, a.t10, a.t11,
           v1.name AS from_vname, v1.owner AS from_uid, u1.username AS from_uname, u1.tribe AS from_tribe,
           w1.x AS from_x, w1.y AS from_y,
           v2.name AS to_vname, v2.owner AS to_uid, u2.username AS to_uname, u2.tribe AS to_tribe,
           w2.x AS to_x, w2.y AS to_y,
           o.conqured AS o_conqured, o.owner AS o_owner, u_o.username AS o_uname
    FROM " . TB_PREFIX . "movement m
    LEFT JOIN " . TB_PREFIX . "attacks a ON (m.ref = a.id AND m.sort_type IN (3, 4, 8))
    LEFT JOIN " . TB_PREFIX . "vdata v1 ON m.from = v1.wref
    LEFT JOIN " . TB_PREFIX . "wdata w1 ON m.from = w1.id
    LEFT JOIN " . TB_PREFIX . "users u1 ON v1.owner = u1.id
    LEFT JOIN " . TB_PREFIX . "vdata v2 ON m.to = v2.wref
    LEFT JOIN " . TB_PREFIX . "wdata w2 ON m.to = w2.id
    LEFT JOIN " . TB_PREFIX . "users u2 ON v2.owner = u2.id
    LEFT JOIN " . TB_PREFIX . "odata o ON m.to = o.wref
    LEFT JOIN " . TB_PREFIX . "users u_o ON (o.conqured = u_o.id OR (o.conqured = 0 AND o.owner = u_o.id))
    WHERE m.proc = 0
      AND (m.from IN ($wrefsIn) OR m.to IN ($wrefsIn))
    ORDER BY m.endtime ASC
";
$activeMovements = $database->query_return($sqlMovements);

// 2. Fetch Stationed Reinforcements (Troops stationed OUTSIDE own villages)
$sqlStationedOut = "
    SELECT e.*,
           v1.name AS from_vname, v1.owner AS from_uid, u1.tribe AS from_tribe,
           w1.x AS from_x, w1.y AS from_y,
           v2.name AS to_vname, v2.owner AS to_uid, u2.username AS to_uname,
           w2.x AS to_x, w2.y AS to_y,
           o.conqured AS o_conqured, u_o.username AS o_uname
    FROM " . TB_PREFIX . "enforcement e
    LEFT JOIN " . TB_PREFIX . "vdata v1 ON e.from = v1.wref
    LEFT JOIN " . TB_PREFIX . "wdata w1 ON e.from = w1.id
    LEFT JOIN " . TB_PREFIX . "users u1 ON v1.owner = u1.id
    LEFT JOIN " . TB_PREFIX . "vdata v2 ON e.vref = v2.wref
    LEFT JOIN " . TB_PREFIX . "wdata w2 ON e.vref = w2.id
    LEFT JOIN " . TB_PREFIX . "users u2 ON v2.owner = u2.id
    LEFT JOIN " . TB_PREFIX . "odata o ON e.vref = o.wref
    LEFT JOIN " . TB_PREFIX . "users u_o ON (o.conqured = u_o.id OR (o.conqured = 0 AND o.owner = u_o.id))
    WHERE e.from IN ($wrefsIn) AND e.vref NOT IN ($wrefsIn)
";
$stationedOut = $database->query_return($sqlStationedOut);

// 3. Fetch Guest Reinforcements (Other players' troops stationed INSIDE this village/player)
$sqlStationedIn = "
    SELECT e.*,
           v1.name AS from_vname, v1.owner AS from_uid, u1.username AS from_uname, u1.tribe AS from_tribe,
           w1.x AS from_x, w1.y AS from_y,
           v2.name AS to_vname, w2.x AS to_x, w2.y AS to_y
    FROM " . TB_PREFIX . "enforcement e
    LEFT JOIN " . TB_PREFIX . "vdata v1 ON e.from = v1.wref
    LEFT JOIN " . TB_PREFIX . "wdata w1 ON e.from = w1.id
    LEFT JOIN " . TB_PREFIX . "users u1 ON v1.owner = u1.id
    LEFT JOIN " . TB_PREFIX . "vdata v2 ON e.vref = v2.wref
    LEFT JOIN " . TB_PREFIX . "wdata w2 ON e.vref = w2.id
    WHERE e.vref IN ($wrefsIn) AND e.from NOT IN ($wrefsIn)
";
$stationedIn = $database->query_return($sqlStationedIn);
?>

<style>
.adm-mov-box {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    margin: 14px 0;
    overflow: hidden;
}
.adm-mov-head {
    background: linear-gradient(135deg, #0284c7, #0ea5e9);
    color: #fff;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.adm-mov-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.adm-mov-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .4px;
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.adm-mov-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #1e293b;
}
.adm-mov-table tr:hover td {
    background: #f8fafc;
}
.badge-mov {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.badge-atk { background: #fee2e2; color: #b91c1c; }
.badge-raid { background: #ffedd5; color: #c2410c; }
.badge-reinf { background: #dcfce7; color: #15803d; }
.badge-scout { background: #ede9fe; color: #6d28d9; }
.badge-settle { background: #e0f2fe; color: #0369a1; }
.badge-return { background: #f1f5f9; color: #475569; }
.badge-hero { background: #fef3c7; color: #b45309; }
.badge-trade { background: #e0e7ff; color: #4338ca; }

.troop-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.troop-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
}
.troop-chip img {
    width: 16px;
    height: 16px;
    display: inline-block;
}
.countdown-box {
    display: inline-flex;
    flex-direction: column;
}
.countdown-val {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    font-weight: 700;
    color: #d97706;
}
.countdown-sub {
    font-size: 10.5px;
    color: #64748b;
}
.btn-abort {
    background: #ef4444;
    color: #fff;
    border: 0;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 10.5px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.15s;
}
.btn-abort:hover {
    background: #dc2626;
}
.adm-subhead {
    background: #f1f5f9;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.adm-empty-box {
    padding: 14px;
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    font-style: italic;
}
</style>

<div class="adm-mov-box">
    <div class="adm-mov-head">
        <span><?php echo $cardTitle; ?></span>
        <span style="font-size:11px; font-weight:normal; opacity:0.9;">
            Live Tracking Real-time
        </span>
    </div>

    <?php if (!empty($movMsg)): ?>
        <div style="padding:10px 14px; background:<?php echo $movMsgType === 'success' ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo $movMsgType === 'success' ? '#166534' : '#991b1b'; ?>; font-size:12px; font-weight:600; border-bottom:1px solid #e2e8f0;">
            <?php echo $movMsg; ?>
        </div>
    <?php endif; ?>

    <!-- 1. PASUKAN SEDANG DI JALAN (IN-FLIGHT MOVEMENTS) -->
    <div class="adm-subhead">
        <span>⚔️ Pasukan Sedang di Jalan (In-Flight Movement): <?php echo count($activeMovements); ?> Pergerakan</span>
    </div>

    <?php if (!empty($activeMovements)): ?>
        <table class="adm-mov-table">
            <thead>
                <tr>
                    <th style="width:130px;">Tipe & Aksi</th>
                    <th>Asal Pasukan</th>
                    <th>Tujuan & Target</th>
                    <th>Rincian Pasukan</th>
                    <th style="width:150px;">Sisa Waktu & Tiba</th>
                    <?php if ($_SESSION['access'] == ADMIN): ?>
                        <th style="width:65px; text-align:center;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeMovements as $mv): ?>
                    <?php
                        $isOutgoing = in_array((int)$mv['from'], $contextWrefs, true);
                        $isReturning = ((int)$mv['sort_type'] === 4) && in_array((int)$mv['to'], $contextWrefs, true);
                        $isIncoming = in_array((int)$mv['to'], $contextWrefs, true) && !$isOutgoing && !$isReturning;

                        $sortType = (int)$mv['sort_type'];
                        $atkType = (int)($mv['attack_type'] ?? 0);

                        // Movement badge determination
                        $badgeClass = 'badge-return';
                        $badgeLabel = 'Pergerakan';
                        if ($sortType === 3) {
                            if ($atkType === 3) {
                                $badgeClass = 'badge-atk';
                                $badgeLabel = $isIncoming ? '⚠️ Diserang (Normal)' : '⚔️ Serangan Penuh';
                            } elseif ($atkType === 4) {
                                $badgeClass = 'badge-raid';
                                $badgeLabel = $isIncoming ? '⚠️ Diraid (Penjarahan)' : '🏹 Penjarahan (Raid)';
                            } elseif ($atkType === 2) {
                                $badgeClass = 'badge-reinf';
                                $badgeLabel = $isIncoming ? '🛡️ Bantuan Datang' : '🛡️ Kirim Bantuan';
                            } elseif ($atkType === 1) {
                                $badgeClass = 'badge-scout';
                                $badgeLabel = $isIncoming ? '🔍 Diintai (Scout)' : '🔍 Mengintai (Scout)';
                            }
                        } elseif ($sortType === 4) {
                            $badgeClass = 'badge-return';
                            $badgeLabel = '↩️ Pasukan Pulang';
                        } elseif ($sortType === 5) {
                            $badgeClass = 'badge-settle';
                            $badgeLabel = '🏕️ Bikin Desa Baru';
                        } elseif (in_array($sortType, [9, 10, 20, 21], true)) {
                            $badgeClass = 'badge-hero';
                            $badgeLabel = in_array($sortType, [9, 20], true) ? '🗺️ Hero Berpetualang' : '↩️ Hero Pulang Petualang';
                        } elseif (in_array($sortType, [0, 1, 2], true)) {
                            $badgeClass = 'badge-trade';
                            $badgeLabel = '📦 Pedagang Sumber Daya';
                        }

                        // Coordinates and Names
                        $fromName = !empty($mv['from_vname']) ? htmlspecialchars($mv['from_vname']) : 'Desa Asal';
                        $fromUser = !empty($mv['from_uname']) ? htmlspecialchars($mv['from_uname']) : 'System';
                        $fromCoords = '(' . ($mv['from_x'] ?? 0) . '|' . ($mv['from_y'] ?? 0) . ')';

                        if (!empty($mv['to_vname'])) {
                            $toName = htmlspecialchars($mv['to_vname']);
                            $toUser = !empty($mv['to_uname']) ? htmlspecialchars($mv['to_uname']) : 'System';
                        } elseif (!empty($mv['o_conqured']) || !empty($mv['o_owner'])) {
                            $toName = 'Oasis (' . ($mv['to_x'] ?? 0) . '|' . ($mv['to_y'] ?? 0) . ')';
                            $toUser = !empty($mv['o_uname']) ? htmlspecialchars($mv['o_uname']) : 'Alam Liar (Nature)';
                        } else {
                            $toName = 'Lahan Bebas (' . ($mv['to_x'] ?? 0) . '|' . ($mv['to_y'] ?? 0) . ')';
                            $toUser = '-';
                        }
                        $toCoords = '(' . ($mv['to_x'] ?? 0) . '|' . ($mv['to_y'] ?? 0) . ')';

                        // Troop Breakdown
                        $senderTribe = (int)($mv['from_tribe'] ?? 1);
                        if ($senderTribe < 1 || $senderTribe > 10) $senderTribe = 1;
                        $unitStart = ($senderTribe - 1) * 10 + 1;

                        $troopsList = [];
                        if (in_array($sortType, [3, 4, 8], true)) {
                            for ($idx = 1; $idx <= 10; $idx++) {
                                $cnt = (int)($mv['t' . $idx] ?? 0);
                                if ($cnt > 0) {
                                    $uId = $unitStart + ($idx - 1);
                                    $troopsList[] = [
                                        'id' => $uId,
                                        'cnt' => $cnt,
                                        'is_hero' => false
                                    ];
                                }
                            }
                            if (!empty($mv['t11']) && (int)$mv['t11'] > 0) {
                                $troopsList[] = [
                                    'id' => 0,
                                    'cnt' => (int)$mv['t11'],
                                    'is_hero' => true
                                ];
                            }
                        } elseif ($sortType === 5) {
                            $settlerU = $senderTribe * 10;
                            $troopsList[] = [
                                'id' => $settlerU,
                                'cnt' => 3,
                                'is_hero' => false
                            ];
                        } elseif (in_array($sortType, [9, 10, 20, 21], true)) {
                            $troopsList[] = [
                                'id' => 0,
                                'cnt' => 1,
                                'is_hero' => true
                            ];
                        }

                        // Time Calculations
                        $now = time();
                        $endTime = (int)$mv['endtime'];
                        $remSec = max(0, $endTime - $now);
                        $remFormatted = sprintf('%02d:%02d:%02d', floor($remSec / 3600), floor(($remSec % 3600) / 60), $remSec % 60);
                        $arriveFormatted = date('d/m H:i:s', $endTime);
                    ?>
                    <tr>
                        <td>
                            <span class="badge-mov <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                            <div style="font-size:10px; color:#94a3b8; margin-top:2px;">#<?php echo $mv['moveid']; ?></div>
                        </td>
                        <td>
                            <a href="admin.php?p=village&did=<?php echo $mv['from']; ?>" style="color:#0284c7; font-weight:600;">
                                <?php echo $fromName; ?>
                            </a>
                            <span style="color:#64748b; font-size:11px;"><?php echo $fromCoords; ?></span>
                            <div style="font-size:11px; color:#475569;">
                                Milik: <a href="admin.php?p=player&uid=<?php echo $mv['from_uid']; ?>" style="color:#2563eb;"><?php echo $fromUser; ?></a>
                            </div>
                        </td>
                        <td>
                            <a href="admin.php?p=village&did=<?php echo $mv['to']; ?>" style="color:#0284c7; font-weight:600;">
                                <?php echo $toName; ?>
                            </a>
                            <span style="color:#64748b; font-size:11px;"><?php echo $toCoords; ?></span>
                            <div style="font-size:11px; color:#475569;">
                                Target: <?php echo !empty($mv['to_uid']) ? '<a href="admin.php?p=player&uid=' . $mv['to_uid'] . '" style="color:#2563eb;">' . $toUser . '</a>' : $toUser; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($troopsList)): ?>
                                <div class="troop-pill-list">
                                    <?php foreach ($troopsList as $tr): ?>
                                        <span class="troop-chip">
                                            <?php if ($tr['is_hero']): ?>
                                                <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="#facc15" stroke="#eab308" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                                <span>Hero (<?php echo $tr['cnt']; ?>)</span>
                                            <?php else: ?>
                                                <img src="../img/un/u/<?php echo $tr['id']; ?>.gif" alt="Unit <?php echo $tr['id']; ?>">
                                                <span><?php echo number_format($tr['cnt']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px;">(Pedagang / Tidak bawa pasukan)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="countdown-box">
                                <span class="countdown-val adm-timer" data-end="<?php echo $endTime; ?>"><?php echo $remFormatted; ?></span>
                                <span class="countdown-sub">Tiba: <?php echo $arriveFormatted; ?> WIB</span>
                            </div>
                        </td>
                        <?php if ($_SESSION['access'] == ADMIN): ?>
                            <td style="text-align:center;">
                                <form method="post" style="margin:0;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pergerakan ini? Pasukan akan dikembalikan ke desa asal.');">
                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                    <input type="hidden" name="adm_act" value="abort_mov">
                                    <input type="hidden" name="abort_mid" value="<?php echo $mv['moveid']; ?>">
                                    <button type="submit" class="btn-abort" title="Tarik/Batalkan Gerakan">Tarik</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="adm-empty-box">
            🍃 Tidak ada pergerakan pasukan di jalan saat ini (Semua pasukan sedang siaga di desa).
        </div>
    <?php endif; ?>

    <!-- 2. PASUKAN BANTUAN DI LUAR DESA (STATIONED REINFORCEMENTS OUTSIDE) -->
    <div class="adm-subhead" style="border-top:1px solid #e2e8f0;">
        <span>🛡️ Pasukan Menjaga di Luar Desa (Bala Bantuan / Garnisun): <?php echo count($stationedOut); ?> Lokasi</span>
    </div>

    <?php if (!empty($stationedOut)): ?>
        <table class="adm-mov-table">
            <thead>
                <tr>
                    <th>Desa Asal Pasukan</th>
                    <th>Lokasi Bertugas (Menjaga)</th>
                    <th>Rincian Pasukan yang Standby di Sana</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stationedOut as $st): ?>
                    <?php
                        $stTroops = [];
                        for ($u = 1; $u <= 100; $u++) {
                            if (!empty($st['u' . $u]) && (int)$st['u' . $u] > 0) {
                                $stTroops[] = ['id' => $u, 'cnt' => (int)$st['u' . $u], 'is_hero' => false];
                            }
                        }
                        if (!empty($st['hero']) && (int)$st['hero'] > 0) {
                            $stTroops[] = ['id' => 0, 'cnt' => (int)$st['hero'], 'is_hero' => true];
                        }
                        if (empty($stTroops)) continue;

                        $targetName = !empty($st['to_vname']) ? htmlspecialchars($st['to_vname']) : 'Oasis (' . ($st['to_x'] ?? 0) . '|' . ($st['to_y'] ?? 0) . ')';
                        $targetUser = !empty($st['to_uname']) ? htmlspecialchars($st['to_uname']) : (!empty($st['o_uname']) ? htmlspecialchars($st['o_uname']) : 'Alam / Sekutu');
                    ?>
                    <tr>
                        <td>
                            <a href="admin.php?p=village&did=<?php echo $st['from']; ?>" style="color:#0284c7; font-weight:600;">
                                <?php echo htmlspecialchars($st['from_vname']); ?>
                            </a>
                            <span style="color:#64748b; font-size:11px;">(<?php echo $st['from_x'] . '|' . $st['from_y']; ?>)</span>
                        </td>
                        <td>
                            <a href="admin.php?p=village&did=<?php echo $st['vref']; ?>" style="color:#0284c7; font-weight:600;">
                                <?php echo $targetName; ?>
                            </a>
                            <span style="color:#64748b; font-size:11px;">(<?php echo $st['to_x'] . '|' . $st['to_y']; ?>)</span>
                            <div style="font-size:11px; color:#475569;">
                                Milik: <?php echo !empty($st['to_uid']) ? '<a href="admin.php?p=player&uid=' . $st['to_uid'] . '" style="color:#2563eb;">' . $targetUser . '</a>' : $targetUser; ?>
                            </div>
                        </td>
                        <td>
                            <div class="troop-pill-list">
                                <?php foreach ($stTroops as $tr): ?>
                                    <span class="troop-chip">
                                        <?php if ($tr['is_hero']): ?>
                                            <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="#facc15" stroke="#eab308" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            <span>Hero</span>
                                        <?php else: ?>
                                            <img src="../img/un/u/<?php echo $tr['id']; ?>.gif" alt="Unit <?php echo $tr['id']; ?>">
                                            <span><?php echo number_format($tr['cnt']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="adm-empty-box">
            🛡️ Tidak ada pasukan yang sedang bertugas menjaga di luar desa.
        </div>
    <?php endif; ?>

    <!-- 3. PASUKAN TAMU DARI LUAR YANG MENJAGA DI SINI (GUEST REINFORCEMENTS) -->
    <?php if (!empty($stationedIn)): ?>
        <div class="adm-subhead" style="border-top:1px solid #e2e8f0;">
            <span>🤝 Pasukan Sekutu / Tamu yang Menjaga Desa Ini: <?php echo count($stationedIn); ?> Pengirim</span>
        </div>
        <table class="adm-mov-table">
            <thead>
                <tr>
                    <th>Pengirim & Desa Asal</th>
                    <th>Desa yang Dijaga</th>
                    <th>Rincian Pasukan Bantuan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stationedIn as $stIn): ?>
                    <?php
                        $inTroops = [];
                        for ($u = 1; $u <= 100; $u++) {
                            if (!empty($stIn['u' . $u]) && (int)$stIn['u' . $u] > 0) {
                                $inTroops[] = ['id' => $u, 'cnt' => (int)$stIn['u' . $u], 'is_hero' => false];
                            }
                        }
                        if (!empty($stIn['hero']) && (int)$stIn['hero'] > 0) {
                            $inTroops[] = ['id' => 0, 'cnt' => (int)$stIn['hero'], 'is_hero' => true];
                        }
                        if (empty($inTroops)) continue;
                    ?>
                    <tr>
                        <td>
                            <a href="admin.php?p=player&uid=<?php echo $stIn['from_uid']; ?>" style="color:#2563eb; font-weight:600;">
                                <?php echo htmlspecialchars($stIn['from_uname']); ?>
                            </a>
                            <div style="font-size:11px; color:#475569;">
                                Desa: <a href="admin.php?p=village&did=<?php echo $stIn['from']; ?>"><?php echo htmlspecialchars($stIn['from_vname']); ?></a> (<?php echo $stIn['from_x'] . '|' . $stIn['from_y']; ?>)
                            </div>
                        </td>
                        <td>
                            <a href="admin.php?p=village&did=<?php echo $stIn['vref']; ?>" style="color:#0284c7; font-weight:600;">
                                <?php echo htmlspecialchars($stIn['to_vname']); ?>
                            </a>
                            <span style="color:#64748b; font-size:11px;">(<?php echo $stIn['to_x'] . '|' . $stIn['to_y']; ?>)</span>
                        </td>
                        <td>
                            <div class="troop-pill-list">
                                <?php foreach ($inTroops as $tr): ?>
                                    <span class="troop-chip">
                                        <?php if ($tr['is_hero']): ?>
                                            <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="#facc15" stroke="#eab308" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            <span>Hero</span>
                                        <?php else: ?>
                                            <img src="../img/un/u/<?php echo $tr['id']; ?>.gif" alt="Unit <?php echo $tr['id']; ?>">
                                            <span><?php echo number_format($tr['cnt']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function() {
    function updateAdminTimers() {
        var now = Math.floor(Date.now() / 1000);
        var timers = document.querySelectorAll('.adm-timer');
        timers.forEach(function(el) {
            var end = parseInt(el.getAttribute('data-end'), 10);
            if (!end) return;
            var rem = end - now;
            if (rem <= 0) {
                el.innerHTML = '<span style="color:#10b981; font-weight:bold;">Tiba di tujuan!</span>';
            } else {
                var h = Math.floor(rem / 3600);
                var m = Math.floor((rem % 3600) / 60);
                var s = rem % 60;
                var hStr = (h < 10 ? '0' : '') + h;
                var mStr = (m < 10 ? '0' : '') + m;
                var sStr = (s < 10 ? '0' : '') + s;
                el.textContent = hStr + ':' + mStr + ':' + sStr;
            }
        });
    }
    setInterval(updateAdminTimers, 1000);
})();
</script>
