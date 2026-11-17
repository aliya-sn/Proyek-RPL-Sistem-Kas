<?php

namespace App\Models;

use App\Core\Model;

class TransaksiKas extends Model
{
    protected string $table = 'transaksi_kas';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'kode_transaksi',
        'jenis', // 'masuk' or 'keluar'
        'kategori_id',
        'jumlah',
        'keterangan',
        'tanggal_transaksi',
        'created_by',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get total amount by transaction type.
     */
    public function getTotalByJenis(string $jenis): float
    {
        $stmt = static::db()->prepare("
            SELECT SUM(jumlah) as total 
            FROM {$this->table} 
            WHERE jenis = :jenis AND status = 'aktif'
        ");
        $stmt->execute(['jenis' => $jenis]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get recent transactions.
     */
    public function getRecentTransactions(int $limit = 5): array
    {
        $stmt = static::db()->prepare("
            SELECT t.*, k.nama_kategori, u.username as creator_name
            FROM {$this->table} t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.status = 'aktif'
            ORDER BY t.tanggal_transaksi DESC, t.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get monthly statistics for chart (grouped by month).
     */
    public function getMonthlyStats(int $year): array
    {
        $stmt = static::db()->prepare("
            SELECT 
                MONTH(tanggal_transaksi) as bulan,
                SUM(CASE WHEN jenis = 'masuk' THEN jumlah ELSE 0 END) as total_masuk,
                SUM(CASE WHEN jenis = 'keluar' THEN jumlah ELSE 0 END) as total_keluar
            FROM {$this->table}
            WHERE YEAR(tanggal_transaksi) = :yr AND status = 'aktif'
            GROUP BY MONTH(tanggal_transaksi)
            ORDER BY bulan ASC
        ");
        $stmt->execute(['yr' => $year]);
        return $stmt->fetchAll();
    }

    /**
     * Calculate current Saldo.
     */
    public function calculateSaldo(): float
    {
        $masuk = $this->getTotalByJenis('masuk');
        $keluar = $this->getTotalByJenis('keluar');
        return $masuk - $keluar;
    }

    /**
     * Get transaction with details.
     */
    public function getWithDetails(int $id): ?array
    {
        $stmt = static::db()->prepare("
            SELECT t.*, k.nama_kategori, u.username as creator_name
            FROM {$this->table} t
            LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.created_by
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get paginated transactions.
     */
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT t.*, k.nama_kategori, u.username as creator_name
                FROM {$this->table} t
                LEFT JOIN kategori_transaksi k ON k.id = t.kategori_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (t.kode_transaksi LIKE :search1 OR t.keterangan LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['jenis'])) {
            $sql .= " AND t.jenis = :jenis";
            $params['jenis'] = $filters['jenis'];
        }
        if (!empty($filters['kategori_id'])) {
            $sql .= " AND t.kategori_id = :kategori_id";
            $params['kategori_id'] = $filters['kategori_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY t.tanggal_transaksi DESC, t.id DESC LIMIT :limit OFFSET :offset";

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
     * Count filtered transactions.
     */
    public function countFiltered(array $filters): int
    {
        $sql = "SELECT COUNT(t.id) as total FROM {$this->table} t WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (t.kode_transaksi LIKE :search1 OR t.keterangan LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['jenis'])) {
            $sql .= " AND t.jenis = :jenis";
            $params['jenis'] = $filters['jenis'];
        }
        if (!empty($filters['kategori_id'])) {
            $sql .= " AND t.kategori_id = :kategori_id";
            $params['kategori_id'] = $filters['kategori_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }
}
