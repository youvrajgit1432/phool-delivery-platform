<?php
/**
 * Vendor Panel Bootstrap Application
 * Initializes dynamic configurations for both localhost and online hosting
 */

// Set timezone
date_default_timezone_set('Asia/Kathmandu');

// Enable error reporting
error_reporting(E_ALL);

// Detect if online hosting
$is_production = (
    strpos($_SERVER['DOCUMENT_ROOT'] ?? '', '/home2/phooldel') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'phooldelivery.example') !== false
);

// Display errors only in development, always log
if ($is_production) {
    ini_set('display_errors', 0);  // Don't display errors in production
} else {
    ini_set('display_errors', 1);  // Show errors in development
}

ini_set('log_errors', 1);

// Include Path Configuration
require_once __DIR__ . '/../config/PathConfig.php';

// Include Environment Helper
require_once __DIR__ . '/../app/helpers/environment.php';

// Include URL Helper Functions
require_once __DIR__ . '/../app/helpers/url.php';

// Include Helper Functions
require_once __DIR__ . '/helpers.php';

// Initialize Path Config (Singleton)
$pathConfig = PathConfig::getInstance();

// Make pathConfig available globally
function pathConfig() {
    return PathConfig::getInstance();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime' => 86400, // 24 hours
    ]);
}

// Initialize session variables only if session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['vendor_id'] = $_SESSION['vendor_id'] ?? null;
    $_SESSION['vendor_name'] = $_SESSION['vendor_name'] ?? null;
    $_SESSION['cart'] = $_SESSION['cart'] ?? [];
    $_SESSION['messages'] = $_SESSION['messages'] ?? [];
}

// Define important constants - use PathConfig instance directly
$_pathConfig = PathConfig::getInstance();

if (!defined('VENDOR_PANEL_URL')) {
    define('VENDOR_PANEL_URL', $_pathConfig->get('base_url'));
}

if (!defined('MAIN_SITE_URL')) {
    define('MAIN_SITE_URL', $_pathConfig->isOnline() 
        ? 'https://phooldelivery.example' 
        : ($_SERVER['HTTP_HOST'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/phool-delivery-platform/public_html' 
           : 'http://localhost/phool-delivery-platform/public_html'));
}

if (!defined('ADMIN_PANEL_URL')) {
    define('ADMIN_PANEL_URL', $_pathConfig->isOnline() 
        ? 'https://admin.phooldelivery.example' 
        : ($_SERVER['HTTP_HOST'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/phool-delivery-platform/admin/public' 
           : 'http://localhost/phool-delivery-platform/admin/public'));
}

if (!defined('DELIVERY_PANEL_URL')) {
    define('DELIVERY_PANEL_URL', $_pathConfig->isOnline() 
        ? 'https://delivery.phooldelivery.example' 
        : ($_SERVER['HTTP_HOST'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/phool-delivery-platform/delivery-panel/public' 
           : 'http://localhost/phool-delivery-platform/delivery-panel/public'));
}

// Load configuration files
$configs = [
    'app' => require __DIR__ . '/../config/app.php',
    'auth' => require __DIR__ . '/../config/auth.php',
    'database' => require __DIR__ . '/../config/database.php',
    'session' => require __DIR__ . '/../config/session.php',
    'paths' => require __DIR__ . '/../config/paths.php',
];

// Store configs globally
$GLOBALS['config'] = $configs;

// Log bootstrap information
error_log("VENDOR PANEL BOOTSTRAP - Environment: " . (pathConfig()->isOnline() ? 'ONLINE' : 'LOCAL'));
error_log("VENDOR PANEL BOOTSTRAP - Base URL: " . pathConfig()->get('base_url'));
error_log("VENDOR PANEL BOOTSTRAP - Database: " . $configs['database']['connections']['mysql']['database']);

?>

