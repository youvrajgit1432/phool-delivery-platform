<?php
/**
 * Vendor Suspend/Unsuspend AJAX Handler
 */

require_once '../../bootstrap/app.php';
header('Content-Type: application/json');

try {
    $db = getDBConnection();

    $vendor_id = $_POST['vendor_id'] ?? null;
    $action = $_POST['action'] ?? null; // 'suspend' or 'unsuspend'

    if (!$vendor_id || !$action) {
        throw new Exception('Missing parameters');
    }

    $stmt = $db->prepare("SELECT id, status FROM vendors WHERE id = ? LIMIT 1");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Vendor not found');
    }

    if ($action === 'suspend') {
        $stmt = $db->prepare("UPDATE vendors SET status = 'suspended', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$vendor_id]);
        echo json_encode(['success' => true, 'message' => 'Vendor suspended']);
        exit;
    }

    if ($action === 'unsuspend') {
        $stmt = $db->prepare("UPDATE vendors SET status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$vendor_id]);
        echo json_encode(['success' => true, 'message' => 'Vendor reactivated']);
        exit;
    }

    throw new Exception('Unknown action');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
