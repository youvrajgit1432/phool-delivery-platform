<?php
/**
 * AJAX: Delete Product
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
use App\Database\Connection;

$productId = (int)($_POST['id'] ?? 0);
$vendorId = $_SESSION['vendor_id'] ?? $_POST['vendor_id'] ?? null;

if (!$productId || !$vendorId) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $dbConfig = $GLOBALS['config']['database'] ?? [];
    $db = Connection::getInstance($dbConfig);

    // Verify product belongs to vendor
    $prod = $db->select('vendor_products', ['id' => $productId, 'vendor_id' => $vendorId], 1);
    if (empty($prod)) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        exit;
    }

    // Deleting products is disabled. Instead archive (make unavailable)
    $db->query('UPDATE vendor_products SET is_available = 0, status = ? WHERE id = ?', ['inactive', $productId]);

    echo json_encode(['success' => true, 'message' => 'Product archived (deletion disabled). Use availability to manage visibility.']);
    exit;

} catch (Throwable $e) {
    error_log('[product-delete] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
?>
