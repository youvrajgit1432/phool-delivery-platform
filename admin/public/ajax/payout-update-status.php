<?php
/**
 * Payout Status Update AJAX Handler
 */

require_once '../../bootstrap/app.php';

header('Content-Type: application/json');

try {
    $payout_id = $_POST['payout_id'] ?? null;
    $status = $_POST['status'] ?? null;
    
    $allowed_statuses = ['pending', 'approved', 'completed', 'rejected'];
    
    if (!$payout_id || !$status || !in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid payout ID or status');
    }
    
    // Update payout status
    $stmt = $db->prepare("
        UPDATE vendor_payouts 
        SET status = ?, processed_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $payout_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payout status updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
