<?php

namespace App\Services;

use App\Models\NotifikasiWhatsApp;

/**
 * FonnteWhatsAppService
 * Layanan integrasi resmi WhatsApp Gateway via Fonnte API (https://api.fonnte.com/send).
 */
class FonnteWhatsAppService
{
    protected string $apiToken;
    protected string $apiUrl;
    protected string $countryCode;
    protected int $timeout;
    protected NotifikasiWhatsApp $notifikasiModel;

    /**
     * Optional callable for mock HTTP transport in unit/integration testing.
     * @var callable|null
     */
    protected static $mockTransport = null;

    public function __construct()
    {
        $this->apiToken = trim((string) (env('FONNTE_API_TOKEN', '') ?: config('whatsapp.token', '')));
        $this->apiUrl = (string) (config('whatsapp.api_url') ?? env('FONNTE_API_URL', 'https://api.fonnte.com/send'));
        $this->countryCode = (string) (config('whatsapp.country_code') ?? env('FONNTE_COUNTRY_CODE', '62'));
        $this->timeout = (int) (config('whatsapp.timeout') ?? env('FONNTE_TIMEOUT', 15));
        $this->notifikasiModel = new NotifikasiWhatsApp();
    }

    /**
     * Set mock transport callback for testing (tanpa panggil API luar).
     * @param callable|null $transport function(string $url, array $headers, array $payload): array
     */
    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    /**
     * Normalisasi nomor telepon ke format yang valid untuk Fonnte & WhatsApp.
     */
    public function normalizePhoneNumber(?string $number): string
    {
        if (empty($number)) {
            return '';
        }

        // Hapus karakter non-digit kecuali tanda plus di awal
        $clean = preg_replace('/[^\d]/', '', (string)$number);

        // Jika diawali 08..., ubah ke 628...
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * Validasi nomor WhatsApp (minimal 10 digit, maksimal 16 digit setelah normalisasi 62...).
     */
    public function isValidPhoneNumber(string $normalizedNumber): bool
    {
        if (empty($normalizedNumber)) {
            return false;
        }

        // Harus diawali dengan kode negara (misal 62) dan panjang wajar 10 - 16 digit
        return preg_match('/^62\d{8,14}$/', $normalizedNumber) === 1;
    }

    /**
     * Format teks konfirmasi pembayaran kas.
     */
    public function formatPaymentConfirmationMessage(
        string $namaAnggota,
        string $kelas,
        float $nominal,
        string $tanggalBayar,
        int $periodeBulan,
        int $periodeTahun,
        ?string $kodeTransaksi = null,
        ?string $petugasNama = null
    ): string {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $strBulan = $namaBulan[$periodeBulan] ?? (string)$periodeBulan;
        $nominalFormatted = 'Rp ' . number_format($nominal, 0, ',', '.');
        $tglFormatted = date('d/m/Y', strtotime($tanggalBayar));

        $msg = "*KONFIRMASI PEMBAYARAN KAS*\n";
        $msg .= "----------------------------------------\n";
        $msg .= "Halo *{$namaAnggota}* ({$kelas}),\n";
        $msg .= "Terima kasih, pembayaran uang kas Anda telah berhasil diverifikasi dan dicatat ke dalam sistem:\n\n";
        $msg .= "• *Nominal:* {$nominalFormatted}\n";
        $msg .= "• *Periode:* {$strBulan} {$periodeTahun}\n";
        $msg .= "• *Tanggal Bayar:* {$tglFormatted}\n";
        if (!empty($kodeTransaksi)) {
            $msg .= "• *No. Transaksi:* {$kodeTransaksi}\n";
        }
        if (!empty($petugasNama)) {
            $msg .= "• *Petugas Penerima:* {$petugasNama}\n";
        }
        $msg .= "• *Status:* LUNAS\n\n";
        $msg .= "Pesan ini dikirim otomatis oleh *Sistem Keuangan Kas* sebagai bukti pembayaran resmi.\n";
        $msg .= "Semoga harimu menyenangkan! ✨";

        return $msg;
    }

    /**
     * Format teks pengingat tunggakan kas.
     */
    public function formatArrearsReminderMessage(
        string $namaAnggota,
        string $kelas,
        int $periodeBulan,
        int $periodeTahun,
        int $mingguBelumBayar,
        float $estimasiTunggakan,
        ?string $instruksi = null
    ): string {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $strBulan = $namaBulan[$periodeBulan] ?? (string)$periodeBulan;
        $nominalFormatted = $estimasiTunggakan > 0 
            ? 'Rp ' . number_format($estimasiTunggakan, 0, ',', '.') 
            : 'Belum dihitung';

        $instruksiDefault = "Silakan melakukan penyetoran langsung ke Bendahara Kelas atau menghubungi pengurus kas.";
        $instruksiText = !empty($instruksi) ? trim($instruksi) : $instruksiDefault;

        $msg = "*PENGINGAT IURAN KAS*\n";
        $msg .= "----------------------------------------\n";
        $msg .= "Halo *{$namaAnggota}* ({$kelas}),\n";
        $msg .= "Ini adalah pengingat ramah mengenai iuran kas Anda:\n\n";
        $msg .= "• *Periode:* {$strBulan} {$periodeTahun}\n";
        if ($mingguBelumBayar > 0) {
            $msg .= "• *Tunggakan:* {$mingguBelumBayar} minggu belum terbayar\n";
        }
        $msg .= "• *Estimasi Tunggakan:* {$nominalFormatted}\n\n";
        $msg .= "*Instruksi Pembayaran:*\n";
        $msg .= "{$instruksiText}\n\n";
        $msg .= "Mohon abaikan pesan ini jika Anda baru saja melakukan pembayaran dalam 1x24 jam terakhir.\n";
        $msg .= "Terima kasih atas kerja sama dan kedisiplinannya! 🙏";

        return $msg;
    }

    /**
     * Kirim konfirmasi pembayaran kas (dengan pencatatan log riwayat).
     */
    public function sendPaymentConfirmation(
        array $anggota,
        array $pembayaran,
        ?string $kodeTransaksi = null,
        ?string $petugasNama = null,
        ?int $sentByUserId = null
    ): array {
        $pembayaranId = (int)$pembayaran['id'];

        // Cegah pengiriman ganda untuk pembayaran yang sama
        $existing = $this->notifikasiModel->getByPembayaranId($pembayaranId);
        if ($existing && in_array($existing['status'], ['sent', 'pending'], true)) {
            return [
                'success' => false,
                'already_sent' => true,
                'message' => 'Konfirmasi pembayaran untuk transaksi ini sudah pernah dikirim sebelumnya.',
                'log_id' => $existing['id']
            ];
        }

        $rawPhone = $anggota['nomor_kontak'] ?? '';
        $normalizedPhone = $this->normalizePhoneNumber($rawPhone);
        
        $pesan = $this->formatPaymentConfirmationMessage(
            $anggota['nama'],
            $anggota['kelas'] ?? '',
            (float)$pembayaran['nominal'],
            $pembayaran['tanggal_bayar'],
            (int)$pembayaran['periode_bulan'],
            (int)$pembayaran['periode_tahun'],
            $kodeTransaksi,
            $petugasNama
        );

        return $this->executeSendAndRecord([
            'tipe_notifikasi' => 'payment_confirmation',
            'anggota_id' => (int)$anggota['id'],
            'pembayaran_id' => $pembayaranId,
            'periode_bulan' => (int)$pembayaran['periode_bulan'],
            'periode_tahun' => (int)$pembayaran['periode_tahun'],
            'nomor_raw' => $rawPhone,
            'nomor_tujuan' => $normalizedPhone,
            'pesan' => $pesan,
            'sent_by' => $sentByUserId
        ]);
    }

    /**
     * Kirim pengingat tunggakan kas kepada satu anggota (dengan pencegahan pengingat ganda).
     */
    public function sendArrearsReminder(
        array $anggota,
        int $periodeBulan,
        int $periodeTahun,
        int $mingguBelumBayar,
        float $estimasiTunggakan,
        ?string $instruksi = null,
        ?int $sentByUserId = null,
        bool $forceResend = false
    ): array {
        $anggotaId = (int)$anggota['id'];

        // Cek apakah anggota sudah lunas (tunggakan = 0 dan minggu_belum_bayar = 0)
        if ($mingguBelumBayar <= 0 && $estimasiTunggakan <= 0) {
            return [
                'success' => false,
                'is_lunas' => true,
                'message' => "Anggota {$anggota['nama']} sudah berstatus lunas untuk periode tersebut.",
                'log_id' => null
            ];
        }

        // Cek duplikasi pengingat untuk anggota & periode yang sama
        if (!$forceResend) {
            $existing = $this->notifikasiModel->cekPengingatTerkirim($anggotaId, $periodeBulan, $periodeTahun);
            if ($existing) {
                return [
                    'success' => false,
                    'already_sent' => true,
                    'message' => "Pengingat tunggakan untuk {$anggota['nama']} pada periode ini sudah pernah dikirim pada {$existing['created_at']}.",
                    'log_id' => $existing['id']
                ];
            }
        }

        $rawPhone = $anggota['nomor_kontak'] ?? '';
        $normalizedPhone = $this->normalizePhoneNumber($rawPhone);

        $pesan = $this->formatArrearsReminderMessage(
            $anggota['nama'],
            $anggota['kelas'] ?? '',
            $periodeBulan,
            $periodeTahun,
            $mingguBelumBayar,
            $estimasiTunggakan,
            $instruksi
        );

        return $this->executeSendAndRecord([
            'tipe_notifikasi' => 'arrears_reminder',
            'anggota_id' => $anggotaId,
            'pembayaran_id' => null,
            'periode_bulan' => $periodeBulan,
            'periode_tahun' => $periodeTahun,
            'nomor_raw' => $rawPhone,
            'nomor_tujuan' => $normalizedPhone,
            'pesan' => $pesan,
            'sent_by' => $sentByUserId
        ]);
    }

    /**
     * Kirim ulang notifikasi yang gagal atau diminta ulang secara sengaja.
     */
    public function resendNotification(int $notificationId, ?int $resendByUserId = null): array
    {
        $log = $this->notifikasiModel->find($notificationId);
        if (!$log) {
            return [
                'success' => false,
                'message' => 'Data notifikasi tidak ditemukan.'
            ];
        }

        $rawPhone = $log['nomor_tujuan'];
        $normalizedPhone = $this->normalizePhoneNumber($rawPhone);

        return $this->executeSendAndRecord([
            'tipe_notifikasi' => $log['tipe_notifikasi'],
            'anggota_id' => (int)$log['anggota_id'],
            'pembayaran_id' => !empty($log['pembayaran_id']) ? (int)$log['pembayaran_id'] : null,
            'periode_bulan' => !empty($log['periode_bulan']) ? (int)$log['periode_bulan'] : null,
            'periode_tahun' => !empty($log['periode_tahun']) ? (int)$log['periode_tahun'] : null,
            'nomor_raw' => $rawPhone,
            'nomor_tujuan' => $normalizedPhone,
            'pesan' => $log['pesan'],
            'sent_by' => $resendByUserId
        ]);
    }

    /**
     * Kirim satu pesan uji coba langsung untuk verifikasi konektivitas ke nomor WhatsApp pengguna.
     * Tidak mengubah data transaksi kas maupun pembayaran anggota.
     */
    public function sendDirectTestMessage(string $rawPhone, ?string $customMessage = null): array
    {
        $normalizedPhone = $this->normalizePhoneNumber($rawPhone);
        if (!$this->isValidPhoneNumber($normalizedPhone)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Format nomor WhatsApp tidak valid: '{$rawPhone}'. Gunakan format nomor yang benar (contoh: 081234567890 atau 6281234567890)."
            ];
        }

        if (empty($this->apiToken)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'FONNTE_API_TOKEN belum dikonfigurasi di file .env.'
            ];
        }

        $waktu = date('d/m/Y H:i:s');
        $pesan = !empty($customMessage) ? $customMessage : 
            "*TES KONEKTIVITAS WHATSAPP FONNTE — SISTEM KAS*\n" .
            "----------------------------------------\n" .
            "Halo,\n" .
            "Ini adalah pesan uji coba resmi dari *Sistem Keuangan Kas* via gateway Fonnte API.\n\n" .
            "• *Status:* Koneksi Berhasil\n" .
            "• *Waktu Kirim:* {$waktu} WIB\n" .
            "• *Provider:* Fonnte Official Gateway\n\n" .
            "Jika Anda menerima pesan ini, perangkat WhatsApp dan token API Fonnte telah aktif serta siap digunakan.";

        $apiResult = $this->callFonnteApi($normalizedPhone, $pesan);

        if ($apiResult['success']) {
            return [
                'success' => true,
                'status' => 'sent',
                'message' => 'Pesan uji coba berhasil diterima oleh antrean provider Fonnte.',
                'target' => $normalizedPhone,
                'message_id' => $apiResult['message_id'] ?? null,
                'process' => $apiResult['process'] ?? 'pending',
                'detail' => $apiResult['detail'] ?? 'message in queue',
                'raw_response' => $apiResult['raw_response'] ?? null
            ];
        }

        return [
            'success' => false,
            'status' => 'failed',
            'message' => $apiResult['error'],
            'raw_response' => $apiResult['raw_response'] ?? null
        ];
    }

    /**
     * Eksekusi pengiriman via Fonnte API dan catat ke tabel notifikasi_whatsapp.
     */
    protected function executeSendAndRecord(array $params): array
    {
        $tipe = $params['tipe_notifikasi'];
        $anggotaId = $params['anggota_id'];
        $pembayaranId = $params['pembayaran_id'];
        $periodeBulan = $params['periode_bulan'];
        $periodeTahun = $params['periode_tahun'];
        $nomorRaw = $params['nomor_raw'] ?? '';
        $nomorTujuan = $params['nomor_tujuan'];
        $pesan = $params['pesan'];
        $sentBy = $params['sent_by'];

        // 1. Validasi nomor telepon
        if (empty($nomorRaw) || empty($nomorTujuan) || !$this->isValidPhoneNumber($nomorTujuan)) {
            $errorMsg = empty($nomorRaw)
                ? 'Nomor WhatsApp anggota kosong/belum diisi di profil.'
                : "Format nomor WhatsApp tidak valid: '{$nomorRaw}'. Pastikan format Indonesia (contoh: 081234567890).";

            $logId = $this->notifikasiModel->create([
                'tipe_notifikasi' => $tipe,
                'anggota_id' => $anggotaId,
                'pembayaran_id' => $pembayaranId,
                'periode_bulan' => $periodeBulan,
                'periode_tahun' => $periodeTahun,
                'nomor_tujuan' => $nomorRaw ?: '-',
                'pesan' => $pesan,
                'status' => 'failed',
                'error_message' => $errorMsg,
                'sent_by' => $sentBy
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $errorMsg,
                'log_id' => $logId
            ];
        }

        // 2. Cek apakah Token API sudah dikonfigurasi
        if (empty($this->apiToken) && !is_callable(self::$mockTransport)) {
            $errorMsg = 'FONNTE_API_TOKEN belum dikonfigurasi di file .env server.';

            $logId = $this->notifikasiModel->create([
                'tipe_notifikasi' => $tipe,
                'anggota_id' => $anggotaId,
                'pembayaran_id' => $pembayaranId,
                'periode_bulan' => $periodeBulan,
                'periode_tahun' => $periodeTahun,
                'nomor_tujuan' => $nomorTujuan,
                'pesan' => $pesan,
                'status' => 'failed',
                'error_message' => $errorMsg,
                'sent_by' => $sentBy
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $errorMsg,
                'log_id' => $logId
            ];
        }

        // 3. Catat entri awal status 'pending'
        $logId = $this->notifikasiModel->create([
            'tipe_notifikasi' => $tipe,
            'anggota_id' => $anggotaId,
            'pembayaran_id' => $pembayaranId,
            'periode_bulan' => $periodeBulan,
            'periode_tahun' => $periodeTahun,
            'nomor_tujuan' => $nomorTujuan,
            'pesan' => $pesan,
            'status' => 'pending',
            'sent_by' => $sentBy
        ]);

        // 4. Lakukan pemanggilan HTTP ke Fonnte API
        $apiResult = $this->callFonnteApi($nomorTujuan, $pesan);

        // 5. Update entri log sesuai status respons
        if ($apiResult['success']) {
            $this->notifikasiModel->update($logId, [
                'status' => 'sent',
                'message_id' => $apiResult['message_id'],
                'provider_status' => $apiResult['process'] ?? 'accepted',
                'provider_response' => $apiResult['raw_response'],
                'error_message' => null
            ]);

            return [
                'success' => true,
                'status' => 'sent',
                'message' => 'Pesan WhatsApp berhasil diterima oleh antrean provider Fonnte.',
                'message_id' => $apiResult['message_id'],
                'log_id' => $logId
            ];
        } else {
            $this->notifikasiModel->update($logId, [
                'status' => 'failed',
                'provider_status' => 'failed',
                'provider_response' => $apiResult['raw_response'] ?? null,
                'error_message' => $apiResult['error']
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $apiResult['error'],
                'log_id' => $logId
            ];
        }
    }

    /**
     * Cek status device Fonnte secara real-time via endpoint /device.
     * Mengembalikan informasi device (status, nomor, kuota, expired) tanpa mengirim pesan.
     */
    public function checkDeviceStatus(): array
    {
        if (empty($this->apiToken)) {
            return [
                'success'       => false,
                'error'         => 'FONNTE_API_TOKEN belum dikonfigurasi di file .env server.',
                'device_status' => 'unknown'
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/device',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->apiToken,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            return [
                'success'       => false,
                'error'         => "Koneksi ke Fonnte API gagal (cURL {$curlErrno}): {$curlError}",
                'device_status' => 'unknown'
            ];
        }

        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            return [
                'success'       => false,
                'error'         => "Respons tidak valid dari Fonnte /device (HTTP {$httpCode})",
                'device_status' => 'unknown'
            ];
        }

        $apiStatus    = $json['status'] ?? false;
        $deviceStatus = $json['device_status'] ?? 'unknown';
        $reason       = $json['reason'] ?? '';

        if (!$apiStatus) {
            // Token invalid atau tidak ada device yang terdaftar
            $errorMsg = !empty($reason)
                ? "Fonnte menolak token: {$reason}"
                : 'Token tidak valid atau tidak ada device terdaftar di akun Fonnte.';
            return [
                'success'       => false,
                'error'         => $errorMsg,
                'device_status' => $deviceStatus,
                'raw'           => $json
            ];
        }

        return [
            'success'       => true,
            'device_status' => $deviceStatus,                          // 'connect' | 'disconnect'
            'device_number' => $json['device'] ?? null,
            'device_name'   => $json['name'] ?? null,
            'quota'         => $json['quota'] ?? null,
            'expired'       => $json['expired'] ?? null,
            'package'       => $json['package'] ?? null,
            'is_ready'      => ($deviceStatus === 'connect'),          // true jika siap kirim
            'raw'           => $json
        ];
    }

    /**
     * Memanggil Fonnte API endpoint /send menggunakan cURL server-to-server.
     */
    protected function callFonnteApi(string $target, string $message): array
    {
        $payload = [
            'target'      => $target,
            'message'     => $message,
            'countryCode' => $this->countryCode
        ];

        // Jika menggunakan mock transport (misal saat testing otomatis)
        if (is_callable(self::$mockTransport)) {
            $mockFn = self::$mockTransport;
            return $mockFn($this->apiUrl, ['Authorization: ' . ($this->apiToken ?: 'MOCK_TOKEN')], $payload);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->apiToken,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            return [
                'success'      => false,
                'error'        => "Koneksi ke gateway Fonnte gagal (cURL error {$curlErrno}): {$curlError}",
                'raw_response' => null
            ];
        }

        // Sanitasi respons agar tidak ada token di dalamnya
        $sanitizedResponse = $this->sanitizeResponse((string)$response);

        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            return [
                'success'      => false,
                'error'        => "Respons tidak valid dari Fonnte (HTTP {$httpCode}): " . mb_substr($sanitizedResponse, 0, 100),
                'raw_response' => $sanitizedResponse
            ];
        }

        // Cek struktur resmi respons Fonnte:
        // Berhasil: {"status": true, "id": ["80367170"], "process": "pending", "detail": "success! message in queue"}
        // Gagal:    {"status": false, "reason": "token invalid"}
        //           {"status": false, "reason": "device not connected"}
        //           {"status": false, "reason": "not enough quota"}
        $status = $json['status'] ?? false;

        if ($status === true) {
            $messageId = null;
            if (isset($json['id'])) {
                $messageId = is_array($json['id']) ? implode(',', $json['id']) : (string)$json['id'];
            }

            return [
                'success'      => true,
                'message_id'   => $messageId,
                'process'      => $json['process'] ?? 'pending',
                'detail'       => $json['detail'] ?? 'message in queue',
                'raw_response' => $sanitizedResponse
            ];
        }

        // Status false dari Fonnte — petakan reason ke pesan yang lebih informatif
        $reason      = $json['reason'] ?? $json['detail'] ?? '';
        $requestId   = $json['requestid'] ?? null;
        $errorMsg    = $this->mapFonnteError($reason, $requestId);

        return [
            'success'        => false,
            'error'          => $errorMsg,
            'fonnte_reason'  => $reason,
            'fonnte_request' => $requestId,
            'raw_response'   => $sanitizedResponse
        ];
    }

    /**
     * Petakan kode/pesan error dari Fonnte ke pesan yang lebih deskriptif dalam Bahasa Indonesia.
     */
    protected function mapFonnteError(string $reason, mixed $requestId = null): string
    {
        $lower = strtolower(trim($reason));
        $reqInfo = $requestId ? " (RequestID: {$requestId})" : '';

        // Petakan reason Fonnte yang dikenal ke pesan yang lebih informatif
        $map = [
            'token invalid'              => 'Token Fonnte tidak valid. Pastikan FONNTE_API_TOKEN di .env sesuai dengan token di dashboard Fonnte.',
            'device not connected'       => 'Perangkat WhatsApp tidak terhubung ke Fonnte. Buka dashboard Fonnte dan pastikan device berstatus Connected, atau scan ulang QR code.',
            'not enough quota'           => 'Kuota pengiriman Fonnte habis. Silakan isi ulang kuota di dashboard Fonnte.',
            'quota'                      => 'Kuota pengiriman Fonnte tidak mencukupi.',
            'target input invalid'       => 'Format nomor tujuan tidak valid menurut Fonnte.',
            'number not registered'      => 'Nomor tujuan tidak terdaftar di WhatsApp.',
            'cannot process'             => 'Fonnte tidak dapat memproses permintaan saat ini. Coba beberapa saat lagi.',
            'service unavailable'        => 'Layanan Fonnte sementara tidak tersedia. Coba beberapa saat lagi.',
            'rate limit'                 => 'Terlalu banyak permintaan pengiriman. Tunggu beberapa saat sebelum mengirim lagi.',
        ];

        foreach ($map as $key => $msg) {
            if (str_contains($lower, $key)) {
                return $msg . $reqInfo;
            }
        }

        // Fallback: tampilkan reason asli dari Fonnte agar mudah didiagnosis
        if (!empty($reason)) {
            return "Fonnte menolak pengiriman: {$reason}" . $reqInfo;
        }

        return 'Pengiriman ditolak oleh provider Fonnte.' . $reqInfo;
    }

    /**
     * Sanitasi isi respons sebelum disimpan atau dicatat, agar tidak pernah membocorkan token.
     */
    protected function sanitizeResponse(string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        if (!empty($this->apiToken)) {
            $raw = str_replace($this->apiToken, '[REDACTED_TOKEN]', $raw);
        }
        return $raw;
    }
}
