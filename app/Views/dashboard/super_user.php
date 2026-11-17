<!-- Dashboard Header & Sapaan Pemilik -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-speedometer2 me-2 text-primary-app"></i>Dashboard Super User
        </h2>
        <p class="text-muted mb-0">
            Selamat datang kembali, <strong><?= e($user['nama'] ?? $user['full_name'] ?? $user['username'] ?? 'Administrator') ?></strong>! 
            <span class="badge bg-primary-subtle text-primary ms-1">Super User</span>
        </p>
    </div>
    <div class="mt-2 mt-md-0">
        <span class="badge bg-glass-dark px-3 py-2">
            <i class="bi bi-calendar-event me-1"></i>
            <?= date('l, d F Y') ?>
        </span>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Saldo Kas -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                            <i class="bi bi-wallet2 fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Saldo Kas Saat Ini</h6>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($stats['saldo_kas']) ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Pemasukan -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 text-success rounded p-3">
                            <i class="bi bi-graph-up-arrow fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Pemasukan</h6>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($stats['total_masuk']) ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Pengeluaran -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-danger bg-opacity-10 text-danger rounded p-3">
                            <i class="bi bi-graph-down-arrow fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Pengeluaran</h6>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= format_rupiah($stats['total_keluar']) ?></h3>
            </div>
        </div>
    </div>

    <!-- Info Anggota -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 text-info rounded p-3">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Anggota Aktif</h6>
                    </div>
                </div>
                <h3 class="mb-0 fw-bold"><?= $stats['total_anggota_aktif'] ?> <span class="fs-6 fw-normal text-muted">Orang</span></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Chart Section -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold mb-0">Grafik Keuangan Tahun Ini</h5>
                <span class="badge bg-light text-dark border"><i class="bi bi-calendar3 me-1"></i> <?= date('Y') ?></span>
            </div>
            <div class="card-body">
                <canvas id="financeChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Ringkasan Pembayaran Kas -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="card-title fw-bold mb-0">Status Pembayaran Kas</h5>
                <p class="text-muted small mb-0">Bulan <?= date('F Y') ?></p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <?php if ($stats['total_anggota_aktif'] == 0): ?>
                    <div class="text-center text-muted my-5">
                        <i class="bi bi-folder-x fs-1"></i>
                        <p class="mt-2">Belum ada anggota aktif.</p>
                    </div>
                <?php else: ?>
                    <div class="w-100 mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Sudah Membayar</span>
                            <span class="fw-bold text-success"><?= $stats['anggota_sudah_bayar'] ?> Orang</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($stats['anggota_sudah_bayar'] / $stats['total_anggota_aktif']) * 100 ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="w-100">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Belum Membayar</span>
                            <span class="fw-bold text-danger"><?= $stats['anggota_belum_bayar'] ?> Orang</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($stats['anggota_belum_bayar'] / $stats['total_anggota_aktif']) * 100 ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Transaksi Terbaru -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title fw-bold mb-0">Transaksi Terbaru</h5>
                <a href="#" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Kode</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Keterangan</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                    Belum ada data transaksi.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $tx): ?>
                                <tr>
                                    <td><?= format_tanggal($tx['tanggal_transaksi']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($tx['kode_transaksi']) ?></span></td>
                                    <td>
                                        <?php if ($tx['jenis'] === 'masuk'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Masuk</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Keluar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($tx['nama_kategori'] ?? 'Lainnya') ?></td>
                                    <td><?= e($tx['keterangan']) ?></td>
                                    <td class="text-end fw-bold <?= $tx['jenis'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
                                        <?= $tx['jenis'] === 'masuk' ? '+' : '-' ?> <?= format_rupiah($tx['jumlah']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inject Chart.js logic via global JS variables -->
<script>
    window.dashboardChartData = <?= $chartData ?? '{}' ?>;
</script>
