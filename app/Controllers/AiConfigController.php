<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Models\AiConfiguration;
use App\Models\AuditLog;
use App\Services\EncryptionService;
use App\Services\GeminiConfigService;

/**
 * AiConfigController
 * Handles Admin UI & API actions for Gemini API Key and Model configuration management.
 * Strictly restricted to Super User (role: 'super_user').
 */
class AiConfigController extends BaseController
{
    protected GeminiConfigService $configService;
    protected AiConfiguration $aiConfigModel;

    public function __construct()
    {
        $this->configService = new GeminiConfigService();
        $this->aiConfigModel = new AiConfiguration();
    }

    /**
     * Enforce strict Super User authorization.
     * Denies Bendahara, Anggota, and unauthenticated visitors at backend level.
     */
    protected function requireSuperUser(bool $isAjax = false): void
    {
        $user = session('user');

        if (empty($user) || empty($user['id'])) {
            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Sesi login telah berakhir. Silakan login kembali.']);
                exit;
            }
            redirect('/login');
            exit;
        }

        if (($user['role'] ?? '') !== 'super_user') {
            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Akses ditolak: Hanya Super User / Admin yang berhak mengelola konfigurasi AI.']);
                exit;
            }
            http_response_code(403);
            $this->render('errors/403', ['message' => 'Anda tidak memiliki hak akses ke halaman Konfigurasi AI.']);
            exit;
        }
    }

    /**
     * Render AI Configuration Page for Super User.
     */
    public function index(): void
    {
        $this->requireSuperUser(false);

        $dbConnected = true;
        $dbError = null;

        try {
            $globalActive = $this->aiConfigModel->getGlobalActive();

            if (!empty($globalActive) && !empty($globalActive['encrypted_api_key'])) {
                $decryptedKey = EncryptionService::decrypt($globalActive['encrypted_api_key']) ?? '';
                $activeConfig = [
                    'provider'      => $globalActive['provider'],
                    'model'         => $globalActive['active_model'],
                    'api_key'       => $decryptedKey,
                    'masked_key'    => EncryptionService::maskApiKey($decryptedKey),
                    'is_configured' => !empty($decryptedKey),
                    'status'        => !empty($decryptedKey) ? 'active' : 'decryption_error',
                    'source'        => 'database',
                    'updated_at'    => $globalActive['updated_at'],
                    'updated_by'    => $globalActive['updated_by_name'] ?? $globalActive['updated_by_username'] ?? 'Super User',
                    'config_id'     => (int)$globalActive['id']
                ];
            } else {
                $activeConfig = $this->configService->getActiveConfig();
            }

            $geminiLatest = $this->aiConfigModel->getLatestByProvider('gemini');
            $groqLatest   = $this->aiConfigModel->getLatestByProvider('groq');

            $providerDetails = [
                'gemini' => [
                    'has_saved'  => !empty($geminiLatest),
                    'masked_key' => !empty($geminiLatest['encrypted_api_key']) ? EncryptionService::maskApiKey(EncryptionService::decrypt($geminiLatest['encrypted_api_key'])) : 'Belum Dikonfigurasi',
                    'model'      => $geminiLatest['active_model'] ?? 'gemini-3.8-flash',
                    'is_active'  => !empty($geminiLatest['is_active'])
                ],
                'groq' => [
                    'has_saved'  => !empty($groqLatest),
                    'masked_key' => !empty($groqLatest['encrypted_api_key']) ? EncryptionService::maskApiKey(EncryptionService::decrypt($groqLatest['encrypted_api_key'])) : 'Belum Dikonfigurasi',
                    'model'      => $groqLatest['active_model'] ?? '',
                    'is_active'  => !empty($groqLatest['is_active'])
                ]
            ];

            $history = $this->aiConfigModel->getHistory(null, 10);
        } catch (\Throwable $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
            $activeConfig = [
                'provider'      => 'gemini',
                'model'         => 'Belum Diatur',
                'api_key'       => '',
                'masked_key'    => 'Belum Dikonfigurasi',
                'is_configured' => false,
                'status'        => 'unconfigured',
                'source'        => 'none',
                'updated_at'    => null,
                'updated_by'    => 'Sistem',
                'config_id'     => null,
            ];
            $providerDetails = [
                'gemini' => ['has_saved' => false, 'masked_key' => 'Belum Dikonfigurasi', 'model' => '', 'is_active' => false],
                'groq'   => ['has_saved' => false, 'masked_key' => 'Belum Dikonfigurasi', 'model' => '', 'is_active' => false],
            ];
            $history = [];
        }

        $fonnteToken = trim((string)env('FONNTE_API_TOKEN', ''));
        $fonnteTokenMasked = $this->maskFonnteToken($fonnteToken);

        $this->render('admin/ai/config', [
            'pageTitle'       => 'Konfigurasi AI Assistant — Super User',
            'activeConfig'    => $activeConfig,
            'providerDetails' => $providerDetails,
            'history'         => $history,
            'role'            => 'super_user',
            'dbConnected'     => $dbConnected,
            'dbError'         => $dbError,
            'fonnteTokenMasked' => $fonnteTokenMasked,
            'fonnteTokenConfigured' => !empty($fonnteToken),
        ]);
    }

    /**
     * AJAX: Fetch available models dynamically from selected AI Provider (Gemini or Groq).
     */
    public function loadModels(): void
    {
        $this->requireSuperUser(true);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Verify CSRF token
        $token = $input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.']);
            exit;
        }

        $provider = strtolower(trim((string)($input['provider'] ?? 'gemini')));
        $apiKey   = trim((string)($input['api_key'] ?? ''));

        // If api_key input is empty, fallback to stored key for this specific provider
        if (empty($apiKey)) {
            $saved = $this->aiConfigModel->getLatestByProvider($provider);
            if (!empty($saved['encrypted_api_key'])) {
                $apiKey = EncryptionService::decrypt($saved['encrypted_api_key']) ?? '';
            }
            if (empty($apiKey) && $provider === 'gemini') {
                $activeConfig = $this->configService->getActiveConfig();
                $apiKey = $activeConfig['api_key'] ?? '';
            }
        }

        if (empty($apiKey)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'API Key ' . ucfirst($provider) . ' belum dimasukkan atau belum dikonfigurasi di sistem.'
            ]);
            exit;
        }

        if ($provider === 'groq') {
            $groqService = new \App\Services\GroqConfigService();
            $result = $groqService->fetchAvailableModels($apiKey);
        } else {
            $result = $this->configService->fetchAvailableModels($apiKey);
        }

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'models'  => $result['models'],
                'count'   => $result['count']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $result['error']
            ]);
        }
        exit;
    }

    /**
     * AJAX: Test connection and generation capability for given provider, key, and model.
     */
    public function testConnection(): void
    {
        $this->requireSuperUser(true);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Verify CSRF token
        $token = $input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.']);
            exit;
        }

        $provider = strtolower(trim((string)($input['provider'] ?? 'gemini')));
        $apiKey   = trim((string)($input['api_key'] ?? ''));
        $model    = trim((string)($input['model'] ?? ''));

        // If api_key input is empty, fallback to stored key for this specific provider
        if (empty($apiKey)) {
            $saved = $this->aiConfigModel->getLatestByProvider($provider);
            if (!empty($saved['encrypted_api_key'])) {
                $apiKey = EncryptionService::decrypt($saved['encrypted_api_key']) ?? '';
            }
            if (empty($apiKey) && $provider === 'gemini') {
                $activeConfig = $this->configService->getActiveConfig();
                $apiKey = $activeConfig['api_key'] ?? '';
            }
        }

        if (empty($apiKey)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'API Key ' . ucfirst($provider) . ' belum dimasukkan atau belum dikonfigurasi.']);
            exit;
        }

        if (empty($model)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Silakan pilih model ' . ucfirst($provider) . ' yang akan diuji.']);
            exit;
        }

        if ($provider === 'groq') {
            $groqService = new \App\Services\GroqConfigService();
            $testResult = $groqService->testConfiguration($apiKey, $model);
        } else {
            $testResult = $this->configService->testConfiguration($apiKey, $model);
        }

        if ($testResult['success']) {
            echo json_encode([
                'success'         => true,
                'message'         => $testResult['message'],
                'model'           => $testResult['model'],
                'latency_ms'      => $testResult['latency_ms'],
                'sample_response' => $testResult['sample_response']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success'    => false,
                'error'      => $testResult['error'],
                'latency_ms' => $testResult['latency_ms'] ?? null
            ]);
        }
        exit;
    }

    /**
     * AJAX: Save Fonnte WhatsApp API token securely to .env.
     */
    public function saveFonnteToken(): void
    {
        $this->requireSuperUser(true);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $token = trim((string)($input['fonnte_token'] ?? ''));

        // Verify CSRF token
        $csrfToken = $input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.']);
            exit;
        }

        if ($token === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token API Fonnte tidak boleh kosong.']);
            exit;
        }

        if (strlen($token) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token API Fonnte tidak valid.']);
            exit;
        }

        try {
            $updated = $this->updateEnvVariable('FONNTE_API_TOKEN', $token);
            if (!$updated) {
                throw new \RuntimeException('Gagal menulis konfigurasi .env.');
            }

            // Reload environment variables for current request/runtime
            Env::load(base_path('.env'));

            $userId = (int)(session('user')['id'] ?? 0) ?: null;
            if (class_exists('App\\Models\\AuditLog')) {
                \App\Models\AuditLog::log($userId, 'Mengubah Token API Fonnte WhatsApp', [
                    'status' => 'updated',
                    'masked_token' => $this->maskFonnteToken($token)
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Token API Fonnte berhasil disimpan dengan aman.',
                'masked_token' => $this->maskFonnteToken($token),
                'configured' => true
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Gagal menyimpan token Fonnte: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Update or append one variable in .env without touching other variables.
     */
    protected function updateEnvVariable(string $key, string $value): bool
    {
        $path = base_path('.env');
        if (!is_file($path) || !is_writable($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        $found = false;
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                $lines[$index] = $key . '=' . $value;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $lines[] = $key . '=' . $value;
        }

        $content = implode("\n", $lines) . "\n";
        return file_put_contents($path, $content, LOCK_EX) !== false;
    }

    /**
     * Mask Fonnte token for safe browser display.
     */
    protected function maskFonnteToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return 'Belum Dikonfigurasi';
        }

        $length = strlen($token);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return substr($token, 0, 4) . str_repeat('•', $length - 8) . substr($token, -4);
    }

    /**
     * AJAX/Form: Validate, Encrypt, Save, and Atomically Activate new configuration.
     */
    public function saveConfig(): void
    {
        $this->requireSuperUser(true);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Verify CSRF token
        $token = $input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.']);
            exit;
        }

        $provider     = strtolower(trim((string)($input['provider'] ?? 'gemini')));
        $submittedKey = trim((string)($input['api_key'] ?? ''));
        $model        = trim((string)($input['model'] ?? ''));
        $cleanModel   = preg_replace('#^models/#', '', $model);

        // Determine effective API key (new or keep active/latest for this provider)
        $effectiveKey = $submittedKey;
        if (empty($effectiveKey)) {
            $saved = $this->aiConfigModel->getLatestByProvider($provider);
            if (!empty($saved['encrypted_api_key'])) {
                $effectiveKey = EncryptionService::decrypt($saved['encrypted_api_key']) ?? '';
            }
            if (empty($effectiveKey) && $provider === 'gemini') {
                $activeConfig = $this->configService->getActiveConfig();
                $effectiveKey = $activeConfig['api_key'] ?? '';
            }
        }

        if (empty($effectiveKey)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'API Key ' . ucfirst($provider) . ' wajib diisi sebelum menyimpan konfigurasi.']);
            exit;
        }

        if (empty($cleanModel)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Model ' . ucfirst($provider) . ' wajib dipilih.']);
            exit;
        }

        // =========================================================================
        // MANDATORY PRE-ACTIVATION VERIFICATION
        // Configuration MUST pass real testing before being activated.
        // If testing fails, the existing active configuration remains untouched.
        // =========================================================================
        if ($provider === 'groq') {
            $groqService = new \App\Services\GroqConfigService();
            $testResult = $groqService->testConfiguration($effectiveKey, $cleanModel);
        } else {
            $testResult = $this->configService->testConfiguration($effectiveKey, $cleanModel);
        }

        if (!$testResult['success']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Konfigurasi tidak dapat disimpan karena pengujian koneksi gagal: ' . $testResult['error']
            ]);
            exit;
        }

        // Get previous active info for audit log
        $currentGlobal = $this->aiConfigModel->getGlobalActive();
        $previousProvider = $currentGlobal['provider'] ?? 'gemini';
        $previousModel = $currentGlobal['active_model'] ?? 'Tidak diketahui';

        // Encrypt API Key before saving
        $encryptedKey = EncryptionService::encrypt($effectiveKey);
        $userId = (int)(session('user')['id'] ?? 0) ?: null;

        try {
            // Atomically activate new configuration in database (disables prior active across providers)
            $savedRecord = $this->aiConfigModel->activate($provider, $encryptedKey, $cleanModel, $userId);

            // Audit log without disclosing the API key
            AuditLog::log($userId, "Mengubah konfigurasi AI ({$provider})", [
                'provider'          => $provider,
                'previous_provider' => $previousProvider,
                'previous_model'    => $previousModel,
                'new_model'         => $cleanModel,
                'status'            => 'activated',
                'config_id'         => $savedRecord['id'] ?? null
            ]);

            // Notify running Node.js service if Gemini
            if ($provider === 'gemini') {
                $this->configService->notifyNodeService($effectiveKey, $cleanModel);
            }

            echo json_encode([
                'success'      => true,
                'message'      => "Konfigurasi " . ucfirst($provider) . " ({$cleanModel}) berhasil diuji, disimpan secara aman, dan diaktifkan.",
                'provider'     => $provider,
                'active_model' => $cleanModel,
                'masked_key'   => EncryptionService::maskApiKey($effectiveKey),
                'updated_at'   => date('Y-m-d H:i:s'),
                'updated_by'   => session('user')['nama'] ?? session('user')['username'] ?? 'Super User'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Gagal menyimpan konfigurasi ke database. Konfigurasi sebelumnya tetap aktif.'
            ]);
        }
        exit;
    }

    /**
     * AJAX/Form: Explicitly Deactivate/Reset AI Configuration in database.
     * Prevents stale credentials from running, clears Node.js runtime memory,
     * and ensures no ghost API Key resurrects from fallback.
     */
    public function resetConfig(): void
    {
        $this->requireSuperUser(true);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Verify CSRF token
        $token = $input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.']);
            exit;
        }

        $userId = (int)(session('user')['id'] ?? 0) ?: null;

        try {
            // Deactivate in database and clear Node.js in-memory credentials
            $this->configService->deactivateConfig($userId);

            // Audit log without disclosing sensitive details
            AuditLog::log($userId, 'Menonaktifkan konfigurasi AI Assistant', [
                'provider' => 'gemini',
                'action'   => 'deactivate',
                'status'   => 'deactivated'
            ]);

            echo json_encode([
                'success'      => true,
                'message'      => 'Konfigurasi AI berhasil dinonaktifkan. AI Assistant kini berstatus nonaktif (offline fallback aktif).',
                'active_model' => 'Belum Diatur',
                'masked_key'   => 'Belum Dikonfigurasi',
                'source'       => 'Database (Dinonaktifkan)',
                'updated_at'   => date('Y-m-d H:i:s'),
                'updated_by'   => session('user')['nama'] ?? session('user')['username'] ?? 'Super User'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Gagal menonaktifkan konfigurasi AI: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
