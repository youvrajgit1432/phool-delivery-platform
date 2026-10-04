<?php
/**
 * AJAX endpoint for carousel form submission
 * Handles: adding and editing carousel images
 */

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error buffering
ob_start();

try {
    require_once '../../../bootstrap/app.php';
    require_once '../../../app/middleware/AuthMiddleware.php';

    // Check authentication
    requireAuth();

    // Get database connection
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $carousel_id = isset($_POST['carousel_id']) ? intval($_POST['carousel_id']) : 0;
    $main_category_id = intval($_POST['main_category_id']);
    $title_en = trim($_POST['title_en']);
    $title_ne = trim($_POST['title_ne']);
    $description_en = trim($_POST['description_en'] ?? '');
    $description_ne = trim($_POST['description_ne'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    // Validate inputs
    if (empty($main_category_id)) {
        throw new Exception('Please select a main category');
    }
    if (empty($title_en) || empty($title_ne)) {
        throw new Exception('Title in both languages is required');
    }

    $image_filename = null;

    // Handle image upload if provided
    if (isset($_FILES['carousel_image']) && $_FILES['carousel_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['carousel_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading image');
        }

        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($file_info, $_FILES['carousel_image']['tmp_name']);
        finfo_close($file_info);

        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid image format. Only JPEG, PNG, WebP, and GIF are allowed');
        }

        if ($_FILES['carousel_image']['size'] > 5242880) { // 5MB
            throw new Exception('Image size must not exceed 5MB');
        }

        $upload_dir = '../../../storage/uploads/carousel/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Delete old image if editing
        if ($carousel_id > 0) {
            $old_carousel_stmt = $pdo->prepare("SELECT image_path FROM carousel_images WHERE id = ?");
            $old_carousel_stmt->execute([$carousel_id]);
            $old_carousel = $old_carousel_stmt->fetch();

            if ($old_carousel && !empty($old_carousel['image_path'])) {
                $old_file_path = $upload_dir . $old_carousel['image_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
        }

        $file_extension = strtolower(pathinfo($_FILES['carousel_image']['name'], PATHINFO_EXTENSION));
        $image_filename = 'carousel_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $image_filename;

        if (!move_uploaded_file($_FILES['carousel_image']['tmp_name'], $target_path)) {
            throw new Exception('Failed to upload image');
        }
    }

    if ($carousel_id > 0) {
        // Update existing carousel
        if ($image_filename) {
            $stmt = $pdo->prepare("
                UPDATE carousel_images 
                SET main_category_id = ?, title_en = ?, title_ne = ?, 
                    description_en = ?, description_ne = ?, image_path = ?,
                    link_url = ?, sort_order = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $main_category_id, $title_en, $title_ne,
                $description_en, $description_ne, $image_filename,
                $link_url, $sort_order, $status, $carousel_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE carousel_images 
                SET main_category_id = ?, title_en = ?, title_ne = ?, 
                    description_en = ?, description_ne = ?,
                    link_url = ?, sort_order = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $main_category_id, $title_en, $title_ne,
                $description_en, $description_ne,
                $link_url, $sort_order, $status, $carousel_id
            ]);
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Carousel image updated successfully',
            'redirect' => 'carousel.php'
        ]);
    } else {
        // Insert new carousel - image is REQUIRED
        if (empty($image_filename)) {
            throw new Exception('Image is required for new carousel items');
        }

        $stmt = $pdo->prepare("
            INSERT INTO carousel_images 
            (main_category_id, title_en, title_ne, description_en, description_ne, 
             image_path, link_url, sort_order, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $main_category_id, $title_en, $title_ne,
            $description_en, $description_ne,
            $image_filename, $link_url, $sort_order, $status
        ]);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Carousel image created successfully',
            'redirect' => 'carousel.php'
        ]);
    }

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
