<?php

namespace App\Models;

use App\Core\Model;

class Anggota extends Model
{
    protected string $table = 'anggota';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id',
        'nomor_identitas',
        'nama',
        'kelas',
        'jenis_kelamin',
        'nomor_kontak',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Find anggota profile by user_id.
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /**
     * Find anggota by nomor identitas.
     */
    public function findByNomorIdentitas(string $nomor): ?array
    {
        return $this->findBy('nomor_identitas', $nomor);
    }

    /**
     * Get all active anggota.
     */
    public function getActive(): array
    {
        return $this->where(['status' => 'aktif'], 'nama ASC');
    }

    /**
     * Get anggota with user data (JOIN).
     */
    public function getWithUser(int $anggotaId): ?array
    {
        $stmt = static::db()->prepare("
            SELECT a.*, u.username, u.role as user_role, u.status as user_status
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $anggotaId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all anggota with their user account info.
     */
    public function getAllWithUser(string $orderBy = 'a.nama ASC'): array
    {
        $stmt = static::db()->query("
            SELECT a.*, u.username, u.status as user_status
            FROM {$this->table} a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY {$orderBy}
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get paginated and filtered data.
     */
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT a.*, u.username 
                FROM {$this->table} a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR a.nomor_identitas LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['kelas'])) {
            $sql .= " AND a.kelas = :kelas";
            $params['kelas'] = $filters['kelas'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY a.nama ASC LIMIT :limit OFFSET :offset";

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
     * Count total filtered data for pagination.
     */
    public function countFiltered(array $filters): int
    {
        $sql = "SELECT COUNT(a.id) as total FROM {$this->table} a WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR a.nomor_identitas LIKE :search2)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
        }
        if (!empty($filters['kelas'])) {
            $sql .= " AND a.kelas = :kelas";
            $params['kelas'] = $filters['kelas'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = :status";
            $params['status'] = $filters['status'];
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Check if nomor identitas already exists.
     */
    public function isNomorIdentitasExists(string $nomor, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(id) as total FROM {$this->table} WHERE nomor_identitas = :nomor";
        $params = ['nomor' => $nomor];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return ((int)($result['total'] ?? 0)) > 0;
    }
}
