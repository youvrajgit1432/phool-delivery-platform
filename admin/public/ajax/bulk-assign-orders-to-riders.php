<?php
/**
 * Bulk Assign Orders to Riders AJAX Endpoint
 * Simply inserts order data into rider_orders table
 * Data comes from vendor_orders (has customer info) and vendors table (pickup address)
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

// Get database connection
try {
    $config = require __DIR__ . '/../../config/database.php';
    $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    ob_end_flush();
    exit;
}

try {
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['rider_ids']) || !isset($input['order_ids'])) {
        throw new Exception('Missing required parameters');
    }

    $rider_ids = array_map('intval', $input['rider_ids']);
    $order_ids = array_map('intval', $input['order_ids']);

    if (empty($rider_ids) || empty($order_ids)) {
        throw new Exception('No riders or orders selected');
    }

    // Remove invalid IDs
    $rider_ids = array_filter($rider_ids, function($id) { return $id > 0; });
    $order_ids = array_filter($order_ids, function($id) { return $id > 0; });

    if (empty($rider_ids) || empty($order_ids)) {
        throw new Exception('Invalid ID parameters');
    }

    $assignment_count = 0;
    $failed_count = 0;

    // Assign each order to each rider - SIMPLE INSERT ONLY
    foreach ($order_ids as $order_id) {
        
        // Get vendor order data (has customer_name, customer_phone, customer_address, totals)
        $vendor_stmt = $db->prepare("
            SELECT 
                vo.order_number,
                vo.customer_name,
                vo.customer_phone,
                vo.customer_address,
                vo.total_items,
                vo.total_amount,
                vo.payment_method,
                v.store_name,
                v.business_address
            FROM vendor_orders vo
            LEFT JOIN vendors v ON vo.vendor_id = v.id
            WHERE vo.order_id = ? AND vo.status IN ('ready', 'accepted')
            LIMIT 1
        ");
        $vendor_stmt->execute([$order_id]);
        $vendor_data = $vendor_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vendor_data) {
            $failed_count++;
            continue;
        }

        // For each rider, insert into rider_orders
        foreach ($rider_ids as $rider_id) {
            
            // Verify rider exists and is active
            $rider_check = $db->prepare("SELECT id, status FROM riders WHERE id = ? AND status = 'active'");
            $rider_check->execute([$rider_id]);
            $rider = $rider_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$rider) {
                continue; // Skip inactive riders
            }

            try {
                // Check if already assigned
                $existing = $db->prepare("
                    SELECT id FROM rider_orders 
                    WHERE order_id = ? AND rider_id = ? AND delivery_status NOT IN ('failed', 'cancelled')
                ");
                $existing->execute([$order_id, $rider_id]);
                if ($existing->fetch()) {
                    continue; // Already assigned
                }

                // SIMPLE INSERT into rider_orders - that's all!
                $insert_sql = "
                    INSERT INTO rider_orders (
                        rider_id, order_id, order_number, 
                        customer_name, customer_phone,
                        pickup_address, delivery_address, delivery_city,
                        total_items, total_amount, payment_method,
                        delivery_status, assigned_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', NOW(), NOW())
                ";

                $insert_stmt = $db->prepare($insert_sql);
                $insert_stmt->execute([
                    $rider_id,
                    $order_id,
                    $vendor_data['order_number'] ?? '',
                    $vendor_data['customer_name'] ?? '',
                    $vendor_data['customer_phone'] ?? '',
                    $vendor_data['store_name'] ?? $vendor_data['business_address'] ?? '', // Pickup (vendor store)
                    $vendor_data['customer_address'] ?? '', // Delivery address
                    '', // delivery_city - can be extracted from address if needed
                    $vendor_data['total_items'] ?? 0,
                    $vendor_data['total_amount'] ?? 0,
                    $vendor_data['payment_method'] ?? 'cod'
                ]);

                $assignment_count++;

            } catch (Exception $e) {
                error_log('Error assigning order ' . $order_id . ' to rider ' . $rider_id . ': ' . $e->getMessage());
                $failed_count++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Orders assigned successfully',
        'assigned_count' => $assignment_count,
        'failed_count' => $failed_count
    ]);
    ob_end_flush();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Bulk assign orders error: ' . $e->getMessage());
    ob_end_flush();
    exit;
}
?>
