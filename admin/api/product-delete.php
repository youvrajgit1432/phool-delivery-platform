<?php
/**
 * AJAX: Delete Product (Admin Panel)
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
        error_log('[product-delete] stray output: ' . $buf);
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
    // Ensure vendor_products has deleted_at column (migration safe-guard)
    $colStmt = $db->prepare("SHOW COLUMNS FROM vendor_products LIKE 'deleted_at'");
    $colStmt->execute();
    $col = $colStmt->fetch();
    if (!$col) {
        // add deleted_at column
        $db->exec("ALTER TABLE vendor_products ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    }

    // Soft-delete: mark inactive and set deleted_at
    $stmt = $db->prepare("UPDATE vendor_products SET status = ?, is_available = 0, deleted_at = ? WHERE id = ?");
    $stmt->execute(['inactive', date('Y-m-d H:i:s'), $product_id]);

    send_json_and_exit(['success' => true, 'message' => 'Product moved to trash (soft-deleted)'], 200);

} catch (Exception $e) {
    error_log('[product-delete] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
