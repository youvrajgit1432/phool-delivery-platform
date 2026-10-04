<?php
if (!isset($_SESSION['rider_id'])) {
    require_once __DIR__ . '/../../helpers/url.php';
    header('Location: ' . app_url('/login'));
    exit;
}

require_once __DIR__ . '/../../helpers/url.php';
require_once __DIR__ . '/../../helpers/formatter.php';
require_once __DIR__ . '/../../models/Ticket.php';

use Phool\DeliveryPanel\Models\Ticket;

// Get PDO from global scope
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die('Database connection error');
}

$riderId = $_SESSION['rider_id'];
$ticketModel = new Ticket($pdo);

// Get tickets and statistics
$status = $_GET['status'] ?? null;
$tickets = $ticketModel->getByRiderId($riderId, $status);
$stats = $ticketModel->getStats($riderId);
$categories = Ticket::getCategories();
$statuses = Ticket::getStatuses();
$priorities = Ticket::getPriorities();

// Get priority color
$priorityColors = [
    'low' => 'bg-info',
    'medium' => 'bg-warning',
    'high' => 'bg-danger',
    'urgent' => 'bg-dark'
];

// Get status color
$statusColors = [
    'open' => 'bg-danger',
    'in_progress' => 'bg-primary',
    'resolved' => 'bg-success',
    'closed' => 'bg-secondary',
    'reopened' => 'bg-warning'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'other');
        $priority = trim($_POST['priority'] ?? 'medium');

        if (!empty($subject) && !empty($description)) {
            try {
                $ticketModel->create($riderId, $subject, $description, $category, $priority);
                $successMessage = "✓ Support ticket created successfully! Our team will respond shortly.";
                // Refresh tickets
                $tickets = $ticketModel->getByRiderId($riderId, $status);
                $stats = $ticketModel->getStats($riderId);
            } catch (\Exception $e) {
                $errorMessage = "Error creating ticket: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Subject and description are required";
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <!-- Statistics Cards -->
        <?php if ($stats): ?>
        <div class="row mb-4 g-2">
            <div class="col-3 col-md-3 mb-2">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Total Tickets</h6>
                        <p class="stat-value"><?php echo intval($stats->total); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-3 col-md-3 mb-2">
                <div class="stat-card">
                    <div class="stat-icon bg-danger">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Open</h6>
                        <p class="stat-value"><?php echo intval($stats->open_count); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-3 col-md-3 mb-2">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <h6>In Progress</h6>
                        <p class="stat-value"><?php echo intval($stats->in_progress_count); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-3 col-md-3 mb-2">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Resolved</h6>
                        <p class="stat-value"><?php echo intval($stats->resolved_count); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Create New Ticket Button -->
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Need Help?</h5>
                    <p class="text-muted mb-0 small">Contact our support team for any issues</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="fas fa-plus me-1"></i> Create New Ticket
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="card mb-3">
            <div class="card-body">
                <ul class="nav nav-pills flex-wrap" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo !$status ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/support/tickets'); ?>">
                            <i class="fas fa-list me-1"></i>All Tickets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'open' ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/support/tickets?status=open'); ?>">
                            <i class="fas fa-exclamation-circle me-1"></i>Open
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'in_progress' ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/support/tickets?status=in_progress'); ?>">
                            <i class="fas fa-hourglass-half me-1"></i>In Progress
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'resolved' ? 'active' : ''; ?>" 
                           href="<?php echo app_url('/support/tickets?status=resolved'); ?>">
                            <i class="fas fa-check-circle me-1"></i>Resolved
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Support Tickets Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Support Tickets</h5>
            </div>

            <?php if (count($tickets) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th class="d-none d-sm-table-cell">Priority</th>
                            <th class="d-none d-md-table-cell">Created</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket->ticket_number); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($ticket->subject); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?php echo $categories[$ticket->category] ?? ucfirst($ticket->category); ?>
                                </span>
                            </td>
                            <td class="d-none d-sm-table-cell">
                                <span class="badge <?php echo $priorityColors[$ticket->priority] ?? 'bg-secondary'; ?>">
                                    <?php echo $priorities[$ticket->priority] ?? ucfirst($ticket->priority); ?>
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <small><?php echo date('M d, Y', strtotime($ticket->opened_at)); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusColors[$ticket->status] ?? 'bg-secondary'; ?>">
                                    <?php echo $statuses[$ticket->status] ?? ucfirst($ticket->status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo app_url('/support/tickets/' . $ticket->id); ?>" 
                                       class="btn btn-outline-info" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i> <span>View</span>
                                    </a>
                                    <?php if ($ticket->status !== 'closed'): ?>
                                    <a href="<?php echo app_url('/support/tickets/' . $ticket->id); ?>#reply" 
                                       class="btn btn-outline-success" 
                                       title="Reply to ticket">
                                        <i class="fas fa-reply"></i> <span>Reply</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No support tickets <?php echo $status ? "with status '" . ucfirst($status) . "'" : ""; ?></p>
                <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="fas fa-plus me-1"></i>Create Your First Ticket
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- FAQ Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
            </div>
            <div class="accordion accordion-flush" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <i class="fas fa-clock me-2 text-primary"></i>How often are payments processed?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <strong>Payment Frequency depends on your delivery type:</strong>
                            <ul class="mt-2">
                                <li><strong>In-House:</strong> Fixed monthly salary + incentives</li>
                                <li><strong>Gig:</strong> Weekly or bi-weekly payouts based on completed deliveries</li>
                                <li><strong>Partner:</strong> Invoice-based settlement as per contract</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <i class="fas fa-road me-2 text-primary"></i>What is the minimum order distance?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The minimum order distance is typically <strong>0.5 km</strong>. Some special zones or during peak hours may have different minimums. Check your zone rules in the orders section.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <i class="fas fa-ban me-2 text-primary"></i>Can I decline orders?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <strong>Availability depends on your delivery type:</strong>
                            <ul class="mt-2">
                                <li><strong>In-House:</strong> Orders assigned by admin (no decline option)</li>
                                <li><strong>Gig:</strong> You can decline orders, but accepting most helps maintain good rating. Frequent declines may affect assignment</li>
                                <li><strong>Partner:</strong> Not applicable (handled by your company)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <i class="fas fa-gift me-2 text-primary"></i>How do bonuses work?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <strong>Bonus structure for Gig & In-House riders:</strong>
                            <ul class="mt-2">
                                <li><strong>On-time Delivery:</strong> +₹50 per delivery</li>
                                <li><strong>High Rating:</strong> +₹25 for ratings ≥4.8</li>
                                <li><strong>Distance Bonus:</strong> ₹40/km for long-distance orders</li>
                                <li><strong>Weekly Performance:</strong> Additional incentives for top performers</li>
                            </ul>
                            Check the Earnings page for your bonus summary.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            <i class="fas fa-wifi me-2 text-primary"></i>What if I lose internet connection?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can mark orders as picked up or delivered offline. Once your connection returns, the app will sync automatically with our servers. Make sure your device has location enabled for accurate tracking.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            <i class="fas fa-user-tie me-2 text-primary"></i>I'm a Partner Company - What support is available?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Partner companies have dedicated support for:
                            <ul class="mt-2">
                                <li>API integration and technical setup</li>
                                <li>Order assignment and routing</li>
                                <li>Invoice and settlement queries</li>
                                <li>SLA and performance metrics</li>
                            </ul>
                            Please contact our Partner Support team via the phone/email below.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-phone me-2"></i>Contact Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><i class="fas fa-phone text-primary me-2"></i> <strong>Support Phone:</strong> <br><a href="tel:+977-1-4123456">+977 1-4123456</a></p>
                        <p><i class="fas fa-envelope text-primary me-2"></i> <strong>Support Email:</strong> <br><a href="mailto:support@phooldelivery.example">support@phooldelivery.example</a></p>
                    </div>
                    <div class="col-md-6">
                        <p><i class="fas fa-clock text-primary me-2"></i> <strong>Support Hours:</strong> <br>9:00 AM - 6:00 PM (Monday - Saturday)</p>
                        <p><i class="fas fa-map-marker-alt text-primary me-2"></i> <strong>Office:</strong> <br>New Road, Kathmandu</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create Support Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_ticket">
                    
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                        <select class="form-select" name="priority" required>
                            <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $key === 'medium' ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" placeholder="Brief title..." maxlength="255" required>
                        <small class="text-muted">Max 255 characters</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" rows="5" placeholder="Provide detailed description..." maxlength="5000" required></textarea>
                        <small class="text-muted">Max 5000 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Statistics Card Styling */
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    min-height: 110px;
}

.stat-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h6 {
    margin: 0;
    font-size: 0.65rem;
    color: #999;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.stat-value {
    margin: 0.25rem 0 0 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: #333;
}

/* Responsive Table */
@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.8rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
}

/* Accordion Enhancement */
.accordion-button {
    padding: 1rem;
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #667eea;
}

.accordion-button::after {
    filter: invert(0.5);
}

/* Modal Enhancement */
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-title {
    font-weight: 600;
}

/* Form Enhancement */
.form-label {
    font-weight: 600;
    color: #333;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* Status Badge Colors */
.badge {
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    border-radius: 6px;
}

/* Action Buttons */
.btn-group-sm .btn {
    padding: 0.4rem 0.75rem;
    font-size: 0.875rem;
    white-space: nowrap;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn-outline-info {
    color: #17a2b8;
    border-color: #17a2b8;
}

.btn-outline-info:hover {
    background-color: #17a2b8;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
}

.btn-outline-success {
    color: #28a745;
    border-color: #28a745;
}

.btn-outline-success:hover {
    background-color: #28a745;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.btn-group-sm {
    display: flex;
    gap: 0.3rem;
}

@media (max-width: 576px) {
    .btn-group-sm .btn {
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .btn-group-sm .btn i {
        margin-right: 0.2rem;
    }
    
    .btn-group-sm .btn span {
        display: none;
    }
}
</style>
