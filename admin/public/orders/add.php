<?php
// public/orders/add.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_number = $_POST['order_number'];
    $customer_id = $_POST['customer_id'];
    $quantity = $_POST['quantity'];
    $rate = $_POST['rate'];
    $total_amount = $_POST['total_amount'];
    $status = $_POST['status'];
    $payment_method = $_POST['payment_method'];
    $payment_status = $_POST['payment_status'];
    $admin_notes = $_POST['admin_notes'];
    
    // Generate a unique order number if not provided
    if (empty($order_number)) {
        $order_number = 'ORD' . date('YmdHis');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, customer_id, quantity, rate, total_amount, status, payment_method, payment_status, admin_notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$order_number, $customer_id, $quantity, $rate, $total_amount, $status, $payment_method, $payment_status, $admin_notes])) {
        $order_id = $pdo->lastInsertId();
        $_SESSION['success_message'] = "Order created successfully!";
        header("Location: edit.php?id=" . $order_id);
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to create order.";
    }
}

// Set page title
$page_title = "Add New Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Order</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>
    </div>
</div>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_number" class="form-label">Order Number</label>
                            <input type="text" class="form-control" id="order_number" name="order_number" 
                                   placeholder="Leave blank to auto-generate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php
                                $customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
                                foreach ($customers as $customer) {
                                    echo "<option value='{$customer['id']}'>{$customer['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" value="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="rate" class="form-label">Rate (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" id="rate" name="rate" value="0.00" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="total_amount" class="form-label">Total Amount (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" value="0.00" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" selected>Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="preparing">Preparing</option>
                                <option value="out_for_delivery">Out for Delivery</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cod">Cash on Delivery</option>
                                <option value="esewa">eSewa</option>
                                <option value="khalti">Khalti</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status" required>
                                <option value="pending" selected>Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create Order
                        </button>
                        <a href="../orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Calculate total amount when quantity or rate changes
document.getElementById('quantity').addEventListener('input', calculateTotal);
document.getElementById('rate').addEventListener('input', calculateTotal);

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    const total = quantity * rate;
    document.getElementById('total_amount').value = total.toFixed(2);
}
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>