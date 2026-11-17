<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
        <h4 class="card-title fw-bold mb-0">Manajemen Anggota</h4>
        <a href="<?= url('admin/anggota/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Tambah Anggota
        </a>
    </div>
    <div class="card-body">
        <!-- Search & Filter Form -->
        <form method="GET" action="<?= url('admin/anggota') ?>" class="row g-3 mb-4 mt-1">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama atau nomor identitas..." value="<?= e($filters['search']) ?>">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <select name="kelas" class="form-select">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= e($k['kelas']) ?>" <?= $filters['kelas'] === $k['kelas'] ? 'selected' : '' ?>><?= e($k['kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= $filters['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $filters['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
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
                        <th>Identitas</th>
                        <th>Nama Anggota</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($anggotaList)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <p class="mb-0">Tidak ada data anggota ditemukan.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($anggotaList as $anggota): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?= e($anggota['nomor_identitas']) ?></span></td>
                                <td class="fw-bold">
                                    <?= e($anggota['nama']) ?>
                                    <?php if (!empty($anggota['username'])): ?>
                                        <div class="small text-muted fw-normal"><i class="bi bi-person me-1"></i><?= e($anggota['username']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($anggota['kelas']) ?></td>
                                <td>
                                    <?php if ($anggota['status'] === 'aktif'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url("admin/anggota/{$anggota['id']}") ?>" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url("admin/anggota/{$anggota['id']}/edit") ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        
                                        <?php if ($anggota['status'] === 'aktif'): ?>
                                        <button type="button" class="btn btn-outline-danger" title="Nonaktifkan" data-bs-toggle="modal" data-bs-target="#deactivateModal<?= $anggota['id'] ?>">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Deactivate Modal -->
                                    <?php if ($anggota['status'] === 'aktif'): ?>
                                    <div class="modal fade text-start" id="deactivateModal<?= $anggota['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title">Konfirmasi Tindakan</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Apakah Anda yakin ingin menonaktifkan anggota <strong><?= e($anggota['nama']) ?></strong>?<br>
                                                    Akun pengguna yang tertaut juga akan dinonaktifkan.
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <form method="POST" action="<?= url("admin/anggota/{$anggota['id']}/deactivate") ?>">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-danger">Ya, Nonaktifkan</button>
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
                <!-- Build query string for pagination links -->
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
                Menampilkan halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total: <?= $totalItems ?> anggota)
            </div>
        <?php endif; ?>

    </div>
</div>
