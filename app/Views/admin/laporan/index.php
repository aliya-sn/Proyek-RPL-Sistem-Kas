<?php
/**
 * Laporan Keuangan — Index / Landing Page
 * Pilih jenis laporan. Super User only.
 */
$pageTitle = $pageTitle ?? 'Laporan Keuangan';
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Laporan Keuangan
        </h1>
        <p class="text-muted mb-0">Pilih jenis laporan yang ingin ditampilkan.</p>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Pemasukan -->
    <div class="col-md-6 col-xl-4">
        <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/pemasukan') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="report-icon-wrap bg-success-subtle rounded-3 me-3">
                            <i class="bi bi-arrow-down-left-circle-fill text-success fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Laporan Pemasukan</h5>
                            <small class="text-muted">Rekap seluruh transaksi kas masuk</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Menampilkan semua transaksi pemasukan kas beserta kategori, tanggal, dan total nominal dalam periode yang dipilih.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                    <span class="text-success fw-semibold small"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</span>
                </div>
            </div>
        </a>
    </div>

    <!-- 2. Pengeluaran -->
    <div class="col-md-6 col-xl-4">
        <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/pengeluaran') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="report-icon-wrap bg-danger-subtle rounded-3 me-3">
                            <i class="bi bi-arrow-up-right-circle-fill text-danger fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Laporan Pengeluaran</h5>
                            <small class="text-muted">Rekap seluruh transaksi kas keluar</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Menampilkan semua transaksi pengeluaran kas beserta kategori, tanggal, dan total nominal dalam periode yang dipilih.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                    <span class="text-danger fw-semibold small"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</span>
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Arus Kas -->
    <div class="col-md-6 col-xl-4">
        <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/arus-kas') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="report-icon-wrap bg-primary-subtle rounded-3 me-3">
                            <i class="bi bi-bar-chart-line-fill text-primary fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Laporan Arus Kas</h5>
                            <small class="text-muted">Cash flow masuk &amp; keluar</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Menampilkan seluruh aliran kas (masuk &amp; keluar) dengan saldo awal, saldo akhir, dan rekap per transaksi.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                    <span class="text-primary fw-semibold small"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</span>
                </div>
            </div>
        </a>
    </div>

    <!-- 4. Pembayaran Anggota -->
    <div class="col-md-6 col-xl-4">
        <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/pembayaran') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="report-icon-wrap bg-info-subtle rounded-3 me-3">
                            <i class="bi bi-people-fill text-info fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Pembayaran Anggota</h5>
                            <small class="text-muted">Rekap iuran kas anggota</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Menampilkan riwayat pembayaran iuran kas seluruh anggota dengan filter periode dan anggota spesifik.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                    <span class="text-info fw-semibold small"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</span>
                </div>
            </div>
        </a>
    </div>

    <!-- 5. Tunggakan -->
    <div class="col-md-6 col-xl-4">
        <a href="<?= url(($rolePrefix ?? 'admin') . '/laporan/tunggakan') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="report-icon-wrap bg-warning-subtle rounded-3 me-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Laporan Tunggakan</h5>
                            <small class="text-muted">Anggota yang belum lunas</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Menampilkan daftar anggota dengan tunggakan iuran kas per periode, estimasi jumlah yang belum terbayar.</p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                    <span class="text-warning fw-semibold small"><i class="bi bi-arrow-right me-1"></i>Lihat Laporan</span>
                </div>
            </div>
        </a>
    </div>
</div>

<style>
.report-icon-wrap {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.report-card {
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    cursor: pointer;
}
.report-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.10) !important;
}
</style>
