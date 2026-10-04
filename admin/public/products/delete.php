<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';
// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get product details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

// Redirect if product not found
if (!$product) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: ../products.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get product images
    $images_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $images_stmt->execute([$id]);
    $product_images = $images_stmt->fetchAll();
    
    // Delete image files
    $upload_dir = '../../storage/uploads/products/';
    foreach ($product_images as $image) {
        $image_path = $upload_dir . $image['image_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    // Delete product images from database
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
    
    // Delete product categories
    $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]);
    
    // Delete product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        $_SESSION['success_message'] = "Product deleted successfully!";
        header("Location: ../products.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to delete product.";
        header("Location: ../products.php");
        exit;
    }
}

// Set page title
$page_title = "Delete Product - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Delete Product</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../products.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Products
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-danger">
            <h5 class="alert-heading">Warning!</h5>
            <p>You are about to delete the product: <strong><?php echo htmlspecialchars($product['name_en']); ?> / <?php echo htmlspecialchars($product['name_ne']); ?></strong></p>
            <p class="mb-0">This action cannot be undone. Are you sure you want to proceed?</p>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Product Details:</h6>
                <ul class="list-group">
                    <li class="list-group-item"><strong>ID:</strong> <?php echo $product['id']; ?></li>
                    <li class="list-group-item"><strong>Name (EN):</strong> <?php echo htmlspecialchars($product['name_en']); ?></li>
                    <li class="list-group-item"><strong>Name (NE):</strong> <?php echo htmlspecialchars($product['name_ne']); ?></li>
                    <li class="list-group-item"><strong>Price:</strong> Rs. <?php echo number_format($product['price'], 2); ?></li>
                    <li class="list-group-item"><strong>Stock:</strong> <?php echo $product['stock_quantity']; ?></li>
                </ul>
            </div>
        </div>
        
        <form method="POST" action="">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="../products.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>