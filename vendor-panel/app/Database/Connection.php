<?php
/**
 * Database Connection Helper
 */

namespace App\Database;

class Connection
{
    private static $instance = null;
    private $connection;
    private $config;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance($config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Connect to database
     */
    private function connect()
    {
        try {
            $config = $this->config['default'] ?? 'mysql';
            $db_config = $this->config['connections'][$config] ?? [];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db_config['host'] ?? 'localhost',
                $db_config['port'] ?? 3306,
                $db_config['database'] ?? 'phool_delivery_demo',
                $db_config['charset'] ?? 'utf8mb4'
            );

            $this->connection = new \PDO(
                $dsn,
                $db_config['username'] ?? 'root',
                $db_config['password'] ?? '',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        } catch (\PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Execute query
     */
    public function query($sql, $bindings = [])
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }

    /**
     * Insert record
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', $columns),
            implode(',', $placeholders)
        );

        return $this->query($sql, array_values($data));
    }

    /**
     * Update record
     */
    public function update($table, $data, $where)
    {
        $columns = array_keys($data);
        $set_clause = implode(' = ?, ', $columns) . ' = ?';

        $where_columns = array_keys($where);
        $where_clause = implode(' = ? AND ', $where_columns) . ' = ?';

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            $set_clause,
            $where_clause
        );

        $bindings = array_merge(array_values($data), array_values($where));
        return $this->query($sql, $bindings);
    }

    /**
     * Delete record
     */
    public function delete($table, $where)
    {
        $where_columns = array_keys($where);
        $where_clause = implode(' = ? AND ', $where_columns) . ' = ?';

        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where_clause);

        return $this->query($sql, array_values($where));
    }

    /**
     * Select query
     */
    public function select($table, $conditions = [], $limit = null)
    {
        $sql = "SELECT * FROM $table";

        if (!empty($conditions)) {
            $where_clause = implode(' AND ', array_map(fn($key) => "$key = ?", array_keys($conditions)));
            $sql .= " WHERE $where_clause";
        }

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $statement = $this->query($sql, array_values($conditions));
        return $statement->fetchAll();
    }

    /**
     * Close connection
     */
    public function close()
    {
        $this->connection = null;
    }
}
?>
