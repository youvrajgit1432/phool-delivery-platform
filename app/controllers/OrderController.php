<?php
// app/controllers/OrderController.php

class OrderController {
    private $db;
    private $orderModel;
    private $pathConfig;
    
    public function __construct($db) {
        $this->db = $db;
        $this->orderModel = new Order($db);
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public function success($order_id) {
        try {
            // Validate order ID
            if (!is_numeric($order_id) || $order_id <= 0) {
                error_log("Invalid order ID format: " . $order_id);
                header('Location: ' . $this->pathConfig->url(''));
                exit;
            }
            
            // Get order details
            $order = $this->orderModel->readOne($order_id);
            
            if ($order) {
                return [
                    'order' => $order,
                    'page_title' => 'Order Successful - Phool Delivery',
                    'pathConfig' => $this->pathConfig
                ];
            } else {
                // Order not found - log for internal use only
                error_log("Order not found with ID: " . $order_id);
                header('Location: ' . $this->pathConfig->url(''));
                exit;
            }
        } catch (Exception $e) {
            // Log the actual error for internal debugging
            error_log("Error in OrderController::success - " . $e->getMessage());
            
            // Redirect user without exposing error details
            header('Location: ' . $this->pathConfig->url('error'));
            exit;
        }
    }
    
    public function track() {
        try {
            $tracking_id = $_GET['id'] ?? '';
            $phone = $_GET['phone'] ?? '';
            
            $order = null;
            
            // Validate input parameters
            if (!empty($tracking_id)) {
                if (!is_numeric($tracking_id) || $tracking_id <= 0) {
                    return [
                        'order' => null,
                        'tracking_id' => $tracking_id,
                        'phone' => $phone,
                        'page_title' => 'Track Your Order - Phool Delivery',
                        'error' => 'Invalid tracking ID format',
                        'pathConfig' => $this->pathConfig
                    ];
                }
                
                // Get order by ID
                $order = $this->orderModel->readOne($tracking_id);
            } else if (!empty($phone)) {
                // Validate phone number format
                if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
                    return [
                        'order' => null,
                        'tracking_id' => $tracking_id,
                        'phone' => $phone,
                        'page_title' => 'Track Your Order - Phool Delivery',
                        'error' => 'Invalid phone number format',
                        'pathConfig' => $this->pathConfig
                    ];
                }
                
                // In a real application, you would search orders by phone
                // This is a simplified version
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone search functionality is currently unavailable'
                ]);
                return;
            }
            
            return [
                'order' => $order,
                'tracking_id' => $tracking_id,
                'phone' => $phone,
                'page_title' => 'Track Your Order - Phool Delivery',
                'pathConfig' => $this->pathConfig
            ];
            
        } catch (Exception $e) {
            // Log the actual error for internal debugging
            error_log("Error in OrderController::track - " . $e->getMessage());
            
            return [
                'order' => null,
                'tracking_id' => $tracking_id ?? '',
                'phone' => $phone ?? '',
                'page_title' => 'Track Your Order - Phool Delivery',
                'error' => 'Unable to process your request at this time',
                'pathConfig' => $this->pathConfig
            ];
        }
    }
    
    public function getStatus($order_id) {
        try {
            // Validate order ID
            if (!is_numeric($order_id) || $order_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid order reference'
                ]);
                return;
            }
            
            $order = $this->orderModel->readOne($order_id);
            
            if ($order) {
                echo json_encode([
                    'success' => true,
                    'order_status' => $order['order_status'],
                    'payment_status' => $order['payment_status']
                ]);
            } else {
                // Log for internal use
                error_log("Order not found in getStatus: " . $order_id);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Order not found'
                ]);
            }
            
        } catch (Exception $e) {
            // Log the actual error for internal debugging
            error_log("Error in OrderController::getStatus - " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve order status'
            ]);
        }
    }
}

// Additional error handling configuration
class ErrorHandler {
    private $pathConfig;
    
    public function __construct() {
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public static function initialize() {
        // Disable display errors in production
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        
        // Get path configuration for log file path
        $pathConfig = PathConfig::getInstance();
        
        // Log errors to file - use proper file system path
        ini_set('log_errors', '1');
        $logPath = $pathConfig->filePath('admin_storage') . '/logs/php_errors.log';
        
        // Ensure log directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        ini_set('error_log', $logPath);
        
        // Set error reporting level
        error_reporting(E_ALL);
        
        // Register custom error handler
        $handler = new self();
        set_error_handler([$handler, 'handleError']);
        set_exception_handler([$handler, 'handleException']);
    }
    
    public function handleError($errno, $errstr, $errfile, $errline) {
        // Log error details
        error_log("Error: [$errno] $errstr in $errfile on line $errline");
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    public function handleException($exception) {
        // Log exception details
        error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        
        // Send generic error response
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        
        // In production, show generic error page
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode([
                'success' => false,
                'message' => 'An unexpected error occurred'
            ]);
        } else {
            // Use PathConfig to get correct error page path
            $errorPage = $this->pathConfig->get('root_dir') . '/app/views/500.php';
            if (file_exists($errorPage)) {
                include $errorPage;
            } else {
                // Fallback error message
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Error - Phool Delivery</title>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                        .error-container { max-width: 500px; margin: 0 auto; }
                        .error-code { font-size: 72px; color: #dc3545; margin-bottom: 20px; }
                        .error-message { font-size: 18px; margin-bottom: 30px; }
                        .home-link { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <div class="error-code">500</div>
                        <div class="error-message">Sorry, something went wrong. Our team has been notified.</div>
                        <a href="' . $this->pathConfig->url('') . '" class="home-link">Go Back Home</a>
                    </div>
                </body>
                </html>';
            }
        }
        
        exit;
    }
}

// Initialize error handling
ErrorHandler::initialize();
?>