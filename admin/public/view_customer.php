<?php
// public/view_customer.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get customer ID
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: customers.php");
    exit;
}

// Get customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: customers.php");
    exit;
}

// Get customer orders
$orders = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.quantity * oi.price) as total_amount
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.customer_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC
");
$orders->execute([$customer_id]);
$orders = $orders->fetchAll();

// Set page title
$page_title = "View Customer - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<style>
/* Responsive Table CSS */
.responsive-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.responsive-table {
    width: 100%;
    min-width: 100%;
}

.responsive-table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 1rem 0.75rem;
    white-space: nowrap;
    border-bottom: 2px solid #dee2e6;
}

.responsive-table tbody td {
    padding: 0.75rem;
    vertical-align: middle;
}

.responsive-table tbody tr {
    border-bottom: 1px solid #dee2e6;
}

@media (max-width: 576px) {
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 0.5rem 0.4rem;
        font-size: 0.85rem;
    }
    
    .btn-group.flex-wrap .btn {
        margin-bottom: 0.25rem;
        padding: 0.35rem 0.5rem;
        font-size: 0.70rem;
    }
}

@media (max-width: 768px) {
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 0.65rem;
        font-size: 0.90rem;
    }
    
    .btn-group.flex-wrap {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
}

@media (max-width: 767px) {
    .d-md-table-cell {
        display: none !important;
    }
}

@media (max-width: 991px) {
    .d-lg-table-cell {
        display: none !important;
    }
}

@media (max-width: 1199px) {
    .d-xl-table-cell {
        display: none !important;
    }
}

@media (min-width: 1200px) {
    .d-md-table-cell,
    .d-lg-table-cell,
    .d-xl-table-cell {
        display: table-cell !important;
    }
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Customer Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="customers.php" class="btn btn-sm btn-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Back to Customers
        </a>
        <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-edit me-1"></i> Edit Customer
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Customer Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="30%">ID</th>
                        <td><?php echo $customer['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><?php echo htmlspecialchars($customer['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?php echo !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Customer Type</th>
                        <td>
                            <span class="badge bg-<?php 
                            switch($customer['customer_type']) {
                                case 'normal': echo 'secondary'; break;
                                case 'bulk': echo 'primary'; break;
                                case 'event_planner': echo 'info'; break;
                                case 'wholesaler': echo 'success'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $customer['customer_type'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                            switch($customer['status']) {
                                case 'active': echo 'success'; break;
                                case 'inactive': echo 'warning'; break;
                                case 'banned': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Verification Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                switch($customer['verification_status']) {
                                    case 'verified': echo 'success'; break;
                                    case 'rejected': echo 'danger'; break;
                                    default: echo 'warning';
                                }
                            ?>">
                                <?php echo ucfirst($customer['verification_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Loyalty Points</th>
                        <td><?php echo $customer['loyalty_points']; ?></td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?php echo date('M d, Y H:i', strtotime($customer['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated</th>
                        <td><?php echo date('M d, Y H:i', strtotime($customer['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Order History</h5>
            </div>
            <div class="card-body">
                <?php if (count($orders) > 0): ?>
                    <div class="table-responsive responsive-table-wrapper">
                        <table class="table table-sm responsive-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><a href="view_order.php?id=<?php echo $order['id']; ?>">#<?php echo $order['id']; ?></a></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td><?php echo $order['item_count']; ?></td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                        switch($order['status']) {
                                            case 'completed': echo 'success'; break;
                                            case 'processing': echo 'primary'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No orders found for this customer.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>