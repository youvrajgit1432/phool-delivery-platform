<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

$page_title = "Manage Ads";
$error_message = '';
$success_message = '';

// Fetch all ads
$ads = $pdo->query("SELECT * FROM ads ORDER BY 
    CASE WHEN status = 'active' THEN 1 ELSE 2 END, 
    created_at DESC")->fetchAll();

// Count active ads
$active_ads_count = $pdo->query("SELECT COUNT(*) as count FROM ads WHERE status = 'active'")->fetch()['count'];

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Ads</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge bg-<?php echo $active_ads_count >= 2 ? 'success' : 'warning'; ?> me-2">
            Active Ads: <?php echo $active_ads_count; ?>/2
        </span>
    </div>
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
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload New Ad</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="add_ad.php" enctype="multipart/form-data" id="uploadForm">
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
                    
                    <div class="mb-3">
                        <label class="form-label">Title (Optional)</label>
                        <input type="text" class="form-control" name="title" placeholder="Enter ad title" maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Enter ad description" maxlength="500"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Note:</strong> Maximum 2 ads can be active at the same time.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Upload Ad</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Active Ads Preview (<?php echo $active_ads_count; ?>/2)</h5>
            </div>
            <div class="card-body">
                <?php if ($active_ads_count > 0): ?>
                    <div class="row">
                        <?php foreach ($ads as $ad): ?>
                            <?php if ($ad['status'] === 'active'): ?>
                                <div class="col-12 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <div class="mb-3">
                                                <?php if ($ad['media_type'] === 'photo'): ?>
                                                    <img src="<?php echo getAdUrl($ad['file_name']); ?>" 
                                                         class="img-fluid rounded" 
                                                         alt="<?php echo htmlspecialchars($ad['title'] ?? 'Ad Image'); ?>"
                                                         style="max-height: 200px; object-fit: contain;">
                                                <?php else: ?>
                                                    <video controls class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
                                                        <source src="<?php echo getAdUrl($ad['file_name']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="text-start">
                                                <?php if (!empty($ad['title'])): ?>
                                                    <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($ad['title']); ?></h6>
                                                <?php endif; ?>
                                                
                                                <p class="card-text"><small class="text-muted">Type: <?php echo ucfirst($ad['media_type']); ?></small></p>
                                                <p class="card-text"><small class="text-muted">Uploaded: <?php echo date('M d, Y H:i', strtotime($ad['created_at'])); ?></small></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ad fa-4x text-muted mb-3"></i>
                        <h5>No Active Ads</h5>
                        <p class="text-muted">There are currently no active ads. Upload new ads and activate them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">All Ads</h5>
    </div>
    <div class="card-body">
        <?php if (count($ads) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads as $ad): ?>
                            <tr>
                                <td>
                                    <?php if ($ad['media_type'] === 'photo'): ?>
                                        <img src="<?php echo getAdUrl($ad['file_name']); ?>" 
                                             class="img-thumbnail" 
                                             alt="Preview" 
                                             style="width: 80px; height: 60px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-dark text-white d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 60px; border-radius: 0.375rem;">
                                            <i class="fas fa-video"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($ad['title']) ? htmlspecialchars($ad['title']) : '<span class="text-muted">No title</span>'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $ad['media_type'] === 'photo' ? 'info' : 'primary'; ?>">
                                        <?php echo ucfirst($ad['media_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $ad['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($ad['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatFileSize($ad['file_size'] ?? 0); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($ad['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($ad['status'] === 'inactive'): ?>
                                            <a href="activate_ad.php?id=<?php echo $ad['id']; ?>" 
                                               class="btn btn-success btn-sm <?php echo $active_ads_count >= 2 ? 'disabled' : ''; ?>"
                                               title="<?php echo $active_ads_count >= 2 ? 'Maximum 2 active ads reached' : 'Activate ad'; ?>">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="deactivate_ad.php?id=<?php echo $ad['id']; ?>" 
                                               class="btn btn-warning btn-sm"
                                               title="Deactivate ad">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="edit_ad.php?id=<?php echo $ad['id']; ?>" 
                                           class="btn btn-primary btn-sm"
                                           title="Edit ad">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <a href="delete_ad.php?id=<?php echo $ad['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           title="Delete ad"
                                           onclick="return confirm('Are you sure you want to delete this ad? This action cannot be undone.')">
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
                <i class="fas fa-ad fa-4x text-muted mb-3"></i>
                <h5>No Ads Found</h5>
                <p class="text-muted">You haven't uploaded any ads yet. Start by uploading your first ad.</p>
            </div>
        <?php endif; ?>
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
</script>

<?php
include '../app/views/layouts/footer.php';
?>