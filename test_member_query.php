<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Model.php';
require_once __DIR__ . '/app/Models/LaporanKeuangan.php';
require_once __DIR__ . '/app/Models/TransaksiKas.php';
require_once __DIR__ . '/app/Models/PembayaranKas.php';
require_once __DIR__ . '/app/Models/Anggota.php';
require_once __DIR__ . '/app/Services/FinancialDataAggregator.php';
require_once __DIR__ . '/app/Services/AIService.php';

use App\Services\FinancialDataAggregator;
use App\Services\AIService;

// Override dotenv loading for test
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
        $_ENV[trim($name)] = trim($value);
    }
}

// Force to use Local Analytical Engine by providing an empty API Key
putenv('AI_API_KEY=');
$_ENV['AI_API_KEY'] = '';

$aggregator = new FinancialDataAggregator();
$aiService = new AIService();

// T1: Super User
$contextAdmin = $aggregator->getAggregatedContext('super_user');
echo "=== TEST SUPER USER ===\n";
$q1 = $aiService->analyzeQuery('Ada berapa anggota?', $contextAdmin, 'super_user');
echo "Q: Ada berapa anggota?\nA: " . $q1['answer'] . "\n\n";

$q2 = $aiService->analyzeQuery('Berapa anggota aktif?', $contextAdmin, 'super_user');
echo "Q: Berapa anggota aktif?\nA: " . $q2['answer'] . "\n\n";

$q3 = $aiService->analyzeQuery('Siapa saja anggota?', $contextAdmin, 'super_user');
echo "Q: Siapa saja anggota?\nA: " . $q3['answer'] . "\n\n";

// T2: Anggota
$contextAnggota = $aggregator->getAggregatedContext('anggota', 1);
echo "=== TEST ANGGOTA ===\n";
$q4 = $aiService->analyzeQuery('Ada berapa anggota?', $contextAnggota, 'anggota');
echo "Q: Ada berapa anggota?\nA: " . $q4['answer'] . "\n\n";
