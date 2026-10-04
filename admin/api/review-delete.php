<?php
/**
 * Review Delete API
 * Delete a vendor review
 */

require_once '../bootstrap/app.php';
require_once '../app/middleware/AdminMiddleware.php';

// Check admin auth
requireAuth();

header('Content-Type: application/json');

$review_id = $_POST['review_id'] ?? null;

if (!$review_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Review ID required']);
    exit;
}

try {
    $db = getDBConnection();
    
    $query = "DELETE FROM vendor_reviews WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$review_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
