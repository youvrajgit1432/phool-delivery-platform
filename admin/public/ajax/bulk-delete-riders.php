<?php
/**
 * Bulk Delete Riders AJAX Endpoint
 * Soft-deletes selected riders (moves to trash)
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

// Check authentication and return JSON
try {
    requireAuth();
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON payload
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_ids']) || !is_array($input['rider_ids']) || empty($input['rider_ids'])) {
        throw new Exception('No riders selected');
    }

    $rider_ids = array_filter(array_map('intval', $input['rider_ids']));
    if (empty($rider_ids)) {
        throw new Exception('Invalid rider IDs');
    }

    $db = getDBConnection();
    
    // Soft delete: set deleted_at timestamp
    $placeholders = implode(',', array_fill(0, count($rider_ids), '?'));
    $sql = "UPDATE riders SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($rider_ids);
    
    $deleted_count = $stmt->rowCount();

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'bulk_delete_riders',
                    json_encode(['count' => $deleted_count, 'rider_ids' => $rider_ids])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $deleted_count . ' rider(s) deleted',
        'deleted_count' => $deleted_count
    ]);

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
