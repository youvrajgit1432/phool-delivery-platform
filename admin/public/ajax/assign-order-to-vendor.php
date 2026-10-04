<?php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

requireAuth();
header('Content-Type: application/json');

try {
    $rawOrderInput = trim($_POST['order_id'] ?? '');
    $orderId = intval($rawOrderInput);
    $vendorId = intval($_POST['vendor_id'] ?? 0);
    if (empty($rawOrderInput) || $vendorId <= 0) throw new Exception('Invalid order or vendor');

    $pdo = getDBConnection();

    // load order: prefer numeric id lookup, otherwise try order_number
    if (ctype_digit($rawOrderInput) && intval($rawOrderInput) > 0) {
        $oStmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ? LIMIT 1");
        $oStmt->execute([intval($rawOrderInput)]);
    } else {
        $oStmt = $pdo->prepare("SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.order_number = ? LIMIT 1");
        $oStmt->execute([$rawOrderInput]);
    }
    $orderRow = $oStmt->fetch(PDO::FETCH_ASSOC);
    if (!$orderRow) {
        // Diagnostic: return helpful info about what's in POST and DB to aid debugging
        error_log('assign-order-to-vendor: order not found. POST=' . json_encode($_POST));
        $checkById = null;
        $checkByNumber = null;
        try {
            if (ctype_digit($rawOrderInput)) {
                $q = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE id = ? LIMIT 1");
                $q->execute([intval($rawOrderInput)]);
                $checkById = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $q2 = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE order_number = ? LIMIT 1");
            $q2->execute([$rawOrderInput]);
            $checkByNumber = $q2->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $xx) {
            // ignore
        }

        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found',
            'debug' => [
                'posted' => $_POST,
                'checked_by_id' => $checkById,
                'checked_by_order_number' => $checkByNumber
            ]
        ]);
        exit;
    }

    // normalize actual order id for later use
    $actualOrderId = intval($orderRow['id']);

    // Validate: order must be confirmed before assignment
    if ($orderRow['status'] !== 'confirmed') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Order must be confirmed before assignment. Current status: ' . ucfirst($orderRow['status'])
        ]);
        exit;
    }

    // Prevent duplicate / multiple assignments:
    // - If order already assigned or accepted by a vendor, disallow new assignments
    // - Only allow new assignment when assign_status is 'unassigned' or 'rejected'
    $currentAssignStatus = strtolower(trim($orderRow['assign_status'] ?? 'unassigned'));
    $currentAssignedVendor = intval($orderRow['assigned_vendor_id'] ?? 0);

    if (in_array($currentAssignStatus, ['assigned', 'accepted'])) {
        // If already assigned and it's to the same vendor, return explicit message
        if ($currentAssignedVendor > 0 && $currentAssignedVendor === $vendorId) {
            // log audit
            try {
                $adminId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
                $vname = null;
                $vn = $pdo->prepare("SELECT store_name FROM vendors WHERE id = ? LIMIT 1");
                $vn->execute([$vendorId]);
                $vrow = $vn->fetch(PDO::FETCH_ASSOC);
                if ($vrow) $vname = $vrow['store_name'];
                $aud = $pdo->prepare("INSERT INTO assignment_audit (order_id, attempted_vendor_id, attempted_vendor_name, attempted_admin_id, reason, details, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $aud->execute([$actualOrderId, $vendorId, $vname, $adminId, 'already_assigned_same_vendor', json_encode(['current_assign_status' => $currentAssignStatus])]);
            } catch (Exception $logEx) {
                // ignore if table missing
            }
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Order is already assigned to the selected vendor.']);
            exit;
        }

        // Otherwise, it's assigned to some vendor and cannot be reassigned unless rejected
        // log audit
        try {
            $adminId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
            $assignedVendorName = null;
            if ($currentAssignedVendor > 0) {
                $vn = $pdo->prepare("SELECT store_name FROM vendors WHERE id = ? LIMIT 1");
                $vn->execute([$currentAssignedVendor]);
                $vrow = $vn->fetch(PDO::FETCH_ASSOC);
                if ($vrow) $assignedVendorName = $vrow['store_name'];
            }
            $aud = $pdo->prepare("INSERT INTO assignment_audit (order_id, attempted_vendor_id, attempted_vendor_name, attempted_admin_id, reason, details, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $aud->execute([$actualOrderId, $vendorId, null, $adminId, 'already_assigned_to_other_vendor', json_encode(['current_assigned_vendor_id' => $currentAssignedVendor, 'current_assigned_vendor_name' => $assignedVendorName, 'current_assign_status' => $currentAssignStatus])] );
        } catch (Exception $logEx) {
            // ignore if table missing
        }
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Order is already assigned to a vendor. Only rejected orders can be reassigned.']);
        exit;
    }

    // compute totals and fetch product info (use actual order id from found row)
    // order_items may not store product_name; join products table and use name_en from products
    $itemsStmt = $pdo->prepare(
        "SELECT oi.product_id, COALESCE(p.name_en, 'Unknown Product') AS product_name, oi.quantity, oi.unit_price " .
        "FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?"
    );
    $itemsStmt->execute([$actualOrderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    $total_items = 0; $subtotal = 0.0;
    foreach ($items as $it) { $total_items += floatval($it['quantity']); $subtotal += floatval($it['quantity']) * floatval($it['unit_price']); }

    // SERVER-SIDE VALIDATION: ensure selected vendor actually supplies required products and has availability/stock
    // Build product list
    $productIds = array_map(function($i){ return intval($i['product_id']); }, $items);
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $checkSql = "SELECT product_id, vendor_stock, is_available, status, minimum_order_quantity FROM vendor_product_map WHERE vendor_id = ? AND product_id IN ($placeholders)";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute(array_merge([$vendorId], $productIds));
        $vendorMaps = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        $mapByProduct = [];
        foreach ($vendorMaps as $m) { $mapByProduct[intval($m['product_id'])] = $m; }

        $problems = [];
        foreach ($items as $it) {
            $pid = intval($it['product_id']);
            $needQty = floatval($it['quantity']);
            if (!isset($mapByProduct[$pid])) {
                $problems[] = ['product_id'=>$pid, 'product_name'=>$it['product_name'], 'issue'=>'not_listed'];
                continue;
            }
            $m = $mapByProduct[$pid];
            if (intval($m['is_available']) !== 1 || ($m['status'] ?? '') !== 'active') {
                $problems[] = ['product_id'=>$pid, 'product_name'=>$it['product_name'], 'issue'=>'not_available'];
                continue;
            }
            $stock = intval($m['vendor_stock']);
            $moq = floatval($m['minimum_order_quantity'] ?? 1);
            if ($needQty < $moq) {
                $problems[] = ['product_id'=>$pid, 'product_name'=>$it['product_name'], 'issue'=>'below_minimum_order_quantity', 'need' => $needQty, 'moq' => $moq];
                continue;
            }
            if ($stock < $needQty) {
                $problems[] = ['product_id'=>$pid, 'product_name'=>$it['product_name'], 'issue'=>'insufficient_stock', 'need' => $needQty, 'stock' => $stock];
                continue;
            }
        }

        if (!empty($problems)) {
            http_response_code(400);
            echo json_encode(['success'=>false, 'message'=>'Selected vendor cannot fulfill the order items', 'issues'=>$problems]);
            exit;
        }
    }

    // allow admin override from form
    $override_total_items = intval($_POST['total_items'] ?? 0);
    if ($override_total_items > 0) {
        $total_items = $override_total_items;
    }
    $assign_delivery_date = trim($_POST['delivery_date'] ?? '');
    $assign_notes = trim($_POST['assign_notes'] ?? '');

    $assignedAt = date('Y-m-d H:i:s');
    $customer_name = $orderRow['customer_name'] ?? $orderRow['name'] ?? '';
    $customer_phone = $orderRow['customer_phone'] ?? $orderRow['phone'] ?? '';
    $customer_address = $orderRow['customer_address'] ?? '';
    $order_number = $orderRow['order_number'] ?? $orderRow['id'];

    $delivery_fee = $orderRow['delivery_fee'] ?? 0;
    $discount = $orderRow['applied_discount'] ?? 0;
    $total_amount = $orderRow['final_amount'] ?? $orderRow['total_amount'] ?? $subtotal;
    $payment_method = $orderRow['payment_method'] ?? '';
    $payment_status = $orderRow['payment_status'] ?? '';

    // insert into vendor_orders
    // combine notes: existing order notes + admin notes + delivery date meta
    $combinedNotes = $orderRow['notes'] ?? '';
    if (!empty($assign_notes)) {
        $combinedNotes .= "\nAdmin Note: " . $assign_notes;
    }
    if (!empty($assign_delivery_date)) {
        $combinedNotes .= "\nRequested Delivery: " . $assign_delivery_date;
    }

    // Avoid duplicate vendor_orders entries for same order/vendor when active
    $dupCheck = $pdo->prepare("SELECT id, status FROM vendor_orders WHERE order_id = ? AND vendor_id = ? LIMIT 1");
    $dupCheck->execute([$actualOrderId, $vendorId]);
    $existingVendorOrder = $dupCheck->fetch(PDO::FETCH_ASSOC);
    if ($existingVendorOrder) {
        $existingStatus = strtolower($existingVendorOrder['status'] ?? '');
        if (!in_array($existingStatus, ['rejected', 'cancelled', 'completed'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This order has already been sent to the selected vendor and is still active.']);
            exit;
        }
        // If existing vendor_order is rejected/cancelled/completed we allow creating a new vendor_order record
    }

    $ins = $pdo->prepare("INSERT INTO vendor_orders (vendor_id, order_id, order_number, customer_name, customer_phone, customer_address, total_items, subtotal, delivery_fee, discount, total_amount, payment_method, payment_status, status, notes, assigned_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?)");
    $ins->execute([$vendorId, $actualOrderId, $order_number, $customer_name, $customer_phone, $customer_address, $total_items, $subtotal, $delivery_fee, $discount, $total_amount, $payment_method, $payment_status, $combinedNotes, $assignedAt]);

    $vendorOrderId = $pdo->lastInsertId();

    // copy order_items into vendor_order_items for vendor panel (preserve quantities and product info)
    // Note: use NULL for product_id since vendor_order_items references vendor_products, not main products
    if ($vendorOrderId) {
        $copyStmt = $pdo->prepare("INSERT INTO vendor_order_items (vendor_order_id, product_id, product_name, quantity, unit_price, item_total) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $it) {
            $item_total = floatval($it['quantity']) * floatval($it['unit_price']);
            // Pass NULL for product_id since we're copying from main orders, not vendor inventory
            $copyStmt->execute([$vendorOrderId, null, $it['product_name'] ?? 'Unknown', intval($it['quantity']), $it['unit_price'] ?? 0, $item_total]);
        }
    }

    // vendor notification
    $notif = $pdo->prepare("INSERT INTO vendor_notifications (vendor_id, notification_type, title, message, data, is_read, created_at) VALUES (?, 'order_assigned', ?, ?, ?, 0, NOW())");
    $title = 'New Order Assigned: ' . $order_number;
    $message = 'You have been assigned order ' . $order_number . '. Please check your vendor panel to accept.';
    $data = json_encode(['order_id' => $actualOrderId, 'order_number' => $order_number, 'amount' => $total_amount]);
    $notif->execute([$vendorId, $title, $message, $data]);

    // update main orders table: ONLY update assignment columns, NOT the main status
    // This keeps the order in its original status (confirmed, pending, etc) so it remains visible in filters
    try {
        $uMain = $pdo->prepare("UPDATE orders SET assigned_vendor_id = ?, assigned_at = NOW(), assign_status = 'assigned', updated_at = NOW() WHERE id = ?");
        $uMain->execute([$vendorId, $actualOrderId]);
    } catch (Exception $e) {
        // ignore - assignment columns may not exist yet
        error_log('assign-order-to-vendor: Could not update assignment columns: ' . $e->getMessage());
    }

    // best-effort notify vendor via email and SMS
    try {
        $vStmt = $pdo->prepare("SELECT id, store_name, email, phone FROM vendors WHERE id = ? LIMIT 1");
        $vStmt->execute([$vendorId]);
        $vendorRow = $vStmt->fetch(PDO::FETCH_ASSOC);
        if ($vendorRow) {
            // email
            if (!empty($vendorRow['email']) && filter_var($vendorRow['email'], FILTER_VALIDATE_EMAIL)) {
                try {
                    require_once __DIR__ . '/../../../vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $smtpStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption')");
                    $smtpStmt->execute();
                    $smtpSettings = $smtpStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    $smtp_host = $smtpSettings['smtp_host'] ?? 'smtp.gmail.com';
                    $smtp_port = $smtpSettings['smtp_port'] ?? 587;
                    $smtp_username = $smtpSettings['smtp_username'] ?? '';
                    $smtp_password = $smtpSettings['smtp_password'] ?? '';
                    $smtp_encryption = $smtpSettings['smtp_encryption'] ?? 'tls';
                    $mail->isSMTP(); $mail->Host = $smtp_host; $mail->SMTPAuth = true; $mail->Username = $smtp_username; $mail->Password = $smtp_password;
                    $mail->SMTPSecure = ($smtp_encryption === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $smtp_port;
                    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                    $fromAddress = !empty($smtp_username) ? $smtp_username : 'no-reply@phool-delivery.local';
                    $mail->setFrom($fromAddress, 'Phool Delivery');
                    $mail->addAddress($vendorRow['email'], $vendorRow['store_name']);
                    $mail->isHTML(true);
                    $mail->Subject = 'New Order Assigned: ' . $order_number;
                    $body = '<p>Hi ' . htmlspecialchars($vendorRow['store_name']) . ',</p>';
                    $body .= '<p>You have been assigned a new order <strong>' . htmlspecialchars($order_number) . '</strong>. Total: Rs. ' . htmlspecialchars(number_format($total_amount,2)) . '.</p>';
                    $body .= '<p>Please login to your vendor panel to accept and start preparing the order.</p>';
                    $mail->Body = $body;
                    $mail->send();
                } catch (Exception $ex) {
                    error_log('Assign email failed: ' . ($mail->ErrorInfo ?? $ex->getMessage()));
                }
            }

            // SMS
            if (!empty($vendorRow['phone'])) {
                try {
                    require_once __DIR__ . '/../../../app/services/SparrowSMSService.php';
                    $sms = new SparrowSMSService($pdo);
                    $phoneFormatted = $vendorRow['phone']; if (strpos($phoneFormatted, '977') !== 0) $phoneFormatted = '977' . $phoneFormatted;
                    $smsMsg = 'New order assigned: ' . $order_number . '. Total: Rs. ' . number_format($total_amount,2) . '. Please check vendor panel.';
                    $smsResp = $sms->sendSMS($phoneFormatted, $smsMsg);
                    if (empty($smsResp['success'])) error_log('Assign SMS failed: ' . ($smsResp['message'] ?? json_encode($smsResp)));
                } catch (Exception $sx) {
                    error_log('Assign SMS exception: ' . $sx->getMessage());
                }
            }
        }
    } catch (Exception $notifyEx) {
        error_log('Assign notify error: ' . $notifyEx->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Order assigned to vendor successfully.']);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
