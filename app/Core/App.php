<?php

namespace App\Core;

use Throwable;

class App
{
    /**
     * Bootstrap the application.
     */
    public static function run(): void
    {
        // 1. Load environment variables
        Env::load(base_path('.env'));

        // 2. Set timezone
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

        // 3. Error reporting configuration
        if (config('app.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // 4. Start session
        Session::start();

        // 5. Load routes
        require_once base_path('routes/web.php');
        if (file_exists(base_path('routes/api.php'))) {
            require_once base_path('routes/api.php');
        }

        // 6. Handle request
        try {
            $request = new Request();
            Router::dispatch($request);
        } catch (Throwable $e) {
            static::handleException($e);
        }
    }

    /**
     * Global Exception Handler.
     */
    protected static function handleException(Throwable $e): void
    {
        Response::status(500);

        if (config('app.debug', false)) {
            $trace = $e->getTraceAsString();
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $file = $e->getFile() . ':' . $e->getLine();

            echo "<div style='font-family: monospace; background: #fff1f2; color: #9f1239; padding: 24px; border: 1px solid #fecdd3; border-radius: 8px; margin: 20px;'>";
            echo "<h2 style='margin-top: 0;'>Application Error: {$msg}</h2>";
            echo "<p><strong>Location:</strong> {$file}</p>";
            echo "<pre style='background: #ffe4e6; padding: 16px; border-radius: 4px; overflow-x: auto;'>{$trace}</pre>";
            echo "</div>";
            return;
        }

        $errorView = base_path('app/Views/errors/500.php');
        if (file_exists($errorView)) {
            echo View::render('errors/500', [
                'title' => '500 - Kesalahan Server Internal',
                'message' => 'Terjadi kesalahan pada sistem. Tim kami sedang menanganinya.'
            ], false);
            return;
        }

        echo "<h1>500 Internal Server Error</h1><p>Terjadi kesalahan pada sistem.</p>";
    }
}
