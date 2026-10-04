<?php
/**
 * AJAX: Cancel / Unassign vendor order
 * POST JSON { vendor_order_id: int }
 */
require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/../../app/middleware/AdminMiddleware.php';

header('Content-Type: application/json');
requireAuth();

 $raw = file_get_contents('php://input');
 $data = json_decode($raw, true) ?: [];
 $vendor_order_id = isset($data['vendor_order_id']) ? (int)$data['vendor_order_id'] : 0;
 $clear_items = !empty($data['clear_items']) ? true : false;

if (!$vendor_order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'vendor_order_id is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    // Verify vendor_order exists
    $stmt = $pdo->prepare('SELECT id, vendor_id, order_id, status FROM vendor_orders WHERE id = ? LIMIT 1');
    $stmt->execute([$vendor_order_id]);
    $vo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vendor order not found']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();


    // Update vendor_orders.status to 'cancelled'
    $u1 = $pdo->prepare('UPDATE vendor_orders SET status = ?, updated_at = NOW() WHERE id = ?');
    $u1->execute(['cancelled', $vendor_order_id]);

    // Optionally delete vendor_order_items if requested
    if ($clear_items) {
        $d = $pdo->prepare('DELETE FROM vendor_order_items WHERE vendor_order_id = ?');
        $d->execute([$vendor_order_id]);
    }

    // Update main orders: set assign_status = 'unassigned', clear assigned_vendor_id
    $u2 = $pdo->prepare('UPDATE orders SET assign_status = ?, assigned_vendor_id = NULL, updated_at = NOW() WHERE id = ?');
    $u2->execute(['unassigned', $vo['order_id']]);

    // Optionally log notification for vendor (use correct columns)
    $log = $pdo->prepare('INSERT INTO vendor_notifications (vendor_id, notification_type, title, message, data, performed_by_admin, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $title = 'Order Cancelled by Admin';
    $msg = 'Order ' . ($vo['order_id'] ?? $vendor_order_id) . ' has been cancelled/unassigned by admin.';
    $log->execute([$vo['vendor_id'], 'cancelled', $title, $msg, json_encode(['admin' => true]), NULL, 0]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Vendor order cancelled and main order set to unassigned']);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('vendor-order-cancel error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel vendor order', 'error' => $e->getMessage()]);
    exit;
}

