<?php
/**
 * Payouts Page - Vendor Panel
 * Display and manage payouts
 */

$dbConfig = $GLOBALS['config']['database'] ?? [];
$db = \App\Database\Connection::getInstance($dbConfig);
$vendorId = $_SESSION['vendor_id'] ?? null;

// Initialize variables
$availableBalance = 0;
$pendingBalance = 0;
$totalEarnings = 0;
$processedPayouts = 0;
$nextSettlementDate = date('Y-m-d', strtotime('next Monday'));
$daysUntilSettlement = 0;
$payoutHistory = [];
$vendorBankDetails = null;

if ($vendorId) {
    $vendorId = (int)$vendorId;
    
    // Get next settlement date (next Monday)
    $today = new \DateTime();
    $nextMonday = new \DateTime();
    if ($today->format('N') == 1) {
        $nextMonday->modify('+7 days');
    } else {
        $nextMonday->modify('next Monday');
    }
    $nextSettlementDate = $nextMonday->format('Y-m-d');
    
    // Calculate days until settlement
    $daysUntilSettlement = $nextMonday->diff($today)->days;
    
    // Get last settlement date (last Monday)
    $lastMonday = new \DateTime();
    if ($today->format('N') == 1) {
        $lastMonday->modify('-7 days');
    } else {
        $lastMonday->modify('last Monday');
    }
    $lastSettlementDate = $lastMonday->format('Y-m-d');
    
    // Calculate total earnings from delivered/picked_up orders
    $stmt = $db->query('
        SELECT SUM(vo.subtotal - vo.discount) as total_amount
        FROM vendor_orders vo
        WHERE vo.vendor_id = ? AND vo.status IN ("delivered", "picked_up")
    ', [$vendorId]);
    $totalResult = $stmt->fetch();
    $totalEarnings = (float)($totalResult['total_amount'] ?? 0);
    
    // Calculate earnings from last settlement to today (to settlement)
    $stmt = $db->query('
        SELECT 
            SUM(vo.subtotal - vo.discount) as total_amount,
            COUNT(vo.id) as order_count
        FROM vendor_orders vo
        WHERE vo.vendor_id = ? 
        AND vo.status IN ("delivered", "picked_up")
        AND DATE(COALESCE(vo.completed_at, vo.updated_at)) >= ?
    ', [$vendorId, $lastSettlementDate]);
    $earningsResult = $stmt->fetch();
    $pendingBalance = (float)($earningsResult['total_amount'] ?? 0);
    
    // Get processed payouts total
    $stmt = $db->query('
        SELECT SUM(payout_amount) as total_amount
        FROM vendor_payouts
        WHERE vendor_id = ? AND status = "processed"
    ', [$vendorId]);
    $payoutResult = $stmt->fetch();
    $processedPayouts = (float)($payoutResult['total_amount'] ?? 0);
    
    // Available balance = total earnings - processed payouts
    $availableBalance = $totalEarnings - $processedPayouts;
    
    // Get payout history
    $stmt = $db->query('
        SELECT 
            id,
            payout_period_start,
            payout_period_end,
            payout_amount,
            status,
            processed_at,
            requested_at
        FROM vendor_payouts
        WHERE vendor_id = ?
        ORDER BY COALESCE(processed_at, requested_at) DESC
        LIMIT 50
    ', [$vendorId]);
    $payoutHistory = $stmt->fetchAll();
    
    // Get vendor bank details from vendors table
    $stmt = $db->query('
        SELECT 
            bank_name,
            account_number,
            ifsc_code,
            account_holder,
            first_name,
            last_name
        FROM vendors
        WHERE id = ?
    ', [$vendorId]);
    $vendorBankDetails = $stmt->fetch();
}
?>

<style>
/* Modern Payouts Page Styles */
.payouts-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.btn-outline {
    background: white;
    border: 2px solid #e0e0e0;
    color: #666;
    padding: 12px 24px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    border-color: #00a854;
    color: #00a854;
}

/* Pending Requests */
.pending-request-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 16px;
    padding: 32px;
    margin: 0 auto;
    max-width: 800px;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.request-header h5 {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.request-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
    padding: 24px;
    background: #f8f9fa;
    border-radius: 12px;
}

.detail-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.detail-value {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

/* Timeline */
.request-timeline {
    position: relative;
    padding-left: 40px;
}

.request-timeline::before {
    content: '';
    position: absolute;
    left: 16px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #00a854, #e0e0e0);
}

.timeline-item {
    position: relative;
    margin-bottom: 32px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    position: absolute;
    left: -48px;
    top: 0;
    z-index: 2;
}

.timeline-item.completed .timeline-marker {
    background: linear-gradient(135deg, #00a854 0%, #00c853 100%);
    color: white;
}

.timeline-item.active .timeline-marker {
    background: white;
    color: #ff9500;
    border: 3px solid #ff9500;
}

.timeline-item:not(.completed):not(.active) .timeline-marker {
    background: #e0e0e0;
    color: #999;
}

.timeline-content {
    flex: 1;
}

.timeline-content strong {
    display: block;
    font-size: 16px;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.timeline-content small {
    color: #666;
    font-size: 14px;
}

/* Settings */
.payout-settings {
    max-width: 800px;
    margin: 0 auto;
}

.alert-info {
    background: linear-gradient(135deg, #e6f7ff 0%, #f0faff 100%);
    border: 1px solid #91d5ff;
    border-radius: 12px;
    color: #0066cc;
}

.alert-info i {
    color: #1890ff;
}

.bank-account-card {
    background: white;
    border: 2px solid #00a854;
    border-radius: 16px;
    padding: 24px;
    margin-top: 16px;
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
}

.account-status-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: linear-gradient(135deg, #00a854 0%, #00c853 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.account-content h6 {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 16px;
}

.account-details p {
    margin: 8px 0;
    color: #666;
}

.account-details strong {
    color: #333;
    min-width: 140px;
    display: inline-block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .payouts-container {
        padding: 16px;
    }
    
    .payout-card {
        padding: 20px;
    }
    
    .card-amount {
        font-size: 28px;
    }
    
    .btn {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .btn-outline {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .pending-request-card {
        padding: 20px;
    }
    
    .request-details {
        grid-template-columns: 1fr;
        padding: 16px;
    }
    
    .request-timeline {
        padding-left: 32px;
    }
    
    .timeline-marker {
        left: -40px;
    }
    
    .bank-account-card {
        flex-direction: column;
        gap: 16px;
    }
}

@media (max-width: 480px) {
    .payouts-container {
        padding: 12px;
    }
    
    .payout-card {
        padding: 20px;
    }
    
    .card-amount {
        font-size: 24px;
    }
    
    .btn {
        padding: 10px 16px;
        font-size: 14px;
    }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 16px;
    color: #e0e0e0;
} 

.empty-state h4 {
    font-size: 18px;
    color: #666;
    margin-bottom: 8px;
}

.empty-state p {
    color: #999;
    margin-bottom: 24px;
}
</style>

<div class="payouts-container">
    <!-- Header Section -->
    <div style="margin-bottom: 32px;">
        <h2 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0 0 8px 0;">
            <i class="fas fa-wallet" style="color: #00a854; margin-right: 12px;"></i>Payouts & Earnings
        </h2>
        <p style="color: #666; margin: 0;">Manage your earnings, track settlements, and view payout history</p>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 32px;">
        <!-- Available Balance Card -->
        <div style="background: white; border-radius: 12px; padding: 20px; border-left: 4px solid #00a854; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="color: #666; font-size: 13px; margin-bottom: 12px; font-weight: 500;">
                <i class="fas fa-check-circle" style="color: #00a854; margin-right: 6px;"></i>Available Balance
            </div>
            <div style="font-size: 32px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px;">₹<?php echo number_format($availableBalance, 2); ?></div>
            <div style="color: #999; font-size: 12px;">Ready for withdrawal</div>
        </div>

        <!-- Next Settlement Card -->
        <div style="background: white; border-radius: 12px; padding: 20px; border-left: 4px solid #ff9500; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="color: #666; font-size: 13px; margin-bottom: 12px; font-weight: 500;">
                <i class="fas fa-clock" style="color: #ff9500; margin-right: 6px;"></i>Next Settlement
            </div>
            <div style="font-size: 32px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px;">₹<?php echo number_format($pendingBalance, 2); ?></div>
            <div style="color: #999; font-size: 12px;"><?php echo date('M d', strtotime($nextSettlementDate)); ?> • <?php echo $daysUntilSettlement; ?> days</div>
        </div>

        <!-- Total Earnings Card -->
        <div style="background: white; border-radius: 12px; padding: 20px; border-left: 4px solid #007aff; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="color: #666; font-size: 13px; margin-bottom: 12px; font-weight: 500;">
                <i class="fas fa-chart-line" style="color: #007aff; margin-right: 6px;"></i>Total Earnings
            </div>
            <div style="font-size: 32px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px;">₹<?php echo number_format($totalEarnings, 2); ?></div>
            <div style="color: #999; font-size: 12px;">All-time earnings</div>
        </div>

        <!-- Paid Out Card -->
        <div style="background: white; border-radius: 12px; padding: 20px; border-left: 4px solid #5856d6; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="color: #666; font-size: 13px; margin-bottom: 12px; font-weight: 500;">
                <i class="fas fa-arrow-right" style="color: #5856d6; margin-right: 6px;"></i>Paid Out
            </div>
            <div style="font-size: 32px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px;">₹<?php echo number_format($processedPayouts, 2); ?></div>
            <div style="color: #999; font-size: 12px;">Successfully transferred</div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 24px;">
        <div style="display: flex; border-bottom: 1px solid #e0e0e0; background: #f8f9fa;">
            <button style="flex: 1; padding: 16px; background: none; border: none; color: #666; font-weight: 600; cursor: pointer; transition: all 0.3s;" class="tab-btn" data-tab="history">
                <i class="fas fa-history me-2"></i>Payout History
            </button>
            <button style="flex: 1; padding: 16px; background: none; border: none; color: #00a854; font-weight: 600; cursor: pointer; transition: all 0.3s; border-bottom: 3px solid #00a854;" class="tab-btn active" data-tab="pending">
                <i class="fas fa-hourglass-end me-2"></i>Pending Settlement
            </button>
            <button style="flex: 1; padding: 16px; background: none; border: none; color: #666; font-weight: 600; cursor: pointer; transition: all 0.3s;" class="tab-btn" data-tab="settings">
                <i class="fas fa-cog me-2"></i>Bank & Settings
            </button>
        </div>

        <!-- Payout History Tab -->
        <div class="tab-content" id="history-content" style="display: none; padding: 20px;">
            <div style="margin-bottom: 16px;">
                <input type="text" id="payoutSearch" placeholder="Search payouts..." style="width: 100%; padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <select id="statusFilter" style="padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                    <option value="">All Status</option>
                    <option value="processed">Processed</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select id="dateFilter" style="padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                    <option value="">All Time</option>
                    <option value="30days">Last 30 days</option>
                    <option value="90days">Last 90 days</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #f0f0f0;">
                <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Payout ID</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Period</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Amount</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Processed Date</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #666; font-size: 13px; border-bottom: 1px solid #e0e0e0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="payoutTableBody">
                        <?php if (empty($payoutHistory)): ?>
                        <tr>
                            <td colspan="6" style="padding: 40px 20px; text-align: center; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; color: #e0e0e0;"></i>
                                <strong>No payouts yet</strong><br>
                                <small>Your payout history will appear here</small>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($payoutHistory as $payout): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0; hover {background: #f8f9ff;}">
                                <td style="padding: 12px; color: #333;">#<?php echo str_pad($payout['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 12px; color: #333;" class="payout-period-cell"><?php echo date('M d, Y', strtotime($payout['payout_period_start'])); ?> - <?php echo date('M d, Y', strtotime($payout['payout_period_end'])); ?></td>
                                <td style="padding: 12px; color: #333; font-weight: 600;" class="payout-amount-cell">₹<?php echo number_format($payout['payout_amount'], 2); ?></td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: <?php echo $payout['status'] === 'processed' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $payout['status'] === 'processed' ? '#155724' : '#856404'; ?>;">
                                        <?php echo ucfirst($payout['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; color: #666; font-size: 13px;" class="payout-date-cell"><div><?php echo $payout['processed_at'] ? date('M d, Y', strtotime($payout['processed_at'])) : 'Pending'; ?></div></td>
                                <td style="padding: 12px;">
                                    <a href="javascript:downloadReceipt(<?php echo $payout['id']; ?>)" style="color: #00a854; text-decoration: none; font-size: 13px; font-weight: 600;">
                                        <i class="fas fa-download"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Settlement Tab -->
        <div class="tab-content" id="pending-content" style="display: block; padding: 20px;">
                <div class="pending-requests">
                    <div class="pending-request-card">
                        <div class="request-header">
                            <h5>Next Scheduled Payout</h5>
                            <span class="badge bg-info" style="background: #e7f3ff; color: #0066cc; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i>
                                <?php echo date('M d, Y', strtotime($nextSettlementDate)); ?>
                            </span>
                        </div>
                        <div class="request-details">
                            <div class="detail-row">
                                <span class="detail-label">Settlement Date</span>
                                <span class="detail-value"><?php echo date('l, M d, Y', strtotime($nextSettlementDate)); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount to Settle</span>
                                <span class="detail-value">₹<?php echo number_format($pendingBalance, 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Days Remaining</span>
                                <span class="detail-value"><?php echo $daysUntilSettlement; ?> days</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Frequency</span>
                                <span class="detail-value">Weekly (Every Monday)</span>
                            </div>
                        </div>
                        <div class="request-timeline">
                            <div class="timeline-item completed">
                                <div class="timeline-marker">✓</div>
                                <div class="timeline-content">
                                    <strong>Orders Completed</strong>
                                    <small>All delivered orders are tracked</small>
                                </div>
                            </div>
                            <div class="timeline-item completed">
                                <div class="timeline-marker">✓</div>
                                <div class="timeline-content">
                                    <strong>Amount Calculated</strong>
                                    <small>Earnings calculated and verified</small>
                                </div>
                            </div>
                            <div class="timeline-item active">
                                <div class="timeline-marker"><?php echo $daysUntilSettlement; ?></div>
                                <div class="timeline-content">
                                    <strong>Pending Settlement</strong>
                                    <small>Scheduled for <?php echo date('M d, Y', strtotime($nextSettlementDate)); ?></small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker">→</div>
                                <div class="timeline-content">
                                    <strong>Bank Transfer</strong>
                                    <small>Direct deposit to your bank account</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Settings Tab -->
        <div class="tab-content" id="settings-content" style="display: none; padding: 20px;">
            <div style="max-width: 600px;">
                <h5 style="font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 16px;">Bank Account Details</h5>
                
                <div style="background: #e7f3ff; border: 1px solid #91d5ff; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #0066cc; font-size: 14px;">
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    Your earnings are automatically transferred to your registered bank account every Monday
                </div>

                <div class="bank-account-card" style="background: white; border: 2px solid #00a854; border-radius: 12px; padding: 20px; position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                    <div style="position: absolute; top: 12px; right: 12px; background: linear-gradient(135deg, #00a854 0%, #00c853 100%); color: white; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 600;">Active</div>
                    <div style="flex: 1;">
                        <h6 style="font-size: 16px; font-weight: 700; color: #1a1a1a; margin-bottom: 12px;">Primary Bank Account</h6>
                        <div style="display: grid; gap: 8px; font-size: 14px;">
                            <p style="margin: 0; color: #333;"><strong style="color: #666; min-width: 140px; display: inline-block;">Account Holder:</strong> <?php echo htmlspecialchars($vendorBankDetails['account_holder'] ?? 'Not Set'); ?></p>
                            <p style="margin: 0; color: #333;"><strong style="color: #666; min-width: 140px; display: inline-block;">Bank Name:</strong> <?php echo htmlspecialchars($vendorBankDetails['bank_name'] ?? 'Not Set'); ?></p>
                            <p style="margin: 0; color: #333;"><strong style="color: #666; min-width: 140px; display: inline-block;">Account Number:</strong> <?php 
                                if (!empty($vendorBankDetails['account_number'])) {
                                    $accNum = $vendorBankDetails['account_number'];
                                    echo '••••••••' . substr($accNum, -4);
                                } else {
                                    echo 'Not Set';
                                }
                            ?></p>
                            <p style="margin: 0; color: #333;"><strong style="color: #666; min-width: 140px; display: inline-block;">Account Type:</strong> Savings Account</p>
                            <p style="margin: 0; color: #333;"><strong style="color: #666; min-width: 140px; display: inline-block;">IFSC Code:</strong> <?php echo htmlspecialchars($vendorBankDetails['ifsc_code'] ?? 'Not Set'); ?></p>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <a href="/account" class="btn btn-outline" style="padding: 8px 12px; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; background: white; border: 2px solid #e0e0e0; color: #666; border-radius: 8px; transition: all 0.3s; cursor: pointer;">
                            <i class="fas fa-edit" style="margin-right: 4px;"></i>Edit Details
                        </a>
                        <button class="btn btn-outline" onclick="verifyAccount()" style="padding: 8px 12px; font-size: 13px;">
                            <i class="fas fa-shield-alt" style="margin-right: 4px;"></i>Verify
                        </button>
                    </div>
                </div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">

                <h5 style="font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 16px;">Payout Schedule</h5>
                
                <div style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <i class="fas fa-calendar-check" style="color: #007aff; font-size: 18px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: rgba(0,122,255,0.1); border-radius: 8px;"></i>
                        <div style="flex: 1;">
                            <h6 style="font-size: 15px; font-weight: 700; color: #1a1a1a; margin: 0;">Weekly Payouts</h6>
                            <p style="color: #666; font-size: 13px; margin: 4px 0 0 0;">Every Monday at 9:00 AM</p>
                        </div>
                        <span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">Active</span>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; color: #333; font-weight: 600; margin-bottom: 8px; font-size: 14px;">Minimum Payout Amount</label>
                    <div style="display: flex; gap: 0;">
                        <span style="background: #f8f9fa; border: 1px solid #e0e0e0; padding: 10px 12px; border-radius: 8px 0 0 8px;">₹</span>
                        <input type="number" value="100" disabled style="flex: 1; padding: 10px 12px; border: 1px solid #e0e0e0; border-left: none; font-size: 14px; background: #f8f9fa;">
                        <span style="background: #f8f9fa; border: 1px solid #e0e0e0; padding: 10px 12px; border-radius: 0 8px 8px 0; border-left: none;">.00</span>
                    </div>
                    <small style="color: #999; font-size: 12px; margin-top: 6px; display: block;">Minimum amount required to process a payout</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        // Remove active state from all buttons
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.style.color = '#666';
            b.style.borderBottom = 'none';
        });
        
        // Show selected tab
        document.getElementById(tabName + '-content').style.display = 'block';
        
        // Set active state
        this.style.color = '#00a854';
        this.style.borderBottom = '3px solid #00a854';
    });
});

/**
 * Enhanced Payouts Management
 */

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search with debounce
    const searchInput = document.getElementById('payoutSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterPayoutTable();
            }, 300);
        });
    }
    
    // Initialize filters
    document.getElementById('statusFilter')?.addEventListener('change', filterPayoutTable);
    document.getElementById('dateFilter')?.addEventListener('change', filterPayoutTable);
    
    // Add interactive effects
    initializeInteractiveElements();
    
    // Check for new payouts periodically
    setInterval(checkForNewPayouts, 30000);
});

/**
 * Filter payout table based on search and filters
 */
function filterPayoutTable() {
    const searchTerm = document.getElementById('payoutSearch')?.value.toLowerCase() || '';
    const statusTerm = document.getElementById('statusFilter')?.value.toLowerCase() || '';
    const dateTerm = document.getElementById('dateFilter')?.value;
    const tableRows = document.querySelectorAll('#payoutTableBody tr');
    let visibleCount = 0;
    
    tableRows.forEach(row => {
        if (row.querySelector('.empty-state')) {
            row.style.display = 'none';
            return;
        }
        
        let matchSearch = true;
        let matchStatus = true;
        let matchDate = true;
        
        // Search filter
        if (searchTerm) {
            const periodText = row.querySelector('.payout-period-cell')?.textContent.toLowerCase() || '';
            const amountText = row.querySelector('.payout-amount-cell')?.textContent.toLowerCase() || '';
            const dateText = row.querySelector('.payout-date-cell')?.textContent.toLowerCase() || '';
            matchSearch = periodText.includes(searchTerm) || amountText.includes(searchTerm) || dateText.includes(searchTerm);
        }
        
        // Status filter
        if (statusTerm) {
            const rowStatus = row.getAttribute('data-payout-status');
            matchStatus = rowStatus === statusTerm;
        }
        
        // Date filter
        if (dateTerm && dateTerm !== '') {
            const dateCell = row.querySelector('.payout-date-cell div')?.textContent;
            if (dateCell) {
                const payoutDate = new Date(dateCell);
                const today = new Date();
                let cutoffDate = new Date();
                
                switch(dateTerm) {
                    case '30days':
                        cutoffDate.setDate(today.getDate() - 30);
                        break;
                    case '90days':
                        cutoffDate.setDate(today.getDate() - 90);
                        break;
                    case 'year':
                        cutoffDate = new Date(today.getFullYear(), 0, 1);
                        break;
                }
                
                matchDate = payoutDate >= cutoffDate;
            } else {
                matchDate = false;
            }
        }
        
        if (matchSearch && matchStatus && matchDate) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide empty state
    const emptyState = document.querySelector('.empty-state');
    if (emptyState && emptyState.closest('tr')) {
        const emptyRow = emptyState.closest('tr');
        if (visibleCount === 0 && tableRows.length > 1) {
            emptyRow.style.display = '';
        } else {
            emptyRow.style.display = 'none';
        }
    }
}

/**
 * Request a new payout
 */
async function requestPayout() {
    const availableBalance = parseFloat(document.querySelector('.payout-card.available .card-amount')?.textContent.replace('₹', '').replace(/,/g, '') || 0);
    
    if (availableBalance < 100) {
        showNotification(`Minimum payout amount is ₹100. Current balance: ₹${availableBalance.toFixed(2)}`, 'warning');
        return;
    }
    
    if (confirm(`Request payout of ₹${availableBalance.toFixed(2)} to your bank account?`)) {
        showLoading('Processing payout request...');
        
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            showNotification(`Payout request for ₹${availableBalance.toFixed(2)} submitted successfully!`, 'success');
            
            // Refresh data after successful request
            setTimeout(() => {
                location.reload();
            }, 2000);
            
        } catch (error) {
            showNotification('Failed to process payout request. Please try again.', 'error');
        } finally {
            hideLoading();
        }
    }
}

/**
 * Download payout statement
 */
function downloadStatement() {
    const modal = createDownloadModal();
    document.body.appendChild(modal);
}

/**
 * Create download modal
 */
function createDownloadModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade show d-block';
    modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-download me-2"></i>Download Statement
                    </h5>
                    <button type="button" class="btn-close" onclick="this.closest('.modal').remove()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" id="downloadFormat">
                            <option value="pdf">PDF Document</option>
                            <option value="csv">CSV Spreadsheet</option>
                            <option value="excel">Excel File</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="downloadRange">
                            <option value="all">All Time</option>
                            <option value="month">This Month</option>
                            <option value="quarter">Last 3 Months</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div id="customDateRange" class="d-none">
                        <div class="row">
                            <div class="col">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" id="dateFrom">
                            </div>
                            <div class="col">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" id="dateTo">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processDownload()">
                        <i class="fas fa-download me-2"></i>Download
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Show/hide custom date range
    modal.querySelector('#downloadRange').addEventListener('change', function(e) {
        const customRangeDiv = modal.querySelector('#customDateRange');
        if (e.target.value === 'custom') {
            customRangeDiv.classList.remove('d-none');
        } else {
            customRangeDiv.classList.add('d-none');
        }
    });
    
    return modal;
}

/**
 * Process download request
 */
function processDownload() {
    const format = document.getElementById('downloadFormat')?.value || 'pdf';
    const range = document.getElementById('downloadRange')?.value || 'all';
    
    showLoading(`Generating ${format.toUpperCase()} statement...`);
    
    // Simulate download
    setTimeout(() => {
        hideLoading();
        showNotification(`Statement downloaded successfully!`, 'success');
        document.querySelector('.modal')?.remove();
    }, 2000);
}

/**
 * Edit bank account
 */
function editBankAccount() {
    showNotification('Bank account editing coming soon!', 'info');
}

/**
 * Verify bank account
 */
function verifyAccount() {
    showNotification('Account verification feature coming soon!', 'info');
}

/**
 * Download receipt for specific payout
 */
function downloadReceipt(payoutId) {
    showLoading('Generating receipt...');
    
    setTimeout(() => {
        hideLoading();
        showNotification(`Receipt #${payoutId.toString().padStart(6, '0')} downloaded!`, 'success');
    }, 1500);
}

/**
 * Check for new payouts
 */
async function checkForNewPayouts() {
    try {
        // This would be an API call in production
        const hasNewPayouts = false; // Replace with actual check
        
        if (hasNewPayouts) {
            showNotification('New payout processed! Refresh to view.', 'info');
        }
    } catch (error) {
        console.error('Error checking for new payouts:', error);
    }
}

/**
 * Initialize interactive elements
 */
function initializeInteractiveElements() {
    // Card hover effects
    document.querySelectorAll('.payout-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
            this.style.boxShadow = '0 16px 32px rgba(0, 0, 0, 0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.12)';
        });
    });
    
    // Table row click for mobile
    if (window.innerWidth < 768) {
        document.querySelectorAll('#payoutTableBody tr').forEach(row => {
            if (!row.querySelector('.empty-state')) {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const viewBtn = this.querySelector('a.btn');
                        if (viewBtn) {
                            window.location.href = viewBtn.href;
                        }
                    }
                });
            }
        });
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.alert-notification').forEach(n => n.remove());
    
    const alert = document.createElement('div');
    alert.className = `alert-notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
        border: none;
    `;
    alert.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-auto" onclick="this.closest('.alert-notification').remove()"></button>
        </div>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

/**
 * Show loading overlay
 */
function showLoading(message = 'Loading...') {
    let loadingOverlay = document.getElementById('loadingOverlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        `;
        document.body.appendChild(loadingOverlay);
    }
    
    loadingOverlay.innerHTML = `
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3" style="font-size: 1.1rem; color: #333; font-weight: 500;">${message}</p>
    `;
    
    loadingOverlay.style.display = 'flex';
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

// Export functions for global access
window.requestPayout = requestPayout;
window.downloadStatement = downloadStatement;
window.editBankAccount = editBankAccount;
window.verifyAccount = verifyAccount;
window.downloadReceipt = downloadReceipt;
window.filterPayoutTable = filterPayoutTable;
</script>