<?php
// Ticket Detail Page - Messenger Style Chat Interface
if (!isset($_SESSION['rider_id'])) {
    header('Location: ' . app_url('/login'));
    exit;
}

require_once $baseDir . '/app/models/Ticket.php';
require_once $baseDir . '/app/controllers/SupportController.php';
require_once $baseDir . '/app/helpers/url.php';
require_once $baseDir . '/app/helpers/formatter.php';

$riderId = $_SESSION['rider_id'];
$ticketId = isset($matches[1]) ? intval($matches[1]) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

$ticketModel = new \Phool\DeliveryPanel\Models\Ticket($GLOBALS['pdo']);
$ticket = $ticketModel->getById($ticketId, $riderId);

if (!$ticket) {
    echo '<div class="alert alert-danger m-3">Ticket not found</div>';
    exit;
}

// Parse messages from response_message field
$messages = [];
if ($ticket->response_message) {
    // Simple parsing - messages are separated by newlines with timestamps
    $lines = explode("\n", $ticket->response_message);
    foreach ($lines as $line) {
        if (!empty(trim($line))) {
            $messages[] = trim($line);
        }
    }
}

// Status and priority colors
$statusColors = [
    'open' => 'danger',
    'in_progress' => 'primary',
    'resolved' => 'success',
    'closed' => 'secondary',
    'reopened' => 'warning'
];

$priorityColors = [
    'low' => 'info',
    'medium' => 'warning',
    'high' => 'danger',
    'urgent' => 'dark'
];

$statuses = \Phool\DeliveryPanel\Models\Ticket::getStatuses();
$priorities = \Phool\DeliveryPanel\Models\Ticket::getPriorities();
$categories = \Phool\DeliveryPanel\Models\Ticket::getCategories();
?>

<div class="container-fluid mt-4">
    <div class="row h-100">
        <!-- Main Chat Area -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm" style="height: calc(100vh - 150px); display: flex; flex-direction: column;">
                <!-- Header -->
                <div class="card-header bg-gradient text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($ticket->ticket_number); ?></h5>
                        <small><?php echo htmlspecialchars($ticket->subject); ?></small>
                    </div>
                    <a href="<?php echo app_url('/support/tickets'); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>

                <!-- Messages Container -->
                <div class="card-body flex-grow-1 overflow-auto" id="messagesContainer" style="background: #f8f9fa;">
                    <!-- Initial Message from User -->
                    <div class="mb-4">
                        <div class="d-flex mb-3">
                            <div class="me-3">
                                <div class="avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="bg-white p-3 rounded" style="max-width: 70%;">
                                    <p class="mb-1"><strong>You</strong></p>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($ticket->description)); ?></p>
                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($ticket->opened_at)); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support Team Responses -->
                    <?php if ($ticket->response_message): ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="mb-3 d-flex justify-content-end">
                            <div class="d-flex" style="flex-direction: row-reverse;">
                                <div class="ms-3">
                                    <div class="avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: #28a745; display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 text-end">
                                    <div class="bg-primary text-white p-3 rounded" style="display: inline-block; max-width: 70%; text-align: left;">
                                        <p class="mb-1"><strong>Support Team</strong></p>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($message)); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments" style="font-size: 3rem; opacity: 0.5;"></i>
                            <p class="mt-3">No responses yet. Our support team will reply soon.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reply Form -->
                <?php if ($ticket->status !== 'closed'): ?>
                <div class="card-footer border-top" style="background: white;">
                    <div id="replyAlertContainer"></div>

                    <form id="replyForm" class="d-flex gap-2">
                        <textarea name="message" class="form-control" rows="2" placeholder="Type your reply here..." required></textarea>
                        <button type="submit" class="btn btn-primary" style="width: 100px;" id="sendBtn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card-footer text-center text-muted" style="background: #fff3cd;">
                    <i class="fas fa-lock-alt me-2"></i> This ticket is closed. You cannot reply to closed tickets.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar - Ticket Info -->
        <div class="col-lg-4">
            <!-- Ticket Details Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i> Ticket Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Ticket Number</label>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($ticket->ticket_number); ?></strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block">Status</label>
                        <span class="badge bg-<?php echo $statusColors[$ticket->status] ?? 'secondary'; ?> fs-6">
                            <i class="fas fa-circle-notch me-1"></i> <?php echo $statuses[$ticket->status] ?? ucfirst($ticket->status); ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block">Priority</label>
                        <span class="badge bg-<?php echo $priorityColors[$ticket->priority] ?? 'secondary'; ?> fs-6">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $priorities[$ticket->priority] ?? ucfirst($ticket->priority); ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block">Category</label>
                        <p class="mb-0"><?php echo $categories[$ticket->category] ?? ucfirst($ticket->category); ?></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block">Opened Date</label>
                        <p class="mb-0"><?php echo date('M d, Y H:i', strtotime($ticket->opened_at)); ?></p>
                    </div>

                    <?php if ($ticket->resolved_at): ?>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Resolved Date</label>
                        <p class="mb-0"><?php echo date('M d, Y H:i', strtotime($ticket->resolved_at)); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i> Actions</h6>
                </div>
                <div class="card-body">
                    <?php if ($ticket->status !== 'closed'): ?>
                        <button type="button" class="btn btn-warning w-100" id="closeTicketBtn">
                            <i class="fas fa-times-circle me-2"></i> Close Ticket
                        </button>
                        <hr>
                    <?php endif; ?>
                    
                    <a href="<?php echo app_url('/support/tickets'); ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i> Need Help?</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        If you have any additional questions or need further assistance, feel free to reply to this ticket.
                    </p>
                    <p class="small text-muted">
                        Our support team typically responds within 24 hours during business days.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
body {
    background-color: #f5f5f5 !important;
}

.card {
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

/* Messages Container Styling */
#messagesContainer {
    overflow-y: auto;
    scroll-behavior: smooth;
}

/* Avatar Styling */
.avatar-sm {
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .col-lg-4 {
        margin-top: 2rem;
    }
}

@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem;
    }
    
    .bg-white {
        max-width: 100% !important;
    }
}

/* Scrollbar styling */
#messagesContainer::-webkit-scrollbar {
    width: 8px;
}

#messagesContainer::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#messagesContainer::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#messagesContainer::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Button Animations */
.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Form styling */
.form-control {
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

textarea.form-control {
    resize: vertical;
}
</style>

<script>
// Test AJAX first
fetch('<?php echo app_url('/ajax-test'); ?>', {
    method: 'GET',
    credentials: 'same-origin'
})
.then(r => r.json())
.then(data => {
    console.log('✓ AJAX Test Success:', data);
})
.catch(err => {
    console.error('✗ AJAX Test Failed:', err);
});

// AJAX functionality for sending messages and closing tickets
document.addEventListener('DOMContentLoaded', function() {
    const replyForm = document.getElementById('replyForm');
    const sendBtn = document.getElementById('sendBtn');
    const closeTicketBtn = document.getElementById('closeTicketBtn');
    const messagesContainer = document.getElementById('messagesContainer');
    const alertContainer = document.getElementById('replyAlertContainer');
    const ticketId = <?php echo $ticketId; ?>;

    // Handle reply form submission
    if (replyForm) {
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const message = replyForm.querySelector('textarea[name="message"]').value.trim();

            if (!message) {
                showAlert('Message cannot be empty', 'danger');
                return;
            }

            if (message.length > 5000) {
                showAlert('Message cannot exceed 5000 characters', 'danger');
                return;
            }

            // Disable button and show loading state
            sendBtn.disabled = true;
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

            try {
                const response = await fetch('<?php echo app_url('/support/ticket'); ?>/' + ticketId + '/reply', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: message })
                });

                const data = await response.json();

                if (response.ok) {
                    showAlert('Message sent successfully!', 'success');
                    replyForm.querySelector('textarea[name="message"]').value = '';
                    
                    // Add new message to chat
                    addMessageToChat(message, true);
                    
                    // Scroll to bottom
                    setTimeout(() => {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 100);
                } else {
                    showAlert(data.error || 'Failed to send message', 'danger');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'danger');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            }
        });
    }

    // Handle close ticket button
    if (closeTicketBtn) {
        closeTicketBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to close this ticket? You will not be able to reply after closing.')) {
                return;
            }

            closeTicketBtn.disabled = true;
            const originalText = closeTicketBtn.innerHTML;
            closeTicketBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Closing...';

            try {
                const response = await fetch('<?php echo app_url('/support/ticket'); ?>/' + ticketId + '/close', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (response.ok) {
                    showAlert('Ticket closed successfully!', 'success');
                    
                    // Hide reply form and button
                    const replyFormSection = document.querySelector('.card-footer');
                    if (replyFormSection && !replyFormSection.style.display) {
                        replyFormSection.outerHTML = `
                            <div class="card-footer text-center text-muted" style="background: #fff3cd;">
                                <i class="fas fa-lock-alt me-2"></i> This ticket is closed. You cannot reply to closed tickets.
                            </div>
                        `;
                    }
                    
                    // Hide action button
                    closeTicketBtn.parentElement.innerHTML = `<a href="<?php echo app_url('/support/tickets'); ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i> Back to List
                    </a>`;
                    
                    // Update status badge
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.error || 'Failed to close ticket', 'danger');
                    closeTicketBtn.disabled = false;
                    closeTicketBtn.innerHTML = originalText;
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'danger');
                closeTicketBtn.disabled = false;
                closeTicketBtn.innerHTML = originalText;
            }
        });
    }

    // Helper function to show alert
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHtml;
        
        // Auto-dismiss success alerts after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
    }

    // Helper function to add message to chat
    function addMessageToChat(message, isUser = false) {
        const messageHtml = isUser ? `
            <div class="mb-4">
                <div class="d-flex mb-3">
                    <div class="me-3">
                        <div class="avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="bg-white p-3 rounded" style="max-width: 70%;">
                            <p class="mb-1"><strong>You</strong></p>
                            <p class="mb-2">${escapeHtml(message)}</p>
                            <small class="text-muted">${new Date().toLocaleString()}</small>
                        </div>
                    </div>
                </div>
            </div>
        ` : `
            <div class="mb-3 d-flex justify-content-end">
                <div class="d-flex" style="flex-direction: row-reverse;">
                    <div class="ms-3">
                        <div class="avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; background: #28a745; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-headset"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 text-end">
                        <div class="bg-primary text-white p-3 rounded" style="display: inline-block; max-width: 70%; text-align: left;">
                            <p class="mb-1"><strong>Support Team</strong></p>
                            <p class="mb-0">${escapeHtml(message)}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
