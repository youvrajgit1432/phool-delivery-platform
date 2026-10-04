<?php
/**
 * Rider Management Dashboard
 * Admin panel for managing all delivery riders, their documents, assignments, and performance
 */

require_once '../bootstrap/app.php';
require_once '../app/middleware/AdminMiddleware.php';

// Check admin authentication
requireAuth();

$page_title = 'Rider Management';
$current_page = 'riders';

// Get database connection
$db = getDBConnection();

// Determine which view to show
$view = $_GET['view'] ?? 'active';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at_desc';

// Build WHERE clause
$where_conditions = [];
$params = [];

switch ($view) {
    case 'trash':
        $where_conditions[] = "r.deleted_at IS NOT NULL";
        break;
    case 'pending':
        $where_conditions[] = "r.status = 'pending' AND r.deleted_at IS NULL";
        break;
    case 'suspended':
        $where_conditions[] = "r.status = 'suspended' AND r.deleted_at IS NULL";
        break;
    case 'inactive':
        $where_conditions[] = "r.status = 'inactive' AND r.deleted_at IS NULL";
        break;
    case 'active':
    default:
        $where_conditions[] = "r.status = 'active' AND r.deleted_at IS NULL";
}

// Status filter (if not trash view)
if (!empty($status_filter) && $view !== 'trash') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(r.first_name LIKE ? OR r.last_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Build WHERE clause string
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Build ORDER BY clause
$order_clause = "ORDER BY ";
switch ($sort_by) {
    case 'created_at_asc':
        $order_clause .= "r.created_at ASC";
        break;
    case 'name_asc':
        $order_clause .= "r.first_name ASC, r.last_name ASC";
        break;
    case 'name_desc':
        $order_clause .= "r.first_name DESC, r.last_name DESC";
        break;
    case 'rating_desc':
        $order_clause .= "r.average_rating DESC";
        break;
    case 'deliveries_desc':
        $order_clause .= "r.total_deliveries DESC";
        break;
    default:
        $order_clause .= "r.created_at DESC";
}

// Fetch riders
$sql = "SELECT 
            r.id, r.first_name, r.last_name, r.email, r.phone, r.rider_type,
            r.vehicle_type, r.vehicle_number, r.status, r.is_available,
            r.average_rating, r.total_deliveries, r.total_reviews,
            r.total_earnings, r.total_penalties, r.cancellation_rate,
            r.on_time_delivery_rate, r.email_verified, r.documents_verified,
            r.bank_verified, r.last_login, r.created_at, r.updated_at, r.deleted_at,
            (SELECT COUNT(*) FROM rider_orders WHERE rider_id = r.id AND delivery_status = 'assigned') as pending_orders,
            (SELECT COUNT(*) FROM rider_orders WHERE rider_id = r.id AND delivery_status = 'delivered') as delivered_orders,
            (SELECT COALESCE(SUM(amount), 0) FROM rider_penalties WHERE rider_id = r.id AND status = 'pending') as pending_penalties
        FROM riders r
        " . $where_clause . "
        " . $order_clause;

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $riders = [];
    $error = "Error loading riders: " . $e->getMessage();
}

// Get summary stats
$stats = [];
try {
    $stats_sql = "SELECT 
        (SELECT COUNT(*) FROM riders WHERE status = 'active' AND deleted_at IS NULL) as active_riders,
        (SELECT COUNT(*) FROM riders WHERE status = 'pending' AND deleted_at IS NULL) as pending_riders,
        (SELECT COUNT(*) FROM riders WHERE status = 'suspended' AND deleted_at IS NULL) as suspended_riders,
        (SELECT COUNT(*) FROM riders WHERE deleted_at IS NOT NULL) as deleted_riders,
        (SELECT COUNT(*) FROM rider_orders WHERE delivery_status IN ('assigned', 'accepted', 'picked_up', 'on_the_way')) as active_deliveries,
        (SELECT COALESCE(SUM(total_deliveries), 0) FROM riders WHERE status = 'active' AND deleted_at IS NULL) as total_deliveries,
        (SELECT COALESCE(AVG(average_rating), 0) FROM riders WHERE status = 'active' AND deleted_at IS NULL) as avg_rating";
    
    $stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [];
}

// Include header
include '../app/views/layouts/header.php';
?>

<style>
/* Global Responsive Table CSS - Desktop to Mobile */
.responsive-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.responsive-table {
    width: 100%;
    min-width: 100%;
    margin-bottom: 0;
}

.responsive-table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 1rem 0.75rem;
    white-space: nowrap;
    text-align: left;
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

/* Mobile responsive adjustments */
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

/* Tablet responsive adjustments */
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

/* Desktop - Full width tables */
@media (min-width: 1200px) {
    .responsive-table {
        width: 100%;
    }
    
    .responsive-table thead th,
    .responsive-table tbody td {
        padding: 1rem 0.75rem;
    }
}

/* Hide columns based on screen size */
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

/* Show all columns on desktop */
@media (min-width: 1200px) {
    .d-md-table-cell,
    .d-lg-table-cell,
    .d-xl-table-cell {
        display: table-cell !important;
    }
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $view === 'trash' ? 'Trash - ' : ''; ?>Rider Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary view-filter-btn <?php echo $view === 'active' ? 'active' : ''; ?>" data-view="active">
                <i class="fas fa-user-check me-1"></i> Active
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning view-filter-btn <?php echo $view === 'pending' ? 'active' : ''; ?>" data-view="pending">
                <i class="fas fa-hourglass-start me-1"></i> Pending
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger view-filter-btn <?php echo $view === 'suspended' ? 'active' : ''; ?>" data-view="suspended">
                <i class="fas fa-ban me-1"></i> Suspended
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary view-filter-btn <?php echo $view === 'inactive' ? 'active' : ''; ?>" data-view="inactive">
                <i class="fas fa-user-slash me-1"></i> Inactive
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger view-filter-btn <?php echo $view === 'trash' ? 'active' : ''; ?>" data-view="trash">
                <i class="fas fa-trash me-1"></i> Trash
            </button>
        </div>
        <?php if ($view === 'trash'): ?>
            <div class="btn-group me-2">
                <button type="button" id="restoreSelectedBtn" class="btn btn-sm btn-outline-success d-none" onclick="restoreSelectedRiders()">
                    <i class="fas fa-undo me-1"></i> Restore (<span id="selectedCountRestore">0</span>)
                </button>
                <button type="button" id="permanentDeleteBtn" class="btn btn-sm btn-danger d-none" onclick="permanentDeleteSelectedRiders()">
                    <i class="fas fa-trash-alt me-1"></i> Permanently Delete (<span id="selectedCountPermanent">0</span>)
                </button>
            </div>
        <?php else: ?>
            <div class="btn-group me-2">
                <button type="button" id="assignOrdersBtn" class="btn btn-sm btn-info d-none" data-bs-toggle="modal" data-bs-target="#assignOrdersModal">
                    <i class="fas fa-tasks me-1"></i> Assign Orders (<span id="selectedCountAssign">0</span>)
                </button>
                <button type="button" id="suspendSelectedBtn" class="btn btn-sm btn-warning d-none" onclick="bulkUpdateRiderStatus('suspended')">
                    <i class="fas fa-ban me-1"></i> Suspend (<span id="selectedCount">0</span>)
                </button>
                <button type="button" id="activateSelectedBtn" class="btn btn-sm btn-success d-none" onclick="bulkUpdateRiderStatus('active')">
                    <i class="fas fa-check me-1"></i> Activate (<span id="selectedCountActivate">0</span>)
                </button>
                <button type="button" id="deleteSelectedBtn" class="btn btn-sm btn-danger d-none" onclick="deleteSelectedRiders()">
                    <i class="fas fa-trash me-1"></i> Delete (<span id="selectedCountDelete">0</span>)
                </button>
            </div>
        <?php endif; ?>
        <a href="riders/add.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add Rider
        </a>
    </div>
</div>

<!-- Summary Stats -->
<div class="row mb-3">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Active Riders</h6>
                <h3 class="text-success"><?php echo $stats['active_riders'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Pending Review</h6>
                <h3 class="text-warning"><?php echo $stats['pending_riders'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Suspended</h6>
                <h3 class="text-danger"><?php echo $stats['suspended_riders'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Active Deliveries</h6>
                <h3 class="text-info"><?php echo $stats['active_deliveries'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Avg Rating</h6>
                <h3 class="text-primary"><?php echo number_format($stats['avg_rating'] ?? 0, 2); ?> ⭐</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Deliveries</h6>
                <h3><?php echo $stats['total_deliveries'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Loading Indicator -->
<div id="filterLoading" class="alert alert-info d-none" role="alert">
    <i class="bi bi-hourglass-split"></i> Loading riders...
</div>

<!-- Search and Filter Card -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Search & Filter</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="searchRiders" class="form-label">Search by Name, Email, or Phone</label>
                <input type="text" class="form-control" id="searchRiders" 
                       placeholder="Enter name, email, or phone" autocomplete="off">
            </div>
            
            <div class="col-md-3">
                <label for="sortBy" class="form-label">Sort By</label>
                <select class="form-select" id="sortBy">
                    <option value="created_at_desc">Newest First</option>
                    <option value="created_at_asc">Oldest First</option>
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
                    <option value="rating_desc">Highest Rating</option>
                    <option value="deliveries_desc">Most Deliveries</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="resetFilters">
                        <i class="fas fa-redo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riders Table -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Riders List</h6>
    </div>
    <div class="table-responsive responsive-table-wrapper">
        <table class="table table-striped table-hover mb-0 responsive-table">
            <thead class="table-light">
                <tr>
                    <th style="width: 30px;">
                        <input type="checkbox" id="selectAllCheckbox" class="form-check-input" title="Select all riders">
                    </th>
                    <th>Name</th>
                    <th class="d-none d-md-table-cell">Contact</th>
                    <th class="d-none d-lg-table-cell">Type</th>
                    <th class="d-none d-lg-table-cell">Vehicle</th>
                    <th>Status</th>
                    <th class="d-none d-xl-table-cell">Rating</th>
                    <th class="d-none d-xl-table-cell">Deliveries</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="ridersTableBody">
                <?php if (!empty($riders)): ?>
                    <?php foreach ($riders as $rider): ?>
                    <tr>
                        <td class="d-none d-lg-table-cell">
                            <input type="checkbox" class="form-check-input rider-checkbox" 
                                   value="<?php echo $rider['id']; ?>" title="Select this rider">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']); ?></strong>
                            <div class="d-md-none small text-muted mt-1">ID: <?php echo $rider['id']; ?></div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <small class="text-muted">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($rider['email']); ?><br>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($rider['phone']); ?>
                            </small>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span class="badge bg-<?php 
                                echo $rider['rider_type'] === 'in_house' ? 'primary' : 
                                     ($rider['rider_type'] === 'gig' ? 'info' : 'success');
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $rider['rider_type'])); ?>
                            </span>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <small><?php echo htmlspecialchars($rider['vehicle_type'] . ' - ' . $rider['vehicle_number']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $rider['status'] === 'active' ? 'success' : 
                                     ($rider['status'] === 'pending' ? 'warning' : 
                                      ($rider['status'] === 'suspended' ? 'danger' : 'secondary'));
                            ?>">
                                <?php echo ucfirst($rider['status']); ?>
                                <?php if ($rider['is_available']): ?>
                                    <i class="fas fa-circle text-success" style="font-size: 0.5em;"></i>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <div class="text-center">
                                <strong><?php echo number_format($rider['average_rating'], 1); ?></strong> ⭐
                                <br><small class="text-muted"><?php echo $rider['total_reviews']; ?> reviews</small>
                            </div>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <div class="text-center">
                                <strong><?php echo $rider['total_deliveries']; ?></strong>
                                <br><small class="text-muted">Completed</small>
                            </div>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <div class="text-center">
                                <strong>Rs. <?php echo number_format($rider['total_earnings'], 2); ?></strong>
                                <br><small class="text-danger">Penalties: Rs. <?php echo number_format($rider['total_penalties'], 2); ?></small>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <small>
                                <?php if ($rider['email_verified']): ?>
                                    <i class="fas fa-check-circle text-success"></i> Email<br>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i> Email<br>
                                <?php endif; ?>
                                <?php if ($rider['documents_verified']): ?>
                                    <i class="fas fa-check-circle text-success"></i> Docs<br>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i> Docs<br>
                                <?php endif; ?>
                                <?php if ($rider['bank_verified']): ?>
                                    <i class="fas fa-check-circle text-success"></i> Bank
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i> Bank
                                <?php endif; ?>
                            </small>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <span class="badge bg-info"><?php echo $rider['pending_orders']; ?></span>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <?php if ($rider['pending_penalties'] > 0): ?>
                                <span class="badge bg-danger">Rs. <?php echo number_format($rider['pending_penalties'], 0); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <small class="text-muted">
                                <?php echo $rider['last_login'] ? date('M d, Y H:i', strtotime($rider['last_login'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm flex-wrap" role="group">
                                <a href="riders/view.php?id=<?php echo $rider['id']; ?>" class="btn btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="riders/edit.php?id=<?php echo $rider['id']; ?>" class="btn btn-primary" title="Edit Rider">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($view === 'trash'): ?>
                                    <button type="button" class="btn btn-success" onclick="restoreSingleRider(<?php echo $rider['id']; ?>)" title="Restore">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="permanentDeleteSingleRider(<?php echo $rider['id']; ?>)" title="Permanently Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="riders/orders.php?rider_id=<?php echo $rider['id']; ?>" class="btn btn-warning" title="Assign Orders">
                                        <i class="fas fa-truck"></i>
                                    </a>
                                    <?php if ($rider['status'] !== 'suspended'): ?>
                                        <button type="button" class="btn btn-outline-warning" onclick="updateRiderStatus(<?php echo $rider['id']; ?>, 'suspended')" title="Suspend">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteRider(<?php echo $rider['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="alert alert-info mb-0">No riders found</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Store current filters in memory
let currentView = '<?php echo $view; ?>';
let currentSearch = '';
let currentStatusFilter = '';
let currentSortBy = 'created_at_desc';

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // View filter buttons
    document.querySelectorAll('.view-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentView = this.getAttribute('data-view');
            loadRidersAjax();
        });
    });

    // Search input
    const searchInput = document.getElementById('searchRiders');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            currentSearch = this.value;
            loadRidersAjax();
        });
    }

    // Sort dropdown
    const sortDropdown = document.getElementById('sortBy');
    if (sortDropdown) {
        sortDropdown.addEventListener('change', function() {
            currentSortBy = this.value;
            loadRidersAjax();
        });
    }

    // Reset filters button
    const resetBtn = document.getElementById('resetFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            currentView = 'active';
            currentSearch = '';
            currentStatusFilter = '';
            currentSortBy = 'created_at_desc';
            document.getElementById('searchRiders').value = '';
            document.getElementById('sortBy').value = 'created_at_desc';
            updateViewButtons();
            loadRidersAjax();
        });
    }

    // Initialize tooltips
    initializeTooltips();
    
    // Setup multi-select
    setupMultiSelect();
});

/**
 * Load riders via AJAX
 */
function loadRidersAjax() {
    const loadingDiv = document.getElementById('filterLoading');
    if (loadingDiv) {
        loadingDiv.classList.remove('d-none');
    }

    const params = new URLSearchParams({
        view: currentView,
        search: currentSearch,
        status_filter: currentStatusFilter,
        sort_by: currentSortBy
    });

    fetch('ajax/get-riders.php?' + params, {
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
                const tbody = document.getElementById('ridersTableBody');
                if (tbody) {
                    tbody.innerHTML = data.html;
                    initializeTooltips();
                    setupMultiSelect();
                }
            } else {
                showErrorMessage('Error loading riders: ' + (data.message || 'Unknown error'));
            }
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text.substring(0, 500));
            showErrorMessage('Server error: Invalid response');
        }
        if (loadingDiv) {
            loadingDiv.classList.add('d-none');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        showErrorMessage('Error loading riders: ' + error.message);
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
        if (view === currentView) {
            btn.classList.add('active');
            btn.classList.remove('btn-outline-secondary', 'btn-outline-warning', 'btn-outline-danger');
        } else {
            btn.classList.remove('active');
            if (view === 'trash') {
                btn.classList.add('btn-outline-danger');
            } else if (view === 'pending') {
                btn.classList.add('btn-outline-warning');
            } else {
                btn.classList.add('btn-outline-secondary');
            }
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
    const container = document.querySelector('.card:first-of-type');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
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
    const container = document.querySelector('.card:first-of-type');
    if (container && container.parentNode) {
        container.parentNode.insertBefore(alertDiv, container);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

// Delete rider - with AJAX reload
function deleteRider(riderId) {
    if (!confirm('Are you sure you want to delete this rider?')) return;
    
    fetch('ajax/bulk-delete-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: [riderId] })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message || 'Rider deleted');
            loadRidersAjax();
        } else {
            showErrorMessage('Error: ' + (data.message || 'Failed to delete'));
        }
    })
    .catch(err => {
        console.error(err);
        showErrorMessage('Error deleting rider');
    });
}

function restoreSingleRider(riderId) {
    if (!confirm('Restore this rider?')) return;
    fetch('ajax/restore-riders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ rider_ids: [riderId] })
    }).then(r => r.json()).then(data => {
        if (data.success) { 
            showSuccessMessage(data.message || 'Rider restored'); 
            loadRidersAjax();
        }
        else showErrorMessage('Error: ' + (data.message || 'Failed'));
    }).catch(err => {
        console.error(err);
        showErrorMessage('Error restoring rider');
    });
}

function updateRiderStatus(riderId, newStatus) {
    const confirmMsg = newStatus === 'suspended' ? 'Suspend this rider?' : 'Activate this rider?';
    if (!confirm(confirmMsg)) return;
    
    fetch('ajax/update-rider-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_id: riderId, new_status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message);
            loadRidersAjax();
        } else {
            showErrorMessage('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(err => {
        console.error(err);
        showErrorMessage('Error updating rider status');
    });
}

// Multi-select functionality
function setupMultiSelect() {
    var selectAllCheckbox = document.getElementById('selectAllCheckbox');
    var riderCheckboxes = document.querySelectorAll('.rider-checkbox');
    var isTrashView = currentView === 'trash';
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            riderCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateButtons();
        });
    }

    riderCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateButtons();
        });
    });

    function updateSelectAllCheckbox() {
        var checkedCount = document.querySelectorAll('.rider-checkbox:checked').length;
        var totalCount = riderCheckboxes.length;
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = (checkedCount === totalCount && totalCount > 0);
            selectAllCheckbox.indeterminate = (checkedCount > 0 && checkedCount < totalCount);
        }
    }

    function updateButtons() {
        var checkedCount = document.querySelectorAll('.rider-checkbox:checked').length;
        
        if (isTrashView) {
            var restoreBtn = document.getElementById('restoreSelectedBtn');
            var permanentDeleteBtn = document.getElementById('permanentDeleteBtn');
            
            if (restoreBtn) {
                restoreBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountRestore').textContent = checkedCount;
            }
            if (permanentDeleteBtn) {
                permanentDeleteBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountPermanent').textContent = checkedCount;
            }
        } else {
            var assignBtn = document.getElementById('assignOrdersBtn');
            var suspendBtn = document.getElementById('suspendSelectedBtn');
            var activateBtn = document.getElementById('activateSelectedBtn');
            var deleteBtn = document.getElementById('deleteSelectedBtn');
            
            if (assignBtn) {
                assignBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountAssign').textContent = checkedCount;
            }
            if (suspendBtn) {
                suspendBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCount').textContent = checkedCount;
            }
            if (activateBtn) {
                activateBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountActivate').textContent = checkedCount;
            }
            if (deleteBtn) {
                deleteBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountDelete').textContent = checkedCount;
            }
        }
    }
}

function deleteSelectedRiders() {
    function updateSelectAllCheckbox() {
        var checkedCount = document.querySelectorAll('.rider-checkbox:checked').length;
        var totalCount = riderCheckboxes.length;
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
    }

    function updateButtons() {
        var checkedCount = document.querySelectorAll('.rider-checkbox:checked').length;
        
        if (isTrashView) {
            var restoreBtn = document.getElementById('restoreSelectedBtn');
            var permanentDeleteBtn = document.getElementById('permanentDeleteBtn');
            
            if (restoreBtn) {
                restoreBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountRestore').textContent = checkedCount;
            }
            if (permanentDeleteBtn) {
                permanentDeleteBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountPermanent').textContent = checkedCount;
            }
        } else {
            var assignBtn = document.getElementById('assignOrdersBtn');
            var suspendBtn = document.getElementById('suspendSelectedBtn');
            var activateBtn = document.getElementById('activateSelectedBtn');
            var deleteBtn = document.getElementById('deleteSelectedBtn');
            
            if (assignBtn) {
                assignBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountAssign').textContent = checkedCount;
                // Update modal when button is clicked
                assignBtn.addEventListener('click', function() {
                    document.getElementById('selectedRidersCount').textContent = checkedCount;
                    loadUnassignedOrders();
                });
            }
            if (suspendBtn) {
                suspendBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCount').textContent = checkedCount;
            }
            if (activateBtn) {
                activateBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountActivate').textContent = checkedCount;
            }
            if (deleteBtn) {
                deleteBtn.classList.toggle('d-none', checkedCount === 0);
                document.getElementById('selectedCountDelete').textContent = checkedCount;
            }
        }
    }
}

function deleteSelectedRiders() {
    var selectedCheckboxes = document.querySelectorAll('.rider-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one rider to delete');
        return;
    }

    if (!confirm('Are you sure you want to delete ' + selectedCheckboxes.length + ' rider(s)? This action cannot be undone.')) {
        return;
    }

    var riderIds = [];
    selectedCheckboxes.forEach(function(checkbox) {
        riderIds.push(parseInt(checkbox.value));
    });

    var deleteBtn = document.getElementById('deleteSelectedBtn');
    deleteBtn.disabled = true;
    var originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deleting...';

    fetch('ajax/bulk-delete-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: riderIds })
    })
    .then(function(response) { return response.text(); })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.success) {
                alert('Successfully deleted ' + data.deleted_count + ' rider(s)');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            }
        } catch (e) {
            console.error('Error:', e);
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            alert('Server error. Check console.');
        }
    })
    .catch(function(error) {
        console.error('Fetch Error:', error);
        alert('An error occurred: ' + error.message);
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
    });
}

function bulkUpdateRiderStatus(newStatus) {
    var selectedCheckboxes = document.querySelectorAll('.rider-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one rider');
        return;
    }

    var riderIds = [];
    selectedCheckboxes.forEach(function(checkbox) {
        riderIds.push(parseInt(checkbox.value));
    });

    var statusText = newStatus === 'suspended' ? 'suspended' : 'activated';
    if (!confirm('Are you sure you want to ' + statusText + ' ' + riderIds.length + ' rider(s)?')) {
        return;
    }

    var buttonId = newStatus === 'suspended' ? 'suspendSelectedBtn' : 'activateSelectedBtn';
    var button = document.getElementById(buttonId);
    button.disabled = true;
    var originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';

    fetch('ajax/bulk-update-rider-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: riderIds, new_status: newStatus })
    })
    .then(function(response) { return response.text(); })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.success) {
                alert('Successfully updated ' + data.updated_count + ' rider(s)');
                location.reload();
            } else {
                alert('Error: ' + data.message);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        } catch (e) {
            console.error('Error:', e);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    });
}

function updateRiderStatus(riderId, newStatus) {
    if (!confirm('Are you sure you want to ' + newStatus + ' this rider?')) return;
    
    fetch('ajax/update-rider-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_id: riderId, new_status: newStatus })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Rider status updated successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function(err) {
        console.error('Error:', err);
        alert('Request failed');
    });
}

function deleteRider(riderId) {
    if (!confirm('Are you sure you want to delete this rider? This action cannot be undone.')) return;
    
    fetch('ajax/bulk-delete-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: [riderId] })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Rider deleted successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function(err) {
        console.error('Error:', err);
        alert('Request failed');
    });
}

function restoreSelectedRiders() {
    var selectedCheckboxes = document.querySelectorAll('.rider-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one rider to restore');
        return;
    }

    if (!confirm('Are you sure you want to restore ' + selectedCheckboxes.length + ' rider(s)?')) return;

    var riderIds = [];
    selectedCheckboxes.forEach(function(checkbox) {
        riderIds.push(parseInt(checkbox.value));
    });

    var button = document.getElementById('restoreSelectedBtn');
    button.disabled = true;
    var originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Restoring...';

    fetch('ajax/restore-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: riderIds })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Restored ' + data.restored_count + ' rider(s)');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(function(err) {
        button.disabled = false;
        button.innerHTML = originalText;
        alert('Request failed: ' + err.message);
    });
}

function permanentDeleteSelectedRiders() {
    var selectedCheckboxes = document.querySelectorAll('.rider-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one rider');
        return;
    }

    if (!confirm('WARNING: Permanently delete ' + selectedCheckboxes.length + ' rider(s)? This cannot be undone!')) return;

    var riderIds = [];
    selectedCheckboxes.forEach(function(checkbox) {
        riderIds.push(parseInt(checkbox.value));
    });

    var button = document.getElementById('permanentDeleteBtn');
    button.disabled = true;
    var originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deleting...';

    fetch('ajax/permanent-delete-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_ids: riderIds })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Permanently deleted ' + data.deleted_count + ' rider(s)');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(function(err) {
        button.disabled = false;
        button.innerHTML = originalText;
        alert('Request failed: ' + err.message);
    });
}

function restoreSingleRider(riderId) {
    if (!confirm('Restore this rider?')) return;
    fetch('ajax/restore-riders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ rider_ids: [riderId] })
    }).then(r => r.json()).then(function(data){
        if (data.success) { alert('Rider restored'); location.reload(); }
        else alert('Error: ' + (data.message || 'Failed'));
    }).catch(function(err){ console.error(err); alert('Request failed'); });
}

function permanentDeleteSingleRider(riderId) {
    if (!confirm('Permanently delete this rider? This cannot be undone!')) return;
    fetch('ajax/permanent-delete-riders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ rider_ids: [riderId] })
    }).then(r => r.json()).then(function(data){
        if (data.success) { alert('Rider deleted'); location.reload(); }
        else alert('Error: ' + (data.message || 'Failed'));
    }).catch(function(err){ console.error(err); alert('Request failed'); });
}

/**
 * Load unassigned orders for assignment modal
 */
function loadUnassignedOrders() {
    var modal = document.getElementById('assignOrdersModal');
    var ordersList = document.getElementById('unassignedOrdersList');
    var loadingDiv = document.getElementById('ordersLoading');
    
    // Show loading state
    loadingDiv.style.display = 'block';
    ordersList.innerHTML = '';
    
    fetch('ajax/get-unassigned-orders.php', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        loadingDiv.style.display = 'none';
        
        if (!data.success) {
            ordersList.innerHTML = '<div class="alert alert-danger">Error loading orders: ' + data.message + '</div>';
            return;
        }
        
        if (data.orders.length === 0) {
            ordersList.innerHTML = '<div class="alert alert-info">No unassigned orders available</div>';
            return;
        }
        
        // Build orders list
        var html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th><input type="checkbox" id="selectAllOrders"></th><th>Order #</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        
        data.orders.forEach(function(order) {
            html += '<tr>';
            html += '<td><input type="checkbox" class="order-checkbox" value="' + order.id + '"></td>';
            html += '<td><strong>' + order.order_number + '</strong></td>';
            html += '<td>' + order.customer_name + '</td>';
            html += '<td>' + order.item_count + '</td>';
            html += '<td>Rs. ' + parseFloat(order.total_amount).toFixed(2) + '</td>';
            html += '<td><span class="badge bg-info">' + order.status + '</span></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        ordersList.innerHTML = html;
        
        // Setup order checkboxes
        setupOrderCheckboxes();
    })
    .catch(function(err) {
        loadingDiv.style.display = 'none';
        ordersList.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}

/**
 * Setup order checkbox selection
 */
function setupOrderCheckboxes() {
    var selectAllOrders = document.getElementById('selectAllOrders');
    var orderCheckboxes = document.querySelectorAll('.order-checkbox');
    
    if (selectAllOrders) {
        selectAllOrders.addEventListener('change', function() {
            orderCheckboxes.forEach(function(cb) {
                cb.checked = selectAllOrders.checked;
            });
            updateAssignButtonState();
        });
    }
    
    orderCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            updateAssignButtonState();
        });
    });
}

/**
 * Update assign button state in modal
 */
function updateAssignButtonState() {
    var selectedOrders = document.querySelectorAll('.order-checkbox:checked').length;
    var assignBtn = document.getElementById('confirmAssignOrdersBtn');
    var selectedCount = document.getElementById('selectedOrdersCount');
    
    selectedCount.textContent = selectedOrders;
    assignBtn.disabled = selectedOrders === 0;
}

/**
 * Assign selected orders to selected riders
 */
function assignOrdersToSelectedRiders() {
    var selectedRiders = Array.from(document.querySelectorAll('.rider-checkbox:checked')).map(function(cb) {
        return parseInt(cb.value);
    });
    
    var selectedOrders = Array.from(document.querySelectorAll('.order-checkbox:checked')).map(function(cb) {
        return parseInt(cb.value);
    });
    
    if (selectedRiders.length === 0) {
        alert('Please select at least one rider');
        return;
    }
    
    if (selectedOrders.length === 0) {
        alert('Please select at least one order');
        return;
    }
    
    var confirmMsg = 'Are you sure you want to assign ' + selectedOrders.length + ' order(s) to ' + selectedRiders.length + ' rider(s)?';
    if (!confirm(confirmMsg)) {
        return;
    }
    
    var assignBtn = document.getElementById('confirmAssignOrdersBtn');
    assignBtn.disabled = true;
    var originalText = assignBtn.innerHTML;
    assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Assigning...';
    
    fetch('ajax/bulk-assign-orders-to-riders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            rider_ids: selectedRiders,
            order_ids: selectedOrders
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        assignBtn.disabled = false;
        assignBtn.innerHTML = originalText;
        
        if (data.success) {
            alert('Orders assigned successfully! ' + data.assigned_count + ' assignment(s) completed.');
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('assignOrdersModal'));
            if (modal) modal.hide();
            // Reload page
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to assign orders'));
        }
    })
    .catch(function(err) {
        assignBtn.disabled = false;
        assignBtn.innerHTML = originalText;
        console.error('Error:', err);
        alert('Request failed: ' + err.message);
    });
}
</script>

<!-- Assign Orders Modal -->
<div class="modal fade" id="assignOrdersModal" tabindex="-1" aria-labelledby="assignOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignOrdersModalLabel">
                    <i class="fas fa-tasks me-2"></i>Assign Orders to Selected Riders
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Selected Riders Info -->
                <div class="alert alert-info" id="selectedRidersInfo" role="alert">
                    <strong>Selected Riders: <span id="selectedRidersCount">0</span></strong>
                </div>
                
                <!-- Orders Loading -->
                <div id="ordersLoading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading orders...</span>
                    </div>
                    <p class="mt-2">Loading available orders...</p>
                </div>
                
                <!-- Orders List -->
                <div id="unassignedOrdersList"></div>
            </div>
            <div class="modal-footer">
                <p class="text-muted me-auto"><small>Selected: <span id="selectedOrdersCount">0</span> order(s)</small></p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmAssignOrdersBtn" class="btn btn-primary" onclick="assignOrdersToSelectedRiders()" disabled>
                    <i class="fas fa-check me-1"></i> Assign Orders
                </button>
            </div>
        </div>
    </div>
</div>

<?php
include '../app/views/layouts/footer.php';
?>
