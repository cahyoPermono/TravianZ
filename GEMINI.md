# TravianZ Project Rules & Guidelines

Aturan-aturan di bawah ini otomatis berlaku untuk setiap sesi/conversation AI di workspace ini.

## 1. Zip Patch Update (Wajib)
- Setiap kali selesai membuat perbaikan bug atau fitur baru, selalu sertakan file yang dimodifikasi ke dalam arsip zip: `travianz_patch_update.zip` di root project (`zip -u travianz_patch_update.zip <file1> <file2> ...`).
- Hal ini memudahkan user langsung mengunggah file patch ke hosting production public_html tanpa perlu mencari file satu per satu.

## 2. Proteksi Konfigurasi (Config Protection)
- **DILARANG KERAS** mengubah, menimpa, atau mereset file konfigurasi server/database (seperti `.env`, konfigurasi koneksi DB, atau file credential hosting).
- Konfigurasi yang ada harus selalu dipertahankan utuh dan aman.

## 3. Verifikasi & Testing
- Setiap file PHP yang dimodifikasi harus divalidasi sintaksnya (`php -l`).
- Jika membuat/menjalankan unit test, jalankan di dalam container Docker yang sedang aktif:
  `docker exec travianz-web php <path-ke-test>`

## 4. Komunikasi & Standar Kode
- Gunakan Bahasa Indonesia yang ramah, jelas, dan profesional dalam setiap penjelasan.
- Selalu cantumkan tautan file lokal (`file://...`) untuk file-file yang diubah.
- Pertahankan komentar asli dan integritas kode yang tidak berkaitan dengan task.

