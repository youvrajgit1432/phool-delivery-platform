<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get buyer ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid buyer ID.";
    header("Location: ../sales.php");
    exit;
}

$buyer_id = intval($_GET['id']);

// Get buyer details
$buyer = $pdo->prepare("SELECT * FROM buyers WHERE buyer_id = ?");
$buyer->execute([$buyer_id]);
$buyer = $buyer->fetch();

if (!$buyer) {
    $_SESSION['error_message'] = "Buyer not found.";
    header("Location: ../sales.php");
    exit;
}

// Check if buyer has any deliveries
$deliveries_count = $pdo->prepare("SELECT COUNT(*) as count FROM flower_deliveries WHERE buyer_id = ?");
$deliveries_count->execute([$buyer_id]);
$deliveries_count = $deliveries_count->fetch();

// Process deletion if confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete profile picture if exists
        if (!empty($buyer['profile_picture'])) {
            $upload_dir = '../../storage/uploads/buyers/';
            $file_path = $upload_dir . $buyer['profile_picture'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete buyer (cascade will handle related records if foreign keys are properly set)
        $stmt = $pdo->prepare("DELETE FROM buyers WHERE buyer_id = ?");
        $stmt->execute([$buyer_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Buyer deleted successfully!";
        header("Location: ../sales.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Failed to delete buyer: " . $e->getMessage();
        header("Location: view_buyer.php?id=" . $buyer_id);
        exit;
    }
}

// Set page title
$page_title = "Delete Buyer - " . htmlspecialchars($buyer['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete Buyer</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
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
            <p class="mb-0">You are about to delete a buyer. This action cannot be undone.</p>
        </div>

        <!-- Buyer Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Buyer Details</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($buyer['buyer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Contact:</th>
                        <td><?php echo htmlspecialchars($buyer['contact_number1']); ?></td>
                    </tr>
                    <tr>
                        <th>Type:</th>
                        <td><?php echo ucfirst($buyer['buyer_type']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><?php echo ucfirst($buyer['status']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5>Related Records</h5>
                <table class="table table-sm">
                    <tr>
                        <th>Delivery Records:</th>
                        <td>
                            <span class="badge bg-<?php echo $deliveries_count['count'] > 0 ? 'warning' : 'success'; ?>">
                                <?php echo $deliveries_count['count']; ?> records
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Profile Picture:</th>
                        <td>
                            <?php if (!empty($buyer['profile_picture'])): ?>
                            <span class="badge bg-info">Exists</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">None</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($deliveries_count['count'] > 0): ?>
        <div class="alert alert-danger">
            <h6 class="alert-heading">
                <i class="fas fa-exclamation-circle me-2"></i>Important Notice
            </h6>
            <p class="mb-2">This buyer has <strong><?php echo $deliveries_count['count']; ?> delivery records</strong>. Deleting this buyer will also delete all associated delivery and payment records.</p>
            <p class="mb-0">Consider deactivating the buyer instead of deleting if you want to preserve historical data.</p>
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
                                <i class="fas fa-trash me-1"></i> Delete Buyer Permanently
                            </button>
                            <a href="view_buyer.php?id=<?php echo $buyer_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                        <?php if ($deliveries_count['count'] == 0): ?>
                        <a href="edit_buyer.php?id=<?php echo $buyer_id; ?>&status=inactive" class="btn btn-warning">
                            <i class="fas fa-user-slash me-1"></i> Deactivate Instead
                        </a>
                        <?php endif; ?>
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
        if (!confirm('Are you absolutely sure you want to delete this buyer? This action cannot be undone!')) {
            e.preventDefault();
        }
    }
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>