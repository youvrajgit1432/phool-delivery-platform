<?php
/**
 * URL Helper - Build app-aware absolute URLs
 * Supports both localhost and online hosting with PathConfig
 */

if (!function_exists('app_url')) {
    function app_url($path = '') {
        // Use PathConfig for dynamic URL construction
        require_once dirname(__FILE__, 3) . '/config/PathConfig.php';
        
        $pathConfig = PathConfig::getInstance();
        $baseUrl = $pathConfig->get('base_url');
        
        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');
        
        return $baseUrl . $path;
    }
}

if (!function_exists('get_base_path')) {
    function get_base_path() {
        // Use PathConfig for dynamic base path
        require_once dirname(__FILE__, 3) . '/config/PathConfig.php';
        
        $pathConfig = PathConfig::getInstance();
        
        // For localhost: /phool-delivery-platform/delivery-panel/public
        // For online: / (empty root)
        $baseUrl = $pathConfig->get('base_url');
        
        // Extract just the path part (after domain)
        $urlParts = parse_url($baseUrl);
        return $urlParts['path'] ?? '/';
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '') {
        // Use PathConfig for dynamic asset URLs
        require_once dirname(__FILE__, 3) . '/config/PathConfig.php';
        
        $pathConfig = PathConfig::getInstance();
        $basePath = $pathConfig->get('assets');
        
        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');
        
        return $basePath . $path;
    }
}

if (!function_exists('api_url')) {
    function api_url($path = '') {
        // Use PathConfig for API URLs
        require_once dirname(__FILE__, 3) . '/config/PathConfig.php';
        
        $pathConfig = PathConfig::getInstance();
        $apiBase = $pathConfig->get('api_base');
        
        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');
        
        return $apiBase . $path;
    }
}

