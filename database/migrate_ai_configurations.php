<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;

Env::load(__DIR__ . '/../.env');

echo "=======================================================\n";
echo " MIGRATION: AI CONFIGURATIONS (PRD 1.0)\n";
echo "=======================================================\n\n";

try {
    $pdo = Database::getConnection();

    $sql = "
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
    ";

    $pdo->exec($sql);
    echo "✅ Tabel 'ai_configurations' berhasil dibuat / sudah ada.\n";
} catch (\Exception $e) {
    echo "❌ Gagal migrasi: " . $e->getMessage() . "\n";
    exit(1);
}
