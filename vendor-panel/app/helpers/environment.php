<?php
/**
 * Vendor Panel Environment Detection Helper
 * Handles environment-specific configurations for both localhost and online hosting
 */

class EnvironmentHelper {
    
    /**
     * Detect if we're on localhost
     */
    public static function isLocalhost() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        $localhost_patterns = [
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.test',
            'phool-delivery.test'
        ];
        
        foreach ($localhost_patterns as $pattern) {
            if (strpos($host, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect if we're on vendor.phooldelivery.example
     */
    public static function isVendorSubdomain() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return strpos($host, 'vendor.phooldelivery.example') !== false;
    }
    
    /**
     * Get base URL
     */
    public static function getBaseUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host;
    }
    
    /**
     * Get vendor panel base URL
     */
    public static function getVendorPanelUrl() {
        if (self::isLocalhost()) {
            return self::getBaseUrl() . '/phool-delivery-platform/vendor-panel';
        } else {
            return 'https://vendor.phooldelivery.example';
        }
    }
    
    /**
     * Get main site base URL
     */
    public static function getMainSiteUrl() {
        if (self::isLocalhost()) {
            return self::getBaseUrl() . '/phool-delivery-platform/public_html';
        } else {
            return 'https://phooldelivery.example';
        }
    }
    
    /**
     * Get admin panel base URL
     */
    public static function getAdminPanelUrl() {
        if (self::isLocalhost()) {
            return self::getBaseUrl() . '/phool-delivery-platform/admin/public';
        } else {
            return 'https://admin.phooldelivery.example';
        }
    }
    
    /**
     * Get delivery panel base URL
     */
    public static function getDeliveryPanelUrl() {
        if (self::isLocalhost()) {
            return self::getBaseUrl() . '/phool-delivery-platform/delivery-panel/public';
        } else {
            return 'https://delivery.phooldelivery.example';
        }
    }
    
    /**
     * Get vendor panel assets path
     */
    public static function getVendorAssetsPath() {
        return self::getVendorPanelUrl() . '/public/assets';
    }
    
    /**
     * Get main site assets path
     */
    public static function getMainAssetsPath() {
        $base_url = self::getMainSiteUrl();
        
        if (self::isLocalhost()) {
            return $base_url . '/assets';
        } else {
            return self::getBaseUrl() . '/assets';
        }
    }
    
    /**
     * Get admin panel assets path
     */
    public static function getAdminAssetsPath() {
        return self::getAdminPanelUrl() . '/assets';
    }
    
    /**
     * Get base directory for file operations
     */
    public static function getBaseDirectory() {
        if (self::isLocalhost()) {
            return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform';
        } else {
            return $_SERVER['DOCUMENT_ROOT'];
        }
    }
    
    /**
     * Get vendor panel directory
     */
    public static function getVendorPanelDirectory() {
        return self::getBaseDirectory() . '/vendor-panel';
    }
    
    /**
     * Get admin panel directory
     */
    public static function getAdminDirectory() {
        if (self::isLocalhost()) {
            return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin';
        } else {
            return dirname($_SERVER['DOCUMENT_ROOT']) . '/admin';
        }
    }
    
    /**
     * Get admin uploads directory
     */
    public static function getAdminUploadsPath() {
        return self::getAdminDirectory() . '/storage/uploads';
    }
    
    /**
     * Get vendor uploads directory
     */
    public static function getVendorUploadsPath() {
        return self::getVendorPanelDirectory() . '/uploads';
    }
    
    /**
     * Get products upload directory
     */
    public static function getProductsUploadPath() {
        return self::getAdminUploadsPath() . '/products';
    }
    
    /**
     * Get vendor images upload directory
     */
    public static function getVendorImagesUploadPath() {
        return self::getVendorUploadsPath() . '/vendors';
    }
    
    /**
     * Get profiles upload directory
     */
    public static function getProfilesUploadPath() {
        return self::getAdminUploadsPath() . '/profiles';
    }
    
    /**
     * Get carousel upload directory
     */
    public static function getCarouselUploadPath() {
        return self::getAdminUploadsPath() . '/carousel';
    }
    
    /**
     * Get media upload directory
     */
    public static function getMediaUploadPath() {
        return self::getAdminUploadsPath() . '/media';
    }
    
    /**
     * Get ads upload directory
     */
    public static function getAdsUploadPath() {
        return self::getAdminUploadsPath() . '/ads';
    }
    
    /**
     * Get notices upload directory
     */
    public static function getNoticesUploadPath() {
        return self::getAdminUploadsPath() . '/notices';
    }
    
    /**
     * Get storage cache directory
     */
    public static function getStorageCachePath() {
        return self::getVendorPanelDirectory() . '/storage/cache';
    }
    
    /**
     * Get storage logs directory
     */
    public static function getStorageLogsPath() {
        return self::getVendorPanelDirectory() . '/storage/logs';
    }
    
    /**
     * Get database name
     */
    public static function getDatabaseName() {
        if (self::isLocalhost()) {
            return 'phool_delivery_demo';
        } else {
            return (getenv('DB_NAME') ?: 'phool_delivery_demo');
        }
    }
    
    /**
     * Get database username
     */
    public static function getDatabaseUsername() {
        if (self::isLocalhost()) {
            return 'root';
        } else {
            return (getenv('DB_USER') ?: 'root');
        }
    }
    
    /**
     * Get database password
     */
    public static function getDatabasePassword() {
        if (self::isLocalhost()) {
            return '';
        } else {
            return (getenv('DB_PASSWORD') ?: "");
        }
    }
    
    /**
     * Get database host
     */
    public static function getDatabaseHost() {
        return 'localhost';
    }
    
    /**
     * Get database port
     */
    public static function getDatabasePort() {
        return 3306;
    }
    
    /**
     * Get full database configuration
     */
    public static function getDatabaseConfig() {
        return [
            'driver' => 'mysql',
            'host' => self::getDatabaseHost(),
            'port' => self::getDatabasePort(),
            'database' => self::getDatabaseName(),
            'username' => self::getDatabaseUsername(),
            'password' => self::getDatabasePassword(),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }
    
    /**
     * Get API endpoint URL
     */
    public static function getApiUrl($endpoint = '') {
        $base = self::getMainSiteUrl();
        
        if (empty($endpoint)) {
            return $base . '/api';
        }
        
        return $base . '/api/' . ltrim($endpoint, '/');
    }
    
    /**
     * Get vendor API endpoint URL
     */
    public static function getVendorApiUrl($endpoint = '') {
        $base = self::getVendorPanelUrl();
        
        if (empty($endpoint)) {
            return $base . '/public/ajax';
        }
        
        return $base . '/public/ajax/' . ltrim($endpoint, '/');
    }
    
    /**
     * Get admin API endpoint URL
     */
    public static function getAdminApiUrl($endpoint = '') {
        $base = self::getAdminPanelUrl();
        
        if (empty($endpoint)) {
            return $base . '/../api';
        }
        
        return $base . '/../api/' . ltrim($endpoint, '/');
    }
    
    /**
     * Get complete configuration array for debugging
     */
    public static function getCompleteConfig() {
        return [
            'environment' => self::isLocalhost() ? 'localhost' : 'online',
            'is_localhost' => self::isLocalhost(),
            'is_vendor_subdomain' => self::isVendorSubdomain(),
            'base_url' => self::getBaseUrl(),
            'urls' => [
                'vendor_panel' => self::getVendorPanelUrl(),
                'main_site' => self::getMainSiteUrl(),
                'admin_panel' => self::getAdminPanelUrl(),
                'delivery_panel' => self::getDeliveryPanelUrl(),
            ],
            'assets' => [
                'vendor' => self::getVendorAssetsPath(),
                'main' => self::getMainAssetsPath(),
                'admin' => self::getAdminAssetsPath(),
            ],
            'directories' => [
                'base' => self::getBaseDirectory(),
                'vendor_panel' => self::getVendorPanelDirectory(),
                'admin' => self::getAdminDirectory(),
            ],
            'uploads' => [
                'admin' => self::getAdminUploadsPath(),
                'vendor' => self::getVendorUploadsPath(),
                'products' => self::getProductsUploadPath(),
                'vendor_images' => self::getVendorImagesUploadPath(),
                'profiles' => self::getProfilesUploadPath(),
                'carousel' => self::getCarouselUploadPath(),
                'media' => self::getMediaUploadPath(),
                'ads' => self::getAdsUploadPath(),
                'notices' => self::getNoticesUploadPath(),
            ],
            'storage' => [
                'cache' => self::getStorageCachePath(),
                'logs' => self::getStorageLogsPath(),
            ],
            'database' => self::getDatabaseConfig(),
            'api' => [
                'main' => self::getApiUrl(),
                'vendor' => self::getVendorApiUrl(),
                'admin' => self::getAdminApiUrl(),
            ],
            'server_info' => [
                'http_host' => $_SERVER['HTTP_HOST'] ?? '',
                'server_name' => $_SERVER['SERVER_NAME'] ?? '',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
            ]
        ];
    }
}
?>
