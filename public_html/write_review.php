<?php
// public_html/write_review.php
require_once __DIR__ . '/../app/bootstrap/app.php';

session_start();
LanguageHelper::initialize();

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if ($product_id <= 0) {
    header('Location: /');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$productStmt = $db->prepare("SELECT id, name FROM products WHERE id = :id LIMIT 1");
$productStmt->execute([':id' => $product_id]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: /');
    exit;
}

$is_logged_in = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
$customer = null;
if ($is_logged_in) {
    $custStmt = $db->prepare("SELECT id, name, email, phone FROM customers WHERE id = :id LIMIT 1");
    $custStmt->execute([':id' => $_SESSION['customer_id']]);
    $customer = $custStmt->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($product['name']) ?> - <?= LanguageHelper::t('write_review', 'Write Review') ?></title>
    <style>
        body{font-family: Arial, Helvetica, sans-serif; padding:20px}
        .container{max-width:700px;margin:0 auto}
        .form-group{margin-bottom:12px}
        label{display:block;margin-bottom:6px}
        input[type=text], input[type=email], textarea, select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
        .btn{display:inline-block;padding:10px 16px;background:#007bff;color:#fff;border:none;border-radius:4px;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <h1><?= LanguageHelper::t('write_review_for', 'Write a review for') ?> <?= htmlspecialchars($product['name']) ?></h1>

    <form method="post" action="<?= $pathConfig->url('write_review_submit.php') ?>">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

        <?php if ($is_logged_in && $customer): ?>
            <input type="hidden" name="user_id" value="<?= $customer['id'] ?>">
            <div class="form-group">
                <label><?= LanguageHelper::t('name', 'Name') ?></label>
                <input type="text" name="guest_name" value="<?= htmlspecialchars($customer['name']) ?>" readonly>
            </div>
            <div class="form-group">
                <label><?= LanguageHelper::t('contact_email', 'Email') ?></label>
                <input type="email" name="guest_email" value="<?= htmlspecialchars($customer['email']) ?>">
            </div>
            <div class="form-group">
                <label><?= LanguageHelper::t('phone', 'Phone') ?></label>
                <input type="text" name="guest_contact" value="<?= htmlspecialchars($customer['phone']) ?>">
            </div>
        <?php else: ?>
            <div class="form-group">
                <label><?= LanguageHelper::t('name', 'Name') ?> (<?= LanguageHelper::t('required', 'required') ?>)</label>
                <input type="text" name="guest_name" required>
            </div>
            <div class="form-group">
                <label><?= LanguageHelper::t('contact_email', 'Email') ?> / <?= LanguageHelper::t('phone', 'Phone') ?> (<?= LanguageHelper::t('one_required', 'one required') ?>)</label>
                <input type="email" name="guest_email">
                <input type="text" name="guest_contact" placeholder="<?= LanguageHelper::t('phone', 'Phone') ?>">
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label><?= LanguageHelper::t('rating', 'Rating') ?> (1-5)</label>
            <select name="rating" required>
                <option value="5">5</option>
                <option value="4">4</option>
                <option value="3">3</option>
                <option value="2">2</option>
                <option value="1">1</option>
            </select>
        </div>

        <div class="form-group">
            <label><?= LanguageHelper::t('review_title', 'Review Title') ?></label>
            <input type="text" name="title" required>
        </div>

        <div class="form-group">
            <label><?= LanguageHelper::t('review_content', 'Review') ?></label>
            <textarea name="content" rows="6" required></textarea>
        </div>

        <div class="form-group">
            <button class="btn" type="submit"><?= LanguageHelper::t('submit_review', 'Submit Review') ?></button>
            <a class="btn" href="<?= $pathConfig->url('product/' . $product['id']) ?>" style="background:#6c757d; margin-left:8px"><?= LanguageHelper::t('cancel', 'Cancel') ?></a>
        </div>
    </form>
</div>
</body>
</html>
