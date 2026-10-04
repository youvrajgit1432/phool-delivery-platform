<?php
/**
 * Nepali Date API
 * Endpoints:
 * GET /date/api.php?action=current - Get current Nepali date
 * GET /date/api.php?action=convert&date=Y-m-d - Convert English date to Nepali
 * POST /date/api.php?action=update - Manually trigger update (admin only)
 */

require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/NepaliDateConverter.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'current';
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'current':
            // Get current Nepali date from database
            $stmt = $pdo->prepare("SELECT * FROM current_nepali_date LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $result,
                    'formatted' => NepaliDateConverter::formatNepaliDate(
                        $result['year'],
                        $result['month'],
                        $result['day'],
                        $result['weekday']
                    )
                ]);
            } else {
                // If no record, convert current date
                $nepaliDate = NepaliDateConverter::getCurrentNepaliDate($pdo);
                echo json_encode([
                    'status' => 'success',
                    'data' => $nepaliDate,
                    'formatted' => NepaliDateConverter::formatNepaliDate(
                        $nepaliDate['year'],
                        $nepaliDate['month'],
                        $nepaliDate['day'],
                        $nepaliDate['weekday']
                    )
                ]);
            }
            break;
            
        case 'convert':
            $date = $_GET['date'] ?? date('Y-m-d');
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Invalid date format. Use Y-m-d');
            }
            
            $nepaliDate = NepaliDateConverter::convertToNepali($date, $pdo);
            
            echo json_encode([
                'status' => 'success',
                'english_date' => $date,
                'nepali_date' => $nepaliDate,
                'formatted' => NepaliDateConverter::formatNepaliDate(
                    $nepaliDate['year'],
                    $nepaliDate['month'],
                    $nepaliDate['day'],
                    $nepaliDate['weekday']
                )
            ]);
            break;
            
        case 'update':
            // Admin only - trigger update
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for update action');
            }
            
            // Execute auto-update script
            $output = shell_exec('php ' . __DIR__ . '/auto-update.php');
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Date updated successfully',
                'output' => $output
            ]);
            break;
            
        case 'all-months':
            // Get all Nepali months information
            $months = NepaliDateConverter::getAllNepaliMonths();
            echo json_encode([
                'status' => 'success',
                'data' => $months
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
