<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Edit Ad";
$error_message = '';
$success_message = '';

// Check if ad ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No ad ID specified.";
    header("Location: ads.php");
    exit();
}

$ad_id = intval($_GET['id']);

// Fetch ad data
$stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
$stmt->execute([$ad_id]);
$ad = $stmt->fetch();

if (!$ad) {
    $_SESSION['error_message'] = "Ad not found.";
    header("Location: ads.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $update_file = isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE;
    
    try {
        if ($update_file) {
            // Handle file upload
            $upload_dir = '../storage/uploads/ads/';
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
            
            // Validate file type based on existing media type
            if ($ad['media_type'] === 'photo' && !in_array($file_type, $allowed_photo_types)) {
                $error_message = "Invalid photo format. Only JPG, PNG, GIF, and WEBP are allowed.";
            } elseif ($ad['media_type'] === 'video') {
                // Check both MIME type and file extension for videos
                $is_allowed_video = in_array($file_type, $allowed_video_types) || 
                                   in_array($file_extension, ['mp4', 'mpeg', 'mov', 'avi', 'mkv', 'webm', 'iveo']);
                
                if (!$is_allowed_video) {
                    $error_message = "Invalid video format. Allowed formats: MP4, MPEG, MOV, AVI, MKV, WEBM, IVEO.";
                }
            }
            
            // Validate file size
            if (empty($error_message) && $file_size > $max_file_size) {
                $error_message = "File size too large. Maximum size is 500MB.";
            }
            
            // Check for upload errors
            if (empty($error_message) && $file['error'] !== UPLOAD_ERR_OK) {
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
            }
            
            if (empty($error_message)) {
                // Generate unique filename
                $file_name = 'ad_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Delete old file
                    if (file_exists($ad['file_path'])) {
                        unlink($ad['file_path']);
                    }
                    
                    // Update ad with new file
                    $stmt = $pdo->prepare("UPDATE ads SET title = ?, description = ?, file_name = ?, file_path = ?, file_size = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$title, $description, $file_name, $file_path, $file_size, $ad_id]);
                    
                    $_SESSION['success_message'] = "Ad updated successfully!";
                    header("Location: ads.php");
                    exit();
                } else {
                    $error_message = "Failed to upload file. Please try again.";
                }
            }
        } else {
            // Update only title and description
            $stmt = $pdo->prepare("UPDATE ads SET title = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $description, $ad_id]);
            
            $_SESSION['success_message'] = "Ad updated successfully!";
            header("Location: ads.php");
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Ad</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="ads.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Ads
        </a>
    </div>
</div>

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
                <h5 class="card-title mb-0">Edit Ad Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Current Media Type</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo $ad['media_type'] === 'photo' ? 'info' : 'primary'; ?>">
                                <?php echo ucfirst($ad['media_type']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Replace Media File (Optional)</label>
                        <input type="file" class="form-control" name="media_file" 
                               accept="<?php echo $ad['media_type'] === 'photo' ? '.jpg,.jpeg,.png,.gif,.webp' : '.mp4,.mpeg,.mov,.avi,.mkv,.webm,.iveo'; ?>">
                        <small class="form-text text-muted">
                            Leave empty to keep the current file. Max size: 500MB
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" 
                               value="<?php echo htmlspecialchars($ad['title'] ?? ''); ?>" 
                               placeholder="Enter ad title" maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Enter ad description" maxlength="500"><?php echo htmlspecialchars($ad['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Ad</button>
                        <a href="ads.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Current Ad Preview</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if ($ad['media_type'] === 'photo'): ?>
                        <img src="<?php echo $ad['file_path']; ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo htmlspecialchars($ad['title'] ?? 'Ad Image'); ?>"
                             style="max-height: 200px; object-fit: contain;">
                    <?php else: ?>
                        <video controls class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
                            <source src="<?php echo $ad['file_path']; ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php endif; ?>
                </div>
                
                <div class="text-start">
                    <?php if (!empty($ad['title'])): ?>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($ad['title']); ?></h6>
                    <?php endif; ?>
                    
                    <?php if (!empty($ad['description'])): ?>
                        <p class="card-text"><?php echo htmlspecialchars($ad['description']); ?></p>
                    <?php endif; ?>
                    
                    <p class="card-text"><small class="text-muted">Status: 
                        <span class="badge bg-<?php echo $ad['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($ad['status']); ?>
                        </span>
                    </small></p>
                    
                    <p class="card-text"><small class="text-muted">File Size: <?php echo formatFileSize($ad['file_size'] ?? 0); ?></small></p>
                    <p class="card-text"><small class="text-muted">Uploaded: <?php echo date('M d, Y H:i', strtotime($ad['created_at'])); ?></small></p>
                    <p class="card-text"><small class="text-muted">Last Updated: <?php echo date('M d, Y H:i', strtotime($ad['updated_at'])); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to format file sizes
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

include '../app/views/layouts/footer.php';
?>