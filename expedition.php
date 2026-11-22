<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : expedition.php                                            ##
##  Type           : Alliance Expedition & Forward Camp Hub                    ##
##  Purpose        : Co-op PvE Lobby for Bandit Fortress & World Boss Raids   ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
AccessLogger::logRequest();

require_once("GameEngine/BanditCamp.php");
require_once("GameEngine/Expedition/ExpeditionEngine.php");

$start_timer = $generator->pageLoadTimeStart();
ExpeditionEngine::ensureTables();

$uid = (int)($session->uid ?? 0);
$userAlliance = (int)($session->userarray['alliance'] ?? 0);
$currentVref = (int)($village->wid ?? 0);

// Deteksi suku desa pemberangkatan (village tribe) yang akurat
$userTribe = 0;
if (!empty($village->tribe) && (int)$village->tribe > 0) {
    $userTribe = (int)$village->tribe;
} elseif (!empty($village->infoarray['tribe']) && (int)$village->infoarray['tribe'] > 0) {
    $userTribe = (int)$village->infoarray['tribe'];
} else {
    $vRow = $database->query_return("SELECT tribe FROM `" . TB_PREFIX . "vdata` WHERE wref = $currentVref LIMIT 1");
    if (!empty($vRow[0]['tribe']) && (int)$vRow[0]['tribe'] > 0) {
        $userTribe = (int)$vRow[0]['tribe'];
    }
}
// Jika masih 0, deteksi dari jenis pasukan yang ada di desa saat ini
if ($userTribe <= 0) {
    $vUnitsCheck = $database->getUnit($currentVref, false);
    if (!empty($vUnitsCheck)) {
        $candidateTribes = [
            1 => [1, 10],   // Romans
            2 => [11, 20],  // Teutons
            3 => [21, 30],  // Gauls
            6 => [51, 60],  // Huns
            7 => [61, 70],  // Egyptians
            8 => [71, 80],  // Spartans
            9 => [81, 90],  // Vikings
            10 => [91, 100] // Nusantara
        ];
        foreach ($candidateTribes as $cTribe => $range) {
            for ($u = $range[0]; $u <= $range[1]; $u++) {
                if (!empty($vUnitsCheck['u' . $u]) && (int)$vUnitsCheck['u' . $u] > 0) {
                    $userTribe = $cTribe;
                    break 2;
                }
            }
        }
    }
}
// Fallback ke suku akun jika desa belum memiliki unit
if ($userTribe <= 0) {
    $userTribe = (!empty($session->tribe) && $session->tribe > 0) ? (int)$session->tribe : 1;
}

// Cek ketersediaan Hero yang masih hidup dan berada di desa saat ini
$heroInVillage = false;
if (!empty($village->unitarray['hero']) && (int)$village->unitarray['hero'] > 0) {
    $heroInVillage = true;
} else {
    $heroCheck = $database->query_return("SELECT heroid, dead, wref FROM `" . TB_PREFIX . "hero` WHERE uid = $uid AND dead = 0 LIMIT 1");
    if (!empty($heroCheck[0]) && (int)$heroCheck[0]['wref'] === $currentVref) {
        $heroInVillage = true;
    }
}

$msgError = '';
$msgSuccess = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create') {
        $campId = (int)($_POST['camp_id'] ?? 0);
        $prepMin = max(10, min(120, (int)($_POST['prep_minutes'] ?? 20)));
        $hasHero = (!empty($_POST['has_hero']) && $heroInVillage);

        $troops = [];
        for ($i = 1; $i <= 10; $i++) {
            $troops['u' . $i] = max(0, (int)($_POST['u' . $i] ?? 0));
        }

        $res = ExpeditionEngine::createExpedition($uid, $campId, $prepMin, $currentVref, $troops, $hasHero);
        if ($res['status'] === 'success') {
            $msgSuccess = $res['message'];
            header("Location: expedition.php?id=" . $res['expedition_id'] . "&msg=" . urlencode($msgSuccess));
            exit;
        } else {
            $msgError = $res['message'];
        }

    } elseif ($action === 'join') {
        $expId = (int)($_POST['expedition_id'] ?? 0);
        $hasHero = (!empty($_POST['has_hero']) && $heroInVillage);

        $troops = [];
        for ($i = 1; $i <= 10; $i++) {
            $troops['u' . $i] = max(0, (int)($_POST['u' . $i] ?? 0));
        }

        $res = ExpeditionEngine::joinExpedition($expId, $uid, $currentVref, $troops, $hasHero);
        if ($res['status'] === 'success') {
            $msgSuccess = $res['message'];
            header("Location: expedition.php?id=" . $expId . "&msg=" . urlencode($msgSuccess));
            exit;
        } else {
            $msgError = $res['message'];
        }

    } elseif ($action === 'cancel') {
        $expId = (int)($_POST['expedition_id'] ?? 0);
        $res = ExpeditionEngine::cancelExpedition($expId, $uid, "Dibatalkan oleh pemimpin ekspedisi");
        if ($res['status'] === 'success') {
            header("Location: expedition.php?msg=" . urlencode($res['message']));
            exit;
        } else {
            $msgError = $res['message'];
        }

    } elseif ($action === 'launch_now') {
        $expId = (int)($_POST['expedition_id'] ?? 0);
        $details = ExpeditionEngine::getExpeditionDetails($expId);
        if (!empty($details) && (int)$details['expedition']['leader_uid'] === $uid) {
            $res = ExpeditionEngine::resolveAssault($expId);
            if ($res['status'] === 'victory') {
                $msgSuccess = "Serangan Forward Camp berhasil! " . $details['expedition']['camp_name'] . " berhasil ditaklukkan bersama aliansi Anda!";
            } else {
                $msgError = "Pertempuran sengit terjadi namun pertahanan lawan masih bertahan. Pasukan telah dipulangkan.";
            }
            header("Location: berichte.php");
            exit;
        } else {
            $msgError = "Hanya pemimpin ekspedisi yang dapat meluncurkan serangan lebih awal.";
        }
    }
}

if (!empty($_GET['msg'])) {
    $msgSuccess = $_GET['msg'];
}

// Cari ekspedisi yang akan ditampilkan
$expeditionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campId = isset($_GET['camp']) ? (int)$_GET['camp'] : 0;

$campData = null;
if ($campId > 0) {
    $camps = $database->query_return("SELECT * FROM `" . TB_PREFIX . "bandit_camps` WHERE id = $campId AND status = 1 LIMIT 1");
    if (!empty($camps[0])) {
        $campData = $camps[0];
        $coor = $database->getCoor((int)$campData['wref']);
        $campData['x'] = $coor['x'] ?? 0;
        $campData['y'] = $coor['y'] ?? 0;

        // Cek jika sudah ada ekspedisi aktif untuk camp ini
        $activeForCamp = ExpeditionEngine::getActiveCampExpedition($campId);
        if (!empty($activeForCamp)) {
            $expeditionId = (int)$activeForCamp['expedition']['id'];
        }
    }
}

$expDetails = null;
if ($expeditionId > 0) {
    $expDetails = ExpeditionEngine::getExpeditionDetails($expeditionId);
    if (!empty($expDetails)) {
        $campId = (int)$expDetails['expedition']['camp_id'];
        $campData = $expDetails['expedition'];
        if (!isset($campData['x']) || !isset($campData['y'])) {
            $coor = $database->getCoor((int)$campData['target_wref']);
            $campData['x'] = $coor['x'] ?? 0;
            $campData['y'] = $coor['y'] ?? 0;
        }
    }
}

// Ambil daftar ekspedisi aliansi aktif
$allianceExpeditions = ($userAlliance > 0) ? ExpeditionEngine::getAllianceActiveExpeditions($userAlliance) : [];

// Ambil kuota harian user
$dailyUsage = ExpeditionEngine::getDailyUsage($uid);
$dailyRemaining = max(0, ExpeditionEngine::DAILY_LIMIT - $dailyUsage);

// Ambil troops di desa saat ini
$villageUnits = $database->getUnit($currentVref, false);

// Buat pemetaan jumlah pasukan maksimal per unit (u1..u10)
$maxTroopMap = [];
for ($i = 1; $i <= 10; $i++) {
    $globalUId = ($userTribe - 1) * 10 + $i;
    $maxTroopMap['u' . $i] = (int)($villageUnits['u' . $globalUId] ?? 0);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo SERVER_NAME; ?> &raquo; Alliance Expedition &bull; Forward Camp</title>
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
	<link href="css/mobile_engine.css?v=20261009_m2" rel="stylesheet" type="text/css" />
	<link href="<?php echo GP_LOCATE; ?>travian.css?v=20261008d" rel="stylesheet" type="text/css" />
	<script type="text/javascript">
		window.addEvent('domready', start);

		function fillTroop(triggerEl, inputName, maxCount) {
			var form = triggerEl.closest('form');
			if (form) {
				var input = form.elements[inputName];
				if (input) {
					input.value = maxCount;
				}
			}
		}

		function fillAllMax(triggerEl) {
			var form = triggerEl.closest('form');
			if (!form) return;
			var troops = <?php echo json_encode($maxTroopMap); ?>;
			for (var key in troops) {
				var input = form.elements[key];
				if (input) {
					input.value = troops[key];
				}
			}
		}

		function clearAllTroops(triggerEl) {
			var form = triggerEl.closest('form');
			if (!form) return;
			for (var i = 1; i <= 10; i++) {
				var input = form.elements['u' + i];
				if (input) {
					input.value = 0;
				}
			}
		}

		// Live Expedition Countdown Timers
		(function() {
			function tickExpTimers() {
				var timers = document.querySelectorAll('.exp-live-timer');
				for (var i = 0; i < timers.length; i++) {
					var el = timers[i];
					var s = parseInt(el.getAttribute('data-seconds'), 10);
					if (isNaN(s)) continue;
					if (s > 0) {
						s--;
						el.setAttribute('data-seconds', s);
						var h = Math.floor(s / 3600);
						var m = Math.floor((s % 3600) / 60);
						var sec = s % 60;
						var str = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
						el.innerHTML = str;
					} else if (s === 0) {
						el.innerHTML = '00:00:00';
						el.setAttribute('data-seconds', -1);
						setTimeout(function() { window.location.reload(); }, 2500);
					}
				}
			}
			setInterval(tickExpTimers, 1000);
		})();
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

		/* DESKTOP LAYOUT (min-width: 861px) */
		@media screen and (min-width: 861px) {
			div#content.statistics {
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
			div#side_info {
				margin-top: 32px !important;
			}
			/* Sleek Custom Scrollbar */
			div#content.statistics::-webkit-scrollbar {
				width: 7px;
			}
			div#content.statistics::-webkit-scrollbar-track {
				background: #f1ebd8;
				border-radius: 4px;
			}
			div#content.statistics::-webkit-scrollbar-thumb {
				background: #b5a485;
				border-radius: 4px;
			}
			div#content.statistics::-webkit-scrollbar-thumb:hover {
				background: #877555;
			}
		}

		/* MOBILE RESPONSIVE ENGINE (max-width: 860px) */
		@media screen and (max-width: 860px) {
			div#content.statistics {
				width: 100% !important;
				max-width: 100% !important;
				float: none !important;
				padding: 6px 4px !important;
				box-sizing: border-box !important;
				min-height: 0 !important;
				max-height: none !important;
				overflow-y: visible !important;
				overflow-x: hidden !important;
			}
			.exp-header {
				padding: 12px 14px !important;
				flex-direction: column !important;
				align-items: flex-start !important;
				gap: 8px !important;
			}
			.exp-header .exp-pill {
				align-self: flex-start !important;
			}
			.exp-title {
				font-size: 15px !important;
			}
			.exp-card {
				padding: 12px 10px !important;
				margin-bottom: 10px !important;
			}
			.exp-camp-target-bar {
				flex-direction: column !important;
				align-items: flex-start !important;
				gap: 8px !important;
			}
			.exp-camp-timer-wrap {
				width: 100% !important;
			}
			.exp-timer-pill {
				display: flex !important;
				justify-content: center !important;
				width: 100% !important;
				box-sizing: border-box !important;
			}
			.exp-actions-bar {
				flex-direction: column-reverse !important;
				align-items: stretch !important;
				gap: 10px !important;
			}
			.exp-actions-bar .btn-exp-action {
				width: 100% !important;
				text-align: center !important;
				box-sizing: border-box !important;
				padding: 10px 14px !important;
			}
			.exp-actions-bar .exp-action-group {
				width: 100% !important;
				display: flex !important;
				flex-direction: column !important;
				gap: 8px !important;
			}
			.exp-actions-bar .exp-action-group form {
				width: 100% !important;
			}
			.exp-deploy-bottom {
				flex-direction: column !important;
				align-items: stretch !important;
				gap: 10px !important;
			}
			.exp-deploy-bottom .btn-exp-action {
				width: 100% !important;
				text-align: center !important;
				padding: 10px !important;
				box-sizing: border-box !important;
			}
			.exp-hero-label {
				padding: 8px 10px !important;
				background: #f8fafc !important;
				border: 1px solid #e2e8f0 !important;
				border-radius: 6px !important;
			}
		}

		/* BASE COMPONENT STYLING */
		.exp-header {
			background: linear-gradient(135deg, #1e1b4b, #312e81);
			color: #ffffff;
			border-radius: 8px;
			padding: 14px 18px;
			margin-bottom: 12px;
			box-shadow: 0 4px 12px rgba(49, 46, 129, 0.25);
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.exp-title {
			font-size: 17px;
			font-weight: bold;
			color: #e0e7ff;
			line-height: 1.25;
		}
		.exp-subtitle {
			font-size: 11px;
			color: #c7d2fe;
			margin-top: 3px;
			line-height: 1.35;
		}
		.exp-pill {
			background: rgba(255, 255, 255, 0.15);
			border: 1px solid rgba(255, 255, 255, 0.3);
			padding: 4px 10px;
			border-radius: 12px;
			font-size: 11px;
			font-weight: bold;
			color: #fef08a;
			white-space: nowrap;
		}
		.exp-card {
			background: #ffffff;
			border: 1px solid #d1d5db;
			border-radius: 8px;
			padding: 14px;
			margin-bottom: 14px;
			box-shadow: 0 1px 4px rgba(0,0,0,0.06);
		}

		/* PARTY 5 SLOTS GRID - ALWAYS 5 COLUMNS */
		.slot-grid {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 6px;
			margin: 10px 0;
		}
		.slot-box {
			background: #f8fafc;
			border: 1.5px dashed #cbd5e1;
			border-radius: 6px;
			padding: 8px 4px;
			text-align: center;
			font-size: 11px;
			min-width: 0;
			box-sizing: border-box;
			display: flex;
			flex-direction: column;
			justify-content: center;
			min-height: 64px;
		}
		.slot-box.filled {
			background: #f0fdf4;
			border: 1.5px solid #86efac;
		}
		.slot-box.leader {
			border: 1.5px solid #f59e0b;
			background: #fffbeb;
		}
		.slot-role {
			font-size: 9px;
			font-weight: bold;
			text-transform: uppercase;
			color: #64748b;
			margin-bottom: 3px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.slot-box.leader .slot-role {
			color: #b45309;
		}
		.slot-box.filled:not(.leader) .slot-role {
			color: #15803d;
		}
		.slot-username {
			font-weight: bold;
			color: #1e293b;
			font-size: 11px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.slot-power {
			font-size: 9.5px;
			color: #059669;
			font-weight: bold;
			margin-top: 2px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.slot-hero-badge {
			font-size: 8.5px;
			color: #d97706;
			font-weight: bold;
			margin-top: 1px;
			white-space: nowrap;
		}

		/* Compact styling for very small mobile screens (<= 480px) */
		@media screen and (max-width: 480px) {
			.slot-grid {
				gap: 3px !important;
			}
			.slot-box {
				padding: 6px 2px !important;
				min-height: 58px !important;
			}
			.slot-role {
				font-size: 8px !important;
			}
			.slot-username {
				font-size: 10px !important;
			}
			.slot-power {
				font-size: 9px !important;
			}
			.slot-hero-badge {
				font-size: 8px !important;
			}
		}

		/* SYNERGY BOX & BADGES */
		.synergy-box {
			background: #f5f3ff;
			border: 1px solid #ddd6fe;
			border-radius: 6px;
			padding: 10px 12px;
			margin-bottom: 12px;
		}
		.synergy-badge {
			display: inline-flex;
			align-items: center;
			background: #9333ea;
			color: #ffffff;
			font-size: 10px;
			font-weight: bold;
			padding: 3px 8px;
			border-radius: 5px;
			margin: 2px 3px 2px 0;
			box-shadow: 0 1px 2px rgba(147, 51, 234, 0.2);
			line-height: 1.3;
		}
		.synergy-badge.coalition {
			background: #d97706;
			box-shadow: 0 1px 2px rgba(217, 119, 6, 0.2);
		}

		/* ACTION BUTTONS */
		.btn-exp-action {
			display: inline-block;
			padding: 6px 14px;
			border-radius: 5px;
			font-weight: bold;
			font-size: 12px;
			text-decoration: none !important;
			cursor: pointer;
			border: none;
			transition: all 0.15s ease;
			box-sizing: border-box;
		}
		.btn-exp-create {
			background: #4f46e5;
			color: #ffffff !important;
			box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
		}
		.btn-exp-create:hover {
			background: #4338ca;
		}
		.btn-exp-join {
			background: #16a34a;
			color: #ffffff !important;
			box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35);
		}
		.btn-exp-join:hover {
			background: #15803d;
		}
		.btn-exp-cancel {
			background: #ef4444;
			color: #ffffff !important;
		}
		.btn-exp-cancel:hover {
			background: #dc2626;
		}
		.btn-exp-launch {
			background: #d97706;
			color: #ffffff !important;
			box-shadow: 0 2px 8px rgba(217, 119, 6, 0.4);
		}
		.btn-exp-launch:hover {
			background: #b45309;
		}

		/* TROOP TABLE WRAPPER */
		.troop-table-wrapper {
			overflow-x: auto;
			width: 100%;
			-webkit-overflow-scrolling: touch;
			margin: 8px 0 10px 0;
			border: 1px solid #e2e8f0;
			border-radius: 6px;
			background: #ffffff;
		}
		.troop-input-table {
			width: 100%;
			min-width: 440px;
			border-collapse: collapse;
			margin: 0;
			font-size: 11px;
			table-layout: fixed;
		}
		.troop-input-table th, .troop-input-table td {
			width: 10%;
			padding: 5px 1px;
			border-bottom: 1px solid #e2e8f0;
			text-align: center;
			vertical-align: middle;
		}
		.troop-input-table input[type="text"] {
			width: 36px;
			max-width: 90%;
			padding: 3px 1px;
			text-align: center;
			border: 1px solid #cbd5e1;
			border-radius: 4px;
			font-size: 11px;
			box-sizing: border-box;
		}
		.troop-input-table input[type="text"]:focus {
			border-color: #6366f1;
			outline: none;
			box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
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
	<div id="content" class="statistics">

		<!-- HERO HEADER -->
		<div class="exp-header">
			<div>
				<div class="exp-title">🛡️⚔️ Alliance Expedition (Forward Camp)</div>
				<div class="exp-subtitle">Serangan Terkoordinasi Aliansi Melawan Benteng Bandit &amp; World Boss</div>
			</div>
			<div>
				<span class="exp-pill">Kuota Harian: <?php echo $dailyRemaining; ?>/<?php echo ExpeditionEngine::DAILY_LIMIT; ?></span>
			</div>
		</div>

		<?php if (!empty($msgError)) { ?>
			<div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; color: #991b1b; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 12px; font-weight: bold;">
				⚠️ <?php echo htmlspecialchars($msgError); ?>
			</div>
		<?php } ?>

		<?php if (!empty($msgSuccess)) { ?>
			<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #22c55e; color: #166534; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 12px; font-weight: bold;">
				✅ <?php echo htmlspecialchars($msgSuccess); ?>
			</div>
		<?php } ?>

		<!-- JIKA USER BELUM MASUK ALIANSI -->
		<?php if ($userAlliance <= 0) { ?>
			<div class="exp-card" style="text-align: center; padding: 25px 15px;">
				<h3 style="color: #4338ca; margin-top: 0;">🏛️ Bergabunglah dengan Aliansi</h3>
				<p style="color: #64748b; font-size: 13px; max-width: 380px; margin: 0 auto 15px;">
					Fitur <b>Alliance Expedition</b> membutuhkan koordinasi taktis antar anggota aliansi. Silakan bergabung atau buat aliansi melalui Kedutaan (Embassy) untuk membuka Forward Camp.
				</p>
				<a href="bandit.php" class="btn-exp-action btn-exp-create">&laquo; Kembali ke Radar Bandit</a>
			</div>

		<!-- JIKA SEDANG MELIHAT SESI EKSPEDISI TERTENTU (LOBBY AKTIF) -->
		<?php } elseif (!empty($expDetails)) { 
			$exp = $expDetails['expedition'];
			$members = $expDetails['members'];
			$synergies = $expDetails['synergies'];
			$isLeader = ($uid === (int)$exp['leader_uid']);
			$hasJoined = false;
			foreach ($members as $m) {
				if ((int)$m['uid'] === $uid) {
					$hasJoined = true;
					break;
				}
			}
			$timeLeft = max(0, (int)$exp['impact_time'] - time());
		?>
			<div class="exp-card">
				<!-- Top Bar: Target & Status -->
				<div class="exp-camp-target-bar" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px;">
					<div class="exp-camp-title-wrap">
						<span style="font-size: 14px; font-weight: bold; color: #1e1b4b;">🎯 <?php echo htmlspecialchars($exp['camp_name']); ?></span>
						<span style="color: #64748b; font-size: 11px; margin-left: 2px;">(<?php echo $campData['x']; ?>|<?php echo $campData['y']; ?>)</span>
					</div>
					<div class="exp-camp-timer-wrap">
						<?php
						$timerNum = ++$session->timer;
						$fmtTime = sprintf('%02d:%02d:%02d', intdiv($timeLeft, 3600), intdiv($timeLeft % 3600, 60), $timeLeft % 60);
						?>
						<span class="exp-timer-pill" style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 12px; border: 1px solid #c7d2fe; display: inline-flex; align-items: center; gap: 4px;">
							⏱️ Benturan: <span id="timer<?php echo $timerNum; ?>" class="exp-live-timer" data-seconds="<?php echo $timeLeft; ?>"><?php echo $fmtTime; ?></span>
						</span>
					</div>
				</div>

				<!-- Party Slots (Grid 5) -->
				<div style="font-weight: bold; color: #334155; font-size: 11px; margin-bottom: 4px;">
					👥 Peserta Forward Camp (<?php echo count($members); ?>/5):
				</div>
				<div class="slot-grid">
					<?php for ($slot = 1; $slot <= 5; $slot++) { 
						$m = $members[$slot - 1] ?? null;
					?>
						<?php if ($m) { 
							$isSlotLeader = ((int)$m['uid'] === (int)$exp['leader_uid']);
						?>
							<div class="slot-box filled <?php echo $isSlotLeader ? 'leader' : ''; ?>">
								<div class="slot-role"><?php echo $isSlotLeader ? '👑 Leader' : '⚔️ Anggota'; ?></div>
								<div class="slot-username" title="<?php echo htmlspecialchars($m['username'] ?? 'Pemain'); ?>"><?php echo htmlspecialchars($m['username'] ?? 'Pemain'); ?></div>
								<div class="slot-power">⚡ <?php echo number_format((int)$m['attack_power']); ?></div>
								<?php if (!empty($m['has_hero'])) { ?>
									<div class="slot-hero-badge">⭐ Hero</div>
								<?php } ?>
							</div>
						<?php } else { ?>
							<div class="slot-box">
								<div class="slot-role" style="color: #94a3b8;">Slot #<?php echo $slot; ?></div>
								<div class="slot-username" style="color: #cbd5e1; font-weight: normal;">Terbuka</div>
								<div class="slot-power" style="color: #94a3b8; font-weight: normal;">Menunggu</div>
							</div>
						<?php } ?>
					<?php } ?>
				</div>

				<!-- Party Readiness & Launch Status Banner -->
				<?php if (count($members) < 2) { ?>
					<div style="margin: 10px 0 6px 0; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 9px 12px; font-size: 11px; color: #92400e; display: flex; align-items: center; gap: 8px;">
						<span style="font-size: 16px;">⏳</span>
						<div>
							<b>Menunggu Anggota Aliansi (1/5):</b> Ekspedisi butuh minimal <b>2 pemain</b> untuk bertempur. Bagikan target ini ke chat aliansi agar anggota lain bergabung! Serangan otomatis meluncur jika minimal 2 orang sudah siap saat timer benturan habis.
						</div>
					</div>
				<?php } elseif (count($members) < 5) { ?>
					<div style="margin: 10px 0 6px 0; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 9px 12px; font-size: 11px; color: #166534; display: flex; align-items: center; gap: 8px;">
						<span style="font-size: 16px;">⚔️</span>
						<div>
							<b>Siap Tempur (<?php echo count($members); ?>/5):</b> Syarat minimal 2 pemain <b>sudah terpenuhi</b>! Tidak wajib menunggu 5 orang.
							<?php if ($isLeader) { ?>
								Anda dapat menunggu anggota lain bergabung hingga timer habis, atau klik tombol <b>⚡ Luncurkan Sekarang</b> di bawah untuk serbu instan!
							<?php } else { ?>
								Menunggu Leader meluncurkan serangan, atau otomatis berangkat saat timer benturan di atas habis.
							<?php } ?>
						</div>
					</div>
				<?php } else { ?>
					<div style="margin: 10px 0 6px 0; background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 6px; padding: 9px 12px; font-size: 11px; color: #831843; display: flex; align-items: center; gap: 8px;">
						<span style="font-size: 16px;">🔥</span>
						<div>
							<b>Forward Camp Penuh (5/5)!</b> Seluruh slot pasukan aliansi telah terisi lengkap.
							<?php if ($isLeader) { ?>
								Silakan klik tombol <b>⚡ Luncurkan Sekarang</b> di bawah untuk melancarkan serangan gabungan sekarang!
							<?php } else { ?>
								Menunggu Leader meluncurkan serangan atau otomatis berangkat saat timer habis.
							<?php } ?>
						</div>
					</div>
				<?php } ?>

				<!-- Active Tribe Synergies -->
				<div class="synergy-box">
					<div style="font-weight: bold; font-size: 11px; color: #6b21a8; margin-bottom: 4px;">
						✨ Sinergi Suku Aktif di Forward Camp:
					</div>
					<?php if (empty($synergies['synergies'])) { ?>
						<span style="color: #94a3b8; font-size: 11px;">Belum ada suku terdaftar.</span>
					<?php } else { ?>
						<?php foreach ($synergies['synergies'] as $syn) { ?>
							<span class="synergy-badge" title="<?php echo htmlspecialchars($syn['desc']); ?>">
								<?php echo htmlspecialchars($syn['badge']); ?>
							</span>
						<?php } ?>
						<?php if (!empty($synergies['coalition_bonus'])) { ?>
							<span class="synergy-badge coalition" title="Bonus Suku Beragam">
								<?php echo htmlspecialchars($synergies['coalition_bonus']['badge']); ?>
							</span>
						<?php } ?>
					<?php } ?>
				</div>

				<!-- Action Controls -->
				<div class="exp-actions-bar" style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
					<div>
						<a href="bandit.php" style="color: #64748b; font-size: 11px; text-decoration: underline; display: inline-block; padding: 4px 0;">&laquo; Kembali ke Radar</a>
					</div>
					<div class="exp-action-group" style="display: flex; gap: 6px; align-items: center;">
						<?php if ($isLeader) { ?>
							<form method="post" action="expedition.php" style="display: inline; margin: 0;">
								<input type="hidden" name="action" value="cancel" />
								<input type="hidden" name="expedition_id" value="<?php echo $exp['id']; ?>" />
								<button type="submit" class="btn-exp-action btn-exp-cancel" onclick="return confirm('Apakah Anda yakin ingin membatalkan ekspedisi ini? Seluruh pasukan akan dipulangkan.');">
									Batalkan
								</button>
							</form>
							<?php if (count($members) >= 2) { ?>
								<form method="post" action="expedition.php" style="display: inline; margin: 0;">
									<input type="hidden" name="action" value="launch_now" />
									<input type="hidden" name="expedition_id" value="<?php echo $exp['id']; ?>" />
									<button type="submit" class="btn-exp-action btn-exp-launch" onclick="return confirm('Luncurkan serangan Forward Camp sekarang bersama seluruh anggota yang sudah siap?');">
										⚡ Luncurkan Sekarang (<?php echo count($members); ?>/5)
									</button>
								</form>
							<?php } ?>
						<?php } else { ?>
							<?php if (count($members) >= 2) { ?>
								<div style="color: #166534; font-size: 11px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 5px; font-weight: bold; text-align: center;">
									⚔️ Siap Tempur &bull; Menunggu Leader meluncurkan serangan
								</div>
							<?php } else { ?>
								<div style="color: #64748b; font-size: 11px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 5px; text-align: center;">
									⏳ Menunggu anggota lain bergabung (Min. 2 pemain)
								</div>
							<?php } ?>
						<?php } ?>
					</div>
				</div>
			</div>

			<!-- Form Deploy Pasukan Jika Anggota Belum Bergabung -->
			<?php if (!$hasJoined && count($members) < 5 && $dailyRemaining > 0) { 
				$reqPower = max(50, (int)round((int)$campData['cur_hp'] * ExpeditionEngine::MIN_POWER_RATIO));
			?>
			<div class="exp-card">
				<h4 style="color: #1e1b4b; margin: 0 0 8px 0;">⚔️ Kerahkan Pasukan ke Forward Camp</h4>
				<p style="color: #64748b; font-size: 11px; margin: 0 0 10px 0;">
					Pilih pasukan dari desa Anda (<b><?php echo htmlspecialchars($village->vname); ?></b>) untuk bergabung ke battlegroup. Minimal kekuatan yang dibutuhkan: <b><?php echo number_format($reqPower); ?></b> Attack Power.
				</p>

				<form method="post" action="expedition.php">
					<input type="hidden" name="action" value="join" />
					<input type="hidden" name="expedition_id" value="<?php echo $exp['id']; ?>" />

					<div style="display: flex; justify-content: space-between; align-items: center; margin: 8px 0 4px 0;">
						<div style="font-weight: bold; font-size: 11px; color: #334155;">
							Pilih Pasukan (Klik angka/ikon untuk isi maksimal):
						</div>
						<div style="font-size: 11px;">
							<a href="javascript:void(0);" onclick="fillAllMax(this);" style="color: #4f46e5; font-weight: bold; text-decoration: underline; margin-right: 8px;">⚡ Isi Semua Maksimal</a>
							<a href="javascript:void(0);" onclick="clearAllTroops(this);" style="color: #64748b; text-decoration: underline;">Reset (0)</a>
						</div>
					</div>

					<div class="troop-table-wrapper">
						<table class="troop-input-table">
							<thead>
								<tr style="background: #f8fafc;">
									<?php for ($i = 1; $i <= 10; $i++) { 
										$uId = ($userTribe - 1) * 10 + $i;
										$uName = defined('U' . $uId) ? constant('U' . $uId) : "U$i";
										$avail = (int)($villageUnits['u' . $uId] ?? 0);
									?>
										<th>
											<img class="unit u<?php echo $uId; ?>" src="img/x.gif" title="Klik untuk isi maksimal <?php echo htmlspecialchars($uName); ?>" style="cursor: pointer;" onclick="fillTroop(this, 'u<?php echo $i; ?>', <?php echo $avail; ?>);" /><br>
											<a href="javascript:void(0);" onclick="fillTroop(this, 'u<?php echo $i; ?>', <?php echo $avail; ?>);" style="display: inline-block; font-size: 9.5px; color: #2563eb; font-weight: bold; text-decoration: underline; margin-top: 1px;" title="Klik untuk mengisi maksimal">(<?php echo $avail; ?>)</a>
										</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<?php for ($i = 1; $i <= 10; $i++) { ?>
										<td><input type="text" name="u<?php echo $i; ?>" value="0" /></td>
									<?php } ?>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="exp-deploy-bottom" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
						<?php if ($heroInVillage) { ?>
							<label class="exp-hero-label" style="font-size: 11px; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 4px; cursor: pointer;">
								<input type="checkbox" name="has_hero" value="1" checked="checked" />
								⭐ Sertakan Hero Pemimpin <span style="color: #16a34a; font-size: 10px; font-weight: normal;">(Tersedia di desa ini)</span>
							</label>
						<?php } else { ?>
							<label class="exp-hero-label" style="font-size: 11px; font-weight: bold; color: #94a3b8; display: flex; align-items: center; gap: 4px; cursor: not-allowed;" title="Hero sedang mati atau berada di desa lain">
								<input type="checkbox" name="has_hero" value="1" disabled="disabled" />
								⭐ Sertakan Hero Pemimpin <span style="color: #ef4444; font-size: 10px; font-weight: normal;">(Tidak ada di desa ini)</span>
							</label>
						<?php } ?>
						<button type="submit" class="btn-exp-action btn-exp-join">
							Kirim ke Forward Camp &raquo;
						</button>
					</div>
				</form>
			</div>
			<?php } ?>

		<!-- JIKA BELUM ADA LOBBY DAN MEMBUKA LOBBY BARU DARI CAMP -->
		<?php } elseif (!empty($campData)) { 
			$reqPower = max(50, (int)round((int)$campData['cur_hp'] * ExpeditionEngine::MIN_POWER_RATIO));
		?>
			<div class="exp-card">
				<div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;">
					<span style="font-size: 15px; font-weight: bold; color: #1e1b4b;">🏰 Buka Forward Camp: <?php echo htmlspecialchars($campData['name']); ?></span>
					<span style="color: #64748b; font-size: 11px;">(<?php echo $campData['x']; ?>|<?php echo $campData['y']; ?>)</span>
				</div>

				<p style="color: #475569; font-size: 12px; line-height: 1.5; margin: 0 0 12px 0;">
					Sebagai <b>Rally Leader</b>, Anda akan mencanangkan titik kumpul <i>Forward Camp</i> di luar sarang target. Anggota aliansi Anda dapat menyetor pasukan ke battlegroup gabungan. Pertempuran serentak akan otomatis diluncurkan saat jam persiapan habis!
				</p>

				<form method="post" action="expedition.php">
					<input type="hidden" name="action" value="create" />
					<input type="hidden" name="camp_id" value="<?php echo $campData['id']; ?>" />

					<div style="margin-bottom: 12px; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
						<label style="font-size: 11px; font-weight: bold; color: #334155;">⏱️ Durasi Persiapan Lobi (Menit):</label>
						<select name="prep_minutes" style="margin-left: 8px; padding: 3px 6px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 11px; background: #ffffff;">
							<option value="15">15 Menit (Cepat)</option>
							<option value="20" selected="selected">20 Menit (Standar)</option>
							<option value="30">30 Menit (Menengah)</option>
							<option value="60">60 Menit / 1 Jam (Jarak Jauh)</option>
							<option value="120">120 Menit / 2 Jam (Lintas Peta)</option>
						</select>
					</div>

					<div style="display: flex; justify-content: space-between; align-items: center; margin: 8px 0 4px 0;">
						<div style="font-weight: bold; font-size: 11px; color: #334155;">
							Pilih Pasukan Awal Pemimpin (Min Power: <b><?php echo number_format($reqPower); ?></b>):
						</div>
						<div style="font-size: 11px;">
							<a href="javascript:void(0);" onclick="fillAllMax(this);" style="color: #4f46e5; font-weight: bold; text-decoration: underline; margin-right: 8px;">⚡ Isi Semua Maksimal</a>
							<a href="javascript:void(0);" onclick="clearAllTroops(this);" style="color: #64748b; text-decoration: underline;">Reset (0)</a>
						</div>
					</div>

					<div class="troop-table-wrapper">
						<table class="troop-input-table">
							<thead>
								<tr style="background: #f8fafc;">
									<?php for ($i = 1; $i <= 10; $i++) { 
										$uId = ($userTribe - 1) * 10 + $i;
										$uName = defined('U' . $uId) ? constant('U' . $uId) : "U$i";
										$avail = (int)($villageUnits['u' . $uId] ?? 0);
									?>
										<th>
											<img class="unit u<?php echo $uId; ?>" src="img/x.gif" title="Klik untuk isi maksimal <?php echo htmlspecialchars($uName); ?>" style="cursor: pointer;" onclick="fillTroop(this, 'u<?php echo $i; ?>', <?php echo $avail; ?>);" /><br>
											<a href="javascript:void(0);" onclick="fillTroop(this, 'u<?php echo $i; ?>', <?php echo $avail; ?>);" style="display: inline-block; font-size: 9.5px; color: #2563eb; font-weight: bold; text-decoration: underline; margin-top: 1px;" title="Klik untuk mengisi maksimal">(<?php echo $avail; ?>)</a>
										</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<?php for ($i = 1; $i <= 10; $i++) { ?>
										<td><input type="text" name="u<?php echo $i; ?>" value="0" /></td>
									<?php } ?>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="exp-deploy-bottom" style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px;">
						<?php if ($heroInVillage) { ?>
							<label class="exp-hero-label" style="font-size: 11px; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 4px; cursor: pointer;">
								<input type="checkbox" name="has_hero" value="1" checked="checked" />
								⭐ Sertakan Hero Pemimpin <span style="color: #16a34a; font-size: 10px; font-weight: normal;">(Tersedia di desa ini)</span>
							</label>
						<?php } else { ?>
							<label class="exp-hero-label" style="font-size: 11px; font-weight: bold; color: #94a3b8; display: flex; align-items: center; gap: 4px; cursor: not-allowed;" title="Hero sedang mati atau berada di desa lain">
								<input type="checkbox" name="has_hero" value="1" disabled="disabled" />
								⭐ Sertakan Hero Pemimpin <span style="color: #ef4444; font-size: 10px; font-weight: normal;">(Tidak ada di desa ini)</span>
							</label>
						<?php } ?>
						<button type="submit" class="btn-exp-action btn-exp-create">
							Buka Forward Camp &raquo;
						</button>
					</div>
				</form>
			</div>

		<!-- JIKA TIDAK ADA CAMP TERTENTU, TAMPILKAN DAFTAR EKSPEDISI ALIANSI -->
		<?php } else { ?>
			<div class="exp-card">
				<h3 style="color: #1e1b4b; margin: 0 0 10px 0;">🛡️ Ekspedisi Aliansi yang Sedang Berjalan</h3>
				<?php if (empty($allianceExpeditions)) { ?>
					<p style="color: #64748b; font-size: 12px; margin: 15px 0; text-align: center;">
						Saat ini belum ada Forward Camp aktif di aliansi Anda.<br>
						Buka radar bandit untuk mencanangkan ekspedisi baru melawan Benteng Bandit atau World Boss!
					</p>
					<div style="text-align: center; margin-top: 15px;">
						<a href="bandit.php" class="btn-exp-action btn-exp-create">🗺️ Buka Radar Bandit &raquo;</a>
					</div>
				<?php } else { ?>
					<div style="display: flex; flex-direction: column; gap: 8px;">
						<?php foreach ($allianceExpeditions as $ae) { 
							$aExp = $ae['expedition'];
							$aTimeLeft = max(0, (int)$aExp['impact_time'] - time());
						?>
							<?php 
								$aTimerNum = ++$session->timer;
								$aFmtTime = sprintf('%02d:%02d:%02d', intdiv($aTimeLeft, 3600), intdiv($aTimeLeft % 3600, 60), $aTimeLeft % 60);
							?>
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center;">
								<div>
									<div style="font-weight: bold; color: #1e1b4b; font-size: 12px;">
										🎯 <?php echo htmlspecialchars($aExp['camp_name']); ?>
									</div>
									<div style="font-size: 10px; color: #64748b; margin-top: 2px;">
										Peserta: <?php echo $ae['member_count']; ?>/5 &bull; ⏱️ Benturan dalam <span id="timer<?php echo $aTimerNum; ?>" class="exp-live-timer" data-seconds="<?php echo $aTimeLeft; ?>"><?php echo $aFmtTime; ?></span>
									</div>
								</div>
								<div>
									<a href="expedition.php?id=<?php echo $aExp['id']; ?>" class="btn-exp-action btn-exp-join">
										Buka Lobi &raquo;
									</a>
								</div>
							</div>
						<?php } ?>
					</div>
				<?php } ?>
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
<?php echo defined('CALCULATED_IN') ? CALCULATED_IN : 'Calculated in';?> <b><?php
echo round(($generator->pageLoadTimeEnd()-$start_timer)*1000);
?></b> ms
<br /><?php echo defined('SERVER_TIME') ? SERVER_TIME : 'Server time:';?> <span id="tp1" class="b"><?php echo date('H:i:s'); ?></span>
</div>
</div>
</div>

<div id="ce"></div>
</body>
</html>
