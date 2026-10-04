<?php
require_once '../../bootstrap/app.php';
header('Content-Type: application/json');

if (!isset($_GET['parent_id'])) {
    echo json_encode([]);
    exit;
}

$parent_id = intval($_GET['parent_id']);
$pdo = getDBConnection();

$query = "SELECT id, name_en, name_ne FROM categories WHERE parent_id = ? AND status = 'active' AND is_product_allowed = 1 ORDER BY name_en";
$stmt = $pdo->prepare($query);
$stmt->execute([$parent_id]);
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subcategories);
?>