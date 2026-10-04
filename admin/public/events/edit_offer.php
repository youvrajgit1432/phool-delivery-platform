<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Edit Product Offer";

// Get offer ID from URL
$offer_id = $_GET['id'] ?? null;

if (!$offer_id) {
    $_SESSION['error_message'] = "Offer ID is required!";
    header("Location: ../events_offers.php");
    exit;
}

// Fetch offer data
try {
    $stmt = $pdo->prepare("
        SELECT po.*, p.name_en as product_name, dc.city_name
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

// Fetch products and cities for dropdowns
try {
    $products = $pdo->query("SELECT id, name_en, unit FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();
    $cities = $pdo->query("SELECT id, city_name FROM delivery_cities ORDER BY city_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $products = [];
    $cities = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $city_id = $_POST['city_id'] ?: null;
    $offer_type = $_POST['offer_type'];
    $buy_quantity = $_POST['buy_quantity'] ?: null;
    $get_quantity = $_POST['get_quantity'] ?: null;
    $discount_percentage = $_POST['discount_percentage'] ?: 0;
    $fixed_discount = $_POST['fixed_discount'] ?: 0;
    $min_order_amount = $_POST['min_order_amount'] ?: 0;
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs based on offer type
    $errors = [];
    
    if (empty($product_id)) {
        $errors[] = "Product is required!";
    }
    
    if ($offer_type === 'buy_x_get_y') {
        if (empty($buy_quantity) || $buy_quantity <= 0) {
            $errors[] = "Buy quantity is required for buy X get Y offers!";
        }
        if (empty($get_quantity) || $get_quantity <= 0) {
            $errors[] = "Get quantity is required for buy X get Y offers!";
        }
    } elseif ($offer_type === 'percentage_discount') {
        if ($discount_percentage <= 0 || $discount_percentage > 100) {
            $errors[] = "Discount percentage must be between 0 and 100!";
        }
    } elseif ($offer_type === 'fixed_discount') {
        if ($fixed_discount <= 0) {
            $errors[] = "Fixed discount must be greater than 0!";
        }
    }
    
    if ($start_date && $end_date && $start_date > $end_date) {
        $errors[] = "End date cannot be before start date!";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE product_offers 
                SET product_id = ?, city_id = ?, offer_type = ?, buy_quantity = ?, get_quantity = ?, 
                    discount_percentage = ?, fixed_discount = ?, min_order_amount = ?, 
                    start_date = ?, end_date = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $product_id, $city_id, $offer_type, $buy_quantity, $get_quantity, $discount_percentage,
                $fixed_discount, $min_order_amount, $start_date, $end_date, $is_active, $offer_id
            ])) {
                $_SESSION['success_message'] = "Product offer updated successfully!";
                header("Location: ../events_offers.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to update product offer.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating product offer: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Product Offer</h1>
    <a href="../events_offers.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Events & Offers
    </a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Product Offer Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editOfferForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_id" class="form-label">Product *</label>
                                <select class="form-select" id="product_id" name="product_id" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                        <?php echo ($offer['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name_en']); ?> (<?php echo $product['unit']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="city_id" class="form-label">City (Optional)</label>
                                <select class="form-select" id="city_id" name="city_id">
                                    <option value="">-- All Cities --</option>
                                    <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city['id']; ?>" 
                                        <?php echo ($offer['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['city_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Leave empty to apply to all cities.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="offer_type" class="form-label">Offer Type *</label>
                        <select class="form-select" id="offer_type" name="offer_type" required>
                            <option value="buy_x_get_y" <?php echo ($offer['offer_type'] == 'buy_x_get_y') ? 'selected' : ''; ?>>Buy X Get Y Free</option>
                            <option value="percentage_discount" <?php echo ($offer['offer_type'] == 'percentage_discount') ? 'selected' : ''; ?>>Percentage Discount</option>
                            <option value="fixed_discount" <?php echo ($offer['offer_type'] == 'fixed_discount') ? 'selected' : ''; ?>>Fixed Discount</option>
                        </select>
                    </div>
                    
                    <!-- Dynamic fields based on offer type -->
                    <div id="buy_x_get_y_fields" class="offer-type-fields" style="<?php echo $offer['offer_type'] != 'buy_x_get_y' ? 'display: none;' : ''; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="buy_quantity" class="form-label">Buy Quantity *</label>
                                    <input type="number" class="form-control" id="buy_quantity" name="buy_quantity" 
                                           min="0.01" step="0.01" 
                                           value="<?php echo $offer['buy_quantity']; ?>">
                                    <div class="form-text">e.g., 10 for 'Buy 10 kg'</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="get_quantity" class="form-label">Get Free Quantity *</label>
                                    <input type="number" class="form-control" id="get_quantity" name="get_quantity" 
                                           min="0.01" step="0.01" 
                                           value="<?php echo $offer['get_quantity']; ?>">
                                    <div class="form-text">e.g., 1 for 'Get 1 kg free'</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="percentage_discount_fields" class="offer-type-fields" style="<?php echo $offer['offer_type'] != 'percentage_discount' ? 'display: none;' : ''; ?>">
                        <div class="mb-3">
                            <label for="discount_percentage" class="form-label">Discount Percentage *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                       min="0" max="100" step="0.01" 
                                       value="<?php echo $offer['discount_percentage']; ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="fixed_discount_fields" class="offer-type-fields" style="<?php echo $offer['offer_type'] != 'fixed_discount' ? 'display: none;' : ''; ?>">
                        <div class="mb-3">
                            <label for="fixed_discount" class="form-label">Fixed Discount Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" class="form-control" id="fixed_discount" name="fixed_discount" 
                                       min="0" step="0.01" 
                                       value="<?php echo $offer['fixed_discount']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_order_amount" class="form-label">Minimum Order Amount (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" class="form-control" id="min_order_amount" name="min_order_amount" 
                                   min="0" step="0.01" 
                                   value="<?php echo $offer['min_order_amount']; ?>">
                        </div>
                        <div class="form-text">Minimum order amount required to avail this offer. Set to 0 for no minimum.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date (Optional)</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $offer['start_date']; ?>">
                                <div class="form-text">Leave empty for no start date limit.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date (Optional)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $offer['end_date']; ?>">
                                <div class="form-text">Leave empty for no end date limit.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                <?php echo $offer['is_active'] == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="form-text">Enable or disable this offer.</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../events_offers.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Offer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Offer Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Offer Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($offer['created_at'])); ?></p>
                        <?php if ($offer['updated_at'] && $offer['updated_at'] != $offer['created_at']): ?>
                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($offer['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $is_current = (!$offer['start_date'] || date('Y-m-d') >= $offer['start_date']) && 
                                     (!$offer['end_date'] || date('Y-m-d') <= $offer['end_date']);
                        $days_remaining = $is_current && $offer['end_date'] ? ceil((strtotime($offer['end_date']) - time()) / (60 * 60 * 24)) : 0;
                        ?>
                        <p><strong>Current Status:</strong> 
                            <span class="badge <?php echo $is_current && $offer['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $is_current && $offer['is_active'] ? 'Active Now' : 'Not Active'; ?>
                            </span>
                        </p>
                        <?php if ($is_current && $offer['end_date']): ?>
                        <p><strong>Days Remaining:</strong> <?php echo $days_remaining; ?> days</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const offerType = document.getElementById('offer_type');
    const form = document.getElementById('editOfferForm');
    
    function toggleOfferFields() {
        // Hide all fields first
        document.querySelectorAll('.offer-type-fields').forEach(field => {
            field.style.display = 'none';
        });
        
        // Show relevant fields
        const selectedType = offerType.value;
        document.getElementById(selectedType + '_fields').style.display = 'block';
    }
    
    // Initial toggle
    toggleOfferFields();
    
    // Toggle on change
    offerType.addEventListener('change', toggleOfferFields);
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const productId = document.getElementById('product_id').value;
        const selectedType = offerType.value;
        let isValid = true;
        
        if (!productId) {
            alert('Please select a product.');
            isValid = false;
        }
        
        if (selectedType === 'buy_x_get_y') {
            const buyQty = document.getElementById('buy_quantity').value;
            const getQty = document.getElementById('get_quantity').value;
            if (!buyQty || buyQty <= 0 || !getQty || getQty <= 0) {
                alert('Please enter valid buy and get quantities for buy X get Y offer.');
                isValid = false;
            }
        } else if (selectedType === 'percentage_discount') {
            const discount = parseFloat(document.getElementById('discount_percentage').value);
            if (discount <= 0 || discount > 100) {
                alert('Discount percentage must be between 0 and 100.');
                isValid = false;
            }
        } else if (selectedType === 'fixed_discount') {
            const fixedDiscount = parseFloat(document.getElementById('fixed_discount').value);
            if (fixedDiscount <= 0) {
                alert('Fixed discount must be greater than 0.');
                isValid = false;
            }
        }
        
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        if (startDate && endDate && startDate > endDate) {
            alert('End date cannot be before start date.');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>