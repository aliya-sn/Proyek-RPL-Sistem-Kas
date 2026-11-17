<?php
// Layout parent: layouts/main
use App\Core\Csrf;
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Page -->
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-3 d-inline-flex align-items-center justify-content-center" style="width: 58px; height: 58px;">
                    <i class="bi bi-sliders2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Konfigurasi AI Assistant</h3>
                    <p class="text-muted small mb-0">Kelola API Key Google Gemini, pemilihan model dinamis, dan verifikasi koneksi tanpa restart server.</p>
                </div>
            </div>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <a href="<?= url('admin/ai-assistant') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2">
                <i class="bi bi-robot me-1"></i> Buka Chat Assistant
            </a>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                <i class="bi bi-shield-lock-fill me-1"></i> Admin Super User Only
            </span>
        </div>
    </div>

    <!-- Live Status Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Provider AI</span>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" id="badgeProviderCategory"><?= ($activeConfig['provider'] ?? 'gemini') === 'groq' ? 'Groq LPU' : 'Google' ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= ($activeConfig['provider'] ?? 'gemini') === 'groq' ? 'bi-lightning-charge-fill text-warning' : 'bi-stars text-primary' ?> fs-4" id="iconActiveProvider"></i>
                        <h5 class="fw-bold mb-0" id="displayActiveProvider"><?= ($activeConfig['provider'] ?? 'gemini') === 'groq' ? 'Groq Cloud' : 'Google Gemini' ?></h5>
                    </div>
                    <small class="text-success mt-2 d-inline-flex align-items-center gap-1" id="displayProviderProtocol">
                        <i class="bi bi-check-circle-fill"></i> <?= ($activeConfig['provider'] ?? 'gemini') === 'groq' ? 'OpenAI-Compatible' : 'REST API v1beta' ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Model AI Aktif</span>
                        <span class="badge <?= !empty($activeConfig['is_configured']) ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2 py-1" id="badgeModelSource">
                            <?= !empty($activeConfig['is_configured']) ? e(ucfirst($activeConfig['source'] ?? 'Database')) : 'Nonaktif' ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cpu <?= !empty($activeConfig['is_configured']) ? 'text-info' : 'text-secondary' ?> fs-4"></i>
                        <h5 class="fw-bold mb-0 text-truncate font-monospace" id="displayActiveModel" title="<?= e($activeConfig['model'] ?? 'Belum Diatur') ?>">
                            <?= e($activeConfig['model'] ?? 'Belum Diatur') ?>
                        </h5>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <?= !empty($activeConfig['is_configured']) ? 'Inference engine ready' : 'Memerlukan konfigurasi' ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Status API Key</span>
                        <span class="badge <?= !empty($activeConfig['is_configured']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2 py-1" id="badgeKeyStatus">
                            <?= !empty($activeConfig['is_configured']) ? 'AES-256-GCM' : 'Belum Aktif' ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-key <?= !empty($activeConfig['is_configured']) ? 'text-success' : 'text-secondary' ?> fs-4"></i>
                        <span class="fw-bold font-monospace text-truncate" id="displayMaskedKey" style="font-size: 0.95rem;">
                            <?= e($activeConfig['masked_key'] ?? 'Belum Dikonfigurasi') ?>
                        </span>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <?= !empty($activeConfig['is_configured']) ? '<i class="bi bi-shield-check text-success me-1"></i> Terenkripsi aman' : '<i class="bi bi-dash-circle text-secondary me-1"></i> Belum ada kredensial' ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Terakhir Diperbarui</span>
                        <i class="bi bi-clock-history text-secondary"></i>
                    </div>
                    <div class="fw-bold text-dark text-truncate" id="displayUpdatedAt">
                        <?= !empty($activeConfig['updated_at']) ? date('d M Y, H:i', strtotime($activeConfig['updated_at'])) : 'Bawaan Sistem' ?>
                    </div>
                    <small class="text-muted mt-2 d-block text-truncate" id="displayUpdatedBy">
                        Oleh: <?= e($activeConfig['updated_by'] ?? 'Sistem') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($dbConnected) && !$dbConnected): ?>
        <div class="alert alert-danger rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 p-3 mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong>Koneksi Database Terputus:</strong> Tidak dapat terhubung ke database (<?= e($dbError ?? 'Koneksi gagal') ?>). Konfigurasi tidak dapat disimpan sampai database aktif kembali.
            </div>
        </div>
    <?php endif; ?>

    <!-- Alerts Container -->
    <div id="alertContainer"></div>

    <!-- Main Configuration Card -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-gear-wide-connected text-primary fs-5"></i>
                        <h5 class="fw-bold mb-0">Form Konfigurasi AI Assistant (Gemini / Groq)</h5>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 font-monospace small">
                        Atomic Activation
                    </span>
                </div>

                <div class="card-body p-4">
                    <form id="aiConfigForm" autocomplete="off">
                        <?= Csrf::getField() ?>

                        <!-- 1. Pilihan Provider AI -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark mb-2">1. Pilih Provider AI <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check provider-radio" name="provider" id="providerGemini" value="gemini" <?= ($activeConfig['provider'] ?? 'gemini') === 'gemini' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary w-100 p-3 rounded-4 text-start d-flex align-items-center gap-3" for="providerGemini">
                                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 fs-4 d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="bi bi-stars"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">Google Gemini</div>
                                            <small class="text-muted d-block" style="font-size: 0.8rem;">REST API Google AI Studio</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-sm-6">
                                    <input type="radio" class="btn-check provider-radio" name="provider" id="providerGroq" value="groq" <?= ($activeConfig['provider'] ?? '') === 'groq' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary w-100 p-3 rounded-4 text-start d-flex align-items-center gap-3" for="providerGroq">
                                        <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-3 fs-4 d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            <i class="bi bi-lightning-charge-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">Groq Cloud (LPU)</div>
                                            <small class="text-muted d-block" style="font-size: 0.8rem;">Inference Cepat & OpenAI-Compatible</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Input API Key -->
                        <div class="mb-4">
                            <label for="geminiApiKey" class="form-label fw-semibold text-dark d-flex align-items-center justify-content-between">
                                <span id="apiKeyLabel">2. API Key Google Gemini <span class="text-danger">*</span></span>
                                <span class="badge bg-success-subtle text-success small rounded-pill" id="keyConfiguredBadge">
                                    <i class="bi bi-check-circle me-1"></i> API Key Aktif
                                </span>
                            </label>
                            
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-key-fill"></i>
                                </span>
                                <input type="password" 
                                       class="form-control border-start-0 border-end-0 px-2 font-monospace" 
                                       id="geminiApiKey" 
                                       name="api_key" 
                                       placeholder="Masukkan API Key" 
                                       autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleApiKeyVisibility" title="Tampilkan / Sembunyikan API Key">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text small mt-2 text-muted">
                                <i class="bi bi-shield-lock me-1 text-primary"></i>
                                <strong>Keamanan:</strong> Kunci disimpan dengan enkripsi <em>AES-256-GCM</em> di database. Kunci mentah tidak pernah disimpan di .env secara otomatis atau dikirim kembali ke browser.
                            </div>
                        </div>

                        <!-- 3. Model Selection & Fetch Button -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label for="modelSelect" class="form-label fw-semibold text-dark mb-0">
                                    <span id="modelSelectLabel">3. Pilihan Model Gemini <span class="text-danger">*</span></span>
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-2" id="loadModelsBtn">
                                    <span class="spinner-border spinner-border-sm d-none" id="loadModelsSpinner" role="status"></span>
                                    <i class="bi bi-arrow-repeat" id="loadModelsIcon"></i>
                                    <span id="loadModelsBtnText">Muat Ulang dari Google API</span>
                                </button>
                            </div>

                            <select class="form-select form-select-lg font-monospace fs-6" id="modelSelect" name="model" required>
                                <?php if (!empty($activeConfig['model']) && !empty($activeConfig['is_configured'])): ?>
                                    <option value="<?= e($activeConfig['model']) ?>" selected>
                                        <?= e($activeConfig['model']) ?> (Model Aktif Saat Ini)
                                    </option>
                                <?php else: ?>
                                    <option value="" disabled selected>-- Pilih Model (Gunakan tombol Muat Ulang) --</option>
                                <?php endif; ?>
                            </select>

                            <div class="form-text small mt-2 text-muted" id="modelCountNotice">
                                <i class="bi bi-info-circle me-1"></i>
                                Pilihlah model yang didukung oleh API Key Anda. Hanya model yang lulus <em>Uji Koneksi</em> yang dapat diaktifkan.
                            </div>
                        </div>

                        <!-- Model Details Card (Hidden until model selected) -->
                        <div class="p-3 bg-light rounded-3 mb-4 d-none" id="modelDetailBox">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold text-primary" id="modelDetailName">-</span>
                                <span class="badge bg-secondary-subtle text-secondary font-monospace" id="modelDetailLimit">-</span>
                            </div>
                            <p class="text-muted small mb-0" id="modelDetailDesc">-</p>
                        </div>

                        <hr class="my-4">

                        <!-- 3. Test and Save Action Buttons -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <button type="button" class="btn btn-warning rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 text-dark" id="testConnectionBtn">
                                <span class="spinner-border spinner-border-sm d-none" id="testSpinner" role="status"></span>
                                <i class="bi bi-lightning-charge-fill" id="testIcon"></i>
                                <span>Uji Koneksi</span>
                            </button>

                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" id="saveConfigBtn">
                                <span class="spinner-border spinner-border-sm d-none" id="saveSpinner" role="status"></span>
                                <i class="bi bi-check-circle-fill" id="saveIcon"></i>
                                <span>Simpan & Aktifkan Konfigurasi</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Test Connection Result Box -->
            <div class="card border-0 shadow-sm rounded-4 mt-4 d-none" id="testResultCard">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-activity text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0">Hasil Uji Verifikasi Koneksi</h6>
                    </div>
                    <span class="badge rounded-pill px-3 py-1" id="testResultBadge">Pengujian</span>
                </div>
                <div class="card-body p-4" id="testResultBody">
                    <!-- Populated via JS -->
                </div>
            </div>
        </div>

        <!-- Sidebar Info & Audit Trail -->
        <div class="col-lg-4">
            <!-- Security Guidance Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-shield-check text-success me-2"></i>Standar Keamanan Konfigurasi
                    </h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0 small text-secondary d-flex flex-column gap-2">
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success fs-6 flex-shrink-0"></i>
                            <span><strong>Enkripsi Terisolasi:</strong> API Key dienkripsi dengan AES-256-GCM sebelum masuk ke database.</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success fs-6 flex-shrink-0"></i>
                            <span><strong>Zero-Leakage:</strong> API Key tidak pernah dikirim ke browser atau dicetak dalam log sistem.</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success fs-6 flex-shrink-0"></i>
                            <span><strong>Atomic Activation:</strong> Konfigurasi lama tetap dipertahankan jika pengujian konfigurasi baru gagal.</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success fs-6 flex-shrink-0"></i>
                            <span><strong>Zero-Restart Sync:</strong> PHP dan layanan microservice Node.js langsung tersinkronisasi otomatis.</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success fs-6 flex-shrink-0"></i>
                            <span><strong>SSL Verifikasi Aktif:</strong> Seluruh komunikasi REST dengan Gemini API wajib memvalidasi sertifikat SSL.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Recent History / Audit Trail Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-clock-history text-secondary me-2"></i>Riwayat Perubahan
                    </h6>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">Audit Log</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($history)): ?>
                        <div class="list-group list-group-flush rounded-bottom-4" id="historyList">
                            <?php foreach ($history as $idx => $item): ?>
                                <div class="list-group-item px-4 py-3 border-0 border-bottom">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div>
                                            <span class="badge bg-<?= ($item['provider'] ?? 'gemini') === 'groq' ? 'warning text-dark' : 'primary-subtle text-primary' ?> me-1 text-uppercase" style="font-size: 0.7rem;">
                                                <?= e($item['provider'] ?? 'gemini') ?>
                                            </span>
                                            <span class="font-monospace fw-semibold text-dark small">
                                                <?= e($item['active_model']) ?>
                                            </span>
                                        </div>
                                        <?php if ((int)$item['is_active'] === 1): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fs-xs">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted rounded-pill px-2 py-1 fs-xs">Arsip</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between text-muted fs-xs">
                                        <span><i class="bi bi-person me-1"></i><?= e($item['updated_by_name'] ?? $item['updated_by_username'] ?? 'Admin') ?></span>
                                        <span><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted small">
                            <i class="bi bi-journal-text fs-3 d-block mb-2 text-secondary"></i>
                            Belum ada riwayat perubahan yang tercatat di database.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Konfigurasi WhatsApp (Fonnte) -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-whatsapp text-success fs-5"></i>
                    <h5 class="fw-bold mb-0">Konfigurasi WhatsApp (Fonnte)</h5>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                    Token API
                </span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <label class="form-label fw-semibold text-dark mb-1">Token API Fonnte Tersimpan</label>
                        <div class="font-monospace text-muted" id="fonnteMaskedToken">
                            <?= e($fonnteTokenMasked ?? 'Belum Dikonfigurasi') ?>
                        </div>
                    </div>
                    <span class="badge <?= !empty($fonnteTokenConfigured) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-3 py-1" id="fonnteTokenStatus">
                        <?= !empty($fonnteTokenConfigured) ? 'Tersimpan' : 'Belum Dikonfigurasi' ?>
                    </span>
                </div>

                <form id="fonnteTokenForm" autocomplete="off">
                    <?= Csrf::getField() ?>
                    <div class="mb-3">
                        <label for="fonnteToken" class="form-label fw-semibold text-dark">
                            Token API Fonnte Baru
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-key-fill"></i>
                            </span>
                            <input type="password"
                                   class="form-control border-start-0 px-2 font-monospace"
                                   id="fonnteToken"
                                   name="fonnte_token"
                                   placeholder="Masukkan token Fonnte baru"
                                   autocomplete="new-password">
                        </div>
                        <div class="form-text small mt-2 text-muted">
                            <i class="bi bi-shield-lock me-1 text-primary"></i>
                            Token disimpan di konfigurasi aplikasi (.env) dan tidak ditampilkan secara utuh.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" id="saveFonnteTokenBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="saveFonnteTokenSpinner" role="status"></span>
                        <i class="bi bi-check-circle-fill" id="saveFonnteTokenIcon"></i>
                        <span>Simpan Token Fonnte</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.fs-xs { font-size: 0.75rem; }
.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerDetails      = <?= json_encode($providerDetails ?? []) ?>;
    const apiKeyInput          = document.getElementById('geminiApiKey');
    const toggleVisibilityBtn  = document.getElementById('toggleApiKeyVisibility');
    const toggleIcon           = document.getElementById('toggleIcon');
    const loadModelsBtn        = document.getElementById('loadModelsBtn');
    const loadModelsSpinner    = document.getElementById('loadModelsSpinner');
    const loadModelsIcon       = document.getElementById('loadModelsIcon');
    const loadModelsBtnText    = document.getElementById('loadModelsBtnText');
    const modelSelect          = document.getElementById('modelSelect');
    const modelDetailBox       = document.getElementById('modelDetailBox');
    const modelDetailName      = document.getElementById('modelDetailName');
    const modelDetailLimit     = document.getElementById('modelDetailLimit');
    const modelDetailDesc      = document.getElementById('modelDetailDesc');
    const modelCountNotice     = document.getElementById('modelCountNotice');
    const testConnectionBtn    = document.getElementById('testConnectionBtn');
    const testSpinner          = document.getElementById('testSpinner');
    const testIcon             = document.getElementById('testIcon');
    const saveConfigBtn        = document.getElementById('saveConfigBtn');
    const saveSpinner          = document.getElementById('saveSpinner');
    const saveIcon             = document.getElementById('saveIcon');
    const aiConfigForm         = document.getElementById('aiConfigForm');
    const alertContainer       = document.getElementById('alertContainer');
    const testResultCard       = document.getElementById('testResultCard');
    const testResultBadge      = document.getElementById('testResultBadge');
    const testResultBody       = document.getElementById('testResultBody');

    const displayActiveProvider   = document.getElementById('displayActiveProvider');
    const badgeProviderCategory   = document.getElementById('badgeProviderCategory');
    const displayProviderProtocol = document.getElementById('displayProviderProtocol');
    const iconActiveProvider      = document.getElementById('iconActiveProvider');
    const displayActiveModel      = document.getElementById('displayActiveModel');
    const displayMaskedKey        = document.getElementById('displayMaskedKey');
    const displayUpdatedAt        = document.getElementById('displayUpdatedAt');
    const displayUpdatedBy        = document.getElementById('displayUpdatedBy');
    const badgeModelSource        = document.getElementById('badgeModelSource');

    const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';

    let cachedModels = [];

    function getSelectedProvider() {
        return document.querySelector('input[name="provider"]:checked')?.value || 'gemini';
    }

    function updateProviderUI(provider) {
        const isGroq = (provider === 'groq');
        const details = providerDetails[provider] || {};

        const apiKeyLabel = document.getElementById('apiKeyLabel');
        if (apiKeyLabel) {
            apiKeyLabel.innerHTML = isGroq 
                ? '2. API Key Groq Cloud <span class="text-danger">*</span>' 
                : '2. API Key Google Gemini <span class="text-danger">*</span>';
        }

        const modelSelectLabel = document.getElementById('modelSelectLabel');
        if (modelSelectLabel) {
            modelSelectLabel.innerHTML = isGroq 
                ? '3. Pilihan Model Groq <span class="text-danger">*</span>' 
                : '3. Pilihan Model Gemini <span class="text-danger">*</span>';
        }

        if (loadModelsBtnText) {
            loadModelsBtnText.textContent = isGroq 
                ? 'Muat Ulang dari Groq API' 
                : 'Muat Ulang dari Google API';
        }

        const badge = document.getElementById('keyConfiguredBadge');
        if (details.has_saved && details.masked_key && details.masked_key !== 'Belum Dikonfigurasi') {
            badge.className = 'badge bg-success-subtle text-success small rounded-pill';
            badge.innerHTML = `<i class="bi bi-check-circle me-1"></i> Tersimpan: ${escapeHtml(details.masked_key)}`;
            apiKeyInput.placeholder = `Kosongkan jika ingin menggunakan kunci ${isGroq ? 'Groq' : 'Gemini'} tersimpan (${details.masked_key})`;
        } else {
            badge.className = 'badge bg-secondary-subtle text-secondary small rounded-pill';
            badge.innerHTML = '<i class="bi bi-dash-circle me-1"></i> Belum Dikonfigurasi';
            apiKeyInput.placeholder = isGroq ? 'Masukkan API Key Groq baru (contoh: gsk_...)' : 'Masukkan API Key Gemini baru (contoh: AIzaSy...)';
        }

        apiKeyInput.value = '';
        cachedModels = [];
        modelSelect.innerHTML = (details.model && details.model !== '') 
            ? `<option value="${escapeHtml(details.model)}" selected>${escapeHtml(details.model)} (Model Tersimpan)</option>`
            : `<option value="" disabled selected>-- Klik "Muat Ulang dari ${isGroq ? 'Groq' : 'Google'} API" --</option>`;

        modelCountNotice.innerHTML = `<i class="bi bi-info-circle me-1"></i> Pilihlah model ${isGroq ? 'Groq' : 'Gemini'} yang didukung. Hanya model yang lulus <em>Uji Koneksi</em> yang dapat diaktifkan.`;
        modelDetailBox.classList.add('d-none');
    }

    // Provider radio switch
    document.querySelectorAll('.provider-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            updateProviderUI(this.value);
        });
    });

    // Initialize UI on load
    updateProviderUI(getSelectedProvider());

    // 1. Toggle API Key Visibility
    toggleVisibilityBtn.addEventListener('click', function() {
        if (apiKeyInput.type === 'password') {
            apiKeyInput.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            apiKeyInput.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    });

    // 2. Load Models dynamically from selected API
    loadModelsBtn.addEventListener('click', function() {
        const provider = getSelectedProvider();
        const key = apiKeyInput.value.trim();

        setLoading(loadModelsBtn, loadModelsSpinner, loadModelsIcon, true);
        clearAlerts();

        fetch('<?= url("admin/ai-config/models") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                _csrf_token: csrfToken,
                provider: provider,
                api_key: key
            })
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            setLoading(loadModelsBtn, loadModelsSpinner, loadModelsIcon, false);

            if (status === 200 && body.success) {
                cachedModels = body.models || [];
                populateModelDropdown(cachedModels, provider);
                showAlert('success', `Berhasil memuat ${body.count} model ${provider === 'groq' ? 'Groq' : 'Gemini'} dari API resmi.`);
            } else {
                showAlert('danger', body.error || `Gagal memuat model dari ${provider === 'groq' ? 'Groq' : 'Google Gemini'} API.`);
            }
        })
        .catch(err => {
            setLoading(loadModelsBtn, loadModelsSpinner, loadModelsIcon, false);
            showAlert('danger', `Terjadi kesalahan jaringan saat memuat daftar model ${provider === 'groq' ? 'Groq' : 'Gemini'}.`);
        });
    });

    // Populate dropdown with models
    function populateModelDropdown(models, provider) {
        const currentSelected = modelSelect.value;
        modelSelect.innerHTML = '';

        if (!models || models.length === 0) {
            modelSelect.innerHTML = `<option value="" disabled>Tidak ada model ${provider === 'groq' ? 'Groq' : 'Gemini'} yang tersedia untuk API Key ini.</option>`;
            return;
        }

        let hasSelected = false;

        models.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = `${m.displayName || m.id}`;
            if (m.id === currentSelected || (!hasSelected && (m.id === 'llama-3.3-70b-versatile' || m.id === 'mixtral-8x7b-32768' || m.id === 'gemini-3.8-flash'))) {
                opt.selected = true;
                hasSelected = true;
                updateModelDetail(m);
            }
            modelSelect.appendChild(opt);
        });

        if (!hasSelected && models.length > 0) {
            modelSelect.options[0].selected = true;
            updateModelDetail(models[0]);
        }

        modelCountNotice.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> Terhubung ke ${provider === 'groq' ? 'Groq' : 'Gemini'} API: <strong>${models.length} model</strong> tersedia untuk digunakan.`;
    }

    // Change model select
    modelSelect.addEventListener('change', function() {
        const selectedId = this.value;
        const found = cachedModels.find(m => m.id === selectedId);
        if (found) {
            updateModelDetail(found);
        } else {
            modelDetailBox.classList.add('d-none');
        }
    });

    function updateModelDetail(model) {
        if (!model) return;
        modelDetailName.textContent = model.displayName || model.id;
        const tokenLimit = model.contextWindow ? `Konteks: ${Number(model.contextWindow).toLocaleString()} tokens` : (model.inputTokenLimit ? `Input: ${Number(model.inputTokenLimit).toLocaleString()} tokens` : '');
        modelDetailLimit.textContent = tokenLimit;
        modelDetailDesc.textContent = model.description || 'Model untuk pemrosesan teks dan penalaran konteks keuangan.';
        modelDetailBox.classList.remove('d-none');
    }

    // 3. Test Connection Button
    testConnectionBtn.addEventListener('click', function() {
        const provider = getSelectedProvider();
        const key = apiKeyInput.value.trim();
        const model = modelSelect.value.trim();

        if (!model) {
            showAlert('warning', `Silakan pilih model ${provider === 'groq' ? 'Groq' : 'Gemini'} terlebih dahulu.`);
            return;
        }

        setLoading(testConnectionBtn, testSpinner, testIcon, true);
        clearAlerts();
        showTestRunning(provider);

        fetch('<?= url("admin/ai-config/test") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                _csrf_token: csrfToken,
                provider: provider,
                api_key: key,
                model: model
            })
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            setLoading(testConnectionBtn, testSpinner, testIcon, false);

            if (status === 200 && body.success) {
                showTestSuccess(body);
                showAlert('success', `Uji koneksi ${provider === 'groq' ? 'Groq' : 'Gemini'} API berhasil! Seluruh tahap validasi terpenuhi.`);
            } else {
                showTestFailed(body.error || 'Uji koneksi gagal.');
                showAlert('danger', body.error || `Uji koneksi ke ${provider === 'groq' ? 'Groq' : 'Google Gemini'} API gagal.`);
            }
        })
        .catch(err => {
            setLoading(testConnectionBtn, testSpinner, testIcon, false);
            showTestFailed(`Terjadi kesalahan jaringan atau koneksi timeout saat menguji ${provider === 'groq' ? 'Groq' : 'Gemini'} API.`);
            showAlert('danger', `Koneksi ke ${provider === 'groq' ? 'Groq' : 'Gemini'} API gagal atau mengalami timeout.`);
        });
    });

    // 4. Save & Activate Form Submission
    aiConfigForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const provider = getSelectedProvider();
        const key = apiKeyInput.value.trim();
        const model = modelSelect.value.trim();

        if (!model) {
            showAlert('warning', `Model ${provider === 'groq' ? 'Groq' : 'Gemini'} wajib dipilih.`);
            return;
        }

        setLoading(saveConfigBtn, saveSpinner, saveIcon, true);
        clearAlerts();

        fetch('<?= url("admin/ai-config/save") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                _csrf_token: csrfToken,
                provider: provider,
                api_key: key,
                model: model
            })
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            setLoading(saveConfigBtn, saveSpinner, saveIcon, false);

            if (status === 200 && body.success) {
                const isGroq = (body.provider === 'groq');

                // Update live header cards
                if (displayActiveProvider) displayActiveProvider.textContent = isGroq ? 'Groq Cloud' : 'Google Gemini';
                if (badgeProviderCategory) badgeProviderCategory.textContent = isGroq ? 'Groq LPU' : 'Google';
                if (displayProviderProtocol) {
                    displayProviderProtocol.innerHTML = isGroq 
                        ? '<i class="bi bi-check-circle-fill"></i> OpenAI-Compatible' 
                        : '<i class="bi bi-check-circle-fill"></i> REST API v1beta';
                }
                if (iconActiveProvider) {
                    iconActiveProvider.className = isGroq 
                        ? 'bi bi-lightning-charge-fill text-warning fs-4' 
                        : 'bi bi-stars text-primary fs-4';
                }

                displayActiveModel.textContent = body.active_model;
                displayActiveModel.title = body.active_model;
                displayMaskedKey.textContent = body.masked_key;
                displayUpdatedAt.textContent = 'Baru saja';
                displayUpdatedBy.textContent = 'Oleh: ' + body.updated_by;
                badgeModelSource.textContent = 'Database';

                // Update providerDetails cache
                providerDetails[body.provider] = {
                    has_saved: true,
                    masked_key: body.masked_key,
                    model: body.active_model,
                    is_active: true
                };

                // Clear input password for security
                apiKeyInput.value = '';
                updateProviderUI(body.provider);

                showAlert('success', body.message);

                // Add item to history list dynamically
                prependHistoryItem(body.provider, body.active_model, body.updated_by);
            } else {
                showAlert('danger', body.error || 'Gagal menyimpan dan mengaktifkan konfigurasi.');
            }
        })
        .catch(err => {
            setLoading(saveConfigBtn, saveSpinner, saveIcon, false);
            showAlert('danger', 'Terjadi kesalahan sistem saat menyimpan konfigurasi.');
        });
    });

    // 5. Save Fonnte Token
    const fonnteTokenForm = document.getElementById('fonnteTokenForm');
    const fonnteTokenInput = document.getElementById('fonnteToken');
    const saveFonnteTokenBtn = document.getElementById('saveFonnteTokenBtn');
    const saveFonnteTokenSpinner = document.getElementById('saveFonnteTokenSpinner');
    const saveFonnteTokenIcon = document.getElementById('saveFonnteTokenIcon');
    const fonnteMaskedToken = document.getElementById('fonnteMaskedToken');
    const fonnteTokenStatus = document.getElementById('fonnteTokenStatus');

    if (fonnteTokenForm) {
        fonnteTokenForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const token = fonnteTokenInput.value.trim();
            if (!token) {
                showAlert('warning', 'Token API Fonnte wajib diisi.');
                return;
            }

            setLoading(saveFonnteTokenBtn, saveFonnteTokenSpinner, saveFonnteTokenIcon, true);
            clearAlerts();

            fetch('<?= url("admin/ai-config/fonnte-token") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    _csrf_token: csrfToken,
                    fonnte_token: token
                })
            })
            .then(response => response.json().then(data => ({ status: response.status, body: data })))
            .then(({ status, body }) => {
                setLoading(saveFonnteTokenBtn, saveFonnteTokenSpinner, saveFonnteTokenIcon, false);

                if (status === 200 && body.success) {
                    if (fonnteMaskedToken) fonnteMaskedToken.textContent = body.masked_token;
                    if (fonnteTokenStatus) {
                        fonnteTokenStatus.className = 'badge bg-success-subtle text-success rounded-pill px-3 py-1';
                        fonnteTokenStatus.textContent = 'Tersimpan';
                    }
                    fonnteTokenInput.value = '';
                    showAlert('success', body.message);
                } else {
                    showAlert('danger', body.error || 'Gagal menyimpan token Fonnte.');
                }
            })
            .catch(err => {
                setLoading(saveFonnteTokenBtn, saveFonnteTokenSpinner, saveFonnteTokenIcon, false);
                showAlert('danger', 'Terjadi kesalahan jaringan saat menyimpan token Fonnte.');
            });
        });
    }

    // Helpers
    function setLoading(btn, spinner, icon, isLoading) {
        if (isLoading) {
            btn.disabled = true;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
        } else {
            btn.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
        }
    }

    function showAlert(type, message) {
        const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        const html = `
            <div class="alert alert-${type} alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 p-3 mb-4" role="alert">
                <i class="bi ${icon} fs-4 flex-shrink-0"></i>
                <div class="flex-grow-1">${escapeHtml(message)}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = html;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearAlerts() {
        alertContainer.innerHTML = '';
    }

    function showTestRunning(provider) {
        testResultCard.classList.remove('d-none');
        testResultBadge.className = 'badge bg-warning-subtle text-warning rounded-pill px-3 py-1';
        testResultBadge.textContent = 'Sedang Menguji...';
        testResultBody.innerHTML = `
            <div class="d-flex align-items-center gap-3 text-muted">
                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                <span>Menghubungi ${provider === 'groq' ? 'Groq Cloud API' : 'Google Gemini API'} & mengirim permintaan verifikasi...</span>
            </div>
        `;
    }

    function showTestSuccess(data) {
        testResultCard.classList.remove('d-none');
        testResultBadge.className = 'badge bg-success-subtle text-success rounded-pill px-3 py-1';
        testResultBadge.textContent = 'Verifikasi Berhasil';
        testResultBody.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3">
                        <small class="text-muted d-block">Model Diuji</small>
                        <span class="fw-bold font-monospace text-primary">${escapeHtml(data.model)}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3">
                        <small class="text-muted d-block">Waktu Respons (Latensi)</small>
                        <span class="fw-bold text-success">${data.latency_ms} ms</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3">
                        <small class="text-muted d-block">Status Autentikasi</small>
                        <span class="fw-bold text-success"><i class="bi bi-shield-check me-1"></i> Terotorisasi</span>
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <label class="small text-muted fw-semibold">Contoh Respons Diterima dari Model:</label>
                    <div class="p-3 bg-success-subtle border border-success-subtle rounded-3 text-dark font-monospace small">
                        "${escapeHtml(data.sample_response || 'OK')}"
                    </div>
                </div>
            </div>
        `;
    }

    function showTestFailed(errorMsg) {
        testResultCard.classList.remove('d-none');
        testResultBadge.className = 'badge bg-danger-subtle text-danger rounded-pill px-3 py-1';
        testResultBadge.textContent = 'Gagal';
        testResultBody.innerHTML = `
            <div class="p-3 bg-danger-subtle border border-danger-subtle rounded-3 text-danger mb-2">
                <div class="fw-bold mb-1"><i class="bi bi-x-circle me-1"></i> Pengujian Koneksi Gagal</div>
                <div>${escapeHtml(errorMsg)}</div>
            </div>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i> <strong>Sesuai prinsip atomic configuration:</strong> Konfigurasi aktif sebelumnya tetap dipertahankan dan AI Assistant tetap berjalan normal.
            </p>
        `;
    }

    function prependHistoryItem(provider, model, user) {
        const historyList = document.getElementById('historyList');
        if (!historyList) return;
        const nowStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

        const isGroq = (provider === 'groq');
        const badgeColor = isGroq ? 'warning text-dark' : 'primary-subtle text-primary';

        const html = `
            <div class="list-group-item px-4 py-3 border-0 border-bottom bg-success-subtle bg-opacity-25">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div>
                        <span class="badge bg-${badgeColor} me-1 text-uppercase" style="font-size: 0.7rem;">${escapeHtml(provider)}</span>
                        <span class="font-monospace fw-semibold text-dark small">${escapeHtml(model)}</span>
                    </div>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fs-xs">Aktif Baru</span>
                </div>
                <div class="d-flex align-items-center justify-content-between text-muted fs-xs">
                    <span><i class="bi bi-person me-1"></i>${escapeHtml(user)}</span>
                    <span><i class="bi bi-calendar3 me-1"></i>${nowStr}</span>
                </div>
            </div>
        `;
        historyList.insertAdjacentHTML('afterbegin', html);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;")
                          .replace(/</g, "&lt;")
                          .replace(/>/g, "&gt;")
                          .replace(/"/g, "&quot;")
                          .replace(/'/g, "&#039;");
    }
});
</script>
