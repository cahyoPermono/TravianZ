<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : assassin_sanctuary.tpl                                    ##
##  Type           : MAP VIEW TEMPLATE - ASSASSIN BROTHERHOOD SANCTUARY        ##
##  Purpose        : Interactive secret guild UI at hidden map coordinates     ##
##                   Displays contract hiring hall, balance, and mission logs  ##
#################################################################################

global $database, $session, $village, $generator, $d;

require_once __DIR__ . '/../../GameEngine/Assassin.php';
if (!class_exists('BlackMarket')) {
    require_once __DIR__ . '/../../GameEngine/BlackMarket.php';
}

// Process any due contracts on view
Assassin::processContracts();

$sanctuary = Assassin::getSanctuary();
$silver = BlackMarket::getSilver((int)$session->uid);
$gold = (int)$database->getUserField((int)$session->uid, 'gold', 0);

$flashMessage = null;
$flashType = 'info';

// Handle POST contract hiring
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hire_assassin') {
    if (!$session->logged_in) {
        header("Location: login.php");
        exit;
    }

    $contractType = $_POST['contract_type'] ?? '';
    $currency     = $_POST['currency'] ?? 'silver';
    $targetInput  = $_POST['target_input'] ?? '';

    $result = Assassin::hireContract((int)$session->uid, (int)$village->wid, $targetInput, $contractType, $currency);

    if ($result['status'] === 'success') {
        $_SESSION['assassin_flash'] = [
            'type' => 'success',
            'msg' => $result['message'] . " Target: " . htmlspecialchars($result['target_name']) . " " . $result['target_coor'] . ". Estimasi selesai dalam " . round($result['duration'] / 60) . " menit."
        ];
    } else {
        $_SESSION['assassin_flash'] = [
            'type' => 'error',
            'msg' => $result['message']
        ];
    }

    $cCheck = $generator->getMapCheck($d);
    header("Location: karte.php?d=" . (int)$d . "&c=" . $cCheck);
    exit;
}

if (!empty($_SESSION['assassin_flash'])) {
    $flashMessage = $_SESSION['assassin_flash']['msg'];
    $flashType    = $_SESSION['assassin_flash']['type'];
    unset($_SESSION['assassin_flash']);
}

$activeContracts = Assassin::getActiveContracts((int)$session->uid);
$contractHistory = Assassin::getContractHistory((int)$session->uid, 5);
$bannerImg = file_exists(__DIR__ . '/../../img/assassin/sanctuary_banner.jpg') ? 'img/assassin/sanctuary_banner.jpg' : '';
?>

<style type="text/css">
.assassin-wrapper {
    background: #0f1115;
    border: 2px solid #3a1c1c;
    border-radius: 8px;
    padding: 16px;
    color: #e0d8cc;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    box-shadow: 0 4px 15px rgba(0,0,0,0.6);
    margin-bottom: 20px;
}
.assassin-header-banner {
    position: relative;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 15px;
    border: 1px solid #5a2222;
}
.assassin-header-banner img {
    width: 100%;
    height: auto;
    display: block;
    max-height: 220px;
    object-fit: cover;
}
.assassin-header-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(180deg, transparent 0%, rgba(10,10,14,0.95) 85%);
    padding: 16px 14px 10px 14px;
}
.assassin-title {
    margin: 0;
    font-size: 20px;
    color: #f5f5f5;
    text-shadow: 0 2px 4px #000;
    display: flex;
    align-items: center;
    gap: 8px;
}
.assassin-tag {
    font-size: 11px;
    background: #7a1515;
    color: #ffd7d7;
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.assassin-balances {
    display: flex;
    gap: 12px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.assassin-balance-badge {
    background: #181a20;
    border: 1px solid #442a2a;
    border-radius: 5px;
    padding: 6px 14px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.assassin-alert {
    padding: 10px 14px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 12px;
    font-weight: 500;
}
.assassin-alert-success {
    background: #15321f;
    border: 1px solid #2ecc71;
    color: #a3e9b9;
}
.assassin-alert-error {
    background: #3d1414;
    border: 1px solid #e74c3c;
    color: #ffb3b3;
}
.assassin-contract-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.assassin-card {
    background: #15181f;
    border: 1px solid #2c2f38;
    border-radius: 6px;
    padding: 14px;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}
.assassin-card:hover {
    border-color: #962d2d;
    background: #1b1c24;
    transform: translateY(-2px);
}
.assassin-card.selected {
    border-color: #e74c3c;
    background: #20181b;
    box-shadow: 0 0 10px rgba(231, 76, 60, 0.3);
}
.assassin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.assassin-card-title {
    font-size: 14px;
    font-weight: bold;
    color: #f0eae1;
}
.assassin-card-desc {
    font-size: 11px;
    color: #a5a5ad;
    line-height: 1.4;
    min-height: 44px;
    margin-bottom: 10px;
}
.assassin-price-tags {
    display: flex;
    gap: 8px;
    font-size: 11px;
    font-weight: bold;
}
.price-silver {
    color: #bdc3c7;
    background: #252830;
    padding: 3px 8px;
    border-radius: 3px;
    border: 1px solid #474b57;
}
.price-gold {
    color: #f1c40f;
    background: #2e2617;
    padding: 3px 8px;
    border-radius: 3px;
    border: 1px solid #695627;
}
.assassin-form-box {
    background: #14161d;
    border: 1px solid #382525;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;
}
.assassin-form-title {
    font-size: 14px;
    font-weight: bold;
    color: #e74c3c;
    margin-top: 0;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.assassin-input-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 12px;
}
.assassin-input {
    background: #090a0d;
    border: 1px solid #4a3333;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    flex: 1;
    min-width: 180px;
}
.assassin-input:focus {
    outline: none;
    border-color: #e74c3c;
    box-shadow: 0 0 5px rgba(231,76,60,0.5);
}
.assassin-btn-submit {
    background: linear-gradient(180deg, #c0392b 0%, #962d2d 100%);
    color: #fff;
    border: 1px solid #781e1e;
    padding: 9px 20px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.4);
    transition: background 0.2s;
}
.assassin-btn-submit:hover {
    background: linear-gradient(180deg, #e74c3c 0%, #c0392b 100%);
}
.assassin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 8px;
}
.assassin-table th {
    background: #1a1e27;
    color: #ccc;
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #333947;
    font-weight: 600;
}
.assassin-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #232732;
    color: #e0d8cc;
}
.badge-status {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
}
.badge-in-progress { background: #63400a; color: #f9d391; border: 1px solid #946214; }
.badge-completed   { background: #154522; color: #a4f2b9; border: 1px solid #288041; }
.badge-failed      { background: #4a1919; color: #f5a8a8; border: 1px solid #852d2d; }
</style>

<div class="assassin-wrapper">
    <!-- Header Banner -->
    <div class="assassin-header-banner">
        <?php if (!empty($bannerImg)) { ?>
            <img src="<?php echo $bannerImg; ?>?v=<?= filemtime(__DIR__ . '/../../' . $bannerImg); ?>" alt="Assassin Sanctuary" />
        <?php } ?>
        <div class="assassin-header-overlay">
            <h1 class="assassin-title">
                🗡️ Kuil Bayangan (The Shadow Sanctuary)
                <span class="assassin-tag">Klan Assassin</span>
            </h1>
            <div style="font-size: 11px; color: #c5b9a8; margin-top: 4px;">
                Koordinat Rahasia: <b>(<?php echo (int)$sanctuary['x']; ?>|<?php echo (int)$sanctuary['y']; ?>)</b> &bull;
                <i>"Kami bergerak dalam senyap melayani bayang-bayang. Bayar harganya, dan musuh Anda akan binasa tanpa jejak."</i>
            </div>
        </div>
    </div>

    <!-- Balance Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
        <div class="assassin-balances">
            <div class="assassin-balance-badge">
                <span>🪙 Saldo Silver:</span>
                <b style="color: #fff; font-size: 13px;"><?php echo number_format($silver); ?></b>
            </div>
            <div class="assassin-balance-badge">
                <span>👑 Saldo Gold:</span>
                <b style="color: #f1c40f; font-size: 13px;"><?php echo number_format($gold); ?></b>
            </div>
        </div>
        <div style="font-size: 11px; color: #999;">
            Kecepatan Assassin: <b>40 petak/jam</b> &bull; Anonymity: <b>100% Tersembunyi</b>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flashMessage) { ?>
        <div class="assassin-alert <?php echo $flashType === 'success' ? 'assassin-alert-success' : 'assassin-alert-error'; ?>">
            <?php echo $flashMessage; ?>
        </div>
    <?php } ?>

    <!-- Contract Hiring Form -->
    <form method="POST" action="karte.php?d=<?php echo (int)$d; ?>&c=<?php echo $generator->getMapCheck($d); ?>" id="assassinForm">
        <input type="hidden" name="action" value="hire_assassin" />
        <input type="hidden" name="contract_type" id="selectedContractType" value="<?php echo Assassin::CONTRACT_SHADOW_RAID; ?>" />

        <!-- Contract Options Grid -->
        <div class="assassin-contract-grid">
            <?php foreach (Assassin::PRICES as $cType => $info) { 
                $isSelected = ($cType === Assassin::CONTRACT_SHADOW_RAID);
            ?>
                <div class="assassin-card <?php echo $isSelected ? 'selected' : ''; ?>" onclick="selectContract('<?php echo $cType; ?>', this)">
                    <div class="assassin-card-header">
                        <span class="assassin-card-title"><?php echo $info['icon'] . ' ' . $info['title']; ?></span>
                    </div>
                    <div class="assassin-card-desc"><?php echo $info['desc']; ?></div>
                    <div class="assassin-price-tags">
                        <span class="price-silver">🪙 <?php echo $info['silver']; ?> Silver</span>
                        <span class="price-gold">👑 <?php echo $info['gold']; ?> Gold</span>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Target and Execution Box -->
        <div class="assassin-form-box">
            <h3 class="assassin-form-title">
                🎯 Tetapkan Sasaran Kontrak
            </h3>

            <div class="assassin-input-group">
                <input type="text" name="target_input" class="assassin-input" placeholder="Ketik Koordinat (misal: 12|-34) atau Nama Desa Sasaran" required />
                
                <div style="display: flex; gap: 14px; align-items: center; background: #0c0d11; padding: 6px 12px; border-radius: 4px; border: 1px solid #332626;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; font-size: 12px;">
                        <input type="radio" name="currency" value="silver" checked />
                        <span>🪙 Bayar Silver</span>
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 4px; font-size: 12px; color: #f1c40f;">
                        <input type="radio" name="currency" value="gold" />
                        <span>👑 Bayar Gold</span>
                    </label>
                </div>

                <button type="submit" class="assassin-btn-submit" onclick="return confirm('Apakah Anda yakin ingin menyewa assassin untuk kontrak ini? Pembayaran akan dipotong segera.');">
                    📜 Sepakati Kontrak &amp; Eksekusi
                </button>
            </div>

            <div style="font-size: 11px; color: #8c827a; line-height: 1.4;">
                🔒 <b>Jaminan Kerahasiaan:</b> Nama akun atau desa Anda <b>TIDAK PERNAH</b> dibocorkan kepada korban dalam laporan pertempuran maupun pesan sistem. Korban hanya menerima pesan dari <i>"Klan Assassin (Topeng Hitam)"</i>.
            </div>
        </div>
    </form>

    <!-- Active Missions Section -->
    <div style="margin-top: 24px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #e0d8cc; display: flex; align-items: center; gap: 6px;">
            ⏳ Kontrak Sedang Berjalan (Active Contracts)
            <span style="font-size: 11px; color: #888;">(<?php echo count($activeContracts); ?>/3)</span>
        </h3>

        <?php if (!empty($activeContracts)) { ?>
            <table class="assassin-table">
                <thead>
                    <tr>
                        <th>Target Desa</th>
                        <th>Jenis Kontrak</th>
                        <th>Biaya</th>
                        <th>Sisa Waktu</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeContracts as $c) { 
                        $remaining = max(0, (int)$c['end_time'] - time());
                        $cType = $c['contract_type'];
                        $info = Assassin::PRICES[$cType] ?? [];
                        $title = $info['title'] ?? $cType;
                    ?>
                        <tr>
                            <td>
                                <b><?php echo htmlspecialchars($c['target_name']); ?></b>
                                <span style="color: #999;">(<?php echo $c['tw_x'] . '|' . $c['tw_y']; ?>)</span>
                            </td>
                            <td><?php echo ($info['icon'] ?? '🗡️') . ' ' . $title; ?></td>
                            <td><?php echo $c['cost_amount'] . ' ' . ucfirst($c['cost_currency']); ?></td>
                            <td>
                                <span class="assassin-timer" data-seconds="<?php echo $remaining; ?>">
                                    <?php echo sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60); ?>
                                </span>
                            </td>
                            <td><span class="badge-status badge-in-progress">Menyusup...</span></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div style="background: #14171f; padding: 12px; border-radius: 4px; font-size: 12px; color: #7f8c8d; text-align: center;">
                Tidak ada kontrak assassin yang sedang aktif saat ini.
            </div>
        <?php } ?>
    </div>

    <!-- History / Logs Section -->
    <div style="margin-top: 24px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #e0d8cc;">
            📜 Arsip Catatan Kontrak Terakhir
        </h3>

        <?php if (!empty($contractHistory)) { ?>
            <table class="assassin-table">
                <thead>
                    <tr>
                        <th>Target Desa</th>
                        <th>Jenis Kontrak</th>
                        <th>Waktu Selesai</th>
                        <th>Ringkasan Hasil Operasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contractHistory as $h) { 
                        $cType = $h['contract_type'];
                        $info = Assassin::PRICES[$cType] ?? [];
                        $title = $info['title'] ?? $cType;
                        $isSuccess = ($h['status'] == Assassin::STATUS_COMPLETED);
                    ?>
                        <tr>
                            <td>
                                <b><?php echo htmlspecialchars($h['target_name']); ?></b>
                                <span style="color: #999;">(<?php echo $h['tw_x'] . '|' . $h['tw_y']; ?>)</span>
                            </td>
                            <td><?php echo ($info['icon'] ?? '🗡️') . ' ' . $title; ?></td>
                            <td style="color: #aaa;"><?php echo date('d/m/Y H:i', (int)$h['end_time']); ?></td>
                            <td style="font-size: 11px; max-width: 250px;"><?php echo htmlspecialchars($h['result_summary'] ?: 'Operasi selesai'); ?></td>
                            <td>
                                <?php if ($isSuccess) { ?>
                                    <span class="badge-status badge-completed">Sukses</span>
                                <?php } else { ?>
                                    <span class="badge-status badge-failed">Gagal</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div style="background: #14171f; padding: 12px; border-radius: 4px; font-size: 12px; color: #7f8c8d; text-align: center;">
                Belum ada arsip riwayat kontrak.
            </div>
        <?php } ?>
    </div>

    <!-- Return to map option -->
    <div style="margin-top: 20px; text-align: right;">
        <a href="karte.php?z=<?php echo (int)$d; ?>" style="color: #c0392b; font-weight: bold; text-decoration: none; font-size: 12px;">
            &laquo; Kembali ke Tampilan Peta
        </a>
    </div>
</div>

<script type="text/javascript">
function selectContract(type, elem) {
    document.getElementById('selectedContractType').value = type;
    var cards = document.querySelectorAll('.assassin-card');
    cards.forEach(function(c) {
        c.classList.remove('selected');
    });
    elem.classList.add('selected');
}

// Live Countdown Timers
(function() {
    var timers = document.querySelectorAll('.assassin-timer');
    if (!timers.length) return;

    setInterval(function() {
        var shouldReload = false;
        timers.forEach(function(t) {
            var s = parseInt(t.getAttribute('data-seconds'), 10);
            if (s > 0) {
                s--;
                t.setAttribute('data-seconds', s);
                var mins = Math.floor(s / 60);
                var secs = s % 60;
                t.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
            } else {
                t.textContent = 'Menyelesaikan...';
                shouldReload = true;
            }
        });
        if (shouldReload) {
            setTimeout(function() { window.location.reload(); }, 2500);
        }
    }, 1000);
})();
</script>
