<?php
// public/expense/view.php
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
    SELECT e.*, ec.name as category_name, ec.parent_category, ec.description as category_desc,
           u.full_name as recorded_by_name, u.email as recorded_by_email
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN users u ON e.recorded_by = u.id
    WHERE e.id = ?
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if (!$expense) {
    $_SESSION['error_message'] = "Expense record not found.";
    header("Location: index.php");
    exit;
}

// Set page title
$page_title = "View Expense - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Expense Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="edit.php?id=<?php echo $expense['id']; ?>" class="btn btn-primary me-2">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Basic Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Expense Date:</th>
                                <td><?php echo date('F d, Y', strtotime($expense['expense_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Category:</th>
                                <td>
                                    <span class="badge bg-<?php echo $expense['parent_category'] == 'phool_delivery' ? 'info' : 'success'; ?>">
                                        <?php echo htmlspecialchars($expense['category_name']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td>
                                    <span class="badge bg-<?php echo $expense['parent_category'] == 'phool_delivery' ? 'primary' : 'success'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $expense['parent_category'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Amount:</th>
                                <td class="h5 text-danger fw-bold">Rs. <?php echo number_format($expense['amount'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Payment Method:</th>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($expense['status']) {
                                            case 'approved': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'rejected': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($expense['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Recorded By:</th>
                                <td><?php echo htmlspecialchars($expense['recorded_by_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Recorded On:</th>
                                <td><?php echo date('M d, Y h:i A', strtotime($expense['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($expense['description'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="text-primary">Description</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($expense['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($expense['remarks'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="text-primary">Remarks</h6>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($expense['remarks'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($expense['bill_receipt'])): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Bill/Receipt</h6>
            </div>
            <div class="card-body text-center">
                <?php
                $file_extension = pathinfo($expense['bill_receipt'], PATHINFO_EXTENSION);
                $file_url = getExpenseUrl($expense['bill_receipt']);
                
                if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                ?>
                    <img src="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Expense Bill" class="img-fluid rounded" style="max-height: 400px;">
                    <div class="mt-3">
                        <a href="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-download me-1"></i> Download Image
                        </a>
                    </div>
                <?php else: ?>
                    <div class="py-4">
                        <i class="fas fa-file fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Document: <?php echo htmlspecialchars($expense['bill_receipt']); ?></p>
                        <a href="<?php echo htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-download me-1"></i> Download Document
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Category Information</h6>
            </div>
            <div class="card-body">
                <h6 class="text-primary"><?php echo htmlspecialchars($expense['category_name']); ?></h6>
                <p class="small text-muted">
                    <?php echo !empty($expense['category_desc']) ? htmlspecialchars($expense['category_desc']) : 'No description available.'; ?>
                </p>
                
                <hr>
                
                <h6 class="text-primary">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo $expense['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> Edit Expense
                    </a>
                    <?php if ($_SESSION['admin_role'] == 'super_admin' || $_SESSION['admin_role'] == 'admin'): ?>
                    <a href="delete.php?id=<?php echo $expense['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this expense record? This action cannot be undone.')">
                        <i class="fas fa-trash me-1"></i> Delete Expense
                    </a>
                    <?php endif; ?>
                    <a href="index.php?category=<?php echo $expense['category_id']; ?>" class="btn btn-outline-info">
                        <i class="fas fa-list me-1"></i> View Similar Expenses
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Recent Similar Expenses</h6>
            </div>
            <div class="card-body">
                <?php
                $recent_similar = $pdo->prepare("
                    SELECT expense_date, amount, description 
                    FROM expenses 
                    WHERE category_id = ? AND id != ? 
                    ORDER BY expense_date DESC 
                    LIMIT 5
                ");
                $recent_similar->execute([$expense['category_id'], $expense['id']]);
                $similar_expenses = $recent_similar->fetchAll();
                
                if ($similar_expenses): 
                    foreach ($similar_expenses as $similar): 
                ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted"><?php echo date('M d', strtotime($similar['expense_date'])); ?></small>
                            <small class="fw-bold text-danger">Rs. <?php echo number_format($similar['amount'], 2); ?></small>
                        </div>
                        <small class="text-truncate d-block"><?php echo htmlspecialchars(substr($similar['description'], 0, 50)); ?></small>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <p class="text-muted small">No similar expenses found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>