<?php
// admin/public/orders/status.php - FIXED VERSION

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database and services
$pdo = getDBConnection();
$pushService = new AdminPushNotificationService($pdo);
$messageService = getMessageService();

// Get order ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get order details with device count
$current_order = null;
$customer_info = null;
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT o.*, c.id as customer_id, c.name as customer_name, c.phone, c.email,
               COUNT(pnd.id) as device_count,
               GROUP_CONCAT(DISTINCT pnd.device_token) as device_tokens
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN push_notification_devices pnd ON c.id = pnd.user_id AND pnd.is_active = 1
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$id]);
    $current_order = $stmt->fetch();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'];
    $notes = trim($_POST['notes']);
    $send_notification = isset($_POST['send_notification']);
    $send_email = isset($_POST['send_email']);
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, admin_notes = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $notes, $id])) {
        
        $notification_results = [];
        $email_results = [];
        
        // Send push notification if requested
        if ($send_notification) {
            $push_result = $pushService->sendOrderStatusUpdate($id, $new_status, $notes);
            $notification_results['push'] = $push_result ? 'success' : 'failed';
            
            // Log detailed push notification result
            if (!$push_result) {
                error_log("Push notification failed for order #" . $id . 
                         " - Customer: " . $current_order['customer_id'] . 
                         " - Devices: " . ($current_order['device_count'] ?? 0));
            }
        }
        
        // Send email notification if requested
        if ($send_email) {
            $emailService = new EmailService($pdo);
            $email_result = $emailService->sendOrderStatusUpdate($id, $new_status);
            $email_results['email'] = $email_result['success'] ? 'success' : 'failed';
        }
        
        // Send in-app message
        $messageService->createOrderStatusMessage($id, $new_status);
        
        // Prepare success message
        $message_parts = ["Order status updated successfully!"];
        
        if ($send_notification) {
            $device_count = $current_order['device_count'] ?? 0;
            $push_status = $notification_results['push'] === 'success' ? 
                '✅ Sent to ' . $device_count . ' device(s)' : 
                '❌ Failed - Check device tokens & FCM config';
            $message_parts[] = "Push notification: " . $push_status;
        }
        
        if ($send_email) {
            $message_parts[] = "Email: " . ($email_results['email'] === 'success' ? '✅ Sent' : '❌ Failed');
        }
        
        $_SESSION['success_message'] = implode(" | ", $message_parts);
        
        header("Location: view.php?id=" . $id);
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to update order status.";
    }
}

// Set page title
$page_title = "Update Order Status - #" . ($current_order['order_number'] ?? 'N/A') . " - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';

if (!$current_order) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    include '../../app/views/layouts/footer.php';
    exit;
}

// Check FCM configuration
$fcmConfig = FirebaseConfig::getInstance();
$hasFcmConfig = !empty($fcmConfig->getServerKey()) && !empty($fcmConfig->get('project_id'));
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bell text-primary me-2"></i>
        Update Order Status - #<?php echo $current_order['order_number']; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Order
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!$hasFcmConfig): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>FCM Configuration Missing!</strong> Push notifications may not work. Please check Firebase configuration in system settings.
    <a href="../settings/notification-settings.php" class="alert-link">Configure Now</a>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-sync-alt me-2"></i>Status Update & Notifications
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="statusForm">
                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                    
                    <!-- System Status -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>FCM Status:</strong> 
                                        <span class="badge bg-<?php echo $hasFcmConfig ? 'success' : 'danger'; ?>">
                                            <?php echo $hasFcmConfig ? 'Configured' : 'Not Configured'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Active Devices:</strong> 
                                        <span class="badge bg-<?php echo ($current_order['device_count'] ?? 0) > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $current_order['device_count'] ?? 0; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Push Ready:</strong> 
                                        <span class="badge bg-<?php echo (($current_order['device_count'] ?? 0) > 0 && $hasFcmConfig) ? 'success' : 'secondary'; ?>">
                                            <?php echo (($current_order['device_count'] ?? 0) > 0 && $hasFcmConfig) ? 'Yes' : 'No'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Order Number</label>
                                <div class="form-control bg-light">#<?php echo htmlspecialchars($current_order['order_number']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Customer</label>
                                <div class="form-control bg-light">
                                    <?php echo htmlspecialchars($current_order['customer_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($current_order['phone']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Selection -->
                    <div class="mb-4">
                        <label for="status" class="form-label fw-bold">
                            <i class="fas fa-flag me-1"></i>New Status *
                        </label>
                        <select class="form-select form-select-lg" id="status" name="status" required onchange="updateNotificationPreview()">
                            <option value="pending" <?php echo $current_order['status'] == 'pending' ? 'selected' : ''; ?>>
                                ⏳ Pending
                            </option>
                            <option value="confirmed" <?php echo $current_order['status'] == 'confirmed' ? 'selected' : ''; ?>>
                                ✅ Confirmed
                            </option>
                            <option value="preparing" <?php echo $current_order['status'] == 'preparing' ? 'selected' : ''; ?>>
                                👨‍🍳 Preparing
                            </option>
                            <option value="out_for_delivery" <?php echo $current_order['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>
                                🚚 Out for Delivery
                            </option>
                            <option value="delivered" <?php echo $current_order['status'] == 'delivered' ? 'selected' : ''; ?>>
                                📦 Delivered
                            </option>
                            <option value="cancelled" <?php echo $current_order['status'] == 'cancelled' ? 'selected' : ''; ?>>
                                ❌ Cancelled
                            </option>
                        </select>
                    </div>
                    
                    <!-- Admin Notes -->
                    <div class="mb-4">
                        <label for="notes" class="form-label fw-bold">
                            <i class="fas fa-sticky-note me-1"></i>Admin Notes
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Add any notes about this status change that will be included in notifications..."
                                  oninput="updateNotificationPreview()"><?php echo htmlspecialchars($current_order['admin_notes'] ?? ''); ?></textarea>
                        <div class="form-text">These notes will be included in customer notifications.</div>
                    </div>
                    
                    <!-- Notification Options -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-bell me-2"></i>Notification Options
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="send_notification" name="send_notification" 
                                               <?php echo (($current_order['device_count'] ?? 0) > 0 && $hasFcmConfig) ? 'checked' : 'disabled'; ?> 
                                               onchange="updateNotificationPreview()">
                                        <label class="form-check-label fw-bold" for="send_notification">
                                            <i class="fas fa-mobile-alt me-1"></i> Push Notification
                                        </label>
                                        <div class="form-text">
                                            <?php if (($current_order['device_count'] ?? 0) > 0 && $hasFcmConfig): ?>
                                                <i class="fas fa-check text-success me-1"></i>
                                                <?php echo $current_order['device_count'] ?? 0; ?> active device(s) - Ready
                                            <?php elseif (!$hasFcmConfig): ?>
                                                <i class="fas fa-times text-danger me-1"></i>
                                                FCM not configured
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger me-1"></i>
                                                No active devices found
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="send_email" name="send_email" 
                                               <?php echo $current_order['email'] ? 'checked' : 'disabled'; ?>>
                                        <label class="form-check-label fw-bold" for="send_email">
                                            <i class="fas fa-envelope me-1"></i> Email
                                        </label>
                                        <div class="form-text">
                                            <?php if ($current_order['email']): ?>
                                                <i class="fas fa-check text-success me-1"></i>
                                                <?php echo htmlspecialchars($current_order['email']); ?>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger me-1"></i>
                                                No email available
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (($current_order['device_count'] ?? 0) === 0): ?>
                            <div class="alert alert-warning mt-2 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Customer has no active devices for push notifications. 
                                <a href="#" onclick="showDeviceHelp()">Learn how to enable push notifications</a>
                            </div>
                            <?php elseif (!$hasFcmConfig): ?>
                            <div class="alert alert-danger mt-2 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                FCM configuration is missing. Push notifications will not work until configured.
                                <a href="../settings/notification-settings.php">Configure FCM</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Notification Preview -->
                    <div class="card mb-4" id="notificationPreview">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-eye me-2"></i>Notification Preview
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="previewContent">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Select a status to see notification preview
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i> Update Status & Send Notifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar: Customer Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Customer Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Name:</strong><br>
                    <?php echo htmlspecialchars($current_order['customer_name']); ?>
                </div>
                <div class="mb-3">
                    <strong>Phone:</strong><br>
                    <?php echo htmlspecialchars($current_order['phone']); ?>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong><br>
                    <?php echo htmlspecialchars($current_order['email'] ?? 'Not provided'); ?>
                </div>
                <div class="mb-3">
                    <strong>Active Devices:</strong><br>
                    <span class="badge bg-<?php echo ($current_order['device_count'] ?? 0) > 0 ? 'success' : 'warning'; ?>">
                        <?php echo $current_order['device_count'] ?? 0; ?> device(s)
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Push Status:</strong><br>
                    <?php if (($current_order['device_count'] ?? 0) > 0 && $hasFcmConfig): ?>
                        <span class="badge bg-success">Ready for notifications</span>
                    <?php elseif (!$hasFcmConfig): ?>
                        <span class="badge bg-danger">FCM not configured</span>
                    <?php else: ?>
                        <span class="badge bg-warning">No active devices</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i> View Order Details
                    </a>
                    <a href="../customers/view_customer.php?id=<?php echo $current_order['customer_id']; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-user me-1"></i> View Customer Profile
                    </a>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="testNotification()" 
                            <?php echo (($current_order['device_count'] ?? 0) === 0 || !$hasFcmConfig) ? 'disabled' : ''; ?>>
                        <i class="fas fa-bell me-1"></i> Test Notification
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="showDeviceHelp()">
                        <i class="fas fa-question-circle me-1"></i> Device Help
                    </button>
                    <?php if (!$hasFcmConfig): ?>
                    <a href="../settings/notification-settings.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-cog me-1"></i> Configure FCM
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Troubleshooting Help -->
        <div class="card mt-3 d-none" id="deviceHelpCard">
            <div class="card-header bg-info text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-question-circle me-2"></i>Push Notification Help
                </h6>
            </div>
            <div class="card-body">
                <h6>Common Issues:</h6>
                <ul class="small">
                    <li><strong>Invalid FCM Tokens:</strong> Device tokens in wrong format</li>
                    <li><strong>Missing FCM Configuration:</strong> Server key not configured</li>
                    <li><strong>No Active Devices:</strong> Customer hasn't enabled notifications</li>
                    <li><strong>Browser Permissions:</strong> Push permission denied</li>
                </ul>
                
                <h6>Solutions:</h6>
                <ol class="small">
                    <li>Check FCM configuration in system settings</li>
                    <li>Verify device tokens in push_notification_devices table</li>
                    <li>Ask customer to allow notifications in browser</li>
                    <li>Ensure customer visits website with HTTPS</li>
                </ol>
                
                <div class="alert alert-warning small mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Push notifications require valid FCM configuration and customer permission.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Notification preview data
const statusMessages = {
    'confirmed': {
        'title': 'Order Confirmed #<?php echo $current_order["order_number"]; ?>',
        'message': 'Your order has been confirmed and is being processed. Order total: Rs. <?php echo number_format($current_order["total_amount"], 2); ?>.'
    },
    'preparing': {
        'title': 'Order Status Update',
        'message': 'Your order #<?php echo $current_order["order_number"]; ?> is now being prepared.'
    },
    'out_for_delivery': {
        'title': 'Order Out for Delivery',
        'message': 'Your order #<?php echo $current_order["order_number"]; ?> is out for delivery! Track your order in the app.'
    },
    'delivered': {
        'title': 'Order Delivered',
        'message': 'Your order #<?php echo $current_order["order_number"]; ?> has been successfully delivered. Thank you for shopping with us!'
    },
    'cancelled': {
        'title': 'Order Cancelled',
        'message': 'Your order #<?php echo $current_order["order_number"]; ?> has been cancelled.'
    },
    'pending': {
        'title': 'Order Status Update',
        'message': 'Your order status has been updated.'
    }
};

function updateNotificationPreview() {
    const status = document.getElementById('status').value;
    const notes = document.getElementById('notes').value;
    const sendNotification = document.getElementById('send_notification').checked;
    const preview = document.getElementById('notificationPreview');
    const previewContent = document.getElementById('previewContent');
    
    if (!sendNotification) {
        previewContent.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-bell-slash me-2"></i>
                Push notifications are disabled. Enable to see preview.
            </div>
        `;
        return;
    }
    
    if (statusMessages[status]) {
        const messageData = statusMessages[status];
        
        let message = messageData.message;
        if (notes) {
            message += ' Note: ' + notes;
        }
        
        previewContent.innerHTML = `
            <div class="notification-preview">
                <div class="mb-3 p-3 border rounded bg-light">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong class="text-primary">${messageData.title}</strong>
                        <small class="text-muted">Now</small>
                    </div>
                    <div class="text-muted">${message}</div>
                    <div class="mt-2 small">
                        <span class="badge bg-primary me-2">Push Notification</span>
                        <span class="badge bg-info">EN</span>
                        <span class="badge bg-success">FCM</span>
                    </div>
                </div>
                <div class="alert alert-success small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    This notification will be sent to <?php echo $current_order['device_count'] ?? 0; ?> device(s) and in-app message center.
                    ${notes ? '<br><strong>Notes included:</strong> ' + notes : ''}
                </div>
            </div>
        `;
        
        preview.style.display = 'block';
    } else {
        previewContent.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Select a status to see notification preview
            </div>
        `;
    }
}

function testNotification() {
    if (!confirm('Send a test notification to this customer?')) {
        return;
    }
    
    fetch('../../api/NotificationApi.php?action=test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: <?php echo $id; ?>,
            customer_id: <?php echo $current_order['customer_id']; ?>,
            title: 'Test Notification',
            message: 'This is a test notification from admin panel'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test notification sent successfully!');
        } else {
            alert('Failed to send test notification: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error sending test notification');
    });
}

function showDeviceHelp() {
    const helpCard = document.getElementById('deviceHelpCard');
    helpCard.classList.toggle('d-none');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationPreview();
    
    // Update preview when inputs change
    document.getElementById('notes').addEventListener('input', updateNotificationPreview);
    document.getElementById('send_notification').addEventListener('change', updateNotificationPreview);
});

// Form submission handler
document.getElementById('statusForm').addEventListener('submit', function(e) {
    const sendNotification = document.getElementById('send_notification').checked;
    const sendEmail = document.getElementById('send_email').checked;
    
    if (!sendNotification && !sendEmail) {
        if (!confirm('No notification methods selected. Are you sure you want to update status without notifying customer?')) {
            e.preventDefault();
            return;
        }
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
    submitBtn.disabled = true;
});
</script>

<style>
.notification-preview {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}
</style>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>