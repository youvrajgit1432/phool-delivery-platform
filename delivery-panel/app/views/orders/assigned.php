<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<?php require_once dirname(__FILE__) . '/../layouts/header.php'; ?>

<div class="content-wrapper">
    <!-- Modern Header -->
    <div class="dashboard-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="dashboard-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h2 class="mb-0">Orders Dashboard</h2>
                    <p class="text-muted mb-0">Manage your deliveries efficiently</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="total-count-badge"><?php echo count($assigned_orders); ?> Orders</span>
            </div>
        </div>
    </div>

    <!-- Modern Statistics Cards -->
    <div class="row mb-4 g-3">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Pending</span>
                    <span class="stat-value"><?php echo $stats['pending'] ?? 0; ?></span>
                    <span class="stat-subtext">Awaiting processing</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card active">
                <div class="stat-icon">
                    <i class="fas fa-road"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">In Progress</span>
                    <span class="stat-value"><?php echo $stats['active'] ?? 0; ?></span>
                    <span class="stat-subtext">Currently delivering</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Completed</span>
                    <span class="stat-value"><?php echo $stats['completed'] ?? 0; ?></span>
                    <span class="stat-subtext">This month</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card failed">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Failed</span>
                    <span class="stat-value"><?php echo $stats['failed'] ?? 0; ?></span>
                    <span class="stat-subtext">Requires attention</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Filter Tabs -->
    <div class="filter-tabs-container mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Order List</h4>
            <div class="view-toggle">
                <button class="view-btn active"><i class="fas fa-list"></i></button>
                <button class="view-btn"><i class="fas fa-th-large"></i></button>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="<?php echo app_url('/orders/assigned?filter=assigned'); ?>" 
               class="filter-tab <?php echo ($current_filter === 'assigned') ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Pending</span>
                <span class="tab-badge"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="<?php echo app_url('/orders/assigned?filter=active'); ?>" 
               class="filter-tab <?php echo ($current_filter === 'active') ? 'active' : ''; ?>">
                <i class="fas fa-road"></i>
                <span>Active</span>
                <span class="tab-badge"><?php echo ($stats['pending'] + $stats['active'] + $stats['failed']) ?? 0; ?></span>
            </a>
            <a href="<?php echo app_url('/orders/assigned?filter=completed'); ?>" 
               class="filter-tab <?php echo ($current_filter === 'completed') ? 'active' : ''; ?>">
                <i class="fas fa-check-double"></i>
                <span>Completed</span>
                <span class="tab-badge"><?php echo $stats['completed'] ?? 0; ?></span>
            </a>
            
            <div class="dropdown filter-dropdown">
                <button class="filter-tab dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-filter"></i>
                    <span>More Filters</span>
                    <i class="fas fa-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item <?php echo ($current_filter === 'accepted') ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/orders/assigned?filter=accepted'); ?>">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span>Accepted</span>
                            <span class="dropdown-badge"><?php echo count(array_filter($activeOrders, fn($o) => $o['delivery_status'] === 'accepted')) ?? 0; ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item <?php echo ($current_filter === 'rejected') ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/orders/assigned?filter=rejected'); ?>">
                            <i class="fas fa-times-circle text-danger me-2"></i>
                            <span>Rejected</span>
                            <span class="dropdown-badge"><?php echo $stats['failed'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item <?php echo ($current_filter === 'all') ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/orders/assigned?filter=all'); ?>">
                            <i class="fas fa-list me-2"></i>
                            <span>All Orders</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Modern Orders Table -->
    <div class="modern-table-card">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="order-id">Order ID</th>
                        <th class="status">Status</th>
                        <th class="customer">Customer</th>
                        <th class="location">Location</th>
                        <th class="amount">Amount</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($assigned_orders)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h4>No Orders Found</h4>
                                <p class="text-muted">You don't have any <?php echo $current_filter === 'all' ? 'orders' : $current_filter . ' orders'; ?> right now.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assigned_orders as $order): ?>
                    <tr>
                        <td class="order-id">
                            <div class="order-id-container">
                                <span class="order-prefix">#</span>
                                <strong><?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></strong>
                            </div>
                        </td>
                        <td class="status">
                            <?php 
                                $status = $order['delivery_status'] ?? 'unknown';
                                $statusClass = 'secondary';
                                $statusLabel = ucfirst($status);
                                
                                if ($status === 'assigned') {
                                    $statusClass = 'warning';
                                    $statusLabel = 'Pending';
                                } elseif ($status === 'accepted') {
                                    $statusClass = 'success';
                                    $statusLabel = 'Accepted';
                                } elseif ($status === 'picked_up') {
                                    $statusClass = 'info';
                                    $statusLabel = 'Picked Up';
                                } elseif ($status === 'on_the_way') {
                                    $statusClass = 'primary';
                                    $statusLabel = 'On the Way';
                                } elseif ($status === 'arrived') {
                                    $statusClass = 'warning';
                                    $statusLabel = 'Arrived';
                                } elseif ($status === 'delivered') {
                                    $statusClass = 'success';
                                    $statusLabel = 'Delivered';
                                } elseif ($status === 'cancelled') {
                                    $statusClass = 'danger';
                                    $statusLabel = 'Rejected';
                                } elseif ($status === 'failed') {
                                    $statusClass = 'danger';
                                    $statusLabel = 'Failed';
                                }
                            ?>
                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                <span class="status-dot"></span>
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td class="customer">
                            <div class="customer-info">
                                <div class="customer-name"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted">Customer</small>
                            </div>
                        </td>
                        <td class="location">
                            <div class="location-info">
                                <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                <?php echo htmlspecialchars($order['delivery_address'] ?? $order['delivery_city'] ?? 'N/A'); ?>
                            </div>
                        </td>
                        <td class="amount">
                            <div class="amount-info">
                                <div class="amount-value">₹<?php echo isset($order['total_amount']) ? number_format($order['total_amount'], 0) : '0'; ?></div>
                                <?php if (isset($order['cod_amount']) && $order['cod_amount'] > 0): ?>
                                    <small class="cod-badge">COD ₹<?php echo number_format($order['cod_amount'], 0); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="actions">
                            <div class="action-buttons">
                                <a href="<?php echo app_url('/orders/view/' . intval($order['order_id'])); ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($status === 'assigned'): ?>
                                    <button type="button" class="btn-action btn-accept accept-order-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" title="Accept Order">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-reject reject-order-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" title="Reject Order">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php elseif ($status === 'accepted'): ?>
                                    <button type="button" class="btn-action btn-pickup pickup-order-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" title="Mark as Picked Up">
                                        <i class="fas fa-box"></i>
                                    </button>
                                <?php elseif ($status === 'picked_up'): ?>
                                    <button type="button" class="btn-action btn-start on-the-way-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" title="Start Delivery">
                                        <i class="fas fa-road"></i>
                                    </button>
                                <?php elseif ($status === 'on_the_way' || $status === 'arrived'): ?>
                                    <?php 
                                        $cod_amount = isset($order['cod_amount']) ? floatval($order['cod_amount']) : 0;
                                        $payment_status = $order['payment_status'] ?? 'pending';
                                        $has_pending_payment = ($cod_amount > 0 && $payment_status === 'pending');
                                        $payment_collected = ($cod_amount > 0 && $payment_status === 'collected');
                                    ?>
                                    
                                    <?php if ($has_pending_payment): ?>
                                        <button type="button" class="btn-action btn-collect collect-payment-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" data-cod="<?php echo htmlspecialchars($order['cod_amount']); ?>" title="Collect Payment">
                                            <i class="fas fa-money-bill"></i>
                                        </button>
                                    <?php elseif ($payment_collected || $cod_amount === 0): ?>
                                        <button type="button" class="btn-action btn-deliver deliver-order-btn" data-order-id="<?php echo intval($order['order_id']); ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>" title="Mark as Delivered">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted no-action">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity Cards -->
    <div class="row mt-4">
        <?php if (!empty($pending_orders) && count($pending_orders) > 0): ?>
        <div class="col-lg-6">
            <div class="activity-card">
                <div class="activity-header">
                    <h5><i class="fas fa-clock text-warning me-2"></i> Recent Pending Orders</h5>
                    <a href="<?php echo app_url('/orders/assigned?filter=assigned'); ?>" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php foreach (array_slice($pending_orders, 0, 5) as $order): ?>
                    <div class="activity-item">
                        <div class="activity-icon pending">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="activity-details">
                            <h6>#<?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></h6>
                            <p class="mb-0"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($order['delivery_city'] ?? 'N/A'); ?></small>
                        </div>
                        <div class="activity-amount">
                            <span class="amount">₹<?php echo isset($order['total_amount']) ? number_format($order['total_amount'], 0) : '0'; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($active_orders) && count($active_orders) > 0): ?>
        <div class="col-lg-6">
            <div class="activity-card">
                <div class="activity-header">
                    <h5><i class="fas fa-truck text-info me-2"></i> Active Deliveries</h5>
                    <a href="<?php echo app_url('/orders/assigned?filter=active'); ?>" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php foreach (array_slice($active_orders, 0, 5) as $order): ?>
                    <div class="activity-item">
                        <?php 
                            $status = $order['delivery_status'] ?? 'unknown';
                            $statusClass = 'info';
                            if ($status === 'accepted') $statusClass = 'success';
                            elseif ($status === 'picked_up') $statusClass = 'primary';
                            elseif ($status === 'on_the_way') $statusClass = 'primary';
                            elseif ($status === 'arrived') $statusClass = 'warning';
                        ?>
                        <div class="activity-icon <?php echo $statusClass; ?>">
                            <i class="fas fa-<?php echo $status === 'on_the_way' ? 'road' : ($status === 'picked_up' ? 'box' : 'check-circle'); ?>"></i>
                        </div>
                        <div class="activity-details">
                            <h6>#<?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></h6>
                            <p class="mb-0"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($order['delivery_city'] ?? 'N/A'); ?></small>
                        </div>
                        <div class="activity-amount">
                            <span class="amount">₹<?php echo isset($order['total_amount']) ? number_format($order['total_amount'], 0) : '0'; ?></span>
                            <small class="status-badge status-<?php echo $statusClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript (unchanged except for class names) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle accept order buttons
    document.querySelectorAll('.accept-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            acceptOrder(orderId, orderNumber, this);
        });
    });

    // Handle reject order buttons
    document.querySelectorAll('.reject-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            const reason = prompt(`Why are you rejecting order ${orderNumber}?`, '');
            if (reason !== null) {
                rejectOrder(orderId, orderNumber, reason, this);
            }
        });
    });

    // Handle pickup order buttons
    document.querySelectorAll('.pickup-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            pickupOrder(orderId, orderNumber, this);
        });
    });

    // Handle on-the-way buttons
    document.querySelectorAll('.on-the-way-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            markOnTheWay(orderId, orderNumber, this);
        });
    });

    // Handle collect payment buttons
    document.querySelectorAll('.collect-payment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            const codAmount = parseFloat(this.dataset.codAmount);
            
            showPaymentModal(orderId, orderNumber, codAmount);
        });
    });

    // Handle deliver order buttons
    document.querySelectorAll('.deliver-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            deliverOrder(orderId, orderNumber, this);
        });
    });
});

// All your existing JavaScript functions remain exactly the same
function acceptOrder(orderId, orderNumber, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo app_url('/ajax/accept-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} accepted! Please proceed to pickup.`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to accept order', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Request failed: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function rejectOrder(orderId, orderNumber, reason, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo app_url('/ajax/reject-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            order_id: orderId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} rejected successfully`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to reject order', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Request failed: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function pickupOrder(orderId, orderNumber, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo app_url('/ajax/pickup-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} picked up! Ready for delivery.`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to update pickup status', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Request failed: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function markOnTheWay(orderId, orderNumber, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('/phool-delivery-platform/delivery-panel/public/ajax/on-the-way-order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} on the way!`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to update status', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Request failed: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function deliverOrder(orderId, orderNumber, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo app_url('/ajax/deliver-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            order_id: orderId,
            cod_collected: 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} marked as delivered successfully!`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to mark as delivered', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Request failed: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function showPaymentModal(orderId, orderNumber, codAmount) {
    const amountCollected = prompt(
        `Enter amount collected for order ${orderNumber}\nCOD Amount: ₹ ${codAmount.toFixed(2)}`,
        codAmount.toFixed(2)
    );

    if (amountCollected !== null) {
        const amount = parseFloat(amountCollected);
        if (isNaN(amount) || amount < 0) {
            showToast('Invalid amount entered', 'warning');
            return;
        }

        const btn = document.querySelector(`.collect-payment-btn[data-order-id="${orderId}"]`);
        const originalHTML = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        let varianceReason = '';
        if (amount !== codAmount) {
            varianceReason = prompt(
                `Amount mismatch!\nExpected: ₹ ${codAmount.toFixed(2)}\nCollected: ₹ ${amount.toFixed(2)}\nReason for variance:`,
                ''
            );
            if (varianceReason === null) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
                return;
            }
        }

        fetch('/phool-delivery-platform/delivery-panel/public/ajax/record-payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                order_id: orderId,
                amount_collected: amount,
                variance_reason: varianceReason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Payment recorded successfully! Amount: ₹ ${amount.toFixed(2)}`, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Failed to record payment', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Request failed: ' + error.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }
}
</script>

<style> 

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
}

.dashboard-icon {
    background: rgba(255,255,255,0.2);
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.total-count-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid var(--gray-200);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevated-shadow);
}

.stat-card.pending {
    border-left: 4px solid var(--warning-color);
}

.stat-card.active {
    border-left: 4px solid var(--info-color);
}

.stat-card.completed {
    border-left: 4px solid var(--success-color);
}

.stat-card.failed {
    border-left: 4px solid var(--danger-color);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-card.pending .stat-icon {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
}

.stat-card.active .stat-icon {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info-color);
}

.stat-card.completed .stat-icon {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.stat-card.failed .stat-icon {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.stat-value {
    display: block;
    font-size: 1.875rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-card.pending .stat-value {
    color: var(--warning-color);
}

.stat-card.active .stat-value {
    color: var(--info-color);
}

.stat-card.completed .stat-value {
    color: var(--success-color);
}

.stat-card.failed .stat-value {
    color: var(--danger-color);
}

.stat-subtext {
    display: block;
    color: var(--gray-400);
    font-size: 0.75rem;
}

/* Filter Tabs */
.filter-tabs-container {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    border: 1px solid var(--gray-200);
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
}

.view-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
    transition: all 0.2s ease;
}

.view-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.view-btn.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    color: var(--gray-600);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.filter-tab:hover {
    background: var(--gray-100);
    border-color: var(--gray-300);
    color: var(--gray-700);
    text-decoration: none;
}

.filter-tab.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.tab-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.125rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.filter-tab.active .tab-badge {
    background: rgba(255,255,255,0.3);
}

.filter-dropdown .dropdown-menu {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    box-shadow: var(--elevated-shadow);
    padding: 0.5rem;
}

.filter-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 0.75rem;
    border-radius: 6px;
    color: var(--gray-700);
    font-size: 0.875rem;
}

.filter-dropdown .dropdown-item:hover {
    background: var(--gray-50);
}

.filter-dropdown .dropdown-item.active {
    background: var(--primary-color);
    color: white;
}

.dropdown-badge {
    background: var(--gray-100);
    padding: 0.125rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.filter-dropdown .dropdown-item.active .dropdown-badge {
    background: rgba(255,255,255,0.3);
}

/* Modern Table */
.modern-table-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--card-shadow);
    border: 1px solid var(--gray-200);
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.modern-table thead th {
    background: var(--gray-50);
    padding: 1rem 1.25rem;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--gray-200);
}

.modern-table tbody td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.modern-table tbody tr:hover {
    background: var(--gray-50);
}

.order-id-container {
    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
}

.order-prefix {
    color: var(--gray-400);
    font-weight: 400;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-warning .status-dot {
    background: var(--warning-color);
}

.status-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-success .status-dot {
    background: var(--success-color);
}

.status-info {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info-color);
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.status-info .status-dot {
    background: var(--info-color);
}

.status-primary {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.status-primary .status-dot {
    background: var(--primary-color);
}

.status-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-danger .status-dot {
    background: var(--danger-color);
}

/* Customer Info */
.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-name {
    font-weight: 500;
    color: var(--gray-800);
}

/* Location Info */
.location-info {
    display: flex;
    align-items: center;
    color: var(--gray-700);
    font-size: 0.875rem;
}

/* Amount Info */
.amount-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.amount-value {
    font-weight: 700;
    font-size: 1.125rem;
    color: var(--gray-800);
}

.cod-badge {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    width: fit-content;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-view {
    background: var(--gray-400);
}

.btn-view:hover {
    background: var(--gray-500);
}

.btn-accept {
    background: var(--success-color);
}

.btn-accept:hover {
    background: #0da271;
}

.btn-reject {
    background: var(--danger-color);
}

.btn-reject:hover {
    background: #dc2626;
}

.btn-pickup {
    background: var(--primary-color);
}

.btn-pickup:hover {
    background: #2563eb;
}

.btn-start {
    background: var(--info-color);
}

.btn-start:hover {
    background: #0891b2;
}

.btn-collect {
    background: var(--warning-color);
}

.btn-collect:hover {
    background: #d97706;
}

.btn-deliver {
    background: var(--success-color);
}

.btn-deliver:hover {
    background: #0da271;
}

.no-action {
    font-size: 0.875rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: var(--gray-600);
    margin-bottom: 0.5rem;
}

/* Activity Cards */
.activity-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    border: 1px solid var(--gray-200);
    height: 100%;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
}

.activity-header h5 {
    margin: 0;
    font-size: 1rem;
    color: var(--gray-800);
}

.view-all {
    color: var(--primary-color);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
}

.view-all:hover {
    text-decoration: underline;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.activity-icon.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
}

.activity-icon.info {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info-color);
}

.activity-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.activity-icon.primary {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary-color);
}

.activity-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
}

.activity-details {
    flex: 1;
}

.activity-details h6 {
    margin: 0 0 0.125rem 0;
    font-size: 0.875rem;
    color: var(--gray-800);
}

.activity-details p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--gray-700);
}

.activity-details small {
    font-size: 0.75rem;
}

.activity-amount {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.activity-amount .amount {
    font-weight: 700;
    color: var(--gray-800);
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .dashboard-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .filter-tabs {
        flex-wrap: wrap;
    }
    
    .filter-tab {
        flex: 1;
        min-width: 100px;
        justify-content: center;
    }
    
    .modern-table thead {
        display: none;
    }
    
    .modern-table tbody td {
        display: block;
        padding: 0.75rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .modern-table tbody tr:last-child td {
        border-bottom: 1px solid var(--gray-200);
    }
    
    .modern-table tbody tr:last-child td:last-child {
        border-bottom: none;
    }
    
    .modern-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--gray-600);
        font-size: 0.75rem;
        text-transform: uppercase;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .order-id::before { content: "Order ID"; }
    .status::before { content: "Status"; }
    .customer::before { content: "Customer"; }
    .location::before { content: "Location"; }
    .amount::before { content: "Amount"; }
    .actions::before { content: "Actions"; }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .activity-item {
        flex-wrap: wrap;
    }
    
    .activity-amount {
        width: 100%;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--gray-200);
    }
}
</style>

<?php require_once dirname(__FILE__) . '/../layouts/footer.php'; ?>