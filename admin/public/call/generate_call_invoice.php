<?php
// public/call/generate_call_invoice.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: ../call_orders.php");
    exit;
}

$order_id = intval($_GET['id']);

// Get order details with customer information
$order = $pdo->prepare("
    SELECT co.*, cc.name as customer_name, cc.phone_number, 
           cc.city, cc.street, p.name_en as product_name,
           p.unit, ci.invoice_number, ci.id as invoice_id
    FROM call_customer_orders co 
    JOIN call_customers cc ON co.call_customer_id = cc.id 
    JOIN products p ON co.product_id = p.id
    LEFT JOIN call_invoices ci ON co.id = ci.call_order_id
    WHERE co.id = ?
");
$order->execute([$order_id]);
$order = $order->fetch();

if (!$order) {
    $_SESSION['error_message'] = "Call order record not found.";
    header("Location: ../call_orders.php");
    exit;
}

// Get company information
$company_info = [
    'name' => 'Phool Delivery',
    'powered_by' => 'Deviatr Krishi Farm',
    'address' => 'Banepa, Nepal',
    'phone' => '+977 9844634579',
    'email' => 'info@phooldelivery.example',
    'gst_number' => '619571560'
];

// Handle invoice creation if requested
if (isset($_GET['action']) && $_GET['action'] == 'create' && !$order['invoice_number']) {
    try {
        // Generate invoice number
        $invoice_number = 'CALL-INV-' . date('Ymd') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
        
        // Create invoice record
        $invoice_stmt = $pdo->prepare("
            INSERT INTO call_invoices (call_order_id, call_customer_id, invoice_number, invoice_date, due_date, subtotal, total_amount, status, notes)
            VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), ?, ?, 'draft', ?)
        ");
        
        $invoice_stmt->execute([
            $order_id,
            $order['call_customer_id'],
            $invoice_number,
            $order['total_amount'],
            $order['total_amount'],
            "Call order invoice for customer: " . $order['customer_name']
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Update order with invoice ID
        $order['invoice_number'] = $invoice_number;
        $order['invoice_id'] = $invoice_id;
        
        $_SESSION['success_message'] = "Invoice created successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error creating invoice: " . $e->getMessage();
    }
}

// Include TCPDF library
require_once '../../../vendor/autoload.php';

// Handle PDF generation
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    generateCallInvoicePDF($order, $company_info, $order_id);
    exit;
}

// Function to generate PDF
function generateCallInvoicePDF($order, $company_info, $order_id) {
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Phool Delivery');
    $pdf->SetAuthor('Phool Delivery');
    $pdf->SetTitle('Call Invoice - ' . $order['customer_name']);
    $pdf->SetSubject('Call Order Invoice');
    
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
    
    // Add Logo to PDF
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

    // Add website
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetXY(50, 48);
    $pdf->Cell(100, 5, 'phooldelivery.example', 0, 2, 'L');

    // Invoice Header on the right
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->SetXY(140, 15);
    $pdf->Cell(50, 10, 'INVOICE', 0, 2, 'R');
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(140, 25);
    $pdf->Cell(50, 5, 'Invoice No: ' . $order['invoice_number'], 0, 2, 'R');
    
    $pdf->SetXY(140, 30);
    $pdf->Cell(50, 5, 'Date: ' . date('d/m/Y', strtotime($order['order_date'])), 0, 2, 'R');
    
    $pdf->SetXY(140, 35);
    $pdf->Cell(50, 5, 'Order No: CALL-' . $order_id, 0, 2, 'R');
    
    $pdf->Ln(20);
    
    // Bill To and Order Information Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(95, 8, 'Bill To:', 0, 0, 'L', true);
    $pdf->Cell(95, 8, 'Order Information:', 0, 1, 'L', true);
    
    // Reset Y position for content
    $pdf->SetY($pdf->GetY() - 8);
    
    // Customer Information
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Ln(10.5);
    $pdf->Cell(95, 7, $order['customer_name'], 0, 2, 'L');
    
    $pdf->SetFont('helvetica', '', 9);
    
    // Store current Y position before MultiCell
    $y_before_address = $pdf->GetY();
    
    // Customer address
    $address = '';
    if (!empty($order['street'])) {
        $address .= $order['street'];
    }
    if (!empty($order['city'])) {
        $address .= (!empty($address) ? ', ' : '') . $order['city'];
    }
    
    $pdf->MultiCell(95, 4, $address, 0, 'L');
    $y_after_address = $pdf->GetY();
    
    if (!empty($order['phone_number'])) {
        $pdf->Cell(95, 5, 'Phone: ' . $order['phone_number'], 0, 2, 'L');
    }
    
    // Order Information
    $pdf->SetXY(110, $y_before_address);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Order Date:', 0, 0, 'L');
    $pdf->Cell(55, 5, date('F d, Y', strtotime($order['order_date'])), 0, 2, 'L');
    
    $pdf->SetX(110);
    $pdf->Cell(40, 5, 'Order Status:', 0, 0, 'L');
    $pdf->Cell(55, 5, ucfirst($order['status']), 0, 2, 'L');
    
    $pdf->SetX(110);
    $pdf->Cell(40, 5, 'Payment Method:', 0, 0, 'L');
    $pdf->Cell(55, 5, $order['payment_method'], 0, 2, 'L');
    
    // Set Y position to the maximum of both columns
    $max_y = max($y_after_address, $pdf->GetY());
    $pdf->SetY($max_y + 10);
    
    // Invoice Items Table
    $pdf->SetFont('helvetica', 'B', 11);
    
    // Table header
    $pdf->SetFillColor(80, 80, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(80, 8, 'Product Description', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Unit', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Unit Price', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Amount', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Table rows
    $item_total = $order['quantity'] * $order['rate'];
    
    $pdf->Cell(80, 10, $order['product_name'], 1, 0, 'L');
    $pdf->Cell(25, 10, number_format($order['quantity'], 2), 1, 0, 'C');
    $pdf->Cell(25, 10, ucfirst($order['unit']), 1, 0, 'C');
    $pdf->Cell(30, 10, 'Rs. ' . number_format($order['rate'], 2), 1, 0, 'R');
    $pdf->Cell(30, 10, 'Rs. ' . number_format($item_total, 2), 1, 1, 'R');
    
    $pdf->Ln(5);
    
    // Calculations
    $final_amount = $order['total_amount'];
    
    // Table footer with calculations
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(150, 8, 'Total Amount:', 0, 0, 'R');
    $pdf->Cell(30, 8, 'Rs. ' . number_format($final_amount, 2), 0, 1, 'R');
    
    $pdf->Ln(12);
    
    // Payment Terms and Notes
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(95, 7, 'Payment Terms:', 0, 0, 'L', true);
    $pdf->Cell(95, 7, 'Notes:', 0, 1, 'L', true);
    
    // Store Y position before MultiCell
    $y_before_terms = $pdf->GetY();
    
    $pdf->SetFont('helvetica', '', 9);
    $terms = "• Payment due upon delivery for COD orders\n• Online payments must be verified\n• Contact for payment issues\n• Thank you for your business";
    $notes = "• Quality guaranteed fresh flowers\n• Contact for any delivery concerns\n• We value your trust\n• Call Order Invoice";
    
    // Calculate height needed for terms
    $pdf->MultiCell(95, 5, $terms, 0, 'L');
    $y_after_terms = $pdf->GetY();
    
    // Position notes at same Y as terms
    $pdf->SetXY(110, $y_before_terms);
    $pdf->MultiCell(95, 5, $notes, 0, 'L');
    
    // Set Y to max of both columns
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
        $pdf->Image(
            $sign_path,
            175,
            $signature_y - 10,
            13,
            0,
            'PNG',
            '',
            'T',
            false,
            300,
            '',
            false,
            false,
            0,
            false,
            false,
            false
        );
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
    
    // Footer with company information
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Generated by Phool Delivery System | ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 4, $company_info['name'] . ' - ' . $company_info['address'], 0, 1, 'C');
    $pdf->Cell(
        0,
        4,
        'Contact: ' . $company_info['phone'] .
        ' | Email: ' . $company_info['email'] .
        ' | phooldelivery.example',
        0,
        1,
        'C'
    );
    
    // Generate filename
    $current_year = date('Y');
    $customer_name = preg_replace('/[^a-zA-Z0-9]/', '_', $order['customer_name']);
    $order_date = date('Y-m-d', strtotime($order['order_date']));
    
    $filename = 'CALL_' . $current_year . '_' . $customer_name . '_' . $order_date . '.pdf';
    
    // Store PDF in storage directory
    $storage_path = __DIR__ . '/../../storage/call_invoices/';
    if (!is_dir($storage_path)) {
        mkdir($storage_path, 0755, true);
    }
    
    $file_path = $storage_path . $filename;
    
    try {
        // Save PDF file locally
        $pdf->Output($file_path, 'F');
        
        // Store record in database
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO call_invoice_pdfs (call_order_id, file_name, file_path, generated_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$order_id, $filename, $file_path]);
        
        // Update invoice with PDF path
        if ($order['invoice_id']) {
            $update_stmt = $pdo->prepare("UPDATE call_invoices SET pdf_path = ?, status = 'sent' WHERE id = ?");
            $update_stmt->execute([$file_path, $order['invoice_id']]);
        }
        
        // Output PDF to browser for download
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        // Log error and show user-friendly message
        error_log("Call Invoice PDF Generation Error: " . $e->getMessage());
        
        // If file saving fails, just output to browser
        $pdf->Output($filename, 'D');
    }
}

// Set page title
$page_title = "Call Invoice - " . htmlspecialchars($order['customer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Call Order Invoice</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($order['invoice_number']): ?>
            <button onclick="printInvoice()" class="btn btn-sm btn-primary me-2">
                <i class="fas fa-print me-1"></i> Print Invoice
            </button>
            <a href="?id=<?php echo $order_id; ?>&download=pdf" class="btn btn-sm btn-success me-2" target="_blank">
                <i class="fas fa-file-pdf me-1"></i> Download PDF
            </a>
        <?php else: ?>
            <a href="?id=<?php echo $order_id; ?>&action=create" class="btn btn-sm btn-warning me-2">
                <i class="fas fa-file-invoice me-1"></i> Create Invoice
            </a>
        <?php endif; ?>
        <a href="../call_orders.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Call Orders
        </a>
    </div>
</div>

<?php if (!$order['invoice_number']): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        No invoice has been generated for this call order yet. Click "Create Invoice" to generate one.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm" id="invoice-content">
    <div class="card-body p-4">
        <!-- Invoice Header with Logo -->
        <div class="row mb-4">
            <div class="col-md-2">
                <?php 
                $logo_path = __DIR__ . '/../../public/assets/img/logo.jpg';
                $web_logo_path = '../assets/img/logo.jpg';
                if (file_exists($logo_path)): 
                ?>
                    <img src="<?php echo $web_logo_path; ?>" 
                         alt="Company Logo" class="img-fluid" style="max-height: 80px;" id="invoice-logo">
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
                <?php if ($order['invoice_number']): ?>
                    <p class="mb-1"><strong>Invoice No:</strong> <?php echo $order['invoice_number']; ?></p>
                <?php else: ?>
                    <p class="mb-1"><strong>Invoice No:</strong> <span class="text-muted">Not generated</span></p>
                <?php endif; ?>
                <p class="mb-1"><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($order['order_date'])); ?></p>
                <p class="mb-0"><strong>Order No:</strong> CALL-<?php echo $order_id; ?></p>
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
                        <?php 
                        $address = '';
                        if (!empty($order['street'])) {
                            $address .= $order['street'];
                        }
                        if (!empty($order['city'])) {
                            $address .= (!empty($address) ? ', ' : '') . $order['city'];
                        }
                        if (!empty($address)): 
                        ?>
                            <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($address)); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['phone_number'])): ?>
                            <p class="mb-1 small">Phone: <?php echo htmlspecialchars($order['phone_number']); ?></p>
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
                                <td class="small"><?php echo date('F d, Y', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <tr>
                                <th class="small">Order Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($order['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'confirmed': echo 'primary'; break;
                                            case 'preparing': echo 'info'; break;
                                            case 'out_for_delivery': echo 'secondary'; break;
                                            case 'delivered': echo 'success'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="small">Payment Method:</th>
                                <td><?php echo $order['payment_method']; ?></td>
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
                        <th class="text-center">Product Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-center">Unit</th>
                        <th class="text-center">Unit Price</th>
                        <th class="text-center">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_total = $order['quantity'] * $order['rate'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                        </td>
                        <td class="text-center align-middle"><?php echo number_format($order['quantity'], 2); ?></td>
                        <td class="text-center align-middle"><?php echo ucfirst($order['unit']); ?></td>
                        <td class="text-end align-middle">Rs. <?php echo number_format($order['rate'], 2); ?></td>
                        <td class="text-end align-middle">Rs. <?php echo number_format($item_total, 2); ?></td>
                    </tr>
                </tbody>
                <tfoot class="border-top-0">
                    <tr>
                        <td colspan="4" class="text-end border-0"><strong>Total Amount:</strong></td>
                        <td class="text-end border-0"><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
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
                            <li>• Payment due upon delivery for COD orders</li>
                            <li>• Online payments must be verified</li>
                            <li>• Contact for payment issues</li>
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
                            <li>• Quality guaranteed fresh flowers</li>
                            <li>• Contact for any delivery concerns</li>
                            <li>• We value your trust</li>
                            <li>• Call Order Invoice</li>
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
    // Create a clone of the invoice content
    const originalContent = document.getElementById('invoice-content');
    const invoiceContent = originalContent.cloneNode(true);
    
    // Remove buttons and unnecessary elements for print
    const buttons = invoiceContent.querySelectorAll('.btn-toolbar, .btn, .border-bottom, .no-print');
    buttons.forEach(button => button.remove());
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Get the logo source
    const logo = document.getElementById('invoice-logo');
    const logoSrc = logo ? logo.src : '';
    
    // Write the print content
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Call Invoice - <?php echo htmlspecialchars($order['customer_name']); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                @media print {
                    body { 
                        margin: 0; 
                        padding: 15px; 
                        font-size: 12px; 
                        background: white !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .card { 
                        border: none !important; 
                        box-shadow: none !important; 
                    }
                    .card-body { 
                        padding: 0 !important; 
                    }
                    .card-header {
                        background-color: #f8f9fa !important;
                    }
                    .table-light { 
                        background-color: #f8f9fa !important; 
                        color: #000 !important; 
                    }
                    .btn-toolbar, .border-bottom, .no-print { 
                        display: none !important; 
                    }
                    .text-success { color: #198754 !important; }
                    .text-danger { color: #dc3545 !important; }
                    .text-primary { color: #0d6efd !important; }
                    .badge.bg-success { background-color: #198754 !important; }
                    .badge.bg-warning { background-color: #ffc107 !important; color: #000 !important; }
                    .badge.bg-danger { background-color: #dc3545 !important; }
                    .border-light { border-color: #dee2e6 !important; }
                    .bg-light { background-color: #f8f9fa !important; }
                    .row {
                        display: flex !important;
                        flex-wrap: wrap !important;
                    }
                    .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
                    .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12 {
                        float: left;
                    }
                    .col-md-6 {
                        width: 50% !important;
                    }
                    .col-md-4 {
                        width: 33.333333% !important;
                    }
                    .col-md-2 {
                        width: 16.666667% !important;
                    }
                    .col-md-12 {
                        width: 100% !important;
                    }
                }
                @page { 
                    margin: 0.5cm;
                    size: A4 portrait;
                }
                body { 
                    font-family: Arial, sans-serif; 
                    background: white;
                    line-height: 1.4;
                }
                .container-fluid {
                    width: 100%;
                    padding-right: 15px;
                    padding-left: 15px;
                    margin-right: auto;
                    margin-left: auto;
                }
                .table {
                    width: 100%;
                    margin-bottom: 1rem;
                    color: #212529;
                    border-collapse: collapse;
                }
                .table-bordered {
                    border: 1px solid #dee2e6;
                }
                .table-bordered th,
                .table-bordered td {
                    border: 1px solid #dee2e6;
                }
                .table-light {
                    color: #000;
                    background-color: #f8f9fa;
                }
                .text-end { text-align: right !important; }
                .text-center { text-align: center !important; }
                .align-middle { vertical-align: middle !important; }
                .mb-0 { margin-bottom: 0 !important; }
                .mb-1 { margin-bottom: 0.25rem !important; }
                .mb-4 { margin-bottom: 1.5rem !important; }
                .mt-4 { margin-top: 1.5rem !important; }
                .pt-3 { padding-top: 1rem !important; }
                .border-top { border-top: 1px solid #dee2e6 !important; }
                .shadow-sm { box-shadow: none !important; }
                .fw-bold { font-weight: bold !important; }
                .small { font-size: 0.875em !important; }
                .img-fluid { max-width: 100%; height: auto; }
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

<!-- Print Styles -->
<style>
@media print {
    .btn-toolbar, .border-bottom, .modal, .modal-backdrop, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .card-body {
        padding: 0 !important;
    }
    body {
        font-size: 12px;
        background: white !important;
        margin: 0 !important;
        padding: 15px !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .container-fluid {
        padding: 0 !important;
        max-width: 100%;
    }
    .table-light {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    .col-md-2 img {
        max-height: 60px !important;
    }
    .text-success { 
        color: #198754 !important; 
    }
    .text-danger { 
        color: #dc3545 !important; 
    }
    .badge {
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
    .badge.bg-success {
        background-color: #198754 !important;
    }
    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #000 !important;
    }
    .badge.bg-danger {
        background-color: #dc3545 !important;
    }
    .border-light {
        border-color: #dee2e6 !important;
    }
    .bg-light {
        background-color: #f8f9fa !important;
    }
    /* Fix flex layout for printing */
    .row {
        display: flex !important;
        flex-wrap: wrap !important;
    }
    .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
    .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12 {
        float: left !important;
        position: relative !important;
        min-height: 1px !important;
    }
    .col-md-6 {
        width: 50% !important;
    }
    .col-md-4 {
        width: 33.333333% !important;
    }
    .col-md-2 {
        width: 16.666667% !important;
    }
    .col-md-12 {
        width: 100% !important;
    }
}

/* Ensure single page layout */
#invoice-content {
    max-width: 100%;
    margin: 0 auto;
}

.card {
    page-break-inside: avoid;
}

.table {
    page-break-inside: avoid;
}

/* Ensure proper printing */
@media print {
    .row {
        display: flex !important;
        flex-wrap: wrap !important;
    }
    .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
    .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12 {
        float: left !important;
    }
    .col-md-6 {
        width: 50% !important;
    }
    .col-md-4 {
        width: 33.333333% !important;
    }
    .col-md-2 {
        width: 16.666667% !important;
    }
    .col-md-12 {
        width: 100% !important;
    }
}

/* Fix flex display issues */
.d-flex {
    display: flex !important;
}

.justify-content-between {
    justify-content: space-between !important;
}

.flex-wrap {
    flex-wrap: wrap !important;
}

.flex-md-nowrap {
    flex-wrap: nowrap !important;
}

.align-items-center {
    align-items: center !important;
}

@media (max-width: 768px) {
    .flex-md-nowrap {
        flex-wrap: wrap !important;
    }
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>