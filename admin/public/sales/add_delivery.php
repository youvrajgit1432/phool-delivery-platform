<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get buyer_id from URL if provided
$buyer_id = isset($_GET['buyer_id']) ? intval($_GET['buyer_id']) : null;

// Get all buyers for dropdown
$buyers = $pdo->query("SELECT buyer_id, buyer_name, buyer_type, special_rate FROM buyers WHERE status = 'active' ORDER BY buyer_name")->fetchAll();

// Common flower types
$flower_types = ['Marigold', 'Rose', 'Jasmine', 'Chrysanthemum', 'Orchid', 'Lily', 'Carnation', 'Sunflower', 'Other'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $buyer_id = intval($_POST['buyer_id']);
        $delivery_dates = $_POST['delivery_date'];
        $flower_types = $_POST['flower_type'];
        $quantities_kg = $_POST['quantity_kg'];
        $rates_per_kg = $_POST['rate_per_kg'];
        $payments_received = $_POST['payment_received'];
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        
        $success_count = 0;
        $error_messages = [];
        
        // Process multiple delivery records
        for ($i = 0; $i < count($delivery_dates); $i++) {
            if (empty($delivery_dates[$i]) || empty($flower_types[$i]) || empty($quantities_kg[$i]) || empty($rates_per_kg[$i])) {
                continue; // Skip empty rows
            }
            
            $delivery_date = $delivery_dates[$i];
            $flower_type = trim($flower_types[$i]);
            $quantity_kg = floatval($quantities_kg[$i]);
            $rate_per_kg = floatval($rates_per_kg[$i]);
            $payment_received = floatval($payments_received[$i]);
            
            // Calculate total amount and pending amount
            $total_amount = $quantity_kg * $rate_per_kg;
            $payment_pending = $total_amount - $payment_received;
            
            // Determine payment status
            if ($payment_received == 0) {
                $payment_status = 'pending';
            } elseif ($payment_received == $total_amount) {
                $payment_status = 'paid';
            } else {
                $payment_status = 'partial';
            }
            
            // Insert delivery record
            $stmt = $pdo->prepare("
                INSERT INTO flower_deliveries 
                (buyer_id, delivery_date, flower_type, quantity_kg, rate_per_kg, total_amount, payment_received, payment_pending, payment_status, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $buyer_id, $delivery_date, $flower_type, $quantity_kg, $rate_per_kg, 
                $total_amount, $payment_received, $payment_pending, $payment_status, $remarks
            ])) {
                $success_count++;
            } else {
                $error_messages[] = "Failed to add record for date: $delivery_date";
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "Successfully added $success_count delivery record(s)!";
        }
        
        if (!empty($error_messages)) {
            $_SESSION['error_message'] = implode('<br>', $error_messages);
        }
        
        header("Location: ../sales.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Failed to add delivery records: " . $e->getMessage();
    }
}

// Set page title
$page_title = "Add Delivery - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add Delivery Records</h1>
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
    <div class="card-header">
        <h5 class="card-title mb-0">Add Multiple Delivery Records</h5>
        <p class="text-muted mb-0">You can add up to 10 delivery records at once for the selected buyer.</p>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="deliveryForm">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="buyer_id" class="form-label">Buyer *</label>
                        <select class="form-select" id="buyer_id" name="buyer_id" required>
                            <option value="">Select Buyer</option>
                            <?php foreach ($buyers as $buyer): ?>
                            <option value="<?php echo $buyer['buyer_id']; ?>" 
                                    <?php echo $buyer_id == $buyer['buyer_id'] ? 'selected' : ''; ?>
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
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks (Optional - applies to all records)</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Any additional notes that apply to all delivery records..."></textarea>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Delivery Records</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addMoreBtn">
                            <i class="fas fa-plus me-1"></i> Add More Rows
                        </button>
                    </div>
                </div>
            </div>

            <div id="deliveryRows">
                <!-- Initial row -->
                <div class="row delivery-row mb-3 border-bottom pb-3">
                    <div class="col-md-2">
                        <label class="form-label">Delivery Date *</label>
                        <input type="date" class="form-control delivery-date" name="delivery_date[]" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Flower Type *</label>
                        <select class="form-select flower-type" name="flower_type[]" required>
                            <option value="">Select Type</option>
                            <?php foreach ($flower_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $type === 'Marigold' ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" class="form-control mt-1 d-none custom-flower-type" placeholder="Enter custom flower type">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Qty (KG) *</label>
                        <input type="number" step="0.01" class="form-control quantity" name="quantity_kg[]" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Rate/KG *</label>
                        <input type="number" step="0.01" class="form-control rate" name="rate_per_kg[]" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Payment (Rs.)</label>
                        <input type="number" step="0.01" class="form-control payment" name="payment_received[]" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Calculated Amounts</label>
                        <div class="border p-2 bg-light rounded">
                            <div class="small">
                                <strong>Total:</strong> <span class="total-amount">Rs. 0.00</span><br>
                                <strong>Pending:</strong> <span class="pending-amount">Rs. 0.00</span><br>
                                <span class="badge payment-status bg-secondary">Pending</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" style="display: none;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary me-md-2">
                            <i class="fas fa-save me-1"></i> Save All Records
                        </button>
                        <a href="../sales.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let rowCount = 1;
const maxRows = 10;

// Initialize the first row
document.addEventListener('DOMContentLoaded', function() {
    calculateRowAmounts(document.querySelector('.delivery-row'));
    updateAddMoreButton();
});

// Add more rows
document.getElementById('addMoreBtn').addEventListener('click', function() {
    if (rowCount >= maxRows) {
        alert('Maximum ' + maxRows + ' rows allowed');
        return;
    }
    
    const template = document.querySelector('.delivery-row').cloneNode(true);
    const newRow = document.getElementById('deliveryRows').appendChild(template);
    
    // Clear input values in new row
    newRow.querySelector('.delivery-date').value = '<?php echo date('Y-m-d'); ?>';
    newRow.querySelector('.flower-type').value = 'Marigold'; // Set Marigold as default
    newRow.querySelector('.quantity').value = '';
    newRow.querySelector('.rate').value = '';
    newRow.querySelector('.payment').value = '0';
    newRow.querySelector('.custom-flower-type').classList.add('d-none');
    newRow.querySelector('.custom-flower-type').value = '';
    
    // Reset calculated amounts
    newRow.querySelector('.total-amount').textContent = 'Rs. 0.00';
    newRow.querySelector('.pending-amount').textContent = 'Rs. 0.00';
    newRow.querySelector('.payment-status').textContent = 'Pending';
    newRow.querySelector('.payment-status').className = 'badge payment-status bg-secondary';
    
    // Show remove button for all rows except first
    newRow.querySelector('.remove-row').style.display = 'block';
    
    // Add event listeners to new row
    addRowEventListeners(newRow);
    
    rowCount++;
    updateAddMoreButton();
});

// Remove row
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-row')) {
        const row = e.target.closest('.delivery-row');
        if (document.querySelectorAll('.delivery-row').length > 1) {
            row.remove();
            rowCount--;
            updateAddMoreButton();
        } else {
            alert('At least one row is required');
        }
    }
});

// Add event listeners to a row
function addRowEventListeners(row) {
    const quantity = row.querySelector('.quantity');
    const rate = row.querySelector('.rate');
    const payment = row.querySelector('.payment');
    const flowerType = row.querySelector('.flower-type');
    const customFlowerType = row.querySelector('.custom-flower-type');
    
    [quantity, rate, payment].forEach(input => {
        input.addEventListener('input', () => calculateRowAmounts(row));
    });
    
    flowerType.addEventListener('change', function() {
        if (this.value === 'Other') {
            customFlowerType.classList.remove('d-none');
            customFlowerType.required = true;
            // Update the flower_type field value
            this.name = 'flower_type_temp[]';
            customFlowerType.name = 'flower_type[]';
        } else {
            customFlowerType.classList.add('d-none');
            customFlowerType.required = false;
            // Reset the names
            this.name = 'flower_type[]';
            customFlowerType.name = 'custom_flower_type_temp';
        }
        calculateRowAmounts(row);
    });
    
    customFlowerType.addEventListener('input', function() {
        calculateRowAmounts(row);
    });
}

// Calculate amounts for a specific row
function calculateRowAmounts(row) {
    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
    const rate = parseFloat(row.querySelector('.rate').value) || 0;
    const payment = parseFloat(row.querySelector('.payment').value) || 0;
    
    const total = quantity * rate;
    const pending = total - payment;
    
    // Update displays
    row.querySelector('.total-amount').textContent = 'Rs. ' + total.toFixed(2);
    row.querySelector('.pending-amount').textContent = 'Rs. ' + pending.toFixed(2);
    
    // Update payment status
    const statusElement = row.querySelector('.payment-status');
    if (payment === 0) {
        statusElement.textContent = 'Pending';
        statusElement.className = 'badge payment-status bg-danger';
    } else if (payment === total) {
        statusElement.textContent = 'Paid';
        statusElement.className = 'badge payment-status bg-success';
    } else {
        statusElement.textContent = 'Partial';
        statusElement.className = 'badge payment-status bg-warning';
    }
}

// Auto-fill special rate when buyer is selected
document.getElementById('buyer_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const specialRate = selectedOption.getAttribute('data-special-rate');
    
    if (specialRate) {
        // Apply special rate to all rate fields
        document.querySelectorAll('.rate').forEach(rateField => {
            if (!rateField.value) { // Only fill if empty
                rateField.value = specialRate;
                calculateRowAmounts(rateField.closest('.delivery-row'));
            }
        });
    }
});

// Update add more button state
function updateAddMoreButton() {
    const addMoreBtn = document.getElementById('addMoreBtn');
    if (rowCount >= maxRows) {
        addMoreBtn.disabled = true;
        addMoreBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Maximum Reached';
    } else {
        addMoreBtn.disabled = false;
        addMoreBtn.innerHTML = `<i class="fas fa-plus me-1"></i> Add More Rows (${maxRows - rowCount} left)`;
    }
}

// Form validation
document.getElementById('deliveryForm').addEventListener('submit', function(e) {
    let hasValidRows = false;
    
    document.querySelectorAll('.delivery-row').forEach(row => {
        const date = row.querySelector('.delivery-date').value;
        const flowerType = row.querySelector('.flower-type').value;
        const quantity = row.querySelector('.quantity').value;
        const rate = row.querySelector('.rate').value;
        
        if (date && flowerType && quantity && rate) {
            hasValidRows = true;
        }
    });
    
    if (!hasValidRows) {
        e.preventDefault();
        alert('Please fill at least one complete delivery record.');
        return;
    }
    
    const buyerId = document.getElementById('buyer_id').value;
    if (!buyerId) {
        e.preventDefault();
        alert('Please select a buyer.');
        return;
    }
});

// Add event listeners to initial row
addRowEventListeners(document.querySelector('.delivery-row'));
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>