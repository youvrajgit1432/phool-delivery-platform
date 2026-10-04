 
<?php
// app/views/checkout/index.php
// Enable all error reporting at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any errors
ob_start();

// Include PathConfig for dynamic path handling
include ("../../config/Pathconfig.php");
$pathConfig = PathConfig::getInstance();

$page_title = LanguageHelper::t('checkout', 'Checkout') . " - Phool Delivery";

// Use PathConfig for all URL paths
$base_url = $pathConfig->get('base_url');
$assets_path = $pathConfig->get('assets');
$product_images_url = $pathConfig->get('product_images');
$wallet_qr_url = $pathConfig->get('wallet_qr');
$admin_url = $pathConfig->get('admin');

// Fetch current Nepali date and available years from database
$current_nepali_date = null;
$default_delivery_date = '';
$available_nepali_years = [];
$quick_load = isset($quick_load) && $quick_load === true;

// ALWAYS fetch Nepali date and years from database (regardless of quick_load)
try {
    include ("../../config/Database.php");
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current Nepali date from database
    $stmt = $db->prepare("SELECT year, month, day, weekday FROM current_nepali_date WHERE id = 1 LIMIT 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $current_nepali_date = $stmt->fetch(PDO::FETCH_ASSOC);
        // Format as YYYY-MM-DD for the hidden field
        $default_delivery_date = sprintf("%04d-%02d-%02d", 
            $current_nepali_date['year'], 
            $current_nepali_date['month'], 
            $current_nepali_date['day']
        );
        error_log("DEBUG: Fetched Nepali date from DB: " . $default_delivery_date);
    } else {
        error_log("DEBUG: No current_nepali_date record found in database");
    }
    
    // Fetch available Nepali years from database
    $stmt = $db->prepare("SELECT year FROM nepali_calendar_years ORDER BY year ASC");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $years_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($years_data as $year_row) {
            $available_nepali_years[] = $year_row['year'];
        }
        error_log("DEBUG: Available Nepali years: " . json_encode($available_nepali_years));
    } else {
        error_log("DEBUG: No years found in nepali_calendar_years table");
    }
    
    // If no years in database, use default range
    if (empty($available_nepali_years)) {
        for ($y = 2080; $y <= 2090; $y++) {
            $available_nepali_years[] = $y;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching Nepali data: " . $e->getMessage());
    // Fallback to defaults
    $current_nepali_date = [
        'year' => 2082,
        'month' => 6,
        'day' => 24,
        'weekday' => 5
    ];
    $default_delivery_date = '2082-06-24';
    // Fallback year range
    for ($y = 2080; $y <= 2090; $y++) {
        $available_nepali_years[] = $y;
    }
}

function getCheckoutImagePath($item) {
    global $pathConfig;
    
    // Check for pre-calculated full path
    if (isset($item['image_full_path']) && !empty($item['image_full_path'])) {
        return $item['image_full_path'];
    }
    
    // Check for image field in various formats
    $image_file = null;
    
    // Priority 1: Check 'image' field
    if (isset($item['image']) && !empty($item['image']) && $item['image'] !== 'default.jpg') {
        $image_file = $item['image'];
    }
    // Priority 2: Check 'image_path' field (from database)
    elseif (isset($item['image_path']) && !empty($item['image_path']) && $item['image_path'] !== 'default.jpg') {
        $image_file = $item['image_path'];
    }
    
    // If we have an image file, return the full path
    if (!empty($image_file)) {
        return $pathConfig->getImagePath($image_file, 'product');
    }
    
    // Fallback: Try to fetch from database if product ID is available
    if (isset($item['id']) && !empty($item['id'])) {
        try {
            include_once __DIR__ . '/../../config/Database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
            $stmt->execute([$item['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['image_path'])) {
                return $pathConfig->getImagePath($result['image_path'], 'product');
            }
        } catch (Exception $e) {
            error_log("Error fetching product image: " . $e->getMessage());
        }
    }
    
    // Default placeholder
    return $pathConfig->get('assets') . '/img/products/placeholder.jpg';
}

function getWalletQRImage($wallet_type) {
    global $pathConfig;
    
    try {
       include ("../../config/Database.php");
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT qr_image FROM wallet_qrs WHERE wallet_type = :wallet_type AND status = 'active' LIMIT 1");
        $stmt->bindParam(':wallet_type', $wallet_type);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $qr_image = $row['qr_image'];
            return $pathConfig->getImagePath($qr_image, 'wallet_qr');
        }
    } catch (Exception $e) {
        error_log("Error fetching QR image: " . $e->getMessage());
    }
    
    return $pathConfig->get('assets') . '/img/payment/placeholder_qr.jpg';
}

// MODIFIED: Get customer info with session city fallback for all users
$customer_name = $customer_phone = $customer_email = $customer_address = $customer_city = $customer_contact = '';
$customer_type = 'normal';
$is_email_verified = false;
$is_phone_verified = false;

if ($is_logged_in && $customer && is_array($customer)) {
    $customer_name = $customer['name'] ?? '';
    $customer_phone = $customer['phone'] ?? '';
    $customer_email = $customer['email'] ?? '';
    $customer_address = $customer['address'] ?? '';
    $customer_city = $customer['city'] ?? '';
    $customer_contact = $customer['contact'] ?? '';
    $customer_type = $customer['customer_type'] ?? 'normal';
    
    // FIXED: Check email_verified_at datetime field instead of non-existent email_verified field
    $is_email_verified = !empty($customer['email_verified_at']);
    
    // FIXED: Check phone_verified_at datetime field instead of verification_status
    $is_phone_verified = (
        !empty($customer['phone_verified_at']) ||
        (isset($_SESSION['login_method']) && $_SESSION['login_method'] === 'otp')
    );
} else {
    // For logged-out users, use session city if available
    $customer_city = $_SESSION['user_city_name'] ?? '';
}

// ADDED: Calculate total quantity in cart
$total_quantity = 0;
foreach ($cart as $item) {
    $total_quantity += isset($item['quantity']) ? (int)$item['quantity'] : 0;
}

// ADDED: Determine if phone verification is required
// Required if: customer is bulk type OR total quantity >= 5
$is_verification_required = ($customer_type === 'bulk' || $total_quantity >= 5);

// FIXED: Always show verification warning if phone is not verified
// Remove the condition that checks $is_verification_required for showing the warning
// We want to show optional warning for all unverified users, and required warning for bulk/5+ items
$show_verification_warning = $is_logged_in && !$is_phone_verified;

$available_cities = [];
$city_delivery_fees = [];
$city_ids = [];
$minimum_quantities = [];
$product_offers = [];
$special_events = [];

if (!$quick_load) {
    try {
         include ("../../config/Database.php");
        $database = new Database();
        $db = $database->getConnection();
        
        // Get cities with delivery fees
        $stmt = $db->prepare("SELECT id, city_name, standard_delivery_fee FROM delivery_cities ORDER BY city_name");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $available_cities[] = $row['city_name'];
            $city_delivery_fees[$row['city_name']] = (float)$row['standard_delivery_fee'];
            $city_ids[$row['city_name']] = $row['id'];
        }
        
        // Get minimum quantities
        $stmt = $db->prepare("SELECT pmq.product_id, pmq.city_id, pmq.minimum_quantity, pmq.unit, p.name_en as product_name, p.unit as product_unit, dc.city_name FROM product_minimum_quantities pmq JOIN products p ON pmq.product_id = p.id JOIN delivery_cities dc ON pmq.city_id = dc.id WHERE p.status = 'active'");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $minimum_quantities[$row['product_id']][$row['city_id']] = [
                'minimum_quantity' => (float)$row['minimum_quantity'],
                'unit' => $row['unit'],
                'product_name' => $row['product_name'],
                'product_unit' => $row['unit'],
                'city_name' => $row['city_name']
            ];
        }
        
        // Get product offers
        $current_date = date('Y-m-d');
        $stmt = $db->prepare("SELECT po.*, p.name_en as product_name FROM product_offers po JOIN products p ON po.product_id = p.id WHERE po.is_active = 1 AND (po.start_date <= :current_date OR po.start_date IS NULL) AND (po.end_date >= :current_date OR po.end_date IS NULL)");
        $stmt->bindParam(':current_date', $current_date);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $product_offers[$row['product_id']][] = $row;
        }
        
        // Get special events
        $stmt = $db->prepare("SELECT event_name, event_description, discount_percentage, start_date, end_date FROM special_events WHERE is_active = 1 AND start_date <= :current_date AND end_date >= :current_date");
        $stmt->bindParam(':current_date', $current_date);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $special_events[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error fetching data: " . $e->getMessage());
        $available_cities = ['Banepa', 'Kathmandu', 'Bhaktapur', 'Dhulikhel'];
        $city_delivery_fees = ['Banepa' => 50.00, 'Kathmandu' => 100.00, 'Bhaktapur' => 80.00, 'Dhulikhel' => 60.00];
    }
} else {
    // Quick-load: fetch delivery cities AND minimum quantities (lightweight but necessary)
    try {
        include ("../../config/Database.php");
        $database = new Database();
        $db = $database->getConnection();

        // Get cities with delivery fees
        $stmt = $db->prepare("SELECT id, city_name, standard_delivery_fee FROM delivery_cities ORDER BY city_name");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $available_cities[] = $row['city_name'];
            $city_delivery_fees[$row['city_name']] = (float)$row['standard_delivery_fee'];
            $city_ids[$row['city_name']] = $row['id'];
        }

        // CRITICAL: Get minimum quantities even in quick-load (needed for validation)
        $stmt = $db->prepare("SELECT pmq.product_id, pmq.city_id, pmq.minimum_quantity, pmq.unit, p.name_en as product_name, p.unit as product_unit, dc.city_name FROM product_minimum_quantities pmq JOIN products p ON pmq.product_id = p.id JOIN delivery_cities dc ON pmq.city_id = dc.id WHERE p.status = 'active'");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $minimum_quantities[$row['product_id']][$row['city_id']] = [
                'minimum_quantity' => (float)$row['minimum_quantity'],
                'unit' => $row['unit'],
                'product_name' => $row['product_name'],
                'product_unit' => $row['unit'],
                'city_name' => $row['city_name']
            ];
        }
    } catch (Exception $e) {
        error_log("Quick-load: Error fetching cities and minimums: " . $e->getMessage());
        $available_cities = [];
        $city_delivery_fees = [];
        $city_ids = [];
        $minimum_quantities = [];
    }

    // Keep other heavier datasets empty in quick-load
    $product_offers = [];
    $special_events = [];
}

// MODIFIED: Get delivery fee based on session city for all users
$delivery_fee = 50.00;
$session_city_id = $_SESSION['user_city_id'] ?? null;
if ($session_city_id && isset($db)) {
    try {
        $query = "SELECT standard_delivery_fee FROM delivery_cities WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_city_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['standard_delivery_fee'])) {
            $delivery_fee = floatval($result['standard_delivery_fee']);
        }
    } catch (Exception $e) {
        error_log("Error fetching delivery fee: " . $e->getMessage());
    }
}

// Check if we have any active offers to determine whether to show the offers section
$has_active_offers = !empty($product_offers) || !empty($special_events);

// Calculate subtotal with bulk pricing and discounts applied
$subtotal = 0;
foreach ($cart as $item) {
    // Use item_total which already has all discounts (bulk + offers) applied
    $subtotal += $item['item_total'];
}

// Calculate original subtotal (before any discounts or bulk pricing)
$original_subtotal = 0;
foreach ($cart as $item) {
    $original_subtotal += $item['original_price'] * $item['quantity'];
}

// Calculate bulk savings
$bulk_savings = 0;
foreach ($cart as $item) {
    if ($item['is_bulk']) {
        $bulk_savings += ($item['original_price'] - $item['final_price']) * $item['quantity'];
    }
}

// Calculate product offers discounts
// FIXED: Use actual offer_discount from cart items (already calculated by controller)
$product_offers_discount = 0;
foreach ($cart as $item) {
    if (isset($item['offer_discount']) && $item['offer_discount'] > 0) {
        $product_offers_discount += $item['offer_discount'];
    }
}

$discount_breakdown = [];
$total_discount = 0;

if ($product_offers_discount > 0) {
    $discount_breakdown[] = [
        'type' => 'offer',
        'name' => 'Product Offers',
        'amount' => $product_offers_discount,
        'percentage' => 0
    ];
    $total_discount += $product_offers_discount;
}

// Calculate special event discount
$event_discount_amount = 0;
if (!empty($special_events) && $subtotal > 0) {
    $event_discount_percentage = 0;
    foreach ($special_events as $event) {
        $event_discount_percentage += (float)$event['discount_percentage'];
    }
    if ($event_discount_percentage > 0) {
        $event_discount_amount = ($subtotal * $event_discount_percentage) / 100;
        $discount_breakdown[] = [
            'type' => 'event',
            'name' => 'Special Event Discount',
            'amount' => $event_discount_amount,
            'percentage' => $event_discount_percentage
        ];
        $total_discount += $event_discount_amount;
    }
}

// Calculate the actual total with cumulative discounts
$actual_total = $subtotal - $total_discount + $delivery_fee;
?>
<a href="<?= $pathConfig->url('home') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        font-size: 24px;
        color: #333;
        text-decoration: none;
        transition: color 0.2s ease;
   "
   onmouseover="this.style.color='#777';"
   onmouseout="this.style.color='#333';"
>
    <i class="fas fa-arrow-left" style="margin-right: 6px;"></i> 
</a>

<section class="checkout-page">
    
    <div class="container">
        
        <!-- Progress Indicator -->
        <div class="checkout-progress" data-aos="fade-up" data-aos-delay="100">
            <?php for($i=1;$i<=4;$i++): ?>
            <div class="progress-step <?=$i==1?'active':''?>" data-step="<?=$i?>">
                <span class="step-number"><?=$i?></span>
                <span class="step-label"><?=[LanguageHelper::t('order_summary', 'Order Summary'), LanguageHelper::t('personal_info', 'Personal Info'), LanguageHelper::t('delivery_details', 'Delivery Details'), LanguageHelper::t('payment', 'Payment')][$i-1]?></span>
            </div>
            <?php endfor; ?>
        </div>
        
        <h1 class="checkout-title" data-aos="fade-up" data-aos-delay="200"><?= LanguageHelper::t('checkout', 'Checkout') ?></h1>
        
        <!-- Verification Warning -->
        <div id="verificationWarning" class="verification-warning <?=$is_verification_required ? 'required' : 'optional'?>" style="<?=$show_verification_warning ? '' : 'display: none;'?>" data-aos="fade-up" data-aos-delay="300">
            <div class="warning-icon"><?=$is_verification_required ? '🔴' : '⚠️'?></div>
            <div class="warning-content">
                <?php if ($is_verification_required): ?>
                    <!-- Required Verification Message -->
                    <h3><?= LanguageHelper::t('phone_verification_required', 'Phone Verification Recommended') ?></h3>
                    <p><?php 
                        if ($customer_type === 'bulk') {
                            echo LanguageHelper::t('bulk_verification_required', 'Since you are a bulk buyer, we strongly recommend phone verification. Click below to verify now, or you can skip this step and proceed without verification.');
                        } else {
                            echo LanguageHelper::t('quantity_verification_required', 'Since your order quantity is 5 or more items, we strongly recommend phone verification. Click below to verify now, or you can skip this step and proceed without verification.');
                        }
                    ?> 
                    <a href="#" id="verifyAccountBtn" class="verify-link"><?= LanguageHelper::t('verify_now', 'Verify now') ?></a></p>
                <?php else: ?>
                    <!-- Optional Verification Message -->
                    <h3><?= LanguageHelper::t('phone_verification_recommended', 'Phone Verification Recommended') ?></h3>
                    <p><?= LanguageHelper::t('phone_not_verified_message', 'Your phone number is not verified. You can still place your order, but we recommend') ?> 
                    <a href="#" id="verifyAccountBtn" class="verify-link"><?= LanguageHelper::t('verifying_your_phone', 'verifying your phone') ?></a> <?= LanguageHelper::t('for_faster_processing', 'for faster processing and better service.') ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Verification Form -->
        <div id="verificationFormContainer" class="verification-form-container" style="display: none;">
            <div class="verification-form">
                <h3><?= LanguageHelper::t('verify_your_phone_number', 'Verify Your Phone Number') ?></h3>
                <p><?= LanguageHelper::t('enter_verification_code', 'Please enter the verification code sent to your phone.') ?></p>
                <div class="form-group">
                    <label for="verification_code"><?= LanguageHelper::t('verification_code', 'Verification Code') ?></label>
                    <input type="text" id="verification_code" name="verification_code" class="form-input" placeholder="<?= LanguageHelper::t('enter_6_digit_code', 'Enter 6-digit code') ?>">
                </div>
                <div class="verification-actions" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                    <button type="button" id="submitVerificationBtn" class="btn btn-primary checkout-btn-primary" style="flex: 1; min-width: 120px;"><?= LanguageHelper::t('verify_account', 'Verify Account') ?></button>
                    <button type="button" id="cancelVerificationBtn" class="btn btn-secondary checkout-btn-secondary" style="flex: 1; min-width: 80px;"><?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                    <button type="button" id="resendCodeBtn" class="btn btn-link checkout-btn-link" style="flex: 1 100%; text-align: center;"><?= LanguageHelper::t('resend_code', 'Resend Code') ?></button>
                </div>
                <!-- Resend Cooldown Timer -->
                <div id="resendCooldownTimer" style="display: none; margin-top: 15px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404; text-align: center; font-size: 14px;">
                    <strong>⏱️ <?= LanguageHelper::t('resend_in', 'Resend in') ?> <span id="cooldownSeconds">60</span>s</strong>
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <div id="addressUpdateMessage" class="alert-message" style="display: none;"></div>
        <div id="verificationMessage" class="alert-message" style="display: none;"></div>
        <div id="minimumQuantityMessage" class="alert-message" style="display: none;"></div>
        
        <!-- Guest Phone Number Capture Form -->
        <div id="guestPhoneFormContainer" class="verification-form-container" style="display: none;">
            <div class="verification-form">
                <h3><?= LanguageHelper::t('enter_phone_number', 'Enter Your Phone Number') ?></h3>
                <p><?= LanguageHelper::t('phone_required_for_verification', 'Your phone number is required to send the verification code.') ?></p>
                <div class="form-group">
                    <label for="guest_phone_number"><?= LanguageHelper::t('phone_number', 'Phone Number') ?> *</label>
                    <input type="tel" id="guest_phone_number" name="guest_phone_number" class="form-input" placeholder="<?= LanguageHelper::t('enter_10_digit_number', 'Enter 10-digit phone number') ?>" pattern="[0-9]{10}" required>
                </div>
                <div class="verification-actions">
                    <button type="button" id="submitPhoneNumberBtn" class="btn btn-primary checkout-btn-primary"><?= LanguageHelper::t('send_code', 'Send Code') ?></button>
                    <button type="button" id="cancelPhoneNumberBtn" class="btn btn-secondary checkout-btn-secondary"><?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                </div>
            </div>
        </div>
        
        <!-- Main Checkout Container -->
        <div class="checkout-container">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <form id="checkoutForm" enctype="multipart/form-data">
                    <?php if ($is_logged_in): ?><input type="hidden" name="terms" value="1"><?php endif; ?>
                    
                    <!-- Hidden fields to store calculated discount values for order storage -->
                    <input type="hidden" id="applied_discount_field" name="applied_discount" value="<?=number_format($total_discount, 2, '.', '')?>">
                    <input type="hidden" id="final_amount_field" name="final_amount" value="<?=number_format($actual_total, 2, '.', '')?>">
                    <input type="hidden" id="subtotal_field" name="subtotal" value="<?=number_format($subtotal, 2, '.', '')?>">
                    <input type="hidden" id="bulk_savings_field" name="bulk_savings" value="<?=number_format($bulk_savings, 2, '.', '')?>">
                    <input type="hidden" id="product_offers_discount_field" name="product_offers_discount" value="<?=number_format($product_offers_discount, 2, '.', '')?>">
                    <input type="hidden" id="event_discount_field" name="event_discount" value="<?=number_format($event_discount_amount, 2, '.', '')?>">
                    
                    <!-- Step 1: Order Summary -->
                    <div class="form-step active" data-step="1">
                        <h2 class="step-title" data-aos="fade-up" data-aos-delay="400"><?= LanguageHelper::t('order_summary', 'Order Summary') ?></h2>
                        
                        <!-- City Selection for Step 1 -->
                        <div class="form-group" style="margin-bottom: 20px;" data-aos="fade-up" data-aos-delay="450">
                            <label for="city-step1" style="font-weight: 600; margin-bottom: 8px; display: block;"><?= LanguageHelper::t('delivery_city', 'Delivery City') ?> *</label>
                            <select id="city-step1" name="city_step1" class="form-input" onchange="handleCityChangeStep1()" style="padding: 12px; border: 2px solid #17a2b8; border-radius: 8px; font-size: 15px;">
                                <option value="">-- <?= LanguageHelper::t('select_city', 'Select City') ?> --</option>
                                <?php foreach ($available_cities as $city): ?>
                                    <option value="<?=htmlspecialchars($city)?><?php echo $city_ids && isset($city_ids[$city]) ? '|' . $city_ids[$city] : ''; ?>" 
                                            <?= ($customer_city == $city) ? 'selected' : '' ?> 
                                            data-fee="<?= $city_delivery_fees[$city] ?? 0.00 ?>" 
                                            data-city-id="<?= $city_ids[$city] ?? '' ?>">
                                        <?=htmlspecialchars($city)?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Hidden terms for logged-in users (backup) -->
                        <?php if ($is_logged_in): ?><input type="hidden" name="terms_hidden" value="1"><?php endif; ?>
                         
                            <?php if ($has_active_offers): ?>
                            <div class="offers-section" id="offersSection" style="display: none;">
                                <h4 class="offers-title">🎉 <?= LanguageHelper::t('special_offers', 'Special Offers & Discounts') ?></h4>
                                <div class="offers-list" id="offersList"></div>
                                <button type="button" class="btn-show-offers checkout-btn-link" onclick="toggleOffers()"><?= LanguageHelper::t('show_offers', 'Show Offers') ?></button>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Delivery Info -->
                            <div class="delivery-info-section" id="deliveryInfoSection" style="display: none;">
                                <div class="delivery-message" id="deliveryMessage">
                                    <?php if ($customer_city && $delivery_fee == 0): ?>
                                        <div class="free-delivery-banner">
                                            <span class="delivery-icon">🚚</span>
                                            <div class="delivery-text">
                                                <strong>🎉 <?= LanguageHelper::t('hurray', 'Hurray!') ?></strong>
                                                <span><?= LanguageHelper::t('free_delivery_selected_city', 'Free delivery for your selected city!') ?></span>
                                            </div>
                                        </div>
                                    <?php elseif ($customer_city): ?>
                                        <div class="standard-delivery-message">
                                            <span class="delivery-icon">📦</span>
                                            <span><?= LanguageHelper::t('standard_delivery_fee_applied', 'Standard delivery fee applied') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="select-city-message">
                                            <span class="delivery-icon">📍</span>
                                            <span><?= LanguageHelper::t('select_city_for_delivery', 'Select a city to see delivery options') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-show-delivery checkout-btn-link" onclick="toggleDeliveryInfo()"><?= LanguageHelper::t('show_delivery_info', 'Show Delivery Info') ?></button>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="order-items">
                                <?php foreach ($cart as $index => $item): 
                                    $hasOffer = isset($product_offers[$item['id']]);
                                    $currentOffers = $product_offers[$item['id']] ?? [];
                                    $buyXGetYOffer = null;
                                    foreach ($currentOffers as $offer) {
                                        if ($offer['offer_type'] == 'buy_x_get_y') {
                                            $buyXGetYOffer = $offer;
                                            break;
                                        }
                                    }
                                ?>
                                <div class="order-item" data-product-id="<?=$item['id']?>" data-aos="fade-up" data-aos-delay="<?= $index * 100 + 600 ?>">
                                    <div class="item-image">
                                        <img src="<?=getCheckoutImagePath($item)?>" alt="<?=htmlspecialchars($item['name'])?>" onerror="this.src='<?=$assets_path?>/img/products/placeholder.jpg'">
                                        <?php if ($hasOffer): ?><div class="offer-badge">🔥 <?= LanguageHelper::t('offer', 'Offer') ?></div><?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <h4 class="item-name"><?=htmlspecialchars($item['name'])?></h4>
                                        <div class="price-info">
                                            <?php if ($item['is_bulk']): ?>
                                                <div class="original-price"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($item['original_price'],2)?></div>
                                                <div class="final-price"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($item['final_price'],2)?> <?= LanguageHelper::t('per', 'per') ?> <?=$item['unit']?></div>
                                                <div class="bulk-badge">📦 <?= LanguageHelper::t('bulk_price', 'Bulk Price') ?></div>
                                            <?php else: ?>
                                                <div class="final-price"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($item['final_price'],2)?> <?= LanguageHelper::t('per', 'per') ?> <?=$item['unit']?></div>
                                            <?php endif; ?>
                                            <?php if ($hasOffer): ?><div class="offer-badge-text">🎁 <?= LanguageHelper::t('special_offer', 'Special Offer') ?></div><?php endif; ?>
                                        </div>
                                        
                                        <div class="quantity-section">
                                            <div class="current-quantity-display">
                                                <span class="quantity-label"><?= LanguageHelper::t('current_quantity', 'Current Quantity') ?>:</span>
                                                <span class="quantity-value" id="current-quantity-<?=$item['id']?>">
                                                    <?=$item['quantity']?> <?=$item['unit']?>
                                                    <?php if ($buyXGetYOffer && $item['quantity'] >= $buyXGetYOffer['buy_quantity']): ?>
                                                        <?php
                                                        $freeQuantity = floor($item['quantity'] / $buyXGetYOffer['buy_quantity']) * $buyXGetYOffer['get_quantity'];
                                                        $totalQuantity = $item['quantity'] + $freeQuantity;
                                                        ?>
                                                        <span class="free-quantity-info">(<?= LanguageHelper::t('including', 'including') ?> <?=$freeQuantity?> <?= LanguageHelper::t('free', 'free') ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="quantity-controls">
                                                <button type="button" class="quantity-btn minus checkout-quantity-btn" onclick="adjustQuantity(<?=$item['id']?>, -1)">-</button>
                                                <input type="number" class="quantity-input" id="quantity-input-<?=$item['id']?>" value="<?=$item['quantity']?>" min="1" onchange="updateQuantityFromInput(<?=$item['id']?>)" onkeydown="handleQuantityKeydown(event, <?=$item['id']?>)">
                                                <button type="button" class="quantity-btn plus checkout-quantity-btn" onclick="adjustQuantity(<?=$item['id']?>, 1)">+</button>
                                            </div>
                                        </div>
                                        
                                        <?php if ($item['total_saving'] > 0): ?>
                                        <div class="savings-info">
                                            <small class="text-success">💰 <?= LanguageHelper::t('you_save', 'You save') ?>: <?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($item['total_saving'],2)?></small>
                                            <?php if (!empty($item['discount_reason'])): ?><small class="text-muted">(<?=$item['discount_reason']?>)</small><?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($hasOffer): ?>
                                        <div class="offer-info" id="offer-info-<?=$item['id']?>">
                                            <?php foreach ($currentOffers as $offer): ?>
                                            <div class="offer-detail">
                                                <small class="text-success">🎊 
                                                    <?php if ($offer['offer_type'] == 'percentage_discount'): ?>
                                                        <?= LanguageHelper::t('get_discount', 'Get') ?> <?=$offer['discount_percentage']?>% <?= LanguageHelper::t('off', 'off') ?>
                                                    <?php elseif ($offer['offer_type'] == 'fixed_discount'): ?>
                                                        <?= LanguageHelper::t('save_rs', 'Save Rs.') ?> <?=number_format($offer['fixed_discount'],2)?>
                                                    <?php elseif ($offer['offer_type'] == 'buy_x_get_y'): ?>
                                                        <?= LanguageHelper::t('buy_get', 'Buy') ?> <?=$offer['buy_quantity']?> <?= LanguageHelper::t('get', 'get') ?> <?=$offer['get_quantity']?> <?= LanguageHelper::t('free', 'free') ?>
                                                    <?php endif; ?>
                                                    <?= $offer['buy_quantity'] > 0 ? LanguageHelper::t('on_minimum', 'on minimum') . ' ' . $offer['buy_quantity'] . ' ' . $item['unit'] : LanguageHelper::t('for_any_quantity', 'for any quantity!') ?>
                                                </small>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="minimum-quantity-info" id="min-quantity-info-<?=$item['id']?>" style="display: none;"><small class="text-warning"></small></div>
                                    </div>
                                    <div class="item-total">
                                        <span class="total-price" id="total-<?=$item['id']?>"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($item['item_total'],2)?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Summary Totals -->
                            <div class="summary-totals">
                                <div class="summary-row"><span><?= LanguageHelper::t('subtotal', 'Subtotal') ?>:</span><span id="subtotal-amount"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($subtotal,2)?></span></div>
                                
                                <?php if (!empty($discount_breakdown)): ?>
                                    <?php foreach ($discount_breakdown as $discount): ?>
                                    <div class="summary-row discount" data-type="<?=$discount['type']?>">
                                        <span>
                                            <?php if ($discount['type'] == 'event'): ?>
                                                🎉 <?= $discount['name'] ?> (<?= $discount['percentage'] ?>%)
                                            <?php else: ?>
                                                🎁 <?= $discount['name'] ?>
                                            <?php endif; ?>
                                        </span>
                                        <span>-<?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($discount['amount'],2)?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="summary-row delivery-fee-row">
                                    <span><?= LanguageHelper::t('delivery_fee', 'Delivery Fee') ?>:</span>
                                    <span id="delivery-fee">
                                        <?php if ($delivery_fee == 0): ?>
                                            <span class="free-delivery">🎉 <?= LanguageHelper::t('free', 'FREE') ?></span>
                                        <?php else: ?>
                                            <?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($delivery_fee,2)?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="summary-row total"><span><?= LanguageHelper::t('total', 'Total') ?>:</span><span id="total-amount"><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($actual_total,2)?></span></div>
                            </div>
                        
                        
                        <div class="step-actions" style="display: flex; justify-content: flex-end; margin-top: 20px !important;"  >
                            <button type="button" class="btn btn-next checkout-btn-next" data-next="2" style="background-color: #007bff !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.3s ease !important; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;" onmouseover="this.style.backgroundColor='#0056b3'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#007bff'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('next_personal_info', 'Next: Personal Information') ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Personal Information -->
                    <div class="form-step" data-step="2">
                        <h2 class="step-title" data-aos="fade-up" data-aos-delay="100"><?= LanguageHelper::t('personal_information', 'Personal Information') ?></h2>
                        <?php if ($is_logged_in): ?>
                        <div class="logged-in-notice" data-aos="fade-up" data-aos-delay="200">
                            <div class="address-display">
                                <h4><?= LanguageHelper::t('current_delivery_address', 'Current Delivery Address') ?>:</h4>
                                <p id="currentAddressDisplay"><?=htmlspecialchars($customer_address)?>, <?=htmlspecialchars($customer_city)?></p>
                            </div>
                            <div class="address-actions" style="margin-top: 10px;">
    <a href="<?= $pathConfig->url('account/profile') ?>" 
       class="btn-change-address"
       style="
           display: inline-flex;
           align-items: center;
           gap: 6px;
           padding: 8px 14px;
           background: #007bff;
           color: #fff;
           border-radius: 6px;
           text-decoration: none;
           font-size: 14px;
       ">
        <i class="fas fa-edit"></i> 
        <?= LanguageHelper::t('change_address', 'Change Address') ?>
    </a>
</div>

                        </div>
                        <?php endif; ?>
                        
                        <div class="form-section address-section" id="addressSection">
                            <?php if (!$is_logged_in): ?>
                            <div class="guest-notice" data-aos="fade-up" data-aos-delay="300">
                                <p><?= LanguageHelper::t('guest_checkout_message', 'You\'re checking out as a guest. Your information will be saved for future orders.') ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- FOR LOGGED-IN USERS: Collapsible Dropdown for Delivery Details -->
                            <?php if ($is_logged_in): ?>
                            <div class="delivery-details-dropdown" style="margin-bottom: 20px;">
                                <button type="button" class="dropdown-toggle" onclick="toggleDeliveryDetailsDropdown()" style="
                                    display: flex;
                                    align-items: center;
                                    gap: 10px;
                                    width: 100%;
                                    padding: 15px;
                                    background: #f9f9f9;
                                    border: 2px solid #28a745;
                                    border-radius: 8px;
                                    cursor: pointer;
                                    font-size: 16px;
                                    font-weight: 600;
                                    color: #333;
                                    transition: all 0.3s ease;
                                " onmouseover="this.style.backgroundColor='#f0f0f0';" onmouseout="this.style.backgroundColor='#f9f9f9';">
                                    <span id="dropdownIcon" style="font-size: 18px;">▼</span>
                                    <span><?= LanguageHelper::t('your_delivery_details', 'Your Delivery Details') ?></span>
                                </button>
                                
                                <div id="deliveryDetailsContent" class="dropdown-content" style="
                                    max-height: 0;
                                    overflow: hidden;
                                    transition: max-height 0.3s ease;
                                ">
                                    <div style="background: #f9f9f9; padding: 20px; border: 1px solid #28a745; border-top: none; border-radius: 0 0 8px 8px;">
                                        <p style="margin: 0 0 15px 0; font-size: 13px; color: #666; font-style: italic;">
                                            📋 <?= LanguageHelper::t('order_specific_details', 'The details below are specifically for this order. Your profile information will not be affected. Leave blank if you don\'t want to change.') ?>
                                        </p>
                                        
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                            <div style="flex: 1; min-width: 200px;">
                                                <small style="color: #666; font-weight: 600;"><?= LanguageHelper::t('full_name', 'Full Name') ?></small>
                                                <p style="margin: 5px 0 0 0; color: #333; font-size: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #28a745;"><?=htmlspecialchars($customer_name)?></p>
                                            </div>
                                            <div style="flex: 1; min-width: 200px;">
                                                <small style="color: #666; font-weight: 600;"><?= LanguageHelper::t('phone_number', 'Phone Number') ?></small>
                                                <div style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #28a745; margin-top: 5px;">
                                                    <p style="margin: 0; color: #333; font-size: 15px;"><?=htmlspecialchars($customer_phone)?></p>
                                                    <?php if ($is_phone_verified): ?>
                                                    <span style="color: #28a745; font-size: 12px; font-weight: 600;">✓ <?= LanguageHelper::t('verified', 'Verified') ?></span>
                                                    <?php else: ?>
                                                    <span style="color: #dc3545; font-size: 12px; font-weight: 600;">✗ <?= LanguageHelper::t('not_verified', 'Not Verified') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px;">
                                            <div style="flex: 1; min-width: 200px;">
                                                <small style="color: #666; font-weight: 600;"><?= LanguageHelper::t('email_address', 'Email Address') ?></small>
                                                <div style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #28a745; margin-top: 5px;">
                                                    <p style="margin: 0; color: #333; font-size: 15px; word-break: break-all;"><?=htmlspecialchars($customer_email)?></p>
                                                    <?php if ($is_email_verified): ?>
                                                    <span style="color: #28a745; font-size: 12px; font-weight: 600;">✓ <?= LanguageHelper::t('verified', 'Verified') ?></span>
                                                    <?php else: ?>
                                                    <span style="color: #dc3545; font-size: 12px; font-weight: 600;">✗ <?= LanguageHelper::t('not_verified', 'Not Verified') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="flex: 1; min-width: 200px;">
                                                <small style="color: #666; font-weight: 600;"><?= LanguageHelper::t('address', 'Address') ?></small>
                                                <p style="margin: 5px 0 0 0; color: #333; font-size: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #28a745;"><?=htmlspecialchars($customer_address)?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order-Specific Optional Information -->
                            <div style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #87ceeb;">
                                <h5 style="margin: 0 0 15px 0; color: #0066cc; font-size: 14px; font-weight: 600;">
                                    <?= LanguageHelper::t('optional_info', 'Optional Information - Order Specific') ?>
                                </h5>
                                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                    <div class="form-group" data-aos="fade-up" data-aos-delay="400" style="flex: 1; min-width: 250px;">
                                        <label for="contact" style="display: flex; align-items: center; gap: 6px;">
                                            <span><?= LanguageHelper::t('secondary_contact_number', 'Secondary Contact Number') ?></span>
                                            <span style="font-size: 12px; color: #999;"><?= LanguageHelper::t('optional', '(Optional)') ?></span>
                                        </label>
                                        <input type="tel" id="contact" name="contact" class="form-input" value="<?=htmlspecialchars($customer_contact)?>" placeholder="<?= LanguageHelper::t('enter_secondary_contact', 'Enter alternate contact number') ?>">
                                        <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">
                                            <?= LanguageHelper::t('secondary_contact_help', 'Provide an alternate contact number for delivery') ?>
                                        </small>
                                    </div>
                                    
                                    <div class="form-group" data-aos="fade-up" data-aos-delay="450" style="flex: 1; min-width: 250px;">
                                        <label for="address_delivery" style="display: flex; align-items: center; gap: 6px;">
                                            <span><?= LanguageHelper::t('delivery_address', 'Delivery Address') ?></span>
                                            <span style="font-size: 12px; color: #999;"><?= LanguageHelper::t('optional', '(Optional)') ?></span>
                                        </label>
                                        <textarea id="address_delivery" name="address" class="form-input" rows="2" placeholder="<?= LanguageHelper::t('delivery_address_help', 'If you want to change the delivery address for this order, update it here') ?>"><?=htmlspecialchars($customer_address)?></textarea>
                                        <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">
                                            <?= LanguageHelper::t('leave_blank_help', 'Leave blank to use your profile address') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- FOR GUEST USERS: Show form to fill in details -->
                            <div class="form-row">
                                <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                                    <label for="name"><?= LanguageHelper::t('full_name', 'Full Name') ?> *</label>
                                    <input type="text" id="name" name="name" class="form-input" value="<?=htmlspecialchars($customer_name)?>" required>
                                </div>
                                <div class="form-group" data-aos="fade-up" data-aos-delay="500">
                                    <label for="phone"><?= LanguageHelper::t('phone_number', 'Phone Number') ?> *</label>
                                    <div class="input-with-status">
                                        <input type="tel" id="phone" name="phone" class="form-input" value="<?=htmlspecialchars($customer_phone)?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="600">
                                <label for="email"><?= LanguageHelper::t('email_address', 'Email Address') ?></label>
                                <input type="email" id="email" name="email" class="form-input" value="<?=htmlspecialchars($customer_email)?>">
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="700">
                                <label for="address"><?= LanguageHelper::t('address', 'Address') ?> *</label>
                                <textarea id="address" name="address" class="form-input" rows="2" required><?=htmlspecialchars($customer_address)?></textarea>
                            </div>
                            
                            <!-- Secondary Contact Number for Guest Users -->
                            <div class="form-group" data-aos="fade-up" data-aos-delay="750">
                                <label for="contact" style="display: flex; align-items: center; gap: 6px;">
                                    <span><?= LanguageHelper::t('secondary_contact_number', 'Secondary Contact Number') ?></span>
                                    <span style="font-size: 12px; color: #999;"><?= LanguageHelper::t('optional', '(Optional)') ?></span>
                                </label>
                                <input type="tel" id="contact" name="contact" class="form-input" value="<?=htmlspecialchars($customer_contact)?>" placeholder="<?= LanguageHelper::t('enter_secondary_contact', 'Enter secondary contact number') ?>">
                                <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">
                                    <?= LanguageHelper::t('secondary_contact_help', 'Provide an alternate contact number for delivery') ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group" data-aos="fade-up" data-aos-delay="800">
                                    <label for="city"><?= LanguageHelper::t('city', 'City') ?> *</label>
                                    <select id="city" name="city" class="form-input" required onchange="handleCityChange()">
                                        <option value="">-- <?= LanguageHelper::t('select_city', 'Select City') ?> --</option>
                                        <?php foreach ($available_cities as $city): ?>
                                            <option value="<?=htmlspecialchars($city)?>" <?= ($customer_city == $city) ? 'selected' : '' ?> data-fee="<?= $city_delivery_fees[$city] ?? 0.00 ?>" data-city-id="<?= $city_ids[$city] ?? '' ?>"><?=htmlspecialchars($city)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text" id="delivery-fee-info">
                                        <?php if ($customer_city && $delivery_fee > 0): ?>
                                            <?= LanguageHelper::t('delivery_fee_for', 'Delivery fee for') ?> <?=htmlspecialchars($customer_city)?>: <?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($delivery_fee, 2)?>
                                        <?php elseif ($customer_city && $delivery_fee == 0): ?>
                                            🎉 <?= LanguageHelper::t('hurray_free_delivery', 'Hurray! Free delivery for') ?> <?=htmlspecialchars($customer_city)?>!
                                        <?php else: ?>
                                            <?= LanguageHelper::t('select_city_see_fee', 'Select a city to see delivery fee') ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="form-group" data-aos="fade-up" data-aos-delay="900" style="display: none;">
                                    <label for="street"><?= LanguageHelper::t('street', 'Street') ?></label>
                                    <input type="text" id="street" name="street" class="form-input" value="<?=htmlspecialchars($customer_street)?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="step-actions" style="display: flex; justify-content: space-between; margin-top: 20px !important;"  >
                            <button type="button" class="btn btn-prev checkout-btn-prev" data-prev="1" style="background-color: #f1f1f1 !important; color: #333 !important; border: 1px solid #ccc !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.3s ease !important;" onmouseover="this.style.backgroundColor='#e2e2e2'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#f1f1f1'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('back', 'Back') ?>
                            </button>
                            <button type="button" class="btn btn-next checkout-btn-next" data-next="3" style="background-color: #007bff !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.3s ease !important; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;" onmouseover="this.style.backgroundColor='#0056b3'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#007bff'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('next_delivery_details', 'Next: Delivery Details') ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Delivery Details -->
                    <div class="form-step" data-step="3">
                        <h2 class="step-title" data-aos="fade-up" data-aos-delay="100"><?= LanguageHelper::t('delivery_time_instructions', 'Delivery Time & Instructions') ?></h2>
                        <div class="form-row">
                            <!-- Hidden field to store the formatted date for backend -->
                            <input type="hidden" id="delivery_date" name="delivery_date" value="<?=$default_delivery_date?>" required>
                            
                            <div class="form-group" style="display: flex; flex-direction: column; margin-bottom: 20px;" data-aos="fade-up" data-aos-delay="200">
                                <label for="nepali_date"><?= LanguageHelper::t('delivery_date', 'Delivery Date') ?> *</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">

                                    <!-- Year Dropdown -->
                                    <select id="nepali_year" name="nepali_year" style="padding:6px 10px; border:1px solid #ccc; border-radius:5px;" required>
                                        <option value="" selected disabled><?= LanguageHelper::t('year','Year') ?></option>
                                    </select>

                                    <!-- Month Dropdown -->
                                    <select id="nepali_month" name="nepali_month" style="padding:6px 10px; border:1px solid #ccc; border-radius:5px;" required>
                                        <option value="" selected disabled><?= LanguageHelper::t('month','Month') ?></option>
                                    </select>

                                    <!-- Day Dropdown -->
                                    <select id="nepali_day" name="nepali_day" style="padding:6px 10px; border:1px solid #ccc; border-radius:5px;" required>
                                        <option value="" selected disabled><?= LanguageHelper::t('day','Day') ?></option>
                                    </select>

                                </div>
                                <small class="form-text"><?= LanguageHelper::t('select_nepali_date', 'Please select the delivery date in Nepali calendar') ?></small>
                            </div>

                            <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                                <label for="delivery_time"><?= LanguageHelper::t('when_need_flowers', 'When do you prefer the flowers delivery?') ?> *</label>
                                <select id="delivery_time" name="delivery_time" class="form-input" required>
                                    <option value="morning"><?= LanguageHelper::t('morning_delivery', 'Morning ') ?></option>
                                    <option value="afternoon"><?= LanguageHelper::t('afternoon_delivery', 'Afternoon ') ?></option>
                                    <option value="evening"><?= LanguageHelper::t('evening_delivery', 'Evening  ') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                            <label for="notes"><?= LanguageHelper::t('special_instructions', 'Special Instructions') ?></label>
                            <textarea id="notes" name="notes" class="form-input" rows="2" placeholder="<?= LanguageHelper::t('special_instructions_placeholder', 'Any special delivery instructions...') ?>"></textarea>
                        </div>
                        <div class="step-actions" style="display: flex; justify-content: space-between; margin-top: 20px !important;"  >
                            <button type="button" class="btn btn-prev checkout-btn-prev" data-prev="2" style="background-color: #f1f1f1 !important; color: #333 !important; border: 1px solid #ccc !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.3s ease !important;" onmouseover="this.style.backgroundColor='#e2e2e2'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#f1f1f1'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('back', 'Back') ?>
                            </button>
                            <button type="button" class="btn btn-next checkout-btn-next" data-next="4" style="background-color: #007bff !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.3s ease !important; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;" onmouseover="this.style.backgroundColor='#0056b3'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#007bff'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('next_payment', 'Next: Payment') ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Payment -->
                    <div class="form-step" data-step="4">
                        <h2 class="step-title" data-aos="fade-up" data-aos-delay="100"><?= LanguageHelper::t('payment_method', 'Payment Method') ?></h2>
                        
                        <!-- Verification Warning in Last Step (For Bulk Buyers or Quantity >= 5) -->
                        <?php if ($is_verification_required && !$is_phone_verified): ?>
                        <div id="verificationWarningStep4" class="verification-warning required" style="margin-bottom: 20px;" data-aos="fade-up" data-aos-delay="150">
                            <div class="warning-icon">🔴</div>
                            <div class="warning-content">
                                <h3><?= LanguageHelper::t('phone_verification_required', 'Phone Verification Required') ?></h3>
                                <p style="margin-bottom: 15px; margin-top: 10px;"><?php 
                                    if ($customer_type === 'bulk') {
                                        echo LanguageHelper::t('bulk_verification_required', 'Since you are a bulk buyer, phone verification is required to proceed with your order.');
                                    } else {
                                        echo LanguageHelper::t('quantity_verification_required', 'Since your order quantity is 5 or more items, phone verification is required to proceed. Please verify your phone number before placing your order.');
                                    }
                                ?></p>
                                <a href="#" id="verifyAccountBtnStep4" class="verify-link" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #007bff; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;"><?= LanguageHelper::t('verify_now', 'Verify now') ?></a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Verification Form for Last Step -->
                        <div id="verificationFormContainerStep4" class="verification-form-container" style="display: none; margin-bottom: 20px;">
                            <div class="verification-form">
                                <h3><?= LanguageHelper::t('verify_your_phone_number', 'Verify Your Phone Number') ?></h3>
                                <p><?= LanguageHelper::t('enter_verification_code', 'Please enter the verification code sent to your phone.') ?></p>
                                <div class="form-group">
                                    <label for="verification_code_step4"><?= LanguageHelper::t('verification_code', 'Verification Code') ?></label>
                                    <input type="text" id="verification_code_step4" name="verification_code_step4" class="form-input" placeholder="<?= LanguageHelper::t('enter_6_digit_code', 'Enter 6-digit code') ?>">
                                </div>
                                <div class="verification-actions">
                                    <button type="button" id="submitVerificationBtnStep4" class="btn btn-primary checkout-btn-primary"><?= LanguageHelper::t('verify_account', 'Verify Account') ?></button>
                                    <button type="button" id="cancelVerificationBtnStep4" class="btn btn-secondary checkout-btn-secondary"><?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                                    <button type="button" id="resendCodeBtnStep4" class="btn btn-link checkout-btn-link"><?= LanguageHelper::t('resend_code', 'Resend Code') ?></button>
                                </div>
                                <!-- Resend Cooldown Timer for Step 4 -->
                                <div id="resendCooldownTimerStep4" style="display: none; margin-top: 15px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404; text-align: center; font-size: 14px;">
                                    <strong>⏱️ <?= LanguageHelper::t('resend_in', 'Resend in') ?> <span id="cooldownSecondsStep4">60</span>s</strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Verification Success Message for Last Step -->
                        <div id="verificationSuccessStep4" style="display: none; margin-bottom: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; color: #155724;">
                            <strong>✓ <?= LanguageHelper::t('phone_verified_successfully', 'Phone number verified successfully!') ?></strong>
                            <p style="margin: 5px 0 0 0; font-size: 14px;">
                                <?php 
                                    if ($customer_type === 'bulk') {
                                        echo LanguageHelper::t('bulk_verification_required', 'Since you are a bulk buyer, you can now proceed with your order.');
                                    } else {
                                        echo LanguageHelper::t('quantity_verification_required', 'Your order of 5+ items is now ready to proceed.');
                                    }
                                ?>
                            </p>
                        </div>
                        
                        <div class="payment-options" data-aos="zoom-in" data-aos-delay="200">
                            <?php foreach(['cod' => '💰 ' . LanguageHelper::t('cash_on_delivery', 'Cash on Delivery'), 'esewa' => '📱 ' . LanguageHelper::t('esewa', 'eSewa'), 'khalti' => '💳 ' . LanguageHelper::t('khalti', 'Khalti'), 'bank_transfer' => '🏦 ' . LanguageHelper::t('bank_transfer', 'Bank Transfer')] as $value=>$label): ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="<?=$value?>" <?=$value=='cod'?'checked':''?>>
                                <span class="payment-label"><?=$label?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="qrCodeContainer" class="qr-code-container" style="display: none;" data-aos="zoom-in" data-aos-delay="300">
                            <h3><?= LanguageHelper::t('scan_to_pay', 'Scan to Pay') ?></h3>
                            <div class="qr-image-wrapper">
                                <img id="qrImage" src="" alt="<?= LanguageHelper::t('payment_qr_code', 'Payment QR Code') ?>" class="qr-image" onclick="openQrModal(this.src)">
                            </div>
                            <p class="qr-instruction">
                                <?= LanguageHelper::t('scan_qr_instruction', 'Please scan the QR code to complete your payment and upload the screenshot below.') ?>
                                <button type="button" class="btn-download-qr checkout-btn-download" onclick="downloadQrImage()"><?= LanguageHelper::t('download_qr_code', 'Download QR Code') ?></button>
                            </p>
                            <div class="form-group payment-screenshot">
                                <label for="payment_screenshot"><?= LanguageHelper::t('upload_payment_screenshot', 'Upload Payment Screenshot') ?> *</label>
                                <input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/jpeg,image/png,image/gif" class="form-input">
                                <small class="form-text"><?= LanguageHelper::t('accepted_formats', 'Accepted formats: JPG, PNG, GIF. Max size: 2MB') ?></small>
                                <div id="screenshotPreview" class="mt-2" style="display: none;">
                                    <p><?= LanguageHelper::t('screenshot_preview', 'Screenshot Preview') ?>:</p>
                                    <img id="preview" src="#" alt="<?= LanguageHelper::t('screenshot_preview', 'Screenshot Preview') ?>" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group terms-section" data-aos="fade-up" data-aos-delay="400">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" value="1" required>
                                <span><?= LanguageHelper::t('agree_terms', 'I agree to the') ?> <?= LanguageHelper::t('terms_and_conditions', 'Terms and Conditions') ?> <?= LanguageHelper::t('and', 'and') ?> <?= LanguageHelper::t('privacy_policy', 'Privacy Policy') ?></span>
                            </label>
                        </div>
                        
                        <!-- WhatsApp Order Option -->
                        <div class="whatsapp-order-section" data-aos="fade-up" data-aos-delay="450">
                            <h3 class="whatsapp-title">📱 <?= LanguageHelper::t('order_via_whatsapp', 'Order via WhatsApp') ?></h3>
                            <p class="whatsapp-description">
                                <?= LanguageHelper::t('whatsapp_order_description', 'Prefer to order via WhatsApp? Click below to send your order directly to our WhatsApp business account.') ?>
                            </p>
                            <button type="button" id="whatsappOrderBtn" class="btn btn-success whatsapp-order-btn" style="background-color: #25D366 !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; width: 100% !important; transition: all 0.3s ease !important; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;" onmouseover="this.style.backgroundColor='#128C7E'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#25D366'; this.style.transform='scale(1)';">
                                <i class="fab fa-whatsapp" style="margin-right: 8px;"></i>
                                <?= LanguageHelper::t('place_order_whatsapp', 'Place Order via WhatsApp') ?>
                            </button>
                        </div>
                        
                        <div class="step-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px !important; gap: 10px;"   >
                            <button type="button" class="btn btn-prev checkout-btn-prev" data-prev="3" style="background-color: #f1f1f1 !important; color: #333 !important; border: 1px solid #ccc !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; width: 40% !important; transition: all 0.3s ease !important;" onmouseover="this.style.backgroundColor='#e2e2e2'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#f1f1f1'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('back', 'Back') ?>
                            </button>
                            <button type="submit" class="btn btn-primary checkout-btn-primary checkout-place-order" style="background-color: #28a745 !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; padding: 12px 24px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; width: 40% !important; transition: all 0.3s ease !important; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;" onmouseover="this.style.backgroundColor='#1e7e34'; this.style.transform='scale(1.03)';" onmouseout="this.style.backgroundColor='#28a745'; this.style.transform='scale(1)';">
                                <?= LanguageHelper::t('place_order_website', 'Place Order on Website') ?>
                            </button>
                        </div>
                        <style>
                        /* ✅ Responsive only for this checkout step */
                        @media (max-width: 768px) {
                          .step-actions {
                            flex-direction: row !important;
                            justify-content: space-between !important;
                            gap: 10px !important;
                          }
                          .step-actions .checkout-btn-prev,
                          .step-actions .checkout-btn-primary {
                            width: 48% !important;
                            font-size: 15px !important;
                            padding: 10px 18px !important;
                          }
                          .whatsapp-order-btn {
                            font-size: 14px !important;
                            padding: 10px 16px !important;
                          }
                        }
                        
                        .whatsapp-order-section {
                            background: #f8fff8;
                            border: 1px solid #25D366;
                            border-radius: 10px;
                            padding: 20px;
                            margin: 20px 0;
                            text-align: center;
                        }
                        
                        .whatsapp-title {
                            color: #25D366;
                            margin-bottom: 10px;
                            font-size: 1.2em;
                        }
                        
                        .whatsapp-description {
                            color: #666;
                            margin-bottom: 15px;
                            font-size: 0.95em;
                        }
                        </style>
                    </div>
                    
                    <!-- ✅ NEW: Step 4.5 - Security Verification (For existing registered phones only) -->
                    <div class="form-step" data-step="4.5" style="display: none;">
                        <div style="max-width: 500px; margin: 40px auto; padding: 30px; text-align: center;">
                            <h3 style="font-size: 22px; font-weight: 700; margin-bottom: 20px; color: #333;">
                                🔒 <?= LanguageHelper::t('security_verification', 'Security Verification') ?>
                            </h3>
                            
                            <p style="font-size: 16px; color: #555; margin-bottom: 20px; line-height: 1.6;">
                                <?= LanguageHelper::t('verify_account_ownership', 'Please verify this phone number to confirm you own this account and complete your order.') ?>
                            </p>
                            
                            <!-- OTP Send Status Message -->
                            <div id="security_otp_send_message" style="margin-bottom: 20px; padding: 12px 15px; border-radius: 6px; text-align: center; display: none;"></div>
                            
                            <div id="securityVerificationForm" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                                <!-- OTP Code Input -->
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; text-align: left; font-weight: 600; margin-bottom: 8px;">
                                        <?= LanguageHelper::t('enter_otp_code', 'Enter OTP Code') ?>
                                    </label>
                                    <input type="text" id="security_verification_code" maxlength="6" 
                                        style="width: 100%; padding: 12px; font-size: 18px; text-align: center; 
                                               border: 2px solid #ddd; border-radius: 6px; letter-spacing: 4px;"
                                        placeholder="000000"
                                        inputmode="numeric">
                                </div>
                                
                                <!-- Resend Link -->
                                <div style="margin-bottom: 20px; font-size: 14px;">
                                    <span style="color: #666;">
                                        <?= LanguageHelper::t('didnt_receive_code', "Didn't receive the code?") ?>
                                        <a href="#" id="resendSecurityCodeBtn" 
                                           style="color: #007bff; text-decoration: none; font-weight: 600; cursor: pointer;">
                                            <?= LanguageHelper::t('resend', 'Resend') ?>
                                        </a>
                                    </span>
                                </div>
                                
                                <!-- Verification Error/Status Message -->
                                <div id="security_verification_message" style="margin-bottom: 20px; display: none;"></div>
                                
                                <!-- Info: Auto-verification on 6 digits -->
                                <div style="font-size: 13px; color: #999; text-align: center; margin-top: 15px; font-style: italic;">
                                    ℹ️ <?= LanguageHelper::t('auto_verify_info', 'Your order will be automatically verified and completed after entering 6 digits.') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmation Step (Hidden until order success) -->
                 <div class="form-step confirmation-step" data-step="5" style="
    display:none;
    width:100%;
    max-width:600px;
    margin:40px auto;
    padding:30px;
    border:1px solid #e0e0e0;
    border-radius:12px;
    background:#ffffff;
    text-align:center;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
">

    <div class="confirmation-icon" style="
        width:80px;
        height:80px;
        margin:0 auto 20px auto;
        border-radius:50%;
        background:#28a745;
        color:#fff;
        font-size:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:bold;
    ">✓</div>

    <h2 style="
        font-size:28px;
        font-weight:700;
        margin:10px 0 10px 0;
        color:#333;
    "><?= LanguageHelper::t('order_confirmed', 'Order Confirmed!') ?></h2>

    <p style="
        font-size:16px;
        color:#555;
        margin:10px 0;
    ">
        <?= LanguageHelper::t('thank_you_order', 'Thank you for your order. Your order number is') ?>
        <strong id="order-confirmation-number" style="color:#111;"></strong>.
    </p>

    <?php if ($total_discount > 0): ?>
    <p class="savings-message" style="
        font-size:16px;
        color:#1d7e34;
        background:#e8f6ec;
        padding:10px 15px;
        border-radius:8px;
        margin:15px 0;
        font-weight:600;
    ">
        🎉 <?= LanguageHelper::t('you_saved', 'You saved') ?>
        <strong><?= LanguageHelper::t('rs', 'Rs.') ?> <?=number_format($total_discount,2)?></strong>
        <?= LanguageHelper::t('with_discounts', 'with bulk pricing and special offers!') ?>
    </p>
    <?php endif; ?>

    <p style="
        font-size:16px;
        color:#555;
        margin:10px 0 25px 0;
    ">
        <?= LanguageHelper::t('confirmation_email_shortly', 'We\'ll send you a confirmation email shortly.') ?>
    </p>

    <div class="confirmation-actions" style="
        display:flex;
        justify-content:center;
        gap:15px;
        margin-top:20px;
        flex-wrap:wrap;
    ">
        <a href="<?=$base_url?>" style="
            padding:10px 20px;
            background:#007bff;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            font-size:16px;
            font-weight:600;
        "><?= LanguageHelper::t('continue_shopping', 'Continue Shopping') ?></a>

        <a href="<?=$base_url?>/account/orders" style="
            padding:10px 20px;
            background:#6c757d;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            font-size:16px;
            font-weight:600;
        "><?= LanguageHelper::t('view_orders', 'View Orders') ?></a>
    </div>

</div>

                </form>
            </div>
        </div>
    </div>
</section>

<!-- QR Code Modal -->
<div id="qrModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeQrModal()">&times;</span>
        <h3><?= LanguageHelper::t('payment_qr_code', 'Payment QR Code') ?></h3>
        <img id="modalQrImage" src="" alt="<?= LanguageHelper::t('payment_qr_code', 'Payment QR Code') ?>" style="max-width: 100%;">
        <div class="modal-actions">
            <button type="button" class="btn btn-primary checkout-btn-primary" onclick="downloadQrImage()"><?= LanguageHelper::t('download_qr_code', 'Download QR Code') ?></button>
            <button type="button" class="btn btn-secondary checkout-btn-secondary" onclick="closeQrModal()"><?= LanguageHelper::t('close', 'Close') ?></button>
        </div>
    </div>
</div> 

<script>
// Enhanced data structure with bulk pricing information
const productData = {
    <?php foreach ($cart as $item): ?>
    <?=$item['id']?>: {
        finalPrice: <?=$item['final_price']?>,
        originalPrice: <?=$item['original_price']?>,
        bulkThreshold: <?=$item['bulk_threshold']?>,
        unit: "<?=$item['unit']?>",
        isBulk: <?=$item['is_bulk'] ? 'true' : 'false'?>,
        bulkSavings: <?=$item['total_saving']?>,
        currentQuantity: <?=$item['quantity']?>
    },
    <?php endforeach; ?>
};

const minimumQuantities = <?= json_encode($minimum_quantities) ?>;
const productOffers = <?= json_encode($product_offers) ?>;
const specialEvents = <?= json_encode($special_events) ?>;
const cityIds = <?= json_encode($city_ids) ?>;
const discountBreakdown = <?= json_encode($discount_breakdown) ?>;
let currentQrImageUrl = '';
let currentDeliveryFee = <?=$delivery_fee?>;
let cityDeliveryFees = <?= json_encode($city_delivery_fees) ?>;
let isPhoneVerified = <?= $is_phone_verified ? 'true' : 'false' ?>;
let isVerificationRequired = <?= $is_verification_required ? 'true' : 'false' ?>;
let totalQuantity = <?= $total_quantity ?>;
let customerType = '<?= $customer_type ?>';
let verificationFormStarted = false; // Track if user clicked "Verify" button
let verificationFormStartedStep4 = false; // Track if user clicked "Verify" in Step 4
let verificationJustCompleted = false; // Track if verification was just completed on THIS page session
let verificationStep4JustCompleted = false; // Track if Step 4 verification was just completed

// ✅ NEW: Separate verification tracking for TWO types of verification
let securityVerificationCompleted = false; // Security Verification - existing phone proof of ownership
let quantityVerificationCompleted = false; // Quantity Verification - bulk order (qty >= 5) requirement
let pendingOrderFormData = null; // Store form data to submit after OTP verification
let autoSubmitOrderAfterOtp = false; // Flag to automatically submit order after OTP

// WhatsApp Business Number - CORRECTED FORMAT
const whatsappBusinessNumber = '9800000000'; // Remove any plus signs or country codes

// Auto-fill street field with address data
function autoFillStreet() {
    const addressField = document.getElementById('address');
    const streetField = document.getElementById('street');
    
    if (addressField && streetField) {
        // Copy address value to street field
        streetField.value = addressField.value;
    }
}

// Toggle Delivery Details Dropdown
function toggleDeliveryDetailsDropdown() {
    const content = document.getElementById('deliveryDetailsContent');
    const icon = document.getElementById('dropdownIcon');
    
    if (content.style.maxHeight === '0px' || content.style.maxHeight === '') {
        // Open dropdown - calculate actual height of content
        content.style.maxHeight = content.scrollHeight + 'px';
        icon.style.transform = 'rotate(180deg)';
        icon.style.transition = 'transform 0.3s ease';
    } else {
        // Close dropdown
        content.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
        icon.style.transition = 'transform 0.3s ease';
    }
}

// Nepali Date Component - Auto-populate with current date from database
(function() {
    console.log("DEBUG: Starting Nepali date initialization");
    
    // Get translated months from backend
    const months = <?= json_encode(LanguageHelper::t('months_list', ["Baishakh","Jestha","Ashadh","Shrawan","Bhadra","Ashwin","Kartik","Mangsir","Poush","Magh","Falgun","Chaitra"])) ?>;
    
    // Get elements
    const yearSelect = document.getElementById('nepali_year');
    const monthSelect = document.getElementById('nepali_month');
    const daySelect = document.getElementById('nepali_day');
    
    if (!yearSelect || !monthSelect || !daySelect) {
        console.error("ERROR: Nepali date select elements not found in DOM");
        return;
    }
    
    // Populate months
    months.forEach((m, i) => {
        let opt = document.createElement('option');
        opt.value = (i + 1).toString().padStart(2, '0');
        opt.text = m;
        monthSelect.appendChild(opt);
    });
    console.log("DEBUG: Months populated");

    // Populate years from database
    const availableYears = <?= json_encode($available_nepali_years); ?>;
    console.log("DEBUG: Available years from database:", availableYears);
    
    availableYears.forEach(year => {
        let opt = document.createElement('option');
        opt.value = year.toString();  // Ensure it's a string to match later
        opt.text = year.toString();
        yearSelect.appendChild(opt);
    });
    console.log("DEBUG: Years populated");

    // Populate days (1-32 for Nepali calendar)
    for (let d = 1; d <= 32; d++) {
        let opt = document.createElement('option');
        opt.value = d.toString().padStart(2, '0');
        opt.text = d;
        daySelect.appendChild(opt);
    }
    console.log("DEBUG: Days populated");

    // Set default values from database current Nepali date - FIXED
    <?php if ($current_nepali_date && !empty($current_nepali_date['year'])): ?>
    const currentNepaliDate = {
        year: '<?= (int)$current_nepali_date['year'] ?>',
        month: '<?= str_pad((int)$current_nepali_date['month'], 2, '0', STR_PAD_LEFT) ?>',
        day: '<?= str_pad((int)$current_nepali_date['day'], 2, '0', STR_PAD_LEFT) ?>'
    };
    
    console.log("DEBUG: Current Nepali date from database:", currentNepaliDate);
    
    // Auto-select current Nepali date from database
    yearSelect.value = currentNepaliDate.year;
    monthSelect.value = currentNepaliDate.month;
    daySelect.value = currentNepaliDate.day;
    
    console.log("DEBUG: Set year to:", yearSelect.value);
    console.log("DEBUG: Set month to:", monthSelect.value);
    console.log("DEBUG: Set day to:", daySelect.value);
    
    // Verify values were set
    if (yearSelect.value === currentNepaliDate.year &&
        monthSelect.value === currentNepaliDate.month &&
        daySelect.value === currentNepaliDate.day) {
        console.log("SUCCESS: Nepali date auto-filled from database");
    } else {
        console.warn("WARNING: Some values may not have been set correctly");
        // Try again with timeout if DOM not ready
        setTimeout(() => {
            yearSelect.value = currentNepaliDate.year;
            monthSelect.value = currentNepaliDate.month;
            daySelect.value = currentNepaliDate.day;
            console.log("DEBUG: Retry - Set values after timeout");
        }, 100);
    }
    <?php else: ?>
    console.warn("DEBUG: No current Nepali date data available from database, using fallback");
    // Fallback: Try to set to first available year
    if (availableYears.length > 0) {
        yearSelect.value = availableYears[0].toString();
    }
    <?php endif; ?>

    // Update hidden delivery_date field when Nepali date changes
    function updateDeliveryDateField() {
        const year = yearSelect.value;
        const month = monthSelect.value;
        const day = daySelect.value;
        
        if (year && month && day) {
            // Format: YYYY-MM-DD (backend compatible)
            const formattedDate = `${year}-${month}-${day}`;
            document.getElementById('delivery_date').value = formattedDate;
            console.log("DEBUG: Updated delivery_date field to:", formattedDate);
        }
    }

    // Add event listeners to update the hidden field
    yearSelect.addEventListener('change', updateDeliveryDateField);
    monthSelect.addEventListener('change', updateDeliveryDateField);
    daySelect.addEventListener('change', updateDeliveryDateField);

    // Initialize the delivery date field
    updateDeliveryDateField();
})();

// Mobile detection
function isMobileDevice() {
    return window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Save form data to localStorage to prevent data loss
function saveFormData() {
    const formData = {
        name: document.getElementById('name')?.value || '',
        phone: document.getElementById('phone')?.value || '',
        email: document.getElementById('email')?.value || '',
        address: document.getElementById('address')?.value || '',
        city: document.getElementById('city')?.value || '',
        street: document.getElementById('street')?.value || '',
        delivery_date: document.getElementById('delivery_date')?.value || '',
        delivery_time: document.getElementById('delivery_time')?.value || '',
        notes: document.getElementById('notes')?.value || '',
        payment_method: document.querySelector('input[name="payment_method"]:checked')?.value || 'cod'
    };
    localStorage.setItem('checkoutFormData', JSON.stringify(formData));
}

// Load form data from localStorage
function loadFormData() {
    const savedData = localStorage.getItem('checkoutFormData');
    if (savedData) {
        const formData = JSON.parse(savedData);
        
        // Only populate fields if they're empty and not readonly (for logged-in users)
        if (document.getElementById('name') && !document.getElementById('name').readOnly) {
            document.getElementById('name').value = formData.name || '';
        }
        if (document.getElementById('phone') && !document.getElementById('phone').readOnly) {
            document.getElementById('phone').value = formData.phone || '';
        }
        if (document.getElementById('email') && !document.getElementById('email').readOnly) {
            document.getElementById('email').value = formData.email || '';
        }
        if (document.getElementById('address')) {
            document.getElementById('address').value = formData.address || '';
        }
        if (document.getElementById('city')) {
            document.getElementById('city').value = formData.city || '';
        }
        if (document.getElementById('street')) {
            document.getElementById('street').value = formData.street || '';
        }
        if (document.getElementById('delivery_date')) {
            document.getElementById('delivery_date').value = formData.delivery_date || '';
        }
        if (document.getElementById('delivery_time')) {
            document.getElementById('delivery_time').value = formData.delivery_time || '';
        }
        if (document.getElementById('notes')) {
            document.getElementById('notes').value = formData.notes || '';
        }
        
        // Set payment method
        if (formData.payment_method) {
            const paymentRadio = document.querySelector(`input[name="payment_method"][value="${formData.payment_method}"]`);
            if (paymentRadio) {
                paymentRadio.checked = true;
                handlePaymentMethodChange();
            }
        }
        
        // Update delivery fee if city is selected
        if (formData.city) {
            setTimeout(updateDeliveryFee, 100);
        }
    }
}

// Clear saved form data after successful order
function clearFormData() {
    localStorage.removeItem('checkoutFormData');
}

function showMessage(message, type = 'success', target = 'addressUpdateMessage') {
    const messageDiv = document.getElementById(target);
    if (!messageDiv) {
        console.error(`Message container not found: ${target}`);
        alert(message); // Fallback to alert if div not found
        return;
    }
    
    messageDiv.textContent = message;
    messageDiv.className = `alert-message ${type}`;
    messageDiv.style.display = 'block';
    
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    // Scroll to message
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Keep error messages visible longer, auto-hide success messages
    const hideDelay = type === 'error' ? 10000 : 5000;
    
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, hideDelay);
}

// Update city display in Step 1 from the actual form city field value
function updateCityDisplayFromForm() {
    try {
        const citySelect = document.getElementById('city');
        const currentCityDisplay = document.getElementById('currentCityDisplay');
        
        if (!citySelect || !currentCityDisplay) {
            console.warn('City select or display element not found');
            return;
        }
        
        const selectedCity = citySelect.value;
        
        if (selectedCity) {
            // Form field has a selected city - show that
            currentCityDisplay.textContent = selectedCity;
            console.log('City display: showing form selected city -', selectedCity);
        } else {
            // No selection in form - show the first selected option's text (which is the default city)
            // This handles the case where the form loads with a default selected option
            const selectedOption = citySelect.options[citySelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                currentCityDisplay.textContent = selectedOption.text;
                console.log('City display: showing default selected option -', selectedOption.text);
            } else {
                currentCityDisplay.textContent = '<?= LanguageHelper::t('not_selected', 'Not Selected') ?>';
                console.log('City display: no selection available');
            }
        }
    } catch (error) {
        console.error('ERROR in updateCityDisplayFromForm:', error);
    }
}

// ✅ NEW: Helper function to get phone number from form
function getPhoneFromForm() {
    // Try to get phone from the phone input field
    let phoneField = document.getElementById('phone');
    if (phoneField && phoneField.value) {
        return phoneField.value.trim();
    }
    
    // Try contact field as fallback
    let contactField = document.getElementById('contact');
    if (contactField && contactField.value) {
        return contactField.value.trim();
    }
    
    // Try guest_phone_number field (for guest users)
    let guestPhoneField = document.getElementById('guest_phone_number');
    if (guestPhoneField && guestPhoneField.value) {
        return guestPhoneField.value.trim();
    }
    
    return '';
}

// FIXED: Handle city change - call both updateDeliveryFee and checkMinimumQuantities
function handleCityChange() {
    // Update the current city display in Step 1 from form field
    updateCityDisplayFromForm();
    
    updateDeliveryFee();
    // Check minimum quantities when city changes
    const isMinimumMet = checkMinimumQuantities();
    
    // If minimum quantity is not met, auto-redirect to Step 1
    if (!isMinimumMet) {
        console.warn('WARNING: Minimum quantity not met for selected city, redirecting to Step 1');
        showMessage(
            '<?= LanguageHelper::t('adjust_quantities_step_1', 'Please adjust quantities to meet minimum requirements for the selected city. Returning to Step 1...') ?>',
            'error',
            'minimumQuantityMessage'
        );
        
        // Auto-navigate back to Step 1 after 2 seconds to let user read the message
        setTimeout(() => {
            navigateToStep(1);
        }, 2000);
    }
}

// Handle city change in Step 1
function handleCityChangeStep1() {
    const citySelectStep1 = document.getElementById('city-step1');
    const selectedCityValue = citySelectStep1.value;
    
    if (!selectedCityValue) {
        return;
    }
    
    // Parse city name and city ID if present
    const [cityName, cityId] = selectedCityValue.split('|');
    
    // Also sync to Step 2 city selector if it exists
    const citySelectStep2 = document.getElementById('city');
    if (citySelectStep2) {
        citySelectStep2.value = cityName;
        // Trigger city change in Step 2 to update delivery fee
        handleCityChange();
    }
    
    saveFormData();
}

function updateDeliveryFee() {
    const citySelect = document.getElementById('city');
    const selectedCity = citySelect.value;
    const feeInfo = document.getElementById('delivery-fee-info');
    
    if (!selectedCity) {
        feeInfo.textContent = '<?= LanguageHelper::t('select_city_see_fee', 'Select a city to see delivery fee') ?>';
        currentDeliveryFee = 0;
        recalculateTotals();
        checkMinimumQuantities();
        updateDeliveryMessage();
        return;
    }
    
    const selectedOption = citySelect.options[citySelect.selectedIndex];
    const deliveryFee = parseFloat(selectedOption.getAttribute('data-fee')) || cityDeliveryFees[selectedCity] || 0.00;
    currentDeliveryFee = deliveryFee;
    
    if (currentDeliveryFee === 0) {
        feeInfo.innerHTML = `🎉 <strong><?= LanguageHelper::t('hurray_free_delivery', 'Hurray! Free delivery for') ?> ${selectedCity}!</strong>`;
    } else {
        feeInfo.textContent = `<?= LanguageHelper::t('delivery_fee_for', 'Delivery fee for') ?> ${selectedCity}: <?= LanguageHelper::t('rs', 'Rs.') ?> ${currentDeliveryFee.toFixed(2)}`;
    }
    
    updateDeliveryFeeDisplays();
    recalculateTotals();
    checkMinimumQuantities(); // This should now be called
    updateDeliveryMessage();
    saveFormData();
}

function updateDeliveryFeeDisplays() {
    document.querySelectorAll('#delivery-fee').forEach(el => {
        el.innerHTML = currentDeliveryFee === 0 ? `<span class="free-delivery">🎉 <?= LanguageHelper::t('free', 'FREE') ?></span>` : `<?= LanguageHelper::t('rs', 'Rs.') ?> ${currentDeliveryFee.toFixed(2)}`;
    });
}

function updateDeliveryMessage() {
    const deliveryMessage = document.getElementById('deliveryMessage');
    const citySelect = document.getElementById('city');
    const selectedCity = citySelect.value;
    
    let messageHTML = '';
    if (selectedCity && currentDeliveryFee === 0) {
        messageHTML = `<div class="free-delivery-banner"><span class="delivery-icon">🚚</span><div class="delivery-text"><strong>🎉 <?= LanguageHelper::t('hurray', 'Hurray!') ?></strong><span><?= LanguageHelper::t('free_delivery_selected_city', 'Free delivery for your selected city!') ?></span></div></div>`;
    } else if (selectedCity) {
        messageHTML = `<div class="standard-delivery-message"><span class="delivery-icon">📦</span><span><?= LanguageHelper::t('standard_delivery_fee_applied', 'Standard delivery fee applied') ?></span></div>`;
    } else {
        messageHTML = `<div class="select-city-message"><span class="delivery-icon">📍</span><span><?= LanguageHelper::t('select_city_for_delivery', 'Select a city to see delivery options') ?></span></div>`;
    }
    
    if (deliveryMessage) deliveryMessage.innerHTML = messageHTML;
}

function toggleOffers() {
    const offersSection = document.getElementById('offersSection');
    const offersList = document.getElementById('offersList');
    const btn = offersSection.querySelector('.btn-show-offers');
    
    if (offersSection.style.display === 'none' || offersSection.style.display === '') {
        offersSection.style.display = 'block';
        btn.textContent = '<?= LanguageHelper::t('show_offers', 'Show Offers') ?>';
        updateOffersDisplay();
    } else {
        offersSection.style.display = 'none';
        btn.textContent = '<?= LanguageHelper::t('hide_offers', 'Hide Offers') ?>';
    }
}

function toggleDeliveryInfo() {
    const deliverySection = document.getElementById('deliveryInfoSection');
    const btn = deliverySection.querySelector('.btn-show-delivery');
    
    if (deliverySection.style.display === 'none' || deliverySection.style.display === '') {
        deliverySection.style.display = 'block';
        btn.textContent = '<?= LanguageHelper::t('show_delivery_info', 'Show Delivery Info') ?>';
        updateDeliveryMessage();
    } else {
        deliverySection.style.display = 'none';
        btn.textContent = '<?= LanguageHelper::t('hide_delivery_info', 'Hide Delivery Info') ?>';
    }
}

function updateOffersDisplay() {
    const offersList = document.getElementById('offersList');
    let offersHTML = '';
    let hasActiveOffers = false;
    
    // Show product offers
    Object.keys(productOffers).forEach(productId => {
        const offers = productOffers[productId];
        const productName = document.querySelector(`[data-product-id="${productId}"] .item-name`)?.textContent || 'Product';
        offers.forEach(offer => {
            hasActiveOffers = true;
            let offerText = '';
            if (offer.offer_type === 'percentage_discount') {
                offerText = `🎊 <strong>${offer.discount_percentage}% OFF</strong> on ${productName}`;
            } else if (offer.offer_type === 'fixed_discount') {
                offerText = `🎊 <strong>Save Rs. ${parseFloat(offer.fixed_discount).toFixed(2)}</strong> on ${productName}`;
            } else if (offer.offer_type === 'buy_x_get_y') {
                offerText = `🎊 <strong>Buy ${offer.buy_quantity} Get ${offer.get_quantity} Free</strong> on ${productName}`;
            }
            offerText += offer.buy_quantity > 0 ? ` (min. ${offer.buy_quantity} ${document.querySelector(`[data-product-id="${productId}"] .final-price`)?.textContent.split('per')[1]?.trim() || 'unit'})` : ` <span class="any-quantity">- <?= LanguageHelper::t('for_any_quantity', 'for any quantity!') ?></span>`;
            offersHTML += `<div class="offer-item">${offerText}</div>`;
        });
    });
    
    // Show special events
    specialEvents.forEach(event => {
        hasActiveOffers = true;
        offersHTML += `<div class="offer-item event-offer">🎉 <strong>${event.event_name}</strong>: ${event.event_description} - <strong>${event.discount_percentage}% OFF</strong> on all products!</div>`;
    });
    
    if (!hasActiveOffers) {
        offersHTML = '<div class="no-offers"><?= LanguageHelper::t('no_special_offers', 'No special offers available at the moment.') ?></div>';
    }
    
    if (offersList) offersList.innerHTML = offersHTML;
}

// FIXED: Enhanced checkMinimumQuantities function with proper validation
function checkMinimumQuantities() {
    const citySelect = document.getElementById('city');
    const selectedCity = citySelect.value;
    const cityId = selectedCity ? cityIds[selectedCity] : null;
    
    console.log(`=== CHECKING MINIMUM QUANTITIES ===`);
    console.log(`Selected City: ${selectedCity}, City ID: ${cityId}`);
    console.log(`Available cityIds:`, cityIds);
    console.log(`Minimum Quantities Data:`, minimumQuantities);
    
    if (!selectedCity || !cityId) {
        // Hide all minimum quantity warnings if no city is selected
        document.querySelectorAll('.minimum-quantity-info').forEach(el => {
            el.style.display = 'none';
            el.querySelector('small').textContent = '';
        });
        
        const messageDiv = document.getElementById('minimumQuantityMessage');
        if (messageDiv) {
            messageDiv.style.display = 'none';
        }
        
        console.log('No city selected - skipping minimum quantity check');
        return true; // No city selected, so no minimum quantity check needed
    }
    
    let hasMinimumQuantityIssue = false;
    let minimumQuantityMessage = '';
    const violatingProducts = [];
    
    // Reset all warnings first
    document.querySelectorAll('.minimum-quantity-info').forEach(el => {
        el.style.display = 'none';
        el.querySelector('small').textContent = '';
    });
    
    // Check each product in cart
    Object.keys(productData).forEach(productId => {
        const quantity = getCurrentQuantity(parseInt(productId));
        const minQuantityInfo = minimumQuantities[productId]?.[cityId];
        const productName = productData[productId]?.name || `Product ${productId}`;
        
        console.log(`  Product ${productId} (${productName}): quantity=${quantity}, minInfo=`, minQuantityInfo);
        
        if (minQuantityInfo) {
            // Product has minimum quantity requirement for this city
            if (quantity < minQuantityInfo.minimum_quantity) {
                // Show warning for this specific product
                const warningElement = document.getElementById(`min-quantity-info-${productId}`);
                if (warningElement) {
                    warningElement.style.display = 'block';
                    warningElement.querySelector('small').textContent = 
                        `⚠️ <?= LanguageHelper::t('minimum_required', 'Minimum') ?> ${minQuantityInfo.minimum_quantity} ${minQuantityInfo.unit} <?= LanguageHelper::t('required_for_city', 'required for') ?> ${selectedCity}`;
                }
                
                hasMinimumQuantityIssue = true;
                violatingProducts.push({
                    productId: productId,
                    productName: minQuantityInfo.product_name || productName,
                    required: minQuantityInfo.minimum_quantity,
                    unit: minQuantityInfo.unit,
                    current: quantity
                });
                
                console.warn(`    ✗ MINIMUM NOT MET: Required ${minQuantityInfo.minimum_quantity} ${minQuantityInfo.unit}, Have ${quantity}`);
            } else {
                console.log(`    ✓ Minimum met: ${quantity} >= ${minQuantityInfo.minimum_quantity}`);
            }
        } else {
            console.log(`    ℹ No minimum quantity requirement for this city`);
        }
    });
    
    // Update the main warning message
    const messageDiv = document.getElementById('minimumQuantityMessage');
    if (messageDiv) {
        if (hasMinimumQuantityIssue) {
            // Build detailed message
            minimumQuantityMessage = `<?= LanguageHelper::t('minimum_quantity_not_met', 'Minimum quantity not met') ?>:\n`;
            violatingProducts.forEach((product, index) => {
                minimumQuantityMessage += `${product.productName}: ${product.required} ${product.unit} (<?= LanguageHelper::t('current', 'Current') ?>: ${product.current})`;
                if (index < violatingProducts.length - 1) minimumQuantityMessage += '\n';
            });
            
            messageDiv.textContent = minimumQuantityMessage;
            messageDiv.className = 'alert-message error';
            messageDiv.style.display = 'block';
            
            // Scroll to warning for better visibility
            setTimeout(() => {
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
            
            console.error(`✗ Minimum quantity issue detected:`, violatingProducts);
            return false;
        } else {
            messageDiv.style.display = 'none';
            console.log('✓ All minimum quantities satisfied');
            return true;
        }
    }
    
    return !hasMinimumQuantityIssue;
}

function handleQuantityKeydown(event, productId) {
    if (event.key === 'Enter') {
        event.preventDefault();
        updateQuantityFromInput(productId);
    }
}

function adjustQuantity(productId, change) {
    const quantityInput = document.getElementById(`quantity-input-${productId}`);
    let quantity = parseInt(quantityInput.value);
    const newQuantity = quantity + change;
    
    if (newQuantity < 1) {
        showMessage('<?= LanguageHelper::t('quantity_cannot_less', 'Quantity cannot be less than 1') ?>', 'error', 'minimumQuantityMessage');
        return;
    }
    
    quantityInput.value = newQuantity;
    
    updateItemDisplay(productId, newQuantity);
    updateCartQuantity(productId, newQuantity);
    saveFormData();
    
    // Check minimum quantities after quantity change
    checkMinimumQuantities();
}

function updateQuantityFromInput(productId) {
    const quantityInput = document.getElementById(`quantity-input-${productId}`);
    let newQuantity = parseInt(quantityInput.value);
    
    if (isNaN(newQuantity) || newQuantity < 1) {
        const previousQuantity = getCurrentQuantity(productId);
        quantityInput.value = previousQuantity;
        showMessage('<?= LanguageHelper::t('enter_valid_quantity', 'Please enter a valid quantity (minimum 1)') ?>', 'error', 'minimumQuantityMessage');
        return;
    }
    
    // Check if quantity is 10 or more - refresh page for bulk pricing
    if (newQuantity >= 10) {
        updateItemDisplay(productId, newQuantity);
        updateCartQuantity(productId, newQuantity, true); // Force refresh
    } else {
        updateItemDisplay(productId, newQuantity);
        updateCartQuantity(productId, newQuantity);
    }
    
    saveFormData();
    
    // Check minimum quantities after quantity change
    checkMinimumQuantities();
}

function getCurrentQuantity(productId) {
    return parseInt(document.getElementById(`quantity-input-${productId}`).value) || 1;
}

function updateItemDisplay(productId, quantity) {
    const product = productData[productId];
    if (!product) return;
    
    const { originalPrice, bulkThreshold, unit } = product;
    
    // Calculate dynamic pricing based on quantity
    let finalPrice = originalPrice;
    let isBulk = false;
    
    // Check if quantity reaches bulk threshold
    if (quantity >= bulkThreshold) {
        finalPrice = product.finalPrice; // Use the bulk price
        isBulk = true;
    }
    
    // Calculate Buy X Get Y free logic
    let effectiveQuantity = quantity;
    let freeQuantity = 0;
    const offers = productOffers[productId] || [];
    const buyXGetYOffer = offers.find(offer => offer.offer_type === 'buy_x_get_y');
    
    if (buyXGetYOffer && quantity >= buyXGetYOffer['buy_quantity']) {
        const sets = Math.floor(quantity / buyXGetYOffer['buy_quantity']);
        freeQuantity = sets * buyXGetYOffer['get_quantity'];
        effectiveQuantity = quantity; // Only pay for the purchased quantity, free items are bonus
    }
    
    const newTotal = finalPrice * quantity; // Only charge for purchased quantity
    
    // Update total price displays
    document.querySelectorAll(`#total-${productId}`).forEach(el => {
        el.textContent = `<?= LanguageHelper::t('rs', 'Rs.') ?> ${newTotal.toFixed(2)}`;
    });
    
    // Update quantity display with free items info
    document.querySelectorAll(`#current-quantity-${productId}`).forEach(el => {
        if (freeQuantity > 0) {
            el.innerHTML = `${quantity} ${unit} <span class="free-quantity-info">(<?= LanguageHelper::t('including', 'including') ?> ${freeQuantity} <?= LanguageHelper::t('free', 'free') ?>)</span>`;
        } else {
            el.textContent = `${quantity} ${unit}`;
        }
    });
    
    // Update price info with dynamic bulk pricing
    const itemElements = document.querySelectorAll(`.order-item[data-product-id="${productId}"]`);
    itemElements.forEach(itemElement => {
        const priceInfo = itemElement.querySelector('.price-info');
        if (isBulk) {
            priceInfo.innerHTML = `
                <div class="original-price"><?= LanguageHelper::t('rs', 'Rs.') ?> ${originalPrice.toFixed(2)}</div>
                <div class="final-price"><?= LanguageHelper::t('rs', 'Rs.') ?> ${finalPrice.toFixed(2)} <?= LanguageHelper::t('per', 'per') ?> ${unit}</div>
                <div class="bulk-badge">📦 <?= LanguageHelper::t('bulk_price', 'Bulk Price') ?></div>
            `;
        } else {
            priceInfo.innerHTML = `<div class="final-price"><?= LanguageHelper::t('rs', 'Rs.') ?> ${originalPrice.toFixed(2)} <?= LanguageHelper::t('per', 'per') ?> ${unit}</div>`;
        }
    });
    
    // Update product data for recalculations
    productData[productId].finalPrice = finalPrice;
    productData[productId].isBulk = isBulk;
    productData[productId].currentQuantity = quantity;
    
    recalculateTotals();
    checkMinimumQuantities(); // Check after updating quantity
}

function updateCartQuantity(productId, quantity, forceRefresh = false) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('action', 'update');
    
    fetch('<?=$base_url?>/cart/update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Error updating cart:', data.message);
        }
        recalculateTotals();
        
        // Force page refresh for bulk pricing when quantity >= 10
        if (forceRefresh || quantity >= 10) {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    })
    .catch(error => {
        console.error('<?= LanguageHelper::t('error_updating_cart', 'Error updating cart') ?>:', error);
        recalculateTotals();
    });
}

function recalculateTotals() {
    let subtotal = 0;
    let originalSubtotal = 0;
    let totalDiscount = 0;
    let bulkSavings = 0;
    let productOffersDiscount = 0;
    let eventDiscountAmount = 0;
    
    // Calculate subtotals and bulk savings with dynamic pricing
    Object.keys(productData).forEach(productId => {
        const product = productData[productId];
        const quantity = product.currentQuantity;
        const finalPrice = product.finalPrice;
        const originalPrice = product.originalPrice;
        const bulkDiscount = product.bulkDiscount || 0;
        const offerDiscount = product.offerDiscount || 0;
        
        // Calculate actual item total with ALL discounts applied
        const itemTotal = (finalPrice * quantity) - offerDiscount;
        subtotal += itemTotal;  // Use item total, not just final price
        originalSubtotal += originalPrice * quantity;
        
        // Calculate bulk savings
        if (product.isBulk) {
            bulkSavings += (originalPrice - finalPrice) * quantity;
        }
        
        // Accumulate product offer discounts
        productOffersDiscount += offerDiscount;
    });
    
    // Calculate event discount on the subtotal after all other discounts
    if (specialEvents.length > 0 && subtotal > 0) {
        let eventDiscountPercentage = 0;
        specialEvents.forEach(event => {
            eventDiscountPercentage += parseFloat(event['discount_percentage']);
        });
        if (eventDiscountPercentage > 0) {
            eventDiscountAmount = (subtotal * eventDiscountPercentage) / 100;
        }
    }
    
    totalDiscount = productOffersDiscount + eventDiscountAmount;
    const total = subtotal - eventDiscountAmount + currentDeliveryFee;
    
    // Update displays
    document.querySelectorAll('#subtotal-amount').forEach(el => {
        el.textContent = `<?= LanguageHelper::t('rs', 'Rs.') ?> ${subtotal.toFixed(2)}`;
    });
    
    // Update discount breakdown
    updateDiscountDisplay(productOffersDiscount, eventDiscountAmount);
    
    // Update total display
    document.querySelectorAll('#total-amount').forEach(el => {
        el.textContent = `<?= LanguageHelper::t('rs', 'Rs.') ?> ${total.toFixed(2)}`;
    });
    
    // Update hidden form fields with recalculated discount values
    // These values will be submitted with the order for accurate storage in database
    document.getElementById('applied_discount_field').value = totalDiscount.toFixed(2);
    document.getElementById('final_amount_field').value = total.toFixed(2);
    document.getElementById('subtotal_field').value = subtotal.toFixed(2);
    document.getElementById('bulk_savings_field').value = bulkSavings.toFixed(2);
    document.getElementById('product_offers_discount_field').value = productOffersDiscount.toFixed(2);
    document.getElementById('event_discount_field').value = eventDiscountAmount.toFixed(2);
    
    // Update offers display
    updateOffersDisplay();
    
    // CHECK VERIFICATION REQUIREMENT LIVE - no need to refresh!
    checkVerificationRequirement();
}

function updateDiscountDisplay(productOffersDiscount, eventDiscountAmount) {
    // Hide all discount rows first
    document.querySelectorAll('.discount').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show product offers discount if applicable
    if (productOffersDiscount > 0) {
        document.querySelectorAll('.discount[data-type="offer"]').forEach(el => {
            el.style.display = 'flex';
            el.querySelector('span:last-child').textContent = `-<?= LanguageHelper::t('rs', 'Rs.') ?> ${productOffersDiscount.toFixed(2)}`;
        });
    }
    
    // Show event discount if applicable
    if (eventDiscountAmount > 0) {
        document.querySelectorAll('.discount[data-type="event"]').forEach(el => {
            el.style.display = 'flex';
            el.querySelector('span:last-child').textContent = `-<?= LanguageHelper::t('rs', 'Rs.') ?> ${eventDiscountAmount.toFixed(2)}`;
        });
    }
}

// LIVE VERIFICATION CHECK - Updates warning dynamically when quantity changes
function checkVerificationRequirement() {
    try {
        // Calculate total quantity from current product data
        let totalQuantity = 0;
        Object.keys(productData).forEach(productId => {
            totalQuantity += productData[productId].currentQuantity || 0;
        });
        
        // Check if verification is required (bulk buyer OR quantity >= 5)
        let isVerificationRequired = (customerType === 'bulk' || totalQuantity >= 5);
        
        console.log('DEBUG: checkVerificationRequirement - totalQuantity:', totalQuantity, 'customerType:', customerType, 'isVerificationRequired:', isVerificationRequired);
        
        // Get warning elements for both Step 1 and Step 4
        const warningStep1 = document.getElementById('verificationWarning');
        const warningStep4 = document.getElementById('verificationWarningStep4');
        
        // Only update if user hasn't already verified
        if (!isPhoneVerified) {
            if (isVerificationRequired) {
                // SHOW warning - quantity >= 5 or bulk buyer
                if (warningStep1) {
                    warningStep1.style.display = 'flex';
                    console.log('DEBUG: Showing Step 1 verification warning');
                }
                if (warningStep4) {
                    warningStep4.style.display = 'block';
                    console.log('DEBUG: Showing Step 4 verification warning');
                }
            } else {
                // HIDE warning - quantity < 5 and not bulk buyer
                // Only hide if user hasn't started verification
                if (!verificationFormStarted && warningStep1) {
                    warningStep1.style.display = 'none';
                    console.log('DEBUG: Hiding Step 1 verification warning');
                }
                if (!verificationFormStartedStep4 && warningStep4) {
                    warningStep4.style.display = 'none';
                    console.log('DEBUG: Hiding Step 4 verification warning');
                }
            }
        } else {
            // Phone is already verified - hide all warnings
            if (warningStep1) {
                warningStep1.style.display = 'none';
                console.log('DEBUG: Phone already verified - hiding Step 1 warning');
            }
            if (warningStep4) {
                warningStep4.style.display = 'none';
                console.log('DEBUG: Phone already verified - hiding Step 4 warning');
            }
            
            // Show success messages ONLY if verification was just completed on this session
            if (verificationJustCompleted) {
                const successStep1 = document.getElementById('verificationSuccess');
                if (successStep1) {
                    successStep1.style.display = 'block';
                    console.log('DEBUG: Showing Step 1 success message (just completed)');
                }
            }
            
            if (verificationStep4JustCompleted) {
                const successStep4 = document.getElementById('verificationSuccessStep4');
                if (successStep4) {
                    successStep4.style.display = 'block';
                    console.log('DEBUG: Showing Step 4 success message (just completed)');
                }
            }
        }
    } catch (error) {
        console.error('ERROR in checkVerificationRequirement:', error);
    }
}

function handlePaymentMethodChange() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const qrContainer = document.getElementById('qrCodeContainer');
    const screenshotInput = document.getElementById('payment_screenshot');
    
    if (paymentMethod !== 'cod') {
        qrContainer.style.display = 'block';
        let walletType = paymentMethod === 'bank_transfer' ? 'bank' : paymentMethod;
        
        fetch('<?=$base_url?>/checkout/get-qr-image?type=' + walletType)
            .then(response => response.json())
            .then(data => {
                document.getElementById('qrImage').src = data.success ? data.qr_url : '<?=$assets_path?>/img/payment/placeholder_qr.jpg';
                currentQrImageUrl = data.success ? data.qr_url : '';
                if (!data.success) {
                    console.error('<?= LanguageHelper::t('error_loading_qr', 'Error loading QR image') ?>:', data.message);
                }
            })
            .catch(error => {
                console.error('<?= LanguageHelper::t('error_fetching_qr', 'Error fetching QR image') ?>:', error);
                document.getElementById('qrImage').src = '<?=$assets_path?>/img/payment/placeholder_qr.jpg';
                currentQrImageUrl = '';
            });
        screenshotInput.required = true;
    } else {
        qrContainer.style.display = 'none';
        screenshotInput.required = false;
    }
    saveFormData();
}

// WhatsApp Order Function - CORRECTED PHONE NUMBER FORMAT
// ✅ NEW: NOW WITH SECURITY VERIFICATION BEFORE SENDING TO WHATSAPP
function placeOrderViaWhatsApp() {
    // Validate form first
    if (!validateStep(4)) {
        return;
    }
    
    // Check minimum quantities
    const citySelect = document.getElementById('city');
    const selectedCity = citySelect.value;
    const cityId = cityIds[selectedCity];
    let hasMinimumQuantityIssue = false;
    
    Object.keys(productData).forEach(productId => {
        const quantity = getCurrentQuantity(parseInt(productId));
        const minQuantityInfo = minimumQuantities[productId]?.[cityId];
        if (minQuantityInfo && quantity < minQuantityInfo.minimum_quantity) {
            hasMinimumQuantityIssue = true;
            showMessage(`<?= LanguageHelper::t('minimum_quantity_not_met_for', 'Minimum quantity not met for') ?> ${minQuantityInfo.product_name}. <?= LanguageHelper::t('required', 'Required') ?>: ${minQuantityInfo.minimum_quantity} ${minQuantityInfo.unit}`, 'error', 'minimumQuantityMessage');
        }
    });
    
    if (hasMinimumQuantityIssue) {
        return;
    }
    
    // Get phone number for verification
    const phoneField = document.getElementById('phone');
    let phone = '<?= addslashes($customer_phone) ?>';
    
    if (phoneField) {
        const formPhone = phoneField.value?.trim();
        if (formPhone) phone = formPhone;
    }
    
    // 🔒 Check if phone is already verified
    checkWhatsAppPhoneVerification(phone).then(isVerified => {
        if (isVerified) {
            // Phone already verified - proceed directly with WhatsApp order
            proceedWithWhatsAppOrder();
        } else {
            // Show security verification form for WhatsApp
            showWhatsAppVerificationForm(phone);
        }
    });
}

/**
 * 🔒 Check if phone is already verified for WhatsApp
 */
function checkWhatsAppPhoneVerification(phone) {
    return new Promise((resolve) => {
        fetch('<?=$base_url?>/checkout/check-phone-verified', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'phone=' + encodeURIComponent(phone)
        })
        .then(response => {
            if (!response.ok) {
                console.error('Phone verification check failed:', response.status, response.statusText);
                return response.text().then(text => {
                    console.error('Response text:', text);
                    throw new Error(`HTTP ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Phone verification response:', data);
            if (data.success && data.verified) {
                console.log('Phone already verified, skipping OTP');
                resolve(true);
            } else {
                console.log('Phone not verified, showing OTP form');
                resolve(false);
            }
        })
        .catch(error => {
            console.error('Phone verification check error:', error);
            console.log('Proceeding with OTP verification');
            resolve(false); // Default to showing OTP form on error
        });
    });
}

/**
 * 🔒 Show security verification form for WhatsApp orders
 */
function showWhatsAppVerificationForm(phone) {
    console.log('Showing WhatsApp verification form for phone:', phone);
    
    // Create verification modal
    const modal = document.createElement('div');
    modal.id = 'whatsappVerificationModal';
    modal.className = 'modal';
    modal.style.cssText = 'display: flex; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;';
    
    modal.innerHTML = `
        <div style="background-color: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%; box-shadow: 0 4px 6px rgba(0,0,0,0.1); animation: slideUp 0.3s ease;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 28px; margin-bottom: 10px;">🔒</div>
                <h3 style="margin: 0; color: #333; font-size: 18px;">Security Verification</h3>
                <p style="color: #666; margin: 10px 0 0 0; font-size: 14px;">Please verify this phone number to confirm you own this account and complete your order.</p>
            </div>
            
            <div id="whatsappVerificationStep1" style="display: block;">
                <div id="whatsappOtpStatus" style="background-color: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ff9800; display: none;">
                    <div style="color: #e65100; font-size: 14px; font-weight: 500;">⏳ Sending OTP code...</div>
                </div>
                
                <div id="whatsappOtpSentSuccess" style="background-color: #f0f9ff; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196F3; display: none;">
                    <div style="color: #1976D2; font-size: 14px; font-weight: 500;">✅ Verification code sent to your phone</div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 500; color: #333; margin-bottom: 8px; font-size: 14px;">Enter OTP Code</label>
                    <input type="text" id="whatsappOtpInput" maxlength="6" placeholder="000000" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; text-align: center; letter-spacing: 8px; font-weight: bold;" />
                    <input type="hidden" id="whatsappPhoneForVerification" value="${phone}" />
                </div>
                
                <button id="whatsappVerifyBtn" onclick="submitWhatsAppVerification()" style="width: 100%; padding: 12px; background-color: #2196F3; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; font-size: 14px; margin-bottom: 10px;">Verify OTP</button>
                
                <div style="text-align: center;">
                    <span style="color: #666; font-size: 13px;">Didn't receive the code? </span>
                    <button id="whatsappResendBtn" onclick="resendWhatsAppOtp()" style="background: none; border: none; color: #2196F3; cursor: pointer; font-weight: 500; font-size: 13px;">Resend</button>
                </div>
            </div>
            
            <div id="whatsappVerificationStep2" style="display: none; text-align: center;">
                <div style="margin: 20px 0;">
                    <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                    <p style="color: #4CAF50; font-weight: 500; font-size: 16px;">Phone Verified Successfully!</p>
                    <p style="color: #666; font-size: 14px; margin-top: 10px;">You can now proceed with your WhatsApp order.</p>
                </div>
                <button onclick="closeWhatsAppVerificationModal(); proceedWithWhatsAppOrder();" style="width: 100%; padding: 12px; background-color: #4CAF50; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">Proceed to WhatsApp</button>
            </div>
            
            <div id="whatsappVerificationError" style="display: none; text-align: center;">
                <div style="background-color: #ffebee; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f44336;">
                    <p id="whatsappVerificationErrorMsg" style="color: #c62828; margin: 0; font-size: 14px;"></p>
                </div>
                <button onclick="resetWhatsAppVerificationForm()" style="width: 100%; padding: 12px; background-color: #2196F3; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">Try Again</button>
            </div>
            
            <div style="text-align: center; margin-top: 15px;">
                <p style="font-size: 12px; color: #999; margin: 0;">ℹ️ Your order will be automatically verified and completed after entering 6 digits.</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focus on OTP input
    setTimeout(() => {
        document.getElementById('whatsappOtpInput').focus();
    }, 100);
    
    // Allow Enter key to submit
    document.getElementById('whatsappOtpInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            submitWhatsAppVerification();
        }
    });
    
    // Send OTP immediately
    console.log('Auto-sending OTP to phone:', phone);
    sendWhatsAppOtpNow(phone);
}

/**
 * 🔒 NEW: Send OTP immediately when modal shows
 */
function sendWhatsAppOtpNow(phone) {
    const statusDiv = document.getElementById('whatsappOtpStatus');
    const successDiv = document.getElementById('whatsappOtpSentSuccess');
    
    if (statusDiv) statusDiv.style.display = 'block';
    if (successDiv) successDiv.style.display = 'none';
    
    console.log('Sending WhatsApp OTP to:', phone);
    
    fetch('<?=$base_url?>/checkout/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'verify_phone_whatsapp',
            phone: phone
        })
    })
    .then(response => {
        console.log('OTP send response status:', response.status);
        if (!response.ok) {
            console.error('OTP send failed with status:', response.status);
            return response.text().then(text => {
                console.error('Response text:', text.substring(0, 500));
                throw new Error(`HTTP ${response.status}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('OTP send response:', data);
        if (statusDiv) statusDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'block';
        
        if (data.success) {
            console.log('✅ OTP sent successfully');
            showMessage('Verification code sent to your phone', 'success', 'whatsappVerificationMessage');
        } else {
            console.error('OTP send failed:', data.message);
            showWhatsAppVerificationError(data.message || 'Failed to send OTP');
        }
    })
    .catch(error => {
        console.error('OTP send error:', error);
        if (statusDiv) statusDiv.style.display = 'none';
        showWhatsAppVerificationError('Failed to send OTP: ' + error.message + '. Please check your connection and try again.');
    });
}

/**
 * 🔒 Submit WhatsApp verification OTP
 */
function submitWhatsAppVerification() {
    const otp = document.getElementById('whatsappOtpInput')?.value || '';
    const phone = document.getElementById('whatsappPhoneForVerification')?.value || '';
    const verifyBtn = document.getElementById('whatsappVerifyBtn');
    
    if (!otp || otp.length !== 6) {
        showWhatsAppVerificationError('Please enter a valid 6-digit code');
        return;
    }
    
    // Disable button during verification
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying...';
    }
    
    console.log('Verifying OTP for phone:', phone);
    
    // Send verification request
    fetch('<?=$base_url?>/checkout/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'confirm_verification_whatsapp',
            phone: phone,
            code: otp
        })
    })
    .then(response => {
        console.log('Verification response status:', response.status);
        if (!response.ok) {
            console.error('Verification failed with status:', response.status);
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify OTP';
            }
            return response.text().then(text => {
                console.error('Response text:', text.substring(0, 500));
                throw new Error(`HTTP ${response.status}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Verification response:', data);
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify OTP';
        }
        
        if (data.success && data.verified) {
            console.log('✅ OTP verification successful');
            // Show success message
            document.getElementById('whatsappVerificationStep1').style.display = 'none';
            document.getElementById('whatsappVerificationError').style.display = 'none';
            document.getElementById('whatsappVerificationStep2').style.display = 'block';
            
            // Auto-proceed after 2 seconds
            setTimeout(() => {
                closeWhatsAppVerificationModal();
                proceedWithWhatsAppOrder();
            }, 2000);
        } else {
            console.error('OTP verification failed:', data.message);
            showWhatsAppVerificationError(data.message || 'Invalid verification code');
        }
    })
    .catch(error => {
        console.error('Verification error:', error);
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify OTP';
        }
        showWhatsAppVerificationError('Verification failed: ' + error.message);
    });
}

/**
 * 🔒 Show verification error message
 */
function showWhatsAppVerificationError(message) {
    document.getElementById('whatsappVerificationStep1').style.display = 'none';
    document.getElementById('whatsappVerificationError').style.display = 'block';
    document.getElementById('whatsappVerificationErrorMsg').textContent = message;
}

/**
 * 🔒 Reset verification form
 */
function resetWhatsAppVerificationForm() {
    document.getElementById('whatsappVerificationStep1').style.display = 'block';
    document.getElementById('whatsappVerificationError').style.display = 'none';
    document.getElementById('whatsappOtpInput').value = '';
    document.getElementById('whatsappOtpInput').focus();
    
    // Resend OTP
    const phone = document.getElementById('whatsappPhoneForVerification')?.value || '';
    resendWhatsAppOtp();
}

/**
 * 🔒 Resend WhatsApp verification OTP
 */
function resendWhatsAppOtp() {
    const phone = document.getElementById('whatsappPhoneForVerification')?.value || '';
    const resendBtn = document.getElementById('whatsappResendBtn');
    
    if (!phone) {
        console.error('Phone number not found');
        return;
    }
    
    // Disable resend button temporarily
    if (resendBtn) {
        resendBtn.disabled = true;
        resendBtn.textContent = 'Sending...';
    }
    
    console.log('Resending OTP to phone:', phone);
    
    // Send verification code request
    fetch('<?=$base_url?>/checkout/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'verify_phone_whatsapp',
            phone: phone
        })
    })
    .then(response => {
        console.log('Resend response status:', response.status);
        if (!response.ok) {
            console.error('Resend failed with status:', response.status);
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend';
            }
            return response.text().then(text => {
                console.error('Response text:', text.substring(0, 500));
                throw new Error(`HTTP ${response.status}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Resend response:', data);
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend';
        }
        
        if (data.success) {
            console.log('✅ OTP resent successfully');
            showMessage('New verification code sent to your phone', 'success', 'whatsappVerificationMessage');
            document.getElementById('whatsappOtpInput').value = '';
            document.getElementById('whatsappOtpInput').focus();
            startResendCooldownWhatsApp(resendBtn);
        } else {
            console.error('Resend failed:', data.message);
            showWhatsAppVerificationError(data.message || 'Failed to resend verification code');
        }
    })
    .catch(error => {
        console.error('Resend error:', error);
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend';
        }
        showWhatsAppVerificationError('Failed to resend code: ' + error.message);
    });
}

/**
 * 🔒 Start resend cooldown for WhatsApp
 */
function startResendCooldownWhatsApp(button, seconds = 60) {
    if (!button) return;
    
    button.disabled = true;
    let countdown = seconds;
    
    const timer = setInterval(() => {
        countdown--;
        button.textContent = `Resend (${countdown}s)`;
        
        if (countdown <= 0) {
            clearInterval(timer);
            button.disabled = false;
            button.textContent = 'Resend';
        }
    }, 1000);
}

/**
 * Close WhatsApp verification modal
 */
function closeWhatsAppVerificationModal() {
    const modal = document.getElementById('whatsappVerificationModal');
    if (modal) {
        modal.style.animation = 'slideDown 0.3s ease';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

/**
 * ✅ Proceed with actual WhatsApp order after verification
 */
function proceedWithWhatsAppOrder() {
    // FIX: Get field values for both logged-in and guest users
    // For logged-in users: use original customer data from PHP variables (primary contact info)
    // For guest users: use form field values (temporary customer data)
    const nameField = document.getElementById('name');
    const phoneField = document.getElementById('phone');
    const emailField = document.getElementById('email');
    
    // Use original customer data from PHP variables - these are always available and correct
    let name = '<?= addslashes($customer_name) ?>';
    let phone = '<?= addslashes($customer_phone) ?>';
    let email = '<?= addslashes($customer_email) ?>' || 'Not provided';
    
    // For guest users: override with form field values if they exist
    if (nameField) {
        const formName = nameField.value?.trim();
        if (formName) name = formName;
    }
    if (phoneField) {
        const formPhone = phoneField.value?.trim();
        if (formPhone) phone = formPhone;
    }
    if (emailField) {
        const formEmail = emailField.value?.trim();
        if (formEmail) email = formEmail;
    }
    
    console.log('WhatsApp Order - Using Name:', name, 'Phone:', phone, 'Email:', email);
    
    // Get address field - different ID for logged-in vs guest users
    // For delivery address, use the form field if filled, otherwise use original address
    const addressField = document.getElementById('address') || document.getElementById('address_delivery');
    const address = (addressField?.value?.trim()) || '<?= addslashes($customer_address) ?>' || '';
    
    // Collect order data
    const orderData = {
        name: name,
        phone: phone,
        email: email,
        address: address,
        city: document.getElementById('city').value,
        delivery_date: document.getElementById('delivery_date').value,
        delivery_time: document.getElementById('delivery_time').value,
        notes: document.getElementById('notes')?.value || 'No special instructions',
        payment_method: document.querySelector('input[name="payment_method"]:checked')?.value || 'cod',
        items: [],
        subtotal: document.getElementById('subtotal-amount')?.textContent || '0',
        delivery_fee: document.getElementById('delivery-fee')?.textContent || '0',
        total: document.getElementById('total-amount')?.textContent || '0'
    };
    
    // Add items to order data
    document.querySelectorAll('.order-item').forEach(item => {
        const productId = item.getAttribute('data-product-id');
        const productName = item.querySelector('.item-name').textContent;
        const quantity = item.querySelector('.quantity-input').value;
        const unit = productData[productId]?.unit || 'pcs';
        const price = productData[productId]?.finalPrice || 0;
        const total = item.querySelector('.total-price').textContent;
        
        orderData.items.push({
            name: productName,
            quantity: quantity,
            unit: unit,
            price: price,
            total: total
        });
    });
    
    // Format WhatsApp message
    const whatsappMessage = formatWhatsAppMessage(orderData);
    
    // Encode message for URL
    const encodedMessage = encodeURIComponent(whatsappMessage);
    
    // CORRECTED: Create WhatsApp URL with proper phone number format
    // Use only the 10-digit number without country code or plus sign
    const whatsappUrl = `https://wa.me/977${whatsappBusinessNumber}?text=${encodedMessage}`;
    
    // Open WhatsApp in new tab
    window.open(whatsappUrl, '_blank');
    
    // Submit order to database with WhatsApp type and proceed to confirmation
    submitWhatsAppOrder(orderData);
}

function formatWhatsAppMessage(orderData) {
    let message = `🌸 *NEW ORDER REQUEST - Phool Delivery* 🌸\n\n`;
    message += `*Customer Details:*\n`;
    message += `👤 Name: ${orderData.name}\n`;
    message += `📞 Phone: ${orderData.phone}\n`;
    message += `📧 Email: ${orderData.email}\n`;
    message += `📍 Address: ${orderData.address}, ${orderData.city}\n\n`;
    
    message += `*Delivery Details:*\n`;
    message += `📅 Date: ${orderData.delivery_date}\n`;
    message += `⏰ Time: ${orderData.delivery_time}\n`;
    message += `📝 Instructions: ${orderData.notes}\n\n`;
    
    message += `*Order Items:*\n`;
    orderData.items.forEach((item, index) => {
        message += `${index + 1}. ${item.name}\n`;
        message += `   Quantity: ${item.quantity} ${item.unit}\n`;
        message += `   Price: Rs. ${item.price}/${item.unit}\n`;
        message += `   Total: ${item.total}\n\n`;
    });
    
    message += `*Order Summary:*\n`;
    message += `Subtotal: ${orderData.subtotal}\n`;
    message += `Delivery Fee: ${orderData.delivery_fee}\n`;
    message += `*Grand Total: ${orderData.total}*\n\n`;
    
    message += `*Payment Method:* ${orderData.payment_method.toUpperCase()}\n\n`;
    
    return message;
}

function submitWhatsAppOrder(orderData) {
    const formData = new FormData();
    
    // Add all form data
    formData.append('name', orderData.name);
    formData.append('phone', orderData.phone);
    formData.append('email', orderData.email);
    formData.append('address', orderData.address);
    formData.append('city', orderData.city);
    formData.append('delivery_date', orderData.delivery_date);
    formData.append('delivery_time', orderData.delivery_time);
    formData.append('notes', orderData.notes);
    formData.append('payment_method', orderData.payment_method);
    formData.append('order_type', 'whatsapp');
    formData.append('terms', '1');
    
    // Add cart items
    Object.keys(productData).forEach(productId => {
        const quantity = getCurrentQuantity(parseInt(productId));
        formData.append('items[]', JSON.stringify({
            id: productId,
            quantity: quantity
        }));
    });
    
    fetch('<?=$base_url?>/checkout/process', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('<?= LanguageHelper::t('whatsapp_order_submitted', 'Your order has been submitted via WhatsApp! Please complete the conversation on WhatsApp.') ?>', 'success', 'addressUpdateMessage');
            
            // ✅ CRITICAL FIX: Automatically proceed to confirmation step for WhatsApp orders
            document.getElementById('order-confirmation-number').textContent = data.order_number;
            navigateToStep(5);
            
            // Clear cart after successful WhatsApp order
            setTimeout(() => {
                // Clear saved form data after successful order
                clearFormData();
            }, 3000);
        } else {
            showMessage('<?= LanguageHelper::t('whatsapp_order_failed', 'Order submission failed. Please try again or contact support.') ?>', 'error', 'addressUpdateMessage');
        }
    })
    .catch(error => {
        console.error('Error submitting WhatsApp order:', error);
        showMessage('<?= LanguageHelper::t('whatsapp_order_error', 'Error submitting order. Please try again.') ?>', 'error', 'addressUpdateMessage');
    });
}

function openQrModal(imageUrl) {
    if (!imageUrl) return;
    document.getElementById('modalQrImage').src = imageUrl;
    document.getElementById('qrModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function downloadQrImage() {
    if (!currentQrImageUrl) {
        showMessage('<?= LanguageHelper::t('no_qr_available', 'No QR code available to download') ?>', 'error', 'verificationMessage');
        return;
    }
    const a = document.createElement('a');
    a.href = currentQrImageUrl;
    a.download = 'payment-qr-code.jpg';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showMessage('<?= LanguageHelper::t('qr_code_downloaded', 'QR code downloaded successfully') ?>', 'success', 'verificationMessage');
}

// Check if phone number is already verified (for guest users)
function checkPhoneVerificationStatus(phone) {
    console.log('checkPhoneVerificationStatus called with phone:', phone);
    
    return fetch('<?=$base_url?>/customer/check-phone-verified', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Phone verification check response:', data);
        return data;
    })
    .catch(error => {
        console.error('Error checking phone verification status:', error);
        return { verified: false, error: true };
    });
}

function sendVerificationCode(phone) {
    console.log('sendVerificationCode called with phone:', phone);
    
    // Safety check for phone parameter
    if (!phone || phone.trim() === '') {
        console.error('Phone parameter is empty');
        showMessage(
            '<?= LanguageHelper::t('please_enter_phone', 'Please enter your phone number first') ?>',
            'error',
            'verificationMessage'
        );
        return;
    }
    
    // Show loading state
    const verifyBtn = document.getElementById('verifyAccountBtn');
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<span class="spinner"></span> <?= LanguageHelper::t('sending_otp', 'Sending OTP...') ?>';
    }
    
    const requestPayload = { phone: phone };
    console.log('Sending OTP request:', requestPayload);
    
    fetch('<?=$base_url?>/otp/send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(requestPayload)
    })
    .then(response => {
        console.log('OTP response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('OTP response data:', data);
        
        // Re-enable button
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<?= LanguageHelper::t('verify_now', 'Verify now') ?>';
        }
        
        if (data.success) {
            console.log('OTP sent successfully');
            // Show success message
            showMessage(
                '<?= LanguageHelper::t('verification_code_sent', 'Verification code sent to your phone') ?>',
                'success',
                'verificationMessage'
            );
            // IMPORTANT: Only show verification form AFTER successful OTP send
            const formContainer = document.getElementById('verificationFormContainer');
            if (formContainer) {
                setTimeout(() => {
                    formContainer.style.display = 'block';
                    // Mark that user has started verification in Step 1
                    verificationFormStarted = true;
                    // Start the 60-second cooldown timer
                    startResendCooldown('resendCodeBtn', 'resendCooldownTimer', 'cooldownSeconds', 60);
                }, 500);
            } else {
                console.warn('verificationFormContainer not found in DOM');
            }
        } else {
            console.log('OTP send failed:', data.message);
            // Show error message - do NOT show verification form
            showMessage(
                '<?= LanguageHelper::t('error_sending_code', 'Error sending verification code') ?>: ' + (data.message || data.error),
                'error',
                'verificationMessage'
            );
        }
    })
    .catch(error => {
        console.error('OTP fetch error:', error);
        
        // Re-enable button
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<?= LanguageHelper::t('verify_now', 'Verify now') ?>';
        }
        
        console.error('<?= LanguageHelper::t('error_sending_code', 'Error sending verification code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_sending_code_try_again', 'Error sending verification code. Please try again.') ?>', 'error', 'verificationMessage');
    });
}

function verifyCode() {
    const codeElement = document.getElementById('verification_code');
    
    if (!codeElement) {
        console.error('OTP code input element not found');
        return showMessage('<?= LanguageHelper::t('error_form_field', 'Form field not found. Please refresh the page.') ?>', 'error', 'verificationMessage');
    }
    
    const code = codeElement.value;
    
    if (!code) {
        return showMessage('<?= LanguageHelper::t('please_enter_verification_code', 'Please enter the verification code') ?>', 'error', 'verificationMessage');
    }
    
    // Get phone from database/session OR from guest phone form
    let phone = '<?= addslashes($customer_phone) ?>';
    
    // For guest users, check if phone was captured in guest phone form
    if (!phone || phone.trim() === '') {
        const guestPhoneInput = document.getElementById('guest_phone_number');
        if (guestPhoneInput && guestPhoneInput.value) {
            phone = guestPhoneInput.value;
        }
    }
    
    if (!phone || phone.trim() === '') {
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
    }
    
    if (!phone || phone.trim() === '') {
        const contactPhone = '<?= addslashes($customer_contact) ?>';
        if (contactPhone && contactPhone.trim() !== '') {
            phone = contactPhone;
        }
    }
    
    if (!phone || phone.trim() === '') {
        return showMessage('<?= LanguageHelper::t('phone_not_available', 'Phone number not available. Please check your profile.') ?>', 'error', 'verificationMessage');
    }
    
    // Show loading state on verify button
    const submitBtn = document.getElementById('submitVerificationBtn');
    const originalText = submitBtn?.textContent || '<?= LanguageHelper::t('verify_account', 'Verify Account') ?>';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('verifying', 'Verifying...') ?>';
    }
    
    fetch('<?=$base_url?>/otp/verify', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone, otp_code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let successMessage = '✓ <?= LanguageHelper::t('phone_verified_successfully', 'Phone number verified successfully!') ?>';
            if (isVerificationRequired && customerType === 'bulk') {
                successMessage += ' You are now verified as a bulk buyer. You can proceed with your order!';
            } else if (isVerificationRequired) {
                successMessage += ' Your order of 5+ items is now verified. Click "Next" to continue!';
            }
            
            showMessage(successMessage, 'success', 'verificationMessage');
            const statusElement = document.querySelector('.verification-status');
            if (statusElement) {
                statusElement.textContent = '✓ <?= LanguageHelper::t('verified', 'Verified') ?>';
                statusElement.className = 'verification-status verified';
            }
            
            const warningElement = document.getElementById('verificationWarning');
            if (warningElement) {
                warningElement.style.display = 'none';
            }
            
            // ✅ IMPORTANT: Set isPhoneVerified to true so validation passes
            isPhoneVerified = true;
            // ✅ IMPORTANT: Set flag that verification was just completed on this session
            verificationJustCompleted = true;
            
            // ✅ NEW: Mark this as SECURITY VERIFICATION (for existing registered phones)
            // This is different from QUANTITY VERIFICATION (for bulk orders)
            securityVerificationCompleted = true;
            console.log('✓ SECURITY VERIFICATION COMPLETED for phone:', phone);
            
            // ✅ NEW: Store verified phone in session and database for guest users
            if (phone) {
                sessionStorage.setItem('verified_phone', phone);
                sessionStorage.setItem('phone_verified_at', new Date().toISOString());
                // ✅ NEW: Also mark security verification in session
                sessionStorage.setItem('security_verification_completed', 'true');
                console.log('Phone verified and stored in session:', phone);
                
                // Store verified phone in database via API call
                fetch('<?=$base_url?>/customer/store-guest-verified-phone', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ phone: phone })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Guest phone stored in database:', data);
                })
                .catch(error => {
                    console.error('Error storing phone in database:', error);
                });
            }
            
            // Hide form after 2 seconds to let user see success message
            setTimeout(hideVerificationForm, 2000);
        } else {
            showMessage('<?= LanguageHelper::t('invalid_verification_code', 'Invalid verification code. Please try again.') ?>', 'error', 'verificationMessage');
            
            // Restore button on error
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('<?= LanguageHelper::t('error_verifying_code', 'Error verifying code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_verifying_code_try_again', 'Error verifying code. Please try again.') ?>', 'error', 'verificationMessage');
        
        // Restore button on error
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function resendCode() {
    // Get phone from database/session (same as showVerificationForm)
    let phone = '<?= addslashes($customer_phone) ?>';
    
    if (!phone || phone.trim() === '') {
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
    }
    
    if (!phone || phone.trim() === '') {
        const contactPhone = '<?= addslashes($customer_contact) ?>';
        if (contactPhone && contactPhone.trim() !== '') {
            phone = contactPhone;
        }
    }
    
    if (!phone || phone.trim() === '') {
        return showMessage('<?= LanguageHelper::t('phone_not_available', 'Phone number not available. Please check your profile.') ?>', 'error', 'verificationMessage');
    }
    
    // Disable resend button during request
    const resendBtn = document.getElementById('resendCodeBtn');
    if (resendBtn) {
        resendBtn.disabled = true;
    }
    
    fetch('<?=$base_url?>/otp/resend', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('<?= LanguageHelper::t('verification_code_resent', 'Verification code resent to your phone') ?>', 'success', 'verificationMessage');
            // Start the 60-second cooldown timer
            startResendCooldown('resendCodeBtn', 'resendCooldownTimer', 'cooldownSeconds', 60);
        } else {
            const message = data.message || '<?= LanguageHelper::t('error_resending_code', 'Error resending verification code') ?>';
            showMessage(message, 'error', 'verificationMessage');
            
            // Re-enable button on error
            if (resendBtn) {
                resendBtn.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('<?= LanguageHelper::t('error_resending_code', 'Error resending verification code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_resending_code_try_again', 'Error resending verification code. Please try again.') ?>', 'error', 'verificationMessage');
        
        // Re-enable button on error
        if (resendBtn) {
            resendBtn.disabled = false;
        }
    });
}

function showVerificationForm() {
    // Get phone from PHP database/session variable (set at line 1931)
    // This is the most reliable source - always available from database
    let phone = '<?= addslashes($customer_phone) ?>';
    const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
    
    // For logged-in users, use their phone from database
    if (isLoggedIn && phone && phone.trim() !== '') {
        // Clean phone number for validation
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        
        // Validate Nepal phone format (10 digits starting with 9)
        if (cleanPhone.length !== 10 || !cleanPhone.startsWith('9')) {
            showMessage(
                '<?= LanguageHelper::t('invalid_phone_format', 'Invalid phone number format. Phone must be 10 digits starting with 9.') ?>',
                'error',
                'verificationMessage'
            );
            return;
        }
        
        // Send OTP - form will be shown only if OTP is sent successfully
        sendVerificationCode(phone);
    } else if (!isLoggedIn) {
        // For guest users: Show phone number input form first
        const guestPhoneForm = document.getElementById('guestPhoneFormContainer');
        if (guestPhoneForm) {
            guestPhoneForm.style.display = 'block';
            guestPhoneForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } else {
        // Fallback: Check if there's a phone input field for guest users
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
        
        // If still no phone, check session/secondary contact as last resort
        if (!phone || phone.trim() === '') {
            const contactPhone = '<?= addslashes($customer_contact) ?>';
            if (contactPhone && contactPhone.trim() !== '') {
                phone = contactPhone;
            }
        }
        
        // Validate that we have a phone number
        if (!phone || phone.trim() === '') {
            showMessage(
                '<?= LanguageHelper::t('phone_not_available', 'Phone number not available in your profile. Please enter your phone number to proceed with verification.') ?>',
                'error',
                'verificationMessage'
            );
            
            // Show guest phone form as fallback
            const guestPhoneForm = document.getElementById('guestPhoneFormContainer');
            if (guestPhoneForm) {
                guestPhoneForm.style.display = 'block';
                guestPhoneForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        // Clean phone number for validation
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        
        // Validate Nepal phone format (10 digits starting with 9)
        if (cleanPhone.length !== 10 || !cleanPhone.startsWith('9')) {
            showMessage(
                '<?= LanguageHelper::t('invalid_phone_format', 'Invalid phone number format. Phone must be 10 digits starting with 9.') ?>',
                'error',
                'verificationMessage'
            );
            return;
        }
        
        // Send OTP - form will be shown only if OTP is sent successfully
        sendVerificationCode(phone);
    }
}

function hideVerificationForm() {
    const formContainer = document.getElementById('verificationFormContainer');
    if (formContainer) {
        formContainer.style.display = 'none';
    }
    
    const otpInput = document.getElementById('verification_code');
    if (otpInput) {
        otpInput.value = '';
    }
    
    // Reset the flag when user cancels verification
    verificationFormStarted = false;
}

// Step 4 Verification Functions
function showVerificationFormStep4() {
    // Get phone from PHP database/session variable
    let phone = '<?= addslashes($customer_phone) ?>';
    
    // Fallback: Check if there's a phone input field for guest users
    if (!phone || phone.trim() === '') {
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
    }
    
    // If still no phone, check secondary contact as last resort
    if (!phone || phone.trim() === '') {
        const contactPhone = '<?= addslashes($customer_contact) ?>';
        if (contactPhone && contactPhone.trim() !== '') {
            phone = contactPhone;
        }
    }
    
    // Validate that we have a phone number
    if (!phone || phone.trim() === '') {
        showMessage(
            '<?= LanguageHelper::t('phone_not_available', 'Phone number not available in your profile. Please check your account details.') ?>',
            'error',
            'verificationMessage'
        );
        return;
    }
    
    // Clean phone number for validation
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    
    // Validate Nepal phone format (10 digits starting with 9)
    if (cleanPhone.length !== 10 || !cleanPhone.startsWith('9')) {
        showMessage(
            '<?= LanguageHelper::t('invalid_phone_format', 'Invalid phone number format. Phone must be 10 digits starting with 9.') ?>',
            'error',
            'verificationMessage'
        );
        return;
    }
    
    // Send OTP for Step 4 verification - form will be shown only if OTP is sent successfully
    sendVerificationCodeStep4(phone);
}

function hideVerificationFormStep4() {
    const formContainer = document.getElementById('verificationFormContainerStep4');
    if (formContainer) {
        formContainer.style.display = 'none';
    }
    
    const otpInput = document.getElementById('verification_code_step4');
    if (otpInput) {
        otpInput.value = '';
    }
    
    // Reset the flag when user cancels verification in Step 4
    verificationFormStartedStep4 = false;
}

function sendVerificationCodeStep4(phone) {
    console.log('sendVerificationCodeStep4 called with phone:', phone);
    
    // Safety check for phone parameter
    if (!phone || phone.trim() === '') {
        console.error('Phone parameter is empty');
        showMessage(
            '<?= LanguageHelper::t('please_enter_phone', 'Please enter your phone number first') ?>',
            'error',
            'verificationMessage'
        );
        return;
    }
    
    // Show loading state
    const verifyBtn = document.getElementById('verifyAccountBtnStep4');
    if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<span class="spinner"></span> <?= LanguageHelper::t('sending_otp', 'Sending OTP...') ?>';
    }
    
    const requestPayload = { phone: phone };
    console.log('Sending OTP request for Step 4:', requestPayload);
    
    fetch('<?=$base_url?>/otp/send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(requestPayload)
    })
    .then(response => {
        console.log('OTP response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('OTP response data for Step 4:', data);
        
        // Re-enable button
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<?= LanguageHelper::t('verify_now', 'Verify now') ?>';
        }
        
        if (data.success) {
            console.log('OTP sent successfully for Step 4');
            // Show success message
            showMessage(
                '<?= LanguageHelper::t('verification_code_sent', 'Verification code sent to your phone') ?>',
                'success',
                'verificationMessage'
            );
            // IMPORTANT: Only show verification form AFTER successful OTP send
            const formContainer = document.getElementById('verificationFormContainerStep4');
            if (formContainer) {
                setTimeout(() => {
                    formContainer.style.display = 'block';
                    // Mark that user has started verification in Step 4
                    verificationFormStartedStep4 = true;
                    // Start the 60-second cooldown timer
                    startResendCooldown('resendCodeBtnStep4', 'resendCooldownTimerStep4', 'cooldownSecondsStep4', 60);
                }, 500);
            } else {
                console.warn('verificationFormContainerStep4 not found in DOM');
            }
        } else {
            console.log('OTP send failed for Step 4:', data.message);
            // Show error message - do NOT show verification form
            showMessage(
                '<?= LanguageHelper::t('error_sending_code', 'Error sending verification code') ?>: ' + (data.message || data.error),
                'error',
                'verificationMessage'
            );
        }
    })
    .catch(error => {
        console.error('OTP fetch error for Step 4:', error);
        
        // Re-enable button
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<?= LanguageHelper::t('verify_now', 'Verify now') ?>';
        }
        
        console.error('<?= LanguageHelper::t('error_sending_code', 'Error sending verification code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_sending_code_try_again', 'Error sending verification code. Please try again.') ?>', 'error', 'verificationMessage');
    });
}

function verifyCodeStep4() {
    const codeElement = document.getElementById('verification_code_step4');
    
    if (!codeElement) {
        console.error('OTP code input element (step 4) not found');
        return showMessage('<?= LanguageHelper::t('error_form_field', 'Form field not found. Please refresh the page.') ?>', 'error', 'verificationMessage');
    }
    
    const code = codeElement.value;
    
    if (!code) {
        return showMessage('<?= LanguageHelper::t('please_enter_verification_code', 'Please enter the verification code') ?>', 'error', 'verificationMessage');
    }
    
    // Get phone from database/session
    let phone = '<?= addslashes($customer_phone) ?>';
    
    if (!phone || phone.trim() === '') {
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
    }
    
    if (!phone || phone.trim() === '') {
        const contactPhone = '<?= addslashes($customer_contact) ?>';
        if (contactPhone && contactPhone.trim() !== '') {
            phone = contactPhone;
        }
    }
    
    if (!phone || phone.trim() === '') {
        return showMessage('<?= LanguageHelper::t('phone_not_available', 'Phone number not available. Please check your profile.') ?>', 'error', 'verificationMessage');
    }
    
    // Show loading state on verify button
    const submitBtn = document.getElementById('submitVerificationBtnStep4');
    const originalText = submitBtn?.textContent || '<?= LanguageHelper::t('verify_account', 'Verify Account') ?>';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('verifying', 'Verifying...') ?>';
    }
    
    fetch('<?=$base_url?>/otp/verify', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone, otp_code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide warning and form
            const warningElement = document.getElementById('verificationWarningStep4');
            if (warningElement) {
                warningElement.style.display = 'none';
            }
            
            const formContainer = document.getElementById('verificationFormContainerStep4');
            if (formContainer) {
                formContainer.style.display = 'none';
            }
            
            // Show success message
            const successElement = document.getElementById('verificationSuccessStep4');
            if (successElement) {
                successElement.style.display = 'block';
            }
            
            // Show success message in the alert area too
            let successMessage = '<?= LanguageHelper::t('phone_verified_successfully', 'Phone number verified successfully!') ?>';
            const isVerificationRequired = <?= $is_verification_required ? 'true' : 'false' ?>;
            const customerType = '<?= $customer_type ?>';
            
            if (isVerificationRequired && customerType === 'bulk') {
                successMessage += ' ✓ <?= LanguageHelper::t('bulk_verification_complete', 'As a bulk buyer, you can now proceed with your order.') ?>';
            } else if (isVerificationRequired) {
                successMessage += ' ✓ <?= LanguageHelper::t('order_verification_complete', 'Your order of 5+ items is now ready to proceed.') ?>';
            }
            
            showMessage(successMessage, 'success', 'verificationMessage');
            isPhoneVerified = true;
            // ✅ IMPORTANT: Set flag that Step 4 verification was just completed on this session
            verificationStep4JustCompleted = true;
            
            // ✅ NEW: Mark this as QUANTITY VERIFICATION (different from SECURITY VERIFICATION)
            // QUANTITY verification is for bulk orders (qty >= 5)
            quantityVerificationCompleted = true;
            console.log('✓ QUANTITY VERIFICATION COMPLETED for phone:', phone);
            
            // ✅ NEW: Store verified phone in session for guest users
            if (phone) {
                sessionStorage.setItem('verified_phone', phone);
                sessionStorage.setItem('phone_verified_at', new Date().toISOString());
                // ✅ NEW: Also mark quantity verification in session
                sessionStorage.setItem('quantity_verification_completed', 'true');
                console.log('Guest phone verified in Step 4 (QUANTITY) and stored in session:', phone);
                
                // Store in database via API call
                fetch('<?=$base_url?>/customer/store-guest-verified-phone', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ phone: phone })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Guest phone stored in database (Step 4 QUANTITY):', data);
                })
                .catch(error => {
                    console.error('Error storing phone in database:', error);
                });
            }
            
            // ✅ NEW: If order submission is pending, auto-submit after OTP verification
            if (autoSubmitOrderAfterOtp && pendingOrderFormData) {
                console.log('✓ Auto-submitting order after OTP verification...');
                
                // Hide OTP form
                const formContainer = document.getElementById('verificationFormContainerStep4');
                if (formContainer) {
                    formContainer.style.display = 'none';
                }
                
                // Wait 1.5 seconds for user to see success message, then submit
                setTimeout(() => {
                    console.log('Submitting order automatically...');
                    
                    // Submit the pending order form data
                    fetch('<?=$base_url?>/checkout/process', {
                        method: 'POST',
                        body: pendingOrderFormData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('✓ Order submitted successfully:', data);
                        
                        if (data.success) {
                            // Show confirmation
                            document.getElementById('order-confirmation-number').textContent = data.order_number;
                            navigateToStep(5);
                            clearFormData();
                        } else {
                            showMessage(data.message || 'Order submission failed', 'error', 'addressUpdateMessage');
                        }
                    })
                    .catch(error => {
                        console.error('Error auto-submitting order:', error);
                        showMessage('Error submitting order: ' + error.message, 'error', 'addressUpdateMessage');
                    })
                    .finally(() => {
                        // Reset flags
                        autoSubmitOrderAfterOtp = false;
                        pendingOrderFormData = null;
                    });
                }, 1500);
            }
        } else {
            showMessage('<?= LanguageHelper::t('invalid_verification_code', 'Invalid verification code. Please try again.') ?>', 'error', 'verificationMessage');
            
            // Restore button on error
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('<?= LanguageHelper::t('error_verifying_code', 'Error verifying code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_verifying_code_try_again', 'Error verifying code. Please try again.') ?>', 'error', 'verificationMessage');
        
        // Restore button on error
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
        
        // Reset flags
        autoSubmitOrderAfterOtp = false;
        pendingOrderFormData = null;
    });
}

function resendCodeStep4() {
    // Get phone from database/session
    let phone = '<?= addslashes($customer_phone) ?>';
    
    if (!phone || phone.trim() === '') {
        const phoneElement = document.getElementById('phone');
        if (phoneElement) {
            phone = phoneElement.value;
        }
    }
    
    if (!phone || phone.trim() === '') {
        const contactPhone = '<?= addslashes($customer_contact) ?>';
        if (contactPhone && contactPhone.trim() !== '') {
            phone = contactPhone;
        }
    }
    
    if (!phone || phone.trim() === '') {
        return showMessage('<?= LanguageHelper::t('phone_not_available', 'Phone number not available. Please check your profile.') ?>', 'error', 'verificationMessage');
    }
    
    fetch('<?=$base_url?>/otp/resend', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('<?= LanguageHelper::t('verification_code_resent', 'Verification code resent to your phone') ?>', 'success', 'verificationMessage');
            // Start the 60-second cooldown timer for Step 4
            startResendCooldown('resendCodeBtnStep4', 'resendCooldownTimerStep4', 'cooldownSecondsStep4', 60);
        } else {
            const message = data.message || '<?= LanguageHelper::t('error_resending_code', 'Error resending verification code') ?>';
            showMessage(message, 'error', 'verificationMessage');
        }
    })
    .catch(error => {
        console.error('<?= LanguageHelper::t('error_resending_code', 'Error resending verification code') ?>:', error);
        showMessage('<?= LanguageHelper::t('error_resending_code_try_again', 'Error resending verification code. Please try again.') ?>', 'error', 'verificationMessage');
    });
}

// ✅ NEW: Functions for Step 4.5 - Security Verification (for existing registered phones)

function showSecurityVerificationStep() {
    console.log('📍 Showing Step 4.5 - Security Verification');
    
    // Hide Step 4 (Payment)
    const step4 = document.querySelector('.form-step[data-step="4"]');
    if (step4) {
        step4.style.display = 'none';
        step4.classList.remove('active');
    }
    
    // Show Step 4.5 (Security Verification)
    const step45 = document.querySelector('.form-step[data-step="4.5"]');
    if (step45) {
        step45.style.display = 'block';
        setTimeout(() => {
            step45.classList.add('active');
        }, 10);
    }
    
    // Clear previous code input
    const codeInput = document.getElementById('security_verification_code');
    if (codeInput) {
        codeInput.value = '';
    }
    
    // Clear previous message
    const messageArea = document.getElementById('security_verification_message');
    if (messageArea) {
        messageArea.textContent = '';
        messageArea.style.display = 'none';
    }
    
    // ✅ NEW: Check if phone was already verified in THIS session
    const alreadyVerifiedPhone = sessionStorage.getItem('security_verified_phone');
    const alreadyVerifiedTime = sessionStorage.getItem('security_verified_at');
    
    // Get current phone from multiple sources
    let phone = null;
    phone = sessionStorage.getItem('verified_phone') || sessionStorage.getItem('guest_phone_number');
    
    if (!phone) {
        const phoneInput = document.getElementById('phone');
        if (phoneInput && phoneInput.value) {
            phone = phoneInput.value.trim();
        }
    }
    
    if (!phone) {
        const phoneInputAlt = document.querySelector('input[name="phone"], input[name="phone_number"], input[name="customer_phone"]');
        if (phoneInputAlt && phoneInputAlt.value) {
            phone = phoneInputAlt.value.trim();
        }
    }
    
    // ✅ Check if THIS phone was already verified in current session
    if (alreadyVerifiedPhone === phone && alreadyVerifiedTime) {
        console.log('✓ Phone already verified in this session:', phone);
        console.log('✓ Verified at:', alreadyVerifiedTime);
        
        // Show that it's already verified - no need to send OTP again
        showOtpSendMessage('✅ <?= LanguageHelper::t('phone_already_verified', 'Phone number already verified in this session!') ?>', 'success');
        
        // Mark as ready for auto-submit
        securityVerificationCompleted = true;
        
        // Auto-submit order after 1 second
        setTimeout(() => {
            console.log('📍 Auto-submitting order (phone already verified)...');
            
            if (pendingOrderFormData) {
                pendingOrderFormData.append('security_verified', 'true');
                pendingOrderFormData.append('phone', phone);
                
                fetch('<?=$base_url?>/checkout/process', {
                    method: 'POST',
                    body: pendingOrderFormData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('✓ Order submitted (already verified):', data);
                    
                    if (data.success) {
                        console.log('✓ Order placed successfully:', data.order_number);
                        document.getElementById('order-confirmation-number').textContent = data.order_number;
                        hideSecurityVerificationStep();
                        navigateToStep(5);
                        clearFormData();
                    } else {
                        console.error('Order submission failed:', data.message);
                        showOtpSendMessage(data.message || 'Order submission failed. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error submitting order:', error);
                    showOtpSendMessage('Error submitting order: ' + error.message, 'error');
                });
            }
        }, 1000);
        
        return;
    }
    
    // ✅ NEW: Send OTP automatically when showing Step 4.5 (only if not already verified)
    if (!phone) {
        console.error('Phone number not found in any source');
        showOtpSendMessage('❌ Error: Phone number not available. Please refresh and try again.', 'error');
        return;
    }
    
    // Store phone in sessionStorage for later use
    sessionStorage.setItem('verified_phone', phone);
    console.log('✓ Phone stored in session: ' + phone);
    
    console.log('📱 Sending OTP to phone:', phone);
    
    // Show loading message
    showOtpSendMessage('⏳ Sending verification code...', 'info');
    
    // Send OTP
    fetch('<?=$base_url?>/otp/send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✓ OTP sent successfully to phone:', phone);
            showOtpSendMessage('✅ <?= LanguageHelper::t('verification_code_sent', 'Verification code sent to your phone!') ?>', 'success');
            
            // Focus on OTP input after showing success
            setTimeout(() => {
                if (codeInput) {
                    codeInput.focus();
                }
            }, 500);
        } else {
            console.error('Failed to send OTP:', data.message);
            showOtpSendMessage('❌ ' + (data.message || '<?= LanguageHelper::t('error_sending_otp', 'Error sending verification code') ?>'), 'error');
        }
    })
    .catch(error => {
        console.error('Error sending OTP:', error);
        showOtpSendMessage('❌ <?= LanguageHelper::t('error_sending_otp', 'Error sending verification code') ?>', 'error');
    });
    
    console.log('✓ Step 4.5 displayed');
}

function hideSecurityVerificationStep() {
    console.log('📍 Hiding Step 4.5 - Security Verification');
    
    const step45 = document.querySelector('.form-step[data-step="4.5"]');
    if (step45) {
        step45.style.display = 'none';
        step45.classList.remove('active');
    }
    
    // Clear stored form data
    pendingOrderFormData = null;
    autoSubmitOrderAfterOtp = false;
}

function showOtpSendMessage(message, type) {
    const messageArea = document.getElementById('security_otp_send_message');
    if (!messageArea) {
        console.error('OTP send message area not found');
        return;
    }
    
    messageArea.textContent = message;
    messageArea.style.display = 'block';
    
    if (type === 'success') {
        messageArea.style.backgroundColor = '#d4edda';
        messageArea.style.color = '#155724';
        messageArea.style.borderLeft = '4px solid #28a745';
        messageArea.style.borderRadius = '6px';
    } else if (type === 'error') {
        messageArea.style.backgroundColor = '#f8d7da';
        messageArea.style.color = '#721c24';
        messageArea.style.borderLeft = '4px solid #dc3545';
        messageArea.style.borderRadius = '6px';
    } else {
        messageArea.style.backgroundColor = '#e2e3e5';
        messageArea.style.color = '#383d41';
        messageArea.style.borderLeft = '4px solid #6c757d';
        messageArea.style.borderRadius = '6px';
    }
}

function verifySecurityCodeStep45() {
    console.log('🔒 Verifying Security Code for Step 4.5...');
    
    const codeElement = document.getElementById('security_verification_code');
    
    if (!codeElement) {
        console.error('Security OTP code input element not found');
        return showMessageStep45('Form field not found. Please refresh the page.', 'error');
    }
    
    const code = codeElement.value;
    
    if (!code) {
        return showMessageStep45('<?= LanguageHelper::t('please_enter_verification_code', 'Please enter the verification code') ?>', 'error');
    }
    
    // Get phone from multiple sources (prioritized)
    let phone = sessionStorage.getItem('verified_phone') || sessionStorage.getItem('guest_phone_number');
    
    // If not in session, try form input
    if (!phone) {
        const phoneInput = document.getElementById('phone');
        if (phoneInput && phoneInput.value) {
            phone = phoneInput.value.trim();
        }
    }
    
    // If still not found, try alternate input names
    if (!phone) {
        const phoneInputAlt = document.querySelector('input[name="phone"], input[name="phone_number"], input[name="customer_phone"]');
        if (phoneInputAlt && phoneInputAlt.value) {
            phone = phoneInputAlt.value.trim();
        }
    }
    
    if (!phone) {
        console.error('Phone number not found in any source');
        return showMessageStep45('Phone number not available. Please refresh and try again.', 'error');
    }
    
    console.log('✓ Verifying OTP for phone:', phone);
    
    // Show loading state
    const submitBtn = document.getElementById('confirmSecurityVerificationBtn');
    const originalText = submitBtn?.textContent || '<?= LanguageHelper::t('verify_account', 'Verify Account') ?>';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('verifying', 'Verifying...') ?>';
    }
    
    fetch('<?=$base_url?>/otp/verify', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone, otp_code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✓ Security verification successful for phone:', phone);
            
            // ✅ NEW: Store verified phone and timestamp in session
            // This prevents re-sending OTP if user refreshes or returns to Step 4.5
            const now = new Date().toISOString();
            sessionStorage.setItem('security_verified_phone', phone);
            sessionStorage.setItem('security_verified_at', now);
            console.log('✅ Stored verification in session - Phone:', phone, 'Time:', now);
            
            // Mark security verification as completed
            securityVerificationCompleted = true;
            sessionStorage.setItem('security_verification_completed', 'true');
            console.log('✓ SECURITY VERIFICATION COMPLETED and stored in session');
            
            // Show success message
            showMessageStep45('<?= LanguageHelper::t('phone_verified_successfully', 'Phone number verified successfully!') ?> ✓', 'success');
            
            // Wait 1.5 seconds for user to see success message
            setTimeout(() => {
                console.log('📍 Auto-submitting order after security verification...');
                
                if (pendingOrderFormData) {
                    // ✅ NEW: Add security verification flag to form data
                    // This tells backend that security verification was already completed
                    pendingOrderFormData.append('security_verified', 'true');
                    pendingOrderFormData.append('phone', phone);
                    console.log('✓ Added security_verified flag to form data');
                    
                    // Submit the pending order form data
                    fetch('<?=$base_url?>/checkout/process', {
                        method: 'POST',
                        body: pendingOrderFormData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('✓ Order submitted after security verification:', data);
                        
                        if (data.success) {
                            console.log('✓ Order placed successfully:', data.order_number);
                            document.getElementById('order-confirmation-number').textContent = data.order_number;
                            
                            // Hide Step 4.5 and go to Step 5 (Confirmation)
                            hideSecurityVerificationStep();
                            navigateToStep(5);
                            clearFormData();
                        } else {
                            console.error('Order submission failed:', data.message);
                            showMessageStep45(data.message || 'Order submission failed. Please try again.', 'error');
                            
                            // Re-enable button
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error auto-submitting order:', error);
                        showMessageStep45('Error submitting order: ' + error.message, 'error');
                        
                        // Re-enable button
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }
                    })
                    .finally(() => {
                        // Reset flags
                        autoSubmitOrderAfterOtp = false;
                        pendingOrderFormData = null;
                    });
                } else {
                    console.error('No pending form data found!');
                    showMessageStep45('Error: Form data not available. Please go back and try again.', 'error');
                    
                    // Re-enable button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }
            }, 1500);
        } else {
            console.error('Invalid OTP code');
            showMessageStep45('<?= LanguageHelper::t('invalid_verification_code', 'Invalid verification code. Please try again.') ?>', 'error');
            
            // Restore button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Error verifying security code:', error);
        showMessageStep45('<?= LanguageHelper::t('error_verifying_code_try_again', 'Error verifying code. Please try again.') ?>', 'error');
        
        // Restore button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
        
        // Reset flags
        autoSubmitOrderAfterOtp = false;
        pendingOrderFormData = null;
    });
}

function cancelSecurityVerification() {
    console.log('❌ Cancelling security verification - Going back to Step 4');
    
    // Hide Step 4.5
    hideSecurityVerificationStep();
    
    // Show Step 4 (Payment)
    navigateToStep(4);
    
    // Clear stored form data
    pendingOrderFormData = null;
    autoSubmitOrderAfterOtp = false;
}

function resendSecurityCode() {
    console.log('🔄 Resending security verification code...');
    
    // Get phone from multiple sources (prioritized)
    let phone = sessionStorage.getItem('verified_phone') || sessionStorage.getItem('guest_phone_number');
    
    // If not in session, try form input
    if (!phone) {
        const phoneInput = document.getElementById('phone');
        if (phoneInput && phoneInput.value) {
            phone = phoneInput.value.trim();
        }
    }
    
    // If still not found, try alternate input names
    if (!phone) {
        const phoneInputAlt = document.querySelector('input[name="phone"], input[name="phone_number"], input[name="customer_phone"]');
        if (phoneInputAlt && phoneInputAlt.value) {
            phone = phoneInputAlt.value.trim();
        }
    }
    
    if (!phone) {
        return showMessageStep45('Phone number not available. Please refresh and try again.', 'error');
    }
    
    const resendBtn = document.getElementById('resendSecurityCodeBtn');
    const originalText = resendBtn?.textContent || 'Resend Code';
    if (resendBtn) {
        resendBtn.disabled = true;
    }
    
    fetch('<?=$base_url?>/otp/resend', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✓ Security code resent');
            showMessageStep45('<?= LanguageHelper::t('verification_code_resent', 'Verification code resent to your phone') ?>', 'success');
            
            // Start the 60-second cooldown timer for resend button
            startResendCooldownStep45(60);
        } else {
            const message = data.message || '<?= LanguageHelper::t('error_resending_code', 'Error resending verification code') ?>';
            showMessageStep45(message, 'error');
            
            if (resendBtn) {
                resendBtn.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error resending security code:', error);
        showMessageStep45('<?= LanguageHelper::t('error_resending_code_try_again', 'Error resending verification code. Please try again.') ?>', 'error');
        
        if (resendBtn) {
            resendBtn.disabled = false;
        }
    });
}

function showMessageStep45(message, type) {
    const messageArea = document.getElementById('security_verification_message');
    if (!messageArea) {
        console.error('Message area not found for Step 4.5');
        return;
    }
    
    messageArea.textContent = message;
    messageArea.style.display = 'block';
    messageArea.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
    messageArea.style.marginBottom = '15px';
    messageArea.style.padding = '12px 15px';
    messageArea.style.borderRadius = '6px';
    
    if (type === 'success') {
        messageArea.style.backgroundColor = '#d4edda';
        messageArea.style.color = '#155724';
        messageArea.style.borderLeft = '4px solid #28a745';
    } else {
        messageArea.style.backgroundColor = '#f8d7da';
        messageArea.style.color = '#721c24';
        messageArea.style.borderLeft = '4px solid #dc3545';
    }
    
    // Auto-clear error messages after 5 seconds
    if (type === 'error') {
        setTimeout(() => {
            messageArea.style.display = 'none';
        }, 5000);
    }
}

function startResendCooldownStep45(seconds) {
    const resendBtn = document.getElementById('resendSecurityCodeBtn');
    if (!resendBtn) return;
    
    resendBtn.disabled = true;
    let remaining = seconds;
    
    const timer = setInterval(() => {
        remaining--;
        resendBtn.textContent = `<?= LanguageHelper::t('resend_code_in', 'Resend code in') ?> ${remaining}s`;
        
        if (remaining <= 0) {
            clearInterval(timer);
            resendBtn.disabled = false;
            resendBtn.textContent = '<?= LanguageHelper::t('resend_code', 'Resend Code') ?>';
        }
    }, 1000);
}

function toggleDetailsForm() {
    const displayDiv = document.querySelector('.logged-in-details-display');
    const formDiv = document.getElementById('detailsEditForm');
    
    if (displayDiv && formDiv) {
        // Toggle visibility
        if (formDiv.style.display === 'none' || formDiv.style.display === '') {
            formDiv.style.display = 'block';
            if (displayDiv) displayDiv.style.display = 'none';
        } else {
            formDiv.style.display = 'none';
            if (displayDiv) displayDiv.style.display = 'block';
        }
    }
}

function navigateToStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
        step.style.display = 'none';
        step.classList.remove('active');
    });
    
    // Show target step
    const targetStep = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
    if (targetStep) {
        targetStep.style.display = 'block';
        setTimeout(() => {
            targetStep.classList.add('active');
        }, 10);
    }
    
    // Update progress steps
    document.querySelectorAll('.progress-step').forEach(step => {
        step.classList.remove('active');
        if (parseInt(step.dataset.step) <= stepNumber) {
            step.classList.add('active');
        }
    });
    
    // Handle summary display based on step and device
    const isMobile = isMobileDevice();
    
    // FIXED: Show verification warning on step 1 for all unverified logged-in users
    // Remove the problematic phoneField.readOnly check
    const verificationWarning = document.getElementById('verificationWarning');
    if (stepNumber === 1) {
        // Always show warning on step 1 if phone is not verified (for logged-in users)
        // The warning is already hidden by PHP if $show_verification_warning is false
        // We just need to make sure it's visible when it should be
        if (verificationWarning) {
            // Check if it was initially shown by PHP
            const initialDisplay = verificationWarning.style.display;
            if (initialDisplay === 'none') {
                // If PHP hid it, keep it hidden
                verificationWarning.style.display = 'none';
            } else {
                // If PHP showed it (based on $show_verification_warning), show it
                verificationWarning.style.display = 'flex';
            }
        }
    } else {
        // Hide on other steps
        if (verificationWarning) verificationWarning.style.display = 'none';
    }
    
    // Scroll to top of form for better mobile experience
    if (isMobile) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

/**
 * Start a 60-second cooldown timer for resend OTP button
 * @param {string} buttonId - ID of the resend button
 * @param {string} timerId - ID of the timer display container
 * @param {string} countdownId - ID of the countdown seconds span
 * @param {number} seconds - Total cooldown seconds (default 60)
 */
function startResendCooldown(buttonId, timerId, countdownId, seconds = 60) {
    const btn = document.getElementById(buttonId);
    const timerDiv = document.getElementById(timerId);
    const countdownSpan = document.getElementById(countdownId);
    
    if (!btn || !timerDiv || !countdownSpan) {
        console.error('Timer elements not found:', { buttonId, timerId, countdownId });
        return;
    }
    
    let remaining = seconds;
    
    // Show timer and disable button
    timerDiv.style.display = 'block';
    btn.disabled = true;
    
    // Update countdown display
    const countdownInterval = setInterval(() => {
        remaining--;
        countdownSpan.textContent = remaining;
        
        if (remaining <= 0) {
            clearInterval(countdownInterval);
            // Hide timer and enable button
            timerDiv.style.display = 'none';
            btn.disabled = false;
            countdownSpan.textContent = '60'; // Reset for next use
        }
    }, 1000);
}

function validateStep(stepNumber) {
    const currentStep = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
    const inputs = currentStep.querySelectorAll('input, select, textarea');
    let isValid = true;
    
    console.log(`DEBUG: Validating step ${stepNumber}, found ${inputs.length} inputs`);
    
    // Track which radio groups we've already validated
    const validatedRadioGroups = new Set();
    
    inputs.forEach(input => {
        // Skip validation for hidden inputs (for logged-in users with pre-filled address)
        if (input.offsetParent === null && input.type !== 'hidden') {
            console.log(`  Skipping hidden field: ${input.name}`);
            return; // Skip hidden fields
        }
        
        // CRITICAL FIX: Skip readonly fields - they are pre-filled for logged-in users
        if (input.hasAttribute('readonly')) {
            console.log(`  ✓ Skipping readonly field: ${input.name} (pre-filled for logged-in user)`);
            return;
        }
        
        // Handle radio button validation as a group (not individually)
        if (input.type === 'radio') {
            if (!validatedRadioGroups.has(input.name)) {
                validatedRadioGroups.add(input.name);
                const radioGroup = currentStep.querySelectorAll(`input[type="radio"][name="${input.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                
                if (!isChecked) {
                    isValid = false;
                    console.warn(`  ✗ Required radio group not selected: ${input.name}`);
                    radioGroup.forEach(radio => radio.classList.add('error'));
                    showMessage(`<?= LanguageHelper::t('please_select', 'Please select a') ?> ${input.name}`, 'error', 'addressUpdateMessage');
                } else {
                    console.log(`  ✓ Required radio group selected: ${input.name}`);
                    radioGroup.forEach(radio => radio.classList.remove('error'));
                }
            }
            return; // Don't process individual radio buttons below
        }
        
        // Handle checkbox validation separately
        if (input.type === 'checkbox') {
            if (input.hasAttribute('required') && !input.checked) {
                input.classList.add('error');
                isValid = false;
                console.warn(`  ✗ Required checkbox not checked: ${input.name || input.id}`);
                showMessage(`<?= LanguageHelper::t('please_check_checkbox', 'Please check the') ?> ${input.previousElementSibling?.textContent || 'required checkbox'}`, 'error', 'addressUpdateMessage');
            } else {
                input.classList.remove('error');
                if (input.hasAttribute('required')) {
                    console.log(`  ✓ Required checkbox checked: ${input.name || input.id}`);
                }
            }
        } else if (input.hasAttribute('required') && !input.value.trim()) {
            // For text inputs, selects, and textareas
            input.classList.add('error');
            isValid = false;
            console.warn(`  ✗ Required field empty: ${input.name || input.id}`);
            
            // Show error message for mobile users
            if (isMobileDevice()) {
                showMessage(`<?= LanguageHelper::t('please_fill_field', 'Please fill in the') ?> ${input.previousElementSibling?.textContent || 'required field'}`, 'error', 'addressUpdateMessage');
            }
        } else {
            input.classList.remove('error');
            if (input.hasAttribute('required')) {
                console.log(`  ✓ Required field filled: ${input.name || input.id} = "${input.value.substring(0, 20)}..."`);
            }
        }
        
        // Additional validation for phone
        if (input.id === 'phone' && input.value.trim() && !/^[0-9]{10}$/.test(input.value)) {
            input.classList.add('error');
            isValid = false;
            console.warn(`  ✗ Invalid phone format: ${input.value}`);
            showMessage('<?= LanguageHelper::t('enter_valid_10_digit_phone', 'Please enter a valid 10-digit phone number') ?>', 'error', 'addressUpdateMessage');
        }
    });
    
    // Additional validation for step 1 (city selection and minimum quantities)
    if (stepNumber === 1) {
        const citySelected = document.getElementById('city').value;
        if (citySelected) {
            const minQuantityCheck = checkMinimumQuantities();
            if (!minQuantityCheck) {
                isValid = false;
                console.warn(`  ✗ Step 1: Minimum quantity check failed`);
            }
        }
        
        // ✅ MANDATORY: Check if verification is REQUIRED (quantity >= 5 or bulk buyer)
        // Calculate total quantity
        let totalQty = 0;
        Object.keys(productData).forEach(productId => {
            totalQty += productData[productId].currentQuantity || 0;
        });
        
        const isVerificationRequired = (customerType === 'bulk' || totalQty >= 5);
        const isPhoneVerifiedValue = <?= $is_phone_verified ? 'true' : 'false' ?>;
        
        // If verification is required, phone MUST be verified
        if (isVerificationRequired && !isPhoneVerifiedValue && !isPhoneVerified) {
            isValid = false;
            console.warn(`  ✗ Step ${stepNumber}: Phone verification is REQUIRED for bulk orders or quantity >= 5`);
            showMessage(
                '<?= LanguageHelper::t('phone_verification_required_for_order', 'Phone verification is required for orders with 5 or more items or bulk purchases. Please verify your phone number to proceed.') ?>',
                'error',
                'addressUpdateMessage'
            );
            
            // Scroll to verification form
            const verificationForm = document.getElementById('verificationFormContainer');
            if (verificationForm) {
                verificationForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

    }
    
    // Additional validation for step 4 (payment - MANDATORY phone verification if quantity >= 5)
    if (stepNumber === 4) {
        // Calculate total quantity
        let totalQty = 0;
        Object.keys(productData).forEach(productId => {
            totalQty += productData[productId].currentQuantity || 0;
        });
        
        const isVerificationRequired = (customerType === 'bulk' || totalQty >= 5);
        const isPhoneVerifiedValue = <?= $is_phone_verified ? 'true' : 'false' ?>;
        
        // ✅ NEW: Check TWO separate verifications for guest users
        const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
        if (!isLoggedIn && isVerificationRequired) {
            const formPhone = getPhoneFromForm();
            const verifiedPhoneSession = sessionStorage.getItem('verified_phone');
            const securityVerified = sessionStorage.getItem('security_verification_completed') === 'true';
            const quantityVerified = sessionStorage.getItem('quantity_verification_completed') === 'true';
            
            console.log(`Step 4 Verification Check:`);
            console.log(`  Form Phone: ${formPhone}`);
            console.log(`  Session Phone: ${verifiedPhoneSession}`);
            console.log(`  Security Verification: ${securityVerified}`);
            console.log(`  Quantity Verification: ${quantityVerified}`);
            console.log(`  isPhoneVerified: ${isPhoneVerified}`);
            
            // Check 1: No verified phone at all
            if (!verifiedPhoneSession || !isPhoneVerified) {
                isValid = false;
                console.warn(`  ✗ Step 4: No verified phone found`);
                
                // Only ask for QUANTITY verification (for bulk orders)
                quantityVerificationCompleted = false;
                showMessage(
                    '<?= LanguageHelper::t('phone_verification_required_for_order', 'Phone verification is required for orders with 5 or more items or bulk purchases. Please verify your phone number to proceed.') ?>',
                    'error',
                    'addressUpdateMessage'
                );
                
                showVerificationFormStep4();
                return false;
            }
            
            // Check 2: Phone in form doesn't match verified phone - need to verify new phone
            if (formPhone && formPhone !== verifiedPhoneSession) {
                isValid = false;
                console.warn(`  ✗ Step 4: Phone mismatch - Form: ${formPhone}, Session: ${verifiedPhoneSession}`);
                
                // Reset both verifications since user changed phone
                securityVerificationCompleted = false;
                quantityVerificationCompleted = false;
                sessionStorage.removeItem('security_verification_completed');
                sessionStorage.removeItem('quantity_verification_completed');
                
                showMessage(
                    '<?= LanguageHelper::t('phone_mismatch_verification_required', 'The phone number you entered does not match your verified phone number. Please verify this phone number to proceed.') ?>',
                    'error',
                    'addressUpdateMessage'
                );
                
                showVerificationFormStep4();
                return false;
            }
            
            // Check 3: For existing registered phones, MUST have SECURITY verification
            // Check if phone exists in database by seeing if it has security verification completed
            if (securityVerificationCompleted) {
                console.log(`  ✓ SECURITY verification already completed`);
                // Phone already verified for security - existing account confirmed
                // User can now place order
            } else if (quantityVerified) {
                console.log(`  ✓ QUANTITY verification already completed`);
                // Quantity verification done - can proceed
            } else {
                console.log(`  ℹ No verification status yet - may need Step 4 verification`);
            }
            
            console.log(`Step 4 validation: isValid = ${isValid}`);
        }
        // For logged-in users, use original check
        else if (isPhoneVerifiedValue === false && isVerificationRequired && !isPhoneVerified) {
            isValid = false;
            console.warn(`  ✗ Step 4: Phone verification is REQUIRED for bulk orders or quantity >= 5`);
            
            showMessage(
                '<?= LanguageHelper::t('phone_verification_required_for_order', 'Phone verification is required for orders with 5 or more items or bulk purchases. Please verify your phone number to proceed.') ?>',
                'error',
                'addressUpdateMessage'
            );
            
            // Scroll to verification warning
            const warningStep4 = document.getElementById('verificationWarningStep4');
            if (warningStep4) {
                warningStep4.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
    
    console.log(`DEBUG: Step ${stepNumber} validation result: ${isValid ? '✓ VALID' : '✗ INVALID'}`);
    return isValid;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    
    console.log("=== CHECKOUT PAGE INITIALIZED ===");
    console.log("Checkout form found:", checkoutForm ? 'YES ✓' : 'NO ✗');
    
    if (!checkoutForm) {
        console.error("❌ CRITICAL: Checkout form not found! ID='checkoutForm'");
        console.error("Available forms:", document.querySelectorAll('form').length);
        return;
    }
    
    console.log("✓ Event listeners will be attached");
    
    // Load saved form data on page load
    loadFormData();
    
    // ✅ NEW: Check if phone is already verified in session for guest users
    const verifiedPhoneFromSession = sessionStorage.getItem('verified_phone');
    if (verifiedPhoneFromSession && !<?= $is_logged_in ? 'true' : 'false' ?>) {
        console.log('Guest user has verified phone in session:', verifiedPhoneFromSession);
        isPhoneVerified = true;
        // Store in a variable for later use
        window.guestVerifiedPhone = verifiedPhoneFromSession;
    }
    
    // Initialize the city display from form field
    updateCityDisplayFromForm();
    
    // Initialize the page
    navigateToStep(1);
    updateOffersDisplay();
    updateDeliveryMessage();
    
    // Auto-fill street field on initial load
    autoFillStreet();
    
    // Save form data on input changes
    checkoutForm.addEventListener('input', function() {
        saveFormData();
    });
    
    checkoutForm.addEventListener('change', function() {
        saveFormData();
    });
    
    // Next button handlers
    document.querySelectorAll('.checkout-btn-next').forEach(button => {
        button.addEventListener('click', function() {
            const currentStep = parseInt(this.closest('.form-step').dataset.step);
            const nextStep = parseInt(this.dataset.next);
            if (validateStep(currentStep)) {
                navigateToStep(nextStep);
            }
        });
    });
    
    // Previous button handlers
    document.querySelectorAll('.checkout-btn-prev').forEach(button => {
        button.addEventListener('click', function() {
            navigateToStep(parseInt(this.dataset.prev));
        });
    });
    
    // Payment method change handler
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', handlePaymentMethodChange);
    });
    
    // WhatsApp order button handler
    const whatsappBtn = document.getElementById('whatsappOrderBtn');
    console.log("WhatsApp button found:", whatsappBtn ? 'YES ✓' : 'NO ✗');
    
    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', placeOrderViaWhatsApp);
        console.log("✓ WhatsApp button listener attached");
    } else {
        console.error("❌ WhatsApp button NOT found!");
    }
    
    // Verification handlers
    document.getElementById('verifyAccountBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        showVerificationForm();
    });
    
    document.getElementById('cancelVerificationBtn')?.addEventListener('click', hideVerificationForm);
    document.getElementById('submitVerificationBtn')?.addEventListener('click', verifyCode);
    document.getElementById('resendCodeBtn')?.addEventListener('click', resendCode);
    
    // Guest Phone Form Handlers - For non-logged-in users needing verification
    document.getElementById('submitPhoneNumberBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const phoneInput = document.getElementById('guest_phone_number');
        const phone = phoneInput.value.trim();
        
        // Validate phone format
        if (!/^[0-9]{10}$/.test(phone)) {
            showMessage(
                '<?= LanguageHelper::t('invalid_phone_format', 'Invalid phone number format. Phone must be 10 digits starting with 9.') ?>',
                'error',
                'verificationMessage'
            );
            return;
        }
        
        // Validate Nepal format (must start with 9)
        if (!phone.startsWith('9')) {
            showMessage(
                '<?= LanguageHelper::t('nepal_phone_format', 'Phone number must start with 9 (Nepal format).') ?>',
                'error',
                'verificationMessage'
            );
            return;
        }
        
        // ✅ NEW: Check if this phone is already verified in session (from previous verification)
        const verifiedPhoneFromSession = sessionStorage.getItem('verified_phone');
        if (verifiedPhoneFromSession === phone) {
            console.log('Phone already verified in session:', phone);
            // Hide guest phone form
            document.getElementById('guestPhoneFormContainer').style.display = 'none';
            
            // Show success message - phone is already verified
            showMessage(
                '✓ <?= LanguageHelper::t('phone_currently_verified', 'Your phone number is currently verified!') ?>',
                'success',
                'verificationMessage'
            );
            
            // Mark as verified
            isPhoneVerified = true;
            verificationJustCompleted = true;
            
            // Auto-close and proceed
            setTimeout(() => {
                document.getElementById('verificationMessage').style.display = 'none';
            }, 2000);
            return;
        }
        
        // Show loading state
        const submitBtn = this;
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> <?= LanguageHelper::t('checking_phone', 'Checking phone...') ?>';
        
        // Check if phone is already verified
        checkPhoneVerificationStatus(phone).then(result => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (result.verified && result.exists) {
                // ✅ CASE 1: Phone exists AND is verified - skip OTP
                console.log('Phone is already verified:', phone);
                
                // Store verified phone in sessionStorage
                sessionStorage.setItem('verified_phone', phone);
                sessionStorage.setItem('phone_verified_at', new Date().toISOString());
                
                // Hide guest phone form
                document.getElementById('guestPhoneFormContainer').style.display = 'none';
                
                // Show success message
                showMessage(
                    '✓ <?= LanguageHelper::t('phone_verified_success', 'Phone number verified successfully!') ?>',
                    'success',
                    'verificationMessage'
                );
                
                // Mark phone as verified and proceed
                isPhoneVerified = true;
                verificationJustCompleted = true;
                
                // Auto-close message and proceed
                setTimeout(() => {
                    document.getElementById('verificationMessage').style.display = 'none';
                }, 2000);
            } else if (result.exists) {
                // ⚠️ SECURITY: Phone exists in database - silently require OTP verification
                // Don't reveal phone is already registered (security/privacy reason)
                // Just show OTP form without messages about existing account
                console.log('SECURITY: Phone exists - silently triggering OTP verification');
                
                // Hide guest phone form silently (no message shown)
                document.getElementById('guestPhoneFormContainer').style.display = 'none';
                
                // Silently send OTP without revealing phone exists in database
                sendVerificationCode(phone);
            } else if (!result.exists) {
                // ✅ CASE: Phone is new (doesn't exist in database) - proceed with normal OTP for new registration
                console.log('Phone is new, sending OTP for guest registration');
                
                // Hide guest phone form and show verification form
                document.getElementById('guestPhoneFormContainer').style.display = 'none';
                
                // Send OTP with the guest phone number
                sendVerificationCode(phone);
            }
        });
    });
    
    // Auto-submit guest phone form on mobile when 10 digits are entered
    document.getElementById('guest_phone_number')?.addEventListener('input', function(e) {
        const phone = this.value.replace(/[^0-9]/g, '');
        this.value = phone;
        
        // On mobile, auto-submit when 10 digits are entered
        if (phone.length === 10 && isMobileDevice()) {
            console.log('Auto-submitting guest phone form on mobile');
            
            // Validate Nepal format (must start with 9)
            if (phone.startsWith('9')) {
                // Auto-trigger submit button
                document.getElementById('submitPhoneNumberBtn')?.click();
            }
        }
    });
    
    // Auto-submit guest phone form on mobile (allow Enter key)
    document.getElementById('guest_phone_number')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('submitPhoneNumberBtn')?.click();
        }
    });
    
    document.getElementById('cancelPhoneNumberBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        const guestPhoneForm = document.getElementById('guestPhoneFormContainer');
        if (guestPhoneForm) {
            guestPhoneForm.style.display = 'none';
        }
        document.getElementById('guest_phone_number').value = '';
    });
    
    // Step 4 Verification Handlers
    document.getElementById('verifyAccountBtnStep4')?.addEventListener('click', function(e) {
        e.preventDefault();
        showVerificationFormStep4();
    });
    
    document.getElementById('cancelVerificationBtnStep4')?.addEventListener('click', hideVerificationFormStep4);
    document.getElementById('submitVerificationBtnStep4')?.addEventListener('click', verifyCodeStep4);
    document.getElementById('resendCodeBtnStep4')?.addEventListener('click', resendCodeStep4);
    
    // ✅ NEW: Step 4.5 Security Verification Handlers (for existing registered phones)
    document.getElementById('confirmSecurityVerificationBtn')?.addEventListener('click', verifySecurityCodeStep45);
    document.getElementById('cancelSecurityVerificationBtn')?.addEventListener('click', cancelSecurityVerification);
    document.getElementById('resendSecurityCodeBtn')?.addEventListener('click', resendSecurityCode);
    
    // Auto-submit security verification OTP when 6 digits are entered (Step 4.5)
    const securityOtpInput = document.getElementById('security_verification_code');
    if (securityOtpInput) {
        securityOtpInput.addEventListener('input', function() {
            // Remove any non-digit characters
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = value;
            
            // Auto-submit when 6 digits are entered
            if (value.length === 6) {
                // Small delay to ensure input is fully processed
                setTimeout(() => {
                    verifySecurityCodeStep45();
                }, 100);
            }
        });
        
        // Allow only digits and max 6 characters
        securityOtpInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-submit OTP verification form when 6 digits are entered (Step 4)
    const otpInputStep4 = document.getElementById('verification_code_step4');
    if (otpInputStep4) {
        otpInputStep4.addEventListener('input', function() {
            // Remove any non-digit characters
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = value;
            
            // Auto-submit when 6 digits are entered
            if (value.length === 6) {
                // Small delay to ensure input is fully processed
                setTimeout(() => {
                    verifyCodeStep4();
                }, 100);
            }
        });
        
        // Allow only digits and max 6 characters
        otpInputStep4.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-submit OTP verification form when 6 digits are entered
    const otpInput = document.getElementById('verification_code');
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            // Remove any non-digit characters
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = value;
            
            // Auto-submit when 6 digits are entered
            if (value.length === 6) {
                // Small delay to ensure input is fully processed
                setTimeout(() => {
                    verifyCode();
                }, 100);
            }
        });
        
        // Allow only digits and max 6 characters
        otpInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Payment screenshot preview
    document.getElementById('payment_screenshot').addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('preview');
        const screenshotPreview = document.getElementById('screenshotPreview');
        
        if (file) {
            // Check file size (2MB limit)
            if (file.size > 2 * 1024 * 1024) {
                showMessage('<?= LanguageHelper::t('file_too_large', 'File size must be less than 2MB') ?>', 'error', 'verificationMessage');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.addEventListener('load', function() {
                preview.src = reader.result;
                screenshotPreview.style.display = 'block';
            });
            reader.readAsDataURL(file);
        } else {
            screenshotPreview.style.display = 'none';
        }
        saveFormData();
    });
    
    // Check minimum quantities on initial load if city is selected
    const initialCity = document.getElementById('city').value;
    if (initialCity) {
        setTimeout(() => {
            checkMinimumQuantities();
        }, 100);
    }
    
    // Form submission
    checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('.checkout-place-order');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '<?= LanguageHelper::t('processing', 'Processing...') ?>';
        
        console.log("=== FORM SUBMISSION EVENT FIRED ===");
        console.log("Submit button found:", submitBtn ? 'YES ✓' : 'NO ✗');
        console.log("Current step validation:");
        
        // FIX: Ensure readonly fields (for logged-in users) have their values
        const readonlyFields = this.querySelectorAll('input[readonly]');
        readonlyFields.forEach(field => {
            if (field.value) {
                console.log(`✓ Readonly field ${field.name} = ${field.value}`);
            }
        });
        
        if (!validateStep(4)) {
            console.error("❌ Step 4 validation FAILED");
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            // Show a helpful error message if validation silently failed
            const step4 = document.querySelector('.form-step[data-step="4"]');
            const errorInputs = step4.querySelectorAll('input.error, select.error, textarea.error');
            
            console.warn("Error inputs found:", errorInputs.length);
            errorInputs.forEach(input => {
                console.warn(`  - ${input.name || input.id}: ${input.type}`);
            });
            
            if (errorInputs.length === 0) {
                // No specific errors marked - might be an issue with date or payment method
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                if (!paymentMethod) {
                    console.error("Payment method not selected");
                    showMessage('<?= LanguageHelper::t('please_select_payment_method', 'Please select a payment method') ?>', 'error', 'addressUpdateMessage');
                } else {
                    console.error("Some fields incomplete in step 4");
                    showMessage('<?= LanguageHelper::t('please_complete_all_fields', 'Please complete all required fields in this step') ?>', 'error', 'addressUpdateMessage');
                }
            }
            return;
        }
        
        console.log("✓ Step 4 validation passed");
        
        // ADDED: Check if phone verification is required but not completed
        <?php if ($is_verification_required): ?>
        if (!isPhoneVerified) {
            console.error("❌ Phone verification is REQUIRED but not completed");
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            let verificationMessage = '<?php 
                if ($customer_type === 'bulk') {
                    echo 'Since you are a bulk buyer, phone verification is required to proceed. Please verify your phone number before placing your order.';
                } else {
                    echo 'Since your order quantity is 5 or more items, phone verification is required to proceed. Please verify your phone number before placing your order.';
                }
            ?>';
            
            showMessage(verificationMessage, 'error', 'addressUpdateMessage');
            
            // Scroll to verification warning
            const verificationWarning = document.getElementById('verificationWarning');
            if (verificationWarning) {
                verificationWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        <?php endif; ?>
        
        // Check minimum quantities
        const citySelect = document.getElementById('city');
        const selectedCity = citySelect.value;
        const cityId = cityIds[selectedCity];
        let hasMinimumQuantityIssue = false;
        
        Object.keys(productData).forEach(productId => {
            const quantity = getCurrentQuantity(parseInt(productId));
            const minQuantityInfo = minimumQuantities[productId]?.[cityId];
            if (minQuantityInfo && quantity < minQuantityInfo.minimum_quantity) {
                hasMinimumQuantityIssue = true;
                showMessage(`<?= LanguageHelper::t('minimum_quantity_not_met_for', 'Minimum quantity not met for') ?> ${minQuantityInfo.product_name}. <?= LanguageHelper::t('required', 'Required') ?>: ${minQuantityInfo.minimum_quantity} ${minQuantityInfo.unit}`, 'error', 'minimumQuantityMessage');
            }
        });
        
        if (hasMinimumQuantityIssue) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }
        
        const formData = new FormData(this);
        
        // Set order type to website for regular form submission
        formData.append('order_type', 'website');
        
        // DEBUG: Log all form data
        console.log("=== FORM DATA BEING SENT ===");
        for (let [key, value] of formData.entries()) {
            if (!key.startsWith('items')) {
                console.log(`  ${key}: ${value}`);
            }
        }
        console.log("Sending POST request to /checkout/process");
        
        fetch('<?=$base_url?>/checkout/process', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log("Response received:", response.status, response.statusText);
            if (!response.ok) {
                console.error("HTTP Error:", response.status);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("JSON parsed successfully:", data);
            
            if (data.success) {
                console.log("✓ Order created successfully");
                document.getElementById('order-confirmation-number').textContent = data.order_number;
                navigateToStep(5);
                // Clear saved form data after successful order
                clearFormData();
            } else {
                console.error("❌ Server returned error:", data.message);
                console.error("Full response:", data);
                
                // ⚠️ SECURITY BLOCK: Phone is already registered - silently require OTP (don't reveal)
                if (data.security_block && data.existing_account) {
                    console.log('SECURITY: Existing phone detected - showing Step 4.5 Security Verification');
                    
                    // ✅ NEW: Store pending form data for auto-submission after OTP verification
                    pendingOrderFormData = formData;
                    autoSubmitOrderAfterOtp = true;
                    console.log('✓ Form data stored for auto-submission after OTP verification');
                    
                    // ✅ CHANGED: Show new Step 4.5 instead of inline verification form
                    // Don't reveal that phone is already registered (security/privacy)
                    showSecurityVerificationStep();
                } else if (data.needs_verification) {
                    showMessage(data.message, 'error', 'verificationMessage');
                    showVerificationForm();
                } else {
                    const errorMsg = data.message || 'Order submission failed. Please try again or contact support.';
                    showMessage(errorMsg, 'error', 'addressUpdateMessage');
                    console.error("Error details:", errorMsg);
                }
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error("❌ FETCH ERROR:", error);
            console.error("Error message:", error.message);
            showMessage(`Network error: ${error.message}. Please check your connection and try again.`, 'error', 'addressUpdateMessage');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    
    // Remove error class on input
    checkoutForm.querySelectorAll('input, textarea, select').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
    
    // Close QR modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('qrModal')) {
            closeQrModal();
        }
    });
    
    // Handle mobile viewport height
    function setViewportHeight() {
        let vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
});
</script>
<style>
/* Advanced UI/UX Styles - Mobile First Approach */
 /* Checkout Page Specific CSS - Isolated from Header/Nav Styles */
.checkout-page * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.checkout-page {
    background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
    min-height: 100vh;
    padding: 0;
}

.checkout-page .container {
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem;
}

/* Progress Indicator - Mobile Optimized */
.checkout-page .checkout-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2rem;
    position: relative;
    background: #FFFFFF;
    padding: 1.25rem;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    gap: 0.5rem;
}

.checkout-page .checkout-progress::before {
    content: '';
    position: absolute;
    top: 2.5rem;
    left: 10%;
    right: 10%;
    height: 3px;
    background: #E5E7EB;
    z-index: 1;
}

.checkout-page .progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 1;
    min-width: 0;
}

.checkout-page .step-number {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: white;
    border: 3px solid #FFFFFF;
    font-size: 0.875rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .progress-step.active .step-number {
    background: #10B981;
    transform: scale(1.1);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.checkout-page .progress-step.completed .step-number {
    background: #10B981;
}

.checkout-page .step-label {
    font-size: 0.75rem;
    text-align: center;
    color: #6B7280;
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    line-height: 1.2;
}

.checkout-page .progress-step.active .step-label {
    color: #10B981;
    font-weight: 600;
}

.checkout-page .checkout-title {
    text-align: center;
    margin-bottom: 1.5rem;
    color: #1F2937;
    font-size: 1.75rem;
    font-weight: 700;
    background: linear-gradient(135deg, #10B981, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.checkout-page .checkout-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-width: 100%;
    margin: 0 auto;
}

.checkout-page .checkout-form {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-page .order-summary-card {
    background: #F9FAFB;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid #E5E7EB;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-page .form-step {
    display: none;
    animation: checkoutFadeIn 0.4s ease-out;
}

.checkout-page .form-step.active {
    display: block;
}

@keyframes checkoutFadeIn {
    from { 
        opacity: 0; 
        transform: translateY(10px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

.checkout-page .step-title {
    margin-bottom: 1.5rem;
    color: #1F2937;
    border-bottom: 2px solid #10B981;
    padding-bottom: 0.75rem;
    font-size: 1.375rem;
    font-weight: 600;
    position: relative;
}

.checkout-page .step-title::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 3rem;
    height: 2px;
    background: #F59E0B;
}

/* Form Elements - Mobile Optimized */
.checkout-page .form-group {
    margin-bottom: 1.25rem;
    position: relative;
}

.checkout-page .form-row {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.checkout-page .form-input {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #FFFFFF;
    font-family: inherit;
}

.checkout-page .form-input:focus {
    outline: none;
    border-color: #10B981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    transform: translateY(-1px);
}

.checkout-page .form-input.error {
    border-color: #EF4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.checkout-page .input-with-status {
    position: relative;
}

.checkout-page .verification-status {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 1rem;
    font-weight: 600;
}

.checkout-page .verification-status.verified {
    background: #10B981;
    color: white;
}

.checkout-page .verification-status.not-verified {
    background: #F59E0B;
    color: white;
}

/* Button Styles - Enhanced for Mobile */
.checkout-page .step-actions {
    display: flex;
    flex-direction: row;
    gap: 0.75rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #E5E7EB;
    justify-content: space-between;
}

.checkout-page .checkout-btn-primary {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: none;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    width: auto;
    flex: 1;
    position: relative;
    overflow: hidden;
}

.checkout-page .checkout-btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.checkout-page .checkout-btn-primary:hover::before {
    left: 100%;
}

.checkout-page .checkout-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.checkout-page .checkout-btn-secondary {
    background: #6B7280;
    color: white;
    border: none;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    width: auto;
    flex: 1;
}

.checkout-page .checkout-btn-secondary:hover {
    background: #4B5563;
    transform: translateY(-1px);
}

.checkout-page .checkout-btn-next, 
.checkout-page .checkout-btn-prev {
    position: relative;
}

.checkout-page .checkout-btn-next::after {
    content: '→';
    margin-left: 0.5rem;
    font-weight: bold;
}

.checkout-page .checkout-btn-prev::before {
    content: '←';
    margin-right: 0.5rem;
    font-weight: bold;
}

.checkout-page .checkout-btn-link {
    background: none;
    border: none;
    color: #10B981;
    text-decoration: underline;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    width: 100%;
    text-align: center;
    margin-top: 0.5rem;
}

.checkout-page .checkout-btn-link:hover {
    color: #059669;
    background: rgba(16, 185, 129, 0.1);
}

.checkout-page .checkout-btn-download {
    background: none;
    border: none;
    color: #3B82F6;
    text-decoration: underline;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}

.checkout-page .checkout-btn-download:hover {
    color: #2563EB;
    background: rgba(59, 130, 246, 0.1);
}

.checkout-page .checkout-quantity-btn {
    width: 2.25rem;
    height: 2.25rem;
    border: 2px solid #10B981;
    background: #FFFFFF;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #10B981;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}

.checkout-page .checkout-quantity-btn:hover {
    background: #10B981;
    color: white;
    transform: scale(1.05);
}

.checkout-page .checkout-place-order {
    background: linear-gradient(135deg, #F59E0B, #EAB308);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    font-size: 1.125rem;
    padding: 1.125rem 1.5rem;
}

.checkout-page .checkout-place-order:hover {
    background: linear-gradient(135deg, #EAB308, #CA8A04);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
}

/* Order Items - Mobile Optimized */
.checkout-page .order-items {
    margin: 1.5rem 0;
}

.checkout-page .order-item {
    display: flex;
    flex-direction: row;
    gap: 1rem;
    padding: 1.25rem;
    border-bottom: 1px solid #E5E7EB;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    margin-bottom: 1rem;
    background: #FFFFFF;
    position: relative;
    align-items: flex-start;
}

.checkout-page .order-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.checkout-page .item-image {
    width: 100px;
    height: 100px;
    min-width: 100px;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-page .offer-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background: linear-gradient(135deg, #F59E0B, #DC2626);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
}

.checkout-page .item-details {
    flex: 1;
    text-align: left;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.checkout-page .item-name {
    margin: 0 0 0.75rem 0;
    color: #1F2937;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.4;
}

.checkout-page .price-info {
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: center;
}

.checkout-page .original-price {
    text-decoration: line-through;
    color: #6B7280;
    font-size: 0.875rem;
}

.checkout-page .final-price {
    font-weight: 600;
    color: #1F2937;
    font-size: 1.125rem;
}

.checkout-page .bulk-badge, 
.checkout-page .offer-badge-text {
    display: inline-block;
    background: linear-gradient(135deg, #FEF3C7, #FCD34D);
    color: #92400E;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.25rem;
}

.checkout-page .offer-badge-text {
    background: linear-gradient(135deg, #DDD6FE, #8B5CF6);
    color: white;
}

.checkout-page .quantity-section {
    margin: 1rem 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: center;
}

.checkout-page .current-quantity-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.checkout-page .quantity-label {
    font-weight: 600;
    color: #6B7280;
    font-size: 0.875rem;
}

.checkout-page .quantity-value {
    color: #1F2937;
    font-weight: 600;
    font-size: 1rem;
}

.checkout-page .free-quantity-info {
    color: #10B981;
    font-weight: 600;
    background: rgba(16, 185, 129, 0.1);
    padding: 0.125rem 0.5rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
}

.checkout-page .quantity-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #FFFFFF;
    padding: 0.5rem;
    border-radius: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    justify-content: center;
}

.checkout-page .quantity-input {
    width: 4rem;
    text-align: center;
    padding: 0.5rem;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    background: #FFFFFF;
}

.checkout-page .quantity-input:focus {
    outline: none;
    border-color: #10B981;
}

.checkout-page .savings-info, 
.checkout-page .offer-info, 
.checkout-page .minimum-quantity-info {
    margin-top: 0.5rem;
    text-align: center;
}

.checkout-page .text-success {
    color: #10B981;
    font-weight: 600;
}

.checkout-page .text-warning {
    color: #F59E0B;
    font-weight: 600;
}

.checkout-page .text-muted {
    color: #6B7280;
}

.checkout-page .item-total {
    font-weight: 700;
    color: #1F2937;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #E5E7EB;
}

.checkout-page .summary-totals {
    border-top: 2px solid #E5E7EB;
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    background: #FFFFFF;
    padding: 1.25rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    padding: 0.5rem 0;
    font-size: 1rem;
}

.checkout-page .summary-row.total {
    font-weight: 700;
    font-size: 1.375rem;
    border-top: 2px solid #E5E7EB;
    padding-top: 1rem;
    margin-top: 1rem;
    color: #10B981;
}

.checkout-page .free-delivery {
    color: #10B981;
    font-weight: 700;
    background: rgba(16, 185, 129, 0.1);
    padding: 0.375rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
}

/* Offers Section */
.checkout-page .offers-section {
    background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
    border: 2px solid #F59E0B;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.checkout-page .offers-section::before {
    content: '🎉';
    position: absolute;
    top: -0.5rem;
    right: -0.5rem;
    font-size: 3rem;
    opacity: 0.1;
    transform: rotate(15deg);
}

.checkout-page .offers-title {
    margin: 0 0 1rem 0;
    color: #92400E;
    font-size: 1.125rem;
    font-weight: 700;
    text-align: center;
}

.checkout-page .offer-item {
    padding: 0.875rem;
    margin-bottom: 0.5rem;
    background: #FFFFFF;
    border-radius: 8px;
    border-left: 4px solid #10B981;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.875rem;
}

.checkout-page .offer-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.checkout-page .event-offer {
    border-left-color: #F59E0B;
}

.checkout-page .any-quantity {
    color: #10B981;
    font-weight: 600;
}

.checkout-page .btn-show-offers, 
.checkout-page .btn-show-delivery {
    width: 100%;
    margin-top: 1rem;
}

/* Delivery Info */
.checkout-page .delivery-info-section {
    margin: 1.25rem 0;
}

.checkout-page .free-delivery-banner, 
.checkout-page .standard-delivery-message, 
.checkout-page .select-city-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
    border: 2px solid #10B981;
    font-weight: 600;
    text-align: center;
}

.checkout-page .standard-delivery-message {
    background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
    border-color: #3B82F6;
}

.checkout-page .select-city-message {
    background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
    border-color: #F59E0B;
}

.checkout-page .delivery-icon {
    font-size: 1.5rem;
}

.checkout-page .delivery-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

/* Payment Options */
.checkout-page .payment-options {
    margin: 1.5rem 0;
}

.checkout-page .payment-option {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    margin-bottom: 0.75rem;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #FFFFFF;
}

.checkout-page .payment-option:hover {
    border-color: #10B981;
    transform: translateY(-2px);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .payment-option input[type="radio"]:checked + .payment-label {
    color: #10B981;
    font-weight: 700;
}

.checkout-page .payment-option input[type="radio"]:checked ~ .payment-option {
    border-color: #10B981;
    background: rgba(16, 185, 129, 0.05);
}

.checkout-page .payment-label {
    margin-left: 1rem;
    font-size: 1rem;
    font-weight: 600;
}

/* QR Code Container */
.checkout-page .qr-code-container {
    margin: 1.5rem 0;
    padding: 1.5rem;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    background: #FFFFFF;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.checkout-page .qr-image-wrapper {
    text-align: center;
    margin: 1.25rem 0;
}

.checkout-page .qr-image {
    max-width: 200px;
    cursor: pointer;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .qr-image:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.checkout-page .qr-instruction {
    text-align: center;
    color: #6B7280;
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

/* Verification Styles */
.checkout-page .verification-warning {
    background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
    border: 2px solid #F59E0B;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.checkout-page .warning-icon {
    font-size: 1.5rem;
    text-align: center;
}

.checkout-page .warning-content {
    text-align: center;
}

.checkout-page .warning-content h3 {
    margin: 0 0 0.5rem 0;
    color: #92400E;
    font-size: 1.125rem;
}

.checkout-page .warning-content p {
    margin: 0;
    color: #92400E;
    line-height: 1.5;
    font-size: 0.875rem;
}

.checkout-page .verify-link {
    color: #10B981;
    text-decoration: underline;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkout-page .verify-link:hover {
    color: #059669;
}

.checkout-page .verification-form-container {
    background: #FFFFFF;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.checkout-page .verification-form h3 {
    margin: 0 0 0.75rem 0;
    color: #1F2937;
    font-size: 1.25rem;
}

.checkout-page .verification-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: center;
    margin-top: 1.25rem;
}

.checkout-page .alert-message {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: none;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.checkout-page .alert-message.success {
    background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
    border: 2px solid #10B981;
    color: #065F46;
}

.checkout-page .alert-message.error {
    background: linear-gradient(135deg, #FEF2F2, #FECACA);
    border: 2px solid #EF4444;
    color: #991B1B;
}

/* Modal Styles */
.checkout-page .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.checkout-page .modal-content {
    background: #FFFFFF;
    padding: 1.5rem;
    border-radius: 16px;
    max-width: 400px;
    width: 100%;
    position: relative;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    animation: checkoutModalAppear 0.3s ease-out;
    text-align: center;
}

@keyframes checkoutModalAppear {
    from { 
        opacity: 0; 
        transform: scale(0.9); 
    }
    to { 
        opacity: 1; 
        transform: scale(1); 
    }
}

.checkout-page .close {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6B7280;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.checkout-page .close:hover {
    color: #1F2937;
    background: #E5E7EB;
}

.checkout-page .modal-actions {
    display: flex;
    flex-direction: row;
    gap: 0.75rem;
    margin-top: 1.25rem;
    justify-content: center;
}

/* Confirmation Step */
.checkout-page .confirmation-step {
    text-align: center;
    padding: 2rem 1rem;
}

.checkout-page .confirmation-container {
    max-width: 100%;
    margin: 0 auto;
    background: #FFFFFF;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.checkout-page .confirmation-icon {
    font-size: 4rem;
    color: #10B981;
    margin-bottom: 1.5rem;
    animation: checkoutBounce 1s ease;
}

@keyframes checkoutBounce {
    0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
    40% {transform: translateY(-10px);}
    60% {transform: translateY(-5px);}
}

.checkout-page .savings-message {
    background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
    padding: 1.25rem;
    border-radius: 12px;
    margin: 1.25rem 0;
    border: 2px solid #10B981;
    font-size: 0.875rem;
}

.checkout-page .confirmation-actions {
    display: flex;
    flex-direction: row;
    gap: 0.75rem;
    margin-top: 2rem;
    justify-content: center;
}

/* Tablet and Desktop Styles */
@media (min-width: 768px) {
    .checkout-page .container {
        max-width: 1200px;
        padding: 2rem;
    }

    .checkout-page .checkout-progress::before {
        left: 15%;
        right: 15%;
    }

    .checkout-page .step-label {
        font-size: 0.875rem;
    }

    .checkout-page .checkout-title {
        font-size: 2.25rem;
        margin-bottom: 2rem;
    }

    .checkout-page .checkout-form {
        padding: 2rem;
    }

    .checkout-page .order-summary-card {
        padding: 1.5rem;
    }

    .checkout-page .form-row {
        flex-direction: row;
        gap: 1.5rem;
    }

    .checkout-page .form-row .form-group {
        flex: 1;
    }

    .checkout-page .step-actions {
        flex-direction: row;
        justify-content: space-between;
    }

    .checkout-page .checkout-btn-primary,
    .checkout-page .checkout-btn-secondary {
        width: auto;
        min-width: 180px;
        flex: 0 1 auto;
    }

    .checkout-page .order-item {
        flex-direction: row;
        text-align: left;
        gap: 1.5rem;
    }

    .checkout-page .item-image {
        align-self: flex-start;
        max-width: 100px;
    }

    .checkout-page .item-details {
        text-align: left;
    }

    .checkout-page .quantity-section {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .checkout-page .current-quantity-display {
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
    }

    .checkout-page .item-total {
        justify-content: flex-end;
        margin-top: 0;
        padding-top: 0;
        border-top: none;
        min-width: 120px;
    }

    .checkout-page .price-info {
        align-items: flex-start;
    }

    .checkout-page .savings-info, 
    .checkout-page .offer-info, 
    .checkout-page .minimum-quantity-info {
        text-align: left;
    }

    .checkout-page .verification-warning {
        flex-direction: row;
        align-items: flex-start;
        text-align: left;
    }

    .checkout-page .warning-icon {
        text-align: left;
    }

    .checkout-page .warning-content {
        text-align: left;
    }

    .checkout-page .verification-actions {
        flex-direction: row;
        justify-content: center;
    }

    .checkout-page .modal-actions {
        flex-direction: row;
        justify-content: center;
    }

    .checkout-page .confirmation-actions {
        flex-direction: row;
        justify-content: center;
    }

    .checkout-page .free-delivery-banner,
    .checkout-page .standard-delivery-message,
    .checkout-page .select-city-message {
        flex-direction: row;
        text-align: left;
    }

    .checkout-page .btn-show-offers, 
    .checkout-page .btn-show-delivery {
        width: auto;
        margin-top: 0;
    }
}

/* Large Desktop Styles */
@media (min-width: 1024px) {
    .checkout-page .checkout-container {
        flex-direction: row;
        gap: 2rem;
    }

    .checkout-page .checkout-form {
        flex: 1;
    }
}

/* Small mobile devices optimization */
@media (max-width: 360px) {
    .checkout-page .container {
        padding: 0.75rem;
    }

    .checkout-page .checkout-form {
        padding: 1rem;
    }

    .checkout-page .checkout-progress {
        padding: 1rem;
    }

    .checkout-page .step-number {
        width: 2rem;
        height: 2rem;
        font-size: 0.75rem;
    }

    .checkout-page .step-label {
        font-size: 0.7rem;
    }

    .checkout-page .checkout-title {
        font-size: 1.5rem;
    }

    .checkout-page .order-summary-card {
        padding: 1rem;
    }

    .checkout-page .form-input {
        padding: 0.75rem;
    }

    .checkout-page .checkout-btn-primary,
    .checkout-page .checkout-btn-secondary {
        padding: 0.875rem 1.25rem;
        font-size: 0.875rem;
    }

    .checkout-page .step-actions {
        flex-direction: column;
    }
}

/* WhatsApp Order Section */
.checkout-page .whatsapp-order-section {
    background: #f8fff8;
    border: 1px solid #25D366;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
}

.checkout-page .whatsapp-title {
    color: #25D366;
    margin-bottom: 10px;
    font-size: 1.2em;
}

.checkout-page .whatsapp-description {
    color: #666;
    margin-bottom: 15px;
    font-size: 0.95em;
}

/* Responsive Step Actions */
@media (max-width: 768px) {
    .checkout-page .step-actions {
        flex-direction: row !important;
        justify-content: space-between !important;
        gap: 10px !important;
    }
    
    .checkout-page .step-actions .checkout-btn-prev,
    .checkout-page .step-actions .checkout-btn-primary {
        width: 48% !important;
        font-size: 15px !important;
        padding: 10px 18px !important;
    }
    
    .checkout-page .whatsapp-order-btn {
        font-size: 14px !important;
        padding: 10px 16px !important;
    }
    
    /* Verification buttons horizontal layout on mobile */
    .checkout-page .verification-actions {
        display: flex !important;
        gap: 8px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    
    .checkout-page .verification-actions .checkout-btn-primary,
    .checkout-page .verification-actions .checkout-btn-secondary {
        flex: 1 !important;
        min-width: 100px !important;
        font-size: 14px !important;
        padding: 10px 12px !important;
    }
    
    .checkout-page .verification-actions .checkout-btn-link {
        flex: 1 100% !important;
        text-align: center !important;
    }
    
    /* Order item layout on mobile - image left, details right */
    .checkout-page .order-item {
        flex-direction: row !important;
        gap: 0.75rem !important;
        padding: 0.75rem !important;
    }
    
    .checkout-page .item-image {
        width: 80px !important;
        height: 80px !important;
        min-width: 80px !important;
        flex-shrink: 0 !important;
    }
    
    .checkout-page .item-details {
        text-align: left !important;
        flex: 1 !important;
    }
    
    .checkout-page .item-name {
        font-size: 0.875rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    .checkout-page .price-info {
        font-size: 0.8rem !important;
    }
    
    .checkout-page .quantity-section {
        flex-direction: column !important;
        gap: 0.5rem !important;
        margin-top: 0.5rem !important;
    }
    
    .checkout-page .item-total {
        font-size: 0.875rem !important;
        margin-top: 0.5rem !important;
        padding-top: 0.5rem !important;
        border-top: 1px solid #E5E7EB !important;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    .checkout-page * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Focus styles for keyboard navigation */
.checkout-page .checkout-btn-primary:focus,
.checkout-page .checkout-btn-secondary:focus,
.checkout-page .form-input:focus,
.checkout-page .payment-option:focus-within {
    outline: 3px solid rgba(16, 185, 129, 0.5);
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .checkout-page {
        --border-color: #000000;
        --shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .checkout-page .form-input {
        border-width: 3px;
    }
}

/* Print styles */
@media print {
    .checkout-page .checkout-progress,
    .checkout-page .step-actions,
    .checkout-page .verification-warning,
    .checkout-page .checkout-btn-primary,
    .checkout-page .checkout-btn-secondary {
        display: none !important;
    }

    .checkout-page .checkout-form {
        box-shadow: none;
        padding: 0;
    }

    .checkout-page .form-step.active {
        display: block !important;
    }

    .checkout-page .checkout-page {
        background: white !important;
    }
}

/* 🔒 WhatsApp Verification Modal Animations */
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideDown {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(30px);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* WhatsApp Verification Modal Styles */
#whatsappVerificationModal input[type="text"] {
    font-family: 'Courier New', monospace;
}

#whatsappVerificationModal button {
    transition: all 0.3s ease;
}

#whatsappVerificationModal button:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

#whatsappVerificationModal button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#whatsappVerificationError {
    animation: shake 0.5s ease;
}

 
</style>