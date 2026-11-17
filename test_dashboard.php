<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Models\PembayaranKas;
use App\Models\TransaksiKas;

Env::load(__DIR__ . '/.env');

echo "Running Dashboard Aggregation Tests...\n";
echo "----------------------------------------\n";

$testResults = [];

function assertTest($name, $condition) {
    global $testResults;
    if ($condition) {
        $testResults[] = "[PASS] $name";
    } else {
        $testResults[] = "[FAIL] $name";
    }
}

try {
    $transaksi = new TransaksiKas();
    $pembayaran = new PembayaranKas();

    // Test 1: Empty database logic (No error fetching aggregations)
    $masuk = $transaksi->getTotalByJenis('masuk');
    $keluar = $transaksi->getTotalByJenis('keluar');
    $chart = $transaksi->getMonthlyStats((int)date('Y'));
    
    assertTest("TransaksiKas Aggregation (Empty state safe)", is_numeric($masuk) && is_numeric($keluar) && is_array($chart));

    // Test 2: PembayaranKas logic
    $totalBayar = $pembayaran->getTotalPembayaranByAnggota(1);
    $status = $pembayaran->cekStatusBayarPeriode(1, 1, 9, 2026);
    $riwayat = $pembayaran->getRiwayatByAnggota(1);

    assertTest("PembayaranKas Aggregation (Empty state safe)", is_numeric($totalBayar) && is_bool($status) && is_array($riwayat));

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    $testResults[] = "[FAIL] Exception encountered during dashboard data fetch.";
}

echo implode("\n", $testResults) . "\n";
echo "----------------------------------------\n";
