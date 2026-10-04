
<?php
// public/expense/add.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

$pdo = getDBConnection();

// Get expense categories
$categories = $pdo->query("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY parent_category, name")->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $expense_date = $_POST['expense_date'];
    $payment_method = $_POST['payment_method'];
    $remarks = trim($_POST['remarks']);
    $status = $_POST['status'];
    
    // Validate input
    $errors = [];
    
    if (empty($category_id)) {
        $errors[] = "Category is required.";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required.";
    }
    
    if (empty($expense_date)) {
        $errors[] = "Expense date is required.";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Payment method is required.";
    }
    
    // Handle file upload
    $bill_receipt = '';
    if (isset($_FILES['bill_receipt']) && $_FILES['bill_receipt']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../storage/uploads/expenses/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['bill_receipt']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $file_name = 'expense_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['bill_receipt']['tmp_name'], $file_path)) {
                $bill_receipt = $file_name;
            } else {
                $errors[] = "Failed to upload bill/receipt file.";
            }
        } else {
            $errors[] = "Invalid file type. Allowed types: JPG, JPEG, PNG, PDF, DOC, DOCX";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (category_id, amount, description, expense_date, bill_receipt, remarks, payment_method, status, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([
                $category_id, 
                $amount, 
                $description, 
                $expense_date, 
                $bill_receipt, 
                $remarks, 
                $payment_method, 
                $status,
                $_SESSION['admin_id']
            ]);
            
            if ($success) {
                $_SESSION['success_message'] = "Expense recorded successfully!";
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Failed to record expense. Please try again.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Add Expense - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Record New Expense</h1>
    <a href="index.php" class="btn btn-secondary">Back to Expenses</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Expense Details</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                $current_type = '';
                                foreach ($categories as $category): 
                                    if ($category['parent_category'] != $current_type) {
                                        $current_type = $category['parent_category'];
                                        echo '<optgroup label="' . ucfirst(str_replace('_', ' ', $current_type)) . '">';
                                    }
                                ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php 
                                    // Close optgroup if next category has different type or it's the last one
                                    $next_category = next($categories);
                                    prev($categories); // Reset pointer
                                    if (!$next_category || $next_category['parent_category'] != $current_type) {
                                        echo '</optgroup>';
                                    }
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount (Rs.) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0" required 
                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo isset($_POST['expense_date']) ? htmlspecialchars($_POST['expense_date']) : date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank_transfer" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="digital_wallet" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'digital_wallet' ? 'selected' : ''; ?>>Digital Wallet</option>
                                <option value="credit_card" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="other" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the expense..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                  placeholder="Any additional remarks..."><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bill_receipt" class="form-label">Bill/Receipt (Optional)</label>
                            <input type="file" class="form-control" id="bill_receipt" name="bill_receipt" 
                                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <small class="text-muted">Supported formats: JPG, PNG, PDF, DOC (Max: 5MB)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="approved" selected>Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Record Expense
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
            </div>
            <div class="card-body">
                <?php
                $today = date('Y-m-d');
                $month_start = date('Y-m-01');
                
                $today_stats = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
                    FROM expenses 
                    WHERE expense_date = ? AND status = 'approved'
                ");
                $today_stats->execute([$today]);
                $today_data = $today_stats->fetch();
                
                $month_stats = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
                    FROM expenses 
                    WHERE expense_date >= ? AND status = 'approved'
                ");
                $month_stats->execute([$month_start]);
                $month_data = $month_stats->fetch();
                ?>
                
                <div class="mb-3">
                    <h6 class="text-primary">Today's Expenses</h6>
                    <p class="mb-1">Records: <?php echo $today_data['count']; ?></p>
                    <p class="mb-0 fw-bold">Total: Rs. <?php echo number_format($today_data['total'], 2); ?></p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-success">This Month</h6>
                    <p class="mb-1">Records: <?php echo $month_data['count']; ?></p>
                    <p class="mb-0 fw-bold">Total: Rs. <?php echo number_format($month_data['total'], 2); ?></p>
                </div>
                
                <hr>
                
                <div class="small text-muted">
                    <p><strong>Tips:</strong></p>
                    <ul class="ps-3">
                        <li>Upload bills for better record keeping</li>
                        <li>Use descriptive descriptions</li>
                        <li>Record expenses daily for accuracy</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set today's date as default if not set
document.addEventListener('DOMContentLoaded', function() {
    const expenseDate = document.getElementById('expense_date');
    if (!expenseDate.value) {
        expenseDate.value = '<?php echo date('Y-m-d'); ?>';
    }
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>