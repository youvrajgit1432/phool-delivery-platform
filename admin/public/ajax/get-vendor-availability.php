<?php
/**
 * Get vendors with product availability for a specific order
 * Returns vendor info + which products they have + their prices
 */

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;

    if (!$order_id) {
        throw new Exception('Order ID is required');
    }

    // Use proper database connection from app bootstrap
    require_once '../../bootstrap/app.php';
    $pdo = getDBConnection();

    // Get order items
    $stmt = $pdo->prepare(
        "SELECT oi.product_id, oi.quantity, p.name_en, p.name_ne, p.id
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?"
    );
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        throw new Exception('Order has no items');
    }

    $product_ids = array_column($order_items, 'product_id');
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

    // Get all vendors with their product availability and prices
    $stmt = $pdo->prepare(
        "SELECT DISTINCT 
            v.id,
            v.store_name,
            v.logo_url,
            v.banner_url,
            v.average_rating,
            v.total_reviews,
            v.status,
            COUNT(DISTINCT vpm.product_id) as available_products,
            SUM(CASE WHEN vpm.is_available = 1 AND vpm.status = 'active' THEN 1 ELSE 0 END) as active_products
         FROM vendors v
         LEFT JOIN vendor_product_map vpm ON v.id = vpm.vendor_id 
         WHERE v.status IN ('active', 'pending')
         AND (vpm.product_id IN ($placeholders) OR vpm.id IS NULL)
         GROUP BY v.id
         ORDER BY active_products DESC, v.average_rating DESC"
    );
    $stmt->execute($product_ids);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each vendor, get detailed product info
    $vendors_with_products = [];
    foreach ($vendors as $vendor) {
        $stmt = $pdo->prepare(
            "SELECT 
                vpm.product_id,
                vpm.vendor_price,
                vpm.vendor_stock,
                vpm.is_available,
                vpm.status,
                vpm.minimum_order_quantity,
                vpm.preparation_time,
                p.name_en
             FROM vendor_product_map vpm
             LEFT JOIN products p ON vpm.product_id = p.id
             WHERE vpm.vendor_id = ? AND vpm.product_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$vendor['id']], $product_ids));
        $vendor_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create product availability map
        $products_by_vendor = [];
        $has_all = true;
        $total_price = 0;

        foreach ($order_items as $item) {
            $product_found = false;
            foreach ($vendor_products as $vp) {
                if ($vp['product_id'] == $item['product_id']) {
                    $product_found = true;
                    $has_stock = $vp['vendor_stock'] >= $item['quantity'];
                    $meets_moq = $item['quantity'] >= $vp['minimum_order_quantity'];

                    $products_by_vendor[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name_en'] ?: $item['name_ne'],
                        'quantity_needed' => $item['quantity'],
                        'available' => $vp['is_available'] && $vp['status'] == 'active',
                        'stock' => (int)$vp['vendor_stock'],
                        'price' => (float)$vp['vendor_price'],
                        'moq' => (float)$vp['minimum_order_quantity'],
                        'has_stock' => $has_stock,
                        'meets_moq' => $meets_moq,
                        'status' => $vp['is_available'] && $vp['status'] == 'active' ? 
                                   ($has_stock && $meets_moq ? 'YES ✓' : 'LIMITED ⚠') : 'NO ✗'
                    ];

                    if ($vp['is_available'] && $vp['status'] == 'active' && $has_stock && $meets_moq) {
                        $total_price += $vp['vendor_price'] * $item['quantity'];
                    } else {
                        $has_all = false;
                    }
                    break;
                }
            }

            if (!$product_found) {
                $products_by_vendor[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name_en'] ?: $item['name_ne'],
                    'quantity_needed' => $item['quantity'],
                    'available' => false,
                    'stock' => 0,
                    'price' => 0,
                    'moq' => 0,
                    'has_stock' => false,
                    'meets_moq' => false,
                    'status' => 'NO ✗'
                ];
                $has_all = false;
            }
        }

        $vendors_with_products[] = [
            'id' => (int)$vendor['id'],
            'store_name' => $vendor['store_name'],
            'logo_url' => $vendor['logo_url'],
            'banner_url' => $vendor['banner_url'],
            'rating' => (float)$vendor['average_rating'],
            'reviews' => (int)$vendor['total_reviews'],
            'status' => $vendor['status'],
            'has_all_products' => $has_all,
            'total_price' => round($total_price, 2),
            'products' => $products_by_vendor
        ];
    }

    // Sort by has_all_products first, then by rating
    usort($vendors_with_products, function($a, $b) {
        if ($a['has_all_products'] != $b['has_all_products']) {
            return $b['has_all_products'] ? 1 : -1;
        }
        return $b['rating'] <=> $a['rating'];
    });

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_items_count' => count($order_items),
        'vendors' => $vendors_with_products
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
