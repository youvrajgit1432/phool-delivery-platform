<?php
class PathConfig {
    private static $instance = null;
    private $base_path;
    private $is_online;
    private $paths = [];
    
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
        
        error_log("DEBUG - Document Root: " . $document_root);
        error_log("DEBUG - HTTP Host: " . $http_host);
        error_log("DEBUG - Server Name: " . $server_name);
        
        // Check if we're on online hosting - FIXED: More specific checks
        $this->is_online = (
            strpos($document_root, '/home2/phooldel') !== false ||
            strpos($http_host, 'phooldelivery.example') !== false ||
            strpos($http_host, 'admin.phooldelivery.example') !== false ||
            strpos($server_name, 'phooldelivery.example') !== false ||
            (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1')
        );
        
        // FIXED: Force localhost detection
        if (strpos($http_host, 'localhost') !== false || 
            strpos($http_host, '127.0.0.1') !== false ||
            strpos($server_name, 'localhost') !== false ||
            (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')) {
            $this->is_online = false;
        }
        
        error_log("Environment detected: " . ($this->is_online ? 'ONLINE' : 'LOCAL'));
    }
    
    private function setupPaths() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($this->is_online) {
            // Online hosting paths - FIXED: Correct structure for cPanel hosting
            $this->base_path = $protocol . '://' . $host;
            
            // On online hosting, public_html IS the web root
            // So /assets is directly under the domain
            $this->paths = [
                'base_url' => $this->base_path,
                'assets' => $this->base_path . '/assets',  // https://domain.com/assets
                'admin' => 'https://admin.phooldelivery.example',
                'public_html' => $this->base_path,
                
                // Use symlinked directories in public_html
                'product_images' => $this->base_path . '/product_images',
                'ads_images' => $this->base_path . '/ad_images',
                'carousel_images' => $this->base_path . '/carousel_images',
                'notices_images' => $this->base_path . '/notice_images',
                'media' => $this->base_path . '/media_files',
                'wallet_qr' => $this->base_path . '/wallet_qr_codes',
                'profiles' => $this->base_path . '/profile_images',
                
                // File system paths pointing to admin storage
                'root_dir' => $_SERVER['DOCUMENT_ROOT'],
                'admin_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin',
                'uploads_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads',
                'carousel_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/carousel',
                'products_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/products',
                'ads_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/ads',
                'notices_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/notices',
                'media_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/media',
                'wallet_qr_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/wallet_qr',
                'profiles_dir' => dirname($_SERVER['DOCUMENT_ROOT']) . '/admin/storage/uploads/profiles'
            ];
        } else {
            // Localhost paths (XAMPP) - FIXED: Correct local paths
            $this->base_path = $protocol . '://' . $host . '/phool-delivery-platform';
            
            $this->paths = [
                'base_url' => $protocol . '://' . $host . '/phool-delivery-platform/public_html',
                'assets' => $protocol . '://' . $host . '/phool-delivery-platform/public_html/assets',
                'admin' => $protocol . '://' . $host . '/phool-delivery-platform/admin',
                'public_html' => $protocol . '://' . $host . '/phool-delivery-platform/public_html',
                
                // Direct paths to admin storage for local development
                'product_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/products',
                'ads_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/ads',
                'carousel_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'notices_images' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'profiles' => $protocol . '://' . $host . '/phool-delivery-platform/admin/storage/uploads/profiles',
                
                // File system paths - FIXED: Correct local filesystem paths
                'root_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform',
                'admin_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin',
                'uploads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads',
                'carousel_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/carousel',
                'products_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/products',
                'ads_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/ads',
                'notices_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/notices',
                'media_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/media',
                'wallet_qr_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/wallet_qr',
                'profiles_dir' => $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/profiles'
            ];
        }
        
        error_log("Base URL set to: " . $this->paths['base_url']);
        error_log("Assets URL set to: " . $this->paths['assets']);
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
            case 'profile':
                $base_path = $this->get('profiles');
                $local_path = $this->get('profiles_dir') . '/' . $image_name;
                break;
            default:
                $base_path = $this->get('product_images');
                $local_path = $this->get('products_dir') . '/' . $image_name;
        }
        
        // For local development - directly check if file exists
        if (!$this->is_online) {
            // For local XAMPP, check if the file exists in the admin storage
            if (file_exists($local_path) && is_readable($local_path)) {
                return $base_path . '/' . $image_name;
            }
            
            // Additional check: try direct path from document root
            $direct_path = $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_name;
            if (file_exists($direct_path) && is_readable($direct_path)) {
                return $this->get('product_images') . '/' . $image_name;
            }
            
            // Fallback to placeholder
            return $this->get('assets') . '/img/products/placeholder.jpg';
        }
        
        // For online hosting, check if symlink exists and file is accessible
        if ($this->is_online) {
            $symlink_path = $this->get('root_dir') . '/' . basename($base_path) . '/' . $image_name;
            if (file_exists($symlink_path) && is_readable($symlink_path)) {
                return $base_path . '/' . $image_name;
            }
        }
        
        // Check if file exists in original location (for local or fallback)
        if (file_exists($local_path)) {
            return $base_path . '/' . $image_name;
        }
        
        // Fallback to placeholder
        return $this->get('assets') . '/img/products/placeholder.jpg';
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
    
    // Helper method to get correct URL for routes - FIXED: Better URL handling for online hosting
    public function url($path = '') {
        $base_url = $this->get('base_url');
        $path = ltrim($path, '/');
        
        if ($this->is_online) {
            // Online: ensure proper absolute URLs
            if (empty($path)) {
                return $base_url . '/';
            }
            return $base_url . '/' . $path;
        } else {
            // Local: handle public_html paths correctly
            if (strpos($path, 'public_html/') === 0) {
                $path = substr($path, 12);
            }
            if (empty($path)) {
                return $base_url . '/';
            }
            return $base_url . '/' . $path;
        }
    }
    
    // Helper method for file system paths
    public function filePath($type = '') {
        switch ($type) {
            case 'admin_storage':
                return $this->get('admin_dir') . '/storage';
            case 'carousel_uploads':
                return $this->get('carousel_dir');
            case 'product_uploads':
                return $this->get('products_dir');
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
    
    // Method to verify symlinks are working
    public function verifySymlinks() {
        if (!$this->is_online) {
            return ['status' => 'local', 'message' => 'Running on local environment'];
        }
        
        $symlinks = ['product_images', 'ad_images', 'notice_images', 'media_files', 'wallet_qr_codes', 'profile_images'];
        $results = [];
        
        foreach ($symlinks as $link) {
            $symlink_path = $this->get('root_dir') . '/' . $link;
            $target_path = $this->get($link . '_dir');
            
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
                    'target' => $target_path,
                    'accessible' => false
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
        
        $base_path = $this->get($type . '_images');
        $local_path = $this->get($type . 's_dir') . '/' . $image_name;
        
        $result['checks']['base_url'] = $base_path;
        $result['checks']['local_path'] = $local_path;
        $result['checks']['file_exists_local'] = file_exists($local_path);
        $result['checks']['is_readable_local'] = is_readable($local_path);
        
        if (!$this->is_online) {
            $direct_path = $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_name;
            $result['checks']['direct_path'] = $direct_path;
            $result['checks']['file_exists_direct'] = file_exists($direct_path);
            $result['checks']['is_readable_direct'] = is_readable($direct_path);
        }
        
        return $result;
    }
    
    // NEW: Method to debug current configuration
    public function debugConfig() {
        return [
            'is_online' => $this->is_online,
            'base_path' => $this->base_path,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'all_paths' => $this->paths
        ];
    }
}
?>