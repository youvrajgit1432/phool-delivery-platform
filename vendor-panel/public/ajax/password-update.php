<?php
/**
 * AJAX: Password Update
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Handle password update
// $response = new \App\Controllers\ProfileController();
// echo json_encode($response->updatePassword());
?>
