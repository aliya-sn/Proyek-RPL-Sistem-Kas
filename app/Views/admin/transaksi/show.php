<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold mb-0">Detail Transaksi</h4>
                <a href="<?= url(($rolePrefix ?? 'admin') . '/transaksi') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-body p-4">
                
                <?php if ($transaksi['status'] === 'void'): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Transaksi ini telah dibatalkan (VOID).</strong><br>
                            Nominal dari transaksi ini tidak lagi dihitung dalam total saldo kas aktif.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-sm-4 text-muted mb-1 mb-sm-0">Kode Transaksi</div>
                    <div class="col-sm-8 fw-bold fs-5">
                        <span class="badge bg-light text-dark border p-2">
                            <?= e($transaksi['kode_transaksi']) ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Tanggal</div>
                    <div class="col-sm-8 fw-bold">
                        <?= date('d F Y', strtotime($transaksi['tanggal_transaksi'])) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Tipe Transaksi</div>
                    <div class="col-sm-8 fw-bold">
                        <?php if ($transaksi['jenis'] === 'masuk'): ?>
                            <span class="text-success"><i class="bi bi-arrow-down-left-circle me-1"></i>Pemasukan Kas</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-arrow-up-right-circle me-1"></i>Pengeluaran Kas</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Kategori</div>
                    <div class="col-sm-8 fw-bold">
                        <?= e($transaksi['nama_kategori'] ?? 'Tidak diketahui') ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Nominal</div>
                    <div class="col-sm-8 fw-bold fs-4 <?= $transaksi['status'] === 'void' ? 'text-muted text-decoration-line-through' : ($transaksi['jenis'] === 'masuk' ? 'text-success' : 'text-danger') ?>">
                        Rp <?= number_format($transaksi['jumlah'], 0, ',', '.') ?>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-sm-4 text-muted">Keterangan</div>
                    <div class="col-sm-8 bg-light p-3 rounded">
                        <?= nl2br(e($transaksi['keterangan'])) ?>
                    </div>
                </div>

                <hr>

                <div class="row small text-muted">
                    <div class="col-sm-6 mb-2 mb-sm-0">
                        <i class="bi bi-person me-1"></i> Dicatat oleh: <strong><?= e($transaksi['creator_name'] ?? 'Sistem') ?></strong>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <i class="bi bi-clock me-1"></i> Terakhir diubah: <?= date('d/m/Y H:i', strtotime($transaksi['updated_at'])) ?>
                    </div>
                </div>

            </div>
            
            <?php if ($transaksi['status'] === 'aktif'): ?>
            <div class="card-footer bg-white border-top-0 pb-4 text-end">
                <a href="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$transaksi['id']}/edit") ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil me-1"></i> Edit Transaksi
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidModal">
                    <i class="bi bi-x-circle me-1"></i> Void Transaksi
                </button>
            </div>

            <!-- Void Modal -->
            <div class="modal fade text-start" id="voidModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Void</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Apakah Anda yakin ingin membatalkan (void) transaksi ini?</p>
                            <small class="text-muted mt-3 d-block">Transaksi yang di-void tidak akan dihapus dari sistem, namun nominalnya tidak akan dihitung dalam laporan saldo kas.</small>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <form method="POST" action="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$transaksi['id']}/void") ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger">Ya, Void Transaksi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
