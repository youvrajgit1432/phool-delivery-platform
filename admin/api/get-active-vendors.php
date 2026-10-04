<?php
/**
 * API Endpoint: Get Active Vendors
 * Returns list of active vendors for selection
 */

header('Content-Type: application/json');
require_once '../../config/Database.php';

try {
    $db = Database::getInstance();
    
    $vendors = $db->query(
        "SELECT id, store_name, store_category, logo_url, banner_url, average_rating, 
                total_reviews, status, email
         FROM vendors 
         WHERE status IN ('active', 'pending')
         ORDER BY average_rating DESC, store_name ASC"
    )->fetchAll();

    echo json_encode([
        'success' => true,
        'vendors' => $vendors ?: []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
