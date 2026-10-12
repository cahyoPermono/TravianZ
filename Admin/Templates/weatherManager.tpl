<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/weatherManager.tpl                        ##
##  Type           : Admin Panel Frontend & Engine for Weather & Disasters     ##
##  Purpose        : Web-based GUI to inspect, force-switch, and manage dynamic##
##                   weather cycles, including Karhutla smoke haze.            ##
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
require_once $repoRoot . '/GameEngine/Weather.php';
Weather::ensureTable();

$flashSuccess = '';
$flashError   = '';

// ─── POST ACTION HANDLERS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'force_weather') {
        $weatherType = (int)($_POST['weather_type'] ?? Weather::TYPE_CLEAR);
        $duration    = (int)($_POST['duration'] ?? 21600);
        $zone        = trim((string)($_POST['zone'] ?? 'global'));
        if ($zone === '') $zone = 'global';

        if ($weatherType < 1 || $weatherType > 6) {
            $flashError = 'Tipe cuaca yang dipilih tidak valid!';
        } elseif ($duration <= 0) {
            $flashError = 'Durasi cuaca harus lebih dari 0 detik!';
        } else {
            $result = Weather::generateNextWeather($zone, $weatherType, $duration);
            $info = Weather::getWeatherInfo($weatherType);
            $durHours = round($duration / 3600, 1);
            $flashSuccess = "Berhasil mengaktifkan cuaca <b>{$info['icon']} {$info['name']}</b> untuk zona <i>{$zone}</i> selama {$durHours} jam!";
        }
    } elseif ($action === 'roll_random') {
        $zone = trim((string)($_POST['zone'] ?? 'global'));
        if ($zone === '') $zone = 'global';

        $result = Weather::generateNextWeather($zone);
        $info = Weather::getWeatherInfo((int)$result['weather_type']);
        $flashSuccess = "Siklus cuaca baru berhasil diacak: <b>{$info['icon']} {$info['name']}</b>!";
    }
}

// ─── LOAD CURRENT WEATHER & HISTORY ─────────────────────────────────────────
$curWeather = Weather::getCurrentWeather('global');
$curType    = (int)($curWeather['weather_type'] ?? Weather::TYPE_CLEAR);
$curMeta    = Weather::getWeatherInfo($curType);
$curRemain  = max(0, (int)($curWeather['end_time'] - time()));

// Database history
$history = [];
if (isset($GLOBALS['link']) && $GLOBALS['link']) {
    $hRes = @mysqli_query($GLOBALS['link'], "SELECT * FROM `" . TB_PREFIX . "weather` ORDER BY `id` DESC LIMIT 10");
    if ($hRes) {
        while ($row = mysqli_fetch_assoc($hRes)) {
            $history[] = $row;
        }
    }
}
?>

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; max-width: 1040px; margin: 0 auto; padding: 12px 16px;">

    <!-- HEADER TITLE -->
    <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 18px 24px; border-radius: 10px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0; font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <span>🌤️</span> Weather & Disaster Manager
                </h2>
                <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">
                    Pusat kendali dinamika cuaca, iklim muson, kemarau, kabut tebal, dan badai asap karhutla di TravianZ.
                </p>
            </div>
            <div>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    Status: <?php echo (defined('WEATHER_SYSTEM_ENABLED') && !WEATHER_SYSTEM_ENABLED) ? '❌ Disabled' : '✅ Active'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- FLASH NOTIFICATIONS -->
    <?php if ($flashSuccess): ?>
        <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; color: #065f46; font-size: 13px;">
            <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; color: #991b1b; font-size: 13px;">
            <b>Peringatan:</b> <?php echo $flashError; ?>
        </div>
    <?php endif; ?>

    <!-- ACTIVE WEATHER CARD -->
    <div style="border-radius: 10px; border: 2px solid <?php echo $curMeta['border_color']; ?>; background: <?php echo $curMeta['bg_gradient']; ?>; padding: 18px 22px; margin-bottom: 22px; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 36px;"><?php echo $curMeta['icon']; ?></span>
                    <div>
                        <h3 style="margin: 0; font-size: 20px; color: #0f172a; font-weight: 800;">
                            <?php echo htmlspecialchars($curMeta['name']); ?>
                        </h3>
                        <div style="font-size: 12px; color: #475569; font-weight: 600;">
                            <?php echo htmlspecialchars($curMeta['name_en']); ?> &bull; Zona: <code><?php echo htmlspecialchars($curWeather['zone'] ?? 'global'); ?></code>
                        </div>
                    </div>
                </div>
                <p style="margin: 12px 0 10px 0; font-size: 13px; color: #334155; line-height: 1.5; max-width: 780px;">
                    <?php echo htmlspecialchars($curMeta['desc']); ?>
                </p>
            </div>
            <div style="background: rgba(255,255,255,0.85); padding: 10px 16px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: right; min-width: 170px;">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700;">Sisa Waktu Siklus</div>
                <div style="font-size: 22px; font-weight: 800; color: #0f172a; font-family: monospace; margin: 3px 0;">
                    <?php echo gmdate("H:i:s", $curRemain); ?>
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    Berakhir: <?php echo date("H:i:s d/m/Y", (int)($curWeather['end_time'] ?? time())); ?>
                </div>
            </div>
        </div>

        <!-- TACTICAL MODIFIERS SUMMARY -->
        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.08);">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 8px;">
                Efek Taktis &amp; Modifikasi Server Aktif:
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <?php foreach ($curMeta['effects'] as $eff): ?>
                    <span style="background: rgba(255,255,255,0.9); border: 1px solid rgba(0,0,0,0.12); padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; color: #1e293b;">
                        <?php echo htmlspecialchars($eff); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($curType === Weather::TYPE_KARHUTLA): ?>
            <div style="margin-top: 14px; background: rgba(192, 57, 43, 0.1); border-left: 4px solid #c0392b; padding: 10px 14px; border-radius: 4px; font-size: 12px; color: #78281f; line-height: 1.5;">
                <b>🔥 Mekanisme Khusus Karhutla Aktif:</b> Jarak pandang turun 90%! Pasukan penyerang musuh diselimuti kabut asap pekat tebal dan baru akan terdeteksi di ringkasan desa dan Titik Temu saat sisa waktu tiba <b>&le; 10 menit (600 detik)</b> sebelum sampai.
            </div>
        <?php endif; ?>
    </div>

    <!-- CONTROL PANEL: FORCE WEATHER & ACTIONS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 24px;">

        <!-- FORM: FORCE WEATHER -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>⚡</span> Paksa Ubah Cuaca (Force Change)
            </h4>
            <form method="post" action="admin.php?p=weatherManager">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="force_weather">

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                        Pilih Jenis Cuaca:
                    </label>
                    <select name="weather_type" style="width: 100%; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600; background: #f8fafc;">
                        <option value="1" <?php echo ($curType === 1) ? 'selected' : ''; ?>>☀️ 1. Musim Subur / Cuaca Cerah (Sunny / Harvest)</option>
                        <option value="2" <?php echo ($curType === 2) ? 'selected' : ''; ?>>🌧️ 2. Hujan Badai Muson (Monsoon Storm)</option>
                        <option value="3" <?php echo ($curType === 3) ? 'selected' : ''; ?>>🏜️ 3. Kemarau Panjang &amp; Terik (Severe Drought)</option>
                        <option value="4" <?php echo ($curType === 4) ? 'selected' : ''; ?>>🌫️ 4. Kabut Tebal Nusantara (Dense Fog)</option>
                        <option value="5" <?php echo ($curType === 5) ? 'selected' : ''; ?>>✨ 5. Purnama Agung / Berkah Dewa (Aurora)</option>
                        <option value="6" <?php echo ($curType === 6) ? 'selected' : ''; ?>>🔥 6. Badai Asap Karhutla (Wildfire Smoke Haze)</option>
                    </select>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                        Durasi Siklus Cuaca:
                    </label>
                    <select name="duration" style="width: 100%; padding: 8px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #f8fafc;">
                        <option value="1800">30 Menit (1.800 detik - Uji Coba)</option>
                        <option value="3600">1 Jam (3.600 detik)</option>
                        <option value="10800">3 Jam (10.800 detik)</option>
                        <option value="21600" selected>6 Jam (21.600 detik - Standar)</option>
                        <option value="43200">12 Jam (43.200 detik)</option>
                        <option value="86400">24 Jam (86.400 detik)</option>
                        <option value="259200">3 Hari (259.200 detik)</option>
                    </select>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px;">
                        Zona Cuaca:
                    </label>
                    <input type="text" name="zone" value="global" style="width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box;" readonly>
                    <span style="font-size: 11px; color: #94a3b8;">Saat ini siklus cuaca berjalan secara global untuk seluruh peta dunia.</span>
                </div>

                <button type="submit" style="width: 100%; padding: 10px 16px; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'">
                    🚀 Terapkan Cuaca Sekarang
                </button>
            </form>
        </div>

        <!-- FORM: RANDOM ROLL & INFO -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span>🎲</span> Acak Siklus Cuaca Berikutnya
                </h4>
                <p style="font-size: 12.5px; color: #475569; line-height: 1.5; margin: 0 0 14px 0;">
                    Sistem cuaca TravianZ memiliki bobot probabilitas acak otomatis setiap siklus 6 jam:
                </p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 11.5px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; padding: 2px 0;"><span>☀️ Cuaca Cerah / Subur:</span><b>30%</b></div>
                    <div style="display: flex; justify-content: space-between; padding: 2px 0;"><span>🌧️ Hujan Badai Muson:</span><b>20%</b></div>
                    <div style="display: flex; justify-content: space-between; padding: 2px 0;"><span>🏜️ Kemarau Panjang &amp; Terik:</span><b>20%</b></div>
                    <div style="display: flex; justify-content: space-between; padding: 2px 0; color: #c0392b;"><span>🔥 Badai Asap Karhutla:</span><b>15%</b></div>
                    <div style="display: flex; justify-content: space-between; padding: 2px 0;"><span>🌫️ Kabut Tebal Nusantara:</span><b>10%</b></div>
                    <div style="display: flex; justify-content: space-between; padding: 2px 0; color: #16a34a;"><span>✨ Purnama Agung Dewa:</span><b>5%</b></div>
                </div>
            </div>

            <form method="post" action="admin.php?p=weatherManager" onsubmit="return confirm('Acak siklus cuaca baru sekarang?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="roll_random">
                <input type="hidden" name="zone" value="global">
                <button type="submit" style="width: 100%; padding: 10px 16px; background: #475569; color: #fff; border: none; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#475569'">
                    🎲 Acak Siklus Cuaca Baru Secara Acak
                </button>
            </form>
        </div>

    </div>

    <!-- RECENT WEATHER HISTORY -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
        <h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span>📜</span> Riwayat 10 Siklus Cuaca Terakhir
        </h4>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                <thead>
                    <tr style="background: #f1f5f9; color: #475569; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 8px 10px;">ID</th>
                        <th style="padding: 8px 10px;">Jenis Cuaca</th>
                        <th style="padding: 8px 10px;">Zona</th>
                        <th style="padding: 8px 10px;">Mulai</th>
                        <th style="padding: 8px 10px;">Berakhir</th>
                        <th style="padding: 8px 10px;">Durasi</th>
                        <th style="padding: 8px 10px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="7" style="padding: 14px; text-align: center; color: #94a3b8;">Belum ada riwayat siklus cuaca.</td></tr>
                    <?php else: foreach ($history as $h):
                        $hMeta = Weather::getWeatherInfo((int)$h['weather_type']);
                        $isCurrent = ((int)$h['end_time'] > time());
                        $durH = round(((int)$h['end_time'] - (int)$h['start_time']) / 3600, 1);
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: <?php echo $isCurrent ? '#f0fdf4' : 'transparent'; ?>;">
                            <td style="padding: 8px 10px; font-family: monospace; font-weight: 600;">#<?php echo $h['id']; ?></td>
                            <td style="padding: 8px 10px; font-weight: 600;">
                                <?php echo $hMeta['icon'] . ' ' . htmlspecialchars($hMeta['name']); ?>
                            </td>
                            <td style="padding: 8px 10px;"><code><?php echo htmlspecialchars($h['zone']); ?></code></td>
                            <td style="padding: 8px 10px; color: #64748b;"><?php echo date("d/m H:i", (int)$h['start_time']); ?></td>
                            <td style="padding: 8px 10px; color: #64748b;"><?php echo date("d/m H:i", (int)$h['end_time']); ?></td>
                            <td style="padding: 8px 10px;"><?php echo $durH; ?> Jam</td>
                            <td style="padding: 8px 10px;">
                                <?php if ($isCurrent): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 700;">Sedang Berlangsung</span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 11px;">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
