<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    /**
     * Singleton PDO instance
     *
     * @var PDO|null
     */
    protected static ?PDO $instance = null;

    /**
     * Get or create PDO connection instance.
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (static::$instance !== null) {
            return static::$instance;
        }

        $default = config('database.default', 'mysql');
        $config = config("database.connections.{$default}");

        if (!$config) {
            throw new RuntimeException("Database connection '{$default}' is not configured.");
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            static::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return static::$instance;
        } catch (PDOException $e) {
            if (config('app.debug', false)) {
                throw new RuntimeException("Database Connection Error: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
            throw new RuntimeException("Terjadi kesalahan koneksi ke database. Silakan periksa konfigurasi .env.");
        }
    }

    /**
     * Test database connection.
     *
     * @return bool
     */
    public static function checkConnection(): bool
    {
        try {
            static::getConnection();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reset connection (useful for testing or reconnecting).
     *
     * @return void
     */
    public static function disconnect(): void
    {
        static::$instance = null;
    }
}
