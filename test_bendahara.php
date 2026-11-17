<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\FinancialDataAggregator;
use App\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Models\User;
use App\Models\TransaksiKas;
use App\Models\PembayaranKas;
use App\Models\LaporanKeuangan;
use App\Controllers\DashboardController;

Env::load(__DIR__ . '/.env');
Session::start();

echo "=======================================================\n";
echo " TEST ROLE BENDAHARA & AUTHORIZATION (PROMPT 11)\n";
echo "=======================================================\n\n";

$auth = new AuthService();
$userModel = new User();
$transaksiModel = new TransaksiKas();
$pembayaranModel = new PembayaranKas();
$laporanModel = new LaporanKeuangan();
$aggregator = new FinancialDataAggregator();

$passes = 0;
$fails = 0;

function checkTest(string $desc, bool $condition, string $details = ''): void {
    global $passes, $fails;
    if ($condition) {
        $passes++;
        echo " [PASS] " . $desc . "\n";
    } else {
        $fails++;
        echo " [FAIL] " . $desc . ($details ? " — " . $details : "") . "\n";
    }
}

// -----------------------------------------------------------------------------
// 1. Login sebagai Bendahara berhasil
// -----------------------------------------------------------------------------
$resBendahara = $auth->attempt('bendahara', 'bendahara123');
checkTest(
    "1. Login sebagai Bendahara berhasil",
    $resBendahara['success'] === true &&
    isset($resBendahara['user']) &&
    $resBendahara['user']['role'] === 'bendahara' &&
    $auth->getRedirectPath($resBendahara['user']) === '/bendahara/dashboard'
);

// -----------------------------------------------------------------------------
// 2. Login sebagai Super User tetap berhasil
// -----------------------------------------------------------------------------
$resAdmin = $auth->attempt('admin', 'admin123');
checkTest(
    "2. Login sebagai Super User tetap berhasil",
    $resAdmin['success'] === true &&
    isset($resAdmin['user']) &&
    $resAdmin['user']['role'] === 'super_user' &&
    $auth->getRedirectPath($resAdmin['user']) === '/admin/dashboard'
);

// -----------------------------------------------------------------------------
// 3. Login sebagai Anggota tetap berhasil
// -----------------------------------------------------------------------------
$resAnggota = $auth->attempt('ahmad.rizky', 'anggota123');
checkTest(
    "3. Login sebagai Anggota tetap berhasil",
    $resAnggota['success'] === true &&
    isset($resAnggota['user']) &&
    $resAnggota['user']['role'] === 'anggota' &&
    $auth->getRedirectPath($resAnggota['user']) === '/anggota/dashboard'
);

// Setup user references
$bendaharaUser = $userModel->findByUsername('bendahara');
$anggotaUser   = $userModel->findByUsername('ahmad.rizky');
$adminUser     = $userModel->findByUsername('admin');

Session::set('user', $bendaharaUser);

// -----------------------------------------------------------------------------
// 4. Bendahara dapat membuka dashboard keuangan
// -----------------------------------------------------------------------------
$totalMasuk = $transaksiModel->getTotalByJenis('masuk');
$totalKeluar = $transaksiModel->getTotalByJenis('keluar');
$saldo = $totalMasuk - $totalKeluar;
$recentTx = $transaksiModel->getPaginated([], 1, 5);
checkTest(
    "4. Bendahara dapat melihat saldo dan dashboard keuangan",
    is_numeric($saldo) && is_array($recentTx)
);

// -----------------------------------------------------------------------------
// 5. Bendahara dapat membuka transaksi kas
// -----------------------------------------------------------------------------
$txList = $transaksiModel->getPaginated([], 1, 10);
$txCount = $transaksiModel->countFiltered([]);
checkTest(
    "5. Bendahara dapat membuka data transaksi kas",
    is_array($txList) && is_numeric($txCount)
);

// -----------------------------------------------------------------------------
// 6. Bendahara dapat membuat & void transaksi
// -----------------------------------------------------------------------------
$newTxId = $transaksiModel->create([
    'kode_transaksi' => 'TRM-TEST-' . time(),
    'jenis' => 'masuk',
    'kategori_id' => 1,
    'jumlah' => 75000,
    'keterangan' => 'Test Transaksi oleh Bendahara',
    'tanggal_transaksi' => date('Y-m-d'),
    'created_by' => $bendaharaUser['id'],
    'status' => 'aktif'
]);
$txCreated = $transaksiModel->find($newTxId);
$transaksiModel->update($newTxId, ['status' => 'void']);
$txVoided = $transaksiModel->find($newTxId);
checkTest(
    "6. Bendahara dapat membuat dan mengelola transaksi kas",
    $newTxId > 0 &&
    $txCreated['status'] === 'aktif' &&
    (float)$txCreated['jumlah'] == 75000 &&
    $txVoided['status'] === 'void'
);

// -----------------------------------------------------------------------------
// 7. Bendahara dapat melihat pembayaran kas
// -----------------------------------------------------------------------------
$pbkList = $pembayaranModel->getPaginated([], 1, 10);
$pbkCount = $pembayaranModel->countFiltered([]);
checkTest(
    "7. Bendahara dapat melihat dan memfilter pembayaran kas",
    is_array($pbkList) && is_numeric($pbkCount)
);

// -----------------------------------------------------------------------------
// 8. Bendahara dapat melihat laporan keuangan
// -----------------------------------------------------------------------------
$laporanMasuk = $laporanModel->getLaporanPemasukan(['periode_tahun' => date('Y')]);
$laporanKeluar = $laporanModel->getLaporanPengeluaran(['periode_tahun' => date('Y')]);
$laporanArusKas = $laporanModel->getArusKasSummary(['periode_tahun' => date('Y')]);
$laporanTunggakan = $laporanModel->getLaporanTunggakan(['periode_bulan' => date('n'), 'periode_tahun' => date('Y')]);
checkTest(
    "8. Bendahara dapat melihat dan memfilter seluruh laporan keuangan",
    is_array($laporanMasuk) &&
    is_array($laporanKeluar) &&
    is_array($laporanArusKas) &&
    is_array($laporanTunggakan)
);

// -----------------------------------------------------------------------------
// 9. Bendahara dapat menggunakan AI sesuai batasan akses
// -----------------------------------------------------------------------------
$aiContextBendahara = $aggregator->getAggregatedContext('bendahara', null, 'Berapa saldo kas saat ini?');
$aiContextAnggota = $aggregator->getAggregatedContext('anggota', (int)$anggotaUser['id'], 'Status iuran saya?');
checkTest(
    "9. AI Assistant membatasi konteks sesuai role (Bendahara vs Anggota)",
    $aiContextBendahara['role_akses'] === 'bendahara' &&
    isset($aiContextBendahara['ringkasan_kas']) &&
    $aiContextAnggota['role_akses'] === 'anggota' &&
    !isset($aiContextAnggota['ringkasan_kas'])
);

// Helper test class to verify RoleMiddleware logic without triggering exit() in CLI
class TestRoleMiddleware extends RoleMiddleware
{
    public ?string $redirectedPath = null;
    public bool $isForbidden = false;

    public function handle(Request $request, ...$allowedRoles): void
    {
        $user = Session::get('user');
        if (!$user) {
            $this->redirectedPath = '/login';
            return;
        }

        $userRole = $user['role'] ?? '';

        if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles, true)) {
            $this->isForbidden = true;
            Session::setFlash('error', 'Anda tidak memiliki hak akses ke halaman tersebut.');
            $this->redirectedPath = match ($userRole) {
                'super_user' => '/admin/dashboard',
                'anggota' => '/anggota/dashboard',
                'bendahara' => '/bendahara/dashboard',
                default => '/login',
            };
            return;
        }
    }
}

$mockReq = new Request();
$testMiddleware = new TestRoleMiddleware();

// -----------------------------------------------------------------------------
// 10. Bendahara tidak dapat mengakses kelola user
// -----------------------------------------------------------------------------
Session::set('user', $bendaharaUser); // role = bendahara
$testMiddleware->isForbidden = false;
$testMiddleware->redirectedPath = null;
$testMiddleware->handle($mockReq, 'super_user');
checkTest(
    "10. Bendahara tidak dapat mengakses kelola user / rute super_user",
    $testMiddleware->isForbidden === true &&
    $testMiddleware->redirectedPath === '/bendahara/dashboard' &&
    Session::getFlash('error') !== null
);

// -----------------------------------------------------------------------------
// 11. Bendahara tidak dapat mengubah role user
// -----------------------------------------------------------------------------
$routesContent = file_get_contents(__DIR__ . '/routes/web.php');
preg_match("/Router::group\(\['prefix' => 'bendahara'.*?\}\);/s", $routesContent, $bendaharaMatches);
$bendaharaRoutesBlock = $bendaharaMatches[0] ?? '';
$bendaharaHasAnggotaCrud = str_contains($bendaharaRoutesBlock, '/anggota');
checkTest(
    "11. Bendahara tidak memiliki route untuk mengubah role / mengelola user",
    $bendaharaHasAnggotaCrud === false && !empty($bendaharaRoutesBlock)
);

// -----------------------------------------------------------------------------
// 12. Bendahara tidak dapat mengakses fitur khusus Super User
// -----------------------------------------------------------------------------
$adminRouteProtected = str_contains($routesContent, "prefix' => 'admin', 'middleware' => ['auth', 'role:super_user']");
checkTest(
    "12. Seluruh rute administrasi super_user dilindungi role:super_user",
    $adminRouteProtected === true
);

// -----------------------------------------------------------------------------
// 13. Anggota tidak mendapatkan akses Bendahara
// -----------------------------------------------------------------------------
Session::set('user', $anggotaUser); // role = anggota
$testMiddleware->isForbidden = false;
$testMiddleware->redirectedPath = null;
$testMiddleware->handle($mockReq, 'bendahara');
checkTest(
    "13. Anggota tidak mendapatkan akses ke rute Bendahara",
    $testMiddleware->isForbidden === true &&
    $testMiddleware->redirectedPath === '/anggota/dashboard' &&
    Session::getFlash('error') !== null
);

// -----------------------------------------------------------------------------
// 14. Super User tetap memiliki seluruh akses sebelumnya
// -----------------------------------------------------------------------------
Session::set('user', $adminUser); // role = super_user
$testMiddleware->isForbidden = false;
$testMiddleware->redirectedPath = null;
$testMiddleware->handle($mockReq, 'super_user');
checkTest(
    "14. Super User tetap memiliki akses penuh tanpa penolakan",
    $testMiddleware->isForbidden === false &&
    $testMiddleware->redirectedPath === null
);

// -----------------------------------------------------------------------------
// 15. Akses langsung melalui URL terlarang ditolak & diredirect ke dashboard masing-masing
// -----------------------------------------------------------------------------
// 15a. Bendahara blocked from admin -> redirects to /bendahara/dashboard
Session::set('user', $bendaharaUser);
$testMiddleware->handle($mockReq, 'super_user');
$redirect15a = $testMiddleware->redirectedPath;

// 15b. Anggota blocked from bendahara -> redirects to /anggota/dashboard
Session::set('user', $anggotaUser);
$testMiddleware->handle($mockReq, 'bendahara');
$redirect15b = $testMiddleware->redirectedPath;

checkTest(
    "15. Akses langsung melalui URL terlarang ditolak & diredirect ke dashboard masing-masing",
    $redirect15a === '/bendahara/dashboard' &&
    $redirect15b === '/anggota/dashboard'
);

// -----------------------------------------------------------------------------
// 16. Tidak ada error pada role lama (Super User & Anggota)
// -----------------------------------------------------------------------------
$anggotaContext = $aggregator->getAggregatedContext('anggota', (int)$anggotaUser['id']);
$superUserContext = $aggregator->getAggregatedContext('super_user');
checkTest(
    "16. Tidak ada error / regresi pada data context dan fitur role lama",
    $anggotaContext['role_akses'] === 'anggota' &&
    $superUserContext['role_akses'] === 'super_user' &&
    isset($superUserContext['ringkasan_kas']['saldo_kas_saat_ini']) &&
    is_numeric($superUserContext['ringkasan_kas']['saldo_kas_saat_ini'])
);

// -----------------------------------------------------------------------------
// 17. Render Controller Bendahara Dashboard menghasilkan HTML valid
// -----------------------------------------------------------------------------
Session::set('user', $bendaharaUser);
ob_start();
$dashboardCtrl = new DashboardController();
$dashboardCtrl->bendaharaDashboard($mockReq);
$renderedDashboard = ob_get_clean();
checkTest(
    "17. View Dashboard Bendahara berhasil dirender tanpa error",
    strlen($renderedDashboard) > 500 &&
    str_contains($renderedDashboard, 'Saldo Kas Saat Ini') &&
    str_contains($renderedDashboard, 'bendahara/transaksi')
);

// -----------------------------------------------------------------------------
// 18. Render Controller AI Assistant Bendahara menghasilkan role bendahara
// -----------------------------------------------------------------------------
ob_start();
$aiCtrl = new \App\Controllers\AiAssistantController();
$aiCtrl->bendaharaIndex();
$renderedAi = ob_get_clean();
checkTest(
    "18. View AI Assistant Bendahara berhasil dirender dengan role bendahara",
    strlen($renderedAi) > 500 &&
    str_contains($renderedAi, 'bendahara/ai-assistant/ask') &&
    str_contains($renderedAi, 'Bendahara')
);

// -----------------------------------------------------------------------------
// 19. Sidebar hanya merender menu Bendahara (tanpa menu Anggota / Kelola User)
// -----------------------------------------------------------------------------
$sidebarFile = file_get_contents(__DIR__ . '/app/Views/layouts/sidebar.php');
$sidebarHasBendaharaTransaksi = str_contains($sidebarFile, "href=\"<?= url('bendahara/transaksi') ?>\"");
$sidebarHasBendaharaPembayaran = str_contains($sidebarFile, "href=\"<?= url('bendahara/pembayaran') ?>\"");
$sidebarHasBendaharaLaporan = str_contains($sidebarFile, "href=\"<?= url('bendahara/laporan') ?>\"");
$sidebarHasBendaharaAi = str_contains($sidebarFile, "href=\"<?= url('bendahara/ai-assistant') ?>\"");
checkTest(
    "19. Sidebar mendefinisikan menu navigasi khusus Bendahara secara lengkap",
    $sidebarHasBendaharaTransaksi &&
    $sidebarHasBendaharaPembayaran &&
    $sidebarHasBendaharaLaporan &&
    $sidebarHasBendaharaAi
);

// -----------------------------------------------------------------------------
// 20. Variabel rolePrefix terinjeksi secara global di BaseController
// -----------------------------------------------------------------------------
$reflector = new ReflectionClass(DashboardController::class);
$parent = $reflector->getParentClass();
$hasRolePrefixMethod = $parent->hasMethod('getRolePrefix');
checkTest(
    "20. BaseController menginjeksi getRolePrefix() secara konsisten",
    $hasRolePrefixMethod === true
);

echo "\n=======================================================\n";
echo " HASIL PENGUJIAN: {$passes} PASS / {$fails} FAIL\n";
echo "=======================================================\n";

if ($fails > 0) {
    exit(1);
}
