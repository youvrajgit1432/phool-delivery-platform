<?php
// app/views/vieworder/orders.php

// Get PathConfig instance
$pathConfig = PathConfig::getInstance();
$page_title = LanguageHelper::t('my_orders', 'My Orders') . " - Phool Delivery";
$base_url = $pathConfig->getBasePath();
$assets_path = $pathConfig->get('assets');

if (isset($error)): ?>
    <div class="account-container">
        <div class="alert alert-danger" data-aos="zoom-in" data-aos-delay="100"><i class="fas fa-exclamation-triangle"></i><?php echo $error; ?></div>
        <a href="<?php echo $pathConfig->url('account/orders'); ?>" class="btn btn-primary" data-aos="zoom-in" data-aos-delay="150"><i class="fas fa-arrow-left"></i> <?= LanguageHelper::t('back', 'Back') ?></a>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="account-container">
    <?php if (isset($order)): ?>
        <!-- Single order view -->
       <a href="<?= $pathConfig->url('account') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid #333;
        border-radius: 6px;
        background-color: transparent;
        color: #333;
        font-size: 14px;
        text-decoration: none;
        transition: 0.2s ease-in-out;
   "

   onmouseover="this.style.backgroundColor='#827b7bff'; this.style.color='#fff';"
   onmouseout="this.style.backgroundColor='transparent'; this.style.color='#887979ff';"
>
    <i class="fas fa-arrow-left"></i> 
    <?= LanguageHelper::t('back_to_dashboard', 'Back ') ?>
</a>
 <br>
        <div class="account-header" data-aos="fade-up" data-aos-delay="100">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2><?= LanguageHelper::t('order_number', 'Order') ?> #<?php echo $order['id']; ?></h2>
                    <p><?= LanguageHelper::t('placed_on', 'Placed on') ?> <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <span class="order-status <?php echo strtolower($order['status']); ?>" data-aos="zoom-in" data-aos-delay="150"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span>
            </div>
        </div>
        
        <div class="order-details-container">
            <!-- Sticky Cards Section -->
            <div class="sticky-cards-container">
                <div class="order-summary" id="orderSummary">
                    <div class="summary-card" data-aos="zoom-in" data-aos-delay="200">
                        <h4><?= LanguageHelper::t('customer_information', 'Customer Information') ?></h4>
                        <p><?php echo $order['customer_name']; ?></p>
                        <small><?php echo $order['email']; ?></small><br>
                        <small><?php echo $order['phone']; ?></small>
                    </div>
                    
                    <div class="summary-card" data-aos="zoom-in" data-aos-delay="250">
                        <h4><?= LanguageHelper::t('delivery_address', 'Delivery Address') ?></h4>
                        <p><?php echo $order['address']; ?>, <?php echo $order['city']; ?></p>
                        <?php if ($order['street']): ?><small><?php echo $order['street']; ?></small><?php endif; ?>
                    </div>
                    
                    <div class="summary-card" data-aos="zoom-in" data-aos-delay="300">
                        <h4><?= LanguageHelper::t('payment_method', 'Payment Method') ?></h4>
                        <p><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                        <small><?= LanguageHelper::t('status', 'Status') ?>: <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?></small>
                    </div>
                    
                    <?php if ($order['delivery_date']): ?>
                    <div class="summary-card" data-aos="zoom-in" data-aos-delay="350">
                        <h4><?= LanguageHelper::t('expected_delivery', 'Expected Delivery') ?></h4>
                        <p><?php echo date('F j, Y \a\t g:i A', strtotime($order['delivery_date'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- View All Button for Mobile -->
                <div class="view-all-container mobile-only">
                    <button class="btn btn-outline view-all-btn" data-aos="zoom-in" data-aos-delay="400">
                        <i class="fas fa-chevron-down"></i> <?= LanguageHelper::t('view_all', 'View All') ?>
                    </button>
                </div>
            </div>
            
            <!-- Order Tracking Timeline -->
            <div class="tracking-timeline" data-aos="fade-up" data-aos-delay="300">
                <h4><?= LanguageHelper::t('order_tracking', 'Order Tracking') ?></h4>
                <?php
                $status_timeline = [
                    'pending' => ['icon' => 'fa-clock', 'title' => LanguageHelper::t('order_placed', 'Order Placed'), 'description' => LanguageHelper::t('order_received', 'Your order has been received')],
                    'confirmed' => ['icon' => 'fa-check', 'title' => LanguageHelper::t('order_confirmed', 'Order Confirmed'), 'description' => LanguageHelper::t('order_confirmed_desc', 'We\'ve confirmed your order')],
                    'preparing' => ['icon' => 'fa-cog', 'title' => LanguageHelper::t('preparing_order', 'Preparing Order'), 'description' => LanguageHelper::t('preparing_order_desc', 'Your items are being prepared')],
                    'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'title' => LanguageHelper::t('out_for_delivery', 'Out for Delivery'), 'description' => LanguageHelper::t('out_for_delivery_desc', 'Your order is on the way')],
                    'delivered' => ['icon' => 'fa-check-circle', 'title' => LanguageHelper::t('delivered', 'Delivered'), 'description' => LanguageHelper::t('delivered_desc', 'Your order has been delivered')]
                ];
                
                $current_status_index = array_search($order['status'], array_keys($status_timeline));
                if ($current_status_index === false) {
                    $current_status_index = -1; // For cancelled orders or unknown status
                }
                
                foreach ($status_timeline as $status_key => $status_info):
                    $is_active = array_search($status_key, array_keys($status_timeline)) <= $current_status_index;
                    $is_current = $status_key === $order['status'];
                ?>
                <div class="timeline-item <?php echo $is_current ? 'current' : ''; ?>" data-aos="fade-right" data-aos-delay="<?= array_search($status_key, array_keys($status_timeline)) * 100 + 350 ?>">
                    <div class="timeline-icon <?php echo $is_active ? 'active' : 'inactive'; ?>">
                        <i class="fas <?php echo $status_info['icon']; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h5><?php echo $status_info['title']; ?></h5>
                        <p><?php echo $status_info['description']; ?></p>
                        <?php 
                        // Show timestamp for completed steps
                        if ($is_active && isset($order['tracking']) && !empty($order['tracking'])): 
                            $tracking_entry = null;
                            foreach ($order['tracking'] as $entry) {
                                if ($entry['status'] === $status_key) {
                                    $tracking_entry = $entry;
                                    break;
                                }
                            }
                            if ($tracking_entry): ?>
                            <small><?= LanguageHelper::t('completed', 'Completed') ?>: <?php echo date('M j, g:i A', strtotime($tracking_entry['created_at'])); ?></small>
                        <?php endif; endif; ?>
                        
                        <?php if ($is_current && !$is_active): ?>
                            <small class="text-warning"><?= LanguageHelper::t('current_step', 'Current Step') ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($order['status'] === 'cancelled'): ?>
                <div class="timeline-item current" data-aos="fade-right" data-aos-delay="850">
                    <div class="timeline-icon cancelled">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="timeline-content">
                        <h5><?= LanguageHelper::t('order_cancelled', 'Order Cancelled') ?></h5>
                        <p><?= LanguageHelper::t('order_cancelled_desc', 'This order has been cancelled') ?></p>
                        <small><?= LanguageHelper::t('cancelled', 'Cancelled') ?>: <?php echo date('M j, g:i A', strtotime($order['updated_at'])); ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            
            <!-- Order Items -->
            <div class="order-items" data-aos="fade-up" data-aos-delay="400">
                <h4><?= LanguageHelper::t('order_items', 'Order Items') ?> (<?php echo count($order['items']); ?>)</h4>
                <?php foreach ($order['items'] as $index => $item): ?>
                <div class="order-item" data-aos="zoom-in" data-aos-delay="<?= $index * 100 + 450 ?>">
                    <div class="item-image">
                        <?php 
                        // Use PathConfig to get proper image path
                        $imagePath = $pathConfig->getImagePath($item['image'], 'product');
                        ?>
                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjFGNUY5Ii8+CjxwYXRoIGQ9Ik0zMCAzNkMzMy4zMTM3IDM2IDM2IDMzLjMxMzcgMzYgMzBDMzYgMjYuNjg2MyAzMy4zMTM3IDI0IDMwIDI0QzI2LjY4NjMgMjQgMjQgMjYuNjg2MyAyNCAzMEMyNCAzMy4zMTM3IDI2LjY4NjMgMzYgMzAgMzZaIiBmaWxsPSIjQ0JENUUxIi8+CjxwYXRoIGQ9Ik0yNCA0MkMzNiAyNCAzNiAyNCAzNiAyNCIgZmlsbD0iI0NCRDVFMSIvPgo8L3N2Zz4K'">
                    </div>
                    <div class="item-details">
                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="item-meta"><?= LanguageHelper::t('quantity', 'Quantity') ?>: <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['unit_price'], 2); ?></div>
                        <?php if (!empty($item['description'])): ?>
                            <div class="item-meta"><?php echo substr(htmlspecialchars($item['description']), 0, 100); ?>...</div>
                        <?php endif; ?>
                    </div>
                    <div class="item-price">Rs. <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></div>
                </div>
                <?php endforeach; ?>
                
                <div class="order-total" data-aos="zoom-in" data-aos-delay="550">
                    <h4><?= LanguageHelper::t('total', 'Total') ?>: Rs. <?php echo number_format($order['total_amount'], 2); ?></h4>
                </div>
            </div>
            
            <!-- Order Actions -->
            <div class="order-actions mt-4">
                <a href="<?php echo $pathConfig->url('account/orders'); ?>" class="btn btn-outline" data-aos="zoom-in" data-aos-delay="600"><i class="fas fa-arrow-left"></i> <?= LanguageHelper::t('back', 'Back') ?></a>
                
                <?php if ($order['status'] === 'pending'): ?>
                    <button class="btn btn-danger cancel-order-btn" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="650"><i class="fas fa-times"></i> <?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                <?php elseif ($order['status'] === 'delivered'): ?>
              <?php elseif (in_array($order['status'], ['confirmed', 'preparing', 'out_for_delivery'])): ?>
                    <button class="btn btn-info contact-support-btn" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="650"><i class="fas fa-headset"></i> <?= LanguageHelper::t('support', 'Support') ?></button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Orders list view -->
        <div class="account-header" data-aos="fade-up" data-aos-delay="100">
            <h2><?= LanguageHelper::t('my_orders', 'My Orders') ?></h2>
            <p><?= LanguageHelper::t('view_order_history', 'View your order history and track current orders') ?></p>
        </div>
               <a href="<?= $pathConfig->url('account') ?>" 
   style="
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border: 1px solid #333;
        border-radius: 6px;
        background-color: transparent;
        color: #030303ff;
        font-size: 14px;
        text-decoration: none;
        transition: 0.2s ease-in-out;
   "
   onmouseover="this.style.backgroundColor='#827b7bff'; this.style.color='#fff';"
   onmouseout="this.style.backgroundColor='transparent'; this.style.color='#312f2fff';"
>
    <i class="fas fa-arrow-left"></i> 
    <?= LanguageHelper::t('back_to_dashboard', 'Back') ?>
</a>
        <!-- Order Actions Filter -->
        <div class="order-actions-filter">
            <?php
            $filters = [
                'all' => ['icon' => 'fa-shopping-cart', 'label' => LanguageHelper::t('all_orders', 'All Orders'), 'count' => count($recent_orders)],
                'pending' => ['icon' => 'fa-clock', 'label' => LanguageHelper::t('pending', 'Pending'), 'count' => $orders_by_status['pending'] ?? 0],
                'confirmed' => ['icon' => 'fa-check', 'label' => LanguageHelper::t('confirmed', 'Confirmed'), 'count' => $orders_by_status['confirmed'] ?? 0],
                'preparing' => ['icon' => 'fa-cog', 'label' => LanguageHelper::t('preparing', 'Preparing'), 'count' => $orders_by_status['preparing'] ?? 0],
                'out_for_delivery' => ['icon' => 'fa-shipping-fast', 'label' => LanguageHelper::t('out_for_delivery', 'Out for Delivery'), 'count' => $orders_by_status['out_for_delivery'] ?? 0],
                'delivered' => ['icon' => 'fa-check-circle', 'label' => LanguageHelper::t('delivered', 'Delivered'), 'count' => $orders_by_status['delivered'] ?? 0],
                'cancelled' => ['icon' => 'fa-times-circle', 'label' => LanguageHelper::t('cancelled', 'Cancelled'), 'count' => $orders_by_status['cancelled'] ?? 0]
            ];
            
            foreach ($filters as $status => $filter): ?>
                <div class="action-btn <?php echo $current_status === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>" data-aos="zoom-in" data-aos-delay="<?= array_search($status, array_keys($filters)) * 100 + 150 ?>">
                    <i class="fas <?php echo $filter['icon']; ?>"></i>
                    <span><?php echo $filter['label']; ?></span>
                    <?php if ($filter['count']): ?><span class="order-count"><?php echo $filter['count']; ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="orders-list">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $index => $order): ?>
                    <div class="order-card <?php echo strtolower($order['status']); ?>" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="<?= $index * 100 + 200 ?>">
                        <div class="order-header">
                            <div class="order-info">
                                <span class="order-id"><?= LanguageHelper::t('order_number', 'Order') ?> #<?php echo $order['id']; ?></span>
                                <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                            </div>
                            <span class="order-status <?php echo strtolower($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span>
                        </div>
                        
                        <div class="order-content">
                            <div class="order-details">
                                <p><?php echo $order['item_count']; ?> <?= LanguageHelper::t('items', 'items') ?> • <?= LanguageHelper::t('total', 'Total') ?>: Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                                <?php if ($order['delivery_date']): ?>
                                    <p class="delivery-date"><?= LanguageHelper::t('expected', 'Expected') ?>: <?php echo date('M j, Y', strtotime($order['delivery_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="order-actions">
                                <a href="<?php echo $pathConfig->url('account/orders?id=' . $order['id']); ?>" class="btn btn-sm btn-outline" data-aos="zoom-in" data-aos-delay="300"><i class="fas fa-eye"></i> <?= LanguageHelper::t('view', 'View') ?></a>
                                
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-warning edit-order-btn" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="350" hidden><i class="fas fa-edit"></i> <?= LanguageHelper::t('edit', 'Edit') ?></button>
                                    <button class="btn btn-sm btn-danger cancel-order-btn" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="400"><i class="fas fa-times"></i> <?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                                <?php elseif ($order['status'] === 'delivered'): ?>
                             <?php elseif (in_array($order['status'], ['confirmed', 'preparing', 'out_for_delivery'])): ?>
                                    <button class="btn btn-sm btn-info contact-support-btn" data-order-id="<?php echo $order['id']; ?>" data-aos="zoom-in" data-aos-delay="350"><i class="fas fa-headset"></i> <?= LanguageHelper::t('support', 'Support') ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($order['status'] === 'pending'): ?>
                            <div class="edit-order-form" style="display: none;" data-order-id="<?php echo $order['id']; ?>">
                                <div class="form-header">
                                    <h4><?= LanguageHelper::t('edit_order', 'Edit Order') ?> #<?php echo $order['id']; ?></h4>
                                    <p><?= LanguageHelper::t('modify_quantities', 'Modify quantities for pending orders') ?></p>
                                </div>
                                <div class="order-items-edit"></div>
                                <div class="form-actions">
                                    <button class="btn btn-success save-changes-btn" data-order-id="<?php echo $order['id']; ?>"><i class="fas fa-save"></i> <?= LanguageHelper::t('save', 'Save') ?></button>
                                    <button class="btn btn-outline cancel-edit-btn"><i class="fas fa-times"></i> <?= LanguageHelper::t('cancel', 'Cancel') ?></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" data-aos="zoom-in" data-aos-delay="200">
                    <div class="empty-icon"><i class="fas fa-shopping-bag"></i></div>
                    <h3><?= LanguageHelper::t('no_orders_found', 'No orders found') ?></h3>
                    <p><?= LanguageHelper::t('no_orders_filter', 'No orders match the selected filter criteria') ?></p>
                    <a href="<?php echo $pathConfig->url('products'); ?>" class="btn btn-primary" data-aos="zoom-in" data-aos-delay="300"><i class="fas fa-shopping-cart"></i> <?= LanguageHelper::t('start_shopping', 'Start Shopping') ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" data-aos="zoom-in">
            <div class="modal-header">
                <h5 class="modal-title"><?= LanguageHelper::t('cancel_order', 'Cancel Order') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?= LanguageHelper::t('confirm_cancel_order', 'Are you sure you want to cancel this order?') ?></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?= LanguageHelper::t('note', 'Note') ?>:</strong> <?= LanguageHelper::t('only_pending_cancellable', 'Only pending orders can be cancelled.') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= LanguageHelper::t('close', 'Close') ?></button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn"><?= LanguageHelper::t('cancel_order', 'Cancel Order') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="contactSupportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" data-aos="zoom-in">
            <div class="modal-header">
                <h5 class="modal-title"><?= LanguageHelper::t('contact_support', 'Contact Support') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?= LanguageHelper::t('contact_support_desc', 'Contact our support team for assistance with confirmed orders.') ?></p>
                <div class="support-options">
                    <div class="support-option" data-aos="fade-right" data-aos-delay="100"><i class="fas fa-phone"></i><div><strong><?= LanguageHelper::t('call_us', 'Call Us') ?></strong><p>+977-9800000001</p></div></div>
                    <div class="support-option" data-aos="fade-right" data-aos-delay="200"><i class="fas fa-envelope"></i><div><strong><?= LanguageHelper::t('email_us', 'Email Us') ?></strong><p>support@phooldelivery.example</p></div></div>
                    <div class="support-option" data-aos="fade-right" data-aos-delay="300"><i class="fas fa-comments"></i><div><strong><?= LanguageHelper::t('live_chat', 'Live Chat') ?></strong><p><?= LanguageHelper::t('available_24_7', 'Available 24/7') ?></p></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= LanguageHelper::t('close', 'Close') ?></button>
                <a href="<?php echo $pathConfig->url('account/support'); ?>" class="btn btn-primary"><?= LanguageHelper::t('support', 'Support') ?></a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile cards toggle functionality
    const viewAllBtn = document.querySelector('.view-all-btn');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function() {
            const orderSummary = document.querySelector('.order-summary');
            const isExpanded = orderSummary.classList.contains('expanded');
            
            if (isExpanded) {
                orderSummary.classList.remove('expanded');
                this.innerHTML = '<i class="fas fa-chevron-down"></i> <?= LanguageHelper::t('view_all', 'View All') ?>';
            } else {
                orderSummary.classList.add('expanded');
                this.innerHTML = '<i class="fas fa-chevron-up"></i> <?= LanguageHelper::t('view_less', 'View Less') ?>';
            }
        });
    }

    // Sticky cards scroll functionality
    const stickyCardsContainer = document.querySelector('.sticky-cards-container');
    const orderSummary = document.querySelector('.order-summary');
    
    if (stickyCardsContainer && orderSummary) {
        let isSticky = false;
        let originalTop = 0;
        
        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const containerTop = stickyCardsContainer.getBoundingClientRect().top + scrollTop;
            
            if (scrollTop > containerTop && !isSticky) {
                // Make cards sticky
                isSticky = true;
                originalTop = orderSummary.offsetTop;
                
                orderSummary.style.position = 'sticky';
                orderSummary.style.top = '80px';
                orderSummary.style.zIndex = '100';
                orderSummary.style.background = 'white';
                orderSummary.style.borderRadius = '12px';
                orderSummary.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                orderSummary.style.padding = '16px';
                orderSummary.style.marginBottom = '16px';
                orderSummary.style.transition = 'all 0.3s ease';
                
                // Add scroll animation class
                orderSummary.classList.add('sticky-active');
                
            } else if (scrollTop <= containerTop && isSticky) {
                // Remove sticky behavior
                isSticky = false;
                
                orderSummary.style.position = '';
                orderSummary.style.top = '';
                orderSummary.style.zIndex = '';
                orderSummary.style.background = '';
                orderSummary.style.borderRadius = '';
                orderSummary.style.boxShadow = '';
                orderSummary.style.padding = '';
                orderSummary.style.marginBottom = '';
                
                // Remove scroll animation class
                orderSummary.classList.remove('sticky-active');
            }
        }
        
        // Throttle scroll events for better performance
        let scrollTimeout;
        function throttledScroll() {
            if (!scrollTimeout) {
                scrollTimeout = setTimeout(function() {
                    scrollTimeout = null;
                    handleScroll();
                }, 10);
            }
        }
        
        window.addEventListener('scroll', throttledScroll);
        window.addEventListener('resize', throttledScroll);
        
        // Initial check
        handleScroll();
    }

    // Status filter
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            window.location.href = `<?php echo $pathConfig->url('account/orders'); ?>?status=${this.getAttribute('data-status')}`;
        });
    });

    // Edit order
    document.querySelectorAll('.edit-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const editForm = this.closest('.order-card').querySelector('.edit-order-form');
            editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
            if (editForm.style.display === 'block') loadOrderItems(orderId, editForm);
        });
    });

    // Cancel edit
    document.querySelectorAll('.cancel-edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.edit-order-form').style.display = 'none';
        });
    });

    // Cancel order
    let currentCancelOrderId = null;
    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentCancelOrderId = this.getAttribute('data-order-id');
            new bootstrap.Modal(document.getElementById('cancelOrderModal')).show();
        });
    });

    // Confirm cancel
    document.getElementById('confirmCancelBtn').addEventListener('click', function() {
        if (!currentCancelOrderId) return;
        fetch('<?php echo $pathConfig->url('account/orders'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=cancel_order&order_id=${currentCancelOrderId}`
        }).then(r => r.json()).then(data => {
            showNotification(data.success ? '<?= LanguageHelper::t('order_cancelled_success', 'Order cancelled successfully') ?>' : data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => window.location.reload(), 1500);
        }).catch(() => showNotification('<?= LanguageHelper::t('error_cancelling_order', 'Error cancelling order') ?>', 'error'));
    });

    // Contact support
    document.querySelectorAll('.contact-support-btn').forEach(btn => {
        btn.addEventListener('click', () => new bootstrap.Modal(document.getElementById('contactSupportModal')).show());
    });

    // FIXED: Save changes function with better error handling
    document.querySelectorAll('.save-changes-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const editForm = document.querySelector(`.edit-order-form[data-order-id="${orderId}"]`);
            const inputs = editForm.querySelectorAll('.quantity-input');
            
            // Validate all inputs first
            let isValid = true;
            const quantities = {};
            
            inputs.forEach(input => {
                const quantity = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                const min = parseInt(input.getAttribute('min'));
                
                if (isNaN(quantity) || quantity < min || quantity > max) {
                    isValid = false;
                    input.style.borderColor = 'var(--danger)';
                    showNotification(`Invalid quantity for ${input.closest('.order-item-edit').querySelector('.item-name').textContent}. Must be between ${min} and ${max}.`, 'error');
                } else {
                    input.style.borderColor = '';
                    quantities[input.getAttribute('data-item-id')] = quantity;
                }
            });
            
            if (!isValid) return;
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= LanguageHelper::t('saving', 'Saving...') ?>';
            
            // Update all items in the order
            const updatePromises = Object.keys(quantities).map(itemId => {
                return fetch('<?php echo $pathConfig->url('account/orders'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=update_quantity&order_id=${orderId}&item_id=${itemId}&quantity=${quantities[itemId]}`
                }).then(r => r.json());
            });
            
            Promise.all(updatePromises).then(results => {
                const success = results.every(r => r.success);
                const message = success ? 
                    '<?= LanguageHelper::t('order_updated_success', 'Order updated successfully') ?>' : 
                    (results[0]?.message || '<?= LanguageHelper::t('error_updating_order', 'Error updating order') ?>');
                
                showNotification(message, success ? 'success' : 'error');
                
                if (success) {
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    // Reset button state on failure
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save"></i> <?= LanguageHelper::t('save', 'Save') ?>';
                }
            }).catch((error) => {
                console.error('Update error:', error);
                showNotification('<?= LanguageHelper::t('error_updating_order', 'Error updating order') ?>', 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-save"></i> <?= LanguageHelper::t('save', 'Save') ?>';
            });
        });
    });

    // FIXED: Enhanced loadOrderItems function
    function loadOrderItems(orderId, editForm) {
        const container = editForm.querySelector('.order-items-edit');
        container.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> <?= LanguageHelper::t('loading', 'Loading...') ?></div>';
        
        fetch('<?php echo $pathConfig->url('account/orders'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_order_items&order_id=${orderId}`
        }).then(r => {
            if (!r.ok) {
                throw new Error('Network response was not ok');
            }
            return r.json();
        }).then(data => {
            if (data.success) {
                container.innerHTML = data.items.map(item => `
                    <div class="order-item-edit">
                        <div class="item-info">
                            <span class="item-name">${item.product_name}</span>
                            <span class="item-price">Rs. ${parseFloat(item.unit_price).toFixed(2)}</span>
                            <small class="stock-info"><?= LanguageHelper::t('available', 'Available') ?>: ${item.stock_quantity}</small>
                        </div>
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline quantity-decrease" data-item-id="${item.id}">-</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" 
                                   min="1" max="${Math.max(item.stock_quantity + item.quantity, item.quantity)}" 
                                   data-item-id="${item.id}">
                            <button class="btn btn-sm btn-outline quantity-increase" data-item-id="${item.id}">+</button>
                        </div>
                    </div>
                `).join('');
                
                setupQuantityControls(container);
            } else {
                container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${data.message}</div>`;
            }
        }).catch((error) => {
            console.error('Error loading items:', error);
            container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= LanguageHelper::t('error_loading_items', 'Error loading items') ?></div>';
        });
    }

    // FIXED: Enhanced quantity controls with better validation
    function setupQuantityControls(container) {
        container.querySelectorAll('.quantity-decrease').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = container.querySelector(`.quantity-input[data-item-id="${this.getAttribute('data-item-id')}"]`);
                const currentValue = parseInt(input.value);
                const min = parseInt(input.getAttribute('min'));
                
                if (currentValue > min) {
                    input.value = currentValue - 1;
                    validateQuantityInput(input);
                }
            });
        });
        
        container.querySelectorAll('.quantity-increase').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = container.querySelector(`.quantity-input[data-item-id="${this.getAttribute('data-item-id')}"]`);
                const currentValue = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                
                if (currentValue < max) {
                    input.value = currentValue + 1;
                    validateQuantityInput(input);
                } else {
                    showNotification('<?= LanguageHelper::t('max_quantity_reached', 'Maximum quantity reached for this product') ?>', 'warning');
                }
            });
        });
        
        // Add input validation
        container.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                validateQuantityInput(this);
            });
            
            input.addEventListener('input', function() {
                validateQuantityInput(this);
            });
        });
    }

    function validateQuantityInput(input) {
        const value = parseInt(input.value);
        const min = parseInt(input.getAttribute('min'));
        const max = parseInt(input.getAttribute('max'));
        
        if (isNaN(value) || value < min) {
            input.value = min;
            input.style.borderColor = 'var(--danger)';
        } else if (value > max) {
            input.value = max;
            input.style.borderColor = 'var(--warning)';
            showNotification('<?= LanguageHelper::t('quantity_adjusted_max', 'Quantity adjusted to maximum available stock') ?>', 'warning');
        } else {
            input.style.borderColor = '';
        }
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<div class="notification-content"><i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i><span>${message}</span></div>`;
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});
</script>

<style>
:root {
    --primary: #2563eb;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --gray: #64748b;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background-color: #f5f7fa;
    color: #334155;
}

.account-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.account-header {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.account-header h2 {
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
}

.order-actions-filter {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 16px 0;
    margin-bottom: 24px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.order-actions-filter::-webkit-scrollbar {
    display: none;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    min-width: fit-content;
    font-weight: 500;
}

.action-btn:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
}

.action-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.order-count {
    background: #94a3b8;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.action-btn.active .order-count {
    background: rgba(255,255,255,0.3);
}

.order-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.order-card.pending { border-left-color: var(--warning); }
.order-card.confirmed { border-left-color: var(--info); }
.order-card.preparing { border-left-color: var(--primary); }
.order-card.out_for_delivery { border-left-color: var(--info); }
.order-card.delivered { border-left-color: var(--success); }
.order-card.cancelled { border-left-color: var(--danger); }

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.order-info {
    flex: 1;
    min-width: 200px;
}

.order-id {
    font-weight: 700;
    color: var(--dark);
    font-size: 16px;
}

.order-date {
    color: var(--gray);
    font-size: 14px;
}

.order-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
}

.order-status.pending { background: #fef3c7; color: #92400e; }
.order-status.confirmed { background: #cffafe; color: #0e7490; }
.order-status.preparing { background: #dbeafe; color: #1e40af; }
.order-status.out_for_delivery { background: #e0e7ff; color: #3730a3; }
.order-status.delivered { background: #d1fae5; color: #065f46; }
.order-status.cancelled { background: #fee2e2; color: #991b1b; }

.order-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.order-details p {
    margin: 4px 0;
    color: var(--gray);
}

.delivery-date {
    color: var(--success) !important;
    font-weight: 600;
}

.order-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-outline {
    border: 1px solid #cbd5e1;
    color: var(--gray);
    background: white;
}

.btn-outline:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.btn-warning {
    background: var(--warning);
    color: white;
    border: none;
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
    border: none;
}

.btn-info {
    background: var(--info);
    color: white;
    border: none;
}

.btn-success {
    background: var(--success);
    color: white;
    border: none;
}

.edit-order-form {
    margin-top: 20px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
    display: none;
}

.order-item-edit {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.order-item-edit:last-child {
    border-bottom: none;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: 600;
    display: block;
}

.item-price {
    color: var(--gray);
    font-size: 14px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantity-input {
    width: 60px;
    text-align: center;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 4px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.empty-icon {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 24px;
}

/* Order Details Page Styles */
.order-details-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: relative;
}

/* Sticky Cards Container */
.sticky-cards-container {
    position: relative;
    margin-bottom: 24px;
}

.order-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}

.order-summary.sticky-active {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.summary-card {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.3s ease;
}

.order-summary.sticky-active .summary-card {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.summary-card h4 {
    font-size: 14px;
    color: var(--gray);
    margin-bottom: 8px;
    text-transform: uppercase;
    font-weight: 600;
}

.summary-card p {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

/* View All Button for Mobile */
.view-all-container {
    display: none;
    text-align: center;
    margin: 16px 0;
}

.view-all-btn {
    width: 100%;
    justify-content: center;
}

.tracking-timeline {
    margin: 32px 0;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 24px;
    position: relative;
}

.timeline-item:not(:last-child):after {
    content: '';
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: -24px;
    width: 2px;
    background: #e2e8f0;
}

.timeline-item.current .timeline-content h5 {
    color: var(--primary);
    font-weight: 700;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    z-index: 1;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.timeline-icon.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
}

.timeline-icon.inactive {
    background: #f1f5f9;
    color: #94a3b8;
}

.timeline-icon.cancelled {
    background: var(--danger);
    color: white;
}

.timeline-content {
    flex: 1;
}

.timeline-content h5 {
    margin: 0 0 4px 0;
    font-weight: 600;
    transition: all 0.3s ease;
}

.timeline-content p {
    margin: 0;
    color: var(--gray);
    font-size: 14px;
}

.timeline-content small {
    color: var(--success);
    font-weight: 500;
}

.timeline-content .text-warning {
    color: var(--warning) !important;
}

.order-items {
    margin: 24px 0;
}

.order-item {
    display: flex;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid #f1f5f9;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    margin-right: 16px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    overflow: hidden;
    flex-shrink: 0;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-image .no-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 12px;
}

.item-image .no-image i {
    font-size: 20px;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    margin-bottom: 4px;
}

.item-meta {
    color: var(--gray);
    font-size: 14px;
}

.item-price {
    font-weight: 600;
    color: var(--dark);
}

.order-total {
    text-align: right;
    padding-top: 16px;
    border-top: 2px solid #e2e8f0;
    margin-top: 16px;
}

.order-total h4 {
    color: var(--dark);
    margin: 0;
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    padding: 16px 20px;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    z-index: 10000;
    max-width: 400px;
    border-left: 4px solid;
}

.notification.show {
    transform: translateX(0);
}

.notification.success {
    border-left-color: var(--success);
}

.notification.error {
    border-left-color: var(--danger);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .account-container {
        padding: 16px;
    }
    
    .account-header {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    /* Mobile-specific order summary styles */
    .order-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    /* Sticky behavior for mobile */
    .order-summary.sticky-active {
        top: 60px !important; /* Lower top position for mobile */
        padding: 12px !important;
    }
    
    .order-summary.sticky-active .summary-card {
        padding: 12px;
        font-size: 14px;
    }
    
    .order-summary.sticky-active .summary-card h4 {
        font-size: 12px;
    }
    
    .order-summary.sticky-active .summary-card p {
        font-size: 14px;
    }
    
    /* Hide cards beyond the first two by default on mobile */
    .order-summary .summary-card:nth-child(n+3) {
        display: none;
    }
    
    /* Show all cards when expanded */
    .order-summary.expanded .summary-card {
        display: block;
    }
    
    /* Show the view all button only on mobile */
    .mobile-only {
        display: block;
    }
    
    .order-details-container {
        padding: 20px;
    }
    
    .btn {
        flex: 1;
        min-width: 120px;
        justify-content: center;
    }
    
    .timeline-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timeline-icon {
        margin-bottom: 8px;
    }
}

@media (min-width: 769px) {
    /* Hide the view all button on desktop */
    .mobile-only {
        display: none;
    }
    
    /* Show all cards by default on desktop */
    .order-summary .summary-card {
        display: block;
    }
    
    /* Enhanced sticky behavior for desktop */
    .order-summary.sticky-active {
        top: 80px !important;
        max-width: calc(100% - 48px);
        margin-left: auto;
        margin-right: auto;
    }
}

@media (max-width: 480px) {
    .action-btn {
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .order-card {
        padding: 16px;
    }
    
    .btn {
        font-size: 13px;
        padding: 8px 12px;
    }
    
    .order-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .item-image {
        margin-bottom: 12px;
    }
    
    /* Single column layout for very small screens */
    @media (max-width: 360px) {
        .order-summary {
            grid-template-columns: 1fr;
        }
        
        .order-summary.sticky-active {
            grid-template-columns: 1fr;
        }
    }
}
</style>