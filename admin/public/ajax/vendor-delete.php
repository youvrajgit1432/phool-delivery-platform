<?php
/**
 * Vendor Delete AJAX Handler
 */

require_once '../../bootstrap/app.php';
header('Content-Type: application/json');

try {
    $db = getDBConnection();

    $vendor_id = $_POST['vendor_id'] ?? null;
    if (!$vendor_id) {
        throw new Exception('Vendor ID is required');
    }

    // Check if vendor exists
    $stmt = $db->prepare("SELECT id, deleted_at FROM vendors WHERE id = ? LIMIT 1");
    $stmt->execute([$vendor_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Vendor not found');
    }

    // If already trashed, return success
    if (!empty($row['deleted_at'])) {
        echo json_encode(['success' => true, 'message' => 'Vendor already in trash']);
        exit;
    }

    // Soft delete: set deleted_at and update status to inactive
    $stmt = $db->prepare("UPDATE vendors SET deleted_at = NOW(), status = 'inactive', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$vendor_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Vendor moved to trash'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
