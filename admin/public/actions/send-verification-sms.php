<?php
// public/actions/send-verification-sms.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $redirect_url = $_POST['redirect_url'] ?? '../customer-verification.php';
    
    $smsService = getSmsService();
    
    if ($smsService->sendVerificationSMS($customer_id)) {
        $_SESSION['success_message'] = "Verification SMS sent successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to send verification SMS. Customer may not have a valid phone number or SMS service is misconfigured.";
    }
    
    header("Location: $redirect_url");
    exit;
} else {
    header("Location: ../customer-verification.php");
    exit;
}
?>