<?php
/**
 * Phool Delivery - Delivery Rider Panel
 * Main Entry Point (Router)
 */

// Handle static assets before routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove old localhost path prefix if present (for compatibility)
if (strpos($request_uri, '/phool-delivery-platform/delivery-panel/public') === 0) {
    $request_uri = substr($request_uri, strlen('/phool-delivery-platform/delivery-panel/public'));
    if (empty($request_uri)) {
        $request_uri = '/';
    }
}

$static_patterns = [
    '/assets/' => __DIR__ . '/assets/',
    '/js/' => __DIR__ . '/js/',
    '/css/' => __DIR__ . '/css/',
    '/img/' => __DIR__ . '/img/',
];

foreach ($static_patterns as $pattern => $dir) {
    if (strpos($request_uri, $pattern) !== false) {
        // Handle both old and new path formats
        $file_path = str_replace($pattern, $dir, $request_uri);
        if (file_exists($file_path) && is_file($file_path)) {
            // Determine MIME type
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            $mime_types = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
            ];
            
            header('Content-Type: ' . ($mime_types[$ext] ?? 'application/octet-stream'));
            header('Cache-Control: public, max-age=3600');
            readfile($file_path);
            exit;
        }
    }
}

// Minimal public entry — delegate routing to bootstrap
try {
    require_once __DIR__ . '/../bootstrap/app.php';

    $app = $GLOBALS['app'] ?? null;
    if (!$app) {
        http_response_code(500);
        exit('Application failed to initialize.');
    }

    // Hand off routing to bootstrap Application
    $app->run();
} catch (\Exception $e) {
    error_log('CRITICAL ERROR: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    exit('An error occurred. Please check the server logs.');
}

