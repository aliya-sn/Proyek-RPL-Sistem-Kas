<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-app fixed-top">
    <div class="container-fluid">
        <!-- Sidebar Toggle Button -->
        <button class="btn btn-link sidebar-toggle me-2 d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Brand / Breadcrumb -->
        <div class="d-flex align-items-center">
            <span class="navbar-brand-text d-none d-md-inline-block">
                <i class="bi bi-cash-stack me-1 text-primary-app"></i>
                <?= e(config('app.name', 'Sistem Kas')) ?>
            </span>
        </div>

        <!-- Right Side Actions -->
        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Date Display -->
            <span class="badge bg-glass text-muted-app d-none d-md-inline-flex align-items-center gap-1">
                <i class="bi bi-calendar3"></i>
                <?= date('d M Y') ?>
            </span>

            <?php if (auth_check()): ?>
                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-user-dropdown dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-circle">
                            <?= strtoupper(substr(auth()['full_name'] ?? auth()['nama'] ?? auth()['username'] ?? 'U', 0, 1)) ?>
                        </span>
                        <span class="d-none d-md-inline ms-1"><?= e(auth()['full_name'] ?? auth()['nama'] ?? auth()['username']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-app" aria-labelledby="userDropdown">
                        <li class="dropdown-header">
                            <div class="fw-semibold"><?= e(auth()['full_name'] ?? auth()['nama'] ?? auth()['username'] ?? '') ?></div>
                            <small class="text-muted"><?= e(ucwords(str_replace('_', ' ', auth()['role'] ?? ''))) ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= url('dashboard') ?>">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= url('change-password') ?>">
                                <i class="bi bi-key me-2"></i>Ubah Password
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= url('logout') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
