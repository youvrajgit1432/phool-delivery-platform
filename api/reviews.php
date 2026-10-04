<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../app/bootstrap/app.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// GET: fetch reviews for a product
if ($method === 'GET') {
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    if (!$product_id) jsonResponse(['success' => false, 'message' => 'product_id required']);

    $stmt = $db->prepare("SELECT r.*, c.name as customer_name FROM reviews r LEFT JOIN customers c ON r.user_id = c.id WHERE r.product_id = :pid AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT :lim");
    $stmt->bindValue(':pid', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'reviews' => $reviews]);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// POST actions: submit, vote, comment
if ($method === 'POST') {
    $action = isset($input['action']) ? $input['action'] : ($input['action'] ?? null);
    if (!$action) jsonResponse(['success' => false, 'message' => 'action required']);

    if ($action === 'submit') {
        $product_id = (int)($input['product_id'] ?? 0);
        $rating = (int)($input['rating'] ?? 0);
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $user_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;

        if (!$product_id || $rating < 1 || $rating > 5 || !$content) {
            jsonResponse(['success' => false, 'message' => 'invalid input']);
        }

        $stmt = $db->prepare("INSERT INTO reviews (user_id, guest_name, guest_email, product_id, rating, title, content, status, created_at, updated_at) VALUES (:uid, :gname, :gemail, :pid, :rating, :title, :content, :status, NOW(), NOW())");
        $gname = $input['guest_name'] ?? null;
        $gemail = $input['guest_email'] ?? null;
        $status = isset($input['auto_approve']) && $input['auto_approve'] ? 'approved' : 'pending';
        $stmt->execute([':uid' => $user_id, ':gname' => $gname, ':gemail' => $gemail, ':pid' => $product_id, ':rating' => $rating, ':title' => $title, ':content' => $content, ':status' => $status]);

        jsonResponse(['success' => true, 'message' => 'review_submitted']);
    }

    if ($action === 'vote') {
        $review_id = (int)($input['review_id'] ?? 0);
        $reaction = ($input['reaction'] === 'dislike') ? 'dislike' : 'like';
        if (!$review_id) jsonResponse(['success' => false, 'message' => 'review_id required']);

        // Prevent duplicate votes for logged in users
        $user_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
        $guest_email = $input['guest_email'] ?? null;

        $checkStmt = $db->prepare("SELECT id FROM review_likes WHERE review_id = :rid AND (user_id = :uid OR guest_email = :gemail) LIMIT 1");
        $checkStmt->execute([':rid' => $review_id, ':uid' => $user_id, ':gemail' => $guest_email]);
        if ($checkStmt->fetch()) jsonResponse(['success' => false, 'message' => 'already_voted']);

        $ins = $db->prepare("INSERT INTO review_likes (review_id, user_id, guest_email, reaction, created_at) VALUES (:rid, :uid, :gemail, :reaction, NOW())");
        $ins->execute([':rid' => $review_id, ':uid' => $user_id, ':gemail' => $guest_email, ':reaction' => $reaction]);

        // update counts
        if ($reaction === 'like') {
            $db->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = :rid")->execute([':rid' => $review_id]);
        } else {
            $db->prepare("UPDATE reviews SET unhelpful_count = unhelpful_count + 1 WHERE id = :rid")->execute([':rid' => $review_id]);
        }

        jsonResponse(['success' => true, 'message' => 'voted']);
    }

    if ($action === 'comment') {
        $review_id = (int)($input['review_id'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        if (!$review_id || !$comment) jsonResponse(['success' => false, 'message' => 'invalid input']);

        $user_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
        $is_admin = isset($_SESSION['admin_id']) ? 1 : 0;
        $admin_id = $_SESSION['admin_id'] ?? null;

        $ins = $db->prepare("INSERT INTO review_comments (review_id, user_id, comment_by_admin, admin_id, comment_text, status, created_at) VALUES (:rid, :uid, :cbyadmin, :adminid, :text, :status, NOW())");
        $status = $is_admin ? 'approved' : 'pending';
        $ins->execute([':rid' => $review_id, ':uid' => $user_id, ':cbyadmin' => $is_admin, ':adminid' => $admin_id, ':text' => $comment, ':status' => $status]);

        jsonResponse(['success' => true, 'message' => 'comment_added']);
    }

    jsonResponse(['success' => false, 'message' => 'unknown_action']);
}

jsonResponse(['success' => false, 'message' => 'unsupported_method']);
