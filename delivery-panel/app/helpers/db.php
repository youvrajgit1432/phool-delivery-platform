<?php
/**
 * Simple PDO helper
 */

if (!function_exists('get_pdo')) {
    function get_pdo() {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $conn = $config['connections'][$config['default']] ?? $config['connections']['mysql'];

        $host = $conn['host'] ?? '127.0.0.1';
        $port = $conn['port'] ?? 3306;
        $db   = $conn['database'] ?? 'phool_delivery_demo';
        $user = $conn['username'] ?? 'root';
        $pass = $conn['password'] ?? '';
        $charset = $conn['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
