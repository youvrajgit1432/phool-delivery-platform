<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Get notice ID from URL
$notice_id = $_GET['id'] ?? 0;
if (!$notice_id) {
    $_SESSION['error_message'] = "Notice ID is required.";
    header("Location: ../notices.php");
    exit();
}

// Fetch notice data to get file path
$stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
$stmt->execute([$notice_id]);
$notice = $stmt->fetch();

if (!$notice) {
    $_SESSION['error_message'] = "Notice not found.";
    header("Location: ../notices.php");
    exit();
}

try {
    // Delete the notice
    $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ?");
    $stmt->execute([$notice_id]);
    
    // Delete associated media file
    if (!empty($notice['media_file'])) {
        $file_path = '../../storage/uploads/notices/' . $notice['media_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $_SESSION['success_message'] = "Notice deleted successfully!";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error deleting notice: " . $e->getMessage();
}

header("Location: ../notices.php");
exit();
?>