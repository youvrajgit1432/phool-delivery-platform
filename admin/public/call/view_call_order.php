<?php
// public/call/view_call_order.php
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
        cc.profile_picture,
        p.name_en as product_name,
        p.name_ne as product_name_ne,
        p.unit
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

// Get customer's order history
$customer_orders = $pdo->prepare("
    SELECT 
        co.*,
        p.name_en as product_name
    FROM call_customer_orders co
    LEFT JOIN products p ON co.product_id = p.id
    WHERE co.call_customer_id = ? AND co.id != ?
    ORDER BY co.order_date DESC
    LIMIT 10
");
$customer_orders->execute([$order['call_customer_id'], $order_id]);
$customer_orders = $customer_orders->fetchAll();

// Set page title
$page_title = "View Call Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Call Order Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../call_orders.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>
        <a href="edit_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary me-2">
            <i class="fas fa-edit me-1"></i> Edit Order
        </a>
        <a href="delete_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger" 
           onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
            <i class="fas fa-trash me-1"></i> Delete
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Order Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Order ID:</th>
                                <td>#<?php echo $order['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
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
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Method:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($order['payment_method']) {
                                        case 'Cash on Delivery': echo 'warning'; break;
                                        case 'Wallets': echo 'info'; break;
                                        case 'Bank Transfer': echo 'success'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo $order['payment_method']; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Order Date:</th>
                                <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Product:</th>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Quantity:</th>
                                <td><?php echo $order['quantity']; ?> <?php echo $order['unit']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Rate:</th>
                                <td>Rs. <?php echo number_format($order['rate'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($order['admin_notes'])): ?>
                <div class="mt-3">
                    <h6>Admin Notes:</h6>
                    <div class="alert alert-light border">
                        <?php echo nl2br(htmlspecialchars($order['admin_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Order History -->
        <?php if (!empty($customer_orders)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Order History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_orders as $history): ?>
                            <tr>
                                <td>#<?php echo $history['id']; ?></td>
                                <td><?php echo htmlspecialchars($history['product_name']); ?></td>
                                <td><?php echo $history['quantity']; ?></td>
                                <td>Rs. <?php echo number_format($history['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($history['payment_method']) {
                                        case 'Cash on Delivery': echo 'warning'; break;
                                        case 'Wallets': echo 'info'; break;
                                        case 'Bank Transfer': echo 'success'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo $history['payment_method']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($history['order_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($order['profile_picture'])): ?>
                <div class="text-center mb-3">
                    <img src="../../storage/uploads/call_customers/<?php echo $order['profile_picture']; ?>" 
                         alt="Profile" class="rounded-circle" width="80" height="80">
                </div>
                <?php endif; ?>
                
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Name:</th>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td><?php echo htmlspecialchars($order['phone_number']); ?></td>
                    </tr>
                    <tr>
                        <th>City:</th>
                        <td><?php echo !empty($order['city']) ? htmlspecialchars($order['city']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Address:</th>
                        <td><?php echo !empty($order['street']) ? htmlspecialchars($order['street']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Total Orders:</th>
                        <td><?php echo count($customer_orders) + 1; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="edit_call_order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Order
                    </a>
                    <a href="../call_orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-1"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>