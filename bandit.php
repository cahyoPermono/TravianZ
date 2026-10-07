<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : bandit.php                                                ##
##  Type           : PvE Radar & World Boss Hub for TravianZ                   ##
##  Purpose        : Interactive overview of Bandit Camps & World Bosses with   ##
##                   distance sorting, guards preview, bounties & 1-click raid ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
AccessLogger::logRequest();

require_once("GameEngine/BanditCamp.php");

$start_timer = $generator->pageLoadTimeStart();

// Ensure bandit camps are generated and maintained
if (defined('BANDIT_CAMPS_ENABLED') && BANDIT_CAMPS_ENABLED) {
    BanditCamp::run();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Admin trigger to spawn or force-clear
if ($session->access == ADMIN && isset($_GET['action'])) {
    if ($_GET['action'] == 'spawn' && isset($_GET['tier'])) {
        $tier = max(1, min(4, (int)$_GET['tier']));
        BanditCamp::spawnCamp($tier);
        header("Location: bandit.php?tab=" . $tab);
        exit;
    }
    if ($_GET['action'] == 'run') {
        BanditCamp::run(true);
        header("Location: bandit.php?tab=" . $tab);
        exit;
    }
}

// Current village coordinates for distance calculation
$fromX = (int)($village->coor['x'] ?? 0);
$fromY = (int)($village->coor['y'] ?? 0);

// Get active camps sorted by distance
$allCamps = BanditCamp::getActiveCamps($fromX, $fromY);

// Filter by tab
$filteredCamps = [];
$bossCamp = null;
$counts = ['all' => count($allCamps), 'boss' => 0, 't3' => 0, 't2' => 0, 't1' => 0];

foreach ($allCamps as $c) {
    $t = (int)$c['tier'];
    if ($t === 4) {
        $counts['boss']++;
        $bossCamp = $c;
    } elseif ($t === 3) {
        $counts['t3']++;
    } elseif ($t === 2) {
        $counts['t2']++;
    } elseif ($t === 1) {
        $counts['t1']++;
    }

    if ($tab === 'all') {
        $filteredCamps[] = $c;
    } elseif ($tab === 'boss' && $t === 4) {
        $filteredCamps[] = $c;
    } elseif ($tab === 't3' && $t === 3) {
        $filteredCamps[] = $c;
    } elseif ($tab === 't2' && $t === 2) {
        $filteredCamps[] = $c;
    } elseif ($tab === 't1' && $t === 1) {
        $filteredCamps[] = $c;
    }
}

// Query history if tab is history
$history = [];
if ($tab === 'history') {
    $histSql = "
        SELECT b.*, u.username as conqueror_name
        FROM " . TB_PREFIX . "bandit_camps b
        LEFT JOIN " . TB_PREFIX . "users u ON b.cleared_by = u.id
        WHERE b.status = 0
        ORDER BY b.cleared_time DESC
        LIMIT 20
    ";
    $history = $database->query_return($histSql);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo SERVER_NAME; ?> &raquo; Radar Sarang Bandit &amp; World Boss PvE</title>
	<link rel="shortcut icon" href="favicon.ico"/>
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="pragma" content="no-cache" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="imagetoolbar" content="no" />
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<script src="mt-full.js?0faab" type="text/javascript"></script>
	<script src="unx.js?f4b7h" type="text/javascript"></script>
	<script src="new.js?0faab" type="text/javascript"></script>
	<link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?f4b7d" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?f4b7i" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>travian.css?e21d2" rel="stylesheet" type="text/css" />
	<script type="text/javascript">
		window.addEvent('domready', start);
	</script>
	<style type="text/css">
		.pve-card {
			margin: 12px 0 16px 0;
			background: #fbfbf9;
			border: 1px solid #d2cbba;
			border-radius: 6px;
			padding: 12px;
			box-shadow: 0 1px 4px rgba(0,0,0,0.06);
		}
		.pve-card.tier-4 {
			border: 2px solid #e17055;
			background: #fff8f5;
			box-shadow: 0 2px 8px rgba(225, 112, 85, 0.2);
		}
		.pve-card.tier-3 {
			border: 1px solid #8e44ad;
			background: #fdfafc;
		}
		.pve-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 8px;
			border-bottom: 1px solid #e8e3d5;
			padding-bottom: 6px;
		}
		.pve-title {
			font-size: 13px;
			font-weight: bold;
			color: #2c3e50;
		}
		.pve-badge {
			display: inline-block;
			padding: 2px 8px;
			font-size: 10px;
			font-weight: bold;
			border-radius: 3px;
			text-transform: uppercase;
		}
		.badge-tier-1 { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.badge-tier-2 { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
		.badge-tier-3 { background: #e8daef; color: #5b2c6f; border: 1px solid #d2b4de; }
		.badge-tier-4 { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; animation: pulse 2s infinite; }
		.pve-stats-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px;
			margin: 8px 0;
		}
		.pve-stat-box {
			background: #fff;
			padding: 6px 8px;
			border: 1px solid #ebe7df;
			border-radius: 4px;
			font-size: 11px;
		}
		.pve-stat-label {
			color: #7f8c8d;
			font-size: 10px;
			margin-bottom: 2px;
		}
		.pve-stat-val {
			font-weight: bold;
			color: #2c3e50;
		}
		.pve-guards-preview {
			margin: 8px 0;
			background: #f4f1ea;
			padding: 6px 8px;
			border-radius: 4px;
			font-size: 11px;
		}
		.pve-guard-chip {
			display: inline-block;
			margin-right: 10px;
			white-space: nowrap;
		}
		.pve-actions {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 10px;
			padding-top: 8px;
			border-top: 1px dashed #e8e3d5;
		}
		.btn-raid {
			display: inline-block;
			background: #c0392b;
			color: #ffffff !important;
			padding: 4px 14px;
			border-radius: 4px;
			font-weight: bold;
			font-size: 11px;
			text-decoration: none !important;
			box-shadow: 0 1px 3px rgba(0,0,0,0.15);
		}
		.btn-raid:hover {
			background: #a93226;
		}
		.pve-notice {
			background: #eef2f7;
			border-left: 4px solid #3498db;
			padding: 8px 12px;
			margin: 12px 0;
			font-size: 11px;
			color: #34495e;
			line-height: 1.5;
		}
	</style>
</head>

<body class="v35 ie ie8">
<div class="wrapper">
<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" />
<div id="dynamic_header"></div>
<?php include("Templates/header.tpl"); ?>
<div id="mid">
<?php include("Templates/menu.tpl"); ?>
	<div id="content" class="bandit_camps">

		<h1>⚔️ Radar Sarang Bandit &amp; World Boss PvE</h1>

		<div class="pve-notice">
			<b>Mode Petualangan PvE:</b> Sarang penyamun dan monster buas muncul secara acak di sekitar pemukiman. 
			Kalahkan seluruh penjaga sarang untuk menjarah jutaan sumber daya, memenangkan <b>Hero EXP masif</b>, <b>Culture Points (CP)</b>, dan <b>Silver</b> gratis!
		</div>

		<!-- Navigasi Tab -->
		<div id="textmenu" style="margin-bottom: 12px;">
			<a href="bandit.php?tab=all" <?php if($tab == 'all') echo 'class="selected"'; ?>>Semua Target (<?php echo $counts['all']; ?>)</a>
			| <a href="bandit.php?tab=boss" <?php if($tab == 'boss') echo 'class="selected"'; ?>>👹 World Boss (<?php echo $counts['boss']; ?>)</a>
			| <a href="bandit.php?tab=t3" <?php if($tab == 't3') echo 'class="selected"'; ?>>🏰 Benteng (<?php echo $counts['t3']; ?>)</a>
			| <a href="bandit.php?tab=t2" <?php if($tab == 't2') echo 'class="selected"'; ?>>🏕️ Markas (<?php echo $counts['t2']; ?>)</a>
			| <a href="bandit.php?tab=t1" <?php if($tab == 't1') echo 'class="selected"'; ?>>⛺ Kroco (<?php echo $counts['t1']; ?>)</a>
			| <a href="bandit.php?tab=history" <?php if($tab == 'history') echo 'class="selected"'; ?>>🏆 Riwayat Kemenangan</a>
		</div>

		<!-- Info Lokasi Desa Pemain -->
		<div style="font-size: 11px; color: #555; margin-bottom: 10px; display: flex; justify-content: space-between;">
			<span>Titik Ukur: <b><?php echo htmlspecialchars($village->vname); ?></b> (<?php echo $fromX; ?>|<?php echo $fromY; ?>)</span>
			<span>Total Target Aktif: <b><?php echo count($allCamps); ?></b></span>
		</div>

		<?php if ($tab === 'history') { ?>
			<!-- TAB RIWAYAT KEMENANGAN -->
			<table id="overview" class="tableNone" style="width: 100%; margin-top: 10px;">
				<thead>
					<tr>
						<th>Waktu</th>
						<th>Nama Sarang</th>
						<th>Tingkat</th>
						<th>Penakluk</th>
						<th>Hadiah EXP</th>
						<th>CP</th>
						<th>Silver</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($history)) { ?>
					<tr><td colspan="7" style="text-align: center; padding: 16px; color: #888;">Belum ada sarang bandit yang berhasil ditumpas. Jadilah yang pertama!</td></tr>
				<?php } else { ?>
					<?php foreach ($history as $h) { ?>
						<tr>
							<td><?php echo date('d.m H:i', (int)$h['cleared_time']); ?></td>
							<td><b><?php echo htmlspecialchars($h['name']); ?></b></td>
							<td><?php echo BanditCamp::getTierLabel((int)$h['tier']); ?></td>
							<td>
								<?php if (!empty($h['conqueror_name'])) { ?>
									<a href="spieler.php?uid=<?php echo (int)$h['cleared_by']; ?>"><b><?php echo htmlspecialchars($h['conqueror_name']); ?></b></a>
								<?php } else { ?>
									<i>Prajurit Misterius</i>
								<?php } ?>
							</td>
							<td style="color: #2980b9; font-weight: bold;">+<?php echo number_format((int)$h['reward_exp']); ?></td>
							<td style="color: #27ae60; font-weight: bold;">+<?php echo number_format((int)$h['reward_cp']); ?></td>
							<td style="color: #f39c12; font-weight: bold;">+<?php echo number_format((int)$h['reward_silver']); ?></td>
						</tr>
					<?php } ?>
				<?php } ?>
				</tbody>
			</table>

		<?php } else { ?>
			<!-- TAB DAFTAR SARANG AKTIF -->
			<?php if (empty($filteredCamps)) { ?>
				<div style="text-align: center; padding: 30px; background: #fafafa; border: 1px dashed #ccc; border-radius: 6px;">
					<p style="font-size: 13px; color: #777;">Tidak ada sarang aktif untuk kategori ini saat ini.</p>
					<?php if ($session->access == ADMIN) { ?>
						<p><a href="bandit.php?action=run" style="font-weight: bold; color: #2980b9;">[Admin: Jalankan Spawn Otomatis Sekarang]</a></p>
					<?php } ?>
				</div>
			<?php } else { ?>
				<?php foreach ($filteredCamps as $camp) { 
					$tier = (int)$camp['tier'];
					$badgeClass = 'badge-tier-' . $tier;
					$dist = isset($camp['dist']) ? round($camp['dist'], 1) : 0;
					$mapCheck = $generator->getMapCheck($camp['wref']);

					// Fetch guard breakdown (bypass cache)
					$unitData = $database->getUnit($camp['wref'], false);
					$guardsList = [];
					if (!empty($unitData)) {
						for ($u = 1; $u <= 50; $u++) {
							if (!empty($unitData['u' . $u]) && $unitData['u' . $u] > 0) {
								$guardsList[] = [
									'id' => $u,
									'name' => defined('U' . $u) ? constant('U' . $u) : "U$u",
									'count' => (int)$unitData['u' . $u]
								];
							}
						}
					}
				?>
				<div class="pve-card tier-<?php echo $tier; ?>">
					<div class="pve-header">
						<div>
							<span class="pve-title"><?php echo htmlspecialchars($camp['name']); ?></span>
							<a href="karte.php?d=<?php echo $camp['wref']; ?>&c=<?php echo $mapCheck; ?>" style="margin-left: 6px; font-size: 11px; color: #2980b9;">(<?php echo $camp['x']; ?>|<?php echo $camp['y']; ?>)</a>
						</div>
						<div>
							<span class="pve-badge <?php echo $badgeClass; ?>"><?php echo BanditCamp::getTierLabel($tier); ?> <?php echo BanditCamp::getTierStars($tier); ?></span>
						</div>
					</div>

					<?php
					$campImg = file_exists(__DIR__ . "/img/bandit/camp_tier_{$tier}.png") 
						? "img/bandit/camp_tier_{$tier}.png" 
						: "img/bandit/camp_tier_{$tier}.jpg";
					$glowColor = ($tier === 4 ? 'rgba(192, 57, 43, 0.5)' : ($tier === 3 ? 'rgba(142, 68, 173, 0.5)' : ($tier === 2 ? 'rgba(41, 128, 185, 0.5)' : 'rgba(39, 174, 96, 0.5)')));
					?>
					<div style="display: flex; gap: 12px; margin-top: 8px;">
						<img src="<?php echo $campImg; ?>" alt="<?php echo htmlspecialchars($camp['name']); ?>" style="width: 140px; height: 110px; object-fit: contain; background: transparent; filter: drop-shadow(0 4px 8px <?php echo $glowColor; ?>); flex-shrink: 0;" />
						<div style="flex-grow: 1;">
							<div class="pve-stats-grid" style="margin-top: 0;">
								<div class="pve-stat-box">
									<div class="pve-stat-label">Jarak Tempuh</div>
									<div class="pve-stat-val">📍 <?php echo $dist; ?> bidang</div>
								</div>
								<div class="pve-stat-box">
									<div class="pve-stat-label">Total Penjaga</div>
									<div class="pve-stat-val">🛡️ <?php echo number_format((int)$camp['cur_hp']); ?> pasukan</div>
								</div>
								<div class="pve-stat-box">
									<div class="pve-stat-label">Gudang Rampasan (Tiap Res)</div>
									<div class="pve-stat-val">
										<img class="r1" src="img/x.gif" title="Kayu"> <?php echo number_format((int)$camp['bounty_wood']); ?> &nbsp;
										<img class="r2" src="img/x.gif" title="Tanah Liat"> <?php echo number_format((int)$camp['bounty_clay']); ?> &nbsp;
										<img class="r3" src="img/x.gif" title="Besi"> <?php echo number_format((int)$camp['bounty_iron']); ?> &nbsp;
										<img class="r4" src="img/x.gif" title="Gandum"> <?php echo number_format((int)$camp['bounty_crop']); ?>
									</div>
								</div>
								<div class="pve-stat-box">
									<div class="pve-stat-label">Bonus Kemenangan</div>
									<div class="pve-stat-val">
										<span style="color: #2980b9;">+<?php echo number_format((int)$camp['reward_exp']); ?> EXP</span> &nbsp;|&nbsp;
										<span style="color: #27ae60;">+<?php echo number_format((int)$camp['reward_cp']); ?> CP</span> &nbsp;|&nbsp;
										<span style="color: #f39c12;">+<?php echo number_format((int)$camp['reward_silver']); ?> Silver</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Troops guard chip preview -->
					<div class="pve-guards-preview">
						<b>Penjaga Sarang:</b> 
						<?php if (empty($guardsList)) { ?>
							<span style="color: #27ae60;">Seluruh penjaga telah tumbang!</span>
						<?php } else { ?>
							<?php foreach ($guardsList as $g) { ?>
								<span class="pve-guard-chip">
									<img class="unit u<?php echo $g['id']; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($g['name']); ?>"> 
									<?php echo number_format($g['count']); ?> <?php echo htmlspecialchars($g['name']); ?>
								</span>
							<?php } ?>
						<?php } ?>
					</div>

					<div class="pve-actions">
						<div>
							<a href="karte.php?d=<?php echo $camp['wref']; ?>&c=<?php echo $mapCheck; ?>">&raquo; Lihat di Peta</a>
						</div>
						<div>
							<?php if ($village->resarray['f39'] > 0) { ?>
								<a class="btn-raid" href="a2b.php?s=2&z=<?php echo $camp['wref']; ?>">⚔️ Serang / Jarah Sarang</a>
							<?php } else { ?>
								<span style="color: #999; font-size: 11px;">(Bangun Titik Temu untuk menyerang)</span>
							<?php } ?>
						</div>
					</div>
				</div>
				<?php } ?>
			<?php } ?>
		<?php } ?>

		<!-- Admin Control Panel (Khusus Admin) -->
		<?php if ($session->access == ADMIN) { ?>
			<div style="margin-top: 25px; padding: 12px; background: #fffde7; border: 1px dashed #fbc02d; border-radius: 4px; font-size: 11px;">
				<b>🛠️ Panel Kontrol Admin (PvE Bandit Camps):</b><br />
				<a href="bandit.php?action=run" style="font-weight: bold; color: #2980b9;">[🔁 Cek &amp; Respawn Otomatis]</a> &nbsp;|&nbsp;
				Spawn Langsung: 
				<a href="bandit.php?action=spawn&tier=1">[+ Tier 1 Kroco]</a> &nbsp;
				<a href="bandit.php?action=spawn&tier=2">[+ Tier 2 Begal]</a> &nbsp;
				<a href="bandit.php?action=spawn&tier=3">[+ Tier 3 Benteng]</a> &nbsp;
				<a href="bandit.php?action=spawn&tier=4" style="color: #d32f2f; font-weight: bold;">[+ Tier 4 World Boss]</a>
			</div>
		<?php } ?>

	</div>

	<div id="side_info">
		<?php
		include("Templates/multivillage.tpl");
		include("Templates/quest.tpl");
		include("Templates/news.tpl");
		if(!NEW_FUNCTIONS_DISPLAY_LINKS) {
			echo "<br><br><br><br>";
			include("Templates/links.tpl");
		}
		?>
	</div>
	<div class="clear"></div>
</div>
<div class="footer-stopper"></div>
<div class="clear"></div>

<?php
include("Templates/footer.tpl");
include("Templates/res.tpl");
?>

<div id="stime">
<div id="ltime">
<div id="ltimeWrap">
<?php echo CALCULATED_IN;?> <b><?php
echo round(($generator->pageLoadTimeEnd()-$start_timer)*1000);
?></b> ms
<br /><?php echo SERVER_TIME;?> <span id="tp1" class="b"><?php echo date('H:i:s'); ?></span>
</div>
</div>
</div>

<div id="ce"></div>
</body>
</html>
