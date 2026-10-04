<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid wallet ID.";
    header("Location: ../pay.php");
    exit();
}

$id = intval($_GET['id']);

try {
    // Get wallet data to delete the image file
    $stmt = $pdo->prepare("SELECT qr_image FROM wallet_qrs WHERE id = ?");
    $stmt->execute([$id]);
    $wallet = $stmt->fetch();
    
    if ($wallet && !empty($wallet['qr_image'])) {
        $file_path = '../../storage/uploads/wallet_qr/' . $wallet['qr_image'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete the wallet record
    $stmt = $pdo->prepare("DELETE FROM wallet_qrs WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success_message'] = "Wallet QR deleted successfully!";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error deleting wallet: " . $e->getMessage();
}

header("Location: ../pay.php");
exit();
?>