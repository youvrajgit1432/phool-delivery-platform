<?php
/**
 * Vendor Panel - Public Entry Point (Router)
 * Main entry point for all requests
 * Routes to appropriate views based on URL
 */

// Register error handler to catch all errors before try/catch
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log('[INDEX ERROR] ' . $errstr . ' at ' . $errfile . ':' . $errline);
    return false;
});

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        error_log('[INDEX FATAL] ' . $error['message'] . ' at ' . $error['file'] . ':' . $error['line']);
    }
});

// DEBUG: Log every request immediately
error_log('[INDEX] Starting request: ' . ($_SERVER['REQUEST_URI'] ?? 'NO URI'));

// Minimal public entry — delegate routing to bootstrap
try {
    error_log('[INDEX] About to load autoload.php');
    require_once __DIR__ . '/../bootstrap/autoload.php';
    error_log('[INDEX] Autoload.php loaded successfully');
    
    require_once __DIR__ . '/../bootstrap/app.php';
    error_log('[INDEX] App.php loaded successfully');

    // Start output buffering if not started
    if (ob_get_level() === 0) ob_start();

    $baseDir = dirname(__DIR__); // vendor-panel
    $publicDir = $baseDir . '/public';

    // Parse the request URL - extract from REQUEST_URI and remove base path
    $raw_request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    error_log('[ROUTER] RAW REQUEST_URI: ' . $raw_request_uri);
    error_log('[ROUTER] SCRIPT_NAME: ' . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET'));
    
    // First, check if __path query parameter exists (from .htaccess rewrite)
    $path_param = $_GET['__path'] ?? '';
    if (!empty($path_param)) {
        // Use the path from rewrite parameter
        $request_path = '/' . ltrim($path_param, '/');
        error_log('[ROUTER] Using __path param from .htaccess: ' . $request_path);
    } else {
        // Extract the clean request path from REQUEST_URI
        $request_path = parse_url($raw_request_uri, PHP_URL_PATH);
        
        // Remove the base path /phool-delivery-platform/vendor-panel/public
        $base_paths = [
            '/phool-delivery-platform/vendor-panel/public',
            '/phool-delivery-platform/vendor-panel',
            '/vendor-panel/public',
            '/vendor-panel'
        ];
        
        foreach ($base_paths as $base) {
            if (strpos($request_path, $base) === 0) {
                $request_path = substr($request_path, strlen($base));
                error_log('[ROUTER] Removed base path: ' . $base);
                break;
            }
        }
        
        // Remove /index.php if present
        if (strpos($request_path, '/index.php') === 0) {
            $request_path = substr($request_path, strlen('/index.php'));
            error_log('[ROUTER] Removed /index.php');
        }
        
        error_log('[ROUTER] Using REQUEST_URI fallback: ' . $request_path);
    }
    
    // Ensure path starts with /
    $request_path = '/' . ltrim($request_path, '/');
    if ($request_path === '//') $request_path = '/';
    
    error_log('[ROUTER] FINAL request_path: ' . $request_path);
    
    // Remove trailing slash for consistent routing (except for root)
    if ($request_path !== '/' && substr($request_path, -1) === '/') {
        $request_path = rtrim($request_path, '/');
    }

    // DEBUG: Show exact path value being used for routing
    error_log('[ROUTER] EXACT PATH FOR ROUTING: "' . $request_path . '" (length: ' . strlen($request_path) . ')');

    // Define request method early
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    error_log('[ROUTER DEBUG] Final request_path: ' . $request_path);
    error_log('[ROUTER DEBUG] REQUEST_METHOD: ' . $request_method);
    
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    // Route handling
    if ($is_ajax && preg_match('/^\/ajax\//', $request_path)) {
        header('Content-Type: application/json');
        $ajax_file = $publicDir . $request_path . '.php';

        if (file_exists($ajax_file)) {
            require_once $ajax_file;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'AJAX endpoint not found']);
        }
    } else {
        // Public routes (no authentication required)
        if ($request_path === '/' || $request_path === '' || empty($request_path)) {
            // Redirect all unauthenticated users to login
            // Skip landing page to avoid redirect loops
            error_log('[ROUTER DEBUG] Root path requested');
            error_log('[ROUTER DEBUG] Session vendor_id: ' . (isset($_SESSION['vendor_id']) && !empty($_SESSION['vendor_id']) ? $_SESSION['vendor_id'] : 'NOT SET'));
            
            if (isset($_SESSION['vendor_id']) && !empty($_SESSION['vendor_id'])) {
                // Authenticated: redirect to dashboard
                error_log('[ROUTER DEBUG] User authenticated, redirecting to dashboard');
                header('Location: ' . vendor_url('/dashboard'), true, 302);
                exit;
            } else {
                // Unauthenticated: redirect to login
                error_log('[ROUTER DEBUG] User not authenticated, redirecting to login');
                header('Location: ' . vendor_url('/login'), true, 302);
                exit;
            }
        } elseif ($request_path === '/login') {
            error_log('[ROUTER DEBUG] ✓ /login route matched');
            
            // Only redirect to dashboard if TRULY logged in (non-empty vendor_id)
            if (!empty($_SESSION['vendor_id'] ?? null)) {
                error_log('[ROUTER DEBUG] Already logged in, redirecting to dashboard');
                header('Location: ' . vendor_url('/dashboard'), true, 302);
                exit;
            }
            
            // Not logged in - show login form
            error_log('[ROUTER DEBUG] Not logged in, showing login form');
            
            $login_file = $baseDir . '/app/views/auth/login.php';
            if (!file_exists($login_file)) {
                error_log('[ROUTER ERROR] Login file not found: ' . $login_file);
                http_response_code(404);
                die('Login page not found');
            }
            
            // Clear buffers and include login
            while (ob_get_level() > 0) ob_end_clean();
            include $login_file;
            exit;
            
        } elseif ($request_path === '/register') {
            if (isset($_SESSION['vendor_id'])) {
                header('Location: ' . vendor_url('/dashboard'));
                exit;
            }
            
            require_once $baseDir . '/app/views/auth/register.php';
            exit;
        } elseif ($request_path === '/forgot-password') {
            if (isset($_SESSION['vendor_id'])) {
                header('Location: ' . vendor_url('/dashboard'));
                exit;
            }
            
            require_once $baseDir . '/app/views/auth/forgot-password.php';
            exit;
        } elseif ($request_path === '/logout') {
            // Destroy session
            session_start();
            session_destroy();
            header('Location: ' . vendor_url('/login'));
            exit;
        } else {
            // Protected routes (require authentication)
            if (!isset($_SESSION['vendor_id'])) {
                // Redirect unauthenticated users to login
                header('Location: ' . vendor_url('/login'));
                exit;
            }

            // Route to appropriate view based on request path
            // Default to dashboard if root path for authenticated users
            $route = ($request_path === '/' || $request_path === '' || empty($request_path)) ? '/dashboard' : $request_path;

            $view_map = [
                '/dashboard' => '/app/views/dashboard/index.php',
                '/products' => '/app/views/products/index.php',
                '/products/add' => '/app/views/products/add.php',
                '/products/edit' => '/app/views/products/edit.php',
                '/products/trash' => '/app/views/products/trash.php',
                '/orders' => '/app/views/orders/index.php',
                '/orders/view' => '/app/views/orders/view.php',
                '/payouts' => '/app/views/payouts/index.php',
                '/payouts/statements' => '/app/views/payouts/statements.php',
                '/availability' => '/app/views/availability/index.php',
                '/account' => '/app/views/account/index.php',
                '/account/edit-profile' => '/app/views/account/edit-profile.php',
            ];

            // First try exact match
            if (isset($view_map[$route])) {
                $view_file = $baseDir . $view_map[$route];
                if (file_exists($view_file)) {
                    // Include header
                    require_once $baseDir . '/app/views/layouts/header.php';
                    // Include the view
                    require_once $view_file;
                    // Include footer
                    require_once $baseDir . '/app/views/layouts/footer.php';
                    exit;
                }
            }
            
            // Try to match routes with path parameters (e.g., /orders/view/63 -> /orders/view with id=63)
            // Pattern: /orders/view/{id}
            if (preg_match('#^/orders/view/(\d+)$#', $route, $matches)) {
                error_log('[router] Matched /orders/view/{id} pattern with id=' . $matches[1]);
                $_GET['order_id'] = $matches[1];
                $view_file = $baseDir . '/app/views/orders/view.php';
                if (file_exists($view_file)) {
                    require_once $baseDir . '/app/views/layouts/header.php';
                    require_once $view_file;
                    require_once $baseDir . '/app/views/layouts/footer.php';
                    exit;
                }
            }
            
            // Pattern: /products/edit/{id}
            if (preg_match('#^/products/edit/(\d+)$#', $route, $matches)) {
                error_log('[router] Matched /products/edit/{id} pattern with id=' . $matches[1]);
                $_GET['id'] = $matches[1];
                $view_file = $baseDir . '/app/views/products/edit.php';
                if (file_exists($view_file)) {
                    require_once $baseDir . '/app/views/layouts/header.php';
                    require_once $view_file;
                    require_once $baseDir . '/app/views/layouts/footer.php';
                    exit;
                }
            }
            
            // Pattern: /orders/invoice/{id}
            if (preg_match('#^/orders/invoice/(\d+)$#', $route, $matches)) {
                $_GET['order_id'] = $matches[1];
                $view_file = $baseDir . '/app/views/orders/invoice.php';
                if (file_exists($view_file)) {
                    require_once $view_file;
                    exit;
                } else {
                    // Try to call controller method if view doesn't exist
                    // For now, treat as 404
                    http_response_code(404);
                    echo "Invoice not available";
                    exit;
                }
            }

            // 404 - Route not found
            error_log('[ROUTER DEBUG] 404 ERROR - request_path: ' . $request_path);
            error_log('[ROUTER DEBUG] Route not found in view_map');
            error_log('[ROUTER DEBUG] Available routes: ' . implode(', ', array_keys($view_map)));
            http_response_code(404);
            echo "Page not found: " . htmlspecialchars($request_path) . "<br>";
            echo "Debug - request_path: " . htmlspecialchars($request_path) . "<br>";
            echo "Debug - Available routes: " . htmlspecialchars(implode(', ', array_keys($view_map))) . "<br>";
            exit;
        }
    }
    
} catch (\Exception $e) {
    error_log('CRITICAL ERROR - Exception: ' . $e->getMessage());
    error_log('CRITICAL ERROR - File: ' . $e->getFile() . ':' . $e->getLine());
    error_log('CRITICAL ERROR - Stack: ' . $e->getTraceAsString());
    http_response_code(500);
    exit('An error occurred. Please check the server logs.');
} catch (\Throwable $t) {
    error_log('CRITICAL ERROR - Throwable: ' . $t->getMessage());
    error_log('CRITICAL ERROR - File: ' . $t->getFile() . ':' . $t->getLine());
    error_log('CRITICAL ERROR - Stack: ' . $t->getTraceAsString());
    http_response_code(500);
    exit('An error occurred. Please check the server logs.');
}

