<?php
/**
 * AJAX endpoint for getting call orders with filters
 * Returns JSON with call order data and table HTML
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering to prevent HTML from mixing with JSON
ob_start();

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';

    // Check authentication
    requireAuth();

    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_clause = "";
$params = [];
$conditions = [];

if (!empty($status_filter)) {
    $conditions[] = "co.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $conditions[] = "co.payment_method = ?";
    $params[] = $payment_filter;
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(co.order_date) = ?";
    $params[] = $date_filter;
}

if (!empty($search)) {
    $conditions[] = "(cc.name LIKE ? OR cc.phone_number LIKE ? OR p.name_en LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Get call orders with filters
try {
    $orders = $pdo->prepare("
        SELECT 
            co.*,
            cc.name as customer_name,
            cc.phone_number,
            cc.city,
            cc.street,
            p.name_en as product_name,
            ci.invoice_number,
            ci.status as invoice_status
        FROM call_customer_orders co
        LEFT JOIN call_customers cc ON co.call_customer_id = cc.id
        LEFT JOIN products p ON co.product_id = p.id
        LEFT JOIN call_invoices ci ON co.id = ci.call_order_id
        $where_clause 
        ORDER BY co.order_date DESC
    ");
    $orders->execute($params);
    $orders = $orders->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($orders)) {
        $orders = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading call orders: ' . $e->getMessage()]);
    exit;
}

// Generate table HTML
$tableHtml = '';

if (empty($orders)) {
    $tableHtml = '<tr><td colspan="12" class="text-center py-4">
                    <i class="bi bi-inbox"></i> No call orders found.
                    <a href="call/add_call_order.php" class="ms-2">Add a new call order</a>
                  </td></tr>';
} else {
    foreach ($orders as $order) {
        $tableHtml .= '<tr class="call-order-row" data-order-search="' . strtolower($order['customer_name'] . ' ' . $order['phone_number'] . ' ' . $order['product_name']) . '" 
                          data-order-status="' . strtolower($order['status']) . '" 
                          data-order-payment="' . strtolower($order['payment_method']) . '">';
        
        // Order ID
        $tableHtml .= '<td>#' . htmlspecialchars($order['id']) . '</td>';
        
        // Customer
        $tableHtml .= '<td>' . htmlspecialchars($order['customer_name']) . '</td>';
        
        // Contact
        $tableHtml .= '<td>' . htmlspecialchars($order['phone_number']) . '</td>';
        
        // Product
        $tableHtml .= '<td>' . htmlspecialchars($order['product_name']) . '</td>';
        
        // Quantity
        $tableHtml .= '<td>' . htmlspecialchars($order['quantity']) . '</td>';
        
        // Rate
        $tableHtml .= '<td>Rs. ' . number_format($order['rate'], 2) . '</td>';
        
        // Total
        $tableHtml .= '<td>Rs. ' . number_format($order['total_amount'], 2) . '</td>';
        
        // Payment Method
        $paymentClass = '';
        switch($order['payment_method']) {
            case 'Cash on Delivery': $paymentClass = 'warning'; break;
            case 'Wallets': $paymentClass = 'info'; break;
            case 'Bank Transfer': $paymentClass = 'success'; break;
            default: $paymentClass = 'secondary';
        }
        $tableHtml .= '<td><span class="badge bg-' . $paymentClass . '">' . htmlspecialchars($order['payment_method']) . '</span></td>';
        
        // Status
        $statusClass = '';
        switch($order['status']) {
            case 'pending': $statusClass = 'warning'; break;
            case 'confirmed': $statusClass = 'primary'; break;
            case 'preparing': $statusClass = 'info'; break;
            case 'out_for_delivery': $statusClass = 'secondary'; break;
            case 'delivered': $statusClass = 'success'; break;
            case 'cancelled': $statusClass = 'danger'; break;
            default: $statusClass = 'secondary';
        }
        $tableHtml .= '<td><span class="badge bg-' . $statusClass . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) . '</span></td>';
        
        // Invoice
        $tableHtml .= '<td>';
        if ($order['invoice_number']) {
            $tableHtml .= '<span class="badge bg-success" data-bs-toggle="tooltip" title="Invoice: ' . htmlspecialchars($order['invoice_number']) . '">'
                        . '<i class="fas fa-file-invoice me-1"></i> Generated</span>';
        } else {
            $tableHtml .= '<span class="badge bg-secondary">No Invoice</span>';
        }
        $tableHtml .= '</td>';
        
        // Order Date
        $tableHtml .= '<td>' . date('M d, Y h:i A', strtotime($order['order_date'])) . '</td>';
        
        // Actions
        $tableHtml .= '<td><div class="btn-group btn-group-sm">';
        $tableHtml .= '<a href="call/view_call_order.php?id=' . $order['id'] . '" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">'
                    . '<i class="fas fa-eye"></i></a>';
        $tableHtml .= '<a href="call/edit_call_order.php?id=' . $order['id'] . '" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Order">'
                    . '<i class="fas fa-edit"></i></a>';
        
        if ($order['invoice_number']) {
            $tableHtml .= '<a href="call/generate_call_invoice.php?id=' . $order['id'] . '" class="btn btn-success" data-bs-toggle="tooltip" title="Download Invoice">'
                        . '<i class="fas fa-file-pdf"></i></a>';
        } else {
            $tableHtml .= '<a href="call/generate_call_invoice.php?id=' . $order['id'] . '&action=create" class="btn btn-warning" data-bs-toggle="tooltip" title="Create Invoice">'
                        . '<i class="fas fa-file-invoice"></i></a>';
        }
        
        $tableHtml .= '<button type="button" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Order" onclick="deleteCallOrder(' . $order['id'] . ')">'
                    . '<i class="fas fa-trash"></i></button>';
        $tableHtml .= '</div></td></tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($orders)
]);
exit;
