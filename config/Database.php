<?php
/**
 * Database Connection Class
 */
class Database {
    private $host;
    private $db_name;
    private $user;
    private $password;
    private $conn;

    public function __construct() {
        // Load from environment or use defaults
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'phool_delivery_demo';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
    }

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->user,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
            return null;
        }

        return $this->conn;
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
}
?>
