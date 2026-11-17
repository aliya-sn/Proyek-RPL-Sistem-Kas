<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="bi bi-shield-lock fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">Pengaturan Password</h4>
                        <p class="text-muted small mb-0">Ubah password akun Anda untuk menjaga keamanan sistem</p>
                    </div>
                </div>

                <form method="POST" action="<?= url('change-password') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Password Saat Ini</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Masukkan password saat ini">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter, kombinasi huruf & angka">
                        </div>
                        <div class="form-text small text-muted">Jangan gunakan password default seperti 'admin123'.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-check2-circle"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Ulangi password baru">
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i>Simpan Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
