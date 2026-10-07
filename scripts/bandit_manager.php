<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : bandit_manager.php                                        ##
##  Type           : CLI Tool for managing PvE Bandit Camps & World Boss       ##
##  Usage          : php scripts/bandit_manager.php [list|spawn|run|clean-all] ##
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
require_once $repoRoot . '/GameEngine/BanditCamp.php';

$action = $argv[1] ?? 'help';

switch ($action) {
    case 'status':
    case 'list':
        $camps = BanditCamp::getActiveCamps();
        echo "=== Active Bandit Camps & World Bosses on Map (" . count($camps) . ") ===\n";
        if (empty($camps)) {
            echo "No active camps found. Run 'php scripts/bandit_manager.php run' to spawn camps.\n";
            exit(0);
        }

        printf("%-4s | %-6s | %-12s | %-32s | %-10s | %-8s | %-10s\n",
            "ID", "WREF", "TIER", "NAME", "COORDS", "GUARDS", "BOUNTY");
        echo str_repeat("-", 95) . "\n";

        foreach ($camps as $c) {
            $tierName = BanditCamp::getTierLabel((int)$c['tier']);
            printf("%-4d | %-6d | %-12s | %-32s | %-10s | %-8d | %-10s\n",
                $c['id'],
                $c['wref'],
                $tierName,
                substr($c['name'], 0, 32),
                "({$c['x']}|{$c['y']})",
                $c['cur_hp'],
                number_format($c['bounty_wood'])
            );
        }
        echo "\n";
        break;

    case 'spawn':
        $tier = isset($argv[2]) ? (int)$argv[2] : BanditCamp::TIER_OUTPOST;
        if ($tier < 1 || $tier > 4) {
            echo "Invalid tier. Supported: 1 (Outpost), 2 (Hideout), 3 (Fortress), 4 (World Boss).\n";
            exit(1);
        }

        echo "Spawning Bandit Camp Tier $tier...\n";
        $camp = BanditCamp::spawnCamp($tier);
        if ($camp) {
            echo "Successfully spawned: [{$camp['name']}] at {$camp['coords']} with {$camp['guards']} guards and {$camp['bounty']} bounty!\n";
        } else {
            echo "Failed to spawn camp (no free tiles found near active players).\n";
        }
        break;

    case 'run':
        echo "Executing BanditCamp::run() cycle...\n";
        $res = BanditCamp::run(true);
        echo "Status : " . ($res['status'] ?? 'unknown') . "\n";
        echo "Cleared: " . count($res['cleared'] ?? []) . " camp(s)\n";
        echo "Spawned: " . count($res['spawned'] ?? []) . " camp(s)\n";
        echo "Active : " . ($res['active_count'] ?? 0) . " camp(s)\n";
        break;

    case 'clean-all':
        echo "Cleaning all active Bandit Camps from map...\n";
        $camps = BanditCamp::getActiveCamps();
        $cleaned = 0;
        foreach ($camps as $c) {
            $wref = (int)$c['wref'];
            $database->query("DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $wref");
            $database->query("DELETE FROM " . TB_PREFIX . "fdata WHERE vref = $wref");
            $database->query("DELETE FROM " . TB_PREFIX . "units WHERE vref = $wref");
            $database->query("UPDATE " . TB_PREFIX . "wdata SET occupied = 0 WHERE id = $wref");
            $database->query("UPDATE " . TB_PREFIX . "bandit_camps SET status = 0 WHERE id = " . (int)$c['id']);
            $cleaned++;
        }
        echo "Cleaned $cleaned camp(s).\n";
        break;

    case 'help':
    default:
        echo "=== TravianZ Bandit Camp & World Boss CLI Manager ===\n";
        echo "Usage: php scripts/bandit_manager.php [action] [options]\n\n";
        echo "Available actions:\n";
        echo "  list               List all currently active camps & world boss on map\n";
        echo "  run                Run maintenance cycle (check cleared camps & maintain targets)\n";
        echo "  spawn [tier]       Spawn a specific tier (1=Kroco, 2=Begal, 3=Benteng, 4=World Boss)\n";
        echo "  clean-all          Remove all active bandit camps and free map tiles\n";
        echo "  status             Show active count and status\n\n";
        break;
}
