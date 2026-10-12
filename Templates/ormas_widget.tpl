<?php
if (defined('ORMAS_SYSTEM_ENABLED') && !ORMAS_SYSTEM_ENABLED) {
    return;
}

if (!class_exists('Ormas')) {
    include_once("GameEngine/Ormas.php");
}

$vid = (int)($village->wid ?? 0);
$villageOrmas = Ormas::getVillageOrmas($vid);
$isAfflicted = !empty($villageOrmas) && (int)($villageOrmas['ormas_type'] ?? 0) > 0;

// Flash message
$ormasMsg = $_SESSION['ormas_msg'] ?? null;
$ormasMsgType = $_SESSION['ormas_msg_type'] ?? 'info';
unset($_SESSION['ormas_msg'], $_SESSION['ormas_msg_type']);

if (!$isAfflicted && !$ormasMsg) {
    return;
}

$ormasMeta = $isAfflicted ? Ormas::getOrmasInfo((int)$villageOrmas['ormas_type']) : null;
$ormasRemain = $isAfflicted ? max(0, (int)($villageOrmas['end_time'] - time())) : 0;
?>

<style>
.ormas-panel {
    margin: 8px 0 14px 0;
    font-family: Arial, sans-serif;
    font-size: 11px;
}
.ormas-card {
    border-radius: 6px;
    border: 1px solid #d9d9d9;
    padding: 10px 14px;
    margin-bottom: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    background: #fff;
    transition: all 0.2s ease;
}
.ormas-afflicted-card {
    border-left: 6px solid <?php echo $ormasMeta['border_color'] ?? '#d4380d'; ?>;
    background: <?php echo $ormasMeta['bg_gradient'] ?? '#fff'; ?>;
}
.ormas-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.ormas-title {
    font-weight: bold;
    font-size: 13px;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 7px;
}
.ormas-badge {
    background: rgba(0,0,0,0.08);
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    color: #333;
    font-weight: 600;
}
.ormas-desc {
    color: #444;
    margin: 4px 0 8px 0;
    line-height: 1.4;
    font-size: 11.5px;
}
.ormas-effects {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 8px 0 10px 0;
}
.ormas-effect-tag {
    background: rgba(255,255,255,0.9);
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 4px;
    padding: 3px 7px;
    font-size: 10px;
    font-weight: bold;
    color: #c0392b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.ormas-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed rgba(0,0,0,0.15);
}
.ormas-btn {
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background 0.15s ease, transform 0.1s ease;
    text-decoration: none;
}
.ormas-btn:active {
    transform: scale(0.98);
}
.ormas-btn-bribe {
    background: #e67e22;
    color: #fff;
}
.ormas-btn-bribe:hover {
    background: #d35400;
}
.ormas-btn-fight {
    background: #c0392b;
    color: #fff;
}
.ormas-btn-fight:hover {
    background: #962d22;
}
.ormas-cost-text {
    font-size: 10px;
    opacity: 0.9;
    font-weight: normal;
}
.ormas-flash-msg {
    padding: 8px 12px;
    border-radius: 5px;
    margin-bottom: 8px;
    font-size: 11.5px;
    font-weight: bold;
}
.ormas-flash-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.ormas-flash-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<div class="ormas-panel">
    <?php if ($ormasMsg): ?>
        <div class="ormas-flash-msg <?php echo $ormasMsgType === 'success' ? 'ormas-flash-success' : 'ormas-flash-error'; ?>">
            <?php echo htmlspecialchars($ormasMsg); ?>
        </div>
    <?php endif; ?>

    <?php if ($isAfflicted && $ormasMeta): ?>
        <div class="ormas-card ormas-afflicted-card">
            <div class="ormas-header">
                <div class="ormas-title">
                    <span><?php echo $ormasMeta['icon']; ?></span>
                    <span><b><?php echo htmlspecialchars($ormasMeta['name']); ?></b></span>
                    <span style="font-size:10px; color:#666; font-weight:normal;">(<?php echo htmlspecialchars($ormasMeta['alias']); ?>)</span>
                </div>
                <div class="ormas-badge">
                    ⏳ Pergi dalam: <span id="ormasTimer"><?php echo sprintf('%02d:%02d:%02d', floor($ormasRemain/3600), floor(($ormasRemain%3600)/60), $ormasRemain%60); ?></span>
                </div>
            </div>

            <div class="ormas-desc">
                <?php echo htmlspecialchars($ormasMeta['desc']); ?>
            </div>

            <div class="ormas-effects">
                <?php foreach ($ormasMeta['effects'] as $effect): ?>
                    <span class="ormas-effect-tag"><?php echo htmlspecialchars($effect); ?></span>
                <?php endforeach; ?>
            </div>

            <div class="ormas-actions">
                <!-- Opsi 1: Bayar Uang Damai / Proposal -->
                <form action="village_action.php" method="POST" style="margin:0; display:inline;">
                    <input type="hidden" name="action" value="ormas_bribe" />
                    <button type="submit" class="ormas-btn ormas-btn-bribe" title="Bayar tuntutan upeti agar mereka segera bubar">
                        💰 <?php echo htmlspecialchars($ormasMeta['bribe']['label']); ?>
                        <span class="ormas-cost-text">
                            (<?php
                            $bCost = [];
                            if ($ormasMeta['bribe']['wood'] > 0) $bCost[] = $ormasMeta['bribe']['wood'] . ' Kayu';
                            if ($ormasMeta['bribe']['clay'] > 0) $bCost[] = $ormasMeta['bribe']['clay'] . ' Liat';
                            if ($ormasMeta['bribe']['iron'] > 0) $bCost[] = $ormasMeta['bribe']['iron'] . ' Besi';
                            if ($ormasMeta['bribe']['crop'] > 0) $bCost[] = $ormasMeta['bribe']['crop'] . ' Gandum';
                            echo implode(', ', $bCost);
                            ?>)
                        </span>
                    </button>
                </form>

                <!-- Opsi 2: Usir dengan Pasukan Siaga -->
                <form action="village_action.php" method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengerahkan pasukan untuk menggerebek preman ini?');">
                    <input type="hidden" name="action" value="ormas_fight" />
                    <button type="submit" class="ormas-btn ormas-btn-fight" title="Kerahkan prajurit tempur di desa untuk mengusir mereka">
                        ⚔️ <?php echo htmlspecialchars($ormasMeta['fight_label']); ?>
                    </button>
                </form>
            </div>
        </div>

        <script type="text/javascript">
        (function() {
            var remain = <?php echo (int)$ormasRemain; ?>;
            var el = document.getElementById('ormasTimer');
            if (!el || remain <= 0) return;

            var timer = setInterval(function() {
                remain--;
                if (remain <= 0) {
                    clearInterval(timer);
                    el.innerHTML = "00:00:00 (Membubarkan diri...)";
                    setTimeout(function() { location.reload(); }, 2500);
                    return;
                }
                var h = Math.floor(remain / 3600);
                var m = Math.floor((remain % 3600) / 60);
                var s = remain % 60;
                el.innerHTML = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            }, 1000);
        })();
        </script>
    <?php endif; ?>
</div>
