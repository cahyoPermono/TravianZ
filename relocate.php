<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : relocate.php                                              ##
##  Type           : Beginner Village Relocation Hub                           ##
##  Purpose        : One-time village relocation for players under protection   ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Antigravity Studio for TravianZ Extended Edition          ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

use App\Utils\AccessLogger;

include_once("GameEngine/Village.php");
AccessLogger::logRequest();

require_once("GameEngine/VillageRelocate.php");

$start_timer = $generator->pageLoadTimeStart();

if (!$session->logged_in) {
    header("Location: login.php");
    exit;
}

$uid = (int)$session->uid;
$wid = (int)$village->wid;
$fromX = (int)($village->coor['x'] ?? 0);
$fromY = (int)($village->coor['y'] ?? 0);

// Check eligibility
$eligibility = VillageRelocate::canRelocate($uid, $wid);

$successMessage = '';
$errorMessage = '';

// Handle Relocation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'relocate') {
    $targetX = isset($_POST['x']) ? (int)$_POST['x'] : 0;
    $targetY = isset($_POST['y']) ? (int)$_POST['y'] : 0;

    $relocateResult = VillageRelocate::relocateVillage($uid, $wid, $targetX, $targetY);
    if ($relocateResult['success']) {
        $_SESSION['relocate_success_flash'] = $relocateResult['message'];
        header("Location: dorf1.php?relocated=1");
        exit;
    } else {
        $errorMessage = $relocateResult['message'];
    }
}

// Target coordinate prefill & preview
$targetX = null;
$targetY = null;
$targetPreview = null;

if (isset($_GET['target'])) {
    $targetWid = (int)$_GET['target'];
    $targetCheck = VillageRelocate::checkTargetByWid($targetWid, $wid);
    if ($targetCheck['valid'] && !empty($targetCheck['tile'])) {
        $targetX = (int)$targetCheck['tile']['x'];
        $targetY = (int)$targetCheck['tile']['y'];
        $targetPreview = $targetCheck;
    } else {
        $errorMessage = $targetCheck['error'] ?? 'Lembah tujuan tidak valid.';
    }
} elseif (isset($_REQUEST['x']) && isset($_REQUEST['y'])) {
    $targetX = (int)$_REQUEST['x'];
    $targetY = (int)$_REQUEST['y'];
    $targetPreview = VillageRelocate::checkTargetCoordinates($targetX, $targetY, $wid);
    if (!$targetPreview['valid']) {
        $errorMessage = $targetPreview['error'];
    }
}

// Distance calculation for preview
$targetDistance = null;
if ($targetPreview && $targetPreview['valid'] && !empty($targetPreview['tile'])) {
    $targetDistance = round($database->getDistance($fromX, $fromY, $targetPreview['tile']['x'], $targetPreview['tile']['y']), 1);
}

// Get relocation log if already relocated
$relocationLog = null;
if ($eligibility['hasUsed']) {
    global $database;
    $link = $database->return_link();
    $lres = mysqli_query($link, "SELECT * FROM " . TB_PREFIX . "village_relocation_log WHERE uid = $uid ORDER BY id DESC LIMIT 1");
    if ($lres && mysqli_num_rows($lres) > 0) {
        $relocationLog = mysqli_fetch_assoc($lres);
    }
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo SERVER_NAME; ?> &raquo; Relokasi Desa Pemula (One-Time Move)</title>
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
	<link href="<?php echo GP_LOCATE; ?>lang/en/compact.css?v=20261008d" rel="stylesheet" type="text/css" />
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

		div#content.relocate_hub {
			width: 502px !important;
			padding: 30px 25px 50px !important;
			float: left !important;
			box-sizing: content-box !important;
			min-height: 480px !important;
			position: relative !important;
		}

		/* HERO HEADER */
		.reloc-hero {
			background: linear-gradient(135deg, #2b4822 0%, #3e6b2c 100%);
			border-radius: 8px;
			padding: 16px 20px;
			color: #ffffff;
			margin-bottom: 18px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.12);
			border: 1px solid #4d8537;
			display: flex;
			align-items: center;
			gap: 14px;
		}
		.reloc-hero-icon {
			font-size: 38px;
			line-height: 1;
			filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
		}
		.reloc-hero-title {
			font-size: 19px;
			font-weight: bold;
			color: #fff;
			margin: 0 0 4px;
			text-shadow: 0 1px 2px rgba(0,0,0,0.4);
		}
		.reloc-hero-subtitle {
			font-size: 11px;
			color: #dbeecd;
			margin: 0;
			line-height: 1.4;
		}

		/* STATUS CARDS GRID */
		.reloc-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px;
			margin-bottom: 16px;
		}
		.reloc-status-box {
			background: #faf7ef;
			border: 1px solid #dcd1ba;
			border-radius: 6px;
			padding: 10px 12px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.04);
		}
		.reloc-status-label {
			font-size: 10px;
			text-transform: uppercase;
			font-weight: bold;
			color: #7d6b4f;
			margin-bottom: 4px;
			display: flex;
			align-items: center;
			gap: 4px;
		}
		.reloc-status-val {
			font-size: 13px;
			font-weight: bold;
			color: #2c2518;
		}
		.reloc-status-sub {
			font-size: 10px;
			color: #8c7d67;
			margin-top: 2px;
		}

		/* ALERT BOXES */
		.reloc-alert {
			border-radius: 6px;
			padding: 12px 14px;
			margin-bottom: 16px;
			font-size: 12px;
			line-height: 1.5;
		}
		.alert-success {
			background: #e8f8f0;
			border: 1px solid #a3e4c4;
			color: #155724;
		}
		.alert-error {
			background: #fdf2f2;
			border: 1px solid #f8b4b4;
			color: #9b1c1c;
		}
		.alert-warning {
			background: #fef9e7;
			border: 1px solid #f9e79f;
			color: #7d6608;
		}
		.alert-info {
			background: #eef6fc;
			border: 1px solid #bcdbf3;
			color: #1a5276;
		}

		/* RULES ACCORDION / BOX */
		.reloc-card {
			background: #ffffff;
			border: 1px solid #d4c8ad;
			border-radius: 6px;
			margin-bottom: 16px;
			overflow: hidden;
			box-shadow: 0 2px 5px rgba(0,0,0,0.03);
		}
		.reloc-card-header {
			background: #f1ebd8;
			padding: 9px 14px;
			font-weight: bold;
			font-size: 12px;
			color: #3b3020;
			border-bottom: 1px solid #dfd3b9;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.reloc-card-body {
			padding: 14px;
			font-size: 11px;
			color: #3c3425;
			line-height: 1.6;
		}

		/* TARGET PREVIEW CARD */
		.target-preview-box {
			background: #f9fbf7;
			border: 1.5px solid #a9dfbf;
			border-radius: 6px;
			padding: 12px 14px;
			margin-bottom: 15px;
		}
		.target-preview-title {
			font-size: 13px;
			font-weight: bold;
			color: #196f3d;
			margin-bottom: 6px;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.target-spec-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 6px;
			font-size: 11px;
		}
		.target-spec-table td {
			padding: 4px 6px;
			border-bottom: 1px solid #e5ece0;
		}
		.target-spec-table td.label {
			color: #5d6d7e;
			width: 35%;
		}
		.target-spec-table td.value {
			font-weight: bold;
			color: #212f3d;
		}

		/* FORM ELEMENTS */
		.coord-input-row {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 12px 0 16px;
		}
		.coord-input {
			width: 55px;
			padding: 6px 8px;
			border: 1px solid #b5a485;
			border-radius: 4px;
			text-align: center;
			font-weight: bold;
			font-size: 12px;
			background: #fff;
		}
		.coord-input:focus {
			border-color: #3e6b2c;
			outline: none;
			box-shadow: 0 0 4px rgba(62,107,44,0.3);
		}
		.btn-check {
			padding: 6px 14px;
			background: #e8dfcd;
			border: 1px solid #bdae91;
			border-radius: 4px;
			font-size: 11px;
			font-weight: bold;
			cursor: pointer;
			color: #332b1f;
		}
		.btn-check:hover {
			background: #ded2bd;
		}

		/* BIG RELOCATE BUTTON */
		.btn-relocate-primary {
			display: block;
			width: 100%;
			padding: 12px 16px;
			background: linear-gradient(180deg, #5b9933 0%, #3e6e1f 100%);
			border: 1px solid #2f5416;
			border-radius: 6px;
			color: #ffffff !important;
			font-size: 14px;
			font-weight: bold;
			text-align: center;
			text-shadow: 0 1px 2px rgba(0,0,0,0.4);
			cursor: pointer;
			box-shadow: 0 3px 6px rgba(0,0,0,0.15);
			margin-top: 14px;
			transition: all 0.15s ease-in-out;
		}
		.btn-relocate-primary:hover {
			background: linear-gradient(180deg, #68ae3b 0%, #467c23 100%);
			box-shadow: 0 4px 8px rgba(0,0,0,0.2);
			transform: translateY(-1px);
		}
		.btn-relocate-primary:disabled {
			background: #c5beae !important;
			border-color: #b0a794 !important;
			color: #756e5f !important;
			cursor: not-allowed;
			transform: none;
			box-shadow: none;
		}

		/* CHECKLIST ICONS */
		.check-list {
			list-style: none;
			padding: 0;
			margin: 8px 0;
		}
		.check-list li {
			padding: 3px 0 3px 22px;
			position: relative;
			font-size: 11px;
		}
		.check-list li.ok::before {
			content: '✔';
			position: absolute;
			left: 2px;
			color: #27ae60;
			font-weight: bold;
		}
		.check-list li.fail::before {
			content: '✖';
			position: absolute;
			left: 2px;
			color: #c0392b;
			font-weight: bold;
		}

		/* CERTIFICATE EMBLEM FOR COMPLETED MOVE */
		.reloc-cert {
			background: #fffcf4;
			border: 2px dashed #caa965;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
			margin-top: 10px;
		}
		.cert-seal {
			font-size: 40px;
			margin-bottom: 8px;
		}
		.cert-title {
			font-size: 15px;
			font-weight: bold;
			color: #6d4c1b;
			margin-bottom: 6px;
		}
		.cert-desc {
			font-size: 11px;
			color: #665239;
			line-height: 1.6;
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
	<div id="content" class="relocate_hub">

		<!-- HERO BANNER -->
		<div class="reloc-hero">
			<div class="reloc-hero-icon">📍</div>
			<div>
				<div class="reloc-hero-title">Relokasi Desa Pemula</div>
				<div class="reloc-hero-subtitle">
					Pindahkan desa Anda ke lembah kosong pilihan Anda secara instan. Fitur ini merupakan hak istimewa khusus pemain baru yang hanya dapat digunakan <b>satu kali</b> selama masa perlindungan aktif.
				</div>
			</div>
		</div>

		<!-- NOTIFICATIONS -->
		<?php if (!empty($errorMessage)): ?>
			<div class="reloc-alert alert-error">
				<b>⚠️ Perhatian:</b> <?php echo htmlspecialchars($errorMessage); ?>
			</div>
		<?php endif; ?>

		<!-- STATUS METRICS -->
		<div class="reloc-grid">
			<div class="reloc-status-box">
				<div class="reloc-status-label">
					🛡️ Perlindungan Pemula
				</div>
				<div class="reloc-status-val">
					<?php if ($eligibility['hasProtection']): ?>
						<span style="color: #27ae60;" id="protect_timer">
							<?php
							$hrs = floor($eligibility['timeLeft'] / 3600);
							$mins = floor(($eligibility['timeLeft'] % 3600) / 60);
							$secs = $eligibility['timeLeft'] % 60;
							echo sprintf("%02d:%02d:%02d", $hrs, $mins, $secs);
							?>
						</span>
					<?php else: ?>
						<span style="color: #c0392b;">Telah Berakhir</span>
					<?php endif; ?>
				</div>
				<div class="reloc-status-sub">
					<?php if ($eligibility['hasProtection']): ?>
						Aktif hingga <?php echo date('d M Y, H:i', time() + $eligibility['timeLeft']); ?>
					<?php else: ?>
						Relokasi tidak lagi tersedia
					<?php endif; ?>
				</div>
			</div>

			<div class="reloc-status-box">
				<div class="reloc-status-label">
					🎟️ Hak Pemindahan
				</div>
				<div class="reloc-status-val">
					<?php if ($eligibility['hasUsed']): ?>
						<span style="color: #7f8c8d;">Sudah Digunakan</span>
					<?php elseif ($eligibility['hasProtection']): ?>
						<span style="color: #27ae60;">Tersedia (1x)</span>
					<?php else: ?>
						<span style="color: #c0392b;">Hangus</span>
					<?php endif; ?>
				</div>
				<div class="reloc-status-sub">
					Maksimal 1 kali per akun seumur hidup
				</div>
			</div>
		</div>

		<?php if ($eligibility['hasUsed']): ?>
			<!-- ALREADY USED CERTIFICATE -->
			<div class="reloc-cert">
				<div class="cert-seal">📜</div>
				<div class="cert-title">Piagam Relokasi Wilayah Selesai</div>
				<div class="cert-desc">
					Akun Anda telah memanfaatkan hak istimewa pemindahan desa pemula.<br/>
					<?php if ($relocationLog): ?>
						Desa Anda telah sukses dipindahkan dari koordinat <b>(<?php echo $relocationLog['old_x']; ?>|<?php echo $relocationLog['old_y']; ?>)</b> 
						ke tanah baru di koordinat <b>(<?php echo $relocationLog['new_x']; ?>|<?php echo $relocationLog['new_y']; ?>)</b> 
						pada tanggal <b><?php echo date('d M Y, H:i', $relocationLog['time']); ?></b>.<br/><br/>
					<?php endif; ?>
					Bangun kekaisaran Anda dari tanah baru ini menuju kejayaan TravianZ!
				</div>
			</div>

		<?php elseif (!$eligibility['hasProtection']): ?>
			<!-- EXPIRED PROTECTION NOTICE -->
			<div class="reloc-alert alert-warning">
				<b>🛡️ Masa Perlindungan Berakhir:</b><br/>
				Masa perlindungan pemula akun Anda telah selesai. Sesuai regulasi permainan, hak pemindahan desa hanya dapat digunakan saat masa perlindungan pemula masih berjalan untuk menjaga keseimbangan geopolitik dunia Travian.
			</div>

		<?php else: ?>
			<!-- ELIGIBLE: RELOCATION FORM & CHECKS -->

			<!-- REQUIREMENTS CHECKLIST -->
			<div class="reloc-card">
				<div class="reloc-card-header">
					<span>📋 Persyaratan Pemindahan</span>
					<span>Lokasi Saat Ini: <b>(<?php echo $fromX; ?>|<?php echo $fromY; ?>)</b></span>
				</div>
				<div class="reloc-card-body">
					<ul class="check-list">
						<li class="ok">Perlindungan pemula masih aktif (Tersisa <?php echo round($eligibility['timeLeft'] / 3600, 1); ?> jam).</li>
						<li class="ok">Belum pernah melakukan relokasi sebelumnya (1x kesempatan tersedia).</li>
						<li class="<?php echo empty($eligibility['hasMovements']) ? 'ok' : 'fail'; ?>">
							Semua pasukan dan pedagang harus berada di desa (Tidak boleh ada gerakan militer/logistik).
						</li>
						<li class="<?php echo empty($eligibility['hasEnforcements']) ? 'ok' : 'fail'; ?>">
							Tidak ada pasukan bantuan garnisun dari pemain lain di desa.
						</li>
					</ul>
					<?php if (!empty($eligibility['hasMovements']) || !empty($eligibility['hasEnforcements'])): ?>
						<div style="color: #c0392b; font-weight: bold; margin-top: 6px;">
							⚠️ <?php echo htmlspecialchars($eligibility['reason']); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TARGET VALLEY PICKER -->
			<div class="reloc-card">
				<div class="reloc-card-header">
					<span>🎯 Tentukan Lembah Tujuan</span>
					<a href="karte.php" target="_blank" style="font-size: 11px; color: #2980b9; text-decoration: none;">🗺️ Buka Peta Dunia</a>
				</div>
				<div class="reloc-card-body">
					<form method="get" action="relocate.php">
						<div style="margin-bottom: 6px;">
							Masukkan koordinat lembah kosong yang ingin Anda jadikan lokasi baru desa:
						</div>
						<div class="coord-input-row">
							<span>X:</span>
							<input type="text" name="x" value="<?php echo $targetX !== null ? $targetX : ''; ?>" class="coord-input" maxlength="4" placeholder="0" required />
							<span>Y:</span>
							<input type="text" name="y" value="<?php echo $targetY !== null ? $targetY : ''; ?>" class="coord-input" maxlength="4" placeholder="0" required />
							<button type="submit" class="btn-check">🔍 Periksa Lembah</button>
						</div>
					</form>

					<!-- TARGET PREVIEW -->
					<?php if ($targetPreview && $targetPreview['valid'] && !empty($targetPreview['tile'])): ?>
						<div class="target-preview-box">
							<div class="target-preview-title">
								<span>🌾 Lembah Teridentifikasi: (<?php echo $targetPreview['tile']['x']; ?>|<?php echo $targetPreview['tile']['y']; ?>)</span>
								<span style="margin-left: auto; font-size: 11px; background: #27ae60; color: #fff; padding: 2px 8px; border-radius: 4px;">Tersedia</span>
							</div>

							<table class="target-spec-table">
								<tr>
									<td class="label">Tipe Sumber Daya:</td>
									<td class="value"><?php echo htmlspecialchars($targetPreview['tile']['fieldtypeName']); ?></td>
								</tr>
								<tr>
									<td class="label">Jarak dari Desa:</td>
									<td class="value"><?php echo $targetDistance; ?> bidang</td>
								</tr>
								<tr>
									<td class="label">Status Lembah:</td>
									<td class="value" style="color: #27ae60;">Kosong (Bebas dari kepemilikan lain)</td>
								</tr>
							</table>

							<!-- EXECUTION FORM -->
							<form method="post" action="relocate.php" onsubmit="return confirm('PERINGATAN RESMI:\n\nApakah Anda yakin ingin memindahkan desa ke koordinat (' + <?php echo $targetPreview['tile']['x']; ?> + '|' + <?php echo $targetPreview['tile']['y']; ?> + ')?\n\nSeluruh bangunan, sumber daya, dan pasukan akan segera dipindahkan. Tindakan ini HANYA DAPAT DILAKUKAN SEKALI seumur hidup!');">
								<input type="hidden" name="action" value="relocate" />
								<input type="hidden" name="x" value="<?php echo $targetPreview['tile']['x']; ?>" />
								<input type="hidden" name="y" value="<?php echo $targetPreview['tile']['y']; ?>" />

								<div style="margin-top: 14px; padding: 8px 10px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 4px; font-size: 11px;">
									<label style="cursor: pointer; display: flex; align-items: center; gap: 6px;">
										<input type="checkbox" id="agree_terms" required />
										<span>Saya mengerti dan menyetujui bahwa pemindahan desa ini hanya berlaku <b>1x kali</b> dan tidak dapat dibatalkan.</span>
									</label>
								</div>

								<button type="submit" class="btn-relocate-primary" <?php echo !$eligibility['can'] ? 'disabled' : ''; ?>>
									🚀 Pindahkan Desa ke Koordinat (<?php echo $targetPreview['tile']['x']; ?>|<?php echo $targetPreview['tile']['y']; ?>) Sekarang
								</button>
							</form>
						</div>
					<?php elseif (!empty($targetX) || !empty($targetY)): ?>
						<div class="reloc-alert alert-warning" style="margin-top: 10px;">
							Silakan masukkan koordinat lembah yang kosong dan belum ditempati untuk melihat pratinjau.
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- FAQ / INFORMATION -->
			<div class="reloc-card">
				<div class="reloc-card-header">
					<span>💡 Panduan Relokasi Desa</span>
				</div>
				<div class="reloc-card-body">
					<b>Apa yang ikut dipindahkan?</b>
					<ul style="margin: 4px 0 10px 18px; padding: 0;">
						<li>Semua tingkat bangunan dan sumber daya tetap utuh tanpa penurunan level.</li>
						<li>Jumlah kayu, tanah liat, besi, dan gandum tetap aman terbawa.</li>
						<li>Pasukan di desa, pahlawan (hero), riset akademi, dan antrean bangunan tetap tersimpan.</li>
						<li>Pasukan tentara bayaran (Black Market Mercenaries) ikut berpindah.</li>
					</ul>
					<b>Tips Memilih Lokasi Baru:</b>
					<ul style="margin: 4px 0 0 18px; padding: 0;">
						<li>Carilah lembah 9 atau 15 lumbung gandum (Cropper) jika ingin memperkuat basis militer di masa depan.</li>
						<li>Anda juga dapat mencari lembah kosong langsung dari peta, lalu klik opsi <i>"📍 Pindahkan Desa ke Lembah Ini"</i>.</li>
					</ul>
				</div>
			</div>

		<?php endif; ?>

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

<script type="text/javascript">
// Live countdown timer for beginner protection
(function() {
    var timerEl = document.getElementById('protect_timer');
    if (!timerEl) return;
    
    var secondsLeft = <?php echo (int)($eligibility['timeLeft'] ?? 0); ?>;
    if (secondsLeft <= 0) return;

    var interval = setInterval(function() {
        secondsLeft--;
        if (secondsLeft <= 0) {
            clearInterval(interval);
            timerEl.innerHTML = "Telah Berakhir";
            timerEl.style.color = "#c0392b";
            return;
        }
        var h = Math.floor(secondsLeft / 3600);
        var m = Math.floor((secondsLeft % 3600) / 60);
        var s = secondsLeft % 60;
        timerEl.innerHTML = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }, 1000);
})();
</script>

<div id="ce"></div>
</body>
</html>
