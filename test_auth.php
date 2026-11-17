<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Services\AuthService;
use App\Middleware\RoleMiddleware;
use App\Core\Request;
use App\Models\User;

Env::load(__DIR__ . '/.env');

Session::start();

echo "Running Auth & Role Tests...\n";
echo "----------------------------------------\n";

$auth = new AuthService();
$userModel = new User();
$testResults = [];

function assertTest($name, $condition) {
    global $testResults;
    if ($condition) {
        $testResults[] = "[PASS] $name";
    } else {
        $testResults[] = "[FAIL] $name";
    }
}


// Test 1: Invalid password
$result = $auth->attempt('admin', 'wrongpassword');
assertTest("Invalid Password Login", $result['success'] === false && strpos($result['message'], 'salah') !== false);

// Test 2: Inactive user
$userModel->update(1, ['status' => 'inactive']); // Temporarily deactivate admin
$result = $auth->attempt('admin', 'admin123');
assertTest("Inactive User Login", $result['success'] === false && strpos($result['message'], 'dinonaktifkan') !== false);
$userModel->update(1, ['status' => 'active']); // Reactivate

// Test 3: Valid Super User login
$result = $auth->attempt('admin', 'admin123');
assertTest("Super User Login", $result['success'] === true && Session::get('user')['role'] === 'super_user');

// Test 4: Protected route access (Super User accessing /admin/*)
$middleware = new RoleMiddleware();
$request = new Request(); // Mock request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/dashboard';
// Note: RoleMiddleware redirects and calls exit if forbidden, but if it passes, it just returns void.
// We can test this by checking if it throws or redirects (which we can catch via output buffering or headers).
// For simplicity, we just check if it passes without redirecting.
ob_start();
$middleware->handle($request, 'super_user');
$output = ob_get_clean();
$headers = headers_list();
$hasRedirect = false;
foreach ($headers as $h) {
    if (strpos($h, 'Location:') !== false) $hasRedirect = true;
}
assertTest("Super User Accessing Admin Route", !$hasRedirect);

// Test 5: Logout
$auth->logout();
assertTest("Logout functionality", !Session::has('user'));

// Test 6: Valid Anggota login
$result = $auth->attempt('ahmad.rizky', 'anggota123');
assertTest("Anggota Login", $result['success'] === true && Session::get('user')['role'] === 'anggota');

// Test 7: Member accessing Super User route (Should redirect to /anggota/dashboard)
// Reset headers for test
header_remove('Location');
ob_start();
$middleware->handle($request, 'super_user'); // Only super_user allowed
$output = ob_get_clean();
// In CLI, header() might not work as expected, but we can check session flash error
assertTest("Member Accessing Super User Route", Session::getFlash('error') === 'Anda tidak memiliki hak akses ke halaman tersebut.');

echo implode("\n", $testResults) . "\n";
echo "----------------------------------------\n";
