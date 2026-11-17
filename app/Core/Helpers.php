<?php

use App\Core\Env;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\View;

if (!function_exists('env')) {
    /**
     * Get an environment variable.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value from config files.
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset($configs[$file])) {
            $path = dirname(__DIR__, 2) . "/config/{$file}.php";
            if (file_exists($path)) {
                $configs[$file] = require $path;
            } else {
                $configs[$file] = [];
            }
        }

        $current = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            $segment = $parts[$i];
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get absolute path from application root.
     */
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $path ? $root . '/' . ltrim($path, '/') : $root;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get absolute path to app folder.
     */
    function app_path(string $path = ''): string
    {
        $app = dirname(__DIR__);
        return $path ? $app . '/' . ltrim($path, '/') : $app;
    }
}

if (!function_exists('public_path')) {
    /**
     * Get absolute path to public folder.
     */
    function public_path(string $path = ''): string
    {
        return base_path('public/' . ltrim($path, '/'));
    }
}

if (!function_exists('url')) {
    /**
     * Generate absolute URL for given relative path.
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        if (empty($baseUrl)) {
            // Auto detect from server
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $baseDir = rtrim(dirname($scriptName), '/\\');
            $baseUrl = $protocol . $host . $baseDir;
        }

        $path = ltrim($path, '/');
        return $path ? "{$baseUrl}/{$path}" : $baseUrl;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate URL for an asset.
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to another URL or route path.
     */
    function redirect(string $path, int $statusCode = 302): void
    {
        Response::redirect($path, $statusCode);
    }
}

if (!function_exists('session')) {
    /**
     * Get / set session value or return Session instance.
     */
    function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Session::all();
        }

        return Session::get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get or generate CSRF token.
     */
    function csrf_token(): string
    {
        return Csrf::getToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate HTML hidden input for CSRF token.
     */
    function csrf_field(): string
    {
        return Csrf::getField();
    }
}

if (!function_exists('auth')) {
    /**
     * Get currently logged-in user or check auth state.
     */
    function auth(): ?array
    {
        return Session::get('user', null);
    }
}

if (!function_exists('auth_check')) {
    /**
     * Check if user is logged in.
     */
    function auth_check(): bool
    {
        return Session::has('user');
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve flashed old input from previous request.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::getFlash('_old_input', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_rupiah')) {
    /**
     * Format number to Indonesian Rupiah.
     */
    function format_rupiah(float $angka): string
    {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

if (!function_exists('format_tanggal')) {
    /**
     * Format date to Indonesian format (e.g. 08 September 2026).
     */
    function format_tanggal(string $date): string
    {
        if (empty($date)) return '-';
        
        $timestamp = strtotime($date);
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $d = date('d', $timestamp);
        $m = (int)date('m', $timestamp);
        $y = date('Y', $timestamp);
        
        return $d . ' ' . $bulan[$m] . ' ' . $y;
    }
}
