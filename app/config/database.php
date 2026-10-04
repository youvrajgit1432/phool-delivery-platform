<?php
// app/config/database.php
//
// Public (sanitized) database configuration.
// All values resolve from environment variables so the application can run
// against any database (local demo, CI, self-hosted) without embedding
// credentials in source control.
//
// Canonical variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
// (DB_PASS is accepted as a backwards-compatible alias.)

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host     = getenv('DB_HOST') ?: 'localhost';
        $this->db_name  = getenv('DB_NAME') ?: 'phool_delivery_demo';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: '');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $port = getenv('DB_PORT') ?: '3306';
            $dsn = "mysql:host=" . $this->host . ";port=" . $port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->exec("SET NAMES utf8mb4");
            $this->conn->exec("SET CHARACTER SET utf8mb4");
            $this->conn->exec("SET character_set_connection=utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // True when running under APP_ENV=production
    public function isOnline() {
        return (getenv('APP_ENV') ?: 'local') === 'production';
    }

    public function getDatabaseName() {
        return $this->db_name;
    }
}
?>
