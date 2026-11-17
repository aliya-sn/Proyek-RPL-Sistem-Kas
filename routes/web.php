<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;

// ==============================================================================
// PUBLIC & GUEST ROUTES
// ==============================================================================

Router::get('/', function() {
    redirect('/login');
});

Router::get('/login', [AuthController::class, 'showLogin'], ['guest'])->name('login');
Router::post('/login', [AuthController::class, 'login'], ['guest'])->name('login.post');

// ==============================================================================
// AUTHENTICATED ROUTES (all roles)
// ==============================================================================

Router::group(['middleware' => ['auth']], function() {
    // Generic dashboard — redirector based on role
    Router::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Logout
    Router::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Router::post('/logout', [AuthController::class, 'logout'])->name('logout.post');

    // Ubah Password (semua role terautentikasi)
    Router::get('/change-password', [AuthController::class, 'showChangePassword'])->name('change_password');
    Router::post('/change-password', [AuthController::class, 'updatePassword'])->name('change_password.post');

    // Floating AI Chat Widget universal endpoint (all roles)
    Router::post('/ai-assistant/chat', [\App\Controllers\AiAssistantController::class, 'chat'])->name('ai.chat');
});

// ==============================================================================
// SUPER USER ROUTES — administrasi penuh
// ==============================================================================

Router::group(['prefix' => 'admin', 'middleware' => ['auth', 'role:super_user']], function() {
    Router::get('/dashboard', [DashboardController::class, 'superUserDashboard'])->name('admin.dashboard');
    
    // Manajemen Anggota
    Router::get('/anggota', [\App\Controllers\AnggotaController::class, 'index'])->name('admin.anggota.index');
    Router::get('/anggota/create', [\App\Controllers\AnggotaController::class, 'create'])->name('admin.anggota.create');
    Router::post('/anggota', [\App\Controllers\AnggotaController::class, 'store'])->name('admin.anggota.store');
    Router::get('/anggota/{id}', [\App\Controllers\AnggotaController::class, 'show'])->name('admin.anggota.show');
    Router::get('/anggota/{id}/edit', [\App\Controllers\AnggotaController::class, 'edit'])->name('admin.anggota.edit');
    Router::post('/anggota/{id}', [\App\Controllers\AnggotaController::class, 'update'])->name('admin.anggota.update'); // using POST instead of PUT for simple form submission
    Router::post('/anggota/{id}/deactivate', [\App\Controllers\AnggotaController::class, 'deactivate'])->name('admin.anggota.deactivate');

    // Manajemen Transaksi Kas
    Router::get('/transaksi', [\App\Controllers\TransaksiKasController::class, 'index'])->name('admin.transaksi.index');
    Router::get('/transaksi/create', [\App\Controllers\TransaksiKasController::class, 'create'])->name('admin.transaksi.create');
    Router::post('/transaksi', [\App\Controllers\TransaksiKasController::class, 'store'])->name('admin.transaksi.store');
    Router::get('/transaksi/{id}', [\App\Controllers\TransaksiKasController::class, 'show'])->name('admin.transaksi.show');
    Router::get('/transaksi/{id}/edit', [\App\Controllers\TransaksiKasController::class, 'edit'])->name('admin.transaksi.edit');
    Router::post('/transaksi/{id}', [\App\Controllers\TransaksiKasController::class, 'update'])->name('admin.transaksi.update');
    Router::post('/transaksi/{id}/void', [\App\Controllers\TransaksiKasController::class, 'void'])->name('admin.transaksi.void');

    // Manajemen Pembayaran Kas Anggota
    Router::get('/pembayaran', [\App\Controllers\PembayaranKasController::class, 'index'])->name('admin.pembayaran.index');
    Router::get('/pembayaran/create', [\App\Controllers\PembayaranKasController::class, 'create'])->name('admin.pembayaran.create');
    Router::post('/pembayaran', [\App\Controllers\PembayaranKasController::class, 'store'])->name('admin.pembayaran.store');
    Router::get('/pembayaran/{id}', [\App\Controllers\PembayaranKasController::class, 'show'])->name('admin.pembayaran.show');
    Router::get('/pembayaran/{id}/edit', [\App\Controllers\PembayaranKasController::class, 'edit'])->name('admin.pembayaran.edit');
    Router::post('/pembayaran/{id}', [\App\Controllers\PembayaranKasController::class, 'update'])->name('admin.pembayaran.update');

    // Laporan Keuangan (Super User only)
    Router::get('/laporan', [\App\Controllers\LaporanKeuanganController::class, 'index'])->name('admin.laporan.index');
    Router::get('/laporan/pemasukan', [\App\Controllers\LaporanKeuanganController::class, 'pemasukan'])->name('admin.laporan.pemasukan');
    Router::get('/laporan/pengeluaran', [\App\Controllers\LaporanKeuanganController::class, 'pengeluaran'])->name('admin.laporan.pengeluaran');
    Router::get('/laporan/arus-kas', [\App\Controllers\LaporanKeuanganController::class, 'arusKas'])->name('admin.laporan.arus_kas');
    Router::get('/laporan/pembayaran', [\App\Controllers\LaporanKeuanganController::class, 'pembayaran'])->name('admin.laporan.pembayaran');
    Router::get('/laporan/tunggakan', [\App\Controllers\LaporanKeuanganController::class, 'tunggakan'])->name('admin.laporan.tunggakan');

    // AI Financial Assistant (Super User)
    Router::get('/ai-assistant', [\App\Controllers\AiAssistantController::class, 'index'])->name('admin.ai.index');
    Router::post('/ai-assistant/ask', [\App\Controllers\AiAssistantController::class, 'ask'])->name('admin.ai.ask');

    // AI Configuration Management (Super User only)
    Router::get('/ai-config', [\App\Controllers\AiConfigController::class, 'index'])->name('admin.ai_config.index');
    Router::post('/ai-config/models', [\App\Controllers\AiConfigController::class, 'loadModels'])->name('admin.ai_config.models');
    Router::post('/ai-config/test', [\App\Controllers\AiConfigController::class, 'testConnection'])->name('admin.ai_config.test');
    Router::post('/ai-config/save', [\App\Controllers\AiConfigController::class, 'saveConfig'])->name('admin.ai_config.save');
    Router::post('/ai-config/reset', [\App\Controllers\AiConfigController::class, 'resetConfig'])->name('admin.ai_config.reset');
    Router::post('/ai-config/fonnte-token', [\App\Controllers\AiConfigController::class, 'saveFonnteToken'])->name('admin.ai_config.fonnte_token');


    // Notifikasi WhatsApp (Fonnte API)
    Router::get('/notifikasi', [\App\Controllers\NotifikasiWhatsAppController::class, 'index'])->name('admin.notifikasi.index');
    Router::get('/notifikasi/device-status', [\App\Controllers\NotifikasiWhatsAppController::class, 'checkDevice'])->name('admin.notifikasi.device_status');
    Router::get('/notifikasi/{id}', [\App\Controllers\NotifikasiWhatsAppController::class, 'show'])->name('admin.notifikasi.show');
    Router::post('/notifikasi/{id}/resend', [\App\Controllers\NotifikasiWhatsAppController::class, 'resend'])->name('admin.notifikasi.resend');
    Router::post('/notifikasi/send-pengingat', [\App\Controllers\NotifikasiWhatsAppController::class, 'sendReminder'])->name('admin.notifikasi.send_reminder');
    Router::post('/notifikasi/bulk-send-pengingat', [\App\Controllers\NotifikasiWhatsAppController::class, 'bulkSendReminder'])->name('admin.notifikasi.bulk_send_reminder');
});

// ==============================================================================
// ANGGOTA ROUTES — akses terbatas pada data miliknya
// ==============================================================================

Router::group(['prefix' => 'anggota', 'middleware' => ['auth', 'role:anggota']], function() {
    Router::get('/dashboard', [DashboardController::class, 'anggotaDashboard'])->name('anggota.dashboard');
    
    // Lihat status pembayaran sendiri
    Router::get('/pembayaran', [\App\Controllers\AnggotaPembayaranController::class, 'index'])->name('anggota.pembayaran.index');

    // AI Financial Assistant (Anggota)
    Router::get('/ai-assistant', [\App\Controllers\AiAssistantController::class, 'anggotaIndex'])->name('anggota.ai.index');
    Router::post('/ai-assistant/ask', [\App\Controllers\AiAssistantController::class, 'anggotaAsk'])->name('anggota.ai.ask');
});

// ==============================================================================
// BENDAHARA ROUTES — manajemen keuangan & transaksi (tanpa akses administrasi user)
// ==============================================================================

Router::group(['prefix' => 'bendahara', 'middleware' => ['auth', 'role:bendahara']], function() {
    Router::get('/dashboard', [DashboardController::class, 'bendaharaDashboard'])->name('bendahara.dashboard');

    // Manajemen Transaksi Kas
    Router::get('/transaksi', [\App\Controllers\TransaksiKasController::class, 'index'])->name('bendahara.transaksi.index');
    Router::get('/transaksi/create', [\App\Controllers\TransaksiKasController::class, 'create'])->name('bendahara.transaksi.create');
    Router::post('/transaksi', [\App\Controllers\TransaksiKasController::class, 'store'])->name('bendahara.transaksi.store');
    Router::get('/transaksi/{id}', [\App\Controllers\TransaksiKasController::class, 'show'])->name('bendahara.transaksi.show');
    Router::get('/transaksi/{id}/edit', [\App\Controllers\TransaksiKasController::class, 'edit'])->name('bendahara.transaksi.edit');
    Router::post('/transaksi/{id}', [\App\Controllers\TransaksiKasController::class, 'update'])->name('bendahara.transaksi.update');
    Router::post('/transaksi/{id}/void', [\App\Controllers\TransaksiKasController::class, 'void'])->name('bendahara.transaksi.void');

    // Manajemen Pembayaran Kas Anggota
    Router::get('/pembayaran', [\App\Controllers\PembayaranKasController::class, 'index'])->name('bendahara.pembayaran.index');
    Router::get('/pembayaran/create', [\App\Controllers\PembayaranKasController::class, 'create'])->name('bendahara.pembayaran.create');
    Router::post('/pembayaran', [\App\Controllers\PembayaranKasController::class, 'store'])->name('bendahara.pembayaran.store');
    Router::get('/pembayaran/{id}', [\App\Controllers\PembayaranKasController::class, 'show'])->name('bendahara.pembayaran.show');
    Router::get('/pembayaran/{id}/edit', [\App\Controllers\PembayaranKasController::class, 'edit'])->name('bendahara.pembayaran.edit');
    Router::post('/pembayaran/{id}', [\App\Controllers\PembayaranKasController::class, 'update'])->name('bendahara.pembayaran.update');

    // Laporan Keuangan
    Router::get('/laporan', [\App\Controllers\LaporanKeuanganController::class, 'index'])->name('bendahara.laporan.index');
    Router::get('/laporan/pemasukan', [\App\Controllers\LaporanKeuanganController::class, 'pemasukan'])->name('bendahara.laporan.pemasukan');
    Router::get('/laporan/pengeluaran', [\App\Controllers\LaporanKeuanganController::class, 'pengeluaran'])->name('bendahara.laporan.pengeluaran');
    Router::get('/laporan/arus-kas', [\App\Controllers\LaporanKeuanganController::class, 'arusKas'])->name('bendahara.laporan.arus_kas');
    Router::get('/laporan/pembayaran', [\App\Controllers\LaporanKeuanganController::class, 'pembayaran'])->name('bendahara.laporan.pembayaran');
    Router::get('/laporan/tunggakan', [\App\Controllers\LaporanKeuanganController::class, 'tunggakan'])->name('bendahara.laporan.tunggakan');

    // AI Financial Assistant (Bendahara)
    Router::get('/ai-assistant', [\App\Controllers\AiAssistantController::class, 'bendaharaIndex'])->name('bendahara.ai.index');
    Router::post('/ai-assistant/ask', [\App\Controllers\AiAssistantController::class, 'bendaharaAsk'])->name('bendahara.ai.ask');

    // Notifikasi WhatsApp (Fonnte API)
    Router::get('/notifikasi', [\App\Controllers\NotifikasiWhatsAppController::class, 'index'])->name('bendahara.notifikasi.index');
    Router::get('/notifikasi/device-status', [\App\Controllers\NotifikasiWhatsAppController::class, 'checkDevice'])->name('bendahara.notifikasi.device_status');
    Router::get('/notifikasi/{id}', [\App\Controllers\NotifikasiWhatsAppController::class, 'show'])->name('bendahara.notifikasi.show');
    Router::post('/notifikasi/{id}/resend', [\App\Controllers\NotifikasiWhatsAppController::class, 'resend'])->name('bendahara.notifikasi.resend');
    Router::post('/notifikasi/send-pengingat', [\App\Controllers\NotifikasiWhatsAppController::class, 'sendReminder'])->name('bendahara.notifikasi.send_reminder');
    Router::post('/notifikasi/bulk-send-pengingat', [\App\Controllers\NotifikasiWhatsAppController::class, 'bulkSendReminder'])->name('bendahara.notifikasi.bulk_send_reminder');
});

