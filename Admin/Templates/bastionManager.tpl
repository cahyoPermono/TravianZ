<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/bastionManager.tpl                        ##
##  Type           : Admin Panel Manager for Benteng Kuno (The Ancient Bastion)##
##  Purpose        : Control center for King of the Hill event at (0|0)        ##
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
require_once $repoRoot . '/GameEngine/Bastion.php';

Bastion::ensureTables();
Bastion::ensureBastionVillage();

$flashSuccess = '';
$flashError   = '';

// ─── POST ACTION HANDLERS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_event') {
        $guardianCount = max(50, (int)($_POST['guardian_count'] ?? 300));
        $holdHours     = max(1, (int)($_POST['hold_hours'] ?? 12));
        $botIntervalHr = max(1, (int)($_POST['bot_interval_hours'] ?? 2));

        $ok = Bastion::startEvent($guardianCount, $holdHours * 3600, $botIntervalHr * 3600);
        if ($ok) {
            $flashSuccess = "Event Benteng Kuno BERHASIL DIBUKA! ($guardianCount penjaga, $holdHours jam durasi bertahan, bot attack tiap $botIntervalHr jam).";
        } else {
            $flashError = "Gagal memulai event Benteng Kuno.";
        }
    } elseif ($action === 'stop_event') {
        $ok = Bastion::stopEvent();
        if ($ok) {
            $flashSuccess = "Event Benteng Kuno telah dihentikan (Status: Damai).";
        } else {
            $flashError = "Gagal menghentikan event.";
        }
    } elseif ($action === 'reset_guardians') {
        $guardianCount = max(50, (int)($_POST['guardian_count'] ?? 300));
        Bastion::ensureBastionVillage($guardianCount);
        Bastion::refillGuardians($guardianCount);
        Bastion::addLog('reset', 0, 0, 'Benteng Kuno Direset', "Admin telah mereset Benteng Kuno dan mengisi ulang $guardianCount prajurit penjaga.");
        $flashSuccess = "Benteng Kuno berhasil direset! Bala bantuan lama dibersihkan dan $guardianCount Penjaga Kuno telah ditempatkan.";
    } elseif ($action === 'trigger_bot_attack') {
        $eventData = Bastion::getEvent();
        Bastion::triggerBotAction($eventData);
        $flashSuccess = "Perintah tindakan bot ke Benteng Kuno telah berhasil dieksekusi secara manual!";
    } elseif ($action === 'force_victory') {
        $allyId = (int)($_POST['winner_alliance'] ?? 0);
        if ($allyId > 0) {
            Bastion::declareVictory($allyId, 1);
            $flashSuccess = "Kemenangan Benteng Kuno berhasil dideklarasikan untuk Aliansi ID #$allyId!";
        } else {
            $flashError = "Pilih aliansi yang valid untuk memenangkan event.";
        }
    } elseif ($action === 'save_schedule') {
        $mode = (int)($_POST['schedule_mode'] ?? 0);
        $scheduledTime = 0;
        if ($mode === Bastion::SCHEDULE_ONEOFF) {
            $dtStr = trim($_POST['scheduled_datetime'] ?? '');
            if (!empty($dtStr)) {
                $scheduledTime = strtotime($dtStr);
            }
        }
        $day = (int)($_POST['recurring_day'] ?? 6);
        $hour = (int)($_POST['recurring_hour'] ?? 19);
        $min = (int)($_POST['recurring_minute'] ?? 0);
        $intervalDays = max(1, (int)($_POST['recurring_interval_days'] ?? 7));

        Bastion::setSchedule($mode, $scheduledTime, $day, $hour, $min, $intervalDays);
        $flashSuccess = "Pengaturan jadwal event berhasil disimpan!";
    } elseif ($action === 'cancel_schedule') {
        Bastion::setSchedule(Bastion::SCHEDULE_MANUAL);
        $flashSuccess = "Jadwal otomatis telah dibatalkan. Event sekarang menggunakan mode manual.";
    }
}

// Ambil data status terbaru
$event = Bastion::getEvent();
$wref = Bastion::BASTION_WREF;
$garrison = Bastion::getBastionGarrison();
$schedDesc = Bastion::formatScheduleDescription($event);
$nextStartTime = Bastion::getNextScheduledStartTime($event, true);
$recentLogs = Bastion::getRecentLogs(20);
$latestWinner = Bastion::getLatestWinner();
$hallOfFame = Bastion::getHallOfFame(20);

// Data Aliansi Penguasa
$holderAllyId = (int)($event['holder_alliance'] ?? 0);
$holderUid = (int)($event['holder_uid'] ?? 0);
$holderAllyName = "Penjaga Kuno Netral";
$holderAllyTag = "KUNO";
$holderPlayerName = "Penjaga Kuno";

if ($holderAllyId > 0) {
    $qAlly = $database->query("SELECT name, tag FROM " . TB_PREFIX . "alidata WHERE id = $holderAllyId LIMIT 1");
    if ($qAlly && $a = mysqli_fetch_assoc($qAlly)) {
        $holderAllyName = $a['name'];
        $holderAllyTag = $a['tag'];
    }
}
if ($holderUid > 0) {
    $qUser = $database->query("SELECT username FROM " . TB_PREFIX . "users WHERE id = $holderUid LIMIT 1");
    if ($qUser && $u = mysqli_fetch_assoc($qUser)) {
        $holderPlayerName = $u['username'];
    }
}

// Durasi & Timer
$now = time();
$holdDuration = (int)($event['hold_duration'] ?: Bastion::DEFAULT_HOLD_DURATION);
$holdStart = (int)($event['hold_start_time'] ?? 0);
$elapsedSeconds = ($event['status'] == Bastion::STATUS_ACTIVE && $holdStart > 0) ? max(0, $now - $holdStart) : 0;
$remainingSeconds = max(0, $holdDuration - $elapsedSeconds);
$progressPercent = min(100, round(($elapsedSeconds / $holdDuration) * 100, 1));

// Daftar Seluruh Aliansi untuk opsi manual victory
$alliesRes = $database->query("SELECT id, name, tag FROM " . TB_PREFIX . "alidata ORDER BY id ASC");
$alliesList = [];
if ($alliesRes) {
    while ($al = mysqli_fetch_assoc($alliesRes)) {
        $alliesList[] = $al;
    }
}

?>

<style type="text/css">
.bm-wrap {
    padding: 12px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1e293b;
}
.bm-hero {
    background: linear-gradient(135deg, #450a0a 0%, #7f1d1d 60%, #991b1b 100%);
    border-radius: 8px;
    padding: 18px 24px;
    color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
}
.bm-hero h2 {
    margin: 0 0 6px;
    font-size: 22px;
    color: #fff;
}
.bm-hero p {
    margin: 0;
    font-size: 13px;
    color: #fecaca;
    line-height: 1.5;
}
.bm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.bm-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.bm-card h3 {
    margin: 0 0 12px;
    font-size: 14px;
    color: #0f172a;
    font-weight: 700;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.bm-stat {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dashed #f1f5f9;
    font-size: 13px;
}
.bm-stat-label {
    color: #64748b;
}
.bm-stat-val {
    font-weight: 600;
    color: #0f172a;
}
.bm-btn {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}
.bm-btn-primary { background: #b91c1c; color: #fff; }
.bm-btn-primary:hover { background: #991b1b; }
.bm-btn-danger { background: #ef4444; color: #fff; }
.bm-btn-danger:hover { background: #dc2626; }
.bm-btn-warning { background: #f59e0b; color: #fff; }
.bm-btn-warning:hover { background: #d97706; }
.bm-btn-secondary { background: #64748b; color: #fff; }
.bm-btn-secondary:hover { background: #475569; }

.bm-alert {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 16px;
}
.bm-alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.bm-alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.bm-form-group {
    margin-bottom: 12px;
}
.bm-form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 4px;
}
.bm-form-control {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    font-size: 13px;
    box-sizing: border-box;
}
.bm-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.bm-table th {
    background: #f8fafc;
    color: #475569;
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.bm-table td {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
}
</style>

<div class="bm-wrap">
    <!-- HERO HEADER -->
    <div class="bm-hero">
        <h2>🏰 Benteng Kuno (0|0) — King of the Hill Control Hub</h2>
        <p>
            Atur dan kelola event benteng pusat peta: mulai atau bekukan event, atur jumlah pasukan penjaga awal, pantau durasi bertahan aliansi, dan uji tindakan penyerangan bot.
        </p>
    </div>

    <!-- NOTIFIKASI -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="bm-alert bm-alert-success">
            <b>✔ Berhasil:</b> <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="bm-alert bm-alert-error">
            <b>✖ Perhatian:</b> <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <!-- STATUS OVERVIEW CARDS -->
    <div class="bm-grid">
        <!-- CARD 1: Status Event -->
        <div class="bm-card">
            <h3><span>⚡</span> Status Event Saat Ini</h3>
            <div class="bm-stat">
                <span class="bm-stat-label">Kondisi Benteng</span>
                <span class="bm-stat-val">
                    <?php if ($event['status'] == Bastion::STATUS_ACTIVE): ?>
                        <span style="color:#b91c1c; font-weight:bold;">● PEREBUTAN AKTIF (LIVE)</span>
                    <?php elseif ($event['status'] == Bastion::STATUS_SCHEDULED): ?>
                        <span style="color:#2563eb; font-weight:bold;">⏳ EVENT TERJADWAL</span>
                    <?php elseif ($event['status'] == Bastion::STATUS_VICTORY): ?>
                        <span style="color:#d97706; font-weight:bold;">🏆 PERIODE KEMENANGAN</span>
                    <?php else: ?>
                        <span style="color:#64748b;">🛡️ GERBANG DITUTUP (DAMAI)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Jadwal Event</span>
                <span class="bm-stat-val" style="color:#0284c7;"><?php echo htmlspecialchars($schedDesc); ?></span>
            </div>
            <?php if ($nextStartTime): ?>
            <div class="bm-stat">
                <span class="bm-stat-label">Mulai Berikutnya</span>
                <span class="bm-stat-val" style="color:#15803d; font-weight:bold;">
                    <?php echo date('d M Y H:i', $nextStartTime); ?> WIB
                    (<?php
                    $diff = $nextStartTime - time();
                    $d = floor($diff / 86400);
                    $h = floor(($diff % 86400) / 3600);
                    $m = floor(($diff % 3600) / 60);
                    echo ($d > 0 ? $d . 'h ' : '') . $h . 'j ' . $m . 'm lagi';
                    ?>)
                </span>
            </div>
            <?php endif; ?>
            <div class="bm-stat">
                <span class="bm-stat-label">Koordinat Benteng</span>
                <span class="bm-stat-val">(0|0) [Wref: <?php echo $wref; ?>]</span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Total Garnisun Penjaga</span>
                <span class="bm-stat-val" style="color:#15803d; font-size:14px;">
                    <?php echo number_format($garrison['total']); ?> Unit
                </span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Tembok Benteng</span>
                <span class="bm-stat-val">Tembok Kuno Level 12</span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Perlindungan Gandum</span>
                <span class="bm-stat-val" style="color:#15803d;">Zero Crop Starvation (Aktif)</span>
            </div>
        </div>

        <!-- CARD 2: Penguasa Saat Ini & Timer -->
        <div class="bm-card">
            <h3><span>👑</span> Penguasa Benteng & Timer</h3>
            <div class="bm-stat">
                <span class="bm-stat-label">Aliansi Pemegang</span>
                <span class="bm-stat-val" style="color:#b91c1c;">
                    <?php echo htmlspecialchars($holderAllyName); ?> [<?php echo htmlspecialchars($holderAllyTag); ?>]
                </span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Panglima Pendahulu</span>
                <span class="bm-stat-val"><?php echo htmlspecialchars($holderPlayerName); ?></span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Waktu Bertahan</span>
                <span class="bm-stat-val" style="font-family:monospace; font-size:14px;">
                    <?php echo sprintf("%02d:%02d:%02d", floor($elapsedSeconds/3600), floor(($elapsedSeconds%3600)/60), $elapsedSeconds%60); ?>
                </span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Sisa Waktu Menang</span>
                <span class="bm-stat-val" style="font-family:monospace; font-size:14px; color:#15803d;">
                    <?php
                    $h = floor($remainingSeconds / 3600);
                    $m = floor(($remainingSeconds % 3600) / 60);
                    $s = $remainingSeconds % 60;
                    echo sprintf("%02d:%02d:%02d", $h, $m, $s);
                    ?>
                </span>
            </div>
            <div class="bm-stat">
                <span class="bm-stat-label">Progres Kemenangan</span>
                <span class="bm-stat-val"><?php echo $progressPercent; ?>%</span>
            </div>
            <?php if (!empty($latestWinner)): ?>
            <div class="bm-stat" style="border-top:1px dashed #e2e8f0; margin-top:8px; padding-top:8px;">
                <span class="bm-stat-label">🏆 Juara Bertahan</span>
                <span class="bm-stat-val" style="color:#d97706; font-weight:bold;">
                    <?php echo htmlspecialchars($latestWinner['alliance_name']); ?> [<?php echo htmlspecialchars($latestWinner['alliance_tag']); ?>]
                    <span style="font-size:11px; color:#475569; font-weight:normal;">(<?php echo htmlspecialchars($latestWinner['leader_username']); ?> • S-<?php echo (int)$latestWinner['season']; ?>)</span>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CARD: PENGATURAN JADWAL MULAI EVENT (SCHEDULER) -->
    <div class="bm-card" style="margin-bottom: 20px; border: 2px solid #0284c7;">
        <h3 style="color: #0369a1; border-bottom: 1px solid #bae6fd;">
            <span>🗓️</span> Pengaturan Jadwal Mulai Event (Scheduler)
        </h3>

        <!-- STATUS JADWAL AKTIF -->
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold; color: #0369a1; margin-bottom: 4px;">
                Status Jadwal Saat Ini:
            </div>
            <div style="font-size: 15px; font-weight: bold; color: #0c4a6e;">
                <?php echo htmlspecialchars($schedDesc); ?>
            </div>
            <?php if ($nextStartTime): ?>
                <div style="font-size: 12px; color: #0284c7; margin-top: 4px;">
                    ⏰ <b>Perkiraan Waktu Mulai Berikutnya:</b> <?php echo date('l, d F Y - H:i', $nextStartTime); ?> WIB
                    <span style="font-weight: bold; color: #166534; background: #dcfce7; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">
                        <?php
                        $diff = $nextStartTime - time();
                        $d = floor($diff / 86400);
                        $h = floor(($diff % 86400) / 3600);
                        $m = floor(($diff % 3600) / 60);
                        echo ($d > 0 ? $d . ' hari ' : '') . $h . ' jam ' . $m . ' menit lagi';
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="admin.php?p=bastionManager">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_schedule" />

            <!-- PILIHAN MODE JADWAL -->
            <div class="bm-form-group">
                <label style="font-size: 13px;">Pilih Mode Penjadwalan:</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 6px;">
                    <label style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
                        <input type="radio" name="schedule_mode" value="0" <?php echo ($event['schedule_mode'] == 0) ? 'checked' : ''; ?> onchange="toggleSchedInputs()" />
                        <div>
                            <b>Manual (Default)</b><br />
                            <span style="font-size: 11px; color: #64748b;">Hanya dimulai saat Admin mengklik tombol Mulai secara manual.</span>
                        </div>
                    </label>

                    <label style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
                        <input type="radio" name="schedule_mode" value="1" <?php echo ($event['schedule_mode'] == 1) ? 'checked' : ''; ?> onchange="toggleSchedInputs()" />
                        <div>
                            <b>Tanggal & Jam Tertentu</b><br />
                            <span style="font-size: 11px; color: #64748b;">Mulai satu kali pada tanggal & jam spesifik yang Anda pilih.</span>
                        </div>
                    </label>

                    <label style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
                        <input type="radio" name="schedule_mode" value="2" <?php echo ($event['schedule_mode'] == 2) ? 'checked' : ''; ?> onchange="toggleSchedInputs()" />
                        <div>
                            <b>Mingguan Rutin (Weekend War)</b><br />
                            <span style="font-size: 11px; color: #64748b;">Otomatis mulai setiap minggu di hari & jam yang sama (misal tiap Sabtu jam 19:00).</span>
                        </div>
                    </label>

                    <label style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
                        <input type="radio" name="schedule_mode" value="3" <?php echo ($event['schedule_mode'] == 3) ? 'checked' : ''; ?> onchange="toggleSchedInputs()" />
                        <div>
                            <b>Interval Rutin (Tiap N Hari)</b><br />
                            <span style="font-size: 11px; color: #64748b;">Otomatis mulai setiap N hari setelah masa kedaulatan sebelumnya berakhir.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- INPUT MODE 1: Tanggal & Jam Tertentu -->
            <div id="box_mode_1" class="bm-form-group" style="display: none; background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px;">
                <label>Pilih Tanggal & Waktu Mulai:</label>
                <?php
                $valDt = !empty($event['scheduled_start_time']) ? date('Y-m-d\TH:i', $event['scheduled_start_time']) : date('Y-m-d\TH:i', strtotime('+1 day 19:00'));
                ?>
                <input type="datetime-local" name="scheduled_datetime" class="bm-form-control" value="<?php echo $valDt; ?>" style="max-width: 320px;" />
                <span style="font-size: 11px; color: #64748b; display: block; margin-top: 4px;">Pemain akan melihat hitung mundur (countdown) menuju tanggal ini di halaman benteng.</span>
            </div>

            <!-- INPUT MODE 2: Mingguan Rutin -->
            <div id="box_mode_2" class="bm-form-group" style="display: none; background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
                    <div>
                        <label>Hari Tiap Minggu:</label>
                        <select name="recurring_day" class="bm-form-control">
                            <option value="6" <?php echo ($event['recurring_day'] == 6) ? 'selected' : ''; ?>>Sabtu (Malam Minggu)</option>
                            <option value="0" <?php echo ($event['recurring_day'] == 0) ? 'selected' : ''; ?>>Minggu</option>
                            <option value="5" <?php echo ($event['recurring_day'] == 5) ? 'selected' : ''; ?>>Jumat</option>
                            <option value="1" <?php echo ($event['recurring_day'] == 1) ? 'selected' : ''; ?>>Senin</option>
                            <option value="2" <?php echo ($event['recurring_day'] == 2) ? 'selected' : ''; ?>>Selasa</option>
                            <option value="3" <?php echo ($event['recurring_day'] == 3) ? 'selected' : ''; ?>>Rabu</option>
                            <option value="4" <?php echo ($event['recurring_day'] == 4) ? 'selected' : ''; ?>>Kamis</option>
                        </select>
                    </div>
                    <div>
                        <label>Jam (00 - 23):</label>
                        <input type="number" name="recurring_hour" class="bm-form-control" value="<?php echo (int)($event['recurring_hour'] ?? 19); ?>" min="0" max="23" />
                    </div>
                    <div>
                        <label>Menit (00 - 59):</label>
                        <input type="number" name="recurring_minute" class="bm-form-control" value="<?php echo (int)($event['recurring_minute'] ?? 0); ?>" min="0" max="59" />
                    </div>
                </div>
            </div>

            <!-- INPUT MODE 3: Interval Hari -->
            <div id="box_mode_3" class="bm-form-group" style="display: none; background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px;">
                <label>Interval Tiap Berapa Hari:</label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" name="recurring_interval_days" class="bm-form-control" value="<?php echo (int)($event['recurring_interval_days'] ?? 7); ?>" min="1" max="90" style="max-width: 140px;" />
                    <span style="font-size: 12px; color: #475569;">Hari sekali setelah event sebelumnya selesai</span>
                </div>
            </div>

            <!-- TOMBOL SIMPAN / RESET -->
            <div style="display: flex; gap: 8px; margin-top: 14px;">
                <button type="submit" class="bm-btn" style="background: #0284c7; color: #fff; padding: 9px 18px;">
                    💾 Simpan & Terapkan Jadwal
                </button>
                <button type="button" class="bm-btn bm-btn-secondary" onclick="if(confirm('Batalkan jadwal dan kembalikan ke mode manual?')) { document.getElementById('cancelSchedForm').submit(); }">
                    ❌ Batalkan Jadwal (Kembali ke Manual)
                </button>
            </div>
        </form>

        <form id="cancelSchedForm" method="post" action="admin.php?p=bastionManager" style="display: none;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="cancel_schedule" />
        </form>
    </div>

    <!-- KONTROL ADMINISTRATOR -->
    <div class="bm-grid">
        <!-- FORM BUKA / MULAI EVENT -->
        <div class="bm-card">
            <h3><span>🚀</span> Buka & Mulai Event Benteng</h3>
            <form method="post" action="admin.php?p=bastionManager">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="start_event" />
                
                <div class="bm-form-group">
                    <label>Jumlah Penjaga Awal Benteng (Unit):</label>
                    <input type="number" name="guardian_count" class="bm-form-control" value="<?php echo (int)($event['guardian_initial_troops'] ?: 300); ?>" min="50" max="10000" />
                    <span style="font-size:11px; color:#64748b;">Rekomendasi: 250 - 400 unit (seimbang & pantas dikeroyok aliansi).</span>
                </div>

                <div class="bm-form-group">
                    <label>Durasi Bertahan untuk Menang (Jam):</label>
                    <input type="number" name="hold_hours" class="bm-form-control" value="<?php echo round(($event['hold_duration'] ?: Bastion::DEFAULT_HOLD_DURATION) / 3600); ?>" min="1" max="168" />
                    <span style="font-size:11px; color:#64748b;">Default: 12 jam berturut-turut.</span>
                </div>

                <div class="bm-form-group">
                    <label>Interval Serangan Uji Bot (Jam):</label>
                    <input type="number" name="bot_interval_hours" class="bm-form-control" value="<?php echo round(($event['bot_attack_interval'] ?: Bastion::DEFAULT_BOT_INTERVAL) / 3600); ?>" min="1" max="48" />
                    <span style="font-size:11px; color:#64748b;">Default: 2 jam sekali.</span>
                </div>

                <button type="submit" class="bm-btn bm-btn-primary" style="width:100%; padding:10px;">
                    ⚔️ Mulai / Buka Event Benteng Sekarang
                </button>
            </form>
        </div>

        <!-- TINDAKAN CEPAT & MAINTENANCE -->
        <div class="bm-card">
            <h3><span>⚙️</span> Tindakan Cepat & Pengujian</h3>
            
            <!-- Hentikan Event -->
            <form method="post" action="admin.php?p=bastionManager" style="margin-bottom:12px;" onsubmit="return confirm('Hentikan event benteng sekarang?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="stop_event" />
                <button type="submit" class="bm-btn bm-btn-danger" style="width:100%;">
                    🛑 Bekukan / Tutup Event (Damai)
                </button>
            </form>

            <!-- Reset Benteng & Isi Ulang Penjaga -->
            <form method="post" action="admin.php?p=bastionManager" style="margin-bottom:12px;" onsubmit="return confirm('Reset Benteng Kuno dan isi ulang pasukan penjaga?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_guardians" />
                <div style="display:flex; gap:6px; margin-bottom:6px;">
                    <input type="number" name="guardian_count" class="bm-form-control" value="300" min="50" style="width:110px;" placeholder="Unit" />
                    <button type="submit" class="bm-btn bm-btn-warning" style="flex:1;">
                        🔄 Reset & Isi Ulang Penjaga
                    </button>
                </div>
            </form>

            <!-- Kirim Serangan Bot Sekarang -->
            <form method="post" action="admin.php?p=bastionManager" style="margin-bottom:12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="trigger_bot_attack" />
                <button type="submit" class="bm-btn bm-btn-secondary" style="width:100%;">
                    🤖 Tes Eksekusi Aksi Bot Sekarang
                </button>
            </form>

            <!-- Deklarasikan Kemenangan Manual -->
            <form method="post" action="admin.php?p=bastionManager" onsubmit="return confirm('Deklarasikan kemenangan sekarang untuk aliansi ini?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="force_victory" />
                <div style="display:flex; gap:6px;">
                    <select name="winner_alliance" class="bm-form-control" style="flex:1;">
                        <option value="">-- Pilih Aliansi Pemenang --</option>
                        <?php foreach ($alliesList as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?> [<?php echo htmlspecialchars($a['tag']); ?>]</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bm-btn bm-btn-primary" style="white-space:nowrap;">
                        🏆 Set Menang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CARD: HALL OF FAME PENGUASA BENTENG -->
    <div class="bm-card" style="margin-bottom: 20px; border: 1px solid #fde68a;">
        <h3 style="color:#b45309; border-bottom:1px solid #fef3c7;">
            <span>🏛️</span> Hall of Fame - Sejarah Penguasa Benteng Kuno
        </h3>
        <?php if (empty($hallOfFame)): ?>
            <p style="color:#64748b; font-size:12px; margin:0;">Belum ada aliansi yang tercatat memenangkan event Benteng Kuno.</p>
        <?php else: ?>
            <table class="bm-table">
                <thead>
                    <tr>
                        <th style="width: 75px;">Musim</th>
                        <th>Aliansi Pemenang</th>
                        <th>Panglima Juara</th>
                        <th>Waktu Kemenangan</th>
                        <th>Status Berkah Kedaulatan</th>
                        <th>Gelar / Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hallOfFame as $idx => $hof): ?>
                        <tr>
                            <td>
                                <b><?php echo ($idx === 0 ? '🥇' : '🎖️'); ?> S-<?php echo (int)$hof['season']; ?></b>
                            </td>
                            <td>
                                <b><?php echo htmlspecialchars($hof['alliance_name']); ?></b>
                                <span style="color:#b45309; font-weight:bold;">[<?php echo htmlspecialchars($hof['alliance_tag']); ?>]</span>
                            </td>
                            <td><?php echo htmlspecialchars($hof['leader_username']); ?></td>
                            <td><?php echo date('d M Y H:i', (int)$hof['win_time']); ?> WIB</td>
                            <td>
                                <?php if ((int)$hof['buff_until'] > time()): ?>
                                    <span style="color:#15803d; font-weight:bold;">Aktif (sisa <?php echo round(((int)$hof['buff_until'] - time()) / 3600); ?> jam)</span>
                                <?php else: ?>
                                    <span style="color:#64748b;">Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo ($idx === 0 ? '<span style="background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:3px; font-size:10px; font-weight:bold;">Juara Bertahan</span>' : htmlspecialchars($hof['note'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- LOG AKTIVITAS BENTENG TERAKHIR -->
    <div class="bm-card">
        <h3><span>📜</span> Riwayat & Log Aktivitas Benteng</h3>
        <?php if (empty($recentLogs)): ?>
            <p style="color:#64748b; font-size:12px; margin:0;">Belum ada log pertempuran tercatat.</p>
        <?php else: ?>
            <table class="bm-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Waktu</th>
                        <th style="width: 90px;">Tipe</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="color:#64748b; font-size:11px;"><?php echo date('d/m H:i:s', $log['event_time']); ?></td>
                            <td>
                                <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">
                                    <?php echo htmlspecialchars($log['log_type']); ?>
                                </span>
                            </td>
                            <td>
                                <b><?php echo htmlspecialchars($log['title']); ?></b><br />
                                <span style="color:#475569; font-size:11px;"><?php echo htmlspecialchars($log['description']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
function toggleSchedInputs() {
    var mode = document.querySelector('input[name="schedule_mode"]:checked');
    if (!mode) return;
    var val = parseInt(mode.value);
    var b1 = document.getElementById('box_mode_1');
    var b2 = document.getElementById('box_mode_2');
    var b3 = document.getElementById('box_mode_3');
    if (b1) b1.style.display = (val === 1) ? 'block' : 'none';
    if (b2) b2.style.display = (val === 2) ? 'block' : 'none';
    if (b3) b3.style.display = (val === 3) ? 'block' : 'none';
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', toggleSchedInputs);
} else {
    toggleSchedInputs();
}
</script>
