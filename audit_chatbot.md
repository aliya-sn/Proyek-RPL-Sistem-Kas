# AUDIT DATABASE DAN MULTI-TABEL CHATBOT+ SISTEM KAS

## 1. STRUKTUR DATABASE AKTUAL (Database: `sistem_kas_ai`)

Database yang digunakan oleh aplikasi Sistem Kas adalah MySQL/MariaDB dengan nama database `sistem_kas_ai` (didefinisikan pada `.env`). Terdapat 7 tabel utama yang mengelola seluruh operasional keuangan, pengguna, dan interaksi sistem.

---

### TABEL 1: `users`
Menyimpan akun pengguna terautentikasi sistem.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `username` (VARCHAR(50), UNIQUE, NOT NULL)
  - `nama` (VARCHAR(100), NULL): Nama lengkap pengguna / profil
  - `password` (VARCHAR(255), NOT NULL): Hash bcrypt kata sandi (tidak boleh diekspos ke AI)
  - `role` (ENUM('super_user', 'anggota', 'bendahara'), NOT NULL, DEFAULT 'anggota')
  - `status` (ENUM('active', 'inactive'), NOT NULL, DEFAULT 'active')
  - `created_at` (TIMESTAMP), `updated_at` (TIMESTAMP)
- **Catatan Keamanan & Hak Akses:**
  - Hanya role `super_user` yang berhak mengetahui jumlah akun, daftar akun, dan rincian role pengguna.
  - Role `bendahara` dan `anggota` dibatasi dari akses data akun demi menjaga keamanan.

---

### TABEL 2: `anggota`
Menyimpan profil detail anggota kas organisasi.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `user_id` (INT, UNIQUE, Foreign Key -> `users.id` ON DELETE SET NULL)
  - `nomor_identitas` (VARCHAR(30), UNIQUE, NOT NULL): Nomor Induk Siswa / Identitas (NIS)
  - `nama` (VARCHAR(100), NOT NULL): Nama lengkap anggota
  - `kelas` (VARCHAR(20), NOT NULL): Kelas anggota (e.g. "XII TKJ 2")
  - `jenis_kelamin` (ENUM('L', 'P'), NOT NULL)
  - `nomor_kontak` (VARCHAR(20), NULL)
  - `status` (ENUM('aktif', 'nonaktif', 'lulus'), NOT NULL, DEFAULT 'aktif')
  - `created_at` (TIMESTAMP), `updated_at` (TIMESTAMP)
- **Relasi:**
  - 1-to-1 opsional dengan `users`: `anggota.user_id = users.id`.

---

### TABEL 3: `kategori_transaksi`
Menyimpan kategori transaksi penerimaan dan pengeluaran kas.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `nama_kategori` (VARCHAR(100), NOT NULL): Nama kategori (misal: "Iuran Kas Rutin", "Sumbangan / Donasi", "Konsumsi Kegiatan", "Alat Tulis & Administrasi", "Dana Sosial")
  - `jenis` (ENUM('masuk', 'keluar'), NOT NULL)
  - `deskripsi` (TEXT, NULL): Keterangan tujuan kategori (misal: "Santunan dan bantuan sosial", "Pembayaran kas mingguan anggota")
  - `created_at` (TIMESTAMP)
- **Peran Kunci dalam Penelusuran Tujuan Pengeluaran:**
  - Menjadi sumber fallback utama ketika kolom `transaksi_kas.keterangan` kosong atau bernilai `-`. AI dapat mengetahui tujuan pengeluaran dari `kategori_transaksi.nama_kategori` dan `kategori_transaksi.deskripsi`.

---

### TABEL 4: `transaksi_kas`
Buku kas utama mencatat mutasi keuangan penerimaan (masuk) dan pengeluaran (keluar).
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `kode_transaksi` (VARCHAR(50), UNIQUE, NOT NULL): e.g. "TRK-20261024103029-606"
  - `jenis` (ENUM('masuk', 'keluar'), NOT NULL)
  - `kategori_id` (INT, NULL, Foreign Key -> `kategori_transaksi.id` ON DELETE SET NULL)
  - `jumlah` (DECIMAL(15,2), NOT NULL, DEFAULT 0.00): Nominal transaksi
  - `keterangan` (TEXT, NOT NULL): Catatan rincian keperluan transaksi
  - `tanggal_transaksi` (DATE, NOT NULL): Tanggal pencatatan transaksi
  - `created_by` (INT, NULL, Foreign Key -> `users.id` ON DELETE SET NULL): User pencatat
  - `status` (ENUM('aktif', 'void'), NOT NULL, DEFAULT 'aktif'): Transaksi void tidak dihitung dalam saldo aktual
  - `created_at` (TIMESTAMP), `updated_at` (TIMESTAMP)
- **Relasi Multi-Tabel:**
  - `transaksi_kas.kategori_id` -> `kategori_transaksi.id`
  - `transaksi_kas.created_by` -> `users.id` (untuk mengetahui nama pencatat transaksi)
  - `transaksi_kas.id` -> `pembayaran_kas.transaksi_id` (link ke iuran anggota jika transaksi berasal dari iuran)

---

### TABEL 5: `pembayaran_kas`
Mencatat rincian pembayaran iuran mingguan per anggota.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `anggota_id` (INT, NOT NULL, Foreign Key -> `anggota.id` ON DELETE CASCADE)
  - `transaksi_id` (INT, NULL, Foreign Key -> `transaksi_kas.id` ON DELETE SET NULL)
  - `periode_minggu` (INT, NOT NULL): Minggu ke-1 s/d ke-4
  - `periode_bulan` (INT, NOT NULL): Bulan 1 s/d 12
  - `periode_tahun` (INT, NOT NULL): Tahun (e.g. 2026)
  - `nominal` (DECIMAL(15,2), NOT NULL, DEFAULT 0.00)
  - `status` (ENUM('lunas', 'belum_lunas'), NOT NULL, DEFAULT 'lunas')
  - `tanggal_bayar` (DATE, NOT NULL)
  - `petugas_id` (INT, NULL, Foreign Key -> `users.id` ON DELETE SET NULL)
  - `created_at` (TIMESTAMP)
- **Relasi:**
  - `pembayaran_kas.anggota_id` -> `anggota.id` (nama, kelas, NIS)
  - `pembayaran_kas.transaksi_id` -> `transaksi_kas.id`
  - `pembayaran_kas.petugas_id` -> `users.id` (nama petugas penerima)

---

### TABEL 6: `audit_log`
Merekam riwayat aktivitas sistem untuk keperluan kepatuhan dan audit keamanan.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `user_id` (INT, NULL, Foreign Key -> `users.id` ON DELETE SET NULL)
  - `aktivitas` (VARCHAR(150), NOT NULL)
  - `waktu` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
  - `metadata` (JSON, NULL)
- **Hak Akses:** Hanya dapat diakses oleh role `super_user`.

---

### TABEL 7: `ai_chat_history`
Merekam percakapan sesi pengguna dengan chatbot AI.
- **Kolom Penting:**
  - `id` (INT, Primary Key, Auto Increment)
  - `user_id` (INT, Foreign Key -> `users.id` ON DELETE CASCADE)
  - `session_id` (VARCHAR(100), NOT NULL)
  - `role` (ENUM('user', 'assistant', 'system'))
  - `message` (LONGTEXT, NOT NULL)
  - `created_at` (TIMESTAMP)

---

## 2. RELASI ANTARTABEL DAN POLA JOIN APLIKASI

```
+------------------+         +-----------------------+         +------------------+
|      users       |<--------|     transaksi_kas     |-------->|kategori_transaksi|
|                  |created_b|                       |kategori_|                  |
+------------------+         +-----------------------+         +------------------+
         ^                              ^
         | user_id                      | transaksi_id
         |                              |
+------------------+         +-----------------------+
|     anggota      |<--------|    pembayaran_kas     |
|                  |anggota_i|                       |
+------------------+         +-----------------------+
```

### Pola JOIN Valid:
1. **Laporan / Penelusuran Pengeluaran:**
   ```sql
   SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah, t.keterangan, t.status,
          k.nama_kategori, k.deskripsi AS deskripsi_kategori,
          u.nama AS nama_pencatat, u.username AS username_pencatat
   FROM transaksi_kas t
   LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
   LEFT JOIN users u ON u.id = t.created_by
   WHERE t.jenis = 'keluar' AND t.status = 'aktif';
   ```
2. **Laporan Pembayaran Iuran Anggota:**
   ```sql
   SELECT p.id, p.tanggal_bayar, p.periode_minggu, p.periode_bulan, p.periode_tahun,
          p.nominal, p.status, a.nama AS nama_anggota, a.nomor_identitas, a.kelas,
          u.username AS nama_petugas, t.status AS status_transaksi
   FROM pembayaran_kas p
   JOIN anggota a ON a.id = p.anggota_id
   LEFT JOIN users u ON u.id = p.petugas_id
   LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
   WHERE p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif');
   ```

### Pencegahan Duplikasi Nominal (Anti Double Counting):
- Perhitungan saldo kas **TIDAK BOLEH** menjumlahkan `pembayaran_kas` bersama `transaksi_kas` dalam satu query JOIN agregasi, karena setiap pembayaran kas sudah dibuatkan record induknya di `transaksi_kas` jenis 'masuk'.
- Saldo kas dihitung murni dari:
  $$\text{Saldo Kas} = \sum (\text{transaksi\_kas.jumlah}[\text{masuk, aktif}]) - \sum (\text{transaksi\_kas.jumlah}[\text{keluar, aktif}])$$

---

## 3. SUMBER KEBENARAN DATA KEUANGAN (SINGLE SOURCE OF TRUTH)

| Data Keuangan | Sumber Kebenaran (Source of Truth) | Kondisi Aktual Database |
|---------------|-----------------------------------|-------------------------|
| **Total Pemasukan** | `SELECT SUM(jumlah) FROM transaksi_kas WHERE jenis='masuk' AND status='aktif'` | Rp 1.680.000,00 (21 transaksi aktif) |
| **Total Pengeluaran** | `SELECT SUM(jumlah) FROM transaksi_kas WHERE jenis='keluar' AND status='aktif'` | Rp 500.000,00 (1 transaksi aktif) |
| **Saldo Kas Saat Ini** | Total Pemasukan - Total Pengeluaran | Rp 1.180.000,00 |
| **Transaksi Void** | `SELECT SUM(jumlah) FROM transaksi_kas WHERE status='void'` | Rp 750.000,00 (10 transaksi void) |
| **Total Pembayaran Iuran** | `SELECT SUM(nominal) FROM pembayaran_kas WHERE status='lunas'` | Rp 1.580.000,00 (20 pembayaran) |
| **Selisih Saldo vs Iuran** | Total Pemasukan (1.680.000) - Total Pembayaran Iuran (1.580.000) | Rp 100.000,00 (1 transaksi pemasukan kas langsung) |
| **Pengeluaran Rp 500.000** | ID: 73, Tgl: 2026-10-24, Kategori: Dana Sosial, Pencatat: Aulia Putri | Keterangan di DB: `-`, Deskripsi Kategori: "Santunan dan bantuan sosial" |

---

## 4. MAPPING SUMBER DATA UNTUK SETIAP JENIS PERTANYAAN CHATBOT

| Jenis Pertanyaan Chatbot | Tabel Terlibat | Cara Menjawab & Integritas Data |
|--------------------------|----------------|---------------------------------|
| **1. Saldo Kas Sekarang** | `transaksi_kas` | Mengambil akumulasi transaksi aktif (masuk - keluar) = Rp 1.180.000. |
| **2. Total Pemasukan & Pengeluaran** | `transaksi_kas` | Pemasukan Rp 1.680.000 dan pengeluaran Rp 500.000 secara terpisah. |
| **3. Tujuan Pengeluaran Tertentu (e.g. 500 ribu)** | `transaksi_kas` JOIN `kategori_transaksi` JOIN `users` | 1. Cari nominal Rp 500.000 jenis 'keluar'.<br>2. Ambil `keterangan`, `nama_kategori`, `deskripsi`, tanggal, kode transaksi, dan `created_by` (nama pencatat).<br>3. Jika keterangan `-`, jelaskan secara jujur bahwa kolom keterangan transaksi diisi `-`, namun kategori transaksi adalah **Dana Sosial** ("Santunan dan bantuan sosial") yang dicatat oleh pengguna terkait. **DILARANG mengarang tujuan pengeluaran.** |
| **4. Pengeluaran Terbesar** | `transaksi_kas` (ORDER BY jumlah DESC LIMIT 1) JOIN `kategori_transaksi` JOIN `users` | Tampilkan pengeluaran terbesar beserta tanggal, kategori, keterangan aktual, dan pencatatnya. |
| **5. Kandidat Transaksi Ganda (Multiple Candidates)** | `transaksi_kas` JOIN `kategori_transaksi` | Jika terdapat lebih dari 1 transaksi dengan nominal sama, tampilkan seluruh kandidat dengan tanggal, kode, dan keterangannya. |
| **6. Pencatat Transaksi** | `transaksi_kas` JOIN `users` | Ambil `users.nama` dan `users.username` dari `created_by` (hanya jika role pengakses berhak melihat, yaitu Super User dan Bendahara). |
| **7. Pembayaran Iuran & Tunggakan** | `pembayaran_kas` JOIN `anggota` | Hitung anggota yang sudah lunas dan yang menunggak dari rekap pembayaran mingguan. |
| **8. Filter Rentang Waktu (Bulan/Tahun/Tanggal)** | `transaksi_kas` & `pembayaran_kas` | Filter WHERE pada `tanggal_transaksi` / `tanggal_bayar` atau `periode_bulan`/`periode_tahun`. |
| **9. Pertanyaan Follow-Up ("pengeluaran itu buat apa?", "siapa pencatatnya?")** | Konteks sesi & transaksi terakhir yang sedang dibahas | Hubungkan rujukan kata ganti dengan transaksi relevan yang ditemukan sebelumnya. |
| **10. Data Fiktif / Tidak Ditemukan** | Query validasi database | Jika transaksi tidak ditemukan, jawab jujur bahwa transaksi tidak ada di database, tanpa halusinasi. |
| **11. Hak Akses (Role Isolation)** | Server Session & Backend Filter | Anggota dibatasi hanya untuk informasi pembayaran pribadinya dan saldo umum, tidak boleh melihat rincian pengeluaran/pencatat internal. |

---

## 5. AKAR MASALAH PADA IMPLEMENTASI SEBELUMNYA

1. **Regex Handler Pengeluaran yang Terlalu Luas:**
   - Pertanyaan yang memuat kata "pengeluaran" langsung ditangkap oleh regex umum dan dijawab dengan teks template ringkasan bulanan: `"Pengeluaran kas Oktober 2026 sebesar Rp 500.000..."`.
2. **Tidak Ada Ekstraksi Nominal & Tujuan:**
   - Engine tidak mengekstrak angka nominal (500 ribu, 500.000), kata kunci tujuan ("buat apa", "untuk apa", "keperluan"), atau siapa pencatatnya ("siapa yang mencatat").
3. **Data Aggregator Kurang Mengirimkan Detail Multi-Tabel:**
   - `FinancialDataAggregator` hanya mengambil 5 sampel pengeluaran terpotong tanpa menyertakan JOIN `kategori_transaksi.deskripsi`, nama lengkap pencatat (`users.nama`), dan evaluasi query targeted khusus transaksi pengeluaran.
4. **Kurangnya Penanganan Keterangan Kosong:**
   - Ketika kolom `keterangan` di database bernilai `-`, sistem belum memiliki fallback cerdas untuk menjelaskan bahwa keterangan catatan kosong namun kategorinya adalah Dana Sosial dengan deskripsi santunan/bantuan sosial.
