<?php
// delivery-panel/api/delivery.php

header('Content-Type: application/json');

require_once '../bootstrap/init.php';
require_once '../app/services/DeliveryService.php';
require_once '../app/services/PaymentService.php';

use App\Services\DeliveryService;
use App\Services\PaymentService;

// Check authentication
if (!isset($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$riderId = $_SESSION['rider_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$db = getDatabase();

try {
    $deliveryService = new DeliveryService($db);
    $paymentService = new PaymentService($db);

    switch ($action) {
        case 'mark_picked_up':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            if (!$riderOrderId) {
                echo json_encode(['success' => false, 'message' => 'Missing rider_order_id']);
                exit;
            }
            $result = $deliveryService->markPickedUp($riderOrderId, $riderId);
            echo json_encode($result);
            break;

        case 'mark_on_the_way':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            if (!$riderOrderId) {
                echo json_encode(['success' => false, 'message' => 'Missing rider_order_id']);
                exit;
            }
            $result = $deliveryService->markOnTheWay($riderOrderId, $riderId);
            echo json_encode($result);
            break;

        case 'mark_delivered':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $codCollected = floatval($_POST['cod_collected'] ?? 0);
            $signatureUrl = $_POST['signature_url'] ?? null;
            $proofImageUrl = $_POST['proof_image_url'] ?? null;

            if (!$riderOrderId) {
                echo json_encode(['success' => false, 'message' => 'Missing rider_order_id']);
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

        case 'record_cod_collection':
            $riderOrderId = $_POST['rider_order_id'] ?? null;
            $amountCollected = floatval($_POST['amount_collected'] ?? 0);
            $varianceReason = $_POST['variance_reason'] ?? null;

            if (!$riderOrderId) {
                echo json_encode(['success' => false, 'message' => 'Missing rider_order_id']);
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

        case 'get_delivery_summary':
            $summary = $deliveryService->getRiderDeliverySummary($riderId);
            echo json_encode(['success' => true, 'data' => $summary]);
            break;

        case 'get_cod_summary':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $summary = $paymentService->getRiderCODSummary($riderId, $startDate, $endDate);
            echo json_encode(['success' => true, 'data' => $summary]);
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
