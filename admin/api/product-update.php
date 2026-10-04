<?php
/**
 * AJAX: Update Product (Admin Panel)
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

function send_json_and_exit($data, $status = 200)
{
    http_response_code($status);
    $buf = ob_get_clean();
    if (!empty($buf)) {
        error_log('[product-update stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
}

// Use __DIR__ for proper path resolution (admin has its own bootstrap folder)
$bootstrapAutoload = __DIR__ . '/../bootstrap/autoload.php';
$bootstrapApp = __DIR__ . '/../bootstrap/app.php';

if (!file_exists($bootstrapAutoload) || !file_exists($bootstrapApp)) {
    send_json_and_exit(['success' => false, 'message' => "Server configuration error: bootstrap files missing\nAutoload: $bootstrapAutoload\nApp: $bootstrapApp"], 500);
}

require_once $bootstrapAutoload;
require_once $bootstrapApp;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Get admin ID from session - for admin panel authorization
$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    send_json_and_exit(['success' => false, 'message' => 'Unauthorized - Admin session required'], 401);
}

try {
    $productId = (int)($_POST['product_id'] ?? 0);
    if (!$productId) {
        send_json_and_exit(['success' => false, 'message' => 'Product ID required'], 400);
    }

    // Get PDO database connection (admin uses getDBConnection)
    $db = getDBConnection();

    // Verify product exists
    $stmt = $db->prepare('SELECT id, vendor_id FROM vendor_products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        send_json_and_exit(['success' => false, 'message' => 'Product not found'], 404);
    }

    // Prepare update data
    $updateData = [];

    // Basic fields
    if (!empty($_POST['product_name'])) {
        $updateData['product_name'] = trim($_POST['product_name']);
    }
    if (!empty($_POST['category'])) {
        $updateData['category'] = trim($_POST['category']);
    }
    if (!empty($_POST['description'])) {
        $updateData['description'] = trim($_POST['description']);
    }
    if (!empty($_POST['price'])) {
        $updateData['price'] = (float)$_POST['price'];
    }
    
    // Accept bulk_price, discount_price, or discount
    $discountVal = $_POST['bulk_price'] ?? ($_POST['discount_price'] ?? ($_POST['discount'] ?? null));
    if (!empty($discountVal) || $discountVal === '0' || $discountVal === 0) {
        $updateData['discount_price'] = (float)$discountVal;
    }
    
    if (isset($_POST['quantity_in_stock'])) {
        $updateData['quantity_in_stock'] = (int)$_POST['quantity_in_stock'];
    }
    if (isset($_POST['min_stock_level'])) {
        $updateData['min_stock_level'] = (int)$_POST['min_stock_level'];
    }
    
    // Handle status
    if (isset($_POST['is_available'])) {
        $updateData['is_available'] = (int)($_POST['is_available'] ?? 0);
        $updateData['status'] = $updateData['is_available'] ? 'active' : 'inactive';
    } elseif (isset($_POST['status'])) {
        $updateData['status'] = $_POST['status'] === 'active' ? 'active' : 'inactive';
    }

    // Update timestamp
    $updateData['updated_at'] = date('Y-m-d H:i:s');

    if (!empty($updateData)) {
        $placeholders = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateData)));
        $stmt = $db->prepare("UPDATE vendor_products SET $placeholders WHERE id = ?");
        $stmt->execute(array_merge(array_values($updateData), [$productId]));
    }

    send_json_and_exit(['success' => true, 'product_id' => $productId, 'message' => 'Product updated successfully'], 200);

} catch (Throwable $e) {
    error_log('[product-update] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    send_json_and_exit(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
