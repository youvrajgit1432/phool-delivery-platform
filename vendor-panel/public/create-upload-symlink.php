<?php
/**
 * Create Upload Directory Symlink
 * This script creates a symlink from /public/uploads to ../uploads
 * Run this on cPanel online hosting ONCE to fix image serving
 * 
 * Usage:
 * 1. Upload this file to your online hosting (e.g., via FTP)
 * 2. Access it in browser: https://vendor.phooldelivery.example/create-upload-symlink.php
 * 3. It will create the symlink and display results
 */

// Get current directory (should be /public)
$publicDir = __DIR__;
$parentDir = dirname($publicDir);
$uploadsDir = $parentDir . '/uploads';
$symlinkPath = $publicDir . '/uploads';

echo "<h2>Upload Directory Symlink Creation</h2>";
echo "<p>Public Directory: " . htmlspecialchars($publicDir) . "</p>";
echo "<p>Parent Directory: " . htmlspecialchars($parentDir) . "</p>";
echo "<p>Actual Uploads: " . htmlspecialchars($uploadsDir) . "</p>";
echo "<p>Symlink Target: " . htmlspecialchars($symlinkPath) . "</p>";

echo "<hr>";

// Check if uploads directory exists
if (!is_dir($uploadsDir)) {
    echo "<p style='color: red;'><strong>❌ ERROR: Uploads directory does not exist at: " . htmlspecialchars($uploadsDir) . "</strong></p>";
    echo "<p>Please create the uploads directory first on your online hosting.</p>";
    exit;
}

echo "<p style='color: green;'><strong>✓ Uploads directory exists</strong></p>";

// Check if symlink already exists
if (is_link($symlinkPath)) {
    echo "<p style='color: green;'><strong>✓ Symlink already exists</strong></p>";
    echo "<p>Target: " . htmlspecialchars(readlink($symlinkPath)) . "</p>";
    exit;
}

// Check if regular directory/file exists with same name
if (file_exists($symlinkPath)) {
    echo "<p style='color: orange;'><strong>⚠ Warning: Path exists but is not a symlink</strong></p>";
    echo "<p>Path: " . htmlspecialchars($symlinkPath) . "</p>";
    echo "<p>Please manually delete this and try again.</p>";
    exit;
}

// Try to create symlink
echo "<p>Attempting to create symlink...</p>";
if (@symlink($uploadsDir, $symlinkPath)) {
    echo "<p style='color: green;'><strong>✓ SUCCESS: Symlink created!</strong></p>";
    echo "<p>Symlink: " . htmlspecialchars($symlinkPath) . "</p>";
    echo "<p>Target: " . htmlspecialchars(readlink($symlinkPath)) . "</p>";
    echo "<p>Images should now load correctly at: https://vendor.phooldelivery.example/uploads/</p>";
} else {
    echo "<p style='color: red;'><strong>❌ ERROR: Failed to create symlink</strong></p>";
    echo "<p>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>Your hosting doesn't allow symlinks (contact support)</li>";
    echo "<li>Directory permissions issue</li>";
    echo "<li>PHP open_basedir restriction</li>";
    echo "</ul>";
    
    // Try alternative: move uploads to public
    echo "<p><strong>Alternative Solution:</strong></p>";
    echo "<p>Since symlinks aren't working, you can use the alternative .htaccess redirect method.</p>";
    echo "<p>This will be automatically handled by the application.</p>";
}

echo "<hr>";
echo "<p><small>This file can be safely deleted after running it.</small></p>";
?>
