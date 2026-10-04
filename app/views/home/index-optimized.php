<?php
// app/views/home/index-optimized.php
// PERFORMANCE OPTIMIZED: All database logic moved to controller
// This file ONLY renders data - no database queries

// All data is passed from controller
// $pathConfig, $base_url, $assets_url already set by controller
// $ads, $notices, $videos, $products, $cities, $current_city available

$page_title = $page_title ?? 'Phool Delivery - Fresh Flowers in Banepa';
$current_city_id = $current_city['id'] ?? 1;
$current_city_name = $current_city['name'] ?? 'Banepa';
?>

<?php if (!empty($notices)): ?>
    <?php foreach ($notices as $index => $notice): ?>
    <div id="noticeModal<?= $index ?>" class="modal notice-modal" style="display: none;">
        <div class="modal-content notice-content">
            <div class="notice-progress">
                <div class="notice-progress-bar" id="progressBar<?= $index ?>"></div>
            </div>
            <span class="close notice-close" data-modal-index="<?= $index ?>">&times;</span>
            <div class="notice-body">
                <?php if ($notice['media_type'] === 'photo'): ?>
                    <div class="notice-media">
                        <img src="<?= $pathConfig->getFilePath(basename($notice['file_path']), 'notice') ?>" alt="<?= htmlspecialchars($notice['title'] ?? '') ?>" loading="lazy">
                    </div>
                <?php elseif ($notice['media_type'] === 'video'): ?>
                    <div class="notice-media">
                        <video controls autoplay muted playsinline>
                            <source src="<?= $pathConfig->getFilePath(basename($notice['file_path']), 'notice') ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                <?php endif; ?>
                
                <div class="notice-actions">
                    <?php if ($notice['button_type'] === 'order_now'): ?>
                        <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
                            <input type="hidden" name="product_slug" value="marigold">
                            <input type="hidden" name="quantity" value="2">
                            <button type="submit" class="btn btn-primary notice-btn">🚀 <?= LanguageHelper::t('order_now', 'Order Now 2 kg Marigold') ?></button>
                        </form>
                    <?php elseif ($notice['button_type'] === 'book_now'): ?>
                        <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
                            <input type="hidden" name="product_slug" value="marigold">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-primary notice-btn">📅 <?= LanguageHelper::t('book_now', 'Book Now') ?></button>
                        </form>
                    <?php elseif ($notice['button_type'] === 'write'): ?>
                        <a href="<?= $pathConfig->url('reviews') ?>" class="btn btn-primary notice-btn">⭐ <?= LanguageHelper::t('write_review', 'Write Review') ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div id="installGuideModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close">&times;</span>
        <h3>Install Phool Delivery App</h3>
        <div class="install-steps">
            <div class="install-step">
                <h4>📱 For Mobile Devices:</h4>
                <p><strong>Android/Chrome:</strong> Tap menu (⋮) → "Install App" or "Add to Home Screen"</p>
                <p><strong>iPhone/Safari:</strong> Tap share icon (📤) → "Add to Home Screen"</p>
            </div>
            <div class="install-step">
                <h4>💻 For Desktop:</h4>
                <p>Look for the install icon in your browser's address bar</p>
                <p>Or check browser menu for "Install" option</p>
            </div>
        </div>
        <button class="btn btn-primary" onclick="checkInstallability()">Check Installation Status</button>
    </div>
</div>

<div id="contactModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3><?= LanguageHelper::t('contact_orders_inquiries', 'Contact for Orders & Inquiries') ?></h3>
        <div class="contact-info">
            <div class="contact-person">
                <h4>Yuvi Syangtan (<?= LanguageHelper::t('contact_us', 'Contact Us') ?>)</h4>
                <p><?= LanguageHelper::t('contact_orders_inquiries', 'Available for direct orders and inquiries') ?></p>
            </div>
            <div class="contact-numbers">
                <div class="phone-number"><span>📞 9800000000</span><a href="tel:9800000000" class="call-btn"><?= LanguageHelper::t('call_now', 'Call Now') ?></a></div>
                <div class="phone-number"><span>📞 9800000001</span><a href="tel:9800000001" class="call-btn"><?= LanguageHelper::t('call_now', 'Call Now') ?></a></div>
            </div>
            <div class="contact-hours"><p><strong><?= LanguageHelper::t('available_hours', 'Available Hours') ?>:</strong> 8:00 AM - 8:00 PM</p></div>
        </div>
    </div>
</div>

<div id="cartNotification" class="cart-notification"><div class="notification-content"><span class="notification-icon">✓</span><span class="notification-text"><?= LanguageHelper::t('item_added_to_cart', 'Item added to cart successfully!') ?></span></div></div>

<section class="hero-section">
    <div class="hero-container">
        <div class="carousel-column-full">
            <div class="hero-carousel-v2" data-aos="zoom-in" data-aos-delay="100">
                <button class="carousel-arrow carousel-arrow-left" aria-label="Previous slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/>
                    </svg>
                </button>
                
                <div class="carousel-track-v2">
                    <?php
                    // Slide data - no database query needed, static slides
                    $slides = [
                        [
                            'image' => $assets_url . '/img/products/wed.jpg', 
                            'slug' => 'marigold', 
                            'qty' => 2, 
                            'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                        ],   
                        [
                            'image' => $assets_url . '/img/products/santa.jpeg', 
                            'slug' => 'marigold', 
                            'qty' => 2, 
                            'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                        ],
                        [
                            'image' => $assets_url . '/img/products/hatke.jpeg', 
                            'slug' => 'marigold', 
                            'qty' => 2, 
                            'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                        ],   
                        [
                            'image' => $assets_url . '/img/products/marriage.jpg', 
                            'slug' => 'marigold', 
                            'qty' => 2, 
                            'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                        ],   
                        [
                            'image' => $assets_url . '/img/products/secret.jpeg', 
                            'slug' => 'marigold', 
                            'qty' => 2, 
                            'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                        ],
                    ];
                    
                    foreach ($slides as $index => $slide):
                        $image_path = $slide['image'] ?? '';
                    ?>
                    <div class="carousel-slide-v2" data-slide-index="<?= $index ?>" data-bg-image="<?= htmlspecialchars($image_path, ENT_QUOTES) ?>">
                        <div class="slide-button-v2">
                            <form class="direct-checkout-form" method="POST" action="<?= htmlspecialchars($pathConfig->url('direct-checkout'), ENT_QUOTES) ?>">
                                <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slide['slug']) ?>">
                                <input type="hidden" name="quantity" value="<?= (int)$slide['qty'] ?>">
                                <button type="submit" class="btn carousel-order-v2"><?= htmlspecialchars($slide['btn_text']) ?></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="carousel-arrow carousel-arrow-right" aria-label="Next slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                    </svg>
                </button>
                
                <div class="carousel-indicators-v2">
                    <div class="dot-indicators">
                        <?php foreach ($slides as $index => $slide): ?>
                            <span class="dot" data-slide-index="<?= $index ?>">●</span>
                        <?php endforeach; ?>
                    </div>
                    <div class="slide-number">
                        <span class="current-slide">1</span>/<span class="total-slides"><?= count($slides) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="featured-products">
    <div class="container">
        <h2 data-aos="fade-up" data-aos-delay="100"><?= LanguageHelper::t('our_popular_flowers', 'Our Popular Flowers') ?></h2>
        
        <div class="city-info-badge" data-aos="fade-up" data-aos-delay="150">
            <span class="badge-icon">📍</span>
            <span class="badge-text">
                <?= LanguageHelper::t('delivering_to', 'Delivering to') ?>: 
                <strong><?= htmlspecialchars($current_city_name) ?></strong>
            </span>
        </div>
        
        <div class="products-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $index => $product): 
                    $product_name = LanguageHelper::getLocalizedText($product, 'name');
                    $product_id = $product['id'] ?? 0;
                    $price = $product['price'] ?? 0;
                    $unit = $product['unit'] ?? 'piece';
                    $stock_qty = $product['stock_quantity'] ?? 0;
                    $image_url = $product['image_url'] ?? '';
                ?>
                <div class="product-card" data-category="<?= $product['category_id'] ?? '' ?>" data-price="<?= $price ?>" data-aos="zoom-in" data-aos-delay="<?= ($index * 100) + 200 ?>">
                    <a href="<?= $pathConfig->url('product/' . $product_id) ?>" class="product-image-link">
                        <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($product_name) ?>" class="product-image" loading="<?= ($index < 4) ? 'eager' : 'lazy' ?>" decoding="async">
                    </a>
                    <div class="product-info">
                        <h3 class="product-title"><?= htmlspecialchars($product_name) ?></h3>
                        <p class="product-price"><?= number_format($price, 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $unit ?></p>
                        <?php if (isset($product['bulk_price']) && $product['bulk_price'] && $product['bulk_price'] < $price): ?>
                            <p class="product-bulk-price"><?= LanguageHelper::t('bulk_price', 'Bulk price') ?>: <?= number_format($product['bulk_price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $unit ?></p>
                        <?php endif; ?>
                        <div class="product-actions">
                            <button class="btn add-to-cart" data-id="<?= $product_id ?>" data-name="<?= htmlspecialchars($product_name) ?>" data-price="<?= $price ?>" data-unit="<?= $unit ?>" data-image="<?= $image_url ?>" <?= ($stock_qty <= 0) ? 'disabled' : '' ?>><?= ($stock_qty <= 0) ? LanguageHelper::t('out_of_stock', 'Out of Stock') : LanguageHelper::t('add_to_cart', 'Add to Cart') ?></button>
                            <?php if ($stock_qty > 0): ?>
                            <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>"><input type="hidden" name="product_id" value="<?= $product_id ?>"><input type="hidden" name="quantity" value="1"><button type="submit" class="btn btn-primary"><?= LanguageHelper::t('buy_now', 'Buy Now') ?></button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products" data-aos="fade-up">
                    <p>
                        <?= LanguageHelper::t('no_products_city', 'No products available for delivery in this city at the moment.') ?>
                        <br>
                        <a href="<?= $pathConfig->url('products') ?>" style="color: #007bff; text-decoration: underline; margin-top: 10px; display: inline-block;">
                            <?= LanguageHelper::t('browse_all_products', 'Browse all available products') ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="view-more-container" data-aos="fade-up" data-aos-delay="500" style="text-align: center; margin-top: 20px;">
            <a href="<?= $pathConfig->url('products') ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #666; color: #fff; padding: 10px 20px; border-radius: 6px; font-size: 14px; text-decoration: none; font-weight: 600; transition: background 0.3s ease;" onmouseover="this.style.background='#555'" onmouseout="this.style.background='#666'">
                <?= LanguageHelper::t('view_more', 'View More Products') ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12h14m-7-7l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2><?= LanguageHelper::t('why_choose_us', 'Why Choose Phool Delivery?') ?></h2>
        <div class="features-grid">
            <div class="feature">
                <div class="feature-icon">🌱</div>
                <h3><?= LanguageHelper::t('fresh_from_farms_title', 'Fresh From Farms') ?></h3>
                <p><?= LanguageHelper::t('fresh_from_farms_desc', 'Our flowers come directly from local farmers, ensuring freshness and quality.') ?></p>
            </div>
            <div class="feature">
                <div class="feature-icon">🚚</div>
                <h3><?= LanguageHelper::t('fast_delivery_title', 'Fast Delivery') ?></h3>
                <p><?= LanguageHelper::t('fast_delivery_desc', 'We deliver to your doorstep in Banepa and surrounding areas within hours.') ?></p>
            </div>
            <div class="feature">
                <div class="feature-icon">💰</div>
                <h3><?= LanguageHelper::t('easy_ordering_title', 'Easy Ordering') ?></h3>
                <p><?= LanguageHelper::t('easy_ordering_desc', 'Simple ordering process with multiple payment options including COD.') ?></p>
            </div>
        </div>
    </div>
</section>

<section class="media-section">
    <div class="container">
        <div class="section-header">
            <h2><?= LanguageHelper::t('latest_videos_from_farm', 'Latest Videos from Our Farm') ?></h2>
            <p><?= LanguageHelper::t('see_how_we_grow', 'See how we grow and deliver fresh flowers directly from our farms') ?></p>
        </div>
        <?php if (!empty($videos)): ?>
            <div class="media-grid">
                <?php foreach ($videos as $index => $video): 
                    $video_title = LanguageHelper::getLocalizedText($video, 'title');
                    $video_description = LanguageHelper::getLocalizedText($video, 'description');
                    $thumbnail_path = $video['thumbnail_path'] ?? $video['file_path'] ?? '';
                    $full_thumbnail_path = $pathConfig->getImagePath($thumbnail_path, 'media_thumb');
                ?>
                    <div class="media-item">
                        <div class="media-thumbnail">
                            <a href="<?= $pathConfig->url('media?id=' . $video['id']) ?>">
                                <img src="<?= $full_thumbnail_path ?>" alt="<?= htmlspecialchars($video_title) ?>" loading="lazy">
                                <div class="play-overlay">
                                    <svg viewBox="0 0 24 24" fill="#ffffff"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </a>
                        </div>
                        <div class="media-info">
                            <h3 class="media-title"><?= htmlspecialchars($video_title) ?></h3>
                            <p class="media-desc"><?= htmlspecialchars(substr($video_description, 0, 80) . (strlen($video_description) > 80 ? '...' : '')) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="media-actions">
                <a href="<?= $pathConfig->url('media') ?>" class="btn watch-more-btn">
                    <?= LanguageHelper::t('watch_more_videos', 'Watch More Videos') ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M5 12h14m-7-7l7 7-7 7"/></svg>
                </a>
            </div>
        <?php else: ?>
            <div class="no-media">
                <p><?= LanguageHelper::t('no_videos_available', 'No videos available at the moment. Check back later for updates!') ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="fixed-buttons-container">
    <div class="fixed-order-btn">
        <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
            <input type="hidden" name="product_slug" value="marigold">
            <input type="hidden" name="quantity" value="2">
            <button type="submit" class="btn order-now-btn">
                <?= LanguageHelper::t('order_now', 'Order Now 2 kg Marigold 🌸') ?>
            </button>
        </form>
    </div>

    <div class="fixed-whatsapp-btn">
        <a href="https://wa.me/9779800000000" target="_blank">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    
    <div class="fixed-contact-btn">
        <button class="btn contact-now-btn" id="contactBtn">
            <?= LanguageHelper::t('contact_us', 'Contact Us 📞') ?>
        </button>
    </div>
</div>

<script>
// PERFORMANCE: Lazy load hero carousel images when visible
function initLazyHeroImages() {
    const slides = document.querySelectorAll('.carousel-slide-v2');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bgImage = entry.target.dataset.bgImage;
                    if (bgImage) {
                        entry.target.style.backgroundImage = `url('${bgImage}')`;
                        entry.target.style.backgroundSize = 'cover';
                        entry.target.style.backgroundPosition = 'center';
                        observer.unobserve(entry.target);
                    }
                }
            });
        });
        
        slides.forEach(slide => observer.observe(slide));
    } else {
        // Fallback for older browsers
        slides.forEach(slide => {
            const bgImage = slide.dataset.bgImage;
            if (bgImage) {
                slide.style.backgroundImage = `url('${bgImage}')`;
                slide.style.backgroundSize = 'cover';
                slide.style.backgroundPosition = 'center';
            }
        });
    }
}

// =========================================================================
// CAROUSEL FUNCTIONALITY
// =========================================================================
function initializeCarousel() {
    const carousel = document.querySelector('.hero-carousel-v2');
    const track = document.querySelector('.carousel-track-v2');
    const slides = document.querySelectorAll('.carousel-slide-v2');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.carousel-arrow-left');
    const nextBtn = document.querySelector('.carousel-arrow-right');
    const currentSlideSpan = document.querySelector('.current-slide');
    const totalSlidesSpan = document.querySelector('.total-slides');
    
    if (!track || !slides.length) return;
    
    let currentIndex = 0;
    const totalSlides = slides.length;
    let autoScrollInterval;
    
    totalSlidesSpan.textContent = totalSlides;
    
    const debounce = (func, delay) => {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    };

    function updateArrowVisibility() {
        if (window.innerWidth >= 1025) {
            const scrollLeft = track.scrollLeft;
            const slideWidth = slides[0].offsetWidth;
            const margin = 25;
            const contentWidth = (slideWidth * totalSlides) + (margin * (totalSlides - 1));
            const trackWidth = track.offsetWidth;
            const maxScroll = contentWidth - trackWidth;

            if (prevBtn) {
                prevBtn.disabled = scrollLeft <= 5;
                prevBtn.style.opacity = prevBtn.disabled ? '0.3' : '1';
            }
            
            if (nextBtn) {
                nextBtn.disabled = scrollLeft >= (maxScroll - 5);
                nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
            }
        }
    }
    
    function adjustForMobile() {
        updateArrowVisibility();
    }
    
    function updateIndicators() {
        dots.forEach((dot, index) => {
            if (index === currentIndex) {
                dot.style.opacity = '1';
                dot.classList.add('active');
            } else {
                dot.style.opacity = '0.3';
                dot.classList.remove('active');
            }
        });
        currentSlideSpan.textContent = currentIndex + 1;
    }
    
    function scrollToSlide(index, behavior = 'smooth') {
        if (index < 0 || index >= totalSlides) {
            if (window.innerWidth <= 1024) {
                index = (index + totalSlides) % totalSlides;
                behavior = 'auto'; 
            } else {
                return;
            }
        }
        
        currentIndex = index;
        
        let scrollPosition;
        const slideWidth = slides[0].offsetWidth;
        
        if (window.innerWidth <= 1024) {
            scrollPosition = slideWidth * index;
        } else {
            const margin = 25;
            scrollPosition = (slideWidth + margin) * index;
        }
        
        track.scrollTo({
            left: scrollPosition,
            behavior: behavior
        });
        
        updateIndicators();
        updateArrowVisibility();
    }
    
    function nextSlide() {
        scrollToSlide(currentIndex + 1);
    }
    
    function prevSlide() {
        scrollToSlide(currentIndex - 1);
    }
    
    const handleScroll = debounce(() => {
        const scrollLeft = track.scrollLeft;
        let newIndex;
        
        if (window.innerWidth <= 1024) {
            const slideWidth = track.offsetWidth; 
            newIndex = Math.round(scrollLeft / slideWidth);
        } else {
            const slideWidth = slides[0].offsetWidth;
            const margin = 25;
            newIndex = Math.round(scrollLeft / (slideWidth + margin));
        }
        
        if (newIndex !== currentIndex && newIndex >= 0 && newIndex < totalSlides) {
            currentIndex = newIndex;
            updateIndicators();
        }
        updateArrowVisibility();
    }, 100);
    
    track.addEventListener('scroll', handleScroll);
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => scrollToSlide(index));
    });
    
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    
    function startAutoScroll() {
        stopAutoScroll(); 
        autoScrollInterval = setInterval(() => {
            nextSlide();
        }, 5000); 
    }
    
    function stopAutoScroll() {
        clearInterval(autoScrollInterval);
    }
    
    if (carousel) {
        carousel.addEventListener('mouseenter', stopAutoScroll);
        carousel.addEventListener('mouseleave', startAutoScroll);
    }
    
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            adjustForMobile();
            scrollToSlide(currentIndex, 'auto');
        }, 250);
    });
    
    adjustForMobile();
    updateIndicators();
    updateArrowVisibility();
    startAutoScroll();
}

document.addEventListener('DOMContentLoaded', function() {
    // Load hero images when carousel is visible
    initLazyHeroImages();
    
    // Initialize carousel
    initializeCarousel();
    
    // Notice Modal Management
    const notices = <?= json_encode($notices ?? []) ?>;
    let currentNoticeIndex = 0;
    let shownNoticesToday = JSON.parse(localStorage.getItem('shownNoticesToday') || '[]');
    let progressInterval;
    
    function initializeNotices() {
        if (notices.length === 0) return;
        const noticesToShow = notices.filter((notice, index) => {
            return !shownNoticesToday.includes(parseInt(notice.id));
        });
        if (noticesToShow.length > 0) {
            setTimeout(() => {
                showNoticeModal(noticesToShow[0].id);
            }, 1500);
        }
    }
    
    function showNoticeModal(noticeId) {
        const noticeIndex = notices.findIndex(notice => parseInt(notice.id) === parseInt(noticeId));
        if (noticeIndex === -1) return;
        const modal = document.getElementById(`noticeModal${noticeIndex}`);
        if (modal) {
            modal.style.display = 'block';
            currentNoticeIndex = noticeIndex;
            startProgressBar(noticeIndex);
            if (!shownNoticesToday.includes(parseInt(noticeId))) {
                shownNoticesToday.push(parseInt(noticeId));
                localStorage.setItem('shownNoticesToday', JSON.stringify(shownNoticesToday));
            }
        }
    }
    
    function startProgressBar(noticeIndex) {
        const progressBar = document.getElementById(`progressBar${noticeIndex}`);
        if (!progressBar) return;
        let width = 0;
        clearInterval(progressInterval);
        progressInterval = setInterval(() => {
            if (width >= 100) {
                clearInterval(progressInterval);
                closeCurrentNoticeAndShowNext();
            } else {
                width += 0.5;
                progressBar.style.width = width + '%';
            }
        }, 50);
    }
    
    function closeCurrentNoticeAndShowNext() {
        clearInterval(progressInterval);
        const currentModal = document.getElementById(`noticeModal${currentNoticeIndex}`);
        if (currentModal) currentModal.style.display = 'none';
        const nextNotice = notices.find((notice, index) => 
            index > currentNoticeIndex && !shownNoticesToday.includes(parseInt(notice.id))
        );
        if (nextNotice) {
            setTimeout(() => {
                showNoticeModal(nextNotice.id);
            }, 300);
        }
    }
    
    notices.forEach((notice, index) => {
        const modal = document.getElementById(`noticeModal${index}`);
        if (modal) {
            const closeBtn = modal.querySelector('.notice-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    clearInterval(progressInterval);
                    closeCurrentNoticeAndShowNext();
                });
            }
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    clearInterval(progressInterval);
                    closeCurrentNoticeAndShowNext();
                }
            });
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            modal.addEventListener('mouseenter', function() {
                clearInterval(progressInterval);
            });
            modal.addEventListener('mouseleave', function() {
                if (modal.style.display === 'block') {
                    startProgressBar(index);
                }
            });
        }
    });
    
    function clearExpiredNotices() {
        const today = new Date().toDateString();
        const lastClearDate = localStorage.getItem('lastClearDate');
        if (lastClearDate !== today) {
            localStorage.setItem('shownNoticesToday', '[]');
            localStorage.setItem('lastClearDate', today);
            shownNoticesToday = [];
        }
    }
    
    clearExpiredNotices();
    initializeNotices();

    // Form submission
    const handleFormSubmit = (form, e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('processing', 'Processing...') ?>';
        fetch(form.action, {method: 'POST',body: new FormData(form)})
        .then(response => response.json())
        .then(data => {
            if (data.success) window.location.href = data.redirect;
            else {alert(data.message || '<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>');submitBtn.disabled = false;submitBtn.textContent = originalText;}
        })
        .catch(error => {console.error('Error:', error);alert('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>');submitBtn.disabled = false;submitBtn.textContent = originalText;});
    };

    document.querySelectorAll('.direct-checkout-form').forEach(form => form.addEventListener('submit', (e) => handleFormSubmit(form, e)));
    
    // Add to cart
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            const productData = {product_id: this.getAttribute('data-id'),product_name: this.getAttribute('data-name'),price: this.getAttribute('data-price'),unit: this.getAttribute('data-unit'),image: this.getAttribute('data-image'),quantity: 1};
            const originalButton = this;
            const originalText = this.textContent;
            this.textContent = '<?= LanguageHelper::t('adding', 'Adding...') ?>';
            this.disabled = true;
            
            const formData = new FormData();
            formData.append('product_id', productData.product_id);
            formData.append('product_name', productData.product_name);
            formData.append('price', productData.price);
            formData.append('unit', productData.unit);
            formData.append('image', productData.image);
            formData.append('quantity', productData.quantity);
            
            fetch('<?= $pathConfig->url('cart/add') ?>', {method: 'POST',body: formData})
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    originalButton.textContent = "✓ <?= LanguageHelper::t('added', 'Added') ?>";
                    originalButton.classList.add('added');
                    showCartNotification();
                    updateCartCount();
                    setTimeout(() => {originalButton.textContent = originalText;originalButton.classList.remove('added');originalButton.disabled = false;}, 2000);
                } else {alert('Failed');originalButton.textContent = originalText;originalButton.disabled = false;}
            })
            .catch(error => {console.error('Error:', error);alert('Error');originalButton.textContent = originalText;originalButton.disabled = false;});
        });
    });
    
    function showCartNotification() {
        const notification = document.getElementById('cartNotification');
        if (notification) {notification.classList.add('show');setTimeout(() => notification.classList.remove('show'), 3000);}
    }
    
    function updateCartCount() {
        fetch('<?= $pathConfig->url('cart/count') ?>').then(response => response.json()).then(data => {
            if (data.success) {
                const cartCountElements = document.querySelectorAll('.cart-count, .cart-badge, [data-cart-count]');
                cartCountElements.forEach(element => {element.textContent = data.count;if (data.count > 0) element.style.display = 'inline-block';});
            }
        }).catch(error => console.error('Error:', error));
    }
    
    // Contact modal
    const modal = document.getElementById("contactModal");
    const btn = document.getElementById("contactBtn");
    const span = document.getElementsByClassName("close")[0];
    if (btn && modal) btn.onclick = function() {modal.style.display = "block";}
    if (span) span.onclick = function() {if (modal) modal.style.display = "none";}
    if (modal) window.onclick = function(event) {if (event.target == modal) modal.style.display = "none";}
    
    // Blinking animation
    function startBlinkingAnimation() {
        const orderBtn = document.querySelector('.order-now-btn');
        const contactBtn = document.querySelector('.contact-now-btn');
        if (orderBtn) orderBtn.style.animation = 'blinkPulse 2s infinite';
        if (contactBtn) contactBtn.style.animation = 'blinkPulse 2s infinite 1s';
    }
    setTimeout(startBlinkingAnimation, 1000);
});
</script>
