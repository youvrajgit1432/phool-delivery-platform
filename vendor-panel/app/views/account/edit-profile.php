<?php
/**
 * Vendor Account Settings - Profile Image & Gallery Management
 * Path: /phool-delivery-platform/vendor-panel/app/views/account/edit-profile.php
 */

session_start();

if (!isset($_SESSION['vendor_id'])) {
    header('Location: /phool-delivery-platform/vendor-panel/public/login.php');
    exit;
}

require_once '../../../config/Database.php';

$db = new Database();
$conn = $db->connect();
$vendor_id = $_SESSION['vendor_id'];

// Get vendor details
$stmt = $conn->prepare('SELECT * FROM vendors WHERE id = ?');
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    echo '<div class="alert alert-danger">Vendor not found</div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Vendor Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .profile-header {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .profile-image-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid #4CAF50;
            display: block;
        }
        
        .image-upload-area {
            border: 2px dashed #4CAF50;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9f9f9;
        }
        
        .image-upload-area:hover {
            background: #f0f0f0;
            border-color: #45a049;
        }
        
        .image-upload-area.dragover {
            background: #e8f5e9;
            border-color: #4CAF50;
        }
        
        #profileImageInput {
            display: none;
        }
        
        .form-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .btn-save {
            background: #4CAF50;
            color: white;
            padding: 10px 25px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-save:hover {
            background: #45a049;
            color: white;
        }
        
        .alert {
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        
        .gallery-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f0f0;
            aspect-ratio: 1;
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-item-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            padding: 10px;
            display: flex;
            gap: 5px;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .gallery-item:hover .gallery-item-actions {
            opacity: 1;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
    </style>
</head>
<body style="background: #f5f5f5;">
    <div class="profile-container">
        <!-- Header -->
        <div style="margin-bottom: 30px;">
            <a href="<?php echo htmlspecialchars(vendor_url('/dashboard')); ?>" class="btn btn-secondary btn-sm mb-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <h2><i class="fas fa-user-circle me-2"></i>Edit Profile</h2>
            <p class="text-muted">Manage your vendor profile information</p>
        </div>

        <!-- Profile Image Section -->
        <div class="profile-header">
            <div class="profile-image-section">
                <h4>Profile Picture</h4>
                <?php if (!empty($vendor['profile_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars(get_image_url($vendor['profile_image_url'])); ?>" alt="Profile" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar" style="background: #e0e0e0; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user" style="font-size: 60px; color: #999;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="image-upload-area" onclick="document.getElementById('profileImageInput').click();" 
                     ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #4CAF50; margin-bottom: 10px; display: block;"></i>
                    <p><strong>Click to upload or drag and drop</strong></p>
                    <p style="font-size: 12px; color: #999; margin: 0;">JPG, PNG, GIF up to 5MB</p>
                </div>
                
                <input type="file" id="profileImageInput" accept="image/*">
            </div>
        </div>

        <!-- Basic Information -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle me-2"></i>Basic Information</h3>
            <form id="profileForm" method="POST" action="<?php echo htmlspecialchars(vendor_url('/ajax/update-profile')); ?>">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($vendor['first_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($vendor['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Store Name</label>
                    <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Store Description</label>
                    <textarea class="form-control" name="store_description" rows="4"><?php echo htmlspecialchars($vendor['store_description'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </form>
        </div>

        <!-- Address Information -->
        <div class="form-section">
            <h3><i class="fas fa-map-marker-alt me-2"></i>Address Information</h3>
            <form>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($vendor['state'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Business Address</label>
                    <textarea class="form-control" rows="3" disabled><?php echo htmlspecialchars($vendor['business_address'] ?? ''); ?></textarea>
                </div>
                
                <p class="text-muted small">Address information can be changed by contacting admin support.</p>
            </form>
        </div>
    </div>

    <script>
    // Profile image upload
    const profileImageInput = document.getElementById('profileImageInput');
    const imageUploadArea = document.querySelector('.image-upload-area');

    profileImageInput.addEventListener('change', function(e) {
        handleProfileImageUpload(e.target.files[0]);
    });

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.querySelector('.image-upload-area').classList.add('dragover');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.querySelector('.image-upload-area').classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.querySelector('.image-upload-area').classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleProfileImageUpload(files[0]);
        }
    }

    function handleProfileImageUpload(file) {
        if (!file.type.startsWith('image/')) {
            showToast('Please select an image file', 'warning');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showToast('File size exceeds 5MB limit', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('profile_image', file);

        const uploadArea = document.querySelector('.image-upload-area');
        uploadArea.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #4CAF50; margin-bottom: 10px; display: block;"></i><p>Uploading...</p>';

        fetch('<?php echo htmlspecialchars(vendor_url('/ajax/upload-vendor-profile-image')); ?>', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const text = await response.text();
            if (!response.ok) {
                console.error('Response error:', text);
                throw new Error(text || 'Network error');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', text);
                throw new Error('Invalid JSON response: ' + text);
            }
        })
        .then(data => {
            if (data.success) {
                showToast('Profile image uploaded successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + (data.message || 'Upload failed'), 'error');
                uploadArea.innerHTML = '<i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #4CAF50; margin-bottom: 10px; display: block;"></i><p><strong>Click to upload or drag and drop</strong></p><p style="font-size: 12px; color: #999; margin: 0;">JPG, PNG, GIF up to 5MB</p>';
            }
        })
        .catch(err => {
            showToast('Error: ' + err.message, 'error');
            uploadArea.innerHTML = '<i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #4CAF50; margin-bottom: 10px; display: block;"></i><p><strong>Click to upload or drag and drop</strong></p><p style="font-size: 12px; color: #999; margin: 0;">JPG, PNG, GIF up to 5MB</p>';
        });
    }

    // Profile form submission
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        btn.disabled = true;

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Profile updated successfully!', 'success');
            } else {
                showToast('Error: ' + (data.message || 'Failed to update'), 'error');
            }
        })
        .catch(err => {
            showToast('Error: ' + err.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
    </script>
</body>
</html>

