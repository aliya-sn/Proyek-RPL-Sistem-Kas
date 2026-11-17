<?php
/**
 * Laporan Arus Kas
 * Super User only.
 */

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$currentYear = (int)date('Y');

$saldo = $saldoAwal ?? 0;
$runningRows = [];
foreach ($rows as $r) {
    if ($r['jenis'] === 'masuk') {
        $saldo += (float)$r['jumlah'];
    } else {
        $saldo -= (float)$r['jumlah'];
    }
    $runningRows[] = array_merge($r, ['saldo_berjalan' => $saldo]);
}
?>

<!-- Print Header -->
<div class="d-none print-only-block">
    <div class="text-center mb-3">
        <h3 class="fw-bold mb-1">LAPORAN ARUS KAS</h3>
        <p class="mb-0">Periode: <?= e($periodeLabel) ?></p>
        <p class="mb-0 text-muted small">Dicetak oleh: <?= e(auth()['username'] ?? '-') ?> &mdash; <?= date('d/m/Y H:i') ?></p>
    </div>
    <hr>
</div>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3 no-print">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url(($rolePrefix ?? 'admin') . '/laporan') ?>">Laporan Keuangan</a></li>
        <li class="breadcrumb-item active">Arus Kas</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Laporan Arus Kas
        </h1>
        <p class="text-muted mb-0">Periode: <strong><?= e($periodeLabel) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary" id="btn-print-arus-kas">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </button>
        <a href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>"
           class="btn btn-outline-success" id="btn-export-arus-kas">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel/CSV
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="small text-muted mb-1">Saldo Awal Periode</div>
                <div class="fs-5 fw-bold <?= ($saldoAwal ?? 0) >= 0 ? 'text-dark' : 'text-danger' ?>">
                    Rp <?= number_format($saldoAwal ?? 0, 0, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body py-3">
                <div class="small text-white-50 mb-1">Total Pemasukan</div>
                <div class="fs-5 fw-bold">Rp <?= number_format($summary['total_masuk'] ?? 0, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body py-3">
                <div class="small text-white-50 mb-1">Total Pengeluaran</div>
                <div class="fs-5 fw-bold">Rp <?= number_format($summary['total_keluar'] ?? 0, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm <?= ($saldoAkhir ?? 0) >= 0 ? 'bg-primary' : 'bg-warning' ?> text-white">
            <div class="card-body py-3">
                <div class="small text-white-50 mb-1">Saldo Akhir Periode</div>
                <div class="fs-5 fw-bold">Rp <?= number_format($saldoAkhir ?? 0, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/laporan/arus-kas') ?>" class="row g-3" id="form-filter-arus-kas">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_mulai']) ?>" id="input-tgl-mulai-arus-kas">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_akhir']) ?>" id="input-tgl-akhir-arus-kas">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Bulan</label>
                <select name="periode_bulan" class="form-select form-select-sm" id="select-bulan-arus-kas">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($namaBulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= (string)$filters['periode_bulan'] === (string)$num ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tahun</label>
                <select name="periode_tahun" class="form-select form-select-sm" id="select-tahun-arus-kas">
                    <option value="">Semua Tahun</option>
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= (string)$filters['periode_tahun'] === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-filter-arus-kas">Filter</button>
                <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/arus-kas') ?>" class="btn btn-light btn-sm" id="btn-reset-arus-kas">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Cash Flow Summary Box (print-friendly) -->
<div class="card border-0 shadow-sm mb-3 bg-light">
    <div class="card-body py-3">
        <div class="row text-center">
            <div class="col">
                <div class="small text-muted">Saldo Awal</div>
                <div class="fw-bold">Rp <?= number_format($saldoAwal ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="col border-start">
                <div class="small text-success">+ Pemasukan</div>
                <div class="fw-bold text-success">Rp <?= number_format($summary['total_masuk'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="col border-start">
                <div class="small text-danger">- Pengeluaran</div>
                <div class="fw-bold text-danger">Rp <?= number_format($summary['total_keluar'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="col border-start">
                <div class="small text-primary fw-semibold">= Saldo Akhir</div>
                <div class="fw-bold text-primary">Rp <?= number_format($saldoAkhir ?? 0, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="fw-bold mb-0">Detail Transaksi Kas</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-arus-kas">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:40px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Keterangan</th>
                        <th class="text-end">Masuk (Rp)</th>
                        <th class="text-end">Keluar (Rp)</th>
                        <th class="text-end pe-4">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($runningRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5" id="empty-arus-kas">
                                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                <p class="mb-0">Tidak ada data transaksi pada periode ini.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <!-- Opening balance row -->
                        <tr class="table-light">
                            <td class="ps-4 text-muted" colspan="6"><em>Saldo Awal Periode</em></td>
                            <td class="text-end pe-4 fw-semibold">Rp <?= number_format($saldoAwal ?? 0, 0, ',', '.') ?></td>
                        </tr>
                        <?php $no = 1; foreach ($runningRows as $r): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($r['tanggal_transaksi'])) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['kode_transaksi']) ?></span></td>
                            <td class="text-truncate" style="max-width:200px" title="<?= e($r['keterangan']) ?>"><?= e($r['keterangan']) ?></td>
                            <td class="text-end text-success fw-semibold">
                                <?= $r['jenis'] === 'masuk' ? number_format($r['jumlah'], 0, ',', '.') : '-' ?>
                            </td>
                            <td class="text-end text-danger fw-semibold">
                                <?= $r['jenis'] === 'keluar' ? number_format($r['jumlah'], 0, ',', '.') : '-' ?>
                            </td>
                            <td class="text-end pe-4 fw-bold <?= $r['saldo_berjalan'] >= 0 ? 'text-dark' : 'text-danger' ?>">
                                Rp <?= number_format($r['saldo_berjalan'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($runningRows)): ?>
                <tfoot class="table-primary">
                    <tr>
                        <th colspan="4" class="ps-4 fw-bold">SALDO AKHIR PERIODE</th>
                        <th class="text-end fw-bold text-success">Rp <?= number_format($summary['total_masuk'] ?? 0, 0, ',', '.') ?></th>
                        <th class="text-end fw-bold text-danger">Rp <?= number_format($summary['total_keluar'] ?? 0, 0, ',', '.') ?></th>
                        <th class="text-end pe-4 fw-bold fs-6">Rp <?= number_format($saldoAkhir ?? 0, 0, ',', '.') ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only-block { display: block !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    body { font-size: 12px; }
}
.print-only-block { display: none; }
</style>
