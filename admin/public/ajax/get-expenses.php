<?php
/**
 * AJAX endpoint for getting expenses with filters
 * Returns JSON with expense data and table HTML
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering to prevent HTML from mixing with JSON
ob_start();

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AuthMiddleware.php';

    // Check authentication
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
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];

if (!empty($category_filter)) {
    $where_clause .= " AND e.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($type_filter)) {
    $where_clause .= " AND ec.parent_category = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $where_clause .= " AND e.expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_clause .= " AND e.expense_date <= ?";
    $params[] = $date_to;
}

if (!empty($status_filter)) {
    $where_clause .= " AND e.status = ?";
    $params[] = $status_filter;
}

// Fetch expenses
try {
    $sql = "SELECT e.*, ec.name as category_name, ec.parent_category, u.full_name as recorded_by_name
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            JOIN users u ON e.recorded_by = u.id
            $where_clause
            ORDER BY e.expense_date DESC, e.created_at DESC";

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    if (!$stmt->execute($params)) {
        throw new Exception('Failed to execute statement');
    }

    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($expenses)) {
        $expenses = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading expenses: ' . $e->getMessage()]);
    exit;
}

// Generate table HTML
$tableHtml = '';

if (empty($expenses)) {
    $tableHtml = '<tr><td colspan="9" class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No expense records found. <a href="add.php">Add your first expense</a></p>
                  </td></tr>';
} else {
    foreach ($expenses as $expense) {
        $tableHtml .= '<tr>';
        
        // Date
        $tableHtml .= '<td>' . date('M d, Y', strtotime($expense['expense_date'])) . '</td>';
        
        // Category
        $categoryBadgeColor = $expense['parent_category'] == 'phool_delivery' ? 'info' : 'success';
        $tableHtml .= '<td><span class="badge bg-' . $categoryBadgeColor . '">'
                    . htmlspecialchars($expense['category_name'])
                    . '</span></td>';
        
        // Description
        $description = !empty($expense['description']) ? htmlspecialchars(substr($expense['description'], 0, 50)) . (strlen($expense['description']) > 50 ? '...' : '') : 'N/A';
        $tableHtml .= '<td>' . $description;
        if (!empty($expense['remarks'])) {
            $tableHtml .= '<br><small class="text-muted">Remarks: ' . htmlspecialchars(substr($expense['remarks'], 0, 30)) . '...</small>';
        }
        $tableHtml .= '</td>';
        
        // Amount
        $tableHtml .= '<td class="fw-bold text-danger">Rs. ' . number_format($expense['amount'], 2) . '</td>';
        
        // Type
        $typeBadgeColor = $expense['parent_category'] == 'phool_delivery' ? 'primary' : 'success';
        $tableHtml .= '<td><span class="badge bg-' . $typeBadgeColor . '">'
                    . ucfirst(str_replace('_', ' ', $expense['parent_category']))
                    . '</span></td>';
        
        // Payment Method
        $tableHtml .= '<td><span class="badge bg-secondary">'
                    . ucfirst(str_replace('_', ' ', $expense['payment_method']))
                    . '</span></td>';
        
        // Status
        $statusBadgeColor = match($expense['status']) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            default => 'secondary'
        };
        $tableHtml .= '<td><span class="badge bg-' . $statusBadgeColor . '">'
                    . ucfirst($expense['status'])
                    . '</span></td>';
        
        // Recorded By
        $tableHtml .= '<td>' . htmlspecialchars($expense['recorded_by_name']) . '</td>';
        
        // Actions
        $tableHtml .= '<td><div class="btn-group btn-group-sm">';
        $tableHtml .= '<a href="view.php?id=' . $expense['id'] . '" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">'
                    . '<i class="fas fa-eye"></i></a>';
        $tableHtml .= '<a href="edit.php?id=' . $expense['id'] . '" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Expense">'
                    . '<i class="fas fa-edit"></i></a>';
        
        // Check admin role
        if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] == 'super_admin' || $_SESSION['admin_role'] == 'admin')) {
            $tableHtml .= '<a href="delete.php?id=' . $expense['id'] . '" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Expense" '
                        . 'onclick="return confirm(\'Are you sure you want to delete this expense record? This action cannot be undone.\')">'
                        . '<i class="fas fa-trash"></i></a>';
        }
        
        $tableHtml .= '</div></td>';
        $tableHtml .= '</tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($expenses)
]);
exit;
