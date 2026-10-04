<?php
// public/crm.php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Handle CRM actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_lead'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $source = $_POST['source'];
        $status = $_POST['status'];
        $notes = trim($_POST['notes']);
        
        // Insert new lead
        $stmt = $pdo->prepare("INSERT INTO crm_leads (name, email, phone, source, status, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $email, $phone, $source, $status, $notes, $_SESSION['admin_id']])) {
            $_SESSION['success_message'] = "Lead added successfully!";
            header("Location: crm.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add lead.";
        }
    }
    
    if (isset($_POST['update_lead'])) {
        $lead_id = intval($_POST['lead_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $source = $_POST['source'];
        $status = $_POST['status'];
        $notes = trim($_POST['notes']);
        
        // Update lead
        $stmt = $pdo->prepare("UPDATE crm_leads SET name = ?, email = ?, phone = ?, source = ?, status = ?, notes = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $email, $phone, $source, $status, $notes, $lead_id])) {
            $_SESSION['success_message'] = "Lead updated successfully!";
            header("Location: crm.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update lead.";
        }
    }
    
    if (isset($_POST['add_followup'])) {
        $lead_id = intval($_POST['lead_id']);
        $followup_date = $_POST['followup_date'];
        $followup_notes = trim($_POST['followup_notes']);
        $followup_status = $_POST['followup_status'];
        
        // Insert follow-up
        $stmt = $pdo->prepare("INSERT INTO crm_followups (lead_id, followup_date, notes, status, created_by) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$lead_id, $followup_date, $followup_notes, $followup_status, $_SESSION['admin_id']])) {
            // Update lead status if different
            if ($followup_status !== 'scheduled') {
                $stmt = $pdo->prepare("UPDATE crm_leads SET status = ? WHERE id = ?");
                $stmt->execute([$followup_status, $lead_id]);
            }
            
            $_SESSION['success_message'] = "Follow-up added successfully!";
            header("Location: crm.php?action=view&id=" . $lead_id);
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add follow-up.";
        }
    }
}

// Create CRM tables if they don't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS crm_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        source VARCHAR(50) DEFAULT 'website',
        status VARCHAR(20) DEFAULT 'new',
        notes TEXT,
        assigned_to INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS crm_followups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT,
        followup_date DATETIME,
        notes TEXT,
        status VARCHAR(20) DEFAULT 'scheduled',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Get leads with filters
$where_clause = "";
$params = [];
if (!empty($status_filter)) {
    $where_clause = "WHERE status = ?";
    $params[] = $status_filter;
}

$leads = $pdo->prepare("
    SELECT l.*, u.full_name as assigned_name 
    FROM crm_leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    $where_clause 
    ORDER BY l.created_at DESC
");
$leads->execute($params);
$leads = $leads->fetchAll();

// Get specific lead for viewing/editing
$current_lead = null;
$lead_followups = [];
if ($action === 'view' && isset($_GET['id'])) {
    $lead_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT l.*, u.full_name as assigned_name FROM crm_leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?");
    $stmt->execute([$lead_id]);
    $current_lead = $stmt->fetch();
    
    if ($current_lead) {
        $stmt = $pdo->prepare("SELECT f.*, u.full_name as created_by_name FROM crm_followups f LEFT JOIN users u ON f.created_by = u.id WHERE f.lead_id = ? ORDER BY f.followup_date DESC");
        $stmt->execute([$lead_id]);
        $lead_followups = $stmt->fetchAll();
    }
}

// Set page title
$page_title = "CRM - Phool Delivery Admin";

// Include header
include '../app/views/layouts/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Customer Relationship Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal">
            <i class="fas fa-plus me-1"></i> Add New Lead
        </button>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($action === 'view' && $current_lead): ?>
<!-- Lead Detail View -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Lead Details: <?php echo htmlspecialchars($current_lead['name']); ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Name:</strong> <?php echo htmlspecialchars($current_lead['name']); ?><br>
                        <strong>Email:</strong> <?php echo !empty($current_lead['email']) ? htmlspecialchars($current_lead['email']) : 'N/A'; ?><br>
                        <strong>Phone:</strong> <?php echo !empty($current_lead['phone']) ? htmlspecialchars($current_lead['phone']) : 'N/A'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Source:</strong> <?php echo ucfirst($current_lead['source']); ?><br>
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php 
                        switch($current_lead['status']) {
                            case 'new': echo 'primary'; break;
                            case 'contacted': echo 'info'; break;
                            case 'qualified': echo 'success'; break;
                            case 'lost': echo 'danger'; break;
                            default: echo 'secondary';
                        }
                        ?>">
                            <?php echo ucfirst($current_lead['status']); ?>
                        </span><br>
                        <strong>Assigned To:</strong> <?php echo !empty($current_lead['assigned_name']) ? htmlspecialchars($current_lead['assigned_name']) : 'Unassigned'; ?>
                    </div>
                </div>
                
                <?php if (!empty($current_lead['notes'])): ?>
                <div class="mb-3">
                    <strong>Notes:</strong>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($current_lead['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editLeadModal">
                        <i class="fas fa-edit me-1"></i> Edit Lead
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                        <i class="fas fa-calendar-plus me-1"></i> Add Follow-up
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Follow-ups -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Follow-up History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($lead_followups)): ?>
                <p class="text-muted">No follow-ups yet.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($lead_followups as $followup): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <span class="badge bg-<?php 
                                switch($followup['status']) {
                                    case 'scheduled': echo 'info'; break;
                                    case 'completed': echo 'success'; break;
                                    case 'cancelled': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                                ?>">
                                    <?php echo ucfirst($followup['status']); ?>
                                </span>
                            </h6>
                            <small><?php echo date('M d, Y H:i', strtotime($followup['followup_date'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($followup['notes'])); ?></p>
                        <small class="text-muted">By: <?php echo htmlspecialchars($followup['created_by_name']); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="tel:<?php echo htmlspecialchars($current_lead['phone']); ?>" class="btn btn-primary">
                        <i class="fas fa-phone me-1"></i> Call Lead
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($current_lead['email']); ?>" class="btn btn-info">
                        <i class="fas fa-envelope me-1"></i> Email Lead
                    </a>
                    <a href="customers.php?action=add&name=<?php echo urlencode($current_lead['name']); ?>&email=<?php echo urlencode($current_lead['email']); ?>&phone=<?php echo urlencode($current_lead['phone']); ?>" class="btn btn-success">
                        <i class="fas fa-user-plus me-1"></i> Convert to Customer
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Lead Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-5 fw-bold">Created:</div>
                    <div class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($current_lead['created_at'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-5 fw-bold">Last Updated:</div>
                    <div class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($current_lead['updated_at'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-5 fw-bold">Total Follow-ups:</div>
                    <div class="col-sm-7"><?php echo count($lead_followups); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="lead_id" value="<?php echo $current_lead['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($current_lead['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_lead['email']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($current_lead['phone']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="source" class="form-label">Source</label>
                        <select class="form-select" id="source" name="source" required>
                            <option value="website" <?php echo $current_lead['source'] == 'website' ? 'selected' : ''; ?>>Website</option>
                            <option value="referral" <?php echo $current_lead['source'] == 'referral' ? 'selected' : ''; ?>>Referral</option>
                            <option value="social_media" <?php echo $current_lead['source'] == 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                            <option value="walk_in" <?php echo $current_lead['source'] == 'walk_in' ? 'selected' : ''; ?>>Walk-in</option>
                            <option value="other" <?php echo $current_lead['source'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="new" <?php echo $current_lead['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="contacted" <?php echo $current_lead['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="qualified" <?php echo $current_lead['status'] == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="lost" <?php echo $current_lead['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($current_lead['notes']); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_lead" class="btn btn-primary">Update Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Follow-up Modal -->
<div class="modal fade" id="addFollowupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="lead_id" value="<?php echo $current_lead['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="followup_date" class="form-label">Follow-up Date & Time</label>
                        <input type="datetime-local" class="form-control" id="followup_date" name="followup_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="followup_status" class="form-label">Status</label>
                        <select class="form-select" id="followup_status" name="followup_status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="followup_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="followup_notes" name="followup_notes" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_followup" class="btn btn-primary">Add Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Leads List -->
<div class="row mb-3">
    <div class="col-md-3">
        <select class="form-select" id="statusFilter" onchange="window.location.href='crm.php?status='+this.value">
            <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
            <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
            <option value="contacted" <?php echo $status_filter == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
            <option value="qualified" <?php echo $status_filter == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
            <option value="lost" <?php echo $status_filter == 'lost' ? 'selected' : ''; ?>>Lost</option>
        </select>
    </div>
    <div class="col-md-6">
        <input type="text" class="form-control" placeholder="Search leads..." id="searchInput">
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="leadsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?php echo $lead['id']; ?></td>
                        <td><?php echo htmlspecialchars($lead['name']); ?></td>
                        <td>
                            <div><?php echo !empty($lead['phone']) ? htmlspecialchars($lead['phone']) : 'N/A'; ?></div>
                            <?php if (!empty($lead['email'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($lead['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $lead['source'])); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                            switch($lead['status']) {
                                case 'new': echo 'primary'; break;
                                case 'contacted': echo 'info'; break;
                                case 'qualified': echo 'success'; break;
                                case 'lost': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                            ?>">
                                <?php echo ucfirst($lead['status']); ?>
                            </span>
                        </td>
                        <td><?php echo !empty($lead['assigned_name']) ? htmlspecialchars($lead['assigned_name']) : 'Unassigned'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="crm.php?action=view&id=<?php echo $lead['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editLeadModal<?php echo $lead['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            
                            <!-- Edit Lead Modal -->
                            <div class="modal fade" id="editLeadModal<?php echo $lead['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Lead</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Name</label>
                                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($lead['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($lead['email']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Phone</label>
                                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($lead['phone']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="source" class="form-label">Source</label>
                                                    <select class="form-select" id="source" name="source" required>
                                                        <option value="website" <?php echo $lead['source'] == 'website' ? 'selected' : ''; ?>>Website</option>
                                                        <option value="referral" <?php echo $lead['source'] == 'referral' ? 'selected' : ''; ?>>Referral</option>
                                                        <option value="social_media" <?php echo $lead['source'] == 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                                        <option value="walk_in" <?php echo $lead['source'] == 'walk_in' ? 'selected' : ''; ?>>Walk-in</option>
                                                        <option value="other" <?php echo $lead['source'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status" required>
                                                        <option value="new" <?php echo $lead['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                                        <option value="contacted" <?php echo $lead['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                        <option value="qualified" <?php echo $lead['status'] == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                                        <option value="lost" <?php echo $lead['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="notes" class="form-label">Notes</label>
                                                    <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($lead['notes']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_lead" class="btn btn-primary">Update Lead</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Lead Modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="new_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="new_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="new_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="new_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="new_source" class="form-label">Source</label>
                        <select class="form-select" id="new_source" name="source" required>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="social_media">Social Media</option>
                            <option value="walk_in">Walk-in</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Status</label>
                        <select class="form-select" id="new_status" name="status" required>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="new_notes" name="notes" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_lead" class="btn btn-primary">Add Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../app/views/layouts/footer.php';
?>