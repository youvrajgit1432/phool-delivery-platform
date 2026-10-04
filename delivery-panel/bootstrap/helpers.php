<?php
/**
 * Delivery Panel Helper Functions
 * Localhost configuration only
 */

/**
 * Get dynamic base URL
 */
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host;
}

/**
 * Get delivery panel base URL
 */
function getDeliveryPanelUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel';
}

/**
 * Get main site URL
 */
function getMainSiteUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/public_html';
}

/**
 * Get admin panel URL
 */
function getAdminPanelUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/public';
}

/**
 * Get vendor panel URL
 */
function getVendorPanelUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/vendor-panel';
}

/**
 * Get delivery panel assets path
 */
function getDeliveryAssetsPath() {
    $base_url = getDeliveryPanelUrl();
    return $base_url . '/public/assets';
}

/**
 * Get main assets path
 */
function getMainAssetsPath() {
    $base_url = getMainSiteUrl();
    return $base_url . '/assets';
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
        return getDeliveryAssetsPath() . '/img/products/placeholder.jpg';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_path;
}

/**
 * Get profile picture URL
 */
function getProfilePictureUrl($profile_picture) {
    if (empty($profile_picture)) {
        return getDeliveryAssetsPath() . '/img/avatars/default-avatar.png';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/profiles/' . $profile_picture;
}

/**
 * Get carousel image URL
 */
function getCarouselImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/carousel/' . $image_path;
}

/**
 * Get ad image URL
 */
function getAdImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/ads/' . $image_path;
}

/**
 * Get notice file URL
 */
function getNoticeUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/notices/' . $file_path;
}

/**
 * Get media file URL
 */
function getMediaUrl($file_path, $thumb = false) {
    if (empty($file_path)) {
        return '';
    }
    
    $subpath = $thumb ? 'thumbs/' : '';
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/media/' . $subpath . $file_path;
}

/**
 * Get wallet QR code URL
 */
function getWalletQrUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr/' . $file_path;
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
function fileExistsInStorage($file_path, $directory = 'media', $thumb = false) {
    if (empty($file_path)) {
        return false;
    }
    
    // Check in admin storage
    $admin_path = __DIR__ . '/../../admin/storage/uploads/' . $directory . '/';
    if ($thumb && $directory === 'media') {
        $admin_path .= 'thumbs/';
    }
    $admin_path .= $file_path;
    
    return file_exists($admin_path);
}

/**
 * Get default profile picture
 */
function getDefaultProfilePicture() {
    return getDeliveryAssetsPath() . '/img/avatars/default-avatar.png';
}

/**
 * Get default product image
 */
function getDefaultProductImage() {
    return getDeliveryAssetsPath() . '/img/products/placeholder.jpg';
}

/**
 * Generate relative URL for routes
 */
function route($path = '') {
    $base = getDeliveryPanelUrl();
    return $base . '/' . ltrim($path, '/');
}

/**
 * Generate asset URL
 */
function asset($path = '') {
    $assets = getDeliveryAssetsPath();
    return $assets . '/' . ltrim($path, '/');
}

/**
 * Get current environment
 */
function environment() {
    return 'local';
}

/**
 * Debug: Get all configuration
 */
function getConfigDebug() {
    return [
        'environment' => environment(),
        'base_url' => getBaseUrl(),
        'delivery_panel_url' => getDeliveryPanelUrl(),
        'main_site_url' => getMainSiteUrl(),
        'admin_panel_url' => getAdminPanelUrl(),
        'vendor_panel_url' => getVendorPanelUrl(),
        'delivery_assets' => getDeliveryAssetsPath(),
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    ];
}
?>
