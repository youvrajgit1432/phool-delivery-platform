<?php
/**
 * AJAX: Permanently Delete Product (Admin Panel)
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
        error_log('[product-perm-delete stray output] ' . $buf);
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

if (!$product_id) {
    send_json_and_exit(['success' => false, 'message' => 'Product ID required'], 400);
}

try {
    // Delete associated images files and rows
    $imgStmt = $db->prepare("SELECT id, image_url FROM vendor_product_images WHERE product_id = ?");
    $imgStmt->execute([$product_id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($images as $img) {
        if (!empty($img['image_url'])) {
            $filepath = $_SERVER['DOCUMENT_ROOT'] . $img['image_url'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
        // delete image record
        $d = $db->prepare("DELETE FROM vendor_product_images WHERE id = ?");
        $d->execute([$img['id']]);
    }

    // Remove primary image file if set on product
    $pstmt = $db->prepare("SELECT primary_image_url FROM vendor_products WHERE id = ? LIMIT 1");
    $pstmt->execute([$product_id]);
    $prod = $pstmt->fetch(PDO::FETCH_ASSOC);
    if ($prod && !empty($prod['primary_image_url'])) {
        $pfile = $_SERVER['DOCUMENT_ROOT'] . $prod['primary_image_url'];
        if (file_exists($pfile)) {@unlink($pfile);}    
    }

    // Finally delete product row
    $stmt = $db->prepare("DELETE FROM vendor_products WHERE id = ?");
    $stmt->execute([$product_id]);

    send_json_and_exit(['success' => true, 'message' => 'Product permanently deleted'], 200);

} catch (Exception $e) {
    error_log('[product-perm-delete] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

?>
