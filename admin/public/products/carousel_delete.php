<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get carousel ID
$carousel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($carousel_id <= 0) {
    $_SESSION['error_message'] = "Invalid carousel image ID.";
    header("Location: carousel.php");
    exit;
}

// Get carousel details
$stmt = $pdo->prepare("SELECT * FROM carousel_images WHERE id = ?");
$stmt->execute([$carousel_id]);
$carousel = $stmt->fetch();

if (!$carousel) {
    $_SESSION['error_message'] = "Carousel image not found.";
    header("Location: carousel.php");
    exit;
}

try {
    // Delete image file from server
    if (!empty($carousel['image_path'])) {
        $file_path = '../../storage/uploads/carousel/' . $carousel['image_path'];
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                error_log("Failed to delete carousel image file: " . $file_path);
            }
        }
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM carousel_images WHERE id = ?");
    $stmt->execute([$carousel_id]);
    
    $_SESSION['success_message'] = "Carousel image deleted successfully!";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to delete carousel image: " . $e->getMessage();
}

// Redirect back to carousel list
$redirect_url = 'carousel.php';
if (isset($_GET['category_id'])) {
    $redirect_url .= '?category_id=' . intval($_GET['category_id']);
}

header("Location: " . $redirect_url);
exit;
?>