<?php

namespace App\Services;

/**
 * LoggerService
 * Logs AI Assistant activity without exposing secrets or API keys.
 */
class LoggerService
{
    protected static string $logPath = __DIR__ . '/../../storage/logs/ai_assistant.log';

    public static function log(string $role, ?int $userId, string $query, string $status, string $provider): void
    {
        try {
            $dir = dirname(self::$logPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            // Sanitize query to avoid newline injection in log file
            $cleanQuery = str_replace(["\r", "\n"], ' ', $query);
            if (mb_strlen($cleanQuery) > 120) {
                $cleanQuery = mb_substr($cleanQuery, 0, 117) . '...';
            }

            $timestamp = date('Y-m-d H:i:s');
            $userIdStr = $userId ? (string)$userId : 'guest';

            // Ensure no secret/API key is logged
            $logLine = sprintf(
                "[%s] [ROLE: %s] [USER_ID: %s] [PROVIDER: %s] [STATUS: %s] Query: \"%s\"\n",
                $timestamp,
                $role,
                $userIdStr,
                $provider,
                $status,
                $cleanQuery
            );

            @file_put_contents(self::$logPath, $logLine, FILE_APPEND);
        } catch (\Throwable $t) {
            // Fail silently so logging never breaks app execution
        }
    }
}
