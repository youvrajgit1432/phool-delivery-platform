<?php
// public/call/edit_call_order.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    $_SESSION['error_message'] = "Invalid order ID";
    header("Location: ../call_orders.php");
    exit;
}

// Get order details
$order = $pdo->prepare("
    SELECT 
        co.*,
        cc.name as customer_name,
        cc.phone_number,
        cc.city,
        cc.street,
        p.name_en as product_name
    FROM call_customer_orders co
    LEFT JOIN call_customers cc ON co.call_customer_id = cc.id
    LEFT JOIN products p ON co.product_id = p.id
    WHERE co.id = ?
");
$order->execute([$order_id]);
$order = $order->fetch();

if (!$order) {
    $_SESSION['error_message'] = "Order not found";
    header("Location: ../call_orders.php");
    exit;
}

// Get all active products
$products = $pdo->query("SELECT id, name_en, price FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $rate = $_POST['rate'];
    $payment_method = $_POST['payment_method'];
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE call_customer_orders 
            SET product_id = ?, quantity = ?, rate = ?, payment_method = ?, status = ?, admin_notes = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$product_id, $quantity, $rate, $payment_method, $status, $admin_notes, $order_id])) {
            $_SESSION['success_message'] = "Order updated successfully!";
            header("Location: view_call_order.php?id=" . $order_id);
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update order.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating order: " . $e->getMessage();
    }
}

// Set page title
$page_title = "Edit Call Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Call Order</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view_call_order.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
            <i class="fas fa-eye me-1"></i> View Order
        </a>
        <a href="../call_orders.php" class="btn btn-sm btn-outline-secondary">
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
                            <label class="form-label">Order ID</label>
                            <input type="text" class="form-control" value="#<?php echo $order['id']; ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($order['customer_name']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product_id" class="form-label">Product *</label>
                            <select class="form-select" id="product_id" name="product_id" required onchange="updateRate(this)">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        data-price="<?php echo $product['price']; ?>"
                                        <?php echo $product['id'] == $order['product_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name_en']); ?> - Rs. <?php echo number_format($product['price'], 2); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo $order['quantity']; ?>" min="1" required onchange="calculateTotal()">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="rate" class="form-label">Rate (Rs.) *</label>
                            <input type="number" step="0.01" class="form-control" id="rate" name="rate" 
                                   value="<?php echo $order['rate']; ?>" min="0" required onchange="calculateTotal()">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="Cash on Delivery" <?php echo $order['payment_method'] == 'Cash on Delivery' ? 'selected' : ''; ?>>Cash on Delivery</option>
                                <option value="Wallets" <?php echo $order['payment_method'] == 'Wallets' ? 'selected' : ''; ?>>Wallets</option>
                                <option value="Bank Transfer" <?php echo $order['payment_method'] == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Order Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="preparing" <?php echo $order['status'] == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="out_for_delivery" <?php echo $order['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="total_amount" 
                                   value="Rs. <?php echo number_format($order['total_amount'], 2); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($order['admin_notes']); ?></textarea>
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
                            <i class="fas fa-save me-1"></i> Update Order
                        </button>
                        <a href="view_call_order.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone_number']); ?></p>
                    <p><strong>City:</strong> <?php echo !empty($order['city']) ? htmlspecialchars($order['city']) : 'N/A'; ?></p>
                    <p><strong>Address:</strong> <?php echo !empty($order['street']) ? htmlspecialchars($order['street']) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function updateRate(select) {
    const price = select.options[select.selectedIndex].getAttribute('data-price');
    if (price) {
        document.getElementById('rate').value = parseFloat(price).toFixed(2);
        calculateTotal();
    }
}

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    const total = quantity * rate;
    document.getElementById('total_amount').value = 'Rs. ' + total.toFixed(2);
}

// Initialize calculations
calculateTotal();
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>