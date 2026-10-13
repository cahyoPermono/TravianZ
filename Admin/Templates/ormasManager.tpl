<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : Admin/Templates/ormasManager.tpl                          ##
##  Type           : Admin Panel Frontend & Engine for Ormas / Preman Manager  ##
##  Purpose        : Web-based GUI to manage, dispatch, and dismiss Ormas      ##
##                   nuisances across all player villages                      ##
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
require_once $repoRoot . '/GameEngine/Ormas.php';
Ormas::ensureTable();

$flashSuccess = '';
$flashError   = '';

// ─── POST ACTION HANDLERS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'dispatch_manual') {
        $targetType = $_POST['target_type'] ?? 'wid';
        $ormasType  = (int)($_POST['ormas_type'] ?? 1);
        $duration   = (int)($_POST['duration'] ?? 3600);
        $targetWid  = 0;

        if ($targetType === 'wid') {
            $targetWid = (int)($_POST['village_id'] ?? 0);
        } elseif ($targetType === 'coords') {
            $x = (int)($_POST['coord_x'] ?? 0);
            $y = (int)($_POST['coord_y'] ?? 0);
            $res = mysqli_query($database->dblink, "SELECT id FROM " . TB_PREFIX . "wdata WHERE x = $x AND y = $y LIMIT 1");
            if ($res && ($wRow = mysqli_fetch_assoc($res))) {
                $targetWid = (int)($wRow['id'] ?? 0);
            }
        } elseif ($targetType === 'username') {
            $uName = trim($_POST['username'] ?? '');
            if ($uName !== '') {
                $uRow = $database->getUserArray($uName, 0);
                if ($uRow && isset($uRow['id'])) {
                    // Ambil ibukota atau desa pertama
                    $vList = $database->getProfileVillages($uRow['id']);
                    if (!empty($vList) && isset($vList[0]['wref'])) {
                        $targetWid = (int)$vList[0]['wref'];
                    }
                }
            }
        }

        if ($targetWid <= 0) {
            $flashError = 'Desa target tidak ditemukan atau koordinat/username tidak valid!';
        } else {
            $vInfo = $database->getVillage($targetWid);
            if (!$vInfo) {
                $flashError = "Desa dengan ID #$targetWid tidak ditemukan dalam basis data!";
            } else {
                $info = Ormas::getOrmasInfo($ormasType);
                if (Ormas::dispatchOrmas($targetWid, $ormasType, $duration)) {
                    $durHours = round($duration / 3600, 1);
                    $flashSuccess = "Berhasil mengerahkan <b>{$info['name']}</b> ({$info['alias']}) ke desa <b>" . htmlspecialchars($vInfo['name']) . "</b> (ID: $targetWid) selama {$durHours} jam!";
                } else {
                    $flashError = 'Gagal menyimpan status Ormas ke basis data!';
                }
            }
        }
    } elseif ($action === 'dismiss') {
        $vref = (int)($_POST['vref'] ?? 0);
        if ($vref > 0) {
            Ormas::dismissOrmas($vref);
            $flashSuccess = "Ormas pada desa ID #$vref berhasil dibubarkan!";
        }
    } elseif ($action === 'trigger_spawn') {
        $spawned = Ormas::processSpawns(true);
        if ($spawned > 0) {
            $flashSuccess = "Siklus spawn berhasil dijalankan! <b>$spawned desa</b> baru telah dikunjungi Ormas secara acak.";
        } else {
            $flashSuccess = "Siklus spawn dijalankan, namun tidak ada desa baru yang memenuhi kriteria saat ini.";
        }
    } elseif ($action === 'dismiss_all') {
        $cleared = Ormas::dismissAll();
        $flashSuccess = "Seluruh Ormas di server berhasil dibubarkan! <b>$cleared desa</b> telah kembali aman dan mendapatkan perlindungan kebal ormas 3 hari.";
    } elseif ($action === 'clear_cooldown') {
        $vref = (int)($_POST['vref'] ?? 0);
        if ($vref > 0) {
            Ormas::clearVillageCooldown($vref);
            $flashSuccess = "Perlindungan cooldown pada desa ID #$vref berhasil direset!";
        }
    }
}

// ─── QUERY DATA ─────────────────────────────────────────────────────────────
$activeList = Ormas::getAllActiveOrmas();
$totalAfflicted = count($activeList);

$immuneList = Ormas::getAllImmuneVillages();
$totalImmune = count($immuneList);

// Statistik per tipe
$typeCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($activeList as $row) {
    $t = (int)$row['ormas_type'];
    if (isset($typeCounts[$t])) {
        $typeCounts[$t]++;
    }
}

$typesMeta = [
    1 => Ormas::getOrmasInfo(1),
    2 => Ormas::getOrmasInfo(2),
    3 => Ormas::getOrmasInfo(3),
    4 => Ormas::getOrmasInfo(4),
];
?>

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; max-width: 100%; margin: 0 auto; padding: 10px;">

    <!-- FLASH MESSAGES -->
    <?php if ($flashSuccess): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; background: #ecfdf5; border-left: 5px solid #10b981; border-radius: 6px; color: #065f46; font-size: 13px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            ✅ <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; background: #fef2f2; border-left: 5px solid #ef4444; border-radius: 6px; color: #991b1b; font-size: 13px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            ❌ <?php echo $flashError; ?>
        </div>
    <?php endif; ?>

    <!-- HEADER CARD -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #fff; padding: 22px 24px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div>
                <h2 style="margin: 0; font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    <span>🦹</span> Ormas & Preman Desa Manager
                </h2>
                <p style="margin: 6px 0 0; font-size: 13px; opacity: 0.85;">
                    Pusat kendali gangguan desa: Kerahkan ormas pemalak, pantau desa terjangkit, atau bubarkan kerumunan di seluruh server.
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form method="post" action="admin.php?p=ormasManager" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="trigger_spawn" />
                    <button type="submit" style="background: #f59e0b; color: #1e293b; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        🎲 Jalankan Spawn Acak
                    </button>
                </form>
                <form method="post" action="admin.php?p=ormasManager" onsubmit="return confirm('Apakah Anda yakin ingin membubarkan SEMUA Ormas di seluruh server?');" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="dismiss_all" />
                    <button type="submit" style="background: #ef4444; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        🧹 Bubarkan Semua Ormas
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- STATS OVERVIEW -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700;">Desa Terjangkit</div>
            <div style="font-size: 26px; font-weight: 800; color: #dc2626; margin-top: 4px;"><?php echo $totalAfflicted; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Sedang diduduki preman</div>
        </div>
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #d97706; font-weight: 700;">🍊 Pemuda Pancasila</div>
            <div style="font-size: 26px; font-weight: 800; color: #d97706; margin-top: 4px;"><?php echo $typeCounts[1]; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Pungli kayu & proposal</div>
        </div>
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #16a34a; font-weight: 700;">🍲 Banser Rawon</div>
            <div style="font-size: 26px; font-weight: 800; color: #16a34a; margin-top: 4px;"><?php echo $typeCounts[2]; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Upkeep gandum +40/jam</div>
        </div>
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #7c3aed; font-weight: 700;">💪 Komplotan Herkules</div>
            <div style="font-size: 26px; font-weight: 800; color: #7c3aed; margin-top: 4px;"><?php echo $typeCounts[3]; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Pasar melambat -35%</div>
        </div>
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #ea580c; font-weight: 700;">☕ Ormas Setempat</div>
            <div style="font-size: 26px; font-weight: 800; color: #ea580c; margin-top: 4px;"><?php echo $typeCounts[4]; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Uang rokok & penalti rata</div>
        </div>
        <div style="background: #fff; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #0284c7; font-weight: 700;">🛡️ Kebal Cooldown (3 Hari)</div>
            <div style="font-size: 26px; font-weight: 800; color: #0284c7; margin-top: 4px;"><?php echo $totalImmune; ?></div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Desa dalam proteksi damai</div>
        </div>
    </div>

    <!-- MAIN GRID: DISPATCH FORM & ACTIVE AFFLICTIONS -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">

        <!-- DISPATCH CARD -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <span>🎯</span> Kirim Preman / Ormas ke Desa Sasaran
            </h3>

            <form method="post" action="admin.php?p=ormasManager" style="margin-top: 15px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="dispatch_manual" />

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
                    <!-- Target Selector -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Cara Penentuan Target Desa:</label>
                        <select name="target_type" id="targetTypeSelect" onchange="switchTargetInputs(this.value);" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="wid">Berdasarkan Village ID (Wref)</option>
                            <option value="username">Berdasarkan Nama Pemain (Username)</option>
                            <option value="coords">Berdasarkan Koordinat (X|Y)</option>
                        </select>
                    </div>

                    <!-- Target Value Inputs -->
                    <div id="targetWidBox">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">ID Desa (Wref):</label>
                        <input type="number" name="village_id" placeholder="Contoh: 123" style="width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" />
                    </div>

                    <div id="targetUserBox" style="display: none;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Nama Pemain (Username):</label>
                        <input type="text" name="username" placeholder="Masukkan username pemain" style="width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" />
                    </div>

                    <div id="targetCoordsBox" style="display: none;">
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Koordinat (X | Y):</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="number" name="coord_x" placeholder="X" style="width: 50%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" />
                            <input type="number" name="coord_y" placeholder="Y" style="width: 50%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" />
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <!-- Ormas Type Selector -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Tipe Ormas / Preman:</label>
                        <select name="ormas_type" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 600;">
                            <option value="1">🍊 Pemuda Pancasila (Pungli Kayu, Liat, Gandum & Pembangunan)</option>
                            <option value="2">🍲 Banser Pencari Rawon (Extra Gandum +40/jam, CP -30%, Biaya Pesta +50%)</option>
                            <option value="3">💪 Komplotan Herkules (Pasar Pedagang -35%, Besi -25%, Jarahan -15%)</option>
                            <option value="4">☕ Ormas Setempat (Semua Res -15%, Moral Tempur -10%)</option>
                        </select>
                    </div>

                    <!-- Duration Selector -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Durasi Kunjungan / Gangguan:</label>
                        <select name="duration" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            <option value="3600">1 Jam (3.600 detik)</option>
                            <option value="10800">3 Jam (10.800 detik)</option>
                            <option value="21600">6 Jam (21.600 detik)</option>
                            <option value="43200">12 Jam (43.200 detik)</option>
                            <option value="86400" selected>24 Jam / 1 Hari (86.400 detik)</option>
                            <option value="172800">48 Jam / 2 Hari (172.800 detik)</option>
                            <option value="259200">72 Jam / 3 Hari (259.200 detik)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" style="background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(37,99,235,0.25);">
                    🚀 Kirim Ormas ke Desa Sasaran
                </button>
            </form>
        </div>

        <!-- ACTIVE AFFLICTIONS TABLE -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span>📋</span> Daftar Desa yang Sedang Diduduki Ormas (<?php echo $totalAfflicted; ?>)
                </h3>
            </div>

            <?php if (empty($activeList)): ?>
                <div style="text-align: center; padding: 36px 20px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                    <div style="font-size: 32px; margin-bottom: 8px;">🕊️</div>
                    <div style="font-weight: 700; color: #334155; font-size: 14px;">Tidak Ada Gangguan Ormas Saat Ini</div>
                    <div style="color: #64748b; font-size: 12px; margin-top: 4px;">Seluruh desa pemain di server sedang dalam keadaan aman dan kondusif.</div>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 10px 12px;">ID Desa</th>
                                <th style="padding: 10px 12px;">Nama Desa & Posisi</th>
                                <th style="padding: 10px 12px;">Pemilik</th>
                                <th style="padding: 10px 12px;">Tipe Ormas</th>
                                <th style="padding: 10px 12px;">Sisa Waktu</th>
                                <th style="padding: 10px 12px; text-align: center;">Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeList as $row):
                                $t = (int)$row['ormas_type'];
                                $meta = $typesMeta[$t] ?? null;
                                $remain = max(0, (int)$row['end_time'] - time());
                                $remStr = sprintf('%02d:%02d:%02d', floor($remain/3600), floor(($remain%3600)/60), $remain%60);
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                    <td style="padding: 10px 12px; font-weight: 600;">
                                        #<?php echo (int)$row['vref']; ?>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <b><?php echo htmlspecialchars($row['vname']); ?></b>
                                        <span style="color: #64748b; font-size: 11px;">(<?php echo (int)($row['x'] ?? 0); ?>|<?php echo (int)($row['y'] ?? 0); ?>)</span>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <a href="admin.php?p=player&uid=<?php echo (int)$row['owner']; ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($row['username']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <?php if ($meta): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 700; color: <?php echo $meta['border_color']; ?>;">
                                                <?php echo $meta['icon']; ?> <?php echo htmlspecialchars($meta['name']); ?>
                                            </span>
                                        <?php else: ?>
                                            Tipe #<?php echo $t; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 12px; font-family: monospace; font-weight: 700; color: #dc2626;">
                                        ⏳ <?php echo $remStr; ?>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: center;">
                                        <form method="post" action="admin.php?p=ormasManager" style="margin: 0; display: inline;" onsubmit="return confirm('Bubarkan Ormas dari desa ini sekarang?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="dismiss" />
                                            <input type="hidden" name="vref" value="<?php echo (int)$row['vref']; ?>" />
                                            <button type="submit" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                ❌ Usir / Bubarkan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- IMMUNE VILLAGES TABLE (COOLDOWN 3 HARI) -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span>🛡️</span> Daftar Desa dalam Masa Perlindungan Kebal Ormas (Cooldown 3 Hari) (<?php echo $totalImmune; ?>)
                </h3>
            </div>

            <?php if (empty($immuneList)): ?>
                <div style="text-align: center; padding: 24px 20px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                    <div style="color: #64748b; font-size: 12px;">Tidak ada desa yang sedang dalam masa cooldown kekebalan ormas saat ini.</div>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 10px 12px;">ID Desa</th>
                                <th style="padding: 10px 12px;">Nama Desa & Posisi</th>
                                <th style="padding: 10px 12px;">Pemilik</th>
                                <th style="padding: 10px 12px;">Sisa Masa Kebal (3 Hari)</th>
                                <th style="padding: 10px 12px; text-align: center;">Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($immuneList as $row):
                                $remImmune = max(0, (int)$row['cooldown_until'] - time());
                                $days = floor($remImmune / 86400);
                                $hours = floor(($remImmune % 86400) / 3600);
                                $mins = floor(($remImmune % 3600) / 60);
                                $remImmuneStr = "{$days} hari {$hours} jam {$mins} mnt";
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                    <td style="padding: 10px 12px; font-weight: 600;">
                                        #<?php echo (int)$row['vref']; ?>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <b><?php echo htmlspecialchars($row['vname']); ?></b>
                                        <span style="color: #64748b; font-size: 11px;">(<?php echo (int)($row['x'] ?? 0); ?>|<?php echo (int)($row['y'] ?? 0); ?>)</span>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <a href="admin.php?p=player&uid=<?php echo (int)$row['owner']; ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($row['username']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 10px 12px; font-family: monospace; font-weight: 700; color: #0284c7;">
                                        🛡️ <?php echo $remImmuneStr; ?>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: center;">
                                        <form method="post" action="admin.php?p=ormasManager" style="margin: 0; display: inline;" onsubmit="return confirm('Reset perlindungan cooldown desa ini agar dapat dikunjungi ormas kembali?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="clear_cooldown" />
                                            <input type="hidden" name="vref" value="<?php echo (int)$row['vref']; ?>" />
                                            <button type="submit" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                                🔄 Reset Cooldown
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ENCYCLOPEDIA / LORE & DEBUFF CARD -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <span>📖</span> Ensiklopedia 4 Jenis Preman & Efek Kejelekannya
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 15px;">
                <?php foreach ($typesMeta as $info): ?>
                    <div style="border: 1px solid <?php echo $info['border_color']; ?>; border-radius: 8px; padding: 14px; background: <?php echo $info['bg_gradient']; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <div style="font-weight: 800; font-size: 14px; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 18px;"><?php echo $info['icon']; ?></span>
                            <span><?php echo htmlspecialchars($info['name']); ?></span>
                        </div>
                        <div style="font-size: 11px; color: #64748b; font-style: italic; margin-top: 2px;">
                            "<?php echo htmlspecialchars($info['alias']); ?>"
                        </div>
                        <p style="font-size: 11.5px; color: #334155; line-height: 1.4; margin: 8px 0;">
                            <?php echo htmlspecialchars($info['desc']); ?>
                        </p>
                        <div style="font-size: 11px; font-weight: 700; color: #991b1b; margin-bottom: 6px;">
                            Efek Kejelekan (Debuff):
                        </div>
                        <ul style="margin: 0 0 10px 0; padding-left: 18px; font-size: 11px; color: #7f1d1d; line-height: 1.5;">
                            <?php foreach ($info['effects'] as $eff): ?>
                                <li><?php echo htmlspecialchars($eff); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div style="border-top: 1px dashed rgba(0,0,0,0.15); padding-top: 8px; font-size: 11px; color: #1e293b;">
                            <div><b>💰 Uang Damai:</b> <?php echo htmlspecialchars($info['bribe']['label']); ?></div>
                            <div style="margin-top: 2px;"><b>⚔️ Minimal Prajurit Razia:</b> <?php echo (int)$info['min_troops_fight']; ?> prajurit</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>

<script type="text/javascript">
function switchTargetInputs(val) {
    document.getElementById('targetWidBox').style.display = (val === 'wid') ? 'block' : 'none';
    document.getElementById('targetUserBox').style.display = (val === 'username') ? 'block' : 'none';
    document.getElementById('targetCoordsBox').style.display = (val === 'coords') ? 'block' : 'none';
}
</script>
