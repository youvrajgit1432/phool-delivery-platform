<?php
// admin/api/delivery-management.php

header('Content-Type: application/json');

require_once '../bootstrap/init.php';
require_once '../app/services/DeliveryService.php';
require_once '../app/services/PaymentService.php';
require_once '../app/services/VendorService.php';

use App\Services\DeliveryService;
use App\Services\PaymentService;
use App\Services\VendorService;

// Check admin authentication
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDatabase();

try {
    $deliveryService = new DeliveryService($db);
    $paymentService = new PaymentService($db);
    $vendorService = new VendorService($db);

    switch ($action) {
        /**
         * DELIVERY MANAGEMENT
         */
        case 'admin_mark_picked_up':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $riderId = $_POST['rider_id'] ?? null;
            if (!$riderOrderId || !$riderId) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }
            $result = $deliveryService->markPickedUp($riderOrderId, $riderId);
            echo json_encode($result);
            break;

        case 'admin_mark_on_the_way':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $riderId = $_POST['rider_id'] ?? null;
            if (!$riderOrderId || !$riderId) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }
            $result = $deliveryService->markOnTheWay($riderOrderId, $riderId);
            echo json_encode($result);
            break;

        case 'admin_mark_delivered':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $riderId = $_POST['rider_id'] ?? null;
            $codCollected = floatval($_POST['cod_collected'] ?? 0);
            $signatureUrl = $_POST['signature_url'] ?? null;
            $proofImageUrl = $_POST['proof_image_url'] ?? null;

            if (!$riderOrderId || !$riderId) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }

            $result = $deliveryService->markDelivered(
                $riderOrderId,
                $riderId,
                $codCollected,
                $signatureUrl,
                $proofImageUrl
            );
            echo json_encode($result);
            break;

        /**
         * PAYMENT MANAGEMENT
         */
        case 'admin_record_cod':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $riderId = $_POST['rider_id'] ?? null;
            $amountCollected = floatval($_POST['amount_collected'] ?? 0);
            $varianceReason = $_POST['variance_reason'] ?? null;

            if (!$riderOrderId || !$riderId) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }

            $result = $paymentService->recordCODCollection(
                $riderOrderId,
                $riderId,
                $amountCollected,
                $varianceReason
            );
            echo json_encode($result);
            break;

        case 'get_payment_logs':
            $orderId = $_GET['order_id'] ?? null;
            if (!$orderId) {
                echo json_encode(['success' => false, 'message' => 'Missing order_id']);
                exit;
            }
            $logs = $paymentService->getPaymentLogs($orderId);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;

        /**
         * VENDOR RATINGS MANAGEMENT
         */
        case 'update_vendor_ratings':
            $vendorId = $_POST['vendor_id'] ?? null;
            if (!$vendorId) {
                echo json_encode(['success' => false, 'message' => 'Missing vendor_id']);
                exit;
            }
            $result = $vendorService->updateVendorRatings($vendorId);
            echo json_encode($result);
            break;

        case 'get_vendor_ratings':
            $vendorId = $_GET['vendor_id'] ?? null;
            if (!$vendorId) {
                echo json_encode(['success' => false, 'message' => 'Missing vendor_id']);
                exit;
            }
            $ratings = $vendorService->getVendorRatings($vendorId);
            echo json_encode(['success' => true, 'data' => $ratings]);
            break;

        case 'get_all_vendor_ratings':
            // Get ratings for all vendors
            $stmt = $db->prepare("SELECT * FROM vendor_ratings ORDER BY average_rating DESC");
            $stmt->execute();
            $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $ratings]);
            break;

        case 'bulk_update_ratings':
            // Update ratings for all vendors with new reviews
            $result = ['success' => true, 'updated' => 0, 'failed' => 0];
            
            // Get all vendors
            $stmt = $db->prepare("SELECT DISTINCT vendor_id FROM vendor_reviews WHERE status = 'approved'");
            $stmt->execute();
            $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($vendors as $vid) {
                $updateResult = $vendorService->updateVendorRatings($vid);
                if ($updateResult['success']) {
                    $result['updated']++;
                } else {
                    $result['failed']++;
                }
            }

            echo json_encode($result);
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
