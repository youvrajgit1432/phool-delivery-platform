<?php
/**
 * SEO Meta Tags Manager for Vendor Panel
 * Provides reusable SEO configuration for all vendor pages
 */

// Define base configuration
$base_url = rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/vendor-panel', '/');

// Default meta information
$seo_config = [
    'site_name' => 'Phool Delivery',
    'site_description' => 'Professional flower vendor management and delivery platform in Nepal',
    'logo' => $base_url . '/assets/img/logo.jpg',
    'favicon' => $base_url . '/assets/img/favicon.png',
    'theme_color' => '#667eea',
    'contact_email' => 'vendor@phooldelivery.example',
    'contact_phone' => '+977-9800000000',
    'social' => [
        'facebook' => 'https://www.facebook.com/phooldelivery',
        'twitter' => 'https://twitter.com/phooldelivery',
        'instagram' => 'https://www.instagram.com/phooldelivery',
        'linkedin' => 'https://www.linkedin.com/company/phooldelivery'
    ],
    'areas_served' => ['Kathmandu', 'Bhaktapur', 'Banepa', 'Nepal'],
    'organization' => [
        'type' => 'Organization',
        'name' => 'Phool Delivery Nepal',
        'founded' => '2023',
        'address' => 'Kathmandu, Nepal',
        'country' => 'NP'
    ]
];

// Page-specific meta configurations
$page_configs = [
    'dashboard' => [
        'title' => 'Dashboard - Phool Delivery Vendor Panel',
        'description' => 'Manage your flower business with our comprehensive vendor dashboard. Track orders, manage products, and view analytics in real-time.',
        'keywords' => 'vendor dashboard, order management, flower business, product tracking, vendor analytics'
    ],
    'orders' => [
        'title' => 'Manage Orders - Phool Delivery Vendor Panel',
        'description' => 'View all your customer orders, track status, and manage fulfillment. Real-time order notifications and tracking system.',
        'keywords' => 'vendor orders, order management, flower orders, order tracking, order fulfillment'
    ],
    'products' => [
        'title' => 'Manage Products - Phool Delivery Vendor Panel',
        'description' => 'Add, edit, and manage your flower products. Set prices, inventory, and product details. Grow your product catalog.',
        'keywords' => 'product management, flower products, inventory management, product listing, vendor products'
    ],
    'payouts' => [
        'title' => 'Payouts & Earnings - Phool Delivery',
        'description' => 'View your earnings, payment history, and payout schedules. Track your revenue and manage banking details.',
        'keywords' => 'vendor earnings, payment history, payout tracking, revenue report, vendor income'
    ],
    'account' => [
        'title' => 'Account Settings - Phool Delivery Vendor Panel',
        'description' => 'Manage your vendor account, profile information, and business settings. Update banking and personal details.',
        'keywords' => 'account settings, vendor profile, business information, account management, vendor settings'
    ],
    'analytics' => [
        'title' => 'Business Analytics - Phool Delivery',
        'description' => 'Analyze your business performance with detailed analytics. Track sales, customer behavior, and growth metrics.',
        'keywords' => 'vendor analytics, business analytics, sales analytics, performance metrics, business reports'
    ],
    'support' => [
        'title' => 'Vendor Support - Phool Delivery',
        'description' => 'Get help with your vendor account, orders, and technical issues. Contact our vendor support team.',
        'keywords' => 'vendor support, help center, customer service, technical support, vendor assistance'
    ]
];

/**
 * Get page-specific SEO configuration
 * @param string $page Current page identifier
 * @return array Merged configuration
 */
function getSeoConfig($page = 'dashboard') {
    global $seo_config, $page_configs;
    
    $config = $seo_config;
    
    if (isset($page_configs[$page])) {
        $config = array_merge($config, $page_configs[$page]);
    } else {
        // Default page config
        $config['title'] = $seo_config['site_name'] . ' - Vendor Management Panel';
        $config['description'] = $seo_config['site_description'];
        $config['keywords'] = 'flower vendor, vendor management, product management, order tracking, flower business nepal';
    }
    
    return $config;
}

/**
 * Generate canonical URL
 * @return string Canonical URL
 */
function getCanonicalUrl() {
    global $base_url;
    return $base_url . $_SERVER['REQUEST_URI'];
}

/**
 * Generate Organization Schema
 * @return string JSON-LD schema
 */
function getOrganizationSchema() {
    global $seo_config, $base_url;
    
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => $seo_config['organization']['type'],
        'name' => $seo_config['organization']['name'],
        'url' => $base_url,
        'logo' => $seo_config['logo'],
        'description' => $seo_config['site_description'],
        'email' => $seo_config['contact_email'],
        'telephone' => $seo_config['contact_phone'],
        'foundingDate' => $seo_config['organization']['founded'],
        'areaServed' => array_map(function($area) {
            return [
                '@type' => 'City',
                'name' => $area
            ];
        }, $seo_config['areas_served']),
        'sameAs' => array_values($seo_config['social']),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'Vendor Support',
            'telephone' => $seo_config['contact_phone'],
            'email' => $seo_config['contact_email']
        ]
    ]);
}

/**
 * Generate BreadcrumbList Schema
 * @param array $breadcrumbs Breadcrumb items
 * @return string JSON-LD schema
 */
function getBreadcrumbSchema($breadcrumbs = []) {
    global $base_url;
    
    $default_breadcrumbs = [
        ['name' => 'Dashboard', 'url' => $base_url . '/dashboard'],
        ['name' => 'Orders', 'url' => $base_url . '/orders'],
        ['name' => 'Products', 'url' => $base_url . '/products']
    ];
    
    $breadcrumbs = !empty($breadcrumbs) ? $breadcrumbs : $default_breadcrumbs;
    
    $items = [];
    foreach ($breadcrumbs as $index => $item) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['name'],
            'item' => $item['url']
        ];
    }
    
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ]);
}

/**
 * Generate Application Schema
 * @return string JSON-LD schema
 */
function getApplicationSchema() {
    global $seo_config, $base_url;
    
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $seo_config['site_name'] . ' Vendor Panel',
        'description' => 'Comprehensive vendor management system for flower sellers',
        'applicationCategory' => 'BusinessApplication',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'NPR'
        ],
        'author' => [
            '@type' => 'Organization',
            'name' => $seo_config['organization']['name']
        ],
        'url' => $base_url
    ]);
}

/**
 * Generate LocalBusiness Schema
 * @return string JSON-LD schema
 */
function getLocalBusinessSchema() {
    global $seo_config, $base_url;
    
    return json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $seo_config['organization']['name'],
        'url' => $base_url,
        'logo' => $seo_config['logo'],
        'image' => $seo_config['logo'],
        'description' => $seo_config['site_description'],
        'email' => $seo_config['contact_email'],
        'telephone' => $seo_config['contact_phone'],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Kathmandu',
            'addressCountry' => $seo_config['organization']['country']
        ],
        'areaServed' => array_map(function($area) {
            return [
                '@type' => 'City',
                'name' => $area
            ];
        }, $seo_config['areas_served'])
    ]);
}

/**
 * Output meta tags for a specific page
 * @param string $page Page identifier
 * @return void
 */
function outputPageMeta($page = 'dashboard') {
    global $base_url;
    
    $config = getSeoConfig($page);
    $canonical = getCanonicalUrl();
    ?>
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($config['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($config['keywords'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($config['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($config['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($config['logo'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($config['site_name'], ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($config['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($config['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($config['logo'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php
}

/**
 * Output all structured data schemas
 * @return void
 */
function outputStructuredData() {
    ?>
    <!-- Structured Data - Organization -->
    <script type="application/ld+json">
    <?php echo getOrganizationSchema(); ?>
    </script>
    
    <!-- Structured Data - Application -->
    <script type="application/ld+json">
    <?php echo getApplicationSchema(); ?>
    </script>
    
    <!-- Structured Data - LocalBusiness -->
    <script type="application/ld+json">
    <?php echo getLocalBusinessSchema(); ?>
    </script>
    
    <!-- Structured Data - BreadcrumbList -->
    <script type="application/ld+json">
    <?php echo getBreadcrumbSchema(); ?>
    </script>
    <?php
}

?>
