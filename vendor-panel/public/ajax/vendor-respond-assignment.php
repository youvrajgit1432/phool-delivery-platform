<?php
/**
 * Vendor Panel - Respond to Order Assignment
 * Endpoint: vendor-panel/public/ajax/vendor-respond-assignment.php
 * 
 * Allows vendor to accept or reject an assigned order
 * POST /vendor-panel/public/ajax/vendor-respond-assignment.php
 * 
 * Parameters:
 *  - vendor_order_id: ID of the order in vendor_orders table
 *  - response: 'accepted' or 'rejected'
 *  - vendor_notes: Optional notes from vendor
 */

// Prevent accidental HTML/debug output from breaking JSON responses
ini_set('display_errors', '0');
// Start output buffering so any stray HTML/debug output can be discarded
ob_start();
// Always send JSON content type for this endpoint
header('Content-Type: application/json');

// Load autoload to define constants like CONFIG_PATH, BASE_PATH, etc.
require_once __DIR__ . '/../../bootstrap/autoload.php';
require_once __DIR__ . '/../../bootstrap/app.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify authentication
if (!isset($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$vendor_order_id = isset($data['vendor_order_id']) ? (int)$data['vendor_order_id'] : null;
$response = isset($data['response']) ? strtolower(trim($data['response'])) : null;
$vendor_notes = isset($data['vendor_notes']) ? trim($data['vendor_notes']) : '';

$vendor_id = (int)$_SESSION['vendor_id'];

// Validate input
if (!$vendor_order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'vendor_order_id is required']);
    exit;
}

if (!in_array($response, ['accepted', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'response must be "accepted" or "rejected"']);
    exit;
}

try {
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = \App\Database\Connection::getInstance($dbConfig);
    $pdo = $db->getConnection();

    // Start transaction
    $pdo->beginTransaction();

    // Verify order belongs to this vendor and is still in 'assigned' state
    $stmt = $pdo->prepare("
        SELECT vo.id, vo.order_id 
        FROM vendor_orders vo
        WHERE vo.id = ? AND vo.vendor_id = ? AND vo.status = 'assigned'
    ");
    $stmt->execute([$vendor_order_id, $vendor_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Check if order exists but with different status (for debugging)
        $debug_stmt = $pdo->prepare("
            SELECT vo.id, vo.status, vo.vendor_id FROM vendor_orders vo
            WHERE vo.id = ?
        ");
        $debug_stmt->execute([$vendor_order_id]);
        $debug_order = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->rollBack();
        http_response_code(404);
        
        if ($debug_order) {
            error_log("Order found but conditions not met: " . json_encode($debug_order) . " | Logged vendor_id: $vendor_id");
            echo json_encode([
                'success' => false, 
                'message' => 'Order status is ' . $debug_order['status'] . ' (expected: assigned) or vendor mismatch',
                'debug' => [
                    'order_status' => $debug_order['status'],
                    'order_vendor_id' => $debug_order['vendor_id'],
                    'logged_vendor_id' => $vendor_id
                ]
            ]);
        } else {
            error_log("Order not found: vendor_order_id=$vendor_order_id, vendor_id=$vendor_id");
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
        exit;
    }

    // Map response to status value for vendor_orders
    $new_status = $response === 'accepted' ? 'accepted' : 'cancelled';
    $accepted_at = $response === 'accepted' ? date('Y-m-d H:i:s') : null;

    // Update vendor_orders table with vendor's response
    $vendor_order_update = $pdo->prepare("
        UPDATE vendor_orders 
        SET status = ?, notes = ?, accepted_at = ?, updated_at = NOW()
        WHERE id = ? AND vendor_id = ?
    ");
    $vendor_order_update->execute([$new_status, $vendor_notes, $accepted_at, $vendor_order_id, $vendor_id]);

    // If accepted, also update main orders table status
    if ($response === 'accepted') {
        $orders_update = $pdo->prepare("
            UPDATE orders 
            SET assign_status = 'accepted', updated_at = NOW()
            WHERE id = ?
        ");
        $orders_update->execute([$order['order_id']]);
    } else {
        // If rejected, mark as unassigned
        $orders_update = $pdo->prepare("
            UPDATE orders 
            SET assign_status = 'unassigned', assigned_vendor_id = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        $orders_update->execute([$order['order_id']]);
    }

    $pdo->commit();

    // Clean any buffered output and return success response
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'message' => 'Order ' . ucfirst($response) . ' successfully',
        'status' => $new_status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Vendor respond assignment error: ' . $e->getMessage());
    http_response_code(500);
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process response',
        'error' => $e->getMessage()
    ]);
}
