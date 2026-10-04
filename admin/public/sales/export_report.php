<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get parameters
$report_type = $_GET['report_type'] ?? 'sales_summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
} elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.xls"');
}

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

if ($report_type === 'sales_summary') {
    // Sales Summary Report
    $sales_summary = $pdo->prepare("
        SELECT 
            COUNT(*) as total_deliveries,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as total_sales,
            SUM(payment_received) as total_payments,
            SUM(payment_pending) as total_pending,
            AVG(rate_per_kg) as avg_rate
        FROM flower_deliveries 
        WHERE delivery_date BETWEEN ? AND ?
    ");
    $sales_summary->execute([$date_from, $date_to]);
    $summary = $sales_summary->fetch();

    // Write header
    fputcsv($output, ['Sales Summary Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Write summary
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Deliveries', $summary['total_deliveries']]);
    fputcsv($output, ['Total Quantity (KG)', number_format($summary['total_kg'], 2)]);
    fputcsv($output, ['Total Sales (Rs.)', number_format($summary['total_sales'], 2)]);
    fputcsv($output, ['Total Payments (Rs.)', number_format($summary['total_payments'], 2)]);
    fputcsv($output, ['Pending Amount (Rs.)', number_format($summary['total_pending'], 2)]);
    fputcsv($output, ['Average Rate/KG (Rs.)', number_format($summary['avg_rate'], 2)]);
    fputcsv($output, []); // Empty row

    // Daily sales
    $daily_sales = $pdo->prepare("
        SELECT 
            delivery_date,
            COUNT(*) as delivery_count,
            SUM(quantity_kg) as total_kg,
            SUM(total_amount) as daily_sales,
            SUM(payment_received) as daily_payments
        FROM flower_deliveries 
        WHERE delivery_date BETWEEN ? AND ?
        GROUP BY delivery_date 
        ORDER BY delivery_date
    ");
    $daily_sales->execute([$date_from, $date_to]);
    
    fputcsv($output, ['Daily Sales Breakdown']);
    fputcsv($output, ['Date', 'Deliveries', 'Quantity (KG)', 'Sales (Rs.)', 'Payments (Rs.)']);
    
    while ($row = $daily_sales->fetch()) {
        fputcsv($output, [
            $row['delivery_date'],
            $row['delivery_count'],
            number_format($row['total_kg'], 2),
            number_format($row['daily_sales'], 2),
            number_format($row['daily_payments'], 2)
        ]);
    }

} elseif ($report_type === 'payment_analysis') {
    // Payment Analysis Report
    fputcsv($output, ['Payment Analysis Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row

    $payments = $pdo->prepare("
        SELECT 
            payment_mode,
            COUNT(*) as payment_count,
            SUM(amount_paid) as total_amount,
            AVG(amount_paid) as avg_amount
        FROM buyer_payments 
        WHERE payment_date BETWEEN ? AND ?
        GROUP BY payment_mode 
        ORDER BY total_amount DESC
    ");
    $payments->execute([$date_from, $date_to]);
    
    fputcsv($output, ['Payment Mode', 'Count', 'Total Amount (Rs.)', 'Average Amount (Rs.)']);
    
    while ($row = $payments->fetch()) {
        fputcsv($output, [
            ucfirst($row['payment_mode']),
            $row['payment_count'],
            number_format($row['total_amount'], 2),
            number_format($row['avg_amount'], 2)
        ]);
    }

} elseif ($report_type === 'buyer_performance') {
    // Buyer Performance Report
    fputcsv($output, ['Buyer Performance Report']);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row

    $buyers = $pdo->prepare("
        SELECT 
            b.buyer_name,
            b.buyer_type,
            b.contact_number1,
            COUNT(fd.delivery_id) as total_deliveries,
            SUM(fd.quantity_kg) as total_kg,
            SUM(fd.total_amount) as total_purchases,
            SUM(fd.payment_received) as total_payments,
            SUM(fd.payment_pending) as total_pending,
            (SUM(fd.payment_received) / SUM(fd.total_amount)) * 100 as payment_percentage
        FROM buyers b
        LEFT JOIN flower_deliveries fd ON b.buyer_id = fd.buyer_id 
            AND fd.delivery_date BETWEEN ? AND ?
        WHERE b.status = 'active'
        GROUP BY b.buyer_id 
        ORDER BY total_purchases DESC
    ");
    $buyers->execute([$date_from, $date_to]);
    
    fputcsv($output, ['Buyer Name', 'Type', 'Contact', 'Deliveries', 'Total KG', 'Purchases (Rs.)', 'Payments (Rs.)', 'Pending (Rs.)', 'Payment %']);
    
    while ($row = $buyers->fetch()) {
        fputcsv($output, [
            $row['buyer_name'],
            ucfirst($row['buyer_type']),
            $row['contact_number1'],
            $row['total_deliveries'],
            number_format($row['total_kg'], 2),
            number_format($row['total_purchases'], 2),
            number_format($row['total_payments'], 2),
            number_format($row['total_pending'], 2),
            number_format($row['payment_percentage'], 1) . '%'
        ]);
    }
}

fclose($output);
exit;