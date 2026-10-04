<?php
/**
 * AJAX endpoint for getting vendors with filters
 * Returns JSON with vendor data and table HTML
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
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build WHERE clause based on view
switch ($view) {
    case 'trash':
        $where = "WHERE v.deleted_at IS NOT NULL";
        break;
    case 'suspended':
        $where = "WHERE v.status = 'suspended' AND v.deleted_at IS NULL";
        break;
    case 'pending':
        $where = "WHERE v.status = 'pending' AND v.deleted_at IS NULL";
        break;
    case 'all':
        $where = "";
        break;
    default:
        $where = "WHERE v.status = 'active' AND v.deleted_at IS NULL";
}

// Add search filter if provided
if (!empty($search)) {
    $searchTerm = '%' . $search . '%';
    if (!empty($where)) {
        $where .= " AND (v.store_name LIKE ? OR v.email LIKE ? OR v.phone LIKE ?)";
    } else {
        $where = "WHERE (v.store_name LIKE ? OR v.email LIKE ? OR v.phone LIKE ?)";
    }
}

// Add status filter if provided and not already in WHERE
if (!empty($status_filter) && $view === 'active') {
    if (!empty($where)) {
        $where .= " AND v.status = ?";
    } else {
        $where = "WHERE v.status = ?";
    }
}

// Build main query
$vendors_query = "SELECT v.*,
                        (
                            (
                                SELECT COUNT(*) FROM vendor_products vp2 WHERE vp2.vendor_id = v.id AND (vp2.deleted_at IS NULL OR vp2.deleted_at = '')
                            )
                            +
                            (
                                SELECT COUNT(*) FROM vendor_product_map vpm2 WHERE vpm2.vendor_id = v.id
                            )
                        ) AS total_products,
                         (
                             SELECT COUNT(*) FROM vendor_orders vo2 WHERE vo2.vendor_id = v.id
                         ) AS total_orders,
                         (
                             SELECT COALESCE(SUM(vo3.total_amount), 0) FROM vendor_orders vo3 WHERE vo3.vendor_id = v.id AND vo3.status = 'completed'
                         ) AS total_sales
                  FROM vendors v " . $where . " GROUP BY v.id ORDER BY v.created_at DESC";

// Prepare and execute query
try {
    $stmt = $db->prepare($vendors_query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $db->errorInfo()[2]);
    }
    
    $paramIndex = 1;
    if (!empty($search)) {
        $stmt->bindParam($paramIndex++, $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam($paramIndex++, $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam($paramIndex++, $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($status_filter) && $view === 'active') {
        $stmt->bindParam($paramIndex, $status_filter, PDO::PARAM_STR);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->errorInfo()[2]);
    }
    
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($vendors)) {
        $vendors = [];
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error loading vendors: ' . $e->getMessage()]);
    exit;
}

// Load last notification per vendor
$lastNotifications = [];
if (!empty($vendors)) {
    try {
        $vendorIds = array_column($vendors, 'id');
        if (!empty($vendorIds)) {
            $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
            $sql = "SELECT vn.vendor_id, vn.notification_type, vn.title, vn.message, vn.data, vn.created_at, vn.performed_by_admin, u.full_name as performed_by_name
                    FROM vendor_notifications vn
                    LEFT JOIN users u ON vn.performed_by_admin = u.id
                    JOIN (
                        SELECT vendor_id, MAX(created_at) as maxt FROM vendor_notifications WHERE vendor_id IN ($placeholders) GROUP BY vendor_id
                    ) latest ON vn.vendor_id = latest.vendor_id AND vn.created_at = latest.maxt";
            
            $notifStmt = $db->prepare($sql);
            if ($notifStmt && $notifStmt->execute($vendorIds)) {
                $rows = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $lastNotifications[intval($r['vendor_id'])] = $r;
                    }
                }
            }
        }
    } catch (Exception $ne) {
        // Ignore notification load errors - not critical
        error_log('Notification load error: ' . $ne->getMessage());
    }
}

// Generate table HTML
$tableHtml = '';

if (empty($vendors)) {
    $tableHtml = '<tr><td colspan="12" class="text-center py-4">
                    <i class="bi bi-inbox"></i> No vendors found.
                    <a href="vendors/add.php" class="ms-2">Create a new vendor</a>
                  </td></tr>';
} else {
    foreach ($vendors as $vendor) {
        $tableHtml .= '<tr class="vendor-row" data-vendor-search="' . strtolower($vendor['store_name'] . ' ' . $vendor['email'] . ' ' . $vendor['phone']) . '" 
                          data-vendor-status="' . strtolower($vendor['status']) . '" 
                          data-vendor-deleted="' . (!empty($vendor['deleted_at']) ? '1' : '0') . '">';
        
        // ID Column
        $tableHtml .= '<td class="d-none d-lg-table-cell"><strong>' . $vendor['id'] . '</strong></td>';
        
        // Store Name Column
        $tableHtml .= '<td><div class="d-flex align-items-center">';
        if (!empty($vendor['logo_url'])) {
            $tableHtml .= '<img src="' . htmlspecialchars($vendor['logo_url']) . '" alt="Logo" 
                               style="width:40px;height:40px;object-fit:cover;border-radius:8px;margin-right:10px;">';
        } else {
            $tableHtml .= '<div style="width:40px;height:40px;border-radius:8px;background:#e9ecef;color:#6c757d;display:flex;align-items:center;justify-content:center;margin-right:10px;font-weight:600;font-size:0.9rem;">'
                        . strtoupper(substr($vendor['store_name'] ?? '', 0, 1)) . '</div>';
        }
        $tableHtml .= '<div><strong>' . htmlspecialchars($vendor['store_name']) . '</strong>'
                    . '<div class="d-md-none small text-muted">' . htmlspecialchars($vendor['phone']) . '</div></div></div></td>';
        
        // Owner Name
        $tableHtml .= '<td class="d-none d-md-table-cell">' . htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']) . '</td>';
        
        // Email
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . '<a href="mailto:' . htmlspecialchars($vendor['email']) . '" style="font-size: 0.90rem;">'
                    . htmlspecialchars($vendor['email']) . '</a></td>';
        
        // Phone
        $tableHtml .= '<td class="d-none d-md-table-cell">' . htmlspecialchars($vendor['phone']) . '</td>';
        
        // Category
        $tableHtml .= '<td class="d-none d-lg-table-cell">'
                    . '<span class="badge bg-info text-dark" style="font-size: 0.75rem;">'
                    . htmlspecialchars(ucfirst(str_replace('_', ' ', $vendor['store_category'])))
                    . '</span></td>';
        
        // Status
        $tableHtml .= '<td>';
        if (!empty($vendor['deleted_at'])) {
            $tableHtml .= '<span class="badge bg-secondary">Trashed</span>';
        } else {
            $badgeClass = $vendor['status'] === 'active' ? 'success' : 
                         ($vendor['status'] === 'suspended' ? 'danger' : 
                         ($vendor['status'] === 'pending' ? 'warning' : 'secondary'));
            $tableHtml .= '<span class="badge bg-' . $badgeClass . '">' . ucfirst($vendor['status']) . '</span>';
        }
        $tableHtml .= '</td>';
        
        // Total Products
        $tableHtml .= '<td class="d-none d-xl-table-cell">'
                    . '<span class="badge bg-light text-dark">' . $vendor['total_products'] . '</span></td>';
        
        // Total Orders
        $tableHtml .= '<td class="d-none d-xl-table-cell">'
                    . '<span class="badge bg-light text-dark">' . $vendor['total_orders'] . '</span></td>';
        
        // Sales
        $tableHtml .= '<td class="d-none d-lg-table-cell"><strong>₹' . number_format($vendor['total_sales'], 0) . '</strong></td>';
        
        // Last Send
        $tableHtml .= '<td class="d-none d-lg-table-cell">';
        $ln = $lastNotifications[$vendor['id']] ?? null;
        if ($ln) {
            $ldata = json_decode($ln['data'], true) ?: [];
            $method = $ldata['method'] ?? ($ln['notification_type'] ?? 'info');
            $time = date('M d, H:i', strtotime($ln['created_at']));
            $badgeClass = ($method === 'email' ? 'primary' : ($method === 'sms' ? 'info' : 'secondary'));
            $tableHtml .= '<span class="badge bg-' . $badgeClass . '" style="font-size: 0.70rem;">' . strtoupper(htmlspecialchars($method)) . '</span>'
                        . '<div class="small text-muted mt-1">' . htmlspecialchars($time) . '</div>';
        } else {
            $tableHtml .= '<span class="text-muted">—</span>';
        }
        $tableHtml .= '</td>';
        
        // Actions
        $tableHtml .= '<td><div class="btn-group btn-group-sm flex-wrap" role="group">';
        $tableHtml .= '<a href="vendors/view.php?id=' . $vendor['id'] . '" class="btn btn-outline-info" title="View Details" data-bs-toggle="tooltip"><i class="bi bi-eye"></i></a>';
        $tableHtml .= '<a href="vendors/edit.php?id=' . $vendor['id'] . '" class="btn btn-outline-warning" title="Edit Vendor" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>';
        
        if (!empty($vendor['deleted_at'])) {
            $tableHtml .= '<button type="button" class="btn btn-outline-success" onclick="restoreVendor(' . $vendor['id'] . ')" title="Restore Vendor" data-bs-toggle="tooltip"><i class="bi bi-arrow-counterclockwise"></i></button>';
        } else {
            if ($vendor['status'] === 'suspended') {
                $tableHtml .= '<button type="button" class="btn btn-outline-success" onclick="suspendVendor(' . $vendor['id'] . ', \'unsuspend\')" title="Unsuspend Vendor" data-bs-toggle="tooltip"><i class="bi bi-unlock"></i></button>';
            } else {
                $tableHtml .= '<button type="button" class="btn btn-outline-secondary" onclick="suspendVendor(' . $vendor['id'] . ', \'suspend\')" title="Suspend Vendor" data-bs-toggle="tooltip"><i class="bi bi-slash-circle"></i></button>';
            }
            $tableHtml .= '<button type="button" class="btn btn-outline-danger" onclick="deleteVendor(' . $vendor['id'] . ')" title="Move to Trash" data-bs-toggle="tooltip"><i class="bi bi-trash"></i></button>';
            
            if ($vendor['status'] === 'pending') {
                $tableHtml .= '<button type="button" class="btn btn-outline-success" onclick="approveVendor(' . $vendor['id'] . ', \'approve\')" title="Approve Vendor" data-bs-toggle="tooltip"><i class="bi bi-check2-circle"></i></button>';
                $tableHtml .= '<button type="button" class="btn btn-outline-danger" onclick="approveVendor(' . $vendor['id'] . ', \'disapprove\')" title="Disapprove Vendor" data-bs-toggle="tooltip"><i class="bi bi-x-circle"></i></button>';
            }
        }
        $tableHtml .= '</div></td></tr>';
    }
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode([
    'success' => true,
    'html' => $tableHtml,
    'count' => count($vendors)
]);
exit;
