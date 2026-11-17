<?php

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_log';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id',
        'aktivitas',
        'waktu',
        'metadata'
    ];

    /**
     * Log an activity (static convenience method).
     *
     * @param int|null $userId  User performing the action (null for system actions)
     * @param string $aktivitas Description of activity
     * @param array $metadata   Additional data/context
     * @return int|string       Inserted log ID
     */
    public static function log(?int $userId, string $aktivitas, array $metadata = []): int|string
    {
        $instance = new static();
        return $instance->create([
            'user_id' => $userId,
            'aktivitas' => $aktivitas,
            'waktu' => date('Y-m-d H:i:s'),
            'metadata' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * Get recent audit logs with user info.
     *
     * @param int $limit  Number of records to return
     * @return array
     */
    public function getRecent(int $limit = 20): array
    {
        $stmt = static::db()->prepare("
            SELECT al.*, u.username
            FROM {$this->table} al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.waktu DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get audit logs for a specific user.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM {$this->table}
            WHERE user_id = :uid
            ORDER BY waktu DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
