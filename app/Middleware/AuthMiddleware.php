<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Ensure user is authenticated.
     */
    public function handle(Request $request, ...$params): void
    {
        if (!Session::has('user')) {
            Session::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
            
            if ($request->isAjax()) {
                Response::json(['error' => 'Unauthorized', 'message' => 'Silakan login terlebih dahulu.'], 401);
                return;
            }

            Response::redirect('login');
        }
    }
}
