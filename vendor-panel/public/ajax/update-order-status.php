<?php
/**
 * Update Order Status - Vendor Panel
 * Allow vendors to change order status: accepted -> preparing -> ready
 */

ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['vendor_id'])) {
        throw new Exception('Unauthorized');
    }

    $vendor_id = (int)$_SESSION['vendor_id'];
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : null;

    if (!$order_id || !$new_status) {
        throw new Exception('Invalid parameters');
    }

    // Validate status transition
    $allowedStatusTransitions = [
        'assigned' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['cancelled'],
    ];

    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = \App\Database\Connection::getInstance($dbConfig);

    // Get current order
    $stmt = $db->query(
        'SELECT id, status FROM vendor_orders WHERE id = ? AND vendor_id = ?',
        [$order_id, $vendor_id]
    );
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found or unauthorized');
    }

    $current_status = $order['status'];

    // Check if transition is allowed
    if (!isset($allowedStatusTransitions[$current_status]) || 
        !in_array($new_status, $allowedStatusTransitions[$current_status])) {
        throw new Exception('Invalid status transition from ' . $current_status . ' to ' . $new_status);
    }

    // Update order status
    $stmt = $db->query(
        'UPDATE vendor_orders SET status = ?, updated_at = NOW() WHERE id = ? AND vendor_id = ?',
        [$new_status, $order_id, $vendor_id]
    );

    // Log status change if accepted
    if ($new_status === 'accepted') {
        $db->query(
            'UPDATE vendor_orders SET accepted_at = NOW() WHERE id = ?',
            [$order_id]
        );
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated to ' . $new_status,
        'order_id' => $order_id,
        'new_status' => $new_status
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
