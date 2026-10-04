<?php
// app/controllers/ProductController.php
require_once __DIR__ . '/../helpers/cache.php';
class ProductController {
    private $db;
    private $productModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->productModel = new Product($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public function index($language = 'en') {
        try {
            // Get all products with language support (cached)
            $productsCacheKey = 'products_' . $language;
            $products = Cache::get($productsCacheKey);
            if ($products === false) {
                $products = $this->productModel->read($language);
                Cache::set($productsCacheKey, $products, 300);
            }
            // Process product images with correct paths
            $products = $this->processProductImages($products);

            // Get categories for filter (cached)
            $categoryModel = new Category($this->db);
            $categoriesCacheKey = 'categories_' . $language;
            $categories = Cache::get($categoriesCacheKey);
            if ($categories === false) {
                $categories = $categoryModel->read($language);
                Cache::set($categoriesCacheKey, $categories, 600);
            }
            
            // Return data for the view
            return [
                'products' => $products,
                'categories' => $categories,
                'page_title' => LanguageHelper::t('our_products', 'Our Products', $language) . ' - Phool Delivery',
                'pathConfig' => $this->pathConfig
            ];
        } catch (Exception $e) {
            error_log("ProductController index error: " . $e->getMessage());
            return [
                'products' => [],
                'categories' => [],
                'page_title' => LanguageHelper::t('our_products', 'Our Products', $language) . ' - Phool Delivery',
                'pathConfig' => $this->pathConfig
            ];
        }
    }
    
    public function detail($id, $language = 'en') {
        try {
            // Validate ID
            if (!is_numeric($id) || $id <= 0) {
                $this->redirectToProducts();
                return;
            }

            // Set product ID
            $this->productModel->id = (int)$id;
            $productCacheKey = 'product_detail_' . $id . '_' . $language;
            $productData = Cache::get($productCacheKey);

            if ($productData === false) {
                // Get product details with language support
                if ($this->productModel->readOne($language)) {
                    // Get localized product data
                    $productData = $this->productModel->getLocalizedData($language);
                    Cache::set($productCacheKey, $productData, 300);
                } else {
                    $this->redirectToProducts();
                    return;
                }
            }

            // Process product images with correct paths
            $productData = $this->processSingleProductImages($productData);

            // Get related products
            $relatedProducts = $this->getRelatedProducts($id, $this->productModel->category_id, $language);

            return [
                'product' => $productData,
                'related_products' => $relatedProducts,
                'page_title' => $productData['name'] . ' - Phool Delivery',
                'pathConfig' => $this->pathConfig
            ];
        } catch (Exception $e) {
            error_log("ProductController detail error for ID {$id}: " . $e->getMessage());
            $this->redirectToProducts();
            return;
        }
    }
    
    // Enhanced byCategory method with improved SEO and category-specific views
    public function byCategory($category_id, $language = 'en') {
        try {
            // Validate category ID
            if (!is_numeric($category_id) || $category_id <= 0) {
                $this->redirectToProducts();
                return;
            }
            
            $category_id = (int)$category_id;
            
            // Get category information with language support
            $categoryModel = new Category($this->db);
            $categoryModel->id = $category_id;
            
            if (!$categoryModel->readOne()) {
                throw new Exception("Category not found");
            }
            
            // Get localized category data
            $categoryData = $categoryModel->getLocalizedData($language);
            
            if (!$categoryData || $categoryData['status'] !== 'active') {
                throw new Exception("Category not active or not found");
            }
            
            $category_name = $categoryData['name'];
            
            // Set category-specific SEO with language support
            $page_title_key = "category_seo_title_" . $category_id;
            $page_title_default = $category_name . " - Phool Delivery";
            $page_title = LanguageHelper::t($page_title_key, $page_title_default, $language);
            
            // Get products by category with language support
            $products = $this->productModel->readByCategory($category_id, $language);
            
            // Process product images with correct paths
            $products = $this->processProductImages($products);
            
            // Determine which view to load based on category ID
            $view_file = $this->getCategoryViewFile($category_id);
            
            // Return data for the view
            return [
                'products' => $products,
                'category_id' => $category_id,
                'category_name' => $category_name,
                'page_title' => $page_title,
                'view_file' => $view_file,
                'show_filters' => true,
                'pathConfig' => $this->pathConfig
            ];
            
        } catch (Exception $e) {
            error_log("ProductController byCategory error for category {$category_id}: " . $e->getMessage());
            
            // Return error data instead of redirecting to allow custom error handling
            return [
                'error' => LanguageHelper::t('category_not_found', 'Category not found', $language),
                'page_title' => LanguageHelper::t('category_not_found', 'Category Not Found', $language) . ' - Phool Delivery',
                'pathConfig' => $this->pathConfig
            ];
        }
    }
    
    // Helper method to get category-specific view file
    private function getCategoryViewFile($category_id) {
        $view_mapping = [
            5  => 'category-roses.php',
            6  => 'category-festival-flowers.php',
            7  => 'category-fruits.php',
            8  => 'category-vegetables.php',
            9  => 'category-fresh-flowers.php',
            10 => 'category-bouquets.php',
            11 => 'category-cakes.php',
            12 => 'category-garlands.php',
            13 => 'category-potted-plants.php',
            14 => 'category-seeds.php',
            15 => 'category-gift-items.php',
            16 => 'category-festival-specials.php',
            17 => 'category-subscription.php'
        ];
        
        return $view_mapping[$category_id] ?? 'category-template.php';
    }
    
    // Add review action
    public function addReview() {
        header('Content-Type: application/json');
        
        try {
            // Check if user is logged in
            if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
                echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
                exit;
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                exit;
            }
            
            $user_id = $_SESSION['customer_id'];
            $product_id = $input['product_id'] ?? null;
            $rating = $input['rating'] ?? null;
            $title = $this->sanitizeInput($input['title'] ?? '');
            $content = $this->sanitizeInput($input['content'] ?? '');
            
            // Validate input
            if (!$product_id || !$rating || !$title || !$content) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            if (!is_numeric($product_id) || $product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product']);
                exit;
            }
            
            if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
                echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
                exit;
            }
            
            // Validate input length
            if (strlen($title) > 255) {
                echo json_encode(['success' => false, 'message' => 'Title is too long']);
                exit;
            }
            
            if (strlen($content) > 1000) {
                echo json_encode(['success' => false, 'message' => 'Content is too long']);
                exit;
            }
            
            // Add review using product model
            $result = $this->productModel->addReview($user_id, $product_id, $rating, $title, $content);
            echo json_encode($result);
            
        } catch (Exception $e) {
            error_log("ProductController addReview error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your review']);
        }
        exit;
    }
    
    // Get related products with language support
    private function getRelatedProducts($product_id, $category_id, $language = 'en', $limit = 4) {
        try {
            $name_field = $language === 'ne' ? 'name_ne' : 'name_en';
            $description_field = $language === 'ne' ? 'description_ne' : 'description_en';
            $category_name_field = $language === 'ne' ? 'c.name_ne' : 'c.name_en';
            
                // Avoid ORDER BY RAND() for performance; use recent/popular ordering instead
                $query = "SELECT p.id, p.{$name_field} as name, p.{$description_field} as description, 
                         p.price, p.bulk_price, p.event_price, p.unit, p.stock_quantity, 
                         p.category_id, {$category_name_field} as category_name
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
                     ORDER BY p.created_at DESC 
                     LIMIT ?";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $category_id);
            $stmt->bindParam(2, $product_id);
            $stmt->bindParam(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get images for each product with correct paths
            $productModel = new Product($this->db);
            foreach ($products as &$product) {
                $product['images'] = $this->processProductImagesArray($productModel->getProductImages($product['id']));
            }
            
            return $products;
        } catch (Exception $e) {
            error_log("ProductController getRelatedProducts error: " . $e->getMessage());
            return [];
        }
    }
    
    // API endpoint for JSON responses
    public function apiIndex($language = 'en') {
        try {
            $productsCacheKey = 'products_api_' . $language;
            $products = Cache::get($productsCacheKey);
            if ($products === false) {
                $products = $this->productModel->read($language);
                Cache::set($productsCacheKey, $products, 300);
            }
            // Process product images with correct paths for API response
            $products = $this->processProductImages($products);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'products' => $products,
                'page_title' => 'Our Products - Phool Delivery'
            ]);
        } catch (Exception $e) {
            error_log("ProductController apiIndex error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unable to load products'
            ]);
        }
        exit;
    }
    
    // API endpoint for single product
    public function apiDetail($id, $language = 'en') {
        try {
            // Validate ID
            if (!is_numeric($id) || $id <= 0) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
                exit;
            }
            
            $this->productModel->id = (int)$id;
            
            if ($this->productModel->readOne($language)) {
                $productData = $this->productModel->getLocalizedData($language);
                
                // Process product images with correct paths
                $productData = $this->processSingleProductImages($productData);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'product' => $productData
                ]);
            } else {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Product not found']);
            }
        } catch (Exception $e) {
            error_log("ProductController apiDetail error for ID {$id}: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to load product details']);
        }
        exit;
    }
    
    // Get product by slug
    public function getBySlug($slug, $language = 'en') {
        try {
            // Validate slug
            if (empty($slug) || !is_string($slug)) {
                $this->redirectToProducts();
                return;
            }
            
            $slug = $this->sanitizeInput($slug);
            $cacheKey = 'product_slug_' . $slug . '_' . $language;
            $product = Cache::get($cacheKey);
            if ($product === false) {
                $product = $this->productModel->getProductBySlug($slug, $language);
                Cache::set($cacheKey, $product, 300);
            }
            
            if ($product) {
                // Process product images with correct paths
                $product = $this->processSingleProductImages($product);
                
                // Get related products
                $relatedProducts = $this->getRelatedProducts($product['id'], $product['category_id'], $language);
                
                return [
                    'product' => $product,
                    'related_products' => $relatedProducts,
                    'page_title' => $product['name'] . ' - Phool Delivery',
                    'pathConfig' => $this->pathConfig
                ];
            } else {
                // Product not found
                $this->redirectToProducts();
                return;
            }
        } catch (Exception $e) {
            error_log("ProductController getBySlug error for slug {$slug}: " . $e->getMessage());
            $this->redirectToProducts();
            return;
        }
    }
    
    // Image processing helper methods
    
    private function processProductImages($products) {
        foreach ($products as &$product) {
            $product = $this->processSingleProductImages($product);
        }
        return $products;
    }
    
    private function processSingleProductImages($product) {
        if (isset($product['image'])) {
            $product['image'] = $this->pathConfig->getImagePath($product['image'], 'product');
        }
        
        if (isset($product['images']) && is_array($product['images'])) {
            $product['images'] = $this->processProductImagesArray($product['images']);
        }
        
        return $product;
    }
    
    private function processProductImagesArray($images) {
        $processedImages = [];
        foreach ($images as $image) {
            if (is_array($image) && isset($image['image_url'])) {
                $image['image_url'] = $this->pathConfig->getImagePath($image['image_url'], 'product');
                $processedImages[] = $image;
            } else if (is_string($image)) {
                $processedImages[] = $this->pathConfig->getImagePath($image, 'product');
            } else {
                $processedImages[] = $image;
            }
        }
        return $processedImages;
    }
    
    // Private helper methods
    
    private function redirectToProducts() {
        $productsUrl = $this->pathConfig->url('products');
        echo '<script>window.location.href = "' . $productsUrl . '";</script>';
        exit;
    }
    
    private function sanitizeInput($input) {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }
}
?>