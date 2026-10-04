<?php
/**
 * Delivery Panel - Localhost Environment Helper
 * Simplified for localhost development only
 */

class EnvironmentHelper {
    
    /**
     * Get environment name (always localhost)
     */
    public static function getEnvironment() {
        return 'local';
    }
    
    /**
     * Get base URL
     */
    public static function getBaseUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/public';
    }
    
    /**
     * Get delivery panel assets path
     */
    public static function getDeliveryAssetsPath() {
        return self::getBaseUrl() . '/assets';
    }
    
    /**
     * Get admin assets path
     */
    public static function getAdminAssetsPath() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/public/assets';
    }
    
    /**
     * Get base directory for file operations
     */
    public static function getBaseDirectory() {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform';
    }
    
    /**
     * Get delivery panel directory
     */
    public static function getDeliveryDirectory() {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel';
    }
    
    /**
     * Get admin directory
     */
    public static function getAdminDirectory() {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin';
    }
    
    /**
     * Get uploads directory path
     */
    public static function getUploadsPath() {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/uploads';
    }
    
    /**
     * Get storage directory path
     */
    public static function getStoragePath() {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage';
    }
    
    /**
     * Get logs directory path
     */
    public static function getLogsPath() {
        return self::getStoragePath() . '/logs';
    }
    
    /**
     * Get cache directory path
     */
    public static function getCachePath() {
        return self::getStoragePath() . '/cache';
    }
    
    /**
     * Get product images URL
     */
    public static function getProductImagesUrl($image_name = '') {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_name;
    }
    
    /**
     * Get ad images URL
     */
    public static function getAdImagesUrl($image_name = '') {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/ads/' . $image_name;
    }
    
    /**
     * Get delivery profile image URL
     */
    public static function getDeliveryProfileUrl($profile_picture = '') {
        if (empty($profile_picture)) {
            return self::getDeliveryAssetsPath() . '/img/avatars/default-avatar.png';
        }
        
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/storage/uploads/profiles/' . $profile_picture;
    }
    
    /**
     * Get database credentials (localhost only)
     */
    public static function getDatabaseConfig() {
        return [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'phool_delivery_demo',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }
    
    /**
     * Get current server info
     */
    public static function getServerInfo() {
        return [
            'environment' => 'local',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        ];
    }
    
    /**
     * Check if HTTPS is enabled
     */
    public static function isHttps() {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }
    
    /**
     * Get protocol (http or https)
     */
    public static function getProtocol() {
        return self::isHttps() ? 'https' : 'http';
    }
    
    /**
     * Build full URL
     */
    public static function buildUrl($path = '') {
        $base = self::getBaseUrl();
        $path = ltrim($path, '/');
        
        if (empty($path)) {
            return $base . '/';
        }
        
        return $base . '/' . $path;
    }
    
    /**
     * Verify directory exists and is writable
     */
    public static function verifyDirectory($directory) {
        return is_dir($directory) && is_writable($directory);
    }
    
    /**
     * Verify logs directory
     */
    public static function verifyLogsDirectory() {
        $logs_dir = self::getLogsPath();
        
        if (!is_dir($logs_dir)) {
            @mkdir($logs_dir, 0755, true);
        }
        
        return self::verifyDirectory($logs_dir);
    }
    
    /**
     * Verify cache directory
     */
    public static function verifyCacheDirectory() {
        $cache_dir = self::getCachePath();
        
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
        }
        
        return self::verifyDirectory($cache_dir);
    }
}
?>
