<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Models\TransaksiKas;
use App\Models\KategoriTransaksi;

Env::load(__DIR__ . '/.env');

echo "Running Transaksi Kas Tests...\n";
echo "----------------------------------------\n";

$testResults = [];
$transaksiModel = new TransaksiKas();
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
    // Insert some test categories if they don't exist
    $kategoriMasuk = $kategoriModel->getByJenis('masuk');
    $kategoriKeluar = $kategoriModel->getByJenis('keluar');

    if (empty($kategoriMasuk) || empty($kategoriKeluar)) {
        throw new \Exception("Database must have default categories seeded for testing.");
    }

    $idKategoriMasuk = $kategoriMasuk[0]['id'];
    $idKategoriKeluar = $kategoriKeluar[0]['id'];

    // Test 1: Insert Pemasukan
    $t1_id = $transaksiModel->create([
        'kode_transaksi' => 'TEST-M-' . time(),
        'jenis' => 'masuk',
        'kategori_id' => $idKategoriMasuk,
        'jumlah' => 100000,
        'keterangan' => 'Test Pemasukan 1',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);
    assertTest("Create Pemasukan", $t1_id > 0);

    // Test 2: Insert Pengeluaran
    $t2_id = $transaksiModel->create([
        'kode_transaksi' => 'TEST-K-' . time(),
        'jenis' => 'keluar',
        'kategori_id' => $idKategoriKeluar,
        'jumlah' => 20000,
        'keterangan' => 'Test Pengeluaran 1',
        'tanggal_transaksi' => date('Y-m-d'),
        'created_by' => 1,
        'status' => 'aktif'
    ]);
    assertTest("Create Pengeluaran", $t2_id > 0);

    // Test 3: Saldo Calculation
    $saldo = $transaksiModel->calculateSaldo();
    assertTest("Calculate Saldo", $saldo == 80000); // 100k - 20k

    // Test 4: Update Transaksi
    $transaksiModel->update($t2_id, [
        'jumlah' => 30000
    ]);
    $saldoUpdated = $transaksiModel->calculateSaldo();
    assertTest("Update Pengeluaran (Saldo adjusts)", $saldoUpdated == 70000); // 100k - 30k

    // Test 5: Void Transaksi
    $transaksiModel->update($t1_id, [
        'status' => 'void'
    ]);
    $saldoVoid = $transaksiModel->calculateSaldo();
    assertTest("Void Transaksi (Saldo adjusts)", $saldoVoid == -30000); // Voided 100k, only 30k expense remains

    // Test 6: Filter and Pagination (Should not show voided in recent but test paginated directly)
    $filters = ['status' => 'aktif'];
    $paginated = $transaksiModel->getPaginated($filters, 1, 10);
    // Paginated should only contain 1 active since 1 is void
    assertTest("Paginated Filter by Status", count($paginated) == 1);

    // Clean up test data
    \App\Core\Database::getConnection()->exec("DELETE FROM transaksi_kas WHERE id IN ($t1_id, $t2_id)");

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    $testResults[] = "[FAIL] Exception encountered during Transaksi tests.";
}

echo implode("\n", $testResults) . "\n";
echo "----------------------------------------\n";
