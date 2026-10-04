<?php
/**
 * Register Push Notification Subscription
 * Endpoint: /delivery-panel/public/ajax/register-push-subscription.php
 * 
 * This endpoint stores push notification subscriptions from riders' devices
 * allowing the backend to send push notifications for new order assignments.
 */

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/app/helpers/Auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    // Get request data
    $request = json_decode(file_get_contents('php://input'), true);
    
    if (!$request) {
        throw new Exception('Invalid request data');
    }

    $rider_id = $_SESSION['rider_id'];
    $subscription = $request['subscription'] ?? null;
    $device_type = $request['device_type'] ?? 'web';
    $timestamp = $request['timestamp'] ?? date('Y-m-d H:i:s');

    if (!$subscription) {
        throw new Exception('Subscription data is required');
    }

    // Extract subscription details
    $endpoint = $subscription['endpoint'] ?? null;
    $auth_key = $subscription['keys']['auth'] ?? null;
    $p256dh_key = $subscription['keys']['p256dh'] ?? null;

    if (!$endpoint) {
        throw new Exception('Invalid subscription endpoint');
    }

    // Prepare database connection
    $conn = new mysqli(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'phool_delivery'
    );

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Generate unique device ID
    $device_id = hash('sha256', $endpoint);

    // Check if subscription already exists
    $check_query = "SELECT id FROM rider_push_subscriptions 
                   WHERE rider_id = ? AND device_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('is', $rider_id, $device_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing = $result->fetch_assoc();

    if ($existing) {
        // Update existing subscription
        $update_query = "UPDATE rider_push_subscriptions 
                        SET subscription_data = ?,
                            auth_key = ?,
                            p256dh_key = ?,
                            device_type = ?,
                            is_active = 1,
                            last_updated = NOW()
                        WHERE rider_id = ? AND device_id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        $subscription_json = json_encode($subscription);
        $update_stmt->bind_param('sssissi', 
            $subscription_json,
            $auth_key,
            $p256dh_key,
            $device_type,
            $rider_id,
            $device_id
        );

        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update subscription: ' . $update_stmt->error);
        }

        $action = 'updated';
    } else {
        // Insert new subscription
        $insert_query = "INSERT INTO rider_push_subscriptions 
                        (rider_id, device_id, endpoint, subscription_data, auth_key, p256dh_key, 
                         device_type, is_active, created_at, last_updated)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $insert_stmt = $conn->prepare($insert_query);
        $subscription_json = json_encode($subscription);
        $insert_stmt->bind_param('issssss',
            $rider_id,
            $device_id,
            $endpoint,
            $subscription_json,
            $auth_key,
            $p256dh_key,
            $device_type
        );

        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to register subscription: ' . $insert_stmt->error);
        }

        $action = 'created';
    }

    // Log the action
    $log_query = "INSERT INTO rider_activity_log 
                  (rider_id, action, description, metadata, created_at)
                  VALUES (?, ?, ?, ?, NOW())";
    
    $log_stmt = $conn->prepare($log_query);
    $action_type = 'push_subscription_' . $action;
    $description = "Push subscription $action for $device_type device";
    $metadata = json_encode([
        'device_id' => $device_id,
        'device_type' => $device_type,
        'endpoint_hash' => substr(hash('sha256', $endpoint), 0, 16)
    ]);
    
    $log_stmt->bind_param('isss', $rider_id, $action_type, $description, $metadata);
    $log_stmt->execute();

    $conn->close();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Push subscription ' . $action . ' successfully',
        'device_id' => $device_id,
        'action' => $action
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
