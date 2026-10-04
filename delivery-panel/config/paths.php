<?php
/**
 * Path Configuration
 * Define all important project paths
 * Now uses dynamic PathConfig class for localhost/online detection
 */

// Load PathConfig singleton for dynamic paths
require_once __DIR__ . '/PathConfig.php';
$pathConfig = PathConfig::getInstance();

return [
    // Dynamic paths from PathConfig
    'base' => $pathConfig->filePath(),
    'base_url' => $pathConfig->get('base_url'),
    'api_base' => $pathConfig->get('api_base'),
    'assets' => $pathConfig->get('assets'),
    'root_dir' => $pathConfig->get('root_dir'),
    
    // Application directories
    'app' => $pathConfig->filePath() . '/app',
    'bootstrap' => $pathConfig->filePath() . '/bootstrap',
    'config' => $pathConfig->filePath() . '/config',
    'database' => $pathConfig->filePath() . '/database',
    'public' => $pathConfig->filePath() . '/public',
    'storage' => $pathConfig->filePath('storage'),
    'resources' => $pathConfig->filePath() . '/app/views',
    'tests' => $pathConfig->filePath() . '/tests',
    'uploads' => $pathConfig->filePath('delivery_profiles'),
    
    // Application modules
    'controllers' => $pathConfig->filePath() . '/app/controllers',
    'models' => $pathConfig->filePath() . '/app/models',
    'middleware' => $pathConfig->filePath() . '/app/middleware',
    'services' => $pathConfig->filePath() . '/app/services',
    'helpers' => $pathConfig->filePath() . '/app/helpers',
    
    // Storage directories
    'logs' => $pathConfig->filePath('logs'),
    'cache' => $pathConfig->filePath('cache'),
    'sessions' => $pathConfig->filePath('storage') . '/sessions',
    
    // Upload directories
    'delivery_profiles' => $pathConfig->filePath('delivery_profiles'),
    'delivery_documents' => $pathConfig->filePath('delivery_documents'),
    
    // Database directories
    'migrations' => $pathConfig->filePath() . '/database/migrations',
    'seeds' => $pathConfig->filePath() . '/database/seeds',
    
    // Shared assets (from admin/main)
    'product_images' => $pathConfig->get('product_images'),
    'ads_images' => $pathConfig->get('ads_images'),
    'carousel_images' => $pathConfig->get('carousel_images'),
    'notices_images' => $pathConfig->get('notices_images'),
    'media' => $pathConfig->get('media'),
    'wallet_qr' => $pathConfig->get('wallet_qr'),
    'profiles' => $pathConfig->get('profiles'),
    
    // CSS/JS/Images
    'css' => $pathConfig->get('css'),
    'js' => $pathConfig->get('js'),
    'img' => $pathConfig->get('img'),
    'lib' => $pathConfig->get('lib'),
    
    // Panel URLs
    'main' => $pathConfig->get('main'),
    'admin' => $pathConfig->get('admin'),
    'delivery' => $pathConfig->get('delivery'),
    
    // API endpoints
    'api_auth' => $pathConfig->get('api_auth'),
    'api_delivery' => $pathConfig->get('api_delivery'),
    'api_orders' => $pathConfig->get('api_orders'),
    'api_location' => $pathConfig->get('api_location'),
    'api_earnings' => $pathConfig->get('api_earnings'),
];
