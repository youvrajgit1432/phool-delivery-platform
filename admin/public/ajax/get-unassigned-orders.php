<?php
/**
 * Get Unassigned Orders AJAX Endpoint
 * Retrieves orders that are ready for delivery but not yet assigned to a rider
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get unassigned orders that are ready for delivery
    // These are orders with vendor_orders status = 'assigned', 'accepted', 'preparing', or 'ready'
    // and NOT in rider_orders yet
    // Using LEFT JOIN for better reliability (NOT IN can fail with NULL values)
    $sql = "
        SELECT 
            vo.id as order_id,
            vo.order_number, 
            vo.customer_name,
            vo.customer_phone,
            vo.total_amount,
            vo.status,
            vo.total_items as item_count,
            'Ready for Delivery' as product_names
        FROM vendor_orders vo
        LEFT JOIN (
            SELECT DISTINCT order_id 
            FROM rider_orders 
            WHERE delivery_status NOT IN ('failed', 'cancelled', 'returned')
        ) ro ON vo.order_id = ro.order_id
        WHERE vo.status IN ('assigned', 'accepted', 'preparing', 'ready')
        AND ro.order_id IS NULL
        ORDER BY vo.created_at DESC
        LIMIT 100
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => count($orders)
    ]);
    ob_end_flush();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Get unassigned orders error: ' . $e->getMessage());
    ob_end_flush();
    exit;
}
?>
