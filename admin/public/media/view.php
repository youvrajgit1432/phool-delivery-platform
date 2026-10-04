<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
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
$stmt = $pdo->prepare("
    SELECT m.*, mc.name_en as category_name, u.full_name as uploaded_by_name 
    FROM media_items m 
    LEFT JOIN media_categories mc ON m.category_id = mc.id 
    LEFT JOIN users u ON m.uploaded_by = u.id 
    WHERE m.id = ?
");
$stmt->execute([$media_id]);
$media = $stmt->fetch();

if (!$media) {
    $_SESSION['error_message'] = "Media not found.";
    header("Location: ../media.php");
    exit;
}

// Update view count
$pdo->prepare("UPDATE media_items SET views_count = views_count + 1 WHERE id = ?")->execute([$media_id]);

// Set page title
$page_title = "View Media - " . htmlspecialchars($media['title_en']) . " - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">View Media: <?php echo htmlspecialchars($media['title_en']); ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../media.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Media
        </a>
        <a href="edit.php?id=<?php echo $media['id']; ?>" class="btn btn-sm btn-primary ms-2">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Media Preview</h5>
            </div>
            <div class="card-body text-center">
                <?php if ($media['media_type'] == 'image'): ?>
                    <img src="<?php echo getMediaUrl($media['file_path']); ?>" 
                         class="img-fluid rounded" 
                         alt="<?php echo htmlspecialchars($media['title_en']); ?>"
                         style="max-height: 500px;">
                <?php else: ?>
                    <video controls class="w-100" style="max-height: 500px;">
                        <source src="<?php echo getMediaUrl($media['file_path']); ?>" 
                                type="video/<?php echo pathinfo($media['file_path'], PATHINFO_EXTENSION); ?>">
                        Your browser does not support the video tag.
                    </video>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Media Details</h5>
            </div>
            <div class="card-body">
                <!-- English Details -->
                <div class="border p-3 mb-3 rounded">
                    <h6 class="mb-3"><i class="fas fa-language me-2"></i>English Details</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title (English)</label>
                        <p><?php echo htmlspecialchars($media['title_en']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description (English)</label>
                        <p><?php echo nl2br(htmlspecialchars($media['description_en'])); ?></p>
                    </div>
                </div>
                
                <!-- Nepali Details -->
                <div class="border p-3 mb-3 rounded">
                    <h6 class="mb-3"><i class="fas fa-language me-2"></i>Nepali Details</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title (नेपाली)</label>
                        <p><?php echo htmlspecialchars($media['title_ne']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description (नेपाली)</label>
                        <p><?php echo nl2br(htmlspecialchars($media['description_ne'])); ?></p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Media Type</label>
                            <p>
                                <span class="badge bg-<?php echo $media['media_type'] == 'image' ? 'info' : 'primary'; ?>">
                                    <?php echo ucfirst($media['media_type']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <p>
                                <?php if ($media['category_name']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($media['category_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">No category</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <p>
                                <span class="badge bg-<?php 
                                    echo $media['status'] == 'active' ? 'success' : 
                                         ($media['status'] == 'inactive' ? 'secondary' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($media['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">File Size</label>
                            <p><?php echo formatFileSize($media['file_size']); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if ($media['media_type'] == 'video' && $media['duration'] > 0): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Duration</label>
                            <p><?php echo gmdate("H:i:s", $media['duration']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Uploaded By</label>
                            <p><?php echo htmlspecialchars($media['uploaded_by_name']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Upload Date</label>
                            <p><?php echo date('M d, Y H:i', strtotime($media['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Views</label>
                            <p><?php echo $media['views_count']; ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Likes</label>
                            <p><?php echo $media['likes_count']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Thumbnail</h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($media['thumbnail_path'])): ?>
                    <img src="<?php echo getMediaUrl($media['thumbnail_path'], true); ?>" 
                         class="img-thumbnail" 
                         alt="Thumbnail for <?php echo htmlspecialchars($media['title_en']); ?>"
                         style="max-height: 200px;">
                    <div class="mt-2">
                        <span class="badge bg-<?php echo $media['custom_thumbnail'] ? 'info' : 'secondary'; ?>">
                            <?php echo $media['custom_thumbnail'] ? 'Custom Thumbnail' : 'Auto-generated Thumbnail'; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No thumbnail available
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">File Information</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Filename:</span>
                    <strong><?php echo $media['file_path']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>File Size:</span>
                    <strong><?php echo formatFileSize($media['file_size']); ?></strong>
                </div>
                <?php if ($media['media_type'] == 'video' && $media['duration'] > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Duration:</span>
                    <strong><?php echo gmdate("H:i:s", $media['duration']); ?></strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Media Type:</span>
                    <strong><?php echo ucfirst($media['media_type']); ?></strong>
                </div>
                <?php if (!empty($media['thumbnail_path'])): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Thumbnail:</span>
                    <strong><?php echo $media['thumbnail_path']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Thumbnail Type:</span>
                    <strong><?php echo $media['custom_thumbnail'] ? 'Custom' : 'Auto-generated'; ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo $media['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit Media
                    </a>
                    <a href="delete.php?id=<?php echo $media['id']; ?>" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this media item?')">
                        <i class="fas fa-trash me-2"></i> Delete Media
                    </a>
                    <a href="<?php echo getMediaUrl($media['file_path']); ?>" 
                       class="btn btn-info" download>
                        <i class="fas fa-download me-2"></i> Download Media
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