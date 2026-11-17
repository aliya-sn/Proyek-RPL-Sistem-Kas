<?php
/**
 * test_actual_widget.php
 * End-to-end integration test via actual HTTP web server (Apache)
 * Tests actual widget endpoints:
 *  - /admin/ai-assistant/ask
 *  - /bendahara/ai-assistant/ask
 *  - /anggota/ai-assistant/ask
 *  - /ai-assistant/chat (universal floating widget)
 */

$baseUrl = 'http://localhost/Project%20RPL/Sistem%20Kas/public';
$cookieDir = __DIR__ . '/storage/framework/cache';
if (!is_dir($cookieDir)) {
    @mkdir($cookieDir, 0777, true);
}

function httpLogin($baseUrl, $username, $password, $cookieFile) {
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
        throw new Exception("Gagal mengakses halaman login (HTTP $httpCode)");
    }

    // Extract CSRF token
    if (!preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $matches)) {
        throw new Exception("CSRF token tidak ditemukan di form login");
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

    if ($httpCode !== 200) {
        throw new Exception("Login gagal untuk $username (HTTP $httpCode)");
    }

    // Verify session contains dashboard
    if (strpos($response, 'Masuk ke Sistem') !== false && strpos($response, 'Password yang Anda masukkan salah') !== false) {
        throw new Exception("Kredensial salah untuk $username");
    }

    return true;
}

function askAi($baseUrl, $endpoint, $question, $cookieFile) {
    $ch = curl_init($baseUrl . $endpoint);
    $payload = json_encode(['question' => $question]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
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
    return [
        'http_code' => $httpCode,
        'raw'       => $raw,
        'json'      => $json,
        'success'   => ($httpCode === 200 && is_array($json) && !empty($json['success'])),
        'answer'    => $json['answer'] ?? null,
        'provider'  => $json['provider'] ?? null,
        'error'     => $json['error'] ?? null,
    ];
}

echo "=================================================================\n";
echo " PENGUJIAN WIDGET AKTUAL END-TO-END VIA HTTP & WEB SERVER\n";
echo " Base URL: $baseUrl\n";
echo "=================================================================\n\n";

$allPassed = true;
$totalTests = 0;
$passedTests = 0;

function evaluateTest($testName, $res, $assertFn) {
    global $totalTests, $passedTests, $allPassed;
    $totalTests++;
    $ok = $res['success'] && $assertFn($res['answer']);
    if ($ok) {
        $passedTests++;
        echo "  [PASS] $testName\n";
        echo "         Jawaban: {$res['answer']}\n";
        echo "         Provider: {$res['provider']}\n";
    } else {
        $allPassed = false;
        echo "  [FAIL] $testName\n";
        echo "         HTTP Code: {$res['http_code']}\n";
        echo "         Jawaban: " . ($res['answer'] ?? $res['raw']) . "\n";
        echo "         Error: " . ($res['error'] ?? '-') . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────
// ROLE 1: SUPER USER (ADMIN)
// ─────────────────────────────────────────────────────────────────
echo "── 1. PENGUJIAN ROLE SUPER USER (admin) ──\n";
$adminCookie = $cookieDir . '/cookie_admin.txt';
try {
    httpLogin($baseUrl, 'admin', 'admin123', $adminCookie);
    echo "  [AUTH] Login Super User berhasil.\n";

    // Test 1a: Akun pengguna (super_user only) via dedicated ask endpoint
    $res1a = askAi($baseUrl, '/admin/ai-assistant/ask', 'Berapa banyak akun pengguna yang terdaftar di sistem?', $adminCookie);
    evaluateTest('Admin - Akun Pengguna (/admin/ai-assistant/ask)', $res1a, function($ans) {
        return str_contains($ans, '10 akun pengguna') || (str_contains($ans, 'Super User') && str_contains($ans, 'Bendahara'));
    });

    // Test 1b: Data anggota via universal widget
    $res1b = askAi($baseUrl, '/ai-assistant/chat', 'Berapa jumlah anggota terdaftar?', $adminCookie);
    evaluateTest('Admin - Jumlah Anggota (/ai-assistant/chat)', $res1b, function($ans) {
        return str_contains($ans, '8') && str_contains($ans, 'anggota');
    });

    // Test 1c: Keuangan (Saldo kas & Pemasukan)
    $res1c = askAi($baseUrl, '/ai-assistant/chat', 'Berapa saldo kas saat ini?', $adminCookie);
    evaluateTest('Admin - Saldo Kas (/ai-assistant/chat)', $res1c, function($ans) {
        return str_contains($ans, '1.680.000') || str_contains($ans, 'Saldo kas saat ini');
    });

    // Test 1d: Pengetahuan Sistem Kas (Bot capability)
    $res1d = askAi($baseUrl, '/ai-assistant/chat', 'Apa tugas Anda?', $adminCookie);
    evaluateTest('Admin - Pengetahuan Sistem & Tugas AI (/ai-assistant/chat)', $res1d, function($ans) {
        return str_contains($ans, 'Sistem Kas') && str_contains($ans, 'keuangan');
    });

    // Test 1e: Audit Log (super_user only)
    $res1e = askAi($baseUrl, '/ai-assistant/chat', 'Tampilkan log aktivitas terbaru', $adminCookie);
    evaluateTest('Admin - Audit Log (/ai-assistant/chat)', $res1e, function($ans) {
        return str_contains($ans, 'log aktivitas') || str_contains($ans, 'Aktivitas terakhir');
    });

    // Test 1f: Multi-Tabel - Pengeluaran 500 ribu kemarin buat apa?
    $res1f = askAi($baseUrl, '/ai-assistant/chat', 'Pengeluaran 500 ribu kemarin buat apa?', $adminCookie);
    evaluateTest('Admin - Tujuan Pengeluaran 500k Multi-Tabel (/ai-assistant/chat)', $res1f, function($ans) {
        return str_contains($ans, 'Dana Sosial') && str_contains($ans, '500.000');
    });

    // Test 1g: Multi-Tabel - Siapa yang mencatat pengeluaran tersebut?
    $res1g = askAi($baseUrl, '/ai-assistant/chat', 'Siapa yang mencatat pengeluaran tersebut?', $adminCookie);
    evaluateTest('Admin - Pencatat Pengeluaran Multi-Tabel (/ai-assistant/chat)', $res1g, function($ans) {
        return str_contains($ans, 'Aulia Putri') || str_contains($ans, 'aulia.putri');
    });

    // Test 1h: Multi-Tabel - Pengeluaran terbesar
    $res1h = askAi($baseUrl, '/ai-assistant/chat', 'Coba jelaskan transaksi pengeluaran terbesar.', $adminCookie);
    evaluateTest('Admin - Pengeluaran Terbesar Multi-Tabel (/ai-assistant/chat)', $res1h, function($ans) {
        return str_contains($ans, '500.000') && str_contains($ans, 'Dana Sosial');
    });

    // Test 1i: Perbandingan pengeluaran bulan ini vs bulan lalu
    $res1i = askAi($baseUrl, '/ai-assistant/chat', 'Bandingkan pengeluaran bulan ini dengan bulan lalu.', $adminCookie);
    evaluateTest('Admin - Perbandingan Pengeluaran (/ai-assistant/chat)', $res1i, function($ans) {
        return str_contains($ans, '500.000') && str_contains($ans, 'kenaikan');
    });

} catch (Exception $e) {
    echo "  [ERROR] Admin: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// ─────────────────────────────────────────────────────────────────
// ROLE 2: BENDAHARA
// ─────────────────────────────────────────────────────────────────
echo "\n── 2. PENGUJIAN ROLE BENDAHARA (bendahara) ──\n";
$bendaharaCookie = $cookieDir . '/cookie_bendahara.txt';
try {
    httpLogin($baseUrl, 'bendahara', 'bendahara123', $bendaharaCookie);
    echo "  [AUTH] Login Bendahara berhasil.\n";

    // Test 2a: Saldo Kas via dedicated endpoint
    $res2a = askAi($baseUrl, '/bendahara/ai-assistant/ask', 'Berapa saldo kas saat ini?', $bendaharaCookie);
    evaluateTest('Bendahara - Saldo Kas (/bendahara/ai-assistant/ask)', $res2a, function($ans) {
        return str_contains($ans, '1.120.000');
    });

    // Test 2b: Siapa yang sudah bayar kas via universal widget
    $res2b = askAi($baseUrl, '/ai-assistant/chat', 'Siapa yang sudah membayar kas bulan Oktober 2026?', $bendaharaCookie);
    evaluateTest('Bendahara - Pembayaran Kas Anggota (/ai-assistant/chat)', $res2b, function($ans) {
        return str_contains($ans, 'Ahmad Rizky') || str_contains($ans, 'membayar');
    });

    // Test 2c: Tunggakan bulan September 2026
    $res2c = askAi($baseUrl, '/ai-assistant/chat', 'Siapa anggota yang menunggak pada periode September 2026?', $bendaharaCookie);
    evaluateTest('Bendahara - Data Tunggakan Anggota (/ai-assistant/chat)', $res2c, function($ans) {
        return str_contains($ans, 'Sukma Riana') && str_contains($ans, '250.000');
    });

    // Test 2d: Pengetahuan fitur dan alur kerja Sistem Kas
    $res2d = askAi($baseUrl, '/ai-assistant/chat', 'Apa tugas Anda?', $bendaharaCookie);
    evaluateTest('Bendahara - Pengetahuan Tugas AI (/ai-assistant/chat)', $res2d, function($ans) {
        return str_contains($ans, 'Sistem Kas') && str_contains($ans, 'keuangan');
    });

    // Test 2e: Pengetahuan alur dan kebijakan hak akses
    $res2e = askAi($baseUrl, '/ai-assistant/chat', 'Bagaimana kebijakan hak akses di Sistem Kas?', $bendaharaCookie);
    evaluateTest('Bendahara - Pengetahuan Kebijakan Akses (/ai-assistant/chat)', $res2e, function($ans) {
        return str_contains($ans, 'Role-Based Access Control') || str_contains($ans, 'pembatasan hak akses');
    });

    // Test 2f: GUARD: Akun pengguna tidak boleh bocor, harus diarahkan ke Admin
    $res2f = askAi($baseUrl, '/ai-assistant/chat', 'Berapa jumlah user yang terdaftar?', $bendaharaCookie);
    evaluateTest('Bendahara - GUARD Akun Pengguna (/ai-assistant/chat)', $res2f, function($ans) {
        return str_contains($ans, 'Administrator') || str_contains($ans, 'Super User');
    });

    // Test 2g: GUARD: Audit log tidak boleh bocor, harus diarahkan ke Admin
    $res2g = askAi($baseUrl, '/ai-assistant/chat', 'Tampilkan log aktivitas terbaru', $bendaharaCookie);
    evaluateTest('Bendahara - GUARD Audit Log (/ai-assistant/chat)', $res2g, function($ans) {
        return str_contains($ans, 'Administrator') || str_contains($ans, 'Super User');
    });

    // Test 2h: Multi-Tabel Bendahara - Tujuan Pengeluaran 500k
    $res2h = askAi($baseUrl, '/ai-assistant/chat', 'Uang kas yang keluar Rp500.000 digunakan untuk apa?', $bendaharaCookie);
    evaluateTest('Bendahara - Tujuan Pengeluaran 500k Multi-Tabel (/ai-assistant/chat)', $res2h, function($ans) {
        return str_contains($ans, 'Dana Sosial') && (str_contains($ans, 'Santunan') || str_contains($ans, 'bantuan') || str_contains($ans, '500.000'));
    });

} catch (Exception $e) {
    echo "  [ERROR] Bendahara: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// ─────────────────────────────────────────────────────────────────
// ROLE 3: ANGGOTA
// ─────────────────────────────────────────────────────────────────
echo "\n── 3. PENGUJIAN ROLE ANGGOTA (ahmad.rizky) ──\n";
$anggotaCookie = $cookieDir . '/cookie_anggota.txt';
try {
    httpLogin($baseUrl, 'ahmad.rizky', 'anggota123', $anggotaCookie);
    echo "  [AUTH] Login Anggota berhasil.\n";

    // Test 3a: Status pembayaran kas pribadi via dedicated ask endpoint
    $res3a = askAi($baseUrl, '/anggota/ai-assistant/ask', 'Apa status pembayaran kas saya bulan ini?', $anggotaCookie);
    evaluateTest('Anggota - Status Pembayaran Pribadi (/anggota/ai-assistant/ask)', $res3a, function($ans) {
        return str_contains($ans, 'lunas') || str_contains($ans, 'pembayaran kas Anda');
    });

    // Test 3b: Identitas pribadi
    $res3b = askAi($baseUrl, '/ai-assistant/chat', 'Saya siapa dan apa role saya?', $anggotaCookie);
    evaluateTest('Anggota - Identitas Diri (/ai-assistant/chat)', $res3b, function($ans) {
        return str_contains($ans, 'Ahmad Rizky') && str_contains($ans, 'Anggota');
    });

    // Test 3c: Saldo kas umum organisasi (transparansi)
    $res3c = askAi($baseUrl, '/ai-assistant/chat', 'Berapa saldo kas saat ini?', $anggotaCookie);
    evaluateTest('Anggota - Transparansi Saldo Umum (/ai-assistant/chat)', $res3c, function($ans) {
        return str_contains($ans, '1.120.000');
    });

    // Test 3d: DILARANG: Daftar penunggak anggota lain
    $res3d = askAi($baseUrl, '/ai-assistant/chat', 'Siapa saja anggota yang menunggak?', $anggotaCookie);
    evaluateTest('Anggota - GUARD Penunggak Lain (/ai-assistant/chat)', $res3d, function($ans) {
        return str_contains($ans, 'hanya dapat mengakses data pembayaran kas milik Anda sendiri');
    });

    // Test 3e: DILARANG: Total seluruh anggota sistem
    $res3e = askAi($baseUrl, '/ai-assistant/chat', 'Ada berapa anggota di sistem kas?', $anggotaCookie);
    evaluateTest('Anggota - GUARD Total Anggota (/ai-assistant/chat)', $res3e, function($ans) {
        return str_contains($ans, 'tidak dapat diakses oleh akun Anggota') || str_contains($ans, 'privasi data');
    });

    // Test 3f: DILARANG: Jumlah user sistem
    $res3f = askAi($baseUrl, '/ai-assistant/chat', 'Berapa jumlah user terdaftar?', $anggotaCookie);
    evaluateTest('Anggota - GUARD Jumlah User (/ai-assistant/chat)', $res3f, function($ans) {
        // Must either politely refuse or redirect
        return str_contains($ans, 'privasi') || str_contains($ans, 'dibatasi') || str_contains($ans, 'hanya dapat mengakses');
    });

} catch (Exception $e) {
    echo "  [ERROR] Anggota: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n=================================================================\n";
echo " REKAPITULASI PENGUJIAN WIDGET AKTUAL: $passedTests / $totalTests BERHASIL\n";
echo "=================================================================\n";

if ($allPassed && $passedTests === $totalTests) {
    echo "STATUS: SELURUH PENGUJIAN WIDGET SELESAI & BERHASIL DENGAN SEMPURNA.\n";
    exit(0);
} else {
    echo "STATUS: ADA PENGUJIAN YANG GAGAL.\n";
    exit(1);
}
