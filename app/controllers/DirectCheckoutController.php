<?php
// app/controllers/DirectCheckoutController.php
class DirectCheckoutController {
    private $db;
    private $cartModel;
    private $productModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->cartModel = new Cart($db);
        $this->productModel = new Product($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    public function process() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $product_slug = $_POST['product_slug'] ?? null;
            $quantity = $_POST['quantity'] ?? 1;
            
            if (!$product_slug) {
                echo json_encode(['success' => false, 'message' => 'Product not specified']);
                exit;
            }
            
            // Get product by slug
            $product = $this->productModel->getProductBySlug($product_slug);
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            
            // Check stock availability
            if ($product['stock_quantity'] < $quantity) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Insufficient stock. Only ' . $product['stock_quantity'] . ' available'
                ]);
                exit;
            }
            
            // Clear cart first
            $this->cartModel->clearCart();
            
            // Add the product to cart
            $this->cartModel->addItem(
                $product['id'], 
                $product['name'], 
                $product['price'], 
                $quantity,
                $product['primary_image'] ?? 'default.jpg',
                $product['unit'] ?? 'piece'
            );
            
            // Set session flag to indicate coming from direct checkout
            $_SESSION['from_direct_checkout'] = true;
            
            // Get checkout URL - ensure it's properly formatted
            // Use the base_url from pathConfig which includes protocol and domain
            $base_url = $this->pathConfig->get('base_url');
            $checkout_url = rtrim($base_url, '/') . '/checkout?quick=1';
            
            // Always redirect to checkout regardless of login status
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart',
                'redirect' => $checkout_url
            ]);
            exit;
        }
    }
}
?>