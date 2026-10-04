<?php
// vendor-panel/api/ratings.php

header('Content-Type: application/json');

require_once '../bootstrap/init.php';
require_once '../app/services/VendorService.php';

use App\Services\VendorService;

// Check authentication
if (!isset($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$vendorId = $_SESSION['vendor_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDatabase();

try {
    $vendorService = new VendorService($db);

    switch ($action) {
        case 'update_ratings':
            // Admin or vendor can update their own ratings
            $targetVendorId = $_POST['vendor_id'] ?? $vendorId;
            
            // Verify permissions
            if ($targetVendorId !== $vendorId && $_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $result = $vendorService->updateVendorRatings($targetVendorId);
            echo json_encode($result);
            break;

        case 'get_ratings':
            $ratings = $vendorService->getVendorRatings($vendorId);
            echo json_encode(['success' => true, 'data' => $ratings]);
            break;

        case 'get_vendor_with_ratings':
            $vendorData = $vendorService->getVendorWithRatings($vendorId);
            echo json_encode(['success' => true, 'data' => $vendorData]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
