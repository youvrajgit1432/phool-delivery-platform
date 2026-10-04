<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

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
        
        // Validate contact number (Nepal format)
        if (!preg_match('/^[0-9]{10}$/', $contact_number1)) {
            throw new Exception("Primary contact number must be 10 digits.");
        }
        
        if ($contact_number2 && !preg_match('/^[0-9]{10}$/', $contact_number2)) {
            throw new Exception("Secondary contact number must be 10 digits.");
        }
        
        // Handle profile picture upload
        $profile_picture = null;
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
                $profile_picture = $filename;
            }
        }
        
        // Insert new buyer
        $stmt = $pdo->prepare("
            INSERT INTO buyers 
            (buyer_name, contact_number1, contact_number2, email, profile_picture, buyer_type, original_address, current_address, business_address, special_rate, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $buyer_name, $contact_number1, $contact_number2, $email, $profile_picture, 
            $buyer_type, $original_address, $current_address, $business_address, $special_rate, $status
        ]);
        
        $_SESSION['success_message'] = "Buyer added successfully!";
        header("Location: ../sales.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Set page title
$page_title = "Add Buyer - Sales Management";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Buyer</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../sales.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Sales
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
                        <input type="text" class="form-control" id="buyer_name" name="buyer_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number1" class="form-label">Primary Contact Number *</label>
                        <input type="text" class="form-control" id="contact_number1" name="contact_number1" 
                               pattern="[0-9]{10}" title="10-digit contact number" required>
                        <small class="form-text text-muted">10-digit number (e.g., 9841234567)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number2" class="form-label">Secondary Contact Number</label>
                        <input type="text" class="form-control" id="contact_number2" name="contact_number2"
                               pattern="[0-9]{10}" title="10-digit contact number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="buyer_type" class="form-label">Buyer Type *</label>
                        <select class="form-select" id="buyer_type" name="buyer_type" required>
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        <small class="form-text text-muted">Supported formats: JPG, JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_rate" class="form-label">Special Rate (Rs. per kg)</label>
                        <input type="number" step="0.01" class="form-control" id="special_rate" name="special_rate">
                        <small class="form-text text-muted">Leave empty to use standard rates</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="original_address" class="form-label">Original Address</label>
                        <textarea class="form-control" id="original_address" name="original_address" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_address" class="form-label">Current Address</label>
                        <textarea class="form-control" id="current_address" name="current_address" rows="2"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="business_address" class="form-label">Business/Delivery Address</label>
                        <textarea class="form-control" id="business_address" name="business_address" rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Add Buyer</button>
                    <a href="../sales.php" class="btn btn-secondary">Cancel</a>
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