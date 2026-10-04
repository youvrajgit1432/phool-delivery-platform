<?php
/**
 * Mark Order as On The Way AJAX Endpoint
 * Allows rider to mark order as on the way for delivery
 * Status transition: picked_up → on_the_way
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
    // Get database connection
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
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = intval($input['order_id'] ?? 0);
    $riderId = intval($_SESSION['rider_id']);

    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }

    // Validate rider has this order assigned
    $stmt = $db->prepare("
        SELECT ro.id, ro.rider_id, ro.delivery_status, ro.order_number
        FROM rider_orders ro
        INNER JOIN orders o ON ro.order_id = o.id
        WHERE ro.order_id = ? AND ro.rider_id = ?
    ");
    $stmt->execute([$orderId, $riderId]);
    $riderOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$riderOrder) {
        throw new Exception('Order not found or not assigned to you');
    }

    if ($riderOrder['delivery_status'] !== 'picked_up') {
        throw new Exception('Order must be picked up before marking as on the way. Current status: ' . $riderOrder['delivery_status']);
    }

    // Start transaction for atomicity
    $db->beginTransaction();

    try {
        // Update rider_orders table
        $stmt = $db->prepare("
            UPDATE rider_orders 
            SET delivery_status = 'on_the_way', 
                on_the_way_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND rider_id = ?
        ");
        $stmt->execute([$riderOrder['id'], $riderId]);

        // Note: vendor_orders doesn't have 'on_the_way' status, so we don't need to update it
        // It only cares about: assigned, accepted, preparing, ready, picked_up, delivered

        // Commit transaction
        $db->commit();

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order marked as on the way',
            'data' => [
                'order_id' => $orderId,
                'status' => 'on_the_way',
                'on_the_way_at' => date('Y-m-d H:i:s'),
                'order_number' => $riderOrder['order_number']
            ]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    ob_end_flush();
    exit;
}
?>
