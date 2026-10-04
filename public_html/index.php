<?php
// public/index.php

// Start session at the very beginning
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("=== NEW REQUEST ===");
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
error_log("Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET'));
error_log("Query String: " . ($_SERVER['QUERY_STRING'] ?? 'NOT SET'));

// Basic security - prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) == 'index.php') {
    // Get the request URI
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remove the base directory if your application is in a subdirectory
    $base_dir = dirname($_SERVER['SCRIPT_NAME']);
    error_log("Base Directory: " . $base_dir);
    
    if ($base_dir != '/' && strpos($request_uri, $base_dir) === 0) {
        $request_uri = substr($request_uri, strlen($base_dir));
        error_log("URI after base removal: " . $request_uri);
    }
    
    // Ensure the URI starts with a slash
    if (empty($request_uri) || $request_uri[0] != '/') {
        $request_uri = '/' . $request_uri;
        error_log("URI after slash fix: " . $request_uri);
    }
    
    // Set the REQUEST_URI to be used by the router
    $_SERVER['REQUEST_URI'] = $request_uri;
    error_log("Final URI: " . $request_uri);
    
    // Include bootstrap
    require_once __DIR__ . '/../app/bootstrap/app.php';
} else {
    // If accessed directly, deny access
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied.');
}