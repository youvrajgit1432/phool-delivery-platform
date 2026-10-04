<?php
// app/views/home/p.php

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
    
    $product_query .= " ORDER BY p.created_at ASC"; // Show old products first
    
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

// Calculate display counts - Show 5 products initially
$initial_display_count = 5; // Show first 5 products initially
$load_more_count = 5; // Load 5 more each time
?>

<div class="pc_container">
    <div class="pc_products-header">
        <h1><?= LanguageHelper::t('our_products', 'Explore More Products') ?></h1>
    </div>

    <div class="pc_products-by-category" id="pc_productsByCategory">
        <?php if (empty($all_products)): ?>
            <div class="pc_no-products">
                <p><?= LanguageHelper::t('no_products_found', 'No products found.') ?></p>
                <?php if ($current_city_id && !$show_all_cities): ?>
                    <p class="pc_suggestion">
                        Try <a href="?city_filter=all" class="pc_link">viewing products from all cities</a>
                    </p>
                <?php else: ?>
                    <p class="pc_suggestion">
                        Please select a city to see available products.
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($category_hierarchy as $main_category): 
                $main_category_id = $main_category['id'];
                $main_category_products = $products_by_category[$main_category_id] ?? [];
                $main_category_count = $products_count_by_category[$main_category_id] ?? 0;
                
                if ($main_category_count > 0): ?>
                    <div class="pc_category-section pc_main-category" data-category-id="<?= $main_category_id ?>">
                        <h2 class="pc_category-title"><?= htmlspecialchars($main_category['name']) ?></h2>
                        
                        <div class="pc_category-products">
                            <?php 
                            // Display only 5 products initially
                            $displayed_products = array_slice($main_category_products, 0, $initial_display_count);
                            $remaining_products = array_slice($main_category_products, $initial_display_count);
                            $remaining_count = count($remaining_products);
                            ?>
                            
                            <div class="pc_products-grid" id="pc_productsGrid-<?= $main_category_id ?>">
                                <?php foreach ($displayed_products as $index => $product): 
                                    $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $pathConfig);
                                    echo $product_card;
                                endforeach; ?>
                            </div>
                            
                            <?php if ($main_category_count > $initial_display_count): ?>
                                <div class="pc_load-more-container" id="pc_loadMoreContainer-<?= $main_category_id ?>">
                                    <?php if ($remaining_count > 0): ?>
                                        <button class="pc_btn pc_btn-load-more pc_load-more-btn" 
                                                            data-category-id="<?= $main_category_id ?>"
                                                            data-current-count="<?= count($displayed_products) ?>"
                                                            data-total-count="<?= $main_category_count ?>"
                                                            data-remaining-products='<?= htmlspecialchars(json_encode($remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                                            data-displayed-products='<?= htmlspecialchars(json_encode($displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                                Load More 
                                        </button>
                                    <?php endif; ?>
                                    <button class="pc_btn pc_btn-show-less pc_show-less-btn" 
                                                    style="display: none;"
                                                    data-category-id="<?= $main_category_id ?>">
                                            Show Less
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($main_category['subcategories'])): ?>
                            <?php foreach ($main_category['subcategories'] as $subcategory): 
                                $subcategory_id = $subcategory['id'];
                                $subcategory_products = $products_by_category[$subcategory_id] ?? [];
                                $subcategory_count = $products_count_by_category[$subcategory_id] ?? 0;
                                
                                if ($subcategory_count > 0): ?>
                                    <div class="pc_subcategory-section" data-subcategory-id="<?= $subcategory_id ?>">
                                        <h3 class="pc_subcategory-title"><?= htmlspecialchars($subcategory['name']) ?></h3>
                                        
                                        <div class="pc_subcategory-products">
                                            <?php 
                                            // Display only 5 products initially
                                            $sub_displayed_products = array_slice($subcategory_products, 0, $initial_display_count);
                                            $sub_remaining_products = array_slice($subcategory_products, $initial_display_count);
                                            $sub_remaining_count = count($sub_remaining_products);
                                            ?>
                                            
                                            <div class="pc_products-grid" id="pc_productsGrid-<?= $subcategory_id ?>">
                                                <?php foreach ($sub_displayed_products as $index => $product): 
                                                    $product_card = generateProductCard($product, $index, $current_city_name, $show_all_cities, $pathConfig);
                                                    echo $product_card;
                                                endforeach; ?>
                                            </div>
                                            
                                            <?php if ($subcategory_count > $initial_display_count): ?>
                                                <div class="pc_load-more-container" id="pc_loadMoreContainer-<?= $subcategory_id ?>">
                                                    <?php if ($sub_remaining_count > 0): ?>
                                                        <button class="pc_btn pc_btn-load-more pc_load-more-btn" 
                                                                                data-category-id="<?= $subcategory_id ?>"
                                                                                data-current-count="<?= count($sub_displayed_products) ?>"
                                                                                data-total-count="<?= $subcategory_count ?>"
                                                                                data-remaining-products='<?= htmlspecialchars(json_encode($sub_remaining_products), ENT_QUOTES, 'UTF-8') ?>'
                                                                                data-displayed-products='<?= htmlspecialchars(json_encode($sub_displayed_products), ENT_QUOTES, 'UTF-8') ?>'>
                                                                            Load More 
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="pc_btn pc_btn-show-less pc_show-less-btn" 
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
    <div class="pc_product-card" 
         data-category="<?= $product['main_category_id'] ?>" 
         data-categories="<?= htmlspecialchars(json_encode(array_column($product['categories'], 'id'))) ?>"
         data-price="<?= $product['price'] ?>" 
         data-id="<?= $product['id'] ?>" 
         data-created="<?= strtotime($product['created_at']) ?>"
         data-city="<?= $show_all_cities ? 'all' : $current_city_name ?>">
        <?php if (!$is_available_in_city): ?>
            <div class="pc_not-available-overlay">
                <span>Not Available in <?= htmlspecialchars($current_city_name) ?></span>
            </div>
        <?php endif; ?>
        
        <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="pc_product-image-link" aria-label="View details for <?= htmlspecialchars($product['name']) ?>">
            <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($product['name']) ?> - Fresh Flowers Delivery Nepal" class="pc_product-image" loading="lazy" onerror="this.onerror=null; this.src='<?= $pathConfig->get('assets') ?>/img/products/placeholder.jpg'">
        </a>
        <div class="pc_product-info">
            <h3 class="pc_product-title"><?= htmlspecialchars($product['name']) ?></h3>
            
            
            
            <div class="pc_price-section">
                <p class="pc_product-price">Rs. <?= number_format($product['price'], 2) ?> 
                    <span class="pc_price-unit">/<?= $product['unit'] ?></span>
                </p>
                <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                    <p class="pc_product-bulk-price">
                        Rs. <?= number_format($product['bulk_price'], 2) ?> for bulk
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="pc_product-actions">
                <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="pc_btn pc_btn-view">
                    <i class="fas fa-eye"></i> Details
                </a>
                <button class="pc_btn pc_btn-add-cart pc_add-to-cart" 
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
    const pc_baseUrl = '<?= $base_url ?>';
    
    // Store ALL products for each category for Show Less functionality
    const pc_categoryAllProducts = new Map();
    
    // Initialize Load More buttons
    pc_setupLoadMoreButtons();
    
    // Add to cart functionality
    pc_setupAddToCartButtons();

    // Enhanced Load More and Show Less functionality
    function pc_setupLoadMoreButtons() {
        // Store ALL products for each category when page loads
        document.querySelectorAll('.pc_category-section, .pc_subcategory-section').forEach(section => {
            const categoryId = section.getAttribute('data-category-id') || section.getAttribute('data-subcategory-id');
            const loadMoreBtn = section.querySelector('.pc_load-more-btn');
            
            if (loadMoreBtn) {
                // Get ALL products from data attributes
                const displayedProducts = JSON.parse(loadMoreBtn.getAttribute('data-displayed-products') || '[]');
                const remainingProducts = JSON.parse(loadMoreBtn.getAttribute('data-remaining-products') || '[]');
                const allProducts = [...displayedProducts, ...remainingProducts];
                
                // Store all products for this category
                pc_categoryAllProducts.set(categoryId, {
                    allProducts: allProducts,
                    currentDisplayCount: displayedProducts.length,
                    loadMoreBtn: loadMoreBtn
                });
            }
        });

        // Load More button functionality
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('pc_load-more-btn')) {
                const button = e.target;
                const categoryId = button.getAttribute('data-category-id');
                const currentCount = parseInt(button.getAttribute('data-current-count'));
                const remainingProducts = JSON.parse(button.getAttribute('data-remaining-products') || '[]');
                const displayedProducts = JSON.parse(button.getAttribute('data-displayed-products') || '[]');
                
                // Load next 5 products
                const nextProducts = remainingProducts.slice(0, 5);
                const newRemainingProducts = remainingProducts.slice(5);
                
                // Add new products to grid
                const productsGrid = document.getElementById(`pc_productsGrid-${categoryId}`);
                
                nextProducts.forEach((product, index) => {
                    const productCard = pc_createProductCard(product, currentCount + index);
                    productCard.classList.add('pc_loaded-new');
                    productsGrid.appendChild(productCard);
                });
                
                // Update button data
                const newCurrentCount = currentCount + nextProducts.length;
                button.setAttribute('data-current-count', newCurrentCount);
                button.setAttribute('data-remaining-products', JSON.stringify(newRemainingProducts));
                
                // Update stored displayed products
                const newDisplayedProducts = displayedProducts.concat(nextProducts);
                button.setAttribute('data-displayed-products', JSON.stringify(newDisplayedProducts));
                
                // Update stored data in pc_categoryAllProducts
                if (pc_categoryAllProducts.has(categoryId)) {
                    const categoryData = pc_categoryAllProducts.get(categoryId);
                    categoryData.currentDisplayCount = newCurrentCount;
                    categoryData.loadMoreBtn = button;
                }
                
                // Update button text or hide if no more products
                if (newRemainingProducts.length > 0) {
                    button.textContent = `Load More`;
                } else {
                    button.textContent = 'All Loaded';
                    button.disabled = true;
                    button.style.opacity = '0.6';
                }
                
                // Show Show Less button if more than initial count
                const showLessBtn = button.parentElement.querySelector('.pc_show-less-btn');
                if (showLessBtn && newCurrentCount > 5) {
                    showLessBtn.style.display = 'inline-block';
                }
                
                // Reinitialize add to cart for new products
                setTimeout(() => {
                    pc_setupAddToCartButtons();
                }, 100);
            }
        });

        // Show Less button functionality
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('pc_show-less-btn')) {
                const button = e.target;
                const categoryId = button.getAttribute('data-category-id');
                const productsGrid = document.getElementById(`pc_productsGrid-${categoryId}`);
                const loadMoreContainer = document.getElementById(`pc_loadMoreContainer-${categoryId}`);
                const loadMoreBtn = loadMoreContainer.querySelector('.pc_load-more-btn');
                
                if (pc_categoryAllProducts.has(categoryId)) {
                    const categoryData = pc_categoryAllProducts.get(categoryId);
                    const allProducts = categoryData.allProducts;
                    
                    // Clear current grid
                    productsGrid.innerHTML = '';
                    
                    // Add only first 5 products (initial display)
                    const initialProducts = allProducts.slice(0, 5);
                    initialProducts.forEach((product, index) => {
                        const productCard = pc_createProductCard(product, index);
                        productsGrid.appendChild(productCard);
                    });
                    
                    // Reset Load More button state
                    if (loadMoreBtn) {
                        const totalCount = allProducts.length;
                        const remainingProducts = allProducts.slice(5);
                        
                        loadMoreBtn.setAttribute('data-current-count', 5);
                        loadMoreBtn.setAttribute('data-total-count', totalCount);
                        loadMoreBtn.setAttribute('data-remaining-products', JSON.stringify(remainingProducts));
                        loadMoreBtn.setAttribute('data-displayed-products', JSON.stringify(initialProducts));
                        loadMoreBtn.textContent = `Load More `;
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.style.opacity = '1';
                        
                        // Update stored data
                        categoryData.currentDisplayCount = 5;
                        categoryData.loadMoreBtn = loadMoreBtn;
                    }
                    
                    // Hide Show Less button
                    button.style.display = 'none';
                    
                    // Reinitialize add to cart for remaining products
                    setTimeout(() => {
                        pc_setupAddToCartButtons();
                    }, 100);
                }
            }
        });
    }

    // Helper function to create product card HTML
    function pc_createProductCard(product, index) {
        const div = document.createElement('div');
        div.className = 'pc_product-card';
        div.setAttribute('data-category', product.main_category_id || '');
        div.setAttribute('data-id', product.id || '');
        
        // Get product categories
        const productCategories = product.categories || [];
        const categoryNames = productCategories.map(cat => cat.name);
        const categoryText = categoryNames.length > 0 ? categoryNames.join(', ') : 'Uncategorized';
        
        // Check availability
        const isAvailable = product.is_available !== '0';
        const currentCityName = '<?= htmlspecialchars($current_city_name) ?>';
        const showAllCities = <?= $show_all_cities ? 'true' : 'false' ?>;
        
        // Generate image path
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
            ${!isAvailable && !showAllCities ? `<div class="pc_not-available-overlay">
                <span>Not Available in ${currentCityName}</span>
            </div>` : ''}
            
            <a href="<?= $base_url ?>/product/${product.id}" class="pc_product-image-link" 
               aria-label="View details for ${product.name}">
                <img src="${imagePath}" 
                    alt="${product.name} - Fresh Flowers Delivery Nepal" 
                    class="pc_product-image" loading="lazy"
                    onerror="this.onerror=null; this.src='${placeholderPath}'">
            </a>
            <div class="pc_product-info">
                <h3 class="pc_product-title">${product.name}</h3>
                
                <div class="pc_product-categories">
                    <small class="pc_category-text">${categoryText}</small>
                </div>
                
                <div class="pc_price-section">
                    <p class="pc_product-price">Rs. ${parseFloat(product.price).toFixed(2)} 
                        <span class="pc_price-unit">/${product.unit}</span>
                    </p>
                    ${product.bulk_price && product.bulk_price < product.price ? 
                        `<p class="pc_product-bulk-price">
                            Rs. ${parseFloat(product.bulk_price).toFixed(2)} for bulk
                        </p>` : ''}
                </div>
                
                <div class="pc_product-actions">
                    <a href="<?= $base_url ?>/product/${product.id}" class="pc_btn pc_btn-view">
                        <i class="fas fa-eye"></i> Details
                    </a>
                    <button class="pc_btn pc_btn-add-cart pc_add-to-cart" 
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

    // Add to cart functionality
    function pc_setupAddToCartButtons() {
        const addToCartButtons = document.querySelectorAll('.pc_add-to-cart');
        
        addToCartButtons.forEach(button => {
            // Remove existing event listeners to avoid duplicates
            button.replaceWith(button.cloneNode(true));
        });
        
        // Re-select buttons after cloning
        const newAddToCartButtons = document.querySelectorAll('.pc_add-to-cart');
        
        newAddToCartButtons.forEach(button => {
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
                
                // Assuming cart logic is accessible via the main page's logic
                fetch(pc_baseUrl + '/cart/add', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const originalText = this.innerHTML;
                        this.innerHTML = "<i class='fas fa-check'></i> Added";
                        this.classList.add('pc_added');
                        
                        // IMPORTANT: Triggering the main home page's cart update logic if it exists
                        if (typeof window.updateCartCount === 'function') {
                            window.updateCartCount();
                        }
                        if (typeof window.showCartNotification === 'function') {
                            window.showCartNotification();
                        }

                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.classList.remove('pc_added');
                        }, 2000);
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
});
</script>

<style>
/* Prefix all classes with pc_ to avoid conflicts */

/* Container max width */
.pc_container {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 0 15px;
    overflow-x: hidden;
}

/* Header Styles */
.pc_products-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #4CAF50;
}

.pc_products-header h1 {
    font-size: 2.5rem;
    color: #2e7d32;
    margin-bottom: 10px;
}

/* RESPONSIVE PRODUCT GRID SYSTEM */
/* Extra Large Screens (1440px and above): 5×4 grid */
@media (min-width: 1440px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 24px !important;
    }
}

/* Large Desktop (1200px - 1439px): 5 products per row */
@media (min-width: 1200px) and (max-width: 1439px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 20px !important;
    }
}

/* Desktop (992px - 1199px): 4 products per row */
@media (min-width: 992px) and (max-width: 1199px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 18px !important;
    }
}

/* Tablet Landscape (768px - 991px): 3 products per row */
@media (min-width: 768px) and (max-width: 991px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 16px !important;
    }
}

/* Tablet Portrait (576px - 767px): 3 products per row */
@media (min-width: 576px) and (max-width: 767px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 14px !important;
    }
}

/* Mobile Landscape (481px - 575px): 2 products per row */
@media (min-width: 481px) and (max-width: 575px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
}

/* Mobile Portrait (360px - 480px): 2 products per row */
@media (min-width: 360px) and (max-width: 480px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
}

/* Very Small Mobile Devices (below 360px): 2 products per row */
@media (max-width: 359px) {
    .pc_products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    
    .pc_product-image {
        height: 120px !important;
    }
}

/* Product Card Styles */
.pc_product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    margin: 0;
    width: 100%;
    box-sizing: border-box;
}

.pc_product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.pc_product-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
}

.pc_product-info {
    padding: 15px;
    flex: 1;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.pc_product-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: #333;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pc_price-section {
    margin-bottom: 10px;
}

.pc_product-price {
    font-size: 18px;
    font-weight: 700;
    color: #2e7d32;
    margin: 0 0 3px 0;
}

.pc_price-unit {
    font-size: 13px;
    color: #666;
    font-weight: 400;
}

.pc_product-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
}

.pc_btn {
    padding: 10px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
    flex: 1;
    justify-content: center;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pc_btn-view {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #e0e0e0;
}

.pc_btn-add-cart {
    background: #4CAF50;
    color: white;
}

/* Category Titles */
.pc_category-title {
    font-size: 28px;
    font-weight: 700;
    color: #2e7d32;
    margin: 40px 0 25px 0;
    padding-bottom: 12px;
    position: relative;
}

/* Subcategory Titles */
.pc_subcategory-title {
    font-size: 22px;
    font-weight: 600;
    color: #333;
    margin: 30px 0 18px 0;
    padding-bottom: 10px;
    position: relative;
}

/* Load More and Show Less Container - FIXED FOR MOBILE */
.pc_load-more-container {
    text-align: center;
    margin: 30px 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    width: 200px;
}

/* Mobile-specific container layout */
@media (max-width: 768px) {
    .pc_load-more-container {
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin: 20px 0;
        padding: 0 10px;
        flex-wrap: nowrap;
    }
}

/* Load More and Show Less buttons */
.pc_btn-load-more,
.pc_btn-show-less {
    padding: 10px 20px;
    border: none;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    min-width: 10px;
    flex-shrink: 0;
    width:100px;
}

/* Load More Button */
.pc_btn-load-more {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(76, 175, 80, 0.3);
}

/* Show Less Button */
.pc_btn-show-less {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(255, 152, 0, 0.3);
}

/* Mobile-specific button styles */
@media (max-width: 768px) {
    .pc_btn-load-more,
    .pc_btn-show-less {
        padding: 8px 16px;
        font-size: 13px;
        min-width: 100px;
        flex: 1;
        max-width: 10px;
    }
    
    .pc_load-more-container {
        gap: 8px;
    }
}

/* Very small screens */
@media (max-width: 480px) {
    .pc_btn-load-more,
    .pc_btn-show-less {
        padding: 6px 12px;
        font-size: 12px;
        min-width: 10px;
        max-width: 100px;
    }
    
    .pc_load-more-container {
        gap: 6px;
    }
}

/* No Products Styling */
.pc_no-products {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

/* Mobile Responsive Adjustments */
@media (max-width: 768px) {
    .pc_products-header h1 {
        font-size: 2rem;
    }
    
    .pc_category-title {
        font-size: 24px;
        margin: 30px 0 20px 0;
    }
    
    .pc_subcategory-title {
        font-size: 20px;
        margin: 25px 0 15px 0;
    }
    
    .pc_product-image {
        height: 160px;
    }
}

@media (max-width: 480px) {
    .pc_product-image {
        height: 140px;
    }
    
    .pc_products-header h1 {
        font-size: 1.8rem;
    }
    
    .pc_category-title {
        font-size: 22px;
        margin: 25px 0 18px 0;
    }
    
    .pc_subcategory-title {
        font-size: 18px;
        margin: 20px 0 12px 0;
    }
    
    .pc_product-info {
        padding: 12px;
    }
    
    .pc_product-title {
        font-size: 14px;
    }
    
    .pc_product-price {
        font-size: 16px;
    }
    
    .pc_btn {
        padding: 8px 10px;
        font-size: 11px;
    }
}

/* Animation for newly loaded products */
.pc_product-card.pc_loaded-new {
    animation: pc_fadeInUp 0.5s ease forwards;
}

@keyframes pc_fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
} 

/* Overlay for products not available in city */
.pc_not-available-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    z-index: 10;
    pointer-events: none; /* Allows clicks to go through to the product card if needed */
}
</style>