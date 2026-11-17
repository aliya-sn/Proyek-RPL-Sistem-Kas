# CI/CD REPORT

## 1. File Workflow yang Dibuat

- `.github/workflows/ci.yml`

## 2. Trigger Pipeline

- `push`
- `pull_request`

## 3. Versi PHP dan Node.js

- PHP: `8.3`
- Node.js: `22`

## 4. Job yang Tersedia

### `php-unit`
- Checkout repository.
- Setup PHP 8.3 dengan ekstensi yang dibutuhkan.
- Validasi `composer.json`.
- Install dependency Composer.
- Lint seluruh file PHP.
- Menjalankan PHPUnit testsuite `Unit`.

### `node-unit`
- Checkout repository.
- Setup Node.js 22.
- Install dependency dengan `npm ci`.
- Validasi sintaks `server.js`.
- Menjalankan Jest unit test (`npm run test:unit`).
- Menjalankan legacy Node.js AI test (`npm test`).

### `integration`
- Berjalan setelah `php-unit` dan `node-unit` berhasil.
- Menyiapkan service container MySQL 8.0.
- Membuat file `.env` khusus CI dengan database testing `sistem_kas_test`.
- Menjalankan migrasi database testing.
- Menjalankan integration test:
  - `test_final_qa.php`
  - `test_security.php`
  - `test_laporan.php`
  - `test_ai_assistant.php`

## 5. Dependencies yang Diinstal

### PHP
- Dependency dari `composer.json` / `composer.lock`.
- PHPUnit 12.5.44 sebagai dev dependency.

### Node.js
- Dependency dari `ai-service/package.json` / `ai-service/package-lock.json`.
- Jest 30.5.2 sebagai dev dependency.

## 6. Pengujian yang Dijalankan

### Unit
- PHP: 14 test.
- Node.js: 9 test.

### Integration/Functional
- `test_final_qa.php`
- `test_security.php`
- `test_laporan.php`
- `test_ai_assistant.php`
- `ai-service/test/chat.test.js`

## 7. Hasil Validasi YAML

- File `.github/workflows/ci.yml` berhasil divalidasi menggunakan PyYAML.
- Struktur YAML dinyatakan valid.

## 8. Hasil Pengujian Lokal

- `php vendor/bin/phpunit --testsuite Unit`: 14 PASS / 0 FAIL
- `npm run test:unit`: 9 PASS / 0 FAIL
- `npm test` (legacy Node.js): 22 PASS / 0 FAIL
- `node --check server.js`: PASS
- PHP lint pada file aplikasi: PASS

## 9. Persyaratan GitHub Secrets

Tidak ada secret wajib untuk pipeline standar.

- `AI_API_KEY` sengaja dikosongkan di CI agar integrasi AI menggunakan fallback lokal/mock.
- Database CI menggunakan kredensial lokal container (`root/root`) dan database `sistem_kas_test`, bukan database produksi.
- Jika ingin menguji provider AI nyata, gunakan GitHub Secrets secara opsional, tetapi pipeline standar tidak memerlukannya.

## 10. Cara Melihat Hasil Pipeline di GitHub

1. Push repository ke GitHub.
2. Buka tab **Actions** pada repository.
3. Pilih workflow **CI**.
4. Lihat status setiap job: `php-unit`, `node-unit`, dan `integration`.
5. Buka detail job untuk melihat log test dan status PASS/FAIL.

## Catatan

Pipeline belum dieksekusi di GitHub pada saat laporan ini dibuat. Validasi dilakukan secara lokal terhadap YAML, dependency, dan perintah testing. Tidak ada API Key atau password produksi yang ditulis di workflow.