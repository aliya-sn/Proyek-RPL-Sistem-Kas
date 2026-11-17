<?php
/**
 * 500 Error Page
 */
$message = $message ?? 'Terjadi kesalahan pada sistem. Tim kami sedang menanganinya.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Kesalahan Server</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1a0a0a 0%, #2d1b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fecaca;
        }
        .error-container { text-align: center; padding: 2rem; }
        .error-code { font-size: 8rem; font-weight: 800; line-height: 1; background: linear-gradient(135deg, #ef4444, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .error-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; color: #fca5a5; }
        .error-desc { color: #d4a3a3; max-width: 500px; margin: 0 auto 2rem; }
        .btn-back { background: linear-gradient(135deg, #dc2626, #ef4444); border: none; padding: 0.75rem 2rem; border-radius: 12px; color: white; font-weight: 600; transition: all 0.3s; }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4); color: white; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Kesalahan Server Internal</h1>
        <p class="error-desc"><?= htmlspecialchars($message) ?></p>
        <a href="<?= function_exists('url') ? url('dashboard') : '/' ?>" class="btn btn-back">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>
</body>
</html>
