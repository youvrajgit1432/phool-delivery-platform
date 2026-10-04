<?php
/**
 * AJAX endpoint for getting customers with filters
 * Returns JSON with customer data and table HTML
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
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($type_filter)) {
    $where_conditions[] = "customer_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch customers
try {
    $customers_query = "
        SELECT * FROM customers 
        $where_clause 
        ORDER BY created_at DESC
    ";

    $stmt = $pdo->prepare($customers_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    if (!$stmt->execute($params)) {
        throw new Exception('Failed to execute statement');
    }

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($customers)) {
        $customers = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading customers: ' . $e->getMessage()]);
    exit;
}

// Generate table HTML
$tableHtml = '';

if (empty($customers)) {
    $tableHtml = '<tr><td colspan="10" class="text-center py-4">
                    <i class="bi bi-inbox"></i> No customers found.
                  </td></tr>';
} else {
    foreach ($customers as $customer) {
        $tableHtml .= '<tr>';
        
        // ID
        $tableHtml .= '<td>' . htmlspecialchars($customer['id']) . '</td>';
        
        // Name
        $tableHtml .= '<td><strong>' . htmlspecialchars($customer['name']) . '</strong>'
                    . '<div class="d-md-none small text-muted mt-1">' . htmlspecialchars($customer['phone']) . '</div></td>';
        
        // Contact (hidden on mobile)
        $tableHtml .= '<td class="d-none d-md-table-cell">'
                    . '<div>' . htmlspecialchars($customer['phone']) . '</div>';
        if (!empty($customer['email'])) {
            $tableHtml .= '<small class="text-muted">' . htmlspecialchars($customer['email']) . '</small>';
        }
        $tableHtml .= '</td>';
        
        // Address (hidden on tablet)
        $tableHtml .= '<td class="d-none d-lg-table-cell">' 
                    . htmlspecialchars(!empty($customer['address']) ? substr($customer['address'], 0, 50) . (strlen($customer['address']) > 50 ? '...' : '') : 'N/A') 
                    . '</td>';
        
        // Type (hidden on mobile)
        $typeColor = 'secondary';
        switch($customer['customer_type']) {
            case 'bulk': $typeColor = 'primary'; break;
            case 'event_planner': $typeColor = 'info'; break;
            case 'wholesaler': $typeColor = 'success'; break;
            case 'normal': $typeColor = 'secondary'; break;
        }
        $tableHtml .= '<td class="d-none d-md-table-cell">'
                    . '<span class="badge bg-' . $typeColor . '">'
                    . htmlspecialchars(ucfirst(str_replace('_', ' ', $customer['customer_type'])))
                    . '</span></td>';
        
        // Status
        $statusColor = 'secondary';
        switch($customer['status']) {
            case 'active': $statusColor = 'success'; break;
            case 'inactive': $statusColor = 'warning'; break;
            case 'banned': $statusColor = 'danger'; break;
        }
        $tableHtml .= '<td><span class="badge bg-' . $statusColor . '">'
                    . htmlspecialchars(ucfirst($customer['status']))
                    . '</span></td>';
        
        // Verification (hidden on large screens)
        $verificationColor = 'warning';
        switch($customer['verification_status']) {
            case 'verified': $verificationColor = 'success'; break;
            case 'rejected': $verificationColor = 'danger'; break;
            case 'pending': $verificationColor = 'warning'; break;
        }
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . '<span class="badge bg-' . $verificationColor . '">'
                    . htmlspecialchars(ucfirst($customer['verification_status']))
                    . '</span>';
        if ($customer['verification_status'] === 'pending') {
            $tableHtml .= '<br><a href="customer-verification.php?status=pending&search=' . urlencode($customer['name']) . '" class="small">'
                        . '<i class="fas fa-user-check me-1"></i> Verify Now</a>';
        }
        $tableHtml .= '</td>';
        
        // Loyalty Points (hidden on large screens)
        $tableHtml .= '<td class="d-none d-xl-table-cell">' . htmlspecialchars($customer['loyalty_points']) . '</td>';
        
        // Created (hidden on large screens)
        $tableHtml .= '<td class="d-none d-lg-table-cell">' 
                    . htmlspecialchars(date('M d, Y', strtotime($customer['created_at']))) 
                    . '</td>';
        
        // Actions
        $tableHtml .= '<td><div class="btn-group btn-group-sm flex-wrap">';
        $tableHtml .= '<a href="view_customer.php?id=' . $customer['id'] . '" class="btn btn-info" data-bs-toggle="tooltip" title="View Details">'
                    . '<i class="fas fa-eye"></i></a>';
        $tableHtml .= '<a href="edit_customer.php?id=' . $customer['id'] . '" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit Customer">'
                    . '<i class="fas fa-edit"></i></a>';
        $tableHtml .= '<button type="button" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Customer" '
                    . 'onclick="deleteCustomer(' . $customer['id'] . ')">'
                    . '<i class="fas fa-trash"></i></button>';
        $tableHtml .= '</div></td>';
        
        $tableHtml .= '</tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($customers)
]);
exit;
