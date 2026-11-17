<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h4 class="card-title fw-bold mb-0">Edit Transaksi Kas</h4>
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

                <form method="POST" action="<?= url(($rolePrefix ?? 'admin') . "/transaksi/{$transaksi['id']}") ?>" id="formTransaksi">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Transaksi</label>
                        <input type="text" class="form-control bg-light" value="<?= e($transaksi['kode_transaksi']) ?>" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Transaksi <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_transaksi" class="form-control <?= isset($errors['tanggal_transaksi']) ? 'is-invalid' : '' ?>" value="<?= e(old('tanggal_transaksi', $transaksi['tanggal_transaksi'])) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipe Transaksi <span class="text-danger">*</span></label>
                        <select name="jenis" id="jenisTransaksi" class="form-select <?= isset($errors['jenis']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="masuk" <?= old('jenis', $transaksi['jenis']) === 'masuk' ? 'selected' : '' ?>>Pemasukan Kas</option>
                            <option value="keluar" <?= old('jenis', $transaksi['jenis']) === 'keluar' ? 'selected' : '' ?>>Pengeluaran Kas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori_id" id="kategoriTransaksi" class="form-select <?= isset($errors['kategori_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Kategori --</option>
                            <!-- Options will be populated via JS -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nominal (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">Rp</span>
                            <?php 
                                $jumlah = old('jumlah', number_format($transaksi['jumlah'], 0, '', ''));
                                // Format to dot separated
                                $jumlahFormatted = number_format((float)$jumlah, 0, ',', '.');
                            ?>
                            <input type="text" name="jumlah" id="inputNominal" class="form-control <?= isset($errors['jumlah']) ? 'is-invalid' : '' ?>" value="<?= e($jumlahFormatted) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Keterangan <span class="text-danger">*</span></label>
                        <textarea name="keterangan" class="form-control <?= isset($errors['keterangan']) ? 'is-invalid' : '' ?>" rows="3" required><?= e(old('keterangan', $transaksi['keterangan'])) ?></textarea>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url(($rolePrefix ?? 'admin') . '/transaksi') ?>" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jenisSelect = document.getElementById('jenisTransaksi');
    const kategoriSelect = document.getElementById('kategoriTransaksi');
    const inputNominal = document.getElementById('inputNominal');
    
    const kategoriMasuk = <?= json_encode($kategoriMasuk) ?>;
    const kategoriKeluar = <?= json_encode($kategoriKeluar) ?>;
    const oldKategori = '<?= old('kategori_id', $transaksi['kategori_id']) ?>';

    function updateKategori() {
        const jenis = jenisSelect.value;
        kategoriSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
        
        if (!jenis) {
            kategoriSelect.disabled = true;
            return;
        }
        
        kategoriSelect.disabled = false;
        const data = jenis === 'masuk' ? kategoriMasuk : kategoriKeluar;
        
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.nama_kategori;
            if (oldKategori == item.id) {
                option.selected = true;
            }
            kategoriSelect.appendChild(option);
        });
    }

    jenisSelect.addEventListener('change', updateKategori);
    updateKategori(); // Run on load

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
