<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication media/edit.php
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get media ID from URL
$media_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($media_id === 0) {
    header("Location: ../media.php");
    exit;
}

// Get media details
$stmt = $pdo->prepare("SELECT * FROM media_items WHERE id = ?");
$stmt->execute([$media_id]);
$media = $stmt->fetch();

if (!$media) {
    $_SESSION['error_message'] = "Media not found.";
    header("Location: ../media.php");
    exit;
}

// Get all active categories
$categories = $pdo->query("SELECT * FROM media_categories WHERE status = 'active' ORDER BY name_en")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_en = trim($_POST['title_en']);
    $title_ne = trim($_POST['title_ne']);
    $description_en = trim($_POST['description_en']);
    $description_ne = trim($_POST['description_ne']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $status = $_POST['status'];
    
    // Handle file replacement if a new file is uploaded
    $file_name = $media['file_path'];
    $thumbnail_name = $media['thumbnail_path'];
    $file_size = $media['file_size'];
    $duration = $media['duration'];
    $media_type = $media['media_type'];
    $custom_thumbnail = $media['custom_thumbnail'];
    
    try {
        $upload_dir = '../../storage/uploads/media/';
        $thumb_dir = $upload_dir . 'thumbs/';
        
        // Handle media file upload
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old files
            if (!empty($media['file_path']) && file_exists($upload_dir . $media['file_path'])) {
                unlink($upload_dir . $media['file_path']);
            }
            if (!empty($media['thumbnail_path']) && file_exists($thumb_dir . $media['thumbnail_path']) && !$media['custom_thumbnail']) {
                unlink($thumb_dir . $media['thumbnail_path']);
            }
            
            // Upload new file
            $file_ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            $file_size = $_FILES['media_file']['size'];
            
            // Validate file type
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_video_types = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'flv'];
            
            $new_media_type = $_POST['media_type'];
            
            if ($new_media_type === 'image' && !in_array($file_ext, $allowed_image_types)) {
                throw new Exception("Invalid image format. Allowed: " . implode(', ', $allowed_image_types));
            }
            
            if ($new_media_type === 'video' && !in_array($file_ext, $allowed_video_types)) {
                throw new Exception("Invalid video format. Allowed: " . implode(', ', $allowed_video_types));
            }
            
            // Check file size (2GB max)
            $max_file_size = 2 * 1024 * 1024 * 1024; // 2GB in bytes
            if ($file_size > $max_file_size) {
                throw new Exception("File size exceeds maximum limit of 2GB.");
            }
            
            // Move or process uploaded file
            if ($new_media_type === 'image') {
                if (!processAndSaveImage($_FILES['media_file']['tmp_name'], $file_path)) {
                    throw new Exception("Failed to process and save image.");
                }
            } else {
                if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $file_path)) {
                    throw new Exception("Failed to upload file.");
                }
            }
            
            // Generate new thumbnail if not using custom thumbnail
            if (!$custom_thumbnail) {
                if ($new_media_type === 'image') {
                    $thumbnail_name = 'thumb_' . $file_name;
                    $thumbnail_path = $thumb_dir . $thumbnail_name;
                    
                    if (!createImageThumbnail($file_path, $thumbnail_path, 300, 300)) {
                        $thumbnail_name = '';
                    }
                } else if ($new_media_type === 'video') {
                    $thumbnail_name = 'thumb_' . uniqid() . '.jpg';
                    $thumbnail_path = $thumb_dir . $thumbnail_name;
                    
                    if (!createVideoThumbnail($file_path, $thumbnail_path)) {
                        $thumbnail_name = '';
                    }
                }
            }
            
            // Try to get video duration if FFmpeg is available
            if ($new_media_type === 'video') {
                $duration = getVideoDuration($file_path);
            }
            
            $media_type = $new_media_type;
        }
        
        // Handle custom thumbnail upload
        if (isset($_FILES['custom_thumbnail']) && $_FILES['custom_thumbnail']['error'] === UPLOAD_ERR_OK) {
            // Delete old thumbnail if it exists and wasn't custom
            if (!empty($media['thumbnail_path']) && file_exists($thumb_dir . $media['thumbnail_path']) && !$media['custom_thumbnail']) {
                unlink($thumb_dir . $media['thumbnail_path']);
            }
            
            $thumb_ext = strtolower(pathinfo($_FILES['custom_thumbnail']['name'], PATHINFO_EXTENSION));
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($thumb_ext, $allowed_image_types)) {
                throw new Exception("Invalid thumbnail format. Allowed: " . implode(', ', $allowed_image_types));
            }
            
            $thumbnail_name = 'custom_thumb_' . uniqid() . '.' . $thumb_ext;
            $thumbnail_path = $thumb_dir . $thumbnail_name;
            
            if (!processAndSaveImage($_FILES['custom_thumbnail']['tmp_name'], $thumbnail_path)) {
                throw new Exception("Failed to process and save custom thumbnail.");
            }
            
            $custom_thumbnail = true;
        }
        
        // Handle thumbnail removal
        if (isset($_POST['remove_thumbnail']) && $_POST['remove_thumbnail'] == '1') {
            if (!empty($media['thumbnail_path']) && file_exists($thumb_dir . $media['thumbnail_path'])) {
                unlink($thumb_dir . $media['thumbnail_path']);
            }
            $thumbnail_name = '';
            $custom_thumbnail = false;
        }
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE media_items 
            SET title_en = ?, title_ne = ?, description_en = ?, description_ne = ?, file_path = ?, thumbnail_path = ?, 
                custom_thumbnail = ?, media_type = ?, file_size = ?, duration = ?, 
                category_id = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([
            $title_en, $title_ne, $description_en, $description_ne, $file_name, $thumbnail_name, $custom_thumbnail,
            $media_type, $file_size, $duration, $category_id, $status, $media_id
        ]);
        
        $_SESSION['success_message'] = "Media updated successfully!";
        header("Location: ../media.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating media: " . $e->getMessage();
    }
}

// Media-specific helper functions
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
        return createFallbackVideoThumbnail($video_path, $thumbnail_path);
    }
    
    try {
        $command = "ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:0{$time} -vframes 1 -y " . escapeshellarg($thumbnail_path) . " 2>&1";
        $output = shell_exec($command);
        
        if (file_exists($thumbnail_path) && filesize($thumbnail_path) > 0) {
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
    $thumb = imagecreatetruecolor(300, 300);
    $bg_color = imagecolorallocate($thumb, 41, 128, 185);
    $icon_color = imagecolorallocate($thumb, 255, 255, 255);
    
    imagefill($thumb, 0, 0, $bg_color);
    
    $points = array(
        120, 100,
        120, 200,
        220, 150
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
$page_title = "Edit Media - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Media</h1>
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
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Current Media Type</label>
                        <p class="form-control-plaintext"><?php echo ucfirst($media['media_type']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Media</label>
                        <div>
                            <?php if ($media['media_type'] == 'image'): ?>
                                <img src="<?php echo getMediaUrl($media['file_path']); ?>" 
                                     class="img-thumbnail" style="max-height: 200px;" 
                                     alt="<?php echo htmlspecialchars($media['title_en']); ?>">
                            <?php else: ?>
                                <div class="bg-dark p-3 text-center text-white">
                                    <i class="fas fa-play-circle fa-3x"></i>
                                    <p class="mt-2">Video File: <?php echo $media['file_path']; ?></p>
                                    <?php if ($media['duration'] > 0): ?>
                                    <p>Duration: <?php echo gmdate("H:i:s", $media['duration']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="media_file" class="form-label">Replace Media File (Optional)</label>
                        <input type="file" class="form-control" id="media_file" name="media_file">
                        <div class="form-text">
                            Leave empty to keep current file. Max 2GB.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="media_type" class="form-label">New Media Type (if replacing file)</label>
                        <select class="form-select" id="media_type" name="media_type">
                            <option value="image" <?php echo $media['media_type'] == 'image' ? 'selected' : ''; ?>>Image</option>
                            <option value="video" <?php echo $media['media_type'] == 'video' ? 'selected' : ''; ?>>Video</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Thumbnail</label>
                        <div>
                            <?php if (!empty($media['thumbnail_path'])): ?>
                                <img src="<?php echo getMediaUrl($media['thumbnail_path'], true); ?>" 
                                     class="img-thumbnail" style="max-height: 150px;" 
                                     alt="Thumbnail for <?php echo htmlspecialchars($media['title_en']); ?>">
                                <?php if ($media['custom_thumbnail']): ?>
                                    <div class="form-text text-success mt-1">
                                        <i class="fas fa-check-circle"></i> Custom thumbnail
                                    </div>
                                <?php else: ?>
                                    <div class="form-text text-info mt-1">
                                        <i class="fas fa-cog"></i> Auto-generated thumbnail
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    No thumbnail available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="custom_thumbnail" class="form-label">Replace Thumbnail (Optional)</label>
                        <input type="file" class="form-control" id="custom_thumbnail" name="custom_thumbnail" accept="image/*">
                        <div class="form-text">
                            Upload a custom thumbnail image. Leave empty to keep current thumbnail.
                        </div>
                    </div>
                    
                    <?php if (!empty($media['thumbnail_path'])): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remove_thumbnail" name="remove_thumbnail" value="1">
                        <label class="form-check-label" for="remove_thumbnail">Remove current thumbnail</label>
                    </div>
                    <?php endif; ?>
                    
                    <!-- English Fields -->
                    <div class="border p-3 mb-3 rounded">
                        <h6 class="mb-3"><i class="fas fa-language me-2"></i>English Details</h6>
                        <div class="mb-3">
                            <label for="title_en" class="form-label">Title (English)</label>
                            <input type="text" class="form-control" id="title_en" name="title_en" 
                                   value="<?php echo htmlspecialchars($media['title_en']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control" id="description_en" name="description_en" rows="4"><?php echo htmlspecialchars($media['description_en']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Nepali Fields -->
                    <div class="border p-3 mb-3 rounded">
                        <h6 class="mb-3"><i class="fas fa-language me-2"></i>Nepali Details</h6>
                        <div class="mb-3">
                            <label for="title_ne" class="form-label">Title (नेपाली)</label>
                            <input type="text" class="form-control" id="title_ne" name="title_ne" 
                                   value="<?php echo htmlspecialchars($media['title_ne']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description_ne" class="form-label">Description (नेपाली)</label>
                            <textarea class="form-control" id="description_ne" name="description_ne" rows="4"><?php echo htmlspecialchars($media['description_ne']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">No Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo $media['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name_en']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $media['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $media['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="draft" <?php echo $media['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">File Size</label>
                                <p class="form-control-plaintext"><?php echo formatFileSize($media['file_size']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Views</label>
                                <p class="form-control-plaintext"><?php echo $media['views_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Uploaded</label>
                        <p class="form-control-plaintext"><?php echo date('M d, Y H:i', strtotime($media['created_at'])); ?></p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Media</button>
                    <a href="../media.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Media Statistics</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>File Size:</span>
                    <strong><?php echo formatFileSize($media['file_size']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Views:</span>
                    <strong><?php echo $media['views_count']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Likes:</span>
                    <strong><?php echo $media['likes_count']; ?></strong>
                </div>
                <?php if ($media['media_type'] == 'video' && $media['duration'] > 0): ?>
                <div class="d-flex justify-content-between mb-3">
                    <span>Duration:</span>
                    <strong><?php echo gmdate("H:i:s", $media['duration']); ?></strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-3">
                    <span>Status:</span>
                    <span class="badge bg-<?php 
                        echo $media['status'] == 'active' ? 'success' : 
                             ($media['status'] == 'inactive' ? 'secondary' : 'warning'); 
                    ?>">
                        <?php echo ucfirst($media['status']); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Thumbnail Type:</span>
                    <span class="badge bg-<?php echo $media['custom_thumbnail'] ? 'info' : 'secondary'; ?>">
                        <?php echo $media['custom_thumbnail'] ? 'Custom' : 'Auto-generated'; ?>
                    </span>
                </div>
                <hr>
                <div class="small text-muted">
                    <p><strong>Uploaded:</strong> <?php echo date('M d, Y', strtotime($media['created_at'])); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($media['updated_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $media['id']; ?>" class="btn btn-info">
                        <i class="fas fa-eye me-2"></i> View Details
                    </a>
                    <a href="delete.php?id=<?php echo $media['id']; ?>" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this media item?')">
                        <i class="fas fa-trash me-2"></i> Delete Media
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>