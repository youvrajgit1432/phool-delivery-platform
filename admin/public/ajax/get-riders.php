<?php
/**
 * AJAX endpoint for getting riders with filters
 * Returns JSON with rider data and table HTML
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering to prevent HTML from mixing with JSON
ob_start();

try {
    require_once '../../bootstrap/app.php';
    require_once '../../app/middleware/AdminMiddleware.php';

    // Check admin authentication
    requireAuth();

    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

// Get filter parameters
$view = isset($_GET['view']) ? $_GET['view'] : 'active';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at_desc';

// Build WHERE clause
$where_conditions = [];
$params = [];

switch ($view) {
    case 'trash':
        $where_conditions[] = "r.deleted_at IS NOT NULL";
        break;
    case 'pending':
        $where_conditions[] = "r.status = 'pending' AND r.deleted_at IS NULL";
        break;
    case 'suspended':
        $where_conditions[] = "r.status = 'suspended' AND r.deleted_at IS NULL";
        break;
    case 'inactive':
        $where_conditions[] = "r.status = 'inactive' AND r.deleted_at IS NULL";
        break;
    case 'active':
    default:
        $where_conditions[] = "r.status = 'active' AND r.deleted_at IS NULL";
}

// Status filter (if not trash view)
if (!empty($status_filter) && $view !== 'trash') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(r.first_name LIKE ? OR r.last_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Build WHERE clause string
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Build ORDER BY clause
$order_clause = "ORDER BY ";
switch ($sort_by) {
    case 'created_at_asc':
        $order_clause .= "r.created_at ASC";
        break;
    case 'name_asc':
        $order_clause .= "r.first_name ASC, r.last_name ASC";
        break;
    case 'name_desc':
        $order_clause .= "r.first_name DESC, r.last_name DESC";
        break;
    case 'rating_desc':
        $order_clause .= "r.average_rating DESC";
        break;
    case 'deliveries_desc':
        $order_clause .= "r.total_deliveries DESC";
        break;
    default:
        $order_clause .= "r.created_at DESC";
}

// Fetch riders
try {
    $sql = "SELECT 
                r.id, r.first_name, r.last_name, r.email, r.phone, r.rider_type,
                r.vehicle_type, r.vehicle_number, r.status, r.is_available,
                r.average_rating, r.total_deliveries, r.total_reviews,
                r.total_earnings, r.total_penalties, r.cancellation_rate,
                r.on_time_delivery_rate, r.email_verified, r.documents_verified,
                r.bank_verified, r.last_login, r.created_at, r.updated_at, r.deleted_at,
                (SELECT COUNT(*) FROM rider_orders WHERE rider_id = r.id AND delivery_status = 'assigned') as pending_orders,
                (SELECT COUNT(*) FROM rider_orders WHERE rider_id = r.id AND delivery_status = 'delivered') as delivered_orders,
                (SELECT COALESCE(SUM(amount), 0) FROM rider_penalties WHERE rider_id = r.id AND status = 'pending') as pending_penalties
            FROM riders r
            " . $where_clause . "
            " . $order_clause;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $db->errorInfo()[2]);
    }

    if (!$stmt->execute($params)) {
        throw new Exception('Failed to execute statement: ' . $stmt->errorInfo()[2]);
    }

    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($riders)) {
        $riders = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading riders: ' . $e->getMessage()]);
    exit;
}

// Generate table HTML
$tableHtml = '';

if (empty($riders)) {
    $tableHtml = '<tr><td colspan="12" class="text-center py-4">
                    <i class="bi bi-inbox"></i> No riders found.
                    <a href="riders/add.php" class="ms-2">Add a new rider</a>
                  </td></tr>';
} else {
    foreach ($riders as $rider) {
        $tableHtml .= '<tr class="rider-row" data-rider-search="' . strtolower($rider['first_name'] . ' ' . $rider['last_name'] . ' ' . $rider['email'] . ' ' . $rider['phone']) . '" 
                          data-rider-status="' . strtolower($rider['status']) . '" 
                          data-rider-deleted="' . (!empty($rider['deleted_at']) ? '1' : '0') . '">';
        
        // Checkbox
        $tableHtml .= '<td><input type="checkbox" class="form-check-input rider-checkbox" value="' . $rider['id'] . '"></td>';
        
        // Name
        $nameInitial = strtoupper(substr($rider['first_name'] ?? '', 0, 1));
        $tableHtml .= '<td><div class="d-flex align-items-center">'
                    . '<div style="width:40px;height:40px;border-radius:50%;background:#e9ecef;color:#6c757d;display:flex;align-items:center;justify-content:center;margin-right:10px;font-weight:600;font-size:0.9rem;">'
                    . htmlspecialchars($nameInitial) . '</div>'
                    . '<div><strong>' . htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) . '</strong>'
                    . '<div class="d-md-none small text-muted">' . htmlspecialchars($rider['phone']) . '</div></div></div></td>';
        
        // Email (hidden on mobile)
        $tableHtml .= '<td class="d-none d-md-table-cell">'
                    . '<a href="mailto:' . htmlspecialchars($rider['email']) . '" style="font-size: 0.90rem;">'
                    . htmlspecialchars($rider['email']) . '</a></td>';
        
        // Phone (hidden on mobile)
        $tableHtml .= '<td class="d-none d-md-table-cell">' . htmlspecialchars($rider['phone']) . '</td>';
        
        // Type (hidden on tablet)
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . '<span class="badge bg-info text-dark">' . htmlspecialchars(ucfirst($rider['rider_type'])) . '</span></td>';
        
        // Vehicle (hidden on tablet)
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . htmlspecialchars($rider['vehicle_type'] . ' - ' . $rider['vehicle_number']) . '</td>';
        
        // Status
        $badgeClass = $rider['status'] === 'active' ? 'success' : 
                     ($rider['status'] === 'suspended' ? 'danger' : 
                     ($rider['status'] === 'pending' ? 'warning' : 'secondary'));
        if (!empty($rider['deleted_at'])) {
            $tableHtml .= '<td><span class="badge bg-secondary">Trashed</span></td>';
        } else {
            $tableHtml .= '<td><span class="badge bg-' . $badgeClass . '">' . ucfirst($rider['status']) . '</span></td>';
        }
        
        // Rating (hidden on large screens)
        $tableHtml .= '<td class="d-none d-xl-table-cell">'
                    . '<span class="badge bg-warning text-dark">★ ' . number_format($rider['average_rating'], 1) . '</span></td>';
        
        // Deliveries (hidden on large screens)
        $tableHtml .= '<td class="d-none d-xl-table-cell">'
                    . '<span class="badge bg-light text-dark">' . $rider['total_deliveries'] . '</span></td>';
        
        // Availability
        $availColor = $rider['is_available'] ? 'success' : 'secondary';
        $availText = $rider['is_available'] ? 'Online' : 'Offline';
        $tableHtml .= '<td><span class="badge bg-' . $availColor . '">' . htmlspecialchars($availText) . '</span></td>';
        
        // Actions
        $tableHtml .= '<td><div class="btn-group btn-group-sm flex-wrap" role="group">';
        $tableHtml .= '<a href="riders/view.php?id=' . $rider['id'] . '" class="btn btn-outline-info" title="View Details" data-bs-toggle="tooltip"><i class="bi bi-eye"></i></a>';
        $tableHtml .= '<a href="riders/edit.php?id=' . $rider['id'] . '" class="btn btn-outline-warning" title="Edit Rider" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>';
        
        if (!empty($rider['deleted_at'])) {
            $tableHtml .= '<button type="button" class="btn btn-outline-success" onclick="restoreSingleRider(' . $rider['id'] . ')" title="Restore Rider" data-bs-toggle="tooltip"><i class="bi bi-arrow-counterclockwise"></i></button>';
        } else {
            if ($rider['status'] === 'suspended') {
                $tableHtml .= '<button type="button" class="btn btn-outline-success" onclick="updateRiderStatus(' . $rider['id'] . ', \'active\')" title="Activate Rider" data-bs-toggle="tooltip"><i class="bi bi-unlock"></i></button>';
            } else {
                $tableHtml .= '<button type="button" class="btn btn-outline-secondary" onclick="updateRiderStatus(' . $rider['id'] . ', \'suspended\')" title="Suspend Rider" data-bs-toggle="tooltip"><i class="bi bi-slash-circle"></i></button>';
            }
            $tableHtml .= '<button type="button" class="btn btn-outline-danger" onclick="deleteRider(' . $rider['id'] . ')" title="Delete Rider" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>';
        }
        $tableHtml .= '</div></td></tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($riders)
]);
exit;
