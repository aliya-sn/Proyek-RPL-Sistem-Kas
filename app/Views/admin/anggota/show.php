<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <a href="<?= url('admin/anggota') ?>" class="btn btn-sm btn-light me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                        <h4 class="card-title fw-bold mb-0">Detail Anggota</h4>
                    </div>
                    <div>
                        <a href="<?= url("admin/anggota/{$anggota['id']}/edit") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit Data</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <!-- Left Column: Photo / Avatar -->
                    <div class="col-12 col-md-3 text-center mb-4 mb-md-0">
                        <div class="avatar-circle avatar-xl mx-auto mb-3 bg-primary bg-opacity-10 text-primary fs-1">
                            <?= strtoupper(substr($anggota['nama'], 0, 1)) ?>
                        </div>
                        <h5 class="fw-bold mb-1"><?= e($anggota['nama']) ?></h5>
                        <p class="text-muted mb-2"><?= e($anggota['kelas']) ?></p>
                        
                        <?php if ($anggota['status'] === 'aktif'): ?>
                            <span class="badge bg-success w-100 py-2">Status: Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary w-100 py-2">Status: Nonaktif</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Right Column: Details -->
                    <div class="col-12 col-md-9 border-start ps-md-4">
                        <h5 class="fw-bold mb-3 border-bottom pb-2">Informasi Pribadi</h5>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Nomor Identitas</div>
                            <div class="col-sm-8 fw-semibold"><?= e($anggota['nomor_identitas']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Nama Lengkap</div>
                            <div class="col-sm-8 fw-semibold"><?= e($anggota['nama']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Kelas / Regu</div>
                            <div class="col-sm-8 fw-semibold"><?= e($anggota['kelas']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Jenis Kelamin</div>
                            <div class="col-sm-8 fw-semibold"><?= $anggota['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 text-muted">Nomor Kontak</div>
                            <div class="col-sm-8 fw-semibold"><?= !empty($anggota['nomor_kontak']) ? e($anggota['nomor_kontak']) : '-' ?></div>
                        </div>

                        <h5 class="fw-bold mb-3 border-bottom pb-2">Informasi Akun (Login)</h5>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Username</div>
                            <div class="col-sm-8 fw-semibold"><?= !empty($anggota['username']) ? e($anggota['username']) : '<span class="text-danger">Belum ada akun</span>' ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Role System</div>
                            <div class="col-sm-8 fw-semibold"><?= !empty($anggota['user_role']) ? ucfirst(e($anggota['user_role'])) : '-' ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Status Akun</div>
                            <div class="col-sm-8 fw-semibold">
                                <?php if (($anggota['user_status'] ?? '') === 'aktif'): ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif dapat login</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Tidak dapat login</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mt-4 pt-3 border-top">
                            <div class="col-sm-6 text-muted small">
                                Didaftarkan pada: <?= format_tanggal($anggota['created_at']) ?>
                            </div>
                            <div class="col-sm-6 text-muted small text-sm-end">
                                Update terakhir: <?= format_tanggal($anggota['updated_at']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
