<?php

namespace App\Services;

use App\Core\Env;

/**
 * AIService
 * Interacts with AI Provider (Google Gemini / OpenAI / Node.js Microservice) or Local Analytical Engine.
 * Strictly READ-ONLY. Never executes DB updates.
 */
class AIService
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;
    protected bool $nodeServiceEnabled;
    protected string $nodeServiceUrl;
    protected string $nodeServiceSecret;
    protected int $nodeServiceTimeout;

    public function __construct()
    {
        // Load active configuration dynamically (Database precedence over .env)
        try {
            $aiConfigModel = new \App\Models\AiConfiguration();
            $globalActive = $aiConfigModel->getGlobalActive();

            if (!empty($globalActive) && !empty($globalActive['encrypted_api_key'])) {
                $decryptedKey = \App\Services\EncryptionService::decrypt($globalActive['encrypted_api_key']);
                if (!empty($decryptedKey)) {
                    $this->provider = $globalActive['provider'] ?? 'gemini';
                    $this->apiKey   = $decryptedKey;
                    $this->model    = $globalActive['active_model'] ?? '';
                } else {
                    $this->provider = 'none';
                    $this->apiKey   = '';
                    $this->model    = '';
                }
            } else {
                // Fallback to GeminiConfigService (handles explicit deactivation and initial .env fallback)
                $configService = new GeminiConfigService();
                $activeConfig  = $configService->getActiveConfig();

                $this->provider = $activeConfig['provider'] ?? Env::get('AI_PROVIDER', 'gemini');
                $this->apiKey   = $activeConfig['api_key'] ?? Env::get('AI_API_KEY', '');
                $this->model    = $activeConfig['model'] ?? Env::get('AI_MODEL', 'gemini-1.5-flash');
            }
        } catch (\Throwable $e) {
            $this->provider = Env::get('AI_PROVIDER', 'gemini');
            $this->apiKey   = Env::get('AI_API_KEY', '');
            $this->model    = Env::get('AI_MODEL', 'gemini-1.5-flash');
        }

        $this->nodeServiceEnabled = (bool)Env::get('AI_SERVICE_ENABLED', true);
        $this->nodeServiceUrl     = Env::get('AI_SERVICE_URL', 'http://127.0.0.1:5000/api/chat');
        $this->nodeServiceSecret  = Env::get('AI_SERVICE_SECRET', 'sistem_kas_ai_node_secret_key_2026_auth');
        $this->nodeServiceTimeout = (int)Env::get('AI_SERVICE_TIMEOUT', 12);
    }

    /**
     * Convenient wrapper to ask a question with dynamic aggregation.
     */
    public function ask(string $userQuery, string $role, ?array $userInfo = null, ?array $context = null): array
    {
        if ($context === null) {
            $aggregator = new FinancialDataAggregator();
            $anggotaId = isset($userInfo['anggota_id']) ? (int)$userInfo['anggota_id'] : null;
            $context = $aggregator->getAggregatedContext($role, $anggotaId, $userQuery);
            if ($userInfo) {
                $context['pengguna_login'] = $userInfo;
            }
        }
        return $this->analyzeQuery($userQuery, $context, $role, $userInfo);
    }

    /**
     * Analyze financial query with provided aggregated context and user identity.
     *
     * @param string     $userQuery Question asked by user
     * @param array      $context   Aggregated financial data context
     * @param string     $role      'super_user', 'bendahara', or 'anggota'
     * @param array|null $userInfo  Authenticated user identity from session
     * @return array ['answer' => string, 'provider' => string, 'success' => bool]
     */
    public function analyzeQuery(string $userQuery, array $context, string $role, ?array $userInfo = null): array
    {
        // 0. Database Offline Guard
        if (!empty($context['database_status']) && $context['database_status'] === 'gagal_akses') {
            return [
                'success'  => false,
                'answer'   => 'Mohon maaf, database Sistem Kas saat ini tidak dapat diakses. Silakan periksa konfigurasi dan koneksi database MySQL Anda.',
                'provider' => 'system_guard (database_offline)',
                'error'    => $context['database_error'] ?? 'Database connection failed'
            ];
        }

        // 1. Resolve authenticated user identity safely (never trust browser input)
        $userInfo = $this->resolveUserInfo($userInfo, $context, $role);

        // 2. Fast-Path Local Recognition: AI capability query ("Apa tugas Anda?", "Apa tugasmu?", etc.)
        if ($this->isBotCapabilityQuery($userQuery)) {
            return [
                'success'  => true,
                'answer'   => $this->generateBotCapabilityResponse(),
                'provider' => 'local_analytical_engine'
            ];
        }

        // 3. Fast-Path Local Recognition: AI mengenali dirinya sendiri ("Siapa Anda?", "Kamu siapa?", "Apa kamu?", "Perkenalkan dirimu.")
        if ($this->isBotIdentityQuery($userQuery)) {
            return [
                'success'  => true,
                'answer'   => $this->generateBotIdentityResponse($role, $userQuery),
                'provider' => 'local_analytical_engine'
            ];
        }

        // 4. Fast-Path Local Recognition: AI mengenali pengguna berdasarkan session terautentikasi
        if ($this->isUserIdentityQuery($userQuery)) {
            return [
                'success'  => true,
                'answer'   => $this->generateUserIdentityResponse($userInfo, $role, $userQuery),
                'provider' => 'local_analytical_engine'
            ];
        }

        // 2b. Hybrid Route: Structured financial queries use PHP/FinancialDataAggregator calculations as source of truth.
        if ($this->isStructuredDataQuery($userQuery)) {
            $localAnswer = $this->generateLocalAnalysis($userQuery, $context, $role, $userInfo);
            return [
                'success'  => true,
                'answer'   => $localAnswer,
                'provider' => 'local_analytical_engine'
            ];
        }

        // 5. Build system prompt with complete real database context and strict anti-hallucination rules
        $systemPrompt = $this->buildSystemPrompt($context, $role, $userInfo);

        // 6. Primary Route: If Node.js AI microservice is enabled and running, delegate request to Node.js backend
        if ($this->nodeServiceEnabled && !empty($this->nodeServiceUrl)) {
            try {
                $nodeResponse = $this->callNodeJsService($userQuery, $context, $role, $userInfo);
                if (!empty($nodeResponse) && !empty($nodeResponse['success']) && !empty($nodeResponse['answer'])) {
                    return [
                        'success'  => true,
                        'answer'   => $nodeResponse['answer'],
                        'provider' => $nodeResponse['provider'] ?? 'nodejs_ai_service'
                    ];
                }
            } catch (\Throwable $t) {
                // Node.js service unavailable or timed out, gracefully proceed to internal engine
            }
        }

        // 7. Live Provider Route: If API Key is present, send context + question to AI Model API
        if ($this->provider === 'groq' && !empty($this->apiKey)) {
            $compactContext = $this->compactContextForGroq($context, $userQuery);
            $compactPrompt = $this->buildSystemPrompt($compactContext, $role, $userInfo, true);
            $apiResult = $this->callGroqApi($compactPrompt, $userQuery);
            $groqAnswer = trim((string)($apiResult['answer'] ?? ''));
            $groqAccepted = (
                !empty($apiResult['success']) &&
                $groqAnswer !== '' &&
                ($apiResult['finish_reason'] ?? null) !== 'length' &&
                !$this->isLowQualityGroqResponse($groqAnswer, $userQuery)
            );

            if ($groqAccepted) {
                return [
                    'success'  => true,
                    'answer'   => $groqAnswer,
                    'provider' => $apiResult['provider']
                ];
            }

            if (!empty($apiResult['error_type']) && in_array($apiResult['error_type'], ['invalid_key', 'quota_exceeded'])) {
                $localFallback = $this->generateLocalAnalysis($userQuery, $context, $role, $userInfo);
                return [
                    'success'  => true,
                    'answer'   => "⚠️ [Pemberitahuan Layanan AI: {$apiResult['error_message']}]\n\n" . $localFallback,
                    'provider' => 'fallback_due_to_' . $apiResult['error_type']
                ];
            }
        } elseif (!empty($this->apiKey)) {
            $apiResult = $this->callAiProvider($systemPrompt, $userQuery);
            if (!empty($apiResult['success']) && !empty($apiResult['answer'])) {
                return [
                    'success'  => true,
                    'answer'   => $apiResult['answer'],
                    'provider' => $apiResult['provider']
                ];
            }

            // If API key is explicitly invalid, expired, or quota exhausted, notify clearly
            if (!empty($apiResult['error_type']) && in_array($apiResult['error_type'], ['invalid_key', 'quota_exceeded'])) {
                $localFallback = $this->generateLocalAnalysis($userQuery, $context, $role, $userInfo);
                return [
                    'success'  => true,
                    'answer'   => "⚠️ [Pemberitahuan Layanan AI: {$apiResult['error_message']}]\n\n" . $localFallback,
                    'provider' => 'fallback_due_to_' . $apiResult['error_type']
                ];
            }
        }

        // 8. Deterministic Local Analytical Engine (Active when API Key is not set or network timeout)
        $localAnswer = $this->generateLocalAnalysis($userQuery, $context, $role, $userInfo);
        return [
            'success'  => true,
            'answer'   => $localAnswer,
            'provider' => 'local_analytical_engine'
        ];
    }

    /**
     * Detect whether a query is a structured data query.
     * Structured queries are handled by the local analytical engine, bypassing external AI.
     */
    public function isStructuredDataQuery(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        // Member queries
        if (preg_match('/(berapa|jumlah|total|ada\s+berapa)\s+.*anggota|anggota\s+.*(jumlah|total|berapa)/u', $q)) return true;
        if (preg_match('/berapa\s+anggota|ada\s+berapa\s+anggota|jumlah\s+anggota|total\s+anggota/u', $q)) return true;
        if (preg_match('/anggota\s+terdaftar/u', $q)) return true;
        if (preg_match('/anggota\s+(yang\s+)?(sudah\s+)?(lunas|menunggak|nunggak|belum\s+bayar|belum\s+membayar)/u', $q)) return true;
        if (preg_match('/berapa\s+anggota\s+(yang\s+)?(lunas|menunggak|nunggak)/u', $q)) return true;
        if (preg_match('/(lunas|menunggak)\s+dan\s+(menunggak|lunas)/u', $q)) return true;
        if (preg_match('/(siapa\s+saja|daftar)\s+anggota\s+(yang\s+)?(menunggak|tunggak|nunggak|belum)/u', $q)) return true;
        if (preg_match('/(siapa|siapa\s+saja)\s+(yang\s+)?(masih\s+)?(menunggak|tunggak|nunggak|belum\s+bayar)/u', $q)) return true;
        // Financial queries
        if (preg_match('/total\s+pemasukan|total\s+pengeluaran|saldo\s+kas/u', $q)) return true;
        if (preg_match('/(pemasukan|pengeluaran|saldo)\s+(saat\s+ini|berdasarkan\s+database)/u', $q)) return true;
        if (preg_match('/berapa\s+(total\s+)?(pemasukan|pengeluaran|saldo)/u', $q)) return true;
        if (preg_match('/(pemasukan|pengeluaran).*dan.*(saldo|pengeluaran)/u', $q)) return true;
        // Payment total (explicit) — must precede member-count check to avoid false positives
        if (preg_match('/(total|seluruh|berapa)\s+(pembayaran|setoran)/u', $q)) return true;
        if (preg_match('/pembayaran\s+(anggota|kas|iuran)\s+(berapa|total|seluruh|periode)/u', $q)) return true;
        if (preg_match('/berapa\s+(total\s+)?pembayaran/u', $q)) return true;
        // Transaction queries
        if (preg_match('/(transaksi|pembayaran)\s+(terbaru|terakhir|lima|5)/u', $q)) return true;
        if (preg_match('/(tampilkan|lihat|daftar)\s+.*(transaksi|pembayaran)/u', $q)) return true;
        if (preg_match('/riwayat\s+(transaksi|pembayaran)/u', $q)) return true;
        // Period-specific
        if (preg_match('/(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)/u', $q)
            && preg_match('/(pembayaran|tunggak|pemasukan|pengeluaran|bayar)/u', $q)) return true;
        // Arrears
        if (preg_match('/tunggakan|menunggak|nunggak|belum\s+(membayar|bayar)/u', $q)) return true;
        // Void
        if (preg_match('/(void|batal|dibatalkan)\s*(transaksi)?/u', $q)) return true;
        // Reconciliation queries (saldo vs pembayaran/iuran)
        if (preg_match('/(mengapa|kenapa|jelaskan|apakah|sebab|alasan).*saldo.*(beda|berbeda|selisih|iuran|pembayaran)/u', $q)) return true;
        if (preg_match('/saldo.*(beda|berbeda|selisih).*pembayaran/u', $q)) return true;
        if (preg_match('/(beda|berbeda|selisih).*saldo.*(pembayaran|iuran)/u', $q)) return true;
        // Access policy queries
        if (preg_match('/(hak\s+akses|kebijakan\s+akses|batasan\s+akses|tidak\s+boleh\s+diakses|bukan\s+hak\s+akses)/u', $q)) return true;
        if (preg_match('/apa\s+yang\s+terjadi.*(akses|informasi\s+anggota|akun\s+saya)/u', $q)) return true;
        // User/account queries
        if (preg_match('/(jumlah|berapa|total)\s+(user|pengguna|akun)/u', $q)) return true;
        if (preg_match('/(user|pengguna|akun)\s+(terdaftar|aktif|role|berapa)/u', $q)) return true;
        if (str_contains($q, 'daftar pengguna') || str_contains($q, 'pengguna terdaftar')) return true;
        // Audit/log queries
        if (str_contains($q, 'audit') || str_contains($q, 'log aktivitas')) return true;
        if (str_contains($q, 'aktivitas') && (str_contains($q, 'terakhir') || str_contains($q, 'log') || str_contains($q, 'tercatat'))) return true;
        // Multi-table Expense queries (purpose, creator, largest, detail, comparison, follow-up)
        if (preg_match('/(pengeluaran|uang\s+keluar|biaya|transaksi).*(buat\s+apa|untuk\s+apa|tujuan|keperluan|kegunaan|detail|rincian|keterangan|terbesar|paling\s+besar|paling\s+banyak|terbanyak|siapa\s+yang\s+mencatat|dicatat\s+oleh|pencatat)/u', $q)) return true;
        if (preg_match('/(buat\s+apa|untuk\s+apa|keperluan\s+apa|siapa\s+yang\s+mencatat|siapa\s+pencatat|kapan\s+transaksi).*(pengeluaran|transaksi|uang\s+keluar)/u', $q)) return true;
        if (preg_match('/(pengeluaran|uang\s+keluar)\s+(\d+|rp)/u', $q)) return true;
        if (preg_match('/(pengeluaran|transaksi)\s+(itu|tadi|tersebut)/u', $q)) return true;
        if (preg_match('/(siapa\s+yang\s+mencatatnya|kapan\s+transaksi\s+itu|pengeluaran\s+itu\s+untuk\s+apa)/u', $q)) return true;
        if (preg_match('/banding.*(pengeluaran|pemasukan)/u', $q)) return true;
        if (preg_match('/(pengeluaran|pemasukan).*banding/u', $q)) return true;
        if (preg_match('/(transaksi|pengeluaran|pemasukan)\s+mana\s+yang\s+(paling\s+besar|terbesar)/u', $q)) return true;
        if (preg_match('/(rincian|detail)\s+saldo/u', $q)) return true;
        if (preg_match('/apakah\s+total\s+pembayaran.*cocok.*pemasukan/u', $q)) return true;
        // Year-specific financial queries (misal: pemasukan tahun 2026)
        if (preg_match('/\b(202[0-9])\b/u', $q) && preg_match('/(pemasukan|pengeluaran|saldo|pembayaran|transaksi|tunggak)/u', $q)) return true;
        return false;
    }

    /**
     * Call Node.js AI Backend Service with internal auth token.
     */
    protected function callNodeJsService(string $question, array $context, string $role, array $userInfo): ?array
    {
        $payload = [
            'question'          => $question,
            'role'              => $role,
            'user_context'      => $userInfo,
            'financial_context' => $context,
            'active_config'     => [
                'provider' => $this->provider,
                'apiKey'   => $this->apiKey,
                'model'    => $this->model,
            ]
        ];

        $ch = curl_init($this->nodeServiceUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Service-Secret: ' . $this->nodeServiceSecret,
                'User-Agent: SistemKas-PHP-Backend/1.0'
            ],
            CURLOPT_TIMEOUT        => $this->nodeServiceTimeout,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data['success'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Resolve authenticated user identity from parameter, context, or active server session.
     */
    public function resolveUserInfo(?array $userInfo, array $context, string $role): array
    {
        if (!empty($userInfo) && is_array($userInfo)) {
            return $userInfo;
        }

        if (!empty($context['pengguna_login']) && is_array($context['pengguna_login'])) {
            return $context['pengguna_login'];
        }

        if (!empty($context['user_info']) && is_array($context['user_info'])) {
            return $context['user_info'];
        }

        // Safe fallback from server session
        $sessionUser = function_exists('session') ? (session('user') ?? []) : [];
        if (!empty($sessionUser)) {
            return [
                'id'              => (int)($sessionUser['id'] ?? 0),
                'username'        => (string)($sessionUser['username'] ?? ''),
                'role'            => (string)($sessionUser['role'] ?? $role),
                'nama'            => $sessionUser['nama'] ?? null,
                'nomor_identitas' => $sessionUser['nomor_identitas'] ?? null,
                'kelas'           => $sessionUser['kelas'] ?? null,
                'status'          => $sessionUser['status'] ?? 'active',
            ];
        }

        return [
            'id'              => 0,
            'username'        => $role === 'super_user' ? 'admin' : ($role === 'bendahara' ? 'bendahara' : 'anggota'),
            'role'            => $role,
            'nama'            => null,
            'nomor_identitas' => null,
            'kelas'           => null,
            'status'          => 'active',
        ];
    }

    /**
     * Check if query is asking about the AI Bot's own identity.
     */
    public function isBotIdentityQuery(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $clean = ' ' . trim(preg_replace('/\s+/', ' ', $clean)) . ' ';

        if (preg_match('/\b(saya|aku|diriku|namaku)\b/u', $clean) && !preg_match('/\b(kamu|anda|dirimu)\b/u', $clean)) {
            return false;
        }

        if (preg_match('/\b(transaksi|belum bayar|belum membayar|menunggak|pembayaran|iuran|saldo)\b/u', $clean)) {
            return false;
        }

        $botPatterns = [
            '/\b(siapa|siapakah)\s+(kamu|anda|dirimu|namamu)\b/u',
            '/\b(kamu|anda|dirimu)\s+(siapa|siapakah)\b/u',
            '/\b(apa|apakah)\s+(kamu|anda|dirimu)\b/u',
            '/\b(kamu|anda|dirimu)\s+(apa|apakah)\b/u',
            '/\b(kamu|anda)\s+ini\s+(apa|siapa)\b/u',
            '/\b(perkenalkan|kenalkan)\s+(dirimu|diri\s+kamu|diri\s+anda)\b/u',
            '/\b(nama\s+kamu|nama\s+anda|namamu)\s+(siapa|siapakah|apa)\b/u',
            '/\b(apa|apakah)\s+(nama\s+kamu|nama\s+anda|namamu)\b/u',
            '/\b(apa|apakah)\s+(tugasmu|tugas\s+kamu|tugas\s+anda|peranmu|peran\s+kamu|peran\s+anda|fungsimu|fungsi\s+kamu|fungsi\s+anda|kemampuanmu|kemampuan\s+kamu)\b/u',
            '/\b(tugasmu|tugas\s+kamu|peranmu|peran\s+kamu|fungsimu|fungsi\s+kamu|kemampuanmu|kemampuan\s+kamu)\s+(apa|apakah)\b/u',
            '/\b(kamu|anda)\s+(bisa|dapat)\s+apa(\s+saja)?\b/u',
            '/\bapa\s+yang\s+(bisa|dapat)\s+(kamu|anda)\s+(lakukan|bantu)\b/u',
            '/\b(kamu|anda)\s+ai\b/u',
            '/\b(apakah\s+kamu|apakah\s+anda)\s+(seorang\s+)?ai\b/u',
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query is asking about bot tasks or capabilities.
     */
    public function isBotCapabilityQuery(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $clean = ' ' . trim(preg_replace('/\s+/', ' ', $clean)) . ' ';

        if (preg_match('/\b(transaksi|belum bayar|belum membayar|menunggak|saldo)\b/u', $clean)) {
            return false;
        }

        $capPatterns = [
            '/\b(apa|apakah)\s+(tugasmu|tugas\s+kamu|tugas\s+anda|peranmu|peran\s+kamu|peran\s+anda|fungsimu|fungsi\s+kamu|fungsi\s+anda|kemampuanmu|kemampuan\s+kamu)\b/u',
            '/\b(tugasmu|tugas\s+kamu|peranmu|peran\s+kamu|fungsimu|fungsi\s+kamu|kemampuanmu|kemampuan\s+kamu)\s+(apa|apakah)\b/u',
            '/\b(kamu|anda)\s+(bisa|dapat)\s+apa(\s+saja)?\b/u',
            '/\bapa\s+yang\s+(bisa|dapat)\s+(kamu|anda)\s+(lakukan|bantu)\b/u',
        ];

        foreach ($capPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query is asking about authenticated user identity.
     */
    public function isUserIdentityQuery(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $clean = ' ' . trim(preg_replace('/\s+/', ' ', $clean)) . ' ';

        if (preg_match('/\b(transaksi|pembayaran|bayar|tunggakan|menunggak|pengeluaran|pemasukan|saldo)\b/u', $clean)) {
            return false;
        }

        $userPatterns = [
            '/\b(saya|aku|diriku)\s+(siapa|siapakah)\b/u',
            '/\b(siapa|siapakah)\s+(saya|aku|diriku)\b/u',
            '/\b(kamu|anda)?\s*(tahu|kenal|mengetahui)\s+(siapa\s+saya|saya\s+siapa|siapa\s+aku|aku\s+siapa)\b/u',
            '/\b(apakah\s+kamu|apakah\s+anda)?\s*(tahu|kenal)\s+(siapa\s+saya|saya\s+siapa)\b/u',
            '/\b(kamu|anda)\s+(tahu|kenal)\s+(saya|aku)\b/u',
            '/\b(siapa|siapakah)\s+(nama\s+saya|nama\s+aku|namaku)\b/u',
            '/\b(nama\s+saya|nama\s+aku|namaku)\s+(siapa|siapakah|apa)\b/u',
            '/\b(apa|apakah)\s+(nama\s+saya|nama\s+aku|namaku)\b/u',
            '/\b(saya|aku)\s+(punya|memiliki)?\s*role\s+(apa|apakah)\b/u',
            '/\b(apa|apakah)\s+(role\s+saya|role\s+aku|peran\s+saya|peran\s+aku|hak\s+akses\s+saya)\b/u',
            '/\b(role\s+saya|role\s+aku|peran\s+saya|peran\s+aku|hak\s+akses\s+saya)\s+(apa|apakah)\b/u',
            '/\b(apa|apakah)\s+(jabatan\s+saya|jabatan\s+aku|posisi\s+saya|posisi\s+aku)(\s+di\s+sistem(\s+ini)?)?\b/u',
            '/\b(jabatan\s+saya|jabatan\s+aku|posisi\s+saya|posisi\s+aku)\s+(apa|apakah)(\s+di\s+sistem(\s+ini)?)?\b/u',
            '/\b(profil\s+saya|profil\s+aku|identitas\s+saya|identitas\s+aku)\b/u',
            '/\bsiapa\s+(yang\s+)?(sedang\s+)?login\b/u',
        ];

        foreach ($userPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate bot identity response.
     */
    public function generateBotIdentityResponse(string $role, string $query = ''): string
    {
        if (!empty($query) && $this->isBotCapabilityQuery($query)) {
            return 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';
        }
        return 'Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.';
    }

    /**
     * Generate bot capability response.
     */
    public function generateBotCapabilityResponse(): string
    {
        return 'Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.';
    }

    /**
     * Generate authenticated user identity response.
     */
    public function generateUserIdentityResponse(array $userInfo, string $role, string $query = ''): string
    {
        $username = !empty($userInfo['username']) ? $userInfo['username'] : '-';
        $nama     = !empty($userInfo['nama']) ? $userInfo['nama'] : null;

        $roleLabel = match($role) {
            'super_user' => 'Super User (Administrator)',
            'bendahara'  => 'Bendahara',
            'anggota'    => 'Anggota',
            default      => ucfirst($role),
        };

        $q = mb_strtolower(trim($query), 'UTF-8');
        $userDesc = ($nama !== null && trim($nama) !== '') ? "{$nama} ({$username})" : $username;

        $extra = '';
        if (!empty($userInfo['kelas'])) {
            $extra .= " kelas {$userInfo['kelas']}";
        }
        if (!empty($userInfo['nomor_identitas'])) {
            $extra .= " (NIS: {$userInfo['nomor_identitas']})";
        }

        if (str_contains($q, 'role') || str_contains($q, 'jabatan') || str_contains($q, 'posisi') || str_contains($q, 'hak akses') || str_contains($q, 'peran')) {
            return "Role Anda di sistem adalah {$roleLabel} ({$userDesc}).";
        }

        if (str_contains($q, 'nama')) {
            if ($nama !== null && trim($nama) !== '') {
                return "Nama Anda yang terdaftar di sistem adalah {$nama} (username: {$username}).";
            }
            return "Nama profil belum diset untuk akun Anda (username: {$username}).";
        }

        $prefix = (str_contains($q, 'tahu') || str_contains($q, 'kenal')) ? 'Ya, ' : '';
        return "{$prefix}Anda masuk sebagai {$userDesc} dengan role {$roleLabel}{$extra}.";
    }

    /**
     * Build strict System Prompt with role boundaries and anti-hallucination rules.
     */
    protected function buildSystemPrompt(array $context, string $role, array $userInfo = [], bool $compact = false): string
    {
        $jsonContext = json_encode($context, $compact ? JSON_UNESCAPED_UNICODE : JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = "Anda adalah asisten AI untuk Sistem Kas Berbasis Web.\n\n";
        $prompt .= "PANDUAN UTAMA (WAJIB DIPATUHI SECARA KETAT):\n";
        $prompt .= "1. IDENTITAS DAN TUGAS AI:\n";
        $prompt .= "   - Jika pengguna bertanya mengenai identitas diri Anda (seperti 'Siapa Anda?', 'Kamu siapa?', 'Apa kamu?', atau 'Perkenalkan dirimu.'), jawab dengan satu kalimat yang informatif dan natural:\n";
        $prompt .= "     \"Saya adalah asisten AI Sistem Kas yang siap membantu Anda memahami informasi anggota, pembayaran, pemasukan, dan pengeluaran kas.\"\n";
        $prompt .= "   - Jika pengguna bertanya mengenai tugas Anda (seperti 'Apa tugas Anda?' atau 'Apa tugasmu?'), jawab dengan satu kalimat:\n";
        $prompt .= "     \"Saya membantu Anda mendapatkan informasi Sistem Kas dan memahami data keuangan sesuai hak akses akun Anda.\"\n\n";

        $prompt .= "2. ATURAN PANJANG JAWABAN (SINGKAT, INFORMATIF, DAN NATURAL):\n";
        $prompt .= "   - Untuk pertanyaan sederhana, utamakan satu kalimat yang informatif dan natural.\n";
        $prompt .= "   - Hindari paragraf panjang, penjelasan berulang, daftar yang tidak diperlukan, dan basa-basi.\n";
        $prompt .= "   - Jika pertanyaan meminta rincian atau daftar data, tampilkan informasi yang diperlukan dengan ringkas.\n";
        $prompt .= "   - Jangan membuat jawaban terlalu kaku atau terlalu pendek, dan jangan mengorbankan kejelasan atau akurasi hanya demi membuat jawaban pendek.\n";
        $prompt .= "   - Jangan selalu memulai jawaban dengan sapaan (seperti 'Halo', 'Hai', dsb.).\n";
        $prompt .= "   - Jangan menambahkan kesimpulan atau penawaran yang tidak diminta di akhir kalimat.\n\n";

        $prompt .= "3. TETAP AKURAT DAN ANTI-HALUSINASI (NO HALLUCINATION):\n";
        $prompt .= "   - Jawablah pertanyaan HANYA menggunakan data aktual dalam format JSON di bawah ini.\n";
        $prompt .= "   - DILARANG KERAS mengarang angka, nama tabel, atau data yang tidak tercantum dalam konteks data.\n";
        $prompt .= "   - Jika informasi atau data yang ditanyakan TIDAK TERSEDIA dalam konteks data, katakan secara singkat: \"Mohon maaf, data tersebut belum tersedia dalam sistem kas.\"\n";
        $prompt .= "   - Sistem bersifat READ-ONLY. AI TIDAK MEMILIKI AKSES dan DILARANG KERAS melakukan modifikasi/penghapusan data pada database.\n\n";

        $prompt .= "IDENTITAS PENGGUNA YANG SEDANG LOGIN (TERAUTENTIKASI):\n";
        $prompt .= "- Username: " . ($userInfo['username'] ?? 'Tidak diketahui') . "\n";
        if (!empty($userInfo['nama'])) {
            $prompt .= "- Nama: " . $userInfo['nama'] . "\n";
        }
        $prompt .= "- Role: " . $role . "\n";
        if (!empty($userInfo['kelas'])) {
            $prompt .= "- Kelas: " . $userInfo['kelas'] . "\n";
        }
        $prompt .= "- DILARANG MENGARANG identitas pengguna.\n\n";

        $prompt .= "PEMBATASAN AKSES ROLE:\n";
        if ($role === 'anggota') {
            $prompt .= "PENGGUNA ADALAH ANGGOTA BIASA. Dilarang keras membocorkan nama, rincian pembayaran, atau status tunggakan anggota lain! Jika ditanyakan tentang anggota lain atau jumlah seluruh anggota, tolak secara sopan sesuai privasi.\n";
        } elseif ($role === 'bendahara') {
            $prompt .= "PENGGUNA ADALAH BENDAHARA / STAFF KEUANGAN. Anda berhak memberikan ringkasan kas lengkap, detail transaksi, data pembayaran, daftar tunggakan anggota, dan statistik anggota.\n";
            $prompt .= "BATASAN BENDAHARA: Anda TIDAK boleh memberikan informasi tentang: (1) Jumlah/daftar akun pengguna sistem, (2) Rincian role user, (3) Log aktivitas/audit sistem. Jika ditanya hal tersebut, arahkan ke Administrator.\n";
        } else {
            $prompt .= "PENGGUNA ADALAH SUPER USER (ADMIN). Anda berhak memberikan ringkasan kas lengkap, data anggota, pembayaran, transaksi, data sistem (akun pengguna, log aktivitas), dan semua data.\n";
        }

        $prompt .= "Gunakan Bahasa Indonesia yang baku, jelas, dan langsung ke sasaran.\n\n";
        $prompt .= "=== KONTEKS DATA DATABASE SISTEM KAS ===\n";
        $prompt .= $jsonContext . "\n";

        return $prompt;
    }

    /**
     * Safely test connection and authentication to AI Provider without exposing secrets.
     */
    public function testAiConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'       => false,
                'status'        => 'missing_key',
                'provider'      => $this->provider,
                'model'         => $this->model,
                'message'       => 'AI_API_KEY belum dikonfigurasi di file .env. Masukkan API Key valid untuk mengaktifkan koneksi AI eksternal.',
                'authenticated' => false
            ];
        }

        $testPrompt = "Anda adalah sistem verifikasi koneksi AI Sistem Kas. Respon 'Koneksi AI berhasil diverifikasi.' jika Anda menerima pesan ini.";
        $testQuery  = "Verifikasi koneksi";

        $res = $this->callAiProvider($testPrompt, $testQuery);

        if (!empty($res['success']) && !empty($res['answer'])) {
            return [
                'success'       => true,
                'status'        => 'connected',
                'provider'      => $res['provider'],
                'model'         => $this->model,
                'message'       => 'Autentikasi dan komunikasi dengan layanan AI berhasil.',
                'authenticated' => true,
                'sample_output' => $res['answer']
            ];
        }

        return [
            'success'       => false,
            'status'        => $res['error_type'] ?? 'failed',
            'provider'      => $this->provider,
            'model'         => $this->model,
            'message'       => $res['error_message'] ?? 'Koneksi ke penyedia AI gagal.',
            'authenticated' => false
        ];
    }

    /**
     * Call AI Provider REST API (Google Gemini or OpenAI).
     */
    public function callAiProvider(string $systemPrompt, string $userQuery): array
    {
        if ($this->provider === 'gemini') {
            return $this->callGeminiApi($systemPrompt, $userQuery);
        } elseif ($this->provider === 'groq') {
            return $this->callGroqApi($systemPrompt, $userQuery);
        } elseif ($this->provider === 'openai') {
            return $this->callOpenAiApi($systemPrompt, $userQuery);
        }

        return [
            'success'       => false,
            'error_type'    => 'unsupported_provider',
            'error_message' => "Penyedia AI '{$this->provider}' tidak didukung.",
            'answer'        => null,
            'provider'      => $this->provider
        ];
    }

    /**
     * Call Groq Cloud REST API (OpenAI-compatible endpoint with SSL verification).
     */
    protected function callGroqApi(string $systemPrompt, string $userQuery): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'       => false,
                'error_type'    => 'missing_key',
                'error_message' => 'API Key Groq belum dikonfigurasi.',
                'answer'        => null,
                'provider'      => 'groq (' . $this->model . ')'
            ];
        }

        $url = "https://api.groq.com/openai/v1/chat/completions";

        $payload = [
            'model' => !empty($this->model) ? $this->model : 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userQuery]
            ],
            'temperature' => 0.2,
            'max_tokens' => 512
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: SistemKas-PHP-Groq/1.0'
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            $isTimeout = ($curlErrNo === CURLE_OPERATION_TIMEDOUT);
            return [
                'success'       => false,
                'error_type'    => $isTimeout ? 'timeout' : 'connection_error',
                'error_message' => $isTimeout
                    ? 'Request ke Groq API timeout setelah 20 detik.'
                    : 'Gagal terhubung ke Groq API: ' . $curlErrMsg,
                'answer'        => null,
                'provider'      => 'groq (' . $this->model . ')'
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data)) {
            if (isset($data['choices'][0]['message']['content'])) {
                $text = trim($data['choices'][0]['message']['content']);
                return [
                    'success'       => true,
                    'error_type'    => null,
                    'error_message' => null,
                    'answer'        => $text,
                    'provider'      => 'groq (' . $this->model . ')',
                    'finish_reason' => $data['choices'][0]['finish_reason'] ?? null
                ];
            }

            return [
                'success'       => false,
                'error_type'    => 'invalid_response',
                'error_message' => 'Format respons dari Groq API tidak valid.',
                'answer'        => null,
                'provider'      => 'groq (' . $this->model . ')'
            ];
        }

        $apiMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        return [
            'success'       => false,
            'error_type'    => ($httpCode === 401) ? 'invalid_key' : (($httpCode === 429) ? 'quota_exceeded' : 'api_error'),
            'error_message' => "Groq API error ({$httpCode}): " . $apiMsg,
            'answer'        => null,
            'provider'      => 'groq (' . $this->model . ')'
        ];
    }

    /**
     * Call Google Gemini REST API with comprehensive HTTP and cURL error handling.
     */
    protected function callGeminiApi(string $systemPrompt, string $userQuery): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'       => false,
                'error_type'    => 'missing_key',
                'error_message' => 'API Key Gemini belum dikonfigurasi di file .env (AI_API_KEY).',
                'answer'        => null,
                'provider'      => 'gemini (' . $this->model . ')'
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey);

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userQuery]]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            $isTimeout = ($curlErrNo === CURLE_OPERATION_TIMEDOUT);
            return [
                'success'       => false,
                'error_type'    => $isTimeout ? 'timeout' : 'connection_error',
                'error_message' => $isTimeout
                    ? 'Request ke Gemini API timeout setelah 15 detik.'
                    : 'Gagal terhubung ke Gemini API: ' . $curlErrMsg,
                'answer'        => null,
                'provider'      => 'gemini (' . $this->model . ')'
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data)) {
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = trim($data['candidates'][0]['content']['parts'][0]['text']);
                return [
                    'success'       => true,
                    'error_type'    => null,
                    'error_message' => null,
                    'answer'        => $text,
                    'provider'      => 'gemini (' . $this->model . ')'
                ];
            } else {
                return [
                    'success'       => false,
                    'error_type'    => 'invalid_response_format',
                    'error_message' => 'Format respons Gemini API tidak memuat kandidat teks.',
                    'answer'        => null,
                    'provider'      => 'gemini (' . $this->model . ')'
                ];
            }
        }

        // Handle specific API error responses
        $apiMessage = $data['error']['message'] ?? "HTTP error {$httpCode}";
        $apiStatus  = $data['error']['status'] ?? '';

        if ($httpCode === 400) {
            if (str_contains(strtolower($apiMessage), 'api_key_invalid') || str_contains(strtolower($apiMessage), 'api key not valid')) {
                $errType = 'invalid_key';
                $userMsg = 'API Key Gemini tidak valid. Silakan periksa kembali AI_API_KEY di .env.';
            } else {
                $errType = 'bad_request';
                $userMsg = 'Permintaan ke Gemini API tidak valid (Bad Request): ' . $apiMessage;
            }
        } elseif ($httpCode === 403 || $apiStatus === 'PERMISSION_DENIED') {
            $errType = 'invalid_key';
            $userMsg = 'Akses Gemini API ditolak (Permission Denied). API Key mungkin tidak valid atau belum diaktifkan.';
        } elseif ($httpCode === 429 || $apiStatus === 'RESOURCE_EXHAUSTED') {
            $errType = 'quota_exceeded';
            $userMsg = 'Batas kuota atau rate limit Gemini API tercapai (Resource Exhausted).';
        } elseif ($httpCode >= 500) {
            $errType = 'server_error';
            $userMsg = 'Server Gemini API sedang mengalami gangguan internal (HTTP ' . $httpCode . ').';
        } else {
            $errType = 'api_error';
            $userMsg = 'Gemini API mengembalikan kesalahan HTTP ' . $httpCode . ': ' . $apiMessage;
        }

        return [
            'success'       => false,
            'error_type'    => $errType,
            'error_message' => $userMsg,
            'answer'        => null,
            'provider'      => 'gemini (' . $this->model . ')'
        ];
    }

    /**
     * Call OpenAI REST API with comprehensive HTTP and cURL error handling.
     */
    protected function callOpenAiApi(string $systemPrompt, string $userQuery): array
    {
        if (empty($this->apiKey)) {
            return [
                'success'       => false,
                'error_type'    => 'missing_key',
                'error_message' => 'API Key OpenAI belum dikonfigurasi di file .env (AI_API_KEY).',
                'answer'        => null,
                'provider'      => 'openai (' . $this->model . ')'
            ];
        }

        $url = "https://api.openai.com/v1/chat/completions";

        $payload = [
            'model' => !empty($this->model) ? $this->model : 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userQuery]
            ],
            'temperature' => 0.2
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            $isTimeout = ($curlErrNo === CURLE_OPERATION_TIMEDOUT);
            return [
                'success'       => false,
                'error_type'    => $isTimeout ? 'timeout' : 'connection_error',
                'error_message' => $isTimeout
                    ? 'Request ke OpenAI API timeout setelah 15 detik.'
                    : 'Gagal terhubung ke OpenAI API: ' . $curlErrMsg,
                'answer'        => null,
                'provider'      => 'openai (' . $this->model . ')'
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && is_array($data)) {
            if (isset($data['choices'][0]['message']['content'])) {
                $text = trim($data['choices'][0]['message']['content']);
                return [
                    'success'       => true,
                    'error_type'    => null,
                    'error_message' => null,
                    'answer'        => $text,
                    'provider'      => 'openai (' . $this->model . ')'
                ];
            } else {
                return [
                    'success'       => false,
                    'error_type'    => 'invalid_response_format',
                    'error_message' => 'Format respons OpenAI API tidak memuat pesan teks.',
                    'answer'        => null,
                    'provider'      => 'openai (' . $this->model . ')'
                ];
            }
        }

        $apiMessage = $data['error']['message'] ?? "HTTP error {$httpCode}";
        $errType = match($httpCode) {
            401 => 'invalid_key',
            429 => 'quota_exceeded',
            default => ($httpCode >= 500 ? 'server_error' : 'api_error')
        };

        return [
            'success'       => false,
            'error_type'    => $errType,
            'error_message' => 'OpenAI API error (' . $httpCode . '): ' . $apiMessage,
            'answer'        => null,
            'provider'      => 'openai (' . $this->model . ')'
        ];
    }

    /**
     * Compact financial context for Groq to avoid HTTP 400 (prompt too long).
     * Keeps relevant summary/target data and drops bulky arrays unless the query asks for them.
     */
    protected function compactContextForGroq(array $context, string $query): array
    {
        $q = mb_strtolower($query, 'UTF-8');
        $compact = [];

        // Always keep small, high-value context.
        foreach (['role_akses', 'database_status', 'periode_laporan', 'tanggal_analisis', 'pengguna_login', 'query_tertarget'] as $key) {
            if (isset($context[$key])) {
                $compact[$key] = $context[$key];
            }
        }

        if (isset($context['ringkasan_kas']) && is_array($context['ringkasan_kas'])) {
            $compact['ringkasan_kas'] = $context['ringkasan_kas'];
        }

        // Member demographics only when relevant.
        $memberRelated = (
            str_contains($q, 'anggota') ||
            str_contains($q, 'member') ||
            str_contains($q, 'kelas') ||
            str_contains($q, 'gender') ||
            str_contains($q, 'laki') ||
            str_contains($q, 'perempuan')
        );
        if ($memberRelated && isset($context['informasi_anggota']) && is_array($context['informasi_anggota'])) {
            $info = $context['informasi_anggota'];
            $keepMembers = (
                str_contains($q, 'daftar anggota') ||
                str_contains($q, 'siapa saja anggota') ||
                str_contains($q, 'nama anggota') ||
                str_contains($q, 'anggota terdaftar')
            );
            $compact['informasi_anggota'] = [
                'total_anggota'          => $info['total_anggota'] ?? null,
                'total_anggota_aktif'    => $info['total_anggota_aktif'] ?? null,
                'total_anggota_nonaktif' => $info['total_anggota_nonaktif'] ?? null,
                'rincian_gender'         => $info['rincian_gender'] ?? null,
                'daftar_kelas'           => $info['daftar_kelas'] ?? null,
            ];
            if ($keepMembers && isset($info['daftar_anggota']) && is_array($info['daftar_anggota'])) {
                $compact['informasi_anggota']['daftar_anggota'] = array_slice($info['daftar_anggota'], 0, 20);
            }
        }

        // Arrears only when relevant.
        $arrearsRelated = (
            str_contains($q, 'tunggak') ||
            str_contains($q, 'nunggak') ||
            str_contains($q, 'belum') ||
            str_contains($q, 'lunas') ||
            str_contains($q, 'menunggak')
        );
        if ($arrearsRelated && isset($context['ringkasan_tunggakan']) && is_array($context['ringkasan_tunggakan'])) {
            $compact['ringkasan_tunggakan'] = $context['ringkasan_tunggakan'];
        }

        // Payments only when relevant.
        $paymentRelated = (
            str_contains($q, 'bayar') ||
            str_contains($q, 'pembayaran') ||
            str_contains($q, 'setoran') ||
            str_contains($q, 'pemasukan') ||
            str_contains($q, 'iuran')
        );
        if ($paymentRelated && isset($context['ringkasan_pembayaran_anggota']) && is_array($context['ringkasan_pembayaran_anggota'])) {
            $pay = $context['ringkasan_pembayaran_anggota'];
            $compact['ringkasan_pembayaran_anggota'] = [
                'total_semua_pembayaran_nominal'   => $pay['total_semua_pembayaran_nominal'] ?? null,
                'total_semua_pembayaran_transaksi' => $pay['total_semua_pembayaran_transaksi'] ?? null,
                'total_pembayaran_bulan_ini'       => $pay['total_pembayaran_bulan_ini'] ?? null,
                'jumlah_transaksi_pembayaran'      => $pay['jumlah_transaksi_pembayaran'] ?? null,
                'daftar_pembayaran_bulan_ini'      => $pay['daftar_pembayaran_bulan_ini'] ?? null,
            ];
            if (str_contains($q, 'per anggota') || str_contains($q, 'masing-masing') || str_contains($q, 'pembayaran anggota')) {
                if (isset($pay['pembayaran_per_anggota'])) {
                    $compact['ringkasan_pembayaran_anggota']['pembayaran_per_anggota'] = $pay['pembayaran_per_anggota'];
                }
                if (isset($pay['rincian_per_periode'])) {
                    $compact['ringkasan_pembayaran_anggota']['rincian_per_periode'] = $pay['rincian_per_periode'];
                }
            }
        }

        // Transactions only when relevant.
        $transactionRelated = (
            str_contains($q, 'transaksi') ||
            str_contains($q, 'riwayat') ||
            str_contains($q, 'kategori') ||
            str_contains($q, 'void') ||
            str_contains($q, 'batal')
        );
        if ($transactionRelated && isset($context['ringkasan_transaksi']) && is_array($context['ringkasan_transaksi'])) {
            $trx = $context['ringkasan_transaksi'];
            $compact['ringkasan_transaksi'] = [
                'total_transaksi_aktif' => $trx['total_transaksi_aktif'] ?? null,
                'total_transaksi_void'  => $trx['total_transaksi_void'] ?? null,
                'total_nominal_void'    => $trx['total_nominal_void'] ?? null,
                'kategori_transaksi'    => $trx['kategori_transaksi'] ?? null,
            ];
            if (str_contains($q, 'tanggal') || preg_match('/\d{4}-\d{2}-\d{2}/', $q)) {
                if (isset($trx['transaksi_per_tanggal'])) {
                    $compact['ringkasan_transaksi']['transaksi_per_tanggal'] = $trx['transaksi_per_tanggal'];
                }
            }
        }

        // Expenses only when relevant.
        $expenseRelated = (
            str_contains($q, 'pengeluaran') ||
            str_contains($q, 'keluar') ||
            str_contains($q, 'biaya') ||
            str_contains($q, 'detail') ||
            str_contains($q, 'rincian') ||
            str_contains($q, 'keterangan') ||
            str_contains($q, 'pencatat') ||
            str_contains($q, 'terbesar')
        );
        if ($expenseRelated) {
            if (isset($context['daftar_transaksi_pengeluaran']) && is_array($context['daftar_transaksi_pengeluaran'])) {
                $compact['daftar_transaksi_pengeluaran'] = array_slice($context['daftar_transaksi_pengeluaran'], 0, 15);
            }
            if (isset($context['pengeluaran_terbesar'])) {
                $compact['pengeluaran_terbesar'] = $context['pengeluaran_terbesar'];
            }
        }

        // Reconciliation only when relevant.
        $reconciliationRelated = (
            str_contains($q, 'saldo') &&
            (str_contains($q, 'pembayaran') || str_contains($q, 'iuran')) &&
            (str_contains($q, 'beda') || str_contains($q, 'berbeda') || str_contains($q, 'selisih') || str_contains($q, 'mengapa') || str_contains($q, 'kenapa'))
        );
        if ($reconciliationRelated && isset($context['rekonsiliasi_kas']) && is_array($context['rekonsiliasi_kas'])) {
            $compact['rekonsiliasi_kas'] = $context['rekonsiliasi_kas'];
        }

        // System/user/audit only when relevant.
        $systemRelated = (
            str_contains($q, 'user') ||
            str_contains($q, 'pengguna') ||
            str_contains($q, 'akun') ||
            str_contains($q, 'audit') ||
            str_contains($q, 'aktivitas') ||
            str_contains($q, 'log')
        );
        if ($systemRelated && isset($context['ringkasan_sistem']) && is_array($context['ringkasan_sistem'])) {
            $compact['ringkasan_sistem'] = $context['ringkasan_sistem'];
        }

        // Notifications only when relevant.
        $notificationRelated = (
            str_contains($q, 'notifikasi') ||
            str_contains($q, 'whatsapp') ||
            str_contains($q, 'wa')
        );
        if ($notificationRelated && isset($context['ringkasan_notifikasi_whatsapp']) && is_array($context['ringkasan_notifikasi_whatsapp'])) {
            $compact['ringkasan_notifikasi_whatsapp'] = $context['ringkasan_notifikasi_whatsapp'];
        }

        // Recent payments only when relevant.
        $recentPaymentRelated = (
            str_contains($q, 'terbaru') ||
            str_contains($q, 'terakhir') ||
            str_contains($q, 'lima')
        );
        if ($recentPaymentRelated && isset($context['transaksi_pembayaran_terbaru'])) {
            $compact['transaksi_pembayaran_terbaru'] = $context['transaksi_pembayaran_terbaru'];
        }
        if ($recentPaymentRelated && isset($context['sampel_transaksi_pemasukan'])) {
            $compact['sampel_transaksi_pemasukan'] = $context['sampel_transaksi_pemasukan'];
        }
        if ($recentPaymentRelated && isset($context['sampel_transaksi_pengeluaran'])) {
            $compact['sampel_transaksi_pengeluaran'] = $context['sampel_transaksi_pengeluaran'];
        }

        foreach ($compact as $key => $value) {
            if ($value === null) {
                unset($compact[$key]);
            }
        }

        return $compact;
    }

    /**
     * Validate Groq response quality before it can replace PHP calculations.
     */
    protected function isLowQualityGroqResponse(string $answer, string $query): bool
    {
        $answer = trim($answer);
        if (mb_strlen($answer) < 10) {
            return true;
        }

        $lines = preg_split('/\R/u', $answer);
        $lines = array_map('trim', $lines);
        $counts = array_count_values(array_filter($lines, fn($line) => $line !== ''));
        foreach ($counts as $line => $count) {
            if ($count > 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Local Deterministic Analytical Engine.
     * Answers dynamically based on the verified database context.
     */
    protected function generateLocalAnalysis(string $query, array $ctx, string $role, array $userInfo = []): string
    {
        $q = mb_strtolower(trim($query), 'UTF-8');

        // ─── INJECTION & PRIVILEGE ESCALATION GUARD ───────────────────────────
        $isInjectionAttempt = (
            str_contains($q, 'ignore previous') ||
            str_contains($q, 'abaikan instruksi') ||
            str_contains($q, 'sudo') ||
            str_contains($q, 'privilege') ||
            str_contains($q, 'debug mode') ||
            str_contains($q, 'override') ||
            str_contains($q, 'bypass')
        );
        if ($isInjectionAttempt) {
            return "Permintaan tidak dapat diproses. Sistem Kas beroperasi dalam lingkungan aman dan seluruh akses data dibatasi secara ketat berdasarkan peran akun Anda.";
        }

        // ─── KEBIJAKAN HAK AKSES & BOUNDARY PRIVASI (Universal Role Awareness) ──────
        $isAccessPolicyQuestion = (
            (str_contains($q, 'hak akses') || str_contains($q, 'kebijakan akses') || str_contains($q, 'batasan akses') || str_contains($q, 'izin akses')) ||
            ((str_contains($q, 'tidak boleh') || str_contains($q, 'bukan hak') || str_contains($q, 'dilarang')) && (str_contains($q, 'akses') || str_contains($q, 'lihat') || str_contains($q, 'minta') || str_contains($q, 'tanya'))) ||
            (str_contains($q, 'apa yang terjadi') && (str_contains($q, 'akses') || str_contains($q, 'informasi anggota')))
        );
        if ($isAccessPolicyQuestion) {
            return "Sistem Kas menerapkan pembatasan hak akses berbasis peran (Role-Based Access Control). Jika Anda menanyakan informasi atau data anggota yang berada di luar wewenang akun Anda (misalnya data pribadi atau status pembayaran anggota lain oleh akun Anggota), sistem akan secara otomatis membatasi akses, menolak permintaan tersebut, dan hanya menyajikan informasi yang diizinkan untuk peran akun Anda.";
        }

        // ─── ACCESS CONTROL GUARD (Strict Role Security) ───────────────────────
        if ($role === 'anggota') {
            $isAboutOtherMembers = (
                str_contains($q, 'anggota lain') ||
                (str_contains($q, 'siapa') && (str_contains($q, 'menunggak') || str_contains($q, 'nunggak') || str_contains($q, 'tunggak') || str_contains($q, 'anggota'))) ||
                (str_contains($q, 'daftar') && (str_contains($q, 'anggota') || str_contains($q, 'menunggak') || str_contains($q, 'tunggak') || str_contains($q, 'pembayar'))) ||
                (str_contains($q, 'siapa saja') && (str_contains($q, 'anggota') || str_contains($q, 'bayar') || str_contains($q, 'menunggak'))) ||
                (str_contains($q, 'siapa yang') && str_contains($q, 'bayar'))
            );
            if ($isAboutOtherMembers) {
                return "Maaf, Anda hanya dapat mengakses data pembayaran kas milik Anda sendiri.";
            }

            if (str_contains($q, 'berapa anggota') || str_contains($q, 'ada berapa anggota') || str_contains($q, 'jumlah anggota') || str_contains($q, 'total anggota') || (str_contains($q, 'anggota') && (str_contains($q, 'jumlah') || str_contains($q, 'total') || str_contains($q, 'seluruh') || str_contains($q, 'berapa')))) {
                return "Maaf, informasi jumlah anggota tidak dapat diakses oleh akun Anggota demi menjaga privasi data.";
            }

            if (str_contains($q, 'pengeluaran') || str_contains($q, 'uang keluar') || str_contains($q, 'biaya') || str_contains($q, 'pencatat') || str_contains($q, 'siapa yang mencatat')) {
                return "Rincian pengeluaran kas dan pencatat transaksi dikelola langsung oleh Bendahara dan Administrator.";
            }
        }

        // ─── QUERY TARGETED DARI PENCARIAN SPESIFIK ───────────────────────────
        $target = $ctx['query_tertarget'] ?? [];

        // ─── ANGGOTA PERSONAL FINANCIAL ANALYSIS ─────────────────────────────
        if ($role === 'anggota') {
            $status = $ctx['status_pembayaran_saya'] ?? [];
            $kasOrg = $ctx['info_kas_organisasi'] ?? [];

            // Transaksi / Riwayat umum
            if (str_contains($q, 'transaksi') || str_contains($q, 'riwayat')) {
                return "Informasi riwayat transaksi umum dibatasi dan dikelola oleh Bendahara dan Administrator.";
            }

            // Tunggakan / Status Pembayaran Pribadi
            if (str_contains($q, 'status') || str_contains($q, 'tunggak') || str_contains($q, 'nunggak') || str_contains($q, 'tagihan') || str_contains($q, 'iuran')) {
                $isLunas = ($status['status_bulan_ini'] ?? '') === 'lunas';
                $tunggakNom = number_format($status['estimasi_tunggakan'] ?? 0, 0, ',', '.');
                $mingguBelum = $status['minggu_belum_bayar'] ?? 0;

                if ($isLunas) {
                    return "Status pembayaran kas Anda bulan ini sudah lunas.";
                } else {
                    return "Anda memiliki tunggakan kas sebanyak {$mingguBelum} minggu dengan estimasi sebesar Rp {$tunggakNom}.";
                }
            }

            // Saldo Kas Organisasi
            if (str_contains($q, 'saldo') || str_contains($q, 'sisa kas') || str_contains($q, 'total kas') || str_contains($q, 'kas organisasi')) {
                $saldoKas = number_format($kasOrg['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
                return "Saldo kas organisasi saat ini sebesar Rp {$saldoKas}.";
            }

            // Pembayaran Saya
            if (str_contains($q, 'bayar') || str_contains($q, 'pembayaran') || str_contains($q, 'setoran') || str_contains($q, 'pemasukan')) {
                $terbayar = number_format($status['total_terbayar_bulan_ini'] ?? 0, 0, ',', '.');
                return "Total pembayaran kas Anda bulan ini sebesar Rp {$terbayar}.";
            }

            // Pengeluaran
            if (str_contains($q, 'pengeluaran') || str_contains($q, 'uang keluar') || str_contains($q, 'biaya')) {
                return "Rincian pengeluaran kas dikelola langsung oleh Bendahara dan Administrator.";
            }

            // Kondisi Pribadi
            if (str_contains($q, 'kondisi') || str_contains($q, 'surplus') || str_contains($q, 'defisit') || str_contains($q, 'analisis')) {
                $isLunas = ($status['status_bulan_ini'] ?? '') === 'lunas';
                $statusStr = $isLunas ? 'lunas' : 'menunggak';
                $tunggak = number_format($status['estimasi_tunggakan'] ?? 0, 0, ',', '.');
                return "Status pembayaran kas Anda bulan ini adalah {$statusStr} dengan estimasi tunggakan Rp {$tunggak}.";
            }
        }

        // ─── SUPER USER & BENDAHARA FINANCIAL ANALYSIS ───────────────────────
        $kas        = $ctx['ringkasan_kas'] ?? [];
        $tunggakan  = $ctx['ringkasan_tunggakan'] ?? [];
        $pembayaran = $ctx['ringkasan_pembayaran_anggota'] ?? [];
        $transaksi  = $ctx['ringkasan_transaksi'] ?? [];
        $infoAnggota= $ctx['informasi_anggota'] ?? [];
        $sistem     = $ctx['ringkasan_sistem'] ?? [];

        // Demografi Gender Anggota (diperiksa sebelum penghitungan umum)
        if (str_contains($q, 'laki-laki') || str_contains($q, 'perempuan') || str_contains($q, 'gender')) {
            $gender = $infoAnggota['rincian_gender'] ?? [];
            $l = $gender['L'] ?? 0;
            $p = $gender['P'] ?? 0;
            return "Terdapat {$l} anggota laki-laki dan {$p} anggota perempuan.";
        }

        // Kelas Anggota
        if (str_contains($q, 'kelas') && str_contains($q, 'anggota')) {
            $kelasList = !empty($infoAnggota['daftar_kelas']) ? implode(', ', array_map(fn($k, $v) => "{$k} ({$v} orang)", array_keys($infoAnggota['daftar_kelas']), $infoAnggota['daftar_kelas'])) : 'tercatat dalam sistem';
            $tot = (int)($infoAnggota['total_anggota'] ?? 0);
            return "Seluruh {$tot} anggota terdaftar berada di kelas {$kelasList}.";
        }

        // ─── 0a. PERBANDINGAN PENGELUARAN BULAN INI VS BULAN LALU ──────────────
        $isExpenseComparison = (
            (str_contains($q, 'banding') || str_contains($q, 'perbandingan')) &&
            (str_contains($q, 'pengeluaran') || str_contains($q, 'biaya'))
        );
        if ($isExpenseComparison) {
            $pBulanIni = number_format($kas['pengeluaran_bulan_ini'] ?? 0, 0, ',', '.');
            $pBulanLalu = number_format($kas['pengeluaran_bulan_lalu'] ?? 0, 0, ',', '.');
            $selisih = (float)($kas['pengeluaran_bulan_ini'] ?? 0) - (float)($kas['pengeluaran_bulan_lalu'] ?? 0);
            $selisihStr = number_format(abs($selisih), 0, ',', '.');
            $bulanIniLabel = $ctx['periode_laporan'] ?? 'bulan ini';
            $bulanLaluLabel = $kas['nama_bulan_lalu'] ?? 'bulan lalu';

            if ($selisih > 0) {
                $statusBanding = "kenaikan pengeluaran sebesar Rp {$selisihStr}";
            } elseif ($selisih < 0) {
                $statusBanding = "penurunan pengeluaran sebesar Rp {$selisihStr}";
            } else {
                $statusBanding = "nominal pengeluaran yang sama";
            }
            return "Pengeluaran kas bulan ini ({$bulanIniLabel}) sebesar Rp {$pBulanIni}, sedangkan pada bulan lalu ({$bulanLaluLabel}) tercatat Rp {$pBulanLalu} (terdapat {$statusBanding}).";
        }

        // ─── 0b. RINCIAN SALDO KAS ─────────────────────────────────────────────
        if (str_contains($q, 'rincian saldo') || str_contains($q, 'detail saldo') || str_contains($q, 'penjelasan saldo')) {
            $saldo = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            $pemasukan = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            $pengeluaran = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
            return "Rincian saldo kas saat ini adalah Rp {$saldo}, yang bersumber dari akumulasi total pemasukan kas sebesar Rp {$pemasukan} dikurangi total pengeluaran kas sebesar Rp {$pengeluaran}.";
        }

        // ─── 0c. RENTANG TANGGAL (DATE RANGE) ──────────────────────────────────
        if (!empty($target['rentang_tanggal_ditemukan'])) {
            $rawStart = $target['rentang_tanggal_ditemukan']['raw_start'];
            $rawEnd = $target['rentang_tanggal_ditemukan']['raw_end'];
            $isPengeluaranRange = (str_contains($q, 'pengeluaran') || str_contains($q, 'keluar'));
            $isPemasukanRange = (str_contains($q, 'pemasukan') || str_contains($q, 'masuk'));

            if ($isPengeluaranRange && !$isPemasukanRange) {
                $totalRentang = 0.0;
                $countRentang = 0;
                foreach ($ctx['daftar_transaksi_pengeluaran'] ?? [] as $t) {
                    $totalRentang += (float)$t['jumlah'];
                    $countRentang++;
                }
                $totStr = number_format($totalRentang, 0, ',', '.');
                return "Total pengeluaran kas pada rentang waktu {$rawStart} sampai {$rawEnd} adalah sebesar Rp {$totStr} (tercatat {$countRentang} transaksi).";
            } elseif ($isPemasukanRange && !$isPengeluaranRange) {
                $totalRentang = (float)($kas['total_pemasukan_semua'] ?? 0);
                $totStr = number_format($totalRentang, 0, ',', '.');
                return "Total pemasukan kas pada rentang waktu {$rawStart} sampai {$rawEnd} adalah sebesar Rp {$totStr}.";
            }
        }

        // ─── 0d. DETAIL, TUJUAN, PENCATAT, KANDIDAT, & MULTI-TABEL PENGELUARAN ─
        $isSpecificExpenseQuery = (
            !empty($target['pertanyaan_tujuan_pengeluaran']) ||
            !empty($target['pertanyaan_pencatat_pengeluaran']) ||
            !empty($target['transaksi_pengeluaran_terpilih']) ||
            !empty($target['kandidat_pengeluaran']) ||
            !empty($target['transaksi_pengeluaran_terbesar']) ||
            !empty($target['pengeluaran_nominal_tidak_ditemukan']) ||
            (str_contains($q, 'terbesar') && (str_contains($q, 'pengeluaran') || str_contains($q, 'keluar') || str_contains($q, 'transaksi'))) ||
            str_contains($q, 'buat apa') || str_contains($q, 'untuk apa') ||
            str_contains($q, 'tujuan pengeluaran') || str_contains($q, 'keperluan apa') ||
            str_contains($q, 'siapa yang mencatat') || str_contains($q, 'dicatat oleh siapa') || str_contains($q, 'siapa pencatat') ||
            (str_contains($q, 'pengeluaran') && (str_contains($q, 'detail') || str_contains($q, 'rincian') || str_contains($q, 'keterangan') || str_contains($q, 'itu') || str_contains($q, 'tadi') || str_contains($q, 'kemarin'))) ||
            (str_contains($q, 'uang') && str_contains($q, 'keluar') && (str_contains($q, 'apa') || str_contains($q, 'siapa')))
        );

        if ($isSpecificExpenseQuery) {
            // Case 1: Nominal dicari tetapi tidak ditemukan di database
            if (!empty($target['pengeluaran_nominal_tidak_ditemukan'])) {
                $nomStr = number_format($target['pengeluaran_nominal_tidak_ditemukan'], 0, ',', '.');
                return "Berdasarkan penelusuran data transaksi database Sistem Kas, tidak ditemukan transaksi pengeluaran kas senilai Rp {$nomStr}.";
            }

            // Case 2: Terdapat beberapa kandidat transaksi dengan nominal sama
            if (!empty($target['kandidat_pengeluaran'])) {
                $kandidat = $target['kandidat_pengeluaran'];
                $nomStr = number_format((float)$kandidat[0]['jumlah'], 0, ',', '.');
                $lines = ["Ditemukan " . count($kandidat) . " kandidat transaksi pengeluaran senilai Rp {$nomStr}:"];
                foreach ($kandidat as $idx => $kd) {
                    $ket = (trim($kd['keterangan'] ?? '') !== '' && trim($kd['keterangan']) !== '-') ? $kd['keterangan'] : ($kd['nama_kategori'] . ' (' . ($kd['deskripsi_kategori'] ?? '') . ')');
                    $pencatat = !empty($kd['creator_nama']) ? $kd['creator_nama'] : ($kd['creator_username'] ?? 'Petugas');
                    $lines[] = ($idx + 1) . ". Kode {$kd['kode_transaksi']} ({$kd['tanggal_transaksi']}) — {$ket}, dicatat oleh {$pencatat}";
                }
                $lines[] = "Silakan sebutkan kode transaksi atau tanggal yang Anda maksud untuk melihat rincian lengkapnya.";
                return implode("\n", $lines);
            }

            // Case 3: Menanyakan transaksi pengeluaran terbesar
            if (!empty($target['transaksi_pengeluaran_terbesar']) || ((str_contains($q, 'terbesar') || str_contains($q, 'paling banyak')) && (str_contains($q, 'pengeluaran') || str_contains($q, 'keluar')))) {
                $t = $target['transaksi_pengeluaran_terbesar'] ?? $ctx['pengeluaran_terbesar'] ?? null;
                if (!empty($t)) {
                    $nomStr = number_format((float)$t['jumlah'], 0, ',', '.');
                    $kode = $t['kode_transaksi'];
                    $tgl = $t['tanggal_transaksi'];
                    $kat = $t['nama_kategori'] ?? 'Pengeluaran Umum';
                    $desk = !empty($t['deskripsi_kategori']) ? " ({$t['deskripsi_kategori']})" : '';
                    $pencatat = !empty($t['creator_nama']) ? $t['creator_nama'] : ($t['creator_username'] ?? 'Petugas');
                    $ketDb = trim($t['keterangan'] ?? '');
                    $ketInfo = ($ketDb === '' || $ketDb === '-')
                        ? "Pada database, kolom keterangan tidak diisi rincian khusus (tercatat '-')."
                        : "Keterangan catatan: \"{$ketDb}\".";

                    return "Pengeluaran kas terbesar yang tercatat adalah sebesar Rp {$nomStr} (kode: {$kode} pada tanggal {$tgl}) untuk kategori {$kat}{$desk}, dicatat oleh {$pencatat}. {$ketInfo}";
                } else {
                    return "Belum ada transaksi pengeluaran yang tercatat pada database.";
                }
            }

            // Case 4: Transaksi spesifik terpilih (dari nominal atau konteks/follow-up)
            $t = $target['transaksi_pengeluaran_terpilih'] ?? null;
            if (empty($t) && !empty($ctx['daftar_transaksi_pengeluaran'])) {
                $t = $ctx['daftar_transaksi_pengeluaran'][0];
            }

            if (!empty($t)) {
                $nomStr = number_format((float)$t['jumlah'], 0, ',', '.');
                $kode = $t['kode_transaksi'];
                $tgl = $t['tanggal_transaksi'];
                $kat = $t['nama_kategori'] ?? 'Pengeluaran Umum';
                $desk = !empty($t['deskripsi_kategori']) ? " ({$t['deskripsi_kategori']})" : '';
                $pencatat = !empty($t['creator_nama']) ? $t['creator_nama'] : ($t['creator_username'] ?? 'Petugas');
                $ketDb = trim($t['keterangan'] ?? '');

                // Sub-case: Hanya menanyakan pencatat transaksi
                if ((str_contains($q, 'siapa yang mencatat') || str_contains($q, 'dicatat oleh siapa') || str_contains($q, 'siapa pencatat') || str_contains($q, 'mencatatnya')) && !str_contains($q, 'buat apa') && !str_contains($q, 'untuk apa')) {
                    return "Transaksi pengeluaran kas sebesar Rp {$nomStr} (kode: {$kode} pada tanggal {$tgl}) dicatat oleh {$pencatat}.";
                }

                // Sub-case: Menanyakan kapan transaksi dilakukan
                if (str_contains($q, 'kapan') && (str_contains($q, 'transaksi') || str_contains($q, 'pengeluaran') || str_contains($q, 'dilakukan'))) {
                    return "Transaksi pengeluaran kas sebesar Rp {$nomStr} (kode: {$kode}) dilakukan pada tanggal {$tgl} dan dicatat oleh {$pencatat}.";
                }

                // Sub-case: Menanyakan tujuan / rincian / buat apa
                $ketInfo = ($ketDb === '' || $ketDb === '-')
                    ? "Pada catatan transaksi, kolom keterangan tidak diisi secara spesifik (tercatat '-')."
                    : "Keterangan catatan: \"{$ketDb}\".";

                return "Pengeluaran kas sebesar Rp {$nomStr} (kode: {$kode} tanggal {$tgl}) tercatat pada kategori {$kat}{$desk} dan dicatat oleh {$pencatat}. {$ketInfo}";
            }

            return "Berdasarkan data database Sistem Kas, belum ada catatan transaksi pengeluaran yang sesuai.";
        }

        // 1. STATUS PEMBAYARAN ANGGOTA (lunas vs menunggak summary)
        $isMemberPayStatus = (
            (str_contains($q, 'lunas') && (str_contains($q, 'menunggak') || str_contains($q, 'tunggak') || str_contains($q, 'nunggak') || str_contains($q, 'belum'))) ||
            (str_contains($q, 'berapa') && str_contains($q, 'anggota') && (str_contains($q, 'lunas') || str_contains($q, 'menunggak') || str_contains($q, 'nunggak') || str_contains($q, 'belum bayar'))) ||
            (str_contains($q, 'sudah lunas') && str_contains($q, 'anggota')) ||
            (str_contains($q, 'anggota') && str_contains($q, 'belum membayar'))
        );
        if ($isMemberPayStatus) {
            if (!isset($ctx['ringkasan_tunggakan']) || !is_array($ctx['ringkasan_tunggakan'])) {
                return "Mohon maaf, data status pembayaran anggota belum tersedia dalam sistem kas.";
            }
            $jmlLunas     = (int)($tunggakan['jumlah_anggota_lunas'] ?? 0);
            $jmlMenunggak = (int)($tunggakan['jumlah_anggota_menunggak'] ?? 0);
            $totalAnggota = isset($infoAnggota['total_anggota']) ? (int)$infoAnggota['total_anggota'] : ($jmlLunas + $jmlMenunggak);
            $periodeLaporan = $ctx['periode_laporan'] ?? 'periode ini';

            if ($jmlMenunggak === 0) {
                return "Pada {$periodeLaporan}, seluruh {$jmlLunas} anggota telah lunas dan tidak ada yang menunggak.";
            }

            $daftar = $tunggakan['daftar_anggota_menunggak'] ?? [];
            $namaArr = array_map(fn($m) => $m['nama'], array_slice($daftar, 0, 3));
            $nmStr = implode(', ', $namaArr);
            if (count($daftar) > 3) $nmStr .= ', dan ' . (count($daftar) - 3) . ' lainnya';

            return "Pada {$periodeLaporan}, {$jmlLunas} dari {$totalAnggota} anggota sudah lunas dan {$jmlMenunggak} masih menunggak" . ($nmStr ? " ({$nmStr})" : '') . ".";
        }

        // 1b. INFORMASI JUMLAH ANGGOTA
        // Exclude explicit payment queries that happen to mention 'anggota' — they should route to payment handlers
        $isExplicitPaymentQuery = (
            (str_contains($q, 'pembayaran') && (str_contains($q, 'total') || str_contains($q, 'seluruh') || str_contains($q, 'berapa'))) ||
            (str_contains($q, 'setoran') && (str_contains($q, 'total') || str_contains($q, 'berapa'))) ||
            (str_contains($q, 'anggota') && (str_contains($q, 'membayar') || str_contains($q, 'bayar')) && (str_contains($q, 'berapa') || str_contains($q, 'jumlah')))
        );
        $isMemberCountQuery = (
            !$isExplicitPaymentQuery &&
            !str_contains($q, 'lunas') && !str_contains($q, 'menunggak') && !str_contains($q, 'nunggak') && !str_contains($q, 'tunggak') && !str_contains($q, 'belum bayar') &&
            (
                str_contains($q, 'berapa anggota') ||
                str_contains($q, 'ada berapa anggota') ||
                str_contains($q, 'jumlah anggota') ||
                str_contains($q, 'total anggota') ||
                str_contains($q, 'anggota terdaftar') ||
                (str_contains($q, 'anggota') && (str_contains($q, 'jumlah') || str_contains($q, 'total') || str_contains($q, 'berapa'))) ||
                (str_contains($q, 'anggota') && str_contains($q, 'aktif') && !str_contains($q, 'bayar')) ||
                (str_contains($q, 'siapa saja') && str_contains($q, 'anggota') && !str_contains($q, 'bayar'))
            )
        );
        if ($isMemberCountQuery) {
            if (!isset($ctx['informasi_anggota']) || !is_array($ctx['informasi_anggota'])) {
                return "Mohon maaf, data anggota belum tersedia dalam sistem kas.";
            }
            $total    = (int)($infoAnggota['total_anggota'] ?? 0);
            $aktif    = (int)($infoAnggota['total_anggota_aktif'] ?? 0);
            $nonAktif = (int)($infoAnggota['total_anggota_nonaktif'] ?? 0);

            if (str_contains($q, 'siapa saja')) {
                return "Total anggota terdaftar adalah {$total} orang; daftar lengkap dapat dilihat pada menu Data Anggota.";
            }

            if (str_contains($q, 'seluruh') && str_contains($q, 'aktif')) {
                $kelasList = !empty($infoAnggota['daftar_kelas']) ? implode(', ', array_keys($infoAnggota['daftar_kelas'])) : '';
                $kelasStr = $kelasList ? " di kelas {$kelasList}" : "";
                return "Total anggota terdaftar adalah {$total} orang dan seluruhnya berstatus aktif{$kelasStr}.";
            }

            if ($nonAktif > 0) {
                return "Total anggota terdaftar adalah {$total} orang ({$aktif} aktif dan {$nonAktif} tidak aktif).";
            }

            return "Total anggota terdaftar adalah {$total} orang.";
        }

        // 1c. TRANSAKSI PEMBAYARAN TERBARU (top-5)
        $periodeLaporan = $ctx['periode_laporan'] ?? 'bulan ini';
        $trxTerbaru = $ctx['transaksi_pembayaran_terbaru'] ?? [];
        $isRecentTrxQuery = (
            ((str_contains($q, 'terbaru') || str_contains($q, 'terakhir')) && (str_contains($q, 'transaksi') || str_contains($q, 'pembayaran'))) ||
            ((str_contains($q, 'lima') || str_contains($q, '5')) && (str_contains($q, 'transaksi') || str_contains($q, 'pembayaran'))) ||
            (str_contains($q, 'tampilkan') && (str_contains($q, 'transaksi') || str_contains($q, 'pembayaran')))
        );
        if ($isRecentTrxQuery) {
            if (!isset($ctx['transaksi_pembayaran_terbaru']) && !isset($ctx['sampel_transaksi_pemasukan'])) {
                return "Mohon maaf, data transaksi pembayaran terbaru belum tersedia dalam sistem kas.";
            }
            if (empty($trxTerbaru)) {
                $sampel = $ctx['sampel_transaksi_pemasukan'] ?? [];
                if (empty($sampel)) return "Belum ada transaksi pembayaran yang tercatat.";
                $lines = [];
                foreach (array_slice($sampel, 0, 5) as $idx => $t) {
                    $nom = number_format((float)($t['jumlah'] ?? 0), 0, ',', '.');
                    $lines[] = ($idx + 1) . '. ' . ($t['tanggal'] ?? '-') . ' — Rp ' . $nom . ' (' . ($t['keterangan'] ?? $t['kategori'] ?? '-') . ')';
                }
                return "Lima transaksi pemasukan terbaru:\n" . implode("\n", $lines);
            }
            $limit = min(5, count($trxTerbaru));
            $lines = [];
            for ($i = 0; $i < $limit; $i++) {
                $t = $trxTerbaru[$i];
                $nom = number_format((float)($t['nominal'] ?? 0), 0, ',', '.');
                $lines[] = ($i + 1) . '. ' . ($t['nama_anggota'] ?? $t['nama'] ?? 'Anggota') . ' — Rp ' . $nom . ' (' . ($t['tanggal_bayar'] ?? '-') . ')';
            }
            return "Lima transaksi pembayaran terbaru:\n" . implode("\n", $lines);
        }

        // 1d. RINGKASAN KEUANGAN LENGKAP (pemasukan + pengeluaran + saldo combined)
        // WAJIB sebelum individual pemasukan/pengeluaran checks!
        $isFullFinancialSummary = (
            (str_contains($q, 'pemasukan') && str_contains($q, 'pengeluaran') && (str_contains($q, 'saldo') || str_contains($q, 'kas'))) ||
            (str_contains($q, 'total pemasukan') && str_contains($q, 'total pengeluaran')) ||
            (str_contains($q, 'pemasukan') && str_contains($q, 'pengeluaran') && str_contains($q, 'total')) ||
            (str_contains($q, 'arus kas') && str_contains($q, 'saldo'))
        );
        if ($isFullFinancialSummary) {
            if (!isset($ctx['ringkasan_kas']) || !is_array($ctx['ringkasan_kas'])) {
                return "Mohon maaf, data keuangan kas belum tersedia dalam sistem kas.";
            }
            $pemasukan   = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            $pengeluaran = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
            $saldo       = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            return "Total pemasukan kas: Rp {$pemasukan}, total pengeluaran: Rp {$pengeluaran}, saldo kas saat ini: Rp {$saldo}.";
        }

        // 1e. QUERY TAHUN SPESIFIK (misal: "pemasukan tahun 2026", "saldo tahun 2025")
        if (preg_match('/\b(202[0-9])\b/u', $q, $yearMatch) && !preg_match('/(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)/u', $q)) {
            $detectedYear = (int)$yearMatch[1];
            if (str_contains($q, 'pemasukan') || str_contains($q, 'transaksi masuk')) {
                $rincianPeriode = $pembayaran['rincian_per_periode'] ?? [];
                $totalTahunIni = 0.0;
                foreach ($rincianPeriode as $prd) {
                    if ((int)($prd['periode_tahun'] ?? 0) === $detectedYear) {
                        $totalTahunIni += (float)($prd['total_nominal'] ?? 0);
                    }
                }
                $allIncome = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
                $yIncome = number_format($totalTahunIni, 0, ',', '.');
                return "Total pemasukan kas tahun {$detectedYear} (berdasarkan pembayaran iuran yang tercatat) sebesar Rp {$yIncome}. Total pemasukan keseluruhan semua periode: Rp {$allIncome}.";
            }
            if (str_contains($q, 'pengeluaran')) {
                $pengTotal = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
                return "Total pengeluaran kas yang tercatat pada seluruh periode adalah Rp {$pengTotal}. Untuk filter per tahun yang lebih detail, silakan cek menu Laporan Keuangan.";
            }
            if (str_contains($q, 'saldo')) {
                $saldo = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
                $pemasukan = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
                $pengeluaran = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
                return "Saldo kas saat ini (akumulasi seluruh periode hingga {$detectedYear}) sebesar Rp {$saldo}, dengan total pemasukan Rp {$pemasukan} dan total pengeluaran Rp {$pengeluaran}.";
            }
        }

        // 2a0. TUNGGAKAN MULTIANGGOTA (nama anggota + periode)
        // Hitung tunggakan setiap anggota yang disebut berdasarkan daftar menunggak periode.
        $isArrearsQuery = (
            str_contains($q, 'tunggak') ||
            str_contains($q, 'nunggak') ||
            str_contains($q, 'belum')
        );
        if ($isArrearsQuery && !empty($target['periode']) && !empty($target['anggota_terpilih_list'])) {
            $pTarget = $target['periode'];
            $namaBulanTgt = $pTarget['nama_bulan'];
            $tahunTgt = $pTarget['tahun'];

            $menunggakMap = [];
            foreach (($pTarget['daftar_menunggak'] ?? []) as $mng) {
                $menunggakMap[mb_strtolower((string)($mng['nama'] ?? ''), 'UTF-8')] = $mng;
            }

            $lines = [];
            foreach ($target['anggota_terpilih_list'] as $agData) {
                $namaAg = $agData['profil']['nama'];
                $mng = $menunggakMap[mb_strtolower($namaAg, 'UTF-8')] ?? null;
                $est = $mng ? (float)($mng['estimasi_tunggakan'] ?? 0) : 0.0;
                $minggu = $mng ? (int)($mng['minggu_belum_bayar'] ?? 0) : 0;
                $statusStr = $mng ? "menunggak {$minggu} minggu" : 'lunas';
                $lines[] = "{$namaAg}: Rp " . number_format($est, 0, ',', '.') . " ({$statusStr})";
            }

            return "Nominal tunggakan pada periode {$namaBulanTgt} {$tahunTgt}: " . implode('; ', $lines) . ".";
        }

        // 2a. KOMBINASI ANGGOTA SPESIFIK + PERIODE SPESIFIK
        // Prioritaskan anggota jika nama anggota dan bulan disebut bersamaan.
        $isPaymentQuery = (
            str_contains($q, 'bayar') ||
            str_contains($q, 'pembayaran') ||
            str_contains($q, 'setoran') ||
            str_contains($q, 'pemasukan')
        );
        if ($isPaymentQuery && !$isArrearsQuery && !empty($target['anggota_terpilih']) && !empty($target['periode'])) {
            $agData = $target['anggota_terpilih'];
            $namaAg = $agData['profil']['nama'];
            $pInfo  = $agData['pembayaran'];
            $pTarget = $target['periode'];
            $namaBulanTgt = $pTarget['nama_bulan'];
            $tahunTgt = $pTarget['tahun'];

            $matchedCount = 0;
            $matchedTotal = 0.0;
            foreach (($pInfo['rincian'] ?? []) as $r) {
                if (trim((string)($r['periode'] ?? '')) === trim("{$namaBulanTgt} {$tahunTgt}")) {
                    $matchedCount++;
                    $matchedTotal += (float)($r['nominal'] ?? 0);
                }
            }

            $totStr = number_format($matchedTotal, 0, ',', '.');
            if ($matchedCount === 0) {
                return "{$namaAg} tidak memiliki catatan pembayaran pada periode {$namaBulanTgt} {$tahunTgt}.";
            }

            return "{$namaAg} melakukan {$matchedCount} pembayaran pada periode {$namaBulanTgt} {$tahunTgt} dengan total nominal Rp {$totStr}.";
        }

        // 2. KELUARGA QUERY PERIODE SPESIFIK (e.g. Pembayaran bulan September / Oktober / November)
        if (!empty($target['periode'])) {
            $pTarget = $target['periode'];
            $namaBulanTgt = $pTarget['nama_bulan'];
            $tahunTgt = $pTarget['tahun'];
            $nomTgt = number_format($pTarget['total_nominal'], 0, ',', '.');
            $jmlTrxTgt = $pTarget['jumlah_transaksi'];
            $jmlTunggakTgt = $pTarget['jumlah_menunggak'] ?? 0;

            // Jika menanyakan siapa yang menunggak pada periode tersebut
            if (str_contains($q, 'tunggak') || str_contains($q, 'nunggak') || (str_contains($q, 'siapa') && (str_contains($q, 'belum') || str_contains($q, 'tunggak')))) {
                if ($jmlTunggakTgt === 0) {
                    return "Tidak ada anggota yang menunggak untuk periode {$namaBulanTgt} {$tahunTgt}.";
                }
                $listT = [];
                foreach ($pTarget['daftar_menunggak'] as $m) {
                    $est = number_format($m['estimasi_tunggakan'], 0, ',', '.');
                    $listT[] = "{$m['nama']} (Rp {$est})";
                }
                return "Terdapat {$jmlTunggakTgt} anggota yang menunggak pada periode {$namaBulanTgt} {$tahunTgt}: " . implode(', ', $listT) . ".";
            }

            // Jika menanyakan siapa yang sudah bayar pada periode tersebut
            if (str_contains($q, 'siapa') && (str_contains($q, 'bayar') || str_contains($q, 'lunas'))) {
                $payList = $pTarget['daftar_pembayar'] ?? [];
                if (empty($payList)) {
                    return "Belum ada catatan pembayaran pada periode {$namaBulanTgt} {$tahunTgt}.";
                }
                $names = [];
                foreach ($payList as $pl) {
                    $nom = number_format($pl['nominal'], 0, ',', '.');
                    $names[] = "{$pl['nama']} (Rp {$nom})";
                }
                return "Pada periode {$namaBulanTgt} {$tahunTgt}, " . count($names) . " anggota telah membayar: " . implode(', ', $names) . ".";
            }

            // Menanyakan jumlah anggota yang membayar dan/atau total uang pembayaran pada periode tertentu
            $isPayingMemberCountQuery = (
                (preg_match('/(berapa|jumlah|banyak)\s+(anggota|orang)\s+(yang\s+)?(sudah\s+)?(membayar|bayar)/u', $q)) ||
                (str_contains($q, 'jumlah anggota yang membayar') || str_contains($q, 'berapa anggota yang membayar') || str_contains($q, 'berapa jumlah anggota yang membayar'))
            );
            $isPaymentAmountQuery = (
                str_contains($q, 'total') ||
                str_contains($q, 'nominal') ||
                str_contains($q, 'uang') ||
                str_contains($q, 'biaya') ||
                str_contains($q, 'dana') ||
                str_contains($q, 'berapa yang dibayarkan') ||
                str_contains($q, 'total pembayaran') ||
                str_contains($q, 'total uang')
            );

            // Jika GABUNGAN: menanyakan jumlah anggota yang membayar DAN total nominal uang yang dibayarkan
            if ($isPayingMemberCountQuery && $isPaymentAmountQuery) {
                $jmlAnggotaBayar = $pTarget['jumlah_anggota_bayar'] ?? 0;
                $totAnggota = (int)($infoAnggota['total_anggota'] ?? 8);
                return "Pada periode {$namaBulanTgt} {$tahunTgt}, terdapat {$jmlAnggotaBayar} dari {$totAnggota} anggota yang telah membayar kas dengan total nominal uang yang dibayarkan sebesar Rp {$nomTgt} (dari {$jmlTrxTgt} transaksi pembayaran).";
            }

            // Jika HANYA menanyakan jumlah anggota yang membayar
            if ($isPayingMemberCountQuery) {
                $jmlAnggotaBayar = $pTarget['jumlah_anggota_bayar'] ?? 0;
                $totAnggota = (int)($infoAnggota['total_anggota'] ?? 8);
                return "Pada periode {$namaBulanTgt} {$tahunTgt}, terdapat {$jmlAnggotaBayar} dari {$totAnggota} anggota yang telah membayar kas.";
            }

            // Jika menanyakan total pembayaran pada periode tersebut
            if (str_contains($q, 'bayar') || str_contains($q, 'pembayaran') || str_contains($q, 'total') || str_contains($q, 'berapa')) {
                return "Total pembayaran kas pada periode {$namaBulanTgt} {$tahunTgt} adalah sebesar Rp {$nomTgt} dari {$jmlTrxTgt} transaksi pembayaran.";
            }
        }

        // 3. QUERY ANGGOTA SPESIFIK (e.g. Pembayaran Ahmad Rizky, Dimas Prayoga, Sukma, dll.)
        if (!empty($target['anggota_terpilih'])) {
            $agData = $target['anggota_terpilih'];
            $namaAg = $agData['profil']['nama'];
            $pInfo  = $agData['pembayaran'];
            $totAg  = number_format($pInfo['total_nominal'], 0, ',', '.');
            $countAg = $pInfo['jumlah_transaksi'];

            $rincianArr = [];
            foreach ($pInfo['rincian'] as $r) {
                $rNom = number_format($r['nominal'], 0, ',', '.');
                $rincianArr[] = "{$r['periode']}: Rp {$rNom}";
            }
            $rincianStr = !empty($rincianArr) ? ' (' . implode(', ', $rincianArr) . ')' : '';

            return "{$namaAg} telah membayar total Rp {$totAg} dari {$countAg} transaksi{$rincianStr}.";
        }

        // 4. QUERY TRANSAKSI BERDASARKAN STATUS (Aktif vs Void)
        if (str_contains($q, 'void') || str_contains($q, 'batal') || str_contains($q, 'dibatalkan')) {
            if (!isset($ctx['ringkasan_transaksi']) || !is_array($ctx['ringkasan_transaksi'])) {
                return "Mohon maaf, data status transaksi belum tersedia dalam sistem kas.";
            }
            $aktifCount = (int)($transaksi['total_transaksi_aktif'] ?? 0);
            $voidCount  = (int)($transaksi['total_transaksi_void'] ?? 0);
            $voidNom    = number_format($transaksi['total_nominal_void'] ?? 0, 0, ',', '.');
            $aktifNom   = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            return "Terdapat {$aktifCount} transaksi kas aktif senilai Rp {$aktifNom} dan {$voidCount} transaksi void (dibatalkan) senilai Rp {$voidNom}.";
        }

        // 5. QUERY TRANSAKSI BERDASARKAN TANGGAL
        if (!empty($target['tanggal_transaksi'])) {
            $tgl = $target['tanggal_transaksi']['tanggal'];
            $rincianTgl = $target['tanggal_transaksi']['rincian'];
            $totalTgl = 0.0;
            $countTgl = 0;
            foreach ($rincianTgl as $rt) {
                $totalTgl += (float)$rt['total'];
                $countTgl += (int)$rt['jml'];
            }
            $totStr = number_format($totalTgl, 0, ',', '.');
            return "Pada tanggal {$tgl} terdapat {$countTgl} transaksi pemasukan aktif dengan total Rp {$totStr}.";
        }

        // 6. QUERY TRANSAKSI BERDASARKAN KATEGORI
        if (str_contains($q, 'kategori')) {
            if (!isset($ctx['ringkasan_transaksi']['kategori_transaksi'])) {
                return "Mohon maaf, data kategori transaksi belum tersedia dalam sistem kas.";
            }
            $katList = $transaksi['kategori_transaksi'] ?? [];
            $katNames = array_map(fn($k) => $k['nama_kategori'], $katList);
            $aktifKatNom = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            $jmlKat = count($katList);
            return "Terdapat {$jmlKat} kategori transaksi (" . implode(', ', $katNames) . ") dengan total transaksi aktif sebesar Rp {$aktifKatNom}.";
        }

        // 7. QUERY SIAPA YANG SUDAH MEMBAYAR & NOMINALNYA
        $isWhoPaidQuery = (
            (str_contains($q, 'siapa') && str_contains($q, 'bayar') && !str_contains($q, 'belum') && !str_contains($q, 'tunggak') && !str_contains($q, 'nunggak')) ||
            (str_contains($q, 'yang sudah bayar') || str_contains($q, 'yang telah bayar') || str_contains($q, 'daftar pembayar'))
        );
        if ($isWhoPaidQuery) {
            if (!isset($ctx['ringkasan_pembayaran_anggota'])) {
                return "Mohon maaf, data pembayaran anggota belum tersedia dalam sistem kas.";
            }
            $pembayarList = $pembayaran['daftar_pembayaran_bulan_ini'] ?? [];
            if (empty($pembayarList)) {
                return "Belum ada transaksi pembayaran untuk bulan ini.";
            }

            $names = [];
            foreach ($pembayarList as $pb) {
                $nom = number_format($pb['nominal'], 0, ',', '.');
                $names[] = "{$pb['nama']} (Rp {$nom})";
            }
            $totalBulanIni = number_format($pembayaran['total_pembayaran_bulan_ini'] ?? 0, 0, ',', '.');
            $countPembayar = count($pembayarList);
            return "Pada {$periodeLaporan}, {$countPembayar} anggota telah membayar dengan total Rp {$totalBulanIni}: " . implode(', ', $names) . ".";
        }

        // 8. QUERY TUNGGAKAN & BELUM BAYAR (Current Period)
        $isTunggakan = (
            str_contains($q, 'belum membayar') ||
            str_contains($q, 'tunggakan') ||
            str_contains($q, 'menunggak') ||
            str_contains($q, 'nunggak') ||
            (str_contains($q, 'siapa') && (str_contains($q, 'belum') || str_contains($q, 'tunggak') || str_contains($q, 'menunggak')))
        );
        if ($isTunggakan) {
            if (!isset($ctx['ringkasan_tunggakan']) || !is_array($ctx['ringkasan_tunggakan'])) {
                return "Mohon maaf, data tunggakan kas belum tersedia dalam sistem kas.";
            }
            $totalTunggakan = number_format($tunggakan['total_estimasi_tunggakan'] ?? 0, 0, ',', '.');
            $jmlMenunggak   = (int)($tunggakan['jumlah_anggota_menunggak'] ?? 0);
            $daftar = $tunggakan['daftar_anggota_menunggak'] ?? [];

            if ($jmlMenunggak === 0 || empty($daftar)) {
                return "Tidak ada anggota yang menunggak untuk periode ini.";
            }

            $names = [];
            $limit = min(5, count($daftar));
            for ($i = 0; $i < $limit; $i++) {
                $item = $daftar[$i];
                $nom = number_format($item['estimasi_tunggakan'] ?? 0, 0, ',', '.');
                $names[] = "{$item['nama']} (Rp {$nom})";
            }
            $listStr = implode(', ', $names);
            if (count($daftar) > $limit) {
                $sisa = count($daftar) - $limit;
                $listStr .= ", dan {$sisa} anggota lainnya";
            }

            return "Terdapat {$jmlMenunggak} anggota yang menunggak dengan total estimasi Rp {$totalTunggakan}: {$listStr}.";
        }

        // 8b. QUERY REKONSILIASI / PERBEDAAN SALDO KAS VS TOTAL PEMBAYARAN IURAN
        $isReconciliationQuery = (
            str_contains($q, 'saldo') &&
            (str_contains($q, 'pembayaran') || str_contains($q, 'iuran')) &&
            (
                str_contains($q, 'beda') ||
                str_contains($q, 'berbeda') ||
                str_contains($q, 'perbedaan') ||
                str_contains($q, 'selisih') ||
                str_contains($q, 'mengapa') ||
                str_contains($q, 'kenapa') ||
                str_contains($q, 'sama') ||
                str_contains($q, 'alasan') ||
                str_contains($q, 'penyebab')
            )
        );
        if ($isReconciliationQuery) {
            $rek = $ctx['rekonsiliasi_kas'] ?? [];
            $saldoNom = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            $pemNom   = number_format($pembayaran['total_semua_pembayaran_nominal'] ?? 0, 0, ',', '.');
            $selisih  = (float)($kas['saldo_kas_saat_ini'] ?? 0) - (float)($pembayaran['total_semua_pembayaran_nominal'] ?? 0);
            $selisihNom = number_format(abs($selisih), 0, ',', '.');

            if (abs($selisih) < 0.01) {
                return "Saldo kas saat ini (Rp {$saldoNom}) sama dengan total pembayaran iuran anggota (Rp {$pemNom}) karena seluruh kas masuk berasal dari pembayaran iuran dan belum ada pengeluaran kas.";
            }

            // Jika ada selisih, periksa apakah penyebabnya terverifikasi dari data transaksi langsung
            $trxLangsung = $rek['rincian_transaksi_langsung'] ?? [];
            $totLangsung = (float)($rek['total_nominal_langsung'] ?? 0);

            if (!empty($trxLangsung) && abs($selisih - $totLangsung) < 0.01) {
                $rincianStr = implode(', ', $trxLangsung);
                return "Saldo kas saat ini sebesar Rp {$saldoNom}, sedangkan total pembayaran iuran anggota adalah Rp {$pemNom} (terdapat selisih Rp {$selisihNom}). Berdasarkan data transaksi aktual, selisih tersebut disebabkan oleh adanya " . count($trxLangsung) . " transaksi pemasukan kas langsung senilai Rp " . number_format($totLangsung, 0, ',', '.') . " ({$rincianStr}) yang dicatat langsung pada buku transaksi kas tanpa melalui modul pembayaran iuran anggota.";
            }

            // Jika ada pengeluaran yang mempengaruhi saldo
            $pengeluaranTotal = (float)($kas['total_pengeluaran_semua'] ?? 0);
            if ($pengeluaranTotal > 0) {
                $pengNom = number_format($pengeluaranTotal, 0, ',', '.');
                return "Saldo kas saat ini sebesar Rp {$saldoNom} berbeda dari total pembayaran iuran (Rp {$pemNom}) karena terdapat pengeluaran kas sebesar Rp {$pengNom} serta mutasi kas lain yang tercatat pada sistem.";
            }

            // Jika penyebab belum dapat dipastikan secara faktual dari database
            return "Saldo kas saat ini adalah Rp {$saldoNom} dan total pembayaran iuran anggota yang tercatat adalah Rp {$pemNom} (selisih Rp {$selisihNom}). Berdasarkan data sistem saat ini, belum cukup data transaksi untuk menyimpulkan secara pasti penyebab selisih tersebut.";
        }

        // 9. QUERY TOTAL PEMBAYARAN SELURUHNYA
        if ((str_contains($q, 'total pembayaran') || str_contains($q, 'seluruh pembayaran')) && !str_contains($q, 'tunggak')) {
            if (!isset($ctx['ringkasan_pembayaran_anggota'])) {
                return "Mohon maaf, data pembayaran kas belum tersedia dalam sistem kas.";
            }
            $totalSemuaNom = number_format($pembayaran['total_semua_pembayaran_nominal'] ?? 0, 0, ',', '.');
            $totalSemuaTrx = (int)($pembayaran['total_semua_pembayaran_transaksi'] ?? 0);
            return "Total seluruh pembayaran kas yang tercatat adalah sebesar Rp {$totalSemuaNom} dari {$totalSemuaTrx} transaksi pembayaran.";
        }

        // 10. QUERY SALDO KAS
        if (str_contains($q, 'saldo') || str_contains($q, 'sisa kas') || str_contains($q, 'total kas')) {
            if (!isset($ctx['ringkasan_kas'])) {
                return "Mohon maaf, data saldo kas belum tersedia dalam sistem kas.";
            }
            $saldo = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            $pemasukan = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            $pengeluaran = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
            return "Saldo kas saat ini sebesar Rp {$saldo}, dengan total pemasukan Rp {$pemasukan} dan total pengeluaran Rp {$pengeluaran}.";
        }

        // 11. QUERY PEMASUKAN & PEMBAYARAN MASUK
        if (str_contains($q, 'pemasukan') || str_contains($q, 'uang masuk') || str_contains($q, 'kas masuk') || (str_contains($q, 'pembayaran') && !str_contains($q, 'tunggak') && !str_contains($q, 'menunggak') && !str_contains($q, 'nunggak') && !str_contains($q, 'belum'))) {
            if (!isset($ctx['ringkasan_kas'])) {
                return "Mohon maaf, data pemasukan kas belum tersedia dalam sistem kas.";
            }
            $pemBulan = number_format($kas['pemasukan_bulan_ini'] ?? 0, 0, ',', '.');
            $pemTotal = number_format($kas['total_pemasukan_semua'] ?? 0, 0, ',', '.');
            return "Pemasukan kas {$periodeLaporan} sebesar Rp {$pemBulan}, dengan akumulasi keseluruhan Rp {$pemTotal}.";
        }

        // 12. QUERY PENGELUARAN
        $isExpenseQuery = (
            str_contains($q, 'pengeluaran') ||
            str_contains($q, 'uang keluar') ||
            (str_contains($q, 'biaya') && (str_contains($q, 'kas') || str_contains($q, 'operasional') || str_contains($q, 'total biaya'))) ||
            (str_contains($q, 'belanja') && str_contains($q, 'kas'))
        );
        if ($isExpenseQuery) {
            if (!isset($ctx['ringkasan_kas'])) {
                return "Mohon maaf, data pengeluaran kas belum tersedia dalam sistem kas.";
            }
            $pengBulan = number_format($kas['pengeluaran_bulan_ini'] ?? 0, 0, ',', '.');
            $pengTotal = number_format($kas['total_pengeluaran_semua'] ?? 0, 0, ',', '.');
            return "Pengeluaran kas {$periodeLaporan} sebesar Rp {$pengBulan}, dengan akumulasi keseluruhan Rp {$pengTotal}.";
        }

        // 13. QUERY AKUN PENGGUNA / USERS (hanya super_user yang diizinkan)
        $isUserQuery = (
            str_contains($q, 'jumlah user') || str_contains($q, 'jumlah pengguna') || str_contains($q, 'jumlah akun') ||
            str_contains($q, 'total user') || str_contains($q, 'total akun') || str_contains($q, 'berapa user') ||
            str_contains($q, 'berapa akun') || str_contains($q, 'berapa pengguna') ||
            str_contains($q, 'akun terdaftar') || str_contains($q, 'pengguna terdaftar') || str_contains($q, 'daftar pengguna') ||
            (str_contains($q, 'user') && (str_contains($q, 'aktif') || str_contains($q, 'berapa')) && !str_contains($q, 'hak akses') && !str_contains($q, 'kebijakan')) ||
            (str_contains($q, 'akun') && (str_contains($q, 'aktif') || str_contains($q, 'role') || str_contains($q, 'terdaftar')) && !str_contains($q, 'hak akses') && !str_contains($q, 'kebijakan'))
        );
        if ($isUserQuery) {
            if ($role === 'anggota') {
                return "Informasi akun pengguna sistem tidak dapat diakses oleh akun Anggota demi menjaga privasi dan keamanan data.";
            }
            // Bendahara tidak punya akses ke data akun pengguna
            if ($role === 'bendahara') {
                return "Informasi akun pengguna sistem hanya dapat diakses oleh Administrator (Super User). Sebagai Bendahara, Anda dapat mengakses data keuangan, transaksi, pembayaran, dan laporan keuangan.";
            }
            if (!isset($ctx['ringkasan_sistem']) || !is_array($ctx['ringkasan_sistem'])) {
                return "Mohon maaf, data akun pengguna belum tersedia dalam sistem kas.";
            }
            $totalUser = (int)($sistem['total_user'] ?? 0);
            $roleCounts = $sistem['rincian_role_user'] ?? [];
            $su = (int)($roleCounts['super_user'] ?? 0);
            $bd = (int)($roleCounts['bendahara'] ?? 0);
            $ag = (int)($roleCounts['anggota'] ?? 0);
            return "Terdapat {$totalUser} akun pengguna terdaftar aktif yang terdiri dari {$su} Super User, {$bd} Bendahara, dan {$ag} Anggota.";
        }

        // 14. QUERY LOG AKTIVITAS / AUDIT LOG (hanya super_user yang diizinkan)
        $isAuditQuery = (
            str_contains($q, 'audit') ||
            str_contains($q, 'log aktivitas') ||
            (str_contains($q, 'aktivitas') && (str_contains($q, 'terakhir') || str_contains($q, 'terbaru') || str_contains($q, 'log') || str_contains($q, 'tercatat')))
        );
        if ($isAuditQuery) {
            if ($role === 'anggota') {
                return "Log aktivitas sistem hanya dapat diakses oleh Administrator (Super User).";
            }
            // Bendahara tidak punya akses ke log audit sistem
            if ($role === 'bendahara') {
                return "Log aktivitas sistem hanya dapat diakses oleh Administrator (Super User).";
            }
            if (!isset($ctx['ringkasan_sistem']['total_audit_log'])) {
                return "Mohon maaf, data log aktivitas belum tersedia dalam sistem kas.";
            }
            $totLog = (int)($sistem['total_audit_log'] ?? 0);
            $lastAktivitas = $sistem['aktivitas_terakhir'] ?? null;
            $lastInfo = '';
            if (!empty($lastAktivitas)) {
                $lastAktivitasStr = $lastAktivitas['aktivitas'] ?? '';
                $lastUsername = $lastAktivitas['username'] ?? '';
                $lastWaktu = $lastAktivitas['waktu'] ?? '';
                if ($lastAktivitasStr && $lastUsername) {
                    $lastInfo = " Aktivitas terakhir: {$lastAktivitasStr} oleh {$lastUsername}" . ($lastWaktu ? " pada {$lastWaktu}" : '') . '.';
                }
            }
            return "Tercatat {$totLog} log aktivitas pada sistem.{$lastInfo}";
        }

        // 15. QUERY TRANSAKSI / RIWAYAT UMUM
        if (str_contains($q, 'transaksi') || str_contains($q, 'riwayat')) {
            if (!isset($ctx['ringkasan_transaksi'])) {
                return "Mohon maaf, data riwayat transaksi belum tersedia dalam sistem kas.";
            }
            $aktifCount = (int)($transaksi['total_transaksi_aktif'] ?? 0);
            return "Setiap transaksi kas dicatat resmi oleh Administrator dan Bendahara (tercatat {$aktifCount} transaksi aktif).";
        }

        // 16. KONDISI KAS / SURPLUS DEFISIT / ANALISIS
        if (str_contains($q, 'banding') || str_contains($q, 'perbandingan') || str_contains($q, 'kondisi') || str_contains($q, 'surplus') || str_contains($q, 'defisit') || str_contains($q, 'analisis') || str_contains($q, 'kesehatan')) {
            $surplus = $kas['surplus_defisit_bulan_ini'] ?? 0;
            $statusSurplus = $surplus >= 0 ? 'surplus' : 'defisit';
            $surplusStr = number_format(abs($surplus), 0, ',', '.');
            $saldoStr = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            return "Arus kas {$periodeLaporan} berstatus {$statusSurplus} sebesar Rp {$surplusStr}, dengan saldo kas saat ini Rp {$saldoStr}.";
        }

        // 17. RINGKASAN UMUM
        // PENTING: Jangan gunakan str_contains($q, 'kas') di sini — "Sistem Kas" dalam pertanyaan
        // anggota akan tertangkap di sini sebelum member count check.
        if (str_contains($q, 'ringkasan') || str_contains($q, 'laporan')) {
            $saldo   = number_format($kas['saldo_kas_saat_ini'] ?? 0, 0, ',', '.');
            $pem     = number_format($kas['pemasukan_bulan_ini'] ?? 0, 0, ',', '.');
            $peng    = number_format($kas['pengeluaran_bulan_ini'] ?? 0, 0, ',', '.');
            $tunggak = number_format($tunggakan['total_estimasi_tunggakan'] ?? 0, 0, ',', '.');
            return "Ringkasan kas {$periodeLaporan}: saldo Rp {$saldo}, pemasukan Rp {$pem}, pengeluaran Rp {$peng}, estimasi tunggakan Rp {$tunggak}.";
        }

        // ─── FALLBACK: DATA TIDAK TERSEDIA ────────────────────────────────────
        return "Mohon maaf, data tersebut belum tersedia dalam sistem kas.";
    }
}
