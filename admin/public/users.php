<?php
// public/users.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication and admin role
requireRole(['super_admin', 'admin', 'manager']);

// Initialize database
$pdo = getDBConnection();

// Get all users
$users = $pdo->query("SELECT u.*, creator.full_name as created_by_name, updater.full_name as updated_by_name 
                     FROM users u 
                     LEFT JOIN users creator ON u.created_by = creator.id 
                     LEFT JOIN users updater ON u.updated_by = updater.id 
                     ORDER BY u.created_at DESC")->fetchAll();

// Set page title
$page_title = "Users Management - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Users Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (in_array($_SESSION['admin_role'], ['super_admin', 'admin'])): ?>
        <a href="users/add.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add New User
        </a>
        <?php endif; ?>
    </div>
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

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Password Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td>
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
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($user['status'] ?? 'active') {
                                case 'active': echo 'success'; break;
                                case 'inactive': echo 'warning'; break;
                                case 'suspended': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($user['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo ($user['default_password_used'] ?? false) ? 'warning' : 'success'; ?>">
                                <?php echo ($user['default_password_used'] ?? false) ? 'Default Password' : 'Custom Password'; ?>
                            </span>
                        </td>
                        <td><?php echo ($user['last_login'] ?? '') ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <!-- Edit Personal Details button -->
                                <a href="users/edit_personal.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary" title="Edit Personal Details">
                                    <i class="fas fa-user-edit"></i>
                                </a>
                                
                                <!-- Edit Role button (admins only) -->
                                <?php if (in_array($_SESSION['admin_role'], ['super_admin', 'admin']) && $user['id'] != $_SESSION['admin_id']): ?>
                                <a href="users/edit_role.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-info" title="Edit Role">
                                    <i class="fas fa-user-tag"></i>
                                </a>
                                <?php endif; ?>
                                
                                <!-- Delete button (admins only) -->
                                <?php if (in_array($_SESSION['admin_role'], ['super_admin', 'admin']) && $user['id'] != $_SESSION['admin_id']): ?>
                                <a href="users/delete.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                
                                <!-- Reset Password button -->
                                <?php if (canResetPassword($_SESSION['admin_role'], $user['role'] ?? 'staff') && $user['id'] != $_SESSION['admin_id']): ?>
                                <a href="users/reset_password.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-warning" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>