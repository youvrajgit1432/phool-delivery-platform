<?php
$page_title = "Media Gallery - Phool Delivery";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$assets_path = $base_url . '/phool-delivery-platform/public_html/assets';

// Define media file paths
$media_base_url = $base_url . '/phool-delivery-platform/admin/storage/uploads/media';
$media_thumbs_url = $media_base_url . '/thumbs';

// Function to get correct media URL
function getMediaUrl($file_path, $is_thumbnail = false) {
    global $media_base_url, $media_thumbs_url;
    
    if ($is_thumbnail) {
        return $media_thumbs_url . '/' . $file_path;
    }
    return $media_base_url . '/' . $file_path;
}
?>
<section class="media-gallery">
    <div class="container">
        <h1>Media Gallery</h1>
        <p class="page-description">Explore our collection of photos and videos from our farms and products.</p>
        
        <!-- Filters -->
        <div class="media-filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="category">Category:</label>
                    <select id="category" name="category" class="form-input">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($current_filters['category'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="type">Media Type:</label>
                    <select id="type" name="type" class="form-input">
                        <option value="">All Types</option>
                        <option value="image" <?php echo ($current_filters['type'] == 'image') ? 'selected' : ''; ?>>Photos</option>
                        <option value="video" <?php echo ($current_filters['type'] == 'video') ? 'selected' : ''; ?>>Videos</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" class="form-input" 
                           placeholder="Search media..." value="<?php echo htmlspecialchars($current_filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-filter">Apply Filters</button>
                    <a href="/phool-delivery-platform/public_html/media" class="btn btn-reset">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Media Grid -->
        <div class="media-grid">
            <?php if (empty($media_items)): ?>
                <div class="no-media">
                    <p>No media found matching your criteria.</p>
                </div>
            <?php else: ?>
                <?php foreach ($media_items as $media): ?>
                    <div class="media-item" data-type="<?php echo $media['media_type']; ?>">
                        <?php if ($media['media_type'] == 'image'): ?>
                            <a href="<?php echo getMediaUrl($media['file_path']); ?>" data-fancybox="gallery" data-caption="<?php echo htmlspecialchars($media['title']); ?>">
                                <img src="<?php echo getMediaUrl($media['thumbnail_path'] ?: $media['file_path'], true); ?>" 
                                     alt="<?php echo htmlspecialchars($media['title']); ?>" 
                                     class="media-thumbnail">
                                <div class="media-overlay">
                                    <h3><?php echo htmlspecialchars($media['title']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($media['description'], 0, 100) . (strlen($media['description']) > 100 ? '...' : '')); ?></p>
                                    <div class="media-stats">
                                        <span>👁️ <?php echo $media['views_count']; ?></span>
                                        <span>❤️ <?php echo $media['likes_count']; ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo getMediaUrl($media['file_path']); ?>" data-fancybox="gallery" data-caption="<?php echo htmlspecialchars($media['title']); ?>">
                                <div class="video-thumbnail">
                                    <img src="<?php echo getMediaUrl($media['thumbnail_path'], true); ?>" 
                                         alt="<?php echo htmlspecialchars($media['title']); ?>" 
                                         class="media-thumbnail">
                                    <div class="play-button">▶</div>
                                    <div class="media-overlay">
                                        <h3><?php echo htmlspecialchars($media['title']); ?></h3>
                                        <p><?php echo htmlspecialchars(substr($media['description'], 0, 100) . (strlen($media['description']) > 100 ? '...' : '')); ?></p>
                                        <div class="media-stats">
                                            <span>👁️ <?php echo $media['views_count']; ?></span>
                                            <span>❤️ <?php echo $media['likes_count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Include Fancybox for lightbox functionality -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js"></script>

<script>
// Initialize Fancybox
Fancybox.bind("[data-fancybox]", {
    // Options
});

// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.filter-form');
    const categorySelect = document.getElementById('category');
    const typeSelect = document.getElementById('type');
    
    // Auto-submit form when filters change
    categorySelect.addEventListener('change', function() {
        filterForm.submit();
    });
    
    typeSelect.addEventListener('change', function() {
        filterForm.submit();
    });
});
</script>

<style>
.media-gallery {
    padding: 40px 0;
}

.media-filters {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 5px;
    font-weight: 500;
}

.btn-filter {
    background: #2d5016;
    color: white;
}

.btn-reset {
    background: #6c757d;
    color: white;
    margin-top: 5px;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.media-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.media-item:hover {
    transform: translateY(-5px);
}

.media-thumbnail {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.video-thumbnail {
    position: relative;
}

.play-button {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 3rem;
    color: white;
    text-shadow: 2px 2px 10px rgba(0,0,0,0.7);
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.media-item:hover .play-button {
    opacity: 1;
}

.media-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    padding: 15px;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.media-item:hover .media-overlay {
    transform: translateY(0);
}

.media-overlay h3 {
    margin: 0 0 5px 0;
    font-size: 1.1rem;
}

.media-overlay p {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.media-stats {
    display: flex;
    gap: 15px;
    font-size: 0.8rem;
    opacity: 0.8;
}

.no-media {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    color: #666;
}

@media (max-width: 768px) {
    .filter-form {
        grid-template-columns: 1fr;
    }
    
    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}
</style>