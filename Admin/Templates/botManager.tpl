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
                SET wood = 800, clay = 800, iron = 800, crop = 800,
                    maxstore = 1200, maxcrop = 1200
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
}

// ==============================================================================
// FETCH CURRENT ACTIVE BOTS & STATS
// ==============================================================================
$botsQuery = "
    SELECT u.id, u.username, u.tribe, u.regtime, u.desc1,
           v.wref, v.name as vname, v.pop, v.wood, v.clay, v.iron, v.crop, v.maxstore, v.maxcrop,
           w.x, w.y
    FROM " . TB_PREFIX . "users u
    LEFT JOIN " . TB_PREFIX . "vdata v ON u.id = v.owner
    LEFT JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
    WHERE u.access = 2
      AND (u.is_bot = 1 OR u.email LIKE '%@bot.travianz' OR u.desc1 LIKE '%[#BOT]%' OR u.desc2 LIKE '%[#BOT]%' OR u.username LIKE 'Bot_%')
    ORDER BY u.id DESC
";
$botsList = $database->query_return($botsQuery);

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

    // Sum troop counts across bot villages
    if (!empty($vrefs)) {
        $vrefsList = implode(',', $vrefs);
        $unitsRows = $database->query_return("
            SELECT vref, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+
                          u11+u12+u13+u14+u15+u16+u17+u18+u19+u20+
                          u21+u22+u23+u24+u25+u26+u27+u28+u29+u30+
                          u51+u52+u53+u54+u55+u56+u57+u58+u59+u60+
                          u61+u62+u63+u64+u65+u66+u67+u68+u69+u70+
                          u71+u72+u73+u74+u75+u76+u77+u78+u79+u80+
                          u81+u82+u83+u84+u85+u86+u87+u88+u89+u90+
                          u91+u92+u93+u94+u95+u96+u97+u98+u99+u100) as sum_troops
            FROM " . TB_PREFIX . "units
            WHERE vref IN ($vrefsList)
        ");
        $unitsMap = [];
        if (!empty($unitsRows)) {
            foreach ($unitsRows as $ur) {
                $unitsMap[$ur['vref']] = (int)$ur['sum_troops'];
                $totalTroops += (int)$ur['sum_troops'];
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
        <div style="display:flex; gap:8px;">
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
                                    } elseif (stripos($act, 'Attack') !== false) {
                                        $badge = '<span class="badge-attack">RAID</span>';
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
                            <th>Suku</th>
                            <th>Nama Desa</th>
                            <th>Koordinat</th>
                            <th>Populasi</th>
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
                                    <span style="font-weight:bold; color:<?php echo $vTroops > 0 ? '#38bdf8' : '#64748b'; ?>;">
                                        ⚔️ <?php echo number_format($vTroops); ?>
                                    </span>
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

<script type="text/javascript">
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
