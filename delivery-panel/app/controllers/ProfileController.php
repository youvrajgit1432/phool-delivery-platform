<?php
/**
 * Profile Controller
 * Handles rider profile management and updates
 */

namespace Phool\DeliveryPanel\Controllers;

use Phool\DeliveryPanel\Models\Rider;

// Ensure models are properly loaded with their dependencies
require_once dirname(__DIR__) . '/models/BaseModel.php';
require_once dirname(__DIR__) . '/models/Rider.php';

class ProfileController {
    /**
     * Show rider profile page
     */
    public function index() {
        if (!isset($_SESSION['rider_id'])) {
            require_once __DIR__ . '/../../app/helpers/url.php';
            header('Location: ' . app_url('/login'));
            exit;
        }

        $riderId = $_SESSION['rider_id'];
        $riderModel = new Rider();
        $riderData = $riderModel->find($riderId);

        if (!$riderData) {
            require_once __DIR__ . '/../../app/helpers/url.php';
            header('Location: ' . app_url('/logout'));
            exit;
        }

        // Convert to object for easier access
        $rider = is_object($riderData) ? $riderData : (object) $riderData;

        $data = [
            'rider' => $rider,
            'page_title' => 'My Profile'
        ];

        extract($data);
        
        // Include dashboard view - header and footer are handled by bootstrap/app.php
        require_once __DIR__ . '/../views/profile/dashboard.php';
    }

    /**
     * Update rider profile
     */
    public function update() {
        // Prevent any output before JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        if (!isset($_SESSION['rider_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Invalid request'], 400);
        }

        $riderId = $_SESSION['rider_id'];
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Prepare update data
            $updateData = [];
            $allowedFields = [
                'first_name', 'last_name', 'phone', 'email',
                'home_address', 'city', 'state', 'district', 'postal_code',
                'bank_name', 'account_number', 'account_holder_name',
                'license_number', 'license_expiry',
                'vehicle_type', 'vehicle_number',
                'emergency_contact_name', 'emergency_contact_phone'
            ];

            foreach ($allowedFields as $field) {
                if (isset($_POST[$field]) && strlen(trim($_POST[$field])) > 0) {
                    $updateData[$field] = $_POST[$field];
                }
            }

            if (!empty($updateData)) {
                // Build UPDATE query
                $setParts = [];
                $values = [];
                foreach ($updateData as $field => $value) {
                    $setParts[] = "$field = ?";
                    $values[] = $value;
                }
                $values[] = $riderId;

                $sql = "UPDATE riders SET " . implode(", ", $setParts) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                if ($isAjax) {
                    $this->jsonResponse(['success' => true, 'message' => 'Profile updated successfully!']);
                } else {
                    $_SESSION['success'] = 'Profile updated successfully!';
                    header('Location: ' . app_url('/profile'));
                    exit;
                }
            }

            // No data to update
            if ($isAjax) {
                $this->jsonResponse(['error' => 'No changes made'], 200);
            } else {
                header('Location: ' . app_url('/profile'));
                exit;
            }

        } catch (\Exception $e) {
            if ($isAjax) {
                $this->jsonResponse(['error' => $e->getMessage()], 500);
            } else {
                $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
                header('Location: ' . app_url('/profile'));
                exit;
            }
        }
    }

    /**
     * Update password
     */
    public function updatePassword() {
        // Prevent any output before JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        if (!isset($_SESSION['rider_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Invalid request'], 400);
        }

        $riderId = $_SESSION['rider_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            if ($isAjax) {
                $this->jsonResponse(['error' => 'All password fields are required'], 400);
            } else {
                $_SESSION['error'] = 'All password fields are required';
                header('Location: ' . app_url('/profile'));
                exit;
            }
        }

        if ($newPassword !== $confirmPassword) {
            if ($isAjax) {
                $this->jsonResponse(['error' => 'New passwords do not match'], 400);
            } else {
                $_SESSION['error'] = 'New passwords do not match';
                header('Location: ' . app_url('/profile'));
                exit;
            }
        }

        if (strlen($newPassword) < 6) {
            if ($isAjax) {
                $this->jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
            } else {
                $_SESSION['error'] = 'Password must be at least 6 characters';
                header('Location: ' . app_url('/profile'));
                exit;
            }
        }

        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM riders WHERE id = ?");
            $stmt->execute([$riderId]);
            $riderPassword = $stmt->fetchColumn();

            if (!$riderPassword) {
                if ($isAjax) {
                    $this->jsonResponse(['error' => 'Rider not found'], 404);
                } else {
                    $_SESSION['error'] = 'Rider not found';
                    header('Location: ' . app_url('/logout'));
                    exit;
                }
            }

            if (!password_verify($currentPassword, $riderPassword)) {
                if ($isAjax) {
                    $this->jsonResponse(['error' => 'Current password is incorrect'], 401);
                } else {
                    $_SESSION['error'] = 'Current password is incorrect';
                    header('Location: ' . app_url('/profile'));
                    exit;
                }
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE riders SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $riderId]);

            if ($isAjax) {
                $this->jsonResponse(['success' => true, 'message' => 'Password updated successfully!']);
            } else {
                $_SESSION['success'] = 'Password updated successfully!';
                header('Location: ' . app_url('/profile'));
                exit;
            }

        } catch (\Exception $e) {
            if ($isAjax) {
                $this->jsonResponse(['error' => $e->getMessage()], 500);
            } else {
                $_SESSION['error'] = 'Failed to update password';
                header('Location: ' . app_url('/profile'));
                exit;
            }
        }
    }

    public function changePassword() {
        // Alias for updatePassword
        $this->updatePassword();
    }

    /**
     * Send JSON response
     */
    protected function jsonResponse($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    public function uploadDocuments() {
        // Placeholder for future document uploads
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture() {
        // Prevent any output before JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        if (!isset($_SESSION['rider_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Invalid request'], 400);
        }

        $riderId = $_SESSION['rider_id'];

        // Check if file is uploaded
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['error' => 'No file uploaded or upload error'], 400);
        }

        $file = $_FILES['profile_picture'];
        
        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->jsonResponse(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed'], 400);
        }

        // Validate file size (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $this->jsonResponse(['error' => 'File size must be less than 5MB'], 400);
        }

        try {
            // Create uploads directory if it doesn't exist
            $uploadsDir = dirname(__DIR__, 2) . '/public/uploads/profile-pictures/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }

            // Generate unique filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'rider_' . $riderId . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadsDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $this->jsonResponse(['error' => 'Failed to save the file'], 500);
            }

            // Generate relative URL for database storage
            $fileUrl = app_url('/uploads/profile-pictures/' . $fileName);

            // Update database
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'phool_delivery_demo';
            $user = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $pdo = new \PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName,
                $user,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Delete old profile picture if exists
            $stmt = $pdo->prepare("SELECT profile_image_url FROM riders WHERE id = ?");
            $stmt->execute([$riderId]);
            $oldImageUrl = $stmt->fetchColumn();

            if ($oldImageUrl) {
                // Extract file name from URL and delete
                $oldFileName = basename(parse_url($oldImageUrl, PHP_URL_PATH));
                $oldFilePath = $uploadsDir . $oldFileName;
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }

            // Update profile image URL
            $stmt = $pdo->prepare("UPDATE riders SET profile_image_url = ? WHERE id = ?");
            $stmt->execute([$fileUrl, $riderId]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Profile picture updated successfully!',
                'image_url' => $fileUrl
            ], 200);

        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function getProfile() {
        // Alias for index
        $this->index();
    }
}
