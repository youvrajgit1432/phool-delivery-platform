<?php
/**
 * Unregister Push Subscription Endpoint
 * Path: /vendor-panel/public/ajax/unregister-push-subscription.php
 * Purpose: Disable vendor push notification subscription
 * Method: POST
 * Input: JSON with endpoint
 * Output: JSON response
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
if (empty($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$vendorId = $_SESSION['vendor_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Deactivate all subscriptions for this vendor
    $stmt = $conn->prepare(
        "UPDATE vendor_push_subscriptions 
         SET is_active = 0 
         WHERE vendor_id = ?"
    );
    
    if (!$stmt) {
        throw new Exception('Prepare failed');
    }
    
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $stmt->close();

    // Log activity
    $logStmt = $conn->prepare(
        "INSERT INTO vendor_activity_log (vendor_id, action, description, created_at)
         VALUES (?, 'push_notifications_disabled', 'Push notifications disabled', CURRENT_TIMESTAMP)"
    );
    
    if ($logStmt) {
        $logStmt->bind_param('i', $vendorId);
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Push notifications disabled'
    ]);
    
} catch (Exception $e) {
    error_log('Push unregister error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
?>
