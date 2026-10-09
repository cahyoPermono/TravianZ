<?php
// check_db.php - Standalone MySQL Connection Diagnostic
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$configFile = __DIR__ . '/GameEngine/config.php';
if (!file_exists($configFile)) {
    die("File GameEngine/config.php tidak ditemukan!");
}

include_once($configFile);

echo "<h2>🔧 TravianZ Database Connection Diagnostic</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse; font-family:sans-serif;'>";
echo "<tr><td><b>SQL_SERVER</b></td><td>" . htmlspecialchars(defined('SQL_SERVER') ? SQL_SERVER : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td><b>SQL_USER</b></td><td>" . htmlspecialchars(defined('SQL_USER') ? SQL_USER : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td><b>SQL_DB</b></td><td>" . htmlspecialchars(defined('SQL_DB') ? SQL_DB : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td><b>SQL_PASS Length</b></td><td>" . (defined('SQL_PASS') ? strlen(SQL_PASS) . " karakter" : 'NOT DEFINED') . "</td></tr>";
echo "<tr><td><b>SQL_PASS Hint</b></td><td>" . (defined('SQL_PASS') && strlen(SQL_PASS) > 2 ? htmlspecialchars(substr(SQL_PASS, 0, 2) . '***' . substr(SQL_PASS, -2)) : '***') . "</td></tr>";
echo "</table>";

echo "<h3>Hasil Pengujian Koneksi:</h3>";

// Tes 1: Menggunakan hostname dari config (biasanya localhost)
$host1 = defined('SQL_SERVER') ? SQL_SERVER : 'localhost';
$port1 = defined('SQL_PORT') ? (int)SQL_PORT : 3306;
$conn1 = @mysqli_connect($host1, SQL_USER, SQL_PASS, SQL_DB, $port1);

if ($conn1) {
    echo "<div style='color:green; padding:10px; background:#e6f4ea; border:1px solid #137333; border-radius:6px; margin-bottom:10px;'>";
    echo "<b>✅ BERHASIL KONEK ke MySQL menggunakan host: <code>$host1</code>!</b><br>";
    echo "MySQL Version: " . mysqli_get_server_info($conn1);
    echo "</div>";

    // -------------------------------------------------------------
    // Tes 2: Periksa Tabel & Akun User
    // -------------------------------------------------------------
    echo "<h3>Daftar Pengguna & Status Desa:</h3>";
    $prefix = defined('TB_PREFIX') ? TB_PREFIX : 's1_';
    $userQuery = @mysqli_query($conn1, "SELECT id, username, access, tribe FROM `{$prefix}users` ORDER BY id ASC LIMIT 20");

    if ($userQuery) {
        echo "<table border='1' cellpadding='6' style='border-collapse:collapse; font-family:sans-serif; width:100%; max-width:800px;'>";
        echo "<tr style='background:#f1f3f4;'><th>UID</th><th>Username</th><th>Access</th><th>Tribe</th><th>Jumlah Desa</th><th>Detail Desa (WREF)</th></tr>";
        while ($u = mysqli_fetch_assoc($userQuery)) {
            $uid = (int)$u['id'];
            $vilQuery = @mysqli_query($conn1, "SELECT wref, name, pop, capital FROM `{$prefix}vdata` WHERE owner = $uid");
            $vils = [];
            if ($vilQuery) {
                while ($v = mysqli_fetch_assoc($vilQuery)) {
                    $vils[] = "WREF: {$v['wref']} ({$v['name']}, Pop: {$v['pop']})";
                }
            }
            $vilCount = count($vils);
            $vilColor = ($vilCount > 0) ? 'green' : ($uid > 2 ? 'red' : 'gray');

            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td><b>" . htmlspecialchars($u['username']) . "</b></td>";
            echo "<td>{$u['access']}</td>";
            echo "<td>{$u['tribe']}</td>";
            echo "<td style='color:$vilColor; font-weight:bold;'>$vilCount</td>";
            echo "<td>" . (!empty($vils) ? implode('<br>', $vils) : '<i style="color:red;">Tidak memiliki desa</i>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Gagal query tabel users: " . htmlspecialchars(mysqli_error($conn1)) . "</p>";
    }

    // -------------------------------------------------------------
    // Tes 3: Periksa Integritas Kolom & Tabel Ekstensi
    // -------------------------------------------------------------
    echo "<h3>Pemeriksaan Integritas Tabel & Kolom:</h3>";
    $tables = ['users', 'vdata', 'fdata', 'units', 'wdata', 'weather', 'village_plague'];
    echo "<ul>";
    foreach ($tables as $t) {
        $tCheck = @mysqli_query($conn1, "SHOW TABLES LIKE '{$prefix}{$t}'");
        $exists = ($tCheck && mysqli_num_rows($tCheck) > 0);
        $icon = $exists ? '✅' : '⚠️';
        echo "<li>{$icon} Tabel <code>{$prefix}{$t}</code>: " . ($exists ? 'Ada' : 'Belum dibuat (akan dibuat otomatis saat fitur dipanggil)') . "</li>";
    }
    echo "</ul>";

    mysqli_close($conn1);
} else {
    echo "<div style='color:#c5221f; padding:10px; background:#fce8e6; border:1px solid #c5221f; border-radius:6px; margin-bottom:10px;'>";
    echo "<b>❌ GAGAL konek ke MySQL menggunakan host <code>$host1</code>:</b><br>";
    echo "<code>" . htmlspecialchars(mysqli_connect_error()) . " (Error Code: " . mysqli_connect_errno() . ")</code>";
    echo "</div>";
}
?>
