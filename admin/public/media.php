<?php
// public/media.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle category filter and search
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$media_type = isset($_GET['type']) ? $_GET['type'] : '';

// Build query
$where_clause = "WHERE 1=1";
$params = [];

if ($category_id > 0) {
    $where_clause .= " AND m.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $where_clause .= " AND (m.title_en LIKE ? OR m.title_ne LIKE ? OR m.description_en LIKE ? OR m.description_ne LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($media_type) && in_array($media_type, ['image', 'video'])) {
    $where_clause .= " AND m.media_type = ?";
    $params[] = $media_type;
}

// Get all media items
$media_items = $pdo->prepare("
    SELECT m.*, mc.name_en as category_name, u.full_name as uploaded_by_name
    FROM media_items m 
    LEFT JOIN media_categories mc ON m.category_id = mc.id 
    LEFT JOIN users u ON m.uploaded_by = u.id 
    $where_clause 
    ORDER BY m.created_at DESC
");
$media_items->execute($params);
$media_items = $media_items->fetchAll();

// Get all media categories
$categories = $pdo->query("SELECT * FROM media_categories WHERE status = 'active' ORDER BY name_en")->fetchAll();

// Set page title
$page_title = "Media Management - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Media Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="media.php" class="btn btn-sm btn-outline-secondary active">All Media</a>
            <a href="media.php?type=image" class="btn btn-sm btn-outline-info">Images</a>
            <a href="media.php?type=video" class="btn btn-sm btn-outline-primary">Videos</a>
        </div>
        <a href="media/add.php" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i> Add New Media
        </a>
        <a href="media/categories.php" class="btn btn-sm btn-secondary ms-2">
            <i class="fas fa-tags me-1"></i> Categories
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Filters and Search -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="categoryFilter" onchange="window.location.href='media.php?category_id='+this.value">
            <option value="0" <?php echo $category_id == 0 ? 'selected' : ''; ?>>All Categories</option>
            <?php foreach ($categories as $category): ?>
            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($category['name_en']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <form method="GET" action="media.php" class="input-group">
            <input type="text" class="form-control" placeholder="Search media..." name="search" value="<?php echo htmlspecialchars($search); ?>">
            <?php if ($category_id > 0): ?>
            <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
            <?php endif; ?>
            <?php if (!empty($media_type)): ?>
            <input type="hidden" name="type" value="<?php echo $media_type; ?>">
            <?php endif; ?>
            <button class="btn btn-outline-secondary" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
</div>

<!-- Media Grid -->
<div class="row" id="mediaGrid">
    <?php if (count($media_items) > 0): ?>
        <?php foreach ($media_items as $index => $media): ?>
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-img-container position-relative" style="height: 200px; overflow: hidden;">
                    <?php if ($media['media_type'] == 'video'): ?>
                        <!-- Video with playable thumbnail -->
                        <div class="video-thumbnail-wrapper position-relative w-100 h-100">
                            <?php if (!empty($media['thumbnail_path'])): ?>
                                <?php
                                // Use the helper function for proper thumbnail URL
                                $thumbnail_url = getMediaUrl($media['thumbnail_path'], true);
                                $video_url = getMediaUrl($media['file_path'], false);
                                ?>
                                <img src="<?php echo $thumbnail_url; ?>" 
                                     class="card-img-top video-thumbnail h-100 w-100" 
                                     alt="<?php echo htmlspecialchars($media['title_en']); ?>"
                                     style="object-fit: cover; cursor: pointer;"
                                     data-media-type="video"
                                     data-video-src="<?php echo $video_url; ?>"
                                     data-media-id="<?php echo $media['id']; ?>"
                                     data-index="<?php echo $index; ?>">
                            <?php else: ?>
                                <?php
                                $video_url = getMediaUrl($media['file_path'], false);
                                ?>
                                <div class="bg-dark d-flex align-items-center justify-content-center h-100 w-100 video-thumbnail" 
                                     style="cursor: pointer;"
                                     data-media-type="video"
                                     data-video-src="<?php echo $video_url; ?>"
                                     data-media-id="<?php echo $media['id']; ?>"
                                     data-index="<?php echo $index; ?>">
                                    <i class="fas fa-play-circle text-white" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="video-play-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-play-circle text-white" style="font-size: 3rem; opacity: 0.8;"></i>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Image with click to view in carousel -->
                        <?php if (!empty($media['thumbnail_path'])): ?>
                            <?php
                            // Use the helper function for proper thumbnail URL
                            $thumbnail_url = getMediaUrl($media['thumbnail_path'], true);
                            $image_url = getMediaUrl($media['file_path'], false);
                            ?>
                            <img src="<?php echo $thumbnail_url; ?>" 
                                 class="card-img-top image-thumbnail h-100 w-100" 
                                 alt="<?php echo htmlspecialchars($media['title_en']); ?>"
                                 style="object-fit: cover; cursor: pointer;"
                                 data-media-type="image"
                                 data-image-src="<?php echo $image_url; ?>"
                                 data-media-id="<?php echo $media['id']; ?>"
                                 data-index="<?php echo $index; ?>">
                        <?php else: ?>
                            <?php
                            // Use the helper function for proper image URL
                            $image_url = getMediaUrl($media['file_path'], false);
                            ?>
                            <img src="<?php echo $image_url; ?>" 
                                 class="card-img-top image-thumbnail h-100 w-100" 
                                 alt="<?php echo htmlspecialchars($media['title_en']); ?>"
                                 style="object-fit: cover; cursor: pointer;"
                                 data-media-type="image"
                                 data-image-src="<?php echo $image_url; ?>"
                                 data-media-id="<?php echo $media['id']; ?>"
                                 data-index="<?php echo $index; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($media['title_en']); ?></h6>
                    <?php if (!empty($media['title_ne'])): ?>
                    <p class="card-text small text-muted"><?php echo htmlspecialchars($media['title_ne']); ?></p>
                    <?php endif; ?>
                    <p class="card-text small text-muted">
                        <?php echo strlen($media['description_en']) > 100 ? 
                            substr($media['description_en'], 0, 100) . '...' : 
                            $media['description_en']; ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary"><?php echo ucfirst($media['media_type']); ?></span>
                        <?php if ($media['category_name']): ?>
                        <span class="badge bg-info"><?php echo htmlspecialchars($media['category_name']); ?></span>
                        <?php endif; ?>
                        <?php if ($media['custom_thumbnail']): ?>
                        <span class="badge bg-success" title="Custom Thumbnail"><i class="fas fa-image"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="btn-group btn-group-sm">
                            <a href="media/edit.php?id=<?php echo $media['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="media/delete.php?id=<?php echo $media['id']; ?>" class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this media item?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="media/view.php?id=<?php echo $media['id']; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        <div class="text-muted small">
                            <div><i class="fas fa-eye"></i> <?php echo $media['views_count']; ?></div>
                            <div><i class="fas fa-heart"></i> <?php echo $media['likes_count']; ?></div>
                        </div>
                    </div>
                    <div class="mt-2 small text-muted">
                        Uploaded by: <?php echo htmlspecialchars($media['uploaded_by_name']); ?><br>
                        <?php echo date('M d, Y', strtotime($media['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No media items found. 
                <a href="media/add.php" class="alert-link">Add your first media item</a>.
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Video Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">Video Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <video id="modalVideo" controls class="w-100" style="max-height: 70vh;">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="viewDetailsBtn" class="btn btn-primary">View Details</a>
            </div>
        </div>
    </div>
</div>

<!-- Image Carousel Modal -->
<div class="modal fade" id="imageCarouselModal" tabindex="-1" aria-labelledby="imageCarouselModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageCarouselModalLabel">Image Gallery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner" id="carousel-inner">
                        <!-- Carousel items will be dynamically added here -->
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
                <div class="mt-3">
                    <h6 id="carouselImageTitle" class="text-center"></h6>
                    <p id="carouselImageDescription" class="text-center text-muted small"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="carouselViewDetailsBtn" class="btn btn-primary">View Details</a>
            </div>
        </div>
    </div>
</div>

<style>
.video-thumbnail-wrapper, .image-thumbnail {
    cursor: pointer;
}
.video-thumbnail-wrapper:hover .video-play-overlay {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.3);
}
.video-play-overlay {
    opacity: 0;
    transition: opacity 0.3s ease, background-color 0.3s ease;
    pointer-events: none;
}
.card-img-container {
    background-color: #f8f9fa;
}
.carousel-item img {
    max-height: 70vh;
    object-fit: contain;
    margin: 0 auto;
}
.carousel-control-prev, .carousel-control-next {
    width: 5%;
}
</style>

<script>
// Store all media items for carousel navigation
const allMediaItems = <?php echo json_encode($media_items); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Video thumbnail click handler
    const videoThumbnails = document.querySelectorAll('.video-thumbnail');
    const videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
    const modalVideo = document.getElementById('modalVideo');
    const videoModalLabel = document.getElementById('videoModalLabel');
    const viewDetailsBtn = document.getElementById('viewDetailsBtn');
    
    videoThumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            const videoSrc = this.getAttribute('data-video-src');
            const mediaId = this.getAttribute('data-media-id');
            const mediaTitle = this.closest('.card').querySelector('.card-title').textContent;
            
            // Set video source and title
            modalVideo.src = videoSrc;
            videoModalLabel.textContent = mediaTitle;
            viewDetailsBtn.href = `media/view.php?id=${mediaId}`;
            
            // Show modal
            videoModal.show();
            
            // Play video when modal is shown
            modalVideo.addEventListener('loadeddata', function() {
                modalVideo.play().catch(e => {
                    console.log('Autoplay prevented:', e);
                });
            }, { once: true });
        });
    });
    
    // Image thumbnail click handler
    const imageThumbnails = document.querySelectorAll('.image-thumbnail');
    const imageCarouselModal = new bootstrap.Modal(document.getElementById('imageCarouselModal'));
    const carouselInner = document.getElementById('carousel-inner');
    const carouselImageTitle = document.getElementById('carouselImageTitle');
    const carouselImageDescription = document.getElementById('carouselImageDescription');
    const carouselViewDetailsBtn = document.getElementById('carouselViewDetailsBtn');
    
    imageThumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            const imageSrc = this.getAttribute('data-image-src');
            const mediaId = this.getAttribute('data-media-id');
            const index = parseInt(this.getAttribute('data-index'));
            const mediaTitle = this.closest('.card').querySelector('.card-title').textContent;
            const mediaDescription = this.closest('.card').querySelector('.card-text').textContent;
            
            // Build carousel items
            buildCarousel(index);
            
            // Set title and description
            carouselImageTitle.textContent = mediaTitle;
            carouselImageDescription.textContent = mediaDescription;
            carouselViewDetailsBtn.href = `media/view.php?id=${mediaId}`;
            
            // Show modal
            imageCarouselModal.show();
        });
    });
    
    // Build carousel with all images
    function buildCarousel(activeIndex) {
        carouselInner.innerHTML = '';
        
        allMediaItems.forEach((media, index) => {
            if (media.media_type === 'image') {
                const carouselItem = document.createElement('div');
                carouselItem.className = `carousel-item ${index === activeIndex ? 'active' : ''}`;
                
                const img = document.createElement('img');
                // Use the helper function to get proper image URL
                img.src = `<?php echo getStorageUrl('media/'); ?>${media.file_path}`;
                img.className = 'd-block w-100';
                img.alt = media.title_en;
                
                carouselItem.appendChild(img);
                carouselInner.appendChild(carouselItem);
            }
        });
        
        // Update carousel controls if there's only one item
        if (document.querySelectorAll('.carousel-item').length <= 1) {
            document.querySelectorAll('.carousel-control-prev, .carousel-control-next').forEach(control => {
                control.style.display = 'none';
            });
        } else {
            document.querySelectorAll('.carousel-control-prev, .carousel-control-next').forEach(control => {
                control.style.display = 'block';
            });
        }
    }
    
    // Update title and description when carousel slides
    document.getElementById('imageCarousel').addEventListener('slid.bs.carousel', function(event) {
        const activeIndex = event.to;
        let imageIndex = 0;
        
        // Find the corresponding image index in allMediaItems
        for (let i = 0; i < allMediaItems.length; i++) {
            if (allMediaItems[i].media_type === 'image') {
                if (imageIndex === activeIndex) {
                    carouselImageTitle.textContent = allMediaItems[i].title_en;
                    carouselImageDescription.textContent = allMediaItems[i].description_en;
                    carouselViewDetailsBtn.href = `media/view.php?id=${allMediaItems[i].id}`;
                    break;
                }
                imageIndex++;
            }
        }
    });
    
    // Reset video when modal is closed
    document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
        modalVideo.pause();
        modalVideo.currentTime = 0;
        modalVideo.src = '';
    });
    
    // Handle video ended event to loop
    modalVideo.addEventListener('ended', function() {
        this.currentTime = 0;
        this.play();
    });
});
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>
<style>
/* Modern color scheme */
.d-flex.justify-content-between {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
}

.h2 {
    color: #2c3e50;
    font-weight: 700;
    font-size: 1.8rem;
}

/* Button styles */
.btn {
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.25s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.btn-outline-secondary.active {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    border-color: #5a6268;
    color: white;
}

.btn-outline-info {
    color: #17a2b8;
    border-color: #17a2b8;
}

.btn-outline-info:hover {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.btn-outline-primary {
    color: #007bff;
    border-color: #007bff;
}

.btn-outline-primary:hover {
    background: linear-gradient(135deg, #007bff 0%, #0069d9 100%);
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border-color: #2980b9;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
    border-color: #1c6ea4;
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    border-color: #7f8c8d;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #7f8c8d 0%, #6c7b7d 100%);
    border-color: #6c7b7d;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    border-color: #c0392b;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
    border-color: #a93226;
}

/* Alert styles */
.alert {
    border-radius: 8px;
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-dismissible .btn-close {
    padding: 0.75rem;
}

/* Form controls */
.form-select, .form-control {
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 0.5rem 0.75rem;
    transition: all 0.2s ease;
}

.form-select:focus, .form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

.input-group .btn-outline-secondary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border-color: #2980b9;
    color: white;
}

.input-group .btn-outline-secondary:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
    border-color: #1c6ea4;
}

/* Card styles */
.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.15);
}

.card-img-top {
    transition: transform 0.5s ease;
}

.card:hover .card-img-top {
    transform: scale(1.05);
}

.card-body {
    padding: 1.25rem;
}

.card-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.card-text {
    color: #7f8c8d;
    line-height: 1.5;
}

.card-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.25rem;
}

/* Badge styles */
.badge {
    padding: 0.4em 0.6em;
    border-radius: 4px;
    font-weight: 500;
}

.bg-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%) !important;
}

.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
}

/* Grid layout improvements */
#mediaGrid {
    margin: 0 -10px;
}

.col-md-4, .col-lg-3 {
    padding: 0 10px;
}

/* Button group adjustments */
.btn-group-sm > .btn {
    border-radius: 4px;
    margin-right: 2px;
}

/* Text and icon adjustments */
.text-muted {
    color: #95a5a6 !important;
}

.fas, .fa {
    transition: transform 0.2s ease;
}

.btn:hover .fas, .btn:hover .fa {
    transform: scale(1.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn-toolbar {
        width: 100%;
        justify-content: flex-start;
    }
    
    .col-md-3, .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style> 