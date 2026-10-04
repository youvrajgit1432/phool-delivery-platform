#!/usr/bin/env php
<?php
/**
 * Phool Delivery - Performance Optimization Implementation Script
 * Run this to apply all performance fixes at once
 * 
 * Usage: php scripts/optimize-performance.php
 */

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║     Phool Delivery - Performance Optimization Setup Script        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$status = true;

// Color codes for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

// Step 1: Check cache directory
echo $blue . "Step 1: Checking cache directories..." . $reset . "\n";
$cacheDir = __DIR__ . '/../storage/cache';
if (!is_dir($cacheDir)) {
    if (@mkdir($cacheDir, 0755, true)) {
        echo $green . "✓ Created storage/cache directory" . $reset . "\n";
    } else {
        echo $red . "✗ Failed to create storage/cache directory" . $reset . "\n";
        $status = false;
    }
} else {
    echo $green . "✓ storage/cache directory exists" . $reset . "\n";
}

// Step 2: Check if PageCache helper exists
echo "\n" . $blue . "Step 2: Verifying PageCache helper..." . $reset . "\n";
$pageCacheFile = __DIR__ . '/../app/helpers/PageCache.php';
if (file_exists($pageCacheFile)) {
    echo $green . "✓ PageCache.php helper found" . $reset . "\n";
} else {
    echo $red . "✗ PageCache.php helper not found" . $reset . "\n";
    $status = false;
}

// Step 3: Check if optimized view exists
echo "\n" . $blue . "Step 3: Verifying optimized view..." . $reset . "\n";
$optimizedView = __DIR__ . '/../app/views/home/index-optimized.php';
if (file_exists($optimizedView)) {
    echo $green . "✓ Optimized view found at: app/views/home/index-optimized.php" . $reset . "\n";
} else {
    echo $red . "✗ Optimized view not found" . $reset . "\n";
    $status = false;
}

// Step 4: Check MySQL indexes SQL file
echo "\n" . $blue . "Step 4: Checking MySQL indexes file..." . $reset . "\n";
$indexFile = __DIR__ . '/../database/mysql-indexes-performance.sql';
if (file_exists($indexFile)) {
    echo $green . "✓ MySQL indexes file found: database/mysql-indexes-performance.sql" . $reset . "\n";
} else {
    echo $red . "✗ MySQL indexes file not found" . $reset . "\n";
    $status = false;
}

// Step 5: Check .htaccess file
echo "\n" . $blue . "Step 5: Checking .htaccess configuration..." . $reset . "\n";
$htaccessFile = __DIR__ . '/../public_html/.htaccess-performance';
if (file_exists($htaccessFile)) {
    echo $green . "✓ Cache configuration file found: public_html/.htaccess-performance" . $reset . "\n";
} else {
    echo $red . "✗ Cache configuration file not found" . $reset . "\n";
    $status = false;
}

// Step 6: Check documentation
echo "\n" . $blue . "Step 6: Verifying documentation..." . $reset . "\n";
$docFiles = [
    __DIR__ . '/../docs/PERFORMANCE-OPTIMIZATION.md',
    __DIR__ . '/../docs/OPCACHE-SETUP.md'
];
foreach ($docFiles as $docFile) {
    if (file_exists($docFile)) {
        echo $green . "✓ " . basename($docFile) . " found" . $reset . "\n";
    } else {
        echo $red . "✗ " . basename($docFile) . " not found" . $reset . "\n";
    }
}

// Summary
echo "\n" . "═══════════════════════════════════════════════════════════════════\n";

if ($status) {
    echo $green . "✓ All optimization files are in place!" . $reset . "\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Run MySQL indexes:\n";
    echo "   " . $yellow . "mysql -u root -p phool_delivery < database/mysql-indexes-performance.sql" . $reset . "\n\n";
    echo "2. Merge .htaccess:\n";
    echo "   " . $yellow . "Copy cache rules from public_html/.htaccess-performance to public_html/.htaccess" . $reset . "\n\n";
    echo "3. Enable OPcache:\n";
    echo "   " . $yellow . "Follow docs/OPCACHE-SETUP.md" . $reset . "\n\n";
    echo "4. Test the site:\n";
    echo "   " . $yellow . "Open your site and check Network tab in DevTools" . $reset . "\n\n";
} else {
    echo $red . "✗ Some files are missing. Please check the above errors." . $reset . "\n";
}

echo "═══════════════════════════════════════════════════════════════════\n";
echo "\nFor detailed information, see: " . $yellow . "docs/PERFORMANCE-OPTIMIZATION.md" . $reset . "\n\n";
?>
