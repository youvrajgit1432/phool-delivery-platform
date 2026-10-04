<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

header('Content-Type: application/json');

// Initialize database
$pdo = getDBConnection();

// Get action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$json_data = json_decode(file_get_contents('php://input'), true);

// Merge JSON data with POST/GET
if ($json_data) {
    $_POST = array_merge($_POST, $json_data);
}

try {
    switch ($action) {
        case 'update_sort_order':
            updateSortOrder($pdo);
            break;
        
        case 'toggle_status':
            toggleStatus($pdo);
            break;
        
        case 'get_carousels':
            getCarousels($pdo);
            break;
        
        case 'get_carousel':
            getCarouselDetail($pdo);
            break;
        
        case 'delete':
            deleteCarousel($pdo);
            break;
        
        case 'get_by_category':
            getCarouselsByCategory($pdo);
            break;
        
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action: ' . htmlspecialchars($action)
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Update carousel sort order
 */
function updateSortOrder($pdo) {
    $id = $_POST['id'] ?? 0;
    $sort_order = $_POST['sort_order'] ?? 0;
    
    if (!$id) {
        throw new Exception('Carousel ID is required');
    }
    
    $stmt = $pdo->prepare("UPDATE carousel_images SET sort_order = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$sort_order, $id]);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Sort order updated successfully' : 'Failed to update sort order',
        'id' => $id,
        'sort_order' => $sort_order
    ]);
}

/**
 * Toggle carousel status
 */
function toggleStatus($pdo) {
    $id = $_POST['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Carousel ID is required');
    }
    
    // Get current status
    $stmt = $pdo->prepare("SELECT status FROM carousel_images WHERE id = ?");
    $stmt->execute([$id]);
    $carousel = $stmt->fetch();
    
    if (!$carousel) {
        throw new Exception('Carousel not found');
    }
    
    $new_status = ($carousel['status'] === 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE carousel_images SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$new_status, $id]);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Status updated successfully' : 'Failed to update status',
        'id' => $id,
        'new_status' => $new_status
    ]);
}

/**
 * Get all carousels
 */
function getCarousels($pdo) {
    $status_filter = $_GET['status'] ?? '';
    $category_id = $_GET['category_id'] ?? 0;
    
    $query = "
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
    ";
    
    $params = [];
    $conditions = [];
    
    if (!empty($status_filter)) {
        $conditions[] = "ci.status = ?";
        $params[] = $status_filter;
    }
    
    if ($category_id > 0) {
        $conditions[] = "ci.main_category_id = ?";
        $params[] = $category_id;
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY ci.sort_order, ci.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $carousels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $carousels,
        'count' => count($carousels)
    ]);
}

/**
 * Get single carousel detail
 */
function getCarouselDetail($pdo) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Carousel ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        WHERE ci.id = ?
    ");
    $stmt->execute([$id]);
    $carousel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carousel) {
        throw new Exception('Carousel not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $carousel
    ]);
}

/**
 * Delete carousel
 */
function deleteCarousel($pdo) {
    $id = $_POST['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Carousel ID is required');
    }
    
    // Get carousel details
    $stmt = $pdo->prepare("SELECT * FROM carousel_images WHERE id = ?");
    $stmt->execute([$id]);
    $carousel = $stmt->fetch();
    
    if (!$carousel) {
        throw new Exception('Carousel not found');
    }
    
    try {
        // Delete image file
        if (!empty($carousel['image_path'])) {
            $file_path = '../storage/uploads/carousel/' . $carousel['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM carousel_images WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Carousel deleted successfully' : 'Failed to delete carousel',
            'id' => $id
        ]);
    } catch (Exception $e) {
        throw new Exception('Error deleting carousel: ' . $e->getMessage());
    }
}

/**
 * Get carousels by specific category
 */
function getCarouselsByCategory($pdo) {
    $category_id = $_GET['category_id'] ?? 0;
    $status = $_GET['status'] ?? 'active';
    
    if (!$category_id) {
        throw new Exception('Category ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT ci.*, c.name_en as category_name_en, c.name_ne as category_name_ne
        FROM carousel_images ci
        LEFT JOIN categories c ON ci.main_category_id = c.id
        WHERE ci.main_category_id = ? AND ci.status = ?
        ORDER BY ci.sort_order, ci.id
    ");
    $stmt->execute([$category_id, $status]);
    
    $carousels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $carousels,
        'count' => count($carousels),
        'category_id' => $category_id
    ]);
}
?>