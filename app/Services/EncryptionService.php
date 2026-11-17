<?php

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * EncryptionService
 * Secure cryptographic helper for encrypting sensitive credentials (e.g. Gemini API Keys).
 * Uses AES-256-GCM authenticated encryption with a distinct IV per payload.
 * Key is retrieved exclusively from environment variables (APP_KEY or AI_ENCRYPTION_KEY).
 */
class EncryptionService
{
    protected const CIPHER = 'aes-256-gcm';
    protected const TAG_LENGTH = 16;
    protected const IV_LENGTH = 12;

    /**
     * Derive a 256-bit binary key from environment configuration.
     * Never stores or hardcodes the key in database.
     */
    protected static function getBinaryKey(): string
    {
        $rawSecret = Env::get('AI_ENCRYPTION_KEY', Env::get('APP_KEY', ''));
        if (empty($rawSecret)) {
            throw new RuntimeException('Kunci enkripsi aplikasi (APP_KEY / AI_ENCRYPTION_KEY) tidak ditemukan di .env.');
        }

        // SHA-256 ensures exact 32 bytes (256 bits)
        return hash('sha256', (string)$rawSecret, true);
    }

    /**
     * Encrypt sensitive plaintext string using AES-256-GCM.
     *
     * @param string $plaintext
     * @return string Base64 encoded payload: base64(iv . tag . ciphertext)
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = static::getBinaryKey();
        $iv = random_bytes(static::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            static::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            static::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Gagal mengenkripsi data menggunakan algoritma AES-256-GCM.');
        }

        // Binary pack: IV (12) + Tag (16) + Ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt AES-256-GCM ciphertext payload.
     *
     * @param string $encryptedPayload Base64 encoded payload
     * @return string|null Plaintext string on success, null on failure/tampering
     */
    public static function decrypt(string $encryptedPayload): ?string
    {
        if (empty($encryptedPayload)) {
            return null;
        }

        $binary = base64_decode($encryptedPayload, true);
        if ($binary === false || strlen($binary) <= (static::IV_LENGTH + static::TAG_LENGTH)) {
            return null;
        }

        $iv = substr($binary, 0, static::IV_LENGTH);
        $tag = substr($binary, static::IV_LENGTH, static::TAG_LENGTH);
        $ciphertext = substr($binary, static::IV_LENGTH + static::TAG_LENGTH);

        $key = static::getBinaryKey();

        $plaintext = openssl_decrypt(
            $ciphertext,
            static::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : null;
    }

    /**
     * Safely mask an API Key for frontend display.
     * Example: "AIzaSy...7TA" or "AQ.Ab8...Y7TA"
     * Ensures the full API Key is never sent to or displayed on the client.
     */
    public static function maskApiKey(?string $apiKey): string
    {
        if (empty($apiKey)) {
            return 'Belum Dikonfigurasi';
        }

        $len = strlen($apiKey);
        if ($len <= 10) {
            return '••••••••';
        }

        $prefix = substr($apiKey, 0, 6);
        $suffix = substr($apiKey, -4);

        return $prefix . '••••••••' . $suffix;
    }
}
