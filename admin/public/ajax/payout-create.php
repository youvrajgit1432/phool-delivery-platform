<?php
/**
 * Payout Create AJAX Handler
 */

require_once '../../bootstrap/app.php';

header('Content-Type: application/json');

try {
    $vendor_id = $_POST['vendor_id'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    $reference_id = trim($_POST['reference_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$vendor_id || $amount <= 0) {
        throw new Exception('Invalid vendor or amount');
    }
    
    // Check vendor exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Vendor not found');
    }
    
    // Create payout record
    $stmt = $db->prepare("
        INSERT INTO vendor_payouts (
            vendor_id, amount, reference_id, notes, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    
    $stmt->execute([
        $vendor_id, $amount, $reference_id, $notes
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payout created successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
