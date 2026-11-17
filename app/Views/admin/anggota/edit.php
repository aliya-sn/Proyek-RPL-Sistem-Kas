<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-3">
                <div class="d-flex align-items-center">
                    <a href="<?= url('admin/anggota') ?>" class="btn btn-sm btn-light me-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                    <h4 class="card-title fw-bold mb-0">Edit Anggota: <?= e($anggota['nama']) ?></h4>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url("admin/anggota/{$anggota['id']}") ?>" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nomor Identitas (NIS/NIM) <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_identitas" class="form-control <?= isset($errors['nomor_identitas']) ? 'is-invalid' : '' ?>" value="<?= e(old('nomor_identitas', $anggota['nomor_identitas'])) ?>" required>
                            <?php if (isset($errors['nomor_identitas'])): ?>
                                <div class="invalid-feedback"><?= e($errors['nomor_identitas']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>" value="<?= e(old('nama', $anggota['nama'])) ?>" required>
                            <?php if (isset($errors['nama'])): ?>
                                <div class="invalid-feedback"><?= e($errors['nama']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Kelas / Regu <span class="text-danger">*</span></label>
                            <input type="text" name="kelas" class="form-control <?= isset($errors['kelas']) ? 'is-invalid' : '' ?>" value="<?= e(old('kelas', $anggota['kelas'])) ?>" required>
                            <?php if (isset($errors['kelas'])): ?>
                                <div class="invalid-feedback"><?= e($errors['kelas']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <?php $jk = old('jenis_kelamin', $anggota['jenis_kelamin']); ?>
                            <select name="jenis_kelamin" class="form-select <?= isset($errors['jenis_kelamin']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= $jk === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $jk === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                            <?php if (isset($errors['jenis_kelamin'])): ?>
                                <div class="invalid-feedback"><?= e($errors['jenis_kelamin']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nomor Kontak / WA</label>
                            <input type="text" name="nomor_kontak" class="form-control" value="<?= e(old('nomor_kontak', $anggota['nomor_kontak'])) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status Anggota</label>
                            <?php $status = old('status', $anggota['status']); ?>
                            <select name="status" class="form-select">
                                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <div class="form-text">Mengubah status menjadi nonaktif akan menonaktifkan akun login anggota ini.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= url('admin/anggota') ?>" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
