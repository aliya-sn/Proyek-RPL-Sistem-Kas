<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Models\Anggota;
use App\Models\User;

Env::load(__DIR__ . '/.env');

echo "Running Anggota Management Tests...\n";
echo "----------------------------------------\n";

$testResults = [];
$anggotaModel = new Anggota();
$userModel = new User();

function assertTest($name, $condition) {
    global $testResults;
    if ($condition) {
        $testResults[] = "[PASS] $name";
    } else {
        $testResults[] = "[FAIL] $name";
    }
}

try {
    // Test 1: isNomorIdentitasExists (Check existing from Seeder)
    // Ahmad Rizky NIS: 20261001
    $exists = $anggotaModel->isNomorIdentitasExists('20261001');
    assertTest("Duplicate Check - Exists", $exists === true);

    // Test 2: isNomorIdentitasExists (Not exists)
    $notExists = $anggotaModel->isNomorIdentitasExists('99999999');
    assertTest("Duplicate Check - Not Exists", $notExists === false);

    // Test 3: isNomorIdentitasExists with Exclude ID
    $excludeExists = $anggotaModel->isNomorIdentitasExists('20261001', 1); // Exclude ID 1 (Ahmad Rizky)
    assertTest("Duplicate Check with Exclude ID", $excludeExists === false);

    // Test 4: Pagination & Search logic
    $filters = ['search' => 'Ahmad'];
    $data = $anggotaModel->getPaginated($filters, 1, 10);
    $total = $anggotaModel->countFiltered($filters);
    assertTest("Search & Pagination Fetch", is_array($data) && count($data) >= 1 && $total >= 1);

    // Test 5: Filter logic
    $filters = ['kelas' => 'XII RPL 1'];
    $data = $anggotaModel->getPaginated($filters, 1, 10);
    assertTest("Filter Fetch", is_array($data) && count($data) >= 1);

    // Test 6: Username Generation Check
    $usernameExists = $userModel->usernameExists('ahmad.rizky');
    assertTest("Username Exists Check", $usernameExists === true);

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    $testResults[] = "[FAIL] Exception encountered during Anggota tests.";
}

echo implode("\n", $testResults) . "\n";
echo "----------------------------------------\n";
