<?php
/**
 * Unregister Push Notification Subscription
 * Endpoint: /delivery-panel/public/ajax/unregister-push-subscription.php
 */

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    $request = json_decode(file_get_contents('php://input'), true);
    $rider_id = $_SESSION['rider_id'];
    $timestamp = $request['timestamp'] ?? date('Y-m-d H:i:s');

    $conn = new mysqli(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'phool_delivery'
    );

    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Deactivate all subscriptions for this rider
    $update_query = "UPDATE rider_push_subscriptions 
                    SET is_active = 0, last_updated = NOW()
                    WHERE rider_id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('i', $rider_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to unregister subscriptions');
    }

    // Log action
    $log_query = "INSERT INTO rider_activity_log 
                  (rider_id, action, description, created_at)
                  VALUES (?, ?, ?, NOW())";
    
    $log_stmt = $conn->prepare($log_query);
    $action = 'push_subscription_disabled';
    $description = 'Push notifications disabled by rider';
    $log_stmt->bind_param('iss', $rider_id, $action, $description);
    $log_stmt->execute();

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Push subscriptions deactivated'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
