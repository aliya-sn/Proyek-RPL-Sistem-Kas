<?php
/**
 * 404 Error Page
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
        }
        .error-container { text-align: center; padding: 2rem; }
        .error-code { font-size: 8rem; font-weight: 800; line-height: 1; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .error-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; }
        .error-desc { color: #94a3b8; max-width: 500px; margin: 0 auto 2rem; }
        .btn-back { background: linear-gradient(135deg, #4f46e5, #6366f1); border: none; padding: 0.75rem 2rem; border-radius: 12px; color: white; font-weight: 600; transition: all 0.3s; }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4); color: white; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Halaman Tidak Ditemukan</h1>
        <p class="error-desc">Maaf, halaman yang Anda cari tidak tersedia atau telah dipindahkan. Periksa kembali URL atau kembali ke halaman utama.</p>
        <a href="<?= function_exists('url') ? url('dashboard') : '/' ?>" class="btn btn-back">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>
    </div>
</body>
</html>
