<?php
// app/views/messages/index.php

// ENHANCED: Start session if not already started and check authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use the user_logged_in flag from controller to determine what to show
$user_logged_in = $user_logged_in ?? (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']));

// If user is not logged in, show access denied message
if (!$user_logged_in) {
    $page_title = LanguageHelper::t('access_denied', 'Access Denied') . " - Phool Delivery";
    $pathConfig = PathConfig::getInstance();
    $assets_path = $pathConfig->get('assets');
    
    // Show not logged in message with registration encouragement
    echo '
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="access-denied-container text-center py-5">
                    <div class="access-denied-icon mb-4">
                        <i class="fas fa-user-lock fa-4x text-muted"></i>
                    </div>
                    <h1 class="display-6 fw-bold text-dark mb-3">' . LanguageHelper::t('you_are_not_logged_in', 'You are not logged in') . '</h1>
                    <p class="lead text-muted mb-4">' . LanguageHelper::t('login_required_for_messages', 'Please login or register to view your messages.') . '</p>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <h5 class="card-title mb-3">' . LanguageHelper::t('get_started', 'Get Started') . '</h5>
                                    <p class="text-muted mb-4">' . LanguageHelper::t('auto_registration_info', 'No need for manual registration! Just place an order and we\'ll automatically create your account.') . '</p>
                                    
                                    <div class="d-grid gap-3">
                                        <a href="' . $pathConfig->url('login') . '" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>
                                            ' . LanguageHelper::t('login_now', 'Login Now') . '
                                        </a>
                                        <a href="' . $pathConfig->url('products') . '" class="btn btn-success btn-lg">
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            ' . LanguageHelper::t('order_now_auto_register', 'Order Now & Auto Register') . '
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5">
                        <h6 class="text-muted mb-3">' . LanguageHelper::t('how_it_works', 'How it works:') . '</h6>
                        <div class="row text-start">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <span class="badge bg-primary me-3">1</span>
                                    <div>
                                        <strong>' . LanguageHelper::t('step_1_order', 'Place Your Order') . '</strong>
                                        <p class="small text-muted mb-0">' . LanguageHelper::t('step_1_desc', 'Add flowers to cart and proceed to checkout') . '</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <span class="badge bg-primary me-3">2</span>
                                    <div>
                                        <strong>' . LanguageHelper::t('step_2_fill_details', 'Fill Your Details') . '</strong>
                                        <p class="small text-muted mb-0">' . LanguageHelper::t('step_2_desc', 'Enter your delivery information and contact details') . '</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex">
                                    <span class="badge bg-primary me-3">3</span>
                                    <div>
                                        <strong>' . LanguageHelper::t('step_3_auto_account', 'Get Auto Account') . '</strong>
                                        <p class="small text-muted mb-0">' . LanguageHelper::t('step_3_desc', 'We automatically create your account for future orders') . '</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    exit;
}

// User is logged in - show the messages page
$page_title = LanguageHelper::t('my_messages', 'My Messages') . " - Phool Delivery";
$pathConfig = PathConfig::getInstance();
$assets_path = $pathConfig->get('assets');

$messages = $messages ?? [];
$current_page = $current_page ?? 1;
$total_pages = $total_pages ?? 1;
$total_messages = $total_messages ?? 0;

// Message types for filtering
$message_types = [
    'all' => LanguageHelper::t('all_messages', 'All Messages'),
    'order' => LanguageHelper::t('order_updates', 'Order Updates'),
    'promotion' => LanguageHelper::t('promotions', 'Promotions'),
    'system' => LanguageHelper::t('system_messages', 'System')
];
?>

<div class="container-fluid message-container">
    <div class="row">
        <!-- Mobile Filter Button -->
        <div class="col-12 d-lg-none d-block">
            <div class="mobile-filter-header mb-3">
                <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterSidebar">
                    <i class="fas fa-filter me-2"></i>
                    <?= LanguageHelper::t('show_filters', 'Show Filters') ?>
                </button>
            </div>
        </div>

        <!-- Sidebar - Hidden by default on mobile, shown via offcanvas -->
        <div class="col-lg-3 col-md-4 d-none d-lg-block">
            <div class="message-sidebar">
                <div class="sidebar-header">
                    <h2><?= LanguageHelper::t('my_messages', 'My Messages') ?></h2>
                    <p class="text-muted"><?= LanguageHelper::t('message_count', 'Total messages:') ?> <?= $total_messages ?></p>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="btn btn-primary w-100 mb-3" id="markAllReadBtn">
                        <i class="fas fa-check-double me-2"></i>
                        <?= LanguageHelper::t('mark_all_read', 'Mark All as Read') ?>
                    </button>
                    
                    <div class="filter-section">
                        <h6 class="filter-title"><?= LanguageHelper::t('filter_by', 'Filter by:') ?></h6>
                        <div class="filter-options">
                            <?php foreach ($message_types as $type => $label): ?>
                                <button class="filter-btn <?= $type === 'all' ? 'active' : '' ?>" 
                                        data-type="<?= $type ?>">
                                    <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Message Stats -->
                <div class="message-stats">
                    <div class="stat-item">
                        <span class="stat-count"><?= count(array_filter($messages, fn($msg) => !$msg['is_read'])) ?></span>
                        <span class="stat-label"><?= LanguageHelper::t('unread', 'Unread') ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-count"><?= count($messages) ?></span>
                        <span class="stat-label"><?= LanguageHelper::t('total', 'Total') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9 col-md-8 col-12">
            <div class="message-main-content">
                <!-- Header -->
                <div class="message-header">
                    <div class="header-left">
                        <h1 class="page-title"><?= LanguageHelper::t('my_messages', 'My Messages') ?></h1>
                        <div class="unread-badge" id="unreadCountBadge">
                            <?= count(array_filter($messages, fn($msg) => !$msg['is_read'])) ?> <?= LanguageHelper::t('unread', 'unread') ?>
                        </div>
                    </div>
                    <div class="header-actions">
                        <!-- Modified: All three elements in single row for mobile -->
                        <div class="mobile-controls-row d-lg-none d-flex align-items-center gap-2 w-100">
                            <div class="search-box flex-grow-1">
                                <input type="text" id="messageSearch" 
                                       placeholder="<?= LanguageHelper::t('search_messages', 'Search messages...') ?>"
                                       class="form-control">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <div class="sort-dropdown flex-shrink-0">
                                <select id="sortMessages" class="form-select">
                                    <option value="newest"><?= LanguageHelper::t('newest_first', 'Newest First') ?></option>
                                    <option value="oldest"><?= LanguageHelper::t('oldest_first', 'Oldest First') ?></option>
                                    <option value="unread"><?= LanguageHelper::t('unread_first', 'Unread First') ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Desktop layout remains the same -->
                        <div class="d-none d-lg-flex align-items-center gap-3">
                            <div class="search-box">
                                <input type="text" id="messageSearchDesktop" 
                                       placeholder="<?= LanguageHelper::t('search_messages', 'Search messages...') ?>"
                                       class="form-control">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <div class="sort-dropdown">
                                <select id="sortMessagesDesktop" class="form-select">
                                    <option value="newest"><?= LanguageHelper::t('newest_first', 'Newest First') ?></option>
                                    <option value="oldest"><?= LanguageHelper::t('oldest_first', 'Oldest First') ?></option>
                                    <option value="unread"><?= LanguageHelper::t('unread_first', 'Unread First') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages List -->
                <div class="messages-list" id="messagesList">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <h3><?= LanguageHelper::t('no_messages', 'No Messages Yet') ?></h3>
                            <p><?= LanguageHelper::t('no_messages_desc', 'You don\'t have any messages yet. We\'ll notify you here about your orders and promotions.') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item <?= !$message['is_read'] ? 'unread' : '' ?>" 
                                 data-message-id="<?= $message['id'] ?>"
                                 data-type="<?= $message['type'] ?>">
                                <div class="message-checkbox">
                                    <input type="checkbox" class="message-check" value="<?= $message['id'] ?>">
                                </div>
                                <div class="message-content" onclick="openMessage(<?= $message['id'] ?>)">
                                    <div class="message-header">
                                        <div class="message-title">
                                            <h4 class="title-text"><?= htmlspecialchars($message['title']) ?></h4>
                                            <?php if (!$message['is_read']): ?>
                                                <span class="unread-indicator"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-meta">
                                            <span class="message-date">
                                                <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                            </span>
                                            <span class="message-type badge type-<?= $message['type'] ?>">
                                                <?= $message_types[$message['type']] ?? ucfirst($message['type']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="message-preview">
                                        <p class="preview-text"><?= htmlspecialchars($message['message']) ?></p>
                                    </div>
                                </div>
                                <div class="message-actions">
                                    <button class="btn-action mark-read-btn" 
                                            title="<?= LanguageHelper::t('mark_as_read', 'Mark as Read') ?>"
                                            onclick="markAsRead(<?= $message['id'] ?>, event)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-action delete-btn" 
                                            title="<?= LanguageHelper::t('delete_message', 'Delete Message') ?>"
                                            onclick="deleteMessage(<?= $message['id'] ?>, event)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions" style="display: none;">
                    <div class="bulk-actions-content">
                        <span id="selectedCount">0</span> <?= LanguageHelper::t('selected', 'selected') ?>
                        <button class="btn btn-outline-primary btn-sm ms-2" id="bulkMarkRead">
                            <i class="fas fa-check me-1"></i>
                            <?= LanguageHelper::t('mark_read', 'Mark Read') ?>
                        </button>
                        <button class="btn btn-outline-danger btn-sm ms-2" id="bulkDelete">
                            <i class="fas fa-trash me-1"></i>
                            <?= LanguageHelper::t('delete', 'Delete') ?>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm ms-2" id="clearSelection">
                            <?= LanguageHelper::t('clear', 'Clear') ?>
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="message-pagination">
                        <ul class="pagination">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $current_page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $current_page + 1 ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Filter Sidebar (Offcanvas) -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="filterSidebar">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><?= LanguageHelper::t('filters', 'Filters') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="message-sidebar mobile-sidebar">
            <div class="sidebar-header">
                <h2><?= LanguageHelper::t('my_messages', 'My Messages') ?></h2>
                <p class="text-muted"><?= LanguageHelper::t('message_count', 'Total messages:') ?> <?= $total_messages ?></p>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button class="btn btn-primary w-100 mb-3" id="markAllReadBtnMobile">
                    <i class="fas fa-check-double me-2"></i>
                    <?= LanguageHelper::t('mark_all_read', 'Mark All as Read') ?>
                </button>
                
                <div class="filter-section">
                    <h6 class="filter-title"><?= LanguageHelper::t('filter_by', 'Filter by:') ?></h6>
                    <div class="filter-options">
                        <?php foreach ($message_types as $type => $label): ?>
                            <button class="filter-btn <?= $type === 'all' ? 'active' : '' ?>" 
                                    data-type="<?= $type ?>"
                                    data-bs-dismiss="offcanvas">
                                <?= $label ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Message Stats -->
            <div class="message-stats">
                <div class="stat-item">
                    <span class="stat-count"><?= count(array_filter($messages, fn($msg) => !$msg['is_read'])) ?></span>
                    <span class="stat-label"><?= LanguageHelper::t('unread', 'Unread') ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-count"><?= count($messages) ?></span>
                    <span class="stat-label"><?= LanguageHelper::t('total', 'Total') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message Detail Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="message-meta-modal">
                    <span class="message-date-modal" id="messageModalDate"></span>
                    <span class="message-type-modal badge" id="messageModalType"></span>
                </div>
                <div class="message-content-modal" id="messageModalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= LanguageHelper::t('close', 'Close') ?>
                </button>
                <button type="button" class="btn btn-primary" id="markReadModalBtn">
                    <i class="fas fa-check me-1"></i>
                    <?= LanguageHelper::t('mark_as_read', 'Mark as Read') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="loading-spinner" id="loadingSpinner" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<script>
class MessageManager {
    constructor() {
        this.selectedMessages = new Set();
        this.currentFilter = 'all';
        this.currentSort = 'newest';
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateUnreadCount();
    }

    bindEvents() {
        // Mark all as read - both desktop and mobile
        document.getElementById('markAllReadBtn').addEventListener('click', () => this.markAllAsRead());
        document.getElementById('markAllReadBtnMobile').addEventListener('click', () => this.markAllAsRead());
        
        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.filterMessages(e.target.dataset.type));
        });

        // Search functionality - both mobile and desktop
        document.getElementById('messageSearch').addEventListener('input', 
            this.debounce(() => this.searchMessages(), 300));
        document.getElementById('messageSearchDesktop').addEventListener('input', 
            this.debounce(() => this.searchMessages(), 300));

        // Sort functionality - both mobile and desktop
        document.getElementById('sortMessages').addEventListener('change', 
            (e) => this.sortMessages(e.target.value));
        document.getElementById('sortMessagesDesktop').addEventListener('change', 
            (e) => this.sortMessages(e.target.value));

        // Bulk actions
        document.getElementById('bulkMarkRead').addEventListener('click', () => this.bulkMarkRead());
        document.getElementById('bulkDelete').addEventListener('click', () => this.bulkDelete());
        document.getElementById('clearSelection').addEventListener('click', () => this.clearSelection());

        // Checkbox events
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('message-check')) {
                this.toggleMessageSelection(e.target.value, e.target.checked);
            }
        });

        // Close offcanvas when filter is selected on mobile
        document.querySelectorAll('.filter-btn[data-bs-dismiss="offcanvas"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('filterSidebar'));
                offcanvas.hide();
            });
        });
    }

    async markAsRead(messageId, event = null) {
        if (event) event.stopPropagation();
        
        try {
            this.showLoading();
            const response = await fetch('<?= $pathConfig->url('api/messages/mark-read') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message_id: messageId })
            });

            const data = await response.json();
            
            if (data.success) {
                // Update UI
                const messageItem = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageItem) {
                    messageItem.classList.remove('unread');
                    messageItem.querySelector('.unread-indicator')?.remove();
                }
                
                this.updateUnreadCount();
                this.showToast('<?= LanguageHelper::t('message_marked_read', 'Message marked as read') ?>', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error marking message as read:', error);
            this.showToast('<?= LanguageHelper::t('operation_failed', 'Operation failed') ?>', 'error');
        } finally {
            this.hideLoading();
        }
    }

    async markAllAsRead() {
        if (!confirm('<?= LanguageHelper::t('confirm_mark_all_read', 'Are you sure you want to mark all messages as read?') ?>')) {
            return;
        }

        try {
            this.showLoading();
            const response = await fetch('<?= $pathConfig->url('api/messages/mark-all-read') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                // Update all messages to read
                document.querySelectorAll('.message-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.querySelector('.unread-indicator')?.remove();
                });
                
                this.updateUnreadCount();
                this.showToast('<?= LanguageHelper::t('all_messages_marked_read', 'All messages marked as read') ?>', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error marking all messages as read:', error);
            this.showToast('<?= LanguageHelper::t('operation_failed', 'Operation failed') ?>', 'error');
        } finally {
            this.hideLoading();
        }
    }

    async deleteMessage(messageId, event = null) {
        if (event) event.stopPropagation();
        
        if (!confirm('<?= LanguageHelper::t('confirm_delete_message', 'Are you sure you want to delete this message?') ?>')) {
            return;
        }

        try {
            this.showLoading();
            const formData = new FormData();
            formData.append('message_id', messageId);

            const response = await fetch('<?= $pathConfig->url('api/messages/delete') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                // Remove message from UI
                const messageItem = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageItem) {
                    messageItem.style.opacity = '0';
                    setTimeout(() => messageItem.remove(), 300);
                }
                
                this.updateUnreadCount();
                this.showToast('<?= LanguageHelper::t('message_deleted', 'Message deleted successfully') ?>', 'success');
                
                // Check if no messages left
                if (document.querySelectorAll('.message-item').length === 0) {
                    this.showEmptyState();
                }
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            this.showToast('<?= LanguageHelper::t('operation_failed', 'Operation failed') ?>', 'error');
        } finally {
            this.hideLoading();
        }
    }

    toggleMessageSelection(messageId, isSelected) {
        if (isSelected) {
            this.selectedMessages.add(messageId);
        } else {
            this.selectedMessages.delete(messageId);
        }
        this.updateBulkActions();
    }

    updateBulkActions() {
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        if (this.selectedMessages.size > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = this.selectedMessages.size;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    async bulkMarkRead() {
        if (this.selectedMessages.size === 0) return;

        try {
            this.showLoading();
            const promises = Array.from(this.selectedMessages).map(messageId => 
                this.markAsRead(messageId)
            );

            await Promise.all(promises);
            this.clearSelection();
            this.showToast('<?= LanguageHelper::t('selected_messages_marked_read', 'Selected messages marked as read') ?>', 'success');
        } catch (error) {
            console.error('Error in bulk mark read:', error);
            this.showToast('<?= LanguageHelper::t('operation_failed', 'Operation failed') ?>', 'error');
        } finally {
            this.hideLoading();
        }
    }

    async bulkDelete() {
        if (this.selectedMessages.size === 0) return;

        if (!confirm(`<?= LanguageHelper::t('confirm_delete_selected', 'Are you sure you want to delete') ?> ${this.selectedMessages.size} <?= LanguageHelper::t('messages', 'messages') ?>?`)) {
            return;
        }

        try {
            this.showLoading();
            const promises = Array.from(this.selectedMessages).map(messageId => 
                this.deleteMessage(messageId)
            );

            await Promise.all(promises);
            this.clearSelection();
        } catch (error) {
            console.error('Error in bulk delete:', error);
            this.showToast('<?= LanguageHelper::t('operation_failed', 'Operation failed') ?>', 'error');
        } finally {
            this.hideLoading();
        }
    }

    clearSelection() {
        this.selectedMessages.clear();
        document.querySelectorAll('.message-check:checked').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateBulkActions();
    }

    filterMessages(type) {
        this.currentFilter = type;
        
        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.type === type);
        });

        // Filter messages
        document.querySelectorAll('.message-item').forEach(item => {
            if (type === 'all' || item.dataset.type === type) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    sortMessages(sortBy) {
        this.currentSort = sortBy;
        const messagesList = document.getElementById('messagesList');
        const messages = Array.from(messagesList.querySelectorAll('.message-item'));

        messages.sort((a, b) => {
            const aDate = new Date(a.querySelector('.message-date').textContent);
            const bDate = new Date(b.querySelector('.message-date').textContent);
            const aUnread = a.classList.contains('unread');
            const bUnread = b.classList.contains('unread');

            switch (sortBy) {
                case 'newest':
                    return bDate - aDate;
                case 'oldest':
                    return aDate - bDate;
                case 'unread':
                    if (aUnread && !bUnread) return -1;
                    if (!aUnread && bUnread) return 1;
                    return bDate - aDate;
                default:
                    return 0;
            }
        });

        // Reappend sorted messages
        messages.forEach(message => messagesList.appendChild(message));
    }

    searchMessages() {
        const searchTermMobile = document.getElementById('messageSearch').value.toLowerCase();
        const searchTermDesktop = document.getElementById('messageSearchDesktop').value.toLowerCase();
        const searchTerm = searchTermMobile || searchTermDesktop;
        
        document.querySelectorAll('.message-item').forEach(item => {
            const title = item.querySelector('.title-text').textContent.toLowerCase();
            const content = item.querySelector('.preview-text').textContent.toLowerCase();
            const matches = title.includes(searchTerm) || content.includes(searchTerm);
            
            item.style.display = matches ? 'flex' : 'none';
        });
    }

    updateUnreadCount() {
        const unreadCount = document.querySelectorAll('.message-item.unread').length;
        const unreadBadge = document.getElementById('unreadCountBadge');
        
        if (unreadBadge) {
            unreadBadge.textContent = `${unreadCount} <?= LanguageHelper::t('unread', 'unread') ?>`;
            unreadBadge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        // Update header message count
        const headerBadges = document.querySelectorAll('.message-badge');
        headerBadges.forEach(badge => {
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    showEmptyState() {
        const messagesList = document.getElementById('messagesList');
        messagesList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <h3><?= LanguageHelper::t('no_messages', 'No Messages Yet') ?></h3>
                <p><?= LanguageHelper::t('no_messages_desc', 'You don\'t have any messages yet. We\'ll notify you here about your orders and promotions.') ?></p>
            </div>
        `;
    }

    showLoading() {
        document.getElementById('loadingSpinner').style.display = 'block';
    }

    hideLoading() {
        document.getElementById('loadingSpinner').style.display = 'none';
    }

    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Global functions for onclick handlers
function openMessage(messageId) {
    const messageItem = document.querySelector(`[data-message-id="${messageId}"]`);
    const title = messageItem.querySelector('.title-text').textContent;
    const content = messageItem.querySelector('.preview-text').textContent;
    const date = messageItem.querySelector('.message-date').textContent;
    const type = messageItem.querySelector('.message-type').textContent;
    
    // Set modal content
    document.getElementById('messageModalTitle').textContent = title;
    document.getElementById('messageModalContent').textContent = content;
    document.getElementById('messageModalDate').textContent = date;
    document.getElementById('messageModalType').textContent = type;
    document.getElementById('messageModalType').className = `message-type-modal badge type-${messageItem.dataset.type}`;
    
    // Set mark as read button handler
    document.getElementById('markReadModalBtn').onclick = () => {
        messageManager.markAsRead(messageId);
        const modal = bootstrap.Modal.getInstance(document.getElementById('messageModal'));
        modal.hide();
    };
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
    modal.show();
    
    // Mark as read when opening if unread
    if (messageItem.classList.contains('unread')) {
        messageManager.markAsRead(messageId);
    }
}

function markAsRead(messageId, event) {
    messageManager.markAsRead(messageId, event);
}

function deleteMessage(messageId, event) {
    messageManager.deleteMessage(messageId, event);
}

// Initialize message manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.messageManager = new MessageManager();
});
</script>

<style>
.access-denied-container {
    min-height: 70vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.access-denied-icon {
    color: #6c757d;
}

.auto-registration-steps {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 2rem;
}

.step-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.step-card:hover {
    transform: translateY(-5px);
}

.badge-step {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<style>
.message-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* Mobile Filter Header */
.mobile-filter-header {
    background: #fff;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Sidebar Styles */
.message-sidebar {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.mobile-sidebar {
    position: static;
    box-shadow: none;
    padding: 0;
}

.sidebar-header h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1a1a1a;
}

.quick-actions {
    margin: 24px 0;
}

.filter-section {
    margin-top: 20px;
}

.filter-title {
    font-weight: 600;
    margin-bottom: 12px;
    color: #4a5568;
}

.filter-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-btn {
    background: none;
    border: 1px solid #e2e8f0;
    padding: 10px 16px;
    border-radius: 8px;
    text-align: left;
    transition: all 0.2s;
    color: #4a5568;
}

.filter-btn:hover,
.filter-btn.active {
    background: #edf2f7;
    border-color: #cbd5e0;
    color: #2d3748;
}

.filter-btn.active {
    background: #4299e1;
    color: white;
    border-color: #4299e1;
}

.message-stats {
    display: flex;
    gap: 20px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.stat-item {
    text-align: center;
}

.stat-count {
    display: block;
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
}

.stat-label {
    font-size: 0.875rem;
    color: #718096;
}

/* Main Content Styles */
.message-main-content {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.message-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
    color: #1a1a1a;
}

.unread-badge {
    background: #e53e3e;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 16px;
    align-items: center;
}

/* NEW: Mobile controls row */
.mobile-controls-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.mobile-controls-row .search-box {
    position: relative;
    flex: 1;
    min-width: 0;
}

.mobile-controls-row .search-box input {
    padding-left: 40px;
    width: 100%;
}

.mobile-controls-row .sort-dropdown {
    min-width: 140px;
    flex-shrink: 0;
}

.search-box {
    position: relative;
    min-width: 250px;
}

.search-box input {
    padding-left: 40px;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.sort-dropdown {
    min-width: 150px;
}

/* Message List Styles */
.messages-list {
    max-height: 600px;
    overflow-y: auto;
}

.message-item {
    display: flex;
    align-items: flex-start;
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 12px;
    transition: all 0.2s;
    background: #fff;
    gap: 12px;
}

.message-item:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.message-item.unread {
    background: #f7fafc;
    border-left: 4px solid #4299e1;
}

.message-checkbox {
    padding-top: 4px;
}

.message-check {
    width: 18px;
    height: 18px;
}

.message-content {
    flex: 1;
    cursor: pointer;
    min-width: 0;
}

.message-header {
    display: flex;
    justify-content: between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 12px;
}

.message-title {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.title-text {
    margin: 0;
    font-size: 1rem;
    font-weight: 500;
    color: #2d3748;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-indicator {
    width: 8px;
    height: 8px;
    background: #4299e1;
    border-radius: 50%;
    flex-shrink: 0;
}

.message-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.message-date {
    font-size: 0.8rem;
    color: #718096;
}

.message-type {
    font-size: 0.7rem;
    padding: 3px 6px;
}

.type-order { background: #e6fffa; color: #234e52; }
.type-promotion { background: #fffaf0; color: #744210; }
.type-system { background: #f0fff4; color: #22543d; }

.message-preview {
    color: #4a5568;
    line-height: 1.4;
}

.preview-text {
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 0.9rem;
}

.message-actions {
    display: flex;
    gap: 6px;
    opacity: 1;
    transition: opacity 0.2s;
}

.btn-action {
    background: none;
    border: 1px solid #e2e8f0;
    padding: 6px;
    border-radius: 6px;
    color: #718096;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 0.8rem;
}

.btn-action:hover {
    background: #f7fafc;
    color: #4a5568;
}

.mark-read-btn:hover {
    border-color: #4299e1;
    color: #4299e1;
}

.delete-btn:hover {
    border-color: #e53e3e;
    color: #e53e3e;
}

/* Bulk Actions */
.bulk-actions {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    padding: 12px;
    margin: 20px -24px -24px;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
}

.bulk-actions-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.empty-icon {
    font-size: 3rem;
    color: #cbd5e0;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: #4a5568;
    margin-bottom: 8px;
    font-size: 1.3rem;
}

/* Pagination */
.message-pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
}

.pagination {
    gap: 6px;
}

.page-link {
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    padding: 6px 12px;
    font-size: 0.9rem;
}

.page-item.active .page-link {
    background: #4299e1;
    border-color: #4299e1;
}

/* Modal Styles */
.message-meta-modal {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 8px;
}

.message-content-modal {
    line-height: 1.5;
    color: #4a5568;
    font-size: 0.95rem;
}

/* Loading Spinner */
.loading-spinner {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
}

/* Offcanvas Styles */
.offcanvas {
    max-width: 280px;
}

.offcanvas-header {
    border-bottom: 1px solid #e2e8f0;
}

.offcanvas-body {
    padding: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .message-container {
        padding: 12px;
    }
    
    .message-main-content {
        padding: 16px;
    }
    
    .message-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .page-title {
        font-size: 1.4rem;
    }
    
    /* NEW: Mobile controls row specific styles */
    .mobile-controls-row {
        flex-direction: row;
        gap: 8px;
    }
    
    .mobile-controls-row .search-box {
        flex: 1;
    }
    
    .mobile-controls-row .sort-dropdown {
        min-width: 130px;
    }
    
    .message-item {
        flex-direction: column;
        gap: 10px;
        padding: 12px;
    }
    
    .message-actions {
        align-self: flex-end;
        opacity: 1;
    }
    
    .message-header .message-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    
    .message-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .bulk-actions {
        padding: 10px;
        margin: 16px -16px -16px;
    }
    
    .bulk-actions-content {
        justify-content: center;
    }
    
    .empty-state {
        padding: 30px 15px;
    }
    
    .empty-icon {
        font-size: 2.5rem;
    }
    
    .empty-state h3 {
        font-size: 1.2rem;
    }
}

@media (max-width: 576px) {
    .message-container {
        padding: 8px;
    }
    
    .message-main-content {
        padding: 12px;
        border-radius: 8px;
    }
    
    .message-item {
        padding: 10px;
    }
    
    .title-text {
        font-size: 0.95rem;
    }
    
    .preview-text {
        font-size: 0.85rem;
    }
    
    .message-date {
        font-size: 0.75rem;
    }
    
    /* NEW: Adjust mobile controls for very small screens */
    .mobile-controls-row {
        gap: 6px;
    }
    
    .mobile-controls-row .sort-dropdown {
        min-width: 120px;
    }
    
    .mobile-controls-row .form-select {
        font-size: 0.85rem;
    }
}

/* NEW: Very small screen adjustments */
@media (max-width: 380px) {
    .mobile-controls-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .mobile-controls-row .search-box,
    .mobile-controls-row .sort-dropdown {
        width: 100%;
    }
}
</style>