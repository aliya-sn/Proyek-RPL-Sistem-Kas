<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Models\LaporanKeuangan;
use App\Models\KategoriTransaksi;
use App\Models\Anggota;

/**
 * LaporanKeuanganController
 * Super User only. Handles all 5 financial report types + export (CSV/Print).
 */
class LaporanKeuanganController extends BaseController
{
    protected LaporanKeuangan    $laporanModel;
    protected KategoriTransaksi  $kategoriModel;
    protected Anggota            $anggotaModel;

    public function __construct()
    {
        $this->laporanModel  = new LaporanKeuangan();
        $this->kategoriModel = new KategoriTransaksi();
        $this->anggotaModel  = new Anggota();
    }

    // =========================================================================
    // INDEX — pilih jenis laporan
    // =========================================================================

    public function index(Request $request): void
    {
        $this->render('admin/laporan/index', [
            'pageTitle' => 'Laporan Keuangan - ' . config('app.name'),
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Parse common filter inputs from request.
     */
    private function parseFilters(Request $request): array
    {
        return [
            'tanggal_mulai'  => $request->input('tanggal_mulai', ''),
            'tanggal_akhir'  => $request->input('tanggal_akhir', ''),
            'periode_bulan'  => $request->input('periode_bulan', ''),
            'periode_tahun'  => $request->input('periode_tahun', ''),
            'kategori_id'    => $request->input('kategori_id', ''),
            'anggota_id'     => $request->input('anggota_id', ''),
        ];
    }

    /**
     * Build human-readable period label for display.
     */
    private function buildPeriodeLabel(array $filters): string
    {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $parts = [];
        if (!empty($filters['tanggal_mulai'])) {
            $parts[] = 'Mulai: ' . date('d/m/Y', strtotime($filters['tanggal_mulai']));
        }
        if (!empty($filters['tanggal_akhir'])) {
            $parts[] = 'Akhir: ' . date('d/m/Y', strtotime($filters['tanggal_akhir']));
        }
        if (!empty($filters['periode_bulan'])) {
            $parts[] = $namaBulan[(int)$filters['periode_bulan']] ?? $filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $parts[] = $filters['periode_tahun'];
        }

        return empty($parts) ? 'Semua Periode' : implode(' ', $parts);
    }

    // =========================================================================
    // 1. PEMASUKAN
    // =========================================================================

    public function pemasukan(Request $request): void
    {
        $filters      = $this->parseFilters($request);
        $rows         = $this->laporanModel->getLaporanPemasukan($filters);
        $totalPemasukan = $this->laporanModel->getTotalPemasukan($filters);
        $allKategori  = array_merge(
            $this->kategoriModel->getByJenis('masuk'),
            $this->kategoriModel->getByJenis('keluar')
        );

        $periodeLabel = $this->buildPeriodeLabel($filters);

        if ($request->input('export') === 'csv') {
            $this->exportCsv(
                ['No', 'Tanggal', 'Kode', 'Kategori', 'Keterangan', 'Jumlah (Rp)'],
                array_map(fn($i, $r) => [
                    $i + 1,
                    $r['tanggal_transaksi'],
                    $r['kode_transaksi'],
                    $r['nama_kategori'],
                    $r['keterangan'],
                    $r['jumlah'],
                ], array_keys($rows), $rows),
                "laporan-pemasukan-" . date('Ymd')
            );
            return;
        }

        $this->render('admin/laporan/pemasukan', [
            'pageTitle'      => 'Laporan Pemasukan - ' . config('app.name'),
            'rows'           => $rows,
            'totalPemasukan' => $totalPemasukan,
            'filters'        => $filters,
            'allKategori'    => $allKategori,
            'periodeLabel'   => $periodeLabel,
        ]);
    }

    // =========================================================================
    // 2. PENGELUARAN
    // =========================================================================

    public function pengeluaran(Request $request): void
    {
        $filters       = $this->parseFilters($request);
        $rows          = $this->laporanModel->getLaporanPengeluaran($filters);
        $totalPengeluaran = $this->laporanModel->getTotalPengeluaran($filters);
        $allKategori   = array_merge(
            $this->kategoriModel->getByJenis('masuk'),
            $this->kategoriModel->getByJenis('keluar')
        );
        $periodeLabel  = $this->buildPeriodeLabel($filters);

        if ($request->input('export') === 'csv') {
            $this->exportCsv(
                ['No', 'Tanggal', 'Kode', 'Kategori', 'Keterangan', 'Jumlah (Rp)'],
                array_map(fn($i, $r) => [
                    $i + 1,
                    $r['tanggal_transaksi'],
                    $r['kode_transaksi'],
                    $r['nama_kategori'],
                    $r['keterangan'],
                    $r['jumlah'],
                ], array_keys($rows), $rows),
                "laporan-pengeluaran-" . date('Ymd')
            );
            return;
        }

        $this->render('admin/laporan/pengeluaran', [
            'pageTitle'        => 'Laporan Pengeluaran - ' . config('app.name'),
            'rows'             => $rows,
            'totalPengeluaran' => $totalPengeluaran,
            'filters'          => $filters,
            'allKategori'      => $allKategori,
            'periodeLabel'     => $periodeLabel,
        ]);
    }

    // =========================================================================
    // 3. ARUS KAS
    // =========================================================================

    public function arusKas(Request $request): void
    {
        $filters       = $this->parseFilters($request);
        $rows          = $this->laporanModel->getLaporanArusKas($filters);
        $summary       = $this->laporanModel->getArusKasSummary($filters);
        $saldoAwal     = $this->laporanModel->getSaldoSebelumPeriode($filters);
        $saldoAkhir    = $saldoAwal + $summary['total_masuk'] - $summary['total_keluar'];
        $periodeLabel  = $this->buildPeriodeLabel($filters);

        if ($request->input('export') === 'csv') {
            $this->exportCsv(
                ['No', 'Tanggal', 'Kode', 'Jenis', 'Kategori', 'Keterangan', 'Masuk (Rp)', 'Keluar (Rp)'],
                array_map(fn($i, $r) => [
                    $i + 1,
                    $r['tanggal_transaksi'],
                    $r['kode_transaksi'],
                    ucfirst($r['jenis']),
                    $r['nama_kategori'],
                    $r['keterangan'],
                    $r['jenis'] === 'masuk' ? $r['jumlah'] : 0,
                    $r['jenis'] === 'keluar' ? $r['jumlah'] : 0,
                ], array_keys($rows), $rows),
                "laporan-arus-kas-" . date('Ymd')
            );
            return;
        }

        $this->render('admin/laporan/arus_kas', [
            'pageTitle'    => 'Laporan Arus Kas - ' . config('app.name'),
            'rows'         => $rows,
            'summary'      => $summary,
            'saldoAwal'    => $saldoAwal,
            'saldoAkhir'   => $saldoAkhir,
            'filters'      => $filters,
            'periodeLabel' => $periodeLabel,
        ]);
    }

    // =========================================================================
    // 4. PEMBAYARAN ANGGOTA
    // =========================================================================

    public function pembayaran(Request $request): void
    {
        $filters       = $this->parseFilters($request);
        $rows          = $this->laporanModel->getLaporanPembayaran($filters);
        $totalPembayaran = $this->laporanModel->getTotalPembayaran($filters);
        $allAnggota    = $this->anggotaModel->getActive();
        $periodeLabel  = $this->buildPeriodeLabel($filters);

        if ($request->input('export') === 'csv') {
            $this->exportCsv(
                ['No', 'Tanggal Bayar', 'Nama Anggota', 'NIS', 'Kelas', 'Periode Minggu', 'Periode Bulan', 'Periode Tahun', 'Nominal (Rp)', 'Petugas'],
                array_map(fn($i, $r) => [
                    $i + 1,
                    $r['tanggal_bayar'],
                    $r['anggota_nama'],
                    $r['nomor_identitas'],
                    $r['kelas'],
                    $r['periode_minggu'],
                    $r['periode_bulan'],
                    $r['periode_tahun'],
                    $r['nominal'],
                    $r['petugas_nama'],
                ], array_keys($rows), $rows),
                "laporan-pembayaran-" . date('Ymd')
            );
            return;
        }

        $this->render('admin/laporan/pembayaran', [
            'pageTitle'       => 'Laporan Pembayaran Anggota - ' . config('app.name'),
            'rows'            => $rows,
            'totalPembayaran' => $totalPembayaran,
            'filters'         => $filters,
            'allAnggota'      => $allAnggota,
            'periodeLabel'    => $periodeLabel,
        ]);
    }

    // =========================================================================
    // 5. TUNGGAKAN
    // =========================================================================

    public function tunggakan(Request $request): void
    {
        $filters       = $this->parseFilters($request);
        $rows          = $this->laporanModel->getLaporanTunggakan($filters);
        $totalTunggakan = $this->laporanModel->getTotalTunggakan($rows);
        $allAnggota    = $this->anggotaModel->getActive();
        $periodeLabel  = $this->buildPeriodeLabel($filters);

        $bulan = !empty($filters['periode_bulan']) ? (int)$filters['periode_bulan'] : (int)date('n');
        $tahun = !empty($filters['periode_tahun']) ? (int)$filters['periode_tahun'] : (int)date('Y');

        if ($request->input('export') === 'csv') {
            $this->exportCsv(
                ['No', 'Nama Anggota', 'NIS', 'Kelas', 'Minggu Terbayar', 'Minggu Belum Bayar', 'Nominal/Minggu (Rp)', 'Estimasi Tunggakan (Rp)', 'Status'],
                array_map(fn($i, $r) => [
                    $i + 1,
                    $r['nama'],
                    $r['nomor_identitas'],
                    $r['kelas'],
                    $r['minggu_terbayar'],
                    $r['minggu_belum_bayar'],
                    $r['nominal_per_minggu'],
                    $r['estimasi_tunggakan'],
                    ucfirst($r['status_bulan']),
                ], array_keys($rows), $rows),
                "laporan-tunggakan-" . date('Ymd')
            );
            return;
        }

        $this->render('admin/laporan/tunggakan', [
            'pageTitle'      => 'Laporan Tunggakan - ' . config('app.name'),
            'rows'           => $rows,
            'totalTunggakan' => $totalTunggakan,
            'filters'        => $filters,
            'allAnggota'     => $allAnggota,
            'periodeLabel'   => $periodeLabel,
            'bulanFilter'    => $bulan,
            'tahunFilter'    => $tahun,
        ]);
    }

    // =========================================================================
    // EXPORT — CSV (browser-native, no library required)
    // =========================================================================

    /**
     * Stream a CSV file to the browser.
     * Uses only PHP's built-in output; no external library needed.
     */
    private function exportCsv(array $headers, array $rows, string $filename): void
    {
        // Clean any output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM for Excel UTF-8 recognition
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Write headers
        fputcsv($out, $headers, ';');

        // Write data rows
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }
}
