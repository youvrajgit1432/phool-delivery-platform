<?php
// admin/bootstrap/helpers.php

function isLocalhost() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // Check for common localhost patterns
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

// Dynamic base URL function
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . "://" . $host;
}

// Dynamic assets path function
function getAdminAssetsPath() {
    $base_url = getBaseUrl();
    
    if (isLocalhost()) {
        // Local development path
        return $base_url . '/phool-delivery-platform/admin/public/assets';
    } else {
        // Production path
        return $base_url . '/assets';
    }
}

function getMainAssetsPath() {
    $base_url = getBaseUrl();
    
    if (isLocalhost()) {
        // Local development path
        return $base_url . '/phool-delivery-platform/public/assets';
    } else {
        // Production path
        return $base_url . '/assets';
    }
}

// Storage URL function for both local and production
function getStorageUrl($path = '') {
    $base_url = getBaseUrl();
    
    if (isLocalhost()) {
        // Local development - direct file access
        return $base_url . '/phool-delivery-platform/admin/storage/uploads/' . ltrim($path, '/');
    } else {
        // Production - use file serving script
        if (!empty($path)) {
            return $base_url . '/storage.php?file=' . urlencode(ltrim($path, '/'));
        }
        return $base_url . '/storage.php';
    }
}

// Profile picture URL - FIXED: Now checks both locations
function getProfilePicturePath($profile_picture) {
    if (empty($profile_picture)) {
        return '';
    }
    
    return getStorageUrl('profiles/' . $profile_picture);
}

// Product image URL
function getProductImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    return getStorageUrl('products/' . $image_path);
}

// Carousel image URL
function getCarouselImageUrl($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    return getStorageUrl('carousel/' . $image_path);
}

// Expense bill/receipt URL
function getExpenseUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('expenses/' . $file_path);
}

// Media URL
function getMediaUrl($file_path, $thumb = false) {
    if (empty($file_path)) {
        return '';
    }
    
    if ($thumb) {
        return getStorageUrl('media/thumbs/' . $file_path);
    }
    
    return getStorageUrl('media/' . $file_path);
}

// Profile picture URL - FIXED: Uses the main function
function getProfilePictureUrl($profile_picture) {
    return getProfilePicturePath($profile_picture);
}

// Notice URL function
function getNoticeUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('notices/' . $file_path);
}

// Ads URL function
function getAdUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('ads/' . $file_path);
}

// Wallet QR URL function
function getWalletQrUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('wallet_qr/' . $file_path);
}

// QR Codes URL function
function getQrCodeUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('qr_codes/' . $file_path);
}

// Buyers URL function
function getBuyerUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('buyers/' . $file_path);
}

// Payment Screenshots URL function
function getPaymentScreenshotUrl($file_path) {
    if (empty($file_path)) {
        return '';
    }
    
    return getStorageUrl('payment_screenshots/' . $file_path);
}

// Helper function to format file size - KEEP ONLY ONE VERSION
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Helper function to check if file exists in storage - FIXED: Check both locations
function fileExistsInStorage($file_path, $directory = 'media', $thumb = false) {
    if (empty($file_path)) {
        return false;
    }
    
    $admin_base_path = '../storage/uploads/';
    $main_base_path = '../../uploads/';
    
    if ($thumb && $directory === 'media') {
        $admin_full_path = $admin_base_path . 'media/thumbs/' . $file_path;
        $main_full_path = $main_base_path . 'media/thumbs/' . $file_path;
    } else {
        $admin_full_path = $admin_base_path . $directory . '/' . $file_path;
        $main_full_path = $main_base_path . $directory . '/' . $file_path;
    }
    
    // Check both locations
    return file_exists($admin_full_path) || file_exists($main_full_path);
}

// Helper function to get default profile picture
function getDefaultProfilePicture() {
    return getAdminAssetsPath() . '/images/default-avatar.png';
}
?>