<?php

namespace App\Services;

use App\Core\Env;
use App\Models\AiConfiguration;
use Exception;

/**
 * GroqConfigService
 * Handles API interactions specifically for the Groq AI provider:
 * - Listing available models from Groq API (GET https://api.groq.com/openai/v1/models)
 * - Testing connection & chat completions (POST https://api.groq.com/openai/v1/chat/completions)
 * - Strict SSL Verification enabled
 * - Zero raw key or Authorization header leakage
 */
class GroqConfigService
{
    protected const CHAT_URL = 'https://api.groq.com/openai/v1/chat/completions';
    protected const MODELS_URL = 'https://api.groq.com/openai/v1/models';

    protected AiConfiguration $model;

    public function __construct()
    {
        $this->model = new AiConfiguration();
    }

    /**
     * Fetch available models dynamically from Groq official API.
     *
     * @param string $apiKey
     * @return array ['success' => bool, 'models' => array, 'count' => int, 'error' => string|null]
     */
    public function fetchAvailableModels(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        if (empty($apiKey)) {
            return [
                'success' => false,
                'models'  => [],
                'count'   => 0,
                'error'   => 'API Key Groq tidak boleh kosong.'
            ];
        }

        $ch = curl_init(self::MODELS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: SistemKas-Groq-Configuration/1.0'
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
                'count'   => 0,
                'error'   => 'Gagal menghubungi Groq API: ' . $this->sanitizeError($curlErr, $apiKey)
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !is_array($data)) {
            $apiMessage = $data['error']['message'] ?? "Kode status HTTP {$httpCode}";
            return [
                'success' => false,
                'models'  => [],
                'count'   => 0,
                'error'   => $this->mapGroqError($httpCode, $apiMessage, $apiKey)
            ];
        }

        $rawModels = $data['data'] ?? [];
        if (!is_array($rawModels)) {
            return [
                'success' => false,
                'models'  => [],
                'count'   => 0,
                'error'   => 'Format respons model dari Groq API tidak valid.'
            ];
        }

        $availableModels = [];
        foreach ($rawModels as $item) {
            $id = trim((string)($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            // Safe check: do not assume active is boolean or always set
            if (isset($item['active']) && $item['active'] === false) {
                continue;
            }

            // Skip non-chat models like whisper/audio if desired, or keep all text models
            $contextWindow = isset($item['context_window']) ? (int)$item['context_window'] : null;
            $ownedBy = trim((string)($item['owned_by'] ?? 'Groq'));

            $availableModels[] = [
                'id'            => $id,
                'displayName'   => $id . ($ownedBy !== '' ? " ({$ownedBy})" : ''),
                'contextWindow' => $contextWindow,
                'description'   => "Model Groq didukung oleh {$ownedBy}." . ($contextWindow ? " Konteks: " . number_format($contextWindow) . " tokens." : '')
            ];
        }

        usort($availableModels, fn($a, $b) => strcmp($a['id'], $b['id']));

        return [
            'success' => true,
            'models'  => $availableModels,
            'count'   => count($availableModels),
            'error'   => null
        ];
    }

    /**
     * Test connection and chat completion capability for a given Groq API Key and Model.
     *
     * @param string $apiKey
     * @param string $model
     * @return array
     */
    public function testConfiguration(string $apiKey, string $model): array
    {
        $apiKey = trim($apiKey);
        $model  = trim($model);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'stage'   => 'validation',
                'error'   => 'API Key Groq tidak boleh kosong.'
            ];
        }

        if (empty($model)) {
            return [
                'success' => false,
                'stage'   => 'validation',
                'error'   => 'Model Groq belum dipilih.'
            ];
        }

        $payload = [
            'model'    => $model,
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => 'Halo! Ini adalah tes verifikasi koneksi AI Sistem Kas. Balas dengan kalimat pendek konfirmasi.'
                ]
            ],
            'max_tokens'  => 50,
            'temperature' => 0.2
        ];

        $startTime = microtime(true);

        $ch = curl_init(self::CHAT_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: SistemKas-Groq-Configuration/1.0'
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
                'error'      => 'Gagal terhubung ke Groq API: ' . $this->sanitizeError($curlErr, $apiKey)
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data)) {
            if (isset($data['choices'][0]['message']['content'])) {
                $sampleText = trim($data['choices'][0]['message']['content']);
                return [
                    'success'         => true,
                    'stage'           => 'completed',
                    'latency_ms'      => $latencyMs,
                    'model'           => $model,
                    'provider'        => 'groq',
                    'sample_response' => $sampleText,
                    'message'         => "Koneksi ke Groq API berhasil diverifikasi ({$latencyMs} ms)."
                ];
            }

            return [
                'success'    => false,
                'stage'      => 'response_format',
                'latency_ms' => $latencyMs,
                'error'      => 'Groq API mengembalikan status 200 OK tetapi konten balasan tidak ditemukan.'
            ];
        }

        $apiMessage = $data['error']['message'] ?? "Kode status HTTP {$httpCode}";
        return [
            'success'    => false,
            'stage'      => 'groq_api',
            'latency_ms' => $latencyMs,
            'error'      => $this->mapGroqError($httpCode, $apiMessage, $apiKey)
        ];
    }

    /**
     * Map Groq HTTP error codes into user-friendly Indonesian messages without leaking secrets.
     */
    protected function mapGroqError(int $httpCode, string $rawMessage, string $apiKey): string
    {
        $sanitized = $this->sanitizeError($rawMessage, $apiKey);

        switch ($httpCode) {
            case 401:
                return "Autentikasi Gagal (HTTP 401): API Key Groq tidak valid atau salah. Silakan periksa kembali API Key Anda.";
            case 403:
                return "Akses Ditolak (HTTP 403): Akses ke Groq API tidak diizinkan. Keterangan: {$sanitized}";
            case 404:
                return "Model Tidak Ditemukan (HTTP 404): Model Groq yang dipilih tidak tersedia atau telah dihentikan oleh Groq.";
            case 429:
                return "Batas Kuota / Laju Terlampaui (HTTP 429): Terlalu banyak permintaan atau batas token Groq tercapai. Silakan coba beberapa saat lagi.";
            case 500:
            case 502:
            case 503:
                return "Server Groq Bermasalah (HTTP {$httpCode}): Layanan Groq sedang mengalami gangguan teknis sementara.";
            default:
                return "Groq API mengembalikan error HTTP {$httpCode}: {$sanitized}";
        }
    }

    /**
     * Strip API key, bearer tokens, or sensitive patterns from error messages.
     */
    protected function sanitizeError(string $message, string $apiKey = ''): string
    {
        if (!empty($apiKey)) {
            $message = str_replace($apiKey, '[REDACTED_KEY]', $message);
        }
        $message = preg_replace('/gsk_[a-zA-Z0-9_\-]+/', '[REDACTED_GROQ_KEY]', $message);
        $message = preg_replace('/Bearer\s+[^\s]+/i', 'Bearer [REDACTED]', $message);
        return htmlspecialchars(trim($message), ENT_QUOTES, 'UTF-8');
    }
}

