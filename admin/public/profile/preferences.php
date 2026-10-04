<?php
// public/profile/preferences.php
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
    $notify_new_orders = isset($_POST['notify_new_orders']) ? 1 : 0;
    $notify_low_stock = isset($_POST['notify_low_stock']) ? 1 : 0;
    $notify_payments = isset($_POST['notify_payments']) ? 1 : 0;
    $language = $_POST['language'];
    $timezone = $_POST['timezone'];
    
    // Update user preferences
    $stmt = $pdo->prepare("UPDATE users SET notify_new_orders = ?, notify_low_stock = ?, notify_payments = ?, language = ?, timezone = ? WHERE id = ?");
    $success = $stmt->execute([$notify_new_orders, $notify_low_stock, $notify_payments, $language, $timezone, $_SESSION['admin_id']]);
    
    if ($success) {
        $_SESSION['success_message'] = "Preferences updated successfully!";
        header("Location: ../profile.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to update preferences.";
    }
}

// Set page title
$page_title = "Notification Preferences - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Notification Preferences</h1>
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
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notify_new_orders" name="notify_new_orders" 
                        <?php echo ($current_user['notify_new_orders'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notify_new_orders">Notify me about new orders</label>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notify_low_stock" name="notify_low_stock" 
                        <?php echo ($current_user['notify_low_stock'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notify_low_stock">Notify me about low stock alerts</label>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notify_payments" name="notify_payments" 
                        <?php echo ($current_user['notify_payments'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notify_payments">Notify me about payment updates</label>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="language" class="form-label">Language</label>
                    <select class="form-select" id="language" name="language">
                        <option value="en" <?php echo ($current_user['language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="ne" <?php echo ($current_user['language'] ?? 'en') == 'ne' ? 'selected' : ''; ?>>Nepali</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select class="form-select" id="timezone" name="timezone">
                        <option value="Asia/Kathmandu" <?php echo ($current_user['timezone'] ?? 'Asia/Kathmandu') == 'Asia/Kathmandu' ? 'selected' : ''; ?>>Asia/Kathmandu (Nepal Time)</option>
                        <option value="UTC" <?php echo ($current_user['timezone'] ?? 'Asia/Kathmandu') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Preferences</button>
                <a href="../profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>