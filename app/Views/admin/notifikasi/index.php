<?php
/**
 * Riwayat Notifikasi WhatsApp (Fonnte API Gateway)
 * Mendukung role super_user dan bendahara via $rolePrefix
 */
$qsParams = array_filter($filters);
unset($qsParams['page']);
$qs = !empty($qsParams) ? '&' . http_build_query($qsParams) : '';
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-whatsapp text-success me-2"></i>Riwayat Notifikasi WhatsApp
        </h1>
        <p class="text-muted mb-0">Catatan pengiriman pesan WhatsApp resmi melalui gateway Fonnte API.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url("{$rolePrefix}/laporan/tunggakan") ?>" class="btn btn-outline-warning">
            <i class="bi bi-bell me-1"></i>Pengingat Tunggakan
        </a>
    </div>
</div>

<?php if (!$tokenConfigured): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
    <div>
        <div class="fw-bold">Token Fonnte API Belum Dikonfigurasi</div>
        <div class="small text-muted">Variabel <code>FONNTE_API_TOKEN</code> di file <code>.env</code> masih kosong. Pesan WhatsApp baru akan dicatat sebagai gagal sampai token API diaktifkan.</div>
    </div>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Total Notifikasi</div>
                        <div class="fs-4 fw-bold"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                    <i class="bi bi-chat-left-dots fs-2 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Terkirim / Antrean</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($stats['sent'] ?? 0) ?></div>
                    </div>
                    <i class="bi bi-check2-circle fs-2 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Gagal Terkirim</div>
                        <div class="fs-4 fw-bold text-danger"><?= number_format($stats['failed'] ?? 0) ?></div>
                    </div>
                    <i class="bi bi-x-circle fs-2 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted mb-1">Menunggu (Pending)</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format($stats['pending'] ?? 0) ?></div>
                    </div>
                    <i class="bi bi-hourglass-split fs-2 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url("{$rolePrefix}/notifikasi") ?>" class="row g-3" id="form-filter-notifikasi">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Pencarian</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Nama / No WA / Isi pesan..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold">Jenis Notifikasi</label>
                <select name="tipe" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <option value="payment_confirmation" <?= ($filters['tipe'] ?? '') === 'payment_confirmation' ? 'selected' : '' ?>>Konfirmasi Pembayaran</option>
                    <option value="arrears_reminder" <?= ($filters['tipe'] ?? '') === 'arrears_reminder' ? 'selected' : '' ?>>Pengingat Tunggakan</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="sent" <?= ($filters['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Terkirim / Antrean</option>
                    <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Gagal</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tgl Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control form-control-sm" value="<?= e($filters['tanggal_mulai'] ?? '') ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tgl Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control form-control-sm" value="<?= e($filters['tanggal_akhir'] ?? '') ?>">
            </div>

            <div class="col-md-1 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Filter Data"><i class="bi bi-funnel"></i></button>
                <a href="<?= url("{$rolePrefix}/notifikasi") ?>" class="btn btn-light btn-sm w-100" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Daftar Pengiriman WhatsApp</h5>
        <span class="badge bg-light text-dark border">Total: <?= number_format($totalItems) ?> log</span>
    </div>
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table-notifikasi">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 40px;">No</th>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Penerima</th>
                        <th>No. WhatsApp</th>
                        <th>Ringkasan Pesan</th>
                        <th class="text-center">Status</th>
                        <th>Pengirim</th>
                        <th class="text-center pe-4" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-whatsapp fs-1 d-block mb-3 text-muted opacity-50"></i>
                                <p class="mb-0">Belum ada riwayat notifikasi WhatsApp.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = ($currentPage - 1) * 15 + 1; foreach ($notifications as $n): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="small fw-semibold"><?= date('d/m/Y', strtotime($n['created_at'])) ?></div>
                                <div class="text-muted smaller"><?= date('H:i:s', strtotime($n['created_at'])) ?> WIB</div>
                            </td>
                            <td>
                                <?php if ($n['tipe_notifikasi'] === 'payment_confirmation'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-receipt me-1"></i>Konfirmasi Bayar
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Pengingat Tunggakan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($n['anggota_nama'] ?? 'Anggota #' . $n['anggota_id']) ?></div>
                                <div class="text-muted smaller">
                                    <?= e($n['anggota_kelas'] ?? '') ?> 
                                    <?= !empty($n['anggota_nis']) ? '(' . e($n['anggota_nis']) . ')' : '' ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-telephone me-1 text-success"></i><?= e($n['nomor_tujuan']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-truncate text-muted small" style="max-width: 260px;" title="<?= e($n['pesan']) ?>">
                                    <?= e(mb_substr($n['pesan'], 0, 75)) ?>...
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($n['status'] === 'sent'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Pesan diterima server Fonnte dan ditempatkan dalam antrean pengiriman">
                                        <i class="bi bi-clock-history me-1"></i>Antrean Provider (Queue)
                                    </span>
                                <?php elseif ($n['status'] === 'failed'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="<?= e($n['error_message'] ?? 'Gagal') ?>">
                                        <i class="bi bi-x-circle me-1"></i>Gagal
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-hourglass-split me-1"></i>Pending (Lokal)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <?= e($n['pengirim_nama'] ?? $n['pengirim_username'] ?? 'Sistem') ?>
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-detail-notif" 
                                            data-id="<?= $n['id'] ?>"
                                            data-nama="<?= e($n['anggota_nama'] ?? '-') ?>"
                                            data-nomor="<?= e($n['nomor_tujuan']) ?>"
                                            data-tipe="<?= $n['tipe_notifikasi'] === 'payment_confirmation' ? 'Konfirmasi Pembayaran' : 'Pengingat Tunggakan' ?>"
                                            data-waktu="<?= date('d/m/Y H:i:s', strtotime($n['created_at'])) ?>"
                                            data-status="<?= $n['status'] ?>"
                                            data-pesan="<?= htmlspecialchars($n['pesan'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-error="<?= htmlspecialchars($n['error_message'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-msgid="<?= htmlspecialchars($n['message_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-provider="<?= htmlspecialchars($n['provider_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            title="Lihat Detail Pesan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($n['status'] === 'failed'): ?>
                                    <form method="POST" action="<?= url("{$rolePrefix}/notifikasi/{$n['id']}/resend") ?>" class="d-inline" onsubmit="return confirm('Kirim ulang pesan WhatsApp ini ke <?= e($n['nomor_tujuan']) ?>?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger" title="Kirim Ulang Pesan">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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
        <nav class="mt-4 pb-3">
            <ul class="pagination justify-content-center mb-0">
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
            <div class="text-center text-muted small pb-3">
                Menampilkan halaman <?= $currentPage ?> dari <?= $totalPages ?> (Total: <?= $totalItems ?> catatan notifikasi)
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detail Pesan WhatsApp -->
<div class="modal fade" id="modalDetailNotif" tabindex="-1" aria-labelledby="modalDetailNotifLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalDetailNotifLabel">
                    <i class="bi bi-whatsapp text-success me-2"></i>Detail Pesan WhatsApp
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Penerima</label>
                        <div class="fw-semibold" id="modal-detail-penerima">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Nomor WhatsApp</label>
                        <div class="fw-semibold" id="modal-detail-nomor">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Jenis Notifikasi</label>
                        <div class="fw-semibold" id="modal-detail-tipe">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Waktu Pengiriman</label>
                        <div class="fw-semibold" id="modal-detail-waktu">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Status Provider</label>
                        <div id="modal-detail-status">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted mb-0">Message ID Fonnte</label>
                        <div class="text-monospace small" id="modal-detail-msgid">-</div>
                    </div>
                </div>

                <div id="modal-detail-error-box" class="alert alert-danger border-0 d-none mb-3">
                    <div class="small fw-bold">Alasan Kegagalan:</div>
                    <div class="small" id="modal-detail-error"></div>
                </div>

                <label class="small text-muted mb-1">Isi Pesan Terkirim</label>
                <!-- Preview Bubble WhatsApp -->
                <div class="wa-preview-card p-3 rounded-3" style="background-color: #EFEAE2; border: 1px solid #ddd;">
                    <div class="wa-bubble p-3 rounded-3 bg-white shadow-sm" style="max-width: 90%; white-space: pre-wrap; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; line-height: 1.5; color: #111b21;" id="modal-detail-pesan"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailModalEl = document.getElementById('modalDetailNotif');
    if (!detailModalEl) return;
    const detailModal = new bootstrap.Modal(detailModalEl);

    document.querySelectorAll('.btn-detail-notif').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal-detail-penerima').textContent = this.dataset.nama || '-';
            document.getElementById('modal-detail-nomor').textContent = this.dataset.nomor || '-';
            document.getElementById('modal-detail-tipe').textContent = this.dataset.tipe || '-';
            document.getElementById('modal-detail-waktu').textContent = this.dataset.waktu || '-';
            document.getElementById('modal-detail-pesan').textContent = this.dataset.pesan || '';
            document.getElementById('modal-detail-msgid').textContent = this.dataset.msgid || '—';

            const status = this.dataset.status;
            const processState = this.dataset.provider || 'pending';
            const statusEl = document.getElementById('modal-detail-status');
            if (status === 'sent') {
                statusEl.innerHTML = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-clock-history me-1"></i>Antrean Gateway (' + processState + ')</span><div class="small text-muted mt-1">Pesan telah diterima server Fonnte dan masuk antrean pengiriman. Belum dapat dipastikan telah sampai/terbaca di ponsel tujuan.</div>';
            } else if (status === 'failed') {
                statusEl.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Gagal</span>';
            } else {
                statusEl.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-hourglass-split me-1"></i>Pending (Lokal)</span>';
            }

            const errorBox = document.getElementById('modal-detail-error-box');
            const errorText = document.getElementById('modal-detail-error');
            if (this.dataset.error && this.dataset.error.trim() !== '') {
                errorText.textContent = this.dataset.error;
                errorBox.classList.remove('d-none');
            } else {
                errorBox.classList.add('d-none');
            }

            detailModal.show();
        });
    });
});
</script>
