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
    mysqli_close($conn1);
} else {
    echo "<div style='color:#c5221f; padding:10px; background:#fce8e6; border:1px solid #c5221f; border-radius:6px; margin-bottom:10px;'>";
    echo "<b>❌ GAGAL konek ke MySQL menggunakan host <code>$host1</code>:</b><br>";
    echo "<code>" . htmlspecialchars(mysqli_connect_error()) . " (Error Code: " . mysqli_connect_errno() . ")</code>";
    echo "</div>";
}

// Tes 2: Menggunakan 127.0.0.1 (TCP/IP)
$conn2 = @mysqli_connect('127.0.0.1', SQL_USER, SQL_PASS, SQL_DB, 3306);
if ($conn2) {
    echo "<div style='color:green; padding:10px; background:#e6f4ea; border:1px solid #137333; border-radius:6px; margin-bottom:10px;'>";
    echo "<b>✅ BERHASIL KONEK ke MySQL menggunakan host: <code>127.0.0.1</code>!</b><br>";
    echo "Tips: Jika host 127.0.0.1 berhasil tapi localhost gagal, ganti SQL_SERVER di config.php menjadi '127.0.0.1'.";
    echo "</div>";
    mysqli_close($conn2);
} else {
    echo "<div style='color:#c5221f; padding:10px; background:#fce8e6; border:1px solid #c5221f; border-radius:6px; margin-bottom:10px;'>";
    echo "<b>❌ GAGAL konek ke MySQL menggunakan host <code>127.0.0.1</code>:</b><br>";
    echo "<code>" . htmlspecialchars(mysqli_connect_error()) . "</code>";
    echo "</div>";
}
?>
