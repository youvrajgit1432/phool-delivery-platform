<?php
/**
 * Bulk Update Rider Status AJAX Endpoint
 * Changes status (active/suspended/inactive) for multiple riders
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_ids']) || !isset($input['new_status'])) {
        throw new Exception('Missing required parameters');
    }

    $rider_ids = array_filter(array_map('intval', $input['rider_ids']));
    $new_status = trim($input['new_status']);

    if (empty($rider_ids)) {
        throw new Exception('No riders selected');
    }

    if (!in_array($new_status, ['active', 'suspended', 'inactive', 'pending'])) {
        throw new Exception('Invalid status');
    }

    $db = getDBConnection();
    
    $placeholders = implode(',', array_fill(0, count($rider_ids), '?'));
    $sql = "UPDATE riders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    
    $params = [$new_status];
    $params = array_merge($params, $rider_ids);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $updated_count = $stmt->rowCount();

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'bulk_update_rider_status',
                    json_encode(['count' => $updated_count, 'status' => $new_status, 'rider_ids' => $rider_ids])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $updated_count . ' rider(s) updated to ' . $new_status,
        'updated_count' => $updated_count
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
