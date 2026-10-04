<?php
/**
 * Vendor Panel Dynamic Path Configuration
 * Handles both localhost and online hosting environments automatically
 * 
 * Localhost: http://localhost/phool-delivery-platform/vendor-panel
 * Online: https://vendor.phooldelivery.example
 */

class PathConfig {
    private static $instance = null;
    private $base_path;
    private $is_online;
    private $paths = [];
    private $panel_type = 'vendor'; // This is the vendor panel
    
    private function __construct() {
        $this->detectEnvironment();
        $this->setupPaths();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new PathConfig();
        }
        return self::$instance;
    }
    
    private function detectEnvironment() {
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        
        error_log("VENDOR PANEL - DEBUG - Document Root: " . $document_root);
        error_log("VENDOR PANEL - DEBUG - HTTP Host: " . $http_host);
        error_log("VENDOR PANEL - DEBUG - Server Name: " . $server_name);
        
        // Check if we're on online hosting - vendor.phooldelivery.example or subdomains
        $this->is_online = (
            strpos($document_root, '/home2/phooldel') !== false ||
            strpos($http_host, 'vendor.phooldelivery.example') !== false ||
            strpos($http_host, 'phooldelivery.example') !== false ||
            strpos($server_name, 'phooldelivery.example') !== false ||
            (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1')
        );
        
        // Force localhost detection
        if (strpos($http_host, 'localhost') !== false || 
            strpos($http_host, '127.0.0.1') !== false ||
            strpos($server_name, 'localhost') !== false ||
            (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')) {
            $this->is_online = false;
        }
        
        error_log("VENDOR PANEL - Environment detected: " . ($this->is_online ? 'ONLINE' : 'LOCAL'));
    }
    
    private function setupPaths() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($this->is_online) {
            // Online hosting paths - vendor.phooldelivery.example
            $this->base_path = $protocol . '://' . $host;
            
            $this->paths = [
                // Base URLs
                'base_url' => $this->base_path,
                'vendor_panel_url' => 'https://vendor.phooldelivery.example',
                'main_site_url' => 'https://phooldelivery.example',
                'admin_panel_url' => 'https://admin.phooldelivery.example',
                'delivery_panel_url' => 'https://delivery.phooldelivery.example',
                
                // Assets paths
                'assets' => $this->base_path . '/assets',
                'css' => $this->base_path . '/assets/css',
                'js' => $this->base_path . '/assets/js',
                'img' => $this->base_path . '/assets/img',
                'fonts' => $this->base_path . '/assets/fonts',
                
                // Image CDN paths (via symlinks in online hosting)
                'product_images' => $this->base_path . '/product_images',
                'vendor_images' => $this->base_path . '/vendor_images',
                'ad_images' => $this->base_path . '/ad_images',
                'carousel_images' => $this->base_path . '/carousel_images',
                'notice_images' => $this->base_path . '/notice_images',
                'media_files' => $this->base_path . '/media_files',
                'wallet_qr_codes' => $this->base_path . '/wallet_qr_codes',
                'profile_images' => $this->base_path . '/profile_images',
                
                // File system paths for online (admin shared storage)
                'root_dir' => $_SERVER['DOCUMENT_ROOT'],
                'vendor_panel_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/vendor-panel',
                'admin_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin',
                'uploads_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads',
                'vendor_uploads_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/vendor-panel/uploads',
                'carousel_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/carousel',
                'products_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/products',
                'vendor_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/vendors',
                'ads_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/ads',
                'notices_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/notices',
                'media_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/media',
                'wallet_qr_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/wallet_qr',
                'profiles_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/profiles',
            ];
        } else {
            // Localhost paths (XAMPP) - http://localhost/phool-delivery-platform/vendor-panel
            $this->base_path = $protocol . '://' . $host . '/phool-delivery-platform/vendor-panel';
            
            $this->paths = [
                // Base URLs
                'base_url' => $this->base_path,
                'vendor_panel_url' => $protocol . '://' . $host . '/phool-delivery-platform/vendor-panel',
                'main_site_url' => $protocol . '://' . $host . '/phool-delivery-platform/public_html',
                'admin_panel_url' => $protocol . '://' . $host . '/phool-delivery-platform/admin/public',
                'delivery_panel_url' => $protocol . '://' . $host . '/phool-delivery-platform/delivery-panel/public',
                
                // Assets paths
                'assets' => $this->base_path . '/public/assets',
                'css' => $this->base_path . '/public/assets/css',
                'js' => $this->base_path . '/public/assets/js',
                'img' => $this->base_path . '/public/assets/img',
                'fonts' => $this->base_path . '/public/assets/fonts',
                
                // Image paths - direct to admin storage for local development
                'product_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/products',
                'vendor_images' => $protocol . '://' . $host . '/phool-delivery-platform/vendor-panel/uploads/vendors',
                'ad_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/ads',
                'carousel_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'notice_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media_files' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr_codes' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'profile_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/profiles',
                
                // File system paths for local development
                'root_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform',
                'vendor_panel_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/vendor-panel',
                'admin_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin',
                'uploads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads',
                'vendor_uploads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/vendor-panel/uploads',
                'carousel_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'products_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/products',
                'vendor_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/vendor-panel/uploads/vendors',
                'ads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/ads',
                'notices_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'profiles_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/profiles',
            ];
        }
        
        error_log("VENDOR PANEL - Base URL set to: " . $this->paths['base_url']);
    }
    
    public function get($key) {
        return $this->paths[$key] ?? null;
    }
    
    public function isOnline() {
        return $this->is_online;
    }
    
    public function getBasePath() {
        return $this->base_path;
    }
    
    // Helper method to get image path with fallback
    public function getImagePath($image_name, $type = 'product') {
        if (empty($image_name) || $image_name === 'default.jpg') {
            return $this->get('img') . '/products/placeholder.jpg';
        }
        
        $base_path = '';
        $local_path = '';
        
        switch ($type) {
            case 'product':
                $base_path = $this->get('product_images');
                $local_path = $this->get('products_dir') . '/' . $image_name;
                break;
            case 'vendor':
                $base_path = $this->get('vendor_images');
                $local_path = $this->get('vendor_dir') . '/' . $image_name;
                break;
            case 'ad':
                $base_path = $this->get('ad_images');
                $local_path = $this->get('ads_dir') . '/' . $image_name;
                break;
            case 'notice':
                $base_path = $this->get('notice_images');
                $local_path = $this->get('notices_dir') . '/' . $image_name;
                break;
            case 'media':
                $base_path = $this->get('media_files');
                $local_path = $this->get('media_dir') . '/' . $image_name;
                break;
            case 'media_thumb':
                $base_path = $this->get('media_files') . '/thumbs';
                $local_path = $this->get('media_dir') . '/thumbs/' . $image_name;
                break;
            case 'wallet_qr':
                $base_path = $this->get('wallet_qr_codes');
                $local_path = $this->get('wallet_qr_dir') . '/' . $image_name;
                break;
            case 'profile':
                $base_path = $this->get('profile_images');
                $local_path = $this->get('profiles_dir') . '/' . $image_name;
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
            return $this->get('img') . '/products/placeholder.jpg';
        }
        
        // For online hosting, check if symlink exists and file is accessible
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
        
        // Fallback to placeholder
        return $this->get('img') . '/products/placeholder.jpg';
    }
    
    // Helper method to get file path with fallback
    public function getFilePath($file_name, $type = 'product') {
        if (empty($file_name)) {
            return '';
        }
        
        $base_path = '';
        $local_path = '';
        
        switch ($type) {
            case 'notice':
                $base_path = $this->get('notice_images');
                $local_path = $this->get('notices_dir') . '/' . $file_name;
                break;
            case 'ad':
                $base_path = $this->get('ad_images');
                $local_path = $this->get('ads_dir') . '/' . $file_name;
                break;
            case 'media':
                $base_path = $this->get('media_files');
                $local_path = $this->get('media_dir') . '/' . $file_name;
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
        
        // For online hosting, check symlink first
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
    
    // Helper method to get correct URL for routes
    public function url($path = '') {
        $base_url = $this->get('base_url');
        $path = ltrim($path, '/');
        
        if ($this->is_online) {
            if (empty($path)) {
                return $base_url . '/';
            }
            return $base_url . '/' . $path;
        } else {
            if (empty($path)) {
                return $base_url . '/';
            }
            return $base_url . '/' . $path;
        }
    }
    
    // Helper method for file system paths
    public function filePath($type = '') {
        switch ($type) {
            case 'vendor_storage':
                return $this->get('vendor_panel_dir') . '/storage';
            case 'vendor_uploads':
                return $this->get('vendor_uploads_dir');
            case 'admin_storage':
                return $this->get('admin_dir') . '/storage';
            case 'carousel_uploads':
                return $this->get('carousel_dir');
            case 'product_uploads':
                return $this->get('products_dir');
            case 'vendor_uploads_dir':
                return $this->get('vendor_dir');
            case 'ads_uploads':
                return $this->get('ads_dir');
            case 'notices_uploads':
                return $this->get('notices_dir');
            case 'media_uploads':
                return $this->get('media_dir');
            case 'wallet_qr_uploads':
                return $this->get('wallet_qr_dir');
            case 'profiles_uploads':
                return $this->get('profiles_dir');
            default:
                return $this->get('root_dir');
        }
    }
    
    // Method to verify symlinks are working (for online hosting)
    public function verifySymlinks() {
        if (!$this->is_online) {
            return ['status' => 'local', 'message' => 'Running on local environment'];
        }
        
        $symlinks = [
            'product_images',
            'vendor_images',
            'ad_images',
            'carousel_images',
            'notice_images',
            'media_files',
            'wallet_qr_codes',
            'profile_images'
        ];
        
        $results = [];
        
        foreach ($symlinks as $link) {
            $symlink_path = $this->get('root_dir') . '/' . $link;
            $target_key = str_replace('_', ' ', $link) . '_dir';
            $target_path = $this->get($target_key);
            
            if (is_link($symlink_path)) {
                $results[$link] = [
                    'status' => 'success',
                    'symlink' => $symlink_path,
                    'target' => readlink($symlink_path),
                    'accessible' => file_exists($symlink_path)
                ];
            } else {
                $results[$link] = [
                    'status' => 'warning',
                    'symlink' => $symlink_path,
                    'target' => $target_path,
                    'accessible' => false,
                    'message' => 'Symlink not found - run symlink creation script'
                ];
            }
        }
        
        return $results;
    }
    
    // Debug method to check image paths
    public function debugImagePath($image_name, $type = 'product') {
        $result = [
            'image_name' => $image_name,
            'type' => $type,
            'environment' => $this->is_online ? 'online' : 'local',
            'final_url' => $this->getImagePath($image_name, $type),
            'checks' => []
        ];
        
        $type_key = $type === 'vendor' ? 'vendor_images' : ($type . '_images');
        $base_path = $this->get($type_key);
        $dir_key = $type . 's_dir';
        $local_path = $this->get($dir_key) . '/' . $image_name;
        
        $result['checks']['base_url'] = $base_path;
        $result['checks']['local_path'] = $local_path;
        $result['checks']['file_exists_local'] = file_exists($local_path);
        $result['checks']['is_readable_local'] = is_readable($local_path);
        
        if (!$this->is_online) {
            $result['checks']['file_exists_direct'] = file_exists($local_path);
            $result['checks']['is_readable_direct'] = is_readable($local_path);
        }
        
        return $result;
    }
    
    // Debug current configuration
    public function debugConfig() {
        return [
            'is_online' => $this->is_online,
            'base_path' => $this->base_path,
            'panel_type' => $this->panel_type,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'all_paths' => $this->paths
        ];
    }
}
?>
