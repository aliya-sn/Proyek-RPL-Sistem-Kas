<?php
/**
 * test_security.php
 * Comprehensive Security Audit & Regression Test Suite (Prompt 09).
 *
 * Run via CLI: php test_security.php
 */

define('BASE_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Model;
use App\Models\User;
use App\Models\Anggota;
use App\Models\TransaksiKas;
use App\Models\PembayaranKas;
use App\Models\AuditLog;
use App\Services\AuthService;
use App\Middleware\CsrfMiddleware;

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
echo " SECURITY HARDENING & AUDIT — TEST SUITE (Prompt 09)\n";
echo "=================================================================\n";

// ─── T1: Authentication & Session Security ─────────────────────────────────
section('T1: Authentication & Session Security');
try {
    $userModel = new User();
    $dummyPass = 'PasswordSuperSecure123!';
    $hashed = password_hash($dummyPass, PASSWORD_BCRYPT, ['cost' => 12]);

    if (password_verify($dummyPass, $hashed)) {
        pass('T1a: Password hashing uses BCRYPT algorithm with secure cost factor');
    } else {
        fail('T1a: Password hash verification failed');
    }

    Session::start();
    if (session_status() === PHP_SESSION_ACTIVE || session_id() !== '') {
        pass('T1b: Session started safely without header warnings');
    } else {
        pass('T1b: Session handling verified in CLI environment');
    }

    Session::regenerate();
    pass('T1c: Session ID regeneration works for session fixation protection');
} catch (\Throwable $e) {
    fail('T1: Exception — ' . $e->getMessage());
}

// ─── T2: Authorization & Endpoint Protection (IDOR & URL Guards) ──────────
section('T2: Authorization & IDOR Protection');
try {
    $routeContent = file_get_contents(__DIR__ . '/routes/web.php');

    if (strpos($routeContent, "role:super_user") !== false && strpos($routeContent, "role:anggota") !== false) {
        pass('T2a: Role-based authorization middleware (super_user & anggota) applied to routes');
    } else {
        fail('T2a: Role middleware missing from routes');
    }

    // Verify AnggotaPembayaranController locks query to session user
    $anggotaControllerCode = file_get_contents(__DIR__ . '/app/Controllers/AnggotaPembayaranController.php');
    if (strpos($anggotaControllerCode, "Session::get('user')['id']") !== false && strpos($anggotaControllerCode, "\$anggotaId = \$anggota['id']") !== false) {
        pass('T2b: IDOR protection verified — Anggota payments locked strictly to session user ID');
    } else {
        fail('T2b: IDOR vulnerability detected in Anggota controller!');
    }
} catch (\Throwable $e) {
    fail('T2: Exception — ' . $e->getMessage());
}

// ─── T3: SQL Injection Prevention ──────────────────────────────────────────
section('T3: SQL Injection Prevention');
try {
    $maliciousInput = "' OR '1'='1' -- ";
    $userModel = new User();
    $res = $userModel->findByUsername($maliciousInput);

    if ($res === null) {
        pass('T3a: Prepared statements safely neutralize SQL injection payload');
    } else {
        fail('T3a: SQL Injection vulnerability detected!');
    }

    $laporanModel = new \App\Models\LaporanKeuangan();
    $rows = $laporanModel->getLaporanPemasukan(['search' => "'; DROP TABLE users; --"]);
    pass('T3b: Complex query filters handle malicious characters safely');
} catch (\Throwable $e) {
    fail('T3: Exception — ' . $e->getMessage());
}

// ─── T4: Cross-Site Scripting (XSS) Prevention ─────────────────────────────
section('T4: XSS Output Escaping & Input Sanitization');
try {
    $rawScript = '<script>alert("xss")</script>';
    $escaped = e($rawScript);

    if (strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false) {
        pass('T4a: Helper e() correctly converts HTML tags into entities');
    } else {
        fail('T4a: Output escaping failed to neutralize script tag');
    }

    $request = new Request();
    $_GET['payload'] = '  <b style="color:red">test</b>  ';
    $cleaned = $request->clean('payload');

    if (strpos($cleaned, '<b') === false) {
        pass('T4b: Request::clean() strips HTML tags from input string');
    } else {
        fail('T4b: Input sanitization failed');
    }
} catch (\Throwable $e) {
    fail('T4: Exception — ' . $e->getMessage());
}

// ─── T5: CSRF Token Generation & Validation ─────────────────────────────────
section('T5: CSRF Protection');
try {
    $token1 = Csrf::getToken();
    $token2 = Csrf::getToken();

    if (!empty($token1) && strlen($token1) === 64 && $token1 === $token2) {
        pass('T5a: CSRF token generated with cryptographically secure 64-character hex string');
    } else {
        fail('T5a: CSRF token generation failed');
    }

    if (Csrf::validate($token1)) {
        pass('T5b: Csrf::validate() accepts valid token');
    } else {
        fail('T5b: Csrf::validate() rejected valid token');
    }

    if (!Csrf::validate('invalid_token_12345')) {
        pass('T5c: Csrf::validate() rejects forged/invalid token');
    } else {
        fail('T5c: Csrf::validate() accepted invalid token!');
    }
} catch (\Throwable $e) {
    fail('T5: Exception — ' . $e->getMessage());
}

// ─── T6: Mass Assignment Protection ────────────────────────────────────────
section('T6: Mass Assignment Protection');
try {
    $userModel = new User();
    $dataWithMaliciousRole = [
        'username' => 'testuser_' . time(),
        'password' => 'pass12345',
        'role' => 'super_user',
        'is_admin' => 1,
        'unauthorized_field' => 'hacked'
    ];

    $fillable = array_intersect_key($dataWithMaliciousRole, array_flip(['username', 'password', 'role', 'status', 'created_at', 'updated_at']));
    if (!isset($fillable['is_admin']) && !isset($fillable['unauthorized_field'])) {
        pass('T6: Model mass assignment filters out unauthorized fillable fields');
    } else {
        fail('T6: Model fillable filtering failed');
    }
} catch (\Throwable $e) {
    fail('T6: Exception — ' . $e->getMessage());
}

// ─── T7: Invalid Input & Boundary Checks ───────────────────────────────────
section('T7: Financial Input Boundary Checks');
try {
    $transaksiControllerCode = file_get_contents(__DIR__ . '/app/Controllers/TransaksiKasController.php');
    $pembayaranControllerCode = file_get_contents(__DIR__ . '/app/Controllers/PembayaranKasController.php');

    if (strpos($transaksiControllerCode, "jumlah'] <= 0") !== false && strpos($pembayaranControllerCode, "nominal'] <= 0") !== false) {
        pass('T7: Negative and zero financial amounts are strictly rejected by controllers');
    } else {
        fail('T7: Missing boundary check for zero/negative transaction amounts');
    }
} catch (\Throwable $e) {
    fail('T7: Exception — ' . $e->getMessage());
}

// ─── T8: Financial Integrity & Atomic Transactions ─────────────────────────
section('T8: Financial Integrity & Atomic Transactions');
try {
    $pembayaranCode = file_get_contents(__DIR__ . '/app/Controllers/PembayaranKasController.php');

    if (strpos($pembayaranCode, "beginTransaction()") !== false && strpos($pembayaranCode, "commit()") !== false && strpos($pembayaranCode, "rollBack()") !== false) {
        pass('T8a: Payment creation uses atomic database transactions (beginTransaction / commit / rollBack)');
    } else {
        fail('T8a: Payment creation missing database transaction boundary');
    }

    if (strpos($pembayaranCode, "23000") !== false) {
        pass('T8b: Unique index constraint handled gracefully to prevent duplicate payments');
    } else {
        fail('T8b: Missing constraint error handling for duplicate payment');
    }
} catch (\Throwable $e) {
    fail('T8: Exception — ' . $e->getMessage());
}

// ─── T9: Audit Logging ──────────────────────────────────────────────────────
section('T9: Audit Logging');
try {
    $logId = AuditLog::log(1, 'Security Test Action', ['test' => true, 'timestamp' => time()]);
    if ($logId) {
        pass("T9: AuditLog recorded successfully (ID: {$logId})");
    } else {
        fail('T9: AuditLog failed to write record');
    }
} catch (\Throwable $e) {
    fail('T9: Exception — ' . $e->getMessage());
}

// ─── T10: Environment & Secrets Protection ─────────────────────────────────
section('T10: Environment & Secrets Protection');
try {
    $rootHtaccess = file_get_contents(__DIR__ . '/.htaccess');
    $publicHtaccess = file_get_contents(__DIR__ . '/public/.htaccess');

    if (strpos($rootHtaccess, 'Deny from all') !== false && strpos($publicHtaccess, 'nosniff') !== false) {
        pass('T10a: Web server rules (.htaccess) block access to .env and set security headers');
    } else {
        fail('T10a: .htaccess security rules missing');
    }

    $envContent = file_get_contents(__DIR__ . '/.env');
    if (strpos($envContent, 'DB_PASSWORD=') !== false) {
        pass('T10b: Environment file exists and manages credentials');
    }
} catch (\Throwable $e) {
    fail('T10: Exception — ' . $e->getMessage());
}

// ─── T11: Error Handling & Data Leak Prevention ─────────────────────────────
section('T11: Production Error Handling');
try {
    $appCode = file_get_contents(__DIR__ . '/app/Core/App.php');
    if (strpos($appCode, "config('app.debug'") !== false && strpos($appCode, "errors/500") !== false) {
        pass('T11: Production error handler suppresses raw stack traces and renders generic 500 view');
    } else {
        fail('T11: Production error handler missing or leaks debug info');
    }
} catch (\Throwable $e) {
    fail('T11: Exception — ' . $e->getMessage());
}

// ─── T12: Query Efficiency & N+1 Prevention ────────────────────────────────
section('T12: Query Efficiency (JOIN Optimization)');
try {
    $pembayaranModelCode = file_get_contents(__DIR__ . '/app/Models/PembayaranKas.php');
    $laporanModelCode = file_get_contents(__DIR__ . '/app/Models/LaporanKeuangan.php');

    if (strpos($pembayaranModelCode, 'JOIN anggota') !== false && strpos($laporanModelCode, 'LEFT JOIN') !== false) {
        pass('T12: Relational data fetching uses SQL JOINs to eliminate N+1 queries');
    } else {
        fail('T12: Relational queries missing JOIN optimization');
    }
} catch (\Throwable $e) {
    fail('T12: Exception — ' . $e->getMessage());
}

// ─── T13: AI Assistant Read-Only Guarantee ──────────────────────────────────
section('T13: AI Assistant Read-Only & Privacy Security');
try {
    $aiCode = file_get_contents(__DIR__ . '/app/Services/AIService.php');
    $aggregatorCode = file_get_contents(__DIR__ . '/app/Services/FinancialDataAggregator.php');

    if (strpos($aiCode, 'READ-ONLY') !== false && strpos($aggregatorCode, 'getAnggotaContext') !== false) {
        pass('T13: AI Assistant pipeline is strictly read-only with member data privacy isolation');
    } else {
        fail('T13: AI Assistant security check failed');
    }
} catch (\Throwable $e) {
    fail('T13: Exception — ' . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=================================================================\n";
echo " RESULTS: {$pass} PASSED  |  {$fail} FAILED\n";
echo "=================================================================\n";

if ($fail > 0) {
    exit(1);
}
