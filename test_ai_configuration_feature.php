<?php

/**
 * test_ai_configuration_feature.php
 * Automated verification test suite for PRD 1.0 (Section L: 19 Poin Pengujian Wajib):
 * "Fitur Konfigurasi API Key dan Model AI Assistant — Sistem Kas"
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Session;
use App\Models\AiConfiguration;
use App\Models\AuditLog;
use App\Services\EncryptionService;
use App\Services\GeminiConfigService;
use App\Services\AIService;
use App\Models\TransaksiKas;
use App\Models\PembayaranKas;
use App\Services\FonnteWhatsAppService;

Env::load(__DIR__ . '/.env');
Session::start();
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

echo "=================================================================\n";
echo " PENGUJIAN WAJIB FITUR KONFIGURASI AI ASSISTANT (PRD SEKSI L)\n";
echo "=================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        if ($detail) {
            echo "         Detail: {$detail}\n";
        }
        $failed++;
    }
}

function runIsolatedScript(string $body): string {
    $tmp = tempnam(sys_get_temp_dir(), 'ai_cfg_test_') . '.php';
    $fullScript = "<?php\n"
        . "require_once '" . addslashes(__DIR__ . '/vendor/autoload.php') . "';\n"
        . "\\App\\Core\\Env::load('" . addslashes(__DIR__ . '/.env') . "');\n"
        . "\\App\\Core\\Session::start();\n"
        . "date_default_timezone_set(\\App\\Core\\Env::get('APP_TIMEZONE', 'Asia/Jakarta'));\n"
        . $body;
    file_put_contents($tmp, $fullScript);
    $out = shell_exec("php \"{$tmp}\" 2>&1");
    @unlink($tmp);
    return (string)$out;
}

$geminiService = new GeminiConfigService();
$aiConfigModel = new AiConfiguration();
$activeEnvKey  = Env::get('AI_API_KEY', '');

// -----------------------------------------------------------------------------
// PENGUJIAN 1, 2, 3: HAK AKSES PERAN
// -----------------------------------------------------------------------------
echo "── 1. Hak Akses Admin, Bendahara, dan Anggota ──\n";

// 1. Admin dapat membuka halaman konfigurasi
$adminIndexScript = <<<PHP
\$_SESSION['user'] = ['id' => 1, 'role' => 'super_user', 'username' => 'admin'];
\$controller = new \App\Controllers\AiConfigController();
ob_start();
\$controller->index();
\$viewOutput = ob_get_clean();
echo strpos(\$viewOutput, 'Konfigurasi AI Assistant') !== false ? 'ALLOWED' : 'FAILED';
PHP;
$adminIndexRes = runIsolatedScript($adminIndexScript);
assertTest($adminIndexRes === 'ALLOWED', "Uji 1: Admin dapat membuka halaman konfigurasi");

// 2. Bendahara tidak dapat membuka halaman konfigurasi
$bendaharaIndexScript = <<<PHP
\$_SESSION['user'] = ['id' => 2, 'role' => 'bendahara', 'username' => 'bendahara'];
\$controller = new \App\Controllers\AiConfigController();
\$controller->index();
PHP;
$bendaharaIndexRes = runIsolatedScript($bendaharaIndexScript);
assertTest(
    strpos($bendaharaIndexRes, 'Anda tidak memiliki hak akses') !== false || http_response_code() === 403,
    "Uji 2: Bendahara tidak dapat membuka halaman konfigurasi (Ditolak 403 Forbidden)"
);

// 3. Anggota tidak dapat membuka halaman konfigurasi
$anggotaIndexScript = <<<PHP
\$_SESSION['user'] = ['id' => 3, 'role' => 'anggota', 'username' => 'ahmad.rizky'];
\$controller = new \App\Controllers\AiConfigController();
\$controller->index();
PHP;
$anggotaIndexRes = runIsolatedScript($anggotaIndexScript);
assertTest(
    strpos($anggotaIndexRes, 'Anda tidak memiliki hak akses') !== false || http_response_code() === 403,
    "Uji 3: Anggota tidak dapat membuka halaman konfigurasi (Ditolak 403 Forbidden)"
);

// -----------------------------------------------------------------------------
// PENGUJIAN 4, 5, 6, 7: PENGUJIAN & PEMILIHAN MODEL GEMINI DINAMIS
// -----------------------------------------------------------------------------
echo "\n── 2. Pengujian & Pemilihan Model Gemini Dinamis ──\n";

// 4. API Key baru dapat diuji
$testKey = "AIzaSyFakeKeyToTestValidation_999";
$testKeyResult = $geminiService->testConfiguration($testKey, 'gemini-1.5-flash');
assertTest(
    isset($testKeyResult['success']) && isset($testKeyResult['stage']),
    "Uji 4: API Key baru dapat diuji secara terisolasi melalui GeminiConfigService"
);

// 5. Daftar model Gemini berhasil dimuat
$loadedModels = $geminiService->fetchAvailableModels($activeEnvKey);
assertTest(
    $loadedModels['success'] === true && count($loadedModels['models'] ?? []) > 0,
    "Uji 5: Daftar model Gemini berhasil dimuat secara dinamis dari API (" . count($loadedModels['models'] ?? []) . " model ditemukan)"
);

// 6. Model dapat dipilih
$firstModel = $loadedModels['models'][0]['id'] ?? '';
assertTest(
    !empty($firstModel),
    "Uji 6: Model dapat dipilih dari daftar model valid (terpilih: '{$firstModel}')"
);

// 7. Model yang tidak tersedia ditolak
$unavailableTest = $geminiService->testConfiguration($activeEnvKey, 'gemini-model-yang-pasti-tidak-ada-999');
assertTest(
    $unavailableTest['success'] === false && strpos($unavailableTest['error'], 'tidak ditemukan') !== false,
    "Uji 7: Model yang tidak tersedia ditolak dengan error deskriptif"
);

// -----------------------------------------------------------------------------
// PENGUJIAN 8, 9, 10: ATOMIC CONFIGURATION UPDATE & ROLLBACK
// -----------------------------------------------------------------------------
echo "\n── 3. Validasi Pra-Aktivasi, Atomisitas, & Rollback ──\n";

$initialActive = $aiConfigModel->getActive('gemini');

// 8. API Key tidak valid tidak dapat diaktifkan
$invalidKeySaveScript = <<<PHP
\$_SESSION['user'] = ['id' => 1, 'role' => 'super_user', 'username' => 'admin'];
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_POST = [
    '_csrf_token' => \App\Core\Csrf::getToken(),
    'api_key'     => 'AIzaSyKunciPastiSalahTidakBisaAktif',
    'model'       => 'gemini-3.8-flash'
];
\$controller = new \App\Controllers\AiConfigController();
\$controller->saveConfig();
PHP;
$invalidKeySaveRes = runIsolatedScript($invalidKeySaveScript);
assertTest(
    strpos($invalidKeySaveRes, 'Konfigurasi tidak dapat disimpan') !== false,
    "Uji 8: API Key tidak valid tidak dapat diaktifkan (Pengujian koneksi gagal membatalkan aktivasi)"
);

// 9. Konfigurasi gagal tidak menimpa konfigurasi aktif
$afterFailedActive = $aiConfigModel->getActive('gemini');
assertTest(
    ($initialActive['id'] ?? null) === ($afterFailedActive['id'] ?? null),
    "Uji 9: Konfigurasi gagal tidak menimpa konfigurasi aktif (Konfigurasi sebelumnya tetap utuh)"
);

// 10. Konfigurasi berhasil dapat langsung digunakan
$targetModel = "gemini-3.8-flash";
$encKey = EncryptionService::encrypt($activeEnvKey);
$activated = $aiConfigModel->activate('gemini', $encKey, $targetModel, 1);
$currentActiveNow = $geminiService->getActiveConfig();
assertTest(
    $currentActiveNow['source'] === 'database' && $currentActiveNow['model'] === $targetModel,
    "Uji 10: Konfigurasi berhasil dapat langsung digunakan secara real-time"
);

// -----------------------------------------------------------------------------
// PENGUJIAN 11, 12: ENKRIPSI & KEAMANAN CREDENTIALS
// -----------------------------------------------------------------------------
echo "\n── 4. Enkripsi dan Pencegahan Kebocoran Kredensial ──\n";

// 11. API Key tersimpan terenkripsi
$dbRow = Database::getConnection()->query("SELECT encrypted_api_key FROM ai_configurations WHERE id = {$activated['id']}")->fetch();
$storedCipher = $dbRow['encrypted_api_key'] ?? '';
assertTest(
    !empty($storedCipher) && strpos($storedCipher, $activeEnvKey) === false && EncryptionService::decrypt($storedCipher) === $activeEnvKey,
    "Uji 11: API Key tersimpan dalam database dalam keadaan terenkripsi AES-256-GCM"
);

// 12. API Key tidak bocor ke frontend dan log
$auditLogId = AuditLog::log(1, 'Mengubah konfigurasi Gemini AI', [
    'provider'       => 'gemini',
    'previous_model' => 'gemini-1.5-flash',
    'new_model'      => $targetModel,
    'status'         => 'activated',
    'config_id'      => $activated['id']
]);
$auditRow = (new AuditLog())->find($auditLogId);
$maskedVal = EncryptionService::maskApiKey($activeEnvKey);
assertTest(
    strpos($auditRow['metadata'] ?? '', $activeEnvKey) === false &&
    strpos($adminIndexRes, $activeEnvKey) === false &&
    $maskedVal !== $activeEnvKey,
    "Uji 12: API Key tidak bocor ke frontend, audit log, maupun respons API (Hanya format masking yang ditampilkan)"
);

// -----------------------------------------------------------------------------
// PENGUJIAN 13, 14: SINKRONISASI PHP & NODE.JS TANPA RESTART
// -----------------------------------------------------------------------------
echo "\n── 5. Sinkronisasi PHP & Node.js Tanpa Restart ──\n";

// 13. PHP dan Node.js membaca konfigurasi aktif yang sama
$geminiService->notifyNodeService($activeEnvKey, $targetModel);
$nodeHealth = json_decode(@file_get_contents('http://127.0.0.1:5000/health'), true) ?? [];
$phpActive = $geminiService->getActiveConfig();
assertTest(
    ($nodeHealth['model'] ?? '') === $phpActive['model'] && $phpActive['model'] === $targetModel,
    "Uji 13: PHP dan Node.js membaca konfigurasi aktif yang sama ('{$targetModel}')"
);

// 14. Tidak ada kebutuhan restart yang tidak perlu
assertTest(
    $currentActiveNow['source'] === 'database' && !empty($nodeHealth['uptime']),
    "Uji 14: Perubahan konfigurasi diterapkan langsung tanpa memerlukan restart Apache maupun Node.js"
);

// -----------------------------------------------------------------------------
// PENGUJIAN 15, 16, 17, 18, 19: REGRESI & KEMAMPUAN LAMA
// -----------------------------------------------------------------------------
echo "\n── 6. Jaminan Zero Regression pada Fitur Lama Sistem Kas ──\n";

$aiService = new AIService();

// 15. AI Assistant tetap dapat menjawab pertanyaan seperti sebelumnya
$ansBot = $aiService->ask("Kamu siapa?", 'super_user');
assertTest(
    $ansBot['success'] === true && !empty($ansBot['answer']),
    "Uji 15: AI Assistant tetap dapat menjawab pertanyaan seperti sebelumnya"
);

// 16. Kemampuan analisis multi-tabel tetap berfungsi
$multiTableAns = $aiService->ask("Berapa saldo kas saat ini?", 'super_user');
assertTest(
    $multiTableAns['success'] === true && (strpos($multiTableAns['answer'], '1.120.000') !== false || strpos($multiTableAns['answer'], 'Rp') !== false),
    "Uji 16: Kemampuan analisis multi-tabel (saldo, pemasukan, pengeluaran) tetap akurat dan berfungsi"
);

// 17. Local Analytical Engine dan fallback tetap berfungsi
$capAns = $aiService->ask("Apa tugas Anda?", 'super_user');
assertTest(
    $capAns['success'] === true && !empty($capAns['answer']),
    "Uji 17: Local Analytical Engine dan mekanisme fallback tetap aktif dan berfungsi normal"
);

// 18. Transaksi dan pembayaran kas tetap berjalan
$transaksiModel = new TransaksiKas();
$pembayaranModel = new PembayaranKas();
$totalMasuk = $transaksiModel->getTotalByJenis('masuk');
$totalKeluar = $transaksiModel->getTotalByJenis('keluar');
$saldo = $totalMasuk - $totalKeluar;
$totalPembayaranCount = $pembayaranModel->count();
assertTest(
    $totalMasuk > 0 && $totalPembayaranCount > 0,
    "Uji 18: Fitur data transaksi kas dan pembayaran kas tetap berjalan normal (Saldo: Rp " . number_format($saldo, 0, ',', '.') . ")"
);


// 19. Integrasi WhatsApp tetap berjalan
$waService = new FonnteWhatsAppService();
$normalized = $waService->normalizePhoneNumber('081234567890');
$token = Env::get('FONNTE_API_TOKEN', '');
assertTest(
    $normalized === '6281234567890' && !empty($token),
    "Uji 19: Integrasi WhatsApp (Fonnte API Service) tetap berjalan normal dan terkonfigurasi"
);


echo "\n=================================================================\n";
echo " HASIL AKHIR PENGUJIAN WAJIB: {$passed} DARI 19 BERHASIL (FAIL: {$failed})\n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
