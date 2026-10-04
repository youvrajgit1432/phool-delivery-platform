<?php
/**
 * Vendor Profile Image Upload API
 * Path: /phool-delivery-platform/vendor-panel/public/ajax/upload-vendor-profile-image.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['vendor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../bootstrap/autoload.php';
require_once '../../bootstrap/app.php';

$vendor_id = $_SESSION['vendor_id'];

try {
    if (!isset($_FILES['profile_image'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images allowed']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit;
    }

    $upload_dir = __DIR__ . '/../assets/uploads/vendor_' . $vendor_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . time() . '.' . $file_ext;
    $filepath = $upload_dir . $filename;
    
    // Store relative path in database (not full URL)
    // get_image_url() helper will convert this to proper URL when displaying
    $file_path = '/assets/uploads/vendor_' . $vendor_id . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit;
    }

    // Update vendor profile image in database with relative path
    $dbConfig = $GLOBALS['config']['database'] ?? [];
    $db = \App\Database\Connection::getInstance($dbConfig);
    
    $db->query(
        'UPDATE vendors SET profile_image_url = ? WHERE id = ?',
        [$file_path, $vendor_id]
    );
    
    // For response, use get_image_url() to show the proper URL
    require_once __DIR__ . '/../../app/helpers/url.php';
    $display_url = get_image_url($file_path);

    echo json_encode([
        'success' => true,
        'message' => 'Profile image uploaded successfully',
        'image_url' => $display_url
    ]);


} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
