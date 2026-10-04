<?php
/**
 * Vendor Payouts Management
 * Admin can manage payouts and settlements for vendors
 */

require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    header('Location: ../vendors.php');
    exit;
}

// Get database connection
$db = getDBConnection();

// Fetch vendor details
try {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: ../vendors.php');
        exit;
    }
    
    // Calculate vendor statistics
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN vo.status = 'completed' THEN vo.total_amount ELSE 0 END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN vo.status = 'completed' THEN (vo.total_amount * ? / 100) ELSE 0 END), 0) as total_commission,
            COALESCE(SUM(vp.amount), 0) as total_paid_out,
            COUNT(DISTINCT CASE WHEN vp.status = 'pending' THEN vp.id END) as pending_payouts
        FROM vendors v
        LEFT JOIN vendor_orders vo ON v.id = vo.vendor_id
        LEFT JOIN vendor_payouts vp ON v.id = vp.vendor_id
        WHERE v.id = ?
    ");
    $stmt->execute([$vendor['commission_rate'] ?? 5, $vendor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch vendor's payouts
    $stmt = $db->prepare("
        SELECT * FROM vendor_payouts
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$vendor_id]);
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
    $payouts = [];
    $stats = [];
}

$page_title = 'Payouts - ' . htmlspecialchars($vendor['store_name']);
$current_page = 'vendor-payouts';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/views/layouts/hheader.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <a href="../vendors/edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left"></i> Back to Vendor
                </a>
                <h1 class="h3">Payouts - <?php echo htmlspecialchars($vendor['store_name']); ?></h1>
                <p class="text-muted">Manage vendor payouts and financial settlements</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Financial Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-graph-up"></i> Total Sales</h6>
                        <h3 class="mb-0">Rs <?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-percent"></i> Commission</h6>
                        <h3 class="mb-0">Rs <?php echo number_format($stats['total_commission'] ?? 0, 2); ?></h3>
                        <small class="text-muted"><?php echo $vendor['commission_rate'] ?? 5; ?>% rate</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-cash-coin"></i> Paid Out</h6>
                        <h3 class="mb-0">Rs <?php echo number_format($stats['total_paid_out'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-hourglass-split"></i> Pending</h6>
                        <h3 class="mb-0"><?php echo $stats['pending_payouts'] ?? 0; ?> Payouts</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchPayouts" placeholder="Search by reference...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newPayoutModal">
                    <i class="bi bi-plus-circle"></i> New Payout
                </button>
            </div>
        </div>

        <!-- Payouts Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Processed Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payouts)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="bi bi-inbox"></i> No payouts found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payouts as $payout): ?>
                                <tr class="payout-row" data-payout-status="<?php echo strtolower($payout['status']); ?>">
                                    <td><strong><?php echo htmlspecialchars($payout['reference_id']); ?></strong></td>
                                    <td>Rs <?php echo number_format($payout['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($payout['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'completed' => 'success',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($payout['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($payout['created_at'])); ?></td>
                                    <td><?php echo $payout['processed_at'] ? date('M d, Y', strtotime($payout['processed_at'])) : '—'; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewPayout(<?php echo $payout['id']; ?>)" title="View">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($payout['status'] === 'pending'): ?>
                                                <button class="btn btn-outline-success" onclick="approvePayout(<?php echo $payout['id']; ?>)" title="Approve">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="rejectPayout(<?php echo $payout['id']; ?>)" title="Reject">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Payout Modal -->
    <div class="modal fade" id="newPayoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Payout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="newPayoutForm">
                    <div class="modal-body">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="amount" min="0" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reference ID</label>
                            <input type="text" class="form-control" name="reference_id" placeholder="e.g., TXN123456">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Payout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../app/views/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchPayouts').addEventListener('keyup', filterPayouts);
        document.getElementById('filterStatus').addEventListener('change', filterPayouts);

        function filterPayouts() {
            const searchTerm = document.getElementById('searchPayouts').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const payoutRows = document.querySelectorAll('.payout-row');

            payoutRows.forEach(row => {
                const status = row.dataset.payoutStatus;
                const payoutInfo = row.textContent.toLowerCase();
                
                const matchesSearch = payoutInfo.includes(searchTerm) || searchTerm === '';
                const matchesStatus = status === statusFilter || statusFilter === '';
                
                row.style.display = (matchesSearch && matchesStatus) ? 'table-row' : 'none';
            });
        }

        document.getElementById('newPayoutForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('../ajax/payout-create.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Payout created successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        });

        function approvePayout(payoutId) {
            if (confirm('Approve this payout?')) {
                updatePayoutStatus(payoutId, 'approved');
            }
        }

        function rejectPayout(payoutId) {
            if (confirm('Reject this payout?')) {
                updatePayoutStatus(payoutId, 'rejected');
            }
        }

        function updatePayoutStatus(payoutId, status) {
            fetch('../ajax/payout-update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'payout_id=' + payoutId + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payout status updated');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
