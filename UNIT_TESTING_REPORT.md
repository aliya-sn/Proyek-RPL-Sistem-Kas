# UNIT TESTING REPORT

## 1. Framework Testing yang Digunakan

### PHP
- **Framework:** PHPUnit 12.5.44
- **Konfigurasi:** `phpunit.xml`
- **Autoload:** `vendor/autoload.php` (Composer PSR-4 `App\\`)

### Node.js
- **Framework:** Jest 30.5.2
- **Konfigurasi:** script pada `ai-service/package.json`
- **Runtime:** Node.js 22.x

## 2. File Konfigurasi yang Dibuat/Diubah

- `phpunit.xml` — konfigurasi testsuite `Unit` untuk direktori `tests/Unit`.
- `composer.json` — ditambahkan script:
  - `composer test`
  - `composer test:unit`
- `ai-service/package.json` — ditambahkan script:
  - `npm run test:unit`
  - `npm run test:all`

## 3. File Test yang Dibuat

### PHP Unit Tests
- `tests/Unit/Core/EnvTest.php` — 2 test
- `tests/Unit/Services/EncryptionServiceTest.php` — 5 test
- `tests/Unit/Services/AIServiceTest.php` — 7 test

Total PHP unit tests: **14 tests, 30 assertions, 0 failures**.

### Node.js Unit Tests
- `ai-service/tests/unit/promptBuilder.test.js` — 3 test
- `ai-service/tests/unit/aiService.test.js` — 6 test

Total Node.js unit tests: **9 tests, 0 failures**.

## 4. Modul Aplikasi yang Diuji

### PHP
- `App\Core\Env` — load file, get value, default value.
- `App\Services\EncryptionService` — enkripsi/dekripsi round-trip, payload invalid, masking API key.
- `App\Services\AIService` — deteksi query terstruktur, deteksi identitas bot/user, pembentukan respons identitas.

### Node.js
- `buildSystemPrompt()` — konteks database, identitas pengguna, role, anti-hallucination, compact mode.
- `processQuery()` — structured query, identitas bot, provider Gemini/OpenAI/Groq dengan mock API, fallback saat API gagal.

## 5. Hasil Pengujian

### Unit Test PHP
- Perintah: `php vendor/bin/phpunit`
- Tests: 14
- Assertions: 30
- PASS: 14
- FAIL: 0

### Unit Test Node.js
- Perintah: `npm run test:unit`
- Tests: 9
- PASS: 9
- FAIL: 0

### Integration/Functional Test Lama (tetap dipertahankan)
- `php test_final_qa.php`: 13 PASS / 0 FAIL
- `php test_security.php`: 22 PASS / 0 FAIL
- `php test_laporan.php`: 20 PASS / 0 FAIL
- `php test_ai_assistant.php`: 14 PASS / 0 FAIL
- `node ai-service/test/chat.test.js`: 22 PASS / 0 FAIL

## 6. Bukti Mock Bekerja Sesuai Tujuan

- Pengujian Node.js mengganti `global.fetch` dengan mock yang mengembalikan respons Gemini, OpenAI, dan Groq.
- Test memverifikasi jawaban dan provider dari mock, bukan dari API nyata.
- Test fallback memaksa `fetch` melempar error dan memverifikasi sistem kembali ke `nodejs_ai_engine (analytical_core)`.
- Test structured query memastikan `fetch` tidak dipanggil untuk query terstruktur.
- Pengujian PHP menggunakan `.env` nyata hanya untuk konfigurasi, tanpa memanggil API eksternal.

## 7. Cara Menjalankan Testing

### PHP Unit Test
```bash
php vendor/bin/phpunit
# atau
composer test
composer test:unit
```

### Node.js Unit Test
```bash
cd ai-service
npm run test:unit
```

### Seluruh Node.js Test
```bash
cd ai-service
npm run test:all
```

### Integration/Functional Test Lama
```bash
php test_final_qa.php
php test_security.php
php test_laporan.php
php test_ai_assistant.php
node ai-service/test/chat.test.js
```

## Catatan
Pengujian lama tidak dihapus. Unit test baru dipisahkan di `tests/Unit` dan `ai-service/tests/unit`. Belum ada CI/CD atau perubahan README pada tahap ini.