<?php
// app/controllers/HomeController.php
require_once __DIR__ . '/../helpers/cache.php';

class HomeController {
    private $db;
    private $productModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->productModel = new Product($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    /**
     * OPTIMIZED: All database queries moved to controller from view
     * Caching strategy: Home page cache is 5 minutes
     * Invalidate manually only when products/notices/ads change
     */
    public function index() {
        // Try to get full page cache
        $pageCacheKey = 'home_page_data_' . (isset($_SESSION['user_city_id']) ? $_SESSION['user_city_id'] : '1') . '_' . LanguageHelper::getCurrentLanguage();
        $cachedData = Cache::get($pageCacheKey);
        
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        // CACHE MISS - Fetch all data
        $data = [
            'ads' => $this->getAds(),
            'notices' => $this->getNotices(),
            'videos' => $this->getVideos(),
            'products' => $this->getProductsWithImages(),
            'cities' => $this->getAvailableCities(),
            'current_city' => $this->getCurrentCity(),
            'page_title' => 'Phool Delivery - Fresh Flowers in Banepa',
            'pathConfig' => $this->pathConfig,
            'base_url' => $this->pathConfig->get('base_url'),
            'assets_url' => $this->pathConfig->get('assets')
        ];
        
        // Cache for 5 minutes (300 seconds)
        Cache::set($pageCacheKey, $data, 300);
        
        return $data;
    }
    
    /**
     * FIX 1: Get ads - single optimized query
     * CRITICAL: Ensure index on (status, created_at)
     */
    private function getAds() {
        $cacheKey = 'home_ads_active';
        $ads = Cache::get($cacheKey);
        
        if ($ads === false) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, title, file_path, status 
                    FROM ads 
                    WHERE status = 'active' 
                    ORDER BY created_at DESC 
                    LIMIT 2
                ");
                $stmt->execute();
                $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                Cache::set($cacheKey, $ads, 600); // 10 minutes
            } catch (Exception $e) {
                error_log("Error fetching ads: " . $e->getMessage());
                $ads = [];
            }
        }
        
        return $ads;
    }
    
    /**
     * FIX 2: Get notices - single optimized query
     * CRITICAL: Ensure index on (status, created_at)
     */
    private function getNotices() {
        $cacheKey = 'home_notices_active';
        $notices = Cache::get($cacheKey);
        
        if ($notices === false) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, title, file_path, media_type, button_type, status 
                    FROM notices 
                    WHERE status = 'active' 
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
                $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                Cache::set($cacheKey, $notices, 600); // 10 minutes
            } catch (Exception $e) {
                error_log("Error fetching notices: " . $e->getMessage());
                $notices = [];
            }
        }
        
        return $notices;
    }
    
    /**
     * FIX 3: Get video items - single optimized query
     * CRITICAL: Ensure index on (media_type, created_at)
     */
    private function getVideos() {
        $cacheKey = 'home_videos_active';
        $videos = Cache::get($cacheKey);
        
        if ($videos === false) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, title, description, file_path, thumbnail_path 
                    FROM media 
                    WHERE media_type = 'video' AND status = 'active'
                    ORDER BY created_at DESC 
                    LIMIT 4
                ");
                $stmt->execute();
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                Cache::set($cacheKey, $videos, 600); // 10 minutes
            } catch (Exception $e) {
                error_log("Error fetching videos: " . $e->getMessage());
                $videos = [];
            }
        }
        
        return $videos;
    }
    
    /**
     * FIX 4: Get products with images - OPTIMIZED 2-query approach
     * Instead of: products + images (separate queries per product)
     * Now: products (1) + all images (1) = 2 queries total
     */
    private function getProductsWithImages() {
        $cacheKey = 'home_products_with_images_' . (isset($_SESSION['user_city_id']) ? $_SESSION['user_city_id'] : '1');
        $products = Cache::get($cacheKey);
        
        if ($products === false) {
            try {
                // Get current city ID from session
                $current_city_id = $_SESSION['user_city_id'] ?? 1;
                
                // QUERY 1: Get product IDs available in current city
                $stmt1 = $this->db->prepare("
                    SELECT DISTINCT pca.product_id 
                    FROM product_city_availability pca 
                    WHERE pca.city_id = ? AND pca.is_available = 1
                    LIMIT 20
                ");
                $stmt1->execute([$current_city_id]);
                $product_ids = $stmt1->fetchAll(PDO::FETCH_COLUMN, 0);
                
                if (empty($product_ids)) {
                    $products = [];
                    Cache::set($cacheKey, $products, 300);
                    return $products;
                }
                
                // QUERY 2: Fetch all products at once with primary images
                $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
                $stmt2 = $this->db->prepare("
                    SELECT p.*, 
                           (SELECT pi.image_path 
                            FROM product_images pi 
                            WHERE pi.product_id = p.id AND pi.is_primary = 1 
                            LIMIT 1) as primary_image
                    FROM products p 
                    WHERE p.status = 'active' AND p.id IN ($placeholders)
                    ORDER BY p.created_at DESC
                ");
                $stmt2->execute($product_ids);
                $products = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                // Add image paths
                foreach ($products as &$product) {
                    $primary_image = $product['primary_image'] ?? 'default.jpg';
                    $product['image_url'] = $this->pathConfig->getImagePath($primary_image, 'product');
                }
                unset($product);
                
                Cache::set($cacheKey, $products, 300); // 5 minutes
            } catch (Exception $e) {
                error_log("Error fetching products: " . $e->getMessage());
                $products = [];
            }
        }
        
        return $products;
    }
    
    /**
     * FIX 5: Get available cities - single query, aggressive cache
     */
    private function getAvailableCities() {
        $cacheKey = 'home_available_cities';
        $cities = Cache::get($cacheKey);
        
        if ($cities === false) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id, city_name 
                    FROM delivery_cities 
                    ORDER BY city_name
                ");
                $stmt->execute();
                $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                Cache::set($cacheKey, $cities, 3600); // 1 hour - rarely changes
            } catch (Exception $e) {
                error_log("Error fetching cities: " . $e->getMessage());
                $cities = [];
            }
        }
        
        return $cities;
    }
    
    /**
     * FIX 6: Get current city - check session first
     */
    private function getCurrentCity() {
        $current_city_id = $_SESSION['user_city_id'] ?? 1;
        $current_city_name = $_SESSION['user_city_name'] ?? 'Banepa';
        
        // For logged-in users, get their preferred city
        if (isset($_SESSION['customer_id'])) {
            $cacheKey = 'customer_city_' . $_SESSION['customer_id'];
            $cityData = Cache::get($cacheKey);
            
            if ($cityData === false) {
                try {
                    $stmt = $this->db->prepare("
                        SELECT c.city, dc.id as city_id, dc.city_name 
                        FROM customers c 
                        LEFT JOIN delivery_cities dc ON LOWER(TRIM(c.city)) = LOWER(TRIM(dc.city_name))
                        WHERE c.id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$_SESSION['customer_id']]);
                    $cityData = $stmt->fetch(PDO::FETCH_ASSOC);
                    Cache::set($cacheKey, $cityData, 3600); // 1 hour
                } catch (Exception $e) {
                    error_log("Error fetching customer city: " . $e->getMessage());
                    $cityData = null;
                }
            }
            
            if ($cityData && !empty($cityData['city_id'])) {
                $current_city_id = $cityData['city_id'];
                $current_city_name = $cityData['city_name'] ?? $cityData['city'];
                $_SESSION['user_city_id'] = $current_city_id;
                $_SESSION['user_city_name'] = $current_city_name;
            }
        }
        
        return [
            'id' => $current_city_id,
            'name' => $current_city_name
        ];
    }
    
    /**
     * Cache invalidation methods - call these when data changes
     */
    public static function invalidateAdsCache() {
        Cache::delete('home_ads_active');
        Cache::delete('home_page_data_1_en');
        Cache::delete('home_page_data_1_ne');
    }
    
    public static function invalidateNoticesCache() {
        Cache::delete('home_notices_active');
        Cache::delete('home_page_data_1_en');
        Cache::delete('home_page_data_1_ne');
    }
    
    public static function invalidateProductsCache($city_id = null) {
        Cache::delete('home_products_with_images_' . ($city_id ?? 1));
        Cache::delete('home_page_data_' . ($city_id ?? 1) . '_en');
        Cache::delete('home_page_data_' . ($city_id ?? 1) . '_ne');
    }
    
    // Helper method to get image URL (for use in views)
    public function getImageUrl($imageName, $type = 'product') {
        return $this->pathConfig->getImagePath($imageName, $type);
    }
    
    // Helper method to get asset URL
    public function getAssetUrl($assetPath) {
        $assetsBase = $this->pathConfig->get('assets');
        return $assetsBase . '/' . ltrim($assetPath, '/');
    }
    
    // Helper method to get correct URL for any path
    public function getUrl($path = '') {
        return $this->pathConfig->url($path);
    }
}
?>