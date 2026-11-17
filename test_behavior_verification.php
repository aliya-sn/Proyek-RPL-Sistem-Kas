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

use App\Services\FinancialDataAggregator;
use App\Services\AIService;

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

$aiService = new AIService();
$aggregator = new FinancialDataAggregator();

$adminCtx = $aggregator->getAggregatedContext('super_user', null);
$anggotaCtx = $aggregator->getAggregatedContext('anggota', 1);

$tests = [
    '1. Siapa Anda?' => [
        'res' => $aiService->analyzeQuery('Siapa Anda?', $adminCtx, 'super_user'),
        'expected' => 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.'
    ],
    '2. Kamu siapa?' => [
        'res' => $aiService->analyzeQuery('Kamu siapa?', $adminCtx, 'super_user'),
        'expected' => 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.'
    ],
    '2b. Apa kamu?' => [
        'res' => $aiService->analyzeQuery('Apa kamu?', $adminCtx, 'super_user'),
        'expected' => 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.'
    ],
    '2c. Perkenalkan dirimu.' => [
        'res' => $aiService->analyzeQuery('Perkenalkan dirimu.', $adminCtx, 'super_user'),
        'expected' => 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.'
    ],
    '2d. Apa tugas Anda?' => [
        'res' => $aiService->analyzeQuery('Apa tugas Anda?', $adminCtx, 'super_user'),
        'expected' => 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.'
    ],
    '2e. Apa tugasmu?' => [
        'res' => $aiService->analyzeQuery('Apa tugasmu?', $adminCtx, 'super_user'),
        'expected' => 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.'
    ],
    '3. Ada berapa anggota?' => [
        'res' => $aiService->analyzeQuery('Ada berapa anggota?', $adminCtx, 'super_user'),
        'expected' => 'Total anggota terdaftar adalah 8 orang.'
    ],
    '4a. Siapa saja anggota yang menunggak? (Admin / Bendahara)' => [
        'res' => $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $adminCtx, 'super_user'),
        'expected' => 'Tidak ada anggota yang menunggak untuk periode ini.'
    ],
    '4b. Siapa saja anggota yang menunggak? (Anggota - Privacy Protected)' => [
        'res' => $aiService->analyzeQuery('Siapa saja anggota yang menunggak?', $anggotaCtx, 'anggota'),
        'expected' => 'Maaf, Anda hanya dapat mengakses data pembayaran kas milik Anda sendiri.'
    ],
    '4c. Status pembayaran kas saya (Anggota)' => [
        'res' => $aiService->analyzeQuery('Bagaimana status pembayaran kas saya bulan ini?', $anggotaCtx, 'anggota'),
        'expected' => 'Status pembayaran kas Anda bulan ini sudah lunas.'
    ],
    '5. Pertanyaan yang datanya tidak tersedia' => [
        'res' => $aiService->analyzeQuery('Berapa luas lapangan sepak bola sekolah?', $adminCtx, 'super_user'),
        'expected' => 'Mohon maaf, data tersebut belum tersedia dalam sistem kas.'
    ],
];

echo "=================================================================\n";
echo " HASIL PENGUJIAN PERILAKU AI SISTEM KAS (JAWABAN SINGKAT & FOKUS)\n";
echo "=================================================================\n\n";

$allPassed = true;
foreach ($tests as $title => $item) {
    $actual = $item['res']['answer'];
    $provider = $item['res']['provider'];
    $match = ($actual === $item['expected']);
    if (!$match) $allPassed = false;
    $status = $match ? "[PASS]" : "[FAIL]";

    echo "{$status} {$title}\n";
    echo "  Jawaban : {$actual}\n";
    echo "  Provider: {$provider}\n\n";
}

echo "=================================================================\n";
echo $allPassed ? " STATUS: SEMUA PENGUJIAN BERHASIL TEPAT & SESUAI ATURAN\n" : " STATUS: ADA PENGUJIAN YANG GAGAL\n";
echo "=================================================================\n";

if (!$allPassed) {
    exit(1);
}
