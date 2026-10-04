<?php
// public/expense/edit.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No expense ID specified.";
    header("Location: index.php");
    exit;
}

$pdo = getDBConnection();
$expense_id = intval($_GET['id']);

// Get expense details
$stmt = $pdo->prepare("
    SELECT e.*, ec.name as category_name, ec.parent_category
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.id = ?
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) {
    $_SESSION['error_message'] = "Expense record not found.";
    header("Location: index.php");
    exit;
}

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
    $remove_bill = isset($_POST['remove_bill']) ? true : false;
    
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
    $bill_receipt = $expense['bill_receipt'];
    
    if ($remove_bill && !empty($bill_receipt)) {
        $file_path = '../../storage/uploads/expenses/' . $bill_receipt;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $bill_receipt = '';
    }
    
    if (isset($_FILES['bill_receipt']) && $_FILES['bill_receipt']['error'] === UPLOAD_ERR_OK) {
        // Remove old file if exists
        if (!empty($bill_receipt)) {
            $old_file_path = '../../storage/uploads/expenses/' . $bill_receipt;
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }
        
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
                UPDATE expenses 
                SET category_id = ?, amount = ?, description = ?, expense_date = ?, 
                    bill_receipt = ?, remarks = ?, payment_method = ?, status = ?
                WHERE id = ?
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
                $expense_id
            ]);
            
            if ($success) {
                $_SESSION['success_message'] = "Expense updated successfully!";
                header("Location: view.php?id=" . $expense_id);
                exit;
            } else {
                $errors[] = "Failed to update expense. Please try again.";
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
$page_title = "Edit Expense - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Expense</h1>
    <a href="view.php?id=<?php echo $expense['id']; ?>" class="btn btn-secondary">Back to View</a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Edit Expense Details</h6>
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
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) ? $_POST['category_id'] : $expense['category_id']) == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php 
                                    $next_category = next($categories);
                                    prev($categories);
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
                                   value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : $expense['amount']; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo isset($_POST['expense_date']) ? htmlspecialchars($_POST['expense_date']) : $expense['expense_date']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash" <?php echo (isset($_POST['payment_method']) ? $_POST['payment_method'] : $expense['payment_method']) == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) ? $_POST['payment_method'] : $expense['payment_method']) == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="digital_wallet" <?php echo (isset($_POST['payment_method']) ? $_POST['payment_method'] : $expense['payment_method']) == 'digital_wallet' ? 'selected' : ''; ?>>Digital Wallet</option>
                                <option value="credit_card" <?php echo (isset($_POST['payment_method']) ? $_POST['payment_method'] : $expense['payment_method']) == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="other" <?php echo (isset($_POST['payment_method']) ? $_POST['payment_method'] : $expense['payment_method']) == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Describe the expense..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : $expense['description']; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                  placeholder="Any additional remarks..."><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : $expense['remarks']; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bill_receipt" class="form-label">Bill/Receipt</label>
                            <input type="file" class="form-control" id="bill_receipt" name="bill_receipt" 
                                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <small class="text-muted">Supported formats: JPG, PNG, PDF, DOC (Max: 5MB)</small>
                            
                            <?php if (!empty($expense['bill_receipt'])): ?>
                            <div class="mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remove_bill" name="remove_bill">
                                    <label class="form-check-label text-danger" for="remove_bill">
                                        Remove current bill/receipt
                                    </label>
                                </div>
                                <small class="text-muted">Current file: <?php echo htmlspecialchars($expense['bill_receipt']); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="approved" <?php echo (isset($_POST['status']) ? $_POST['status'] : $expense['status']) == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo (isset($_POST['status']) ? $_POST['status'] : $expense['status']) == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="rejected" <?php echo (isset($_POST['status']) ? $_POST['status'] : $expense['status']) == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Expense
                        </button>
                        <a href="view.php?id=<?php echo $expense['id']; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Current Details</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th>Recorded:</th>
                        <td><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td><?php echo date('M d, Y', strtotime($expense['updated_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Type:</th>
                        <td><?php echo ucfirst(str_replace('_', ' ', $expense['parent_category'])); ?></td>
                    </tr>
                </table>
                
                <?php if (!empty($expense['bill_receipt'])): ?>
                <hr>
                <h6 class="text-primary">Current Bill/Receipt</h6>
                <?php
                $file_extension = pathinfo($expense['bill_receipt'], PATHINFO_EXTENSION);
                $file_url = getExpenseUrl($expense['bill_receipt']);
                
                if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                ?>
                    <img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Current Bill" class="img-fluid rounded mb-2" style="max-height: 150px;">
                <?php else: ?>
                    <div class="text-center py-2 border rounded">
                        <i class="fas fa-file fa-2x text-muted"></i>
                        <p class="small text-muted mb-0"><?php echo htmlspecialchars($expense['bill_receipt']); ?></p>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>