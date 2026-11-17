<?php
/**
 * scripts/diagnose_gemini.php
 * Skrip diagnostik mandiri untuk menguji konfigurasi, autentikasi, & respons Google Gemini API.
 * Sesuai instruksi:
 * - Tidak mengubah kode chatbot, database, alur utama, maupun file .env.
 * - Tidak mencetak/menampilkan nilai API Key sama sekali (hanya status TERISI/KOSONG).
 * - Menguji autentikasi secara terpisah dari respons model.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\AIService;

// 1. Muat file .env menggunakan mekanisme Env::load() yang dipakai aplikasi
$envPath = BASE_PATH . '/.env';
$envFileExists = file_exists($envPath);
$envLoaded = false;

if ($envFileExists) {
    Env::load($envPath);
    $envLoaded = true;
}

// 2. Baca konfigurasi melalui Env::get()
$provider = Env::get('AI_PROVIDER', '');
$apiKey   = Env::get('AI_API_KEY', '');
$model    = Env::get('AI_MODEL', '');

// Sanitizer helper untuk menjamin API Key tidak pernah tercetak dalam kondisi apapun
$sanitize = function ($text) use ($apiKey) {
    if (!empty($apiKey) && is_string($text)) {
        return str_replace($apiKey, '[REDACTED]', $text);
    }
    return $text;
};

echo "=== DIAGNOSTIK KONEKSI GEMINI API ===\n";

// Evaluasi Konfigurasi
$configValid = true;
$configNotes = [];

$configNotes[] = "File .env dimuat: " . ($envLoaded ? "YA (" . realpath($envPath) . ")" : "TIDAK DITEMUKAN");

if (strtolower(trim((string)$provider)) === 'gemini') {
    $configNotes[] = "AI_PROVIDER: 'gemini' (VALID)";
} else {
    $configValid = false;
    $configNotes[] = "AI_PROVIDER: '" . $sanitize((string)$provider) . "' (Dibutuhkan 'gemini')";
}

if (!empty($apiKey) && strlen(trim((string)$apiKey)) > 0) {
    $configNotes[] = "AI_API_KEY: Terbaca dan TERISI";
} else {
    $configValid = false;
    $configNotes[] = "AI_API_KEY: KOSONG";
}

if (!empty($model)) {
    $configNotes[] = "AI_MODEL: '" . $sanitize((string)$model) . "' (VALID)";
} else {
    $configValid = false;
    $configNotes[] = "AI_MODEL: KOSONG";
}

echo "1. Evaluasi Konfigurasi:\n";
foreach ($configNotes as $note) {
    echo "   - {$note}\n";
}
echo "   Status Konfigurasi: " . ($configValid ? "BERHASIL" : "GAGAL") . "\n\n";

if (!$configValid) {
    echo "Pengujian dibatalkan karena konfigurasi belum lengkap.\n";
    exit(1);
}

// 3. Uji Autentikasi API Key ke Google Gemini API Service
echo "2. Menguji Autentikasi API Key ke Gemini API...\n";
$authUrl = "https://generativelanguage.googleapis.com/v1beta/models?pageSize=1&key=" . urlencode(trim((string)$apiKey));

$chAuth = curl_init($authUrl);
curl_setopt_array($chAuth, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);

$authResponseRaw = curl_exec($chAuth);
$authHttpCode    = curl_getinfo($chAuth, CURLINFO_HTTP_CODE);
$authCurlErrNo   = curl_errno($chAuth);
$authCurlErrMsg  = curl_error($chAuth);
curl_close($chAuth);

$authSuccess = false;
$authErrorDetails = '';

if ($authCurlErrNo !== 0) {
    $authErrorDetails = "Koneksi cURL gagal ({$authCurlErrNo}): " . $sanitize($authCurlErrMsg);
} else {
    $authDecoded = json_decode($authResponseRaw, true);
    if ($authHttpCode === 200) {
        $authSuccess = true;
    } else {
        $apiMsg = $authDecoded['error']['message'] ?? "HTTP status code {$authHttpCode}";
        $apiStat = $authDecoded['error']['status'] ?? '';
        $authErrorDetails = "HTTP {$authHttpCode} [{$apiStat}]: " . $sanitize($apiMsg);
    }
}

echo "   Hasil Autentikasi: " . ($authSuccess ? "BERHASIL (API Key valid & diotorisasi)" : "GAGAL") . "\n\n";

// 4. Uji Pengiriman Prompt / Respons Model Gemini yang dikonfigurasi
echo "3. Menguji Pengiriman Prompt (generateContent) ke Model '{$model}'...\n";
$genEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode(trim((string)$apiKey));

$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Halo! Ini adalah tes verifikasi koneksi API. Balas hanya dengan kata: OK']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'maxOutputTokens' => 50
    ]
];

$chGen = curl_init($genEndpoint);
curl_setopt_array($chGen, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);

$genResponseRaw = curl_exec($chGen);
$genHttpCode    = curl_getinfo($chGen, CURLINFO_HTTP_CODE);
$genCurlErrNo   = curl_errno($chGen);
$genCurlErrMsg  = curl_error($chGen);
curl_close($chGen);

$responseSuccess = false;
$responseErrorDetails = '';
$sampleOutput = '';

if ($genCurlErrNo !== 0) {
    $responseErrorDetails = "cURL Error ({$genCurlErrNo}): " . $sanitize($genCurlErrMsg);
} else {
    $genDecoded = json_decode($genResponseRaw, true);
    if ($genHttpCode === 200 && is_array($genDecoded)) {
        if (isset($genDecoded['candidates'][0]['content']['parts'][0]['text'])) {
            $responseSuccess = true;
            $sampleOutput = trim($genDecoded['candidates'][0]['content']['parts'][0]['text']);
        } else {
            $responseErrorDetails = "Respons 200 OK tetapi struktur JSON kandidat tidak ditemukan: " . $sanitize(substr($genResponseRaw, 0, 300));
        }
    } else {
        $apiErrorMsg = $genDecoded['error']['message'] ?? ("HTTP status code " . $genHttpCode);
        $apiStatus = $genDecoded['error']['status'] ?? '';
        $responseErrorDetails = "HTTP {$genHttpCode} [{$apiStatus}]: " . $sanitize($apiErrorMsg);
    }
}

// 5. Uji method terintegrasi AIService::testAiConnection()
echo "4. Menguji via method aplikasi AIService::testAiConnection()...\n";
$aiService = new AIService();
$serviceResult = $aiService->testAiConnection();

echo "\n=== LAPORAN HASIL PENGUJIAN SESUAI FORMAT ===\n";
echo "- Konfigurasi terbaca: " . ($configValid ? "BERHASIL" : "GAGAL") . "\n";
echo "- Autentikasi Gemini: " . ($authSuccess ? "BERHASIL" : "GAGAL") . "\n";
echo "- Respons dari Gemini: " . ($responseSuccess ? "BERHASIL" : "GAGAL") . "\n";
echo "- Model yang digunakan: " . $sanitize($model) . "\n";

if (!$authSuccess) {
    echo "- Detail error jika gagal: " . $authErrorDetails . "\n";
} elseif (!$responseSuccess) {
    echo "- Detail error jika gagal: " . $responseErrorDetails . "\n";
} else {
    echo "- Contoh Respons Diterima: \"" . $sampleOutput . "\"\n";
}

echo "- Hasil AIService::testAiConnection(): " . ($serviceResult['authenticated'] ? "BERHASIL" : "GAGAL (" . $sanitize($serviceResult['message']) . ")") . "\n";
