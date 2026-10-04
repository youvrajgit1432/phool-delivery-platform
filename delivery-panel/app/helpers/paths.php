<?php
/**
 * Delivery Panel - Helper Functions
 * Localhost configuration only
 */

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/public';
}

/**
 * Get delivery panel assets path
 */
function getDeliveryAssetsPath() {
    return getBaseUrl() . '/assets';
}

/**
 * Get main website assets path
 */
function getMainAssetsPath() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/public_html/assets';
}

/**
 * Get admin assets path
 */
function getAdminAssetsPath() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host . '/phool-delivery-platform/admin/public/assets';
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
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/products/' . $image_path;
    } else {
        return $protocol . "://" . $host . '/product_images/' . $image_path;
    }
}

/**
 * Get ad image URL
 */
function getAdImageUrl($image_path) {
    if (empty($image_path)) {
        return getDeliveryAssetsPath() . '/img/ads/placeholder.jpg';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/ads/' . $image_path;
    } else {
        return $protocol . "://" . $host . '/ad_images/' . $image_path;
    }
}

/**
 * Get carousel image URL
 */
function getCarouselImageUrl($image_path) {
    if (empty($image_path)) {
        return getDeliveryAssetsPath() . '/img/carousel/placeholder.jpg';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/carousel/' . $image_path;
    } else {
        return $protocol . "://" . $host . '/carousel_images/' . $image_path;
    }
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
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/notices/' . $file_path;
    } else {
        return $protocol . "://" . $host . '/notice_images/' . $file_path;
    }
}

/**
 * Get media file URL (photo/video)
 */
function getMediaUrl($file_path, $thumb = false) {
    if (empty($file_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $path = $thumb ? 'media/thumbs/' : 'media/';
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/' . $path . $file_path;
    } else {
        return $protocol . "://" . $host . '/media_files/' . ($thumb ? 'thumbs/' : '') . $file_path;
    }
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
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/admin/storage/uploads/wallet_qr/' . $file_path;
    } else {
        return $protocol . "://" . $host . '/wallet_qr_codes/' . $file_path;
    }
}

/**
 * Get delivery person profile picture URL
 */
function getDeliveryProfileUrl($profile_picture) {
    if (empty($profile_picture)) {
        return getDeliveryAssetsPath() . '/img/avatars/default-avatar.png';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/storage/uploads/profiles/' . $profile_picture;
    } else {
        return $protocol . "://" . $host . '/delivery-profiles/' . $profile_picture;
    }
}

/**
 * Get delivery document URL
 */
function getDeliveryDocumentUrl($document_path) {
    if (empty($document_path)) {
        return '';
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isLocalhost()) {
        return $protocol . "://" . $host . '/phool-delivery-platform/delivery-panel/storage/uploads/documents/' . $document_path;
    } else {
        return $protocol . "://" . $host . '/delivery-documents/' . $document_path;
    }
}

/**
 * Get API base URL
 */
function getApiBaseUrl() {
    return getBaseUrl() . '/api';
}

/**
 * Get API auth endpoint
 */
function getApiAuthUrl($endpoint = '') {
    $base = getApiBaseUrl() . '/auth';
    return empty($endpoint) ? $base : $base . '/' . ltrim($endpoint, '/');
}

/**
 * Get API delivery endpoint
 */
function getApiDeliveryUrl($endpoint = '') {
    $base = getApiBaseUrl() . '/delivery';
    return empty($endpoint) ? $base : $base . '/' . ltrim($endpoint, '/');
}

/**
 * Get API orders endpoint
 */
function getApiOrdersUrl($endpoint = '') {
    $base = getApiBaseUrl() . '/orders';
    return empty($endpoint) ? $base : $base . '/' . ltrim($endpoint, '/');
}

/**
 * Get API location endpoint
 */
function getApiLocationUrl($endpoint = '') {
    $base = getApiBaseUrl() . '/location';
    return empty($endpoint) ? $base : $base . '/' . ltrim($endpoint, '/');
}

/**
 * Get API earnings endpoint
 */
function getApiEarningsUrl($endpoint = '') {
    $base = getApiBaseUrl() . '/earnings';
    return empty($endpoint) ? $base : $base . '/' . ltrim($endpoint, '/');
}

/**
 * Get base directory for file operations
 */
function getBaseDirectory() {
    if (isLocalhost()) {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel';
    } else {
        return $_SERVER['DOCUMENT_ROOT'];
    }
}

/**
 * Get storage directory path
 */
function getStorageDirectory() {
    $base = getBaseDirectory();
    
    if (isLocalhost()) {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage';
    } else {
        return $_SERVER['DOCUMENT_ROOT'] . '/storage';
    }
}

/**
 * Get logs directory path
 */
function getLogsDirectory() {
    return getStorageDirectory() . '/logs';
}

/**
 * Get cache directory path
 */
function getCacheDirectory() {
    return getStorageDirectory() . '/cache';
}

/**
 * Get uploads directory path
 */
function getUploadsDirectory() {
    $base = getBaseDirectory();
    
    if (isLocalhost()) {
        return $_SERVER['DOCUMENT_ROOT'] . '/phool-delivery-platform/delivery-panel/storage/uploads';
    } else {
        return $_SERVER['DOCUMENT_ROOT'] . '/uploads';
    }
}

/**
 * Get delivery profiles uploads directory
 */
function getDeliveryProfilesDir() {
    return getUploadsDirectory() . '/profiles';
}

/**
 * Get delivery documents uploads directory
 */
function getDeliveryDocumentsDir() {
    return getUploadsDirectory() . '/documents';
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
    
    $uploads_dir = getUploadsDirectory();
    
    if ($thumb && $directory === 'media') {
        $full_path = $uploads_dir . '/media/thumbs/' . $file_path;
    } else {
        $full_path = $uploads_dir . '/' . $directory . '/' . $file_path;
    }
    
    return file_exists($full_path) && is_readable($full_path);
}

/**
 * Get default avatar
 */
function getDefaultAvatar() {
    return getDeliveryAssetsPath() . '/img/avatars/default-avatar.png';
}

/**
 * Build URL for routes
 */
function url($path = '') {
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    
    if (empty($path)) {
        return $base . '/';
    }
    
    return $base . '/' . $path;
}

/**
 * Get main site URL
 */
function mainUrl($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isLocalhost()) {
        $base = $protocol . "://" . $host . '/phool-delivery-platform/public_html';
    } else {
        $base = $protocol . "://" . $host;
    }
    
    $path = ltrim($path, '/');
    
    if (empty($path)) {
        return $base . '/';
    }
    
    return $base . '/' . $path;
}

/**
 * Get admin site URL
 */
function adminUrl($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $protocol . "://" . $host . '/phool-delivery-platform/admin';
    
    $path = ltrim($path, '/');
    
    if (empty($path)) {
        return $base . '/';
    }
    
    return $base . '/' . $path;
}

/**
 * Get delivery panel URL
 */
function deliveryUrl($path = '') {
    return url($path);
}
?>
