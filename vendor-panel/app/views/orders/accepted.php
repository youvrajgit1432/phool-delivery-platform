<?php
/**
 * Accepted Orders Page - Vendor Panel
 * Display vendor's accepted orders
 */
// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$orders = [];
$ordersCount = 0;
if ($vendorId) {
    $query = "
        SELECT 
            vo.id, 
            vo.order_id, 
            vo.order_number, 
            vo.total_amount, 
            vo.subtotal, 
            vo.delivery_fee, 
            vo.discount, 
            vo.status as vendor_status,
            o.rider_assignment_status,
            vo.notes, 
            vo.accepted_at
        FROM vendor_orders vo
        JOIN orders o ON vo.order_id = o.id
        WHERE vo.vendor_id = ? AND vo.status = 'accepted'
        ORDER BY vo.accepted_at DESC LIMIT 100
    ";
    $stmt = $db->query($query, [$vendorId]);
    $orders = $stmt->fetchAll();
    $ordersCount = count($orders);
    
    // Fetch order items for each order
    $orderItems = [];
    foreach ($orders as &$o) {
        $stmt = $db->query('SELECT product_name, quantity FROM vendor_order_items WHERE vendor_order_id = ? ORDER BY id ASC', [$o['id']]);
        $o['items'] = $stmt->fetchAll();
    }
}
?>

<div class="orders-container">
    <!-- Page Header -->
    <div class="orders-header">
        <h1>
            <i class="fas fa-check-circle me-2"></i>Accepted Orders
        </h1>
        <div class="text-muted">Total: <strong><?php echo (int)$ordersCount; ?> Accepted Orders</strong></div>
    </div>

    <!-- Order Status Tabs -->
    <div class="orders-tabs" style="margin-bottom: 20px;">
        <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="btn btn-primary">
            <i class="fas fa-clock me-2"></i>Assigned Orders
        </a>
        <a href="<?php echo htmlspecialchars(vendor_url('/orders/accepted')); ?>" class="btn btn-success active">
            <i class="fas fa-check-circle me-2"></i>Accepted Orders
        </a>
    </div>

    <!-- Toolbar with Filters -->
    <div class="orders-toolbar">
        <!-- Search Bar -->
        <div class="orders-search">
            <input type="text" placeholder="Search by Order ID..." 
                   id="orderSearch" class="form-control">
            <button class="btn btn-primary" id="searchBtn">
                <i class="fas fa-search me-2"></i>Search
            </button>
        </div>

        <!-- Filters -->
        <div class="orders-filters">
            <select id="dateFilter" class="form-select" style="width: auto;">
                <option value="">Any Date</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="custom">Custom Range</option>
            </select>

            <select id="sortFilter" class="form-select" style="width: auto;">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="highest">Highest Amount</option>
                <option value="lowest">Lowest Amount</option>
            </select>
        </div>

        <!-- Active Filters Display -->
        <div class="active-filters" id="activeFilters"></div>
    </div>

    <!-- Orders Table -->
    <div class="orders-table-wrapper">
        <table class="orders-table">
            <thead>
                <tr>
                    <th class="sortable-header">
                        Order ID <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Products <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Accepted Date <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Amount <span class="sort-icon">↕</span>
                    </th>
                    <th>Vendor Status</th>
                    <th>R.S</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $o): ?>
                        <tr data-vendor-order-id="<?php echo $o['id']; ?>">
                            <td class="order-id-cell"><?php echo htmlspecialchars($o['order_number']); ?></td>
                            <td class="order-products-cell">
                                <?php if (!empty($o['items'])): ?>
                                    <div style="max-height: 80px; overflow-y: auto;">
                                        <?php foreach ($o['items'] as $item): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #f0f0f0;">
                                                <span style="flex: 1; font-size: 13px; font-weight: 500;">
                                                    <?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?>
                                                </span>
                                                <span style="background-color: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; min-width: 35px; text-align: center;">
                                                    ×<?php echo (int)$item['quantity']; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No items</span>
                                <?php endif; ?>
                            </td>
                            <td class="order-date-cell"><?php echo date('M d, Y', strtotime($o['accepted_at'])); ?></td>
                            <td class="order-amount-cell">₹<?php 
                                $orderAmount = ((float)$o['subtotal'] - (float)$o['discount']);
                                echo number_format($orderAmount, 2); 
                            ?></td>
                            <td class="order-status-cell">
                                <span class="order-status <?php echo strtolower(htmlspecialchars($o['vendor_status'])); ?>">
                                    <?php 
                                    $vendorStatusText = ucfirst(str_replace('_', ' ', $o['vendor_status']));
                                    echo htmlspecialchars($vendorStatusText);
                                    ?>
                                </span>
                            </td>
                            <td class="order-status-cell">
                                <?php 
                                $riderStatus = $o['rider_assignment_status'] ?? 'unassigned';
                                $isRiderAssigned = ($riderStatus !== 'unassigned' && $riderStatus !== 'rejected');
                                $badgeClass = $isRiderAssigned ? 'bg-success' : 'bg-secondary';
                                $statusText = $isRiderAssigned ? 'Y' : 'N';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                            </td>
                            <td class="order-actions-cell">
                                <?php if ($o['vendor_status'] === 'accepted'): ?>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="updateStatus(<?php echo $o['id']; ?>, 'preparing')"
                                            title="Mark as Preparing">
                                        <i class="fas fa-hourglass-start"></i> Preparing
                                    </button>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/view/' . $o['id'])); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php elseif ($o['vendor_status'] === 'preparing'): ?>
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="updateStatus(<?php echo $o['id']; ?>, 'ready')"
                                            title="Mark as Ready">
                                        <i class="fas fa-check-circle"></i> Ready
                                    </button>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/view/' . $o['id'])); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php elseif ($o['vendor_status'] === 'ready'): ?>
                                    <span class="badge bg-info">Ready for Dispatch</span>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/view/' . $o['id'])); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php elseif ($o['vendor_status'] === 'picked_up' || $o['vendor_status'] === 'delivered'): ?>
                                    <span class="badge bg-info">
                                        <?php echo ($o['vendor_status'] === 'picked_up') ? 'Rider Pickup' : 'Completed'; ?>
                                    </span>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/view/' . $o['id'])); ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/view/' . $o['id'])); ?>"<?php echo $o['id']; ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="<?php echo htmlspecialchars(vendor_url('/orders/invoice/' . $o['order_id'])); ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="Download Invoice">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No accepted orders found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 d-flex justify-content-between align-items-center">
        <div class="text-muted">Showing 1 to 5 of 156 orders</div>
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                </li>
                <li class="page-item active">
                    <a class="page-link" href="#">1</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#">2</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#">3</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- JavaScript for interactivity -->
<script>
// Helper function to build AJAX URLs
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

// Update Order Status (preparing, ready)
function updateStatus(orderId, newStatus) {
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
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating order status: ' + (error.message || error), 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    document.getElementById('searchBtn').addEventListener('click', function() {
        const searchTerm = document.getElementById('orderSearch').value;
        // Implement search logic
    });

    // Date filter
    document.getElementById('dateFilter').addEventListener('change', function(e) {
        const date = e.target.value;
        // Implement date filter logic
    });

    // Sorting
    document.getElementById('sortFilter').addEventListener('change', function(e) {
        const sort = e.target.value;
        // Implement sorting logic
    });

    // Sortable headers
    document.querySelectorAll('.sortable-header').forEach(header => {
        header.addEventListener('click', function() {
            // Implement column sorting
        });
    });
});
</script>

