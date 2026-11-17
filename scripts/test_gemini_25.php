<?php
/**
 * scripts/test_gemini_25.php
 * Skrip diagnostik terpisah untuk menguji model gemini-2.5-flash dan gemini-3.8-flash dengan SSL aktif.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

$apiKey = Env::get('AI_API_KEY', '');

$sanitize = function ($text) use ($apiKey) {
    if (!empty($apiKey) && is_string($text)) {
        return str_replace($apiKey, '[REDACTED]', $text);
    }
    return $text;
};

function testModel($modelName, $apiKey, $sanitize) {
    echo "=== PENGUJIAN MODEL: {$modelName} ===\n";
    echo "Verifikasi SSL: AKTIF (CURLOPT_SSL_VERIFYPEER = true, CURLOPT_SSL_VERIFYHOST = 2)\n";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . urlencode(trim((string)$apiKey));

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => 'Halo! Balas hanya dengan satu kalimat: Koneksi Gemini berhasil.']
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 60
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $responseRaw = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrNo   = curl_errno($ch);
    $curlErrMsg  = curl_error($ch);
    $sslVerifyResult = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
    curl_close($ch);

    echo "cURL Error Code: {$curlErrNo}\n";
    echo "SSL Verify Result: " . ($sslVerifyResult === 0 ? "VALID (0 - OK)" : "CODE {$sslVerifyResult}") . "\n";
    echo "HTTP Status Code: {$httpCode}\n";

    $isSuccess = false;
    $msg = '';

    if ($httpCode === 200 && $curlErrNo === 0) {
        $data = json_decode($responseRaw, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $isSuccess = true;
            $msg = trim($data['candidates'][0]['content']['parts'][0]['text']);
        } else {
            $msg = "Format JSON tanpa teks: " . $sanitize(substr($responseRaw, 0, 200));
        }
    } else {
        $data = json_decode($responseRaw, true);
        $apiMsg = $data['error']['message'] ?? ("cURL/HTTP error " . $httpCode);
        $msg = "Error API: " . $sanitize($apiMsg);
    }

    echo "Gemini Berhasil Merespons: " . ($isSuccess ? "YA" : "TIDAK") . "\n";
    echo "Detail/Isi Respons: \"" . $msg . "\"\n\n";
    return ['httpCode' => $httpCode, 'success' => $isSuccess, 'msg' => $msg];
}

// 1. Uji gemini-2.5-flash (sesuai permintaan spesifik pengguna)
testModel('gemini-2.5-flash', $apiKey, $sanitize);

// 2. Uji gemini-3.8-flash (sesuai rekomendasi langsung dari error Google di atas)
testModel('gemini-3.8-flash', $apiKey, $sanitize);
