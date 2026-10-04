<?php
// public/profile.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get current user details with preferences
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_user = $stmt->fetch();

// Set page title
$page_title = "My Profile - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Profile</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Profile Picture</h5>
            </div>
            <div class="card-body text-center">
                <!-- FIXED: Use getProfilePictureUrl which handles both environments -->
                <img src="<?php echo getProfilePictureUrl($current_user['profile_picture']); ?>" 
                     alt="Profile Picture" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                
                <a href="profile/upload-picture.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-camera me-1"></i> Change Picture
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Personal Information</h5>
                <a href="profile/edit.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold">Full Name:</div>
                    <div class="col-sm-8"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold">Email:</div>
                    <div class="col-sm-8"><?php echo htmlspecialchars($current_user['email']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold">Phone:</div>
                    <div class="col-sm-8"><?php echo !empty($current_user['phone']) ? htmlspecialchars($current_user['phone']) : 'Not set'; ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold">Username:</div>
                    <div class="col-sm-8"><?php echo htmlspecialchars($current_user['username']); ?></div>
                </div>
                <div class="row">
                    <div class="col-sm-4 fw-bold">Role:</div>
                    <div class="col-sm-8"><?php echo ucfirst(str_replace('_', ' ', $current_user['role'])); ?></div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Security</h5>
                <a href="profile/change-password.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-key me-1"></i> Change Password
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4 fw-bold">Password:</div>
                    <div class="col-sm-8">••••••••</div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notification Preferences</h5>
                <a href="profile/preferences.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-cog me-1"></i> Edit Preferences
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-6 fw-bold">New Orders:</div>
                    <div class="col-sm-6"><?php echo ($current_user['notify_new_orders'] ?? 1) ? 'Enabled' : 'Disabled'; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-6 fw-bold">Low Stock Alerts:</div>
                    <div class="col-sm-6"><?php echo ($current_user['notify_low_stock'] ?? 1) ? 'Enabled' : 'Disabled'; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-6 fw-bold">Payment Updates:</div>
                    <div class="col-sm-6"><?php echo ($current_user['notify_payments'] ?? 1) ? 'Enabled' : 'Disabled'; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-6 fw-bold">Language:</div>
                    <div class="col-sm-6"><?php echo ($current_user['language'] ?? 'en') == 'en' ? 'English' : 'Nepali'; ?></div>
                </div>
                <div class="row">
                    <div class="col-sm-6 fw-bold">Timezone:</div>
                    <div class="col-sm-6"><?php echo ($current_user['timezone'] ?? 'Asia/Kathmandu') == 'Asia/Kathmandu' ? 'Nepal Time' : 'UTC'; ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Last Login:</div>
                    <div class="col-sm-8"><?php echo $current_user['last_login'] ? date('M d, Y H:i', strtotime($current_user['last_login'])) : 'Never'; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Account Created:</div>
                    <div class="col-sm-8"><?php echo date('M d, Y', strtotime($current_user['created_at'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Status:</div>
                    <div class="col-sm-8">
                        <span class="badge bg-<?php echo $current_user['status'] == 'active' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($current_user['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>