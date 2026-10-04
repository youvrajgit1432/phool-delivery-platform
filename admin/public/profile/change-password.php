<?php
// public/profile/change-password.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_user = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate current password
    if (!password_verify($current_password, $current_user['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    if (empty($errors)) {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $success = $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
        
        if ($success) {
            $_SESSION['success_message'] = "Password changed successfully!";
            header("Location: ../profile.php");
            exit;
        } else {
            $errors[] = "Failed to change password.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Change Password - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Change Password</h1>
    <a href="../profile.php" class="btn btn-secondary">Back to Profile</a>
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
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password *</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password *</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required 
                       minlength="6" placeholder="At least 6 characters">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Change Password</button>
                <a href="../profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>