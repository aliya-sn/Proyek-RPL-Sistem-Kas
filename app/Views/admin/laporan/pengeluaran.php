<?php
/**
 * Laporan Pengeluaran
 * Super User only.
 */

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$currentYear = (int)date('Y');
?>

<!-- Print Header -->
<div class="d-none print-only-block">
    <div class="text-center mb-3">
        <h3 class="fw-bold mb-1">LAPORAN PENGELUARAN KAS</h3>
        <p class="mb-0">Periode: <?= e($periodeLabel) ?></p>
        <p class="mb-0 text-muted small">Dicetak oleh: <?= e(auth()['username'] ?? '-') ?> &mdash; <?= date('d/m/Y H:i') ?></p>
    </div>
    <hr>
</div>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3 no-print">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url(($rolePrefix ?? 'admin') . '/laporan') ?>">Laporan Keuangan</a></li>
        <li class="breadcrumb-item active">Pengeluaran</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-arrow-up-right-circle-fill text-danger me-2"></i>Laporan Pengeluaran
        </h1>
        <p class="text-muted mb-0">Periode: <strong><?= e($periodeLabel) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary" id="btn-print-pengeluaran">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </button>
        <a href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>"
           class="btn btn-outline-success" id="btn-export-pengeluaran">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel/CSV
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50 mb-1">Total Pengeluaran</div>
                        <div class="fs-4 fw-bold">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    </div>
                    <i class="bi bi-cash-coin fs-2 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Jumlah Transaksi</div>
                        <div class="fs-4 fw-bold"><?= count($rows) ?></div>
                    </div>
                    <i class="bi bi-receipt fs-2 text-muted"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Rata-rata per Transaksi</div>
                        <div class="fs-4 fw-bold">Rp <?= count($rows) > 0 ? number_format($totalPengeluaran / count($rows), 0, ',', '.') : '0' ?></div>
                    </div>
                    <i class="bi bi-graph-down fs-2 text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/laporan/pengeluaran') ?>" class="row g-3" id="form-filter-pengeluaran">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_mulai']) ?>" id="input-tgl-mulai-pengeluaran">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_akhir']) ?>" id="input-tgl-akhir-pengeluaran">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Bulan</label>
                <select name="periode_bulan" class="form-select form-select-sm" id="select-bulan-pengeluaran">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($namaBulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= (string)$filters['periode_bulan'] === (string)$num ? 'selected' : '' ?>>
                            <?= $nama ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tahun</label>
                <select name="periode_tahun" class="form-select form-select-sm" id="select-tahun-pengeluaran">
                    <option value="">Semua Tahun</option>
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= (string)$filters['periode_tahun'] === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Kategori</label>
                <select name="kategori_id" class="form-select form-select-sm" id="select-kategori-pengeluaran">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($allKategori as $k): ?>
                        <?php if ($k['jenis'] === 'keluar'): ?>
                        <option value="<?= $k['id'] ?>" <?= (string)$filters['kategori_id'] === (string)$k['id'] ? 'selected' : '' ?>>
                            <?= e($k['nama_kategori']) ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" id="btn-filter-pengeluaran">Filter</button>
            </div>
            <div class="col-12">
                <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/pengeluaran') ?>" class="btn btn-light btn-sm" id="btn-reset-pengeluaran">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="fw-bold mb-0">Detail Transaksi Pengeluaran</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-pengeluaran">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:40px">No</th>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-end pe-4">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5" id="empty-pengeluaran">
                                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                <p class="mb-0">Tidak ada data pengeluaran pada periode ini.</p>
                                <small class="text-muted">Coba ubah filter untuk menampilkan data.</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($r['tanggal_transaksi'])) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['kode_transaksi']) ?></span></td>
                            <td><?= e($r['nama_kategori']) ?></td>
                            <td class="text-truncate" style="max-width:220px" title="<?= e($r['keterangan']) ?>"><?= e($r['keterangan']) ?></td>
                            <td class="text-end pe-4 text-danger fw-semibold">
                                <?= number_format($r['jumlah'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-danger">
                    <tr>
                        <th colspan="5" class="ps-4 fw-bold">TOTAL PENGELUARAN</th>
                        <th class="text-end pe-4 fw-bold fs-6">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></th>
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
