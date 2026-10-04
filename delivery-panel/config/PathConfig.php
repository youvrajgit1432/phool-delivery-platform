<?php
/**
 * Delivery Panel - Dynamic Path Configuration
 * Supports both localhost (XAMPP) and online hosting
 * 
 * Localhost: http://localhost/phool-delivery-platform/delivery-panel/public
 * Online: delivery.phooldelivery.example (document root: /delivery-panel/public)
 */

class PathConfig {
    private static $instance = null;
    private $base_path;
    private $is_online = false;
    private $paths = [];
    
    private function __construct() {
        $this->detectEnvironment();
        $this->setupPaths();
    }
    
    /**
     * Detect if running on localhost or online hosting
     */
    private function detectEnvironment() {
        // Check for online hosting indicators
        if (!empty(getenv('DB_HOST')) || 
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
            strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false) {
            
            // Check if it's a real domain (not localhost)
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (!empty($host) && strpos($host, '.') !== false && strpos($host, 'localhost') === false) {
                $this->is_online = true;
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new PathConfig();
        }
        return self::$instance;
    }
    
    /**
     * Setup all paths - dynamically for localhost and online hosting
     */
    private function setupPaths() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($this->is_online) {
            // Online hosting paths (cPanel domain routing)
            // Document root is set to /delivery-panel/public
            $this->base_path = $protocol . '://' . $host;
            
            $this->paths = [
                // Base URLs
                'base_url' => $protocol . '://' . $host,
                'api_base' => $protocol . '://' . $host . '/api',
                'assets' => $protocol . '://' . $host . '/assets',
                
                // Panel URLs
                'main' => $protocol . '://' . str_replace('delivery.', '', $host) . '/public_html',
                'admin' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/public',
                'delivery' => $protocol . '://' . $host,
                
                // API endpoints
                'api_auth' => $protocol . '://' . $host . '/api/auth',
                'api_delivery' => $protocol . '://' . $host . '/api/delivery',
                'api_orders' => $protocol . '://' . $host . '/api/orders',
                'api_location' => $protocol . '://' . $host . '/api/location',
                'api_earnings' => $protocol . '://' . $host . '/api/earnings',
                
                // Public assets
                'css' => $protocol . '://' . $host . '/assets/css',
                'js' => $protocol . '://' . $host . '/assets/js',
                'img' => $protocol . '://' . $host . '/assets/img',
                'lib' => $protocol . '://' . $host . '/assets/lib',
                
                // Direct paths to admin storage via symlinks or proxies
                'product_images' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/products',
                'ads_images' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/ads',
                'carousel_images' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/carousel',
                'notices_images' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/notices',
                'media' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/media',
                'wallet_qr' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/wallet_qr',
                'profiles' => $protocol . '://' . str_replace('delivery.', '', $host) . '/admin/storage/uploads/profiles',
                
                // File system paths (cPanel)
                'root_dir' => $_SERVER['DOCUMENT_ROOT'],
                'delivery_dir' => $_SERVER['DOCUMENT_ROOT'],
                'admin_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin',
                'public_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/public_html',
                
                // Uploads directories
                'uploads_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads',
                'carousel_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/carousel',
                'products_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/products',
                'ads_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/ads',
                'notices_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/notices',
                'media_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/media',
                'wallet_qr_dir' => dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/admin/storage/uploads/wallet_qr',
                'delivery_profiles_dir' => $_SERVER['DOCUMENT_ROOT'] . '/storage/uploads/profiles',
                'delivery_documents_dir' => $_SERVER['DOCUMENT_ROOT'] . '/storage/uploads/documents',
                
                // Storage paths
                'storage' => $_SERVER['DOCUMENT_ROOT'] . '/storage',
                'logs' => $_SERVER['DOCUMENT_ROOT'] . '/storage/logs',
                'cache' => $_SERVER['DOCUMENT_ROOT'] . '/storage/cache',
            ];
        } else {
            // Localhost paths (XAMPP)
            $this->base_path = $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public';
            
            $this->paths = [
                // Base URLs
                'base_url' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public',
                'api_base' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api',
                'assets' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/assets',
                
                // Panel URLs
                'main' => $protocol . '://' . $host . '/phool-delivery-platform/public_html',
                'admin' => $protocol . '://' . $host . '/phool-delivery-platform/admin',
                'delivery' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public',
                
                // API endpoints
                'api_auth' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api/auth',
                'api_delivery' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api/delivery',
                'api_orders' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api/orders',
                'api_location' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api/location',
                'api_earnings' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/api/earnings',
                
                // Public assets
                'css' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/assets/css',
                'js' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/assets/js',
                'img' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/assets/img',
                'lib' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public/assets/lib',
                
                // Direct paths to admin storage
                'product_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/products',
                'ads_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/ads',
                'carousel_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'notices_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'profiles' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/profiles',
                
                // File system paths
                'root_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform',
                'delivery_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel',
                'admin_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin',
                'public_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/public_html',
                
                // Uploads directories
                'uploads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads',
                'carousel_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'products_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/products',
                'ads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/ads',
                'notices_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'delivery_profiles_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/uploads/profiles',
                'delivery_documents_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/uploads/documents',
                
                // Storage paths
                'storage' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage',
                'logs' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/logs',
                'cache' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/cache',
            ];
        }
    }
    
    /**
     * Get a configuration value
     */
    public function get($key) {
        return $this->paths[$key] ?? null;
    }
    
    /**
     * Check if running on online hosting
     */
    public function isOnline() {
        return $this->is_online;
    }
    
    /**
     * Check if running on localhost
     */
    public function isLocalhost() {
        return !$this->is_online;
    }
    
    /**
     * Get base path
     */
    public function getBasePath() {
        return $this->base_path;
    }
    
    /**
     * Helper method to get image path with fallback
     */
    public function getImagePath($image_name, $type = 'product') {
        if (empty($image_name) || $image_name === 'default.jpg') {
            return $this->get('assets') . '/img/products/placeholder.jpg';
        }
        
        $base_path = '';
        $local_path = '';
        
        switch ($type) {
            case 'product':
                $base_path = $this->get('product_images');
                $local_path = $this->get('products_dir') . '/' . $image_name;
                break;
            case 'ad':
                $base_path = $this->get('ads_images');
                $local_path = $this->get('ads_dir') . '/' . $image_name;
                break;
            case 'notice':
                $base_path = $this->get('notices_images');
                $local_path = $this->get('notices_dir') . '/' . $image_name;
                break;
            case 'carousel':
                $base_path = $this->get('carousel_images');
                $local_path = $this->get('carousel_dir') . '/' . $image_name;
                break;
            case 'media':
                $base_path = $this->get('media');
                $local_path = $this->get('media_dir') . '/' . $image_name;
                break;
            case 'media_thumb':
                $base_path = $this->get('media') . '/thumbs';
                $local_path = $this->get('media_dir') . '/thumbs/' . $image_name;
                break;
            case 'wallet_qr':
                $base_path = $this->get('wallet_qr');
                $local_path = $this->get('wallet_qr_dir') . '/' . $image_name;
                break;
            case 'delivery_profile':
                $base_path = $this->base_path . '/assets/img/delivery-profiles';
                $local_path = $this->get('delivery_profiles_dir') . '/' . $image_name;
                break;
            default:
                $base_path = $this->get('product_images');
                $local_path = $this->get('products_dir') . '/' . $image_name;
        }
        
        // For local development - directly check if file exists
        if (!$this->is_online) {
            if (file_exists($local_path) && is_readable($local_path)) {
                return $base_path . '/' . $image_name;
            }
            return $this->get('assets') . '/img/products/placeholder.jpg';
        }
        
        // For online hosting, check if symlink exists
        if ($this->is_online) {
            $symlink_path = $this->get('root_dir') . '/' . basename($base_path) . '/' . $image_name;
            if (file_exists($symlink_path) && is_readable($symlink_path)) {
                return $base_path . '/' . $image_name;
            }
        }
        
        // Check if file exists in original location
        if (file_exists($local_path)) {
            return $base_path . '/' . $image_name;
        }
        
        return $this->get('assets') . '/img/products/placeholder.jpg';
    }
    
    /**
     * Helper method to get file path with fallback
     */
    public function getFilePath($file_name, $type = 'product') {
        if (empty($file_name)) {
            return '';
        }
        
        $base_path = '';
        $local_path = '';
        
        switch ($type) {
            case 'notice':
                $base_path = $this->get('notices_images');
                $local_path = $this->get('notices_dir') . '/' . $file_name;
                break;
            case 'ad':
                $base_path = $this->get('ads_images');
                $local_path = $this->get('ads_dir') . '/' . $file_name;
                break;
            case 'media':
                $base_path = $this->get('media');
                $local_path = $this->get('media_dir') . '/' . $file_name;
                break;
            case 'delivery_document':
                $base_path = $this->base_path . '/assets/documents';
                $local_path = $this->get('delivery_documents_dir') . '/' . $file_name;
                break;
            default:
                return '';
        }
        
        // For local development
        if (!$this->is_online) {
            if (file_exists($local_path)) {
                return $base_path . '/' . $file_name;
            }
            return '';
        }
        
        // For online hosting
        if ($this->is_online) {
            $symlink_path = $this->get('root_dir') . '/' . basename($base_path) . '/' . $file_name;
            if (file_exists($symlink_path)) {
                return $base_path . '/' . $file_name;
            }
        }
        
        // Check original location
        if (file_exists($local_path)) {
            return $base_path . '/' . $file_name;
        }
        
        return '';
    }
    
    /**
     * Helper method to get correct URL for routes
     */
    public function url($path = '') {
        $base_url = $this->get('base_url');
        $path = ltrim($path, '/');
        
        if (empty($path)) {
            return $base_url . '/';
        }
        
        return $base_url . '/' . $path;
    }
    
    /**
     * Helper method for file system paths
     */
    public function filePath($type = '') {
        switch ($type) {
            case 'storage':
                return $this->get('storage');
            case 'logs':
                return $this->get('logs');
            case 'cache':
                return $this->get('cache');
            case 'uploads':
                return $this->get('uploads_dir');
            case 'delivery_profiles':
                return $this->get('delivery_profiles_dir');
            case 'delivery_documents':
                return $this->get('delivery_documents_dir');
            default:
                return $this->get('root_dir');
        }
    }
    
    /**
     * Verify symlinks are working (for online only)
     */
    public function verifySymlinks() {
        if (!$this->is_online) {
            return ['status' => 'local', 'message' => 'Running on local environment'];
        }
        
        $symlinks = [
            'product_images',
            'ad_images',
            'notice_images',
            'carousel_images',
            'media_files',
            'wallet_qr_codes',
            'profile_images'
        ];
        
        $results = [];
        
        foreach ($symlinks as $link) {
            $symlink_path = $this->get('root_dir') . '/' . $link;
            
            if (is_link($symlink_path)) {
                $results[$link] = [
                    'status' => 'success',
                    'symlink' => $symlink_path,
                    'target' => readlink($symlink_path),
                    'accessible' => file_exists($symlink_path)
                ];
            } else {
                $results[$link] = [
                    'status' => 'error',
                    'symlink' => $symlink_path,
                    'accessible' => false
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Debug method to check image paths
     */
    public function debugImagePath($image_name, $type = 'product') {
        $result = [
            'image_name' => $image_name,
            'type' => $type,
            'environment' => $this->is_online ? 'online' : 'local',
            'final_url' => $this->getImagePath($image_name, $type),
            'checks' => []
        ];
        
        $base_path = $this->get($type . '_images');
        $local_path = $this->get($type . 's_dir') . '/' . $image_name;
        
        $result['checks']['base_url'] = $base_path;
        $result['checks']['local_path'] = $local_path;
        $result['checks']['file_exists_local'] = file_exists($local_path);
        $result['checks']['is_readable_local'] = is_readable($local_path);
        
        return $result;
    }
    
    /**
     * Debug current configuration
     */
    public function debugConfig() {
        return [
            'environment' => $this->is_online ? 'online' : 'localhost',
            'is_online' => $this->is_online,
            'base_path' => $this->base_path,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
        ];
    }
}
?>
