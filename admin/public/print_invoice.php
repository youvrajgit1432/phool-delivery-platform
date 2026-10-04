<?php
// print_invoice.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
require_once '../app/services/InvoiceService.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Check if invoice exists
    $invoiceService = new InvoiceService($pdo);
    $invoice = $invoiceService->getInvoiceByOrderId($id);
    
    if ($invoice) {
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, c.* 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
        
        // Get system settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Generate PDF
        $options = new Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf\Dompdf($options);
        
        $html = getInvoiceHtml($order, $items, $settings, $invoice);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream('invoice_' . $invoice['invoice_number'] . '.pdf', [
            'Attachment' => true
        ]);
        exit;
    } else {
        // Try to generate invoice if it doesn't exist
        $invoiceService = new InvoiceService($pdo);
        $generated = $invoiceService->generateInvoice($id);
        
        if ($generated) {
            // Redirect to print the newly generated invoice
            header("Location: print_invoice.php?id=" . $id);
            exit;
        }
    }
}

// If no invoice found, show error
header('Content-Type: text/html');
echo "<h2>Invoice not found</h2>";
echo "<p>No invoice has been generated for this order yet.</p>";
echo "<a href='javascript:history.back()'>Go Back</a>";

function getInvoiceHtml($order, $items, $settings, $invoice) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Invoice #<?php echo $invoice['invoice_number']; ?></title>
        <style>
            body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 20px; color: #333; }
            .container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; }
            .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
            .company-info { text-align: right; }
            .invoice-info { margin: 30px 0; }
            .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            .table th { background-color: #f8f9fa; }
            .totals { float: right; width: 300px; margin-top: 20px; }
            .totals .row { display: flex; justify-content: space-between; margin: 5px 0; }
            .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .nepali-text { font-family: 'Preeti', 'Mangal', sans-serif; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div>
                    <h1>INVOICE</h1>
                    <h3>#<?php echo $invoice['invoice_number']; ?></h3>
                    <div class="nepali-text">
                        <small>इन्भ्वाइस</small>
                    </div>
                </div>
                <div class="company-info">
                    <h2><?php echo htmlspecialchars($settings['company_name']); ?></h2>
                    <div class="nepali-text">
                        <small><?php echo htmlspecialchars($settings['company_name']); ?></small>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($settings['company_address'])); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($settings['company_phone']); ?></p>
                    <p>Email: <?php echo htmlspecialchars($settings['company_email']); ?></p>
                </div>
            </div>
            
            <div class="invoice-info">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <h4>Bill To:</h4>
                        <p><strong><?php echo htmlspecialchars($order['name']); ?></strong></p>
                        <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
                        <?php if (!empty($order['email'])): ?>
                        <p>Email: <?php echo htmlspecialchars($order['email']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p><strong>Invoice Date:</strong> <?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></p>
                        <p><strong>Due Date:</strong> <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></p>
                        <p><strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>Rs. <?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="totals">
                <div class="row">
                    <span>Subtotal:</span>
                    <span>Rs. <?php echo number_format($invoice['subtotal'], 2); ?></span>
                </div>
                <div class="row">
                    <span>Tax (<?php echo $settings['tax_rate']; ?>%):</span>
                    <span>Rs. <?php echo number_format($invoice['tax_amount'], 2); ?></span>
                </div>
                <div class="row" style="font-weight: bold; border-top: 2px solid #333; padding-top: 5px;">
                    <span>Total Amount:</span>
                    <span>Rs. <?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div class="nepali-text" style="margin-top: 10px; text-align: right;">
                    <small>जम्मा रकम: रु. <?php echo number_format($invoice['total_amount'], 2); ?></small>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($settings['invoice_terms']); ?></p>
                <div class="nepali-text">
                    <small>भुक्तानीका सर्तहरू: <?php echo htmlspecialchars($settings['invoice_terms']); ?></small>
                </div>
                <p class="text-center">Thank you for your business!</p>
                <div class="nepali-text text-center">
                    <small>हाम्रो सेवा प्रयोग गर्नुभएकोमा धन्यवाद!</small>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>