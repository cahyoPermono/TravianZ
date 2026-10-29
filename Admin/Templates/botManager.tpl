<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/botManager.tpl                            ##
##  Type           : Admin Panel Frontend & Engine for Autonomous Bot AI       ##
##  Purpose        : Web-based GUI to manage, spawn, run and clean Bot AI      ##
##                   players without requiring terminal/SSH access             ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

// Access control: Admin or Multihunter only
if (!isset($_SESSION['access']) || $_SESSION['access'] < MULTIHUNTER) {
    echo '<p style="color:#f87171;padding:16px;">Access denied: Administrator or Multihunter privileges required.</p>';
    return;
}

$repoRoot = dirname(__DIR__, 2);
require_once $repoRoot . '/autoloader.php';
require_once $repoRoot . '/GameEngine/BotAI.php';
require_once $repoRoot . '/GameEngine/NameGenerator.php';
if (file_exists($repoRoot . '/GameEngine/Units.php')) {
    require_once $repoRoot . '/GameEngine/Units.php';
}

// Ensure database schema integrity for bot flag
$database->query("ALTER TABLE " . TB_PREFIX . "users ADD COLUMN IF NOT EXISTS is_bot TINYINT(1) DEFAULT 0");

$tribeNames = [
    1 => 'Romawi (Roman)',
    2 => 'Teuton',
    3 => 'Galia (Gaul)',
    6 => 'Hun',
    7 => 'Mesir (Egyptian)',
    8 => 'Spartan',
    9 => 'Viking',
    10 => 'Nusantara',
];

$tribeIcons = [
    1 => '🏛️',
    2 => '⚔️',
    3 => '🛡️',
    6 => '🏹',
    7 => '🏺',
    8 => '🛡️',
    9 => '🪓',
    10 => '👑',
];

$playableTribes = [1, 2, 3, 6, 7, 8, 9];
if (defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA) {
    $playableTribes[] = 10;
}

$flashSuccess = '';
$flashError   = '';
$executionLog = null;

// ==============================================================================
// POST ACTION HANDLERS
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bot_action'])) {
    $action = trim((string)$_POST['bot_action']);

    // 1. SPAWN BOTS
    if ($action === 'spawn') {
        $count = max(1, min(50, (int)($_POST['count'] ?? 5)));
        $specificTribe = (int)($_POST['tribe'] ?? 0);
        $selectedQuadrant = (int)($_POST['quadrant'] ?? 0);

        $created = 0;
        $spawnList = [];

        for ($i = 1; $i <= $count; $i++) {
            $tribe = ($specificTribe > 0 && in_array($specificTribe, $playableTribes, true))
                ? $specificTribe
                : $playableTribes[array_rand($playableTribes)];

            $tName = $tribeNames[$tribe] ?? 'Warrior';
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
                continue;
            }

            // Flag as bot, realistic bio, protect = 0 so bots can be raided immediately
            $database->query("
                UPDATE " . TB_PREFIX . "users
                SET is_bot = 1, desc1 = '" . $database->escape($bio) . "', desc2 = '[#0]', access = 2, protect = 0
                WHERE id = $uid
            ");

            // Village quadrant
            $kid = ($selectedQuadrant >= 1 && $selectedQuadrant <= 4) ? $selectedQuadrant : rand(1, 4);

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
            $wid = is_array($wid) ? (int)($wid[0] ?? 0) : (int)$wid;

            if (!$wid) {
                continue;
            }

            // Ensure units row exists
            $qUnits = $database->query_return("SELECT vref FROM " . TB_PREFIX . "units WHERE vref = $wid");
            if (empty($qUnits)) {
                $database->query("INSERT INTO " . TB_PREFIX . "units (vref) VALUES ($wid)");
            }

            // Starter resource fields (level 1)
            $database->query("
                UPDATE " . TB_PREFIX . "fdata
                SET f1=1, f2=1, f3=1, f4=1, f5=1, f6=1, f7=1, f8=1, f9=1,
                    f10=1, f11=1, f12=1, f13=1, f14=1, f15=1, f16=1, f17=1, f18=1
                WHERE vref = $wid
            ");

            // Starter resources & warehouse capacity
            $database->query("
                UPDATE " . TB_PREFIX . "vdata
                SET wood = 400, clay = 400, iron = 400, crop = 400,
                    maxstore = 1200, maxcrop = 1200, lastupdate = " . time() . "
                WHERE wref = $wid
            ");

            // Create starter hero for bot if enabled
            if ((!defined('HERO_FROM_START') || HERO_FROM_START) && class_exists('Units')) {
                Units::createStarterHero($uid, $wid, $tribe, $username);
            }

            // Recalculate population
            $pop = BotAI::recountPop($wid);
            $coor = $database->getCoor($wid);

            $spawnList[] = "<b>{$username}</b> ({$tName}) - Desa \"{$villageName}\" (#{$wid} @ {$coor['x']}|{$coor['y']})";
            $created++;
        }

        if ($created > 0) {
            $flashSuccess = "Berhasil membuat <b>$created</b> akun bot baru!<br>" . implode('<br>', array_slice($spawnList, 0, 10));
            if (count($spawnList) > 10) {
                $flashSuccess .= "<br><i>... dan " . (count($spawnList) - 10) . " bot lainnya.</i>";
            }
        } else {
            $flashError = "Gagal membuat bot. Periksa konfigurasi map atau ruang kosong di server.";
        }
    }

    // 2. RUN BOT AI CYCLE (MANUAL TICK)
    elseif ($action === 'run_tick') {
        $report = BotAI::run(true);
        $executionLog = $report;
        if (($report['status'] ?? '') === 'success') {
            $flashSuccess = "Siklus Bot AI berhasil dijalankan! Diproses: <b>" . ($report['bots_total'] ?? 0) . "</b> bot, Aktif melakukan aksi: <b>" . ($report['bots_active'] ?? 0) . "</b> bot.";
        } elseif (($report['status'] ?? '') === 'idle') {
            $flashSuccess = "Siklus dijalankan: Tidak ada akun bot aktif atau bot dalam status siaga.";
        } else {
            $flashError = "Status eksekusi: " . ($report['message'] ?? 'Unknown');
        }
    }

    // 3. DELETE SINGLE BOT
    elseif ($action === 'delete_single') {
        $delUid = (int)($_POST['uid'] ?? 0);
        if ($delUid > 0) {
            $botUser = $database->query_return("
                SELECT u.id, u.username, v.wref
                FROM " . TB_PREFIX . "users u
                LEFT JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
                WHERE u.id = $delUid AND u.access = 2
                  AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
            ");

            if (!empty($botUser)) {
                $bName = $botUser[0]['username'];
                foreach ($botUser as $row) {
                    $wid = (int)($row['wref'] ?? 0);
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
                }
                $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $delUid");
                $database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $delUid");

                $flashSuccess = "Bot <b>{$bName}</b> (UID: {$delUid}) beserta seluruh desanya berhasil dihapus dan lahannya dibebaskan!";
            } else {
                $flashError = "Akun bot dengan UID {$delUid} tidak ditemukan atau bukan akun bot!";
            }
        }
    }

    // 4. CLEAN ALL BOTS
    elseif ($action === 'clean_all') {
        $allBots = $database->query_return("
            SELECT u.id, v.wref
            FROM " . TB_PREFIX . "users u
            LEFT JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
            WHERE u.access = 2
              AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
        ");

        if (!empty($allBots)) {
            $cleanedCount = 0;
            $processedUids = [];
            foreach ($allBots as $b) {
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
                if (!isset($processedUids[$uid])) {
                    $database->query("DELETE FROM " . TB_PREFIX . "hero WHERE uid = $uid");
                    $database->query("DELETE FROM " . TB_PREFIX . "users WHERE id = $uid");
                    $processedUids[$uid] = true;
                    $cleanedCount++;
                }
            }
            $flashSuccess = "Seluruh akun bot (Total: <b>$cleanedCount</b> bot) berhasil dibersihkan dari server!";
        } else {
            $flashSuccess = "Tidak ada akun bot yang perlu dibersihkan.";
        }
    }
    // 5. ORDER ATTACK
    elseif ($action === 'order_attack') {
        $selectedBot = $_POST['target_bot'] ?? '0';
        $targetAlliance = (int)($_POST['target_alliance'] ?? 0);
        $targetInput = trim((string)($_POST['target_input'] ?? ''));
        if ($targetAlliance > 0) {
            $targetInput = 'alliance:' . $targetAlliance;
        }

        $attackType  = (int)($_POST['attack_type'] ?? 4);
        $troopCount  = (int)($_POST['troop_count'] ?? 0);
        $composition = trim((string)($_POST['composition'] ?? 'balanced'));

        $res = BotAI::orderAttack($selectedBot, $targetInput, $attackType, $troopCount, $composition);
        if ($res['status'] === 'success') {
            $reportHtml = "<ul style='margin:6px 0 0 16px; padding:0; max-height:260px; overflow-y:auto;'>";
            foreach ($res['reports'] as $rep) {
                $reportHtml .= "<li style='margin-bottom:4px;'>$rep</li>";
            }
            $reportHtml .= "</ul>";
            $flashSuccess = "🎯 <b>Operasi Militer Bot AI Diluncurkan!</b> " . $res['message'] . $reportHtml;
        } else {
            $flashError = "❌ Gagal meluncurkan serangan bot: " . htmlspecialchars($res['message']);
        }
    }
    // 6. ASSIGN BOTS TO ALLIANCE
    elseif ($action === 'join_alliance') {
        $aid = (int)($_POST['aid'] ?? 0);
        $targetBot = $_POST['target_bot'] ?? 'all_unassigned';
        $botCount = max(0, (int)($_POST['bot_count'] ?? 0));

        $aliCheck = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata WHERE id = $aid");
        if (empty($aliCheck)) {
            $flashError = "Aliansi dengan ID #$aid tidak ditemukan!";
        } else {
            $aliTag = $aliCheck[0]['tag'];
            $aliName = $aliCheck[0]['name'];

            $whereExtra = "AND alliance != $aid";
            if ($targetBot === 'all_unassigned') {
                $whereExtra = "AND (alliance = 0 OR alliance IS NULL)";
            } elseif (strpos($targetBot, 'tribe_') === 0) {
                $trNum = (int)substr($targetBot, 6);
                $whereExtra = "AND tribe = $trNum AND (alliance = 0 OR alliance IS NULL)";
            } elseif ($targetBot !== 'all' && (int)$targetBot > 0) {
                $specUid = (int)$targetBot;
                $whereExtra = "AND id = $specUid";
            }

            $limitClause = ($botCount > 0 && $targetBot !== (string)(int)$targetBot) ? "LIMIT $botCount" : "";

            $eligibleBots = $database->query_return("
                SELECT id, username FROM " . TB_PREFIX . "users
                WHERE access = 2
                  AND (is_bot = 1 OR email LIKE '%@bot.travianz' OR desc1 LIKE '%[#BOT]%' OR desc2 LIKE '%[#BOT]%' OR username LIKE 'Bot_%')
                  $whereExtra
                ORDER BY id ASC
                $limitClause
            ");

            if (empty($eligibleBots)) {
                $flashError = "Tidak ada bot yang memenuhi syarat untuk dimasukkan ke aliansi [{$aliTag}] {$aliName}.";
            } else {
                $joined = 0;
                foreach ($eligibleBots as $b) {
                    $bUid = (int)$b['id'];
                    $database->query("UPDATE " . TB_PREFIX . "users SET alliance = $aid WHERE id = $bUid");
                    $database->query("
                        INSERT INTO " . TB_PREFIX . "ali_permission (uid, alliance, rank, opt1, opt2, opt3, opt4, opt5, opt6, opt7, opt8)
                        VALUES ($bUid, $aid, 'Member', 0, 0, 0, 0, 0, 0, 0, 0)
                        ON DUPLICATE KEY UPDATE alliance = $aid, rank = 'Member'
                    ");
                    $joined++;
                }
                $database->procAllyPop($aid);
                $flashSuccess = "🛡️ Berhasil memasukkan <b>$joined</b> bot ke aliansi <b>[{$aliTag}] {$aliName}</b> (#$aid)!";
            }
        }
    }
    // 7. LEAVE ALLIANCE
    elseif ($action === 'leave_alliance') {
        $targetBot = $_POST['target_bot'] ?? 'all';
        $whereExtra = "AND alliance > 0";
        if ($targetBot !== 'all' && (int)$targetBot > 0) {
            $specUid = (int)$targetBot;
            $whereExtra = "AND id = $specUid AND alliance > 0";
        }

        $botList = $database->query_return("
            SELECT id, username, alliance FROM " . TB_PREFIX . "users
            WHERE access = 2
              AND (is_bot = 1 OR email LIKE '%@bot.travianz' OR desc1 LIKE '%[#BOT]%' OR desc2 LIKE '%[#BOT]%' OR username LIKE 'Bot_%')
              $whereExtra
            ORDER BY id ASC
        ");

        if (empty($botList)) {
            $flashError = "Tidak ada bot dalam aliansi yang ditemukan untuk dikeluarkan.";
        } else {
            $left = 0;
            $affectedAids = [];
            foreach ($botList as $b) {
                $bUid = (int)$b['id'];
                $oldAid = (int)$b['alliance'];
                if ($oldAid > 0) $affectedAids[$oldAid] = true;
                $database->query("UPDATE " . TB_PREFIX . "users SET alliance = 0 WHERE id = $bUid");
                $database->query("DELETE FROM " . TB_PREFIX . "ali_permission WHERE uid = $bUid");
                $left++;
            }
            foreach (array_keys($affectedAids) as $aId) {
                $database->procAllyPop($aId);
            }
            $flashSuccess = "🛡️ Berhasil mengeluarkan <b>$left</b> bot dari aliansi!";
        }
    }
    // 8. CREATE ALLIANCE FOR BOTS (ADMIN CREATION)
    elseif ($action === 'create_alliance') {
        $tag = trim((string)($_POST['tag'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $founderUid = (int)($_POST['founder_uid'] ?? 0);
        $maxMembers = max(10, min(100, (int)($_POST['max_members'] ?? 60)));
        $autoCount = max(0, (int)($_POST['auto_count'] ?? 0));
        $autoTribe = (int)($_POST['auto_tribe'] ?? 0);

        if (empty($tag) || empty($name)) {
            $flashError = "Tag dan Nama Aliansi tidak boleh kosong!";
        } elseif (strlen($tag) > 10) {
            $flashError = "Tag Aliansi maksimal 10 karakter!";
        } elseif (strlen($name) > 30) {
            $flashError = "Nama Aliansi maksimal 30 karakter!";
        } else {
            // Check duplicate tag or name
            $dup = $database->query_return("
                SELECT id, tag, name FROM " . TB_PREFIX . "alidata 
                WHERE tag = '" . $database->escape($tag) . "' OR name = '" . $database->escape($name) . "'
                LIMIT 1
            ");
            if (!empty($dup)) {
                $flashError = "Aliansi dengan Tag [{$dup[0]['tag']}] atau Nama '{$dup[0]['name']}' sudah ada di database!";
            } else {
                // Determine founder bot
                if ($founderUid <= 0) {
                    $whereTribe = ($autoTribe > 0) ? "AND tribe = $autoTribe" : "";
                    $cand = $database->query_return("
                        SELECT id, username FROM " . TB_PREFIX . "users
                        WHERE access = 2
                          AND (is_bot = 1 OR email LIKE '%@bot.travianz' OR desc1 LIKE '%[#BOT]%' OR desc2 LIKE '%[#BOT]%' OR username LIKE 'Bot_%')
                          AND (alliance = 0 OR alliance IS NULL)
                          $whereTribe
                        ORDER BY id ASC LIMIT 1
                    ");
                    if (!empty($cand[0]['id'])) {
                        $founderUid = (int)$cand[0]['id'];
                    } else {
                        // Any bot
                        $anyBot = $database->query_return("
                            SELECT id, username FROM " . TB_PREFIX . "users
                            WHERE access = 2
                              AND (is_bot = 1 OR email LIKE '%@bot.travianz' OR desc1 LIKE '%[#BOT]%' OR desc2 LIKE '%[#BOT]%' OR username LIKE 'Bot_%')
                            ORDER BY id ASC LIMIT 1
                        ");
                        $founderUid = !empty($anyBot[0]['id']) ? (int)$anyBot[0]['id'] : (int)($_SESSION['id'] ?? 0);
                    }
                }

                if ($founderUid <= 0) {
                    $flashError = "Tidak ditemukan akun bot untuk dijadikan pendiri aliansi. Buat bot terlebih dahulu!";
                } else {
                    $fUser = $database->getUserArray($founderUid, 1);
                    $fName = $fUser['username'] ?? 'BotFounder';

                    $aid = $database->createAlliance($tag, $name, $founderUid, $maxMembers);
                    if ($aid) {
                        $database->updateUserField($founderUid, "alliance", $aid, 1);
                        $database->updateUserField($founderUid, "alliance_joined", 0, 1);
                        $database->createAlliPermissions($founderUid, $aid, 'Alliance founder', '1', '1', '1', '1', '1', '1', '1', '1');
                        $database->procAllyPop($aid);

                        $notice = rc_tok('MSG_ALLIANCE_FOUNDED', '<a href="spieler.php?uid=' . $founderUid . '">' . addslashes($fName) . '</a>');
                        $database->insertAlliNotice($aid, $notice);

                        $joinedCount = 1;

                        // Auto join other bots if requested
                        if ($autoCount > 0) {
                            $whereExtra = "AND id != $founderUid AND (alliance = 0 OR alliance IS NULL)";
                            if ($autoTribe > 0) {
                                $whereExtra .= " AND tribe = $autoTribe";
                            }
                            $limitClause = "LIMIT $autoCount";
                            $extraBots = $database->query_return("
                                SELECT id, username FROM " . TB_PREFIX . "users
                                WHERE access = 2
                                  AND (is_bot = 1 OR email LIKE '%@bot.travianz' OR desc1 LIKE '%[#BOT]%' OR desc2 LIKE '%[#BOT]%' OR username LIKE 'Bot_%')
                                  $whereExtra
                                ORDER BY id ASC
                                $limitClause
                            ");

                            if (!empty($extraBots)) {
                                foreach ($extraBots as $eb) {
                                    $ebUid = (int)$eb['id'];
                                    $database->query("UPDATE " . TB_PREFIX . "users SET alliance = $aid WHERE id = $ebUid");
                                    $database->query("
                                        INSERT INTO " . TB_PREFIX . "ali_permission (uid, alliance, rank, opt1, opt2, opt3, opt4, opt5, opt6, opt7, opt8)
                                        VALUES ($ebUid, $aid, 'Member', 0, 0, 0, 0, 0, 0, 0, 0)
                                        ON DUPLICATE KEY UPDATE alliance = $aid, rank = 'Member'
                                    ");
                                    $joinedCount++;
                                }
                                $database->procAllyPop($aid);
                            }
                        }

                        $flashSuccess = "👑 Aliansi baru <b>[{$tag}] {$name}</b> (#{$aid}) berhasil dibuat dengan Pendiri <b>{$fName}</b>! Total <b>{$joinedCount}</b> anggota bot telah tergabung.";
                    } else {
                        $flashError = "Gagal membuat aliansi di database.";
                    }
                }
            }
        }
    }
    // 9. REBALANCE BOT TROOPS
    elseif ($action === 'rebalance_troops') {
        $targetGarrison = max(0, (int)($_POST['target_garrison'] ?? 0));
        $rebalancePop = !empty($_POST['rebalance_pop']) || !isset($_POST['rebalance_pop_submitted']);
        $targetAgeDay = isset($_POST['target_age_day']) ? (int)$_POST['target_age_day'] : 1;
        $result = BotAI::rebalanceBotTroops($targetGarrison, $rebalancePop, $targetAgeDay);
        $ageLabel = $targetAgeDay > 0 ? "Hari ke-$targetAgeDay" : "Otomatis";
        $flashSuccess = "⚖️ <b>Normalisasi Berhasil!</b> Diproses: <b>" . ($result['villages_processed'] ?? 0) . "</b> desa bot (Target Usia: <b>$ageLabel</b>).<br>"
            . "• Pasukan berlebih dipangkas: <b>" . number_format($result['troops_trimmed'] ?? 0) . "</b> unit (Sisa total server: <b>" . number_format($result['total_troops_after'] ?? 0) . "</b> unit).<br>"
            . "• Bangunan & populasi dinormalkan: <b>" . ($result['pop_adjusted_bots'] ?? 0) . "</b> desa bot (Ladang lvl 2, MB lvl 3, Warehouse/Barracks lvl 1, gedung lanjut seperti Stable/Workshop dicopot, pop ~48-56/desa).";
    }
    // 10. SAVE BOT GROWTH & EXPANSION SETTINGS
    elseif ($action === 'save_bot_settings') {
        $maxPop = max(200, min(5000, (int)($_POST['bot_max_pop'] ?? 800)));
        $maxBuildingLvl = max(10, min(20, (int)($_POST['bot_max_building_level'] ?? 15)));
        $maxVillages = max(1, min(5, (int)($_POST['bot_max_villages'] ?? 2)));
        $expansionEnabled = !empty($_POST['bot_expansion_enabled']);
        $paranormalEnabled = !empty($_POST['bot_paranormal_enabled']);
        $troopPopFactor = max(0.3, min(3.5, (float)($_POST['bot_troop_pop_factor'] ?? 1.25)));
        $maxArmy = max(100, min(10000, (int)($_POST['bot_max_army'] ?? 2500)));
        $scoutEnabled = !empty($_POST['bot_scout_enabled']);

        // Alliance mutual defense settings
        $allianceDefenseEnabled = !empty($_POST['bot_alliance_defense_enabled']);
        $defenseRatioActive = max(0.1, min(1.0, (float)($_POST['bot_defense_ratio_active'] ?? 0.75)));
        $defenseRatioRecovery = max(0.1, min(1.0, (float)($_POST['bot_defense_ratio_recovery'] ?? 0.50)));
        $defenseMaxCap = max(50, min(3000, (int)($_POST['bot_defense_max_cap'] ?? 350)));
        $defenseMaxCapRecovery = max(20, min(1500, (int)($_POST['bot_defense_max_cap_recovery'] ?? 150)));
        $maxHelpers = max(1, min(20, (int)($_POST['bot_max_helpers_per_village'] ?? 6)));
        $pureDefOnly = !empty($_POST['bot_defense_pure_def_only']);
        $maxDefDistance = max(20.0, min(500.0, (float)($_POST['bot_max_defense_distance'] ?? 120.0)));

        BotAI::saveConfig([
            'BOT_AI_MAX_POP' => $maxPop,
            'BOT_AI_MAX_BUILDING_LEVEL' => $maxBuildingLvl,
            'BOT_AI_MAX_VILLAGES' => $maxVillages,
            'BOT_AI_EXPANSION_ENABLED' => $expansionEnabled,
            'BOT_AI_PARANORMAL_ENABLED' => $paranormalEnabled,
            'BOT_AI_TROOP_POP_FACTOR' => $troopPopFactor,
            'BOT_AI_MAX_ARMY' => $maxArmy,
            'BOT_AI_SCOUT_ENABLED' => $scoutEnabled,
            'BOT_AI_ALLIANCE_DEFENSE_ENABLED' => $allianceDefenseEnabled,
            'BOT_AI_DEFENSE_RATIO_ACTIVE' => $defenseRatioActive,
            'BOT_AI_DEFENSE_RATIO_RECOVERY' => $defenseRatioRecovery,
            'BOT_AI_DEFENSE_MAX_CAP' => $defenseMaxCap,
            'BOT_AI_DEFENSE_MAX_CAP_RECOVERY' => $defenseMaxCapRecovery,
            'BOT_AI_MAX_HELPERS_PER_VILLAGE' => $maxHelpers,
            'BOT_AI_DEFENSE_PURE_DEF_ONLY' => $pureDefOnly,
            'BOT_AI_MAX_DEFENSE_DISTANCE' => $maxDefDistance,
        ]);

        $flashSuccess = "⚙️ <b>Pengaturan Bot AI Berhasil Disimpan!</b><br>"
            . "• Batas Populasi (Cap Pop): <b>$maxPop</b> pop/desa<br>"
            . "• Batas Level Bangunan: <b>Level $maxBuildingLvl</b><br>"
            . "• Rasio Pasukan: <b>" . ($troopPopFactor * 100) . "%</b> dari populasi desa (Maks: <b>$maxArmy</b> unit/desa)<br>"
            . "• Bantuan Bertahan Aliansi: <b>" . ($allianceDefenseEnabled ? "Aktif (Solid & Tanggap)" : "Nonaktif") . "</b><br>"
            . "• Mobilisasi saat Kawan Diserang: <b>" . ($defenseRatioActive * 100) . "%</b> pasukan cadangan (Maks <b>$defenseMaxCap</b> unit/bot, Kuota: <b>$maxHelpers</b> bot penolong/desa)<br>"
            . "• Filter Unit Pertahanan Murni: <b>" . ($pureDefOnly ? "Aktif (Hanya spesialis defense; cegah Clubswinger/Axeman mati sia-sia)" : "Nonaktif") . "</b><br>"
            . "• Unit Scout (Pengintai): <b>" . ($scoutEnabled ? "Aktif (Latih di Barak/Stable, Anti-Spionase & Patroli Intai)" : "Nonaktif") . "</b><br>"
            . "• Ekspansi Desa ke-2: <b>" . ($expansionEnabled ? "Aktif (Maks $maxVillages Desa/Bot)" : "Nonaktif") . "</b> (Berjalan secara natural via Padepokan Lvl 10, pelatihan 3 Pionir, CP, dan perjalanan perintis).<br>"
            . "• Sanggar Paranormal Nusantara: <b>" . ($paranormalEnabled ? "Aktif (Natural & Bertahap)" : "Nonaktif") . "</b> (Konstruksi GID 51 di slot 38, pemanggilan Pocong/Kunti/Genderuwo & ritual Tuyul sesuai sumber daya).";
    }
}

// ==============================================================================
// FETCH CURRENT ACTIVE BOTS & STATS
// ==============================================================================
$botConfig = BotAI::getConfig();
$currentMaxPop = (int)BotAI::getConfig('BOT_AI_MAX_POP', 800);
$currentMaxLvl = (int)BotAI::getConfig('BOT_AI_MAX_BUILDING_LEVEL', 15);
$currentMaxVil = (int)BotAI::getConfig('BOT_AI_MAX_VILLAGES', 2);
$currentExpEnabled = !empty(BotAI::getConfig('BOT_AI_EXPANSION_ENABLED', true));
$currentParanormalEnabled = !empty(BotAI::getConfig('BOT_AI_PARANORMAL_ENABLED', true));
$currentTroopFactor = (float)BotAI::getConfig('BOT_AI_TROOP_POP_FACTOR', 1.25);
$currentMaxArmy = (int)BotAI::getConfig('BOT_AI_MAX_ARMY', 2500);
$currentScoutEnabled = !empty(BotAI::getConfig('BOT_AI_SCOUT_ENABLED', true));

// Current Alliance Defense Configurations
$currentAllianceDefEnabled = !empty(BotAI::getConfig('BOT_AI_ALLIANCE_DEFENSE_ENABLED', true));
$currentDefRatioActive = (float)BotAI::getConfig('BOT_AI_DEFENSE_RATIO_ACTIVE', 0.75);
$currentDefRatioRecovery = (float)BotAI::getConfig('BOT_AI_DEFENSE_RATIO_RECOVERY', 0.50);
$currentDefMaxCap = (int)BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP', 350);
$currentDefMaxCapRecovery = (int)BotAI::getConfig('BOT_AI_DEFENSE_MAX_CAP_RECOVERY', 150);
$currentMaxHelpers = (int)BotAI::getConfig('BOT_AI_MAX_HELPERS_PER_VILLAGE', 6);
$currentPureDefOnly = !empty(BotAI::getConfig('BOT_AI_DEFENSE_PURE_DEF_ONLY', true));
$currentMaxDefDist = (float)BotAI::getConfig('BOT_AI_MAX_DEFENSE_DISTANCE', 120.0);

$botsQuery = "
    SELECT u.id, u.username, u.tribe, u.regtime, u.desc1, u.alliance,
           v.wref, v.name as vname, v.pop, v.wood, v.clay, v.iron, v.crop, v.maxstore, v.maxcrop,
           w.x, w.y,
           a.tag as ali_tag, a.name as ali_name
    FROM " . TB_PREFIX . "users u
    LEFT JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
    LEFT JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
    LEFT JOIN " . TB_PREFIX . "alidata a ON u.alliance = a.id
    WHERE u.access = 2
      AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
    ORDER BY u.id DESC
";
$botsList = $database->query_return($botsQuery);
$existingAlliances = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata ORDER BY name ASC");

$totalBots = 0;
$totalVillages = 0;
$totalPop = 0;
$totalTroops = 0;
$uniqueUids = [];

if (!empty($botsList)) {
    $vrefs = [];
    foreach ($botsList as $b) {
        if (!isset($uniqueUids[$b['id']])) {
            $uniqueUids[$b['id']] = true;
            $totalBots++;
        }
        if (!empty($b['wref'])) {
            $totalVillages++;
            $totalPop += (int)$b['pop'];
            $vrefs[] = (int)$b['wref'];
        }
    }

    // Sum troop counts and unit details across bot villages
    if (!empty($vrefs)) {
        $vrefsList = implode(',', $vrefs);
        $unitsRows = $database->query_return("
            SELECT *
            FROM " . TB_PREFIX . "units
            WHERE vref IN ($vrefsList)
        ");
        $unitsMap = [];
        $unitsDetailsMap = [];
        if (!empty($unitsRows)) {
            foreach ($unitsRows as $ur) {
                $vref = (int)$ur['vref'];
                $total = 0;
                $details = [];
                for ($i = 1; $i <= 100; $i++) {
                    $cnt = (int)($ur['u' . $i] ?? 0);
                    if ($cnt > 0) {
                        $uName = defined('U' . $i) ? constant('U' . $i) : ('Unit ' . $i);
                        $details[] = [
                            'id' => $i,
                            'name' => $uName,
                            'count' => $cnt,
                        ];
                        $total += $cnt;
                    }
                }
                if (!empty($ur['hero']) && (int)$ur['hero'] > 0) {
                    $cnt = (int)$ur['hero'];
                    $hName = defined('U0') ? constant('U0') : 'Hero';
                    $details[] = [
                        'id' => 'hero',
                        'name' => $hName,
                        'count' => $cnt,
                    ];
                    $total += $cnt;
                }
                $unitsMap[$vref] = $total;
                $unitsDetailsMap[$vref] = $details;
                $totalTroops += $total;
            }
        }
    }
}

// Protocol & host detection for Hostinger Cron URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cronKey = defined('CRON_KEY') ? CRON_KEY : 'secret_cron_key';
$cronHttpUrl = $protocol . $host . '/cron.php?once=1&key=' . $cronKey;
?>

<style>
/* Modern Dark ACP Theme for Bot AI Manager */
.botm-wrap { color:#e2e8f0; font-family:Verdana,Arial,sans-serif; font-size:12px; padding:6px 4px 30px; }
.botm-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:16px; border-bottom:1px solid #1f2937; padding-bottom:14px; gap:12px; }
.botm-header h2 { font-size:20px; margin:0 0 4px; color:#fff; display:flex; align-items:center; gap:8px; }
.botm-header h2 span { color:#22c55e; }
.botm-header p { color:#94a3b8; font-size:11px; margin:0; }

/* Alerts */
.botm-alert { padding:12px 16px; border-radius:8px; font-size:12px; margin-bottom:16px; line-height:1.5; }
.botm-alert-success { background:#064e3b; border:1px solid #059669; color:#a7f3d0; }
.botm-alert-error { background:#7f1d1d; border:1px solid #dc2626; color:#fecaca; }

/* Stat Cards */
.botm-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:#0f172a; border:1px solid #1e293b; border-radius:10px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.25); }
.stat-card .stat-label { font-size:10px; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:6px; display:flex; align-items:center; gap:5px; }
.stat-card .stat-val { font-size:22px; font-weight:700; color:#f8fafc; }
.stat-card .stat-sub { font-size:10px; color:#64748b; margin-top:4px; }
.stat-card.green .stat-val { color:#4ade80; }
.stat-card.amber .stat-val { color:#fbbf24; }
.stat-card.cyan .stat-val { color:#38bdf8; }
.stat-card.purple .stat-val { color:#c084fc; }

/* Two-column Action Grid */
.botm-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; }
@media (max-width:960px) { .botm-grid { grid-template-columns:1fr; } }

.botm-panel { background:#0b1220; border:1px solid #1f2937; border-radius:10px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,.2); }
.botm-panel h3 { font-size:14px; font-weight:700; color:#f1f5f9; margin:0 0 8px; display:flex; align-items:center; gap:8px; }
.botm-panel p.desc { color:#94a3b8; font-size:11px; margin:0 0 14px; line-height:1.4; }

/* Form Controls */
.botm-form-group { margin-bottom:12px; }
.botm-form-group label { display:block; font-size:11px; font-weight:600; color:#cbd5e1; margin-bottom:5px; }
.botm-form-control { width:100%; box-sizing:border-box; background:#0f172a; border:1px solid #334155; border-radius:6px; color:#f8fafc; padding:8px 10px; font-size:12px; outline:none; }
.botm-form-control:focus { border-color:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.2); }

.quick-counts { display:flex; gap:6px; margin-top:6px; }
.quick-btn { background:#1e293b; border:1px solid #334155; color:#cbd5e1; padding:3px 10px; border-radius:4px; font-size:10px; cursor:pointer; }
.quick-btn:hover { background:#334155; color:#fff; }

.botm-checkbox { display:flex; align-items:center; gap:8px; font-size:11px; color:#94a3b8; margin-top:8px; }

/* Buttons */
.btn-primary { background:#16a34a; border:0; color:#fff; font-weight:700; font-size:13px; padding:10px 18px; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background .15s; }
.btn-primary:hover { background:#15803d; }
.btn-amber { background:#d97706; border:0; color:#fff; font-weight:700; font-size:13px; padding:10px 18px; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
.btn-amber:hover { background:#b45309; }
.btn-danger { background:#dc2626; border:0; color:#fff; font-weight:700; font-size:11px; padding:5px 10px; border-radius:5px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
.btn-danger:hover { background:#b91c1c; }
.btn-secondary { background:#1e293b; border:1px solid #334155; color:#cbd5e1; font-weight:600; font-size:11px; padding:5px 10px; border-radius:5px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
.btn-secondary:hover { background:#334155; color:#fff; }

/* Execution Log Console */
.log-box { background:#030712; border:1px solid #1e293b; border-radius:8px; padding:14px; margin-bottom:20px; font-family:'Courier New',Courier,monospace; font-size:11px; max-height:320px; overflow-y:auto; }
.log-entry { margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid #111827; }
.log-bot { color:#38bdf8; font-weight:bold; }
.log-action { color:#94a3b8; margin-left:14px; }
.badge-build { background:#1e3a5f; color:#93c5fd; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; }
.badge-train { background:#064e3b; color:#86efac; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; }
.badge-attack { background:#7f1d1d; color:#fca5a5; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; }
.badge-defense { background:#0284c7; color:#e0f2fe; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; }
.badge-hero { background:#7c2d12; color:#fed7aa; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; }
.badge-revenge { background:#b91c1c; color:#ffffff; padding:1px 6px; border-radius:4px; font-size:9px; font-weight:bold; box-shadow:0 0 6px rgba(185,28,28,0.6); }

/* Table Directory */
.botm-table-panel { background:#0b1220; border:1px solid #1f2937; border-radius:10px; padding:16px; margin-bottom:20px; }
.table-head-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px; }
.table-head-bar h3 { margin:0; font-size:14px; color:#f8fafc; }
.search-input { background:#0f172a; border:1px solid #334155; border-radius:6px; color:#e2e8f0; padding:6px 12px; font-size:11px; width:220px; }

.botm-table { width:100%; border-collapse:collapse; background:#0f172a; border:1px solid #1f2937; border-radius:8px; overflow:hidden; }
.botm-table th { background:#111827; text-align:left; padding:9px 10px; font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; border-bottom:1px solid #1f2937; }
.botm-table td { padding:8px 10px; border-bottom:1px solid #14203a; vertical-align:middle; font-size:11px; }
.botm-table tr:hover td { background:#132038; }

.tribe-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:bold; background:#1e293b; color:#cbd5e1; }
.res-pill { font-size:10px; color:#94a3b8; font-variant-numeric:tabular-nums; }

/* Hostinger Cron Box */
.cron-card { background:linear-gradient(135deg, #0b1329 0%, #0d1e38 100%); border:1px solid #1e3a8a; border-radius:10px; padding:18px; margin-bottom:20px; }
.cron-card h3 { color:#60a5fa; font-size:14px; margin:0 0 6px; display:flex; align-items:center; gap:8px; }
.cron-card p { font-size:11px; color:#cbd5e1; margin:0 0 10px; line-height:1.5; }
.cron-code { background:#030712; border:1px solid #1e293b; border-radius:6px; padding:10px 12px; color:#38bdf8; font-family:monospace; font-size:11px; word-break:break-all; display:flex; justify-content:space-between; align-items:center; }
.copy-btn { background:#1e293b; border:1px solid #334155; color:#cbd5e1; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:10px; }
.copy-btn:hover { background:#334155; color:#fff; }
</style>

<div class="botm-wrap">

    <!-- Header Section -->
    <div class="botm-header">
        <div>
            <h2>🤖 <span>Autonomous Bot AI</span> Manager</h2>
            <p>Kelola bot mandiri dengan perkembangan bangunan organik, pelatihan militer multi-tier, dan penyerangan raid aktif — 100% via Browser tanpa perlu terminal Hostinger.</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <a href="admin.php?p=addTroops" class="btn-secondary" style="padding:10px 14px; font-size:12px; font-weight:700; background:#1e293b; color:#38bdf8; border:1px solid #0284c7;" title="Buka Admin Troop Manager untuk tambah atau kompensasi pasukan pemain/desa">
                ⚔️ Troop Manager (Kompensasi)
            </a>
            <button type="button" class="btn-secondary" style="padding:10px 14px; font-size:12px; font-weight:700; background:#3b1e2b; color:#f472b6; border:1px solid #be185d;" onclick="openRebalanceModal()" title="Pangkas pasukan bot berlebih dan normalkan bangunan/populasi agar sesuai pertumbuhan alami">
                ⚖️ Normalisasi Pasukan & Bangunan Bot
            </button>
            <form method="post" action="admin.php?p=botManager" style="margin:0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bot_action" value="run_tick">
                <button type="submit" class="btn-amber" title="Jalankan perkembangan dan penyerangan sekarang">
                    ⚡ Jalankan 1 Siklus AI Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="botm-alert botm-alert-success"><?php echo $flashSuccess; ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="botm-alert botm-alert-error"><?php echo $flashError; ?></div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="botm-stats">
        <div class="stat-card green">
            <div class="stat-label">🤖 Total Akun Bot</div>
            <div class="stat-val"><?php echo number_format($totalBots); ?></div>
            <div class="stat-sub">Pemain simulasi aktif</div>
        </div>
        <div class="stat-card cyan">
            <div class="stat-label">🏰 Total Desa Bot</div>
            <div class="stat-val"><?php echo number_format($totalVillages); ?></div>
            <div class="stat-sub">Desa berkembang di peta</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">👥 Total Populasi</div>
            <div class="stat-val"><?php echo number_format($totalPop); ?></div>
            <div class="stat-sub">Rata-rata: <?php echo $totalVillages > 0 ? round($totalPop / $totalVillages) : 0; ?> pop/desa</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-label">⚔️ Total Pasukan Bot</div>
            <div class="stat-val"><?php echo number_format($totalTroops); ?></div>
            <div class="stat-sub">Garnisun & pasukan serang</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">⏱️ Status Engine</div>
            <div class="stat-val" style="font-size:16px; color:#22c55e;">AKTIF</div>
            <div class="stat-sub">Interval: <?php echo defined('BOT_AI_INTERVAL') ? BOT_AI_INTERVAL : 120; ?>s | Tribe: Nusantara & Klasik</div>
        </div>
    </div>

    <!-- Live Execution Logs (Shown if tick was executed) -->
    <?php if ($executionLog !== null): ?>
        <div class="botm-panel" style="margin-bottom:20px; border-color:#0284c7;">
            <h3>📊 Laporan Eksekusi Siklus Bot Terakhir</h3>
            <div class="log-box">
                <?php if (!empty($executionLog['logs'])): ?>
                    <?php foreach ($executionLog['logs'] as $bUser => $actions): ?>
                        <div class="log-entry">
                            <span class="log-bot">▶ <?php echo htmlspecialchars($bUser); ?>:</span>
                            <?php foreach ($actions as $act): ?>
                                <?php
                                    $badge = '<span class="badge-build">UPGRADE</span>';
                                    if (stripos($act, 'Train') !== false) {
                                        $badge = '<span class="badge-train">TRAIN</span>';
                                    } elseif (stripos($act, 'Defense') !== false || stripos($act, 'Bantuan') !== false) {
                                        $badge = '<span class="badge-defense">DEFEND</span>';
                                    } elseif (stripos($act, 'Revenge') !== false || stripos($act, 'Balas Dendam') !== false || stripos($act, 'Retaliation') !== false) {
                                        $badge = '<span class="badge-revenge">REVENGE</span>';
                                    } elseif (stripos($act, 'Attack') !== false) {
                                        $badge = '<span class="badge-attack">RAID</span>';
                                    } elseif (stripos($act, 'Hero') !== false) {
                                        $badge = '<span class="badge-hero">HERO</span>';
                                    }
                                ?>
                                <div class="log-action"><?php echo $badge; ?> <?php echo htmlspecialchars($act); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:#64748b; padding:10px;">Semua bot dalam status menunggu batas interval atau kapasitas antrian tercapai. Tidak ada upgrade baru di detik ini.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two Action Columns: Spawn Bots & Hostinger Automation -->
    <div class="botm-grid">

        <!-- Panel 1: Spawn Bot -->
        <div class="botm-panel">
            <h3>🚀 Tambah Bot Baru (Spawn Bot AI)</h3>
            <p class="desc">Buat akun bot realistis lengkap dengan nama gamer natural, bio profil, ladang sumber daya level 1, dan starter hero.</p>

            <form method="post" action="admin.php?p=botManager">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bot_action" value="spawn">

                <div class="botm-form-group">
                    <label>Jumlah Bot yang Ingin Dibuat (1 - 50):</label>
                    <input type="number" name="count" id="spawn_count" class="botm-form-control" value="5" min="1" max="50" required>
                    <div class="quick-counts">
                        <button type="button" class="quick-btn" onclick="setCount(1)">1 Bot</button>
                        <button type="button" class="quick-btn" onclick="setCount(5)">5 Bot</button>
                        <button type="button" class="quick-btn" onclick="setCount(10)">10 Bot</button>
                        <button type="button" class="quick-btn" onclick="setCount(20)">20 Bot</button>
                    </div>
                </div>

                <div class="botm-form-group">
                    <label>Pilihan Suku (Tribe):</label>
                    <select name="tribe" class="botm-form-control">
                        <option value="0">🎲 Acak (Distribusi Otomatis Semua Suku)</option>
                        <option value="1">🏛️ Romawi (Roman)</option>
                        <option value="2">⚔️ Teuton (Offensive/Farm)</option>
                        <option value="3">🛡️ Galia (Gaul - Defensive/Trapper)</option>
                        <option value="6">🏹 Hun (Kavaleri Cepat)</option>
                        <option value="7">🏺 Mesir (Produksi Ekonomi Tinggi)</option>
                        <option value="8">🛡️ Spartan (Pertahanan Elit)</option>
                        <option value="9">🪓 Viking (Penyerbu Pesisir)</option>
                        <?php if (defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA): ?>
                        <option value="10" selected>👑 Nusantara (Kerajaan Majapahit / Pendekar)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Wilayah / Kuadran Peta:</label>
                    <select name="quadrant" class="botm-form-control">
                        <option value="0">🌍 Acak di Seluruh Dunia (Rekomendasi)</option>
                        <option value="1">Barat Laut (-|+)</option>
                        <option value="2">Timur Laut (+|+)</option>
                        <option value="3">Barat Daya (-|-)</option>
                        <option value="4">Tenggara (+|-)</option>
                    </select>
                </div>

                <div style="background:#0f172a; padding:10px; border-radius:6px; margin-bottom:14px; border:1px solid #1e293b;">
                    <div class="botm-checkbox">
                        <input type="checkbox" checked disabled>
                        <span>Nama akun natural & Bio profil manusiawi (NameGenerator)</span>
                    </div>
                    <div class="botm-checkbox">
                        <input type="checkbox" checked disabled>
                        <span>Bangun langsung seluruh 18 Ladang Sumber Daya Level 1</span>
                    </div>
                    <div class="botm-checkbox">
                        <input type="checkbox" checked disabled>
                        <span>Buat Hero Awal otomatis (Starter Hero)</span>
                    </div>
                    <div class="botm-checkbox">
                        <input type="checkbox" checked disabled>
                        <span>Bebas Proteksi Pemula (Dapat di-raid langsung oleh pemain)</span>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
                    🚀 Spawn Bot Sekarang
                </button>
            </form>
        </div>

        <!-- Panel 2: Hostinger Automation & Quick Controls -->
        <div>
            <!-- Hostinger Cron Guide Box -->
            <div class="cron-card">
                <h3>⏰ Solusi Otomatisasi Hostinger (Tanpa SSH / Terminal)</h3>
                <p>
                    Karena Anda tidak memiliki akses terminal di Hostinger, Anda dapat mengaktifkan bot agar berjalan <b>24 Jam Non-Stop secara otomatis</b> menggunakan fitur <b>Cron Jobs</b> di hPanel Hostinger:
                </p>

                <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;">
                    <b>Langkah Mudah di Hostinger:</b><br>
                    1. Masuk ke <b>hPanel Hostinger</b> > Buka menu <b>Cron Jobs</b> (Tingkat Lanjut).<br>
                    2. Pilih jenis <b>Kustom (Custom)</b> atau <b>PHP</b>.<br>
                    3. Masukkan perintah berikut (setiap 5 atau 10 menit):
                </div>

                <div class="cron-code" id="cronCodeBox">
                    <span id="cronCommandText">wget -q -O - "<?php echo htmlspecialchars($cronHttpUrl); ?>" >/dev/null 2>&1</span>
                    <button type="button" class="copy-btn" onclick="copyCronCmd()">Salin</button>
                </div>

                <p style="margin-top:10px; font-size:10px; color:#64748b;">
                    💡 <i>Catatan: Selain Cron, game TravianZ juga otomatis menjalankan Bot AI setiap kali ada pemain aktif yang membuka halaman game (Background Automation Engine).</i>
                </p>

                <div style="margin-top:12px;">
                    <a href="<?php echo htmlspecialchars($cronHttpUrl); ?>" target="_blank" class="btn-secondary">
                        🔗 Tes Jalankan URL Cron di Tab Baru
                    </a>
                </div>
            </div>

            <!-- Danger Zone / Clean All -->
            <div class="botm-panel" style="border-color:#7f1d1d;">
                <h3 style="color:#ef4444;">⚠️ Zona Bahaya: Hapus Semua Bot</h3>
                <p class="desc">Hapus seluruh akun bot dan kembalikan koordinat lahan di peta menjadi kosong (unoccupied).</p>

                <form method="post" action="admin.php?p=botManager" onsubmit="return confirm('PERINGATAN: Anda yakin ingin menghapus SEMUA akun bot? Seluruh desa bot dan pasukan akan dihapus permanen.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="bot_action" value="clean_all">
                    <button type="submit" class="btn-danger" style="padding:10px 16px;">
                        🗑️ Hapus Seluruh Bot (Reset Sistem Bot)
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Panel: Pengaturan Pertumbuhan Alami & Ekspansi Desa ke-2 -->
    <div class="botm-panel" style="border-color:#10b981; background:linear-gradient(135deg, #064e3b 0%, #0b1220 100%); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h3 style="color:#34d399; margin:0;">⚙️ Pengaturan Pertumbuhan Alami &amp; Ekspansi Desa ke-2 (Bot Growth &amp; Expansion)</h3>
            <span style="background:#065f46; color:#a7f3d0; border:1px solid #10b981; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">
                🌱 Natural Progression Engine
            </span>
        </div>
        <p class="desc" style="margin-top:6px;">
            Admin dapat mengatur batas maksimal populasi per desa (mengganti batas lama 445 pop) dan mengaktifkan kemampuan bot AI untuk mendirikan <b>Desa ke-2</b> secara bertahap dan <b>100% natural</b> (membangun Padepokan level 10, melatih 3 pionir dengan sumber daya &amp; waktu antrian asli, mengumpulkan Culture Points/CP yang cukup, membawa 750 perbekalan, dan berjalan ke lahan baru).
        </p>

        <form method="post" action="admin.php?p=botManager">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="bot_action" value="save_bot_settings">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-bottom:14px;">
                <div class="botm-form-group">
                    <label>Batas Populasi Maksimal per Desa (Cap Pop):</label>
                    <input type="number" name="bot_max_pop" id="bot_max_pop_input" class="botm-form-control" value="<?php echo $currentMaxPop; ?>" min="200" max="5000" required>
                    <div class="quick-counts" style="margin-top:4px;">
                        <button type="button" class="quick-btn" onclick="document.getElementById('bot_max_pop_input').value=450">450 Pop (Klasik)</button>
                        <button type="button" class="quick-btn" onclick="document.getElementById('bot_max_pop_input').value=800">800 Pop (Standar)</button>
                        <button type="button" class="quick-btn" onclick="document.getElementById('bot_max_pop_input').value=1200">1200 Pop (Besar)</button>
                        <button type="button" class="quick-btn" onclick="document.getElementById('bot_max_pop_input').value=2000">2000 Pop (Metropolis)</button>
                    </div>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        💡 <b>Sebelumnya mentok di 445 pop</b> karena formula lama dibatasi. Kini bot dapat membangun gedung lanjutan: Penggilingan Gandum, Toko Roti, Penggergajian, Pabrik Bata, Pabrik Besi, Balai Kota, dan Balai Prajurit lanjutan.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Batas Level Bangunan Maksimal:</label>
                    <select name="bot_max_building_level" class="botm-form-control">
                        <?php for ($lvl = 10; $lvl <= 20; $lvl++): ?>
                            <option value="<?php echo $lvl; ?>" <?php echo $currentMaxLvl === $lvl ? 'selected' : ''; ?>>
                                Level <?php echo $lvl; ?> <?php echo $lvl === 15 ? '(Rekomendasi)' : ($lvl === 20 ? '(Maksimal Game)' : ''); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        Level maksimal untuk ladang dan bangunan infrastruktur desa bot.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Batas Maksimal Desa per Akun Bot:</label>
                    <select name="bot_max_villages" class="botm-form-control">
                        <option value="1" <?php echo $currentMaxVil === 1 ? 'selected' : ''; ?>>1 Desa (Hanya Ibukota Saja)</option>
                        <option value="2" <?php echo $currentMaxVil === 2 ? 'selected' : ''; ?>>2 Desa (Ibukota + 1 Desa Baru - Rekomendasi)</option>
                        <option value="3" <?php echo $currentMaxVil === 3 ? 'selected' : ''; ?>>3 Desa</option>
                        <option value="4" <?php echo $currentMaxVil === 4 ? 'selected' : ''; ?>>4 Desa</option>
                        <option value="5" <?php echo $currentMaxVil === 5 ? 'selected' : ''; ?>>5 Desa</option>
                    </select>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        Menentukan berapa desa yang boleh didirikan oleh satu akun bot.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Status Fitur Ekspansi Alami:</label>
                    <div style="background:#0f172a; padding:10px 12px; border-radius:6px; border:1px solid #1e293b; margin-top:2px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#f8fafc; font-weight:bold; font-size:12px;">
                            <input type="checkbox" name="bot_expansion_enabled" value="1" <?php echo $currentExpEnabled ? 'checked' : ''; ?> style="width:16px; height:16px; cursor:pointer;">
                            <span>Aktifkan Ekspansi Desa Baru (Desa ke-2)</span>
                        </label>
                        <span style="font-size:10px; color:#6ee7b7; display:block; margin-top:4px;">
                            Jika dicentang, bot yang memenuhi syarat akan mendirikan desa baru secara otomatis dan natural.
                        </span>
                    </div>
                </div>

                <div class="botm-form-group">
                    <label>Rasio Pasukan terhadap Populasi Desa:</label>
                    <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#38bdf8;">
                        📌 <b>Settingan Aktif Saat Ini:</b> <?php echo ($currentTroopFactor * 100); ?>% dari Populasi (Pop 800 ≈ <?php echo number_format(round(800 * $currentTroopFactor)); ?> Pasukan)
                    </div>
                    <select name="bot_troop_pop_factor" class="botm-form-control">
                        <option value="0.5" <?php echo abs($currentTroopFactor - 0.5) < 0.05 ? 'selected' : ''; ?>>50% dari Populasi (Ringan)</option>
                        <option value="1.0" <?php echo abs($currentTroopFactor - 1.0) < 0.05 ? 'selected' : ''; ?>>100% dari Populasi (Seimbang - 1:1)</option>
                        <option value="1.25" <?php echo abs($currentTroopFactor - 1.25) < 0.05 ? 'selected' : ''; ?>>125% dari Populasi (Klasik)</option>
                        <option value="1.5" <?php echo abs($currentTroopFactor - 1.5) < 0.05 ? 'selected' : ''; ?>>150% dari Populasi (Kuat)</option>
                        <option value="1.75" <?php echo abs($currentTroopFactor - 1.75) < 0.05 ? 'selected' : ''; ?>>175% dari Populasi (Rekomendasi - Solid)</option>
                        <option value="2.0" <?php echo abs($currentTroopFactor - 2.0) < 0.05 ? 'selected' : ''; ?>>200% dari Populasi (Militer Penuh - Pop 800 ≈ 1.600 Pasukan)</option>
                        <option value="2.5" <?php echo abs($currentTroopFactor - 2.5) < 0.05 ? 'selected' : ''; ?>>250% dari Populasi (Sangat Tangguh)</option>
                        <option value="3.0" <?php echo abs($currentTroopFactor - 3.0) < 0.05 ? 'selected' : ''; ?>>300% dari Populasi (Super Militer)</option>
                    </select>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        Menentukan kapasitas total pasukan bot sebanding dengan ukuran populasinya.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Batas Maksimal Total Pasukan per Desa (Cap Army):</label>
                    <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#38bdf8;">
                        📌 <b>Settingan Aktif Saat Ini:</b> Maksimal <?php echo number_format($currentMaxArmy); ?> Unit per Desa
                    </div>
                    <input type="number" name="bot_max_army" class="botm-form-control" value="<?php echo $currentMaxArmy; ?>" min="100" max="10000" required>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        Plafon tertinggi total pasukan (garnisun + serang). Rekomendasi: 2.500 - 4.000 unit.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Unit Scout (Pengintai) &amp; Patroli Intai:</label>
                    <div style="background:#0f172a; padding:10px 12px; border-radius:6px; border:1px solid #1e293b; margin-top:2px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#f8fafc; font-weight:bold; font-size:12px;">
                            <input type="checkbox" name="bot_scout_enabled" value="1" <?php echo $currentScoutEnabled ? 'checked' : ''; ?> style="width:16px; height:16px; cursor:pointer;">
                            <span>Aktifkan Pelatihan &amp; Penggunaan Scout (Manusiawi)</span>
                        </label>
                        <span style="font-size:10px; color:#38bdf8; display:block; margin-top:4px;">
                            📌 <b>Saat ini:</b> <?php echo $currentScoutEnabled ? '🟢 AKTIF (Melatih scout di Barak/Stable, patroli & intai)' : '🔴 Nonaktif'; ?>
                        </span>
                    </div>
                </div>

                <div class="botm-form-group">
                    <label>Sanggar Paranormal (Suku Nusantara):</label>
                    <div style="background:#0f172a; padding:10px 12px; border-radius:6px; border:1px solid #1e293b; margin-top:2px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#f8fafc; font-weight:bold; font-size:12px;">
                            <input type="checkbox" name="bot_paranormal_enabled" value="1" <?php echo $currentParanormalEnabled ? 'checked' : ''; ?> style="width:16px; height:16px; cursor:pointer;">
                            <span>Aktifkan Sanggar Paranormal &amp; Khodam Nusantara</span>
                        </label>
                        <span style="font-size:10px; color:#c084fc; display:block; margin-top:4px;">
                            📌 <b>Saat ini:</b> <?php echo $currentParanormalEnabled ? '🟢 AKTIF (Bangun GID 51 di slot 38 & pemanggilan bertahap)' : '🔴 Nonaktif'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Panel Khusus: Bantuan Pertahanan Aliansi (Alliance Defense & Reinforcements) -->
            <div style="background:linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); border:1px solid #6366f1; border-radius:8px; padding:16px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px; border-bottom:1px solid #312e81; padding-bottom:10px;">
                    <div>
                        <h4 style="margin:0; color:#818cf8; font-size:14px; display:flex; align-items:center; gap:6px;">
                            🛡️ Pengaturan Bantuan Pertahanan Aliansi (Alliance Mutual Defense &amp; Reinforcements)
                        </h4>
                        <span style="font-size:11px; color:#c7d2fe;">
                            Atur seberapa tanggap, seberapa banyak, dan jenis pasukan apa yang dikirim bot saat kawan sealinsinya diserang musuh.
                        </span>
                    </div>
                    <span style="background:<?php echo $currentAllianceDefEnabled ? '#065f46' : '#7f1d1d'; ?>; color:<?php echo $currentAllianceDefEnabled ? '#a7f3d0' : '#fecaca'; ?>; border:1px solid <?php echo $currentAllianceDefEnabled ? '#10b981' : '#ef4444'; ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">
                        <?php echo $currentAllianceDefEnabled ? '🟢 Status: Siaga Pertahanan Aktif' : '🔴 Status: Bantuan Nonaktif'; ?>
                    </span>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
                    <!-- Toggle Feature -->
                    <div class="botm-form-group">
                        <label>Status Fitur Bantuan Aliansi:</label>
                        <div style="background:#0f172a; padding:10px 12px; border-radius:6px; border:1px solid #1e293b; margin-top:2px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#f8fafc; font-weight:bold; font-size:12px;">
                                <input type="checkbox" name="bot_alliance_defense_enabled" value="1" <?php echo $currentAllianceDefEnabled ? 'checked' : ''; ?> style="width:16px; height:16px; cursor:pointer;">
                                <span>Aktifkan Bala Bantuan Aliansi Otomatis</span>
                            </label>
                            <span style="font-size:10px; color:#818cf8; display:block; margin-top:4px;">
                                📌 <b>Saat ini:</b> <?php echo $currentAllianceDefEnabled ? '🟢 Bot saling membantu saat ada serangan' : '🔴 Bantuan dimatikan'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Filter Pure Defense -->
                    <div class="botm-form-group">
                        <label>Filter Spesialis Pertahanan Murni:</label>
                        <div style="background:#0f172a; padding:10px 12px; border-radius:6px; border:1px solid #1e293b; margin-top:2px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#f8fafc; font-weight:bold; font-size:12px;">
                                <input type="checkbox" name="bot_defense_pure_def_only" value="1" <?php echo $currentPureDefOnly ? 'checked' : ''; ?> style="width:16px; height:16px; cursor:pointer;">
                                <span>Hanya Kirim Unit Pertahanan Murni</span>
                            </label>
                            <span style="font-size:10px; color:#34d399; display:block; margin-top:4px;">
                                📌 <b>Saat ini:</b> <?php echo $currentPureDefOnly ? '🟢 AKTIF (Hanya kirim Spearman, Praetorian, Phalanx, Pendekar Tombak, Ash Warden. Melarang Clubswinger/Axeman mati sia-sia)' : '⚪ Fleksibel (Semua unit boleh ikut)'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Ratio Active Attack -->
                    <div class="botm-form-group">
                        <label>Rasio Kirim saat Serangan Masuk (Priority 1):</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> <?php echo ($currentDefRatioActive * 100); ?>% dari pasukan siap kirim
                        </div>
                        <select name="bot_defense_ratio_active" class="botm-form-control">
                            <option value="0.40" <?php echo abs($currentDefRatioActive - 0.40) < 0.05 ? 'selected' : ''; ?>>40% Pasukan (Lama - Konservatif)</option>
                            <option value="0.60" <?php echo abs($currentDefRatioActive - 0.60) < 0.05 ? 'selected' : ''; ?>>60% Pasukan (Kuat)</option>
                            <option value="0.75" <?php echo abs($currentDefRatioActive - 0.75) < 0.05 ? 'selected' : ''; ?>>75% Pasukan (Rekomendasi - Aliansi Solid)</option>
                            <option value="0.90" <?php echo abs($currentDefRatioActive - 0.90) < 0.05 ? 'selected' : ''; ?>>90% Pasukan (Mobilisasi Darurat Penuh)</option>
                        </select>
                        <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                            Berapa persen pasukan cadangan desa bot yang langsung dimobilisasi saat ada serangan sedang berjalan menuju desa kawan.
                        </span>
                    </div>

                    <!-- Max Cap Active -->
                    <div class="botm-form-group">
                        <label>Plafon Maksimal Bantuan per Bot (Priority 1):</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> Maksimal <?php echo $currentDefMaxCap; ?> Unit / Bot (sebelumnya cuma 100)
                        </div>
                        <input type="number" name="bot_defense_max_cap" id="bot_def_max_cap_input" class="botm-form-control" value="<?php echo $currentDefMaxCap; ?>" min="50" max="3000" required>
                        <div class="quick-counts" style="margin-top:4px;">
                            <button type="button" class="quick-btn" onclick="document.getElementById('bot_def_max_cap_input').value=100">100 (Lama)</button>
                            <button type="button" class="quick-btn" onclick="document.getElementById('bot_def_max_cap_input').value=250">250 (Sedang)</button>
                            <button type="button" class="quick-btn" onclick="document.getElementById('bot_def_max_cap_input').value=350">350 (Rekomendasi)</button>
                            <button type="button" class="quick-btn" onclick="document.getElementById('bot_def_max_cap_input').value=600">600 (Kuat)</button>
                            <button type="button" class="quick-btn" onclick="document.getElementById('bot_def_max_cap_input').value=1000">1000 (Masif)</button>
                        </div>
                    </div>

                    <!-- Max Helpers per Village -->
                    <div class="botm-form-group">
                        <label>Maksimal Bot Penolong per Desa:</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> Maksimal <?php echo $currentMaxHelpers; ?> Bot Penolong (sebelumnya cuma 3)
                        </div>
                        <select name="bot_max_helpers_per_village" class="botm-form-control">
                            <option value="3" <?php echo $currentMaxHelpers === 3 ? 'selected' : ''; ?>>3 Bot (Lama - Bantuan Sedikit)</option>
                            <option value="5" <?php echo $currentMaxHelpers === 5 ? 'selected' : ''; ?>>5 Bot (Standar)</option>
                            <option value="6" <?php echo $currentMaxHelpers === 6 ? 'selected' : ''; ?>>6 Bot (Rekomendasi - Dinding Garnisun Tebal)</option>
                            <option value="8" <?php echo $currentMaxHelpers === 8 ? 'selected' : ''; ?>>8 Bot (Pertahanan Aliansi Sangat Kuat)</option>
                            <option value="12" <?php echo $currentMaxHelpers === 12 ? 'selected' : ''; ?>>12 Bot (Perang Total)</option>
                        </select>
                        <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                            Berapa banyak kawan bot yang diizinkan mengirim bala bantuan ke 1 desa target yang sama.
                        </span>
                    </div>

                    <!-- Ratio Recovery -->
                    <div class="botm-form-group">
                        <label>Rasio Pemulihan Garnisun (Priority 2):</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> <?php echo ($currentDefRatioRecovery * 100); ?>% Pasukan (Maks <?php echo $currentDefMaxCapRecovery; ?> unit)
                        </div>
                        <select name="bot_defense_ratio_recovery" class="botm-form-control">
                            <option value="0.25" <?php echo abs($currentDefRatioRecovery - 0.25) < 0.05 ? 'selected' : ''; ?>>25% Pasukan (Lama)</option>
                            <option value="0.40" <?php echo abs($currentDefRatioRecovery - 0.40) < 0.05 ? 'selected' : ''; ?>>40% Pasukan</option>
                            <option value="0.50" <?php echo abs($currentDefRatioRecovery - 0.50) < 0.05 ? 'selected' : ''; ?>>50% Pasukan (Rekomendasi)</option>
                            <option value="0.65" <?php echo abs($currentDefRatioRecovery - 0.65) < 0.05 ? 'selected' : ''; ?>>65% Pasukan (Cepat Pulih)</option>
                        </select>
                        <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                            Dikirim saat kawan baru selesai diserang dalam 2 jam terakhir untuk mengisi kembali pos jaga.
                        </span>
                    </div>

                    <!-- Max Cap Recovery -->
                    <div class="botm-form-group">
                        <label>Plafon Bantuan Pemulihan (Priority 2):</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> Maksimal <?php echo $currentDefMaxCapRecovery; ?> Unit / Bot
                        </div>
                        <input type="number" name="bot_defense_max_cap_recovery" class="botm-form-control" value="<?php echo $currentDefMaxCapRecovery; ?>" min="20" max="1500" required>
                    </div>

                    <!-- Max Defense Distance -->
                    <div class="botm-form-group">
                        <label>Radius Jarak Maksimal Pengiriman Bantuan:</label>
                        <div style="background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:6px 10px; margin-bottom:5px; font-size:11px; color:#818cf8;">
                            📌 <b>Settingan Aktif:</b> Radius <?php echo $currentMaxDefDist; ?> Kotak Peta
                        </div>
                        <input type="number" step="1" name="bot_max_defense_distance" class="botm-form-control" value="<?php echo (int)$currentMaxDefDist; ?>" min="20" max="400" required>
                        <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                            Batas terjauh kawan aliansi yang akan dibantu (dalam satuan jarak koordinat peta).
                        </span>
                    </div>
                </div>

                <!-- Simulation comparison explanation -->
                <div style="margin-top:14px; background:#0f172a; border:1px solid #312e81; border-radius:6px; padding:10px 14px; font-size:11px; color:#c7d2fe; line-height:1.6;">
                    <b style="color:#a5b4fc;">📊 Simulasi Perbandingan Bantuan:</b><br>
                    • <b>Sebelumnya (Lama):</b> Plafon 100 unit &times; maks 3 bot = hanya <b>maksimal ~160-300 unit</b> bantuan (termasuk Clubswinger rapuh yang mudah tewas).<br>
                    • <b>Dengan Pengaturan Saat Ini:</b> Plafon <?php echo $currentDefMaxCap; ?> unit &times; <?php echo $currentMaxHelpers; ?> bot = mampu menumpuk <b>hingga <?php echo ($currentDefMaxCap * $currentMaxHelpers); ?> unit pasukan spesialis defense murni</b> (Spearman, Praetorian, Phalanx, Ash Warden, Pendekar Tombak) + Hero, cukup untuk menahan gelombang ratusan/ribuan penyerang!
                </div>
            </div>

            <!-- Natural Steps Infographic Box -->
            <div style="background:#022c22; border:1px solid #059669; border-radius:6px; padding:12px; margin-bottom:14px; font-size:11px; line-height:1.6; color:#a7f3d0;">
                <b style="color:#34d399; font-size:12px;">🌱 Alur Natural Pembentukan Desa ke-2 (Tanpa Skip Tahapan):</b>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-top:8px;">
                    <div style="background:#064e3b; padding:8px 10px; border-radius:4px; border-left:3px solid #10b981;">
                        <b style="color:#fff;">1. Padepokan (Residence)</b><br>
                        Bot harus menaikkan Residence bertahap hingga Level 10 dengan biaya &amp; waktu konstruksi asli.
                    </div>
                    <div style="background:#064e3b; padding:8px 10px; border-radius:4px; border-left:3px solid #10b981;">
                        <b style="color:#fff;">2. Pelatihan 3 Pionir</b><br>
                        Dilatih satu per satu di antrian pelatihan, memakan biaya ribuan kayu, tanah, besi, gandum, &amp; waktu riil.
                    </div>
                    <div style="background:#064e3b; padding:8px 10px; border-radius:4px; border-left:3px solid #10b981;">
                        <b style="color:#fff;">3. Poin Budaya (Culture Points)</b><br>
                        Bot wajib mengumpulkan CP dari bangunan &amp; Balai Kota sebelum diizinkan berekspansi.
                    </div>
                    <div style="background:#064e3b; padding:8px 10px; border-radius:4px; border-left:3px solid #10b981;">
                        <b style="color:#fff;">4. Perbekalan &amp; Ekspedisi</b><br>
                        Membawa 750 tiap SDA, berjalan melintasi peta ke lahan kosong dengan durasi tempuh asli pionir.
                    </div>
                </div>
            </div>

            <!-- Natural Paranormal Infographic Box -->
            <div style="background:#2e1065; border:1px solid #7c3aed; border-radius:6px; padding:12px; margin-bottom:14px; font-size:11px; line-height:1.6; color:#ddd6fe;">
                <b style="color:#c4b5fd; font-size:12px;">👻 Alur Natural Sanggar Paranormal &amp; Khodam Nusantara (100% Organik, Tanpa Tiba-tiba Muncul):</b>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-top:8px;">
                    <div style="background:#3b0764; padding:8px 10px; border-radius:4px; border-left:3px solid #a855f7;">
                        <b style="color:#fff;">1. Prasyarat &amp; Fondasi</b><br>
                        Gedung Utama Lvl 3, Cranny Lvl 3, dan umur desa minimal 1.5 hari. Biaya konstruksi riil (150 kayu, 120 tanah, 160 besi, 80 gandum).
                    </div>
                    <div style="background:#3b0764; padding:8px 10px; border-radius:4px; border-left:3px solid #a855f7;">
                        <b style="color:#fff;">2. Peningkatan Bertahap</b><br>
                        Dibangun di slot 38 dan dinaikkan per level secara bergantian dengan fasilitas kota lain sesuai antrian tukang normal.
                    </div>
                    <div style="background:#3b0764; padding:8px 10px; border-radius:4px; border-left:3px solid #a855f7;">
                        <b style="color:#fff;">3. Pemanggilan Arwah Alami</b><br>
                        Hanya 1-2 makhluk gaib (Pocong/Kunti/Genderuwo) per siklus bila kapasitas slot cukup dan desa memiliki cadangan surplus SDA aman.
                    </div>
                    <div style="background:#3b0764; padding:8px 10px; border-radius:4px; border-left:3px solid #a855f7;">
                        <b style="color:#fff;">4. Ritual Khodam Tuyul</b><br>
                        Diaktifkan jika bot aktif merampok, Sanggar Lvl &gt;= 2, serta memiliki cadangan gandum &amp; bahan yang cukup (berkah jarahan +15% 24 jam).
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="background:#059669; border-color:#10b981; padding:9px 20px; font-weight:bold; cursor:pointer;">
                💾 Simpan Pengaturan Pertumbuhan &amp; Desa ke-2
            </button>
        </form>
    </div>

    <!-- Panel 3: Order Attack / Air Strike / Alliance War -->
    <div class="botm-panel" style="border-color:#b91c1c; background:linear-gradient(135deg, #130a10 0%, #0b1220 100%); margin-bottom:20px;">
        <h3 style="color:#f87171;">🎯 Perintahkan Bot AI Menyerang Target / Perang Aliansi</h3>
        <p class="desc">Perintahkan bot perorangan, bot aliansi, atau seluruh bot di peta untuk meluncurkan serangan/raid militer ke pemain, koordinat, atau <b>seluruh anggota aliansi lawan</b>. Target akan menerima notifikasi pedang merah di Titik Temu (Rally Point) secara real-time!</p>

        <form method="post" action="admin.php?p=botManager">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="bot_action" value="order_attack">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-bottom:14px;">
                <div class="botm-form-group">
                    <label>Pilih Bot Penyerang:</label>
                    <select name="target_bot" class="botm-form-control">
                        <option value="0">⚡ Otomatis: Bot Terdekat dari Target</option>
                        <option value="all">🔥 SEMUA BOT AKTIF (Serangan Massal Seluruh Peta)</option>
                        <?php if (!empty($existingAlliances)): ?>
                            <optgroup label="🛡️ Serang Mengatasnamakan Aliansi Bot:">
                                <?php foreach ($existingAlliances as $ali): ?>
                                    <option value="alliance:<?php echo $ali['id']; ?>">
                                        🛡️ Semua Bot di Aliansi [<?php echo htmlspecialchars($ali['tag']); ?>] <?php echo htmlspecialchars($ali['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($botsList)): ?>
                            <optgroup label="👤 Pilih Bot Spesifik:">
                                <?php foreach ($botsList as $b): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        <?php echo (!empty($b['ali_tag']) ? "[{$b['ali_tag']}] " : "") . htmlspecialchars($b['username']); ?> [<?php echo htmlspecialchars($b['vname']); ?> (<?php echo $b['x']; ?>|<?php echo $b['y']; ?>)]
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Target Sasaran (Pemain / Koordinat X|Y):</label>
                    <input type="text" name="target_input" class="botm-form-control" placeholder="Contoh: Player1 atau 15|-24">
                    <span style="font-size:10px; color:#64748b;">Ketik username atau koordinat X|Y. Kosongkan jika memilih Aliansi Sasaran di bawah.</span>
                </div>

                <div class="botm-form-group">
                    <label>⚔️ Atau Target Seluruh Aliansi Lawan:</label>
                    <select name="target_alliance" class="botm-form-control">
                        <option value="0">-- Bukan Serangan Aliansi (Gunakan Input di Atas) --</option>
                        <?php if (!empty($existingAlliances)): ?>
                            <?php foreach ($existingAlliances as $ali): ?>
                                <option value="<?php echo $ali['id']; ?>">
                                    🎯 Gempur Seluruh Desa Aliansi [<?php echo htmlspecialchars($ali['tag']); ?>] <?php echo htmlspecialchars($ali['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span style="font-size:10px; color:#f87171;">Jika dipilih, bot akan membagi serangan ke setiap desa aliansi ini!</span>
                </div>

                <div class="botm-form-group">
                    <label>Komposisi Pasukan:</label>
                    <select name="composition" class="botm-form-control">
                        <option value="balanced">⚔️ Seimbang (Infanteri + Kavaleri + Pengepungan)</option>
                        <option value="defense">🛡️ Garnisun Pertahanan (Infanteri & Kavaleri Defensif)</option>
                        <option value="cavalry">🐎 Kavaleri Cepat (Serbuan Kuda Kilat)</option>
                        <option value="infantry">⚔️ Barisan Infanteri (Pedang & Tombak)</option>
                        <option value="siege">💣 Penghancur Benteng (Catapult & Ram)</option>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Tipe Operasi / Serangan:</label>
                    <select name="attack_type" class="botm-form-control">
                        <option value="4">⚔️ Raid (Penjarahan Sumber Daya)</option>
                        <option value="3">💀 Normal Attack (Serangan Tempur Penuh)</option>
                        <option value="2">🛡️ Bantuan Bertahan (Reinforcement ke Sekutu)</option>
                    </select>
                    <span style="font-size:10px; color:#94a3b8; display:block; margin-top:3px;">
                        💡 <b>Info:</b> Ram &amp; Catapult <u>hanya</u> akan dibawa pada <b>Normal Attack</b>. Pada <b>Raid</b>, senjata pengepungan ditinggal di desa agar perampokan bergerak cepat.
                    </span>
                </div>

                <div class="botm-form-group">
                    <label>Jumlah Pasukan Tiap Bot:</label>
                    <input type="number" name="troop_count" class="botm-form-control" value="0" min="0" max="10000">
                    <span style="font-size:10px; color:#64748b;">0 = Otomatis (maks. 50 pasukan). Pasukan yang dikirim dibatasi ketat oleh jumlah pasukan yang dimiliki bot di desanya.</span>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="background:#dc2626; border-color:#ef4444; width:auto; padding:10px 22px; font-weight:bold; cursor:pointer;">
                🚀 Luncurkan Serangan Bot Sekarang
            </button>
        </form>
    </div>

    <!-- Panel 4: Alliance Management for Bots -->
    <div class="botm-panel" style="border-color:#0284c7; background:linear-gradient(135deg, #082f49 0%, #0b1220 100%); margin-bottom:20px;">
        <h3 style="color:#38bdf8;">🛡️ Manajemen Aliansi Bot AI (Admin Alliance Center)</h3>
        <p class="desc">Buat aliansi baru khusus bot langsung sebagai Admin, atau masukkan bot secara spesifik / per-suku ke aliansi yang Anda inginkan.</p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(310px, 1fr)); gap:16px;">
            <!-- Card 1: Form Buat Aliansi Baru Langsung sebagai Admin -->
            <form method="post" action="admin.php?p=botManager" style="background:#0f172a; padding:14px; border-radius:8px; border:1px solid #1e293b;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bot_action" value="create_alliance">
                <h4 style="margin:0 0 10px 0; color:#38bdf8; font-size:13px;">👑 1. Buat Aliansi Baru (Admin):</h4>

                <div class="botm-form-group">
                    <label>Tag Aliansi (Singkatan, maks 8 huruf):</label>
                    <input type="text" name="tag" class="botm-form-control" placeholder="Contoh: ROMA atau NUSAN" maxlength="8" required>
                </div>

                <div class="botm-form-group">
                    <label>Nama Aliansi (Maks 25 huruf):</label>
                    <input type="text" name="name" class="botm-form-control" placeholder="Contoh: Legiun Romawi Bot" maxlength="25" required>
                </div>

                <div class="botm-form-group">
                    <label>Pilih Pendiri (Founder Aliansi):</label>
                    <select name="founder_uid" class="botm-form-control">
                        <option value="0">⚡ Otomatis: Bot Pertama yang Bebas</option>
                        <?php if (!empty($botsList)): ?>
                            <optgroup label="Pilih Bot Tertentu:">
                                <?php foreach ($botsList as $b): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        <?php echo htmlspecialchars($b['username']); ?> (UID: #<?php echo $b['id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Filter Suku untuk Anggota Awal:</label>
                    <select name="auto_tribe" class="botm-form-control">
                        <option value="0">🌐 Semua Suku (Campuran)</option>
                        <option value="1">🏛️ Romawi (Roman)</option>
                        <option value="2">⚔️ Teuton</option>
                        <option value="3">🛡️ Galia (Gaul)</option>
                        <option value="6">🏹 Hun</option>
                        <option value="7">🏺 Mesir (Egyptian)</option>
                        <option value="8">🛡️ Spartan</option>
                        <option value="9">🪓 Viking</option>
                        <?php if (defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA): ?>
                        <option value="10">👑 Nusantara</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Langsung Masukkan N Bot Awal:</label>
                    <input type="number" name="auto_count" class="botm-form-control" value="5" min="0" max="60">
                    <span style="font-size:10px; color:#64748b;">0 = Hanya pendiri saja. 5 = Pendiri + 5 anggota bot.</span>
                </div>

                <button type="submit" class="btn-primary" style="background:#0284c7; border-color:#38bdf8; width:100%; justify-content:center;">
                    👑 Buat Aliansi Sekarang
                </button>
            </form>

            <!-- Card 2: Form Masukkan Bot ke Aliansi yang Ada -->
            <form method="post" action="admin.php?p=botManager" style="background:#0f172a; padding:14px; border-radius:8px; border:1px solid #1e293b;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bot_action" value="join_alliance">
                <h4 style="margin:0 0 10px 0; color:#4ade80; font-size:13px;">➕ 2. Masukkan Bot ke Aliansi:</h4>

                <div class="botm-form-group">
                    <label>Pilih Aliansi Tujuan:</label>
                    <select name="aid" class="botm-form-control" required>
                        <?php if (!empty($existingAlliances)): ?>
                            <?php foreach ($existingAlliances as $al): ?>
                                <option value="<?php echo (int)$al['id']; ?>">
                                    [<?php echo htmlspecialchars($al['tag']); ?>] <?php echo htmlspecialchars($al['name']); ?> (#<?php echo (int)$al['id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0" disabled selected>-- Belum ada aliansi terdaftar (Buat di card 1) --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Pilih Bot / Kelompok Bot:</label>
                    <select name="target_bot" class="botm-form-control">
                        <option value="all_unassigned">⚡ Semua Bot yang Belum Ber-aliansi</option>
                        <option value="all">🌐 Semua Bot (Pindahkan Semuanya)</option>
                        <optgroup label="Kelompokkan Sesuai Suku (Belum Ber-aliansi):">
                            <option value="tribe_1">🏛️ Semua Bot Romawi</option>
                            <option value="tribe_2">⚔️ Semua Bot Teuton</option>
                            <option value="tribe_3">🛡️ Semua Bot Galia</option>
                            <option value="tribe_6">🏹 Semua Bot Hun</option>
                            <option value="tribe_7">🏺 Semua Bot Mesir</option>
                            <option value="tribe_8">🛡️ Semua Bot Spartan</option>
                            <option value="tribe_9">🪓 Semua Bot Viking</option>
                            <?php if (defined('NEW_FUNCTION_TRIBE_NUSANTARA') && NEW_FUNCTION_TRIBE_NUSANTARA): ?>
                            <option value="tribe_10">👑 Semua Bot Nusantara</option>
                            <?php endif; ?>
                        </optgroup>
                        <?php if (!empty($botsList)): ?>
                            <optgroup label="Pilih Bot Spesifik:">
                                <?php foreach ($botsList as $b): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        <?php echo htmlspecialchars($b['username']); ?> (UID: #<?php echo $b['id']; ?><?php echo !empty($b['ali_tag']) ? " | [{$b['ali_tag']}]" : ''; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="botm-form-group">
                    <label>Jumlah Bot (Batas Maksimal):</label>
                    <input type="number" name="bot_count" class="botm-form-control" value="5" min="0" max="100">
                    <span style="font-size:10px; color:#64748b;">0 = Semua bot yang cocok tanpa batas.</span>
                </div>

                <button type="submit" class="btn-primary" style="background:#16a34a; border-color:#22c55e; width:100%; justify-content:center;" <?php echo empty($existingAlliances) ? 'disabled' : ''; ?>>
                    🛡️ Masukkan Bot ke Aliansi
                </button>
            </form>

            <!-- Card 3: Form Keluarkan Bot dari Aliansi -->
            <form method="post" action="admin.php?p=botManager" style="background:#0f172a; padding:14px; border-radius:8px; border:1px solid #1e293b;" onsubmit="return confirm('Keluarkan bot dari aliansi?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bot_action" value="leave_alliance">
                <h4 style="margin:0 0 10px 0; color:#f59e0b; font-size:13px;">🚪 3. Keluarkan Bot dari Aliansi:</h4>

                <div class="botm-form-group">
                    <label>Pilih Bot yang Dikeluarkan:</label>
                    <select name="target_bot" class="botm-form-control">
                        <option value="all">⚡ Semua Bot yang Ada di Aliansi Mana Saja</option>
                        <?php if (!empty($botsList)): ?>
                            <optgroup label="Pilih Bot Tertentu:">
                                <?php foreach ($botsList as $b): ?>
                                    <?php if (!empty($b['alliance'])): ?>
                                        <option value="<?php echo $b['id']; ?>">
                                            <?php echo htmlspecialchars($b['username']); ?> [<?php echo htmlspecialchars($b['ali_tag'] ?? 'Aliansi'); ?>]
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div style="font-size:11px; color:#94a3b8; margin-bottom:14px; line-height:1.5;">
                    Bot yang dikeluarkan akan menjadi independen (tanpa aliansi). Izin aliansi di tabel <code>ali_permission</code> akan dibersihkan dan populasi aliansi disinkronkan kembali.
                </div>

                <button type="submit" class="btn-secondary" style="border-color:#f59e0b; color:#f59e0b; width:100%; justify-content:center;">
                    🚪 Keluarkan Bot dari Aliansi
                </button>
            </form>
        </div>
    </div>

    <!-- Active Bots Table Directory -->
    <div class="botm-table-panel">
        <div class="table-head-bar">
            <div>
                <h3>📋 Daftar Akun Bot Aktif (<?php echo count($botsList); ?> Desa)</h3>
                <span style="font-size:10px; color:#64748b;">Menampilkan semua akun dengan bendera bot AI di server ini.</span>
            </div>
            <div>
                <input type="text" id="botTableSearch" class="search-input" placeholder="🔍 Cari bot / suku / desa..." onkeyup="filterBotTable()">
            </div>
        </div>

        <?php if (!empty($botsList)): ?>
            <div style="overflow-x:auto;">
                <table class="botm-table" id="activeBotsTable">
                    <thead>
                        <tr>
                            <th style="width:50px;">UID</th>
                            <th>Username</th>
                            <th>Aliansi</th>
                            <th>Suku</th>
                            <th>Nama Desa</th>
                            <th>Koordinat</th>
                            <th>Populasi</th>
                            <th>Ekspansi / Status</th>
                            <th>Pasukan</th>
                            <th>Sumber Daya</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($botsList as $b): ?>
                            <?php
                                $tNum = (int)$b['tribe'];
                                $tIcon = $tribeIcons[$tNum] ?? '👤';
                                $tName = $tribeNames[$tNum] ?? 'Suku ' . $tNum;
                                $coords = !empty($b['wref']) ? "({$b['x']}|{$b['y']})" : "-";
                                $vTroops = $unitsMap[$b['wref']] ?? 0;
                            ?>
                            <tr>
                                <td style="font-family:monospace; color:#94a3b8;">#<?php echo (int)$b['id']; ?></td>
                                <td>
                                    <div style="font-weight:bold; color:#f8fafc;"><?php echo htmlspecialchars($b['username']); ?></div>
                                    <div style="font-size:9px; color:#64748b; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?php echo htmlspecialchars($b['desc1'] ?? ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($b['alliance']) && !empty($b['ali_tag'])): ?>
                                        <a href="../allianz.php?aid=<?php echo (int)$b['alliance']; ?>" target="_blank" style="color:#38bdf8; text-decoration:none; font-weight:600; font-size:11px;">
                                            🛡️ [<?php echo htmlspecialchars($b['ali_tag']); ?>]
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#64748b; font-size:11px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tribe-badge"><?php echo $tIcon; ?> <?php echo htmlspecialchars($tName); ?></span>
                                </td>
                                <td>
                                    <a href="../karte.php?d=<?php echo (int)$b['wref']; ?>&c=<?php echo !empty($b['wref']) ? $database->getVilHash($b['wref']) : ''; ?>" target="_blank" style="color:#38bdf8; text-decoration:none; font-weight:600;">
                                        <?php echo htmlspecialchars($b['vname'] ?? 'Desa'); ?>
                                    </a>
                                </td>
                                <td style="font-family:monospace; color:#fbbf24;"><?php echo $coords; ?></td>
                                <td><b style="color:#4ade80;"><?php echo number_format((int)$b['pop']); ?></b></td>
                                <td>
                                    <?php
                                        $expStatus = BotAI::getExpansionStatus((int)$b['id'], (int)($b['wref'] ?? 0), $tNum);
                                        $vCount = $expStatus['villages'] ?? 1;
                                        $vMax = $expStatus['max_villages'] ?? 2;
                                    ?>
                                    <div style="display:flex; align-items:center; gap:4px; margin-bottom:3px;">
                                        <span style="background:#1e293b; color:#38bdf8; border:1px solid #334155; padding:1px 6px; border-radius:10px; font-size:10px; font-weight:bold;">
                                            Desa <?php echo $vCount; ?>/<?php echo $vMax; ?>
                                        </span>
                                    </div>
                                    <div style="font-size:10px; color:#cbd5e1; line-height:1.4;">
                                        <?php echo htmlspecialchars($expStatus['status_text'] ?? '-'); ?>
                                    </div>
                                    <div style="font-size:9px; color:#64748b; margin-top:2px;">
                                        CP: <b style="color:#f59e0b;"><?php echo number_format($expStatus['cp'] ?? 0); ?></b> / <?php echo number_format($expStatus['cp_needed'] ?? 2000); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        $vDetails = $unitsDetailsMap[$b['wref']] ?? [];
                                    ?>
                                    <?php if ($vTroops > 0): ?>
                                        <div style="font-weight:bold; color:#38bdf8; margin-bottom:4px; font-size:12px;">
                                            ⚔️ <?php echo number_format($vTroops); ?> Total
                                        </div>
                                        <div style="display:flex; flex-wrap:wrap; gap:4px; max-width:320px;">
                                            <?php foreach ($vDetails as $u): ?>
                                                <span style="display:inline-flex; align-items:center; gap:4px; background:#0f172a; border:1px solid #334155; padding:2px 6px; border-radius:4px; font-size:11px; color:#e2e8f0; white-space:nowrap;" title="<?php echo htmlspecialchars($u['name']); ?>: <?php echo number_format($u['count']); ?>">
                                                    <?php if ($u['id'] === 'hero'): ?>
                                                        <span style="font-size:11px;">👑</span>
                                                    <?php else: ?>
                                                        <img src="../img/un/u/<?php echo $u['id']; ?>.gif" style="width:14px; height:14px; vertical-align:middle;" alt="" onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <b style="color:#f8fafc; font-family:monospace;"><?php echo number_format($u['count']); ?></b>
                                                    <span style="color:#94a3b8; font-size:10px;"><?php echo htmlspecialchars($u['name']); ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-weight:bold; color:#64748b; font-size:11px;">
                                            ⚔️ 0 Pasukan
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($tNum === 10):
                                        $spStatus = BotAI::getParanormalStatus((int)$b['wref']);
                                        if ($spStatus['level'] > 0 || $spStatus['used_slots'] > 0): ?>
                                        <div style="margin-top:6px; font-size:10px; color:#c084fc; display:flex; flex-wrap:wrap; gap:3px;">
                                            <span style="background:#3b0764; border:1px solid #7e22ce; padding:1px 5px; border-radius:3px;" title="Sanggar Paranormal Slot 38">
                                                👻 Sanggar Lvl <?php echo $spStatus['level']; ?> (<?php echo $spStatus['used_slots']; ?>/<?php echo $spStatus['max_cap']; ?>)
                                            </span>
                                            <?php if ($spStatus['pocong'] > 0): ?>
                                                <span style="background:#1e1b4b; border:1px solid #4338ca; padding:1px 4px; border-radius:3px; color:#a5b4fc;">Pocong: <?php echo $spStatus['pocong']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($spStatus['kuntilanak'] > 0): ?>
                                                <span style="background:#1e1b4b; border:1px solid #4338ca; padding:1px 4px; border-radius:3px; color:#a5b4fc;">Kunti: <?php echo $spStatus['kuntilanak']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($spStatus['genderuwo'] > 0): ?>
                                                <span style="background:#1e1b4b; border:1px solid #4338ca; padding:1px 4px; border-radius:3px; color:#a5b4fc;">Genderuwo: <?php echo $spStatus['genderuwo']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($spStatus['tuyul_active']): ?>
                                                <span style="background:#701a75; border:1px solid #c026d3; padding:1px 4px; border-radius:3px; color:#f0abfc;">Tuyul (+15% Raid)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; endif; ?>
                                </td>
                                <td class="res-pill">
                                    🌲 <?php echo number_format((int)$b['wood']); ?> |
                                    🧱 <?php echo number_format((int)$b['clay']); ?> |
                                    ⛏️ <?php echo number_format((int)$b['iron']); ?> |
                                    🌾 <?php echo number_format((int)$b['crop']); ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="admin.php?p=editUser&uid=<?php echo (int)$b['id']; ?>" class="btn-secondary" title="Edit Akun di ACP">
                                        ✏️ Edit
                                    </a>
                                    <a href="../spieler.php?uid=<?php echo (int)$b['id']; ?>" target="_blank" class="btn-secondary" title="Lihat Profil Game">
                                        👤 Profil
                                    </a>
                                    <form method="post" action="admin.php?p=botManager" style="display:inline; margin:0;" onsubmit="return confirm('Hapus bot <?php echo htmlspecialchars(addslashes($b['username'])); ?> (UID: <?php echo (int)$b['id']; ?>)?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="bot_action" value="delete_single">
                                        <input type="hidden" name="uid" value="<?php echo (int)$b['id']; ?>">
                                        <button type="submit" class="btn-danger" title="Hapus Bot Ini">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:30px; color:#64748b;">
                Belum ada bot yang terdaftar. Gunakan tombol <b>"Spawn Bot Sekarang"</b> di atas untuk membuat bot pertama Anda!
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Normalisasi Pasukan Bot -->
<div id="modalRebalance" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.78); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
    <div style="background:#0f172a; border:1px solid #334155; border-radius:12px; width:92%; max-width:540px; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.6); color:#f8fafc; font-family:Verdana,Arial,sans-serif;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #1e293b; padding-bottom:10px;">
            <h3 style="margin:0; font-size:16px; color:#f472b6; display:flex; align-items:center; gap:8px;">⚖️ Normalisasi / Rebalance Pasukan Bot</h3>
            <button type="button" onclick="closeRebalanceModal()" style="background:transparent; border:none; color:#94a3b8; font-size:24px; cursor:pointer; line-height:1;">&times;</button>
        </div>
        <p style="font-size:11px; color:#cbd5e1; line-height:1.5; margin:0 0 16px;">
            Fitur ini secara instan memangkas ribuan pasukan bot yang menumpuk tidak wajar (misal 60.000 pasukan) agar kembali seimbang sesuai batas pertumbuhan alami berdasarkan usia akun (1 hari: ~20-35 pasukan).
        </p>
        <form method="post" action="admin.php?p=botManager" style="margin:0;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="bot_action" value="rebalance_troops">
            
            <div class="botm-form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#cbd5e1; margin-bottom:6px;">Target Normalisasi Usia Bot:</label>
                <select name="target_age_day" class="botm-form-control" style="width:100%; box-sizing:border-box; background:#0b1220; color:#fff; border:1px solid #334155; border-radius:6px; padding:7px 10px; font-size:12px;">
                    <option value="1" selected>⭐ Usia Hari 1 (Default): Pop ~48-56, Ladang Lvl 2, Gedung Kota Lvl 1-3, Tanpa Stable/Workshop, Pasukan ~20-25</option>
                    <option value="2">Usia Hari 2: Pop ~75-85, Ladang Lvl 3, Gedung Kota Lvl 2-4, Pasukan ~35-45</option>
                    <option value="3">Usia Hari 3: Pop ~110-130, Ladang Lvl 4, Buka Kavaleri Dasar, Pasukan ~55-70</option>
                    <option value="0">Otomatis (Sesuai hitungan timestamp akun saat ini)</option>
                </select>
                <small style="color:#94a3b8; font-size:10px; display:block; margin-top:4px;">
                    💡 Pilih <b>Usia Hari 1</b> untuk langsung membersihkan bot yang populasinya membesar tidak wajar (400+) dan mencopot kandang kavaleri/bengkel.
                </small>
            </div>

            <div class="botm-form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#cbd5e1; margin-bottom:6px;">Batas Maksimal Pasukan Garnisun per Desa:</label>
                <input type="number" name="target_garrison" class="botm-form-control" value="0" min="0" max="500" style="width:100%; box-sizing:border-box;">
                <small style="color:#94a3b8; font-size:10px; display:block; margin-top:4px;">
                    💡 Isi <b>0</b> untuk otomatis sesuai formula target usia bot (Hari 1 = ~20-25 prajurit dasar). Atau tentukan angka tetap jika ingin membatasi (misal 20).
                </small>
            </div>

            <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:12px; margin-bottom:16px;">
                <input type="hidden" name="rebalance_pop_submitted" value="1">
                <label style="display:flex; align-items:flex-start; gap:8px; font-size:11px; color:#e2e8f0; cursor:pointer;">
                    <input type="checkbox" name="rebalance_pop" value="1" checked style="margin-top:2px;">
                    <span>
                        <b style="color:#f472b6;">Normalisasi Bangunan, Ladang & Populasi ke Batas Usia Bot</b><br>
                        <span style="font-size:10px; color:#94a3b8;">Sangat Dianjurkan (Centang): Menormalkan ladang ke level 2, merapikan gedung kota (Main Building lvl 3, Warehouse/Granary/Barracks/Wall lvl 1), mencopot gedung lanjut (Stable/Workshop/Academy) yang belum saatnya dibangun, menghapus antrean konstruksi/pelatihan yang tertunda, dan menghitung ulang populasi menjadi wajar ~48-56 pop/desa.</span>
                    </span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="closeRebalanceModal()" class="btn-secondary" style="padding:8px 14px; font-size:11px; cursor:pointer;">Batal</button>
                <button type="submit" class="btn-primary" style="background:#be185d; padding:8px 16px; font-size:12px; font-weight:700; cursor:pointer;">⚖️ Eksekusi Normalisasi</button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
function openRebalanceModal() {
    var modal = document.getElementById('modalRebalance');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeRebalanceModal() {
    var modal = document.getElementById('modalRebalance');
    if (modal) {
        modal.style.display = 'none';
    }
}

function setCount(val) {
    document.getElementById('spawn_count').value = val;
}

function copyCronCmd() {
    var text = document.getElementById('cronCommandText').innerText;
    navigator.clipboard.writeText(text).then(function() {
        alert('Perintah Cron berhasil disalin ke clipboard!');
    }).catch(function() {
        var tempInput = document.createElement('input');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('Perintah Cron disalin!');
    });
}

function filterBotTable() {
    var input = document.getElementById('botTableSearch');
    var filter = input.value.toLowerCase();
    var table = document.getElementById('activeBotsTable');
    if (!table) return;
    var tr = table.getElementsByTagName('tr');

    for (var i = 1; i < tr.length; i++) {
        var rowText = tr[i].innerText.toLowerCase();
        if (rowText.indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}
</script>
