<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid wallet ID.";
    header("Location: ../pay.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch the wallet data
$stmt = $pdo->prepare("SELECT * FROM wallet_qrs WHERE id = ?");
$stmt->execute([$id]);
$wallet = $stmt->fetch();

if (!$wallet) {
    $_SESSION['error_message'] = "Wallet not found.";
    header("Location: ../pay.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet_type = $_POST['wallet_type'];
    $status = $_POST['status'];
    
    $qr_image = $wallet['qr_image'];
    $upload_success = true;
    $error_message = '';
    
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../storage/uploads/wallet_qr/';
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
                // Remove old image
                if (!empty($qr_image) && file_exists($upload_dir . $qr_image)) {
                    unlink($upload_dir . $qr_image);
                }
                
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
    }
    
    if ($upload_success) {
        try {
            $stmt = $pdo->prepare("UPDATE wallet_qrs SET wallet_type = ?, qr_image = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$wallet_type, $qr_image, $status, $id]);
            $_SESSION['success_message'] = "Wallet QR updated successfully!";
            header("Location: ../pay.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Edit Wallet QR";
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Wallet QR</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../pay.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Payment Methods
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
                            <option value="esewa" <?php echo ($wallet['wallet_type'] == 'esewa') ? 'selected' : ''; ?>>eSewa</option>
                            <option value="khalti" <?php echo ($wallet['wallet_type'] == 'khalti') ? 'selected' : ''; ?>>Khalti</option>
                            <option value="bank" <?php echo ($wallet['wallet_type'] == 'bank') ? 'selected' : ''; ?>>Bank Account</option>
                            <option value="other" <?php echo ($wallet['wallet_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="active" <?php echo ($wallet['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($wallet['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">QR Code Image</label>
                        <input type="file" class="form-control" name="qr_image" accept="image/jpeg,image/png,image/gif">
                        <small class="form-text text-muted">Leave empty to keep current image. Accepted formats: JPG, PNG, GIF. Max size: 2MB</small>
                    </div>
                    
                    <?php if (!empty($wallet['qr_image'])): ?>
                    <div class="mt-2">
                        <p>Current Image:</p>
                        <img src="../../storage/uploads/wallet_qr/<?php echo $wallet['qr_image']; ?>" 
                             class="img-thumbnail" 
                             style="max-width: 200px; max-height: 200px; object-fit: contain;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" name="edit_wallet" class="btn btn-primary">Update Wallet QR</button>
                    <a href="../pay.php" class="btn btn-secondary">Cancel</a>
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
            if (!preview) {
                const newPreview = document.createElement('img');
                newPreview.id = 'preview';
                newPreview.src = reader.result;
                newPreview.alt = "QR Code Preview";
                newPreview.className = "img-thumbnail";
                newPreview.style = "max-width: 200px; max-height: 200px; object-fit: contain;";
                
                const previewContainer = document.createElement('div');
                previewContainer.id = 'imagePreview';
                previewContainer.innerHTML = '<p>New Image Preview:</p>';
                previewContainer.appendChild(newPreview);
                
                this.parentNode.appendChild(previewContainer);
            } else {
                preview.src = reader.result;
                imagePreview.style.display = 'block';
            }
        });
        
        reader.readAsDataURL(file);
    } else if (preview) {
        imagePreview.style.display = 'none';
    }
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>