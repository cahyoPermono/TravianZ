<?php
use App\Utils\AccessLogger;

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : index.php                                                 ##
##  Type           : In Game Index Page (War Council & Expansion Redesign)     ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Dzoki                                                     ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow & Antigravity Studio                               ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ Extended Edition                                 ##
##  URLs:          : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if(!file_exists('var/installed') && @opendir('install')) {
    header("Location: install/");
    exit;
}

include_once("GameEngine/config.php");
error_reporting(E_ALL || E_NOTICE);

if(file_exists('Security/Security.class.php'))
{
    require 'Security/Security.class.php';
    Security::instance();
}
else
{
    die('Security: Please activate security class!');
}

include_once "GameEngine/Database.php";
require_once __DIR__ . "/GameEngine/Lang/loader.php";
tz_load_language(LANG);

AccessLogger::logRequest();

$link = $database->return_link();
@mysqli_report(MYSQLI_REPORT_OFF);

// Real-Time World Intelligence
// Player Tribes: 1=Romans, 2=Teutons, 3=Gauls, 6=Egyptians, 7=Huns, 8=Spartans, 9=Vikings, 10=Nusantara
$totalUsers = 0;
$activeUsers = 0;
$onlineUsers = 0;
$totalVillages = 0;
$activeBandits = 0;
$totalMercGarrisons = 0;

try {
    $resUsers = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE tribe IN(1, 2, 3, 6, 7, 8, 9, 10)");
    $totalUsers = $resUsers ? (int)mysqli_fetch_assoc($resUsers)['Total'] : 0;
} catch (\Throwable $e) {}

try {
    $resActive = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE timestamp > " . (time() - 86400) . " AND tribe IN(1, 2, 3, 6, 7, 8, 9, 10)");
    $activeUsers = $resActive ? (int)mysqli_fetch_assoc($resActive)['Total'] : 0;
} catch (\Throwable $e) {}

try {
    $resOnline = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE timestamp > " . (time() - 600) . " AND tribe IN(1, 2, 3, 6, 7, 8, 9, 10)");
    $onlineUsers = $resOnline ? (int)mysqli_fetch_assoc($resOnline)['Total'] : 0;
} catch (\Throwable $e) {}

try {
    $resVillages = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "vdata");
    $totalVillages = $resVillages ? (int)mysqli_fetch_assoc($resVillages)['Total'] : 0;
} catch (\Throwable $e) {}

try {
    $resBandits = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "bandit_camps WHERE status = 1");
    $activeBandits = $resBandits ? (int)mysqli_fetch_assoc($resBandits)['Total'] : 0;
} catch (\Throwable $e) {}

try {
    $resMercs = @mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "mercenaries");
    $totalMercGarrisons = $resMercs ? (int)mysqli_fetch_assoc($resMercs)['Total'] : 0;
} catch (\Throwable $e) {}

// Logged-in session detection
$isLoggedIn = !empty($_SESSION['username']);
$loggedUser = $isLoggedIn ? htmlspecialchars((string)$_SESSION['username'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars(SERVER_NAME); ?> &bull; Dewan Perang Nusantara & Jagat Terlarang</title>
    <link rel="shortcut icon" href="favicon.ico" />
    <meta name="description" content="Portal perang strategi kuno TravianZ dengan Suku Maritim Nusantara, Dinamika Cuaca Ekstrem, Sampar Wabah Desa, Ekspedisi PvE Sarang Bandit, Pasar Gelap Bebas Emas, dan Tentara Bayaran Netral!" />
    <link rel="stylesheet" type="text/css" href="gpack/travian/landing_modern.css?v=20261009_mobile" />
</head>

<body class="war-portal">
    <!-- Top Sticky War Room Navigation -->
    <header class="war-nav">
        <div class="portal-container war-nav-inner">
            <a href="index.php" class="portal-brand">
                <img src="img/rpage/travian_logo.png" alt="Travian Logo" class="brand-crest" />
                <div class="brand-meta">
                    <span class="brand-name"><?php echo htmlspecialchars(SERVER_NAME); ?></span>
                    <span class="brand-edition">EXPANSI NUSANTARA 2026</span>
                </div>
            </a>

            <ul class="war-nav-menu">
                <li><a href="#codex" class="war-nav-link">Buku Ekspansi</a></li>
                <li><a href="#council" class="war-nav-link">Dewan Peradaban</a></li>
                <li><a href="#chronicles" class="war-nav-link">Warta Server</a></li>
                <li><a href="#gallery" class="war-nav-link">Peta Tempur</a></li>
                <li><a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank" rel="noopener noreferrer" class="war-nav-link">Komunitas</a></li>
            </ul>

            <div class="war-nav-actions">
                <?php if ($isLoggedIn): ?>
                    <div class="player-crest-badge desktop-only">
                        <span>Panglima:</span>
                        <strong><?php echo $loggedUser; ?></strong>
                    </div>
                    <a href="dorf1.php" class="war-btn war-btn-gold desktop-only">🏰 Ke Desa Saya</a>
                    <a href="logout.php" class="war-btn war-btn-iron desktop-only" title="Keluar">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="war-btn war-btn-iron desktop-only">🛡️ Masuk Benteng</a>
                    <a href="anmelden.php" class="war-btn war-btn-gold desktop-only">⚔️ Rekrut Akun</a>
                <?php endif; ?>
                <button class="mobile-nav-toggle" id="mobileMenuBtn" aria-label="Buka Menu" type="button">☰</button>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Backdrop & Navigation -->
    <div class="mobile-drawer-backdrop" id="mobileDrawerBackdrop" onclick="closeMobileMenu()"></div>
    <div class="mobile-drawer" id="mobileMenuPanel" role="dialog" aria-modal="true" aria-label="Menu Navigasi Mobile">
        <div class="mobile-drawer-header">
            <div class="portal-brand">
                <img src="img/rpage/travian_logo.png" alt="Travian Logo" class="brand-crest" style="height:38px;" />
                <div class="brand-meta">
                    <span class="brand-name" style="font-size:18px;"><?php echo htmlspecialchars(SERVER_NAME); ?></span>
                    <span class="brand-edition" style="font-size:9px; letter-spacing:1.5px;">EXPANSI 2026</span>
                </div>
            </div>
            <button class="mobile-drawer-close" id="mobileDrawerCloseBtn" onclick="closeMobileMenu()" aria-label="Tutup Menu" type="button">✕</button>
        </div>

        <div class="mobile-drawer-body">
            <?php if ($isLoggedIn): ?>
                <div class="mobile-user-card">
                    <span class="mobile-user-label">Panglima Terdaftar</span>
                    <strong class="mobile-user-name">👑 <?php echo $loggedUser; ?></strong>
                </div>
                <div class="mobile-drawer-actions">
                    <a href="dorf1.php" class="war-btn war-btn-gold" style="width:100%;">🏰 Ke Desa Saya</a>
                    <a href="logout.php" class="war-btn war-btn-iron" style="width:100%;">Keluar</a>
                </div>
            <?php else: ?>
                <div class="mobile-drawer-actions">
                    <a href="anmelden.php" class="war-btn war-btn-gold" style="width:100%;">⚔️ Rekrut Akun Baru</a>
                    <a href="login.php" class="war-btn war-btn-iron" style="width:100%;">🛡️ Masuk Benteng</a>
                </div>
            <?php endif; ?>

            <div class="mobile-drawer-divider"></div>

            <ul class="mobile-drawer-nav">
                <li><a href="#codex" class="mobile-nav-link" onclick="closeMobileMenu()"><span>📜</span> Buku Ekspansi</a></li>
                <li><a href="#council" class="mobile-nav-link" onclick="closeMobileMenu()"><span>👑</span> Dewan Peradaban</a></li>
                <li><a href="#chronicles" class="mobile-nav-link" onclick="closeMobileMenu()"><span>📢</span> Warta Server</a></li>
                <li><a href="#gallery" class="mobile-nav-link" onclick="closeMobileMenu()"><span>🗺️</span> Peta Tempur</a></li>
                <li><a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank" rel="noopener noreferrer" class="mobile-nav-link" onclick="closeMobileMenu()"><span>💬</span> Komunitas Forum ↗</a></li>
            </ul>
        </div>
    </div>

    <!-- Hero Stage: Asymmetric War Room -->
    <section class="hero-stage">
        <div class="hero-backdrop-glow"></div>
        <div class="portal-container hero-grid">
            <!-- Left Column: Manifesto & Action -->
            <div class="hero-dispatch">
                <div class="imperial-seal-pill">
                    <span class="seal-gem"></span>
                    <span class="seal-text">DOKTRIN EXPANSION 2026: NUSANTARA & JAGAT TERLARANG</span>
                </div>

                <h1 class="hero-headline">
                    PIMPIN PERADABAN AGUNG,<br />
                    <span class="gold-shimmer">TAKDIRKAN SEJARAH DUNIA.</span>
                </h1>

                <p class="hero-manifesto">
                    Jelajahi era paling sengit dalam sejarah <strong>Travian</strong>. Pimpin armada bahari adidaya <strong>Suku Nusantara</strong>, bertarung di bawah amukan <strong>Dinamika Cuaca Ekstrem</strong>, bentengi desa dari ancaman <strong>Wabah Sampar</strong>, buru <strong>Sarang Bandit PvE</strong> di peta kuno, dan kendalikan <strong>Pasar Gelap</strong> bersama kompi <strong>Tentara Bayaran Netral</strong>!
                </p>

                <div class="hero-cta-cluster">
                    <?php if ($isLoggedIn): ?>
                        <a href="dorf1.php" class="war-btn war-btn-gold war-btn-lg">🏰 Kembali Memimpin Desa (<?php echo $loggedUser; ?>)</a>
                        <a href="#codex" class="war-btn war-btn-iron war-btn-lg">📜 Buka Buku Ekspansi</a>
                    <?php else: ?>
                        <a href="anmelden.php" class="war-btn war-btn-gold war-btn-lg">⚔️ Bangun Kekaisaran Anda (Daftar Sekarang)</a>
                        <a href="login.php" class="war-btn war-btn-iron war-btn-lg">🛡️ Masuk ke Medan Tempur</a>
                    <?php endif; ?>
                </div>

                <!-- War Intel Live Counters -->
                <div class="intel-ribbon">
                    <div class="intel-card">
                        <div class="intel-metric"><?php echo number_format($totalUsers); ?></div>
                        <div class="intel-label">Total Jenderal</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" style="color:#34d399;"><?php echo number_format($onlineUsers); ?></div>
                        <div class="intel-label">Pasukan Siaga</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric"><?php echo number_format($totalVillages); ?></div>
                        <div class="intel-label">Benteng & Desa</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" style="color:#f87171;"><?php echo number_format($activeBandits); ?></div>
                        <div class="intel-label">Sarang Bandit Siap Diserbu</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive Hero Commander Stage -->
            <div class="hero-commander-stage">
                <div class="commander-aura-ring"></div>
                <div class="commander-pedestal">
                    <div class="commander-portrait-frame">
                        <img src="img/reg/portrait_v10.png" alt="Panglima Nusantara" class="commander-portrait-img" id="heroCommanderImg" />
                    </div>
                    <div class="faction-banner-badge">
                        <img src="img/rpage/Nusantara1.jpg" alt="Panji Nusantara" id="heroBannerImg" />
                    </div>
                    <div class="commander-caption-box">
                        <h3 class="commander-caption-title" id="heroCommanderTitle">Raden Wijaya</h3>
                        <p class="commander-caption-sub" id="heroCommanderSub">Panglima Tertinggi Kerajaan Maritim Nusantara</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tactical Expansion Codex: The 6 Custom Systems -->
    <section class="codex-section" id="codex">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">⚡ DOKUMEN INTELIJEN BENTENG</span>
                <h2 class="heraldry-title">6 Pilar Ekspansi yang Mengubah Medan Perang</h2>
                <p class="heraldry-subtitle">
                    Keseimbangan strategi kini dipengaruhi oleh alam, epidemi biologis, jalur perdagangan bawah tanah, dan kekuatan angkatan perang baru.
                </p>
            </div>

            <div class="codex-layout">
                <!-- Feature 1: Grand Nusantara Flagship Feature -->
                <div class="grand-feature-card">
                    <div class="grand-feature-content">
                        <div class="grand-feature-badge">
                            <span>🇮🇩 SUKU EKSKLUSIF KE-10</span>
                        </div>
                        <h3 class="grand-feature-title">Kemaharajaan Maritim Nusantara</h3>
                        <p class="grand-feature-desc">
                            Peradaban agung kepulauan khatulistiwa yang menguasai jalur perdagangan maritim dunia kuno. Nusantara diberkahi kapasitas lumbung hasil bumi yang melimpah, artileri meriam <strong>Cetbang</strong> yang sanggup meremukkan pertahanan tembok batu musuh, dan barisan <strong>Gajah Bhayangkara</strong> yang kebal terhadap gempuran barisan depan lawan.
                        </p>

                        <!-- Troops Lineup Strip -->
                        <div class="grand-unit-strip">
                            <span class="strip-label">10 Unit Militer Khas:</span>
                            <img src="img/un/u/91.gif" alt="Keris" class="sprite-trooper" title="Pendekar Keris - Infanteri serang gesit beracun" />
                            <img src="img/un/u/92.gif" alt="Tombak" class="sprite-trooper" title="Pasukan Tombak - Pertahanan kokoh anti kavaleri" />
                            <img src="img/un/u/93.gif" alt="Panah" class="sprite-trooper" title="Pemanah Panah - Penembak jitu rimba tropis" />
                            <img src="img/un/u/94.gif" alt="Golok" class="sprite-trooper" title="Pendekar Golok - Barisan tempur frontal" />
                            <img src="img/un/u/95.gif" alt="Cetbang" class="sprite-trooper" title="Cetbang Artileri - Meriam putar perunggu penghancur tembok" />
                            <img src="img/un/u/96.gif" alt="Kuda Laut" class="sprite-trooper" title="Kavaleri Kuda Laut - Pengintai cepat antar wilayah" />
                            <img src="img/un/u/97.gif" alt="Badak" class="sprite-trooper" title="Kavaleri Badak - Kavaleri dobrak lapis tebal" />
                            <img src="img/un/u/98.gif" alt="Gajah" class="sprite-trooper" title="Gajah Bhayangkara - Monster lapis baja pemusnah formasi" />
                            <img src="img/un/u/99.gif" alt="Jung" class="sprite-trooper" title="Jung Maritim - Armada ekspedisi laut raksasa" />
                            <img src="img/un/u/100.gif" alt="Resi" class="sprite-trooper" title="Tetua Adat / Resi - Penakluk kesetiaan desa musuh" />
                        </div>
                    </div>

                    <div class="grand-visual-column">
                        <img src="img/rpage/Nusantara1.jpg" alt="Panji Nusantara" class="grand-banner-preview" />
                        <div class="grand-traits-row">
                            <div class="mini-trait-box">
                                <div class="mini-trait-name">🌾 Lumbung Pangan</div>
                                <div class="mini-trait-info">Kapasitas penyimpanan logistik lumbung & perlindungan komoditas pangan superior.</div>
                            </div>
                            <div class="mini-trait-box">
                                <div class="mini-trait-name">💥 Artileri Cetbang</div>
                                <div class="mini-trait-info">Meriam perunggu putar dengan daya tembak artileri tinggi penembus benteng batu.</div>
                            </div>
                            <div class="mini-trait-box">
                                <div class="mini-trait-name">🐘 Gajah Bhayangkara</div>
                                <div class="mini-trait-info">Unit monster lapis baja berdaya hancur dahsyat pematah formasi infantri musuh.</div>
                            </div>
                            <div class="mini-trait-box">
                                <div class="mini-trait-name">⛵ Armada Amfibi</div>
                                <div class="mini-trait-info">Keahlian navigasi dan pergerakan amfibi yang fleksibel di medan pesisir.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2-Column Split Cards for Features 2-6 -->
                <div class="codex-dual-grid">
                    <!-- Feature 2: Bandit Camps PvE -->
                    <div class="codex-card codex-card-bandit">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#ef4444;">🎯</div>
                            <span class="codex-tag tag-bandit">Ekspedisi PvE & World Boss</span>
                        </div>
                        <h3 class="codex-card-title">Radar Sarang Bandit & World Boss</h3>
                        <p class="codex-card-body">
                            Petakan dunia kuno dan hancurkan persembunyian komplotan penyamun liar! Mulai dari <strong>Penyamun Rimba Tier 1</strong> hingga <strong>World Boss Sarang Naga & Gembong Tier 4</strong>. Hancurkan sarang mereka untuk meraup jarahan sumber daya berimbang, EXP Hero masif, Poin Budaya (CP), dan Koin Silver!
                        </p>
                        <div class="bandit-preview-row">
                            <img src="img/bandit/camp_tier_4.png" alt="World Boss Sarang Naga" class="bandit-preview-img" />
                            <div>
                                <div style="font-size:12.5px; font-weight:800; color:#fff;">World Boss Tier 4: Sarang Naga</div>
                                <div style="font-size:11.5px; color:#f87171;">Tantangan raid kolosal dengan hadiah Silver berlimpah!</div>
                            </div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#ef4444;"></span> 4 Peringkat Sarang Bandit berimbang (Tier 1 s/d Tier 4)</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#ef4444;"></span> Reward Hero EXP, Poin Budaya (CP), dan Koin Silver</div>
                        </div>
                    </div>

                    <!-- Feature 3: Dynamic Weather Engine -->
                    <div class="codex-card codex-card-weather">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#0ea5e9;">🌧️</div>
                            <span class="codex-tag tag-weather">Mesin Iklim Real-Time</span>
                        </div>
                        <h3 class="codex-card-title">Dinamika Cuaca & Bencana Alam</h3>
                        <p class="codex-card-body">
                            Medan pertempuran kini tidak pernah statis! 5 sistem iklim memengaruhi kerajaan Anda: <strong>Musim Kemarau</strong> (panen melimpah, gerak lambat), <strong>Hujan Badai</strong> (lumpur memperlambat gerak pasukan), <strong>Kabut Tebal</strong> (intai gagal), <strong>Badai Salju</strong> (konsumsi gandum melonjak), dan <strong>Angin Kering</strong>.
                        </p>
                        <div class="weather-chips-cluster">
                            <div class="weather-chip active">☀️ Kemarau</div>
                            <div class="weather-chip active">🌧️ Hujan Badai</div>
                            <div class="weather-chip active">🌫️ Kabut Tebal</div>
                            <div class="weather-chip active">❄️ Badai Salju</div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#0ea5e9;"></span> Mempengaruhi kecepatan infanteri, kavaleri, dan senjata kepung</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#0ea5e9;"></span> Menuntut fleksibilitas taktik penyerangan berdasarkan ramalan cuaca</div>
                        </div>
                    </div>

                    <!-- Feature 4: Plague & Quarantine -->
                    <div class="codex-card codex-card-plague">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#a855f7;">☣️</div>
                            <span class="codex-tag tag-plague">Sanitasi & Karantina Desa</span>
                        </div>
                        <h3 class="codex-card-title">Epidemi Sampar & Karantina Gerbang</h3>
                        <p class="codex-card-body">
                            Ancaman biologis yang sanggup meruntuhkan kekaisaran dalam hitungan hari! Penyakit <strong>Kolera</strong>, <strong>Demam Rawa</strong>, dan <strong>Sampar Hitam</strong> dapat menjangkiti desa dan menular ke desa sekitar. Karantina gerbang perbatasan untuk memutus transmisi, racik obat bersama Tabib herbal, atau tebus ramuan penawar langka!
                        </p>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#a855f7;"></span> Transmisi infeksi dinamis antar desa tetangga</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#a855f7;"></span> Gerbang Karantina Desa, Tabib Tradisional, dan Penawar Selundupan</div>
                        </div>
                    </div>

                    <!-- Feature 5: The Black Market -->
                    <div class="codex-card codex-card-market">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#f59e0b;">🏴‍☠️</div>
                            <span class="codex-tag tag-market">Bebas Emas (Tab 5 Pasar)</span>
                        </div>
                        <h3 class="codex-card-title">Pasar Gelap Para Penyelundup</h3>
                        <p class="codex-card-body">
                            Saudagar misterius kini menggelar lapak rahasia di Pasar desa! <strong>Cuci & Tukar Sumber Daya</strong> surplus tanpa memerlukan koin Emas (hanya potongan komisi wajar 15%), tebus <strong>Peti Kargo Suplai Instan</strong> menggunakan koin Silver dari membasmi bandit, dan beli obat penawar wabah selundupan.
                        </p>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#f59e0b;"></span> 100% Bebas Gold: cuci sumber daya dengan komisi 15% pedagang gelap</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#f59e0b;"></span> Tebus peti perbekalan instan (Kayu, Tanah, Besi, Gandum) via Silver</div>
                        </div>
                    </div>

                    <!-- Feature 6: Mercenary Enclaves -->
                    <div class="codex-card codex-card-mercenary" style="grid-column: 1 / -1;">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#3b82f6;">🛡️</div>
                            <span class="codex-tag tag-mercenary">Garnisun Instan (Tab 6 Pasar)</span>
                        </div>
                        <h3 class="codex-card-title">Tentara Bayaran Netral (Mercenary Enclaves)</h3>
                        <p class="codex-card-body">
                            Butuh bala bantuan garnisun instan tanpa menunggu antrean barak atau riset teknologi di akademi? Sewa serdadu bayaran berpengalaman di Tab 6 Pasar menggunakan koin Silver: <strong>Garda Zirah Besi</strong> (perisai infantri berat), <strong>Pemanah Busur Kreta</strong> (penembak runduk mematikan), <strong>Penjarah Stepa</strong> (kavaleri tombak), dan <strong>Penebas Benteng</strong> (veteran pendobrak). Otomatis memperkuat pertahanan desa dari serbuan musuh dengan sistem desersi otomatis saat kelaparan gandum!
                        </p>
                        <div class="bullet-traits-list mercenary-traits-grid">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#3b82f6;"></span> Kontrak bayaran menggunakan koin Silver hasil berburu</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#3b82f6;"></span> Menjaga garnisun desa dari serangan & serbuan lawan</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#3b82f6;"></span> Desersi logis saat lumbung gandum desa mengalami kelaparan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- The War Council: 8 Civilizations Arena -->
    <section class="council-section" id="council">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">👑 DEWAN KERAJAAN DUNIA KUNO</span>
                <h2 class="heraldry-title">Pilih Peradaban & Tentukan Jalur Kejayaan Anda</h2>
                <p class="heraldry-subtitle">
                    Setiap suku memiliki karakteristik kepemimpinan, doktrin tempur, dan keunggulan militer yang unik. Pilih faksi yang mewakili jiwa strategi Anda.
                </p>
            </div>

            <!-- Faction Selector Tabs -->
            <div class="faction-tabs-bar">
                <button class="faction-tab-btn active" data-faction="nusantara">🇮🇩 Nusantara</button>
                <button class="faction-tab-btn" data-faction="romans">🏛️ Romawi</button>
                <button class="faction-tab-btn" data-faction="teutons">⚔️ Teuton</button>
                <button class="faction-tab-btn" data-faction="gauls">🛡️ Galia</button>
                <button class="faction-tab-btn" data-faction="egyptians">☀️ Mesir</button>
                <button class="faction-tab-btn" data-faction="huns">🏹 Hun</button>
                <button class="faction-tab-btn" data-faction="spartans">🛡️ Sparta</button>
                <button class="faction-tab-btn" data-faction="vikings">🪓 Viking</button>
            </div>

            <!-- Interactive Council Arena Display Card -->
            <div class="council-arena-card" id="councilArenaCard">
                <!-- Left: Life-size Commander Cutout -->
                <div class="council-portrait-col">
                    <div class="council-portrait-box">
                        <img src="img/reg/portrait_v10.png" alt="Panglima Faksi" id="councilPortraitImg" class="council-portrait-img" />
                    </div>
                    <img src="img/rpage/Nusantara1.jpg" alt="Panji Faksi" id="councilBannerImg" class="council-banner-img" />
                    <span id="councilFactionBadge" class="council-faction-badge">Eksklusif: Kerajaan Maritim</span>
                </div>

                <!-- Right: Lore, Traits, Roster -->
                <div class="council-details-col">
                    <div class="council-header-bar">
                        <h3 class="council-tribe-name" id="councilTribeName">Kerajaan Nusantara</h3>
                        <span class="council-tribe-type" id="councilTribeType">Peradaban Maritim & Agrikultur</span>
                    </div>

                    <p class="council-tribe-lore" id="councilTribeLore">
                        Kerajaan Maritim Nusantara berdiri megah di atas gugusan kepulauan khatulistiwa yang kaya rempah. Dengan perpaduan armada kapal Jung raksasa, lumbung pangan yang melimpah, artileri meriam Cetbang, serta barisan Gajah Bhayangkara yang disegani, Nusantara adalah peradaban adidaya maritim yang tangguh dalam pertahanan dan mematikan dalam serbuan amfibi.
                    </p>

                    <div class="council-traits-grid" id="councilTraitsGrid">
                        <div class="council-trait-card">
                            <div class="council-trait-title">🌾 Lumbung Kemakmuran</div>
                            <div class="council-trait-desc">Kapasitas penyimpanan lumbung pangan dan perlindungan komoditas pangan lebih unggul.</div>
                        </div>
                        <div class="council-trait-card">
                            <div class="council-trait-title">💥 Artileri Cetbang</div>
                            <div class="council-trait-desc">Meriam perunggu putar yang sanggup meremukkan dinding benteng lawan dengan presisi tinggi.</div>
                        </div>
                        <div class="council-trait-card">
                            <div class="council-trait-title">🐘 Gajah Bhayangkara</div>
                            <div class="council-trait-desc">Monster lapis baja pengobrak-abrik barisan infanteri musuh dalam pertempuran frontal.</div>
                        </div>
                        <div class="council-trait-card">
                            <div class="council-trait-title">⛵ Jung & Kuda Laut</div>
                            <div class="council-trait-desc">Kekuatan mobilitas armada cepat untuk ekspedisi dan pengintaian pesisir.</div>
                        </div>
                    </div>

                    <div class="council-roster-footer">
                        <span class="roster-label">Barisan Pasukan:</span>
                        <div class="council-icons-strip" id="councilTroopStrip">
                            <img src="img/un/u/91.gif" alt="Keris" class="sprite-trooper" title="Pendekar Keris" />
                            <img src="img/un/u/92.gif" alt="Tombak" class="sprite-trooper" title="Pasukan Tombak" />
                            <img src="img/un/u/93.gif" alt="Panah" class="sprite-trooper" title="Pemanah Panah" />
                            <img src="img/un/u/94.gif" alt="Golok" class="sprite-trooper" title="Pendekar Golok" />
                            <img src="img/un/u/95.gif" alt="Cetbang" class="sprite-trooper" title="Cetbang Artileri" />
                            <img src="img/un/u/96.gif" alt="Kuda Laut" class="sprite-trooper" title="Kavaleri Kuda Laut" />
                            <img src="img/un/u/97.gif" alt="Badak" class="sprite-trooper" title="Kavaleri Badak" />
                            <img src="img/un/u/98.gif" alt="Gajah" class="sprite-trooper" title="Gajah Bhayangkara" />
                            <img src="img/un/u/99.gif" alt="Jung" class="sprite-trooper" title="Jung Maritim" />
                            <img src="img/un/u/100.gif" alt="Resi" class="sprite-trooper" title="Tetua Adat / Resi" />
                        </div>
                        <a href="anmelden.php" class="war-btn war-btn-gold" style="margin-left:auto;">⚔️ Pilih Suku Ini</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Server Chronicles & Dispatch -->
    <section class="chronicles-section" id="chronicles">
        <div class="portal-container chronicles-grid">
            <!-- Server Parameters Ledger -->
            <div class="chronicle-card">
                <div class="chronicle-header">
                    <h3 class="chronicle-title">📊 Parameter Dunia & Status Server</h3>
                    <div class="server-live-dot-tag">
                        <span class="seal-gem" style="background:#10b981; box-shadow:0 0 10px #10b981;"></span>
                        <span>SERVER AKTIF & STABIL</span>
                    </div>
                </div>

                <div class="specs-ledger">
                    <div class="ledger-row">
                        <span class="ledger-key">Nama Server / Dunia</span>
                        <span class="ledger-val gold"><?php echo htmlspecialchars(SERVER_NAME); ?></span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Versi Engine</span>
                        <span class="ledger-val">TravianZ Extended v4.5 (Nusantara)</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Kecepatan Permainan (Speed)</span>
                        <span class="ledger-val gold">1x Classic Balanced</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Total Panglima Terdaftar</span>
                        <span class="ledger-val"><?php echo number_format($totalUsers); ?> Panglima</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Total Desa di Peta</span>
                        <span class="ledger-val"><?php echo number_format($totalVillages); ?> Desa</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Sarang Bandit Beroperasi</span>
                        <span class="ledger-val" style="color:#f87171;"><?php echo number_format($activeBandits); ?> Sarang Terbuka</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Kompi Bayaran Bertugas</span>
                        <span class="ledger-val" style="color:#60a5fa;"><?php echo number_format($totalMercGarrisons); ?> Garnisun Aktif</span>
                    </div>
                    <div class="ledger-row">
                        <span class="ledger-key">Proteksi Server</span>
                        <span class="ledger-val gold">Anti-Cheat & Anti-Bot Siaga</span>
                    </div>
                </div>
            </div>

            <!-- War Dispatch Notes -->
            <div class="chronicle-card">
                <div class="chronicle-header">
                    <h3 class="chronicle-title">📜 Lembar Warta & Titah Resmi</h3>
                    <a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank" rel="noopener noreferrer" class="war-btn war-btn-iron" style="padding:6px 14px; font-size:12px;">Forum ↗</a>
                </div>

                <div class="dispatch-list">
                    <div class="dispatch-item">
                        <div class="dispatch-meta">
                            <span class="dispatch-tag">RILIS BARU</span>
                            <span class="dispatch-date">Oktober 2026</span>
                        </div>
                        <h4 class="dispatch-headline">Pasar Gelap & Tentara Bayaran Netral Resmi Beroperasi!</h4>
                        <p class="dispatch-text">
                            Fitur pencucian sumber daya tanpa emas kini dibuka di Tab 5 Pasar. Seluruh panglima dapat menyewa serdadu bayaran independen di Tab 6 Pasar menggunakan koin Silver hasil perburuan bandit untuk memperkuat pertahanan benteng seketika.
                        </p>
                    </div>

                    <div class="dispatch-item">
                        <div class="dispatch-meta">
                            <span class="dispatch-tag">INVASI PVE</span>
                            <span class="dispatch-date">Oktober 2026</span>
                        </div>
                        <h4 class="dispatch-headline">Radar Sarang Bandit Melacak Kemunculan Sarang Naga</h4>
                        <p class="dispatch-text">
                            Peta dunia kuno kini dipenuhi sarang penyamun rimba hingga sarang naga legendaris. Kalahkan mereka untuk mengumpulkan rampasan pangan berimbang, EXP pahlawan, Poin Budaya, dan Koin Silver!
                        </p>
                    </div>

                    <div class="dispatch-item">
                        <div class="dispatch-meta">
                            <span class="dispatch-tag">PERADABAN BARU</span>
                            <span class="dispatch-date">Oktober 2026</span>
                        </div>
                        <h4 class="dispatch-headline">Kemaharajaan Nusantara Memasuki Kancah Perang Dunia</h4>
                        <p class="dispatch-text">
                            Suku ke-10 Nusantara resmi dapat dipilih saat registrasi. Pimpin barisan Pendekar Keris, artileri Cetbang, dan Gajah Bhayangkara untuk menorehkan tinta emas kemenangan kekaisaran Anda!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Battlefield Scout Gallery -->
    <section class="battlefield-gallery-section" id="gallery">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">🗺️ LEMBAR PETA & REKOGNISI</span>
                <h2 class="heraldry-title">Dokumentasi Medan Tempur & Wilayah Kerajaan</h2>
                <p class="heraldry-subtitle">
                    Pratinjau otentik dari citra satelit perang: markas bandit liar, tata letak desa, dan peta penjelajahan dunia.
                </p>
            </div>

            <div class="gallery-scout-grid">
                <div class="scout-shot-card" onclick="triggerLightbox('img/bandit/camp_tier_4.png', 'World Boss Tier 4: Sarang Naga', 'Markas monster legenda kolosal yang menuntut serangan terkoordinasi gabungan aliansi agung.')">
                    <img src="img/bandit/camp_tier_4.png" alt="World Boss Sarang Naga" class="scout-shot-img" />
                    <div class="scout-shot-caption">
                        <div class="caption-title">World Boss: Sarang Naga</div>
                        <div class="caption-sub">Ekspedisi PvE Kolosal</div>
                    </div>
                </div>

                <div class="scout-shot-card" onclick="triggerLightbox('img/bandit/camp_tier_2.png', 'Pasukan Pembelot Bandit (Tier 2)', 'Markas persembunyian penyamun bersenjata di bentang lembah berbatu.')">
                    <img src="img/bandit/camp_tier_2.png" alt="Pasukan Pembelot" class="scout-shot-img" />
                    <div class="scout-shot-caption">
                        <div class="caption-title">Pasukan Pembelot (Tier 2)</div>
                        <div class="caption-sub">Sarang Bandit Bersenjata</div>
                    </div>
                </div>

                <div class="scout-shot-card" onclick="triggerLightbox('img/reg/map.png', 'Kartografi Dunia Travian Kuno', 'Peta kuadran navigasi perang yang memuat ribuan desa pemain dan oasis subur.')">
                    <img src="img/reg/map.png" alt="Peta Dunia" class="scout-shot-img" />
                    <div class="scout-shot-caption">
                        <div class="caption-title">Kartografi Wilayah Kuno</div>
                        <div class="caption-sub">Navigasi Peta Kuadran</div>
                    </div>
                </div>

                <div class="scout-shot-card" onclick="triggerLightbox('img/en/s/s1.png', 'Pusat Pemerintahan & Tata Kota', 'Pengelolaan gedung utama, barak prajurit, pasar perdagangan, dan lumbung peradaban.')">
                    <img src="img/en/s/s1.png" alt="Tata Kota Desa" class="scout-shot-img" />
                    <div class="scout-shot-caption">
                        <div class="caption-title">Tata Kelola Desa</div>
                        <div class="caption-sub">Jantung Ekonomi Kerajaan</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Imperial Call to Arms (Bottom Banner) -->
    <section class="call-to-arms-section">
        <div class="portal-container">
            <div class="throne-banner">
                <h2 class="throne-title">Takhta Keajaiban Dunia Menanti Titah Anda</h2>
                <p class="throne-sub">
                    Ratusan aliansi telah berikrar setia, ribuan pedang telah diasah. Tentukan nasib peradaban Anda sekarang sebelum genderang perang berbunyi!
                </p>
                <div class="throne-cta-cluster">
                    <?php if ($isLoggedIn): ?>
                        <a href="dorf1.php" class="war-btn war-btn-gold war-btn-lg">🏰 Masuk ke Desa Saya Sekarang</a>
                    <?php else: ?>
                        <a href="anmelden.php" class="war-btn war-btn-gold war-btn-lg">⚔️ Daftar Akun & Mulai Berperang Gratis</a>
                        <a href="login.php" class="war-btn war-btn-iron war-btn-lg">🛡️ Masuk ke Akun Terdaftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Imperial Footer -->
    <footer class="imperial-footer">
        <div class="portal-container">
            <div class="footer-heraldry-grid">
                <div>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                        <img src="img/rpage/travian_logo.png" alt="Logo" style="height:40px; width:auto;" />
                        <span style="font-family:var(--font-title); font-size:22px; font-weight:900; color:var(--gold-glow);"><?php echo htmlspecialchars(SERVER_NAME); ?></span>
                    </div>
                    <p class="footer-desc">
                        TravianZ Extended Nusantara Edition adalah permainan strategi peramban multipemain masif yang menuntut keahlian kepemimpinan, kalkulasi taktis militer, diplomasi aliansi, dan penguasaan peradaban kuno.
                    </p>
                </div>

                <div>
                    <h5 class="footer-nav-title">Akses Cepat</h5>
                    <ul class="footer-nav-list">
                        <li><a href="anmelden.php">Pendaftaran Jenderal</a></li>
                        <li><a href="login.php">Masuk ke Benteng</a></li>
                        <li><a href="#codex">Buku Ekspansi Fitur</a></li>
                        <li><a href="#council">Dewan Peradaban</a></li>
                    </ul>
                </div>

                <div>
                    <h5 class="footer-nav-title">Bantuan & Doktrin</h5>
                    <ul class="footer-nav-list">
                        <li><a href="anleitung.php">Petunjuk Manual</a></li>
                        <li><a href="tutorial.php">Tutorial Dasar Pemula</a></li>
                        <li><a href="anleitung.php?s=3">FAQ Pertanyaan Umum</a></li>
                        <li><a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank" rel="noopener noreferrer">Forum Diskusi Resmi</a></li>
                    </ul>
                </div>

                <div>
                    <h5 class="footer-nav-title">Hukum & Kebijakan</h5>
                    <ul class="footer-nav-list">
                        <li><a href="spielregeln.php">Peraturan Perang (Rules)</a></li>
                        <li><a href="agb.php">Ketentuan Layanan (AGB)</a></li>
                        <li><a href="impressum.php">Imprint & Kontak Server</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom-ledger">
                <div>
                    &copy; 2011-<?php echo date('Y'); ?> <strong>TravianZ Project</strong>. Hak cipta dilindungi undang-undang.
                </div>
                <div>
                    Didukung oleh TravianZ Core Engine &bull; Nusantara Custom Architecture v4.5
                </div>
            </div>
        </div>
    </footer>

    <!-- Lightbox Modal -->
    <div class="war-lightbox" id="warLightboxModal" onclick="closeLightbox(event)">
        <div class="war-lightbox-panel" onclick="event.stopPropagation()">
            <button class="lightbox-close-btn" onclick="closeLightbox(event)" aria-label="Tutup">&times;</button>
            <img src="" alt="Pratinjau Foto" id="lightboxModalImg" class="lightbox-img-viewer" />
            <div class="lightbox-details-box">
                <h4 id="lightboxModalTitle">Judul</h4>
                <p id="lightboxModalDesc">Deskripsi gambar tangkapan layar perang.</p>
            </div>
        </div>
    </div>

    <!-- Client-side Interactive Logic -->
    <script>
        // Faction dataset with complete commander portraits, banners, lore, traits, and troop sprites
        const factionRegistry = {
            nusantara: {
                name: "Kerajaan Nusantara",
                type: "Peradaban Maritim & Agrikultur",
                portrait: "img/reg/portrait_v10.png",
                banner: "img/rpage/Nusantara1.jpg",
                badge: "Eksklusif: Kerajaan Maritim",
                commander: "Raden Wijaya",
                commanderSub: "Panglima Tertinggi Kerajaan Maritim Nusantara",
                lore: "Kerajaan Maritim Nusantara berdiri megah di atas gugusan kepulauan khatulistiwa yang kaya rempah. Dengan perpaduan armada kapal Jung raksasa, lumbung pangan yang melimpah, artileri meriam Cetbang, serta barisan Gajah Bhayangkara yang disegani, Nusantara adalah peradaban adidaya maritim yang tangguh dalam pertahanan dan mematikan dalam serbuan amfibi.",
                traits: [
                    { title: "🌾 Lumbung Kemakmuran", desc: "Kapasitas penyimpanan lumbung pangan dan perlindungan komoditas pangan lebih unggul." },
                    { title: "💥 Artileri Cetbang", desc: "Meriam perunggu putar yang sanggup meremukkan dinding benteng lawan dengan presisi tinggi." },
                    { title: "🐘 Gajah Bhayangkara", desc: "Monster lapis baja pengobrak-abrik barisan infanteri musuh dalam pertempuran frontal." },
                    { title: "⛵ Jung & Kuda Laut", desc: "Kekuatan mobilitas armada cepat untuk ekspedisi dan pengintaian pesisir." }
                ],
                units: [
                    { id: 91, name: "Pendekar Keris" },
                    { id: 92, name: "Pasukan Tombak" },
                    { id: 93, name: "Pemanah Panah" },
                    { id: 94, name: "Pendekar Golok" },
                    { id: 95, name: "Cetbang Artileri" },
                    { id: 96, name: "Kavaleri Kuda Laut" },
                    { id: 97, name: "Kavaleri Badak" },
                    { id: 98, name: "Gajah Bhayangkara" },
                    { id: 99, name: "Jung Maritim" },
                    { id: 100, name: "Tetua Adat / Resi" }
                ]
            },
            romans: {
                name: "Kekaisaran Romawi",
                type: "Infanteri Elit & Arsitektur Canggih",
                portrait: "img/reg/portrait_v1.png",
                banner: "img/rpage/Roman1.jpg",
                badge: "Kekaisaran Klasik",
                commander: "Julius Caesar",
                commanderSub: "Kaisar Tertinggi Legiun Romawi",
                lore: "Kekaisaran Romawi terkenal dengan kedisiplinan militer tinggi dan kemajuan teknologi arsitektur mereka. Prajurit Romawi memiliki kemampuan tempur infanteri terbaik di dunia kuno, didukung sistem pembangunan ganda yang memungkinkan desa berkembang dengan sangat pesat.",
                traits: [
                    { title: "🏛️ Pembangunan Ganda", desc: "Mampu membangun gedung dan ladang sumber daya secara serentak." },
                    { title: "🛡️ Praetorian Tangguh", desc: "Infanteri pertahanan terbaik di dunia kuno penangkal infantri musuh." },
                    { title: "⚔️ Equites Caesaris", desc: "Kavaleri berat perkasa dengan daya serang paling mematikan." },
                    { title: "🧱 Tembok Kota Kokoh", desc: "Memberikan bonus pertahanan tertinggi bagi seluruh garnisun benteng." }
                ],
                units: [
                    { id: 1, name: "Legionnaire" },
                    { id: 2, name: "Praetorian" },
                    { id: 3, name: "Imperian" },
                    { id: 4, name: "Equites Legati" },
                    { id: 5, name: "Equites Imperatoris" },
                    { id: 6, name: "Equites Caesaris" },
                    { id: 7, name: "Battering Ram" },
                    { id: 8, name: "Fire Catapult" },
                    { id: 9, name: "Senator" },
                    { id: 10, name: "Settler" }
                ]
            },
            teutons: {
                name: "Bangsa Teuton",
                type: "Raja Penjarahan & Serbuan Agresif",
                portrait: "img/reg/portrait_v2.png",
                banner: "img/rpage/Teuton1.jpg",
                badge: "Penakluk Liar",
                commander: "Arminius",
                commanderSub: "Panglima Perang Rimba Germania",
                lore: "Bangsa Teuton adalah para petarung liar yang haus kemenangan dan penjarahan. Mereka melancarkan serangan kilat dengan biaya pelatihan prajurit yang sangat murah, menjadikannya momok menakutkan bagi desa-desa tetangga sejak fajar peradaban.",
                traits: [
                    { title: "🪓 Raja Penjarah", desc: "Mampu menjarah sebagian isi gua persembunyian musuh." },
                    { title: "⚡ Clubswinger Murah", desc: "Pasukan penyerbu dengan biaya sangat ekonomis dan pelatihan kilat." },
                    { title: "🛡️ Paladin Pelindung", desc: "Kavaleri bertameng baja dengan pertahanan hebat melawan infantri." },
                    { title: "🧱 Tembok Tanah Ulet", desc: "Sangat sulit dihancurkan oleh senjata pengepung lawan." }
                ],
                units: [
                    { id: 11, name: "Clubswinger" },
                    { id: 12, name: "Spearman" },
                    { id: 13, name: "Axeman" },
                    { id: 14, name: "Scout" },
                    { id: 15, name: "Paladin" },
                    { id: 16, name: "Teutonic Knight" },
                    { id: 17, name: "Ram" },
                    { id: 18, name: "Catapult" },
                    { id: 19, name: "Chief" },
                    { id: 20, name: "Settler" }
                ]
            },
            gauls: {
                name: "Bangsa Galia",
                type: "Pertahanan Perangkap & Kavaleri Kilat",
                portrait: "img/reg/portrait_v3.png",
                banner: "img/rpage/Gaul1.jpg",
                badge: "Pelindung Damai",
                commander: "Vercingetorix",
                commanderSub: "Pemimpin Agung Suku-Suku Galia",
                lore: "Bangsa Galia menjunjung tinggi kebebasan dan perdamaian, namun jangan pernah meremehkan pertahanan mereka. Dipersenjatai perangkap pemburu yang mampu menawan penyerang hidup-hidup dan kavaleri tercepat di dunia, Galia adalah pilihan sempurna bagi jenderal ahli strategi.",
                traits: [
                    { title: "🪤 Perangkap Rahasia", desc: "Menjebak dan mengurung prajurit musuh yang menyerang desa." },
                    { title: "⚡ Theutates Thunder", desc: "Kavaleri tercepat di Travian untuk penyerangan kilat." },
                    { title: "🌾 Gua Kapasitas 2x", desc: "Menyembunyikan sumber daya dua kali lipat lebih banyak." },
                    { title: "🛡️ Phalanx Serba Bisa", desc: "Pasukan pertahanan awal yang murah dan tangguh." }
                ],
                units: [
                    { id: 21, name: "Phalanx" },
                    { id: 22, name: "Swordsman" },
                    { id: 23, name: "Pathfinder" },
                    { id: 24, name: "Theutates Thunder" },
                    { id: 25, name: "Druidrider" },
                    { id: 26, name: "Haeduan" },
                    { id: 27, name: "Ram" },
                    { id: 28, name: "Trebuchet" },
                    { id: 29, name: "Chieftain" },
                    { id: 30, name: "Settler" }
                ]
            },
            egyptians: {
                name: "Kerajaan Mesir",
                type: "Raksasa Ekonomi & Pertahanan Benteng",
                portrait: "img/reg/portrait_v6.png",
                banner: "img/rpage/Egyptians1.jpg",
                badge: "Kekayaan Nil",
                commander: "Ramses II",
                commanderSub: "Firaun Penakluk Dinasti Sungai Nil",
                lore: "Peradaban Sungai Nil yang agung memiliki kekayaan sumber daya melimpah dan tradisi pembangunan benteng yang kokoh. Mesir adalah raksasa ekonomi yang mampu menyuplai perang jangka panjang dengan sistem pasokan air dan irigasi mereka.",
                traits: [
                    { title: "💧 Pusat Air Nil", desc: "Meningkatkan produksi gandum desa oasis secara masif." },
                    { title: "🛡️ Ash Warden", desc: "Prajurit penjaga berdisiplin tinggi penangkal kavaleri serbu." },
                    { title: "🏹 Resheph Chariot", desc: "Kereta perang beroda cepat dengan kapasitas angkut besar." },
                    { title: "🧱 Dinding Batu Kokoh", desc: "Pertahanan desa tahan banting dari kepungan musuh." }
                ],
                units: [
                    { id: 51, name: "Slave Militia" },
                    { id: 52, name: "Ash Warden" },
                    { id: 53, name: "Khopesh Warrior" },
                    { id: 54, name: "Sopdu Explorer" },
                    { id: 55, name: "Anhur Guard" },
                    { id: 56, name: "Resheph Chariot" },
                    { id: 57, name: "Ram" },
                    { id: 58, name: "Stone Catapult" },
                    { id: 59, name: "Nomarch" },
                    { id: 60, name: "Settler" }
                ]
            },
            huns: {
                name: "Bangsa Hun",
                type: "Nomaden Kavaleri Pembantai",
                portrait: "img/reg/portrait_v7.png",
                banner: "img/rpage/Huns1.jpg",
                badge: "Teror Stepa",
                commander: "Attila the Hun",
                commanderSub: "Cambuk Para Dewa di Padang Stepa",
                lore: "Kaum nomaden dari padang rumput tak berujung yang ditakuti seluruh benua. Pasukan berkuda Hun bergerak laksana badai gurun, meluluhlantakkan garis pertahanan musuh sebelum mereka sempat menyadari apa yang terjadi.",
                traits: [
                    { title: "🐎 Kavaleri Stepa", desc: "Steppe Rider dan Marauder adalah teror bergerak di medan terbuka." },
                    { title: "🏹 Pemanah Berkuda", desc: "Menembakkan hujan anak panah sambil bermanuver lincah." },
                    { title: "🏕️ Kemah Komando", desc: "Mengurangi moral dan efisiensi pertahanan benteng lawan." },
                    { title: "💨 Mobilitas Tertinggi", desc: "Pasukan penyerbu dengan efisiensi penjarahan kilat." }
                ],
                units: [
                    { id: 61, name: "Mercenary" },
                    { id: 62, name: "Bowman" },
                    { id: 63, name: "Spotter" },
                    { id: 64, name: "Steppe Rider" },
                    { id: 65, name: "Marksman" },
                    { id: 66, name: "Marauder" },
                    { id: 67, name: "Ram" },
                    { id: 68, name: "Catapult" },
                    { id: 69, name: "Logades" },
                    { id: 70, name: "Settler" }
                ]
            },
            spartans: {
                name: "Bangsa Sparta",
                type: "Ksatria Perisai Baja Tak Tergoyahkan",
                portrait: "img/reg/portrait_v8.png",
                banner: "img/rpage/Spartans1.jpg",
                badge: "Prajurit Sejati",
                commander: "Leonidas",
                commanderSub: "Raja Pertahanan Abadi Thermopylae",
                lore: "Lahir dan ditempa hanya untuk satu tujuan: kemenangan di medan tempur. Setiap pejuang Sparta adalah benteng berjalan yang tidak mengenal kata mundur, dipersenjatai perisai perunggu rapat dan tombak panjang penembus baju zirah.",
                traits: [
                    { title: "🛡️ Phalanx Perisai Baja", desc: "Formasi pertahanan rapat yang kebal hantaman kavaleri." },
                    { title: "⚔️ Spartan Champion", desc: "Pendekar elit dengan daya tempur individual tertinggi." },
                    { title: "🗡️ Shield Breaker", desc: "Menghancurkan formasi pertahanan musuh dengan tebasan bertenaga." },
                    { title: "🏛️ Akademi Agoge", desc: "Menempa prajurit dengan moral baja pantang menyerah." }
                ],
                units: [
                    { id: 71, name: "Hoplite" },
                    { id: 72, name: "Sentinel" },
                    { id: 73, name: "Shield Breaker" },
                    { id: 74, name: "Hippeus" },
                    { id: 75, name: "Theban Lancer" },
                    { id: 76, name: "Spartan Champion" },
                    { id: 77, name: "Battering Ram" },
                    { id: 78, name: "Ballista" },
                    { id: 79, name: "Ephor" },
                    { id: 80, name: "Settler" }
                ]
            },
            vikings: {
                name: "Bangsa Viking",
                type: "Penjelajah Laut & Berserker Buas",
                portrait: "img/reg/portrait_v9.png",
                banner: "img/rpage/Vikings1.jpg",
                badge: "Serbuan Badai Es",
                commander: "Ragnar Lothbrok",
                commanderSub: "Raja Penakluk Samudra Es Skandinavia",
                lore: "Para penakluk samudra es utara yang mengarungi badai laut dengan kapal naga Drakkar. Keberanian Viking melegenda di seluruh penjuru dunia karena para Berserker yang mengamuk tanpa takut mati demi menyambut kejayaan Valhalla.",
                traits: [
                    { title: "🪓 Berserker Rage", desc: "Serangan infanteri buas yang sanggup meremukkan formasi apa pun." },
                    { title: "🛡️ Shieldmaiden", desc: "Barisan perisai wanita perkasa dengan pertahanan seimbang." },
                    { title: "🌊 Kapal Penjarah", desc: "Perjalanan laut dan ekspedisi pesisir yang sangat cepat." },
                    { title: "🍺 Balai Mead", desc: "Meningkatkan moral bertarung pasukan dalam pertempuran akbar." }
                ],
                units: [
                    { id: 81, name: "Thrall" },
                    { id: 82, name: "Shieldmaiden" },
                    { id: 83, name: "Berserker" },
                    { id: 84, name: "Raider Scout" },
                    { id: 85, name: "Hirdman" },
                    { id: 86, name: "Jarl Raider" },
                    { id: 87, name: "Log Battering Ram" },
                    { id: 88, name: "War Catapult" },
                    { id: 89, name: "Jarl" },
                    { id: 90, name: "Settler" }
                ]
            }
        };

        // Faction Switching Logic
        document.querySelectorAll('.faction-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const key = this.getAttribute('data-faction');
                if (!factionRegistry[key]) return;

                document.querySelectorAll('.faction-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const data = factionRegistry[key];
                
                // Update Hero Stage Preview
                document.getElementById('heroCommanderImg').src = data.portrait;
                document.getElementById('heroBannerImg').src = data.banner;
                document.getElementById('heroCommanderTitle').textContent = data.commander;
                document.getElementById('heroCommanderSub').textContent = data.commanderSub;

                // Update War Council Arena
                document.getElementById('councilTribeName').textContent = data.name;
                document.getElementById('councilTribeType').textContent = data.type;
                document.getElementById('councilPortraitImg').src = data.portrait;
                document.getElementById('councilBannerImg').src = data.banner;
                document.getElementById('councilFactionBadge').textContent = data.badge;
                document.getElementById('councilTribeLore').textContent = data.lore;

                // Render Traits
                const traitsGrid = document.getElementById('councilTraitsGrid');
                traitsGrid.innerHTML = '';
                data.traits.forEach(t => {
                    const card = document.createElement('div');
                    card.className = 'council-trait-card';
                    card.innerHTML = `<div class="council-trait-title">${t.title}</div><div class="council-trait-desc">${t.desc}</div>`;
                    traitsGrid.appendChild(card);
                });

                // Render Units Strip
                const troopStrip = document.getElementById('councilTroopStrip');
                troopStrip.innerHTML = '';
                data.units.forEach(u => {
                    const img = document.createElement('img');
                    img.src = `img/un/u/${u.id}.gif`;
                    img.alt = u.name;
                    img.title = u.name;
                    img.className = 'sprite-trooper';
                    troopStrip.appendChild(img);
                });
            });
        });

        // Mobile Nav Drawer Logic
        function openMobileMenu() {
            const drawer = document.getElementById('mobileMenuPanel');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (drawer && backdrop) {
                drawer.classList.add('open');
                backdrop.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileMenu() {
            const drawer = document.getElementById('mobileMenuPanel');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (drawer && backdrop) {
                drawer.classList.remove('open');
                backdrop.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        const mobileBtn = document.getElementById('mobileMenuBtn');
        if (mobileBtn) {
            mobileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const drawer = document.getElementById('mobileMenuPanel');
                if (drawer && drawer.classList.contains('open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            });
        }

        // Lightbox Functions
        function triggerLightbox(imgSrc, title, desc) {
            const modal = document.getElementById('warLightboxModal');
            document.getElementById('lightboxModalImg').src = imgSrc;
            document.getElementById('lightboxModalTitle').textContent = title;
            document.getElementById('lightboxModalDesc').textContent = desc;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox(e) {
            const modal = document.getElementById('warLightboxModal');
            if (modal) {
                modal.classList.remove('active');
            }
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
                closeMobileMenu();
            }
        });

        // Smooth scroll for anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId && targetId.length > 1) {
                    const targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        targetEl.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });
    </script>
</body>
</html>
