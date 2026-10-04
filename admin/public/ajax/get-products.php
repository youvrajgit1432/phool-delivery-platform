<?php
/**
 * AJAX endpoint for getting products with filters
 * Returns JSON with product data and table HTML
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering to prevent HTML from mixing with JSON
ob_start();

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';

    // Check admin authentication
    requireAuth();

    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

// Get filter parameters
$main_category_id = isset($_GET['main_category']) ? intval($_GET['main_category']) : 0;
$subcategory_id = isset($_GET['subcategory']) ? intval($_GET['subcategory']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause for category filtering
$where_clause = "";
$params = [];

if ($main_category_id > 0) {
    if ($subcategory_id > 0) {
        // Filter by specific subcategory
        $where_clause = "WHERE EXISTS (
            SELECT 1 FROM product_categories pc 
            WHERE pc.product_id = p.id AND pc.category_id = ?
        )";
        $params[] = $subcategory_id;
    } else {
        // Filter by main category (including all its subcategories)
        // First, get all subcategory IDs for this main category
        $subcat_ids = [];
        $subcat_stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
        $subcat_stmt->execute([$main_category_id]);
        $subcategories = $subcat_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($subcategories)) {
            $placeholders = str_repeat('?,', count($subcategories) - 1) . '?';
            $where_clause = "WHERE EXISTS (
                SELECT 1 FROM product_categories pc 
                WHERE pc.product_id = p.id 
                AND pc.category_id IN (?, $placeholders)
            )";
            $params[] = $main_category_id;
            $params = array_merge($params, $subcategories);
        } else {
            // No subcategories, only filter by main category
            $where_clause = "WHERE EXISTS (
                SELECT 1 FROM product_categories pc 
                WHERE pc.product_id = p.id AND pc.category_id = ?
            )";
            $params[] = $main_category_id;
        }
    }
}

// Add search filter
if (!empty($search)) {
    $searchCondition = "EXISTS (
        SELECT 1 FROM product_categories pc 
        WHERE pc.product_id = p.id AND pc.category_id IN (
            SELECT id FROM categories WHERE parent_id IS NULL
        )
    ) OR p.name_en LIKE ? OR p.name_ne LIKE ? OR p.description LIKE ?";
    
    if (!empty($where_clause)) {
        $where_clause .= " AND ($searchCondition)";
    } else {
        $where_clause = "WHERE ($searchCondition)";
    }
    
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Fetch products
try {
    $products_query = "
        SELECT p.*,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM products p
    ";
    
    if (!empty($where_clause)) {
        $products_query .= " " . $where_clause;
    }
    
    $products_query .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($products_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    
    if (!$stmt->execute($params)) {
        throw new Exception('Failed to execute statement');
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($products)) {
        $products = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading products: ' . $e->getMessage()]);
    exit;
}

// Generate table HTML
$tableHtml = '';

if (empty($products)) {
    $tableHtml = '<tr><td colspan="9" class="text-center py-4">
                    <i class="bi bi-inbox"></i> No products found.
                    <a href="products/add.php" class="ms-2">Add a new product</a>
                  </td></tr>';
} else {
    foreach ($products as $product) {
        $tableHtml .= '<tr>';
        
        // ID (hidden on mobile)
        $tableHtml .= '<td class="d-none d-lg-table-cell">' . htmlspecialchars($product['id']) . '</td>';
        
        // Image
        $tableHtml .= '<td>';
        if (!empty($product['primary_image'])) {
            $imageUrl = getProductImageUrl($product['primary_image']);
            $tableHtml .= '<img src="' . htmlspecialchars($imageUrl) . '" 
                              alt="' . htmlspecialchars($product['name_en']) . '" 
                              class="rounded" 
                              width="40" height="40" 
                              style="object-fit: cover; border: 1px solid #dee2e6;">';
        } else {
            $tableHtml .= '<div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                style="width: 40px; height: 40px; border: 1px solid #dee2e6;">
                                <i class="fas fa-box text-muted"></i>
                           </div>';
        }
        $tableHtml .= '</td>';
        
        // Name
        $tableHtml .= '<td><strong>' . htmlspecialchars($product['name_en']) . '</strong><br>'
                    . '<small class="text-muted">' . htmlspecialchars($product['name_ne']) . '</small></td>';
        
        // Categories (hidden on mobile)
        $tableHtml .= '<td class="d-none d-md-table-cell">';
        $product_cats = $pdo->prepare("
            SELECT c.* 
            FROM categories c 
            JOIN product_categories pc ON c.id = pc.category_id 
            WHERE pc.product_id = ? 
            ORDER BY c.parent_id, c.name_en
        ");
        $product_cats->execute([$product['id']]);
        $categories = $product_cats->fetchAll();
        
        if (!empty($categories)) {
            $catNames = [];
            foreach ($categories as $cat) {
                $catNames[] = htmlspecialchars($cat['name_en']);
            }
            $tableHtml .= implode(', ', array_unique($catNames));
        } else {
            $tableHtml .= '<span class="text-muted">-</span>';
        }
        $tableHtml .= '</td>';
        
        // Price
        $tableHtml .= '<td><strong>Rs. ' . number_format($product['price'], 2) . '</strong></td>';
        
        // Stock (hidden on mobile)
        $stockClass = $product['quantity'] <= 5 ? 'text-danger' : ($product['quantity'] <= 10 ? 'text-warning' : 'text-success');
        $tableHtml .= '<td class="d-none d-md-table-cell"><span class="' . $stockClass . '">'
                    . $product['quantity'] . ' units</span></td>';
        
        // Status
        $statusBadgeClass = $product['status'] === 'active' ? 'success' : 'danger';
        $tableHtml .= '<td><span class="badge bg-' . $statusBadgeClass . '">'
                    . ucfirst($product['status']) . '</span></td>';
        
        // Created (hidden on mobile)
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . htmlspecialchars(date('M d, Y', strtotime($product['created_at']))) . '</td>';
        
        // Actions
        $tableHtml .= '<td>';
        $tableHtml .= '<div class="btn-group btn-group-sm" role="group">';
        $tableHtml .= '<a href="products/view.php?id=' . $product['id'] . '" class="btn btn-outline-info" title="View" data-bs-toggle="tooltip"><i class="bi bi-eye"></i></a>';
        $tableHtml .= '<a href="products/edit.php?id=' . $product['id'] . '" class="btn btn-outline-warning" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>';
        $tableHtml .= '<button type="button" class="btn btn-outline-danger" onclick="deleteProduct(' . $product['id'] . ')" title="Delete" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>';
        $tableHtml .= '</div>';
        $tableHtml .= '</td>';
        
        $tableHtml .= '</tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($products)
]);
exit;
