<?php
// Diagnostic tool to find the exact cause of 500 on dorf1.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== TravianZ dorf1.php Error Diagnostic ===\n\n";

try {
    require_once __DIR__ . '/GameEngine/config.php';
    echo "[1] config.php loaded successfully.\n";

    require_once __DIR__ . '/GameEngine/Database.php';
    echo "[2] Database.php loaded successfully.\n";

    // Check user cahyo
    $user = $database->query_return("SELECT * FROM " . TB_PREFIX . "users WHERE username = 'cahyo'");
    if (empty($user)) {
        echo "[-] User 'cahyo' not found in users table!\n";
        $allUsers = $database->query_return("SELECT id, username, access FROM " . TB_PREFIX . "users LIMIT 10");
        echo "Sample users:\n";
        print_r($allUsers);
        exit;
    }

    $cahyo = $user[0];
    $uid = (int)$cahyo['id'];
    echo "[+] Found user cahyo: UID={$uid}, Tribe={$cahyo['tribe']}, Access={$cahyo['access']}\n";

    // Check villages
    $vils = $database->query_return("SELECT * FROM " . TB_PREFIX . "vdata WHERE owner = $uid");
    if (empty($vils)) {
        echo "[-] CRITICAL: User cahyo has NO VILLAGES in vdata table!\n";
    } else {
        echo "[+] Found " . count($vils) . " village(s) for user cahyo:\n";
        foreach ($vils as $v) {
            echo "    -> Village WREF={$v['wref']}, Name='{$v['name']}', Pop={$v['pop']}, Capital={$v['capital']}\n";
            $wid = (int)$v['wref'];

            // Check fdata
            $fdata = $database->query_return("SELECT * FROM " . TB_PREFIX . "fdata WHERE vref = $wid");
            echo "       fdata: " . (empty($fdata) ? "MISSING!" : "OK") . "\n";

            // Check units
            $units = $database->query_return("SELECT * FROM " . TB_PREFIX . "units WHERE vref = $wid");
            echo "       units: " . (empty($units) ? "MISSING!" : "OK") . "\n";
        }
    }

    // Now test Session & Village simulation
    echo "\n[3] Testing Village class instantiation...\n";
    $_SESSION['username'] = 'cahyo';
    $_SESSION['id'] = $uid;
    $_SESSION['access'] = (int)$cahyo['access'];
    $_SESSION['tribe'] = (int)$cahyo['tribe'];
    if (!empty($vils)) {
        $_SESSION['wid'] = (int)$vils[0]['wref'];
    }

    require_once __DIR__ . '/GameEngine/Session.php';
    echo "[4] Session.php loaded.\n";

    require_once __DIR__ . '/GameEngine/Village.php';
    echo "[5] Village.php loaded.\n";

    echo "\n[6] Testing Templates inclusion...\n";
    $testTemplates = [
        'Templates/weather_widget.tpl',
        'Templates/field.tpl',
        'Templates/movement.tpl',
        'Templates/production.tpl',
        'Templates/troops.tpl',
        'Templates/quest.tpl',
    ];

    foreach ($testTemplates as $tpl) {
        echo "Testing template: {$tpl}... ";
        ob_start();
        include __DIR__ . '/' . $tpl;
        ob_end_clean();
        echo "OK\n";
    }

    echo "\n=== ALL CHECKS PASSED WITHOUT ERRORS ===\n";

} catch (Throwable $e) {
    echo "\n\n❌ FATAL ERROR CAUGHT:\n";
    echo "Class:   " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . " on line " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
