<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : bot_manager.php                                           ##
##  Type           : CLI Tool for managing TravianZ Bot AI                      ##
##  Usage          : php scripts/bot_manager.php [spawn|run|list|clean]        ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (PHP_SAPI !== 'cli') {
    die("This tool can only be run via CLI.\n");
}

$repoRoot = dirname(__DIR__);
require_once $repoRoot . '/autoloader.php';
require_once $repoRoot . '/GameEngine/config.php';
require_once $repoRoot . '/GameEngine/Database.php';
require_once $repoRoot . '/GameEngine/BotAI.php';
require_once $repoRoot . '/GameEngine/NameGenerator.php';

$action = $argv[1] ?? 'help';

$tribeNames = [
    1 => 'Roman',
    2 => 'Teuton',
    3 => 'Gaul',
    6 => 'Hun',
    7 => 'Egyptian',
    8 => 'Spartan',
    9 => 'Viking',
];

// Ensure is_bot column exists in users table
$database->query("ALTER TABLE " . TB_PREFIX . "users ADD COLUMN IF NOT EXISTS is_bot TINYINT(1) DEFAULT 0");

switch ($action) {
    case 'spawn':
        $count = isset($argv[2]) ? (int)$argv[2] : 5;
        $specificTribe = isset($argv[3]) ? (int)$argv[3] : 0;

        echo "=== Spawning $count Bot Account(s) with Natural Names ===\n";
        $playableTribes = [1, 2, 3, 6, 7, 8, 9];

        $created = 0;
        for ($i = 1; $i <= $count; $i++) {
            $tribe = ($specificTribe > 0 && in_array($specificTribe, $playableTribes, true))
                ? $specificTribe
                : $playableTribes[array_rand($playableTribes)];

            $tName = $tribeNames[$tribe] ?? 'Warrior';

            // Generate realistic human gamer username
            $username = NameGenerator::generateUsername($tribe);

            // Avoid collisions with existing usernames
            $check = $database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE username = '" . $database->escape($username) . "'");
            if (!empty($check)) {
                $username .= rand(10, 99);
            }

            $password = bin2hex(random_bytes(8));
            $cleanUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username));
            $email = $cleanUser . rand(100, 999) . "@bot.travianz";
            $villageName = NameGenerator::generateVillageName($username, $tribe);
            $bio = NameGenerator::generateBio($tribe);

            // Register user
            $uid = $database->register(
                $username,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
                $email,
                $tribe,
                ""
            );

            if (!$uid) {
                echo "[-] Failed to register bot $username\n";
                continue;
            }

            // Set BOT flag (is_bot = 1), realistic bio, protect = 0 so bots can be raided immediately
            $database->query("
                UPDATE " . TB_PREFIX . "users
                SET is_bot = 1, desc1 = '" . $database->escape($bio) . "', desc2 = '[#0]', access = 2, protect = 0
                WHERE id = $uid
            ");

            // Generate village in random quadrant
            $kid = rand(1, 4);
            $wid = $database->generateVillages(
                [
                    [
                        'wid'     => 0,
                        'mode'    => 0,
                        'type'    => 3,
                        'kid'     => $kid,
                        'capital' => 1,
                        'pop'     => 2,
                        'name'    => $villageName,
                        'natar'   => 0
                    ]
                ],
                $uid,
                $username
            );

            if (!$wid) {
                echo "[-] Failed to generate village for $username\n";
                continue;
            }

            // Ensure units row exists
            $qUnits = $database->query_return("SELECT vref FROM " . TB_PREFIX . "units WHERE vref = $wid");
            if (empty($qUnits)) {
                $database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($wid)");
            }

            // Initialize starter resource fields (level 1)
            $database->query("
                UPDATE " . TB_PREFIX . "fdata
                SET f1=1, f2=1, f3=1, f4=1, f5=1, f6=1, f7=1, f8=1, f9=1,
                    f10=1, f11=1, f12=1, f13=1, f14=1, f15=1, f16=1, f17=1, f18=1
                WHERE vref = $wid
            ");

            // Starter resources & warehouse capacity
            $database->query("
                UPDATE " . TB_PREFIX . "vdata
                SET wood = 800, clay = 800, iron = 800, crop = 800,
                    maxstore = 1200, maxcrop = 1200
                WHERE wref = $wid
            ");

            // Recalculate population
            $pop = BotAI::recountPop($wid);

            // Get coordinates
            $coor = $database->getCoor($wid);

            echo "[+] Created: {$username} | Tribe: {$tName} | Village: \"{$villageName}\" (#{$wid} @ {$coor['x']}|{$coor['y']}) | Pop: {$pop}\n";
            $created++;
        }

        echo "=== Finished! Created $created bot(s). ===\n";
        break;

    case 'run':
        echo "=== Running Bot AI Execution Cycle ===\n";
        $report = BotAI::run(true);
        echo "Status : " . ($report['status'] ?? 'unknown') . "\n";
        echo "Bots   : " . ($report['bots_total'] ?? 0) . " total, " . ($report['bots_active'] ?? 0) . " active\n";

        if (!empty($report['logs'])) {
            echo "\nActions taken:\n";
            foreach ($report['logs'] as $botName => $actions) {
                echo "-> {$botName}:\n";
                foreach ($actions as $act) {
                    echo "     * {$act}\n";
                }
            }
        } else {
            echo "No action taken this tick (idle / caps reached).\n";
        }
        break;

    case 'list':
        echo "=== Active Bot Accounts ===\n";
        $bots = $database->query_return("
            SELECT u.id, u.username, u.tribe, v.wref, v.name as vname, v.pop, w.x, w.y
            FROM " . TB_PREFIX . "users u
            JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
            JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
            WHERE u.access = 2
              AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
            ORDER BY u.id ASC
        ");

        if (empty($bots)) {
            echo "No bot accounts found. Run 'php scripts/bot_manager.php spawn 5' to create bots.\n";
            exit(0);
        }

        printf("%-6s | %-18s | %-10s | %-22s | %-10s | %-6s\n", "UID", "Username", "Tribe", "Village", "Coords", "Pop");
        echo str_repeat("-", 85) . "\n";

        foreach ($bots as $b) {
            $tName = $tribeNames[$b['tribe']] ?? 'Tribe ' . $b['tribe'];
            $coords = "({$b['x']}|{$b['y']})";
            printf("%-6d | %-18s | %-10s | %-22s | %-10s | %-6d\n",
                $b['id'],
                $b['username'],
                $tName,
                $b['vname'],
                $coords,
                $b['pop']
            );
        }
        echo "Total: " . count($bots) . " bot village(s).\n";
        break;

    case 'clean':
        echo "=== Cleaning Bot Accounts ===\n";
        $bots = $database->query_return("
            SELECT u.id, v.wref
            FROM " . TB_PREFIX . "users u
            LEFT JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
            WHERE u.access = 2
              AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
        ");

        if (empty($bots)) {
            echo "No bots to clean.\n";
            exit(0);
        }

        $count = count($bots);
        foreach ($bots as $b) {
            $uid = (int)$b['id'];
            $wid = (int)($b['wref'] ?? 0);
            if ($wid > 0) {
                $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "tdata WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "abdata WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "bdata WHERE wid = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "training WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "research WHERE vref = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "movement WHERE `from` = $wid OR `to` = $wid");
                $database->query("DELETE FROM " . TB_PREFIX . "enforcement WHERE `from` = $wid OR `vref` = $wid");
                $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id = $wid");
            }
            $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $uid");
            $database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
        }
        echo "Cleaned $count bot account(s).\n";
        break;

    default:
        echo "TravianZ Bot AI Manager\n";
        echo "Usage: php scripts/bot_manager.php <command> [arguments]\n\n";
        echo "Commands:\n";
        echo "  spawn [count] [tribe]  Spawn N bot accounts with natural names (default: 5)\n";
        echo "  run                    Manually trigger a Bot AI build/train/raid tick\n";
        echo "  list                   List all current bot accounts and coordinates\n";
        echo "  clean                  Remove all bot accounts\n";
        break;
}
