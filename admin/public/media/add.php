<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get all active categories
$categories = $pdo->query("SELECT * FROM media_categories WHERE status = 'active' ORDER BY name_en")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en = trim($_POST['title_en']);
    $title_ne = trim($_POST['title_ne']);
    $description_en = trim($_POST['description_en']);
    $description_ne = trim($_POST['description_ne']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $media_type = $_POST['media_type'];
    $status = $_POST['status'];
    $uploaded_by = $_SESSION['admin_id'];
    
    try {
        // Handle file upload
        $upload_dir = '../../storage/uploads/media/';
        $thumb_dir = $upload_dir . 'thumbs/';
        
        // Create directories if they don't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        if (!file_exists($thumb_dir)) {
            mkdir($thumb_dir, 0777, true);
        }
        
        $file_name = '';
        $thumbnail_name = '';
        $file_size = 0;
        $duration = 0;
        $custom_thumbnail = false;
        
        // Check if media file is uploaded
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            $file_size = $_FILES['media_file']['size'];
            
            // Validate file type
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_video_types = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'flv'];
            
            if ($media_type === 'image' && !in_array($file_ext, $allowed_image_types)) {
                throw new Exception("Invalid image format. Allowed: " . implode(', ', $allowed_image_types));
            }
            
            if ($media_type === 'video' && !in_array($file_ext, $allowed_video_types)) {
                throw new Exception("Invalid video format. Allowed: " . implode(', ', $allowed_video_types));
            }
            
            // Check file size (2GB max)
            $max_file_size = 2 * 1024 * 1024 * 1024; // 2GB in bytes
            if ($file_size > $max_file_size) {
                throw new Exception("File size exceeds maximum limit of 2GB.");
            }
            
                // Move or process uploaded file
                if ($media_type === 'image') {
                    // Process image (resize + compress) before saving
                    if (!processAndSaveImage($_FILES['media_file']['tmp_name'], $file_path)) {
                        throw new Exception("Failed to process and save image.");
                    }
                } else {
                    // For videos and other files, move as-is
                    if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $file_path)) {
                        throw new Exception("Failed to upload file.");
                    }
                }
            
            // Handle custom thumbnail upload
            if (isset($_FILES['custom_thumbnail']) && $_FILES['custom_thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumb_ext = strtolower(pathinfo($_FILES['custom_thumbnail']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($thumb_ext, $allowed_image_types)) {
                    throw new Exception("Invalid thumbnail format. Allowed: " . implode(', ', $allowed_image_types));
                }
                
                $thumbnail_name = 'custom_thumb_' . uniqid() . '.' . $thumb_ext;
                $thumbnail_path = $thumb_dir . $thumbnail_name;
                
                // Process and save thumbnail to reduce size while preserving quality
                if (!processAndSaveImage($_FILES['custom_thumbnail']['tmp_name'], $thumbnail_path)) {
                    throw new Exception("Failed to process and save custom thumbnail.");
                }
                
                $custom_thumbnail = true;
            } else {
                // Generate automatic thumbnail
                if ($media_type === 'image') {
                    $thumbnail_name = 'thumb_' . $file_name;
                    $thumbnail_path = $thumb_dir . $thumbnail_name;
                    
                    if (!createImageThumbnail($file_path, $thumbnail_path, 300, 300)) {
                        $thumbnail_name = '';
                    }
                } else if ($media_type === 'video') {
                    $thumbnail_name = 'thumb_' . uniqid() . '.jpg';
                    $thumbnail_path = $thumb_dir . $thumbnail_name;
                    
                    if (!createVideoThumbnail($file_path, $thumbnail_path)) {
                        $thumbnail_name = '';
                    }
                }
            }
            
            // Try to get video duration if FFmpeg is available
            if ($media_type === 'video') {
                $duration = getVideoDuration($file_path);
            }
        } else {
            $error_code = $_FILES['media_file']['error'] ?? 'Unknown';
            throw new Exception("Please select a valid file to upload. Error code: " . $error_code);
        }
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO media_items (title_en, title_ne, description_en, description_ne, file_path, thumbnail_path, custom_thumbnail, media_type, file_size, duration, category_id, uploaded_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title_en, $title_ne, $description_en, $description_ne, $file_name, $thumbnail_name, $custom_thumbnail, 
            $media_type, $file_size, $duration, $category_id, $uploaded_by, $status
        ]);
        
        $_SESSION['success_message'] = "Media uploaded successfully!";
        header("Location: ../media.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error uploading media: " . $e->getMessage();
    }
}

// Helper functions for media processing
function createImageThumbnail($source_path, $thumbnail_path, $width = 300, $height = 300) {
    try {
        list($src_width, $src_height, $type) = getimagesize($source_path);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($source_path);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($source_path);
                break;
            default:
                return false;
        }
        
        $thumb = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $src_width, $src_height);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $thumbnail_path, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $thumbnail_path);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb, $thumbnail_path);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumb, $thumbnail_path, 85);
                break;
        }
        
        imagedestroy($thumb);
        imagedestroy($source);
        
        return true;
    } catch (Exception $e) {
        error_log("Thumbnail creation error: " . $e->getMessage());
        return false;
    }
}

// Image processing helper: resizes and compresses while preserving quality
function processAndSaveImage($tmpPath, $targetPath, $maxWidth = 1200, $maxHeight = 1200, $jpegQuality = 85) {
    $info = @getimagesize($tmpPath);
    if (!$info) {
        return false;
    }

    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    // Calculate new dimensions preserving aspect ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newW = (int) max(1, floor($width * $ratio));
    $newH = (int) max(1, floor($height * $ratio));

    // Create image resource from uploaded file
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($tmpPath);
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($tmpPath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($tmpPath);
            } else {
                $src = @imagecreatefromstring(file_get_contents($tmpPath));
            }
            break;
        default:
            $src = @imagecreatefromstring(file_get_contents($tmpPath));
    }

    if (!$src) {
        return false;
    }

    $dst = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG and GIF
    if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Save according to extension (preserve original where possible)
    $saved = false;
    if (in_array($ext, ['jpg', 'jpeg'])) {
        $saved = imagejpeg($dst, $targetPath, $jpegQuality);
    } elseif ($ext === 'png') {
        $pngLevel = 6;
        $saved = imagepng($dst, $targetPath, $pngLevel);
    } elseif ($ext === 'gif') {
        $saved = imagegif($dst, $targetPath);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $saved = imagewebp($dst, $targetPath, $jpegQuality);
    } else {
        // Fallback: save as JPEG
        $saved = imagejpeg($dst, $targetPath, $jpegQuality);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $saved;
}

function createVideoThumbnail($video_path, $thumbnail_path, $time = 5) {
    // Check if FFmpeg is available
    if (!function_exists('shell_exec') || !shell_exec('which ffmpeg')) {
        // FFmpeg not available, use a fallback method or return false
        return createFallbackVideoThumbnail($video_path, $thumbnail_path);
    }
    
    try {
        $command = "ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:0{$time} -vframes 1 -y " . escapeshellarg($thumbnail_path) . " 2>&1";
        $output = shell_exec($command);
        
        if (file_exists($thumbnail_path) && filesize($thumbnail_path) > 0) {
            // Create a smaller version of the thumbnail
            createImageThumbnail($thumbnail_path, $thumbnail_path, 300, 300);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Video thumbnail error: " . $e->getMessage());
        return createFallbackVideoThumbnail($video_path, $thumbnail_path);
    }
}

function createFallbackVideoThumbnail($video_path, $thumbnail_path) {
    // Create a generic video thumbnail
    $thumb = imagecreatetruecolor(300, 300);
    $bg_color = imagecolorallocate($thumb, 41, 128, 185);
    $icon_color = imagecolorallocate($thumb, 255, 255, 255);
    
    imagefill($thumb, 0, 0, $bg_color);
    
    // Draw a play button
    $points = array(
        120, 100,   // Point 1 (x, y)
        120, 200,   // Point 2 (x, y)
        220, 150    // Point 3 (x, y)
    );
    imagefilledpolygon($thumb, $points, 3, $icon_color);
    
    imagejpeg($thumb, $thumbnail_path, 85);
    imagedestroy($thumb);
    
    return true;
}

function getVideoDuration($video_path) {
    if (!function_exists('shell_exec') || !shell_exec('which ffprobe')) {
        return 0;
    }
    
    try {
        $command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($video_path);
        $duration = shell_exec($command);
        return intval(round(floatval($duration)));
    } catch (Exception $e) {
        error_log("Duration detection error: " . $e->getMessage());
        return 0;
    }
}

// Set page title
$page_title = "Add Media - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Media</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../media.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Media
        </a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Media Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="mediaForm">
                    <div class="mb-3">
                        <label for="media_type" class="form-label">Media Type</label>
                        <select class="form-select" id="media_type" name="media_type" required>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="media_file" class="form-label">Media File</label>
                        <input type="file" class="form-control" id="media_file" name="media_file" required>
                        <div class="form-text" id="fileHelp">
                            For images: JPG, PNG, GIF, WebP (Max 2GB). For videos: MP4, MOV, AVI, WMV, MKV, FLV (Max 2GB).
                        </div>
                        <div class="progress mt-2 d-none" id="uploadProgress">
                            <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="custom_thumbnail" class="form-label">Custom Thumbnail (Optional)</label>
                        <input type="file" class="form-control" id="custom_thumbnail" name="custom_thumbnail" accept="image/*">
                        <div class="form-text">
                            Upload a custom thumbnail image. If not provided, a thumbnail will be automatically generated.
                        </div>
                    </div>
                    
                    <!-- English Fields -->
                    <div class="border p-3 mb-3 rounded">
                        <h6 class="mb-3"><i class="fas fa-language me-2"></i>English Details</h6>
                        <div class="mb-3">
                            <label for="title_en" class="form-label">Title (English)</label>
                            <input type="text" class="form-control" id="title_en" name="title_en" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control" id="description_en" name="description_en" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <!-- Nepali Fields -->
                    <div class="border p-3 mb-3 rounded">
                        <h6 class="mb-3"><i class="fas fa-language me-2"></i>Nepali Details</h6>
                        <div class="mb-3">
                            <label for="title_ne" class="form-label">Title (नेपाली)</label>
                            <input type="text" class="form-control" id="title_ne" name="title_ne" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description_ne" class="form-label">Description (नेपाली)</label>
                            <textarea class="form-control" id="description_ne" name="description_ne" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">No Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_en']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">Upload Media</button>
                    <a href="../media.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Upload Guidelines</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Image Guidelines:</h6>
                    <ul class="mb-0 small">
                        <li>Max file size: 2GB</li>
                        <li>Formats: JPG, PNG, GIF, WebP</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Video Guidelines:</h6>
                    <ul class="mb-0 small">
                        <li>Max file size: 2GB</li>
                        <li>Formats: MP4, MOV, AVI, WMV, MKV, FLV</li>
                        <li>Longer videos may take time to process</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Language Guidelines:</h6>
                    <ul class="mb-0 small">
                        <li>Provide both English and Nepali titles</li>
                        <li>Provide both English and Nepali descriptions</li>
                        <li>Both languages are required</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important:</h6>
                    <ul class="mb-0 small">
                        <li>Ensure you have proper rights to upload the media</li>
                        <li>Use descriptive titles and descriptions in both languages</li>
                        <li>Categorize properly for better organization</li>
                        <li>Large files may take longer to upload</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update file help text based on media type selection
document.getElementById('media_type').addEventListener('change', function() {
    const fileHelp = document.getElementById('fileHelp');
    if (this.value === 'image') {
        fileHelp.textContent = 'For images: JPG, PNG, GIF, WebP (Max 2GB).';
    } else {
        fileHelp.textContent = 'For videos: MP4, MOV, AVI, WMV, MKV, FLV (Max 2GB).';
    }
});

// Simple client-side file size validation
document.getElementById('mediaForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('media_file');
    const thumbInput = document.getElementById('custom_thumbnail');
    const maxSize = 2 * 1024 * 1024 * 1024; // 2GB in bytes
    const maxThumbSize = 10 * 1024 * 1024; // 10MB for thumbnails
    
    if (fileInput.files.length > 0) {
        const fileSize = fileInput.files[0].size;
        if (fileSize > maxSize) {
            e.preventDefault();
            alert('File size exceeds the maximum limit of 2GB. Please choose a smaller file.');
            return false;
        }
    }
    
    if (thumbInput.files.length > 0) {
        const thumbSize = thumbInput.files[0].size;
        if (thumbSize > maxThumbSize) {
            e.preventDefault();
            alert('Thumbnail size exceeds the maximum limit of 10MB. Please choose a smaller file.');
            return false;
        }
    }
});

// Show progress bar for large file uploads
document.getElementById('media_file').addEventListener('change', function() {
    const progressBar = document.getElementById('uploadProgress');
    const file = this.files[0];
    
    if (file && file.size > 10 * 1024 * 1024) { // Show progress for files > 10MB
        progressBar.classList.remove('d-none');
    } else {
        progressBar.classList.add('d-none');
    }
});
</script>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>