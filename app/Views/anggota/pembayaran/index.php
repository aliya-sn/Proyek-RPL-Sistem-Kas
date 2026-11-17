<div class="row mb-4">
    <div class="col-md-6 mb-3 mb-md-0">
        <div class="card border-0 shadow-sm bg-primary text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="text-white-50 mb-0">Total Pembayaran Kas Anda</h6>
                    <i class="bi bi-wallet2 fs-4 text-white-50"></i>
                </div>
                <h2 class="mb-0 fw-bold">Rp <?= number_format($totalBayar, 0, ',', '.') ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-info text-white h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="text-white-50 mb-0">Status Pembayaran Bulan Ini</h6>
                    <i class="bi bi-calendar-check fs-4 text-white-50"></i>
                </div>
                <?php
                $db = \App\Core\Database::getConnection();
                $stmt = $db->prepare("
                    SELECT p.id 
                    FROM pembayaran_kas p
                    LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
                    WHERE p.anggota_id = ? 
                      AND p.periode_bulan = ? 
                      AND p.periode_tahun = ? 
                      AND p.status = 'lunas'
                      AND (p.transaksi_id IS NULL OR t.status = 'aktif')
                ");
                $stmt->execute([$anggota['id'], date('n'), date('Y')]);
                $lunasBulanIni = $stmt->fetch();
                ?>
                <?php if ($lunasBulanIni): ?>
                    <h3 class="mb-0 fw-bold text-white"><i class="bi bi-check-circle-fill me-2"></i>Lunas</h3>
                <?php else: ?>
                    <h3 class="mb-0 fw-bold text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Belum Lunas</h3>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold mb-0">Riwayat Pembayaran Kas</h5>
        <form method="GET" action="<?= url('anggota/pembayaran') ?>" class="d-flex">
            <select name="periode_tahun" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                <?php 
                $currentYear = (int)date('Y');
                for ($i = $currentYear - 2; $i <= $currentYear; $i++): 
                    $selected = (string)$filters['periode_tahun'] === (string)$i ? 'selected' : '';
                ?>
                    <option value="<?= $i ?>" <?= $selected ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Periode</th>
                        <th>Tanggal Bayar</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Status</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pembayaranList)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <p class="mb-0">Tidak ada riwayat pembayaran di tahun ini.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pembayaranList as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border p-2">
                                        <?= date('F', mktime(0, 0, 0, $p['periode_bulan'], 10)) ?> <?= $p['periode_tahun'] ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($p['tanggal_bayar'])) ?></td>
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
                                <td class="small text-muted">
                                    <?= e($p['petugas_nama'] ?? 'Sistem') ?>
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
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?periode_tahun=<?= $filters['periode_tahun'] ?>&page=<?= $currentPage - 1 ?>">Sebelumnya</a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?periode_tahun=<?= $filters['periode_tahun'] ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?periode_tahun=<?= $filters['periode_tahun'] ?>&page=<?= $currentPage + 1 ?>">Selanjutnya</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>
