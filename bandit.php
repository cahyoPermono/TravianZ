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
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<script src="mt-full.js?0faab" type="text/javascript"></script>
	<script src="unx.js?f4b7h" type="text/javascript"></script>
	<script src="new.js?0faab" type="text/javascript"></script>
	<link href="<?php echo GP_LOCATE; ?>lang/en/lang.css?f4b7d" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?v=20261008d" rel="stylesheet" type="text/css" />
	<link href="css/mobile_engine.css?v=20261009_m1" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>travian.css?v=20261008d" rel="stylesheet" type="text/css" />
	<script type="text/javascript">
		window.addEvent('domready', start);
	</script>
	<style type="text/css">
		/* PAGE-LEVEL LAYOUT FIXES: Prevent footer collision and enable smooth scrolling */
		html, body {
			height: auto !important;
			min-height: 100% !important;
			overflow-y: auto !important;
		}

		.wrapper {
			height: auto !important;
			min-height: 100% !important;
			position: relative !important;
			padding-bottom: 20px !important;
		}

		/* Footer must sit naturally below #mid and never cover content */
		div#footer {
			position: static !important;
			bottom: auto !important;
			clear: both !important;
			margin-top: 30px !important;
			padding-bottom: 25px !important;
			width: 100% !important;
		}

		.footer-stopper {
			display: none !important;
		}

		/* STRICT TRAVIAN GRID & SCROLLABLE CONTENT COLUMN */
		div#content.statistics,
		div#content.bandit_camps,
		div.bandit_camps {
			width: 502px !important;
			padding: 38px 25px 50px !important;
			float: left !important;
			box-sizing: content-box !important;
			min-height: 480px !important;
			max-height: calc(100vh - 195px) !important;
			overflow-y: auto !important;
			overflow-x: hidden !important;
			position: relative !important;
		}

		/* Sleek Parchment Custom Scrollbar */
		div#content.bandit_camps::-webkit-scrollbar,
		div#content.statistics::-webkit-scrollbar {
			width: 7px;
		}
		div#content.bandit_camps::-webkit-scrollbar-track,
		div#content.statistics::-webkit-scrollbar-track {
			background: #f1ebd8;
			border-radius: 4px;
		}
		div#content.bandit_camps::-webkit-scrollbar-thumb,
		div#content.statistics::-webkit-scrollbar-thumb {
			background: #b5a485;
			border-radius: 4px;
		}
		div#content.bandit_camps::-webkit-scrollbar-thumb:hover,
		div#content.statistics::-webkit-scrollbar-thumb:hover {
			background: #877555;
		}

		/* HEADER BANNER */
		.pve-hero-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			border-bottom: 2px solid #71d000;
			padding-bottom: 6px;
			margin-bottom: 8px;
		}
		.pve-hero-title {
			font-size: 19px;
			font-weight: bold;
			color: #3b2c15;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.pve-hero-subtitle {
			font-size: 10px;
			color: #7f6e52;
			margin-top: 1px;
		}

		/* COMPACT NOTICE */
		.pve-notice-strip {
			background: #fbf7ec;
			border: 1px solid #e2d7be;
			border-left: 4px solid #b38b3f;
			border-radius: 4px;
			padding: 5px 8px;
			margin-bottom: 8px;
			font-size: 10px;
			color: #554426;
			line-height: 1.35;
		}

		/* TABS */
		.pve-tab-strip {
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
			margin-bottom: 8px;
		}
		.pve-tab-btn {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 3px 7px;
			font-size: 11px;
			font-weight: bold;
			color: #4b3e2a;
			background: #f4efe2;
			border: 1px solid #d3c7ab;
			border-radius: 4px;
			text-decoration: none !important;
			transition: all 0.15s ease;
		}
		.pve-tab-btn:hover {
			background: #e8dfcd;
			color: #1a160d;
			border-color: #baa887;
		}
		.pve-tab-btn.active {
			background: #6aa800;
			color: #ffffff !important;
			border-color: #558700;
			box-shadow: 0 1px 3px rgba(0,0,0,0.18);
		}
		.pve-tab-btn.boss-tab {
			border-color: #e09285;
		}
		.pve-tab-btn.boss-tab.active {
			background: #c0392b;
			border-color: #962d22;
		}
		.tab-pill {
			display: inline-block;
			background: rgba(0,0,0,0.12);
			padding: 0 5px;
			border-radius: 8px;
			font-size: 10px;
		}
		.pve-tab-btn.active .tab-pill {
			background: rgba(255,255,255,0.25);
		}

		/* INFO BAR */
		.pve-info-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			background: #f8f6ee;
			border: 1px solid #e5dcce;
			border-radius: 4px;
			padding: 4px 8px;
			margin-bottom: 10px;
			font-size: 11px;
			color: #63553e;
		}

		/* PVE CARD DESIGN */
		.pve-card {
			box-sizing: border-box;
			width: 100%;
			margin-bottom: 12px;
			background: #ffffff;
			border: 1px solid #d6cca8;
			border-radius: 6px;
			padding: 8px 10px;
			box-shadow: 0 1px 4px rgba(0,0,0,0.05);
			position: relative;
		}
		.pve-card.tier-4 {
			border: 2px solid #c0392b;
			background: #fffafa;
			box-shadow: 0 2px 10px rgba(192, 57, 43, 0.22);
		}
		.pve-card.tier-3 {
			border: 1.5px solid #8e44ad;
			background: #fdfbfe;
		}
		.pve-card.tier-2 {
			border: 1px solid #2980b9;
			background: #f9fbfe;
		}
		.pve-card.tier-1 {
			border: 1px solid #27ae60;
			background: #f9fdfa;
		}

		/* CARD TOP BAR */
		.pve-card-top {
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1px solid #ede7d8;
			padding-bottom: 4px;
			margin-bottom: 6px;
		}
		.pve-title-group {
			display: flex;
			align-items: center;
			gap: 5px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.pve-title {
			font-size: 13px;
			font-weight: bold;
			color: #2c3e50;
		}
		.pve-coords {
			font-size: 11px;
			color: #2980b9;
			text-decoration: none;
			font-weight: bold;
		}
		.pve-coords:hover {
			text-decoration: underline;
		}
		.pve-badge {
			display: inline-block;
			padding: 2px 6px;
			font-size: 9px;
			font-weight: bold;
			border-radius: 3px;
			text-transform: uppercase;
			white-space: nowrap;
		}
		.badge-tier-1 { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.badge-tier-2 { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
		.badge-tier-3 { background: #e8daef; color: #5b2c6f; border: 1px solid #d2b4de; }
		.badge-tier-4 { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; animation: pulse 2s infinite; }

		@keyframes pulse {
			0% { opacity: 0.9; }
			50% { opacity: 1; transform: scale(1.02); }
			100% { opacity: 0.9; }
		}

		/* CARD BODY: IMAGE + SPECS + BUTTONS */
		.pve-card-body {
			display: flex;
			gap: 8px;
			align-items: center;
			margin-bottom: 6px;
		}
		.pve-thumb-box {
			width: 80px;
			height: 70px;
			flex-shrink: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #f6f3eb;
			border: 1px solid #e1d8c5;
			border-radius: 4px;
			overflow: hidden;
		}
		.pve-thumb-box img {
			max-width: 100%;
			max-height: 100%;
			object-fit: contain;
			filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
		}
		.pve-specs-box {
			flex-grow: 1;
			display: flex;
			flex-direction: column;
			gap: 2px;
			font-size: 11px;
		}
		.spec-line {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 1px 0;
		}
		.spec-label {
			color: #777;
			font-size: 10px;
		}
		.spec-val {
			font-weight: bold;
			color: #333;
		}

		/* ACTIONS ROW */
		.pve-actions-strip {
			display: flex;
			justify-content: flex-end;
			align-items: center;
			gap: 6px;
			margin-top: 4px;
		}
		.btn-pve-raid {
			display: inline-block;
			background: #c0392b;
			color: #ffffff !important;
			padding: 3px 10px;
			border-radius: 3px;
			font-weight: bold;
			font-size: 11px;
			text-decoration: none !important;
			box-shadow: 0 1px 3px rgba(0,0,0,0.2);
			border: 1px solid #a93226;
		}
		.btn-pve-raid:hover {
			background: #962d22;
		}
		.btn-pve-rally {
			display: inline-block;
			background: #e67e22;
			color: #ffffff !important;
			padding: 3px 8px;
			border-radius: 3px;
			font-weight: bold;
			font-size: 10px;
			text-decoration: none !important;
			border: 1px solid #d35400;
		}
		.btn-pve-rally:hover {
			background: #d35400;
		}
		.btn-pve-map {
			display: inline-block;
			background: #f4efe2;
			color: #554426 !important;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 10px;
			font-weight: bold;
			text-decoration: none !important;
			border: 1px solid #d3c7ab;
		}
		.btn-pve-map:hover {
			background: #e8dfcd;
		}

		/* COMPACT LOOT STRIP */
		.pve-loot-strip {
			background: #f9f8f3;
			border: 1px solid #e9e3d4;
			border-radius: 4px;
			padding: 4px 6px;
			margin-bottom: 5px;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.loot-label {
			font-size: 9px;
			font-weight: bold;
			color: #7f6e52;
			text-transform: uppercase;
		}
		.loot-items {
			display: flex;
			gap: 8px;
		}
		.loot-res-cell {
			display: flex;
			align-items: center;
			gap: 3px;
			font-size: 10px;
			font-weight: bold;
			color: #2c3e50;
		}

		/* REWARDS PILL STRIP */
		.pve-rewards-strip {
			display: flex;
			gap: 5px;
			margin-bottom: 5px;
		}
		.reward-pill {
			flex: 1;
			text-align: center;
			font-size: 10px;
			font-weight: bold;
			padding: 2px 4px;
			border-radius: 3px;
			white-space: nowrap;
		}
		.reward-pill.exp {
			background: #ebf5fb;
			color: #2471a3;
			border: 1px solid #d4e6f1;
		}
		.reward-pill.cp {
			background: #eafaf1;
			color: #1e8449;
			border: 1px solid #d5f5e3;
		}
		.reward-pill.silver {
			background: #fef9e7;
			color: #b7950b;
			border: 1px solid #fcf3cf;
		}

		/* GUARDS CHIP LIST */
		.pve-guards-strip {
			background: #f5f2ea;
			border: 1px solid #e7e2d6;
			border-radius: 4px;
			padding: 4px 6px;
			font-size: 10px;
		}
		.guards-header {
			font-weight: bold;
			color: #666;
			margin-bottom: 2px;
		}
		.guards-chips-row {
			display: flex;
			flex-wrap: wrap;
			gap: 4px;
		}
		.guard-chip {
			display: inline-flex;
			align-items: center;
			gap: 3px;
			background: #ffffff;
			border: 1px solid #dfd8c8;
			border-radius: 3px;
			padding: 1px 5px;
			font-size: 10px;
			white-space: nowrap;
		}

		/* ADMIN TOOLBAR */
		.pve-admin-bar {
			margin-top: 15px;
			margin-bottom: 10px;
			padding: 6px 10px;
			background: #fffde7;
			border: 1px dashed #fbc02d;
			border-radius: 4px;
			font-size: 10px;
			line-height: 18px;
		}
		.pve-admin-bar a {
			font-weight: bold;
			color: #2980b9;
			text-decoration: none;
			margin-right: 5px;
		}
		.pve-admin-bar a:hover {
			text-decoration: underline;
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
	<div id="content" class="statistics bandit_camps">

		<!-- HERO HEADER -->
		<div class="pve-hero-header">
			<div>
				<div class="pve-hero-title">⚔️ Radar Sarang Bandit &amp; Boss</div>
				<div class="pve-hero-subtitle">Mode Petualangan PvE &bull; Jarah sumber daya, menangkan EXP Hero &amp; Silver</div>
			</div>
		</div>

		<!-- COMPACT NOTICE STRIP -->
		<div class="pve-notice-strip">
			💡 <b>Petualangan PvE:</b> Kalahkan seluruh penjaga sarang untuk menjarah jutaan sumber daya, memenangkan <b>Hero EXP masif</b>, <b>Culture Points (CP)</b>, dan <b>Silver</b> gratis!
		</div>

		<!-- TABS STRIP -->
		<div class="pve-tab-strip">
			<a href="bandit.php?tab=all" class="pve-tab-btn <?php if($tab == 'all') echo 'active'; ?>">
				Semua <span class="tab-pill"><?php echo $counts['all']; ?></span>
			</a>
			<a href="bandit.php?tab=boss" class="pve-tab-btn boss-tab <?php if($tab == 'boss') echo 'active'; ?>">
				👹 Boss <span class="tab-pill"><?php echo $counts['boss']; ?></span>
			</a>
			<a href="bandit.php?tab=t3" class="pve-tab-btn <?php if($tab == 't3') echo 'active'; ?>">
				🏰 Benteng <span class="tab-pill"><?php echo $counts['t3']; ?></span>
			</a>
			<a href="bandit.php?tab=t2" class="pve-tab-btn <?php if($tab == 't2') echo 'active'; ?>">
				🏕️ Markas <span class="tab-pill"><?php echo $counts['t2']; ?></span>
			</a>
			<a href="bandit.php?tab=t1" class="pve-tab-btn <?php if($tab == 't1') echo 'active'; ?>">
				⛺ Kroco <span class="tab-pill"><?php echo $counts['t1']; ?></span>
			</a>
			<a href="bandit.php?tab=history" class="pve-tab-btn <?php if($tab == 'history') echo 'active'; ?>">
				🏆 Riwayat
			</a>
		</div>

		<!-- VILLAGE REFERENCE & TOTAL TARGETS -->
		<div class="pve-info-bar">
			<div>📍 Titik Ukur: <b><?php echo htmlspecialchars($village->vname); ?></b> <span style="color:#777;">(<?php echo $fromX; ?>|<?php echo $fromY; ?>)</span></div>
			<div>🎯 <b><?php echo count($allCamps); ?></b> Sarang Terdeteksi</div>
		</div>

		<?php if ($tab === 'history') { ?>
			<!-- TAB RIWAYAT KEMENANGAN -->
			<table class="tbg" style="width: 100%; margin-top: 6px; font-size: 11px; border-collapse: collapse;">
				<thead>
					<tr class="cbg1" style="background: #e2dac9; text-align: left;">
						<th style="padding: 5px 4px; width: 60px;">Waktu</th>
						<th style="padding: 5px 4px;">Nama Sarang</th>
						<th style="padding: 5px 4px; width: 60px;">Tingkat</th>
						<th style="padding: 5px 4px; width: 85px;">Penakluk</th>
						<th style="padding: 5px 4px; width: 140px;">Hadiah Didapat</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($history)) { ?>
					<tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">Belum ada sarang bandit yang berhasil ditumpas. Jadilah yang pertama!</td></tr>
				<?php } else { ?>
					<?php foreach ($history as $h) { ?>
						<tr style="border-bottom: 1px solid #eee;">
							<td style="padding: 4px; color: #666; font-size: 10px;"><?php echo date('d.m H:i', (int)$h['cleared_time']); ?></td>
							<td style="padding: 4px;"><b><?php echo htmlspecialchars($h['name']); ?></b></td>
							<td style="padding: 4px; font-size: 10px;"><?php echo BanditCamp::getTierLabel((int)$h['tier']); ?></td>
							<td style="padding: 4px;">
								<?php if (!empty($h['conqueror_name'])) { ?>
									<a href="spieler.php?uid=<?php echo (int)$h['cleared_by']; ?>"><b><?php echo htmlspecialchars($h['conqueror_name']); ?></b></a>
								<?php } else { ?>
									<i style="color: #999;">Prajurit Misterius</i>
								<?php } ?>
							</td>
							<td style="padding: 4px; font-size: 10px; line-height: 1.3;">
								<span style="color: #2980b9; font-weight: bold;">+<?php echo number_format((int)$h['reward_exp']); ?> EXP</span><br />
								<span style="color: #27ae60; font-weight: bold;">+<?php echo number_format((int)$h['reward_cp']); ?> CP</span> &nbsp;|&nbsp;
								<span style="color: #d35400; font-weight: bold;">+<?php echo number_format((int)$h['reward_silver']); ?> 🪙</span>
							</td>
						</tr>
					<?php } ?>
				<?php } ?>
				</tbody>
			</table>

		<?php } else { ?>
			<!-- TAB DAFTAR SARANG AKTIF -->
			<?php if (empty($filteredCamps)) { ?>
				<div style="text-align: center; padding: 25px; background: #fafafa; border: 1px dashed #ccc; border-radius: 6px;">
					<p style="font-size: 12px; color: #777;">Tidak ada sarang aktif untuk kategori ini saat ini.</p>
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

					$campImg = file_exists(__DIR__ . "/img/bandit/camp_tier_{$tier}.png") 
						? "img/bandit/camp_tier_{$tier}.png" 
						: "img/bandit/camp_tier_{$tier}.jpg";
				?>
				<div class="pve-card tier-<?php echo $tier; ?>">
					<!-- Top Row: Name, Map Link, Badge -->
					<div class="pve-card-top">
						<div class="pve-title-group">
							<span class="pve-title"><?php echo htmlspecialchars($camp['name']); ?></span>
							<a href="karte.php?d=<?php echo $camp['wref']; ?>&c=<?php echo $mapCheck; ?>" class="pve-coords">(<?php echo $camp['x']; ?>|<?php echo $camp['y']; ?>)</a>
						</div>
						<div>
							<span class="pve-badge <?php echo $badgeClass; ?>"><?php echo BanditCamp::getTierLabel($tier); ?> <?php echo BanditCamp::getTierStars($tier); ?></span>
						</div>
					</div>

					<!-- Body: Image + Specs + Action Buttons -->
					<div class="pve-card-body">
						<div class="pve-thumb-box">
							<img src="<?php echo $campImg; ?>" alt="<?php echo htmlspecialchars($camp['name']); ?>" />
						</div>
						<div class="pve-specs-box">
							<div class="spec-line">
								<span class="spec-label">Jarak Tempuh:</span>
								<span class="spec-val">📍 <?php echo $dist; ?> bidang</span>
							</div>
							<div class="spec-line">
								<span class="spec-label">Total Penjaga:</span>
								<span class="spec-val">🛡️ <?php echo number_format((int)$camp['cur_hp']); ?> pasukan</span>
							</div>
							<div class="spec-line">
								<span class="spec-label">Status:</span>
								<span class="spec-val" style="color: <?php echo ((int)$camp['cur_hp'] > 0 ? '#c0392b' : '#27ae60'); ?>;">
									<?php echo ((int)$camp['cur_hp'] > 0 ? 'Siap Diserang' : 'Tumbang'); ?>
								</span>
							</div>

							<!-- Action Buttons Right on the Card Body -->
							<div class="pve-actions-strip">
								<a class="btn-pve-map" href="karte.php?d=<?php echo $camp['wref']; ?>&c=<?php echo $mapCheck; ?>">🗺️ Peta</a>
								<?php if ($village->resarray['f39'] > 0) { ?>
									<a class="btn-pve-raid" href="a2b.php?s=2&z=<?php echo $camp['wref']; ?>">⚔️ Serang / Jarah</a>
								<?php } else { ?>
									<a class="btn-pve-rally" href="build.php?id=39" title="Klik untuk membangun Titik Temu di desa ini">🏛️ Bangun Titik Temu</a>
								<?php } ?>
							</div>
						</div>
					</div>

					<!-- Loot Strip (4 Resources) -->
					<?php
					$lootWood = isset($camp['wood']) ? min((int)$camp['bounty_wood'], (int)round($camp['wood'])) : (int)$camp['bounty_wood'];
					$lootClay = isset($camp['clay']) ? min((int)$camp['bounty_clay'], (int)round($camp['clay'])) : (int)$camp['bounty_clay'];
					$lootIron = isset($camp['iron']) ? min((int)$camp['bounty_iron'], (int)round($camp['iron'])) : (int)$camp['bounty_iron'];
					$lootCrop = isset($camp['crop']) ? min((int)$camp['bounty_crop'], (int)round($camp['crop'])) : (int)$camp['bounty_crop'];
					?>
					<div class="pve-loot-strip">
						<div class="loot-label">Rampasan:</div>
						<div class="loot-items">
							<div class="loot-res-cell"><img class="r1" src="img/x.gif" title="Kayu" /> <?php echo number_format($lootWood); ?></div>
							<div class="loot-res-cell"><img class="r2" src="img/x.gif" title="Tanah Liat" /> <?php echo number_format($lootClay); ?></div>
							<div class="loot-res-cell"><img class="r3" src="img/x.gif" title="Besi" /> <?php echo number_format($lootIron); ?></div>
							<div class="loot-res-cell"><img class="r4" src="img/x.gif" title="Gandum" /> <?php echo number_format($lootCrop); ?></div>
						</div>
					</div>

					<!-- Rewards Strip -->
					<div class="pve-rewards-strip">
						<span class="reward-pill exp">⭐ +<?php echo number_format((int)$camp['reward_exp']); ?> EXP</span>
						<span class="reward-pill cp">🏛️ +<?php echo number_format((int)$camp['reward_cp']); ?> CP</span>
						<span class="reward-pill silver">🪙 +<?php echo number_format((int)$camp['reward_silver']); ?> Silver</span>
					</div>

					<!-- Guards Breakdown -->
					<div class="pve-guards-strip">
						<div class="guards-header">Penjaga Sarang:</div>
						<div class="guards-chips-row">
							<?php if (empty($guardsList)) { ?>
								<span style="color: #27ae60; font-weight: bold;">Seluruh penjaga telah tumbang!</span>
							<?php } else { ?>
								<?php foreach ($guardsList as $g) { ?>
									<span class="guard-chip">
										<img class="unit u<?php echo $g['id']; ?>" src="img/x.gif" alt="<?php echo htmlspecialchars($g['name']); ?>"> 
										<b><?php echo number_format($g['count']); ?></b> <?php echo htmlspecialchars($g['name']); ?>
									</span>
								<?php } ?>
							<?php } ?>
						</div>
					</div>
				</div>
				<?php } ?>
			<?php } ?>
		<?php } ?>

		<!-- Admin Control Panel (Khusus Admin) -->
		<?php if ($session->access == ADMIN) { ?>
			<div class="pve-admin-bar">
				<b>🛠️ Admin:</b> 
				<a href="bandit.php?action=run">[🔁 Respawn Otomatis]</a> |
				<span>Spawn:</span>
				<a href="bandit.php?action=spawn&tier=1">[T1 Kroco]</a>
				<a href="bandit.php?action=spawn&tier=2">[T2 Begal]</a>
				<a href="bandit.php?action=spawn&tier=3">[T3 Benteng]</a>
				<a href="bandit.php?action=spawn&tier=4" style="color: #c0392b;">[T4 Boss]</a>
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
