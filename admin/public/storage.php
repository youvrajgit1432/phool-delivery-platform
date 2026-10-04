<?php
// admin/public/storage.php - File serving script for storage access
require_once '../bootstrap/app.php';

// Get the requested file path from URL
$requested_file = $_GET['file'] ?? '';

if (empty($requested_file)) {
    http_response_code(400);
    die('File parameter required');
}

// Security: Prevent directory traversal
$requested_file = str_replace(['../', '..\\'], '', $requested_file);
$requested_file = ltrim($requested_file, '/');

// Define allowed storage directories and their subdirectories
$allowed_dirs = [
    'products',
    'buyers', 
    'carousel',
    'expenses',
    'media',
    'media/thumbs',
    'notices',
    'payment_screenshots',
    'profiles', // Added profiles directory
    'wallet_qr',
    'ads',
    'qr_codes'
];

// Extract directory path and filename
$path_parts = explode('/', $requested_file);
$directory_path = '';
$filename = '';

if (count($path_parts) >= 2) {
    // Handle subdirectories like media/thumbs
    $filename = array_pop($path_parts);
    $directory_path = implode('/', $path_parts);
} else {
    http_response_code(403);
    die('Access denied - invalid path');
}

// Validate directory path
if (!in_array($directory_path, $allowed_dirs) || empty($filename)) {
    http_response_code(403);
    die('Access denied - directory not allowed or filename missing');
}

// Build full file path - FIXED: Check both admin storage and main uploads
$admin_storage_path = "../storage/uploads/{$directory_path}/{$filename}";
$main_uploads_path = "../../uploads/{$directory_path}/{$filename}"; // For profiles in main uploads

// Security: Check if file exists in either location
$full_path = null;

// First check admin storage
if (file_exists($admin_storage_path)) {
    $full_path = realpath($admin_storage_path);
    $storage_base = realpath("../storage/uploads");
} 
// Then check main uploads (for profiles)
else if (file_exists($main_uploads_path)) {
    $full_path = realpath($main_uploads_path);
    $storage_base = realpath("../../uploads");
}

// Security: Ensure the file is within the allowed directories
if (!$full_path || !$storage_base || strpos($full_path, $storage_base) !== 0) {
    http_response_code(404);
    die('File not found - security violation');
}

// Check if file exists
if (!file_exists($full_path)) {
    http_response_code(404);
    die('File not found');
}

// Set appropriate content type
$extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo',
    'pdf' => 'application/pdf'
];

$content_type = $mime_types[$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: public, max-age=3600');

// Output the file
readfile($full_path);
exit;
?>