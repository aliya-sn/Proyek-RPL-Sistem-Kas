<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\PembayaranKas;
use App\Models\TransaksiKas;
use App\Models\Anggota;
use App\Models\KategoriTransaksi;
use App\Models\AuditLog;

class PembayaranKasController extends BaseController
{
    protected PembayaranKas $pembayaranModel;
    protected TransaksiKas $transaksiModel;
    protected Anggota $anggotaModel;
    protected KategoriTransaksi $kategoriModel;

    public function __construct()
    {
        $this->pembayaranModel = new PembayaranKas();
        $this->transaksiModel = new TransaksiKas();
        $this->anggotaModel = new Anggota();
        $this->kategoriModel = new KategoriTransaksi();
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->input('search', ''),
            'anggota_id' => $request->input('anggota_id', ''),
            'periode_bulan' => $request->input('periode_bulan', ''),
            'periode_tahun' => $request->input('periode_tahun', ''),
            'status' => $request->input('status', '')
        ];
        
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 15;
        
        $totalItems = $this->pembayaranModel->countFiltered($filters);
        $totalPages = ceil($totalItems / $perPage);
        $pembayaranList = $this->pembayaranModel->getPaginated($filters, $page, $perPage);

        $anggotaList = $this->anggotaModel->getActive();

        $this->render('admin/pembayaran/index', [
            'pageTitle' => 'Pembayaran Kas - ' . config('app.name'),
            'pembayaranList' => $pembayaranList,
            'anggotaList' => $anggotaList,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    public function create(Request $request): void
    {
        $anggotaList = $this->anggotaModel->getActive();
        
        $this->render('admin/pembayaran/create', [
            'pageTitle' => 'Catat Pembayaran - ' . config('app.name'),
            'anggotaList' => $anggotaList,
            'errors' => Session::getFlash('errors', []),
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    public function store(Request $request): void
    {
        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect("/{$prefix}/pembayaran/create", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $data = [
            'anggota_id' => $request->clean('anggota_id'),
            'periode_bulan' => $request->clean('periode_bulan'),
            'periode_tahun' => $request->clean('periode_tahun'),
            'periode_minggu' => 1, // Defaulting to 1 for monthly payment
            'nominal' => str_replace(['.', ','], ['', '.'], $request->clean('nominal')),
            'tanggal_bayar' => $request->clean('tanggal_bayar'),
            'status' => $request->clean('status') ?: 'lunas'
        ];

        $errors = $this->validate($data, [
            'anggota_id' => 'required',
            'periode_bulan' => 'required',
            'periode_tahun' => 'required',
            'nominal' => 'required',
            'tanggal_bayar' => 'required'
        ]);

        if (empty($errors) && (float)$data['nominal'] <= 0) {
            $errors['nominal'] = 'Nominal harus lebih dari 0.';
        }

        if (empty($errors)) {
            // Check double payment (Unique constraint check via code for better user feedback)
            $isPaid = $this->pembayaranModel->cekStatusBayarPeriode(
                (int)$data['anggota_id'], 
                1, 
                (int)$data['periode_bulan'], 
                (int)$data['periode_tahun']
            );
            
            if ($isPaid) {
                $errors['periode_bulan'] = 'Anggota ini sudah melakukan pembayaran untuk periode tersebut.';
            }
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect("/{$prefix}/pembayaran/create", ['error' => 'Silakan periksa kembali isian form Anda.']);
            return;
        }

        $data['petugas_id'] = Session::get('user')['id'];

        $db = \App\Core\Database::getConnection();
        $db->beginTransaction();
        
        try {
            // 1. Dapatkan kategori untuk Pembayaran Kas
            $kategori = $this->kategoriModel->findBy('nama_kategori', 'Pembayaran Kas');
            $kategoriId = $kategori ? $kategori['id'] : null;

            // Jika tidak ada, gunakan kategori masuk pertama
            if (!$kategoriId) {
                $kategoriMasuk = $this->kategoriModel->getByJenis('masuk');
                if (!empty($kategoriMasuk)) {
                    $kategoriId = $kategoriMasuk[0]['id'];
                } else {
                    throw new \Exception("Kategori 'Pembayaran Kas' (Pemasukan) tidak ditemukan di database.");
                }
            }

            // 2. Buat Transaksi Kas
            $kodeTransaksi = 'PBK-' . date('YmdHis') . '-' . rand(100, 999);
            
            $anggota = $this->anggotaModel->find((int)$data['anggota_id']);
            $namaBulan = date('F', mktime(0, 0, 0, $data['periode_bulan'], 10));
            $keterangan = "Pembayaran Kas - {$anggota['nama']} ({$anggota['kelas']}) Periode {$namaBulan} {$data['periode_tahun']}";

            $transaksiId = $this->transaksiModel->create([
                'kode_transaksi' => $kodeTransaksi,
                'jenis' => 'masuk',
                'kategori_id' => $kategoriId,
                'jumlah' => $data['nominal'],
                'keterangan' => $keterangan,
                'tanggal_transaksi' => $data['tanggal_bayar'],
                'created_by' => $data['petugas_id'],
                'status' => 'aktif'
            ]);

            // 3. Buat Record Pembayaran
            $data['transaksi_id'] = $transaksiId;
            $pembayaranId = $this->pembayaranModel->create($data);

            // 4. Catat Audit Log
            AuditLog::log($data['petugas_id'], 'Tambah Pembayaran Kas', [
                'pembayaran_id' => $pembayaranId,
                'anggota_id' => $data['anggota_id'],
                'periode' => "{$data['periode_bulan']}/{$data['periode_tahun']}",
                'nominal' => (float)$data['nominal']
            ]);

            $db->commit();

            // 5. Kirim Notifikasi WhatsApp Konfirmasi Pembayaran via Fonnte API
            $flashMessage = 'Pembayaran berhasil dicatat.';
            try {
                $waService = new \App\Services\FonnteWhatsAppService();
                $petugasNama = Session::get('user')['nama'] ?? Session::get('user')['username'] ?? null;
                $pembayaranRecord = array_merge($data, ['id' => $pembayaranId]);
                $waResult = $waService->sendPaymentConfirmation(
                    $anggota,
                    $pembayaranRecord,
                    $kodeTransaksi,
                    $petugasNama,
                    $data['petugas_id']
                );

                if ($waResult['success']) {
                    $flashMessage = 'Pembayaran berhasil dicatat dan konfirmasi WhatsApp berhasil dikirim.';
                } elseif (!empty($waResult['already_sent'])) {
                    $flashMessage = 'Pembayaran berhasil dicatat. Konfirmasi WhatsApp sudah pernah dikirim sebelumnya.';
                } else {
                    $flashMessage = 'Pembayaran berhasil dicatat. (Catatan WhatsApp: ' . $waResult['message'] . ')';
                }
            } catch (\Throwable $waErr) {
                // Pastikan kegagalan notifikasi WhatsApp tidak pernah menggagalkan transaksi pembayaran
                $flashMessage = 'Pembayaran berhasil dicatat, namun terjadi kendala saat memproses notifikasi WhatsApp.';
            }

            $this->redirect("/{$prefix}/pembayaran", ['success' => $flashMessage]);
        } catch (\PDOException $e) {
            $db->rollBack();
            $request->flashInput();
            if ($e->getCode() == 23000) {
                 // Integrity constraint violation (Double Payment unique index fallback)
                 $this->redirect("/{$prefix}/pembayaran/create", ['error' => 'Terjadi duplikasi pembayaran untuk periode tersebut.']);
            } else {
                 $this->redirect("/{$prefix}/pembayaran/create", ['error' => 'Database error: ' . $e->getMessage()]);
            }
        } catch (\Exception $e) {
            $db->rollBack();
            $request->flashInput();
            $this->redirect("/{$prefix}/pembayaran/create", ['error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
    }

    public function show(Request $request, string $id): void
    {
        $pembayaran = $this->pembayaranModel->getWithDetails((int)$id);
        
        if (!$pembayaran) {
            $this->redirect('/admin/pembayaran', ['error' => 'Data pembayaran tidak ditemukan.']);
            return;
        }

        $this->render('admin/pembayaran/show', [
            'pageTitle' => 'Detail Pembayaran - ' . config('app.name'),
            'pembayaran' => $pembayaran,
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $pembayaran = $this->pembayaranModel->find((int)$id);
        
        if (!$pembayaran) {
            $this->redirect('/' . $this->getRolePrefix() . '/pembayaran', ['error' => 'Data pembayaran tidak ditemukan.']);
            return;
        }

        $anggotaList = $this->anggotaModel->getActive();

        $this->render('admin/pembayaran/edit', [
            'pageTitle' => 'Edit Pembayaran - ' . config('app.name'),
            'pembayaran' => $pembayaran,
            'anggotaList' => $anggotaList,
            'errors' => Session::getFlash('errors', []),
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            $request->flashInput();
            $this->redirect("/{$prefix}/pembayaran/{$id}/edit", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $pembayaranId = (int)$id;
        $pembayaran = $this->pembayaranModel->find($pembayaranId);
        
        if (!$pembayaran) {
            $this->redirect("/{$prefix}/pembayaran", ['error' => 'Data pembayaran tidak ditemukan.']);
            return;
        }

        $data = [
            'anggota_id' => $request->clean('anggota_id'),
            'periode_bulan' => $request->clean('periode_bulan'),
            'periode_tahun' => $request->clean('periode_tahun'),
            'nominal' => str_replace(['.', ','], ['', '.'], $request->clean('nominal')),
            'tanggal_bayar' => $request->clean('tanggal_bayar'),
            'status' => $request->clean('status')
        ];

        $errors = $this->validate($data, [
            'anggota_id' => 'required',
            'periode_bulan' => 'required',
            'periode_tahun' => 'required',
            'nominal' => 'required',
            'tanggal_bayar' => 'required',
            'status' => 'required'
        ]);

        if (empty($errors) && (float)$data['nominal'] <= 0) {
            $errors['nominal'] = 'Nominal harus lebih dari 0.';
        }

        // Check for double payment (if changing to a different period/anggota)
        if (empty($errors)) {
            $isPaid = false;
            if ($pembayaran['anggota_id'] != $data['anggota_id'] || 
                $pembayaran['periode_bulan'] != $data['periode_bulan'] || 
                $pembayaran['periode_tahun'] != $data['periode_tahun']) {
                
                $isPaid = $this->pembayaranModel->cekStatusBayarPeriode(
                    (int)$data['anggota_id'], 
                    1, 
                    (int)$data['periode_bulan'], 
                    (int)$data['periode_tahun']
                );
            }
            if ($isPaid) {
                $errors['periode_bulan'] = 'Anggota ini sudah melakukan pembayaran untuk periode tersebut.';
            }
        }

        if (!empty($errors)) {
            $request->flashInput();
            Session::setFlash('errors', $errors);
            $this->redirect("/{$prefix}/pembayaran/{$pembayaranId}/edit", ['error' => 'Silakan periksa kembali isian form Anda.']);
            return;
        }

        $db = \App\Core\Database::getConnection();
        $db->beginTransaction();
        
        try {
            $this->pembayaranModel->update($pembayaranId, $data);

            // Jika ada transaksi yang terhubung, update juga nominal & tanggalnya
            if (!empty($pembayaran['transaksi_id'])) {
                $anggota = $this->anggotaModel->find((int)$data['anggota_id']);
                $namaBulan = date('F', mktime(0, 0, 0, $data['periode_bulan'], 10));
                $keterangan = "Pembayaran Kas - {$anggota['nama']} ({$anggota['kelas']}) Periode {$namaBulan} {$data['periode_tahun']}";

                $this->transaksiModel->update((int)$pembayaran['transaksi_id'], [
                    'jumlah' => $data['nominal'],
                    'keterangan' => $keterangan,
                    'tanggal_transaksi' => $data['tanggal_bayar'],
                    'status' => $data['status'] === 'lunas' ? 'aktif' : 'void' // Jika pembayaran belum lunas, void transaksi
                ]);
            }

            $adminId = Session::get('user')['id'];
            AuditLog::log($adminId, 'Edit Pembayaran Kas', [
                'pembayaran_id' => $pembayaranId,
                'perubahan' => array_diff_assoc($data, $pembayaran)
            ]);

            $db->commit();
            $this->redirect("/{$prefix}/pembayaran", ['success' => 'Pembayaran berhasil diperbarui.']);
        } catch (\PDOException $e) {
            $db->rollBack();
            if ($e->getCode() == 23000) {
                 $this->redirect("/{$prefix}/pembayaran/{$pembayaranId}/edit", ['error' => 'Terjadi duplikasi pembayaran untuk periode tersebut.']);
            } else {
                 $this->redirect("/{$prefix}/pembayaran/{$pembayaranId}/edit", ['error' => 'Database error.']);
            }
        } catch (\Exception $e) {
            $db->rollBack();
            $this->redirect("/{$prefix}/pembayaran/{$pembayaranId}/edit", ['error' => 'Terjadi kesalahan sistem saat memperbarui.']);
        }
    }
}
