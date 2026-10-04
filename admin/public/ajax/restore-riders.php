<?php
/**
 * Restore Riders from Trash AJAX Endpoint
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_ids']) || !is_array($input['rider_ids'])) {
        throw new Exception('No riders selected');
    }

    $rider_ids = array_filter(array_map('intval', $input['rider_ids']));
    if (empty($rider_ids)) {
        throw new Exception('Invalid rider IDs');
    }

    $db = getDBConnection();
    
    // Restore: clear deleted_at timestamp
    $placeholders = implode(',', array_fill(0, count($rider_ids), '?'));
    $sql = "UPDATE riders SET deleted_at = NULL, updated_at = NOW() WHERE id IN ($placeholders)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($rider_ids);
    
    $restored_count = $stmt->rowCount();

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'restore_riders',
                    json_encode(['count' => $restored_count, 'rider_ids' => $rider_ids])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $restored_count . ' rider(s) restored',
        'restored_count' => $restored_count
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
