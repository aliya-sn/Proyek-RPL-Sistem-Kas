<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use Exception;

/**
 * AiConfiguration Model
 * Manages database records for AI API Keys and Model configurations.
 * Strictly enforces atomic updates so that only one active record exists per provider.
 */
class AiConfiguration extends Model
{
    protected string $table = 'ai_configurations';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'provider',
        'encrypted_api_key',
        'active_model',
        'is_active',
        'updated_by'
    ];

    /**
     * Get the currently active configuration for a given provider.
     *
     * @param string $provider
     * @return array|null
     */
    public function getActive(string $provider = 'gemini'): ?array
    {
        $stmt = static::db()->prepare("
            SELECT ac.*, u.username as updated_by_username, u.nama as updated_by_name
            FROM {$this->table} ac
            LEFT JOIN users u ON u.id = ac.updated_by
            WHERE ac.provider = :provider AND ac.is_active = 1
            ORDER BY ac.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute(['provider' => $provider]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get the globally active AI configuration across any provider.
     *
     * @return array|null
     */
    public function getGlobalActive(): ?array
    {
        $stmt = static::db()->query("
            SELECT ac.*, u.username as updated_by_username, u.nama as updated_by_name
            FROM {$this->table} ac
            LEFT JOIN users u ON u.id = ac.updated_by
            WHERE ac.is_active = 1
            ORDER BY ac.updated_at DESC
            LIMIT 1
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return $row ?: null;
    }

    /**
     * Get the most recent configuration record for a specific provider (active or historical).
     *
     * @param string $provider
     * @return array|null
     */
    public function getLatestByProvider(string $provider): ?array
    {
        $stmt = static::db()->prepare("
            SELECT ac.*, u.username as updated_by_username, u.nama as updated_by_name
            FROM {$this->table} ac
            LEFT JOIN users u ON u.id = ac.updated_by
            WHERE ac.provider = :provider
            ORDER BY ac.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute(['provider' => $provider]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Atomically activate a new configuration.
     * Sets prior configurations across all providers to inactive and creates the new active record.
     *
     * @param string $provider
     * @param string $encryptedApiKey
     * @param string $activeModel
     * @param int|null $updatedBy
     * @return array The newly created active record
     * @throws Exception
     */
    public function activate(string $provider, string $encryptedApiKey, string $activeModel, ?int $updatedBy): array
    {
        $db = static::db();
        $db->beginTransaction();

        try {
            // 1. Deactivate existing active configuration(s) across ANY provider
            $deactivateStmt = $db->prepare("
                UPDATE {$this->table}
                SET is_active = 0
                WHERE is_active = 1
            ");
            $deactivateStmt->execute();

            // 2. Insert the new active configuration record
            $insertStmt = $db->prepare("
                INSERT INTO {$this->table} (provider, encrypted_api_key, active_model, is_active, updated_by, created_at, updated_at)
                VALUES (:provider, :encrypted_api_key, :active_model, 1, :updated_by, NOW(), NOW())
            ");
            $insertStmt->execute([
                'provider'          => $provider,
                'encrypted_api_key' => $encryptedApiKey,
                'active_model'      => $activeModel,
                'updated_by'        => $updatedBy,
            ]);

            $newId = (int)$db->lastInsertId();

            $db->commit();

            return $this->find($newId) ?? [
                'id'           => $newId,
                'provider'     => $provider,
                'active_model' => $activeModel,
                'is_active'    => 1,
                'updated_by'   => $updatedBy,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Gagal menyimpan dan mengaktifkan konfigurasi AI: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get recent configuration history for audit and transparency.
     *
     * @param string|null $provider
     * @param int $limit
     * @return array
     */
    public function getHistory(?string $provider = null, int $limit = 10): array
    {
        $sql = "
            SELECT ac.id, ac.provider, ac.active_model, ac.is_active, ac.created_at, ac.updated_at,
                   u.username as updated_by_username, u.nama as updated_by_name
            FROM {$this->table} ac
            LEFT JOIN users u ON u.id = ac.updated_by
        ";
        if (!empty($provider)) {
            $sql .= " WHERE ac.provider = :provider";
        }
        $sql .= " ORDER BY ac.updated_at DESC LIMIT :lim";

        $stmt = static::db()->prepare($sql);
        if (!empty($provider)) {
            $stmt->bindValue(':provider', $provider);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deactivate all active configurations for a provider (explicit reset by admin).
     *
     * @param string $provider
     * @param int|null $updatedBy
     * @return bool
     */
    public function deactivateAll(string $provider = 'gemini', ?int $updatedBy = null): bool
    {
        $db = static::db();
        $stmt = $db->prepare("
            UPDATE {$this->table}
            SET is_active = 0, updated_at = NOW(), updated_by = :updated_by
            WHERE provider = :provider AND is_active = 1
        ");
        return $stmt->execute([
            'provider'   => $provider,
            'updated_by' => $updatedBy
        ]);
    }

    /**
     * Check if this provider has historical records in database,
     * but currently has NO active configuration (meaning explicitly deactivated).
     *
     * @param string $provider
     * @return bool
     */
    public function isExplicitlyDeactivated(string $provider = 'gemini'): bool
    {
        try {
            $db = static::db();
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_rows, SUM(is_active) as active_count
                FROM {$this->table}
                WHERE provider = :provider
            ");
            $stmt->execute(['provider' => $provider]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && (int)$row['total_rows'] > 0 && (int)$row['active_count'] === 0) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Check if this provider has any historical records in database.
     *
     * @param string $provider
     * @return bool
     */
    public function hasAnyRecords(string $provider = 'gemini'): bool
    {
        try {
            $db = static::db();
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE provider = :provider");
            $stmt->execute(['provider' => $provider]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
