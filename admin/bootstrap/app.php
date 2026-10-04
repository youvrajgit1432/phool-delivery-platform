<?php
// admin/bootstrap/app.php - ENHANCED VERSION

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
require_once __DIR__ . '/../config/session.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Include necessary classes
require_once __DIR__ . '/../app/services/EmailService.php';
require_once __DIR__ . '/../app/services/SmsService.php';
require_once __DIR__ . '/../app/services/MessageService.php';

// Include Firebase Configuration FIRST
require_once __DIR__ . '/../app/config/firebase-config.php';

// Include Push Notification Service
require_once __DIR__ . '/../app/services/PushNotificationService.php';

// Database connection
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

// Initialize database (if needed)
function initDatabase($pdo) {
    // Your database initialization code here
}

// Email service
function getEmailService() {
    static $emailService = null;
    
    if ($emailService === null) {
        $pdo = getDBConnection();
        $emailService = new EmailService($pdo);
    }
    
    return $emailService;
}

// SMS service
function getSmsService() {
    static $smsService = null;
    
    if ($smsService === null) {
        $pdo = getDBConnection();
        $smsService = new SmsService($pdo);
    }
    
    return $smsService;
}


function isProductAvailableInCity($product_id, $city_id) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT is_available 
        FROM product_city_availability 
        WHERE product_id = ? AND city_id = ?
    ");
    $stmt->execute([$product_id, $city_id]);
    $result = $stmt->fetch();
    
    // If no specific rule exists, assume product is available
    return $result ? (bool)$result['is_available'] : true;
}


/**
 * Get available products for a specific city
 */
function getAvailableProductsForCity($city_id) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM products p
        LEFT JOIN product_city_availability pca ON p.id = pca.product_id AND pca.city_id = ?
        WHERE p.status = 'active' 
        AND (pca.is_available IS NULL OR pca.is_available = 1)
        ORDER BY p.name_en
    ");
    $stmt->execute([$city_id]);
    return $stmt->fetchAll();
}


// Message service
function getMessageService() {
    static $messageService = null;
    
    if ($messageService === null) {
        $pdo = getDBConnection();
        $messageService = new MessageService($pdo);
    }
    
    return $messageService;
}

// Push Notification service
function getPushNotificationService() {
    static $pushService = null;
    
    if ($pushService === null) {
        $pdo = getDBConnection();
        $pushService = new AdminPushNotificationService($pdo);
    }
    
    return $pushService;
}

// Firebase Configuration
function getFirebaseConfig() {
    return FirebaseConfig::getInstance();
}

// Helper functions for orders
function sendOrderConfirmationEmail($order) {
    try {
        $emailService = getEmailService();
        return $emailService->sendOrderConfirmation($order['id']);
    } catch (Exception $e) {
        error_log("Error sending order confirmation email: " . $e->getMessage());
        return false;
    }
}

function sendOrderStatusEmail($order_id, $status) {
    try {
        $emailService = getEmailService();
        return $emailService->sendOrderStatusUpdate($order_id, $status);
    } catch (Exception $e) {
        error_log("Error sending order status email: " . $e->getMessage());
        return false;
    }
}

function sendOrderConfirmationSMS($order) {
    // Implement SMS sending functionality
    $message = "Your order #" . $order['order_number'] . " has been confirmed. Total: Rs. " . number_format($order['total_amount'], 2) . ". Thank you!";
    
    // Example using a hypothetical SMS function
    // sendSMS($order['phone'], $message);
}

function calculateOrderWeight($order_id) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT SUM(oi.quantity * p.weight) as total_weight 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $result = $stmt->fetch();
    
    return $result['total_weight'] ?? 0;
}

// Load additional helpers if they exist
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}
function getCategoryHierarchy($pdo, $parent_id = NULL) {
    $query = "SELECT * FROM categories WHERE parent_id " . ($parent_id ? "= ?" : "IS NULL") . " ORDER BY sort_order, name_en";
    $stmt = $pdo->prepare($query);
    $stmt->execute($parent_id ? [$parent_id] : []);
    $categories = $stmt->fetchAll();
    
    $result = [];
    foreach ($categories as $category) {
        $category['subcategories'] = getCategoryHierarchy($pdo, $category['id']);
        $result[] = $category;
    }
    
    return $result;
}

// Function to get all products in a category (including subcategories)
function getProductsByCategory($pdo, $category_id, $include_subcategories = true) {
    $category_ids = [$category_id];
    
    if ($include_subcategories) {
        // Get all subcategory IDs
        $subcategories = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
        $subcategories->execute([$category_id]);
        while ($subcat = $subcategories->fetch(PDO::FETCH_ASSOC)) {
            $category_ids[] = $subcat['id'];
            // Recursively get deeper subcategories
            $deeper_subcats = getProductsByCategory($pdo, $subcat['id'], true);
            foreach ($deeper_subcats['category_ids'] as $deeper_id) {
                $category_ids[] = $deeper_id;
            }
        }
    }
    
    // Get products
    $placeholders = str_repeat('?,', count($category_ids) - 1) . '?';
    $query = "SELECT DISTINCT p.* FROM products p 
              JOIN product_category_map pcm ON p.id = pcm.product_id 
              WHERE pcm.category_id IN ($placeholders) AND p.status = 'active'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($category_ids);
    $products = $stmt->fetchAll();
    
    return [
        'category_ids' => array_unique($category_ids),
        'products' => $products
    ];
}

// Function to get breadcrumb for a category
function getCategoryBreadcrumb($pdo, $category_id) {
    $breadcrumb = [];
    $current_id = $category_id;
    
    while ($current_id) {
        $query = "SELECT id, name_en, name_ne, parent_id FROM categories WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_id]);
        $category = $stmt->fetch();
        
        if ($category) {
            array_unshift($breadcrumb, $category);
            $current_id = $category['parent_id'];
        } else {
            break;
        }
    }
    
    return $breadcrumb;
}
?>