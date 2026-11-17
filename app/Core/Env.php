<?php

namespace App\Core;

class Env
{
    /**
     * Loaded environment variables cache
     *
     * @var array
     */
    protected static array $variables = [];

    /**
     * Load environment variables from a file.
     *
     * @param string $filePath
     * @return void
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || (!is_readable($filePath) && !is_file($filePath))) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain '='
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Convert boolean and null literals
            $value = match (strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $value,
            };

            static::$variables[$name] = $value;
            $_ENV[$name] = $value;
            putenv("{$name}=" . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value));
        }
    }

    /**
     * Get an environment variable value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$variables)) {
            return static::$variables[$key];
        }

        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        return $_ENV[$key] ?? $default;
    }
}
