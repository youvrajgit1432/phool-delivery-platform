<?php
namespace Phool\DeliveryPanel\Controllers;

use Phool\DeliveryPanel\Models\RiderOrder;

// Ensure models are properly loaded with their dependencies
require_once dirname(__DIR__) . '/models/BaseModel.php';
require_once dirname(__DIR__) . '/models/RiderOrder.php';

class OrderController {
    /**
     * Show main orders index page with navigation and statistics
     */
    public function index() {
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $orderModel = new RiderOrder();
        
        // Fetch pending (assigned) orders
        $pendingOrders = $orderModel->getOrdersWithDetails($riderId, ['status' => 'assigned']);
        
        // Fetch active/in-progress orders
        $activeOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['accepted', 'picked_up', 'on_the_way', 'arrived']
        ]);
        
        // Fetch completed orders
        $completedOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['delivered']
        ]);
        
        // Fetch failed orders
        $failedOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['failed', 'cancelled']
        ]);
        
        // Calculate statistics
        $stats = [
            'pending' => count($pendingOrders),
            'active' => count($activeOrders),
            'completed' => count($completedOrders),
            'failed' => count($failedOrders),
        ];
        
        $data = [
            'pending_orders' => $pendingOrders,
            'active_orders' => $activeOrders,
            'completed_orders' => $completedOrders,
            'failed_orders' => $failedOrders,
            'stats' => $stats,
        ];
        extract($data);
        require_once __DIR__ . '/../views/orders/index.php';
    }

    public function assigned() {
        // Main dashboard - Show all orders with filters
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $orderModel = new RiderOrder();
        
        // Get filter from GET parameter - default to 'active'
        $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'active';
        
        // Fetch all order types for statistics
        $pendingOrders = $orderModel->getOrdersWithDetails($riderId, ['status' => 'assigned']);
        $activeOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['accepted', 'picked_up', 'on_the_way', 'arrived']
        ]);
        $completedOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['delivered']
        ]);
        $failedOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['failed', 'cancelled']
        ]);
        
        // Calculate statistics
        $stats = [
            'pending' => count($pendingOrders),
            'active' => count($activeOrders),
            'completed' => count($completedOrders),
            'failed' => count($failedOrders),
        ];
        
        // Fetch filtered orders based on filter parameter
        $filteredOrders = [];
        
        if ($filter === 'all') {
            // Get all orders for this rider
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, [
                'statuses' => ['assigned', 'accepted', 'picked_up', 'on_the_way', 'arrived', 'delivered', 'failed', 'cancelled']
            ]);
        } elseif ($filter === 'accepted') {
            // Get accepted orders
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, [
                'statuses' => ['accepted']
            ]);
        } elseif ($filter === 'rejected') {
            // Get rejected/cancelled orders
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, [
                'statuses' => ['cancelled']
            ]);
        } elseif ($filter === 'active') {
            // Get active orders - all except completed/delivered
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, [
                'statuses' => ['assigned', 'accepted', 'picked_up', 'on_the_way', 'arrived', 'failed', 'cancelled']
            ]);
        } elseif ($filter === 'completed') {
            // Get completed orders
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, [
                'statuses' => ['delivered']
            ]);
        } else {
            // Default: Get assigned (pending) orders
            $filteredOrders = $orderModel->getOrdersWithDetails($riderId, ['status' => 'assigned']);
        }

        $data = [
            'pending_orders' => $pendingOrders,
            'active_orders' => $activeOrders,
            'completed_orders' => $completedOrders,
            'failed_orders' => $failedOrders,
            'stats' => $stats,
            'assigned_orders' => $filteredOrders,
            'current_filter' => $filter,
        ];
        extract($data);
        require_once __DIR__ . '/../views/orders/assigned.php';
    }

    public function active() {
        // List active deliveries - orders in progress
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $orderModel = new RiderOrder();
        
        // Fetch orders in progress with complete details
        $activeOrders = $orderModel->getOrdersWithDetails($riderId, [
            'statuses' => ['accepted', 'picked_up', 'on_the_way', 'arrived']
        ]);

        $data = [
            'active_orders' => $activeOrders,
        ];
        extract($data);
        require_once __DIR__ . '/../views/orders/active.php';
    }

    public function history() {
        // List order history - completed/failed orders
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $orderModel = new RiderOrder();
        // Fetch completed orders
        $completedOrders = $orderModel->where('rider_id', '=', $riderId)
            ->andWhere('delivery_status', '=', 'delivered')
            ->orderBy('delivered_at', 'DESC')
            ->get();

        // Fetch failed orders
        $failedOrders = $orderModel->where('rider_id', '=', $riderId)
            ->andWhere('delivery_status', '=', 'failed')
            ->orderBy('failed_at', 'DESC')
            ->get();

        // Fetch cancelled orders
        $cancelledOrders = $orderModel->where('rider_id', '=', $riderId)
            ->andWhere('delivery_status', '=', 'cancelled')
            ->orderBy('updated_at', 'DESC')
            ->get();

        $data = [
            'completed_orders' => $completedOrders,
            'failed_orders' => $failedOrders,
            'cancelled_orders' => $cancelledOrders,
        ];
        extract($data);
        require_once __DIR__ . '/../views/orders/history.php';
    }

    public function view($orderId) {
        // View order details
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $orderModel = new RiderOrder();
        
        // Fetch order details for this rider
        $order = $orderModel->where('order_id', '=', intval($orderId))
            ->andWhere('rider_id', '=', $riderId)
            ->first();
        
        if (!$order) {
            http_response_code(404);
            echo "Order not found";
            exit;
        }

        $data = [
            'order' => $order,
        ];
        extract($data);
        require_once __DIR__ . '/../views/orders/view.php';
    }

    public function updateStatus($id, $status) {
        // Update delivery status (picked_up, on_way, arrived, delivered, failed)
    }

    public function acceptOrder($orderId) {
        // Accept an assigned order
    }

    public function rejectOrder($orderId, $reason = null) {
        // Reject an assigned order
    }
}
