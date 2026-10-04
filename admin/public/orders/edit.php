<?php
// edit.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get order details
$current_order = null;
$order_items = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $current_order = $stmt->fetch();
    
    if ($current_order) {
        // FIXED: Use name_en instead of name for products
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name_en as product_name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$id]);
        $order_items = $stmt->fetchAll();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle order update logic here
    $order_number = $_POST['order_number'];
    $customer_id = $_POST['customer_id'];
    $quantity = $_POST['quantity'];
    $rate = $_POST['rate'];
    $total_amount = $_POST['total_amount'];
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    $admin_notes = $_POST['admin_notes'];
    
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET order_number = ?, customer_id = ?, quantity = ?, rate = ?, total_amount = ?, status = ?, 
            payment_status = ?, admin_notes = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$order_number, $customer_id, $quantity, $rate, $total_amount, $status, $payment_status, $admin_notes, $id])) {
        $_SESSION['success_message'] = "Order updated successfully!";
        header("Location: view.php?id=" . $id);
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to update order.";
    }
}

// Set page title
$page_title = "Edit Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';

if (!$current_order) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    include '../../app/views/layouts/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Order - #<?php echo $current_order['order_number']; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Back to Order
        </a>
        <a href="../orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list me-1"></i> All Orders
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
                                   value="<?php echo htmlspecialchars($current_order['order_number']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <?php
                                $customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
                                foreach ($customers as $customer) {
                                    $selected = $customer['id'] == $current_order['customer_id'] ? 'selected' : '';
                                    echo "<option value='{$customer['id']}' $selected>{$customer['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo $current_order['quantity']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="rate" class="form-label">Rate (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" id="rate" name="rate" 
                                   value="<?php echo $current_order['rate']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="total_amount" class="form-label">Total Amount (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" 
                                   value="<?php echo $current_order['total_amount']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo $current_order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $current_order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="preparing" <?php echo $current_order['status'] == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="out_for_delivery" <?php echo $current_order['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                <option value="delivered" <?php echo $current_order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $current_order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status" required>
                                <option value="pending" <?php echo $current_order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $current_order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $current_order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $current_order['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($current_order['admin_notes']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Order Items Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Order Items</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus me-1"></i> Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td>Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>Rs. <?php echo number_format($item['total_price'], 2); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="delete_item.php?id=<?php echo $item['id']; ?>&order_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Item to Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="add_item.php">
                <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product</label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php
                            // FIXED: Use name_en instead of name for products
                            $products = $pdo->query("SELECT id, name_en, price FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();
                            foreach ($products as $product) {
                                echo "<option value='{$product['id']}' data-price='{$product['price']}'>{$product['name_en']} - Rs. " . number_format($product['price'], 2) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update price field when product selection changes
document.getElementById('product_id').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.dataset.price) {
        document.getElementById('price').value = selectedOption.dataset.price;
    }
});

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