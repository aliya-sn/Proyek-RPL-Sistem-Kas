<?php
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';
\App\Core\Env::load(__DIR__ . '/.env');

use App\Core\Database;
use App\Models\LaporanKeuangan;
use App\Models\PembayaranKas;
use App\Models\TransaksiKas;
use App\Models\Anggota;

$db = Database::getConnection();
$laporanModel = new LaporanKeuangan();
$pembayaranModel = new PembayaranKas();
$transaksiModel = new TransaksiKas();
$anggotaModel = new Anggota();

$passed = 0;
$failed = 0;

function assertScenario(string $scenarioName, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$scenarioName}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] {$scenarioName} — " . ($details ? $details : "Assertion failed") . "\n";
    }
}

echo "=================================================================\n";
echo " PENGUJIAN SKENARIO PERBAIKAN BUG TUNGGAKAN & PEMBAYARAN ANGGOTA\n";
echo "=================================================================\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 1: Anggota belum membayar: tetap menunggak jika sudah jatuh tempo
// ─────────────────────────────────────────────────────────────────────────────
echo "── SKENARIO 1: Anggota belum bayar tetap menunggak jika jatuh tempo ──\n";
$currBulan = 10;
$currTahun = 2026;
$tunggakanBulanIni = $laporanModel->getLaporanTunggakan(['periode_bulan' => $currBulan, 'periode_tahun' => $currTahun]);

// Zahia Awa (ID 7) has not paid for October 2026
$zahiaRow = null;
foreach ($tunggakanBulanIni as $r) {
    if ((int)$r['anggota_id'] === 7) {
        $zahiaRow = $r;
        break;
    }
}

assertScenario(
    "1a. Anggota yang belum bayar memiliki status 'tunggak'",
    $zahiaRow !== null && $zahiaRow['status_bulan'] === 'tunggak',
    "Zahia Awa: status_bulan = '{$zahiaRow['status_bulan']}'"
);

assertScenario(
    "1b. Minggu belum bayar = 4 dan estimasi tunggakan > 0",
    $zahiaRow !== null && $zahiaRow['minggu_terbayar'] === 0 && $zahiaRow['minggu_belum_bayar'] === 4 && $zahiaRow['estimasi_tunggakan'] > 0,
    "Minggu: {$zahiaRow['minggu_terbayar']}/{$zahiaRow['minggu_belum_bayar']}, Estimasi: Rp " . number_format($zahiaRow['estimasi_tunggakan'], 0, ',', '.')
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 2: Anggota sudah membayar penuh: status lunas dan sisa tunggakan sesuai
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 2: Anggota bayar penuh berstatus lunas & sisa tunggakan Rp 0 ──\n";
// Ahmad Rizky (ID 1) has paid full for October 2026
$ahmadRow = null;
foreach ($tunggakanBulanIni as $r) {
    if ((int)$r['anggota_id'] === 1) {
        $ahmadRow = $r;
        break;
    }
}

assertScenario(
    "2a. Anggota yang sudah bayar penuh berstatus 'lunas'",
    $ahmadRow !== null && $ahmadRow['status_bulan'] === 'lunas',
    "Ahmad Rizky: status_bulan = '{$ahmadRow['status_bulan']}'"
);

assertScenario(
    "2b. Sisa tunggakan adalah Rp 0 dan minggu terbayar = 4",
    $ahmadRow !== null && $ahmadRow['minggu_terbayar'] === 4 && $ahmadRow['minggu_belum_bayar'] === 0 && (float)$ahmadRow['estimasi_tunggakan'] === 0.0,
    "Minggu: {$ahmadRow['minggu_terbayar']}/{$ahmadRow['minggu_belum_bayar']}, Estimasi: Rp {$ahmadRow['estimasi_tunggakan']}"
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 3: Anggota membayar sebagian: sisa tagihan dihitung dengan benar
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 3: Anggota bayar sebagian (parsial / belum lunas) ──\n";
// Create simulated partial payment for test month (e.g. Month 4, Year 2026) for Anggota 7
$db->beginTransaction();
$txPartialId = $transaksiModel->create([
    'kode_transaksi' => 'PBK-PARTIAL-TEST-' . time(),
    'jenis' => 'masuk',
    'kategori_id' => 1,
    'jumlah' => 20000,
    'keterangan' => 'Pembayaran Kas Sebagian (Test)',
    'tanggal_transaksi' => '2026-04-15',
    'created_by' => 1,
    'status' => 'aktif'
]);

$pmtPartialId = $pembayaranModel->create([
    'anggota_id' => 7,
    'transaksi_id' => $txPartialId,
    'periode_minggu' => 1,
    'periode_bulan' => 4,
    'periode_tahun' => 2026,
    'nominal' => 20000,
    'status' => 'belum_lunas', // Status belum lunas / sebagian
    'tanggal_bayar' => '2026-04-15',
    'petugas_id' => 1
]);
$db->commit();

$tunggakanPartial = $laporanModel->getLaporanTunggakan(['periode_bulan' => 4, 'periode_tahun' => 2026, 'anggota_id' => 7]);
$partialRow = $tunggakanPartial[0] ?? null;

// Clean up test data
$db->exec("DELETE FROM pembayaran_kas WHERE id = {$pmtPartialId}");
$db->exec("DELETE FROM transaksi_kas WHERE id = {$txPartialId}");

assertScenario(
    "3a. Pembayaran dengan status 'belum_lunas' tetap berstatus 'tunggak'",
    $partialRow !== null && $partialRow['status_bulan'] === 'tunggak',
    "Status: '{$partialRow['status_bulan']}'"
);

// Standard nominal for Zahia is 60.000 (from her previous payments), paid 20.000 -> sisa tunggakan should be 40.000
assertScenario(
    "3b. Sisa tagihan berkurang sesuai nominal yang telah dibayarkan sebagian",
    $partialRow !== null && $partialRow['estimasi_tunggakan'] < 60000 && $partialRow['estimasi_tunggakan'] > 0,
    "Sisa tunggakan berkurang menjadi Rp " . number_format($partialRow['estimasi_tunggakan'], 0, ',', '.')
);

assertScenario(
    "3c. Total minggu (terbayar + belum) tetap berjumlah 4",
    $partialRow !== null && ($partialRow['minggu_terbayar'] + $partialRow['minggu_belum_bayar'] === 4),
    "Terbayar: {$partialRow['minggu_terbayar']}, Belum: {$partialRow['minggu_belum_bayar']}"
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 4: Anggota belum memiliki tagihan jatuh tempo: jangan otomatis menunggak
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 4: Tagihan belum jatuh tempo (periode masa depan) ──\n";
// Future month: December 2029 (far in the future, nobody has paid yet)
$tunggakanMasaDepan = $laporanModel->getLaporanTunggakan(['periode_bulan' => 12, 'periode_tahun' => 2029]);
$allLunasMasaDepan = true;
$totalTunggakanMasaDepan = $laporanModel->getTotalTunggakan($tunggakanMasaDepan);

foreach ($tunggakanMasaDepan as $r) {
    if ($r['status_bulan'] === 'tunggak' || $r['estimasi_tunggakan'] > 0) {
        $allLunasMasaDepan = false;
        break;
    }
}

assertScenario(
    "4a. Anggota yang belum jatuh tempo tidak otomatis dianggap menunggak",
    $allLunasMasaDepan,
    "Seluruh anggota tidak memiliki tunggakan untuk periode masa depan"
);

assertScenario(
    "4b. Total estimasi tunggakan untuk periode masa depan adalah Rp 0",
    $totalTunggakanMasaDepan == 0.0,
    "Total estimasi tunggakan = Rp " . number_format($totalTunggakanMasaDepan, 0, ',', '.')
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 5: Pemasukan kas umum (bukan iuran): tidak boleh otomatis melunasi iuran
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 5: Pemasukan kas umum (Donasi/Sumbangan) tidak melunasi iuran ──\n";
$db->beginTransaction();
$txDonasiId = $transaksiModel->create([
    'kode_transaksi' => 'DONASI-TEST-' . time(),
    'jenis' => 'masuk',
    'kategori_id' => 2, // Sumbangan / Donasi
    'jumlah' => 10000000, // Rp 10 Juta donasi
    'keterangan' => 'Donasi Kas Umum dari Alumni',
    'tanggal_transaksi' => '2026-10-10',
    'created_by' => 1,
    'status' => 'aktif'
]);
$db->commit();

// Check Zahia Awa status again
$tunggakanAfterDonasi = $laporanModel->getLaporanTunggakan(['periode_bulan' => 10, 'periode_tahun' => 2026, 'anggota_id' => 7]);
$zahiaAfterDonasi = $tunggakanAfterDonasi[0] ?? null;

// Clean up donation
$db->exec("DELETE FROM transaksi_kas WHERE id = {$txDonasiId}");

assertScenario(
    "5a. Pemasukan donasi umum Rp 10.000.000 TIDAK otomatis melunasi iuran anggota menunggak",
    $zahiaAfterDonasi !== null && $zahiaAfterDonasi['status_bulan'] === 'tunggak',
    "Zahia Awa tetap berstatus 'tunggak'"
);

assertScenario(
    "5b. Estimasi tunggakan anggota tidak terpengaruh oleh pemasukan umum",
    $zahiaAfterDonasi !== null && $zahiaAfterDonasi['estimasi_tunggakan'] === $zahiaRow['estimasi_tunggakan'],
    "Estimasi tunggakan tetap Rp " . number_format($zahiaAfterDonasi['estimasi_tunggakan'], 0, ',', '.')
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 6: Pembayaran untuk satu anggota: tidak boleh mengubah status anggota lain
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 6: Isolasi Pembayaran antar Anggota ──\n";
// Ahmad Rizky paid Rp 100.000 in October 2026
// Zahia Awa has NOT paid in October 2026
assertScenario(
    "6a. Pembayaran Ahmad Rizky tidak membuat Zahia Awa menjadi lunas",
    $ahmadRow['status_bulan'] === 'lunas' && $zahiaRow['status_bulan'] === 'tunggak',
    "Ahmad: '{$ahmadRow['status_bulan']}' vs Zahia: '{$zahiaRow['status_bulan']}'"
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 7: Transaksi yang di-void (dibatalkan) tidak dihitung sebagai pembayaran sah
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 7: Pembayaran dengan transaksi VOID tidak sah ──\n";
$db->beginTransaction();
$txVoidId = $transaksiModel->create([
    'kode_transaksi' => 'PBK-VOID-TEST-' . time(),
    'jenis' => 'masuk',
    'kategori_id' => 1,
    'jumlah' => 60000,
    'keterangan' => 'Pembayaran Kas Void Test',
    'tanggal_transaksi' => '2026-10-10',
    'created_by' => 1,
    'status' => 'void' // VOID!
]);

$pmtVoidId = $pembayaranModel->create([
    'anggota_id' => 7,
    'transaksi_id' => $txVoidId,
    'periode_minggu' => 1,
    'periode_bulan' => 10,
    'periode_tahun' => 2026,
    'nominal' => 60000,
    'status' => 'lunas',
    'tanggal_bayar' => '2026-10-10',
    'petugas_id' => 1
]);
$db->commit();

$tunggakanWithVoid = $laporanModel->getLaporanTunggakan(['periode_bulan' => 10, 'periode_tahun' => 2026, 'anggota_id' => 7]);
$zahiaWithVoid = $tunggakanWithVoid[0] ?? null;

$isPaidVoid = $pembayaranModel->cekStatusBayarBulanIni(7, 10, 2026);

// Clean up
$db->exec("DELETE FROM pembayaran_kas WHERE id = {$pmtVoidId}");
$db->exec("DELETE FROM transaksi_kas WHERE id = {$txVoidId}");

assertScenario(
    "7a. Pembayaran yang transaksinya di-void diabaikan dalam laporan tunggakan",
    $zahiaWithVoid !== null && $zahiaWithVoid['status_bulan'] === 'tunggak',
    "Zahia Awa tetap 'tunggak' karena transaksinya void"
);

assertScenario(
    "7b. cekStatusBayarBulanIni mengembalikan false untuk pembayaran void",
    !$isPaidVoid,
    "Status bayar bulan ini = false (Belum Lunas)"
);


// ─────────────────────────────────────────────────────────────────────────────
// SKENARIO 8: Konsistensi Dashboard Bendahara, Dashboard Anggota, dan Laporan
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── SKENARIO 8: Konsistensi Perhitungan Antar Halaman & Role ──\n";
$totalAktif = $anggotaModel->count(['status' => 'aktif']);
$sudahBayarBendahara = $pembayaranModel->getCountSudahBayarPeriodeIni(10, 2026);
$belumBayarBendahara = max(0, $totalAktif - $sudahBayarBendahara);

$lunasReportCount = count(array_filter($tunggakanBulanIni, fn($r) => $r['status_bulan'] === 'lunas'));
$tunggakReportCount = count(array_filter($tunggakanBulanIni, fn($r) => $r['status_bulan'] === 'tunggak'));

$ahmadDashboardStatus = $pembayaranModel->cekStatusBayarBulanIni(1, 10, 2026);
$zahiaDashboardStatus = $pembayaranModel->cekStatusBayarBulanIni(7, 10, 2026);

assertScenario(
    "8a. Dashboard Bendahara mencatat 7 anggota sudah bayar, 1 anggota belum bayar",
    $sudahBayarBendahara === 7 && $belumBayarBendahara === 1,
    "Sudah bayar: {$sudahBayarBendahara}, Belum bayar: {$belumBayarBendahara}"
);

assertScenario(
    "8b. Laporan Tunggakan konsisten dengan Dashboard Bendahara (7 lunas, 1 tunggak)",
    $lunasReportCount === 7 && $tunggakReportCount === 1,
    "Lunas: {$lunasReportCount}, Tunggak: {$tunggakReportCount}"
);

assertScenario(
    "8c. Dashboard Anggota Ahmad Rizky menampilkan status LUNAS (true)",
    $ahmadDashboardStatus === true,
    "Status: Lunas"
);

assertScenario(
    "8d. Dashboard Anggota Zahia Awa menampilkan status BELUM LUNAS (false)",
    $zahiaDashboardStatus === false,
    "Status: Belum Lunas"
);

echo "\n=================================================================\n";
echo " HASIL VALIDASI SKENARIO: {$passed} BERHASIL  |  {$failed} GAGAL\n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
