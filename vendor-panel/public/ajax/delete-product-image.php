<?php
/**
 * Delete Product Image - Vendor Panel
 * Delete a vendor product image
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
    $image_name = isset($_POST['image_name']) ? $_POST['image_name'] : null;

    if (!$map_id || !$image_name) {
        throw new Exception('Invalid parameters');
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

    // Remove image from array
    $key = array_search($image_name, $vendorImages);
    if ($key !== false) {
        unset($vendorImages[$key]);
        $vendorImages = array_values($vendorImages); // Reindex
    } else {
        throw new Exception('Image not found in product');
    }

    // Delete physical file
    $filePath = __DIR__ . '/../../uploads/vendor_' . $vendor_id . '/products/' . basename($image_name);
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Failed to delete image file');
        }
    }

    // Update notes
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

    $notesData['vendor_images'] = $vendorImages;
    $notesJson = json_encode($notesData);

    // Update database
    $db->query(
        "UPDATE vendor_product_map SET notes = ?, updated_at = NOW() WHERE id = ?",
        [$notesJson, $map_id]
    );

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Image deleted successfully!'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
