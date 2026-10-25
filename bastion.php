<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : bastion.php                                               ##
##  Type           : Ancient Bastion (King of the Hill) Hub                    ##
##  Purpose        : Main user interface for Ancient Bastion at (0|0)          ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
AccessLogger::logRequest();

require_once("GameEngine/Bastion.php");

$start_timer = $generator->pageLoadTimeStart();

if (!$session->logged_in) {
    header("Location: login.php");
    exit;
}

// Inisialisasi status benteng
Bastion::ensureTables();
Bastion::ensureBastionVillage();
Bastion::processTick();

$event = Bastion::getEvent();
$wref = Bastion::BASTION_WREF;
$garrison = Bastion::getBastionGarrison();
$recentLogs = Bastion::getRecentLogs(15);

// Data Aliansi Penguasa
$holderAllyId = (int)($event['holder_alliance'] ?? 0);
$holderUid = (int)($event['holder_uid'] ?? 0);
$holderAllyName = "Penjaga Kuno Netral";
$holderAllyTag = "KUNO";
$holderPlayerName = "Penjaga Kuno";

if ($holderAllyId > 0) {
    $qAlly = $database->query("SELECT name, tag FROM " . TB_PREFIX . "alidata WHERE id = $holderAllyId LIMIT 1");
    if ($qAlly && $a = mysqli_fetch_assoc($qAlly)) {
        $holderAllyName = $a['name'];
        $holderAllyTag = $a['tag'];
    }
}
if ($holderUid > 0) {
    $qUser = $database->query("SELECT username FROM " . TB_PREFIX . "users WHERE id = $holderUid LIMIT 1");
    if ($qUser && $u = mysqli_fetch_assoc($qUser)) {
        $holderPlayerName = $u['username'];
    }
}

// Data Pemenang Terakhir & Hall of Fame
$latestWinner = Bastion::getLatestWinner();
$hallOfFame = Bastion::getHallOfFame(20);

$winnerAllyName = "Tidak Ada";
$winnerAllyTag = "-";
if (!empty($latestWinner)) {
    $winnerAllyName = $latestWinner['alliance_name'];
    $winnerAllyTag = $latestWinner['alliance_tag'];
} elseif (!empty($event['winner_alliance'])) {
    $qWin = $database->query("SELECT name, tag FROM " . TB_PREFIX . "alidata WHERE id = " . (int)$event['winner_alliance'] . " LIMIT 1");
    if ($qWin && $w = mysqli_fetch_assoc($qWin)) {
        $winnerAllyName = $w['name'];
        $winnerAllyTag = $w['tag'];
    }
}

// Perhitungan Durasi Bertahan
$now = time();
$holdDuration = (int)($event['hold_duration'] ?: Bastion::DEFAULT_HOLD_DURATION);
$holdStart = (int)($event['hold_start_time'] ?? 0);
$elapsedSeconds = ($event['status'] == Bastion::STATUS_ACTIVE && $holdStart > 0) ? max(0, $now - $holdStart) : 0;
$remainingSeconds = max(0, $holdDuration - $elapsedSeconds);
$progressPercent = min(100, round(($elapsedSeconds / $holdDuration) * 100, 1));

// Cek apakah aliansi pemain sendiri yang sedang menguasai
$userAllyId = (int)($session->userinfo['alliance'] ?? 0);
$isOurAllianceHolding = ($userAllyId > 0 && $userAllyId === $holderAllyId);
$hasWinnerBuff = Bastion::getProductionMultiplier((int)$village->wid) > 1.0;

// Data Penjadwalan Event
$schedDesc = Bastion::formatScheduleDescription($event);
$nextStartTime = Bastion::getNextScheduledStartTime($event, true);
$isScheduled = ($event['status'] == Bastion::STATUS_SCHEDULED || ($event['status'] == Bastion::STATUS_INACTIVE && $nextStartTime !== null));
$secondsUntilStart = $nextStartTime ? max(0, $nextStartTime - $now) : 0;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo SERVER_NAME; ?> - 🏰 Benteng Kuno (0|0)</title>
	<link rel="shortcut icon" href="favicon.ico" />
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
	<link href="css/mobile_engine.css?v=20261009_m2" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>travian.css?v=20261008d" rel="stylesheet" type="text/css" />
	<script type="text/javascript">
		window.addEvent('domready', start);
	</script>
	<style type="text/css">
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
		div#content.bastion_hub {
			width: 502px !important;
			padding: 30px 25px 50px !important;
			float: left !important;
			box-sizing: content-box !important;
			min-height: 480px !important;
			position: relative !important;
		}

		/* HERO BANNER */
		.bastion-hero {
			background: linear-gradient(135deg, #450a0a 0%, #7f1d1d 50%, #991b1b 100%);
			border-radius: 8px;
			padding: 16px 20px;
			color: #ffffff;
			margin-bottom: 16px;
			box-shadow: 0 4px 14px rgba(0,0,0,0.18);
			border: 1px solid #b91c1c;
			display: flex;
			align-items: center;
			gap: 14px;
		}
		.bastion-hero-icon {
			font-size: 40px;
			line-height: 1;
			filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));
		}
		.bastion-hero-title {
			font-size: 19px;
			font-weight: bold;
			color: #fff;
			margin: 0 0 4px;
			text-shadow: 0 1px 2px rgba(0,0,0,0.5);
		}
		.bastion-hero-subtitle {
			font-size: 11px;
			color: #fecaca;
			margin: 0;
			line-height: 1.4;
		}

		/* STATUS CARD */
		.bastion-card {
			background: #faf7ef;
			border: 1px solid #dcd1ba;
			border-radius: 8px;
			padding: 14px 16px;
			margin-bottom: 16px;
			box-shadow: 0 2px 5px rgba(0,0,0,0.04);
		}
		.bastion-card-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
			border-bottom: 1px dashed #d5c8ad;
			padding-bottom: 8px;
		}
		.bastion-card-title {
			font-size: 13px;
			font-weight: bold;
			color: #451a03;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.badge-live {
			background: #fee2e2;
			color: #b91c1c;
			border: 1px solid #f87171;
			padding: 2px 7px;
			border-radius: 4px;
			font-size: 10px;
			font-weight: bold;
			text-transform: uppercase;
			animation: pulse 1.5s infinite;
		}
		.badge-victory {
			background: #fef3c7;
			color: #92400e;
			border: 1px solid #f59e0b;
			padding: 2px 7px;
			border-radius: 4px;
			font-size: 10px;
			font-weight: bold;
		}
		.badge-peace {
			background: #e2e8f0;
			color: #475569;
			border: 1px solid #cbd5e1;
			padding: 2px 7px;
			border-radius: 4px;
			font-size: 10px;
			font-weight: bold;
		}
		@keyframes pulse {
			0%, 100% { opacity: 1; }
			50% { opacity: 0.5; }
		}

		/* HOLDER BANNER */
		.holder-box {
			background: #fff;
			border: 1px solid #e2d7c0;
			border-radius: 6px;
			padding: 12px;
			margin-bottom: 12px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.holder-info-title {
			font-size: 10px;
			color: #78716c;
			text-transform: uppercase;
			font-weight: bold;
		}
		.holder-info-val {
			font-size: 16px;
			font-weight: bold;
			color: #991b1b;
			margin-top: 2px;
		}
		.holder-info-sub {
			font-size: 11px;
			color: #57534e;
		}

		/* TIMER DISPLAY */
		.timer-box {
			text-align: right;
		}
		.timer-num {
			font-size: 20px;
			font-weight: bold;
			color: #1c1917;
			font-family: monospace;
		}
		.timer-label {
			font-size: 10px;
			color: #78716c;
			text-transform: uppercase;
		}

		/* PROGRESS BAR */
		.progress-wrap {
			background: #e7e5e4;
			border-radius: 8px;
			height: 12px;
			overflow: hidden;
			margin: 10px 0 6px;
			border: 1px solid #d6d3d1;
		}
		.progress-bar {
			background: linear-gradient(90deg, #f59e0b 0%, #ef4444 100%);
			height: 100%;
			border-radius: 6px;
			transition: width 0.4s ease;
		}

		/* ACTION BUTTONS */
		.bastion-actions {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px;
			margin-top: 14px;
		}
		.bastion-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			padding: 9px 12px;
			border-radius: 5px;
			font-size: 11px;
			font-weight: bold;
			text-decoration: none !important;
			cursor: pointer;
			text-align: center;
			transition: all 0.15s ease;
			box-sizing: border-box;
		}
		.bastion-btn-attack {
			background: linear-gradient(to bottom, #dc2626, #991b1b);
			color: #fff !important;
			border: 1px solid #7f1d1d;
		}
		.bastion-btn-attack:hover {
			background: linear-gradient(to bottom, #ef4444, #b91c1c);
		}
		.bastion-btn-reinf {
			background: linear-gradient(to bottom, #16a34a, #15803d);
			color: #fff !important;
			border: 1px solid #14532d;
		}
		.bastion-btn-reinf:hover {
			background: linear-gradient(to bottom, #22c55e, #16a34a);
		}
		.bastion-btn-map {
			background: #e7e5e4;
			color: #292524 !important;
			border: 1px solid #d6d3d1;
			grid-column: span 2;
		}
		.bastion-btn-map:hover {
			background: #d6d3d1;
		}

		/* PERK PILLS */
		.perk-grid {
			display: grid;
			grid-template-columns: 1fr 1fr 1fr;
			gap: 6px;
			margin-top: 10px;
		}
		.perk-pill {
			background: #fff;
			border: 1px solid #e7e5e4;
			border-radius: 5px;
			padding: 8px 6px;
			text-align: center;
		}
		.perk-icon {
			font-size: 16px;
			margin-bottom: 2px;
		}
		.perk-val {
			font-size: 11px;
			font-weight: bold;
			color: #15803d;
		}
		.perk-desc {
			font-size: 9px;
			color: #78716c;
		}

		/* CHAMPION & HALL OF FAME STYLES */
		.champion-card {
			background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 45%, #fff7ed 100%);
			border: 2px solid #f59e0b;
			border-radius: 8px;
			padding: 14px 16px;
			margin-bottom: 14px;
			box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
			position: relative;
			overflow: hidden;
		}
		.champion-card::after {
			content: '👑';
			position: absolute;
			right: 5px;
			bottom: -15px;
			font-size: 80px;
			opacity: 0.10;
			pointer-events: none;
		}
		.champion-badge-ribbon {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			background: #f59e0b;
			color: #fff;
			padding: 3px 10px;
			border-radius: 12px;
			font-size: 10px;
			font-weight: bold;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 8px;
		}
		.champion-grid {
			display: grid;
			grid-template-columns: auto 1fr auto;
			gap: 12px;
			align-items: center;
		}
		.champion-trophy {
			font-size: 38px;
			line-height: 1;
			filter: drop-shadow(0 2px 4px rgba(217, 119, 6, 0.3));
		}
		.champion-names {
			display: flex;
			flex-direction: column;
		}
		.champion-ally {
			font-size: 17px;
			font-weight: 800;
			color: #92400e;
			text-decoration: none;
		}
		.champion-ally:hover {
			text-decoration: underline;
		}
		.champion-leader {
			font-size: 12px;
			color: #78350f;
			margin-top: 3px;
		}
		.champion-meta {
			text-align: right;
			font-size: 11px;
			color: #854d0e;
		}
		.champion-buff-status {
			margin-top: 10px;
			padding: 7px 11px;
			background: rgba(255,255,255,0.75);
			border-radius: 6px;
			border: 1px dashed #f59e0b;
			font-size: 11px;
			color: #92400e;
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 6px;
		}

		/* HALL OF FAME TABLE */
		.hof-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 10px;
			font-size: 11px;
		}
		.hof-table th {
			background: #fef3c7;
			color: #78350f;
			padding: 7px 10px;
			text-align: left;
			font-weight: bold;
			border-bottom: 2px solid #fde68a;
		}
		.hof-table td {
			padding: 8px 10px;
			border-bottom: 1px solid #fef08a;
			vertical-align: middle;
		}
		.hof-medal {
			font-size: 14px;
			margin-right: 4px;
		}
		.hof-season-badge {
			display: inline-block;
			background: #78350f;
			color: #fef3c7;
			padding: 2px 6px;
			border-radius: 4px;
			font-size: 9px;
			font-weight: bold;
		}

		/* LOGS TABLE */
		.log-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 8px;
			font-size: 11px;
		}
		.log-table th {
			background: #ede6d6;
			color: #57534e;
			padding: 6px 8px;
			text-align: left;
			font-weight: bold;
			border-bottom: 1px solid #dcd1ba;
		}
		.log-table td {
			padding: 6px 8px;
			border-bottom: 1px solid #e7e2d4;
			vertical-align: middle;
		}
		.log-time {
			color: #8c7d67;
			font-size: 10px;
			white-space: nowrap;
		}
		.tag-capture {
			background: #fee2e2;
			color: #991b1b;
			padding: 1px 4px;
			border-radius: 3px;
			font-size: 9px;
			font-weight: bold;
		}
		.tag-repelled {
			background: #e0f2fe;
			color: #0369a1;
			padding: 1px 4px;
			border-radius: 3px;
			font-size: 9px;
			font-weight: bold;
		}
		.tag-bot {
			background: #fef3c7;
			color: #b45309;
			padding: 1px 4px;
			border-radius: 3px;
			font-size: 9px;
			font-weight: bold;
		}
		.tag-victory {
			background: #dcfce7;
			color: #15803d;
			padding: 1px 4px;
			border-radius: 3px;
			font-size: 9px;
			font-weight: bold;
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
	<div id="content" class="bastion_hub">
				<!-- HERO BANNER -->
				<div class="bastion-hero">
					<div class="bastion-hero-icon">🏰</div>
					<div>
						<div class="bastion-hero-title">Benteng Kuno (The Ancient Bastion)</div>
						<div class="bastion-hero-subtitle">
							Pusat Sumbu Peta <b>(0|0)</b> — Arena Perebutan Kedaulatan Aliansi. Pertahankan benteng selama <b>12 Jam</b> berturut-turut untuk memenangkan Berkah Kedaulatan seluruh kerajaan!
						</div>
						<div style="margin-top: 8px;">
							<a href="manual.php?typ=7&amp;s=1" target="_blank" style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.35); padding:4px 10px; border-radius:4px; font-size:11px; font-weight:bold; text-decoration:none;">
								📖 Pelajari Detail &amp; Aturan Event di Buku Manual &raquo;
							</a>
						</div>
					</div>
				</div>

				<!-- BANNER JADWAL JIKA EVENT TERJADWAL -->
				<?php if ($isScheduled && $event['status'] != Bastion::STATUS_ACTIVE): ?>
				<div style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: #fff; border-radius: 8px; padding: 16px; margin-bottom: 14px; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);">
					<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
						<div>
							<div style="font-size: 11px; text-transform: uppercase; color: #93c5fd; font-weight: bold; letter-spacing: 0.5px;">
								⏳ Jadwal Pembukaan Gerbang Benteng Kuno
							</div>
							<div style="font-size: 16px; font-weight: bold; color: #fff; margin-top: 3px;">
								<?php echo htmlspecialchars($schedDesc); ?>
							</div>
							<?php if ($nextStartTime): ?>
								<div style="font-size: 12px; color: #bfdbfe; margin-top: 3px;">
									Jadwal: <b><?php echo date('l, d F Y', $nextStartTime) . ' pukul ' . date('H:i', $nextStartTime); ?> WIB</b>
								</div>
							<?php endif; ?>
							<div style="font-size: 11px; color: #93c5fd; margin-top: 5px;">
								💡 Siapkan pasukan aliansi Anda! Pelajari medan di (0|0) dan koordinasikan strategi sebelum gerbang dibuka.
							</div>
						</div>
						<?php if ($nextStartTime): ?>
							<div style="text-align: right; background: rgba(0,0,0,0.3); padding: 8px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15);">
								<div style="font-size: 10px; color: #93c5fd; text-transform: uppercase;">Gerbang Dibuka Dalam</div>
								<div style="font-size: 20px; font-weight: bold; font-family: monospace; color: #fde047;" id="openingCountdown">
									<?php
									$diff = max(0, $nextStartTime - time());
									$od = floor($diff / 86400);
									$oh = floor(($diff % 86400) / 3600);
									$om = floor(($diff % 3600) / 60);
									$os = $diff % 60;
									echo ($od > 0 ? $od . 'h ' : '') . sprintf("%02d:%02d:%02d", $oh, $om, $os);
									?>
								</div>
								<div style="font-size: 9px; color: #cbd5e1;">Hitung Mundur Live</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if (!empty($latestWinner)): ?>
				<!-- CARD JUARA BERTAHAN / PEMENANG EVENT SAAT ITU -->
				<div class="champion-card">
					<div class="champion-badge-ribbon">
						<span>🏆</span> Pemenang Event / Juara Bertahan (Season #<?php echo (int)$latestWinner['season']; ?>)
					</div>
					<div class="champion-grid">
						<div class="champion-trophy">🏆</div>
						<div class="champion-names">
							<a href="allianz.php?aid=<?php echo (int)$latestWinner['alliance_id']; ?>" class="champion-ally">
								<?php echo htmlspecialchars($latestWinner['alliance_name']); ?> 
								<span style="font-size:13px; color:#b45309;">[<?php echo htmlspecialchars($latestWinner['alliance_tag']); ?>]</span>
							</a>
							<div class="champion-leader">
								👑 Panglima Pemenang: <b><a href="spieler.php?uid=<?php echo (int)$latestWinner['leader_uid']; ?>" style="color:#78350f; text-decoration:underline;"><?php echo htmlspecialchars($latestWinner['leader_username']); ?></a></b>
							</div>
						</div>
						<div class="champion-meta">
							<div><b><?php echo date('d M Y', (int)$latestWinner['win_time']); ?></b></div>
							<div style="font-size:10px; color:#a16207;">Pukul <?php echo date('H:i', (int)$latestWinner['win_time']); ?> WIB</div>
							<div style="font-size:9.5px; color:#15803d; font-weight:bold; margin-top:2px;">Bertahan 12 Jam Penuh</div>
						</div>
					</div>
					<div class="champion-buff-status">
						<?php if ((int)$latestWinner['buff_until'] > time()): ?>
							<?php
							$diffBuff = (int)$latestWinner['buff_until'] - time();
							$bH = floor($diffBuff / 3600);
							$bM = floor(($diffBuff % 3600) / 60);
							?>
							<span>✨ <b>Berkah Kedaulatan Masih Aktif!</b> (+10% Res, +15% Speed, +5% Def bagi seluruh anggota aliansi)</span>
							<span style="font-weight:bold; color:#15803d; white-space:nowrap;">Sisa Buff: <?php echo $bH; ?>j <?php echo $bM; ?>m</span>
						<?php else: ?>
							<span>🛡️ Masa Berkah Kedaulatan telah selesai. Gelar juara dipertahankan hingga penakluk baru tiba!</span>
							<span style="color:#78716c; font-size:10px; white-space:nowrap;">Musim Berikutnya Siap Dimulai</span>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- STATUS KONTROL UTAMA -->
				<div class="bastion-card">
					<div class="bastion-card-header">
						<div class="bastion-card-title">
							<span>⚔️</span> Status Perebutan Benteng
						</div>
						<div>
							<?php if ($event['status'] == Bastion::STATUS_ACTIVE): ?>
								<span class="badge-live">● Event Sedang Berlangsung</span>
							<?php elseif ($event['status'] == Bastion::STATUS_SCHEDULED || ($isScheduled && $event['status'] != Bastion::STATUS_ACTIVE)): ?>
								<span style="background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:bold;">⏳ Event Terjadwal</span>
							<?php elseif ($event['status'] == Bastion::STATUS_VICTORY): ?>
								<span class="badge-victory">🏆 Masa Kemenangan Aktif</span>
							<?php else: ?>
								<span class="badge-peace">🛡️ Pintu Benteng Ditutup</span>
							<?php endif; ?>
						</div>
					</div>

					<!-- KOTAK PENGUASA SAAT INI -->
					<div class="holder-box">
						<div>
							<div class="holder-info-title">Penguasa Benteng Saat Ini</div>
							<div class="holder-info-val">
								<?php echo htmlspecialchars($holderAllyName); ?> 
								<span style="font-size: 13px; color: #78716c;">[<?php echo htmlspecialchars($holderAllyTag); ?>]</span>
							</div>
							<div class="holder-info-sub">
								Panglima Penjaga: <b><?php echo htmlspecialchars($holderPlayerName); ?></b>
								<?php if ($isOurAllianceHolding): ?>
									<span style="color:#15803d; font-weight:bold;">(Aliansi Anda!)</span>
								<?php endif; ?>
							</div>
						</div>

						<div class="timer-box">
							<div class="timer-label">Sisa Waktu Menang</div>
							<div class="timer-num" id="bastionCountdown">
								<?php
								if ($event['status'] == Bastion::STATUS_ACTIVE && $holdStart > 0) {
									$h = floor($remainingSeconds / 3600);
									$m = floor(($remainingSeconds % 3600) / 60);
									$s = $remainingSeconds % 60;
									echo sprintf("%02d:%02d:%02d", $h, $m, $s);
								} else {
									echo "12:00:00";
								}
								?>
							</div>
							<div style="font-size: 10px; color: #a8a29e;">Target: 12 Jam Penuh</div>
						</div>
					</div>

					<!-- PROGRESS BAR 12 JAM -->
					<div style="display:flex; justify-content:space-between; font-size:10px; color:#78716c;">
						<span>Waktu Bertahan: <b><?php echo sprintf("%02d:%02d:%02d", floor($elapsedSeconds/3600), floor(($elapsedSeconds%3600)/60), $elapsedSeconds%60); ?></b></span>
						<span>Progres: <b><?php echo $progressPercent; ?>%</b></span>
					</div>
					<div class="progress-wrap">
						<div class="progress-bar" style="width: <?php echo $progressPercent; ?>%;"></div>
					</div>

					<!-- ESTIMASI GARNISUN BENTENG -->
					<div style="background:#fff; border:1px solid #e7e5e4; border-radius:5px; padding:8px 12px; margin-top:10px; font-size:11px; display:flex; justify-content:space-between; align-items:center;">
						<div>
							🛡️ Estimasi Garnisun di Benteng: <b><?php echo number_format($garrison['total']); ?> Pasukan</b>
							<span style="color:#78716c; font-size:10px;">(Tembok Kuno Lv 12)</span>
						</div>
						<div style="font-size:10px; color:#15803d; font-weight:bold;">
							🌾 Zero Crop Starvation
						</div>
					</div>

					<!-- TOMBOL TINDAKAN -->
					<div class="bastion-actions">
						<?php if ($event['status'] == Bastion::STATUS_ACTIVE): ?>
							<a href="a2b.php?z=<?php echo $wref; ?>" class="bastion-btn bastion-btn-attack">
								⚔️ Serang Benteng (0|0)
							</a>
							<a href="a2b.php?z=<?php echo $wref; ?>" class="bastion-btn bastion-btn-reinf">
								🛡️ Kirim Bantuan ke (0|0)
							</a>
						<?php else: ?>
							<button type="button" class="bastion-btn" style="background:#94a3b8; color:#fff; cursor:not-allowed; opacity:0.85; border:none;" disabled title="Benteng belum dapat diserang selama gerbang terkunci">
								🔒 Gerbang Terkunci (Event Belum Aktif)
							</button>
						<?php endif; ?>
						<a href="karte.php?z=<?php echo $wref; ?>" class="bastion-btn bastion-btn-map">
							🗺️ Buka Koordinat (0|0) di Peta Kerajaan
						</a>
					</div>
				</div>

				<!-- BERKAH KEDAULATAN BENTENG (BUFFS) -->
				<div class="bastion-card">
					<div class="bastion-card-title">
						<span>✨</span> Berkah Kedaulatan Aliansi Pemenang (3 Hari)
					</div>
					<div style="font-size: 11px; color: #57534e; margin-top: 4px;">
						Aliansi pertama yang sukses menjaga benteng selama 12 jam berturut-turut akan membuka berkah ini bagi seluruh anggotanya:
					</div>
					<div class="perk-grid">
						<div class="perk-pill">
							<div class="perk-icon">🌾</div>
							<div class="perk-val">+10%</div>
							<div class="perk-desc">Produksi Resource</div>
						</div>
						<div class="perk-pill">
							<div class="perk-icon">🏇</div>
							<div class="perk-val">+15%</div>
							<div class="perk-desc">Kecepatan Pasukan</div>
						</div>
						<div class="perk-pill">
							<div class="perk-icon">🛡️</div>
							<div class="perk-val">+5%</div>
							<div class="perk-desc">Bonus Pertahanan</div>
						</div>
					</div>

					<?php if ($hasWinnerBuff): ?>
						<div style="margin-top: 10px; background: #dcfce7; border: 1px solid #86efac; border-radius: 5px; padding: 8px 10px; font-size: 11px; color: #166534; font-weight: bold; text-align: center;">
							🎉 Aliansi Anda saat ini sedang menikmati Berkah Kedaulatan Benteng Kuno!
						</div>
					<?php endif; ?>
				</div>

				<!-- RUANG KEHORMATAN (HALL OF FAME) BENTENG KUNO -->
				<div class="bastion-card" style="border: 1px solid #fde68a;">
					<div class="bastion-card-title" style="display:flex; justify-content:space-between; align-items:center;">
						<div>
							<span>🏛️</span> Hall of Fame - Ruang Kehormatan Penguasa Benteng Kuno
						</div>
						<span style="font-size:10px; background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-weight:bold; border:1px solid #fde68a;">
							Total <?php echo count($hallOfFame); ?> Musim
						</span>
					</div>
					<div style="font-size: 11px; color: #57534e; margin-top: 4px;">
						Daftar para aliansi legendaris dan panglima perang yang berhasil menaklukkan serta mempertahankan Benteng Kuno (0|0) selama 12 jam penuh dari masa ke masa:
					</div>

					<?php if (empty($hallOfFame)): ?>
						<div style="padding: 15px; text-align: center; color: #78716c; font-size: 11px;">
							Belum ada aliansi yang tercatat memenangkan perebutan Benteng Kuno. Jadilah yang pertama menorehkan sejarah!
						</div>
					<?php else: ?>
						<table class="hof-table">
							<thead>
								<tr>
									<th style="width: 75px;">Musim</th>
									<th>Aliansi Pemenang</th>
									<th>Panglima Juara</th>
									<th style="width: 125px;">Waktu Menang</th>
									<th style="width: 95px; text-align:right;">Gelar</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($hallOfFame as $idx => $hof): ?>
									<?php
									$medal = ($idx === 0) ? '🥇' : (($idx === 1) ? '🥈' : (($idx === 2) ? '🥉' : '🎖️'));
									$isCurrent = ($idx === 0);
									$rowBg = $isCurrent ? 'background: #fffdf5;' : '';
									?>
									<tr style="<?php echo $rowBg; ?>">
										<td>
											<span class="hof-medal"><?php echo $medal; ?></span>
											<span class="hof-season-badge">S-<?php echo (int)$hof['season']; ?></span>
										</td>
										<td>
											<a href="allianz.php?aid=<?php echo (int)$hof['alliance_id']; ?>" style="font-weight:bold; color:#92400e; text-decoration:underline;">
												<?php echo htmlspecialchars($hof['alliance_name']); ?>
											</a>
											<span style="font-size:10px; color:#b45309; font-weight:bold;">[<?php echo htmlspecialchars($hof['alliance_tag']); ?>]</span>
										</td>
										<td>
											<a href="spieler.php?uid=<?php echo (int)$hof['leader_uid']; ?>" style="color:#475569; font-weight:bold;">
												<?php echo htmlspecialchars($hof['leader_username']); ?>
											</a>
										</td>
										<td style="color:#78716c; font-size:10px;">
											<?php echo date('d M Y H:i', (int)$hof['win_time']); ?> WIB
										</td>
										<td style="text-align:right;">
											<?php if ($isCurrent): ?>
												<span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:1px 6px; border-radius:3px; font-size:9.5px; font-weight:bold; white-space:nowrap;">
													Juara Bertahan
												</span>
											<?php else: ?>
												<span style="color:#64748b; font-size:9.5px;">
													<?php echo htmlspecialchars($hof['note'] ?: 'Juara Musim ' . $hof['season']); ?>
												</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<!-- RIWAYAT PERTEMPURAN & LOG EVENT -->
				<div class="bastion-card">
					<div class="bastion-card-title">
						<span>📜</span> Riwayat Pertempuran & Kejadian Benteng
					</div>

					<?php if (empty($recentLogs)): ?>
						<div style="padding: 15px; text-align: center; color: #78716c; font-size: 11px;">
							Belum ada riwayat pertempuran baru di Benteng Kuno.
						</div>
					<?php else: ?>
						<table class="log-table">
							<thead>
								<tr>
									<th style="width: 75px;">Waktu</th>
									<th style="width: 70px;">Tipe</th>
									<th>Keterangan</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($recentLogs as $log): ?>
									<?php
									$tagClass = 'tag-bot';
									$tagText = 'Info';
									switch ($log['log_type']) {
										case 'capture':
											$tagClass = 'tag-capture';
											$tagText = 'Direbut';
											break;
										case 'repelled':
											$tagClass = 'tag-repelled';
											$tagText = 'Dihalau';
											break;
										case 'bot_attack':
										case 'bot_reinforce':
											$tagClass = 'tag-bot';
											$tagText = 'Bot Pasukan';
											break;
										case 'victory':
											$tagClass = 'tag-victory';
											$tagText = 'Kemenangan';
											break;
									}
									?>
									<tr>
										<td class="log-time"><?php echo date('H:i:s', $log['event_time']); ?></td>
										<td><span class="<?php echo $tagClass; ?>"><?php echo $tagText; ?></span></td>
										<td>
											<b><?php echo htmlspecialchars($log['title']); ?></b><br />
											<span style="color: #666; font-size: 10px;"><?php echo htmlspecialchars($log['description']); ?></span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

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
					<?php echo CALCULATED_IN; ?> <b><?php echo round(($generator->pageLoadTimeEnd()-$start_timer)*1000); ?></b> ms
					| <?php echo SERVER_TIME; ?> <span id="tp1" class="b"><?php echo date('H:i:s'); ?></span>
				</div>
			</div>
		</div>

		<div id="ce"></div>

<script type="text/javascript">
(function() {
    // 1. Countdown perebutan 12 jam (jika sedang aktif)
    var secondsLeft = <?php echo ($event['status'] == Bastion::STATUS_ACTIVE && $holdStart > 0) ? (int)$remainingSeconds : 0; ?>;
    var timerEl = document.getElementById('bastionCountdown');
    if (timerEl && secondsLeft > 0) {
        var interval = setInterval(function() {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(interval);
                timerEl.innerHTML = "00:00:00";
                timerEl.style.color = "#15803d";
                setTimeout(function() { window.location.reload(); }, 2000);
                return;
            }
            var h = Math.floor(secondsLeft / 3600);
            var m = Math.floor((secondsLeft % 3600) / 60);
            var s = secondsLeft % 60;
            timerEl.innerHTML = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }, 1000);
    }

    // 2. Countdown pembukaan gerbang (jika terjadwal)
    var openSeconds = <?php echo (int)$secondsUntilStart; ?>;
    var openEl = document.getElementById('openingCountdown');
    if (openEl && openSeconds > 0) {
        var openInterval = setInterval(function() {
            openSeconds--;
            if (openSeconds <= 0) {
                clearInterval(openInterval);
                openEl.innerHTML = "DIBUKA!";
                openEl.style.color = "#86efac";
                setTimeout(function() { window.location.reload(); }, 3000);
                return;
            }
            var od = Math.floor(openSeconds / 86400);
            var oh = Math.floor((openSeconds % 86400) / 3600);
            var om = Math.floor((openSeconds % 3600) / 60);
            var os = openSeconds % 60;
            var str = (od > 0 ? od + 'h ' : '') + (oh < 10 ? '0' : '') + oh + ':' + (om < 10 ? '0' : '') + om + ':' + (os < 10 ? '0' : '') + os;
            openEl.innerHTML = str;
        }, 1000);
    }
})();
</script>
</body>
</html>
