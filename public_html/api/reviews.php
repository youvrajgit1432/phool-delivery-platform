<?php
// public_html/api/reviews.php
// Ensure no output before headers
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent errors from corrupting JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include the app bootstrap file (which includes all necessary models and configs)
require_once dirname(dirname(__DIR__)) . '/app/bootstrap/app.php';

// Re-disable display_errors because app.php sets it to 1
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        error_log('Fatal error in reviews API: ' . json_encode($error));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'A fatal error occurred. Please contact support.'
            ]);
        }
        exit;
    }
});

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Database connection - use correct instantiation
    $db = (new Database())->getConnection();
    $review = new Review($db);
    
    if ($method === 'POST' && $action === 'submit') {
        // Submit a new review
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid input data'
            ]);
            exit;
        }
        
        $product_id = isset($input['product_id']) ? (int)$input['product_id'] : null;
        $rating = isset($input['rating']) ? (int)$input['rating'] : null;
        $title = isset($input['title']) ? trim($input['title']) : null;
        $content = isset($input['content']) ? trim($input['content']) : null;
        
        // For guest users
        $guest_name = isset($input['guest_name']) ? trim($input['guest_name']) : null;
        $guest_contact = isset($input['guest_contact']) ? trim($input['guest_contact']) : null;
        $guest_email = isset($input['guest_email']) ? trim($input['guest_email']) : null;
        
        // Validation
        if (!$product_id || !$rating || !$title || !$content) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'All review fields are required'
            ]);
            exit;
        }
        
        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Rating must be between 1 and 5'
            ]);
            exit;
        }
        
        if (strlen($title) < 3 || strlen($title) > 255) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Review title must be between 3 and 255 characters'
            ]);
            exit;
        }
        
        if (strlen($content) < 10) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Review content must be at least 10 characters'
            ]);
            exit;
        }
        
        $is_logged_in = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
        
        if ($is_logged_in) {
            $user_id = (int)$_SESSION['customer_id'];
        } else {
            // For guest users, validate and set user_id to NULL (will be stored with guest info)
            if (!$guest_name || strlen($guest_name) < 2) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid name (at least 2 characters)'
                ]);
                exit;
            }
            
            if (!$guest_contact || strlen($guest_contact) < 5) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid phone or email'
                ]);
                exit;
            }
            
            $user_id = null;
        }
        
        // Check if user has already reviewed this product (for logged-in users)
        if ($is_logged_in && $review->hasUserReviewed($user_id, $product_id)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'You have already reviewed this product'
            ]);
            exit;
        }
        
        // Create the review
        $review->user_id = $user_id;
        $review->product_id = $product_id;
        $review->rating = $rating;
        $review->title = $title;
        $review->content = $content;
        $review->status = 'pending';
        $review->guest_name = $guest_name;
        $review->guest_contact = $guest_contact;
        $review->guest_email = $guest_email;
        
        if ($review->create()) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Review submitted successfully. It will be published after approval.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to submit review. Please try again.'
            ]);
        }
        exit;
    }
    
    if ($method === 'GET' && $action === 'get-reviews') {
        // Fetch reviews for a product
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        
        if (!$product_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Product ID is required'
            ]);
            exit;
        }
        
        // Validate pagination
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 50) $limit = 5;
        
        $reviews = $review->readByProduct($product_id, $page, $limit);
        $total = $review->getProductReviewCount($product_id);
        $average_rating = $review->getAverageRating($product_id);
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'total' => $total,
            'average_rating' => $average_rating,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action or request method'
    ]);

} catch (PDOException $e) {
    error_log('Database error in reviews API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log('Error in reviews API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>
