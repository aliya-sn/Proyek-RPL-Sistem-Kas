<?php

namespace App\Models;

use App\Core\Model;

class PembayaranKas extends Model
{
    protected string $table = 'pembayaran_kas';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'anggota_id',
        'transaksi_id',
        'periode_minggu',
        'periode_bulan',
        'periode_tahun',
        'nominal',
        'status',
        'tanggal_bayar',
        'petugas_id',
        'created_at'
    ];

    /**
     * Get count of anggota who have paid for the current period (month/year).
     */
    public function getCountSudahBayarPeriodeIni(int $bulan, int $tahun): int
    {
        $stmt = static::db()->prepare("
            SELECT COUNT(DISTINCT p.anggota_id) as total 
            FROM {$this->table} p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.periode_bulan = :bln 
              AND p.periode_tahun = :thn 
              AND p.status = 'lunas'
              AND (p.transaksi_id IS NULL OR t.status = 'aktif')
        ");
        $stmt->execute(['bln' => $bulan, 'thn' => $tahun]);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get payment history for a specific anggota.
     */
    public function getRiwayatByAnggota(int $anggotaId, int $limit = 5): array
    {
        $stmt = static::db()->prepare("
            SELECT p.* 
            FROM {$this->table} p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.anggota_id = :id
              AND (p.transaksi_id IS NULL OR t.status = 'aktif')
            ORDER BY p.tanggal_bayar DESC, p.id DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':id', $anggotaId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get total amount paid by a specific anggota.
     */
    public function getTotalPembayaranByAnggota(int $anggotaId): float
    {
        $stmt = static::db()->prepare("
            SELECT COALESCE(SUM(p.nominal), 0) as total 
            FROM {$this->table} p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.anggota_id = :id 
              AND p.status = 'lunas'
              AND (p.transaksi_id IS NULL OR t.status = 'aktif')
        ");
        $stmt->execute(['id' => $anggotaId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Check if a specific anggota has paid for a period (by week or month).
     */
    public function cekStatusBayarPeriode(int $anggotaId, int $minggu, int $bulan, int $tahun): bool
    {
        $stmt = static::db()->prepare("
            SELECT COUNT(*) as total 
            FROM {$this->table} p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.anggota_id = :id 
              AND p.periode_minggu = :mgg 
              AND p.periode_bulan = :bln 
              AND p.periode_tahun = :thn 
              AND p.status = 'lunas'
              AND (p.transaksi_id IS NULL OR t.status = 'aktif')
        ");
        $stmt->execute(['id' => $anggotaId, 'mgg' => $minggu, 'bln' => $bulan, 'thn' => $tahun]);
        $result = $stmt->fetch();
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Check if a specific anggota has paid fully for a given month and year.
     */
    public function cekStatusBayarBulanIni(int $anggotaId, int $bulan, int $tahun): bool
    {
        $stmt = static::db()->prepare("
            SELECT COUNT(*) as total 
            FROM {$this->table} p
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.anggota_id = :id 
              AND p.periode_bulan = :bln 
              AND p.periode_tahun = :thn 
              AND p.status = 'lunas'
              AND (p.transaksi_id IS NULL OR t.status = 'aktif')
        ");
        $stmt->execute(['id' => $anggotaId, 'bln' => $bulan, 'thn' => $tahun]);
        $result = $stmt->fetch();
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Get paginated list of pembayaran with details.
     */
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT p.*, a.nama as anggota_nama, a.nomor_identitas, a.kelas, u.username as petugas_nama, t.kode_transaksi 
                FROM {$this->table} p
                JOIN anggota a ON a.id = p.anggota_id
                LEFT JOIN users u ON u.id = p.petugas_id
                LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR a.nomor_identitas LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['anggota_id'])) {
            $sql .= " AND p.anggota_id = :anggota_id";
            $params['anggota_id'] = $filters['anggota_id'];
        }
        if (!empty($filters['periode_bulan'])) {
            $sql .= " AND p.periode_bulan = :periode_bulan";
            $params['periode_bulan'] = $filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $sql .= " AND p.periode_tahun = :periode_tahun";
            $params['periode_tahun'] = $filters['periode_tahun'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY p.tanggal_bayar DESC, p.id DESC LIMIT :limit OFFSET :offset";

        $stmt = static::db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(":$key", $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Count filtered pembayaran.
     */
    public function countFiltered(array $filters): int
    {
        $sql = "SELECT COUNT(p.id) as total 
                FROM {$this->table} p
                JOIN anggota a ON a.id = p.anggota_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR a.nomor_identitas LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['anggota_id'])) {
            $sql .= " AND p.anggota_id = :anggota_id";
            $params['anggota_id'] = $filters['anggota_id'];
        }
        if (!empty($filters['periode_bulan'])) {
            $sql .= " AND p.periode_bulan = :periode_bulan";
            $params['periode_bulan'] = $filters['periode_bulan'];
        }
        if (!empty($filters['periode_tahun'])) {
            $sql .= " AND p.periode_tahun = :periode_tahun";
            $params['periode_tahun'] = $filters['periode_tahun'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get single record with details.
     */
    public function getWithDetails(int $id): ?array
    {
        $stmt = static::db()->prepare("
            SELECT p.*, a.nama as anggota_nama, a.nomor_identitas, a.kelas, u.username as petugas_nama, t.kode_transaksi 
            FROM {$this->table} p
            JOIN anggota a ON a.id = p.anggota_id
            LEFT JOIN users u ON u.id = p.petugas_id
            LEFT JOIN transaksi_kas t ON t.id = p.transaksi_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
