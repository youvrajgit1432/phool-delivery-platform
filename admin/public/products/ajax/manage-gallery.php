<?php
/**
 * AJAX endpoint for managing product gallery images
 * Handles: image upload, delete, and set as primary
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering
ob_start();

try {
    require_once '../../../bootstrap/app.php';
    require_once '../../../app/middleware/AuthMiddleware.php';

    // Check authentication
    requireAuth();

    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Verify product exists
$product_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$product_stmt->execute([$product_id]);
if (!$product_stmt->fetch()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

try {
    if ($action === 'upload') {
        // Handle image upload via AJAX
        if (!isset($_FILES['gallery_images'])) {
            throw new Exception('No files uploaded');
        }

        $upload_dir = '../../../storage/uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $uploaded_files = [];
        $gallery_images = $_FILES['gallery_images'];

        for ($i = 0; $i < count($gallery_images['name']); $i++) {
            if ($gallery_images['error'][$i] === UPLOAD_ERR_OK) {
                // Validate file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $file_type = finfo_file($finfo, $gallery_images['tmp_name'][$i]);
                finfo_close($finfo);

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception('Invalid file type for: ' . $gallery_images['name'][$i]);
                }

                // Check file size (5MB max)
                if ($gallery_images['size'][$i] > 5242880) {
                    throw new Exception('File too large: ' . $gallery_images['name'][$i]);
                }

                $file_extension = strtolower(pathinfo($gallery_images['name'][$i], PATHINFO_EXTENSION));
                $filename = 'product_' . $product_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $filename;

                if (move_uploaded_file($gallery_images['tmp_name'][$i], $target_path)) {
                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 0)");
                    if ($stmt->execute([$product_id, $filename])) {
                        $uploaded_files[] = [
                            'id' => $pdo->lastInsertId(),
                            'filename' => $filename
                        ];
                    }
                }
            }
        }

        if (empty($uploaded_files)) {
            throw new Exception('No images were successfully uploaded');
        }

        // Fetch updated gallery images
        $images_stmt = $pdo->prepare("SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
        $images_stmt->execute([$product_id]);
        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => count($uploaded_files) . ' image(s) uploaded successfully',
            'uploaded_count' => count($uploaded_files),
            'images' => $images
        ]);

    } elseif ($action === 'delete') {
        // Handle image deletion
        $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;

        $image_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
        $image_stmt->execute([$image_id, $product_id]);
        $image = $image_stmt->fetch();

        if (!$image) {
            throw new Exception('Image not found');
        }

        // Delete file from server
        $file_path = '../../../storage/uploads/products/' . $image['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete from database
        $delete_stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
        $delete_stmt->execute([$image_id]);

        // Fetch updated gallery images
        $images_stmt = $pdo->prepare("SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
        $images_stmt->execute([$product_id]);
        $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Image deleted successfully',
            'images' => $images
        ]);

    } elseif ($action === 'set_primary') {
        // Handle set as primary image
        $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;

        $pdo->beginTransaction();

        try {
            // Remove primary from all images of this product
            $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$product_id]);

            // Set new primary image
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
            $stmt->execute([$image_id, $product_id]);

            $pdo->commit();

            // Fetch updated gallery images
            $images_stmt = $pdo->prepare("SELECT id, image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
            $images_stmt->execute([$product_id]);
            $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Primary image updated successfully',
                'images' => $images
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
