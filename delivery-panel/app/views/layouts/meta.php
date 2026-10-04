<?php
/**
 * SEO Meta Tags Manager for Delivery Panel
 * Provides reusable SEO configuration for all pages
 */

// Define base configuration
$base_url = rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/delivery-panel', '/');

// Default meta information
$seo_config = [
    'site_name' => 'Phool Delivery',
    'site_description' => 'Professional flower delivery and rider management platform in Nepal',
    'logo' => $base_url . '/assets/img/logo.jpg',
    'favicon' => $base_url . '/assets/img/favicon.png',
    'theme_color' => '#FF6B35',
    'contact_email' => 'support@phooldelivery.example',
    'contact_phone' => '+977-9803962360',
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
        'title' => 'Dashboard - Phool Delivery Rider Panel',
        'description' => 'Access your delivery dashboard, track orders in real-time, and manage your earnings. Complete control over your delivery operations.',
        'keywords' => 'delivery dashboard, order tracking, earnings dashboard, rider management, real-time tracking'
    ],
    'orders' => [
        'title' => 'My Orders - Phool Delivery Rider Panel',
        'description' => 'View and manage all your assigned delivery orders. Track order status, delivery locations, and customer details in one place.',
        'keywords' => 'delivery orders, order status, assigned orders, order tracking, delivery management'
    ],
    'earnings' => [
        'title' => 'Earnings & Statistics - Phool Delivery',
        'description' => 'Track your daily, weekly, and monthly earnings. View detailed payment statistics and transaction history.',
        'keywords' => 'rider earnings, delivery payments, income tracking, payment statistics, earnings report'
    ],
    'profile' => [
        'title' => 'Rider Profile - Phool Delivery',
        'description' => 'Manage your rider profile, update personal information, banking details, and delivery preferences.',
        'keywords' => 'rider profile, account settings, personal information, banking details, profile management'
    ],
    'support' => [
        'title' => 'Support & Help - Phool Delivery',
        'description' => 'Get help with your delivery orders, account issues, and technical support. Contact our support team.',
        'keywords' => 'support, help, contact support, technical support, customer service'
    ],
    'statistics' => [
        'title' => 'Delivery Statistics - Phool Delivery',
        'description' => 'View detailed analytics of your delivery performance, completion rates, ratings, and performance metrics.',
        'keywords' => 'delivery statistics, performance metrics, delivery analytics, completion rates, performance report'
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
        $config['title'] = $seo_config['site_name'] . ' - Rider Management Panel';
        $config['description'] = $seo_config['site_description'];
        $config['keywords'] = 'delivery rider, rider management, order tracking, delivery app, flower delivery nepal';
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
            'contactType' => 'Customer Support',
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
        ['name' => 'Profile', 'url' => $base_url . '/profile']
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
        'name' => $seo_config['site_name'] . ' Rider Panel',
        'description' => 'Professional delivery management system for tracking orders and earnings',
        'applicationCategory' => 'DeliveryApplication',
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
