<?php
/**
 * API Endpoint: Check Vendor Product Availability
 * Purpose: Validate if vendor has products with required quantity
 * Method: POST
 * Params: vendor_id, order_items (array of product_id => quantity)
 */

header('Content-Type: application/json');
require_once '../../config/Database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $order_items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if (!$vendor_id) {
        throw new Exception('Vendor ID is required');
    }

    if (empty($order_items)) {
        throw new Exception('Order items are required');
    }

    $db = Database::getInstance();
    
    // Get vendor info
    $vendor = $db->query(
        "SELECT id, store_name, logo_url, banner_url, average_rating, total_reviews, status
         FROM vendors WHERE id = ? AND status IN ('active', 'pending')",
        [$vendor_id]
    )->fetch();

    if (!$vendor) {
        throw new Exception('Vendor not found or inactive');
    }

    // Check each product in the order
    $products_status = [];
    $all_available = true;
    $total_applicable_items = 0;

    foreach ($order_items as $product_id => $required_qty) {
        $product_map = $db->query(
            "SELECT vpm.*, p.name_en, p.name_ne, p.primary_image_url
             FROM vendor_product_map vpm
             LEFT JOIN products p ON vpm.product_id = p.id
             WHERE vpm.vendor_id = ? AND vpm.product_id = ? AND vpm.status = 'active'",
            [$vendor_id, (int)$product_id]
        )->fetch();

        if (!$product_map || !$product_map['is_available']) {
            $products_status[] = [
                'product_id' => $product_id,
                'available' => false,
                'message' => 'Not available with this vendor',
                'stock' => 0,
                'price' => null
            ];
            $all_available = false;
        } else {
            $stock = (int)$product_map['vendor_stock'];
            $meets_moq = $required_qty >= (float)$product_map['minimum_order_quantity'];
            $has_stock = $stock >= $required_qty;

            $products_status[] = [
                'product_id' => $product_id,
                'available' => true,
                'has_stock' => $has_stock,
                'meets_moq' => $meets_moq,
                'stock' => $stock,
                'required' => $required_qty,
                'price' => (float)$product_map['vendor_price'],
                'moq' => (float)$product_map['minimum_order_quantity'],
                'prep_time' => (int)$product_map['preparation_time'],
                'message' => $has_stock && $meets_moq ? '✓ In Stock' : 
                           (!$has_stock ? "✗ Only $stock available" : '✗ Below minimum order quantity')
            ];

            if ($has_stock && $meets_moq) {
                $total_applicable_items++;
            } else {
                $all_available = false;
            }
        }
    }

    // Calculate total price for vendor
    $total_price = 0;
    foreach ($products_status as $item) {
        if ($item['available'] && $item['price']) {
            $total_price += $item['price'] * $item['required'];
        }
    }

    // Get vendor's other compatible vendors if partial availability
    $alternatives = [];
    if (!$all_available) {
        $alternatives = $db->query(
            "SELECT DISTINCT v.id, v.store_name, v.logo_url, COUNT(vpm.id) as product_count
             FROM vendors v
             JOIN vendor_product_map vpm ON v.id = vpm.vendor_id
             WHERE v.id != ? AND v.status = 'active' 
             AND vpm.product_id IN (" . implode(',', array_keys($order_items)) . ")
             AND vpm.is_available = 1
             AND vpm.status = 'active'
             GROUP BY v.id
             ORDER BY product_count DESC
             LIMIT 3",
            [$vendor_id]
        )->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'vendor' => [
            'id' => (int)$vendor['id'],
            'name' => $vendor['store_name'],
            'logo' => $vendor['logo_url'],
            'banner' => $vendor['banner_url'],
            'rating' => (float)$vendor['average_rating'],
            'reviews' => (int)$vendor['total_reviews'],
            'status' => $vendor['status']
        ],
        'all_available' => $all_available,
        'applicable_items' => $total_applicable_items,
        'total_items' => count($order_items),
        'estimated_total' => round($total_price, 2),
        'products' => $products_status,
        'alternatives' => $alternatives
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
