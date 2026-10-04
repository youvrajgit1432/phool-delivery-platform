<?php
/**
 * Restore Deleted Orders from Trash
 * File: admin/public/ajax/restore-orders.php
 */

ob_start();
header('Content-Type: application/json');

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';
    requireAuth();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Bootstrap error: ' . $e->getMessage()]));
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$order_ids = isset($data['order_ids']) && is_array($data['order_ids']) ? $data['order_ids'] : [];

// Validate input
if (empty($order_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No orders selected']);
    exit;
}

// Sanitize order IDs
$order_ids = array_map('intval', array_filter($order_ids, function($id) {
    return is_numeric($id) && intval($id) > 0;
}));

if (empty($order_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order IDs provided']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Restore deleted orders
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare("UPDATE orders SET deleted_at = NULL WHERE id IN ($placeholders) AND deleted_at IS NOT NULL");
    $stmt->execute($order_ids);
    $restored_count = $stmt->rowCount();
    
    // Log the action
    error_log("Restored " . $restored_count . " orders from trash. IDs: " . implode(', ', $order_ids));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $restored_count . ' order(s) restored from trash',
        'restored_count' => $restored_count
    ]);
    
} catch (Exception $e) {
    error_log('Restore error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error restoring orders: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
