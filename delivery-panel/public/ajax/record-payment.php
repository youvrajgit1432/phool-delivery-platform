<?php
/**
 * Record COD Payment Collection AJAX Endpoint
 * Allows rider to record COD payment collection from customer
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
    $amountCollected = floatval($input['amount_collected'] ?? 0);
    $varianceReason = trim($input['variance_reason'] ?? '');
    $riderId = intval($_SESSION['rider_id']);

    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }

    if ($amountCollected < 0) {
        throw new Exception('Amount collected must be >= 0');
    }

    // Get rider order with payment details
    $stmt = $db->prepare("
        SELECT ro.id, ro.order_id, ro.cod_amount, ro.cod_collected, ro.payment_status, ro.order_number
        FROM rider_orders ro
        WHERE ro.order_id = ? AND ro.rider_id = ?
    ");
    $stmt->execute([$orderId, $riderId]);
    $riderOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$riderOrder) {
        throw new Exception('Order not found or not assigned to you');
    }

    if ($riderOrder['cod_amount'] <= 0) {
        throw new Exception('This order does not have COD payment');
    }

    if ($riderOrder['payment_status'] === 'collected') {
        throw new Exception('Payment already collected for this order');
    }

    // Use stored procedure for safe transaction
    $stmt = $db->prepare("CALL sp_record_cod_collection(?, ?, ?, ?, @p_success, @p_message)");
    $stmt->execute([$riderOrder['id'], $riderId, $amountCollected, $varianceReason]);
    
    // Get procedure output
    $result = $db->query("SELECT @p_success as success, @p_message as message")->fetch(PDO::FETCH_ASSOC);
    
    if (!$result['success']) {
        throw new Exception($result['message'] ?? 'Failed to record payment collection');
    }

    $variance = $riderOrder['cod_amount'] - $amountCollected;
    $variancePercent = ($riderOrder['cod_amount'] > 0) ? ($variance / $riderOrder['cod_amount']) * 100 : 0;

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'order_id' => $orderId,
            'order_number' => $riderOrder['order_number'],
            'cod_amount' => floatval($riderOrder['cod_amount']),
            'amount_collected' => floatval($amountCollected),
            'variance' => floatval($variance),
            'variance_percent' => round($variancePercent, 2),
            'payment_status' => 'collected',
            'recorded_at' => date('Y-m-d H:i:s')
        ]
    ]);
    ob_end_flush();
    exit;

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    ob_end_flush();
    exit;
}
?>
