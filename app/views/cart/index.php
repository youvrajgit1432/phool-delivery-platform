<?php
// app/views/cart/index.php

// Use PathConfig for dynamic path management
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('shopping_cart', 'Shopping Cart') . " - Phool Delivery";

function getCartImagePath($image_name) {
    $pathConfig = PathConfig::getInstance();
    
    // Handle empty or default images
    if (empty($image_name) || $image_name === 'default.jpg') {
        return $pathConfig->get('assets') . '/img/products/placeholder.jpg';
    }
    
    // Check if it's already a full URL
    if (strpos($image_name, 'http') === 0) {
        return $image_name;
    }
    
    // Check if it's a relative path from assets
    if (strpos($image_name, 'assets/') === 0) {
        return $pathConfig->get('base_url') . '/' . $image_name;
    }
    
    // For product images, use the product images path via getImagePath method
    return $pathConfig->getImagePath($image_name, 'product');
}

function getProductName($item) {
    $current_lang = $_SESSION['language'] ?? 'en';
    return ($current_lang === 'ne' && !empty($item['name_ne'])) ? $item['name_ne'] : ($item['name_en'] ?? $item['name'] ?? 'Product');
}

// FIXED: Get city info - use customer data for logged-in users, session for guests
function getCurrentCityInfo($db, $customer_id = null) {
    // If user is logged in, use their customer city from database
    if ($customer_id && isset($_SESSION['customer_id'])) {
        try {
            $query = "SELECT c.city, c.street, c.address, dc.id as city_id, dc.city_name, dc.standard_delivery_fee
                     FROM customers c 
                     LEFT JOIN delivery_cities dc ON LOWER(TRIM(c.city)) = LOWER(TRIM(dc.city_name))
                     WHERE c.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$customer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'city_id' => $result['city_id'],
                    'city_name' => $result['city_name'] ?? $result['city'],
                    'raw_city' => $result['city'],
                    'street' => $result['street'] ?? '',
                    'address' => $result['address'] ?? '',
                    'delivery_fee' => $result['standard_delivery_fee'] ?? 50.00
                ];
            }
        } catch (Exception $e) {
            error_log("Error fetching customer city: " . $e->getMessage());
        }
    }
    
    // For logged-out users, use session city
    return [
        'city_id' => $_SESSION['user_city_id'] ?? null,
        'city_name' => $_SESSION['user_city_name'] ?? null,
        'raw_city' => $_SESSION['user_city_name'] ?? null,
        'street' => $_SESSION['user_street'] ?? '',
        'address' => $_SESSION['user_address'] ?? '',
        'delivery_fee' => $_SESSION['user_delivery_fee'] ?? 50.00
    ];
}

function getDeliveryFee($db, $city_id) {
    if (!$city_id) return 50.00;
    
    try {
        $query = "SELECT standard_delivery_fee FROM delivery_cities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$city_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && isset($result['standard_delivery_fee']) ? floatval($result['standard_delivery_fee']) : 50.00;
    } catch (Exception $e) {
        error_log("Error fetching delivery fee: " . $e->getMessage());
        return 50.00;
    }
}

// FIXED: Get applicable offers based on current user type
function getApplicableOffers($db, $cart_items, $customer_id = null) {
    $offers = [];
    
    // Get city info based on user type
    $city_info = getCurrentCityInfo($db, $customer_id);
    $city_id = $city_info['city_id'];
    
    // Store city info in session for display
    $_SESSION['customer_city_id'] = $city_id;
    $_SESSION['customer_city_name'] = $city_info['city_name'];
    $_SESSION['customer_raw_city'] = $city_info['raw_city'];
    $_SESSION['customer_street'] = $city_info['street'];
    $_SESSION['customer_address'] = $city_info['address'];
    $_SESSION['current_delivery_fee'] = $city_info['delivery_fee'];
    
    try {
        $current_date = date('Y-m-d');
        $event_query = "SELECT * FROM special_events 
                       WHERE is_active = 1 
                       AND start_date <= ? 
                       AND end_date >= ?";
        $event_stmt = $db->prepare($event_query);
        $event_stmt->execute([$current_date, $current_date]);
        $special_events = $event_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($special_events as $event) {
            $offers[] = [
                'type' => 'special_event',
                'title' => $event['event_name'],
                'description' => $event['event_description'],
                'discount_percentage' => $event['discount_percentage'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date']
            ];
        }
        
        $product_ids = array_column($cart_items, 'id');
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $product_offer_query = "SELECT po.*, p.name_en, p.name_ne, dc.city_name
                                   FROM product_offers po 
                                   JOIN products p ON po.product_id = p.id 
                                   LEFT JOIN delivery_cities dc ON po.city_id = dc.id
                                   WHERE po.is_active = 1 
                                   AND (po.city_id = ? OR po.city_id IS NULL)
                                   AND po.product_id IN ($placeholders)
                                   AND (po.start_date IS NULL OR po.start_date <= ?)
                                   AND (po.end_date IS NULL OR po.end_date >= ?)";
            
            $params = array_merge([$city_id], $product_ids, [$current_date, $current_date]);
            $product_offer_stmt = $db->prepare($product_offer_query);
            $product_offer_stmt->execute($params);
            $product_offers = $product_offer_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($product_offers as $offer) {
                $offers[] = [
                    'type' => 'product_offer',
                    'product_id' => $offer['product_id'],
                    'product_name' => $offer['name_en'] ?? 'Product',
                    'product_name_ne' => $offer['name_ne'] ?? '',
                    'offer_type' => $offer['offer_type'],
                    'buy_quantity' => $offer['buy_quantity'],
                    'get_quantity' => $offer['get_quantity'],
                    'discount_percentage' => $offer['discount_percentage'],
                    'fixed_discount' => $offer['fixed_discount'],
                    'min_order_amount' => $offer['min_order_amount'],
                    'start_date' => $offer['start_date'],
                    'end_date' => $offer['end_date'],
                    'city_id' => $offer['city_id'],
                    'city_name' => $offer['city_name']
                ];
            }
        }
        
        foreach ($cart_items as $item) {
            if (isset($item['event_price']) && $item['event_price'] > 0 && $item['event_price'] < $item['price']) {
                $offers[] = [
                    'type' => 'event_pricing',
                    'product_id' => $item['id'],
                    'product_name' => $item['name_en'] ?? $item['name'] ?? 'Product',
                    'product_name_ne' => $item['name_ne'] ?? '',
                    'regular_price' => $item['price'],
                    'event_price' => $item['event_price'],
                    'savings' => $item['price'] - $item['event_price']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching offers: " . $e->getMessage());
    }
    
    return $offers;
}

function calculateOfferSavings($offers, $cart_items, $final_subtotal_before_discounts) {
    $savings = 0;
    
    foreach ($offers as $offer) {
        switch ($offer['type']) {
            case 'special_event':
                $savings += ($final_subtotal_before_discounts * $offer['discount_percentage']) / 100;
                break;
                
            case 'product_offer':
                $product_id = $offer['product_id'];
                foreach ($cart_items as $item) {
                    if ($item['id'] == $product_id) {
                        switch ($offer['offer_type']) {
                            case 'percentage_discount':
                                $savings += ($item['item_total'] * $offer['discount_percentage']) / 100;
                                break;
                            case 'fixed_discount':
                                $savings += $offer['fixed_discount'] * $item['quantity'];
                                break;
                            case 'buy_x_get_y':
                                if ($offer['buy_quantity'] > 0) {
                                    $free_items = floor($item['quantity'] / $offer['buy_quantity']) * $offer['get_quantity'];
                                    $savings += $free_items * $item['effective_price'];
                                }
                                break;
                        }
                    }
                }
                break;
        }
    }
    
    return $savings;
}

function getBulkPriceInfo($db, $product_id, $quantity) {
    try {
        $query = "SELECT price, bulk_price FROM products WHERE id = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $bulk_threshold = 10;
            $is_bulk = ($result['bulk_price'] !== null && $result['bulk_price'] > 0 && $quantity >= $bulk_threshold);
            
            return [
                'final_price' => $is_bulk ? floatval($result['bulk_price']) : floatval($result['price']),
                'original_price' => floatval($result['price']),
                'is_bulk' => $is_bulk,
                'bulk_threshold' => $bulk_threshold,
                'savings' => $is_bulk ? ($result['price'] - $result['bulk_price']) * $quantity : 0
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching bulk pricing: " . $e->getMessage());
    }
    
    return null;
}

function getEffectivePriceWithBulk($db, $product, $quantity) {
    if (isset($product['event_price']) && $product['event_price'] > 0 && $product['event_price'] < $product['price']) {
        $effective_price = $product['event_price'];
        $is_bulk = false;
        $bulk_threshold = 0;
        $savings = ($product['price'] - $effective_price) * $quantity;
        $original_price = $product['price'];
    } else {
        $bulk_info = getBulkPriceInfo($db, $product['id'], $quantity);
        if ($bulk_info) {
            $effective_price = $bulk_info['final_price'];
            $is_bulk = $bulk_info['is_bulk'];
            $bulk_threshold = $bulk_info['bulk_threshold'];
            $savings = $bulk_info['savings'];
            $original_price = $bulk_info['original_price'];
        } else {
            $effective_price = $product['price'];
            $is_bulk = false;
            $bulk_threshold = 0;
            $savings = 0;
            $original_price = $product['price'];
        }
    }
    
    return [
        'effective_price' => $effective_price,
        'is_bulk' => $is_bulk,
        'bulk_threshold' => $bulk_threshold,
        'savings' => $savings,
        'original_price' => $original_price
    ];
}

// MODIFICATION: Sort cart items by creation date (oldest first)
function sortCartByOldestFirst($cart) {
    // Sort by created_at timestamp if available, otherwise maintain original order
    usort($cart, function($a, $b) {
        $timeA = isset($a['added_at']) ? strtotime($a['added_at']) : 0;
        $timeB = isset($b['added_at']) ? strtotime($b['added_at']) : 0;
        return $timeA - $timeB; // Oldest first (ascending order)
    });
    return $cart;
}

// FIXED: Get delivery fee based on current user type
$delivery_fee = 50.00;
$city_id = null;
$current_city_info = [];
$user_street_address = '';
$user_full_address = '';

if (isset($db)) {
    $customer_id = $_SESSION['customer_id'] ?? null;
    $current_city_info = getCurrentCityInfo($db, $customer_id);
    $city_id = $current_city_info['city_id'];
    
    if ($city_id) {
        $delivery_fee = getDeliveryFee($db, $city_id);
    } else {
        $delivery_fee = $current_city_info['delivery_fee'] ?? 50.00;
    }
    
    // Build user address display for logged-in users
    if (isset($_SESSION['customer_id']) && !empty($current_city_info['street'])) {
        $user_street_address = htmlspecialchars($current_city_info['street']);
        $address_parts = [];
        if (!empty($current_city_info['address'])) {
            $address_parts[] = htmlspecialchars($current_city_info['address']);
        }
        $address_parts[] = $user_street_address;
        // Remove duplicate city name - only show city if it's different from street address
        if (!empty($current_city_info['city_name']) && 
            !str_contains(strtolower($current_city_info['address'] ?? ''), strtolower($current_city_info['city_name'])) &&
            !str_contains(strtolower($current_city_info['street'] ?? ''), strtolower($current_city_info['city_name']))) {
            $address_parts[] = htmlspecialchars($current_city_info['city_name']);
        }
        $user_full_address = implode(', ', $address_parts);
    }
}

$delivery_fee_display = ($delivery_fee == 0) ? LanguageHelper::t('free', 'Free') : 'Rs. ' . number_format($delivery_fee, 2);

// MODIFICATION: Sort cart items before processing
$cart = sortCartByOldestFirst($cart);

$cart_with_effective_prices = [];
$subtotal = 0;
$original_subtotal = 0;
$bulk_savings = 0;
$total_kg = 0;

foreach ($cart as $item) {
    $price_info = getEffectivePriceWithBulk($db, $item, $item['quantity']);
    
    $effective_price = $price_info['effective_price'];
    $item_total = $effective_price * $item['quantity'];
    $original_total = $price_info['original_price'] * $item['quantity'];
    
    $item['effective_price'] = $effective_price;
    $item['item_total'] = $item_total;
    $item['original_total'] = $original_total;
    $item['is_bulk'] = $price_info['is_bulk'];
    $item['bulk_threshold'] = $price_info['bulk_threshold'];
    $item['bulk_savings'] = $price_info['savings'];
    $item['original_price'] = $price_info['original_price'];
    
    $cart_with_effective_prices[] = $item;
    $subtotal += $item_total;
    $original_subtotal += $original_total;
    $bulk_savings += $price_info['savings'];
    
    if (isset($item['weight_kg'])) {
        $total_kg += $item['weight_kg'] * $item['quantity'];
    } elseif (isset($item['unit']) && strtolower($item['unit']) === 'kg') {
        $total_kg += $item['quantity'];
    }
}

$cart = $cart_with_effective_prices;

$offers = [];
$offer_savings = 0;
if (isset($db) && !empty($cart)) {
    $customer_id = $_SESSION['customer_id'] ?? null;
    $offers = getApplicableOffers($db, $cart, $customer_id);
    $offer_savings = calculateOfferSavings($offers, $cart, $subtotal);
}

$final_subtotal = $subtotal - $offer_savings;
$final_total = $final_subtotal + $delivery_fee;

// Prepare product data for JavaScript calculations
$product_prices_js = [];
$product_original_prices_js = [];
$product_bulk_thresholds_js = [];
$product_bulk_prices_js = [];
$product_weights_js = [];
foreach ($cart as $item) {
    $product_prices_js[$item['id']] = $item['effective_price'];
    $product_original_prices_js[$item['id']] = $item['original_price'];
    $product_bulk_thresholds_js[$item['id']] = $item['bulk_threshold'];
    
    try {
        $stmt = $db->prepare("SELECT bulk_price, weight_kg FROM products WHERE id = ?");
        $stmt->execute([$item['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $product_bulk_prices_js[$item['id']] = $result['bulk_price'] ?? null;
        $product_bulk_thresholds_js[$item['id']] = 10;
        $product_weights_js[$item['id']] = $result['weight_kg'] ?? 0;
    } catch (Exception $e) {
        $product_bulk_prices_js[$item['id']] = null;
        $product_bulk_thresholds_js[$item['id']] = 10;
        $product_weights_js[$item['id']] = 0;
    }
}
?>

<section class="cart-page">
    <div class="container">
        <!-- Modern Header with Breadcrumb -->
        <div class="cart-header">
            <div class="breadcrumb-nav">
                <a href="<?= $pathConfig->url('home') ?>" class="breadcrumb-link">
                    <i class="fas fa-home"></i> Home
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Shopping Cart</span>
            </div>
            <h1 class="page-title"><?= LanguageHelper::t('shopping_cart', 'Shopping Cart') ?></h1>
            <div class="cart-stats">
                <span class="item-count"><?= count($cart) ?> items</span>
            </div>
        </div>
        
        <?php if (empty($cart)): ?>
            <!-- Empty Cart State -->
            <div class="empty-state" data-aos="fade-up">
                <div class="empty-state-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2><?= LanguageHelper::t('your_cart_empty', 'Your cart is empty') ?></h2>
                <p><?= LanguageHelper::t('havent_added_flowers', 'You haven\'t added any flowers to your cart yet') ?></p>
                <a href="<?= $pathConfig->url('products') ?>" class="btn btn-primary btn-large">
                    <i class="fas fa-store"></i> <?= LanguageHelper::t('browse_flowers', 'Browse Flowers') ?>
                </a>
            </div>

            <!-- Featured Products Section -->
            <div class="featured-products-section" data-aos="fade-up">
                <div class="section-header">
                    <h2><?= LanguageHelper::t('featured_products', 'Featured Products') ?></h2>
                    <p>Discover our most popular flower arrangements</p>
                </div>
                <div class="products-grid modern-grid">
                    <?php
                    // Fetch featured products to show when cart is empty
                    try {
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        $featured_query = "SELECT p.*, pi.image_path, pi.is_primary 
                                         FROM products p 
                                         LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
                                         WHERE p.status = 'active' 
                                         ORDER BY p.created_at DESC 
                                         LIMIT 8";
                        $featured_stmt = $db->prepare($featured_query);
                        $featured_stmt->execute();
                        $featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($featured_products as $product) {
                            $product['name'] = LanguageHelper::getLocalizedText($product, 'name');
                            $primary_image = $product['image_path'] ?? 'placeholder.jpg';
                            $image_path = $pathConfig->getImagePath($primary_image, 'product');
                            
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
                    ?>
                        <div class="product-card modern-card" data-aos="zoom-in">
                            <div class="card-badges">
                                <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                                    <span class="badge discount">Bulk Discount</span>
                                <?php endif; ?>
                                <?php if ($product['stock_quantity'] <= 5 && $product['stock_quantity'] > 0): ?>
                                    <span class="badge warning">Low Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-image">
                                <a href="<?= $pathConfig->url('product/' . $product['id']) ?>">
                                    <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                </a>
                                <div class="card-actions">
                                    <button class="action-btn wishlist" title="Add to Wishlist">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="action-btn quick-view" title="Quick View">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-content">
                                <h3 class="product-title">
                                    <a href="<?= $pathConfig->url('product/' . $product['id']) ?>">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="price-section">
                                    <span class="current-price">Rs. <?= number_format($product['price'], 2) ?></span>
                                    <?php if ($product['bulk_price'] && $product['bulk_price'] < $product['price']): ?>
                                        <span class="bulk-price">Rs. <?= number_format($product['bulk_price'], 2) ?> for bulk</span>
                                    <?php endif; ?>
                                    <span class="price-unit">/<?= $product['unit'] ?></span>
                                </div>
                                
                             
                                <div class="card-footer">
                                    <a href="<?= $pathConfig->url('product/' . $product['id']) ?>" class="btn btn-outline">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                    <button class="btn btn-primary add-to-cart" 
                                            data-id="<?= $product['id'] ?>" 
                                            data-name="<?= htmlspecialchars($product['name']) ?>" 
                                            data-price="<?= $product['price'] ?>"
                                            data-unit="<?= $product['unit'] ?>"
                                            data-image="<?= $image_path ?>"
                                            <?= ($product['stock_quantity'] <= 0) ? 'disabled' : '' ?>>
                                        <i class="fas fa-shopping-cart"></i>
                                        <?php if ($product['stock_quantity'] <= 0): ?>
                                            Out of Stock
                                        <?php else: ?>
                                            Add to Cart
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php
                        }
                    } catch (Exception $e) {
                        echo '<div class="error-message">Unable to load featured products at this time.</div>';
                    }
                    ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart with Items -->
            <div class="cart-layout">
                <!-- Main Cart Items -->
                <div class="cart-main">
                    <!-- Compact Offers Section -->
                    <?php if (!empty($offers)): ?>
                        <div class="offers-section compact-offers" data-aos="fade-up">
                            <div class="offers-header">
                                <h3><i class="fas fa-tag"></i> Active Offers</h3>
                                <div class="location-indicator">
                                    <?php if (!empty($user_full_address)): ?>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= $user_full_address ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-location-dot"></i>
                                        <span><?= $_SESSION['customer_city_name'] ?? $_SESSION['customer_raw_city'] ?? 'Unknown' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Global Offers (Special Events) -->
                            <?php 
                            $global_offers = array_filter($offers, function($offer) {
                                return $offer['type'] == 'special_event';
                            });
                            ?>
                            <?php if (!empty($global_offers)): ?>
                                <div class="global-offers">
                                    <?php foreach ($global_offers as $offer): ?>
                                        <div class="global-offer-badge" data-aos="zoom-in">
                                            <div class="offer-icon">
                                                <i class="fas fa-gift"></i>
                                            </div>
                                            <div class="offer-details">
                                                <span class="offer-title"><?= htmlspecialchars($offer['title']) ?></span>
                                                <span class="offer-discount"><?= $offer['discount_percentage'] ?>% OFF</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Product-specific offers will be displayed on product images -->
                        </div>
                    <?php else: ?>
                        <div class="no-offers-message" data-aos="fade-up">
                            <div class="location-info">
                                <?php if (!empty($user_full_address)): ?>
                                    <div class="address-badge">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Delivering to: <?= $user_full_address ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="city-badge">
                                        <i class="fas fa-location-dot"></i>
                                        <span>Your City: <?= $_SESSION['customer_city_name'] ?? $_SESSION['customer_raw_city'] ?? 'Unknown' ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Cart Items List -->
                    <div class="cart-items-section" data-aos="fade-up">
                        <div class="section-header">
                            <h3>Cart Items (<?= count($cart) ?>)</h3>
                        </div>
                        <div class="cart-items-list">
                            <?php 
                            foreach ($cart as $index => $item): 
                                // Get product-specific offers
                                $product_offers = array_filter($offers, function($offer) use ($item) {
                                    return ($offer['type'] == 'product_offer' && $offer['product_id'] == $item['id']) ||
                                           ($offer['type'] == 'event_pricing' && $offer['product_id'] == $item['id']);
                                });
                            ?>
                                <div class="cart-item modern-item" data-product-id="<?=$item['id']?>" data-unit="<?=$item['unit']?>" data-aos="fade-up" data-aos-delay="<?= ($index * 100) + 200 ?>">
                                    <div class="item-checkbox">
                                        <input type="checkbox" checked>
                                    </div>
                                    <div class="item-image">
                                        <img src="<?=getCartImagePath($item['image'] ?? '')?>" alt="<?=htmlspecialchars(getProductName($item))?>" 
                                             onerror="this.src='<?= $pathConfig->get('assets') ?>/img/products/placeholder.jpg'">
                                        
                                        <!-- Product-specific offer badges on image -->
                                        <?php if (!empty($product_offers)): ?>
                                             
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <div class="item-header">
                                            <h3 class="item-name"><?=htmlspecialchars(getProductName($item))?></h3>
                                            <button class="item-remove" data-product-id="<?=$item['id']?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="price-info">
                                            <?php if ($item['is_bulk']): ?>
                                                <div class="price-comparison">
                                                    <span class="original-price">Rs. <?=number_format($item['original_price'], 2)?></span>
                                                    <span class="current-price">Rs. <?=number_format($item['effective_price'], 2)?></span>
                                                    <span class="price-badge bulk">Bulk Price</span>
                                                </div>
                                                <div class="savings-notice">
                                                    <i class="fas fa-tag"></i> Bulk pricing applied (min. <?=$item['bulk_threshold']?> <?=$item['unit']?>)
                                                </div>
                                            <?php elseif (isset($item['event_price']) && $item['event_price'] > 0 && $item['event_price'] < $item['price']): ?>
                                                <div class="price-comparison">
                                                    <span class="original-price">Rs. <?=number_format($item['price'], 2)?></span>
                                                    <span class="current-price">Rs. <?=number_format($item['effective_price'], 2)?></span>
                                                    <span class="price-badge event">Event Price</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="current-price">Rs. <?=number_format($item['effective_price'], 2)?> per <?=$item['unit']?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-controls">
                                            <div class="quantity-controls modern-qty">
                                                <button class="qty-btn decrease" data-product-id="<?=$item['id']?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="qty-input" value="<?=$item['quantity'] ?? 1?>" min="1" 
                                                       data-product-id="<?=$item['id']?>" data-price="<?=$item['price'] ?? 0?>" 
                                                       data-event-price="<?=$item['event_price'] ?? 0?>" data-unit="<?=$item['unit']?>" 
                                                       data-bulk-threshold="<?=$item['bulk_threshold'] ?? 10?>" data-original-price="<?=$item['original_price']?>">
                                                <button class="qty-btn increase" data-product-id="<?=$item['id']?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            
                                            <div class="item-total">
                                                <span class="total-price" id="item-total-<?=$item['id']?>">Rs. <?=number_format($item['item_total'], 2)?></span>
                                                <?php if (isset($item['original_total']) && $item['original_total'] > $item['item_total']): ?>
                                                    <span class="original-total">Was Rs. <?=number_format($item['original_total'], 2)?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($item['bulk_savings'] > 0): ?>
                                            <div class="savings-message">
                                                <i class="fas fa-piggy-bank"></i> You save Rs. <?=number_format($item['bulk_savings'], 2)?> with bulk pricing
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary Sidebar -->
                <div class="cart-sidebar">
                    <div class="summary-card modern-summary" data-aos="zoom-in" data-aos-delay="400">
                        <div class="summary-header">
                            <h3>Order Summary</h3>
                        </div>
                        
                        <?php if (!empty($user_full_address)): ?>
                            <div class="delivery-section">
                                <div class="section-title">
                                    <i class="fas fa-truck"></i> Delivery Information
                                </div>
                                <div class="address-display">
                                    <div class="address-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="address-details">
                                        <div class="address-text"><?= $user_full_address ?></div>
                                        <?php if (isset($_SESSION['customer_id'])): ?>
                                            <a href="<?= $pathConfig->url('account/profile') ?>" class="change-address">
                                                Change Address
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-details">
                            <?php if ($total_kg > 0): ?>
                                <div class="summary-row">
                                    <span>Total Weight</span>
                                    <span id="summary-total-weight"><?= number_format($total_kg, 2) ?> kg</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($original_subtotal > $subtotal): ?>
                                <div class="summary-row original">
                                    <span>Original Subtotal</span>
                                    <span class="original-price" id="summary-original-subtotal">Rs. <?=number_format($original_subtotal, 2)?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span id="summary-subtotal">Rs. <?=number_format($subtotal, 2)?></span>
                            </div>
                            
                            <?php if ($bulk_savings > 0): ?>
                                <div class="summary-row discount">
                                    <span>Bulk Savings</span>
                                    <span class="savings" id="summary-bulk-savings">- Rs. <?=number_format($bulk_savings, 2)?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($offer_savings > 0): ?>
                                <div class="summary-row discount">
                                    <span>Offer Savings</span>
                                    <span class="savings" id="summary-offer-savings">- Rs. <?=number_format($offer_savings, 2)?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="summary-row delivery">
                                <span>Delivery Fee</span>
                                <span id="summary-delivery-fee"><?= $delivery_fee_display ?></span>
                            </div>
                            
                            <div class="summary-divider"></div>
                            
                            <div class="summary-row total">
                                <span>Total Amount</span>
                                <span id="summary-total">Rs. <?=number_format($final_total, 2)?></span>
                            </div>
                            
                            <?php if (($bulk_savings + $offer_savings) > 0): ?>
                                <div class="savings-summary">
                                    <i class="fas fa-star"></i>
                                    <span>You saved Rs. <?=number_format($bulk_savings + $offer_savings, 2)?> with offers!</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="summary-actions">
                            <a href="<?= $pathConfig->url('checkout') ?>" class="btn btn-primary btn-checkout">
                                <i class="fas fa-lock"></i> Proceed to Checkout
                            </a>
                            <a href="<?= $pathConfig->url('products') ?>" class="btn btn-outline continue-shopping">
                                Continue Shopping <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="security-badges">
                            <div class="security-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure Payment</span>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-truck"></i>
                                <span>Free Delivery Over Rs. 2000</span>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-undo"></i>
                                <span>Easy Returns</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productPrices = <?= json_encode($product_prices_js) ?>;
    const productOriginalPrices = <?= json_encode($product_original_prices_js) ?>;
    const productBulkThresholds = <?= json_encode($product_bulk_thresholds_js) ?>;
    const productBulkPrices = <?= json_encode($product_bulk_prices_js) ?>;
    const productWeights = <?= json_encode($product_weights_js) ?>;

    function calculateBulkPrice(productId, quantity) {
        const originalPrice = productOriginalPrices[productId] || 0;
        const bulkThreshold = productBulkThresholds[productId] || 10;
        const bulkPrice = productBulkPrices[productId] || null;
        
        if (bulkPrice && quantity >= bulkThreshold && bulkPrice > 0 && bulkPrice < originalPrice) {
            return bulkPrice;
        }
        
        return originalPrice;
    }

    function updatePriceDisplay(productId, quantity) {
        const itemElement = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
        const input = document.querySelector(`.qty-input[data-product-id="${productId}"]`);
        const unit = input.dataset.unit;
        const regularPrice = parseFloat(input.dataset.price);
        const eventPrice = parseFloat(input.dataset.eventPrice) || 0;
        const bulkThreshold = parseInt(input.dataset.bulkThreshold) || 10;
        
        let effectivePrice = regularPrice;
        let isBulk = false;
        
        if (eventPrice > 0 && eventPrice < regularPrice) {
            effectivePrice = eventPrice;
        } else {
            const bulkPrice = calculateBulkPrice(productId, quantity);
            if (bulkPrice < regularPrice && quantity >= bulkThreshold) {
                effectivePrice = bulkPrice;
                isBulk = true;
            }
        }
        
        const totalPrice = effectivePrice * quantity;
        const originalTotal = regularPrice * quantity;
        
        const totalElement = document.getElementById(`item-total-${productId}`);
        if (totalElement) {
            totalElement.textContent = `Rs. ${totalPrice.toFixed(2)}`;
        }
        
        const originalTotalElement = itemElement.querySelector('.original-total');
        if (originalTotal > totalPrice) {
            if (!originalTotalElement) {
                const originalTotalEl = document.createElement('span');
                originalTotalEl.className = 'original-total';
                originalTotalEl.textContent = `Was Rs. ${originalTotal.toFixed(2)}`;
                totalElement.parentNode.appendChild(originalTotalEl);
            } else {
                originalTotalElement.textContent = `Was Rs. ${originalTotal.toFixed(2)}`;
            }
        } else if (originalTotalElement) {
            originalTotalElement.remove();
        }
        
        const priceInfo = itemElement.querySelector('.price-info');
        const savingsMessage = itemElement.querySelector('.savings-message');
        
        if (isBulk) {
            priceInfo.innerHTML = `
                <div class="price-comparison">
                    <span class="original-price">Rs. ${regularPrice.toFixed(2)}</span>
                    <span class="current-price">Rs. ${effectivePrice.toFixed(2)}</span>
                    <span class="price-badge bulk">Bulk Price</span>
                </div>
                <div class="savings-notice">
                    <i class="fas fa-tag"></i> Bulk pricing applied (min. ${bulkThreshold} ${unit})
                </div>
            `;
            
            const bulkSavings = (regularPrice - effectivePrice) * quantity;
            if (savingsMessage) {
                savingsMessage.innerHTML = `<i class="fas fa-piggy-bank"></i> You save Rs. ${bulkSavings.toFixed(2)} with bulk pricing`;
            } else {
                const savingsEl = document.createElement('div');
                savingsEl.className = 'savings-message';
                savingsEl.innerHTML = `<i class="fas fa-piggy-bank"></i> You save Rs. ${bulkSavings.toFixed(2)} with bulk pricing`;
                itemElement.querySelector('.item-details').appendChild(savingsEl);
            }
        } else if (eventPrice > 0 && eventPrice < regularPrice) {
            priceInfo.innerHTML = `
                <div class="price-comparison">
                    <span class="original-price">Rs. ${regularPrice.toFixed(2)}</span>
                    <span class="current-price">Rs. ${effectivePrice.toFixed(2)}</span>
                    <span class="price-badge event">Event Price</span>
                </div>
            `;
            if (savingsMessage) {
                savingsMessage.remove();
            }
        } else {
            priceInfo.innerHTML = `<span class="current-price">Rs. ${effectivePrice.toFixed(2)} per ${unit}</span>`;
            if (savingsMessage) {
                savingsMessage.remove();
            }
        }
        
        input.dataset.bulkThreshold = bulkThreshold;
    }

    function updateOrderSummary() {
        let subtotal = 0;
        let originalSubtotal = 0;
        let bulkSavings = 0;
        let totalWeight = 0;
        
        document.querySelectorAll('.cart-item').forEach(item => {
            const productId = item.dataset.productId;
            const quantity = parseInt(item.querySelector('.qty-input').value);
            const regularPrice = parseFloat(item.querySelector('.qty-input').dataset.price);
            const eventPrice = parseFloat(item.querySelector('.qty-input').dataset.eventPrice) || 0;
            const unit = item.dataset.unit;
            
            let effectivePrice = regularPrice;
            
            if (eventPrice > 0 && eventPrice < regularPrice) {
                effectivePrice = eventPrice;
            } else {
                const bulkPrice = calculateBulkPrice(productId, quantity);
                if (bulkPrice < regularPrice && quantity >= 10) {
                    effectivePrice = bulkPrice;
                }
            }
            
            const itemTotal = effectivePrice * quantity;
            const originalItemTotal = regularPrice * quantity;
            
            subtotal += itemTotal;
            originalSubtotal += originalItemTotal;
            
            if (effectivePrice < regularPrice) {
                bulkSavings += (regularPrice - effectivePrice) * quantity;
            }
            
            const productWeight = productWeights[productId] || 0;
            if (productWeight > 0) {
                totalWeight += productWeight * quantity;
            } else if (unit.toLowerCase() === 'kg') {
                totalWeight += quantity;
            }
        });
        
        const offerSavings = 0;
        const deliveryFee = <?= $delivery_fee ?>;
        const finalSubtotal = subtotal - offerSavings;
        const finalTotal = finalSubtotal + deliveryFee;
        
        if (totalWeight > 0) {
            document.getElementById('summary-total-weight').textContent = `${totalWeight.toFixed(2)} kg`;
        }
        
        if (originalSubtotal > subtotal) {
            document.getElementById('summary-original-subtotal').textContent = `Rs. ${originalSubtotal.toFixed(2)}`;
        }
        
        document.getElementById('summary-subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
        
        if (bulkSavings > 0) {
            document.getElementById('summary-bulk-savings').textContent = `- Rs. ${bulkSavings.toFixed(2)}`;
        }
        
        if (offerSavings > 0) {
            document.getElementById('summary-offer-savings').textContent = `- Rs. ${offerSavings.toFixed(2)}`;
        }
        
        const deliveryFeeDisplay = deliveryFee === 0 ? 'Free' : `Rs. ${deliveryFee.toFixed(2)}`;
        document.getElementById('summary-delivery-fee').textContent = deliveryFeeDisplay;
        
        document.getElementById('summary-total').textContent = `Rs. ${finalTotal.toFixed(2)}`;
        
        const totalSavings = bulkSavings + offerSavings;
        const savingsSummary = document.querySelector('.savings-summary');
        if (totalSavings > 0) {
            if (savingsSummary) {
                savingsSummary.querySelector('span').textContent = `You saved Rs. ${totalSavings.toFixed(2)} with offers!`;
            }
        } else if (savingsSummary) {
            savingsSummary.remove();
        }
    }

    function shouldRefreshForBulkPricing(productId, newQuantity) {
        return newQuantity >= 10;
    }

    function handleQuantity(action, productId) {
        const input = document.querySelector(`.qty-input[data-product-id="${productId}"]`);
        const oldQuantity = parseInt(input.value);
        let newQuantity = oldQuantity;
        
        if (action === 'increase') newQuantity++;
        else if (action === 'decrease' && newQuantity > 1) newQuantity--;
        if (newQuantity < 1) newQuantity = 1;
        
        input.value = newQuantity;
        
        if (shouldRefreshForBulkPricing(productId, newQuantity)) {
            updateCartAndRefresh(productId, newQuantity);
        } else {
            updatePriceDisplay(productId, newQuantity);
            updateOrderSummary();
            updateCart(productId, newQuantity);
        }
    }

    function handleDirectQuantityInput(productId, newQuantity) {
        const input = document.querySelector(`.qty-input[data-product-id="${productId}"]`);
        
        if (newQuantity < 1) newQuantity = 1;
        input.value = newQuantity;
        
        if (newQuantity >= 10) {
            updateCartAndRefresh(productId, newQuantity);
        } else {
            updatePriceDisplay(productId, newQuantity);
            updateOrderSummary();
            updateCart(productId, newQuantity);
        }
    }

    // Event Listeners
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            handleQuantity(this.classList.contains('increase') ? 'increase' : 'decrease', this.dataset.productId);
        });
    });

    document.querySelectorAll('.qty-input').forEach(input => {
        let oldValue = parseInt(input.value);
        
        input.addEventListener('focus', function() {
            oldValue = parseInt(this.value);
        });
        
        input.addEventListener('change', function() {
            let newQuantity = parseInt(this.value);
            if (newQuantity < 1) newQuantity = 1;
            this.value = newQuantity;
            
            handleDirectQuantityInput(this.dataset.productId, newQuantity);
            
            oldValue = newQuantity;
        });
        
        input.addEventListener('input', function() {
            let newQuantity = parseInt(this.value);
            if (newQuantity >= 1 && newQuantity < 10) {
                updatePriceDisplay(this.dataset.productId, newQuantity);
                updateOrderSummary();
            }
        });

        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                let newQuantity = parseInt(this.value);
                if (newQuantity < 1) newQuantity = 1;
                this.value = newQuantity;
                
                handleDirectQuantityInput(this.dataset.productId, newQuantity);
                this.blur();
            }
        });
    });

    document.querySelectorAll('.item-remove').forEach(btn => {
        btn.addEventListener('click', function() {
            removeFromCart(this.dataset.productId);
        });
    });

    // Add to cart functionality for empty cart products
    document.querySelectorAll('.add-to-cart').forEach(button => {
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
            
            fetch('<?= $pathConfig->url("cart/add") ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const originalText = this.innerHTML;
                    this.innerHTML = "<i class='fas fa-check'></i> Added";
                    this.classList.add('added');
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('added');
                    }, 2000);
                    
                    updateCartCount();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Failed to add to cart: ' + (d.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    });

    function updateCart(productId, q) {
        fetch('<?= $pathConfig->url("cart/update") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `product_id=${productId}&quantity=${q}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                updateUI(d);
            }
        });
    }

    function updateCartAndRefresh(productId, q) {
        fetch('<?= $pathConfig->url("cart/update") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `product_id=${productId}&quantity=${q}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            }
        });
    }

    function removeFromCart(productId) {
        if (!confirm('Are you sure you want to remove this item from your cart?')) return;
        
        fetch('<?= $pathConfig->url("cart/remove") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `product_id=${productId}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.querySelector(`.cart-item[data-product-id="${productId}"]`).remove();
                updateUI(d);
                updateOrderSummary();
                if (d.cart_count === 0) {
                    location.reload();
                }
            }
        });
    }

    function updateUI(data) {
        document.querySelectorAll('.cart-count, .cart-badge').forEach(el => {
            el.textContent = data.cart_count;
            el.style.display = data.cart_count > 0 ? 'inline' : 'none';
        });
    }

    function updateCartCount() {
        fetch('<?= $pathConfig->url("cart/count") ?>')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.querySelectorAll('.cart-count, .cart-badge').forEach(el => {
                        el.textContent = d.count;
                        el.style.display = d.count > 0 ? 'inline-block' : 'none';
                    });
                }
            })
            .catch(error => console.error('Error updating cart count:', error));
    }

    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after delay
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});
</script>

<style>
/* Cart Page Specific CSS - Isolated from Header Styles */
.cart-page {
    padding: 2rem 0;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg-gray-50, #f9fafb);
    min-height: 100vh;
}

.cart-page .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Modern Header Styles - Scoped to cart page only */
.cart-page .cart-header {
    margin-bottom: 2rem;
}

.cart-page .breadcrumb-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1rem;
    font-size: 14px;
    color: var(--text-secondary, #6b7280);
}

.cart-page .breadcrumb-link {
    color: var(--text-secondary, #6b7280);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 6px;
}

.cart-page .breadcrumb-link:hover {
    color: var(--primary-color, #10b981);
}

.cart-page .breadcrumb-separator {
    color: var(--text-light, #9ca3af);
}

.cart-page .breadcrumb-current {
    color: var(--text-primary, #1f2937);
    font-weight: 500;
}

.cart-page .page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary, #1f2937);
    margin-bottom: 0.5rem;
}

.cart-page .cart-stats {
    color: var(--text-secondary, #6b7280);
    font-size: 14px;
}

/* Empty State - Scoped */
.cart-page .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
    margin: 2rem 0;
}

.cart-page .empty-state-icon {
    font-size: 4rem;
    color: var(--text-light, #9ca3af);
    margin-bottom: 1.5rem;
}

.cart-page .empty-state h2 {
    color: var(--text-primary, #1f2937);
    margin-bottom: 1rem;
    font-weight: 600;
}

.cart-page .empty-state p {
    color: var(--text-secondary, #6b7280);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

/* Buttons - Scoped to cart page */
.cart-page .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius, 8px);
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 14px;
    line-height: 1;
}

.cart-page .btn-primary {
    background: var(--primary-color, #10b981);
    color: white;
}

.cart-page .btn-primary:hover {
    background: var(--primary-dark, #059669);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md, 0 4px 6px rgba(0, 0, 0, 0.1));
}

.cart-page .btn-outline {
    background: transparent;
    color: var(--text-primary, #1f2937);
    border: 2px solid var(--border-color, #e5e7eb);
}

.cart-page .btn-outline:hover {
    border-color: var(--primary-color, #10b981);
    color: var(--primary-color, #10b981);
}

.cart-page .btn-large {
    padding: 16px 32px;
    font-size: 16px;
}

.cart-page .btn-checkout {
    width: 100%;
    justify-content: center;
    padding: 16px;
    font-size: 16px;
    margin-bottom: 1rem;
}

/* Cart Layout - Scoped */
.cart-page .cart-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    align-items: start;
}

/* Cart Main Section - Scoped */
.cart-page .cart-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.cart-page .section-header {
    margin-bottom: 1rem;
}

.cart-page .section-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Compact Offers Section - Scoped */
.cart-page .compact-offers {
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
    padding: 1.25rem;
}

.cart-page .offers-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.cart-page .offers-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary, #1f2937);
    display: flex;
    align-items: center;
    gap: 8px;
}

.cart-page .location-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--text-secondary, #6b7280);
    background: var(--bg-gray-50, #f9fafb);
    padding: 6px 12px;
    border-radius: 20px;
}

/* Global Offers - Scoped */
.cart-page .global-offers {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.cart-page .global-offer-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 14px;
    border-radius: var(--radius, 8px);
    font-size: 0.875rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    flex: 1;
    min-width: 200px;
}

.cart-page .global-offer-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md, 0 4px 6px rgba(0, 0, 0, 0.1));
}

.cart-page .offer-icon {
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.cart-page .offer-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}

.cart-page .offer-title {
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.2;
}

.cart-page .offer-discount {
    font-size: 0.8rem;
    opacity: 0.9;
    font-weight: 500;
}

/* Product Offer Badges on Images - Scoped */
.cart-page .item-image {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: var(--radius, 8px);
    overflow: hidden;
    flex-shrink: 0;
}

.cart-page .item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-page .product-offer-badges {
    position: absolute;
    top: 4px;
    left: 4px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 2;
}

.cart-page .offer-badge {
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    color: white;
    line-height: 1;
    white-space: nowrap;
}

.cart-page .offer-badge.discount {
    background: var(--danger-color, #ef4444);
}

.cart-page .offer-badge.fixed {
    background: var(--warning-color, #f59e0b);
}

.cart-page .offer-badge.bogo {
    background: var(--success-color, #10b981);
}

.cart-page .offer-badge.event {
    background: var(--secondary-color, #6366f1);
}

/* Location Badges - Scoped */
.cart-page .location-info {
    margin-bottom: 1rem;
}

.cart-page .address-badge,
.cart-page .city-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--bg-gray-50, #f9fafb);
    border-radius: 20px;
    font-size: 0.875rem;
    color: var(--text-secondary, #6b7280);
}

/* Cart Items Section - Scoped */
.cart-page .cart-items-section {
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
    overflow: hidden;
}

.cart-page .cart-items-list {
    padding: 0;
}

.cart-page .cart-item.modern-item {
    display: grid;
    grid-template-columns: auto 80px 1fr;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-light, #f3f4f6);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    align-items: start;
}

.cart-page .cart-item.modern-item:hover {
    background: var(--bg-gray-50, #f9fafb);
}

.cart-page .cart-item.modern-item:last-child {
    border-bottom: none;
}

.cart-page .item-checkbox {
    display: flex;
    align-items: flex-start;
    padding-top: 0.25rem;
}

.cart-page .item-checkbox input {
    accent-color: var(--primary-color, #10b981);
    width: 18px;
    height: 18px;
}

.cart-page .item-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
}

.cart-page .item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.cart-page .item-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin: 0;
    line-height: 1.4;
    flex: 1;
}

.cart-page .item-remove {
    background: none;
    border: none;
    color: var(--text-light, #9ca3af);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}

.cart-page .item-remove:hover {
    color: var(--danger-color, #ef4444);
    background: var(--bg-gray-100, #f3f4f6);
}

/* Price Information - Scoped */
.cart-page .price-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cart-page .price-comparison {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.cart-page .original-price {
    text-decoration: line-through;
    color: var(--text-light, #9ca3af);
    font-size: 0.9rem;
}

.cart-page .current-price {
    color: var(--primary-color, #10b981);
    font-weight: 600;
    font-size: 1.1rem;
}

.cart-page .price-badge {
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.cart-page .price-badge.bulk {
    background: #d1fae5;
    color: #065f46;
}

.cart-page .price-badge.event {
    background: #fef3c7;
    color: #92400e;
}

.cart-page .savings-notice {
    font-size: 0.8rem;
    color: var(--success-color, #10b981);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Item Controls - Scoped */
.cart-page .item-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: 0.5rem;
}

.cart-page .quantity-controls.modern-qty {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-gray-50, #f9fafb);
    border-radius: var(--radius, 8px);
    padding: 4px;
}

.cart-page .qty-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--bg-white, #ffffff);
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--text-primary, #1f2937);
}

.cart-page .qty-btn:hover {
    background: var(--primary-color, #10b981);
    color: white;
}

.cart-page .qty-input {
    width: 50px;
    height: 32px;
    border: none;
    background: transparent;
    text-align: center;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
}

.cart-page .qty-input:focus {
    outline: none;
}

.cart-page .item-total {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 100px;
}

.cart-page .total-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary, #1f2937);
}

.cart-page .original-total {
    font-size: 0.9rem;
    color: var(--text-light, #9ca3af);
    text-decoration: line-through;
}

/* Savings Message - Scoped */
.cart-page .savings-message {
    padding: 8px 12px;
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #166534;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 0.5rem;
}

/* Cart Sidebar - Scoped */
.cart-page .cart-sidebar {
    position: sticky;
    top: 2rem;
}

.cart-page .summary-card.modern-summary {
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow-lg, 0 10px 15px rgba(0, 0, 0, 0.1));
    overflow: hidden;
}

.cart-page .summary-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light, #f3f4f6);
    background: linear-gradient(135deg, var(--primary-color, #10b981), var(--secondary-color, #6366f1));
    color: white;
}

.cart-page .summary-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

/* Delivery Section - Scoped */
.cart-page .delivery-section {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light, #f3f4f6);
}

.cart-page .section-title {
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cart-page .address-display {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.cart-page .address-icon {
    width: 32px;
    height: 32px;
    background: var(--primary-color, #10b981);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.cart-page .address-details {
    flex: 1;
}

.cart-page .address-text {
    color: var(--text-primary, #1f2937);
    font-weight: 500;
    margin-bottom: 4px;
    line-height: 1.4;
}

.cart-page .change-address {
    color: var(--primary-color, #10b981);
    font-size: 0.9rem;
    text-decoration: none;
    font-weight: 500;
}

.cart-page .change-address:hover {
    text-decoration: underline;
}

/* Summary Details - Scoped */
.cart-page .summary-details {
    padding: 1.5rem;
}

.cart-page .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light, #f3f4f6);
}

.cart-page .summary-row:last-child {
    border-bottom: none;
}

.cart-page .summary-row.original {
    font-size: 0.9rem;
}

.cart-page .summary-row.discount .savings {
    color: var(--success-color, #10b981);
    font-weight: 600;
}

.cart-page .summary-row.delivery {
    font-weight: 500;
}

.cart-page .summary-row.total {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary, #1f2937);
    padding-top: 1rem;
    border-top: 2px solid var(--border-light, #f3f4f6);
}

.cart-page .summary-divider {
    height: 1px;
    background: var(--border-light, #f3f4f6);
    margin: 1rem 0;
}

.cart-page .savings-summary {
    padding: 12px;
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    border-radius: var(--radius, 8px);
    display: flex;
    align-items: center;
    gap: 8px;
    color: #166534;
    font-weight: 500;
    margin-top: 1rem;
}

/* Summary Actions - Scoped */
.cart-page .summary-actions {
    padding: 1.5rem;
    border-top: 1px solid var(--border-light, #f3f4f6);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Security Badges - Scoped */
.cart-page .security-badges {
    padding: 1.5rem;
    background: var(--bg-gray-50, #f9fafb);
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.cart-page .security-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--text-secondary, #6b7280);
}

.cart-page .security-item i {
    color: var(--success-color, #10b981);
}

/* Featured Products Section - Scoped */
.cart-page .featured-products-section {
    margin-top: 3rem;
}

.cart-page .section-header {
    text-align: center;
    margin-bottom: 2rem;
}

.cart-page .section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary, #1f2937);
    margin-bottom: 0.5rem;
}

.cart-page .section-header p {
    color: var(--text-secondary, #6b7280);
    font-size: 1.125rem;
}

/* Modern Product Grid - Scoped */
.cart-page .products-grid.modern-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.cart-page .product-card.modern-card {
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.cart-page .product-card.modern-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg, 0 10px 15px rgba(0, 0, 0, 0.1));
}

.cart-page .card-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    z-index: 2;
}

.cart-page .badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.cart-page .badge.discount {
    background: var(--danger-color, #ef4444);
    color: white;
}

.cart-page .badge.warning {
    background: var(--warning-color, #f59e0b);
    color: white;
}

.cart-page .card-image {
    position: relative;
    overflow: hidden;
}

.cart-page .card-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cart-page .product-card:hover .card-image img {
    transform: scale(1.05);
}

.cart-page .card-actions {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cart-page .product-card:hover .card-actions {
    opacity: 1;
}

.cart-page .action-btn {
    width: 36px;
    height: 36px;
    background: var(--bg-white, #ffffff);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--text-primary, #1f2937);
}

.cart-page .action-btn:hover {
    background: var(--primary-color, #10b981);
    color: white;
    transform: scale(1.1);
}

.cart-page .card-content {
    padding: 1.25rem;
}

.cart-page .product-title {
    margin: 0 0 0.75rem 0;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.4;
}

.cart-page .product-title a {
    color: var(--text-primary, #1f2937);
    text-decoration: none;
}

.cart-page .product-title a:hover {
    color: var(--primary-color, #10b981);
}

.cart-page .price-section {
    margin-bottom: 0.75rem;
}

.cart-page .current-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color, #10b981);
    margin-right: 8px;
}

.cart-page .bulk-price {
    font-size: 0.875rem;
    color: var(--danger-color, #ef4444);
    font-weight: 500;
    display: block;
    margin-top: 2px;
}

.cart-page .price-unit {
    font-size: 0.875rem;
    color: var(--text-light, #9ca3af);
}

.cart-page .stock-info {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.cart-page .stock-info.in-stock {
    color: var(--success-color, #10b981);
}

.cart-page .stock-info.low-stock {
    color: var(--warning-color, #f59e0b);
}

.cart-page .stock-info.out-of-stock {
    color: var(--danger-color, #ef4444);
}

.cart-page .card-footer {
    display: flex;
    gap: 8px;
}

.cart-page .card-footer .btn {
    flex: 1;
    justify-content: center;
    font-size: 0.875rem;
    padding: 10px 16px;
}

/* Notifications - Scoped */
.cart-page .notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--bg-white, #ffffff);
    border-radius: var(--radius, 8px);
    box-shadow: var(--shadow-lg, 0 10px 15px rgba(0, 0, 0, 0.1));
    padding: 1rem;
    max-width: 400px;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

.cart-page .notification.show {
    transform: translateX(0);
    opacity: 1;
}

.cart-page .notification.error {
    border-left: 4px solid var(--danger-color, #ef4444);
}

.cart-page .notification.success {
    border-left: 4px solid var(--success-color, #10b981);
}

.cart-page .notification-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cart-page .notification.error .notification-content i {
    color: var(--danger-color, #ef4444);
}

.cart-page .notification.success .notification-content i {
    color: var(--success-color, #10b981);
}

/* Enhanced Mobile Responsive Design - Scoped */
@media (max-width: 1024px) {
    .cart-page .cart-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .cart-page .cart-sidebar {
        position: static;
    }
    
    .cart-page .products-grid.modern-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .cart-page .container {
        padding: 0 16px;
    }
    
    .cart-page .page-title {
        font-size: 2rem;
    }
    
    .cart-page .cart-item.modern-item {
        grid-template-columns: auto 60px 1fr;
        gap: 0.75rem;
        padding: 0.75rem;
    }
    
    .cart-page .item-checkbox {
        padding-top: 0;
    }
    
    .cart-page .item-image {
        width: 60px;
        height: 60px;
    }
    
    .cart-page .item-details {
        gap: 0.5rem;
    }
    
    .cart-page .item-name {
        font-size: 1rem;
    }
    
    .cart-page .item-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .cart-page .quantity-controls.modern-qty {
        justify-content: center;
        align-self: center;
    }
    
    .cart-page .item-total {
        text-align: center;
        align-self: center;
        min-width: auto;
    }
    
    .cart-page .products-grid.modern-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .cart-page .global-offers {
        flex-direction: column;
    }
    
    .cart-page .global-offer-badge {
        min-width: auto;
    }
    
    .cart-page .offers-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .cart-page .summary-header h3 {
        font-size: 1.1rem;
    }
    
    .cart-page .summary-details {
        padding: 1rem;
    }
    
    .cart-page .summary-row {
        font-size: 0.9rem;
        padding: 10px 0;
    }
    
    .cart-page .summary-row.total {
        font-size: 1.1rem;
    }
    
    .cart-page .security-badges {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .cart-page .summary-actions {
        flex-direction: row;
        gap: 8px;
    }
    
    .cart-page .summary-actions .btn {
        flex: 1;
        min-width: 0;
        font-size: 0.875rem;
        padding: 12px 8px;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .cart-page .cart-page {
        padding: 1rem 0;
    }
    
    .cart-page .page-title {
        font-size: 1.75rem;
    }
    
    .cart-page .empty-state {
        padding: 2rem 1rem;
    }
    
    .cart-page .empty-state-icon {
        font-size: 3rem;
    }
    
    .cart-page .products-grid.modern-grid {
        grid-template-columns: 1fr;
    }
    
    .cart-page .card-footer {
        flex-direction: column;
    }
    
    .cart-page .summary-actions {
        flex-direction: row;
        gap: 6px;
    }
    
    .cart-page .summary-actions .btn {
        font-size: 0.8rem;
        padding: 10px 6px;
    }
    
    .cart-page .summary-actions .btn-checkout {
        order: 2;
    }
    
    .cart-page .summary-actions .continue-shopping {
        order: 1;
    }
    
    .cart-page .cart-item.modern-item {
        grid-template-columns: 30px 50px 1fr;
        gap: 0.5rem;
        padding: 0.5rem;
    }
    
    .cart-page .item-image {
        width: 50px;
        height: 50px;
    }
    
    .cart-page .item-name {
        font-size: 0.9rem;
    }
    
    .cart-page .current-price {
        font-size: 1rem;
    }
    
    .cart-page .total-price {
        font-size: 1.1rem;
    }
    
    .cart-page .qty-btn {
        width: 28px;
        height: 28px;
    }
    
    .cart-page .qty-input {
        width: 40px;
        height: 28px;
    }
    
    .cart-page .product-offer-badges {
        top: 2px;
        left: 2px;
    }
    
    .cart-page .offer-badge {
        font-size: 0.65rem;
        padding: 2px 4px;
    }
}

/* Animation Keyframes - Scoped */
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

.cart-page .fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

/* Utility Classes - Scoped */
.cart-page .text-success { color: var(--success-color, #10b981); }
.cart-page .text-danger { color: var(--danger-color, #ef4444); }
.cart-page .text-warning { color: var(--warning-color, #f59e0b); }
.cart-page .text-muted { color: var(--text-light, #9ca3af); }

.cart-page .bg-success { background: var(--success-color, #10b981); }
.cart-page .bg-danger { background: var(--danger-color, #ef4444); }
.cart-page .bg-warning { background: var(--warning-color, #f59e0b); }

/* Loading States - Scoped */
.cart-page .loading {
    opacity: 0.7;
    pointer-events: none;
}

.cart-page .loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid var(--border-color, #e5e7eb);
    border-top: 2px solid var(--primary-color, #10b981);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Animation for badge updates - Scoped */
.cart-page .pulse-animation {
    animation: pulse 0.5s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* CSS Variables for cart page only */
.cart-page {
    --primary-color: #10b981;
    --primary-dark: #059669;
    --secondary-color: #6366f1;
    --accent-color: #f59e0b;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --success-color: #10b981;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-gray: #f9fafb;
    --bg-gray-50: #f9fafb;
    --bg-gray-100: #f3f4f6;
    --border-color: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 8px;
    --radius-lg: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>