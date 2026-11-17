-- ==============================================================================
-- SISTEM KEUANGAN KAS BERBASIS WEB DENGAN AI - DATABASE SCHEMA
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `sistem_kas_ai` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistem_kas_ai`;

-- 1. Table: Users
-- Kolom 'password' menyimpan bcrypt hash, bukan plaintext.
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `nama` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    `role` ENUM('super_user', 'anggota', 'bendahara') NOT NULL DEFAULT 'anggota',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: Anggota (Profil detail untuk user berrole 'anggota')
-- Relationship: users 1-to-0..1 anggota (user_id UNIQUE)
CREATE TABLE IF NOT EXISTS `anggota` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL UNIQUE,
    `nomor_identitas` VARCHAR(30) NOT NULL UNIQUE,
    `nama` VARCHAR(100) NOT NULL,
    `kelas` VARCHAR(20) NOT NULL,
    `jenis_kelamin` ENUM('L', 'P') NOT NULL,
    `nomor_kontak` VARCHAR(20) NULL,
    `status` ENUM('aktif', 'nonaktif', 'lulus') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_anggota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_anggota_kelas` (`kelas`),
    INDEX `idx_anggota_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: Audit Log
-- Relationship: users 1-to-many audit_log
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `aktivitas` VARCHAR(150) NOT NULL,
    `waktu` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `metadata` JSON NULL COMMENT 'Data terkait dalam format JSON',
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_audit_waktu` (`waktu`),
    INDEX `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: Kategori Transaksi
CREATE TABLE IF NOT EXISTS `kategori_transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kategori` VARCHAR(100) NOT NULL,
    `jenis` ENUM('masuk', 'keluar') NOT NULL,
    `deskripsi` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: Transaksi Kas
CREATE TABLE IF NOT EXISTS `transaksi_kas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_transaksi` VARCHAR(50) NOT NULL UNIQUE,
    `jenis` ENUM('masuk', 'keluar') NOT NULL,
    `kategori_id` INT NULL,
    `jumlah` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `keterangan` TEXT NOT NULL,
    `tanggal_transaksi` DATE NOT NULL,
    `created_by` INT NULL,
    `status` ENUM('aktif', 'void') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transaksi_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_transaksi` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_transaksi_tanggal` (`tanggal_transaksi`),
    INDEX `idx_transaksi_jenis` (`jenis`),
    INDEX `idx_transaksi_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table: Pembayaran Kas Anggota
CREATE TABLE IF NOT EXISTS `pembayaran_kas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anggota_id` INT NOT NULL,
    `transaksi_id` INT NULL,
    `periode_minggu` INT NOT NULL,
    `periode_bulan` INT NOT NULL,
    `periode_tahun` INT NOT NULL,
    `nominal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('lunas', 'belum_lunas') NOT NULL DEFAULT 'lunas',
    `tanggal_bayar` DATE NOT NULL,
    `petugas_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pembayaran_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pembayaran_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi_kas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pembayaran_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_pembayaran_anggota_periode` (`anggota_id`, `periode_tahun`, `periode_bulan`, `periode_minggu`),
    INDEX `idx_pembayaran_periode` (`periode_tahun`, `periode_bulan`, `periode_minggu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table: AI Chat History
CREATE TABLE IF NOT EXISTS `ai_chat_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_id` VARCHAR(100) NOT NULL,
    `role` ENUM('user', 'assistant', 'system') NOT NULL,
    `message` LONGTEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ai_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_ai_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table: Notifikasi WhatsApp (Fonnte API Log & History)
CREATE TABLE IF NOT EXISTS `notifikasi_whatsapp` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipe_notifikasi` ENUM('payment_confirmation', 'arrears_reminder') NOT NULL,
    `anggota_id` INT NOT NULL,
    `pembayaran_id` INT NULL COMMENT 'Referensi pembayaran jika konfirmasi bayar',
    `periode_bulan` INT NULL COMMENT 'Periode bulan terkait (1-12)',
    `periode_tahun` INT NULL COMMENT 'Periode tahun terkait',
    `nomor_tujuan` VARCHAR(30) NOT NULL,
    `pesan` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `message_id` VARCHAR(100) NULL COMMENT 'ID pesan dari provider Fonnte',
    `provider_status` VARCHAR(50) NULL COMMENT 'Status response provider (misal: pending/accepted)',
    `provider_response` TEXT NULL COMMENT 'Sanitized raw response dari provider',
    `error_message` VARCHAR(255) NULL COMMENT 'Ringkasan pesan error sanitasi jika gagal',
    `sent_by` INT NULL COMMENT 'User yang menginisiasi pengiriman',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifikasi_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifikasi_pembayaran` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran_kas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_notifikasi_user` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_notifikasi_anggota` (`anggota_id`),
    INDEX `idx_notifikasi_tipe` (`tipe_notifikasi`),
    INDEX `idx_notifikasi_status` (`status`),
    INDEX `idx_notifikasi_pembayaran` (`pembayaran_id`),
    INDEX `idx_notifikasi_periode` (`periode_tahun`, `periode_bulan`),
    INDEX `idx_notifikasi_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table: AI Configurations (Gemini API Key & Model Configuration)
CREATE TABLE IF NOT EXISTS `ai_configurations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'gemini',
    `encrypted_api_key` TEXT NOT NULL,
    `active_model` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    CONSTRAINT `fk_ai_config_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_ai_config_active` (`is_active`, `provider`),
    INDEX `idx_ai_config_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


