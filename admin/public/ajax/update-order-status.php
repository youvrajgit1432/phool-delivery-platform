<?php
/**
 * Quick Order Status Update AJAX Handler
 * Admin Panel - admin/public/ajax/update-order-status.php
 * 
 * POST request to update an order's status directly
 * Includes: Push Notifications, Email, and In-App Messages
 * 
 * Parameters:
 *  - order_id: ID of order to update
 *  - new_status: New status value (confirmed, preparing, out_for_delivery, delivered, cancelled, pending)
 *  - send_notification: (optional) boolean to send push notification
 *  - send_email: (optional) boolean to send email
 */

// Prevent any output before JSON
ob_start();
header('Content-Type: application/json');

try {
    // Include required files
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';
    
    // Check authentication
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
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : null;
$new_status = isset($data['new_status']) ? strtolower(trim($data['new_status'])) : null;
$send_notification = isset($data['send_notification']) ? (bool)$data['send_notification'] : true;
$send_email = isset($data['send_email']) ? (bool)$data['send_email'] : true;
$notes = isset($data['notes']) ? trim($data['notes']) : '';

// Validate input
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_id is required']);
    exit;
}

// Valid status values
$allowed_statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

if (!$new_status || !in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed: ' . implode(', ', $allowed_statuses)]);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify order exists and get full details
    $stmt = $pdo->prepare("
        SELECT o.*, c.id as customer_id, c.name as customer_name, c.phone, c.email,
               COUNT(pnd.id) as device_count,
               GROUP_CONCAT(DISTINCT pnd.device_token) as device_tokens
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN push_notification_devices pnd ON c.id = pnd.user_id AND pnd.is_active = 1
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Prevent updating to same status
    if ($order['status'] === $new_status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order is already in ' . ucfirst($new_status) . ' status']);
        exit;
    }

    // Update order status and notes
    $update_stmt = $pdo->prepare("UPDATE orders SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$new_status, $notes, $order_id]);

    // Log the status change
    error_log("Order {$order['order_number']} (ID: {$order_id}) status changed from '{$order['status']}' to '{$new_status}'");

    $notification_results = [];
    $email_results = [];
    
    // Send push notification if requested
    if ($send_notification && ($order['device_count'] ?? 0) > 0) {
        try {
            $pushService = new AdminPushNotificationService($pdo);
            $push_result = $pushService->sendOrderStatusUpdate($order_id, $new_status, $notes);
            $notification_results['push'] = $push_result ? 'success' : 'failed';
        } catch (Exception $e) {
            error_log("Push notification error: " . $e->getMessage());
            $notification_results['push'] = 'failed';
        }
    }
    
    // Send email notification if requested
    if ($send_email && !empty($order['email'])) {
        try {
            $emailService = new EmailService($pdo);
            $email_result = $emailService->sendOrderStatusUpdate($order_id, $new_status);
            $email_results['email'] = $email_result['success'] ? 'success' : 'failed';
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            $email_results['email'] = 'failed';
        }
    }
    
    // Send in-app message
    try {
        $messageService = getMessageService();
        $messageService->createOrderStatusMessage($order_id, $new_status);
    } catch (Exception $e) {
        error_log("In-app message error: " . $e->getMessage());
    }

    // Get status badge color for response
    $badge_color = '';
    switch($new_status) {
        case 'pending': $badge_color = 'warning'; break;
        case 'confirmed': $badge_color = 'info'; break;
        case 'preparing': $badge_color = 'primary'; break;
        case 'out_for_delivery': $badge_color = 'secondary'; break;
        case 'delivered': $badge_color = 'success'; break;
        case 'cancelled': $badge_color = 'danger'; break;
        default: $badge_color = 'secondary';
    }

    // Prepare message parts for response
    $message_parts = ["Order status updated to " . ucfirst(str_replace('_', ' ', $new_status))];
    
    if ($send_notification) {
        $device_count = $order['device_count'] ?? 0;
        $push_status = ($notification_results['push'] ?? 'skipped') === 'success' ? 
            '✅ Sent to ' . $device_count . ' device(s)' : 
            '❌ Failed or no devices';
        $message_parts[] = "Push: " . $push_status;
    }
    
    if ($send_email) {
        $message_parts[] = "Email: " . (($email_results['email'] ?? 'skipped') === 'success' ? '✅ Sent' : '❌ Failed or no email');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => implode(' | ', $message_parts),
        'old_status' => $order['status'],
        'new_status' => $new_status,
        'badge_color' => $badge_color,
        'timestamp' => date('Y-m-d H:i:s'),
        'notifications' => $notification_results,
        'emails' => $email_results
    ]);

} catch (Exception $e) {
    error_log('Order status update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating order status: ' . $e->getMessage()
    ]);
}
