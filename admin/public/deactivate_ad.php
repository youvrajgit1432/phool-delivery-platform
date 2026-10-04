<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

if (isset($_GET['id'])) {
    $ad_id = intval($_GET['id']);
    
    try {
        // Check if ad exists
        $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
        $stmt->execute([$ad_id]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            $_SESSION['error_message'] = "Ad not found.";
            header("Location: ads.php");
            exit();
        }
        
        // Deactivate the ad
        $stmt = $pdo->prepare("UPDATE ads SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ad_id]);
        
        $_SESSION['success_message'] = "Ad deactivated successfully!";
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "No ad ID specified.";
}

header("Location: ads.php");
exit();
?>