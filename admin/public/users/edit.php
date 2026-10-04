<?php
// public/users/edit.php
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
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? 'active';
    
    // Validate required fields
    if (empty($username) || empty($email) || empty($full_name)) {
        $_SESSION['error_message'] = "Username, email, and full name are required.";
    } else {
        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            $_SESSION['error_message'] = "Username or email already exists by another user.";
        } else {
            try {
                // Update user
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, role = ?, status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                
                if ($stmt->execute([$username, $email, $full_name, $phone, $role, $status, $_SESSION['admin_id'], $user_id])) {
                    $_SESSION['success_message'] = "User updated successfully!";
                    header("Location: ../users.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Failed to update user.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Set page title
$page_title = "Edit User - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit User</h1>
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
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <optgroup label="Administration & Management">
                                <option value="super_admin" <?php echo $role == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="system_administrator" <?php echo $role == 'system_administrator' ? 'selected' : ''; ?>>System Administrator</option>
                                <option value="hr_admin_officer" <?php echo $role == 'hr_admin_officer' ? 'selected' : ''; ?>>HR Admin Officer</option>
                            </optgroup>
                            
                            <optgroup label="Finance & Accounting">
                                <option value="finance_head_accountant" <?php echo $role == 'finance_head_accountant' ? 'selected' : ''; ?>>Finance Head Accountant</option>
                                <option value="accountant_main" <?php echo $role == 'accountant_main' ? 'selected' : ''; ?>>Accountant Main</option>
                                <option value="assistant_accountant" <?php echo $role == 'assistant_accountant' ? 'selected' : ''; ?>>Assistant Accountant</option>
                                <option value="auditor" <?php echo $role == 'auditor' ? 'selected' : ''; ?>>Auditor</option>
                                <option value="vendor_payment_officer" <?php echo $role == 'vendor_payment_officer' ? 'selected' : ''; ?>>Vendor Payment Officer</option>
                                <option value="billing_officer_cashier" <?php echo $role == 'billing_officer_cashier' ? 'selected' : ''; ?>>Billing Officer / Cashier</option>
                            </optgroup>
                            
                            <optgroup label="Product & Inventory">
                                <option value="product_manager" <?php echo $role == 'product_manager' ? 'selected' : ''; ?>>Product Manager</option>
                                <option value="inventory_officer" <?php echo $role == 'inventory_officer' ? 'selected' : ''; ?>>Inventory Officer</option>
                                <option value="procurement_officer" <?php echo $role == 'procurement_officer' ? 'selected' : ''; ?>>Procurement Officer</option>
                                <option value="quality_control_officer" <?php echo $role == 'quality_control_officer' ? 'selected' : ''; ?>>Quality Control Officer</option>
                            </optgroup>
                            
                            <optgroup label="Operations & Delivery">
                                <option value="order_manager" <?php echo $role == 'order_manager' ? 'selected' : ''; ?>>Order Manager</option>
                                <option value="delivery_manager" <?php echo $role == 'delivery_manager' ? 'selected' : ''; ?>>Delivery Manager</option>
                                <option value="delivery_rider" <?php echo $role == 'delivery_rider' ? 'selected' : ''; ?>>Delivery Rider</option>
                                <option value="pickup_coordinator" <?php echo $role == 'pickup_coordinator' ? 'selected' : ''; ?>>Pickup Coordinator</option>
                            </optgroup>
                            
                            <optgroup label="Farm & Supply Chain">
                                <option value="farm_manager" <?php echo $role == 'farm_manager' ? 'selected' : ''; ?>>Farm Manager</option>
                                <option value="farm_supervisor" <?php echo $role == 'farm_supervisor' ? 'selected' : ''; ?>>Farm Supervisor</option>
                                <option value="farmer_partner" <?php echo $role == 'farmer_partner' ? 'selected' : ''; ?>>Farmer Partner</option>
                                <option value="farm_supply_officer" <?php echo $role == 'farm_supply_officer' ? 'selected' : ''; ?>>Farm Supply Officer</option>
                                <option value="warehouse_staff" <?php echo $role == 'warehouse_staff' ? 'selected' : ''; ?>>Warehouse Staff</option>
                            </optgroup>
                            
                            <optgroup label="Customer Service & Marketing">
                                <option value="customer_support_officer" <?php echo $role == 'customer_support_officer' ? 'selected' : ''; ?>>Customer Support Officer</option>
                                <option value="marketing_manager" <?php echo $role == 'marketing_manager' ? 'selected' : ''; ?>>Marketing Manager</option>
                            </optgroup>
                            
                            <optgroup label="IT & Technical">
                                <option value="developer_it_technician" <?php echo $role == 'developer_it_technician' ? 'selected' : ''; ?>>Developer / IT Technician</option>
                                <option value="data_analyst" <?php echo $role == 'data_analyst' ? 'selected' : ''; ?>>Data Analyst</option>
                            </optgroup>
                            
                            <optgroup label="Support Staff">
                                <option value="security_officer" <?php echo $role == 'security_officer' ? 'selected' : ''; ?>>Security Officer</option>
                                <option value="office_assistant" <?php echo $role == 'office_assistant' ? 'selected' : ''; ?>>Office Assistant</option>
                                <option value="intern_trainee" <?php echo $role == 'intern_trainee' ? 'selected' : ''; ?>>Intern / Trainee</option>
                                <option value="cleaner_maintenance_staff" <?php echo $role == 'cleaner_maintenance_staff' ? 'selected' : ''; ?>>Cleaner / Maintenance Staff</option>
                                <option value="staff" <?php echo $role == 'staff' ? 'selected' : ''; ?>>General Staff</option>
                            </optgroup>
                        </select>
                        <div class="form-text">Select appropriate role based on user responsibilities</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <div class="form-text">User account status</div>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="../users.php" class="btn btn-secondary me-md-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Add search functionality to role dropdown
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const roleOptions = Array.from(roleSelect.options);
    
    // Create search input
    const searchDiv = document.createElement('div');
    searchDiv.className = 'mb-2';
    searchDiv.innerHTML = `
        <input type="text" id="roleSearch" class="form-control form-control-sm" placeholder="Search roles...">
    `;
    roleSelect.parentNode.insertBefore(searchDiv, roleSelect);
    
    const searchInput = document.getElementById('roleSearch');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        // Show all optgroups initially
        const optgroups = roleSelect.querySelectorAll('optgroup');
        optgroups.forEach(optgroup => {
            optgroup.style.display = '';
        });
        
        if (searchTerm) {
            let hasVisibleOptions = false;
            
            // Hide all options first
            roleOptions.forEach(option => {
                option.style.display = 'none';
            });
            
            // Show matching options
            roleOptions.forEach(option => {
                if (option.text.toLowerCase().includes(searchTerm)) {
                    option.style.display = '';
                    hasVisibleOptions = true;
                    
                    // Show the parent optgroup
                    const optgroup = option.parentElement;
                    if (optgroup.tagName === 'OPTGROUP') {
                        optgroup.style.display = '';
                    }
                }
            });
            
            // Hide empty optgroups
            optgroups.forEach(optgroup => {
                const visibleOptions = Array.from(optgroup.querySelectorAll('option')).filter(opt => 
                    opt.style.display !== 'none'
                );
                if (visibleOptions.length === 0) {
                    optgroup.style.display = 'none';
                }
            });
        } else {
            // Show all options when search is cleared
            roleOptions.forEach(option => {
                option.style.display = '';
            });
            optgroups.forEach(optgroup => {
                optgroup.style.display = '';
            });
        }
    });
});
</script>

<style>
.form-select optgroup {
    font-weight: bold;
    color: #6c757d;
}

.form-select option {
    padding-left: 20px;
}

#roleSearch {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>