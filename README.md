# Sistem Keuangan Kas Berbasis Web dengan AI

## 1. Identitas Proyek

- **Nama aplikasi:** Sistem Keuangan Kas Berbasis Web dengan AI
- **Nama mahasiswa:** Aliya Sabihatunnisa
- **NIM:** 41257179
- **Kelompok:** 1
- **Tema/topik kelompok:** Sistem Kas
---

## 2. Deskripsi Aplikasi

Sistem Keuangan Kas Berbasis Web dengan AI adalah aplikasi web untuk mengelola keuangan kas organisasi/kelas, termasuk pencatatan transaksi pemasukan dan pengeluaran, pembayaran kas anggota, laporan keuangan, dan analisis keuangan berbantuan AI.

Aplikasi ini dibangun untuk menyelesaikan permasalahan pencatatan kas yang manual, sulit diaudit, dan kurang transparan. Dengan sistem ini, admin, bendahara, dan anggota dapat melihat data keuangan sesuai hak akses masing-masing.

Pengguna aplikasi:
- **Super User (Admin):** mengelola anggota, transaksi, pembayaran, laporan, konfigurasi AI, dan notifikasi WhatsApp.
- **Bendahara:** mengelola transaksi, pembayaran, laporan, dan asisten AI terbatas.
- **Anggota:** melihat dashboard pribadi, riwayat pembayaran, status tunggakan, dan asisten AI pribadi.

Fitur utama pengelolaan kas:
- Autentikasi dan otorisasi berbasis role.
- Manajemen data anggota.
- Pencatatan transaksi pemasukan dan pengeluaran.
- Pembayaran kas anggota dengan transaksi atomik database.
- Laporan pemasukan, pengeluaran, arus kas, pembayaran, dan tunggakan.
- Export/cetak laporan.
- Audit log aktivitas.

---

## 3. Fitur AI

Aplikasi memiliki fitur **AI Financial Assistant** yang bersifat read-only. AI hanya menganalisis data yang telah diagregasi oleh backend dan tidak melakukan mutasi database.

Teknologi/provider AI yang didukung oleh kode:
- Google Gemini API (`gemini-1.5-flash`).
- OpenAI API.
- Groq API.

Cara aplikasi mengakses layanan AI:
1. Frontend mengirim pertanyaan ke backend PHP.
2. Backend PHP mengagregasi konteks data keuangan nyata melalui `FinancialDataAggregator`.
3. Backend PHP mendelegasikan request ke microservice Node.js pada endpoint `/api/chat`.
4. Microservice Node.js memanggil provider AI eksternal jika API key tersedia.
5. Jika API key tidak tersedia atau request gagal, sistem menggunakan fallback **Local Analytical Engine**.

Fungsi AI dalam membantu pengguna:
- Menjawab pertanyaan saldo kas, pemasukan, pengeluaran, tunggakan, dan pembayaran.
- Memberikan ringkasan kondisi keuangan.
- Menjaga isolasi data anggota agar anggota hanya melihat data pribadinya.

Perbedaan AI provider/API nyata dan fallback lokal:
- **Provider/API nyata:** digunakan jika API key valid dan request berhasil; respons berasal dari layanan AI eksternal.
- **Fallback lokal:** digunakan jika API key tidak dikonfigurasi, request gagal, atau query terstruktur; respons dihasilkan dari perhitungan lokal berdasarkan data database.

Catatan: konfigurasi default `ai-service/.env` memiliki `AI_API_KEY` kosong. Pada pipeline CI, AI key juga sengaja dikosongkan agar pengujian menggunakan fallback. Integrasi kode provider nyata telah diuji dengan mock API, tetapi belum dibuktikan dengan API key valid pada environment produksi.

---

## 4. Teknologi yang Digunakan

- **Bahasa backend:** PHP >= 8.1
- **Database:** MySQL / MariaDB
- **Arsitektur:** Custom Lightweight PHP MVC (PSR-4 Autoloading)
- **Frontend:** HTML5, Vanilla CSS, Bootstrap 5.3.3, Bootstrap Icons, Google Fonts (Plus Jakarta Sans)
- **Microservice AI:** Node.js, Express
- **Testing PHP:** PHPUnit 12.5.44
- **Testing Node.js:** Jest 30.5.2
- **CI/CD:** GitHub Actions
- **Library Node.js:** cors, dotenv, express

---

## 5. Struktur Proyek

```text
Sistem Kas/
├── .github/
│   └── workflows/
│       └── ci.yml
├── ai-service/
│   ├── src/
│   ├── test/
│   └── tests/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
├── config/
├── database/
├── public/
├── routes/
├── tests/
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 6. Instalasi dan Konfigurasi

### 6.1 Persyaratan Perangkat Lunak

- PHP >= 8.1
- Composer
- MySQL / MariaDB
- Web server (Apache/Nginx) atau XAMPP/Laragon
- Node.js dan npm (untuk microservice AI)

### 6.2 Clone atau Salin Repository

Pastikan folder proyek berada di direktori web server lokal, misalnya:

```text
C:/xampp/htdocs/Project RPL/Sistem Kas
```

### 6.3 Instalasi Dependency

PHP:

```bash
composer install
```

Node.js (microservice AI):

```bash
cd ai-service
npm ci
```

### 6.4 Konfigurasi Environment

Salin `.env.example` menjadi `.env`:

```bash
cp .env.example .env
```

Sesuaikan konfigurasi database dan AI:

```env
APP_NAME="Sistem Keuangan Kas Berbasis Web dengan AI"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/Project%20RPL/Sistem%20Kas/public

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistem_kas_ai
DB_USERNAME=root
DB_PASSWORD=

AI_PROVIDER=gemini
AI_API_KEY=
AI_MODEL=gemini-1.5-flash
AI_SERVICE_ENABLED=true
AI_SERVICE_URL=http://127.0.0.1:5000/api/chat
AI_SERVICE_SECRET=sistem_kas_ai_node_secret_key_2026_auth
AI_SERVICE_TIMEOUT=12
```

Untuk menggunakan provider AI nyata, isi `AI_API_KEY` dengan API key yang valid. Jika dibiarkan kosong, aplikasi tetap berjalan menggunakan fallback lokal.

### 6.5 Konfigurasi Database

Jalankan migrasi database:

```bash
php database/migrate.php
```

Skrip ini akan:
1. Membuat database `sistem_kas_ai` jika belum ada.
2. Menjalankan skema dari `database/schema.sql`.
3. Menambahkan data awal kategori transaksi, akun admin, dan akun anggota uji coba.

### 6.6 Menjalankan Aplikasi PHP

Pastikan Apache/Nginx berjalan dan folder proyek dapat diakses melalui browser. Contoh URL:

```text
http://localhost/Project%20RPL/Sistem%20Kas/public
```

### 6.7 Menjalankan Microservice AI

```bash
cd ai-service
npm start
```

Service berjalan pada:

```text
http://127.0.0.1:5000
```

Endpoint health check:

```text
http://127.0.0.1:5000/health
```

---

## 7. Pengujian

### PHPUnit Unit Test

```bash
php vendor/bin/phpunit
# atau
composer test
composer test:unit
```

### Jest Unit Test

```bash
cd ai-service
npm run test:unit
```

### Legacy Node.js AI Test

```bash
cd ai-service
npm test
```

### Integration / Functional Test

```bash
php test_final_qa.php
php test_security.php
php test_laporan.php
php test_ai_assistant.php
```

### Seluruh Node.js Test

```bash
cd ai-service
npm run test:all
```

---

## 8. CI/CD

Workflow tersedia pada:

```text
.github/workflows/ci.yml
```

Trigger:
- `push`
- `pull_request`

Job yang tersedia:
- `php-unit`: setup PHP, install Composer, lint PHP, jalankan PHPUnit Unit.
- `node-unit`: setup Node.js, install npm, validasi sintaks, jalankan Jest dan legacy Node.js test.
- `integration`: service MySQL, migrasi database testing, menjalankan integration/functional test.

Workflow telah dibuat dan divalidasi secara lokal. Workflow belum dapat disebut berhasil di GitHub sebelum ada bukti eksekusi pada tab Actions repository.

---

## 9. Video Progres dan Demo

- **Link Video:** [ISI LINK VIDEO PROGRES 1]

---

## 10. Akun Bawaan (Development Credentials)

| Role | Username | Password | Deskripsi Akses |
| :--- | :--- | :--- | :--- |
| **Super User** | `admin` | `admin123` | Akses Administrasi Penuh & Laporan Eksekutif |
| **Anggota 1** | `ahmad.rizky` | `anggota123` | Anggota Kelas XII RPL 1 |
| **Anggota 2** | `siti.nurhaliza` | `anggota123` | Anggota Kelas XII RPL 2 |

*Catatan: Selalu ubah password atau hapus akun seeder ini sebelum di-deploy ke lingkungan production.*

---

## 11. Catatan Keamanan

- Jangan commit file `.env` berisi secret nyata.
- Ganti `AI_SERVICE_SECRET` dan kredensial database pada environment produksi.
- Aktifkan HTTPS dan `SESSION_SECURE_COOKIE=true` pada production.