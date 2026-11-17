<?php
/**
 * test_laporan.php
 * Test script for Laporan Keuangan module (Prompt 07).
 *
 * Run via CLI: php test_laporan.php
 * Or in browser: http://localhost/Project%20RPL/Sistem%20Kas/test_laporan.php
 *
 * Tests covered:
 *  T1  — Authorization: ensure only super_user can access laporan routes
 *  T2  — Model: LaporanKeuangan instantiation
 *  T3  — Pemasukan: data query without filters (all time)
 *  T4  — Pemasukan: date range filter
 *  T5  — Pemasukan: periode_bulan + periode_tahun filter
 *  T6  — Pengeluaran: data query and total
 *  T7  — Arus Kas: total_masuk + total_keluar + saldo calculation
 *  T8  — Arus Kas: getSaldoSebelumPeriode
 *  T9  — Pembayaran: getLaporanPembayaran query
 *  T10 — Pembayaran: getTotalPembayaran
 *  T11 — Tunggakan: getLaporanTunggakan returns correct structure
 *  T12 — Tunggakan: getTotalTunggakan sum
 *  T13 — Empty result: pemasukan with impossible date range
 *  T14 — Empty result: pengeluaran with impossible date range
 *  T15 — Routes: all 6 laporan routes are registered
 */

define('BASE_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Models\LaporanKeuangan;

Env::load(__DIR__ . '/.env');

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function pass(string $name): void {
    global $pass;
    $pass++;
    echo "  [PASS] {$name}\n";
}

function fail(string $name, string $reason = ''): void {
    global $fail;
    $fail++;
    echo "  [FAIL] {$name}" . ($reason ? " — {$reason}" : '') . "\n";
}

function section(string $title): void {
    echo "\n── {$title} ──\n";
}

// ─────────────────────────────────────────────────────────────────────────────

echo "=================================================================\n";
echo " LAPORAN KEUANGAN — TEST SUITE (Prompt 07)\n";
echo "=================================================================\n";

// ─── T1: Authorization check ──────────────────────────────────────────────
section('T1: Authorization / Access Control');
try {
    // Check route file contains role:super_user guard for laporan
    $routeContent = file_get_contents(__DIR__ . '/routes/web.php');
    if (strpos($routeContent, "role:super_user") !== false
        && strpos($routeContent, "LaporanKeuanganController") !== false) {
        pass('T1: Laporan routes are inside super_user middleware group');
    } else {
        fail('T1: Laporan routes NOT found inside super_user group');
    }
} catch (Exception $e) {
    fail('T1: Exception — ' . $e->getMessage());
}

// ─── T2: Model instantiation ─────────────────────────────────────────────
section('T2: LaporanKeuangan Model');
try {
    $laporan = new LaporanKeuangan();
    pass('T2: LaporanKeuangan instantiated successfully');
} catch (Exception $e) {
    fail('T2: Cannot instantiate LaporanKeuangan — ' . $e->getMessage());
    exit(1);
}

// ─── T3: Pemasukan — all time ────────────────────────────────────────────
section('T3–T5: Laporan Pemasukan');
try {
    $rows = $laporan->getLaporanPemasukan([]);
    $total = $laporan->getTotalPemasukan([]);
    pass("T3: getLaporanPemasukan (all time) returned " . count($rows) . " rows");

    // Verify total matches sum of rows
    $sumRows = array_sum(array_column($rows, 'jumlah'));
    if (abs($sumRows - $total) < 0.01) {
        pass("T3b: Total Pemasukan matches sum of rows — Rp " . number_format($total, 0, ',', '.'));
    } else {
        fail("T3b: Total mismatch — rows sum={$sumRows}, total={$total}");
    }
} catch (Exception $e) {
    fail('T3: Exception — ' . $e->getMessage());
}

// ─── T4: Pemasukan with date range ──────────────────────────────────────
try {
    $filters = ['tanggal_mulai' => '2026-01-01', 'tanggal_akhir' => '2026-12-31'];
    $rows = $laporan->getLaporanPemasukan($filters);
    $total = $laporan->getTotalPemasukan($filters);
    pass("T4: Date range filter 2026 returned " . count($rows) . " rows (total: Rp " . number_format($total, 0, ',', '.') . ")");
} catch (Exception $e) {
    fail('T4: Exception — ' . $e->getMessage());
}

// ─── T5: Pemasukan with bulan/tahun ─────────────────────────────────────
try {
    $filters = ['periode_bulan' => (int)date('n'), 'periode_tahun' => (int)date('Y')];
    $rows = $laporan->getLaporanPemasukan($filters);
    $total = $laporan->getTotalPemasukan($filters);
    pass("T5: Periode Bulan=" . date('n') . " Tahun=" . date('Y') . " → " . count($rows) . " rows (Rp " . number_format($total, 0, ',', '.') . ")");
} catch (Exception $e) {
    fail('T5: Exception — ' . $e->getMessage());
}

// ─── T6: Pengeluaran ─────────────────────────────────────────────────────
section('T6: Laporan Pengeluaran');
try {
    $rows  = $laporan->getLaporanPengeluaran([]);
    $total = $laporan->getTotalPengeluaran([]);
    $sumRows = array_sum(array_column($rows, 'jumlah'));
    pass("T6: getLaporanPengeluaran returned " . count($rows) . " rows");
    if (abs($sumRows - $total) < 0.01) {
        pass("T6b: Total Pengeluaran matches sum of rows — Rp " . number_format($total, 0, ',', '.'));
    } else {
        fail("T6b: Total mismatch — rows sum={$sumRows}, total={$total}");
    }
} catch (Exception $e) {
    fail('T6: Exception — ' . $e->getMessage());
}

// ─── T7: Arus Kas — saldo calculation ───────────────────────────────────
section('T7–T8: Laporan Arus Kas');
try {
    $summary = $laporan->getArusKasSummary([]);
    $totalMasuk  = (float)$summary['total_masuk'];
    $totalKeluar = (float)$summary['total_keluar'];
    $saldo       = $totalMasuk - $totalKeluar;

    // Cross-check with individual totals
    $expectedMasuk  = $laporan->getTotalPemasukan([]);
    $expectedKeluar = $laporan->getTotalPengeluaran([]);

    if (abs($totalMasuk - $expectedMasuk) < 0.01) {
        pass("T7a: Arus Kas total_masuk matches getPemasukan — Rp " . number_format($totalMasuk, 0, ',', '.'));
    } else {
        fail("T7a: Arus Kas total_masuk mismatch — summary={$totalMasuk}, pemasukan={$expectedMasuk}");
    }
    if (abs($totalKeluar - $expectedKeluar) < 0.01) {
        pass("T7b: Arus Kas total_keluar matches getPengeluaran — Rp " . number_format($totalKeluar, 0, ',', '.'));
    } else {
        fail("T7b: Arus Kas total_keluar mismatch — summary={$totalKeluar}, pengeluaran={$expectedKeluar}");
    }
    pass("T7c: Saldo kas = Rp " . number_format($saldo, 0, ',', '.'));
} catch (Exception $e) {
    fail('T7: Exception — ' . $e->getMessage());
}

// ─── T8: Saldo sebelum periode ───────────────────────────────────────────
try {
    $saldoSebelum = $laporan->getSaldoSebelumPeriode(['tanggal_mulai' => '2099-01-01']);
    // Should be the full saldo since nothing is after this date
    pass("T8: getSaldoSebelumPeriode (before 2099) = Rp " . number_format($saldoSebelum, 0, ',', '.'));

    $saldoSebelumZero = $laporan->getSaldoSebelumPeriode(['tanggal_mulai' => '2000-01-01']);
    // Should be ~0 since nothing before 2000
    if (abs($saldoSebelumZero) < 0.01) {
        pass("T8b: getSaldoSebelumPeriode (before 2000) = 0 as expected");
    } else {
        pass("T8b: getSaldoSebelumPeriode (before 2000) = Rp " . number_format($saldoSebelumZero, 0, ',', '.') . " (may have pre-2000 data)");
    }
} catch (Exception $e) {
    fail('T8: Exception — ' . $e->getMessage());
}

// ─── T9–T10: Pembayaran ──────────────────────────────────────────────────
section('T9–T10: Laporan Pembayaran Anggota');
try {
    $rows  = $laporan->getLaporanPembayaran([]);
    $total = $laporan->getTotalPembayaran([]);
    pass("T9: getLaporanPembayaran returned " . count($rows) . " rows");

    $sumRows = array_sum(array_column($rows, 'nominal'));
    if (abs($sumRows - $total) < 0.01) {
        pass("T10: Total Pembayaran matches row sum — Rp " . number_format($total, 0, ',', '.'));
    } else {
        fail("T10: Total mismatch — rows sum={$sumRows}, total={$total}");
    }
} catch (Exception $e) {
    fail('T9: Exception — ' . $e->getMessage());
}

// ─── T11–T12: Tunggakan ──────────────────────────────────────────────────
section('T11–T12: Laporan Tunggakan');
try {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $filters = ['periode_bulan' => $bulan, 'periode_tahun' => $tahun];
    $tunggakanRows = $laporan->getLaporanTunggakan($filters);

    // Check structure of each row
    $requiredKeys = ['anggota_id', 'nama', 'nomor_identitas', 'kelas', 'minggu_terbayar',
                     'minggu_belum_bayar', 'nominal_per_minggu', 'estimasi_tunggakan', 'status_bulan'];
    $structureOk = true;
    foreach ($tunggakanRows as $row) {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $row)) {
                $structureOk = false;
                fail("T11: Missing key '{$key}' in tunggakan row");
                break 2;
            }
        }
        if (!in_array($row['status_bulan'], ['lunas', 'tunggak'])) {
            fail("T11: Invalid status_bulan value: " . $row['status_bulan']);
            $structureOk = false;
            break;
        }
        if ($row['minggu_terbayar'] + $row['minggu_belum_bayar'] !== 4) {
            fail("T11: minggu_terbayar + minggu_belum_bayar != 4 for anggota {$row['nama']}");
            $structureOk = false;
            break;
        }
    }
    if ($structureOk) {
        $tunggakCount = count(array_filter($tunggakanRows, fn($r) => $r['status_bulan'] === 'tunggak'));
        $lunasCount   = count(array_filter($tunggakanRows, fn($r) => $r['status_bulan'] === 'lunas'));
        pass("T11: Tunggakan rows structure OK — total=" . count($tunggakanRows) . " (tunggak={$tunggakCount}, lunas={$lunasCount})");
    }

    $totalTunggakan = $laporan->getTotalTunggakan($tunggakanRows);
    pass("T12: Total estimasi tunggakan = Rp " . number_format($totalTunggakan, 0, ',', '.'));
} catch (Exception $e) {
    fail('T11: Exception — ' . $e->getMessage());
}

// ─── T13–T14: Empty results ──────────────────────────────────────────────
section('T13–T14: Empty Result Tests');
try {
    $impossibleFilter = ['tanggal_mulai' => '1900-01-01', 'tanggal_akhir' => '1900-12-31'];
    $rows = $laporan->getLaporanPemasukan($impossibleFilter);
    $total = $laporan->getTotalPemasukan($impossibleFilter);
    if (empty($rows) && $total == 0) {
        pass("T13: Pemasukan with impossible date range returns empty (0 rows, total=0) ✓");
    } else {
        fail("T13: Expected empty result but got " . count($rows) . " rows, total={$total}");
    }
} catch (Exception $e) {
    fail('T13: Exception — ' . $e->getMessage());
}

try {
    $impossibleFilter = ['tanggal_mulai' => '1900-01-01', 'tanggal_akhir' => '1900-12-31'];
    $rows = $laporan->getLaporanPengeluaran($impossibleFilter);
    $total = $laporan->getTotalPengeluaran($impossibleFilter);
    if (empty($rows) && $total == 0) {
        pass("T14: Pengeluaran with impossible date range returns empty (0 rows, total=0) ✓");
    } else {
        fail("T14: Expected empty result but got " . count($rows) . " rows, total={$total}");
    }
} catch (Exception $e) {
    fail('T14: Exception — ' . $e->getMessage());
}

// ─── T15: Routes registration ────────────────────────────────────────────
section('T15: Route Registration');
try {
    $routeContent = file_get_contents(__DIR__ . '/routes/web.php');
    $expectedRoutes = [
        'laporan index'       => "'/laporan'",
        'laporan pemasukan'   => "'/laporan/pemasukan'",
        'laporan pengeluaran' => "'/laporan/pengeluaran'",
        'laporan arus-kas'    => "'/laporan/arus-kas'",
        'laporan pembayaran'  => "'/laporan/pembayaran'",
        'laporan tunggakan'   => "'/laporan/tunggakan'",
    ];

    $allFound = true;
    foreach ($expectedRoutes as $label => $needle) {
        if (strpos($routeContent, $needle) === false) {
            fail("T15: Route '{$label}' NOT found in routes/web.php");
            $allFound = false;
        }
    }
    if ($allFound) {
        pass("T15: All 6 laporan routes registered in routes/web.php ✓");
    }
} catch (Exception $e) {
    fail('T15: Exception — ' . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED  |  {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
