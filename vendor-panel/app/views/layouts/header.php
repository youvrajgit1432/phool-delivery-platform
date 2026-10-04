<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    
    <!-- Primary Meta Tags - SEO -->
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title . ' - Phool Delivery Vendor Panel', ENT_QUOTES, 'UTF-8') : 'Phool Delivery - Vendor Management Dashboard'; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description ?? 'Phool Delivery Vendor Panel - Manage flower products, track orders, and grow your business. Complete vendor management system for Kathmandu, Bhaktapur, and Banepa.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords ?? 'vendor panel, flower vendor, product management, order management, vendor dashboard, phool delivery, flower business nepal, vendor management system', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="Phool Delivery Nepal">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#667eea">
    
    <!-- Canonical URL -->
    <?php
    $base_url = rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/vendor-panel', '/');
    $current_page = $base_url . $_SERVER['REQUEST_URI'];
    ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Language Alternatives -->
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph / Facebook - Enhanced Social Sharing -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title ?? 'Phool Delivery - Vendor Management Dashboard', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description ?? 'Professional vendor management system for flower sellers. Manage products, track orders, and grow your business.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo $base_url; ?>/assets/img/logo.jpg">
    <meta property="og:image:secure_url" content="<?php echo str_replace('http://', 'https://', $base_url); ?>/assets/img/logo.jpg">
    <meta property="og:image:width" content="500">
    <meta property="og:image:height" content="500">
    <meta property="og:image:alt" content="Phool Delivery Vendor Panel">
    <meta property="og:site_name" content="Phool Delivery">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card - Enhanced Social Sharing -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@phooldelivery">
    <meta name="twitter:creator" content="@phooldelivery">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title ?? 'Phool Delivery - Vendor Panel', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description ?? 'Comprehensive vendor management platform for flower sellers.', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo $base_url; ?>/assets/img/logo.jpg">
    <meta name="twitter:image:alt" content="Phool Delivery">
    
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_url; ?>/assets/img/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.webmanifest">
    
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
        "description": "Professional flower delivery and vendor management platform in Nepal",
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "Vendor Support",
            "telephone": "+977-9800000000",
            "email": "vendor@phooldelivery.example",
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
        "name": "Phool Delivery Vendor Panel",
        "description": "Vendor management system for tracking products and orders",
        "applicationCategory": "BusinessApplication",
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
                "name": "Products",
                "item": "<?php echo $base_url; ?>/products"
            }
        ]
    }
    </script>
    
    <?php
    // Preload critical resources for performance
    if (!empty($base_url)): ?>
    <link rel="preload" href="<?php echo htmlspecialchars(vendor_asset_url('/css/vendor.css')); ?>" as="style">
    <link rel="preload" href="<?php echo $base_url; ?>/assets/img/logo.jpg" as="image">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://maps.googleapis.com">
    <?php endif; ?>
    
    <?php
    // Fetch order counts for navigation badges
    $pendingCount = 0;
    $assignedCount = 0;
    $acceptedCount = 0;
    $preparingCount = 0;
    $readyCount = 0;
    $totalNonCompleteCount = 0;
    $productsCount = 0;
    
    if (isset($_SESSION['vendor_id'])) {
        try {
            // Use the global database connection
            $dbConfig = $GLOBALS['config']['database'] ?? [];
            if (!empty($dbConfig)) {
                $db = \App\Database\Connection::getInstance($dbConfig);
                $vendorId = $_SESSION['vendor_id'];
                
                // Get count for each order status (non-complete)
                $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'assigned']);
                $row = $stmt->fetch();
                $assignedCount = $row['cnt'] ?? 0;
                
                $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'accepted']);
                $row = $stmt->fetch();
                $acceptedCount = $row['cnt'] ?? 0;
                
                $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'preparing']);
                $row = $stmt->fetch();
                $preparingCount = $row['cnt'] ?? 0;
                
                $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_orders WHERE vendor_id = ? AND status = ?', [$vendorId, 'ready']);
                $row = $stmt->fetch();
                $readyCount = $row['cnt'] ?? 0;
                
                // Total non-complete orders (assigned + accepted + preparing + ready)
                $totalNonCompleteCount = $assignedCount + $acceptedCount + $preparingCount + $readyCount;
                
                // Get linked products count
                $stmt = $db->query('SELECT COUNT(*) as cnt FROM vendor_product_map WHERE vendor_id = ? AND unlinked_at IS NULL', [$vendorId]);
                $row = $stmt->fetch();
                $productsCount = $row['cnt'] ?? 0;
            }
        } catch (Exception $e) {
            // Silently fail if database connection issues
            error_log('[header.php] Database error: ' . $e->getMessage());
        }
    }
    ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Load URL helpers -->
    <?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(vendor_asset_url('/css/vendor.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(vendor_asset_url('/css/pages.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(vendor_asset_url('/css/business-pages.css')); ?>">
    
    <style>
     
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .toast-notification {
            background-color: #fff;
            border-left: 4px solid #667eea;
            padding: 16px 20px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
            font-size: 14px;
            color: #333;
        }

        .toast-notification.success {
            border-left-color: #1AAB8A;
            background-color: #d4edda;
        }

        .toast-notification.success i {
            color: #1AAB8A;
        }

        .toast-notification.error {
            border-left-color: #EE5A6F;
            background-color: #f8d7da;
        }

        .toast-notification.error i {
            color: #EE5A6F;
        }

        .toast-notification.warning {
            border-left-color: #F7B801;
            background-color: #fff3cd;
        }

        .toast-notification.warning i {
            color: #F7B801;
        }

        .toast-notification.info {
            border-left-color: #004E89;
            background-color: #cfe2ff;
        }

        .toast-notification.info i {
            color: #004E89;
        }

        .toast-notification.hide {
            animation: slideOut 0.3s ease-out;
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

        /* Navigation Bar Styles */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem !important;
        }

        .navbar-dark .navbar-nav .nav-link:hover {
            color: white !important;
        }

        .navbar-dark .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .nav-badge {
            display: inline-block;
            background: #ff4757;
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
            font-weight: 600;
        }

        .navbar-dark .dropdown-menu {
            background: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .navbar-dark .dropdown-menu .dropdown-item {
            color: #333;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }

        .navbar-dark .dropdown-menu .dropdown-item:hover {
            background: #f5f7fa;
            color: #667eea;
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
            color: #667eea;
            border-bottom-color: #667eea;
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

        /* Dashboard Stats Grid Layout */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Tablet: 2 columns */
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }
        }

        /* Small Mobile: 2 columns for better use of space */
        @media (max-width: 480px) {
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .stat-card-content {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .stat-icon {
            font-size: 1.8rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            flex-shrink: 0;
        }

        .stat-card.primary .stat-icon {
            background-color: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .stat-card.success .stat-icon {
            background-color: rgba(26, 171, 138, 0.1);
            color: #1AAB8A;
        }

        .stat-card.warning .stat-icon {
            background-color: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        .stat-card.danger .stat-icon {
            background-color: rgba(238, 90, 111, 0.1);
            color: #EE5A6F;
        }

        .stat-card p {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px !important;
        }

        .stat-card h4 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 6px !important;
        }

        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-card .stat-description {
            font-size: 0.8rem;
            color: #999;
        }

        /* Tablet responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                padding: 16px;
            }

            .stat-card-content {
                gap: 10px;
            }

            .stat-icon {
                font-size: 1.5rem;
                width: 36px;
                height: 36px;
            }

            .stat-card h4 {
                font-size: 1.3rem;
            }

            .stat-card .stat-label {
                font-size: 0.8rem;
            }

            .stat-card .stat-number {
                font-size: 1.5rem;
            }

            .stat-card p {
                font-size: 0.8rem;
            }
        }

        /* Small Mobile responsive adjustments */
        @media (max-width: 480px) {
            .stat-card {
                padding: 12px;
            }

            .stat-card-content {
                flex-direction: column;
                gap: 8px;
            }

            .stat-icon {
                font-size: 1.3rem;
                width: 32px;
                height: 32px;
            }

            .stat-card h4 {
                font-size: 1.1rem;
            }

            .stat-card .stat-label {
                font-size: 0.7rem;
                margin-bottom: 2px !important;
            }

            .stat-card .stat-number {
                font-size: 1.2rem;
                margin-bottom: 2px;
            }

            .stat-card .stat-description {
                font-size: 0.7rem;
            }

            .stat-card p {
                font-size: 0.75rem;
                margin-bottom: 4px !important;
            }
        }

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .stat-card small {
            font-size: 0.85rem;
        }

        /* Tablet responsive font sizes */
        @media (max-width: 768px) {
            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }

            .stat-card p {
                font-size: 0.85rem;
            }

            .stat-card i {
                font-size: 2rem !important;
            }
        }

        /* Small mobile responsive font sizes */
        @media (max-width: 480px) {
            .stat-card {
                padding: 14px;
            }

            .stat-card h3 {
                font-size: 1.3rem;
            }

            .stat-card p {
                font-size: 0.8rem;
            }

            .stat-card i {
                font-size: 1.8rem !important;
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
            color: #667eea;
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
            color: #667eea;
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

        /* Remove flower icon before navbar-brand */
        .navbar-brand::before {
            content: none !important;
            display: none !important;
        }

        .navbar-brand i {
            display: none !important;
        }

        /* Navigation Badge Styles */
        .nav-badge {
            position: absolute;
            top: -8px;
            right: -8px;
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

        /* Mobile Navigation Responsive Styles */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 0;
            }

            .navbar-brand img {
                height: 32px !important;
            }

            .navbar-nav {
                gap: 0;
            }

            .nav-link {
                padding: 0.5rem 1rem !important;
                font-size: 0.9rem;
                white-space: nowrap;
            }

            .nav-badge {
                width: 18px;
                height: 18px;
                font-size: 0.65rem;
                margin-left: 2px;
            }

            .dropdown-menu {
                font-size: 0.9rem;
            }

            .mobile-nav-badge {
                width: 18px;
                height: 18px;
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .navbar-brand img {
                height: 28px !important;
            }

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
                font-size: 0.6rem;
            }

            .mobile-nav-badge {
                width: 16px;
                height: 16px;
                font-size: 0.6rem;
                top: 1px;
                right: 2px;
            }
        }

        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 2px solid #f0f0f0;
            display: none;
            z-index: 1000;
            padding-bottom: env(safe-area-inset-bottom);
            box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.06);
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
            padding: 8px 0;
            text-decoration: none;
            color: #999;
            font-size: 0.7rem;
            gap: 3px;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            position: relative;
            border-radius: 8px 8px 0 0;
        }

        .mobile-bottom-nav-item:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.08);
        }

        .mobile-bottom-nav-item.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: rgba(102, 126, 234, 0.12);
            font-weight: 600;
        }

        .mobile-bottom-nav-item i {
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }

        .mobile-bottom-nav-item:hover i {
            transform: scale(1.1) translateY(-2px);
        }

        .mobile-bottom-nav-item.active i {
            transform: scale(1.15);
        }

        .mobile-nav-badge {
            position: absolute;
            top: 2px;
            right: 8px;
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

        .mobile-nav-badge.empty {
            display: none !important;
        }

        /* Adjust body padding for mobile nav */
        @media (max-width: 768px) {
            body {
                padding-bottom: 60px;
            }

            /* Hide desktop navigation on mobile */
            .navbar-collapse {
                display: none !important;
            }

            .navbar {
                padding: 0.75rem 0;
            }

            .navbar-brand img {
                height: 32px !important;
            }

            /* Show mobile nav */
            .mobile-bottom-nav.show {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            .mobile-nav-badge {
                width: 16px;
                height: 16px;
                font-size: 0.55rem;
                top: 1px;
                right: 6px;
            }
        }

        /* Notification Icon Styles */
        .navbar-notification-icon {
            position: relative;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
            text-decoration: none;
        }

        .navbar-notification-icon:hover {
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification-badge.empty {
            display: none;
        }
    </style>
    
    <!-- Bootstrap JS Bundle (includes Popper for dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Global Toast Function -->
    <script>
        // Toast Notification System - Available globally
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer') || (function() {
                const div = document.createElement('div');
                div.id = 'toastContainer';
                div.className = 'toast-container';
                document.body.appendChild(div);
                return div;
            })();
            
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            
            // Icons for different types
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            toast.innerHTML = `<i class="${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(toast);
            
            // Auto remove after duration
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    </script>
</head>
<body>
    <!-- Top bar with logo and contact -->
 

    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-4">
            <!-- Brand/Logo -->
            <a class="navbar-brand fw-bold" href="<?php echo htmlspecialchars(vendor_url('/dashboard')); ?>">
                <img src="<?php echo htmlspecialchars(vendor_asset_url('/img/bl1.png')); ?>" alt="Phool Delivery Logo" style="height: 40px; object-fit: contain;">
            </a>
            
            <!-- Right side content (Notification Icon) -->
            <div class="d-flex align-items-center ms-auto">
                <a href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>" class="navbar-notification-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($totalNonCompleteCount > 0): ?>
                    <span class="notification-badge"><?php echo min($totalNonCompleteCount, 99); ?></span>
                    <?php else: ?>
                    <span class="notification-badge empty"></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Navbar Toggler (Mobile Menu) - Desktop Only -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/dashboard')); ?>">
                            <i class="fas fa-chart-line me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/products')); ?>">
                            <i class="fas fa-box me-2"></i>Products
                            <?php if ($productsCount > 0): ?>
                            <span class="nav-badge"><?php echo $productsCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/orders')); ?>">
                            <i class="fas fa-receipt me-2"></i>Orders
                            <?php if ($totalNonCompleteCount > 0): ?>
                            <span class="nav-badge"><?php echo $totalNonCompleteCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/availability')); ?>">
                            <i class="fas fa-clock me-2"></i>Availability
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(vendor_url('/payouts')); ?>">
                            <i class="fas fa-money-bill me-2"></i>Revenue
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-2"></i>Account
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li>
                                <a class="dropdown-item" href="<?php echo htmlspecialchars(vendor_url('/account')); ?>">
                                    <i class="fas fa-user me-2"></i>Account Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(vendor_url('/logout')); ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <main class="container-fluid">
        <div class="container">
