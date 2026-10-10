<?php

#################################################################################
##  Filename       : assassin_manager.php                                      ##
##  Type           : CLI Tool for managing Assassin Syndicate & Sanctuary      ##
##  Usage          : php scripts/assassin_manager.php [status|relocate|set-coords X Y|contracts]
#################################################################################

if (PHP_SAPI !== 'cli') {
    die("This tool can only be run via CLI.\n");
}

$repoRoot = dirname(__DIR__);
require_once $repoRoot . '/autoloader.php';
require_once $repoRoot . '/GameEngine/config.php';
require_once $repoRoot . '/GameEngine/Database.php';
require_once $repoRoot . '/GameEngine/Generator.php';
require_once $repoRoot . '/GameEngine/Assassin.php';

$action = $argv[1] ?? 'status';

switch ($action) {
    case 'status':
    case 'info':
        echo "=== Status Kuil Bayangan (Assassin Syndicate Sanctuary) ===\n";
        $sanctuary = Assassin::getSanctuary();
        if (empty($sanctuary)) {
            echo "Sanctuary belum aktif di database. Jalankan 'php scripts/assassin_manager.php relocate' untuk membuat.\n";
            exit(0);
        }

        $wref = (int)$sanctuary['wref'];
        $check = $generator->getMapCheck($wref);
        $activeContracts = Assassin::getActiveContracts(0); // all active

        echo "Nama Kuil    : " . $sanctuary['name'] . "\n";
        echo "Koordinat    : (" . $sanctuary['x'] . "|" . $sanctuary['y'] . ")\n";
        echo "ID Desa/Wref : " . $sanctuary['wref'] . "\n";
        echo "Map Check    : " . $check . "\n";
        echo "Direct URL   : karte.php?d=" . $wref . "&c=" . $check . "\n";
        echo "Center Map   : karte.php?z=" . $wref . "\n";
        echo "Status       : AKTIF (Tersedia di peta dunia)\n";
        echo "------------------------------------------------------------\n";
        break;

    case 'relocate':
    case 'spawn':
        echo "Memindahkan/Membuat Kuil Bayangan di lokasi lembah acak baru...\n";
        $s = Assassin::relocateSanctuary();
        $check = $generator->getMapCheck($s['wref']);
        echo "Sukses! Kuil Bayangan kini berada di koordinat (" . $s['x'] . "|" . $s['y'] . ") [Wref: " . $s['wref'] . "]\n";
        echo "URL Peta: karte.php?d=" . $s['wref'] . "&c=" . $check . "\n";
        break;

    case 'set-coords':
        if (!isset($argv[2]) || !isset($argv[3])) {
            echo "Penggunaan: php scripts/assassin_manager.php set-coords <X> <Y>\n";
            echo "Contoh    : php scripts/assassin_manager.php set-coords 25 -30\n";
            exit(1);
        }
        $targetX = (int)$argv[2];
        $targetY = (int)$argv[3];
        echo "Menempatkan Kuil Bayangan di koordinat ($targetX|$targetY)...\n";
        try {
            $s = Assassin::relocateSanctuary($targetX, $targetY);
            $check = $generator->getMapCheck($s['wref']);
            echo "Sukses! Kuil Bayangan berhasil ditempatkan di koordinat ($targetX|$targetY) [Wref: " . $s['wref'] . "]\n";
            echo "URL Peta: karte.php?d=" . $s['wref'] . "&c=" . $check . "\n";
        } catch (\Throwable $e) {
            echo "Gagal: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'contracts':
        echo "=== Daftar Kontrak Assassin Aktif ===\n";
        global $database;
        $contracts = $database->query_return("
            SELECT c.*, u.username as client_name, w.x, w.y
            FROM " . TB_PREFIX . "assassin_contracts c
            JOIN " . TB_PREFIX . "users u ON c.client_uid = u.id
            JOIN " . TB_PREFIX . "wdata w ON c.target_wid = w.id
            WHERE c.status = 0
            ORDER BY c.end_time ASC
        ");
        if (empty($contracts)) {
            echo "Tidak ada kontrak assassin yang sedang berjalan saat ini.\n";
            exit(0);
        }
        printf("%-4s | %-15s | %-20s | %-12s | %-10s | %-12s\n", "ID", "Penyewa", "Target", "Jenis", "Biaya", "Sisa Waktu");
        echo str_repeat("-", 80) . "\n";
        foreach ($contracts as $c) {
            $remain = max(0, (int)$c['end_time'] - time());
            $timeStr = sprintf("%02d:%02d", floor($remain/60), $remain%60);
            printf("%-4d | %-15s | %-20s | %-12s | %-10s | %-12s\n",
                $c['id'],
                substr($c['client_name'], 0, 15),
                substr($c['target_name'] . "({$c['x']}|{$c['y']})", 0, 20),
                $c['contract_type'],
                $c['cost_amount'] . " " . $c['cost_currency'],
                $timeStr
            );
        }
        echo "\n";
        break;

    default:
        echo "Penggunaan: php scripts/assassin_manager.php [perintah]\n";
        echo "Perintah tersedia:\n";
        echo "  status            : Tampilkan status, koordinat, dan URL Kuil Bayangan\n";
        echo "  relocate          : Pindahkan kuil ke petak lembah terpencil acak baru\n";
        echo "  set-coords <X> <Y>: Tentukan sendiri koordinat Kuil Bayangan di peta\n";
        echo "  contracts         : Tampilkan daftar kontrak yang sedang berjalan\n";
        break;
}
