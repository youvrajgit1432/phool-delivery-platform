<?php
/**
 * Order Invoice View - Vendor Panel
 * Generate and download invoice as PDF
 */

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$orderId = $_GET['order_id'] ?? null;

// Validate
if (!$vendorId || !$orderId) {
    http_response_code(404);
    echo 'Invalid order';
    exit;
}

// Fetch order details
$stmt = $db->query(
    'SELECT * FROM vendor_orders WHERE order_id = ? AND vendor_id = ?',
    [$orderId, $vendorId]
);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

// Fetch order items
$stmt = $db->query(
    'SELECT * FROM vendor_order_items WHERE vendor_order_id = ? ORDER BY id ASC',
    [$orderId]
);
$orderItems = $stmt->fetchAll();

// Fetch vendor details
$stmt = $db->query(
    'SELECT * FROM vendors WHERE id = ?',
    [$vendorId]
);
$vendor = $stmt->fetch() ?? [];

// Prepare vendor info with fallbacks
$vendorBusinessName = $vendor['business_name'] ?? $vendor['name'] ?? 'Vendor';
$vendorEmail = $vendor['email'] ?? 'N/A';
$vendorPhone = $vendor['phone'] ?? 'N/A';

// Generate HTML content for PDF
$acceptedDate = !empty($order['accepted_at']) ? $order['accepted_at'] : 'Not yet';

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .company-info h2 {
            margin: 0;
            color: #007bff;
        }
        .company-info p {
            margin: 5px 0;
            font-size: 12px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h1 {
            margin: 0;
            font-size: 28px;
            color: #007bff;
        }
        .invoice-info p {
            margin: 5px 0;
            font-size: 12px;
        }
        .order-details {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
        }
        .detail-box {
            flex: 1;
            margin-right: 20px;
        }
        .detail-box h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
        }
        .detail-box p {
            margin: 3px 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        table th {
            background-color: #007bff;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .summary {
            margin-top: 30px;
            width: 50%;
            margin-left: auto;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 12px;
            border-bottom: 1px solid #eee;
        }
        .summary-row.total {
            border-bottom: 2px solid #007bff;
            font-weight: bold;
            font-size: 14px;
            padding: 12px 0;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-assigned { background-color: #fff3cd; color: #856404; }
        .status-accepted { background-color: #d4edda; color: #155724; }
        .status-preparing { background-color: #cfe2ff; color: #084298; }
        .status-ready { background-color: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h2>Phool Delivery</h2>
                <p><strong>Vendor:</strong> {$vendorBusinessName}</p>
                <p><strong>Email:</strong> {$vendorEmail}</p>
                <p><strong>Phone:</strong> {$vendorPhone}</p>
            </div>
            <div class="invoice-info">
                <h1>INVOICE</h1>
                <p><strong>Order:</strong> {$order['order_number']}</p>
                <p><strong>Date:</strong> {$order['created_at']}</p>
                <p><span class="status-badge status-{$order['status']}">{$order['status']}</span></p>
            </div>
        </div>

        <!-- Order Details -->
        <div class="order-details">
            <div class="detail-box">
                <h3>Order Information</h3>
                <p><strong>Order Number:</strong> {$order['order_number']}</p>
                <p><strong>Order Date:</strong> {$order['created_at']}</p>
                <p><strong>Total Items:</strong> {$order['total_items']}</p>
            </div>
            <div class="detail-box">
                <h3>Payment Information</h3>
                <p><strong>Payment Method:</strong> {$order['payment_method']}</p>
                <p><strong>Payment Status:</strong> {$order['payment_status']}</p>
            </div>
            <div class="detail-box">
                <h3>Order Status</h3>
                <p><strong>Status:</strong> {$order['status']}</p>
                <p><strong>Assigned:</strong> {$order['assigned_at']}</p>
                <p><strong>Accepted:</strong> {$acceptedDate}</p>
            </div>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Product Name</th>
                    <th style="width: 15%; text-align: center;">Quantity</th>
                    <th style="width: 15%; text-align: right;">Unit Price</th>
                    <th style="width: 20%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
HTML;

foreach ($orderItems as $item) {
    $html .= <<<HTML
                <tr>
                    <td>{$item['product_name']}</td>
                    <td style="text-align: center;">{$item['quantity']}</td>
                    <td style="text-align: right;">₹{$item['unit_price']}</td>
                    <td style="text-align: right;">₹{$item['item_total']}</td>
                </tr>
HTML;
}

$html .= <<<HTML
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>₹{$order['subtotal']}</span>
            </div>
            <div class="summary-row">
                <span>Delivery Fee:</span>
                <span>₹{$order['delivery_fee']}</span>
            </div>
HTML;

if ($order['discount'] > 0) {
    $html .= <<<HTML
            <div class="summary-row">
                <span>Discount:</span>
                <span>-₹{$order['discount']}</span>
            </div>
HTML;
}

$orderTotal = $order['subtotal'] - $order['discount'];
$html .= <<<HTML
            <div class="summary-row total">
                <span>Total Amount:</span>
                <span>₹{$orderTotal}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is an electronically generated invoice. Thank you for your business!</p>
            <p>© 2025 Phool Delivery. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

// Generate PDF using DOMPDF
try {
    // Construct path to autoload.php - vendor-panel is 1 level deep from main vendor root
    $vendorAutoload = dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        // Fallback path
        $vendorAutoload = __DIR__ . '/../../../../vendor/autoload.php';
    }
    require_once $vendorAutoload;
    
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    $filename = "Invoice-{$order['order_number']}.pdf";
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    echo $dompdf->output();
    exit;
} catch (\Exception $e) {
    error_log('Invoice PDF Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating invoice: ' . htmlspecialchars($e->getMessage());
    exit;
}
