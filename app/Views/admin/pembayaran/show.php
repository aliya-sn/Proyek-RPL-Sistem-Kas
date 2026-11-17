<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold mb-0">Detail Pembayaran Kas</h4>
                <a href="<?= url(($rolePrefix ?? 'admin') . '/pembayaran') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-body p-4">
                
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Status Pembayaran</div>
                    <div class="col-sm-8 fw-bold">
                        <?php if ($pembayaran['status'] === 'lunas'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6"><i class="bi bi-check-circle me-1"></i> Lunas</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6"><i class="bi bi-exclamation-circle me-1"></i> Belum Lunas</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Anggota</div>
                    <div class="col-sm-8 fw-bold fs-5">
                        <?= e($pembayaran['anggota_nama']) ?>
                        <div class="small fw-normal text-muted mt-1">
                            <span class="badge bg-light text-dark border me-1"><?= e($pembayaran['nomor_identitas']) ?></span>
                            <?= e($pembayaran['kelas']) ?>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Periode</div>
                    <div class="col-sm-8 fw-bold fs-5">
                        <span class="badge bg-info text-dark border border-info-subtle">
                            <?= date('F', mktime(0, 0, 0, $pembayaran['periode_bulan'], 10)) ?> <?= $pembayaran['periode_tahun'] ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Nominal</div>
                    <div class="col-sm-8 fw-bold fs-4 text-success">
                        Rp <?= number_format($pembayaran['nominal'], 0, ',', '.') ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Tanggal Dibayar</div>
                    <div class="col-sm-8 fw-bold">
                        <?= date('d F Y', strtotime($pembayaran['tanggal_bayar'])) ?>
                    </div>
                </div>

                <?php if (!empty($pembayaran['kode_transaksi'])): ?>
                <div class="row mb-4">
                    <div class="col-sm-4 text-muted">Transaksi Referensi</div>
                    <div class="col-sm-8">
                        <a href="<?= url("admin/transaksi/{$pembayaran['transaksi_id']}") ?>" class="text-decoration-none">
                            <span class="badge bg-light text-primary border p-2"><i class="bi bi-link-45deg me-1"></i><?= e($pembayaran['kode_transaksi']) ?></span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <hr>

                <div class="row small text-muted">
                    <div class="col-sm-6 mb-2 mb-sm-0">
                        <i class="bi bi-person me-1"></i> Dicatat oleh: <strong><?= e($pembayaran['petugas_nama'] ?? 'Sistem') ?></strong>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <i class="bi bi-clock me-1"></i> Waktu rekam: <?= date('d/m/Y H:i', strtotime($pembayaran['created_at'])) ?>
                    </div>
                </div>

            </div>
            
            <div class="card-footer bg-white border-top-0 pb-4 text-end">
                <a href="<?= url(($rolePrefix ?? 'admin') . "/pembayaran/{$pembayaran['id']}/edit") ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit Pembayaran
                </a>
            </div>
        </div>
    </div>
</div>
