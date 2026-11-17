<?php
/**
 * Dashboard Index View
 * 
 * Variables: $pageTitle, $user, $stats
 */
$user = $user ?? auth();
$stats = $stats ?? [];
?>

<!-- Dashboard Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-speedometer2 me-2 text-primary-app"></i>Dashboard
        </h2>
        <p class="text-muted mb-0">
            Selamat datang kembali, <strong><?= e($user['full_name'] ?? $user['username'] ?? 'Pengguna') ?></strong>! 
            <span class="badge bg-primary-subtle text-primary ms-1"><?= e(ucwords(str_replace('_', ' ', $user['role'] ?? 'anggota'))) ?></span>
        </p>
    </div>
    <div class="mt-2 mt-md-0">
        <span class="badge bg-glass-dark px-3 py-2">
            <i class="bi bi-calendar-event me-1"></i>
            <?= date('l, d F Y') ?>
        </span>
    </div>
</div>

<!-- Stats Cards Row -->
<div class="row g-3 mb-4">
    <!-- Saldo Kas -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card stat-card-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Saldo Kas</p>
                        <h3 class="stat-value">Rp <?= number_format($stats['saldo_kas'] ?? 0, 0, ',', '.') ?></h3>
                        <small class="stat-change text-muted">
                            <i class="bi bi-info-circle"></i> Data akan tersedia
                        </small>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pemasukan -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card stat-card-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Total Pemasukan</p>
                        <h3 class="stat-value">Rp <?= number_format($stats['total_masuk'] ?? 0, 0, ',', '.') ?></h3>
                        <small class="stat-change text-muted">
                            <i class="bi bi-arrow-up-short text-success"></i> Kas masuk
                        </small>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pengeluaran -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card stat-card-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Total Pengeluaran</p>
                        <h3 class="stat-value">Rp <?= number_format($stats['total_keluar'] ?? 0, 0, ',', '.') ?></h3>
                        <small class="stat-change text-muted">
                            <i class="bi bi-arrow-down-short text-danger"></i> Kas keluar
                        </small>
                    </div>
                    <div class="stat-icon stat-icon-danger">
                        <i class="bi bi-graph-down-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Anggota -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card stat-card-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Total Pengguna</p>
                        <h3 class="stat-value"><?= number_format($stats['total_anggota'] ?? 0, 0, ',', '.') ?></h3>
                        <small class="stat-change text-muted">
                            <i class="bi bi-people"></i> Terdaftar
                        </small>
                    </div>
                    <div class="stat-icon stat-icon-info">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Info Row -->
<div class="row g-3">
    <!-- Quick Actions -->
    <div class="col-12 col-lg-8">
        <div class="card card-app h-100">
            <div class="card-header card-header-app">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning-fill text-warning me-2"></i>Aksi Cepat
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="quick-action-card" title="Modul dalam pengembangan">
                            <div class="quick-action-icon bg-success-subtle text-success">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            <span class="quick-action-label">Kas Masuk</span>
                            <span class="badge bg-secondary-subtle text-secondary mt-1" style="font-size: 0.65rem;">Segera</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="quick-action-card" title="Modul dalam pengembangan">
                            <div class="quick-action-icon bg-danger-subtle text-danger">
                                <i class="bi bi-dash-circle"></i>
                            </div>
                            <span class="quick-action-label">Kas Keluar</span>
                            <span class="badge bg-secondary-subtle text-secondary mt-1" style="font-size: 0.65rem;">Segera</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="quick-action-card" title="Modul dalam pengembangan">
                            <div class="quick-action-icon bg-primary-subtle text-primary">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <span class="quick-action-label">Tambah Anggota</span>
                            <span class="badge bg-secondary-subtle text-secondary mt-1" style="font-size: 0.65rem;">Segera</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="quick-action-card" title="Modul dalam pengembangan">
                            <div class="quick-action-icon bg-info-subtle text-info">
                                <i class="bi bi-robot"></i>
                            </div>
                            <span class="quick-action-label">AI Assistant</span>
                            <span class="badge bg-info-subtle text-info mt-1" style="font-size: 0.65rem;">AI</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Info -->
    <div class="col-12 col-lg-4">
        <div class="card card-app h-100">
            <div class="card-header card-header-app">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle text-primary-app me-2"></i>Info Sistem
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush system-info-list">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted"><i class="bi bi-app me-2"></i>Versi Aplikasi</span>
                        <span class="badge bg-primary-subtle text-primary">v1.0.0</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted"><i class="bi bi-gear me-2"></i>Environment</span>
                        <span class="badge bg-warning-subtle text-warning"><?= e(ucfirst((string) config('app.env', 'production'))) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted"><i class="bi bi-robot me-2"></i>AI Status</span>
                        <span class="badge bg-<?= !empty(env('AI_API_KEY')) ? 'success' : 'secondary' ?>-subtle text-<?= !empty(env('AI_API_KEY')) ? 'success' : 'secondary' ?>">
                            <?= e($stats['ai_ready'] ?? 'Siap Konfigurasi') ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted"><i class="bi bi-clock me-2"></i>Login Terakhir</span>
                        <span class="small"><?= !empty($user['last_login_at']) ? date('d/m/y H:i', strtotime($user['last_login_at'])) : '-' ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
