<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get media ID from URL
$media_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($media_id === 0) {
    $_SESSION['error_message'] = "Invalid media ID.";
    header("Location: ../media.php");
    exit;
}

// Get media details to delete files
$stmt = $pdo->prepare("SELECT * FROM media_items WHERE id = ?");
$stmt->execute([$media_id]);
$media = $stmt->fetch();

if ($media) {
    try {
        // Delete media files
        $upload_dir = '../../storage/uploads/media/';
        
        if (!empty($media['file_path']) && file_exists($upload_dir . $media['file_path'])) {
            unlink($upload_dir . $media['file_path']);
        }
        
        if (!empty($media['thumbnail_path']) && file_exists($upload_dir . $media['thumbnail_path'])) {
            unlink($upload_dir . $media['thumbnail_path']);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM media_items WHERE id = ?");
        $stmt->execute([$media_id]);
        
        // Also delete related comments and likes
        $pdo->prepare("DELETE FROM media_comments WHERE media_id = ?")->execute([$media_id]);
        $pdo->prepare("DELETE FROM media_likes WHERE media_id = ?")->execute([$media_id]);
        
        $_SESSION['success_message'] = "Media deleted successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting media: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Media not found.";
}

header("Location: ../media.php");
exit;
?>