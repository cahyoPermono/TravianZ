<?php
#################################################################################
##  Filename       : addTroops.tpl                                             ##
##  Type           : Admin Panel Frontend & Controller for Troop Manager       ##
#################################################################################

if (empty($_SESSION['access']) || $_SESSION['access'] < (defined('MULTIHUNTER') ? MULTIHUNTER : 8)) {
    die(defined('ACCESS_DENIED_ADMIN') ? ACCESS_DENIED_ADMIN : 'Access Denied: You are not Admin!');
}

$unarray = [
    1 => 'Legionnaire', 'Praetorian', 'Imperian', 'Equites Legati', 'Equites Imperatoris', 'Equites Caesaris', 'Battering Ram', 'Fire Catapult', 'Senator', 'Settler',
    11 => 'Clubswinger', 'Spearman', 'Axeman', 'Scout', 'Paladin', 'Teutonic Knight', 'Ram', 'Catapult', 'Chief', 'Settler',
    21 => 'Phalanx', 'Swordsman', 'Pathfinder', 'Theutates Thunder', 'Druidrider', 'Haeduan', 'Ram', 'Trebuchet', 'Chieftain', 'Settler',
    31 => 'Rat', 'Spider', 'Snake', 'Bat', 'Wild Boar', 'Wolf', 'Bear', 'Crocodile', 'Tiger', 'Elephant',
    41 => 'Pikeman', 'Thorned Warrior', 'Guardsman', 'Birds of Prey', 'Axerider', 'Natarian Knight', 'War Elephant', 'Ballista', 'Natarian Emperor', 'Settler',
    51 => 'Pendekar', 'Pemanah Bambu', 'Prajurit Tombak', 'Ksatria Elang', 'Kavaleri Gajah', 'Panglima Gajah', 'Pelontar Batu', 'Meriam Cetbang', 'Patih', 'Perintis',
    61 => 'Monk', 'Spearman', 'Crossbowman', 'Lightning Cavalry', 'Iron Cavalry', 'Heavy Catapult', 'Battering Ram', 'Dragon Ship', 'General', 'Settler',
    71 => 'Skirmisher', 'Phalanx Warrior', 'Bowman', 'Desert Raider', 'Camel Rider', 'Heavy Siege', 'Ram', 'Siege Tower', 'Sultan', 'Settler',
    81 => 'Amazon Warrior', 'Huntress', 'Shieldmaiden', 'Pegasus Scout', 'Valkyrie', 'War Chariot', 'Battering Ram', 'Catapult', 'Matriarch', 'Settler',
    91 => 'Rebel Militiaman', 'Partisan', 'Insurgent Raider', 'Guerrilla Scout', 'Iron Vanguard', 'Siege Breaker', 'Ballista', 'Trebuchet', 'Warlord', 'Settler'
];

$tribeNames = [
    1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 4 => 'Nature', 5 => 'Natars',
    6 => 'Chinese', 7 => 'Egyptians', 8 => 'Amazons', 9 => 'Rebels', 10 => 'Nusantara'
];

$did = isset($_GET['did']) ? (int)$_GET['did'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$flashSuccess = '';
$flashError = '';

// ==============================================================================
// NATIVE POST ACTION HANDLER (Direct ACP Execution without redirect issues)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetVid = (int)($_POST['id'] ?? $did);
    if ($targetVid > 0) {
        $vRow = $database->getVillage($targetVid);
        if (!empty($vRow)) {
            $uRow = $database->getUserArray($vRow['owner'], 1);
            $vTribe = (int)($uRow['tribe'] ?? 1);
            $baseU = ($vTribe - 1) * 10;

            // 1. One-Click Quick Compensation (Vee attack refund: 197 Macemen + 8 Paladin/Cav + 1 Hero Revived)
            if (isset($_POST['quick_comp'])) {
                if (method_exists($database, 'addUnits')) {
                    $database->addUnits($targetVid);
                }

                $uOwner = (int)$vRow['owner'];
                // Revive / restore Hero in hero table
                $heroCheck = $database->query_return("SELECT heroid, dead, health FROM " . TB_PREFIX . "hero WHERE uid = $uOwner LIMIT 1");
                if (!empty($heroCheck)) {
                    $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 0, health = 100, inrevive = 0, intraining = 0, wref = $targetVid WHERE uid = $uOwner");
                } else {
                    $basicUnit = $baseU + 1;
                    $heroNameEsc = $database->escape(($uRow['username'] ?? 'Player') . "'s Hero");
                    $database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `production`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `autoregen`, `lastupdate`) VALUES ($uOwner, $basicUnit, '$heroNameEsc', $targetVid, 0, 0, 0, 100, 0, 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, " . time() . ")");
                }

                if ($vTribe == 2) {
                    // Teuton: 197 Clubswinger (u11) + 8 Paladin (u15) + Hero
                    $database->query("UPDATE " . TB_PREFIX . "units SET u11 = u11 + 197, u15 = u15 + 8, hero = 1 WHERE vref = $targetVid");
                    $flashSuccess = "🎁 <b>Kompensasi Instan Berhasil!</b> Berhasil menambahkan <b>+197 Clubswinger (u11)</b>, <b>+8 Paladin (u15)</b>, dan <b>Memulihkan Hero (Health 100%)</b> ke desa <b>" . htmlspecialchars($vRow['name']) . "</b> milik <b>" . htmlspecialchars($uRow['username']) . "</b>!";
                } elseif ($vTribe == 1) {
                    // Roman: 197 Legionnaire (u1) + 8 Equites Imperatoris (u5) + Hero
                    $database->query("UPDATE " . TB_PREFIX . "units SET u1 = u1 + 197, u5 = u5 + 8, hero = 1 WHERE vref = $targetVid");
                    $flashSuccess = "🎁 <b>Kompensasi Berhasil!</b> Berhasil menambahkan +197 Legionnaire, +8 Equites Imperatoris, dan Memulihkan Hero (Health 100%) ke desa <b>" . htmlspecialchars($vRow['name']) . "</b>!";
                } elseif ($vTribe == 3) {
                    // Gaul: 197 Phalanx (u21) + 8 Theutates Thunder (u24) + Hero
                    $database->query("UPDATE " . TB_PREFIX . "units SET u21 = u21 + 197, u24 = u24 + 8, hero = 1 WHERE vref = $targetVid");
                    $flashSuccess = "🎁 <b>Kompensasi Berhasil!</b> Berhasil menambahkan +197 Phalanx, +8 Theutates Thunder, dan Memulihkan Hero (Health 100%) ke desa <b>" . htmlspecialchars($vRow['name']) . "</b>!";
                } else {
                    $uBasic = $baseU + 1;
                    $uCav = $baseU + 5;
                    $database->query("UPDATE " . TB_PREFIX . "units SET u$uBasic = u$uBasic + 197, u$uCav = u$uCav + 8, hero = 1 WHERE vref = $targetVid");
                    $flashSuccess = "🎁 <b>Kompensasi Berhasil!</b> Berhasil menambahkan +197 infanteri dasar, +8 kavaleri, dan Memulihkan Hero ke desa <b>" . htmlspecialchars($vRow['name']) . "</b>!";
                }

                // Invalidate in-memory caches
                if (method_exists($database, 'clearHeroCache')) {
                    $database->clearHeroCache();
                }
                if (method_exists($database, 'clearUnitsCache')) {
                    $database->clearUnitsCache();
                }
            }
            // 2. Custom Additive / Direct Set
            elseif (isset($_POST['save_troops'])) {
                if (method_exists($database, 'addUnits')) {
                    $database->addUnits($targetVid);
                }

                $sets = [];
                for ($ui = 1; $ui <= 10; $ui++) {
                    $col = $baseU + $ui;
                    $addNum = (int)($_POST['add_u' . $col] ?? 0);
                    if ($addNum > 0) {
                        $sets[] = "u{$col} = u{$col} + {$addNum}";
                    } elseif (isset($_POST['u' . $col]) && $_POST['u' . $col] !== '') {
                        $exactNum = max(0, (int)$_POST['u' . $col]);
                        $sets[] = "u{$col} = {$exactNum}";
                    }
                }
                if (isset($_POST['hero']) && $_POST['hero'] !== '') {
                    $heroVal = max(0, (int)$_POST['hero']);
                    $sets[] = "hero = {$heroVal}";

                    $uOwner = (int)$vRow['owner'];
                    if ($heroVal == 1) {
                        $heroCheck = $database->query_return("SELECT heroid FROM " . TB_PREFIX . "hero WHERE uid = $uOwner LIMIT 1");
                        if (!empty($heroCheck)) {
                            $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 0, health = 100, inrevive = 0, intraining = 0, wref = $targetVid WHERE uid = $uOwner");
                        } else {
                            $basicUnit = $baseU + 1;
                            $heroNameEsc = $database->escape(($uRow['username'] ?? 'Player') . "'s Hero");
                            $database->query("INSERT INTO " . TB_PREFIX . "hero (`uid`, `unit`, `name`, `wref`, `level`, `points`, `experience`, `silver`, `production`, `dead`, `health`, `attack`, `defence`, `attackbonus`, `defencebonus`, `regeneration`, `resources`, `res_type`, `autoregen`, `lastupdate`) VALUES ($uOwner, $basicUnit, '$heroNameEsc', $targetVid, 0, 0, 0, 100, 0, 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, " . time() . ")");
                        }
                    } elseif ($heroVal == 0) {
                        $database->query("UPDATE " . TB_PREFIX . "hero SET dead = 1, health = 0, inrevive = 0, intraining = 0 WHERE uid = $uOwner");
                    }
                }
                if (!empty($sets)) {
                    $database->query("UPDATE " . TB_PREFIX . "units SET " . implode(', ', $sets) . " WHERE vref = $targetVid");
                    $flashSuccess = "✅ <b>Berhasil!</b> Jumlah pasukan pada desa <b>" . htmlspecialchars($vRow['name']) . "</b> telah berhasil diperbarui!";
                } else {
                    $flashSuccess = "Tidak ada perubahan jumlah pasukan yang dikirim.";
                }

                // Invalidate in-memory caches
                if (method_exists($database, 'clearHeroCache')) {
                    $database->clearHeroCache();
                }
                if (method_exists($database, 'clearUnitsCache')) {
                    $database->clearUnitsCache();
                }
            }

            if (method_exists($database, 'addStarvationData')) {
                $database->addStarvationData($targetVid);
            }
            $did = $targetVid;
        } else {
            $flashError = "Desa dengan ID #$targetVid tidak ditemukan di database!";
        }
    }
}
?>

<style>
.troops-mgr-wrap { max-width: 960px; margin: 16px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f8fafc; }
.troops-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.3); }
.troops-head { background: linear-gradient(135deg, #0284c7, #2563eb); color: #fff; padding: 14px 18px; font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
.troops-subhead { background: #1e293b; padding: 12px 18px; border-bottom: 1px solid #334155; font-size: 13px; color: #94a3b8; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.troops-grid { padding: 12px 18px; }
.troop-row { display: grid; grid-template-columns: 36px 1fr 90px 140px 110px; align-items: center; padding: 10px 12px; border-bottom: 1px solid #1e293b; gap: 12px; }
.troop-row:last-child { border-bottom: 0; }
.troop-row:hover { background: #1e293b; border-radius: 8px; }
.troop-row img { width: 24px; height: 24px; display: block; margin: 0 auto; }
.troop-name { font-size: 13px; font-weight: 600; color: #f1f5f9; }
.troop-now { font-size: 12px; color: #94a3b8; font-family: monospace; text-align: center; }
.troop-now b { color: #38bdf8; font-size: 13px; }
.troop-input { display: flex; flex-direction: column; gap: 4px; }
.troop-input label { font-size: 10px; color: #64748b; font-weight: bold; text-transform: uppercase; }
.troop-input input { width: 100%; box-sizing: border-box; background: #0b1220; color: #f8fafc; border: 1px solid #334155; border-radius: 6px; padding: 6px 8px; font-size: 13px; font-family: monospace; text-align: right; }
.troop-input input:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56,189,248,0.2); }
.preset-bar { background: linear-gradient(135deg, #1e293b, #0f172a); border: 1px dashed #38bdf8; border-radius: 8px; padding: 12px 16px; margin: 14px 18px; font-size: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.btn-preset { background: #0284c7; color: #fff; border: 0; padding: 6px 12px; border-radius: 5px; font-size: 11px; font-weight: bold; cursor: pointer; transition: 0.15s; }
.btn-preset:hover { background: #0369a1; }
.btn-vee { background: #be185d; color: #fff; border: 0; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(190,24,93,0.3); }
.btn-vee:hover { background: #9d174d; }
.troops-foot { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: #0b1220; border-top: 1px solid #1e293b; }
.btn-save { background: #22c55e; color: #fff; border: 0; padding: 9px 20px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; }
.btn-save:hover { background: #16a34a; }
.btn-back { background: #334155; color: #cbd5e1; border: 0; padding: 8px 14px; border-radius: 6px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.btn-back:hover { background: #475569; color: #fff; }
.search-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.search-table th { background: #1e293b; color: #94a3b8; text-align: left; padding: 10px 14px; font-weight: 600; font-size: 11px; text-transform: uppercase; }
.search-table td { padding: 10px 14px; border-bottom: 1px solid #1e293b; color: #cbd5e1; }
.search-table tr:hover td { background: #1e293b; }
.badge-tribe { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #334155; color: #93c5fd; }
.btn-select { background: #0284c7; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 11px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; }
.btn-select:hover { background: #0369a1; }
.alert-success { background: #064e3b; color: #86efac; border: 1px solid #059669; padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; line-height: 1.5; }
.alert-error { background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; }
</style>

<div class="troops-mgr-wrap">

<?php if (!empty($flashSuccess)): ?>
    <div class="alert-success"><?php echo $flashSuccess; ?></div>
<?php elseif (isset($_GET['saved'])): ?>
    <div class="alert-success">
        ✅ <b>Berhasil!</b> Jumlah pasukan pada desa telah diperbarui dan data konsumsi gandum (starvation) berhasil dihitung ulang.
    </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
    <div class="alert-error"><?php echo $flashError; ?></div>
<?php endif; ?>

<?php if ($did > 0): 
    $village = $database->getVillage($did);
    if (!empty($village)):
        $user = $database->getUserArray($village['owner'], 1);
        $tribe = (int)($user['tribe'] ?? 1);
        $img = ($tribe - 1) * 10;
        $units = $database->getUnit($did, false);
        $coor = $database->getCoor($did);
        $tName = $tribeNames[$tribe] ?? 'Unknown';
?>
    <!-- FORM EDIT / TAMBAH PASUKAN PADA DESA -->
    <div class="troops-card">
        <div class="troops-head">
            <div>⚔️ Troop Manager – Desa "<?php echo htmlspecialchars($village['name']); ?>" (ID: #<?php echo $did; ?>)</div>
            <div style="font-size:12px; font-weight:normal; opacity:0.9;">Koordinat: (<?php echo $coor['x'] . '|' . $coor['y']; ?>)</div>
        </div>

        <div class="troops-subhead">
            <div>👤 Pemilik: <b><?php echo htmlspecialchars($user['username']); ?></b> (UID: <?php echo (int)$user['id']; ?>) | Suku: <span class="badge-tribe"><?php echo $tName; ?></span></div>
            <div>👥 Populasi: <b><?php echo number_format((int)$village['pop']); ?></b></div>
        </div>

        <?php
        $compLabel = "⚡ Kompensasi Penuh (+197 Clubswinger, +8 Paladin & Revive Hero)";
        $basicSlot = $img + 1;
        $cavSlot = $img + 5;
        if ($tribe == 1) {
            $compLabel = "⚡ Kompensasi Penuh (+197 Legionnaire, +8 Equites Imperatoris & Revive Hero)";
            $cavSlot = $img + 5;
        } elseif ($tribe == 2) {
            $compLabel = "⚡ Kompensasi Penuh Vee (+197 Clubswinger, +8 Paladin & Revive Hero)";
            $cavSlot = $img + 5; // u15 Paladin
        } elseif ($tribe == 3) {
            $compLabel = "⚡ Kompensasi Penuh (+197 Phalanx, +8 Theutates Thunder & Revive Hero)";
            $cavSlot = $img + 4; // u24 Theutates Thunder
        } else {
            $compLabel = "⚡ Kompensasi Penuh (+197 Infanteri, +8 Kavaleri & Revive Hero)";
        }
        $basicName = $unarray[$basicSlot] ?? 'Infanteri Utama';
        $cavName = $unarray[$cavSlot] ?? 'Kavaleri Utama';
        ?>

        <!-- 1-Click Direct Compensation for Lost Troops -->
        <div class="preset-bar">
            <div>
                <b style="color:#f472b6;">🎁 Kompensasi Cepat 1-Klik:</b> Tambahkan langsung pasukan ganti rugi:
            </div>
            <form method="POST" action="admin.php?p=addTroops&did=<?php echo $did; ?>" style="margin:0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $did; ?>">
                <input type="hidden" name="quick_comp" value="1">
                <button type="submit" class="btn-vee" onclick="return confirm('Terapkan langsung kompensasi pasukan ganti rugi ke desa ini?');">
                    <?php echo $compLabel; ?>
                </button>
            </form>
        </div>

        <form action="admin.php?p=addTroops&did=<?php echo $did; ?>" method="POST" style="margin:0;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $did; ?>">
            <input type="hidden" name="save_troops" value="1">

            <!-- Quick Add Helpers -->
            <div style="background:#131d31; border:1px solid #1e293b; border-radius:8px; padding:10px 16px; margin:0 18px 12px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <div style="font-size:11px; color:#94a3b8; font-weight:600;">
                    ⚡ Tambah Cepat ke Input:
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn-preset" onclick="quickAdd(<?php echo $basicSlot; ?>, 50);">+50 <?php echo htmlspecialchars($basicName); ?></button>
                    <button type="button" class="btn-preset" onclick="quickAdd(<?php echo $basicSlot; ?>, 100);">+100 <?php echo htmlspecialchars($basicName); ?></button>
                    <button type="button" class="btn-preset" onclick="quickAdd(<?php echo $basicSlot; ?>, 197);">+197 <?php echo htmlspecialchars($basicName); ?></button>
                    <button type="button" class="btn-preset" style="background:#4f46e5;" onclick="quickAdd(<?php echo $cavSlot; ?>, 8);">+8 <?php echo htmlspecialchars($cavName); ?></button>
                    <button type="button" class="btn-preset" style="background:#4f46e5;" onclick="quickAdd(<?php echo $cavSlot; ?>, 50);">+50 <?php echo htmlspecialchars($cavName); ?></button>
                    <button type="button" class="btn-preset" style="background:#475569;" onclick="resetAdds();">↺ Reset Tambahan</button>
                </div>
            </div>

            <div class="troops-grid">
                <div style="display:grid; grid-template-columns:36px 1fr 90px 140px 110px; gap:12px; padding:6px 12px; font-size:11px; color:#64748b; font-weight:bold; border-bottom:1px solid #334155;">
                    <div>ICON</div>
                    <div>JENIS PASUKAN</div>
                    <div style="text-align:center;">SAAT INI</div>
                    <div>TAMBAH JUMLAH (+)</div>
                    <div>UBAH TOTAL LANGSUNG</div>
                </div>

                <?php for ($i = 1; $i <= 10; $i++): 
                    $uid = $img + $i;
                    $current = (int)($units['u' . $uid] ?? 0);
                    $uName = $unarray[$uid] ?? ("Unit " . $uid);
                ?>
                    <div class="troop-row">
                        <div><img src="../img/un/u/<?php echo $uid; ?>.gif" alt="<?php echo htmlspecialchars($uName); ?>" onerror="this.src='../img/un/u/hero.gif';"></div>
                        <div class="troop-name">
                            <?php echo htmlspecialchars($uName); ?>
                            <div style="font-size:10px; color:#64748b; font-weight:normal;">Slot: u<?php echo $uid; ?></div>
                        </div>
                        <div class="troop-now">
                            <b><?php echo number_format($current); ?></b>
                        </div>
                        <div class="troop-input">
                            <label>Tambah (+)</label>
                            <input type="number" name="add_u<?php echo $uid; ?>" id="add_u<?php echo $uid; ?>" placeholder="+0" min="0">
                        </div>
                        <div class="troop-input">
                            <label>Ubah Total</label>
                            <input type="number" name="u<?php echo $uid; ?>" id="u<?php echo $uid; ?>" value="<?php echo $current; ?>" min="0">
                        </div>
                    </div>
                <?php endfor; ?>

                <!-- Hero Section -->
                <?php $currentHero = (int)($units['hero'] ?? 0); ?>
                <div class="troop-row" style="background:#131d31; border-radius:8px; margin-top:8px;">
                    <div><img src="../img/un/u/hero.gif" alt="Hero"></div>
                    <div class="troop-name" style="color:#f59e0b;">
                        Hero / Pahlawan
                        <div style="font-size:10px; color:#94a3b8; font-weight:normal;">Prajurit Pahlawan Desa</div>
                    </div>
                    <div class="troop-now">
                        <b><?php echo $currentHero > 0 ? '1 (Ada)' : '0 (Tidak Ada)'; ?></b>
                    </div>
                    <div class="troop-input" style="grid-column: span 2;">
                        <label>Status Hero di Desa</label>
                        <select name="hero" style="background:#0b1220; color:#fff; border:1px solid #334155; border-radius:6px; padding:6px 8px; font-size:12px;">
                            <option value="<?php echo $currentHero; ?>" selected>Pertahankan Saat Ini (<?php echo $currentHero; ?>)</option>
                            <option value="1">Siagakan Hero di Desa (1)</option>
                            <option value="0">Tarik / Kosongkan Hero (0)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="troops-foot">
                <a href="admin.php?p=addTroops" class="btn-back">
                    ⬅ Kembali ke Pencarian Desa
                </a>
                <button type="submit" class="btn-save">
                    💾 Simpan & Perbarui Pasukan Sekarang
                </button>
            </div>
        </form>
    </div>

<?php else: ?>
    <div class="alert-error">
        Desa dengan ID #<?php echo $did; ?> tidak ditemukan! Silakan pilih desa di bawah.
    </div>
<?php endif; ?>

<?php else: ?>

    <!-- PENCARIAN & DAFTAR DESA (JIKA BELUM MEMILIH DESA) -->
    <?php
    $searchQ = trim((string)($_GET['q'] ?? ''));
    $whereClause = "";
    if ($searchQ !== '') {
        $qEsc = $database->escape($searchQ);
        $whereClause = "WHERE u.username LIKE '%$qEsc%' OR v.name LIKE '%$qEsc%' OR v.wref = " . (int)$searchQ;
    }

    $villagesList = $database->query_return("
        SELECT v.wref, v.name as vname, v.pop, w.x, w.y, u.id as uid, u.username, u.tribe, u.is_bot
        FROM " . TB_PREFIX . "vdata v
        JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
        JOIN " . TB_PREFIX . "users u ON v.owner = u.id
        $whereClause
        ORDER BY v.wref DESC
        LIMIT 50
    ");

    // Quick auto-detection for player Vee / village Fuuuiiyooohh
    $veeVillages = $database->query_return("
        SELECT v.wref, v.name as vname, v.pop, w.x, w.y, u.id as uid, u.username, u.tribe
        FROM " . TB_PREFIX . "vdata v
        JOIN " . TB_PREFIX . "wdata w ON v.wref = w.id
        JOIN " . TB_PREFIX . "users u ON v.owner = u.id
        WHERE u.username LIKE '%Vee%' OR v.name LIKE '%Fuuuiiyooohh%'
        LIMIT 1
    ");
    $veeRow = !empty($veeVillages) ? $veeVillages[0] : null;
    ?>
    <?php if ($veeRow): ?>
        <div style="background: linear-gradient(135deg, #1e1b4b, #3b0764); border: 2px solid #ec4899; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(236,72,153,0.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div>
                    <div style="font-size:15px; font-weight:800; color:#f472b6; margin-bottom:4px; display:flex; align-items:center; gap:8px;">
                        <span>🎯 Pemain Terdampak Ditemukan:</span> <u><?php echo htmlspecialchars($veeRow['username']); ?></u> – Desa "<?php echo htmlspecialchars($veeRow['vname']); ?>" (#<?php echo (int)$veeRow['wref']; ?>)
                    </div>
                    <div style="font-size:12px; color:#e2e8f0; line-height:1.5;">
                        Pasukan gugur saat menyerang bot berlebih: <b>197 Clubswinger</b>, <b>8 Paladin</b>, dan <b>1 Hero (Pahlawan)</b>.<br>
                        Klik tombol di samping untuk langsung memberikan kompensasi penuh (pasukan bertambah + Hero langsung hidup kembali 100% health).
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <form method="POST" action="admin.php?p=addTroops" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$veeRow['wref']; ?>">
                        <input type="hidden" name="quick_comp" value="1">
                        <button type="submit" class="btn-vee" style="padding:10px 18px; font-size:13px; font-weight:bold; cursor:pointer;" onclick="return confirm('Kirim kompensasi penuh (+197 Clubswinger, +8 Paladin, +1 Revive Hero 100%) ke <?php echo htmlspecialchars(addslashes($veeRow['username'])); ?>?');">
                            🎁 Berikan Kompensasi Penuh Vee Sekarang
                        </button>
                    </form>
                    <a href="admin.php?p=addTroops&did=<?php echo (int)$veeRow['wref']; ?>" class="btn-select" style="padding:10px 14px; font-size:12px;">
                        ✏️ Buka Detail Desa
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="troops-card">
        <div class="troops-head">
            <div>⚔️ Troop Manager – Tambah & Kompensasi Pasukan Pemain</div>
            <a href="admin.php?p=botManager" class="btn-back" style="font-size:11px; padding:4px 10px;">🤖 Ke Bot Manager</a>
        </div>

        <div style="padding:16px 18px; border-bottom:1px solid #1e293b; background:#0b1220;">
            <form method="GET" action="admin.php" style="display:flex; gap:10px; margin:0;">
                <input type="hidden" name="p" value="addTroops">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Ketik Nama Pemain (misal: Vee), Nama Desa (Fuuuiiyooohh), atau ID Desa..." style="flex:1; background:#1e293b; color:#fff; border:1px solid #334155; border-radius:6px; padding:8px 12px; font-size:13px;">
                <button type="submit" class="btn-save" style="padding:8px 16px;">🔍 Cari Desa</button>
                <?php if ($searchQ !== ''): ?>
                    <a href="admin.php?p=addTroops" class="btn-back" style="display:inline-flex; align-items:center;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table class="search-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>NAMA DESA</th>
                        <th>PEMILIK (USER)</th>
                        <th>SUKU</th>
                        <th style="text-align:center;">KOORDINAT</th>
                        <th style="text-align:center;">POPULASI</th>
                        <th style="text-align:right;">AKSI CEPAT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($villagesList)): ?>
                        <?php foreach ($villagesList as $row): 
                            $tribeId = (int)$row['tribe'];
                            $tName = $tribeNames[$tribeId] ?? 'Unknown';
                            $isBot = !empty($row['is_bot']) || stripos($row['username'], 'Bot_') === 0;
                            $wid = (int)$row['wref'];
                        ?>
                            <tr>
                                <td><b>#<?php echo $wid; ?></b></td>
                                <td>
                                    <b><?php echo htmlspecialchars($row['vname']); ?></b>
                                </td>
                                <td>
                                    <b><?php echo htmlspecialchars($row['username']); ?></b>
                                    <?php if ($isBot): ?>
                                        <span style="background:#064e3b; color:#86efac; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:bold;">BOT</span>
                                    <?php else: ?>
                                        <span style="background:#1e3a5f; color:#93c5fd; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:bold;">PLAYER</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge-tribe"><?php echo $tName; ?></span></td>
                                <td style="text-align:center; font-family:monospace;"><?php echo $row['x'] . '|' . $row['y']; ?></td>
                                <td style="text-align:center; font-weight:bold;"><?php echo number_format((int)$row['pop']); ?></td>
                                <td style="text-align:right; white-space:nowrap; display:flex; justify-content:flex-end; gap:6px;">
                                    <!-- 1-Click Vee compensation button -->
                                    <form method="POST" action="admin.php?p=addTroops" style="display:inline; margin:0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $wid; ?>">
                                        <input type="hidden" name="quick_comp" value="1">
                                        <button type="submit" class="btn-vee" style="padding:4px 8px; font-size:11px;" title="Kompensasi langsung +197 prajurit & +8 kavaleri" onclick="return confirm('Kirim kompensasi langsung +197 prajurit dan +8 kavaleri ke desa <?php echo htmlspecialchars(addslashes($row['vname'])); ?> (<?php echo htmlspecialchars(addslashes($row['username'])); ?>)?');">
                                            🎁 Kompensasi (+197 & +8)
                                        </button>
                                    </form>
                                    <a href="admin.php?p=addTroops&did=<?php echo $wid; ?>" class="btn-select">
                                        ✏️ Edit Pasukan
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#64748b;">
                                Tidak ada desa atau pemain yang cocok dengan kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<script type="text/javascript">
function quickAdd(slot, count) {
    var inp = document.getElementById('add_u' + slot);
    if (inp) {
        var v = parseInt(inp.value || 0, 10);
        if (isNaN(v)) v = 0;
        inp.value = v + count;
        inp.style.borderColor = '#22c55e';
    }
}
function resetAdds() {
    var inputs = document.querySelectorAll('input[id^="add_u"]');
    inputs.forEach(function(el) {
        el.value = '';
        el.style.borderColor = '#334155';
    });
}
</script>

</div>