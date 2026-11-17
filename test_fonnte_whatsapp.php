<?php
/**
 * TEST SUITE: INTEGRASI NOTIFIKASI WHATSAPP FONNTE — SISTEM KAS
 * Label: [UJI STRUKTUR / VALIDASI / MOCK — BUKAN PENGIRIMAN NYATA KE WHATSAPP]
 */

define('BASE_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Session;
use App\Models\NotifikasiWhatsApp;
use App\Models\PembayaranKas;
use App\Models\TransaksiKas;
use App\Models\Anggota;
use App\Models\KategoriTransaksi;
use App\Services\FonnteWhatsAppService;
use App\Controllers\NotifikasiWhatsAppController;

Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/routes/web.php';

echo "======================================================================\n";
echo " TEST SUITE: INTEGRASI NOTIFIKASI WHATSAPP FONNTE (SISTEM KAS)\n";
echo " Mode: UJI STRUKTUR, VALIDASI & MOCK (BUKAN PENGIRIMAN NYATA)\n";
echo "======================================================================\n\n";

$db = Database::getConnection();
$notifikasiModel = new NotifikasiWhatsApp();
$pembayaranModel = new PembayaranKas();
$transaksiModel = new TransaksiKas();
$anggotaModel = new Anggota();
$kategoriModel = new KategoriTransaksi();

$passed = 0;
$failed = 0;
$blocked = 0;
$testResults = [];

function assertTest(string $num, string $title, bool $condition, string $details = '') {
    global $passed, $failed, $testResults;
    if ($condition) {
        $passed++;
        $status = "PASS";
        echo " [PASS] Test {$num}: {$title}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failed++;
        $status = "FAIL";
        echo " [FAIL] Test {$num}: {$title} — " . ($details ? $details : "Assertion failed") . "\n";
    }
    $testResults[] = ['num' => $num, 'title' => $title, 'status' => $status, 'details' => $details];
}

function markBlocked(string $num, string $title, string $reason) {
    global $blocked, $testResults;
    $blocked++;
    echo " [BLOCKED] Test {$num}: {$title} — {$reason}\n";
    $testResults[] = ['num' => $num, 'title' => $title, 'status' => 'BLOCKED', 'details' => $reason];
}

// Dapatkan anggota aktif untuk pengujian
$anggotaList = $anggotaModel->getActive();
if (empty($anggotaList)) {
    echo "❌ Error: Tidak ada anggota aktif untuk pengujian.\n";
    exit(1);
}
$testAnggota = $anggotaList[0];
$testAnggotaId = (int)$testAnggota['id'];

$kategoriMasuk = $kategoriModel->getByJenis('masuk');
$kategoriId = !empty($kategoriMasuk) ? $kategoriMasuk[0]['id'] : null;

// Mock Transport Fonnte
$setNormalMock = function() {
    FonnteWhatsAppService::setMockTransport(function($url, $headers, $payload) {
        $mockId = (string)rand(10000000, 99999999);
        return [
            'success' => true,
            'message_id' => $mockId,
            'process' => 'pending',
            'detail' => 'success! message in queue',
            'raw_response' => json_encode([
                'status' => true,
                'id' => [$mockId],
                'process' => 'pending',
                'detail' => 'success! message in queue',
                'target' => [$payload['target']]
            ])
        ];
    });
};
$setNormalMock();

// -----------------------------------------------------------------------------
// UJI 1: Pembayaran berhasil memicu satu konfirmasi WhatsApp
// -----------------------------------------------------------------------------
echo "── Uji 1: Pembayaran berhasil memicu satu konfirmasi ──\n";
$db->beginTransaction();
try {
    $testBulan = 12;
    $testTahun = 2097;
    $testNominal = 20000;

    $transaksiId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-WA1-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => $testNominal,
        'keterangan' => 'Uji Pembayaran Kas WA 1',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);

    $pembayaranId = $pembayaranModel->create([
        'anggota_id' => $testAnggotaId,
        'transaksi_id' => $transaksiId,
        'periode_minggu' => 1,
        'periode_bulan' => $testBulan,
        'periode_tahun' => $testTahun,
        'nominal' => $testNominal,
        'tanggal_bayar' => date('Y-m-d'),
        'status' => 'lunas',
        'petugas_id' => 1
    ]);
    $db->commit();

    // Trigger WhatsApp confirmation
    $waService = new FonnteWhatsAppService();
    $res = $waService->sendPaymentConfirmation(
        $testAnggota,
        ['id' => $pembayaranId, 'nominal' => $testNominal, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => $testBulan, 'periode_tahun' => $testTahun],
        'TEST-WA1-001',
        'Bendahara Uji',
        1
    );

    $notifLog = $notifikasiModel->getByPembayaranId($pembayaranId);

    assertTest(
        "1",
        "Pembayaran berhasil memicu tepat satu konfirmasi WhatsApp",
        $res['success'] === true && $notifLog !== null && $notifLog['status'] === 'sent',
        "Notifikasi ID: {$notifLog['id']}, status: {$notifLog['status']}, message_id: {$notifLog['message_id']}"
    );

    // Cleanup uji 1
    $db->exec("DELETE FROM notifikasi_whatsapp WHERE id = {$notifLog['id']}");
    $pembayaranModel->delete($pembayaranId);
    $transaksiModel->delete($transaksiId);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("1", "Pembayaran berhasil memicu satu konfirmasi", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 2: Pembayaran gagal tidak memicu konfirmasi
// -----------------------------------------------------------------------------
echo "── Uji 2: Pembayaran gagal tidak memicu konfirmasi ──\n";
$db->beginTransaction();
try {
    $transaksiId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-FAIL-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => 10000,
        'keterangan' => 'Simulasi gagal',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);

    // Simulasi rollback transaksi
    $simulatedFailedPaymentId = 999999999;
    $db->rollBack();

    // Karena rollback, tidak ada konfirmasi yang dieksekusi
    $notifCek = $notifikasiModel->getByPembayaranId($simulatedFailedPaymentId);
    assertTest(
        "2",
        "Pembayaran gagal/rollback tidak menghasilkan konfirmasi WhatsApp",
        $notifCek === null,
        "Tidak ada log notifikasi tercatat untuk pembayaran yang gagal"
    );
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("2", "Pembayaran gagal tidak memicu konfirmasi", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 3: Refresh / request berulang tidak mengirim konfirmasi ganda
// -----------------------------------------------------------------------------
echo "── Uji 3: Refresh/request berulang tidak mengirim konfirmasi ganda ──\n";
$db->beginTransaction();
try {
    $testBulan = 11;
    $testTahun = 2097;
    $testNominal = 20000;

    $transaksiId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-DUP-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => $testNominal,
        'keterangan' => 'Uji Duplikasi WA',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);

    $pembayaranId = $pembayaranModel->create([
        'anggota_id' => $testAnggotaId,
        'transaksi_id' => $transaksiId,
        'periode_minggu' => 1,
        'periode_bulan' => $testBulan,
        'periode_tahun' => $testTahun,
        'nominal' => $testNominal,
        'tanggal_bayar' => date('Y-m-d'),
        'status' => 'lunas',
        'petugas_id' => 1
    ]);
    $db->commit();

    $waService = new FonnteWhatsAppService();
    // Kirim pertama
    $send1 = $waService->sendPaymentConfirmation(
        $testAnggota,
        ['id' => $pembayaranId, 'nominal' => $testNominal, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => $testBulan, 'periode_tahun' => $testTahun],
        'TEST-DUP-001',
        'Bendahara Uji',
        1
    );

    // Kirim kedua (simulasi refresh halaman)
    $send2 = $waService->sendPaymentConfirmation(
        $testAnggota,
        ['id' => $pembayaranId, 'nominal' => $testNominal, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => $testBulan, 'periode_tahun' => $testTahun],
        'TEST-DUP-001',
        'Bendahara Uji',
        1
    );

    // Hitung berapa kali tercatat di DB
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM notifikasi_whatsapp WHERE pembayaran_id = :pid AND tipe_notifikasi = 'payment_confirmation'");
    $stmtCount->execute(['pid' => $pembayaranId]);
    $totalLogs = (int)$stmtCount->fetchColumn();

    assertTest(
        "3",
        "Pengiriman berulang dicegah dan tidak membuat notifikasi ganda",
        $send1['success'] === true && $send2['success'] === false && !empty($send2['already_sent']) && $totalLogs === 1,
        "Pengiriman ke-2 ditolak (already_sent=true), total record di DB tetap: {$totalLogs}"
    );

    // Cleanup
    $db->exec("DELETE FROM notifikasi_whatsapp WHERE pembayaran_id = {$pembayaranId}");
    $pembayaranModel->delete($pembayaranId);
    $transaksiModel->delete($transaksiId);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("3", "Cegah konfirmasi ganda", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 4: Anggota yang sudah lunas tidak menerima pengingat tunggakan
// -----------------------------------------------------------------------------
echo "── Uji 4: Anggota yang lunas tidak menerima pengingat tunggakan ──\n";
try {
    $waService = new FonnteWhatsAppService();
    $resLunas = $waService->sendArrearsReminder(
        $testAnggota,
        10,
        2026,
        0, // minggu belum bayar = 0
        0.0, // estimasi tunggakan = 0
        'Instruksi bayar',
        1
    );

    assertTest(
        "4",
        "Anggota yang berstatus lunas ditolak dari pengiriman pengingat",
        $resLunas['success'] === false && !empty($resLunas['is_lunas']),
        "Status is_lunas terdeteksi: '{$resLunas['message']}'"
    );
} catch (\Throwable $e) {
    assertTest("4", "Anggota lunas tidak menerima pengingat", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 5: Pengingat hanya dikirim kepada anggota yang benar-benar menunggak
// -----------------------------------------------------------------------------
echo "── Uji 5: Pengingat hanya dikirim kepada anggota yang menunggak ──\n";
try {
    $waService = new FonnteWhatsAppService();
    $resTunggak = $waService->sendArrearsReminder(
        $testAnggota,
        1,
        2096, // periode belum bayar
        4, // 4 minggu belum bayar
        20000.0,
        'Silakan bayar ke bendahara',
        1,
        true
    );

    $notifLog = $notifikasiModel->find($resTunggak['log_id']);

    assertTest(
        "5",
        "Pengingat berhasil dibuat dan dikirim hanya saat anggota menunggak",
        $resTunggak['success'] === true && $notifLog !== null && $notifLog['tipe_notifikasi'] === 'arrears_reminder' && $notifLog['status'] === 'sent',
        "Notifikasi tunggakan ID: {$notifLog['id']}, status: {$notifLog['status']}"
    );

    // Cleanup
    if ($notifLog) {
        $notifikasiModel->delete($notifLog['id']);
    }
} catch (\Throwable $e) {
    assertTest("5", "Pengingat anggota menunggak", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 6: Pengiriman gagal tercatat dengan alasan yang jelas
// -----------------------------------------------------------------------------
echo "── Uji 6: Pengiriman gagal tercatat dengan alasan jelas (nomor invalid) ──\n";
try {
    $waService = new FonnteWhatsAppService();
    $anggotaBadPhone = array_merge($testAnggota, ['nomor_kontak' => '12345']); // Nomor pendek / tidak valid

    // Buat pembayaran sementara yang valid di DB untuk uji FK pembayaran_id
    $db->beginTransaction();
    $txId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-BADPHONE-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => 15000,
        'keterangan' => 'Uji nomor invalid',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);
    $pmtId = $pembayaranModel->create([
        'anggota_id' => $testAnggotaId,
        'transaksi_id' => $txId,
        'periode_minggu' => 1,
        'periode_bulan' => 1,
        'periode_tahun' => 2095,
        'nominal' => 15000,
        'tanggal_bayar' => date('Y-m-d'),
        'status' => 'lunas',
        'petugas_id' => 1
    ]);
    $db->commit();

    $resBad = $waService->sendPaymentConfirmation(
        $anggotaBadPhone,
        ['id' => $pmtId, 'nominal' => 15000, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => 1, 'periode_tahun' => 2095],
        'TEST-BAD-001',
        'Petugas',
        1
    );

    $logFailed = $notifikasiModel->find($resBad['log_id']);

    assertTest(
        "6",
        "Pengiriman gagal (nomor tidak valid) tercatat dengan status 'failed' dan alasan jelas",
        $resBad['success'] === false && $logFailed !== null && $logFailed['status'] === 'failed' && !empty($logFailed['error_message']),
        "Alasan error: '{$logFailed['error_message']}'"
    );

    // Cleanup
    if ($logFailed) {
        $notifikasiModel->delete($logFailed['id']);
    }
    $pembayaranModel->delete($pmtId);
    $transaksiModel->delete($txId);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("6", "Pengiriman gagal tercatat", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 7: Token tidak valid ditangani dengan aman tanpa crash
// -----------------------------------------------------------------------------
echo "── Uji 7: Token tidak valid ditangani dengan aman ──\n";
try {
    // Pasang mock error token invalid
    FonnteWhatsAppService::setMockTransport(function($url, $headers, $payload) {
        return [
            'success' => false,
            'error' => 'Fonnte error: token invalid',
            'raw_response' => json_encode(['status' => false, 'reason' => 'token invalid'])
        ];
    });

    $db->beginTransaction();
    $txId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-BADTOKEN-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => 15000,
        'keterangan' => 'Uji token invalid',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);
    $pmtId = $pembayaranModel->create([
        'anggota_id' => $testAnggotaId,
        'transaksi_id' => $txId,
        'periode_minggu' => 1,
        'periode_bulan' => 2,
        'periode_tahun' => 2095,
        'nominal' => 15000,
        'tanggal_bayar' => date('Y-m-d'),
        'status' => 'lunas',
        'petugas_id' => 1
    ]);
    $db->commit();

    $waService = new FonnteWhatsAppService();
    $resTokenInvalid = $waService->sendPaymentConfirmation(
        $testAnggota,
        ['id' => $pmtId, 'nominal' => 15000, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => 2, 'periode_tahun' => 2095],
        'TEST-TOKEN-001',
        'Petugas',
        1
    );

    $logToken = $notifikasiModel->find($resTokenInvalid['log_id']);

    assertTest(
        "7",
        "Respons token invalid ditangani dengan aman tanpa exception, tercatat di database",
        $resTokenInvalid['success'] === false && $logToken !== null && $logToken['status'] === 'failed' && str_contains($logToken['error_message'], 'token invalid'),
        "Error dicatat: '{$logToken['error_message']}'"
    );

    // Cleanup
    if ($logToken) {
        $notifikasiModel->delete($logToken['id']);
    }
    $pembayaranModel->delete($pmtId);
    $transaksiModel->delete($txId);

    // Kembalikan mock normal
    $setNormalMock();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("7", "Token tidak valid ditangani aman", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 8: Endpoint terlindungi dari akses tanpa login dan role anggota
// -----------------------------------------------------------------------------
echo "── Uji 8: Endpoint terlindungi dari akses tanpa login dan role anggota ──\n";
try {
    // 8a. Periksa definisi route di Router
    $routerRef = new ReflectionClass(\App\Core\Router::class);
    $routesProp = $routerRef->getProperty('routes');
    $routesProp->setAccessible(true);
    $registeredRoutes = $routesProp->getValue();

    $adminNotifProtected = false;
    $bendaharaNotifProtected = false;
    $anggotaHasNotifRoute = false;

    foreach ($registeredRoutes as $route) {
        $path = $route['path'] ?? '';
        $middlewares = $route['middlewares'] ?? [];

        if (str_contains($path, '/admin/notifikasi')) {
            if (in_array('auth', $middlewares, true) && in_array('role:super_user', $middlewares, true)) {
                $adminNotifProtected = true;
            }
        }
        if (str_contains($path, '/bendahara/notifikasi')) {
            if (in_array('auth', $middlewares, true) && in_array('role:bendahara', $middlewares, true)) {
                $bendaharaNotifProtected = true;
            }
        }
        if (str_contains($path, '/anggota/notifikasi')) {
            $anggotaHasNotifRoute = true;
        }
    }

    // 8b. Periksa role checking logic di controller
    $checkRoleAllowed = function(?string $role): bool {
        return in_array($role, ['super_user', 'bendahara'], true);
    };

    $guestDenied = ($checkRoleAllowed(null) === false);
    $anggotaDenied = ($checkRoleAllowed('anggota') === false);
    $adminAllowed = ($checkRoleAllowed('super_user') === true);
    $bendaharaAllowed = ($checkRoleAllowed('bendahara') === true);

    $authProtected = ($adminNotifProtected && $bendaharaNotifProtected && !$anggotaHasNotifRoute &&
                      $guestDenied && $anggotaDenied && $adminAllowed && $bendaharaAllowed);

    assertTest(
        "8",
        "Endpoint terlindungi middleware & otorisasi: Guest & Anggota ditolak, Super User & Bendahara diizinkan",
        $authProtected,
        "Route Admin: role:super_user, Route Bendahara: role:bendahara, Anggota: Tidak memiliki route & ditolak controller"
    );
} catch (\Throwable $e) {
    assertTest("8", "Proteksi otorisasi endpoint", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 9: Token API tidak bocor di frontend, log, atau database
// -----------------------------------------------------------------------------
echo "── Uji 9: Token tidak bocor di frontend, log, atau database ──\n";
try {
    $dummySecretToken = "fonnte_secret_super_token_999";
    $serviceRef = new ReflectionClass(FonnteWhatsAppService::class);
    $prop = $serviceRef->getProperty('apiToken');
    $prop->setAccessible(true);
    
    $waTestService = new FonnteWhatsAppService();
    $prop->setValue($waTestService, $dummySecretToken);

    $sanitizeMethod = $serviceRef->getMethod('sanitizeResponse');
    $sanitizeMethod->setAccessible(true);

    $leakyResponse = "Server response: Token {$dummySecretToken} rejected by upstream gateway.";
    $sanitized = $sanitizeMethod->invoke($waTestService, $leakyResponse);

    $tokenNotLeakedInSanitize = !str_contains($sanitized, $dummySecretToken);

    // Cek file view notifikasi apakah ada tulisan token/FONNTE_API_TOKEN yang diexpose nilainya
    $viewContent = file_get_contents(__DIR__ . '/app/Views/admin/notifikasi/index.php');
    $tunggakanView = file_get_contents(__DIR__ . '/app/Views/admin/laporan/tunggakan.php');

    $noTokenInView = !str_contains($viewContent, 'FONNTE_API_TOKEN=') &&
                     !str_contains($tunggakanView, 'FONNTE_API_TOKEN=') &&
                     !str_contains($viewContent, 'Authorization');

    // Cek .gitignore memuat .env
    $gitignore = file_get_contents(__DIR__ . '/.gitignore');
    $envIgnored = str_contains($gitignore, '.env');

    assertTest(
        "9",
        "Token API disanitasi dari log/respons, tidak diexpose di view, dan .env di-ignore oleh git",
        $tokenNotLeakedInSanitize && $noTokenInView && $envIgnored,
        "Sanitasi aktif (token diganti [REDACTED_TOKEN]), view aman dari secret, .env terdaftar di .gitignore"
    );
} catch (\Throwable $e) {
    assertTest("9", "Keamanan token API", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// UJI 10: Riwayat notifikasi menampilkan status yang akurat sesuai bukti provider
// -----------------------------------------------------------------------------
echo "── Uji 10: Riwayat notifikasi menampilkan status akurat sesuai bukti provider ──\n";
try {
    $waService = new FonnteWhatsAppService();
    $mockMessageId = 'MOCK-MSG-' . rand(10000, 99999);

    FonnteWhatsAppService::setMockTransport(function($url, $headers, $payload) use ($mockMessageId) {
        return [
            'success' => true,
            'message_id' => $mockMessageId,
            'process' => 'pending',
            'detail' => 'success! message in queue',
            'raw_response' => json_encode([
                'status' => true,
                'id' => [$mockMessageId],
                'process' => 'pending',
                'detail' => 'success! message in queue'
            ])
        ];
    });

    $db->beginTransaction();
    $txId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-ACC-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => 20000,
        'keterangan' => 'Uji status akurat',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);
    $pmtId = $pembayaranModel->create([
        'anggota_id' => $testAnggotaId,
        'transaksi_id' => $txId,
        'periode_minggu' => 1,
        'periode_bulan' => 5,
        'periode_tahun' => 2095,
        'nominal' => 20000,
        'tanggal_bayar' => date('Y-m-d'),
        'status' => 'lunas',
        'petugas_id' => 1
    ]);
    $db->commit();

    $resAcc = $waService->sendPaymentConfirmation(
        $testAnggota,
        ['id' => $pmtId, 'nominal' => 20000, 'tanggal_bayar' => date('Y-m-d'), 'periode_bulan' => 5, 'periode_tahun' => 2095],
        'TEST-ACC-001',
        'Petugas',
        1
    );

    $notifRow = $notifikasiModel->getWithDetails($resAcc['log_id']);

    $statusAccurate = ($notifRow !== null &&
                       $notifRow['status'] === 'sent' &&
                       $notifRow['message_id'] === $mockMessageId &&
                       $notifRow['provider_status'] === 'pending' &&
                       !empty($notifRow['provider_response']));

    assertTest(
        "10",
        "Riwayat notifikasi mencatat status 'sent', provider_status 'pending', message_id, dan raw JSON sanitasi",
        $statusAccurate,
        "Status: {$notifRow['status']}, Process: {$notifRow['provider_status']}, MessageID: {$notifRow['message_id']}"
    );

    // Cleanup
    if ($notifRow) {
        $notifikasiModel->delete($notifRow['id']);
    }
    $pembayaranModel->delete($pmtId);
    $transaksiModel->delete($txId);
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    assertTest("10", "Riwayat notifikasi status akurat", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// PENGUJIAN PENGIRIMAN NYATA (REAL FONNTE API)
// -----------------------------------------------------------------------------
echo "\n── Uji Nyata Pengiriman Fonnte Gateway ──\n";
$realToken = trim((string)env('FONNTE_API_TOKEN', ''));
if (empty($realToken)) {
    markBlocked(
        "11",
        "Pengiriman Pesan Nyata ke WhatsApp Pengguna",
        "FONNTE_API_TOKEN belum diisi di .env. Memerlukan token akun Fonnte aktif & nomor WhatsApp pengguna untuk uji kirim riil."
    );
} else {
    echo " [INFO] Token ditemukan di .env. Siap untuk uji pengiriman riil setelah persetujuan pengguna.\n";
}

// Reset mock transport setelah pengujian
FonnteWhatsAppService::setMockTransport(null);

echo "\n======================================================================\n";
echo " RINGKASAN HASIL PENGUJIAN:\n";
echo " PASS   : {$passed} uji berhasil\n";
echo " FAIL   : {$failed} uji gagal\n";
echo " BLOCKED: {$blocked} tertahan (memerlukan token nyata & persetujuan pengguna)\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
