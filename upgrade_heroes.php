<?php
#################################################################################
##  Filename       : upgrade_heroes.php                                        ##
##  Description    : Web & CLI Migration Tool to Upgrade Heroes to Elite Units ##
##  Copyright      : TravianZ Project                                          ##
#################################################################################

// Allow sufficient execution time for large databases
@set_time_limit(300);

// Load TravianZ core
$baseDir = __DIR__;
if (!file_exists($baseDir . '/GameEngine/config.php')) {
    die("Error: GameEngine/config.php tidak ditemukan!");
}

require_once $baseDir . '/GameEngine/config.php';
require_once $baseDir . '/GameEngine/Database.php';

global $database;

// Best unit mapping per tribe
$bestUnits = [
    1  => ['unit' => 6,  'name' => 'Equites Caesaris',   'tribe_name' => 'Romawi'],
    2  => ['unit' => 16, 'name' => 'Teutonic Knight',    'tribe_name' => 'Teuton'],
    3  => ['unit' => 26, 'name' => 'Haeduan',            'tribe_name' => 'Gaul'],
    6  => ['unit' => 56, 'name' => 'Marauder',           'tribe_name' => 'Hun / Marauder'],
    7  => ['unit' => 66, 'name' => 'Resheph Chariot',    'tribe_name' => 'Mesir'],
    8  => ['unit' => 76, 'name' => 'Elpida Rider',       'tribe_name' => 'Spartan / Yunani'],
    9  => ['unit' => 86, 'name' => 'Einherjar',          'tribe_name' => 'Viking'],
    10 => ['unit' => 96, 'name' => 'Gajah Perang',       'tribe_name' => 'Nusantara'],
];

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));

if (empty($database->dblink) || !($database->dblink instanceof mysqli)) {
    $errorMsg = "Koneksi ke database gagal. Pastikan konfigurasi database di GameEngine/config.php sudah benar.";
    if ($isCli) {
        fwrite(STDERR, "[ERROR] " . $errorMsg . PHP_EOL);
        exit(1);
    } else {
        die("<div style='font-family:sans-serif;padding:30px;background:#161b22;color:#f85149;border-radius:10px;margin:40px auto;max-width:600px;border:1px solid #da3633;'><h3>⚠️ Koneksi Database Gagal</h3><p>{$errorMsg}</p></div>");
    }
}

// Fetch statistics
$sql = "SELECT h.heroid, h.uid, h.unit, h.name, u.tribe, u.username
        FROM " . TB_PREFIX . "hero h
        JOIN " . TB_PREFIX . "users u ON h.uid = u.id";
$result = mysqli_query($database->dblink, $sql);

$totalHeroes = 0;
$needUpgrade = 0;
$alreadyElite = 0;
$unsupportedTribe = 0;

$heroList = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $totalHeroes++;
        $tribe = (int)$row['tribe'];
        $currentUnit = (int)$row['unit'];
        if (isset($bestUnits[$tribe])) {
            $targetUnit = $bestUnits[$tribe]['unit'];
            if ($currentUnit === $targetUnit) {
                $alreadyElite++;
                $row['status'] = 'elite';
            } else {
                $needUpgrade++;
                $row['status'] = 'need_upgrade';
            }
        } else {
            $unsupportedTribe++;
            $row['status'] = 'unsupported';
        }
        $heroList[] = $row;
    }
}

$doExecute = $isCli || (isset($_REQUEST['run']) && $_REQUEST['run'] == '1') || (isset($_POST['action']) && $_POST['action'] === 'upgrade');

$upgradeLogs = [];
$upgradedCount = 0;

if ($doExecute && !empty($heroList)) {
    foreach ($heroList as &$h) {
        $tribe = (int)$h['tribe'];
        if (!isset($bestUnits[$tribe])) {
            continue;
        }

        $targetUnit = $bestUnits[$tribe]['unit'];
        $targetName = $bestUnits[$tribe]['name'];
        $currentUnit = (int)$h['unit'];
        $heroid = (int)$h['heroid'];

        if ($currentUnit !== $targetUnit) {
            $updateSql = "UPDATE " . TB_PREFIX . "hero SET `unit` = $targetUnit WHERE `heroid` = $heroid";
            if (mysqli_query($database->dblink, $updateSql)) {
                $upgradedCount++;
                $h['unit'] = $targetUnit;
                $h['status'] = 'just_upgraded';
                $upgradeLogs[] = [
                    'heroid'   => $heroid,
                    'username' => $h['username'],
                    'tribe'    => $bestUnits[$tribe]['tribe_name'],
                    'from'     => $currentUnit,
                    'to'       => "{$targetUnit} ({$targetName})",
                    'success'  => true
                ];
            }
        }
    }
    unset($h);
}

// CLI Execution Output
if ($isCli) {
    echo "=== TravianZ Hero Elite Unit Migration ===" . PHP_EOL;
    echo "Total Heroes: $totalHeroes | Need Upgrade: $needUpgrade | Already Elite: $alreadyElite" . PHP_EOL;
    if ($doExecute) {
        foreach ($upgradeLogs as $log) {
            echo "[OK] Hero ID {$log['heroid']} ({$log['username']}, {$log['tribe']}) upgraded: Unit {$log['from']} -> {$log['to']}" . PHP_EOL;
        }
        echo PHP_EOL . "Done! Upgraded: $upgradedCount heroes. Already elite: $alreadyElite." . PHP_EOL;
    }
    exit(0);
}

// Web UI Output
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Hero ke Pasukan Terbaik - TravianZ</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0d1117;
            --card-bg: rgba(22, 27, 34, 0.95);
            --card-border: rgba(212, 160, 23, 0.3);
            --gold-primary: #d4a017;
            --gold-gradient: linear-gradient(135deg, #fce072 0%, #d4a017 50%, #9c7308 100%);
            --text-main: #f0f6fc;
            --text-muted: #8b949e;
            --success-color: #2ea043;
            --info-color: #388bfd;
            --warning-color: #d29922;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 50% 0%, rgba(212, 160, 23, 0.15) 0%, transparent 75%);
            color: var(--text-main);
            min-height: 100vh;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            width: 100%;
            max-width: 900px;
        }

        .header-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(212, 160, 23, 0.15);
            margin-bottom: 25px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gold-gradient);
        }

        h1 {
            font-family: 'Cinzel', serif;
            font-size: 26px;
            letter-spacing: 1px;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 14px;
            max-width: 650px;
            margin: 0 auto 20px auto;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat-box {
            background: rgba(13, 17, 23, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Cinzel', serif;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-box.need .stat-value { color: #f0883e; }
        .stat-box.elite .stat-value { color: #3fb950; }
        .stat-box.total .stat-value { color: #58a6ff; }
        .stat-box.upgraded .stat-value { color: #d29922; }

        .btn-upgrade {
            display: inline-block;
            background: var(--gold-gradient);
            color: #1a1402;
            font-weight: 700;
            font-size: 15px;
            padding: 14px 35px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(212, 160, 23, 0.4);
            transition: all 0.2s ease;
            letter-spacing: 0.5px;
        }

        .btn-upgrade:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(212, 160, 23, 0.6);
        }

        .section-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .section-card h2 {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #d4a017;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            text-align: left;
            padding: 10px 12px;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #c9d1d9;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46, 160, 67, 0.2);
            color: #3fb950;
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .badge-warning {
            background: rgba(210, 153, 34, 0.2);
            color: #d29922;
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .badge-info {
            background: rgba(56, 139, 253, 0.2);
            color: #58a6ff;
            border: 1px solid rgba(88, 166, 255, 0.3);
        }

        .unit-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .unit-card {
            background: rgba(13, 17, 23, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .unit-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: #21262d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--gold-primary);
            font-size: 13px;
        }

        .unit-details {
            display: flex;
            flex-direction: column;
        }

        .unit-tribe {
            font-size: 11px;
            color: var(--text-muted);
        }

        .unit-name {
            font-size: 13px;
            font-weight: 600;
            color: #f0f6fc;
        }

        .notice-box {
            background: rgba(56, 139, 253, 0.1);
            border-left: 4px solid #388bfd;
            border-radius: 6px;
            padding: 14px 18px;
            font-size: 13px;
            color: #c9d1d9;
            margin-top: 20px;
            line-height: 1.6;
        }

        .notice-box.success {
            background: rgba(46, 160, 67, 0.15);
            border-left-color: #2ea043;
        }

        .notice-box.warning {
            background: rgba(210, 153, 34, 0.15);
            border-left-color: #d29922;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-card">
        <h1>TravianZ - Upgrade Hero ke Pasukan Terbaik</h1>
        <p class="subtitle">
            Alat migrasi otomatis untuk meningkatkan basis unit seluruh Hero pemain ke pasukan kavaleri / pasukan terbaik suku masing-masing (Equites Caesaris, Teutonic Knight, Haeduan, Marauder, Resheph, Elpida, Einherjar, Gajah Perang).
        </p>

        <div class="stats-grid">
            <div class="stat-box total">
                <div class="stat-value"><?php echo $totalHeroes; ?></div>
                <div class="stat-label">Total Hero Terdaftar</div>
            </div>
            <div class="stat-box need">
                <div class="stat-value"><?php echo $needUpgrade; ?></div>
                <div class="stat-label">Perlu Di-upgrade</div>
            </div>
            <div class="stat-box elite">
                <div class="stat-value"><?php echo $alreadyElite; ?></div>
                <div class="stat-label">Sudah Pasukan Terbaik</div>
            </div>
            <?php if ($doExecute): ?>
            <div class="stat-box upgraded">
                <div class="stat-value"><?php echo $upgradedCount; ?></div>
                <div class="stat-label">Berhasil Di-upgrade</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($needUpgrade > 0 && !$doExecute): ?>
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="upgrade">
                <button type="submit" class="btn-upgrade">
                    ⚡ Jalankan Upgrade Sekarang (<?php echo $needUpgrade; ?> Hero)
                </button>
            </form>
        <?php elseif ($doExecute): ?>
            <div class="notice-box success" style="text-align: left;">
                <strong>✅ Proses Upgrade Selesai!</strong><br>
                Sebanyak <strong><?php echo $upgradedCount; ?></strong> hero telah berhasil ditingkatkan ke pasukan terbaik sukunya.<br>
                Semua pendaftaran akun baru juga sudah otomatis mendapatkan pasukan terbaik via <code>Units::createStarterHero()</code>.
            </div>
            <div style="margin-top: 15px;">
                <a href="upgrade_heroes.php" class="btn-upgrade" style="font-size: 13px; padding: 10px 24px;">🔄 Refresh Status Database</a>
            </div>
        <?php else: ?>
            <div class="notice-box success" style="text-align: center;">
                🎉 <strong>Hebat!</strong> Semua hero yang ada di database saat ini sudah menggunakan pasukan terbaik sukunya masing-masing.
            </div>
        <?php endif; ?>
    </div>

    <!-- Mapping Suku & Unit Terbaik -->
    <div class="section-card">
        <h2>🛡️ Daftar Pemetaan Pasukan Terbaik Tiap Suku</h2>
        <div class="unit-preview-grid">
            <?php foreach ($bestUnits as $tribeId => $info): ?>
                <div class="unit-card">
                    <div class="unit-icon">u<?php echo $info['unit']; ?></div>
                    <div class="unit-details">
                        <span class="unit-tribe"><?php echo $info['tribe_name']; ?> (Suku <?php echo $tribeId; ?>)</span>
                        <span class="unit-name"><?php echo $info['name']; ?> (Unit <?php echo $info['unit']; ?>)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Riwayat Upgrade / Status Hero Terdaftar -->
    <div class="section-card">
        <h2>
            📋 <?php echo $doExecute ? "Hasil Log Eksekusi Upgrade" : "Daftar Hero di Database"; ?>
        </h2>

        <?php if (empty($heroList)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">Tidak ada hero terdaftar di database.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Hero ID</th>
                            <th>Pemilik</th>
                            <th>Suku</th>
                            <th>Unit Basis</th>
                            <th>Target Terbaik</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($heroList as $h): 
                            $tribe = (int)$h['tribe'];
                            $target = $bestUnits[$tribe] ?? null;
                        ?>
                        <tr>
                            <td>#<?php echo $h['heroid']; ?></td>
                            <td><strong><?php echo htmlspecialchars($h['username']); ?></strong></td>
                            <td><?php echo $target ? $target['tribe_name'] : "Suku $tribe"; ?></td>
                            <td>Unit <?php echo $h['unit']; ?></td>
                            <td><?php echo $target ? "Unit {$target['unit']} ({$target['name']})" : "-"; ?></td>
                            <td>
                                <?php if ($h['status'] === 'just_upgraded'): ?>
                                    <span class="badge badge-success">⚡ Baru Saja Di-upgrade</span>
                                <?php elseif ($h['status'] === 'elite'): ?>
                                    <span class="badge badge-success">✓ Pasukan Terbaik</span>
                                <?php elseif ($h['status'] === 'need_upgrade'): ?>
                                    <span class="badge badge-warning">Perlu Di-upgrade</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><?php echo $h['status']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="notice-box warning">
            🔒 <strong>Keamanan:</strong> Setelah Anda selesai melakukan upgrade, Anda dapat menghapus file <code>upgrade_heroes.php</code> ini dari <code>public_html</code> Hostinger demi menjaga keamanan server.
        </div>
    </div>
</div>

</body>
</html>
