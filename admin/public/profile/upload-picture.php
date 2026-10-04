<?php
// public/profile/upload-picture.php
require_once '../../bootstrap/app.php';
require_once '../../app/middleware/AuthMiddleware.php';

// Check authentication
requireAuth();

// Initialize database
$pdo = getDBConnection();

// Get current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_user = $stmt->fetch();

// Handle file upload
function handleProfilePictureUpload() {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file = $_FILES['profile_picture'];
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Only JPG, PNG, and GIF images are allowed.");
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        throw new Exception("Image size must be less than 2MB.");
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $_SESSION['admin_id'] . '_' . time() . '.' . $extension;
    $upload_path = '../../storage/uploads/profiles/' . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists('../../storage/uploads/profiles')) {
        mkdir('../../storage/uploads/profiles', 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $filename;
    }
    
    throw new Exception("Failed to upload image.");
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $profile_picture = $current_user['profile_picture'];
        $new_profile_picture = handleProfilePictureUpload();
        
        if ($new_profile_picture) {
            // Delete old profile picture if exists
            if ($profile_picture && file_exists('../../storage/uploads/profiles/' . $profile_picture)) {
                unlink('../../storage/uploads/profiles/' . $profile_picture);
            }
            $profile_picture = $new_profile_picture;
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $success = $stmt->execute([$profile_picture, $_SESSION['admin_id']]);
            
            if ($success) {
                $_SESSION['success_message'] = "Profile picture updated successfully!";
                header("Location: ../profile.php");
                exit;
            } else {
                throw new Exception("Failed to update profile picture in database.");
            }
        } else {
            throw new Exception("Please select a valid image file.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Set page title
$page_title = "Upload Profile Picture - Phool Delivery Admin";

// Include header
include '../../app/views/layouts/hheader.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Upload Profile Picture</h1>
    <a href="../profile.php" class="btn btn-secondary">Back to Profile</a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Current Picture</h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($current_user['profile_picture'])): ?>
                <img src="../../storage/uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                     alt="Profile Picture" class="rounded-circle mb-3" style="width: 200px; height: 200px; object-fit: cover;">
                <?php else: ?>
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto" 
                     style="width: 200px; height: 200px;">
                    <i class="fas fa-user fa-4x text-secondary"></i>
                </div>
                <p class="text-muted">No profile picture set</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload New Picture</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Upload Picture</button>
                        <a href="../profile.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../app/views/layouts/footer.php';
?>