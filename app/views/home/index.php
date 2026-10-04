<?php
// app/views/home/index.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('welcome', 'Welcome') . " - Phool Delivery";
$assets_path = $pathConfig->get('assets');
$base_url = $pathConfig->getBasePath();

function getImagePath($image_name) {
    global $pathConfig;
    return $pathConfig->getImagePath($image_name, 'product');
}

// Fetch active ads
try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT * FROM ads WHERE status = 'active' ORDER BY created_at DESC LIMIT 2");
    $stmt->execute();
    $active_ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $active_ads = []; }

// Fetch ALL active notices (newer first)
$active_notices = [];
try {
    $notice_stmt = $db->prepare("SELECT * FROM notices WHERE status = 'active' ORDER BY created_at DESC");
    $notice_stmt->execute();
    $active_notices = $notice_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $active_notices = []; }

// Fetch media items
try {
    $mediaController = new MediaController($db);
    $video_items = array_slice(array_filter($mediaController->homeMedia(), function($item) {
        return isset($item['media_type']) && $item['media_type'] === 'video';
    }), 0, 4);
} catch (Exception $e) { $video_items = []; }

// SIMPLIFIED: Get user's city from session or default to Banepa
$current_city_id = $_SESSION['user_city_id'] ?? 1; // Default to Banepa (ID 1)
$current_city_name = $_SESSION['user_city_name'] ?? 'Banepa';

// For logged-in users, check if they have a different city preference
if (isset($_SESSION['customer_id'])) {
    try {
        $customer_id = $_SESSION['customer_id'];
        $query = "SELECT c.city, dc.id as city_id, dc.city_name 
                 FROM customers c 
                 LEFT JOIN delivery_cities dc ON LOWER(TRIM(c.city)) = LOWER(TRIM(dc.city_name))
                 WHERE c.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$customer_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['city_id'])) {
            $current_city_id = $result['city_id'];
            $current_city_name = $result['city_name'] ?? $result['city'];
            $_SESSION['user_city_id'] = $current_city_id;
            $_SESSION['user_city_name'] = $current_city_name;
        }
    } catch (Exception $e) {
        error_log("Error fetching customer city: " . $e->getMessage());
    }
}

// CORRECTED: Fetch up to 20 products for the grid - FIXED QUERY
try {
    // First, get the product IDs that are available in the current city
    $product_ids_query = "
        SELECT DISTINCT pca.product_id 
        FROM product_city_availability pca 
        WHERE pca.city_id = ? AND pca.is_available = 1
        LIMIT 20
    ";
    
    $product_ids_stmt = $db->prepare($product_ids_query);
    $product_ids_stmt->execute([$current_city_id]);
    $available_product_ids = $product_ids_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!empty($available_product_ids)) {
        // Create placeholders for the IN clause
        $placeholders = str_repeat('?,', count($available_product_ids) - 1) . '?';
        
        // Fetch product details with their primary images
        $product_query = "
            SELECT p.*, 
                   (SELECT pi.image_path 
                    FROM product_images pi 
                    WHERE pi.product_id = p.id AND pi.is_primary = 1 
                    LIMIT 1) as primary_image
            FROM products p 
            WHERE p.status = 'active'
            AND p.id IN ($placeholders)
            ORDER BY p.created_at ASC
        ";
        
        $product_stmt = $db->prepare($product_query);
        $product_stmt->execute($available_product_ids);
        $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Now fetch all images for these products
        $product_images_query = "
            SELECT pi.product_id, pi.image_path, pi.is_primary
            FROM product_images pi
            WHERE pi.product_id IN ($placeholders)
            ORDER BY pi.is_primary DESC
        ";
        
        $product_images_stmt = $db->prepare($product_images_query);
        $product_images_stmt->execute($available_product_ids);
        $all_images = $product_images_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize images by product_id
        $product_images = [];
        foreach ($all_images as $image) {
            $product_id = $image['product_id'];
            if (!isset($product_images[$product_id])) {
                $product_images[$product_id] = [];
            }
            $product_images[$product_id][] = [
                'image_path' => $image['image_path'],
                'is_primary' => $image['is_primary']
            ];
        }
        
        // Add images array to each product
        foreach ($products as &$product) {
            $product['images'] = $product_images[$product['id']] ?? [];
        }
        unset($product);
    } else {
        $products = [];
    }
    
} catch (Exception $e) { 
    $products = []; 
    error_log("Error fetching products: " . $e->getMessage()); 
}

// Fetch all available cities for reference
$available_cities = [];
try {
    $city_stmt = $db->prepare("SELECT id, city_name FROM delivery_cities ORDER BY city_name");
    $city_stmt->execute();
    $available_cities = $city_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching cities: " . $e->getMessage());
}

// Fetch carousel images from database
$carousel_slides = [];
try {
    $carousel_query = "
        SELECT ci.*, c.id as category_id, c.name_en, c.name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        WHERE ci.status = 'active'
        ORDER BY ci.sort_order ASC, ci.id DESC
        LIMIT 10
    ";
    $carousel_stmt = $db->prepare($carousel_query);
    $carousel_stmt->execute();
    $carousel_slides = $carousel_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching carousel images: " . $e->getMessage());
    $carousel_slides = [];
}

// Helper function to get carousel image URL
function getCarouselImageUrl($image_path) {
    global $pathConfig;
    if (empty($image_path)) {
        return '';
    }
    // If it's already a full URL, return as is
    if (strpos($image_path, 'http') === 0) {
        return $image_path;
    }
    // Extract just the filename
    $filename = basename($image_path);
    
    // Use the carousel image proxy for all carousel images
    // This works for both localhost and production
    $base_url = $pathConfig->get('base_url');
    return rtrim($base_url, '/') . '/get-carousel-image.php?file=' . urlencode($filename);
}
?>

<?php if (!empty($active_notices)): ?>
    <?php foreach ($active_notices as $index => $notice): ?>
    <div id="noticeModal<?= $index ?>" class="modal notice-modal" style="display: none;">
        <div class="modal-content notice-content">
            <div class="notice-progress">
                <div class="notice-progress-bar" id="progressBar<?= $index ?>"></div>
            </div>
            <span class="close notice-close" data-modal-index="<?= $index ?>">&times;</span>
            <div class="notice-body">
                <?php if ($notice['media_type'] === 'photo'): ?>
                    <div class="notice-media">
                        <img src="<?= $pathConfig->getFilePath(basename($notice['file_path']), 'notice') ?>" alt="<?= htmlspecialchars($notice['title']) ?>" loading="lazy">
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
                    // Dynamic carousel slides from database
                    $default_image = 'https://images.unsplash.com/photo-1560749003-f4b1e17e2dff?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80';
                    
                    if (!empty($carousel_slides)):
                        foreach ($carousel_slides as $index => $slide):
                            $image_path = getCarouselImageUrl($slide['image_path']) ?: $default_image;
                            $has_video = false;
                            $background_style = "background-image: url('" . htmlspecialchars($image_path, ENT_QUOTES) . "'); background-size: cover; background-position: center;";
                            
                            // Get category details
                            $category_id = $slide['category_id'] ?? null;
                            $category_name_en = $slide['name_en'] ?? '';
                            $category_name_ne = $slide['name_ne'] ?? '';
                            
                            // Generate category slug for URL
                            $category_slug = '';
                            if ($category_name_en) {
                                $category_slug = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s]/', '', $category_name_en)));
                            }
                            
                            // Set button text based on category
                            $btn_text = LanguageHelper::t('order_now', 'Order Now');
                            if ($category_name_en) {
                                $localized_category_name = LanguageHelper::getLocalizedText([
                                    'name_en' => $category_name_en,
                                    'name_ne' => $category_name_ne
                                ], 'name');
                                $btn_text = LanguageHelper::t('order_category', 'Order ') . ' ' . $localized_category_name;
                            }
                    ?>
                    <div class="carousel-slide-v2" data-slide-index="<?= $index ?>" style="<?= $background_style ?>">
                        <?php if ($has_video): ?>
                        <video class="slide-video" autoplay muted loop playsinline style="position: absolute; width: 100%; height: 100%; object-fit: cover; z-index: -1;">
                            <source src="<?= htmlspecialchars($slide['video'], ENT_QUOTES) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <?php endif; ?>
                        
                        <div class="slide-button-v2">
                            <?php if ($category_id): ?>
                                <!-- Link to category page instead of direct checkout -->
                                <a href="<?= htmlspecialchars($pathConfig->url('category/' . $category_id . '/' . $category_slug), ENT_QUOTES) ?>" 
                                   class="btn carousel-order-v2">
                                    visit now
                                </a>
                            <?php else: ?>
                                <!-- Fallback to direct checkout if no category -->
                                <form class="direct-checkout-form" method="POST" action="<?= htmlspecialchars($pathConfig->url('direct-checkout'), ENT_QUOTES) ?>">
                                    <input type="hidden" name="product_slug" value="marigold">
                                    <input type="hidden" name="quantity" value="2">
                                    <button type="submit" class="btn carousel-order-v2">visit now</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endforeach; 
                    else: 
                        // Fallback to static slides if no carousel images in database
                        $slides = [
                            [
                                'image' => $assets_path . '/img/products/wed.jpg', 
                                'slug' => 'marigold', 
                                'qty' => 2, 
                                'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                            ],   
                            [
                                'image' => $assets_path . '/img/products/santa.jpeg', 
                                'slug' => 'marigold', 
                                'qty' => 2, 
                                'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                            ],
                            [
                                'image' => $assets_path . '/img/products/hatke.jpeg', 
                                'slug' => 'marigold', 
                                'qty' => 2, 
                                'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                            ],   
                            [
                                'image' => $assets_path . '/img/products/marriage.jpg', 
                                'slug' => 'marigold', 
                                'qty' => 2, 
                                'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                            ],   
                            [
                                'image' => $assets_path . '/img/products/secret.jpeg', 
                                'slug' => 'marigold', 
                                'qty' => 2, 
                                'btn_text' => LanguageHelper::t('order_marigold', 'Order Now ')
                            ],
                        ];
                        
                        foreach ($slides as $index => $slide):
                            $has_video = isset($slide['video']);
                            $image_path = $slide['image'] ?? $default_image;
                            $background_style = '';
                            
                            if ($has_video) {
                                // Handle video slides
                                $background_style = '';
                            } else {
                                // Handle image slides
                                $background_style = "background-image: url('" . htmlspecialchars($image_path, ENT_QUOTES) . "'); background-size: cover; background-position: center;";
                            }
                    ?>
                    <div class="carousel-slide-v2" data-slide-index="<?= $index ?>" style="<?= $background_style ?>">
                        <?php if ($has_video): ?>
                        <video class="slide-video" autoplay muted loop playsinline style="position: absolute; width: 100%; height: 100%; object-fit: cover; z-index: -1;">
                            <source src="<?= htmlspecialchars($slide['video'], ENT_QUOTES) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <?php endif; ?>
                        
                        <div class="slide-button-v2">
                            <form class="direct-checkout-form" method="POST" action="<?= htmlspecialchars($pathConfig->url('direct-checkout'), ENT_QUOTES) ?>">
                                <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slide['slug']) ?>">
                                <input type="hidden" name="quantity" value="<?= (int)$slide['qty'] ?>">
                                <button type="submit" class="btn carousel-order-v2"><?= htmlspecialchars($slide['btn_text']) ?></button>
                            </form>
                        </div>
                    </div>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </div>
                
                <button class="carousel-arrow carousel-arrow-right" aria-label="Next slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                    </svg>
                </button>
                
                <div class="carousel-indicators-v2">
                    <div class="dot-indicators">
                        <?php 
                        $total_slides = !empty($carousel_slides) ? count($carousel_slides) : 5;
                        for ($i = 0; $i < $total_slides; $i++): ?>
                            <span class="dot" data-slide-index="<?= $i ?>">●</span>
                        <?php endfor; ?>
                    </div>
                    <div class="slide-number">
                        <span class="current-slide">1</span>/<span class="total-slides"><?= $total_slides ?></span>
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
        
        <div style="display: none; color: #666; font-size: 12px; margin-bottom: 10px;">
            Products fetched: <?= count($products) ?> (Maximum: 20)
        </div>
        
        <div class="products-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $index => $product): 
                    $product_name = LanguageHelper::getLocalizedText($product, 'name');
                    $product_description = LanguageHelper::getLocalizedText($product, 'description');
                    $product_slug = LanguageHelper::getLocalizedSlug($product);
                    
                    $primary_image = 'default.jpg';
                    if (!empty($product['images'])) {
                        foreach ($product['images'] as $image) {
                            if (isset($image['is_primary']) && $image['is_primary']) { $primary_image = $image['image_path']; break; }
                        }
                        if ($primary_image === 'default.jpg' && isset($product['images'][0]['image_path'])) $primary_image = $product['images'][0]['image_path'];
                    }
                    $image_path = getImagePath($primary_image);
                ?>
                <div class="product-card" data-category="<?= $product['category_id'] ?? '' ?>" data-price="<?= $product['price'] ?? 0 ?>" data-aos="zoom-in" data-aos-delay="<?= ($index * 100) + 200 ?>">
                    <?php
                        // Try to determine intrinsic image dimensions to improve LCP and avoid layout shifts
                        $img_width = null;
                        $img_height = null;
                        $local_img_path = '';
                        try {
                            $uploads_dir = $pathConfig->filePath('product_uploads');
                            if (!empty($uploads_dir) && !empty($primary_image)) {
                                $local_img_path = rtrim($uploads_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $primary_image;
                                if (file_exists($local_img_path) && is_readable($local_img_path)) {
                                    $size = @getimagesize($local_img_path);
                                    if ($size && isset($size[0]) && isset($size[1])) {
                                        $img_width = (int)$size[0];
                                        $img_height = (int)$size[1];
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // ignore errors - fallback to no dimensions
                        }

                        // Prioritize above-the-fold images: first 4 items should load with high priority
                        $loading_attr = ($index < 4) ? 'eager' : 'lazy';
                        $fetch_priority = ($index < 4) ? 'high' : 'auto';
                    ?>
                    <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="product-image-link">
                        <?php
                            // Build responsive variants and srcsets (look for generated -{w}.webp and -{w}.jpg/png variants)
                            $variant_widths = [400,800,1200];
                            $webp_srcset_parts = [];
                            $fallback_srcset_parts = [];
                            try {
                                if (!empty($local_img_path)) {
                                    foreach ($variant_widths as $w) {
                                        $webp_local_variant = preg_replace('/\.[^.]+$/', '-' . $w . '.webp', $local_img_path);
                                        $fallback_local_variant = preg_replace('/\.[^.]+$/', '-' . $w . preg_replace('/^.*\./', '.', $local_img_path), $local_img_path);

                                        // Simpler fallback: try jpg then png
                                        $fallback_local_jpg = preg_replace('/\.[^.]+$/', '-' . $w . '.jpg', $local_img_path);
                                        $fallback_local_png = preg_replace('/\.[^.]+$/', '-' . $w . '.png', $local_img_path);

                                        if (file_exists($webp_local_variant) && is_readable($webp_local_variant)) {
                                            $webp_url_variant = preg_replace('/\.[^.]+$/', '-' . $w . '.webp', $image_path);
                                            $webp_srcset_parts[] = htmlspecialchars($webp_url_variant, ENT_QUOTES, 'UTF-8') . ' ' . $w . 'w';
                                        }

                                        if (file_exists($fallback_local_jpg) && is_readable($fallback_local_jpg)) {
                                            $fallback_url_variant = preg_replace('/\.[^.]+$/', '-' . $w . '.jpg', $image_path);
                                            $fallback_srcset_parts[] = htmlspecialchars($fallback_url_variant, ENT_QUOTES, 'UTF-8') . ' ' . $w . 'w';
                                        } elseif (file_exists($fallback_local_png) && is_readable($fallback_local_png)) {
                                            $fallback_url_variant = preg_replace('/\.[^.]+$/', '-' . $w . '.png', $image_path);
                                            $fallback_srcset_parts[] = htmlspecialchars($fallback_url_variant, ENT_QUOTES, 'UTF-8') . ' ' . $w . 'w';
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                // ignore
                            }
                        ?>
                        <picture>
                            <?php if (!empty($webp_srcset_parts)): ?>
                                <source type="image/webp" srcset="<?= implode(', ', $webp_srcset_parts) ?>" sizes="(max-width:600px) 100vw, 25vw">
                            <?php endif; ?>
                            <img src="<?= $image_path ?>" <?= !empty($fallback_srcset_parts) ? 'srcset="' . implode(', ', $fallback_srcset_parts) . '" sizes="(max-width:600px) 100vw, 25vw"' : '' ?> alt="<?= htmlspecialchars($product_name) ?>" class="product-image" loading="<?= $loading_attr ?>" fetchpriority="<?= $fetch_priority ?>" <?= $img_width && $img_height ? 'width="' . $img_width . '" height="' . $img_height . '"' : '' ?> decoding="async">
                        </picture>
                    </a>
                    <div class="product-info">
                        <h3 class="product-title"><?= htmlspecialchars($product_name) ?></h3>
                        <p class="product-price"><?= number_format($product['price'] ?? 0, 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?? 'piece' ?></p>
                        <?php if (isset($product['bulk_price']) && $product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                            <p class="product-bulk-price"><?= LanguageHelper::t('bulk_price', 'Bulk price') ?>: <?= number_format($product['bulk_price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?? 'piece' ?></p>
                        <?php endif; ?>
                        <div class="product-actions">
                            <button class="btn add-to-cart" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product_name) ?>" data-price="<?= $product['price'] ?? 0 ?>" data-unit="<?= $product['unit'] ?? 'piece' ?>" data-image="<?= $image_path ?>" <?= (($product['stock_quantity'] ?? 0) <= 0) ? 'disabled' : '' ?>><?= (($product['stock_quantity'] ?? 0) <= 0) ? LanguageHelper::t('out_of_stock', 'Out of Stock') : LanguageHelper::t('add_to_cart', 'Add to Cart') ?></button>
                            <?php if (($product['stock_quantity'] ?? 0) > 0): ?>
                            <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>"><input type="hidden" name="product_slug" value="<?= $product_slug ?>"><input type="hidden" name="quantity" value="1"><button type="submit" class="btn btn-primary"><?= LanguageHelper::t('buy_now', 'Buy Now') ?></button></form>
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
        
        <div class="view-more-container" data-aos="fade-up" data-aos-delay="500" 
             style="text-align: center; margin-top: 20px;">
            <a href="<?= $pathConfig->url('products') ?>" 
               style="
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        background: #666;
                        color: #fff;
                        padding: 10px 20px;
                        border-radius: 6px;
                        font-size: 14px;
                        text-decoration: none;
                        font-weight: 600;
                        transition: background 0.3s ease;
               "
               onmouseover="this.style.background='#555'"
               onmouseout="this.style.background='#666'"
            >
                <?= LanguageHelper::t('view_more', 'View More Products') ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block;">
                    <path d="M5 12h14m-7-7l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</section>
        <?php include __DIR__ . '/p.php'; ?>
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
        <?php if (!empty($video_items)): ?>
            <div class="media-grid">
                <?php foreach ($video_items as $index => $video): 
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
                                    <svg viewBox="0 0 24 24" fill="#ffffff">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M5 12h14m-7-7l7 7-7 7"/>
                    </svg>
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
// =========================================================================
// SCROLL FIXES: Only rely on viewport meta and CSS touch-action
// =========================================================================
function setFixedViewportMeta() {
    // Set viewport meta tag to prevent initial zoom, but allow native scrolling
    const viewportMeta = document.querySelector('meta[name="viewport"]');
    if (viewportMeta) {
        // Removed 'user-scalable=no' and 'maximum-scale=1.0' to be less restrictive
        // and avoid triggering scroll issues, but added a gesture listener for pinch
        viewportMeta.setAttribute('content', 'width=device-width, initial-scale=1.0');
    }
}

function handlePinchZoomPrevention() {
     // Prevent pinch zoom manually on the document
    document.addEventListener('touchmove', function(event) {
        // If two or more fingers are used, prevent default (which is usually pinch zoom)
        if (event.touches.length > 1) {
            event.preventDefault();
        }
    }, { passive: false });
    
    // Prevent double-tap zoom
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = (new Date()).getTime();
        // Check for quick second tap
        if (now - lastTouchEnd <= 300) {
            // Check if touch count is 1 (to not interfere with carousel swiping)
            if (event.touches.length === 0) { 
                 event.preventDefault();
            }
        }
        lastTouchEnd = now;
    }, false);
}


// =========================================================================
// CAROUSEL FUNCTIONALITY - NO MAJOR CHANGES HERE, AS CSS HANDLES THE SCROLL
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
    
    // Set total slides
    totalSlidesSpan.textContent = totalSlides;
    
    // Debounce function
    const debounce = (func, delay) => {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    };

    // Update arrow visibility based on current position
    function updateArrowVisibility() {
        if (window.innerWidth >= 1025) {
            const scrollLeft = track.scrollLeft;
            const slideWidth = slides[0].offsetWidth;
            const margin = 25; // Gap
            
            const contentWidth = (slideWidth * totalSlides) + (margin * (totalSlides - 1));
            const trackWidth = track.offsetWidth;
            const maxScroll = contentWidth - trackWidth;

            if (prevBtn) {
                prevBtn.disabled = scrollLeft <= 5;
                prevBtn.style.opacity = prevBtn.disabled ? '0.3' : '1';
                prevBtn.style.cursor = prevBtn.disabled ? 'not-allowed' : 'pointer';
            }
            
            if (nextBtn) {
                nextBtn.disabled = scrollLeft >= (maxScroll - 5);
                nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
                nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
            }
        } else {
            // On mobile, arrows remain enabled for manual slide transition
            if (prevBtn) { prevBtn.disabled = false; prevBtn.style.opacity = '1'; prevBtn.style.cursor = 'pointer'; }
            if (nextBtn) { nextBtn.disabled = false; nextBtn.style.opacity = '1'; nextBtn.style.cursor = 'pointer'; }
        }
    }
    
    // Mobile-specific adjustments (Mostly handled by CSS now, but good to keep logic)
    function adjustForMobile() {
        if (window.innerWidth <= 1024) {
             // Mobile styles are primarily driven by CSS 100vw and scroll-snap
        } else {
             // Desktop styles are primarily driven by CSS calc(50% - 12.5px)
        }
        updateArrowVisibility();
    }
    
    // Update indicators
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
    
    // Scroll to slide
    function scrollToSlide(index, behavior = 'smooth') {
        if (index < 0 || index >= totalSlides) {
            if (window.innerWidth <= 1024) {
                 index = (index + totalSlides) % totalSlides;
                 // Set behavior to 'auto' for wrapping only if it wasn't already 'smooth'
                 behavior = 'auto'; 
            } else {
                return;
            }
        }
        
        currentIndex = index;
        
        let scrollPosition;
        const slideWidth = slides[0].offsetWidth;
        
        if (window.innerWidth <= 1024) {
            // Mobile: Use native scroll snapping in CSS
            scrollPosition = slideWidth * index;
        } else {
            // Desktop: Account for margin
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
    
    // Next slide
    function nextSlide() {
        scrollToSlide(currentIndex + 1);
    }
    
    // Previous slide
    function prevSlide() {
        scrollToSlide(currentIndex - 1);
    }
    
    // Debounced scroll handler to update index only when scroll settles
    const handleScroll = debounce(() => {
        const scrollLeft = track.scrollLeft;
        
        let newIndex;
        if (window.innerWidth <= 1024) {
            // Mobile: width is 100vw
            const slideWidth = track.offsetWidth; 
            newIndex = Math.round(scrollLeft / slideWidth);
        } else {
            // Desktop: account for margin
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
    
    // Initialize scroll event listener
    track.addEventListener('scroll', handleScroll);
    
    // Add click events to dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => scrollToSlide(index));
    });
    
    // Add click events to navigation arrows
    if (prevBtn) {
        prevBtn.addEventListener('click', prevSlide);
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', nextSlide);
    }
    
    // Auto scroll
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
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            adjustForMobile();
            scrollToSlide(currentIndex, 'auto');
        }, 250);
    });
    
    // Initialize
    adjustForMobile();
    updateIndicators();
    updateArrowVisibility();
    startAutoScroll();
}


// =========================================================================
// DOCUMENT READY LOGIC
// =========================================================================
document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize anti-zoom protection and viewport fixes
    setFixedViewportMeta();
    handlePinchZoomPrevention(); // Use safer pinch/double-tap detection
    
    // 2. Initialize carousel
    initializeCarousel();
    
    // 3. Fix mobile viewport issues (vh unit calculation)
    function fixMobileViewport() {
        if (window.innerWidth <= 1024) {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
    }
    
    fixMobileViewport();
    window.addEventListener('resize', fixMobileViewport);
    
    // ... (All other functionalities like Notice Modals, Form Submissions, Cart, Fixed Buttons, etc. remain the same) ...
    // Notice Modal Management
    const notices = <?= json_encode($active_notices) ?>;
    let currentNoticeIndex = 0;
    let shownNoticesToday = JSON.parse(localStorage.getItem('shownNoticesToday') || '[]');
    let progressInterval;
    
    // Initialize notice modals
    function initializeNotices() {
        if (notices.length === 0) return;
        
        // Filter notices that haven't been shown today
        const noticesToShow = notices.filter((notice, index) => {
            // Check if the actual ID is not in the shown array
            return !shownNoticesToday.includes(parseInt(notice.id));
        });
        
        if (noticesToShow.length > 0) {
            // Show first notice after delay
            setTimeout(() => {
                showNoticeModal(noticesToShow[0].id);
            }, 1500);
        }
    }
    
    // Show specific notice by ID
    function showNoticeModal(noticeId) {
        const noticeIndex = notices.findIndex(notice => parseInt(notice.id) === parseInt(noticeId));
        if (noticeIndex === -1) return;
        
        const modal = document.getElementById(`noticeModal${noticeIndex}`);
        if (modal) {
            modal.style.display = 'block';
            currentNoticeIndex = noticeIndex;
            
            // Start progress bar
            startProgressBar(noticeIndex);
            
            // Mark as shown today
            if (!shownNoticesToday.includes(parseInt(noticeId))) {
                shownNoticesToday.push(parseInt(noticeId));
                localStorage.setItem('shownNoticesToday', JSON.stringify(shownNoticesToday));
            }
        }
    }
    
    // Start progress bar for auto-advance
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
                width += 0.5; // 10 seconds total
                progressBar.style.width = width + '%';
            }
        }, 50);
    }
    
    // Close current notice and show next one
    function closeCurrentNoticeAndShowNext() {
        clearInterval(progressInterval);
        
        const currentModal = document.getElementById(`noticeModal${currentNoticeIndex}`);
        if (currentModal) {
            currentModal.style.display = 'none';
        }
        
        // Find next notice that hasn't been shown in this session
        const nextNotice = notices.find((notice, index) => 
            index > currentNoticeIndex && !shownNoticesToday.includes(parseInt(notice.id))
        );
        
        if (nextNotice) {
            setTimeout(() => {
                showNoticeModal(nextNotice.id);
            }, 300);
        }
    }
    
    // Set up event listeners for all notice modals
    notices.forEach((notice, index) => {
        const modal = document.getElementById(`noticeModal${index}`);
        if (modal) {
            // Close button
            const closeBtn = modal.querySelector('.notice-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    clearInterval(progressInterval);
                    closeCurrentNoticeAndShowNext();
                });
            }
            
            // Close when clicking outside (but only close current modal)
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    clearInterval(progressInterval);
                    closeCurrentNoticeAndShowNext();
                }
            });
            
            // Prevent closing when clicking inside modal content
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Pause progress on hover
            modal.addEventListener('mouseenter', function() {
                clearInterval(progressInterval);
            });
            
            // Resume progress when mouse leaves
            modal.addEventListener('mouseleave', function() {
                if (modal.style.display === 'block') {
                    startProgressBar(index);
                }
            });
        }
    });
    
    // Test modal button functionality
    const testModalBtn = document.getElementById('testModalBtn');
    if (testModalBtn && notices.length > 0) {
        testModalBtn.addEventListener('click', function() {
            // Reset shown notices for testing
            shownNoticesToday = [];
            localStorage.setItem('shownNoticesToday', '[]');
            showNoticeModal(notices[0].id);
        });
    }
    
    // Clear shown notices at the start of new day
    function clearExpiredNotices() {
        const today = new Date().toDateString();
        const lastClearDate = localStorage.getItem('lastClearDate');
        
        if (lastClearDate !== today) {
            localStorage.setItem('shownNoticesToday', '[]');
            localStorage.setItem('lastClearDate', today);
            shownNoticesToday = [];
        }
    }
    
    // Initialize
    clearExpiredNotices();
    initializeNotices();

    // Form submission handler
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
    
    // Add to cart functionality
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            const productData = {product_id: this.getAttribute('data-id'),product_name: this.getAttribute('data-name'),price: this.getAttribute('data-price'),unit: this.getAttribute('data-unit'),image: this.getAttribute('data-image'),quantity: 1};
            const originalButton = this;
            const originalText = this.textContent;
            this.textContent = '<?= LanguageHelper::t('adding', 'Adding...') ?>';
            this.disabled = true;
            
            // Use FormData instead of JSON for better compatibility
            const formData = new FormData();
            formData.append('product_id', productData.product_id);
            formData.append('product_name', productData.product_name);
            formData.append('price', productData.price);
            formData.append('unit', productData.unit);
            formData.append('image', productData.image);
            formData.append('quantity', productData.quantity);
            
            fetch('<?= $pathConfig->url('cart/add') ?>', {method: 'POST',body: formData})
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    originalButton.textContent = "✓ <?= LanguageHelper::t('added', 'Added') ?>";
                    originalButton.classList.add('added');
                    showCartNotification();
                    updateCartCount();
                    setTimeout(() => {originalButton.textContent = originalText;originalButton.classList.remove('added');originalButton.disabled = false;}, 2000);
                } else {alert('<?= LanguageHelper::t('failed_to_add_cart', 'Failed : ') ?>' + (data.message || 'Unknown error'));originalButton.textContent = originalText;originalButton.disabled = false;}
            })
            .catch(error => {console.error('Error:', error);alert('<?= LanguageHelper::t('error_adding_cart', 'Error ') ?>');originalButton.textContent = originalText;originalButton.disabled = false;});
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
        }).catch(error => console.error('Error updating cart count:', error));
    }
    
    // Contact modal functionality
    const modal = document.getElementById("contactModal");
    const btn = document.getElementById("contactBtn");
    const span = document.getElementsByClassName("close")[0];
    if (btn && modal) btn.onclick = function() {modal.style.display = "block";}
    if (span) span.onclick = function() {if (modal) modal.style.display = "none";}
    if (modal) window.onclick = function(event) {if (event.target == modal) modal.style.display = "none";}
    
    // Install modal close functionality
    const installModal = document.getElementById("installGuideModal");
    if (installModal) {
        const installClose = installModal.querySelector('.close');
        if (installClose) installClose.onclick = function() {installModal.style.display = "none";}
        window.onclick = function(event) {
            if (event.target == installModal) installModal.style.display = "none";
        }
    }
    
    // Blinking animation for fixed buttons
    function startBlinkingAnimation() {
        const orderBtn = document.querySelector('.order-now-btn');
        const contactBtn = document.querySelector('.contact-now-btn');
        if (orderBtn) orderBtn.style.animation = 'blinkPulse 2s infinite';
        if (contactBtn) contactBtn.style.animation = 'blinkPulse 2s infinite 1s';
    }
    setTimeout(startBlinkingAnimation, 1000);

});
</script>

<style>
/* =========================================================================
// CRITICAL SCROLL FIXES
// ========================================================================= */
body {
    overflow-x: hidden;
    max-width: 100%;
    position: relative;
    /* Removed -webkit-overflow-scrolling: touch; as it can sometimes conflict */
}

/* CAROUSEL TRACK FIXES */
.carousel-track-v2 {
    display: flex;
    height: 100%;
    max-width: 100%;
    overflow-x: auto; 
    scroll-snap-type: x mandatory; /* Keep this for native swipe on mobile */
    scroll-behavior: smooth;
    scrollbar-width: none;
    -ms-overflow-style: none;
    /* CRITICAL FIX: Ensures horizontal drag in the carousel area is prioritized */
    touch-action: pan-y; 
}

/* MOBILE CAROUSEL FIXES */
@media (max-width: 1024px) {
    .carousel-track-v2 {
        /* Re-emphasize touch-action for mobile */
        touch-action: pan-y;
        /* Ensure there are no weird paddings/margins on the track */
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .carousel-slide-v2 {
        /* Ensure slides snap properly and don't introduce extra horizontal space */
        scroll-snap-align: center; /* Changed from start for better user experience */
    }
    
    /* Ensure no residual side scroll from other elements */
    .container { 
        overflow-x: hidden; 
        max-width: 100vw;
        padding: 0 15px; /* Keep padding on container */
    }
    
    /* Ensure the main sections also respect screen width */
    .hero-section, .featured-products, .features, .media-section {
        max-width: 100vw;
        overflow: hidden;
    }
}
/* =========================================================================
// END OF CRITICAL SCROLL FIXES
// ========================================================================= */

/* ========== FIXED CAROUSEL STYLES - ORIGINAL STYLES ========== */
.hero-section { 
    margin-bottom: 20px; 
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}
.hero-container { 
    display: flex; 
    width: 100%; 
    max-width: 100%;
    align-items: stretch; 
    min-height: 400px; 
    overflow: hidden;
}
.carousel-column-full { 
    flex: 1; 
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.hero-carousel-v2 { 
    position: relative; 
    height: 100%; 
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    border-radius: 12px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.carousel-track-v2::-webkit-scrollbar {
    display: none;
}

.carousel-slide-v2 {
    flex-shrink: 0;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    scroll-snap-align: start;
    border-radius: 12px;
    position: relative;
    box-sizing: border-box;
}

.slide-button-v2 { 
    position: absolute;
    z-index: 2;
}

.carousel-slide-v2 .slide-button-v2 {
    position: absolute;
    right: 10%;
    bottom: 15%;
    width: auto;
    transform: none !important;
}

.carousel-slide-v2[data-slide-index="0"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="1"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="2"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="3"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="4"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="5"] .slide-button-v2,
.carousel-slide-v2[data-slide-index="6"] .slide-button-v2 {
    right: 60% !important;
    bottom: 15% !important;
    left: auto !important;
    top: auto !important;
}

.carousel-order-v2 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    padding: 12px 24px !important;
    border-radius: 20px !important;
    font-weight: 600 !important;
    border: none !important;
    cursor: pointer !important;
    text-decoration: none !important;
    display: inline-block !important;
    font-size: clamp(0.8rem, 1.5vw, 1rem) !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
    min-width: 160px !important;
    max-width: 200px !important;
    white-space: nowrap !important;
    text-align: center !important;
    box-sizing: border-box !important;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.carousel-order-v2:hover {
    transform: translateY(-2px) scale(1.05) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
}

.carousel-slide-v2[data-slide-index="0"] .carousel-order-v2 {
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A24 100%) !important;
}

.carousel-slide-v2[data-slide-index="1"] .carousel-order-v2 {
    background: linear-gradient(135deg, #48DBFB 0%, #18DCFF 100%) !important;
}

.carousel-slide-v2[data-slide-index="2"] .carousel-order-v2 {
    background: linear-gradient(135deg, #7BED9F 0%, #2ED573 100%) !important;
}

.carousel-slide-v2[data-slide-index="3"] .carousel-order-v2 {
    background: linear-gradient(135deg, #FF9F43 0%, #FECA57 100%) !important;
}

.carousel-slide-v2[data-slide-index="4"] .carousel-order-v2 {
    background: linear-gradient(135deg, #5F27CD 0%, #341F97 100%) !important;
}

.carousel-slide-v2[data-slide-index="5"] .carousel-order-v2 {
    background: linear-gradient(135deg, #00D2D3 0%, #01A3A4 100%) !important;
}

.carousel-slide-v2[data-slide-index="6"] .carousel-order-v2 {
    background: linear-gradient(135deg, #FF9FF3 0%, #F368E0 100%) !important;
}

.carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.carousel-arrow:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.7);
    transform: translateY(-50%) scale(1.1);
}

.carousel-arrow:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.carousel-arrow-left {
    left: 10px;
}

.carousel-arrow-right {
    right: 10px;
}

.carousel-arrow svg {
    width: 24px;
    height: 24px;
}

.carousel-indicators-v2 {
    position: absolute;
    bottom: 15px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    z-index: 3;
}

.dot-indicators {
    display: flex;
    gap: 8px;
    background: rgba(0, 0, 0, 0.5);
    padding: 6px 12px;
    border-radius: 20px;
    backdrop-filter: blur(5px);
}

.dot {
    font-size: 12px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0.3;
}

.dot.active {
    opacity: 1;
    transform: scale(1.2);
}

.slide-number {
    background: rgba(0, 0, 0, 0.5);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(5px);
}

.current-slide {
    font-weight: 700;
}

.total-slides {
    opacity: 0.8;
}

/* ========== DESKTOP STYLES: TWO COMPLETE SLIDES ONLY ========== */
@media (min-width: 1025px) {
    .hero-carousel-v2 {
        overflow: hidden !important;
        max-width: 1400px;
        margin: 0 auto;
    }

    .carousel-track-v2 {
        width: 100%;
        max-width: 100%;
        padding-right: 0 !important;
        scroll-snap-type: none;
        touch-action: pan-y;
    }

    .carousel-slide-v2 {
        width: calc(50% - 12.5px) !important;
        flex: 0 0 calc(50% - 12.5px) !important;
        margin-right: 25px;
        scroll-snap-align: start;
    }

    .carousel-slide-v2:last-child {
        margin-right: 0;
    }
    
    .hero-carousel-v2::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 30px;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1));
        pointer-events: none;
        z-index: 2;
        border-radius: 0 12px 12px 0;
    }
    
    .carousel-arrow {
        display: flex;
    }
}

/* ========== MOBILE CAROUSEL FIXES (Re-adjusted) ========== */
@media (max-width: 1024px) {
    .hero-container {
        flex-direction: column;
        min-height: 250px;
        width: 100%;
        max-width: 100vw;
        margin: 0;
        padding: 0;
    }
    
    .carousel-column-full {
        height: 250px;
        overflow: hidden;
        width: 100%;
        max-width: 100vw;
        padding: 0;
    }
    
    .hero-carousel-v2 {
        overflow: visible;
        width: 100%;
        max-width: 100vw;
        border-radius: 0;
        box-shadow: none;
        height: 250px;
    }
    
    .carousel-track-v2 {
        display: flex;
        height: 100%;
        max-width: 100vw;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        gap: 0 !important;
        padding: 0 !important;
        scrollbar-width: none;
        -ms-overflow-style: none;
        width: 100vw;
        touch-action: pan-y; /* CRITICAL FIX: Explicitly allow vertical scroll outside of horizontal area */
    }
    
    .carousel-slide-v2 {
        flex: 0 0 100vw !important;
        width: 100vw !important;
        max-width: 100vw;
        height: 100%;
        scroll-snap-align: center; /* Changed from start */
        border-radius: 0;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        margin-right: 0 !important;
    }
    
    .slide-button-v2 {
        position: absolute !important;
        left: 60% !important;
        bottom: 40px !important;
        top: auto !important;
        padding: 10px 15px !important;
        right: auto !important;
        transform: translateX(-50%) !important;
        width: 35% !important;
        min-width:10px !important;
    }
    
    .carousel-order-v2 {
        width: 5% !important;
        left: 10% !important;
        bottom: 8px !important;
        text-align: center !important;
        font-size: 0.7rem !important;
        padding: 10px 8px !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
        min-width:2px !important;
    }
    
    .carousel-arrow {
        display: flex !important;
        width: 36px;
        height: 36px;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    
    .carousel-arrow-left {
        left: 10px;
    }
    
    .carousel-arrow-right {
        right: 10px;
    }
    
    .carousel-indicators-v2 {
        bottom: 15px;
        gap: 10px;
        padding: 0 10px;
    }
    
    .dot-indicators {
        padding: 4px 8px;
        background: rgba(0, 0, 0, 0.6);
    }
    
    .slide-number {
        padding: 3px 8px;
        font-size: 12px;
        background: rgba(0, 0, 0, 0.6);
    }
    
    body {
        overflow-x: hidden;
        max-width: 100vw;
        position: relative;
        width: 100%;
    }
}

/* Extra small mobile devices */
@media (max-width: 375px) {
    .carousel-column-full {
        height: 200px;
    }
    
    .hero-carousel-v2 {
        height: 200px;
    }
    
    .slide-button-v2 {
        bottom: 13px !important;
    }
    
    .carousel-order-v2 {
        font-size: 0.7rem !important;
        padding: 8px 14px !important;
    }
}

/* Tablet devices */
@media (min-width: 768px) and (max-width: 1024px) {
    .carousel-column-full {
        height: 280px;
    }
    
    .hero-carousel-v2 {
        height: 280px;
    }
    
    .carousel-order-v2 {
        font-size: 0.9rem !important;
        padding: 12px 22px !important;
        min-width: 150px !important;
    }
}

/* Fix for iOS Safari specific issues */
@supports (-webkit-touch-callout: none) {
    .carousel-track-v2 {
        -webkit-overflow-scrolling: touch;
    }
    
    .carousel-slide-v2 {
        -webkit-transform: translateZ(0);
    }
}
/* ... (Rest of your original CSS styles follow, ensuring max-width is handled correctly) ... */

/* MODIFIED PRODUCT GRID STYLES - ADDED MAX-WIDTH PROTECTION */
.products-grid {
    display: grid;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    gap: 15px;
    overflow: hidden;
}

/* Large screens (1200px+) - 8x8, 7x7, 6x6, 5x5 depending on available space */
@media (min-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        max-width: 1400px;
    }
    
    .products-grid .product-card:nth-child(n+65) {
        display: none;
    }
}

/* Desktop (992px-1199px) */
@media (min-width: 992px) and (max-width: 1199px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        max-width: 1200px;
    }
    
    .products-grid .product-card:nth-child(n+50) {
        display: none;
    }
}

/* Tablet (768px-991px) */
@media (min-width: 768px) and (max-width: 991px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        max-width: 1000px;
    }
    
    .products-grid .product-card:nth-child(n+37) {
        display: none;
    }
}

/* Medium mobile (576px-767px) */
@media (min-width: 576px) and (max-width: 767px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        max-width: 800px;
    }
    
    .products-grid .product-card:nth-child(n+26) {
        display: none;
    }
}

/* Small mobile (up to 575px) */
@media (max-width: 575px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        max-width: 100%;
        padding: 0 10px;
    }
    
    .products-grid .product-card:nth-child(n+17) {
        display: none;
    }
}

/* City Info Badge */
.city-info-badge {
    text-align: center;
    margin-bottom: 20px;
    padding: 8px 16px;
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 20px;
    display: inline-block;
    margin-left: auto;
    margin-right: auto;
    max-width: 90%;
}

.badge-icon {
    margin-right: 8px;
}

.badge-text {
    font-size: 14px;
    color: #0066cc;
}

/* Current City Display Styles */
.current-city-section {
    margin: 20px 0;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    color: white;
    text-align: center;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

.current-city-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.city-icon {
    font-size: 24px;
    margin-bottom: 5px;
}

.city-name {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
}

.city-description {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

.change-city-link {
    color: #ffeb3b;
    text-decoration: underline;
    font-size: 14px;
    margin-top: 5px;
}

.change-city-link:hover {
    color: #fff;
}

@media (max-width: 768px) {
    .current-city-section {
        padding: 12px;
        margin: 15px 10px;
        width: calc(100% - 20px);
    }
    
    .city-name {
        font-size: 18px;
    }
}

/* Cart Notification */
.cart-notification {position: fixed;top: 20px;right: 20px;background: #4CAF50;color: white;padding: 15px 20px;border-radius: 12px;box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);z-index: 10000;transform: translateX(150%);transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);max-width: 300px;backdrop-filter: blur(10px);}
.cart-notification.show {transform: translateX(0);}
.notification-content {display: flex;align-items: center;gap: 10px;}
.notification-icon {background: white;color: #4CAF50;width: 24px;height: 24px;border-radius: 50%;display: flex;align-items: center;justify-content: center;font-weight: bold;font-size: 14px;}
.add-to-cart.added {background-color: #4CAF50 !important;transform: scale(0.95);transition: all 0.3s ease;}
@media (max-width: 768px) {.cart-notification {top: 10px;right: 10px;left: 10px;max-width: calc(100% - 20px);transform: translateY(-100px);}.cart-notification.show {transform: translateY(0);}body {-webkit-overflow-scrolling: touch;}}
 
/* Fixed Buttons Container - MODIFIED: WhatsApp above Contact */
.fixed-buttons-container {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}

/* Individual Fixed Buttons */
.fixed-order-btn, 
.fixed-contact-btn {
    margin: 0;
    padding: 0;
}

/* Order Now Button - REDUCED WIDTH */
.order-now-btn {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
    color: white !important;
    padding: 10px 15px !important;
    border-radius: 50px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    margin-bottom: 5px;
    border: none !important;
    cursor: pointer !important;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4) !important;
    transition: all 0.3s ease !important;
    white-space: nowrap !important;
    min-width: 10px !important;
    text-align: center !important;
    display: block !important;
}

.order-now-btn:hover {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.5) !important;
}

/* Contact Us Button - REDUCED WIDTH */
.contact-now-btn {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%) !important;
    color: white !important;
    padding: 10px 15px !important;
    border-radius: 50px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border: none !important;
    cursor: pointer !important;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4) !important;
    transition: all 0.3s ease !important;
    white-space: nowrap !important;
    min-width: 10px !important;
    text-align: center !important;
    display: block !important;
}

.contact-now-btn:hover {
    background: linear-gradient(135deg, #2980b9 0%, #3498db 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5) !important;
}

/* WhatsApp Button - POSITIONED ABOVE Contact button */
.fixed-whatsapp-btn {
    /* Adjusted margin to position above contact and order buttons */
    margin-bottom: 170px; 
    padding: 0;
}

.fixed-whatsapp-btn a {
    display: flex;
    align-items: center;
    justify-content: center; 
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white;
    border-radius: 50%;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
    transition: all 0.3s ease;
    text-decoration: none;
}

.fixed-whatsapp-btn a:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
    background: linear-gradient(135deg, #128C7E, #25D366);
}

/* Mobile Responsive Fixed Buttons */
@media (max-width: 768px) {
    .fixed-buttons-container {
        right: 10px;
        bottom: 10px;
        gap: 8px;
    }
    
    .order-now-btn,
    .contact-now-btn {
        padding: 10px 15px !important;
        font-size: 12px !important;
        min-width: 10px !important;
    }
    
    .fixed-whatsapp-btn {
        /* Adjusted margin for mobile stacking */
              padding: 10px 15px !important;
        margin-bottom: 170px; 
        padding: 0;
    }

    .fixed-whatsapp-btn a {
        width: 45px;
        height: 45px;
        font-size: 22px;
    }
}

@media (max-width: 480px) {
    .fixed-buttons-container {
        right: 5px;
        bottom: 170px;
    }
    
    .order-now-btn,
    .contact-now-btn {
        padding: 10px 15px !important;
        font-size: 11px !important;
        min-width: 10px !important;
    }

    .fixed-whatsapp-btn {
        margin-bottom: 8px;
    }
}

/* Blinking Animation - UPDATED */
@keyframes blinkPulse {
    0% {
        transform: scale(1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }
}

.order-now-btn {
    animation: blinkPulse 2s infinite;
}

.contact-now-btn {
    animation: blinkPulse 2s infinite 1s;
}

/* Notice Modal Styles - MODERN E-COMMERCE DESIGN */
.notice-modal .modal-content {
    max-width: 95vw;
    max-height: 95vh;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
    margin: auto;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    background: transparent;
    border: none;
    backdrop-filter: blur(10px);
}

.notice-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
    border-radius: 20px;
}

.notice-media {
    width: 100%;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.notice-media img,
.notice-media video {
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
    border-radius: 0;
}

.notice-close {
    position: absolute;
    top: 15px;
    right: 15px;
    color: white;
    font-size: 28px;
    font-weight: 300;
    cursor: pointer;
    z-index: 100;
    background: rgba(0,0,0,0.6);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.notice-close:hover {
    background: rgba(0,0,0,0.8);
    transform: scale(1.1);
    border-color: rgba(255,255,255,0.4);
}

.notice-actions {
    padding: 25px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 0 0 12px 12px;
}

.notice-btn {
    width: 100%;
    padding: 16px 24px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-transform: none;
    letter-spacing: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.notice-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.notice-btn:hover::before {
    left: 100%;
}

.notice-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* Modal Backdrop */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from { 
        transform: translate(-50%, -50%) scale(0.8);
        opacity: 0; 
    }
    to { 
        transform: translate(-50%, -50%) scale(1);
        opacity: 1; 
    }
}

/* Progress indicator for multiple notices */
.notice-progress {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: rgba(255,255,255,0.2);
    z-index: 50;
}

.notice-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    width: 0%;
    transition: width 0.1s linear;
}

/* General Styles */
.hero-section { margin-bottom: 20px; }
.ad-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; }
.ad-media { width: 100%; object-fit: cover; border-radius: 12px; }

/* Media Section */
.media-section { 
    padding: 40px 0; 
    background: #f8f9fa; 
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}
.section-header { 
    text-align: center; 
    margin-bottom: 30px; 
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 15px;
}
.section-header h2 { color: #2d5016; font-size: 2rem; margin-bottom: 10px; }
.section-header p { color: #666; font-size: 1.1rem; }
.media-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
    gap: 20px; 
    margin-bottom: 30px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 15px;
}
.media-item { 
    background: white; 
    border-radius: 16px; 
    overflow: hidden; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 100%;
}
.media-item:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.15);}
.media-thumbnail { position: relative; height: 200px; overflow: hidden;}
.media-thumbnail img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;}
.media-item:hover .media-thumbnail img { transform: scale(1.05);}
.play-overlay {position: absolute;top: 0;left: 0;width: 100%;height: 100%;background: rgba(0,0,0,0.3);display: flex;align-items: center;justify-content: center;opacity: 0;transition: opacity 0.3s ease;}
.media-item:hover .play-overlay { opacity: 1;}
.play-overlay svg { width: 60px; height: 60px; }
.media-info { padding: 20px; }
.media-title { font-size: 1.2rem; color: #2d5016; margin-bottom: 10px; line-height: 1.3;}
.media-desc { color: #666; font-size: 0.9rem; margin-bottom: 15px; line-height: 1.4;}
.media-actions { text-align: center; }
.watch-more-btn {display: inline-flex;align-items: center;gap: 10px;background: linear-gradient(135deg, #2d5016 0%, #3a6720 100%);color: white;padding: 12px 24px;border-radius: 8px;text-decoration: none;font-weight: 600;transition: all 0.3s ease;}
.watch-more-btn:hover {background: linear-gradient(135deg, #3a6720 0%, #2d5016 100%);transform: translateY(-2px);box-shadow: 0 4px 15px rgba(45, 80, 22, 0.4);}
.no-media {text-align: center;padding: 60px 20px;color: #666;}

/* Featured Products */
.featured-products { 
    padding: 20px 0; 
    background: #f9f9f9; 
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}
.featured-products h2 { text-align: center; margin-bottom: 20px; color: #2d5016; font-size: 1.5rem; padding: 0 15px; }
.products-grid {
    display: grid;
    width: 100%;
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 15px;
    overflow: hidden;
}

/* Prevent cards from shrinking too much */
.product-card {
    background: white;
    min-width: 140px;
    max-width: 100%;
    width: 100%;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Large screens */
@media (min-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        max-width: 1400px;
        margin: 0 auto;
    }
}

/* Desktop */
@media (min-width: 992px) and (max-width: 1199px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        max-width: 1200px;
        margin: 0 auto;
    }
}

/* Tablet */
@media (min-width: 768px) and (max-width: 991px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        max-width: 1000px;
        margin: 0 auto;
    }
}

/* Medium mobile */
@media (min-width: 576px) and (max-width: 767px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        max-width: 800px;
        margin: 0 auto;
    }
}

/* Small mobile */
@media (max-width: 575px) {
    .products-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        max-width: 100%;
        margin: 0 auto;
        padding: 0 10px;
    }
}


.product-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.product-image { width: 100%; height: 120px; object-fit: cover; }
.product-info { padding: 15px; }
.product-title { font-size: 0.9rem; margin-bottom: 8px; color: #2d5016; }
.product-price { font-weight: bold; color: #2d5016; margin-bottom: 5px; font-size: 0.9rem; }
.product-bulk-price { color: #e74c3c; font-weight: bold; margin-bottom: 8px; font-size: 0.8rem; }
.product-actions { display: flex; flex-direction: row; gap: 8px; justify-content: space-between; }
.btn { padding: 8px 10px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.6rem; flex: 1; text-align: center; }
.btn-primary { background: linear-gradient(135deg, #2d5016 0%, #3a6720 100%); color: white; }
.btn-primary:hover { background: linear-gradient(135deg, #3a6720 0%, #2d5016 100%); }
.add-to-cart { background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%); color: #2d5016; }
.add-to-cart:hover:not(:disabled) { background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%); }
.add-to-cart:disabled { background: #ccc; cursor: not-allowed; }
.add-to-cart.added { background: #27ae60; color: white; }

/* Features Section */
.features { 
    padding: 30px 0; 
    background: white; 
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}
.features h2 { text-align: center; margin-bottom: 25px; color: #2d5016; font-size: 1.5rem; padding: 0 15px; }
.features-grid { 
    display: grid; 
    gap: 15px; 
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 15px;
}
.feature { text-align: center; padding: 15px; border-radius: 12px; background: #f9f9f9; transition: transform 0.3s ease; display: flex; flex-direction: column; align-items: center; width: 100%; }
.feature:hover { transform: translateY(-3px); }
.feature-icon { font-size: 1.8rem; margin-bottom: 10px; }
.feature h3 { margin-bottom: 8px; color: #2d5016; font-size: 1rem; }
.feature p { color: #666; line-height: 1.4; font-size: 0.8rem; margin: 0; }

/* Contact Modal */
.modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); position: relative; }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; right: 15px; top: 10px; }
.close:hover, .close:focus { color: #000; text-decoration: none; cursor: pointer; }
.contact-info h3 { color: #2d5016; margin-bottom: 15px; text-align: center; }
.contact-person { margin-bottom: 20px; text-align: center; }
.contact-person h4 { color: #2d5016; margin-bottom: 5px; }
.contact-person p { color: #666; font-size: 0.9rem; }
.contact-numbers { margin-bottom: 20px; }
.phone-number { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 8px; }
.phone-number span { font-weight: bold; color: #2d5016; }
.call-btn { background: linear-gradient(135deg, #27ae60 0%, #219653 100%); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; transition: all 0.3s ease; }
.call-btn:hover { background: linear-gradient(135deg, #219653 0%, #27ae60 100%); }
.contact-hours { text-align: center; font-size: 0.9rem; color: #666; }

/* Utility Classes */
.container { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 0 15px; 
    width: 100%;
}
.no-products { text-align: center; grid-column: 1 / -1; padding: 30px; color: #666; }

/* Responsive Styles for Features Grid */
@media (min-width: 769px) {
    .features-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .features-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .features-grid { grid-template-columns: 1fr; }
}

/* Responsive Styles */
@media (min-width: 769px) {
    .hero-carousel-v2, .ad-media { height: 400px; }
    .carousel-order-v2 {font-size: 1.1rem !important;padding: 14px 22px !important;min-width: 220px !important;}
    .media-grid { grid-template-columns: repeat(4, 1fr); gap: 25px;}
    .media-thumbnail { height: 180px; }
    .featured-products { padding: 40px 0; }
    .product-image { height: 200px; }
    .product-info { padding: 20px; }
    .product-title { font-size: 1.2rem; }
    .btn { padding: 10px 15px; font-size: 1rem; }
    .features { padding: 60px 0; }
    .features-grid { gap: 30px; }
    .feature { padding: 30px 20px; }
    .feature-icon { font-size: 3rem; margin-bottom: 20px; }
    .feature h3 { font-size: 1.3rem; margin-bottom: 15px; }
    .feature p { font-size: 1rem; }
}

@media (max-width: 768px) {
    .carousel-order-v2 {font-size: 0.8rem !important;padding: 8px 12px !important;min-width: 150px !important;}
    .media-grid { grid-template-columns: repeat(2, 1fr); gap: 15px;}
    .media-thumbnail { height: 150px; }
    .media-info { padding: 15px; }
    .media-title { font-size: 1rem; }
}

@media (max-width: 480px) {
    .media-grid { grid-template-columns: 1fr; }
    .section-header h2 { font-size: 1.5rem; }
    .section-header p { font-size: 1rem; }
    .product-actions .btn {
        font-size: 0.65rem;
        padding: 7px 4px;
    }
    .product-title {
        font-size: 0.85rem;
    }
    .product-price, .product-bulk-price {
        font-size: 0.8rem;
    }
}
</style>

