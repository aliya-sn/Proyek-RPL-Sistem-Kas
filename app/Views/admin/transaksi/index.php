<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card border-0 shadow-sm bg-primary text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="text-white-50 mb-1">Total Pemasukan</h6>
                <h3 class="mb-0 fw-bold">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card border-0 shadow-sm bg-danger text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="text-white-50 mb-1">Total Pengeluaran</h6>
                <h3 class="mb-0 fw-bold">Rp <?= number_format($totalKeluar, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="text-white-50 mb-1">Saldo Kas</h6>
                <h3 class="mb-0 fw-bold">Rp <?= number_format($saldo, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold mb-0">Riwayat Transaksi Kas</h4>
        <a href="<?= url(($rolePrefix ?? 'admin') . '/transaksi/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Tambah Transaksi
        </a>
    </div>
    <div class="card-body">
        <!-- Search & Filter Form -->
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/transaksi') ?>" class="row g-3 mb-4 mt-1">
            <div class="col-12 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Kode atau keterangan..." value="<?= e($filters['search']) ?>">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <select name="jenis" class="form-select">
                    <option value="">Semua Tipe</option>
                    <option value="masuk" <?= $filters['jenis'] === 'masuk' ? 'selected' : '' ?>>Pemasukan</option>
                    <option value="keluar" <?= $filters['jenis'] === 'keluar' ? 'selected' : '' ?>>Pengeluaran</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="kategori_id" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($allKategori as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= (string)$filters['kategori_id'] === (string)$k['id'] ? 'selected' : '' ?>>
                            <?= e($k['nama_kategori']) ?> (<?= e(ucfirst($k['jenis'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= $filters['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="void" <?= $filters['status'] === 'void' ? 'selected' : '' ?>>Void</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transaksiList)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <p class="mb-0">Tidak ada transaksi ditemukan.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transaksiList as $t): ?>
                            <tr class="<?= $t['status'] === 'void' ? 'table-secondary opacity-75 text-decoration-line-through' : '' ?>">
                                <td><?= date('d/m/Y', strtotime($t['tanggal_transaksi'])) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($t['kode_transaksi']) ?></span></td>
                                <td>
                                    <?php if ($t['jenis'] === 'masuk'): ?>
                                        <span class="text-success"><i class="bi bi-arrow-down-left-circle me-1"></i><?= e($t['nama_kategori']) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-arrow-up-right-circle me-1"></i><?= e($t['nama_kategori']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="<?= $t['status'] === 'void' ? '' : 'text-truncate d-inline-block' ?>" style="max-width: 200px;" title="<?= e($t['keterangan']) ?>"><?= e($t['keterangan']) ?></span></td>
                                <td class="text-end fw-bold <?= $t['status'] === 'void' ? 'text-muted' : ($t['jenis'] === 'masuk' ? 'text-success' : 'text-danger') ?>">
                                    <?= $t['jenis'] === 'masuk' ? '+' : '-' ?> Rp <?= number_format($t['jumlah'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t['status'] === 'aktif'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Void</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$t['id']}") ?>" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                        
                                        <?php if ($t['status'] === 'aktif'): ?>
                                        <a href="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$t['id']}/edit") ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <button type="button" class="btn btn-outline-danger" title="Void Transaksi" data-bs-toggle="modal" data-bs-target="#voidModal<?= $t['id'] ?>">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Void Modal -->
                                    <?php if ($t['status'] === 'aktif'): ?>
                                    <div class="modal fade text-start" id="voidModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Void</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin membatalkan (void) transaksi ini?</p>
                                                    <div class="card bg-light border-0 p-3 mb-0">
                                                        <strong><?= e($t['kode_transaksi']) ?></strong><br>
                                                        <?= e($t['nama_kategori']) ?> - Rp <?= number_format($t['jumlah'], 0, ',', '.') ?>
                                                    </div>
                                                    <small class="text-muted mt-3 d-block">Transaksi yang di-void tidak akan dihapus dari sistem, namun nominalnya tidak akan dihitung dalam laporan saldo kas.</small>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <form method="POST" action="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$t['id']}/void") ?>">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-danger">Ya, Void Transaksi</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center mb-0">
                <?php 
                    $qs = '';
                    foreach ($filters as $k => $v) {
                        if ($v !== '') $qs .= '&' . $k . '=' . urlencode($v);
                    }
                ?>
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage - 1 ?><?= $qs ?>">Sebelumnya</a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $currentPage + 1 ?><?= $qs ?>">Selanjutnya</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php if ($totalItems > 0): ?>
            <div class="text-center text-muted small mt-2">
                Menampilkan halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total: <?= $totalItems ?> transaksi)
            </div>
        <?php endif; ?>

    </div>
</div>
