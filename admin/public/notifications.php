<?php
// public/notifications.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get notifications
$notifications = $pdo->query("
    SELECT 
        n.*,
        u.full_name as created_by
    FROM notifications n
    LEFT JOIN users u ON n.created_by = u.id
    ORDER BY n.created_at DESC
    LIMIT 50
")->fetchAll();

// Mark all notifications as read
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0")->execute();

// Set page title
$page_title = "Notifications - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Notifications</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllNotifications()">
            <i class="fas fa-trash me-1"></i> Clear All
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No notifications yet</h5>
            <p class="text-muted">You'll see important alerts and messages here.</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notification): ?>
            <div class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                </div>
                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                <small class="text-muted">By: <?php echo htmlspecialchars($notification['created_by']); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        window.location.href = 'notifications.php?action=clear_all';
    }
}
</script>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>