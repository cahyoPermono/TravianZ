<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/assassinManager.tpl                       ##
##  Type           : Admin Panel Frontend & Engine for Assassin Syndicate      ##
##  Purpose        : Web-based GUI to inspect, relocate, and manage Kuil       ##
##                   Bayangan (Assassin Sanctuary) and active contracts        ##
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
if (!file_exists($repoRoot . '/GameEngine/Assassin.php')) {
    $candidates = [
        dirname(__DIR__, 2),
        dirname(__DIR__),
        __DIR__ . '/../..',
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/travian'
    ];
    foreach ($candidates as $c) {
        if (!empty($c) && file_exists($c . '/GameEngine/Assassin.php')) {
            $repoRoot = $c;
            break;
        }
    }
}

if (!file_exists($repoRoot . '/GameEngine/Assassin.php')) {
    echo '<div style="background:#450a0a; border:2px solid #ef4444; color:#fecaca; padding:24px; border-radius:12px; margin:20px; font-family:sans-serif;">';
    echo '<h3 style="color:#f87171; margin-top:0; font-size:1.3rem;">⚠️ File Backend GameEngine/Assassin.php Belum Terpasang!</h3>';
    echo '<p style="font-size:1rem; margin:10px 0;">Halaman ini membutuhkan file <code>GameEngine/Assassin.php</code>, namun file tersebut tidak ditemukan di direktori server.</p>';
    echo '<p style="font-size:0.9rem; color:#fca5a5;"><strong>Solusi:</strong> Unggah file <code>GameEngine/Assassin.php</code> dari file arsip pembaruan <code>travianz_update_hero_bot_ormas.zip</code> ke folder <code>GameEngine/</code> di server hosting Anda.</p>';
    echo '</div>';
    return;
}

require_once $repoRoot . '/GameEngine/Assassin.php';
if (file_exists($repoRoot . '/GameEngine/Generator.php')) {
    require_once $repoRoot . '/GameEngine/Generator.php';
}

global $database, $generator;
if (empty($generator) && class_exists('MyGenerator')) {
    $generator = new MyGenerator();
}

$flashSuccess = '';
$flashError   = '';

// ─── POST ACTION HANDLERS ──────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'relocate_coords') {
        $tx = isset($_POST['target_x']) ? (int)$_POST['target_x'] : null;
        $ty = isset($_POST['target_y']) ? (int)$_POST['target_y'] : null;

        if ($tx === null || $ty === null) {
            $flashError = 'Koordinat X dan Y harus diisi!';
        } else {
            try {
                $newS = Assassin::relocateSanctuary($tx, $ty);
                $flashSuccess = "Berhasil! Kuil Bayangan telah dipindahkan ke koordinat (<strong>{$newS['x']}|{$newS['y']}</strong>) [Tile ID / Wref: <strong>{$newS['wref']}</strong>].";
            } catch (\Exception $e) {
                $flashError = 'Gagal memindahkan Kuil Bayangan: ' . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($action === 'relocate_random') {
        try {
            $newS = Assassin::relocateSanctuary(null, null);
            $flashSuccess = "Berhasil! Kuil Bayangan telah diacak ke petak terpencil baru di (<strong>{$newS['x']}|{$newS['y']}</strong>) [Tile ID / Wref: <strong>{$newS['wref']}</strong>].";
        } catch (\Exception $e) {
            $flashError = 'Gagal mengacak Kuil Bayangan: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ─── FETCH SANCTUARY DATA ──────────────────────────────────────────────────
$sanctuary = null;
$vData     = null;
$uData     = null;
$loadError = '';

try {
    $sanctuary = Assassin::getSanctuary();
    if (!empty($sanctuary['wref'])) {
        $vData = $database->getVillage($sanctuary['wref']);
        $uData = $database->getUnit($sanctuary['wref']);
    }
} catch (\Throwable $e) {
    $loadError = htmlspecialchars($e->getMessage());
}

// Fetch active & recent contracts
$contracts = [];
try {
    $res = $database->query_return("
        SELECT c.*, 
               u.username AS client_name,
               t.username AS victim_name,
               v.name AS target_village_name
        FROM " . TB_PREFIX . "assassin_contracts c
        LEFT JOIN " . TB_PREFIX . "users u ON c.client_uid = u.id
        LEFT JOIN " . TB_PREFIX . "users t ON c.target_uid = t.id
        LEFT JOIN " . TB_PREFIX . "vdata v ON c.target_wid = v.wref
        ORDER BY c.id DESC
        LIMIT 25
    ");
    if (is_array($res)) {
        $contracts = $res;
    }
} catch (\Throwable $e) {
    $contracts = [];
}

$worldMax = defined('WORLD_MAX') ? (int)WORLD_MAX : 100;
?>

<style>
.asm-card {
    background: #1e1b4b;
    border: 1px solid #3730a3;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.3);
    color: #e0e7ff;
}
.asm-card h3 {
    margin-top: 0;
    color: #c084fc;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #312e81;
    padding-bottom: 10px;
}
.asm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}
.asm-stat-box {
    background: #18181b;
    border: 1px solid #3f3f46;
    border-radius: 8px;
    padding: 14px;
}
.asm-stat-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    margin-bottom: 4px;
}
.asm-stat-val {
    font-size: 1.35rem;
    font-weight: 700;
    color: #f8fafc;
}
.asm-btn {
    background: #7e22ce;
    color: #fff;
    padding: 9px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.15s ease-in-out;
}
.asm-btn:hover {
    background: #9333ea;
    box-shadow: 0 0 12px rgba(147, 51, 234, 0.4);
}
.asm-btn-secondary {
    background: #374151;
    color: #f3f4f6;
}
.asm-btn-secondary:hover {
    background: #4b5563;
}
.asm-input {
    background: #0f172a;
    border: 1px solid #334155;
    color: #f8fafc;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.95rem;
    width: 100px;
}
.asm-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    font-size: 0.88rem;
}
.asm-table th {
    background: #1e1b4b;
    color: #c084fc;
    padding: 10px;
    text-align: left;
    border-bottom: 2px solid #4338ca;
}
.asm-table td {
    padding: 10px;
    border-bottom: 1px solid #27272a;
    color: #e2e8f0;
}
.asm-table tr:hover {
    background: rgba(126, 34, 206, 0.1);
}
.asm-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.asm-badge-pending { background: #854d0e; color: #fef08a; }
.asm-badge-success { background: #166534; color: #bbf7d0; }
.asm-badge-failed  { background: #991b1b; color: #fecaca; }
.asm-alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 0.92rem;
}
.asm-alert-success { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
.asm-alert-error   { background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
</style>

<div style="max-width: 1100px; margin: 0 auto; padding: 10px;">
    <h2 style="color: #c084fc; margin-bottom: 6px; display:flex; align-items:center; gap:10px;">
        🗡️ Assassin Syndicate & Sanctuary Manager
    </h2>
    <p style="color: #94a3b8; font-size: 0.9rem; margin-top: 0; margin-bottom: 20px;">
        Kelola markas rahasia <strong>Kuil Bayangan</strong>, pantau kontrak eksekusi, serta pindahkan koordinat kuil ke mana saja di seluruh peta tanpa merusak database.
    </p>

    <?php if ($flashSuccess): ?>
        <div class="asm-alert asm-alert-success"><?= $flashSuccess ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="asm-alert asm-alert-error"><?= $flashError ?></div>
    <?php endif; ?>
    <?php if ($loadError): ?>
        <div class="asm-alert asm-alert-error">Peringatan status Kuil Bayangan: <?= $loadError ?></div>
    <?php endif; ?>

    <!-- CARD 1: STATUS KUIL BAYANGAN -->
    <div class="asm-card">
        <h3>🏛️ Status Kuil Bayangan (Sanctuary) Saat Ini</h3>
        <?php if (!empty($sanctuary)): ?>
            <div class="asm-grid">
                <div class="asm-stat-box">
                    <div class="asm-stat-label">Nama Kuil & Wref</div>
                    <div class="asm-stat-val" style="color:#c084fc;">
                        <?= htmlspecialchars($sanctuary['name'] ?? 'Kuil Bayangan [Sanctuary]') ?>
                    </div>
                    <div style="font-size:0.85rem; color:#94a3b8; margin-top:4px;">
                        Tile ID (Wref): <strong><?= (int)$sanctuary['wref'] ?></strong>
                    </div>
                </div>

                <div class="asm-stat-box">
                    <div class="asm-stat-label">Koordinat Peta</div>
                    <div class="asm-stat-val" style="color:#38bdf8;">
                        (<?= (int)$sanctuary['x'] ?>|<?= (int)$sanctuary['y'] ?>)
                    </div>
                    <div style="margin-top:6px;">
                        <?php
                        $mapCheck = '';
                        if (!empty($generator) && is_object($generator) && method_exists($generator, 'getMapCheck')) {
                            $mapCheck = '&c=' . $generator->getMapCheck((int)$sanctuary['wref']);
                        }
                        ?>
                        <a href="../karte.php?d=<?= (int)$sanctuary['wref'] ?><?= $mapCheck ?>" target="_blank" style="color:#67e8f9; font-size:0.85rem; text-decoration:underline;">
                            🔍 Buka di Peta (Tile View) ↗
                        </a>
                    </div>
                </div>

                <div class="asm-stat-box">
                    <div class="asm-stat-label">Pasukan Penjaga Kuil</div>
                    <div class="asm-stat-val" style="color:#fbbf24;">
                        <?= (int)($uData['u41'] ?? 500) ?> Praetorian / <?= (int)($uData['u46'] ?? 200) ?> Equites
                    </div>
                    <div style="font-size:0.85rem; color:#94a3b8; margin-top:4px;">
                        Pertahanan garnisun elit otomatis aktif
                    </div>
                </div>

                <div class="asm-stat-box">
                    <div class="asm-stat-label">Gudang Logistik</div>
                    <div class="asm-stat-val" style="color:#4ade80; font-size:1.1rem;">
                        🪵 <?= number_format($vData['wood'] ?? 50000) ?> | 🧱 <?= number_format($vData['clay'] ?? 50000) ?> | ⚔️ <?= number_format($vData['iron'] ?? 50000) ?> | 🌾 <?= number_format($vData['crop'] ?? 50000) ?>
                    </div>
                    <div style="font-size:0.85rem; color:#94a3b8; margin-top:4px;">
                        Kapasitas Gudang: <?= number_format($vData['maxstore'] ?? 80000) ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p style="color:#f87171;">Data Kuil Bayangan belum ditemukan di peta. Silakan gunakan tombol acak lokasi di bawah untuk membangunkannya.</p>
        <?php endif; ?>
    </div>

    <!-- CARD 2: RELOKASI KUIL BAYANGAN -->
    <div class="asm-card">
        <h3>📍 Pindahkan Kuil Bayangan ke Lokasi Baru</h3>
        <p style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0;">
            Gunakan form ini untuk memindahkan markas Kuil Bayangan secara aman dan otomatis. Sistem akan membersihkan petak lama, mendaftarkan petak baru di <code>wdata</code> dan <code>vdata</code>, mengisi pasukan penjaga, serta menyinkronkan koordinat tanpa risiko crash.
        </p>

        <div style="display:flex; flex-wrap:wrap; gap:24px; margin-top:16px;">
            <!-- Form Coords -->
            <form method="POST" style="background:#09090b; border:1px solid #27272a; padding:16px; border-radius:8px; flex:1; min-width:300px;">
                <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                <input type="hidden" name="action" value="relocate_coords">
                <div style="font-weight:600; color:#e0e7ff; margin-bottom:10px;">
                    🎯 Pindahkan ke Koordinat Spesifik
                </div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
                    <label style="color:#94a3b8; font-size:0.85rem;">X:
                        <input type="number" name="target_x" class="asm-input" min="-<?= $worldMax ?>" max="<?= $worldMax ?>" value="<?= (int)($sanctuary['x'] ?? 0) ?>" required>
                    </label>
                    <label style="color:#94a3b8; font-size:0.85rem;">Y:
                        <input type="number" name="target_y" class="asm-input" min="-<?= $worldMax ?>" max="<?= $worldMax ?>" value="<?= (int)($sanctuary['y'] ?? 0) ?>" required>
                    </label>
                </div>
                <button type="submit" class="asm-btn" onclick="return confirm('Pindahkan Kuil Bayangan ke koordinat ini?');">
                    💾 Pindahkan Kuil Bayangan
                </button>
            </form>

            <!-- Form Random -->
            <form method="POST" style="background:#09090b; border:1px solid #27272a; padding:16px; border-radius:8px; flex:1; min-width:300px; display:flex; flex-direction:column; justify-content:space-between;">
                <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                <input type="hidden" name="action" value="relocate_random">
                <div>
                    <div style="font-weight:600; color:#e0e7ff; margin-bottom:8px;">
                        🎲 Acak Lokasi Baru (Wilderness)
                    </div>
                    <p style="font-size:0.83rem; color:#94a3b8; margin:0 0 14px 0;">
                        Sistem akan memilih petak lembah kosong terpencil secara otomatis pada jarak 15–45 petak dari titik pusat peta (0|0).
                    </p>
                </div>
                <button type="submit" class="asm-btn asm-btn-secondary" onclick="return confirm('Acak lokasi Kuil Bayangan ke petak lembah kosong terpencil?');">
                    🔄 Acak Lokasi Baru Otomatis
                </button>
            </form>
        </div>
    </div>

    <!-- CARD 3: DAFTAR KONTRAK EKSEKUSI -->
    <div class="asm-card">
        <h3>📜 Kontrak Pembunuhan Klan Assassin (Terbaru)</h3>
        <?php if (!empty($contracts)): ?>
            <div style="overflow-x:auto;">
                <table class="asm-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pemesan (Klien)</th>
                            <th>Target Pemain</th>
                            <th>Target Desa</th>
                            <th>Tipe Kontrak</th>
                            <th>Biaya Emas</th>
                            <th>Waktu Eksekusi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $c): ?>
                            <?php
                            $statusBadge = '<span class="asm-badge asm-badge-pending">⏳ Berjalan</span>';
                            if ($c['status'] == 1) {
                                $statusBadge = '<span class="asm-badge asm-badge-success">✅ Berhasil</span>';
                            } elseif ($c['status'] == 2) {
                                $statusBadge = '<span class="asm-badge asm-badge-failed">❌ Gagal</span>';
                            }
                            ?>
                            <tr>
                                <td>#<?= (int)$c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['client_name'] ?? ('UID:' . $c['client_uid'])) ?></strong></td>
                                <td style="color:#f87171; font-weight:600;">
                                    <?= htmlspecialchars($c['victim_name'] ?? ('UID:' . $c['target_uid'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['target_village_name'] ?? ('Wref:' . ($c['target_wid'] ?? 0))) ?>
                                </td>
                                <td><?= htmlspecialchars($c['contract_type'] ?? 'Assassinate') ?></td>
                                <td style="color:#fbbf24; font-weight:600;">🪙 <?= (int)($c['cost_amount'] ?? $c['gold_cost'] ?? 0) ?> <?= htmlspecialchars($c['cost_currency'] ?? 'Gold') ?></td>
                                <td><?= !empty($c['end_time']) ? date('d M Y H:i:s', (int)$c['end_time']) : (!empty($c['execution_time']) ? date('d M Y H:i:s', (int)$c['execution_time']) : '-') ?></td>
                                <td><?= $statusBadge ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#94a3b8; font-size:0.9rem;">Belum ada pesanan kontrak pembunuhan dari pemain.</p>
        <?php endif; ?>
    </div>
</div>
