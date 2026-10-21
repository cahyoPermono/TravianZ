<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : bastion.tpl                                               ##
##  Type           : In Game Manual Page - Event Benteng Kuno                  ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ                                                  ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
#################################################################################

if (!class_exists('Bastion') && file_exists(__DIR__ . '/../../GameEngine/Bastion.php')) {
    require_once __DIR__ . '/../../GameEngine/Bastion.php';
}

$bastionEvent = class_exists('Bastion') ? Bastion::getEvent() : [];
$bStatus = (int)($bastionEvent['status'] ?? 0);
$latestWinner = class_exists('Bastion') ? Bastion::getLatestWinner() : null;
$nextStartTime = class_exists('Bastion') ? Bastion::getNextScheduledStartTime($bastionEvent, true) : null;
$schedDesc = class_exists('Bastion') ? Bastion::formatScheduleDescription($bastionEvent) : '';
$now = time();
?>

<style type="text/css">
.bastion-manual-box {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    color: #333;
    line-height: 1.5;
    padding: 4px 4px 50px 4px;
    overflow: visible;
}
.b-card {
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 12px 14px;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.b-card h2 {
    font-size: 14px;
    color: #78350f;
    margin: 0 0 8px 0;
    padding-bottom: 5px;
    border-bottom: 1px solid #fef3c7;
    display: flex;
    align-items: center;
    gap: 6px;
}
.b-status-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.b-status-active { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.b-status-scheduled { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.b-status-victory { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.b-status-inactive { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

.b-table {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0;
    font-size: 11px;
}
.b-table th {
    background: #f8fafc;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    padding: 6px 8px;
    text-align: left;
}
.b-table td {
    border: 1px solid #e2e8f0;
    padding: 6px 8px;
    background: #fff;
}
.b-table tr:nth-child(even) td {
    background: #fbfcfe;
}
.b-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}
.b-badge-green { background: #dcfce7; color: #15803d; }
.b-badge-gold { background: #fef3c7; color: #b45309; }
.b-badge-blue { background: #e0f2fe; color: #0369a1; }
.b-btn-link {
    display: inline-block;
    background: #2563eb;
    color: #ffffff !important;
    text-decoration: none;
    font-weight: bold;
    font-size: 11px;
    padding: 5px 12px;
    border-radius: 4px;
    margin-right: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.b-btn-link:hover {
    background: #1d4ed8;
}
.b-btn-secondary {
    background: #475569;
}
.b-btn-secondary:hover {
    background: #334155;
}
.b-btn-gold {
    background: #d97706;
}
.b-btn-gold:hover {
    background: #b45309;
}
.b-step {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 10px;
}
.b-step-num {
    background: #78350f;
    color: #fff;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    flex-shrink: 0;
    margin-top: 2px;
}
</style>

<div class="bastion-manual-box">
    <h1><img class="point" src="img/x.gif" alt="" title="" /> 🏰 Event Benteng Kuno (The Ancient Bastion)</h1>
    <p>
        <b>Benteng Kuno</b> adalah event perebutan takhta kerajaan berformat <i>King of the Hill</i> di titik pusat peta dunia Travian pada koordinat <b>(0|0)</b>. 
        Aliansi dari seluruh penjuru dunia berkompetisi untuk menaklukkan benteng dan mempertahankannya selama <b>12 Jam penuh</b> guna merebut tahta serta <b>Berkah Kedaulatan</b> bagi seluruh anggota aliansi!
    </p>

    <!-- KARTU STATUS REAL-TIME SAAT INI -->
    <div class="b-card" style="background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%); border-color: #fde68a;">
        <h2><span>⚡</span> Status Event Saat Ini di Server</h2>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
                <?php if ($bStatus === 1): ?>
                    <span class="b-status-pill b-status-active">🟢 EVENT SEDANG AKTIF - PEREBUTAN BERLANGSUNG</span>
                    <div style="margin-top:5px; font-size:11px; color:#166534;">
                        Gerbang (0|0) sedang terbuka! Pasukan dari seluruh aliansi dapat menyerang atau memperkuat pertahanan.
                    </div>
                <?php elseif ($bStatus === 3 || ($bStatus === 0 && $nextStartTime !== null && $nextStartTime > $now)): ?>
                    <span class="b-status-pill b-status-scheduled">⏳ EVENT TERJADWAL</span>
                    <div style="margin-top:5px; font-size:11px; color:#1e40af;">
                        Jadwal: <b><?php echo htmlspecialchars($schedDesc); ?></b>
                        <?php if ($nextStartTime): ?>
                            (Mulai: <?php echo date('d M Y pukul H:i', $nextStartTime); ?> WIB)
                        <?php endif; ?>
                    </div>
                <?php elseif ($bStatus === 2): ?>
                    <span class="b-status-pill b-status-victory">🏆 MASA BERKAH KEMENANGAN</span>
                    <div style="margin-top:5px; font-size:11px; color:#92400e;">
                        Benteng telah dimenangkan! Berkah Kedaulatan sedang dinikmati oleh aliansi juara bertahan.
                    </div>
                <?php else: ?>
                    <span class="b-status-pill b-status-inactive">⚪ BENTENG TUTUP / DAMAI</span>
                    <div style="margin-top:5px; font-size:11px; color:#64748b;">
                        Gerbang benteng sedang tertutup dalam keadaan damai. Menunggu pembukaan event oleh Administrator.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($latestWinner)): ?>
            <div style="text-align:right; font-size:11px; background:#fef3c7; padding:6px 10px; border-radius:5px; border:1px solid #fde68a;">
                <div style="font-weight:bold; color:#78350f;">🏆 Juara Bertahan (Season #<?php echo (int)$latestWinner['season']; ?>):</div>
                <div style="font-size:12px; font-weight:800; color:#92400e; margin-top:2px;">
                    <?php echo htmlspecialchars($latestWinner['alliance_name']); ?> [<?php echo htmlspecialchars($latestWinner['alliance_tag']); ?>]
                </div>
                <div style="color:#854d0e; font-size:10px; margin-top:1px;">
                    Panglima: <b><?php echo htmlspecialchars($latestWinner['leader_username']); ?></b>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div style="margin-top:10px; padding-top:8px; border-top:1px dashed #fde68a;">
            <a href="bastion.php" target="_blank" class="b-btn-link b-btn-gold">&raquo; Buka Balai Benteng Kuno (Live Hub)</a>
            <a href="karte.php?z=20201" target="_blank" class="b-btn-link b-btn-secondary">&raquo; Lihat Lokasi (0|0) di Peta</a>
        </div>
    </div>

    <!-- KARTU MEKANISME & LOKASI -->
    <div class="b-card">
        <h2><span>📍</span> Karakteristik Medan Benteng Kuno</h2>
        <table class="b-table">
            <tr>
                <th style="width:28%;">Koordinat &amp; Lokasi</th>
                <td><b>(0|0)</b> — Terletak tepat di poros sumbu tengah peta dunia Travian (Village Ref: <code>20201</code>).</td>
            </tr>
            <tr>
                <th>Pertahanan Alami</th>
                <td>Dilengkapi <b>Tembok Kuno Level 12</b> dan <b>Titik Temu Level 1</b> yang kokoh.</td>
            </tr>
            <tr>
                <th>Garnisun Penjaga Awal</th>
                <td>
                    Dijaga ketat oleh faksi <b>Penjaga Kuno (Tribe Natars)</b> dengan kekuatan awal <b>300 prajurit elit</b> 
                    (Pikeman anti-kavaleri, Throned Warrior anti-infanteri, Guardsman pelindung tebal, Natarian Knight, dan Gajah Perang).
                </td>
            </tr>
            <tr>
                <th>Pasokan Logistik (Zero Starvation)</th>
                <td>
                    Lumbung gandum Benteng Kuno secara otomatis dijaga penuh <b>80.000 gandum</b> setiap waktu. 
                    <b>Pasukan penjaga dan bala bantuan aliansi tidak akan pernah mati karena kelaparan di Benteng Kuno!</b>
                </td>
            </tr>
        </table>
    </div>

    <!-- KARTU ATURAN PERMAINAN -->
    <div class="b-card">
        <h2><span>⚔️</span> Aturan Main &amp; Cara Merebut Benteng</h2>

        <div class="b-step">
            <div class="b-step-num">1</div>
            <div>
                <b>Gerbang Terbuka (Event Aktif)</b><br />
                Pemain hanya bisa menyerang dan mengirim bala bantuan ke (0|0) saat status event <b>AKTIF</b>. Jika event sedang Terjadwal atau Damai, gerbang terkunci dan penyerangan dicegah sistem.
            </div>
        </div>

        <div class="b-step">
            <div class="b-step-num">2</div>
            <div>
                <b>Menaklukkan Pertahanan (Normal Attack)</b><br />
                Pemain menyerang dengan tipe <b>Serangan Normal (Normal Attack)</b>. Serangan penjarahan (<i>Raid</i>) tidak dapat mengambil alih kepemilikan benteng.<br />
                Jika seluruh garnisun bertahan (penjaga kuno atau pemain bertahan) berhasil dibasmi habis dan penyerang memiliki prajurit yang masih hidup, <b>kendali benteng resmi jatuh ke aliansi penyerang!</b>
            </div>
        </div>

        <div class="b-step">
            <div class="b-step-num">3</div>
            <div>
                <b>Timer Bertahan 12 Jam (Hold Timer)</b><br />
                Begitu benteng direbut, pengatur waktu bertahan (12 jam berturut-turut) dimulai. Jika ada aliansi lain yang berhasil merebut benteng, timer langsung <b>direset kembali ke 0 detik</b>.
            </div>
        </div>

        <div class="b-step">
            <div class="b-step-num">4</div>
            <div>
                <b>Bala Bantuan Aliansi (Reinforcements)</b><br />
                Semua anggota dari aliansi penguasa benteng dapat mengirimkan pasukan bantuan ke (0|0). Kerja sama aliansi sangat vital untuk mempertahankan benteng dari gempuran musuh!
            </div>
        </div>

        <div class="b-step">
            <div class="b-step-num">5</div>
            <div>
                <b>Uji Gelombang Pasukan Kuno (Bot Waves)</b><br />
                Setiap <b>2 jam sekali</b>, regu pasukan kuno (~30 prajurit) akan bergerak menuju benteng untuk menguji kesiapan garnisun pertahanan aliansi.
            </div>
        </div>
    </div>

    <!-- KARTU HADIAH & BERKAH KEMENANGAN -->
    <div class="b-card" style="background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%); border-color: #bbf7d0;">
        <h2><span>🎁</span> Hadiah Kemenangan &amp; Berkah Kedaulatan Aliansi</h2>
        <p>
            Aliansi pertama yang berhasil mempertahankan Benteng Kuno selama <b>12 Jam penuh tanpa terputus</b> akan dinyatakan sebagai <b>Pemenang Musim Benteng Kuno</b> dan berhak atas hadiah berlimpah:
        </p>

        <table class="b-table">
            <thead>
                <tr>
                    <th style="width:30%;">Kategori Hadiah</th>
                    <th style="width:25%;">Nilai / Bonus</th>
                    <th>Detail Manfaat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>SDA Langsung (Instant Loot)</b></td>
                    <td><span class="b-badge b-badge-green">35.000 Tiap Sumber Daya</span></td>
                    <td>
                        <img class="r1" src="img/x.gif" alt="Wood" title="Wood" /> 35.000 
                        <img class="r2" src="img/x.gif" alt="Clay" title="Clay" /> 35.000 
                        <img class="r3" src="img/x.gif" alt="Iron" title="Iron" /> 35.000 
                        <img class="r4" src="img/x.gif" alt="Crop" title="Crop" /> 35.000<br />
                        Dibagikan langsung ke seluruh desa milik anggota aliansi pemenang!
                    </td>
                </tr>
                <tr>
                    <td><b>Berkah Produksi SDA</b></td>
                    <td><span class="b-badge b-badge-gold">+10% Produksi SDA</span></td>
                    <td>Meningkatkan produksi Kayu, Tanah Liat, Besi, dan Gandum di seluruh desa anggota selama <b>3 Hari penuh</b>.</td>
                </tr>
                <tr>
                    <td><b>Berkah Kecepatan Pasukan</b></td>
                    <td><span class="b-badge b-badge-blue">+15% Kecepatan Pergerakan</span></td>
                    <td>Seluruh pasukan tempur dan pedagang anggota aliansi bergerak 15% lebih cepat selama <b>3 Hari</b>.</td>
                </tr>
                <tr>
                    <td><b>Berkah Pertahanan Kerajaan</b></td>
                    <td><span class="b-badge b-badge-green">+5% Bonus Pertahanan</span></td>
                    <td>Meningkatkan ketahanan dan pertahanan seluruh desa anggota aliansi dari serangan lawan selama <b>3 Hari</b>.</td>
                </tr>
                <tr>
                    <td><b>Aula Kehormatan (Hall of Fame)</b></td>
                    <td><span class="b-badge b-badge-gold">Abadi di Hall of Fame</span></td>
                    <td>Nama Aliansi, Tag, dan nama Panglima Perang tercatat abadi di Aula Kehormatan server sebagai juara resmi.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- KARTU STRATEGI ALIANSI -->
    <div class="b-card">
        <h2><span>💡</span> Tips &amp; Panduan Taktis Aliansi</h2>
        <ul style="margin:6px 0 0 16px; padding:0; font-size:11px;">
            <li><b>Sinkronisasi Serangan:</b> Kerahkan pasukan serbu terbaik dari beberapa anggota sekaligus agar benteng dapat langsung dibersihkan dalam satu gelombang.</li>
            <li><b>Pengawalan Kilat:</b> Begitu anggota aliansi berhasil menaklukkan benteng, anggota lain harus langsung mengirimkan bala bantuan pertahanan (defenders) dalam hitungan menit.</li>
            <li><b>Kombinasi Satuan Pertahanan:</b> Siapkan pertahanan seimbang antara anti-infanteri (seperti Praetorian / Phalanx) dan anti-kavaleri (seperti Spearman / Pikeman).</li>
            <li><b>Pantau Live Hub:</b> Selalu pantau log pertempuran dan pergerakan pasukan secara langsung melalui menu <a href="bastion.php" target="_blank"><b>🏰 Benteng Kuno</b></a>.</li>
        </ul>
    </div>

    <!-- TOMBOL NAVIGASI MANUAL KLASIK -->
    <div style="margin-top: 14px; text-align: center;">
        <map id="nav" name="nav">
            <area href="manual.php?s=1" title="<?php echo BACK; ?>" coords="0,0,45,18" shape="rect" alt="" />
            <area href="manual.php?s=1" title="<?php echo OVERVIEW; ?>" coords="46,0,70,18" shape="rect" alt="" />
            <area href="bastion.php" target="_blank" title="Buka Benteng Kuno" coords="71,0,116,18" shape="rect" alt="" />
        </map>
        <img usemap="#nav" src="img/x.gif" class="navi" alt="" />
    </div>
</div>
