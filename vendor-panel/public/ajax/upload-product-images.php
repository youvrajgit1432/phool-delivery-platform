<?php
/**
 * Upload Product Images - Vendor Panel
 * Handle vendor product image uploads
 */

ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['vendor_id'])) {
        throw new Exception('Unauthorized');
    }

    $vendor_id = (int)$_SESSION['vendor_id'];
    $map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : null;

    if (!$map_id) {
        throw new Exception('Invalid mapping ID');
    }

    // Get database config
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = \App\Database\Connection::getInstance($dbConfig);

    // Verify ownership
    $check = $db->query(
        "SELECT id FROM vendor_product_map WHERE id = ? AND vendor_id = ?",
        [$map_id, $vendor_id]
    );

    if (!$check || $check->rowCount() === 0) {
        throw new Exception('Product mapping not found');
    }

    // Get current notes/images
    $product = $db->query(
        "SELECT notes FROM vendor_product_map WHERE id = ?",
        [$map_id]
    )->fetch(\PDO::FETCH_ASSOC);

    $vendorImages = [];
    if (!empty($product['notes'])) {
        try {
            $notesData = json_decode($product['notes'], true);
            if (isset($notesData['vendor_images'])) {
                $vendorImages = $notesData['vendor_images'];
            }
        } catch (Exception $e) {
            // Ignore
        }
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../../uploads/vendor_' . $vendor_id . '/products/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Process uploaded files
    $uploadedFiles = [];
    if (isset($_FILES['images'])) {
        $files = is_array($_FILES['images']['name']) ? $_FILES['images'] : ['images' => $_FILES['images']];
        
        $fileCount = is_array($_FILES['images']['name']) ? count($_FILES['images']['name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = is_array($_FILES['images']['name']) ? $_FILES['images']['name'][$i] : $_FILES['images']['name'];
            $fileTmp = is_array($_FILES['images']['tmp_name']) ? $_FILES['images']['tmp_name'][$i] : $_FILES['images']['tmp_name'];
            $fileSize = is_array($_FILES['images']['size']) ? $_FILES['images']['size'][$i] : $_FILES['images']['size'];
            $fileError = is_array($_FILES['images']['error']) ? $_FILES['images']['error'][$i] : $_FILES['images']['error'];

            // Validate file
            if ($fileError !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $fileName);
            }

            if ($fileSize > 5 * 1024 * 1024) {
                throw new Exception('File too large: ' . $fileName . ' (max 5MB)');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmp);
            finfo_close($finfo);

            if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
                throw new Exception('Invalid file type: ' . $fileName);
            }

            // Generate unique filename
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'vendor_prod_' . $map_id . '_' . uniqid() . '.' . $ext;
            $filePath = $uploadDir . $newFileName;

            // Move file
            if (!move_uploaded_file($fileTmp, $filePath)) {
                throw new Exception('Failed to save file: ' . $fileName);
            }

            // Resize image if needed (optional)
            $uploadedFiles[] = $newFileName;
            $vendorImages[] = $newFileName;
        }
    }

    // Update notes with new images
    $notesData = [];
    if (!empty($product['notes'])) {
        try {
            $notesData = json_decode($product['notes'], true);
            if (!is_array($notesData)) {
                $notesData = [];
            }
        } catch (Exception $e) {
            $notesData = [];
        }
    }

    $notesData['vendor_images'] = array_unique($vendorImages);
    $notesJson = json_encode($notesData);

    // Update database
    $db->query(
        "UPDATE vendor_product_map SET notes = ?, updated_at = NOW() WHERE id = ?",
        [$notesJson, $map_id]
    );

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => count($uploadedFiles) . ' image(s) uploaded successfully!',
        'files' => $uploadedFiles
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
