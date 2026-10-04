<?php
// app/views/layouts/notification-modal.php

// AUTOMATIC NOTIFICATION ENABLEMENT - No modal shown to users
if (!isset($_SESSION['notification_preference_set']) || $_SESSION['notification_preference_set'] !== true):
    
    // Set default notification preferences automatically
    $default_notifications = [
        'push_notifications_enabled' => true,
        'main_notification_enabled' => true,
        'offers_notifications' => true,
        'delivery_tracking_notifications' => true,
        'system_notifications' => true
    ];
    
    // Save to session
    $_SESSION['user_notification_preferences'] = $default_notifications;
    $_SESSION['notification_preference_set'] = true;
    $_SESSION['notification_preference_shown'] = true;
    $_SESSION['show_notification_modal'] = false;
    
    // Save to database if user is logged in
    $userId = $_SESSION['customer_id'] ?? null;
    $sessionId = session_id();
    
    if ($userId && class_exists('Notification')) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $notificationModel = new Notification($db);
            $notificationModel->savePreferences($userId, null, $default_notifications);
        } catch (Exception $e) {
            error_log("Error saving automatic notification preferences: " . $e->getMessage());
        }
    }
    
    // Initialize push notifications automatically
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Auto-initialize push notifications
        if (window.pwaHelper) {
            setTimeout(() => {
                window.pwaHelper.initializeAutomaticPushNotifications();
            }, 3000);
        }
        
        // Force device registration if token available
        if (sessionStorage.getItem("pendingDeviceToken")) {
            const deviceToken = sessionStorage.getItem("pendingDeviceToken");
            fetch("' . $pathConfig->get("base_url") . '/api/notifications/force-register-device", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    device_token: deviceToken,
                    auto_registered: true
                })
            }).catch(error => console.log("Auto device registration failed:", error));
        }
    });
    </script>';
endif;
?>