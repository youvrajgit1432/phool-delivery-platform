<?php
/**
 * Vendor Panel Database Configuration
 * Automatically detects localhost vs online hosting
 */

// Check if Database class already exists to avoid redeclaration
if (!class_exists('Database')) {
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;
    private $is_online;
    
    public function __construct() {
        $this->detectEnvironment();
    }
    
    private function detectEnvironment() {
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Check if we're on online hosting
        $this->is_online = (
            strpos($document_root, '/home2/phooldel') !== false ||
            strpos($http_host, 'vendor.phooldelivery.example') !== false ||
            strpos($http_host, 'phooldelivery.example') !== false
        );
        
        // Force localhost detection
        if (strpos($http_host, 'localhost') !== false || 
            strpos($http_host, '127.0.0.1') !== false) {
            $this->is_online = false;
        }
        
        if ($this->is_online) {
            // Online hosting database credentials
            $this->host = "localhost";
            $this->db_name = (getenv('DB_NAME') ?: 'phool_delivery_demo');
            $this->username = (getenv('DB_USER') ?: 'root');
            $this->password = (getenv('DB_PASSWORD') ?: "");
        } else {
            // Localhost database credentials (XAMPP)
            $this->host = "localhost";
            $this->db_name = "phool_delivery_demo";
            $this->username = "root";
            $this->password = "";
        }
        
        error_log("VENDOR PANEL - Database Environment: " . ($this->is_online ? 'ONLINE' : 'LOCAL'));
        error_log("VENDOR PANEL - Database: " . $this->db_name);
    }
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("VENDOR PANEL - Database connection error: " . $exception->getMessage());
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    // Helper method to check if connected to online database
    public function isOnline() {
        return $this->is_online;
    }
    
    // Get current database name
    public function getDatabaseName() {
        return $this->db_name;
    }
    
    // Get database configuration array
    public function getConfig() {
        return [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => $this->host,
                    'port' => 3306,
                    'database' => $this->db_name,
                    'username' => $this->username,
                    'password' => $this->password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ],
        ];
    }
}

// Return configuration array for compatibility (only if not already in global scope)
if (!isset($GLOBALS['_vendor_database_config'])) {
    $database = new Database();
    $GLOBALS['_vendor_database_config'] = $database->getConfig();
}

} // End of class_exists check

return $GLOBALS['_vendor_database_config'];
?>
