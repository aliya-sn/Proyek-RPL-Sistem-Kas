<?php

namespace App\Controllers;

use App\Services\FinancialDataAggregator;
use App\Services\AIService;
use App\Services\LoggerService;

/**
 * AiAssistantController
 * Handles AI Financial Assistant requests for both Super User and Anggota.
 * Enforces role authorization and returns JSON responses for AJAX chat interface.
 */
class AiAssistantController extends BaseController
{
    protected FinancialDataAggregator $aggregator;
    protected AIService $aiService;

    public function __construct()
    {
        $this->aggregator = new FinancialDataAggregator();
        $this->aiService  = new AIService();
    }

    /**
     * Get authenticated user identity securely from server session.
     * Strictly ignores any client-supplied identity attributes in request payload.
     */
    protected function getAuthenticatedUserContext(string $expectedRole): array
    {
        $sessionUser = session('user') ?? [];
        $userId   = (int)($sessionUser['id'] ?? 0);
        $username = (string)($sessionUser['username'] ?? '');

        $context = [
            'id'              => $userId,
            'username'        => $username,
            'role'            => $expectedRole,
            'nama'            => $sessionUser['nama'] ?? null,
            'nomor_identitas' => $sessionUser['nomor_identitas'] ?? null,
            'kelas'           => $sessionUser['kelas'] ?? null,
            'status'          => $sessionUser['status'] ?? 'active',
        ];

        // If nama is not populated in session, try resolving from anggota profile if linked
        if (empty($context['nama']) && $userId > 0) {
            try {
                $anggotaModel = new \App\Models\Anggota();
                $anggota = $anggotaModel->findByUserId($userId);
                if ($anggota) {
                    $context['nama'] = $anggota['nama'] ?? null;
                    if (empty($context['nomor_identitas'])) {
                        $context['nomor_identitas'] = $anggota['nomor_identitas'] ?? null;
                    }
                    if (empty($context['kelas'])) {
                        $context['kelas'] = $anggota['kelas'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                // Non-blocking fallback
            }
        }

        return $context;
    }

    /**
     * Render AI Assistant interface for Super User.
     */
    public function index(): void
    {
        $this->render('admin/ai/index', [
            'pageTitle' => 'AI Financial Assistant — Super User',
            'role'      => 'super_user'
        ]);
    }

    /**
     * Handle AJAX question submission from Super User.
     */
    public function ask(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $question = trim($input['question'] ?? '');

        if (empty($question)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Pertanyaan tidak boleh kosong.']);
            exit;
        }

        $role = 'super_user';
        $userContext = $this->getAuthenticatedUserContext($role);
        $userId = $userContext['id'] ?: null;

        try {
            // Aggregates safe read-only data context for Super User
            $context = $this->aggregator->getAggregatedContext($role, null, $question);
            $context['pengguna_login'] = $userContext;

            // Calls AI Service with secure identity context
            $result = $this->aiService->analyzeQuery($question, $context, $role, $userContext);

            // Log activity without secrets
            LoggerService::log($role, $userId, $question, 'success', $result['provider']);

            echo json_encode([
                'success'   => true,
                'question'  => htmlspecialchars($question, ENT_QUOTES, 'UTF-8'),
                'answer'    => $result['answer'],
                'provider'  => $result['provider'],
                'timestamp' => date('H:i')
            ]);
        } catch (\Throwable $e) {
            LoggerService::log($role, $userId, $question, 'error: ' . $e->getMessage(), 'unknown');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memproses analisis AI. Silakan coba lagi.'
            ]);
        }
        exit;
    }

    /**
     * Render AI Assistant interface for Anggota.
     */
    public function anggotaIndex(): void
    {
        $this->render('anggota/ai/index', [
            'pageTitle' => 'Asisten Kas AI — Anggota',
            'role'      => 'anggota'
        ]);
    }

    /**
     * Handle AJAX question submission from Anggota.
     */
    public function anggotaAsk(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $question = trim($input['question'] ?? '');

        if (empty($question)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Pertanyaan tidak boleh kosong.']);
            exit;
        }

        $role        = 'anggota';
        $userContext = $this->getAuthenticatedUserContext($role);
        $userId      = $userContext['id'] ?: null;
        $anggotaId   = session('user')['anggota_id'] ?? null;

        try {
            // Aggregates safe read-only data context ISOLATED to this Anggota
            $context = $this->aggregator->getAggregatedContext($role, $anggotaId, $question);
            $context['pengguna_login'] = $userContext;

            // Calls AI Service with secure identity context
            $result = $this->aiService->analyzeQuery($question, $context, $role, $userContext);

            // Log activity without secrets
            LoggerService::log($role, $userId, $question, 'success', $result['provider']);

            echo json_encode([
                'success'   => true,
                'question'  => htmlspecialchars($question, ENT_QUOTES, 'UTF-8'),
                'answer'    => $result['answer'],
                'provider'  => $result['provider'],
                'timestamp' => date('H:i')
            ]);
        } catch (\Throwable $e) {
            LoggerService::log($role, $userId, $question, 'error: ' . $e->getMessage(), 'unknown');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memproses analisis AI. Silakan coba lagi.'
            ]);
        }
        exit;
    }

    /**
     * Render AI Assistant interface for Bendahara.
     * Uses the same admin AI view since Bendahara has financial access.
     */
    public function bendaharaIndex(): void
    {
        $this->render('admin/ai/index', [
            'pageTitle' => 'AI Financial Assistant — Bendahara',
            'role'      => 'bendahara'
        ]);
    }

    /**
     * Handle AJAX question submission from Bendahara.
     * Bendahara gets full financial data context (same as super_user)
     * but role is identified as 'bendahara' for AI boundary awareness.
     */
    public function bendaharaAsk(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $question = trim($input['question'] ?? '');

        if (empty($question)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Pertanyaan tidak boleh kosong.']);
            exit;
        }

        $role        = 'bendahara';
        $userContext = $this->getAuthenticatedUserContext($role);
        $userId      = $userContext['id'] ?: null;

        try {
            // Aggregates safe read-only financial data context for Bendahara
            $context = $this->aggregator->getAggregatedContext($role, null, $question);
            $context['pengguna_login'] = $userContext;

            // Calls AI Service with secure identity context
            $result = $this->aiService->analyzeQuery($question, $context, $role, $userContext);

            // Log activity without secrets
            LoggerService::log($role, $userId, $question, 'success', $result['provider']);

            echo json_encode([
                'success'   => true,
                'question'  => htmlspecialchars($question, ENT_QUOTES, 'UTF-8'),
                'answer'    => $result['answer'],
                'provider'  => $result['provider'],
                'timestamp' => date('H:i')
            ]);
        } catch (\Throwable $e) {
            LoggerService::log($role, $userId, $question, 'error: ' . $e->getMessage(), 'unknown');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memproses analisis AI. Silakan coba lagi.'
            ]);
        }
        exit;
    }

    /**
     * Universal Floating Chat Widget endpoint for all authenticated roles.
     * Enforces strict server session authentication & role data isolation.
     */
    public function chat(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        // Verify authenticated session
        $sessionUser = session('user');
        if (empty($sessionUser) || empty($sessionUser['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sesi login tidak valid atau telah berakhir.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $question = trim($input['question'] ?? '');

        if (empty($question)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Pertanyaan tidak boleh kosong.']);
            exit;
        }

        // Determine actual verified role from server session
        $role = $sessionUser['role'] ?? 'anggota';
        $userContext = $this->getAuthenticatedUserContext($role);
        $userId = $userContext['id'] ?: null;
        $anggotaId = ($role === 'anggota') ? ($sessionUser['anggota_id'] ?? null) : null;

        try {
            // Aggregate safe read-only data context matching user's role
            $context = $this->aggregator->getAggregatedContext($role, $anggotaId, $question);
            $context['pengguna_login'] = $userContext;

            // Analyze via AIService (delegates to Node.js backend if available)
            $result = $this->aiService->analyzeQuery($question, $context, $role, $userContext);

            // Log activity
            LoggerService::log($role, $userId, $question, 'success', $result['provider']);

            echo json_encode([
                'success'   => true,
                'question'  => htmlspecialchars($question, ENT_QUOTES, 'UTF-8'),
                'answer'    => $result['answer'],
                'provider'  => $result['provider'],
                'timestamp' => date('H:i')
            ]);
        } catch (\Throwable $e) {
            LoggerService::log($role, $userId, $question, 'error: ' . $e->getMessage(), 'unknown');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memproses analisis AI. Silakan coba lagi.'
            ]);
        }
        exit;
    }
}
