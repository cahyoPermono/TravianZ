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
define("BOT_AI_MAX_DISTANCE", 35.0);         // Jarak maksimum (radius kotak) target raid bot
define("BOT_AI_MAX_CONCURRENT_ATTACKS", 1);  // Jumlah serangan keluar maksimal dari 1 desa bot bersamaan
define("BOT_MAX_FIELD_LEVEL", 6);            // Batas level ladang sumber daya pada fase awal
```

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

