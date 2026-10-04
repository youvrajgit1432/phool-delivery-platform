<?php
/**
 * Vendor Orders Management
 * Admin can view and manage orders for specific vendors
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}
// Get database connection
$db = getDBConnection();
// Fetch vendor details
try {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }
    
    // Fetch vendor's orders
        $stmt = $db->prepare("
            SELECT vo.*, 
                   COUNT(voi.id) as total_items
            FROM vendor_orders vo
            LEFT JOIN vendor_order_items voi ON vo.id = voi.vendor_order_id
            WHERE vo.vendor_id = ?
            GROUP BY vo.id
            ORDER BY vo.created_at DESC
        ");
    $stmt->execute([$vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
    $orders = [];
}

$page_title = 'Orders - ' . htmlspecialchars($vendor['store_name']);
$current_page = 'vendor-orders';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <a href="../vendors/edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Back to Vendor
                </a>
                <h1 class="h3">Orders - <?php echo htmlspecialchars($vendor['store_name']); ?></h1>
                <p class="text-muted">View and manage all orders assigned to this vendor</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchOrders" placeholder="Search by order ID or customer...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="exportOrders">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total Amount</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bi bi-inbox"></i> No orders found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="order-row" data-order-status="<?php echo strtolower($order['status']); ?>">
                                    <td><strong>#<?php echo htmlspecialchars($order['id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                    <td>Rs <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo $order['total_items']; ?> items</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'confirmed' => 'info',
                                                'processing' => 'primary',
                                                'shipped' => 'secondary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?vendor_id=<?php echo $vendor['id']; ?>&order_id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-info" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?vendor_id=<?php echo $vendor['id']; ?>&order_id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($order['status'] !== 'cancelled'): ?>
                                            <button type="button" class="btn btn-outline-danger cancel-vendor-order" data-order-id="<?php echo $order['id']; ?>" title="Cancel / Unassign">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger cancel-vendor-order-remove" data-order-id="<?php echo $order['id']; ?>" title="Cancel & Remove Items">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-success uncancel-vendor-order" data-order-id="<?php echo $order['id']; ?>" title="Undo Cancel">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
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
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchOrders').addEventListener('keyup', filterOrders);
        document.getElementById('filterStatus').addEventListener('change', filterOrders);

        function filterOrders() {
            const searchTerm = document.getElementById('searchOrders').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const orderRows = document.querySelectorAll('.order-row');

            orderRows.forEach(row => {
                const status = row.dataset.orderStatus;
                const orderInfo = row.textContent.toLowerCase();
                
                const matchesSearch = orderInfo.includes(searchTerm) || searchTerm === '';
                const matchesStatus = status === statusFilter || statusFilter === '';
                
                row.style.display = (matchesSearch && matchesStatus) ? 'table-row' : 'none';
            });
        }

        document.getElementById('exportOrders').addEventListener('click', function() {
            window.location.href = '../ajax/orders-export.php?vendor_id=<?php echo $vendor['id']; ?>';
        });

        // Cancel / Unassign vendor order (preserve items)
        document.querySelectorAll('.cancel-vendor-order').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var vendorOrderId = this.getAttribute('data-order-id');
                if (!confirm('Cancel and unassign this vendor order? This will mark the vendor order as cancelled and set the main order as unassigned.')) return;
                this.disabled = true;
                performCancel(vendorOrderId, false, this);
            });
        });

        // Cancel and remove vendor_order_items
        document.querySelectorAll('.cancel-vendor-order-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var vendorOrderId = this.getAttribute('data-order-id');
                if (!confirm('Cancel, unassign and REMOVE associated vendor order items? This action will delete line items for the vendor order.')) return;
                this.disabled = true;
                performCancel(vendorOrderId, true, this);
            });
        });

        function performCancel(vendorOrderId, clearItems, btnElem) {
            fetch('../ajax/vendor-order-cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ vendor_order_id: parseInt(vendorOrderId), clear_items: clearItems ? 1 : 0 })
            }).then(function(r){ return r.json(); }).then(function(data){
                if (data.success) {
                    alert(data.message || 'Order cancelled and unassigned');
                    var row = document.querySelector('.order-row[data-order-status] button[data-order-id="' + vendorOrderId + '"]')?.closest('tr');
                    if (row) {
                        var badge = row.querySelector('.badge');
                        if (badge) { badge.textContent = 'Cancelled'; badge.className = 'badge bg-danger'; }
                        row.querySelectorAll('.cancel-vendor-order, .cancel-vendor-order-remove').forEach(function(n){ if (n) n.style.display = 'none'; });
                        // show uncancel button if present
                        var unc = row.querySelector('.uncancel-vendor-order'); if (unc) unc.style.display = '';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Operation failed'));
                    if (btnElem) btnElem.disabled = false;
                }
            }).catch(function(err){ console.error(err); alert('Request failed'); if (btnElem) btnElem.disabled = false; });
        }

        // Un-cancel (soft revert)
        document.querySelectorAll('.uncancel-vendor-order').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var vendorOrderId = this.getAttribute('data-order-id');
                if (!confirm('Undo cancel: reassign this vendor order and mark as assigned?')) return;
                this.disabled = true;
                fetch('../ajax/vendor-order-uncancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ vendor_order_id: parseInt(vendorOrderId) })
                }).then(function(r){ return r.json(); }).then(function(data){
                    if (data.success) {
                        alert(data.message || 'Order restored to assigned');
                        var row = document.querySelector('.order-row[data-order-status] button[data-order-id="' + vendorOrderId + '"]')?.closest('tr');
                        if (row) {
                            var badge = row.querySelector('.badge');
                            if (badge) { badge.textContent = 'Assigned'; badge.className = 'badge bg-info'; }
                            row.querySelectorAll('.cancel-vendor-order, .cancel-vendor-order-remove').forEach(function(n){ if (n) n.style.display = ''; });
                            btn.style.display = 'none';
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Operation failed'));
                        btn.disabled = false;
                    }
                }).catch(function(err){ console.error(err); alert('Request failed'); btn.disabled = false; });
            });
        });
    </script>
</body>
</html>
