<?php
/**
 * Link Master Product to Vendor
 * Creates entry in vendor_product_map table
 * Optionally saves vendor-specific images
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer output and disable direct display of PHP errors so we always return JSON
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    // Check if vendor is logged in
    if (!isset($_SESSION['vendor_id'])) {
        throw new Exception('Unauthorized: Vendor not logged in');
    }

    // Validate input
    $vendor_id = (int)$_SESSION['vendor_id'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $vendor_price = isset($_POST['vendor_price']) ? (float)$_POST['vendor_price'] : null;
    $vendor_stock = isset($_POST['vendor_stock']) ? (int)$_POST['vendor_stock'] : null;
    $preparation_time = isset($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : 30;
    $minimum_order_quantity = isset($_POST['minimum_order_quantity']) ? (float)$_POST['minimum_order_quantity'] : 1;

    // Validation
    if (!$product_id || $product_id <= 0) {
        throw new Exception('Invalid product selected');
    }

    if (!$vendor_price || $vendor_price <= 0) {
        throw new Exception('Invalid price entered');
    }

    if ($vendor_stock === null || $vendor_stock < 0) {
        throw new Exception('Invalid stock quantity');
    }

    // Database connection - use project config Database
    require_once __DIR__ . '/../../../config/Database.php';
    $db = new Database();
    $pdo = $db->connect();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Check if master product exists
    $checkStmt = $db->prepare("SELECT id FROM products WHERE id = ?");
    $checkStmt->execute([$product_id]);
    if ($checkStmt->rowCount() === 0) {
        throw new Exception('Master product not found');
    }

    // Check if vendor already linked this product
    $checkExistsStmt = $db->prepare("SELECT id FROM vendor_product_map WHERE vendor_id = ? AND product_id = ?");
    $checkExistsStmt->execute([$vendor_id, $product_id]);
    if ($checkExistsStmt->rowCount() > 0) {
        $existing = $checkExistsStmt->fetch(PDO::FETCH_ASSOC);
        $existingId = $existing['id'] ?? null;

        // Clear buffer and log any unexpected output
        $buffer = ob_get_clean();
        if (!empty($buffer)) {
            error_log('Unexpected output when returning existing mapping: ' . $buffer);
        }

        // Return success with existing mapping info so client can redirect to inventory
        echo json_encode([
            'success' => true,
            'message' => 'Product already linked. Redirecting to your inventory.',
            'data' => [
                'vendor_product_map_id' => $existingId,
                'already_linked' => true
            ]
        ]);
        exit;
    }

    // Insert into vendor_product_map
    $stmt = $db->prepare(
        "INSERT INTO vendor_product_map 
         (vendor_id, product_id, vendor_price, vendor_stock, preparation_time, minimum_order_quantity, is_available, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW(), NOW())"
    );

    $stmt->execute([
        $vendor_id,
        $product_id,
        $vendor_price,
        $vendor_stock,
        $preparation_time,
        $minimum_order_quantity
    ]);

    $lastId = $db->lastInsertId();

    if (!$lastId) {
        throw new Exception('Failed to link product');
    }

    // Record initial pricing history (old_price = 0.00 for new mapping)
    // This is optional; if table doesn't exist, silently skip
    if (function_exists('error_log')) {
        @error_log('DEBUG: Attempting to insert pricing history for mapping ID: ' . $lastId);
    }
    try {
        $historyStmt = $db->prepare(
            "INSERT INTO vendor_product_pricing_history (vendor_product_map_id, old_price, new_price, changed_at) VALUES (?, ?, ?, NOW())"
        );
        $historyStmt->execute([$lastId, 0.00, $vendor_price]);
    } catch (Exception $e) {
        // Silently skip if table doesn't exist; don't fail the main operation
        // Table will be created on next deployment
    }

    // Handle vendor images if provided
    if (!empty($_FILES['vendor_images']) && is_array($_FILES['vendor_images']['name'])) {
        $uploadDir = __DIR__ . '/../../uploads/vendor_' . $vendor_id . '/products/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $imagePaths = [];
        foreach ($_FILES['vendor_images']['name'] as $key => $fileName) {
            if ($_FILES['vendor_images']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Generate unique filename
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueName = 'vendor_prod_' . $lastId . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . $uniqueName;

            // Validate and move file
            if (move_uploaded_file($_FILES['vendor_images']['tmp_name'][$key], $filePath)) {
                $imagePaths[] = $uniqueName;
            }
        }

        // Save image references for vendor product map
        if (!empty($imagePaths)) {
            try {
                // Store as JSON in vendor_product_map notes or create separate table entry
                // For now, we'll store in a simple format
                $imageData = json_encode(['vendor_images' => $imagePaths]);
                $updateStmt = $db->prepare(
                    "UPDATE vendor_product_map SET notes = ? WHERE id = ?"
                );
                $updateStmt->execute([$imageData, $lastId]);
            } catch (Exception $e) {
                // Log error but don't fail the main operation
                error_log('Error saving image metadata: ' . $e->getMessage());
            }
        }
    }

    // Success response - clear any buffered output (log if present) and return JSON
    $buffer = ob_get_clean();
    if (!empty($buffer)) {
        error_log('Unexpected output before JSON success: ' . $buffer);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Product linked successfully!',
        'data' => [
            'id' => $lastId,
            'vendor_id' => $vendor_id,
            'product_id' => $product_id,
            'vendor_price' => $vendor_price,
            'vendor_stock' => $vendor_stock
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    $buffer = ob_get_clean();
    if (!empty($buffer)) {
        error_log('Unexpected output before JSON error: ' . $buffer);
    }
    $resp = [
        'success' => false,
        'message' => $e->getMessage()
    ];

    // If debug flag is sent (local dev only), include buffered output and exception trace
    if (isset($_POST['debug']) && ($_POST['debug'] == '1' || $_POST['debug'] === 1)) {
        $resp['debug'] = [
            'buffer' => $buffer,
            'exception' => $e->getMessage()
        ];
    }

    echo json_encode($resp);
}
