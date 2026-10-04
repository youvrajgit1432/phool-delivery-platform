<?php
/**
 * Approve Rider AJAX Endpoint
 * Changes rider status from pending to active
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rider_id'])) {
        throw new Exception('Missing rider ID');
    }

    $rider_id = intval($input['rider_id']);
    if ($rider_id <= 0) {
        throw new Exception('Invalid rider ID');
    }

    $db = getDBConnection();
    
    // Verify rider exists and is pending
    $check = $db->prepare("SELECT id, status FROM riders WHERE id = ?");
    $check->execute([$rider_id]);
    $rider = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$rider) {
        throw new Exception('Rider not found');
    }

    if ($rider['status'] !== 'pending') {
        throw new Exception('Rider is not in pending status');
    }

    // Update status to active
    $stmt = $db->prepare("UPDATE riders SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$rider_id]);

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'approve_rider',
                    json_encode(['rider_id' => $rider_id])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Rider approved successfully'
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
