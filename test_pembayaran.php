<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Models\PembayaranKas;
use App\Models\TransaksiKas;
use App\Models\Anggota;
use App\Models\KategoriTransaksi;

Env::load(__DIR__ . '/.env');

echo "Running Pembayaran Kas Tests...\n";
echo "----------------------------------------\n";

$testResults = [];
$pembayaranModel = new PembayaranKas();
$transaksiModel = new TransaksiKas();
$anggotaModel = new Anggota();
$kategoriModel = new KategoriTransaksi();

function assertTest($name, $condition) {
    global $testResults;
    if ($condition) {
        $testResults[] = "[PASS] $name";
    } else {
        $testResults[] = "[FAIL] $name";
    }
}

try {
    $db = \App\Core\Database::getConnection();

    // 1. Setup Test Data
    $anggotaList = $anggotaModel->getActive();
    if (empty($anggotaList)) {
        throw new \Exception("Database must have at least one active anggota for testing.");
    }
    $testAnggotaId = $anggotaList[0]['id'];

    $kategoriMasuk = $kategoriModel->getByJenis('masuk');
    if (empty($kategoriMasuk)) {
        throw new \Exception("Database must have at least one kategori masuk.");
    }
    $kategoriId = $kategoriMasuk[0]['id'];
    
    $testBulan = rand(1, 12);
    $testTahun = 2099; // Future year to avoid collision
    $testNominal = 25000;

    // 2. Test Pembayaran Creation & Atomicity (simulated controller behavior)
    $db->beginTransaction();
    
    // Create Transaksi First
    $transaksiId = $transaksiModel->create([
        'kode_transaksi' => 'TEST-PBK-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $kategoriId,
        'jumlah' => $testNominal,
        'keterangan' => 'Test Pembayaran Kas',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);

    // Create Pembayaran
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
    assertTest("Create Pembayaran & Transaksi Atomicity", $pembayaranId > 0 && $transaksiId > 0);

    // 3. Test Double Payment (Unique Constraint)
    $doublePaymentException = false;
    try {
        $pembayaranModel->create([
            'anggota_id' => $testAnggotaId,
            'transaksi_id' => null,
            'periode_minggu' => 1,
            'periode_bulan' => $testBulan,
            'periode_tahun' => $testTahun, // Same period
            'nominal' => $testNominal,
            'tanggal_bayar' => date('Y-m-d'),
            'status' => 'lunas',
            'petugas_id' => 1
        ]);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation
            $doublePaymentException = true;
        }
    }
    assertTest("Duplicate Payment Rejected (Unique Constraint)", $doublePaymentException);

    // 4. Test Rekap & Cek Status
    $isPaid = $pembayaranModel->cekStatusBayarPeriode($testAnggotaId, 1, $testBulan, $testTahun);
    assertTest("Cek Status Bayar Periode (True)", $isPaid);

    $isNotPaid = $pembayaranModel->cekStatusBayarPeriode($testAnggotaId, 1, ($testBulan % 12) + 1, $testTahun);
    assertTest("Cek Status Bayar Periode (False)", !$isNotPaid);

    $totalBayar = $pembayaranModel->getTotalPembayaranByAnggota($testAnggotaId);
    assertTest("Total Pembayaran by Anggota", $totalBayar >= $testNominal);

    // 5. Cleanup
    $db->exec("DELETE FROM pembayaran_kas WHERE id = $pembayaranId");
    $db->exec("DELETE FROM transaksi_kas WHERE id = $transaksiId");

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    $testResults[] = "[FAIL] Exception encountered during Pembayaran Kas tests.";
}

echo implode("\n", $testResults) . "\n";
echo "----------------------------------------\n";
