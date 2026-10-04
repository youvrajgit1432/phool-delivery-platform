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
    SELECT fd.*, b.buyer_name, b.buyer_type, b.special_rate 
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

// Get all buyers for dropdown
$buyers = $pdo->query("SELECT buyer_id, buyer_name, buyer_type, special_rate FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll();

// Common flower types
$flower_types = ['Marigold', 'Rose', 'Jasmine', 'Chrysanthemum', 'Orchid', 'Lily', 'Carnation', 'Sunflower', 'Other'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $buyer_id = intval($_POST['buyer_id']);
        $delivery_date = $_POST['delivery_date'];
        $flower_type = trim($_POST['flower_type']);
        $quantity_kg = floatval($_POST['quantity_kg']);
        $rate_per_kg = floatval($_POST['rate_per_kg']);
        $payment_received = floatval($_POST['payment_received']);
        $remarks = trim($_POST['remarks']) ?: null;
        
        // Calculate total amount
        $total_amount = $quantity_kg * $rate_per_kg;
        
        // Update delivery record
        $stmt = $pdo->prepare("
            UPDATE flower_deliveries SET 
            buyer_id = ?, delivery_date = ?, flower_type = ?, quantity_kg = ?, 
            rate_per_kg = ?, total_amount = ?, payment_received = ?, remarks = ? 
            WHERE delivery_id = ?
        ");
        
        $stmt->execute([
            $buyer_id, $delivery_date, $flower_type, $quantity_kg, $rate_per_kg, 
            $total_amount, $payment_received, $remarks, $delivery_id
        ]);
        
        $_SESSION['success_message'] = "Delivery record updated successfully!";
        header("Location: view_buyer.php?id=" . $buyer_id);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Failed to update delivery record: " . $e->getMessage();
    }
}

// Set page title
$page_title = "Edit Delivery - " . htmlspecialchars($delivery['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Delivery Record</h1>
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
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="buyer_id" class="form-label">Buyer *</label>
                        <select class="form-select" id="buyer_id" name="buyer_id" required>
                            <option value="">Select Buyer</option>
                            <?php foreach ($buyers as $buyer): ?>
                            <option value="<?php echo $buyer['buyer_id']; ?>" 
                                    <?php echo $delivery['buyer_id'] == $buyer['buyer_id'] ? 'selected' : ''; ?>
                                    data-special-rate="<?php echo $buyer['special_rate'] ?? ''; ?>">
                                <?php echo htmlspecialchars($buyer['buyer_name']); ?> 
                                (<?php echo ucfirst($buyer['buyer_type']); ?>)
                                <?php if (!empty($buyer['special_rate'])): ?>
                                - Special Rate: Rs. <?php echo number_format($buyer['special_rate'], 2); ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delivery_date" class="form-label">Delivery Date *</label>
                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                               value="<?php echo $delivery['delivery_date']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="flower_type" class="form-label">Flower Type *</label>
                        <select class="form-select" id="flower_type" name="flower_type" required>
                            <option value="">Select Flower Type</option>
                            <?php foreach ($flower_types as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                    <?php echo $delivery['flower_type'] == $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" class="form-control mt-2 <?php echo !in_array($delivery['flower_type'], $flower_types) ? '' : 'd-none'; ?>" 
                               id="custom_flower_type" name="custom_flower_type" 
                               placeholder="Enter custom flower type"
                               value="<?php echo !in_array($delivery['flower_type'], $flower_types) ? htmlspecialchars($delivery['flower_type']) : ''; ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="quantity_kg" class="form-label">Quantity (KG) *</label>
                        <input type="number" step="0.01" class="form-control" id="quantity_kg" name="quantity_kg" 
                               value="<?php echo $delivery['quantity_kg']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rate_per_kg" class="form-label">Rate per KG (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" id="rate_per_kg" name="rate_per_kg" 
                               value="<?php echo $delivery['rate_per_kg']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_received" class="form-label">Payment Received (Rs.)</label>
                        <input type="number" step="0.01" class="form-control" id="payment_received" name="payment_received" 
                               value="<?php echo $delivery['payment_received']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Calculated Amounts</label>
                        <div class="border p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Total Amount:</strong>
                                </div>
                                <div class="col-6">
                                    <span id="total_amount_display">Rs. <?php echo number_format($delivery['total_amount'], 2); ?></span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Pending Amount:</strong>
                                </div>
                                <div class="col-6">
                                    <span id="pending_amount_display">Rs. <?php echo number_format($delivery['payment_pending'], 2); ?></span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Payment Status:</strong>
                                </div>
                                <div class="col-6">
                                    <span id="payment_status_display" class="badge bg-<?php 
                                        switch($delivery['payment_status']) {
                                            case 'paid': echo 'success'; break;
                                            case 'partial': echo 'warning'; break;
                                            case 'pending': echo 'danger'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst($delivery['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Any additional notes..."><?php echo htmlspecialchars($delivery['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Update Delivery Record</button>
                    <a href="view_buyer.php?id=<?php echo $delivery['buyer_id']; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Calculate amounts in real-time
function calculateAmounts() {
    const quantity = parseFloat(document.getElementById('quantity_kg').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_kg').value) || 0;
    const payment = parseFloat(document.getElementById('payment_received').value) || 0;
    
    const total = quantity * rate;
    const pending = total - payment;
    
    // Update displays
    document.getElementById('total_amount_display').textContent = 'Rs. ' + total.toFixed(2);
    document.getElementById('pending_amount_display').textContent = 'Rs. ' + pending.toFixed(2);
    
    // Update payment status
    const statusElement = document.getElementById('payment_status_display');
    if (payment === 0) {
        statusElement.textContent = 'Pending';
        statusElement.className = 'badge bg-danger';
    } else if (payment === total) {
        statusElement.textContent = 'Paid';
        statusElement.className = 'badge bg-success';
    } else {
        statusElement.textContent = 'Partial';
        statusElement.className = 'badge bg-warning';
    }
}

// Event listeners for calculation
document.getElementById('quantity_kg').addEventListener('input', calculateAmounts);
document.getElementById('rate_per_kg').addEventListener('input', calculateAmounts);
document.getElementById('payment_received').addEventListener('input', calculateAmounts);

// Auto-fill special rate when buyer is selected
document.getElementById('buyer_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const specialRate = selectedOption.getAttribute('data-special-rate');
    
    if (specialRate) {
        document.getElementById('rate_per_kg').value = specialRate;
        calculateAmounts();
    }
});

// Custom flower type input
document.getElementById('flower_type').addEventListener('change', function() {
    const customInput = document.getElementById('custom_flower_type');
    if (this.value === 'Other') {
        customInput.classList.remove('d-none');
        customInput.required = true;
    } else {
        customInput.classList.add('d-none');
        customInput.required = false;
    }
});

// Initialize calculations
calculateAmounts();
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>