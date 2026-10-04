<?php
// admin/public/api/notifications.php

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize services
$pdo = getDBConnection();
$notificationApi = new NotificationApi($pdo);

// Get the action from URL or POST data
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different actions
switch ($action) {
    case 'send-order-status':
        $notificationApi->sendOrderStatusUpdate();
        break;
        
    case 'send-bulk':
        $notificationApi->sendBulkNotification();
        break;
        
    case 'send-special-offer':
        $notificationApi->sendSpecialOffer();
        break;
        
    case 'get-stats':
        $notificationApi->getNotificationStats();
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Action not found'
        ]);
        break;
}
?>