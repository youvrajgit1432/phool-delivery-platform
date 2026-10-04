<?php
/**
 * URL Helper - Build app-aware absolute URLs
 * Supports both localhost and online hosting with PathConfig
 */

// Create a global cache for PathConfig to avoid repeated requires
$GLOBALS['_pathConfig'] = null;

function getPathConfig() {
    if ($GLOBALS['_pathConfig'] === null) {
        // Require PathConfig only once
        if (!class_exists('PathConfig')) {
            require_once dirname(__FILE__, 3) . '/config/PathConfig.php';
        }
        $GLOBALS['_pathConfig'] = PathConfig::getInstance();
    }
    return $GLOBALS['_pathConfig'];
}

if (!function_exists('vendor_url')) {
    function vendor_url($path = '') {
        try {
            $pathConfig = getPathConfig();
            if ($pathConfig === null) {
                error_log('[URL HELPER ERROR] PathConfig is null');
                return '/' . ltrim($path, '/');
            }
            
            $baseUrl = $pathConfig->get('base_url');
            if (empty($baseUrl)) {
                error_log('[URL HELPER ERROR] base_url is empty');
                return '/' . ltrim($path, '/');
            }
            
            // Ensure path starts with /
            $path = '/' . ltrim($path, '/');
            
            return $baseUrl . $path;
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] vendor_url failed: ' . $e->getMessage());
            error_log('[URL HELPER ERROR] Trace: ' . $e->getTraceAsString());
            // Fallback
            return '/' . ltrim($path, '/');
        } catch (Throwable $t) {
            error_log('[URL HELPER ERROR] vendor_url Throwable: ' . $t->getMessage());
            error_log('[URL HELPER ERROR] Trace: ' . $t->getTraceAsString());
            return '/' . ltrim($path, '/');
        }
    }
}

if (!function_exists('vendor_asset_url')) {
    function vendor_asset_url($path = '') {
        try {
            $pathConfig = getPathConfig();
            $assetsBase = $pathConfig->get('assets');
            
            // Ensure path starts with /
            $path = '/' . ltrim($path, '/');
            
            return $assetsBase . $path;
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] vendor_asset_url failed: ' . $e->getMessage());
            // Fallback
            return '/assets' . ('/' . ltrim($path, '/'));
        }
    }
}

if (!function_exists('vendor_image_url')) {
    function vendor_image_url($image_path, $type = 'product') {
        try {
            $pathConfig = getPathConfig();
            return $pathConfig->getImagePath($image_path, $type);
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] vendor_image_url failed: ' . $e->getMessage());
            return '/uploads/' . ltrim($image_path, '/');
        }
    }
}

if (!function_exists('main_site_url')) {
    function main_site_url($path = '') {
        try {
            $pathConfig = getPathConfig();
            $mainUrl = $pathConfig->get('main_site_url');
            
            $path = '/' . ltrim($path, '/');
            return $mainUrl . $path;
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] main_site_url failed: ' . $e->getMessage());
            return $path;
        }
    }
}

if (!function_exists('admin_panel_url')) {
    function admin_panel_url($path = '') {
        try {
            $pathConfig = getPathConfig();
            $adminUrl = $pathConfig->get('admin_panel_url');
            
            $path = '/' . ltrim($path, '/');
            return $adminUrl . $path;
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] admin_panel_url failed: ' . $e->getMessage());
            return $path;
        }
    }
}

if (!function_exists('delivery_panel_url')) {
    function delivery_panel_url($path = '') {
        try {
            $pathConfig = getPathConfig();
            $deliveryUrl = $pathConfig->get('delivery_panel_url');
            
            $path = '/' . ltrim($path, '/');
            return $deliveryUrl . $path;
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] delivery_panel_url failed: ' . $e->getMessage());
            return $path;
        }
    }
}

if (!function_exists('get_vendor_base_path')) {
    function get_vendor_base_path() {
        try {
            $pathConfig = getPathConfig();
            
            // For localhost: /phool-delivery-platform/vendor-panel
            // For online: / (empty root)
            $baseUrl = $pathConfig->get('base_url');
            
            // Extract just the path part (after domain)
            $urlParts = parse_url($baseUrl);
            return $urlParts['path'] ?? '/';
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] get_vendor_base_path failed: ' . $e->getMessage());
            return '/';
        }
    }
}

/**
 * Convert image path stored in database to proper URL
 * Handles both old hardcoded paths and new relative paths
 * Environment-aware: includes /public/ on localhost, omits on online
 * 
 * Examples:
 * Localhost:
 * - '/assets/uploads/vendor_1/profile.jpg' → 'http://localhost/phool-delivery-platform/vendor-panel/public/assets/uploads/vendor_1/profile.jpg'
 * 
 * Online:
 * - '/assets/uploads/vendor_1/profile.jpg' → 'https://vendor.phooldelivery.example/assets/uploads/vendor_1/profile.jpg'
 */
if (!function_exists('get_image_url')) {
    function get_image_url($storedPath) {
        if (empty($storedPath)) {
            return '';
        }
        
        // If already a full URL (http/https), return as-is
        if (strpos($storedPath, 'http://') === 0 || strpos($storedPath, 'https://') === 0) {
            return $storedPath;
        }
        
        // Remove old hardcoded paths if present
        $storedPath = str_replace('/phool-delivery-platform/vendor-panel/public', '', $storedPath);
        $storedPath = str_replace('/phool-delivery-platform/vendor-panel', '', $storedPath);
        
        // Ensure path starts with /
        $storedPath = '/' . ltrim($storedPath, '/');
        
        // Get PathConfig to check if we're on localhost
        try {
            $pathConfig = getPathConfig();
            $isOnline = $pathConfig->isOnline();
            
            // On localhost, we need to include /public/ in the path
            // because document root is /phool-delivery-platform/, not /phool-delivery-platform/vendor-panel/public/
            if (!$isOnline) {
                // Check if path already contains /public/
                if (strpos($storedPath, '/public/') === false) {
                    // Insert /public/ before the path
                    $storedPath = '/public' . $storedPath;
                }
            }
        } catch (Exception $e) {
            error_log('[URL HELPER ERROR] get_image_url PathConfig failed: ' . $e->getMessage());
            // Fallback: assume we might be on localhost and try adding /public/
            if (strpos($storedPath, '/public/') === false) {
                $storedPath = '/public' . $storedPath;
            }
        }
        
        // Now use vendor_url() to get proper URL with base
        return vendor_url($storedPath);
    }
}
?>
