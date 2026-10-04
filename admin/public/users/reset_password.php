<?php
// public/users/reset_password.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication and admin role
requireRole(['super_admin', 'admin', 'manager']);

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

// Initialize variables with user data
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$full_name = $user['full_name'] ?? '';
$role = $user['role'] ?? '';

// Check permission to reset password
if (!canResetPassword($_SESSION['admin_role'], $role) || ($user['id'] ?? 0) == $_SESSION['admin_id']) {
    $_SESSION['error_message'] = "You don't have permission to reset this user's password.";
    header("Location: ../users.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    // Reset password to default
    $default_password = "DemoReset";
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, default_password_used = 1, password_changed_at = NULL, updated_by = ? WHERE id = ?");
    
    if ($stmt->execute([$hashed_password, $_SESSION['admin_id'], $user_id])) {
        $_SESSION['success_message'] = "Password reset successfully! Default password: DemoReset";
        header("Location: ../users.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to reset password.";
    }
}

// Set page title
$page_title = "Reset Password - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Reset Password</h1>
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
        <form method="POST" action="">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
            
            <div class="alert alert-warning">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Password Reset</h4>
                <p>Are you sure you want to reset the password for user <strong><?php echo htmlspecialchars($full_name); ?></strong>?</p>
                <p>The password will be reset to the default value: <strong>DemoReset</strong></p>
                <p class="mb-0">The user will be required to change their password after logging in.</p>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($username); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <p class="form-control-plaintext fw-bold"><?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <p class="form-control-plaintext fw-bold"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="../users.php" class="btn btn-secondary me-md-2">Cancel</a>
                <button type="submit" class="btn btn-warning">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>