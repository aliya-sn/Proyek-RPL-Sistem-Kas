<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Csrf;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Validate CSRF token for state-changing HTTP requests.
     */
    public function handle(Request $request, ...$params): void
    {
        $method = $request->getMethod();
        
        // Only validate mutating methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // Check body parameter or X-CSRF-TOKEN header
            $token = $request->input('_csrf_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!Csrf::validate($token)) {
                if ($request->isAjax()) {
                    Response::json(['error' => 'Invalid or expired CSRF token.'], 419);
                    exit;
                }
                
                Response::redirect('/login', 302, ['error' => 'Sesi keamanan kadaluarsa. Silakan muat ulang halaman.']);
                exit;
            }
        }
    }
}
