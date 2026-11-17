<?php
/**
 * test_groq_prompt_length.php
 * Tests compact prompt generation for Groq (short and long prompts).
 */
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\FinancialDataAggregator;
use App\Services\AIService;

Env::load(__DIR__ . '/.env');

$pass = 0;
$fail = 0;

function pass(string $name): void { global $pass; $pass++; echo "  [PASS] {$name}\n"; }
function fail(string $name, string $reason = ''): void { global $fail; $fail++; echo "  [FAIL] {$name}" . ($reason ? " — {$reason}" : '') . "\n"; }

echo "=================================================================\n";
echo " GROQ PROMPT LENGTH — TEST SUITE\n";
echo "=================================================================\n";

$agg = new FinancialDataAggregator();
$ai = new AIService();
$ref = new ReflectionClass($ai);
$compactMethod = $ref->getMethod('compactContextForGroq');
$compactMethod->setAccessible(true);
$promptMethod = $ref->getMethod('buildSystemPrompt');
$promptMethod->setAccessible(true);

$shortQuery = 'Berapa jumlah anggota?';
$longQuery = 'Uang kas yang keluar Rp500.000 digunakan untuk apa? Jelaskan rincian, kategori, pencatat, dan tanggal transaksi.';

foreach ([$shortQuery, $longQuery] as $query) {
    $ctx = $agg->getAggregatedContext('super_user', null, $query);
    $compact = $compactMethod->invoke($ai, $ctx, $query);
    $compactPrompt = $promptMethod->invoke($ai, $compact, 'super_user', ['username' => 'admin', 'role' => 'super_user'], true);
    $fullPrompt = $promptMethod->invoke($ai, $ctx, 'super_user', ['username' => 'admin', 'role' => 'super_user'], false);

    $label = strlen($query) < 40 ? 'short' : 'long';
    if (strlen($compactPrompt) < strlen($fullPrompt)) {
        pass("T-{$label}: compact prompt is shorter than full prompt");
    } else {
        fail("T-{$label}: compact prompt is not shorter", "compact=" . strlen($compactPrompt) . ", full=" . strlen($fullPrompt));
    }

    if (strlen($compactPrompt) > 0 && strlen($compactPrompt) < 12000) {
        pass("T-{$label}: compact prompt stays within safe size");
    } else {
        fail("T-{$label}: compact prompt size unsafe", (string)strlen($compactPrompt));
    }

    if (str_contains($compactPrompt, 'KONTEKS DATA DATABASE')) {
        pass("T-{$label}: compact prompt retains database context marker");
    } else {
        fail("T-{$label}: compact prompt missing context marker");
    }
}

// Relevant key preservation for member count query
$ctxShort = $agg->getAggregatedContext('super_user', null, $shortQuery);
$compactShort = $compactMethod->invoke($ai, $ctxShort, $shortQuery);
if (isset($compactShort['informasi_anggota']['total_anggota'])) {
    pass('T-short-key: member count context is preserved');
} else {
    fail('T-short-key: member count context missing');
}

// Relevant key preservation for expense query
$ctxLong = $agg->getAggregatedContext('super_user', null, $longQuery);
$compactLong = $compactMethod->invoke($ai, $ctxLong, $longQuery);
if (isset($compactLong['pengeluaran_terbesar']) || isset($compactLong['daftar_transaksi_pengeluaran'])) {
    pass('T-long-key: expense context is preserved');
} else {
    fail('T-long-key: expense context missing');
}

echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED | {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
?>