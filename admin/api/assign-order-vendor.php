<?php
/**
 * API Endpoint: Assign Order to Vendor
 * Creates vendor_order record and sends notification
 */

header('Content-Type: application/json');
session_start();
require_once '../../config/Database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate admin session
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if (!$vendor_id || !$order_id) {
        throw new Exception('Vendor ID and Order ID are required');
    }

    $db = Database::getInstance();

    // Get order details
    $order = $db->query(
        "SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.id
         WHERE o.id = ?",
        [$order_id]
    )->fetch();

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get vendor details
    $vendor = $db->query(
        "SELECT * FROM vendors WHERE id = ? AND status IN ('active', 'pending')",
        [$vendor_id]
    )->fetch();

    if (!$vendor) {
        throw new Exception('Vendor not found or inactive');
    }

    // Get order items
    $order_items = $db->query(
        "SELECT oi.*, p.name_en as product_name
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?",
        [$order_id]
    )->fetchAll();

    if (empty($order_items)) {
        throw new Exception('Order has no items');
    }

    // Calculate totals
    $total_items = count($order_items);
    $subtotal = 0;
    $delivery_fee = $order['delivery_fee'] ?? 0;
    $discount = $order['discount'] ?? 0;

    foreach ($order_items as $item) {
        // Get vendor's price for this product
        $vendor_product = $db->query(
            "SELECT vendor_price FROM vendor_product_map 
             WHERE vendor_id = ? AND product_id = ? AND is_available = 1",
            [$vendor_id, $item['product_id']]
        )->fetch();

        $price = $vendor_product ? $vendor_product['vendor_price'] : ($item['price'] ?? 0);
        $subtotal += $price * $item['quantity'];
    }

    $total_amount = max(0, $subtotal + $delivery_fee - $discount);
    $vendor_commission = round($total_amount * 0.10, 2); // 10% commission

    // Create vendor_order record
    $result = $db->execute(
        "INSERT INTO vendor_orders 
        (vendor_id, order_id, order_number, customer_name, customer_phone, customer_address,
         total_items, subtotal, delivery_fee, discount, total_amount, vendor_commission,
         payment_method, payment_status, status, notes, assigned_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $vendor_id,
            $order_id,
            $order['order_number'],
            $order['customer_name'],
            $order['customer_phone'],
            $order['customer_address'],
            $total_items,
            $subtotal,
            $delivery_fee,
            $discount,
            $total_amount,
            $vendor_commission,
            $order['payment_method'] ?? 'cod',
            'pending',
            'assigned',
            $notes
        ]
    );

    if (!$result) {
        throw new Exception('Failed to assign order to vendor');
    }

    $vendor_order_id = $db->lastInsertId();

    // Insert vendor order items
    foreach ($order_items as $item) {
        $db->execute(
            "INSERT INTO vendor_order_items 
            (vendor_order_id, product_id, product_name, quantity, unit_price, item_total)
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $vendor_order_id,
                $item['product_id'],
                $item['product_name'] ?? 'Unknown',
                $item['quantity'],
                $item['price'],
                $item['quantity'] * $item['price']
            ]
        );
    }

    // Send notification to vendor
    $notification_data = [
        'order_id' => $order_id,
        'order_number' => $order['order_number'],
        'amount' => number_format($total_amount, 2),
        'items_count' => $total_items,
        'customer_name' => $order['customer_name'],
        'delivery_address' => $order['customer_address']
    ];

    $db->execute(
        "INSERT INTO vendor_notifications 
        (vendor_id, notification_type, title, message, data, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())",
        [
            $vendor_id,
            'order_assigned',
            "New Order Assigned: {$order['order_number']}",
            "You have been assigned order {$order['order_number']}. Please check your vendor panel to accept.",
            json_encode($notification_data)
        ]
    );

    // Update order status
    $db->execute(
        "UPDATE orders SET status = 'assigned_to_vendor', updated_at = NOW() WHERE id = ?",
        [$order_id]
    );

    // Log the assignment
    $db->execute(
        "INSERT INTO assignment_audit (order_id, vendor_id, assigned_by, action, details, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [
            $order_id,
            $vendor_id,
            $_SESSION['admin_id'] ?? null,
            'order_assigned',
            json_encode([
                'total_amount' => $total_amount,
                'items' => count($order_items),
                'notes' => $notes
            ])
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => "Order assigned successfully",
        'vendor_order_id' => $vendor_order_id,
        'order_number' => $order['order_number'],
        'vendor_name' => $vendor['store_name'],
        'total_amount' => round($total_amount, 2)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
