<?php

namespace App\Core;

use PDO;
use PDOStatement;

abstract class Model
{
    /**
     * Database table name
     */
    protected string $table = '';

    /**
     * Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * Fillable fields for mass assignment
     */
    protected array $fillable = [];

    /**
     * Get PDO connection.
     */
    public static function db(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Find record by ID.
     */
    public function find(int|string $id): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find single record by column condition.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM {$this->table} WHERE {$column} = :val LIMIT 1");
        $stmt->execute(['val' => $value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all records.
     */
    public function all(string $orderBy = 'id DESC'): array
    {
        $stmt = static::db()->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
        return $stmt->fetchAll();
    }

    /**
     * Get records with WHERE conditions.
     */
    public function where(array $conditions, string $orderBy = 'id DESC'): array
    {
        $clauses = [];
        $params = [];

        foreach ($conditions as $col => $val) {
            $paramName = "p_" . str_replace('.', '_', $col);
            $clauses[] = "{$col} = :{$paramName}";
            $params[$paramName] = $val;
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }
        $sql .= " ORDER BY {$orderBy}";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count records.
     */
    public function count(array $conditions = []): int
    {
        $clauses = [];
        $params = [];

        foreach ($conditions as $col => $val) {
            $paramName = "p_" . str_replace('.', '_', $col);
            $clauses[] = "{$col} = :{$paramName}";
            $params[$paramName] = $val;
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return (int)($res['total'] ?? 0);
    }

    /**
     * Insert a new record.
     */
    public function create(array $data): int|string
    {
        if (!empty($this->fillable)) {
            $filtered = array_intersect_key($data, array_flip($this->fillable));
        } else {
            $filtered = $data;
        }

        if (empty($filtered)) {
            throw new \InvalidArgumentException("No valid or fillable attributes provided for model creation.");
        }

        $cols = array_keys($filtered);
        $placeholders = array_map(fn($col) => ":{$col}", $cols);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        $stmt = static::db()->prepare($sql);
        $stmt->execute($filtered);

        return static::db()->lastInsertId();
    }

    /**
     * Update an existing record.
     */
    public function update(int|string $id, array $data): bool
    {
        if (!empty($this->fillable)) {
            $filtered = array_intersect_key($data, array_flip($this->fillable));
        } else {
            $filtered = $data;
        }

        if (empty($filtered)) {
            return false;
        }

        $assignments = [];
        $params = [':id' => $id];

        foreach ($filtered as $col => $val) {
            $assignments[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :id",
            $this->table,
            implode(', ', $assignments),
            $this->primaryKey
        );

        $stmt = static::db()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete record by ID.
     */
    public function delete(int|string $id): bool
    {
        $stmt = static::db()->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Execute custom raw query.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
