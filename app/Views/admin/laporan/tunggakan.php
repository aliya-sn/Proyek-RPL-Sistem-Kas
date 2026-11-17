<?php
/**
 * Laporan Tunggakan
 * Super User only.
 */

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$currentYear = (int)date('Y');

$jumlahTunggak = count(array_filter($rows, fn($r) => $r['status_bulan'] === 'tunggak'));
$jumlahLunas   = count(array_filter($rows, fn($r) => $r['status_bulan'] === 'lunas'));
?>

<!-- Print Header -->
<div class="d-none print-only-block">
    <div class="text-center mb-3">
        <h3 class="fw-bold mb-1">LAPORAN TUNGGAKAN KAS ANGGOTA</h3>
        <p class="mb-0">Periode: <?= $namaBulan[$bulanFilter] ?? $bulanFilter ?> <?= $tahunFilter ?></p>
        <p class="mb-0 text-muted small">Dicetak oleh: <?= e(auth()['username'] ?? '-') ?> &mdash; <?= date('d/m/Y H:i') ?></p>
    </div>
    <hr>
</div>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3 no-print">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url(($rolePrefix ?? 'admin') . '/laporan') ?>">Laporan Keuangan</a></li>
        <li class="breadcrumb-item active">Tunggakan</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Laporan Tunggakan
        </h1>
        <p class="text-muted mb-0">
            Periode: <strong><?= ($namaBulan[$bulanFilter] ?? $bulanFilter) . ' ' . $tahunFilter ?></strong>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-2">
                <i class="bi bi-info-circle me-1"></i>Berbasis 4 minggu per bulan
            </span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($jumlahTunggak > 0): ?>
            <?php if (env('FONNTE_BULK_SENDING_ENABLED', false)): ?>
            <button type="button" class="btn btn-warning" id="btn-bulk-reminder" data-bs-toggle="modal" data-bs-target="#modalBulkReminder">
                <i class="bi bi-whatsapp me-1"></i>Kirim Pengingat Massal (<?= $jumlahTunggak ?>)
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-outline-secondary disabled" id="btn-bulk-reminder" title="Pengiriman massal dinonaktifkan sementara untuk keamanan (menunggu persetujuan)">
                <i class="bi bi-shield-lock me-1"></i>Pengingat Massal (Terkunci)
            </button>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= url("{$rolePrefix}/notifikasi") ?>" class="btn btn-outline-primary" id="btn-riwayat-wa">
            <i class="bi bi-clock-history me-1"></i>Riwayat WA
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary" id="btn-print-tunggakan">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </button>
        <a href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>"
           class="btn btn-outline-success" id="btn-export-tunggakan">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel/CSV
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small mb-1 opacity-75">Total Estimasi Tunggakan</div>
                        <div class="fs-5 fw-bold">Rp <?= number_format($totalTunggakan, 0, ',', '.') ?></div>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-2 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-danger-subtle">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Anggota Menunggak</div>
                        <div class="fs-4 fw-bold text-danger"><?= $jumlahTunggak ?></div>
                    </div>
                    <i class="bi bi-person-x fs-2 text-danger-emphasis opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-success-subtle">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Anggota Lunas</div>
                        <div class="fs-4 fw-bold text-success"><?= $jumlahLunas ?></div>
                    </div>
                    <i class="bi bi-person-check fs-2 text-success-emphasis opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Total Anggota Aktif</div>
                        <div class="fs-4 fw-bold"><?= count($rows) ?></div>
                    </div>
                    <i class="bi bi-people fs-2 text-muted"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/laporan/tunggakan') ?>" class="row g-3" id="form-filter-tunggakan">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Bulan <span class="text-danger">*</span></label>
                <select name="periode_bulan" class="form-select form-select-sm" id="select-bulan-tunggakan">
                    <?php foreach ($namaBulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= (int)$bulanFilter === $num ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Tahun <span class="text-danger">*</span></label>
                <select name="periode_tahun" class="form-select form-select-sm" id="select-tahun-tunggakan">
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= (int)$tahunFilter === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Anggota (opsional)</label>
                <select name="anggota_id" class="form-select form-select-sm" id="select-anggota-tunggakan">
                    <option value="">Semua Anggota</option>
                    <?php foreach ($allAnggota as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (string)($filters['anggota_id'] ?? '') === (string)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nama']) ?> (<?= e($a['nomor_identitas']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-filter-tunggakan">Filter</button>
                <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/tunggakan') ?>" class="btn btn-light btn-sm" id="btn-reset-tunggakan">Reset</a>
            </div>
        </form>
        <div class="mt-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Laporan tunggakan dihitung berdasarkan 4 minggu per bulan. Estimasi tunggakan = minggu belum bayar × nominal iuran terakhir.
                Jika anggota belum pernah membayar, estimasi tunggakan = 0 (tidak dapat dihitung).
            </small>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="fw-bold mb-0">
            Daftar Tunggakan — 
            <?= ($namaBulan[$bulanFilter] ?? $bulanFilter) . ' ' . $tahunFilter ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-tunggakan">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:40px">No</th>
                        <th>Nama Anggota</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th class="text-center">Minggu Bayar</th>
                        <th class="text-center">Minggu Belum Bayar</th>
                        <th class="text-end">Nominal/Minggu</th>
                        <th class="text-end pe-4">Estimasi Tunggakan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-print" style="width:130px">Aksi WA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5" id="empty-tunggakan">
                                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                                <p class="mb-0">Tidak ada data anggota aktif.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rows as $r): ?>
                        <tr class="<?= $r['status_bulan'] === 'tunggak' ? 'table-warning bg-opacity-25' : '' ?>">
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= e($r['nama']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['nomor_identitas']) ?></span></td>
                            <td><?= e($r['kelas']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <?= $r['minggu_terbayar'] ?> / 4
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($r['minggu_belum_bayar'] > 0): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <?= $r['minggu_belum_bayar'] ?> minggu
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?= $r['nominal_per_minggu'] > 0 
                                    ? 'Rp ' . number_format($r['nominal_per_minggu'], 0, ',', '.') 
                                    : '<span class="text-muted small">Belum ada data</span>' ?>
                            </td>
                            <td class="text-end pe-4 fw-bold <?= $r['estimasi_tunggakan'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                <?= $r['estimasi_tunggakan'] > 0 
                                    ? 'Rp ' . number_format($r['estimasi_tunggakan'], 0, ',', '.') 
                                    : '—' ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['status_bulan'] === 'lunas'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Menunggak</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print">
                                <?php if ($r['status_bulan'] === 'tunggak'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-send-single-reminder"
                                            data-id="<?= $r['anggota_id'] ?>"
                                            data-nama="<?= e($r['nama']) ?>"
                                            data-nis="<?= e($r['nomor_identitas']) ?>"
                                            data-kelas="<?= e($r['kelas']) ?>"
                                            data-minggu="<?= $r['minggu_belum_bayar'] ?>"
                                            data-tunggakan="<?= $r['estimasi_tunggakan'] ?>"
                                            title="Kirim pengingat WhatsApp ke <?= e($r['nama']) ?>">
                                        <i class="bi bi-whatsapp me-1"></i>Ingatkan
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">
                                        <i class="bi bi-check-circle text-success me-1"></i>Lunas
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($rows) && $totalTunggakan > 0): ?>
                <tfoot class="table-warning">
                    <tr>
                        <th colspan="7" class="ps-4 fw-bold">TOTAL ESTIMASI TUNGGAKAN</th>
                        <th class="text-end pe-4 fw-bold fs-6">Rp <?= number_format($totalTunggakan, 0, ',', '.') ?></th>
                        <th colspan="2" class="no-print"></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Modal Kirim Pengingat Tunggakan Satuan -->
<div class="modal fade" id="modalSingleReminder" tabindex="-1" aria-labelledby="modalSingleReminderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= url("{$rolePrefix}/notifikasi/send-pengingat") ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="anggota_id" id="single-reminder-anggota-id">
                <input type="hidden" name="periode_bulan" value="<?= (int)$bulanFilter ?>">
                <input type="hidden" name="periode_tahun" value="<?= (int)$tahunFilter ?>">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="modalSingleReminderLabel">
                        <i class="bi bi-whatsapp text-success me-2"></i>Kirim Pengingat WhatsApp
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Nama Anggota:</span>
                            <span class="fw-bold" id="single-reminder-nama">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Kelas / NIS:</span>
                            <span class="fw-semibold" id="single-reminder-kelas">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Periode:</span>
                            <span class="fw-semibold"><?= ($namaBulan[$bulanFilter] ?? $bulanFilter) . ' ' . $tahunFilter ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Belum Bayar:</span>
                            <span class="fw-semibold text-danger" id="single-reminder-minggu">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Estimasi Tunggakan:</span>
                            <span class="fw-bold text-danger" id="single-reminder-nominal">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Instruksi Pembayaran Tambahan (opsional)</label>
                        <textarea name="instruksi" class="form-control form-control-sm" rows="3" placeholder="Contoh: Silakan transfer ke rekening BCA 123456 a/n Kas Kelas atau serahkan tunai ke Bendahara saat istirahat."></textarea>
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="force_resend" value="1" id="single-force-resend">
                        <label class="form-check-label small text-muted" for="single-force-resend">
                            Kirim ulang paksa meskipun pengingat periode ini sudah pernah dikirim sebelumnya
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i>Kirim Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pengingat Massal -->
<?php if ($jumlahTunggak > 0): ?>
<div class="modal fade" id="modalBulkReminder" tabindex="-1" aria-labelledby="modalBulkReminderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= url("{$rolePrefix}/notifikasi/bulk-send-pengingat") ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="periode_bulan" value="<?= (int)$bulanFilter ?>">
                <input type="hidden" name="periode_tahun" value="<?= (int)$tahunFilter ?>">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="modalBulkReminderLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Pengiriman Pengingat Massal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">
                        Anda akan mengirimkan pengingat WhatsApp resmi kepada <strong><?= $jumlahTunggak ?> anggota</strong> yang belum menyelesaikan pembayaran kas pada periode <strong><?= ($namaBulan[$bulanFilter] ?? $bulanFilter) . ' ' . $tahunFilter ?></strong>.
                    </p>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body p-3">
                            <div class="small fw-bold text-muted mb-2">Daftar Penerima Pengingat:</div>
                            <div style="max-height: 180px; overflow-y: auto;">
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($rows as $item): ?>
                                        <?php if ($item['status_bulan'] === 'tunggak'): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-1 px-0">
                                            <span>
                                                <i class="bi bi-person me-1"></i><?= e($item['nama']) ?> (<?= e($item['kelas']) ?>)
                                            </span>
                                            <span class="text-danger fw-semibold">
                                                Rp <?= number_format($item['estimasi_tunggakan'], 0, ',', '.') ?> (<?= $item['minggu_belum_bayar'] ?> minggu)
                                            </span>
                                        </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Instruksi Pembayaran untuk Semua Penerima (opsional)</label>
                        <textarea name="instruksi" class="form-control form-control-sm" rows="3" placeholder="Contoh: Silakan transfer ke rekening kas atau serahkan langsung ke Bendahara Kelas."></textarea>
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="force_resend" value="1" id="bulk-force-resend">
                        <label class="form-check-label small text-muted" for="bulk-force-resend">
                            Kirim ulang kepada anggota yang sudah pernah menerima pengingat untuk periode ini (secara default dilewati untuk mencegah pesan ganda)
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-semibold">
                        <i class="bi bi-send-check me-1"></i>Ya, Kirim ke <?= $jumlahTunggak ?> Anggota
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const singleModalEl = document.getElementById('modalSingleReminder');
    if (!singleModalEl) return;
    const singleModal = new bootstrap.Modal(singleModalEl);

    document.querySelectorAll('.btn-send-single-reminder').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('single-reminder-anggota-id').value = this.dataset.id;
            document.getElementById('single-reminder-nama').textContent = this.dataset.nama;
            document.getElementById('single-reminder-kelas').textContent = (this.dataset.kelas || '-') + ' / NIS: ' + (this.dataset.nis || '-');
            document.getElementById('single-reminder-minggu').textContent = this.dataset.minggu + ' minggu';
            
            const nominal = parseFloat(this.dataset.tunggakan || 0);
            document.getElementById('single-reminder-nominal').textContent = 'Rp ' + nominal.toLocaleString('id-ID');

            singleModal.show();
        });
    });
});
</script>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only-block { display: block !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    body { font-size: 12px; }
}
.print-only-block { display: none; }
</style>

