<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'username',
        'nama',
        'password',
        'role',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Find user by username.
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM {$this->table} 
            WHERE username = :username 
            LIMIT 1
        ");
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new user with hashed password and normalized status.
     */
    public function register(array $data): int|string
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (isset($data['status'])) {
            $data['status'] = in_array(strtolower((string)$data['status']), ['aktif', 'active'], true) ? 'active' : 'inactive';
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * Update an existing user with normalized status.
     */
    public function update(int|string $id, array $data): bool
    {
        if (isset($data['status'])) {
            $data['status'] = in_array(strtolower((string)$data['status']), ['aktif', 'active'], true) ? 'active' : 'inactive';
        }

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return parent::update($id, $data);
    }

    /**
     * Check if username already exists.
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE username = :username";
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Get users by role.
     */
    public function getByRole(string $role): array
    {
        return $this->where(['role' => $role], 'username ASC');
    }
}
