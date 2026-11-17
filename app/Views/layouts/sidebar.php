<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <!-- Sidebar Header / Branding -->
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-title">Sistem Kas</span>
                    <span class="brand-subtitle">AI Finance</span>
                </div>
            </div>
            <button class="btn btn-link sidebar-close d-lg-none" id="sidebarClose" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- User Info Card -->
        <?php if (auth_check()): ?>
        <div class="sidebar-user-card">
            <div class="avatar-circle avatar-sm">
                <?= strtoupper(substr(auth()['full_name'] ?? auth()['nama'] ?? auth()['username'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?= e(auth()['full_name'] ?? auth()['nama'] ?? auth()['username']) ?></span>
                <span class="user-role">
                    <i class="bi bi-shield-check"></i>
                    <?= e(ucwords(str_replace('_', ' ', auth()['role'] ?? 'anggota'))) ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-title">MENU UTAMA</span>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'dashboard') !== false) ? 'active' : '' ?>" href="<?= url('dashboard') ?>">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php if (auth_check() && (auth()['role'] ?? '') === 'super_user'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/ai-assistant') !== false) ? 'active' : '' ?>" href="<?= url('admin/ai-assistant') ?>">
                            <i class="bi bi-robot"></i>
                            <span>AI Financial Assistant</span>
                        </a>
                    </li>
                    <?php elseif (auth_check() && (auth()['role'] ?? '') === 'bendahara'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'bendahara/ai-assistant') !== false) ? 'active' : '' ?>" href="<?= url('bendahara/ai-assistant') ?>">
                            <i class="bi bi-robot"></i>
                            <span>AI Financial Assistant</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'anggota/ai-assistant') !== false) ? 'active' : '' ?>" href="<?= url('anggota/ai-assistant') ?>">
                            <i class="bi bi-robot"></i>
                            <span>Asisten Kas AI</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">MANAJEMEN DATA</span>
                <ul class="nav flex-column">
                    <?php if (auth_check() && (auth()['role'] ?? '') === 'super_user'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/anggota') !== false) ? 'active' : '' ?>" href="<?= url('admin/anggota') ?>">
                            <i class="bi bi-people"></i>
                            <span>Anggota</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/transaksi') !== false) ? 'active' : '' ?>" href="<?= url('admin/transaksi') ?>">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Transaksi Kas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/pembayaran') !== false) ? 'active' : '' ?>" href="<?= url('admin/pembayaran') ?>">
                            <i class="bi bi-credit-card"></i>
                            <span>Pembayaran Kas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/notifikasi') !== false) ? 'active' : '' ?>" href="<?= url('admin/notifikasi') ?>">
                            <i class="bi bi-whatsapp"></i>
                            <span>Notifikasi WhatsApp</span>
                        </a>
                    </li>
                    <?php elseif (auth_check() && (auth()['role'] ?? '') === 'bendahara'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'bendahara/transaksi') !== false) ? 'active' : '' ?>" href="<?= url('bendahara/transaksi') ?>">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Transaksi Kas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'bendahara/pembayaran') !== false) ? 'active' : '' ?>" href="<?= url('bendahara/pembayaran') ?>">
                            <i class="bi bi-credit-card"></i>
                            <span>Pembayaran Kas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'bendahara/notifikasi') !== false) ? 'active' : '' ?>" href="<?= url('bendahara/notifikasi') ?>">
                            <i class="bi bi-whatsapp"></i>
                            <span>Notifikasi WhatsApp</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'anggota/pembayaran') !== false) ? 'active' : '' ?>" href="<?= url('anggota/pembayaran') ?>">
                            <i class="bi bi-credit-card"></i>
                            <span>Status Pembayaran Saya</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">LAPORAN KEUANGAN</span>
                <ul class="nav flex-column">
                    <?php if (auth_check() && (auth()['role'] ?? '') === 'super_user'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/laporan') !== false) ? 'active' : '' ?>" href="<?= url('admin/laporan') ?>">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Laporan Keuangan</span>
                        </a>
                    </li>
                    <?php elseif (auth_check() && (auth()['role'] ?? '') === 'bendahara'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'bendahara/laporan') !== false) ? 'active' : '' ?>" href="<?= url('bendahara/laporan') ?>">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span>Laporan Keuangan</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if (auth_check() && (auth()['role'] ?? '') === 'super_user'): ?>
            <div class="nav-section">
                <span class="nav-section-title">ADMINISTRASI</span>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'] ?? '', 'admin/ai-config') !== false) ? 'active' : '' ?>" href="<?= url('admin/ai-config') ?>">
                            <i class="bi bi-sliders2"></i>
                            <span>Konfigurasi AI</span>
                            <span class="badge bg-primary-subtle text-primary ms-auto">Gemini</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" href="#">
                            <i class="bi bi-person-gear"></i>
                            <span>Kelola User</span>
                            <span class="badge bg-secondary-subtle text-secondary ms-auto">Segera</span>
                        </a>

                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" href="#">
                            <i class="bi bi-clock-history"></i>
                            <span>Audit Log</span>
                            <span class="badge bg-secondary-subtle text-secondary ms-auto">Segera</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <a href="<?= url('logout') ?>" class="btn btn-logout-sidebar">
                <i class="bi bi-box-arrow-left"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
