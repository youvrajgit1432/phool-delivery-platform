<?php
//app/views/review/index.php
if (!isset($reviews) || !isset($stats)) {
    header('Location: /');
    exit;
}

LanguageHelper::initialize();
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$assets_path = $base_url . '/phool-delivery-platform/public_html/assets';
?>

<section class="reviews-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= LanguageHelper::t('home', 'Home') ?></a> &gt;
            <span><?= LanguageHelper::t('customer_reviews', 'Customer Reviews') ?></span>
        </nav>

        <div class="reviews-header">
            <h1><?= LanguageHelper::t('customer_reviews', 'Customer Reviews') ?></h1>
            
            <div class="reviews-stats">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_reviews'] ?></div>
                    <div class="stat-label"><?= LanguageHelper::t('total_reviews', 'Total Reviews') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($stats['average_rating'], 1) ?></div>
                    <div class="stat-label"><?= LanguageHelper::t('average_rating', 'Average Rating') ?></div>
                </div>
            </div>
            
            <div class="reviews-actions">
                <a href="/reviews/write" class="btn btn-primary">
                    <?= LanguageHelper::t('write_review', 'Write a Review') ?>
                </a>
                <?php if (isset($_SESSION['customer_id'])): ?>
                <a href="/reviews/my-reviews" class="btn btn-outline">
                    <?= LanguageHelper::t('my_reviews', 'My Reviews') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="reviews-list">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <?php if (!empty($review['profile_image'])): ?>
                                <img src="<?= $base_url ?>/phool-delivery-platform/admin/storage/uploads/profiles/<?= $review['profile_image'] ?>" 
                                     alt="<?= htmlspecialchars($review['customer_name']) ?>" 
                                     class="reviewer-avatar">
                                <?php else: ?>
                                <div class="reviewer-avatar placeholder">
                                    <?= substr($review['customer_name'], 0, 1) ?>
                                </div>
                                <?php endif; ?>
                                <div class="reviewer-details">
                                    <div class="reviewer-name"><?= htmlspecialchars($review['customer_name']) ?></div>
                                    <div class="review-date"><?= date('F j, Y', strtotime($review['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= $review['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="review-content">
                            <h3 class="review-title"><?= htmlspecialchars($review['title']) ?></h3>
                            <p class="review-text"><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                        </div>
                        
                        <?php if (!empty($review['product_name'])): ?>
                        <div class="review-product">
                            <span><?= LanguageHelper::t('for_product', 'For') ?>:</span>
                            <a href="/product/<?= $review['product_id'] ?>" class="product-link">
                                <?= htmlspecialchars($review['product_name']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-reviews">
                    <p><?= LanguageHelper::t('no_reviews_found', 'No reviews found yet.') ?></p>
                    <a href="/reviews/write" class="btn btn-primary">
                        <?= LanguageHelper::t('be_first_to_review', 'Be the first to write a review') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="/reviews?page=<?= $current_page - 1 ?>" class="page-link">
                    <?= LanguageHelper::t('previous', 'Previous') ?>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="/reviews?page=<?= $i ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="/reviews?page=<?= $current_page + 1 ?>" class="page-link">
                    <?= LanguageHelper::t('next', 'Next') ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.reviews-page {
    padding: 2rem 0;
}

.reviews-header {
    text-align: center;
    margin-bottom: 3rem;
}

.reviews-stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin: 2rem 0;
}

.stat-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    min-width: 150px;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
}

.stat-label {
    color: #6c757d;
    margin-top: 0.5rem;
}

.reviews-actions {
    margin-top: 1.5rem;
}

.review-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.reviewer-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.reviewer-avatar.placeholder {
    background: #007bff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.reviewer-name {
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.review-date {
    color: #6c757d;
    font-size: 0.9rem;
}

.review-rating {
    color: #ffc107;
    font-size: 1.2rem;
}

.review-title {
    margin: 0 0 1rem 0;
    color: #333;
}

.review-text {
    line-height: 1.6;
    color: #555;
}

.review-product {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
    color: #6c757d;
}

.product-link {
    color: #007bff;
    text-decoration: none;
}

.product-link:hover {
    text-decoration: underline;
}

.no-reviews {
    text-align: center;
    padding: 3rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-link {
    padding: 0.5rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #007bff;
}

.page-link.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.page-link:hover {
    background: #e9ecef;
}
</style>