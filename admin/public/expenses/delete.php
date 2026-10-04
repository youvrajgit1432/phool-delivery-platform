<?php
// public/expense/delete.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
requireAuth();

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No expense ID specified.";
    header("Location: index.php");
    exit;
}

$pdo = getDBConnection();
$expense_id = intval($_GET['id']);

// Check if user has permission to delete
if ($_SESSION['admin_role'] != 'super_admin' && $_SESSION['admin_role'] != 'admin') {
    $_SESSION['error_message'] = "You don't have permission to delete expenses.";
    header("Location: index.php");
    exit;
}

// Get expense details to delete associated file
$stmt = $pdo->prepare("SELECT bill_receipt FROM expenses WHERE id = ?");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch();

if ($expense) {
    try {
        // Delete associated file if exists
        if (!empty($expense['bill_receipt'])) {
            $file_path = '../../storage/uploads/expenses/' . $expense['bill_receipt'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete expense record
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        
        $_SESSION['success_message'] = "Expense record deleted successfully!";
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Expense record not found.";
}

header("Location: index.php");
exit;
?>