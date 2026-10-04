<?php
// Admin AJAX: Add product for a specific vendor (accepts images[])

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

function send_json_and_exit($data, $status = 200)
{
    http_response_code($status);
    $buf = ob_get_clean();
    if (!empty($buf)) {
        error_log('[admin product-add stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
} 

// Locate bootstrap/app.php and optional autoload.php in a few common places
$candidates = [
    __DIR__ . '/../bootstrap/app.php',        // admin/api/../bootstrap/app.php
    __DIR__ . '/../../bootstrap/app.php',     // admin/api/../../bootstrap/app.php
    __DIR__ . '/../../admin/bootstrap/app.php', // fallback
    __DIR__ . '/../../../bootstrap/app.php',  // project-level
];
$bootstrapApp = null;
foreach ($candidates as $c) {
    if (file_exists($c)) { $bootstrapApp = $c; break; }
}
if (!$bootstrapApp) {
    send_json_and_exit(['success' => false, 'message' => 'Server configuration error: bootstrap app not found'], 500);
}

// optional autoload (not mandatory)
$autoloadCandidates = [
    dirname($bootstrapApp) . '/autoload.php',
    __DIR__ . '/../../bootstrap/autoload.php',
    __DIR__ . '/../bootstrap/autoload.php'
];
$bootstrapAutoload = null;
foreach ($autoloadCandidates as $a) { if (file_exists($a)) { $bootstrapAutoload = $a; break; } }

if ($bootstrapAutoload) require_once $bootstrapAutoload;
require_once $bootstrapApp;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $pdo = getDBConnection(); // returns PDO

    // vendor_id must be provided by admin form
    $vendorId = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    if ($vendorId <= 0) send_json_and_exit(['success' => false, 'message' => 'Missing vendor_id'], 400);

    // verify vendor exists
    $stmt = $pdo->prepare('SELECT id FROM vendors WHERE id = ?');
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) send_json_and_exit(['success' => false, 'message' => 'Vendor not found'], 404);

    $name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    $bulk_price = isset($_POST['bulk_price']) && $_POST['bulk_price'] !== '' ? $_POST['bulk_price'] : null;
    $stock = isset($_POST['quantity_in_stock']) ? (int)$_POST['quantity_in_stock'] : 0;
    $min_stock = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
    $sku = trim($_POST['product_sku'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $is_available = $status === 'active' ? 1 : 0;

    if ($name === '' || $category === '') {
        send_json_and_exit(['success' => false, 'message' => 'Required fields missing'], 400);
    }

    // handle images upload to vendor-panel public folder
    $uploadedPaths = [];
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../vendor-panel/public/assets/img/products';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $files = $_FILES['images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $files['tmp_name'][$i];
            $orig = basename($files['name'][$i]);
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $fname = uniqid('prod_') . '.' . $ext;
            $dest = $uploadDir . '/' . $fname;
            if (move_uploaded_file($tmp, $dest)) {
                // Use environment-aware web path
                $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                                strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
                $basePrefix = $isLocalhost ? '/phool-delivery-platform' : '';
                $webPath = $basePrefix . '/vendor-panel/public/assets/img/products/' . $fname;
                $uploadedPaths[] = $webPath;
            }
        }
    }

    // generate SKU if empty
    if (empty($sku)) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $datePart = date('Ymd');
        $base = $prefix . $datePart;
        $q = $pdo->prepare('SELECT product_sku FROM vendor_products WHERE product_sku LIKE ? ORDER BY id DESC LIMIT 1');
        $q->execute([$base . '%']);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $serial = 1;
        if (!empty($row['product_sku'])) {
            if (preg_match('/(\d{3})$/', $row['product_sku'], $m)) {
                $serial = (int)$m[1] + 1;
            }
        }
        $sku = $base . str_pad($serial, 3, '0', STR_PAD_LEFT);
    }

    // prepare insert
    $fields = [
        'vendor_id' => $vendorId,
        'product_name' => $name,
        'product_sku' => $sku,
        'description' => $description,
        'category' => $category,
        'price' => (float)$price,
        'discount_price' => $bulk_price !== null ? (float)$bulk_price : null,
        'quantity_in_stock' => (int)$stock,
        'min_stock_level' => (int)$min_stock,
        'primary_image_url' => $uploadedPaths[0] ?? null,
        'is_available' => $is_available,
        'status' => $status
    ];

    // build SQL dynamically to exclude nulls
    $cols = [];
    $placeholders = [];
    $values = [];
    foreach ($fields as $k => $v) {
        if ($v === null) continue;
        $cols[] = $k;
        $placeholders[] = '?';
        $values[] = $v;
    }

    $sql = 'INSERT INTO vendor_products (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $insertId = $pdo->lastInsertId();

    // insert product images
    if (!empty($uploadedPaths)) {
        $imgStmt = $pdo->prepare('INSERT INTO vendor_product_images (product_id, image_url, is_primary, display_order, alt_text) VALUES (?, ?, ?, ?, ?)');
        foreach ($uploadedPaths as $idx => $p) {
            $isPrimary = $idx === 0 ? 1 : 0;
            $displayOrder = $idx;
            $imgStmt->execute([$insertId, $p, $isPrimary, $displayOrder, null]);
        }
    }

    send_json_and_exit(['success' => true, 'product_id' => $insertId], 200);

} catch (Throwable $e) {
    send_json_and_exit(['success' => false, 'message' => $e->getMessage()], 500);
}

?>
