<?php
/**
 * AJAX: Delete Product Image (Admin Panel)
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
        error_log('[image-delete] stray output: ' . $buf);
    }
    echo json_encode($data);
    exit;
}

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$db = getDBConnection();
$image_id = (int)($_POST['image_id'] ?? 0);

if (!$image_id) {
    send_json_and_exit(['success' => false, 'message' => 'Image ID required'], 400);
}

try {
    // Get image details
    $stmt = $db->prepare("SELECT id, image_url, product_id FROM vendor_product_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        send_json_and_exit(['success' => false, 'message' => 'Image not found'], 404);
    }

    $product_id = $image['product_id'];

    // If product_id/vendor_id provided in POST, validate ownership
    $postedProductId = (int)($_POST['product_id'] ?? 0);
    $postedVendorId = (int)($_POST['vendor_id'] ?? 0);
    if ($postedProductId && $postedProductId !== (int)$product_id) {
        send_json_and_exit(['success' => false, 'message' => 'Image does not belong to provided product'], 403);
    }
    if ($postedVendorId) {
        // verify product belongs to this vendor
        $pstmt = $db->prepare('SELECT vendor_id FROM vendor_products WHERE id = ? LIMIT 1');
        $pstmt->execute([$product_id]);
        $prod = $pstmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod || (int)$prod['vendor_id'] !== $postedVendorId) {
            send_json_and_exit(['success' => false, 'message' => 'Unauthorized for this vendor/product'], 403);
        }
    }

    // Delete database record
    // Log incoming request for debugging
    error_log('[image-delete] POST: ' . json_encode($_POST));

    $stmt = $db->prepare("DELETE FROM vendor_product_images WHERE id = ?");
    $stmt->execute([$image_id]);
    $deletedRows = $stmt->rowCount();

    // Try to delete file
    if (!empty($image['image_url'])) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $image['image_url'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }

    // If this was primary image, set next image as primary
    $stmt = $db->prepare("
        SELECT image_url FROM vendor_product_images 
        WHERE product_id = ? 
        ORDER BY display_order ASC, id ASC 
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $nextImage = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nextImage) {
        $stmt = $db->prepare("UPDATE vendor_products SET primary_image_url = ? WHERE id = ?");
        $stmt->execute([$nextImage['image_url'], $product_id]);
    } else {
        $stmt = $db->prepare("UPDATE vendor_products SET primary_image_url = NULL WHERE id = ?");
        $stmt->execute([$product_id]);
    }

    send_json_and_exit(['success' => true, 'message' => 'Image deleted successfully', 'deleted_rows' => $deletedRows ?? 0], 200);

} catch (Exception $e) {
    error_log('[image-delete] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error'], 500);
}
