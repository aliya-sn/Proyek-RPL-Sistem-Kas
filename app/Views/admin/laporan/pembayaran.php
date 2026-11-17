<?php
/**
 * Laporan Pembayaran Anggota
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
        <h3 class="fw-bold mb-1">LAPORAN PEMBAYARAN KAS ANGGOTA</h3>
        <p class="mb-0">Periode: <?= e($periodeLabel) ?></p>
        <p class="mb-0 text-muted small">Dicetak oleh: <?= e(auth()['username'] ?? '-') ?> &mdash; <?= date('d/m/Y H:i') ?></p>
    </div>
    <hr>
</div>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3 no-print">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url(($rolePrefix ?? 'admin') . '/laporan') ?>">Laporan Keuangan</a></li>
        <li class="breadcrumb-item active">Pembayaran Anggota</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-people-fill text-info me-2"></i>Laporan Pembayaran Anggota
        </h1>
        <p class="text-muted mb-0">Periode: <strong><?= e($periodeLabel) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary" id="btn-print-pembayaran">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </button>
        <a href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>"
           class="btn btn-outline-success" id="btn-export-pembayaran">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel/CSV
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50 mb-1">Total Pembayaran</div>
                        <div class="fs-4 fw-bold">Rp <?= number_format($totalPembayaran, 0, ',', '.') ?></div>
                    </div>
                    <i class="bi bi-credit-card-2-front fs-2 text-white-50"></i>
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
                        <div class="small text-muted mb-1">Anggota Membayar</div>
                        <div class="fs-4 fw-bold"><?= count(array_unique(array_column($rows, 'nomor_identitas'))) ?></div>
                    </div>
                    <i class="bi bi-person-check fs-2 text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/laporan/pembayaran') ?>" class="row g-3" id="form-filter-pembayaran">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_mulai']) ?>" id="input-tgl-mulai-pembayaran">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control form-control-sm"
                       value="<?= e($filters['tanggal_akhir']) ?>" id="input-tgl-akhir-pembayaran">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Bulan</label>
                <select name="periode_bulan" class="form-select form-select-sm" id="select-bulan-pembayaran">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($namaBulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= (string)$filters['periode_bulan'] === (string)$num ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tahun</label>
                <select name="periode_tahun" class="form-select form-select-sm" id="select-tahun-pembayaran">
                    <option value="">Semua Tahun</option>
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= (string)$filters['periode_tahun'] === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Anggota</label>
                <select name="anggota_id" class="form-select form-select-sm" id="select-anggota-pembayaran">
                    <option value="">Semua Anggota</option>
                    <?php foreach ($allAnggota as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (string)$filters['anggota_id'] === (string)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nama']) ?> (<?= e($a['nomor_identitas']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" id="btn-filter-pembayaran">Filter</button>
            </div>
            <div class="col-12">
                <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/pembayaran') ?>" class="btn btn-light btn-sm" id="btn-reset-pembayaran">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="fw-bold mb-0">Riwayat Pembayaran Anggota</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-pembayaran">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:40px">No</th>
                        <th>Tanggal Bayar</th>
                        <th>Nama Anggota</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th class="text-center">Periode</th>
                        <th class="text-end pe-4">Nominal (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5" id="empty-pembayaran">
                                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                <p class="mb-0">Tidak ada data pembayaran pada periode ini.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td><?= $r['tanggal_bayar'] ? date('d/m/Y', strtotime($r['tanggal_bayar'])) : '-' ?></td>
                            <td class="fw-semibold"><?= e($r['anggota_nama']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['nomor_identitas']) ?></span></td>
                            <td><?= e($r['kelas']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary">
                                    Minggu <?= $r['periode_minggu'] ?> / <?= $namaBulan[(int)$r['periode_bulan']] ?? $r['periode_bulan'] ?> <?= $r['periode_tahun'] ?>
                                </span>
                            </td>
                            <td class="text-end pe-4 text-info fw-semibold">
                                <?= number_format($r['nominal'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot class="table-info">
                    <tr>
                        <th colspan="6" class="ps-4 fw-bold">TOTAL PEMBAYARAN</th>
                        <th class="text-end pe-4 fw-bold fs-6">Rp <?= number_format($totalPembayaran, 0, ',', '.') ?></th>
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
