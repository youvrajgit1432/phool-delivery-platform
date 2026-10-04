<?php
// Environment detection helper
class EnvironmentHelper {
    
    // Detect if we're on localhost
    public static function isLocalhost() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        $localhost_patterns = [
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.test',
            'phool-delivery-platform.test'
        ];
        
        foreach ($localhost_patterns as $pattern) {
            if (strpos($host, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // Get base URL
    public static function getBaseUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host;
    }
    
    // Get main application assets path
    public static function getMainAssetsPath() {
        $base_url = self::getBaseUrl();
        
        if (self::isLocalhost()) {
            return $base_url . '/phool-delivery-platform/public_html/assets';
        } else {
            return $base_url . '/assets';
        }
    }
    
    // Get admin assets path
    public static function getAdminAssetsPath() {
        $base_url = self::getBaseUrl();
        
        if (self::isLocalhost()) {
            return $base_url . '/phool-delivery-platform/admin/public/assets';
        } else {
            return $base_url . '/admin/public/assets';
        }
    }
    
    // Get media path
    public static function getMediaPath() {
        $base_url = self::getBaseUrl();
        
        if (self::isLocalhost()) {
            return $base_url . '/phool-delivery-platform/public_html/media';
        } else {
            return $base_url . '/media';
        }
    }
    
    // Get base directory for file operations
    public static function getBaseDirectory() {
        if (self::isLocalhost()) {
            return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform';
        } else {
            return $_SERVER['DOCUMENT_ROOT'];
        }
    }
    
    // Get uploads directory path
    public static function getUploadsPath() {
        $base_dir = self::getBaseDirectory();
        
        if (self::isLocalhost()) {
            return $base_dir . '/uploads';
        } else {
            return $base_dir . '/../uploads';
        }
    }
}
?>