<?php
/**
 * Vendor Update AJAX Handler
 */

require_once '../../bootstrap/app.php';

$db = getDBConnection();

header('Content-Type: application/json');

try {
    $vendor_id = $_POST['vendor_id'] ?? null;
    
    if (!$vendor_id) {
        throw new Exception('Vendor ID is required');
    }
    
    // Validate input
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'store_name', 'store_category', 'business_address', 'city', 'state', 'postal_code', 'registration_number', 'tax_id', 'account_number', 'account_holder', 'ifsc_code'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }
    
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'] ?? '';
    $store_name = trim($_POST['store_name']);
    $store_description = trim($_POST['store_description'] ?? '');
    $store_category = trim($_POST['store_category']);
    $business_address = trim($_POST['business_address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $postal_code = trim($_POST['postal_code']);
    $registration_number = trim($_POST['registration_number']);
    $tax_id = trim($_POST['tax_id']);
    $status = $_POST['status'] ?? 'active';
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder = trim($_POST['account_holder']);
    $ifsc_code = trim($_POST['ifsc_code']);
    $bank_name_other = trim($_POST['bank_name_other'] ?? '');

    // Prefer bank_name_other if provided
    if (empty($bank_name) && empty($bank_name_other)) {
        throw new Exception('bank_name or bank_name_other is required');
    }
    if (!empty($bank_name_other)) {
        $bank_name = $bank_name_other;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate phone
    if (!preg_match('/^\d{10}$/', $phone)) {
        throw new Exception('Phone number must be 10 digits');
    }
    
    // Check if email already exists (for other vendors)
    $stmt = $db->prepare("SELECT id FROM vendors WHERE email = ? AND id != ?");
    $stmt->execute([$email, $vendor_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Email already registered to another vendor');
    }
    
    // Check if phone already exists (for other vendors)
    $stmt = $db->prepare("SELECT id FROM vendors WHERE phone = ? AND id != ?");
    $stmt->execute([$phone, $vendor_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Phone number already registered to another vendor');
    }
    
    // Check if registration number already exists (for other vendors)
    $stmt = $db->prepare("SELECT id FROM vendors WHERE registration_number = ? AND id != ?");
    $stmt->execute([$registration_number, $vendor_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Registration number already registered to another vendor');
    }
    
    // Check if tax ID already exists (for other vendors)
    $stmt = $db->prepare("SELECT id FROM vendors WHERE tax_id = ? AND id != ?");
    $stmt->execute([$tax_id, $vendor_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Tax ID already registered to another vendor');
    }
    
    // Check if account number already exists (for other vendors)
    $stmt = $db->prepare("SELECT id FROM vendors WHERE account_number = ? AND id != ?");
    $stmt->execute([$account_number, $vendor_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Account number already registered to another vendor');
    }
    
    // Prepare update statement
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            UPDATE vendors SET
                first_name = ?, last_name = ?, email = ?, phone = ?, password = ?, 
                store_name = ?, store_description = ?, store_category = ?, 
                business_address = ?, city = ?, state = ?, postal_code = ?,
                registration_number = ?, tax_id = ?, status = ?, 
                bank_name = ?, account_number = ?, account_holder = ?, ifsc_code = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $first_name, $last_name, $email, $phone, $hashed_password, 
            $store_name, $store_description, $store_category, $business_address, 
            $city, $state, $postal_code, $registration_number, $tax_id, $status,
            $bank_name, $account_number, $account_holder, $ifsc_code, $vendor_id
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE vendors SET
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                store_name = ?, store_description = ?, store_category = ?, 
                business_address = ?, city = ?, state = ?, postal_code = ?,
                registration_number = ?, tax_id = ?, status = ?, 
                bank_name = ?, account_number = ?, account_holder = ?, ifsc_code = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $first_name, $last_name, $email, $phone, 
            $store_name, $store_description, $store_category, $business_address, 
            $city, $state, $postal_code, $registration_number, $tax_id, $status,
            $bank_name, $account_number, $account_holder, $ifsc_code,
            $vendor_id
        ]);
    }
    // Handle optional image uploads and save to vendor-panel uploads path
    try {
        $uploadDir = __DIR__ . '/../../../vendor-panel/public/assets/uploads/vendor_' . $vendor_id;
        $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                        strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
        $basePrefix = $isLocalhost ? '/phool-delivery-platform' : '';
        $webPathBase = $basePrefix . '/vendor-panel/public/assets/uploads/vendor_' . $vendor_id;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $saved = [];
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $name = 'profile.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . '/' . $name)) {
                $saved['profile_image_url'] = $webPathBase . '/' . $name;
            }
        }
        if (!empty($_FILES['logo_image']['tmp_name'])) {
            $ext = pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION);
            $name = 'logo.' . $ext;
            if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $uploadDir . '/' . $name)) {
                $saved['logo_url'] = $webPathBase . '/' . $name;
            }
        }
        if (!empty($_FILES['banner_image']['tmp_name'])) {
            $ext = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
            $name = 'banner.' . $ext;
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . '/' . $name)) {
                $saved['banner_url'] = $webPathBase . '/' . $name;
            }
        }

        if (!empty($saved)) {
            $sets = [];
            $vals = [];
            foreach ($saved as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
            $vals[] = $vendor_id;
            $sql = "UPDATE vendors SET " . implode(', ', $sets) . " WHERE id = ?";
            $ustmt = $db->prepare($sql);
            $ustmt->execute($vals);
        }
    } catch (Exception $ue) {
        error_log('[vendor-update] image save error: ' . $ue->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Vendor updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
