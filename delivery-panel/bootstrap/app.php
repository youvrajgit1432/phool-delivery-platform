<?php
/**
 * Bootstrap Application
 * Initialize the delivery rider panel application
 * Includes dynamic path configuration for localhost and online hosting
 */

require_once __DIR__ . '/autoload.php';

// Load dynamic configuration classes first
require_once dirname(__DIR__) . '/config/PathConfig.php';
require_once dirname(__DIR__) . '/app/helpers/environment.php';
require_once dirname(__DIR__) . '/app/helpers/paths.php';

// Initialize PathConfig singleton
$pathConfig = PathConfig::getInstance();

// Load environment variables (use phpdotenv if available, otherwise fallback)
if (class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
} else {
    // Simple .env parser fallback (does not support advanced features)
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (!strpos($line, '=')) continue;
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            // remove surrounding quotes
            $value = preg_replace('/^\"|\"$/', '', $value);
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Set timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kathmandu');

// Get configuration
$config = require_once dirname(__DIR__) . '/config/app.php';

// Initialize logging directory with dynamic path
$logPath = $pathConfig->filePath('logs');
if (!is_dir($logPath)) {
    @mkdir($logPath, 0755, true);
}

// Initialize cache directory with dynamic path
$cachePath = $pathConfig->filePath('cache');
if (!is_dir($cachePath)) {
    @mkdir($cachePath, 0755, true);
}

// Initialize uploads directories
$uploadsPath = $pathConfig->filePath('delivery_profiles');
if (!is_dir($uploadsPath)) {
    @mkdir($uploadsPath, 0755, true);
}

$documentsPath = $pathConfig->filePath('delivery_documents');
if (!is_dir($documentsPath)) {
    @mkdir($documentsPath, 0755, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize dynamic PDO database connection using Database class
require_once dirname(__DIR__) . '/config/database.php';

$dbClass = new Database();
$pdo = $dbClass->getConnection();

// Make $pdo available globally
$GLOBALS['pdo'] = $pdo;

// Store PathConfig and Database instances globally
$GLOBALS['pathConfig'] = $pathConfig;
$GLOBALS['database'] = $dbClass;

// Define application instance (simple service container)
if (!defined('APP_INSTANCE')) {
    class Application {
        private $config = [];
        private $services = [];
        
        public function __construct($config) {
            $this->config = $config;
        }
        
        public function config($key, $default = null) {
            return $this->config[$key] ?? $default;
        }
        
        public function bind($key, $service) {
            $this->services[$key] = $service;
        }
        
        public function get($key) {
            return $this->services[$key] ?? null;
        }
        
        public function has($key) {
            return isset($this->services[$key]);
        }

            /**
             * Run the application router.
             * Moves routing out of public/index.php so public entry is minimal.
             */
            public function run() {
                try {
                    $this->runRouter();
                } catch (\Throwable $e) {
                    // Catch any error and log it
                    $logFile = dirname(__DIR__) . '/storage/logs/critical-errors.log';
                    @file_put_contents($logFile, "\n=== CRITICAL ERROR ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                    @file_put_contents($logFile, "Message: " . $e->getMessage() . "\n", FILE_APPEND);
                    @file_put_contents($logFile, "File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
                    @file_put_contents($logFile, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
                    
                    error_log('CRITICAL: ' . $e->getMessage());
                    
                    // Don't expose error to user
                    http_response_code(500);
                    echo "Internal Server Error. Contact support.";
                    exit;
                }
            }
            
            /**
             * Router implementation
             */
            private function runRouter() {
                // Start output buffering if not started
                if (ob_get_level() === 0) ob_start();

                $baseDir = dirname(__DIR__); // delivery-panel
                $publicDir = $baseDir . '/public';
                $logFile = $baseDir . '/storage/logs/router-debug.log';

                // Initialize variables that will be used in routing
                $request_path = '/';
                $request_method = 'GET';
                $is_ajax = false;

                // Log request start with detailed debugging
                @file_put_contents($logFile, "\n=== REQUEST START ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "POST_DATA_EXISTS: " . (!empty($_POST) ? 'YES' : 'NO') . "\n", FILE_APPEND);
                @file_put_contents($logFile, "POST_KEYS: " . implode(',', array_keys($_POST)) . "\n", FILE_APPEND);
                
                // Parse the request URL
                $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $script_dir = dirname($_SERVER['SCRIPT_NAME']);
                
                // Only remove script_dir if it's not just '/'
                if ($script_dir !== '/' && !empty($script_dir)) {
                    // Use strpos to ensure we only remove from the beginning
                    if (strpos($request_uri, $script_dir) === 0) {
                        $request_path = substr($request_uri, strlen($script_dir));
                    } else {
                        $request_path = $request_uri;
                    }
                } else {
                    // If script_dir is '/', use request_uri directly
                    $request_path = $request_uri;
                }
                
                @file_put_contents($logFile, "request_uri (parsed): " . $request_uri . "\n", FILE_APPEND);
                @file_put_contents($logFile, "script_dir: " . $script_dir . "\n", FILE_APPEND);
                @file_put_contents($logFile, "request_path (initial): " . $request_path . "\n", FILE_APPEND);
                
                // Remove old localhost path prefix if present (for compatibility when .htaccess not working)
                if (strpos($request_path, '/phool-delivery-platform/delivery-panel/public') === 0) {
                    $request_path = substr($request_path, strlen('/phool-delivery-platform/delivery-panel/public'));
                    @file_put_contents($logFile, "Removed localhost prefix\n", FILE_APPEND);
                }
                
                $request_path = '/' . ltrim($request_path, '/');
                if ($request_path === '//') $request_path = '/';
                
                @file_put_contents($logFile, "request_path (final): " . $request_path . "\n", FILE_APPEND);

                // Debug: log request info
                $reqLog = $baseDir . '/storage/logs/request-debug.log';
                $info = date('c') . " REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? '') . " SCRIPT_NAME=" . ($_SERVER['SCRIPT_NAME'] ?? '') . " request_path=" . $request_path . " METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n";
                @file_put_contents($reqLog, $info, FILE_APPEND);

                $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                
                // Log POST data if present
                if ($request_method === 'POST') {
                    @file_put_contents($logFile, "POST Variables: " . json_encode($_POST) . "\n", FILE_APPEND);
                    @file_put_contents($logFile, "POST keys: " . implode(', ', array_keys($_POST)) . "\n", FILE_APPEND);
                }

                // Check if this is an AJAX request
                $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

                // Route handling (moved from public/index.php)
                if ($is_ajax && preg_match('/^\/ajax\//', $request_path)) {
                    header('Content-Type: application/json');
                    $ajax_file = $publicDir . $request_path . '.php';

                    if (file_exists($ajax_file)) {
                        require_once $ajax_file;
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'AJAX endpoint not found', 'path' => $request_path]);
                    }
                } else {
                    // Public routes (no authentication required)
                    if ($request_path === '/' || $request_path === '/login') {
                        @file_put_contents($logFile, "Matched: /login route\n", FILE_APPEND);
                        @file_put_contents($logFile, "Request method: " . $request_method . "\n", FILE_APPEND);
                        
                        if (isset($_SESSION['rider_id'])) {
                            @file_put_contents($logFile, "Rider already logged in, redirecting to dashboard\n", FILE_APPEND);
                            require_once dirname(__DIR__) . '/app/helpers/url.php';
                            header('Location: ' . app_url('/dashboard'));
                            exit;
                        }

                        if ($request_method === 'POST') {
                            @file_put_contents($logFile, "POST request to /login - calling AuthController::authenticate()\n", FILE_APPEND);
                            require_once $baseDir . '/app/controllers/AuthController.php';
                            @file_put_contents($logFile, "AuthController included successfully\n", FILE_APPEND);
                            $controller = new \Phool\DeliveryPanel\Controllers\AuthController();
                            @file_put_contents($logFile, "AuthController instantiated, calling authenticate()\n", FILE_APPEND);
                            $controller->authenticate();
                            @file_put_contents($logFile, "authenticate() returned (should not reach here if redirect)\n", FILE_APPEND);
                        } else {
                            @file_put_contents($logFile, "GET request to /login - showing login form\n", FILE_APPEND);
                            require_once $baseDir . '/app/views/auth/login.php';
                            exit;
                        }
                    }

                    // Register route
                    elseif ($request_path === '/register') {
                        if (isset($_SESSION['rider_id'])) {
                            require_once dirname(__DIR__) . '/app/helpers/url.php';
                            header('Location: ' . app_url('/dashboard'));
                            exit;
                        }

                        if ($request_method === 'POST') {
                            require_once $baseDir . '/app/controllers/AuthController.php';
                            $controller = new \Phool\DeliveryPanel\Controllers\AuthController();
                            $controller->store();
                        } else {
                            require_once $baseDir . '/app/views/auth/register.php';
                            exit;
                        }
                    }

                    // Logout route
                    elseif ($request_path === '/logout') {
                        session_destroy();
                        if (isset($_COOKIE['PHPSESSID'])) {
                            setcookie('PHPSESSID', '', time() - 3600, '/');
                        }
                        require_once $baseDir . '/app/helpers/url.php';
                        header('Location: ' . app_url('/login'));
                        exit;
                    }

                    // Test endpoint for debugging
                    if ($request_path === '/ajax-test') {
                        while (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => true,
                            'message' => 'AJAX endpoint is working',
                            'session_id' => session_id(),
                            'has_session' => isset($_SESSION['rider_id']),
                            'rider_id' => $_SESSION['rider_id'] ?? null,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        exit;
                    }

                    // AJAX Routes (handle session verification internally)
                    if (preg_match('/^\/support\/ticket\/\d+\/reply$/', $request_path)) {
                        $debugInfo = [
                            'path' => $request_path,
                            'method' => $request_method,
                            'has_session' => isset($_SESSION['rider_id']),
                            'session_id' => session_id(),
                            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                            'headers' => getallheaders()
                        ];
                        error_log("DEBUG AJAX REPLY: " . json_encode($debugInfo));
                        
                        // Check authentication
                        if (!isset($_SESSION['rider_id'])) {
                            http_response_code(401);
                            header('Content-Type: application/json');
                            echo json_encode(['error' => 'Unauthorized']);
                            exit;
                        }
                        
                        if ($request_method !== 'POST') {
                            http_response_code(405);
                            header('Content-Type: application/json');
                            echo json_encode(['error' => 'Method not allowed']);
                            exit;
                        }
                        
                        // Extract ticket ID
                        preg_match('/^\/support\/ticket\/(\d+)\/reply$/', $request_path, $matches);
                        
                        // Clear all output buffers before sending headers
                        while (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        
                        // Set content type FIRST before any output
                        header('Content-Type: application/json; charset=utf-8');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');
                        header('Expires: 0');
                        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                        
                        try {
                            $input = json_decode(file_get_contents('php://input'), true);
                            $message = trim($input['message'] ?? '');
                            
                            if (empty($message)) {
                                http_response_code(400);
                                echo json_encode(['error' => 'Message cannot be empty']);
                                exit;
                            }
                            
                            if (strlen($message) > 5000) {
                                http_response_code(400);
                                echo json_encode(['error' => 'Message cannot exceed 5000 characters']);
                                exit;
                            }
                            
                            require_once $baseDir . '/app/models/Ticket.php';
                            require_once $baseDir . '/app/controllers/SupportController.php';
                            
                            $ticketModel = new \Phool\DeliveryPanel\Models\Ticket($pdo);
                            $ticketId = intval($matches[1]);
                            $riderId = $_SESSION['rider_id'];
                            
                            // Verify ticket ownership
                            $ticket = $ticketModel->getById($ticketId, $riderId);
                            if (!$ticket) {
                                http_response_code(404);
                                echo json_encode(['error' => 'Ticket not found']);
                                exit;
                            }
                            
                            // Check if ticket is closed
                            if ($ticket->status === 'closed') {
                                http_response_code(400);
                                echo json_encode(['error' => 'Cannot reply to a closed ticket']);
                                exit;
                            }
                            
                            // Add message
                            $ticketModel->addMessage($ticketId, $riderId, $message);
                            
                            http_response_code(200);
                            echo json_encode([
                                'success' => true,
                                'message' => 'Message sent successfully',
                                'timestamp' => date('Y-m-d H:i:s')
                            ]);
                        } catch (\Exception $e) {
                            error_log('Ticket Reply Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
                            http_response_code(500);
                            echo json_encode(['error' => 'Failed to send message: ' . $e->getMessage()]);
                        }
                        exit;
                    }
                    if (preg_match('/^\/support\/ticket\/\d+\/close$/', $request_path)) {
                        error_log("DEBUG: Matched ticket/close route! path={$request_path}, method={$request_method}");
                        
                        // Check authentication
                        if (!isset($_SESSION['rider_id'])) {
                            http_response_code(401);
                            header('Content-Type: application/json');
                            echo json_encode(['error' => 'Unauthorized']);
                            exit;
                        }
                        
                        if ($request_method !== 'POST') {
                            http_response_code(405);
                            header('Content-Type: application/json');
                            echo json_encode(['error' => 'Method not allowed']);
                            exit;
                        }
                        
                        // Extract ticket ID
                        preg_match('/^\/support\/ticket\/\d+\/close$/', $request_path, $matches);
                        
                        // Clear all output buffers before sending headers
                        while (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        
                        // Set content type FIRST before any output
                        header('Content-Type: application/json; charset=utf-8');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');
                        header('Expires: 0');
                        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                        
                        try {
                            require_once $baseDir . '/app/models/Ticket.php';
                            require_once $baseDir . '/app/controllers/SupportController.php';
                            
                            $ticketModel = new \Phool\DeliveryPanel\Models\Ticket($pdo);
                            $ticketId = intval($matches[1]);
                            $riderId = $_SESSION['rider_id'];
                            
                            // Verify ticket ownership
                            $ticket = $ticketModel->getById($ticketId, $riderId);
                            if (!$ticket) {
                                http_response_code(404);
                                echo json_encode(['error' => 'Ticket not found']);
                                exit;
                            }
                            
                            // Close ticket
                            $ticketModel->updateStatus($ticketId, $riderId, 'closed');
                            
                            http_response_code(200);
                            echo json_encode([
                                'success' => true,
                                'message' => 'Ticket closed successfully',
                                'new_status' => 'closed'
                            ]);
                        } catch (\Exception $e) {
                            error_log('Ticket Close Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
                            http_response_code(500);
                            echo json_encode(['error' => 'Failed to close ticket: ' . $e->getMessage()]);
                        }
                        exit;
                    }

                    // Protected routes (authentication required)
                    elseif (isset($_SESSION['rider_id'])) {
                        // Log for debugging
                        if ($request_method === 'POST' && strpos($request_path, '/support/ticket') !== false) {
                            error_log("DEBUG POST: path={$request_path}, method={$request_method}");
                        }
                        
                        // Check if this is a POST request to profile (AJAX update/password change)
                        if ($request_path === '/profile' && $request_method === 'POST') {
                            // Don't include header/footer for AJAX POST requests
                            require_once $baseDir . '/app/controllers/ProfileController.php';
                            
                            if (isset($_POST['password_update'])) {
                                $controller = new \Phool\DeliveryPanel\Controllers\ProfileController();
                                $controller->updatePassword();
                            } else {
                                $controller = new \Phool\DeliveryPanel\Controllers\ProfileController();
                                $controller->update();
                            }
                        }
                        // Handle profile picture upload
                        elseif ($request_path === '/profile/upload-picture' && $request_method === 'POST') {
                            // Don't include header/footer for AJAX POST requests
                            require_once $baseDir . '/app/controllers/ProfileController.php';
                            $controller = new \Phool\DeliveryPanel\Controllers\ProfileController();
                            $controller->uploadProfilePicture();
                        } else {
                            // Include header for all non-POST requests
                            require_once $baseDir . '/app/views/layouts/header.php';

                            // Route to appropriate view
                            if ($request_path === '/dashboard' || $request_path === '/') {
                                require_once $baseDir . '/app/controllers/DashboardController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\DashboardController();
                                $controller->index();
                            }
                            elseif ($request_path === '/orders') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->index();
                            }
                            elseif ($request_path === '/orders/assigned') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->assigned();
                            }
                            elseif (preg_match('/^\/orders\/view\/(\d+)$/', $request_path, $matches)) {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->view(intval($matches[1]));
                            }
                            elseif ($request_path === '/orders/active') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->active();
                            }
                            elseif ($request_path === '/orders/history') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->history();
                            }
                            elseif ($request_path === '/orders/completed') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->completed();
                            }
                            elseif ($request_path === '/orders/rejected') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->rejected();
                            }
                            elseif ($request_path === '/orders/all') {
                                require_once $baseDir . '/app/controllers/OrderController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\OrderController();
                                $controller->all();
                            }
                            elseif ($request_path === '/earnings' || $request_path === '/earnings/index') {
                                require_once $baseDir . '/app/controllers/EarningsController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\EarningsController();
                                $controller->index();
                            }
                            elseif ($request_path === '/earnings/statements') {
                                require_once $baseDir . '/app/views/earnings/statements.php';
                            }
                            elseif ($request_path === '/profile') {
                                require_once $baseDir . '/app/controllers/ProfileController.php';
                                $controller = new \Phool\DeliveryPanel\Controllers\ProfileController();
                                $controller->index();
                            }
                            elseif (preg_match('/^\/support\/tickets\/(\d+)$/', $request_path, $matches)) {
                                // View single ticket detail page
                                require_once $baseDir . '/app/models/Ticket.php';
                                require_once $baseDir . '/app/controllers/SupportController.php';
                                
                                $ticketId = intval($matches[1]);
                                
                                // Load header
                                require_once $baseDir . '/app/views/layouts/header.php';
                                require_once $baseDir . '/app/views/support/ticket-detail.php';
                                // Load footer
                                require_once $baseDir . '/app/views/layouts/footer.php';
                            }
                            elseif (preg_match('/^\/support\/ticket\/(\d+)$/', $request_path, $matches)) {
                                // Handle ticket detail AJAX request - MUST exit before footer is loaded
                                
                                // Clear any output buffer first
                                while (ob_get_level()) {
                                    ob_end_clean();
                                }
                                
                                header('Content-Type: application/json; charset=utf-8');
                                header('Cache-Control: no-cache, must-revalidate');
                                header('Pragma: no-cache');
                                
                                if (!isset($_SESSION['rider_id'])) {
                                    http_response_code(401);
                                    echo json_encode(['error' => 'Unauthorized - please log in']);
                                    exit;
                                }
                                
                                try {
                                    require_once $baseDir . '/app/models/Ticket.php';
                                    require_once $baseDir . '/app/controllers/SupportController.php';
                                    
                                    $controller = new \Phool\DeliveryPanel\Controllers\SupportController($pdo);
                                    $ticketId = intval($matches[1]);
                                    $result = $controller->viewTicket($ticketId);
                                    
                                    if (isset($result['error'])) {
                                        http_response_code(404);
                                    }
                                    echo json_encode($result);
                                } catch (\Exception $e) {
                                    error_log('Ticket API Error: ' . $e->getMessage());
                                    http_response_code(500);
                                    echo json_encode(['error' => 'Failed to load ticket: ' . $e->getMessage()]);
                                }
                                exit;
                            }
                            elseif ($request_path === '/support' || $request_path === '/support/tickets') {
                                require_once $baseDir . '/app/views/support/tickets.php';
                            }
                            else {
                                // Log 404 for debugging
                                @file_put_contents($baseDir . '/storage/logs/404-errors.log', 
                                    date('Y-m-d H:i:s') . " | Path: $request_path | URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n", 
                                    FILE_APPEND);
                                
                                http_response_code(404);
                                echo '<h1>404 - Page Not Found</h1>';
                                echo '<p>Requested path: ' . htmlspecialchars($request_path) . '</p>';
                                echo '<p><a href="' . app_url('/dashboard') . '">Back to Dashboard</a></p>';
                            }

                            // Load footer
                            require_once $baseDir . '/app/views/layouts/footer.php';
                        }
                    }

                    // Redirect unauthenticated users to login
                    else {
                        require_once dirname(__DIR__) . '/app/helpers/url.php';
                        header('Location: ' . app_url('/login'));
                        exit;
                    }
                }

                // Flush output buffer
                if (ob_get_level() > 0) ob_end_flush();
            }
    }
    
    $GLOBALS['app'] = new Application($config);
    define('APP_INSTANCE', true);
}

// Return app instance
return $GLOBALS['app'] ?? null;
