<?php
// app/views/account/dashboard.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

$page_title = LanguageHelper::t('account_dashboard', 'Account Dashboard') . " - Phool Delivery";

// Check if variables are set before using them
$user = $user ?? null;
$recent_orders = $recent_orders ?? [];
$addresses = $addresses ?? [];
$wishlist_count = $wishlist_count ?? 0;
$payment_methods = $payment_methods ?? [];
$preferences = $preferences ?? [];

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Determine active section from URL or default to overview
$active_section = $_GET['section'] ?? 'overview';
?>

<div class="account-dashboard">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-top">
            <a href="<?= $pathConfig->url('home') ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title"><?= LanguageHelper::t('account_dashboard', 'My Account') ?></h1>
        </div>
        
        <?php if ($user): ?>
        <div class="user-profile-section">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h3 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <a href="<?= $pathConfig->url('account/profile') ?>" class="edit-btn">
                <i class="fas fa-edit"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Wallet/Account Summary Section (Mobile only) -->
    <div class="mobile-only" hidden>
        <div class="account-summary-card">
            <div class="summary-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="summary-content">
                <h4 class="summary-title"><?= LanguageHelper::t('wallet_balance', 'Phool Cash') ?></h4>
                <p class="summary-subtitle">₹0.00</p>
                <span class="summary-badge"><?= LanguageHelper::t('new', 'NEW') ?></span>
            </div>
            <div class="summary-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="dashboard-content">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- ACCOUNT SECTION -->
        <div class="section-group">
            <h3 class="section-title" hidden><?= LanguageHelper::t('account', 'Account') ?></h3>
            <div class="section-list">
                <!-- Personal Information -->
                <a href="<?= $pathConfig->url('account/profile') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('personal_info', 'Personal Information') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('manage_your_profile', 'Manage your name, email & phone') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Address Book -->
                <a href="<?= $pathConfig->url('account/addresses') ?>" class="list-item" hidden>
                    <div class="item-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('address_book', 'Saved Addresses') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('manage_delivery_addresses', 'Manage your delivery addresses') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Payment Methods -->
                <a href="<?= $pathConfig->url('account/payment-methods') ?>" class="list-item" hidden>
                    <div class="item-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('payment_methods', 'Payment Methods') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('manage_payment_options', 'Add or remove payment options') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- SERVICES SECTION -->
        <div class="section-group">
            <h3 class="section-title" hidden><?= LanguageHelper::t('services', 'Services') ?></h3>
            <div class="section-list">
                <!-- My Orders -->
                <a href="<?= $pathConfig->url('account/orders') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-shopping-bag"></i>
                        <?php if (!empty($recent_orders)): ?>
                            <span class="icon-badge"><?php echo count($recent_orders); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('my_orders', 'My Orders') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('track_returns_exchanges', 'Track, return or exchange items') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Recent Order Quick View -->
                <?php if (!empty($recent_orders)): ?>
                    <?php $recent_order = $recent_orders[0]; ?>
                    <div class="recent-order-preview">
                        <div class="order-preview-header">
                            <h4><?= LanguageHelper::t('recent_order', 'Recent Order') ?></h4>
                            <a href="<?= $pathConfig->url('account/orders') ?>" class="view-all-link">
                                <?= LanguageHelper::t('view_all', 'View All') ?>
                            </a>
                        </div>
                        <div class="order-preview-details">
                            <div class="order-info">
                                <span class="order-id"><?= LanguageHelper::t('order', 'Order') ?> #<?php echo htmlspecialchars($recent_order['order_number'] ?? $recent_order['id']); ?></span>
                                <span class="order-date"><?php echo date('M j, Y', strtotime($recent_order['created_at'])); ?></span>
                            </div>
                            <div class="order-status-badge <?php echo strtolower($recent_order['status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($recent_order['status'] ?? 'Pending'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Wishlist -->
                <a href="<?= $pathConfig->url('account/wishlist') ?>" class="list-item" hidden>
                    <div class="item-icon">
                        <i class="fas fa-heart"></i>
                        <?php if ($wishlist_count > 0): ?>
                            <span class="icon-badge"><?php echo $wishlist_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('wishlist', 'My Wishlist') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('view_saved_items', 'View your saved items') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Notifications -->
                <a href="<?= $pathConfig->url('account/notifications') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('notifications', 'Notifications') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('manage_notification_preferences', 'Manage your notification preferences') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- ENQUIRIES SECTION -->
        <div class="section-group">
            <h3 class="section-title" hidden><?= LanguageHelper::t('enquiries_support', 'Enquiries & Support') ?></h3>
            <div class="section-list">
                <!-- Customer Support -->
                <a href="<?= $pathConfig->url('account/support') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('customer_support', 'Customer Support') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('get_help_issues', 'Get help with your issues') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- FAQ -->
                <a href="<?= $pathConfig->url('faq') ?>" class="list-item" hidden>
                    <div class="item-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('faq', 'FAQs') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('frequently_asked_questions', 'Frequently asked questions') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- SECURITY SECTION -->
        <div class="section-group">
            <h3 class="section-title" hidden><?= LanguageHelper::t('security', 'Security') ?></h3>
            <div class="section-list">
                <!-- Change Password -->
                <a href="<?= $pathConfig->url('account/security') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('change_password', 'Change Password') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('update_password_security', 'Update your account password') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Preferences -->
                <a href="<?= $pathConfig->url('account/preferences') ?>" class="list-item">
                    <div class="item-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('preferences', 'Preferences') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('manage_language_settings', 'Manage language and settings') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>

                <!-- Logout Button (Added as a list item) -->
                <a href="<?= $pathConfig->url('logout') ?>" class="list-item logout-list-item">
                    <div class="item-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="item-content">
                        <h4 class="item-title"><?= LanguageHelper::t('logout', 'Logout') ?></h4>
                        <p class="item-subtitle"><?= LanguageHelper::t('sign_out_account', 'Sign out from your account') ?></p>
                    </div>
                    <div class="item-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer Actions - Continue Shopping Only -->
        <div class="footer-actions">
            <a href="<?= $pathConfig->url('/') ?>" class="btn-continue-shopping">
                <i class="fas fa-shopping-bag"></i>
                <?= LanguageHelper::t('continue_shopping', 'Continue Shopping') ?>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to list items
    const listItems = document.querySelectorAll('.list-item');
    
    listItems.forEach(item => {
        item.addEventListener('touchstart', function() {
            this.style.backgroundColor = '#f5f5f5';
        });
        
        item.addEventListener('touchend', function() {
            setTimeout(() => {
                this.style.backgroundColor = '';
            }, 150);
        });
    });
    
    // Add special handling for logout button
    const logoutBtn = document.querySelector('.logout-list-item');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            if (!confirm('<?= LanguageHelper::t('confirm_logout', 'Are you sure you want to logout?') ?>')) {
                e.preventDefault();
                return false;
            }
            
            // Add loading state
            const originalHTML = this.innerHTML;
            this.innerHTML = '<div class="item-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="item-content"><h4 class="item-title"><?= LanguageHelper::t('logging_out', 'Logging out...') ?></h4></div>';
            this.style.pointerEvents = 'none';
            this.style.opacity = '0.7';
            
            // Reset after 5 seconds if still on same page
            setTimeout(() => {
                if (this.innerHTML.includes('fa-spinner')) {
                    this.innerHTML = originalHTML;
                    this.style.pointerEvents = 'auto';
                    this.style.opacity = '1';
                }
            }, 5000);
        });
    }
    
    // Add loading states for other buttons (excluding logout)
    const buttons = document.querySelectorAll('a[href]:not(.logout-list-item)');
    buttons.forEach(button => {
        if (!button.href.includes('home') && !button.classList.contains('logout-list-item')) {
            button.addEventListener('click', function(e) {
                // Add loading state for better UX
                if (this.classList.contains('list-item')) {
                    const originalText = this.querySelector('.item-title')?.textContent;
                    if (originalText) {
                        this.querySelector('.item-title').textContent = '<?= LanguageHelper::t('loading', 'Loading...') ?>';
                        this.style.opacity = '0.7';
                        this.style.pointerEvents = 'none';
                        
                        // Reset after 3 seconds if still on same page
                        setTimeout(() => {
                            if (this.querySelector('.item-title').textContent === '<?= LanguageHelper::t('loading', 'Loading...') ?>') {
                                this.querySelector('.item-title').textContent = originalText;
                                this.style.opacity = '1';
                                this.style.pointerEvents = 'auto';
                            }
                        }, 3000);
                    }
                } else if (this.classList.contains('btn-continue-shopping')) {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= LanguageHelper::t('redirecting', 'Redirecting...') ?>';
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none';
                    
                    // Reset after 3 seconds if still on same page
                    setTimeout(() => {
                        if (this.innerHTML.includes('fa-spinner')) {
                            this.innerHTML = originalHTML;
                            this.style.opacity = '1';
                            this.style.pointerEvents = 'auto';
                        }
                    }, 3000);
                }
            });
        }
    });
});
</script>

<style>
:root {
    --primary: #FF6B00;
    --primary-light: #FF8C42;
    --primary-dark: #E55A00;
    --secondary: #1A1A1A;
    --secondary-light: #2D2D2D;
    --background: #FFFFFF;
    --card-bg: #F8F8F8;
    --text-primary: #1A1A1A;
    --text-secondary: #666666;
    --text-light: #999999;
    --border: #E0E0E0;
    --success: #10B981;
    --error: #EF4444;
    --warning: #F59E0B;
    --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s ease;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background-color: #F5F5F5;
    color: var(--text-primary);
    line-height: 1.5;
}

/* Dashboard Container */
.account-dashboard {
    max-width: 100%;
    margin: 0 auto;
    background: var(--background);
    min-height: 100vh;
}

/* Header Section */
.dashboard-header {
    padding: 16px 16px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--background);
}

.header-top {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 20px;
    transition: var(--transition);
    margin-right: 12px;
}

.back-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}

.page-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* User Profile Section */
.user-profile-section {
    display: flex;
    align-items: center;
    padding: 0 8px;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
}

.user-avatar i {
    font-size: 32px;
    color: white;
}

.user-info {
    flex: 1;
}

.user-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.user-email {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    color: var(--primary);
    text-decoration: none;
    font-size: 18px;
    transition: var(--transition);
}

.edit-btn:hover {
    background: rgba(255, 107, 0, 0.1);
}

/* Account Summary Card (Mobile only) */
.mobile-only {
    display: block;
}

.account-summary-card {
    display: flex;
    align-items: center;
    padding: 16px;
    margin: 0 16px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    text-decoration: none;
    transition: var(--transition);
}

.account-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.summary-icon {
    font-size: 24px;
    margin-right: 16px;
}

.summary-content {
    flex: 1;
}

.summary-title {
    font-size: 14px;
    font-weight: 500;
    opacity: 0.9;
    margin-bottom: 4px;
}

.summary-subtitle {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
}

.summary-badge {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}

.summary-arrow {
    font-size: 18px;
    opacity: 0.8;
}

/* Dashboard Content */
.dashboard-content {
    padding: 0 0 100px;
}

/* Section Groups */
.section-group {
    margin-bottom: 24px;
}

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0 16px 12px;
    margin: 0;
}

/* Section List */
.section-list {
    background: var(--background);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.list-item {
    display: flex;
    align-items: center;
    padding: 16px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--border);
    transition: var(--transition);
    min-height: 72px;
}

.list-item:last-child {
    border-bottom: none;
}

.list-item:hover {
    background: #f9f9f9;
}

/* Logout list item specific styles */
.logout-list-item:hover {
    background: rgba(239, 68, 68, 0.05);
}

.logout-list-item .item-icon i {
    color: var(--error);
}

.logout-list-item .item-title {
    color: var(--error);
}

.item-icon {
    position: relative;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
}

.item-icon i {
    font-size: 20px;
    color: var(--primary);
}

.icon-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--error);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border: 2px solid white;
}

.item-content {
    flex: 1;
}

.item-title {
    font-size: 16px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.item-subtitle {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.item-arrow {
    color: var(--text-light);
    font-size: 16px;
    margin-left: 8px;
}

/* Recent Order Preview */
.recent-order-preview {
    background: #f8f9fa;
    border-radius: 8px;
    margin: 12px 16px;
    padding: 16px;
    border: 1px solid var(--border);
}

.order-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.order-preview-header h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.view-all-link {
    font-size: 14px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.order-preview-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-id {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

.order-date {
    font-size: 13px;
    color: var(--text-secondary);
}

.order-status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.order-status-badge.pending { background: #FEF3C7; color: #D97706; }
.order-status-badge.processing { background: #DBEAFE; color: #2563EB; }
.order-status-badge.delivered { background: #D1FAE5; color: #059669; }
.order-status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
.order-status-badge.shipped { background: #E0E7FF; color: #4F46E5; }

/* Alerts */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin: 0 16px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Footer Actions - Only Continue Shopping */
.footer-actions {
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
}

.btn-continue-shopping {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
    font-size: 16px;
    transition: var(--transition);
    gap: 12px;
    background: var(--primary);
    color: white;
    border: none;
    width: 100%;
}

.btn-continue-shopping:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 107, 0, 0.3);
}

/* Responsive Design for Desktop */
@media (min-width: 769px) {
    .account-dashboard {
        max-width: 1200px;
        margin: 30px auto;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    
    .dashboard-header {
        padding: 24px 30px;
    }
    
    .dashboard-content {
        padding: 0 30px 30px;
    }
    
    .mobile-only {
        display: none;
    }
    
    /* Desktop-specific card layout */
    .desktop-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .desktop-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 24px;
        border: 1px solid var(--border);
        transition: var(--transition);
    }
    
    .desktop-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-shadow);
    }
    
    .section-group {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid var(--border);
    }
    
    .section-list {
        border: none;
        background: transparent;
    }
    
    .list-item {
        border-radius: 8px;
        margin-bottom: 8px;
    }
    
    .list-item:hover {
        background: rgba(255, 107, 0, 0.05);
    }
    
    .logout-list-item:hover {
        background: rgba(239, 68, 68, 0.05);
    }
    
    .footer-actions {
        flex-direction: row;
        justify-content: center;
        gap: 20px;
    }
    
    .btn-continue-shopping {
        min-width: 200px;
        width: auto;
    }
}

/* Responsive Design for Mobile */
@media (max-width: 768px) {
    .dashboard-header {
        padding-top: 12px;
    }
    
    .user-avatar {
        width: 50px;
        height: 50px;
    }
    
    .user-avatar i {
        font-size: 28px;
    }
    
    .user-name {
        font-size: 16px;
    }
    
    .user-email {
        font-size: 13px;
    }
    
    .list-item {
        padding: 14px 16px;
        min-height: 68px;
    }
    
    .item-title {
        font-size: 15px;
    }
    
    .item-subtitle {
        font-size: 13px;
    }
    
    .footer-actions {
        padding: 16px;
    }
    
    .btn-continue-shopping {
        padding: 14px;
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .account-summary-card {
        margin: 0 12px 16px;
    }
    
    .section-title {
        padding: 0 12px 8px;
    }
    
    .list-item {
        padding: 12px;
    }
    
    .item-icon {
        margin-right: 12px;
    }
    
    .alert {
        margin: 0 12px 16px;
    }
    
    .footer-actions {
        padding: 12px;
    }
}

@media (max-width: 360px) {
    .user-avatar {
        width: 45px;
        height: 45px;
        margin-right: 12px;
    }
    
    .user-avatar i {
        font-size: 24px;
    }
    
    .edit-btn {
        width: 36px;
        height: 36px;
    }
    
    .list-item {
        padding: 10px 12px;
    }
    
    .item-icon {
        width: 36px;
        height: 36px;
        margin-right: 10px;
    }
    
    .item-icon i {
        font-size: 18px;
    }
    
    .btn-continue-shopping {
        padding: 12px;
        font-size: 14px;
    }
}
</style>