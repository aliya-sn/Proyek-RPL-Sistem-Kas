<div class="row g-4 mb-4">
    <!-- Profil Card -->
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center pt-4">
                <div class="avatar-circle avatar-lg mx-auto mb-3 bg-primary bg-opacity-10 text-primary fs-2">
                    <?= strtoupper(substr($anggota['nama'] ?? $user['username'] ?? 'U', 0, 1)) ?>
                </div>
                <h4 class="mb-1 fw-bold"><?= e($anggota['nama'] ?? $user['username']) ?></h4>
                <p class="text-muted mb-3"><i class="bi bi-person-badge me-1"></i> <?= e($anggota['nomor_identitas'] ?? '-') ?></p>
                
                <hr>
                
                <div class="d-flex justify-content-between text-start mb-2">
                    <span class="text-muted">Kelas</span>
                    <span class="fw-bold"><?= e($anggota['kelas'] ?? '-') ?></span>
                </div>
                <div class="d-flex justify-content-between text-start mb-2">
                    <span class="text-muted">Status</span>
                    <span>
                        <?php if (($anggota['status'] ?? '') === 'aktif'): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= ucfirst(e($anggota['status'] ?? '-')) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Pembayaran & Total -->
    <div class="col-12 col-md-8">
        <div class="row g-4 h-100">
            <div class="col-12 col-sm-6">
                <div class="card border-0 shadow-sm h-100 <?= $stats['status_bulan_ini'] ? 'bg-success bg-opacity-10 border-success' : 'bg-warning bg-opacity-10 border-warning' ?> border border-2">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                        <?php if ($stats['status_bulan_ini']): ?>
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-success fw-bold">Lunas</h5>
                            <p class="text-muted mb-0 small">Pembayaran kas periode ini sudah lunas.</p>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-warning fw-bold">Belum Lunas</h5>
                            <p class="text-muted mb-0 small">Anda belum membayar kas untuk periode ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                                    <i class="bi bi-cash-stack fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-0">Total Kontribusi</h6>
                            </div>
                        </div>
                        <h3 class="mb-0 fw-bold"><?= format_rupiah($stats['total_pembayaran']) ?></h3>
                        <p class="text-muted small mt-2 mb-0">Total seluruh pembayaran kas yang sah.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Riwayat Pembayaran Pribadi -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="card-title fw-bold mb-0">Riwayat Pembayaran Saya</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal Bayar</th>
                                <th>Periode</th>
                                <th>Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($riwayatPembayaran)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                    Belum ada data pembayaran.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($riwayatPembayaran as $bayar): ?>
                                <tr>
                                    <td><?= format_tanggal($bayar['tanggal_bayar']) ?></td>
                                    <td>
                                        Bulan <?= e($bayar['periode_bulan']) ?> Tahun <?= e($bayar['periode_tahun']) ?><br>
                                        <small class="text-muted">Minggu ke-<?= e($bayar['periode_minggu']) ?></small>
                                    </td>
                                    <td class="fw-bold"><?= format_rupiah($bayar['nominal']) ?></td>
                                    <td>
                                        <?php if ($bayar['status'] === 'lunas'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Lunas</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Belum Lunas</span>
                                        <?php endif; ?>
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
