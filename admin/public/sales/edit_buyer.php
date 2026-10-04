<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get buyer ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid buyer ID.";
    header("Location: ../sales.php");
    exit;
}

$buyer_id = intval($_GET['id']);

// Get buyer details
$buyer = $pdo->prepare("SELECT * FROM buyers WHERE buyer_id = ?");
$buyer->execute([$buyer_id]);
$buyer = $buyer->fetch();

if (!$buyer) {
    $_SESSION['error_message'] = "Buyer not found.";
    header("Location: ../sales.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $buyer_name = trim($_POST['buyer_name']);
        $contact_number1 = trim($_POST['contact_number1']);
        $contact_number2 = trim($_POST['contact_number2']) ?: null;
        $email = trim($_POST['email']) ?: null;
        $buyer_type = $_POST['buyer_type'];
        $original_address = trim($_POST['original_address']) ?: null;
        $current_address = trim($_POST['current_address']) ?: null;
        $business_address = trim($_POST['business_address']) ?: null;
        $special_rate = !empty($_POST['special_rate']) ? floatval($_POST['special_rate']) : null;
        $status = $_POST['status'];
        
        // Validate contact number
        if (!preg_match('/^[0-9]{10}$/', $contact_number1)) {
            throw new Exception("Primary contact number must be 10 digits.");
        }
        
        if ($contact_number2 && !preg_match('/^[0-9]{10}$/', $contact_number2)) {
            throw new Exception("Secondary contact number must be 10 digits.");
        }
        
        // Handle profile picture upload
        $profile_picture = $buyer['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../storage/uploads/buyers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                throw new Exception("Only JPG, JPEG, PNG, and GIF files are allowed.");
            }
            
            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                // Delete old profile picture if exists
                if ($profile_picture && file_exists($upload_dir . $profile_picture)) {
                    unlink($upload_dir . $profile_picture);
                }
                $profile_picture = $filename;
            }
        }
        
        // Update buyer
        $stmt = $pdo->prepare("
            UPDATE buyers SET 
            buyer_name = ?, contact_number1 = ?, contact_number2 = ?, email = ?, 
            profile_picture = ?, buyer_type = ?, original_address = ?, current_address = ?, 
            business_address = ?, special_rate = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE buyer_id = ?
        ");
        
        $stmt->execute([
            $buyer_name, $contact_number1, $contact_number2, $email, $profile_picture, 
            $buyer_type, $original_address, $current_address, $business_address, 
            $special_rate, $status, $buyer_id
        ]);
        
        $_SESSION['success_message'] = "Buyer updated successfully!";
        header("Location: view_buyer.php?id=" . $buyer_id);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Set page title
$page_title = "Edit Buyer - " . htmlspecialchars($buyer['buyer_name']);

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Buyer</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
        </a>
        <a href="view_buyer.php?id=<?php echo $buyer_id; ?>" class="btn btn-sm btn-info ms-2">
            <i class="fas fa-eye me-1"></i> View Buyer
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
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="buyer_name" class="form-label">Buyer Name *</label>
                        <input type="text" class="form-control" id="buyer_name" name="buyer_name" 
                               value="<?php echo htmlspecialchars($buyer['buyer_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number1" class="form-label">Primary Contact Number *</label>
                        <input type="text" class="form-control" id="contact_number1" name="contact_number1" 
                               pattern="[0-9]{10}" title="10-digit contact number" 
                               value="<?php echo htmlspecialchars($buyer['contact_number1']); ?>" required>
                        <small class="form-text text-muted">10-digit number (e.g., 9841234567)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number2" class="form-label">Secondary Contact Number</label>
                        <input type="text" class="form-control" id="contact_number2" name="contact_number2"
                               pattern="[0-9]{10}" title="10-digit contact number"
                               value="<?php echo htmlspecialchars($buyer['contact_number2'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($buyer['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="buyer_type" class="form-label">Buyer Type *</label>
                        <select class="form-select" id="buyer_type" name="buyer_type" required>
                            <option value="small" <?php echo $buyer['buyer_type'] == 'small' ? 'selected' : ''; ?>>Small</option>
                            <option value="medium" <?php echo $buyer['buyer_type'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="large" <?php echo $buyer['buyer_type'] == 'large' ? 'selected' : ''; ?>>Large</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Profile Picture</label>
                        <?php if (!empty($buyer['profile_picture'])): ?>
                        <div class="mb-2">
                            <img src="../../storage/uploads/buyers/<?php echo htmlspecialchars($buyer['profile_picture']); ?>" 
                                 alt="Current Profile Picture" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                            <br>
                            <small class="text-muted">Current profile picture</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        <small class="form-text text-muted">Supported formats: JPG, JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_rate" class="form-label">Special Rate (Rs. per kg)</label>
                        <input type="number" step="0.01" class="form-control" id="special_rate" name="special_rate"
                               value="<?php echo $buyer['special_rate'] ?? ''; ?>">
                        <small class="form-text text-muted">Leave empty to use standard rates</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $buyer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $buyer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="original_address" class="form-label">Original Address</label>
                        <textarea class="form-control" id="original_address" name="original_address" rows="2"><?php echo htmlspecialchars($buyer['original_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_address" class="form-label">Current Address</label>
                        <textarea class="form-control" id="current_address" name="current_address" rows="2"><?php echo htmlspecialchars($buyer['current_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="business_address" class="form-label">Business/Delivery Address</label>
                        <textarea class="form-control" id="business_address" name="business_address" rows="3"><?php echo htmlspecialchars($buyer['business_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Update Buyer</button>
                    <a href="view_buyer.php?id=<?php echo $buyer_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Client-side validation for contact numbers
document.getElementById('contact_number1').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

document.getElementById('contact_number2').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>