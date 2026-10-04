<?php
/**
 * Vendor Panel Helper Functions
 * Dynamic path and URL generation for both localhost and online hosting
 */

/**
 * Check if running on localhost
 */
function isLocalhost() {
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
 * Get dynamic base URL
 */
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host;
}

/**
 * Get vendor panel base URL
 */
function getVendorPanelUrl() {
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/vendor-panel';
    } else {
        return 'https://vendor.phooldelivery.example';
    }
}

/**
 * Get main site URL
 */
function getMainSiteUrl() {
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/public_html';
    } else {
        return 'https://phooldelivery.example';
    }
}

/**
 * Get admin panel URL
 */
function getAdminPanelUrl() {
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/public';
    } else {
        return 'https://admin.phooldelivery.example';
    }
}

/**
 * Get delivery panel URL
 */
function getDeliveryPanelUrl() {
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/public';
    } else {
        return 'https://delivery.phooldelivery.example';
    }
}

/**
 * Get vendor panel assets path
 */
function getVendorAssetsPath() {
    $base_url = getVendorPanelUrl();
    return $base_url . '/public/assets';
}

/**
 * Get main assets path
 */
function getMainAssetsPath() {
    $base_url = getMainSiteUrl();
    
    if (isLocalhost()) {
        return $base_url . '/assets';
    } else {
        return getBaseUrl() . '/assets';
    }
}

/**
 * Get admin assets path
 */
function getAdminAssetsPath() {
    $base_url = getAdminPanelUrl();
    return $base_url . '/assets';
}

/**
 * Get product image URL
 */
function getProductImageUrl($image_path) {
    if (empty($image_path)) {
        return getVendorAssetsPath() . '/img/products/placeholder.jpg';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_path;
    } else {
        return getBaseUrl() . '/product_images/' . $image_path;
    }
}

/**
 * Get vendor image URL
 */
function getVendorImageUrl($image_path) {
    if (empty($image_path)) {
        return getVendorAssetsPath() . '/img/vendors/default.jpg';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/vendor-panel/uploads/vendors/' . $image_path;
    } else {
        return getBaseUrl() . '/vendor_images/' . $image_path;
    }
}

/**
 * Get profile picture URL
 */
function getProfilePictureUrl($profile_picture) {
    if (empty($profile_picture)) {
        return getVendorAssetsPath() . '/img/avatars/default-avatar.png';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/profiles/' . $profile_picture;
    } else {
        return getBaseUrl() . '/profile_images/' . $profile_picture;
    }
}

/**
 * Get carousel image URL
 */
function getCarouselImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/carousel/' . $image_path;
    } else {
        return getBaseUrl() . '/carousel_images/' . $image_path;
    }
}

/**
 * Get ad image URL
 */
function getAdImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/ads/' . $image_path;
    } else {
        return getBaseUrl() . '/ad_images/' . $image_path;
    }
}

/**
 * Get notice file URL
 */
function getNoticeUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/notices/' . $file_path;
    } else {
        return getBaseUrl() . '/notice_images/' . $file_path;
    }
}

/**
 * Get media file URL
 */
function getMediaUrl($file_path, $thumb = false) {
    if (empty($file_path)) {
        return '';
    }
    
    $subpath = $thumb ? 'thumbs/' : '';
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/media/' . $subpath . $file_path;
    } else {
        return getBaseUrl() . '/media_files/' . $subpath . $file_path;
    }
}

/**
 * Get wallet QR code URL
 */
function getWalletQrUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr/' . $file_path;
    } else {
        return getBaseUrl() . '/wallet_qr_codes/' . $file_path;
    }
}

/**
 * Get QR code URL
 */
function getQrCodeUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    if (isLocalhost()) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/qr_codes/' . $file_path;
    } else {
        return getBaseUrl() . '/qr_codes/' . $file_path;
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Check if file exists in storage
 */
function fileExistsInStorage($file_path, $directory = 'products', $thumb = false) {
    if (empty($file_path)) {
        return false;
    }
    
    if (isLocalhost()) {
        // Check in vendor-panel uploads
        $vendor_path = __DIR__ . '/../../vendor-panel/uploads/' . $directory . '/' . $file_path;
        
        // Check in admin storage
        $admin_path = __DIR__ . '/../../admin/storage/uploads/' . $directory . '/';
        if ($thumb && $directory === 'media') {
            $admin_path .= 'thumbs/';
        }
        $admin_path .= $file_path;
        
        return file_exists($vendor_path) || file_exists($admin_path);
    }
    
    // For online, we can't directly check file existence
    // Return true and let the browser handle 404s
    return true;
}

/**
 * Get file path for local file operations
 */
function getLocalFilePath($file_path, $directory = 'products') {
    if (isLocalhost()) {
        // First check vendor panel uploads
        $vendor_path = __DIR__ . '/../../vendor-panel/uploads/' . $directory . '/' . $file_path;
        if (file_exists($vendor_path)) {
            return $vendor_path;
        }
        
        // Then check admin storage
        $admin_path = __DIR__ . '/../../admin/storage/uploads/' . $directory . '/' . $file_path;
        if (file_exists($admin_path)) {
            return $admin_path;
        }
    }
    
    return null;
}

/**
 * Get upload directory path
 */
function getUploadDirectory($directory = 'products') {
    if (isLocalhost()) {
        $path = __DIR__ . '/../../vendor-panel/uploads/' . $directory;
        
        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        
        return $path;
    }
    
    return null; // Online: use configured upload path
}

/**
 * Get default profile picture
 */
function getDefaultProfilePicture() {
    return getVendorAssetsPath() . '/img/avatars/default-avatar.png';
}

/**
 * Get default product image
 */
function getDefaultProductImage() {
    return getVendorAssetsPath() . '/img/products/placeholder.jpg';
}

/**
 * Generate relative URL for routes
 */
function route($path = '') {
    $base = getVendorPanelUrl();
    return $base . '/' . ltrim($path, '/');
}

/**
 * Generate asset URL
 */
function asset($path = '') {
    $assets = getVendorAssetsPath();
    return $assets . '/' . ltrim($path, '/');
}

/**
 * Get current environment
 */
function environment() {
    return isLocalhost() ? 'local' : 'production';
}

/**
 * Debug: Get all configuration
 */
function getConfigDebug() {
    return [
        'environment' => environment(),
        'base_url' => getBaseUrl(),
        'vendor_panel_url' => getVendorPanelUrl(),
        'main_site_url' => getMainSiteUrl(),
        'admin_panel_url' => getAdminPanelUrl(),
        'delivery_panel_url' => getDeliveryPanelUrl(),
        'vendor_assets' => getVendorAssetsPath(),
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    ];
}
?>
