# Fitur Preman & Ormas Desa Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Menghadirkan sistem event acak gangguan desa bertema preman/ormas ("Ormas Setempat", "Komplotan Herkules", "Pemuda Pancasila", "Banser Pencari Rawon di Kondangan") yang datang tiba-tiba selama 1 jam hingga 3 hari, memberikan debuff unik pada sumber daya, gandum, perniagaan, dan moral, dengan opsi pengusiran lewat uang damai/proposal atau razia pasukan.

**Architecture:** Modul mesin mandiri `GameEngine/Ormas.php` yang mengelola siklus hidup gangguan desa pada tabel `village_ormas`. Pengaruh debuff dihubungkan secara bersih ke `Village.php` (pengali produksi & extra upkeep gandum), `Market.php` (kecepatan pedagang), dan `Building.php` (durasi pembangunan). Pemain dapat berinteraksi melalui `village_action.php` (bayar upeti atau kerahkan pasukan untuk mengusir). Disertai widget visual interaktif di `dorf1.php` / `dorf2.php` dan kontrol Admin CP.

**Tech Stack:** PHP 8.x, MySQL/MariaDB, Vanilla CSS/HTML, TravianZ Game Engine, PHPUnit/CLI Test Harness.

---

## 4 Varian Preman & Debuff Kejelekan Masing-Masing

1. **Pemuda Pancasila (Pungli Loreng Jeruk - Type 1)**
   - *Deskripsi:* Mendatangi desa membawa map proposal pembangunan posko darurat serta memungut jatah parkir liar di gerbang desa.
   - *Debuff:*
     * Produksi Kayu & Tanah Liat berkurang **-25%** (proyek material dihadang).
     * Pemalakan Gandum berkurang **-15%**.
     * Waktu konstruksi bangunan melambat **+20%** (tukang bangunan diganggu).
   - *Syarat Pengusiran:*
     * Opsi Damai: Beri sumbangan proposal (Kayu 300, Tanah Liat 300, Gandum 200).
     * Opsi Gebuk: Kerahkan minimal **15 prajurit tempur** untuk membubarkan posko.

2. **Banser Pencari Rawon di Kondangan (Type 2)**
   - *Deskripsi:* Berkerumun di balai desa, menghabiskan seluruh persediaan rawon, prasmanan, dan nasi kebuli milik warga.
   - *Debuff:*
     * Gandum tersedot rakus: **Extra Upkeep Konsumsi Gandum +40 gandum/jam** (atau penalti produksi gandum **-30%**).
     * Poin Budaya (CP) desa berkurang **-30%** (suasana desa bising dan gaduh).
     * Biaya mengadakan perayaan pesta desa naik **+50%** (jatah makanan habis).
   - *Syarat Pengusiran:*
     * Opsi Damai: Beri bekal bungkusan rawon perpisahan (Gandum 800).
     * Opsi Gebuk: Kerahkan minimal **20 prajurit** untuk menggerebek kerumunan.

3. **Komplotan Herkules (Penguasa Lapak Pasar - Type 3)**
   - *Deskripsi:* Preman kekar menduduki pasar desa dan memungut retribusi liar dari setiap pedagang dan karavan yang lewat.
   - *Debuff:*
     * Kecepatan Pedagang melambat **-35%** (dipalak di pos jalan keluar desa).
     * Produksi Tambang Besi & Kayu terpotong **-25%**.
     * Kapasitas rampokan (*carry capacity*) pasukan raid desa berkurang **-15%** (sebagian jarahan dipotong preman).
   - *Syarat Pengusiran:*
     * Opsi Damai: Bayar uang keamanan pasar (Besi 400, Kayu 400, Gandum 300).
     * Opsi Gebuk: Kerahkan minimal **25 prajurit** bersenjata tajam untuk adu mekanik.

4. **Ormas Setempat (Jatah Bulanan & Uang Rokok - Type 4)**
   - *Deskripsi:* Nongkrong di gardu pos kamling meminta jatah uang rokok dan kopi harian kepada warga desa.
   - *Debuff:*
     * Pungli merata: Seluruh produksi sumber daya (Kayu, Liat, Besi, Gandum) terpotong **-15%**.
     * Nilai serangan pasukan (*attack power*) berkurang **-10%** (moral warga dan prajurit tertekan).
   - *Syarat Pengusiran:*
     * Opsi Damai: Bayar uang rokok & kopi murah (Kayu 150, Liat 150, Besi 150, Gandum 150).
     * Opsi Gebuk: Kerahkan minimal **10 prajurit** untuk mengusir mereka keluar gerbang.

---

### Task 1: Engine Core & Database Schema (`GameEngine/Ormas.php`)

**Files:**
- Create: `GameEngine/Ormas.php`
- Create: `tests/OrmasTest.php`

**Step 1: Write the failing test**
Create `tests/OrmasTest.php` to verify:
- Table creation and schema validation (`village_ormas`).
- Metadata retrieval for all 4 Ormas types (`getOrmasInfo`).
- Arrival generation with duration (1 hour to 72 hours).
- Multipliers: `getProductionMultiplier`, `getExtraCropUpkeep`, `getMerchantSpeedMultiplier`, `getBuildTimeMultiplier`.
- Expire / dismissal methods (`dismissOrmas`).

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: FAIL with "Class Ormas not found".

**Step 3: Implement minimal `GameEngine/Ormas.php`**
Implement:
- Constants: `TYPE_PP = 1`, `TYPE_BANSER = 2`, `TYPE_HERKULES = 3`, `TYPE_SETEMPAT = 4`.
- `ensureTable()`: Creates table `TB_PREFIX . village_ormas` with columns `(vref, ormas_type, severity, start_time, end_time, created_at)`.
- `getVillageOrmas(int $vref)`: Retrieves active ormas with automatic expiry check.
- `dispatchOrmas(int $vref, int $type, ?int $duration = null)`: Places ormas into a village.
- `dismissOrmas(int $vref)`: Removes ormas from village.
- `getOrmasInfo(int $type)`: Returns Indonesian metadata, icons, badges, and debuff formulas.
- `getProductionMultiplier(int $vref, string $res)`: Returns float (e.g. 0.75 for wood/clay under PP).
- `getExtraCropUpkeep(int $vref)`: Returns int extra crop consumed (e.g. 40 under Banser).
- `getMerchantSpeedMultiplier(int $vref)`: Returns float (0.65 under Herkules).
- `getBuildTimeMultiplier(int $vref)`: Returns float (1.20 under PP).

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: PASS "All Ormas Unit Tests Passed Successfully!".

**Step 5: Commit**
```bash
git add GameEngine/Ormas.php tests/OrmasTest.php
git commit -m "feat: add Ormas village nuisance engine core and unit tests"
```

---

### Task 2: Game Engine Hooks Integration (`Village.php`, `Market.php`, `Building.php`)

**Files:**
- Modify: `GameEngine/Village.php`
- Modify: `GameEngine/Market.php`
- Modify: `GameEngine/Building.php`
- Modify: `tests/OrmasTest.php`

**Step 1: Write the test verifying engine hook effects**
Add assertions in `tests/OrmasTest.php` testing:
- Production calculation applies `Ormas::getProductionMultiplier`.
- Upkeep calculation adds `Ormas::getExtraCropUpkeep`.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: FAIL on hook integration check.

**Step 3: Implement engine modifications**
1. In `GameEngine/Village.php`:
   - Include `GameEngine/Ormas.php`.
   - In `calculateProduction()`:
     Add `$amount *= Ormas::getProductionMultiplier((int)$this->wid, $type);`
     Add extra crop upkeep to `$this->production['crop']` via `Ormas::getExtraCropUpkeep((int)$this->wid)`.
2. In `GameEngine/Market.php`:
   - Apply `Ormas::getMerchantSpeedMultiplier($wid)` to merchant duration calculation.
3. In `GameEngine/Building.php`:
   - Multiply construction time by `Ormas::getBuildTimeMultiplier($wid)`.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/Village.php GameEngine/Market.php GameEngine/Building.php tests/OrmasTest.php
git commit -m "feat: integrate Ormas production, upkeep, build time, and merchant speed hooks"
```

---

### Task 3: Player Interaction Endpoints (`village_action.php`)

**Files:**
- Modify: `village_action.php`
- Test: `tests/OrmasTest.php`

**Step 1: Write test for bribe payment and troop raid dismissal**
Add tests in `tests/OrmasTest.php`:
- `bribeOrmas`: deducts resources from village and clears ormas.
- `raidOrmas`: checks standing troops in village; if sufficient, expels ormas and awards success message.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: FAIL on endpoint actions.

**Step 3: Implement interaction handlers in `village_action.php`**
Add POST actions:
1. `action=ormas_bribe`:
   - Verifies session and ownership of village.
   - Checks if village has required resources.
   - Deducts resources via `$database->modifyResource()`.
   - Calls `Ormas::dismissOrmas($wid)`.
   - Sets flash message: "Ormas berhasil dibubarkan setelah menerima uang damai/proposal!".
2. `action=ormas_fight`:
   - Checks standing combat troops in `TB_PREFIX . units`.
   - Requires minimum troops based on type (10-25 troops).
   - If troops suffice: dismisses ormas and sets celebratory flash message: "Pasukan Anda berhasil menggerebek dan mengusir Ormas kocar-kacir keluar desa!".
   - If troops insufficient: sets warning message: "Pasukan Anda terlalu sedikit untuk menghadapi komplotan preman ini!".

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add village_action.php tests/OrmasTest.php
git commit -m "feat: add player interaction actions for Ormas bribe and troop raid"
```

---

### Task 4: Automated Random Spawning (`GameEngine/Automation.php` & `config.php`)

**Files:**
- Modify: `GameEngine/config.php`
- Modify: `GameEngine/Automation.php`
- Modify: `GameEngine/Ormas.php`

**Step 1: Write test for automated spawn tick**
Add test asserting `Ormas::processSpawns()` selects eligible non-beginner villages and does not spam already afflicted villages.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: FAIL on `processSpawns`.

**Step 3: Implement periodic spawn logic**
1. In `GameEngine/config.php`:
   - Add `define("ORMAS_SYSTEM_ENABLED", true);`
   - Add `define("ORMAS_SPAWN_CHANCE", 20); // 20% chance per tick to target a village`
   - Add `define("ORMAS_MIN_POP", 50); // Minimum village pop to attract thugs`
2. In `GameEngine/Ormas.php`:
   - Implement `processSpawns()`: picks 1-2 random player villages with `pop >= ORMAS_MIN_POP`, `protect < time()`, and no active ormas.
   - Sets random duration: weighted roll between 1 hour, 3 hours, 12 hours, 24 hours, up to 72 hours (3 days).
3. In `GameEngine/Automation.php`:
   - Call `Ormas::processSpawns()` on automation tick.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/config.php GameEngine/Automation.php GameEngine/Ormas.php tests/OrmasTest.php
git commit -m "feat: implement automated random Ormas spawning in automation cycle"
```

---

### Task 5: Village UI Widget (`Templates/ormas_widget.tpl`, `dorf1.php`, `dorf2.php`)

**Files:**
- Create: `Templates/ormas_widget.tpl`
- Modify: `dorf1.php`
- Modify: `dorf2.php`

**Step 1: Write template verification test**
Ensure `Templates/ormas_widget.tpl` includes valid HTML/PHP and handles both idle and afflicted village states without notices.

**Step 2: Run check to verify template renders cleanly**
Run: `php -l Templates/ormas_widget.tpl`
Expected: PASS syntax check.

**Step 3: Build interactive Ormas Card in `Templates/ormas_widget.tpl`**
- Custom warning badge (e.g. orange camo for PP, green-gold for Banser, iron red for Herkules, brown for Ormas Setempat).
- Dynamic countdown timer (`Berganti/Pergi dalam: HH:MM:SS`).
- Debuff pills with clear icons (e.g. 🌾 Gandum -30%, 🧱 Liat -25%, 🐎 Pedagang -35%).
- Two interactive buttons:
  - Form button 1: "💰 Bayar Uang Damai (Wood / Clay / Iron / Crop)"
  - Form button 2: "⚔️ Razia / Usir Pakai Pasukan (Min. X Prajurit)"
- Include in `dorf1.php` and `dorf2.php` directly beneath `Templates/weather_widget.tpl`.

**Step 4: Test in browser/docker**
Verify via CLI/curl or browser that `dorf1.php` displays ormas widget when active and hides when idle.

**Step 5: Commit**
```bash
git add Templates/ormas_widget.tpl dorf1.php dorf2.php
git commit -m "feat: add responsive interactive Ormas card to dorf1 and dorf2"
```

---

### Task 6: Admin CP Ormas Manager (`Admin/Templates/ormasManager.tpl`)

**Files:**
- Create: `Admin/Templates/ormasManager.tpl`
- Modify: `Admin/admin.php`
- Modify: `Admin/Templates/menu.tpl`

**Step 1: Write test for Admin manual dispatch & dismiss**
Ensure `Ormas::dispatchOrmas()` and `Ormas::dismissOrmas()` can be executed by Admin with arbitrary village target and custom duration.

**Step 2: Run test to verify it passes**
Run: `docker exec travianz-web php /var/www/html/tests/OrmasTest.php`
Expected: PASS.

**Step 3: Implement Admin Panel page**
1. In `Admin/Templates/ormasManager.tpl`:
   - Table of all villages currently afflicted by Ormas with remaining duration and cancel button.
   - Control card: **"🎯 Kirim Preman / Ormas ke Desa Target"**:
     * Dropdown / Input: Username / Koordinat X|Y / ID Desa.
     * Dropdown: Tipe Ormas (Pemuda Pancasila, Banser, Herkules, Ormas Setempat).
     * Dropdown: Durasi (1 Jam, 6 Jam, 24 Jam, 3 Hari).
     * Tombol: "Kirim Ormas Sekarang".
2. Register page `p=ormasManager` in `Admin/admin.php` and add link to `Admin/Templates/menu.tpl`.

**Step 4: Verify syntax & execution**
Run: `php -l Admin/Templates/ormasManager.tpl && php -l Admin/admin.php`
Expected: No syntax errors.

**Step 5: Commit**
```bash
git add Admin/Templates/ormasManager.tpl Admin/admin.php Admin/Templates/menu.tpl
git commit -m "feat: add Ormas Manager control panel in Admin CP"
```

---

### Task 7: Comprehensive Integration Testing & Packaging

**Files:**
- Test: `tests/OrmasTest.php`
- Update: `travianz_update_all_features.zip`

**Step 1: Run complete test suite**
Run:
```bash
docker exec travianz-web php /var/www/html/tests/OrmasTest.php
docker exec travianz-web php /var/www/html/tests/WeatherPlagueTest.php
docker exec travianz-web php /var/www/html/tests/BotAITest.php
```
Expected: All tests PASS.

**Step 2: Package all modified and new files into zip (excluding `config.php`)**
```bash
zip -r travianz_update_all_features.zip \
  GameEngine/Ormas.php \
  Templates/ormas_widget.tpl \
  Admin/Templates/ormasManager.tpl \
  village_action.php \
  GameEngine/Village.php \
  GameEngine/Market.php \
  GameEngine/Building.php \
  GameEngine/Automation.php \
  dorf1.php dorf2.php \
  Admin/admin.php Admin/Templates/menu.tpl
```

**Step 3: Commit and verify clean status**
```bash
git status
```
Expected: All changes tracked and clean.
