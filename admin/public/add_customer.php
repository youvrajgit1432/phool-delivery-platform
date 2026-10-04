<?php
// public/add_customer.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $customer_type = $_POST['customer_type'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($name) || empty($phone)) {
        $_SESSION['error_message'] = "Name and phone are required fields.";
    } else {
        // Check if phone or email already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? OR (email != '' AND email = ?)");
        $stmt->execute([$phone, $email]);
        
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = "Phone number or email already exists.";
        } else {
            // Insert new customer
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, customer_type, status) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $phone, $address, $customer_type, $status])) {
                $_SESSION['success_message'] = "Customer added successfully!";
                header("Location: customers.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to add customer.";
            }
        }
    }
}

// Set page title
$page_title = "Add Customer - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Customer</h1>
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
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="customer_type" class="form-label">Customer Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="customer_type" name="customer_type" required>
                            <option value="normal" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] == 'normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="bulk" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] == 'bulk') ? 'selected' : ''; ?>>Bulk Buyer</option>
                            <option value="event_planner" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] == 'event_planner') ? 'selected' : ''; ?>>Event Planner</option>
                            <option value="wholesaler" <?php echo (isset($_POST['customer_type']) && $_POST['customer_type'] == 'wholesaler') ? 'selected' : ''; ?>>Wholesaler</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="banned" <?php echo (isset($_POST['status']) && $_POST['status'] == 'banned') ? 'selected' : ''; ?>>Banned</option>
                </select>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Add Customer</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>