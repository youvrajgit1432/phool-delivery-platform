<?php
/**
 * Assign Order to Rider AJAX Endpoint
 * Assigns an order to a delivery rider for fulfillment
 */

// CRITICAL: Must be first - set JSON header BEFORE anything else
header('Content-Type: application/json; charset=utf-8');
ob_start();

// Suppress all output/errors
ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication FIRST
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in']);
    ob_end_flush();
    exit;
}

// Get database connection MANUALLY (don't include bootstrap which has error output settings)
try {
    // Need to define isLocalhost function first
    if (!function_exists('isLocalhost')) {
        function isLocalhost() {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
        }
    }
    
    $config = require __DIR__ . '/../../config/database.php';
    $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
}

try {
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_id']) || !isset($input['order_id'])) {
        throw new Exception('Missing required parameters');
    }

    $rider_id = intval($input['rider_id']);
    $order_id = intval($input['order_id']);

    if ($rider_id <= 0 || $order_id <= 0) {
        throw new Exception('Invalid ID parameters');
    }
    
    // Verify rider exists and is active
    $rider_check = $db->prepare("SELECT id, status, first_name, last_name, phone FROM riders WHERE id = ?");
    $rider_check->execute([$rider_id]);
    $rider = $rider_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$rider) {
        throw new Exception('Rider not found');
    }

    if ($rider['status'] !== 'active') {
        throw new Exception('Rider is not active. Status: ' . $rider['status']);
    }

    // Verify order exists
    $order_check = $db->prepare("SELECT id, order_number, total_amount FROM orders WHERE id = ?");
    $order_check->execute([$order_id]);
    $order = $order_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }

    // Check if order is already assigned to a rider
    $rider_order_check = $db->prepare("SELECT id FROM rider_orders WHERE order_id = ? AND delivery_status NOT IN ('failed', 'cancelled')");
    $rider_order_check->execute([$order_id]);
    if ($rider_order_check->fetch()) {
        throw new Exception('Order is already assigned to another rider');
    }

    // Get order details from vendor_orders (which has complete customer & vendor details)
    $order_details = $db->prepare("
        SELECT vo.*, v.store_name, v.business_address, v.city as vendor_city
        FROM vendor_orders vo
        LEFT JOIN vendors v ON vo.vendor_id = v.id
        WHERE vo.order_id = ?
        LIMIT 1
    ");
    $order_details->execute([$order_id]);
    $order_data = $order_details->fetch(PDO::FETCH_ASSOC);

    if (!$order_data) {
        throw new Exception('Order details not found in vendor_orders');
    }

    // Create rider order record with complete data
    $sql = "INSERT INTO rider_orders (
        rider_id, order_id, order_number, customer_name, customer_phone,
        pickup_address, delivery_address, delivery_city, total_items,
        total_amount, payment_method, delivery_status, assigned_at,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', NOW(), NOW(), NOW())";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $rider_id,
        $order_id,
        $order_data['order_number'],
        $order_data['customer_name'] ?? '',
        $order_data['customer_phone'] ?? '',
        $order_data['store_name'] ?? ($order_data['business_address'] ?? ''), // Vendor pickup location
        $order_data['customer_address'] ?? '',
        $order_data['customer_address'] ?? '',
        $order_data['total_items'] ?? 0,
        $order_data['total_amount'] ?? 0,
        $order_data['payment_method'] ?? 'cod'
    ]);

    $rider_order_id = $db->lastInsertId();

    // Update orders table to track rider assignment (if columns exist)
    try {
        $db->prepare("UPDATE orders SET assigned_rider_id = ?, rider_assigned_at = NOW(), rider_assignment_status = 'assigned', updated_at = NOW() WHERE id = ?")
            ->execute([$rider_id, $order_id]);
    } catch (Exception $e) {
        // Column might not exist yet, log and continue
        error_log('Could not update orders table with rider assignment: ' . $e->getMessage());
    }

    // Send notification to rider
    try {
        $rider_notification = "
            INSERT INTO rider_notifications (
                rider_id, notification_type, title, message, order_id, 
                push_sent, created_at
            ) VALUES (?, 'order_assigned', 'New Order Assigned', ?, ?, 0, NOW())
        ";
        $db->prepare($rider_notification)->execute([
            $rider_id,
            'Order ' . $order_data['order_number'] . ' assigned to you for delivery',
            $order_id
        ]);
    } catch (Exception $e) {
        // Notification table might not exist, log and continue
        error_log('Could not send rider notification: ' . $e->getMessage());
    }

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'assign_order_to_rider',
                    json_encode(['rider_id' => $rider_id, 'order_id' => $order_id, 'order_number' => $order_data['order_number']])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Order assigned to rider successfully',
        'rider_order_id' => $rider_order_id,
        'rider_name' => trim($rider['first_name'] . ' ' . $rider['last_name']),
        'rider_phone' => $rider['phone']
    ]);
    ob_end_flush();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'order_assignment_error'
    ]);
    error_log('Order assignment error: ' . $e->getMessage());
    ob_end_flush();
    exit;
}
?>
