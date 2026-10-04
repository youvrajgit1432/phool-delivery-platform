<?php
// app/views/products/category-template.php
// Template for category pages to avoid code duplication

$pathConfig = PathConfig::getInstance();
$page_title = htmlspecialchars($category_name ?? 'Category', ENT_QUOTES, 'UTF-8') . " - Phool Delivery Nepal | Fresh Flowers Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');
$product_images_url = $pathConfig->get('product_images');

// Get current user's city preference
$current_city_id = $_SESSION['user_city_id'] ?? 1;
$current_city_name = $_SESSION['user_city_name'] ?? 'Banepa';
$show_all_cities = isset($_GET['city_filter']) && $_GET['city_filter'] === 'all';

// Get category ID from session if set
$current_category_id = $category_id ?? ($_SESSION['current_category_id'] ?? null);
$current_category_name = $category_name ?? ($_SESSION['current_category_name'] ?? 'Products');

// If category ID is provided, store it in session
if (isset($category_id) && $category_id) {
    $_SESSION['current_category_id'] = $category_id;
    $_SESSION['current_category_name'] = $category_name;
}

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
                $_SESSION['user_city_last_set'] = time();
                $show_all_cities = false;
            }
        } catch (Exception $e) {
            error_log("Error fetching city data: " . $e->getMessage());
        }
    }
}

// Fetch category hierarchy
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get main categories for navigation
    $main_categories_stmt = $db->prepare("
        SELECT * FROM categories 
        WHERE status = 'active' AND type = 'main' 
        ORDER BY sort_order ASC
    ");
    $main_categories_stmt->execute();
    $main_categories = $main_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Localize category names
    foreach ($main_categories as &$cat) {
        $cat['name'] = LanguageHelper::getLocalizedText($cat, 'name');
    }
    unset($cat);
    
    // Get current category details
    $current_category = null;
    if ($current_category_id) {
        $cat_stmt = $db->prepare("SELECT * FROM categories WHERE id = ? AND status = 'active'");
        $cat_stmt->execute([$current_category_id]);
        $current_category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_category) {
            $current_category['name'] = LanguageHelper::getLocalizedText($current_category, 'name');
        }
    }
    
    // Get subcategories for current category (used for filters)
    $subcategories = [];
    try {
        if ($current_category_id) {
            // Determine which parent_id to use: if current category is a subcategory,
            // show its siblings (children of its parent). Otherwise show children of current category.
            $parent_id_for_filter = $current_category_id;
            if (isset($current_category['type']) && $current_category['type'] === 'subcategory' && !empty($current_category['parent_id'])) {
                $parent_id_for_filter = $current_category['parent_id'];
            }

            $subcat_stmt = $db->prepare(
                "SELECT * FROM categories WHERE status = 'active' AND type = 'subcategory' AND parent_id = ? ORDER BY sort_order ASC"
            );
            $subcat_stmt->execute([$parent_id_for_filter]);
            $subcategories = $subcat_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Localize subcategory names
            foreach ($subcategories as &$subcat) {
                $subcat['name'] = LanguageHelper::getLocalizedText($subcat, 'name');
            }
            unset($subcat);
        }
    } catch (Exception $e) {
        error_log("Error fetching subcategories for filters: " . $e->getMessage());
        $subcategories = [];
    }
    
} catch (Exception $e) {
    $main_categories = [];
    $current_category = null;
    $subcategories = [];
    error_log("Error fetching categories: " . $e->getMessage());
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

// Create a map of category IDs to names for easy lookup
$category_map = [];
try {
    $all_categories_stmt = $db->prepare("SELECT id, name_en, name_ne, parent_id, type FROM categories WHERE status = 'active'");
    $all_categories_stmt->execute();
    $all_categories = $all_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_categories as $cat) {
        $category_map[$cat['id']] = [
            'name' => LanguageHelper::getLocalizedText($cat, 'name'),
            'parent_id' => $cat['parent_id'],
            'type' => $cat['type']
        ];
    }
    
    // Make category_map available globally for processProducts function
    $GLOBALS['category_map'] = $category_map;
    
} catch (Exception $e) {
    $category_map = [];
    $GLOBALS['category_map'] = [];
    error_log("Error fetching all categories: " . $e->getMessage());
}

// Fetch products for current category
$products = [];
$products_count = 0;
$products_by_subcategory = [];

if ($current_category_id) {
    try {
        // Fetch main category products
        $products = fetchProductsByCategory($current_category_id, $current_city_id, $show_all_cities, $db);
        $products_count = count($products);
        
        // Fetch products by subcategory
        foreach ($subcategories as $subcategory) {
            $subcategory_id = $subcategory['id'];
            $subcategory_products = fetchProductsByCategory($subcategory_id, $current_city_id, $show_all_cities, $db);
            $products_by_subcategory[$subcategory_id] = [
                'info' => $subcategory,
                'products' => $subcategory_products,
                'count' => count($subcategory_products)
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching products: " . $e->getMessage());
    }
}

// Function to fetch products by category - FIXED VERSION
function fetchProductsByCategory($category_id, $current_city_id, $show_all_cities, $db) {
    if (!$show_all_cities && $current_city_id) {
        // FIXED: Corrected SQL query - Simplified to match logic in products page
        $product_query = "
                 SELECT DISTINCT p.*, pi.image_path, pi.is_primary,
                     COALESCE(pca.is_available, 1) as is_available,
                     pmq.minimum_quantity as city_minimum_quantity,
                     dc.city_name as available_city,
                     dc.id as available_city_id
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
            LEFT JOIN product_categories pc ON p.id = pc.product_id
            LEFT JOIN product_city_availability pca ON p.id = pca.product_id AND pca.city_id = ?
            LEFT JOIN product_minimum_quantities pmq ON p.id = pmq.product_id AND pmq.city_id = ?
            LEFT JOIN delivery_cities dc ON dc.id = ?
            WHERE p.status = 'active' 
            AND pc.category_id = ?
            AND (pca.is_available IS NULL OR pca.is_available = 1)
            ORDER BY p.created_at ASC
        ";
        
        $query_params = [$current_city_id, $current_city_id, $current_city_id, $category_id];
        
        $product_stmt = $db->prepare($product_query);
        $product_stmt->execute($query_params);
        $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Show all products from all cities
        $product_query = "
                 SELECT DISTINCT p.*, pi.image_path, pi.is_primary,
                     1 as is_available,
                     NULL as city_minimum_quantity,
                     NULL as available_city,
                     NULL as available_city_id
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
            LEFT JOIN product_categories pc ON p.id = pc.product_id
            WHERE p.status = 'active'
            AND pc.category_id = ?
            ORDER BY p.created_at ASC
        ";
        
        $query_params = [$category_id];
        
        $product_stmt = $db->prepare($product_query);
        $product_stmt->execute($query_params);
        $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return processProducts($products, $db);
}

// Function to process products
function processProducts($products, $db) {
    if (empty($products)) return [];
    
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

// Calculate display counts
// Show 10 products initially per category/subcategory, then load in batches of 10
$initial_display_count = 10;
$load_more_count = 10;

// SEO Meta Tags
$meta_description = "Buy fresh " . ($current_category_name ?? 'flowers') . " for Tihar, Dashain, weddings, puja. Same-day delivery in Banepa, Bhaktapur, Kathmandu. Direct from farmers to your home.";
$meta_keywords = strtolower($current_category_name ?? 'flowers') . " delivery Nepal, fresh " . ($current_category_name ?? 'flowers') . ", online " . ($current_category_name ?? 'flowers') . " shopping, same day delivery, farm fresh";

?>

<!-- Schema.org Structured Data for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "<?= addslashes($current_category_name) ?> - Phool Delivery Nepal",
  "description": "Fresh <?= addslashes($current_category_name) ?> delivery service in Banepa, Bhaktapur and Kathmandu.",
  "url": "<?= $current_page ?>",
  "mainEntity": {
    "@type": "ItemList",
    "itemListElement": [
      <?php foreach($products as $index => $product): ?>
      {
        "@type": "ListItem",
        "position": <?= $index + 1 ?>,
        "item": {
          "@type": "Product",
          "name": "<?= addslashes($product['name']) ?>",
          "description": "<?= addslashes($product['description']) ?>",
          "offers": {
            "@type": "Offer",
            "price": "<?= $product['price'] ?>",
            "priceCurrency": "NPR",
            "availability": "https://schema.org/<?= $product['stock_quantity'] > 0 ? 'InStock' : 'OutOfStock' ?>"
          }
        }
      }<?= $index < count($products)-1 ? ',' : '' ?>
      <?php endforeach; ?>
    ]
  }
}
</script>

<div class="container">
    <!-- Category Header -->
    <div class="category-header" data-aos="fade-up">
        <p class="category-description">
            Explore our collection of fresh <?= strtolower($current_category_name) ?> for all occasions. 
            Same-day delivery available in Banepa, Bhaktapur, and Kathmand
        </p>
    
    </div>
    
    <!-- Advanced E-commerce Filter System -->
    <div class="advanced-filter-system" data-aos="fade-up" data-aos-delay="150">
        <!-- Mobile Filter Header -->
        <div class="filter-mobile-header">
            <div class="mobile-filter-row">
                <button class="filter-toggle-btn" id="mobileFilterToggle">
                    <i class="fas fa-sliders-h"></i>
                    <span>Filters</span>
                    <span class="filter-count" id="mobileFilterCount">0</span>
                </button>
                
                <div class="mobile-sort">
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
                <div class="filter-group">
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

                <!-- Subcategory Filter (if available) -->
                <?php if (!empty($subcategories)): ?>
                <div class="filter-group">
                    <div class="filter-title">
                        <h4>Subcategories</h4>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" id="mobile-subcategory-all" name="mobile-subcategory" value="all" checked>
                            <label for="mobile-subcategory-all">All Subcategories</label>
                        </div>
                        <?php foreach ($subcategories as $subcategory): ?>
                        <div class="filter-option">
                            <input type="checkbox" id="mobile-subcategory-<?= $subcategory['id'] ?>" name="mobile-subcategory" value="<?= $subcategory['id'] ?>">
                            <label for="mobile-subcategory-<?= $subcategory['id'] ?>"><?= htmlspecialchars($subcategory['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sort Options -->
                <div class="filter-group">
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
                <button class="btn btn-clear" id="mobileClearFilters">Clear All</button>
                <button class="btn btn-apply" id="mobileApplyFilters">Apply Filters</button>
            </div>
        </div>

        <!-- Desktop Filter Layout -->
        <div class="filter-container">
            <!-- Left Sidebar Filters for Desktop -->
            <div class="filter-sidebar">
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Filters</h3>
                        <button class="clear-all-filters" id="clearAllFilters">Clear All</button>
                    </div>
                    
                    <!-- City Filter -->
                    <div class="filter-group">
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

                    <!-- Subcategory Filter (if available) -->
                    <?php if (!empty($subcategories)): ?>
                    <div class="filter-group">
                        <div class="filter-title">
                            <h4>Subcategories</h4>
                        </div>
                        <div class="filter-options">
                            <div class="filter-option">
                                <input type="radio" id="subcategory-all" name="subcategory" value="all" checked>
                                <label for="subcategory-all">All Subcategories</label>
                            </div>
                            <?php foreach ($subcategories as $subcategory): ?>
                            <div class="filter-option">
                                <input type="checkbox" id="subcategory-<?= $subcategory['id'] ?>" name="subcategory" value="<?= $subcategory['id'] ?>">
                                <label for="subcategory-<?= $subcategory['id'] ?>"><?= htmlspecialchars($subcategory['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sort Options -->
                    <div class="filter-group">
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

            <!-- Main Content Area -->
            <div class="filter-main-content">
                <!-- Active Filters Bar -->
                <div class="active-filters-bar" id="activeFiltersBar">
                    <div class="active-filters-container" id="activeFiltersContainer">
                        <!-- Active filters will appear here -->
                    </div>
                    <div class="results-count">
                        <span id="resultsCount"><?= $products_count ?> products found</span>
                    </div>
                </div>

                <!-- Products Display -->
                <div class="products-by-category" id="productsByCategory">
                    <?php if (empty($products)): ?>
                        <div class="no-products" data-aos="fade-up">
                            <p><?= LanguageHelper::t('no_products_found', 'No products found in this category.') ?></p>
                            <?php if ($current_city_id && !$show_all_cities): ?>
                                <p class="suggestion">
                                    Try <a href="?city_filter=all" class="link">viewing products from all cities</a>
                                </p>
                            <?php else: ?>
                                <p class="suggestion">
                                    Check other categories or visit our <a href="<?= $base_url ?>/products" class="link">main products page</a>.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Main Category Products -->
                        <div class="category-products">
                            <!-- Display only 4 products initially -->
                            <?php 
                            $displayed_products = array_slice($products, 0, $initial_display_count);
                            $remaining_products = array_slice($products, $initial_display_count);
                            $remaining_count = count($remaining_products);
                            ?>
                            
                            <div class="products-grid" id="productsGrid-main"
                                 data-current-count="<?= count($displayed_products) ?>"
                                 data-total-count="<?= $products_count ?>"
                                 data-remaining-products='<?= htmlspecialchars(json_encode($remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                 data-displayed-products='<?= htmlspecialchars(json_encode($displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                <?php foreach ($displayed_products as $index => $product): 
                                    $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $current_city_id, $pathConfig);
                                    echo $product_card;
                                endforeach; ?>
                            </div>

                            <?php if ($products_count > $initial_display_count): ?>
                                <div class="infinite-sentinel" data-category-id="main"></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Subcategories (if available in main category only) -->
                        <?php if (!empty($products_by_subcategory) && isset($current_category['type']) && $current_category['type'] === 'main'): ?>
                            <?php foreach ($products_by_subcategory as $subcat_id => $subcat_data): 
                                if ($subcat_data['count'] > 0): ?>
                                    <div class="subcategory-section" data-subcategory-id="<?= $subcat_id ?>">
                                        <h3 class="subcategory-title" data-aos="fade-up" data-aos-delay="250">
                                            <?= htmlspecialchars($subcat_data['info']['name']) ?>
                                        </h3>
                                        
                                        <div class="subcategory-products">
                                            <?php 
                                            $sub_displayed_products = array_slice($subcat_data['products'], 0, $initial_display_count);
                                            $sub_remaining_products = array_slice($subcat_data['products'], $initial_display_count);
                                            $sub_remaining_count = count($sub_remaining_products);
                                            ?>
                                            
                                            <div class="products-grid" id="productsGrid-<?= $subcat_id ?>"
                                                 data-current-count="<?= count($sub_displayed_products) ?>"
                                                 data-total-count="<?= $subcat_data['count'] ?>"
                                                 data-remaining-products='<?= htmlspecialchars(json_encode($sub_remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                                 data-displayed-products='<?= htmlspecialchars(json_encode($sub_displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                                <?php foreach ($sub_displayed_products as $index => $product): 
                                                    $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $current_city_id, $pathConfig);
                                                    echo $product_card;
                                                endforeach; ?>
                                            </div>

                                            <?php if ($subcat_data['count'] > $initial_display_count): ?>
                                                <div class="infinite-sentinel" data-category-id="<?= $subcat_id ?>"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fixed-order-btn" data-aos="zoom-in" data-aos-delay="600">
    <form class="direct-checkout-form" method="POST" action="<?= $pathConfig->url('direct-checkout') ?>">
        <input type="hidden" name="product_slug" value="marigold">
        <input type="hidden" name="quantity" value="2">
        <button type="submit" class="btn order-now-btn">Order Now ðŸŽ‰</button>
    </form>
</div>

<div class="fixed-contact-btn" data-aos="zoom-in" data-aos-delay="700">
    <button class="btn contact-now-btn" id="contactBtn">Contact Us ðŸ“±</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize filter functionality
    initializeCategoryFilters();
    setupLoadMoreButtons();
    setupAddToCartButtons();
    
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

function initializeCategoryFilters() {
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
    
    // Filter inputs
    const cityFilters = document.querySelectorAll('input[name="city"]');
    const subcategoryFilters = document.querySelectorAll('input[name="subcategory"]');
    const sortFilters = document.querySelectorAll('input[name="sort"]');
    
    // Mobile filter inputs
    const mobileCityFilters = document.querySelectorAll('input[name="mobile-city"]');
    const mobileSubcategoryFilters = document.querySelectorAll('input[name="mobile-subcategory"]');
    const mobileSortFilters = document.querySelectorAll('input[name="mobile-sort"]');
    const mobileSortFilter = document.getElementById('mobile-sort-filter');
    
    // Active filters state
    let activeFilters = {
        city: '<?= $show_all_cities ? 'all' : $current_city_id ?>',
        subcategories: [],
        sort: 'oldest'
    };

    // Mobile filter modal functions
    function openMobileFilters() {
        mobileFilterModal.classList.add('active');
        filterOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileFiltersHandler() {
        mobileFilterModal.classList.remove('active');
        filterOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // AUTO APPLY CITY FILTER - RELOAD PAGE WITH NEW CITY
    function autoApplyCityFilter(cityId) {
        const currentUrl = new URL(window.location.href);
        
        // Remove existing city_filter parameter
        currentUrl.searchParams.delete('city_filter');
        
        // Add new city_filter parameter
        if (cityId === 'all') {
            currentUrl.searchParams.set('city_filter', 'all');
        } else {
            currentUrl.searchParams.set('city_filter', cityId);
        }
        
        // Remove other parameters that might interfere
        currentUrl.searchParams.delete('page');
        currentUrl.searchParams.delete('sort');
        
        // Force reload the page with new city filter
        window.location.href = currentUrl.toString();
    }

    // Mobile filter modal toggle
    if (mobileFilterToggle) {
        mobileFilterToggle.addEventListener('click', openMobileFilters);
    }

    if (closeMobileFilters) {
        closeMobileFilters.addEventListener('click', closeMobileFiltersHandler);
    }

    if (filterOverlay) {
        filterOverlay.addEventListener('click', closeMobileFiltersHandler);
    }

    // Setup mobile auto apply filters
    function setupMobileAutoApplyFilters() {
        // Auto-apply city filter on change
        mobileCityFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                if (this.checked) {
                    autoApplyCityFilter(this.value);
                }
            });
        });

        // Auto-apply subcategory filters on change
        mobileSubcategoryFilters.forEach(filter => {
            filter.addEventListener('change', function() {
                if (this.checked && this.value !== 'all') {
                    if (!activeFilters.subcategories.includes(this.value)) {
                        activeFilters.subcategories.push(this.value);
                    }
                } else if (!this.checked && this.value !== 'all') {
                    activeFilters.subcategories = activeFilters.subcategories.filter(sub => sub !== this.value);
                } else if (this.checked && this.value === 'all') {
                    activeFilters.subcategories = [];
                    mobileSubcategoryFilters.forEach(subFilter => {
                        if (subFilter.value !== 'all') {
                            subFilter.checked = false;
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

    // Apply mobile filters
    if (mobileApplyFilters) {
        mobileApplyFilters.addEventListener('click', function() {
            const selectedCity = document.querySelector('input[name="mobile-city"]:checked');
            if (selectedCity) {
                autoApplyCityFilter(selectedCity.value);
                return;
            }
            
            // Get selected subcategories
            activeFilters.subcategories = [];
            const selectedSubcategories = document.querySelectorAll('input[name="mobile-subcategory"]:checked');
            selectedSubcategories.forEach(sub => {
                if (sub.value !== 'all') {
                    activeFilters.subcategories.push(sub.value);
                }
            });
            
            // Update desktop subcategory filters
            subcategoryFilters.forEach(filter => {
                filter.checked = activeFilters.subcategories.includes(filter.value);
            });
            
            // Get selected sort
            const selectedSort = document.querySelector('input[name="mobile-sort"]:checked');
            if (selectedSort) {
                activeFilters.sort = selectedSort.value;
                document.querySelector(`#sort-${selectedSort.value}`).checked = true;
            }
            
            updateActiveFilters();
            filterProducts();
            updateFilterCount();
            
            closeMobileFiltersHandler();
        });
    }

    // Clear mobile filters
    if (mobileClearFilters) {
        mobileClearFilters.addEventListener('click', function() {
            document.getElementById('mobile-city-all').checked = true;
            document.getElementById('mobile-subcategory-all').checked = true;
            document.getElementById('mobile-sort-oldest').checked = true;
            
            document.getElementById('city-all').checked = true;
            document.getElementById('subcategory-all').checked = true;
            document.getElementById('sort-oldest').checked = true;
            
            activeFilters.city = 'all';
            activeFilters.subcategories = [];
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
                autoApplyCityFilter(this.value);
            }
        });
    });

    // Subcategory filter change (desktop)
    subcategoryFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked && this.value !== 'all') {
                if (!activeFilters.subcategories.includes(this.value)) {
                    activeFilters.subcategories.push(this.value);
                }
            } else if (!this.checked && this.value !== 'all') {
                activeFilters.subcategories = activeFilters.subcategories.filter(sub => sub !== this.value);
            } else if (this.checked && this.value === 'all') {
                activeFilters.subcategories = [];
                subcategoryFilters.forEach(subFilter => {
                    if (subFilter.value !== 'all') {
                        subFilter.checked = false;
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
            document.getElementById('city-all').checked = true;
            activeFilters.city = 'all';
            
            document.getElementById('subcategory-all').checked = true;
            activeFilters.subcategories = [];
            subcategoryFilters.forEach(filter => {
                if (filter.value !== 'all') {
                    filter.checked = false;
                }
            });
            
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
            const cityName = document.querySelector(`#city-${activeFilters.city} + label`)?.textContent || 'Selected City';
            addActiveFilter('city', activeFilters.city, `City: ${cityName}`);
        }
        
        // Subcategory filters
        activeFilters.subcategories.forEach(subcategoryId => {
            const subcategoryName = document.querySelector(`#subcategory-${subcategoryId} + label`)?.textContent || 'Subcategory';
            addActiveFilter('subcategory', subcategoryId, `Subcategory: ${subcategoryName}`);
        });
        
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
        } else if (type === 'subcategory') {
            activeFilters.subcategories = activeFilters.subcategories.filter(sub => sub !== value);
            document.getElementById(`subcategory-${value}`).checked = false;
            
            if (activeFilters.subcategories.length === 0) {
                document.getElementById('subcategory-all').checked = true;
            }
        }
        
        updateActiveFilters();
        filterProducts();
        updateFilterCount();
    }

    // Filter products
    function filterProducts() {
        const productCards = document.querySelectorAll('.product-card');
        let visibleCount = 0;

        productCards.forEach(card => {
            let shouldShow = true;
            
            // Check city filter (compare as strings)
            if (activeFilters.city !== 'all') {
                const cardCity = String(card.getAttribute('data-city') || 'all');
                if (cardCity !== String(activeFilters.city) && cardCity !== 'all') {
                    shouldShow = false;
                }
            }

            // Check subcategory filter (compare as strings to avoid number/string mismatches)
            if (activeFilters.subcategories.length > 0) {
                const cardCategoriesRaw = JSON.parse(card.getAttribute('data-categories') || '[]');
                const cardCategories = cardCategoriesRaw.map(c => String(c));
                const hasMatchingSubcategory = activeFilters.subcategories.some(subId => 
                    cardCategories.includes(String(subId))
                );
                if (!hasMatchingSubcategory) {
                    shouldShow = false;
                }
            }
            
            if (shouldShow) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Sort products if needed
        if (activeFilters.sort !== 'oldest') {
            sortProducts(activeFilters.sort);
        }
        
        resultsCount.textContent = `${visibleCount} products found`;
        // Hide or show subcategory sections depending on whether they have visible products
        document.querySelectorAll('.subcategory-section').forEach(section => {
            const cards = section.querySelectorAll('.product-card');
            let anyVisible = false;
            cards.forEach(c => {
                if (c.style.display !== 'none') anyVisible = true;
            });
            section.style.display = anyVisible ? 'block' : 'none';
        });

        // Hide main category block if it has no visible products
        const mainCategoryBlock = document.querySelector('.category-products');
        if (mainCategoryBlock) {
            const mainGrid = document.getElementById('productsGrid-main');
            const mainCards = mainGrid ? Array.from(mainGrid.querySelectorAll('.product-card')) : [];
            const mainAnyVisible = mainCards.some(c => c.style.display !== 'none');
            mainCategoryBlock.style.display = mainAnyVisible ? 'block' : 'none';
        }
    }

    function sortProducts(sortType) {
        const productContainers = document.querySelectorAll('.products-grid');
        
        productContainers.forEach(container => {
            const cards = Array.from(container.querySelectorAll('.product-card'));
            
            cards.sort((a, b) => {
                switch (sortType) {
                    case 'newest':
                        return (b.getAttribute('data-created') || 0) - (a.getAttribute('data-created') || 0);
                    case 'price-low':
                        return (parseFloat(a.getAttribute('data-price')) || 0) - (parseFloat(b.getAttribute('data-price')) || 0);
                    case 'price-high':
                        return (parseFloat(b.getAttribute('data-price')) || 0) - (parseFloat(a.getAttribute('data-price')) || 0);
                    default:
                        return 0;
                }
            });
            
            // Reorder cards in container
            cards.forEach(card => container.appendChild(card));
        });
    }

    // Update filter count badge
    function updateFilterCount() {
        let count = 0;
        if (activeFilters.city !== 'all') count++;
        count += activeFilters.subcategories.length;
        mobileFilterCount.textContent = count;
        
        if (count > 0) {
            mobileFilterCount.style.display = 'inline-flex';
        } else {
            mobileFilterCount.style.display = 'none';
        }
    }

    // Initialize
    updateActiveFilters();
    filterProducts();
    updateFilterCount();
    setupMobileAutoApplyFilters();
}

function setupLoadMoreButtons() {
    const categoryAllProducts = new Map();

    // Initialize from products-grid data attributes
    document.querySelectorAll('.products-grid').forEach(grid => {
        const gridId = grid.id || '';
        // gridId is like productsGrid-<catId> or productsGrid-main
        const categoryId = gridId.replace('productsGrid-', '') || 'main';
        const displayedProducts = JSON.parse(grid.getAttribute('data-displayed-products') || '[]');
        const remainingProducts = JSON.parse(grid.getAttribute('data-remaining-products') || '[]');
        const totalCount = parseInt(grid.getAttribute('data-total-count') || displayedProducts.length + remainingProducts.length);

        categoryAllProducts.set(categoryId, {
            allProducts: [...displayedProducts, ...remainingProducts],
            currentDisplayCount: displayedProducts.length,
            displayedProducts: displayedProducts,
            remainingProducts: remainingProducts,
            totalCount: totalCount,
            gridElement: grid
        });
    });

    // IntersectionObserver-based infinite loader
    const observerOptions = {
        root: null,
        rootMargin: '600px',
        threshold: 0.01
    };

    // Batch size for infinite loading
    const BATCH_SIZE = 10;

    const sentinelCallback = (entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const sentinel = entry.target;
                const categoryId = sentinel.getAttribute('data-category-id') || 'main';
                loadMoreForCategory(categoryId);
            }
        });
    };

    const observer = new IntersectionObserver(sentinelCallback, observerOptions);
    document.querySelectorAll('.infinite-sentinel').forEach(s => observer.observe(s));

    function loadMoreForCategory(categoryId) {
        if (!categoryAllProducts.has(categoryId)) return;
        const categoryData = categoryAllProducts.get(categoryId);
        const remaining = categoryData.remainingProducts || [];
        if (remaining.length === 0) return;

        // Load next batch
        const nextProducts = remaining.slice(0, BATCH_SIZE);
        const newRemaining = remaining.slice(BATCH_SIZE);

        const productsGrid = categoryData.gridElement;
        const startIndex = categoryData.currentDisplayCount;
        nextProducts.forEach((product, idx) => {
            const productCard = createProductCard(product, startIndex + idx);
            productCard.classList.add('loaded-new');
            productsGrid.appendChild(productCard);
        });

        // Update stored data
        categoryData.currentDisplayCount += nextProducts.length;
        categoryData.remainingProducts = newRemaining;
        categoryData.displayedProducts = categoryData.displayedProducts.concat(nextProducts);

        // If no more remaining products, remove sentinel
        if (newRemaining.length === 0) {
            const sentinel = document.querySelector(`.infinite-sentinel[data-category-id="${categoryId}"]`);
            if (sentinel) sentinel.remove();
        }

        // Refresh AOS and reinit buttons
        if (typeof AOS !== 'undefined') AOS.refresh();
        setTimeout(() => setupAddToCartButtons(), 100);
    }
}

function createProductCard(product, index) {
    const div = document.createElement('div');
    div.className = 'product-card';
    div.setAttribute('data-category', product.main_category_id || '');
    div.setAttribute('data-categories', JSON.stringify(product.categories ? product.categories.map(c => c.id) : []));
    div.setAttribute('data-price', product.price || '0');
    div.setAttribute('data-id', product.id || '');
    div.setAttribute('data-created', product.created_at ? new Date(product.created_at).getTime() : '');
    // Set data-city to city id or 'all' so filters compare by id
    div.setAttribute('data-city', (product.available_city_id !== undefined && product.available_city_id !== null) ? String(product.available_city_id) : 'all');
    div.setAttribute('data-aos', 'zoom-in');
    div.setAttribute('data-aos-delay', (index % 6) * 100 + 200);
    
    const productCategories = product.categories || [];
    const categoryNames = productCategories.map(cat => cat.name);
    const categoryText = categoryNames.length > 0 ? categoryNames.join(', ') : 'Uncategorized';
    
    // FIXED: Correct availability check
    const isAvailable = product.is_available == 1 || product.is_available === 1 || product.is_available === '1' || product.is_available === true;
    const currentCityName = '<?= htmlspecialchars($current_city_name) ?>';
    const showAllCities = <?= $show_all_cities ? 'true' : 'false' ?>;
    
    let imagePath = '<?= $pathConfig->getImagePath('placeholder.jpg', 'product') ?>';
    if (product.images && product.images[0] && product.images[0].image_path) {
        imagePath = '<?= $product_images_url ?>' + '/' + product.images[0].image_path;
    }
    
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
</script>

<?php
// Function to generate product card HTML - FIXED VERSION
function generateProductCard($product, $index, $current_city_name, $show_all_cities, $current_city_id, $pathConfig) {
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
    
    $alt_text = htmlspecialchars($product['name']) . " - Fresh Flowers Delivery Nepal";
    
    // FIXED: Correct availability check
    $is_available_in_city = $show_all_cities || 
                          (isset($product['is_available']) && 
                          ($product['is_available'] == 1 || 
                           $product['is_available'] === 1 || 
                           $product['is_available'] === '1' || 
                           $product['is_available'] === true));
    
    $product_category_names = [];
    foreach ($product['categories'] as $cat) {
        $product_category_names[] = $cat['name'];
    }
    $category_text = !empty($product_category_names) ? implode(', ', $product_category_names) : 'Uncategorized';
    
    ob_start();
    ?>
        <div class="product-card" 
            data-category="<?= $product['main_category_id'] ?>" 
            data-categories="<?= htmlspecialchars(json_encode(array_column($product['categories'], 'id'))) ?>"
            data-price="<?= $product['price'] ?>" 
            data-id="<?= $product['id'] ?>" 
            data-created="<?= strtotime($product['created_at']) ?>"
            data-city="<?= $show_all_cities ? 'all' : ($product['available_city_id'] ?? $current_city_id) ?>"
            data-aos="zoom-in" 
            data-aos-delay="<?= ($index % 6) * 100 + 200 ?>">
        <?php if (!$is_available_in_city && !$show_all_cities): ?>
            <div class="not-available-overlay">
                <span>Not Available in <?= htmlspecialchars($current_city_name) ?></span>
            </div>
        <?php endif; ?>
        
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
                    <?php if (!$is_available_in_city && !$show_all_cities): ?>
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




<style>
/* Category Header Styles */
.category-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 5px solid #4CAF50;
}

.category-title {
    font-size: 2.5rem;
    color: #2c3e50;
    margin-bottom: 15px;
    font-weight: 700;
}

.category-description {
    font-size: 1.1rem;
    color: #5a6c7d;
    line-height: 1.6;
    margin-bottom: 20px;
    max-width: 800px;
}

.category-meta {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.product-count {
    background: #4CAF50;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

.delivery-city {
    background: #3498db;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Load More and Show Less buttons */
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

/* Mobile optimizations */
@media (max-width: 768px) {
    .category-header {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .category-title {
        font-size: 1.8rem;
    }
    
    .category-description {
        font-size: 1rem;
    }
    
    .load-more-container {
        margin: 20px 0;
        padding: 15px 10px;
        gap: 12px;
    }
    
    .btn-load-more,
    .btn-show-less {
        padding: 8px 16px;
        font-size: 13px;
        min-width: 120px;
        max-width: 140px;
    }
}

@media (max-width: 480px) {
    .category-header {
        padding: 15px;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
    
    .category-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
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

/* Subcategory section */
.subcategory-section {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #eee;
}

.subcategory-title {
    font-size: 1.8rem;
    color: #34495e;
    margin-bottom: 20px;
    padding-left: 15px;
    border-left: 4px solid #3498db;
}
  /* MODIFIED: Load More and Show Less buttons for mobile optimization */
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
    padding: 12px 18px !important; 
    border-radius: 50px !important; 
    font-size: 0.9rem !important; 
    box-shadow: 0 2px 10px rgba(231, 76, 60, 0.4); 
    transition: all 0.3s ease; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
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
    padding: 12px 18px !important; 
    border-radius: 50px !important; 
    font-size: 0.9rem; 
    box-shadow: 0 2px 10px rgba(52, 152, 219, 0.4); 
    transition: all 0.3s ease; 
    border: none; 
    cursor: pointer; 
    font-weight: 600; 
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
    animation: blinkPulse 2s infinite;
}

.contact-now-btn {
    animation-delay: 1s;
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
<style>
/* Make filter sidebar independently scrollable and sticky on desktop */
@media (min-width: 769px) {
    .filter-container {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    .filter-sidebar {
        width: 280px;
        flex: 0 0 280px;
    }

    .filter-sidebar .filter-section {
        position: sticky;
        top: 96px; /* leaves space for header */
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        padding-right: 8px;
        -webkit-overflow-scrolling: touch;
    }

    /* Light scrollbar styling */
    .filter-sidebar .filter-section::-webkit-scrollbar {
        width: 8px;
    }
    .filter-sidebar .filter-section::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.12);
        border-radius: 6px;
    }
}

/* On small screens keep default behavior (modal) */
@media (max-width: 768px) {
    .filter-sidebar {
        display: none;
    }
}
</style>