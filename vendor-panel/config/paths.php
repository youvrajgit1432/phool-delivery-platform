<?php
/**
 * Application Paths Configuration for Vendor Panel
 * Uses dynamic PathConfig for both localhost and online hosting
 */

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();

return [
    // Base paths
    'base' => BASE_PATH,
    'app' => APP_PATH,
    'config' => CONFIG_PATH,
    'storage' => STORAGE_PATH,
    'uploads' => UPLOADS_PATH,
    'logs' => LOGS_PATH,
    'cache' => CACHE_PATH,
    'views' => VIEWS_PATH,
    'public' => BASE_PATH . '/public',
    
    // Dynamic URLs
    'base_url' => $pathConfig->get('base_url'),
    'vendor_panel_url' => $pathConfig->get('vendor_panel_url'),
    'main_site_url' => $pathConfig->get('main_site_url'),
    'admin_panel_url' => $pathConfig->get('admin_panel_url'),
    'delivery_panel_url' => $pathConfig->get('delivery_panel_url'),
    
    // Assets paths
    'assets' => $pathConfig->get('assets'),
    'css' => $pathConfig->get('css'),
    'js' => $pathConfig->get('js'),
    'img' => $pathConfig->get('img'),
    'fonts' => $pathConfig->get('fonts'),
    
    // Image URLs
    'product_images' => $pathConfig->get('product_images'),
    'vendor_images' => $pathConfig->get('vendor_images'),
    'profile_images' => $pathConfig->get('profile_images'),
    'carousel_images' => $pathConfig->get('carousel_images'),
    'media_files' => $pathConfig->get('media_files'),
    
    // File system paths
    'root_dir' => $pathConfig->get('root_dir'),
    'vendor_panel_dir' => $pathConfig->get('vendor_panel_dir'),
    'admin_dir' => $pathConfig->get('admin_dir'),
    'uploads_dir' => $pathConfig->get('uploads_dir'),
    'vendor_uploads_dir' => $pathConfig->get('vendor_uploads_dir'),
    'products_dir' => $pathConfig->get('products_dir'),
    'vendor_dir' => $pathConfig->get('vendor_dir'),
    'profiles_dir' => $pathConfig->get('profiles_dir'),
    
    // Environment
    'is_online' => $pathConfig->isOnline(),
    'environment' => $pathConfig->isOnline() ? 'production' : 'local',
];
?>

