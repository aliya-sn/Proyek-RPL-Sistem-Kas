<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold mb-0">Pembayaran Kas Anggota</h4>
        <a href="<?= url(($rolePrefix ?? 'admin') . '/pembayaran/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Catat Pembayaran
        </a>
    </div>
    <div class="card-body">
        <!-- Search & Filter Form -->
        <form method="GET" action="<?= url(($rolePrefix ?? 'admin') . '/pembayaran') ?>" class="row g-3 mb-4 mt-1">
            <div class="col-12 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama anggota..." value="<?= e($filters['search']) ?>">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <select name="anggota_id" class="form-select">
                    <option value="">Semua Anggota</option>
                    <?php foreach ($anggotaList as $anggota): ?>
                        <option value="<?= $anggota['id'] ?>" <?= (string)$filters['anggota_id'] === (string)$anggota['id'] ? 'selected' : '' ?>>
                            <?= e($anggota['nama']) ?> (<?= e($anggota['kelas']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="periode_bulan" class="form-select">
                    <option value="">Bulan</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= (string)$filters['periode_bulan'] === (string)$i ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 10)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="periode_tahun" class="form-select">
                    <option value="">Tahun</option>
                    <?php $currYear = (int)date('Y'); for ($i = $currYear - 2; $i <= $currYear + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= (string)$filters['periode_tahun'] === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
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
                        <th>Tanggal Bayar</th>
                        <th>Anggota</th>
                        <th>Periode</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pembayaranList)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <p class="mb-0">Tidak ada data pembayaran ditemukan.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pembayaranList as $p): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($p['tanggal_bayar'])) ?></td>
                                <td class="fw-bold">
                                    <?= e($p['anggota_nama']) ?>
                                    <div class="small text-muted fw-normal"><?= e($p['kelas']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark border border-info-subtle">
                                        <?= date('F', mktime(0, 0, 0, $p['periode_bulan'], 10)) ?> <?= $p['periode_tahun'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    Rp <?= number_format($p['nominal'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['status'] === 'lunas'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url(($rolePrefix ?? 'admin') . "/pembayaran/{$p['id']}") ?>" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url(($rolePrefix ?? 'admin') . "/pembayaran/{$p['id']}/edit") ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    </div>
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
                Menampilkan halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total: <?= $totalItems ?> pembayaran)
            </div>
        <?php endif; ?>

    </div>
</div>
