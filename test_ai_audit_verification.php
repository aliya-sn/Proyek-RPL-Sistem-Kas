<?php
/**
 * test_ai_audit_verification.php
 * Verification test suite for AI Sistem Kas.
 * Directly compares AI answers with actual database records.
 */

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

use App\Core\Env;
use App\Core\Model;
use App\Services\FinancialDataAggregator;
use App\Services\AIService;

Env::load(__DIR__ . '/.env');

$db = Model::db();
$aiService = new AIService();
$aggregator = new FinancialDataAggregator();

$totalPassed = 0;
$totalFailed = 0;

function assertCheck(string $title, bool $condition, string $detail = '') {
    global $totalPassed, $totalFailed;
    if ($condition) {
        $totalPassed++;
        echo "  [PASS] {$title}\n";
    } else {
        $totalFailed++;
        echo "  [FAIL] {$title}\n";
        if ($detail) {
            echo "         Detail: {$detail}\n";
        }
    }
}

echo "=================================================================\n";
echo " TEST SUITE: AUDIT DAN VERIFIKASI AKHIR AI SISTEM KAS\n";
echo "=================================================================\n\n";

// ─── SECTION 1: Direct Database Ground-Truth Verification ───────
echo "── 1. Verifikasi Ground-Truth Database Langsung ──\n";

$rowAnggota = $db->query("SELECT COUNT(*) as total FROM anggota WHERE status = 'aktif'")->fetch();
$dbTotalAnggota = (int)$rowAnggota['total'];

$rowMasuk = $db->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi_kas WHERE jenis = 'masuk' AND status = 'aktif'")->fetch();
$dbTotalMasuk = (float)$rowMasuk['total'];

$rowKeluar = $db->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi_kas WHERE jenis = 'keluar' AND status = 'aktif'")->fetch();
$dbTotalKeluar = (float)$rowKeluar['total'];

$dbSaldo = $dbTotalMasuk - $dbTotalKeluar;

$stmtTop5 = $db->query("
    SELECT p.id, p.tanggal_bayar, p.nominal, a.nama as nama_anggota
    FROM pembayaran_kas p
    JOIN anggota a ON a.id = p.anggota_id
    LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
    WHERE p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif')
    ORDER BY p.tanggal_bayar DESC, p.id DESC
    LIMIT 5
");
$dbTop5 = $stmtTop5->fetchAll();

echo "  DB Total Anggota Aktif : {$dbTotalAnggota}\n";
echo "  DB Total Pemasukan     : Rp " . number_format($dbTotalMasuk, 0, ',', '.') . "\n";
echo "  DB Total Pengeluaran   : Rp " . number_format($dbTotalKeluar, 0, ',', '.') . "\n";
echo "  DB Saldo Kas           : Rp " . number_format($dbSaldo, 0, ',', '.') . "\n";
echo "  DB 5 Transaksi Terbaru:\n";
foreach ($dbTop5 as $i => $t) {
    echo "    " . ($i + 1) . ". {$t['nama_anggota']} - Rp " . number_format($t['nominal'], 0, ',', '.') . " ({$t['tanggal_bayar']})\n";
}
echo "\n";

// ─── SECTION 2: 5 Pertanyaan Wajib Melalui PHP AIService ────────
echo "── 2. Uji 5 Pertanyaan Wajib via PHP AIService ──\n";

// Q1: Siapa Anda?
$ctxAdmin = $aggregator->getAggregatedContext('super_user', null);
$res1 = $aiService->analyzeQuery('Siapa Anda?', $ctxAdmin, 'super_user');
$ans1 = $res1['answer'];
assertCheck(
    'Q1: "Siapa Anda?" mengembalikan identitas standar yang ramah & natural',
    str_contains($ans1, 'asisten AI Sistem Kas') && str_contains($ans1, 'informasi anggota, pembayaran, pemasukan, dan pengeluaran kas'),
    "Jawaban: {$ans1}"
);

// Q2: Ada berapa anggota yang terdaftar di Sistem Kas saya?
$res2 = $aiService->analyzeQuery('Ada berapa anggota yang terdaftar di Sistem Kas saya?', $ctxAdmin, 'super_user');
$ans2 = $res2['answer'];
assertCheck(
    'Q2: "Ada berapa anggota yang terdaftar di Sistem Kas saya?" cocok dengan DB (' . $dbTotalAnggota . ' orang)',
    str_contains($ans2, "{$dbTotalAnggota} orang") && str_contains($ans2, 'Total anggota terdaftar'),
    "Jawaban: {$ans2}"
);

// Q3: Berapa anggota yang sudah lunas dan berapa yang masih menunggak?
$res3 = $aiService->analyzeQuery('Berapa anggota yang sudah lunas dan berapa yang masih menunggak?', $ctxAdmin, 'super_user');
$ans3 = $res3['answer'];
assertCheck(
    'Q3: "Berapa anggota yang sudah lunas dan berapa yang masih menunggak?" menyatakan 8 lunas & 0 menunggak',
    str_contains($ans3, 'seluruh 8 anggota telah lunas') || (str_contains($ans3, '8') && str_contains($ans3, 'lunas') && str_contains($ans3, 'tidak ada yang menunggak')),
    "Jawaban: {$ans3}"
);

// Q4: Berapa total pemasukan, total pengeluaran, dan saldo kas saat ini berdasarkan database?
$res4 = $aiService->analyzeQuery('Berapa total pemasukan, total pengeluaran, dan saldo kas saat ini berdasarkan database?', $ctxAdmin, 'super_user');
$ans4 = $res4['answer'];
$fmtMasuk = number_format($dbTotalMasuk, 0, ',', '.');
$fmtKeluar = number_format($dbTotalKeluar, 0, ',', '.');
$fmtSaldo = number_format($dbSaldo, 0, ',', '.');
assertCheck(
    'Q4: Pemasukan (Rp ' . $fmtMasuk . '), Pengeluaran (Rp ' . $fmtKeluar . '), Saldo (Rp ' . $fmtSaldo . ') cocok presisi dengan DB',
    str_contains($ans4, $fmtMasuk) && str_contains($ans4, $fmtKeluar) && str_contains($ans4, $fmtSaldo),
    "Jawaban: {$ans4}"
);

// Q5: Tampilkan lima transaksi pembayaran terbaru beserta tanggal, nominal, dan nama anggotanya.
$res5 = $aiService->analyzeQuery('Tampilkan lima transaksi pembayaran terbaru beserta tanggal, nominal, dan nama anggotanya.', $ctxAdmin, 'super_user');
$ans5 = $res5['answer'];
$all5Match = true;
foreach ($dbTop5 as $top) {
    if (!str_contains($ans5, $top['nama_anggota'])) {
        $all5Match = false;
        break;
    }
}
assertCheck(
    'Q5: Lima transaksi pembayaran terbaru memuat 5 nama anggota teratas dari database',
    $all5Match && (str_contains($ans5, 'Lima transaksi') || str_contains($ans5, 'terbaru')),
    "Jawaban:\n{$ans5}"
);

echo "\n";

// ─── SECTION 3: 5 Pertanyaan Wajib Langsung ke Node.js Endpoint ──
echo "── 3. Uji 5 Pertanyaan Wajib Langsung ke Node.js (POST http://127.0.0.1:5000/api/chat) ──\n";

function callNodeDirect(string $question, array $context, string $role = 'super_user'): ?array {
    $secret = Env::get('AI_SERVICE_SECRET', 'sistem_kas_ai_node_secret_key_2026_auth');
    $payload = json_encode([
        'question' => $question,
        'role' => $role,
        'user_context' => ['username' => 'admin', 'role' => $role],
        'financial_context' => $context
    ]);

    $ch = curl_init('http://127.0.0.1:5000/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Service-Secret: ' . $secret
        ],
        CURLOPT_TIMEOUT => 5
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        return json_decode($res, true);
    }
    return null;
}

$nodeQ1 = callNodeDirect('Siapa Anda?', $ctxAdmin);
assertCheck(
    'Node.js Q1: Identitas AI valid',
    !empty($nodeQ1['success']) && str_contains($nodeQ1['answer'], 'asisten AI Sistem Kas'),
    "Jawaban: " . ($nodeQ1['answer'] ?? 'NULL')
);

$nodeQ2 = callNodeDirect('Ada berapa anggota yang terdaftar di Sistem Kas saya?', $ctxAdmin);
assertCheck(
    'Node.js Q2: Jumlah anggota sesuai DB (8 orang)',
    !empty($nodeQ2['success']) && str_contains($nodeQ2['answer'], '8 orang'),
    "Jawaban: " . ($nodeQ2['answer'] ?? 'NULL')
);

$nodeQ3 = callNodeDirect('Berapa anggota yang sudah lunas dan berapa yang masih menunggak?', $ctxAdmin);
assertCheck(
    'Node.js Q3: Status lunas/tunggakan periode Oktober 2026 sesuai DB',
    !empty($nodeQ3['success']) && (str_contains($nodeQ3['answer'], 'seluruh 8 anggota telah lunas') || (str_contains($nodeQ3['answer'], '8') && str_contains($nodeQ3['answer'], 'tidak ada yang menunggak'))),
    "Jawaban: " . ($nodeQ3['answer'] ?? 'NULL')
);

$nodeQ4 = callNodeDirect('Berapa total pemasukan, total pengeluaran, dan saldo kas saat ini berdasarkan database?', $ctxAdmin);
assertCheck(
    'Node.js Q4: Pemasukan, pengeluaran, dan saldo kas sesuai DB',
    !empty($nodeQ4['success']) && str_contains($nodeQ4['answer'], '1.680.000') && str_contains($nodeQ4['answer'], 'Rp 0'),
    "Jawaban: " . ($nodeQ4['answer'] ?? 'NULL')
);

$nodeQ5 = callNodeDirect('Tampilkan lima transaksi pembayaran terbaru beserta tanggal, nominal, dan nama anggotanya.', $ctxAdmin);
assertCheck(
    'Node.js Q5: Lima transaksi pembayaran terbaru valid',
    !empty($nodeQ5['success']) && str_contains($nodeQ5['answer'], 'Lima transaksi pembayaran terbaru') && str_contains($nodeQ5['answer'], 'Ahmad Rizky'),
    "Jawaban:\n" . ($nodeQ5['answer'] ?? 'NULL')
);

echo "\n";

// ─── SECTION 4: Keamanan Hak Akses (Role Access Control) ─────────
echo "── 4. Pengujian Hak Akses dan Isolasi Data Antar Peran ──\n";

$ctxAnggota = $aggregator->getAggregatedContext('anggota', 1);
$userAnggota = ['id' => 2, 'username' => 'ahmad.rizky', 'role' => 'anggota', 'nama' => 'Ahmad Rizky'];

// Anggota bertanya anggota yang menunggak -> Harus ditolak
$sec1 = $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $ctxAnggota, 'anggota', $userAnggota);
assertCheck(
    'Security: Anggota dilarang mengakses daftar tunggakan anggota lain',
    str_contains($sec1['answer'], 'hanya dapat mengakses data pembayaran kas milik Anda sendiri'),
    "Jawaban: {$sec1['answer']}"
);

// Anggota bertanya jumlah seluruh anggota -> Harus ditolak
$sec2 = $aiService->analyzeQuery('Berapa jumlah seluruh anggota?', $ctxAnggota, 'anggota', $userAnggota);
assertCheck(
    'Security: Anggota dilarang mengakses total anggota sistem',
    str_contains($sec2['answer'], 'tidak dapat diakses oleh akun Anggota'),
    "Jawaban: {$sec2['answer']}"
);

// Anggota bertanya saldo kas organisasi -> Boleh (transparansi umum)
$sec3 = $aiService->analyzeQuery('Berapa saldo kas organisasi?', $ctxAnggota, 'anggota', $userAnggota);
assertCheck(
    'Security: Anggota dapat melihat saldo kas umum organisasi (transparansi)',
    str_contains($sec3['answer'], '1.680.000'),
    "Jawaban: {$sec3['answer']}"
);

// Anggota bertanya status pribadinya -> Boleh
$sec4 = $aiService->analyzeQuery('Bagaimana status pembayaran kas saya bulan ini?', $ctxAnggota, 'anggota', $userAnggota);
assertCheck(
    'Security: Anggota dapat melihat status pembayaran pribadinya sendiri',
    str_contains($sec4['answer'], 'sudah lunas'),
    "Jawaban: {$sec4['answer']}"
);

// Bendahara bertanya saldo kas dan ringkasan -> Boleh (akses penuh)
$ctxBendahara = $aggregator->getAggregatedContext('bendahara', null);
$sec5 = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $ctxBendahara, 'bendahara');
assertCheck(
    'Security: Bendahara memiliki akses keuangan penuh',
    str_contains($sec5['answer'], '1.680.000') && str_contains($sec5['answer'], 'pemasukan'),
    "Jawaban: {$sec5['answer']}"
);

echo "\n";

// ─── SECTION 5: Pembedaan Query Gagal vs Nol Sah ─────────────────
echo "── 5. Pembedaan Kondisi Data Tidak Tersedia vs Hasil Bernilai Nol ──\n";

// Query dengan context kosong (data tidak tersedia / query gagal)
$emptyCtx = [];
$fail1 = $aiService->analyzeQuery('Ada berapa anggota?', $emptyCtx, 'super_user');
assertCheck(
    'Handling: Query anggota tanpa konteks data tidak mengembalikan "0 orang" atau klaim palsu',
    str_contains($fail1['answer'], 'belum tersedia'),
    "Jawaban: {$fail1['answer']}"
);

$fail2 = $aiService->analyzeQuery('Berapa anggota yang lunas dan menunggak?', $emptyCtx, 'super_user');
assertCheck(
    'Handling: Query status pembayaran tanpa konteks data tidak mengklaim "tidak ada tunggakan"',
    str_contains($fail2['answer'], 'belum tersedia'),
    "Jawaban: {$fail2['answer']}"
);

$fail3 = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $emptyCtx, 'super_user');
assertCheck(
    'Handling: Query saldo tanpa konteks data tidak mengklaim "saldo Rp 0"',
    str_contains($fail3['answer'], 'belum tersedia'),
    "Jawaban: {$fail3['answer']}"
);

// Pertanyaan yang datanya memang tidak ada dalam sistem kas
$fail4 = $aiService->analyzeQuery('Berapa anggaran biaya renovasi gedung?', $ctxAdmin, 'super_user');
assertCheck(
    'Handling: Pertanyaan topik di luar sistem kas dijawab jujur tidak tersedia tanpa halusinasi',
    str_contains($fail4['answer'], 'belum tersedia dalam sistem kas'),
    "Jawaban: {$fail4['answer']}"
);

echo "\n=================================================================\n";
echo " REKAPITULASI AUDIT VERIFIKASI: {$totalPassed} PASSED | {$totalFailed} FAILED\n";
echo "=================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
