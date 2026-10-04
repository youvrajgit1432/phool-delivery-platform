<?php
/**
 * AJAX: Toggle Product Availability
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
        error_log('[toggle-availability stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
}

$bootstrapAutoload = __DIR__ . '/../../bootstrap/autoload.php';
$bootstrapApp = __DIR__ . '/../../bootstrap/app.php';
if (!file_exists($bootstrapAutoload) || !file_exists($bootstrapApp)) {
    send_json_and_exit(['success' => false, 'message' => 'Server configuration error: bootstrap files missing'], 500);
}

require_once $bootstrapAutoload;
require_once $bootstrapApp;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
use App\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$vendorId = $_SESSION['vendor_id'] ?? null;
if (!$vendorId) {
    send_json_and_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $productId = (int)($_POST['product_id'] ?? 0);
    $isAvailable = (int)($_POST['is_available'] ?? 0);

    if (!$productId) {
        send_json_and_exit(['success' => false, 'message' => 'Product ID required'], 400);
    }

    $dbConfig = $GLOBALS['config']['database'] ?? [];
    $db = Connection::getInstance($dbConfig);

    // Verify product mapping belongs to vendor
    $stmt = $db->query('SELECT id FROM vendor_product_map WHERE id = ? AND vendor_id = ?', [$productId, $vendorId]);
    $product = $stmt->fetch();
    if (!$product) {
        send_json_and_exit(['success' => false, 'message' => 'Product not found or unauthorized'], 403);
    }

    // Update product availability in vendor_product_map
    $db->query(
        'UPDATE vendor_product_map SET is_available = ?, updated_at = NOW() WHERE id = ?',
        [$isAvailable, $productId]
    );

    send_json_and_exit(['success' => true, 'product_id' => $productId, 'is_available' => $isAvailable, 'message' => 'Availability updated successfully'], 200);

} catch (Throwable $e) {
    error_log('[toggle-availability] Exception: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
    send_json_and_exit(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
