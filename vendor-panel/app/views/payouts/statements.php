<?php
// Load URL helpers
require_once dirname(__FILE__, 3) . '/helpers/url.php';

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;
$payouts = [];
if ($vendorId) {
    // Use actual column names from vendor_payouts table
    $stmt = $db->query('SELECT id, payout_period_start, payout_period_end, payout_amount, status, processed_at, requested_at FROM vendor_payouts WHERE vendor_id = ? ORDER BY COALESCE(processed_at, requested_at) DESC LIMIT 50', [$vendorId]);
    $payouts = $stmt->fetchAll();
}
?>

<div class="payouts-container">
    <h2>Payout Statements</h2>
    
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="/payouts" class="btn btn-primary">Request Payout</a>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payouts)): ?>
                    <?php foreach ($payouts as $pt): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($pt['payout_period_start'])) . ' - ' . date('M d, Y', strtotime($pt['payout_period_end'])); ?></td>
                            <td>₹<?php echo number_format((float)$pt['payout_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($pt['status'])); ?></td>
                            <td><?php echo !empty($pt['processed_at']) ? date('M d, Y', strtotime($pt['processed_at'])) : ( !empty($pt['requested_at']) ? date('M d, Y', strtotime($pt['requested_at'])) : '-'); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars(vendor_url('/payouts/view/' . $pt['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No payout records</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
