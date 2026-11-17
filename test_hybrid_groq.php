<?php
/**
 * test_hybrid_groq.php
 * Tests hybrid routing and Groq response validation.
 */
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\AIService;

Env::load(__DIR__ . '/.env');

$pass = 0;
$fail = 0;

function pass(string $name): void { global $pass; $pass++; echo "  [PASS] {$name}\n"; }
function fail(string $name, string $reason = ''): void { global $fail; $fail++; echo "  [FAIL] {$name}" . ($reason ? " — {$reason}" : '') . "\n"; }

echo "=================================================================\n";
echo " HYBRID GROQ — TEST SUITE\n";
echo "=================================================================\n";

$ai = new AIService();
$ref = new ReflectionClass($ai);

$isStructured = $ref->getMethod('isStructuredDataQuery');
$isStructured->setAccessible(true);
$lowQuality = $ref->getMethod('isLowQualityGroqResponse');
$lowQuality->setAccessible(true);

$structuredQueries = [
    'Berapa nominal tunggakan Diva Nayla dan Siti Nurhaliza pada November 2026?',
    'Pada bulan November, Ahmad Rizky melakukan berapa pembayaran?',
    'Total pembayaran kas pada bulan November 2026 berapa?',
    'Berapa jumlah anggota yang terdaftar di Sistem Kas saat ini?',
];

foreach ($structuredQueries as $q) {
    if ($isStructured->invoke($ai, $q)) {
        pass("T-structured: {$q}");
    } else {
        fail("T-structured: {$q}");
    }
}

$badResponses = [
    '',
    "Diva Nayla\nDiva Nayla\nDiva Nayla\nDiva Nayla",
    'x',
];
foreach ($badResponses as $resp) {
    if ($lowQuality->invoke($ai, $resp, 'Berapa tunggakan?')) {
        pass('T-lowquality: rejected bad Groq response');
    } else {
        fail('T-lowquality: bad Groq response accepted', $resp);
    }
}

$goodResponse = 'Nominal tunggakan Diva Nayla adalah Rp 80.000 dan Siti Nurhaliza adalah Rp 100.000.';
if (!$lowQuality->invoke($ai, $goodResponse, 'Berapa tunggakan?')) {
    pass('T-lowquality: good Groq response accepted');
} else {
    fail('T-lowquality: good Groq response rejected');
}

echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED | {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
?>