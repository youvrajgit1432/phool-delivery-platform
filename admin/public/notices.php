<?php
// public/notices.php

require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Manage Notices";
$error_message = '';
$success_message = '';

// Process form submission for new notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $button_type = $_POST['button_type'] ?? '';
    $media_type = $_POST['media_type'] ?? '';
    $upload_success = true;
    
    // Validate required fields
    if (empty($title) || empty($media_type) || !in_array($media_type, ['photo', 'video'])) {
        $error_message = "Please fill all required fields and select a valid media type.";
        $upload_success = false;
    }
    
    // Check if a file was uploaded
    if ($upload_success && (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] === UPLOAD_ERR_NO_FILE)) {
        $error_message = "Please select a file to upload.";
        $upload_success = false;
    }
    
    if ($upload_success) {
        $upload_dir = '../storage/uploads/notices/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Define allowed file types and size limits
        $allowed_photo_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_video_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm', 'video/iveo'];
        $max_file_size = 500 * 1024 * 1024; // 500MB
        
        $file = $_FILES['media_file'];
        $file_type = $file['type'];
        $file_size = $file['size'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type based on media type selection
        if ($media_type === 'photo' && !in_array($file_type, $allowed_photo_types)) {
            $error_message = "Invalid photo format. Only JPG, PNG, GIF, and WEBP are allowed.";
            $upload_success = false;
        } elseif ($media_type === 'video') {
            // Check both MIME type and file extension for videos
            $is_allowed_video = in_array($file_type, $allowed_video_types) || 
                               in_array($file_extension, ['mp4', 'mpeg', 'mov', 'avi', 'mkv', 'webm', 'iveo']);
            
            if (!$is_allowed_video) {
                $error_message = "Invalid video format. Allowed formats: MP4, MPEG, MOV, AVI, MKV, WEBM, IVEO.";
                $upload_success = false;
            }
        }
        
        // Validate file size
        if ($upload_success && $file_size > $max_file_size) {
            $error_message = "File size too large. Maximum size is 500MB.";
            $upload_success = false;
        }
        
        // Check for upload errors
        if ($upload_success && $file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive in php.ini",
                UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive in HTML form",
                UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
                UPLOAD_ERR_NO_FILE => "No file was uploaded",
                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                UPLOAD_ERR_EXTENSION => "File upload stopped by extension"
            ];
            $error_message = $upload_errors[$file['error']] ?? "Unknown upload error";
            $upload_success = false;
        }
        
        if ($upload_success) {
            // Generate unique filename
            $file_name = 'notice_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    // Insert new notice
                    $stmt = $pdo->prepare("INSERT INTO notices (title, description, button_type, media_type, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $button_type, $media_type, $file_name, $file_name, $file_size]);
                    
                    $_SESSION['success_message'] = "Notice added successfully!";
                    header("Location: notices.php");
                    exit();
                } catch (PDOException $e) {
                    // Delete the uploaded file if database operation failed
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "Failed to upload file. Please try again.";
            }
        }
    }
}

// Process delete notice
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Get notice details
        $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
        $stmt->execute([$id]);
        $notice = $stmt->fetch();
        
        if ($notice) {
            // Delete the media file
            $file_path = '../storage/uploads/notices/' . $notice['file_name'];
            if (!empty($notice['file_name']) && file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success_message'] = "Notice deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Notice not found.";
        }
        
        header("Location: notices.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: notices.php");
        exit();
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

// Fetch all notices
$notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();

include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Notices</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
        <i class="fas fa-plus me-1"></i> Add New Notice
    </button>
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

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">All Notices</h5>
    </div>
    <div class="card-body">
        <?php if (count($notices) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Media</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Button Type</th>
                            <th>Media Type</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notices as $notice): ?>
                        <tr>
                            <td><?php echo $notice['id']; ?></td>
                            <td>
                                <?php if ($notice['media_type'] === 'photo'): ?>
                                    <img src="<?php echo getNoticeUrl($notice['file_name']); ?>" 
                                         class="img-thumbnail" 
                                         alt="<?php echo htmlspecialchars($notice['title']); ?>"
                                         style="width: 80px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="position-relative">
                                        <video class="img-thumbnail" style="width: 80px; height: 60px; object-fit: cover;">
                                            <source src="<?php echo getNoticeUrl($notice['file_name']); ?>" type="video/mp4">
                                        </video>
                                        <div class="position-absolute top-50 start-50 translate-middle">
                                            <i class="fas fa-play text-white"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($notice['title']); ?></td>
                            <td>
                                <?php 
                                if (!empty($notice['description'])) {
                                    echo strlen($notice['description']) > 50 
                                        ? htmlspecialchars(substr($notice['description'], 0, 50)) . '...' 
                                        : htmlspecialchars($notice['description']);
                                } else {
                                    echo '<span class="text-muted">No description</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($notice['button_type'])): ?>
                                    <span class="badge bg-primary"><?php echo ucfirst($notice['button_type']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $notice['media_type'] === 'photo' ? 'success' : 'info'; ?>">
                                    <?php echo ucfirst($notice['media_type']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($notice['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary view-notice" 
                                            data-bs-toggle="modal" data-bs-target="#viewNoticeModal"
                                            data-id="<?php echo $notice['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($notice['title']); ?>"
                                            data-description="<?php echo htmlspecialchars($notice['description'] ?? ''); ?>"
                                            data-button-type="<?php echo $notice['button_type']; ?>"
                                            data-media-type="<?php echo $notice['media_type']; ?>"
                                            data-file-name="<?php echo $notice['file_name']; ?>"
                                            data-created-at="<?php echo date('M d, Y H:i', strtotime($notice['created_at'])); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary edit-notice"
                                            data-bs-toggle="modal" data-bs-target="#editNoticeModal"
                                            data-id="<?php echo $notice['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($notice['title']); ?>"
                                            data-description="<?php echo htmlspecialchars($notice['description'] ?? ''); ?>"
                                            data-button-type="<?php echo $notice['button_type']; ?>"
                                            data-media-type="<?php echo $notice['media_type']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="notices.php?delete=<?php echo $notice['id']; ?>" 
                                       class="btn btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this notice?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-sticky-note fa-4x text-muted mb-3"></i>
                <h5>No Notices Found</h5>
                <p class="text-muted">You haven't added any notices yet. Click the "Add New Notice" button to create your first notice.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                    <i class="fas fa-plus me-1"></i> Add Your First Notice
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1" aria-labelledby="addNoticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoticeModalLabel">Add New Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="addNoticeForm">
                <div class="modal-body">
                    <input type="hidden" name="add_notice" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" placeholder="Enter notice title" maxlength="100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Enter notice description" maxlength="500"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Button Type</label>
                        <select class="form-select" name="button_type">
                            <option value="">No Button</option>
                            <option value="order_now">Order Now</option>
                            <option value="book_now">Book Now</option>
                            <option value="learn_more">Learn More</option>
                            <option value="sign_up">Sign Up</option>
                            <option value="get_started">Get Started</option>
                            <option value="buy_now">Buy Now</option>
                            <option value="download">Download</option>
                            <option value="view_details">View Details</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Media Type *</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="media_type" id="photoType" value="photo" required>
                                <label class="form-check-label" for="photoType">Photo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="media_type" id="videoType" value="video" required>
                                <label class="form-check-label" for="videoType">Video</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Media File *</label>
                        <input type="file" class="form-control" name="media_file" id="mediaFile" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mpeg,.mov,.avi,.mkv,.webm,.iveo" required>
                        <small class="form-text text-muted">
                            Accepted formats: 
                            <strong>Photos:</strong> JPG, PNG, GIF, WEBP | 
                            <strong>Videos:</strong> MP4, MPEG, MOV, AVI, MKV, WEBM, IVEO<br>
                            Max size: 500MB
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Notice Modal -->
<div class="modal fade" id="viewNoticeModal" tabindex="-1" aria-labelledby="viewNoticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNoticeModalLabel">Notice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div id="noticeMediaPreview" class="text-center mb-3">
                            <!-- Media will be inserted here by JavaScript -->
                        </div>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Title:</th>
                                <td id="viewTitle"></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td id="viewDescription"></td>
                            </tr>
                            <tr>
                                <th>Button Type:</th>
                                <td id="viewButtonType"></td>
                            </tr>
                            <tr>
                                <th>Media Type:</th>
                                <td id="viewMediaType"></td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td id="viewCreatedAt"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Notice Modal -->
<div class="modal fade" id="editNoticeModal" tabindex="-1" aria-labelledby="editNoticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNoticeModalLabel">Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="editNoticeForm">
                <div class="modal-body">
                    <input type="hidden" name="edit_notice" value="1">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" id="editTitle" placeholder="Enter notice title" maxlength="100" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3" placeholder="Enter notice description" maxlength="500"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Button Type</label>
                        <select class="form-select" name="button_type" id="editButtonType">
                            <option value="">No Button</option>
                            <option value="order_now">Order Now</option>
                            <option value="book_now">Book Now</option>
                            <option value="write">Write</option> 
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Note:</strong> To change the media file, please delete this notice and create a new one.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Dynamic file input accept attribute based on media type selection
document.querySelectorAll('input[name="media_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const fileInput = document.getElementById('mediaFile');
        if (this.value === 'photo') {
            fileInput.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp');
        } else if (this.value === 'video') {
            fileInput.setAttribute('accept', '.mp4,.mpeg,.mov,.avi,.mkv,.webm,.iveo');
        }
    });
});

// File validation
document.getElementById('mediaFile').addEventListener('change', function() {
    const file = this.files[0];
    const maxSize = 500 * 1024 * 1024; // 500MB
    
    if (file) {
        // Validate file size
        if (file.size > maxSize) {
            alert('File size exceeds 500MB limit. Please choose a smaller file.');
            this.value = '';
            return;
        }
        
        // Validate file type based on selected media type
        const selectedMediaType = document.querySelector('input[name="media_type"]:checked');
        if (selectedMediaType) {
            const isPhoto = selectedMediaType.value === 'photo';
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const videoExtensions = ['mp4', 'mpeg', 'mov', 'avi', 'mkv', 'webm', 'iveo'];
            
            if (isPhoto && !isImage) {
                alert('Selected file is not an image. Please choose a photo file.');
                this.value = '';
                return;
            }
            
            if (!isPhoto && !isVideo && !videoExtensions.includes(fileExtension)) {
                alert('Selected file is not a supported video format.');
                this.value = '';
                return;
            }
        }
    }
});

// View Notice Modal
document.querySelectorAll('.view-notice').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const title = this.getAttribute('data-title');
        const description = this.getAttribute('data-description');
        const buttonType = this.getAttribute('data-button-type');
        const mediaType = this.getAttribute('data-media-type');
        const fileName = this.getAttribute('data-file-name');
        const createdAt = this.getAttribute('data-created-at');
        
        document.getElementById('viewTitle').textContent = title;
        document.getElementById('viewDescription').textContent = description || 'No description';
        
        // Format button type for display
        let buttonTypeDisplay = 'None';
        if (buttonType) {
            buttonTypeDisplay = buttonType.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }
        document.getElementById('viewButtonType').textContent = buttonTypeDisplay;
        
        document.getElementById('viewMediaType').textContent = mediaType.charAt(0).toUpperCase() + mediaType.slice(1);
        document.getElementById('viewCreatedAt').textContent = createdAt;
        
        // Set media preview
        const mediaPreview = document.getElementById('noticeMediaPreview');
        mediaPreview.innerHTML = '';
        
        if (mediaType === 'photo') {
            const img = document.createElement('img');
            img.src = '<?php echo getStorageUrl("notices/"); ?>' + fileName;
            img.alt = title;
            img.className = 'img-fluid rounded';
            img.style.maxHeight = '300px';
            img.style.objectFit = 'contain';
            mediaPreview.appendChild(img);
        } else {
            const video = document.createElement('video');
            video.controls = true;
            video.className = 'img-fluid rounded';
            video.style.maxHeight = '300px';
            video.style.objectFit = 'contain';
            
            const source = document.createElement('source');
            source.src = '<?php echo getStorageUrl("notices/"); ?>' + fileName;
            source.type = 'video/mp4';
            video.appendChild(source);
            
            video.appendChild(document.createTextNode('Your browser does not support the video tag.'));
            mediaPreview.appendChild(video);
        }
    });
});

// Edit Notice Modal
document.querySelectorAll('.edit-notice').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const title = this.getAttribute('data-title');
        const description = this.getAttribute('data-description');
        const buttonType = this.getAttribute('data-button-type');
        const mediaType = this.getAttribute('data-media-type');
        
        document.getElementById('editId').value = id;
        document.getElementById('editTitle').value = title;
        document.getElementById('editDescription').value = description || '';
        document.getElementById('editButtonType').value = buttonType || '';
    });
});

// Format file size function for JavaScript
function formatFileSizeJS(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php
include '../app/views/layouts/footer.php';
?>