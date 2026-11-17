<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Models\Anggota;
use App\Models\AuditLog;
use App\Models\TransaksiKas;
use App\Models\PembayaranKas;
use App\Services\AuthService;

class DashboardController extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Redirect to role-based dashboard.
     */
    public function index(Request $request): void
    {
        $redirectPath = $this->authService->getRedirectPath();
        $this->redirect($redirectPath);
    }

    /**
     * Dashboard for Super User — full system overview.
     */
    public function superUserDashboard(Request $request): void
    {
        $user = Session::get('user');
        
        $anggotaModel = new Anggota();
        $auditLogModel = new AuditLog();
        $transaksiModel = new TransaksiKas();
        $pembayaranModel = new PembayaranKas();

        // Keuangan
        $totalMasuk = $transaksiModel->getTotalByJenis('masuk');
        $totalKeluar = $transaksiModel->getTotalByJenis('keluar');
        $saldoKas = $totalMasuk - $totalKeluar;

        // Anggota
        $totalAnggotaAktif = $anggotaModel->count(['status' => 'aktif']);
        
        // Pembayaran periode berjalan
        $bulanSekarang = (int)date('m');
        $tahunSekarang = (int)date('Y');
        $anggotaSudahBayar = $pembayaranModel->getCountSudahBayarPeriodeIni($bulanSekarang, $tahunSekarang);
        $anggotaBelumBayar = max(0, $totalAnggotaAktif - $anggotaSudahBayar);

        // Chart Data
        $monthlyStats = $transaksiModel->getMonthlyStats($tahunSekarang);
        
        // Prepare chart arrays (1 to 12 months)
        $chartData = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            'masuk' => array_fill(0, 12, 0),
            'keluar' => array_fill(0, 12, 0)
        ];

        foreach ($monthlyStats as $stat) {
            $monthIndex = (int)$stat['bulan'] - 1;
            $chartData['masuk'][$monthIndex] = (float)$stat['total_masuk'];
            $chartData['keluar'][$monthIndex] = (float)$stat['total_keluar'];
        }

        // Data tabular
        $recentTransactions = $transaksiModel->getRecentTransactions(5);
        $recentLogs = $auditLogModel->getRecent(5);

        $stats = [
            'saldo_kas' => $saldoKas,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'total_anggota_aktif' => $totalAnggotaAktif,
            'anggota_sudah_bayar' => $anggotaSudahBayar,
            'anggota_belum_bayar' => $anggotaBelumBayar,
        ];

        $this->render('dashboard/super_user', [
            'pageTitle' => 'Dashboard Super User - ' . config('app.name'),
            'user' => $user,
            'stats' => $stats,
            'chartData' => json_encode($chartData),
            'recentTransactions' => $recentTransactions,
            'recentLogs' => $recentLogs,
            'bulanSekarang' => format_tanggal(date('Y-m-d')),
        ]);
    }

    /**
     * Dashboard for Anggota — limited personal view.
     */
    public function anggotaDashboard(Request $request): void
    {
        $user = Session::get('user');
        $anggotaId = $user['anggota_id'] ?? 0;
        
        $anggotaModel = new Anggota();
        $pembayaranModel = new PembayaranKas();

        // Dapatkan data spesifik anggota yang sedang login (isolasi data)
        $anggota = $anggotaModel->findByUserId((int)$user['id']);
        
        if (!$anggota) {
            // Fallback jika profil belum lengkap
            $this->render('dashboard/anggota', [
                'pageTitle' => 'Dashboard Anggota - ' . config('app.name'),
                'user' => $user,
                'anggota' => null,
                'error' => 'Profil anggota Anda belum dilengkapi.'
            ]);
            return;
        }

        // Status pembayaran periode ini
        $bulanSekarang = (int)date('m');
        $tahunSekarang = (int)date('Y');
        $statusPembayaranBulanIni = $pembayaranModel->cekStatusBayarBulanIni($anggotaId, $bulanSekarang, $tahunSekarang);
        
        // Total pembayaran
        $totalPembayaran = $pembayaranModel->getTotalPembayaranByAnggota($anggotaId);
        
        // Riwayat
        $riwayatPembayaran = $pembayaranModel->getRiwayatByAnggota($anggotaId, 10);

        $stats = [
            'total_pembayaran' => $totalPembayaran,
            'status_bulan_ini' => $statusPembayaranBulanIni,
            'bulan_ini_label' => format_tanggal(date('Y-m-d')),
        ];

        $this->render('dashboard/anggota', [
            'pageTitle' => 'Dashboard Anggota - ' . config('app.name'),
            'user' => $user,
            'anggota' => $anggota,
            'stats' => $stats,
            'riwayatPembayaran' => $riwayatPembayaran
        ]);
    }

    /**
     * Dashboard for Bendahara — financial overview without admin privileges.
     */
    public function bendaharaDashboard(Request $request): void
    {
        $user = Session::get('user');
        
        $anggotaModel = new Anggota();
        $transaksiModel = new TransaksiKas();
        $pembayaranModel = new PembayaranKas();

        // Keuangan
        $totalMasuk = $transaksiModel->getTotalByJenis('masuk');
        $totalKeluar = $transaksiModel->getTotalByJenis('keluar');
        $saldoKas = $totalMasuk - $totalKeluar;

        // Anggota
        $totalAnggotaAktif = $anggotaModel->count(['status' => 'aktif']);
        
        // Pembayaran periode berjalan
        $bulanSekarang = (int)date('m');
        $tahunSekarang = (int)date('Y');
        $anggotaSudahBayar = $pembayaranModel->getCountSudahBayarPeriodeIni($bulanSekarang, $tahunSekarang);
        $anggotaBelumBayar = max(0, $totalAnggotaAktif - $anggotaSudahBayar);

        // Chart Data
        $monthlyStats = $transaksiModel->getMonthlyStats($tahunSekarang);
        
        // Prepare chart arrays (1 to 12 months)
        $chartData = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            'masuk' => array_fill(0, 12, 0),
            'keluar' => array_fill(0, 12, 0)
        ];

        foreach ($monthlyStats as $stat) {
            $monthIndex = (int)$stat['bulan'] - 1;
            $chartData['masuk'][$monthIndex] = (float)$stat['total_masuk'];
            $chartData['keluar'][$monthIndex] = (float)$stat['total_keluar'];
        }

        // Data tabular — transaksi terbaru saja, tanpa audit log
        $recentTransactions = $transaksiModel->getRecentTransactions(5);

        $stats = [
            'saldo_kas' => $saldoKas,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'total_anggota_aktif' => $totalAnggotaAktif,
            'anggota_sudah_bayar' => $anggotaSudahBayar,
            'anggota_belum_bayar' => $anggotaBelumBayar,
        ];

        $this->render('dashboard/bendahara', [
            'pageTitle' => 'Dashboard Bendahara - ' . config('app.name'),
            'user' => $user,
            'stats' => $stats,
            'chartData' => json_encode($chartData),
            'recentTransactions' => $recentTransactions,
            'bulanSekarang' => format_tanggal(date('Y-m-d')),
        ]);
    }
}
