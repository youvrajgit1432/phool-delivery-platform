<?php
// Helper functions for applying discounts and offers

/**
 * Get active event discount for a given date
 */
function getActiveEventDiscount($pdo, $date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM special_events 
            WHERE is_active = 1 
            AND start_date <= ? 
            AND end_date >= ?
            LIMIT 1
        ");
        $stmt->execute([$date, $date]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching active event: " . $e->getMessage());
        return null;
    }
}

/**
 * Get applicable offers for a product in a specific city
 */
function getApplicableOffers($pdo, $product_id, $city_id, $order_amount = 0) {
    $current_date = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM product_offers 
            WHERE product_id = ? 
            AND is_active = 1
            AND (city_id IS NULL OR city_id = ?)
            AND (start_date IS NULL OR start_date <= ?)
            AND (end_date IS NULL OR end_date >= ?)
            AND (min_order_amount = 0 OR min_order_amount <= ?)
            ORDER BY 
                CASE WHEN city_id IS NOT NULL THEN 0 ELSE 1 END, -- Prefer city-specific offers
                min_order_amount DESC -- Prefer offers with higher minimum order requirements
        ");
        $stmt->execute([$product_id, $city_id, $current_date, $current_date, $order_amount]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching applicable offers: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate discount amount for an order
 */
function calculateOrderDiscount($pdo, $order_items, $city_id, $event_id = null) {
    $total_discount = 0;
    $applied_offers = [];
    
    // Get event discount if applicable
    $event_discount = 0;
    if ($event_id) {
        $stmt = $pdo->prepare("SELECT discount_percentage FROM special_events WHERE id = ? AND is_active = 1");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        if ($event) {
            $event_discount = $event['discount_percentage'];
        }
    }
    
    foreach ($order_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $unit_price = $item['unit_price'];
        
        // Get applicable offers for this product
        $offers = getApplicableOffers($pdo, $product_id, $city_id, $unit_price * $quantity);
        
        foreach ($offers as $offer) {
            $discount_amount = 0;
            $free_quantity = 0;
            
            switch ($offer['offer_type']) {
                case 'buy_x_get_y':
                    // Calculate how many times the offer applies
                    $offer_multiplier = floor($quantity / $offer['buy_quantity']);
                    $free_quantity = $offer_multiplier * $offer['get_quantity'];
                    $discount_amount = $free_quantity * $unit_price;
                    break;
                    
                case 'percentage_discount':
                    $discount_amount = ($unit_price * $quantity * $offer['discount_percentage']) / 100;
                    break;
                    
                case 'fixed_discount':
                    $discount_amount = $offer['fixed_discount'] * $quantity;
                    break;
            }
            
            if ($discount_amount > 0) {
                $total_discount += $discount_amount;
                $applied_offers[] = [
                    'offer_id' => $offer['id'],
                    'product_id' => $product_id,
                    'discount_amount' => $discount_amount,
                    'free_quantity' => $free_quantity
                ];
            }
        }
        
        // Apply event discount if applicable
        if ($event_discount > 0) {
            $event_discount_amount = ($unit_price * $quantity * $event_discount) / 100;
            $total_discount += $event_discount_amount;
        }
    }
    
    return [
        'total_discount' => $total_discount,
        'applied_offers' => $applied_offers,
        'event_discount_percentage' => $event_discount
    ];
}

/**
 * Apply discounts to an order during checkout
 */
function applyOrderDiscounts($pdo, $order_id, $city_id, $event_id = null) {
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.price as unit_price 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Calculate discounts
    $discount_info = calculateOrderDiscount($pdo, $order_items, $city_id, $event_id);
    
    // Update order with discount information
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET applied_discount = ?, final_amount = total_amount - ?, event_id = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $discount_info['total_discount'],
        $discount_info['total_discount'],
        $event_id,
        $order_id
    ]);
    
    // Update order items with applied offers
    foreach ($discount_info['applied_offers'] as $applied_offer) {
        $stmt = $pdo->prepare("
            UPDATE order_items 
            SET applied_offer_id = ?, free_quantity = ?
            WHERE order_id = ? AND product_id = ?
        ");
        $stmt->execute([
            $applied_offer['offer_id'],
            $applied_offer['free_quantity'],
            $order_id,
            $applied_offer['product_id']
        ]);
    }
    
    return $discount_info;
}
?>