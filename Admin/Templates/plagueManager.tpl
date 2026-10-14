<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/plagueManager.tpl                         ##
##  Type           : Admin Panel Frontend & Engine for Village Plague System   ##
##  Purpose        : Web-based GUI to inspect infected villages, infect any     ##
##                   village, cure outbreaks, and manage quarantine status     ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

// Access control: Admin or Multihunter only
if (!isset($_SESSION['access']) || $_SESSION['access'] < MULTIHUNTER) {
    echo '<p style="color:#f87171;padding:16px;">Access denied: Administrator or Multihunter privileges required.</p>';
    return;
}

$repoRoot = dirname(__DIR__, 2);
if (!file_exists($repoRoot . '/GameEngine/Plague.php')) {
    $candidates = [
        dirname(__DIR__, 2),
        dirname(__DIR__),
        __DIR__ . '/../..',
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/travian'
    ];
    foreach ($candidates as $c) {
        if (!empty($c) && file_exists($c . '/GameEngine/Plague.php')) {
            $repoRoot = $c;
            break;
        }
    }
}

if (!file_exists($repoRoot . '/GameEngine/Plague.php')) {
    echo '<div style="background:#450a0a; border:2px solid #ef4444; color:#fecaca; padding:24px; border-radius:12px; margin:20px; font-family:sans-serif;">';
    echo '<h3 style="color:#f87171; margin-top:0; font-size:1.3rem;">⚠️ File Backend GameEngine/Plague.php Belum Terpasang!</h3>';
    echo '<p style="font-size:1rem; margin:10px 0;">Halaman ini membutuhkan file <code>GameEngine/Plague.php</code>, namun file tersebut tidak ditemukan di direktori proyek server.</p>';
    echo '<p style="font-size:0.9rem; color:#fca5a5;"><strong>Solusi:</strong> Unggah file <code>GameEngine/Plague.php</code> dari file arsip pembaruan <code>travianz_update_hero_bot_ormas.zip</code> ke folder <code>GameEngine/</code> di server hosting Anda.</p>';
    echo '</div>';
    return;
}

require_once $repoRoot . '/GameEngine/Plague.php';
try {
    Plague::ensureTable();
} catch (\Throwable $t) {
    // ignore or handle table creation errors gracefully
}

global $database;
$db = null;
if (isset($database->dblink) && $database->dblink instanceof \mysqli) {
    $db = $database->dblink;
} elseif (isset($GLOBALS['link']) && $GLOBALS['link'] instanceof \mysqli) {
    $db = $GLOBALS['link'];
} elseif (isset($database->connection) && $database->connection instanceof \mysqli) {
    $db = $database->connection;
}

$flashSuccess = '';
$flashError   = '';

// ─── POST ACTION HANDLERS ──────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'infect_village') {
        $targetMode = $_POST['target_mode'] ?? 'dropdown';
        $wref = 0;

        if ($targetMode === 'dropdown') {
            $wref = (int)($_POST['target_dropdown'] ?? 0);
        } elseif ($targetMode === 'coords') {
            $tx = isset($_POST['target_x']) ? (int)$_POST['target_x'] : 0;
            $ty = isset($_POST['target_y']) ? (int)$_POST['target_y'] : 0;
            $wref = (int)$database->getVilWref($tx, $ty);
        } elseif ($targetMode === 'manual') {
            $wref = (int)($_POST['target_wref_manual'] ?? 0);
        }

        $plagueType = (int)($_POST['plague_type'] ?? Plague::PLAGUE_CHOLERA);
        $severity   = max(1, min(5, (int)($_POST['severity'] ?? 1)));
        $hours      = max(0.5, (float)($_POST['duration_hours'] ?? 4));
        $duration   = (int)round($hours * 3600);
        $quarantine = !empty($_POST['set_quarantine']) ? 1 : 0;

        if ($wref <= 0 || !$database->checkVilExist($wref)) {
            $flashError = 'Desa sasaran tidak ditemukan atau koordinat tidak valid!';
        } elseif ($plagueType < 1 || $plagueType > 3) {
            $flashError = 'Jenis wabah yang dipilih tidak valid!';
        } else {
            $vData = $database->getVillage($wref);
            $vName = htmlspecialchars($vData['name'] ?? ('Village #' . $wref));
            $uName = htmlspecialchars($database->getUserField($vData['owner'], 'username', 0) ?? 'Player');
            $coor  = $database->getCoor($wref);
            $vX    = (int)($coor['x'] ?? 0);
            $vY    = (int)($coor['y'] ?? 0);

            Plague::infectVillage($wref, $plagueType, $severity, $duration);

            if ($quarantine) {
                $cur = Plague::getVillagePlague($wref);
                if (empty($cur['quarantine'])) {
                    Plague::toggleQuarantine($wref);
                }
            }

            $meta = Plague::getPlagueInfo($plagueType);
            $flashSuccess = "Berhasil menebarkan <b>{$meta['icon']} {$meta['name']}</b> (Tingkat {$severity}) ke desa <b>{$vName}</b> ({$vX}|{$vY}) milik <b>{$uName}</b> selama {$hours} jam!" . ($quarantine ? ' <i>(Status Karantina Langsung Diaktifkan)</i>' : '');
        }
    } elseif ($action === 'cure_village') {
        $vref = (int)($_POST['vref'] ?? 0);
        if ($vref > 0) {
            $vData = $database->getVillage($vref);
            $vName = htmlspecialchars($vData['name'] ?? ('Village #' . $vref));
            Plague::cureVillage($vref);
            $flashSuccess = "Desa <b>{$vName}</b> (Wref: {$vref}) berhasil disembuhkan seketika dari wabah penyakit! Karantina telah dicabut.";
        }
    } elseif ($action === 'toggle_quarantine') {
        $vref = (int)($_POST['vref'] ?? 0);
        if ($vref > 0) {
            Plague::toggleQuarantine($vref);
            $cur = Plague::getVillagePlague($vref);
            $qStatus = !empty($cur['quarantine']) ? 'Diaktifkan (Pasar Ditutup ke Luar)' : 'Dicabut (Perdagangan Normal)';
            $flashSuccess = "Status karantina desa #{$vref} berhasil diperbarui: <b>{$qStatus}</b>.";
        }
    } elseif ($action === 'cure_all') {
        if ($db) {
            $countRes = @mysqli_query($db, "SELECT COUNT(*) as cnt FROM `" . TB_PREFIX . "village_plague` WHERE `plague_type` > 0 AND `cure_time` > " . time());
            $cntRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
            $totalCured = (int)($cntRow['cnt'] ?? 0);

            @mysqli_query($db, "UPDATE `" . TB_PREFIX . "village_plague` SET `plague_type` = 0, `cure_time` = 0, `quarantine` = 0, `severity` = 0 WHERE `plague_type` > 0");
            $flashSuccess = "Seluruh <b>{$totalCured}</b> desa yang terinfeksi wabah di seluruh server telah berhasil disembuhkan seketika!";
        }
    }
}

// ─── LOAD DATA FOR DISPLAY ────────────────────────────────────────────────
$now = time();
$activeInfections = [];
$countsByType = [1 => 0, 2 => 0, 3 => 0];
$totalQuarantined = 0;

if ($db) {
    // 1. Fetch all active infections
    $qInf = "SELECT p.*, v.name as vname, v.owner as uid, w.x, w.y, v.pop, u.username, u.tribe
             FROM `" . TB_PREFIX . "village_plague` p
             INNER JOIN `" . TB_PREFIX . "vdata` v ON p.vref = v.wref
             INNER JOIN `" . TB_PREFIX . "wdata` w ON v.wref = w.id
             LEFT JOIN `" . TB_PREFIX . "users` u ON v.owner = u.id
             WHERE p.plague_type > 0 AND p.cure_time > $now
             ORDER BY p.cure_time ASC";
    $resInf = @mysqli_query($db, $qInf);
    if ($resInf) {
        while ($row = mysqli_fetch_assoc($resInf)) {
            $activeInfections[] = $row;
            $type = (int)$row['plague_type'];
            if (isset($countsByType[$type])) {
                $countsByType[$type]++;
            }
            if (!empty($row['quarantine'])) {
                $totalQuarantined++;
            }
        }
    }

    // 2. Fetch list of player villages for dropdown selector
    $villagesList = [];
    $qVils = "SELECT v.wref, v.name as vname, w.x, w.y, v.pop, u.username, u.id as uid
              FROM `" . TB_PREFIX . "vdata` v
              INNER JOIN `" . TB_PREFIX . "wdata` w ON v.wref = w.id
              LEFT JOIN `" . TB_PREFIX . "users` u ON v.owner = u.id
              WHERE v.owner > 2
              ORDER BY u.username ASC, v.name ASC
              LIMIT 300";
    $resVils = @mysqli_query($db, $qVils);
    if ($resVils) {
        while ($v = mysqli_fetch_assoc($resVils)) {
            $villagesList[] = $v;
        }
    }
}

$tribeNames = [
    1 => 'Romawi',
    2 => 'Teuton',
    3 => 'Galia',
    4 => 'Natar',
    5 => 'Monster',
    6 => 'Mesir',
    7 => 'Hun',
    8 => 'Sparta',
    9 => 'Viking',
    10 => 'Nusantara',
];
?>

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; max-width: 1080px; margin: 0 auto; padding: 12px 16px;">

    <!-- HEADER TITLE -->
    <div style="background: linear-gradient(135deg, #991b1b 0%, #b91c1c 100%); color: #fff; padding: 18px 24px; border-radius: 10px; box-shadow: 0 4px 14px rgba(185, 28, 28, 0.3); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0; font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <span>☣️</span> Village Plague & Outbreak Manager
                </h2>
                <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">
                    Pusat pemantauan epidemi desa, manipulasi wabah penyakit (Kolera, Malaria, Pestilensi), karantina wilayah, dan penawar instan.
                </p>
            </div>
            <div>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    Sistem Wabah: <?php echo (defined('PLAGUE_SYSTEM_ENABLED') && !PLAGUE_SYSTEM_ENABLED) ? '❌ Disabled' : '✅ Active'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- FLASH NOTIFICATIONS -->
    <?php if ($flashSuccess): ?>
        <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; color: #065f46; font-size: 13px;">
            <strong>✅ Sukses:</strong> <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; color: #991b1b; font-size: 13px;">
            <strong>❌ Terjadi Kesalahan:</strong> <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Total Desa Terinfeksi</div>
            <div style="font-size: 26px; font-weight: 700; color: #dc2626; margin-top: 4px;">
                ☣️ <?php echo count($activeInfections); ?>
            </div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Wabah Kolera (Pangan)</div>
            <div style="font-size: 26px; font-weight: 700; color: #b91c1c; margin-top: 4px;">
                ☠️ <?php echo $countsByType[1]; ?>
            </div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Demam Rawa (Malaria)</div>
            <div style="font-size: 26px; font-weight: 700; color: #d97706; margin-top: 4px;">
                🦟 <?php echo $countsByType[2]; ?>
            </div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Pestilensi Tempur</div>
            <div style="font-size: 26px; font-weight: 700; color: #7c3aed; margin-top: 4px;">
                💀 <?php echo $countsByType[3]; ?>
            </div>
        </div>
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600;">Dalam Karantina</div>
            <div style="font-size: 26px; font-weight: 700; color: #475569; margin-top: 4px;">
                🚧 <?php echo $totalQuarantined; ?>
            </div>
        </div>
    </div>

    <!-- TABLE: ACTIVE OUTBREAKS -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span>📋</span> Daftar Desa Yang Sedang Terjangkit Wabah (<?php echo count($activeInfections); ?>)
                </h3>
                <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">
                    Memperlihatkan seluruh desa yang terdampak penalti produksi, militer, dan perdagangan akibat epidemi.
                </p>
            </div>
            <?php if (count($activeInfections) > 0): ?>
                <div>
                    <form method="post" action="admin.php?p=plagueManager" onsubmit="return confirm('Apakah Anda yakin ingin menyembuhkan SEMUA desa terinfeksi di seluruh server seketika?');" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="cure_all">
                        <button type="submit" style="background: #10b981; color: #fff; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>💊</span> Sembuhkan Semua Desa Seketika
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($activeInfections)): ?>
            <div style="text-align: center; padding: 32px 16px; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                <div style="font-size: 32px; margin-bottom: 8px;">🌿</div>
                <div style="font-size: 14px; font-weight: 600; color: #334155;">Tidak Ada Desa Yang Terjangkit Wabah</div>
                <div style="font-size: 12px; margin-top: 4px;">Seluruh pemukiman penduduk di server saat ini berada dalam kondisi bugar dan sehat.</div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600;">
                            <th style="padding: 10px 8px;">Desa & Koordinat</th>
                            <th style="padding: 10px 8px;">Pemilik / Suku</th>
                            <th style="padding: 10px 8px;">Jenis Wabah</th>
                            <th style="padding: 10px 8px; text-align: center;">Tingkat</th>
                            <th style="padding: 10px 8px; text-align: center;">Karantina</th>
                            <th style="padding: 10px 8px;">Sisa Waktu</th>
                            <th style="padding: 10px 8px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeInfections as $inf):
                            $pType = (int)$inf['plague_type'];
                            $pInfo = Plague::getPlagueInfo($pType);
                            $remSec = max(0, (int)$inf['cure_time'] - $now);
                            $remH = floor($remSec / 3600);
                            $remM = floor(($remSec % 3600) / 60);
                            $remS = $remSec % 60;
                            $remStr = sprintf('%02dj %02dm %02ds', $remH, $remM, $remS);
                            $tribeStr = $tribeNames[(int)($inf['tribe'] ?? 1)] ?? 'Unknown';
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; background: #fff;">
                                <td style="padding: 10px 8px;">
                                    <div style="font-weight: 700; color: #0f172a;">
                                        <a href="admin.php?p=village&did=<?php echo (int)$inf['vref']; ?>" style="color: #0369a1; text-decoration: none;">
                                            🏘️ <?php echo htmlspecialchars($inf['vname']); ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b;">
                                        (<?php echo $inf['x']; ?>|<?php echo $inf['y']; ?>) • Pop: <?php echo number_format($inf['pop']); ?> • Wref: <?php echo $inf['vref']; ?>
                                    </div>
                                </td>
                                <td style="padding: 10px 8px;">
                                    <div style="font-weight: 600; color: #334155;">
                                        <a href="admin.php?p=player&uid=<?php echo (int)$inf['uid']; ?>" style="color: #475569; text-decoration: none;">
                                            👤 <?php echo htmlspecialchars($inf['username'] ?? 'Unknown'); ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 10px; color: #888;">
                                        Suku: <b><?php echo $tribeStr; ?></b>
                                    </div>
                                </td>
                                <td style="padding: 10px 8px;">
                                    <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; background: <?php echo $pInfo['bg']; ?>; color: <?php echo $pInfo['color']; ?>; border: 1px solid <?php echo $pInfo['border']; ?>;">
                                        <?php echo $pInfo['icon'] . ' ' . $pInfo['name']; ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 8px; text-align: center;">
                                    <span style="display: inline-block; padding: 2px 7px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-weight: 700; font-size: 11px;">
                                        Lv. <?php echo (int)$inf['severity']; ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 8px; text-align: center;">
                                    <?php if (!empty($inf['quarantine'])): ?>
                                        <span style="display: inline-block; padding: 3px 7px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 4px; font-weight: 700; font-size: 10px;">
                                            🚧 Karantina Aktif
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 3px 7px; background: #f1f5f9; color: #64748b; border-radius: 4px; font-size: 10px;">
                                            Bebas
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px 8px;">
                                    <div style="font-weight: 700; color: #b91c1c; font-family: monospace; font-size: 12px;">
                                        ⏳ <?php echo $remStr; ?>
                                    </div>
                                    <div style="font-size: 10px; color: #888;">
                                        Hingga: <?php echo date('H:i:s d/m', (int)$inf['cure_time']); ?>
                                    </div>
                                </td>
                                <td style="padding: 10px 8px; text-align: right;">
                                    <div style="display: inline-flex; gap: 4px;">
                                        <!-- TOGGLE QUARANTINE -->
                                        <form method="post" action="admin.php?p=plagueManager" style="margin: 0; display: inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_quarantine">
                                            <input type="hidden" name="vref" value="<?php echo (int)$inf['vref']; ?>">
                                            <button type="submit" title="Ubah status karantina wilayah" style="background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; padding: 4px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                                🚧 <?php echo !empty($inf['quarantine']) ? 'Buka' : 'Karantina'; ?>
                                            </button>
                                        </form>

                                        <!-- QUICK CURE -->
                                        <form method="post" action="admin.php?p=plagueManager" onsubmit="return confirm('Sembuhkan wabah dari desa ini seketika?');" style="margin: 0; display: inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="cure_village">
                                            <input type="hidden" name="vref" value="<?php echo (int)$inf['vref']; ?>">
                                            <button type="submit" style="background: #10b981; color: #fff; border: 1px solid #059669; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                                💊 Sembuhkan
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- CONTROL PANEL: INFECT A VILLAGE -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; margin-bottom: 24px;">

        <!-- FORM: INFECT VILLAGE -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>☣️</span> Tebarkan Wabah Baru ke Desa
            </h4>
            <p style="font-size: 12px; color: #64748b; margin: 0 0 14px 0;">
                Pilih desa sasaran dan jenis wabah yang ingin diinjeksikan secara instan untuk pengujian atau skenario krisis.
            </p>

            <form method="post" action="admin.php?p=plagueManager">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="infect_village">

                <!-- TARGET SELECTION MODE -->
                <div style="margin-bottom: 12px; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Metode Pemilihan Sasaran:
                    </label>
                    <div style="display: flex; gap: 12px; font-size: 12px; margin-bottom: 8px;">
                        <label style="cursor: pointer;">
                            <input type="radio" name="target_mode" value="dropdown" checked onclick="switchTargetMode('dropdown')"> Pilih Dari List Desa
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="target_mode" value="coords" onclick="switchTargetMode('coords')"> Koordinat (X|Y)
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="target_mode" value="manual" onclick="switchTargetMode('manual')"> ID Desa (Wref)
                        </label>
                    </div>

                    <!-- DROPDOWN SELECTOR -->
                    <div id="target_dropdown_box">
                        <select name="target_dropdown" style="width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 12px; background: #fff;">
                            <option value="">-- Pilih Desa Pemain --</option>
                            <?php foreach ($villagesList as $vil): ?>
                                <option value="<?php echo $vil['wref']; ?>">
                                    <?php echo htmlspecialchars($vil['username']); ?> — <?php echo htmlspecialchars($vil['vname']); ?> (<?php echo $vil['x']; ?>|<?php echo $vil['y']; ?>) [Pop: <?php echo $vil['pop']; ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- COORDS SELECTOR -->
                    <div id="target_coords_box" style="display: none;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="font-size: 12px; font-weight: 600;">X:</span>
                            <input type="number" name="target_x" placeholder="0" style="width: 70px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px;">
                            <span style="font-size: 12px; font-weight: 600;">Y:</span>
                            <input type="number" name="target_y" placeholder="0" style="width: 70px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px;">
                        </div>
                    </div>

                    <!-- MANUAL WREF -->
                    <div id="target_manual_box" style="display: none;">
                        <input type="number" name="target_wref_manual" placeholder="Contoh: 1234" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px;">
                    </div>
                </div>

                <!-- PLAGUE TYPE -->
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                        Pilih Jenis Wabah:
                    </label>
                    <select name="plague_type" style="width: 100%; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600; background: #f8fafc;">
                        <option value="1">☠️ 1. Wabah Kolera &amp; Kelaparan (-20% Produksi, -15% Tempur)</option>
                        <option value="2">🦟 2. Demam Rawa &amp; Malaria (-25% Speed Truk/Pasukan, -15% Gandum, -20% Pedagang)</option>
                        <option value="3">💀 3. Pestilensi Medan Tempur (+25% Waktu Latih Pasukan, -10% Kayu &amp; Besi)</option>
                    </select>
                </div>

                <!-- SEVERITY & DURATION -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                            Tingkat Keparahan:
                        </label>
                        <select name="severity" style="width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 12px; background: #f8fafc;">
                            <option value="1">Tingkat 1 (Ringan)</option>
                            <option value="2">Tingkat 2 (Sedang)</option>
                            <option value="3">Tingkat 3 (Parah)</option>
                            <option value="4">Tingkat 4 (Kritis)</option>
                            <option value="5">Tingkat 5 (Bencana)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                            Durasi Wabah:
                        </label>
                        <select name="duration_hours" style="width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 12px; background: #f8fafc;">
                            <option value="1">1 Jam</option>
                            <option value="2">2 Jam</option>
                            <option value="4" selected>4 Jam (Standar)</option>
                            <option value="8">8 Jam</option>
                            <option value="12">12 Jam</option>
                            <option value="24">24 Jam (1 Hari)</option>
                            <option value="48">48 Jam (2 Hari)</option>
                        </select>
                    </div>
                </div>

                <!-- QUARANTINE TOGGLE -->
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer;">
                        <input type="checkbox" name="set_quarantine" value="1">
                        <span>🚧 Terapkan Karantina Wilayah Langsung (Blokir pengiriman pasar ke luar)</span>
                    </label>
                </div>

                <button type="submit" style="width: 100%; background: #dc2626; color: #fff; border: 1px solid #b91c1c; padding: 9px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 4px rgba(220,38,38,0.2);">
                    ☣️ Tebarkan Wabah Sekarang
                </button>
            </form>
        </div>

        <!-- GUIDE & INFORMATION BOX -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <span>📖</span> Panduan Mekanisme Wabah &amp; Pasar Gelap
            </h4>
            <div style="font-size: 12px; color: #475569; line-height: 1.6;">
                <p style="margin: 0 0 8px 0;">
                    <b>1. Dampak Wabah Kolera (☠️):</b> Terjadi bila gandum kritis (< 5) atau ditebarkan admin. Mengurangi seluruh produksi sumber daya desa sebesar <b>-20%</b> dan menurunkan kekuatan serang &amp; bertahan pasukan sebesar <b>-15%</b>.
                </p>
                <p style="margin: 0 0 8px 0;">
                    <b>2. Dampak Demam Malaria (🦟):</b> Menurunkan kecepatan gerak pasukan sebesar <b>-25%</b>, kecepatan pedagang sebesar <b>-20%</b>, dan produksi gandum <b>-15%</b>.
                </p>
                <p style="margin: 0 0 8px 0;">
                    <b>3. Dampak Pestilensi Tempur (💀):</b> Memperlambat waktu latihan pasukan di barak dan kandang sebesar <b>+25%</b> serta mengurangi produksi kayu &amp; besi <b>-10%</b>.
                </p>
                <p style="margin: 0 0 8px 0;">
                    <b>4. Karantina Wilayah (🚧):</b> Menutup akses pasar agar pedagang tidak dapat mengirim pasokan ke desa pemain lain.
                </p>
                <p style="margin: 0;">
                    <b>5. Penyembuhan Pemain:</b> Pemain dapat menyembuhkan desa mereka secara mandiri melalui <b>🧪 Obat Penawar Wabah Selundupan</b> (65 Silver di Tab 5 Pasar) atau racikan jamu tabib tradisional di ringkasan desa.
                </p>
            </div>
        </div>

    </div>

</div>

<script type="text/javascript">
function switchTargetMode(mode) {
    document.getElementById('target_dropdown_box').style.display = (mode === 'dropdown') ? 'block' : 'none';
    document.getElementById('target_coords_box').style.display = (mode === 'coords') ? 'block' : 'none';
    document.getElementById('target_manual_box').style.display = (mode === 'manual') ? 'block' : 'none';
}
</script>
