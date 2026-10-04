<?php
/**
 * Review Status Update API
 * Update vendor review status (approve/reject)
 */

require_once '../bootstrap/app.php';
require_once '../app/middleware/AdminMiddleware.php';

// Check admin auth
requireAuth();

header('Content-Type: application/json');

$review_id = $_POST['review_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$review_id || !in_array($status, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDBConnection();
    
    $query = "UPDATE vendor_reviews SET status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$status, $review_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Review status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update review']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
