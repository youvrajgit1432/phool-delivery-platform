<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Add New Wallet QR";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet_type = $_POST['wallet_type'];
    $status = $_POST['status'];
    $qr_image = '';
    $upload_success = true;
    $error_message = '';
    
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../storage/uploads/wallet_qr/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['qr_image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                $upload_success = false;
            }
            
            if ($_FILES['qr_image']['size'] > 2097152) {
                $error_message = "File size too large. Maximum size is 2MB.";
                $upload_success = false;
            }
            
            if ($upload_success) {
                $file_extension = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
                $file_name = 'qr_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $file_path)) {
                    $qr_image = $file_name;
                } else {
                    $error_message = "Failed to upload file.";
                    $upload_success = false;
                }
            }
        } else {
            $error_message = "Upload error: " . $_FILES['qr_image']['error'];
            $upload_success = false;
        }
    } else {
        $error_message = "QR code image is required.";
        $upload_success = false;
    }
    
    if ($upload_success) {
        try {
            // Check if a QR code already exists for this wallet type
            $check_stmt = $pdo->prepare("SELECT id, qr_image FROM wallet_qrs WHERE wallet_type = ?");
            $check_stmt->execute([$wallet_type]);
            $existing_qr = $check_stmt->fetch();
            
            if ($existing_qr) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE wallet_qrs SET qr_image = ?, status = ?, updated_at = NOW() WHERE wallet_type = ?");
                $stmt->execute([$qr_image, $status, $wallet_type]);
                
                // Delete the old image file
                if (!empty($existing_qr['qr_image'])) {
                    $old_file_path = $upload_dir . $existing_qr['qr_image'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                
                $_SESSION['success_message'] = "Wallet QR updated successfully!";
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO wallet_qrs (wallet_type, qr_image, status) VALUES (?, ?, ?)");
                $stmt->execute([$wallet_type, $qr_image, $status]);
                $_SESSION['success_message'] = "Wallet QR added successfully!";
            }
            
            header("Location: ../pay.php");
            exit();
        } catch (PDOException $e) {
            if (!empty($qr_image)) {
                $file_path = $upload_dir . $qr_image;
                if (file_exists($file_path)) unlink($file_path);
            }
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add/Update Wallet QR</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../wallet_qr.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Wallet QR List
        </a>
    </div>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Wallet Type *</label>
                        <select class="form-select" name="wallet_type" required>
                            <option value="">Select Type</option>
                            <option value="esewa" <?php echo (isset($_POST['wallet_type']) && $_POST['wallet_type'] == 'esewa') ? 'selected' : ''; ?>>eSewa</option>
                            <option value="khalti" <?php echo (isset($_POST['wallet_type']) && $_POST['wallet_type'] == 'khalti') ? 'selected' : ''; ?>>Khalti</option>
                            <option value="bank" <?php echo (isset($_POST['wallet_type']) && $_POST['wallet_type'] == 'bank') ? 'selected' : ''; ?>>Bank Account</option>
                            <option value="other" <?php echo (isset($_POST['wallet_type']) && $_POST['wallet_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : 'selected'; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">QR Code Image *</label>
                        <input type="file" class="form-control" name="qr_image" accept="image/jpeg,image/png,image/gif" required>
                        <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF. Max size: 2MB</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Note:</strong> Only one QR code is allowed per wallet type. Uploading a new image for an existing wallet type will replace the previous one.
                    </div>
                    
                    <div id="imagePreview" class="mt-2" style="display: none;">
                        <p>Image Preview:</p>
                        <img id="preview" src="#" alt="QR Code Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" name="add_wallet" class="btn btn-primary">Save Wallet QR</button>
                    <a href="../wallet_qr.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Image preview functionality
document.querySelector('input[name="qr_image"]').addEventListener('change', function() {
    const file = this.files[0];
    const preview = document.getElementById('preview');
    const imagePreview = document.getElementById('imagePreview');
    
    if (file) {
        const reader = new FileReader();
        
        reader.addEventListener('load', function() {
            preview.src = reader.result;
            imagePreview.style.display = 'block';
        });
        
        reader.readAsDataURL(file);
    } else {
        imagePreview.style.display = 'none';
    }
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>