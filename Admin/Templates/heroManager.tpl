<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : heroManager.tpl                                           ##
##  Type           : Admin Panel Frontend & Controller for Hero Inventory      ##
##  Purpose        : Grant hero equipment & items to single or multiple users, ##
##                   inspect inventories, manage silver, and send gift IGMs.   ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (empty($_SESSION['access']) || $_SESSION['access'] < (defined('MULTIHUNTER') ? MULTIHUNTER : 8)) {
    die(defined('ACCESS_DENIED_ADMIN') ? ACCESS_DENIED_ADMIN : 'Access Denied: You are not Admin!');
}

require_once __DIR__ . '/../../GameEngine/Data/hero_items.php';
require_once __DIR__ . '/../../GameEngine/HeroItems.php';
if (file_exists(__DIR__ . '/../../GameEngine/BotAI.php')) {
    require_once __DIR__ . '/../../GameEngine/BotAI.php';
}

$heroItems = new HeroItems();
$flashSuccess = '';
$flashError = '';

$slotNames = [
    HSLOT_HELMET => '🛡️ Helm (Helmet)',
    HSLOT_BODY   => '🥋 Zirah Tubuh (Body Armour)',
    HSLOT_RIGHT  => '⚔️ Senjata Kanan (Weapons)',
    HSLOT_LEFT   => '🛡️ Tangan Kiri (Shield, Horn, Map, Standard)',
    HSLOT_SHOES  => '👢 Sepatu & Boots (Shoes)',
    HSLOT_HORSE  => '🐎 Kuda & Tunggangan (Horse)',
    HSLOT_BAG    => '🎒 Tas / Consumables (Bandages, Cage, Tablets, Art)',
];

$tribeNames = [
    1 => 'Romawi', 2 => 'Teuton', 3 => 'Gaul', 4 => 'Nature', 5 => 'Natar',
    6 => 'Chinese', 7 => 'Mesir', 8 => 'Amazon', 9 => 'Rebel', 10 => 'Nusantara'
];

// ==============================================================================
// CONTROLLER: PROCESS POST ACTIONS
// ==============================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['hero_action'])) {
    $action = (string)$_POST['hero_action'];

    // --------------------------------------------------------------------------
    // ACTION 1: GIVE ITEMS / EQUIPMENT (SINGLE OR BULK)
    // --------------------------------------------------------------------------
    if ($action === 'give_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $silverBonus = max(0, (int)($_POST['silver_bonus'] ?? 0));
        $targetType = (string)($_POST['target_type'] ?? 'single');
        $excludeBots = !empty($_POST['exclude_bots']);
        $excludeBanned = !empty($_POST['exclude_banned']);
        $sendIgm = !empty($_POST['send_igm']);
        $igmTopic = trim((string)($_POST['igm_topic'] ?? ''));
        $igmMessage = trim((string)($_POST['igm_message'] ?? ''));

        if (!isset($heroItemCatalog[$itemId])) {
            $flashError = "❌ <b>Gagal:</b> Item ID #$itemId tidak ditemukan dalam katalog item hero.";
        } else {
            $itemDef = $heroItemCatalog[$itemId];
            $itemName = $itemDef['name'];

            // Tentukan daftar target User IDs
            $targetUids = [];

            switch ($targetType) {
                case 'single':
                    $singleUid = (int)($_POST['single_uid'] ?? 0);
                    $manualUsername = trim((string)($_POST['manual_username'] ?? ''));
                    if (!empty($manualUsername)) {
                        $foundU = $database->query_return("SELECT id FROM " . TB_PREFIX . "users WHERE username = '" . $database->escape($manualUsername) . "' LIMIT 1");
                        if (!empty($foundU[0]['id'])) {
                            $singleUid = (int)$foundU[0]['id'];
                        }
                    }
                    if ($singleUid > 0) {
                        $targetUids[] = $singleUid;
                    }
                    break;

                case 'all':
                    $query = "SELECT id, is_bot, email, desc1, desc2, username, access FROM " . TB_PREFIX . "users WHERE id > 3";
                    if ($excludeBanned) {
                        $query .= " AND access > 0";
                    }
                    $rows = $database->query_return($query);
                    foreach ($rows as $r) {
                        $uid = (int)$r['id'];
                        if ($excludeBots && class_exists('BotAI') && BotAI::isBotUser($uid)) {
                            continue;
                        }
                        $targetUids[] = $uid;
                    }
                    break;

                case 'alliance':
                    $allianceId = (int)($_POST['target_alliance'] ?? 0);
                    if ($allianceId > 0) {
                        $query = "SELECT id FROM " . TB_PREFIX . "users WHERE alliance = $allianceId AND id > 3";
                        if ($excludeBanned) {
                            $query .= " AND access > 0";
                        }
                        $rows = $database->query_return($query);
                        foreach ($rows as $r) {
                            $uid = (int)$r['id'];
                            if ($excludeBots && class_exists('BotAI') && BotAI::isBotUser($uid)) {
                                continue;
                            }
                            $targetUids[] = $uid;
                        }
                    }
                    break;

                case 'tribe':
                    $targetTribe = (int)($_POST['target_tribe'] ?? 1);
                    $query = "SELECT id FROM " . TB_PREFIX . "users WHERE tribe = $targetTribe AND id > 3";
                    if ($excludeBanned) {
                        $query .= " AND access > 0";
                    }
                    $rows = $database->query_return($query);
                    foreach ($rows as $r) {
                        $uid = (int)$r['id'];
                        if ($excludeBots && class_exists('BotAI') && BotAI::isBotUser($uid)) {
                            continue;
                        }
                        $targetUids[] = $uid;
                    }
                    break;

                case 'selected':
                    $selUids = (array)($_POST['selected_uids'] ?? []);
                    foreach ($selUids as $sUid) {
                        $sUid = (int)$sUid;
                        if ($sUid > 3) {
                            $targetUids[] = $sUid;
                        }
                    }
                    break;
            }

            $targetUids = array_unique(array_filter($targetUids));

            if (empty($targetUids)) {
                $flashError = "❌ <b>Gagal:</b> Tidak ada pemain yang cocok dengan kriteria target yang dipilih.";
            } else {
                $successCount = 0;
                $now = time();

                foreach ($targetUids as $uid) {
                    // Pastikan hero dasar ada di tabel hero
                    $heroCheck = $database->query_return("SELECT heroid FROM " . TB_PREFIX . "hero WHERE uid = $uid LIMIT 1");
                    if (empty($heroCheck)) {
                        $uData = $database->getUserArray($uid, 1);
                        $uTribe = (int)($uData['tribe'] ?? 1);
                        $baseUnit = max(1, ($uTribe - 1) * 10 + 1);
                        $capWref = (int)($uData['capital'] ?? 0);
                        if ($capWref <= 0) {
                            $vCheck = $database->query_return("SELECT wref FROM " . TB_PREFIX . "vdata WHERE owner = $uid ORDER BY capital DESC LIMIT 1");
                            $capWref = (int)($vCheck[0]['wref'] ?? 0);
                        }
                        $heroNameEsc = $database->escape(($uData['username'] ?? 'Player') . "'s Hero");
                        $database->query("
                            INSERT INTO " . TB_PREFIX . "hero
                            (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `production`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `autoregen`, `lastupdate`)
                            VALUES ($uid, $baseUnit, '$heroNameEsc', $capWref, 0, 0, 0, 100, 0, 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, $now)
                        ");
                    }

                    // Tambahkan item ke inventory
                    $resId = $heroItems->addItem($uid, $itemId, $qty);
                    if ($resId > 0) {
                        $successCount++;

                        // Tambahkan silver jika diminta
                        if ($silverBonus > 0) {
                            $database->query("UPDATE " . TB_PREFIX . "hero SET silver = silver + $silverBonus WHERE uid = $uid LIMIT 1");
                        }

                        // Kirim pesan IGM resmi jika dicentang
                        if ($sendIgm) {
                            $topic = !empty($igmTopic) ? $igmTopic : "🎁 Hadiah Hero Resmi dari Administrator!";
                            $msgBody = !empty($igmMessage) ? $igmMessage : "Selamat! Akun Anda telah menerima hadiah item hero resmi dari Administrator server:\n\n• <b>$itemName</b> (x$qty)\n" . ($silverBonus > 0 ? "• <b>Bonus Silver:</b> +" . number_format($silverBonus) . " Silver\n" : "") . "\nItem telah langsung masuk ke dalam tas inventory Hero Anda. Selamat bertualang!";

                            $search = ['{item}', '{qty}', '{silver}'];
                            $replace = [$itemName, $qty, number_format($silverBonus)];
                            $topicParsed = str_replace($search, $replace, $topic);
                            $msgParsed = nl2br(str_replace($search, $replace, $msgBody));

                            // Kirim dari Support (uid 1 / 0)
                            $database->sendMessage($uid, 1, $topicParsed, $msgParsed, 0, 0, 0, 0, 0);
                        }
                    }
                }

                if (method_exists($database, 'sendPendingMessages')) {
                    $database->sendPendingMessages();
                }

                // Catat log admin
                $adminUser = $_SESSION['username'] ?? 'Admin';
                $logMsg = "Memberikan hadiah Hero Item: $itemName (ID: $itemId, Qty: $qty" . ($silverBonus > 0 ? ", Silver: +$silverBonus" : "") . ") kepada $successCount pemain (Target: $targetType).";
                $logEsc = $database->escape($logMsg);
                $database->query("INSERT INTO " . TB_PREFIX . "admin_log (`id`, `user`, `log`, `time`) VALUES (0, '$adminUser', '$logEsc', $now)");

                $flashSuccess = "🎉 <b>Berhasil!</b> Hadiah <b>$itemName</b> (x$qty)" . ($silverBonus > 0 ? " + <b>" . number_format($silverBonus) . " Silver</b>" : "") . " berhasil diberikan kepada <b>$successCount</b> pemain!";
            }
        }
    }

    // --------------------------------------------------------------------------
    // ACTION 2: DELETE ITEM ROW (FROM INSPECTION TAB)
    // --------------------------------------------------------------------------
    elseif ($action === 'delete_item') {
        $rowId = (int)($_POST['row_id'] ?? 0);
        $userUid = (int)($_POST['user_uid'] ?? 0);

        if ($rowId > 0 && $userUid > 0) {
            $stmt = $database->dblink->prepare("DELETE FROM " . TB_PREFIX . "hero_items WHERE id = ? AND uid = ? LIMIT 1");
            $stmt->bind_param('ii', $rowId, $userUid);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $flashSuccess = "🗑️ Item #$rowId berhasil dihapus dari inventory pemain UID #$userUid.";
            } else {
                $flashError = "❌ Gagal menghapus item atau item tidak ditemukan.";
            }
            $stmt->close();
        }
    }

    // --------------------------------------------------------------------------
    // ACTION 3: SET SILVER (FROM INSPECTION TAB)
    // --------------------------------------------------------------------------
    elseif ($action === 'set_silver') {
        $userUid = (int)($_POST['user_uid'] ?? 0);
        $newSilver = max(0, (int)($_POST['new_silver'] ?? 0));

        if ($userUid > 0) {
            $stmt = $database->dblink->prepare("UPDATE " . TB_PREFIX . "hero SET silver = ? WHERE uid = ? LIMIT 1");
            $stmt->bind_param('ii', $newSilver, $userUid);
            $stmt->execute();
            $flashSuccess = "💰 Saldo Silver pemain UID #$userUid berhasil diatur menjadi " . number_format($newSilver) . " Silver.";
            $stmt->close();
        }
    }
}

// ==============================================================================
// FETCH DATA FOR DISPLAY
// ==============================================================================
// Fetch All Active Players (for selection)
$playersList = $database->query_return("
    SELECT u.id, u.username, u.tribe, u.access, u.is_bot, u.email, u.desc1, u.desc2, a.tag as ali_tag,
           (SELECT COUNT(*) FROM " . TB_PREFIX . "vdata WHERE owner = u.id) as villages_count
    FROM " . TB_PREFIX . "users u
    LEFT JOIN " . TB_PREFIX . "alidata a ON u.alliance = a.id
    WHERE u.id > 3
    ORDER BY u.access DESC, u.username ASC
");

// Fetch All Alliances
$alliancesList = $database->query_return("SELECT id, name, tag FROM " . TB_PREFIX . "alidata ORDER BY name ASC");

// Fetch Server Hero Stats
$heroStats = $database->query_return("
    SELECT COUNT(*) as total_heroes,
           SUM(silver) as total_silver,
           SUM(CASE WHEN dead = 0 THEN 1 ELSE 0 END) as alive_heroes
    FROM " . TB_PREFIX . "hero
");
$totalHeroes = (int)($heroStats[0]['total_heroes'] ?? 0);
$aliveHeroes = (int)($heroStats[0]['alive_heroes'] ?? 0);
$totalSilver = (int)($heroStats[0]['total_silver'] ?? 0);

$totalItemsRow = $database->query_return("SELECT COUNT(*) as total_items FROM " . TB_PREFIX . "hero_items");
$totalItemsInServer = (int)($totalItemsRow[0]['total_items'] ?? 0);

// Active Tab
$activeTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'give';
$inspectUid = isset($_GET['inspect_uid']) ? (int)$_GET['inspect_uid'] : (isset($_POST['inspect_uid']) ? (int)$_POST['inspect_uid'] : 0);

$inspectUser = null;
$inspectHero = null;
$inspectInventory = [];

if ($inspectUid > 0) {
    $inspectUser = $database->getUserArray($inspectUid, 1);
    $heroRow = $database->query_return("SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $inspectUid LIMIT 1");
    if (!empty($heroRow[0])) {
        $inspectHero = $heroRow[0];
    }
    $inspectInventory = $heroItems->getInventory($inspectUid);
}

// Fetch Recent Logs
$recentLogs = $database->query_return("
    SELECT * FROM " . TB_PREFIX . "admin_log 
    WHERE log LIKE '%Hero Item%' OR log LIKE '%Silver%'
    ORDER BY id DESC LIMIT 15
");
?>

<style>
.herom-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #e2e8f0;
    margin: 10px 0 30px 0;
}
.herom-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #0f172a 100%);
    border: 1px solid #6366f1;
    border-radius: 10px;
    padding: 18px 22px;
    margin-bottom: 18px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}
.herom-header h2 {
    margin: 0 0 6px 0;
    color: #fbbf24;
    font-size: 22px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}
.herom-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 14px;
}
.herom-stat-card {
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 10px 14px;
}
.herom-stat-label {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 600;
}
.herom-stat-val {
    font-size: 20px;
    font-weight: bold;
    color: #f8fafc;
    margin-top: 2px;
}

/* Tabs */
.herom-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 16px;
    border-bottom: 2px solid #334155;
    padding-bottom: 2px;
}
.herom-tab-btn {
    background: #1e293b;
    color: #94a3b8;
    padding: 9px 18px;
    border-radius: 8px 8px 0 0;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    border: 1px solid #334155;
    border-bottom: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.herom-tab-btn:hover {
    background: #334155;
    color: #fff;
}
.herom-tab-btn.active {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}

/* Card & Forms */
.herom-card {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.herom-card h3 {
    margin: 0 0 14px 0;
    color: #60a5fa;
    font-size: 16px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}
.herom-form-group {
    margin-bottom: 16px;
}
.herom-form-group label {
    display: block;
    font-size: 12px;
    font-weight: bold;
    color: #cbd5e1;
    margin-bottom: 6px;
}
.herom-control {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 6px;
    padding: 8px 12px;
    color: #fff;
    font-size: 13px;
    box-sizing: border-box;
}
.herom-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.3);
}
.herom-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.herom-btn:hover {
    background: #1d4ed8;
}
.herom-btn-danger {
    background: #dc2626;
    color: #fff;
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
}
.herom-btn-danger:hover {
    background: #b91c1c;
}
.herom-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.herom-table th {
    background: #1e293b;
    color: #94a3b8;
    padding: 9px 12px;
    text-align: left;
    border-bottom: 1px solid #334155;
    font-weight: 600;
}
.herom-table td {
    padding: 9px 12px;
    border-bottom: 1px solid #1e293b;
    vertical-align: middle;
}
.herom-table tr:hover td {
    background: rgba(51, 65, 85, 0.4);
}
.herom-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}
.badge-tier1 { background: #334155; color: #cbd5e1; }
.badge-tier2 { background: #0369a1; color: #bae6fd; }
.badge-tier3 { background: #7c2d12; color: #fed7aa; }
.badge-tier4 { background: #581c87; color: #f3e8ff; border: 1px solid #a855f7; }
.badge-equipped { background: #065f46; color: #6ee7b7; }
.badge-bag { background: #475569; color: #e2e8f0; }

.flash-box {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    line-height: 1.5;
}
.flash-success {
    background: #064e3b;
    border: 1px solid #10b981;
    color: #ecfdf5;
}
.flash-error {
    background: #7f1d1d;
    border: 1px solid #ef4444;
    color: #fef2f2;
}
</style>

<div class="herom-container">

    <!-- Header & Server Hero Overview -->
    <div class="herom-header">
        <h2>👑 Hero Inventory &amp; Equipment Manager</h2>
        <div style="font-size:12px; color:#cbd5e1;">
            Kelola dan bagikan senjata, zirah, kuda, helm, consumables (kandang, perban, tablet), dan silver ke 1 user atau seluruh pemain server.
        </div>
        <div class="herom-stats-grid">
            <div class="herom-stat-card">
                <div class="herom-stat-label">👥 Total Hero Terdaftar</div>
                <div class="herom-stat-val" style="color:#38bdf8;"><?php echo number_format($totalHeroes); ?> <span style="font-size:12px; font-weight:normal; color:#94a3b8;">(<?php echo number_format($aliveHeroes); ?> Hidup)</span></div>
            </div>
            <div class="herom-stat-card">
                <div class="herom-stat-label">🎒 Total Item di Server</div>
                <div class="herom-stat-val" style="color:#34d399;"><?php echo number_format($totalItemsInServer); ?> Unit</div>
            </div>
            <div class="herom-stat-card">
                <div class="herom-stat-label">💰 Total Silver Beredar</div>
                <div class="herom-stat-val" style="color:#fbbf24;"><?php echo number_format($totalSilver); ?> Silver</div>
            </div>
            <div class="herom-stat-card">
                <div class="herom-stat-label">📖 Total Katalog Item</div>
                <div class="herom-stat-val" style="color:#c084fc;"><?php echo count($heroItemCatalog); ?> Macam Equip</div>
            </div>
        </div>
    </div>

    <!-- Flash Alerts -->
    <?php if (!empty($flashSuccess)) { ?>
        <div class="flash-box flash-success"><?php echo $flashSuccess; ?></div>
    <?php } ?>
    <?php if (!empty($flashError)) { ?>
        <div class="flash-box flash-error"><?php echo $flashError; ?></div>
    <?php } ?>

    <!-- Navigation Tabs -->
    <div class="herom-tabs">
        <a href="admin.php?p=heroManager&tab=give" class="herom-tab-btn <?php echo $activeTab === 'give' ? 'active' : ''; ?>">
            🎁 Hadiahkan Equip / Item
        </a>
        <a href="admin.php?p=heroManager&tab=inspect<?php echo $inspectUid > 0 ? '&inspect_uid=' . $inspectUid : ''; ?>" class="herom-tab-btn <?php echo $activeTab === 'inspect' ? 'active' : ''; ?>">
            🎒 Inspeksi &amp; Kelola Inventory Pemain
        </a>
        <a href="admin.php?p=heroManager&tab=logs" class="herom-tab-btn <?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
            📜 Riwayat Log Hadiah
        </a>
    </div>

    <!-- ==================================================================== -->
    <!-- TAB 1: GIVE ITEMS / EQUIPMENT                                        -->
    <!-- ==================================================================== -->
    <?php if ($activeTab === 'give') { ?>
    <div class="herom-card">
        <h3>🎁 Form Pembagian Hadiah Item &amp; Equipment Hero</h3>
        <p style="font-size:12px; color:#94a3b8; margin-top:-6px; margin-bottom:18px;">
            Pilih item dari katalog dan tentukan penerima. Anda bisa memberikan item ke 1 user, aliansi, suku tertentu, atau ke seluruh server sekaligus.
        </p>

        <form method="post" action="admin.php?p=heroManager&tab=give" id="giveItemForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="hero_action" value="give_item">

            <!-- Bagian 1: Pilih Target Penerima -->
            <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:18px;">
                <label style="color:#38bdf8; font-size:13px; font-weight:bold; display:block; margin-bottom:10px;">
                    🎯 1. Tentukan Target Penerima:
                </label>

                <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:14px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:#f8fafc;">
                        <input type="radio" name="target_type" value="single" checked onchange="toggleTargetType(this.value)">
                        <b>1 Pemain Tertentu</b>
                    </label>
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:#f8fafc;">
                        <input type="radio" name="target_type" value="all" onchange="toggleTargetType(this.value)">
                        <b>Semua Pemain Server (Mass Gift)</b>
                    </label>
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:#f8fafc;">
                        <input type="radio" name="target_type" value="alliance" onchange="toggleTargetType(this.value)">
                        <b>Seluruh Anggota Aliansi</b>
                    </label>
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:#f8fafc;">
                        <input type="radio" name="target_type" value="tribe" onchange="toggleTargetType(this.value)">
                        <b>Berdasarkan Suku (Tribe)</b>
                    </label>
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:#f8fafc;">
                        <input type="radio" name="target_type" value="selected" onchange="toggleTargetType(this.value)">
                        <b>Pilih Manual (Checklist Banyak User)</b>
                    </label>
                </div>

                <!-- Sub-Option: Single Player -->
                <div id="target_single_box" style="display:block; margin-top:10px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                        <div>
                            <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Pilih dari Daftar Pemain:</label>
                            <select name="single_uid" class="herom-control">
                                <option value="0">-- Pilih Pemain --</option>
                                <?php foreach ($playersList as $p) { 
                                    $isBot = class_exists('BotAI') && BotAI::isBotUser((int)$p['id']);
                                ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo $inspectUid === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['username']); ?> (UID: <?php echo (int)$p['id']; ?> | Suku: <?php echo $tribeNames[(int)$p['tribe']] ?? 'Unknown'; ?> | <?php echo !empty($p['ali_tag']) ? '[' . htmlspecialchars($p['ali_tag']) . ']' : 'Tanpa Aliansi'; ?><?php echo $isBot ? ' 🤖 Bot' : ''; ?>)
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Atau Ketik Username Manual:</label>
                            <input type="text" name="manual_username" class="herom-control" placeholder="Contoh: mkonmko">
                        </div>
                    </div>
                </div>

                <!-- Sub-Option: Alliance -->
                <div id="target_alliance_box" style="display:none; margin-top:10px;">
                    <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Pilih Aliansi Penerima:</label>
                    <select name="target_alliance" class="herom-control">
                        <option value="0">-- Pilih Aliansi --</option>
                        <?php foreach ($alliancesList as $ali) { ?>
                        <option value="<?php echo (int)$ali['id']; ?>">
                            [<?php echo htmlspecialchars($ali['tag']); ?>] <?php echo htmlspecialchars($ali['name']); ?> (ID: <?php echo (int)$ali['id']; ?>)
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Sub-Option: Tribe -->
                <div id="target_tribe_box" style="display:none; margin-top:10px;">
                    <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Pilih Suku Penerima:</label>
                    <select name="target_tribe" class="herom-control">
                        <?php foreach ($tribeNames as $tId => $tName) { 
                            if ($tId === 4 || $tId === 5) continue; // Skip nature/natars
                        ?>
                        <option value="<?php echo $tId; ?>"><?php echo $tName; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <!-- Sub-Option: Multi-Select Checklist -->
                <div id="target_selected_box" style="display:none; margin-top:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="font-size:11px; color:#94a3b8; margin:0;">Centang Pemain yang Ingin Diberi Hadiah:</label>
                        <div>
                            <button type="button" onclick="toggleAllCheckboxes(true)" style="background:#334155; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:10px; cursor:pointer;">Pilih Semua</button>
                            <button type="button" onclick="toggleAllCheckboxes(false)" style="background:#334155; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:10px; cursor:pointer;">Kosongkan</button>
                        </div>
                    </div>
                    <div style="max-height:180px; overflow-y:auto; background:#0f172a; border:1px solid #334155; border-radius:6px; padding:10px;">
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:6px;">
                            <?php foreach ($playersList as $p) { 
                                $isBot = class_exists('BotAI') && BotAI::isBotUser((int)$p['id']);
                            ?>
                            <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#cbd5e1; cursor:pointer;">
                                <input type="checkbox" name="selected_uids[]" value="<?php echo (int)$p['id']; ?>" class="user-check">
                                <span><?php echo htmlspecialchars($p['username']); ?> <?php echo $isBot ? '<span style="color:#22c55e;">[Bot]</span>' : ''; ?></span>
                            </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Saringan Pengaman -->
                <div style="display:flex; gap:16px; margin-top:14px; padding-top:10px; border-top:1px dashed #334155; font-size:11px;">
                    <label style="display:flex; align-items:center; gap:6px; color:#a7f3d0; cursor:pointer;">
                        <input type="checkbox" name="exclude_bots" value="1" checked>
                        <span>🤖 Kecualikan Akun Bot AI (Hanya berikan ke pemain manusia asli)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; color:#cbd5e1; cursor:pointer;">
                        <input type="checkbox" name="exclude_banned" value="1" checked>
                        <span>🚫 Kecualikan Akun Banned / Terblokir</span>
                    </label>
                </div>
            </div>

            <!-- Bagian 2: Pilih Equip / Item dari Katalog -->
            <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:18px;">
                <label style="color:#fbbf24; font-size:13px; font-weight:bold; display:block; margin-bottom:10px;">
                    ⚔️ 2. Pilih Item &amp; Equipment Hero:
                </label>

                <!-- Filter Kategori & Tier Cerdas -->
                <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Filter Berdasarkan Slot / Jenis:</label>
                        <select id="slot_filter" class="herom-control" onchange="filterCatalogItems()">
                            <option value="0">-- Tampilkan Semua Jenis Slot (Semua 201 Item) --</option>
                            <?php foreach ($slotNames as $sId => $sName) { ?>
                            <option value="<?php echo $sId; ?>"><?php echo $sName; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Filter Tier:</label>
                        <select id="tier_filter" class="herom-control" onchange="filterCatalogItems()">
                            <option value="0">-- Semua Tier --</option>
                            <option value="1">Tier 1 (Dasar / Pemula)</option>
                            <option value="2">Tier 2 (Menengah / Solid)</option>
                            <option value="3">Tier 3 (Tinggi / Sultan)</option>
                            <option value="4">Tier 4 (Legendaris / Artefak)</option>
                        </select>
                    </div>
                </div>

                <!-- Dropdown Item Utama -->
                <div class="herom-form-group">
                    <label>Pilih Item dari Katalog:</label>
                    <select name="item_id" id="catalog_select" class="herom-control" style="font-size:13px; font-weight:bold; padding:9px 12px;" required>
                        <?php foreach ($heroItemCatalog as $iId => $def) { 
                            $slotId = (int)$def['slot'];
                            $tier = (int)($def['tier'] ?? 1);
                            $slotLabel = $slotNames[$slotId] ?? 'Slot ' . $slotId;
                            
                            $bonusDesc = [];
                            if (!empty($def['bonus'])) {
                                foreach ($def['bonus'] as $bk => $bv) {
                                    if (is_array($bv)) {
                                        $bonusDesc[] = $bk . '=' . json_encode($bv);
                                    } else {
                                        $bonusDesc[] = "+$bv $bk";
                                    }
                                }
                            }
                            $bonusStr = !empty($bonusDesc) ? ' [' . implode(', ', $bonusDesc) . ']' : '';
                        ?>
                        <option value="<?php echo $iId; ?>" data-slot="<?php echo $slotId; ?>" data-tier="<?php echo $tier; ?>">
                            #<?php echo $iId; ?>: <?php echo htmlspecialchars($def['name']); ?> (T<?php echo $tier; ?>, <?php echo $slotLabel; ?>)<?php echo htmlspecialchars($bonusStr); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:10px;">
                    <div>
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Jumlah / Quantity:</label>
                        <input type="number" name="quantity" class="herom-control" value="1" min="1" max="10000" required>
                        <span style="font-size:10px; color:#64748b; display:block; margin-top:2px;">
                            Untuk senjata/zirah/sepatu/kuda default: 1. Untuk tas/perban/kandang bisa diisi lebih (misal: 20x kandang).
                        </span>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:4px;">Bonus Silver (Opsional):</label>
                        <input type="number" name="silver_bonus" class="herom-control" value="0" min="0" step="50">
                        <span style="font-size:10px; color:#64748b; display:block; margin-top:2px;">
                            Tambahan saldo Silver langsung ke akun hero pemain penerima (misal: +1.000 Silver).
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bagian 3: Pesan In-Game (IGM) Resmi -->
            <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:20px;">
                <label style="display:flex; align-items:center; gap:8px; color:#34d399; font-size:13px; font-weight:bold; cursor:pointer;">
                    <input type="checkbox" name="send_igm" value="1" checked onchange="document.getElementById('igm_content_box').style.display = this.checked ? 'block' : 'none';">
                    <span>✉️ 3. Kirim Surat Notifikasi Hadiah ke Kotak Masuk Pemain (In-Game Message)</span>
                </label>

                <div id="igm_content_box" style="margin-top:12px; display:block;">
                    <div class="herom-form-group">
                        <label style="font-size:11px; color:#94a3b8;">Subjek Surat:</label>
                        <input type="text" name="igm_topic" class="herom-control" value="🎁 Hadiah Hero Resmi dari Administrator!">
                    </div>
                    <div class="herom-form-group" style="margin-bottom:0;">
                        <label style="font-size:11px; color:#94a3b8;">Isi Surat: <span style="font-size:10px; color:#64748b;">(Gunakan tag {item}, {qty}, {silver} untuk teks dinamis)</span></label>
                        <textarea name="igm_message" class="herom-control" rows="4" style="line-height:1.4;">Selamat! Akun Anda telah menerima hadiah item hero resmi dari Administrator server:

• Item: {item} (Jumlah: {qty}x)
• Bonus Silver: +{silver} Silver

Item telah langsung masuk ke dalam tas inventory Hero Anda. Terima kasih atas partisipasi aktif Anda di server TravianZ!</textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="herom-btn" style="background:#059669; font-size:14px; padding:11px 26px;">
                🚀 Kirim &amp; Bagikan Hadiah Sekarang
            </button>
        </form>
    </div>
    <?php } ?>

    <!-- ==================================================================== -->
    <!-- TAB 2: INSPECT & MANAGE PLAYER INVENTORY                             -->
    <!-- ==================================================================== -->
    <?php if ($activeTab === 'inspect') { ?>
    <div class="herom-card">
        <h3>🎒 Inspeksi &amp; Kelola Inventory Hero Pemain</h3>
        <p style="font-size:12px; color:#94a3b8; margin-top:-6px; margin-bottom:16px;">
            Pilih seorang pemain untuk melihat rincian perlengkapan hero yang sedang dipakai, isi tas inventori, saldo silver, atau menghapus item tertentu.
        </p>

        <!-- Selector Pemain -->
        <form method="get" action="admin.php" style="margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="p" value="heroManager">
            <input type="hidden" name="tab" value="inspect">
            <select name="inspect_uid" class="herom-control" style="width:auto; min-width:320px;">
                <option value="0">-- Pilih Pemain untuk Diinspeksi --</option>
                <?php foreach ($playersList as $p) { ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo $inspectUid === (int)$p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['username']); ?> (UID: <?php echo (int)$p['id']; ?>)
                </option>
                <?php } ?>
            </select>
            <button type="submit" class="herom-btn">🔍 Buka Inventory</button>
        </form>

        <?php if ($inspectUser && $inspectHero) { ?>
        <!-- Hero Profile Card -->
        <div style="background:#1e293b; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h4 style="margin:0; color:#38bdf8; font-size:16px;">
                        👑 <?php echo htmlspecialchars($inspectHero['name'] ?? 'Hero'); ?> (Milik: <b><?php echo htmlspecialchars($inspectUser['username']); ?></b>)
                    </h4>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">
                        Level: <b><?php echo (int)$inspectHero['level']; ?></b> | 
                        EXP: <b><?php echo number_format((int)$inspectHero['experience']); ?></b> | 
                        Darah (HP): <b><?php echo round((float)$inspectHero['health']); ?>%</b> | 
                        Status: <b><?php echo empty($inspectHero['dead']) ? '🟢 Hidup' : '🔴 Gugur'; ?></b>
                    </div>
                </div>

                <!-- Form Ubah Silver -->
                <form method="post" action="admin.php?p=heroManager&tab=inspect&inspect_uid=<?php echo $inspectUid; ?>" style="display:flex; align-items:center; gap:8px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="hero_action" value="set_silver">
                    <input type="hidden" name="user_uid" value="<?php echo $inspectUid; ?>">
                    <span style="font-size:12px; color:#fbbf24; font-weight:bold;">💰 Saldo Silver:</span>
                    <input type="number" name="new_silver" value="<?php echo (int)$inspectHero['silver']; ?>" min="0" style="width:100px; padding:5px 8px; font-size:12px; background:#0f172a; color:#fff; border:1px solid #334155; border-radius:4px; text-align:right;">
                    <button type="submit" class="herom-btn" style="padding:5px 12px; font-size:11px;">Update Silver</button>
                </form>
            </div>
        </div>

        <!-- Inventory Items Table -->
        <h4 style="color:#fbbf24; font-size:13px; margin:16px 0 8px 0;">🎒 Isi Tas &amp; Perlengkapan Hero (<?php echo count($inspectInventory); ?> Item)</h4>
        <?php if (!empty($inspectInventory)) { ?>
        <table class="herom-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID Baris</th>
                    <th>Nama Item</th>
                    <th>Kategori Slot</th>
                    <th>Tier</th>
                    <th style="text-align:center;">Jumlah</th>
                    <th style="text-align:center;">Status</th>
                    <th style="width:80px; text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspectInventory as $itemRow) { 
                    $tDef = $heroItemCatalog[(int)$itemRow['itemid']] ?? null;
                    $tierNum = (int)($tDef['tier'] ?? 1);
                    $slotNum = (int)$itemRow['slot'];
                ?>
                <tr>
                    <td>#<?php echo (int)$itemRow['id']; ?></td>
                    <td>
                        <b style="color:#f8fafc;"><?php echo htmlspecialchars($itemRow['name']); ?></b>
                        <span style="font-size:10px; color:#64748b; display:block;">(Item ID: #<?php echo (int)$itemRow['itemid']; ?>)</span>
                    </td>
                    <td><?php echo $slotNames[$slotNum] ?? 'Slot ' . $slotNum; ?></td>
                    <td><span class="herom-badge badge-tier<?php echo $tierNum; ?>">Tier <?php echo $tierNum; ?></span></td>
                    <td style="text-align:center;"><b><?php echo (int)$itemRow['quantity']; ?>x</b></td>
                    <td style="text-align:center;">
                        <?php if (!empty($itemRow['equipped'])) { ?>
                            <span class="herom-badge badge-equipped">Sedang Dipakai</span>
                        <?php } else { ?>
                            <span class="herom-badge badge-bag">Di Dalam Tas</span>
                        <?php } ?>
                    </td>
                    <td style="text-align:center;">
                        <form method="post" action="admin.php?p=heroManager&tab=inspect&inspect_uid=<?php echo $inspectUid; ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus item ini secara permanen dari tas pemain?');" style="margin:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="hero_action" value="delete_item">
                            <input type="hidden" name="row_id" value="<?php echo (int)$itemRow['id']; ?>">
                            <input type="hidden" name="user_uid" value="<?php echo $inspectUid; ?>">
                            <button type="submit" class="herom-btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { ?>
            <div style="background:#1e293b; padding:20px; text-align:center; color:#94a3b8; border-radius:6px; font-size:13px;">
                Pemain ini belum memiliki satupun item di inventori heronya.
            </div>
        <?php } ?>

        <?php } elseif ($inspectUid > 0) { ?>
            <div style="background:#7f1d1d; border:1px solid #ef4444; color:#fff; padding:12px; border-radius:6px; font-size:12px;">
                Data pemain dengan UID #<?php echo $inspectUid; ?> tidak ditemukan.
            </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- ==================================================================== -->
    <!-- TAB 3: LOGS & HISTORY                                                -->
    <!-- ==================================================================== -->
    <?php if ($activeTab === 'logs') { ?>
    <div class="herom-card">
        <h3>📜 Riwayat Log Hadiah Item Hero</h3>
        <p style="font-size:12px; color:#94a3b8; margin-top:-6px; margin-bottom:16px;">
            Mencatat aktivitas pembagian item dan penyesuaian silver yang dilakukan oleh staf administrator.
        </p>

        <?php if (!empty($recentLogs)) { ?>
        <table class="herom-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID Log</th>
                    <th style="width:120px;">Administrator</th>
                    <th>Rincian Aktivitas</th>
                    <th style="width:140px;">Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log) { ?>
                <tr>
                    <td>#<?php echo (int)$log['id']; ?></td>
                    <td><b style="color:#38bdf8;"><?php echo htmlspecialchars($log['user']); ?></b></td>
                    <td><?php echo htmlspecialchars($log['log']); ?></td>
                    <td style="color:#94a3b8;"><?php echo date('d.m.Y H:i:s', (int)$log['time']); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { ?>
            <div style="background:#1e293b; padding:20px; text-align:center; color:#94a3b8; border-radius:6px; font-size:13px;">
                Belum ada catatan riwayat pemberian item hero.
            </div>
        <?php } ?>
    </div>
    <?php } ?>

</div>

<script>
function toggleTargetType(val) {
    document.getElementById('target_single_box').style.display   = (val === 'single') ? 'block' : 'none';
    document.getElementById('target_alliance_box').style.display = (val === 'alliance') ? 'block' : 'none';
    document.getElementById('target_tribe_box').style.display    = (val === 'tribe') ? 'block' : 'none';
    document.getElementById('target_selected_box').style.display = (val === 'selected') ? 'block' : 'none';
}

function toggleAllCheckboxes(state) {
    var cbs = document.querySelectorAll('.user-check');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = state;
    }
}

function filterCatalogItems() {
    var slotVal = document.getElementById('slot_filter').value;
    var tierVal = document.getElementById('tier_filter').value;
    var select = document.getElementById('catalog_select');
    var opts = select.options;

    for (var i = 0; i < opts.length; i++) {
        var opt = opts[i];
        var itemSlot = opt.getAttribute('data-slot');
        var itemTier = opt.getAttribute('data-tier');

        var matchSlot = (slotVal === '0' || itemSlot === slotVal);
        var matchTier = (tierVal === '0' || itemTier === tierVal);

        if (matchSlot && matchTier) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }

    // Auto select first visible option
    for (var j = 0; j < opts.length; j++) {
        if (opts[j].style.display !== 'none') {
            select.selectedIndex = j;
            break;
        }
    }
}
</script>
