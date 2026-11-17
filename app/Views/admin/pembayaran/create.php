<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Catat Pembayaran Kas Anggota</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url(($rolePrefix ?? 'admin') . '/pembayaran') ?>">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Anggota <span class="text-danger">*</span></label>
                        <select name="anggota_id" class="form-select <?= isset($errors['anggota_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Anggota --</option>
                            <?php foreach ($anggotaList as $anggota): ?>
                                <option value="<?= $anggota['id'] ?>" <?= old('anggota_id') == $anggota['id'] ? 'selected' : '' ?>>
                                    <?= e($anggota['nomor_identitas']) ?> - <?= e($anggota['nama']) ?> (<?= e($anggota['kelas']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Periode Bulan <span class="text-danger">*</span></label>
                            <select name="periode_bulan" class="form-select <?= isset($errors['periode_bulan']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Bulan --</option>
                                <?php 
                                $currentMonth = (int)date('n');
                                for ($i = 1; $i <= 12; $i++): 
                                    $selected = old('periode_bulan') ? (old('periode_bulan') == $i) : ($currentMonth === $i);
                                ?>
                                    <option value="<?= $i ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 10)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Periode Tahun <span class="text-danger">*</span></label>
                            <select name="periode_tahun" class="form-select <?= isset($errors['periode_tahun']) ? 'is-invalid' : '' ?>" required>
                                <?php 
                                $currentYear = (int)date('Y');
                                for ($i = $currentYear - 1; $i <= $currentYear + 1; $i++): 
                                    $selected = old('periode_tahun') ? (old('periode_tahun') == $i) : ($currentYear === $i);
                                ?>
                                    <option value="<?= $i ?>" <?= $selected ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nominal Pembayaran (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">Rp</span>
                            <input type="text" name="nominal" id="inputNominal" class="form-control <?= isset($errors['nominal']) ? 'is-invalid' : '' ?>" value="<?= e(old('nominal', '20.000')) ?>" required>
                        </div>
                        <div class="form-text">Secara default, nilai transaksi akan otomatis ditambahkan ke pemasukan kas.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tanggal Pembayaran <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_bayar" class="form-control <?= isset($errors['tanggal_bayar']) ? 'is-invalid' : '' ?>" value="<?= e(old('tanggal_bayar', date('Y-m-d'))) ?>" required>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url(($rolePrefix ?? 'admin') . '/pembayaran') ?>" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputNominal = document.getElementById('inputNominal');
    
    inputNominal.addEventListener('keyup', function(e) {
        let val = this.value.replace(/[^0-9]/g, '');
        if (val) {
            val = parseInt(val, 10).toString();
            this.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        } else {
            this.value = '';
        }
    });
});
</script>
