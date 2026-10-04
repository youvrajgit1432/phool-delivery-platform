<?php
/**
 * Get Order Counts for All Status Filters - AJAX
 * Returns count of orders for each status
 */
use App\Database\Connection;

ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $vendor_id = (int)$_SESSION['vendor_id'];
    
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = Connection::getInstance($dbConfig);
    
    // Define status filters
    $statuses = ['active', 'assigned', 'accepted', 'preparing', 'ready', 'picked_up', 'delivered', 'cancelled', 'all'];
    $counts = [];
    
    foreach ($statuses as $status) {
        $whereClause = 'WHERE vo.vendor_id = ?';
        $params = [$vendor_id];
        
        if ($status === 'active') {
            // Show all except cancelled, picked_up, and delivered (completed)
            $whereClause .= ' AND vo.status NOT IN (?, ?, ?)';
            $params[] = 'cancelled';
            $params[] = 'picked_up';
            $params[] = 'delivered';
        } elseif ($status !== 'all') {
            // Show specific status
            $whereClause .= ' AND vo.status = ?';
            $params[] = $status;
        }
        
        $query = "SELECT COUNT(*) as count FROM vendor_orders vo $whereClause";
        $stmt = $db->query($query, $params);
        $result = $stmt->fetch();
        $counts[$status] = (int)($result['count'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'counts' => $counts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching order counts: ' . $e->getMessage()
    ]);
}
