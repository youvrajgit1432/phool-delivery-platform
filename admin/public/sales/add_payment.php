<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get delivery_id from URL if provided
$delivery_id = isset($_GET['delivery_id']) ? intval($_GET['delivery_id']) : null;

// Get delivery details if delivery_id is provided
$delivery = null;
if ($delivery_id) {
    $delivery = $pdo->prepare("
        SELECT fd.*, b.buyer_name, b.buyer_id 
        FROM flower_deliveries fd 
        JOIN buyers b ON fd.buyer_id = b.buyer_id 
        WHERE fd.delivery_id = ?
    ");
    $delivery->execute([$delivery_id]);
    $delivery = $delivery->fetch();
}

// Get all deliveries with pending payments for dropdown
$pending_deliveries = $pdo->query("
    SELECT fd.delivery_id, fd.delivery_date, fd.total_amount, fd.payment_received, fd.payment_pending, b.buyer_name 
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    WHERE fd.payment_status IN ('pending', 'partial') 
    ORDER BY fd.delivery_date DESC
")->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $delivery_id = intval($_POST['delivery_id']);
        $amount_paid = floatval($_POST['amount_paid']);
        $payment_date = $_POST['payment_date'];
        $payment_mode = $_POST['payment_mode'];
        $transaction_reference = trim($_POST['transaction_reference']) ?: null;
        $remarks = trim($_POST['remarks']) ?: null;
        
        // Get current delivery details
        $current_delivery = $pdo->prepare("SELECT * FROM flower_deliveries WHERE delivery_id = ?");
        $current_delivery->execute([$delivery_id]);
        $current_delivery = $current_delivery->fetch();
        
        if (!$current_delivery) {
            throw new Exception("Delivery record not found.");
        }
        
        // Calculate new payment received amount
        $new_payment_received = $current_delivery['payment_received'] + $amount_paid;
        
        // Check if payment exceeds total amount
        if ($new_payment_received > $current_delivery['total_amount']) {
            throw new Exception("Payment amount cannot exceed the total amount of Rs. " . number_format($current_delivery['total_amount'], 2));
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO buyer_payments 
            (buyer_id, delivery_id, amount_paid, payment_date, payment_mode, transaction_reference, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $current_delivery['buyer_id'], $delivery_id, $amount_paid, $payment_date, 
            $payment_mode, $transaction_reference, $remarks
        ]);
        
        // Update delivery record
        $update_stmt = $pdo->prepare("
            UPDATE flower_deliveries SET 
            payment_received = ? 
            WHERE delivery_id = ?
        ");
        
        $update_stmt->execute([$new_payment_received, $delivery_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Payment recorded successfully!";
        header("Location: view_buyer.php?id=" . $current_delivery['buyer_id']);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to record payment: " . $e->getMessage();
    }
}

// Set page title
$page_title = "Add Payment - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Record Payment</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
        </a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="delivery_id" class="form-label">Select Delivery *</label>
                        <select class="form-select" id="delivery_id" name="delivery_id" required>
                            <option value="">Select Delivery</option>
                            <?php foreach ($pending_deliveries as $delivery_item): ?>
                            <option value="<?php echo $delivery_item['delivery_id']; ?>" 
                                    <?php echo $delivery_id == $delivery_item['delivery_id'] ? 'selected' : ''; ?>
                                    data-total="<?php echo $delivery_item['total_amount']; ?>"
                                    data-paid="<?php echo $delivery_item['payment_received']; ?>"
                                    data-pending="<?php echo $delivery_item['payment_pending']; ?>">
                                <?php echo date('M d, Y', strtotime($delivery_item['delivery_date'])); ?> - 
                                <?php echo htmlspecialchars($delivery_item['buyer_name']); ?> - 
                                Rs. <?php echo number_format($delivery_item['total_amount'], 2); ?> 
                                (Pending: Rs. <?php echo number_format($delivery_item['payment_pending'], 2); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($delivery): ?>
                    <!-- Display delivery details when specific delivery is selected -->
                    <div class="alert alert-info">
                        <h6>Delivery Details:</h6>
                        <p class="mb-1"><strong>Buyer:</strong> <?php echo htmlspecialchars($delivery['buyer_name']); ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></p>
                        <p class="mb-1"><strong>Total Amount:</strong> Rs. <?php echo number_format($delivery['total_amount'], 2); ?></p>
                        <p class="mb-1"><strong>Already Paid:</strong> Rs. <?php echo number_format($delivery['payment_received'], 2); ?></p>
                        <p class="mb-0"><strong>Pending Amount:</strong> Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="amount_paid" class="form-label">Amount Paid (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" 
                               max="<?php echo $delivery ? $delivery['payment_pending'] : ''; ?>" required>
                        <small class="form-text text-muted" id="max_amount_info">
                            <?php if ($delivery): ?>
                            Maximum amount: Rs. <?php echo number_format($delivery['payment_pending'], 2); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_mode" class="form-label">Payment Mode *</label>
                        <select class="form-select" id="payment_mode" name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="esewa">eSewa</option>
                            <option value="khalti">Khalti</option>
                            <option value="connectips">ConnectIPS</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_reference" class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" id="transaction_reference" name="transaction_reference" 
                               placeholder="e.g., transaction ID, voucher number, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="4" 
                                  placeholder="Any additional notes about this payment..."></textarea>
                    </div>
                    
                    <!-- Payment Summary -->
                    <div class="border p-3 bg-light rounded">
                        <h6 class="mb-3">Payment Summary</h6>
                        <div class="row">
                            <div class="col-6">
                                <strong>Total Amount:</strong>
                            </div>
                            <div class="col-6">
                                <span id="total_amount_display">Rs. 0.00</span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Already Paid:</strong>
                            </div>
                            <div class="col-6">
                                <span id="paid_amount_display">Rs. 0.00</span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Pending Amount:</strong>
                            </div>
                            <div class="col-6">
                                <span id="pending_amount_display">Rs. 0.00</span>
                            </div>
                        </div>
                        <hr>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>This Payment:</strong>
                            </div>
                            <div class="col-6">
                                <span id="this_payment_display">Rs. 0.00</span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <strong>Remaining After:</strong>
                            </div>
                            <div class="col-6">
                                <span id="remaining_amount_display">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Record Payment
                    </button>
                    <a href="../sales.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Update payment summary when delivery or amount changes
function updatePaymentSummary() {
    const deliverySelect = document.getElementById('delivery_id');
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    
    if (deliverySelect.value) {
        const selectedOption = deliverySelect.options[deliverySelect.selectedIndex];
        const totalAmount = parseFloat(selectedOption.getAttribute('data-total'));
        const paidAmount = parseFloat(selectedOption.getAttribute('data-paid'));
        const pendingAmount = parseFloat(selectedOption.getAttribute('data-pending'));
        
        // Update displays
        document.getElementById('total_amount_display').textContent = 'Rs. ' + totalAmount.toFixed(2);
        document.getElementById('paid_amount_display').textContent = 'Rs. ' + paidAmount.toFixed(2);
        document.getElementById('pending_amount_display').textContent = 'Rs. ' + pendingAmount.toFixed(2);
        document.getElementById('this_payment_display').textContent = 'Rs. ' + amountPaid.toFixed(2);
        document.getElementById('remaining_amount_display').textContent = 'Rs. ' + (pendingAmount - amountPaid).toFixed(2);
        
        // Update max amount and info
        document.getElementById('amount_paid').max = pendingAmount;
        document.getElementById('max_amount_info').textContent = 'Maximum amount: Rs. ' + pendingAmount.toFixed(2);
        
        // Color code remaining amount
        const remainingElement = document.getElementById('remaining_amount_display');
        const remaining = pendingAmount - amountPaid;
        if (remaining === 0) {
            remainingElement.className = 'text-success';
        } else if (remaining > 0) {
            remainingElement.className = 'text-warning';
        } else {
            remainingElement.className = 'text-danger';
        }
    } else {
        // Reset displays if no delivery selected
        document.getElementById('total_amount_display').textContent = 'Rs. 0.00';
        document.getElementById('paid_amount_display').textContent = 'Rs. 0.00';
        document.getElementById('pending_amount_display').textContent = 'Rs. 0.00';
        document.getElementById('this_payment_display').textContent = 'Rs. 0.00';
        document.getElementById('remaining_amount_display').textContent = 'Rs. 0.00';
        document.getElementById('max_amount_info').textContent = '';
    }
}

// Event listeners
document.getElementById('delivery_id').addEventListener('change', updatePaymentSummary);
document.getElementById('amount_paid').addEventListener('input', updatePaymentSummary);

// Initialize payment summary
updatePaymentSummary();
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>