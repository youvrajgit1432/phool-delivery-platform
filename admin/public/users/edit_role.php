<?php
// public/users/edit_role.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication and admin role - only super_admin and admin can edit roles
requireRole(['super_admin', 'admin']);

// Initialize database
$pdo = getDBConnection();

// Get user ID from query parameter
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    $_SESSION['error_message'] = "User ID is required";
    header('Location: ../users.php');
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error_message'] = "User not found";
    header('Location: ../users.php');
    exit;
}

// Prevent editing own role
if ($user['id'] == $_SESSION['admin_id']) {
    $_SESSION['error_message'] = "You cannot edit your own role";
    header('Location: ../users.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $new_role = $_POST['role'] ?? '';
        
        // Validate role
        $allowed_roles = ['super_admin', 'admin', 'manager', 'staff'];
        if (!in_array($new_role, $allowed_roles)) {
            throw new Exception("Invalid role selected");
        }
        
        // Prevent non-super_admin from assigning super_admin role
        if ($_SESSION['admin_role'] !== 'super_admin' && $new_role === 'super_admin') {
            throw new Exception("Only super admin can assign super admin role");
        }
        
        // Update user role
        $update_stmt = $pdo->prepare("UPDATE users SET role = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$new_role, $_SESSION['admin_id'], $user_id]);
        
        $_SESSION['success_message'] = "User role updated successfully";
        header('Location: ../users.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Set page title
$page_title = "Edit User Role - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit User Role</h1>
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

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Change Role for <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Current User Information</label>
                        <div class="form-control bg-light">
                            <strong>Username:</strong> <?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?><br>
                            <strong>Current Role:</strong> 
                            <span class="badge bg-<?php 
                                switch($user['role'] ?? 'staff') {
                                    case 'super_admin': echo 'danger'; break;
                                    case 'admin': echo 'primary'; break;
                                    case 'manager': echo 'info'; break;
                                    case 'staff': echo 'secondary'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'] ?? 'staff')); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Select New Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Choose a role...</option>
                            <option value="staff" <?php echo ($user['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                            <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="super_admin" <?php echo ($user['role'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <?php else: ?>
                            <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">
                            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                You can assign any role including Super Admin.
                            <?php else: ?>
                                You can assign Staff, Manager, or Admin roles. Only Super Admin can assign Super Admin role.
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Changing user roles may affect their permissions and access to system features.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../users.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>