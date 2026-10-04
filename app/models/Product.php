<?php
// app/models/Product.php
class Product {
    private $conn;
    private $table_name = "products";
    
    public $id;
    public $name_en;
    public $name_ne;
    public $slug;
    public $slug_en;
    public $slug_ne;
    public $description_en;
    public $description_ne;
    public $category_id;
    public $price;
    public $bulk_price;
    public $event_price;
    public $unit;
    public $stock_quantity;
    public $min_stock_alert;
    public $expiry_date;
    public $images;
    public $gallery_images;
    public $status;
    public $created_at;
    public $updated_at;
    public $category_name_en;
    public $category_name_ne;
    public $primary_image;
    public $all_images = [];
    public $reviews = [];
    public $rating_stats = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    // Security: Input validation helper
    private function validateInput($input, $type = 'string', $max_length = 255) {
        if ($input === null) return null;
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'slug':
                return preg_match('/^[a-z0-9-]+$/', $input) ? $input : null;
            case 'string':
            default:
                $input = trim($input);
                $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
                return strlen($input) <= $max_length ? $input : substr($input, 0, $max_length);
        }
    }

    // Security: Rate limiting helper (simple version)
    private function checkRateLimit($identifier, $max_requests = 10, $time_window = 60) {
        $key = "rate_limit_{$identifier}";
        $current_time = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $current_time
            ];
            return true;
        }
        
        $rate_data = $_SESSION[$key];
        
        if ($current_time - $rate_data['start_time'] > $time_window) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $current_time
            ];
            return true;
        }
        
        if ($rate_data['count'] >= $max_requests) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }

    // Read products with language support (supports optional limit and light file cache)
    public function read($language = 'en', $limit = 0) {
        // Security: Validate language input
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        // Simple file cache to avoid repeated heavy queries (TTL seconds)
        $cacheTtl = 30; // seconds
        $cacheKey = 'product_read_' . md5($language . '_' . intval($limit));
        $cacheDir = realpath(__DIR__ . '/../../storage/cache');
        if ($cacheDir === false) {
            $cacheDir = __DIR__ . '/../../storage/cache';
        }
        $cacheFile = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cacheKey . '.cache';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false) {
                $data = @unserialize($cached);
                if ($data !== false && is_array($data)) {
                    return $data;
                }
            }
        }

        $limitSql = '';
        if ($limit && is_int($limit) && $limit > 0) {
            $limitSql = ' LIMIT ' . intval($limit);
        }

        $query = "SELECT p.id, p.{$name_field} as name, p.{$description_field} as description, 
                         p.price, p.bulk_price, p.event_price, p.unit, p.stock_quantity, 
                         p.min_stock_alert, p.expiry_date, p.status, p.category_id,
                         {$category_name_field} as category_name
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' 
                 ORDER BY p.created_at DESC" . $limitSql;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Batch-load images for all products (avoid N+1 queries)
        if (!empty($products)) {
            $ids = array_column($products, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $imgQuery = "SELECT product_id, image_path, is_primary FROM product_images WHERE product_id IN ($placeholders) ORDER BY is_primary DESC, created_at ASC";
            $imgStmt = $this->conn->prepare($imgQuery);
            foreach ($ids as $k => $id) {
                // bindParam uses references, so use bindValue via execute with array
            }
            $imgStmt->execute($ids);
            $all_images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

            $images_by = [];
            foreach ($all_images as $img) {
                $pid = $img['product_id'];
                if (!isset($images_by[$pid])) $images_by[$pid] = [];
                $images_by[$pid][] = $img;
            }

            foreach ($products as &$product) {
                $product['images'] = $images_by[$product['id']] ?? [];
            }
            unset($product);
        }

        // Save to cache (best-effort)
        try {
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            @file_put_contents($cacheFile, serialize($products));
        } catch (Exception $e) {
            // ignore cache write failures
        }

        return $products;
    }

    // Read single product with language support
    public function readOne($language = 'en') {
        // Security: Validate language input
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        // Security: Validate product ID
        $this->id = $this->validateInput($this->id, 'int');
        if (!$this->id) {
            return false;
        }
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        $query = "SELECT p.*, {$category_name_field} as category_name 
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.id = ? LIMIT 0,1";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->name_en = $row['name_en'];
            $this->name_ne = $row['name_ne'];
            $this->slug = $row['slug'];
            $this->slug_en = $row['slug_en'];
            $this->slug_ne = $row['slug_ne'];
            $this->description_en = $row['description_en'];
            $this->description_ne = $row['description_ne'];
            $this->category_id = $row['category_id'];
            $this->price = (float)$row['price'];
            $this->bulk_price = (float)$row['bulk_price'];
            $this->event_price = (float)$row['event_price'];
            $this->unit = $row['unit'];
            $this->stock_quantity = (int)$row['stock_quantity'];
            $this->min_stock_alert = (int)$row['min_stock_alert'];
            $this->expiry_date = $row['expiry_date'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->category_name_en = $row['category_name_en'] ?? '';
            $this->category_name_ne = $row['category_name_ne'] ?? '';
            
            // Get product images
            $this->all_images = $this->getProductImages($this->id);
            $this->primary_image = $this->getPrimaryImage($this->id);
            
            // Get reviews and rating stats
            $this->reviews = $this->getProductReviews($this->id);
            $this->rating_stats = $this->getRatingStats($this->id);
            
            return true;
        }
        return false;
    }

    // Get localized product data
    public function getLocalizedData($language = 'en') {
        // Security: Validate language input
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        return [
            'id' => $this->id,
            'name' => $language === 'ne' ? $this->name_ne : $this->name_en,
            'description' => $language === 'ne' ? $this->description_ne : $this->description_en,
            'category_name' => $language === 'ne' ? $this->category_name_ne : $this->category_name_en,
            'price' => $this->price,
            'bulk_price' => $this->bulk_price,
            'event_price' => $this->event_price,
            'unit' => $this->unit,
            'stock_quantity' => $this->stock_quantity,
            'min_stock_alert' => $this->min_stock_alert,
            'expiry_date' => $this->expiry_date,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'primary_image' => $this->primary_image,
            'all_images' => $this->all_images,
            'reviews' => $this->reviews,
            'rating_stats' => $this->rating_stats
        ];
    }

    // Get product by slug with language support
    public function getProductBySlug($slug, $language = 'en') {
        // Security: Validate inputs
        $slug = $this->validateInput($slug, 'slug');
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        if (!$slug) {
            return null;
        }
        
        $slug_field = $language === 'ne' ? 'slug_ne' : 'slug_en';
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        $query = "SELECT p.*, {$category_name_field} as category_name 
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.{$slug_field} = ? AND p.status = 'active' 
                 LIMIT 1";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $slug);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Get all images for the product
            $product['images'] = $this->getProductImages($product['id']);
            // Get reviews and rating stats
            $product['reviews'] = $this->getProductReviews($product['id']);
            $product['rating_stats'] = $this->getRatingStats($product['id']);
            
            // Add localized name and description
            $product['name'] = $product[$name_field];
            $product['description'] = $product[$description_field];
        }
        
        return $product;
    }

    // Get product images
    public function getProductImages($product_id) {
        // Security: Validate product ID
        $product_id = $this->validateInput($product_id, 'int');
        if (!$product_id) {
            return [];
        }
        
        $query = "SELECT image_path, is_primary 
                 FROM product_images 
                 WHERE product_id = ? 
                 ORDER BY is_primary DESC, created_at ASC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $product_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get primary image
    public function getPrimaryImage($product_id) {
        // Security: Validate product ID
        $product_id = $this->validateInput($product_id, 'int');
        if (!$product_id) {
            return null;
        }
        
        $query = "SELECT image_path 
                 FROM product_images 
                 WHERE product_id = ? AND is_primary = 1 
                 LIMIT 1";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $product_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['image_path'] : null;
    }

    // Get product reviews
    public function getProductReviews($product_id, $limit = 10) {
        // Security: Validate inputs
        $product_id = $this->validateInput($product_id, 'int');
        $limit = $this->validateInput($limit, 'int');
        
        if (!$product_id || !$limit || $limit > 50) { // Limit max reviews to 50
            $limit = 10;
        }
        
        $query = "SELECT r.*, c.name as user_name 
                 FROM reviews r 
                 LEFT JOIN customers c ON r.user_id = c.id 
                 WHERE r.product_id = ? AND r.status = 'approved' 
                 ORDER BY r.created_at DESC 
                 LIMIT ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $product_id);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get rating statistics
    public function getRatingStats($product_id) {
        // Security: Validate product ID
        $product_id = $this->validateInput($product_id, 'int');
        if (!$product_id) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [0, 0, 0, 0, 0]
            ];
        }
        
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
                 FROM reviews 
                 WHERE product_id = ? AND status = 'approved'";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $product_id);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [0, 0, 0, 0, 0]
            ];
        }
        
        return [
            'total_reviews' => (int)$stats['total_reviews'],
            'average_rating' => $stats['average_rating'] ? round((float)$stats['average_rating'], 1) : 0,
            'rating_distribution' => [
                (int)$stats['rating_5'],
                (int)$stats['rating_4'],
                (int)$stats['rating_3'],
                (int)$stats['rating_2'],
                (int)$stats['rating_1']
            ]
        ];
    }

    // Add review with security enhancements
    public function addReview($user_id, $product_id, $rating, $title, $content) {
        // Security: Rate limiting per user
        if (!$this->checkRateLimit("review_{$user_id}", 5, 300)) { // 5 reviews per 5 minutes
            return ['success' => false, 'message' => 'Too many review submissions. Please try again later.'];
        }
        
        // Security: Validate all inputs
        $user_id = $this->validateInput($user_id, 'int');
        $product_id = $this->validateInput($product_id, 'int');
        $rating = $this->validateInput($rating, 'int');
        $title = $this->validateInput($title, 'string', 200);
        $content = $this->validateInput($content, 'string', 1000);
        
        // Security: Validate rating range
        if (!$user_id || !$product_id || !$rating || $rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Invalid input data'];
        }
        
        // Security: Check if product exists and is active
        $productCheck = $this->conn->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
        $productCheck->bindParam(1, $product_id);
        $productCheck->execute();
        
        if (!$productCheck->fetch()) {
            return ['success' => false, 'message' => 'Product not found or unavailable'];
        }

        // Check if user already reviewed this product
        $checkQuery = "SELECT id FROM reviews WHERE user_id = ? AND product_id = ?";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(1, $user_id);
        $checkStmt->bindParam(2, $product_id);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'You have already reviewed this product'];
        }
        
        $query = "INSERT INTO reviews (user_id, product_id, rating, title, content, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'approved', NOW())";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $product_id);
        $stmt->bindParam(3, $rating);
        $stmt->bindParam(4, $title);
        $stmt->bindParam(5, $content);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Review submitted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to submit review'];
        }
    }

    // Read products by category with language support
    public function readByCategory($category_id, $language = 'en') {
        // Security: Validate inputs
        $category_id = $this->validateInput($category_id, 'int');
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        if (!$category_id) {
            return [];
        }
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        $query = "SELECT p.id, p.{$name_field} as name, p.{$description_field} as description, 
                         p.price, p.bulk_price, p.event_price, p.unit, p.stock_quantity, 
                         p.category_id, {$category_name_field} as category_name
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.category_id = ? AND p.status = 'active' 
                 ORDER BY p.created_at DESC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $category_id);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get images for each product
        foreach ($products as &$product) {
            $product['images'] = $this->getProductImages($product['id']);
        }
        
        return $products;
    }

    // Get featured products with language support
    public function getFeaturedProducts($limit = 8, $language = 'en') {
        // Security: Validate inputs
        $limit = $this->validateInput($limit, 'int');
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        if (!$limit || $limit > 50) { // Limit max products to 50
            $limit = 8;
        }
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        $query = "SELECT p.id, p.{$name_field} as name, p.{$description_field} as description, 
                         p.price, p.bulk_price, p.event_price, p.unit, p.stock_quantity, 
                         p.category_id, {$category_name_field} as category_name
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' 
                 ORDER BY p.created_at DESC 
                 LIMIT ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get images for each product
        foreach ($products as &$product) {
            $product['images'] = $this->getProductImages($product['id']);
        }
        
        return $products;
    }

    // Get popular products with language support
    public function getPopularProducts($limit = 6, $language = 'en') {
        // Security: Validate inputs
        $limit = $this->validateInput($limit, 'int');
        $language = $this->validateInput($language, 'string', 2);
        $language = in_array($language, ['en', 'ne']) ? $language : 'en';
        
        if (!$limit || $limit > 50) { // Limit max products to 50
            $limit = 6;
        }
        
        $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
        $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
        $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
        
        $query = "SELECT p.id, p.{$name_field} as name, p.{$description_field} as description, 
                         p.price, p.bulk_price, p.event_price, p.unit, p.stock_quantity, 
                         p.category_id, {$category_name_field} as category_name,
                         COUNT(DISTINCT r.id) as review_count,
                         COUNT(DISTINCT oi.id) as order_count
                 FROM " . $this->table_name . " p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 LEFT JOIN reviews r ON p.id = r.product_id 
                 LEFT JOIN order_items oi ON p.id = oi.product_id 
                 WHERE p.status = 'active' 
                 GROUP BY p.id 
                 ORDER BY (review_count * 2 + order_count) DESC, p.created_at DESC 
                 LIMIT ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get images for each product
        foreach ($products as &$product) {
            $product['images'] = $this->getProductImages($product['id']);
        }
        
        return $products;
    }

    // Update product stock with transaction safety
    public function updateStock($quantity) {
        // Security: Validate inputs
        $this->id = $this->validateInput($this->id, 'int');
        $quantity = $this->validateInput($quantity, 'int');
        
        if (!$this->id || !$quantity || $quantity < 0) {
            return false;
        }
        
        try {
            $this->conn->beginTransaction();
            
            $query = "UPDATE " . $this->table_name . " 
                     SET stock_quantity = stock_quantity - ?, 
                     updated_at = NOW() 
                     WHERE id = ? AND stock_quantity >= ?";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $quantity);
            $stmt->bindParam(2, $this->id);
            $stmt->bindParam(3, $quantity);
            
            $result = $stmt->execute();
            $affected_rows = $stmt->rowCount();
            
            if ($affected_rows > 0) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Stock update failed: " . $e->getMessage());
            return false;
        }
    }

    // Check stock availability
    public function checkStock($required_quantity) {
        // Security: Validate inputs
        $this->id = $this->validateInput($this->id, 'int');
        $required_quantity = $this->validateInput($required_quantity, 'int');
        
        if (!$this->id || !$required_quantity || $required_quantity < 0) {
            return false;
        }
        
        $query = "SELECT stock_quantity FROM " . $this->table_name . " 
                 WHERE id = ? AND status = 'active'";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['stock_quantity'] >= $required_quantity) {
            return true;
        }
        return false;
    }

    // Security: Sanitize output for display
    public function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
?>