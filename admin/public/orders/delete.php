<?php
//delete.php - Soft Delete (move to trash)
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Check if order exists
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Soft delete: Mark as deleted instead of removing
        $stmt = $pdo->prepare("UPDATE orders SET deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['success_message'] = "Order moved to trash successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete order.";
        }
    } else {
        $_SESSION['error_message'] = "Order not found.";
    }
} else {
    $_SESSION['error_message'] = "Invalid order ID.";
}

// Redirect back to orders page
header("Location: ../orders.php");
exit;
?>