<?php
/**
 * Vendor Image Upload Handler (Admin)
 * Handles profile, logo, banner, and gallery image uploads
 */

header('Content-Type: application/json');
require_once '../../bootstrap/app.php';

$db = getDBConnection();

try {
    $vendor_id = $_POST['vendor_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$vendor_id || !$action) {
        throw new Exception('Missing required parameters');
    }

    // Verify vendor exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Vendor not found');
    }

    $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
    $basePrefix = $isLocalhost ? '/phool-delivery-platform' : '';
    $uploadDir = __DIR__ . '/../../../vendor-panel/public/assets/uploads/vendor_' . $vendor_id;
    $webPath = $basePrefix . '/vendor-panel/public/assets/uploads/vendor_' . $vendor_id;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Helper function to save image
    function saveImage($file, $uploadDir, $fileName = null, $webPath = '') {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size exceeds 5MB limit');
        }
        if (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            throw new Exception('Invalid image type: ' . $file['type']);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $savedName = $fileName ?? uniqid('img_') . '.' . $ext;
        $dest = $uploadDir . '/' . $savedName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Failed to move uploaded file');
        }

        return $webPath . '/' . $savedName;
    }

    // Handle store images upload
    if ($action === 'upload_store_images') {
        $updateData = [];

        // Profile image
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $updateData['profile_image_url'] = saveImage($_FILES['profile_image'], $uploadDir, 'profile.jpg', $webPath);
        }

        // Logo
        if (!empty($_FILES['logo_image']['tmp_name'])) {
            $updateData['logo_url'] = saveImage($_FILES['logo_image'], $uploadDir, 'logo.png', $webPath);
        }

        // Banner
        if (!empty($_FILES['banner_image']['tmp_name'])) {
            $updateData['banner_url'] = saveImage($_FILES['banner_image'], $uploadDir, 'banner.jpg', $webPath);
        }

        if (empty($updateData)) {
            throw new Exception('No files to upload');
        }

        // Update vendor record
        $sets = [];
        $vals = [];
        foreach ($updateData as $k => $v) {
            $sets[] = "$k = ?";
            $vals[] = $v;
        }
        $vals[] = $vendor_id;
        $sql = "UPDATE vendors SET " . implode(', ', $sets) . " WHERE id = ?";
        $ustmt = $db->prepare($sql);
        $ustmt->execute($vals);

        echo json_encode([
            'success' => true,
            'message' => 'Store images updated successfully'
        ]);
    }

    // Handle gallery images upload
    elseif ($action === 'upload_gallery_images') {
        if (empty($_FILES['gallery_images']) || empty($_FILES['gallery_images']['tmp_name'])) {
            throw new Exception('No gallery images selected');
        }

        // Check current gallery count
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM vendor_gallery_images WHERE vendor_id = ?');
        $stmt->execute([$vendor_id]);
        $currentCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentImages = $currentCount['cnt'] ?? 0;
        $newImages = count(array_filter($_FILES['gallery_images']['tmp_name']));

        if ($currentImages + $newImages > 10) {
            throw new Exception('Cannot exceed 10 total gallery images. Current: ' . $currentImages);
        }

        $uploadedCount = 0;
        $fileCount = count($_FILES['gallery_images']['tmp_name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['gallery_images']['tmp_name'][$i])) {
                continue;
            }

            try {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$i],
                    'type' => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error' => $_FILES['gallery_images']['error'][$i],
                    'size' => $_FILES['gallery_images']['size'][$i]
                ];

                $imagePath = saveImage($file, $uploadDir, null, $webPath);

                // Insert into database
                $stmt = $db->prepare("
                    INSERT INTO vendor_gallery_images 
                    (vendor_id, image_url, alt_text, description, image_type, display_order, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $vendor_id,
                    $imagePath,
                    $_POST['image_descriptions'][$i] ?? null,
                    $_POST['image_descriptions'][$i] ?? null,
                    $_POST['image_types'][$i] ?? 'gallery',
                    $currentImages + $uploadedCount
                ]);

                $uploadedCount++;
            } catch (Exception $e) {
                error_log('[vendor-image-upload] Error saving gallery image ' . ($i + 1) . ': ' . $e->getMessage());
                continue;
            }
        }

        if ($uploadedCount === 0) {
            throw new Exception('Failed to upload any images');
        }

        echo json_encode([
            'success' => true,
            'message' => $uploadedCount . ' gallery image(s) uploaded successfully'
        ]);
    }

    // Handle gallery image deletion
    elseif ($action === 'delete_gallery_image') {
        $imageId = $_POST['image_id'] ?? null;
        if (!$imageId) {
            throw new Exception('Missing image ID');
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT image_url FROM vendor_gallery_images WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$imageId, $vendor_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            throw new Exception('Image not found or not owned by this vendor');
        }

        // Delete file (use uploadDir and basename)
        $filePath = $uploadDir . '/' . basename($image['image_url']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $stmt = $db->prepare("DELETE FROM vendor_gallery_images WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$imageId, $vendor_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }

    else {
        throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
