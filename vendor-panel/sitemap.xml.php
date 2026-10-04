<?php
/**
 * Sitemap generator for Phool Delivery Vendor Panel
 * Generate XML sitemap for search engines
 * 
 * Usage: Access via /vendor-panel/sitemap.xml
 * This should be placed at the root of vendor-panel folder
 */

// Set XML header
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

$base_url = rtrim(getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/vendor-panel', '/');
$current_date = date('Y-m-d');

// Define sitemap entries
$sitemap_entries = [
    [
        'url' => '/dashboard',
        'lastmod' => $current_date,
        'changefreq' => 'daily',
        'priority' => '1.0'
    ],
    [
        'url' => '/orders',
        'lastmod' => $current_date,
        'changefreq' => 'hourly',
        'priority' => '0.9'
    ],
    [
        'url' => '/products',
        'lastmod' => $current_date,
        'changefreq' => 'daily',
        'priority' => '0.9'
    ],
    [
        'url' => '/payouts',
        'lastmod' => $current_date,
        'changefreq' => 'daily',
        'priority' => '0.8'
    ],
    [
        'url' => '/analytics',
        'lastmod' => $current_date,
        'changefreq' => 'daily',
        'priority' => '0.7'
    ],
    [
        'url' => '/account',
        'lastmod' => $current_date,
        'changefreq' => 'weekly',
        'priority' => '0.7'
    ],
    [
        'url' => '/support',
        'lastmod' => $current_date,
        'changefreq' => 'weekly',
        'priority' => '0.6'
    ]
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?xml-stylesheet type="text/xsl" href="' . $base_url . '/sitemap.xsl"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0">
<?php
foreach ($sitemap_entries as $entry) {
    $url = $base_url . $entry['url'];
    echo "\n    <url>\n";
    echo "        <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "        <lastmod>" . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "        <changefreq>" . htmlspecialchars($entry['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo "        <priority>" . htmlspecialchars($entry['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "    </url>\n";
}
?>
</urlset>
