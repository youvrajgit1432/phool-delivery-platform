<?php
// public/users/edit_personal.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication - allow users to edit their own details
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error_message'] = "Please login to continue.";
    header("Location: ../auth/login.php");
    exit;
}

// Initialize database
$pdo = getDBConnection();

// Get user ID - allow users to edit their own profile or admins to edit others
if (isset($_GET['id']) && in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    // Admin editing another user
    $id = intval($_GET['id']);
    $is_editing_self = ($id == $_SESSION['admin_id']);
} else {
    // User editing their own profile
    $id = $_SESSION['admin_id'];
    $is_editing_self = true;
}

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

// Check permissions - users can only edit their own details unless they're admin
if (!$is_editing_self && !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    $_SESSION['error_message'] = "You don't have permission to edit other users' details.";
    header("Location: ../users.php");
    exit;
}

// Initialize variables with user data
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$full_name = $user['full_name'] ?? '';
$phone = $user['phone'] ?? '';
$role = $user['role'] ?? 'staff';
$status = $user['status'] ?? 'active';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate required fields
    if (empty($username) || empty($email) || empty($full_name)) {
        $_SESSION['error_message'] = "Username, email, and full name are required.";
    } else {
        // Check if fields actually changed to avoid unnecessary duplicate checks
        $username_changed = ($username !== $user['username']);
        $email_changed = ($email !== $user['email']);
        
        $has_duplicate = false;
        $duplicate_message = '';
        
        // Only check for duplicates if the field actually changed
        if ($username_changed) {
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            $existing_username = $stmt->fetch();
            
            if ($existing_username) {
                $has_duplicate = true;
                $duplicate_message = "Username '{$username}' already exists by another user.";
            }
        }
        
        if (!$has_duplicate && $email_changed) {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            $existing_email = $stmt->fetch();
            
            if ($existing_email) {
                $has_duplicate = true;
                $duplicate_message = "Email '{$email}' already exists by another user.";
            }
        }
        
        if ($has_duplicate) {
            $_SESSION['error_message'] = $duplicate_message;
        } else {
            try {
                // Update user - only personal details, no role changes
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                
                if ($stmt->execute([$username, $email, $full_name, $phone, $_SESSION['admin_id'], $user_id])) {
                    $_SESSION['success_message'] = "User details updated successfully!";
                    
                    // If user is editing themselves, update session data
                    if ($is_editing_self) {
                        $_SESSION['admin_username'] = $username;
                        $_SESSION['admin_full_name'] = $full_name;
                    }
                    
                    // Redirect based on who is editing
                    if ($is_editing_self) {
                        header("Location: ../dashboard.php");
                    } else {
                        header("Location: ../users.php");
                    }
                    exit;
                } else {
                    $_SESSION['error_message'] = "Failed to update user details.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Set page title
$page_title = $is_editing_self ? "Edit My Profile - Phool Delivery" : "Edit User Details - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo $is_editing_self ? 'Edit My Profile' : 'Edit User Details'; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($is_editing_self): ?>
            <a href="../dashboard.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        <?php else: ?>
            <a href="../users.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Users
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
            
            <?php if (!$is_editing_self): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                You are editing details for <strong><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></strong>. 
                Role changes are not allowed on this page. Use the "Edit Role" option for role management.
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" required>
                        <div class="form-text">Unique username for login</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <div class="form-text">User's email address</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($full_name); ?>" required>
                        <div class="form-text">User's complete name</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($phone); ?>">
                        <div class="form-text">Contact number (optional)</div>
                    </div>
                </div>
            </div>
            
            <!-- Display current role and status (read-only) -->
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Current Role</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $role)); ?>" readonly>
                        <div class="form-text">Role cannot be changed on this page</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($status); ?>" readonly>
                        <div class="form-text">Status cannot be changed on this page</div>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <?php if ($is_editing_self): ?>
                    <a href="../dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                <?php else: ?>
                    <a href="../users.php" class="btn btn-secondary me-md-2">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update Details
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>