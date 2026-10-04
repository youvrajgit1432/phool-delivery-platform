<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get order and invoice details
$order_stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.email, c.phone, c.address,
           ic.invoice_number, ic.invoice_date, ic.due_date, ic.total_amount as invoice_total
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN invoice_custom ic ON o.id = ic.order_id 
    WHERE o.id = ?
");
$order_stmt->execute([$id]);
$order = $order_stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// Get order items
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.name_en as product_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$items_stmt->execute([$id]);
$order_items = $items_stmt->fetchAll();

// Company information
$company_info = [
    'name' => 'Phool Delivery',
    'powered_by' => 'Deviatr Krishi Farm',
    'address' => 'Banepa, Nepal',
    'phone' => '+977 9800000001',
    'email' => 'info@phooldelivery.example',
    'gst_number' => '619571560'
];

// Handle PDF generation
if (isset($_GET['action']) && $_GET['action'] == 'generate_pdf') {
    generateInvoicePDF($order, $order_items, $company_info, $id);
    exit;
}

function generateInvoicePDF($order, $order_items, $company_info, $order_id) {
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Phool Delivery');
    $pdf->SetAuthor('Phool Delivery');
    $pdf->SetTitle('Invoice - ' . $order['customer_name']);
    $pdf->SetSubject('Order Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add Logo
    $logo_path = __DIR__ . '/../../public/assets/img/logo.jpg';
    $sign_path = __DIR__ . '/../../public/assets/img/sign.png';
    
    // Company Header with Logo
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 15, 30, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }
    
    // Company Information
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(50, 15);
    $pdf->Cell(100, 8, $company_info['name'], 0, 2, 'L');
    
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetXY(50, 23);
    $pdf->Cell(100, 5, 'Powered By: ' . $company_info['powered_by'], 0, 2, 'L');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 28);
    $pdf->Cell(100, 5, $company_info['address'], 0, 2, 'L');
    
    $pdf->SetXY(50, 33);
    $pdf->Cell(100, 5, 'Phone: ' . $company_info['phone'], 0, 2, 'L');
    
    $pdf->SetXY(50, 38);
    $pdf->Cell(100, 5, 'Email: ' . $company_info['email'], 0, 2, 'L');
    
    $pdf->SetXY(50, 43);
    $pdf->Cell(100, 5, 'PAN NO.: ' . $company_info['gst_number'], 0, 2, 'L');

    // Invoice Header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->SetXY(140, 15);
    $pdf->Cell(50, 10, 'INVOICE', 0, 2, 'R');
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(140, 25);
    $pdf->Cell(50, 5, 'Invoice No: ' . ($order['invoice_number'] ?? 'INV-' . date('Ymd') . '-' . $order_id), 0, 2, 'R');
    
    $pdf->SetXY(140, 30);
    $pdf->Cell(50, 5, 'Date: ' . date('d/m/Y', strtotime($order['invoice_date'] ?? $order['created_at'])), 0, 2, 'R');
    
    $pdf->SetXY(140, 35);
    $pdf->Cell(50, 5, 'Order #: ' . $order['order_number'], 0, 2, 'R');
    
    $pdf->Ln(20);
    
    // Bill To and Order Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(95, 8, 'Bill To:', 0, 0, 'L', true);
    $pdf->Cell(95, 8, 'Order Information:', 0, 1, 'L', true);
    
    $pdf->SetY($pdf->GetY() - 8);
    
    // Customer Information
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Ln(10.5);
    $pdf->Cell(95, 7, $order['customer_name'], 0, 2, 'L');
    
    $pdf->SetFont('helvetica', '', 9);
    if (!empty($order['address'])) {
        $y_before_address = $pdf->GetY();
        $pdf->MultiCell(95, 4, $order['address'], 0, 'L');
        $y_after_address = $pdf->GetY();
    }
    
    if (!empty($order['phone'])) {
        $pdf->Cell(95, 5, 'Phone: ' . $order['phone'], 0, 2, 'L');
    }
    
    if (!empty($order['email'])) {
        $pdf->Cell(95, 5, 'Email: ' . $order['email'], 0, 2, 'L');
    }
    
    // Order Information
    $pdf->SetXY(110, $y_before_address ?? $pdf->GetY() - 20);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Order Date:', 0, 0, 'L');
    $pdf->Cell(55, 5, date('F d, Y', strtotime($order['created_at'])), 0, 2, 'L');
    
    $pdf->SetX(110);
    $pdf->Cell(40, 5, 'Due Date:', 0, 0, 'L');
    $pdf->Cell(55, 5, date('F d, Y', strtotime($order['due_date'] ?? '+7 days')), 0, 2, 'L');
    
    $pdf->SetX(110);
    $pdf->Cell(40, 5, 'Status:', 0, 0, 'L');
    $pdf->Cell(55, 5, ucfirst($order['status']), 0, 2, 'L');
    
    $pdf->SetX(110);
    $pdf->Cell(40, 5, 'Payment Status:', 0, 0, 'L');
    
    switch($order['payment_status']) {
        case 'paid': 
            $status_text = 'Paid';
            $pdf->SetTextColor(0, 128, 0);
            break;
        case 'partial': 
            $status_text = 'Partial';
            $pdf->SetTextColor(255, 165, 0);
            break;
        case 'pending': 
            $status_text = 'Pending';
            $pdf->SetTextColor(255, 0, 0);
            break;
        default: 
            $status_text = 'Unknown';
    }
    $pdf->Cell(55, 5, $status_text, 0, 2, 'L');
    $pdf->SetTextColor(0, 0, 0);
    
    $max_y = max($y_after_address ?? $pdf->GetY(), $pdf->GetY());
    $pdf->SetY($max_y + 10);
    
    // Invoice Items Table
    $pdf->SetFont('helvetica', 'B', 11);
    
    // Table header
    $pdf->SetFillColor(80, 80, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(100, 8, 'Description', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Rate', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Amount', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Table rows
    $subtotal = 0;
    foreach ($order_items as $item) {
        $pdf->Cell(100, 10, $item['product_name'], 1, 0, 'L');
        $pdf->Cell(25, 10, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(35, 10, 'Rs. ' . number_format($item['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(30, 10, 'Rs. ' . number_format($item['total_price'], 2), 1, 1, 'R');
        $subtotal += $item['total_price'];
    }
    
    // Summary
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(160, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 7, 'Rs. ' . number_format($subtotal, 2), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(160, 8, 'Total Amount:', 0, 0, 'R');
    $pdf->Cell(30, 8, 'Rs. ' . number_format($order['total_amount'], 2), 0, 1, 'R');
    
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(160, 8, 'Amount Paid:', 0, 0, 'R');
    $pdf->Cell(30, 8, 'Rs. ' . number_format(0, 2), 0, 1, 'R'); // You can modify this based on payment records
    
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(160, 8, 'Balance Due:', 0, 0, 'R');
    $pdf->Cell(30, 8, 'Rs. ' . number_format($order['total_amount'], 2), 0, 1, 'R');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(12);
    
    // Terms and Notes
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(95, 7, 'Payment Terms:', 0, 0, 'L', true);
    $pdf->Cell(95, 7, 'Notes:', 0, 1, 'L', true);
    
    $y_before_terms = $pdf->GetY();
    
    $pdf->SetFont('helvetica', '', 9);
    $terms = "• Payment due within 7 days\n• Late payments subject to interest\n• Thank you for your business";
    $notes = "• Quality products guaranteed\n• Contact for any concerns\n• We value your trust";
    
    $pdf->MultiCell(95, 5, $terms, 0, 'L');
    $y_after_terms = $pdf->GetY();
    
    $pdf->SetXY(110, $y_before_terms);
    $pdf->MultiCell(95, 5, $notes, 0, 'L');
    
    $max_y = max($y_after_terms, $pdf->GetY());
    $pdf->SetY($max_y + 10);
    
    // Signature Section
    $signature_y = $pdf->GetY();
    $pdf->Cell(95, 6, '_________________________', 0, 0, 'L');
    $pdf->Cell(95, 6, '_________________________', 0, 1, 'R');
    
    $pdf->Cell(95, 5, 'Customer Signature', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Authorized Signature', 0, 1, 'R');
    
    // Add signature image
    if (file_exists($sign_path)) {
        $pdf->Image($sign_path, 175, $signature_y - 10, 13, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }

    // Accountant information
    $pdf->SetY($signature_y + 12);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Yuvraj Syangtan', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(95, 4, '', 0, 0, 'L');
    $pdf->Cell(95, 4, 'Accountant', 0, 1, 'R');
    
    $pdf->Ln(8);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Generated by Phool Delivery System | ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 4, $company_info['name'] . ' - ' . $company_info['address'], 0, 1, 'C');
    $pdf->Cell(0, 4, 'Contact: ' . $company_info['phone'] . ' | Email: ' . $company_info['email'] . ' | phooldelivery.example', 0, 1, 'C');
    
    // Generate filename
    $customer_name = preg_replace('/[^a-zA-Z0-9]/', '_', $order['customer_name']);
    $filename = 'Invoice_' . $customer_name . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF to browser
    $pdf->Output($filename, 'D');
}

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Invoice - Order #<?php echo $order['order_number']; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button onclick="printInvoice()" class="btn btn-sm btn-primary me-2">
            <i class="fas fa-print me-1"></i> Print Invoice
        </button>
        <a href="?id=<?php echo $id; ?>&action=generate_pdf" class="btn btn-sm btn-success me-2" target="_blank">
            <i class="fas fa-file-pdf me-1"></i> Download PDF
        </a>
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Order
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" id="invoice-content">
    <div class="card-body p-4">
        <!-- Invoice Header -->
        <div class="row mb-4">
            <div class="col-md-2">
                <?php 
                $logo_path = __DIR__ . '/../../public/assets/img/logo.jpg';
                $web_logo_path = '../assets/img/logo.jpg';
                if (file_exists($logo_path)): 
                ?>
                    <img src="<?php echo $web_logo_path; ?>" alt="Company Logo" class="img-fluid" style="max-height: 80px;" id="invoice-logo">
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h3 class="text-primary fw-bold"><?php echo $company_info['name']; ?></h3>
                <p class="text-muted mb-1"><em>Powered By: <?php echo $company_info['powered_by']; ?></em></p>
                <p class="mb-1 small"><?php echo $company_info['address']; ?></p>
                <p class="mb-1 small">Phone: <?php echo $company_info['phone']; ?></p>
                <p class="mb-1 small">Email: <?php echo $company_info['email']; ?></p>
                <p class="mb-0 small">PAN NO.: <?php echo $company_info['gst_number']; ?></p>
                <p class="mb-0 small"><em>phooldelivery.example</em></p>
            </div>
            <div class="col-md-6 text-end">
                <h2 class="text-success fw-bold">INVOICE</h2>
                <p class="mb-1"><strong>Invoice No:</strong> <?php echo $order['invoice_number'] ?? 'INV-' . date('Ymd') . '-' . $id; ?></p>
                <p class="mb-1"><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($order['invoice_date'] ?? $order['created_at'])); ?></p>
                <p class="mb-0"><strong>Order #:</strong> <?php echo $order['order_number']; ?></p>
            </div>
        </div>

        <!-- Bill To and Order Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-light">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold">Bill To</h6>
                    </div>
                    <div class="card-body">
                        <h5 class="text-dark"><?php echo htmlspecialchars($order['customer_name']); ?></h5>
                        <?php if (!empty($order['address'])): ?>
                            <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['phone'])): ?>
                            <p class="mb-1 small">Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['email'])): ?>
                            <p class="mb-0 small">Email: <?php echo htmlspecialchars($order['email']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-light">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold">Order Information</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="small">Order Date:</th>
                                <td class="small"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th class="small">Due Date:</th>
                                <td class="small"><?php echo date('F d, Y', strtotime($order['due_date'] ?? '+7 days')); ?></td>
                            </tr>
                            <tr>
                                <th class="small">Status:</th>
                                <td class="small"><?php echo ucfirst($order['status']); ?></td>
                            </tr>
                            <tr>
                                <th class="small">Payment Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($order['payment_status']) {
                                            case 'paid': echo 'success'; break;
                                            case 'partial': echo 'warning'; break;
                                            case 'pending': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Items -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Rate</th>
                        <th class="text-center">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        </td>
                        <td class="text-center align-middle"><?php echo $item['quantity']; ?></td>
                        <td class="text-end align-middle">Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end align-middle">Rs. <?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-top-0">
                    <tr>
                        <td colspan="3" class="text-end border-0"><strong>Subtotal:</strong></td>
                        <td class="text-end border-0"><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end border-0"><strong>Total Amount:</strong></td>
                        <td class="text-end border-0"><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end border-0"><strong>Amount Paid:</strong></td>
                        <td class="text-end border-0 text-success"><strong>Rs. <?php echo number_format(0, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end border-0"><strong>Balance Due:</strong></td>
                        <td class="text-end border-0 text-danger"><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payment Terms and Notes -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-light">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold">Payment Terms</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li>• Payment due within 7 days of invoice</li>
                            <li>• Accepted methods: Cash, Bank Transfer, UPI</li>
                            <li>• Late payments subject to interest charges</li>
                            <li>• Thank you for your business</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-light">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold">Notes</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li>• Quality products guaranteed</li>
                            <li>• Proper handling ensured</li>
                            <li>• Contact for any concerns</li>
                            <li>• We value your trust</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="row mt-4 pt-3 border-top">
            <div class="col-md-6">
                <p class="mb-1">_________________________</p>
                <p class="small text-muted">Customer Signature</p>
            </div>
            <div class="col-md-6 text-end">
                <?php 
                $sign_path = __DIR__ . '/../../public/assets/img/sign.png';
                $web_sign_path = '../assets/img/sign.png';
                if (file_exists($sign_path)): 
                ?>
                <img src="<?php echo $web_sign_path; ?>" alt="Authorized Signature" class="img-fluid mb-2" style="max-height: 40px; margin-right: 20px; margin-bottom: 80px;">
                <?php endif; ?>
                <p class="mb-1">_________________________</p>
                <p class="mb-0 fw-bold">Yuvraj Syangtan</p>
                <p class="small text-muted">Accountant</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="row mt-4 pt-3 border-top">
            <div class="col-md-12 text-center">
                <p class="text-muted small">
                    Generated by Phool Delivery System on <?php echo date('Y-m-d H:i:s'); ?><br>
                    <?php echo $company_info['name']; ?> - <?php echo $company_info['address']; ?><br>
                    Contact: <?php echo $company_info['phone']; ?> | Email: <?php echo $company_info['email']; ?> | phooldelivery.example
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function printInvoice() {
    const originalContent = document.getElementById('invoice-content');
    const invoiceContent = originalContent.cloneNode(true);
    
    const buttons = invoiceContent.querySelectorAll('.btn-toolbar, .btn, .border-bottom, .no-print');
    buttons.forEach(button => button.remove());
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    const logo = document.getElementById('invoice-logo');
    const logoSrc = logo ? logo.src : '';
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Invoice - <?php echo htmlspecialchars($order['customer_name']); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    body { margin: 0; padding: 15px; font-size: 12px; background: white !important; }
                    .card { border: none !important; box-shadow: none !important; }
                    .card-body { padding: 0 !important; }
                    .btn-toolbar, .border-bottom, .no-print { display: none !important; }
                }
                body { font-family: Arial, sans-serif; }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                ${invoiceContent.innerHTML}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 100);
                }
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>

<style>
@media print {
    .btn-toolbar, .border-bottom, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    body {
        font-size: 12px;
        background: white !important;
    }
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>