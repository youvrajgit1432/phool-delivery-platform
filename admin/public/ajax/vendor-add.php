<?php

 

require_once '../../bootstrap/app.php';

$db = getDBConnection();

header('Content-Type: application/json');

try {
    // Validate input
    $required_fields = ['owner_name', 'email', 'phone', 'password', 'store_name', 'store_address', 'city', 'state', 'postal_code', 'store_category', 'registration_number', 'tax_id', 'account_number', 'account_holder_name', 'ifsc_code'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field is required");
        }
    }
    
    // Parse owner name into first and last name
    $owner_name = trim($_POST['owner_name']);
    $name_parts = explode(' ', $owner_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $store_name = trim($_POST['store_name']);
    $store_description = trim($_POST['store_description'] ?? '');
    $business_address = trim($_POST['store_address']); // Using store_address from form as business_address
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $postal_code = trim($_POST['postal_code']);
    $store_category = trim($_POST['store_category']);
    $registration_number = trim($_POST['registration_number']);
    $tax_id = trim($_POST['tax_id']);
    $status = $_POST['status'] ?? 'active';
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder = trim($_POST['account_holder_name']); // Renamed to account_holder for DB
    $ifsc_code = trim($_POST['ifsc_code']);
    $bank_name_other = trim($_POST['bank_name_other'] ?? '');

    // Prefer other bank name if provided
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
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Email already registered');
    }
    
    // Check if phone already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Phone number already registered');
    }
    
    // Check if registration number already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE registration_number = ?");
    $stmt->execute([$registration_number]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Registration number already registered');
    }
    
    // Check if tax ID already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE tax_id = ?");
    $stmt->execute([$tax_id]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Tax ID already registered');
    }
    
    // Check if account number already exists
    $stmt = $db->prepare("SELECT id FROM vendors WHERE account_number = ?");
    $stmt->execute([$account_number]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Account number already registered');
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert vendor using correct database columns
    $stmt = $db->prepare("
        INSERT INTO vendors (
            first_name, last_name, email, phone, password, store_name, 
            store_description, store_category, business_address, city, state, 
            postal_code, country, registration_number, tax_id, status,
            bank_name, account_number, account_holder, ifsc_code, 
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    
        $stmt->execute([
        $first_name, $last_name, $email, $phone, $hashed_password, 
        $store_name, $store_description, $store_category, $business_address, 
        $city, $state, $postal_code, 'India', $registration_number, $tax_id, 
        $status, $bank_name, $account_number, $account_holder, $ifsc_code
    ]);
    $vendorId = $db->lastInsertId();

    // Handle optional image uploads and save to vendor-panel uploads path
    $uploadMessages = [];
    try {
        $isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                        strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
        $basePrefix = $isLocalhost ? '/phool-delivery-platform' : '';
        $uploadDir = __DIR__ . '/../../../vendor-panel/public/assets/uploads/vendor_' . $vendorId;
        $webPathBase = $basePrefix . '/vendor-panel/public/assets/uploads/vendor_' . $vendorId;
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
            $vals[] = $vendorId;
            $sql = "UPDATE vendors SET " . implode(', ', $sets) . " WHERE id = ?";
            $ustmt = $db->prepare($sql);
            $ustmt->execute($vals);
        }

        // Handle gallery images
        if (!empty($_FILES['gallery_images']['tmp_name'])) {
            $galleryDir = $uploadDir . '/gallery';
            if (!is_dir($galleryDir)) mkdir($galleryDir, 0755, true);

            $imageTypes = $_POST['image_types'] ?? [];
            $imageDescriptions = $_POST['image_descriptions'] ?? [];

            foreach ($_FILES['gallery_images']['tmp_name'] as $idx => $tmpFile) {
                if (!empty($tmpFile) && is_uploaded_file($tmpFile)) {
                    $ext = pathinfo($_FILES['gallery_images']['name'][$idx], PATHINFO_EXTENSION);
                    $timestamp = time();
                    $random = rand(1000, 9999);
                    $filename = 'gallery_' . $timestamp . '_' . $random . '.' . $ext;
                    
                    if (move_uploaded_file($tmpFile, $galleryDir . '/' . $filename)) {
                        $imageUrl = $webPathBase . '/gallery/' . $filename;
                        $imageType = $imageTypes[$idx] ?? 'gallery';
                        $description = $imageDescriptions[$idx] ?? '';

                        // Insert into vendor_gallery_images table if it exists
                        try {
                            $galleryStmt = $db->prepare("
                                INSERT INTO vendor_gallery_images 
                                (vendor_id, image_url, alt_text, description, image_type, display_order, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $description = $imageDescriptions[$idx] ?? '';
                            $displayOrder = $idx;
                            $galleryStmt->execute([$vendorId, $imageUrl, $description, $description, $imageType, $displayOrder]);
                            $uploadMessages[] = 'Gallery image uploaded successfully';
                        } catch (Exception $galleryErr) {
                            $uploadMessages[] = 'Warning: Could not save gallery image metadata';
                        }
                    }
                }
            }
        }

    } catch (Exception $ue) {
        $uploadMessages[] = 'Image upload error: ' . $ue->getMessage();
    }

    // Send credentials: prefer Email first, fallback to SMS
    $sendResultMessage = '';
    $plainPassword = $password; // generated/entered password from form

    // Build a friendly login URL (best-effort)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $loginUrl = $scheme . '://' . $host . '/vendor-panel/login.php';

    $emailSent = false;
    $smsSent = false;
    $emailError = '';
    $smsResponse = null;

    // Attempt to send Email using PHPMailer if email present
    if (!empty($email)) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            // Load SMTP username from database for From address
            $smtpStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'smtp_username' LIMIT 1");
            $smtpStmt->execute();
            $smtpRow = $smtpStmt->fetch(PDO::FETCH_ASSOC);
            $fromAddress = !empty($smtpRow['setting_value']) ? $smtpRow['setting_value'] : 'phooldelivery@phool-delivery.local';
            $mail->setFrom($fromAddress, 'Phool Delivery');
            $mail->addAddress($email, trim($first_name . ' ' . $last_name));
            $mail->isHTML(true);
            $mail->Subject = 'Your vendor account credentials';
            $body  = "<p>Hi " . htmlspecialchars($first_name) . ",</p>";
            $body .= "<p>Your vendor account has been created. Use the credentials below to login:</p>";
            $body .= "<p><strong>Username:</strong> " . htmlspecialchars($email) . "<br>";
            $body .= "<strong>Password:</strong> " . htmlspecialchars($plainPassword) . "</p>";
            $body .= "<p>Login here: <a href=\"" . htmlspecialchars($loginUrl) . "\">" . htmlspecialchars($loginUrl) . "</a></p>";
            $body .= "<p>If you did not expect this, please contact support.</p>";
            $mail->Body = $body;

            $mail->send();
            $emailSent = true;
        } catch (Exception $ex) {
            $emailError = $mail->ErrorInfo ?? $ex->getMessage();
            error_log('Vendor add email error: ' . $emailError);
        }
    }

    // If email not sent or not available, attempt SMS fallback
    if (!$emailSent && !empty($phone)) {
        try {
            require_once __DIR__ . '/../../../app/services/SparrowSMSService.php';
            $smsService = new SparrowSMSService($db);
            $phoneFormatted = $phone;
            if (strpos($phoneFormatted, '977') !== 0) {
                $phoneFormatted = '977' . $phoneFormatted;
            }
            $smsMessage = "Welcome to Phool Delivery. Username: " . $email . " Password: " . $plainPassword . " Login: " . $loginUrl;
            $smsResponse = $smsService->sendSMS($phoneFormatted, $smsMessage);
            $smsSent = !empty($smsResponse['success']);
        } catch (Exception $sex) {
            error_log('Vendor add SMS error: ' . $sex->getMessage());
        }
    }

    // Log notification attempts (best-effort) using vendor_notifications schema
    try {
        $logStmt = $db->prepare("INSERT INTO vendor_notifications (vendor_id, notification_type, title, message, data, performed_by_admin, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        if ($emailSent) {
            $title = 'Vendor credentials emailed';
            $messageText = 'Credentials emailed to vendor at ' . $email;
            $performedBy = null;
            $data = json_encode(['method' => 'email', 'to' => $email]);
            $logStmt->execute([$vendorId, 'credentials', $title, $messageText, $data, $performedBy]);
            $sendResultMessage = 'Credentials emailed to vendor.';
        } elseif ($smsSent) {
            $title = 'Vendor credentials sent via SMS';
            $messageText = 'Credentials sent via SMS to ' . $phoneFormatted;
            $performedBy = null;
            $data = json_encode(['method' => 'sms', 'to' => $phoneFormatted, 'response' => $smsResponse]);
            $logStmt->execute([$vendorId, 'credentials', $title, $messageText, $data, $performedBy]);
            $sendResultMessage = 'Email failed/unavailable; credentials sent via SMS.';
        } else {
            // record fail with detailed errors and log for debugging
            $title = 'Credentials delivery failed';
            $emailErrText = !empty($emailError) ? $emailError : 'none';
            $smsRespText = !empty($smsResponse) ? (is_string($smsResponse) ? $smsResponse : json_encode($smsResponse)) : 'none';
            $messageText = 'Failed to deliver credentials via email or SMS. Email error: ' . $emailErrText . '. SMS response: ' . $smsRespText;
            $performedBy = null;
            $data = json_encode(['email_error' => $emailError, 'sms_response' => $smsResponse]);
            // Write to DB
            $logStmt->execute([$vendorId, 'credentials', $title, $messageText, $data, $performedBy]);
            // Also write to PHP error log for immediate diagnostics
            error_log('Vendor add send failure for vendor_id=' . $vendorId . ' | email_error=' . $emailErrText . ' | sms_response=' . $smsRespText);
            $sendResultMessage = 'Vendor created but credentials could not be delivered (email & SMS failed).';
        }
    } catch (Exception $le) {
        // Ignore logging errors but set generic message
        error_log('Vendor notification log error: ' . $le->getMessage());
        if ($emailSent) $sendResultMessage = 'Credentials emailed to vendor.';
        elseif ($smsSent) $sendResultMessage = 'Email failed/unavailable; credentials sent via SMS.';
        else $sendResultMessage = 'Vendor created but credentials delivery failed.';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Vendor created successfully. ' . $sendResultMessage,
        'vendor_id' => $vendorId,
        'upload_messages' => $uploadMessages
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
