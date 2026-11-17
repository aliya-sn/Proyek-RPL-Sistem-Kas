<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class GuestMiddleware implements MiddlewareInterface
{
    /**
     * Ensure user is guest (not authenticated).
     * If authenticated, redirect to their role-based dashboard.
     */
    public function handle(Request $request, ...$params): void
    {
        if (Session::has('user')) {
            $user = Session::get('user');
            $redirectPath = match ($user['role'] ?? '') {
                'super_user' => '/admin/dashboard',
                'anggota' => '/anggota/dashboard',
                default => '/login',
            };
            Response::redirect($redirectPath);
        }
    }
}
