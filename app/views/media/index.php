<?php
// app/views/log/media.php

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Initialize language helper
LanguageHelper::initialize();
$current_lang = LanguageHelper::getCurrentLanguage();

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

$page_title = htmlspecialchars(LanguageHelper::t('media_gallery', 'Media Gallery') . " - Phool Delivery", ENT_QUOTES, 'UTF-8');
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');
$media_base_url = $pathConfig->get('media');
$media_thumbs_url = $pathConfig->getImagePath('', 'media_thumb');

function getMediaUrl($file_path, $is_thumbnail = false) {
    global $pathConfig;
    if (empty($file_path)) return '';
    
    // Sanitize file path to prevent directory traversal
    $safe_path = basename($file_path);
    
    if ($is_thumbnail) {
        return $pathConfig->getImagePath($safe_path, 'media_thumb');
    } else {
        return $pathConfig->getImagePath($safe_path, 'media');
    }
}

function getLocalizedTitle($item) {
    return htmlspecialchars(LanguageHelper::getLocalizedText($item, 'title') ?? '', ENT_QUOTES, 'UTF-8');
}

function getLocalizedDescription($item) {
    return htmlspecialchars(LanguageHelper::getLocalizedText($item, 'description') ?? '', ENT_QUOTES, 'UTF-8');
}

function getLocalizedCategoryName($category) {
    return htmlspecialchars(LanguageHelper::getLocalizedText($category, 'name') ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Token generation (if needed for forms)
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$single_media_view = false;
$current_media = null;
$comments = [];
$liked = false;

// Validate and process media ID
if (isset($_GET['id'])) {
    $media_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($media_id !== false && $media_id > 0) {
        $current_media = $mediaController->view($media_id);
        
        if ($current_media) {
            $single_media_view = true;
            // Sample comments - in real app, fetch from database with proper sanitization
            $comments = [
             ];
            $liked = false;
        }
    }
}

// Get current filter values from URL with validation
$current_category = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT) : '';
$current_type = isset($_GET['type']) ? ($_GET['type'] === 'image' || $_GET['type'] === 'video' ? $_GET['type'] : '') : '';
?>

<section class="media-page">
    <div class="container">
        <?php if ($single_media_view && $current_media): ?>
            <a href="?" class="back-button" data-aos="fade-right" data-aos-delay="100"><i class="fas fa-arrow-left"></i> <?= htmlspecialchars(LanguageHelper::t('back_to_gallery', 'Back to Gallery'), ENT_QUOTES, 'UTF-8') ?></a>
            <div class="single-media-view">
                <div class="media-player" data-aos="zoom-in" data-aos-delay="200">
                    <?php if ($current_media['media_type'] == 'image'): ?>
                        <img src="<?= getMediaUrl($current_media['file_path']) ?>" 
                             alt="<?= getLocalizedTitle($current_media) ?>"
                             loading="lazy">
                    <?php else: ?>
                        <video controls 
                               poster="<?= getMediaUrl($current_media['thumbnail_path'] ?: $current_media['file_path'], true) ?>"
                               preload="metadata">
                            <source src="<?= getMediaUrl($current_media['file_path']) ?>" type="video/mp4">
                            <?= htmlspecialchars(LanguageHelper::t('browser_no_support', 'Your browser does not support the video tag.'), ENT_QUOTES, 'UTF-8') ?>
                        </video>
                    <?php endif; ?>
                </div>
                
                <div class="media-actions" data-aos="fade-up" data-aos-delay="300">
                    <button class="action-btn share-btn" 
                            data-media-url="<?= htmlspecialchars($pathConfig->url($_SERVER['REQUEST_URI']), ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-share-alt"></i> <?= htmlspecialchars(LanguageHelper::t('share', 'Share'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                
                <div class="media-details" data-aos="fade-up" data-aos-delay="400">
                    <h2><?= getLocalizedTitle($current_media) ?></h2>
                    <div class="media-stats">
                        <?php 
                        // Get category name dynamically
                        $category_name = '';
                        foreach ($categories as $category) {
                            if ($category['id'] == $current_media['category_id']) {
                                $category_name = getLocalizedCategoryName($category);
                                break;
                            }
                        }
                        ?>
                        <span class="media-category"><?= $category_name ?></span>
                        <span><?= htmlspecialchars(LanguageHelper::t('uploaded_on', 'Uploaded on'), ENT_QUOTES, 'UTF-8') ?> <?= date('F j, Y', strtotime($current_media['created_at'])) ?></span>
                        <span><?= htmlspecialchars(LanguageHelper::t('views', 'Views'), ENT_QUOTES, 'UTF-8') ?>: <?= (int)$current_media['views_count'] ?></span>
                    </div>
                    <p><?= nl2br(getLocalizedDescription($current_media)) ?></p>
                </div>
                
                <?php if (!empty($comments)): ?>
                <div class="media-comments" data-aos="fade-up" data-aos-delay="500">
                    <h3><?= htmlspecialchars(LanguageHelper::t('comments', 'Comments'), ENT_QUOTES, 'UTF-8') ?> (<?= count($comments) ?>)</h3>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <strong><?= htmlspecialchars($comment['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="comment-date"><?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1 data-aos="fade-up" data-aos-delay="100"><?= htmlspecialchars(LanguageHelper::t('media_gallery', 'Media Gallery'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="page-description" data-aos="fade-up" data-aos-delay="200"><?= htmlspecialchars(LanguageHelper::t('media_gallery_description', 'Explore our collection of photos and videos from flower cultivation, farm activities, and decoration ideas.'), ENT_QUOTES, 'UTF-8') ?></p>
            
            <!-- Filter Toggle Button for Mobile -->
            <button id="filter-toggle" class="filter-toggle-btn" data-aos="zoom-in" data-aos-delay="300">
                <i class="fas fa-filter"></i> <?= htmlspecialchars(LanguageHelper::t('filters', 'Filters'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            
            <!-- Filter Section -->
            <div class="media-filters" id="media-filters" data-aos="fade-up" data-aos-delay="400">
                <div class="filter-row">
                    <div class="filter-group">
                        <select id="category-filter" class="form-input">
                            <option value=""><?= htmlspecialchars(LanguageHelper::t('all_categories', 'All Categories'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $category_name = getLocalizedCategoryName($category);
                                ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $current_category == $category['id'] ? 'selected' : '' ?>>
                                    <?= $category_name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select id="type-filter" class="form-input">
                            <option value=""><?= htmlspecialchars(LanguageHelper::t('photo_video', 'Photo & Video'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="image" <?= $current_type == 'image' ? 'selected' : '' ?>><?= htmlspecialchars(LanguageHelper::t('photos', 'Photos'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="video" <?= $current_type == 'video' ? 'selected' : '' ?>><?= htmlspecialchars(LanguageHelper::t('videos', 'Videos'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-buttons">
                        <button id="apply-filters" class="btn btn-primary"><?= htmlspecialchars(LanguageHelper::t('apply', 'Apply'), ENT_QUOTES, 'UTF-8') ?></button>
                        <button id="reset-filters" class="btn btn-secondary"><?= htmlspecialchars(LanguageHelper::t('reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </div>
            </div>
            
            <div class="media-grid">
                <?php if (empty($media_items)): ?>
                    <div class="no-media" data-aos="fade-up"><p><?= htmlspecialchars(LanguageHelper::t('no_media_found', 'No media found matching your criteria.'), ENT_QUOTES, 'UTF-8') ?></p></div>
                <?php else: foreach ($media_items as $index => $item): ?>
                    <div class="media-item" data-category="<?= (int)$item['category_id'] ?>" data-type="<?= htmlspecialchars($item['media_type'], ENT_QUOTES, 'UTF-8') ?>" data-aos="zoom-in" data-aos-delay="<?= ($index % 8) * 100 + 500 ?>">
                        <a href="?id=<?= (int)$item['id'] ?>">
                            <?php if ($item['media_type'] == 'image'): ?>
                                <img src="<?= getMediaUrl($item['thumbnail_path'] ?: $item['file_path'], true) ?>" 
                                     alt="<?= getLocalizedTitle($item) ?>" 
                                     class="media-thumbnail"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="video-thumbnail">
                                    <img src="<?= getMediaUrl($item['thumbnail_path'] ?: $item['file_path'], true) ?>" 
                                         alt="<?= getLocalizedTitle($item) ?>" 
                                         class="media-thumbnail"
                                         loading="lazy">
                                    <div class="play-icon">
                                        <svg viewBox="0 0 24 24" fill="#ffffff"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                    <?php if ($item['duration'] > 0): ?>
                                        <div class="video-duration"><?= gmdate("i:s", (int)$item['duration']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                        
                        <div class="media-info">
                            <h3 class="media-title"><?= getLocalizedTitle($item) ?></h3>
                            <p class="media-description"><?= htmlspecialchars(substr(getLocalizedDescription($item), 0, 100) . (strlen(getLocalizedDescription($item)) > 100 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="media-meta">
                                <?php 
                                // Get category name for each media item
                                $item_category_name = '';
                                foreach ($categories as $category) {
                                    if ($category['id'] == $item['category_id']) {
                                        $item_category_name = getLocalizedCategoryName($category);
                                        break;
                                    }
                                }
                                ?>
                                <span class="media-category"><?= $item_category_name ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            
            <?php if (!empty($popular_media)): ?>
            <div class="popular-media-section" data-aos="fade-up" data-aos-delay="600">
                <h2><?= htmlspecialchars(LanguageHelper::t('popular_media', 'Popular Media'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="popular-media-grid">
                    <?php foreach ($popular_media as $index => $item): ?>
                        <div class="popular-media-item" data-aos="zoom-in" data-aos-delay="<?= ($index % 4) * 100 + 700 ?>">
                            <a href="?id=<?= (int)$item['id'] ?>">
                                <?php if ($item['media_type'] == 'image'): ?>
                                    <img src="<?= getMediaUrl($item['thumbnail_path'] ?: $item['file_path'], true) ?>" 
                                         alt="<?= getLocalizedTitle($item) ?>" 
                                         class="popular-media-thumbnail"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="video-thumbnail">
                                        <img src="<?= getMediaUrl($item['thumbnail_path'] ?: $item['file_path'], true) ?>" 
                                             alt="<?= getLocalizedTitle($item) ?>" 
                                             class="popular-media-thumbnail"
                                             loading="lazy">
                                        <div class="play-icon">
                                            <svg viewBox="0 0 24 24" fill="#ffffff"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="popular-media-info">
                                <h4><?= getLocalizedTitle($item) ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
// Security: Add Content Security Policy meta tag if not set in headers
const cspMeta = document.createElement('meta');
cspMeta.httpEquiv = 'Content-Security-Policy';
cspMeta.content = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:; media-src 'self' https:;";
document.head.appendChild(cspMeta);

document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('category-filter');
    const typeFilter = document.getElementById('type-filter');
    const applyFilters = document.getElementById('apply-filters');
    const resetFilters = document.getElementById('reset-filters');
    const likeButtons = document.querySelectorAll('.like-btn');
    const shareButtons = document.querySelectorAll('.share-btn');
    const filterToggle = document.getElementById('filter-toggle');
    const mediaFilters = document.getElementById('media-filters');
    
    // Input validation for filters
    function validateFilterInput(value, type) {
        if (type === 'category') {
            const numValue = parseInt(value);
            return isNaN(numValue) || numValue >= 0;
        }
        if (type === 'type') {
            return ['', 'image', 'video'].includes(value);
        }
        return true;
    }
    
    // Safe URL parameter construction
    function buildSafeURL(params) {
        const url = new URL(window.location.href);
        url.search = '';
        
        Object.keys(params).forEach(key => {
            if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                url.searchParams.set(key, params[key]);
            }
        });
        
        return url.toString();
    }
    
    // Auto-apply filters when selection changes (for desktop)
    function autoApplyFilters() {
        if (window.innerWidth > 768) {
            applyFiltersHandler();
        }
    }
    
    // Apply filters handler with validation
    function applyFiltersHandler() {
        const category = categoryFilter ? categoryFilter.value : '';
        const type = typeFilter ? typeFilter.value : '';
        
        // Validate inputs
        if (!validateFilterInput(category, 'category') || !validateFilterInput(type, 'type')) {
            console.error('Invalid filter values');
            return;
        }
        
        const params = {};
        if (category) params.category = category;
        if (type) params.type = type;
        
        window.location.href = buildSafeURL(params);
    }
    
    // Toggle filter visibility on mobile
    if (filterToggle && mediaFilters) {
        filterToggle.addEventListener('click', function() {
            const isVisible = mediaFilters.classList.toggle('filters-visible');
            this.setAttribute('aria-expanded', isVisible);
            
            if (isVisible) {
                this.innerHTML = '<i class="fas fa-times"></i> <?= addslashes(htmlspecialchars(LanguageHelper::t('hide_filters', 'Hide Filters'), ENT_QUOTES, 'UTF-8')) ?>';
            } else {
                this.innerHTML = '<i class="fas fa-filter"></i> <?= addslashes(htmlspecialchars(LanguageHelper::t('filters', 'Filters'), ENT_QUOTES, 'UTF-8')) ?>';
            }
        });
        
        // Hide filters when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && mediaFilters.classList.contains('filters-visible')) {
                if (!mediaFilters.contains(event.target) && !filterToggle.contains(event.target)) {
                    mediaFilters.classList.remove('filters-visible');
                    filterToggle.innerHTML = '<i class="fas fa-filter"></i> <?= addslashes(htmlspecialchars(LanguageHelper::t('filters', 'Filters'), ENT_QUOTES, 'UTF-8')) ?>';
                    filterToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }
    
    // Event listeners for filters
    if (categoryFilter) {
        categoryFilter.addEventListener('change', autoApplyFilters);
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', autoApplyFilters);
    }
    
    if (applyFilters) {
        applyFilters.addEventListener('click', applyFiltersHandler);
    }
    
    if (resetFilters) {
        resetFilters.addEventListener('click', function() { 
            window.location.href = window.location.pathname; 
        });
    }
    
    // Like functionality
    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const mediaId = this.getAttribute('data-media-id');
            const likeCount = this.querySelector('.like-count');
            
            // Validate media ID
            if (!mediaId || !/^\d+$/.test(mediaId)) {
                console.error('Invalid media ID');
                return;
            }
            
            if (this.classList.contains('btn-like')) {
                this.classList.remove('btn-like');
                this.classList.add('btn-liked');
                likeCount.textContent = parseInt(likeCount.textContent) + 1;
                console.log('Liked media ID: ' + mediaId);
                
                // In real app, send AJAX request to server
                // this.likeMedia(mediaId);
            } else {
                this.classList.remove('btn-liked');
                this.classList.add('btn-like');
                likeCount.textContent = parseInt(likeCount.textContent) - 1;
                console.log('Unliked media ID: ' + mediaId);
                
                // In real app, send AJAX request to server
                // this.unlikeMedia(mediaId);
            }
        });
    });
    
    // Share functionality
    shareButtons.forEach(button => {
        button.addEventListener('click', function() {
            const mediaUrl = this.getAttribute('data-media-url');
            
            // Validate URL
            try {
                new URL(mediaUrl);
            } catch (e) {
                console.error('Invalid media URL');
                return;
            }
            
            if (navigator.share) {
                navigator.share({ 
                    title: 'Phool Delivery Media', 
                    url: mediaUrl 
                })
                .then(() => console.log('Shared successfully'))
                .catch(error => {
                    if (error.name !== 'AbortError') {
                        console.log('Error sharing:', error);
                    }
                });
            } else {
                navigator.clipboard.writeText(mediaUrl)
                    .then(() => alert('<?= addslashes(htmlspecialchars(LanguageHelper::t('link_copied', 'Link copied to clipboard!'), ENT_QUOTES, 'UTF-8')) ?>'))
                    .catch(err => { 
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = mediaUrl;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        alert('<?= addslashes(htmlspecialchars(LanguageHelper::t('link_copied', 'Link copied to clipboard!'), ENT_QUOTES, 'UTF-8')) ?>');
                    });
            }
        });
    });
    
    // Handle window resize to adjust filter behavior
    function handleResize() {
        if (window.innerWidth > 768) {
            // On desktop, ensure filters are visible
            if (mediaFilters) {
                mediaFilters.classList.add('filters-visible');
                mediaFilters.style.display = 'block';
            }
            if (filterToggle) {
                filterToggle.style.display = 'none';
            }
        } else {
            // On mobile, hide filters by default
            if (mediaFilters) {
                mediaFilters.classList.remove('filters-visible');
                mediaFilters.style.display = 'none';
            }
            if (filterToggle) {
                filterToggle.style.display = 'flex';
                filterToggle.innerHTML = '<i class="fas fa-filter"></i> <?= addslashes(htmlspecialchars(LanguageHelper::t('filters', 'Filters'), ENT_QUOTES, 'UTF-8')) ?>';
                filterToggle.setAttribute('aria-expanded', 'false');
            }
        }
    }
    
    window.addEventListener('resize', handleResize);
    
    // Initialize filter visibility based on screen size
    handleResize();
    
    // Add loading states for better UX
    const mediaImages = document.querySelectorAll('img[loading="lazy"]');
    mediaImages.forEach(img => {
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
        img.style.transition = 'opacity 0.3s ease';
        img.style.opacity = '0';
    });
});
</script>

<style>
.media-meta {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.8rem;
    color: #666;
}

.media-views {
    display: flex;
    align-items: center;
    gap: 4px;
}

.popular-media-info {
    padding: 8px;
}

.popular-media-info h4 {
    margin: 0 0 4px 0;
    font-size: 0.9rem;
}

.views-count {
    font-size: 0.8rem;
    color: #666;
}

.video-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
}

/* Filter toggle styles for mobile */
.filter-toggle-btn {
    display: none;
    background: #4CAF50;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 15px;
    width: 100%;
    font-size: 16px;
}

.filter-toggle-btn i {
    margin-right: 8px;
}

/* Desktop filter styles */
.media-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid #e9ecef;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

.form-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Mobile responsive styles */
@media (max-width: 768px) {
    .filter-toggle-btn {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .media-filters {
        display: none;
        margin-bottom: 20px;
    }
    
    .media-filters.filters-visible {
        display: block;
    }
    
    .filter-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .filter-buttons {
        width: 100%;
    }
    
    .filter-buttons .btn {
        flex: 1;
    }
}

/* Media grid styles */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.media-item {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.media-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.media-thumbnail {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.video-thumbnail {
    position: relative;
}

.play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 60px;
    background: rgba(0,0,0,0.7);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.play-icon svg {
    width: 24px;
    height: 24px;
    margin-left: 4px;
}

.media-info {
    padding: 15px;
}

.media-title {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    color: #333;
}

.media-description {
    color: #666;
    margin: 0 0 10px 0;
    line-height: 1.4;
}

/* Blinking animation */
@keyframes blinkPulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(76, 175, 80, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
}
 
.media-page { padding: 20px 0; font-family: 'Arial', sans-serif; }
.container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
h1 { color: #2d5016; text-align: center; margin-bottom: 10px; font-size: 1.5rem; }
.page-description { text-align: center; color: #666; margin-bottom: 20px; font-size: 0.9rem; }

/* Filter Toggle Button (Mobile Only) */
.filter-toggle-btn { 
    display: none; 
    width: 100%; 
    padding: 10px; 
    margin-bottom: 15px;
    background: #2d5016; 
    color: white; 
    border: none; 
    border-radius: 5px; 
    cursor: pointer;
    font-size: 0.9rem;
}

.media-filters { 
    background: #f9f9f9; 
    padding: 15px; 
    border-radius: 10px; 
    margin-bottom: 20px;
}
.filter-row { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 10px; 
    align-items: end; 
}
.filter-group { 
    flex: 1; 
    min-width: 150px; 
}
.filter-group label { 
    margin-bottom: 5px; 
    font-weight: 500; 
    font-size: 0.85rem;
}
.form-input { 
    padding: 8px; 
    border: 1px solid #ddd; 
    border-radius: 5px; 
    font-size: 0.9rem; 
    width: 100%;
}
.filter-buttons { 
    display: flex; 
    gap: 10px; 
}
.btn { 
    padding: 8px 15px; 
    border: none; 
    border-radius: 5px; 
    cursor: pointer; 
    font-size: 0.9rem; 
    transition: background-color 0.3s; 
}
.btn-primary { background: #2d5016; color: white; }
.btn-primary:hover { background: #3a6720; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }

/* Media Grid */
.media-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
    gap: 15px; 
    margin-bottom: 30px; 
}
.media-item { 
    background: #fff; 
    border-radius: 8px; 
    overflow: hidden; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    transition: transform 0.3s ease; 
}
.media-item:hover { transform: translateY(-5px); }
.media-thumbnail, .popular-media-thumbnail { 
    width: 100%; 
    height: 180px; 
    object-fit: cover; 
}
.popular-media-thumbnail { height: 120px; }
.video-thumbnail { position: relative; cursor: pointer; }
.play-icon { 
    position: absolute; 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%); 
    opacity: 0.8; 
    transition: opacity 0.3s ease; 
    background: rgba(0,0,0,0.7); 
    border-radius: 50%; 
    width: 50px; 
    height: 50px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
}
.play-icon svg { width: 25px; height: 25px; }
.video-thumbnail:hover .play-icon { opacity: 1; }
.media-info { padding: 12px; }
.media-title { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; color: #333; }
.media-description { color: #666; font-size: 0.85rem; margin-bottom: 8px; }
.media-meta { display: flex; gap: 8px; font-size: 0.75rem; color: #888; flex-wrap: wrap; }
.media-category { background: #f0f0f0; padding: 2px 6px; border-radius: 12px; }

/* Popular Media Section */
.popular-media-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
.popular-media-section h2 { font-size: 1.2rem; margin-bottom: 15px; }
.popular-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }

/* Single Media View */
.single-media-view { max-width: 1000px; margin: 0 auto; padding: 15px; }
.media-player { margin-bottom: 20px; }
.media-player img, .media-player video { width: 100%; max-height: 500px; object-fit: contain; border-radius: 10px; }
.media-actions { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
.action-btn { 
    display: flex; 
    align-items: center; 
    gap: 5px; 
    padding: 6px 12px; 
    background: #f0f0f0; 
    border: none; 
    border-radius: 5px; 
    cursor: pointer; 
    transition: background-color 0.3s; 
    font-size: 0.85rem;
}
.action-btn:hover { background: #e0e0e0; }
.media-details { margin-bottom: 20px; }
.media-details h2 { color: #2d5016; margin-bottom: 8px; font-size: 1.2rem; }
.media-details p { color: #666; line-height: 1.5; font-size: 0.9rem; }
.media-stats { display: flex; gap: 15px; margin: 12px 0; color: #888; font-size: 0.85rem; flex-wrap: wrap; }
.comments-section { margin-top: 20px; }
.comments-section h3 { margin-bottom: 12px; color: #2d5016; font-size: 1.1rem; }
.comment-form { margin-bottom: 20px; }
.comment-form textarea { 
    width: 100%; 
    padding: 8px; 
    border: 1px solid #ddd; 
    border-radius: 5px; 
    resize: vertical; 
    min-height: 80px; 
    margin-bottom: 8px; 
    font-size: 0.9rem;
}
.comment-list { list-style: none; padding: 0; }
.comment-item { padding: 12px; border-bottom: 1px solid #eee; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; }
.comment-author { font-weight: 600; color: #2d5016; font-size: 0.9rem; }
.comment-date { color: #888; font-size: 0.8rem; }
.comment-text { color: #333; line-height: 1.4; font-size: 0.9rem; }
.back-button { 
    display: inline-block; 
    margin-bottom: 15px; 
    padding: 6px 12px; 
    background: #6c757d; 
    color: white; 
    text-decoration: none; 
    border-radius: 5px; 
    transition: background-color 0.3s; 
    font-size: 0.9rem;
}
.back-button:hover { background: #5a6268; }

/* Like Button States */
.btn-like { background: #e74c3c; color: white; }
.btn-like:hover { background: #c0392b; }
.btn-liked { background: #27ae60; color: white; }
.btn-liked:hover { background: #219653; }

.no-media { text-align: center; padding: 30px; color: #666; grid-column: 1 / -1; }

/* Responsive Styles */
@media (max-width: 768px) {
    h1 { font-size: 1.3rem; }
    .page-description { font-size: 0.85rem; }
    
    /* Filter Toggle for Mobile */
    .filter-toggle-btn { display: block; }
    .media-filters { 
        display: none; 
        padding: 12px;
        margin-bottom: 15px;
    }
    .media-filters.filters-visible { 
        display: block; 
    }
    .filter-row { 
        flex-direction: column; 
        gap: 8px;
    }
    .filter-group { 
        width: 100%; 
        min-width: auto; 
    }
    .filter-buttons { 
        width: 100%; 
        justify-content: space-between;
    }
    .btn { 
        flex: 1; 
        margin: 0 5px;
        padding: 8px 10px;
    }
    
    /* Media Grid for Mobile - 2 columns */
    .media-grid { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 12px; 
    }
    .media-thumbnail, .popular-media-thumbnail { 
        height: 150px; 
    }
    .media-info { padding: 10px; }
    .media-title { font-size: 0.9rem; }
    .media-description { font-size: 0.8rem; }
    .media-meta { font-size: 0.7rem; gap: 6px; }
    
    /* Popular Media for Mobile */
    .popular-media-grid { 
        grid-template-columns: repeat(3, 1fr); 
        gap: 10px; 
    }
    .popular-media-thumbnail { height: 100px; }
    
    /* Single Media View Adjustments */
    .single-media-view { padding: 10px; }
    .media-player img, .media-player video { max-height: 300px; }
    .media-actions { gap: 8px; }
    .action-btn { font-size: 0.8rem; padding: 5px 10px; }
    .media-details h2 { font-size: 1.1rem; }
    .media-details p { font-size: 0.85rem; }
    .media-stats { font-size: 0.8rem; gap: 10px; }
    .comments-section h3 { font-size: 1rem; }
    .comment-form textarea { font-size: 0.85rem; }
    .comment-author { font-size: 0.85rem; }
    .comment-date { font-size: 0.75rem; }
    .comment-text { font-size: 0.85rem; }
}

@media (max-width: 480px) {
    .media-grid { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 10px; 
    }
    .media-thumbnail { 
        height: 120px; 
    }
    .media-info { padding: 8px; }
    .media-title { font-size: 0.85rem; }
    .media-description { display: none; } /* Hide description on very small screens */
    .popular-media-grid { 
        grid-template-columns: repeat(2, 1fr); 
    }
}
</style>