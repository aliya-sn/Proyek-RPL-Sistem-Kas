<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\Anggota;
use App\Models\User;
use App\Models\AuditLog;

class AnggotaController extends BaseController
{
    protected Anggota $anggotaModel;
    protected User $userModel;

    public function __construct()
    {
        $this->anggotaModel = new Anggota();
        $this->userModel = new User();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->input('search', ''),
            'kelas' => $request->input('kelas', ''),
            'status' => $request->input('status', '')
        ];
        
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 10;
        
        $totalItems = $this->anggotaModel->countFiltered($filters);
        $totalPages = ceil($totalItems / $perPage);
        $anggotaList = $this->anggotaModel->getPaginated($filters, $page, $perPage);

        // Get unique kelas list for filter dropdown
        $db = \App\Core\Model::db();
        $stmt = $db->query("SELECT DISTINCT kelas FROM anggota WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
        $kelasList = $stmt->fetchAll();

        $this->render('admin/anggota/index', [
            'pageTitle' => 'Manajemen Anggota - ' . config('app.name'),
            'anggotaList' => $anggotaList,
            'kelasList' => $kelasList,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): void
    {
        $this->render('admin/anggota/create', [
            'pageTitle' => 'Tambah Anggota - ' . config('app.name'),
            'errors' => Session::getFlash('errors', []),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect('/admin/anggota/create', ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $data = [
            'nomor_identitas' => $request->clean('nomor_identitas'),
            'nama' => $request->clean('nama'),
            'kelas' => $request->clean('kelas'),
            'jenis_kelamin' => $request->clean('jenis_kelamin'),
            'nomor_kontak' => $request->clean('nomor_kontak'),
            'status' => $request->clean('status') ?: 'aktif'
        ];

        // Validation
        $errors = $this->validate($data, [
            'nomor_identitas' => 'required|min:3',
            'nama' => 'required|min:3',
            'kelas' => 'required',
            'jenis_kelamin' => 'required',
        ]);

        if (empty($errors)) {
            if ($this->anggotaModel->isNomorIdentitasExists($data['nomor_identitas'])) {
                $errors['nomor_identitas'] = 'Nomor identitas sudah terdaftar.';
            }
        }

        // Validate unique username (we use nomor_identitas as default username)
        $username = strtolower(str_replace(' ', '.', $data['nomor_identitas']));
        if (empty($errors) && $this->userModel->usernameExists($username)) {
            $errors['nomor_identitas'] = 'Akun pengguna untuk nomor identitas ini sudah ada.';
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect('/admin/anggota/create', ['error' => 'Silakan periksa kembali form isian Anda.']);
            return;
        }

        // Create User Account
        // Using nomor_kontak or nomor_identitas as initial password
        $initialPassword = !empty($data['nomor_kontak']) ? $data['nomor_kontak'] : $data['nomor_identitas'];
        $userStatus = in_array(strtolower((string)$data['status']), ['aktif', 'active'], true) ? 'active' : 'inactive';
        $userId = $this->userModel->register([
            'username' => $username,
            'password' => $initialPassword,
            'role' => 'anggota',
            'status' => $userStatus
        ]);

        if ($userId) {
            $data['user_id'] = (int)$userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $anggotaId = $this->anggotaModel->create($data);

            // Audit log
            $adminId = Session::get('user')['id'];
            AuditLog::log($adminId, 'Tambah Anggota', [
                'anggota_id' => $anggotaId,
                'nama' => $data['nama'],
                'nomor_identitas' => $data['nomor_identitas']
            ]);

            $this->redirect('/admin/anggota', ['success' => 'Anggota berhasil ditambahkan dan akun otomatis dibuat.']);
        } else {
            $request->flashInput();
            $this->redirect('/admin/anggota/create', ['error' => 'Gagal membuat akun pengguna.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): void
    {
        $anggota = $this->anggotaModel->getWithUser((int)$id);
        
        if (!$anggota) {
            $this->redirect('/admin/anggota', ['error' => 'Data anggota tidak ditemukan.']);
            return;
        }

        $this->render('admin/anggota/show', [
            'pageTitle' => 'Detail Anggota - ' . config('app.name'),
            'anggota' => $anggota
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id): void
    {
        $anggota = $this->anggotaModel->find((int)$id);
        
        if (!$anggota) {
            $this->redirect('/admin/anggota', ['error' => 'Data anggota tidak ditemukan.']);
            return;
        }

        $this->render('admin/anggota/edit', [
            'pageTitle' => 'Edit Anggota - ' . config('app.name'),
            'anggota' => $anggota,
            'errors' => Session::getFlash('errors', []),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect("/admin/anggota/{$id}/edit", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $anggotaId = (int)$id;
        $anggota = $this->anggotaModel->find($anggotaId);
        
        if (!$anggota) {
            $this->redirect('/admin/anggota', ['error' => 'Data anggota tidak ditemukan.']);
            return;
        }

        $data = [
            'nomor_identitas' => $request->clean('nomor_identitas'),
            'nama' => $request->clean('nama'),
            'kelas' => $request->clean('kelas'),
            'jenis_kelamin' => $request->clean('jenis_kelamin'),
            'nomor_kontak' => $request->clean('nomor_kontak'),
            'status' => $request->clean('status')
        ];

        // Validation
        $errors = $this->validate($data, [
            'nomor_identitas' => 'required|min:3',
            'nama' => 'required|min:3',
            'kelas' => 'required',
            'jenis_kelamin' => 'required',
            'status' => 'required'
        ]);

        if (empty($errors)) {
            if ($this->anggotaModel->isNomorIdentitasExists($data['nomor_identitas'], $anggotaId)) {
                $errors['nomor_identitas'] = 'Nomor identitas sudah digunakan oleh anggota lain.';
            }
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect("/admin/anggota/{$anggotaId}/edit", ['error' => 'Silakan periksa kembali form isian Anda.']);
            return;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->anggotaModel->update($anggotaId, $data);

        // Update user status to match anggota status
        if (!empty($anggota['user_id'])) {
            $userStatus = in_array(strtolower((string)$data['status']), ['aktif', 'active'], true) ? 'active' : 'inactive';
            $this->userModel->update((int)$anggota['user_id'], ['status' => $userStatus]);
        }

        // Audit log
        $adminId = Session::get('user')['id'];
        AuditLog::log($adminId, 'Edit Anggota', [
            'anggota_id' => $anggotaId,
            'perubahan' => array_diff_assoc($data, $anggota)
        ]);

        $this->redirect('/admin/anggota', ['success' => 'Data anggota berhasil diperbarui.']);
    }

    /**
     * Deactivate the specified resource (Soft Delete).
     */
    public function deactivate(Request $request, string $id): void
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            $this->redirect('/admin/anggota', ['error' => 'Sesi keamanan kadaluarsa.']);
            return;
        }

        $anggotaId = (int)$id;
        $anggota = $this->anggotaModel->find($anggotaId);
        
        if (!$anggota) {
            $this->redirect('/admin/anggota', ['error' => 'Data anggota tidak ditemukan.']);
            return;
        }

        // Set status to nonaktif
        $this->anggotaModel->update($anggotaId, [
            'status' => 'nonaktif',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!empty($anggota['user_id'])) {
            $this->userModel->update((int)$anggota['user_id'], [
                'status' => 'inactive',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Audit log
        $adminId = Session::get('user')['id'];
        AuditLog::log($adminId, 'Nonaktifkan Anggota', [
            'anggota_id' => $anggotaId,
            'nama' => $anggota['nama']
        ]);

        $this->redirect('/admin/anggota', ['success' => 'Anggota berhasil dinonaktifkan.']);
    }
}
