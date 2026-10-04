<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Add New Notice";
$error_message = '';
$success_message = '';

// Handle form submission for new notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $media_type = $_POST['media_type'] ?? 'image';
    $button_text = trim($_POST['button_text'] ?? '');
    $button_action = $_POST['button_action'] ?? '';
    $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
    $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = intval($_POST['display_order'] ?? 0);
    
    $upload_success = true;
    $media_file = '';

    // Validate required fields
    if (empty($title)) {
        $error_message = "Title is required.";
        $upload_success = false;
    }

    // Handle file upload
    if ($upload_success && (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] === UPLOAD_ERR_NO_FILE)) {
        $error_message = "Please select a media file.";
        $upload_success = false;
    }

    if ($upload_success && isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../storage/uploads/notices/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_video_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm'];
        $max_size = 100 * 1024 * 1024; // 100MB
        
        $file = $_FILES['media_file'];
        $file_type = $file['type'];
        $file_size = $file['size'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type based on media type
        if ($media_type === 'image' && !in_array($file_type, $allowed_image_types)) {
            $error_message = "Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.";
            $upload_success = false;
        } elseif ($media_type === 'video' && !in_array($file_type, $allowed_video_types)) {
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
            $media_file = 'notice_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $media_file;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
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

    if ($upload_success && !empty($button_action) && empty($button_text)) {
        $error_message = "Please provide button text when button action is selected.";
        $upload_success = false;
    }
    
    // Validate date range
    if ($upload_success) {
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        
        if ($start_timestamp > $end_timestamp) {
            $error_message = "End date cannot be before start date.";
            $upload_success = false;
        }
    }
    
    if ($upload_success && empty($error_message)) {
        try {
            // If no button text, set button_action to NULL
            if (empty($button_text)) {
                $button_action = null;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO notices 
                (title, description, media_type, media_file, button_text, button_action, start_date, end_date, is_active, display_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $description, $media_type, $media_file, 
                $button_text, $button_action, 
                $start_date, $end_date, $is_active, $display_order
            ]);
            
            $_SESSION['success_message'] = "Notice added successfully!";
            header("Location: ../notices.php");
            exit();
        } catch (PDOException $e) {
            // Delete uploaded file if database operation failed
            if (!empty($media_file) && file_exists($upload_dir . $media_file)) {
                unlink($upload_dir . $media_file);
            }
            $error_message = "Database error: " . $e->getMessage();
            error_log("Notice addition failed: " . $e->getMessage());
        }
    }
}

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Notice</h1>
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
                <h5 class="card-title mb-0">Notice Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" id="addNoticeForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mediaType" class="form-label">Media Type *</label>
                                <select class="form-select" name="media_type" id="mediaType" required>
                                    <option value="image" selected>Image</option>
                                    <option value="video">Video</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mediaFile" class="form-label">Media File *</label>
                                <input type="file" class="form-control" name="media_file" id="mediaFile" required>
                                <div class="form-text" id="fileHelp">
                                    For images: JPG, PNG, GIF, WEBP. For videos: MP4, MPEG, MOV, AVI, MKV, WEBM. Max size: 100MB
                                </div>
                                <div id="filePreview" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" id="title"
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                       placeholder="Enter notice title" required maxlength="255">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="3" 
                                          placeholder="Enter notice description" maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Button Settings</label>
                                <div class="mb-2">
                                    <label for="button_text" class="form-label">Button Text</label>
                                    <input type="text" class="form-control" name="button_text" id="button_text"
                                           value="<?php echo htmlspecialchars($_POST['button_text'] ?? ''); ?>" 
                                           placeholder="e.g., Order Now, Book Now" maxlength="100">
                                    <div class="form-text">Leave empty for no button</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="buttonAction" class="form-label">Button Action</label>
                                    <select class="form-select" name="button_action" id="buttonAction">
                                        <option value="">Select Action</option>
                                        <option value="order-now" <?php echo ($_POST['button_action'] ?? '') === 'order-now' ? 'selected' : ''; ?>>Order Now</option>
                                        <option value="book-now" <?php echo ($_POST['button_action'] ?? '') === 'book-now' ? 'selected' : ''; ?>>Book Now</option>
                                        <option value="write" <?php echo ($_POST['button_action'] ?? '') === 'write' ? 'selected' : ''; ?>>Write</option>
                                    </select>
                                    <div class="form-text">Required if button text is provided</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="datetime-local" class="form-control" name="start_date" id="start_date"
                                               value="<?php echo $_POST['start_date'] ?? date('Y-m-d\TH:i'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="datetime-local" class="form-control" name="end_date" id="end_date"
                                               value="<?php echo $_POST['end_date'] ?? date('Y-m-d\TH:i', strtotime('+30 days')); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="display_order" class="form-label">Display Order</label>
                                        <input type="number" class="form-control" name="display_order" id="display_order"
                                               value="<?php echo $_POST['display_order'] ?? 0; ?>" min="0">
                                        <div class="form-text">Lower numbers display first</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" 
                                                   <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" name="add_notice" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Notice
                        </button>
                        <a href="../notices.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Guidelines</h5>
            </div>
            <div class="card-body">
                <h6>Media Requirements:</h6>
                <ul class="small">
                    <li><strong>Images:</strong> JPG, PNG, GIF, WEBP</li>
                    <li><strong>Videos:</strong> MP4, MPEG, MOV, AVI, MKV, WEBM</li>
                    <li><strong>Max Size:</strong> 100MB per file</li>
                </ul>
                
                <h6>Button Options:</h6>
                <ul class="small">
                    <li><strong>Order Now:</strong> For ordering products</li>
                    <li><strong>Book Now:</strong> For booking services</li>
                    <li><strong>Write:</strong> For contact forms</li>
                    <li>Leave empty if no button needed</li>
                </ul>
                
                <h6>Display Settings:</h6>
                <ul class="small">
                    <li>Notices with lower display order numbers show first</li>
                    <li>Set dates to control when notice is visible</li>
                    <li>Inactive notices won't be shown to users</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Dynamic file input based on media type
document.getElementById('mediaType').addEventListener('change', function() {
    const fileInput = document.getElementById('mediaFile');
    const fileHelp = document.getElementById('fileHelp');
    
    if (this.value === 'image') {
        fileInput.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp');
        fileHelp.textContent = 'For images: JPG, PNG, GIF, WEBP. Max size: 100MB';
    } else {
        fileInput.setAttribute('accept', '.mp4,.mpeg,.mov,.avi,.mkv,.webm');
        fileHelp.textContent = 'For videos: MP4, MPEG, MOV, AVI, MKV, WEBM. Max size: 100MB';
    }
    
    // Clear file input and preview when media type changes
    fileInput.value = '';
    document.getElementById('filePreview').innerHTML = '';
});

// File validation and preview
document.getElementById('mediaFile').addEventListener('change', function() {
    const file = this.files[0];
    const maxSize = 100 * 1024 * 1024; // 100MB
    const mediaType = document.getElementById('mediaType').value;
    const preview = document.getElementById('filePreview');
    
    preview.innerHTML = '';
    
    if (file) {
        // Validate file size
        if (file.size > maxSize) {
            alert('File size exceeds 100MB limit. Please choose a smaller file.');
            this.value = '';
            return;
        }
        
        // Validate file type
        const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const allowedVideoTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm'];
        
        if (mediaType === 'image' && !allowedImageTypes.includes(file.type)) {
            alert('Invalid image format. Please select JPG, PNG, GIF, or WEBP file.');
            this.value = '';
            return;
        }
        
        if (mediaType === 'video' && !allowedVideoTypes.includes(file.type)) {
            alert('Invalid video format. Please select MP4, MPEG, MOV, AVI, MKV, or WEBM file.');
            this.value = '';
            return;
        }
        
        // Show preview
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        if (mediaType === 'image') {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `
                    <div class="border rounded p-2">
                        <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 150px;">
                        <div class="mt-1 small text-muted">${file.name} (${fileSizeMB} MB)</div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `
                <div class="border rounded p-2">
                    <div class="text-center py-3 bg-light rounded">
                        <i class="fas fa-video fa-2x text-muted mb-2"></i>
                        <div class="small text-muted">${file.name}</div>
                        <div class="small text-muted">${fileSizeMB} MB</div>
                    </div>
                </div>
            `;
        }
    }
});

// Show/hide button action based on button text
document.getElementById('button_text').addEventListener('input', function() {
    const buttonAction = document.getElementById('buttonAction');
    if (this.value.trim()) {
        buttonAction.required = true;
    } else {
        buttonAction.required = false;
        buttonAction.value = '';
    }
});

// Form validation
document.getElementById('addNoticeForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const fileInput = document.getElementById('mediaFile');
    const buttonText = document.getElementById('button_text').value.trim();
    const buttonAction = document.getElementById('buttonAction').value;
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    // Validate title
    if (!title) {
        e.preventDefault();
        alert('Please enter a title for the notice.');
        document.getElementById('title').focus();
        return false;
    }
    
    // Validate file
    if (!fileInput.files[0]) {
        e.preventDefault();
        alert('Please select a media file.');
        fileInput.focus();
        return false;
    }
    
    // Validate button settings
    if (buttonText && !buttonAction) {
        e.preventDefault();
        alert('Please select a button action when button text is provided.');
        document.getElementById('buttonAction').focus();
        return false;
    }
    
    if (!buttonText && buttonAction) {
        e.preventDefault();
        alert('Please provide button text when button action is selected.');
        document.getElementById('button_text').focus();
        return false;
    }
    
    // Validate date range
    if (startDate > endDate) {
        e.preventDefault();
        alert('End date cannot be before start date.');
        document.getElementById('end_date').focus();
        return false;
    }
    
    return true;
});
</script>

<?php
include '../../app/views/layouts/footer.php';
?>