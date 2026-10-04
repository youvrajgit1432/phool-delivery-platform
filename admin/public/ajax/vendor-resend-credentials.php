<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AdminMiddleware.php';

requireAuth();

header('Content-Type: application/json');

try {
    $vendorId = $_POST['vendor_id'] ?? null;
    if (empty($vendorId)) throw new Exception('vendor_id is required');

    $db = getDBConnection();

    // load vendor
    $stmt = $db->prepare("SELECT id, email, phone FROM vendors WHERE id = ? LIMIT 1");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) throw new Exception('Vendor not found');

    // simple rate-limit: prevent resend if credential notification sent in last 1 minute
    $rlStmt = $db->prepare("SELECT id FROM vendor_notifications WHERE vendor_id = ? AND notification_type = 'credentials' AND created_at > (NOW() - INTERVAL 1 MINUTE) LIMIT 1");
    $rlStmt->execute([$vendorId]);
    if ($rlStmt->rowCount() > 0) {
        throw new Exception('Credentials were recently sent. Please wait a minute before resending.');
    }

    // enforce 3-send rule: first send (on create) + two manual resends without admin auth
    $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendor_notifications WHERE vendor_id = ? AND notification_type = 'credentials'");
    $countStmt->execute([$vendorId]);
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $sentCount = intval($countRow['cnt'] ?? 0);

    $adminPassword = $_POST['admin_password'] ?? null;
    if ($sentCount >= 3 && empty($adminPassword)) {
        // indicate to client that admin authentication is required
        http_response_code(401);
        echo json_encode(['success' => false, 'auth_required' => true, 'message' => 'Admin authentication required to resend credentials.']);
        exit;
    }

    // if admin password provided, verify it
    $adminAuthenticated = false;
    if (!empty($adminPassword)) {
        if (!isset($_SESSION['admin_id'])) {
            throw new Exception('Admin session missing');
        }
        $adminStmt = $db->prepare("SELECT id, password FROM users WHERE id = ? LIMIT 1");
        $adminStmt->execute([$_SESSION['admin_id']]);
        $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if (!$adminUser || !password_verify($adminPassword, $adminUser['password'])) {
            throw new Exception('Invalid admin password');
        }
        // admin authenticated; allow resend even if sentCount >=3
        $adminAuthenticated = true;
    }

    // generate temporary password and update vendor record
    $tempPassword = bin2hex(random_bytes(6)); // 12 hex chars
    $hashed = password_hash($tempPassword, PASSWORD_BCRYPT);
    $up = $db->prepare("UPDATE vendors SET password = ?, updated_at = NOW() WHERE id = ?");
    $up->execute([$hashed, $vendorId]);

    // prepare send
    $emailSent = false; $smsSent = false; $emailError = ''; $smsResponse = null;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $loginUrl = $scheme . '://' . $host . '/vendor-panel/login.php';

    if (!empty($vendor['email']) && filter_var($vendor['email'], FILTER_VALIDATE_EMAIL)) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Load full SMTP settings from database
            $smtpStmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption')");
            $smtpStmt->execute();
            $smtpSettings = $smtpStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $smtp_host = $smtpSettings['smtp_host'] ?? 'smtp.gmail.com';
            $smtp_port = $smtpSettings['smtp_port'] ?? 587;
            $smtp_username = $smtpSettings['smtp_username'] ?? '';
            $smtp_password = $smtpSettings['smtp_password'] ?? '';
            $smtp_encryption = $smtpSettings['smtp_encryption'] ?? 'tls';

            // Configure PHPMailer to use SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = ($smtp_encryption === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_port;

            // Windows/XAMPP SSL settings
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Timeout and basic settings
            $mail->Timeout = 30;
            $fromAddress = !empty($smtp_username) ? $smtp_username : 'phooldelivery@phool-delivery.local';
            $mail->setFrom($fromAddress, 'Phool Delivery');
            $mail->addAddress($vendor['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Your vendor account - credentials reset';
            $body  = "<p>Hi,</p>";
            $body .= "<p>A temporary password has been generated for your vendor account. Use the details below to login and change your password immediately.</p>";
            $body .= "<p><strong>Username:</strong> " . htmlspecialchars($vendor['email']) . "<br><strong>Temporary Password:</strong> " . htmlspecialchars($tempPassword) . "</p>";
            $body .= "<p>Login: <a href=\"" . htmlspecialchars($loginUrl) . "\">" . htmlspecialchars($loginUrl) . "</a></p>";
            $mail->Body = $body;

            // send
            $mail->send();
            $emailSent = true;
        } catch (Exception $ex) {
            $emailError = $mail->ErrorInfo ?? $ex->getMessage();
            error_log('Resend creds email error: ' . $emailError);
        }
    }

    if (!$emailSent && !empty($vendor['phone'])) {
        try {
            require_once __DIR__ . '/../../../app/services/SparrowSMSService.php';
            $smsService = new SparrowSMSService($db);
            $phoneFormatted = $vendor['phone'];
            if (strpos($phoneFormatted, '977') !== 0) $phoneFormatted = '977' . $phoneFormatted;
            $smsMsg = "Your vendor account: Username: " . ($vendor['email'] ?? $vendor['phone']) . " Temporary password: " . $tempPassword . " Login: " . $loginUrl;
            $smsResponse = $smsService->sendSMS($phoneFormatted, $smsMsg);
            $smsSent = !empty($smsResponse['success']);
        } catch (Exception $sx) {
            error_log('Resend creds SMS error: ' . $sx->getMessage());
        }
    }

    // log
    try {
        $log = $db->prepare("INSERT INTO vendor_notifications (vendor_id, notification_type, title, message, data, performed_by_admin, is_read, created_at) VALUES (?, 'credentials', ?, ?, ?, ?, 0, NOW())");
            if ($emailSent) {
                $title = 'Temporary password emailed';
                $msg = 'Temporary password emailed to ' . $vendor['email'];
                $dataArr = ['method' => 'email', 'to' => $vendor['email']];
                $performedBy = $adminAuthenticated ? $_SESSION['admin_id'] : null;
                $data = json_encode($dataArr);
                $log->execute([$vendorId, $title, $msg, $data, $performedBy]);
            echo json_encode(['success' => true, 'message' => 'Temporary password emailed to vendor.']);
            exit;
        } elseif ($smsSent) {
            $title = 'Temporary password sent via SMS';
            $msg = 'Temporary password sent via SMS to ' . $phoneFormatted;
            $dataArr = ['method' => 'sms', 'to' => $phoneFormatted, 'response' => $smsResponse];
            $performedBy = $adminAuthenticated ? $_SESSION['admin_id'] : null;
            $data = json_encode($dataArr);
            $log->execute([$vendorId, $title, $msg, $data, $performedBy]);
            echo json_encode(['success' => true, 'message' => 'Temporary password sent via SMS to vendor.']);
            exit;
        } else {
            $title = 'Temporary password delivery failed';
            $emailErrText = !empty($emailError) ? $emailError : 'none';
            $smsRespText = !empty($smsResponse) ? (is_string($smsResponse) ? $smsResponse : json_encode($smsResponse)) : 'none';
            $msg = 'Failed to deliver temporary password via email or SMS. Email error: ' . $emailErrText . '. SMS response: ' . $smsRespText;
            $dataArr = ['email_error' => $emailError, 'sms_response' => $smsResponse];
            $performedBy = $adminAuthenticated ? $_SESSION['admin_id'] : null;
            $data = json_encode($dataArr);
            $log->execute([$vendorId, $title, $msg, $data, $performedBy]);
            error_log('Resend creds failure for vendor_id=' . $vendorId . ' | email_error=' . $emailErrText . ' | sms_response=' . $smsRespText);
            throw new Exception('Could not deliver temporary password (email & SMS failed)');
        }
    } catch (Exception $le) {
        error_log('Resend log error: ' . $le->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $le->getMessage()]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

?>
