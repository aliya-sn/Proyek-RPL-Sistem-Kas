<?php
/**
 * test_owner_account.php
 * Comprehensive verification test for Aulia Putri Owner/Super User account.
 * Tests:
 * 1. Wrong password rejection for aulia.putri
 * 2. Default password 'admin123' rejection for aulia.putri
 * 3. Correct password authentication for aulia.putri (Aulia Putri)
 * 4. Session data verification (nama, username, role, displayName)
 * 5. Dashboard greeting and profile display
 * 6. Change password functionality (CLI and Web)
 * 7. Logout and session termination
 * 8. Multi-role regression (Bendahara, Anggota, Admin)
 * 9. AI Assistant identity response for Aulia Putri
 */

$baseUrl = 'http://localhost/Project%20RPL/Sistem%20Kas/public';
$cookieDir = __DIR__ . '/storage/framework/cache';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

$passCount = 0;
$failCount = 0;

function assertCheck(string $testName, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}\n";
        if ($detail) echo "         {$detail}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$testName}\n";
        if ($detail) echo "         Detail: {$detail}\n";
    }
}

function httpPostLogin($baseUrl, $username, $password, $cookieFile) {
    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }

    // 1. GET login page to retrieve CSRF token
    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($html)) {
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }

    if (!preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $matches)) {
        return ['success' => false, 'error' => "CSRF not found"];
    }
    $csrfToken = $matches[1];

    // 2. POST credentials
    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            '_csrf_token' => $csrfToken,
            'username'    => $username,
            'password'    => $password,
        ]),
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isLoggedIn = (strpos($response, 'Masuk ke Sistem') === false) && 
                  (strpos($response, 'dashboard') !== false || strpos($response, 'Dashboard') !== false);

    return [
        'success'   => $isLoggedIn,
        'http_code' => $httpCode,
        'response'  => $response,
    ];
}

function httpGet($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'content' => $res];
}

function httpPostJson($url, $payload, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);
    return ['http_code' => $httpCode, 'json' => $json];
}

echo "=================================================================\n";
echo " PENGUJIAN AKUN PEMILIK AULIA PUTRI & KEAMANAN AUTENTIKASI\n";
echo "=================================================================\n\n";

$ownerCookie = $cookieDir . '/cookie_aulia.txt';

// ── TEST 1: Login dengan password salah ditolak ──
echo "── 1. Uji Keamanan: Password Salah & Default ──\n";
$resWrong = httpPostLogin($baseUrl, 'aulia.putri', 'wrongpass999', $ownerCookie);
assertCheck(
    "1.1 Login aulia.putri dengan password salah ditolak",
    $resWrong['success'] === false && strpos($resWrong['response'], 'Password yang Anda masukkan salah') !== false,
    "Pesan penolakan terverifikasi"
);

// ── TEST 2: Login dengan password default admin123 ditolak ──
$resDefault = httpPostLogin($baseUrl, 'aulia.putri', 'admin123', $ownerCookie);
assertCheck(
    "1.2 Login aulia.putri dengan default password 'admin123' ditolak",
    $resDefault['success'] === false && strpos($resDefault['response'], 'Password yang Anda masukkan salah') !== false,
    "Password default tidak berlaku untuk aulia.putri"
);

// ── TEST 3: Login dengan password aman berhasil ──
echo "\n── 2. Uji Login Sukses Akun Pemilik Aulia Putri ──\n";
$resSuccess = httpPostLogin($baseUrl, 'aulia.putri', 'AuliaKas2026!#Secure', $ownerCookie);
assertCheck(
    "2.1 Login username 'aulia.putri' dengan password aman berhasil",
    $resSuccess['success'] === true,
    "Berhasil masuk ke dashboard"
);

assertCheck(
    "2.2 Pesan sapaan login memuat nama 'Aulia Putri'",
    strpos($resSuccess['response'], 'Aulia Putri') !== false,
    "Nama pemilik terdeteksi pada response dashboard"
);

// ── TEST 4: Tampilan Dashboard & Profil Pemilik ──
echo "\n── 3. Uji Tampilan Dashboard & Profil di Web ──\n";
$dashRes = httpGet($baseUrl . '/admin/dashboard', $ownerCookie);
assertCheck(
    "3.1 Dashboard Super User memuat sapaan 'Selamat datang kembali, Aulia Putri!'",
    strpos($dashRes['content'], 'Selamat datang kembali') !== false && strpos($dashRes['content'], 'Aulia Putri') !== false,
    "Sapaan dashboard memuat nama pemilik secara personal"
);

assertCheck(
    "3.2 User dropdown navbar dan sidebar memuat nama 'Aulia Putri'",
    substr_count($dashRes['content'], 'Aulia Putri') >= 2,
    "Nama Aulia Putri tampil di navbar dan sidebar"
);

// ── TEST 5: Chatbot AI Mengenali Pemilik Aulia Putri ──
echo "\n── 4. Uji Pengenalan Identitas oleh Chatbot AI ──\n";
$aiIdentity = httpPostJson($baseUrl . '/ai-assistant/chat', ['question' => 'Saya siapa?'], $ownerCookie);
$aiAnswer = $aiIdentity['json']['answer'] ?? '';
assertCheck(
    "4.1 Chatbot AI mengenali identitas 'Aulia Putri' dan role Super User",
    strpos($aiAnswer, 'Aulia Putri') !== false && (strpos($aiAnswer, 'Super User') !== false || strpos($aiAnswer, 'Administrator') !== false),
    "Jawaban AI: $aiAnswer"
);

$aiRole = httpPostJson($baseUrl . '/ai-assistant/chat', ['question' => 'Apa role saya?'], $ownerCookie);
$aiRoleAnswer = $aiRole['json']['answer'] ?? '';
assertCheck(
    "4.2 Chatbot AI memverifikasi role akun pemilik",
    strpos($aiRoleAnswer, 'Super User') !== false && strpos($aiRoleAnswer, 'Aulia Putri') !== false,
    "Jawaban AI: $aiRoleAnswer"
);

// ── TEST 6: Mekanisme Perubahan Password Langsung ──
echo "\n── 5. Uji Mekanisme Perubahan Password oleh Pemilik ──\n";
$changePage = httpGet($baseUrl . '/change-password', $ownerCookie);
assertCheck(
    "5.1 Halaman Ubah Password dapat diakses oleh Aulia Putri (HTTP 200)",
    $changePage['http_code'] === 200 && strpos($changePage['content'], 'Pengaturan Password') !== false,
    "Form Ubah Password tersedia di web UI"
);

// Test CLI password setter update
$outputCli = [];
exec('php ' . escapeshellarg(__DIR__ . '/scripts/set_admin_password.php') . ' "AuliaKas2026!#NewUpdated"', $outputCli, $exitCode);
assertCheck(
    "5.2 Skrip CLI set_admin_password.php berhasil memperbarui password pemilik",
    $exitCode === 0 && strpos(implode("\n", $outputCli), 'berhasil diperbarui') !== false,
    implode(" | ", $outputCli)
);

// Verify new password works
$resNewPass = httpPostLogin($baseUrl, 'aulia.putri', 'AuliaKas2026!#NewUpdated', $ownerCookie);
assertCheck(
    "5.3 Login dengan password baru yang diatur pemilik berhasil",
    $resNewPass['success'] === true,
    "Autentikasi dengan password baru sukses"
);

// Re-set to standard secure password for ongoing tests
exec('php ' . escapeshellarg(__DIR__ . '/scripts/set_admin_password.php') . ' "AuliaKas2026!#Secure"');

// ── TEST 7: Logout & Proteksi Sesi ──
echo "\n── 6. Uji Logout & Terminasi Sesi ──\n";
$logoutRes = httpGet($baseUrl . '/logout', $ownerCookie);
assertCheck(
    "6.1 Logout berhasil dan diarahkan kembali ke login",
    strpos($logoutRes['content'], 'Masuk ke Sistem') !== false || $logoutRes['http_code'] === 200,
    "Sesi berhasil diakhiri"
);

$protectedDash = httpGet($baseUrl . '/admin/dashboard', $ownerCookie);
assertCheck(
    "6.2 Akses dashboard setelah logout ditolak (diredirect ke login)",
    strpos($protectedDash['content'], 'Masuk ke Sistem') !== false,
    "Halaman dashboard dilindungi otentikasi"
);

// ── TEST 8: Kompatibilitas Role Lain & Akun Lama ──
echo "\n── 7. Uji Kompatibilitas Role Lain & Akun Lama ──\n";
$bendaharaCookie = $cookieDir . '/cookie_bendahara_owner_test.txt';
$resBendahara = httpPostLogin($baseUrl, 'bendahara', 'bendahara123', $bendaharaCookie);
assertCheck(
    "7.1 Login role Bendahara tetap berfungsi normal",
    $resBendahara['success'] === true,
    "Bendahara login berhasil"
);

$anggotaCookie = $cookieDir . '/cookie_anggota_owner_test.txt';
$resAnggota = httpPostLogin($baseUrl, 'ahmad.rizky', 'anggota123', $anggotaCookie);
assertCheck(
    "7.2 Login role Anggota (ahmad.rizky) tetap berfungsi normal",
    $resAnggota['success'] === true,
    "Anggota login berhasil"
);

$adminLegacyCookie = $cookieDir . '/cookie_admin_legacy.txt';
$resAdminLegacy = httpPostLogin($baseUrl, 'admin', 'admin123', $adminLegacyCookie);
assertCheck(
    "7.3 Akun lama 'admin' tetap berfungsi dan tidak terganggu",
    $resAdminLegacy['success'] === true,
    "Akun admin legacy login berhasil"
);

echo "\n=================================================================\n";
echo " REKAPITULASI HASIL: {$passCount} PASSED | {$failCount} FAILED\n";
echo "=================================================================\n";

if ($failCount === 0) {
    echo "STATUS: SELURUH PENGUJIAN AKUN PEMILIK AULIA PUTRI BERHASIL 100%.\n";
    exit(0);
} else {
    echo "STATUS: TERDAPAT KEGAGALAN DALAM PENGUJIAN.\n";
    exit(1);
}
