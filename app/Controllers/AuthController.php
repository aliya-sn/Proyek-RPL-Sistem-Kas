<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Csrf;
use App\Core\Session;
use App\Services\AuthService;

class AuthController extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Display login form view.
     */
    public function showLogin(Request $request): void
    {
        if ($this->authService->check()) {
            $redirectPath = $this->authService->getRedirectPath();
            $this->redirect($redirectPath);
            return;
        }

        $this->render('auth/login', [
            'pageTitle' => 'Login - ' . config('app.name'),
            'errors' => Session::getFlash('errors', []),
        ], false);
    }

    /**
     * Process login form submission.
     */
    public function login(Request $request): void
    {
        // CSRF Verification
        $csrfToken = $request->input('_csrf_token');
        if (!Csrf::validate($csrfToken)) {
            $request->flashInput();
            $this->redirect('/login', ['error' => 'Sesi keamanan (CSRF Token) kadaluarsa. Silakan muat ulang halaman.']);
            return;
        }

        $username = $request->clean('username');
        $password = (string) $request->input('password');

        // Validation
        $errors = $this->validate([
            'username' => $username,
            'password' => $password,
        ], [
            'username' => 'required',
            'password' => 'required|min:4',
        ]);

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            Session::setFlash('error', 'Silakan lengkapi formulir login dengan benar.');
            $this->redirect('/login');
            return;
        }

        // Attempt Authentication
        $result = $this->authService->attempt($username, $password);

        if (!$result['success']) {
            $request->flashInput();
            $this->redirect('/login', ['error' => $result['message']]);
            return;
        }

        // Redirect to role-based dashboard
        $redirectPath = $this->authService->getRedirectPath($result['user']);
        $this->redirect($redirectPath, ['success' => $result['message']]);
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request): void
    {
        $this->authService->logout();
        $this->redirect('/login', ['info' => 'Anda telah berhasil keluar dari sistem.']);
    }

    /**
     * Show change password view.
     */
    public function showChangePassword(Request $request): void
    {
        $this->render('auth/change_password', [
            'pageTitle' => 'Ubah Password - ' . config('app.name'),
        ]);
    }

    /**
     * Handle change password submission.
     */
    public function updatePassword(Request $request): void
    {
        $csrfToken = $request->input('_csrf_token');
        if (!\App\Core\Csrf::validate($csrfToken)) {
            $this->redirect('/change-password', ['error' => 'Sesi keamanan (CSRF Token) kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $currentPassword = (string)$request->input('current_password');
        $newPassword = (string)$request->input('new_password');
        $confirmPassword = (string)$request->input('confirm_password');

        $sessionUser = \App\Core\Session::get('user');
        if (!$sessionUser) {
            $this->redirect('/login');
            return;
        }

        $userId = (int)$sessionUser['id'];
        $userModel = new \App\Models\User();
        $user = $userModel->find($userId);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $this->redirect('/change-password', ['error' => 'Password saat ini yang Anda masukkan salah.']);
            return;
        }

        if (strlen($newPassword) < 6) {
            $this->redirect('/change-password', ['error' => 'Password baru minimal harus 6 karakter.']);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->redirect('/change-password', ['error' => 'Konfirmasi password baru tidak cocok.']);
            return;
        }

        if ($newPassword === 'admin123') {
            $this->redirect('/change-password', ['error' => 'Password baru tidak boleh menggunakan password default (admin123).']);
            return;
        }

        // Update password with BCRYPT hash
        $userModel->update($userId, [
            'password' => $newPassword
        ]);

        \App\Models\AuditLog::log($userId, 'Ubah password', [
            'username' => $user['username'],
            'role' => $user['role'],
        ]);

        $this->redirect('/change-password', ['success' => 'Password berhasil diperbarui dengan aman!']);
    }
}
