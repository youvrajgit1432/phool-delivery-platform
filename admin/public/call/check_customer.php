<?php
// public/call/check_customer.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = trim($_POST['phone_number']);
    
    if (!empty($phone_number)) {
        $stmt = $pdo->prepare("
            SELECT id, name, phone_number, city, street, profile_picture, created_at 
            FROM call_customers 
            WHERE phone_number = ?
        ");
        $stmt->execute([$phone_number]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            echo json_encode([
                'exists' => true,
                'customer' => $customer
            ]);
        } else {
            echo json_encode([
                'exists' => false,
                'message' => 'Customer not found'
            ]);
        }
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Phone number required'
        ]);
    }
}
?>