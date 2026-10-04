<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get delivery ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid delivery ID.";
    header("Location: ../sales.php");
    exit;
}

$delivery_id = intval($_GET['id']);

// Get delivery details
$delivery = $pdo->prepare("
    SELECT fd.*, b.buyer_name, b.buyer_id 
    FROM flower_deliveries fd 
    JOIN buyers b ON fd.buyer_id = b.buyer_id 
    WHERE fd.delivery_id = ?
");
$delivery->execute([$delivery_id]);
$delivery = $delivery->fetch();

if (!$delivery) {
    $_SESSION['error_message'] = "Delivery record not found.";
    header("Location: ../sales.php");
    exit;
}

// Check if delivery has any payments
$payments_count = $pdo->prepare("SELECT COUNT(*) as count FROM buyer_payments WHERE delivery_id = ?");
$payments_count->execute([$delivery_id]);
$payments_count = $payments_count->fetch();

// Process deletion if confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete related payments first
        $pdo->prepare("DELETE FROM buyer_payments WHERE delivery_id = ?")->execute([$delivery_id]);
        
        // Delete delivery record
        $stmt = $pdo->prepare("DELETE FROM flower_deliveries WHERE delivery_id = ?");
        $stmt->execute([$delivery_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Delivery record deleted successfully!";
        header("Location: view_buyer.php?id=" . $delivery['buyer_id']);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to delete delivery record: " . $e->getMessage();
        header("Location: view_buyer.php?id=" . $delivery['buyer_id']);
        exit;
    }
}

// Set page title
$page_title = "Delete Delivery - " . htmlspecialchars($delivery['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete Delivery Record</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view_buyer.php?id=<?php echo $delivery['buyer_id']; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Buyer
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
        <div class="alert alert-warning">
            <h4 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2"></i>Warning!
            </h4>
            <p class="mb-0">You are about to delete a delivery record. This action cannot be undone.</p>
        </div>

        <!-- Delivery Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Delivery Details</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Buyer:</th>
                        <td><?php echo htmlspecialchars($delivery['buyer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Flower Type:</th>
                        <td><?php echo htmlspecialchars($delivery['flower_type']); ?></td>
                    </tr>
                    <tr>
                        <th>Quantity:</th>
                        <td><?php echo number_format($delivery['quantity_kg'], 2); ?> kg</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5>Financial Details</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Rate per KG:</th>
                        <td>Rs. <?php echo number_format($delivery['rate_per_kg'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Total Amount:</th>
                        <td>Rs. <?php echo number_format($delivery['total_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Payment Received:</th>
                        <td>Rs. <?php echo number_format($delivery['payment_received'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Pending Amount:</th>
                        <td>Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($payments_count['count'] > 0): ?>
        <div class="alert alert-danger">
            <h6 class="alert-heading">
                <i class="fas fa-exclamation-circle me-2"></i>Important Notice
            </h6>
            <p class="mb-0">This delivery has <strong><?php echo $payments_count['count']; ?> payment records</strong>. Deleting this delivery will also delete all associated payment records.</p>
        </div>
        <?php endif; ?>

        <!-- Confirmation Form -->
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="confirmation" class="form-label">
                            Type "DELETE" to confirm deletion:
                        </label>
                        <input type="text" class="form-control" id="confirmation" name="confirmation" 
                               placeholder="Type DELETE here" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                                <i class="fas fa-trash me-1"></i> Delete Delivery Permanently
                            </button>
                            <a href="view_buyer.php?id=<?php echo $delivery['buyer_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                        <a href="edit_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> Edit Instead
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Enable delete button only when "DELETE" is typed
document.getElementById('confirmation').addEventListener('input', function(e) {
    const deleteBtn = document.getElementById('deleteBtn');
    deleteBtn.disabled = this.value.toUpperCase() !== 'DELETE';
});

// Confirm deletion on button click
document.getElementById('deleteBtn').addEventListener('click', function(e) {
    if (!this.disabled) {
        if (!confirm('Are you absolutely sure you want to delete this delivery record? This action cannot be undone!')) {
            e.preventDefault();
        }
    }
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>