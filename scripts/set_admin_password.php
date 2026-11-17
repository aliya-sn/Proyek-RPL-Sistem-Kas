<?php
/**
 * scripts/set_admin_password.php
 * Secure CLI utility for setting or changing owner/admin password directly.
 *
 * Usage:
 *   php scripts/set_admin_password.php
 *   php scripts/set_admin_password.php "MyNewPassword123!"
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Env.php';
require_once __DIR__ . '/../app/Core/Model.php';

use App\Core\Env;
use App\Core\Model;

Env::load(__DIR__ . '/../.env');
$db = Model::db();

$targetUsername = 'aulia.putri';

echo "=======================================================\n";
echo " PENGATURAN PASSWORD PEMILIK SISTEM KAS (AULIA PUTRI)\n";
echo "=======================================================\n";

$newPassword = $argv[1] ?? null;

if (empty($newPassword)) {
    echo "Masukkan password baru untuk '$targetUsername': ";
    if (PHP_OS_FAMILY === 'Windows') {
        $newPassword = trim(fgets(STDIN));
    } else {
        $newPassword = trim(fgets(STDIN));
    }
}

if (strlen($newPassword) < 6) {
    echo "\n[ERROR] Password minimal harus 6 karakter demi keamanan!\n";
    exit(1);
}

if ($newPassword === 'admin123') {
    echo "\n[ERROR] Password tidak boleh menggunakan default 'admin123'!\n";
    exit(1);
}

$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

// Check if user exists
$stmt = $db->prepare("SELECT id, username, nama FROM users WHERE username = :u LIMIT 1");
$stmt->execute(['u' => $targetUsername]);
$user = $stmt->fetch();

if ($user) {
    $updateStmt = $db->prepare("UPDATE users SET password = :p, nama = 'Aulia Putri', updated_at = NOW() WHERE username = :u");
    $updateStmt->execute(['p' => $hashedPassword, 'u' => $targetUsername]);
    echo "\n[SUKSES] Password untuk akun '{$targetUsername}' (Aulia Putri) berhasil diperbarui!\n";
} else {
    $insertStmt = $db->prepare("
        INSERT INTO users (username, nama, password, role, status, created_at, updated_at)
        VALUES (:u, :n, :p, 'super_user', 'active', NOW(), NOW())
    ");
    $insertStmt->execute([
        'u' => $targetUsername,
        'n' => 'Aulia Putri',
        'p' => $hashedPassword
    ]);
    echo "\n[SUKSES] Akun baru '{$targetUsername}' (Aulia Putri) berhasil dibuat dengan password pilihan Anda!\n";
}

echo "Role: Super User (Admin)\n";
echo "Metode enkripsi: BCRYPT (cost 12)\n";
echo "Status: Active\n";
echo "=======================================================\n";
