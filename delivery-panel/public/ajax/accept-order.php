<?php
/**
 * Accept Order AJAX Endpoint
 * Allows rider to accept an assigned order
 */

// Initialize session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
ob_start();

// Suppress errors
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

// Check authentication
if (!isset($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

try {
    // Get database connection using parent bootstrap
    $db = null;
    
    // Load config from delivery-panel
    $configPath = dirname(__DIR__, 2) . '/config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception('Database config not found at: ' . $configPath);
    }
    
    $dbConfig = require $configPath;
    
    // Get the default connection config
    $defaultDriver = $dbConfig['default'] ?? 'mysql';
    $config = $dbConfig['connections'][$defaultDriver] ?? [];
    
    if (empty($config)) {
        throw new Exception('Database configuration not found');
    }
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = intval($input['order_id'] ?? 0);
    $riderId = intval($_SESSION['rider_id']);

    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }

    // Validate rider has this order assigned
    $stmt = $db->prepare("
        SELECT ro.id, ro.rider_id, ro.delivery_status, o.order_number
        FROM rider_orders ro
        INNER JOIN orders o ON ro.order_id = o.id
        WHERE ro.order_id = ? AND ro.rider_id = ?
    ");
    $stmt->execute([$orderId, $riderId]);
    $riderOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$riderOrder) {
        throw new Exception('Order not found or not assigned to you');
    }

    if ($riderOrder['delivery_status'] === 'accepted') {
        throw new Exception('Order already accepted');
    }

    if ($riderOrder['delivery_status'] === 'cancelled') {
        throw new Exception('Order has been cancelled');
    }

    // Update delivery_status to 'accepted' and set accepted_at timestamp
    $stmt = $db->prepare("
        UPDATE rider_orders 
        SET delivery_status = 'accepted', 
            accepted_at = NOW(),
            updated_at = NOW()
        WHERE order_id = ? AND rider_id = ?
    ");
    $stmt->execute([$orderId, $riderId]);

    // Note: We don't sync with vendor_orders on accept because the vendor manages accept status
    // vendor_orders status 'accepted' is controlled by the vendor only

    // Log action
    $stmt = $db->prepare("
        INSERT INTO rider_activity_log (rider_id, action, details, created_at)
        VALUES (?, 'accept_order', ?, NOW())
    ");
    $details = json_encode([
        'order_id' => $orderId,
        'order_number' => $riderOrder['order_number'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    $stmt->execute([$riderId, $details]);

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Order accepted successfully',
        'data' => [
            'order_id' => $orderId,
            'status' => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s'),
        ]
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Accept order error: ' . $e->getMessage());
}

ob_end_flush();

