<?php

namespace App\Services;

use App\Core\Env;
use App\Models\AiConfiguration;
use Exception;

/**
 * GeminiConfigService
 * Handles communication with Google Gemini API for model discovery, connection testing,
 * and configuration state management without exposing sensitive credentials.
 */
class GeminiConfigService
{
    protected AiConfiguration $model;
    protected string $nodeServiceUrl;
    protected string $nodeServiceSecret;

    public function __construct()
    {
        $this->model = new AiConfiguration();
        $this->nodeServiceUrl = Env::get('AI_SERVICE_URL', 'http://127.0.0.1:5000/api/chat');
        $this->nodeServiceSecret = Env::get('AI_SERVICE_SECRET', 'sistem_kas_ai_node_secret_key_2026_auth');
    }

    /**
     * Get the active Gemini configuration with strict hierarchical priority:
     * 1. Primary: Encrypted Database (if active record exists)
     * 2. Deactivated: If explicitly turned off in database, DO NOT fall back to .env
     * 3. Fallback: .env file (only if database has never been configured)
     *
     * @return array
     */
    public function getActiveConfig(): array
    {
        $activeRow = null;
        $isExplicitlyDeactivated = false;
        $hasDbHistory = false;

        try {
            $activeRow = $this->model->getActive('gemini');
            $isExplicitlyDeactivated = $this->model->isExplicitlyDeactivated('gemini');
            $hasDbHistory = $this->model->hasAnyRecords('gemini');
        } catch (\Throwable $e) {
            // Database offline or unreachable, proceed gracefully to offline fallback
            $activeRow = null;
            $isExplicitlyDeactivated = false;
            $hasDbHistory = false;
        }

        // 1. Primary Source: Active record in database
        if ($activeRow && !empty($activeRow['encrypted_api_key'])) {
            $decrypted = EncryptionService::decrypt($activeRow['encrypted_api_key']);
            if (!empty($decrypted)) {
                return [
                    'provider'       => 'gemini',
                    'model'          => $activeRow['active_model'],
                    'api_key'        => $decrypted,
                    'masked_key'     => EncryptionService::maskApiKey($decrypted),
                    'is_configured'  => true,
                    'status'         => 'active',
                    'source'         => 'database',
                    'updated_at'     => $activeRow['updated_at'],
                    'updated_by'     => $activeRow['updated_by_name'] ?? $activeRow['updated_by_username'] ?? 'Super User',
                    'config_id'      => (int)$activeRow['id'],
                ];
            } else {
                // Decryption failure: DO NOT fall back silently to .env!
                return [
                    'provider'       => 'gemini',
                    'model'          => $activeRow['active_model'] ?? 'Belum Diatur',
                    'api_key'        => '',
                    'masked_key'     => 'Kunci Enkripsi Tidak Cocok',
                    'is_configured'  => false,
                    'status'         => 'decryption_error',
                    'source'         => 'database',
                    'updated_at'     => $activeRow['updated_at'],
                    'updated_by'     => $activeRow['updated_by_name'] ?? 'Super User',
                    'config_id'      => (int)$activeRow['id'],
                ];
            }
        }

        // 2. Explicitly Deactivated or Database History Exists without Active Record:
        // Administrator deliberately turned off AI in database or no record is active.
        // DO NOT resurrect stale API Key from .env!
        if ($isExplicitlyDeactivated || $hasDbHistory) {
            return [
                'provider'       => 'gemini',
                'model'          => 'Belum Diatur',
                'api_key'        => '',
                'masked_key'     => 'Belum Dikonfigurasi',
                'is_configured'  => false,
                'status'         => 'deactivated',
                'source'         => 'database',
                'updated_at'     => null,
                'updated_by'     => 'Admin Database',
                'config_id'      => null,
            ];
        }

        // 3. Initial Fallback: Environment variables (.env) ONLY if database has NEVER been configured (0 records)
        $envKey   = trim((string)Env::get('AI_API_KEY', ''));
        $envModel = trim((string)Env::get('AI_MODEL', ''));

        $isEnvConfigured = !empty($envKey);

        return [
            'provider'       => 'gemini',
            'model'          => $isEnvConfigured ? (!empty($envModel) ? $envModel : 'gemini-2.0-flash') : 'Belum Diatur',
            'api_key'        => $envKey,
            'masked_key'     => $isEnvConfigured ? EncryptionService::maskApiKey($envKey) : 'Belum Dikonfigurasi',
            'is_configured'  => $isEnvConfigured,
            'status'         => $isEnvConfigured ? 'active' : 'unconfigured',
            'source'         => $isEnvConfigured ? 'env' : 'none',
            'updated_at'     => null,
            'updated_by'     => $isEnvConfigured ? 'Konfigurasi .env' : 'Belum Dikonfigurasi',
            'config_id'      => null,
        ];
    }

    /**
     * Explicitly deactivate AI configuration in database and clear runtime memory.
     *
     * @param int|null $userId
     * @return bool
     */
    public function deactivateConfig(?int $userId = null): bool
    {
        try {
            $this->model->deactivateAll('gemini', $userId);
            $this->notifyNodeService('', '');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Fetch available Gemini models that support 'generateContent'.
     *
     * @param string $apiKey
     * @return array ['success' => bool, 'models' => array, 'error' => string|null]
     */
    public function fetchAvailableModels(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        if (empty($apiKey)) {
            return [
                'success' => false,
                'models'  => [],
                'error'   => 'API Key Gemini tidak boleh kosong.'
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: SistemKas-AI-Configuration/1.0'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if (!empty($curlErr)) {
            return [
                'success' => false,
                'models'  => [],
                'error'   => 'Gagal menghubungi Gemini API: ' . $this->sanitizeError($curlErr, $apiKey)
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !is_array($data)) {
            $apiMessage = $data['error']['message'] ?? "Kode respons HTTP {$httpCode}";
            $apiStatus  = $data['error']['status'] ?? '';
            return [
                'success' => false,
                'models'  => [],
                'error'   => $this->mapGeminiError($httpCode, $apiStatus, $apiMessage, $apiKey)
            ];
        }

        $rawModels = $data['models'] ?? [];
        $supportedModels = [];

        foreach ($rawModels as $item) {
            $methods = $item['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods, true)) {
                continue;
            }

            $rawName = $item['name'] ?? '';
            $cleanId = preg_replace('#^models/#', '', $rawName);
            $displayName = $item['displayName'] ?? $cleanId;
            $description = $item['description'] ?? '';

            $supportedModels[] = [
                'id'               => $cleanId,
                'name'             => $rawName,
                'displayName'      => $displayName,
                'description'      => $description,
                'inputTokenLimit'  => $item['inputTokenLimit'] ?? null,
                'outputTokenLimit' => $item['outputTokenLimit'] ?? null,
            ];
        }

        // Sort models: popular/standard models first
        usort($supportedModels, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        return [
            'success' => true,
            'models'  => $supportedModels,
            'count'   => count($supportedModels),
            'error'   => null
        ];
    }

    /**
     * Test connection and generateContent capability for a specified Gemini API Key and Model.
     *
     * @param string $apiKey
     * @param string $model
     * @return array
     */
    public function testConfiguration(string $apiKey, string $model): array
    {
        $apiKey = trim($apiKey);
        $model = trim($model);

        // Normalize model: remove leading 'models/' if present
        $cleanModel = preg_replace('#^models/#', '', $model);

        // Stage 1: Validation
        if (empty($apiKey)) {
            return [
                'success' => false,
                'stage'   => 'validation',
                'error'   => 'API Key Gemini tidak boleh kosong.'
            ];
        }

        if (empty($cleanModel)) {
            return [
                'success' => false,
                'stage'   => 'validation',
                'error'   => 'Model Gemini belum dipilih.'
            ];
        }

        // Stage 2: Prompt Testing
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key=" . urlencode($apiKey);

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => 'Halo! Ini adalah tes verifikasi koneksi AI Sistem Kas. Balas dengan kalimat pendek konfirmasi.']
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 60
            ]
        ];

        $startTime = microtime(true);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: SistemKas-AI-Configuration/1.0'
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $latencyMs = round((microtime(true) - $startTime) * 1000);

        if (!empty($curlErr)) {
            return [
                'success'    => false,
                'stage'      => 'network',
                'latency_ms' => $latencyMs,
                'error'      => 'Gagal terhubung ke Gemini API: ' . $this->sanitizeError($curlErr, $apiKey)
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data)) {
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $sampleText = trim($data['candidates'][0]['content']['parts'][0]['text']);
                return [
                    'success'         => true,
                    'stage'           => 'completed',
                    'model'           => $cleanModel,
                    'latency_ms'      => $latencyMs,
                    'sample_response' => $this->sanitizeError($sampleText, $apiKey),
                    'message'         => "Autentikasi dan respons model '{$cleanModel}' berhasil diverifikasi ({$latencyMs} ms)."
                ];
            }

            return [
                'success'    => false,
                'stage'      => 'response_format',
                'latency_ms' => $latencyMs,
                'error'      => 'Gemini API mengembalikan respons 200 OK tetapi teks kandidat tidak ditemukan.'
            ];
        }

        $apiMessage = $data['error']['message'] ?? "Kode status HTTP {$httpCode}";
        $apiStatus  = $data['error']['status'] ?? '';

        return [
            'success'    => false,
            'stage'      => 'gemini_api',
            'latency_ms' => $latencyMs,
            'error'      => $this->mapGeminiError($httpCode, $apiStatus, $apiMessage, $apiKey)
        ];
    }

    /**
     * Notify Node.js microservice of active configuration update.
     * Enables zero-restart live reload in Node.js runtime.
     *
     * @param string $apiKey
     * @param string $model
     * @return bool
     */
    public function notifyNodeService(string $apiKey, string $model): bool
    {
        $nodeConfigUrl = preg_replace('#/api/chat.*$#', '/api/config', $this->nodeServiceUrl);
        if ($nodeConfigUrl === $this->nodeServiceUrl) {
            $nodeConfigUrl = 'http://127.0.0.1:5000/api/config';
        }

        $payload = [
            'provider' => 'gemini',
            'model'    => preg_replace('#^models/#', '', $model),
            'apiKey'   => $apiKey,
        ];

        $ch = curl_init($nodeConfigUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Service-Secret: ' . $this->nodeServiceSecret,
                'User-Agent: SistemKas-PHP-Backend/1.0'
            ],
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code === 200);
    }

    /**
     * Map HTTP error codes and Gemini API errors to clear, Indonesian user-friendly messages.
     */
    protected function mapGeminiError(int $httpCode, string $status, string $message, string $apiKey): string
    {
        $sanitizedMsg = $this->sanitizeError($message, $apiKey);

        if ($httpCode === 400) {
            return "Format permintaan atau API Key tidak valid ({$sanitizedMsg}).";
        }

        if ($httpCode === 401 || $status === 'UNAUTHENTICATED') {
            return "API Key Gemini tidak valid atau tidak memiliki izin akses ke Google Gemini API.";
        }

        if ($httpCode === 403 || $status === 'PERMISSION_DENIED') {
            if (stripos($message, 'billing') !== false || stripos($message, 'free tier') !== false) {
                return "Google Gemini API menolak akses: Project memerlukan akun billing aktif atau model tier berbayar. Silakan gunakan model yang tersedia atau periksa billing di Google AI Studio.";
            }
            return "Akses Gemini API ditolak (PERMISSION_DENIED). Periksa izin API Key atau batasan geografis.";
        }

        if ($httpCode === 404 || $status === 'NOT_FOUND') {
            return "Model yang dipilih tidak ditemukan atau sudah tidak didukung oleh Gemini API ({$sanitizedMsg}).";
        }

        if ($httpCode === 429 || $status === 'RESOURCE_EXHAUSTED') {
            return "Batas kuota request (rate limit / quota exhausted) Google Gemini API telah tercapai. Silakan coba beberapa saat lagi.";
        }

        if ($httpCode >= 500) {
            return "Terjadi kendala pada server Google Gemini API (HTTP {$httpCode}). Silakan coba beberapa saat lagi.";
        }

        return "Gemini API mengembalikan kesalahan [HTTP {$httpCode} - {$status}]: {$sanitizedMsg}";
    }

    /**
     * Ensure the raw API Key is strictly never leaked in any output, log, or message.
     */
    protected function sanitizeError(string $text, string $apiKey): string
    {
        if (!empty($apiKey)) {
            $text = str_replace($apiKey, '[REDACTED]', $text);
        }
        return $text;
    }
}
