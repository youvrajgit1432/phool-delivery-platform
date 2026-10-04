<?php
// public/ajax/retry-email.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $log_id = intval($input['log_id']);
    
    $pdo = getDBConnection();
    $emailService = getEmailService();
    
    // Get the email log entry
    $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
    $stmt->execute([$log_id]);
    $log = $stmt->fetch();
    
    if (!$log) {
        throw new Exception("Email log not found");
    }
    
    // For verification emails, we can regenerate the code
    if ($log['message_type'] === 'verification' && $log['related_id']) {
        $success = $emailService->sendCustomerVerificationEmail($log['related_id']);
    } else {
        // For other email types, we need to reconstruct the message
        // This is a simplified example - you'd need to implement based on your message types
        $success = $emailService->sendEmail(
            $log['recipient_email'],
            $log['recipient_name'],
            $log['subject'],
            'Please check the original message content', // You'd reconstruct this
            $log['message_type'],
            $log['related_id']
        );
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        throw new Exception($emailService->getLastError());
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}