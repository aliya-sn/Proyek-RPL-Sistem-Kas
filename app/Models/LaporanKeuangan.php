<?php

namespace App\Models;

use App\Core\Model;

/**
 * LaporanKeuangan
 * Provides aggregated query methods for all financial report types.
 * All methods accept a $filters array that may contain:
 *   - tanggal_mulai (Y-m-d)
 *   - tanggal_akhir (Y-m-d)
 *   - periode_bulan (int 1-12)
 *   - periode_tahun (int)
 *   - kategori_id (int)
 *   - anggota_id (int)
 */
class LaporanKeuangan extends Model
{
    protected string $table = 'transaksi_kas';

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build date/period WHERE clauses for transaksi_kas table alias 't'.
     */
    private function buildTransaksiDateClauses(array $filters): array
    {
        $clauses = ["t.status = 'aktif'"];
        $params  = [];

        if (!empty($filters['tanggal_mulai'])) {
            $clauses[] = 't.tanggal_transaksi >= :tgl_mulai';
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        }
        if (!empty($filters['tanggal_akhir'])) {
            $clauses[] = 't.tanggal_transaksi <= :tgl_akhir';
            $params['tgl_akhir'] = $filters['tanggal_akhir'];
        }
        if (!empty($filters['periode_bulan'])) {
            $clauses[] = 'MONTH(t.tanggal_transaksi) = :periode_bulan';
            $params['periode_bulan'] = (int)$filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $clauses[] = 'YEAR(t.tanggal_transaksi) = :periode_tahun';
            $params['periode_tahun'] = (int)$filters['periode_tahun'];
        }
        if (!empty($filters['kategori_id'])) {
            $clauses[] = 't.kategori_id = :kategori_id';
            $params['kategori_id'] = (int)$filters['kategori_id'];
        }

        return [$clauses, $params];
    }

    // =========================================================================
    // 1. LAPORAN PEMASUKAN
    // =========================================================================

    public function getLaporanPemasukan(array $filters): array
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $clauses[] = "t.jenis = 'masuk'";
        $where = implode(' AND ', $clauses);

        $sql = "
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah,
                   t.keterangan, k.nama_kategori, u.username AS creator_name
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE {$where}
            ORDER BY t.tanggal_transaksi ASC, t.id ASC
        ";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTotalPemasukan(array $filters): float
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $clauses[] = "t.jenis = 'masuk'";
        $where = implode(' AND ', $clauses);

        $stmt = static::db()->prepare("
            SELECT COALESCE(SUM(t.jumlah), 0) AS total
            FROM transaksi_kas t
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    // =========================================================================
    // 2. LAPORAN PENGELUARAN
    // =========================================================================

    public function getLaporanPengeluaran(array $filters): array
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $clauses[] = "t.jenis = 'keluar'";
        $where = implode(' AND ', $clauses);

        $sql = "
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jumlah,
                   t.keterangan, k.nama_kategori, u.username AS creator_name
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE {$where}
            ORDER BY t.tanggal_transaksi ASC, t.id ASC
        ";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTotalPengeluaran(array $filters): float
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $clauses[] = "t.jenis = 'keluar'";
        $where = implode(' AND ', $clauses);

        $stmt = static::db()->prepare("
            SELECT COALESCE(SUM(t.jumlah), 0) AS total
            FROM transaksi_kas t
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    // =========================================================================
    // 3. LAPORAN ARUS KAS
    // =========================================================================

    public function getLaporanArusKas(array $filters): array
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $where = implode(' AND ', $clauses);

        $sql = "
            SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.jenis,
                   t.jumlah, t.keterangan, k.nama_kategori, u.username AS creator_name
            FROM transaksi_kas t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE {$where}
            ORDER BY t.tanggal_transaksi ASC, t.id ASC
        ";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get cash flow summary (grouped by date for running balance).
     */
    public function getArusKasSummary(array $filters): array
    {
        [$clauses, $params] = $this->buildTransaksiDateClauses($filters);
        $where = implode(' AND ', $clauses);

        $stmt = static::db()->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN t.jenis = 'masuk' THEN t.jumlah ELSE 0 END), 0) AS total_masuk,
                COALESCE(SUM(CASE WHEN t.jenis = 'keluar' THEN t.jumlah ELSE 0 END), 0) AS total_keluar
            FROM transaksi_kas t
            WHERE {$where}
        ");
        $stmt->execute($params);
        return $stmt->fetch() ?: ['total_masuk' => 0, 'total_keluar' => 0];
    }

    /**
     * Get saldo before the filter period (for opening balance in cash flow).
     */
    public function getSaldoSebelumPeriode(array $filters): float
    {
        $params = [];
        $clauses = ["status = 'aktif'"];

        if (!empty($filters['tanggal_mulai'])) {
            $clauses[] = 'tanggal_transaksi < :tgl_mulai';
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        } elseif (!empty($filters['periode_bulan']) && !empty($filters['periode_tahun'])) {
            $clauses[] = "CONCAT(YEAR(tanggal_transaksi), '-', LPAD(MONTH(tanggal_transaksi),2,'0')) < :period_key";
            $params['period_key'] = $filters['periode_tahun'] . '-' . str_pad($filters['periode_bulan'], 2, '0', STR_PAD_LEFT);
        } elseif (!empty($filters['periode_tahun'])) {
            $clauses[] = 'YEAR(tanggal_transaksi) < :yr';
            $params['yr'] = (int)$filters['periode_tahun'];
        } else {
            return 0.0;
        }

        $where = implode(' AND ', $clauses);
        $stmt  = static::db()->prepare("
            SELECT COALESCE(SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE -jumlah END), 0) AS saldo
            FROM transaksi_kas
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)($stmt->fetch()['saldo'] ?? 0);
    }

    // =========================================================================
    // 4. LAPORAN PEMBAYARAN ANGGOTA
    // =========================================================================

    public function getLaporanPembayaran(array $filters): array
    {
        $clauses = ["p.status = 'lunas'"];
        $params  = [];

        if (!empty($filters['tanggal_mulai'])) {
            $clauses[] = 'p.tanggal_bayar >= :tgl_mulai';
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        }
        if (!empty($filters['tanggal_akhir'])) {
            $clauses[] = 'p.tanggal_bayar <= :tgl_akhir';
            $params['tgl_akhir'] = $filters['tanggal_akhir'];
        }
        if (!empty($filters['periode_bulan'])) {
            $clauses[] = 'p.periode_bulan = :periode_bulan';
            $params['periode_bulan'] = (int)$filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $clauses[] = 'p.periode_tahun = :periode_tahun';
            $params['periode_tahun'] = (int)$filters['periode_tahun'];
        }
        if (!empty($filters['anggota_id'])) {
            $clauses[] = 'p.anggota_id = :anggota_id';
            $params['anggota_id'] = (int)$filters['anggota_id'];
        }

        $where = implode(' AND ', $clauses);

        $sql = "
            SELECT p.id, p.tanggal_bayar, p.periode_minggu, p.periode_bulan, p.periode_tahun,
                   p.nominal, p.status, a.nama AS anggota_nama, a.nomor_identitas, a.kelas,
                   u.username AS petugas_nama
            FROM pembayaran_kas p
            JOIN anggota a ON a.id = p.anggota_id
            LEFT JOIN users u ON u.id = p.petugas_id
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE {$where} AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ORDER BY p.tanggal_bayar ASC, a.nama ASC
        ";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTotalPembayaran(array $filters): float
    {
        $clauses = ["p.status = 'lunas'"];
        $params  = [];

        if (!empty($filters['tanggal_mulai'])) {
            $clauses[] = 'p.tanggal_bayar >= :tgl_mulai';
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        }
        if (!empty($filters['tanggal_akhir'])) {
            $clauses[] = 'p.tanggal_bayar <= :tgl_akhir';
            $params['tgl_akhir'] = $filters['tanggal_akhir'];
        }
        if (!empty($filters['periode_bulan'])) {
            $clauses[] = 'p.periode_bulan = :periode_bulan';
            $params['periode_bulan'] = (int)$filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $clauses[] = 'p.periode_tahun = :periode_tahun';
            $params['periode_tahun'] = (int)$filters['periode_tahun'];
        }
        if (!empty($filters['anggota_id'])) {
            $clauses[] = 'p.anggota_id = :anggota_id';
            $params['anggota_id'] = (int)$filters['anggota_id'];
        }

        $where = implode(' AND ', $clauses);
        $stmt  = static::db()->prepare("
            SELECT COALESCE(SUM(p.nominal), 0) AS total
            FROM pembayaran_kas p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE {$where} AND (p.transaksi_id IS NULL OR t.status = 'aktif')
        ");
        $stmt->execute($params);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    // =========================================================================
    // 5. LAPORAN TUNGGAKAN
    // =========================================================================

    /**
     * Get list of anggota aktif and their arrears.
     * Arrears = expected dues for the period minus valid non-void payments.
     *
     * Handles:
     * - Fully paid members (lunas, sisa tunggakan = 0, minggu_terbayar = 4)
     * - Partial payment (status = belum_lunas, reduces arrears accordingly)
     * - Unpaid members (due: menunggak, future/not yet due: not menunggak)
     * - Voided / cancelled transactions (excluded from valid payments)
     * - Total weeks = 4 (minggu_terbayar + minggu_belum_bayar === 4)
     */
    public function getLaporanTunggakan(array $filters): array
    {
        $bulan = !empty($filters['periode_bulan']) ? (int)$filters['periode_bulan'] : (int)date('n');
        $tahun = !empty($filters['periode_tahun']) ? (int)$filters['periode_tahun'] : (int)date('Y');
        $anggotaId = !empty($filters['anggota_id']) ? (int)$filters['anggota_id'] : null;

        $currBulan = (int)date('n');
        $currTahun = (int)date('Y');
        $isFuturePeriod = ($tahun > $currTahun) || ($tahun === $currTahun && $bulan > $currBulan);

        // Fetch active anggota
        $anggotaWhere = "a.status = 'aktif'";
        $anggotaParams = [];
        if ($anggotaId !== null) {
            $anggotaWhere .= " AND a.id = :anggota_id";
            $anggotaParams['anggota_id'] = $anggotaId;
        }

        $anggotaList = static::db()->prepare("
            SELECT a.id, a.nama, a.nomor_identitas, a.kelas, a.created_at
            FROM anggota a
            WHERE {$anggotaWhere}
            ORDER BY a.nama ASC
        ");
        $anggotaList->execute($anggotaParams);
        $anggotaRows = $anggotaList->fetchAll();

        // Standard monthly nominal fallback from recent valid payments
        $stmtStd = static::db()->query("
            SELECT p.nominal FROM pembayaran_kas p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ORDER BY p.id DESC LIMIT 1
        ");
        $refGlobal = $stmtStd ? $stmtStd->fetch() : null;
        $globalStandardNominal = $refGlobal ? (float)$refGlobal['nominal'] : 20000.0;
        if ($globalStandardNominal <= 0) {
            $globalStandardNominal = 20000.0;
        }

        $result = [];

        foreach ($anggotaRows as $anggota) {
            // Fetch all valid non-void payment records for this anggota and period
            $stmtPaid = static::db()->prepare("
                SELECT p.id, p.periode_minggu, p.nominal, p.status, p.tanggal_bayar
                FROM pembayaran_kas p
                LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
                WHERE p.anggota_id = :aid
                  AND p.periode_bulan = :bulan
                  AND p.periode_tahun = :tahun
                  AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ");
            $stmtPaid->execute(['aid' => $anggota['id'], 'bulan' => $bulan, 'tahun' => $tahun]);
            $payments = $stmtPaid->fetchAll();

            // Find member's specific standard nominal from any historical lunas payment
            $stmtMemberRef = static::db()->prepare("
                SELECT p.nominal FROM pembayaran_kas p
                LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
                WHERE p.anggota_id = :aid AND p.status = 'lunas' AND (p.transaksi_id IS NULL OR t.status = 'aktif')
                ORDER BY p.id DESC LIMIT 1
            ");
            $stmtMemberRef->execute(['aid' => $anggota['id']]);
            $memberRef = $stmtMemberRef->fetch();
            $standardNominal = $memberRef ? (float)$memberRef['nominal'] : $globalStandardNominal;
            if ($standardNominal <= 0) {
                $standardNominal = $globalStandardNominal;
            }

            // Check if member joined after the period end
            $memberJoinedTimestamp = !empty($anggota['created_at']) ? strtotime($anggota['created_at']) : 0;
            $periodEndTimestamp = strtotime(sprintf('%04d-%02d-01 +1 month -1 second', $tahun, $bulan));
            $isNotYetDue = $isFuturePeriod || ($memberJoinedTimestamp > $periodEndTimestamp);

            $hasLunasPayment = false;
            $hasBelumLunasPayment = false;
            $distinctWeeksPaid = [];
            $totalNominalPaid = 0.0;

            foreach ($payments as $pmt) {
                if ($pmt['status'] === 'lunas') {
                    $hasLunasPayment = true;
                    $distinctWeeksPaid[(int)$pmt['periode_minggu']] = true;
                    $totalNominalPaid += (float)$pmt['nominal'];
                } elseif ($pmt['status'] === 'belum_lunas') {
                    $hasBelumLunasPayment = true;
                    $totalNominalPaid += (float)$pmt['nominal'];
                }
            }

            if ($hasLunasPayment) {
                // If member paid full monthly payment (recorded with minggu=1 default) or all 4 weeks
                if (count($distinctWeeksPaid) >= 4 || count($payments) === 1 || $totalNominalPaid >= $standardNominal) {
                    $paidWeeks = 4;
                    $unpaidWeeks = 0;
                    $nominalPerPayment = $standardNominal / 4;
                    $estimasiTunggakan = 0.0;
                    $statusBulan = 'lunas';
                } else {
                    // Weekly breakdown with some weeks paid
                    $paidWeeks = count($distinctWeeksPaid);
                    $unpaidWeeks = max(0, 4 - $paidWeeks);
                    $nominalPerPayment = $standardNominal / 4;
                    $estimasiTunggakan = $unpaidWeeks * $nominalPerPayment;
                    $statusBulan = $unpaidWeeks === 0 ? 'lunas' : 'tunggak';
                }
            } elseif ($hasBelumLunasPayment) {
                // Partial payment exists (status = 'belum_lunas')
                $nominalPerPayment = $standardNominal / 4;
                $estimasiTunggakan = max(0.0, $standardNominal - $totalNominalPaid);
                $paidWeeks = min(3, max(1, (int)round(($totalNominalPaid / max(1.0, $standardNominal)) * 4)));
                $unpaidWeeks = 4 - $paidWeeks;
                $statusBulan = 'tunggak';
            } else {
                // No payment recorded
                if ($isNotYetDue) {
                    // Tagihan belum jatuh tempo: tidak dianggap menunggak
                    $paidWeeks = 4;
                    $unpaidWeeks = 0;
                    $nominalPerPayment = $standardNominal / 4;
                    $estimasiTunggakan = 0.0;
                    $statusBulan = 'lunas';
                } else {
                    // Belum bayar dan sudah jatuh tempo: tetap menunggak
                    $paidWeeks = 0;
                    $unpaidWeeks = 4;
                    $nominalPerPayment = $standardNominal / 4;
                    $estimasiTunggakan = $standardNominal;
                    $statusBulan = 'tunggak';
                }
            }

            $result[] = [
                'anggota_id'          => $anggota['id'],
                'nama'                => $anggota['nama'],
                'nomor_identitas'     => $anggota['nomor_identitas'],
                'kelas'               => $anggota['kelas'],
                'minggu_terbayar'     => $paidWeeks,
                'minggu_belum_bayar'  => $unpaidWeeks,
                'nominal_per_minggu'  => $nominalPerPayment,
                'estimasi_tunggakan'  => $estimasiTunggakan,
                'status_bulan'        => $statusBulan,
            ];
        }

        return $result;
    }

    /**
     * Get total estimated arrears from tunggakan report rows.
     */
    public function getTotalTunggakan(array $tunggakanRows): float
    {
        return array_sum(array_column($tunggakanRows, 'estimasi_tunggakan'));
    }
}
