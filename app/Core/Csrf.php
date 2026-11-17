<?php

namespace App\Core;

class Csrf
{
    /**
     * Session key for CSRF token
     */
    protected const SESSION_KEY = '_csrf_token';

    /**
     * Generate or return existing CSRF token.
     */
    public static function getToken(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);

        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Validate CSRF token against session token.
     */
    public static function validate(?string $token): bool
    {
        Session::start();
        $sessionToken = Session::get(self::SESSION_KEY);

        if (empty($token) || empty($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate HTML hidden input tag for forms.
     */
    public static function getField(): string
    {
        $token = static::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Regenerate CSRF token.
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }
}
