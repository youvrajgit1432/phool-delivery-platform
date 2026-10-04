<?php
/**
 * Resolve Penalty Appeal AJAX Endpoint
 * Handles rider appeals on penalties
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['penalty_id']) || !isset($input['approved'])) {
        throw new Exception('Missing required parameters');
    }

    $penalty_id = intval($input['penalty_id']);
    $approved = (bool) $input['approved'];

    if ($penalty_id <= 0) {
        throw new Exception('Invalid penalty ID');
    }

    $db = getDBConnection();
    
    // Fetch penalty
    $penalty_stmt = $db->prepare("SELECT * FROM rider_penalties WHERE id = ?");
    $penalty_stmt->execute([$penalty_id]);
    $penalty = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$penalty) {
        throw new Exception('Penalty not found');
    }

    if ($penalty['status'] !== 'appealed') {
        throw new Exception('Penalty is not in appealed status');
    }

    // Update penalty status
    $new_status = $approved ? 'rejected' : 'resolved';  // If appeal approved, reject original penalty; if rejected, resolve (keep penalty)
    
    $stmt = $db->prepare("UPDATE rider_penalties SET status = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $penalty_id]);

    // If appeal approved, remove from rider's total penalties
    if ($approved) {
        $db->prepare("UPDATE riders SET total_penalties = total_penalties - ? WHERE id = ?")
            ->execute([$penalty['amount'], $penalty['rider_id']]);
    }

    // Log action
    if (isset($_SESSION['admin_id'])) {
        $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
            ->execute([
                $_SESSION['admin_id'],
                'resolve_penalty_appeal',
                json_encode(['penalty_id' => $penalty_id, 'approved' => $approved])
            ]);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $approved ? 'Appeal approved - penalty removed' : 'Appeal rejected - penalty upheld'
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
