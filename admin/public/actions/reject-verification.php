<?php
// actions/reject-verification.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database and services
$pdo = getDBConnection();
$messageService = getMessageService();

// Get customer ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '../customer-verification.php';

if ($id > 0) {
    try {
        // Update customer verification status
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET verification_status = 'rejected', 
                verified_by = ? 
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$_SESSION['admin_id'], $id]);
        
        if ($success) {
            // Create verification rejection message
            $messageService->createVerificationMessage($id, 'rejected');
            
            $_SESSION['success_message'] = "Customer verification rejected.";
        } else {
            $_SESSION['error_message'] = "Failed to reject customer verification.";
        }
    } catch (PDOException $e) {
        error_log("Error rejecting customer verification: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error occurred.";
    }
} else {
    $_SESSION['error_message'] = "Invalid customer ID.";
}

header("Location: " . $redirect);
exit;
?>