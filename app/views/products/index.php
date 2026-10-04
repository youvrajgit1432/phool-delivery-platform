<?php
// app/views/products/index.php

// Use PathConfig singleton for path management
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('our_products', 'Our Products') . " - Phool Delivery Nepal | Fresh Flowers Delivery Banepa, Bhaktapur, Kathmandu";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');
$product_images_url = $pathConfig->get('product_images');

// Get current user's city preference with timeout reset (3 minutes)
$current_city_id = null;
$current_city_name = null;
$show_all_cities = false;

// Check if city preference has expired (3 minutes = 180 seconds)
$city_preference_timeout = 180; // 3 minutes in seconds
if (isset($_SESSION['user_city_last_set'])) {
    $time_since_set = time() - $_SESSION['user_city_last_set'];
    if ($time_since_set > $city_preference_timeout) {
        // Reset to default city (Banepa) after timeout
        unset($_SESSION['user_city_id']);
        unset($_SESSION['user_city_name']);
        unset($_SESSION['user_city_last_set']);
    }
}

// Get current user's city preference
$current_city_id = $_SESSION['user_city_id'] ?? 1; // Default to Banepa (ID 1)
$current_city_name = $_SESSION['user_city_name'] ?? 'Banepa';

// Check if user wants to see all cities (from filter)
$show_all_cities = isset($_GET['city_filter']) && $_GET['city_filter'] === 'all';

// Handle city filter change - AUTO APPLY WHEN CITY CHANGES
if (isset($_GET['city_filter'])) {
    if ($_GET['city_filter'] === 'all') {
        $show_all_cities = true;
    } else {
        $selected_city_id = intval($_GET['city_filter']);
        
        // Validate if the city exists
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $city_stmt = $db->prepare("SELECT id, city_name FROM delivery_cities WHERE id = ?");
            $city_stmt->execute([$selected_city_id]);
            $city_data = $city_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($city_data) {
                $current_city_id = $city_data['id'];
                $current_city_name = $city_data['city_name'];
                $_SESSION['user_city_id'] = $current_city_id;
                $_SESSION['user_city_name'] = $current_city_name;
                $_SESSION['user_city_last_set'] = time(); // Set timestamp
                $show_all_cities = false;
                
                // AUTO REFRESH PRODUCTS WHEN CITY CHANGES
                // The page will reload with new city filter applied
            }
        } catch (Exception $e) {
            error_log("Error fetching city data: " . $e->getMessage());
        }
    }
}

// Fetch categories with hierarchy (main and sub categories)
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get main categories
    $main_categories_stmt = $db->prepare("
        SELECT * FROM categories 
        WHERE status = 'active' AND type = 'main' 
        ORDER BY sort_order ASC
    ");
    $main_categories_stmt->execute();
    $main_categories = $main_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subcategories for each main category
    $category_hierarchy = [];
    foreach ($main_categories as $main_category) {
        $main_category['name'] = LanguageHelper::getLocalizedText($main_category, 'name');
        
        // Get subcategories for this main category
        $subcategories_stmt = $db->prepare("
            SELECT * FROM categories 
            WHERE status = 'active' AND type = 'sub' AND parent_id = ? 
            ORDER BY sort_order ASC
        ");
        $subcategories_stmt->execute([$main_category['id']]);
        $subcategories = $subcategories_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Localize subcategory names
        foreach ($subcategories as &$subcategory) {
            $subcategory['name'] = LanguageHelper::getLocalizedText($subcategory, 'name');
        }
        unset($subcategory);
        
        $main_category['subcategories'] = $subcategories;
        $category_hierarchy[] = $main_category;
    }
    
} catch (Exception $e) {
    $category_hierarchy = [];
    error_log("Error fetching category hierarchy: " . $e->getMessage());
}

// Fetch ALL categories for product-category mapping
try {
    $all_categories_stmt = $db->prepare("SELECT id, name_en, name_ne, parent_id, type FROM categories WHERE status = 'active'");
    $all_categories_stmt->execute();
    $all_categories = $all_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of category IDs to names for easy lookup
    $category_map = [];
    foreach ($all_categories as $cat) {
        $category_map[$cat['id']] = [
            'name' => LanguageHelper::getLocalizedText($cat, 'name'),
            'parent_id' => $cat['parent_id'],
            'type' => $cat['type']
        ];
    }
    
} catch (Exception $e) {
    $all_categories = [];
    $category_map = [];
    error_log("Error fetching all categories: " . $e->getMessage());
}

// Fetch delivery cities for filter
$delivery_cities = [];
try {
    $city_stmt = $db->prepare("SELECT id, city_name FROM delivery_cities ORDER BY city_name");
    $city_stmt->execute();
    $delivery_cities = $city_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching delivery cities: " . $e->getMessage());
}

// Initialize variables for products
$products_by_category = [];
$all_products = [];
$products_count_by_category = [];

// Fetch products by category
foreach ($category_hierarchy as $main_category) {
    $main_category_id = $main_category['id'];
    
    // Fetch products for main category
    $main_category_products = fetchProductsByCategory($main_category_id, $current_city_id, $show_all_cities, $db);
    $products_by_category[$main_category_id] = $main_category_products;
    $products_count_by_category[$main_category_id] = count($main_category_products);
    
    // Add to all products array
    $all_products = array_merge($all_products, $main_category_products);
    
    // Fetch products for each subcategory
    foreach ($main_category['subcategories'] as $subcategory) {
        $subcategory_id = $subcategory['id'];
        $subcategory_products = fetchProductsByCategory($subcategory_id, $current_city_id, $show_all_cities, $db);
        $products_by_category[$subcategory_id] = $subcategory_products;
        $products_count_by_category[$subcategory_id] = count($subcategory_products);
        
        // Add to all products array
        $all_products = array_merge($all_products, $subcategory_products);
    }
}

// Function to fetch products by category
function fetchProductsByCategory($category_id, $current_city_id, $show_all_cities, $db) {
    // Build the product query based on city filter
    if (!$show_all_cities && $current_city_id) {
        // Show only products available in the selected city
        $product_query = "
            SELECT DISTINCT p.*, pi.image_path, pi.is_primary,
                   pca.is_available,
                   pmq.minimum_quantity as city_minimum_quantity,
                   dc.city_name as available_city
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
            LEFT JOIN product_categories pc ON p.id = pc.product_id
            LEFT JOIN product_city_availability pca ON p.id = pca.product_id AND pca.city_id = ?
            LEFT JOIN product_minimum_quantities pmq ON p.id = pmq.product_id AND pmq.city_id = ?
            LEFT JOIN delivery_cities dc ON dc.id = ?
            WHERE p.status = 'active' 
            AND pc.category_id = ?
            AND (pca.is_available = 1 OR pca.is_available IS NULL)
        ";
        
        $query_params = [$current_city_id, $current_city_id, $current_city_id, $category_id];
    } else {
        // Show all products from all cities
        $product_query = "
            SELECT DISTINCT p.*, pi.image_path, pi.is_primary,
                   NULL as is_available,
                   NULL as city_minimum_quantity,
                   NULL as available_city
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
            LEFT JOIN product_categories pc ON p.id = pc.product_id
            WHERE p.status = 'active'
            AND pc.category_id = ?
        ";
        
        $query_params = [$category_id];
    }
    
    $product_query .= " ORDER BY p.created_at ASC";  // Show old products first
    
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute($query_params);
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process products
    return processProducts($products, $db);
}

// Function to process products with categories and images
function processProducts($products, $db) {
    if (empty($products)) {
        return [];
    }
    
    $product_ids = array_column($products, 'id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    
    // Get product categories
    $prod_cat_stmt = $db->prepare("
        SELECT pc.product_id, pc.category_id, c.name_en, c.name_ne, c.parent_id, c.type 
        FROM product_categories pc 
        LEFT JOIN categories c ON pc.category_id = c.id 
        WHERE pc.product_id IN ($placeholders) 
        AND c.status = 'active'
    ");
    $prod_cat_stmt->execute($product_ids);
    $prod_cat_data = $prod_cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize categories by product
    $product_categories = [];
    foreach ($prod_cat_data as $row) {
        $product_id = $row['product_id'];
        if (!isset($product_categories[$product_id])) {
            $product_categories[$product_id] = [];
        }
        $product_categories[$product_id][] = [
            'id' => $row['category_id'],
            'name' => LanguageHelper::getLocalizedText($row, 'name'),
            'parent_id' => $row['parent_id'],
            'type' => $row['type']
        ];
    }
    
    // Get product images
    $image_stmt = $db->prepare("
        SELECT product_id, image_path, is_primary 
        FROM product_images 
        WHERE product_id IN ($placeholders)
        ORDER BY is_primary DESC, id ASC
    ");
    $image_stmt->execute($product_ids);
    $all_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize images by product
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
    
    // Process each product
    foreach ($products as &$product) {
        $product_id = $product['id'];
        
        // Get product categories
        $product['categories'] = $product_categories[$product_id] ?? [];
        
        // Find main category
        $product['main_category_id'] = null;
        foreach ($product['categories'] as $cat) {
            if ($cat['type'] === 'main') {
                $product['main_category_id'] = $cat['id'];
                break;
            } elseif ($cat['parent_id'] !== null) {
                // Check if parent is a main category
                if (isset($GLOBALS['category_map'][$cat['parent_id']]) && 
                    $GLOBALS['category_map'][$cat['parent_id']]['type'] === 'main') {
                    $product['main_category_id'] = $cat['parent_id'];
                }
            }
        }
        
        $product['images'] = $product_images[$product_id] ?? [];
        $product['name'] = LanguageHelper::getLocalizedText($product, 'name');
        $product['description'] = LanguageHelper::getLocalizedText($product, 'description');
        $product['slug'] = LanguageHelper::getLocalizedSlug($product);
        
        // If no images found, use a placeholder
        if (empty($product['images'])) {
            $product['images'][] = [
                'image_path' => 'placeholder.jpg',
                'is_primary' => 1
            ];
        }
    }
    
    return $products;
}

// Calculate display counts - MODIFIED: Show 4 initially, load 3 more
$initial_display_count = 4; // Show first 4 products initially
$load_more_count = 3; // Load 3 more each time

// SEO Meta Tags
$meta_description = "Buy fresh marigold flowers (sayapatri, genda phool), roses, and organic flowers for Tihar, Dashain, weddings, puja. Same-day flower delivery in Banepa, Bhaktapur, Kathmandu. Direct from farmers to your home.";
$meta_keywords = "phool delivery Nepal, marigold delivery, sayapatri flowers, genda phool, organic flowers, Tihar flowers, Dashain decoration, flower delivery Banepa, flower delivery Bhaktapur, flower delivery Kathmandu, fresh flowers, fool delivery, ful delivery, phul delivery, pool delivery, festival flowers, puja flowers, wedding flowers, birthday flowers, bulk flowers, 2kg marigold, 5kg flowers, 10kg flowers, same day delivery, farm fresh flowers, local flowers, Chandeshwori flowers, Pashupatinath flowers, temple flowers, eco-friendly flowers, plastic-free garlands";
?>

<!-- Schema.org Structured Data for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Florist",
  "name": "Phool Delivery Nepal",
  "description": "Fresh sayapatri, marigold, and genda phool delivery service for Tihar, Dashain, and puja. Direct from farmers to homes in Banepa, Bhaktapur, and Kathmandu.",
  "url": "<?= $base_url ?>",
  "telephone": "+977-9800000000",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Banepa",
    "addressRegion": "Kavrepalanchok",
    "addressCountry": "NP"
  },
  "areaServed": ["Banepa", "Bhaktapur", "Kathmandu", "Dhulikhel", "Kavre"],
  "openingHours": "Mo-Su 08:00-20:00",
  "priceRange": "Rs.",
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "Flower Products",
    "itemListElement": [
      <?php foreach($all_products as $index => $product): ?>
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Product",
          "name": "<?= addslashes($product['name']) ?>",
          "description": "<?= addslashes($product['description']) ?>",
          "category": "Flowers"
        },
        "price": "<?= $product['price'] ?>",
        "priceCurrency": "NPR",
        "availability": "https://schema.org/<?= $product['stock_quantity'] > 0 ? 'InStock' : 'OutOfStock' ?>"
      }<?= $index < count($all_products)-1 ? ',' : '' ?>
      <?php endforeach; ?>
    ]
  }
}
</script>

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
                    <i class="fas fa-phone"></i>
                    <span>9800000000</span>
                    <a href="tel:9800000000" class="call-btn">Call Now</a>
                </div>
                <div class="phone-number">
                    <i class="fas fa-mobile-alt"></i>
                    <span>9800000001</span>
                    <a href="tel:9800000001" class="call-btn">Call Now</a>
                </div>
            </div>
            <div class="contact-hours">
                <p><strong><?= LanguageHelper::t('available_hours', 'Available Hours') ?>:</strong> 8:00 AM - 8:00 PM</p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Advanced E-commerce Filter System -->
    <div class="advanced-filter-system">
        <!-- Mobile Filter Header - FIXED: Both buttons in same row -->
        <div class="filter-mobile-header">
            <div class="mobile-filter-row">
                <button class="filter-toggle-btn" id="mobileFilterToggle" data-aos="fade-down" data-aos-delay="50">
                    <i class="fas fa-sliders-h"></i>
                    <span>Filters</span>
                    <span class="filter-count" id="mobileFilterCount">0</span>
                </button>
                
                <div class="mobile-sort" data-aos="fade-down" data-aos-delay="100">
                    <select id="mobile-sort-filter" class="sort-select">
                        <option value="oldest">Sort: Oldest First</option>
                        <option value="newest">Sort: Newest First</option>
                        <option value="price-low">Sort: Price Low to High</option>
                        <option value="price-high">Sort: Price High to Low</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Mobile Filter Modal Overlay -->
        <div class="filter-overlay" id="filterOverlay"></div>

        <!-- Mobile Filter Modal -->
        <div class="mobile-filter-modal" id="mobileFilterModal">
            <div class="mobile-filter-header">
                <h3>Filters</h3>
                <button class="close-filters" id="closeMobileFilters">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mobile-filter-content">
                <!-- City Filter -->
                <div class="filter-group" data-aos="fade-up" data-aos-delay="50">
                    <div class="filter-title">
                        <h4>Delivery City</h4>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" id="mobile-city-all" name="mobile-city" value="all" <?= $show_all_cities ? 'checked' : '' ?>>
                            <label for="mobile-city-all">All Cities</label>
                        </div>
                        <?php foreach ($delivery_cities as $city): ?>
                        <div class="filter-option">
                            <input type="radio" id="mobile-city-<?= $city['id'] ?>" name="mobile-city" value="<?= $city['id'] ?>" 
                                <?= (!$show_all_cities && $current_city_id == $city['id']) ? 'checked' : '' ?>>
                            <label for="mobile-city-<?= $city['id'] ?>"><?= htmlspecialchars($city['city_name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-group" data-aos="fade-up" data-aos-delay="100">
                    <div class="filter-title">
                        <h4>Categories</h4>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" id="mobile-category-all" name="mobile-category" value="all" checked>
                            <label for="mobile-category-all">All Categories</label>
                        </div>
                        <?php foreach ($category_hierarchy as $main_category): ?>
                        <div class="filter-option">
                            <input type="checkbox" id="mobile-category-<?= $main_category['id'] ?>" name="mobile-category" value="<?= $main_category['id'] ?>">
                            <label for="mobile-category-<?= $main_category['id'] ?>"><?= htmlspecialchars($main_category['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sort Options -->
                <div class="filter-group" data-aos="fade-up" data-aos-delay="150">
                    <div class="filter-title">
                        <h4>Sort By</h4>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" id="mobile-sort-oldest" name="mobile-sort" value="oldest" checked>
                            <label for="mobile-sort-oldest">Oldest First</label>
                        </div>
                        <div class="filter-option">
                            <input type="radio" id="mobile-sort-newest" name="mobile-sort" value="newest">
                            <label for="mobile-sort-newest">Newest First</label>
                        </div>
                        <div class="filter-option">
                            <input type="radio" id="mobile-sort-price-low" name="mobile-sort" value="price-low">
                            <label for="mobile-sort-price-low">Price: Low to High</label>
                        </div>
                        <div class="filter-option">
                            <input type="radio" id="mobile-sort-price-high" name="mobile-sort" value="price-high">
                            <label for="mobile-sort-price-high">Price: High to Low</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mobile-filter-actions">
                <button class="btn btn-clear" id="mobileClearFilters" data-aos="fade-up" data-aos-delay="50">Clear All</button>
                <button class="btn btn-apply" id="mobileApplyFilters" data-aos="fade-up" data-aos-delay="100">Apply Filters</button>
            </div>
        </div>

        <!-- Desktop Filter Layout - MODIFIED: 25% filter, 75% products -->
        <div class="filter-container">
            <!-- Left Sidebar Filters for Desktop - 25% width -->
            <div class="filter-sidebar">
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Filters</h3>
                        <button class="clear-all-filters" id="clearAllFilters">Clear All</button>
                    </div>
                    
                    <!-- City Filter -->
                    <div class="filter-group" data-aos="fade-right" data-aos-delay="50">
                        <div class="filter-title">
                            <h4>Delivery City</h4>
                        </div>
                        <div class="filter-options">
                            <div class="filter-option">
                                <input type="radio" id="city-all" name="city" value="all" <?= $show_all_cities ? 'checked' : '' ?>>
                                <label for="city-all">All Cities</label>
                            </div>
                            <?php foreach ($delivery_cities as $city): ?>
                            <div class="filter-option">
                                <input type="radio" id="city-<?= $city['id'] ?>" name="city" value="<?= $city['id'] ?>" 
                                    <?= (!$show_all_cities && $current_city_id == $city['id']) ? 'checked' : '' ?>>
                                <label for="city-<?= $city['id'] ?>"><?= htmlspecialchars($city['city_name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="filter-group" data-aos="fade-right" data-aos-delay="100">
                        <div class="filter-title">
                            <h4>Categories</h4>
                        </div>
                        <div class="filter-options">
                            <div class="filter-option">
                                <input type="radio" id="category-all" name="category" value="all" checked>
                                <label for="category-all">All Categories</label>
                            </div>
                            <?php foreach ($category_hierarchy as $main_category): ?>
                            <div class="filter-option">
                                <input type="checkbox" id="category-<?= $main_category['id'] ?>" name="category" value="<?= $main_category['id'] ?>">
                                <label for="category-<?= $main_category['id'] ?>"><?= htmlspecialchars($main_category['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sort Options for Desktop -->
                    <div class="filter-group" data-aos="fade-right" data-aos-delay="150">
                        <div class="filter-title">
                            <h4>Sort By</h4>
                        </div>
                        <div class="filter-options">
                            <div class="filter-option">
                                <input type="radio" id="sort-oldest" name="sort" value="oldest" checked>
                                <label for="sort-oldest">Oldest First</label>
                            </div>
                            <div class="filter-option">
                                <input type="radio" id="sort-newest" name="sort" value="newest">
                                <label for="sort-newest">Newest First</label>
                            </div>
                            <div class="filter-option">
                                <input type="radio" id="sort-price-low" name="sort" value="price-low">
                                <label for="sort-price-low">Price: Low to High</label>
                            </div>
                            <div class="filter-option">
                                <input type="radio" id="sort-price-high" name="sort" value="price-high">
                                <label for="sort-price-high">Price: High to Low</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area - 75% width -->
            <div class="filter-main-content">
                <!-- Active Filters Bar -->
                <div class="active-filters-bar" id="activeFiltersBar">
                    <div class="active-filters-container" id="activeFiltersContainer">
                        <!-- Active filters will appear here -->
                    </div>
                    <div class="results-count">
                        <span id="resultsCount"><?= count($all_products) ?> products available</span>
                    </div>
                </div>

                <!-- Products by Category Sections -->
                <div class="products-by-category" id="productsByCategory">
                    <?php if (empty($all_products)): ?>
                        <div class="no-products">
                            <p><?= LanguageHelper::t('no_products_found', 'No products found.') ?></p>
                            <?php if ($current_city_id && !$show_all_cities): ?>
                                <p class="suggestion">
                                    Try <a href="?city_filter=all" class="link">viewing products from all cities</a>
                                </p>
                            <?php else: ?>
                                <p class="suggestion">
                                    Please select a city to see available products.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Display products by category hierarchy -->
                        <?php foreach ($category_hierarchy as $main_category): 
                            $main_category_id = $main_category['id'];
                            $main_category_products = $products_by_category[$main_category_id] ?? [];
                            $main_category_count = $products_count_by_category[$main_category_id] ?? 0;
                            
                            if ($main_category_count > 0): ?>
                                <!-- Main Category Section -->
                                <div class="category-section main-category" data-category-id="<?= $main_category_id ?>">
                                    <h2 class="category-title"><?= htmlspecialchars($main_category['name']) ?></h2>
                                    
                                    <!-- Main Category Products -->
                                    <div class="category-products">
                                        <?php 
                                        // MODIFIED: Display only 4 products initially
                                        $displayed_products = array_slice($main_category_products, 0, $initial_display_count);
                                        $remaining_products = array_slice($main_category_products, $initial_display_count);
                                        $remaining_count = count($remaining_products);
                                        ?>
                                        
                                        <div class="products-grid" id="productsGrid-<?= $main_category_id ?>">
                                            <?php foreach ($displayed_products as $index => $product): 
                                                $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $pathConfig);
                                                echo $product_card;
                                            endforeach; ?>
                                        </div>
                                        
                                        <?php if ($main_category_count > $initial_display_count): ?>
                                            <!-- MODIFIED: Load More Button with Show Less functionality -->
                                            <div class="load-more-container" id="loadMoreContainer-<?= $main_category_id ?>">
                                                <?php if ($remaining_count > 0): ?>
                                                    <button class="btn btn-load-more load-more-btn" 
                                                            data-category-id="<?= $main_category_id ?>"
                                                            data-current-count="<?= count($displayed_products) ?>"
                                                            data-total-count="<?= $main_category_count ?>"
                                                            data-remaining-products='<?= htmlspecialchars(json_encode($remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                                            data-displayed-products='<?= htmlspecialchars(json_encode($displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                                        Load More (Show <?= min($load_more_count, $remaining_count) ?> more)
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-show-less show-less-btn" 
                                                        style="display: none;"
                                                        data-category-id="<?= $main_category_id ?>">
                                                    Show Less
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Subcategories -->
                                    <?php if (!empty($main_category['subcategories'])): ?>
                                        <?php foreach ($main_category['subcategories'] as $subcategory): 
                                            $subcategory_id = $subcategory['id'];
                                            $subcategory_products = $products_by_category[$subcategory_id] ?? [];
                                            $subcategory_count = $products_count_by_category[$subcategory_id] ?? 0;
                                            
                                            if ($subcategory_count > 0): ?>
                                                <div class="subcategory-section" data-subcategory-id="<?= $subcategory_id ?>">
                                                    <h3 class="subcategory-title"><?= htmlspecialchars($subcategory['name']) ?></h3>
                                                    
                                                    <!-- Subcategory Products -->
                                                    <div class="subcategory-products">
                                                        <?php 
                                                        // MODIFIED: Display only 4 products initially
                                                        $sub_displayed_products = array_slice($subcategory_products, 0, $initial_display_count);
                                                        $sub_remaining_products = array_slice($subcategory_products, $initial_display_count);
                                                        $sub_remaining_count = count($sub_remaining_products);
                                                        ?>
                                                        
                                                        <div class="products-grid" id="productsGrid-<?= $subcategory_id ?>">
                                                            <?php foreach ($sub_displayed_products as $index => $product): 
                                                                $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $pathConfig);
                                                                echo $product_card;
                                                            endforeach; ?>
                                                        </div>
                                                        
                                                        <?php if ($subcategory_count > $initial_display_count): ?>
                                                            <!-- MODIFIED: Load More Button with Show Less functionality -->
                                                            <div class="load-more-container" id="loadMoreContainer-<?= $subcategory_id ?>">
                                                                <?php if ($sub_remaining_count > 0): ?>
                                                                    <button class="btn btn-load-more load-more-btn" 
                                                                            data-category-id="<?= $subcategory_id ?>"
                                                                            data-current-count="<?= count($sub_displayed_products) ?>"
                                                                            data-total-count="<?= $subcategory_count ?>"
                                                                            data-remaining-products='<?= htmlspecialchars(json_encode($sub_remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                                                            data-displayed-products='<?= htmlspecialchars(json_encode($sub_displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                                                        Load More (Show <?= min($load_more_count, $sub_remaining_count) ?> more)
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button class="btn btn-show-less show-less-btn" 
                                                                        style="display: none;"
                                                                        data-category-id="<?= $subcategory_id ?>">
                                                                    Show Less
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fixed-contact-btn">
    <button class="btn contact-now-btn" id="contactBtn" title="Contact Us">
        <i class="fas fa-phone"></i>
    </button>
</div>
 
<?php
// Function to generate product card HTML
function generateProductCard($product, $index, $current_city_name, $show_all_cities, $pathConfig) {
    // Get primary image
    $primary_image = null;
    foreach ($product['images'] as $image) {
        if ($image['is_primary'] == 1) {
            $primary_image = $image['image_path'];
            break;
        }
    }
    if (!$primary_image && !empty($product['images'])) {
        $primary_image = $product['images'][0]['image_path'];
    }
    
    $image_path = $pathConfig->getImagePath($primary_image ?: 'placeholder.jpg', 'product');
    
    // Stock status
    $stock_status = '';
    $stock_class = '';
    
    if ($product['stock_quantity'] <= 0) {
        $stock_status = LanguageHelper::t('out_of_stock', 'Out of Stock');
        $stock_class = 'out-of-stock';
    } else if ($product['stock_quantity'] <= 5) {
        $stock_status = LanguageHelper::t('low_stock', 'Low Stock');
        $stock_class = 'low-stock';
    } else {
        $stock_status = LanguageHelper::t('in_stock', 'In Stock');
        $stock_class = 'in-stock';
    }
    
    // Generate SEO-friendly alt text
    $alt_text = htmlspecialchars($product['name']) . " - Fresh Flowers Delivery Nepal";
    
    // Check product availability for current city
    $is_available_in_city = $show_all_cities || $product['is_available'] !== '0';
    
    // Get product categories for display
    $product_category_names = [];
    foreach ($product['categories'] as $cat) {
        $product_category_names[] = $cat['name'];
    }
    $category_text = !empty($product_category_names) ? implode(', ', $product_category_names) : 'Uncategorized';
    
    // Create product card HTML
    ob_start();
    ?>
    <div class="product-card" 
         data-category="<?= $product['main_category_id'] ?>" 
         data-categories="<?= htmlspecialchars(json_encode(array_column($product['categories'], 'id'))) ?>"
         data-price="<?= $product['price'] ?>" 
         data-id="<?= $product['id'] ?>" 
         data-slug="<?= htmlspecialchars($product['slug_en'] ?? $product['slug'] ?? '') ?>"
         data-created="<?= strtotime($product['created_at']) ?>"
         data-city="<?= $show_all_cities ? 'all' : $current_city_name ?>">
        <?php if (!$is_available_in_city): ?>
            <div class="not-available-overlay">
                <span>Not Available in <?= htmlspecialchars($current_city_name) ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Product Badges -->
        <div class="product-badges">
            <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                <span class="badge discount-badge">Bulk Discount</span>
            <?php endif; ?>
            <?php if ($product['stock_quantity'] <= 5 && $product['stock_quantity'] > 0): ?>
                <span class="badge stock-badge">Low Stock</span>
            <?php endif; ?>
        </div>
        
        <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="product-image-link" aria-label="View details for <?= htmlspecialchars($product['name']) ?>">
            <img src="<?= $image_path ?>" alt="<?= $alt_text ?>" class="product-image" loading="lazy" onerror="this.onerror=null; this.src='<?= $pathConfig->get('assets') ?>/img/products/placeholder.jpg'">
        </a>
        <div class="product-info">
            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
            
            <!-- Display product categories -->
            <div class="product-categories">
                <small class="category-text"><?= htmlspecialchars($category_text) ?></small>
            </div>
            
            <div class="price-section">
                <p class="product-price">Rs. <?= number_format($product['price'], 2) ?> 
                    <span class="price-unit">/<?= $product['unit'] ?></span>
                </p>
                <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                    <p class="product-bulk-price">
                        Rs. <?= number_format($product['bulk_price'], 2) ?> for bulk
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="product-actions">
                <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> Details
                </a>
                <button class="btn btn-add-cart add-to-cart" 
                        data-id="<?= $product['id'] ?>" 
                        data-name="<?= htmlspecialchars($product['name']) ?>" 
                        data-price="<?= $product['price'] ?>"
                        data-unit="<?= $product['unit'] ?>"
                        data-image="<?= $image_path ?>"
                        <?= ($product['stock_quantity'] <= 0 || !$is_available_in_city) ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (!$is_available_in_city): ?>
                        Not Available
                    <?php elseif ($product['stock_quantity'] <= 0): ?>
                        Out of Stock
                    <?php else: ?>
                        Add to Cart
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS animation for filters and buttons
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 600,
            once: true,
            offset: 100
        });
    }

    // Filter Elements
    const mobileFilterToggle = document.getElementById('mobileFilterToggle');
    const mobileFilterModal = document.getElementById('mobileFilterModal');
    const filterOverlay = document.getElementById('filterOverlay');
    const closeMobileFilters = document.getElementById('closeMobileFilters');
    const mobileApplyFilters = document.getElementById('mobileApplyFilters');
    const mobileClearFilters = document.getElementById('mobileClearFilters');
    const activeFiltersBar = document.getElementById('activeFiltersBar');
    const activeFiltersContainer = document.getElementById('activeFiltersContainer');
    const resultsCount = document.getElementById('resultsCount');
    const mobileFilterCount = document.getElementById('mobileFilterCount');
    const clearAllFilters = document.getElementById('clearAllFilters');
    const productsByCategory = document.getElementById('productsByCategory');
    
    // Filter inputs
    const cityFilters = document.querySelectorAll('input[name="city"]');
    const categoryFilters = document.querySelectorAll('input[name="category"]');
    const sortFilters = document.querySelectorAll('input[name="sort"]');
    
    // Mobile filter inputs
    const mobileCityFilters = document.querySelectorAll('input[name="mobile-city"]');
    const mobileCategoryFilters = document.querySelectorAll('input[name="mobile-category"]');
    const mobileSortFilters = document.querySelectorAll('input[name="mobile-sort"]');
    const mobileSortFilter = document.getElementById('mobile-sort-filter');
    
    // MODIFIED: Load More and Show Less buttons
    const loadMoreButtons = document.querySelectorAll('.load-more-btn');
    const showLessButtons = document.querySelectorAll('.show-less-btn');
    
    // Active filters state
    let activeFilters = {
        city: '<?= $show_all_cities ? 'all' : $current_city_id ?>',
        categories: [],
        sort: 'oldest'
    };

    // Store ALL products for each category for Show Less functionality
    const categoryAllProducts = new Map();
    
    // DIRECT CHECKOUT FORM HANDLER
    function setupDirectCheckoutForm() {
        const form = document.querySelector('.direct-checkout-form');
        const orderNowButton = document.getElementById('orderNowButton');
        const orderProductSlug = document.getElementById('orderProductSlug');
        const orderQuantity = document.getElementById('orderQuantity');
        
        if (!form || !orderNowButton) {
            console.warn('Direct checkout form or button not found');
            return;
        }
        
        // Function to update form with first available product
        function updateFormWithFirstProduct() {
            const firstVisibleProduct = document.querySelector('.product-card:not([style*="display: none"])');
            if (firstVisibleProduct) {
                // Get slug from data attribute (most reliable)
                const productSlug = firstVisibleProduct.getAttribute('data-slug');
                const productId = firstVisibleProduct.getAttribute('data-id');
                
                if (productSlug) {
                    orderProductSlug.value = productSlug;
                    orderQuantity.value = 1;
                    orderNowButton.disabled = false;
                    return true;
                } else if (productId) {
                    // Fallback: try to extract from link if data-slug is not available
                    const productLink = firstVisibleProduct.querySelector('a[href*="/product/"]');
                    if (productLink) {
                        const href = productLink.getAttribute('href');
                        // Try to extract slug - it might be /product/{id} or /product/{id}/{slug}
                        const matches = href.match(/\/product\/(\d+)(?:\/(.+))?/);
                        if (matches) {
                            const extractedSlug = matches[2] || matches[1]; // Use slug if available, otherwise ID
                            orderProductSlug.value = extractedSlug;
                            orderQuantity.value = 1;
                            orderNowButton.disabled = false;
                            return true;
                        }
                    }
                }
            }
            return false;
        }
        
        // Update on page load
        setTimeout(() => {
            if (!updateFormWithFirstProduct()) {
                // Disable button if no products found
                orderNowButton.disabled = true;
                orderNowButton.title = 'No products available';
                console.warn('No products found for Order Now button');
            }
        }, 500);
        
        // Update when products are filtered
        document.addEventListener('filter-updated', updateFormWithFirstProduct);
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const slug = orderProductSlug.value;
            const qty = parseInt(orderQuantity.value) || 1;
            
            if (!slug) {
                alert('Please select a product first');
                return;
            }
            
            // Show loading state
            orderNowButton.disabled = true;
            const originalContent = orderNowButton.innerHTML;
            orderNowButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processing...</span>';
            
            // Submit via AJAX for better error handling
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_slug=' + encodeURIComponent(slug) + '&quantity=' + qty
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to checkout
                    window.location.href = data.redirect;
                } else {
                    alert('Error: ' + (data.message || 'Failed to process order'));
                    orderNowButton.disabled = false;
                    orderNowButton.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                orderNowButton.disabled = false;
                orderNowButton.innerHTML = originalContent;
            });
        });
    }
    
    // Initialize filters
    function initializeFilters() {
        updateActiveFilters();
        filterProducts();
        updateFilterCount();
    }

    // Mobile filter modal functions
    function openMobileFilters() {
        mobileFilterModal.classList.add('active');
        filterOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reinitialize AOS for mobile filter content
        if (typeof AOS !== 'undefined') {
            setTimeout(() => {
                AOS.refresh();
            }, 100);
        }
    }

    function closeMobileFiltersHandler() {
        mobileFilterModal.classList.remove('active');
        filterOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // AUTO APPLY FILTERS WHEN CITY CHANGES - RELOAD PAGE WITH NEW CITY
    function autoApplyCityFilter(cityId) {
        if (cityId === 'all') {
            window.location.href = '<?= $base_url ?>/products?city_filter=all';
        } else {
            window.location.href = '<?= $base_url ?>/products?city_filter=' + cityId;
        }
    }

    // Mobile filter modal toggle
    if (mobileFilterToggle) {
        mobileFilterToggle.addEventListener('click', openMobileFilters);
    }

    if (closeMobileFilters) {
        closeMobileFilters.addEventListener('click', closeMobileFiltersHandler);
    }

    // Close modal when clicking on overlay
    if (filterOverlay) {
        filterOverlay.addEventListener('click', closeMobileFiltersHandler);
    }

    // MODIFIED: Enhanced Load More and Show Less functionality
    function setupLoadMoreButtons() {
        // Store ALL products for each category when page loads
        document.querySelectorAll('.category-section, .subcategory-section').forEach(section => {
            const categoryId = section.getAttribute('data-category-id') || section.getAttribute('data-subcategory-id');
            const productGrid = section.querySelector('.products-grid');
            const loadMoreBtn = section.querySelector('.load-more-btn');
            
            if (productGrid && loadMoreBtn) {
                // Get ALL products from data attributes
                const displayedProducts = JSON.parse(loadMoreBtn.getAttribute('data-displayed-products') || '[]');
                const remainingProducts = JSON.parse(loadMoreBtn.getAttribute('data-remaining-products') || '[]');
                const allProducts = [...displayedProducts, ...remainingProducts];
                
                // Store all products for this category
                categoryAllProducts.set(categoryId, {
                    allProducts: allProducts,
                    currentDisplayCount: displayedProducts.length,
                    loadMoreBtn: loadMoreBtn
                });
            }
        });

        // Load More button functionality
        loadMoreButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                const currentCount = parseInt(this.getAttribute('data-current-count'));
                const totalCount = parseInt(this.getAttribute('data-total-count'));
                const remainingProducts = JSON.parse(this.getAttribute('data-remaining-products') || '[]');
                const displayedProducts = JSON.parse(this.getAttribute('data-displayed-products') || '[]');
                
                // Load next 3 products
                const nextProducts = remainingProducts.slice(0, 3);
                const newRemainingProducts = remainingProducts.slice(3);
                
                // Add new products to grid
                const productsGrid = document.getElementById(`productsGrid-${categoryId}`);
                
                nextProducts.forEach((product, index) => {
                    const productCard = createProductCard(product, currentCount + index);
                    productCard.classList.add('loaded-new');
                    productsGrid.appendChild(productCard);
                });
                
                // Update button data
                const newCurrentCount = currentCount + nextProducts.length;
                this.setAttribute('data-current-count', newCurrentCount);
                this.setAttribute('data-remaining-products', JSON.stringify(newRemainingProducts));
                
                // Update stored displayed products
                const newDisplayedProducts = displayedProducts.concat(nextProducts);
                this.setAttribute('data-displayed-products', JSON.stringify(newDisplayedProducts));
                
                // Update stored data in categoryAllProducts
                if (categoryAllProducts.has(categoryId)) {
                    const categoryData = categoryAllProducts.get(categoryId);
                    categoryData.currentDisplayCount = newCurrentCount;
                    categoryData.loadMoreBtn = this;
                }
                
                // Update button text or hide if no more products
                if (newRemainingProducts.length > 0) {
                    this.textContent = `Load More (Show ${Math.min(3, newRemainingProducts.length)} more)`;
                } else {
                    this.textContent = 'All Products Loaded';
                    this.disabled = true;
                    this.style.opacity = '0.6';
                }
                
                // Show Show Less button if more than initial count
                const showLessBtn = this.parentElement.querySelector('.show-less-btn');
                if (showLessBtn && newCurrentCount > 4) {
                    showLessBtn.style.display = 'inline-block';
                }
                
                // Reinitialize add to cart for new products
                setTimeout(() => {
                    setupAddToCartButtons();
                }, 100);
            });
        });

        // Show Less button functionality
        showLessButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                const productsGrid = document.getElementById(`productsGrid-${categoryId}`);
                const loadMoreContainer = document.getElementById(`loadMoreContainer-${categoryId}`);
                const loadMoreBtn = loadMoreContainer.querySelector('.load-more-btn');
                
                if (categoryAllProducts.has(categoryId)) {
                    const categoryData = categoryAllProducts.get(categoryId);
                    const allProducts = categoryData.allProducts;
                    
                    // Clear current grid
                    productsGrid.innerHTML = '';
                    
                    // Add only first 4 products (initial display)
                    const initialProducts = allProducts.slice(0, 4);
                    initialProducts.forEach((product, index) => {
                        const productCard = createProductCard(product, index);
                        productsGrid.appendChild(productCard);
                    });
                    
                    // Reset Load More button state
                    if (loadMoreBtn) {
                        const totalCount = allProducts.length;
                        const remainingProducts = allProducts.slice(4);
                        
                        loadMoreBtn.setAttribute('data-current-count', 4);
                        loadMoreBtn.setAttribute('data-total-count', totalCount);
                        loadMoreBtn.setAttribute('data-remaining-products', JSON.stringify(remainingProducts));
                        loadMoreBtn.setAttribute('data-displayed-products', JSON.stringify(initialProducts));
                        loadMoreBtn.textContent = `Load More (Show ${Math.min(3, remainingProducts.length)} more)`;
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.style.opacity = '1';
                        
                        // Update stored data
                        categoryData.currentDisplayCount = 4;
                        categoryData.loadMoreBtn = loadMoreBtn;
                    }
                    
                    // Hide Show Less button
                    this.style.display = 'none';
                    
                    // Reinitialize add to cart for remaining products
                    setTimeout(() => {
                        setupAddToCartButtons();
                    }, 100);
                }
            });
        });
    }

    // Helper function to create product card HTML
    function createProductCard(product, index) {
        const div = document.createElement('div');
        div.className = 'product-card';
        div.setAttribute('data-category', product.main_category_id || '');
        div.setAttribute('data-categories', JSON.stringify(product.categories ? product.categories.map(c => c.id) : []));
        div.setAttribute('data-price', product.price || '0');
        div.setAttribute('data-id', product.id || '');
        div.setAttribute('data-created', product.created_at ? new Date(product.created_at).getTime() : '');
        
        // Get product categories for display
        const productCategories = product.categories || [];
        const categoryNames = productCategories.map(cat => cat.name);
        const categoryText = categoryNames.length > 0 ? categoryNames.join(', ') : 'Uncategorized';
        
        // Check availability
        const isAvailable = product.is_available !== '0';
        const currentCityName = '<?= htmlspecialchars($current_city_name) ?>';
        const showAllCities = <?= $show_all_cities ? 'true' : 'false' ?>;
        
        // Generate image path correctly using PathConfig
        let imagePath = '<?= $pathConfig->getImagePath('placeholder.jpg', 'product') ?>';
        if (product.images && product.images[0] && product.images[0].image_path) {
            imagePath = '<?= $product_images_url ?>' + '/' + product.images[0].image_path;
        }
        
        // Ensure image path is absolute
        if (!imagePath.startsWith('http') && !imagePath.startsWith('//')) {
            imagePath = '<?= $base_url ?>' + (imagePath.startsWith('/') ? '' : '/') + imagePath;
        }
        
        const placeholderPath = '<?= $assets_path ?>/img/products/placeholder.jpg';
        
        div.innerHTML = `
            ${!isAvailable && !showAllCities ? `<div class="not-available-overlay">
                <span>Not Available in ${currentCityName}</span>
            </div>` : ''}
            
            <div class="product-badges">
                ${product.bulk_price && product.bulk_price < product.price ? 
                    `<span class="badge discount-badge">Bulk Discount</span>` : ''}
                ${product.stock_quantity <= 5 && product.stock_quantity > 0 ? 
                    `<span class="badge stock-badge">Low Stock</span>` : ''}
            </div>
            
            <a href="<?= $base_url ?>/product/${product.id}" class="product-image-link" 
               aria-label="View details for ${product.name}">
                <img src="${imagePath}" 
                    alt="${product.name} - Fresh Flowers Delivery Nepal" 
                    class="product-image" loading="lazy"
                    onerror="this.onerror=null; this.src='${placeholderPath}'">
            </a>
            <div class="product-info">
                <h3 class="product-title">${product.name}</h3>
                
                <div class="product-categories">
                    <small class="category-text">${categoryText}</small>
                </div>
                
                <div class="price-section">
                    <p class="product-price">Rs. ${parseFloat(product.price).toFixed(2)} 
                        <span class="price-unit">/${product.unit}</span>
                    </p>
                    ${product.bulk_price && product.bulk_price < product.price ? 
                        `<p class="product-bulk-price">
                            Rs. ${parseFloat(product.bulk_price).toFixed(2)} for bulk
                        </p>` : ''}
                </div>
                
                <div class="product-actions">
                    <a href="<?= $base_url ?>/product/${product.id}" class="btn btn-view">
                        <i class="fas fa-eye"></i> Details
                    </a>
                    <button class="btn btn-add-cart add-to-cart" 
                            data-id="${product.id}" 
                            data-name="${product.name}" 
                            data-price="${product.price}"
                            data-unit="${product.unit}"
                            data-image="${imagePath}"
                            ${product.stock_quantity <= 0 || !isAvailable ? 'disabled' : ''}>
                        <i class="fas fa-shopping-cart"></i>
                        ${!isAvailable && !showAllCities ? 'Not Available' : 
                          product.stock_quantity <= 0 ? 'Out of Stock' : 'Add to Cart'}
                    </button>
                </div>
            </div>
        `;
        
        return div;
    }

    // FIXED: Auto-apply mobile filters when changed (without needing Apply button)
    function setupMobileAutoApplyFilters() {
        // Auto-apply city filter on change
        mobileCityFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                if (this.checked) {
                    // AUTO APPLY CITY FILTER - RELOAD PAGE IMMEDIATELY
                    autoApplyCityFilter(this.value);
                }
            });
        });

        // Auto-apply category filters on change
        mobileCategoryFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                if (this.checked && this.value !== 'all') {
                    if (!activeFilters.categories.includes(this.value)) {
                        activeFilters.categories.push(this.value);
                    }
                } else if (!this.checked && this.value !== 'all') {
                    activeFilters.categories = activeFilters.categories.filter(cat => cat !== this.value);
                } else if (this.checked && this.value === 'all') {
                    // Clear all categories when "All Categories" is selected
                    activeFilters.categories = [];
                    mobileCategoryFilters.forEach(catFilter => {
                        if (catFilter.value !== 'all') {
                            catFilter.checked = false;
                        }
                    });
                }
                updateActiveFilters();
                filterProducts();
                updateFilterCount();
            });
        });

        // Auto-apply sort filters on change
        mobileSortFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                if (this.checked) {
                    activeFilters.sort = this.value;
                    filterProducts();
                }
            });
        });
    }

    // Apply mobile filters (for backward compatibility)
    if (mobileApplyFilters) {
        mobileApplyFilters.addEventListener('click', function() {
            // Get selected city
            const selectedCity = document.querySelector('input[name="mobile-city"]:checked');
            if (selectedCity) {
                // AUTO APPLY CITY FILTER - RELOAD PAGE
                autoApplyCityFilter(selectedCity.value);
                return; // Stop further execution as page will reload
            }
            
            // Get selected categories
            activeFilters.categories = [];
            const selectedCategories = document.querySelectorAll('input[name="mobile-category"]:checked');
            selectedCategories.forEach(cat => {
                if (cat.value !== 'all') {
                    activeFilters.categories.push(cat.value);
                }
            });
            
            // Update desktop category filters
            categoryFilters.forEach(filter => {
                filter.checked = activeFilters.categories.includes(filter.value);
            });
            
            // Get selected sort
            const selectedSort = document.querySelector('input[name="mobile-sort"]:checked');
            if (selectedSort) {
                activeFilters.sort = selectedSort.value;
                // Update desktop sort filter
                document.querySelector(`#sort-${selectedSort.value}`).checked = true;
            }
            
            updateActiveFilters();
            filterProducts();
            updateFilterCount();
            
            // Close modal
            closeMobileFiltersHandler();
        });
    }

    // Clear mobile filters
    if (mobileClearFilters) {
        mobileClearFilters.addEventListener('click', function() {
            // Reset mobile filters
            document.getElementById('mobile-city-all').checked = true;
            document.getElementById('mobile-category-all').checked = true;
            document.getElementById('mobile-sort-oldest').checked = true;
            
            // Reset desktop filters
            document.getElementById('city-all').checked = true;
            document.getElementById('category-all').checked = true;
            document.getElementById('sort-oldest').checked = true;
            
            activeFilters.city = 'all';
            activeFilters.categories = [];
            activeFilters.sort = 'oldest';
            
            updateActiveFilters();
            filterProducts();
            updateFilterCount();
        });
    }

    // City filter change (desktop) - AUTO APPLY
    cityFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked) {
                // AUTO APPLY CITY FILTER - RELOAD PAGE IMMEDIATELY
                autoApplyCityFilter(this.value);
            }
        });
    });

    // Category filter change (desktop)
    categoryFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked && this.value !== 'all') {
                if (!activeFilters.categories.includes(this.value)) {
                    activeFilters.categories.push(this.value);
                }
            } else if (!this.checked && this.value !== 'all') {
                activeFilters.categories = activeFilters.categories.filter(cat => cat !== this.value);
            } else if (this.checked && this.value === 'all') {
                // Clear all categories when "All Categories" is selected
                activeFilters.categories = [];
                categoryFilters.forEach(catFilter => {
                    if (catFilter.value !== 'all') {
                        catFilter.checked = false;
                    }
                });
            }
            updateActiveFilters();
            filterProducts();
            updateFilterCount();
        });
    });

    // Sort filter change (desktop)
    sortFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked) {
                activeFilters.sort = this.value;
                filterProducts();
            }
        });
    });

    // Mobile sort filter
    if (mobileSortFilter) {
        mobileSortFilter.addEventListener('change', function() {
            activeFilters.sort = this.value;
            filterProducts();
        });
    }

    // Clear all filters (desktop)
    if (clearAllFilters) {
        clearAllFilters.addEventListener('click', function() {
            // Reset city to current
            document.getElementById('city-all').checked = true;
            activeFilters.city = 'all';
            
            // Reset categories
            document.getElementById('category-all').checked = true;
            activeFilters.categories = [];
            categoryFilters.forEach(filter => {
                if (filter.value !== 'all') {
                    filter.checked = false;
                }
            });
            
            // Reset sort
            document.getElementById('sort-oldest').checked = true;
            activeFilters.sort = 'oldest';
            
            updateActiveFilters();
            filterProducts();
            updateFilterCount();
        });
    }

    // Update active filters display
    function updateActiveFilters() {
        activeFiltersContainer.innerHTML = '';
        
        // City filter
        if (activeFilters.city !== 'all') {
            const cityName = document.querySelector(`#city-${activeFilters.city} + label`).textContent;
            addActiveFilter('city', activeFilters.city, `City: ${cityName}`);
        }
        
        // Category filters
        activeFilters.categories.forEach(categoryId => {
            const categoryName = document.querySelector(`#category-${categoryId} + label`).textContent;
            addActiveFilter('category', categoryId, `Category: ${categoryName}`);
        });
        
        // Show/hide active filters bar
        if (activeFiltersContainer.children.length > 0) {
            activeFiltersBar.style.display = 'flex';
        } else {
            activeFiltersBar.style.display = 'none';
        }
    }

    function addActiveFilter(type, value, label) {
        const filterElement = document.createElement('div');
        filterElement.className = 'active-filter';
        filterElement.innerHTML = `
            <span>${label}</span>
            <button type="button" class="remove-filter" data-type="${type}" data-value="${value}">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        const removeBtn = filterElement.querySelector('.remove-filter');
        removeBtn.addEventListener('click', function() {
            removeFilter(type, value);
        });
        
        activeFiltersContainer.appendChild(filterElement);
    }

    function removeFilter(type, value) {
        if (type === 'city') {
            document.getElementById('city-all').checked = true;
            activeFilters.city = 'all';
        } else if (type === 'category') {
            activeFilters.categories = activeFilters.categories.filter(cat => cat !== value);
            document.getElementById(`category-${value}`).checked = false;
            
            // If no categories selected, check "All Categories"
            if (activeFilters.categories.length === 0) {
                document.getElementById('category-all').checked = true;
            }
        }
        
        updateActiveFilters();
        filterProducts();
        updateFilterCount();
    }

    // Filter products by category
    function filterProducts() {
        const categorySections = document.querySelectorAll('.category-section, .subcategory-section');
        let visibleCount = 0;

        categorySections.forEach(section => {
            const categoryId = section.getAttribute('data-category-id') || section.getAttribute('data-subcategory-id');
            const isMainCategory = section.classList.contains('main-category');
            const shouldShow = shouldShowCategory(categoryId, isMainCategory);
            
            if (shouldShow) {
                section.style.display = 'block';
                visibleCount += countVisibleProductsInSection(section);
            } else {
                section.style.display = 'none';
            }
        });

        // Update results count
        resultsCount.textContent = `${visibleCount} products found`;
    }

    function shouldShowCategory(categoryId, isMainCategory) {
        // Always show if no category filters are selected
        if (activeFilters.categories.length === 0) {
            return true;
        }
        
        // Check if this category is in the selected filters
        return activeFilters.categories.includes(categoryId);
    }

    function countVisibleProductsInSection(section) {
        const productCards = section.querySelectorAll('.product-card');
        let visibleCount = 0;
        
        productCards.forEach(card => {
            // Check city filter
            if (activeFilters.city !== 'all') {
                const cardCity = card.getAttribute('data-city');
                if (cardCity !== activeFilters.city && cardCity !== 'all') {
                    return;
                }
            }
            
            visibleCount++;
        });
        
        return visibleCount;
    }

    // Update filter count badge
    function updateFilterCount() {
        let count = 0;
        if (activeFilters.city !== 'all') count++;
        count += activeFilters.categories.length;
        mobileFilterCount.textContent = count;
        
        if (count > 0) {
            mobileFilterCount.style.display = 'inline-flex';
        } else {
            mobileFilterCount.style.display = 'none';
        }
    }

    // Add to cart functionality
    function setupAddToCartButtons() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        const baseUrl = '<?= $base_url ?>';
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) return;
                
                const productData = {
                    product_id: this.getAttribute('data-id'),
                    product_name: this.getAttribute('data-name'),
                    price: this.getAttribute('data-price'),
                    unit: this.getAttribute('data-unit'),
                    image: this.getAttribute('data-image'),
                    quantity: 1
                };
                
                const formData = new FormData();
                formData.append('product_id', productData.product_id);
                formData.append('product_name', productData.product_name);
                formData.append('price', productData.price);
                formData.append('unit', productData.unit);
                formData.append('image', productData.image);
                formData.append('quantity', productData.quantity);
                
                fetch(baseUrl + '/cart/add', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const originalText = this.innerHTML;
                        this.innerHTML = "<i class='fas fa-check'></i> Added";
                        this.classList.add('added');
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.classList.remove('added');
                        }, 2000);
                        
                        updateCartCount();
                    } else {
                        alert('Failed to add to cart: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    }

    function updateCartCount() {
        const baseUrl = '<?= $base_url ?>';
        fetch(baseUrl + '/cart/count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCountElements = document.querySelectorAll('.cart-count, .cart-badge');
                    cartCountElements.forEach(element => {
                        element.textContent = data.count;
                        if (data.count > 0) {
                            element.style.display = 'inline-block';
                        }
                    });
                }
            })
            .catch(error => console.error('Error updating cart count:', error));
    }

    // Initialize
    initializeFilters();
    setupMobileAutoApplyFilters();
    setupLoadMoreButtons();
    setupAddToCartButtons();
    setupDirectCheckoutForm();  // Initialize direct checkout form

    // Contact modal
    const modal = document.getElementById("contactModal");
    const btn = document.getElementById("contactBtn");
    const span = document.getElementsByClassName("close")[0];
    
    if (btn) btn.onclick = function() { modal.style.display = "block"; }
    if (span) span.onclick = function() { modal.style.display = "none"; }
    if (modal) window.onclick = function(event) {
        if (event.target == modal) modal.style.display = "none";
    }
});
</script>

<style> /* MODIFIED: Load More and Show Less buttons for mobile optimization */
.load-more-container {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 15px;
    align-items: center;
}

/* Load More and Show Less buttons - Mobile optimized */
.btn-load-more,
.btn-show-less {
    display: inline-block;
    margin: 0;
    padding: 10px 20px;
    border: none;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    min-width: 140px;
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    position: relative;
    z-index: 1;
}

/* Load More Button */
.btn-load-more {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(76, 175, 80, 0.3);
}

.btn-load-more:hover {
    background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
}

.btn-load-more:active {
    transform: translateY(0);
}

.btn-load-more:disabled {
    background: linear-gradient(135deg, #cccccc 0%, #999999 100%);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Show Less Button */
.btn-show-less {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.3);
}

.btn-show-less:hover {
    background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
}

.btn-show-less:active {
    transform: translateY(0);
}

/* Hover effect for both buttons */
.btn-load-more:before,
.btn-show-less:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
    z-index: -1;
}

.btn-load-more:hover:before,
.btn-show-less:hover:before {
    left: 100%;
}

/* Mobile-specific optimizations */
@media (max-width: 768px) {
    .load-more-container {
        margin: 20px 0;
        padding: 15px 10px;
        gap: 12px;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
    }
    
    .btn-load-more,
    .btn-show-less {
        padding: 8px 16px;
        font-size: 13px;
        min-width: 120px;
        max-width: 140px;
        flex: 1;
        margin: 0;
    }
    
    /* Ensure both buttons stay in same row */
    .btn-load-more,
    .btn-show-less {
        display: inline-block;
        margin: 0 5px;
    }
    
    /* When only one button is visible, center it */
    .load-more-container:has(:only-child) {
        justify-content: center;
    }
    
    .load-more-container:has(:only-child) .btn-load-more,
    .load-more-container:has(:only-child) .btn-show-less {
        max-width: 180px;
        min-width: 150px;
    }
}

@media (max-width: 480px) {
    .load-more-container {
        margin: 15px 0;
        padding: 12px 8px;
        gap: 10px;
    }
    
    .btn-load-more,
    .btn-show-less {
        padding: 7px 14px;
        font-size: 12px;
        min-width: 110px;
        max-width: 130px;
        border-radius: 20px;
    }
}

@media (max-width: 360px) {
    .load-more-container {
        margin: 12px 0;
        padding: 10px 6px;
        gap: 8px;
    }
    
    .btn-load-more,
    .btn-show-less {
        padding: 6px 12px;
        font-size: 11px;
        min-width: 100px;
        max-width: 115px;
    }
    
    /* For very small screens, ensure buttons don't wrap */
    .load-more-container {
        flex-wrap: nowrap;
        overflow-x: auto;
        justify-content: space-between;
    }
    
    .btn-load-more,
    .btn-show-less {
        flex-shrink: 0;
    }
}

/* Extra small screens - keep buttons readable */
@media (max-width: 320px) {
    .load-more-container {
        justify-content: space-around;
    }
    
    .btn-load-more,
    .btn-show-less {
        padding: 5px 10px;
        font-size: 10px;
        min-width: 95px;
        max-width: 105px;
    }
}

/* Animation for newly loaded products */
.product-card.loaded-new {
    animation: fadeInUp 0.5s ease forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading spinner for load more */
.load-more-loading {
    display: none;
    text-align: center;
    margin: 20px 0;
}

.load-more-loading.active {
    display: block;
}

.load-more-loading .spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #4CAF50;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
 
/* Advanced E-commerce Filter System */
.advanced-filter-system {
    margin-bottom: 30px;
}

/* Mobile Filter Header - FIXED: Both buttons in same row */
.filter-mobile-header {
    display: none;
    padding: 15px 0;
    border-bottom: 1px solid #e0e0e0;
    margin-bottom: 20px;
}

.mobile-filter-row {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.filter-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #4CAF50;
    color: white;
    padding: 12px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    flex: 1;
    min-width: 0;
}

.filter-count {
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.mobile-sort {
    flex: 1;
    min-width: 0;
}

.sort-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
    font-size: 14px;
    min-width: 0;
}

/* Mobile Filter Modal Overlay */
.filter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1001;
    display: none;
}

.filter-overlay.active {
    display: block;
}

/* Mobile Filter Modal - Reduced Width */
.mobile-filter-modal {
    position: fixed;
    top: 0;
    left: -100%;
    width: 85%;
    max-width: 300px; /* Reduced width */
    height: 100vh;
    background: white;
    z-index: 1002;
    transition: left 0.3s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.mobile-filter-modal.active {
    left: 0;
}

.mobile-filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 15px; /* Reduced padding */
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.mobile-filter-header h3 {
    margin: 0;
    font-size: 16px; /* Smaller font */
    color: #333;
}

.close-filters {
    background: none;
    border: none;
    font-size: 18px; /* Smaller close button */
    color: #666;
    cursor: pointer;
    padding: 5px;
}

.mobile-filter-content {
    flex: 1;
    overflow-y: auto;
    padding: 15px; /* Reduced padding */
}

.mobile-filter-actions {
    display: flex;
    gap: 10px;
    padding: 15px; /* Reduced padding */
    border-top: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.mobile-filter-actions .btn {
    flex: 1;
    padding: 10px; /* Reduced padding */
    border: none;
    border-radius: 6px; /* Smaller radius */
    cursor: pointer;
    font-weight: 600;
    font-size: 13px; /* Smaller font */
}

.btn-clear {
    background: #f8f9fa;
    color: #666;
    border: 1px solid #ddd;
}

.btn-apply {
    background: #4CAF50;
    color: white;
}

/* MODIFIED: Filter Container Layout - 25% filter, 75% products */
.filter-container {
    display: grid;
    grid-template-columns: 25% 75%; /* 25% filter, 75% products */
    gap: 30px;
    align-items: start;
}

/* Filter Sidebar */
.filter-sidebar {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    position: sticky;
    top: 20px;
}

/* Make desktop filter sidebar independently scrollable like mobile */
.filter-sidebar {
    /* keep visual style above */
    max-height: calc(100vh - 40px); /* allow sidebar to fit viewport and leave small gap */
    overflow-y: auto;
    -webkit-overflow-scrolling: touch; /* smooth scrolling on touch devices */
    touch-action: auto;
    /* Subtle custom scrollbars */
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.12) transparent;
}

.filter-sidebar::-webkit-scrollbar {
    width: 8px;
}
.filter-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.filter-sidebar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.12);
    border-radius: 6px;
}
.filter-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.2);
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e0e0e0;
}

.filter-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.clear-all-filters {
    background: none;
    border: none;
    color: #4CAF50;
    cursor: pointer;
    font-size: 14px;
    text-decoration: underline;
}

.filter-group {
    margin-bottom: 25px;
}

.filter-title h4 {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #333;
    font-weight: 600;
}

.filter-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
}

.filter-option input[type="radio"],
.filter-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
}

.filter-option label {
    font-size: 14px;
    color: #555;
    cursor: pointer;
    margin: 0;
}

/* Main Content Area */
.filter-main-content {
    min-height: 500px;
}

/* Active Filters Bar */
.active-filters-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.active-filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.active-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid #e0e0e0;
    font-size: 13px;
}

.remove-filter {
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 2px;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.remove-filter:hover {
    background: #f0f0f0;
    color: #333;
}

.results-count {
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

/* MODIFIED: Enhanced Product Grid - 3 CARDS PER ROW ON DESKTOP */
.products-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* 3 cards per row */
    gap: 20px;
}

/* Mobile-specific grid for 2 cards per row */
@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    
    .product-card {
        margin: 0 !important;
        width: 100% !important;
    }
}

/* Small mobile optimization */
@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
}

/* Very small screens - ensure 2 cards */
@media (max-width: 360px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.product-badges {
    position: absolute;
    top: 8px;
    left: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 2;
}

.badge {
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    line-height: 1;
}

.discount-badge {
    background: #ff4444;
    color: white;
}

.stock-badge {
    background: #ff9800;
    color: white;
}

.product-image {
    width: 100%;
    height: 160px;
    object-fit: cover;
}

.product-info {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.product-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px 0;
    color: #333;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.price-section {
    margin-bottom: 8px;
}

.product-price {
    font-size: 16px;
    font-weight: 700;
    color: #2e7d32;
    margin: 0 0 2px 0;
}

.price-unit {
    font-size: 12px;
    color: #666;
    font-weight: 400;
}

.product-bulk-price {
    font-size: 11px;
    color: #d32f2f;
    margin: 0;
    font-weight: 500;
}

.stock-status {
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 12px;
}

.in-stock { color: #2e7d32; }
.low-stock { color: #ff9800; }
.out-of-stock { color: #d32f2f; }

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: auto;
}

.btn {
    padding: 8px 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s ease;
    flex: 1;
    justify-content: center;
    line-height: 1;
}

.btn-view {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #e0e0e0;
    font-size: 11px;
}

.btn-view:hover {
    background: #e9ecef;
    border-color: #ccc;
}

.btn-add-cart {
    background: #4CAF50;
    color: white;
    font-size: 11px;
}

.btn-add-cart:hover:not(:disabled) {
    background: #388E3C;
}

.btn-add-cart:disabled {
    background: #cccccc;
    cursor: not-allowed;
}

.btn-add-cart.added {
    background: #2E7D32;
}

/* MODIFIED: Category Titles WITHOUT vertical line/border */
.category-title {
    font-size: 28px;
    font-weight: 700;
    color: #2e7d32;
    margin: 30px 0 20px 0;
    padding-bottom: 10px;
    /* REMOVED: border-bottom: 3px solid #4CAF50; */
    position: relative;
}

/* REMOVED the ::after pseudo-element that created the vertical line animation */
.category-title::after {
    display: none;
}

/* REMOVED the @keyframes titleUnderline animation */

.subcategory-title {
    font-size: 22px;
    font-weight: 600;
    color: #333;
    margin: 25px 0 15px 0;
    padding-bottom: 8px;
    /* REMOVED: border-bottom: 2px solid #ddd; */
    position: relative;
}

/* REMOVED the ::after pseudo-element that created the vertical line */
.subcategory-title::after {
    display: none;
}

/* MODIFIED: Responsive Design for new layout */
@media (max-width: 1024px) {
    .filter-container {
        grid-template-columns: 30% 70%; /* Adjusted for tablet */
        gap: 20px;
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 cards per row on tablet */
        gap: 15px;
    }
    
    .category-title {
        font-size: 24px;
    }
    
    .subcategory-title {
        font-size: 20px;
    }
}

@media (max-width: 768px) {
    .filter-mobile-header {
        display: block;
    }
    
    .filter-container {
        grid-template-columns: 1fr;
    }
    
    .filter-sidebar {
        display: none;
    }
    
    /* MOBILE: Ensure 2 cards per row */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    
    .product-image {
        height: 140px;
    }
    
    .product-info {
        padding: 10px;
    }
    
    .product-title {
        font-size: 13px;
    }
    
    .product-price {
        font-size: 15px;
    }
    
    .btn {
        padding: 6px 8px;
        font-size: 10px;
    }
    
    /* Category titles mobile optimization */
    .category-title {
        font-size: 22px;
        margin: 25px 0 15px 0;
    }
    
    .subcategory-title {
        font-size: 18px;
        margin: 20px 0 12px 0;
    }
    
    /* FIXED: Mobile filter row layout */
    .mobile-filter-row {
        flex-direction: row;
        align-items: stretch;
    }
    
    .filter-toggle-btn,
    .mobile-sort {
        flex: 1;
        min-width: 0;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
    
    .filter-mobile-header {
        padding: 10px 0;
    }
    
    .mobile-filter-row {
        gap: 10px;
    }
    
    .filter-toggle-btn,
    .sort-select {
        padding: 10px 12px;
        font-size: 13px;
    }
    
    .active-filters-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .active-filters-container {
        justify-content: flex-start;
    }
    
    .product-image {
        height: 120px;
    }
    
    .product-info {
        padding: 8px;
    }
    
    .product-title {
        font-size: 12px;
    }
    
    .product-price {
        font-size: 14px;
    }
    
    .btn {
        padding: 5px 6px;
        font-size: 9px;
    }
    
    .category-title {
        font-size: 20px;
        margin: 20px 0 12px 0;
    }
    
    .subcategory-title {
        font-size: 16px;
        margin: 15px 0 10px 0;
    }
}

/* Very small mobile devices */
@media (max-width: 360px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    
    .product-image {
        height: 110px;
    }
    
    .product-info {
        padding: 6px;
    }
    
    .product-title {
        font-size: 11px;
    }
    
    .btn {
        padding: 4px 5px;
        font-size: 8px;
    }
    
    .mobile-filter-row {
        gap: 8px;
    }
    
    .filter-toggle-btn,
    .sort-select {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .category-title {
        font-size: 18px;
        margin: 15px 0 10px 0;
    }
    
    .subcategory-title {
        font-size: 14px;
        margin: 12px 0 8px 0;
    }
}

/* Fixed Buttons */
.fixed-order-btn { 
    position: fixed; 
    bottom: 78px !important; 
    right: 15px !important; 
    z-index: 1000 !important; 
}

.order-now-btn { 
    background: #e74c3c !important; 
    color: white; 
    padding: 12px !important; 
    border-radius: 50% !important; 
    font-size: 1.3rem !important; 
    box-shadow: 0 2px 10px rgba(231, 76, 60, 0.4); 
    transition: all 0.3s ease; 
    border: none; 
    cursor: pointer; 
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
}

.order-now-btn i {
    font-size: 1.3rem;
}

.order-now-btn:hover { 
    background: #c0392b !important; 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.5); 
}

.fixed-contact-btn { 
    position: fixed; 
    bottom: 140px !important; 
    right: 15px !important; 
    z-index: 1000 !important; 
}

.contact-now-btn { 
    background: #3498db !important; 
    color: white !important; 
    padding: 12px !important; 
    border-radius: 50% !important; 
    font-size: 1.3rem; 
    box-shadow: 0 2px 10px rgba(52, 152, 219, 0.4); 
    transition: all 0.3s ease; 
    border: none; 
    cursor: pointer; 
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
}

.contact-now-btn i {
    font-size: 1.3rem;
}

.contact-now-btn:hover { 
    background: #2980b9; 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.5); 
}

/* Modal Styles */
.modal {
    display: none; 
    position: fixed; 
    z-index: 1001; 
    left: 0; 
    top: 0; 
    width: 100%; 
    height: 100%; 
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe; 
    margin: 5% auto; 
    padding: 20px; 
    border-radius: 8px; 
    width: 90%; 
    max-width: 500px; 
    position: relative;
}

.close {
    color: #aaa; 
    float: right; 
    font-size: 28px; 
    font-weight: bold; 
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.contact-info {
    margin-top: 20px;
}

.contact-person {
    margin-bottom: 20px;
}

.contact-numbers {
    margin-bottom: 15px;
}

.phone-number {
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 10px; 
    padding: 10px; 
    background: #f9f9f9; 
    border-radius: 4px;
}

.call-btn {
    background: #4CAF50; 
    color: white; 
    padding: 5px 10px; 
    border-radius: 4px; 
    text-decoration: none;
}

/* Enhanced animations */
@keyframes blinkPulse {
    0%, 100% { 
        opacity: 1; 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
    }
    50% { 
        opacity: 0.9; 
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
    }
}

.order-now-btn, .contact-now-btn {
    animation: none;
}

.contact-now-btn {
    animation-delay: unset;
}

/* Not Available Overlay */
.not-available-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 12px;
}

.not-available-overlay span {
    color: white;
    font-weight: bold;
    text-align: center;
    padding: 8px;
    background: rgba(255,0,0,0.8);
    border-radius: 4px;
    font-size: 12px;
}

/* No Products Styling */
.no-products {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-products .suggestion {
    margin-top: 10px;
}

.no-products .link {
    color: #4CAF50;
    text-decoration: none;
}

.no-products .link:hover {
    text-decoration: underline;
}

/* Ensure proper mobile layout */
@media (max-width: 768px) {
    .container {
        padding: 0 10px;
    }
    
    .products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .product-card {
        width: 100% !important;
        margin: 0 !important;
    }
}
</style>