<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Core/Env.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Model.php';
require_once __DIR__ . '/app/Models/LaporanKeuangan.php';
require_once __DIR__ . '/app/Models/TransaksiKas.php';
require_once __DIR__ . '/app/Models/PembayaranKas.php';
require_once __DIR__ . '/app/Models/Anggota.php';
require_once __DIR__ . '/app/Services/FinancialDataAggregator.php';
require_once __DIR__ . '/app/Services/AIService.php';

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
        $_ENV[trim($name)] = trim($value);
    }
}

use App\Core\Model;
use App\Services\FinancialDataAggregator;
use App\Services\AIService;

echo "=================================================================\n";
echo " TEST SUITE: PEMAHAMAN SELURUH DATABASE OLEH AI SISTEM KAS\n";
echo "=================================================================\n\n";

$aggregator = new FinancialDataAggregator();
$aiService  = new AIService();
$db = Model::db();

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        if ($details) echo "         {$details}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         Detail: {$details}\n";
    }
}

// ─────────────────────────────────────────────────────────────────
// BAGIAN 1: AUDIT SELURUH STRUKTUR DATABASE (7 TABEL AKTUAL)
// ─────────────────────────────────────────────────────────────────
echo "── BAGIAN 1: Audit Seluruh Database Aktual ──\n";

// 1.1 Periksa seluruh tabel yang ada di database
$stmtTables = $db->query("SHOW TABLES");
$tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['ai_chat_history', 'anggota', 'audit_log', 'kategori_transaksi', 'pembayaran_kas', 'transaksi_kas', 'users'];
$missingTables = array_diff($expectedTables, $tables);

assertTest(
    "1.1 Seluruh 7 tabel utama terdeteksi di database aktual",
    empty($missingTables),
    "Tabel ditemukan: " . implode(', ', $tables)
);

// 1.2 Verifikasi data aktual di tabel
$countUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$countAnggota = (int)$db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
$countKategori = (int)$db->query("SELECT COUNT(*) FROM kategori_transaksi")->fetchColumn();
$countTransaksi = (int)$db->query("SELECT COUNT(*) FROM transaksi_kas")->fetchColumn();
$countPembayaran = (int)$db->query("SELECT COUNT(*) FROM pembayaran_kas")->fetchColumn();
$countAudit = (int)$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

assertTest(
    "1.2 Seluruh data terhubung dengan benar",
    $countUsers >= 10 && $countAnggota === 8 && $countKategori === 5 && $countPembayaran === 20,
    "Users: {$countUsers}, Anggota: {$countAnggota}, Kategori: {$countKategori}, Transaksi: {$countTransaksi}, Pembayaran: {$countPembayaran}, Audit Log: {$countAudit}"
);

// ─────────────────────────────────────────────────────────────────
// BAGIAN 2: HUBUNGAN AI DENGAN DATA AKTUAL (QUERY DINAMIS)
// ─────────────────────────────────────────────────────────────────
echo "\n── BAGIAN 2: Pengujian Query Data Aktual oleh AI (Super User & Bendahara) ──\n";

// Test 2.1: Jumlah seluruh anggota dan anggota aktif
$qAnggota = "Berapa jumlah seluruh anggota dan anggota aktif?";
$ctxAdmin = $aggregator->getAggregatedContext('super_user', null, $qAnggota);
$resAnggota = $aiService->analyzeQuery($qAnggota, $ctxAdmin, 'super_user');
assertTest(
    "2.1 Jumlah seluruh anggota dan anggota aktif",
    str_contains($resAnggota['answer'], '8') && str_contains($resAnggota['answer'], 'aktif'),
    "Jawaban: " . $resAnggota['answer']
);

// Test 2.2: Anggota laki-laki dan perempuan
$qGender = "Berapa anggota laki-laki dan perempuan?";
$resGender = $aiService->analyzeQuery($qGender, $ctxAdmin, 'super_user');
assertTest(
    "2.2 Demografi gender anggota",
    str_contains($resGender['answer'], '2') && str_contains($resGender['answer'], '6'),
    "Jawaban: " . $resGender['answer']
);

// Test 2.3: Siapa saja anggota yang menunggak bulan ini (Oktober 2026 - Semua lunas)
$qTunggak = "Siapa saja anggota yang menunggak?";
$resTunggak = $aiService->analyzeQuery($qTunggak, $ctxAdmin, 'super_user');
assertTest(
    "2.3 Anggota yang menunggak bulan berjalan (Oktober 2026)",
    str_contains($resTunggak['answer'], 'Tidak ada anggota yang menunggak'),
    "Jawaban: " . $resTunggak['answer']
);

// Test 2.4: Siapa saja anggota yang menunggak periode September 2026 (Sukma Riana belum bayar)
$qTunggakSep = "Siapa saja anggota yang menunggak di bulan September 2026?";
$ctxSepTunggak = $aggregator->getAggregatedContext('super_user', null, $qTunggakSep);
$resTunggakSep = $aiService->analyzeQuery($qTunggakSep, $ctxSepTunggak, 'super_user');
assertTest(
    "2.4 Anggota yang menunggak periode spesifik (September 2026)",
    str_contains($resTunggakSep['answer'], 'Sukma Riana'),
    "Jawaban: " . $resTunggakSep['answer']
);

// Test 2.5: Siapa yang sudah membayar dan berapa nominalnya (Bulan ini / Oktober 2026)
$qSudahBayar = "Siapa yang sudah membayar dan berapa nominalnya?";
$resSudahBayar = $aiService->analyzeQuery($qSudahBayar, $ctxAdmin, 'super_user');
assertTest(
    "2.5 Siapa yang sudah membayar dan nominalnya (Oktober 2026)",
    str_contains($resSudahBayar['answer'], 'Ahmad Rizky') && str_contains($resSudahBayar['answer'], '660.000'),
    "Jawaban: " . $resSudahBayar['answer']
);

// Test 2.6: Rincian pembayaran anggota tertentu (Ahmad Rizky)
$qAhmad = "Berapa total pembayaran Ahmad Rizky?";
$ctxAhmad = $aggregator->getAggregatedContext('super_user', null, $qAhmad);
$resAhmad = $aiService->analyzeQuery($qAhmad, $ctxAhmad, 'super_user');
assertTest(
    "2.6 Rincian pembayaran anggota tertentu (Ahmad Rizky)",
    str_contains($resAhmad['answer'], '140.000') && str_contains($resAhmad['answer'], '3 transaksi'),
    "Jawaban: " . $resAhmad['answer']
);

// Test 2.7: Total pemasukan, pengeluaran, dan saldo kas
$qSaldo = "Berapa total pemasukan, pengeluaran, dan saldo kas?";
$resSaldo = $aiService->analyzeQuery($qSaldo, $ctxAdmin, 'super_user');
assertTest(
    "2.7 Total pemasukan, pengeluaran, dan saldo kas",
    str_contains($resSaldo['answer'], '1.680.000') && str_contains($resSaldo['answer'], 'Rp 0'),
    "Jawaban: " . $resSaldo['answer']
);

// Test 2.8: Total pembayaran pada periode tertentu (September 2026)
$qTotalSep = "Berapa total pembayaran pada bulan September 2026?";
$ctxTotalSep = $aggregator->getAggregatedContext('super_user', null, $qTotalSep);
$resTotalSep = $aiService->analyzeQuery($qTotalSep, $ctxTotalSep, 'super_user');
assertTest(
    "2.8 Total pembayaran pada periode September 2026",
    str_contains($resTotalSep['answer'], '130.000') && str_contains($resTotalSep['answer'], '7 transaksi'),
    "Jawaban: " . $resTotalSep['answer']
);

// Test 2.9: Total pembayaran pada periode November 2026
$qTotalNov = "Berapa total pembayaran kas di bulan November 2026?";
$ctxTotalNov = $aggregator->getAggregatedContext('super_user', null, $qTotalNov);
$resTotalNov = $aiService->analyzeQuery($qTotalNov, $ctxTotalNov, 'super_user');
assertTest(
    "2.9 Total pembayaran pada periode November 2026",
    str_contains($resTotalNov['answer'], '790.000') && str_contains($resTotalNov['answer'], '5 transaksi'),
    "Jawaban: " . $resTotalNov['answer']
);

// Test 2.10: Rincian transaksi berdasarkan status (Aktif vs Void)
$qVoid = "Bagaimana rincian transaksi void dan transaksi aktif?";
$resVoid = $aiService->analyzeQuery($qVoid, $ctxAdmin, 'super_user');
$totVoidAktual = (int)($ctxAdmin['ringkasan_transaksi']['total_transaksi_void'] ?? 0);
$nomVoidAktual = number_format((float)($ctxAdmin['ringkasan_transaksi']['total_nominal_void'] ?? 0), 0, ',', '.');
assertTest(
    "2.10 Rincian transaksi berdasarkan status (Aktif vs Void)",
    str_contains($resVoid['answer'], '21') && str_contains($resVoid['answer'], (string)$totVoidAktual) && str_contains($resVoid['answer'], $nomVoidAktual),
    "Jawaban: " . $resVoid['answer']
);

// Test 2.11: Rincian transaksi berdasarkan kategori
$qKategori = "Kategori transaksi apa saja yang ada di kas?";
$resKategori = $aiService->analyzeQuery($qKategori, $ctxAdmin, 'super_user');
assertTest(
    "2.11 Rincian kategori transaksi (kategori_transaksi)",
    str_contains($resKategori['answer'], 'Iuran Kas Rutin') && str_contains($resKategori['answer'], 'Dana Sosial'),
    "Jawaban: " . $resKategori['answer']
);

// Test 2.12: Rincian transaksi berdasarkan tanggal (19 September 2026)
$qTgl = "Bagaimana rincian transaksi pada tanggal 19 September 2026?";
$ctxTgl = $aggregator->getAggregatedContext('super_user', null, $qTgl);
$resTgl = $aiService->analyzeQuery($qTgl, $ctxTgl, 'super_user');
assertTest(
    "2.12 Rincian transaksi berdasarkan tanggal (19 September 2026)",
    str_contains($resTgl['answer'], '8 transaksi') && str_contains($resTgl['answer'], '230.000'),
    "Jawaban: " . $resTgl['answer']
);

// Test 2.13: Pertanyaan tabel users (Akun Pengguna)
$qUsers = "Berapa banyak akun pengguna yang terdaftar di sistem?";
$resUsers = $aiService->analyzeQuery($qUsers, $ctxAdmin, 'super_user');
$actualUserCount = (string)($ctxAdmin['ringkasan_sistem']['total_user'] ?? $countUsers);
assertTest(
    "2.13 Akun pengguna sistem (tabel users)",
    str_contains($resUsers['answer'], $actualUserCount) && str_contains($resUsers['answer'], 'Super User'),
    "Jawaban: " . $resUsers['answer']
);

// ─────────────────────────────────────────────────────────────────
// BAGIAN 3: HAK AKSES DAN ISOLASI KEAMANAN PERAN PENGGUNA
// ─────────────────────────────────────────────────────────────────
echo "\n── BAGIAN 3: Pengujian Keamanan & Hak Akses (Role Isolation) ──\n";

$ahmadId = 1; // Ahmad Rizky (Anggota)
$userInfoAnggota = [
    'id' => 2,
    'username' => 'ahmad.rizky',
    'role' => 'anggota',
    'nama' => 'Ahmad Rizky',
    'nomor_identitas' => '20261001',
    'kelas' => 'XII TKJ 2'
];

// Test 3.1: Anggota dilarang melihat daftar anggota yang menunggak
$ctxAnggota = $aggregator->getAggregatedContext('anggota', $ahmadId, 'Siapa saja anggota yang menunggak?');
$resAnggotaTunggak = $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $ctxAnggota, 'anggota', $userInfoAnggota);
assertTest(
    "3.1 Anggota dilarang melihat daftar penunggak umum (Privacy Guard)",
    str_contains($resAnggotaTunggak['answer'], 'hanya dapat mengakses data pembayaran kas milik Anda sendiri'),
    "Jawaban: " . $resAnggotaTunggak['answer']
);

// Test 3.2: Anggota dilarang melihat siapa yang sudah bayar
$resAnggotaBayar = $aiService->analyzeQuery('Siapa yang sudah membayar?', $ctxAnggota, 'anggota', $userInfoAnggota);
assertTest(
    "3.2 Anggota dilarang melihat rincian pembayaran anggota lain",
    str_contains($resAnggotaBayar['answer'], 'hanya dapat mengakses data pembayaran kas milik Anda sendiri'),
    "Jawaban: " . $resAnggotaBayar['answer']
);

// Test 3.3: Anggota dilarang melihat jumlah seluruh anggota
$resAnggotaCount = $aiService->analyzeQuery('Berapa jumlah seluruh anggota?', $ctxAnggota, 'anggota', $userInfoAnggota);
assertTest(
    "3.3 Anggota dilarang melihat total anggota sistem",
    str_contains($resAnggotaCount['answer'], 'tidak dapat diakses oleh akun Anggota'),
    "Jawaban: " . $resAnggotaCount['answer']
);

// Test 3.4: Anggota DAPAT melihat status pembayaran pribadinya sendiri
$resStatusSaya = $aiService->analyzeQuery('Bagaimana status pembayaran kas saya bulan ini?', $ctxAnggota, 'anggota', $userInfoAnggota);
assertTest(
    "3.4 Anggota dapat melihat status pembayaran kas miliknya sendiri",
    str_contains($resStatusSaya['answer'], 'Status pembayaran kas Anda bulan ini sudah lunas'),
    "Jawaban: " . $resStatusSaya['answer']
);

// Test 3.5: Anggota DAPAT melihat saldo kas organisasi (transparansi umum)
$resSaldoOrg = $aiService->analyzeQuery('Berapa saldo kas organisasi?', $ctxAnggota, 'anggota', $userInfoAnggota);
assertTest(
    "3.5 Anggota dapat melihat saldo kas organisasi umum",
    str_contains($resSaldoOrg['answer'], '1.680.000'),
    "Jawaban: " . $resSaldoOrg['answer']
);

// Test 3.6: Hak akses Bendahara melihat laporan keuangan penuh
$ctxBendahara = $aggregator->getAggregatedContext('bendahara', null, 'Berapa saldo kas dan siapa yang bayar?');
$resBendahara = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $ctxBendahara, 'bendahara');
assertTest(
    "3.6 Bendahara memiliki hak akses keuangan penuh",
    str_contains($resBendahara['answer'], '1.680.000'),
    "Jawaban: " . $resBendahara['answer']
);

// ─────────────────────────────────────────────────────────────────
// BAGIAN 4: PERILAKU JAWABAN (SINGKAT, JELAS, NATURAL, ANTI-HALUSINASI)
// ─────────────────────────────────────────────────────────────────
echo "\n── BAGIAN 4: Pengujian Perilaku Jawaban ──\n";

// Test 4.1: Identitas AI dijawab 1 kalimat natural
$resIdentitas = $aiService->analyzeQuery('Siapa Anda?', $ctxAdmin, 'super_user');
assertTest(
    "4.1 Identitas AI dijawab 1 kalimat informatif dan natural",
    str_contains($resIdentitas['answer'], 'asisten AI Sistem Kas') && !str_contains($resIdentitas['answer'], "\n"),
    "Jawaban: " . $resIdentitas['answer']
);

// Test 4.2: Pertanyaan data tidak tersedia dijawab singkat tanpa halusinasi
$resDataKosong = $aiService->analyzeQuery('Berapa anggaran biaya renovasi gedung?', $ctxAdmin, 'super_user');
assertTest(
    "4.2 Data tidak tersedia dijawab singkat tanpa mengarang",
    str_contains($resDataKosong['answer'], 'Mohon maaf, data tersebut belum tersedia dalam sistem kas'),
    "Jawaban: " . $resDataKosong['answer']
);

echo "\n=================================================================\n";
echo " REKAPITULASI PENGUJIAN: {$passed} BERHASIL | {$failed} GAGAL\n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
