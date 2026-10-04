<?php
// public/actions/send-verification-email.php

// Include the bootstrap file first
require_once __DIR__ . '/../../bootstrap/app.php';

// Check authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['error_message'] = "Please log in to access this page.";
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : '../customer-verification.php';
    
    // Validate customer ID
    if ($customer_id <= 0) {
        $_SESSION['error_message'] = "Invalid customer ID.";
        header("Location: $redirect_url");
        exit;
    }
    
    try {
        $emailService = getEmailService();
        
        if ($emailService->sendCustomerVerificationEmail($customer_id)) {
            $_SESSION['success_message'] = "Verification email sent successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to send verification email. " . $emailService->getLastError();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Email service error: " . $e->getMessage();
        error_log("Email service error: " . $e->getMessage());
    }
    
    header("Location: $redirect_url");
    exit;
} else {
    // If not POST request, redirect back
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: ../customer-verification.php");
    exit;
}
?>