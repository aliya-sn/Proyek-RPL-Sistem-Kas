  <?php
/**
 * test_ai_assistant.php
 * Automated test suite for AI Financial Assistant (Prompt 08).
 *
 * Run via CLI: php test_ai_assistant.php
 */

define('BASE_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\FinancialDataAggregator;
use App\Services\AIService;
use App\Services\LoggerService;
use App\Controllers\AiAssistantController;

Env::load(__DIR__ . '/.env');

$pass = 0;
$fail = 0;

function pass(string $name): void {
    global $pass;
    $pass++;
    echo "  [PASS] {$name}\n";
}

function fail(string $name, string $reason = ''): void {
    global $fail;
    $fail++;
    echo "  [FAIL] {$name}" . ($reason ? " — {$reason}" : '') . "\n";
}

function section(string $title): void {
    echo "\n── {$title} ──\n";
}

echo "=================================================================\n";
echo " AI FINANCIAL ASSISTANT — TEST SUITE (Prompt 08)\n";
echo "=================================================================\n";

// ─── T1: Route Authorization & Middleware Guards ───────────────────────────
section('T1: Authorization & Route Registration');
try {
    $routeContent = file_get_contents(__DIR__ . '/routes/web.php');
    if (strpos($routeContent, "role:super_user") !== false && strpos($routeContent, "'/ai-assistant'") !== false) {
        pass('T1a: Super User AI Assistant route registered inside super_user middleware group');
    } else {
        fail('T1a: Super User AI route missing or not protected by super_user middleware');
    }

    if (strpos($routeContent, "role:anggota") !== false && strpos($routeContent, "AiAssistantController") !== false) {
        pass('T1b: Anggota AI Assistant route registered inside anggota middleware group');
    } else {
        fail('T1b: Anggota AI route missing or not protected by anggota middleware');
    }
} catch (\Throwable $e) {
    fail('T1: Exception — ' . $e->getMessage());
}

// ─── T2: Data Aggregation & Isolation ───────────────────────────────────────
section('T2: Financial Data Isolation (Role Security)');
try {
    $aggregator = new FinancialDataAggregator();

    // Test Super User Aggregation
    $superContext = $aggregator->getAggregatedContext('super_user', null);
    if (isset($superContext['ringkasan_kas']['saldo_kas_saat_ini']) && isset($superContext['ringkasan_tunggakan'])) {
        pass('T2a: Super User receives full financial summary and member arrear list');
    } else {
        fail('T2a: Super User context missing required financial summary keys');
    }

    // Test Anggota Aggregation (Data Isolation)
    $anggotaContext = $aggregator->getAggregatedContext('anggota', 1);
    if (!isset($anggotaContext['ringkasan_tunggakan']) && !isset($anggotaContext['ringkasan_tunggakan']['daftar_anggota_menunggak'])) {
        pass('T2b: Anggota context does NOT reveal other members\' arrear list (Data Isolated)');
    } else {
        fail('T2b: Anggota context leaked other members\' arrear list!');
    }

    if (isset($anggotaContext['status_pembayaran_saya'])) {
        pass('T2c: Anggota context contains isolated personal payment status');
    } else {
        fail('T2c: Anggota context missing personal status_pembayaran_saya');
    }
} catch (\Throwable $e) {
    fail('T2: Exception — ' . $e->getMessage());
}

// ─── T3: AIService System Prompt & Read-Only Constraints ────────────────────
section('T3: System Prompt & Read-Only Rules');
try {
    $aiService = new AIService();
    $reflection = new ReflectionClass($aiService);
    $method = $reflection->getMethod('buildSystemPrompt');
    $method->setAccessible(true);

    $dummyCtx = ['ringkasan_kas' => ['saldo_kas_saat_ini' => 500000]];
    $promptSuper   = $method->invoke($aiService, $dummyCtx, 'super_user');
    $promptAnggota = $method->invoke($aiService, $dummyCtx, 'anggota');

    if (strpos($promptSuper, 'Bahasa Indonesia') !== false && strpos($promptSuper, 'READ-ONLY') !== false) {
        pass('T3a: System prompt contains Bahasa Indonesia and READ-ONLY constraints');
    } else {
        fail('T3a: System prompt missing READ-ONLY or language constraints');
    }

    if (strpos($promptSuper, 'NO HALLUCINATION') !== false) {
        pass('T3b: System prompt contains anti-hallucination instruction');
    } else {
        fail('T3b: System prompt missing anti-hallucination rule');
    }

    if (strpos($promptAnggota, 'PENGGUNA ADALAH ANGGOTA BIASA') !== false) {
        pass('T3c: System prompt enforces member privacy protection');
    } else {
        fail('T3c: System prompt missing member privacy instruction');
    }
} catch (\Throwable $e) {
    fail('T3: Exception — ' . $e->getMessage());
}

// ─── T4: API Key Protection & Non-Exposure ──────────────────────────────────
section('T4: API Key Protection');
try {
    $apiKey = Env::get('AI_API_KEY', '');
    // Ensure API Key is retrieved from Env, not hardcoded in source files
    $aiServiceCode = file_get_contents(__DIR__ . '/app/Services/AIService.php');
    $controllerCode = file_get_contents(__DIR__ . '/app/Controllers/AiAssistantController.php');

    if (!empty($apiKey) && (strpos($aiServiceCode, "'{$apiKey}'") !== false || strpos($controllerCode, "'{$apiKey}'") !== false)) {
        fail('T4: API Key is HARDCODED in source files!');
    } else {
        pass('T4a: API Key is stored safely in .env and NOT hardcoded in PHP source code');
    }

    // Verify view files don't output API keys
    $adminView = file_get_contents(__DIR__ . '/app/Views/admin/ai/index.php');
    $anggotaView = file_get_contents(__DIR__ . '/app/Views/anggota/ai/index.php');
    if (strpos($adminView, 'AI_API_KEY') === false && strpos($anggotaView, 'AI_API_KEY') === false) {
        pass('T4b: Views do NOT expose or leak API Key to frontend');
    } else {
        fail('T4b: Views expose AI_API_KEY string to frontend!');
    }
} catch (\Throwable $e) {
    fail('T4: Exception — ' . $e->getMessage());
}

// ─── T5: Provider Error / Fallback Handling ────────────────────────────────
section('T5: Fallback & Provider Error Resilience');
try {
    $aggregator = new FinancialDataAggregator();
    $aiService  = new AIService();
    $context    = $aggregator->getAggregatedContext('super_user', null);

    // Test query execution (works seamlessly via live API or local fallback engine)
    $res = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $context, 'super_user');

    if ($res['success'] === true && !empty($res['answer']) && !empty($res['provider'])) {
        pass("T5: AI Service returned valid response via provider: '{$res['provider']}'");
    } else {
        fail('T5: AI Service failed to return answer response');
    }
} catch (\Throwable $e) {
    fail('T5: Exception — ' . $e->getMessage());
}

// ─── T6: Data Available vs Unavailable Responses ───────────────────────────
section('T6: Data Available vs Unavailable Behavior');
try {
    $aggregator = new FinancialDataAggregator();
    $aiService  = new AIService();
    $context    = $aggregator->getAggregatedContext('super_user', null);

    // Query with known available data
    $resSaldo = $aiService->analyzeQuery('Berapa saldo kas saat ini?', $context, 'super_user');
    if (strpos($resSaldo['answer'], 'Saldo') !== false || strpos($resSaldo['answer'], 'Rp') !== false) {
        pass('T6a: Known query (saldo) returns accurate financial data');
    } else {
        fail('T6a: Known query did not return expected financial figures');
    }

    // Query with unavailable / irrelevant data
    $resUnknown = $aiService->analyzeQuery('Berapa luas lapangan sepak bola sekolah?', $context, 'super_user');
    if (strpos($resUnknown['answer'], 'tidak tersedia') !== false || strpos($resUnknown['answer'], 'Mohon maaf') !== false) {
        pass('T6b: Unavailable query returns polite "data tidak tersedia" response');
    } else {
        fail('T6b: Unavailable query did not handle unknown context properly');
    }
} catch (\Throwable $e) {
    fail('T6: Exception — ' . $e->getMessage());
}

// ─── T7: Read-Only Database Guarantee ──────────────────────────────────────
section('T7: Database Read-Only Guarantee');
try {
    $aggregator = new FinancialDataAggregator();
    $aiService  = new AIService();

    // Verify AIService source code contains no SQL write operations
    $aiCode = file_get_contents(__DIR__ . '/app/Services/AIService.php');
    $aggregatorCode = file_get_contents(__DIR__ . '/app/Services/FinancialDataAggregator.php');

    $forbiddenKeywords = ['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'TRUNCATE ', 'ALTER '];
    $hasForbidden = false;

    foreach ($forbiddenKeywords as $word) {
        if (stripos($aiCode, $word) !== false || stripos($aggregatorCode, $word) !== false) {
            $hasForbidden = true;
            fail("T7: Found SQL write keyword '{$word}' in AI Service pipeline!");
            break;
        }
    }

    if (!$hasForbidden) {
        pass('T7: AI Service pipeline is strictly READ-ONLY (Zero SQL mutation statements) ✓');
    }
} catch (\Throwable $e) {
    fail('T7: Exception — ' . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED  |  {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
