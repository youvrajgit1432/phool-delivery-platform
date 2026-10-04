<?php
class ReviewController {
    private $db;
    private $reviewModel;
    private $productModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->reviewModel = new Review($db);
        $this->productModel = new Product($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public function index($language = 'en') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        
        $reviews = $this->reviewModel->readAll($page, $limit);
        
        foreach ($reviews as &$review) {
            $review['product_name'] = LanguageHelper::getLocalizedText([
                'name_en' => $review['product_name_en'],
                'name_ne' => $review['product_name_ne']
            ], 'name');
        }
        
        $stats = $this->reviewModel->getStats();
        $totalReviews = $this->reviewModel->getTotalCount();
        $totalPages = ceil($totalReviews / $limit);
        
        return [
            'reviews' => $reviews,
            'stats' => $stats,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_title' => LanguageHelper::t('customer_reviews', 'Customer Reviews', $language) . ' - Phool Delivery'
        ];
    }
    
    public function write($language = 'en') {
        error_log("ReviewController::write called");
        
        if (!isset($_SESSION['customer_id'])) {
            $_SESSION['redirect_url'] = $this->pathConfig->url('reviews/write');
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        // Get available products for review (products user has ordered but not reviewed)
        $available_products = $this->getAvailableProductsForReview($user_id, $language);
        
        // Get user's ordered products for suggestions
        $user_orders = $this->getUserOrdersForReview($user_id, $language);
        
        return [
            'available_products' => $available_products,
            'user_orders' => $user_orders,
            'page_title' => LanguageHelper::t('write_review', 'Write a Review', $language) . ' - Phool Delivery'
        ];
    }
    
    public function writeWithProduct($product_id, $language = 'en') {
        error_log("ReviewController::writeWithProduct called with product_id: " . $product_id);
        
        if (!isset($_SESSION['customer_id'])) {
            $_SESSION['redirect_url'] = $this->pathConfig->url('reviews/write/' . $product_id);
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        
        // Check if user has already reviewed this product
        if ($this->reviewModel->hasUserReviewed($user_id, $product_id)) {
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'message' => LanguageHelper::t('already_reviewed_product', 'You have already reviewed this product.')
            ];
            header('Location: ' . $this->pathConfig->url('product/' . $product_id));
            exit;
        }
        
        // Get product details
        $this->productModel->id = $product_id;
        if (!$this->productModel->readOne($language)) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => LanguageHelper::t('product_not_found', 'Product not found.')
            ];
            header('Location: ' . $this->pathConfig->url('reviews/write'));
            exit;
        }
        
        $product = $this->productModel->getLocalizedData($language);
        
        // Get available products for dropdown (excluding current product)
        $available_products = $this->getAvailableProductsForReview($user_id, $language, $product_id);
        
        return [
            'product' => $product,
            'available_products' => $available_products,
            'page_title' => LanguageHelper::t('write_review_for', 'Write Review for', $language) . ' ' . $product['name'] . ' - Phool Delivery'
        ];
    }
    
    public function submit() {
        error_log("ReviewController::submit called");
        
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['customer_id'])) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('login_to_review', 'Please login to submit a review')
            ]);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('invalid_input', 'Invalid input data')
            ]);
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $product_id = $input['product_id'] ?? null;
        $rating = $input['rating'] ?? null;
        $title = $input['title'] ?? null;
        $content = $input['content'] ?? null;
        
        if (!$product_id || !$rating || !$title || !$content) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('all_fields_required', 'All fields are required')
            ]);
            exit;
        }
        
        if ($rating < 1 || $rating > 5) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('rating_range_error', 'Rating must be between 1 and 5')
            ]);
            exit;
        }
        
        // Verify that the user has ordered this product
        if (!$this->hasUserOrderedProduct($user_id, $product_id)) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('cannot_review_unordered', 'You can only review products you have ordered')
            ]);
            exit;
        }
        
        if ($this->reviewModel->hasUserReviewed($user_id, $product_id)) {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('already_reviewed', 'You have already reviewed this product')
            ]);
            exit;
        }
        
        $this->reviewModel->user_id = $user_id;
        $this->reviewModel->product_id = $product_id;
        $this->reviewModel->rating = $rating;
        $this->reviewModel->title = $title;
        $this->reviewModel->content = $content;
        
        if ($this->reviewModel->create()) {
            echo json_encode([
                'success' => true, 
                'message' => LanguageHelper::t('review_submitted', 'Review submitted successfully! It will be visible after approval.')
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => LanguageHelper::t('review_submission_error', 'Failed to submit review')
            ]);
        }
        exit;
    }
    
    public function myReviews($language = 'en') {
        if (!isset($_SESSION['customer_id'])) {
            $_SESSION['redirect_url'] = $this->pathConfig->url('reviews/my-reviews');
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        
        $reviews = $this->reviewModel->readByUser($user_id, $page, $limit);
        
        foreach ($reviews as &$review) {
            $review['product_name'] = LanguageHelper::getLocalizedText([
                'name_en' => $review['product_name_en'],
                'name_ne' => $review['product_name_ne']
            ], 'name');
            
            $review['product_slug'] = LanguageHelper::getLocalizedSlug([
                'slug_en' => $review['slug_en'],
                'slug_ne' => $review['slug_ne']
            ]);
        }
        
        $totalReviews = $this->reviewModel->getTotalCount($user_id);
        $totalPages = ceil($totalReviews / $limit);
        
        return [
            'reviews' => $reviews,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_title' => LanguageHelper::t('my_reviews', 'My Reviews', $language) . ' - Phool Delivery'
        ];
    }
    
    private function getAvailableProductsForReview($user_id, $language = 'en', $exclude_product_id = null) {
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        
        $query = "SELECT DISTINCT p.id, p.{$name_field} as name, p.primary_image, p.price, p.unit
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 JOIN products p ON oi.product_id = p.id
                 LEFT JOIN reviews r ON p.id = r.product_id AND r.user_id = :user_id
                 WHERE o.customer_id = :user_id 
                 AND p.status = 'active'
                 AND r.id IS NULL";
        
        if ($exclude_product_id) {
            $query .= " AND p.id != :exclude_id";
        }
        
        $query .= " ORDER BY p.{$name_field}";
                 
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($exclude_product_id) {
            $stmt->bindParam(':exclude_id', $exclude_product_id);
        }
        
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process product images using PathConfig
        foreach ($products as &$product) {
            if (!empty($product['primary_image'])) {
                $product['image_url'] = $this->pathConfig->getImagePath($product['primary_image'], 'product');
            } else {
                $product['image_url'] = $this->pathConfig->getImagePath('default.jpg', 'product');
            }
        }
        
        return $products;
    }
    
    private function getUserOrdersForReview($user_id, $language = 'en') {
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        
        $query = "SELECT DISTINCT oi.product_id, p.{$name_field} as name, p.primary_image,
                         COUNT(oi.id) as order_count, MAX(o.created_at) as last_ordered
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 JOIN products p ON oi.product_id = p.id
                 LEFT JOIN reviews r ON oi.product_id = r.product_id AND r.user_id = :user_id
                 WHERE o.customer_id = :user_id 
                 AND r.id IS NULL
                 AND p.status = 'active'
                 GROUP BY oi.product_id
                 ORDER BY last_ordered DESC
                 LIMIT 10";
                 
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process product images using PathConfig
        foreach ($products as &$product) {
            if (!empty($product['primary_image'])) {
                $product['image_url'] = $this->pathConfig->getImagePath($product['primary_image'], 'product');
            } else {
                $product['image_url'] = $this->pathConfig->getImagePath('default.jpg', 'product');
            }
        }
        
        return $products;
    }
    
    private function hasUserOrderedProduct($user_id, $product_id) {
        $query = "SELECT oi.id 
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 WHERE o.customer_id = :user_id 
                 AND oi.product_id = :product_id
                 LIMIT 1";
                 
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    public function apiGetProductReviews($product_id, $language = 'en') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        
        $reviews = $this->reviewModel->readByProduct($product_id, $page, $limit);
        $stats = $this->reviewModel->getStats($product_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'reviews' => $reviews,
            'stats' => $stats,
            'current_page' => $page
        ]);
        exit;
    }
    
    public function apiGetProductsForReview() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['customer_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $language = $_GET['language'] ?? 'en';
        
        $products = $this->getAvailableProductsForReview($user_id, $language);
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
        exit;
    }
}
?>
 