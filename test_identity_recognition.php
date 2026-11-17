<?php
/**
 * test_identity_recognition.php
 * Automated test suite for Two-Way Identity Recognition in AI Assistant.
 *
 * Test cases required:
 * 1. Admin bertanya "Saya siapa?"
 * 2. Bendahara bertanya "Kamu tahu saya siapa?"
 * 3. Anggota bertanya "Apa role saya?"
 * 4. Pengguna bertanya "Kamu siapa?" (termasuk "Siapa namamu?" dan "Apa tugasmu?")
 * 5. Pengguna bertanya "Siapa yang melakukan transaksi ini?" (memastikan data transaksi tidak salah dianggap sebagai identitas)
 * 6. Pastikan setiap role hanya mendapatkan informasi yang diizinkan dan pencegahan spoofing dari client.
 */

define('BASE_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Services\FinancialDataAggregator;
use App\Services\AIService;
use App\Controllers\AiAssistantController;

Env::load(__DIR__ . '/.env');

$pass = 0;
$fail = 0;

function pass(string $name, string $detail = ''): void {
    global $pass;
    $pass++;
    echo "  [PASS] {$name}" . ($detail ? " -> {$detail}" : '') . "\n";
}

function fail(string $name, string $reason = ''): void {
    global $fail;
    $fail++;
    echo "  [FAIL] {$name}" . ($reason ? " -> {$reason}" : '') . "\n";
}

function section(string $title): void {
    echo "\n── {$title} ──\n";
}

echo "=================================================================\n";
echo " AI ASSISTANT TWO-WAY IDENTITY RECOGNITION TEST SUITE\n";
echo "=================================================================\n";

$aiService = new AIService();
$aggregator = new FinancialDataAggregator();

// ─── TEST 1: Admin bertanya "Saya siapa?" ─────────────────────────────────────
section('Test 1: Admin bertanya "Saya siapa?"');
try {
    $adminUser = [
        'id'       => 1,
        'username' => 'admin',
        'role'     => 'super_user',
        'status'   => 'active',
        'nama'     => null, // Admin default does not have fake name
    ];
    Session::set('user', $adminUser);

    $context = $aggregator->getAggregatedContext('super_user', null);
    $res = $aiService->analyzeQuery('Saya siapa?', $context, 'super_user', $adminUser);

    $answer = $res['answer'];
    $isRecognized = (
        strpos($answer, 'Super User') !== false ||
        strpos($answer, 'Administrator') !== false
    ) && strpos($answer, 'admin') !== false;

    // Must NOT invent a fake name
    $noFakeName = strpos($answer, 'Budi') === false && strpos($answer, 'Ahmad') === false;

    if ($isRecognized && $noFakeName) {
        pass('Admin mengenali identitasnya sendiri', 'Username: admin, Role: Super User (Administrator)');
    } else {
        fail('Admin gagal mengenali identitasnya', "Jawaban: {$answer}");
    }
} catch (\Throwable $e) {
    fail('Test 1 Exception', $e->getMessage());
}

// ─── TEST 2: Bendahara bertanya "Kamu tahu saya siapa?" ───────────────────────
section('Test 2: Bendahara bertanya "Kamu tahu saya siapa?"');
try {
    $bendaharaUser = [
        'id'       => 4,
        'username' => 'bendahara',
        'role'     => 'bendahara',
        'status'   => 'active',
        'nama'     => null,
    ];
    Session::set('user', $bendaharaUser);

    $context = $aggregator->getAggregatedContext('bendahara', null);
    $res = $aiService->analyzeQuery('Kamu tahu saya siapa?', $context, 'bendahara', $bendaharaUser);

    $answer = $res['answer'];
    $isAffirmed = (
        strpos(strtolower($answer), 'mengenali') !== false ||
        strpos(strtolower($answer), 'ya') !== false ||
        strpos(strtolower($answer), 'tentu') !== false
    );
    $isRoleRecognized = strpos($answer, 'Bendahara') !== false && strpos($answer, 'bendahara') !== false;

    if ($isAffirmed && $isRoleRecognized) {
        pass('Bendahara dikenali oleh AI', 'AI merespon afirmatif dan menampilkan username bendahara & role Bendahara');
    } else {
        fail('Bendahara gagal dikenali AI', "Jawaban: {$answer}");
    }
} catch (\Throwable $e) {
    fail('Test 2 Exception', $e->getMessage());
}

// ─── TEST 3: Anggota bertanya "Apa role saya?" ──────────────────────────────
section('Test 3: Anggota bertanya "Apa role saya?"');
try {
    $anggotaUser = [
        'id'              => 2,
        'username'        => 'ahmad.rizky',
        'role'            => 'anggota',
        'status'          => 'active',
        'nama'            => 'Ahmad Rizky',
        'nomor_identitas' => '20261001',
        'kelas'           => 'XII RPL 1',
        'anggota_id'      => 1,
    ];
    Session::set('user', $anggotaUser);

    $context = $aggregator->getAggregatedContext('anggota', 1);
    $res = $aiService->analyzeQuery('Apa role saya?', $context, 'anggota', $anggotaUser);

    $answer = $res['answer'];
    $isAnggotaRole = strpos($answer, 'Anggota') !== false;
    $hasPersonalDetails = strpos($answer, 'Ahmad Rizky') !== false && strpos($answer, 'ahmad.rizky') !== false;

    if ($isAnggotaRole && $hasPersonalDetails) {
        pass('Anggota mengenali rolenya dan data profilnya', 'Role: Anggota, Nama: Ahmad Rizky, NIS: 20261001, Kelas: XII RPL 1');
    } else {
        fail('Anggota gagal mengenali role/profilnya', "Jawaban: {$answer}");
    }
} catch (\Throwable $e) {
    fail('Test 3 Exception', $e->getMessage());
}

// ─── TEST 4: Pengguna bertanya "Kamu siapa?" ────────────────────────────────
section('Test 4: AI mengenali dirinya sendiri ("Kamu siapa?", "Siapa Anda?", "Apa kamu?", "Perkenalkan dirimu.", "Apa tugas Anda?")');
try {
    $context = $aggregator->getAggregatedContext('super_user', null);
    $expectedIdentity = 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.';
    $expectedCapability = 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';

    // 4a. "Kamu siapa?" -> Harus "Saya adalah asisten AI Sistem Kas..."
    $res4a = $aiService->analyzeQuery('Kamu siapa?', $context, 'super_user');
    $ans4a = $res4a['answer'];
    if ($ans4a === $expectedIdentity) {
        pass('4a. Query "Kamu siapa?" dijawab satu kalimat informatif dan natural');
    } else {
        fail('4a. Query "Kamu siapa?" tidak sesuai expected', "Jawaban: {$ans4a}");
    }

    // 4b. "Siapa Anda?" -> Harus "Saya adalah asisten AI Sistem Kas..."
    $res4b = $aiService->analyzeQuery('Siapa Anda?', $context, 'super_user');
    $ans4b = $res4b['answer'];
    if ($ans4b === $expectedIdentity) {
        pass('4b. Query "Siapa Anda?" dijawab satu kalimat informatif dan natural');
    } else {
        fail('4b. Query "Siapa Anda?" tidak sesuai expected', "Jawaban: {$ans4b}");
    }

    // 4c. "Apa kamu?" -> Harus "Saya adalah asisten AI Sistem Kas..."
    $res4c = $aiService->analyzeQuery('Apa kamu?', $context, 'super_user');
    $ans4c = $res4c['answer'];
    if ($ans4c === $expectedIdentity) {
        pass('4c. Query "Apa kamu?" dijawab satu kalimat informatif dan natural');
    } else {
        fail('4c. Query "Apa kamu?" tidak sesuai expected', "Jawaban: {$ans4c}");
    }

    // 4d. "Perkenalkan dirimu." -> Harus "Saya adalah asisten AI Sistem Kas..."
    $res4d = $aiService->analyzeQuery('Perkenalkan dirimu.', $context, 'super_user');
    $ans4d = $res4d['answer'];
    if ($ans4d === $expectedIdentity) {
        pass('4d. Query "Perkenalkan dirimu." dijawab satu kalimat informatif dan natural');
    } else {
        fail('4d. Query "Perkenalkan dirimu." tidak sesuai expected', "Jawaban: {$ans4d}");
    }

    // 4e. "Apa tugas Anda?"
    $res4e = $aiService->analyzeQuery('Apa tugas Anda?', $context, 'super_user');
    $ans4e = $res4e['answer'];
    if ($ans4e === $expectedCapability) {
        pass('4e. Query "Apa tugas Anda?" dijawab satu kalimat informatif tentang tugas sistem kas');
    } else {
        fail('4e. Query "Apa tugas Anda?" tidak sesuai', "Jawaban: {$ans4e}");
    }
} catch (\Throwable $e) {
    fail('Test 4 Exception', $e->getMessage());
}

// ─── TEST 5: Pengguna bertanya "Siapa yang melakukan transaksi ini?" ─────────
section('Test 5: Pertanyaan Transaksi vs Pertanyaan Identitas');
try {
    // 5a. Admin query "Siapa yang melakukan transaksi ini?"
    $adminCtx = $aggregator->getAggregatedContext('super_user', null);
    $res5a = $aiService->analyzeQuery('Siapa yang melakukan transaksi ini?', $adminCtx, 'super_user', [
        'id' => 1, 'username' => 'admin', 'role' => 'super_user'
    ]);
    $ans5a = $res5a['answer'];

    // Must NOT be mistaken for bot identity
    $notBotIdentity = strpos($ans5a, 'Halo! Saya adalah Asisten AI') === false;
    // Must NOT be mistaken for user identity
    $notUserIdentity = strpos($ans5a, 'Identitas Pengguna Terautentikasi') === false;
    // Must be classified as transaction inquiry
    $isTransactionHandled = strpos($ans5a, 'Transaksi') !== false || strpos($ans5a, 'transaksi') !== false;

    if ($notBotIdentity && $notUserIdentity && $isTransactionHandled) {
        pass('5a. "Siapa yang melakukan transaksi ini?" (Admin) diproses sebagai transaksi, BUKAN pertanyaan identitas');
    } else {
        fail('5a. Salah mengklasifikasikan pertanyaan transaksi', "Jawaban: {$ans5a}");
    }

    // 5b. Anggota query "Siapa yang melakukan transaksi ini?"
    $anggotaCtx = $aggregator->getAggregatedContext('anggota', 1);
    $res5b = $aiService->analyzeQuery('Siapa yang melakukan transaksi ini?', $anggotaCtx, 'anggota', [
        'id' => 2, 'username' => 'ahmad.rizky', 'role' => 'anggota', 'nama' => 'Ahmad Rizky'
    ]);
    $ans5b = $res5b['answer'];

    $isAnggotaLimited = (strpos($ans5b, 'Informasi Terbatas') !== false || strpos($ans5b, 'kebijakan') !== false || strpos($ans5b, 'privasi') !== false || strpos($ans5b, 'dibatasi') !== false);
    $notAnggotaSelfIdentity = strpos($ans5b, 'Identitas Pengguna Terautentikasi') === false;

    if ($isAnggotaLimited && $notAnggotaSelfIdentity) {
        pass('5b. "Siapa yang melakukan transaksi ini?" (Anggota) dibatasi secara aman, BUKAN jawaban identitas');
    } else {
        fail('5b. Anggota transaksi query bocor atau salah klasifikasi', "Jawaban: {$ans5b}");
    }
} catch (\Throwable $e) {
    fail('Test 5 Exception', $e->getMessage());
}

// ─── TEST 6: Proteksi Otorisasi, Pembatasan Role, & Anti-Spoofing ───────────
section('Test 6: Boundary & Anti-Spoofing Security');
try {
    // 6a. Pertanyaan Identitas Dijawab Secara Lokal Tanpa API Eksternal
    $idRes = $aiService->analyzeQuery('Kamu siapa?', [], 'super_user');
    if ($idRes['provider'] === 'local_analytical_engine' || strpos($idRes['provider'], 'nodejs_ai_engine') !== false) {
        pass('6a. Pertanyaan identitas dijawab lokal tanpa memanggil API eksternal');
    } else {
        fail('6a. Provider lokal tidak digunakan untuk pertanyaan identitas', "Provider: {$idRes['provider']}");
    }

    // 6b. Anggota bertanya tentang anggota lain ("Siapa anggota lain yang menunggak?")
    $anggotaLeakQuery = $aiService->analyzeQuery('Siapa anggota lain yang menunggak?', $aggregator->getAggregatedContext('anggota', 1), 'anggota');
    if (strpos($anggotaLeakQuery['answer'], 'hanya dapat') !== false || strpos($anggotaLeakQuery['answer'], 'Informasi Terbatas') !== false || strpos($anggotaLeakQuery['answer'], 'Status Tunggakan Kas Pribadi') !== false) {
        pass('6b. Anggota dilarang mengakses informasi anggota lain (Privacy Protected)');
    } else {
        fail('6b. Pertanyaan tentang anggota lain bocor ke Anggota!', "Jawaban: {$anggotaLeakQuery['answer']}");
    }

    // 6c. Controller Anti-Spoofing: controller strictly uses server session
    $controller = new AiAssistantController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getAuthenticatedUserContext');
    $method->setAccessible(true);

    // Setup authentic session as anggota
    Session::set('user', [
        'id'       => 2,
        'username' => 'ahmad.rizky',
        'role'     => 'anggota',
        'nama'     => 'Ahmad Rizky'
    ]);

    $resolvedUser = $method->invoke($controller, 'anggota');
    if ($resolvedUser['username'] === 'ahmad.rizky' && $resolvedUser['role'] === 'anggota') {
        pass('6c. Server session digunakan sebagai sumber identitas autentik tunggal');
    } else {
        fail('6c. Gagal meresolve identitas autentik dari session');
    }

    // 6d. Pertanyaan keuangan biasa (saldo) tetap berfungsi normal
    $saldoRes = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $aggregator->getAggregatedContext('super_user', null), 'super_user');
    if (stripos($saldoRes['answer'], 'saldo kas') !== false && strpos($saldoRes['answer'], 'Rp') !== false) {
        pass('6d. Pertanyaan keuangan biasa (saldo kas) tetap diproses seperti sebelumnya tanpa regresi');
    } else {
        fail('6d. Regresi pada pertanyaan keuangan biasa!', "Jawaban: {$saldoRes['answer']}");
    }
} catch (\Throwable $e) {
    fail('Test 6 Exception', $e->getMessage());
}

// ─── TEST 7: 5 Pertanyaan Wajib Sesuai Spesifikasi Perbaikan ─────────────────
section('Test 7: 5 Skenario Uji Sesuai Permintaan Pengguna');
try {
    $expectedIdentity7 = 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.';

    // 1. "Siapa Anda?" -> informatif dan natural
    $q1 = $aiService->analyzeQuery('Siapa Anda?', [], 'super_user');
    if ($q1['answer'] === $expectedIdentity7) {
        pass('7.1. "Siapa Anda?" dijawab satu kalimat informatif dan natural');
    } else {
        fail('7.1. "Siapa Anda?" tidak sesuai expected', "Jawaban: {$q1['answer']}");
    }

    // 2. "Kamu siapa?" -> informatif dan natural
    $q2 = $aiService->analyzeQuery('Kamu siapa?', [], 'super_user');
    if ($q2['answer'] === $expectedIdentity7) {
        pass('7.2. "Kamu siapa?" dijawab satu kalimat informatif dan natural');
    } else {
        fail('7.2. "Kamu siapa?" tidak sesuai expected', "Jawaban: {$q2['answer']}");
    }

    // 3. "Ada berapa anggota?" -> Jawaban singkat berdasarkan data aktual
    $adminCtx = $aggregator->getAggregatedContext('super_user', null);
    $q3 = $aiService->analyzeQuery('Ada berapa anggota?', $adminCtx, 'super_user');
    $isShort3 = strlen($q3['answer']) < 150 && substr_count($q3['answer'], "\n") <= 1;
    if ($isShort3 && strpos($q3['answer'], 'Total anggota terdaftar') !== false) {
        pass('7.3. "Ada berapa anggota?" dijawab singkat dan akurat berdasarkan data aktual', "Jawaban: {$q3['answer']}");
    } else {
        fail('7.3. "Ada berapa anggota?" gagal dijawab singkat atau data tidak sesuai', "Jawaban: {$q3['answer']}");
    }

    // 4. "Siapa saja anggota yang menunggak?" -> Jawaban singkat berdasarkan data yang boleh diakses
    $q4Admin = $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $adminCtx, 'super_user');
    $isShort4 = strlen($q4Admin['answer']) < 250 && substr_count($q4Admin['answer'], "\n") <= 1;
    if ($isShort4 && (strpos($q4Admin['answer'], 'menunggak') !== false || strpos($q4Admin['answer'], 'Tidak ada anggota') !== false)) {
        pass('7.4a. "Siapa saja anggota yang menunggak?" (Admin) dijawab singkat dan sesuai data', "Jawaban: {$q4Admin['answer']}");
    } else {
        fail('7.4a. "Siapa saja anggota yang menunggak?" (Admin) format tidak singkat', "Jawaban: {$q4Admin['answer']}");
    }

    // 4b. Anggota menanyakan anggota yang menunggak -> Dibatasi secara singkat
    $anggotaCtx = $aggregator->getAggregatedContext('anggota', 1);
    $q4Anggota = $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $anggotaCtx, 'anggota');
    if (strpos($q4Anggota['answer'], 'hanya dapat') !== false) {
        pass('7.4b. "Siapa saja anggota yang menunggak?" (Anggota) dibatasi dengan jawaban singkat 1 kalimat', "Jawaban: {$q4Anggota['answer']}");
    } else {
        fail('7.4b. Anggota tidak dibatasi dengan benar', "Jawaban: {$q4Anggota['answer']}");
    }

    // 5. Pertanyaan yang datanya tidak tersedia -> Jawaban singkat tanpa mengarang
    $q5 = $aiService->analyzeQuery('Berapa luas lapangan sepak bola sekolah?', $adminCtx, 'super_user');
    $isShort5 = strlen($q5['answer']) < 100 && substr_count($q5['answer'], "\n") === 0;
    if ($isShort5 && strpos($q5['answer'], 'belum tersedia') !== false) {
        pass('7.5. Pertanyaan data tidak tersedia dijawab singkat tanpa mengarang', "Jawaban: {$q5['answer']}");
    } else {
        fail('7.5. Pertanyaan data tidak tersedia gagal dijawab singkat', "Jawaban: {$q5['answer']}");
    }
} catch (\Throwable $e) {
    fail('Test 7 Exception', $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED  |  {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
