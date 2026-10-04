<?php
// messages.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle filters with validation
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['order', 'system', 'promotion']) ? $_GET['type'] : '';
$read_filter = isset($_GET['read']) && in_array($_GET['read'], ['read', 'unread']) ? $_GET['read'] : '';
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $message_ids = isset($_POST['message_ids']) ? $_POST['message_ids'] : [];
    
    // Validate message IDs
    $valid_message_ids = [];
    foreach ($message_ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            $valid_message_ids[] = $id;
        }
    }
    
    if (!empty($valid_message_ids)) {
        $placeholders = str_repeat('?,', count($valid_message_ids) - 1) . '?';
        
        try {
            switch ($_POST['bulk_action']) {
                case 'mark_read':
                    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                    $stmt->execute($valid_message_ids);
                    $_SESSION['success_message'] = count($valid_message_ids) . " message(s) marked as read successfully.";
                    break;
                    
                case 'mark_unread':
                    $stmt = $pdo->prepare("UPDATE messages SET is_read = 0, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                    $stmt->execute($valid_message_ids);
                    $_SESSION['success_message'] = count($valid_message_ids) . " message(s) marked as unread successfully.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)");
                    $stmt->execute($valid_message_ids);
                    $_SESSION['success_message'] = count($valid_message_ids) . " message(s) deleted successfully.";
                    break;
                    
                default:
                    $_SESSION['error_message'] = "Invalid bulk action.";
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error performing bulk action: " . $e->getMessage();
        }
        
        header("Location: messages.php");
        exit;
    } else {
        $_SESSION['error_message'] = "No valid messages selected.";
    }
}

// Handle individual message actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    
    if ($message_id > 0) {
        try {
            switch ($_GET['action']) {
                case 'mark_read':
                    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$message_id]);
                    $_SESSION['success_message'] = "Message marked as read.";
                    break;
                    
                case 'mark_unread':
                    $stmt = $pdo->prepare("UPDATE messages SET is_read = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$message_id]);
                    $_SESSION['success_message'] = "Message marked as unread.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                    $stmt->execute([$message_id]);
                    $_SESSION['success_message'] = "Message deleted successfully.";
                    break;
                    
                default:
                    $_SESSION['error_message'] = "Invalid action.";
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error performing action: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid message ID.";
    }
    
    header("Location: messages.php");
    exit;
}

// Build WHERE clause for filtering safely
$where_conditions = [];
$params = [];

if (!empty($type_filter)) {
    $where_conditions[] = "m.type = ?";
    $params[] = $type_filter;
}

if ($read_filter === 'read') {
    $where_conditions[] = "m.is_read = 1";
} elseif ($read_filter === 'unread') {
    $where_conditions[] = "m.is_read = 0";
}

if ($customer_filter > 0) {
    $where_conditions[] = "m.customer_id = ?";
    $params[] = $customer_filter;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get messages with customer information safely
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COALESCE(c.name, 'System') as customer_name, 
               COALESCE(c.phone, 'N/A') as customer_phone, 
               COALESCE(c.email, 'N/A') as customer_email
        FROM messages m 
        LEFT JOIN customers c ON m.customer_id = c.id 
        $where_clause 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
    $_SESSION['error_message'] = "Error loading messages: " . $e->getMessage();
}

// Get customers for filter dropdown
try {
    $customers = $pdo->query("SELECT id, name FROM customers WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}

// Get message statistics
try {
    $total_messages = $pdo->query("SELECT COUNT(*) as count FROM messages")->fetch()['count'];
    $unread_messages = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE is_read = 0")->fetch()['count'];
    $order_messages = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE type = 'order'")->fetch()['count'];
    $system_messages = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE type = 'system'")->fetch()['count'];
} catch (PDOException $e) {
    $total_messages = $unread_messages = $order_messages = $system_messages = 0;
}

// Set page title
$page_title = "Messages - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Messages Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filtersModal">
                <i class="fas fa-filter me-1"></i> Filters
            </button>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Messages</div>
                        <div class="h5 mb-0"><?php echo $total_messages; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Unread Messages</div>
                        <div class="h5 mb-0"><?php echo $unread_messages; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope-open fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Order Messages</div>
                        <div class="h5 mb-0"><?php echo $order_messages; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase mb-1">System Messages</div>
                        <div class="h5 mb-0"><?php echo $system_messages; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cog fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">All Messages</h5>
            </div>
            <div class="col-md-6 text-end">
                <form method="post" class="d-inline" id="bulkActionForm">
                    <input type="hidden" name="bulk_action" id="bulkAction">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            Bulk Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="setBulkAction('mark_read')">Mark as Read</a></li>
                            <li><a class="dropdown-item" href="#" onclick="setBulkAction('mark_unread')">Mark as Unread</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="setBulkAction('delete')">Delete</a></li>
                        </ul>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="messagesTable">
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                        <tr class="<?php echo $message['is_read'] ? '' : 'table-warning'; ?>">
                            <td>
                                <input type="checkbox" name="message_ids[]" value="<?php echo $message['id']; ?>" class="message-checkbox">
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    switch($message['type']) {
                                        case 'order': echo 'info'; break;
                                        case 'system': echo 'primary'; break;
                                        case 'promotion': echo 'warning'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars(ucfirst($message['type'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($message['title']); ?></strong>
                                <?php if ($message['related_id']): ?>
                                <br>
                                <small class="text-muted">Related ID: <?php echo htmlspecialchars($message['related_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 100)); ?>
                                    <?php if (strlen($message['message']) > 100): ?>
                                    ... <a href="#" class="text-primary view-message" data-message="<?php echo htmlspecialchars($message['message']); ?>" data-title="<?php echo htmlspecialchars($message['title']); ?>">View more</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($message['customer_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($message['customer_phone']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $message['is_read'] ? 'success' : 'warning'; ?>">
                                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (!$message['is_read']): ?>
                                    <a href="messages.php?action=mark_read&id=<?php echo $message['id']; ?>" class="btn btn-success" title="Mark as Read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="messages.php?action=mark_unread&id=<?php echo $message['id']; ?>" class="btn btn-warning" title="Mark as Unread">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="messages.php?action=delete&id=<?php echo $message['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this message?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No messages found with the selected filters.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Filters Modal -->
<div class="modal fade" id="filtersModal" tabindex="-1" aria-labelledby="filtersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filtersModalLabel">Filter Messages</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET" action="messages.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type_filter" class="form-label">Message Type</label>
                        <select class="form-select" id="type_filter" name="type">
                            <option value="">All Types</option>
                            <option value="order" <?php echo $type_filter == 'order' ? 'selected' : ''; ?>>Order Messages</option>
                            <option value="system" <?php echo $type_filter == 'system' ? 'selected' : ''; ?>>System Messages</option>
                            <option value="promotion" <?php echo $type_filter == 'promotion' ? 'selected' : ''; ?>>Promotion Messages</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="read_filter" class="form-label">Read Status</label>
                        <select class="form-select" id="read_filter" name="read">
                            <option value="">All Messages</option>
                            <option value="read" <?php echo $read_filter == 'read' ? 'selected' : ''; ?>>Read Only</option>
                            <option value="unread" <?php echo $read_filter == 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customer_filter" class="form-label">Customer</label>
                        <select class="form-select" id="customer_filter" name="customer_id">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="messages.php" class="btn btn-secondary">Reset Filters</a>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Message View Modal -->
<div class="modal fade" id="messageViewModal" tabindex="-1" aria-labelledby="messageViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageViewModalLabel">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="messageModalTitle" class="mb-3"></h6>
                <div id="messageContent" class="border p-3 bg-light rounded" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk actions
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

function setBulkAction(action) {
    const form = document.getElementById('bulkActionForm');
    const checkboxes = document.querySelectorAll('.message-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one message.');
        return;
    }
    
    document.getElementById('bulkAction').value = action;
    
    if (action === 'delete') {
        if (!confirm('Are you sure you want to delete ' + checkboxes.length + ' selected message(s)?')) {
            return;
        }
    }
    
    form.submit();
}

// View full message
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-message').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-message');
            const title = this.getAttribute('data-title');
            
            document.getElementById('messageModalTitle').textContent = title;
            document.getElementById('messageContent').textContent = message;
            
            const modal = new bootstrap.Modal(document.getElementById('messageViewModal'));
            modal.show();
        });
    });
});

// Initialize DataTable
$(document).ready(function() {
    $('#messagesTable').DataTable({
        "pageLength": 25,
        "order": [[6, 'desc']],
        "columnDefs": [
            { "orderable": false, "targets": [0, 7] }
        ],
        "language": {
            "emptyTable": "No messages found",
            "info": "Showing _START_ to _END_ of _TOTAL_ messages",
            "infoEmpty": "Showing 0 to 0 of 0 messages",
            "infoFiltered": "(filtered from _MAX_ total messages)",
            "lengthMenu": "Show _MENU_ messages",
            "search": "Search:",
            "zeroRecords": "No matching messages found"
        }
    });
});
</script>

<style>
/* Message status indicators */
.status-indicator {
    height: 12px;
    width: 12px;
    border-radius: 50%;
    display: inline-block;
}

.message-preview {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Table row highlighting for unread messages */
.table-warning {
    background-color: #fff3cd !important;
}

/* Dropdown message items */
.dropdown-list-image {
    position: relative;
    height: 2.5rem;
    width: 2.5rem;
}

.dropdown-list-image img {
    height: 2.5rem;
    width: 2.5rem;
}

/* DataTable customization */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 1rem;
}

.dataTables_wrapper .dataTables_info {
    padding-top: 1rem;
}
</style>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>