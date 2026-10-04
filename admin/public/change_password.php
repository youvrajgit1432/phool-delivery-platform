<?php
// public/change_password.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// User must be logged in but using default password
if (!isset($_SESSION['default_password_used']) || $_SESSION['default_password_used'] !== true) {
    header("Location: index.php");
    exit;
}

// Initialize database
$pdo = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        // Verify current password (should be default password)
        $default_password = "Adphool1432@@";
        
        if (!password_verify($default_password, $current_password)) {
            // Get actual current password from database
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect.';
            }
        }
        
        if (empty($error)) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, default_password_used = 0, password_changed_at = NOW() WHERE id = ?");
            
            if ($stmt->execute([$hashed_password, $_SESSION['admin_id']])) {
                // Update session
                $_SESSION['default_password_used'] = false;
                $_SESSION['success_message'] = 'Password changed successfully!';
                header("Location: index.php");
                exit;
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}

$page_title = "Change Password - Phool Delivery Admin";
include '../app/views/layouts/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Change Your Password</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Security Notice:</strong> You are currently using the default password. Please change your password to secure your account.
                    </div>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <div class="form-text">Your current default password</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../app/views/layouts/footer.php'; ?>