<?php
/**
 * Main Application Layout
 * 
 * Variables available:
 * - $content    : Rendered view content injected by View::render()
 * - $pageTitle  : Page title
 * - $authUser   : Currently logged-in user data
 * - $flashSuccess, $flashError, $flashWarning, $flashInfo : Flash messages
 */
?>
<?php App\Core\View::partial('layouts/header', get_defined_vars()); ?>

<?php App\Core\View::partial('layouts/navbar', get_defined_vars()); ?>

<?php App\Core\View::partial('layouts/sidebar', get_defined_vars()); ?>

<!-- Main Content Wrapper -->
<main class="main-content" id="mainContent">
    <div class="content-wrapper">
        <!-- Flash Messages -->
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show alert-flash" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-danger alert-dismissible fade show alert-flash" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashWarning)): ?>
            <div class="alert alert-warning alert-dismissible fade show alert-flash" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($flashWarning) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashInfo)): ?>
            <div class="alert alert-info alert-dismissible fade show alert-flash" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= e($flashInfo) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Content -->
        <?= $content ?>
    </div>
</main>

<!-- Floating AI Chat Widget -->
<?php if (auth_check()): ?>
    <?php App\Core\View::partial('layouts/ai_chat_widget', get_defined_vars()); ?>
<?php endif; ?>

<?php App\Core\View::partial('layouts/footer', get_defined_vars()); ?>
