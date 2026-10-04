<?php
// app/bootstrap/app.php - FIXED VERSION WITH CATEGORY ROUTE

// CRITICAL: Set UTF-8 encoding BEFORE anything else
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Define ROOT_PATH constant first
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));

// Set timezone
date_default_timezone_set('Asia/Kathmandu');

// ENHANCED: Start session early with proper configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 24 hours
        'read_and_close'  => false,
    ]);
}

// Initialize session arrays to prevent undefined errors
$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['message_count'] = $_SESSION['message_count'] ?? 0;
$_SESSION['customer_id'] = $_SESSION['customer_id'] ?? null;

// Include session configuration
require_once __DIR__ . '/../config/session.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Include Path Configuration
require_once __DIR__ . '/../config/PathConfig.php';

// Initialize Path Config
$pathConfig = PathConfig::getInstance();

// Make pathConfig available globally
function pathConfig() {
    return PathConfig::getInstance();
}

// Include configurations
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Router.php';

// Include Language Helper
require_once __DIR__ . '/../helpers/language.php';

// Include Cache Helper
require_once __DIR__ . '/../helpers/cache.php';

// Include models
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/OTP.php';
require_once __DIR__ . '/../models/Cart.php'; 
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/CustomerAddress.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Media.php';
require_once __DIR__ . '/../models/Security.php';
require_once __DIR__ . '/../models/ViewOrderModel.php';
require_once __DIR__ . '/../models/LogModel.php'; 
require_once __DIR__ . '/../models/LogOTPModel.php';
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/PersistentLogin.php';

// Include services
require_once __DIR__ . '/../services/SparrowSMSService.php';

// Include controllers
require_once __DIR__ . '/../controllers/AccountController.php';
require_once __DIR__ . '/../controllers/HomeController.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/CheckoutController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/LogController.php';
require_once __DIR__ . '/../controllers/SocialAuthController.php';
require_once __DIR__ . '/../controllers/DirectCheckoutController.php';
require_once __DIR__ . '/../controllers/MessageController.php';
require_once __DIR__ . '/../controllers/MediaController.php';
require_once __DIR__ . '/../controllers/OTPController.php';
require_once __DIR__ . '/../controllers/SecurityController.php';
require_once __DIR__ . '/../controllers/ViewOrderController.php';
require_once __DIR__ . '/../controllers/LogOTPController.php';
require_once __DIR__ . '/../controllers/LanguageController.php';
require_once __DIR__ . '/../controllers/ReviewController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../models/EmailService.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../models/Profile.php';
require_once __DIR__ . '/../models/PushNotificationService.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize Persistent Login Handler
    $persistentLogin = new PersistentLogin($db);
    
    // ENHANCED: Check for persistent login if no active session
    if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
        $persistentLogin->checkPersistentLogin();
    }

    // Initialize Language Helper
    LanguageHelper::initialize();

    // Initialize router
    $router = new Router();

    // Define routes
    $router->addRoute('', ['controller' => 'home', 'action' => 'index']);
    $router->addRoute('/home', ['controller' => 'home', 'action' => 'index']);
    $router->addRoute('/products', ['controller' => 'product', 'action' => 'index']);
    $router->addRoute('/product/{id}', ['controller' => 'product', 'action' => 'detail']);
    $router->addRoute('/product/review/add', ['controller' => 'product', 'action' => 'addReview']);
    
    // NEW: Category route with both ID and slug
    $router->addRoute('/category/{id}/{slug}', ['controller' => 'product', 'action' => 'byCategory']);
    
    // Keep the old category route for backward compatibility
    $router->addRoute('/category/{category}', ['controller' => 'product', 'action' => 'byCategory']);
    
    $router->addRoute('/cart', ['controller' => 'cart', 'action' => 'index']);
    $router->addRoute('/cart/add', ['controller' => 'cart', 'action' => 'add']);
    $router->addRoute('/cart/update', ['controller' => 'cart', 'action' => 'update']);
    $router->addRoute('/cart/remove', ['controller' => 'cart', 'action' => 'remove']);
    $router->addRoute('/cart/clear', ['controller' => 'cart', 'action' => 'clear']);
    $router->addRoute('/cart/count', ['controller' => 'cart', 'action' => 'getCount']);
    
    // Cart selection routes
    $router->addRoute('/cart/update-selection', ['controller' => 'cart', 'action' => 'updateSelection']);
    $router->addRoute('/cart/checkout-selected', ['controller' => 'cart', 'action' => 'checkoutSelected']);
    $router->addRoute('/cart/get-selected', ['controller' => 'cart', 'action' => 'getSelectedForCheckout']);
    
    // Checkout routes
    $router->addRoute('/checkout', ['controller' => 'checkout', 'action' => 'index']);
    $router->addRoute('/checkout/process', ['controller' => 'checkout', 'action' => 'process']);
    $router->addRoute('/checkout/verify', ['controller' => 'checkout', 'action' => 'verify']);
    $router->addRoute('/checkout/set-verification-phone', ['controller' => 'checkout', 'action' => 'setVerificationPhone']);
    $router->addRoute('/checkout/clear-verification-phone', ['controller' => 'checkout', 'action' => 'clearVerificationPhone']);
    $router->addRoute('/checkout/get-qr-image', ['controller' => 'checkout', 'action' => 'getQrImage']);
    $router->addRoute('/success', ['controller' => 'checkout', 'action' => 'success']);
    
    // Verification routes
    $router->addRoute('/account/verify', ['controller' => 'checkout', 'action' => 'verify']);
    
    // OTP routes (for checkout)
    $router->addRoute('/otp/send', ['controller' => 'otp', 'action' => 'apiSendOTP']);
    $router->addRoute('/otp/verify', ['controller' => 'otp', 'action' => 'apiVerifyOTP']);
    $router->addRoute('/otp/resend', ['controller' => 'otp', 'action' => 'apiResendOTP']);
    
    // Customer verification routes (for guest users)
    $router->addRoute('/customer/check-phone-verified', ['controller' => 'phoneVerification', 'action' => 'checkPhoneVerified']);
    $router->addRoute('/customer/store-guest-verified-phone', ['controller' => 'phoneVerification', 'action' => 'storeGuestVerifiedPhone']);
    
    // Language routes
    $router->addRoute('/change-language', ['controller' => 'language', 'action' => 'changeLanguage']);
    $router->addRoute('/api/language/set', ['controller' => 'language', 'action' => 'setLanguage']);
    $router->addRoute('/api/language/skip', ['controller' => 'language', 'action' => 'skipLanguage']);
    $router->addRoute('/api/language/current', ['controller' => 'language', 'action' => 'getCurrentLanguage']);
    
    $router->addRoute('/track', ['controller' => 'order', 'action' => 'track']);
    
    // Login routes
    $router->addRoute('/login', ['controller' => 'log', 'action' => 'login']);
    $router->addRoute('/log/login-process', ['controller' => 'log', 'action' => 'processLogin']);
    
    // Enhanced logout route - ADDED AT THE TOP LEVEL
    $router->addRoute('/logout', ['controller' => 'log', 'action' => 'logout']);
    
    // OTP Login routes - SEPARATE OTP VERIFICATION PAGE
    $router->addRoute('/otp/login', ['controller' => 'logotp', 'action' => 'showOTPLogin']);
    $router->addRoute('/otp/send-otp', ['controller' => 'logotp', 'action' => 'sendOTP']);
    $router->addRoute('/otp/verify-otp', ['controller' => 'logotp', 'action' => 'showVerifyOTP']);
    $router->addRoute('/otp/verify-otp-process', ['controller' => 'logotp', 'action' => 'verifyOTP']);
    $router->addRoute('/otp/resend-otp', ['controller' => 'logotp', 'action' => 'resendOTP']);
    
    // Signup routes
    $router->addRoute('/signup', ['controller' => 'auth', 'action' => 'signup']);
    $router->addRoute('/auth/signup-process', ['controller' => 'auth', 'action' => 'processSignup']);
    
    // Direct checkout route
    $router->addRoute('/direct-checkout', ['controller' => 'directcheckout', 'action' => 'process']);
    
    // Social auth routes
    $router->addRoute('/auth/google', ['controller' => 'social', 'action' => 'googleLogin']);
    $router->addRoute('/auth/google-callback', ['controller' => 'social', 'action' => 'googleCallback']);

    // Account routes
    $router->addRoute('/account/address-change', ['controller' => 'account', 'action' => 'addressChange']);
    $router->addRoute('/account/update-address', ['controller' => 'account', 'action' => 'updateAddress']);
    $router->addRoute('/account', ['controller' => 'account', 'action' => 'dashboard']);
    $router->addRoute('/account/addresses', ['controller' => 'account', 'action' => 'addresses']);
    $router->addRoute('/account/orders', ['controller' => 'vieworder', 'action' => 'orders']);
    $router->addRoute('/account/wishlist', ['controller' => 'account', 'action' => 'wishlist']);
    $router->addRoute('/account/payment-methods', ['controller' => 'account', 'action' => 'paymentMethods']);
    $router->addRoute('/account/preferences', ['controller' => 'account', 'action' => 'preferences']);
    $router->addRoute('/account/security', ['controller' => 'security', 'action' => 'index']);
    
    // Notification settings route
    $router->addRoute('/account/notifications', ['controller' => 'account', 'action' => 'notifications']);
    $router->addRoute('/account/profile', ['controller' => 'profile', 'action' => 'profile']);

    // Support routes
    $router->addRoute('/account/support', ['controller' => 'account', 'action' => 'support']);
    $router->addRoute('/account/support-process', ['controller' => 'account', 'action' => 'supportProcess']);
    $router->addRoute('/account/submit-interest', ['controller' => 'account', 'action' => 'submitInterest']);
 
    // Profile routes
    $router->addRoute('/account/profile', ['controller' => 'profile', 'action' => 'profile']);
    $router->addRoute('/account/profile/send-otp', ['controller' => 'profile', 'action' => 'sendVerificationOTP']);
    $router->addRoute('/account/profile/verify-otp', ['controller' => 'profile', 'action' => 'verifyEmailOTP']);
    $router->addRoute('/account/profile/check-verification', ['controller' => 'profile', 'action' => 'checkVerificationStatus']);

    // Security routes
    $router->addRoute('/security/change-password', ['controller' => 'security', 'action' => 'changePassword']);
    $router->addRoute('/security/toggle-2fa', ['controller' => 'security', 'action' => 'toggle2FA']);
    $router->addRoute('/security/login-history', ['controller' => 'security', 'action' => 'getLoginHistory']);

    // Message routes - FIXED: Remove authentication check from router since controller handles it
    $router->addRoute('/messages', ['controller' => 'message', 'action' => 'index']);
    $router->addRoute('/api/messages/unread-count', ['controller' => 'message', 'action' => 'getUnreadCount']);
    $router->addRoute('/api/messages/mark-read', ['controller' => 'message', 'action' => 'markAsRead']);
    $router->addRoute('/api/messages/recent', ['controller' => 'message', 'action' => 'getRecentMessages']);
    $router->addRoute('/api/messages/mark-all-read', ['controller' => 'message', 'action' => 'markAllAsRead']);
    $router->addRoute('/api/messages/delete', ['controller' => 'message', 'action' => 'deleteMessage']);

    // Media routes
    $router->addRoute('/media', ['controller' => 'media', 'action' => 'index']);
    $router->addRoute('/media/{id}', ['controller' => 'media', 'action' => 'view']);
    
    // Password change routes
    $router->addRoute('/otp/change-password', ['controller' => 'logotp', 'action' => 'showChangePassword']);
    $router->addRoute('/otp/process-password-change', ['controller' => 'logotp', 'action' => 'processPasswordChange']);
    
    // Delivery fee API route
    $router->addRoute('/checkout/get-delivery-fee', ['controller' => 'checkout', 'action' => 'getDeliveryFeeApi']);

    // API routes
    $router->addRoute('/api/products', ['controller' => 'product', 'action' => 'apiIndex']);
    $router->addRoute('/api/product/{id}', ['controller' => 'product', 'action' => 'apiDetail']);
    $router->addRoute('/api/verify-otp', ['controller' => 'checkout', 'action' => 'apiVerifyOtp']);
    $router->addRoute('/api/resend-otp', ['controller' => 'checkout', 'action' => 'apiResendOtp']);

    // Review routes
    $router->addRoute('/reviews', ['controller' => 'review', 'action' => 'index']);
    $router->addRoute('/reviews/write', ['controller' => 'review', 'action' => 'write']);
    $router->addRoute('/reviews/write/{product_id}', ['controller' => 'review', 'action' => 'writeWithProduct']);
    $router->addRoute('/reviews/my-reviews', ['controller' => 'review', 'action' => 'myReviews']);
    $router->addRoute('/reviews/submit', ['controller' => 'review', 'action' => 'submit']);
    $router->addRoute('/api/reviews/product/{product_id}', ['controller' => 'review', 'action' => 'apiGetProductReviews']);
    $router->addRoute('/api/reviews/get-products', ['controller' => 'review', 'action' => 'apiGetProductsForReview']);

    // City routes
    $router->addRoute('/api/city/set', ['controller' => 'city', 'action' => 'setCity']);
    $router->addRoute('/api/city/skip', ['controller' => 'city', 'action' => 'skipCity']);
    $router->addRoute('/api/city/current', ['controller' => 'city', 'action' => 'getCurrentCity']);

    // Notification API routes
    $router->addRoute('/api/notifications/set', ['controller' => 'notification', 'action' => 'setNotifications']);
    $router->addRoute('/api/notifications/skip', ['controller' => 'notification', 'action' => 'skipNotifications']);
    $router->addRoute('/api/notifications/current', ['controller' => 'notification', 'action' => 'getCurrentNotifications']);

    // Push Notification API routes
    $router->addRoute('/api/notifications/register-device', ['controller' => 'notification', 'action' => 'registerDevice']);
    $router->addRoute('/api/notifications/unregister-device', ['controller' => 'notification', 'action' => 'unregisterDevice']);
    $router->addRoute('/api/notifications/device-status', ['controller' => 'notification', 'action' => 'getDeviceStatus']);
    $router->addRoute('/api/notifications/vapid-key', ['controller' => 'notification', 'action' => 'getVapidKey']);
    $router->addRoute('/api/notifications/log-delivery', ['controller' => 'notification', 'action' => 'logNotificationDelivery']);
    $router->addRoute('/api/notifications/mark-read', ['controller' => 'notification', 'action' => 'markNotificationRead']);
    $router->addRoute('/api/notifications/update-subscription', ['controller' => 'notification', 'action' => 'updateSubscription']);
    $router->addRoute('/api/notifications/auto-enable', ['controller' => 'notification', 'action' => 'autoEnable']);

    // Notification settings routes
    $router->addRoute('/account/notifications/settings', ['controller' => 'notification', 'action' => 'settings']);
    $router->addRoute('/api/notifications/update-settings', ['controller' => 'notification', 'action' => 'updateSettings']);

    // Order status update webhook
    $router->addRoute('/api/notifications/order-status-update', ['controller' => 'notification', 'action' => 'sendOrderStatusUpdate']);
    $router->addRoute('/api/notifications/force-register-device', ['controller' => 'notification', 'action' => 'forceRegisterDevice']);

    // Initialize controllers
    $homeController = new HomeController($db);
    $productController = new ProductController($db);
    $cartController = new CartController($db);
    $checkoutController = new CheckoutController($db);
    $orderController = new OrderController($db);
    $authController = new AuthController($db);
    $logController = new LogController($db);
    $socialAuthController = new SocialAuthController($db);
    $directCheckoutController = new DirectCheckoutController($db);
    $accountController = new AccountController($db);
    $messageController = new MessageController($db);
    $mediaController = new MediaController($db);
    $otpController = new OTPController($db);
    $securityController = new SecurityController($db);
    $viewOrderController = new ViewOrderController($db);
    $logOTPController = new LogOTPController($db);
    $languageController = new LanguageController($db);
    $reviewController = new ReviewController($db);
    $profileController = new ProfileController($db);

    // Include and initialize CityController and NotificationController
    require_once __DIR__ . '/../controllers/CityController.php';
    require_once __DIR__ . '/../controllers/NotificationController.php';
    
    $cityController = new CityController($db); 
    $notificationController = new NotificationController($db);

    // Dispatch the request
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/');

    // Remove base path if exists (for subdirectory installations)
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    if ($basePath != '/' && strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }

    // Get the route
    $route = $router->matchRoute($uri);

    if ($route) {
        $controllerName = $route['controller'];
        $action = $route['action'];
        $params = $route['params'] ?? [];
        
        $data = [];
        
        // FIXED: Remove authentication check for message controller since it handles it internally
        $protectedRoutes = ['account', 'security', 'vieworder', 'profile'];
        if (in_array($controllerName, $protectedRoutes) && !isset($_SESSION['customer_id'])) {
            // Redirect to login page for truly protected routes
            $pathConfig = PathConfig::getInstance();
            header('Location: ' . $pathConfig->url('login'));
            exit;
        }
        
        switch ($controllerName) {
            case 'home':
                if ($action === 'index') {
                    $data = $homeController->index();
                }
                break;
                
            case 'city':
                switch ($action) {
                    case 'setCity':
                        $cityController->setCity();
                        exit;
                    case 'skipCity':
                        $cityController->skipCity();
                        exit;
                    case 'getCurrentCity':
                        $cityController->getCurrentCity();
                        exit;
                }
                break;
                
            case 'notification':
                switch ($action) {
                    case 'autoEnable':
                        $notificationController->autoEnable();
                        exit;
                    case 'setNotifications':
                        $notificationController->setNotifications();
                        exit;
                    case 'skipNotifications':
                        $notificationController->skipNotifications();
                        exit;
                    case 'getCurrentNotifications':
                        $notificationController->getCurrentNotifications();
                        exit;
                    case 'updateSettings':
                        $notificationController->updateSettings();
                        exit;
                    case 'settings':
                        $data = $notificationController->settings();
                        break;
                    case 'registerDevice':
                        $notificationController->registerDevice();
                        exit;
                    case 'unregisterDevice':
                        $notificationController->unregisterDevice();
                        exit;
                    case 'getDeviceStatus':
                        $notificationController->getDeviceStatus();
                        exit;
                    case 'getVapidKey':
                        $notificationController->getVapidKey();
                        exit;
                    case 'logNotificationDelivery':
                        $notificationController->logNotificationDelivery();
                        exit;
                    case 'markNotificationRead':
                        $notificationController->markNotificationRead();
                        exit;
                    case 'updateSubscription':
                        $notificationController->updateSubscription();
                        exit;
                    case 'sendOrderStatusUpdate':
                        $notificationController->sendOrderStatusUpdate();
                        exit;
                    case 'forceRegisterDevice':
                        $notificationController->forceRegisterDevice();
                        exit;
                }
                break;

            case 'profile':
                if ($action === 'profile') {
                    $data = $profileController->profile();
                } else if ($action === 'verifyEmail') {
                    $data = $profileController->verifyEmail();
                } else if ($action === 'sendVerificationOTP') {
                    $profileController->sendVerificationOTP();
                    exit;
                } else if ($action === 'verifyEmailOTP') {
                    $profileController->verifyEmailOTP();
                    exit;
                } else if ($action === 'checkVerificationStatus') {
                    $profileController->checkVerificationStatus();
                    exit;
                }
                break;

            case 'product':
                if ($action === 'index') {
                    $data = $productController->index();
                } elseif ($action === 'detail') {
                    $productId = $params['id'] ?? null;
                    if (!$productId) {
                        throw new Exception("Product ID is required");
                    }
                    $data = $productController->detail($productId);
                } elseif ($action === 'byCategory') {
                    $categoryId = $params['id'] ?? $params['category'] ?? null;
                    if (!$categoryId) {
                        throw new Exception("Category ID is required");
                    }
                    $data = $productController->byCategory($categoryId);
                    
                    // Special handling for category pages
                    if (isset($data['view_file'])) {
                        // Extract data for view
                        if (is_array($data)) {
                            extract($data);
                        }
                        
                        // Include the specific category view
                        $viewPath = __DIR__ . '/../views/products/' . $view_file;
                        if (file_exists($viewPath)) {
                            include_once __DIR__ . '/../views/layouts/header.php';
                            include $viewPath;
                            include_once __DIR__ . '/../views/layouts/footer.php';
                            exit;
                        } else {
                            // Fallback to template
                            include_once __DIR__ . '/../views/layouts/header.php';
                            include __DIR__ . '/../views/products/category-template.php';
                            include_once __DIR__ . '/../views/layouts/footer.php';
                            exit;
                        }
                    }
                } elseif ($action === 'apiIndex') {
                    $productController->apiIndex();
                    exit;
                } elseif ($action === 'apiDetail') {
                    $productId = $params['id'] ?? null;
                    if (!$productId) {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['error' => 'Product ID is required']);
                        exit;
                    }
                    $productController->apiDetail($productId);
                    exit;
                } elseif ($action === 'addReview') {
                    $productController->addReview();
                    exit;
                }
                break;
                
            case 'review':
                switch ($action) {
                    case 'index':
                        $data = $reviewController->index();
                        break;
                    case 'write':
                        $data = $reviewController->write();
                        break;
                    case 'writeWithProduct':
                        $productId = $params['product_id'] ?? null;
                        if (!$productId) {
                            header('Location: /reviews/write');
                            exit;
                        }
                        $data = $reviewController->writeWithProduct($productId);
                        break;
                    case 'myReviews':
                        $data = $reviewController->myReviews();
                        break;
                    case 'submit':
                        $reviewController->submit();
                        exit;
                    case 'apiGetProductReviews':
                        $productId = $params['product_id'] ?? null;
                        if (!$productId) {
                            header('Content-Type: application/json');
                            http_response_code(400);
                            echo json_encode(['error' => 'Product ID is required']);
                            exit;
                        }
                        $reviewController->apiGetProductReviews($productId);
                        exit;
                    case 'apiGetProductsForReview':
                        $reviewController->apiGetProductsForReview();
                        exit;
                }
                break;
                
            case 'cart':
                switch ($action) {
                    case 'index':
                        $data = $cartController->index();
                        break;
                    case 'add':
                        $cartController->add();
                        exit;
                    case 'update':
                        $cartController->update();
                        exit;
                    case 'remove':
                        $cartController->remove();
                        exit;
                    case 'clear':
                        $cartController->clear();
                        exit;
                    case 'getCount':
                        $cartController->getCount();
                        exit;
                    case 'updateSelection':
                        $cartController->updateSelection();
                        exit;
                    case 'checkoutSelected':
                        $cartController->checkoutSelected();
                        exit;
                    case 'getSelectedForCheckout':
                        $cartController->getSelectedForCheckout();
                        exit;
                }
                break;
                
            case 'checkout':
                switch ($action) {
                    case 'index':
                        $data = $checkoutController->index();
                        break;
                    case 'process':
                        $checkoutController->process();
                        exit;
                    case 'verify':
                        $data = $checkoutController->verify();
                        break;
                    case 'setVerificationPhone':
                        $checkoutController->setVerificationPhone();
                        exit;
                    case 'clearVerificationPhone':
                        $checkoutController->clearVerificationPhone();
                        exit;
                    case 'getQrImage':
                        $checkoutController->getQrImage();
                        exit;
                    case 'apiVerifyOtp':
                        $checkoutController->apiVerifyOtp();
                        exit;
                    case 'apiResendOtp':
                        $checkoutController->apiResendOtp();
                        exit;
                    case 'success':
                        $data = $checkoutController->success();
                        break;
                    case 'getDeliveryFeeApi':
                        $checkoutController->getDeliveryFeeApi();
                        exit;
                }
                break;
                
            case 'otp':
                switch ($action) {
                    case 'apiSendOTP':
                        $otpController->apiSendOTP();
                        exit;
                    case 'apiVerifyOTP':
                        $otpController->apiVerifyOTP();
                        exit;
                    case 'apiResendOTP':
                        $otpController->apiResendOTP();
                        exit;
                }
                break;
                
            case 'language':
                switch ($action) {
                    case 'changeLanguage':
                        $data = $languageController->changeLanguage();
                        break;
                    case 'setLanguage':
                        $languageController->setLanguage();
                        exit;
                    case 'skipLanguage':
                        $languageController->skipLanguage();
                        exit;
                    case 'getCurrentLanguage':
                        $languageController->getCurrentLanguage();
                        exit;
                }
                break;
                
            case 'order':
                if ($action === 'track') {
                    $data = $orderController->track();
                }
                break;
                
            case 'log':
                switch ($action) {
                    case 'login':
                        $data = $logController->login();
                        break;
                    case 'processLogin':
                        $logController->processLogin();
                        exit;
                    case 'logout':
                        $logController->logout();
                        exit;
                }
                break;
                
            case 'logotp':
                switch ($action) {
                    case 'showOTPLogin':
                        $data = $logOTPController->showOTPLogin();
                        break;
                    case 'sendOTP':
                        $logOTPController->sendOTP();
                        exit;
                    case 'showVerifyOTP':
                        $data = $logOTPController->showVerifyOTP();
                        break;
                    case 'verifyOTP':
                        $logOTPController->verifyOTP();
                        exit;
                    case 'resendOTP':
                        $logOTPController->resendOTP();
                        exit;
                    case 'showChangePassword':
                        $data = $logOTPController->showChangePassword();
                        break;
                    case 'processPasswordChange':
                        $logOTPController->processPasswordChange();
                        exit;
                }
                break;
                
            case 'auth':
                switch ($action) {
                    case 'signup':
                        $data = $authController->signup();
                        break;
                    case 'processSignup':
                        $authController->processSignup();
                        exit;
                }
                break;
                
            case 'social':
                switch ($action) {
                    case 'googleLogin':
                        $socialAuthController->googleLogin();
                        exit;
                    case 'googleCallback':
                        $socialAuthController->googleCallback();
                        exit;
                }
                break;
                
            case 'directcheckout':
                if ($action === 'process') {
                    $directCheckoutController->process();
                    exit;
                }
                break;
                
            case 'account':
                switch ($action) {
                    case 'dashboard':
                        $data = $accountController->dashboard();
                        break;
                    case 'profile':
                        $data = $accountController->profile();
                        break;
                    case 'addresses':
                        $data = $accountController->addresses();
                        break;
                    case 'wishlist':
                        $data = $accountController->wishlist();
                        break;
                    case 'paymentMethods':
                        $data = $accountController->paymentMethods();
                        break;
                    case 'preferences':
                        $data = $accountController->preferences();
                        break;
                    case 'notifications':
                        $data = $accountController->notifications();
                        break;
                    case 'support':
                        $data = $accountController->support();
                        break;
                    case 'supportProcess':
                        $accountController->supportProcess();
                        exit;
                    case 'submitInterest':
                        $accountController->submitInterest();
                        exit;
                    case 'addressChange':
                        $data = $accountController->addressChange();
                        break;
                    case 'updateAddress':
                        $accountController->updateAddress();
                        exit;
                }
                break;
                
            case 'vieworder':
                if ($action === 'orders') {
                    $data = $viewOrderController->orders();
                }
                break;
                
            case 'security':
                switch ($action) {
                    case 'index':
                        $data = $securityController->index();
                        break;
                    case 'changePassword':
                        $securityController->changePassword();
                        exit;
                    case 'toggle2FA':
                        $securityController->toggle2FA();
                        exit;
                    case 'getLoginHistory':
                        $securityController->getLoginHistory();
                        exit;
                }
                break;
                
            case 'message':
                // FIXED: Message controller handles authentication internally
                if ($action === 'index') {
                    $data = $messageController->index();
                } else if ($action === 'getUnreadCount') {
                    $messageController->getUnreadCount();
                } else if ($action === 'markAsRead') {
                    $messageController->markAsRead();
                } else if ($action === 'getRecentMessages') {
                    $messageController->getRecentMessages();
                } else if ($action === 'markAllAsRead') {
                    $messageController->markAllAsRead();
                } else if ($action === 'deleteMessage') {
                    $messageController->deleteMessage();
                }
                break;
                
            case 'media':
                if ($action === 'index') {
                    $data = $mediaController->index();
                } elseif ($action === 'view') {
                    $mediaId = $params['id'] ?? null;
                    if (!$mediaId) {
                        throw new Exception("Media ID is required");
                    }
                    $data = $mediaController->view($mediaId);
                    if (!$data) {
                        http_response_code(404);
                        include __DIR__ . '/../views/404.php';
                        exit;
                    }
                }
                break;
                
            default:
                http_response_code(404);
                include __DIR__ . '/../views/404.php';
                exit;
        }
        
        // Check if this is an API request
        $isApiRequest = (
            strpos($uri, '/api/') === 0 || 
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
        
        // Special handling for form submissions
        $isFormSubmission = (
            $_SERVER['REQUEST_METHOD'] === 'POST' && 
            (
                strpos($uri, '/auth/signup-process') === 0 ||
                strpos($uri, '/log/login-process') === 0 ||
                strpos($uri, '/account/support-process') === 0 ||
                strpos($uri, '/checkout/process') === 0
            )
        );
        
        if ($isApiRequest && !$isFormSubmission) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        
        // Extract data for views
        if (is_array($data)) {
            extract($data);
        }
        
        // Include the appropriate view
        $viewDirs = [
            __DIR__ . '/../views/' . $controllerName . 's/' . $action . 's.php',
            __DIR__ . '/../views/' . $controllerName . 's/' . $action . '.php',
            __DIR__ . '/../views/' . $controllerName . '/' . $action . 's.php',
            __DIR__ . '/../views/' . $controllerName . '/' . $action . '.php',
            __DIR__ . '/../views/account/' . $action . '.php',
            __DIR__ . '/../views/otp/' . $action . '.php',
            __DIR__ . '/../views/otp/verify-otp.php',
            __DIR__ . '/../views/security/' . $action . '.php',
            __DIR__ . '/../views/vieworder/' . $action . '.php',
            __DIR__ . '/../views/log/' . $action . '.php',
            __DIR__ . '/../views/otp/' . $action . '.php',
            __DIR__ . '/../views/logotp/' . $action . '.php',
            __DIR__ . '/../views/language/' . $action . '.php',
            __DIR__ . '/../views/review/' . $action . '.php',
            __DIR__ . '/../views/review/index.php',
            __DIR__ . '/../views/review/write.php',
            __DIR__ . '/../views/review/writeWithProduct.php',
            __DIR__ . '/../views/notification/' . $action . '.php',
            __DIR__ . '/../views/notification/settings.php'
        ];
        
        $viewFound = false;
        foreach ($viewDirs as $viewPath) {
            if (file_exists($viewPath)) {
                include_once __DIR__ . '/../views/layouts/header.php';
                include $viewPath;
                include_once __DIR__ . '/../views/layouts/footer.php';
                $viewFound = true;
                break;
            }
        }
        
        if (!$viewFound) {
            // Fallback to default view location
            $defaultViewPath = __DIR__ . '/../views/' . $controllerName . '/' . $action . '.php';
            if (file_exists($defaultViewPath)) {
                include_once __DIR__ . '/../views/layouts/header.php';
                include $defaultViewPath;
                include_once __DIR__ . '/../views/layouts/footer.php';
                $viewFound = true;
            }
        }
        
        if (!$viewFound) {
            throw new Exception("View not found for controller: $controllerName, action: $action. Tried: " . implode(', ', array_map('basename', $viewDirs)));
        }
    } else {
        http_response_code(404);
        include __DIR__ . '/../views/404.php';
        exit;
    }
} catch (Exception $e) {
    error_log("Bootstrap Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $isApiRequest = (
        strpos($_SERVER['REQUEST_URI'], '/api/') === 0 || 
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
    
    if ($isApiRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
            'error' => (ini_get('display_errors') ? $e->getMessage() : 'Internal Server Error')
        ]);
        exit;
    }
    
    http_response_code(500);
    if (file_exists(__DIR__ . '/../views/500.php')) {
        include __DIR__ . '/../views/500.php';
    } else {
        echo "<h1>500 Internal Server Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
        if (ini_get('display_errors')) {
            echo "<pre>Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
            echo "<pre>File: " . $e->getFile() . " Line: " . $e->getLine() . "</pre>";
        }
    }
    exit;
}
?>