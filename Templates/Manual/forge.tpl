<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : forge.tpl                                                 ##
##  Type           : In Game Manual Page - World Essence & Altar Tempa Tier 4  ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if (!class_exists('HeroForge') && file_exists(__DIR__ . '/../../GameEngine/HeroForge.php')) {
    require_once __DIR__ . '/../../GameEngine/HeroForge.php';
}
if (!class_exists('HeroItems') && file_exists(__DIR__ . '/../../GameEngine/HeroItems.php')) {
    require_once __DIR__ . '/../../GameEngine/HeroItems.php';
}
global $heroItemCatalog;
if (!isset($heroItemCatalog) && file_exists(__DIR__ . '/../../GameEngine/Data/hero_items.php')) {
    require_once __DIR__ . '/../../GameEngine/Data/hero_items.php';
}

$recipes = class_exists('HeroForge') ? HeroForge::getRecipes() : [];
?>

<link rel="stylesheet" href="css/hero_items.css?v=20261110_t4v2" type="text/css">
<style type="text/css">
.manual-forge-box {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    color: #333;
    line-height: 1.5;
    padding: 6px 6px 50px 6px;
}
.mf-hero-banner {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #f8fafc;
    border-radius: 8px;
    padding: 16px 18px;
    margin-bottom: 16px;
    border: 1px solid #334155;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
.mf-hero-banner h1 {
    color: #f59e0b;
    font-size: 17px;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.mf-hero-banner p {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #cbd5e1;
    line-height: 1.6;
}
.mf-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 14px 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.mf-card h2 {
    font-size: 13.5px;
    color: #b45309;
    margin: 0 0 10px 0;
    padding-bottom: 6px;
    border-bottom: 2px solid #fef3c7;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mf-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
    margin: 8px 0 12px 0;
}
.mf-table th {
    background: #f1f5f9;
    color: #334155;
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    text-align: left;
    font-size: 11px;
}
.mf-table td {
    padding: 7px 8px;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
}
.mf-table tr:nth-child(even) {
    background: #f8fafc;
}
.mf-pill {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}
.mf-pill-boss { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.mf-pill-fort { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
.mf-pill-begal { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
.mf-pill-none { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

.mf-recipe-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 12px 14px;
    margin: 10px 0;
    font-size: 12px;
}
.mf-recipe-list {
    margin: 6px 0;
    padding-left: 20px;
    line-height: 1.7;
}
.mf-item-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin-top: 10px;
}
.mf-catalog-card {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 10px;
    display: flex;
    gap: 10px;
    align-items: center;
    transition: all 0.2s ease;
}
.mf-catalog-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
    background: #fff;
}
.mf-catalog-details {
    flex: 1;
}
.mf-catalog-title {
    font-size: 12px;
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 2px;
}
.mf-catalog-slot {
    font-size: 10px;
    color: #64748b;
    font-weight: bold;
    text-transform: uppercase;
}
.mf-catalog-bonus {
    font-size: 11px;
    color: #b45309;
    margin-top: 4px;
    line-height: 1.35;
}
.mf-btn-nav {
    display: inline-block;
    padding: 6px 14px;
    background: #f1f5f9;
    color: #334155;
    font-weight: bold;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 11px;
    margin-right: 6px;
    margin-top: 6px;
}
.mf-btn-nav:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.mf-btn-nav.primary {
    background: #f59e0b;
    color: #ffffff;
    border-color: #d97706;
}
.mf-btn-nav.primary:hover {
    background: #d97706;
}
</style>

<div class="manual-forge-box">

    <!-- Header Banner -->
    <div class="mf-hero-banner">
        <h1>🔥 Panduan Altar Penempaan Legendaris &amp; World Essence</h1>
        <p>
            Selamat datang di panduan resmi <b>Perlengkapan Legendaris Tier 4</b> di TravianZ!
            Perlengkapan legendaris adalah artefak sakti berkekuatan dewa yang ditempa melalui ritual magis di <b>Altar Penempaan Kuno</b>.
            Berbeda dengan senjata biasa yang terkunci pada satu suku, seluruh perlengkapan Tier 4 bersifat <b>Murni Universal</b> dan dapat dipakai oleh pahlawan dari suku mana saja (Romawi, Teuton, Galia, Hun, Mesir, Sparta, Viking, dan Nusantara).
        </p>
        <div>
            <a href="hero.php?t4tab=forge" class="mf-btn-nav primary" target="_top">🔥 Buka Altar Tempa di Game</a>
            <a href="bandit.php" class="mf-btn-nav" target="_top">🗺️ Radar Sarang Bandit &amp; World Boss</a>
            <a href="manual.php?s=1" class="mf-btn-nav">📖 Daftar Isi Manual</a>
        </div>
    </div>

    <!-- Bagian 1: Dari Mana Mendapatkan World Essence -->
    <div class="mf-card">
        <h2>💎 1. Dari Mana Mendapatkan World Essence?</h2>
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px; background: #fdf8eb; border: 1px solid #ebd3a7; padding: 10px 14px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span class="heroT4Item item210 heroT4ItemLarge heroT4EssenceGlow"></span>
            <div>
                <b style="color: #b45309; font-size: 13px;">World Essence (Mustika Intisari Dunia) - Item #210</b>
                <p style="margin: 3px 0 0 0; font-size: 11.5px; color: #5c4427; line-height: 1.45;">
                    Mustika permata surya kuno yang memancarkan aura emas hangat kemegahan peradaban antik. Diperlukan sebanyak <b>30 butir</b> sebagai bahan katalis inti pada setiap penempaan perlengkapan Tier 4 di Altar Kuno.
                </p>
            </div>
        </div>
        <p>
            Anda dapat mengumpulkannya dengan menaklukkan sarang penyamun PvE di peta:
        </p>

        <table class="mf-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Target Musuh PvE</th>
                    <th style="width: 20%;">Peluang Drop</th>
                    <th style="width: 20%;">Jumlah Essence</th>
                    <th>Keterangan &amp; Mekanisme</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>Sarang Kroco (Tier 1)</b></td>
                    <td><span class="mf-pill mf-pill-none">0%</span></td>
                    <td>0 pcs</td>
                    <td>Sarang bandit tingkat pemula; tidak menjatuhkan World Essence.</td>
                </tr>
                <tr>
                    <td><b>Markas Begal (Tier 2)</b></td>
                    <td><span class="mf-pill mf-pill-begal">30% Peluang</span></td>
                    <td>1 pcs</td>
                    <td>Begal kelas menengah. Penakluk yang berhasil meratakan markas berpeluang membawa pulang 1 World Essence.</td>
                </tr>
                <tr>
                    <td><b>Benteng Gembong (Tier 3)</b></td>
                    <td><span class="mf-pill mf-pill-fort">75% Peluang</span></td>
                    <td>1 &ndash; 2 pcs</td>
                    <td>Benteng penyamun kuat. Penakluk hampir pasti mendapatkan 1 hingga 2 World Essence di ruang brankasnya.</td>
                </tr>
                <tr>
                    <td><b>WORLD BOSS (Tier 4 Raid)</b><br><small style="color:#b91c1c;">(Sarang Naga &amp; Gembong Legenda)</small></td>
                    <td><span class="mf-pill mf-pill-boss">100% PASTI DROP</span></td>
                    <td><b>Pool 80 pcs</b><br>(Bagi Hasil Raid)</td>
                    <td>
                        <b>Pusat Buruan Utama!</b> Setiap kali World Boss tumbang, <b>Pool 80 World Essence</b> dibagikan proporsional kepada semua pemain berdasarkan persentase <i>damage</i> yang dicatatkan di papan kontribusi.<br>
                        • <i>Top Damager (Rank 1):</i> Memperoleh bagian terbesar (~25&ndash;35 Essence).<br>
                        • <i>Last Hitter (Pukulan Terakhir):</i> Mendapatkan <b>Bonus Ekstra +3 Essence</b>!
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 10px 12px; font-size: 11.5px; color: #1e40af; border-radius: 0 4px 4px 0;">
            💡 <b>Tips Berburu:</b> Selalu pantau halaman <b><a href="bandit.php" target="_top" style="color:#1d4ed8; font-weight:bold;">Radar Sarang Bandit &amp; World Boss</a></b> untuk mengetahui koordinat lokasi dan sisa HP World Boss. Siapkan pasukan gabungan aliansi untuk mengamankan kontribusi damage tertinggi!
        </div>
    </div>

    <!-- Bagian 2: Apa Saja yang Dibutuhkan untuk Menempa -->
    <div class="mf-card">
        <h2>⚒️ 2. Apa Saja yang Dibutuhkan di Altar Penempaan?</h2>
        <p>
            Penempaan perlengkapan Legendaris memiliki <b>Tingkat Keberhasilan 100% (Pasti Berhasil)</b>. Tidak ada risiko kegagalan atau hilangnya bahan secara sia-sia.
            Namun, Anda harus mengumpulkan dan mengorbankan bahan-bahan berharga berikut:
        </p>

        <div class="mf-recipe-box">
            <b style="color: #92400e; font-size: 12.5px;">📜 Formula Standar 1 Item Legendaris Tier 4:</b>
            <ul class="mf-recipe-list">
                <li><b>1x Item Tier 3 (Bahan Utama):</b> Harus dari slot yang sama dengan item yang ingin dibuat (misal: ingin menempa Helm Legendaris, Anda harus menumbalkan 1 Helm Tier 3). Item tidak boleh sedang terpasang di Hero (*unequipped*).</li>
                <li><b>2x Item Tier 2 (Katalis Penyeimbang):</b> Dua buah item Tier 2 dari slot yang sama (tidak boleh sedang terpasang di Hero).</li>
                <li><b>30x World Essence:</b> Tersimpan di dalam tas kantong inventori Hero Anda.</li>
                <li><b>2.000 Silver:</b> Saldo silver pahlawan Anda (akan dipotong langsung dari dompet perak pahlawan saat penempaan berhasil).</li>
                <li><b>Sumber Daya Desa:</b>
                    <span style="font-weight: bold; color: #1e293b;">
                        🪵 100.000 Kayu &bull; 🧱 100.000 Tanah Liat &bull; ⛓️ 100.000 Besi &bull; 🌾 80.000 Gandum
                    </span>
                    (akan langsung dipotong dari gudang desa yang sedang aktif).
                </li>
            </ul>
        </div>

        <p style="font-size: 11.5px; color: #475569;">
            <b>Cara Mengakses Altar Tempa:</b><br>
            Buka menu <b>Pahlawan / Hero</b> di pojok kanan atas, lalu klik tab <b>"Altar Tempa (Tier 4)"</b> (atau kunjungi URL <code>hero.php?t4tab=forge</code>).
            Anda juga dapat mengaksesnya dari gedung Rumah Agresi (Hero's Mansion) level 1 ke atas.
        </p>
    </div>

    <!-- Bagian 3: Katalog Perlengkapan Legendaris Tier 4 -->
    <div class="mf-card">
        <h2>✨ 3. Katalog Lengkap 14 Perlengkapan Legendaris Tier 4</h2>
        <p>
            Setiap perlengkapan Tier 4 memberikan kombinasi atribut tempur atau fasilitas kekaisaran yang luar biasa kuat:
        </p>

        <div class="mf-item-grid">
            <?php
            $slotNames = [
                1 => 'Helm',
                2 => 'Zirah Tubuh',
                3 => 'Senjata Universal',
                4 => 'Tangan Kiri',
                5 => 'Sepatu & Pelindung Kaki',
                6 => 'Tunggangan'
            ];
            foreach ($recipes as $iid => $def):
                $slotNum = (int)$def['slot'];
                $itemName = function_exists('heroItemName') ? heroItemName($iid) : $def['name'];
                $itemBonus = function_exists('heroItemBonusText') ? heroItemBonusText($iid) : '';
            ?>
            <div class="mf-catalog-card">
                <span class="heroT4Item item<?php echo $iid; ?> heroT4ItemLarge heroT4LegendaryGlow"></span>
                <div class="mf-catalog-details">
                    <div class="mf-catalog-slot"><?php echo $slotNames[$slotNum] ?? ('Slot ' . $slotNum); ?></div>
                    <div class="mf-catalog-title"><?php echo htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="mf-catalog-bonus">
                        <b>Efek:</b> <?php echo htmlspecialchars($itemBonus, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Footer Navigation -->
    <div style="text-align: center; margin-top: 20px;">
        <map id="nav" name="nav">
            <area href="manual.php?s=1" title="<?php echo OVERVIEW; ?>" coords="0,0,116,18" shape="rect" alt="" />
        </map>
        <a href="manual.php?s=1" class="mf-btn-nav">&laquo; Kembali ke Daftar Isi Manual</a>
        <a href="hero.php?t4tab=forge" class="mf-btn-nav primary" target="_top">🔥 Pergi ke Altar Tempa Legendaris</a>
    </div>

</div>
