<?php
/**
 * Permanently Delete Riders AJAX Endpoint
 * Permanently removes riders from database
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
    
    // Delete rider orders first (foreign key constraint)
    $placeholders = implode(',', array_fill(0, count($rider_ids), '?'));
    $db->prepare("DELETE FROM rider_orders WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_earnings WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_penalties WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_notifications WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_reviews WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_performance WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_availability WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_activity_log WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_payouts WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_bank_details WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_bonuses WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_documents WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_support_tickets WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    $db->prepare("DELETE FROM rider_zone_assignments WHERE rider_id IN ($placeholders)")->execute($rider_ids);
    
    // Finally delete riders
    $sql = "DELETE FROM riders WHERE id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->execute($rider_ids);
    
    $deleted_count = $stmt->rowCount();

    // Log action (with error handling for missing table)
    if (isset($_SESSION['admin_id'])) {
        try {
            $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)")
                ->execute([
                    $_SESSION['admin_id'],
                    'permanent_delete_riders',
                    json_encode(['count' => $deleted_count, 'rider_ids' => $rider_ids])
                ]);
        } catch (Exception $e) {
            error_log('Admin activity logging failed: ' . $e->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $deleted_count . ' rider(s) permanently deleted',
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
