<?php
// app/views/account/wishlist.php

$pathConfig = PathConfig::getInstance();
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<div class="account-container">
    <div class="account-header">
        <h2>My Wishlist</h2>
        <p>Your saved items for later</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="wishlist-content">
        <?php if (!empty($wishlist)): ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlist as $item): ?>
                    <div class="wishlist-item">
                        <div class="wishlist-image">
                            <img src="<?php echo $pathConfig->getImagePath($item['image'] ?? 'default.jpg', 'product'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 onerror="this.src='<?php echo $pathConfig->get('assets'); ?>/img/products/placeholder.jpg'">
                        </div>
                        
                        <div class="wishlist-details">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p class="price">Rs. <?php echo number_format($item['price'], 2); ?></p>
                            <p class="stock <?php echo ($item['stock_status'] === 'in_stock') ? 'in-stock' : 'out-of-stock'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $item['stock_status'])); ?>
                            </p>
                        </div>

                        <div class="wishlist-actions">
                            <a href="<?php echo $pathConfig->url('product?id=' . $item['product_id']); ?>" class="btn btn-sm btn-primary">
                                View Product
                            </a>
                            <a href="<?php echo $pathConfig->url('account/wishlist?action=remove&product_id=' . $item['product_id']); ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Remove from wishlist?')">
                                Remove
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <h3>Your wishlist is empty</h3>
                <p>Start saving your favorite items</p>
                <a href="<?php echo $pathConfig->url('products'); ?>" class="btn btn-primary">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>
</div>