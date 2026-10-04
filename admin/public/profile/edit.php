<?php
// public/profile/edit.php
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
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validate input
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
    }
    
    // Check if email already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $_SESSION['admin_id']]);
    if ($stmt->fetch()) {
        $errors[] = "Email already exists.";
    }
    
    if (empty($errors)) {
        // Update user profile
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $success = $stmt->execute([$full_name, $email, $phone, $_SESSION['admin_id']]);
        
        if ($success) {
            $_SESSION['success_message'] = "Profile updated successfully!";
            $_SESSION['admin_name'] = $full_name;
            $_SESSION['admin_email'] = $email;
            header("Location: ../profile.php");
            exit;
        } else {
            $errors[] = "Failed to update profile.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Edit Profile - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Personal Information</h1>
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" 
                           value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                    <small class="text-muted">Username cannot be changed</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <input type="text" class="form-control" id="role" 
                       value="<?php echo ucfirst(str_replace('_', ' ', $current_user['role'])); ?>" disabled>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="../profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>