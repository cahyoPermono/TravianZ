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
                <div class="hero-header-row">
                    <div class="imperial-seal-pill">
                        <span class="seal-gem"></span>
                        <span class="seal-text">DOKTRIN EXPANSION 2026: NUSANTARA &amp; JAGAT TERLARANG</span>
                    </div>
                    <button id="warAudioToggle" class="war-audio-pill" onclick="toggleWarAudio()" type="button" title="Aktifkan Suasana Musik Perang">
                        <span class="audio-icon">🎵</span>
                        <span id="audioText">Musik Suasana Perang</span>
                        <span class="audio-bars">
                            <span class="bar"></span>
                            <span class="bar"></span>
                            <span class="bar"></span>
                        </span>
                    </button>
                </div>
                <div class="realm-live-badge-strip">
                    <span class="realm-status-dot"></span>
                    <span>Dunia 1 Aktif</span> &bull; 
                    <span class="realm-stat-highlight">Speed 10x</span> &bull; 
                    <span class="realm-stat-weather">Musim: Kemarau (Panen +15%)</span> &bull; 
                    <span>Ekspedisi: Siaga</span>
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
                        <div class="intel-metric" data-target="<?php echo (int)$onlineUsers; ?>" style="color:#047857;"><?php echo number_format($onlineUsers); ?></div>
                        <div class="intel-label">Pasukan Siaga</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$totalVillages; ?>"><?php echo number_format($totalVillages); ?></div>
                        <div class="intel-label">Benteng &amp; Desa</div>
                    </div>
                    <div class="intel-card">
                        <div class="intel-metric" data-target="<?php echo (int)$activeBandits; ?>" style="color:#b91c1c;"><?php echo number_format($activeBandits); ?></div>
                        <div class="intel-label">Sarang Bandit Aktif</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive 3D Hero Centerpiece Stage -->
            <div class="hero-centerpiece-stage">
                <div class="hero-floating-island" id="heroFloatingIsland">
                    <!-- Rotating Celestial Astrolabe Halo -->
                    <div class="hero-astrolabe-ring"></div>

                    <!-- Floating Ancient Runic Monoliths -->
                    <div class="floating-runic-card card-pos-1" title="Cetbang Artileri">&#9876;</div>
                    <div class="floating-runic-card card-pos-2" title="Lumbung Nusantara">&#127806;</div>
                    <div class="floating-runic-card card-pos-3" title="Takhta Peradaban">&#128081;</div>
                    <div class="floating-amber-shard shard-1"></div>
                    <div class="floating-amber-shard shard-2"></div>

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
                    <!-- Feature 2: Bandit Camps PvE with Interactive Tier Selector -->
                    <div class="codex-card codex-card-bandit">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#b91c1c;">🎯</div>
                            <span class="codex-tag tag-bandit">Ekspedisi PvE &amp; World Boss</span>
                        </div>
                        <h3 class="codex-card-title">Radar Sarang Bandit &amp; World Boss</h3>
                        <p class="codex-card-body">
                            Petakan dunia kuno dan hancurkan komplotan penyamun liar! Pilih peringkat sarang untuk menguji daya serbu ekspedisi aliansi Anda:
                        </p>
                        <!-- Interactive Tier Switcher -->
                        <div class="bandit-tier-switch-bar">
                            <button class="tier-tab-btn" onclick="selectBanditTier(1)" id="tierBtn1" type="button">Tier 1</button>
                            <button class="tier-tab-btn" onclick="selectBanditTier(2)" id="tierBtn2" type="button">Tier 2</button>
                            <button class="tier-tab-btn" onclick="selectBanditTier(3)" id="tierBtn3" type="button">Tier 3</button>
                            <button class="tier-tab-btn active" onclick="selectBanditTier(4)" id="tierBtn4" type="button">Tier 4 Boss</button>
                        </div>
                        <div class="bandit-preview-row" id="banditPreviewBox">
                            <img src="img/bandit/camp_tier_4.png" alt="World Boss Sarang Naga" class="bandit-preview-img" id="banditCampImg" />
                            <div style="flex:1;">
                                <div id="banditTierTitle" style="font-size:13.5px; font-weight:800; color:#1e293b;">World Boss Tier 4: Sarang Naga</div>
                                <div id="banditTierSub" style="font-size:12px; color:#b91c1c; font-weight:600;">Tantangan raid kolosal dengan hadiah Silver berlimpah!</div>
                                <div id="banditTierRewards" class="bandit-tier-rewards">⚔️ Pasukan Minimal: 15,000 &bull; Hadiah: 12,000 Silver, 5,000 EXP, 2,500 CP</div>
                            </div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#b91c1c;"></span> 4 Peringkat Sarang Bandit berimbang (Tier 1 s/d Tier 4)</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#b91c1c;"></span> Reward Hero EXP, Poin Budaya (CP), dan Koin Silver</div>
                        </div>
                    </div>

                    <!-- Feature 3: Dynamic Weather Engine with Interactive Simulator -->
                    <div class="codex-card codex-card-weather">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#0369a1;">🌧️</div>
                            <span class="codex-tag tag-weather">Mesin Iklim Real-Time</span>
                        </div>
                        <h3 class="codex-card-title">Dinamika Cuaca &amp; Bencana Alam</h3>
                        <p class="codex-card-body">
                            Medan tempur dipengaruhi atmosfer alam nyata. Uji simulasi cuaca untuk melihat efek pergerakan pasukan secara langsung:
                        </p>
                        <!-- Interactive Weather Simulator -->
                        <div class="weather-chips-cluster">
                            <button class="weather-chip active" id="wChipKemarau" onclick="selectWeather('kemarau')" type="button">☀️ Kemarau</button>
                            <button class="weather-chip" id="wChipHujan" onclick="selectWeather('hujan')" type="button">🌧️ Hujan Badai</button>
                            <button class="weather-chip" id="wChipKabut" onclick="selectWeather('kabut')" type="button">🌫️ Kabut Tebal</button>
                            <button class="weather-chip" id="wChipSalju" onclick="selectWeather('salju')" type="button">❄️ Badai Salju</button>
                        </div>
                        <div class="weather-tactical-display" id="weatherDisplay">
                            <div class="w-status-name" id="wStatusName">☀️ Musim Kemarau Berkelanjutan</div>
                            <div class="w-status-desc" id="wStatusDesc">Panen gandum melimpah (+20%), cadangan air berkurang, kecepatan kavaleri normal.</div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#0369a1;"></span> Mempengaruhi kecepatan infanteri, kavaleri, dan artileri</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#0369a1;"></span> Fleksibilitas taktik penyerangan berdasarkan ramalan cuaca</div>
                        </div>
                    </div>

                    <!-- Feature 4: Plague & Interactive Quarantine Gate Switch -->
                    <div class="codex-card codex-card-plague">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#7e22ce;">☣️</div>
                            <span class="codex-tag tag-plague">Sanitasi &amp; Karantina Desa</span>
                        </div>
                        <h3 class="codex-card-title">Epidemi Sampar &amp; Karantina Gerbang</h3>
                        <p class="codex-card-body">
                            Ancaman biologis yang sanggup meruntuhkan kekaisaran dalam hitungan hari! Uji coba kunci gerbang untuk memutus rantai transmisi:
                        </p>
                        <!-- Interactive Quarantine Switch -->
                        <div class="quarantine-interactive-box">
                            <div class="quarantine-bar-head">
                                <span class="q-label" id="qLabel">Pintu Gerbang: <strong style="color:#047857;">TERBUKA</strong></span>
                                <span class="q-risk-meter" id="qRisk">Risiko Sampar: 85% Bahaya</span>
                            </div>
                            <button class="quarantine-switch-btn" id="qSwitchBtn" onclick="toggleVillageQuarantine()" type="button">
                                🔒 Kunci Karantina Gerbang Desa
                            </button>
                            <div class="quarantine-note" id="qNote">Gerbang terbuka memicu penyebaran sampar dari desa tetangga dalam radius 7 kotak.</div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#7e22ce;"></span> Transmisi infeksi dinamis antar desa tetangga</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#7e22ce;"></span> Gerbang Karantina Desa, Tabib Tradisional, dan Penawar Selundupan</div>
                        </div>
                    </div>

                    <!-- Feature 5: The Black Market with Interactive Calculator -->
                    <div class="codex-card codex-card-market">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#b45309;">🏴‍☠️</div>
                            <span class="codex-tag tag-market">Bebas Emas (Tab 5 Pasar)</span>
                        </div>
                        <h3 class="codex-card-title">Pasar Gelap Para Penyelundup</h3>
                        <p class="codex-card-body">
                            Tukar sumber daya berlebih tanpa sepeser pun koin Emas! Geser jumlah sumber daya untuk menghitung hasil penukaran instan:
                        </p>
                        <!-- Interactive Resource Laundering Calculator -->
                        <div class="market-laundering-widget">
                            <div class="m-calc-top">
                                <span>Simulasi Cuci Sumber Daya:</span>
                                <strong id="mInputAmountText">10,000 Kayu</strong>
                            </div>
                            <input type="range" min="1000" max="50000" step="1000" value="10000" class="market-range-slider" id="mRangeSlider" oninput="updateMarketCalc(this.value)" />
                            <div class="m-calc-bottom">
                                <div class="m-res-badge">Diterima: <strong id="mResultAmount">8,500 Gandum</strong></div>
                                <div class="m-fee-badge">Komisi Saudagar: 15% (0 Gold)</div>
                            </div>
                        </div>
                        <div class="bullet-traits-list">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#b45309;"></span> 100% Bebas Gold: cuci sumber daya dengan komisi 15% pedagang gelap</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#b45309;"></span> Tebus peti perbekalan instan (Kayu, Tanah, Besi, Gandum) via Silver</div>
                        </div>
                    </div>

                    <!-- Feature 6: Mercenary Enclaves with Contract Roster -->
                    <div class="codex-card codex-card-mercenary" style="grid-column: 1 / -1;">
                        <div class="codex-card-top">
                            <div class="codex-icon-plate" style="color:#1d4ed8;">🛡️</div>
                            <span class="codex-tag tag-mercenary">Garnisun Instan (Tab 6 Pasar)</span>
                        </div>
                        <h3 class="codex-card-title">Tentara Bayaran Netral (Mercenary Enclaves)</h3>
                        <p class="codex-card-body">
                            Sewa serdadu bayaran veteran seketika menggunakan koin Silver dari membasmi bandit untuk membentengi desa dari serbuan musuh:
                        </p>
                        <!-- Interactive Mercenary Cards -->
                        <div class="mercenary-interactive-cards">
                            <div class="merc-contract-card">
                                <div class="merc-title">🛡️ Garda Zirah Besi</div>
                                <div class="merc-desc">Infanteri lapis baja penahan serangan frontal.</div>
                                <button class="merc-hire-btn" onclick="hireMercenaryFx('Garda Zirah Besi')" type="button">Sewa &bull; 500 Silver</button>
                            </div>
                            <div class="merc-contract-card">
                                <div class="merc-title">🏹 Pemanah Tembok Aegis</div>
                                <div class="merc-desc">Penembak jitu tembok benteng anti-infanteri.</div>
                                <button class="merc-hire-btn" onclick="hireMercenaryFx('Pemanah Aegis')" type="button">Sewa &bull; 750 Silver</button>
                            </div>
                            <div class="merc-contract-card">
                                <div class="merc-title">🐎 Ksatria Templar</div>
                                <div class="merc-desc">Kavaleri penyerang cepat anti-senjata kepung.</div>
                                <button class="merc-hire-btn" onclick="hireMercenaryFx('Ksatria Templar')" type="button">Sewa &bull; 1,200 Silver</button>
                            </div>
                        </div>
                        <div class="bullet-traits-list mercenary-traits-grid" style="margin-top:12px;">
                            <div class="bullet-item"><span class="bullet-dot" style="background:#1d4ed8;"></span> Kontrak bayaran menggunakan koin Silver hasil berburu</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#1d4ed8;"></span> Menjaga garnisun desa dari serangan &amp; serbuan lawan</div>
                            <div class="bullet-item"><span class="bullet-dot" style="background:#1d4ed8;"></span> Desersi logis saat lumbung gandum desa mengalami kelaparan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3D Figurine Characters & Civilizations Showcase -->
        <!-- Character & Civilization Figurine Showcase (3D Turntable Style) -->
    <section class="characters-stage" id="characters">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">👑 DEWAN PERADABAN &amp; 9 BANGSA BESAR</span>
                <h2 class="heraldry-title">Pilih Panglima &amp; Tentukan Takdir Kerajaan Anda</h2>
                <p class="heraldry-subtitle">
                    Setiap suku memiliki karakteristik doktrin perang, keunggulan pasukan militer, dan efek sinergi ekspedisi aliansi yang unik.
                </p>
            </div>

            <!-- Faction Selector Tabs -->
            <div class="faction-tabs-bar" style="margin-bottom: 28px;">
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
                    <div class="pedestal-runic-ring" id="pedestalRunicRing"></div>
                    <div class="figurine-card-active" id="figurineDisplayCard">
                        <img src="img/landing_v3/fig_nusantara.png" alt="Figurine Karakter" class="figurine-main-img" id="figurineImg" />
                        <div class="figurine-shadow-base"></div>
                    </div>
                </div>

                <!-- Center: Lore, Doctrine Hexagon Radar, Traits, Military Lineup -->
                <div class="figurine-lore-col">
                    <div class="figurine-kicker">
                        <span id="figurineBadge">Eksklusif: Kemaharajaan Maritim</span>
                    </div>
                    <h3 class="figurine-name-title" id="figurineName">RADEN WIJAYA</h3>
                    <div class="figurine-sub-title" id="figurineSub">Panglima Tertinggi Kerajaan Maritim Nusantara</div>

                    <div class="vintage-flourish" style="margin: 8px 0 14px;">
                        <span class="flourish-line"></span>
                        <span class="flourish-diamond">&#9671;</span>
                        <span class="flourish-line"></span>
                    </div>

                    <p class="figurine-story-p" id="figurineLore">
                        Kerajaan Maritim Nusantara berdiri megah di atas gugusan kepulauan khatulistiwa yang kaya rempah. Dengan perpaduan armada kapal Jung raksasa, lumbung pangan yang melimpah, artileri meriam Cetbang, serta barisan Gajah Bhayangkara yang disegani, Nusantara adalah peradaban adidaya maritim yang tangguh dalam pertahanan dan mematikan dalam serbuan amfibi.
                    </p>

                    <!-- Hexagonal Combat Doctrine Radar Chart -->
                    <div class="doctrine-radar-box">
                        <div class="radar-header">
                            <span class="radar-label">⚡ DOKTRIN TEMPUR PERADABAN</span>
                            <span class="radar-score" id="radarTotalScore">Rating: 92/100</span>
                        </div>
                        <div class="radar-chart-container">
                            <div class="radar-svg-wrap">
                                <svg class="radar-svg" viewBox="0 0 200 200">
                                    <!-- Background Web Guidelines -->
                                    <polygon points="100,20 169.3,60 169.3,140 100,180 30.7,140 30.7,60" class="radar-grid-web" />
                                    <polygon points="100,45 152,75 152,125 100,155 48,125 48,75" class="radar-grid-web sub" />
                                    <polygon points="100,70 134.6,90 134.6,110 100,130 65.4,110 65.4,90" class="radar-grid-web inner" />
                                    <!-- Dynamic Polyline Polygon -->
                                    <polygon id="radarPolygon" points="100,26.4 161,64.8 154,131.2 100,178.4 36.3,136.8 34.2,62" class="radar-dynamic-poly" />
                                    <!-- Corner Labels -->
                                    <text x="100" y="14" class="radar-axis-text top">Serang</text>
                                    <text x="175" y="62" class="radar-axis-text">Tahan</text>
                                    <text x="175" y="146" class="radar-axis-text">Mobilitas</text>
                                    <text x="100" y="196" class="radar-axis-text bottom">Ekonomi</text>
                                    <text x="25" y="146" class="radar-axis-text">Ekspedisi</text>
                                    <text x="25" y="62" class="radar-axis-text">Sinergi</text>
                                </svg>
                            </div>
                            <div class="radar-bars-col">
                                <div class="radar-bar-row">
                                    <span class="stat-name">⚔️ Serang</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statAtk" style="width: 92%;"></div></div>
                                    <span class="stat-val" id="valAtk">92</span>
                                </div>
                                <div class="radar-bar-row">
                                    <span class="stat-name">🛡️ Pertahanan</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statDef" style="width: 88%;"></div></div>
                                    <span class="stat-val" id="valDef">88</span>
                                </div>
                                <div class="radar-bar-row">
                                    <span class="stat-name">🐎 Mobilitas</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statSpd" style="width: 78%;"></div></div>
                                    <span class="stat-val" id="valSpd">78</span>
                                </div>
                                <div class="radar-bar-row">
                                    <span class="stat-name">🌾 Ekonomi</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statEco" style="width: 98%;"></div></div>
                                    <span class="stat-val" id="valEco">98</span>
                                </div>
                                <div class="radar-bar-row">
                                    <span class="stat-name">🎯 Ekspedisi</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statRaid" style="width: 92%;"></div></div>
                                    <span class="stat-val" id="valRaid">92</span>
                                </div>
                                <div class="radar-bar-row">
                                    <span class="stat-name">✨ Sinergi</span>
                                    <div class="stat-bar-track"><div class="stat-bar-fill" id="statSyn" style="width: 95%;"></div></div>
                                    <span class="stat-val" id="valSyn">95</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Faction Traits -->
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
                        <a href="anmelden.php" class="war-btn-pill-crimson" style="margin-left:auto; padding: 10px 22px; font-size: 13.5px;">⚔️ Rekrut Panglima Ini</a>
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
                        <span class="ledger-val" style="color:#b91c1c;"><?php echo number_format($activeBandits); ?> Sarang Terbuka</span>
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
        <!-- Battlefield Scout Gallery & Interactive Realm Radar -->
    <section class="battlefield-gallery-section" id="gallery">
        <div class="portal-container">
            <div class="section-heraldry-header">
                <span class="heraldry-seal-tag">🗺️ LEMBAR PETA &amp; RADAR TEMPUR INTERAKTIF</span>
                <h2 class="heraldry-title">Kartografi Medan Perang &amp; Intelijen Wilayah</h2>
                <p class="heraldry-subtitle">
                    Peta kuadran navigasi perang real-time. Klik titik radar pada peta kuno untuk melihat status ancaman, kondisi iklim, dan potensi jarahan sumber daya:
                </p>
            </div>

            <!-- Interactive Realm Map Radar Explorer -->
            <div class="interactive-realm-map-wrapper">
                <div class="realm-map-container">
                    <img src="img/reg/map.png" alt="Peta Dunia Travian" class="realm-map-base" />
                    <!-- Map Pins -->
                    <div class="realm-pin pin-capital" onclick="showMapBrief(1)" style="top: 50%; left: 50%;" title="Ibu Kota Kerajaan (0|0)">
                        <div class="pin-ring green"></div>
                        <div class="pin-marker">🏛️</div>
                        <span class="pin-label">Ibu Kota (0|0)</span>
                    </div>
                    <div class="realm-pin pin-boss" onclick="showMapBrief(2)" style="top: 36%; left: 32%;" title="Sarang Naga World Boss (-14|28)">
                        <div class="pin-ring red"></div>
                        <div class="pin-marker">🐉</div>
                        <span class="pin-label">World Boss (-14|28)</span>
                    </div>
                    <div class="realm-pin pin-market" onclick="showMapBrief(3)" style="top: 65%; left: 68%;" title="Pasar Gelap (42|-15)">
                        <div class="pin-ring gold"></div>
                        <div class="pin-marker">🏴‍☠️</div>
                        <span class="pin-label">Pasar Gelap (42|-15)</span>
                    </div>
                    <div class="realm-pin pin-wonder" onclick="showMapBrief(4)" style="top: 24%; left: 76%;" title="Wonder of the World (100|100)">
                        <div class="pin-ring purple"></div>
                        <div class="pin-marker">✨</div>
                        <span class="pin-label">Kuil Keajaiban (100|100)</span>
                    </div>
                </div>
                <div class="realm-brief-card" id="realmBriefCard">
                    <div class="brief-kicker" id="briefKicker">📍 REKOGNISI WILAYAH KUADRAN</div>
                    <h4 class="brief-title" id="briefTitle">Ibu Kota Kerajaan Pusat (0|0)</h4>
                    <p class="brief-desc" id="briefDesc">Jantung peradaban kuno, pusat keajaiban arsitektur dan lumbung logistik pertahanan aliansi agung.</p>
                    <div class="brief-tags-row" id="briefTags">
                        <span class="brief-tag">Kuadran: Pusat (0|0)</span>
                        <span class="brief-tag">Status: Siaga Tempur</span>
                        <span class="brief-tag">Oasis: Gandum 150%</span>
                    </div>
                </div>
            </div>

            <!-- Scout Gallery Cards -->
            <div class="gallery-scout-grid" style="margin-top: 32px;">
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

                <div class="scout-shot-card" onclick="triggerLightbox('img/en/s/s1.png', 'Pusat Pemerintahan &amp; Tata Kota', 'Pengelolaan gedung utama, barak prajurit, pasar perdagangan, dan lumbung peradaban.')">
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
        // =========================================================
        // 1. FACTION & 3D FIGURINE REGISTRY WITH DOCTRINE RADAR STATS
        // =========================================================
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
                color: "#047857",
                glow: "rgba(4, 120, 87, 0.4)",
                stats: { atk: 92, def: 88, spd: 78, eco: 98, raid: 92, syn: 95 },
                score: "92/100",
                lore: "Kerajaan Maritim Nusantara berdiri megah di atas gugusan kepulauan khatulistiwa yang kaya rempah. Dengan perpaduan armada kapal Jung raksasa, lumbung pangan yang melimpah, artileri meriam Cetbang, serta barisan Gajah Bhayangkara yang disegani, Nusantara adalah peradaban adidaya maritim yang tangguh dalam pertahanan dan mematikan dalam serbuan amfibi.",
                traits: [
                    { title: "🌾 Lumbung Kemakmuran", desc: "Kapasitas penyimpanan logistik lumbung & perlindungan pangan superior." },
                    { title: "💥 Artileri Cetbang", desc: "Meriam perunggu putar dengan daya tembak tinggi penembus dinding benteng." },
                    { title: "🐘 Gajah Bhayangkara", desc: "Monster lapis baja penghancur formasi frontal musuh di garis depan." },
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
                title: "RAGNAR LOTHBROK",
                type: "Prajurit Serbu Laut Es",
                figurine: "img/landing_v3/fig_viking.png",
                badge: "Agresor: Penakluk Pesisir Dingin",
                commanderSub: "Jarl Tertinggi Armada Drakkar Es",
                color: "#0284c7",
                glow: "rgba(2, 132, 199, 0.4)",
                stats: { atk: 96, def: 74, spd: 82, eco: 80, raid: 94, syn: 85 },
                score: "88/100",
                lore: "Bangsa Viking menjelajahi samudera ganas dari utara yang beku. Dikenal dengan daya rusak serangan yang buas, drakkar kapal naga yang lincah mengarungi sungai dan pesisir, serta barisan Berserker berkapak ganda yang tak kenal rasa takut.",
                traits: [
                    { title: "🪓 Serbuan Berserker", desc: "Bonus serangan frontal masif pada gelombang pembuka pertempuran." },
                    { title: "🛡️ Formasi Skjaldborg", desc: "Dinding perisai kayu kokoh penahan panah musuh saat mendarat di pantai." },
                    { title: "⚡ Kapal Drakkar Naga", desc: "Penyergapan pesisir kilat dengan jarahan rampasan sumber daya maksimal." },
                    { title: "❄️ Ketahanan Salju", desc: "Kebal terhadap pinalti cuaca dingin dan badai salju ekstrem di peta tempur." }
                ],
                units: [
                    { id: 81, name: "Thrall" },
                    { id: 82, name: "Huskarl" },
                    { id: 83, name: "Berserker" },
                    { id: 84, name: "Archer" },
                    { id: 85, name: "Raider" },
                    { id: 86, name: "Valkyrie" },
                    { id: 87, name: "Ram" },
                    { id: 88, name: "Catapult" },
                    { id: 89, name: "Chief" },
                    { id: 90, name: "Settler" }
                ]
            },
            natars: {
                name: "Bangsa Natars",
                title: "THE DARK QUEEN",
                type: "Penguasa Kuno & Misteri Gaib",
                figurine: "img/landing_v3/fig_queen.png",
                badge: "Penjaga Kuil: Keajaiban Dunia",
                commanderSub: "Ratu Abadi Takhta Keajaiban Dunia",
                color: "#7e22ce",
                glow: "rgba(126, 34, 206, 0.45)",
                stats: { atk: 98, def: 95, spd: 80, eco: 85, raid: 96, syn: 90 },
                score: "96/100",
                lore: "Natars adalah peradaban tertua yang bangkit dari tidur panjangnya di jantung peta dunia. Menguasai teknologi misterius pendirian Keajaiban Dunia (Wonder of the World) dan mengerahkan legiun elit berkekuatan gaib kuno.",
                traits: [
                    { title: "🏛️ Takhta Keajaiban", desc: "Mampu mendirikan mahakarya Wonder of the World penentu akhir zaman." },
                    { title: "🔮 Sihir Pertahanan", desc: "Aura perlindungan dinding tebal yang melipatgandakan pertahanan desa pusat." },
                    { title: "💀 Pasukan Abadi", desc: "Garnisun penjaga kuil dengan ketahanan tempur legendaris tak tertandingi." },
                    { title: "📜 Cetak Biru Kuno", desc: "Menjaga relik arsitektur dan artefak rahasia yang diperebutkan aliansi besar." }
                ],
                units: [
                    { id: 41, name: "Pikeman" },
                    { id: 42, name: "Thorned Warrior" },
                    { id: 43, name: "Guardsman" },
                    { id: 44, name: "Birds of Prey" },
                    { id: 45, name: "Axerider" },
                    { id: 46, name: "Natarian Knight" },
                    { id: 47, name: "War Elephant" },
                    { id: 48, name: "Ballista" },
                    { id: 49, name: "Natarian Emperor" },
                    { id: 50, name: "Settler" }
                ]
            },
            romans: {
                name: "Kekaisaran Romawi",
                title: "LEGATUS CAESAR",
                type: "Disiplin Taktis & Teknologi Tinggi",
                figurine: "img/reg/portrait_v1.png",
                badge: "Klasik: Elit Disiplin Tinggi",
                commanderSub: "Panglima Legiun Elit Romawi",
                color: "#b45309",
                glow: "rgba(180, 83, 9, 0.4)",
                stats: { atk: 88, def: 92, spd: 80, eco: 90, raid: 86, syn: 92 },
                score: "90/100",
                lore: "Kekaisaran Romawi adalah maestro pembangunan dan keteraturan militer. Kemampuan mendirikan gedung dan ladang sumber daya secara bersamaan serta barisan Legionnaire berperisai baja menjadikannya peradaban paling berimbang.",
                traits: [
                    { title: "🏛️ Konstruksi Ganda", desc: "Dapat mengupgrade gedung kota dan ladang sumber daya secara simultan." },
                    { title: "🛡️ Praetorian Anti-Infanteri", desc: "Pasukan penjaga benteng terbaik dalam meredam gelombang serangan lawan." },
                    { title: "⚔️ Kaisar Kavaleri", desc: "Kavaleri Equites Caesaris berdaya hancur tinggi pembalik keadaan pertempuran." },
                    { title: "🧱 Tembok Batu Kokoh", desc: "Tembok kota memberikan bonus pertahanan tertinggi di antara seluruh suku." }
                ],
                units: [
                    { id: 1, name: "Legionnaire" }, { id: 2, name: "Praetorian" }, { id: 3, name: "Imperian" },
                    { id: 4, name: "Equites Legati" }, { id: 5, name: "Equites Imperatoris" }, { id: 6, name: "Equites Caesaris" },
                    { id: 7, name: "Battering Ram" }, { id: 8, name: "Fire Catapult" }, { id: 9, name: "Senator" }, { id: 10, name: "Settler" }
                ]
            },
            teutons: {
                name: "Suku Teuton",
                title: "WARLORD ARMINIUS",
                type: "Agresor Perang & Penjarah Brutal",
                figurine: "img/reg/portrait_v2.png",
                badge: "Klasik: Penjarah Terganas",
                commanderSub: "Panglima Serbuan Badai Teuton",
                color: "#b91c1c",
                glow: "rgba(185, 28, 28, 0.4)",
                stats: { atk: 98, def: 70, spd: 85, eco: 78, raid: 90, syn: 84 },
                score: "87/100",
                lore: "Teuton adalah teror bagi peradaban tetangga. Dibina untuk peperangan ofensif tanpa henti, pasukan pentung Clubswinger murah dan cepat diproduksi, serta sanggup menjarah cadangan persembunyian lawan.",
                traits: [
                    { title: "💀 Penjarah Persembunyian", desc: "Mampu merampas hingga 20% isi gua persembunyian desa musuh." },
                    { title: "⚡ Biaya Pasukan Murah", desc: "Waktu dan biaya pelatihan prajurit infantri paling ekonomis." },
                    { title: "🪓 Pasukan Paladin Gesit", desc: "Kavaleri penjarah berkapasitas muatan tinggi dan pertahanan tangguh." },
                    { title: "🏰 Tembok Tanah Kebal", desc: "Tembok tanah sulit dihancurkan oleh senjata pendobrak ram lawan." }
                ],
                units: [
                    { id: 11, name: "Clubswinger" }, { id: 12, name: "Spearman" }, { id: 13, name: "Axeman" },
                    { id: 14, name: "Scout" }, { id: 15, name: "Paladin" }, { id: 16, name: "Teutonic Knight" },
                    { id: 17, name: "Ram" }, { id: 18, name: "Catapult" }, { id: 19, name: "Chief" }, { id: 20, name: "Settler" }
                ]
            },
            gauls: {
                name: "Suku Galia",
                title: "CHIEFTAIN VERCINGETORIX",
                type: "Pertahanan Alam & Kavaleri Kilat",
                figurine: "img/reg/portrait_v3.png",
                badge: "Klasik: Perisai Terkokoh",
                commanderSub: "Panglima Perang Penjaga Hutan Kuno",
                color: "#0f766e",
                glow: "rgba(15, 118, 110, 0.4)",
                stats: { atk: 76, def: 98, spd: 94, eco: 86, raid: 82, syn: 90 },
                score: "91/100",
                lore: "Galia adalah suku yang hidup selaras dengan alam dan sangat dicintai oleh pemain bertahan. Memiliki perangkap rahasia yang menjebak serbuan musuh dan kavaleri Theutates Thunder tercepat di dunia Travian.",
                traits: [
                    { title: "🪤 Perangkap Rahasia", desc: "Bangunan Trapper mampu mengurung ratusan tentara penyerbu musuh." },
                    { title: "📦 Gua Berkapasitas 2x", desc: "Perlindungan lumbung sumber daya gua dua kali lebih besar dari suku lain." },
                    { title: "⚡ Kavaleri Kilat TT", desc: "Theutates Thunder adalah pasukan berkuda tercepat untuk serangan kilat." },
                    { title: "🛡️ Druidrider Tangguh", desc: "Dukungan pertahanan kavaleri anti-infanteri yang sangat fleksibel." }
                ],
                units: [
                    { id: 21, name: "Phalanx" }, { id: 22, name: "Swordsman" }, { id: 23, name: "Pathfinder" },
                    { id: 24, name: "Theutates Thunder" }, { id: 25, name: "Druidrider" }, { id: 26, name: "Haeduan" },
                    { id: 27, name: "Ram" }, { id: 28, name: "Trebuchet" }, { id: 29, name: "Chieftain" }, { id: 30, name: "Settler" }
                ]
            },
            egyptians: {
                name: "Kerajaan Mesir",
                title: "PHARAOH RAMSES",
                type: "Arsitektur Oase & Ekonomi Emas",
                figurine: "img/reg/portrait_v6.png",
                badge: "Eksklusif: Raja Ekonomi Gurun",
                commanderSub: "Firaun Penguasa Sungai Nil Kuno",
                color: "#d97706",
                glow: "rgba(217, 119, 6, 0.4)",
                stats: { atk: 72, def: 90, spd: 75, eco: 98, raid: 84, syn: 94 },
                score: "89/100",
                lore: "Mesir adalah pusat kemakmuran dan peradaban oase. Mampu membangun sistem pengairan Waterworks yang melipatgandakan produksi oase, serta barisan pertahanan Ash Warden yang murah dan ulet.",
                traits: [
                    { title: "💧 Pengairan Waterworks", desc: "Melipatgandakan bonus produksi sumber daya dari oasis oase aliansi." },
                    { title: "🛡️ Ash Warden Murah", desc: "Pasukan pertahanan infantri berbiaya gandum sangat rendah." },
                    { title: "🌾 Kapasitas Kereta Dagang", desc: "Pedagang pasar Mesir sanggup mengangkut logistik dalam volume raksasa." },
                    { title: "☀️ Pertahanan Tembok Pasir", desc: "Tembok batu pasir memberikan kestabilan pertahanan perimeter benteng." }
                ],
                units: [
                    { id: 51, name: "Slave Militia" }, { id: 52, name: "Ash Warden" }, { id: 53, name: "Khopesh Warrior" },
                    { id: 54, name: "Sopdu Explorer" }, { id: 55, name: "Anhur Guard" }, { id: 56, name: "Resheph Chariot" },
                    { id: 57, name: "Ram" }, { id: 58, name: "Catapult" }, { id: 59, name: "Nomarch" }, { id: 60, name: "Settler" }
                ]
            },
            huns: {
                name: "Kekaisaran Hun",
                title: "ATTILA THE HUN",
                type: "Kavaleri Pemanah Padang Rumput",
                figurine: "img/reg/portrait_v7.png",
                badge: "Eksklusif: Badai Kavaleri Panah",
                commanderSub: "Khan Penguasa Kavaleri Padang Rumput",
                color: "#c2410c",
                glow: "rgba(194, 65, 12, 0.4)",
                stats: { atk: 95, def: 68, spd: 98, eco: 72, raid: 92, syn: 80 },
                score: "88/100",
                lore: "Hun lahir di atas pelana kuda. Tak ada yang sanggup menandingi kecepatan dan kegesitan gerak kavaleri panah Hun di medan padang terbuka, mampu menyerang dan mundur tanpa sempat disentuh lawan.",
                traits: [
                    { title: "🏹 Pemanah Kuda Mematikan", desc: "Kavaleri panah mampu menembus formasi musuh dengan daya rusak tinggi." },
                    { title: "⚡ Kecepatan Jelajah Maksimal", desc: "Kecepatan gerak pasukan di peta tempur paling tinggi di dunia kuno." },
                    { title: "🏕️ Kamp Lapangan Praktis", desc: "Struktur bangunan sederhana yang menunjang mobilitas perkemahan perang." },
                    { title: "🎯 Serbuan Tak Terduga", desc: "Menyerang sasaran dari jarak jauh tanpa terdeteksi oleh pos intai standar." }
                ],
                units: [
                    { id: 61, name: "Mercenary" }, { id: 62, name: "Bowman" }, { id: 63, name: "Spotter" },
                    { id: 64, name: "Steppe Rider" }, { id: 65, name: "Marksman" }, { id: 66, name: "Marauder" },
                    { id: 67, name: "Ram" }, { id: 68, name: "Catapult" }, { id: 69, name: "Logades" }, { id: 70, name: "Settler" }
                ]
            },
            spartans: {
                name: "Negara Kota Sparta",
                title: "KING LEONIDAS",
                type: "Phalanx Disiplin Besi & Perisai Perunggu",
                figurine: "img/reg/portrait_v8.png",
                badge: "Eksklusif: Tembok Manusia Perunggu",
                commanderSub: "Raja Pejuang Gerbang Thermopylae",
                color: "#9a3412",
                glow: "rgba(154, 52, 18, 0.4)",
                stats: { atk: 90, def: 96, spd: 76, eco: 80, raid: 90, syn: 92 },
                score: "91/100",
                lore: "Sparta tidak membutuhkan tembok batu karena prajuritnya adalah tembok itu sendiri! Dilatih sejak belia dalam doktrin Agoge, Hoplite Sparta membentuk formasi perisai perunggu yang tak tergoyahkan.",
                traits: [
                    { title: "🛡️ Tembok Perisai Phalanx", desc: "Bonus pertahanan kolosal saat barisan infantri berdiri bahu-membahu." },
                    { title: "💪 Doktrin Militer Agoge", desc: "Moral tempur prajurit kebal terhadap efek rasa takut atau desersi." },
                    { title: "🗡️ Tombak Dory Penusuk", desc: "Tombak panjang penembus zirah besi lawan sebelum musuh mendekat." },
                    { title: "🔥 Disiplin Tempur Mutlak", desc: "Kerugian pasukan berkurang signifikan saat menghadapi serbuan musuh." }
                ],
                units: [
                    { id: 71, name: "Hoplite" }, { id: 72, name: "Shield Biter" }, { id: 73, name: "Twin Blades" },
                    { id: 74, name: "Elpida Rider" }, { id: 75, name: "Corinthian" }, { id: 76, name: "Spartiate" },
                    { id: 77, name: "Ram" }, { id: 78, name: "Catapult" }, { id: 79, name: "Ephor" }, { id: 80, name: "Settler" }
                ]
            }
        };

        // =========================================================
        // 2. RADAR & FIGURINE SWITCHER
        // =========================================================
        function updateRadarPolygon(stats, color) {
            const keys = ['atk', 'def', 'spd', 'eco', 'raid', 'syn'];
            const center = 100;
            const radius = 78;
            const points = [];
            for (let i = 0; i < keys.length; i++) {
                const angle = (i * 60 - 90) * Math.PI / 180;
                const r = (stats[keys[i]] / 100) * radius;
                const x = center + r * Math.cos(angle);
                const y = center + r * Math.sin(angle);
                points.push(x.toFixed(1) + ',' + y.toFixed(1));
            }
            const poly = document.getElementById('radarPolygon');
            if (poly) {
                poly.setAttribute('points', points.join(' '));
                if (color) {
                    poly.style.stroke = color;
                    poly.style.fill = color + '33';
                }
            }
            // Update individual bars
            const atkFill = document.getElementById('statAtk'); if (atkFill) atkFill.style.width = stats.atk + '%';
            const defFill = document.getElementById('statDef'); if (defFill) defFill.style.width = stats.def + '%';
            const spdFill = document.getElementById('statSpd'); if (spdFill) spdFill.style.width = stats.spd + '%';
            const ecoFill = document.getElementById('statEco'); if (ecoFill) ecoFill.style.width = stats.eco + '%';
            const raidFill = document.getElementById('statRaid'); if (raidFill) raidFill.style.width = stats.raid + '%';
            const synFill = document.getElementById('statSyn'); if (synFill) synFill.style.width = stats.syn + '%';

            const valAtk = document.getElementById('valAtk'); if (valAtk) valAtk.textContent = stats.atk;
            const valDef = document.getElementById('valDef'); if (valDef) valDef.textContent = stats.def;
            const valSpd = document.getElementById('valSpd'); if (valSpd) valSpd.textContent = stats.spd;
            const valEco = document.getElementById('valEco'); if (valEco) valEco.textContent = stats.eco;
            const valRaid = document.getElementById('valRaid'); if (valRaid) valRaid.textContent = stats.raid;
            const valSyn = document.getElementById('valSyn'); if (valSyn) valSyn.textContent = stats.syn;
        }

        function switchFigurine(key) {
            const data = factionRegistry[key];
            if (!data) return;

            playClickFx(580, 'sine');

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

            // Update Pedestal Runic Ring color
            const ring = document.getElementById('pedestalRunicRing');
            if (ring) {
                ring.style.borderColor = data.color;
                ring.style.boxShadow = '0 0 25px ' + data.glow + ', inset 0 0 15px ' + data.glow;
                ring.style.background = 'radial-gradient(ellipse at 50% 50%, ' + data.glow + ' 0%, transparent 70%)';
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
                }, 140);
            }

            // Update Text Elements
            const nameEl = document.getElementById('figurineName');
            const subEl = document.getElementById('figurineSub');
            const badgeEl = document.getElementById('figurineBadge');
            const loreEl = document.getElementById('figurineLore');
            const scoreEl = document.getElementById('radarTotalScore');

            if (nameEl) nameEl.textContent = data.title || data.name;
            if (subEl) subEl.textContent = data.commanderSub || data.type;
            if (badgeEl) badgeEl.textContent = data.badge;
            if (loreEl) loreEl.textContent = data.lore;
            if (scoreEl && data.score) scoreEl.textContent = 'Rating: ' + data.score;

            // Update Radar Polygon & Bars
            if (data.stats) {
                updateRadarPolygon(data.stats, data.color);
            }

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

        // =========================================================
        // 3. INTERACTIVE CODEX ENHANCEMENTS
        // =========================================================
        // 3A. Bandit Camp Tier Selector
        const banditData = {
            1: { title: "Tier 1: Penyamun Rimba", sub: "Patroli liar di perbatasan hutan dengan penjagaan ringan.", req: "⚔️ Pasukan Minimal: 500 &bull; Hadiah: 800 Silver, 600 EXP, 200 CP", img: "img/bandit/camp_tier_1.png" },
            2: { title: "Tier 2: Pasukan Pembelot", sub: "Garnisun pembelot bersenjata di bentang lembah berbatu.", req: "⚔️ Pasukan Minimal: 2,500 &bull; Hadiah: 2,400 Silver, 1,500 EXP, 600 CP", img: "img/bandit/camp_tier_2.png" },
            3: { title: "Tier 3: Benteng Penyamun", sub: "Benteng pertahanan kokoh komplotan begal bersenjata kepung.", req: "⚔️ Pasukan Minimal: 6,000 &bull; Hadiah: 5,500 Silver, 2,800 EXP, 1,200 CP", img: "img/bandit/camp_tier_3.png" },
            4: { title: "World Boss Tier 4: Sarang Naga", sub: "Tantangan raid kolosal aliansi dengan hadiah Silver berlimpah!", req: "⚔️ Pasukan Minimal: 15,000 &bull; Hadiah: 12,000 Silver, 5,000 EXP, 2,500 CP", img: "img/bandit/camp_tier_4.png" }
        };

        function selectBanditTier(tier) {
            playClickFx(620, 'sine');
            document.querySelectorAll('.tier-tab-btn').forEach((btn, idx) => {
                btn.classList.toggle('active', (idx + 1) === tier);
            });
            const d = banditData[tier];
            if (!d) return;
            const img = document.getElementById('banditCampImg'); if (img) img.src = d.img;
            const title = document.getElementById('banditTierTitle'); if (title) title.textContent = d.title;
            const sub = document.getElementById('banditTierSub'); if (sub) sub.textContent = d.sub;
            const req = document.getElementById('banditTierRewards'); if (req) req.innerHTML = d.req;
        }

        // 3B. Weather Climate Simulator
        const weatherPresets = {
            kemarau: { name: "☀️ Musim Kemarau Berkelanjutan", desc: "Panen gandum melimpah (+20%), cadangan air berkurang, kecepatan kavaleri normal." },
            hujan: { name: "🌧️ Hujan Badai & Lumpur Cadas", desc: "Kavaleri melambat (-25%), ketepatan artileri berkurang, konsumsi pangan normal." },
            kabut: { name: "🌫️ Kabut Tebal Lembah Kuno", desc: "Pos intai scout gagal mendeteksi formasi musuh dalam radius 5 kotak." },
            salju: { name: "❄️ Badai Salju Dataran Es", desc: "Konsumsi gandum pasukan meningkat (+15%), pergerakan infantri terhambat." }
        };

        function selectWeather(type) {
            playClickFx(520, 'sine');
            document.querySelectorAll('.weather-chip').forEach(chip => {
                chip.classList.toggle('active', chip.id.toLowerCase().includes(type));
            });
            const d = weatherPresets[type];
            if (!d) return;
            const nameEl = document.getElementById('wStatusName'); if (nameEl) nameEl.textContent = d.name;
            const descEl = document.getElementById('wStatusDesc'); if (descEl) descEl.textContent = d.desc;
        }

        // 3C. Village Quarantine Toggle
        let isQuarantined = false;
        function toggleVillageQuarantine() {
            playClickFx(740, 'triangle');
            isQuarantined = !isQuarantined;
            const label = document.getElementById('qLabel');
            const meter = document.getElementById('qRisk');
            const btn = document.getElementById('qSwitchBtn');
            const note = document.getElementById('qNote');

            if (isQuarantined) {
                if (label) label.innerHTML = 'Pintu Gerbang: <strong style="color:#b91c1c;">DIKARANTINA</strong>';
                if (meter) { meter.textContent = 'Risiko Sampar: 0% Steril'; meter.style.color = '#047857'; meter.style.background = '#ecfdf5'; }
                if (btn) btn.innerHTML = '🔓 Buka Gerbang Desa (Pulihkan Perdagangan)';
                if (note) note.textContent = 'Gerbang desa terkunci rapat. Bebas dari penularan wabah namun kafilah dagang tertahan.';
            } else {
                if (label) label.innerHTML = 'Pintu Gerbang: <strong style="color:#047857;">TERBUKA</strong>';
                if (meter) { meter.textContent = 'Risiko Sampar: 85% Bahaya'; meter.style.color = '#991b1b'; meter.style.background = '#fee2e2'; }
                if (btn) btn.innerHTML = '🔒 Kunci Karantina Gerbang Desa';
                if (note) note.textContent = 'Gerbang terbuka memicu penyebaran sampar dari desa tetangga dalam radius 7 kotak.';
            }
        }

        // 3D. Black Market Laundering Calculator
        function updateMarketCalc(val) {
            const v = parseInt(val, 10);
            const received = Math.round(v * 0.85);
            const inputTxt = document.getElementById('mInputAmountText');
            const resTxt = document.getElementById('mResultAmount');
            if (inputTxt) inputTxt.textContent = Number(v).toLocaleString() + ' Kayu';
            if (resTxt) resTxt.textContent = Number(received).toLocaleString() + ' Gandum';
        }

        // 3E. Mercenary Hire Sound / Toast
        function hireMercenaryFx(unitName) {
            playClickFx(880, 'sine');
            alert('⚔️ [KONTRAK TENTARA BAYARAN]\n' + unitName + ' berhasil disewa dan langsung bersiaga menjaga garnisun benteng desa Anda!');
        }

        // =========================================================
        // 4. INTERACTIVE REALM MAP RADAR BRIEFINGS
        // =========================================================
        const realmPins = {
            1: { kicker: "📍 IBU KOTA PUSAT (0|0)", title: "Ibu Kota Kerajaan Pusat (0|0)", desc: "Jantung peradaban kuno, pusat diplomasi aliansi besar dan benteng keajaiban tata kota logistik.", tags: ["Kuadran: Pusat (0|0)", "Status: Siaga Tempur", "Oasis: Gandum 150%"] },
            2: { kicker: "🎯 SARANG WORLD BOSS", title: "Sarang Naga Kolosal (-14|28)", desc: "Markas naga purba berkulit sisik baja. Membutuhkan minimal 3 aliansi dalam ekspedisi Forward Camp.", tags: ["Koordinat: (-14|28)", "Tingkat Bahaya: Tier 4", "Hadiah: 12,000 Silver"] },
            3: { kicker: "🏴‍☠️ PASAR PENYELUNDUP", title: "Pasar Gelap Rahasia (42|-15)", desc: "Lapak tersembunyi saudagar hitam di tepi jurang gurun. Tempat cuci sumber daya bebas koin Emas.", tags: ["Koordinat: (42|-15)", "Komisi: 15% Bersih", "Bebas Gold: Aktif"] },
            4: { kicker: "✨ KEAJAIBAN DUNIA", title: "Kuil Keajaiban Dunia (100|100)", desc: "Pusat mahakarya Wonder of the World. Aliansi pertama yang membangun kuil hingga level 100 memenangkan perang dunia!", tags: ["Koordinat: (100|100)", "Status: Natars Guard", "Tujuan Akhir Perang"] }
        };

        function showMapBrief(id) {
            playClickFx(650, 'sine');
            const d = realmPins[id];
            if (!d) return;
            const k = document.getElementById('briefKicker'); if (k) k.textContent = d.kicker;
            const t = document.getElementById('briefTitle'); if (t) t.textContent = d.title;
            const desc = document.getElementById('briefDesc'); if (desc) desc.textContent = d.desc;
            const tags = document.getElementById('briefTags');
            if (tags && d.tags) {
                tags.innerHTML = d.tags.map(tag => `<span class="brief-tag">${tag}</span>`).join('');
            }
        }

        // =========================================================
        // 5. WEB AUDIO API SOUNDSCAPE & SYNTHESIZER
        // =========================================================
        let audioCtx = null;
        let isAudioPlaying = false;
        let ambientTimer = null;

        function getAudioContext() {
            if (!audioCtx) {
                const AudioClass = window.AudioContext || window.webkitAudioContext;
                if (AudioClass) audioCtx = new AudioClass();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        function playClickFx(freq = 520, type = 'sine') {
            const ctx = getAudioContext();
            if (!ctx) return;
            try {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 0.5, ctx.currentTime + 0.12);
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch(e) {}
        }

        function startAmbientChimes() {
            const notes = [220, 261.63, 293.66, 329.63, 392, 440, 523.25];
            ambientTimer = setInterval(() => {
                if (!isAudioPlaying) return;
                const ctx = getAudioContext();
                if (!ctx) return;
                try {
                    const freq = notes[Math.floor(Math.random() * notes.length)];
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(freq, ctx.currentTime);
                    gain.gain.setValueAtTime(0.001, ctx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.04, ctx.currentTime + 0.8);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 2.5);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 2.5);
                } catch(e) {}
            }, 1400);
        }

        function stopAmbientChimes() {
            if (ambientTimer) {
                clearInterval(ambientTimer);
                ambientTimer = null;
            }
        }

        function toggleWarAudio() {
            const ctx = getAudioContext();
            isAudioPlaying = !isAudioPlaying;
            const txt = document.getElementById('audioText');
            const bars = document.querySelector('.audio-bars');
            if (isAudioPlaying) {
                if (txt) txt.textContent = 'Musik: Nyala (Majestic)';
                if (bars) bars.classList.add('playing');
                startAmbientChimes();
                playClickFx(660, 'sine');
            } else {
                if (txt) txt.textContent = 'Musik Suasana Perang';
                if (bars) bars.classList.remove('playing');
                stopAmbientChimes();
            }
        }

        // =========================================================
        // 6. 3D HERO MOUSE PARALLAX TILT
        // =========================================================
        (function initHeroTilt() {
            const stage = document.getElementById('heroStage');
            const island = document.getElementById('heroFloatingIsland');
            if (!stage || !island || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            let ticking = false;

            stage.addEventListener('mousemove', (e) => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const rect = stage.getBoundingClientRect();
                        const x = e.clientX - rect.left;
                        const y = e.clientY - rect.top;
                        const centerX = rect.width / 2;
                        const centerY = rect.height / 2;
                        const rotateX = ((y - centerY) / centerY) * -8;
                        const rotateY = ((x - centerX) / centerX) * 8;

                        island.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-8px)`;
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });

            stage.addEventListener('mouseleave', () => {
                island.style.transform = '';
            });
        })();

        // =========================================================
        // 7. MOBILE NAVIGATION DRAWER
        // =========================================================
        function openMobileMenu() {
            const panel = document.getElementById('mobileMenuPanel');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (panel && backdrop) {
                panel.classList.add('active');
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileMenu() {
            const panel = document.getElementById('mobileMenuPanel');
            const backdrop = document.getElementById('mobileDrawerBackdrop');
            if (panel && backdrop) {
                panel.classList.remove('active');
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        const menuToggleBtn = document.getElementById('mobileMenuBtn');
        if (menuToggleBtn) {
            menuToggleBtn.addEventListener('click', openMobileMenu);
        }

        // =========================================================
        // 8. LIGHTBOX PREVIEWS
        // =========================================================
        function triggerLightbox(imgSrc, title, desc) {
            playClickFx(600, 'sine');
            const modal = document.getElementById('warLightboxModal');
            const img = document.getElementById('lightboxModalImg');
            const t = document.getElementById('lightboxTitle');
            const d = document.getElementById('lightboxDesc');
            if (img) img.src = imgSrc;
            if (t) t.textContent = title;
            if (d) d.textContent = desc;
            if (modal) modal.classList.add('active');
        }

        function closeLightbox(event) {
            const modal = document.getElementById('warLightboxModal');
            if (modal) modal.classList.remove('active');
        }

        // =========================================================
        // 9. ANIMATE COUNTERS & GOLD SUN DUST EMBER ENGINE
        // =========================================================
        (function initCounters() {
            const metrics = document.querySelectorAll('.intel-metric');
            if (!metrics.length) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = parseInt(entry.target.getAttribute('data-target') || '0', 10);
                        let count = 0;
                        const duration = 1500;
                        const step = Math.max(1, Math.floor(target / (duration / 16)));
                        const timer = setInterval(() => {
                            count += step;
                            if (count >= target) {
                                count = target;
                                clearInterval(timer);
                            }
                            entry.target.textContent = count.toLocaleString();
                        }, 16);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            metrics.forEach(m => observer.observe(m));
        })();

        // Canvas Particle Embers (Shimmering Golden Sun Motes)
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

            const colors = ['#d4af37', '#f59e0b', '#d97706', '#b45309', '#fcd34d'];
            const particles = [];
            const count = Math.min(45, Math.floor(width / 30));

            for (let i = 0; i < count; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    radius: Math.random() * 2.2 + 0.8,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    vx: (Math.random() - 0.5) * 0.4,
                    vy: -(Math.random() * 0.7 + 0.3),
                    alpha: Math.random() * 0.6 + 0.2,
                    pulse: Math.random() * 0.02
                });
            }

            function render() {
                ctx.clearRect(0, 0, width, height);
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;
                    p.alpha += p.pulse;
                    if (p.alpha > 0.8 || p.alpha < 0.2) p.pulse = -p.pulse;

                    if (p.y < 0) {
                        p.y = height + 10;
                        p.x = Math.random() * width;
                    }
                    if (p.x < 0) p.x = width;
                    if (p.x > width) p.x = 0;

                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fillStyle = p.color;
                    ctx.globalAlpha = p.alpha;
                    ctx.shadowBlur = 8;
                    ctx.shadowColor = p.color;
                    ctx.fill();
                    ctx.restore();
                }
                requestAnimationFrame(render);
            }
            render();
        })();
    </script>
</body>
</html>
