<?php
/**
 * Update Availability AJAX Endpoint
 * Allows rider to toggle online/offline status
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$isAvailable = $data['is_available'] ?? false;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$riderId = $_SESSION['rider_id'];

// TODO: Update rider is_available status
// TODO: Update location if provided
// TODO: Log activity
// TODO: Update rider_availability table

echo json_encode([
    'success' => true,
    'message' => $isAvailable ? 'You are now online' : 'You are now offline',
    'data' => [
        'is_available' => $isAvailable,
        'updated_at' => date('Y-m-d H:i:s'),
    ]
]);
