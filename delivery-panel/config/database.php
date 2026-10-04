<?php
/**
 * Delivery Panel - Dynamic Database Configuration
 * Supports both localhost (XAMPP) and online hosting
 * 
 * Environment Detection:
 * - Localhost: Uses XAMPP credentials (root, empty password, phooldelivery DB)
 * - Online: Uses cpanel credentials from .env file
 */

// Check if Database class already exists to avoid redeclaration
if (!class_exists('Database')) {
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $environment; // 'localhost' or 'online'
    public $conn;
    
    public function __construct() {
        // Detect environment and set database credentials accordingly
        $this->detectEnvironment();
        $this->setDatabaseCredentials();
        
        error_log("Environment: " . $this->environment . " | Database: " . $this->db_name . " | Host: " . $this->host);
    }
    
    /**
     * Detect if running on localhost or online hosting
     */
    private function detectEnvironment() {
        // Check if running on cPanel hosting (online)
        $hostname = gethostname();
        
        // Check if environment variables are explicitly set
        if (!empty(getenv('DB_HOST'))) {
            // Online hosting with explicit env vars
            $this->environment = 'online';
        } elseif (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                  strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                  strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false) {
            // Localhost detection
            $this->environment = 'localhost';
        } else {
            // Default to online for production domains
            $this->environment = 'online';
        }
    }
    
    /**
     * Set database credentials based on environment
     */
    private function setDatabaseCredentials() {
        if ($this->environment === 'online') {
            // Online hosting credentials from .env or defaults
            $this->host = getenv('DB_HOST') ?: 'localhost';
            $this->db_name = getenv('DB_NAME') ?: (getenv('DB_NAME') ?: 'phool_delivery_demo');
            $this->username = getenv('DB_USER') ?: (getenv('DB_USER') ?: 'root');
            $this->password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: "");
        } else {
            // Localhost (XAMPP) credentials
            $this->host = 'localhost';
            $this->db_name = 'phool_delivery_demo';
            $this->username = 'root';
            $this->password = '';
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 30,
                ]
            );
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            http_response_code(500);
            die("Database connection error. Please try again later.");
        }
        
        return $this->conn;
    }
    
    /**
     * Check if connected to online database
     */
    public function isOnline() {
        return $this->environment === 'online';
    }
    
    /**
     * Check if connected to localhost
     */
    public function isLocalhost() {
        return $this->environment === 'localhost';
    }
    
    /**
     * Get current environment
     */
    public function getEnvironment() {
        return $this->environment;
    }
    
    /**
     * Get current database name
     */
    public function getDatabaseName() {
        return $this->db_name;
    }
    
    /**
     * Get host
     */
    public function getHost() {
        return $this->host;
    }
}
} // End of class_exists check

// Return legacy config array for backward compatibility
return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: 3306,
            'database' => getenv('DB_NAME') ?: 'phool_delivery_demo',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ],
    ],
    
    'migrations' => 'database/migrations',
    'seeds' => 'database/seeds',
];
