<?php

namespace App\Core;

class Session
{
    /**
     * Start secure session if not already started.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $lifetime = (int) config('app.session_lifetime', 7200);
                $secure = (bool) env('SESSION_SECURE_COOKIE', false);

                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            @session_start();
        }
    }

    /**
     * Set a session variable.
     */
    public static function set(string $key, mixed $value): void
    {
        static::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session variable.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        static::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        static::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    public static function remove(string $key): void
    {
        static::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Get all session data.
     */
    public static function all(): array
    {
        static::start();
        return $_SESSION ?? [];
    }

    /**
     * Set flash message (persists only for the next request).
     */
    public static function setFlash(string $key, mixed $value): void
    {
        static::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get flash message and consume it immediately or retain it if not read.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        static::start();
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Check if flash message exists.
     */
    public static function hasFlash(string $key): bool
    {
        static::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Destroy all session data.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (!headers_sent() && ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            @session_destroy();
        }
    }

    /**
     * Regenerate session ID for security upon login.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
    }
}
