<?php

/**
 * SKRIP UJI COBA PENGIRIMAN SATU PESAN WHATSAPP NYATA (FONNTE GATEWAY)
 * 
 * Penggunaan:
 *   php test_send_real_wa.php
 * atau
 *   php test_send_real_wa.php 0812xxxxxxxx
 *
 * Catatan Keamanan:
 * - Tidak menampilkan token API.
 * - Meminta konfirmasi eksplisit nomor tujuan sebelum mengirim.
 * - Tidak membuat transaksi kas atau mengubah data pembayaran apa pun di database.
 */

require_once __DIR__ . '/vendor/autoload.php';
\App\Core\Env::load(__DIR__ . '/.env');

use App\Services\FonnteWhatsAppService;

echo "======================================================================\n";
echo " TES PENGIRIMAN SATU PESAN NYATA VIA FONNTE WHATSAPP GATEWAY\n";
echo "======================================================================\n\n";

$token = config('whatsapp.token') ?? env('FONNTE_API_TOKEN');

if (empty($token)) {
    echo "❌ ERROR: FONNTE_API_TOKEN belum diisi di file .env.\n";
    echo "   Silakan isi FONNTE_API_TOKEN pada file .env terlebih dahulu.\n\n";
    exit(1);
}

echo "✓ Status Token : Terbaca dari .env (Panjang: " . strlen($token) . " karakter, Dirahasiakan)\n";
echo "✓ Gateway URL  : " . (config('whatsapp.api_url') ?? 'https://api.fonnte.com/send') . "\n";
echo "✓ Keamanan Kas : Tidak ada data transaksi/pembayaran yang dibuat atau diubah.\n\n";

// 1. Ambil nomor tujuan dari argumen CLI atau input interaktif
$targetPhone = $argv[1] ?? null;

if (empty($targetPhone)) {
    echo "Masukkan nomor WhatsApp tujuan (contoh: 081234567890 atau 6281234567890):\n> ";
    $handle = fopen("php://stdin", "r");
    $targetPhone = trim((string)fgets($handle));
}

if (empty($targetPhone)) {
    echo "❌ Pengiriman dibatalkan: Nomor tujuan tidak boleh kosong.\n";
    exit(1);
}

$waService = new FonnteWhatsAppService();
$normalized = $waService->normalizePhoneNumber($targetPhone);

if (!$waService->isValidPhoneNumber($normalized)) {
    echo "❌ Format nomor tidak valid: '{$targetPhone}'.\n";
    echo "   Pastikan nomor diawali dengan 08... atau 628... dan terdiri dari 10-15 digit.\n";
    exit(1);
}

echo "\n----------------------------------------------------------------------\n";
echo "Nomor Asli      : {$targetPhone}\n";
echo "Nomor Tujuan WA : {$normalized} (Format Fonnte)\n";
echo "Isi Pesan       : Pesan uji coba resmi koneksi Sistem Kas ke perangkat WA\n";
echo "----------------------------------------------------------------------\n\n";

// 2. Konfirmasi eksplisit sebelum mengirim
echo "Kirimkan pesan uji coba ini sekarang? (ketik 'y' untuk ya / 'n' untuk batal): ";
$handle = fopen("php://stdin", "r");
$confirm = strtolower(trim((string)fgets($handle)));

if ($confirm !== 'y' && $confirm !== 'yes') {
    echo "\n⏹ Pengiriman DIBATALKAN oleh pengguna. Tidak ada pesan yang dikirim.\n\n";
    exit(0);
}

echo "\nMengirim pesan ke gateway Fonnte...\n";

// 3. Eksekusi pengiriman satu pesan langsung (tanpa transaksi kas)
$result = $waService->sendDirectTestMessage($targetPhone);

echo "\n==================== HASIL RESPON PROVIDER ====================\n";

if ($result['success']) {
    echo "✅ STATUS        : BERHASIL DITERIMA PROVIDER\n";
    echo "   Target        : " . ($result['target'] ?? $normalized) . "\n";
    echo "   Message ID    : " . ($result['message_id'] ?? '—') . "\n";
    echo "   Process State : " . ($result['process'] ?? 'pending') . "\n";
    echo "   Detail        : " . ($result['detail'] ?? 'message in queue') . "\n";
    echo "\n Bukti Respons Sanitasi:\n";
    echo " " . ($result['raw_response'] ?? '-') . "\n";
    echo "\n Silakan periksa aplikasi WhatsApp pada nomor {$targetPhone}.\n";
} else {
    echo "❌ STATUS        : GAGAL DIKIRIM\n";
    echo "   Alasan        : " . ($result['message'] ?? 'Error tidak diketahui') . "\n";
    if (!empty($result['raw_response'])) {
        echo "\n Respons Provider Sanitasi:\n";
        echo " " . $result['raw_response'] . "\n";
    }
}

echo "======================================================================\n\n";
