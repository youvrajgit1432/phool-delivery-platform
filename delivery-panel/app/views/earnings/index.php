<?php 
require_once dirname(__FILE__, 3) . '/helpers/url.php'; 
require_once dirname(__FILE__) . '/../layouts/header.php'; 

// Initialize earnings data
$riderId = $_SESSION['rider_id'] ?? null;
$riderType = $rider_type ?? 'gig';
$earningsData = $earnings_data ?? [];
?>

<style>
/* Modern Earnings Page Styles */
.earnings-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Summary Cards */
.earnings-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.earnings-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.earnings-card:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.earnings-card.success { border-left-color: #28a745; }
.earnings-card.info { border-left-color: #007bff; }
.earnings-card.warning { border-left-color: #ffc107; }
.earnings-card.danger { border-left-color: #dc3545; }
.earnings-card.secondary { border-left-color: #6c757d; }

.card-label {
    color: #666;
    font-size: 13px;
    margin-bottom: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-label i {
    font-size: 14px;
}

.card-amount {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.card-subtitle {
    color: #999;
    font-size: 12px;
}

/* Tabs Navigation */
.earnings-tabs {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.tabs-header {
    display: flex;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.tab-btn {
    flex: 1;
    padding: 16px;
    background: none;
    border: none;
    color: #666;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.tab-btn:hover {
    background: rgba(0,0,0,0.02);
}

.tab-btn.active {
    color: #667eea;
    border-bottom: 3px solid #667eea;
}

.tab-content {
    display: none;
    padding: 24px;
}

.tab-content.active {
    display: block;
}

/* Breakdown Grid */
.earnings-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.breakdown-item {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.breakdown-item.negative { border-left-color: #dc3545; }
.breakdown-item.total {
    grid-column: 1 / -1;
    border-left-color: #28a745;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
}

.breakdown-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 8px;
}

.breakdown-value {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
}

.breakdown-item.negative .breakdown-value {
    color: #dc3545;
}

.breakdown-item.total .breakdown-value {
    font-size: 24px;
    color: #28a745;
}

/* Earnings Table */
.earnings-table-wrapper {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #f0f0f0;
}

.earnings-table {
    width: 100%;
    border-collapse: collapse;
}

.earnings-table thead {
    background: #f8f9fa;
}

.earnings-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #666;
    font-size: 13px;
    border-bottom: 1px solid #e0e0e0;
}

.earnings-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #333;
}

.earnings-table tbody tr:hover {
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 64px;
    color: #e0e0e0;
    margin-bottom: 16px;
}

.empty-state h4 {
    font-size: 18px;
    color: #666;
    margin-bottom: 8px;
}

.empty-state p {
    color: #999;
    margin: 0;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.pending { background: #e2e3e5; color: #383d41; }

/* Responsive */
@media (max-width: 768px) {
    .earnings-container {
        padding: 16px;
    }

    .earnings-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .card-amount {
        font-size: 24px;
    }

    .tabs-header {
        overflow-x: auto;
    }

    .tab-btn {
        min-width: 120px;
        padding: 12px;
    }

    .earnings-breakdown-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .breakdown-item.total {
        grid-column: 1 / -1;
    }
}

@media (max-width: 480px) {
    .earnings-container {
        padding: 12px;
    }

    .earnings-summary {
        grid-template-columns: 1fr;
    }

    .earnings-breakdown-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content-wrapper">
    <div class="earnings-container">
        <!-- Page Header -->
        <div style="margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 28px; font-weight: 700; color: #1a1a1a; margin: 0 0 8px 0;">
                        <i class="fas fa-wallet" style="color: #667eea; margin-right: 12px;"></i>Earnings & Payouts
                    </h2>
                    <p style="color: #666; margin: 0; font-size: 14px;">
                        <?php 
                        if ($rider_type === 'gig') {
                            echo 'Per-delivery earnings tracker';
                        } elseif ($rider_type === 'in_house') {
                            echo 'Salary, bonuses, and performance tracking';
                        } else {
                            echo 'Settlement and invoice management';
                        }
                        ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: #999; margin-bottom: 8px;">Last Updated</div>
                    <div style="font-size: 13px; font-weight: 600; color: #333;"><?php echo date('M d, Y \a\t h:i A'); ?></div>
                </div>
            </div>
        </div>

        <?php if (isset($earnings_data['error'])): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 24px;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                <?php echo htmlspecialchars($earnings_data['error']); ?>
            </div>
        <?php elseif ($rider_type === 'gig'): ?>
            <!-- =============== GIG WORKER VIEW =============== -->
            
            <!-- Summary Cards -->
            <div class="earnings-summary">
                <div class="earnings-card success">
                    <div class="card-label">
                        <i class="fas fa-sun"></i> Today's Earnings
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['today']['total'] ?? 0, 2); ?></div>
                    <div class="card-subtitle"><?php echo intval($earnings_data['today']['deliveries'] ?? 0); ?> deliveries</div>
                </div>

                <div class="earnings-card info">
                    <div class="card-label">
                        <i class="fas fa-calendar"></i> This Month
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['this_month']['total'] ?? 0, 2); ?></div>
                    <div class="card-subtitle"><?php echo intval($earnings_data['this_month']['total_deliveries'] ?? 0); ?> deliveries</div>
                </div>

                <div class="earnings-card warning">
                    <div class="card-label">
                        <i class="fas fa-gift"></i> Bonuses Earned
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['today']['bonuses'] ?? 0, 2); ?></div>
                    <div class="card-subtitle">Completed today</div>
                </div>

                <div class="earnings-card danger">
                    <div class="card-label">
                        <i class="fas fa-minus-circle"></i> Penalties
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['today']['penalties'] ?? 0, 2); ?></div>
                    <div class="card-subtitle">Today's deductions</div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="earnings-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab(event, 'gig-breakdown')">
                        <i class="fas fa-chart-pie" style="margin-right: 8px;"></i> Today's Breakdown
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'gig-history')">
                        <i class="fas fa-history" style="margin-right: 8px;"></i> Earnings History
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'gig-summary')">
                        <i class="fas fa-chart-bar" style="margin-right: 8px;"></i> Monthly Summary
                    </button>
                </div>

                <!-- Today's Breakdown -->
                <div id="gig-breakdown" class="tab-content active">
                    <div class="earnings-breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Base Delivery Fees</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['today']['base_fees'] ?? 0, 2); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Distance Surges</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['today']['distance_surges'] ?? 0, 2); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Peak Hour Bonus</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['today']['time_surges'] ?? 0, 2); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">On-Time Bonuses</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['today']['bonuses'] ?? 0, 2); ?></div>
                        </div>

                        <div class="breakdown-item negative">
                            <div class="breakdown-label">Penalties & Deductions</div>
                            <div class="breakdown-value">-₹<?php echo number_format($earnings_data['today']['penalties'] ?? 0, 2); ?></div>
                        </div>

                        <div class="breakdown-item total">
                            <div class="breakdown-label">Total Earnings</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['today']['total'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Earnings History -->
                <div id="gig-history" class="tab-content">
                    <?php if (!empty($earnings_data['recent'])): ?>
                        <div class="earnings-table-wrapper">
                            <table class="earnings-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Base Fee</th>
                                        <th>Surges</th>
                                        <th>Bonuses</th>
                                        <th>Penalties</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($earnings_data['recent'] as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_id'] ?? ''); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($order['date'] ?? '')); ?></td>
                                            <td>₹<?php echo number_format($order['base_fee'] ?? 0, 2); ?></td>
                                            <td>₹<?php echo number_format($order['surges'] ?? 0, 2); ?></td>
                                            <td>₹<?php echo number_format($order['bonuses'] ?? 0, 2); ?></td>
                                            <td>-₹<?php echo number_format($order['penalties'] ?? 0, 2); ?></td>
                                            <td><strong>₹<?php echo number_format($order['total'] ?? 0, 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge success">
                                                    <i class="fas fa-check-circle" style="margin-right: 4px;"></i>Completed
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No earnings data</h4>
                            <p>Complete your first delivery to see earnings history</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Monthly Summary -->
                <div id="gig-summary" class="tab-content">
                    <div class="earnings-breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Total Deliveries</div>
                            <div class="breakdown-value"><?php echo intval($earnings_data['this_month']['total_deliveries'] ?? 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Avg Per Delivery</div>
                            <div class="breakdown-value">₹<?php 
                                $deliveries = intval($earnings_data['this_month']['total_deliveries'] ?? 0);
                                $total = floatval($earnings_data['this_month']['total'] ?? 0);
                                echo number_format($deliveries > 0 ? $total / $deliveries : 0, 0);
                            ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Best Day Earning</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['this_month']['best_day'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Total Bonuses</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['this_month']['bonuses'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item negative">
                            <div class="breakdown-label">Total Penalties</div>
                            <div class="breakdown-value">-₹<?php echo number_format($earnings_data['this_month']['penalties'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item total">
                            <div class="breakdown-label">Monthly Total</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['this_month']['total'] ?? 0, 0); ?></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($rider_type === 'in_house'): ?>
            <!-- =============== IN-HOUSE RIDER VIEW =============== -->
            
            <!-- Summary Cards -->
            <div class="earnings-summary">
                <div class="earnings-card success">
                    <div class="card-label">
                        <i class="fas fa-money-bill"></i> Base Salary
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['base_salary'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Monthly</div>
                </div>

                <div class="earnings-card info">
                    <div class="card-label">
                        <i class="fas fa-star"></i> Bonuses
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['bonuses_this_month'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">This month</div>
                </div>

                <div class="earnings-card warning">
                    <div class="card-label">
                        <i class="fas fa-tasks"></i> Deliveries
                    </div>
                    <div class="card-amount"><?php echo intval($earnings_data['this_month']['deliveries'] ?? 0); ?></div>
                    <div class="card-subtitle">This month</div>
                </div>

                <div class="earnings-card danger">
                    <div class="card-label">
                        <i class="fas fa-exclamation-triangle"></i> Penalties
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['penalties_this_month'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Deductions</div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="earnings-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab(event, 'salary-breakdown')">
                        <i class="fas fa-chart-pie" style="margin-right: 8px;"></i> Salary Breakdown
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'bonuses-table')">
                        <i class="fas fa-gift" style="margin-right: 8px;"></i> Bonuses & Incentives
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'performance')">
                        <i class="fas fa-chart-line" style="margin-right: 8px;"></i> Performance
                    </button>
                </div>

                <!-- Salary Breakdown -->
                <div id="salary-breakdown" class="tab-content active">
                    <div class="earnings-breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Base Monthly</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['base_salary'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Daily Average</div>
                            <div class="breakdown-value">₹<?php echo number_format(($earnings_data['base_salary'] ?? 0) / 30, 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Performance Bonus</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['bonuses_this_month'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Allowances</div>
                            <div class="breakdown-value">₹<?php echo number_format($earnings_data['allowances'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item negative">
                            <div class="breakdown-label">Deductions</div>
                            <div class="breakdown-value">-₹<?php echo number_format($earnings_data['penalties_this_month'] ?? 0, 0); ?></div>
                        </div>

                        <div class="breakdown-item total">
                            <div class="breakdown-label">Net This Month</div>
                            <div class="breakdown-value">₹<?php echo number_format(($earnings_data['base_salary'] ?? 0) + ($earnings_data['bonuses_this_month'] ?? 0) - ($earnings_data['penalties_this_month'] ?? 0), 0); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Bonuses & Incentives -->
                <div id="bonuses-table" class="tab-content">
                    <?php if (!empty($earnings_data['bonuses'])): ?>
                        <div class="earnings-table-wrapper">
                            <table class="earnings-table">
                                <thead>
                                    <tr>
                                        <th>Bonus Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($earnings_data['bonuses'] as $bonus): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($bonus['type'] ?? ''); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bonus['description'] ?? ''); ?></td>
                                            <td>₹<?php echo number_format($bonus['amount'] ?? 0, 0); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($bonus['date'] ?? '')); ?></td>
                                            <td>
                                                <span class="status-badge success">
                                                    <i class="fas fa-check" style="margin-right: 4px;"></i>Credited
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-gift"></i>
                            <h4>No bonuses earned yet</h4>
                            <p>Complete deliveries and maintain good metrics to earn bonuses</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Performance Metrics -->
                <div id="performance" class="tab-content">
                    <div class="earnings-breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Deliveries</div>
                            <div class="breakdown-value"><?php echo intval($earnings_data['this_month']['deliveries'] ?? 0); ?></div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Completed Rate</div>
                            <div class="breakdown-value"><?php echo floatval($earnings_data['this_month']['completion_rate'] ?? 0); ?>%</div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Avg Rating</div>
                            <div class="breakdown-value"><?php echo number_format($earnings_data['this_month']['avg_rating'] ?? 0, 1); ?> ⭐</div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">On-Time Rate</div>
                            <div class="breakdown-value"><?php echo floatval($earnings_data['this_month']['on_time_rate'] ?? 0); ?>%</div>
                        </div>

                        <div class="breakdown-item">
                            <div class="breakdown-label">Cancellations</div>
                            <div class="breakdown-value"><?php echo intval($earnings_data['this_month']['cancellations'] ?? 0); ?></div>
                        </div>

                        <div class="breakdown-item total">
                            <div class="breakdown-label">Performance Score</div>
                            <div class="breakdown-value"><?php echo number_format($earnings_data['this_month']['performance_score'] ?? 0, 1); ?>/10</div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($rider_type === 'partner'): ?>
            <!-- =============== PARTNER RIDER VIEW =============== -->
            
            <!-- Summary Cards -->
            <div class="earnings-summary">
                <div class="earnings-card success">
                    <div class="card-label">
                        <i class="fas fa-box"></i> Deliveries
                    </div>
                    <div class="card-amount"><?php echo intval($earnings_data['month_deliveries'] ?? 0); ?></div>
                    <div class="card-subtitle"><?php echo date('M Y'); ?></div>
                </div>

                <div class="earnings-card info">
                    <div class="card-label">
                        <i class="fas fa-invoice-dollar"></i> Total Invoiced
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['total_invoiced'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">This month</div>
                </div>

                <div class="earnings-card warning">
                    <div class="card-label">
                        <i class="fas fa-credit-card"></i> Pending Settlement
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['pending_settlement'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Awaiting invoice</div>
                </div>

                <div class="earnings-card success">
                    <div class="card-label">
                        <i class="fas fa-check-circle"></i> Settled
                    </div>
                    <div class="card-amount">₹<?php echo number_format($earnings_data['settled_amount'] ?? 0, 0); ?></div>
                    <div class="card-subtitle">Last settlement</div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="earnings-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab(event, 'settlement-info')">
                        <i class="fas fa-info-circle" style="margin-right: 8px;"></i> Settlement Info
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'invoices-list')">
                        <i class="fas fa-file-invoice" style="margin-right: 8px;"></i> Invoices
                    </button>
                    <button class="tab-btn" onclick="switchTab(event, 'bank-details')">
                        <i class="fas fa-university" style="margin-right: 8px;"></i> Bank Details
                    </button>
                </div>

                <!-- Settlement Information -->
                <div id="settlement-info" class="tab-content active">
                    <div style="background: #f8f9fa; padding: 24px; border-radius: 8px; margin-bottom: 24px;">
                        <h5 style="margin-top: 0;">Settlement Process</h5>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px;">
                            <div style="text-align: center;">
                                <div style="width: 40px; height: 40px; background: #667eea; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-weight: 700;">1</div>
                                <div style="font-weight: 600; margin-bottom: 4px;">Complete Deliveries</div>
                                <div style="font-size: 12px; color: #666;">Finish orders assigned to you</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="width: 40px; height: 40px; background: #667eea; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-weight: 700;">2</div>
                                <div style="font-weight: 600; margin-bottom: 4px;">Generate Invoice</div>
                                <div style="font-size: 12px; color: #666;">Auto-generated monthly invoices</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="width: 40px; height: 40px; background: #667eea; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-weight: 700;">3</div>
                                <div style="font-weight: 600; margin-bottom: 4px;">Payment Processing</div>
                                <div style="font-size: 12px; color: #666;">Settlement within 15 days</div>
                            </div>
                        </div>
                    </div>

                    <div style="background: white; border-left: 4px solid #28a745; padding: 16px; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">Settlement Mode</div>
                                <div style="font-size: 18px; font-weight: 700;">Monthly Invoice</div>
                            </div>
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">Settlement Cycle</div>
                                <div style="font-size: 18px; font-weight: 700;">1st of every month</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoices List -->
                <div id="invoices-list" class="tab-content">
                    <?php if (!empty($earnings_data['invoices'])): ?>
                        <div class="earnings-table-wrapper">
                            <table class="earnings-table">
                                <thead>
                                    <tr>
                                        <th>Invoice No.</th>
                                        <th>Period</th>
                                        <th>Deliveries</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($earnings_data['invoices'] as $invoice): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($invoice['invoice_no'] ?? ''); ?></strong></td>
                                            <td><?php echo htmlspecialchars($invoice['period'] ?? ''); ?></td>
                                            <td><?php echo intval($invoice['deliveries'] ?? 0); ?></td>
                                            <td>₹<?php echo number_format($invoice['amount'] ?? 0, 0); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($invoice['date'] ?? '')); ?></td>
                                            <td>
                                                <?php 
                                                $status = strtolower($invoice['status'] ?? 'pending');
                                                $badgeClass = $status === 'paid' ? 'success' : ($status === 'pending' ? 'warning' : 'pending');
                                                ?>
                                                <span class="status-badge <?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="#" style="color: #667eea; text-decoration: none; font-size: 13px; font-weight: 600;">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <h4>No invoices yet</h4>
                            <p>Complete deliveries to generate monthly invoices</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bank Details -->
                <div id="bank-details" class="tab-content">
                    <div style="background: white; border-left: 4px solid #667eea; padding: 24px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                            <h5 style="margin: 0; color: #1a1a1a;">Bank Account Details</h5>
                            <span style="background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-check-circle" style="margin-right: 4px;"></i> Verified
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">Bank Name</div>
                                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a;"><?php echo htmlspecialchars($earnings_data['bank_details']['bank_name'] ?? 'Not Set'); ?></div>
                            </div>
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">Account Holder</div>
                                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a;"><?php echo htmlspecialchars($earnings_data['bank_details']['account_holder'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">Account Number</div>
                                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a; font-family: 'Courier New', monospace;">
                                    <?php 
                                    $accNum = $earnings_data['bank_details']['account_number'] ?? '';
                                    if ($accNum) {
                                        $masked = str_repeat('•', strlen($accNum) - 4) . substr($accNum, -4);
                                        echo $masked;
                                    } else {
                                        echo 'Not Set';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div>
                                <div style="color: #666; font-size: 13px; margin-bottom: 6px;">IFSC Code</div>
                                <div style="font-size: 16px; font-weight: 600; color: #1a1a1a; font-family: 'Courier New', monospace;"><?php echo htmlspecialchars($earnings_data['bank_details']['ifsc_code'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>

                        <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
                            <p style="color: #666; font-size: 13px; margin: 0;">
                                <i class="fas fa-lock" style="margin-right: 6px;"></i> Your bank details are securely stored and encrypted. Update them from your profile settings if needed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(event, tabId) {
    event.preventDefault();
    
    // Hide all tab contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all buttons
    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(button => button.classList.remove('active'));
    
    // Show selected tab and mark button as active
    document.getElementById(tabId).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
}
</script>

<?php require_once dirname(__FILE__) . '/../layouts/footer.php'; ?>
