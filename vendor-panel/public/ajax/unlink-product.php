<?php
/**
 * Unlink Vendor Product - Move to Trash
 * Soft delete with trash recovery capability
 */

// Prevent any output before JSON
ob_start();

// Set JSON header first
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress PHP errors from displaying
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (!isset($_SESSION['vendor_id'])) {
        throw new Exception('Unauthorized');
    }

    $vendor_id = (int)$_SESSION['vendor_id'];
    $map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : null;

    if (!$map_id) {
        throw new Exception('Invalid mapping ID');
    }

    // Get database config
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = \App\Database\Connection::getInstance($dbConfig);

    // Verify ownership
    $check = $db->query(
        "SELECT id FROM vendor_product_map WHERE id = ? AND vendor_id = ?",
        [$map_id, $vendor_id]
    );

    if (!$check || $check->rowCount() === 0) {
        throw new Exception('Product mapping not found');
    }

    // Move to trash (soft delete) using proper trash columns
    $stmt = $db->query(
        "UPDATE vendor_product_map SET unlinked_at = NOW(), unlinked_by = ?, status = 'inactive' 
         WHERE id = ? AND vendor_id = ?",
        [$vendor_id, $map_id, $vendor_id]
    );

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Product moved to trash successfully!'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
