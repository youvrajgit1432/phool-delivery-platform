<?php
// media/toggle_status.php - Toggle Media Status Page
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle toggle status action
if (!isset($_GET['id'])) {
    header("Location: ../media.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $pdo->prepare("UPDATE media SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
if ($stmt->execute([$id])) {
    $_SESSION['success_message'] = "Media status updated successfully!";
} else {
    $_SESSION['error_message'] = "Error updating media status: " . implode(" ", $stmt->errorInfo());
}

header("Location: ../media.php");
exit();
?>