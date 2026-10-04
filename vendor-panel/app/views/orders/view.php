<?php
/**
 * Order Details View Page - Vendor Panel
 * Display detailed order information with product details (NO CUSTOMER DETAILS)
 */
// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$orderId = $_GET['order_id'] ?? null;

// Note: Order validation is done in the router before this file is included
if (!$vendorId || !$orderId) {
    http_response_code(404);
    echo '<div class="container mt-5"><h1>404 - Order Not Found</h1></div>';
    exit;
}


// Fetch order details (already validated in router)
$stmt = $db->query(
    'SELECT * FROM vendor_orders WHERE id = ? AND vendor_id = ?',
    [$orderId, $vendorId]
);
$order = $stmt->fetch();

// Fetch order items (products)
$stmt = $db->query(
    'SELECT * FROM vendor_order_items WHERE vendor_order_id = ? ORDER BY id ASC',
    [$orderId]
);
$orderItems = $stmt->fetchAll();

// Calculate totals
$itemsTotal = 0;
foreach ($orderItems as $item) {
    $itemsTotal += $item['item_total'] ?? 0;
}
?>

<div class="order-view-container">
    <!-- Page Header with Back Button -->
    <div class="order-view-header" style="margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-outline-secondary" style="width: auto;">
                <i class="fas fa-arrow-left me-2"></i>Back to Orders
            </a>
            <h1 style="margin: 0; flex: 1;">
                <i class="fas fa-eye me-2"></i>Order Details
            </h1>
            <span class="order-status <?php echo strtolower(htmlspecialchars($order['status'])); ?>" style="font-size: 14px; padding: 8px 16px;">
                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
            </span>
        </div>
    </div>

    <!-- Order Header Section -->
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header bg-light" style="border-bottom: 1px solid #dee2e6;">
            <h5 style="margin: 0;">Order Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted">Order Number</label>
                        <p class="fs-5 fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Order Date</label>
                        <p class="fs-5"><?php echo date('M d, Y H:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Payment Method</label>
                        <p class="fs-5"><?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted">Payment Status</label>
                        <p class="fs-5">
                            <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?>
                            </span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Assigned Date</label>
                        <p class="fs-5"><?php echo date('M d, Y H:i A', strtotime($order['assigned_at'])); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Accepted Date</label>
                        <p class="fs-5">
                            <?php 
                            if ($order['accepted_at']) {
                                echo date('M d, Y H:i A', strtotime($order['accepted_at']));
                            } else {
                                echo '<span class="text-muted">Not yet accepted</span>';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items / Products Section -->
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header bg-light" style="border-bottom: 1px solid #dee2e6;">
            <h5 style="margin: 0;">
                <i class="fas fa-box me-2"></i>Order Items (<?php echo count($orderItems); ?> Products)
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($orderItems)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50%;">Product Name</th>
                                <th style="width: 15%; text-align: center;">Quantity</th>
                                <th style="width: 15%; text-align: right;">Unit Price</th>
                                <th style="width: 20%; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td class="fw-500">
                                        <i class="fas fa-flower me-2" style="color: #e91e63;"></i>
                                        <?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge bg-info"><?php echo (int)$item['quantity']; ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        ₹<?php echo number_format((float)$item['unit_price'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        ₹<?php echo number_format((float)$item['item_total'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>No items in this order
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Summary Section -->
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header bg-light" style="border-bottom: 1px solid #dee2e6;">
            <h5 style="margin: 0;">Order Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-borderless" style="margin-bottom: 0;">
                        <tr>
                            <td class="text-end fw-500">Items Subtotal:</td>
                            <td class="text-end" style="width: 150px;">
                                ₹<?php echo number_format((float)$order['subtotal'], 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-end fw-500">Delivery Fee:</td>
                            <td class="text-end">
                                ₹<?php echo number_format((float)($order['delivery_fee'] ?? 0), 2); ?>
                            </td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                        <tr style="border-top: 1px solid #dee2e6;">
                            <td class="text-end fw-500">Discount:</td>
                            <td class="text-end text-danger">
                                -₹<?php echo number_format((float)$order['discount'], 2); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr style="border-top: 2px solid #dee2e6; font-weight: 700; font-size: 16px;">
                            <td class="text-end">Total Order Amount:</td>
                            <td class="text-end" style="color: #28a745;">
                                ₹<?php 
                                $orderTotal = ((float)$order['subtotal'] - (float)$order['discount']);
                                echo number_format($orderTotal, 2); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-end text-muted fw-500">Total Items:</td>
                            <td class="text-end text-muted">
                                <?php echo (int)$order['total_items']; ?> items
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor commission is not shown in vendor panel (centralized pricing model) -->

    <!-- Order Notes Section -->
    <?php if (!empty($order['notes'])): ?>
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header bg-light" style="border-bottom: 1px solid #dee2e6;">
            <h5 style="margin: 0;">
                <i class="fas fa-sticky-note me-2"></i>Special Instructions
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-0" style="white-space: pre-wrap;">
                <?php echo htmlspecialchars($order['notes']); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div style="display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
        <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Orders
        </a>
        
        <!-- Order Status Action Buttons -->
        <?php if ($order['status'] === 'assigned'): ?>
            <!-- Accept/Reject for assigned orders -->
            <button class="btn btn-success" onclick="acceptOrder(<?php echo $order['id']; ?>)">
                <i class="fas fa-check me-2"></i>Accept Order
            </button>
            <button class="btn btn-danger" onclick="rejectOrder(<?php echo $order['id']; ?>)">
                <i class="fas fa-times me-2"></i>Reject Order
            </button>
        <?php elseif ($order['status'] === 'accepted'): ?>
            <!-- Mark as preparing for accepted orders -->
            <button class="btn btn-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')">
                <i class="fas fa-cooking me-2"></i>Mark as Preparing
            </button>
        <?php elseif ($order['status'] === 'preparing'): ?>
            <!-- Mark as ready for preparing orders -->
            <button class="btn btn-warning" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')">
                <i class="fas fa-check-circle me-2"></i>Mark as Ready
            </button>
        <?php elseif ($order['status'] === 'ready'): ?>
            <!-- Ready state - awaiting dispatch -->
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Order is ready for pickup/dispatch. Waiting for delivery assignment.
            </div>
        <?php endif; ?>
        
        <a href="<?php echo htmlspecialchars(vendor_url('/orders/invoice/' . $order['order_id'])); ?>" class="btn btn-info">
            <i class="fas fa-download me-2"></i>Download Invoice
        </a>
    </div>
</div>

<style>
.order-view-container {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
}

.order-status {
    display: inline-block;
    border-radius: 20px;
    font-weight: 500;
    font-size: 12px;
    text-transform: uppercase;
}

.order-status.accepted {
    background-color: #d4edda;
    color: #155724;
}

.order-status.assigned {
    background-color: #fff3cd;
    color: #856404;
}

.order-status.preparing {
    background-color: #cfe2ff;
    color: #084298;
}

.order-status.ready {
    background-color: #e2e3e5;
    color: #383d41;
}

.order-status.dispatched {
    background-color: #cff4fc;
    color: #055160;
}

.order-status.delivered {
    background-color: #d1e7dd;
    color: #0f5132;
}

.order-status.cancelled {
    background-color: #f8d7da;
    color: #842029;
}

.order-status.returned {
    background-color: #f8d7da;
    color: #842029;
}

.card {
    border: 1px solid #dee2e6;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.fw-500 {
    font-weight: 500;
}
</style>

<!-- JavaScript for Order Status Management -->
<script>
// Helper function to build AJAX URLs
// AJAX endpoints are routed via public/index.php router which expects /ajax/* paths
function getAjaxUrl(endpoint) {
    // On localhost: /phool-delivery-platform/vendor-panel/ prefix needed
    // On online: / prefix (no vendor-panel path)
    // Check if we're on localhost by looking for the vendor-panel prefix in current URL
    const isLocalhost = window.location.pathname.includes('/phool-delivery-platform/vendor-panel/');
    if (isLocalhost) {
        return '/phool-delivery-platform/vendor-panel' + endpoint;
    }
    return endpoint;
}

function acceptOrder(orderId) {
    if (!confirm('Accept this order?')) return;
    updateOrderStatus(orderId, 'accepted');
}

function rejectOrder(orderId) {
    if (!confirm('Reject this order? This action cannot be undone.')) return;
    updateOrderStatus(orderId, 'cancelled');
}

function updateOrderStatus(orderId, newStatus) {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';

    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);

    fetch(getAjaxUrl('/ajax/update-order-status'), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Response error:', text);
            throw new Error(text || 'Network response was not ok');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Order status updated successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to update order status'), 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating order status: ' + (error.message || error), 'error');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}
</script>

