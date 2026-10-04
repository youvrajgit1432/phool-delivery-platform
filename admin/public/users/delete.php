<?php
// public/users/delete.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication and admin role
requireRole(['super_admin', 'admin']);

// Initialize database
$pdo = getDBConnection();

// Get user ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists
if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: ../users.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    // Verify user still exists before deletion
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $check_stmt->execute([$user_id]);
    $user_exists = $check_stmt->fetch();
    
    if (!$user_exists) {
        $_SESSION['error_message'] = "User not found or already deleted.";
        header("Location: ../users.php");
        exit;
    }
    
    // Prevent self-deletion
    if ($user_id == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: ../users.php");
        exit;
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            
            if ($stmt->execute([$user_id])) {
                // Check if any row was actually deleted
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "User deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user. User may have been already deleted.";
                }
                header("Location: ../users.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to delete user. Database error occurred.";
                header("Location: ../users.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            header("Location: ../users.php");
            exit;
        }
    }
}

// Initialize variables with user data
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$full_name = $user['full_name'] ?? '';
$role = $user['role'] ?? '';

// Set page title
$page_title = "Delete User - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete User</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../users.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Users
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
        <form method="POST" action="" id="deleteForm">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
            
            <div class="alert alert-danger">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Warning!</h4>
                <p>Are you sure you want to delete the following user? This action cannot be undone.</p>
                <hr>
                <p class="mb-0"><strong>Note:</strong> All user data and records associated with this account will be permanently removed.</p>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <p class="form-control-plaintext border-bottom pb-2"><?php echo htmlspecialchars($username); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <p class="form-control-plaintext border-bottom pb-2"><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <p class="form-control-plaintext border-bottom pb-2"><?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <p class="form-control-plaintext border-bottom pb-2"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">User ID</label>
                        <p class="form-control-plaintext border-bottom pb-2">#<?php echo htmlspecialchars($user['id'] ?? ''); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Created</label>
                        <p class="form-control-plaintext border-bottom pb-2"><?php echo date('M d, Y', strtotime($user['created_at'] ?? '')); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="../users.php" class="btn btn-secondary me-md-2">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-danger" id="deleteButton">
                    <i class="fas fa-trash me-1"></i> Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteForm = document.getElementById('deleteForm');
    const deleteButton = document.getElementById('deleteButton');
    
    deleteForm.addEventListener('submit', function(e) {
        const confirmed = confirm('Are you absolutely sure you want to delete this user? This action cannot be undone.');
        if (!confirmed) {
            e.preventDefault();
        }
    });
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>