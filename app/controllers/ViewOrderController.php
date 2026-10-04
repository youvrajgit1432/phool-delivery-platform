<?php
// app/controllers/ViewOrderController.php
class ViewOrderController {
    private $db;
    private $viewOrderModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->viewOrderModel = new ViewOrderModel($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    // Orders management
    public function orders() {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: ' . $this->pathConfig->url('login'));
            exit;
        }
        
        $user_id = $_SESSION['customer_id'];
        $order_id = $_GET['id'] ?? null;
        $status = $_GET['status'] ?? 'all'; // Default to all
        
        // Handle AJAX actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleOrderActions($user_id);
            exit;
        }
        
        if ($order_id) {
            // Single order view
            try {
                $order = $this->viewOrderModel->getOrderDetails($user_id, $order_id);
                
                if (!$order) {
                    // Order not found or doesn't belong to user
                    return [
                        'error' => 'Order not found',
                        'page_title' => 'Order Not Found - Phool Delivery'
                    ];
                }
                
                $data = [
                    'order' => $order,
                    'page_title' => 'Order Details - Phool Delivery'
                ];
            } catch (Exception $e) {
                error_log("Error loading order details: " . $e->getMessage());
                return [
                    'error' => 'Unable to load order details. Please try again later.',
                    'page_title' => 'Error - Phool Delivery'
                ];
            }
        } else {
            // Orders list with filtering
            try {
                $orders = $this->viewOrderModel->getUserOrders($user_id, $status);
                $orders_by_status = $this->viewOrderModel->getOrdersCountByStatus($user_id);
                $recent_orders = $this->viewOrderModel->getRecentOrders($user_id, 10);
                
                $data = [
                    'orders' => $orders,
                    'orders_by_status' => $orders_by_status,
                    'recent_orders' => $recent_orders,
                    'current_status' => $status,
                    'page_title' => 'My Orders - Phool Delivery'
                ];
            } catch (Exception $e) {
                error_log("Error loading orders: " . $e->getMessage());
                $data = [
                    'orders' => [],
                    'orders_by_status' => [],
                    'recent_orders' => [],
                    'current_status' => $status,
                    'error' => 'Unable to load orders. Please try again later.',
                    'page_title' => 'My Orders - Phool Delivery'
                ];
            }
        }
        
        return $data;
    }
    
    private function handleOrderActions($user_id) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $order_id = $_POST['order_id'] ?? null;
        $item_id = $_POST['item_id'] ?? null;
        $quantity = $_POST['quantity'] ?? null;
        
        try {
            switch ($action) {
                case 'update_quantity':
                    if (!$order_id || !$item_id || !$quantity) {
                        throw new Exception("Missing required parameters");
                    }
                    
                    // Validate quantity
                    if (!is_numeric($quantity) || $quantity < 1) {
                        throw new Exception("Invalid quantity");
                    }
                    
                    $success = $this->viewOrderModel->updateOrderQuantity($order_id, $user_id, $item_id, $quantity);
                    
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Quantity updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Unable to update quantity. Order may not be pending.']);
                    }
                    break;
                    
                case 'cancel_order':
                    if (!$order_id) {
                        throw new Exception("Order ID is required");
                    }
                    
                    // Validate order ID format
                    if (!is_numeric($order_id)) {
                        throw new Exception("Invalid order ID");
                    }
                    
                    $success = $this->viewOrderModel->cancelOrder($order_id, $user_id);
                    
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Unable to cancel order. Order may not be pending.']);
                    }
                    break;
                    
                case 'get_order_items':
                    if (!$order_id) {
                        throw new Exception("Order ID is required");
                    }
                    
                    // Validate order ID format
                    if (!is_numeric($order_id)) {
                        throw new Exception("Invalid order ID");
                    }
                    
                    $items = $this->viewOrderModel->getOrderItemsForEdit($order_id, $user_id);
                    
                    if ($items === false) {
                        echo json_encode(['success' => false, 'message' => 'Unable to load order items. Order may not be pending.']);
                    } else {
                        echo json_encode(['success' => true, 'items' => $items]);
                    }
                    break;
                    
                default:
                    throw new Exception("Invalid action");
            }
        } catch (Exception $e) {
            error_log("Order action error: " . $e->getMessage());
            // Generic error message for client - don't expose sensitive details
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
    }
}
?>