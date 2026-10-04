<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Edit Notice";
$error_message = '';
$success_message = '';

// Get notice ID from URL
$notice_id = $_GET['id'] ?? 0;
if (!$notice_id) {
    header("Location: ../notices.php");
    exit();
}

// Fetch notice data
$stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
$stmt->execute([$notice_id]);
$notice = $stmt->fetch();

if (!$notice) {
    $_SESSION['error_message'] = "Notice not found.";
    header("Location: ../notices.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notice'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $button_text = trim($_POST['button_text'] ?? '');
    $button_action = $_POST['button_action'] ?? '';
    $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
    $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = intval($_POST['display_order'] ?? 0);
    
    $upload_success = true;
    $media_file = $notice['media_file'];

    // Handle file upload if new file is provided
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../storage/uploads/notices/';
        
        $allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_video_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm'];
        $max_size = 100 * 1024 * 1024; // 100MB
        
        $file = $_FILES['media_file'];
        $file_type = $file['type'];
        $file_size = $file['size'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type based on existing media type
        if ($notice['media_type'] === 'image' && !in_array($file_type, $allowed_image_types)) {
            $error_message = "Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.";
            $upload_success = false;
        } elseif ($notice['media_type'] === 'video' && !in_array($file_type, $allowed_video_types)) {
            $error_message = "Invalid video format. Only MP4, MPEG, MOV, AVI, MKV, and WEBM are allowed.";
            $upload_success = false;
        }
        
        // Validate file size
        if ($upload_success && $file_size > $max_size) {
            $error_message = "File size too large. Maximum size is 100MB.";
            $upload_success = false;
        }
        
        if ($upload_success) {
            // Generate unique filename
            $new_media_file = 'notice_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $new_media_file;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Delete old file
                if (!empty($notice['media_file']) && file_exists($upload_dir . $notice['media_file'])) {
                    unlink($upload_dir . $notice['media_file']);
                }
                $media_file = $new_media_file;
            } else {
                $error_message = "Failed to upload file. Please try again.";
                $upload_success = false;
            }
        }
    }
    
    // Validate button action if button text is provided
    if ($upload_success && !empty($button_text) && empty($button_action)) {
        $error_message = "Please select a button action when button text is provided.";
        $upload_success = false;
    }
    
    if ($upload_success && empty($error_message)) {
        try {
            // If no button text, set button_action to NULL
            if (empty($button_text)) {
                $button_action = null;
            }
            
            $stmt = $pdo->prepare("
                UPDATE notices 
                SET title = ?, description = ?, media_file = ?, button_text = ?, button_action = ?, 
                    start_date = ?, end_date = ?, is_active = ?, display_order = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $title, $description, $media_file, $button_text, $button_action, 
                $start_date, $end_date, $is_active, $display_order, $notice_id
            ]);
            
            $_SESSION['success_message'] = "Notice updated successfully!";
            header("Location: ../notices.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Notice</h1>
    <a href="../notices.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Notices
    </a>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Notice Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" id="editNoticeForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Media Type</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($notice['media_type']); ?>" readonly>
                                <small class="form-text text-muted">Media type cannot be changed after creation</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Update Media File</label>
                                <input type="file" class="form-control" name="media_file">
                                <small class="form-text text-muted">
                                    Leave empty to keep current file. 
                                    <?php echo $notice['media_type'] === 'image' ? 
                                        'Accepted: JPG, PNG, GIF, WEBP' : 
                                        'Accepted: MP4, MPEG, MOV, AVI, MKV, WEBM'; 
                                    ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Media Preview</label>
                                <div>
                                    <?php if ($notice['media_type'] === 'image'): ?>
                                        <img src="../../storage/uploads/notices/<?php echo htmlspecialchars($notice['media_file']); ?>" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 200px;"
                                             alt="<?php echo htmlspecialchars($notice['title']); ?>">
                                    <?php else: ?>
                                        <video controls class="img-fluid rounded border" style="max-height: 200px;">
                                            <source src="../../storage/uploads/notices/<?php echo htmlspecialchars($notice['media_file']); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" 
                                       value="<?php echo htmlspecialchars($notice['title']); ?>" 
                                       placeholder="Enter notice title" required maxlength="255">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Enter notice description" maxlength="500"><?php echo htmlspecialchars($notice['description']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Button Text</label>
                                <input type="text" class="form-control" name="button_text" 
                                       value="<?php echo htmlspecialchars($notice['button_text'] ?? ''); ?>" 
                                       placeholder="e.g., Order Now, Book Now" maxlength="100">
                                <small class="form-text text-muted">Leave empty for no button</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Button Action</label>
                                <select class="form-select" name="button_action">
                                    <option value="">Select Action</option>
                                    <option value="order-now" <?php echo ($notice['button_action'] === 'order-now') ? 'selected' : ''; ?>>Order Now</option>
                                    <option value="book-now" <?php echo ($notice['button_action'] === 'book-now') ? 'selected' : ''; ?>>Book Now</option>
                                    <option value="write" <?php echo ($notice['button_action'] === 'write') ? 'selected' : ''; ?>>Write</option>
                                </select>
                                <small class="form-text text-muted">Required if button text is provided</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="datetime-local" class="form-control" name="start_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($notice['start_date'])); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="datetime-local" class="form-control" name="end_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($notice['end_date'])); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="display_order" 
                                       value="<?php echo $notice['display_order']; ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" 
                                           <?php echo $notice['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_notice" class="btn btn-primary">Update Notice</button>
                        <a href="../notices.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Notice Information</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($notice['created_at'])); ?></dd>
                    
                    <dt class="col-sm-5">Last Updated:</dt>
                    <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($notice['updated_at'])); ?></dd>
                    
                    <dt class="col-sm-5">Media Type:</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-info"><?php echo ucfirst($notice['media_type']); ?></span>
                    </dd>
                    
                    <dt class="col-sm-5">File Name:</dt>
                    <dd class="col-sm-7">
                        <small class="text-muted"><?php echo htmlspecialchars($notice['media_file']); ?></small>
                    </dd>
                    
                    <dt class="col-sm-5">Current Status:</dt>
                    <dd class="col-sm-7">
                        <span class="badge <?php echo $notice['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $notice['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
// Validation for edit form
document.getElementById('editNoticeForm').addEventListener('submit', function(e) {
    const fileInput = document.querySelector('input[name="media_file"]');
    const file = fileInput.files[0];
    const maxSize = 100 * 1024 * 1024; // 100MB
    const mediaType = '<?php echo $notice['media_type']; ?>';
    
    if (file) {
        // Validate file size
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File size exceeds 100MB limit. Please choose a smaller file.');
            return false;
        }
        
        // Validate file type
        const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const allowedVideoTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm'];
        
        if (mediaType === 'image' && !allowedImageTypes.includes(file.type)) {
            e.preventDefault();
            alert('Invalid image format. Please select JPG, PNG, GIF, or WEBP file.');
            return false;
        }
        
        if (mediaType === 'video' && !allowedVideoTypes.includes(file.type)) {
            e.preventDefault();
            alert('Invalid video format. Please select MP4, MPEG, MOV, AVI, MKV, or WEBM file.');
            return false;
        }
    }
    
    // Validate button settings
    const buttonText = document.querySelector('input[name="button_text"]').value;
    const buttonAction = document.querySelector('select[name="button_action"]').value;
    
    if (buttonText && !buttonAction) {
        e.preventDefault();
        alert('Please select a button action when button text is provided.');
        return false;
    }
    
    if (!buttonText && buttonAction) {
        e.preventDefault();
        alert('Please provide button text when button action is selected.');
        return false;
    }
    
    // Validate date range
    const startDate = new Date(document.querySelector('input[name="start_date"]').value);
    const endDate = new Date(document.querySelector('input[name="end_date"]').value);
    
    if (startDate > endDate) {
        e.preventDefault();
        alert('End date cannot be before start date.');
        return false;
    }
});

// Show/hide button action based on button text
document.querySelector('input[name="button_text"]').addEventListener('input', function() {
    const buttonAction = document.querySelector('select[name="button_action"]');
    if (this.value.trim()) {
        buttonAction.required = true;
    } else {
        buttonAction.required = false;
        buttonAction.value = '';
    }
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>