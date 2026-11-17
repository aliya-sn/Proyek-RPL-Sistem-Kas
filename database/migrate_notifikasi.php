<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;

Env::load(__DIR__ . '/../.env');

echo "=======================================================\n";
echo " MIGRATION: NOTIFIKASI WHATSAPP\n";
echo "=======================================================\n\n";

try {
    $pdo = Database::getConnection();
    
    $sql = "
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
    ";

    $pdo->exec($sql);
    echo "✅ Tabel 'notifikasi_whatsapp' berhasil dibuat / sudah ada.\n";
} catch (\Exception $e) {
    echo "❌ Gagal migrasi: " . $e->getMessage() . "\n";
    exit(1);
}
