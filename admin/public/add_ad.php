<?php
require_once '../bootstrap/app.php';
require_once '../app/middleware/AuthMiddleware.php';
requireAuth();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $media_type = $_POST['media_type'] ?? '';
    $upload_success = true;
    
    // Validate media type
    if (empty($media_type) || !in_array($media_type, ['photo', 'video'])) {
        $_SESSION['error_message'] = "Please select a valid media type (photo or video).";
        header("Location: ads.php");
        exit();
    }
    
    // Check if a file was uploaded
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error_message'] = "Please select a file to upload.";
        header("Location: ads.php");
        exit();
    }
    
    $upload_dir = '../storage/uploads/ads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Define allowed file types and size limits
    $allowed_photo_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_video_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/mkv', 'video/webm', 'video/iveo'];
    $max_file_size = 500 * 1024 * 1024; // 500MB
    
    $file = $_FILES['media_file'];
    $file_type = $file['type'];
    $file_size = $file['size'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type based on media type selection
    if ($media_type === 'photo' && !in_array($file_type, $allowed_photo_types)) {
        $_SESSION['error_message'] = "Invalid photo format. Only JPG, PNG, GIF, and WEBP are allowed.";
        header("Location: ads.php");
        exit();
    } elseif ($media_type === 'video') {
        // Check both MIME type and file extension for videos
        $is_allowed_video = in_array($file_type, $allowed_video_types) || 
                           in_array($file_extension, ['mp4', 'mpeg', 'mov', 'avi', 'mkv', 'webm', 'iveo']);
        
        if (!$is_allowed_video) {
            $_SESSION['error_message'] = "Invalid video format. Allowed formats: MP4, MPEG, MOV, AVI, MKV, WEBM, IVEO.";
            header("Location: ads.php");
            exit();
        }
    }
    
    // Validate file size
    if ($file_size > $max_file_size) {
        $_SESSION['error_message'] = "File size too large. Maximum size is 500MB.";
        header("Location: ads.php");
        exit();
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive in HTML form",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension"
        ];
        $_SESSION['error_message'] = $upload_errors[$file['error']] ?? "Unknown upload error";
        header("Location: ads.php");
        exit();
    }
    
    // Generate unique filename
    $file_name = 'ad_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        try {
            // Insert new ad as inactive by default
            $stmt = $pdo->prepare("INSERT INTO ads (title, description, media_type, file_name, file_path, status, file_size) VALUES (?, ?, ?, ?, ?, 'inactive', ?)");
            $stmt->execute([$title, $description, $media_type, $file_name, $file_path, $file_size]);
            
            $_SESSION['success_message'] = "Ad uploaded successfully! The ad has been added as inactive. You can activate it from the ads list.";
            header("Location: ads.php");
            exit();
        } catch (PDOException $e) {
            // Delete the uploaded file if database operation failed
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            header("Location: ads.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Failed to upload file. Please try again.";
        header("Location: ads.php");
        exit();
    }
} else {
    header("Location: ads.php");
    exit();
}
?>