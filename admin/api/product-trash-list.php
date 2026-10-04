<?php
/**
 * AJAX: List Trashed Products (Admin Panel)
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
        error_log('[product-trash-list stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
}

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$db = getDBConnection();

$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;

try {
    if ($vendor_id) {
        $stmt = $db->prepare("SELECT vp.*, v.store_name FROM vendor_products vp LEFT JOIN vendors v ON vp.vendor_id = v.id WHERE vp.deleted_at IS NOT NULL AND vp.vendor_id = ? ORDER BY vp.deleted_at DESC");
        $stmt->execute([$vendor_id]);
    } else {
        $stmt = $db->prepare("SELECT vp.*, v.store_name FROM vendor_products vp LEFT JOIN vendors v ON vp.vendor_id = v.id WHERE vp.deleted_at IS NOT NULL ORDER BY vp.deleted_at DESC");
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json_and_exit(['success' => true, 'data' => $rows], 200);

} catch (Exception $e) {
    error_log('[product-trash-list] Exception: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

?>
