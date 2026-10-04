<?php
// public/edit_customer.php
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $customer_type = $_POST['customer_type'];
    $status = $_POST['status'];
    
    // Check if phone or email already exists (excluding current customer)
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE (phone = ? OR (email != '' AND email = ?)) AND id != ?");
    $stmt->execute([$phone, $email, $customer_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "Phone number or email already exists.";
    } else {
        // Update customer
        $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, customer_type = ?, status = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $email, $phone, $address, $customer_type, $status, $customer_id])) {
            $_SESSION['success_message'] = "Customer updated successfully!";
            header("Location: customers.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update customer.";
        }
    }
}

// Set page title
$page_title = "Edit Customer - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Customer</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="customers.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Customers
        </a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="customer_type" class="form-label">Customer Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="customer_type" name="customer_type" required>
                            <option value="normal" <?php echo $customer['customer_type'] == 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="bulk" <?php echo $customer['customer_type'] == 'bulk' ? 'selected' : ''; ?>>Bulk Buyer</option>
                            <option value="event_planner" <?php echo $customer['customer_type'] == 'event_planner' ? 'selected' : ''; ?>>Event Planner</option>
                            <option value="wholesaler" <?php echo $customer['customer_type'] == 'wholesaler' ? 'selected' : ''; ?>>Wholesaler</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="banned" <?php echo $customer['status'] == 'banned' ? 'selected' : ''; ?>>Banned</option>
                </select>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>