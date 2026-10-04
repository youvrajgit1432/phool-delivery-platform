<?php
// public/call/delete_call_order.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    $_SESSION['error_message'] = "Invalid order ID";
    header("Location: ../call_orders.php");
    exit;
}

// Check if order exists
$order = $pdo->prepare("SELECT id FROM call_customer_orders WHERE id = ?");
$order->execute([$order_id]);
$order = $order->fetch();

if (!$order) {
    $_SESSION['error_message'] = "Order not found";
    header("Location: ../call_orders.php");
    exit;
}

// Delete order
try {
    $stmt = $pdo->prepare("DELETE FROM call_customer_orders WHERE id = ?");
    $stmt->execute([$order_id]);
    
    $_SESSION['success_message'] = "Order deleted successfully!";
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error deleting order: " . $e->getMessage();
}

header("Location: ../call_orders.php");
exit;
?>