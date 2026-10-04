<?php
/**
 * Rider Penalties Management
 * Admin interface to manage rider penalties, suspensions, and approvals
 */

require_once '../bootstrap/app.php';
require_once '../app/middleware/AdminMiddleware.php';

requireAuth();

$page_title = 'Rider Penalties & Approvals';
$current_page = 'riders';

$db = getDBConnection();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'penalties';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query based on tab
if ($tab === 'approvals') {
    // Pending rider approvals
    $where = "WHERE r.status = 'pending' AND r.deleted_at IS NULL";
    $query = "SELECT r.id, r.first_name, r.last_name, r.email, r.phone, r.rider_type,
                     r.email_verified, r.documents_verified, r.bank_verified,
                     r.created_at,
                     (SELECT COUNT(*) FROM rider_documents WHERE rider_id = r.id) as doc_count
              FROM riders r
              $where
              ORDER BY r.created_at ASC";
    
    $items = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    $title = 'Pending Rider Approvals';

} else {
    // Penalties management
    $where = "WHERE 1=1";
    
    if (!empty($status_filter)) {
        if ($status_filter === 'pending') {
            $where .= " AND rp.status = 'pending'";
        } elseif ($status_filter === 'appealed') {
            $where .= " AND rp.status = 'appealed'";
        } elseif ($status_filter === 'resolved') {
            $where .= " AND rp.status IN ('approved', 'resolved', 'rejected')";
        }
    }

    $query = "SELECT rp.*, r.first_name, r.last_name, r.email,
                     (SELECT COUNT(*) FROM rider_orders WHERE rider_id = r.id) as total_orders
              FROM rider_penalties rp
              LEFT JOIN riders r ON rp.rider_id = r.id
              $where
              ORDER BY rp.created_at DESC";
    
    $items = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    $title = 'Rider Penalties Management';
}

include '../app/views/layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="riders.php" class="btn btn-outline-secondary mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Riders
            </a>
            <h1 class="h3"><?php echo $title; ?></h1>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab === 'penalties' ? 'active' : ''; ?>" 
               href="?tab=penalties">
                <i class="fas fa-exclamation-triangle me-2"></i>Penalties Management
                <?php 
                $pending_count = $db->query("SELECT COUNT(*) as count FROM rider_penalties WHERE status = 'pending'")->fetch()['count'];
                if ($pending_count > 0) echo '<span class="badge bg-danger ms-2">' . $pending_count . '</span>';
                ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab === 'approvals' ? 'active' : ''; ?>" 
               href="?tab=approvals">
                <i class="fas fa-check-circle me-2"></i>Pending Approvals
                <?php 
                $approval_count = $db->query("SELECT COUNT(*) as count FROM riders WHERE status = 'pending' AND deleted_at IS NULL")->fetch()['count'];
                if ($approval_count > 0) echo '<span class="badge bg-warning ms-2">' . $approval_count . '</span>';
                ?>
            </a>
        </li>
    </ul>

    <?php if ($tab === 'approvals'): ?>
        <!-- Pending Approvals Tab -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Riders Awaiting Approval</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Verification</th>
                            <th>Documents</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></strong></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($item['email']); ?><br>
                                        <?php echo htmlspecialchars($item['phone']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['rider_type'] === 'in_house' ? 'primary' : 
                                             ($item['rider_type'] === 'gig' ? 'info' : 'success');
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['rider_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['email_verified']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Email</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['documents_verified'] ? 'success' : 'warning'; ?>">
                                        <?php echo $item['doc_count']; ?> docs
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="riders/view.php?id=<?php echo $item['id']; ?>" class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-success" onclick="approveRider(<?php echo $item['id']; ?>)" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="rejectRider(<?php echo $item['id']; ?>)" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="alert alert-info mb-0">No pending approvals</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- Penalties Tab -->
        <div class="card mb-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Rider Penalties</h6>
                    <div>
                        <a href="?tab=penalties" class="btn btn-sm btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">All</a>
                        <a href="?tab=penalties&status=pending" class="btn btn-sm btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?tab=penalties&status=appealed" class="btn btn-sm btn-outline-info <?php echo $status_filter === 'appealed' ? 'active' : ''; ?>">Appealed</a>
                        <a href="?tab=penalties&status=resolved" class="btn btn-sm btn-outline-success <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">Resolved</a>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Rider</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $penalty): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($penalty['first_name'] . ' ' . $penalty['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($penalty['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $penalty['penalty_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($penalty['reason'] ?? 'No reason specified'); ?></small>
                                </td>
                                <td>
                                    <strong>Rs. <?php echo number_format($penalty['amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $penalty['status'] === 'pending' ? 'warning' : 
                                             ($penalty['status'] === 'appealed' ? 'info' : 
                                              ($penalty['status'] === 'approved' ? 'danger' : 'success'));
                                    ?>">
                                        <?php echo ucfirst($penalty['status']); ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo date('M d, Y', strtotime($penalty['created_at'])); ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-info" onclick="viewPenaltyDetails(<?php echo $penalty['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($penalty['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-success" onclick="approvePenalty(<?php echo $penalty['id']; ?>)" title="Approve Penalty">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="rejectPenalty(<?php echo $penalty['id']; ?>)" title="Reject Penalty">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($penalty['status'] === 'appealed'): ?>
                                            <button type="button" class="btn btn-success" onclick="resolvePenaltyAppeal(<?php echo $penalty['id']; ?>, true)" title="Approve Appeal">
                                                <i class="fas fa-thumbs-up"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="resolvePenaltyAppeal(<?php echo $penalty['id']; ?>, false)" title="Reject Appeal">
                                                <i class="fas fa-thumbs-down"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="alert alert-info mb-0">No penalties found</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function approveRider(riderId) {
    if (!confirm('Approve this rider for delivery service?')) return;
    
    fetch('ajax/approve-rider.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_id: riderId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Rider approved successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(function(err) {
        console.error('Error:', err);
        alert('Request failed');
    });
}

function rejectRider(riderId) {
    var reason = prompt('Enter reason for rejection:');
    if (!reason) return;
    
    fetch('ajax/reject-rider.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rider_id: riderId, reason: reason })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Rider rejected');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(function(err) {
        alert('Request failed');
    });
}

function approvePenalty(penaltyId) {
    if (!confirm('Approve this penalty?')) return;
    
    fetch('ajax/approve-penalty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ penalty_id: penaltyId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Penalty approved');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(function(err) {
        alert('Request failed');
    });
}

function rejectPenalty(penaltyId) {
    if (!confirm('Reject this penalty?')) return;
    
    fetch('ajax/reject-penalty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ penalty_id: penaltyId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Penalty rejected');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(function(err) {
        alert('Request failed');
    });
}

function resolvePenaltyAppeal(penaltyId, approve) {
    var msg = approve ? 'Approve this appeal?' : 'Reject this appeal?';
    if (!confirm(msg)) return;
    
    fetch('ajax/resolve-penalty-appeal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ penalty_id: penaltyId, approved: approve })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Appeal resolved');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed'));
        }
    })
    .catch(function(err) {
        alert('Request failed');
    });
}

function viewPenaltyDetails(penaltyId) {
    alert('Feature coming soon: View penalty details');
    // Will implement detailed view later
}
</script>

<?php
include '../app/views/layouts/footer.php';
?>
