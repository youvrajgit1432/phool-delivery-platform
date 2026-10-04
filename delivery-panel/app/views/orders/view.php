<?php require_once dirname(__FILE__, 3) . '/helpers/url.php'; ?>
<?php require_once dirname(__FILE__) . '/../layouts/header.php'; ?>

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box"></i> Order Details</h2>
        <a href="<?php echo app_url('/orders/assigned'); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>

    <?php if ($order): ?>
    <div class="row">
        <!-- Order Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order #<?php echo htmlspecialchars($order->order_number ?? 'N/A'); ?></h5>
                    <?php 
                        $status = $order->delivery_status ?? 'unknown';
                        $badgeClass = 'secondary';
                        $statusLabel = ucfirst($status);
                        
                        if ($status === 'assigned') {
                            $badgeClass = 'warning';
                            $statusLabel = 'Pending';
                        } elseif ($status === 'accepted') {
                            $badgeClass = 'success';
                            $statusLabel = 'Accepted';
                        } elseif ($status === 'picked_up') {
                            $badgeClass = 'info';
                            $statusLabel = 'Picked Up';
                        } elseif ($status === 'on_the_way') {
                            $badgeClass = 'primary';
                            $statusLabel = 'On the Way';
                        } elseif ($status === 'arrived') {
                            $badgeClass = 'warning';
                            $statusLabel = 'Arrived';
                        } elseif ($status === 'delivered') {
                            $badgeClass = 'success';
                            $statusLabel = 'Delivered';
                        } elseif ($status === 'cancelled') {
                            $badgeClass = 'danger';
                            $statusLabel = 'Rejected';
                        } elseif ($status === 'failed') {
                            $badgeClass = 'danger';
                            $statusLabel = 'Failed';
                        }
                    ?>
                    <span class="badge bg-<?php echo $badgeClass; ?> fs-6"><?php echo $statusLabel; ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Order Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">Order Number:</td>
                                    <td>#<?php echo htmlspecialchars($order->order_number ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Order Date:</td>
                                    <td><?php echo isset($order->created_at) ? date('M d, Y H:i', strtotime($order->created_at)) : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Total Amount:</td>
                                    <td><strong>NPR <?php echo isset($order->total_amount) ? number_format($order->total_amount, 2) : '0.00'; ?></strong></td>
                                </tr>
                                <?php if (isset($order->cod_amount) && floatval($order->cod_amount) > 0): ?>
                                <tr>
                                    <td class="fw-bold">COD Amount:</td>
                                    <td>NPR <?php echo number_format($order->cod_amount, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Payment Status:</td>
                                    <td>
                                        <?php
                                            $paymentStatus = $order->payment_status ?? 'pending';
                                            $paymentBadge = $paymentStatus === 'collected' ? 'success' : 'warning';
                                        ?>
                                        <span class="badge bg-<?php echo $paymentBadge; ?>"><?php echo ucfirst($paymentStatus); ?></span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Timeline</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">Assigned At:</td>
                                    <td><?php echo isset($order->assigned_at) ? date('M d, Y H:i', strtotime($order->assigned_at)) : 'Pending'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Accepted At:</td>
                                    <td><?php echo isset($order->accepted_at) ? date('M d, Y H:i', strtotime($order->accepted_at)) : 'Pending'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Picked Up At:</td>
                                    <td><?php echo isset($order->picked_up_at) ? date('M d, Y H:i', strtotime($order->picked_up_at)) : 'Pending'; ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Delivered At:</td>
                                    <td><?php echo isset($order->delivered_at) ? date('M d, Y H:i', strtotime($order->delivered_at)) : 'Pending'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Customer Name:</strong><br><?php echo htmlspecialchars($order->customer_name ?? 'N/A'); ?></p>
                            <p><strong>Phone Number:</strong><br><?php echo htmlspecialchars($order->customer_phone ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong><br><?php echo htmlspecialchars($order->customer_email ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Delivery Address:</strong><br><?php echo htmlspecialchars($order->delivery_address ?? 'N/A'); ?></p>
                            <p><strong>City:</strong><br><?php echo htmlspecialchars($order->delivery_city ?? 'N/A'); ?></p>
                            <p><strong>Special Instructions:</strong><br><?php echo htmlspecialchars($order->delivery_notes ?? 'None'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    // Parse order items if they exist in a serialized format
                                    $items = [];
                                    if (isset($order->items)) {
                                        $items = is_array($order->items) ? $order->items : json_decode($order->items, true) ?? [];
                                    }
                                    
                                    if (!empty($items)):
                                        foreach ($items as $item):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name'] ?? 'Unknown Item'); ?></td>
                                    <td class="text-center"><?php echo intval($item['quantity'] ?? 1); ?></td>
                                    <td class="text-right">NPR <?php echo number_format(floatval($item['price'] ?? 0), 2); ?></td>
                                    <td class="text-right"><strong>NPR <?php echo number_format((floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 1)), 2); ?></strong></td>
                                </tr>
                                <?php 
                                        endforeach;
                                    else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Item details not available</td>
                                </tr>
                                <?php 
                                    endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>NPR <?php echo isset($order->subtotal) ? number_format($order->subtotal, 2) : '0.00'; ?></span>
                    </div>
                    <?php if (isset($order->delivery_fee) && floatval($order->delivery_fee) > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Delivery Fee:</span>
                        <span>NPR <?php echo number_format($order->delivery_fee, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($order->discount) && floatval($order->discount) > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Discount:</span>
                        <span>-NPR <?php echo number_format($order->discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total Amount:</strong>
                        <strong>NPR <?php echo isset($order->total_amount) ? number_format($order->total_amount, 2) : '0.00'; ?></strong>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php 
                            $status = $order->delivery_status ?? 'unknown';
                            if ($status === 'assigned'):
                        ?>
                        <button type="button" class="btn btn-success btn-sm accept-order-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>">
                            <i class="fas fa-check"></i> Accept Order
                        </button>
                        <button type="button" class="btn btn-danger btn-sm reject-order-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>">
                            <i class="fas fa-times"></i> Reject Order
                        </button>
                        <?php elseif ($status === 'accepted'): ?>
                        <button type="button" class="btn btn-primary btn-sm pickup-order-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>">
                            <i class="fas fa-box"></i> Mark as Picked Up
                        </button>
                        <?php elseif ($status === 'picked_up'): ?>
                        <button type="button" class="btn btn-info btn-sm on-the-way-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>">
                            <i class="fas fa-road"></i> Start Delivery
                        </button>
                        <?php elseif ($status === 'on_the_way' || $status === 'arrived'): ?>
                            <?php 
                                $cod_amount = isset($order->cod_amount) ? floatval($order->cod_amount) : 0;
                                $payment_status = $order->payment_status ?? 'pending';
                                $has_pending_payment = ($cod_amount > 0 && $payment_status === 'pending');
                            ?>
                            <?php if ($has_pending_payment): ?>
                            <button type="button" class="btn btn-warning btn-sm collect-payment-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>" data-cod="<?php echo htmlspecialchars($order->cod_amount); ?>">
                                <i class="fas fa-money-bill"></i> Collect Payment (NPR <?php echo number_format($cod_amount, 2); ?>)
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-success btn-sm deliver-order-btn" data-order-id="<?php echo intval($order->order_id); ?>" data-order-number="<?php echo htmlspecialchars($order->order_number); ?>">
                                <i class="fas fa-check-circle"></i> Mark as Delivered
                            </button>
                            <?php endif; ?>
                        <?php elseif ($status === 'delivered'): ?>
                        <div style="padding: 12px 16px; background: #d4edda; border-left: 4px solid #1AAB8A; border-radius: 4px; color: #0d5a42; font-weight: 500;">
                            <i class="fas fa-check-circle"></i> Order Delivered
                        </div>
                        <?php elseif ($status === 'cancelled' || $status === 'failed'): ?>
                        <div style="padding: 12px 16px; background: #f8d7da; border-left: 4px solid #EE5A6F; border-radius: 4px; color: #8b1a2b; font-weight: 500;">
                            <i class="fas fa-times-circle"></i> Order <?php echo $status === 'cancelled' ? 'Rejected' : 'Failed'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div style="padding: 16px; background: #f8d7da; border-left: 4px solid #EE5A6F; border-radius: 4px; color: #8b1a2b; font-weight: 500;">
        <i class="fas fa-exclamation-circle"></i> Order not found
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__FILE__) . '/../layouts/footer.php'; ?>
