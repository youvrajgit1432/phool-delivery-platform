<?php
/**
 * Permanently Delete Orders from Trash
 * File: admin/public/ajax/permanent-delete-orders.php
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
    
    // Start transaction
    $pdo->beginTransaction();
    
    // First, delete order items associated with these orders
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
    $stmt->execute($order_ids);
    
    // Delete vendor_order_items (if table exists)
    try {
        $stmt = $pdo->prepare("
            DELETE FROM vendor_order_items 
            WHERE vendor_order_id IN (
                SELECT id FROM vendor_orders WHERE order_id IN ($placeholders)
            )
        ");
        $stmt->execute($order_ids);
    } catch (Exception $e) {
        error_log('Warning: vendor_order_items delete failed: ' . $e->getMessage());
    }
    
    // Delete vendor_orders (if table exists)
    try {
        $stmt = $pdo->prepare("DELETE FROM vendor_orders WHERE order_id IN ($placeholders)");
        $stmt->execute($order_ids);
    } catch (Exception $e) {
        error_log('Warning: vendor_orders delete failed: ' . $e->getMessage());
    }
    
    // Delete vendor_notifications (if table exists)
    try {
        $stmt = $pdo->prepare("DELETE FROM vendor_notifications WHERE order_id IN ($placeholders)");
        $stmt->execute($order_ids);
    } catch (Exception $e) {
        error_log('Warning: vendor_notifications delete failed: ' . $e->getMessage());
    }
    
    // Finally, permanently delete the orders
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
    $stmt->execute($order_ids);
    $deleted_count = $stmt->rowCount();
    
    // Commit transaction
    $pdo->commit();
    
    // Log the action
    error_log("Permanently deleted " . $deleted_count . " orders. IDs: " . implode(', ', $order_ids));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $deleted_count . ' order(s) permanently deleted',
        'deleted_count' => $deleted_count
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rbError) {
        error_log('Rollback failed: ' . $rbError->getMessage());
    }
    
    error_log('Permanent delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting orders: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
