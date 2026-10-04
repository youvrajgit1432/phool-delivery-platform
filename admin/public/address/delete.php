<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Get city ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid city ID!";
    header("Location: ../address_fee.php");
    exit;
}

$city_id = $_GET['id'];

// Fetch city data for confirmation message
try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_cities WHERE id = ?");
    $stmt->execute([$city_id]);
    $city = $stmt->fetch();
    
    if (!$city) {
        $_SESSION['error_message'] = "City not found!";
        header("Location: ../address_fee.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching city: " . $e->getMessage();
    header("Location: ../address_fee.php");
    exit;
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        try {
            // Start transaction to ensure all deletions happen together
            $pdo->beginTransaction();
            
            // First, delete related records from product_minimum_quantities table
            $stmt1 = $pdo->prepare("DELETE FROM product_minimum_quantities WHERE city_id = ?");
            $stmt1->execute([$city_id]);
            
            // Then delete the city itself
            $stmt2 = $pdo->prepare("DELETE FROM delivery_cities WHERE id = ?");
            $stmt2->execute([$city_id]);
            
            // Check if city was actually deleted
            if ($stmt2->rowCount() > 0) {
                $pdo->commit();
                $_SESSION['success_message'] = "City '" . htmlspecialchars($city['city_name']) . "' and all related data deleted successfully!";
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Failed to delete city. City may have been already deleted.";
            }
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Check if it's a foreign key constraint error
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $_SESSION['error_message'] = "Cannot delete city. It is being used in orders or other records. Please remove all related records first.";
            } else {
                $_SESSION['error_message'] = "Error deleting city: " . $e->getMessage();
            }
            
            error_log("Delete city error: " . $e->getMessage());
        }
    } else {
        $_SESSION['info_message'] = "Deletion cancelled.";
    }
    
    header("Location: ../address_fee.php");
    exit;
}

$page_title = "Delete City";
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete City</h1>
    <a href="../address_fee.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Cities
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Confirm Deletion</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                
                <p>Are you sure you want to delete the following city?</p>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title"><?php echo htmlspecialchars($city['city_name']); ?></h6>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Standard Fee:</small><br>
                                <strong>Rs. <?php echo number_format($city['standard_delivery_fee'], 2); ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Nighttime Fee:</small><br>
                                <strong>Rs. <?php echo number_format($city['nighttime_urgent_fee'], 2); ?></strong>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Created: <?php echo date('M j, Y', strtotime($city['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                
                <form method="POST" id="deleteForm">
                    <div class="d-grid gap-2">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Yes, Delete This City
                        </button>
                        <a href="../address_fee.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('deleteForm');
    
    form.addEventListener('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete this city? This will also delete all minimum quantity rules for this city. This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>