<?php

namespace App\Services;

use App\Models\User;
use App\Models\Anggota;
use App\Models\AuditLog;
use App\Core\Session;

class AuthService
{
    protected User $userModel;
    protected Anggota $anggotaModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->anggotaModel = new Anggota();
    }

    /**
     * Attempt to authenticate a user.
     *
     * @param string $username
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => ?array]
     */
    public function attempt(string $username, string $password): array
    {
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username dan Password wajib diisi.'
            ];
        }

        $user = $this->userModel->findByUsername($username);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Kredensial yang Anda masukkan tidak sesuai.'
            ];
        }

        // Determine whether user account is active
        $userStatus = strtolower((string)($user['status'] ?? 'active'));
        $isActive = in_array($userStatus, ['active', 'aktif'], true);

        // For role anggota, enforce linked profile status consistency
        if ($user['role'] === 'anggota') {
            $anggota = $this->anggotaModel->findByUserId((int)$user['id']);
            if ($anggota) {
                $anggotaStatus = strtolower((string)($anggota['status'] ?? 'aktif'));
                if (in_array($anggotaStatus, ['nonaktif', 'lulus', 'inactive'], true)) {
                    // Profile is deactivated or graduated
                    $isActive = false;
                } elseif ($anggotaStatus === 'aktif') {
                    // Profile is active
                    $isActive = true;
                    // Auto-heal empty or mismatched status in users table
                    if ($user['status'] !== 'active') {
                        $this->userModel->update((int)$user['id'], ['status' => 'active']);
                        $user['status'] = 'active';
                    }
                }
            }
        }

        if (!$isActive) {
            // Audit: attempted login on inactive account
            AuditLog::log((int)$user['id'], 'Login gagal — akun nonaktif', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator.'
            ];
        }

        if (!password_verify($password, $user['password'])) {
            // Audit: failed login attempt
            AuditLog::log((int)$user['id'], 'Login gagal — password salah', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'message' => 'Password yang Anda masukkan salah.'
            ];
        }

        // Regenerate session ID to prevent session fixation
        Session::regenerate();

        // Build session user data (exclude password)
        $sessionUser = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'nama' => $user['nama'] ?? null,
            'full_name' => $user['nama'] ?? null,
            'role' => $user['role'],
            'status' => $user['status'],
        ];

        // If user is anggota, attach profile data
        if ($user['role'] === 'anggota') {
            $anggota = $this->anggotaModel->findByUserId((int)$user['id']);
            if ($anggota) {
                $sessionUser['anggota_id'] = (int)$anggota['id'];
                $sessionUser['nama'] = $anggota['nama'];
                $sessionUser['full_name'] = $anggota['nama'];
                $sessionUser['nomor_identitas'] = $anggota['nomor_identitas'];
                $sessionUser['kelas'] = $anggota['kelas'];
            }
        }

        Session::set('user', $sessionUser);

        // Audit: successful login
        AuditLog::log((int)$user['id'], 'Login berhasil', [
            'username' => $username,
            'role' => $user['role'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        $displayName = $sessionUser['nama'] ?? $sessionUser['username'];

        return [
            'success' => true,
            'message' => "Login berhasil. Selamat datang, {$displayName}!",
            'user' => $sessionUser
        ];
    }

    /**
     * Get redirect path based on user role.
     */
    public function getRedirectPath(?array $user = null): string
    {
        $user = $user ?? Session::get('user');
        if (!$user) {
            return '/login';
        }

        return match ($user['role'] ?? '') {
            'super_user' => '/admin/dashboard',
            'anggota' => '/anggota/dashboard',
            'bendahara' => '/bendahara/dashboard',
            default => '/login',
        };
    }

    /**
     * Destroy authentication session.
     */
    public function logout(): void
    {
        $user = Session::get('user');

        if ($user) {
            // Audit: logout
            AuditLog::log((int)$user['id'], 'Logout', [
                'username' => $user['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }

        Session::remove('user');
        Session::destroy();
    }

    /**
     * Get currently logged-in user.
     */
    public function user(): ?array
    {
        return Session::get('user');
    }

    /**
     * Check if user is logged in.
     */
    public function check(): bool
    {
        return Session::has('user');
    }

    /**
     * Check if current user has specific role.
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user && ($user['role'] ?? '') === $role;
    }
}
