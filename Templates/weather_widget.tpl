<?php
if (!defined('WEATHER_SYSTEM_ENABLED') || !WEATHER_SYSTEM_ENABLED) {
    return;
}

if (!class_exists('Weather')) {
    include_once("GameEngine/Weather.php");
}
if (!class_exists('Plague')) {
    include_once("GameEngine/Plague.php");
}

$curWeather = Weather::getCurrentWeather();
$weatherType = (int)($curWeather['weather_type'] ?? Weather::TYPE_CLEAR);
$weatherMeta = Weather::getWeatherInfo($weatherType);
$weatherRemain = max(0, (int)($curWeather['end_time'] - time()));

$vid = (int)($village->wid ?? 0);
$villagePlague = Plague::getVillagePlague($vid);
$isPlagued = !empty($villagePlague);
$plagueMeta = $isPlagued ? Plague::getPlagueInfo((int)$villagePlague['plague_type']) : null;
$plagueRemain = $isPlagued ? max(0, (int)($villagePlague['cure_time'] - time())) : 0;
$isQuarantine = $isPlagued && !empty($villagePlague['quarantine']);

// Flash message
$flashMsg = $_SESSION['weather_plague_msg'] ?? null;
$flashType = $_SESSION['weather_plague_type'] ?? 'info';
unset($_SESSION['weather_plague_msg'], $_SESSION['weather_plague_type']);

// Herbal cost (Nusantara gets discount)
$userTribe = (int)($session->tribe ?? 0);
$herbCost = ($userTribe === 10) ? 75 : 150;
?>

<style>
.weather-plague-panel {
    margin: 8px 0 14px 0;
    font-family: Arial, sans-serif;
    font-size: 11px;
}
.wp-card {
    border-radius: 6px;
    border: 1px solid #d9d9d9;
    padding: 8px 12px;
    margin-bottom: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    background: #fff;
}
.wp-weather-card {
    border-left: 5px solid <?php echo $weatherMeta['border_color']; ?>;
    background: <?php echo $weatherMeta['bg_gradient']; ?>;
}
.wp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}
.wp-title {
    font-weight: bold;
    font-size: 13px;
    color: #222;
    display: flex;
    align-items: center;
    gap: 6px;
}
.wp-badge {
    background: rgba(0,0,0,0.08);
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 10px;
    color: #444;
    font-weight: normal;
}
.wp-desc {
    color: #555;
    margin: 3px 0 6px 0;
    line-height: 1.35;
}
.wp-effects {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 5px;
}
.wp-effect-tag {
    background: rgba(255,255,255,0.85);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
    color: #333;
}

/* Plague Box */
.wp-plague-card {
    border-left: 5px solid <?php echo $isPlagued ? $plagueMeta['border'] : '#52c41a'; ?>;
    background: <?php echo $isPlagued ? $plagueMeta['bg'] : '#f6ffed'; ?>;
}
.wp-plague-title {
    font-weight: bold;
    font-size: 12px;
    color: <?php echo $isPlagued ? $plagueMeta['color'] : '#237804'; ?>;
}
.wp-btn {
    display: inline-block;
    padding: 3px 9px;
    background: #722ed1;
    color: #fff !important;
    text-decoration: none;
    border-radius: 4px;
    font-weight: bold;
    font-size: 10px;
    cursor: pointer;
    border: none;
    transition: background 0.2s;
}
.wp-btn:hover {
    background: #531dab;
    color: #fff;
}
.wp-btn-quarantine {
    background: #fa8c16;
}
.wp-btn-quarantine:hover {
    background: #d46b08;
}
.wp-flash {
    padding: 6px 10px;
    border-radius: 4px;
    margin-bottom: 8px;
    font-weight: bold;
    font-size: 11px;
}
.wp-flash-success {
    background: #f6ffed;
    border: 1px solid #b7eb8f;
    color: #389e0d;
}
.wp-flash-error {
    background: #fff1f0;
    border: 1px solid #ffa39e;
    color: #cf1322;
}
.wp-flash-info {
    background: #e6f7ff;
    border: 1px solid #91d5ff;
    color: #096dd9;
}
</style>

<div class="weather-plague-panel">

    <?php if ($flashMsg): ?>
        <div class="wp-flash wp-flash-<?php echo htmlspecialchars($flashType); ?>">
            <?php echo htmlspecialchars($flashMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Weather Widget -->
    <div class="wp-card wp-weather-card">
        <div class="wp-header">
            <div class="wp-title">
                <span><?php echo $weatherMeta['icon']; ?></span>
                <span><?php echo $weatherMeta['name']; ?></span>
            </div>
            <div class="wp-badge">
                ⏳ Berganti dalam: <b><?php echo gmdate("H:i:s", $weatherRemain); ?></b>
            </div>
        </div>
        <div class="wp-desc">
            <?php echo $weatherMeta['desc']; ?>
        </div>
        <div class="wp-effects">
            <?php foreach ($weatherMeta['effects'] as $eff): ?>
                <span class="wp-effect-tag"><?php echo $eff; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Plague Widget -->
    <?php if ($isPlagued): ?>
        <div class="wp-card wp-plague-card">
            <div class="wp-header">
                <div class="wp-plague-title">
                    <span><?php echo $plagueMeta['icon']; ?></span>
                    <span>PERINGATAN WABAH: <?php echo $plagueMeta['name']; ?>!</span>
                    <?php if ($isQuarantine): ?>
                        <span style="color:#d4380d; font-size:10px; margin-left:5px;">[DESA TERKARANTINA]</span>
                    <?php endif; ?>
                </div>
                <div class="wp-badge" style="background:#ffd591; color:#873800;">
                    Sembuh alami: <b><?php echo gmdate("H:i:s", $plagueRemain); ?></b>
                </div>
            </div>
            <div class="wp-desc" style="color: #7d2600;">
                <?php echo $plagueMeta['desc']; ?>
            </div>
            <div class="wp-effects" style="margin-bottom: 8px;">
                <?php foreach ($plagueMeta['effects'] as $eff): ?>
                    <span class="wp-effect-tag" style="background:#fff2e8; border-color:#ffbb96; color:#a8071a;"><?php echo $eff; ?></span>
                <?php endforeach; ?>
            </div>
            <div style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                <a href="village_action.php?action=cure_plague" class="wp-btn" title="Gunakan ramuan jamu untuk menyembuhkan seluruh desa seketika">
                    🌿 Racik Jamu Tradisional & Obat Tabib (<?php echo $herbCost; ?> Kayu/Liat/Besi/Gandum<?php echo ($userTribe == 10) ? ' - Diskon 50% Nusantara!' : ''; ?>)
                </a>
                <a href="village_action.php?action=toggle_quarantine" class="wp-btn wp-btn-quarantine" title="Karantina menutup perdagangan keluar untuk mencegah penularan">
                    🛡️ <?php echo $isQuarantine ? 'Buka Pintu Karantina' : 'Terapkan Karantina Wilayah'; ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>
