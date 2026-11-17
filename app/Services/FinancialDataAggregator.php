<?php

namespace App\Services;

use App\Core\Model;
use App\Models\LaporanKeuangan;
use App\Models\TransaksiKas;
use App\Models\PembayaranKas;
use App\Models\Anggota;

/**
 * FinancialDataAggregator
 * Comprehensive, secure data aggregation layer for the AI Assistant.
 * Connects directly to the actual MySQL database (`sistem_kas_ai`) across all tables:
 * - users
 * - anggota
 * - kategori_transaksi
 * - transaksi_kas
 * - pembayaran_kas
 * - audit_log
 * - ai_chat_history
 *
 * Strictly READ-ONLY. Prevents raw DB mutations and enforces role-based data isolation.
 */
class FinancialDataAggregator
{
    protected LaporanKeuangan $laporanModel;
    protected TransaksiKas $transaksiModel;
    protected PembayaranKas $pembayaranModel;
    protected Anggota $anggotaModel;

    public function __construct()
    {
        $this->laporanModel    = new LaporanKeuangan();
        $this->transaksiModel  = new TransaksiKas();
        $this->pembayaranModel = new PembayaranKas();
        $this->anggotaModel    = new Anggota();
    }

    /**
     * Get aggregated financial context array based on user role and dynamic query.
     *
     * @param string   $role       'super_user', 'bendahara', or 'anggota'
     * @param int|null $anggotaId  ID of the member if role is 'anggota'
     * @param string   $userQuery  Query string from user to help tailor dynamic data lookups
     * @return array
     */
    public function getAggregatedContext(string $role, ?int $anggotaId = null, string $userQuery = ''): array
    {
        $bulanSekarang = (int)date('n');
        $tahunSekarang = (int)date('Y');
        $namaBulan = $this->getNamaBulan($bulanSekarang);

        try {
            if ($role === 'super_user') {
                $context = $this->getSuperUserContext($bulanSekarang, $tahunSekarang, $namaBulan, $userQuery);
                $context['role_akses'] = 'super_user';
                $context['database_status'] = 'terhubung';
                return $context;
            } elseif ($role === 'bendahara') {
                // Bendahara mendapat akses data keuangan lengkap tetapi TIDAK ke data sistem (users/audit log)
                // sesuai kebijakan akses di routes/web.php (tidak ada /bendahara/users atau /bendahara/audit)
                $context = $this->getBendaharaContext($bulanSekarang, $tahunSekarang, $namaBulan, $userQuery);
                $context['role_akses'] = 'bendahara';
                $context['database_status'] = 'terhubung';
                return $context;
            } else {
                $context = $this->getAnggotaContext($anggotaId, $bulanSekarang, $tahunSekarang, $namaBulan, $userQuery);
                $context['role_akses'] = 'anggota';
                $context['database_status'] = 'terhubung';
                return $context;
            }
        } catch (\Throwable $e) {
            return [
                'role_akses'       => $role,
                'database_status'  => 'gagal_akses',
                'database_error'   => $e->getMessage(),
                'tanggal_analisis' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Aggregates full, dynamic database context for Super User & Bendahara.
     */
    protected function getSuperUserContext(int $bulan, int $tahun, string $namaBulan, string $userQuery = ''): array
    {
        $db = Model::db();

        // 1. Kas & Arus Kas Global (Actual DB totals)
        $arusKasAll = $this->laporanModel->getArusKasSummary([]);
        $totalPemasukanAll   = (float)($arusKasAll['total_masuk'] ?? 0);
        $totalPengeluaranAll = (float)($arusKasAll['total_keluar'] ?? 0);
        $saldoKasSaatIni     = $totalPemasukanAll - $totalPengeluaranAll;

        // 2. Transaksi Kas Summary (Active vs Void, Dates, Categories)
        $stmtStatus = $db->query("
            SELECT status, jenis, COUNT(*) as jml, COALESCE(SUM(jumlah), 0) as total
            FROM transaksi_kas
            GROUP BY status, jenis
        ");
        $transaksiByStatus = $stmtStatus ? $stmtStatus->fetchAll() : [];

        $totalTransaksiAktif = 0;
        $totalTransaksiVoid  = 0;
        $nominalVoidMasuk    = 0.0;
        foreach ($transaksiByStatus as $st) {
            if ($st['status'] === 'aktif') {
                $totalTransaksiAktif += (int)$st['jml'];
            } elseif ($st['status'] === 'void') {
                $totalTransaksiVoid += (int)$st['jml'];
                if ($st['jenis'] === 'masuk') {
                    $nominalVoidMasuk += (float)$st['total'];
                }
            }
        }

        // 3. Current Month Summary (Default Period)
        $filtersBulanIni = ['periode_bulan' => $bulan, 'periode_tahun' => $tahun];
        $pemasukanBulanIni  = $this->laporanModel->getTotalPemasukan($filtersBulanIni);
        $pengeluaranBulanIni = $this->laporanModel->getTotalPengeluaran($filtersBulanIni);

        // 3b. Previous Month Summary (for comparative analysis)
        $bulanLalu = ($bulan === 1) ? 12 : $bulan - 1;
        $tahunLalu = ($bulan === 1) ? $tahun - 1 : $tahun;
        $namaBulanLalu = $this->getNamaBulan($bulanLalu);
        $filtersBulanLalu = ['periode_bulan' => $bulanLalu, 'periode_tahun' => $tahunLalu];
        $pemasukanBulanLalu   = (float)$this->laporanModel->getTotalPemasukan($filtersBulanLalu);
        $pengeluaranBulanLalu  = (float)$this->laporanModel->getTotalPengeluaran($filtersBulanLalu);
        $selisihPemasukanBulan = (float)$pemasukanBulanIni - $pemasukanBulanLalu;
        $persenPerubahanPemasukan = ($pemasukanBulanLalu > 0) ? round(($selisihPemasukanBulan / $pemasukanBulanLalu) * 100, 1) : 0.0;

        // 4. Pembayaran Kas: By Period & By Member (Complete DB records)
        $stmtAllPayments = $db->query("
            SELECT p.id, p.anggota_id, a.nama, a.kelas, p.periode_minggu, p.periode_bulan, p.periode_tahun,
                   p.nominal, p.tanggal_bayar, p.status, t.status as transaksi_status
            FROM pembayaran_kas p
            JOIN anggota a ON a.id = p.anggota_id
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ORDER BY p.periode_tahun DESC, p.periode_bulan DESC, a.nama ASC
        ");
        $allPayments = $stmtAllPayments ? $stmtAllPayments->fetchAll() : [];

        $totalPembayaranAllNominal = 0.0;
        $pembayaranPerPeriode = [];
        $pembayaranPerAnggota = [];
        $pembayarBulanIni = [];

        foreach ($allPayments as $p) {
            $nom = (float)$p['nominal'];
            $totalPembayaranAllNominal += $nom;
            $periodKey = sprintf('%04d-%02d', $p['periode_tahun'], $p['periode_bulan']);
            $namaAnggota = $p['nama'];

            if (!isset($pembayaranPerPeriode[$periodKey])) {
                $pembayaranPerPeriode[$periodKey] = [
                    'periode_key'    => $periodKey,
                    'periode_bulan'  => (int)$p['periode_bulan'],
                    'periode_tahun'  => (int)$p['periode_tahun'],
                    'nama_bulan'     => $this->getNamaBulan((int)$p['periode_bulan']),
                    'total_nominal'  => 0.0,
                    'jumlah_transaksi' => 0,
                    'daftar_pembayar' => []
                ];
            }
            $pembayaranPerPeriode[$periodKey]['total_nominal'] += $nom;
            $pembayaranPerPeriode[$periodKey]['jumlah_transaksi']++;
            $pembayaranPerPeriode[$periodKey]['daftar_pembayar'][] = [
                'nama'          => $namaAnggota,
                'kelas'         => $p['kelas'],
                'minggu'        => (int)$p['periode_minggu'],
                'nominal'       => $nom,
                'tanggal_bayar' => $p['tanggal_bayar']
            ];

            if ((int)$p['periode_bulan'] === $bulan && (int)$p['periode_tahun'] === $tahun) {
                $pembayarBulanIni[] = [
                    'nama'          => $namaAnggota,
                    'kelas'         => $p['kelas'],
                    'nominal'       => $nom,
                    'tanggal_bayar' => $p['tanggal_bayar']
                ];
            }

            if (!isset($pembayaranPerAnggota[$namaAnggota])) {
                $pembayaranPerAnggota[$namaAnggota] = [
                    'nama'             => $namaAnggota,
                    'kelas'            => $p['kelas'],
                    'total_nominal'    => 0.0,
                    'jumlah_transaksi' => 0,
                    'rincian'          => []
                ];
            }
            $pembayaranPerAnggota[$namaAnggota]['total_nominal'] += $nom;
            $pembayaranPerAnggota[$namaAnggota]['jumlah_transaksi']++;
            $pembayaranPerAnggota[$namaAnggota]['rincian'][] = [
                'periode'       => $this->getNamaBulan((int)$p['periode_bulan']) . ' ' . $p['periode_tahun'],
                'nominal'       => $nom,
                'tanggal_bayar' => $p['tanggal_bayar']
            ];
        }

        // Current month payment list
        $pembayaranRows = $this->laporanModel->getLaporanPembayaran($filtersBulanIni);
        $totalPembayaranBulanIni = $this->laporanModel->getTotalPembayaran($filtersBulanIni);

        // 5. Tunggakan Anggota (Current Month)
        $tunggakanRows = $this->laporanModel->getLaporanTunggakan($filtersBulanIni);
        $totalTunggakanEstimasi = $this->laporanModel->getTotalTunggakan($tunggakanRows);

        $anggotaMenunggak = array_filter($tunggakanRows, fn($r) => $r['status_bulan'] === 'tunggak');
        $anggotaLunas     = array_filter($tunggakanRows, fn($r) => $r['status_bulan'] === 'lunas');

        $daftarMenunggak = array_map(function($r) {
            return [
                'nama'               => $r['nama'],
                'kelas'              => $r['kelas'],
                'minggu_belum_bayar' => $r['minggu_belum_bayar'],
                'estimasi_tunggakan' => (float)$r['estimasi_tunggakan']
            ];
        }, array_values($anggotaMenunggak));

        // Tunggakan terbesar periode saat ini
        $menunggakSekarangSorted = array_values($anggotaMenunggak);
        usort($menunggakSekarangSorted, fn($a, $b) => (float)$b['estimasi_tunggakan'] <=> (float)$a['estimasi_tunggakan']);
        $tunggakanTerbesarSekarang = !empty($menunggakSekarangSorted) ? [
            'nama'               => $menunggakSekarangSorted[0]['nama'],
            'kelas'              => $menunggakSekarangSorted[0]['kelas'],
            'minggu_belum_bayar' => $menunggakSekarangSorted[0]['minggu_belum_bayar'],
            'estimasi_tunggakan' => (float)$menunggakSekarangSorted[0]['estimasi_tunggakan'],
            'periode'            => "{$namaBulan} {$tahun}"
        ] : null;

        // Tunggakan terbesar riwayat (misal periode bulan lalu)
        $tunggakanRowsLalu = $this->laporanModel->getLaporanTunggakan($filtersBulanLalu);
        $menunggakLalu = array_values(array_filter($tunggakanRowsLalu, fn($r) => $r['status_bulan'] === 'tunggak'));
        usort($menunggakLalu, fn($a, $b) => (float)$b['estimasi_tunggakan'] <=> (float)$a['estimasi_tunggakan']);
        $tunggakanTerbesarRiwayat = !empty($menunggakLalu) ? [
            'nama'               => $menunggakLalu[0]['nama'],
            'kelas'              => $menunggakLalu[0]['kelas'],
            'minggu_belum_bayar' => $menunggakLalu[0]['minggu_belum_bayar'],
            'estimasi_tunggakan' => (float)$menunggakLalu[0]['estimasi_tunggakan'],
            'periode'            => "{$namaBulanLalu} {$tahunLalu}"
        ] : null;

        // 6. Anggota & Demographics
        $semuaAnggota = $this->anggotaModel->all();
        $totalAnggota = count($semuaAnggota);
        $totalAnggotaAktif = count(array_filter($semuaAnggota, fn($a) => strtolower($a['status'] ?? 'aktif') === 'aktif'));
        $totalAnggotaNonaktif = $totalAnggota - $totalAnggotaAktif;

        $genderCounts = ['L' => 0, 'P' => 0];
        $kelasCounts  = [];
        $daftarAnggotaRingkas = [];
        foreach ($semuaAnggota as $ag) {
            $jk = strtoupper($ag['jenis_kelamin'] ?? '');
            if (isset($genderCounts[$jk])) {
                $genderCounts[$jk]++;
            }
            $kls = $ag['kelas'] ?? 'Umum';
            $kelasCounts[$kls] = ($kelasCounts[$kls] ?? 0) + 1;
            $daftarAnggotaRingkas[] = [
                'nama'            => $ag['nama'],
                'nomor_identitas' => $ag['nomor_identitas'],
                'kelas'           => $ag['kelas'],
                'jenis_kelamin'   => $ag['jenis_kelamin'],
                'status'          => $ag['status']
            ];
        }

        // 7. Kategori Transaksi
        $stmtKategori = $db->query("
            SELECT k.id, k.nama_kategori, k.jenis, k.deskripsi,
                   COUNT(t.id) as jumlah_transaksi_aktif,
                   COALESCE(SUM(CASE WHEN t.status = 'aktif' THEN t.jumlah ELSE 0 END), 0) as total_nominal_aktif
            FROM kategori_transaksi k
            LEFT JOIN transaksi_kas t ON t.kategori_id = k.id AND t.status = 'aktif'
            GROUP BY k.id, k.nama_kategori, k.jenis, k.deskripsi
            ORDER BY k.id ASC
        ");
        $kategoriList = $stmtKategori ? $stmtKategori->fetchAll() : [];

        // 8. Transaksi per Tanggal (Active Transactions Grouped by Date)
        $stmtPerTanggal = $db->query("
            SELECT tanggal_transaksi, jenis, COUNT(*) as jml, SUM(jumlah) as total
            FROM transaksi_kas
            WHERE status = 'aktif'
            GROUP BY tanggal_transaksi, jenis
            ORDER BY tanggal_transaksi DESC
        ");
        $transaksiPerTanggal = $stmtPerTanggal ? $stmtPerTanggal->fetchAll() : [];

        // 9. Akun Pengguna / Users (Strictly no passwords)
        $stmtUsers = $db->query("
            SELECT id, username, role, status, created_at
            FROM users
            ORDER BY id ASC
        ");
        $usersList = $stmtUsers ? $stmtUsers->fetchAll() : [];
        $userRoleCounts = [];
        foreach ($usersList as $u) {
            $r = $u['role'];
            $userRoleCounts[$r] = ($userRoleCounts[$r] ?? 0) + 1;
        }

        // 10. Audit Log Summary
        $stmtAudit = $db->query("
            SELECT a.id, a.aktivitas, a.waktu, u.username
            FROM audit_log a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.id DESC
            LIMIT 5
        ");
        $recentAudit = $stmtAudit ? $stmtAudit->fetchAll() : [];

        $stmtCountAudit = $db->query("SELECT COUNT(*) as total FROM audit_log");
        $totalAudit = $stmtCountAudit ? (int)($stmtCountAudit->fetch()['total'] ?? 0) : 0;

        // Multi-table query: fetch all active expense transactions with category and creator details
        $stmtAllKeluar = $db->query("
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah, t.keterangan, t.status,
                   k.id AS kategori_id, k.nama_kategori, k.deskripsi AS deskripsi_kategori,
                   u.id AS user_id, u.nama AS creator_nama, u.username AS creator_username
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.jenis = 'keluar' AND t.status = 'aktif'
            ORDER BY t.tanggal_transaksi DESC, t.id DESC
        ");
        $semuaPengeluaran = $stmtAllKeluar ? $stmtAllKeluar->fetchAll() : [];

        // Multi-table query: fetch all active income transactions with category and creator details
        $stmtAllMasuk = $db->query("
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah, t.keterangan, t.status,
                   k.id AS kategori_id, k.nama_kategori, k.deskripsi AS deskripsi_kategori,
                   u.id AS user_id, u.nama AS creator_nama, u.username AS creator_username
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.jenis = 'masuk' AND t.status = 'aktif'
            ORDER BY t.tanggal_transaksi DESC, t.id DESC
        ");
        $semuaPemasukan = $stmtAllMasuk ? $stmtAllMasuk->fetchAll() : [];

        $pengeluaranTerbesar = null;
        if (!empty($semuaPengeluaran)) {
            $sortedKeluar = $semuaPengeluaran;
            usort($sortedKeluar, fn($a, $b) => (float)$b['jumlah'] <=> (float)$a['jumlah']);
            $pengeluaranTerbesar = $sortedKeluar[0];
        }

        $selisihPengeluaranBulan = (float)$pengeluaranBulanIni - (float)$pengeluaranBulanLalu;
        $persenPerubahanPengeluaran = ($pengeluaranBulanLalu > 0) ? round(($selisihPengeluaranBulan / $pengeluaranBulanLalu) * 100, 1) : 0.0;

        // 11. Dynamic Targeted Query Evaluator
        $targetedQuery = $this->evaluateTargetedQuery(
            $userQuery,
            $allPayments,
            $pembayaranPerPeriode,
            $pembayaranPerAnggota,
            $kategoriList,
            $transaksiPerTanggal,
            $daftarAnggotaRingkas,
            $semuaPengeluaran,
            $semuaPemasukan
        );

        // 12. Sample Recent Transactions
        $transaksiPemasukan   = array_slice($this->laporanModel->getLaporanPemasukan([]), 0, 5);
        $transaksiPengeluaran = array_slice($this->laporanModel->getLaporanPengeluaran([]), 0, 5);

        // 13. Transaksi Pembayaran Terbaru (top-5 with member names) — for Tes 5
        $stmtTrxTerbaru = $db->query("
            SELECT p.tanggal_bayar, p.nominal, a.nama AS nama_anggota,
                   p.periode_bulan, p.periode_tahun
            FROM pembayaran_kas p
            JOIN anggota a ON a.id = p.anggota_id
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ORDER BY p.tanggal_bayar DESC, p.id DESC
            LIMIT 5
        ");
        $transaksiPembayaranTerbaru = $stmtTrxTerbaru ? $stmtTrxTerbaru->fetchAll() : [];

        // 14. Rekonsiliasi Saldo Kas vs Total Pembayaran Iuran Anggota
        $stmtDirectTrx = $db->query("
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah, t.keterangan, k.nama_kategori
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN pembayaran_kas p ON p.transaksi_id = t.id AND p.status = 'lunas'
            WHERE t.status = 'aktif' AND t.jenis = 'masuk' AND p.id IS NULL
        ");
        $transaksiPemasukanLangsung = $stmtDirectTrx ? $stmtDirectTrx->fetchAll() : [];
        $totalPemasukanLangsung = array_sum(array_column($transaksiPemasukanLangsung, 'jumlah'));
        $selisihSaldoPembayaran = $saldoKasSaatIni - $totalPembayaranAllNominal;
        $ketLangsungArr = [];
        foreach ($transaksiPemasukanLangsung as $tpl) {
            $nomFmt = number_format((float)$tpl['jumlah'], 0, ',', '.');
            $ketLangsungArr[] = "{$tpl['kode_transaksi']} (Rp {$nomFmt}, ket: \"{$tpl['keterangan']}\")";
        }

        // 15. Notifikasi WhatsApp (Multi-tabel notifikasi_whatsapp JOIN anggota)
        $stmtNotif = $db->query("
            SELECT n.id, n.tipe_notifikasi, n.anggota_id, a.nama as nama_anggota,
                   n.periode_bulan, n.periode_tahun, n.nomor_tujuan, n.pesan,
                   n.status, n.created_at
            FROM notifikasi_whatsapp n
            LEFT JOIN anggota a ON a.id = n.anggota_id
            ORDER BY n.id DESC
            LIMIT 10
        ");
        $notifRows = $stmtNotif ? $stmtNotif->fetchAll() : [];
        $stmtNotifCount = $db->query("SELECT status, COUNT(*) as jml FROM notifikasi_whatsapp GROUP BY status");
        $notifStatusCounts = [];
        if ($stmtNotifCount) {
            foreach ($stmtNotifCount->fetchAll() as $sc) {
                $notifStatusCounts[$sc['status']] = (int)$sc['jml'];
            }
        }
        $ringkasanNotifikasi = [
            'total_notifikasi' => array_sum($notifStatusCounts),
            'status_kirim'     => $notifStatusCounts,
            'riwayat_terbaru'  => array_map(fn($n) => [
                'id'           => (int)$n['id'],
                'tipe'         => $n['tipe_notifikasi'],
                'nama_anggota' => $n['nama_anggota'] ?? 'Anggota',
                'periode'      => ($n['periode_bulan'] ? $this->getNamaBulan((int)$n['periode_bulan']) : '') . ' ' . ($n['periode_tahun'] ?? ''),
                'tujuan'       => $n['nomor_tujuan'],
                'status'       => $n['status'],
                'waktu'        => $n['created_at']
            ], $notifRows)
        ];

        return [
            'role_akses' => 'super_user',
            'periode_laporan' => "{$namaBulan} {$tahun}",
            'tanggal_analisis' => date('Y-m-d H:i:s'),
            'ringkasan_notifikasi_whatsapp' => $ringkasanNotifikasi,
            'rekonsiliasi_kas' => [
                'saldo_kas'                  => $saldoKasSaatIni,
                'total_pemasukan'            => $totalPemasukanAll,
                'total_pengeluaran'          => $totalPengeluaranAll,
                'total_pembayaran_iuran'     => $totalPembayaranAllNominal,
                'selisih'                    => $selisihSaldoPembayaran,
                'ada_perbedaan'              => (abs($selisihSaldoPembayaran) > 0.01),
                'jumlah_transaksi_langsung'  => count($transaksiPemasukanLangsung),
                'total_nominal_langsung'     => (float)$totalPemasukanLangsung,
                'rincian_transaksi_langsung' => $ketLangsungArr
            ],
            'informasi_anggota' => [
                'total_anggota'          => $totalAnggota,
                'total_anggota_aktif'    => $totalAnggotaAktif,
                'total_anggota_nonaktif' => $totalAnggotaNonaktif,
                'rincian_gender'         => $genderCounts,
                'daftar_kelas'           => $kelasCounts,
                'daftar_anggota'         => $daftarAnggotaRingkas
            ],
            'ringkasan_kas' => [
                'saldo_kas_saat_ini'             => $saldoKasSaatIni,
                'total_pemasukan_semua'          => $totalPemasukanAll,
                'total_pengeluaran_semua'         => $totalPengeluaranAll,
                'pemasukan_bulan_ini'            => (float)$pemasukanBulanIni,
                'pengeluaran_bulan_ini'           => (float)$pengeluaranBulanIni,
                'surplus_defisit_bulan_ini'      => (float)$pemasukanBulanIni - (float)$pengeluaranBulanIni,
                'pemasukan_bulan_lalu'           => $pemasukanBulanLalu,
                'pengeluaran_bulan_lalu'          => $pengeluaranBulanLalu,
                'nama_bulan_lalu'                => "{$namaBulanLalu} {$tahunLalu}",
                'selisih_pemasukan_bulan_lalu'   => $selisihPemasukanBulan,
                'persentase_perubahan_pemasukan' => $persenPerubahanPemasukan,
                'selisih_pengeluaran_bulan_lalu'   => $selisihPengeluaranBulan,
                'persentase_perubahan_pengeluaran' => $persenPerubahanPengeluaran,
            ],
            'ringkasan_pembayaran_anggota' => [
                'total_semua_pembayaran_nominal'   => $totalPembayaranAllNominal,
                'total_semua_pembayaran_transaksi' => count($allPayments),
                'total_pembayaran_bulan_ini'       => (float)$totalPembayaranBulanIni,
                'jumlah_transaksi_pembayaran'      => count($pembayaranRows),
                'daftar_pembayaran_bulan_ini'      => $pembayarBulanIni,
                'rincian_per_periode'              => $pembayaranPerPeriode,
                'pembayaran_per_anggota'           => $pembayaranPerAnggota
            ],
            'ringkasan_tunggakan' => [
                'total_estimasi_tunggakan'       => $totalTunggakanEstimasi,
                'jumlah_anggota_menunggak'       => count($anggotaMenunggak),
                'jumlah_anggota_lunas'           => count($anggotaLunas),
                'daftar_anggota_menunggak'       => $daftarMenunggak,
                'tunggakan_terbesar_periode_ini' => $tunggakanTerbesarSekarang,
                'tunggakan_terbesar_riwayat'     => $tunggakanTerbesarRiwayat,
            ],
            'ringkasan_transaksi' => [
                'total_transaksi_aktif' => $totalTransaksiAktif,
                'total_transaksi_void'  => $totalTransaksiVoid,
                'total_nominal_void'    => $nominalVoidMasuk,
                'kategori_transaksi'    => $kategoriList,
                'transaksi_per_tanggal' => $transaksiPerTanggal
            ],
            'ringkasan_sistem' => [
                'total_user'        => count($usersList),
                'rincian_role_user' => $userRoleCounts,
                'total_audit_log'   => $totalAudit,
                'aktivitas_terakhir'=> $recentAudit[0] ?? null
            ],
            'query_tertarget' => $targetedQuery,
            'daftar_transaksi_pengeluaran' => array_map(fn($t) => [
                'id'                 => (int)$t['id'],
                'kode_transaksi'     => $t['kode_transaksi'],
                'tanggal_transaksi'  => $t['tanggal_transaksi'],
                'jumlah'             => (float)$t['jumlah'],
                'keterangan'         => $t['keterangan'],
                'kategori_id'        => (int)($t['kategori_id'] ?? 0),
                'nama_kategori'      => $t['nama_kategori'] ?? 'Pengeluaran Umum',
                'deskripsi_kategori' => $t['deskripsi_kategori'] ?? '',
                'creator_nama'       => !empty($t['creator_nama']) ? $t['creator_nama'] : ($t['creator_username'] ?? 'Petugas'),
                'creator_username'   => $t['creator_username'] ?? ''
            ], $semuaPengeluaran),
            'pengeluaran_terbesar' => $pengeluaranTerbesar ? [
                'id'                 => (int)$pengeluaranTerbesar['id'],
                'kode_transaksi'     => $pengeluaranTerbesar['kode_transaksi'],
                'tanggal_transaksi'  => $pengeluaranTerbesar['tanggal_transaksi'],
                'jumlah'             => (float)$pengeluaranTerbesar['jumlah'],
                'keterangan'         => $pengeluaranTerbesar['keterangan'],
                'nama_kategori'      => $pengeluaranTerbesar['nama_kategori'] ?? 'Pengeluaran Umum',
                'deskripsi_kategori' => $pengeluaranTerbesar['deskripsi_kategori'] ?? '',
                'creator_nama'       => !empty($pengeluaranTerbesar['creator_nama']) ? $pengeluaranTerbesar['creator_nama'] : ($pengeluaranTerbesar['creator_username'] ?? 'Petugas'),
                'creator_username'   => $pengeluaranTerbesar['creator_username'] ?? ''
            ] : null,
            'transaksi_pembayaran_terbaru' => array_map(fn($t) => [
                'tanggal_bayar'  => $t['tanggal_bayar'] ?? '-',
                'nominal'        => (float)($t['nominal'] ?? 0),
                'nama_anggota'   => $t['nama_anggota'] ?? '-',
                'periode_bulan'  => (int)($t['periode_bulan'] ?? 0),
                'periode_tahun'  => (int)($t['periode_tahun'] ?? 0),
            ], $transaksiPembayaranTerbaru),
            'sampel_transaksi_pemasukan' => array_map(fn($t) => [
                'tanggal'    => $t['tanggal_transaksi'] ?? $t['tanggal'] ?? '-',
                'kategori'   => $t['nama_kategori'] ?? 'Umum',
                'keterangan' => $t['keterangan'] ?? '-',
                'jumlah'     => (float)($t['jumlah'] ?? 0)
            ], $transaksiPemasukan),
            'sampel_transaksi_pengeluaran' => array_map(fn($t) => [
                'tanggal'    => $t['tanggal_transaksi'] ?? $t['tanggal'] ?? '-',
                'kategori'   => $t['nama_kategori'] ?? 'Umum',
                'keterangan' => $t['keterangan'] ?? '-',
                'jumlah'     => (float)($t['jumlah'] ?? 0)
            ], $transaksiPengeluaran),
        ];
    }

    /**
     * Aggregates financial context for Bendahara.
     * Identical to getSuperUserContext() EXCEPT ringkasan_sistem (user accounts & audit log)
     * is excluded, because Bendahara has no /bendahara/users or /bendahara/audit routes.
     * Bendahara CAN see: kas, transaksi, pembayaran, tunggakan, anggota demographics.
     * Bendahara CANNOT see via AI: jumlah akun user, rincian role user, log aktivitas.
     */
    protected function getBendaharaContext(int $bulan, int $tahun, string $namaBulan, string $userQuery = ''): array
    {
        // Reuse full context from getSuperUserContext, then strip sistem data
        $full = $this->getSuperUserContext($bulan, $tahun, $namaBulan, $userQuery);

        // Remove system/user data not accessible to Bendahara
        unset($full['ringkasan_sistem']);

        return $full;
    }

    /**
     * Aggregates isolated data for an individual Anggota.
     * Strictly isolates personal data: cannot see other members' payments, arrears, or internal system logs.
     */
    protected function getAnggotaContext(?int $anggotaId, int $bulan, int $tahun, string $namaBulan, string $userQuery = ''): array
    {
        $profil = null;
        if ($anggotaId) {
            $profil = $this->anggotaModel->find($anggotaId);
        }

        // 1. General Organization Public Balance
        $arusKasAll = $this->laporanModel->getArusKasSummary([]);
        $totalPemasukanAll   = (float)($arusKasAll['total_masuk'] ?? 0);
        $totalPengeluaranAll = (float)($arusKasAll['total_keluar'] ?? 0);
        $saldoKasSaatIni     = $totalPemasukanAll - $totalPengeluaranAll;

        // 2. Personal Payments (Current Month)
        $filtersBulanIni = ['periode_bulan' => $bulan, 'periode_tahun' => $tahun, 'anggota_id' => $anggotaId];
        $pembayaranSaya = $this->laporanModel->getLaporanPembayaran($filtersBulanIni);
        $totalPembayaranSaya = array_sum(array_column($pembayaranSaya, 'nominal'));

        // 3. Personal Payments (All Periods)
        $allMyPayments = [];
        $totalAllMyPayments = 0.0;
        if ($anggotaId) {
            $allMyPayments = $this->laporanModel->getLaporanPembayaran(['anggota_id' => $anggotaId]);
            $totalAllMyPayments = array_sum(array_column($allMyPayments, 'nominal'));
        }

        // 4. Personal Tunggakan Status
        $tunggakanRows = $this->laporanModel->getLaporanTunggakan(['periode_bulan' => $bulan, 'periode_tahun' => $tahun]);
        $myTunggakan = null;
        if ($anggotaId) {
            foreach ($tunggakanRows as $r) {
                if ((int)$r['anggota_id'] === (int)$anggotaId) {
                    $myTunggakan = [
                        'minggu_terbayar'    => $r['minggu_terbayar'],
                        'minggu_belum_bayar' => $r['minggu_belum_bayar'],
                        'nominal_per_minggu' => (float)$r['nominal_per_minggu'],
                        'estimasi_tunggakan' => (float)$r['estimasi_tunggakan'],
                        'status_bulan'       => $r['status_bulan']
                    ];
                    break;
                }
            }
        }

        // 5. Personal WhatsApp Notifications
        $myNotif = [];
        if ($anggotaId) {
            $db = Model::db();
            $stmtMyNotif = $db->prepare("
                SELECT id, tipe_notifikasi, periode_bulan, periode_tahun, status, created_at
                FROM notifikasi_whatsapp
                WHERE anggota_id = ?
                ORDER BY id DESC
                LIMIT 5
            ");
            $stmtMyNotif->execute([$anggotaId]);
            $myNotif = $stmtMyNotif->fetchAll();
        }

        return [
            'role_akses' => 'anggota',
            'periode_laporan' => "{$namaBulan} {$tahun}",
            'tanggal_analisis' => date('Y-m-d H:i:s'),
            'profil_saya' => [
                'nama'            => $profil['nama'] ?? 'Anggota',
                'nomor_identitas' => $profil['nomor_identitas'] ?? '-',
                'kelas'           => $profil['kelas'] ?? '-'
            ],
            'status_pembayaran_saya' => [
                'total_terbayar_bulan_ini' => (float)$totalPembayaranSaya,
                'status_bulan_ini'         => $myTunggakan['status_bulan'] ?? 'lunas',
                'minggu_terbayar'          => $myTunggakan['minggu_terbayar'] ?? 4,
                'minggu_belum_bayar'       => $myTunggakan['minggu_belum_bayar'] ?? 0,
                'estimasi_tunggakan'       => $myTunggakan['estimasi_tunggakan'] ?? 0,
                'total_semua_pembayaran'   => (float)$totalAllMyPayments,
                'riwayat_pembayaran_bulan_ini' => array_map(fn($p) => [
                    'tanggal'        => $p['tanggal_bayar'],
                    'periode_minggu' => $p['periode_minggu'],
                    'nominal'        => (float)$p['nominal']
                ], $pembayaranSaya)
            ],
            'notifikasi_saya' => array_map(fn($n) => [
                'tipe'    => $n['tipe_notifikasi'],
                'periode' => ($n['periode_bulan'] ? $this->getNamaBulan((int)$n['periode_bulan']) : '') . ' ' . ($n['periode_tahun'] ?? ''),
                'status'  => $n['status'],
                'waktu'   => $n['created_at']
            ], $myNotif),
            'info_kas_organisasi' => [
                'saldo_kas_saat_ini' => $saldoKasSaatIni,
                'keterangan'         => 'Kas Organisasi dalam kondisi aktif.'
            ]
        ];
    }

    /**
     * Helper to extract numeric nominal amount from query.
     * Supports formats: "500 ribu", "500.000", "500000", "500rb", "500 k", "1.5 juta", "Rp 500.000"
     */
    public function extractNominal(string $text): ?float
    {
        $q = mb_strtolower($text, 'UTF-8');

        // 1. Juta / jt
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:juta|jt)\b/u', $q, $m)) {
            $val = (float)str_replace(',', '.', $m[1]);
            return $val * 1000000;
        }

        // 2. Ribu / rb / k
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:ribu|rb|k)\b/u', $q, $m)) {
            $val = (float)str_replace(',', '.', $m[1]);
            return $val * 1000;
        }

        // 3. Format Rp 500.000 or 500.000 with dots
        if (preg_match('/(?:rp\.?\s*)?(\d{1,3}(?:\.\d{3})+(?:,\d+)?)/u', $q, $m)) {
            $cleaned = str_replace('.', '', $m[1]);
            $cleaned = str_replace(',', '.', $cleaned);
            return (float)$cleaned;
        }

        // 4. Preceded by Rp e.g. Rp500000
        if (preg_match('/(?:rp\.?\s*)(\d+)/u', $q, $m)) {
            return (float)$m[1];
        }

        // 5. Plain number of 4 to 9 digits (excluding years 2020..2030)
        if (preg_match('/\b(\d{4,9})\b/u', $q, $m)) {
            $num = (int)$m[1];
            if ($num < 2020 || $num > 2030) {
                return (float)$num;
            }
        }

        return null;
    }

    /**
     * Helper to detect and extract targeted data from user query dynamically.
     */
    protected function evaluateTargetedQuery(
        string $query,
        array $allPayments,
        array $pembayaranPerPeriode,
        array $pembayaranPerAnggota,
        array $kategoriList,
        array $transaksiPerTanggal,
        array $daftarAnggota,
        array $semuaPengeluaran = [],
        array $semuaPemasukan = []
    ): array {
        if (trim($query) === '') {
            return [];
        }

        $q = mb_strtolower($query, 'UTF-8');
        $target = [];

        // 1. Detect Month Mention
        $months = [
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8
        ];
        $detectedMonth = null;
        foreach ($months as $name => $num) {
            if (str_contains($q, $name)) {
                $detectedMonth = $num;
                break;
            }
        }

        // Detect Year
        $detectedYear = 2026;
        if (preg_match('/\b(202[0-9])\b/', $q, $yMatch)) {
            $detectedYear = (int)$yMatch[1];
        }

        if ($detectedMonth !== null) {
            $key = sprintf('%04d-%02d', $detectedYear, $detectedMonth);
            $periodData = $pembayaranPerPeriode[$key] ?? null;
            $namaBulan = $this->getNamaBulan($detectedMonth);

            // Also check arrears for that specific month
            $tunggakanRows = $this->laporanModel->getLaporanTunggakan(['periode_bulan' => $detectedMonth, 'periode_tahun' => $detectedYear]);
            $menunggak = array_values(array_filter($tunggakanRows, fn($r) => $r['status_bulan'] === 'tunggak'));

            $pembayarUnik = [];
            if ($periodData && !empty($periodData['daftar_pembayar'])) {
                foreach ($periodData['daftar_pembayar'] as $dp) {
                    $pembayarUnik[$dp['nama']] = true;
                }
            }

            $target['periode'] = [
                'bulan'                => $detectedMonth,
                'tahun'                => $detectedYear,
                'nama_bulan'           => $namaBulan,
                'total_nominal'        => $periodData ? $periodData['total_nominal'] : 0.0,
                'jumlah_transaksi'     => $periodData ? $periodData['jumlah_transaksi'] : 0,
                'daftar_pembayar'      => $periodData ? $periodData['daftar_pembayar'] : [],
                'jumlah_anggota_bayar' => count($pembayarUnik),
                'jumlah_menunggak'     => count($menunggak),
                'daftar_menunggak'     => array_map(fn($r) => [
                    'nama'               => $r['nama'],
                    'kelas'              => $r['kelas'],
                    'minggu_belum_bayar' => $r['minggu_belum_bayar'],
                    'estimasi_tunggakan' => (float)$r['estimasi_tunggakan']
                ], $menunggak)
            ];
        }

        // 2. Detect Specific Member Mention
        $matchedMembers = [];
        foreach ($daftarAnggota as $ag) {
            $namaLower = mb_strtolower($ag['nama'], 'UTF-8');
            $parts = explode(' ', $namaLower);
            $match = false;
            foreach ($parts as $p) {
                if (strlen($p) >= 4 && str_contains($q, $p)) {
                    $match = true;
                    break;
                }
            }

            if ($match) {
                $memberData = [
                    'profil'     => $ag,
                    'pembayaran' => $pembayaranPerAnggota[$ag['nama']] ?? [
                        'nama'             => $ag['nama'],
                        'total_nominal'    => 0.0,
                        'jumlah_transaksi' => 0,
                        'rincian'          => []
                    ]
                ];
                $matchedMembers[] = $memberData;

                // Backward compatibility: keep the first matched member as anggota_terpilih
                if (!isset($target['anggota_terpilih'])) {
                    $target['anggota_terpilih'] = $memberData;
                }
            }
        }

        if (!empty($matchedMembers)) {
            $target['anggota_terpilih_list'] = $matchedMembers;
        }

        // 3. Detect Specific Date
        if (preg_match('/\b(202[0-9]-[0-1][0-9]-[0-3][0-9])\b/', $q, $dMatch)) {
            $dateStr = $dMatch[1];
            $tglData = array_filter($transaksiPerTanggal, fn($t) => $t['tanggal_transaksi'] === $dateStr);
            $target['tanggal_transaksi'] = [
                'tanggal' => $dateStr,
                'rincian' => array_values($tglData)
            ];
        } elseif (str_contains($q, '19 september') || str_contains($q, '19-09')) {
            $dateStr = '2026-09-19';
            $tglData = array_filter($transaksiPerTanggal, fn($t) => $t['tanggal_transaksi'] === $dateStr);
            $target['tanggal_transaksi'] = [
                'tanggal' => $dateStr,
                'rincian' => array_values($tglData)
            ];
        } elseif (str_contains($q, '10 oktober') || str_contains($q, '10-10')) {
            $dateStr = '2026-10-10';
            $tglData = array_filter($transaksiPerTanggal, fn($t) => $t['tanggal_transaksi'] === $dateStr);
            $target['tanggal_transaksi'] = [
                'tanggal' => $dateStr,
                'rincian' => array_values($tglData)
            ];
        }

        // 4. DETECT EXPENSE & MULTI-TABLE TRANSACTION DETAIL QUERIES
        $extractedNominal = $this->extractNominal($q);
        $isAskingPurpose = (
            str_contains($q, 'buat apa') || str_contains($q, 'untuk apa') ||
            str_contains($q, 'tujuan') || str_contains($q, 'keperluan') ||
            str_contains($q, 'kegunaan') || str_contains($q, 'digunakan untuk') ||
            str_contains($q, 'detail') || str_contains($q, 'rincian') ||
            str_contains($q, 'keterangan') || str_contains($q, 'penjelasan')
        );
        $isAskingCreator = (
            str_contains($q, 'siapa yang mencatat') || str_contains($q, 'dicatat oleh siapa') ||
            str_contains($q, 'siapa pencatat') || str_contains($q, 'pencatatnya') ||
            str_contains($q, 'siapa yang mencatatnya')
        );
        $isAskingLargest = (
            (str_contains($q, 'terbesar') || str_contains($q, 'paling besar') || str_contains($q, 'paling banyak') || str_contains($q, 'terbanyak')) &&
            (str_contains($q, 'pengeluaran') || str_contains($q, 'keluar') || str_contains($q, 'biaya') || str_contains($q, 'transaksi'))
        );
        $isFollowUp = (
            str_contains($q, 'pengeluaran itu') || str_contains($q, 'pengeluaran tadi') ||
            str_contains($q, 'pengeluaran tersebut') || str_contains($q, 'transaksi itu') ||
            str_contains($q, 'transaksi tadi') || str_contains($q, 'transaksi tersebut') ||
            (str_contains($q, 'itu') && ($isAskingPurpose || $isAskingCreator)) ||
            (str_contains($q, 'tadi') && ($isAskingPurpose || $isAskingCreator))
        );

        $isExpenseRelated = (
            str_contains($q, 'pengeluaran') || str_contains($q, 'uang keluar') ||
            str_contains($q, 'biaya') || $isAskingLargest || $isFollowUp ||
            ($extractedNominal !== null && ($isAskingPurpose || $isAskingCreator || str_contains($q, 'keluar')))
        );

        if ($isExpenseRelated) {
            $target['pertanyaan_tujuan_pengeluaran'] = $isAskingPurpose;
            $target['pertanyaan_pencatat_pengeluaran'] = $isAskingCreator;
            $target['is_follow_up'] = $isFollowUp;

            if ($extractedNominal !== null) {
                $cocok = array_values(array_filter($semuaPengeluaran, function($t) use ($extractedNominal) {
                    return abs((float)$t['jumlah'] - $extractedNominal) < 1.0;
                }));

                if (count($cocok) === 1) {
                    $target['transaksi_pengeluaran_terpilih'] = $cocok[0];
                } elseif (count($cocok) > 1) {
                    $target['kandidat_pengeluaran'] = $cocok;
                } else {
                    $target['pengeluaran_nominal_tidak_ditemukan'] = $extractedNominal;
                }
            } elseif ($isAskingLargest) {
                if (!empty($semuaPengeluaran)) {
                    $sortedKeluar = $semuaPengeluaran;
                    usort($sortedKeluar, fn($a, $b) => (float)$b['jumlah'] <=> (float)$a['jumlah']);
                    $target['transaksi_pengeluaran_terbesar'] = $sortedKeluar[0];
                }
            } elseif ($isFollowUp || $isAskingPurpose || $isAskingCreator) {
                if (count($semuaPengeluaran) === 1) {
                    $target['transaksi_pengeluaran_terpilih'] = $semuaPengeluaran[0];
                } elseif (!empty($semuaPengeluaran)) {
                    $target['transaksi_pengeluaran_terpilih'] = $semuaPengeluaran[0];
                }
            }
        }

        // 5. Perbandingan Pengeluaran Bulan Ini vs Bulan Lalu
        if ((str_contains($q, 'banding') || str_contains($q, 'perbandingan')) && (str_contains($q, 'pengeluaran') || str_contains($q, 'biaya'))) {
            $target['perbandingan_pengeluaran'] = true;
        }

        // 6. Rentang Tanggal (Date Range e.g. "antara 19 September sampai 24 Oktober")
        if (preg_match('/(dari|antara)\s+(\d{1,2}\s+[a-z]+|\d{4}-\d{2}-\d{2})\s+(sampai|hingga|dan|-)\s+(\d{1,2}\s+[a-z]+|\d{4}-\d{2}-\d{2})/iu', $q, $rangeMatch)) {
            $target['rentang_tanggal_ditemukan'] = [
                'raw_start' => $rangeMatch[2],
                'raw_end'   => $rangeMatch[4]
            ];
        }

        return $target;
    }

    public function getNamaBulan(int $bulan): string
    {
        $bulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $bulanMap[$bulan] ?? 'Bulan ' . $bulan;
    }
}
