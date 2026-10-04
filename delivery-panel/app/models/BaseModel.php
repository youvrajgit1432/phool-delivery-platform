<?php
/**
 * BaseModel - Database Query Builder
 * Provides common database operations for all models
 */

namespace Phool\DeliveryPanel\Models;

use PDO;
use PDOException;

class BaseModel {
    protected $table;
    protected $connection;
    protected $query;
    protected $bindings = [];
    
    public function __construct() {
        $this->connect();
    }
    
    /**
     * Connect to database
     * Uses the global PDO connection initialized in bootstrap/app.php
     */
    protected function connect() {
        try {
            // Check if global PDO connection exists
            if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                $this->connection = $GLOBALS['pdo'];
            } else {
                // Fallback: create new connection (should rarely happen)
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
                $user = getenv('DB_USER') ?: 'root';
                $password = getenv('DB_PASSWORD') ?: '';
                
                $this->connection = new PDO(
                    'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4',
                    $user,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            }
        } catch (PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Find by ID
     */
    public function find($id) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? (object)$data : null;
    }
    
    /**
     * Get all records
     */
    public function all() {
        $stmt = $this->connection->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }
    
    /**
     * Get where
     */
    public function where($column, $operator = '=', $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        if (strtoupper($operator) === 'IN' && is_array($value)) {
            $placeholders = implode(',', array_fill(0, count($value), '?'));
            $this->query = "SELECT * FROM {$this->table} WHERE {$column} IN ($placeholders)";
            $this->bindings = $value;
        } else {
            $this->query = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
            $this->bindings = [$value];
        }
        return $this;
    }
    
    /**
     * And where
     */
    public function andWhere($column, $operator = '=', $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        if (strtoupper($operator) === 'IN' && is_array($value)) {
            $placeholders = implode(',', array_fill(0, count($value), '?'));
            $this->query .= " AND {$column} IN ($placeholders)";
            foreach ($value as $v) {
                $this->bindings[] = $v;
            }
        } else {
            $this->query .= " AND {$column} {$operator} ?";
            $this->bindings[] = $value;
        }
        return $this;
    }
    
    /**
     * Order by
     */
    public function orderBy($column, $direction = 'ASC') {
        $this->query .= " ORDER BY {$column} {$direction}";
        return $this;
    }
    
    /**
     * Limit
     */
    public function limit($limit, $offset = 0) {
        $this->query .= " LIMIT {$offset}, {$limit}";
        return $this;
    }
    
    /**
     * Get first result
     */
    public function first() {
        $stmt = $this->connection->prepare($this->query);
        $stmt->execute($this->bindings);
        $data = $stmt->fetch();
        $this->resetQuery();
        return $data ? (object)$data : null;
    }
    
    /**
     * Get all results
     */
    public function get() {
        $stmt = $this->connection->prepare($this->query);
        $stmt->execute($this->bindings);
        $results = $stmt->fetchAll();
        $this->resetQuery();
        // Convert arrays to objects
        return array_map(function($row) {
            return (object)$row;
        }, $results);
    }
    
    /**
     * Raw query
     */
    public function raw($sql, $bindings = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }
    
    /**
     * Count records
     */
    public function count() {
        $sql = preg_replace('/SELECT \*/', 'SELECT COUNT(*) as total', $this->query);
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch();
        $this->resetQuery();
        return $result['total'] ?? 0;
    }
    
    /**
     * Insert
     */
    public function insert($data) {
        $columns = implode(',', array_keys($data));
        $values = implode(',', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * Update
     */
    public function update($id, $data) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE id = ?";
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Reset query
     */
    protected function resetQuery() {
        $this->query = null;
        $this->bindings = [];
    }
}
