<?php
/**
 * AJAX: Image Upload Handler
 * Handles profile, store images, and gallery image uploads
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

function send_json_and_exit($data, $status = 200)
{
    http_response_code($status);
    $buf = ob_get_clean();
    if (!empty($buf)) {
        error_log('[image-upload stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
}

// Ensure bootstrap files exist
$bootstrapAutoload = __DIR__ . '/../../bootstrap/autoload.php';
$bootstrapApp = __DIR__ . '/../../bootstrap/app.php';
if (!file_exists($bootstrapAutoload) || !file_exists($bootstrapApp)) {
    send_json_and_exit(['success' => false, 'message' => 'Server configuration error'], 500);
}

require_once $bootstrapAutoload;
require_once $bootstrapApp;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
use App\Database\Connection;

// Helper to get PathConfig instance with caching
function getPathConfig() {
    if ($GLOBALS['_pathConfig'] === null) {
        if (!class_exists('PathConfig')) {
            require_once dirname(__FILE__, 4) . '/config/PathConfig.php';
        }
        $GLOBALS['_pathConfig'] = PathConfig::getInstance();
    }
    return $GLOBALS['_pathConfig'];
}
$GLOBALS['_pathConfig'] = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$vendorId = $_POST['vendor_id'] ?? $_SESSION['vendor_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$vendorId || !$action) {
    send_json_and_exit(['success' => false, 'message' => 'Missing required parameters'], 400);
}

try {
    $dbConfig = $GLOBALS['config']['database'] ?? [];
    $db = Connection::getInstance($dbConfig);

    // Verify vendor exists
    $vendorCheck = $db->select('vendors', ['id' => $vendorId], 1);
    if (empty($vendorCheck)) {
        send_json_and_exit(['success' => false, 'message' => 'Vendor not found'], 404);
    }

    $uploadDir = __DIR__ . '/../assets/uploads/vendor_' . $vendorId;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Determine if we're on localhost or online
    // This will be used to generate correct relative paths
    $pathConfig = getPathConfig();
    $isOnline = $pathConfig->isOnline();

    // Helper function to validate and save image
    // Returns relative path that can be used with vendor_url() helper
    function saveImage($file, $uploadDir, $fileName = null, $isOnline = false) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size exceeds 5MB limit');
        }
        if (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            throw new Exception('Invalid image type');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $savedName = $fileName ?? uniqid('img_') . '.' . $ext;
        $dest = $uploadDir . '/' . $savedName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Return relative path that works on both environments
        // Online: /assets/uploads/vendor_X/filename
        // Localhost: /public/assets/uploads/vendor_X/filename (get_image_url will handle this)
        $relativePath = '/assets/uploads/vendor_' . $_SESSION['vendor_id'] . '/' . $savedName;
        return $relativePath;
    }

    // Handle store images upload
    if ($action === 'upload_store_images') {
        $updateData = [];

        // Profile image
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $updateData['profile_image_url'] = saveImage($_FILES['profile_image'], $uploadDir, 'profile.jpg', $isOnline);
        }

        // Logo
        if (!empty($_FILES['logo_image']['tmp_name'])) {
            $updateData['logo_url'] = saveImage($_FILES['logo_image'], $uploadDir, 'logo.png', $isOnline);
        }

        // Banner
        if (!empty($_FILES['banner_image']['tmp_name'])) {
            $updateData['banner_url'] = saveImage($_FILES['banner_image'], $uploadDir, 'banner.jpg', $isOnline);
        }

        if (empty($updateData)) {
            send_json_and_exit(['success' => false, 'message' => 'No files to upload'], 400);
        }

        $db->update('vendors', $updateData, ['id' => $vendorId]);
        send_json_and_exit(['success' => true, 'message' => 'Store images updated successfully'], 200);
    }

    // Handle gallery images upload
    elseif ($action === 'upload_gallery_images') {
        if (empty($_FILES['gallery_images']) || empty($_FILES['gallery_images']['tmp_name'])) {
            send_json_and_exit(['success' => false, 'message' => 'No gallery images selected'], 400);
        }

        // Check current gallery count
        $currentCount = $db->query(
            'SELECT COUNT(*) as cnt FROM vendor_gallery_images WHERE vendor_id = ?',
            [$vendorId]
        )->fetch();

        $currentImages = $currentCount['cnt'] ?? 0;
        $newImages = count(array_filter($_FILES['gallery_images']['tmp_name']));

        if ($currentImages + $newImages > 10) {
            send_json_and_exit(['success' => false, 'message' => 'Cannot exceed 10 total gallery images'], 400);
        }

        $uploadedCount = 0;
        $fileCount = count($_FILES['gallery_images']['tmp_name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['gallery_images']['tmp_name'][$i])) {
                continue;
            }

            $file = [
                'name' => $_FILES['gallery_images']['name'][$i],
                'type' => $_FILES['gallery_images']['type'][$i],
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                'error' => $_FILES['gallery_images']['error'][$i],
                'size' => $_FILES['gallery_images']['size'][$i]
            ];

            try {
                $imagePath = saveImage($file, $uploadDir, null, $isOnline);
                
                $galleryData = [
                    'vendor_id' => $vendorId,
                    'image_url' => $imagePath,
                    'alt_text' => $_POST['image_descriptions'][$i] ?? null,
                    'description' => $_POST['image_descriptions'][$i] ?? null,
                    'image_type' => $_POST['image_types'][$i] ?? 'gallery',
                    'display_order' => $currentImages + $uploadedCount
                ];

                $db->insert('vendor_gallery_images', $galleryData);
                $uploadedCount++;
            } catch (Exception $e) {
                error_log('[image-upload] Error saving gallery image ' . ($i + 1) . ': ' . $e->getMessage());
                continue;
            }
        }

        if ($uploadedCount === 0) {
            send_json_and_exit(['success' => false, 'message' => 'Failed to upload any images'], 400);
        }

        send_json_and_exit(['success' => true, 'message' => $uploadedCount . ' gallery image(s) uploaded successfully'], 200);
    }

    // Handle gallery image deletion
    elseif ($action === 'delete_gallery_image') {
        $imageId = $_POST['image_id'] ?? null;
        if (!$imageId) {
            send_json_and_exit(['success' => false, 'message' => 'Missing image ID'], 400);
        }

        // Verify ownership
        $image = $db->select('vendor_gallery_images', ['id' => $imageId, 'vendor_id' => $vendorId], 1);
        if (empty($image)) {
            send_json_and_exit(['success' => false, 'message' => 'Image not found'], 404);
        }

        // Delete file (use uploadDir and basename to avoid path mismatches)
        $filePath = $uploadDir . '/' . basename($image['image_url']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $db->delete('vendor_gallery_images', ['id' => $imageId]);
        send_json_and_exit(['success' => true, 'message' => 'Image deleted successfully'], 200);
    }

    else {
        send_json_and_exit(['success' => false, 'message' => 'Unknown action'], 400);
    }

} catch (Throwable $e) {
    error_log('[image-upload] Error: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
?>
