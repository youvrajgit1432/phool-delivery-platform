<?php
/**
 * AJAX: Restore Product from Trash (Admin Panel)
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
        error_log('[product-restore stray output] ' . $buf);
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
    // Check column exists; if not, nothing to restore
    $colStmt = $db->prepare("SHOW COLUMNS FROM vendor_products LIKE 'deleted_at'");
    $colStmt->execute();
    $col = $colStmt->fetch();

    if (!$col) {
        send_json_and_exit(['success' => false, 'message' => 'Trash not enabled on products'], 400);
    }

    // Restore product: clear deleted_at and set status active
    $stmt = $db->prepare("UPDATE vendor_products SET deleted_at = NULL, status = ?, is_available = 1 WHERE id = ?");
    $stmt->execute(['active', $product_id]);

    send_json_and_exit(['success' => true, 'message' => 'Product restored from trash'], 200);

} catch (Exception $e) {
    error_log('[product-restore] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

?>
