<?php
// public/offers.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle offer actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_offer'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $discount_type = $_POST['discount_type'];
        $discount_value = floatval($_POST['discount_value']);
        $min_order_value = floatval($_POST['min_order_value']);
        $customer_type = $_POST['customer_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['status'];
        
        // Insert new offer
        $stmt = $pdo->prepare("INSERT INTO offers (name, description, discount_type, discount_value, min_order_value, customer_type, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $description, $discount_type, $discount_value, $min_order_value, $customer_type, $start_date, $end_date, $status])) {
            $_SESSION['success_message'] = "Offer added successfully!";
            header("Location: offers.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add offer.";
        }
    }
    
    if (isset($_POST['edit_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $discount_type = $_POST['discount_type'];
        $discount_value = floatval($_POST['discount_value']);
        $min_order_value = floatval($_POST['min_order_value']);
        $customer_type = $_POST['customer_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['status'];
        
        // Update offer
        $stmt = $pdo->prepare("UPDATE offers SET name = ?, description = ?, discount_type = ?, discount_value = ?, min_order_value = ?, customer_type = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $description, $discount_type, $discount_value, $min_order_value, $customer_type, $start_date, $end_date, $status, $offer_id])) {
            $_SESSION['success_message'] = "Offer updated successfully!";
            header("Location: offers.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update offer.";
        }
    }
    
    if (isset($_POST['delete_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        
        $stmt = $pdo->prepare("DELETE FROM offers WHERE id = ?");
        
        if ($stmt->execute([$offer_id])) {
            $_SESSION['success_message'] = "Offer deleted successfully!";
            header("Location: offers.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to delete offer.";
        }
    }
}

// Get offers with filters
$where_clause = "";
$params = [];
if (!empty($status_filter)) {
    $where_clause = "WHERE status = ?";
    $params[] = $status_filter;
}

$offers = $pdo->prepare("
    SELECT * FROM offers 
    $where_clause 
    ORDER BY created_at DESC
");
$offers->execute($params);
$offers = $offers->fetchAll();

// Get specific offer for editing
$edit_offer = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ?");
    $stmt->execute([$id]);
    $edit_offer = $stmt->fetch();
}

// Set page title
$page_title = "Offers & Coupons - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Offers & Coupons Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOfferModal">
            <i class="fas fa-plus me-1"></i> Add New Offer
        </button>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="statusFilter" onchange="window.location.href='offers.php?status='+this.value">
            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
        </select>
    </div>
    <div class="col-md-6">
        <input type="text" class="form-control" placeholder="Search offers..." id="searchInput">
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="offersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Customer Type</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $offer): ?>
                    <tr>
                        <td><?php echo $offer['id']; ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($offer['name']); ?></div>
                            <?php if (!empty($offer['description'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars(substr($offer['description'], 0, 50)) . (strlen($offer['description']) > 50 ? '...' : ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($offer['discount_type'] === 'percentage'): ?>
                            <?php echo $offer['discount_value']; ?>%
                            <?php else: ?>
                            Rs. <?php echo number_format($offer['discount_value'], 2); ?>
                            <?php endif; ?>
                            <br><small class="text-muted"><?php echo ucfirst($offer['discount_type']); ?></small>
                        </td>
                        <td>
                            <?php if ($offer['min_order_value'] > 0): ?>
                            Rs. <?php echo number_format($offer['min_order_value'], 2); ?>
                            <?php else: ?>
                            No minimum
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo ucfirst(str_replace('_', ' ', $offer['customer_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($offer['start_date'])); ?>
                            <?php if ($offer['end_date']): ?>
                            <br>to<br>
                            <?php echo date('M d, Y', strtotime($offer['end_date'])); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($offer['status']) {
                                case 'active': echo 'success'; break;
                                case 'inactive': echo 'secondary'; break;
                                case 'expired': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($offer['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($offer['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editOfferModal<?php echo $offer['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteOfferModal<?php echo $offer['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <!-- Edit Offer Modal -->
                            <div class="modal fade" id="editOfferModal<?php echo $offer['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Offer</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Offer Name</label>
                                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($offer['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($offer['description']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="discount_type" class="form-label">Discount Type</label>
                                                    <select class="form-select" id="discount_type" name="discount_type" required>
                                                        <option value="percentage" <?php echo $offer['discount_type'] == 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                                        <option value="fixed" <?php echo $offer['discount_type'] == 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                                        <option value="bulk" <?php echo $offer['discount_type'] == 'bulk' ? 'selected' : ''; ?>>Bulk Discount</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="discount_value" class="form-label">Discount Value</label>
                                                    <input type="number" step="0.01" class="form-control" id="discount_value" name="discount_value" value="<?php echo $offer['discount_value']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="min_order_value" class="form-label">Minimum Order Value (Rs.)</label>
                                                    <input type="number" step="0.01" class="form-control" id="min_order_value" name="min_order_value" value="<?php echo $offer['min_order_value']; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="customer_type" class="form-label">Customer Type</label>
                                                    <select class="form-select" id="customer_type" name="customer_type" required>
                                                        <option value="all" <?php echo $offer['customer_type'] == 'all' ? 'selected' : ''; ?>>All Customers</option>
                                                        <option value="normal" <?php echo $offer['customer_type'] == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                                        <option value="bulk" <?php echo $offer['customer_type'] == 'bulk' ? 'selected' : ''; ?>>Bulk Buyer</option>
                                                        <option value="event_planner" <?php echo $offer['customer_type'] == 'event_planner' ? 'selected' : ''; ?>>Event Planner</option>
                                                        <option value="wholesaler" <?php echo $offer['customer_type'] == 'wholesaler' ? 'selected' : ''; ?>>Wholesaler</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $offer['start_date']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="end_date" class="form-label">End Date (Optional)</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $offer['end_date']; ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status" required>
                                                        <option value="active" <?php echo $offer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $offer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="expired" <?php echo $offer['status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="edit_offer" class="btn btn-primary">Update Offer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Offer Modal -->
                            <div class="modal fade" id="deleteOfferModal<?php echo $offer['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete offer <strong><?php echo htmlspecialchars($offer['name']); ?></strong>?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="delete_offer" class="btn btn-danger">Delete Offer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Offer Modal -->
<div class="modal fade" id="addOfferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_name" class="form-label">Offer Name</label>
                        <input type="text" class="form-control" id="new_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_description" class="form-label">Description</label>
                        <textarea class="form-control" id="new_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="new_discount_type" class="form-label">Discount Type</label>
                        <select class="form-select" id="new_discount_type" name="discount_type" required>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                            <option value="bulk">Bulk Discount</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_discount_value" class="form-label">Discount Value</label>
                        <input type="number" step="0.01" class="form-control" id="new_discount_value" name="discount_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_min_order_value" class="form-label">Minimum Order Value (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="new_min_order_value" name="min_order_value" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="new_customer_type" class="form-label">Customer Type</label>
                        <select class="form-select" id="new_customer_type" name="customer_type" required>
                            <option value="all">All Customers</option>
                            <option value="normal">Normal</option>
                            <option value="bulk">Bulk Buyer</option>
                            <option value="event_planner">Event Planner</option>
                            <option value="wholesaler">Wholesaler</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="new_start_date" name="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_end_date" class="form-label">End Date (Optional)</label>
                        <input type="date" class="form-control" id="new_end_date" name="end_date">
                    </div>
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Status</label>
                        <select class="form-select" id="new_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_offer" class="btn btn-primary">Add Offer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>