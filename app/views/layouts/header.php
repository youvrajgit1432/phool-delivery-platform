<?php
// app/views/layouts/header.php

// CRITICAL: Set UTF-8 encoding BEFORE session and any output
// This must be done FIRST before any output
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-UA-Compatible: IE=edge');
}

// Enhanced session initialization with persistent login check
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
    
    // Check for persistent login if user is not logged in
    if (!isset($_SESSION['customer_id']) && class_exists('Database')) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $persistentLogin = new PersistentLogin($db);
            $persistentLogin->checkPersistentLogin();
        } catch (Exception $e) {
            error_log("Persistent login check failed: " . $e->getMessage());
        }
    }
}

// Initialize all required session variables with proper defaults
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['message_count'] = $_SESSION['message_count'] ?? 0;

// CRITICAL: Initialize preference flags with proper persistence
$_SESSION['language_preference_set'] = $_SESSION['language_preference_set'] ?? false;
$_SESSION['city_preference_set'] = $_SESSION['city_preference_set'] ?? false;
$_SESSION['notification_preference_set'] = $_SESSION['notification_preference_set'] ?? false;

// Add a flag to force show city modal when user clicks "Change Location"
if (!isset($_SESSION['force_show_city_modal'])) {
    $_SESSION['force_show_city_modal'] = false;
}

// Initialize user preferences with sensible defaults
$_SESSION['user_city_id'] = $_SESSION['user_city_id'] ?? 2; // Default to Kathmandu
$_SESSION['user_city_name'] = $_SESSION['user_city_name'] ?? 'Kathmandu';
$_SESSION['user_language'] = $_SESSION['user_language'] ?? 'en';

// PWA install prompt tracking
$_SESSION['pwa_install_shown'] = $_SESSION['pwa_install_shown'] ?? false;
$_SESSION['pwa_last_shown_date'] = $_SESSION['pwa_last_shown_date'] ?? '';

$is_logged_in = isset($_SESSION['customer_id']);
$user_name = $is_logged_in ? $_SESSION['customer_name'] : '';

// Use PathConfig for all paths
$pathConfig = PathConfig::getInstance();
$base_url = $pathConfig->get('base_url');
$assets_path = $pathConfig->get('assets');

// Dynamic page titles based on content
$page_title = $page_title ?? "Phool Delivery - Fresh Flowers Delivery in Banepa, Bhaktapur & Kathmandu | Sayapatri Marigold Online";

$cart_count = count($_SESSION['cart']);
$message_count = $_SESSION['message_count'];

$is_home_page = (basename($_SERVER['PHP_SELF']) === 'index.php' || 
                $_SERVER['REQUEST_URI'] === '/' || 
                $_SERVER['REQUEST_URI'] === '' ||
                $_SERVER['REQUEST_URI'] === $pathConfig->url(''));

require_once __DIR__ . '/../../../app/helpers/language.php';
LanguageHelper::initialize();
$current_language = LanguageHelper::getCurrentLanguage();

// Get current page for canonical URL and structured data
$current_page = $base_url . $_SERVER['REQUEST_URI'];

// CRITICAL FIX: Use only ONE logo file consistently
$logo_filename = 'logo.jpg'; // Stick to one file name

// Logo URLs - CONSISTENT PATHS
$logo_url = $base_url . '/assets/img/' . $logo_filename;
$og_image_url = $base_url . '/assets/img/' . $logo_filename; 
$twitter_image_url = $base_url . '/assets/img/' . $logo_filename;
$favicon_url = $base_url . '/assets/img/favicon.png';

// Verify logo file exists, fallback to default
$logo_physical_path = __DIR__ . '/../../../../public/assets/img/' . $logo_filename;
if (!file_exists($logo_physical_path)) {
    // Log this issue for debugging
    error_log("Logo file not found: " . $logo_physical_path);
    // Use a generic fallback or create the file
    $logo_url = $base_url . '/assets/img/logo.jpg';
}

// Ensure HTTPS for all URLs (Google prefers HTTPS)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $base_url = str_replace('http://', 'https://', $base_url);
    $logo_url = str_replace('http://', 'https://', $logo_url);
    $og_image_url = str_replace('http://', 'https://', $og_image_url);
    $twitter_image_url = str_replace('http://', 'https://', $twitter_image_url);
}

?>

<!DOCTYPE html>
<html lang="ne" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description ?? 'Fresh Phool Delivery in Banepa, Bhaktapur, Kathmandu. Order organic Sayapatri, Marigold, Genda Phool for Tihar, Dashain, Puja. Same day flower delivery from local farmers.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords ?? 'phool delivery, fool delivery, ful delivery, sayapatri, marigold, genda phool, flower delivery banepa, tihar flowers, dashain decoration, organic flowers nepal, fresh flowers kathmandu, puja flowers, wedding flowers, festival flowers nepal', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="Phool Delivery Nepal">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Language Alternatives -->
    <link rel="alternate" hreflang="ne" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph / Facebook - FIXED CONSISTENT LOGO URL -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description ?? 'Fresh Phool Delivery Service in Banepa, Bhaktapur, Kathmandu Valley. Organic flowers for festivals and occasions.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="500">
    <meta property="og:image:height" content="500">
    <meta property="og:image:alt" content="Phool Delivery Nepal - Fresh Flower Delivery Service">
    <meta property="og:site_name" content="Phool Delivery Nepal">
    <meta property="og:locale" content="ne_NP">
    
    <!-- Twitter Card - FIXED CONSISTENT LOGO URL -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@phooldelivery">
    <meta name="twitter:creator" content="@phooldelivery">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description ?? 'Fresh Phool Delivery in Banepa, Bhaktapur, Kathmandu. Order organic flowers for festivals.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image:alt" content="Phool Delivery Nepal - Fresh Flower Delivery">
    
    <!-- Additional Meta Tags for Better SEO -->
    <meta name="google-site-verification" content="YOUR_GOOGLE_SEARCH_CONSOLE_CODE">
    
    <!-- Favicon and App Icons - FIXED: Separate favicon from logo -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.webmanifest">
    <link rel="stylesheet" href="">
    <!-- AOS Animation Library -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
     
    <!-- PWA CSS -->
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/pwa.css?v=1.0">
    <!-- PWA CSS -->
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/main.css?v=1.0">
    <!-- Structured Data for Local Business with Logo - FIXED LOGO URL -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Florist",
        "@id": "<?php echo $base_url; ?>/#florist",
        "name": "Phool Delivery Nepal",
        "description": "Fresh flower delivery service in Banepa, Bhaktapur and Kathmandu. Specializing in organic Sayapatri, Marigold, and Genda Phool for festivals and occasions.",
        "url": "<?php echo $base_url; ?>",
        "telephone": "+977-9803962360",
        "logo": "<?php echo $logo_url; ?>",
        "image": "<?php echo $logo_url; ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Banepa-5, Kavre",
            "addressLocality": "Banepa,Dhulikhel,Bhaktapur ,Kathmandu",
            "addressRegion": "Bagmati Province",
            "postalCode": "45210",
            "addressCountry": "NP"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "27.6292",
            "longitude": "85.5214"
        },
        "openingHours": [
            "Mo-Su 06:00-20:00"
        ],
        "priceRange": "₹₹",
        "areaServed": ["Banepa", "Bhaktapur", "Kathmandu", "Dhulikhel", "Kavre"],
        "sameAs": [
            "https://www.facebook.com/phooldeliverynepal",
            "https://www.instagram.com/phooldeliverynepal"
        ]
    }
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/main.css?v=1.2">
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/responsive.css?v=1.2">
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/animations.css">
    <link rel="stylesheet" href="<?php echo $assets_path; ?>/css/language-fix.css?v=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sajanm/nepali-date-picker@4.0.1/dist/nepali.datepicker.v4.0.1.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery (required for language switching and AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Phool Delivery">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/assets/img/apple-touch-icon.png">

    <!-- Theme Color -->
    <meta name="theme-color" content="#FF6B01">
    <meta name="msapplication-TileColor" content="#FF6B01">

    <!-- Preload critical resources -->
    <!-- Removed preload for main.css as it's already loaded via link tag below -->
    <!-- Category CSS -->
<link rel="stylesheet" href="<?php echo $assets_path; ?>/css/categories.css?v=1.0">
    <link rel="preload" href="<?php echo $logo_url; ?>" as="image">
    <?php
    // Preload top product images to improve LCP when available
    if (!empty($products) && is_array($products)) {
        $preload_count = 0;
        foreach ($products as $prod) {
            if ($preload_count >= 4) break;
            $img = $prod['image_url'] ?? null;
            if ($img) {
                echo '<link rel="preload" href="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" as="image">' . "\n";
                $preload_count++;
            }
        }
    }
    ?>
    
    <!-- Additional SEO Meta Tags -->
    <meta name="geo.region" content="NP-BA">
    <meta name="geo.placename" content="Banepa, Kavre, Nepal">
    <meta name="geo.position" content="27.6292;85.5214">
    <meta name="ICBM" content="27.6292, 85.5214">
    
</head>
<body itemscope itemtype="https://schema.org/WebPage">

    <!-- Language Selection Toast -->
    <div id="languageToast" class="language-toast"></div>
    
    <!-- Language Modal - Always loaded so it can be triggered anytime -->
    <?php include __DIR__ . '/language-modal.php'; ?>
    
    <!-- City Delivery Address Modal -->
    <?php include __DIR__ . '/city-modal.php'; ?>
    
    <!-- Notification Preferences Modal -->
    <?php include __DIR__ . '/notification-modal.php'; ?>
    
    <!-- Install Button for PWA -->
    <button class="install-button" id="installButton" style="display: none;">
        <i class="fas fa-download"></i> Install App
    </button>

    <!-- Top Delivery Banner - 100% width  
    <div class="delivery-banner">
        <span class="delivery-icon"><i class="fas fa-truck action-icon"></i></span> FREE DELIVERY!!! Enjoy free shipping with our free delivery time slots
    </div>
    -->
    <!-- Location Bar  
    <div class="location-bar">
        <div class="location-container">
            <div class="location-info">
                <span class="location-icon"><i class="fas fa-map-marker-alt action-icon"></i></span>
                <span class="location-text">Where to deliver?</span>
                <span class="change-location" id="changeLocationBtn">Change Location</span>
            </div>
            <div class="contact-info">
                <span class="phone-icon"><i class="fas fa-phone action-icon"></i></span> Need help? Call: 9803962360
            </div>
        </div>
    </div>
    -->
    <!-- Main Header -->
    <header class="main-header" style="position: fixed; top: 0; left: 0; right: 0; z-index: 999; background: white; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="header-container">
            <!-- Logo with image from old code -->
            <div class="logo-container">
                <a href="<?php echo $base_url; ?>/" aria-label="Phool Delivery Nepal Home">
                    <img src="<?php echo $assets_path; ?>/img/logo.jpg" alt="Phool Delivery Nepal - Fresh Flower Delivery Service" class="logo-image" itemprop="image" loading="eager">
                </a>
            </div>
            
            <!-- Search Bar - Centered between logo and icons on mobile -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="<?= LanguageHelper::t('search_flowers', 'Search flowers, marigold, sayapatri...') ?>" id="searchInput">
                    <button class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <!-- Header Actions -->
            <div class="header-actions" style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap; justify-content: flex-end;">
                <!-- Home button (Desktop only) -->
                <a href="<?php echo $base_url; ?>/" class="action-item desktop-only-nav" title="Home" aria-label="Go to Home">
                    <i class="fas fa-home action-icon"></i>
                    <span class="action-text"><?= LanguageHelper::t('home', 'Home') ?></span>
                </a>
                
                <!-- Flowers button (Desktop only) -->
                <a href="<?php echo $base_url; ?>/products" class="action-item desktop-only-nav" title="Flowers" aria-label="View all flowers">
                    <i class="fas fa-leaf action-icon"></i>
                    <span class="action-text"><?= LanguageHelper::t('flowers', 'Flowers') ?></span>
                </a>
                
                <!-- Media button from old code -->
                <a href="<?php echo $base_url; ?>/media" class="action-item" title="Media Gallery" aria-label="View our media gallery">
                    <i class="fas fa-images action-icon"></i>
                    <span class="action-text"><?= LanguageHelper::t('media', 'Media') ?></span>
                </a>
                
            
                <!-- Cart button - Hidden on mobile -->
                <a href="<?php echo $base_url; ?>/cart" class="action-item cart-action">
                    <i class="fas fa-shopping-cart action-icon"></i>
                    <span class="action-text"><?= LanguageHelper::t('cart', 'Cart') ?></span>
                    <?php if($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Messages button - Hidden on mobile, shown on desktop -->
                <a href="<?php echo $base_url; ?>/messages" class="action-item" aria-label="Messages" style="position: relative;">
                    <i class="fas fa-envelope action-icon"></i>
                    <span class="action-text"><?= LanguageHelper::t('messages', 'Messages') ?></span>
                    <?php if($message_count > 0): ?>
                        <span class="cart-badge message-badge"><?= $message_count ?></span>
                    <?php endif; ?>
                </a>
                     <!-- Language Switch Button - Opens Language Modal -->
                <button class="action-item language-switch-btn" id="languageSwitchBtn" aria-label="Change Language" title="Change Language" type="button">
                    <i class="fas fa-globe action-icon"></i>
                    <span class="action-text"><?= ($current_language === 'ne' ? 'ने' : 'En') ?></span>
                </button>
                
                <!-- User Account Section from old code - Hidden on mobile -->
                <?php if ($is_logged_in) : ?>
                <div class="user-dropdown" style="position: relative; display: inline-block;">
                    <button class="action-item user-account-btn" aria-expanded="false" aria-controls="user-dropdown-menu" style="background: none; border: none; cursor: pointer;">
                        <i class="fas fa-user action-icon"></i>
                        <span class="action-text"><?= LanguageHelper::t('my_account', 'Account') ?></span>
                    </button>
                    <div class="dropdown-menu" id="user-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; background: #fff; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 160px; z-index: 1000;" role="menu">
                        <a href="<?php echo $base_url; ?>/account" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; font-size: 14px;" role="menuitem"><?= LanguageHelper::t('my_account', 'My Account') ?></a>
                        <a href="<?php echo $base_url; ?>/logout" 
                           style="display: block; padding: 10px 15px; text-decoration: none; color: #e63946; font-size: 14px;" 
                           role="menuitem"
                           onclick="return confirmLogout(event)">
                            <?= LanguageHelper::t('logout', 'Logout') ?>
                        </a>
                    </div>
                </div>
                <?php else : ?>
                    <div class="auth-buttons" style="display: flex; gap: 10px;">
                        <a href="<?php echo $base_url; ?>/login" class="action-item login-action" aria-label="Login to your account">
                            <i class="fas fa-sign-in-alt action-icon"></i>
                            <span class="action-text"><?= LanguageHelper::t('login', 'Login') ?></span>
                        </a>
                        <a href="<?php echo $base_url; ?>/signup" class="action-item signup-action" aria-label="Sign up for a new account">
                            <i class="fas fa-user-plus action-icon"></i>
                            <span class="action-text"><?= LanguageHelper::t('signup', 'Sign Up') ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
<!-- Main Navigation -->
<nav class="main-nav-container">
    <div class="main-nav">
        <div class="nav-items">
            <a href="<?= $base_url ?>/" class="nav-item <?php echo $is_home_page ? 'active' : ''; ?>"><?= LanguageHelper::t('home', 'Home') ?></a>
            
            <!-- Category Buttons -->
            <?php
            // Fetch main categories for navigation
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $main_categories_stmt = $db->prepare("
                    SELECT id, name_en, name_ne 
                    FROM categories 
                    WHERE status = 'active' AND type = 'main' 
                    ORDER BY sort_order ASC
                ");
                $main_categories_stmt->execute();
                $nav_categories = $main_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($nav_categories as $category) {
                    $category_name = LanguageHelper::getLocalizedText($category, 'name');
                    $category_slug = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s]/', '', $category['name_en'])));
                    $is_active = (isset($_SESSION['current_category_id']) && $_SESSION['current_category_id'] == $category['id']);
                    ?>
                    <a href="<?= $base_url ?>/category/<?= $category['id'] ?>/<?= $category_slug ?>" 
                       class="nav-item category-btn <?= $is_active ? 'active' : '' ?>" 
                       data-category-id="<?= $category['id'] ?>">
                        <?= htmlspecialchars($category_name) ?>
                    </a>
                    <?php
                }
            } catch (Exception $e) {
                error_log("Error fetching navigation categories: " . $e->getMessage());
            }
            ?>
            
            <!-- All Products Link -->
            <a href="<?= $base_url ?>/products" class="nav-item">
                <?= LanguageHelper::t('all_products', 'All Products') ?>
            </a>
        </div>
    </div>
</nav>

<!-- Category Navigation (for mobile) -->
<nav class="category-nav-container mobile-only">
    <div class="category-scroll">
        <div class="category-items">
            <?php if (isset($nav_categories)): ?>
                <?php foreach ($nav_categories as $category): 
                    $category_name = LanguageHelper::getLocalizedText($category, 'name');
                    $category_slug = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s]/', '', $category['name_en'])));
                    $is_active = (isset($_SESSION['current_category_id']) && $_SESSION['current_category_id'] == $category['id']);
                    ?>
                    <a href="<?= $base_url ?>/category/<?= $category['id'] ?>/<?= $category_slug ?>" 
                       class="category-item <?= $is_active ? 'active' : '' ?>">
                        <?= htmlspecialchars($category_name) ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?= $base_url ?>/products" class="category-item">
                    <?= LanguageHelper::t('all_products', 'All') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
/* Category Navigation Styles */
.category-nav-container {
    background: #fff;
    border-bottom: 1px solid #eaeaea;
    padding: 10px 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.category-scroll {
    display: flex;
    min-width: max-content;
}

.category-items {
    display: flex;
    gap: 10px;
    padding: 0 15px;
}

.category-item {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    color: #495057;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.category-item:hover {
    background: #e9ecef;
    color: #212529;
}

.category-item.active {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

/* Desktop category buttons in main nav */
.nav-items .category-btn {
    position: relative;
    transition: all 0.3s ease;
}

.nav-items .category-btn:hover {
    background: rgba(76, 175, 80, 0.1);
}

.nav-items .category-btn.active {
    color: #4CAF50;
    font-weight: 600;
}

.nav-items .category-btn.active::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    right: 0;
    height: 3px;
    background: #4CAF50;
    border-radius: 3px;
}

/* Action text default styles - ensure visibility */
.action-text {
    display: inline-block;
    visibility: visible;
    opacity: 1;
}

/* Desktop-only navigation buttons - Hidden on mobile */
.desktop-only-nav {
    display: none;
}

@media (min-width: 769px) {
    .desktop-only-nav {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-direction: column !important;
        gap: 2px !important;
        padding: 8px 12px !important;
    }
}

/* Language Switch Button - Exact same styling as other action items */
.language-switch-btn {
    padding: 8px 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-direction: column !important;
    gap: 2px !important;
    min-width: auto !important;
}

.language-switch-btn .action-icon {
    font-size: 20px !important;
    color: #333 !important;
    display: block !important;
}

.language-switch-btn .action-text {
    display: block !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    color: #333 !important;
    margin: 0 !important;
    line-height: 1.4 !important;
    white-space: normal !important;
    text-align: center !important;
    min-height: 16px !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: auto !important;
    overflow: visible !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", "Noto Sans Devanagari", sans-serif !important;
    letter-spacing: normal !important;
    word-break: break-word !important;
}

/* Desktop: Show both icon and text stacked vertically */
@media (min-width: 769px) {
    .language-switch-btn {
        flex-direction: column !important;
        gap: 2px !important;
        padding: 8px 12px !important;
    }
    
    .language-switch-btn .action-text {
        display: block !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        color: #333 !important;
        line-height: 1.4 !important;
        white-space: normal !important;
        text-align: center !important;
        min-height: 16px !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: auto !important;
        overflow: visible !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", "Noto Sans Devanagari", sans-serif !important;
        letter-spacing: normal !important;
        word-break: break-word !important;
    }
    
    .language-switch-btn .action-icon {
        font-size: 20px !important;
        color: #333 !important;
        display: block !important;
    }
}

/* Mobile only */
.mobile-only {
    display: none;
}

@media (max-width: 768px) {
    .mobile-only {
        display: block;
    }
    
    .main-nav-container {
        display: none;
    }
    
    /* Language switcher on mobile - same as desktop */
    .language-switch-btn {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 2px !important;
        padding: 8px 12px !important;
        background: none !important;
        border: none !important;
        cursor: pointer !important;
    }
    
    .language-switch-btn .action-text {
        display: block !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        color: #333 !important;
        line-height: 1.4 !important;
        white-space: normal !important;
        text-align: center !important;
        min-height: 16px !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: auto !important;
        overflow: visible !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", "Noto Sans Devanagari", sans-serif !important;
        letter-spacing: normal !important;
        word-break: break-word !important;
    }
    
    .language-switch-btn .action-icon {
        display: block !important;
        font-size: 20px !important;
        color: #333 !important;
    }
    
    /* Hide cart action button on very small mobile */
    .cart-action {
        display: none !important;
    }
}

/* Language Modal - Always in DOM and ready */
#languageModal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.7) !important;
    z-index: 999999 !important;
    padding: 20px !important;
    box-sizing: border-box !important;
}
</style>
    
    <!-- Category Navigation -->
   
    
    <!-- Mobile Bottom Navigation - From old code -->
    <nav class="mobile-bottom-nav" role="navigation" aria-label="Mobile navigation" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 998; background: white; width: 100%; border-top: 1px solid #eaeaea;">
        <a href="<?php echo $base_url; ?>/" class="mobile-nav-item" title="Home" aria-current="<?php echo $is_home_page ? 'page' : 'false'; ?>">
            <i class="fas fa-home"></i>
            <span><?= LanguageHelper::t('home', 'Home') ?></span>
        </a>
        
        <a href="<?php echo $base_url; ?>/products" class="mobile-nav-item" title="Flowers">
            <i class="fas fa-leaf"></i>
            <span><?= LanguageHelper::t('flowers', 'Flowers') ?></span>
        </a>
        
        <a href="<?php echo $base_url; ?>/cart" class="mobile-nav-item" title="Cart" style="position: relative;">
            <i class="fas fa-shopping-cart"></i>
            <?php if($cart_count > 0): ?>
                <span class="mobile-badge"><?= $cart_count ?></span>
            <?php endif; ?>
            <span><?= LanguageHelper::t('cart', 'Cart') ?></span>
        </a>

        <a href="<?php echo $base_url; ?>/messages" class="mobile-nav-item" title="Messages" style="position: relative;">
            <i class="fas fa-envelope"></i>
            <?php if($message_count > 0): ?>
                <span class="mobile-badge"><?= $message_count ?></span>
            <?php endif; ?>
            <span><?= LanguageHelper::t('messages', 'Messages') ?></span>
        </a>

        <?php if ($is_logged_in) : ?>
            <a href="<?php echo $base_url; ?>/account" class="mobile-nav-item" title="Account">
                <i class="fas fa-user"></i>
                <span><?= LanguageHelper::t('my_account', 'Account') ?></span>
            </a>
        <?php else : ?>
            <a href="<?php echo $base_url; ?>/login" class="mobile-nav-item" title="Login">
                <i class="fas fa-sign-in-alt"></i>
                <span><?= LanguageHelper::t('login', 'Login') ?></span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Modal Display Manager -->
    <script>
    // Get base URL from server-side PHP
    const baseUrl = '<?php echo $base_url; ?>';
    
    // Production environment detection
    var isProduction = baseUrl.includes('phooldelivery.example') || baseUrl.includes('https://');
    // Completely suppress all debug logs - no console output in any environment
    var debugLog = function() {};
    var debugWarn = function() {};
    var debugError = function() {};
    
    // Expose globally for external scripts - set empty functions to prevent other scripts from logging
    window.isProduction = isProduction;
    window.debugLog = function() {};
    window.debugWarn = function() {};
    window.debugError = function() {};
    
    // Language Switch Button - Ensure it works in all languages
    document.addEventListener('DOMContentLoaded', function() {
        const languageSwitchBtn = document.getElementById('languageSwitchBtn');
        
        if (languageSwitchBtn) {
            languageSwitchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Try to find and show language modal
                const languageModal = document.getElementById('languageModal');
                if (languageModal) {
                    languageModal.style.display = 'flex';
                    document.body.classList.add('modal-open');
                } else {
                    debugWarn('Language modal not found in DOM');
                }
                
                // Also call the global function if it exists
                if (window.showLanguageModalManually) {
                    window.showLanguageModalManually();
                }
                return false;
            });
            
            // Also handle Enter key
            languageSwitchBtn.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.code === 'Space') {
                    e.preventDefault();
                    this.click();
                }
            });
        }
    });
    
    // Global modal display manager - handles sequential modal display
    (function() {
        // Configuration
        const LANGUAGE_MODAL_DELAY = 2000; // 2 seconds after page load
        const CITY_MODAL_DELAY = 3000;     // 3 seconds after language modal closes
        
        // Track modal states
        let languageModalShown = false;
        let cityModalShown = false;
        let pageLoadTime = Date.now();
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeModalManager);
        } else {
            initializeModalManager();
        }
        
        function initializeModalManager() {
            debugLog('Modal Manager Initialized');
            
            // Check if we just reloaded the page after language change
            const showCityModalImmediately = sessionStorage.getItem('showCityModalAfterReload') === 'true';
            
            if (showCityModalImmediately) {
                debugLog('Page reloaded after language change - showing city modal immediately');
                sessionStorage.removeItem('showCityModalAfterReload');
                
                // Wait a bit for city modal to be in DOM, then show it
                setTimeout(function() {
                    const cityModal = document.getElementById('cityModal');
                    if (cityModal) {
                        debugLog('City modal found, displaying now');
                        cityModal.style.display = 'flex';
                        document.body.classList.add('modal-open');
                        cityModalShown = true;
                    } else {
                        debugWarn('City modal not found in DOM');
                    }
                }, 500); // Wait 500ms for DOM to settle
                
                return; // Don't run normal flow
            }
            
            // Normal flow: Set up language modal to show after delay
            showLanguageModalWithDelay();
            
            // Listen for language modal close event to trigger city modal
            document.addEventListener('languageModalClosed', function() {
                debugLog('Language modal closed event received');
                languageModalShown = true;
                showCityModalWithDelay();
            });
        }
        
        function showLanguageModalWithDelay() {
            const languageModal = document.getElementById('languageModal');
            
            if (!languageModal) return;
            
            // Check if user explicitly closed the modal (don't show again)
            const wasExplicitlyClosed = sessionStorage.getItem('languageModalExplicitlyClosed') === 'true' || 
                                       localStorage.getItem('languageModalClosed') === 'true';
            
            // Check if should show language modal (based on session/preferences)
            const shouldShowLanguageModal = <?= (LanguageHelper::shouldShowLanguageModal() ? 'true' : 'false') ?>;
            
            if (!shouldShowLanguageModal || wasExplicitlyClosed) {
                // Skip to city modal if language modal not needed or was explicitly closed
                languageModalShown = true;
                showCityModalWithDelay();
                return;
            }
            
            // Show language modal after 2 seconds
            setTimeout(function() {
                if (!languageModalShown) {
                    languageModal.style.display = 'flex';
                    document.body.classList.add('modal-open');
                    languageModalShown = true;
                    debugLog('Language modal displayed');
                }
            }, LANGUAGE_MODAL_DELAY);
        }
        
        function showCityModalWithDelay() {
            // Wait for city modal to exist in DOM
            function waitForCityModal(attempts = 0) {
                const cityModal = document.getElementById('cityModal');
                
                if (!cityModal) {
                    if (attempts < 10) {
                        debugLog('City modal not found, retrying... (attempt ' + (attempts + 1) + ')');
                        setTimeout(() => waitForCityModal(attempts + 1), 300);
                    } else {
                        debugWarn('City modal element not found after retries');
                    }
                    return;
                }
                
                debugLog('City modal found in DOM');
                
                // Check if user explicitly closed the modal (don't show again)
                const wasExplicitlyClosed = sessionStorage.getItem('cityModalExplicitlyClosed') === 'true' || 
                                           localStorage.getItem('cityModalClosed') === 'true';
                
                // Check if should show city modal (based on session/preferences)
                const shouldShowCityModal = <?= ((!isset($_SESSION['city_preference_set']) || $_SESSION['city_preference_set'] !== true) ? 'true' : 'false') ?>;
                
                if (!shouldShowCityModal || wasExplicitlyClosed) {
                    debugLog('City modal should not be shown - preferences set or explicitly closed');
                    return;
                }
                
                // Show city modal after 3 seconds delay
                setTimeout(function() {
                    if (!cityModalShown && cityModal.style.display !== 'flex') {
                        cityModal.style.display = 'flex';
                        document.body.classList.add('modal-open');
                        cityModalShown = true;
                        debugLog('City modal displayed after delay');
                    }
                }, CITY_MODAL_DELAY);
            }
            
            waitForCityModal();
        }
        
        // Global function to manually show city modal (e.g., from "Change Location" button)
        window.showCityModalManually = function() {
            const cityModal = document.getElementById('cityModal');
            if (cityModal) {
                cityModal.style.display = 'flex';
                document.body.classList.add('modal-open');
                cityModalShown = true;
            }
        };
        
        // Global function to manually show language modal
        window.showLanguageModalManually = function() {
            const languageModal = document.getElementById('languageModal');
            
            if (languageModal) {
                languageModal.style.display = 'flex';
                languageModal.style.visibility = 'visible';
                languageModal.style.opacity = '1';
                document.body.classList.add('modal-open');
                debugLog('Language modal displayed successfully');
            } else {
                debugWarn('Language modal element not found');
                // Try again after a short delay
                setTimeout(function() {
                    const retryModal = document.getElementById('languageModal');
                    if (retryModal) {
                        retryModal.style.display = 'flex';
                        retryModal.style.visibility = 'visible';
                        retryModal.style.opacity = '1';
                        document.body.classList.add('modal-open');
                        debugLog('Language modal displayed on retry');
                    }
                }, 100);
            }
        };
    })();
    </script>

    <!-- Main Content Area -->
    <main class="main-content" id="main-content" role="main" style="padding-top: 0px; padding-bottom: 80px;">
        <!-- Content will be inserted here by individual pages -->
       