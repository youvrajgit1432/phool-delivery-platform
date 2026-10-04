<?php
// public/call/add_call_order.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get all active products
$products = $pdo->query("SELECT id, name_en, price FROM products WHERE status = 'active' ORDER BY name_en")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle customer information
        $customer_name = trim($_POST['customer_name']);
        $phone_number = trim($_POST['phone_number']);
        $city = trim($_POST['city']);
        $street = trim($_POST['street']);
        
        // Check if customer exists
        $existing_customer = $pdo->prepare("SELECT id FROM call_customers WHERE phone_number = ?");
        $existing_customer->execute([$phone_number]);
        $customer = $existing_customer->fetch();
        
        if ($customer) {
            $call_customer_id = $customer['id'];
            // Update customer details if needed
            $update_customer = $pdo->prepare("UPDATE call_customers SET name = ?, city = ?, street = ? WHERE id = ?");
            $update_customer->execute([$customer_name, $city, $street, $call_customer_id]);
        } else {
            // Create new customer
            $insert_customer = $pdo->prepare("INSERT INTO call_customers (name, phone_number, city, street) VALUES (?, ?, ?, ?)");
            $insert_customer->execute([$customer_name, $phone_number, $city, $street]);
            $call_customer_id = $pdo->lastInsertId();
        }
        
        // Handle order creation
        $product_ids = $_POST['product_id'];
        $quantities = $_POST['quantity'];
        $rates = $_POST['rate'];
        $payment_method = $_POST['payment_method'];
        $status = $_POST['status'];
        $admin_notes = trim($_POST['admin_notes']);
        $generate_invoice = isset($_POST['generate_invoice']) ? 1 : 0;
        
        $order_ids = [];
        
        // Insert each product as separate order
        foreach ($product_ids as $index => $product_id) {
            if (!empty($product_id) && !empty($quantities[$index]) && !empty($rates[$index])) {
                $stmt = $pdo->prepare("
                    INSERT INTO call_customer_orders (call_customer_id, product_id, quantity, rate, payment_method, status, admin_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $call_customer_id,
                    $product_id,
                    $quantities[$index],
                    $rates[$index],
                    $payment_method,
                    $status,
                    $admin_notes
                ]);
                
                $order_ids[] = $pdo->lastInsertId();
            }
        }
        
        // Generate invoice if requested
        if ($generate_invoice && !empty($order_ids)) {
            // Calculate total amount
            $total_amount = 0;
            foreach ($order_ids as $order_id) {
                $order_total = $pdo->prepare("SELECT total_amount FROM call_customer_orders WHERE id = ?");
                $order_total->execute([$order_id]);
                $total = $order_total->fetch();
                $total_amount += $total['total_amount'];
            }
            
            // Generate invoice number
            $invoice_number = 'CALL-INV-' . date('Ymd') . '-' . str_pad($call_customer_id, 4, '0', STR_PAD_LEFT);
            
            // Create invoice record
            $invoice_stmt = $pdo->prepare("
                INSERT INTO call_invoices (call_order_id, call_customer_id, invoice_number, invoice_date, due_date, subtotal, total_amount, status, notes)
                VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), ?, ?, 'draft', ?)
            ");
            
            // Use the first order ID as primary reference
            $invoice_stmt->execute([
                $order_ids[0],
                $call_customer_id,
                $invoice_number,
                $total_amount,
                $total_amount,
                "Call order invoice for customer: $customer_name"
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            $_SESSION['success_message'] = "Call order created successfully! Invoice #$invoice_number has been generated.";
            $_SESSION['new_invoice_id'] = $invoice_id;
        } else {
            $_SESSION['success_message'] = "Call order created successfully!";
        }
        
        header("Location: ../call_orders.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error creating order: " . $e->getMessage();
    }
}

// Set page title
$page_title = "Add Call Order - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Call Order</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../call_orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Call Orders
        </a>
    </div>
</div>

<form method="POST" action="" id="callOrderForm">
    <div class="row">
        <div class="col-md-6">
            <!-- Customer Details Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>Customer Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" required 
                               placeholder="Enter phone number to check existing customer">
                        <div class="form-text">Enter phone number to auto-fill customer details</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name *</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="street" class="form-label">Street Address</label>
                            <input type="text" class="form-control" id="street" name="street">
                        </div>
                    </div>
                    
                    <div id="customerMatchAlert" class="alert alert-info d-none">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="customerMatchText"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Order Details Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Order Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="Cash on Delivery">Cash on Delivery</option>
                            <option value="Wallets">Wallets</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Order Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="Any special instructions or notes..."></textarea>
                    </div>
                    
                    <!-- Invoice Option -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="generate_invoice" name="generate_invoice" value="1" checked>
                            <label class="form-check-label" for="generate_invoice">
                                Generate Invoice for this order
                            </label>
                        </div>
                        <div class="form-text">An invoice will be created automatically for billing purposes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Products Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-boxes me-2"></i>Products
            </h5>
            <button type="button" class="btn btn-sm btn-primary" id="addProductBtn">
                <i class="fas fa-plus me-1"></i> Add Product
            </button>
        </div>
        <div class="card-body">
            <div id="productsContainer">
                <div class="product-row row mb-3">
                    <div class="col-md-5">
                        <label class="form-label">Product *</label>
                        <select class="form-select product-select" name="product_id[]" required onchange="updateRate(this)">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                                <?php echo htmlspecialchars($product['name_en']); ?> - Rs. <?php echo number_format($product['price'], 2); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity *</label>
                        <input type="number" step="0.01" class="form-control quantity" name="quantity[]" value="1" min="1" required onchange="calculateTotal()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rate (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control rate" name="rate[]" value="0.00" min="0" required onchange="calculateTotal()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total (Rs.)</label>
                        <input type="text" class="form-control total" readonly value="0.00">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-sm remove-product" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                        <strong>Grand Total:</strong>
                        <strong id="grandTotal">Rs. 0.00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Create Order
                </button>
                <a href="../call_orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<script>
let productCounter = 1;

// Add product row
document.getElementById('addProductBtn').addEventListener('click', function() {
    const container = document.getElementById('productsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'product-row row mb-3';
    newRow.innerHTML = `
        <div class="col-md-5">
            <label class="form-label">Product *</label>
            <select class="form-select product-select" name="product_id[]" required onchange="updateRate(this)">
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                    <?php echo htmlspecialchars($product['name_en']); ?> - Rs. <?php echo number_format($product['price'], 2); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Quantity *</label>
            <input type="number" step="0.01" class="form-control quantity" name="quantity[]" value="1" min="1" required onchange="calculateTotal()">
        </div>
        <div class="col-md-2">
            <label class="form-label">Rate (Rs.) *</label>
            <input type="number" step="0.01" class="form-control rate" name="rate[]" value="0.00" min="0" required onchange="calculateTotal()">
        </div>
        <div class="col-md-2">
            <label class="form-label">Total (Rs.)</label>
            <input type="text" class="form-control total" readonly value="0.00">
        </div>
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-danger btn-sm remove-product">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    
    // Show remove buttons for all rows except first
    document.querySelectorAll('.remove-product').forEach(btn => {
        btn.style.display = 'inline-block';
    });
});

// Remove product row
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-product') || e.target.closest('.remove-product')) {
        const btn = e.target.classList.contains('remove-product') ? e.target : e.target.closest('.remove-product');
        const row = btn.closest('.product-row');
        if (document.querySelectorAll('.product-row').length > 1) {
            row.remove();
            calculateTotal();
        }
    }
});

// Update rate when product is selected
function updateRate(select) {
    const price = select.options[select.selectedIndex].getAttribute('data-price');
    const row = select.closest('.product-row');
    const rateInput = row.querySelector('.rate');
    const quantityInput = row.querySelector('.quantity');
    
    if (price) {
        rateInput.value = parseFloat(price).toFixed(2);
        calculateRowTotal(row);
        calculateTotal();
    }
}

// Calculate row total
function calculateRowTotal(row) {
    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
    const rate = parseFloat(row.querySelector('.rate').value) || 0;
    const total = quantity * rate;
    row.querySelector('.total').value = total.toFixed(2);
}

// Calculate grand total
function calculateTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.product-row').forEach(row => {
        calculateRowTotal(row);
        const total = parseFloat(row.querySelector('.total').value) || 0;
        grandTotal += total;
    });
    document.getElementById('grandTotal').textContent = 'Rs. ' + grandTotal.toFixed(2);
}

// Customer lookup by phone number
document.getElementById('phone_number').addEventListener('blur', function() {
    const phone = this.value.trim();
    if (phone.length >= 7) {
        // AJAX call to check customer
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'check_customer.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                const alert = document.getElementById('customerMatchAlert');
                const alertText = document.getElementById('customerMatchText');
                
                if (response.exists) {
                    // Auto-fill customer details
                    document.getElementById('customer_name').value = response.customer.name;
                    document.getElementById('city').value = response.customer.city || '';
                    document.getElementById('street').value = response.customer.street || '';
                    
                    alertText.textContent = 'Existing customer found! Details auto-filled.';
                    alert.className = 'alert alert-success';
                    alert.classList.remove('d-none');
                } else {
                    alertText.textContent = 'New customer. Please fill in the details.';
                    alert.className = 'alert alert-info';
                    alert.classList.remove('d-none');
                }
            }
        };
        xhr.send('phone_number=' + encodeURIComponent(phone));
    }
});

// Initialize calculations
calculateTotal();
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>