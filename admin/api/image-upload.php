<?php
/**
 * AJAX: Upload Product Images (Admin Panel)
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
        error_log('[image-upload] stray output: ' . $buf);
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
$product_id = (int)($_POST['product_id'] ?? 0);
$vendor_id = (int)($_POST['vendor_id'] ?? 0);

if (!$product_id || !$vendor_id) {
    send_json_and_exit(['success' => false, 'message' => 'Product ID and Vendor ID required'], 400);
}

try {
    // Verify product exists and belongs to vendor
    $stmt = $db->prepare("SELECT id FROM vendor_products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    if (!$stmt->fetch()) {
        send_json_and_exit(['success' => false, 'message' => 'Product not found'], 404);
    }

    if (empty($_FILES['images'])) {
        send_json_and_exit(['success' => false, 'message' => 'No images provided'], 400);
    }

    // Save product images into vendor-panel public assets so web paths remain consistent
    $uploadDir = __DIR__ . '/../../vendor-panel/public/assets/img/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedCount = 0;
    $files = $_FILES['images'];

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmp = $files['tmp_name'][$i];
        $orig = basename($files['name'][$i]);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        // Validate file type
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) continue;

        // Validate file size (5MB max)
        if (filesize($tmp) > 5242880) continue;

        $fname = 'product_' . $product_id . '_' . uniqid() . '.' . $ext;
        $dest = $uploadDir . '/' . $fname;

        if (move_uploaded_file($tmp, $dest)) {
            // Use environment-aware web path
            $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                            strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
            $basePrefix = $isLocalhost ? '/phool-delivery-platform' : '';
            $webPath = $basePrefix . '/vendor-panel/public/assets/img/products/' . $fname;

            // Check if this is the first image - set as primary if no primary exists
            $stmt = $db->prepare("SELECT primary_image_url FROM vendor_products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            $isPrimary = empty($product['primary_image_url']) ? 1 : 0;

            // Insert image record
            $stmt = $db->prepare("
                INSERT INTO vendor_product_images (product_id, image_url, display_order, is_primary)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $webPath, $uploadedCount, $isPrimary]);

            // Update product primary_image_url if this is first image
            if ($isPrimary) {
                $stmt = $db->prepare("UPDATE vendor_products SET primary_image_url = ? WHERE id = ?");
                $stmt->execute([$webPath, $product_id]);
            }

            $uploadedCount++;
        }
    }

    if ($uploadedCount === 0) {
        send_json_and_exit(['success' => false, 'message' => 'No valid images were uploaded'], 400);
    }

    send_json_and_exit(['success' => true, 'message' => "Uploaded $uploadedCount image(s) successfully", 'count' => $uploadedCount], 200);

} catch (Exception $e) {
    error_log('[image-upload] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error'], 500);
}
