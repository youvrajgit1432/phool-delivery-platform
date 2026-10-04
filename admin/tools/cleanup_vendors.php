<?php
// admin/tools/cleanup_vendors.php
// Permanently delete vendors soft-deleted more than X days ago and remove related images/files.

require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';

requireRole(['super_admin']);
$pdo = getDBConnection();

$days = 30; // soft-delete retention period
$threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

$stmt = $pdo->prepare('SELECT id, logo_url FROM vendors WHERE deleted_at IS NOT NULL AND deleted_at < ?');
$stmt->execute([$threshold]);
$rows = $stmt->fetchAll();

foreach ($rows as $r) {
    $vendorId = $r['id'];
    // delete product images files
    $pstmt = $pdo->prepare('SELECT primary_image_url FROM vendor_products WHERE vendor_id = ?');
    $pstmt->execute([$vendorId]);
    $prods = $pstmt->fetchAll();
    foreach ($prods as $p) {
        if (!empty($p['primary_image_url'])) {
            $path = __DIR__ . '/../../' . $p['primary_image_url'];
            if (is_file($path)) @unlink($path);
        }
    }

    // delete vendor logo
    if (!empty($r['logo_url'])) {
        $vpath = __DIR__ . '/../../' . $r['logo_url'];
        if (is_file($vpath)) @unlink($vpath);
    }

    // delete related rows (rely on FK cascade where configured)
    $pdo->prepare('DELETE FROM vendor_product_images WHERE product_id IN (SELECT id FROM vendor_products WHERE vendor_id = ?)')->execute([$vendorId]);
    $pdo->prepare('DELETE FROM vendor_products WHERE vendor_id = ?')->execute([$vendorId]);
    $pdo->prepare('DELETE FROM vendor_payouts WHERE vendor_id = ?')->execute([$vendorId]);
    $pdo->prepare('DELETE FROM vendor_documents WHERE vendor_id = ?')->execute([$vendorId]);
    $pdo->prepare('DELETE FROM vendor_notifications WHERE vendor_id = ?')->execute([$vendorId]);

    // finally delete vendor row
    $pdo->prepare('DELETE FROM vendors WHERE id = ?')->execute([$vendorId]);
}

echo "Cleanup completed. Processed: " . count($rows) . " vendors.\n";
