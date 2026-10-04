<?php
LanguageHelper::initialize();
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$assets_path = $base_url . '/phool-delivery-platform/public_html/assets';
?>

<section class="write-review-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= LanguageHelper::t('home', 'Home') ?></a> &gt;
            <a href="/reviews"><?= LanguageHelper::t('reviews', 'Reviews') ?></a> &gt;
            <span><?= LanguageHelper::t('write_review', 'Write a Review') ?></span>
        </nav>

        <div class="write-review-container">
            <h1><?= LanguageHelper::t('write_review', 'Write a Review') ?></h1>
            
            <?php if (isset($product)): ?>
            <div class="product-to-review">
                <h3><?= LanguageHelper::t('reviewing_product', 'Reviewing Product') ?>:</h3>
                <div class="product-card">
                    <?php if (!empty($product['primary_image'])): ?>
                    <img src="<?= $base_url ?>/phool-delivery-platform/admin/storage/uploads/products/<?= $product['primary_image'] ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-image">
                    <?php else: ?>
                    <img src="<?= $assets_path ?>/img/products/placeholder.jpg" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-image">
                    <?php endif; ?>
                    <div class="product-info">
                        <h4><?= htmlspecialchars($product['name']) ?></h4>
                        <p class="product-price">Rs. <?= number_format($product['price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $product['unit'] ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form id="review-form" class="review-form">
                <!-- Product Selection Dropdown -->
                <div class="form-group" id="product-selection-group" <?= isset($product) ? 'style="display: none;"' : '' ?>>
                    <label for="product_id"><?= LanguageHelper::t('select_product', 'Select Product to Review') ?> *</label>
                    <select id="product_id" name="product_id" class="form-control" required>
                        <option value=""><?= LanguageHelper::t('choose_product', '-- Choose a Product --') ?></option>
                        <?php if (!empty($available_products)): ?>
                            <?php foreach ($available_products as $available_product): ?>
                                <option value="<?= $available_product['id'] ?>" 
                                        data-image="<?= !empty($available_product['primary_image']) ? $base_url . '/phool-delivery-platform/admin/storage/uploads/products/' . $available_product['primary_image'] : $assets_path . '/img/products/placeholder.jpg' ?>"
                                        data-price="Rs. <?= number_format($available_product['price'], 2) ?> <?= LanguageHelper::t('per_unit', 'per') ?> <?= $available_product['unit'] ?>">
                                    <?= htmlspecialchars($available_product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="selected-product-preview" id="selected-product-preview" style="display: none;">
                        <div class="product-card">
                            <img id="preview-image" src="" alt="" class="product-image">
                            <div class="product-info">
                                <h4 id="preview-name"></h4>
                                <p class="product-price" id="preview-price"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="product_id_final" id="product_id_final" value="<?= $product['id'] ?? '' ?>">
                
                <div class="form-group">
                    <label for="rating"><?= LanguageHelper::t('your_rating', 'Your Rating') ?> *</label>
                    <div class="star-rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-labels">
                        <span><?= LanguageHelper::t('poor', 'Poor') ?></span>
                        <span><?= LanguageHelper::t('excellent', 'Excellent') ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review_title"><?= LanguageHelper::t('review_title', 'Review Title') ?> *</label>
                    <input type="text" id="review_title" name="title" class="form-control" 
                           placeholder="<?= LanguageHelper::t('title_placeholder', 'Give your review a title') ?>" 
                           required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="review_content"><?= LanguageHelper::t('your_review', 'Your Review') ?> *</label>
                    <textarea id="review_content" name="content" class="form-control" 
                              rows="8" 
                              placeholder="<?= LanguageHelper::t('review_placeholder', 'Share your experience with this product...') ?>" 
                              required></textarea>
                    <div class="char-count">
                        <span id="char-count">0</span> <?= LanguageHelper::t('characters', 'characters') ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= LanguageHelper::t('submit_review', 'Submit Review') ?>
                    </button>
                    <a href="<?= $product ? '/product/' . $product['id'] : '/reviews' ?>" class="btn btn-outline">
                        <?= LanguageHelper::t('cancel', 'Cancel') ?>
                    </a>
                </div>
            </form>

            <?php if (!empty($user_orders) && !isset($product)): ?>
            <div class="suggested-products">
                <h3><?= LanguageHelper::t('products_you_ordered', 'Products You\'ve Ordered') ?></h3>
                <p><?= LanguageHelper::t('suggest_review', 'You can review products you\'ve previously ordered:') ?></p>
                
                <div class="suggested-products-grid">
                    <?php foreach ($user_orders as $order_product): ?>
                        <div class="suggested-product">
                            <?php if (!empty($order_product['primary_image'])): ?>
                            <img src="<?= $base_url ?>/phool-delivery-platform/admin/storage/uploads/products/<?= $order_product['primary_image'] ?>" 
                                 alt="<?= htmlspecialchars($order_product['name']) ?>" 
                                 class="product-image">
                            <?php else: ?>
                            <img src="<?= $assets_path ?>/img/products/placeholder.jpg" 
                                 alt="<?= htmlspecialchars($order_product['name']) ?>" 
                                 class="product-image">
                            <?php endif; ?>
                            <div class="product-info">
                                <h4><?= htmlspecialchars($order_product['name']) ?></h4>
                                <a href="/reviews/write/<?= $order_product['product_id'] ?>" class="btn btn-sm btn-primary">
                                    <?= LanguageHelper::t('write_review', 'Write Review') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('review-form');
    const reviewContent = document.getElementById('review_content');
    const charCount = document.getElementById('char-count');
    const productSelect = document.getElementById('product_id');
    const productIdFinal = document.getElementById('product_id_final');
    const selectedProductPreview = document.getElementById('selected-product-preview');
    const previewImage = document.getElementById('preview-image');
    const previewName = document.getElementById('preview-name');
    const previewPrice = document.getElementById('preview-price');
    
    // Character count for review content
    reviewContent.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    
    // Star rating interaction
    const stars = document.querySelectorAll('.star-rating-input input');
    stars.forEach(star => {
        star.addEventListener('change', function() {
            const rating = this.value;
            // Update visual state if needed
        });
    });
    
    // Product selection handler
    if (productSelect) {
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                // Show product preview
                previewImage.src = selectedOption.getAttribute('data-image');
                previewName.textContent = selectedOption.text;
                previewPrice.textContent = selectedOption.getAttribute('data-price');
                productIdFinal.value = selectedOption.value;
                selectedProductPreview.style.display = 'block';
            } else {
                // Hide product preview
                selectedProductPreview.style.display = 'none';
                productIdFinal.value = '';
            }
        });
    }
    
    // Form submission
    reviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const reviewData = {
            product_id: productIdFinal.value || formData.get('product_id'),
            rating: formData.get('rating'),
            title: formData.get('title'),
            content: formData.get('content')
        };
        
        // Validate product selection
        if (!reviewData.product_id) {
            alert('<?= LanguageHelper::t('please_select_product', 'Please select a product to review') ?>');
            return;
        }
        
        // Validate rating
        if (!reviewData.rating) {
            alert('<?= LanguageHelper::t('please_select_rating', 'Please select a rating') ?>');
            return;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('submitting', 'Submitting...') ?>';
        
        fetch('/reviews/submit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(reviewData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = reviewData.product_id ? '/product/' + reviewData.product_id : '/reviews/my-reviews';
            } else {
                alert(data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?= LanguageHelper::t('error_submitting_review', 'Error submitting review') ?>');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
});
</script>