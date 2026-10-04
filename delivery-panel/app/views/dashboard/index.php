<!-- Rider Dashboard - Dynamic View Based on Rider Type -->
<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<?php require_once dirname(__FILE__) . '/../layouts/header.php'; ?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        --dark-bg: #1a1d29;
        --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        --hover-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        --border-radius: 16px;
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    .content-wrapper {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: calc(100vh - 70px);
        padding: 24px;
    }

    /* Welcome Section - Modern Header */
    .welcome-section {
        background: white;
        border-radius: var(--border-radius);
        padding: 28px 32px;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        margin-bottom: 24px;
    }

    .welcome-section h1 {
        font-size: 2.2rem;
        font-weight: 700;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .welcome-section .text-muted {
        color: #64748b !important;
        font-size: 1rem;
        font-weight: 500;
    }

    /* Status Badges - Modern Design */
    .badge-type {
        font-size: 0.75rem;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        letter-spacing: 0.3px;
        border: 2px solid transparent;
        text-transform: uppercase;
    }
    
    .badge-in-house {
        background: var(--success-gradient);
        color: white;
        border: none;
    }
    
    .badge-gig {
        background: var(--primary-gradient);
        color: white;
        border: none;
    }
    
    .badge-partner {
        background: var(--warning-gradient);
        color: white;
        border: none;
    }

    /* Status Cards - Modern Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary-gradient);
        border-radius: var(--border-radius) 0 0 var(--border-radius);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--hover-shadow);
    }

    .stat-card .card-text {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .stat-card h3 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .stat-card small {
        color: #94a3b8;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .stat-card .emoji {
        font-size: 2.5rem;
        position: absolute;
        right: 24px;
        top: 24px;
        opacity: 0.8;
    }

    /* Earnings Section - Modern Cards */
    .earnings-section {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .card-header-modern {
        background: linear-gradient(90deg, #f8fafc 0%, #ffffff 100%);
        border-bottom: 1px solid #e2e8f0;
        padding: 20px 24px;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .card-header-modern h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header-modern h5::before {
        content: '';
        width: 4px;
        height: 24px;
        background: var(--primary-gradient);
        border-radius: 2px;
    }

    .earnings-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        padding: 24px;
    }

    .earning-item {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: var(--transition);
    }

    .earning-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .earning-item label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 8px;
    }

    .earning-item .amount {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Active Orders - Modern List */
    .orders-container {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .order-list {
        padding: 0;
    }

    .order-item {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .order-item:hover {
        background: #f8fafc;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .order-info {
        flex: 1;
    }

    .order-number {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 6px;
    }

    .order-meta {
        display: flex;
        gap: 20px;
        color: #64748b;
        font-size: 0.875rem;
    }

    .order-amount {
        font-weight: 700;
        color: #1e293b;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .status-assigned { 
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        color: #0369a1;
    }
    .status-accepted { 
        background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
        color: #7c3aed;
    }
    .status-picked-up { 
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #d97706;
    }
    .status-on-the-way { 
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #059669;
    }
    .status-arrived { 
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #16a34a;
    }
    .status-delivered { 
        background: linear-gradient(135deg, #bbf7d0 0%, #86efac 100%);
        color: #15803d;
    }

    /* Quick Actions - Modern Grid */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .quick-action-card {
        background: white;
        border-radius: 12px;
        padding: 24px 20px;
        text-align: center;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .quick-action-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--hover-shadow);
        border-color: transparent;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }

    .quick-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: var(--primary-gradient);
        color: white;
    }

    .quick-action-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #1e293b;
    }

    /* Type Alert - Modern Banner */
    .type-alert {
        padding: 20px 24px;
        border-radius: var(--border-radius);
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 16px;
        border: none;
        box-shadow: var(--card-shadow);
    }
    
    .type-alert::before {
        content: '';
        width: 4px;
        height: 40px;
        border-radius: 2px;
    }
    
    .type-alert-in-house {
        background: linear-gradient(90deg, #d1fae5 0%, #bbf7d0 100%);
        color: #065f46;
    }
    
    .type-alert-in-house::before {
        background: #10b981;
    }
    
    .type-alert-gig {
        background: linear-gradient(90deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
    }
    
    .type-alert-gig::before {
        background: #3b82f6;
    }
    
    .type-alert-partner {
        background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
    }
    
    .type-alert-partner::before {
        background: #f59e0b;
    }

    /* Action Buttons - Modern Design */
    .action-buttons {
        display: flex;
        gap: 12px;
    }

    .btn-accept {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .btn-accept:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .btn-reject {
        background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        color: white;
    }

    .btn-view {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        color: white;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .content-wrapper {
            padding: 16px;
        }

        .welcome-section {
            padding: 20px;
            margin-bottom: 20px;
        }

        .welcome-section h1 {
            font-size: 1.75rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-card h3 {
            font-size: 2rem;
        }

        .earnings-breakdown {
            grid-template-columns: repeat(2, 1fr);
            padding: 20px;
        }

        .action-buttons {
            width: auto;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .quick-action-card {
            padding: 20px 16px;
        }

        .col-lg-4 {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .welcome-section {
            text-align: center;
            padding: 16px;
        }

        .welcome-section .row {
            flex-direction: column;
        }

        .status-info {
            text-align: center;
            margin-top: 16px;
        }

        .quick-actions-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .type-alert {
            flex-direction: column;
            text-align: center;
            gap: 12px;
            padding: 16px;
        }

        .type-alert::before {
            width: 100%;
            height: 4px;
            order: -1;
        }
    }

    /* Loading States */
    .loading {
        position: relative;
        overflow: hidden;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    /* Toast Notification */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
    }

    .toast {
        background: white;
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        border-left: 4px solid;
        margin-bottom: 10px;
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
    }

    .toast-success {
        border-left-color: #10b981;
        background: linear-gradient(90deg, #d1fae5 0%, #ffffff 100%);
    }

    .toast-error {
        border-left-color: #ef4444;
        background: linear-gradient(90deg, #fee2e2 0%, #ffffff 100%);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>

<div class="content-wrapper">
    <!-- Welcome Section with Rider Type -->
    <div class="welcome-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>Welcome, <?php echo htmlspecialchars($rider->first_name ?? 'Rider'); ?>! 👋</h1>
                <p class="text-muted mb-0">
                    <?php echo date('l, F j, Y'); ?> • 
                    <span class="badge-type badge-<?php echo htmlspecialchars($rider_type ?? 'in-house'); ?> ms-2">
                        <?php 
                        $types = ['in_house' => 'IN-HOUSE', 'gig' => 'GIG WORKER', 'partner' => 'PARTNER'];
                        echo $types[$rider_type] ?? 'DELIVERY AGENT';
                        ?>
                    </span>
                </p>
            </div>
            <div class="col-md-4">
                <div class="status-info text-end text-md-start text-md-end">
                    <div class="d-inline-flex align-items-center gap-3">
                        <div>
                            <div class="text-muted small">Status</div>
                            <div class="badge rounded-pill" style="background: <?php echo ($rider->status === 'active') ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'; ?>; color: white; padding: 6px 16px;">
                                <?php echo strtoupper($rider->status ?? 'inactive'); ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small">Availability</div>
                            <div class="badge rounded-pill" style="background: <?php echo ($rider->is_available) ? 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)' : 'linear-gradient(135deg, #64748b 0%, #475569 100%)'; ?>; color: white; padding: 6px 16px;">
                                <?php echo ($rider->is_available) ? 'ONLINE' : 'OFFLINE'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Type-Specific Alert -->
    <?php if ($rider_type === 'gig'): ?>
    <div class="type-alert type-alert-gig">
        <div class="d-flex align-items-center">
            <div style="font-size: 1.5rem;">💰</div>
            <div>
                <strong class="d-block mb-1">Gig Worker Mode</strong>
                <small>Earn per delivery • Complete more orders to increase earnings!</small>
            </div>
        </div>
    </div>
    <?php elseif ($rider_type === 'partner'): ?>
    <div class="type-alert type-alert-partner">
        <div class="d-flex align-items-center">
            <div style="font-size: 1.5rem;">🏢</div>
            <div>
                <strong class="d-block mb-1">Partner Mode</strong>
                <small>Invoice-based settlement • Earnings settled per agreement</small>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="type-alert type-alert-in-house">
        <div class="d-flex align-items-center">
            <div style="font-size: 1.5rem;">💼</div>
            <div>
                <strong class="d-block mb-1">In-House Rider</strong>
                <small>Fixed salary with performance incentives and bonuses</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Today's Deliveries -->
        <div class="stat-card">
            <div class="emoji">📦</div>
            <div class="card-text">Today's Deliveries</div>
            <h3><?php echo intval($today_deliveries); ?></h3>
            <small>Completed deliveries</small>
        </div>
        
        <!-- Earnings -->
        <div class="stat-card">
            <div class="emoji">💰</div>
            <div class="card-text">
                <?php echo ($rider_type === 'gig') ? "Today's Earnings" : "Total Earnings"; ?>
            </div>
            <h3 style="color: #10b981;">₹<?php echo number_format($today_earnings, 2); ?></h3>
            <small>
                <?php echo ($rider_type === 'partner') ? 'Invoice pending' : 'Net earnings'; ?>
            </small>
        </div>
        
        <!-- Rating -->
        <div class="stat-card">
            <div class="emoji">⭐</div>
            <div class="card-text">Customer Rating</div>
            <h3><?php echo number_format($average_rating, 1); ?></h3>
            <small>Based on <?php echo intval($total_deliveries); ?> deliveries</small>
        </div>
        
        <!-- On-Time Rate -->
        <div class="stat-card">
            <div class="emoji">⏱️</div>
            <div class="card-text">On-Time Rate</div>
            <h3><?php echo number_format($on_time_rate, 1); ?>%</h3>
            <small>Performance metric</small>
        </div>
    </div>
    
    <!-- Earnings Breakdown for GIG workers -->
    <?php if ($rider_type === 'gig'): ?>
    <div class="earnings-section">
        <div class="card-header-modern">
            <h5>This Week's Earnings Breakdown</h5>
        </div>
        <div class="earnings-breakdown">
            <div class="earning-item">
                <label>Base Earnings</label>
                <div class="amount">₹<?php echo number_format($today_earnings, 2); ?></div>
            </div>
            <div class="earning-item">
                <label>Distance Bonus</label>
                <div class="amount">₹0.00</div>
            </div>
            <div class="earning-item">
                <label>Penalties</label>
                <div class="amount" style="color: #ef4444;">-₹0.00</div>
            </div>
            <div class="earning-item">
                <label>Net This Week</label>
                <div class="amount">₹<?php echo number_format($today_earnings, 2); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- In-House Salary Info -->
    <?php if ($rider_type === 'in_house'): ?>
    <div class="earnings-section">
        <div class="card-header-modern">
            <h5>Salary & Incentives</h5>
        </div>
        <div class="earnings-breakdown">
            <div class="earning-item">
                <label>Base Monthly Salary</label>
                <div class="amount">₹<?php echo number_format($rider->base_salary ?? 0, 2); ?></div>
            </div>
            <div class="earning-item">
                <label>Performance Bonus</label>
                <div class="amount">₹0.00</div>
            </div>
            <div class="earning-item">
                <label>Incentives This Month</label>
                <div class="amount">₹0.00</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Active Orders -->
        <div class="col-lg-8 mb-4">
            <div class="orders-container">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <h5>Active Orders</h5>
                    <span class="badge rounded-pill" style="background: var(--primary-gradient); color: white; padding: 6px 16px;">
                        <?php echo count($pending_orders); ?> orders
                    </span>
                </div>
                <div class="order-list">
                    <?php if (empty($pending_orders)): ?>
                        <div class="order-item text-center py-5">
                            <div style="font-size: 3rem; opacity: 0.3;">📭</div>
                            <h5 class="mt-3 mb-2">No Active Orders</h5>
                            <p class="text-muted mb-0">You're all set! Check back later for new deliveries.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_orders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <div class="order-number">
                                    Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                </div>
                                <div class="order-meta">
                                    <span>
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($order['delivery_city'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="order-amount">
                                        ₹<?php echo number_format($order['total_amount'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="status-badge status-<?php echo str_replace('_', '-', $order['delivery_status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                            </div>
                            <div class="action-buttons">
                                <?php if ($order['delivery_status'] === 'assigned'): ?>
                                    <button type="button" class="btn-accept accept-order-btn" 
                                            data-order-id="<?php echo intval($order['order_id']); ?>" 
                                            data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                        <i class="fas fa-check me-1"></i> Accept
                                    </button>
                                    <button type="button" class="btn-reject reject-order-btn" 
                                            data-order-id="<?php echo intval($order['order_id']); ?>" 
                                            data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars(app_url('/orders/' . $order['id'])); ?>" class="btn-view">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="orders-container">
                <div class="card-header-modern">
                    <h5>Quick Actions</h5>
                </div>
                <div class="p-4">
                    <div class="quick-actions-grid">
                        <a href="<?php echo htmlspecialchars(app_url('/orders/assigned')); ?>" class="quick-action-card">
                            <div class="quick-action-icon">
                                📋
                            </div>
                            <div class="quick-action-label">All Orders</div>
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/earnings')); ?>" class="quick-action-card">
                            <div class="quick-action-icon">
                                💵
                            </div>
                            <div class="quick-action-label">Earnings</div>
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/profile')); ?>" class="quick-action-card">
                            <div class="quick-action-icon">
                                👤
                            </div>
                            <div class="quick-action-label">Profile</div>
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/support')); ?>" class="quick-action-card">
                            <div class="quick-action-icon">
                                💬
                            </div>
                            <div class="quick-action-label">Support</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container"></div>

<script>
// Toast function
function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div style="font-size: 1.25rem;">${type === 'success' ? '✅' : '❌'}</div>
        <div>${message}</div>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle accept order buttons
    document.querySelectorAll('.accept-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            acceptOrder(orderId, orderNumber, this);
        });
    });

    // Handle reject order buttons
    document.querySelectorAll('.reject-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = parseInt(this.dataset.orderId);
            const orderNumber = this.dataset.orderNumber;
            
            // Modern confirmation modal
            const reason = prompt(`Please enter reason for rejecting order ${orderNumber}:`, '');
            if (reason !== null && reason.trim() !== '') {
                rejectOrder(orderId, orderNumber, reason.trim(), this);
            } else if (reason !== null) {
                showToast('Please provide a reason for rejection', 'error');
            }
        });
    });
});

function acceptOrder(orderId, orderNumber, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Accepting...';

    fetch('<?php echo app_url('/ajax/accept-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} accepted successfully!`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to accept order', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function rejectOrder(orderId, orderNumber, reason, buttonElement) {
    const btn = buttonElement;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Rejecting...';

    fetch('<?php echo app_url('/ajax/reject-order.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            order_id: orderId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Order ${orderNumber} rejected successfully`, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to reject order', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}
</script>

<?php require_once dirname(__FILE__) . '/../layouts/footer.php'; ?>