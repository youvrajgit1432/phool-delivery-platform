<?php
// view.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_order'])) {
        $order_id = intval($_POST['order_id']);
        $notes = trim($_POST['notes']);
        
        // Verify customer first if not verified
        $stmt = $pdo->prepare("
            SELECT c.*, o.customer_id 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order_data = $stmt->fetch();
        
        if ($order_data && $order_data['verification_status'] !== 'verified') {
            $_SESSION['error_message'] = "Cannot approve order. Customer is not verified.";
            header("Location: view.php?id=" . $order_id);
            exit;
        }
        
        // Update order status to confirmed
        $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', admin_notes = ? WHERE id = ?");
        if ($stmt->execute([$notes, $order_id])) {
            // Send confirmation notification
            $stmt = $pdo->prepare("
                SELECT o.*, c.name, c.email, c.phone 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!empty($order['email'])) {
                sendOrderConfirmationEmail($order);
            }
            
            $_SESSION['success_message'] = "Order approved and confirmed successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to approve order.";
        }
        
        header("Location: view.php?id=" . $order_id);
        exit;
    }

    // Assign order to vendor
    if (isset($_POST['assign_vendor'])) {
        $order_id = intval($_POST['order_id'] ?? 0);
        $vendor_id = intval($_POST['vendor_id'] ?? 0);

        try {
            if ($order_id <= 0 || $vendor_id <= 0) throw new Exception('Invalid order or vendor');

            // load order
            $oStmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ? LIMIT 1");
            $oStmt->execute([$order_id]);
            $orderRow = $oStmt->fetch(PDO::FETCH_ASSOC);
            if (!$orderRow) throw new Exception('Order not found');

            // compute totals from order_items
            $itemsStmt = $pdo->prepare("SELECT quantity, unit_price FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$order_id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $total_items = 0; $subtotal = 0.0;
            foreach ($items as $it) { $total_items += floatval($it['quantity']); $subtotal += floatval($it['quantity']) * floatval($it['unit_price']); }

            // prepare vendor_orders insert
            $assignedAt = date('Y-m-d H:i:s');
            $customer_name = $orderRow['customer_name'] ?? $orderRow['name'] ?? '';
            $customer_phone = $orderRow['customer_phone'] ?? $orderRow['phone'] ?? '';
            $customer_address = $orderRow['customer_address'] ?? '';
            $order_number = $orderRow['order_number'] ?? $orderRow['id'];

            $ins = $pdo->prepare("INSERT INTO vendor_orders (vendor_id, order_id, order_number, customer_name, customer_phone, customer_address, total_items, subtotal, delivery_fee, discount, total_amount, payment_method, payment_status, status, notes, assigned_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?)");
            $delivery_fee = $orderRow['delivery_fee'] ?? 0;
            $discount = $orderRow['applied_discount'] ?? 0;
            $total_amount = $orderRow['final_amount'] ?? $orderRow['total_amount'] ?? $subtotal;
            $payment_method = $orderRow['payment_method'] ?? '';
            $payment_status = $orderRow['payment_status'] ?? '';

            $ins->execute([$vendor_id, $order_id, $order_number, $customer_name, $customer_phone, $customer_address, $total_items, $subtotal, $delivery_fee, $discount, $total_amount, $payment_method, $payment_status, $orderRow['notes'] ?? '', $assignedAt]);

            // insert vendor notification
            $notif = $pdo->prepare("INSERT INTO vendor_notifications (vendor_id, notification_type, title, message, data, is_read, created_at) VALUES (?, 'order_assigned', ?, ?, ?, 0, NOW())");
            $title = 'New Order Assigned: ' . $order_number;
            $message = 'You have been assigned order ' . $order_number . '. Please check your vendor panel to accept.';
            $data = json_encode(['order_id' => $order_id, 'order_number' => $order_number, 'amount' => $total_amount]);
            $notif->execute([$vendor_id, $title, $message, $data]);

            // attempt to notify vendor via email and SMS (best-effort)
            try {
                $vStmt = $pdo->prepare("SELECT id, store_name, email, phone FROM vendors WHERE id = ? LIMIT 1");
                $vStmt->execute([$vendor_id]);
                $vendorRow = $vStmt->fetch(PDO::FETCH_ASSOC);
                if ($vendorRow) {
                    // Email
                    if (!empty($vendorRow['email']) && filter_var($vendorRow['email'], FILTER_VALIDATE_EMAIL)) {
                        try {
                            require_once __DIR__ . '/../../../vendor/autoload.php';
                            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                            // load smtp settings
                            $smtpStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption')");
                            $smtpStmt->execute();
                            $smtpSettings = $smtpStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                            $smtp_host = $smtpSettings['smtp_host'] ?? 'smtp.gmail.com';
                            $smtp_port = $smtpSettings['smtp_port'] ?? 587;
                            $smtp_username = $smtpSettings['smtp_username'] ?? '';
                            $smtp_password = $smtpSettings['smtp_password'] ?? '';
                            $smtp_encryption = $smtpSettings['smtp_encryption'] ?? 'tls';
                            $mail->isSMTP();
                            $mail->Host = $smtp_host; $mail->SMTPAuth = true; $mail->Username = $smtp_username; $mail->Password = $smtp_password;
                            $mail->SMTPSecure = ($smtp_encryption === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = $smtp_port;
                            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                            $fromAddress = !empty($smtp_username) ? $smtp_username : 'no-reply@phool-delivery-platform.local';
                            $mail->setFrom($fromAddress, 'Phool Delivery');
                            $mail->addAddress($vendorRow['email'], $vendorRow['store_name']);
                            $mail->isHTML(true);
                            $mail->Subject = 'New Order Assigned: ' . $order_number;
                            $body = '<p>Hi ' . htmlspecialchars($vendorRow['store_name']) . ',</p>';
                            $body .= '<p>You have been assigned a new order <strong>' . htmlspecialchars($order_number) . '</strong>. Total: Rs. ' . htmlspecialchars(number_format($total_amount,2)) . '.</p>';
                            $body .= '<p>Please login to your vendor panel to accept and start preparing the order.</p>';
                            $mail->Body = $body;
                            $mail->send();
                        } catch (Exception $ex) {
                            error_log('Order assign email failed: ' . ($mail->ErrorInfo ?? $ex->getMessage()));
                        }
                    }

                    // SMS
                    if (!empty($vendorRow['phone'])) {
                        try {
                            require_once __DIR__ . '/../../../app/services/SparrowSMSService.php';
                            $sms = new SparrowSMSService($pdo);
                            $phoneFormatted = $vendorRow['phone']; if (strpos($phoneFormatted, '977') !== 0) $phoneFormatted = '977' . $phoneFormatted;
                            $smsMsg = 'New order assigned: ' . $order_number . '. Total: Rs. ' . number_format($total_amount,2) . '. Please check vendor panel.';
                            $smsResp = $sms->sendSMS($phoneFormatted, $smsMsg);
                            if (empty($smsResp['success'])) {
                                error_log('Order assign SMS failed: ' . ($smsResp['message'] ?? json_encode($smsResp)));
                            }
                        } catch (Exception $sx) {
                            error_log('Order assign SMS exception: ' . $sx->getMessage());
                        }
                    }
                }
            } catch (Exception $notifyEx) {
                error_log('Order assign notification error: ' . $notifyEx->getMessage());
            }

            $_SESSION['success_message'] = 'Order assigned to vendor successfully.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Failed to assign order: ' . $e->getMessage();
        }

        header('Location: view.php?id=' . $order_id);
        exit;
    }
    
    if (isset($_POST['verify_customer'])) {
        $customer_id = intval($_POST['customer_id']);
        $verification_method = $_POST['verification_method'];
        $notes = trim($_POST['notes']);
        
        $stmt = $pdo->prepare("UPDATE customers SET verification_status = 'verified', verified_at = NOW(), verification_method = ?, verification_notes = ? WHERE id = ?");
        if ($stmt->execute([$verification_method, $notes, $customer_id])) {
            $_SESSION['success_message'] = "Customer verified successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to verify customer.";
        }
        
        header("Location: view.php?id=" . $_POST['order_id']);
        exit;
    }
    
    // Customer Invoice management functions
    if (isset($_POST['generate_customer_invoice'])) {
        // Generate invoice in the same window
        require_once '../../app/services/CustomerInvoiceService.php';
        $invoiceService = new CustomerInvoiceService($pdo);
        $result = $invoiceService->generateInvoice($id);
        
        if ($result['success']) {
            $_SESSION['success_message'] = "Invoice generated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to generate invoice: " . $result['message'];
        }
        header("Location: view.php?id=" . $id);
        exit;
    }
    
    if (isset($_POST['send_customer_invoice'])) {
        // Send customer invoice via email
        require_once '../../app/services/CustomerInvoiceService.php';
        $invoiceService = new CustomerInvoiceService($pdo);
        $result = $invoiceService->sendInvoiceEmail($id);
        
        if ($result['success']) {
            $_SESSION['success_message'] = "Invoice sent successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to send invoice: " . $result['message'];
        }
        header("Location: view.php?id=" . $id);
        exit;
    }
    
    // New: Handle PDF generation in same window
    if (isset($_POST['generate_pdf_invoice'])) {
        // Generate and download PDF in same window
        require_once '../../app/services/CustomerInvoiceService.php';
        $invoiceService = new CustomerInvoiceService($pdo);
        $result = $invoiceService->generateInvoicePDF($id);
        
        if ($result['success']) {
            // Force download of PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="invoice_' . $id . '.pdf"');
            readfile($result['file_path']);
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to generate PDF: " . $result['message'];
            header("Location: view.php?id=" . $id);
            exit;
        }
    }
    
    // New: Handle print view in same window
    if (isset($_POST['print_invoice'])) {
        // Redirect to print-friendly version in same window
        header("Location: generate_customer_invoice.php?id=" . $id . "&print=1");
        exit;
    }
}

// Get specific order for viewing
$current_order = null;
$order_items = [];
$order_customer = null;
$customer_invoice = null;

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT o.*, c.*, u.full_name as handled_by 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        LEFT JOIN users u ON o.handled_by = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $current_order = $stmt->fetch();
    
    if ($current_order) {
        // FIXED: Use name_en instead of name for products
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name_en as product_name, p.images 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$id]);
        $order_items = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$current_order['customer_id']]);
        $order_customer = $stmt->fetch();
        
        // Check if customer invoice exists
        $stmt = $pdo->prepare("SELECT * FROM customer_invoices WHERE order_id = ?");
        $stmt->execute([$id]);
        $customer_invoice = $stmt->fetch();
        
        // Also check for PDF files
        $stmt = $pdo->prepare("SELECT * FROM customer_invoice_pdfs WHERE order_id = ? ORDER BY generated_at DESC LIMIT 1");
        $stmt->execute([$id]);
        $customer_invoice_pdf = $stmt->fetch();
    }

    // Fetch active vendors for assignment dropdown
    $vendors = [];
    try {
        $vstmt = $pdo->prepare("SELECT id, store_name, email, phone FROM vendors WHERE status = 'active' ORDER BY store_name ASC");
        $vstmt->execute();
        $vendors = $vstmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ve) {
        $vendors = [];
    }
}

// Set page title
$page_title = "View Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';

if (!$current_order) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    include '../../app/views/layouts/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Order Details - #<?php echo $current_order['order_number']; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <!-- Order Details Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Details - #<?php echo $current_order['order_number']; ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Order Status:</strong>
                        <span class="badge bg-<?php 
                        switch($current_order['status']) {
                            case 'pending': echo 'warning'; break;
                            case 'confirmed': echo 'info'; break;
                            case 'preparing': echo 'primary'; break;
                            case 'out_for_delivery': echo 'secondary'; break;
                            case 'delivered': echo 'success'; break;
                            case 'cancelled': echo 'danger'; break;
                            default: echo 'secondary';
                        }
                        ?> ms-2">
                            <?php echo ucfirst($current_order['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Status:</strong>
                        <span class="badge bg-<?php 
                        switch($current_order['payment_status']) {
                            case 'pending': echo 'warning'; break;
                            case 'paid': echo 'success'; break;
                            case 'failed': echo 'danger'; break;
                            case 'refunded': echo 'secondary'; break;
                            default: echo 'secondary';
                        }
                        ?> ms-2">
                            <?php echo ucfirst($current_order['payment_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Quantity:</strong> <?php echo number_format($current_order['quantity'], 2); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Rate:</strong> Rs. <?php echo number_format($current_order['rate'], 2); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Amount:</strong> Rs. <?php echo number_format($current_order['total_amount'], 2); ?>
                    </div>
                </div>
                
                <?php if ($current_order['applied_discount'] > 0): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Discount:</strong> Rs. <?php echo number_format($current_order['applied_discount'], 2); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Final Amount:</strong> Rs. <?php echo number_format($current_order['final_amount'], 2); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($current_order['created_at'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($current_order['updated_at'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($current_order['delivery_date'])): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Delivery Date:</strong> <?php echo date('M d, Y h:i A', strtotime($current_order['delivery_date'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($current_order['admin_notes'])): ?>
                <div class="mb-3">
                    <strong>Admin Notes:</strong>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($current_order['admin_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($current_order['notes'])): ?>
                <div class="mb-3">
                    <strong>Customer Notes:</strong>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($current_order['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Order Items Table -->
                <h6 class="mb-3">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            foreach ($order_items as $item): 
                                $item_total = $item['quantity'] * $item['unit_price'];
                                $subtotal += $item_total;
                            ?>
                            <tr>
                                <td>
                                    <div><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <?php if (!empty($item['images'])): 
                                        $images = json_decode($item['images'], true);
                                        if (is_array($images) && count($images) > 0): ?>
                                        <img src="../../uploads/products/<?php echo htmlspecialchars($images[0]); ?>" alt="Product Image" width="50" class="mt-1">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>Rs. <?php echo number_format($item_total, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td><strong>Rs. <?php echo number_format($subtotal, 2); ?></strong></td>
                            </tr>
                            <?php if ($current_order['applied_discount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                <td><strong class="text-danger">- Rs. <?php echo number_format($current_order['applied_discount'], 2); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                                <td><strong>Rs. <?php echo number_format($current_order['final_amount'] > 0 ? $current_order['final_amount'] : $subtotal, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customer Invoice Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Invoice Management</h5>
            </div>
            <div class="card-body">
                <?php if ($customer_invoice || $customer_invoice_pdf): ?>
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($customer_invoice): ?>
                            <strong>Invoice #<?php echo $customer_invoice['invoice_number']; ?></strong>
                            <br>
                            <small>Generated: <?php echo date('M d, Y', strtotime($customer_invoice['created_at'])); ?></small>
                            <span class="badge bg-<?php 
                                switch($customer_invoice['status']) {
                                    case 'draft': echo 'secondary'; break;
                                    case 'sent': echo 'info'; break;
                                    case 'paid': echo 'success'; break;
                                    case 'overdue': echo 'warning'; break;
                                    case 'cancelled': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?> ms-2">
                                <?php echo ucfirst($customer_invoice['status']); ?>
                            </span>
                            <?php if ($customer_invoice['sent_via_email']): ?>
                            <span class="badge bg-success ms-1">
                                <i class="fas fa-envelope"></i> Sent
                            </span>
                            <?php endif; ?>
                            <?php else: ?>
                            <strong>Invoice PDF Available</strong>
                            <br>
                            <small>Generated: <?php echo date('M d, Y H:i', strtotime($customer_invoice_pdf['generated_at'])); ?></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="generate_pdf_invoice" class="btn btn-sm btn-success">
                                    <i class="fas fa-file-pdf me-1"></i> Download PDF
                                </button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="print_invoice" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye me-1"></i> View/Print Invoice
                                </button>
                            </form>
                            <?php if ((!$customer_invoice || !$customer_invoice['sent_via_email']) && !empty($order_customer['email'])): ?>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="send_customer_invoice" class="btn btn-sm btn-warning">
                                    <i class="fas fa-paper-plane me-1"></i> Send via Email
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($customer_invoice_pdf): ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-file me-1"></i>
                            File: <?php echo htmlspecialchars($customer_invoice_pdf['file_name']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <p>No customer invoice has been generated for this order yet.</p>
                    <div class="d-flex gap-2">
                        <form method="POST">
                            <button type="submit" name="generate_customer_invoice" class="btn btn-primary">
                                <i class="fas fa-file-invoice me-1"></i> Generate Invoice
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Invoice Actions -->
                <?php if ($customer_invoice || $customer_invoice_pdf): ?>
                <div class="mt-3 p-3 border rounded">
                    <h6 class="mb-3">Quick Invoice Actions</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="print_invoice" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="generate_pdf_invoice" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-download me-1"></i> Download
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="print_invoice" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </form>
                        <?php if (!empty($order_customer['email'])): ?>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="send_customer_invoice" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-envelope me-1"></i> Email
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Verification Section -->
        <?php if ($order_customer && $order_customer['verification_status'] !== 'verified'): ?>
        <div class="card mt-4 border-warning">
            <div class="card-header bg-warning text-white">
                <h5 class="card-title mb-0">Customer Verification Required</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This customer has not been verified. Please verify the customer before approving the order.
                </div>
                
                <form method="POST" action="">
                    <button class="btn btn-success">
                        <a href="../actions/verify-customer.php?id=<?php echo $order_customer['id']; ?>&redirect=<?php
                        echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-success" title="Verify Customer">
                            <i class="fas fa-check-circle me-1"></i> Verify Customer
                        </a>
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($order_customer && $order_customer['verification_status'] === 'verified'): ?>
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Customer Verification Status</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Customer verified on <?php echo !empty($order_customer['verified_at']) ? date('M d, Y', strtotime($order_customer['verified_at'])) : 'N/A'; ?>
                    <?php if (!empty($order_customer['verification_method'])): ?>
                    via <?php echo ucfirst(str_replace('_', ' ', $order_customer['verification_method'])); ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($order_customer['verification_notes'])): ?>
                <div class="mb-3">
                    <strong>Verification Notes:</strong>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order_customer['verification_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Order Approval Button -->
                <?php if ($current_order['status'] === 'pending'): ?>
                <form method="POST" action="">
                    <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Approval Notes</label>
                        <textarea class="form-control" id="approval_notes" name="notes" rows="2" placeholder="Add any special instructions or notes..."></textarea>
                    </div>
                    
                    <button type="submit" name="approve_order" class="btn btn-primary">
                        <i class="fas fa-thumbs-up me-1"></i> Approve Order
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Customer Information Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Name:</strong> <?php echo htmlspecialchars($current_order['name']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($current_order['phone']); ?><br>
                    <?php if (!empty($order_customer['email'])): ?>
                    <strong>Email:</strong> <?php echo htmlspecialchars($order_customer['email']); ?><br>
                    <?php endif; ?>
                    <strong>Customer Type:</strong>
                    <span class="badge bg-info ms-1">
                        <?php echo ucfirst($order_customer['customer_type'] ?? 'normal'); ?>
                    </span><br>
                    <strong>Verification Status:</strong>
                    <span class="badge bg-<?php echo $order_customer['verification_status'] === 'verified' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($order_customer['verification_status']); ?>
                    </span>
                </div>
                
                <?php if (!empty($order_customer['address']) || !empty($order_customer['city']) || !empty($order_customer['street'])): ?>
                <div class="mb-3">
                    <strong>Address:</strong>
                    <p class="text-muted">
                        <?php 
                        $address_parts = [];
                        if (!empty($order_customer['address'])) $address_parts[] = $order_customer['address'];
                        if (!empty($order_customer['street'])) $address_parts[] = $order_customer['street'];
                        if (!empty($order_customer['city'])) $address_parts[] = $order_customer['city'];
                        echo nl2br(htmlspecialchars(implode(', ', $address_parts)));
                        ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <a href="../customers.php?action=view&id=<?php echo $current_order['customer_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-user me-1"></i> View Customer Profile
                    </a>
                    
                    <?php if (!empty($order_customer['phone'])): ?>
                    <a href="tel:<?php echo htmlspecialchars($order_customer['phone']); ?>" class="btn btn-outline-success">
                        <i class="fas fa-phone me-1"></i> Call Customer
                    </a>
                    <?php endif; ?>
                    
                    <?php if (($customer_invoice || $customer_invoice_pdf) && !empty($order_customer['email'])): ?>
                    <form method="POST">
                        <button type="submit" name="send_customer_invoice" class="btn btn-outline-info">
                            <i class="fas fa-paper-plane me-1"></i> Email Invoice
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Order Actions Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="status.php?id=<?php echo $current_order['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-sync me-1"></i> Update Status
                    </a>
                    
                    <a href="edit.php?id=<?php echo $current_order['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> Edit Order
                    </a>
                    
                    <!-- Customer Invoice Actions -->
                    <?php if ($customer_invoice || $customer_invoice_pdf): ?>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="print_invoice" class="btn btn-outline-warning">
                            <i class="fas fa-file-invoice me-1"></i> View/Print Invoice
                        </button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="generate_pdf_invoice" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i> Download PDF
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <button type="submit" name="generate_customer_invoice" class="btn btn-outline-warning">
                            <i class="fas fa-file-invoice me-1"></i> Create Invoice
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($current_order['status'] === 'pending' && $order_customer['verification_status'] === 'verified'): ?>
                    <form method="POST" action="" class="mt-2">
                        <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                        <button type="submit" name="approve_order" class="btn btn-success w-100">
                            <i class="fas fa-thumbs-up me-1"></i> Approve Order
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Assign to Vendor -->
                    <div class="mt-3">
                        <div id="assignResult"></div>
                        <form id="assignVendorForm" method="POST" class="row g-2">
                            <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                            <div class="col-12 mb-2">
                                <select name="vendor_id" id="vendor_id" class="form-select form-select-sm" required>
                                    <option value="">Select Vendor to Assign</option>
                                    <?php foreach ($vendors as $v): ?>
                                        <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['store_name']); ?> (<?php echo htmlspecialchars($v['email'] ?: $v['phone']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Quantity</label>
                                <input type="number" name="total_items" id="total_items" class="form-control form-control-sm" min="1" value="<?php echo isset($order_items) ? count($order_items) : 1; ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Delivery Date</label>
                                <input type="datetime-local" name="delivery_date" id="delivery_date" class="form-control form-control-sm" value="<?php echo !empty($current_order['delivery_date']) ? date('Y-m-d\TH:i', strtotime($current_order['delivery_date'])) : ''; ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Admin Notes</label>
                                <input type="text" name="assign_notes" id="assign_notes" class="form-control form-control-sm" placeholder="Optional note for vendor">
                            </div>
                            <div class="col-4">
                                <button type="submit" id="assignBtn" class="btn btn-outline-info w-100">Assign</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <div class="text-muted small">Items</div>
                            <div class="h5 mb-0"><?php echo count($order_items); ?></div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <div class="text-muted small">Total Qty</div>
                            <div class="h5 mb-0"><?php echo number_format($current_order['quantity'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-2 bg-light">
                            <div class="text-muted small">Total Amount</div>
                            <div class="h4 mb-0 text-success">Rs. <?php echo number_format($current_order['final_amount'] > 0 ? $current_order['final_amount'] : $current_order['total_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh page every 30 seconds to check for status updates
setTimeout(function() {
    window.location.reload();
}, 30000);
</script>

<script>
// AJAX assign vendor handler
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('assignVendorForm');
    if (!form) return;

    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        var btn = document.getElementById('assignBtn');
        var resultDiv = document.getElementById('assignResult');
        resultDiv.innerHTML = '';
        btn.disabled = true;
        btn.innerText = 'Assigning...';

        var fd = new FormData(form);

        fetch('../ajax/assign-order-to-vendor.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function(resp) {
            return resp.json();
        }).then(function(json) {
            if (json.success) {
                resultDiv.innerHTML = '<div class="alert alert-success">' + (json.message || 'Assigned') + '</div>';
                // optionally disable form
                btn.innerText = 'Assigned';
                btn.classList.remove('btn-outline-info');
                btn.classList.add('btn-success');
                btn.disabled = true;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Failed to assign') + '</div>';
                btn.disabled = false;
                btn.innerText = 'Assign';
            }
        }).catch(function(err) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Request failed</div>';
            btn.disabled = false;
            btn.innerText = 'Assign';
            console.error(err);
        });
    });
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>