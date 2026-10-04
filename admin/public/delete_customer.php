<?php
// public/delete_customer.php
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

// Process delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if customer has orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $order_count = $stmt->fetch()['order_count'];
    
    if ($order_count > 0) {
        $_SESSION['error_message'] = "Cannot delete customer with existing orders. Please deactivate instead.";
        header("Location: customers.php");
        exit;
    } else {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        
        if ($stmt->execute([$customer_id])) {
            $_SESSION['success_message'] = "Customer deleted successfully!";
            header("Location: customers.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to delete customer.";
            header("Location: customers.php");
            exit;
        }
    }
}

// Set page title
$page_title = "Delete Customer - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete Customer</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="customers.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Customers
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-danger">
            <h4 class="alert-heading">Warning!</h4>
            <p>You are about to delete the following customer. This action cannot be undone.</p>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Customer Information</h5>
                <table class="table table-borderless">
                    <tr>
                        <th width="30%">ID</th>
                        <td><?php echo $customer['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><?php echo htmlspecialchars($customer['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Customer Type</th>
                        <td>
                            <span class="badge bg-<?php 
                            switch($customer['customer_type']) {
                                case 'normal': echo 'secondary'; break;
                                case 'bulk': echo 'primary'; break;
                                case 'event_planner': echo 'info'; break;
                                case 'wholesaler': echo 'success'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $customer['customer_type'])); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="d-flex justify-content-between">
                <a href="customers.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>