<?php
/**
 * Login Page View
 * 
 * Variables: $pageTitle, $flashError, $flashInfo, $errors
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login - Sistem Keuangan Kas Berbasis Web dengan AI">
    <title><?= e($pageTitle ?? 'Login') ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="login-page">

    <!-- Animated Background Shapes -->
    <div class="login-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- Card Header / Branding -->
            <div class="login-card-header">
                <div class="login-brand-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
                <h1 class="login-title">Sistem Keuangan Kas</h1>
                <p class="login-subtitle">Berbasis Web dengan AI</p>
            </div>

            <!-- Flash Messages -->
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3 mb-0" role="alert" id="flashAlert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashInfo)): ?>
                <div class="alert alert-info alert-dismissible fade show mx-4 mt-3 mb-0" role="alert" id="flashAlert">
                    <i class="bi bi-info-circle-fill me-2"></i><?= e($flashInfo) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="login-card-body">
                <form method="POST" action="<?= url('login') ?>" id="loginForm" novalidate>
                    <?= csrf_field() ?>

                    <!-- Username -->
                    <div class="form-floating-custom mb-3">
                        <div class="input-group input-group-custom">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <div class="form-floating">
                                <input 
                                    type="text" 
                                    class="form-control <?= !empty($errors['username'] ?? '') ? 'is-invalid' : '' ?>" 
                                    id="username" 
                                    name="username" 
                                    placeholder="Username atau Email" 
                                    value="<?= e(old('username')) ?>"
                                    autocomplete="username"
                                    autofocus
                                    required
                                >
                                <label for="username">Username atau Email</label>
                            </div>
                        </div>
                        <?php if (!empty($errors['username'] ?? '')): ?>
                            <div class="invalid-feedback d-block mt-1">
                                <small><?= e($errors['username']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="form-floating-custom mb-4">
                        <div class="input-group input-group-custom">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <div class="form-floating">
                                <input 
                                    type="password" 
                                    class="form-control <?= !empty($errors['password'] ?? '') ? 'is-invalid' : '' ?>" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Password"
                                    autocomplete="current-password"
                                    required
                                >
                                <label for="password">Password</label>
                            </div>
                            <button class="btn btn-outline-secondary btn-toggle-password" type="button" id="togglePassword" tabindex="-1" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($errors['password'] ?? '')): ?>
                            <div class="invalid-feedback d-block mt-1">
                                <small><?= e($errors['password']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary-app btn-login w-100" id="btnLogin">
                        <span class="btn-login-text">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Sistem
                        </span>
                        <span class="btn-login-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...
                        </span>
                    </button>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="login-card-footer">
                <small class="text-muted">
                    <i class="bi bi-shield-lock me-1"></i>Koneksi aman &bull; 
                    <i class="bi bi-robot me-1"></i>Dilengkapi AI Assistant
                </small>
            </div>
        </div>

        <!-- Bottom Info -->
        <div class="login-bottom-info">
            <small>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?> &mdash; v1.0.0</small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
