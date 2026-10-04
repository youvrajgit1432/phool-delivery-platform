<?php
// app/views/products/detail.php

if (!isset($product) || empty($product)) {
    header('Location: /products');
    exit;
}

LanguageHelper::initialize();

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

// Apply localization to product data
$product['name'] = LanguageHelper::getLocalizedText($product, 'name');
$product['description'] = LanguageHelper::getLocalizedText($product, 'description');
$product['slug'] = LanguageHelper::getLocalizedSlug($product);

// Apply localization to category data
if (isset($product['category_name'])) {
    $category_data = [
        'name_en' => $product['category_name_en'] ?? $product['category_name'],
        'name_ne' => $product['category_name_ne'] ?? $product['category_name']
    ];
    $product['category_name'] = LanguageHelper::getLocalizedText($category_data, 'name');
}

$page_title = $product['name'] . ' - Phool Delivery';
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');
$product_images_path = $pathConfig->get('product_images');
$profile_images_path = $pathConfig->get('profiles');

function getImagePath($image_name, $type = 'product') {
    global $pathConfig;
    return $pathConfig->getImagePath($image_name, $type);
}

$vendor_name = !empty($product['vendor_name']) ? $product['vendor_name'] : "Devitar Agro Farm";
$is_logged_in = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);

// Apply localization to related products
if (isset($related_products)) {
    foreach ($related_products as &$related_product) {
        $related_product['name'] = LanguageHelper::getLocalizedText($related_product, 'name');
        $related_product['description'] = LanguageHelper::getLocalizedText($related_product, 'description');
        $related_product['slug'] = LanguageHelper::getLocalizedSlug($related_product);
        
        if (isset($related_product['category_name'])) {
            $related_category_data = [
                'name_en' => $related_product['category_name_en'] ?? $related_product['category_name'],
                'name_ne' => $related_product['category_name_ne'] ?? $related_product['category_name']
            ];
            $related_product['category_name'] = LanguageHelper::getLocalizedText($related_category_data, 'name');
        }
    }
    unset($related_product);
}
?>

<!-- Contact Modal -->
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
                <div class="phone-number">
                    <span>📞 9800000000</span>
                    <a href="tel:9800000000" class="call-btn"><?= LanguageHelper::t('call_now', 'Call Now') ?></a>
                </div>
                <div class="phone-number">
                    <span>📞 9800000001</span>
                    <a href="tel:9800000001" class="call-btn"><?= LanguageHelper::t('call_now', 'Call Now') ?></a>
                </div>
            </div>
            <div class="contact-hours">
                <p><strong><?= LanguageHelper::t('available_hours', 'Available Hours') ?>:</strong> 8:00 AM - 8:00 PM</p>
            </div>
        </div>
    </div>
</div>

<section class="product-detail-page">
    <a href="<?= $pathConfig->url('products') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        font-size: 24px;
        color: #333;
        text-decoration: none;
        transition: color 0.2s ease;
   "
   onmouseover="this.style.color='#777';"
   onmouseout="this.style.color='#333';"
>
    <i class="fas fa-arrow-left" style="margin-right: 6px;"></i> 
</a>

    <div class="container">
        <nav class="breadcrumb" data-aos="fade-up" data-aos-delay="100">
            <a href="/"><?= LanguageHelper::t('home', 'Home') ?></a> &gt;
            <a href="/products"><?= LanguageHelper::t('products', 'Products') ?></a> &gt;
            <a href="/category/<?= $product['category_id'] ?>"><?= $product['category_name'] ?></a> &gt;
            <span><?= htmlspecialchars($product['name']) ?></span>
        </nav>

        <div class="product-detail-container">
            <div class="product-gallery" data-aos="zoom-in" data-aos-delay="200">
                <?php if (!empty($product['all_images'])): ?>
                    <div class="main-image">
                        <img src="<?= getImagePath($product['primary_image'] ?: $product['all_images'][0]['image_path']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             id="main-product-image">
                    </div>
                    
                    <div class="thumbnail-gallery">
                        <?php foreach ($product['all_images'] as $index => $image): ?>
                            <div class="thumbnail <?= ($index === 0) ? 'active' : '' ?>" 
                                 data-image="<?= getImagePath($image['image_path']) ?>">
                                <img src="<?= getImagePath($image['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?> - Thumbnail <?= $index + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="main-image">
                        <img src="<?= getImagePath('default.jpg') ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info" data-aos="fade-up" data-aos-delay="300">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                
                <?php if (!empty($product['vendor_id'])): ?>
                <div class="product-vendor">
                    <?= LanguageHelper::t('sold_by', 'Sold by') ?>: 
                    <div class="vendor-info">
                        <?php if (!empty($product['vendor_profile_image'])): ?>
                        <img src="<?= getImagePath($product['vendor_profile_image'], 'profile') ?>" 
                             alt="<?= htmlspecialchars($vendor_name) ?>" 
                             class="vendor-profile-image">
                        <?php endif; ?>
                        <span class="vendor-name"><?= htmlspecialchars($vendor_name) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="product-category">
                    <?= LanguageHelper::t('category', 'Category') ?>: <span><?= $product['category_name'] ?></span>
                </div>
                
                <div class="product-price-section">
                    <p class="product-price">Rs. <?= number_format($product['price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?></p>
                    
                    <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                        <p class="product-bulk-price"><?= LanguageHelper::t('bulk_price', 'Bulk price') ?>: Rs. <?= number_format($product['bulk_price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?></p>
                    <?php endif; ?>
                    
                    <?php if ($product['event_price']): ?>
                        <p class="product-event-price"><?= LanguageHelper::t('event_price', 'Event price') ?>: Rs. <?= number_format($product['event_price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="product-stock">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="in-stock"><?= LanguageHelper::t('in_stock', 'In Stock') ?> (<?= $product['stock_quantity'] ?> <?= $product['unit'] ?> <?= LanguageHelper::t('available', 'available') ?>)</span>
                    <?php else: ?>
                        <span class="out-of-stock"><?= LanguageHelper::t('out_of_stock', 'Out of Stock') ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="product-description">
                    <h3><?= LanguageHelper::t('description', 'Description') ?></h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                
                <div class="add-to-cart-section">
                    <div class="quantity-selector">
                        <label for="quantity"><?= LanguageHelper::t('quantity', 'Quantity') ?>:</label>
                        <div class="quantity-controls">
                            <button class="quantity-btn minus" type="button">-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                   max="<?= $product['stock_quantity'] ?>" class="quantity-input">
                            <button class="quantity-btn plus" type="button">+</button>
                        </div>
                        <span class="unit-display"><?= $product['unit'] ?></span>
                    </div>
                    
                    <div class="cart-actions">
                        <button class="btn btn-primary add-to-cart-btn" 
                                data-id="<?= $product['id'] ?>" 
                                data-name="<?= htmlspecialchars($product['name']) ?>" 
                                data-price="<?= $product['price'] ?>"
                                data-unit="<?= $product['unit'] ?>"
                                data-image="<?= !empty($product['primary_image']) ? getImagePath($product['primary_image']) : (isset($product['all_images'][0]['image_path']) ? getImagePath($product['all_images'][0]['image_path']) : getImagePath('default.jpg')) ?>"
                                <?= ($product['stock_quantity'] <= 0) ? 'disabled' : '' ?>>
                            <?= ($product['stock_quantity'] <= 0) ? LanguageHelper::t('out_of_stock', 'Out of Stock') : LanguageHelper::t('add_to_cart', 'Add to Cart') ?>
                        </button>
                        
                        <button class="btn btn-secondary wishlist-btn" data-product-id="<?= $product['id'] ?>">
                            <span class="heart-icon">❤</span> <?= LanguageHelper::t('add_to_wishlist', 'Add to Wishlist') ?>
                        </button>
                    </div>
                </div>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <strong><?= LanguageHelper::t('sku', 'SKU') ?>:</strong> PD-<?= str_pad($product['id'], 4, '0', STR_PAD_LEFT) ?>
                    </div>
                    <div class="meta-item">
                        <strong><?= LanguageHelper::t('category', 'Category') ?>:</strong> <?= $product['category_name'] ?>
                    </div>
                    <div class="meta-item">
                        <strong><?= LanguageHelper::t('vendor', 'Vendor') ?>:</strong> <?= htmlspecialchars($vendor_name) ?>
                    </div>
                    <?php if ($product['expiry_date']): ?>
                    <div class="meta-item">
                        <strong><?= LanguageHelper::t('fresh_until', 'Fresh Until') ?>:</strong> <?= date('F j, Y', strtotime($product['expiry_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="social-sharing">
                    <span><?= LanguageHelper::t('share_this_product', 'Share this product') ?>:</span>
                    <a href="#" class="social-share facebook" data-platform="facebook">Facebook</a>
                    <a href="#" class="social-share twitter" data-platform="twitter">Twitter</a>
                    <a href="#" class="social-share whatsapp" data-platform="whatsapp">WhatsApp</a>
                </div>
            </div>
        </div>
        
        <div class="product-tabs" data-aos="fade-up" data-aos-delay="400">
            <ul class="tabs-nav">
                <li class="active" data-tab="description"><?= LanguageHelper::t('description', 'Description') ?></li>
                <li data-tab="shipping"><?= LanguageHelper::t('shipping_returns', 'Shipping & Returns') ?></li>
                <?php if (!empty($product['vendor_id'])): ?>
                <li data-tab="vendor"><?= LanguageHelper::t('vendor_information', 'Vendor Information') ?></li>
                <?php endif; ?>
            </ul>
            
            <div class="tabs-content">
                <div class="tab-pane active" id="description">
                    <h3><?= LanguageHelper::t('product_details', 'Product Details') ?></h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    
                    <h3><?= LanguageHelper::t('key_features', 'Key Features') ?></h3>
                    <ul>
                        <li><?= LanguageHelper::t('fresh_from_farms_feature', 'Fresh from local farms in Banepa') ?></li>
                        <li><?= LanguageHelper::t('handpicked_quality', 'Handpicked for quality') ?></li>
                        <li><?= LanguageHelper::t('eco_friendly_packaging', 'Eco-friendly packaging') ?></li>
                        <li><?= LanguageHelper::t('same_day_delivery', 'Same-day delivery available') ?></li>
                    </ul>
                </div>
                
                <div class="tab-pane" id="shipping">
                    <h3><?= LanguageHelper::t('shipping_information', 'Shipping Information') ?></h3>
                    <p><?= LanguageHelper::t('shipping_description', 'We deliver to Banepa and surrounding areas. Delivery charges may apply based on distance.') ?></p>
                    
                    <h4><?= LanguageHelper::t('delivery_options', 'Delivery Options') ?></h4>
                    <ul>
                        <li><strong><?= LanguageHelper::t('standard_delivery', 'Standard Delivery') ?>:</strong> <?= LanguageHelper::t('within_24_hours', 'Within 24 hours') ?> - Rs. 50</li>
                        <li><strong><?= LanguageHelper::t('express_delivery', 'Express Delivery') ?>:</strong> <?= LanguageHelper::t('within_4_hours', 'Within 4 hours') ?> - Rs. 100</li>
                        <li><strong><?= LanguageHelper::t('free_delivery', 'Free Delivery') ?>:</strong> <?= LanguageHelper::t('on_orders_above', 'On orders above') ?> Rs. 1000</li>
                    </ul>
                    
                    <h4><?= LanguageHelper::t('return_policy', 'Return Policy') ?></h4>
                    <p><?= LanguageHelper::t('return_policy_description', 'We accept returns within 24 hours if the product is damaged or not as described. Please contact our customer service for return requests.') ?></p>
                </div>
                
                <?php if (!empty($product['vendor_id'])): ?>
                <div class="tab-pane" id="vendor">
                    <h3><?= LanguageHelper::t('vendor_information', 'Vendor Information') ?></h3>
                    <div class="vendor-details">
                        <?php if (!empty($product['vendor_profile_image'])): ?>
                        <div class="vendor-image">
                            <img src="<?= getImagePath($product['vendor_profile_image'], 'profile') ?>" 
                                 alt="<?= htmlspecialchars($vendor_name) ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="vendor-info">
                            <h4><?= htmlspecialchars($vendor_name) ?></h4>
                            <?php if (!empty($product['vendor_description'])): ?>
                            <p><?= nl2br(htmlspecialchars($product['vendor_description'])) ?></p>
                            <?php endif; ?>
                            
                            <div class="vendor-stats">
                                <div class="stat">
                                    <span class="stat-value">4.7</span>
                                    <span class="stat-label"><?= LanguageHelper::t('rating', 'Rating') ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value">125</span>
                                    <span class="stat-label"><?= LanguageHelper::t('products', 'Products') ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-value">98%</span>
                                    <span class="stat-label"><?= LanguageHelper::t('positive_reviews', 'Positive Reviews') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="related-products" data-aos="fade-up" data-aos-delay="500">
            <h2><?= LanguageHelper::t('you_may_also_like', 'You May Also Like') ?></h2>
            <div class="products-grid">
                <?php if (!empty($related_products)): ?>
                    <?php foreach ($related_products as $index => $related_product): 
                        $related_vendor_name = !empty($related_product['vendor_name']) ? $related_product['vendor_name'] : "Devitar Agro Farm";
                    ?>
                        <div class="product-card" data-category="<?= $related_product['category_id'] ?>" data-price="<?= $related_product['price'] ?>" data-aos="zoom-in" data-aos-delay="<?= ($index % 4) * 100 + 600 ?>">
                            <?php if (!empty($related_product['images'][0]['image_path'])): ?>
                                <img src="<?= getImagePath($related_product['images'][0]['image_path']) ?>" alt="<?= htmlspecialchars($related_product['name']) ?>" class="product-image">
                            <?php else: ?>
                                <img src="<?= getImagePath('default.jpg') ?>" alt="<?= htmlspecialchars($related_product['name']) ?>" class="product-image">
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <h3 class="product-title"><?= htmlspecialchars($related_product['name']) ?></h3>
                                <p class="product-description"><?= htmlspecialchars(substr($related_product['description'], 0, 100) . (strlen($related_product['description']) > 100 ? '...' : '')) ?></p>
                                <p class="product-price">Rs. <?= $related_product['price'] ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $related_product['unit'] ?></p>
                                
                                <div class="product-actions">
                                    <a href="<?= $pathConfig->url('product/' . $related_product['id']) ?>" class="btn btn-view"><?= LanguageHelper::t('view_details', 'View Details') ?></a>
                                    <button class="btn btn-add-cart add-to-cart" 
                                            data-id="<?= $related_product['id'] ?>" 
                                            data-name="<?= htmlspecialchars($related_product['name']) ?>" 
                                            data-price="<?= $related_product['price'] ?>"
                                            data-unit="<?= $related_product['unit'] ?>"
                                            data-image="<?= !empty($related_product['images'][0]['image_path']) ? getImagePath($related_product['images'][0]['image_path']) : getImagePath('default.jpg') ?>">
                                        <?= LanguageHelper::t('add_to_cart', 'Add to Cart') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-related-products" data-aos="fade-up">
                        <p><?= LanguageHelper::t('no_related_products', 'No related products found.') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Fixed Buttons -->
<div class="fixed-order-btn" data-aos="zoom-in" data-aos-delay="700">
    <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
        <input type="hidden" name="product_slug" value="marigold">
        <input type="hidden" name="quantity" value="2">
        <button type="submit" class="btn order-now-btn">Order Now 🌸</button>
    </form>
</div>

<div class="fixed-contact-btn" data-aos="zoom-in" data-aos-delay="800">
    <button class="btn contact-now-btn" id="contactBtn">Contact Us 📞</button>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image gallery
    const mainImage = document.getElementById('main-product-image');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (thumbnails.length > 0) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.getAttribute('data-image');
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
    
    // Quantity controls
    const quantityInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    
    minusBtn.addEventListener('click', function() {
        let currentValue = parseInt(quantityInput.value);
        if (currentValue > 1) quantityInput.value = currentValue - 1;
    });
    
    plusBtn.addEventListener('click', function() {
        let currentValue = parseInt(quantityInput.value);
        const maxValue = parseInt(quantityInput.getAttribute('max'));
        if (currentValue < maxValue) quantityInput.value = currentValue + 1;
    });
    
    // Tabs
    const tabNavs = document.querySelectorAll('.tabs-nav li');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabNavs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            tabNavs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            tabPanes.forEach(pane => {
                pane.classList.remove('active');
                if (pane.id === tabId) pane.classList.add('active');
            });
        });
    });
    
    // Add to cart
    const addToCartBtn = document.querySelector('.add-to-cart-btn');
    
    addToCartBtn.addEventListener('click', function() {
        if (this.disabled) return;
        
        const productId = this.getAttribute('data-id');
        const productName = this.getAttribute('data-name');
        const productPrice = this.getAttribute('data-price');
        const productUnit = this.getAttribute('data-unit');
        const productImage = this.getAttribute('data-image');
        const quantity = document.getElementById('quantity').value;
        
        fetch('<?= $pathConfig->url('cart/add') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_id: productId, product_name: productName, price: productPrice, unit: productUnit, image: productImage, quantity: quantity})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const originalText = this.textContent;
                this.textContent = "✓ <?= LanguageHelper::t('added_to_cart', 'Added to Cart') ?>";
                this.classList.add('added');
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('added');
                }, 2000);
                updateCartCount();
            } else alert('<?= LanguageHelper::t('failed_to_add_cart', 'Failed to add to cart') ?>: ' + data.message);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?= LanguageHelper::t('error_adding_cart', 'Error adding to cart') ?>');
        });
    });
    
    // Wishlist
    const wishlistBtn = document.querySelector('.wishlist-btn');
    wishlistBtn.addEventListener('click', function() {
        const productId = this.getAttribute('data-product-id');
        this.classList.toggle('active');
        this.innerHTML = this.classList.contains('active') ? 
            '<span class="heart-icon">❤</span> <?= LanguageHelper::t('added_to_wishlist', 'Added to Wishlist') ?>' : 
            '<span class="heart-icon">❤</span> <?= LanguageHelper::t('add_to_wishlist', 'Add to Wishlist') ?>';
    });
    
    // Social sharing
    const socialShareButtons = document.querySelectorAll('.social-share');
    socialShareButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const platform = this.getAttribute('data-platform');
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            
            let shareUrl;
            switch(platform) {
                case 'facebook': shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`; break;
                case 'twitter': shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`; break;
                case 'whatsapp': shareUrl = `https://wa.me/?text=${title}%20${url}`; break;
            }
            
            window.open(shareUrl, '_blank', 'width=600,height=400');
        });
    });
    
    // Related products add to cart
    const relatedAddToCartButtons = document.querySelectorAll('.related-products .add-to-cart');
    relatedAddToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const unit = this.getAttribute('data-unit');
            const image = this.getAttribute('data-image');
            
            fetch('<?= $pathConfig->url('cart/add') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({product_id: id, product_name: name, price: price, unit: unit, image: image, quantity: 1})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.textContent = "✓ <?= LanguageHelper::t('added', 'Added') ?>";
                    this.classList.add('added');
                    setTimeout(() => {
                        this.textContent = "<?= LanguageHelper::t('add_to_cart', 'Add to Cart') ?>";
                        this.classList.remove('added');
                    }, 2000);
                    updateCartCount();
                } else alert('<?= LanguageHelper::t('failed_to_add_cart', 'Failed to add to cart') ?>: ' + data.message);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?= LanguageHelper::t('error_adding_cart', 'Error adding to cart') ?>');
            });
        });
    });
    
    function updateCartCount() {
        fetch('<?= $pathConfig->url('cart/count') ?>')
            .then(response => response.json())
            .then(data => {
                const cartCountElements = document.querySelectorAll('.cart-count, .cart-badge');
                cartCountElements.forEach(element => {
                    element.textContent = data.count;
                    element.style.display = data.count > 0 ? 'inline' : 'none';
                });
            });
    }
    
    // Contact Modal
    const modal = document.getElementById("contactModal");
    const btn = document.getElementById("contactBtn");
    const span = document.getElementsByClassName("close")[0];
    
    btn.onclick = function() {
        modal.style.display = "block";
    }
    
    span.onclick = function() {
        modal.style.display = "none";
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    
    // Form handling for direct checkout
    const handleFormSubmit = (form, e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('processing', 'Processing...') ?>';
        
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?= LanguageHelper::t('error_occurred', 'An error occurred. Please try again.') ?>');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    };

    document.querySelectorAll('.direct-checkout-form').forEach(form => {
        form.addEventListener('submit', (e) => handleFormSubmit(form, e));
    });
    
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