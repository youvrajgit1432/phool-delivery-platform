<?php
/**
 * Vendor Management Dashboard
 * Admin panel for managing all vendors and their stores
 */

require_once '../bootstrap/app.php';
require_once '../app/middleware/AdminMiddleware.php';

// Check admin authentication
requireAuth();

$page_title = 'Vendor Management';
$current_page = 'vendors';

// Get database connection
$db = getDBConnection();

// Determine which view to show: 'active' (default), 'suspended', 'pending', 'trash', or 'all'
$view = $_GET['view'] ?? (isset($_GET['show_trash']) && $_GET['show_trash'] ? 'trash' : 'active');

switch ($view) {
    case 'trash':
        $where = "WHERE v.deleted_at IS NOT NULL";
        break;
    case 'suspended':
        $where = "WHERE v.status = 'suspended' AND v.deleted_at IS NULL";
        break;
    case 'pending':
        $where = "WHERE v.status = 'pending' AND v.deleted_at IS NULL";
        break;
    case 'all':
        $where = "";
        break;
    default:
        $where = "WHERE v.status = 'active' AND v.deleted_at IS NULL";
}

$vendors_query = "SELECT v.*,
                        (
                            (
                                SELECT COUNT(*) FROM vendor_products vp2 WHERE vp2.vendor_id = v.id AND (vp2.deleted_at IS NULL OR vp2.deleted_at = '')
                            )
                            +
                            (
                                SELECT COUNT(*) FROM vendor_product_map vpm2 WHERE vpm2.vendor_id = v.id
                            )
                        ) AS total_products,
                         (
                             SELECT COUNT(*) FROM vendor_orders vo2 WHERE vo2.vendor_id = v.id
                         ) AS total_orders,
                         (
                             SELECT COALESCE(SUM(vo3.total_amount), 0) FROM vendor_orders vo3 WHERE vo3.vendor_id = v.id AND vo3.status = 'completed'
                         ) AS total_sales
                  FROM vendors v " . $where . " GROUP BY v.id ORDER BY v.created_at DESC";

try {
    $vendors = $db->query($vendors_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vendors = [];
    $error = "Error loading vendors: " . $e->getMessage();
}

// Load last notification per vendor (for display in list)
$lastNotifications = [];
if (!empty($vendors)) {
    $vendorIds = array_column($vendors, 'id');
    $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
    try {
        $sql = "SELECT vn.vendor_id, vn.notification_type, vn.title, vn.message, vn.data, vn.created_at, vn.performed_by_admin, u.full_name as performed_by_name
                FROM vendor_notifications vn
                LEFT JOIN users u ON vn.performed_by_admin = u.id
                JOIN (
                    SELECT vendor_id, MAX(created_at) as maxt FROM vendor_notifications WHERE vendor_id IN ($placeholders) GROUP BY vendor_id
                ) latest ON vn.vendor_id = latest.vendor_id AND vn.created_at = latest.maxt";
        $stmt = $db->prepare($sql);
        $stmt->execute($vendorIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $lastNotifications[intval($r['vendor_id'])] = $r;
        }
    } catch (Exception $ne) {
        // ignore — leave lastNotifications empty
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .table-hover tbody tr {
            transition: background-color 0.15s ease-in-out;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .table td, .table th {
            vertical-align: middle;
        }

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
            transition: background-color 0.15s ease-in-out;
        }

        .responsive-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        /* Mobile responsive */
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
            
            .badge {
                font-size: 0.70rem;
                padding: 0.35rem 0.5rem;
            }
        }

        /* Tablet responsive */
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
            
            .btn-group.flex-wrap .btn {
                flex: 0 0 calc(50% - 0.125rem);
                margin-bottom: 0.25rem;
            }
        }

        /* Column visibility */
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

        /* Last send column compact styling */
        .last-send-cell .small { white-space: nowrap; }
        .last-send-badge { font-size: 0.75rem; padding: 0.35rem 0.5rem; }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include '../app/views/layouts/header.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h3 mb-0">Vendor Management</h1>
                                    <p class="text-muted mt-1">Manage all vendor stores and their operations</p>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <a href="vendors/add.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add New Vendor
                                    </a>
                                    <?php $currentView = $_GET['view'] ?? (isset($_GET['show_trash']) && $_GET['show_trash'] ? 'trash' : ''); ?>
                                    <div class="btn-group ms-2" role="group">
                                        <button type="button" class="btn view-filter-btn <?php echo $currentView === '' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" data-view="active">Active</button>
                                        <button type="button" class="btn view-filter-btn <?php echo $currentView === 'suspended' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" data-view="suspended">Suspended</button>
                                        <button type="button" class="btn view-filter-btn <?php echo $currentView === 'pending' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" data-view="pending">Pending</button>
                                        <button type="button" class="btn view-filter-btn <?php echo $currentView === 'trash' ? 'btn-dark' : 'btn-outline-dark'; ?>" data-view="trash">Trash</button>
                                        <button type="button" class="btn view-filter-btn <?php echo $currentView === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" data-view="all">All</button>
                                    </div>
                                </div>
                            </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="row mb-4">
            <div class="col-md-6">
                <input type="text" class="form-control" id="searchVendors" placeholder="Search by name, email, or phone..." autocomplete="off">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1" id="exportVendors">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-secondary" id="resetFilters" title="Reset all filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="filterLoading" class="alert alert-info d-none" role="alert">
            <i class="bi bi-hourglass-split"></i> Loading vendors...
        </div>

        <!-- Vendors Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive responsive-table-wrapper">
                    <table class="table table-hover table-striped responsive-table" id="vendorsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;" class="d-none d-lg-table-cell">ID</th>
                                <th style="width: 20%;">Store Name</th>
                                <th style="width: 15%;" class="d-none d-md-table-cell">Owner Name</th>
                                <th style="width: 15%;" class="d-none d-lg-table-cell">Email</th>
                                <th style="width: 12%;" class="d-none d-md-table-cell">Phone</th>
                                <th style="width: 8%;" class="d-none d-lg-table-cell">Category</th>
                                <th style="width: 8%;">Status</th>
                                <th style="width: 8%;" class="d-none d-xl-table-cell">Products</th>
                                <th style="width: 8%;" class="d-none d-xl-table-cell">Orders</th>
                                <th style="width: 10%;" class="d-none d-lg-table-cell">Sales</th>
                                <th style="width: 12%;" class="d-none d-lg-table-cell">Last Send</th>
                                <th style="width: 15%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vendorsTableBody">
                            <?php if (empty($vendors)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox"></i> No vendors found.
                                        <a href="vendors/add.php" class="ms-2">Create a new vendor</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vendors as $vendor): ?>
                                    <tr class="vendor-row" data-vendor-search="<?php echo strtolower($vendor['store_name'] . ' ' . $vendor['email'] . ' ' . $vendor['phone']); ?>" data-vendor-status="<?php echo strtolower($vendor['status']); ?>" data-vendor-deleted="<?php echo !empty($vendor['deleted_at']) ? '1' : '0'; ?>">
                                        <td class="d-none d-lg-table-cell"><strong><?php echo $vendor['id']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($vendor['logo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($vendor['logo_url']); ?>" alt="Logo" style="width:40px;height:40px;object-fit:cover;border-radius:8px;margin-right:10px;">
                                                <?php else: ?>
                                                    <div style="width:40px;height:40px;border-radius:8px;background:#e9ecef;color:#6c757d;display:flex;align-items:center;justify-content:center;margin-right:10px;font-weight:600;font-size:0.9rem;">
                                                        <?php echo htmlspecialchars(strtoupper(substr($vendor['store_name'] ?? '', 0, 1))); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($vendor['store_name']); ?></strong>
                                                    <div class="d-md-none small text-muted"><?php echo htmlspecialchars($vendor['phone']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></td>
                                        <td class="d-none d-lg-table-cell">
                                            <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>" style="font-size: 0.90rem;">
                                                <?php echo htmlspecialchars($vendor['email']); ?>
                                            </a>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($vendor['phone']); ?></td>
                                        <td class="d-none d-lg-table-cell">
                                            <span class="badge bg-info text-dark" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $vendor['store_category']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($vendor['deleted_at'])): ?>
                                                <span class="badge bg-secondary">Trashed</span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $vendor['status'] === 'active' ? 'success' : ($vendor['status'] === 'suspended' ? 'danger' : ($vendor['status'] === 'pending' ? 'warning' : 'secondary')); ?>">
                                                    <?php echo ucfirst($vendor['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-none d-xl-table-cell">
                                            <span class="badge bg-light text-dark"><?php echo $vendor['total_products']; ?></span>
                                        </td>
                                        <td class="d-none d-xl-table-cell">
                                            <span class="badge bg-light text-dark"><?php echo $vendor['total_orders']; ?></span>
                                        </td>
                                        <td class="d-none d-lg-table-cell">
                                            <strong>₹<?php echo number_format($vendor['total_sales'], 0); ?></strong>
                                        </td>
                                        <td class="d-none d-lg-table-cell">
                                            <?php
                                            $ln = $lastNotifications[$vendor['id']] ?? null;
                                            if ($ln) {
                                                $ldata = json_decode($ln['data'], true) ?: [];
                                                $method = $ldata['method'] ?? ($ln['notification_type'] ?? 'info');
                                                $time = date('M d, H:i', strtotime($ln['created_at']));
                                                $badgeClass = ($method === 'email' ? 'primary' : ($method === 'sms' ? 'info' : 'secondary'));
                                                echo "<span class=\"badge bg-$badgeClass\" style=\"font-size: 0.70rem;\">" . strtoupper(htmlspecialchars($method)) . "</span>";
                                                echo "<div class='small text-muted mt-1'>" . htmlspecialchars($time) . "</div>";
                                            } else {
                                                echo '<span class="text-muted">—</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm flex-wrap" role="group">
                                                <a href="vendors/view.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-info" title="View Details" data-bs-toggle="tooltip">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="vendors/edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-warning" title="Edit Vendor" data-bs-toggle="tooltip">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (!empty($vendor['deleted_at'])): ?>
                                                    <button type="button" class="btn btn-outline-success" onclick="restoreVendor(<?php echo $vendor['id']; ?>)" title="Restore Vendor" data-bs-toggle="tooltip">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <?php if ($vendor['status'] === 'suspended'): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="suspendVendor(<?php echo $vendor['id']; ?>, 'unsuspend')" title="Unsuspend Vendor" data-bs-toggle="tooltip">
                                                            <i class="bi bi-unlock"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="suspendVendor(<?php echo $vendor['id']; ?>, 'suspend')" title="Suspend Vendor" data-bs-toggle="tooltip">
                                                            <i class="bi bi-slash-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteVendor(<?php echo $vendor['id']; ?>)" title="Move to Trash" data-bs-toggle="tooltip">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php if ($vendor['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-success" onclick="approveVendor(<?php echo $vendor['id']; ?>, 'approve')" title="Approve Vendor" data-bs-toggle="tooltip">
                                                            <i class="bi bi-check2-circle"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" onclick="approveVendor(<?php echo $vendor['id']; ?>, 'disapprove')" title="Disapprove Vendor" data-bs-toggle="tooltip">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
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
    </div>

    <!-- Footer -->
    <?php include '../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store current filters in memory
        let currentView = '<?php echo $view; ?>';
        let currentSearch = '';
        let currentStatusFilter = '';

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // View filter buttons
            document.querySelectorAll('.view-filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentView = this.getAttribute('data-view');
                    loadVendorsAjax();
                });
            });

            // Search input
            const searchInput = document.getElementById('searchVendors');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    currentSearch = this.value;
                    loadVendorsAjax();
                });
            }

            // Status filter
            const statusFilter = document.getElementById('filterStatus');
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    currentStatusFilter = this.value;
                    loadVendorsAjax();
                });
            }

            // Reset filters button
            const resetBtn = document.getElementById('resetFilters');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    currentView = 'active';
                    currentSearch = '';
                    currentStatusFilter = '';
                    document.getElementById('searchVendors').value = '';
                    document.getElementById('filterStatus').value = '';
                    updateViewButtons();
                    loadVendorsAjax();
                });
            }

            // Export vendors
            document.getElementById('exportVendors').addEventListener('click', function() {
                window.location.href = 'ajax/vendor-export.php';
            });

            // Initialize tooltips
            initializeTooltips();
        });

        /**
         * Load vendors via AJAX
         */
        function loadVendorsAjax() {
            const loadingDiv = document.getElementById('filterLoading');
            if (loadingDiv) {
                loadingDiv.classList.remove('d-none');
            }

            const params = new URLSearchParams({
                view: currentView,
                search: currentSearch,
                status_filter: currentStatusFilter
            });

            fetch('ajax/get-vendors.php?' + params, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP Error: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                // Try to parse JSON
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const tbody = document.getElementById('vendorsTableBody');
                        if (tbody) {
                            tbody.innerHTML = data.html;
                            initializeTooltips();
                        }
                    } else {
                        showErrorMessage('Error loading vendors: ' + (data.message || 'Unknown error'));
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text.substring(0, 500));
                    showErrorMessage('Server error: Invalid response. Please check browser console.');
                }
                if (loadingDiv) {
                    loadingDiv.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showErrorMessage('Error loading vendors: ' + error.message);
                if (loadingDiv) {
                    loadingDiv.classList.add('d-none');
                }
            });
        }

        /**
         * Update view button states
         */
        function updateViewButtons() {
            document.querySelectorAll('.view-filter-btn').forEach(btn => {
                const view = btn.getAttribute('data-view');
                btn.classList.remove('btn-secondary', 'btn-dark', 'btn-outline-secondary', 'btn-outline-dark');
                
                if (view === currentView) {
                    btn.classList.add(view === 'trash' ? 'btn-dark' : 'btn-secondary');
                } else {
                    btn.classList.add(view === 'trash' ? 'btn-outline-dark' : 'btn-outline-secondary');
                }
            });
        }

        /**
         * Initialize Bootstrap tooltips
         */
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Delete vendor function - with AJAX reload
        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to move this vendor to trash? They can be restored within 30 days.')) {
                fetch('ajax/vendor-delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'vendor_id=' + vendorId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessMessage(data.message || 'Vendor moved to trash');
                        loadVendorsAjax();
                    } else {
                        showErrorMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorMessage('Error deleting vendor');
                });
            }
        }

        function restoreVendor(vendorId) {
            if (!confirm('Restore this vendor from trash?')) return;
            fetch('ajax/vendor-restore.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'vendor_id=' + vendorId
            }).then(r => r.json()).then(data => {
                if (data.success) { 
                    showSuccessMessage(data.message || 'Vendor restored'); 
                    loadVendorsAjax();
                }
                else showErrorMessage('Error: ' + data.message);
            }).catch(err => {
                console.error(err);
                showErrorMessage('Error restoring vendor');
            });
        }

        function suspendVendor(vendorId, action) {
            const confirmMsg = action === 'suspend' ? 'Suspend this vendor?' : 'Unsuspend this vendor?';
            if (!confirm(confirmMsg)) return;
            fetch('ajax/vendor-suspend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'vendor_id=' + vendorId + '&action=' + action
            }).then(r => r.json()).then(data => {
                if (data.success) { 
                    showSuccessMessage(data.message); 
                    loadVendorsAjax();
                }
                else showErrorMessage('Error: ' + data.message);
            }).catch(err => {
                console.error(err);
                showErrorMessage('Error updating vendor status');
            });
        }

        function approveVendor(vendorId, action) {
            if (!confirm(action === 'approve' ? 'Approve this vendor?' : 'Disapprove this vendor?')) return;
            fetch('ajax/vendor-approve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'vendor_id=' + vendorId + '&action=' + action
            }).then(r => r.json()).then(data => {
                if (data.success) { 
                    showSuccessMessage(data.message); 
                    loadVendorsAjax();
                }
                else showErrorMessage('Error: ' + data.message);
            }).catch(err => {
                console.error(err);
                showErrorMessage('Error updating vendor approval');
            });
        }

        /**
         * Show success message toast
         */
        function showSuccessMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const container = document.querySelector('.container-fluid');
            if (container) {
                container.insertBefore(alertDiv, container.firstChild);
                setTimeout(() => alertDiv.remove(), 5000);
            }
        }

        /**
         * Show error message toast
         */
        function showErrorMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const container = document.querySelector('.container-fluid');
            if (container) {
                container.insertBefore(alertDiv, container.firstChild);
                setTimeout(() => alertDiv.remove(), 5000);
            }
        }
    </script>
</body>
</html>
