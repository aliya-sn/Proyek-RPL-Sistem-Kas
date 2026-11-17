<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class RoleMiddleware implements MiddlewareInterface
{
    /**
     * Ensure authenticated user has one of allowed roles.
     * Usage in route: 'role:super_user' or 'role:super_user,anggota'
     *
     * @param Request $request
     * @param mixed ...$allowedRoles
     * @return void
     */
    public function handle(Request $request, ...$allowedRoles): void
    {
        $user = Session::get('user');

        if (!$user) {
            Session::setFlash('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
            Response::redirect('/login');
            return;
        }

        $userRole = $user['role'] ?? '';

        if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles, true)) {
            if ($request->isAjax()) {
                Response::json([
                    'error' => 'Forbidden',
                    'message' => 'Anda tidak memiliki hak akses ke halaman tersebut.'
                ], 403);
                return;
            }

            Session::setFlash('error', 'Anda tidak memiliki hak akses ke halaman tersebut.');

            // Redirect to the user's own dashboard instead of generic '/dashboard'
            $redirectPath = match ($userRole) {
                'super_user' => '/admin/dashboard',
                'anggota' => '/anggota/dashboard',
                'bendahara' => '/bendahara/dashboard',
                default => '/login',
            };

            Response::redirect($redirectPath);
        }
    }
}
