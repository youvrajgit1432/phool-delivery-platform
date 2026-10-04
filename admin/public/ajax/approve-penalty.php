<?php
/**
 * Approve Penalty AJAX Endpoint
 * Approves a pending penalty and deducts from rider earnings
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

try {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['penalty_id'])) {
        throw new Exception('Missing penalty ID');
    }

    $penalty_id = intval($input['penalty_id']);
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

    if ($penalty['status'] !== 'pending') {
        throw new Exception('Penalty is not in pending status');
    }

    // Update penalty status
    $stmt = $db->prepare("UPDATE rider_penalties SET status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$penalty_id]);

    // Update rider total penalties
    $db->prepare("UPDATE riders SET total_penalties = total_penalties + ? WHERE id = ?")
        ->execute([$penalty['amount'], $penalty['rider_id']]);

    // Log action
    if (isset($_SESSION['admin_id'])) {
        $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
            ->execute([
                $_SESSION['admin_id'],
                'approve_penalty',
                json_encode(['penalty_id' => $penalty_id, 'amount' => $penalty['amount']])
            ]);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Penalty approved successfully'
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
