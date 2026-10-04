<?php
/**
 * Update Vendor Profile API
 * Path: /phool-delivery-platform/vendor-panel/public/ajax/update-profile.php
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['vendor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../../config/Database.php';

$db = new Database();
$conn = $db->connect();
$vendor_id = $_SESSION['vendor_id'];

try {
    // Prepare update fields
    $updates = [];
    $params = [];
    
    if (!empty($_POST['first_name'])) {
        $updates[] = 'first_name = ?';
        $params[] = $_POST['first_name'];
    }
    
    if (!empty($_POST['last_name'])) {
        $updates[] = 'last_name = ?';
        $params[] = $_POST['last_name'];
    }
    
    if (!empty($_POST['email'])) {
        $updates[] = 'email = ?';
        $params[] = $_POST['email'];
    }
    
    if (!empty($_POST['phone'])) {
        $updates[] = 'phone = ?';
        $params[] = $_POST['phone'];
    }
    
    if (!empty($_POST['store_name'])) {
        $updates[] = 'store_name = ?';
        $params[] = $_POST['store_name'];
    }
    
    if (isset($_POST['store_description'])) {
        $updates[] = 'store_description = ?';
        $params[] = $_POST['store_description'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No updates provided']);
        exit;
    }

    $params[] = $vendor_id;
    $updates[] = 'updated_at = NOW()';
    
    $query = 'UPDATE vendors SET ' . implode(', ', $updates) . ' WHERE id = ?';
    
    $stmt = $conn->prepare($query);
    $result = $stmt->execute($params);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
