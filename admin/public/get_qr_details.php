<?php
// get_qr_details.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $qr_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM payment_qr_codes WHERE id = ?");
    $stmt->execute([$qr_id]);
    $qr_code = $stmt->fetch();
    
    if ($qr_code) {
        echo json_encode([
            'success' => true,
            'qr' => $qr_code
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'QR code not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ID provided'
    ]);
}
?>