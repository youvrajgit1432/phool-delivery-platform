<?php
/**
 * Database Migration - Update Image URLs in Vendors and Gallery Tables
 * Converts hardcoded image paths to relative paths that work across environments
 * 
 * Run this ONCE on your online hosting to fix existing images
 * Access: https://vendor.phooldelivery.example/migrate-image-urls.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require bootstrap
require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);

echo "<h2>Image URL Database Migration</h2>";
echo "<p>Converting hardcoded paths to relative paths...</p>";
echo "<hr>";

$updates = [
    'profile_image_url',
    'logo_url',
    'banner_url'
];

$totalUpdated = 0;

// Update vendors table
foreach ($updates as $column) {
    try {
        // Get all vendors with this column set
        $vendors = $db->query(
            "SELECT id, {$column} FROM vendors WHERE {$column} IS NOT NULL AND {$column} != ''",
            []
        )->fetchAll();
        
        if (empty($vendors)) {
            echo "<p>✓ Vendors table - column '{$column}': No updates needed</p>";
            continue;
        }
        
        $updated = 0;
        foreach ($vendors as $vendor) {
            $oldPath = $vendor[$column];
            $newPath = convertImagePath($oldPath);
            
            if ($oldPath !== $newPath) {
                $db->query(
                    "UPDATE vendors SET {$column} = ? WHERE id = ?",
                    [$newPath, $vendor['id']]
                );
                $updated++;
                $totalUpdated++;
            }
        }
        
        if ($updated > 0) {
            echo "<p style='color: green;'>✓ Vendors table - column '{$column}': Updated {$updated} record(s)</p>";
        } else {
            echo "<p>✓ Vendors table - column '{$column}': No changes needed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error updating vendors.{$column}: " . $e->getMessage() . "</p>";
    }
}

// Update vendor_gallery_images table
try {
    $images = $db->query(
        "SELECT id, image_url FROM vendor_gallery_images WHERE image_url IS NOT NULL AND image_url != ''",
        []
    )->fetchAll();
    
    if (empty($images)) {
        echo "<p>✓ Gallery images table: No updates needed</p>";
    } else {
        $updated = 0;
        foreach ($images as $image) {
            $oldPath = $image['image_url'];
            $newPath = convertImagePath($oldPath);
            
            if ($oldPath !== $newPath) {
                $db->query(
                    "UPDATE vendor_gallery_images SET image_url = ? WHERE id = ?",
                    [$newPath, $image['id']]
                );
                $updated++;
                $totalUpdated++;
            }
        }
        
        if ($updated > 0) {
            echo "<p style='color: green;'>✓ Gallery images table: Updated {$updated} record(s)</p>";
        } else {
            echo "<p>✓ Gallery images table: No changes needed</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error updating gallery images: " . $e->getMessage() . "</p>";
}

echo "<hr>";
if ($totalUpdated > 0) {
    echo "<p style='color: green;'><strong>✓ SUCCESS: Total {$totalUpdated} record(s) updated!</strong></p>";
    echo "<p>Database migration complete. Image paths have been converted to relative paths.</p>";
    echo "<p>Images should now load correctly on both localhost and online hosting.</p>";
} else {
    echo "<p><strong>✓ All paths are already in correct format!</strong></p>";
}

echo "<hr>";
echo "<p><small>This file can be safely deleted after running it.</small></p>";

/**
 * Convert hardcoded image paths to relative paths
 * 
 * Examples:
 * '/phool-delivery-platform/vendor-panel/public/assets/uploads/vendor_1/profile.jpg' 
 *   → '/assets/uploads/vendor_1/profile.jpg'
 * 
 * '/assets/uploads/vendor_1/img_123.jpg' 
 *   → '/assets/uploads/vendor_1/img_123.jpg' (no change)
 */
function convertImagePath($path) {
    if (empty($path)) {
        return $path;
    }
    
    // Already a relative path or URL
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path; // Already full URL
    }
    
    // Remove old hardcoded prefixes
    $path = str_replace('/phool-delivery-platform/vendor-panel/public', '', $path);
    $path = str_replace('/phool-delivery-platform/vendor-panel', '', $path);
    
    // Ensure it starts with /
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    
    return $path;
}

// Check PHP version for str_starts_with compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

?>
