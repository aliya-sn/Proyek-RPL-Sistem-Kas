<?php
/**
 * test_member_period_payment.php
 * Tests combined member + period payment filtering and multi-member arrears in AI local analytical engine.
 */
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\AIService;

Env::load(__DIR__ . '/.env');

$pass = 0;
$fail = 0;

function pass(string $name): void { global $pass; $pass++; echo "  [PASS] {$name}\n"; }
function fail(string $name, string $reason = ''): void { global $fail; $fail++; echo "  [FAIL] {$name}" . ($reason ? " — {$reason}" : '') . "\n"; }

echo "=================================================================\n";
echo " MEMBER + PERIOD PAYMENT & MULTI-MEMBER ARREARS — TEST SUITE\n";
echo "=================================================================\n";

$ai = new AIService();
$ref = new ReflectionClass($ai);
$method = $ref->getMethod('generateLocalAnalysis');
$method->setAccessible(true);

// T1: Combined member + period payment
$contextCombined = [
    'query_tertarget' => [
        'periode' => [
            'nama_bulan' => 'November',
            'tahun' => 2026,
            'total_nominal' => 1000000.0,
            'jumlah_transaksi' => 5,
            'jumlah_anggota_bayar' => 3,
            'daftar_pembayar' => [],
            'daftar_menunggak' => [],
        ],
        'anggota_terpilih' => [
            'profil' => ['nama' => 'Ahmad Rizky'],
            'pembayaran' => [
                'total_nominal' => 300000.0,
                'jumlah_transaksi' => 2,
                'rincian' => [
                    ['periode' => 'November 2026', 'nominal' => 100000.0, 'tanggal_bayar' => '2026-11-10'],
                    ['periode' => 'Oktober 2026', 'nominal' => 200000.0, 'tanggal_bayar' => '2026-10-10'],
                ],
            ],
        ],
        'anggota_terpilih_list' => [
            [
                'profil' => ['nama' => 'Ahmad Rizky'],
                'pembayaran' => [
                    'total_nominal' => 300000.0,
                    'jumlah_transaksi' => 2,
                    'rincian' => [
                        ['periode' => 'November 2026', 'nominal' => 100000.0, 'tanggal_bayar' => '2026-11-10'],
                        ['periode' => 'Oktober 2026', 'nominal' => 200000.0, 'tanggal_bayar' => '2026-10-10'],
                    ],
                ],
            ],
        ],
    ],
    'ringkasan_kas' => ['saldo_kas_saat_ini' => 0, 'total_pemasukan_semua' => 0, 'total_pengeluaran_semua' => 0],
];

try {
    $answer = $method->invoke($ai, 'Pada bulan November, Ahmad Rizky melakukan berapa pembayaran?', $contextCombined, 'super_user', []);
    if (str_contains($answer, 'Ahmad Rizky') && str_contains($answer, '1 pembayaran') && str_contains($answer, 'November 2026') && str_contains($answer, '100.000')) {
        pass('T1: Combined member + period returns only that member payment in that period');
    } else {
        fail('T1: Combined member + period', $answer);
    }
} catch (\Throwable $e) {
    fail('T1: Exception — ' . $e->getMessage());
}

// T2: Period-only
$contextPeriod = [
    'query_tertarget' => [
        'periode' => [
            'nama_bulan' => 'November',
            'tahun' => 2026,
            'total_nominal' => 1000000.0,
            'jumlah_transaksi' => 5,
            'jumlah_anggota_bayar' => 3,
            'daftar_pembayar' => [],
            'daftar_menunggak' => [],
        ],
    ],
    'ringkasan_kas' => ['saldo_kas_saat_ini' => 0, 'total_pemasukan_semua' => 0, 'total_pengeluaran_semua' => 0],
];

try {
    $answer = $method->invoke($ai, 'Total pembayaran kas pada bulan November 2026 berapa?', $contextPeriod, 'super_user', []);
    if (str_contains($answer, 'Total pembayaran kas pada periode November 2026') && str_contains($answer, '1.000.000')) {
        pass('T2: Period-only returns all members payment total for that period');
    } else {
        fail('T2: Period-only', $answer);
    }
} catch (\Throwable $e) {
    fail('T2: Exception — ' . $e->getMessage());
}

// T3: Combined with no matching payment
$contextNoMatch = $contextCombined;
$contextNoMatch['query_tertarget']['anggota_terpilih']['pembayaran']['rincian'] = [
    ['periode' => 'Oktober 2026', 'nominal' => 200000.0, 'tanggal_bayar' => '2026-10-10'],
];
$contextNoMatch['query_tertarget']['anggota_terpilih_list'][0]['pembayaran']['rincian'] = [
    ['periode' => 'Oktober 2026', 'nominal' => 200000.0, 'tanggal_bayar' => '2026-10-10'],
];

try {
    $answer = $method->invoke($ai, 'Pada bulan November, Ahmad Rizky melakukan berapa pembayaran?', $contextNoMatch, 'super_user', []);
    if (str_contains($answer, 'tidak memiliki catatan pembayaran')) {
        pass('T3: Combined with no matching payment returns no-payment message');
    } else {
        fail('T3: No-match case', $answer);
    }
} catch (\Throwable $e) {
    fail('T3: Exception — ' . $e->getMessage());
}

// T4: Multi-member arrears
$contextMultiArrears = [
    'query_tertarget' => [
        'periode' => [
            'nama_bulan' => 'November',
            'tahun' => 2026,
            'total_nominal' => 810000.0,
            'jumlah_transaksi' => 6,
            'jumlah_anggota_bayar' => 6,
            'daftar_pembayar' => [],
            'daftar_menunggak' => [
                ['nama' => 'Diva Nayla', 'kelas' => 'XII TKJ 2', 'minggu_belum_bayar' => 4, 'estimasi_tunggakan' => 80000.0],
                ['nama' => 'Siti Nurhaliza', 'kelas' => 'XII TKJ 2', 'minggu_belum_bayar' => 4, 'estimasi_tunggakan' => 100000.0],
            ],
        ],
        'anggota_terpilih' => [
            'profil' => ['nama' => 'Diva Nayla'],
            'pembayaran' => ['total_nominal' => 90000.0, 'jumlah_transaksi' => 2, 'rincian' => []],
        ],
        'anggota_terpilih_list' => [
            ['profil' => ['nama' => 'Diva Nayla'], 'pembayaran' => ['total_nominal' => 90000.0, 'jumlah_transaksi' => 2, 'rincian' => []]],
            ['profil' => ['nama' => 'Siti Nurhaliza'], 'pembayaran' => ['total_nominal' => 0.0, 'jumlah_transaksi' => 0, 'rincian' => []]],
        ],
    ],
    'ringkasan_kas' => ['saldo_kas_saat_ini' => 0, 'total_pemasukan_semua' => 0, 'total_pengeluaran_semua' => 0],
];

try {
    $answer = $method->invoke($ai, 'Berapa nominal tunggakan Diva Nayla dan Siti Nurhaliza pada November 2026?', $contextMultiArrears, 'super_user', []);
    if (str_contains($answer, 'Diva Nayla: Rp 80.000') && str_contains($answer, 'Siti Nurhaliza: Rp 100.000')) {
        pass('T4: Multi-member arrears returns tunggakan for each requested member');
    } else {
        fail('T4: Multi-member arrears', $answer);
    }
} catch (\Throwable $e) {
    fail('T4: Exception — ' . $e->getMessage());
}

// T5: Single-member arrears
$contextSingleArrears = $contextMultiArrears;
$contextSingleArrears['query_tertarget']['anggota_terpilih_list'] = [
    ['profil' => ['nama' => 'Diva Nayla'], 'pembayaran' => ['total_nominal' => 90000.0, 'jumlah_transaksi' => 2, 'rincian' => []]],
];

try {
    $answer = $method->invoke($ai, 'Berapa tunggakan Diva Nayla November 2026?', $contextSingleArrears, 'super_user', []);
    if (str_contains($answer, 'Diva Nayla: Rp 80.000')) {
        pass('T5: Single-member arrears returns that member tunggakan');
    } else {
        fail('T5: Single-member arrears', $answer);
    }
} catch (\Throwable $e) {
    fail('T5: Exception — ' . $e->getMessage());
}

echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED | {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
?>