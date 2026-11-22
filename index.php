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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" type="text/css" href="gpack/travian/landing_v3.css?v=20261122_v3" />
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
                <li><a href="#characters" class="war-nav-link">Suku &amp; Panglima</a></li>
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
                <li><a href="#characters" class="mobile-nav-link" onclick="closeMobileMenu()"><span>👑</span> Suku &amp; Panglima</a></li>
                <li><a href="#chronicles" class="mobile-nav-link" onclick="closeMobileMenu()"><span>📢</span> Warta Server</a></li>
                <li><a href="#gallery" class="mobile-nav-link" onclick="closeMobileMenu()"><span>🗺️</span> Peta Tempur</a></li>
                <li><a href="https://github.com/Shadowss/TravianZ/discussions" target="_blank" rel="noopener noreferrer" class="mobile-nav-link" onclick="closeMobileMenu()"><span>💬</span> Komunitas Forum ↗</a></li>
            </ul>
        </div>
    </div>

    <main class="war-portal-viewport">
    <!-- Hero Stage: Cinematic Lost in Random Style -->
    <section class="hero-stage" id="heroStage">
        <canvas id="warEmbersCanvas" class="hero-embers-canvas"></canvas>
        <div class="hero-backdrop-glow"></div>
        <div class="portal-container hero-grid">
            <!-- Left Column: Manifesto & Action -->
            <div class="hero-dispatch">
                <div class="imperial-seal-pill">
                    <span class="seal-gem"></span>
                    <span class="seal-text">DOKTRIN EXPANSION 2026: NUSANTARA &amp; JAGAT TERLARANG</span>
                </div>

                <h1 class="hero-headline">
                    PIMPIN PERADABAN &mdash;<br />
                    <span class="crimson-shimmer">TAKDIRKAN SEJARAH DUNIA.</span>
                </h1>

                <p class="hero-manifesto">
                    Jelajahi era paling sengit dalam sejarah <strong>Travian</strong>. Pimpin armada bahari adidaya <strong>Suku Nusantara</strong>, taklukkan ekspedisi <strong>Forward Camp Aliansi</strong> melawan Sarang Bandit &amp; World Boss, bertarung di bawah amukan <strong>Cuaca Ekstrem</strong>, bentengi desa dari <strong>Wabah Sampar</strong>, dan kuasai <strong>Pasar Gelap</strong> bersama <strong>Tentara Bayaran Netral</strong>!
                </p>

                <div class="vintage-flourish">
                    <span class="flourish-line"></span>
                    <span class="flourish-diamond">&#9671;</span>
                    <span class="flourish-line"></span>
                </div>

                <div class="hero-cta-cluster">
                    <?php if ($isLoggedIn): ?>
                        <a href="dorf1.php" class="war-btn-pill-crimson">🏰 Kembali Memimpin Desa (<?php echo $loggedUser; ?>)</a>
                        <a href="#characters" class="war-btn-pill-outline">📜 Dewan Suku &amp; Panglima</a>
                    <?php else: ?>
                        <a href="anmelden.php" class="war-btn-pill-crimson">⚔️ Rekrut Akun (Mulai Main Gratis)</a>
                        <a href="login.php" class="war-btn-pill-outline">🛡️ Masuk Medan Tempur</a>
                    <?php endif; ?>
                </div>

                <!-- Laurel Wreath Badges (Indie Game Festival Stamp Style) -->
                <div class="laurel-stamps-row">
                    <div class="laurel-stamp-badge">
                        <svg class="laurel-wreath-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M7 10c-.5-1-1.5-2-3-2s-2.5 1-3 2c.5 1 1.5 2 3 2s2.5-1 3-2zM17 10c.5-1 1.5-2 3-2s2.5 1 3 2-.5 2-2 2-2.5-1-3-2zM9 16c-.5-1-1.5-2-3-2s-2.5 1-3 2c.5 1 1.5 2 3 2s2.5-1 3-2zM15 16c.5-1 1.5-2 3-2s2.5 1 3 2-.5 2-2 2-2.5-1-3-2zM12 2v20M12 22a6 6 0 0 1-6-6M12 22a6 6 0 0 0 6-6" />
                        </svg>
                        <div class="laurel-stamp-meta">
                            <span class="laurel-stamp-title">Edisi Eksklusif 2026</span>
                            <span class="laurel-stamp-sub">Ekspansi Nusantara</span>
                        </div>
                    </div>
                    <div class="laurel-stamp-badge">
                        <svg class="laurel-wreath-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <div class="laurel-stamp-meta">
                            <span class="laurel-stamp-title">Server Speed 10x</span>
                            <span class="laurel-stamp-sub">PvE Forward Camp &amp; Bosses</span>
                        </div>
                    </div>
                </div>

                <!-- War Intel Live Counters -->
                <div class="intel-ribbon" id="intelRibbon" style="margin-top: 24px;">
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$totalUsers; ?>"><?php echo number_format($totalUsers); ?></div>
                        <div class="intel-label">Total Jenderal</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$onlineUsers; ?>" style="color:#34d399;"><?php echo number_format($onlineUsers); ?></div>
                        <div class="intel-label">Pasukan Siaga</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$totalVillages; ?>"><?php echo number_format($totalVillages); ?></div>
                        <div class="intel-label">Benteng &amp; Desa</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$activeBandits; ?>" style="color:#f87171;"><?php echo number_format($activeBandits); ?></div>
                        <div class="intel-label">Sarang Bandit Aktif</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive 3D Hero Centerpiece Stage -->
            <div class="hero-centerpiece-stage">
                <div class="hero-floating-island" id="heroFloatingIsland">
                    <!-- Floating Runic Cards -->
                    <div class="floating-runic-card card-pos-1" title="Cetbang Artileri">&#9876;</div>
                    <div class="floating-runic-card card-pos-2" title="Lumbung Nusantara">&#127806;</div>
                    <div class="floating-runic-card card-pos-3" title="Takhta Peradaban">&#128081;</div>

                    <img src="img/landing_v3/hero_commander.png" alt="Panglima Nusantara Raden Wijaya" class="hero-figure-img" id="heroCommanderImg" />
                    <div class="hero-stone-slab"></div>
                    <div class="hero-slab-shadow"></div>
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
                            Butuh bala bantuan garnisun instan tanpa menunggu antrean barak atau riset teknologi di akademi? Sewa serdadu bayaran berpengalaman di Tab 6 Pasar menggunakan koin Silver: <strong>Garda Zirah Besi</strong> (perisai infantri seimbang), <strong>Pemanah Tembok Aegis</strong> (anti-infanteri benteng), <strong>Ksatria Perisai Templar</strong> (kavaleri anti-kuda), dan <strong>Penjaga Gerbang Praetorian</strong> (serdadu elit benteng). Otomatis memperkuat pertahanan desa dari serbuan musuh dengan sistem desersi otomatis saat kelaparan gandum!
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

    <!-- 3D Figurine Characters & Civilizations Showcase -->
    <section class="characters-stage" id="characters">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">👑 DEWAN PERADABAN &amp; 8 SUKU BESAR</span>
                <h2 class="heraldry-title">Pilih Panglima &amp; Tentukan Takdir Kerajaan Anda</h2>
                <p class="heraldry-subtitle">
                    Setiap suku memiliki karakteristik doktrin perang, keunggulan pasukan militer, dan efek sinergi ekspedisi aliansi yang unik.
                </p>
            </div>

            <!-- Faction Selector Tabs -->
            <div class="faction-tabs-bar" style="margin-bottom: 24px;">
                <button class="faction-tab-btn active" data-faction="nusantara" onclick="switchFigurine('nusantara')">🇮🇩 Nusantara</button>
                <button class="faction-tab-btn" data-faction="vikings" onclick="switchFigurine('vikings')">🪓 Viking</button>
                <button class="faction-tab-btn" data-faction="natars" onclick="switchFigurine('natars')">🔮 Natars (The Queen)</button>
                <button class="faction-tab-btn" data-faction="romans" onclick="switchFigurine('romans')">🏛️ Romawi</button>
                <button class="faction-tab-btn" data-faction="teutons" onclick="switchFigurine('teutons')">⚔️ Teuton</button>
                <button class="faction-tab-btn" data-faction="gauls" onclick="switchFigurine('gauls')">🛡️ Galia</button>
                <button class="faction-tab-btn" data-faction="egyptians" onclick="switchFigurine('egyptians')">☀️ Mesir</button>
                <button class="faction-tab-btn" data-faction="huns" onclick="switchFigurine('huns')">🏹 Hun</button>
                <button class="faction-tab-btn" data-faction="spartans" onclick="switchFigurine('spartans')">🛡️ Sparta</button>
            </div>

            <!-- Turntable Carousel Layout -->
            <div class="characters-carousel-wrap">
                <!-- Left: 3D Figurine on Pedestal Spotlight -->
                <div class="figurine-stage-col">
                    <div class="figurine-spotlight"></div>
                    <div class="figurine-card-active" id="figurineDisplayCard">
                        <img src="img/landing_v3/fig_nusantara.png" alt="Figurine Karakter" class="figurine-main-img" id="figurineImg" />
                        <div class="figurine-shadow-base"></div>
                    </div>
                </div>

                <!-- Center: Lore, Traits, Military Lineup -->
                <div class="figurine-lore-col">
                    <div class="figurine-kicker">
                        <span id="figurineBadge">Eksklusif: Kemaharajaan Maritim</span>
                    </div>
                    <h3 class="figurine-name-title" id="figurineName">RADEN WIJAYA</h3>
                    <div class="figurine-sub-title" id="figurineSub">Panglima Tertinggi Kerajaan Maritim Nusantara</div>

                    <div class="vintage-flourish" style="margin: 8px 0 16px;">
                        <span class="flourish-line"></span>
                        <span class="flourish-diamond">&#9671;</span>
                        <span class="flourish-line"></span>
                    </div>

                    <p class="figurine-story-p" id="figurineLore">
                        Kerajaan Maritim Nusantara berdiri megah di atas gugusan kepulauan khatulistiwa yang kaya rempah. Dengan perpaduan armada kapal Jung raksasa, lumbung pangan yang melimpah, artileri meriam Cetbang, serta barisan Gajah Bhayangkara yang disegani, Nusantara adalah peradaban adidaya maritim yang tangguh dalam pertahanan dan mematikan dalam serbuan amfibi.
                    </p>

                    <div class="figurine-traits-row" id="figurineTraits">
                        <div class="figurine-trait-pill">
                            <div class="figurine-trait-label">🌾 Lumbung Kemakmuran</div>
                            <div class="figurine-trait-desc">Kapasitas penyimpanan lumbung pangan dan perlindungan komoditas pangan lebih unggul.</div>
                        </div>
                        <div class="figurine-trait-pill">
                            <div class="figurine-trait-label">💥 Artileri Cetbang</div>
                            <div class="figurine-trait-desc">Meriam perunggu putar yang sanggup meremukkan dinding benteng lawan dengan presisi tinggi.</div>
                        </div>
                        <div class="figurine-trait-pill">
                            <div class="figurine-trait-label">🐘 Gajah Bhayangkara</div>
                            <div class="figurine-trait-desc">Monster lapis baja pengobrak-abrik barisan infanteri musuh dalam pertempuran frontal.</div>
                        </div>
                        <div class="figurine-trait-pill">
                            <div class="figurine-trait-label">⛵ Jung &amp; Kuda Laut</div>
                            <div class="figurine-trait-desc">Kekuatan mobilitas armada cepat untuk ekspedisi dan pengintaian pesisir.</div>
                        </div>
                    </div>

                    <div class="council-roster-footer">
                        <span class="roster-label">Barisan Pasukan Militer:</span>
                        <div class="council-icons-strip" id="figurineTroopStrip">
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
                        <a href="anmelden.php" class="war-btn-pill-crimson" style="margin-left:auto; padding: 10px 20px; font-size: 13px;">⚔️ Pilih Suku Ini</a>
                    </div>
                </div>

                <!-- Right: Vertical Slide Indicator Rail -->
                <div class="figurine-rail-col">
                    <div class="rail-track">
                        <div class="rail-fill" id="railFill" style="height: 33%;"></div>
                    </div>
                    <div class="rail-step-dot active" data-step="0" onclick="switchFigurineByIndex(0)" title="Nusantara"></div>
                    <div class="rail-step-dot" data-step="1" onclick="switchFigurineByIndex(1)" title="Viking"></div>
                    <div class="rail-step-dot" data-step="2" onclick="switchFigurineByIndex(2)" title="The Queen"></div>
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

    </main>

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
        // Faction & 3D Figurine dataset
        const figurineKeys = ['nusantara', 'vikings', 'natars', 'romans', 'teutons', 'gauls', 'egyptians', 'huns', 'spartans'];
        let currentFigurineIndex = 0;

        const factionRegistry = {
            nusantara: {
                name: "Kerajaan Nusantara",
                title: "RADEN WIJAYA",
                type: "Peradaban Maritim & Agrikultur",
                figurine: "img/landing_v3/fig_nusantara.png",
                badge: "Eksklusif: Kemaharajaan Maritim",
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
            vikings: {
                name: "Bangsa Viking",
                title: "BERGYR THE BERSERKER",
                type: "Penjelajah Laut & Berserker Buas",
                figurine: "img/landing_v3/fig_viking.png",
                badge: "Teror Samudra Es",
                commanderSub: "Panglima Perang Barbarik & Armada Viking",
                lore: "Para penakluk samudra es utara yang mengarungi badai laut dengan kapal naga Drakkar. Keberanian Viking melegenda di seluruh penjuru dunia karena para Berserker yang mengamuk tanpa takut mati, melancarkan serbuan penghancur armor benteng seketika.",
                traits: [
                    { title: "🪓 Berserker Sunder", desc: "Menghancurkan 15% armor pertahanan musuh pada ronde benturan pertama." },
                    { title: "🐺 War Cry Demoralize", desc: "Teriakan perang melumpuhkan moral dan menurunkan serangan lawan." },
                    { title: "💰 Silver Hoard", desc: "Bonus perolehan Koin Perak ekstra dari setiap jarahan sarang bandit." },
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
            },
            natars: {
                name: "Kekaisaran Natars",
                title: "THE QUEEN OF NATARS",
                type: "Penguasa Takhta Kuno & Wonder of the World",
                figurine: "img/landing_v3/fig_queen.png",
                badge: "Arsitek Keajaiban Dunia",
                commanderSub: "Ratu Kuno Penguasa Jagat Terlarang",
                lore: "Kekaisaran Natars adalah peradaban purba yang tertidur ribuan tahun di balik kabut dunia terlarang. Di bawah titah The Queen dengan orb kegelapan dan pedang kutukannya, pasukan Natars mengawal cetak biru Keajaiban Dunia (Wonder of the World) dengan barisan titan mistis.",
                traits: [
                    { title: "🏛️ Wonder of the World", desc: "Pemegang kunci pembangunan Keajaiban Dunia penentu kemenangan akhir server." },
                    { title: "🔮 Dark Orb Supremacy", desc: "Sihir kuno yang membatalkan perisai pertahanan garis depan penyerang." },
                    { title: "👹 Pasukan Titan Kuno", desc: "Garnisun gajah raksasa dan monster penjaga yang kebal intimidasi." },
                    { title: "🛡️ Benteng Abadi", desc: "Tembok raksasa Natars dengan bonus pertahanan tertinggi di jagat Travian." }
                ],
                units: [
                    { id: 41, name: "Pikeman" },
                    { id: 42, name: "Thorneyed" },
                    { id: 43, name: "Guardsman" },
                    { id: 44, name: "Birds of Prey" },
                    { id: 45, name: "Axerider" },
                    { id: 46, name: "Natarian Knight" },
                    { id: 47, name: "War Elephant" },
                    { id: 48, name: "Ballista" },
                    { id: 49, name: "Natarian Emperor" },
                    { id: 50, name: "Natarian Settler" }
                ]
            },
            romans: {
                name: "Kekaisaran Romawi",
                title: "JULIUS CAESAR",
                type: "Infanteri Elit & Arsitektur Canggih",
                figurine: "img/reg/portrait_v1.png",
                badge: "Kekaisaran Klasik",
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
                title: "ARMINIUS",
                type: "Raja Penjarahan & Serbuan Agresif",
                figurine: "img/reg/portrait_v2.png",
                badge: "Penakluk Liar",
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
                title: "VERCINGETORIX",
                type: "Pertahanan Perangkap & Kavaleri Kilat",
                figurine: "img/reg/portrait_v3.png",
                badge: "Pelindung Damai",
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
                title: "RAMSES II",
                type: "Raksasa Ekonomi & Pertahanan Benteng",
                figurine: "img/reg/portrait_v6.png",
                badge: "Kekayaan Nil",
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
                title: "ATTILA THE HUN",
                type: "Nomaden Kavaleri Pembantai",
                figurine: "img/reg/portrait_v7.png",
                badge: "Teror Stepa",
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
                title: "LEONIDAS",
                type: "Ksatria Perisai Baja Tak Tergoyahkan",
                figurine: "img/reg/portrait_v8.png",
                badge: "Prajurit Sejati",
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
            }
        };

        function switchFigurine(key) {
            const data = factionRegistry[key];
            if (!data) return;

            const idx = figurineKeys.indexOf(key);
            if (idx !== -1) currentFigurineIndex = idx;

            // Update Tab buttons
            document.querySelectorAll('.faction-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-faction') === key);
            });

            // Update Vertical Rail Dots
            document.querySelectorAll('.rail-step-dot').forEach((dot, dIdx) => {
                dot.classList.toggle('active', dIdx === currentFigurineIndex);
            });

            const railFill = document.getElementById('railFill');
            if (railFill) {
                const totalVisible = 3;
                const pct = Math.min(100, Math.max(20, ((Math.min(currentFigurineIndex, 2) + 1) / totalVisible) * 100));
                railFill.style.height = pct + '%';
            }

            // Animate Figurine Card
            const card = document.getElementById('figurineDisplayCard');
            const img = document.getElementById('figurineImg');
            if (card && img) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(15px) scale(0.95)';
                setTimeout(() => {
                    img.src = data.figurine;
                    card.style.transition = 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0) scale(1)';
                }, 150);
            }

            // Update Text Elements
            const nameEl = document.getElementById('figurineName');
            const subEl = document.getElementById('figurineSub');
            const badgeEl = document.getElementById('figurineBadge');
            const loreEl = document.getElementById('figurineLore');

            if (nameEl) nameEl.textContent = data.title || data.name;
            if (subEl) subEl.textContent = data.commanderSub || data.type;
            if (badgeEl) badgeEl.textContent = data.badge;
            if (loreEl) loreEl.textContent = data.lore;

            // Update Traits
            const traitsGrid = document.getElementById('figurineTraits');
            if (traitsGrid && data.traits) {
                traitsGrid.innerHTML = data.traits.map(t => `
                    <div class="figurine-trait-pill">
                        <div class="figurine-trait-label">${t.title}</div>
                        <div class="figurine-trait-desc">${t.desc}</div>
                    </div>
                `).join('');
            }

            // Update Troops Strip
            const troopStrip = document.getElementById('figurineTroopStrip');
            if (troopStrip && data.units) {
                troopStrip.innerHTML = data.units.map(u => `
                    <img src="img/un/u/${u.id}.gif" alt="${u.name}" class="sprite-trooper" title="${u.name}" />
                `).join('');
            }
        }

        function switchFigurineByIndex(idx) {
            if (idx >= 0 && idx < figurineKeys.length) {
                switchFigurine(figurineKeys[idx]);
            }
        }

        // 3D Mouse Parallax Tilt for Hero Island
        (function initHeroTilt() {
            const stage = document.getElementById('heroStage');
            const island = document.getElementById('heroFloatingIsland');
            if (!stage || !island || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            let ticking = false;

            stage.addEventListener('mousemove', (e) => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const rect = stage.getBoundingClientRect();
                        const x = (e.clientX - rect.left) / rect.width - 0.5;
                        const y = (e.clientY - rect.top) / rect.height - 0.5;
                        island.style.transform = `perspective(1000px) rotateY(${x * 16}deg) rotateX(${-y * 14}deg) translateY(-8px)`;
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });

            stage.addEventListener('mouseleave', () => {
                island.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) translateY(0px)';
            });
        })();

        // 1. Sticky Nav Scroll State
        const warNav = document.querySelector('.war-nav');
        if (warNav) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) {
                    warNav.classList.add('scrolled');
                } else {
                    warNav.classList.remove('scrolled');
                }
            }, { passive: true });
        }

        // 3. Mobile Nav Drawer Logic
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

        // 4. Lightbox Functions
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

        // 5. Smooth scroll for anchors with sticky nav offset
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId && targetId.length > 1) {
                    const targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        const headerOffset = 80;
                        const elementPosition = targetEl.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });

        // 6. Real-Time Intel Ribbon CountUp Animation
        function animateCounters() {
            const counters = document.querySelectorAll('.intel-metric[data-target]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
                if (target <= 0) return;
                const duration = 1800; // ms
                const startTime = performance.now();
                const updateNumber = (now) => {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // Ease-out Quad
                    const easeProgress = 1 - (1 - progress) * (1 - progress);
                    const currentVal = Math.floor(easeProgress * target);
                    counter.textContent = currentVal.toLocaleString();
                    if (progress < 1) {
                        requestAnimationFrame(updateNumber);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                requestAnimationFrame(updateNumber);
            });
        }

        const intelRibbon = document.getElementById('intelRibbon');
        if (intelRibbon && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounters();
                        obs.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(intelRibbon);
        } else {
            animateCounters();
        }

        // 7. Interactive 3D Card Tilt (Subtle)
        const tiltCards = document.querySelectorAll('.codex-card, .grand-feature-card');
        tiltCards.forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -4;
                const rotateY = ((x - centerX) / centerX) * 4;
                card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-4px)`;
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });

        // 8. Canvas Particle Embers Engine
        (function initEmbers() {
            const canvas = document.getElementById('warEmbersCanvas');
            if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            let width = (canvas.width = canvas.parentElement.offsetWidth);
            let height = (canvas.height = canvas.parentElement.offsetHeight);

            window.addEventListener('resize', () => {
                if (canvas && canvas.parentElement) {
                    width = canvas.width = canvas.parentElement.offsetWidth;
                    height = canvas.height = canvas.parentElement.offsetHeight;
                }
            }, { passive: true });

            const colors = ['#f59e0b', '#fb923c', '#d97706', '#fcd34d', '#ea580c'];
            const particleCount = 42;
            const particles = [];

            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    radius: Math.random() * 1.8 + 0.6,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    vx: (Math.random() - 0.5) * 0.4,
                    vy: -(Math.random() * 0.8 + 0.3),
                    alpha: Math.random() * 0.7 + 0.2,
                    fade: Math.random() * 0.006 + 0.003
                });
            }

            let animationFrameId;
            let isTabActive = true;

            document.addEventListener('visibilitychange', () => {
                isTabActive = !document.hidden;
                if (isTabActive) render();
            });

            function render() {
                if (!isTabActive) return;
                ctx.clearRect(0, 0, width, height);

                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.x += p.vx + Math.sin(p.y * 0.015) * 0.2;
                    p.y += p.vy;
                    p.alpha -= p.fade;

                    if (p.alpha <= 0 || p.y < -10) {
                        p.x = Math.random() * width;
                        p.y = height + 10;
                        p.alpha = Math.random() * 0.7 + 0.2;
                        p.vy = -(Math.random() * 0.8 + 0.3);
                    }

                    ctx.save();
                    ctx.globalAlpha = Math.max(0, p.alpha);
                    ctx.fillStyle = p.color;
                    ctx.shadowBlur = 6;
                    ctx.shadowColor = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }

                animationFrameId = requestAnimationFrame(render);
            }

            render();
        })();
    </script>
</body>
</html>
