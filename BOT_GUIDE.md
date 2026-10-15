# Panduan Penggunaan & Kontrol Bot AI (TravianZ)

Dokumen ini berisi panduan lengkap untuk mengelola, mengontrol, dan mengonfigurasi sistem **Bot AI Otomatis** pada server TravianZ.

---

## 1. Ringkasan Fitur Bot AI

Bot AI dirancang untuk mensimulasikan pemain lawan otomatis dalam mode multiplayer/co-op dengan karakteristik yang ramah pemain awal (*early-game friendly*):

- **Auto-Build**: Bot meningkatkan ladang sumber daya (*Woodcutter, Clay Pit, Iron Mine, Cropland*) dan membangun infrastruktur desa secara bertahap (*Main Building, Warehouse, Granary, Rally Point, Barracks, Wall, Cranny*).
- **Auto-Train**: Setelah memiliki *Barracks*, bot melatih infanteri dasar sesuai sukunya secara bertahap hingga batas kapasitas awal (*troop cap*).
- **Auto-Raid**: Bot mengirim pasukan perampokan kecil (5–15 prajurit) ke desa-desa terdekat di peta.
- **Fair Play & Perlindungan Pemula**: Bot **TIDAK AKAN** menyerang pemain yang masih memiliki perlindungan pemula (*Beginner Protection*), tidak menyerang admin/Multihunter, dan tidak mengeroyok desa yang sudah memiliki 2+ serangan masuk.
- **Farming Target**: Gudang sumber daya bot secara alami terisi, sehingga pemain dapat menyerang dan mem-farming desa bot untuk mempercepat pertumbuhan desa sendiri.

---

## 2. Perintah CLI (Cara Mengontrol Bot)

Semua perintah kontrol dijalankan melalui terminal menggunakan container `travianz-web`.

### A. Melihat Daftar Bot yang Sedang Aktif
Menampilkan ID akun, nama bot, suku, nama desa, koordinat `(X|Y)`, dan jumlah populasinya:
```bash
docker exec travianz-web php scripts/bot_manager.php list
```

### B. Menambah / Spawn Bot Baru
Membuat sejumlah bot baru di kuadran peta acak dengan ladang level 1 dan kapasitas gudang awal:
```bash
# Menambah 5 bot secara acak:
docker exec travianz-web php scripts/bot_manager.php spawn 5

# Menambah 10 bot secara acak:
docker exec travianz-web php scripts/bot_manager.php spawn 10

# Menambah 3 bot dengan suku tertentu (misal suku 3 = Gaul):
# Pilihan suku: 1=Roman, 2=Teuton, 3=Gaul, 6=Hun, 7=Egyptian, 8=Spartan, 9=Viking
docker exec travianz-web php scripts/bot_manager.php spawn 3 3
```

### C. Memicu 1 Putaran Aksi Bot Secara Manual (*Force Run*)
Memaksa semua bot untuk segera melakukan evaluasi pembangunan, pelatihan pasukan, dan penyerangan raid, serta menampilkan log aksinya langsung di terminal:
```bash
docker exec travianz-web php scripts/bot_manager.php run
```

### D. Membersihkan / Menghapus Semua Akun Bot
Menghapus seluruh akun bot dan desanya dari database (dapat digunakan jika ingin mereset bot dari awal):
```bash
docker exec travianz-web php scripts/bot_manager.php clean
```

---

## 3. Konfigurasi Parameter Bot AI

Pengaturan bot dapat disesuaikan pada file `GameEngine/config.php`:

```php
// ***** BOT AI System
define("BOT_AI_ENABLED", true);               // true = Bot aktif, false = Bot nonaktif
define("BOT_AI_INTERVAL", 120);              // Jeda waktu antar aksi bot dalam detik (default: 2 menit)
define("BOT_AI_MAX_TROOPS", 45);             // Batas maksimum pasukan infanteri per desa bot
define("BOT_AI_MIN_RAID_TROOPS", 6);         // Jumlah minimal pasukan sebelum bot boleh mengirim raid
define("BOT_AI_ATTACK_CHANCE", 35);          // Persentase peluang bot melancarkan raid per tick (1-100%)
define("BOT_AI_MAX_DISTANCE", 70.0);         // Jarak maksimum (radius kotak) target raid bot (2x lipat lebih luas)
define("BOT_AI_MAX_CONCURRENT_ATTACKS", 1);  // Jumlah serangan keluar maksimal dari 1 desa bot bersamaan
define("BOT_AI_REVENGE_ENABLED", true);       // Prioritaskan serang balik pemain yang menyerang bot / aliansi bot
define("BOT_AI_REVENGE_MAX_DISTANCE", 250.0); // Jarak maksimal balas dendam (default 250 tile / set 0 untuk seluruh map)
define("BOT_MAX_FIELD_LEVEL", 6);            // Batas level ladang sumber daya pada fase awal
```

### Fitur Unggulan Balas Dendam (Global Alliance Revenge):
- **Dual-Channel Detection (Real-Time & Laporan)**:
  1. **Serangan Balasan Saat Ini (Counter-Strike)**: Bot langsung mendeteksi jika musuh sedang berbaris menyerang desa bot atau rekan aliansinya saat ini juga, dan langsung menyerang balik desa asal musuh selagi pasukan musuh di jalan!
  2. **Laporan Tempur (ndata)**: Memeriksa laporan tempur 24 jam terakhir dari seluruh anggota aliansi dan membalas dendam kepada desa penyerang.
- **Jangkauan Luas & Fleksibel**: Balas dendam menjangkau radius hingga 250 tile (`BOT_AI_REVENGE_MAX_DISTANCE`), atau seluruh map jika diset 0.
- **Aktif 24 Jam Penuh**: Balas dendam tidak terhalang oleh jam tidur malam dan melewati roll persentase peluang random (100% langsung membalas).
- **Kekuatan Lebih Besar**: Serangan balik menggunakan *Normal Attack* dengan kekuatan armada hingga 50% pasukan dan mengikutsertakan Hero jika sehat.

### Tips Penyesuaian:
- **Ingin bot lebih agresif?** Naikkan `BOT_AI_ATTACK_CHANCE` (misal ke `60` atau `80`) dan kurangi `BOT_AI_INTERVAL` (misal ke `60`).
- **Ingin bot lebih pasif/santai?** Turunkan `BOT_AI_ATTACK_CHANCE` ke `15` atau `20`.
- **Ingin pasukan bot lebih banyak?** Naikkan `BOT_AI_MAX_TROOPS` (misal ke `100` atau `150`).

---

## 4. Mekanisme Eksekusi Otomatis

Sistem Bot AI terintegrasi langsung dengan mesin otomatisasi TravianZ (`GameEngine/Automation.php`):
1. **Otomatis saat Bermain di Web**: Setiap kali pemain membuka halaman web TravianZ (seperti `dorf1.php`, `dorf2.php`, `karte.php`, dll), game engine secara otomatis memeriksa apakah sudah lewat `BOT_AI_INTERVAL` detik. Jika ya, siklus bot akan berjalan di latar belakang tanpa mengganggu kecepatan loading web.
2. **Background Cron (Opsional)**: Jika server ingin terus berjalan mandiri meskipun tidak ada pemain yang membuka browser, cron runner dapat dipanggil secara berkala:
   ```bash
   docker exec travianz-web php cron.php --once
   ```

---

## 5. Referensi Suku, Tembok, dan Pasukan Bot

| ID Suku | Nama Suku | Pasukan yang Dilatih | Jenis Tembok Pertahanan |
| :---: | :--- | :--- | :--- |
| **1** | Roman | Legionnaire (`u1`) | City Wall (Tipe 31) |
| **2** | Teuton | Clubswinger (`u11`) | Earth Wall (Tipe 32) |
| **3** | Gaul | Phalanx (`u21`) | Palisade (Tipe 33) |
| **6** | Hun | Mercenary (`u51`) | Makeshift Wall (Tipe 42) |
| **7** | Egyptian | Slave Militia (`u61`) | Stone Wall (Tipe 43) |
| **8** | Spartan | Hoplite (`u71`) | Spartan Wall (Tipe 47) |
| **9** | Viking | Thrall (`u81`) | Wooden Wall (Tipe 50) |

---

## 6. Generator Nama & Penyamaran Alami (Natural Player Simulation)

Sistem bot menggunakan generator nama pintar di `GameEngine/NameGenerator.php` sehingga semua bot tampak seperti pemain manusia biasa tanpa embel-embel atau awalan `Bot_`:

1. **Dual-Source Generation (API + Bank Lokal)**:
   - **Online API**: Memanfaatkan endpoint `randomuser.me` untuk mengambil nama pengguna nyata secara dinamis.
   - **Curated Name Bank (Fallback & Themed)**: Jika offline atau API timeout, generator mengambil nama dari ratusan kombinasi nama otentik:
     - **Tema Suku / Sejarah**: Nama-nama jenderal/tokoh sesuai sukunya (Romawi: *Marcus, Aurelius, Cassius*; Galia: *Cavarnos, Asterix, Casticus*; Teuton: *Arminius, Alaric, Siegfried*; Hun: *Attila, Bleda, Mundzuk*; Mesir: *Ramses, Imhotep, Horus*; Sparta: *Leonidas, Brasidas*; Viking: *Ragnar, Bjorn, Ivar*).
     - **Handle Gamer Modern & Tahun Lahir**: *Alex_94, ShadowRider, Tyler, Kimberly99, IronClad, Redsnake376*.
     - **Nama Pemain Indonesia**: *Agung, BimaSakti, Dimas99, Arya_K, Pandu_36, BayuPratama*.
2. **Nama Desa Alami**:
   - Desa bot dinamai secara natural sesuai sukunya atau gaya pemukiman klasik (contoh: *Sunrise, Burdigala, Aswan, Gudvangen, Camelot, Steppe_Hold, Stonehaven, Nuremberg, Singhasari, Valhalla*), bukan default "*username's village*".
3. **Bio Profil Alami**:
   - Di profil akun (`spieler.php`), bot memiliki deskripsi acak seperti *"Building my empire stone by stone."*, *"Just casual gameplay."*, atau *"Trade and peace welcome!"*.
4. **Penandaan Bot Tanpa Label Publik**:
   - Bot ditandai secara internal di database menggunakan kolom `is_bot = 1` pada tabel `users`.
   - Tidak ada tulisan `[#BOT]` di profil publik, sehingga di papan peringkat (*statistiken.php*), profil pemain, maupun di peta (*karte.php*), bot tidak dapat dibedakan dari pemain asli.

---

## 7. Tips Strategi untuk Pemain

1. **Memanfaatkan Perlindungan Pemula**:
   - Selama akun Anda masih berstatus *protect*, bot **tidak akan** menyerang Anda.
   - Manfaatkan waktu ini untuk menaikkan ladang sumber daya dan membangun *Cranny (Gua)* serta *Wall (Tembok)*.
2. **Farming Desa Bot**:
   - Desa bot memiliki produksi sumber daya yang stabil.
   - Buat *Rally Point* dan *Barracks*, latih beberapa prajurit, lalu kirim *Raid* ke koordinat bot terdekat untuk mengambil sumber daya mereka.
3. **Mendeteksi Serangan Masuk**:
   - Jika masa proteksi Anda telah habis dan desa Anda diserang oleh bot, Anda akan melihat ikon pedang bersilang di menu atas beserta timer kedatangan di *Rally Point*.
   - Pasukan raid bot berjumlah kecil (5–15 orang). *Cranny* level tinggi dapat menyembunyikan semua sumber daya Anda sehingga bot pulang dengan tangan hampa.

---

## 8. Fitur Hero T4 dari Awal & Sistem Oasis Opsional

Server kini telah dilengkapi fitur **Hero dari Awal Permainan** persis seperti di Travian T4 beserta penyempurnaan sistem **Hero's Mansion Opsional**:

1. **Auto-Provisioning**:
   - Setiap akun baru yang mendaftar (termasuk bot dan pemain yang sudah ada) otomatis langsung memiliki 1 Hero hidup di desanya sesuai sukunya masing-masing (Level 0, 100% Health, 5 Poin Atribut, 100 Silver).

2. **Halaman `hero.php` Mandiri (T4 Style)**:
   - Pemain dapat mengklik potret Hero di bar atas atau membuka URL `hero.php` kapan saja untuk:
     - **Hero Overview**: Mengalokasikan poin atribut (Offence, Defence, Off-Bonus, Def-Bonus, Regeneration, Resources) dan menghidupkan kembali (*revive*) Hero jika gugur.
     - **Items / Inventory** (`hero.php?t4tab=items`): Memakai perlengkapan, helm, senjata, baju zirah, dan sepatu.
     - **Adventures** (`hero.php?t4tab=adventures`): Mengirim Hero berpetualang mencari XP, perak, dan item.
     - **Auctions** (`hero.php?t4tab=auction`): Pelelangan item perlengkapan.
     - **Oasis Management** (`hero.php?t4tab=land`): Melihat oasis yang dikuasai desa saat ini, koordinat, bonus sumber daya, serta tombol pelepasan oasis tanpa perlu membangun Hero's Mansion.

3. **Penaklukan Oasis Berdasarkan Level Hero (Hero's Mansion Jadi Opsional)**:
   - Pemain **TIDAK DIWAJIBKAN** membangun *Hero's Mansion* untuk menaklukkan oasis di peta.
   - Slot oasis kini otomatis terbuka berdasarkan **Level Hero** atau level *Hero's Mansion* (mana saja yang lebih tinggi):
     - **Level 10**: Membuka slot oasis ke-1.
     - **Level 15**: Membuka slot oasis ke-2.
     - **Level 20**: Membuka slot oasis ke-3 (maksimal 3 oasis per desa).
   - Selama Hero Anda sudah mencapai level 10 dan Anda mengalahkan hewan liar di oasis dalam radius 3 kotak, oasis dapat langsung ditaklukkan dengan serangan biasa bersama Hero.

4. **Buff Pasif Hero's Mansion (Manfaat Jika Dibangun)**:
   - Walaupun opsional, membangun *Hero's Mansion* tetap memberikan keuntungan pasif yang sangat besar bagi Hero:
     - **Regenerasi HP Pasif Tambahan**: Memberikan **+3 HP / hari** per level gedung (*Hero's Mansion* Level 10 = +30 HP/hari, Level 20 = +60 HP/hari).
     - **Diskon Waktu Revive**: Mempercepat waktu pemulihan/pelatihan kembali Hero yang gugur sebesar **-2.5% per level** (*Hero's Mansion* Level 10 = -25% lebih cepat, Level 20 = -50% lebih cepat).
     - **Slot Cadangan**: Jika Hero masih level rendah tetapi Anda sudah membangun mansion, slot oasis tetap aktif mengikuti level mansion.

5. **Konfigurasi Server**:
   - Diatur pada `GameEngine/config.php`:
     - `define("HERO_FROM_START", true);`
     - `define("HERO_MANSION_OPTIONAL", true);`
     - `define("HERO_MANSION_REGEN_PER_LEVEL", 3);`
     - `define("HERO_MANSION_REVIVE_DISCOUNT", 0.025);`

---

## 9. Formula Pertumbuhan Alami Bot Berbasis Usia Akun (Natural Age Pacing)

Untuk mencegah bot melesat secara tidak realistis dalam hitungan jam (seperti mencapai populasi 400+ dan ribuan pasukan hanya dalam 1 hari), sistem telah dilengkapi **pembatas usia akun (*Account Age-Gated Progression*)**:

1. **Plafon Populasi Maksimal per Usia**:
   - **Hari ke-1 (Usia 0–24 Jam)**: Maksimal **~48–56 populasi** (desa pemula yang wajar: ladang lvl 2, MB lvl 3, Warehouse/Granary/Barracks/Wall lvl 1).
   - **Hari ke-2 (Usia 24–48 Jam)**: Maksimal **~75–85 populasi** (ladang lvl 3).
   - **Hari ke-3 (Usia 48–72 Jam)**: Maksimal **~110–130 populasi** (ladang lvl 4, mulai kavaleri).
   - Bot tidak akan melakukan upgrade gedung jika populasinya telah menyentuh batas wajar usianya.
   - Jika bot terlanjur overgrown (> 1.5x plafon), bot secara otomatis menormalkan kembali bangunannya.

2. **Plafon Level Ladang Sumber Daya**:
   - **Hari ke-1**: Maksimal level **2**.
   - **Hari ke-2**: Maksimal level **3**.
   - **Hari ke-3**: Maksimal level **4**.

3. **Batasan Pasukan & Jenis Unit Berbasis Usia**:
   - Rasio pasukan dibatasi **25%–35% dari populasi** desa bot (bukan 180%).
   - **Hari ke-1**: Maksimal **20–25 pasukan total**, dan **hanya infanteri dasar** (contoh: *Clubswinger / Legionnaire / Phalanx*). Kavaleri dan senjata pengepungan (*catapult / ram*) **dikunci total**.
   - **Hari ke-2.5+**: Kandang Kuda (*Stable*) baru boleh dibangun untuk melatih unit kavaleri awal.
   - **Hari ke-4.5+**: Bengkel Senjata (*Workshop*) baru boleh dibangun untuk senjata pengepungan.

4. **Pencegahan Akumulasi & Auto-Trimming**:
   - Setiap kali bot memeriksa pelatihan militer, jika total pasukan di desa bot melebihi 1.5x batas usianya (misal karena sisa bot lama), sistem secara otomatis memangkas (*auto-trim*) pasukan tersebut kembali ke batas wajar.
   - Seluruh antrean konstruksi (`bdata`), antrean pelatihan militer (`training`), dan penelitian (`research`) yang tertunda dibersihkan saat normalisasi agar gedung atau pasukan tidak melonjak kembali.

---

## 10. Fitur Admin: Troop Manager (Tambah Pasukan) & Normalisasi Bot

Tersedia antarmuka admin lengkap untuk mengganti kerugian pasukan pemain atau menyeimbangkan bot yang ada:

### A. Admin Troop Manager (Kompensasi / Edit Pasukan)
- **Akses**: Masuk ke **Admin Control Panel (ACP)** -> Menu Sidebar **⚔️ Troop Manager (Tambah Pasukan)** atau URL `admin.php?p=addTroops`.
- **Fitur Utama**:
  - **Banner Kompensasi Instan Vee**: Sistem otomatis mendeteksi akun pemain **Vee** (Desa: *Fuuuiiyooohh*) di baris teratas dengan tombol 1-klik untuk memberikan kompensasi penuh.
  - **Tombol Kompensasi Cepat 1-Klik**:
    - `🎁 Berikan Kompensasi Penuh (+197 & +8 & Revive Hero)`: Langsung tersedia di banner atas, tabel pencarian, dan di halaman edit desa.
    - Suku Teuton (Vee): Menambahkan **+197 Clubswinger (u11)**, **+8 Paladin (u15)**, serta **menghidupkan kembali Hero (Pahlawan) dengan 100% Health** langsung di desa.
    - Suku Romawi: +197 Legionnaire (u1), +8 Equites Imperatoris (u5), dan Revive Hero 100%.
    - Suku Gaul: +197 Phalanx (u21), +8 Theutates Thunder (u24), dan Revive Hero 100%.
  - **Pencarian Cepat**: Cari desa berdasarkan Nama Pemain, Nama Desa, ID Desa (`did`), atau Koordinat `(X|Y)`.
  - **Helper Input Cepat**: Tombol `+50`, `+100`, `+197` infanteri dan `+8`, `+50` kavaleri untuk mengisi form secara instan.
  - **Mode Input Aditif (`+`)**: Tambahkan jumlah pasukan tanpa menimpa jumlah yang ada.
  - **Mode Input Langsung**: Menetapkan angka pasti pasukan garnisun (`u1`–`u100`) dan status Hero di desa.

### B. Tombol Normalisasi Pasukan & Bangunan Bot (1-Klik Rebalance)
- **Akses**: Masuk ke **Admin Panel -> Bot AI Manager** (`admin.php?p=botManager`), klik tombol merah muda di pojok kanan atas: **⚖️ Normalisasi Pasukan & Bangunan Bot**.
- **Fungsi**:
  - Pilihan Target Usia Bot: **Usia Hari 1 (Default)**, Hari 2, Hari 3, atau Otomatis.
  - Langsung mereset seluruh 18 ladang ke level 2, merapikan gedung kota (Main Building lvl 3, Warehouse/Granary/Barracks/Wall/Cranny lvl 1), mencopot gedung lanjut (Stable/Workshop/Academy/Market/Residence ke level 0), membersihkan antrean pending (`bdata`, `training`, `research`, `demolition`), dan menghitung ulang populasi menjadi ~48–56 pop/desa.
  - Memangkas ribuan pasukan bot berlebih kembali ke 20–25 prajurit dasar per desa (0 kavaleri, 0 senjata pengepungan).
  - Membersihkan pasukan bantuan bot (*enforcement*) agar bot tidak saling menumpuk garnisun puluhan ribu.

---

## 11. Mekanisme Produksi Sumber Daya Alami Bot (Anti-Infinite ATM / Natural Resource Economy)

Sebelum pembaruan ini, bot menggunakan injeksi flat buatan (`+250 * SPEED` per tick) yang menyebabkan desa bot langsung penuh kembali (**1.200 / 1.200 / 1.200 / 1.200**) dalam hitungan detik setelah dijarah pemain, sehingga bot bertindak seperti mesin ATM tanpa batas yang tidak realistis.

Sistem kini telah diperbaiki total menjadi **Sistem Ekonomi & Produksi Alami**:
1. **Produksi Berdasarkan Level Ladang Nyata**:
   - Produksi dihitung langsung dari level 18 ladang sumber daya di tabel `fdata` (Kayu, Tanah Liat, Besi, Gandum) menggunakan data standar Travian (`buidata.php`).
   - Bonus gedung olahan (Penggergajian Kayu lvl 5, Pabrik Batu Bata lvl 6, Pabrik Peleburan Besi lvl 7, Penggilingan Gandum lvl 8, dan Toko Roti lvl 9) turut dihitung.
   - Dikurangi konsumsi gandum oleh populasi (*crop upkeep*).
2. **Akumulasi Berdasarkan Selang Waktu Nyata (*Elapsed Time*)**:
   - Pertambahan sumber daya dihitung dari selisih waktu (`time() - lastupdate`), sama persis seperti desa pemain asli:
     $$\Delta \text{Resource} = \left(\frac{\text{Produksi per Jam}}{3600}\right) \times \Delta t$$
3. **Hasil Jarahan Bertahan Nyata (Tidak Langsung Penuh)**:
   - Ketika pemain menyerang dan menjarah desa bot, sumber daya yang terambil akan benar-benar berkurang di database.
   - Jika pemain kembali menyerang 2 menit kemudian, sumber daya bot hanya bertambah sedikit sesuai produksi 2 menit tersebut (misal hanya +10 s/d +20 sumber daya, bukan langsung 1.200!).
4. **Perlindungan Cranny (*Persembunyian*) Otomatis**:
   - Sumber daya bot dilindungi oleh gedung Persembunyian (*Cranny*) sesuai levelnya. Pemain hanya bisa menjarah sisa sumber daya di atas kapasitas persembunyian.
5. **Konstruksi & Pelatihan Mengonsumsi Sumber Daya**:
   - Tukang bangunan dan barak bot kini memeriksa kecukupan stok sumber daya. Jika desa bot baru saja dijarah habis (< 30 sumber daya), pembangunan gedung dan pelatihan pasukan akan **ditunda sementara** hingga ladang bot memproduksi cukup bahan baku.
6. **Normalisasi Cepat Lewat Admin Panel**:
   - Tombol **⚖️ Normalisasi Bot** di ACP secara otomatis menormalkan stok sumber daya bot lama yang terlanjur mentok di 1.200 menjadi kapasitas operasional wajar (~35% kapasitas gudang) dan menyinkronkan timestamp `lastupdate`.



