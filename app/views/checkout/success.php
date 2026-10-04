<?php
// app/views/checkout/success.php
$page_title = "Order Success - Phool Delivery";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$assets_path = $base_url . '/phool-delivery-platform/public_html/assets';
?>
<style>
/* Order Success Page Styles */
.order-success {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.order-success .container {
    max-width: 800px;
    width: 100%;
}

.success-card {
    background: white;
    border-radius: 20px;
    padding: 3rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.success-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #4CAF50, #8BC34A);
}

.success-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #E8F5E9;
    border-radius: 50%;
    color: #4CAF50;
    border: 3px solid #4CAF50;
    animation: scaleUp 0.5s ease-in-out;
}

.success-icon svg {
    width: 50px;
    height: 50px;
}

@keyframes scaleUp {
    0% { transform: scale(0); opacity: 0; }
    70% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

.order-success h1 {
    color: #2E7D32;
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 700;
}

.order-success > p {
    color: #616161;
    font-size: 1.1rem;
    margin-bottom: 2.5rem;
    line-height: 1.6;
}

.order-details {
    background: #F1F8E9;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2.5rem;
    text-align: left;
    border-left: 4px solid #4CAF50;
}

.order-details h3 {
    color: #33691E;
    margin-bottom: 1.5rem;
    font-size: 1.3rem;
    position: relative;
    display: inline-block;
}

.order-details h3::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 40px;
    height: 3px;
    background: #4CAF50;
    border-radius: 3px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.8rem 0;
    border-bottom: 1px solid #E8F5E9;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row span {
    color: #616161;
}

.detail-row strong {
    color: #2E7D32;
}

.success-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.btn {
    padding: 1rem 2rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
    text-align: center;
    min-width: 180px;
}

.btn-primary {
    background: #4CAF50;
    color: white;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.btn-primary:hover {
    background: #388E3C;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
}

.btn-outline {
    background: transparent;
    color: #4CAF50;
    border: 2px solid #4CAF50;
}

.btn-outline:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
}

.support-info {
    color: #757575;
    font-size: 0.9rem;
}

.support-info a {
    color: #4CAF50;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

.support-info a:hover {
    color: #2E7D32;
    text-decoration: underline;
}

/* Responsive design */
@media (max-width: 768px) {
    .success-card {
        padding: 2rem 1.5rem;
    }
    
    .order-success h1 {
        font-size: 2rem;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 0.3rem;
    }
    
    .success-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 300px;
    }
}

/* Confetti animation for celebration */
@keyframes confettiFall {
    0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
    100% { transform: translateY(500px) rotate(360deg); opacity: 0; }
}

.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #ff0;
    opacity: 0;
}

.confetti:nth-child(1) {
    left: 10%;
    background: #FFC107;
    animation: confettiFall 3s ease-in-out 0.5s forwards;
}

.confetti:nth-child(2) {
    left: 20%;
    background: #4CAF50;
    animation: confettiFall 3s ease-in-out 0.2s forwards;
}

.confetti:nth-child(3) {
    left: 30%;
    background: #F44336;
    animation: confettiFall 3s ease-in-out 0.9s forwards;
}

.confetti:nth-child(4) {
    left: 40%;
    background: #2196F3;
    animation: confettiFall 3s ease-in-out 0.4s forwards;
}

.confetti:nth-child(5) {
    left: 50%;
    background: #9C27B0;
    animation: confettiFall 3s ease-in-out 0.7s forwards;
}

.confetti:nth-child(6) {
    left: 60%;
    background: #FF9800;
    animation: confettiFall 3s ease-in-out 0.3s forwards;
}

.confetti:nth-child(7) {
    left: 70%;
    background: #795548;
    animation: confettiFall 3s ease-in-out 0.6s forwards;
}

.confetti:nth-child(8) {
    left: 80%;
    background: #607D8B;
    animation: confettiFall 3s ease-in-out 0.1s forwards;
}

.confetti:nth-child(9) {
    left: 90%;
    background: #E91E63;
    animation: confettiFall 3s ease-in-out 0.8s forwards;
}
</style>

<!-- Add confetti elements for celebration -->
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>
<div class="confetti"></div>

<section class="order-success">
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
            </div>
            
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for your order. Your order has been received and is being processed.</p>
            
            <div class="order-details">
                <h3>Order Details</h3>
                <div class="detail-row">
                    <span>Order Number:</span>
                    <strong>#<?php echo $order_id; ?></strong>
                </div>
                
                <?php if (isset($order) && is_array($order)): ?>
                <div class="detail-row">
                    <span>Order Date:</span>
                    <strong><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></strong>
                </div>
                
                <div class="detail-row">
                    <span>Total Amount:</span>
                    <strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
                
                <div class="detail-row">
                    <span>Payment Method:</span>
                    <strong>
                        <?php 
                        $payment_methods = [
                            'cod' => 'Cash on Delivery',
                            'esewa' => 'eSewa',
                            'khalti' => 'Khalti',
                            'bank_transfer' => 'Bank Transfer'
                        ];
                        echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                        ?>
                    </strong>
                </div>
                
                <div class="detail-row">
                    <span>Delivery Address:</span>
                    <strong>
                        <?php 
                        // Check if we have address data in the order array
                        if (isset($order['address']) && !empty($order['address'])) {
                            echo htmlspecialchars($order['address']);
                            if (isset($order['city']) && !empty($order['city'])) {
                                echo ', ' . htmlspecialchars($order['city']);
                            }
                            if (isset($order['street']) && !empty($order['street'])) {
                                echo ', ' . htmlspecialchars($order['street']);
                            }
                        } 
                        // Fallback for customer address data (if available)
                        else if (isset($customer) && is_array($customer)) {
                            if (isset($customer['address']) && !empty($customer['address'])) {
                                echo htmlspecialchars($customer['address']);
                            }
                            if (isset($customer['city']) && !empty($customer['city'])) {
                                echo ', ' . htmlspecialchars($customer['city']);
                            }
                            if (isset($customer['street']) && !empty($customer['street'])) {
                                echo ', ' . htmlspecialchars($customer['street']);
                            }
                        } 
                        // Final fallback if no address data is available
                        else {
                            echo "Address information not available";
                        }
                        ?>
                    </strong>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="success-actions">
                <a href="/phool-delivery-platform/public_html/account/orders<?php echo isset($order_id) ? '?id=' . $order_id : ''; ?>" class="btn btn-primary">View Order Details</a>
                <a href="/phool-delivery-platform/public_html/products" class="btn btn-outline">Continue Shopping</a>
            </div>
            
            <div class="support-info">
                <p>Need help? <a href="/phool-delivery-platform/public_html/contact">Contact our support team</a></p>
            </div>
        </div>
    </div>
</section>