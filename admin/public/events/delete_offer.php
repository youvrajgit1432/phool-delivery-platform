<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Get offer ID from URL
$offer_id = $_GET['id'] ?? null;

if (!$offer_id) {
    $_SESSION['error_message'] = "Offer ID is required!";
    header("Location: ../events_offers.php");
    exit;
}

// Fetch offer data for confirmation message
try {
    $stmt = $pdo->prepare("
        SELECT po.*, p.name_en as product_name, dc.city_name,
               CASE 
                   WHEN po.offer_type = 'buy_x_get_y' THEN CONCAT('Buy ', po.buy_quantity, ' Get ', po.get_quantity, ' free')
                   WHEN po.offer_type = 'percentage_discount' THEN CONCAT(po.discount_percentage, '% Discount')
                   WHEN po.offer_type = 'fixed_discount' THEN CONCAT('Rs. ', po.fixed_discount, ' off')
               END as offer_description
        FROM product_offers po
        LEFT JOIN products p ON po.product_id = p.id
        LEFT JOIN delivery_cities dc ON po.city_id = dc.id
        WHERE po.id = ?
    ");
    $stmt->execute([$offer_id]);
    $offer = $stmt->fetch();
    
    if (!$offer) {
        $_SESSION['error_message'] = "Offer not found!";
        header("Location: ../events_offers.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching offer: " . $e->getMessage();
    header("Location: ../events_offers.php");
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM product_offers WHERE id = ?");
        
        if ($stmt->execute([$offer_id])) {
            $_SESSION['success_message'] = "Product offer deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete offer.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting offer: " . $e->getMessage();
    }
    
    header("Location: ../events_offers.php");
    exit;
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 text-danger">Delete Product Offer</h1>
    <a href="../events_offers.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Events & Offers
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6 class="alert-heading">Warning!</h6>
                    <p class="mb-0">You are about to delete a product offer. This action cannot be undone.</p>
                </div>
                
                <div class="offer-info p-3 border rounded bg-light">
                    <h6>Offer Details:</h6>
                    <p><strong>Product:</strong> <?php echo htmlspecialchars($offer['product_name']); ?></p>
                    <p><strong>Offer Type:</strong> 
                        <span class="badge bg-info">
                            <?php echo ucfirst(str_replace('_', ' ', $offer['offer_type'])); ?>
                        </span>
                    </p>
                    <p><strong>Offer Description:</strong> <?php echo $offer['offer_description']; ?></p>
                    <p><strong>City:</strong> 
                        <?php if ($offer['city_name']): ?>
                            <span class="badge bg-secondary"><?php echo $offer['city_name']; ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark">All Cities</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($offer['min_order_amount'] > 0): ?>
                    <p><strong>Minimum Order:</strong> Rs. <?php echo $offer['min_order_amount']; ?></p>
                    <?php endif; ?>
                    <?php if ($offer['start_date'] && $offer['end_date']): ?>
                    <p><strong>Duration:</strong> 
                        <?php echo date('M j, Y', strtotime($offer['start_date'])); ?> 
                        to 
                        <?php echo date('M j, Y', strtotime($offer['end_date'])); ?>
                    </p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $offer['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $offer['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($offer['created_at'])); ?></p>
                </div>
                
                <form method="POST" class="mt-4">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash me-2"></i>Confirm Delete
                        </button>
                        <a href="../events_offers.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include '../../app/views/layouts/footer.php';
?>