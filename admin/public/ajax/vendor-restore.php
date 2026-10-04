<?php
/**
 * Vendor Restore AJAX Handler
 */

require_once '../../bootstrap/app.php';
header('Content-Type: application/json');

try {
    $db = getDBConnection();

    $vendor_id = $_POST['vendor_id'] ?? null;
    if (!$vendor_id) {
        throw new Exception('Vendor ID is required');
    }

    // Check if vendor exists and is trashed
    $stmt = $db->prepare("SELECT id, deleted_at FROM vendors WHERE id = ? LIMIT 1");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Vendor not found');
    }

    if (empty($row['deleted_at'])) {
        echo json_encode(['success' => true, 'message' => 'Vendor is not in trash']);
        exit;
    }

    // Restore vendor: clear deleted_at and set status active
    $stmt = $db->prepare("UPDATE vendors SET deleted_at = NULL, status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$vendor_id]);

    echo json_encode(['success' => true, 'message' => 'Vendor restored successfully']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
