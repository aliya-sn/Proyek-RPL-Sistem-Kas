<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Models\PembayaranKas;
use App\Models\Anggota;

class AnggotaPembayaranController extends BaseController
{
    protected PembayaranKas $pembayaranModel;
    protected Anggota $anggotaModel;

    public function __construct()
    {
        $this->pembayaranModel = new PembayaranKas();
        $this->anggotaModel = new Anggota();
    }

    public function index(Request $request): void
    {
        $userId = Session::get('user')['id'];
        $anggota = $this->anggotaModel->findByUserId($userId);

        if (!$anggota) {
            $this->redirect('/anggota/dashboard', ['error' => 'Profil anggota tidak ditemukan.']);
            return;
        }

        $anggotaId = $anggota['id'];

        $filters = [
            'anggota_id' => $anggotaId,
            'periode_tahun' => $request->input('periode_tahun', date('Y'))
        ];
        
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 12; // Menampilkan 12 bulan
        
        $totalItems = $this->pembayaranModel->countFiltered($filters);
        $totalPages = ceil($totalItems / $perPage);
        $pembayaranList = $this->pembayaranModel->getPaginated($filters, $page, $perPage);
        $totalBayar = $this->pembayaranModel->getTotalPembayaranByAnggota($anggotaId);

        $this->render('anggota/pembayaran/index', [
            'pageTitle' => 'Status Pembayaran Kas - ' . config('app.name'),
            'pembayaranList' => $pembayaranList,
            'anggota' => $anggota,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'totalBayar' => $totalBayar
        ]);
    }
}
