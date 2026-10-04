<?php
// app/controllers/CartController.php
require_once __DIR__ . '/../helpers/cache.php';
class CartController {
    private $cartModel;
    private $db;
    private $pathConfig;

    public function __construct($db = null) {
        if ($db) {
            $this->cartModel = new Cart($db);
            $this->db = $db;
            $this->pathConfig = PathConfig::getInstance();
        }
    }

    public function checkoutSelected() {
        try {
            // Get selected items for checkout
            $selected_items = $this->cartModel->getSelectedItemsForCheckout();
            
            if (empty($selected_items)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select at least one item to proceed to checkout'
                ]);
                exit;
            }
            
            // Store selected items in session for checkout page
            $_SESSION['checkout_items'] = $selected_items;
            
            echo json_encode([
                'success' => true,
                'message' => 'Proceeding to checkout with selected items',
                'redirect' => $this->pathConfig->url('checkout')
            ]);
            exit;
        } catch (Exception $e) {
            error_log("CartController checkoutSelected error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Unable to process checkout request'
            ]);
            exit;
        }
    }

    public function index() {
        try {
            // Ensure cart model exists and database is available
            if (!$this->cartModel || !$this->db) {
                error_log("CartController: cartModel or db not initialized");
                return [
                    'cart' => [],
                    'selected_items' => [],
                    'subtotal' => 0,
                    'selected_subtotal' => 0,
                    'delivery_fee' => 0,
                    'total' => 0,
                    'selected_total' => 0,
                    'page_title' => 'Shopping Cart - Phool Delivery',
                    'error' => 'Cart system initialization error'
                ];
            }

            $cart_items = $this->cartModel->getCart();
            error_log("CartController index - getCart() returned: " . count($cart_items) . " items");
            
            $selected_items = $this->cartModel->getSelectedItems();
            error_log("CartController index - getSelectedItems() returned: " . count($selected_items) . " items");
            
            // Get product images for each cart item
            if (!empty($cart_items)) {
                $cart_items = $this->addProductImagesToCart($cart_items);
                error_log("CartController index - addProductImagesToCart() completed");
            }
            
            $subtotal = $this->cartModel->getTotal();
            error_log("CartController index - getTotal() = " . $subtotal);
            
            $selected_subtotal = $this->cartModel->getSelectedTotal();
            error_log("CartController index - getSelectedTotal() = " . $selected_subtotal);
            
            // Get delivery fee from settings or use default
            $delivery_fee = $this->getDeliveryFee();
            error_log("CartController index - getDeliveryFee() = " . $delivery_fee);
            
            $total = $subtotal + $delivery_fee;
            $selected_total = $selected_subtotal + $delivery_fee;

            return [
                'db' => $this->db,
                'cart' => $cart_items,
                'selected_items' => $selected_items,
                'subtotal' => $subtotal,
                'selected_subtotal' => $selected_subtotal,
                'delivery_fee' => $delivery_fee,
                'total' => $total,
                'selected_total' => $selected_total,
                'page_title' => 'Shopping Cart - Phool Delivery'
            ];
        } catch (Exception $e) {
            error_log("CartController index error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . " | Trace: " . $e->getTraceAsString());
            return [
                'db' => $this->db,
                'cart' => [],
                'selected_items' => [],
                'subtotal' => 0,
                'selected_subtotal' => 0,
                'delivery_fee' => 0,
                'total' => 0,
                'selected_total' => 0,
                'page_title' => 'Shopping Cart - Phool Delivery',
                'error' => $e->getMessage()
            ];
        }
    }

    // Add product images to cart items
    private function addProductImagesToCart($cart_items) {
        if (empty($cart_items)) {
            return $cart_items;
        }

        try {
            // Get product IDs from cart
            $product_ids = array_column($cart_items, 'id');
            $ids_key = implode(',', $product_ids);
            $cacheKey = 'product_primary_images_' . md5($ids_key);
            $product_info = Cache::get($cacheKey);

            if ($product_info === false) {
                $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
                // Query to get primary images for products with multilingual names
                $stmt = $this->db->prepare(
                    "SELECT p.id, p.name_en, p.name_ne, pi.image_path 
                     FROM products p 
                     LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
                     WHERE p.id IN ($placeholders)"
                );
                $stmt->execute($product_ids);
                $product_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Create a mapping of product data
                $product_info = [];
                foreach ($product_data as $product) {
                    $product_info[$product['id']] = [
                        'image' => $product['image_path'] ?? 'default.jpg',
                        'name_en' => $product['name_en'],
                        'name_ne' => $product['name_ne']
                    ];
                }
                // cache small mapping for 60 seconds
                Cache::set($cacheKey, $product_info, 60);
            }

            // Add image paths and multilingual names to cart items
            foreach ($cart_items as &$item) {
                if (isset($product_info[$item['id']])) {
                    $item['image'] = $this->pathConfig->getImagePath($product_info[$item['id']]['image'], 'product');
                    $item['name_en'] = $product_info[$item['id']]['name_en'];
                    $item['name_ne'] = $product_info[$item['id']]['name_ne'];
                } else {
                    $item['image'] = $this->pathConfig->getImagePath('default.jpg', 'product');
                    $item['name_en'] = $item['name'] ?? '';
                    $item['name_ne'] = $item['name'] ?? '';
                }
            }

            return $cart_items;
        } catch (Exception $e) {
            error_log("CartController addProductImagesToCart error: " . $e->getMessage());
            // Return original cart items without images if error occurs
            foreach ($cart_items as &$item) {
                $item['image'] = $this->pathConfig->getImagePath('default.jpg', 'product');
                $item['name_en'] = $item['name'] ?? '';
                $item['name_ne'] = $item['name'] ?? '';
            }
            return $cart_items;
        }
    }

    // Get delivery fee based on customer location or default
    private function getDeliveryFee() {
        try {
            // Get delivery fee from session if available
            if (isset($_SESSION['user_delivery_fee']) && !empty($_SESSION['user_delivery_fee'])) {
                return floatval($_SESSION['user_delivery_fee']);
            }

            // Try to get from customer record if logged in
            if (isset($_SESSION['customer_id'])) {
                $query = "SELECT standard_delivery_fee FROM customers WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$_SESSION['customer_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['standard_delivery_fee'])) {
                    return floatval($result['standard_delivery_fee']);
                }
            }

            // Try to get from delivery cities based on user city
            $city_id = $_SESSION['user_city_id'] ?? null;
            if ($city_id) {
                $query = "SELECT standard_delivery_fee FROM delivery_cities WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$city_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['standard_delivery_fee'])) {
                    return floatval($result['standard_delivery_fee']);
                }
            }

            // Default delivery fee
            return 50.00;
        } catch (Exception $e) {
            error_log("CartController getDeliveryFee error: " . $e->getMessage());
            return 50.00;
        }
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Check if it's JSON data
                $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    // Get JSON data
                    $content = trim(file_get_contents("php://input"));
                    $data = json_decode($content, true);
                    
                    $product_id = $data['product_id'] ?? null;
                    $product_name = $data['product_name'] ?? '';
                    $product_price = $data['product_price'] ?? $data['price'] ?? 0;
                    $quantity = $data['quantity'] ?? 1;
                    $image = $data['image'] ?? 'default.jpg';
                    $unit = $data['unit'] ?? 'piece';
                } else {
                    // Get form data
                    $product_id = $_POST['product_id'] ?? null;
                    $product_name = $_POST['product_name'] ?? '';
                    $product_price = $_POST['product_price'] ?? $_POST['price'] ?? 0;
                    $quantity = $_POST['quantity'] ?? 1;
                    $image = $_POST['image'] ?? 'default.jpg';
                    $unit = $_POST['unit'] ?? 'piece';
                }

                if (!$product_id) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Product ID is required'
                    ]);
                    exit;
                }

                // Validate price and quantity
                $product_price = floatval($product_price);
                $quantity = intval($quantity);
                
                if ($product_price <= 0 || $quantity <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid price or quantity'
                    ]);
                    exit;
                }

                if ($this->cartModel && method_exists($this->cartModel, 'addItem')) {
                    $this->cartModel->addItem($product_id, $product_name, $product_price, $quantity, $image, $unit);
                }
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added to cart',
                    'cart_count' => $this->cartModel ? $this->cartModel->getItemCount() : 1,
                    'selected_count' => $this->cartModel ? $this->cartModel->getSelectedItemCount() : 1,
                    'cart_total' => $this->cartModel ? $this->cartModel->getTotal() : $product_price * $quantity,
                    'selected_total' => $this->cartModel ? $this->cartModel->getSelectedTotal() : $product_price * $quantity
                ]);
                exit;
            } catch (Exception $e) {
                error_log("CartController add error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'An error occurred while adding product to cart: ' . $e->getMessage()
                ]);
                exit;
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Check if it's JSON data
                $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    // Get JSON data
                    $content = trim(file_get_contents("php://input"));
                    $data = json_decode($content, true);
                    
                    $product_id = $data['product_id'] ?? null;
                    $quantity = $data['quantity'] ?? 1;
                } else {
                    // Get form data
                    $product_id = $_POST['product_id'] ?? null;
                    $quantity = $_POST['quantity'] ?? 1;
                }

                if (!$product_id) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Product not found in cart'
                    ]);
                    exit;
                }

                $quantity = intval($quantity);
                if ($quantity <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid quantity'
                    ]);
                    exit;
                }

                if ($this->cartModel && method_exists($this->cartModel, 'updateQuantity')) {
                    $this->cartModel->updateQuantity($product_id, $quantity);
                }
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Cart updated',
                    'cart_count' => $this->cartModel ? $this->cartModel->getItemCount() : 0,
                    'selected_count' => $this->cartModel ? $this->cartModel->getSelectedItemCount() : 0,
                    'cart_total' => $this->cartModel ? $this->cartModel->getTotal() : 0,
                    'selected_total' => $this->cartModel ? $this->cartModel->getSelectedTotal() : 0
                ]);
                exit;
            } catch (Exception $e) {
                error_log("CartController update error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to update cart'
                ]);
                exit;
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }

    public function remove() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Check if it's JSON data
                $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    // Get JSON data
                    $content = trim(file_get_contents("php://input"));
                    $data = json_decode($content, true);
                    
                    $product_id = $data['product_id'] ?? null;
                } else {
                    // Get form data
                    $product_id = $_POST['product_id'] ?? null;
                }

                if (!$product_id) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Product not found in cart'
                    ]);
                    exit;
                }

                if ($this->cartModel && method_exists($this->cartModel, 'removeItem')) {
                    $this->cartModel->removeItem($product_id);
                }
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Product removed from cart',
                    'cart_count' => $this->cartModel ? $this->cartModel->getItemCount() : 0,
                    'selected_count' => $this->cartModel ? $this->cartModel->getSelectedItemCount() : 0,
                    'cart_total' => $this->cartModel ? $this->cartModel->getTotal() : 0,
                    'selected_total' => $this->cartModel ? $this->cartModel->getSelectedTotal() : 0
                ]);
                exit;
            } catch (Exception $e) {
                error_log("CartController remove error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to remove product from cart'
                ]);
                exit;
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }

    public function updateSelection() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Check if it's JSON data
                $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    // Get JSON data
                    $content = trim(file_get_contents("php://input"));
                    $data = json_decode($content, true);
                    
                    $selected_items = $data['selected_items'] ?? [];
                } else {
                    // Get form data
                    $selected_items = $_POST['selected_items'] ?? [];
                }

                if ($this->cartModel && method_exists($this->cartModel, 'updateSelectedItems')) {
                    $this->cartModel->updateSelectedItems($selected_items);
                }
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Selection updated',
                    'selected_count' => $this->cartModel ? $this->cartModel->getSelectedItemCount() : 0,
                    'selected_total' => $this->cartModel ? $this->cartModel->getSelectedTotal() : 0
                ]);
                exit;
            } catch (Exception $e) {
                error_log("CartController updateSelection error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to update selection'
                ]);
                exit;
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }

    public function clear() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                if ($this->cartModel && method_exists($this->cartModel, 'clearCart')) {
                    $this->cartModel->clearCart();
                }
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Cart cleared',
                    'cart_count' => 0,
                    'selected_count' => 0,
                    'cart_total' => 0,
                    'selected_total' => 0
                ]);
                exit;
            } catch (Exception $e) {
                error_log("CartController clear error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to clear cart'
                ]);
                exit;
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }

    // API endpoint to get cart count
    public function getCount() {
        try {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'count' => $this->cartModel ? $this->cartModel->getItemCount() : 0,
                'selected_count' => $this->cartModel ? $this->cartModel->getSelectedItemCount() : 0,
                'total' => $this->cartModel ? $this->cartModel->getTotal() : 0,
                'selected_total' => $this->cartModel ? $this->cartModel->getSelectedTotal() : 0
            ]);
            exit;
        } catch (Exception $e) {
            error_log("CartController getCount error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'count' => 0,
                'selected_count' => 0,
                'total' => 0,
                'selected_total' => 0
            ]);
            exit;
        }
    }

    // Get selected items for checkout
    public function getSelectedForCheckout() {
        try {
            $selected_items = $this->cartModel->getSelectedItemsForCheckout();
            
            if (empty($selected_items)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No items selected for checkout'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'selected_items' => $selected_items,
                'selected_count' => $this->cartModel->getSelectedItemCount(),
                'selected_total' => $this->cartModel->getSelectedTotal()
            ]);
        } catch (Exception $e) {
            error_log("CartController getSelectedForCheckout error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Unable to retrieve selected items'
            ]);
        }
    }
}
?>
 