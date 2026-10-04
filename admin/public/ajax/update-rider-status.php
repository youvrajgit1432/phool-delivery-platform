<?php
/**
 * Update Single Rider Status AJAX Endpoint
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_id']) || !isset($input['new_status'])) {
        throw new Exception('Missing required parameters');
    }

    $rider_id = intval($input['rider_id']);
    $new_status = trim($input['new_status']);

    if ($rider_id <= 0) {
        throw new Exception('Invalid rider ID');
    }

    if (!in_array($new_status, ['active', 'suspended', 'inactive', 'pending'])) {
        throw new Exception('Invalid status');
    }

    $db = getDBConnection();
    
    // Verify rider exists
    $check = $db->prepare("SELECT id FROM riders WHERE id = ?");
    $check->execute([$rider_id]);
    if (!$check->fetch()) {
        throw new Exception('Rider not found');
    }

    // Update status
    $stmt = $db->prepare("UPDATE riders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $rider_id]);

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'update_rider_status',
                    json_encode(['rider_id' => $rider_id, 'status' => $new_status])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Rider status updated to ' . $new_status
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
