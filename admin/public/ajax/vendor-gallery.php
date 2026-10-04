<?php
/**
 * Vendor Gallery Images Fetch (Admin)
 */

header('Content-Type: application/json');
require_once '../../bootstrap/app.php';

$db = getDBConnection();

try {
    $vendor_id = $_GET['vendor_id'] ?? null;
    
    if (!$vendor_id) {
        throw new Exception('Vendor ID required');
    }
    
    $stmt = $db->prepare("SELECT id, image_url, alt_text, image_type FROM vendor_gallery_images WHERE vendor_id = ? ORDER BY display_order ASC");
    $stmt->execute([$vendor_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'images' => $images ?? []
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
