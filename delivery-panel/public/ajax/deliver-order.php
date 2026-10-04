<?php
/**
 * Mark Order as Delivered AJAX Endpoint
 * Allows rider to mark order as delivered
 * Status transition: on_the_way/arrived → delivered
 * 
 * IMPORTANT: For COD orders, payment must be collected first
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
    $codCollected = floatval($input['cod_collected'] ?? 0);
    $signatureUrl = trim($input['signature_url'] ?? '');
    $proofImageUrl = trim($input['proof_image_url'] ?? '');
    $riderId = intval($_SESSION['rider_id']);

    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }

    // Get rider order with all details
    $stmt = $db->prepare("
        SELECT ro.id, ro.order_id, ro.delivery_status, ro.cod_amount, ro.payment_status, 
               ro.order_number, ro.customer_name
        FROM rider_orders ro
        WHERE ro.order_id = ? AND ro.rider_id = ?
    ");
    $stmt->execute([$orderId, $riderId]);
    $riderOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$riderOrder) {
        throw new Exception('Order not found or not assigned to you');
    }

    // Validate status
    if (!in_array($riderOrder['delivery_status'], ['on_the_way', 'arrived'])) {
        throw new Exception('Order must be on the way or arrived before marking as delivered. Current status: ' . $riderOrder['delivery_status']);
    }

    // For COD orders, payment MUST be collected
    if ($riderOrder['cod_amount'] > 0 && $riderOrder['payment_status'] !== 'collected') {
        throw new Exception('Payment must be collected before marking order as delivered. Please collect payment first.');
    }

    // Start transaction for atomicity
    $db->beginTransaction();

    try {
        // Update rider_orders table
        $stmt = $db->prepare("
            UPDATE rider_orders 
            SET delivery_status = 'delivered', 
                delivered_at = NOW(),
                cod_collected = ?,
                signature_image_url = ?,
                proof_of_delivery_image = ?,
                updated_at = NOW()
            WHERE id = ? AND rider_id = ?
        ");
        $stmt->execute([
            $codCollected,
            $signatureUrl ?: null,
            $proofImageUrl ?: null,
            $riderOrder['id'],
            $riderId
        ]);

        // Also update vendor_orders table to keep in sync
        // Find vendor_order for this order and update its status to 'delivered'
        $stmt = $db->prepare("
            UPDATE vendor_orders 
            SET status = 'delivered',
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);

        // Log activity
        $stmt = $db->prepare("
            INSERT INTO rider_activity_log (rider_id, action, details, created_at)
            VALUES (?, 'mark_delivered', ?, NOW())
        ");
        $details = json_encode([
            'order_id' => $orderId,
            'order_number' => $riderOrder['order_number'],
            'customer' => $riderOrder['customer_name'],
            'cod_collected' => $codCollected,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $stmt->execute([$riderId, $details]);

        // Commit transaction
        $db->commit();

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Order successfully delivered!',
            'data' => [
                'order_id' => $orderId,
                'order_number' => $riderOrder['order_number'],
                'status' => 'delivered',
                'delivered_at' => date('Y-m-d H:i:s'),
                'cod_collected' => floatval($codCollected),
                'payment_status' => $riderOrder['cod_amount'] > 0 ? 'submitted' : 'n/a'
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
