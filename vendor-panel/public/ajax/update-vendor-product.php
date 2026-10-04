<?php
/**
 * Update Vendor Product Mapping
 * Updates price, stock, and availability in vendor_product_map
 */

// Set JSON header first
header('Content-Type: application/json');

// Prevent any output before JSON
ob_start();

// Suppress PHP errors from displaying
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

try {
    if (!isset($_SESSION['vendor_id'])) {
        throw new Exception('Unauthorized');
    }

    $vendor_id = (int)$_SESSION['vendor_id'];
    $map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : null;
    $vendor_price = isset($_POST['vendor_price']) ? (float)$_POST['vendor_price'] : null;
    $vendor_stock = isset($_POST['vendor_stock']) ? (int)$_POST['vendor_stock'] : null;
    $preparation_time = isset($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (!$map_id) {
        throw new Exception('Invalid mapping ID');
    }

    require_once __DIR__ . '/../../bootstrap/autoload.php';
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $db = \App\Database\Connection::getInstance($dbConfig);

    // Verify ownership and fetch existing price
    $check = $db->query(
        "SELECT id, vendor_price FROM vendor_product_map WHERE id = ? AND vendor_id = ?",
        [$map_id, $vendor_id]
    );

    if (!$check || $check->rowCount() === 0) {
        throw new Exception('Product mapping not found');
    }

    // Update
    $updateFields = [];
    $params = [];

    if ($vendor_price !== null && $vendor_price > 0) {
        $updateFields[] = "vendor_price = ?";
        $params[] = $vendor_price;
    }

    if ($vendor_stock !== null && $vendor_stock >= 0) {
        $updateFields[] = "vendor_stock = ?";
        $params[] = $vendor_stock;
    }

    if ($preparation_time !== null && $preparation_time >= 0) {
        $updateFields[] = "preparation_time = ?";
        $params[] = $preparation_time;
    }

    $updateFields[] = "is_available = ?";
    $params[] = $is_available;

    $updateFields[] = "updated_at = NOW()";

    if (empty($updateFields)) {
        throw new Exception('Nothing to update');
    }

    $params[] = $map_id;

    $sql = "UPDATE vendor_product_map SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // If vendor_price was updated, insert pricing history (optional)
    try {
        if ($vendor_price !== null && $vendor_price > 0) {
            // fetch old price
            $old = 0.00;
            $row = $check->fetch(\PDO::FETCH_ASSOC);
            if ($row && isset($row['vendor_price'])) {
                $old = (float)$row['vendor_price'];
            }

            if (abs($old - $vendor_price) > 0.0001) {
                $histStmt = $db->prepare(
                    "INSERT INTO vendor_product_pricing_history (vendor_product_map_id, old_price, new_price, changed_at) VALUES (?, ?, ?, NOW())"
                );
                $histStmt->execute([$map_id, $old, $vendor_price]);
            }
        }
    } catch (Exception $e) {
        // Silently skip if table doesn't exist; pricing history is optional
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully!'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
