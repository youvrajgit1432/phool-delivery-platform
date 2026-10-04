<?php
/**
 * Orders Page - Vendor Panel
 * Display and manage vendor orders
 */

// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$orders = [];
$ordersCount = 0;

// Get status filter from URL - default to 'active' (all non-cancelled)
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'active';
$validStatuses = ['active', 'all', 'assigned', 'accepted', 'preparing', 'ready', 'picked_up', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'active';
}

if ($vendorId) {
    // Build query based on status filter - JOIN with rider_orders to get delivery status
    $whereClause = 'WHERE vo.vendor_id = ?';
    $params = [$vendorId];
    
    if ($statusFilter === 'active') {
        // Show all except cancelled, picked_up, and delivered (completed)
        $whereClause .= ' AND vo.status NOT IN (?, ?, ?)';
        $params[] = 'cancelled';
        $params[] = 'picked_up';
        $params[] = 'delivered';
    } elseif ($statusFilter === 'picked_up') {
        // Show orders that are picked_up (synced from rider)
        $whereClause .= ' AND vo.status = ?';
        $params[] = 'picked_up';
    } elseif ($statusFilter === 'delivered') {
        // Show orders that are delivered (synced from rider)
        $whereClause .= ' AND vo.status = ?';
        $params[] = 'delivered';
    } elseif ($statusFilter !== 'all') {
        // Show specific status
        $whereClause .= ' AND vo.status = ?';
        $params[] = $statusFilter;
    }
    
    // Query with JOINs to get both vendor status and proper rider assignment status from orders table
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
            vo.assigned_at
        FROM vendor_orders vo
        JOIN orders o ON vo.order_id = o.id
        $whereClause 
        ORDER BY vo.assigned_at DESC 
        LIMIT 100
    ";
    
    $stmt = $db->query($query, $params);
    $orders = $stmt->fetchAll();
    $ordersCount = count($orders);
    
    // Fetch order items for each order
    foreach ($orders as &$o) {
        $stmt = $db->query('SELECT product_name, quantity FROM vendor_order_items WHERE vendor_order_id = ? ORDER BY id ASC', [$o['id']]);
        $o['items'] = $stmt->fetchAll();
    }
}
?>

<style>
    /* Mobile 5-Column Grid Layout for Filter Buttons */
    @media (max-width: 768px) {
        .orders-tabs {
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 5px !important;
            flex-wrap: nowrap !important;
            margin-bottom: 12px;
        }
        
        .btn-filter {
            padding: 5px 3px !important;
            font-size: 0.6rem !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 45px;
            white-space: normal;
            line-height: 1;
            border-radius: 5px;
            background-color: #f0f0f0 !important;
            color: #666 !important;
            border: 1px solid #ddd !important;
        }
        
        .btn-filter:hover {
            background-color: #e8e8e8 !important;
        }
        
        .btn-filter.btn-primary,
        .btn-filter.active {
            background-color: #667eea !important;
            color: white !important;
            border-color: #667eea !important;
        }
        
        .btn-filter i {
            font-size: 0.95rem !important;
            margin: 0 !important;
            margin-bottom: 1px !important;
            display: block;
        }
        
        .btn-filter span {
            display: none;
        }
    }
    
    @media (max-width: 480px) {
        .orders-tabs {
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 4px !important;
            margin-bottom: 10px;
        }
        
        .btn-filter {
            padding: 4px 2px !important;
            font-size: 0.55rem !important;
            min-height: 40px;
            border-radius: 4px;
        }
        
        .btn-filter i {
            font-size: 0.85rem !important;
            margin-bottom: 0px !important;
        }
    }
</style>

<div class="orders-container">
    <!-- Page Header -->
    <div class="orders-page-header">
        <h1>
            <i class="fas fa-receipt"></i>Orders Management
        </h1>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="text-muted" style="font-size: 0.95rem;">
                Showing: <strong><?php echo (int)$ordersCount; ?> 
                <?php 
                $statusLabel = 'Orders';
                if ($statusFilter === 'active') $statusLabel = 'Active Orders';
                elseif ($statusFilter === 'assigned') $statusLabel = 'Assigned Orders';
                elseif ($statusFilter === 'accepted') $statusLabel = 'Accepted Orders';
                elseif ($statusFilter === 'preparing') $statusLabel = 'Preparing Orders';
                elseif ($statusFilter === 'ready') $statusLabel = 'Ready Orders';
                elseif ($statusFilter === 'cancelled') $statusLabel = 'Cancelled Orders';
                echo $statusLabel;
                ?></strong>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()" title="Refresh">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Order Status Filters -->
    <div class="orders-filters">
        <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=active')); ?>" 
           class="btn btn-filter <?php echo $statusFilter === 'active' ? 'btn-primary active' : 'btn-outline-primary'; ?>"
           data-status="active">
            <i class="fas fa-fire"></i>Active <span class="count-badge badge bg-danger text-white">0</span>
        </a>
        <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=assigned')); ?>" 
           class="btn btn-filter <?php echo $statusFilter === 'assigned' ? 'btn-primary active' : 'btn-outline-primary'; ?>"
           data-status="assigned">
            <i class="fas fa-clock"></i>Assigned <span class="count-badge badge bg-warning text-dark">0</span>
        </a>
        <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=accepted')); ?>" 
           class="btn btn-filter <?php echo $statusFilter === 'accepted' ? 'btn-primary active' : 'btn-outline-primary'; ?>"
           data-status="accepted">
            <i class="fas fa-check-circle"></i>Accepted <span class="count-badge badge bg-info text-dark">0</span>
        </a>
        <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=delivered')); ?>" 
           class="btn btn-filter <?php echo $statusFilter === 'delivered' ? 'btn-primary active' : 'btn-outline-primary'; ?>"
           data-status="delivered">
            <i class="fas fa-check"></i>Delivered <span class="count-badge badge bg-success text-white">0</span>
        </a>

        <!-- Dropdown for Additional Filters -->
        <div class="dropdown" style="display: inline-block;">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-filter"></i>More Filters
            </button>
            <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                <li>
                    <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=preparing')); ?>" 
                       class="dropdown-item <?php echo $statusFilter === 'preparing' ? 'active' : ''; ?>"
                       data-status="preparing">
                        <i class="fas fa-hourglass-start"></i>Preparing 
                        <span class="count-badge badge bg-secondary text-white">0</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=ready')); ?>" 
                       class="dropdown-item <?php echo $statusFilter === 'ready' ? 'active' : ''; ?>"
                       data-status="ready">
                        <i class="fas fa-box"></i>Ready 
                        <span class="count-badge badge bg-info text-dark">0</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=picked_up')); ?>" 
                       class="dropdown-item <?php echo $statusFilter === 'picked_up' ? 'active' : ''; ?>"
                       data-status="picked_up">
                        <i class="fas fa-truck"></i>Picked Up 
                        <span class="count-badge badge bg-success text-white">0</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=cancelled')); ?>" 
                       class="dropdown-item <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>"
                       data-status="cancelled">
                        <i class="fas fa-ban"></i>Cancelled 
                        <span class="count-badge badge bg-dark text-white">0</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="<?php echo htmlspecialchars(vendor_url('/orders?status=all')); ?>" 
                       class="dropdown-item <?php echo $statusFilter === 'all' ? 'active' : ''; ?>"
                       data-status="all">
                        <i class="fas fa-list"></i>All Orders 
                        <span class="count-badge badge bg-primary text-white">0</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Toolbar with Search -->
    <div class="orders-toolbar">
        <!-- Search Bar -->
        <div class="orders-search">
            <input type="text" placeholder="Search by Order ID..." 
                   id="orderSearch" class="form-control">
        </div>
    </div>

    <!-- Orders Table -->
    <div class="orders-table-wrapper">
        <table class="orders-table">
            <thead>
                <tr>
                    <th class="sortable-header" style="width: 50px;">
                        S.No <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Order ID <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Products <span class="sort-icon">↕</span>
                    </th>
                    <th class="sortable-header">
                        Date <span class="sort-icon">↕</span>
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
                    <?php foreach ($orders as $index => $o): ?>
                        <tr data-vendor-order-id="<?php echo $o['id']; ?>">
                            <td class="serial-number-cell" style="font-weight: 600; text-align: center;"><?php echo $index + 1; ?></td>
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
                            <td class="order-date-cell"><?php echo date('M d, Y', strtotime($o['assigned_at'])); ?></td>
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
                                <?php if ($o['vendor_status'] === 'assigned'): ?>
                                    <button class="btn btn-sm btn-success accept-order-btn" 
                                            data-vendor-order-id="<?php echo $o['id']; ?>"
                                            title="Accept Order">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button class="btn btn-sm btn-danger reject-order-btn" 
                                            data-vendor-order-id="<?php echo $o['id']; ?>"
                                            title="Reject Order">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php elseif ($o['vendor_status'] === 'accepted'): ?>
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
                    <tr><td colspan="8" class="text-center">No orders found</td></tr>
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
    // Load order counts for all status filters
    loadOrderCounts();
    
    // Refresh counts every 30 seconds
    setInterval(loadOrderCounts, 30000);
    
    // Auto-search functionality with debounce
    const searchInput = document.getElementById('orderSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim().toLowerCase();
            searchTimeout = setTimeout(() => {
                filterOrdersBySearch(searchTerm);
            }, 300);
        });
    }
    
    // Accept Order Button Handler
    document.querySelectorAll('.accept-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const vendorOrderId = this.getAttribute('data-vendor-order-id');
            respondToAssignment(vendorOrderId, 'accepted');
        });
    });

    // Reject Order Button Handler
    document.querySelectorAll('.reject-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const vendorOrderId = this.getAttribute('data-vendor-order-id');
            showRejectionModal(vendorOrderId);
        });
    });

    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function(e) {
            const status = e.target.value;
        });
    }

    // Date filter
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter) {
        dateFilter.addEventListener('change', function(e) {
            const date = e.target.value;
        });
    }

    // Sorting
    const sortFilter = document.getElementById('sortFilter');
    if (sortFilter) {
        sortFilter.addEventListener('change', function(e) {
            const sort = e.target.value;
        });
    }

    // Sortable headers
    document.querySelectorAll('.sortable-header').forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            // Highlight sorted column
            document.querySelectorAll('.sortable-header').forEach(h => {
                h.style.opacity = '0.6';
            });
            this.style.opacity = '1';
        });
    });
});

/**
 * Respond to order assignment (accept or reject)
 */
function respondToAssignment(vendorOrderId, response, notes = '') {
    const url = getAjaxUrl('/ajax/vendor-respond-assignment');
    
    const payload = {
        vendor_order_id: parseInt(vendorOrderId),
        response: response,
        vendor_notes: notes
    };

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        // Ensure cookies/session are sent for same-origin requests
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(async response => {
        // Read raw response text first so we can show it on JSON parse errors
        const text = await response.text();
        if (!response.ok) {
            throw new Error(text || 'Network response was not ok');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text || 'Invalid JSON response');
        }
    })
    .then(data => {
        if (data.success) {
            // Show success message
            showToast('Order ' + response.charAt(0).toUpperCase() + response.slice(1) + ' successfully!', 'success');
            
            // Update the row in the table
            const row = document.querySelector(`tr[data-vendor-order-id="${vendorOrderId}"]`);
            if (row) {
                // Update status cell
                const statusCell = row.querySelector('.order-status-cell .order-status');
                if (statusCell) {
                    statusCell.textContent = response.charAt(0).toUpperCase() + response.slice(1);
                    statusCell.className = 'order-status ' + response.toLowerCase();
                }

                // Update response badge
                const responseCell = row.querySelector('.order-response-cell');
                if (responseCell) {
                    if (response === 'accepted') {
                        responseCell.innerHTML = '<span class="badge bg-success">Accepted</span>';
                    } else if (response === 'rejected') {
                        responseCell.innerHTML = '<span class="badge bg-danger">Rejected</span>';
                    }
                }

                // Update action buttons
                const actionCell = row.querySelector('.order-actions-cell');
                if (actionCell) {
                    const vendorOrderId = row.getAttribute('data-vendor-order-id');
                    const baseUrl = '<?php echo htmlspecialchars(vendor_url('/orders/view/')); ?>';
                    actionCell.innerHTML = '<a href="' + baseUrl + vendorOrderId + '" class="btn btn-sm btn-primary" title="View Details"><i class="fas fa-eye"></i> View</a>';
                }
            }

            // Reload the page after a short delay to refresh data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
            // Try to update counts even if there's an error
            updateCountBadges();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show server response or parse error in toast for easier debugging
        showToast('An error occurred while processing your response: ' + (error.message || error), 'error');
    });
}

/**
 * Show rejection reason modal
 */
function showRejectionModal(vendorOrderId) {
    const reason = prompt('Please provide a reason for rejection (optional):', '');
    
    if (reason !== null) { // User clicked OK (not Cancel)
        respondToAssignment(vendorOrderId, 'rejected', reason);
    }
}

/**
 * Load order counts for all status filters via AJAX
 */
function loadOrderCounts() {
    fetch(getAjaxUrl('/ajax/get-order-counts'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            console.error('Error response:', text);
            throw new Error(text || 'Failed to load order counts');
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (data.success && data.counts) {
            // Update count badges for all status filters
            const counts = data.counts;
            Object.keys(counts).forEach(status => {
                const badges = document.querySelectorAll(`[data-status="${status}"] .count-badge`);
                badges.forEach(badge => {
                    badge.textContent = counts[status];
                    // Animate badge update
                    badge.style.animation = 'none';
                    setTimeout(() => {
                        badge.style.animation = 'pulse 0.5s ease';
                    }, 10);
                });
            });
        }
    })
    .catch(error => {
        console.error('Error loading order counts:', error);
    });
}

/**
 * Update counts after status change
 */
function updateCountBadges() {
    loadOrderCounts();
}

/**
 * Filter orders by search term (auto-search with better logic)
 */
function filterOrdersBySearch(searchTerm) {
    const tableRows = document.querySelectorAll('.orders-table tbody tr');
    let visibleCount = 0;
    
    searchTerm = searchTerm.toLowerCase().trim();
    
    if (searchTerm === '') {
        // Show all rows if search is empty
        tableRows.forEach(row => {
            row.style.display = '';
            visibleCount++;
        });
    } else {
        tableRows.forEach(row => {
            // Get searchable columns
            const orderIdCell = row.querySelector('.order-id-cell');
            const customerCell = row.querySelector('td:nth-child(3)');
            const orderIdText = orderIdCell ? orderIdCell.textContent.toLowerCase() : '';
            const customerText = customerCell ? customerCell.textContent.toLowerCase() : '';
            
            // Show row if order ID or customer matches search term
            if (orderIdText.includes(searchTerm) || customerText.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Show/hide "no results" message
    const tbody = document.querySelector('.orders-table tbody');
    let noResultsRow = tbody.querySelector('.no-results-row');
    
    if (visibleCount === 0 && tableRows.length > 0) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = '<td colspan="8" style="text-align: center; padding: 2rem; color: #999;">No orders found matching your search.</td>';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}
</script>

