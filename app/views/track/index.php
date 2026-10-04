<?php
$page_title = "Track Order - Phool Delivery";
?>

<section class="track-order">
    <div class="container">
        <h1>Track Your Order</h1>
        
        <div class="track-container">
            <div class="track-form">
                <form id="trackForm">
                    <div class="form-group">
                        <label for="order_number">Order Number</label>
                        <input type="text" id="order_number" name="order_number" class="form-input" 
                               placeholder="Enter your order number (e.g., ORD-123)" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter the email used for ordering">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Track Order</button>
                </form>
            </div>
            
            <div class="track-results" id="trackResults" style="display: none;">
                <div class="tracking-card">
                    <h3>Order Status</h3>
                    <div class="tracking-info">
                        <div class="tracking-number">
                            <span>Order #:</span>
                            <strong id="resultOrderNumber"></strong>
                        </div>
                        
                        <div class="tracking-status">
                            <span>Status:</span>
                            <span class="status-badge" id="resultStatus"></span>
                        </div>
                        
                        <div class="tracking-date">
                            <span>Order Date:</span>
                            <span id="resultOrderDate"></span>
                        </div>
                    </div>
                    
                    <div class="tracking-timeline">
                        <div class="timeline-item completed">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-title">Order Placed</span>
                                <span class="timeline-date" id="timelinePlaced"></span>
                            </div>
                        </div>
                        
                        <div class="timeline-item" id="timelineConfirmed">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-title">Order Confirmed</span>
                                <span class="timeline-date"></span>
                            </div>
                        </div>
                        
                        <div class="timeline-item" id="timelinePreparing">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-title">Preparing Order</span>
                                <span class="timeline-date"></span>
                            </div>
                        </div>
                        
                        <div class="timeline-item" id="timelineOutForDelivery">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-title">Out for Delivery</span>
                                <span class="timeline-date"></span>
                            </div>
                        </div>
                        
                        <div class="timeline-item" id="timelineDelivered">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-title">Delivered</span>
                                <span class="timeline-date"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trackForm = document.getElementById('trackForm');
    const trackResults = document.getElementById('trackResults');
    
    trackForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const orderNumber = document.getElementById('order_number').value.trim();
        const email = document.getElementById('email').value.trim();
        
        // Simulate API call (in real app, this would fetch from server)
        simulateTrackOrder(orderNumber, email);
    });
    
    function simulateTrackOrder(orderNumber, email) {
        // This is a simulation - in real app, you'd fetch from your API
        const orderData = {
            order_number: orderNumber,
            status: 'confirmed',
            order_date: '2025-01-15 14:30:00',
            confirmed_date: '2025-01-15 15:00:00',
            preparing_date: '2025-01-15 16:30:00',
            estimated_delivery: '2025-01-16 12:00:00'
        };
        
        // Update UI with order data
        document.getElementById('resultOrderNumber').textContent = orderData.order_number;
        document.getElementById('resultStatus').textContent = orderData.status;
        document.getElementById('resultOrderDate').textContent = formatDate(orderData.order_date);
        document.getElementById('timelinePlaced').textContent = formatDate(orderData.order_date);
        
        // Update timeline based on status
        updateTimeline(orderData);
        
        // Show results
        trackResults.style.display = 'block';
    }
    
    function updateTimeline(orderData) {
        const statusOrder = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
        const currentStatusIndex = statusOrder.indexOf(orderData.status);
        
        statusOrder.forEach((status, index) => {
            const timelineItem = document.getElementById(`timeline${status.charAt(0).toUpperCase() + status.slice(1)}`);
            if (timelineItem) {
                if (index <= currentStatusIndex) {
                    timelineItem.classList.add('completed');
                } else {
                    timelineItem.classList.remove('completed');
                }
            }
        });
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
});
</script>