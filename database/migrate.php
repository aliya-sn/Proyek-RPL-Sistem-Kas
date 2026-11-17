<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;

// Load .env
Env::load(__DIR__ . '/../.env');

echo "=======================================================\n";
echo " SISTEM KEUANGAN KAS BERBASIS WEB DENGAN AI - MIGRATION\n";
echo "=======================================================\n\n";

$host = env('DB_HOST', '127.0.0.1');
$port = (int) env('DB_PORT', 3306);
$dbName = env('DB_DATABASE', 'sistem_kas_ai');
$user = env('DB_USERNAME', 'root');
$pass = env('DB_PASSWORD', '');

try {
    // =========================================================================
    // 1. Create database if not exists
    // =========================================================================
    echo "[1/4] Memeriksa & membuat database '{$dbName}' jika belum ada...\n";
    $rootPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "      -> Database '{$dbName}' siap.\n\n";

    // =========================================================================
    // 2. Drop existing tables for clean migration (dev only)
    // =========================================================================
    echo "[2/4] Membersihkan tabel lama (fresh migration)...\n";
    $pdo = Database::getConnection();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $tables = ['notifikasi_whatsapp', 'ai_chat_history', 'pembayaran_kas', 'transaksi_kas', 'kategori_transaksi', 'audit_log', 'audit_logs', 'anggota', 'users'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`;");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "      -> Tabel lama berhasil dihapus.\n\n";

    // =========================================================================
    // 3. Execute schema.sql
    // =========================================================================
    echo "[3/4] Mengeksekusi tabel dari database/schema.sql...\n";
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);
    echo "      -> Struktur tabel berhasil dibuat.\n\n";

    // =========================================================================
    // 4. Seed data
    // =========================================================================
    echo "[4/4] Menambahkan data awal (seeder)...\n";
    echo "      ⚠  Credential ini hanya untuk DEVELOPMENT. Jangan gunakan di production!\n\n";

    // -------------------------------------------------------------------------
    // Super User Pemilik: aulia.putri (Aulia Putri)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => 'aulia.putri']);
    if (!$stmt->fetch()) {
        $ownerInitialPassword = password_hash('AuliaPutri#2026Kas', PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $pdo->prepare("
            INSERT INTO users (username, nama, password, role, status, created_at)
            VALUES (:u, :n, :p, :r, :s, NOW())
        ");
        $ins->execute([
            'u' => 'aulia.putri',
            'n' => 'Aulia Putri',
            'p' => $ownerInitialPassword,
            'r' => 'super_user',
            's' => 'active',
        ]);
        echo "      -> Super User Pemilik dibuat: username='aulia.putri' / nama='Aulia Putri'\n";
    } else {
        echo "      -> Super User Pemilik 'aulia.putri' sudah ada.\n";
    }

    // -------------------------------------------------------------------------
    // Super User Legacy: admin / admin123 (tetap dipertahankan untuk kompatibilitas uji)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => 'admin']);
    if (!$stmt->fetch()) {
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $pdo->prepare("
            INSERT INTO users (username, nama, password, role, status, created_at)
            VALUES (:u, :n, :p, :r, :s, NOW())
        ");
        $ins->execute([
            'u' => 'admin',
            'n' => 'Administrator',
            'p' => $adminPassword,
            'r' => 'super_user',
            's' => 'active',
        ]);
        echo "      -> Super User Legacy dibuat: username='admin' / password='admin123'\n";
    } else {
        echo "      -> Super User Legacy 'admin' sudah ada.\n";
    }

    // -------------------------------------------------------------------------
    // Anggota 1: ahmad.rizky / anggota123
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => 'ahmad.rizky']);
    if (!$stmt->fetch()) {
        $anggota1Password = password_hash('anggota123', PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $pdo->prepare("
            INSERT INTO users (username, password, role, status, created_at)
            VALUES (:u, :p, :r, :s, NOW())
        ");
        $ins->execute([
            'u' => 'ahmad.rizky',
            'p' => $anggota1Password,
            'r' => 'anggota',
            's' => 'active',
        ]);
        $anggota1UserId = $pdo->lastInsertId();

        // Profil anggota
        $insAnggota = $pdo->prepare("
            INSERT INTO anggota (user_id, nomor_identitas, nama, kelas, jenis_kelamin, nomor_kontak, status, created_at)
            VALUES (:user_id, :nomor_identitas, :nama, :kelas, :jk, :kontak, 'aktif', NOW())
        ");
        $insAnggota->execute([
            'user_id' => $anggota1UserId,
            'nomor_identitas' => '20261001',
            'nama' => 'Ahmad Rizky',
            'kelas' => 'XII RPL 1',
            'jk' => 'L',
            'kontak' => '081234567890'
        ]);

        echo "      -> Anggota 1 dibuat: username='ahmad.rizky' / password='anggota123'\n";
        echo "         Profil: Ahmad Rizky — XII RPL 1 — NIS: 20261001\n";
    } else {
        echo "      -> User Anggota 'ahmad.rizky' sudah ada.\n";
    }

    // -------------------------------------------------------------------------
    // Anggota 2: siti.nurhaliza / anggota123
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => 'siti.nurhaliza']);
    if (!$stmt->fetch()) {
        $anggota2Password = password_hash('anggota123', PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $pdo->prepare("
            INSERT INTO users (username, password, role, status, created_at)
            VALUES (:u, :p, :r, :s, NOW())
        ");
        $ins->execute([
            'u' => 'siti.nurhaliza',
            'p' => $anggota2Password,
            'r' => 'anggota',
            's' => 'active',
        ]);
        $anggota2UserId = $pdo->lastInsertId();

        // Profil anggota
        $insAnggota = $pdo->prepare("
            INSERT INTO anggota (user_id, nomor_identitas, nama, kelas, jenis_kelamin, nomor_kontak, status, created_at)
            VALUES (:user_id, :nomor_identitas, :nama, :kelas, :jk, :kontak, 'aktif', NOW())
        ");
        $insAnggota->execute([
            'user_id' => $anggota2UserId,
            'nomor_identitas' => '20261002',
            'nama' => 'Siti Nurhaliza',
            'kelas' => 'XII RPL 2',
            'jk' => 'P',
            'kontak' => '082198765432'
        ]);

        echo "      -> Anggota 2 dibuat: username='siti.nurhaliza' / password='anggota123'\n";
        echo "         Profil: Siti Nurhaliza — XII RPL 2 — NIS: 20261002\n";
    } else {
        echo "      -> User Anggota 'siti.nurhaliza' sudah ada.\n";
    }

    // -------------------------------------------------------------------------
    // Bendahara: bendahara / bendahara123
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $stmt->execute(['u' => 'bendahara']);
    if (!$stmt->fetch()) {
        $bendaharaPassword = password_hash('bendahara123', PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $pdo->prepare("
            INSERT INTO users (username, password, role, status, created_at)
            VALUES (:u, :p, :r, :s, NOW())
        ");
        $ins->execute([
            'u' => 'bendahara',
            'p' => $bendaharaPassword,
            'r' => 'bendahara',
            's' => 'active',
        ]);
        echo "      -> Bendahara dibuat: username='bendahara' / password='bendahara123'\n";
    } else {
        echo "      -> User Bendahara 'bendahara' sudah ada.\n";
    }

    // -------------------------------------------------------------------------
    // Seed default transaction categories
    // -------------------------------------------------------------------------
    $catCount = $pdo->query("SELECT COUNT(*) FROM kategori_transaksi")->fetchColumn();
    if ($catCount == 0) {
        $pdo->exec("
            INSERT INTO kategori_transaksi (nama_kategori, jenis, deskripsi) VALUES
            ('Iuran Kas Rutin', 'masuk', 'Pembayaran kas mingguan anggota'),
            ('Sumbangan / Donasi', 'masuk', 'Dana sumbangan sukarela'),
            ('Konsumsi Kegiatan', 'keluar', 'Biaya makanan/minuman rapat atau kegiatan'),
            ('Alat Tulis & Administrasi', 'keluar', 'Pembelian ATK kas dan buku laporan'),
            ('Dana Sosial', 'keluar', 'Santunan dan bantuan sosial');
        ");
        echo "      -> Kategori transaksi default berhasil ditambahkan.\n";
    }

    // -------------------------------------------------------------------------
    // Seed initial audit log
    // -------------------------------------------------------------------------
    $adminUser = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetch();
    if ($adminUser) {
        $pdo->prepare("
            INSERT INTO audit_log (user_id, aktivitas, waktu, metadata)
            VALUES (:uid, :aktivitas, NOW(), :metadata)
        ")->execute([
            'uid' => $adminUser['id'],
            'aktivitas' => 'Sistem diinisialisasi — migration & seeder berhasil',
            'metadata' => json_encode(['action' => 'migration', 'env' => 'development']),
        ]);
        echo "      -> Audit log awal berhasil dicatat.\n";
    }

    echo "\n=======================================================\n";
    echo " ✅ SUKSES — Migrasi & seeder berhasil diselesaikan!\n";
    echo "=======================================================\n";
    echo "\n AKUN DEVELOPMENT:\n";
    echo " ┌─────────────────┬─────────────────┬───────────────┐\n";
    echo " │ Role            │ Username        │ Password      │\n";
    echo " ├─────────────────┼─────────────────┼───────────────┤\n";
    echo " │ Super User      │ admin           │ admin123      │\n";
    echo " │ Bendahara       │ bendahara       │ bendahara123  │\n";
    echo " │ Anggota         │ ahmad.rizky     │ anggota123    │\n";
    echo " │ Anggota         │ siti.nurhaliza   │ anggota123    │\n";
    echo " └─────────────────┴─────────────────┴───────────────┘\n";
    echo "\n ⚠  Jangan gunakan credential di atas untuk production!\n\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}
