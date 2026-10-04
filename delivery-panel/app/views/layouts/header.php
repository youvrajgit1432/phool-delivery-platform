<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    
    <!-- Primary Meta Tags - SEO -->
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title . ' - Phool Delivery Rider Panel', ENT_QUOTES, 'UTF-8') : 'Phool Delivery - Rider Delivery Management Dashboard'; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description ?? 'Phool Delivery Rider Panel - Manage your delivery orders, track earnings, and optimize your delivery routes. Real-time order assignment and delivery tracking system for Kathmandu, Bhaktapur, and Banepa.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords ?? 'delivery rider, rider management, order tracking, delivery app, phool delivery, flower delivery kathmandu, rider dashboard, delivery tracking nepal', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="Phool Delivery Nepal">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="format-detection" content="telephone=no">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="format-detection" content="telephone=no">
    
    <!-- Canonical URL -->
    <?php
    $base_url = rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/delivery-panel', '/');
    $current_page = $base_url . $_SERVER['REQUEST_URI'];
    ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Language Alternatives -->
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph / Facebook - Enhanced Social Sharing -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title ?? 'Phool Delivery - Rider Management Dashboard', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description ?? 'Efficient delivery management system for riders. Track orders, earnings, and optimize your delivery routes in real-time.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo $base_url; ?>/assets/img/logo.jpg">
    <meta property="og:image:secure_url" content="<?php echo str_replace('http://', 'https://', $base_url); ?>/assets/img/logo.jpg">
    <meta property="og:image:width" content="500">
    <meta property="og:image:height" content="500">
    <meta property="og:image:alt" content="Phool Delivery Rider Panel">
    <meta property="og:site_name" content="Phool Delivery">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card - Enhanced Social Sharing -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@phooldelivery">
    <meta name="twitter:creator" content="@phooldelivery">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title ?? 'Phool Delivery - Rider Panel', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description ?? 'Professional delivery management system for riders.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo $base_url; ?>/assets/img/logo.jpg">
    <meta name="twitter:image:alt" content="Phool Delivery">
    
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.webmanifest">
    
    <!-- Theme Color -->
    <meta name="theme-color" content="#FF6B35">
    <meta name="msapplication-TileColor" content="#FF6B35">
    
    <!-- SEO - Geographic Meta Tags -->
    <meta name="geo.region" content="NP-BA">
    <meta name="geo.placename" content="Kathmandu, Bhaktapur, Banepa, Nepal">
    <meta name="geo.position" content="27.7172;85.3240">
    <meta name="ICBM" content="27.7172, 85.3240">
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Phool Delivery">
    
    <!-- Structured Data - Organization Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "@id": "<?php echo $base_url; ?>/#organization",
        "name": "Phool Delivery Nepal",
        "url": "<?php echo $base_url; ?>",
        "logo": "<?php echo $base_url; ?>/assets/img/logo.jpg",
        "description": "Professional flower delivery and rider management platform in Nepal",
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "Customer Support",
            "telephone": "+977-9803962360",
            "email": "support@phooldelivery.example",
            "areaServed": ["NP-BA", "NP-BH", "NP-KA"]
        },
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "NP",
            "addressRegion": "Kathmandu"
        },
        "sameAs": [
            "https://www.facebook.com/phooldelivery",
            "https://www.instagram.com/phooldelivery",
            "https://twitter.com/phooldelivery"
        ]
    }
    </script>
    
    <!-- Structured Data - Application Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Phool Delivery Rider Panel",
        "description": "Delivery management system for tracking orders and earnings",
        "applicationCategory": "DeliveryApplication",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "NPR"
        },
        "author": {
            "@type": "Organization",
            "name": "Phool Delivery Nepal"
        }
    }
    </script>
    
    <!-- Structured Data - BreadcrumbList for Navigation -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Dashboard",
                "item": "<?php echo $base_url; ?>/dashboard"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "Orders",
                "item": "<?php echo $base_url; ?>/orders"
            },
            {
                "@type": "ListItem",
                "position": 3,
                "name": "Profile",
                "item": "<?php echo $base_url; ?>/profile"
            }
        ]
    }
    </script>
    
    <?php
    // Preload critical resources for performance
    if (!empty($base_url)): ?>
    <link rel="preload" href="<?php echo $base_url; ?>/assets/css/style.css" as="style">
    <link rel="preload" href="<?php echo $base_url; ?>/assets/img/logo.jpg" as="image">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://maps.googleapis.com">
    <?php endif; ?>
    
    <?php
    // Initialize default values
    $pendingOrdersCount = 0;
    $openTicketsCount = 0;
    
    if (isset($_SESSION['rider_id'])) {
        try {
            // Get database connection
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';
            
            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password
            );
            
            $riderId = $_SESSION['rider_id'];
            
            // Get non-completed orders count (all statuses except delivered, failed, cancelled)
            $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM rider_orders WHERE rider_id = ? AND delivery_status NOT IN (?, ?, ?)');
            $stmt->execute([$riderId, 'delivered', 'failed', 'cancelled']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pendingOrdersCount = $row['cnt'] ?? 0;
            
            // Get open support tickets count
            $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM rider_support_tickets WHERE rider_id = ? AND status IN (?, ?)');
            $stmt->execute([$riderId, 'open', 'in_progress']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $openTicketsCount = $row['cnt'] ?? 0;
        } catch (Exception $e) {
            // Silently fail if database connection issues
        }
    }
    
    // Make counts global for access in footer
    $GLOBALS['pendingOrdersCount'] = $pendingOrdersCount;
    $GLOBALS['openTicketsCount'] = $openTicketsCount;
    ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Google Maps -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo getenv('GOOGLE_MAPS_API_KEY') ?? 'YOUR_API_KEY'; ?>"></script>
    
    <!-- Custom Styles -->
    <?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/style.css')); ?>">
    
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #004E89;
            --success-color: #1AAB8A;
            --danger-color: #EE5A6F;
            --warning-color: #F7B801;
            --light-bg: #F5F5F5;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: #333;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: color 0.3s;
        }
        
        .nav-link:hover {
            color: #fff !important;
        }
        
        .sidebar {
            background: white;
            border-right: 1px solid #e0e0e0;
            min-height: 100vh;
            position: sticky;
            top: 0;
        }
        
        .sidebar-nav {
            padding: 15px 0;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: #f5f5f5;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            padding-left: 18px;
        }
        
        .content-wrapper {
            padding: 25px;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #e55a2a;
            border-color: #e55a2a;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .toast-notification {
            background: white;
            border-left: 4px solid #004E89;
            padding: 16px 20px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .toast-notification.success {
            border-left-color: #1AAB8A;
            color: #0d5a42;
        }

        .toast-notification.error {
            border-left-color: #EE5A6F;
            color: #8b1a2b;
        }

        .toast-notification.warning {
            border-left-color: #F7B801;
            color: #7a5a00;
        }

        .toast-notification.info {
            border-left-color: #004E89;
            color: #002d57;
        }

        .toast-notification i {
            font-size: 1.2rem;
        }

        .toast-notification.success i {
            color: #1AAB8A;
        }

        .toast-notification.error i {
            color: #EE5A6F;
        }

        .toast-notification.warning i {
            color: #F7B801;
        }

        .toast-notification.info i {
            color: #004E89;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast-notification.hide {
            animation: slideOut 0.3s ease-out forwards;
        }

        /* Navigation Badge Styles */
        .nav-badge {
            position: absolute;
            top: -2px;
            right: 2px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin: 0 !important;
            padding: 0;
        }

        .nav-badge.empty {
            display: none;
        }

        .nav-item {
            position: relative;
        }

        /* Mobile Bottom Navigation Badge Styles */
        .mobile-nav-badge {
            position: absolute;
            top: 2px;
            right: 4px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            font-weight: 700;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin: 0 !important;
            padding: 0;
            line-height: 1;
        }

        .mobile-bottom-nav-item {
            position: relative;
        }

        /* Responsive Navigation Badge Styles */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 0;
            }

            .navbar-brand img {
                height: 32px !important;
            }

            .nav-link {
                padding: 0.5rem 1rem !important;
                font-size: 0.9rem;
                white-space: nowrap;
            }

            .nav-badge {
                width: 18px;
                height: 18px;
                font-size: 0.6rem;
                top: -6px;
                right: -6px;
            }
        }

        @media (max-width: 480px) {
            .nav-link {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.85rem;
            }

            .nav-link i {
                margin-right: 4px !important;
                font-size: 0.9rem;
            }

            .nav-badge {
                width: 16px;
                height: 16px;
                font-size: 0.55rem;
                top: -5px;
                right: -5px;
            }

            .mobile-nav-badge {
                width: 16px;
                height: 16px;
                font-size: 0.5rem;
                top: 1px;
                right: 2px;
            }
        }
        
        .status-online {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-offline {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-busy {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e0e0e0;
            display: none;
            z-index: 1000;
            padding-bottom: env(safe-area-inset-bottom);
        }

        .mobile-bottom-nav.show {
            display: flex;
        }

        .mobile-bottom-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 0;
            text-decoration: none;
            color: #666;
            font-size: 0.75rem;
            gap: 4px;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .mobile-bottom-nav-item:hover,
        .mobile-bottom-nav-item.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .mobile-bottom-nav-item i {
            font-size: 1.3rem;
        }

        /* Adjust body padding for mobile nav */
        @media (max-width: 768px) {
            body {
                padding-bottom: 70px;
            }
        }

        /* Accordion / Expandable Section Styles */
        .accordion-section {
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .accordion-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .accordion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            user-select: none;
            transition: all 0.3s ease;
            background: white;
        }

        .accordion-header:hover {
            background-color: #f9f9f9;
        }

        .accordion-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .accordion-header-title i {
            font-size: 1.1rem;
            color: var(--primary-color);
            width: 24px;
            text-align: center;
        }

        .accordion-header-title h6 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }

        .accordion-toggle {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .accordion-section.open .accordion-toggle {
            transform: rotate(90deg);
        }

        .accordion-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: #fafafa;
        }

        .accordion-section.open .accordion-body {
            max-height: 2000px;
            padding: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .accordion-body-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .accordion-body-item {
            display: flex;
            flex-direction: column;
        }

        .accordion-body-item label {
            font-size: 0.75rem;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .accordion-body-item h6 {
            margin: 0;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
            word-break: break-word;
        }

        /* Single column on mobile */
        @media (max-width: 768px) {
            .accordion-body-content {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .accordion-header {
                padding: 14px 16px;
            }

            .accordion-header-title h6 {
                font-size: 0.95rem;
            }

            .accordion-header-title i {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo htmlspecialchars(app_url('/dashboard')); ?>">
                <img src="<?php echo htmlspecialchars(app_url('/assets/img/bl1.png')); ?>" alt="Phool Delivery Logo" style="height: 40px; object-fit: contain;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="navbar-text text-white" style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; overflow: hidden; border: 2px solid rgba(255, 255, 255, 0.3);">
                                <?php 
                                if (isset($rider) && !empty($rider->profile_image_url)): ?>
                                    <img src="<?php echo htmlspecialchars($rider->profile_image_url); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <span>
                                <?php 
                                $rider_name = isset($rider) ? ($rider->first_name ?? 'Rider') : (isset($_SESSION['rider_name']) ? $_SESSION['rider_name'] : 'Rider');
                                echo htmlspecialchars($rider_name);
                                ?>
                            </span>
                        </span>
                    </li>
                    <li class="nav-item ms-3">
                        <a class="nav-link" href="<?php echo htmlspecialchars(app_url('/logout')); ?>">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container with Sidebar -->
    <div class="container-fluid" style="margin-top: 0; padding: 0;">
        <div class="row g-0">
            <!-- Sidebar (Hidden on mobile) -->
            <div class="col-md-2 d-none d-md-block">
                <div class="sidebar">
                    <div class="sidebar-nav">
                        <a href="<?php echo htmlspecialchars(app_url('/dashboard')); ?>" class="<?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false) ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/orders/assigned')); ?>" class="<?php echo (strpos($_SERVER['REQUEST_URI'], '/orders') !== false) ? 'active' : ''; ?>" style="position: relative;">
                            <i class="fas fa-clipboard-list"></i> Orders
                            <?php if ($pendingOrdersCount > 0): ?>
                            <span class="nav-badge"><?php echo $pendingOrdersCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/earnings')); ?>" class="<?php echo (strpos($_SERVER['REQUEST_URI'], '/earnings') !== false) ? 'active' : ''; ?>">
                            <i class="fas fa-wallet"></i> Earnings
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/profile')); ?>" class="<?php echo (strpos($_SERVER['REQUEST_URI'], '/profile') !== false) ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/support')); ?>" class="<?php echo (strpos($_SERVER['REQUEST_URI'], '/support') !== false) ? 'active' : ''; ?>" style="position: relative;">
                            <i class="fas fa-headset"></i> Support
                            <?php if ($openTicketsCount > 0): ?>
                            <span class="nav-badge"><?php echo $openTicketsCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-12 col-md-10" style="padding: 0;">
                <div class="content-wrapper" style="padding: 15px 20px;">
                    <!-- Flash Messages (converted to toasts) -->
                    <?php
                    $flash_messages = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
                    foreach ($flash_messages as $type => $message):
                    ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                showToast('<?php echo addslashes(htmlspecialchars($message)); ?>', '<?php echo htmlspecialchars($type); ?>');
                            });
                        </script>
                    <?php
                    endforeach;
                    unset($_SESSION['flash']);
                    ?>
