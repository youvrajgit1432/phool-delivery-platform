<?php
/**
 * Register Push Subscription Endpoint
 * Path: /vendor-panel/public/ajax/register-push-subscription.php
 * Purpose: Store vendor push notification subscription
 * Method: POST
 * Input: JSON with subscription data
 * Output: JSON response
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../storage/logs/push-errors.log');

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Session check
session_start();
if (empty($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get vendor ID
$vendorId = $_SESSION['vendor_id'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
if (empty($input['endpoint']) || empty($input['auth_key']) || empty($input['p256dh_key'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Database connection
require_once __DIR__ . '/../../../config/database.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Generate device ID (hash of endpoint)
    $deviceId = hash('sha256', $input['endpoint']);
    
    // Prepare subscription data
    $endpoint = $input['endpoint'];
    $authKey = $input['auth_key'];
    $p256dhKey = $input['p256dh_key'];
    $deviceType = $input['device_type'] ?? 'web';
    $deviceName = $input['device_name'] ?? 'Unknown Device';
    $browserInfo = $input['browser_info'] ?? 'Unknown Browser';
    
    // Prepare subscription data as JSON
    $subscriptionData = json_encode([
        'endpoint' => $endpoint,
        'keys' => [
            'auth' => $authKey,
            'p256dh' => $p256dhKey
        ]
    ]);

    // Check if subscription already exists
    $checkStmt = $conn->prepare(
        "SELECT id FROM vendor_push_subscriptions WHERE vendor_id = ? AND device_id = ?"
    );
    
    if (!$checkStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param('is', $vendorId, $deviceId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existingRecord = $result->fetch_assoc();
    $checkStmt->close();

    if ($existingRecord) {
        // Update existing subscription
        $updateStmt = $conn->prepare(
            "UPDATE vendor_push_subscriptions 
             SET subscription_data = ?, auth_key = ?, p256dh_key = ?, 
                 device_type = ?, device_name = ?, browser_info = ?, 
                 is_active = 1, last_updated = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        
        if (!$updateStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $updateStmt->bind_param(
            'sssssssi',
            $subscriptionData,
            $authKey,
            $p256dhKey,
            $deviceType,
            $deviceName,
            $browserInfo,
            $existingRecord['id']
        );
        
        if (!$updateStmt->execute()) {
            throw new Exception('Update failed: ' . $updateStmt->error);
        }
        
        $updateStmt->close();
        
        // Log activity
        logActivity($conn, $vendorId, 'push_subscription_updated', 'Device subscription updated', $deviceId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription updated',
            'action' => 'updated'
        ]);
    } else {
        // Insert new subscription
        $insertStmt = $conn->prepare(
            "INSERT INTO vendor_push_subscriptions 
             (vendor_id, device_id, endpoint, subscription_data, auth_key, p256dh_key, 
              device_type, device_name, browser_info, is_active, created_at, last_updated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        
        if (!$insertStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $insertStmt->bind_param(
            'isssssss',
            $vendorId,
            $deviceId,
            $endpoint,
            $subscriptionData,
            $authKey,
            $p256dhKey,
            $deviceType,
            $deviceName,
            $browserInfo
        );
        
        if (!$insertStmt->execute()) {
            throw new Exception('Insert failed: ' . $insertStmt->error);
        }
        
        $insertStmt->close();
        
        // Log activity
        logActivity($conn, $vendorId, 'push_subscription_created', 'New device subscription', $deviceId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription registered',
            'action' => 'created'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Push subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}

/**
 * Log activity
 */
function logActivity($conn, $vendorId, $action, $description, $details = null) {
    try {
        $logStmt = $conn->prepare(
            "INSERT INTO vendor_activity_log (vendor_id, action, description, details, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        
        if ($logStmt) {
            $logStmt->bind_param('isss', $vendorId, $action, $description, $details);
            $logStmt->execute();
            $logStmt->close();
        }
    } catch (Exception $e) {
        error_log('Activity logging failed: ' . $e->getMessage());
    }
}
?>
