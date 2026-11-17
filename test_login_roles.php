<?php
/**
 * test_login_roles.php
 * Comprehensive test script for Anggota login fix and role authentication.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Services\AuthService;
use App\Models\User;
use App\Models\Anggota;

Env::load(__DIR__ . '/.env');

$auth = new AuthService();
$userModel = new User();
$anggotaModel = new Anggota();

$pass = 0;
$fail = 0;

function check(string $name, bool $condition, string $detail = '') {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

echo "=======================================================\n";
echo " PENGUJIAN LOGIN MULTI-ROLE & STATUS AKUN\n";
echo "=======================================================\n\n";

// 1. Login Anggota Aktif (Ahmad Rizky)
$res = $auth->attempt('ahmad.rizky', 'anggota123');
check(
    "1. Login Anggota aktif (ahmad.rizky) berhasil",
    $res['success'] === true &&
    isset($res['user']) &&
    $res['user']['role'] === 'anggota' &&
    $res['user']['nama'] === 'Ahmad Rizky',
    $res['message']
);

// 2. Login Super User (Admin)
$resAdmin = $auth->attempt('admin', 'admin123');
check(
    "2. Login Super User (admin) tetap berhasil",
    $resAdmin['success'] === true &&
    isset($resAdmin['user']) &&
    $resAdmin['user']['role'] === 'super_user',
    $resAdmin['message']
);

// 3. Login Bendahara
$resBendahara = $auth->attempt('bendahara', 'bendahara123');
check(
    "3. Login Bendahara (bendahara) tetap berhasil",
    $resBendahara['success'] === true &&
    isset($resBendahara['user']) &&
    $resBendahara['user']['role'] === 'bendahara',
    $resBendahara['message']
);

// 4. Password salah pada akun Anggota
$resWrongPass = $auth->attempt('ahmad.rizky', 'passwordsalah');
check(
    "4. Password salah pada Anggota tetap ditolak secara wajar",
    $resWrongPass['success'] === false && strpos($resWrongPass['message'], 'salah') !== false,
    $resWrongPass['message']
);

// 5. Akun Anggota yang dinonaktifkan (Simulasi deaktifasi)
// Ambil anggota ID 1 (Ahmad Rizky, user_id 2)
$anggotaModel->update(1, ['status' => 'nonaktif']);
$userModel->update(2, ['status' => 'inactive']);

$resDeactivated = $auth->attempt('ahmad.rizky', 'anggota123');
check(
    "5. Anggota yang berstatus nonaktif ditolak dengan pesan yang benar",
    $resDeactivated['success'] === false && strpos($resDeactivated['message'], 'dinonaktifkan') !== false,
    $resDeactivated['message']
);

// 6. Akun Anggota diaktifkan kembali (Simulasi re-aktivasi oleh Admin)
$anggotaModel->update(1, ['status' => 'aktif']);
$userModel->update(2, ['status' => 'active']);

$resReactivated = $auth->attempt('ahmad.rizky', 'anggota123');
check(
    "6. Anggota yang diaktifkan kembali dapat login dengan sukses",
    $resReactivated['success'] === true && $resReactivated['user']['role'] === 'anggota',
    $resReactivated['message']
);

// 7. Akun Super User dinonaktifkan sementara
$userModel->update(1, ['status' => 'inactive']);
$resAdminDeactivated = $auth->attempt('admin', 'admin123');
check(
    "7. Admin nonaktif ditolak login",
    $resAdminDeactivated['success'] === false && strpos($resAdminDeactivated['message'], 'dinonaktifkan') !== false,
    $resAdminDeactivated['message']
);
$userModel->update(1, ['status' => 'active']); // Kembalikan ke active

// 8. Akun Bendahara dinonaktifkan sementara
$userModel->update(4, ['status' => 'inactive']);
$resBendaharaDeactivated = $auth->attempt('bendahara', 'bendahara123');
check(
    "8. Bendahara nonaktif ditolak login",
    $resBendaharaDeactivated['success'] === false && strpos($resBendaharaDeactivated['message'], 'dinonaktifkan') !== false,
    $resBendaharaDeactivated['message']
);
$userModel->update(4, ['status' => 'active']); // Kembalikan ke active

// 9. Pastikan role dan hak akses tetap utuh
$adminRow = $userModel->findByUsername('admin');
$bendaharaRow = $userModel->findByUsername('bendahara');
check(
    "9. Role Admin dan Bendahara tidak berubah",
    $adminRow['role'] === 'super_user' && $bendaharaRow['role'] === 'bendahara',
    "Admin: {$adminRow['role']}, Bendahara: {$bendaharaRow['role']}"
);

echo "\n=======================================================\n";
echo " HASIL PENGUJIAN: {$pass} PASS / {$fail} FAIL\n";
echo "=======================================================\n";

if ($fail > 0) {
    exit(1);
}
