<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\TransaksiKas;
use App\Models\KategoriTransaksi;
use App\Models\AuditLog;

class TransaksiKasController extends BaseController
{
    protected TransaksiKas $transaksiModel;
    protected KategoriTransaksi $kategoriModel;

    public function __construct()
    {
        $this->transaksiModel = new TransaksiKas();
        $this->kategoriModel = new KategoriTransaksi();
    }

    /**
     * Tampilkan list transaksi kas.
     */
    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->input('search', ''),
            'jenis' => $request->input('jenis', ''),
            'kategori_id' => $request->input('kategori_id', ''),
            'status' => $request->input('status', '')
        ];
        
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 10;
        
        $totalItems = $this->transaksiModel->countFiltered($filters);
        $totalPages = ceil($totalItems / $perPage);
        $transaksiList = $this->transaksiModel->getPaginated($filters, $page, $perPage);

        $kategoriMasuk = $this->kategoriModel->getByJenis('masuk');
        $kategoriKeluar = $this->kategoriModel->getByJenis('keluar');
        $allKategori = array_merge($kategoriMasuk, $kategoriKeluar);

        $totalMasuk = $this->transaksiModel->getTotalByJenis('masuk');
        $totalKeluar = $this->transaksiModel->getTotalByJenis('keluar');
        $saldo = $totalMasuk - $totalKeluar;

        $this->render('admin/transaksi/index', [
            'pageTitle' => 'Transaksi Kas - ' . config('app.name'),
            'transaksiList' => $transaksiList,
            'allKategori' => $allKategori,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'totalMasuk' => $totalMasuk,
            'totalKeluar' => $totalKeluar,
            'saldo' => $saldo,
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    /**
     * Tampilkan form create.
     */
    public function create(Request $request): void
    {
        $kategoriMasuk = $this->kategoriModel->getByJenis('masuk');
        $kategoriKeluar = $this->kategoriModel->getByJenis('keluar');
        
        $this->render('admin/transaksi/create', [
            'pageTitle' => 'Tambah Transaksi - ' . config('app.name'),
            'kategoriMasuk' => $kategoriMasuk,
            'kategoriKeluar' => $kategoriKeluar,
            'errors' => Session::getFlash('errors', []),
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    /**
     * Proses penyimpanan.
     */
    public function store(Request $request): void
    {
        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect("/{$prefix}/transaksi/create", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $data = [
            'jenis' => $request->clean('jenis'),
            'kategori_id' => $request->clean('kategori_id'),
            'jumlah' => str_replace(['.', ','], ['', '.'], $request->clean('jumlah')),
            'keterangan' => $request->clean('keterangan'),
            'tanggal_transaksi' => $request->clean('tanggal_transaksi'),
        ];

        $errors = $this->validate($data, [
            'jenis' => 'required',
            'kategori_id' => 'required',
            'jumlah' => 'required',
            'keterangan' => 'required',
            'tanggal_transaksi' => 'required',
        ]);

        if (empty($errors) && (float)$data['jumlah'] <= 0) {
            $errors['jumlah'] = 'Nominal harus lebih dari 0.';
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect("/{$prefix}/transaksi/create", ['error' => 'Silakan periksa kembali isian form Anda.']);
            return;
        }

        // Generate unique code
        $kodePrefix = $data['jenis'] === 'masuk' ? 'TRM' : 'TRK';
        $data['kode_transaksi'] = $kodePrefix . '-' . date('YmdHis') . '-' . rand(100, 999);
        $data['created_by'] = Session::get('user')['id'];
        $data['status'] = 'aktif';
        
        // Simpan menggunakan basic transaction support jika memungkinkan
        $db = \App\Core\Database::getConnection();
        $db->beginTransaction();
        try {
            $transaksiId = $this->transaksiModel->create($data);
            
            AuditLog::log($data['created_by'], 'Tambah Transaksi', [
                'transaksi_id' => $transaksiId,
                'kode_transaksi' => $data['kode_transaksi'],
                'jenis' => $data['jenis'],
                'jumlah' => (float)$data['jumlah']
            ]);

            $db->commit();
            $this->redirect("/{$prefix}/transaksi", ['success' => 'Transaksi berhasil dicatat.']);
        } catch (\Exception $e) {
            $db->rollBack();
            $request->flashInput();
            $this->redirect("/{$prefix}/transaksi/create", ['error' => 'Terjadi kesalahan sistem saat menyimpan transaksi.']);
        }
    }

    /**
     * Detail transaksi.
     */
    public function show(Request $request, string $id): void
    {
        $transaksi = $this->transaksiModel->getWithDetails((int)$id);
        
        if (!$transaksi) {
            $this->redirect('/' . $this->getRolePrefix() . '/transaksi', ['error' => 'Transaksi tidak ditemukan.']);
            return;
        }

        $this->render('admin/transaksi/show', [
            'pageTitle' => 'Detail Transaksi - ' . config('app.name'),
            'transaksi' => $transaksi,
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    /**
     * Form edit.
     */
    public function edit(Request $request, string $id): void
    {
        $transaksi = $this->transaksiModel->find((int)$id);
        
        if (!$transaksi || $transaksi['status'] === 'void') {
            $this->redirect('/' . $this->getRolePrefix() . '/transaksi', ['error' => 'Transaksi tidak dapat diubah (mungkin tidak ditemukan atau sudah void).']);
            return;
        }

        $kategoriMasuk = $this->kategoriModel->getByJenis('masuk');
        $kategoriKeluar = $this->kategoriModel->getByJenis('keluar');

        $this->render('admin/transaksi/edit', [
            'pageTitle' => 'Edit Transaksi - ' . config('app.name'),
            'transaksi' => $transaksi,
            'kategoriMasuk' => $kategoriMasuk,
            'kategoriKeluar' => $kategoriKeluar,
            'errors' => Session::getFlash('errors', []),
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    /**
     * Proses update.
     */
    public function update(Request $request, string $id): void
    {
        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect("/{$prefix}/transaksi/{$id}/edit", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $transaksiId = (int)$id;
        $transaksi = $this->transaksiModel->find($transaksiId);
        
        if (!$transaksi || $transaksi['status'] === 'void') {
            $this->redirect("/{$prefix}/transaksi", ['error' => 'Transaksi tidak ditemukan atau sudah void.']);
            return;
        }

        $data = [
            'jenis' => $request->clean('jenis'),
            'kategori_id' => $request->clean('kategori_id'),
            'jumlah' => str_replace(['.', ','], ['', '.'], $request->clean('jumlah')),
            'keterangan' => $request->clean('keterangan'),
            'tanggal_transaksi' => $request->clean('tanggal_transaksi'),
        ];

        $errors = $this->validate($data, [
            'jenis' => 'required',
            'kategori_id' => 'required',
            'jumlah' => 'required',
            'keterangan' => 'required',
            'tanggal_transaksi' => 'required',
        ]);

        if (empty($errors) && (float)$data['jumlah'] <= 0) {
            $errors['jumlah'] = 'Nominal harus lebih dari 0.';
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect("/{$prefix}/transaksi/{$transaksiId}/edit", ['error' => 'Silakan periksa kembali isian form Anda.']);
            return;
        }
        
        $db = \App\Core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->transaksiModel->update($transaksiId, $data);
            
            $adminId = Session::get('user')['id'];
            AuditLog::log($adminId, 'Edit Transaksi', [
                'transaksi_id' => $transaksiId,
                'perubahan' => array_diff_assoc($data, $transaksi)
            ]);

            $db->commit();
            $this->redirect("/{$prefix}/transaksi", ['success' => 'Data transaksi berhasil diperbarui.']);
        } catch (\Exception $e) {
            $db->rollBack();
            $request->flashInput();
            $this->redirect("/{$prefix}/transaksi/{$transaksiId}/edit", ['error' => 'Terjadi kesalahan sistem saat memperbarui transaksi.']);
        }
    }

    /**
     * Proses Void (Soft Delete/Deactivate).
     */
    public function void(Request $request, string $id): void
    {
        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            $this->redirect("/{$prefix}/transaksi", ['error' => 'Sesi keamanan kadaluarsa.']);
            return;
        }

        $transaksiId = (int)$id;
        $transaksi = $this->transaksiModel->find($transaksiId);
        
        if (!$transaksi || $transaksi['status'] === 'void') {
            $this->redirect("/{$prefix}/transaksi", ['error' => 'Transaksi tidak ditemukan atau sudah void.']);
            return;
        }

        $db = \App\Core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->transaksiModel->update($transaksiId, [
                'status' => 'void'
            ]);

            // Update linked pembayaran_kas (if any) to belum_lunas
            $db->prepare("UPDATE pembayaran_kas SET status = 'belum_lunas' WHERE transaksi_id = :tid")->execute(['tid' => $transaksiId]);

            $adminId = Session::get('user')['id'];
            AuditLog::log($adminId, 'Void Transaksi', [
                'transaksi_id' => $transaksiId,
                'kode_transaksi' => $transaksi['kode_transaksi']
            ]);

            $db->commit();
            $this->redirect("/{$prefix}/transaksi", ['success' => 'Transaksi berhasil di-void.']);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect("/{$prefix}/transaksi", ['error' => 'Terjadi kesalahan sistem saat me-void transaksi.']);
        }
    }
}
