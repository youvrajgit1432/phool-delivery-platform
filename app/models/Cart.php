<?php
// app/models/Cart.php
class Cart {
    private $conn;
    private $table_name = "cart";

    public function __construct($db) {
        $this->conn = $db;
        
        // Initialize session cart if not exists
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Initialize selected items if not exists
        if (!isset($_SESSION['selected_items'])) {
            $_SESSION['selected_items'] = [];
        }
    }

    // Add item to cart (session + database if user logged in)
    public function addItem($product_id, $product_name, $product_price, $quantity = 1, $image = 'default.jpg', $unit = 'piece') {
        $cart_item = [
            'id' => $product_id,
            'name' => $product_name,
            'price' => (float)$product_price,
            'quantity' => (int)$quantity,
            'image' => $image,
            'unit' => $unit
        ];

        // Check if product already in cart
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $cart_item;
        }

        // Auto-select new item
        $_SESSION['selected_items'][$product_id] = true;

        // If user is logged in, sync with database
        if (isset($_SESSION['customer_id'])) {
            $this->syncWithDatabase();
        }

        return true;
    }

    // Sync session cart with database
    private function syncWithDatabase() {
        $customer_id = $_SESSION['customer_id'];
        
        // Clear existing cart items for this customer
        $query = "DELETE FROM cart WHERE customer_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $customer_id);
        $stmt->execute();

        // Insert current cart items
        foreach ($_SESSION['cart'] as $item) {
            $query = "INSERT INTO cart (customer_id, product_id, quantity, created_at) 
                     VALUES (?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $customer_id);
            $stmt->bindParam(2, $item['id']);
            $stmt->bindParam(3, $item['quantity']);
            $stmt->execute();
        }
    }

    // Get cart items
    public function getCart() {
        // If user is logged in, load from database
        if (isset($_SESSION['customer_id'])) {
            $this->loadFromDatabase();
        }
        
        return $_SESSION['cart'];
    }

    // Get selected items
    public function getSelectedItems() {
        return $_SESSION['selected_items'] ?? [];
    }

    // Update selected items
    public function updateSelectedItems($selected_items) {
        $_SESSION['selected_items'] = $selected_items;
        return true;
    }

    // Load cart from database
    private function loadFromDatabase() {
        $customer_id = $_SESSION['customer_id'];
        
        $query = "SELECT c.*, p.name_en, p.name_ne, p.price, p.unit, pi.image_path 
                 FROM cart c 
                 JOIN products p ON c.product_id = p.id 
                 LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                 WHERE c.customer_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $customer_id);
        $stmt->execute();
        
        $db_cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge with session cart
        foreach ($db_cart as $item) {
            $_SESSION['cart'][$item['product_id']] = [
                'id' => $item['product_id'],
                'name' => $item['name_en'], // Default to English name
                'name_en' => $item['name_en'],
                'name_ne' => $item['name_ne'],
                'price' => (float)$item['price'],
                'quantity' => (int)$item['quantity'],
                'image' => $item['image_path'] ?? 'default.jpg',
                'image_path' => $item['image_path'] ?? 'default.jpg',
                'unit' => $item['unit'] ?? 'piece'
            ];
            
            // Ensure selected items exist for loaded items
            if (!isset($_SESSION['selected_items'][$item['product_id']])) {
                $_SESSION['selected_items'][$item['product_id']] = true;
            }
        }
    }

    // Update item quantity
    public function updateQuantity($product_id, $quantity) {
        if ($quantity <= 0) {
            $this->removeItem($product_id);
            return;
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            
            // Sync with database if logged in
            if (isset($_SESSION['customer_id'])) {
                $this->syncWithDatabase();
            }
        }
    }

    // Remove item from cart
    public function removeItem($product_id) {
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            
            // Remove from selected items
            if (isset($_SESSION['selected_items'][$product_id])) {
                unset($_SESSION['selected_items'][$product_id]);
            }
            
            // Sync with database if logged in
            if (isset($_SESSION['customer_id'])) {
                $this->syncWithDatabase();
            }
        }
    }

    // Clear cart
    public function clearCart() {
        $_SESSION['cart'] = [];
        $_SESSION['selected_items'] = [];
        
        // Clear database cart if logged in
        if (isset($_SESSION['customer_id'])) {
            $query = "DELETE FROM cart WHERE customer_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['customer_id']);
            $stmt->execute();
        }
    }

    // Get cart total for selected items
    public function getSelectedTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            if (isset($_SESSION['selected_items'][$item['id']]) && $_SESSION['selected_items'][$item['id']]) {
                $total += $item['price'] * $item['quantity'];
            }
        }
        return $total;
    }

    // Get cart total for all items
    public function getTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }

    // Get item count for selected items
    public function getSelectedItemCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            if (isset($_SESSION['selected_items'][$item['id']]) && $_SESSION['selected_items'][$item['id']]) {
                $count += $item['quantity'];
            }
        }
        return $count;
    }

    // Get item count for all items
    public function getItemCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    // Get selected items for checkout
    public function getSelectedItemsForCheckout() {
        $selected_items = [];
        foreach ($_SESSION['cart'] as $item) {
            if (isset($_SESSION['selected_items'][$item['id']]) && $_SESSION['selected_items'][$item['id']]) {
                $selected_items[$item['id']] = $item;
            }
        }
        return $selected_items;
    }
}
?>