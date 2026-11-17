<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\NotifikasiWhatsApp;
use App\Models\Anggota;
use App\Models\LaporanKeuangan;
use App\Models\AuditLog;
use App\Services\FonnteWhatsAppService;

class NotifikasiWhatsAppController extends BaseController
{
    protected NotifikasiWhatsApp $notifikasiModel;
    protected Anggota $anggotaModel;
    protected LaporanKeuangan $laporanModel;
    protected FonnteWhatsAppService $waService;

    public function __construct()
    {
        $this->notifikasiModel = new NotifikasiWhatsApp();
        $this->anggotaModel = new Anggota();
        $this->laporanModel = new LaporanKeuangan();
        $this->waService = new FonnteWhatsAppService();
    }

    /**
     * Memastikan hanya super_user dan bendahara yang boleh mengakses.
     */
    protected function ensureAuthorized(Request $request): bool
    {
        $role = Session::get('user')['role'] ?? '';
        if (!in_array($role, ['super_user', 'bendahara'], true)) {
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            } else {
                $this->redirect('/login', ['error' => 'Anda tidak memiliki hak akses ke halaman notifikasi.']);
            }
            return false;
        }
        return true;
    }

    /**
     * Halaman Riwayat Notifikasi WhatsApp.
     */
    public function index(Request $request): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $filters = [
            'search' => trim((string)$request->input('search', '')),
            'tipe' => trim((string)$request->input('tipe', '')),
            'status' => trim((string)$request->input('status', '')),
            'anggota_id' => trim((string)$request->input('anggota_id', '')),
            'tanggal_mulai' => trim((string)$request->input('tanggal_mulai', '')),
            'tanggal_akhir' => trim((string)$request->input('tanggal_akhir', ''))
        ];

        $page = max(1, (int)$request->input('page', 1));
        $perPage = 15;

        $totalItems = $this->notifikasiModel->countFiltered($filters);
        $totalPages = (int)ceil($totalItems / $perPage);
        $notifications = $this->notifikasiModel->getPaginated($filters, $page, $perPage);

        $allAnggota = $this->anggotaModel->getActive();

        // Statistik sederhana
        $totalSent = $this->notifikasiModel->count(['status' => 'sent']);
        $totalFailed = $this->notifikasiModel->count(['status' => 'failed']);
        $totalPending = $this->notifikasiModel->count(['status' => 'pending']);
        $totalAll = $this->notifikasiModel->count();

        $tokenConfigured = !empty(config('whatsapp.token') ?? env('FONNTE_API_TOKEN', ''));

        $this->render('admin/notifikasi/index', [
            'pageTitle' => 'Riwayat Notifikasi WhatsApp - ' . config('app.name'),
            'notifications' => $notifications,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'allAnggota' => $allAnggota,
            'stats' => [
                'total' => $totalAll,
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'pending' => $totalPending
            ],
            'tokenConfigured' => $tokenConfigured,
            'rolePrefix' => $this->getRolePrefix()
        ]);
    }

    /**
     * Dapatkan detail notifikasi spesifik (JSON untuk modal/AJAX).
     */
    public function show(Request $request, string $id): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $notif = $this->notifikasiModel->getWithDetails((int)$id);
        if (!$notif) {
            $this->json(['success' => false, 'message' => 'Data notifikasi tidak ditemukan.'], 404);
            return;
        }

        // Pastikan tidak ada token dalam output
        unset($notif['token']);

        $this->json(['success' => true, 'data' => $notif]);
    }

    /**
     * Kirim ulang notifikasi yang gagal atau diminta ulang.
     */
    public function resend(Request $request, string $id): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Sesi keamanan kadaluarsa.'], 403);
                return;
            }
            $this->redirect("/{$prefix}/notifikasi", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $userId = Session::get('user')['id'];
        $result = $this->waService->resendNotification((int)$id, $userId);

        // Catat ke audit log
        AuditLog::log($userId, 'Kirim Ulang Notifikasi WhatsApp', [
            'notifikasi_id' => (int)$id,
            'hasil' => $result['status'] ?? 'unknown',
            'pesan' => $result['message'] ?? ''
        ]);

        if ($request->isAjax()) {
            $this->json($result);
            return;
        }

        if ($result['success']) {
            $this->redirect("/{$prefix}/notifikasi", ['success' => 'Pesan berhasil dikirim ulang ke provider.']);
        } else {
            $this->redirect("/{$prefix}/notifikasi", ['error' => 'Gagal mengirim ulang: ' . ($result['message'] ?? 'Error tidak diketahui.')]);
        }
    }

    /**
     * Kirim pengingat tunggakan kas kepada satu anggota secara spesifik.
     */
    public function sendReminder(Request $request): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Sesi keamanan kadaluarsa.'], 403);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        $anggotaId = (int)$request->input('anggota_id');
        $bulan = (int)$request->input('periode_bulan', date('n'));
        $tahun = (int)$request->input('periode_tahun', date('Y'));
        $instruksi = trim((string)$request->input('instruksi', ''));
        $forceResend = (bool)$request->input('force_resend', false);

        $anggota = $this->anggotaModel->find($anggotaId);
        if (!$anggota) {
            $msg = 'Data anggota tidak ditemukan.';
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 404);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['error' => $msg]);
            return;
        }

        // Ambil data tunggakan anggota untuk periode tersebut
        $tunggakanList = $this->laporanModel->getLaporanTunggakan([
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'anggota_id' => $anggotaId
        ]);

        if (empty($tunggakanList)) {
            $msg = "Data tunggakan untuk {$anggota['nama']} tidak ditemukan.";
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['error' => $msg]);
            return;
        }

        $tunggakan = $tunggakanList[0];

        // Validasi: Jangan mengirim pengingat kepada anggota yang sudah lunas
        if ($tunggakan['status_bulan'] === 'lunas' || $tunggakan['minggu_belum_bayar'] <= 0) {
            $msg = "Anggota {$anggota['nama']} sudah berstatus lunas untuk periode tersebut. Pengingat tidak dikirim.";
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['warning' => $msg]);
            return;
        }

        $userId = Session::get('user')['id'];
        $result = $this->waService->sendArrearsReminder(
            $anggota,
            $bulan,
            $tahun,
            (int)$tunggakan['minggu_belum_bayar'],
            (float)$tunggakan['estimasi_tunggakan'],
            $instruksi,
            $userId,
            $forceResend
        );

        AuditLog::log($userId, 'Kirim Pengingat Tunggakan WA', [
            'anggota_id' => $anggotaId,
            'periode' => "{$bulan}/{$tahun}",
            'hasil' => $result['status'] ?? 'unknown',
            'pesan' => $result['message'] ?? ''
        ]);

        if ($request->isAjax()) {
            $this->json($result);
            return;
        }

        if ($result['success']) {
            $this->redirect("/{$prefix}/laporan/tunggakan?periode_bulan={$bulan}&periode_tahun={$tahun}", [
                'success' => "Pengingat tunggakan untuk {$anggota['nama']} berhasil dikirim ke antrean WhatsApp."
            ]);
        } elseif (!empty($result['already_sent'])) {
            $this->redirect("/{$prefix}/laporan/tunggakan?periode_bulan={$bulan}&periode_tahun={$tahun}", [
                'warning' => $result['message']
            ]);
        } else {
            $this->redirect("/{$prefix}/laporan/tunggakan?periode_bulan={$bulan}&periode_tahun={$tahun}", [
                'error' => "Gagal mengirim pengingat: " . $result['message']
            ]);
        }
    }

    /**
     * Kirim pengingat tunggakan massal kepada seluruh atau pilihan anggota yang menunggak.
     */
    public function bulkSendReminder(Request $request): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $prefix = $this->getRolePrefix();

        if (!Csrf::validate($request->input('_csrf_token'))) {
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Sesi keamanan kadaluarsa.'], 403);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['error' => 'Sesi keamanan kadaluarsa. Silakan coba lagi.']);
            return;
        }

        // Safeguard wajib: Jangan mengaktifkan pengiriman massal sebelum disetujui eksplisit
        $bulkEnabled = (bool) env('FONNTE_BULK_SENDING_ENABLED', false);
        if (!$bulkEnabled) {
            $msg = 'Pengiriman pengingat massal dinonaktifkan untuk keamanan (memerlukan persetujuan administrator).';
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 403);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan", ['warning' => $msg]);
            return;
        }

        $bulan = (int)$request->input('periode_bulan', date('n'));
        $tahun = (int)$request->input('periode_tahun', date('Y'));
        $instruksi = trim((string)$request->input('instruksi', ''));
        $forceResend = (bool)$request->input('force_resend', false);
        $selectedIds = $request->input('selected_anggota_ids', []);

        if (is_string($selectedIds)) {
            $selectedIds = array_filter(explode(',', $selectedIds));
        }

        // Ambil daftar tunggakan pada periode tersebut
        $tunggakanList = $this->laporanModel->getLaporanTunggakan([
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun
        ]);

        // Filter hanya yang menunggak (status_bulan === 'tunggak')
        $menunggakList = array_filter($tunggakanList, function($r) use ($selectedIds) {
            $isMenunggak = ($r['status_bulan'] === 'tunggak' && $r['minggu_belum_bayar'] > 0);
            if (!empty($selectedIds)) {
                return $isMenunggak && in_array((string)$r['anggota_id'], $selectedIds, true);
            }
            return $isMenunggak;
        });

        if (empty($menunggakList)) {
            $msg = 'Tidak ada anggota yang menunggak pada periode ini.';
            if ($request->isAjax()) {
                $this->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            $this->redirect("/{$prefix}/laporan/tunggakan?periode_bulan={$bulan}&periode_tahun={$tahun}", ['info' => $msg]);
            return;
        }

        $userId = Session::get('user')['id'];
        $countSent = 0;
        $countSkipped = 0;
        $countFailed = 0;
        $results = [];

        foreach ($menunggakList as $item) {
            $anggota = $this->anggotaModel->find((int)$item['anggota_id']);
            if (!$anggota) {
                continue;
            }

            $res = $this->waService->sendArrearsReminder(
                $anggota,
                $bulan,
                $tahun,
                (int)$item['minggu_belum_bayar'],
                (float)$item['estimasi_tunggakan'],
                $instruksi,
                $userId,
                $forceResend
            );

            if ($res['success']) {
                $countSent++;
                $results[] = ['nama' => $anggota['nama'], 'status' => 'sent'];
            } elseif (!empty($res['already_sent']) || !empty($res['is_lunas'])) {
                $countSkipped++;
                $results[] = ['nama' => $anggota['nama'], 'status' => 'skipped', 'reason' => $res['message']];
            } else {
                $countFailed++;
                $results[] = ['nama' => $anggota['nama'], 'status' => 'failed', 'reason' => $res['message']];
            }
        }

        AuditLog::log($userId, 'Kirim Pengingat Tunggakan Massal WA', [
            'periode' => "{$bulan}/{$tahun}",
            'total_target' => count($menunggakList),
            'terkirim' => $countSent,
            'dilewati' => $countSkipped,
            'gagal' => $countFailed
        ]);

        $summaryMsg = "Pengingat massal diproses: {$countSent} terkirim ke antrean, {$countSkipped} dilewati (sudah dikirim sebelumnya/lunas), {$countFailed} gagal.";

        if ($request->isAjax()) {
            $this->json([
                'success' => $countSent > 0 || $countSkipped > 0,
                'message' => $summaryMsg,
                'details' => [
                    'sent' => $countSent,
                    'skipped' => $countSkipped,
                    'failed' => $countFailed,
                    'items' => $results
                ]
            ]);
            return;
        }

        $flashType = $countFailed > 0 && $countSent === 0 ? 'error' : ($countFailed > 0 ? 'warning' : 'success');
        $this->redirect("/{$prefix}/laporan/tunggakan?periode_bulan={$bulan}&periode_tahun={$tahun}", [
            $flashType => $summaryMsg
        ]);
    }

    /**
     * Cek status device Fonnte secara real-time (AJAX).
     * Mengembalikan apakah device benar-benar terhubung dan siap mengirim pesan.
     * Aman: hanya membaca status dari API Fonnte, tidak mengirim pesan apapun.
     */
    public function checkDevice(Request $request): void
    {
        if (!$this->ensureAuthorized($request)) {
            return;
        }

        $status = $this->waService->checkDeviceStatus();

        if ($status['success']) {
            $isReady      = $status['is_ready'] ?? false;
            $deviceStatus = $status['device_status'] ?? 'unknown';
            $quota        = $status['quota'] ?? null;
            $expired      = $status['expired'] ?? null;

            $this->json([
                'success'       => true,
                'is_ready'      => $isReady,
                'device_status' => $deviceStatus,
                'device_number' => $status['device_number'] ?? null,
                'device_name'   => $status['device_name'] ?? null,
                'quota'         => $quota,
                'expired'       => $expired,
                'package'       => $status['package'] ?? null,
                'message'       => $isReady
                    ? "Device WhatsApp terhubung dan siap mengirim pesan. Kuota tersisa: {$quota}."
                    : "Device terdaftar di Fonnte tetapi tidak dalam status 'connect'. Silakan periksa koneksi perangkat WhatsApp Anda."
            ]);
        } else {
            $this->json([
                'success'       => false,
                'is_ready'      => false,
                'device_status' => $status['device_status'] ?? 'unknown',
                'message'       => $status['error'] ?? 'Gagal mengambil status device dari Fonnte.'
            ]);
        }
    }
}
