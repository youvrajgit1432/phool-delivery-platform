<?php
/**
 * Vendor Export AJAX Handler
 * Exports vendor list as CSV
 */

require_once '../../bootstrap/app.php';

try {
    $query = "SELECT 
                v.id, v.owner_name, v.email, v.phone, v.store_name, 
                v.store_address, v.city, v.status, v.commission_rate, 
                COUNT(DISTINCT vp.id) as total_products,
                COUNT(DISTINCT vo.id) as total_orders,
                COALESCE(SUM(vo.total_amount), 0) as total_sales,
                v.created_at
              FROM vendors v
              LEFT JOIN vendor_products vp ON v.id = vp.vendor_id
              LEFT JOIN vendor_orders vo ON v.id = vo.vendor_id AND vo.status = 'completed'
              GROUP BY v.id
              ORDER BY v.created_at DESC";
    
    $vendors = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Create CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vendors_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'ID', 'Owner Name', 'Email', 'Phone', 'Store Name', 
        'Address', 'City', 'Status', 'Commission Rate', 'Products', 
        'Orders', 'Total Sales', 'Joined Date'
    ]);
    
    // Data rows
    foreach ($vendors as $vendor) {
        fputcsv($output, [
            $vendor['id'],
            $vendor['owner_name'],
            $vendor['email'],
            $vendor['phone'],
            $vendor['store_name'],
            $vendor['store_address'],
            $vendor['city'],
            $vendor['status'],
            $vendor['commission_rate'] . '%',
            $vendor['total_products'],
            $vendor['total_orders'],
            'Rs ' . number_format($vendor['total_sales'], 2),
            $vendor['created_at']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting vendors: ' . $e->getMessage()
    ]);
}
