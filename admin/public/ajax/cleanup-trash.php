<?php
/**
 * Auto-delete orders that have been in trash for 30 days
 * File: admin/public/ajax/cleanup-trash.php
 * 
 * This should be called periodically (via cron job or page load)
 */

ob_start();
header('Content-Type: application/json');

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';
    requireAuth();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Bootstrap error: ' . $e->getMessage()]));
}

try {
    $pdo = getDBConnection();
    
    // Find orders deleted more than 30 days ago
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    // First, get the count of orders to be deleted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE deleted_at IS NOT NULL AND deleted_at < ?
    ");
    $stmt->execute([$thirty_days_ago]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_to_delete = $result['count'] ?? 0;
    
    if ($count_to_delete > 0) {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get order IDs to delete
        $stmt = $pdo->prepare("
            SELECT id FROM orders 
            WHERE deleted_at IS NOT NULL AND deleted_at < ?
        ");
        $stmt->execute([$thirty_days_ago]);
        $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($orders)) {
            $placeholders = implode(',', array_fill(0, count($orders), '?'));
            
            // Delete order items
            try {
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
                $stmt->execute($orders);
            } catch (Exception $e) {
                error_log('Warning: Could not delete order_items: ' . $e->getMessage());
            }
            
            // Delete vendor_order_items
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM vendor_order_items 
                    WHERE vendor_order_id IN (SELECT id FROM vendor_orders WHERE order_id IN ($placeholders))
                ");
                $stmt->execute($orders);
            } catch (Exception $e) {
                error_log('Warning: Could not delete vendor_order_items: ' . $e->getMessage());
            }
            
            // Delete vendor_orders
            try {
                $stmt = $pdo->prepare("DELETE FROM vendor_orders WHERE order_id IN ($placeholders)");
                $stmt->execute($orders);
            } catch (Exception $e) {
                error_log('Warning: Could not delete vendor_orders: ' . $e->getMessage());
            }
            
            // Delete vendor_notifications
            try {
                $stmt = $pdo->prepare("DELETE FROM vendor_notifications WHERE order_id IN ($placeholders)");
                $stmt->execute($orders);
            } catch (Exception $e) {
                error_log('Warning: Could not delete vendor_notifications: ' . $e->getMessage());
            }
            
            // Finally, delete the orders
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
            $stmt->execute($orders);
        }
        
        // Commit transaction
        $pdo->commit();
        
        error_log("Auto-cleanup: Permanently deleted $count_to_delete orders from trash (older than 30 days)");
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Auto-deleted $count_to_delete expired orders from trash",
            'deleted_count' => $count_to_delete
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'No orders to auto-delete',
            'deleted_count' => 0
        ]);
    }
    
} catch (Exception $e) {
    // Rollback on error
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rbError) {
        error_log('Rollback failed: ' . $rbError->getMessage());
    }
    
    error_log('Auto-cleanup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error during auto-cleanup: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>
