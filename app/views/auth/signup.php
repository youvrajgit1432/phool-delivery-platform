<?php
// app/views/auth/signup.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('order_now_register_automatically', 'Order Now - Registration Automatic') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$error_message = $error_message ?? '';
$success_message = $success_message ?? '';

// SEO Meta Data
$meta_description = "Order fresh flowers from Phool Delivery Nepal. No manual registration required - your account will be created automatically when you place your first order. Fast delivery in Banepa, Bhaktapur, Kathmandu.";
$meta_keywords = "order flowers online, automatic registration, phool delivery, fresh flowers, marigold delivery, sayapatri, no signup needed, quick order";
?>

<!-- Schema.org Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Order Now - Automatic Registration | Phool Delivery",
  "description": "Order fresh flowers without manual registration. Your account is created automatically when you place your first order.",
  "url": "<?= $base_url ?>/signup"
}
</script>

<section class="order-section">
    <div class="container">
        <div class="order-container">
            <!-- SEO-optimized header -->
            <div class="order-header" data-aos="fade-up" data-aos-delay="100">
                <h1>Order Fresh Flowers - No Registration Needed! 🌼</h1>
                <p class="welcome-text">Place your order now and your account will be created automatically. Enjoy fresh flowers delivered to your doorstep in Banepa, Bhaktapur, and Kathmandu.</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error" data-aos="zoom-in" data-aos-delay="150">
                    <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" data-aos="zoom-in" data-aos-delay="150">
                    <i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Hurray Message -->
            <div class="hurray-message" data-aos="zoom-in" data-aos-delay="200">
                <div class="hurray-icon">🎉</div>
                <h2>Hurray! No Manual Signup Required</h2>
                <p>Simply place your order and we'll automatically create your account. Start shopping immediately!</p>
            </div>

            <!-- Featured Products for Quick Order -->
            <div class="featured-products-order" data-aos="fade-up" data-aos-delay="250">
                <h3>Popular Flowers - Ready for Delivery</h3>
                <div class="products-grid-order">
                    <?php
                    // Fetch only 4 popular products for quick ordering
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        $product_stmt = $db->prepare("
                            SELECT p.*, pi.image_path, pi.is_primary 
                            FROM products p 
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
                            WHERE p.status = 'active' 
                            ORDER BY p.created_at ASC LIMIT 4
                        ");
                        $product_stmt->execute();
                        $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($products as $index => $product) {
                            $product_name = LanguageHelper::getLocalizedText($product, 'name');
                            $product_slug = LanguageHelper::getLocalizedSlug($product);
                            
                            $primary_image = 'default.jpg';
                            if (!empty($product['image_path'])) {
                                $primary_image = $product['image_path'];
                            }
                            $image_path = $pathConfig->getImagePath($primary_image, 'product');
                    ?>
                    <div class="product-card-order" data-aos="zoom-in" data-aos-delay="<?= ($index * 100) + 300 ?>">
                        <div class="product-image-container">
                            <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($product_name) ?> - Fresh Flowers Delivery" class="product-image" loading="lazy">
                        </div>
                        <div class="product-info-order">
                            <h4 class="product-title"><?= htmlspecialchars($product_name) ?></h4>
                            <p class="product-price">Rs. <?= number_format($product['price'] ?? 0, 2) ?> per <?= $product['unit'] ?? 'piece' ?></p>
                            <?php if (isset($product['bulk_price']) && $product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                                <p class="product-bulk-price">Bulk price: Rs. <?= number_format($product['bulk_price'], 2) ?></p>
                            <?php endif; ?>
                            
                            <div class="order-actions">
                                <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
                                    <input type="hidden" name="product_slug" value="<?= $product_slug ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="auto_register" value="1">
                                    <button type="submit" class="btn btn-order-now" <?= (($product['stock_quantity'] ?? 0) <= 0) ? 'disabled' : '' ?>>
                                        <?= (($product['stock_quantity'] ?? 0) <= 0) ? 'Out of Stock' : 'Order Now 🌸' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                        }
                        
                        // Check if there are more products available
                        $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
                        $count_stmt->execute();
                        $total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        $has_more_products = $total_products > 4;
                        
                    } catch (Exception $e) {
                        echo '<div class="no-products"><p>Products loading soon...</p></div>';
                        $has_more_products = false;
                    }
                    ?>
                </div>
                
                <!-- Load More Button -->
                <?php if ($has_more_products): ?>
                <div class="load-more-container" data-aos="fade-up" data-aos-delay="400">
                    <a href="<?= $pathConfig->url('products') ?>" class="btn btn-load-more">
                        View More Flowers 🌺
                    </a>
                </div>
                <?php endif; ?>
            </div>

        

            <!-- Benefits Section -->
            <div class="benefits-section" data-aos="fade-up" data-aos-delay="650">
                <h3>Why Order With Us?</h3>
                <div class="benefits-grid">
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="700">
                        <div class="benefit-icon">🚀</div>
                        <h4>No Registration Needed</h4>
                        <p>Start ordering immediately without filling forms</p>
                    </div>
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="750">
                        <div class="benefit-icon">🌱</div>
                        <h4>Fresh From Farms</h4>
                        <p>Direct from local farmers to your doorstep</p>
                    </div>
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="800">
                        <div class="benefit-icon">🚚</div>
                        <h4>Fast Delivery</h4>
                        <p>Same-day delivery in Banepa, Bhaktapur, Kathmandu</p>
                    </div>
                    <div class="benefit-item" data-aos="fade-right" data-aos-delay="850">
                        <div class="benefit-icon">💰</div>
                        <h4>Best Prices</h4>
                        <p>Competitive prices with bulk discounts</p>
                    </div>
                </div>
            </div>

            <!-- Contact CTA -->
            <div class="contact-cta" data-aos="zoom-in" data-aos-delay="900">
                <h3>Need Help with Your Order?</h3>
                <p>Our team is ready to assist you with bulk orders or special requests</p>
                <div class="contact-buttons">
                    <a href="tel:9800000000" class="btn btn-call">
                        <i class="fas fa-phone"></i> Call: 9800000000
                    </a>
                    <a href="tel:9800000001" class="btn btn-call">
                        <i class="fas fa-phone"></i> Call: 9800000001
                    </a>
                </div>
            </div>

            <!-- Browse More Link -->
            <div class="browse-more" data-aos="fade-up" data-aos-delay="950">
                <p>Want to see more options?</p>
                <a href="<?= $pathConfig->url('products') ?>" class="btn btn-browse">
                    Browse All Flowers 🌺
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Fixed Order Button -->
<div class="fixed-order-btn" data-aos="zoom-in" data-aos-delay="1000">
    <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
        <input type="hidden" name="product_slug" value="marigold">
        <input type="hidden" name="quantity" value="2">
        <input type="hidden" name="auto_register" value="1">
        <button type="submit" class="btn order-now-btn">Order Now 🌸</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle direct checkout forms
    const handleFormSubmit = (form, e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing Order...';
        
        // Submit form data
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to checkout or success page
                window.location.href = data.redirect || '<?= $pathConfig->url('checkout') ?>';
            } else {
                // Show error message
                alert(data.message || 'An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    };

    // Add event listeners to all direct checkout forms
    document.querySelectorAll('.direct-checkout-form').forEach(form => {
        form.addEventListener('submit', (e) => handleFormSubmit(form, e));
    });

    // Add smooth scrolling for better UX
    const smoothScroll = (target) => {
        const element = document.querySelector(target);
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    };

    // Initialize AOS animations
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            mirror: false
        });
    }

    // Add intersection observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all product cards and benefit items
    document.querySelectorAll('.product-card-order, .benefit-item, .quick-order-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});
</script>

<style>
.order-section {
    padding: 40px 0;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
}

.order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.order-header {
    text-align: center;
    margin-bottom: 40px;
}

.order-header h1 {
    color: #2d5016;
    font-size: 2.5rem;
    margin-bottom: 15px;
    font-weight: 700;
}

.welcome-text {
    font-size: 1.2rem;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.hurray-message {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 40px;
    box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
}

.hurray-icon {
    font-size: 4rem;
    margin-bottom: 15px;
}

.hurray-message h2 {
    font-size: 1.8rem;
    margin-bottom: 10px;
    font-weight: 600;
}

.hurray-message p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.featured-products-order {
    margin-bottom: 50px;
}

.featured-products-order h3 {
    text-align: center;
    color: #2d5016;
    font-size: 2rem;
    margin-bottom: 30px;
    font-weight: 600;
}

.products-grid-order {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.product-card-order {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.product-card-order:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.product-image-container {
    height: 200px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card-order:hover .product-image {
    transform: scale(1.05);
}

.product-info-order {
    padding: 20px;
}

.product-title {
    font-size: 1.2rem;
    color: #2d5016;
    margin-bottom: 10px;
    font-weight: 600;
}

.product-price {
    font-size: 1.1rem;
    color: #e74c3c;
    font-weight: bold;
    margin-bottom: 5px;
}

.product-bulk-price {
    font-size: 0.9rem;
    color: #27ae60;
    font-weight: 600;
    margin-bottom: 15px;
}

.order-actions {
    margin-top: 15px;
}

.btn-order-now {
    width: 100%;
    background: linear-gradient(135deg, #FF6B01 0%, #FF8C42 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: none;
}

.btn-order-now:hover:not(:disabled) {
    background: linear-gradient(135deg, #E55A00 0%, #FF6B01 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 107, 1, 0.4);
}

.btn-order-now:disabled {
    background: #95a5a6;
    cursor: not-allowed;
}

.load-more-container {
    text-align: center;
    margin-top: 30px;
}

.btn-load-more {
    background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
    color: white;
    padding: 12px 30px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
    border: none;
    cursor: pointer;
}

.btn-load-more:hover {
    background: linear-gradient(135deg, #219653 0%, #27ae60 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
}

.quick-order-section {
    margin-bottom: 50px;
}

.quick-order-section h3 {
    text-align: center;
    color: #2d5016;
    font-size: 2rem;
    margin-bottom: 30px;
    font-weight: 600;
}

.quick-order-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.quick-order-card {
    background: white;
    padding: 30px 20px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.quick-order-card:hover {
    transform: translateY(-5px);
}

.order-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.quick-order-card h4 {
    color: #2d5016;
    font-size: 1.3rem;
    margin-bottom: 10px;
    font-weight: 600;
}

.quick-order-card p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

.btn-quick-order {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-quick-order:hover {
    background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
}

.benefits-section {
    margin-bottom: 50px;
}

.benefits-section h3 {
    text-align: center;
    color: #2d5016;
    font-size: 2rem;
    margin-bottom: 30px;
    font-weight: 600;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

.benefit-item {
    text-align: center;
    padding: 25px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.benefit-item:hover {
    transform: translateY(-3px);
}

.benefit-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
}

.benefit-item h4 {
    color: #2d5016;
    font-size: 1.2rem;
    margin-bottom: 10px;
    font-weight: 600;
}

.benefit-item p {
    color: #666;
    line-height: 1.5;
    margin: 0;
}

.contact-cta {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 40px;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.contact-cta h3 {
    font-size: 1.8rem;
    margin-bottom: 15px;
    font-weight: 600;
}

.contact-cta p {
    font-size: 1.1rem;
    margin-bottom: 25px;
    opacity: 0.9;
}

.contact-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-call {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-call:hover {
    background: white;
    color: #667eea;
    transform: translateY(-2px);
}

.browse-more {
    text-align: center;
    padding: 30px 0;
}

.browse-more p {
    font-size: 1.1rem;
    color: #666;
    margin-bottom: 15px;
}

.btn-browse {
    background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
    color: white;
    padding: 12px 30px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-browse:hover {
    background: linear-gradient(135deg, #219653 0%, #27ae60 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
}

.fixed-order-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.order-now-btn {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
}

.order-now-btn:hover {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(231, 76, 60, 0.6);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.no-products {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    grid-column: 1 / -1;
}

/* MODIFIED: Hide quick orders on mobile */
.desktop-only {
    display: block;
}

/* MODIFIED: Mobile-specific grid layout for horizontal cards */
@media (max-width: 768px) {
    .order-header h1 {
        font-size: 2rem;
    }
    
    .welcome-text {
        font-size: 1.1rem;
    }
    
    /* MODIFIED: Hide quick order section on mobile */
    .desktop-only {
        display: none !important;
    }
    
    /* MODIFIED: Show 2 cards horizontally in mobile with proper spacing */
    .products-grid-order {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        overflow-x: auto;
        padding-bottom: 10px;
    }
    
    .product-card-order {
        margin-bottom: 0;
        min-width: 0;
        flex-shrink: 0;
    }
    
    .product-image-container {
        height: 150px;
    }
    
    .product-info-order {
        padding: 15px;
    }
    
    .product-title {
        font-size: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .product-price {
        font-size: 1rem;
    }
    
    .btn-order-now {
        padding: 10px 15px;
        font-size: 0.9rem;
    }
    
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .benefit-item {
        padding: 20px 15px;
    }
    
    .benefit-icon {
        font-size: 2rem;
    }
    
    .benefit-item h4 {
        font-size: 1.1rem;
    }
    
    .benefit-item p {
        font-size: 0.9rem;
    }
    
    .contact-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-call {
        width: 200px;
        text-align: center;
    }
    
    .fixed-order-btn {
        bottom: 20px;
        right: 20px;
        left: 20px;
    }
    
    .order-now-btn {
        width: 100%;
        border-radius: 8px;
    }
    
    .load-more-container {
        margin-top: 20px;
    }
    
    .btn-load-more {
        padding: 10px 25px;
        font-size: 0.9rem;
    }
}

/* MODIFIED: Additional mobile-specific fixes for 2 cards layout */
@media (max-width: 480px) {
    .order-container {
        padding: 0 15px;
    }
    
    .order-header h1 {
        font-size: 1.8rem;
    }
    
    .hurray-message {
        padding: 20px 15px;
    }
    
    .hurray-message h2 {
        font-size: 1.5rem;
    }
    
    .featured-products-order h3,
    .quick-order-section h3,
    .benefits-section h3 {
        font-size: 1.6rem;
    }
    
    /* MODIFIED: Ensure proper spacing and sizing for 2 cards in very small screens */
    .products-grid-order {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        display: grid;
        overflow-x: visible;
    }
    
    .product-card-order {
        width: 100%;
        margin: 0;
    }
    
    .product-image-container {
        height: 120px;
    }
    
    .product-info-order {
        padding: 12px;
    }
    
    .product-title {
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .product-price {
        font-size: 0.9rem;
    }
    
    .btn-order-now {
        padding: 8px 12px;
        font-size: 0.8rem;
    }
    
    /* Ensure benefits grid shows 2 items properly */
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .benefit-item {
        padding: 15px 10px;
    }
    
    .benefit-icon {
        font-size: 1.8rem;
    }
    
    .benefit-item h4 {
        font-size: 1rem;
    }
    
    .benefit-item p {
        font-size: 0.8rem;
    }
}

/* MODIFIED: Additional responsive adjustments for tablet */
@media (min-width: 769px) and (max-width: 1024px) {
    .products-grid-order {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* MODIFIED: Bootstrap-like utility classes for better mobile handling */
.mobile-two-columns {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.mobile-scroll-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 10px;
}

.mobile-scroll-horizontal .product-card-order {
    flex: 0 0 calc(50% - 8px);
    min-width: calc(50% - 8px);
}
</style>